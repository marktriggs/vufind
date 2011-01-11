<?php
/**
 * printms Smarty plugin
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
 * @package  Smarty_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_plugin Wiki
 */

/**
 * Smarty plugin
 * -------------------------------------------------------------
 * Type:     modifier
 * Name:     printms
 * Purpose:  Prints a human readable format from a number of milliseconds
 * -------------------------------------------------------------
 *
 * @param float $ms Number of milliseconds
 *
 * @return string   Human-readable representation
 */
function smarty_modifier_printms($ms)
{
    $seconds = floor($ms/1000);
    $ms = ($ms % 1000);

    $minutes = floor($seconds/60);
    $seconds = ($seconds % 60);

    $hours = floor($minutes/60);
    $minutes = ($minutes % 60);

    if ($hours) {
        $days = floor($hours/60);
        $hours = ($hours % 60);

        if ($days) {
            $years = floor($days/365);
            $days = ($days % 365);

            if ($years) {
                return sprintf("%dyears %ddays %dhours %dminutes %dseconds",
                               $years, $days, $hours, $minutes, $seconds);
            } else {
                return sprintf("%ddays %dhours %dminutes %dseconds",
                               $days, $hours, $minutes, $seconds);
            }
        } else {
            return sprintf("%dhours %dminutes %dseconds",
                           $hours, $minutes, $seconds);
        }
    } else {
        return sprintf("%dminutes %dseconds", $minutes, $seconds);
    }
}
?>