<?php

/**
 * Script to add the new 'capabilities' table.
 *
 * command line arguments:
 *   * MySQL admin username
 *   * MySQL admin password
 *   * path to previous version installation
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2012.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Upgrade_Tools
 * @author   Mark Triggs <mark@dishevelled.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/migration_notes Wiki
 */


require_once dirname(__FILE__) . '/upgrade_utils.php';


// Die if we don't have enough parameters:
if (!isset($argv[3]) || !isset($argv[2]) || !isset($argv[1])) {
    die("\n\nMissing command line parameter... aborting database upgrade.\n\n");
}

$mysql_admin_user = $argv[1];
$mysql_admin_pw = $argv[2];
$vufind_dir = $argv[3];

$configArray = readVuFindConfig($vufind_dir);

?>

#### Database upgrade ####

This script will upgrade your VuFind database from the previous version to the new
release.  It is recommended that you make a backup before proceeding with this
script, just to be on the safe side!

<?php

$mysql = readMySQLSettings($configArray);

echo "\nUsing the following values to access your MySQL database:\n";
echo "MySQL admin username: " . $mysql_admin_user . "\n";
echo "MySQL VuFind username: " . $mysql['vufind'] . "\n";
echo "MySQL database: " . $mysql['database'] . "\n";
echo "MySQL host: " . $mysql['host'] . "\n";

$line = "n";

while ($line != "y") {
    $line = getInput("\nDo you want to proceed? [y/n] ");
    if ($line == "n") {
        exit(0);
    }
}

// create a PDO with connection to database
$dsn = 'mysql:host=' . $mysql['host'] . ';dbname=' . $mysql['database'];
try {
    $db = new PDO($dsn, $mysql_admin_user, $mysql_admin_pw);
} catch(PDOException $e) {
    echo "Error connecting to database -- " . $e->getMessage() . "\n";
    exit(0);
}
if (!$db) {
    echo "Error connecting to Database\n";
    exit(0);
}

// add new tables:
addNewTables();


/**
 * Routine to add new tables to the database.
 *
 * @return void
 */
function addNewTables()
{
    // Get a list of all existing tables (so we can avoid duplicates):
    $sql = executeSQL("SHOW TABLES;");
    $tmp = $sql->fetchAll();
    $tables = array();
    foreach ($tmp as $current) {
        $tables[] = $current[0];
    }

    if (in_array('capabilities', $tables)) {
        echo "Skipping table creation -- capabilities already exists.\n";
    } else {
        executeSQL(getCreationCommand('capabilities'));
    }

    echo "Done creating tables.\n\n";
}

?>
