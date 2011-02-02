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
}
?>