<?php
/**
 * Records action for Admin module
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
 * @package  Controller_Admin
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_module Wiki
 */
require_once 'Action.php';

require_once 'CatalogConnection.php';

/**
 * Records action for Admin module
 *
 * @category VuFind
 * @package  Controller_Admin
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_module Wiki
 */
class Records extends Action
{
    private $_db;

    /**
     * Process parameters and display the page.
     *
     * @return void
     * @access public
     */
    public function launch()
    {
        global $configArray;
        global $interface;

        // Run the specified method if it exists...  but don't run the launch
        // method or we'll end up in an infinite loop!!
        if (isset($_GET['util']) && $_GET['util'] != 'launch'
            && method_exists($this, $_GET['util'])
        ) {
            // Setup Search Engine Connection
            $class = $configArray['Index']['engine'];
            $url = $configArray['Index']['url'];
            $this->_db = new $class($url);
            if ($configArray['System']['debug']) {
                $this->_db->debug = true;
            }

            $this->$_GET['util']();
        } else {
            $interface->setTemplate('records.tpl');
            $interface->setPageTitle('Record Management');
            $interface->display('layout-admin.tpl');
        }
    }

    /**
     * Display (and possibly edit) a specified record.
     *
     * NOTE: It is not really possible to adequately edit a record based only on
     * the data within Solr, so this function is dangerous -- it doesn't really do
     * what it seems to do, and there really isn't any way to make it better.  It
     * is currently disabled in the templates, but it should probably be removed
     * entirely at some point.
     *
     * @param bool $allowChanges Is editing allowed?
     *
     * @return void
     * @access public
     */
    public function editRecord($allowChanges = true)
    {
        global $interface;

        // Read in the original record:
        $record = $this->_db->getRecord($_GET['id']);

        // Save changes if necessary
        if (isset($_POST['submit']) && $_POST['submit'] == 'Save') {
            // Strip off the "solr_" prefix used to identify index fields:
            $fields = array();
            foreach ($_POST as $field => $value) {
                if (substr($field, 0, 5) == 'solr_') {
                    $fields[substr($field, 5)] = $value;
                }
            }

            // Make sure we haven't lost the ID:
            if (strlen(trim($fields['id'][0])) == 0) {
                $fields['id'][0] = $_GET['id'];
            }

            // Write the changes to Solr using the XML interface.
            // TODO: Find a way to represent control characters (ASCII 29, 30, 31)
            //       commonly found in MARC; until we can do this, we can't save
            //       changes due to the content of the fullrecord field and the
            //       limitations of XML.  This may need to wait until a non-XML
            //       transport layer is available for external Solr interfacing.
            $xml = $this->_db->getSaveXML($fields);
            $this->_db->saveRecord($xml);
            $this->_db->commit();

            // Redirect to the newly-saved record (in case the ID changed):
            if ($_GET['id'] != $fields['id']) {
                header(
                    "Location: Records?util=editRecord&id=" .
                    urlencode($fields['id'][0])
                );
                die();
            }
        }

        $interface->assign('record', $record);
        $interface->assign('recordId', $_GET['id']);
        $interface->assign('allowChanges', $allowChanges);

        $interface->setTemplate('record-edit.tpl');
        $interface->display('layout-admin.tpl');
    }

    /**
     * Display (without editing capabilities) a specified record.
     *
     * @return void
     * @access public
     */
    public function viewRecord()
    {
        // View is exactly the same as edit, but it doesn't allow changes.
        $this->editRecord(false);
    }

    /**
     * Delete a specified record.
     *
     * @return void
     * @access public
     */
    public function deleteRecord()
    {
        global $interface;

        if (!empty($_GET['id'])) {
            $this->_db->deleteRecord($_GET['id']);
            $this->_db->commit();
            //$this->_db->optimize();
            $interface->assign('status', 'Record ' . $_GET['id'] . ' deleted.');
        } else {
            $interface->assign('status', 'Please specify a record to delete.');
        }

        $interface->setTemplate('records.tpl');
        $interface->display('layout-admin.tpl');
    }

    /**
     * Delete suppressed records.
     *
     * @return void
     * @access public
     */
    public function deleteSuppressed()
    {
        global $interface;
        global $configArray;

        ini_set('memory_limit', '50M');
        ini_set('max_execution_time', '3600');

        // Make ILS Connection
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

        /*
        // Display Progress Page
        $interface->display('loading.tpl');
        ob_flush();
        flush();
        */

        // Get Suppressed Records and Delete from index
        $deletes = array();
        if ($catalog->status) {
            $result = $catalog->getSuppressedRecords();
            if (!PEAR::isError($result)) {
                $status = $this->_db->deleteRecords($result);
                foreach ($result as $current) {
                    $deletes[] = array('id' => $current);
                }

                $this->_db->commit();
                $this->_db->optimize();
            }
        } else {
            PEAR::raiseError(new PEAR_Error('Cannot connect to ILS'));
        }

        $interface->assign('resultList', $deletes);

        $interface->setTemplate('grid.tpl');
        $interface->setPageTitle('Delete Suppressed');
        $interface->display('layout-admin.tpl');
    }
}

?>