<?php
/**
 * OWMigration extension information, read by the about page.
 *
 * @copyright Copyright (C) Open Wide http://www.openwide.fr/, Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package owmigration
 */

class owmigrationInfo
{
    static function info()
    {
        return array(
            'Name' => 'OWMigration',
            'Version' => "1.1.5",
            'Copyright' => 'Copyright (C) Open Wide http://www.openwide.fr/, Copyright (C) 2024 - 2026 7x',
            'License' => 'GNU General Public License v2.0',
            'Info_url' => 'https://github.com/se7enxweb/OWMigration',
            'Includes the following third-party software' => array(
                'Name' => 'GeSHi - Generic Syntax Highlighter',
                'Version' => '1.0.8.11',
                'License' => 'GNU General Public License v2.0 (or any later version)',
                'For more information' => 'lib/geshi/docs/README'
            )
        );
    }
}

?>
