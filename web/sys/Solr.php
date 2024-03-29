<?php
/**
 * Solr HTTP Interface
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
 * @package  Support_Classes
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes#index_interface Wiki
 */
require_once 'sys/Proxy_Request.php';
require_once 'sys/IndexEngine.php';
require_once 'sys/ConfigArray.php';
require_once 'sys/SolrUtils.php';

require_once 'services/MyResearch/lib/Change_tracker.php';

require_once 'XML/Unserializer.php';
require_once 'XML/Serializer.php';

/**
 * Solr HTTP Interface
 *
 * @category VuFind
 * @package  Support_Classes
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes#index_interface Wiki
 */
class Solr implements IndexEngine
{
    /**
     * A boolean value determining whether to print debug information
     * @var bool
     */
    public $debug = false;

    /**
     * Whether to Serialize to a PHP Array or not.
     * @var bool
     */
    public $raw = false;

    /**
     * The HTTP_Request object used for REST transactions
     * @var object HTTP_Request
     */
    public $client;

    /**
     * The host to connect to
     * @var string
     */
    public $host;

    /**
     * The core being used on the host
     * @var string
     */
    public $core;

    /**
     * The status of the connection to Solr
     * @var string
     */
    public $status = false;

    /**
     * An array of characters that are illegal in search strings
     */
    private $_illegal = array('!', ':', ';', '[', ']', '{', '}');

    /**
     * The path to the YAML file specifying available search types:
     */
    protected $searchSpecsFile = 'conf/searchspecs.yaml';

    /**
     * An array of search specs pulled from $searchSpecsFile (above)
     */
    private $_searchSpecs = false;

    /**
     * Should boolean operators in the search string be treated as
     * case-insensitive (false), or must they be ALL UPPERCASE (true)?
     */
    private $_caseSensitiveBooleans = true;

    /**
     * Should range operators (i.e. [a TO b]) in the search string be treated as
     * case-insensitive (false), or must they be ALL UPPERCASE (true)?  Note that
     * making this setting case insensitive not only changes the word "TO" to
     * uppercase but also inserts OR clauses to check for case insensitive matches
     * against the edges of the range...  i.e. ([a TO b] OR [A TO B]).
     */
    private $_caseSensitiveRanges = true;

    /**
     * Selected shard settings.
     */
    private $_solrShards = array();
    private $_solrShardsFieldsToStrip = array();

    /**
     * Should we collect highlighting data?
     */
    private $_highlight = false;

    /**
     * How should we cache the search specs?
     */
    private $_specCache = false;

    /**
     * Constructor
     *
     * @param string $host  The URL for the local Solr Server
     * @param string $index The core to use on the specified server
     *
     * @access public
     */
    public function __construct($host, $index = '')
    {
        global $configArray;

        // Set a default Solr index if none is provided to the constructor:
        if (empty($index)) {
            $this->core = isset($configArray['Index']['default_core']) ?
                $configArray['Index']['default_core'] : "biblio";
        } else {
            $this->core = $index;
        }

        $this->host = $host . '/' . $this->core;

        // Test to see solr is online
        $test_url = $this->host . "/admin/ping";
        $test_client = new Proxy_Request();
        $test_client->setMethod(HTTP_REQUEST_METHOD_GET);
        $test_client->setURL($test_url);
        $result = $test_client->sendRequest();
        if (!PEAR::isError($result)) {
            // Even if we get a response, make sure it's a 'good' one.
            if ($test_client->getResponseCode() != 200) {
                PEAR::raiseError('Solr index is offline.');
            }
        } else {
            PEAR::raiseError($result);
        }

        // If we're still processing then solr is online
        $this->client = new Proxy_Request(null, array('useBrackets' => false));

        // Read in preferred boolean/range behavior:
        $searchSettings = getExtraConfigArray('searches');
        if (isset($searchSettings['General']['case_sensitive_bools'])) {
            $this->_caseSensitiveBooleans
                = $searchSettings['General']['case_sensitive_bools'];
        }
        if (isset($searchSettings['General']['case_sensitive_ranges'])) {
            $this->_caseSensitiveRanges
                = $searchSettings['General']['case_sensitive_ranges'];
        }

        // Turn on highlighting if the user has requested highlighting or snippet
        // functionality:
        $highlight = !isset($searchSettings['General']['highlighting'])
            ? false : $searchSettings['General']['highlighting'];
        $snippet = !isset($searchSettings['General']['snippets'])
            ? false : $searchSettings['General']['snippets'];
        if ($highlight || $snippet) {
            $this->_highlight = true;
        }

        // Deal with field-stripping shard settings:
        if (isset($searchSettings['StripFields'])
            && is_array($searchSettings['StripFields'])
        ) {
            $this->_solrShardsFieldsToStrip = $searchSettings['StripFields'];
        }

        // Deal with search spec cache setting:
        if (isset($searchSettings['Cache']['type'])) {
            $this->_specCache = $searchSettings['Cache']['type'];
        }

        // Deal with session-based shard settings:
        if (isset($_SESSION['shards'])) {
            $shards = array();
            foreach ($_SESSION['shards'] as $current) {
                if (isset($configArray['IndexShards'][$current])) {
                    $shards[$current] = $configArray['IndexShards'][$current];
                }
            }
            // if only one shard is used, take its URL as SOLR-Host-URL
            if (count($shards) === 1) {
                $shardsKeys = array_keys($shards);
                $this->host = 'http://'.$shards[$shardsKeys[0]];
            }
            // always set the shards -- even if only one is selected, we may
            // need to filter fields and facets:
            $this->setShards($shards);
        }
    }

    /**
     * Is this object configured with case-sensitive boolean operators?
     *
     * @return boolean
     * @access public
     */
    public function hasCaseSensitiveBooleans()
    {
        return $this->_caseSensitiveBooleans;
    }

    /**
     * Is this object configured with case-sensitive range operators?
     *
     * @return boolean
     * @access public
     */
    public function hasCaseSensitiveRanges()
    {
        return $this->_caseSensitiveRanges;
    }

    /**
     * Support method for _getSearchSpecs() -- load the specs from cache or disk.
     *
     * @return void
     * @access private
     */
    private function _loadSearchSpecs()
    {
        // Turn relative path into absolute path:
        $fullPath = dirname(__FILE__) . '/../' . $this->searchSpecsFile;

        // Check for a local override file:
        $local = str_replace('.yaml', '_local.yaml', $fullPath);
        $local = file_exists($local) ? $local : false;

        // Generate cache key:
        $key = basename($fullPath) . '-' . filemtime($fullPath);
        if ($local) {
            $key .= '-' . basename($local) . '-' . filemtime($local);
        }
        $key = md5($key);

        // Load cache manager:
        $cache = new VuFindCache($this->_specCache, 'searchspecs');

        // Generate data if not found in cache:
        if (!($results = $cache->load($key))) {
            $results = Horde_Yaml::load(file_get_contents($fullPath));
            if ($local) {
                $localResults = Horde_Yaml::load(file_get_contents($local));
                foreach ($localResults as $key => $value) {
                    $results[$key] = $value;
                }
            }
            $cache->save($results, $key);
        }
        $this->_searchSpecs = $results;
    }

