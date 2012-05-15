<?php

/**
 * Script to upgrade VuFind database to enable encrypted passwords.
 *
 * command line arguments:
 *   * MySQL admin username
 *   * MySQL admin password
 *   * path to previous version installation
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
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

$vufind_dir = isset($argv[1]) ? $argv[1] : false;

if (!$vufind_dir) {
    die("\nUsage: " . $argv[0] . " </path/to/vufind>\n\n");
}

$vufind_dir = realpath($vufind_dir);

$configArray = readVuFindConfig($vufind_dir);

?>

#### Database upgrade ####

This script will remove the stored plaintext passwords for users that have had
encrypted passwords created for them.  It is recommended that you make a backup
before proceeding with this script, just to be on the safe side!

<?php

    $line = "n";
    while ($line != "y") {
        $line = getInput("\nDo you want to proceed? [y/n] ");
        if ($line == "n") {
            exit(0);
        }
    }

    chdir($vufind_dir . "/web");

    require_once 'sys/ConnectionManager.php';
    require_once 'services/MyResearch/lib/User.php';
    require_once 'services/MyResearch/lib/Capabilities.php';
    require_once 'sys/authn/AuthenticationFactory.php';

    ConnectionManager::connectToDatabase();

    $password_mode = Capabilities::getCapability('PASSWORD_MODE', 'plaintext');

    if ($password_mode !== 'hybrid') {
        die("\n\nAborted!  We were expecting to see " .
            "PASSWORD_MODE set to 'hybrid'\n");
    }

    Capabilities::setCapability('PASSWORD_MODE', 'hashed');


    $authN = AuthenticationFactory::initAuthentication('DB');

    $rollback_file = $vufind_dir . "/upgrade/" . "vufind-rollback.sql";
    $rollback = fopen($rollback_file, "a");

    if (!$rollback) {
        die("Failed to open 'vufind-rollback.sql' for writing.  Aborting.");
    }

    fprintf($rollback, "########## %s - BEGIN VUFIND ROLLBACK LOG ##########\n\n",
            date(DATE_RFC822));

    $user = new User();
    if ($user->find(false)) {
        while ($user->fetch()) {
            if ($user->password != '' && $user->hashed_password) {
                echo "Removing stored password for " . $user->username . "... ";

                fprintf($rollback,
                        "update user set password = '%s' where username = '%s';\n",
                        mysql_real_escape_string($user->password),
                        mysql_real_escape_string($user->username));

                $user->password = '';
                $user->update();
                echo "done.\n";
            }
        }
    }

    fprintf($rollback, "\n########## %s - END VUFIND ROLLBACK LOG ##########\n",
            date(DATE_RFC822));

    fclose($rollback);

?>

#### Upgrade complete ####

A script that can be used to recover users stored plaintext password has been
written to:

  <? echo $rollback_file ?>.

Since it contains users' passwords, please store this file somewhere secure,
and remove it once you are confident you won't need it again.


