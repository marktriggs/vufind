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

class MCryptEncryption extends Encryption
{
    private $configArray = array();

    public function __construct($configArray)
    {
        $this->configArray = $configArray;
    }


    private function getKey()
    {
        $key = $this->configArray['Encryption']['key'];

        if (!isset($key)) {
            throw new Exception("No config entry found for " .
                                "'Encryption' -> 'key'");
        }

        $key = mhash(MHASH_SHA256, $key);
    }


    public function encrypt($plaintext)
    {
        $key = $this->getKey();

        $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256,
                                                  MCRYPT_MODE_CBC),
                               MCRYPT_DEV_URANDOM);

        if (!$iv) {
            $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256,
                                                      MCRYPT_MODE_CBC),
                                   MCRYPT_RAND);
        }

        $ciphertext = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $plaintext,
                                     MCRYPT_MODE_CBC, $iv);

        return base64_encode($iv) . ":" . base64_encode($ciphertext);
    }


    public function decrypt($ciphertext)
    {
        $parts = explode(":", $ciphertext, 2);

        $iv = base64_decode($parts[0]);
        $ciphertext = base64_decode($parts[1]);
        $key = $this->getKey();

        return rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $ciphertext,
                                    MCRYPT_MODE_CBC, $iv),
                     "\0");
    }
}

?>