    /**
     * Get the search specifications loaded from the specified YAML file.
     *
     * @param string $handler The named search to provide information about (set
     * to null to get all search specifications)
     *
     * @return mixed Search specifications array if available, false if an invalid
     * search is specified.
     * @access  private
     */
    private function _getSearchSpecs($handler = null)
    {
        // Only load specs once:
        if ($this->_searchSpecs === false) {
            $this->_loadSearchSpecs();
        }

        // Special case -- null $handler means we want all search specs.
        if (is_null($handler)) {
            return $this->_searchSpecs;
        }

        // Return specs on the named search if found (easiest, most common case).
        if (isset($this->_searchSpecs[$handler])) {
            return $this->_searchSpecs[$handler];
        }

        // Check for a case-insensitive match -- this provides backward
        // compatibility with different cases used in early VuFind versions
        // and allows greater tolerance of minor typos in config files.
        foreach ($this->_searchSpecs as $name => $specs) {
            if (strcasecmp($name, $handler) == 0) {
                return $specs;
            }
        }

        // If we made it this far, no search specs exist -- return false.
        return false;
    }

    /**
     * Retrieves a document specified by the ID.
     *
     * @param string $id The document to retrieve from Solr
     *
     * @throws object    PEAR Error
     * @return string    The requested resource (or null if bad ID)
     * @access public
     */
    public function getRecord($id)
    {
        if ($this->debug) {
            echo "<pre>Get Record: $id</pre>\n";
        }

        // Query String Parameters
        $options = array('q' => 'id:"' . addcslashes($id, '"') . '"');
        $result = $this->_select('GET', $options);
        if (PEAR::isError($result)) {
            PEAR::raiseError($result);
        }

        return isset($result['response']['docs'][0]) ?
            $result['response']['docs'][0] : null;
    }

    /**
     * Get records similiar to one record
     * Uses MoreLikeThis Request Handler
     *
     * Uses SOLR MLT Query Handler
     *
     * @param string $id     A Solr document ID.
     * @param array  $extras Extra parameters to pass to Solr (optional)
     *
     * @throws object    PEAR Error
     * @return array     An array of query results similar to the specified record
     * @access public
     */
    public function getMoreLikeThis($id, $extras = array())
    {
        // Query String Parameters
        $options = $extras + array(
            'q' => 'id:"' . addcslashes($id, '"') . '"',
            'qt' => 'morelikethis'
        );
        $result = $this->_select('GET', $options);
        if (PEAR::isError($result)) {
            PEAR::raiseError($result);
        }

        return $result;
    }

    /**
     * Get record data based on the provided field and phrase.
     * Used for AJAX suggestions.
     *
     * @param string $phrase The input phrase
     * @param string $field  The field to search on
     * @param int    $limit  The number of results to return
     *
     * @return array         An array of query results
     * @access public
     */
    public function getSuggestion($phrase, $field, $limit)
    {
        if (!strlen($phrase)) {
            return null;
        }

        // Ignore illegal characters
        $phrase = str_replace($this->_illegal, '', $phrase);

        // Process Search
        $query = "$field:($phrase*)";
        $result = $this->search(
            $query, null, null, 0, $limit,
            array('field' => $field, 'limit' => $limit)
        );
        return $result['facet_counts']['facet_fields'][$field];
    }

    /**
     * Get spelling suggestions based on input phrase.
     *
     * @param string $phrase The input phrase
     *
     * @return array         An array of spelling suggestions
     * @access public
     */
    public function checkSpelling($phrase)
    {
        if ($this->debug) {
            echo "<pre>Spell Check: $phrase</pre>\n";
        }

        // Query String Parameters
        $options = array(
            'q'          => $phrase,
            'rows'       => 0,
            'start'      => 1,
            'indent'     => 'yes',
            'spellcheck' => 'true'
        );

        $result = $this->_select($method, $options);
        if (PEAR::isError($result)) {
            PEAR::raiseError($result);
        }

        return $result;
    }

     /**
      * Internal method to build query string from search parameters
      *
      * @param array  $structure The SearchSpecs-derived structure or substructure
      * defining the search, derived from the yaml file
      * @param array  $values    The various values in an array with keys
      * 'onephrase', 'and', 'or' (and perhaps others)
      * @param string $joiner    The operator used to combine generated clauses
      *
      * @throws object           PEAR Error
      * @return string           A search string suitable for adding to a query URL
      * @access private
      */
    private function _applySearchSpecs($structure, $values, $joiner = "OR")
    {
        $clauses = array();
        foreach ($structure as $field => $clausearray) {
            if (is_numeric($field)) {
                // shift off the join string and weight
                $sw = array_shift($clausearray);
                $internalJoin = ' ' . $sw[0] . ' ';
                // Build it up recursively
                $sstring = '(' .
                    $this->_applySearchSpecs($clausearray, $values, $internalJoin) .
                    ')';
                // ...and add a weight if we have one
                $weight = $sw[1];
                if (!is_null($weight) && $weight && $weight > 0) {
                    $sstring .= '^' . $weight;
                }
                // push it onto the stack of clauses
                $clauses[] = $sstring;
            } else if (!$this->_isStripped($field)) {
                // Otherwise, we've got a (list of) [munge, weight] pairs to deal
                // with
                foreach ($clausearray as $spec) {
                    // build a string like title:("one two")
                    $sstring = $field . ':(' . $values[$spec[0]] . ')';
                    // Add the weight if we have one. Yes, I know, it's redundant
                    // code.
                    $weight = $spec[1];
                    if (!is_null($weight) && $weight && $weight > 0) {
                        $sstring .= '^' . $weight;
                    }
                    // ..and push it on the stack of clauses
                    $clauses[] = $sstring;
                }
            }
        }

        // Join it all together
        return implode(' ' . $joiner . ' ', $clauses);
    }

    /**
     * _getStrippedFields -- internal method to read the fields that should get
     * stripped for the used shards from config file
     *
     * @return array An array containing any field that should be stripped from query
     * @access private
     */
    private function _getStrippedFields()
    {
        // Store stripped fields as a static variable so that we only need to
        // process the configuration settings once:
        static $strippedFields = false;
        if ($strippedFields === false) {
            $strippedFields = array();
            foreach ($this->_solrShards as $index => $address) {
                if (array_key_exists($index, $this->_solrShardsFieldsToStrip)) {
                    $parts = explode(',', $this->_solrShardsFieldsToStrip[$index]);
                    foreach ($parts as $part) {
                        $strippedFields[] = trim($part);
                    }
                }
            }
            $strippedFields = array_unique($strippedFields);
        }

        return $strippedFields;
    }

