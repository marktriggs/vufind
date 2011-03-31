<?php
/**
 * Integration testing of Record module.
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
 * @package  Tests
 * @author   Preetha Rao <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */

require_once dirname(__FILE__) . '/../lib/SeleniumTestCase.php';
//error_reporting(E_ALL);

/**
 * Integration testing of Record module.
 *
 * @category VuFind
 * @package  Tests
 * @author   Preetha Rao <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */
class Record_Functions extends SeleniumTestCase
{
    /**
     * Test various features on the record view page.
     *
     * @return void
     * @access public
     */
    public function testRecord()
    {
        $this->open($this->baseUrl);
        $this->assertRegExp("/Search Home/", $this->getTitle());
        $this->click("link=A - General Works");
        $this->waitForPageToLoad("$this->timeout");
        $this->click(
            "link=Journal of rational emotive therapy : " .
            "the journal of the Institute for Rational-Emotive Therapy."
        );

        $this->waitForPageToLoad("$this->timeout");

        /* Test the Cite This functionality */
        //echo "Testing Cite This Functionality";
        $this->click("link=Cite this");
        $this->waitForElementPresent("id=popupboxContent");

        //echo "\nAsserting if Cite This Lightbox was opened";
        $this->assertElementPresent("id=popupboxContent");                          //Asserting if Lightbox was opened

        //echo "\nAsserting Lightbox Contents";
        $this->verifyTextPresent($this->apa_text);
        $this->verifyTextPresent("Institute for Rational-Emotive Therapy (New York, N. (1983). Journal of rational emotive therapy: The journal of the Institute for Rational-Emotive Therapy. [New York]: The Institute. ");
        $this->verifyTextPresent($this->mla_text);
        $this->verifyTextPresent("Institute for Rational-Emotive Therapy (New York, N.Y.). Journal of Rational Emotive Therapy: The Journal of the Institute for Rational-Emotive Therapy. [New York]: The Institute, 1983. ");
        $this->verifyTextPresent("Warning: These citations may not always be 100% accurate.");

        $this->click("link=close");                                                     // Close the light box
        //echo "\nAsserting if Lightbox was closed\n";
        $this->assertNotVisible("id=popupbox");                                         // Assert that the lightbox is not visible

        /* Test the Text This functionlaity */
        //echo "\nTesting Text This Functionality";
        $this->click("link=Text this");
        $this->waitForElementPresent("id=popupboxContent");

        //echo "\nAsserting if Text This Lightbox was opened";
        $this->assertElementPresent("id=popupboxContent");                          //Asserting if Lightbox was opened

        //echo "\nSubmitting without any data";
        //$this->type("name=to","2155467886");
        $this->click("name=submit");

        //echo "\nAsserting Error message";
        $jsstring = "{selenium.browserbot.getCurrentWindow().document.getElementById('popupDetails').style.display == \"block\";}" ;
        $this->waitForCondition($jsstring, "$this->timeout");
        $this->verifyTextPresent($this->sms_failure);

        $this->click("link=close");                                               // Close the light box ( no close button cuurently )
        //echo "\nAsserting if Lightbox was closed\n";
        $this->assertNotVisible("id=popupbox");                                   // Assert that the lightbox is not visible

        /* Test the Email This functionlaity  */
        //echo "\n\nTesting Email This Functionality";
        $this->click("link=Email this");
        $this->waitForElementPresent("id=popupboxContent");

        //echo "\nAsserting if Email This Lightbox was opened";
        $this->assertElementPresent("id=popupboxContent");                            //Asserting if Lightbox was opened
        //echo "\nSubmitting without any data";
        $this->click("name=submit");
        //echo "\nAsserting Error message";
        $jsstring = "{selenium.browserbot.getCurrentWindow().document.getElementById('popupDetails').style.display == \"block\";}" ;
        $this->waitForCondition($jsstring, "$this->timeout");
        $this->verifyTextPresent(preg_replace('/\s\s+/', ' ', $this->email_failure . ": " . $this->email_rcpt_err));

        //echo "\nSubmitting without sender data";
        $this->type("name=to", "abc@xyz.com");       // Valid email id format
        $this->type("name=from", "abc");             // Invalid email id format
        $this->click("name=submit");

        //echo "\nAsserting Error message";
        $jsstring = "{selenium.browserbot.getCurrentWindow().document.getElementById('popupDetails').style.display == \"block\";}" ;
        $this->waitForCondition($jsstring, "$this->timeout");
        $this->verifyTextPresent(preg_replace('/\s\s+/', ' ', $this->email_failure . ": " . $this->email_sndr_err));

        $this->click("link=close");                 // Close the light box
        //echo "\nAsserting if Lightbox was closed\n";
        $this->assertNotVisible("id=popupbox");     // Assert that the lightbox is not visible
    }
}
?>

