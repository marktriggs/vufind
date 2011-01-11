<?php
/**
 * Script to upgrade VuFind database between versions.
 *
 * command line arguments:
 *   * MySQL admin username
 *   * MySQL admin password
 *   * path to RC 1 installation
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2009.
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
 * @author   Till Kinstler <kinstler@gmail.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/migration_notes Wiki
 */

// Die if we don't have enough parameters:
if (!isset($argv[3]) || !isset($argv[2]) || !isset($argv[1])) {
    die("\n\nMissing command line parameter... aborting database upgrade.\n\n");
}

$mysql_admin_user = $argv[1];
$mysql_admin_pw = $argv[2];
$old_config = $argv[3];

// Try to read the ini file:
$iniFile = $old_config . '/web/conf/config.ini';
if (!file_exists($iniFile)) {
    die("\n\nProblem opening {$iniFile}... aborting database upgrade.\n\n");
}
$configArray = parse_ini_file($iniFile, true);

?>

#### Database upgrade ####

This script will upgrade your VuFind database from 1.0 RC2 to 1.0.
Only minor adjustments will be made to compensate for a small bug in RC2;
there will be no structural changes.  It is still recommended that you
make a backup before proceeding with this script, just to be on the safe
side!

<?php

// get connection credentials from config.ini
$match = preg_match(
    "/mysql:\/\/([^:]+):([^@]+)@([^\/]+)\/(.+)/",
    $configArray['Database']['database'], $mysql_conn
);
if (!$match) {
    echo "Can't determine data needed to access your MySQL database. You have " .
        $configArray['Database']['database'] .
        "\nas connection string in your config.ini.\n";
    exit(0);
}

if ($mysql_admin_user=="") {
    $mysql_admin_user="root";
}
$mysql_host = $mysql_conn[3];
$mysql_db = $mysql_conn[4];
if ($mysql_db=="") {
    $mysql_db="vufind";
}

echo "\nUsing the following values to access your MySQL database:\n";
echo "MySQL admin username: " . $mysql_admin_user . "\n";
echo "MySQL VuFind username: " . $mysql_conn[1] . "\n";
echo "MySQL database: " . $mysql_db . "\n";
echo "MySQL host: " . $mysql_host . "\n";

$line = "n";

while ($line != "y") {
    $line = getInput("\nDo you want to proceed? [y/n] ");
    if ($line == "n") {
        exit(0);
    }
}

// create a PDO with connection to database
$dsn = 'mysql:host=' . $mysql_host . ';dbname=' . $mysql_db;
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

// fix lists with blank names:
$sqlStatement = "update user_list set title='Untitled' where title='';";
$sql = $db->prepare($sqlStatement);
if (!$sql->execute()) {
    die("Problem executing: {$sqlStatement}");
}

// all done!
echo "Update complete -- " . $sql->rowCount() . " row(s) corrected.\n\n";

/**
 * readline() does not exist on Windows.  This is a simple wrapper for portablility.
 *
 * @param string $prompt Prompt to display to the user.
 *
 * @return string        User-entered response.
 */
function getInput($prompt)
{
    // Standard function for most uses
    if (function_exists('readline')) {
        $in = readline($prompt);
        return $in;
    } else {
        // Or use our own if it doesn't exist (windows)
        print $prompt;
        $fp = fopen("php://stdin", "r");
        $in = fgets($fp, 4094); // Maximum windows buffer size
        fclose($fp);
        // Seems to keep the carriage return if you don't trim
        return trim($in);
    }
}

?>
