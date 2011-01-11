<?php
/**
 * Configuration File Loader for LDAP module
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
require_once 'ConfigurationReader.php';

/**
 * Configuration File Loader Class for LDAP module
 *
 * @category VuFind
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 */
class LDAPConfigurationParameter
{
    private $ldapParameter;

    public function __construct($configurationFilePath = '')
    {
        $this->configurationFilePath = $configurationFilePath;
    }

    public function getParameter()
    {
        $this->getFullSectionParameters();
        $this->checkIfMandatoryParametersAreSet();
        $this->convertParameterValuesToLowercase();
        return $this->ldapParameter;
    }

    private function getFullSectionParameters()
    {
        $configurationReader = new ConfigurationReader($this->configurationFilePath);
        $this->ldapParameter = $configurationReader->readConfiguration("LDAP");
    }

    private function checkIfMandatoryParametersAreSet()
    {
        if (empty($this->ldapParameter['host'])
            || empty($this->ldapParameter['port'])
            || empty($this->ldapParameter['basedn'])
            || empty($this->ldapParameter['username'])
        ) {
            throw new InvalidArgumentException("One or more LDAP parameter are missing. Check your config.ini!");
        }
    }

    private function convertParameterValuesToLowercase()
    {
        foreach ($this->ldapParameter as $index => $value) {
            // Don't lowercase the bind credentials -- they may be case sensitive!
            if ($index != 'bind_username' && $index != 'bind_password') {
                $this->ldapParameter[$index] = strtolower($value);
            }
        }
    }


}
?>
