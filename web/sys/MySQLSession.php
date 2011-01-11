<?php
/**
 * MySQL session handler
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
require_once 'SessionInterface.php';
require_once 'services/MyResearch/lib/Session.php';

/**
 * MySQL session handler
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class MySQLSession extends SessionInterface
{
    static public function read($sess_id)
    {
        $s = new Session();
        $s->session_id = $sess_id;

        if ($s->find(true)) {
            // enforce lifetime of this session data
            if ($s->last_used + self::$lifetime > time()) {
                $s->last_used = time();
                $s->update();
                return $s->data;
            } else {
                $s->delete();
            return '';
            }
        } else {
            // in seconds - easier for calcuating duration
            $s->last_used = time();
            // in date format - easier to read
            $s->created = date('Y-m-d h:i:s');
            $s->insert();
            return '';
        }
    }

    static public function write($sess_id, $data)
    {
        $s = new Session();
        $s->session_id = $sess_id;
        if ($s->find(true)) {
            $s->data = $data;
            return $s->update();
        } else {
            return false;
        }
    }

    static public function destroy($sess_id)
    {
        // Perform standard actions required by all session methods:
        parent::destroy($sess_id);

        // Now do database-specific destruction:
        $s = new Session();
        $s->session_id = $sess_id;
        return $s->delete();
    }

    static public function gc($sess_maxlifetime)
    {
        $s = new Session();
        $s->whereAdd('last_used + ' . $sess_maxlifetime . ' < ' . time());
        $s->delete(true);
    }
}

?>
