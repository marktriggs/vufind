<?php
/**
 * XC NCIP Toolkit ILS Driver
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
 * @package  ILS_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
require_once 'Interface.php';
require_once 'sys/Proxy_Request.php';

/**
 * XC NCIP Toolkit ILS Driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
class XCNCIP implements DriverInterface
{
    /**
     * Values loaded from XCNCIP.ini.
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
            dirname(__FILE__) . '/../conf/XCNCIP.ini', true
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
        $client->addPostData('NCIP', $xml);
        $result = $client->sendRequest();
        if (PEAR::isError($result)) {
            PEAR::raiseError($result);
        }

        // Process the NCIP response:
        $response = $client->getResponseBody();
        if ($result = @simplexml_load_string($response)) {
            return $result;
        } else {
            PEAR::raiseError(new PEAR_Error("Problem parsing XML"));
        }
    }

    /**
     * Build the "initiation header" section of the XML:
     *
     * @return string XML chunk
     * @access private
     */
    private function _getInitiationHeader()
    {
        $id = htmlspecialchars($this->_config['Catalog']['agency']);
        return
            '<InitiationHeader>' .
                '<FromAgencyId>' .
                    '<UniqueAgencyId>' .
                        '<Scheme>http://128.151.189.131:8080/NCIPToolkit/NCIPschemes/AgencyScheme.scm</Scheme>' .
                        '<Value>' . $id . '</Value>' .
                    '</UniqueAgencyId>' .
                '</FromAgencyId>' .
                '<ToAgencyId>' .
                    '<UniqueAgencyId>' .
                        '<Scheme>http://128.151.189.131:8080/NCIPToolkit/NCIPschemes/AgencyScheme.scm</Scheme>' .
                        '<Value>' . $id . '</Value>' .
                    '</UniqueAgencyId>' .
                '</ToAgencyId>' .
            '</InitiationHeader>';
    }

    /**
     * Build the request XML to get item status:
     *
     * @param mixed $id Bibliographic record ID (or array of IDs)
     *
     * @return string   NCIP request XML
     * @access private
     */
    private function _getStatusRequest($id)
    {
        // Build the start of the XML:
        $xml = "<?xml version='1.0' encoding='UTF-8'?>" .
            '<!DOCTYPE NCIPMessage PUBLIC "-//NISO//NCIP DTD Version 1//EN" "http://xml.coverpages.org/NCIP-v10a-DTD.txt">' .
            '<NCIPMessage version="http://www.niso.org/ncip/v1_0/imp1/dtd/ncip_v1_0.dtd">' .
                '<XCGetAvailability>' . $this->_getInitiationHeader();

        // Add entries for IDs passed in:
        if (!is_array($id)) {
            $id = array($id);
        }
        foreach ($id as $current) {
            $current = htmlspecialchars($current);
            $xml .=     '<VisibleItemId>' .
                            '<VisibleItemIdentifierType>' .
                                '<Scheme>http://128.151.189.131:8080/NCIPToolkit/NCIPschemes/BibIdCode.scm</Scheme>' .
                                '<Value>bibliographic ID</Value>' .
                            '</VisibleItemIdentifierType>' .
                            "<VisibleItemIdentifier>{$current}</VisibleItemIdentifier>" .
                        '</VisibleItemId>';
        }

        // Build the end of the XML:
        $xml .= '</XCGetAvailability></NCIPMessage>';

        return $xml;
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
        $status = $current->xpath('ItemOptionalFields/CirculationStatus/Value');
        $status = empty($status) ? '' : (string)$status[0];
        $location = $current->xpath('ItemOptionalFields/Location');
        $callNo = $current->xpath('ItemOptionalFields/ItemDescription/CallNumber');
        $id = $current->xpath('BibId');

        // Build return array:
        return array(
            'id' => empty($id) ? '' : (string)$id[0],
            'availability' => ($status == 'Not Charged'),
            'status' => $status,
            'location' => empty($location) ? '' : (string)$location[0],
            'reserve' => 'N',       // not supported
            'callnumber' => empty($callNo) ? '' : (string)$callNo[0],
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
        $avail = $response->xpath('XCGetAvailabilityResponse/XCItemAvailability');

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
        $request = $this->_getStatusRequest($id);
        $response = $this->_sendRequest($request);
        $avail = $response->xpath('XCGetAvailabilityResponse/XCItemAvailability');

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
     * Build the request XML to log in a user:
     *
     * @param string $username Username for login
     * @param string $password Password for login
     *
     * @return string          NCIP request XML
     * @access private
     */
    private function _getLoginRequest($username, $password)
    {
        // Build the start of the XML:
        $xml = "<?xml version='1.0' encoding='UTF-8'?>" .
            '<!DOCTYPE NCIPMessage PUBLIC "-//NISO//NCIP DTD Version 1//EN" "http://xml.coverpages.org/NCIP-v10a-DTD.txt">' .
            '<NCIPMessage version="http://www.niso.org/ncip/v1_0/imp1/dtd/ncip_v1_0.dtd">' .
                '<AuthenticateUser>' . $this->_getInitiationHeader();

        // Prepare input values for inclusion in XML:
        $username = htmlspecialchars($username);
        $password = htmlspecialchars($password);

        // Build the core of the message:
        $xml .=
            '<AuthenticationInput>' .
                '<AuthenticationInputData>' . $username . '</AuthenticationInputData>' .
                '<AuthenticationDataFormatType>' .
                    '<Scheme>http://localhost:8080/NCIPToolkit/NCIPschemes/FormatType.scm</Scheme>' .
                    '<Value>Text</Value>' .
                '</AuthenticationDataFormatType>' .
                '<AuthenticationInputType>' .
                    '<Scheme>http://localhost:8080/NCIPToolkit/NCIPschemes/AuthenticationType.scm</Scheme>' .
                    '<Value>Username</Value>' .
                '</AuthenticationInputType>' .
            '</AuthenticationInput>' .
            '<AuthenticationInput>' .
                '<AuthenticationInputData>' . $password . '</AuthenticationInputData>' .
                '<AuthenticationDataFormatType>' .
                    '<Scheme>http://localhost:8080/NCIPToolkit/NCIPschemes/FormatType.scm</Scheme>' .
                    '<Value>Text</Value>' .
                '</AuthenticationDataFormatType>' .
                '<AuthenticationInputType>' .
                    '<Scheme>http://localhost:8080/NCIPToolkit/NCIPschemes/AuthenticationType.scm</Scheme>' .
                    '<Value>Password</Value>' .
                '</AuthenticationInputType>' .
            '</AuthenticationInput>';

        // Build the end of the XML:
        $xml .= '</AuthenticateUser></NCIPMessage>';

        return $xml;
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
        $request = $this->_getLoginRequest($username, $password);
        $response = $this->_sendRequest($request);
        $id = $response->xpath('AuthenticateUserResponse/UniqueUserId/UserIdentifierValue');
        if (!empty($id)) {
            // Fill in basic patron details:
            $patron = array(
                'id' => (string)$id[0],
                'cat_username' => $username,
                'cat_password' => $password,
                'email' => null,
                'major' => null,
                'college' => null
            );

            // Look up additional details:
            $details = $this->getMyProfile($patron);
            if (!is_null($details)) {
                $patron['firstname'] = $details['firstname'];
                $patron['lastname'] = $details['lastname'];
                return $patron;
            }
        }

        return null;
    }

    /**
     * Build the request XML to obtain user information.  The optional $userElements
     * and $otherElements parameters may be used to get specific types of additional
     * information -- see the XC NCIP Toolkit User Documentation for details.
     *
     * @access  private
     * @param   string  $id             User ID to look up
     * @param   mixed   $userElements   UserElementType parameters (string or array)
     * @param   mixed   $otherElements  Empty element(s) to add to request
     * @return  string                  NCIP request XML
     */
    private function getUserLookupRequest($id, $userElements, $otherElements = array())
    {
        // Build the start of the XML:
        $xml = "<?xml version='1.0' encoding='UTF-8'?>" .
            '<!DOCTYPE NCIPMessage PUBLIC "-//NISO//NCIP DTD Version 1//EN" "http://xml.coverpages.org/NCIP-v10a-DTD.txt">' .
            '<NCIPMessage version="http://www.niso.org/ncip/v1_0/imp1/dtd/ncip_v1_0.dtd">' .
                '<LookupUser>' . $this->_getInitiationHeader();

        // Fill in the important bits:
        $id = htmlspecialchars($id);
        $xml .=
            '<UniqueUserId>' .
                '<UniqueAgencyId>' .
                    '<Scheme>http://128.151.244.137:8080/NCIPToolkit/NCIPschemes/AgencyScheme.scm</Scheme>' .
                    '<Value>' .
                        htmlspecialchars($this->_config['Catalog']['agency']) .
                    '</Value>' .
                '</UniqueAgencyId>' .
                '<UserIdentifierValue>' . $id . '</UserIdentifierValue>' .
            '</UniqueUserId>';

        // Add relevant UserElementType parameters:
        if (!is_array($userElements)) {
            $userElements = array($userElements);
        }
        foreach ($userElements as $current) {
            $xml .=
                '<UserElementType>' .
                    '<Scheme>http://www.niso.org/ncip/v1_0/schemes/userelementtype/userelementtype.scm</Scheme>' .
                    '<Value>' . htmlspecialchars($current) . '</Value>' .
                '</UserElementType>';
        }

        // Add relevent empty elements:
        if (!is_array($otherElements)) {
            $otherElements = array($otherElements);
        }
        foreach ($otherElements as $current) {
            $xml .= '<' . htmlspecialchars($current) . ' />';
        }

        // Finish up request:
        $xml .= '</LookupUser></NCIPMessage>';
        return $xml;
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
        //$request = $this->getUserLookupRequest($patron['id'], 'Visible User Id',
        //    'LoanedItemsDesired');
        $response = $this->_sendRequest($request);
        // TODO -- process response
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
        //$request = $this->getUserLookupRequest($patron['id'], 'Visible User Id',
        //    'UserFiscalAccountDesired');
        //$response = $this->_sendRequest($request);
        // TODO -- process response
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
        //$request = $this->getUserLookupRequest($patron['id'], 'Visible User Id',
        //    'RequestedItemsDesired');
        //$response = $this->_sendRequest($request);
        // TODO -- process response
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
        $request = $this->getUserLookupRequest(
            $patron['id'], array('Visible User Id', 'User Address Information')
        );
        $response = $this->_sendRequest($request);

        // Skip to the interesting part of the response:
        $response = $response->xpath('LookupUserResponse/UserOptionalFields');
        if (empty($response)) {
            return null;
        }
        $response = $response[0];

        // Extract arrays of names and addresses:
        $names = $response->xpath('VisibleUserId');
        $addresses = $response->xpath('UserAddressInformation');

        // Find the best names and addresses to use:
        $details = array();
        foreach ($names as $name) {
            $type = $name->xpath('VisibleUserIdentifierType/Value');
            if (!empty($type) && (string)$type[0] == 'Full Name') {
                $id = $name->xpath('VisibleUserIdentifier');
                if (!empty($id)) {
                    $parts = explode(' ', (string)$id[0]);
                    $details['firstname'] = $parts[0];
                    $i = count($parts) - 1;
                    if ($i > 0) {
                        $details['lastname'] = $parts[$i];
                    }
                }
            }
        }

        foreach ($addresses as $address) {
            $type = $address->xpath('UserAddressRoleType/Value');
            if (!empty($type) && (string)$type[0] == 'Permanent') {
                $physical = $address->xpath('PhysicalAddress');
                if (!empty($physical)) {
                    $details['address1'] = (string)$physical[0];
                }
            }
        }

        return $details;
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