    /**
     * _isStripped -- internal method to check if a field is stripped from query
     *
     * @param string $field The name of the field that should be checked for
     * stripping
     *
     * @return bool         A boolean value indicating whether the field should be
     * stripped (true) or not (false)
     * @access private
     */
    private function _isStripped($field)
    {
        // Never strip fields if shards are disabled.
        // Return true if the current field needs to be stripped.
        if (!empty($this->_solrShards)
            && in_array($field, $this->_getStrippedFields())
        ) {
            return true;
        }
        return false;
    }

    /**
     * Given a field name and search string, return an array containing munged
     * versions of the search string for use in _applySearchSpecs().
     *
     * @param string $field   The YAML search spec field name to search
     * @param string $lookfor The string to search for in the field
     * @param array  $custom  Custom munge settings from YAML search specs
     * @param bool   $basic   Is $lookfor a basic (true) or advanced (false) query?
     *
     * @return  array         Array for use as _applySearchSpecs() values param
     * @access  private
     */
    private function _buildMungeValues($field, $lookfor, $custom = null,
        $basic = true
    ) {
        // Only tokenize basic queries:
        if ($basic) {
            // Tokenize Input
            $tokenized = $this->tokenizeInput($lookfor);

            // Create AND'd and OR'd queries
            $andQuery = implode(' AND ', $tokenized);
            $orQuery = implode(' OR ', $tokenized);

            // Build possible inputs for searching:
            $values = array();
            $values['onephrase']
                = '"' . str_replace('"', '', implode(' ', $tokenized)) . '"';
            $values['and'] = $andQuery;
            $values['or'] = $orQuery;
        } else {
            // If we're skipping tokenization, we just want to pass $lookfor through
            // unmodified (it's probably an advanced search that won't benefit from
            // tokenization).  We'll just set all possible values to the same thing,
            // except that we'll try to do the "one phrase" in quotes if possible.
            // IMPORTANT: If we detect a boolean NOT, we MUST omit the quotes.
            $onephrase = (strstr($lookfor, '"') || strstr($lookfor, ' NOT '))
                ? $lookfor : '"' . $lookfor . '"';
            $values = array(
                'onephrase' => $onephrase, 'and' => $lookfor, 'or' => $lookfor
            );
        }

        // Apply custom munge operations if necessary:
        if (is_array($custom)) {
            foreach ($custom as $mungeName => $mungeOps) {
                $values[$mungeName] = $lookfor;

                // Skip munging of advanced queries:
                if ($basic) {
                    foreach ($mungeOps as $operation) {
                        switch($operation[0]) {
                        case 'append':
                            $values[$mungeName] .= $operation[1];
                            break;
                        case 'lowercase':
                            $values[$mungeName] = strtolower($values[$mungeName]);
                            break;
                        case 'preg_replace':
                            $values[$mungeName] = preg_replace(
                                $operation[1], $operation[2], $values[$mungeName]
                            );
                            break;
                        case 'uppercase':
                            $values[$mungeName] = strtoupper($values[$mungeName]);
                            break;
                        }
                    }
                }
            }
        }

        return $values;
    }

    /**
     * Given a field name and search string, expand this into the necessary Lucene
     * query to perform the specified search on the specified field(s).
     *
     * @param string $field   The YAML search spec field name to search
     * @param string $lookfor The string to search for in the field
     * @param bool   $basic   Is $lookfor a basic (true) or advanced (false) query?
     *
     * @return string         The query
     * @access private
     */
    private function _buildQueryComponent($field, $lookfor, $basic = true)
    {
        // Load the YAML search specifications:
        $ss = $this->_getSearchSpecs($field);

        // If we received a field spec that wasn't defined in the YAML file,
        // let's try simply passing it along to Solr.
        if ($ss === false) {
            return $field . ':(' . $lookfor . ')';
        }

        // If this is a basic query and we have Dismax settings, let's build
        // a Dismax subquery to avoid some of the ugly side effects of our Lucene
        // query generation logic.
        if ($basic && isset($ss['DismaxFields'])) {
            $qf = implode(' ', $ss['DismaxFields']);
            $dmParams = '';
            if (isset($ss['DismaxParams']) && is_array($ss['DismaxParams'])) {
                foreach ($ss['DismaxParams'] as $current) {
                    $dmParams .= ' ' . $current[0] . "='" .
                        addcslashes($current[1], "'") . "'";
                }
            }
            $dismaxQuery = '{!dismax qf="' . $qf . '"' . $dmParams . '}' . $lookfor;
            $baseQuery = '_query_:"' . addslashes($dismaxQuery) . '"';
        } else {
            // Munge the user query in a few different ways:
            $customMunge = isset($ss['CustomMunge']) ? $ss['CustomMunge'] : null;
            $values
                = $this->_buildMungeValues($field, $lookfor, $customMunge, $basic);

            // Apply the $searchSpecs property to the data:
            $baseQuery = $this->_applySearchSpecs($ss['QueryFields'], $values);
        }

        // Apply filter query if applicable:
        if (isset($ss['FilterQuery'])) {
            return "({$baseQuery}) AND ({$ss['FilterQuery']})";
        }

        return "($baseQuery)";
    }

    /**
     * Given a field name and search string known to contain advanced features
     * (as identified by isAdvanced()), expand this into the necessary Lucene
     * query to perform the specified search on the specified field(s).
     *
     * @param string $handler The YAML search spec field name to search
     * @param string $query   The string to search for in the field
     *
     * @return  string        The query
     * @access  private
     */
    private function _buildAdvancedQuery($handler, $query)
    {
        $query = $this->_buildAdvancedInnerQuery($handler, $query);

        // Apply boost query/boost function, if any:
        $ss = $this->_getSearchSpecs($handler);
        $bq = array();
        if (isset($ss['DismaxParams']) && is_array($ss['DismaxParams'])) {
            foreach ($ss['DismaxParams'] as $current) {
                if ($current[0] == 'bq') {
                    $bq[] = $current[1];
                } else if ($current[0] == 'bf') {
                    // BF parameter may contain multiple space-separated functions
                    // with individual boosts.  We need to parse this into _val_
                    // query components:
                    $bfParts = explode(' ', $current[1]);
                    foreach ($bfParts as $bf) {
                        $bf = trim($bf);
                        if (!empty($bf)) {
                            $bfSubParts = explode('^', $bf, 2);
                            $boost = '"' . addcslashes($bfSubParts[0], '"') . '"';
                            if (isset($bfSubParts[1])) {
                                $boost .= '^' . $bfSubParts[1];
                            }
                            $bq[] = '_val_:' . $boost;
                        }
                    }
                }
            }
        }

        if (!empty($bq)) {
            $query = '(' . $query . ') AND (*:* OR ' . implode(' OR ', $bq) . ')';
        }

        return $query;
    }

