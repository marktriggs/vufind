<?php
/**
 * LDAP configuration test class
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
require_once 'sys/authn/LDAPConfigurationParameter.php';

/**
 * LDAP configuration test class
 *
 * @category VuFind
 * @package  Tests
 * @author   Franck Borel <franck.borel@gbv.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */
class LDAPConfigurationParameterTest extends PHPUnit_Framework_TestCase
{
    /**
     * Standard setup method.
     *
     * @return void
     * @access public
     */
    public function setUp()
    {
        $this->pathToTestConfigurationFiles = dirname(__FILE__) . '/../../conf';
    }

    public function test_with_missing_host()
    {
        try {
            $ldapConfigurationParameter = new LDAPConfigurationParameter(
            $this->pathToTestConfigurationFiles . "/authn/ldap/without-ldap-host-config.ini");
            $parameters = $ldapConfigurationParameter->getParameter();
        } catch (InvalidArgumentException $expected) {
            return;
        }
        $this->fail('An expected UnexpectedValueException has not been raised');
    }

    public function test_with_missing_port()
    {
        try {
            $ldapConfigurationParameter = new LDAPConfigurationParameter(
            $this->pathToTestConfigurationFiles . "/authn/ldap/without-ldap-port-config.ini");
            $parameters = $ldapConfigurationParameter->getParameter();
        } catch (InvalidArgumentException $expected) {
            return;
        }
        $this->fail('An expected UnexpectedValueException has not been raised');
    }

    public function test_with_missing_baseDN()
    {
        try {
            $ldapConfigurationParameter = new LDAPConfigurationParameter($this->pathToTestConfigurationFiles .
                                                                         "/authn/ldap/without-ldap-basedn-config.ini");
            $parameters = $ldapConfigurationParameter->getParameter();
        } catch (InvalidArgumentException $expected) {
            return;
        }
        $this->fail('An expected UnexpectedValueException has not been raised');
    }

    public function test_with_missing_uid()
    {
        try {
            $ldapConfigurationParameter = new LDAPConfigurationParameter($this->pathToTestConfigurationFiles .
                                                                         "/authn/ldap/without-ldap-uid-config.ini");
            $parameters = $ldapConfigurationParameter->getParameter();
        } catch (InvalidArgumentException $expected) {
            return;
        }
        $this->fail('An expected UnexpectedValueException has not been raised');
    }

    public function test_with_working_parameters()
    {
        try {
            $ldapConfigurationParameter = new LDAPConfigurationParameter();
            $parameters = $ldapConfigurationParameter->getParameter();
            $this->assertTrue(is_array($parameters));
        } catch (InvalidArgumentException $unexpected) {
             $this->fail("An unexpected UnexpectedValueException has not been raised: {$unexpected}");
        }
    }

    public function test_if_parameter_are_converted_to_lowercase()
    {
        try {
            $ldapConfigurationParameter = new LDAPConfigurationParameter(
                $this->pathToTestConfigurationFiles .
                "/authn/ldap/unconverted-parameter-values-config.ini"
            );
            $parameters = $ldapConfigurationParameter->getParameter();
            foreach ($parameters as $index => $value){
                if ($index == "username") {
                    $this->assertTrue($value == "uid");
                }

                if ($index == "college") {
                    $this->assertTrue($value == "employeetype");
                }
            }
        } catch (InvalidArgumentException $unexpected) {
            $this->fail("An unexpected UnexpectedValueException has not been raised: {$unexpected}");
        }
    }

}
?>
