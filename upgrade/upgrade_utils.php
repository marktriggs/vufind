<?php

/**
 * Utilities used by upgrade scripts
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
 */

/**
 * Support function to execute a SQL statement.
 *
 * @param string $sqlStatement SQL to execute.
 * @param array  $bindParams   Bind parameters to pass with SQL.
 *
 * @return object              PDO SQL object (on success; does not return on error).
 */
function executeSQL($sqlStatement, $bindParams = array())
{
    global $db;

    $sql = $db->prepare($sqlStatement);
    if (!$sql->execute($bindParams)) {
        die("Problem executing: {$sqlStatement}");
    }
    return $sql;
}


/**
 * readline() does not exist on Windows.  This is a simple wrapper for portability.
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



/**
 * Read the VuFind ini file
 *
 * @return mixed The VuFind $configArray
 */
function readVuFindConfig($vufind_dir)
{
    $basePath = $vufind_dir . '/web/conf';

    if (!file_exists($basePath . '/config.ini')) {
        die("\n\nCan't open {$basePath}/config.ini; aborting database upgrade.\n\n");
    }

    require_once dirname(__FILE__) . '/../web/sys/ConfigArray.php';
    $configArray = readConfig($basePath);

    return $configArray;
}


/**
 * Read MySQL connection settings
 *
 * @return mixed Connection details for MySQL
 */
function readMySQLSettings($configArray)
{
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

    $mysql_host = $mysql_conn[3];
    $mysql_db = $mysql_conn[4];

    if ($mysql_db=="") {
        $mysql_db="vufind";
    }

    return array('vufind' => $mysql_conn[1],
                 'database' => $mysql_db,
                 'host' => $mysql_host);
}


/**
 * Extract the table creation DDL from mysql.sql
 *
 * @return string The create command for the requested table
 */
function getCreationCommand($table)
{
    $install_sql = dirname(__FILE__) . '/../mysql.sql';

    $fh = fopen($install_sql, "r");

    if (!$fh) {
        die("\nCould not open '$install_sql' for reading");
    }

    while (($line = fgets($fh)) && !preg_match("/^\s*CREATE TABLE `$table` /", $line)) {
    }

    $result = array();

    if ($line) {
        do {
            array_push($result, $line);
            $line = fgets($fh);
        } while ($line && !preg_match("/.*;$/", $line));

        if ($line) {
            array_push($result, $line);
        }
    }

    fclose($fh);

    if (count($result) > 0) {
        return implode(" ", $result);
    } else {
        throw new Exception("Failed to find DDL snippet for '$table'");
    }
}