    /**
     * Support method for _buildAdvancedQuery -- build the inner portion of the
     * query; the calling method may then wrap this with additional settings.
     *
     * @param string $handler The YAML search spec field name to search
     * @param string $query   The string to search for in the field
     *
     * @return  string        The query
     * @access  private
     */
    private function _buildAdvancedInnerQuery($handler, $query)
    {
        // Special case -- if the user wants all records but the current handler
        // has a filter query, apply the filter query:
        if (trim($query) == '*:*') {
            $ss = $this->_getSearchSpecs($handler);
            if (isset($ss['FilterQuery'])) {
                return $ss['FilterQuery'];
            }
        }

        // Strip out any colons that are NOT part of a field specification:
        $query = preg_replace('/(\:\s+|\s+:)/', ' ', $query);

        // If the query already includes field specifications, we can't easily
        // apply it to other fields through our defined handlers, so we'll leave
        // it as-is:
        if (strstr($query, ':')) {
            return $query;
        }

        // Convert empty queries to return all values in a field:
        if (empty($query)) {
            $query = '[* TO *]';
        }

        // If the query ends in a question mark, the user may not really intend to
        // use the question mark as a wildcard -- let's account for that possibility
        if (substr($query, -1) == '?') {
            $query = "({$query}) OR (" . substr($query, 0, strlen($query) - 1) . ")";
        }

        // We're now ready to use the regular YAML query handler but with the
        // $basic parameter set to false so that we leave the advanced query
        // features unmolested.
        return $this->_buildQueryComponent($handler, $query, false);
    }

    /**
     * Build Query string from search parameters
     *
     * @param array $search An array of search parameters
     *
     * @throws object       PEAR Error
     * @return string       The query
     * @access public
     */
    public function buildQuery($search)
    {
        $groups   = array();
        $excludes = array();
        if (is_array($search)) {
            $query = '';

            foreach ($search as $params) {

                // Advanced Search
                if (isset($params['group'])) {
                    $thisGroup = array();
                    // Process each search group
                    foreach ($params['group'] as $group) {
                        // Build this group individually as a basic search
                        $thisGroup[] = $this->buildQuery(array($group));
                    }
                    // Is this an exclusion (NOT) group or a normal group?
                    if ($params['group'][0]['bool'] == 'NOT') {
                        $excludes[] = join(" OR ", $thisGroup);
                    } else {
                        $groups[] = join(
                            " " . $params['group'][0]['bool'] . " ", $thisGroup
                        );
                    }
                }

                // Basic Search
                if (isset($params['lookfor']) && $params['lookfor'] != '') {
                    // Clean and validate input
                    $lookfor = $this->validateInput($params['lookfor']);

                    // Force boolean operators to uppercase if we are in a
                    // case-insensitive mode:
                    if (!$this->_caseSensitiveBooleans) {
                        $lookfor = VuFindSolrUtils::capitalizeBooleans($lookfor);
                    }
                    // Adjust range operators if we are in a case-insensitive mode:
                    if (!$this->_caseSensitiveRanges) {
                        $lookfor = VuFindSolrUtils::capitalizeRanges($lookfor);
                    }

                    if (isset($params['field']) && ($params['field'] != '')) {
                        if ($this->isAdvanced($lookfor)) {
                            $query .= $this->_buildAdvancedQuery(
                                $params['field'], $lookfor
                            );
                        } else {
                            $query .= $this->_buildQueryComponent(
                                $params['field'], $lookfor
                            );
                        }
                    } else {
                        $query .= $lookfor;
                    }
                }
            }
        }

        // Put our advanced search together
        if (count($groups) > 0) {
            $query = "(" . join(") " . $search[0]['join'] . " (", $groups) . ")";
        }
        // and concatenate exclusion after that
        if (count($excludes) > 0) {
            $query .= " NOT ((" . join(") OR (", $excludes) . "))";
        }

        // Ensure we have a valid query to this point
        if (!isset($query) || $query  == '') {
            $query = '*:*';
        }

        return $query;
    }

    /**
     * Normalize a sort option.
     *
     * @param string $sort The sort option.
     *
     * @return string      The normalized sort value.
     * @access private
     */
    private function _normalizeSort($sort)
    {
        // Break apart sort into field name and sort direction (note error
        // suppression to prevent notice when direction is left blank):
        @list($sortField, $sortDirection) = explode(' ', $sort);

        // Default sort order (may be overridden by switch below):
        $defaultSortDirection = 'asc';

        // Translate special sort values into appropriate Solr fields:
        switch ($sortField) {
        case 'year':
        case 'publishDate':
            $sortField = 'publishDateSort';
            $defaultSortDirection = 'desc';
            break;
        case 'author':
            $sortField = 'authorStr';
            break;
        case 'title':
            $sortField = 'title_sort';
            break;
        }

        // Normalize sort direction to either "asc" or "desc":
        $sortDirection = strtolower(trim($sortDirection));
        if ($sortDirection != 'desc' && $sortDirection != 'asc') {
            $sortDirection = $defaultSortDirection;
        }

        return $sortField . ' ' . $sortDirection;
    }

