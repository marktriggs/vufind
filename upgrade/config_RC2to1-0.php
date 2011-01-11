<?php
/**
 * Script to upgrade VuFind configuration files.
 *
 * command line arguments:
 *   * path to RC2 installation
 *   * path to 1.0 config.ini file (optional)
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
 * @package  Upgrade_Tools
 * @author   Till Kinstler <kinstler@gmail.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/migration_notes Wiki
 */

// Make sure required parameter is present:
$old_config_path = isset($argv[1]) ? $argv[1] : '';
if (empty($old_config_path)) {
    die("Usage: {$argv[0]} [RC2 base path] [1.0 config.ini file (optional)]\n");
}

// Build input file paths:
$old_config_input = str_replace(
    array('//',"\\"), '/', $old_config_path . '/web/conf/config.ini'
);
$old_facets_input = str_replace(
    array('//',"\\"), '/', $old_config_path . '/web/conf/facets.ini'
);
$old_search_input = str_replace(
    array('//',"\\"), '/', $old_config_path . '/web/conf/searches.ini'
);
$new_config_input = empty($argv[2]) ? 'web/conf/config.ini' : $argv[2];
$new_facets_input = 'web/conf/facets.ini';
$new_search_input = 'web/conf/searches.ini';

// Check for existence of old config files:
$files = array($old_config_input, $old_facets_input, $old_search_input);
foreach ($files as $file) {
    if (!file_exists($file)) {
        die("Error: Cannot open file {$file}.\n");
    }
}

// Check for existence of new config files:
$files = array($new_config_input, $new_facets_input, $new_search_input);
foreach ($files as $file) {
    if (!file_exists($file)) {
        die("Error: Cannot open file {$file}.\n" .
            "Please run this script from the root of your VuFind installation.\n");
    }
}

// Display introductory banner:
?>

#### configuration file upgrade ####

This script will upgrade some of your configuration files in web/conf/. It
reads values from your RC2 files and puts them into the new 1.0 versions.
This is an automated process, so the results may require some manual cleanup.

**** PROCESSING FILES... ****
<?php

fixConfigIni($old_config_input, $new_config_input);
fixFacetsIni($old_facets_input, $new_facets_input);
fixSearchIni($old_search_input, $new_search_input);

// Display parting notes now that we are done:
?>

**** DONE PROCESSING. ****

