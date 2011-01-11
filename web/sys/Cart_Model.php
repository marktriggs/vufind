<?php
/**
 * Cart_Model Class
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
 * @package  Support_Classes
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */

/**
 * Cart_Model Class
 *
 * The data model object representing a user's book cart.
 *
 * @category VuFind
 * @package  Support_Classes
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class Cart_Model
{
    private static $singleton;
    private $items;

    const CART_COOKIE =  'vufind_cart';
    const CART_COOKIE_DELIM = "\t";

    /**
     * Private constructor to ensure singleton pattern.
     *
     * @access private
     */
    private function __construct()
    {
        $this->items = array();
    }

    /**
     * Get the current instance of the user's cart, if
     * it is not initialized, then one will be initialized.
     *
     * @return Cart_Model
     * @access public
     */
    static function getInstance()
    {
        if (!Cart_Model::$singleton) {
            $cart = new Cart_Model();
            $cart->init();
            Cart_Model::$singleton = $cart;
        }
        return Cart_Model::$singleton;
    }

    /**
     * Return the contents of the cart.
     *
     * @return array     array of items in the cart
     * @access public
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Empty the cart.
     *
     * @return void
     * @access public
     */
    public function emptyCart()
    {
        $this->items = array();
        $this->save();
    }

    /**
     * Add an item to the cart.
     *
     * @return void
     * @access public
     */
    public function addItem($item)
    {
        $this->addItems(array($item));
    }

    /**
     * Add an array of items to the cart.
     *
     * @return void
     * @access public
     */
    public function addItems($items)
    {
        $results = array_unique(array_merge($this->items, $items));
        $this->items = $results;
        $this->save();
    }

    /**
     * Remove an item from the cart.
     *
     * @return void
     * @access public
     */
    public function removeItem($item)
    {
        $results = array();
        foreach ($this->items as $id) {
            if ($id != $item) {
                $results[] = $id;
            }
        }
        $this->items = $results;
        $this->save();
    }

    /**
     * Check whether the cart is empty.
     *
     * @return boolean   true if empty, false otherwise
     * @access public
     */
    public function isEmpty()
    {
        return empty($this->items);
    }

    /**
     * Initialize the cart model.
     *
     * @return array   contents of the cart
     * @access private
     */
    private function init()
    {
        $items = null;
        if (isset($_COOKIE[Cart_Model::CART_COOKIE])) {
            $cookie = $_COOKIE[Cart_Model::CART_COOKIE];
            $items = explode(Cart_Model::CART_COOKIE_DELIM, $cookie);
        }
        $this->items = $items ? $items : array();
    }

    /**
     * Save the state of the cart. This implementation uses cookie
     * so the cart contents can be manipulated on the client side as well.
     *
     * @return void
     * @access private
     */
    private function save()
    {
        $cookie = implode(Cart_Model::CART_COOKIE_DELIM, $this->items);
        setcookie(Cart_Model::CART_COOKIE, $cookie, 0, '/');
    }
}