    /**
     * Execute a search.
     *
     * @param string $query           The search query
     * @param string $handler         The Query Handler to use (null for default)
     * @param array  $filter          The fields and values to filter results on
     * @param string $start           The record to start with
     * @param string $limit           The amount of records to return
     * @param array  $facet           An array of faceting options
     * @param string $spell           Phrase to spell check
     * @param string $dictionary      Spell check dictionary to use
     * @param string $sort            Field name to use for sorting
     * @param string $fields          A list of fields to be returned
     * @param string $method          Method to use for sending request (GET/POST)
     * @param bool   $returnSolrError Fail outright on syntax error (false) or
     * treat it as an empty result set with an error key set (true)?
     *
     * @throws object                 PEAR Error
     * @return array                  An array of query results
     * @access public
     */
    public function search($query, $handler = null, $filter = null, $start = 0,
        $limit = 20, $facet = null, $spell = '', $dictionary = null,
        $sort = null, $fields = null,
        $method = HTTP_REQUEST_METHOD_POST, $returnSolrError = false
    ) {
        // Query String Parameters
        $options = array(
            'q' => $query, 'rows' => $limit, 'start' => $start, 'indent' => 'yes'
        );

        // Add Sorting
        if ($sort && !empty($sort)) {
            // There may be multiple sort options (ranked, with tie-breakers);
            // process each individually, then assemble them back together again:
            $sortParts = explode(',', $sort);
            for ($x = 0; $x < count($sortParts); $x++) {
                $sortParts[$x] = $this->_normalizeSort($sortParts[$x]);
            }
            $options['sort'] = implode(',', $sortParts);
        }

        // Determine which handler to use
        if (!$this->isAdvanced($query)) {
            $ss = is_null($handler) ? null : $this->_getSearchSpecs($handler);
            // Is this a Dismax search?
            if (isset($ss['DismaxFields'])) {
                // Specify the fields to do a Dismax search on:
                $options['qf'] = implode(' ', $ss['DismaxFields']);

                // Specify the default dismax search handler so we can use any
                // global settings defined by the user:
                $options['qt'] = 'dismax';

                // Load any custom Dismax parameters from the YAML search spec file:
                if (isset($ss['DismaxParams']) && is_array($ss['DismaxParams'])) {
                    foreach ($ss['DismaxParams'] as $current) {
                        // The way we process the current parameter depends on
                        // whether or not we have previously encountered it.  If
                        // we have multiple values for the same parameter, we need
                        // to turn its entry in the $options array into a subarray;
                        // otherwise, one-off parameters can be safely represented
                        // as single values.
                        if (isset($options[$current[0]])) {
                            if (!is_array($options[$current[0]])) {
                                $options[$current[0]]
                                    = array($options[$current[0]]);
                            }
                            $options[$current[0]][] = $current[1];
                        } else {
                            $options[$current[0]] = $current[1];
                        }
                    }
                }

                // Apply search-specific filters if necessary:
                if (isset($ss['FilterQuery'])) {
                    if (is_array($filter)) {
                        $filter[] = $ss['FilterQuery'];
                    } else {
                        $filter = array($ss['FilterQuery']);
                    }
                }
            } else {
                // Not DisMax... but if we have a handler set, we may still need
                // to build a query using a setting in the YAML search specs or a
                // simple field name:
                if (!empty($handler)) {
                    $options['q'] = $this->_buildQueryComponent($handler, $query);
                }
            }
        } else {
            // Force boolean operators to uppercase if we are in a case-insensitive
            // mode:
            if (!$this->_caseSensitiveBooleans) {
                $query = VuFindSolrUtils::capitalizeBooleans($query);
            }
            // Adjust range operators if we are in a case-insensitive mode:
            if (!$this->_caseSensitiveRanges) {
                $query = VuFindSolrUtils::capitalizeRanges($query);
            }

            // Process advanced search -- if a handler was specified, let's see
            // if we can adapt the search to work with the appropriate fields.
            if (!empty($handler)) {
                $options['q'] = $this->_buildAdvancedQuery($handler, $query);
                // If highlighting is enabled, we only want to use the inner query
                // for highlighting; anything added outside of this is a boost and
                // should be ignored for highlighting purposes!
                if ($this->_highlight) {
                    $options['hl.q']
                        = $this->_buildAdvancedInnerQuery($handler, $query);
                }
            }
        }

        // Limit Fields
        if ($fields) {
            $options['fl'] = $fields;
        } else {
            // This should be an explicit list
            $options['fl'] = '*,score';
        }

        // Build Facet Options
        if ($facet && !empty($facet['field'])) {
            $options['facet'] = 'true';
            $options['facet.mincount'] = 1;
            $options['facet.limit']
                = (isset($facet['limit'])) ? $facet['limit'] : null;
            unset($facet['limit']);
            $options['facet.field']
                = (isset($facet['field'])) ? $facet['field'] : null;
            unset($facet['field']);
            $options['facet.prefix']
                = (isset($facet['prefix'])) ? $facet['prefix'] : null;
            unset($facet['prefix']);
            $options['facet.sort']
                = (isset($facet['sort'])) ? $facet['sort'] : null;
            unset($facet['sort']);
            if (isset($facet['offset'])) {
                $options['facet.offset'] = $facet['offset'];
                unset($facet['offset']);
            }
            foreach ($facet as $param => $value) {
                $options[$param] = $value;
            }
        }

        // Build Filter Query
        if (is_array($filter) && count($filter)) {
            $options['fq'] = $filter;
        }

        // Enable Spell Checking
        if ($spell != '') {
            $options['spellcheck'] = 'true';
            $options['spellcheck.q'] = $spell;
            if ($dictionary != null) {
                $options['spellcheck.dictionary'] = $dictionary;
            }
        }

        // Enable highlighting
        if ($this->_highlight) {
            $options['hl'] = 'true';
            $options['hl.fl'] = '*';
            $options['hl.simple.pre'] = '{{{{START_HILITE}}}}';
            $options['hl.simple.post'] = '{{{{END_HILITE}}}}';
        }

        if ($this->debug) {
            echo '<pre>Search options: ' . print_r($options, true) . "\n";

            if ($filter) {
                echo "\nFilterQuery: ";
                foreach ($filter as $filterItem) {
                    echo " $filterItem";
                }
            }

            if ($sort) {
                echo "\nSort: " . $options['sort'];
            }

            echo "</pre>\n";
        }

        $result = $this->_select($method, $options, $returnSolrError);
        if (PEAR::isError($result)) {
            PEAR::raiseError($result);
        }

        return $result;
    }

    /**
     * Convert an array of fields into XML for saving to Solr.
     *
     * @param array $fields Array of fields to save
     *
     * @return string       XML document ready for posting to Solr.
     * @access public
     */
    public function getSaveXML($fields)
    {
        // Create XML Document
        $doc = new DOMDocument('1.0', 'UTF-8');

        // Create add node
        $node = $doc->createElement('add');
        $addNode = $doc->appendChild($node);

        // Create doc node
        $node = $doc->createElement('doc');
        $docNode = $addNode->appendChild($node);

        // Add fields to XML docuemnt
        foreach ($fields as $field => $value) {
            // Normalize current value to an array for convenience:
            if (!is_array($value)) {
                $value = array($value);
            }
            // Add all non-empty values of the current field to the XML:
            foreach ($value as $current) {
                if ($current != '') {
                    $node = $doc->createElement(
                        'field', htmlspecialchars($current, ENT_COMPAT, 'UTF-8')
                    );
                    $node->setAttribute('name', $field);
                    $docNode->appendChild($node);
                }
            }
        }

        return $doc->saveXML();
    }

