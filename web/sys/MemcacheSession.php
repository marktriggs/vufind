<?php
/**
 * MemCache session handler
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

/**
 * Memcache session handler
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class MemcacheSession extends SessionInterface
{
    static private $connection;

    public function init($lt)
    {
        global $configArray;

        // Set defaults if nothing set in config file.
        $host = isset($configArray['Session']['memcache_host']) ?
            $configArray['Session']['memcache_host'] : 'localhost';
        $port = isset($configArray['Session']['memcache_port']) ?
            $configArray['Session']['memcache_port'] : 11211;
        $timeout = isset($configArray['Session']['memcache_connection_timeout']) ?
            $configArray['Session']['memcache_connection_timeout'] : 1;

        // Connect to Memcache:
        self::$connection = new Memcache();
        if (!@self::$connection->connect($host, $port, $timeout)) {
            PEAR::raiseError(new PEAR_Error("Could not connect to Memcache (host = {$host}, port = {$port})."));
        }

        // Call standard session initialization from this point.
        parent::init($lt);
    }

    static public function read($sess_id)
    {
        return self::$connection->get("vufind_sessions/{$sess_id}");
    }

    static public function write($sess_id, $data)
    {
        return self::$connection->set("vufind_sessions/{$sess_id}", $data, 0, self::$lifetime);
    }

    static public function destroy($sess_id)
    {
        // Perform standard actions required by all session methods:
        parent::destroy($sess_id);

        // Perform Memcache-specific cleanup:
        return self::$connection->delete("vufind_sessions/{$sess_id}");
    }
}


?>
