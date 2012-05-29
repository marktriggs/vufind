<?php
/**
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
 * @package  Crypt
 * @author   Mark Triggs <mark@dishevelled.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */

abstract class Encryption
{
    abstract public function __construct($configArray);


    /**
     * Encrypt a string
     *
     * @param string $plaintext to be encrypted
     *
     * @return string An encrypted ciphertext
     * @access public
     */
    abstract public function encrypt($plaintext);


    /**
     * Decrypt a string
     *
     * @param string $ciphertext to be decrypted
     *
     * @return string The decrypted plaintext
     * @access public
     */
    abstract public function decrypt($ciphertext);


    public function getInstance()
    {
        global $configArray;

        require_once 'MCryptEncryption.php';
        return new MCryptEncryption($configArray);
    }
}

?>