    /**
     * Save Record to Database
     *
     * @param string $xml XML document to post to Solr
     *
     * @return mixed      Boolean true on success or PEAR_Error
     * @access public
     */
    public function saveRecord($xml)
    {
        if ($this->debug) {
            echo "<pre>Add Record</pre>\n";
        }

        $result = $this->_update($xml);
        if (PEAR::isError($result)) {
            PEAR::raiseError($result);
        }

        return $result;
    }

    /**
     * Delete Record from Database
     *
     * @param string $id ID for record to delete
     *
     * @return boolean
     * @access public
     */
    public function deleteRecord($id)
    {
        // Treat single-record deletion as a special case of multi-record deletion:
        return $this->deleteRecords(array($id));
    }

    /**
     * Delete Record from Database
     *
     * @param string $idList Array of IDs for record to delete
     *
     * @return boolean
     * @access public
     */
    public function deleteRecords($idList)
    {
        if ($this->debug) {
            echo "<pre>Delete Record List</pre>\n";
        }

        // Build the delete XML
        $body = '<delete>';
        foreach ($idList as $id) {
            $body .= '<id>' . htmlspecialchars($id) . '</id>';
        }
        $body .= '</delete>';

        // Attempt to post the XML:
        $result = $this->_update($body);
        if (PEAR::isError($result)) {
            PEAR::raiseError($result);
        }

        // Record the deletions in our change tracker database:
        foreach ($idList as $id) {
            $tracker = new Change_tracker();
            $tracker->markDeleted($this->core, $id);
        }

        return $result;
    }

    /**
     * Commit
     *
     * @return string
     * @access public
     */
    public function commit()
    {
        if ($this->debug) {
            echo "<pre>Commit</pre>\n";
        }

        $body = '<commit/>';

        $result = $this->_update($body);
        if (PEAR::isError($result)) {
            PEAR::raiseError($result);
        }

        return $result;
    }

    /**
     * Optimize
     *
     * @return string
     * @access public
     */
    public function optimize()
    {
        if ($this->debug) {
            echo "<pre>Optimize</pre>\n";
        }

        $body = '<optimize/>';

        $result = $this->_update($body);
        if (PEAR::isError($result)) {
            PEAR::raiseError($result);
        }

        return $result;
    }

    /**
     * Set the shards for distributed search
     *
     * @param array $shards Name => URL array of shards
     *
     * @return void
     * @access public
     */
    public function setShards($shards)
    {
        $this->_solrShards = $shards;
    }
    
    /**
     * Submit REST Request to write data (protected wrapper to allow child classes
     * to use this mechanism -- we should eventually phase out private _update).
     *
     * @param string $xml The command to execute
     *
     * @return mixed      Boolean true on success or PEAR_Error
     * @access protected
     */
    protected function update($xml)
    {
        return $this->_update($xml);
    }

    /**
     * Strip facet settings that are illegal due to shard settings.
     *
     * @param array $value Current facet.field setting
     *
     * @return array       Filtered facet.field setting
     * @access private
     */
    private function _stripUnwantedFacets($value)
    {
        // Load the configuration of facets to strip and build a list of the ones
        // that currently apply:
        $facetConfig = getExtraConfigArray('facets');
        $badFacets = array();
        if (!empty($this->_solrShards) && is_array($this->_solrShards)
            && isset($facetConfig['StripFacets'])
            && is_array($facetConfig['StripFacets'])
        ) {
            $shardNames = array_keys($this->_solrShards);
            foreach ($facetConfig['StripFacets'] as $indexName => $facets) {
                if (in_array($indexName, $shardNames) === true) {
                    $badFacets = array_merge($badFacets, explode(",", $facets));
                }
            }
        }

        // No bad facets means no filtering necessary:
        if (empty($badFacets)) {
            return $value;
        }

        // Ensure that $value is an array:
        if (!is_array($value)) {
            $value = array($value);
        }

        // Rebuild the $value array, excluding all unwanted facets:
        $newValue = array();
        foreach ($value as $current) {
            if (!in_array($current, $badFacets)) {
                $newValue[] = $current;
            }
        }

        return $newValue;
    }

    /**
     * Submit REST Request to read data
     *
     * @param string $method          HTTP Method to use: GET, POST,
     * @param array  $params          Array of parameters for the request
     * @param bool   $returnSolrError Should we fail outright on syntax error
     * (false) or treat it as an empty result set with an error key set (true)?
     *
     * @return array                  The Solr response (or a PEAR error)
     * @access private
     */
    private function _select($method = HTTP_REQUEST_METHOD_GET, $params = array(),
        $returnSolrError = false
    ) {
        $this->client->setMethod($method);
        $this->client->setURL($this->host . "/select/");

        $params['wt'] = 'json';
        $params['json.nl'] = 'arrarr';

        // Build query string for use with GET or POST:
        $query = array();
        if ($params) {
            foreach ($params as $function => $value) {
                if ($function != '') {
                    // Strip custom FacetFields when sharding makes it necessary:
                    if ($function === 'facet.field') {
                        $value = $this->_stripUnwantedFacets($value);

                        // If we stripped all values, skip the parameter:
                        if (empty($value)) {
                            continue;
                        }
                    }
                    if (is_array($value)) {
                        foreach ($value as $additional) {
                            $additional = urlencode($additional);
                            $query[] = "$function=$additional";
                        }
                    } else {
                        $value = urlencode($value);
                        $query[] = "$function=$value";
                    }
                }
            }
        }

        // pass the shard parameter along to Solr if necessary; if the shard
        // count is 0, shards are disabled; if the count is 1, only one shard
        // is selected so the host has already been adjusted:
        if (is_array($this->_solrShards) && count($this->_solrShards) > 1) {
            $query[] = 'shards=' . urlencode(implode(',', $this->_solrShards));
        }
        $queryString = implode('&', $query);

        if ($this->debug) {
            echo "<pre>$method: ";
            print_r($this->host . "/select/?" . $queryString);
            echo "</pre>\n";
        }

        if ($method == 'GET') {
            $this->client->addRawQueryString($queryString);
        } elseif ($method == 'POST') {
            $this->client->setBody($queryString);
        }

        // Send Request
        $result = $this->client->sendRequest();
        $this->client->clearPostData();

        if (!PEAR::isError($result)) {
            return $this->_process(
                $this->client->getResponseBody(), $returnSolrError
            );
        } else {
            return $result;
        }
    }

