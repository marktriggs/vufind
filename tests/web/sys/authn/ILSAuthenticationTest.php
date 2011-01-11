<?php
/**
 * ILS authentication test class
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
 * @package  Tests
 * @author   Franck Borel <franck.borel@gbv.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */
require_once dirname(__FILE__) . '/../../prepend.inc.php';
require_once 'PEAR.php';
require_once 'sys/authn/ILSAuthentication.php';
require_once 'sys/authn/IOException.php';
require_once 'sys/authn/ConfigurationReader.php';

/**
 * ILS authentication test class
 *
 * @category VuFind
 * @package  Tests
 * @author   Franck Borel <franck.borel@gbv.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */
class ILSAuthenticationTest extends PHPUnit_Framework_TestCase
{
    private $username = 'testuser';       // a valid username
    private $password = 'testpass';       // a valid password

    /**
     * Standard setup method.
     *
     * @return void
     * @access public
     */
    public function setUp()
    {
        // Set up the global config array required by the ILS driver:
        global $configArray;
        $configArray = parse_ini_file(dirname(__FILE__) . '/../../conf/config.ini', true);
    }

    public function test_with_empty_username()
    {
        try {
            $_POST['username'] = '';
            $_POST['password'] = $this->password;
            $authN = new ILSAuthentication();
            $this->assertTrue(PEAR::isError($authN->authenticate()));
        } catch (InvalidArgumentException $unexpected) {
            $this->fail('An unexpected InvalidArgumentException has been raised');
        }
    }

    public function test_with_empty_password()
    {
        try {
            $_POST['username'] = $this->username;
            $_POST['password'] = '';
            $authN = new ILSAuthentication();
            $this->assertTrue(PEAR::isError($authN->authenticate()));
        } catch (InvalidArgumentException $unexpected) {
            $this->fail('An unexpected InvalidArgumentException has been raised');
        }
    }

    public function test_with_wrong_credentials()
    {
        try {
            $_POST['username'] = $this->username;
            $_POST['password'] = $this->password . 'test';
            $authN = new ILSAuthentication();
            $this->assertTrue(PEAR::isError($authN->authenticate()));
        } catch (IOException $unexpected) {
            $this->fail('Unexpected Exception with: ' . $unexpected->getMessage());
        }
    }

    /* TODO -- figure out a way to make this test work cleanly in our continuous
               integration environment.
    public function test_with_working_credentials()
    {
        $_POST['username'] = $this->username;
        $_POST['password'] = $this->password;
        $authN = new ILSAuthentication();
        $this->assertTrue($authN->authenticate() instanceof User);
    }
     */
}
?>
