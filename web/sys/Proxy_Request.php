<?php
/**
 * Proxy Server Support for VuFind
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2009.
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
 */

// Suppress error output when importing the PEAR HTTP_Request module; some versions
// output deprecation warnings which interfere with AJAX requests in debug mode.
@require_once 'HTTP/Request.php';

/**
 * Proxy_Request Class
 *
 * This is a wrapper class around the PEAR HTTP_Request which adds proxy server
 * support.
 *
 * @category VuFind
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class Proxy_Request extends HTTP_Request
{
    private $useProxy = null;

    /**
     * Disable the proxy manually
     *
     * @return void
     */
    function disableProxy()
    {
        $this->useProxy = false;
    }

    /**
     * Enable the proxy manually
     *
     * @return void
     */
    function useProxy()
    {
        $this->useProxy = true;
    }

    /**
     * Time to send the request
     *
     * @param bool $saveBody
     *
     * @return void
     */
    function SendRequest($saveBody = true)
    {
        // Unless the user has expressly set the proxy setting
        $defaultProxy = null;
        if ($this->useProxy == null) {
            // Is this localhost traffic?
            if (strstr($this->getUrl(), "localhost") !== false) {
                // Yes, don't proxy
                $defaultProxy = false;
            }
        }

        // Setup the proxy if needed
        //   useProxy + defaultProxy both need to be null or true
        if ($this->useProxy !== false && $defaultProxy !== false) {
            global $configArray;

            // Proxy server settings
            if (isset($configArray['Proxy']['host'])) {
                if (isset($configArray['Proxy']['port'])) {
                    $this->setProxy($configArray['Proxy']['host'], $configArray['Proxy']['port']);
                } else {
                    $this->setProxy($configArray['Proxy']['host']);
                }
            }
        }

        // Send the request via the parent class
        parent::sendRequest($saveBody);
    }
}

?>