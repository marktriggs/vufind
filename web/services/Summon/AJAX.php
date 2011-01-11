<?php
/**
 * AJAX handler for Summon module.
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
 * @package  Controller_Summon
 * @author   Andrew Nagy <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_module Wiki
 */
require_once 'Action.php';

require_once 'sys/Summon.php';

/**
 * AJAX handler for Summon module.
 *
 * @category VuFind
 * @package  Controller_Summon
 * @author   Andrew Nagy <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_module Wiki
 */
class AJAX extends Action
{
    /**
     * Process parameters and display response.
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

    /**
     * Saves a Record to User's Account
     *
     * @return void
     * @access public
     */
    public function SaveRecord()
    {
        global $configArray;

        require_once 'services/MyResearch/lib/User.php';
        require_once 'services/MyResearch/lib/Resource.php';

        // check if user is logged in
        if (!($user = UserAccount::isLoggedIn())) {
            echo "<result>Unauthorized</result>";
            return;
        }

        $resource = new Resource();
        $resource->record_id = $_GET['id'];
        if (!$resource->find(true)) {
            $resource->insert();
        }

        preg_match_all('/"[^"]*"|[^ ]+/', $_GET['tags'], $tagArray);
        $user->addResource($resource, $tagArray[0], $_GET['notes']);
        echo "<result>Done</result>";
    }

    /**
     * Saves a search to User's Account
     *
     * @return void
     * @access public
     */
    public function SaveSearch()
    {
        require_once 'services/MyResearch/lib/User.php';
        require_once 'services/MyResearch/lib/Search.php';

        //check if user is logged in
        if (!($user = UserAccount::isLoggedIn())) {
            echo "<result>Please Log in.</result>";
            return;
        }

        $lookfor = $_GET['lookfor'];
        $limitto = urldecode($_GET['limit']);
        $type = $_GET['type'];

        $search = new Search();
        $search->user_id = $user->id;
        $search->limitto = $limitto;
        $search->lookfor = $lookfor;
        $search->type = $type;
        if (!$search->find()) {
            $search = new Search();
            $search->user_id = $user->id;
            $search->lookfor = $lookfor;
            $search->limitto = $limitto;
            $search->type = $type;
            $search->created = date('Y-m-d');

            $search->insert();
        }
        echo "<result>Done</result>";
    }

    /**
     * Email Record
     *
     * @return void
     * @access public
     */
    public function SendEmail()
    {
        require_once 'services/Summon/Email.php';

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

    /**
     * Get status of whether record is currently saved.
     *
     * @return void
     * @access public
     */
    public function GetSaveStatus()
    {
        require_once 'services/MyResearch/lib/User.php';
        require_once 'services/MyResearch/lib/Resource.php';

        // check if user is logged in
        if (!($user = UserAccount::isLoggedIn())) {
            echo "<result>Unauthorized</result>";
            return;
        }

        // Check if resource is saved to favorites
        $resource = new Resource();
        $resource->record_id = $_GET['id'];
        if ($resource->find(true)) {
            if ($user->hasResource($resource)) {
                echo '<result>Saved</result>';
            } else {
                echo '<result>Not Saved</result>';
            }
        } else {
            echo '<result>Not Saved</result>';
        }
    }

    /**
     * Get Save Statuses
     *
     * This is responsible for printing the save status for a collection of
     * records in XML format.
     *
     * @return void
     * @access public
     * @author Chris Delis <cedelis@uillinois.edu>
     */
    public function GetSaveStatuses()
    {
        require_once 'services/MyResearch/lib/User.php';
        require_once 'services/MyResearch/lib/Resource.php';

        // check if user is logged in
        if (!($user = UserAccount::isLoggedIn())) {
            echo "<result>Unauthorized</result>";
            return;
        }

        foreach ($_GET['id'] as $id) {
            echo '<item id="' . $id . '">';

            // Check if resource is saved to favorites
            $resource = new Resource();
            $resource->record_id = $id;
            if ($resource->find(true)) {
                $dataList = $user->getSavedData($id);
                if ($dataList) {
                    echo '<result>';
                    foreach ($dataList as $data) {
                        echo '{"id":"' . $data->list_id . '","title":"' . $data->list_title . '"}';
                    }
                    echo '</result>';
                } else {
                    echo '<result>False</result>';
                }
            } else {
                echo '<result>False</result>';
            }

            echo '</item>';
        }
    }

    /**
     * Load saved data from the database.
     *
     * @return void
     * @access public
     */
    public function GetSavedData()
    {
        require_once 'services/MyResearch/lib/User.php';
        require_once 'services/MyResearch/lib/Resource.php';

        echo "<result>\n";

        // check if user is logged in
        if (!($user = UserAccount::isLoggedIn())) {
            echo "<result>Unauthorized</result>";
            return;
        }

        $saved = $user->getSavedData($_GET['id']);
        if ($saved->notes) {
            echo "  <Notes>$saved->notes</Notes>\n";
        }

        $myTagList = $user->getTags($_GET['id']);
        if (count($myTagList)) {
            foreach ($myTagList as $tag) {
                echo "  <Tag>" . $tag->tag . "</Tag>\n";
            }
        }

        echo '</result>';
    }
}

?>
