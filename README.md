# OWMigration

OWMigration brings versioned, repeatable migrations to Exponential 6.x. Instead of clicking content classes, roles,
workflows and object states together by hand on every server, you describe the changes in small PHP classes, keep
them in your extension next to your code, and run them with one command on development, staging and production.
Each migration has an `up()` method that applies it and a `down()` method that undoes it, and OWMigration records
which version each extension has reached.

It also adds a **Migrations** tab to the administration (shown with the `database` icon in the admin4 design) that
writes those migration classes for you from what already exists in the database, and shows which migration version
each extension is at.

It is meant for developers and integrators who deploy the same site to several environments or work on it as a
team.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Writing migrations](#writing-migrations)
- [Running migrations](#running-migrations)
- [The Migrations tab](#the-migrations-tab)
- [Migration API reference](#migration-api-reference)
- [Permissions](#permissions)
- [Upgrading](#upgrading)
- [Known limits](#known-limits)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)
- [Credits and history](#credits-and-history)

## Requirements

- Exponential 6.x (the legacy kernel)
- PHP 8.0 or newer, with the `zip` extension for the "Export all" buttons of the code generator
- The **OWScriptLogger** extension (<https://github.com/se7enxweb/OWScriptLogger>): every migration step logs
  through it, and the migration command needs it to run.
- A user with the login `admin`: the command line scripts run as that user.

## Installation

The extension must be installed in a directory named **`owmigration`** (lowercase): its settings register the
module, templates, template operators and translations under that name, and its transformation rules point to
`extension/owmigration/classes/...`.

### With Composer

From the root of your Exponential installation:

```bash
composer require se7enxweb/owmigration
```

The package type is `ezpublish-legacy-extension`, so the legacy extension installer places it in
`extension/owmigration/`. Install OWScriptLogger as well (it is not on Packagist; copy it by hand as below, into
`extension/owscriptlogger/`).

### By hand

1. Download the latest release from <https://github.com/se7enxweb/OWMigration/releases> (or clone the repository)
   into `extension/owmigration/`:

   ```bash
   git clone https://github.com/se7enxweb/OWMigration.git extension/owmigration
   git clone https://github.com/se7enxweb/OWScriptLogger.git extension/owscriptlogger
   ```

2. Activate both extensions in `settings/override/site.ini.append.php`:

   ```ini
   [ExtensionSettings]
   ActiveExtensions[]=owscriptlogger
   ActiveExtensions[]=owmigration
   ```

### After either installation

1. Create the table that records the migration versions. With MySQL or MariaDB:

   ```bash
   mysql -u <user> -p <database> < extension/owmigration/sql/mysql.sql
   ```

   The same table is described in `share/db_schema.dba`; the migration command also compares the database with the
   schemas of all active extensions and offers to create what is missing (see
   [Running migrations](#running-migrations)).

2. Regenerate the autoloads and clear the caches, from the installation root:

   ```bash
   php bin/php/ezpgenerateautoloads.php -e
   php bin/php/ezcache.php --clear-all
   ```

## Configuration

### site.ini: the extensions that have migrations

Every extension whose migrations OWMigration should run is listed in `[MigrationSettings]` of `site.ini`, for
example in `settings/override/site.ini.append.php`:

```ini
[MigrationSettings]
MigrationExtensions[]=mysite
MigrationExtensions[]=mysite_newsletter
```

The migrations of an extension live in its `migrations/` directory, for example `extension/mysite/migrations/`.

### owmigration.ini: tables left out of the database check

Before it migrates, the command compares the database with the schema files (see below). Tables that are not part
of the content repository can be left out of that comparison with `[CheckDatabase] NoCheckOnTables[]`. The shipped
file lists the MySQL system tables; add your own in `settings/override/owmigration.ini.append.php`:

```ini
<?php /* #?ini charset="utf-8"?

[CheckDatabase]
NoCheckOnTables[]=my_import_staging
NoCheckOnTables[]=my_statistics

*/ ?>
```

### Settings the extension registers itself

You do not need to change these; they are listed so you know what the extension adds:

- `module.ini`: the `owmigration` module.
- `menu.ini`: the **Migrations** tab (`[Topmenu_owmigration]`, `Icon=database`, opens `owmigration/dashboard`, needs
  `owmigration/read`) and its left menus.
- `site.ini`: the template operators (`ExtensionAutoloadPath[]=owmigration`) and the translations
  (`TranslationExtensions[]=owmigration`; a French translation is included).
- `transform.ini`: the `camelize`, `filename` and `humanize` character transformations the code generator uses.

## Writing migrations

A migration is a PHP file in `extension/<your_extension>/migrations/`. Its name starts with a three digit version
number and an underscore: `001_create_article_class.php`, `002_editor_role.php`, `003_...`. The file declares one
class (any name, but unique in the whole installation, so include your extension's name) with an `up()` and,
ideally, a `down()` method:

```php
<?php

class MySite_Migration_001
{
    public function up()
    {
        $migration = new OWMigrationContentClass();
        $migration->startMigrationOn( 'article' );
        $migration->createIfNotExists();

        $migration->name = array(
            'eng-GB' => 'Article',
            'always-available' => 'eng-GB',
        );
        $migration->contentobject_name = '<title>';
        $migration->is_container = false;

        $migration->addAttribute( 'title', array(
            'data_type_string' => 'ezstring',
            'name' => array( 'eng-GB' => 'Title', 'always-available' => 'eng-GB' ),
            'is_required' => true,
        ) );
        $migration->addAttribute( 'body', array(
            'data_type_string' => 'ezxmltext',
            'name' => array( 'eng-GB' => 'Body', 'always-available' => 'eng-GB' ),
            'data_int1' => 10,
        ) );
        $migration->addToContentClassGroup( 'Content' );
        $migration->end();
    }

    public function down()
    {
        $migration = new OWMigrationContentClass();
        $migration->startMigrationOn( 'article' );
        $migration->removeClass();
    }
}
```

`end()` stores the changes; call it after the last change of each class, role, workflow or state group. A role:

```php
$migration = new OWMigrationRole();
$migration->startMigrationOn( 'Editor' );
$migration->createIfNotExists();
$migration->addPolicy( 'content', '*' );
$migration->addPolicy( 'user', 'login' );
$migration->assignToUserGroup( 'Editors', 'Subtree', '/1/2/' );
$migration->end();
```

A workflow attached to the "after publish" trigger:

```php
$migration = new OWMigrationWorkflow();
$migration->startMigrationOn( 'Publish approval' );
$migration->createIfNotExists();
$migration->addEvent( 'Wait until date', 'event_ezwaituntildate' );
$migration->assignToTrigger( 'content', 'publish', 'after' );
$migration->end();
```

An object state group with two states, named per language:

```php
$migration = new OWMigrationStateGroup();
$migration->startMigrationOn( 'review' );
$migration->createIfNotExists();
$migration->addState( 'pending', array( 'eng-GB' => 'Pending' ) );
$migration->addState( 'approved', array( 'eng-GB' => 'Approved' ) );
$migration->end();
```

The `migrations/` directory of this extension holds eight more complete examples (`001_owmigration_feedbackform.php`
to `008_owmigration_datatype_handler.php`). You rarely have to write a class by hand: the
[Migrations tab](#the-migrations-tab) generates it from the existing class, role, workflow or state group.

A migration class that extends `OWMigrationBase` can also stop at a step that needs a person, with
`$this->manualStep( 'Upload the logo', 'Upload the new logo in Media > Images.' );`. The command prints the text and
waits for Enter; an answer starting with `warn:` or `error:` is logged as a warning or an error.

## Running migrations

The command runs from the root of the installation:

```bash
# Show the current and the latest version of every extension in MigrationExtensions[]
php extension/owmigration/bin/migrate.php --list

# Migrate every extension in MigrationExtensions[] to its latest version
php extension/owmigration/bin/migrate.php

# Migrate one extension to its latest version
php extension/owmigration/bin/migrate.php --extension=mysite

# Migrate one extension up or down to version 3 (runs every step in between)
php extension/owmigration/bin/migrate.php --extension=mysite --version=3

# Run only the up() (or down()) method of version 3, whatever the recorded version is
php extension/owmigration/bin/migrate.php --extension=mysite --version=3 --force=up
```

Before migrating, the command reads `share/db_schema.dba` and the `share/db_schema.dba` of every active extension,
compares them with the database and, if they differ, prints the SQL and asks `(y/n)` whether to run it. Answer `n`
to skip it. The versions reached are stored in the `owmigration_version` table, so running the command again only
runs the new migrations.

`bin/specify.php --extension=mysite --classes` writes the definition of every content class as JSON to
`extension/mysite/migrations/spec_classes.json`, a snapshot you can keep under version control and compare between
environments.

## The Migrations tab

Click **Migrations** in the top menu of the admin. The pages are:

- **Dashboard** (`owmigration/dashboard`): every extension in `MigrationExtensions[]` with its current and latest
  version; **View all versions** lists each version and its status.
- **Code generator** (`owmigration/codegenerator_classes`, `_roles`, `_workflows`, `_state_groups`): pick a content
  class, role, workflow or state group and click **Display code** to see the migration class that recreates it, with
  syntax highlighting. **Export code** downloads it as a PHP file; **Export all classes code** (the button has this label on all four pages) downloads all of them as a ZIP
  archive. Put the files in your extension's `migrations/` directory and give them version numbers.
- **Description** (`owmigration/description_classes`, `_roles`, `_workflows`, `_state_groups`): a read-only
  overview of all content classes with their attributes and settings, all roles with their policies, all workflows
  and all state groups.

## Migration API reference

| Class | Main methods |
| --- | --- |
| `OWMigrationContentClass` | `startMigrationOn( $identifier )`, `createIfNotExists()`, `createFrom( $classIdentifier )`, `addAttribute()`, `updateAttribute()`, `removeAttribute()`, `hasAttribute()`, `getAttribute()`, `addToContentClassGroup()`, `removeFromContentClassGroup()`, `removeClass()`, `end()`; class properties (`name`, `description`, `identifier`, `contentobject_name`, `is_container` ...) are set as object properties |
| `OWMigrationRole` | `startMigrationOn( $name )`, `createIfNotExists()`, `addPolicy()`, `hasPolicy()`, `removePolicies()`, `assignToUser()`, `assignToUserGroup()`, `unassignToUser()`, `unassignToUserGroup()`, `removeRole()`, `end()` |
| `OWMigrationWorkflow` | `startMigrationOn( $name )`, `createIfNotExists()`, `addEvent()`, `updateEvent()`, `removeEvent()`, `hasEvent()`, `getEvent()`, `addToGroup()`, `removeFromGroup()`, `assignToTrigger()`, `unassignFromTrigger()`, `removeWorkflow()`, `end()` |
| `OWMigrationStateGroup` | `startMigrationOn( $identifier )`, `createIfNotExists()`, `update()`, `addState()`, `updateState()`, `removeState()`, `removeStateGroup()`, `end()` |

Class attributes of these datatypes are read and written with their datatype settings: ezbinaryfile, ezboolean,
ezcountry, ezdate, ezdatetime, ezenum, ezfloat, ezimage, ezinteger, ezmultiplexer, ezobjectrelation,
ezobjectrelationlist, ezstring, eztags, eztext, eztime and ezxmltext. Other datatypes use a generic handler that
copies the `data_*` fields. Workflow events of the approve type have their own handler; other event types use a
generic one. A handler for your own datatype is a class named `<DatatypeClass>MigrationHandler` with static
`toArray()` and `fromArray()` methods, picked up by the autoloads.

### Template operators

The extension registers five template operators, used by the code generator pages:

```smarty
{'my class name'|camelize()}                         {* MyClassName *}
{$class_identifier|display_content_migration_class()}
{$role_id|display_role_migration_class()}
{$workflow_id|display_workflow_migration_class()}
{$state_group_id|display_state_group_migration_class()}
```

The `display_*` operators return the generated migration class as highlighted HTML.

## Permissions

The module has one policy function, `owmigration/read`. It opens every page of the Migrations tab, and the tab is
only shown to users who have it. Administrators with `*/*` already have it. The command line scripts do not check
policies; they run as the `admin` user.

## Upgrading

Replace the files in `extension/owmigration/` (or run `composer update se7enxweb/owmigration`), keep your settings in
`settings/override/`, then regenerate the autoloads and clear the caches. The `owmigration_version` table has not
changed between versions.

## Known limits

- The command line scripts log in as the user with the login `admin`; on a site without that user they fail.
- The database check asks its `(y/n)` question on standard input, so a non-interactive run (a deployment pipeline)
  waits for an answer. Pipe one in: `echo n | php extension/owmigration/bin/migrate.php`.
- `bin/specify.php` accepts `--roles`, `--workflows` and `--object_states`, but only writes the content classes.
- Migration classes are loaded with `require_once` and must have names that are unique in the installation.
- Version numbers are three digits, so an extension can have up to 999 migrations.

## Troubleshooting

- **`Class "OWScriptLogger" not found`:** OWScriptLogger is not installed or not active, or the autoloads were not
  regenerated after activating it.
- **"Migration is not enabled for this extension":** add the extension to `[MigrationSettings] MigrationExtensions[]`.
- **"Could not find migration class for migration step":** the file of that version is missing in `migrations/`, or
  its name does not start with the three digit version and an underscore.
- **No Migrations tab, or "module not found":** the directory is not named `owmigration`, the extension is not
  active, or the INI cache is stale (`php bin/php/ezcache.php --clear-tag=ini`). Check that your role has
  `owmigration/read`.
- **The code generator shows the operator name instead of code:** clear the template caches
  (`php bin/php/ezcache.php --clear-tag=template`).

## Contributing

Issues and pull requests are welcome at <https://github.com/se7enxweb/OWMigration>.

## License

GNU General Public License, version 2. See [LICENSE](LICENSE). The bundled GeSHi syntax highlighter in `lib/geshi/`
keeps its own license, see `lib/geshi/docs/COPYING`.

## Credits and history

OWMigration was written by Open Wide (<http://www.openwide.fr>), mainly by Madeline Veyrenc with Fabien Jarnet,
Simon Boyer and Anis Jrad, from 2013 to 2015 for eZ Publish 4.x, the system that later continued as Exponential.
7x (<https://se7enx.com>) maintains it for Exponential 6.x and PHP 8. See [CHANGELOG.md](CHANGELOG.md) for the
changes per release.
