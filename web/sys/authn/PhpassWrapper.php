<?php
/**
 * Wrapper to expose Phpass functionality to VuFind
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
 * @package  Authentication
 * @author   Mark Triggs <mark@dishevelled.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 */


require_once 'sys/authn/PasswordHasher.php';
require_once 'sys/authn/PasswordHash.php';

/**
 * Wrapper to expose Phpass functionality to VuFind
 *
 * @category VuFind
 * @package  Authentication
 * @author   Mark Triggs <mark@dishevelled.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 */
class PhpassWrapper implements PasswordHasher
{
    private $phpass;


    public function __construct($configArray)
    {
        $this->phpass = new PasswordHash(8, true);
    }


    /**
     * Generate and return a salted password hash
     *
     * @param string $password to be hashed
     *
     * @return string A hashed password.
     * @access public
     */
    public function hashPassword($password)
    {
        $hashed = $this->phpass->HashPassword($password);

        /* phpass specifies that a length < 20 means something went
         * wrong. */
        if (strlen($hashed) >= 20) {
            return $hashed;
        } else {
            throw new Exception('Failure when hashing password');
        }
    }


    /**
     * Check whether a supplied password is correct
     *
     * @param string $password to be checked
     * @param string $hash to be checked against
     *
     * @return boolean True if the password was correct.  False otherwise.
     * @access public
     */
    public function checkPassword($password, $hash)
    {
        return $this->phpass->CheckPassword($password, $hash);
    }
}
?>
