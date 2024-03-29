<?php
/**
 * Catalog Connection Class
 *
 * This wrapper works with a driver class to pass information from the ILS to
 * VuFind.
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
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */

/**
 * Catalog Connection Class
 *
 * This wrapper works with a driver class to pass information from the ILS to
 * VuFind.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
class CatalogConnection
{
    /**
     * A boolean value that defines whether a connection has been successfully
     * made.
     *
     * @access public
     * @var    bool
     */
    public $status = false;

    /**
     * The object of the appropriate driver.
     *
     * @access private
     * @var    object
     */
    public $driver;

    /**
     * Constructor
     *
     * This is responsible for instantiating the driver that has been specified.
     *
     * @param string $driver The name of the driver to load.
     *
     * @access public
     */
    public function __construct($driver)
    {
        global $configArray;
        $path = "{$configArray['Site']['local']}/Drivers/{$driver}.php";
        if (is_readable($path)) {
            include_once $path;

            try {
                $this->driver = new $driver;
            } catch (PDOException $e) {
                throw $e;
            }

            $this->status = true;
        }
    }

    /**
     * Check Function
     *
     * This is responsible for checking the driver configuration to determine
     * if the system supports a particular function.
     *
     * @param string $function The name of the function to check.
     *
     * @return mixed On success, an associative array with specific function keys
     * and values; on failure, false.
     * @access public
     */
    public function checkFunction($function)
    {
        // Extract the configuration from the driver if available:
        $functionConfig = method_exists($this->driver, 'getConfig')
            ? $this->driver->getConfig($function) : false;

        // See if we have a corresponding check method to analyze the response:
        $checkMethod = "_checkMethod".$function;
        if (!method_exists($this, $checkMethod)) {
            return false;
        }

        // Send back the settings:
        return $this->$checkMethod($functionConfig);
    }

    /**
     * Check Holds
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports Holds.
     *
     * @param string $functionConfig The Hold configuration values
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for placing holds via a form or a URL; on failure, false.
     * @access private
     */
    private function _checkMethodHolds($functionConfig)
    {
        global $configArray;
        $response = false;

        if ($this->getHoldsMode() != "none"
            && method_exists($this->driver, 'placeHold')
            && isset($functionConfig['HMACKeys'])
        ) {
            $response = array('function' => "placeHold");
            $response['HMACKeys'] = explode(":", $functionConfig['HMACKeys']);
            if (isset($functionConfig['defaultRequiredDate'])) {
                $response['defaultRequiredDate']
                    = $functionConfig['defaultRequiredDate'];
            }
            if (isset($functionConfig['extraHoldFields'])) {
                $response['extraHoldFields'] = $functionConfig['extraHoldFields'];
            }
        } else if (method_exists($this->driver, 'getHoldLink')) {
            $response = array('function' => "getHoldLink");
        }
        return $response;
    }

    /**
     * Check Cancel Holds
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports Cancelling Holds.
     *
     * @param string $functionConfig The Cancel Hold configuration values
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for cancelling holds via a form or a URL;
     * on failure, false.
     * @access private
     */
    private function _checkMethodcancelHolds($functionConfig)
    {
        global $configArray;
        $response = false;

        if ($configArray['Catalog']['cancel_holds_enabled'] == true
            && method_exists($this->driver, 'cancelHolds')
        ) {
            $response = array('function' => "cancelHolds");
        } else if ($configArray['Catalog']['cancel_holds_enabled'] == true
            && method_exists($this->driver, 'getCancelHoldLink')
        ) {
            $response = array('function' => "getCancelHoldLink");
        }
        return $response;
    }

    /**
     * Check Renewals
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports Renewing Items.
     *
     * @param string $functionConfig The Renewal configuration values
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for renewing items via a form or a URL; on failure, false.
     * @access private
     */
    private function _checkMethodRenewals($functionConfig)
    {
        global $configArray;
        $response = false;

        if ($configArray['Catalog']['renewals_enabled'] == true
            && method_exists($this->driver, 'renewMyItems')
        ) {
            $response = array('function' => "renewMyItems");
        } else if ($configArray['Catalog']['renewals_enabled'] == true
            && method_exists($this->driver, 'renewMyItemsLink')
        ) {
            $response = array('function' => "renewMyItemsLink");
        }
        return $response;
    }

    /**
     * Check Request is Valid
     *
     * This is responsible for checking if a request is valid from hold.php
     *
     * @param string $id     A Bibliographic ID
     * @param array  $data   Collected Holds Data
     * @param array  $patron Patron related data
     *
     * @return mixed The result of the checkRequestIsValid function if it
     *               exists, true if it does not
     * @access public
     */
    public function checkRequestIsValid($id, $data, $patron)
    {
        $method = array($this->driver, 'checkRequestIsValid');
        if (is_callable($method)) {
            return $this->driver->checkRequestIsValid($id, $data, $patron);
        }
        // If the driver has no checkRequestIsValid method, we will assume that
        // all requests are valid - failure can be handled later after the user
        // attempts to place an illegal hold
        return true;
    }

    /**
     * Get Holds Mode
     *
     * This is responsible for returning the holds mode
     *
     * @return string The Holds mode
     * @access public
     */
    public static function getHoldsMode()
    {
        global $configArray;
        return isset($configArray['Catalog']['holds_mode'])
            ? $configArray['Catalog']['holds_mode'] : 'all';
    }

    /**
     * Get Title Holds Mode
     *
     * This is responsible for returning the Title holds mode
     *
     * @return string The Title Holds mode
     * @access public
     */
    public static function getTitleHoldsMode()
    {
        global $configArray;
        return isset($configArray['Catalog']['title_level_holds_mode'])
            ? $configArray['Catalog']['title_level_holds_mode'] : 'disabled';
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $recordId The record id to retrieve the holdings for
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber; on
     * failure, a PEAR_Error.
     * @access public
     */
    public function getStatus($recordId)
    {
        return $this->driver->getStatus($recordId);
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $recordIds The array of record ids to retrieve the status for
     *
     * @return mixed           An array of getStatus() return values on success,
     * a PEAR_Error object otherwise.
     * @access public
     * @author Chris Delis <cedelis@uillinois.edu>
     */
    public function getStatuses($recordIds)
    {
        return $this->driver->getStatuses($recordIds);
    }

    /**
     * Has Holdings
     *
     * Obtain information on whether or not the item has holdings
     *
     * @param string $id A bibliographic id
     *
     * @return bool true on success, false on failure
     * @access public
     */
    public function hasHoldings($id)
    {
        // Graceful degradation -- return true if no method supported.
        return method_exists($this->driver, 'hasHoldings') ?
            $this->driver->hasHoldings($id) : true;
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $recordId The record id to retrieve the holdings for
     * @param array  $patron   Optional Patron details to determine if a user can
     * place a hold or recall on an item
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber, duedate,
     * number, barcode; on failure, a PEAR_Error.
     * @access public
     */
    public function getHolding($recordId, $patron = false)
    {
        $holding = $this->driver->getHolding($recordId, $patron);

        // Validate return from driver's getHolding method -- should be an array or
        // an error.  Anything else is unexpected and should become an error.
        if (!is_array($holding) && !PEAR::isError($holding)) {
            return new PEAR_Error('Unexpected return from getHolding: ' . $holding);
        }

        return $holding;
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $recordId The record id to retrieve the info for
     *
     * @return mixed           An array with the acquisitions data on success,
     * PEAR_Error on failure
     * @access public
     */
    public function getPurchaseHistory($recordId)
    {
        return $this->driver->getPurchaseHistory($recordId);
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron username
     * @param string $password The patron password
     *
     * @return mixed           Associative array of patron info on successful
     * login, null on unsuccessful login, PEAR_Error on error.
     * @access public
     */
    public function patronLogin($username, $password)
    {
        return $this->driver->patronLogin($username, $password);
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
        return $this->driver->getMyTransactions($patron);
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
        return $this->driver->getMyFines($patron);
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
        return $this->driver->getMyHolds($patron);
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
        return $this->driver->getMyProfile($patron);
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
    public function getNewItems($page = 1, $limit = 20, $daysOld = 30,
        $fundId = null
    ) {
        return $this->driver->getNewItems($page, $limit, $daysOld, $fundId);
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
        // Graceful degradation -- return empty fund list if no method supported.
        return method_exists($this->driver, 'getFunds') ?
            $this->driver->getFunds() : array();
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
        // Graceful degradation -- return empty list if no method supported.
        return method_exists($this->driver, 'getDepartments') ?
            $this->driver->getDepartments() : array();
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
        // Graceful degradation -- return empty list if no method supported.
        return method_exists($this->driver, 'getInstructors') ?
            $this->driver->getInstructors() : array();
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
        // Graceful degradation -- return empty list if no method supported.
        return method_exists($this->driver, 'getCourses') ?
            $this->driver->getCourses() : array();
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
        return $this->driver->findReserves($course, $inst, $dept);
    }

    /**
     * Get suppressed records.
     *
     * @return array ID numbers of suppressed records in the system.
     * @access public
     */
    public function getSuppressedRecords()
    {
        return $this->driver->getSuppressedRecords();
    }

    /**
     * Default method -- pass along calls to the driver if available; return
     * false otherwise.  This allows custom functions to be implemented in
     * the driver without constant modification to the connection class.
     *
     * @param string $methodName The name of the called method.
     * @param array  $params     Array of passed parameters.
     *
     * @return mixed             Varies by method (false if undefined method)
     * @access public
     */
    public function __call($methodName, $params)
    {
        $method = array($this->driver, $methodName);
        if (is_callable($method)) {
            return call_user_func_array($method, $params);
        }
        return false;
    }
}

?>