    /**
     * Submit REST Request to write data
     *
     * @param string $xml The command to execute
     *
     * @return mixed      Boolean true on success or PEAR_Error
     * @access private
     */
    private function _update($xml)
    {
        $this->client->setMethod('POST');
        $this->client->setURL($this->host . "/update/");

        if ($this->debug) {
            echo "<pre>POST: ";
            print_r($this->host . "/update/");
            echo "XML:\n";
            print_r($xml);
            echo "</pre>\n";
        }

        // Set up XML
        $this->client->addHeader('Content-Type', 'text/xml; charset=utf-8');
        $this->client->addHeader('Content-Length', strlen($xml));
        $this->client->setBody($xml);

        // Send Request
        $result = $this->client->sendRequest();
        $responseCode = $this->client->getResponseCode();
        $this->client->clearPostData();

        if (in_array($responseCode, array(400, 500))) {
            $detail = $this->client->getResponseBody();
            // Attempt to extract the most useful error message from the response:
            if (preg_match("/<title>(.*)<\/title>/msi", $detail, $matches)) {
                $errorMsg = $matches[1];
            } else {
                $errorMsg = $detail;
            }
            return new PEAR_Error("Unexpected response -- " . $errorMsg);
        }

        if (!PEAR::isError($result)) {
            return true;
        } else {
            return $result;
        }
    }

    /**
     * Perform normalization and analysis of Solr return value.
     *
     * @param array $result          The raw response from Solr
     * @param bool  $returnSolrError Should we fail outright on syntax error
     * (false) or treat it as an empty result set with an error key set (true)?
     *
     * @return array                 The processed response from Solr
     * @access private
     */
    private function _process($result, $returnSolrError = false)
    {
        // Catch errors from SOLR
        if (substr(trim($result), 0, 2) == '<h') {
            $errorMsg = substr($result, strpos($result, '<pre>'));
            $errorMsg = substr(
                $errorMsg, strlen('<pre>'), strpos($result, "</pre>")
            );
            if ($returnSolrError) {
                return array(
                    'response' => array('numfound' => 0, 'docs' => array()),
                    'error' => $errorMsg
                );
            } else {
                $msg = 'Unable to process query<br />Solr Returned: ' . $errorMsg;
                PEAR::raiseError(new PEAR_Error($msg));
            }
        }
        $result = json_decode($result, true);

        // Inject highlighting details into results if necessary:
        if (isset($result['highlighting'])) {
            foreach ($result['response']['docs'] as $key => $current) {
                if (isset($result['highlighting'][$current['id']])) {
                    $result['response']['docs'][$key]['_highlighting']
                        = $result['highlighting'][$current['id']];
                }
            }
            // Remove highlighting section now that we have copied its contents:
            unset($result['highlighting']);
        }

        return $result;
    }

    /**
     * Input Tokenizer
     *
     * Tokenizes the user input based on spaces and quotes.  Then joins phrases
     * together that have an AND, OR, NOT present.
     *
     * @param string $input User's input string
     *
     * @return array        Tokenized array
     * @access public
     */
    public function tokenizeInput($input)
    {
        // Tokenize on spaces and quotes
        //preg_match_all('/"[^"]*"|[^ ]+/', $input, $words);
        preg_match_all('/"[^"]*"[~[0-9]+]*|"[^"]*"|[^ ]+/', $input, $words);
        $words = $words[0];

        // Join words with AND, OR, NOT
        $newWords = array();
        for ($i=0; $i<count($words); $i++) {
            if (($words[$i] == 'OR') || ($words[$i] == 'AND')
                || ($words[$i] == 'NOT')
            ) {
                if (count($newWords)) {
                    $newWords[count($newWords)-1] .= ' ' . $words[$i] . ' ' .
                        $words[$i+1];
                    $i = $i+1;
                }
            } else {
                $newWords[] = $words[$i];
            }
        }

        return $newWords;
    }

    /**
     * Input Validater
     *
     * Cleans the input based on the Lucene Syntax rules.
     *
     * @param string $input User's input string
     *
     * @return bool         Fixed input
     * @access public
     */
    public function validateInput($input)
    {
        // Normalize fancy quotes:
        $quotes = array(
            "\xC2\xAB"     => '"', // « (U+00AB) in UTF-8
            "\xC2\xBB"     => '"', // » (U+00BB) in UTF-8
            "\xE2\x80\x98" => "'", // ‘ (U+2018) in UTF-8
            "\xE2\x80\x99" => "'", // ’ (U+2019) in UTF-8
            "\xE2\x80\x9A" => "'", // ‚ (U+201A) in UTF-8
            "\xE2\x80\x9B" => "'", // ? (U+201B) in UTF-8
            "\xE2\x80\x9C" => '"', // “ (U+201C) in UTF-8
            "\xE2\x80\x9D" => '"', // ” (U+201D) in UTF-8
            "\xE2\x80\x9E" => '"', // „ (U+201E) in UTF-8
            "\xE2\x80\x9F" => '"', // ? (U+201F) in UTF-8
            "\xE2\x80\xB9" => "'", // ‹ (U+2039) in UTF-8
            "\xE2\x80\xBA" => "'", // › (U+203A) in UTF-8
        );
        $input = strtr($input, $quotes);

        // If the user has entered a lone BOOLEAN operator, convert it to lowercase
        // so it is treated as a word (otherwise it will trigger a fatal error):
        switch(trim($input)) {
        case 'OR':
            return 'or';
        case 'AND':
            return 'and';
        case 'NOT':
            return 'not';
        }

        // If the string consists only of control characters and/or BOOLEANs with no
        // other input, wipe it out entirely to prevent weird errors:
        $operators = array('AND', 'OR', 'NOT', '+', '-', '"', '&', '|');
        if (trim(str_replace($operators, '', $input)) == '') {
            return '';
        }

        // Translate "all records" search into a blank string
        if (trim($input) == '*:*') {
            return '';
        }

        // Ensure wildcards are not at beginning of input
        if ((substr($input, 0, 1) == '*') || (substr($input, 0, 1) == '?')) {
            $input = substr($input, 1);
        }

        // Ensure all parens match
        $start = preg_match_all('/\(/', $input, $tmp);
        $end = preg_match_all('/\)/', $input, $tmp);
        if ($start != $end) {
            $input = str_replace(array('(', ')'), '', $input);
        }

        // Ensure ^ is used properly
        $cnt = preg_match_all('/\^/', $input, $tmp);
        $matches = preg_match_all('/.+\^[0-9]/', $input, $tmp);
        if (($cnt) && ($cnt !== $matches)) {
            $input = str_replace('^', '', $input);
        }

        // Remove unwanted brackets/braces that are not part of range queries.
        // This is a bit of a shell game -- first we replace valid brackets and
        // braces with tokens that cannot possibly already be in the query (due
        // to ^ normalization in the step above).  Next, we remove all remaining
        // invalid brackets/braces, and transform our tokens back into valid ones.
        // Obviously, the order of the patterns/merges array is critically
        // important to get this right!!
        $patterns = array(
            // STEP 1 -- escape valid brackets/braces
            '/\[([^\[\]\s]+\s+TO\s+[^\[\]\s]+)\]/' .
            ($this->_caseSensitiveRanges ? '' : 'i'),
            '/\{([^\{\}\s]+\s+TO\s+[^\{\}\s]+)\}/' .
            ($this->_caseSensitiveRanges ? '' : 'i'),
            // STEP 2 -- destroy remaining brackets/braces
            '/[\[\]\{\}]/',
            // STEP 3 -- unescape valid brackets/braces
            '/\^\^lbrack\^\^/', '/\^\^rbrack\^\^/',
            '/\^\^lbrace\^\^/', '/\^\^rbrace\^\^/');
        $matches = array(
            // STEP 1 -- escape valid brackets/braces
            '^^lbrack^^$1^^rbrack^^', '^^lbrace^^$1^^rbrace^^',
            // STEP 2 -- destroy remaining brackets/braces
            '',
            // STEP 3 -- unescape valid brackets/braces
            '[', ']', '{', '}');
        $input = preg_replace($patterns, $matches, $input);
        return $input;
    }

