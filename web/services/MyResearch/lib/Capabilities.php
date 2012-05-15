<?php
/**
 * Table Definition for capabilities
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2012.
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
 * @package  DB_DataObject
 * @author   Mark Triggs <mark@dishevelled.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://pear.php.net/package/DB_DataObject/ PEAR Documentation
 */
require_once 'DB/DataObject.php';

/**
 * Table Definition for capabilities
 *
 * @category VuFind
 * @package  DB_DataObject
 * @author   Mark Triggs <mark@dishevelled.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://pear.php.net/package/DB_DataObject/ PEAR Documentation
 */ // @codingStandardsIgnoreStart
class Capabilities extends DB_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'capabilities';        // table name
    public $id;                              // int(11)  not_null primary_key auto_increment
    public $capability;                      // string(128)  unique_key
    public $value;                           // string(128)

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Capabilities',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
    // @codingStandardsIgnoreEnd


    public static function getCapability($capabilityName, $default = false)
    {
        $capabilities = new Capabilities();
        $capabilities->capability = $capabilityName;

        if ($capabilities->find(true)) {
            return $capabilities->value;
        } else {
            return $default;
        }
    }


    public static function setCapability($capabilityName, $newValue)
    {
        $capabilities = new Capabilities();
        $capabilities->capability = $capabilityName;

        if ($capabilities->find(true)) {
            $capabilities->value = $newValue;
            $capabilities->update();
        } else {
            $capabilities->value = $newValue;
            $capabilities->insert();
        }
    }
}