Please check all of the output files (web/conf/*.new) to make sure you are
happy with the results.

Known issues to watch for:

- Disabled settings may get turned back on.  You will have to comment
  them back out again.
- Comments from your RC2 files will be lost and replaced with the new
  default comments from the 1.0 files -- if you have important
  information embedded there, you will need to merge it by hand.
- Boolean "true" will map to "1" and "false" will map to "".  This
  is functionally equivalent, but you may want to adjust the file
  for readability.

When you have made the necessary corrections, just copy the *.new files
over the equivalent *.ini files (i.e. replace config.ini with config.ini.new).
<?php

/**
 * Process the config.ini file.
 *
 * @param string $old_config_input The old input file
 * @param string $new_config_input The new input file
 *
 * @return void
 */
function fixConfigIni($old_config_input, $new_config_input)
{
    $old_config = parse_ini_file($old_config_input, true);
    $new_config = parse_ini_file($new_config_input, true);
    $new_comments = readIniComments($new_config_input);
    $new_config_file = 'web/conf/config.ini.new';

    // override new version's defaults with matching settings from old version:
    foreach ($old_config as $section => $subsection) {
        foreach ($subsection as $key => $value) {
            $new_config[$section][$key] = $value;
        }
    }

    // patch some values manually -- the [COinS] and [OpenURL] sections have changed
    if (isset($old_config['COinS']['identifier'])) {
        $new_config['OpenURL']['rfr_id'] = $old_config['COinS']['identifier'];
    }
    unset($new_config['COinS']);
    unset($new_config['OpenURL']['api']);
    if (isset($new_config['OpenURL']['url'])) {
        $new_config['OpenURL']['resolver']
            = stristr($new_config['OpenURL']['url'], 'sfx') ? 'sfx' : 'other';
        list($new_config['OpenURL']['url'])
            = explode('?', $new_config['OpenURL']['url']);
    }

    // save the file
    if (!writeIniFile($new_config, $new_comments, $new_config_file)) {
        die("Error: Problem writing to {$new_config_file}.");
    }

    // report success
    echo "\nInput:  {$old_config_input}\n";
    echo "Output: {$new_config_file}\n";
}

/**
 * Process the facets.ini file.
 *
 * @param string $old_facets_input The old input file
 * @param string $new_facets_input The new input file
 *
 * @return void
 */
function fixFacetsIni($old_facets_input, $new_facets_input)
{
    $old_config = parse_ini_file($old_facets_input, true);
    $new_config = parse_ini_file($new_facets_input, true);
    $new_comments = readIniComments($new_facets_input);
    $new_config_file = 'web/conf/facets.ini.new';

    // override new version's defaults with matching settings from old version:
    foreach ($old_config as $section => $subsection) {
        foreach ($subsection as $key => $value) {
            $new_config[$section][$key] = $value;
        }
    }

    // we want to retain the old installation's various facet groups
    // exactly as-is
    $new_config['Results'] = $old_config['Results'];
    $new_config['ResultsTop'] = $old_config['ResultsTop'];
    $new_config['Advanced'] = $old_config['Advanced'];
    $new_config['Author'] = $old_config['Author'];

    // save the file
    if (!writeIniFile($new_config, $new_comments, $new_config_file)) {
        die("Error: Problem writing to {$new_config_file}.");
    }

    // report success
    echo "\nInput:  {$old_facets_input}\n";
    echo "Output: {$new_config_file}\n";
}

/**
 * Process the searches.ini file.
 *
 * @param string $old_search_input The old input file
 * @param string $new_search_input The new input file
 *
 * @return void
 */
function fixSearchIni($old_search_input, $new_search_input)
{
    $old_config = parse_ini_file($old_search_input, true);
    $new_config = parse_ini_file($new_search_input, true);
    $new_comments = readIniComments($new_search_input);
    $new_config_file = 'web/conf/searches.ini.new';

    // override new version's defaults with matching settings from old version:
    foreach ($old_config as $section => $subsection) {
        foreach ($subsection as $key => $value) {
            $new_config[$section][$key] = $value;
        }
    }

    // we want to retain the old installation's Basic/Advanced search settings
    // exactly as-is
    $new_config['Basic_Searches'] = $old_config['Basic_Searches'];
    $new_config['Advanced_Searches'] = $old_config['Advanced_Searches'];

    // save the file
    if (!writeIniFile($new_config, $new_comments, $new_config_file)) {
        die("Error: Problem writing to {$new_config_file}.");
    }

    // report success
    echo "\nInput:  {$old_search_input}\n";
    echo "Output: {$new_config_file}\n";
}

/**
 * support function for writeIniFile -- format a value
 *
 * @param mixed $e Value to format
 *
 * @return string  Value formatted for output to ini file.
 */
function writeIniFileFormatValue($e)
{
    if ($e === true) {
        return 'true';
    } else if ($e === false) {
        return 'false';
    } else if ($e == "") {
        return '';
    } else {
        return '"' . $e . '"';
    }
}

/**
 * support function for writeIniFile -- format a line
 *
 * @param string $key   Configuration key
 * @param mixed  $value Configuration value
 * @param int    $tab   Tab size to help values line up
 *
 * @return string       Formatted line
 */
function writeIniFileFormatLine($key, $value, $tab = 17)
{
    // Build a tab string so the equals signs line up attractively:
    $tabStr = '';
    for ($i = strlen($key); $i < $tab; $i++) {
        $tabStr .= ' ';
    }
    
    return $key . $tabStr . "= ". writeIniFileFormatValue($value);
}

/**
 * write an ini file, adapted from http://php.net/manual/function.parse-ini-file.php
 *
 * @param array  $assoc_arr Array to output
 * @param array  $comments  Comments to inject
 * @param string $path      File to write
 *
 * @return bool             True on success, false on error.
 */
function writeIniFile($assoc_arr, $comments, $path)
{
    $content = "";
    foreach ($assoc_arr as $key=>$elem) {
        if (isset($comments['sections'][$key]['before'])) {
            $content .= $comments['sections'][$key]['before'];
        }
        $content .= "[".$key."]";
        if (!empty($comments['sections'][$key]['inline'])) {
            $content .= "\t" . $comments['sections'][$key]['inline'];
        }
        $content .= "\n";
        foreach ($elem as $key2=>$elem2) {
            if (isset($comments['sections'][$key]['settings'][$key2])) {
                $settingComments = $comments['sections'][$key]['settings'][$key2];
                $content .= $settingComments['before'];
            } else {
                $settingComments = array();
            }
            if (is_array($elem2)) {
                for ($i = 0; $i < count($elem2); $i++) {
                    $content .= 
                        writeIniFileFormatLine($key2 . "[]", $elem2[$i]) . "\n";
                }
            } else {
                $content .= writeIniFileFormatLine($key2, $elem2);
            }
            if (!empty($settingComments['inline'])) {
                $content .= "\t" . $settingComments['inline'];
            }
            $content .= "\n";
        }
    }
    
    $content .= $comments['after'];
    
    if (!$handle = fopen($path, 'w')) {
        return false;
    }
    if (!fwrite($handle, $content)) {
        return false;
    }
    fclose($handle);
    return true;
}

/**
 * readIniComments
 *
 * Read the specified file and return an associative array of this format
 * containing all comments extracted from the file:
 *
 * array =>
 *   'sections' => array
 *     'section_name_1' => array
 *       'before' => string ("Comments found at the beginning of this section")
 *       'inline' => string ("Comments found at the end of the section's line")
 *       'settings' => array
 *         'setting_name_1' => array
 *           'before' => string ("Comments found before this setting")
 *           'inline' => string ("Comments found at the end of the setting's line")
 *           ...
 *         'setting_name_n' => array (same keys as setting_name_1)
 *        ...
 *      'section_name_n' => array (same keys as section_name_1)
 *   'after' => string ("Comments found at the very end of the file")
 *
 * @param string $filename Name of ini file to read.
 *
 * @return array           Associative array as described above.
 */
function readIniComments($filename)
{
    $lines = file($filename);
    
    // Initialize our return value:
    $comments = array('sections' => array(), 'after' => '');
    
    // Initialize variables for tracking status during parsing:
    $currentSection = '';
    $currentSetting = '';
    $currentComments = '';
    
    foreach ($lines as $line) {
        // To avoid redundant processing, create a trimmed version of the current
        // line:
        $trimmed = trim($line);
        
        // Is the current line a comment?  If so, add to the currentComments string.
        // Note that we treat blank lines as comments.
        if (substr($trimmed, 0, 1) == ';' || empty($trimmed)) {
            $currentComments .= $line;
        } else if (substr($trimmed, 0, 1) == '['
            && ($closeBracket = strpos($trimmed, ']')) > 1
        ) {
            // Is the current line the start of a section?  If so, create the
            // appropriate section of the return value:
            $currentSection = substr($trimmed, 1, $closeBracket - 1);
            if (!empty($currentSection)) {
                // Grab comments at the end of the line, if any:
                if (($semicolon = strpos($trimmed, ';')) !== false) {
                    $inline = trim(substr($trimmed, $semicolon));
                } else {
                    $inline = '';
                }
                $comments['sections'][$currentSection] = array(
                    'before' => $currentComments, 
                    'inline' => $inline, 
                    'settings' => array());
                $currentComments = '';
            }
        } else if (($equals = strpos($trimmed, '=')) !== false) {
            // Is the current line a setting?  If so, add to the return value:
            $currentSetting = trim(substr($trimmed, 0, $equals));
            $currentSetting = trim(str_replace('[]', '', $currentSetting));
            if (!empty($currentSection) && !empty($currentSetting)) {
                // Grab comments at the end of the line, if any:
                if (($semicolon = strpos($trimmed, ';')) !== false) {
                    $inline = trim(substr($trimmed, $semicolon));
                } else {
                    $inline = '';
                }
                // Currently, this data structure doesn't support arrays very well,
                // since it can't distinguish which line of the array corresponds
                // with which comments.  For now, we just append all the preceding
                // and inline comments together for arrays.  Since we don't actually
                // use arrays in the config.ini file, this isn't a big concern, but
                // we should improve it if we ever need to.
                if (!isset($comments['sections'][$currentSection]['settings'][$currentSetting])) {
                    $comments['sections'][$currentSection]['settings'][$currentSetting]
                        = array('before' => $currentComments, 'inline' => $inline);
                } else {
                    $comments['sections'][$currentSection]['settings'][$currentSetting]['before'] .= 
                        $currentComments;
                    $comments['sections'][$currentSection]['settings'][$currentSetting]['inline'] .=
                        "\n" . $inline;
                }
                $currentComments = '';
            }
        }
    }
    
    // Store any leftover comments following the last setting:
    $comments['after'] = $currentComments;
    
    return $comments;
}

?>