    /**
     * Does the provided query use advanced Lucene syntax features?
     *
     * @param string $query Query to test.
     *
     * @return bool
     * @access public
     */
    public function isAdvanced($query)
    {
        // Check for various conditions that flag an advanced Lucene query:
        if ($query == '*:*') {
            return true;
        }

        // The following conditions do not apply to text inside quoted strings,
        // so let's just strip all quoted strings out of the query to simplify
        // detection.  We'll replace quoted phrases with a dummy keyword so quote
        // removal doesn't interfere with the field specifier check below.
        $query = preg_replace('/"[^"]*"/', 'quoted', $query);

        // Check for field specifiers:
        if (preg_match("/[^\s]\:[^\s]/", $query)) {
            return true;
        }

        // Check for parentheses and range operators:
        if (strstr($query, '(') && strstr($query, ')')) {
            return true;
        }
        $rangeReg = '/(\[.+\s+TO\s+.+\])|(\{.+\s+TO\s+.+\})/';
        if (!$this->_caseSensitiveRanges) {
            $rangeReg .= "i";
        }
        if (preg_match($rangeReg, $query)) {
            return true;
        }

        // Build a regular expression to detect booleans -- AND/OR/NOT surrounded
        // by whitespace, or NOT leading the query and followed by whitespace.
        $boolReg = '/((\s+(AND|OR|NOT)\s+)|^NOT\s+)/';
        if (!$this->_caseSensitiveBooleans) {
            $boolReg .= "i";
        }
        if (preg_match($boolReg, $query)) {
            return true;
        }

        // Check for wildcards and fuzzy matches:
        if (strstr($query, '*') || strstr($query, '?') || strstr($query, '~')) {
            return true;
        }

        // Check for boosts:
        if (preg_match('/[\^][0-9]+/', $query)) {
            return true;
        }

        return false;
    }

    /**
     * Remove illegal characters from the provided query.
     *
     * @param string $query Query to clean.
     *
     * @return string       Clean query.
     * @access public
     */
    public function cleanInput($query)
    {
        $query = trim(str_replace($this->_illegal, '', $query));
        $query = strtolower($query);

        return $query;
    }

    /**
     * Obtain information from an alphabetic browse index.
     *
     * @param string $source          Name of index to search
     * @param string $from            Starting point for browse results
     * @param int    $page            Result page to return (starts at 0)
     * @param int    $page_size       Number of results to return on each page
     * @param bool   $returnSolrError Should we fail outright on syntax error
     * (false) or treat it as an empty result set with an error key set (true)?
     *
     * @return array
     * @access public
     */
    public function alphabeticBrowse($source, $from, $page, $page_size = 20,
        $returnSolrError = false
    ) {
        $this->client->setMethod('GET');
        $this->client->setURL($this->host . "/browse");

        $offset = $page * $page_size;

        $this->client->addQueryString('from', $from);
        $this->client->addQueryString('json.nl', 'arrarr');
        $this->client->addQueryString('offset', $offset);
        $this->client->addQueryString('rows', $page_size);
        $this->client->addQueryString('source', $source);
        $this->client->addQueryString('wt', 'json');

        $result = $this->client->sendRequest();

        if (!PEAR::isError($result)) {
            return $this->_process(
                $this->client->getResponseBody(), $returnSolrError
            );
        } else {
            return $result;
        }
    }

    /**
     * Convert a terms array (where every even entry is a term and every odd entry
     * is a count) into an associate array of terms => counts.
     *
     * @param array $in Input array
     *
     * @return array    Processed array
     * @access private
     */
    private function _processTerms($in)
    {
        $out = array();

        for ($i = 0; $i < count($in); $i += 2) {
            $out[$in[$i]] = $in[$i + 1];
        }

        return $out;
    }

    /**
     * Get the boolean clause limit.
     *
     * @return int
     * @access public
     */
    public function getBooleanClauseLimit()
    {
        global $configArray;

        // Use setting from config.ini if present, otherwise assume 1024:
        return isset($configArray['Index']['maxBooleanClauses'])
            ? $configArray['Index']['maxBooleanClauses'] : 1024;
    }

    /**
     * Extract terms from the Solr index.
     *
     * @param string $field           Field to extract terms from
     * @param string $start           Starting term to extract (blank for beginning
     * of list)
     * @param int    $limit           Maximum number of terms to return (-1 for no
     * limit)
     * @param bool   $returnSolrError Should we fail outright on syntax error
     * (false) or treat it as an empty result set with an error key set (true)?
     *
     * @return array                  Associative array parsed from Solr JSON
     * response; meat of the response is in the ['terms'] element, which contains
     * an index named for the requested term, which in turn contains an associative
     * array of term => count in index.
     * @access public
     */
    public function getTerms($field, $start, $limit, $returnSolrError = false)
    {
        $this->client->setMethod('GET');
        $this->client->setURL($this->host . '/term');

        $this->client->addQueryString('terms', 'true');
        $this->client->addQueryString('terms.fl', $field);
        $this->client->addQueryString('terms.lower.incl', 'false');
        $this->client->addQueryString('terms.lower', $start);
        $this->client->addQueryString('terms.limit', $limit);
        $this->client->addQueryString('terms.sort', 'index');
        $this->client->addQueryString('wt', 'json');

        $result = $this->client->sendRequest();

        if (!PEAR::isError($result)) {
            // Process the JSON response:
            $data = $this->_process(
                $this->client->getResponseBody(), $returnSolrError
            );

            // Tidy the data into a more usable format:
            $fixedArray = array();
            if (isset($data['terms'])) {
                foreach ($data['terms'] as $field => $contents) {
                    $data['terms'][$field] = $this->_processTerms($contents);
                }
            }
            return $data;
        } else {
            return $result;
        }
    }
}

?>
