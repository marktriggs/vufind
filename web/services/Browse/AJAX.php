<?php
/**
 * AJAX action for Browse module
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
 * @package  Controller_Browse
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_module Wiki
 */
require_once 'Action.php';

/**
 * AJAX action for Browse module
 *
 * @category VuFind
 * @package  Controller_Browse
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_module Wiki
 */
class AJAX extends Action
{
    private $_searchObject;

    /**
     * Constructor
     *
     * @access public
     */
    function __construct()
    {
        $this->_searchObject = SearchObjectFactory::initSearchObject();
    }

    /**
     * Process parameters and output the response.
     *
     * @return void
     * @access public
     */
    function launch()
    {
        header('Content-type: application/json');
        $response = array();
        $method = '_' . strtolower($_GET['method']);
        if (is_callable(array($this, $method))) {
            $this->_searchObject->initBrowseScreen();
            $this->_searchObject->disableLogging();
            $this->$method();
            $result = $this->_searchObject->processSearch();
            $response['AJAXResponse'] = $result['facet_counts']['facet_fields'];
        } else {
            $response['AJAXResponse'] = array('Error' => 'Invalid Method');
        }
        // Shutdown the search object
        $this->_searchObject->close();

        echo json_encode($response);
    }

    /**
     * Set SearchObject parameters for GetOptions AJAX call.
     *
     * @return void
     * @access private
     */
    private function _getoptions()
    {
        if (isset($_GET['field'])) {
            $this->_searchObject->addFacet($_GET['field']);
        }
        if (isset($_GET['facet_prefix'])) {
            $this->_searchObject->addFacetPrefix($_GET['facet_prefix']);
        }
        if (isset($_GET['query'])) {
            $this->_searchObject->setQueryString($_GET['query']);
        }
    }

    /**
     * Set SearchObject parameters for GetAlphabet AJAX call.
     *
     * @return void
     * @access private
     */
    private function _getalphabet()
    {
        if (isset($_GET['field'])) {
            $this->_searchObject->addFacet($_GET['field']);
        }
        if (isset($_GET['query'])) {
            $this->_searchObject->setQueryString($_GET['query']);
        }
        $this->_searchObject->setFacetSortOrder(false);
    }

    /**
     * Set SearchObject parameters for GetSubjects AJAX call.
     *
     * @return void
     * @access private
     */
    private function _getsubjects()
    {
        if (isset($_GET['field'])) {
            $this->_searchObject->addFacet($_GET['field']);
        }
        if (isset($_GET['query'])) {
            $this->_searchObject->setQueryString($_GET['query']);
        }
    }
}
?>