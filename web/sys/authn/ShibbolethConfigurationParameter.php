<?php
/**
 * Configuration File Loader for Shibboleth module
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
 * Configuration File Loader Class for Shibboleth module
 *
 * @category VuFind
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 */
class ShibbolethConfigurationParameter
{
    private $configurationFilePath;
    private $userAttributes;

    public function __construct($configurationFilePath = '')
    {
        $this->configurationFilePath = $configurationFilePath;
    }

    public function getUserAttributes()
    {
        $this->getFullSectionParameters();
        $this->checkIfUsernameExists();
        $this->filterFullSectionParameter();
        $this->sortUserAttributes();
        $this->checkIfAnyAttributeValueIsEmpty();
        $this->checkIfAtLeastOneUserAttributeIsSet();
        return $this->userAttributes;
    }

    private function getFullSectionParameters()
    {
        $configurationReader
            = new ConfigurationReader($this->configurationFilePath);
        $this->userAttributes
            = $configurationReader->readConfiguration("Shibboleth");
    }

    private function checkIfUsernameExists()
    {
        if (empty($this->userAttributes['username'])) {
            throw new UnexpectedValueException(
                "Username is missing in your configuration file : '" .
                $this->configurationFilePath . "'"
            );
        }
    }

    private function filterFullSectionParameter()
    {
        $filterPatternAttribute = "/userattribute_[0-9]{1,}/";
        $filterPatternAttributeValue = "/userattribute_value_[0-9]{1,}/";
        foreach ($this->userAttributes as $key => $value) {
            if (!preg_match($filterPatternAttribute, $key)
                && !preg_match($filterPatternAttributeValue, $key)
                && $key != "username"
            ) {
                unset($this->userAttributes[$key]);
            }
        }
    }

    private function sortUserAttributes()
    {
        $filterPatternAttributes = "/userattribute_[0-9]{1,}/";
         $sortedUserAttributes['username'] = $this->userAttributes['username'];
        foreach ($this->userAttributes as $key => $value) {
            if (preg_match($filterPatternAttributes, $key)) {
                $sortedUserAttributes[$value]
                    = $this->getUserAttributeValue(substr($key, 14));
            }
        }
        $this->userAttributes = $sortedUserAttributes;
    }

    private function getUserAttributeValue($userAttributeNumber)
    {
        $filterPatternAttributeValues = "/userattribute_value_[" .
            $userAttributeNumber . "]{1,}/";
        foreach ($this->userAttributes as $key => $value) {
            if (preg_match($filterPatternAttributeValues, $key)) {
                return $value;
            }
        }
    }

    private function checkIfAnyAttributeValueIsEmpty()
    {
        foreach ($this->userAttributes as $key => $value) {
            if (empty($value)) {
                throw new UnexpectedValueException(
                    "User attribute value of " . $key. " is missing!"
                );
            }
        }
    }

    private function checkIfAtLeastOneUserAttributeIsSet()
    {
        if (count($this->userAttributes) == 1) {
            throw new UnexpectedValueException(
                "You must at least set one user attribute in your configuration " .
                "file '" . $this->configurationFilePath  . "'.", 3
            );
        }
    }
}


?>