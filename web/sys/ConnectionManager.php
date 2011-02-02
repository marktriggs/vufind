<?php
/**
 * Central class for connecting to resources used by VuFind.
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
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */

/**
 * Central class for connecting to resources used by VuFind.
 *
 * @category VuFind
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */
class ConnectionManager
{
    /**
     * Connect to the catalog.
     *
     * @return mixed CatalogConnection object on success, boolean false on error
     * @access public
     */
    public static function connectToCatalog()
    {
        global $configArray;

        // Use a static variable for the connection -- we never want more than one
        // connection open at a time, so if we have previously connected, we will
        // remember the old connection and return that instead of starting over.
        static $catalog = false;
        if ($catalog === false) {
            include_once 'CatalogConnection.php';

            try {
                $catalog = new CatalogConnection($configArray['Catalog']['driver']);
            } catch (PDOException $e) {
                // What should we do with this error?
                if ($configArray['System']['debug']) {
                    echo '<pre>';
                    echo 'DEBUG: ' . $e->getMessage();
                    echo '</pre>';
                }
            }
        }

        return $catalog;
    }

    /**
     * Connect to the database.
     *
     * @return void
     * @access public
     */
    public static function connectToDatabase()
    {
        global $configArray;

        if (!defined('DB_DATAOBJECT_NO_OVERLOAD')) {
            define('DB_DATAOBJECT_NO_OVERLOAD', 0);
        }
        $options =& PEAR::getStaticProperty('DB_DataObject', 'options');
        $options = $configArray['Database'];
    }

    /**
     * Connect to the index.
     *
     * @param string $type Index type to connect to (null for config.ini default).
     * @param string $core Index core to use (null for default).
     * @param string $url  Connection URL for index (null for config.ini default).
     *
     * @return object
     * @access public
     */
    public static function connectToIndex($type = null, $core = null, $url = null)
    {
        global $configArray;

        // Load config.ini settings for missing parameters:
        if ($type == null) {
            $type = $configArray['Index']['engine'];
        }
        if ($url == null) {
            // Load appropriate default server URL based on index type:
            $url = ($type == 'SolrStats')
                ? $configArray['Statistics']['solr'] : $configArray['Index']['url'];
        }

        // Load the index connection code:
        include_once 'sys/' . $type . '.php';

        // Construct the object appropriately based on the $core setting:
        if (empty($core)) {
            $index = new $type($url);
        } else {
            $index = new $type($url, $core);
        }

        // Turn on debug mode if necessary:
        if ($configArray['System']['debug']) {
            $index->debug = true;
        }

        return $index;
    }
}
?>