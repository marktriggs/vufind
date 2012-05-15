<?php
/**
 * Database-driven authentication module.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 */
require_once 'Authentication.php';
require_once 'services/MyResearch/lib/User.php';
require_once 'services/MyResearch/lib/Capabilities.php';

/**
 * Database-driven authentication module.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Mark Triggs <mark@dishevelled.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 */
class DatabaseAuthentication implements Authentication
{
    private $passHasher;


    public function __construct($configurationFilePath = '')
    {
        global $configArray;

        $hashClass = 'PhpassWrapper';

        if (isset($configArray['Authentication']['password_hash_method'])) {
            $hashClass = $configArray['Authentication']['password_hash_method'];
            $hashClass = preg_replace('/[^A-Za-z0-9_]/', '', $hashClass);
        }

        require_once sprintf("%s/%s.php", dirname(__FILE__), $hashClass);
        $this->passHasher = new $hashClass($configArray);
    }


    /**
     * Set the appropriate password properties for the user.  This might be the
     * unhashed 'password' column, the hashed 'hashed_password' column, or
     * both.
     *
     * @param object the $user object whose properties will be set.  Note that
     *               the caller is responsible for persisting the object to the
     *               DB.
     * @param string $password to set
     *
     * @return array
     * @access public
     */
    public function setPasswordForUser($user, $password)
    {
        $password_mode = Capabilities::getCapability("PASSWORD_MODE", "plaintext");

        if ($password_mode === "plaintext" || $password_mode === "hybrid") {
            $user->password = $password;
        }

        if ($password_mode === "hashed" || $password_mode === "hybrid") {
            $hashedPassword = $this->passHasher->hashPassword($password);
            $user->hashed_password = $hashedPassword;
        }

        return $user;
    }


    /**
     * Attempt to authenticate the current user.
     *
     * @return object User object if successful, PEAR_Error otherwise.
     * @access public
     */
    public function authenticate()
    {
        $username = $_POST['username'];
        $password = $_POST['password'];

        if (($username == '') || ($password == '')) {
            return new PEAR_Error('authentication_error_blank');
        }

        $user = new User();
        $user->username = $username;

        if (!$user->find(true)) {
            return new PEAR_Error('authentication_error_invalid');
        }

        if ($user->hashed_password) {
            /* If the user has an encrypted password we'll use it. */
            if ($this->passHasher->checkPassword($password,
                                                 $user->hashed_password)) {
                return $user;
            }
        } else if ($user->password === $password) {
            return $user;
        }

        /* No encrypted password and the password they provided was
         * wrong */
        return new PEAR_Error('authentication_error_invalid');
    }
}
?>
