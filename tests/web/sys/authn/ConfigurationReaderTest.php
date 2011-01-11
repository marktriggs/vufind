<?php
/**
 * ConfigurationReader test class
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
require_once 'sys/authn/ConfigurationReader.php';
require_once 'sys/authn/IOException.php';
require_once 'sys/authn/FileParseException.php';

/**
 * ConfigurationReader test class
 *
 * @category VuFind
 * @package  Tests
 * @author   Franck Borel <franck.borel@gbv.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */
class ConfigurationReaderTest extends PHPUnit_Framework_TestCase
{
    private $pathToTestConfigurationFile;

    public function __construct()
    {
        $this->pathToTestConfigurationFile = dirname(__FILE__) . '/../../conf';
    }

    public function test_no_configuration_file_found()
    {
        try {
            $configurationReader = new ConfigurationReader($this->pathToTestConfigurationFile . "/authn/shib/this-file-do-not-exist.ini");
        } catch (IOException $expected) {
            return;
        }

        $this->fail('An expected IOException has not been raised');
    }

    public function test_unknown_section()
    {
        try {
            $configurationReader = new ConfigurationReader($this->pathToTestConfigurationFile . "/authn/shib/no-shibboleth-section-config.ini");
            $section = $configurationReader->readConfiguration("Shibboleth");
        } catch (UnexpectedValueException $expected) {
            return;
        }
        $this->fail('An expected UnexpectedValueException has not been raised');
    }

    public function test_empty_section()
    {
        try {
            $configurationReader = new ConfigurationReader($this->pathToTestConfigurationFile . "/authn/shib/empty-shibboleth-section-config.ini");
            $section = $configurationReader->readConfiguration("Shibboleth");
        } catch (UnexpectedValueException $expected) {
            return;
        }
        $this->fail('An expected UnexpectedValueException has not been raised');
    }

    public function test_with_attribute_value_but_missing_attributename()
    {
       try {
            $configurationReader = new ConfigurationReader($this->pathToTestConfigurationFile . "/authn/shib/attribute-value-but-missing-attributename-config.ini");
            $section = $configurationReader->readConfiguration("Shibboleth");
        } catch (FileParseException $expected) {
            return;
        }
        $this->fail('An expected FileParseException has not been raised');
    }


    public function test_with_correct_configuration_file()
    {
        try {
            $configurationReader = new ConfigurationReader($this->pathToTestConfigurationFile . "/config.ini");
            $section = $configurationReader->readConfiguration("Extra_Config");
            $this->assertEquals($section['facets'], "facets.ini");
            $this->assertEquals($section['searches'], "searches.ini");
        } catch (Exception $unexpected) {
            $this->fail($unexpected);
        }
    }

    public function test_without_commited_configuration_file()
    {
        try {
            $configurationReader = new ConfigurationReader();
            $section = $configurationReader->readConfiguration("Extra_Config");
            $this->assertEquals($section['facets'], "facets.ini");
            $this->assertEquals($section['searches'], "searches.ini");
        } catch (Exception $unexpected) {
            $this->fail($unexpected);
        }
    }
}
?>