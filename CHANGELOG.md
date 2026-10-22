# Changelog

All notable changes to OWMigration. Versions follow the release tags on GitHub.

## 1.1.5 - 2026-10-21

The first release for Exponential 6.x and PHP 8. It also carries the upstream work made after 1.1.4 (2013 - 2015):
the Description pages of the Migrations tab, the eZ Tags datatype handler, removeFromGroup() for workflow
migrations, another selection type for object relation list attributes, role assignment by subtree path,
tables left out of the database check (owmigration.ini [CheckDatabase] NoCheckOnTables[]) and fixes for newer PHP
versions.

- Updated: the Migrations tab names the `database` icon (`Icon=database` in `[Topmenu_owmigration]` of menu.ini),
  which the admin4 design draws before the tab's name.
- Fixed: the template operators (camelize and the display_*_migration_class operators of the code generator pages)
  register on PHP 8; their class used a PHP 4 style constructor.
- Updated: README.md replaces README with a complete guide: installation with Composer and by hand, configuration,
  writing and running migrations with examples, the Migrations tab, the API, permissions, known limits and
  troubleshooting. doc/INSTALL points to it.
- Added: composer.json (`se7enxweb/owmigration`, installed as `extension/owmigration`, PHP 8.0 or newer) and
  ezinfo.php.
- Updated: extension.xml states version 1.1.5, the license, the repository, the bundled GeSHi 1.0.8.11 and the
  correctly spelled owscriptlogger dependency.
- Added: this CHANGELOG.md.

## 1.1.4 - 2013-09-20 and earlier

Released by Open Wide: 1.0.0 (2013-03-12) to 1.1.4 (2013-09-20). See the commit history of the tags v1.0.0 to v1.1.4
for their changes.
