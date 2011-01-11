<?php
/**
 * Base class for session handling
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
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
require_once 'services/MyResearch/lib/Search.php';

/**
 * Session Interface
 *
 * Base class for session handling
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class SessionInterface
{
    static public $lifetime = 3600;

    public function init($lt)
    {
        self::$lifetime = $lt;
        session_set_save_handler(array(get_class($this), 'open'), array(get_class($this),'close'), array(get_class($this),'read'), array(get_class($this),'write'), array(get_class($this),'destroy'), array(get_class($this),'gc'));
        session_start();
    }

    // the following need to be static since they are used as callback functions
    static public function open($sess_path, $sess_name)
    {
        return true;
    }

    static public function close()
    {
        return true;
    }

    static public function read($sess_id)
    {
    }

    static public function write($sess_id, $data)
    {
    }

    // IMPORTANT:  The functionality defined in this method is global to all session
    //      mechanisms.  If you override this method, be sure to still call
    //      parent::destroy() in addition to any new behavior.
    static public function destroy($sess_id)
    {
        // Delete the searches stored for this session
        $search = new SearchEntry();
        $searchList = $search->getSearches($sess_id);
        // Make sure there are some
        if (count($searchList) > 0) {
            foreach ($searchList as $oldSearch) {
                // And make sure they aren't saved
                if ($oldSearch->saved == 0) {
                    $oldSearch->delete();
                }
            }
        }
    }

    // how often does this get called (if at all)?

    // *** 08/Oct/09 - Greg Pendlebury
    // Clearly this is being called. Production installs with
    //   thousands of sessions active are showing no old sessions.
    // What I can't do is reproduce for testing. It might need the
    //   search delete code from 'destroy()' if it is not calling it.
    // *** 09/Oct/09 - Greg Pendlebury
    // Anecdotal testing Today and Yesterday seems to indicate destroy()
    //   is called by the garbage collector and everything is good.
    // Something to keep in mind though.
    static public function gc($sess_maxlifetime)
    {
    }
}

?>
