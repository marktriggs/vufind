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


$configArray = readVuFindConfig($vufind_dir);

?>

#### Database upgrade ####

This script will create encrypted versions of the stored passwords for all
users in VuFind's database.  While this script does not remove the old
passwords, it is recommended that you make a backup before proceeding with
this script, just to be on the safe side!

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

    if ($password_mode === 'hashed') {
        die("\n\nAborted!  We were weren't expecting to see " .
            "PASSWORD_MODE set to 'hashed'\n");
    }

    Capabilities::setCapability('PASSWORD_MODE', 'hybrid');

    $authN = AuthenticationFactory::initAuthentication('DB');

    $user = new User();
    if ($user->find(false)) {
        while ($user->fetch()) {
            if ($user->password != '' && !$user->hashed_password) {
                echo "Encrypted and storing password for " . $user->username . "... ";
                $authN->setPasswordForUser($user, $user->password, true);
                $user->update();
                echo "done.\n";
            }
        }
    }

?>
