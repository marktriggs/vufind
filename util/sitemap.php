<?php
/**
 * Command-line tool to generate sitemaps based on Solr index contents.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2009.
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
 * @package  Utilities
 * @author   David K. Uspal <david.uspal@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/search_engine_optimization Wiki
 */

//ini_set('memory_limit', '50M');
//ini_set('max_execution_time', '3600');

/**
 * Set up util environment
 */
require_once 'util.inc.php';

// Read Config file
$base = dirname(__FILE__);
$configArray = parse_ini_file($base . '/../web/conf/config.ini', true);
if (!$configArray) {
    PEAR::raiseError(new PEAR_Error("Can't open file - ../web/conf/config.ini"));
}
$sitemapArray = parse_ini_file($base . '/../web/conf/sitemap.ini', true);
if (!$sitemapArray) {
    PEAR::raiseError(new PEAR_Error("Can't open file - ../web/conf/sitemap.ini"));
}

$result_url = $configArray['Site']['url'] . "/" . "Record" . "/";
$sitemap_url = $configArray['Index']['url'] . "/" .
    $configArray['Index']['default_core'] . "/sitemap?wt=json";
$frequency = htmlspecialchars($sitemapArray['Sitemap']['frequency']);
$countPerPage = $sitemapArray['Sitemap']['countPerPage'];
$fileStart = $sitemapArray['Sitemap']['fileLocation'] . "/" .
    $sitemapArray['Sitemap']['fileName'];

$currentPage = 1;
$keep_going = 1;

while ($keep_going == 1) {
    if ($currentPage == 1) {
        $fileWhole = $fileStart . ".xml";
    } else {
        $fileWhole = $fileStart . "-" . $currentPage . ".xml";
    }

    $current_url = $sitemap_url . "&currentPage=" . $currentPage .
        "&countPerPage=" . $countPerPage;
    $current_page_info_json = file_get_contents($current_url);
    $current_page_info_array = json_decode($current_page_info_json, true);

    if (count($current_page_info_array["sitemap"]["idSet"]) < 1) {
        $keep_going = 0;
    } else {
        $smf = fopen($fileWhole, 'w');
        if (!$smf) {
            PEAR::raiseError(new PEAR_Error("Can't open file - " . $fileWhole));
        }
        fwrite($smf, '<?xml version="1.0" encoding="UTF-8"?>' . "\n");
        fwrite($smf, '<urlset' . "\n");
        fwrite($smf, '     xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n");
        fwrite($smf, '     xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n");
        fwrite($smf, '     xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9' . "\n");
        fwrite($smf, '     http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . "\n\n");

        for ($i = 0; $i < count($current_page_info_array["sitemap"]["idSet"]); $i++) {
            $loc = htmlspecialchars(
                $result_url . $current_page_info_array["sitemap"]["idSet"][$i]
            );
            fwrite($smf, '<url>' . "\n");
            fwrite($smf, '  <loc>' . $loc . '</loc>' . "\n");
            fwrite($smf, '  <changefreq>' . $frequency . '</changefreq>' . "\n");
            fwrite($smf, '</url>' . "\n");
        }

        fwrite($smf, '</urlset>');
        fclose($smf);
    }

    $currentPage++;
}

// Set-up Sitemap Index
if (isset($sitemapArray['SitemapIndex']['indexFileName'])) {
    $fileWhole = $sitemapArray['Sitemap']['fileLocation'] . "/" .
        $sitemapArray['SitemapIndex']['indexFileName']. ".xml";
    $smf = fopen($fileWhole, 'w');
    if (!$smf) {
        PEAR::raiseError(new PEAR_Error("Can't open file - " . $fileWhole));
    }
    fwrite($smf, '<?xml version="1.0" encoding="UTF-8"?>' . "\n");
    fwrite($smf, '<sitemapindex' . "\n");
    fwrite($smf, '     xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n");
    fwrite($smf, '     xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n");
    fwrite($smf, '     xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9' . "\n");
    fwrite($smf, '     http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . "\n");

    // Add a <sitemap /> group for a static sitemap file. See sitemap.ini for more
    // information on this option.
    if (isset($sitemapArray['SitemapIndex']['baseSitemapFileName'])) {
        $baseSitemapFile = $sitemapArray['Sitemap']['fileLocation'] . "/" .
            $sitemapArray['SitemapIndex']['baseSitemapFileName'] . ".xml";
        // Only add the <sitemap /> group if the file exists in the directory where
        // the other sitemap files are saved, i.e. ['Sitemap']['fileLocation']
        if (file_exists($baseSitemapFile)) {
            $loc = htmlspecialchars(
                $configArray['Site']['url'] . "/" . 
                $sitemapArray['SitemapIndex']['baseSitemapFileName']. ".xml"
            );
            $lastmod = htmlspecialchars(date("Y-m-d"));
            fwrite($smf, '  <sitemap>' . "\n");
            fwrite($smf, '    <loc>' . $loc . '</loc>' . "\n");
            fwrite($smf, '    <lastmod>' . $lastmod . '</lastmod>' . "\n");
            fwrite($smf, '  </sitemap>' . "\n");
        } else {
            print "WARNING: Can't open file " . $baseSitemapFile . ". " .
                "The sitemap index will be generated without this sitemap file.\n";
        }
    }

    // Add <sitemap /> group for each sitemap file generated.
    for ($i = 1; $i < $currentPage - 1; $i++) {
        $sitemapNumber = ($i == 1) ? "" : "-" . $i;
        $loc = htmlspecialchars(
            $configArray['Site']['url'] . "/" .
            $sitemapArray['Sitemap']['fileName'] . $sitemapNumber . ".xml"
        );
        $lastmod = htmlspecialchars(date("Y-m-d"));
        fwrite($smf, '  <sitemap>' . "\n");
        fwrite($smf, '    <loc>' . $loc . '</loc>' . "\n");
        fwrite($smf, '    <lastmod>' . $lastmod . '</lastmod>' . "\n");
        fwrite($smf, '  </sitemap>' . "\n");
    }

    fwrite($smf, '</sitemapindex>');
    fclose($smf);
}
?>
