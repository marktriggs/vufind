<?php
/**
 * ILS Driver for VuFind to query availability information via DAIA.
 *
 * Based on the proof-of-concept-driver by Till Kinstler, GBV.
 *
 * PHP version 5
 *
 * Copyright (C) Oliver Goldschmidt 2010.
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
 * @author   Oliver Goldschmidt <o.goldschmidt@tu-harburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */

require_once 'Interface.php';

/**
 * ILS Driver for VuFind to query availability information via DAIA.
 *
 * Based on the proof-of-concept-driver by Till Kinstler, GBV.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Oliver Goldschmidt <o.goldschmidt@tu-harburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
class DAIA implements DriverInterface
{
    private $_baseURL;

    /**
     * Constructor
     *
     * @access public
     */
    public function __construct()
    {
        $configArray = parse_ini_file('conf/DAIA.ini', true);

        $this->_baseURL = $configArray['Global']['baseUrl'];
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
        $holding = $this->daiaToHolding($id);
        return $holding;
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @return mixed     An array of getStatus() return values on success,
     * a PEAR_Error object otherwise.
     * @access public
     */
    public function getStatuses($ids)
    {
        $items = array();
        foreach ($ids as $id) {
            $items[] = $this->getShortStatus($id);
        }
        return $items;
    }

     /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber, duedate,
     * number, barcode; on failure, a PEAR_Error.
     * @access public
     */
    public function getHolding($id, $patron = false)
    {
        return $this->getStatus($id);
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
        return array();
    }

    /**
     * Query a DAIA server and return the result as DomDocument object.
     * The returned object is an XML document containing
     * content as described in the DAIA format specification.
     *
     * @param string $id Document to look up.
     *
     * @return DomDocument Object representation of an XML document containing
     * content as described in the DAIA format specification.
     * @access public
     */
    private function _queryDAIA($id)
    {
        $daia = new DomDocument();
        $daia->load($this->_baseURL . '?output=xml&ppn='.$id);

        return $daia;
    }

    /**
     * Flatten a DAIA response to an array of holding information.
     *
     * @param string $id Document to look up.
     *
     * @return array
     * @access public
     */
    public function daiaToHolding($id)
    {
        $daia = $this->_queryDAIA($id);
        // get Availability information from DAIA
        $documentlist = $daia->getElementsByTagName('document');
        $status = array();
        for ($b = 0; $documentlist->item($b) !== null; $b++) {
            $itemlist = $documentlist->item($b)->getElementsByTagName('item');
            $emptyResult = array(
                    'callnumber' => '-',
                    'availability' => '0',
                    'number' => 1,
                    'reserve' => 'No',
                    'duedate' => '',
                    'queue'   => '',
                    'delay'   => '',
                    'barcode' => 'No samples',
                    'status' => '',
                    'id' => $id,
                    'label' => 'No samples'
            );
            for ($c = 0; $itemlist->item($c) !== null; $c++) {
                $result = array(
                    'callnumber' => '',
                    'availability' => '0',
                    'number' => ($c+1),
                    'reserve' => 'No',
                    'duedate' => '',
                    'queue'   => '',
                    'delay'   => '',
                    'barcode' => 1,
                    'status' => '',
                    'id' => $id,
                    'itemid' => '',
                    'recallhref' => '',
                    'location' => '',
                    'location.id' => '',
                    'location.href' => '',
                    'label' => '',
                    'notes' => array()
                );
                $result['itemid'] = $itemlist->item($c)->attributes->getNamedItem('id')->nodeValue;
                if ($itemlist->item($c)->attributes->getNamedItem('href') !== null) {
                    $result['recallhref'] = $itemlist->item($c)->attributes->getNamedItem('href')->nodeValue;
                }
                $departmentElements = $itemlist->item($c)->getElementsByTagName('department');
                if ($departmentElements->length > 0) {
                    if ($departmentElements->item(0)->nodeValue) {
                        $result['location'] = $departmentElements->item(0)->nodeValue;
                        $result['location.id'] = $departmentElements->item(0)->attributes->getNamedItem('id')->nodeValue;
                        $result['location.href'] = $departmentElements->item(0)->attributes->getNamedItem('href')->nodeValue;
                    }
                }
                $storageElements = $itemlist->item($c)->getElementsByTagName('storage');
                if ($storageElements->length > 0) {
                    if ($storageElements->item(0)->nodeValue) {
                        $result['location'] = $storageElements->item(0)->nodeValue;
                        //$result['location.id'] = $storageElements->item(0)->attributes->getNamedItem('id')->nodeValue;
                        $result['location.href'] = $storageElements->item(0)->attributes->getNamedItem('href')->nodeValue;
                        //$result['barcode'] = $result['location.id'];
                    }
                }
                $barcodeElements = $itemlist->item($c)->getElementsByTagName('identifier');
                if ($barcodeElements->length > 0) {
                    if ($barcodeElements->item(0)->nodeValue) {
                        $result['barcode'] = $barcodeElements->item(0)->nodeValue;
                    }
                }
                $labelElements = $itemlist->item($c)->getElementsByTagName('label');
                if ($labelElements->length > 0) {
                    if ($labelElements->item(0)->nodeValue) {
                        $result['label'] = $labelElements->item(0)->nodeValue;
                        $result['callnumber'] = urldecode($labelElements->item(0)->nodeValue);
                    }
                }
                $messageElements = $itemlist->item($c)->getElementsByTagName('message');
                if ($messageElements->length > 0) {
                    for ($m = 0; $messageElements->item($m) !== null; $m++) {
                        if ($messageElements->item($m)->attributes->getNamedItem('errno')->nodeValue === '404') {
                            $result['status'] = 'missing';
                        } else {
                            if (is_array($result['notes'][$messageElements->item($m)->attributes->getNamedItem('errno')->nodeValue]) === false) {
                                $result['notes'][$messageElements->item($m)->attributes->getNamedItem('errno')->nodeValue] = array();
                            }
                            $result['notes'][$messageElements->item($m)->attributes->getNamedItem('errno')->nodeValue]
                            [$messageElements->item($m)->attributes->getNamedItem('lang')->nodeValue]
                                = $messageElements->item($m)->nodeValue;
                        }
                    }
                }

                //$loanAvail = 0;
                //$loanExp = 0;
                //$presAvail = 0;
                //$presExp = 0;

                $unavailableElements = $itemlist->item($c)->getElementsByTagName('unavailable');
                if ($unavailableElements->item(0) !== null) {
                    for ($n = 0; $unavailableElements->item($n) !== null; $n++) {
                        $service = $unavailableElements->item($n)->attributes->getNamedItem('service')->nodeValue;
                        if ($service === 'presentation') {
                            $result['presentation.availability'] = '0';
                            $result['presentation_availability'] = '0';
                            if ($unavailableElements->item($n)->attributes->getNamedItem('expected') !== null) {
                                $result['presentation.duedate'] = $unavailableElements->item($n)->attributes->getNamedItem('expected')->nodeValue;
                            }
                            if ($unavailableElements->item($n)->attributes->getNamedItem('queue') !== null) {
                                $result['presentation.queue'] = $unavailableElements->item($n)->attributes->getNamedItem('queue')->nodeValue;
                            }
                            $result['availability'] = '0';
                        } elseif ($service === 'loan') {
                            $result['loan.availability'] = '0';
                            $result['loan_availability'] = '0';
                            if ($unavailableElements->item($n)->attributes->getNamedItem('expected') !== null) {
                                $result['loan.duedate'] = $unavailableElements->item($n)->attributes->getNamedItem('expected')->nodeValue;
                            }
                            if ($unavailableElements->item($n)->attributes->getNamedItem('queue') !== null) {
                                $result['loan.queue'] = $unavailableElements->item($n)->attributes->getNamedItem('queue')->nodeValue;
                            }
                            $result['availability'] = '0';
                        } elseif ($service === 'interloan') {
                            $result['interloan.availability'] = '0';
                            if ($unavailableElements->item($n)->attributes->getNamedItem('expected') !== null) {
                                $result['interloan.duedate'] = $unavailableElements->item($n)->attributes->getNamedItem('expected')->nodeValue;
                            }
                            if ($unavailableElements->item($n)->attributes->getNamedItem('queue') !== null) {
                                $result['interloan.queue'] = $unavailableElements->item($n)->attributes->getNamedItem('queue')->nodeValue;
                            }
                            $result['availability'] = '0';
                        } elseif ($service === 'openaccess') {
                            $result['openaccess.availability'] = '0';
                            if ($unavailableElements->item($n)->attributes->getNamedItem('expected') !== null) {
                                $result['openaccess.duedate'] = $unavailableElements->item($n)->attributes->getNamedItem('expected')->nodeValue;
                            }
                            if ($unavailableElements->item($n)->attributes->getNamedItem('queue') !== null) {
                                $result['openaccess.queue'] = $unavailableElements->item($n)->attributes->getNamedItem('queue')->nodeValue;
                            }
                            $result['availability'] = '0';
                        }
                        // TODO: message/limitation
                        if ($unavailableElements->item($n)->attributes->getNamedItem('expected') !== null) {
                            $result['duedate'] = $unavailableElements->item($n)->attributes->getNamedItem('expected')->nodeValue;
                        }
                        if ($unavailableElements->item($n)->attributes->getNamedItem('queue') !== null) {
                            $result['queue'] = $unavailableElements->item($n)->attributes->getNamedItem('queue')->nodeValue;
                        }
                    }
                }

                $availableElements = $itemlist->item($c)->getElementsByTagName('available');
                if ($availableElements->item(0) !== null) {
                    for ($n = 0; $availableElements->item($n) !== null; $n++) {
                        $service = $availableElements->item($n)->attributes->getNamedItem('service')->nodeValue;
                        if ($service === 'presentation') {
                            $result['presentation.availability'] = '1';
                            $result['presentation_availability'] = '1';
                            if ($availableElements->item($n)->attributes->getNamedItem('delay') !== null) {
                                $result['presentation.delay'] = $availableElements->item($n)->attributes->getNamedItem('delay')->nodeValue;
                            }
                            $result['availability'] = '1';
                        } elseif ($service === 'loan') {
                            $result['loan.availability'] = '1';
                            $result['loan_availability'] = '1';
                            if ($availableElements->item($n)->attributes->getNamedItem('delay') !== null) {
                                $result['loan.delay'] = $availableElements->item($n)->attributes->getNamedItem('delay')->nodeValue;
                            }
                            $result['availability'] = '1';
                        } elseif ($service === 'interloan') {
                            $result['interloan.availability'] = '1';
                            if ($availableElements->item($n)->attributes->getNamedItem('delay') !== null) {
                                $result['interloan.delay'] = $availableElements->item($n)->attributes->getNamedItem('delay')->nodeValue;
                            }
                            $result['availability'] = '1';
                        } elseif ($service === 'openaccess') {
                            $result['openaccess.availability'] = '1';
                            if ($availableElements->item($n)->attributes->getNamedItem('delay') !== null) {
                                $result['openaccess.delay'] = $availableElements->item($n)->attributes->getNamedItem('delay')->nodeValue;
                            }
                            $result['availability'] = '1';
                        }
                        // TODO: message/limitation
                        if ($availableElements->item($n)->attributes->getNamedItem('delay') !== null) {
                            $result['delay'] = $availableElements->item($n)->attributes->getNamedItem('delay')->nodeValue;
                        }
                    }
                }
                // document has no availability elements, so set availability and barcode to -1
                if ($availableElements->item(0) === null && $unavailableElements->item(0) === null) {
                    $result['availability'] = '-1';
                    $result['barcode'] = '-1';
                }
                $status[] = $result;
                /* $status = "available";
                if (loanAvail) return 0;
                if (presAvail) {
                    if (loanExp) return 1;
                    return 2;
                }
                if (loanExp) return 3;
                if (presExp) return 4;
                return 5;
                */
            }
            if (count($status) === 0) {
                $status[] = $emptyResult;
            }
        }
        return $status;
    }

    /**
     * Return an abbreviated set of status information.
     *
     * @param string $id The record id to retrieve the status for
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber, duedate,
     * number; on failure, a PEAR_Error.
     * @access public
     */
    public function getShortStatus($id)
    {
        $daia = $this->_queryDAIA($id);
        // get Availability information from DAIA
        $itemlist = $daia->getElementsByTagName('item');
        $label = "Unknown";
        $storage = "Unknown";
        $presenceOnly = '1';
        $holding = array();
        for ($c = 0; $itemlist->item($c) !== null; $c++) {
            $storageElements = $itemlist->item($c)->getElementsByTagName('storage');
            if ($storageElements->item(0)->nodeValue) {
                if ($storageElements->item(0)->nodeValue === 'Internet') {
                    $storage = '<a href="'.$storageElements->item(0)->attributes->getNamedItem('href')->nodeValue.'">'.$storageElements->item(0)->attributes->getNamedItem('href')->nodeValue.'</a>';
                } else {
                    $storage = $storageElements->item(0)->nodeValue;
                }
            }
            $labelElements = $itemlist->item($c)->getElementsByTagName('label');
            if ($labelElements->item(0)->nodeValue) {
                $label = $labelElements->item(0)->nodeValue;
            }
            $availableElements = $itemlist->item($c)->getElementsByTagName('available');
            if ($availableElements->item(0) !== null) {
                $availability = 1;
                $status = 'Available';
                if ($availableElements->item(0)->attributes->getNamedItem('href') !== null) {
                    $earliest_href = $availableElements->item(0)->attributes->getNamedItem('href')->nodeValue;
                }
                for ($n = 0; $availableElements->item($n) !== null; $n++) {
                    if ($availableElements->item($n)->getAttribute('service') === 'loan') {
                        $presenceOnly = '0';
                    }
                    // $status .= ' ' . $availableElements->item($n)->getAttribute('service');
                }
            } else {
                $leanable = 1;
                $unavailableElements = $itemlist->item($c)->getElementsByTagName('unavailable');
                if ($unavailableElements->item(0) !== null) {
                    $earliest = array();
                    $queue = array();
                    $hrefs = array();
                    for ($n = 0; $unavailableElements->item($n) !== null; $n++) {
                        if ($unavailableElements->item($n)->attributes->getNamedItem('href') !== null) {
                            $hrefs['item'.$n] = $unavailableElements->item($n)->attributes->getNamedItem('href')->nodeValue;
                        }
                        if ($unavailableElements->item($n)->attributes->getNamedItem('expected') !== null) {
                            //$duedate = $unavailableElements->item($n)->attributes->getNamedItem('expected')->nodeValue;
                            //$duedate_arr = explode('-', $duedate);
                            //$duedate_timestamp = mktime('0', '0', '0', $duedate_arr[1], $duedate_arr[2], $duedate_arr[0]);
                            //array_push($earliest, array('expected' => $unavailableElements->item($n)->attributes->getNamedItem('expected')->nodeValue,
                            //                            'recall' => $unavailableElements->item($n)->attributes->getNamedItem('href')->nodeValue);
                            //array_push($earliest, $unavailableElements->item($n)->attributes->getNamedItem('expected')->nodeValue);
                            $earliest['item'.$n] = $unavailableElements->item($n)->attributes->getNamedItem('expected')->nodeValue;
                        } else {
                            array_push($earliest, "0");
                        }
                        if ($unavailableElements->item($n)->attributes->getNamedItem('queue') !== null) {
                            $queue['item'.$n] = $unavailableElements->item($n)->attributes->getNamedItem('queue')->nodeValue;
                        } else {
                            array_push($queue, "0");
                        }
                    }
                }
                if (count($earliest) > 0) {
                    arsort($earliest);
                    $earliest_counter = 0;
                    foreach ($earliest as $earliest_key => $earliest_value) {
                        if ($earliest_counter === 0) {
                            $earliest_duedate = $earliest_value;
                            $earliest_href = $hrefs[$earliest_key];
                            $earliest_queue = $queue[$earliest_key];
                        }
                        $earliest_counter = 1;
                    }
                } else {
                    $leanable = 0;
                }
                $messageElements = $itemlist->item($c)->getElementsByTagName('message');
                if ($messageElements->length > 0) {
                    if ($messageElements->item(0)->attributes->getNamedItem('errno')->nodeValue === '404') {
                        $status = 'missing';
                    }
                }
                if (!$status) {
                    $status = 'Unavailable';
                }
                $availability = 0;
            }
            $reserve = 'N';
            if ($earliest_queue > 0) {
                $reserve = 'Y';
            }
            $holding[] = array('availability' => $availability,
                   'id' => $id,
                   'status' => "$status",
                   'location' => "$storage",
                   'reserve' => $reserve,
                   'queue' => $earliest_queue,
                   'callnumber' => "$label",
                   'duedate' => $earliest_duedate,
                   'leanable' => $leanable,
                   'recallhref' => $earliest_href,
                   'number' => ($c+1),
                   'presenceOnly' => $presenceOnly);
        }
        return $holding;
    }
}
?>