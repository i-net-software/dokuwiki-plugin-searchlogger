<?php
/**
 * i-net Download Plugin
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');

class helper_plugin_searchlogger extends DokuWiki_Plugin { // DokuWiki_Helper_Plugin

	var $database = null;
	var $checkedOK = null;
	var $pageWordLenIdx = null;


    function getMethods() {
        $result = array();
		return $result;
	}
  
	function checkDatabase() {

		$this->init_database();
		if ( empty($this->database) ) { return false; }

		if ( !is_null($this->checkedOK) ) { return $this->checkedOK; }
		$this->checkedOK = $this->currentVersionChecked();
		if ( $this->checkedOK ) { return $this->checkedOK; }
		
		$tableName = $this->database->escapeParameter($this->getConf('DBTableName'));
		$this->database->query("SHOW TABLES LIKE \"$tableName\";");

		$allGood = false;
		
		if ( $this->database->num_rows() != 1 ) {
			// Create Log
			$this->database->prepare("CREATE TABLE `$tableName` (  `ID` int(11) NOT NULL auto_increment,  `query` varchar(255) NOT NULL,  `pages` int(11) NOT NULL,  `occurency` int(11) NOT NULL,  `date` datetime NOT NULL,  `dw_key` varchar(255) NOT NULL,  PRIMARY KEY  (`ID`));");
			$this->database->execute();

			$allGood = true;
		} else {
			// Check Log
			$cols = array('ID', 'query', 'pages', 'occurency', 'date', 'dw_key');
			$table = $this->database->databaseConnection->escape_string($this->getConf('DBTableName'));
			$this->database->query("SHOW COLUMNS FROM `$tableName`");

			if ( $this->database->num_rows() >= 5 ) {

				$allGood = true;
				while ( $data = $this->database->fetch_array() ) {
					if ( !in_array($data['Field'], $cols) ) {
						msg("Field missing in searchLog: '{$data['Field']}'", -1);
						$allGood = false;
						break;
					}
				}
			}
		}

		$this->checkedOK = $allGood;
		return $allGood;
	}

	function init_database() {
	
		if ( empty($this->database) ) {
			if ( !$this->database =& plugin_load('helper', 'databaseconnector',true) ) { return false;}
			$this->dbType = $dbType;
			$this->database->setType($this->getConf('DBType'));
			$this->database->connect($this->getConf('DBName'), $this->getConf('DBUserName'), $this->getConf('DBUserPassword'), $this->getConf('DBHost'));
		}
	}
	
	function close_database() {
	
		if ( !empty($this->database) ) {
			$this->database->close();
			$this->database = null;
		}
	}
	
	function _get_cloud($cloud) {
		global $conf, $ID;
		
		$min = current($cloud);
		$max = end($cloud);
		$delta = ($max-$min)/16;
		
		// and render the cloud
		$output = '<div id="cloud">'.DOKU_LF;
		foreach ($cloud as $word => $size) {
			if ($size < $min+round($delta)) $class = 'cloud1';
			elseif ($size < $min+round(2*$delta)) $class = 'cloud2';
			elseif ($size < $min+round(4*$delta)) $class = 'cloud3';
			elseif ($size < $min+round(8*$delta)) $class = 'cloud4';
			else $class = 'cloud5';

			$link = wl($ID, array('do'=>'search', 'id'=>$word));
			$title = "$word ($size)";
			$output .= DOKU_TAB.'<a href="'.$link.'" class="'.$class.'"'.
				' title="'.$title.'">'.$word.'</a>'.DOKU_LF;
		}
		return $output .= '</div>'.DOKU_LF;
	}
	
		
	function _ft_pageSearch(&$data){
	
		// split out original parameters
		$query = $data['query'];
		$highlight =& $data['highlight'];

		$q = ft_queryParser($query);
		
		$highlight = array();

		// remember for hilighting later
		foreach($q['words'] as $wrd){
			$highlight[] =  str_replace('*','',$wrd);
		}

		// lookup all words found in the query
		$words  = array_merge($q['and'],$q['not']);
		if(!count($words)) return array();
		$result = idx_lookup($words);
		if(!count($result)) return array();
		
		// merge search results with query
		foreach($q['and'] as $pos => $w){
			$q['and'][$w] = $result[$w];
			unset($q['and'][$pos]);
		}

		// create a list of unwanted docs
		$not = array();
		foreach($q['not'] as $pos => $w){
			$not = array_merge($not,array_keys($result[$w]));
		}

		
		$docs = $this->ft_resultCombine($q['and']);
		
		if(!count($docs)) return array();

		// create a list of hidden pages in the result
		$hidden = array();
		$hidden = array_filter(array_keys($docs),'isHiddenPage');
		$not = array_merge($not,$hidden);
		
		// filter unmatched namespaces
		if(!empty($q['ns'])) {
			$pattern = implode('|^',$q['ns']);
			foreach($docs as $key => $val) {
				if(!preg_match('/^'.$pattern.'/',$key)) {
					unset($docs[$key]);
				}
			}
		}

		// remove negative matches
		foreach($not as $n){
			unset($docs[$n]);
		}

		if(!count($docs)) return array();

		// handle phrases
		if(count($q['phrases'])){
			$q['phrases'] = array_map('utf8_strtolower',$q['phrases']);
			// use this for higlighting later:
			$highlight = array_merge($highlight,$q['phrases']);
			$q['phrases'] = array_map('preg_quote_cb',$q['phrases']);
			// check the source of all documents for the exact phrases
			foreach(array_keys($docs) as $id){
				$text = utf8_strtolower(rawWiki($id));
				foreach($q['phrases'] as $phrase){
					if(!preg_match('/'.$phrase.'/usi',$text)){
						unset($docs[$id]); // no hit - remove
						break;
					}
				}
			}
		}

		if(!count($docs)) return array();

		// check ACL permissions
		foreach(array_keys($docs) as $doc){
			if(auth_quickaclcheck($doc) < AUTH_READ){
				unset($docs[$doc]);
			}
		}

		if(!count($docs)) return array();
		
		$docs = $this->ft_resultScore($docs);

		return $docs;
	}

	/**
	 * Combine found documents and sum up their scores
	 *
	 * This function is used to combine searched words with a logical
	 * AND. Only documents available in all arrays are returned.
	 *
	 * based upon PEAR's PHP_Compat function for array_intersect_key()
	 *
	 * @param array $args An array of page arrays
	 */
	function ft_resultCombine($args){
		
	    $result = array();
		foreach ($args as $w => $pages ) {
			foreach ( $pages as $key => $value) {
				$result[$key][$w] = $value;
			}
	    }
		
	    return $result;
	}

	function ft_resultScore($args){
		$results = array();
		foreach ( $args as $page => $values ) {
			$pageWords = $this->idx_getPageWordLen($page);
			if ( $pageWords === false ) { $pageWords = $this->idx_calcPageWordLen($page); // count of words
			}

			if ( $pageWords === false || is_null($pageWords) || $pageWords == 0 ) { $score = 0; }
			else {
				$min = min($values);
				$score = $min / $pageWords;
			}
			$results[$page] = array("score" => $score, "count" => array_sum($values));
		}
		
		// if there are any hits left, sort them by count
		array_multisort($results, SORT_DESC);
		foreach( $results as $page => $value) {
			$results[$page] = $value['count'];
		}

		return $results;
	}
	
	/**
	 * Adds/updates the search for the given page
	 *
	 * This is the core function of the indexer which does most
	 * of the work. This function needs to be called with proper
	 * locking!
	 *
	 * @author Andreas Gohr <andi@splitbrain.org>
	 */
	function idx_addPageWordLen($page, $count){
	    global $conf;

	    // load known documents
	    $page_idx = idx_getIndex('pagewordlen','');

	    $pid = $this->Array_Search_Preg("$page\*\d*\n",$page_idx);
		if ( !is_int($pid) ) {
	        // page was new - write back
	        if (!idx_appendIndex('pagewordlen','',"$page*$count\n")){
	            trigger_error("Failed to write page index", E_USER_ERROR);
	            return false;
	        }
		}

	    unset($page_idx); // free memory

	    if(!idx_saveIndexLine('pageword','',$pid,"$page*$count\n")){
	        trigger_error("Failed to write word length index", E_USER_ERROR);
	        return false;
	    }

	    return true;
	}
	
	function idx_calcPageWordLen($ID, $hasToIndex=null) {

		if ( is_null($hasToIndex) ) { $hasToIndex = $this->idx_indexingNeeded($ID); }
		if ( !$hasToIndex ) { return false; }

	    // do the work
		$words = idx_getPageWords($ID);
		if($words === false) return false;
		$count = 0;
		
		foreach( $words as $key => $value ) {
			$count += array_sum($value);
		}

		$this->idx_addPageWordLen($ID, $count);

	    return $count;
	}
	
	function Array_Search_Preg( $find, $in_array )
	{
	    if( is_array( $in_array ) ) {
	        foreach( $in_array as $key=> $val ) {
	            if( is_array( $val ) ) return $this->Array_Search_Preg( $find, $val );
	            else {
	                if( preg_match( '/'. $find .'/', $val ) ) return $key;
	            }
	        }
	    }
	    return false;
	}
	
	function idx_getPageWordLen($page) {
		
		if ( is_null($this->pageWordLenIdx) ) {
			$this->pageWordLenIdx = idx_getIndex('pagewordlen','');
		}
		
		$pid = $this->Array_Search_Preg("$page\*\d*\n",$this->pageWordLenIdx);
		if ( !is_int($pid) ) {
			return false;
		}
		
		return trim(array_pop(explode('*', $this->pageWordLenIdx[$pid], 2)));
	}
	
	function idx_indexingNeeded($ID) {
	    // check if indexing needed
	    $idxtag = metaFN($ID,'.indexed');
	    if(@file_exists($idxtag)){
	        if(io_readFile($idxtag) >= INDEXER_VERSION){
	            $last = @filemtime($idxtag);
	            if($last > @filemtime(wikiFN($ID))){
	                print "idx_calcPageWordLen(): index for $ID up to date".NL;
	                return false;
	            }
	        }
	    }
		return false;
	}

	
	/**
	 * Prüfen, ob die aktuelle Version schonmal hinsichtlich der Datenbankanbindung geprüft wurde
	 */
	function currentVersionChecked() {
	    if ( !file_exists( dirname(__FILE__).'/.databaseversioncheck' ) ) {
	        return false;
	    }
	    
	    $checkDate = implode('', file( dirname(__FILE__).'/.databaseversioncheck' ) );
	    $hash = confToHash(dirname(__FILE__).'/info.txt');
	    return $hash['date'] == trim($checkDate); 
	}
}
  
//Setup VIM: ex: et ts=4 enc=utf-8 :