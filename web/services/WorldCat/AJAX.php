<?php
/**
 * AJAX handler for WorldCat module
 *
 * PHP version 5
 *
 * Copyright (C) Andrew Nagy 2009.
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
 * @package  Controller_WorldCat
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_module Wiki
 */
require_once 'Action.php';

/**
 * AJAX handler for WorldCat module
 *
 * @category VuFind
 * @package  Controller_WorldCat
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_module Wiki
 */
class AJAX extends Action
{
    /**
     * Process incoming parameters and output an AJAX response.
     *
     * @return void
     * @access public
     */
    public function launch()
    {
        header('Content-type: text/xml');
        header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        echo '<?xml version="1.0" encoding="UTF-8"?' . ">\n";
        echo "<AJAXResponse>\n";
        if (is_callable(array($this, $_GET['method']))) {
            $method = $_GET['method']; 
            $this->$method();
        } else {
            echo '<Error>Invalid Method</Error>';
        }
        echo '</AJAXResponse>';
    }    
    
    // Email Record
    function SendEmail()
    {
        require_once 'services/WorldCat/Email.php';

        // Ensure that id is properly set; the Email service expects it as a GET
        // parameter, but in the AJAX context it may actually be elsewhere.
        $_GET['id'] = $_REQUEST['id'];

        $emailService = new Email();
        $result = $emailService->sendEmail($_REQUEST['to'], $_REQUEST['from'],
            $_REQUEST['message']);

        if (PEAR::isError($result)) {
            echo '<result>Error</result><details>' . 
                htmlspecialchars($result->getMessage()) . '</details>';
        } else {
            echo '<result>Done</result>';
        }
    }
}

?>
