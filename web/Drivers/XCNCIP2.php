<?php
/**
 * XC NCIP Toolkit (v2) ILS Driver
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
 * @package  ILS_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
require_once 'Interface.php';
require_once 'sys/Proxy_Request.php';

/**
 * XC NCIP Toolkit (v2) ILS Driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
class XCNCIP2 implements DriverInterface
{
    /**
     * Values loaded from XCNCIP2.ini.
     *
     * @var    array
     * @access private
     */
    private $_config;

    /**
     * Constructor
     *
     * @access public
     */
    function __construct()
    {
        // Load Configuration for this Module
        $this->_config = parse_ini_file(
            dirname(__FILE__) . '/../conf/XCNCIP2.ini', true
        );
    }

    /**
     * Send an NCIP request.
     *
     * @param string $xml XML request document
     *
     * @return object     SimpleXMLElement parsed from response
     * @access private
     */
    private function _sendRequest($xml)
    {
        // Make the NCIP request:
        $client = new Proxy_Request(null, array('useBrackets' => false));
        $client->setMethod(HTTP_REQUEST_METHOD_POST);
        $client->setURL($this->_config['Catalog']['url']);
        $client->addHeader('Content-type', 'application/xml; "charset=utf-8"');
        $client->setBody($xml);
        $result = $client->sendRequest();
        if (PEAR::isError($result)) {
            PEAR::raiseError($result);
        }

        // Process the NCIP response:
        $response = $client->getResponseBody();
        $result = @simplexml_load_string($response);
        if (is_a($result, 'SimpleXMLElement')) {
            $result->registerXPathNamespace('ns1', 'http://www.niso.org/2008/ncip');
            return $result;
        } else {
            PEAR::raiseError(new PEAR_Error("Problem parsing XML"));
        }
    }

    /**
     * Given a chunk of the availability response, extract the values needed
     * by VuFind.
     *
     * @param array $current Current XCItemAvailability chunk.
     *
     * @return array
     * @access private
     */
    private function _getHoldingsForChunk($current)
    {
        // Maintain an internal static count of line numbers:
        static $number = 1;

        // Extract details from the XML:
        $status = $current->xpath(
            'ns1:HoldingsSet/ns1:ItemInformation/' .
            'ns1:ItemOptionalFields/ns1:CirculationStatus'
        );
        $status = empty($status) ? '' : (string)$status[0];
        $id = $current->xpath(
            'ns1:BibliographicId/ns1:BibliographicItemId/' .
            'ns1:BibliographicItemIdentifier'
        );

        // Pick out the permanent location (TODO: better smarts for dealing with
        // temporary locations and multi-level location names):
        $locationNodes = $current->xpath('ns1:HoldingsSet/ns1:Location');
        $location = '';
        foreach ($locationNodes as $curLoc) {
            $type = $curLoc->xpath('ns1:LocationType');
            if ((string)$type[0] == 'Permanent') {
                $tmp = $curLoc->xpath(
                    'ns1:LocationName/ns1:LocationNameInstance/ns1:LocationNameValue'
                );
                $location = (string)$tmp[0];
            }
        }

        // Get both holdings and item level call numbers; we'll pick the most
        // specific available value below.
        $holdCallNo = $current->xpath('ns1:HoldingsSet/ns1:CallNumber');
        $holdCallNo = (string)$holdCallNo[0];
        $itemCallNo = $current->xpath(
            'ns1:HoldingsSet/ns1:ItemInformation/' .
            'ns1:ItemOptionalFields/ns1:ItemDescription/ns1:CallNumber'
        );
        $itemCallNo = (string)$itemCallNo[0];

        // Build return array:
        return array(
            'id' => empty($id) ? '' : (string)$id[0],
            'availability' => ($status == 'Not Charged'),
            'status' => $status,
            'location' => $location,
            'reserve' => 'N',       // not supported
            'callnumber' => empty($itemCallNo) ? $holdCallNo : $itemCallNo,
            'duedate' => '',        // not supported
            'number' => $number++,
            // XC NCIP does not support barcode, but we need a placeholder here
            // to display anything on the record screen:
            'barcode' => 'placeholder' . $number
        );
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber; on
     * failure, a PEAR_Error.
     * @access public
     */
    public function getStatus($id)
    {
        // For now, we'll just use getHolding, since getStatus should return a
        // subset of the same fields, and the extra values will be ignored.
        return $this->getHolding($id);
    }

    /**
     * Build NCIP2 request XML for item status information.
     *
     * @param array $idList IDs to look up.
     *
     * @return string       XML request
     * @access private
     */
    private function _getStatusRequest($idList)
    {
        // Build a list of the types of information we want to retrieve:
        $desiredParts = array(
            'Bibliographic Description',
            'Circulation Status',
            'Electronic Resource',
            'Hold Queue Length',
            'Item Description',
            'Item Use Restriction Type',
            'Location'
        );

        // Start the XML:
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ' .
            'ns1:version="http://www.niso.org/schemas/ncip/v2_0/imp1/xsd/' .
            'ncip_v2_0.xsd"><ns1:Ext><ns1:LookupItemSet>';

        // Add the ID list:
        foreach ($idList as $id) {
            $xml .= '<ns1:BibliographicId>' .
                    '<ns1:BibliographicItemId>' .
                        '<ns1:BibliographicItemIdentifier>' .
                            htmlspecialchars($id) .
                        '</ns1:BibliographicItemIdentifier>' .
                        '<ns1:AgencyId>LOCAL</ns1:AgencyId>' .
                    '</ns1:BibliographicItemId>' .
                '</ns1:BibliographicId>';
        }

        // Add the desired data list:
        foreach ($desiredParts as $current) {
            $xml .= '<ns1:ItemElementType ' .
                'ns1:Scheme="http://www.niso.org/ncip/v1_0/schemes/' .
                'itemelementtype/itemelementtype.scm">' .
                htmlspecialchars($current) . '</ns1:ItemElementType>';
        }

        // Close the XML and send it to the caller:
        $xml .= '</ns1:LookupItemSet></ns1:Ext></ns1:NCIPMessage>';
        return $xml;
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $idList The array of record ids to retrieve the status for
     *
     * @return mixed        An array of getStatus() return values on success,
     * a PEAR_Error object otherwise.
     * @access public
     */
    public function getStatuses($idList)
    {
        $request = $this->_getStatusRequest($idList);
        $response = $this->_sendRequest($request);
        $avail = $response->xpath(
            'ns1:Ext/ns1:LookupItemSetResponse/ns1:BibInformation'
        );

        // Build the array of statuses:
        $status = array();
        foreach ($avail as $current) {
            // Get data on the current chunk of data:
            $chunk = $this->_getHoldingsForChunk($current);

            // Each bibliographic ID has its own key in the $status array; make sure
            // we initialize new arrays when necessary and then add the current
            // chunk to the right place:
            $id = $chunk['id'];
            if (!isset($status[$id])) {
                $status[$id] = array();
            }
            $status[$id][] = $chunk;
        }
        return $status;
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber, duedate,
     * number, barcode; on failure, a PEAR_Error.
     * @access public
     */
    public function getHolding($id)
    {
        $request = $this->_getStatusRequest(array($id));
        $response = $this->_sendRequest($request);
        $avail = $response->xpath(
            'ns1:Ext/ns1:LookupItemSetResponse/ns1:BibInformation'
        );

        // Build the array of holdings:
        $holdings = array();
        foreach ($avail as $current) {
            $holdings[] = $this->_getHoldingsForChunk($current);
        }
        return $holdings;
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @return mixed     An array with the acquisitions data on success, PEAR_Error
     * on failure
     * @access public
     */
    public function getPurchaseHistory($id)
    {
        // TODO
        return array();
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron username
     * @param string $password The patron password
     *
     * @return mixed           Associative array of patron info on successful login,
     * null on unsuccessful login, PEAR_Error on error.
     * @access public
     */
    public function patronLogin($username, $password)
    {
        // TODO
        return null;
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's transactions on success,
     * PEAR_Error otherwise.
     * @access public
     */
    public function getMyTransactions($patron)
    {
        // TODO
        return array();
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's fines on success, PEAR_Error
     * otherwise.
     * @access public
     */
    public function getMyFines($patron)
    {
        // TODO
        return array();
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's holds on success, PEAR_Error
     * otherwise.
     * @access public
     */
    public function getMyHolds($patron)
    {
        // TODO
        return array();
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @return mixed        Array of the patron's profile data on success,
     * PEAR_Error otherwise.
     * @access public
     */
    public function getMyProfile($patron)
    {
        // TODO
        return array();
    }

    /**
     * Get New Items
     *
     * Retrieve the IDs of items recently added to the catalog.
     *
     * @param int $page    Page number of results to retrieve (counting starts at 1)
     * @param int $limit   The size of each page of results to retrieve
     * @param int $daysOld The maximum age of records to retrieve in days (max. 30)
     * @param int $fundId  optional fund ID to use for limiting results (use a value
     * returned by getFunds, or exclude for no limit); note that "fund" may be a
     * misnomer - if funds are not an appropriate way to limit your new item
     * results, you can return a different set of values from getFunds. The
     * important thing is that this parameter supports an ID returned by getFunds,
     * whatever that may mean.
     *
     * @return array       Associative array with 'count' and 'results' keys
     * @access public
     */
    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        // TODO
        return array();
    }

    /**
     * Get Funds
     *
     * Return a list of funds which may be used to limit the getNewItems list.
     *
     * @return array An associative array with key = fund ID, value = fund name.
     * @access public
     */
    public function getFunds()
    {
        // TODO
        return array();
    }

    /**
     * Get Departments
     *
     * Obtain a list of departments for use in limiting the reserves list.
     *
     * @return array An associative array with key = dept. ID, value = dept. name.
     * @access public
     */
    public function getDepartments()
    {
        // TODO
        return array();
    }

    /**
     * Get Instructors
     *
     * Obtain a list of instructors for use in limiting the reserves list.
     *
     * @return array An associative array with key = ID, value = name.
     * @access public
     */
    public function getInstructors()
    {
        // TODO
        return array();
    }

    /**
     * Get Courses
     *
     * Obtain a list of courses for use in limiting the reserves list.
     *
     * @return array An associative array with key = ID, value = name.
     * @access public
     */
    public function getCourses()
    {
        // TODO
        return array();
    }

    /**
     * Find Reserves
     *
     * Obtain information on course reserves.
     *
     * @param string $course ID from getCourses (empty string to match all)
     * @param string $inst   ID from getInstructors (empty string to match all)
     * @param string $dept   ID from getDepartments (empty string to match all)
     *
     * @return mixed An array of associative arrays representing reserve items (or a
     * PEAR_Error object if there is a problem)
     * @access public
     */
    public function findReserves($course, $inst, $dept)
    {
        // TODO
        return array();
    }

    /**
     * Get suppressed records.
     *
     * @return array ID numbers of suppressed records in the system.
     * @access public
     */
    public function getSuppressedRecords()
    {
        // TODO
        return array();
    }
}

?>