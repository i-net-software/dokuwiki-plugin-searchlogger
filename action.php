<?php
/**
 * search Logger Plugin
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_searchlogger extends DokuWiki_Action_Plugin {

	var $functions = null;
	var $hasToIndex = false;
	var $didAlreadyLog = false;

	function register(&$controller) {

		// Log Query
		$controller->register_hook('SEARCH_QUERY_FULLPAGE', 'AFTER', $this, 'searchlogger__log');
		$controller->register_hook('SEARCH_QUERY_PAGELOOKUP', 'AFTER', $this, 'searchlogger__pagelookup_shorten');
	}

	function searchlogger__pagelookup_shorten(&$event, $args) {
		$amount = $this->getConf('amount');
		if ( !empty($amount) && $amount > 0 ) {
			$event->result = array_slice($event->result, 0, $this->getConf('amount'));
		}
	}
	
	/*
	 *  Calc Word Len Index For Page while Indexing
	 */
	function searchlogger__prepareCalcWordLen(&$event, $args) {
		global $ID;
		
		if ( !$this->functions =& plugin_load('helper', 'searchlogger') ) { return false; }
		$this->hasToIndex = $this->functions->idx_indexingNeeded($ID);
	}
	 
	function searchlogger__calcWordLen(&$event, $args) {
		global $ID;
	    if(!$ID) return false;
		if ( !$this->functions =& plugin_load('helper', 'searchlogger') ) { return false; }
		return $this->functions->idx_calcPageWordLen($ID, $this->hasToIndex);
	}
	
	function searchlogger__log(&$event) {
		global $ACT;

		if ( $ACT == 'search' && ! $this->didAlreadyLog) {

			$this->didAlreadyLog = true;
			if ( !$this->functions =& plugin_load('helper', 'searchlogger') ) { return false; }
			if ( $this->getConf('check_database') && !$this->functions->checkDatabase() ) {
				$this->functions = null;
				return false;
			}
			
			$this->functions->init_database();
			$table = $this->functions->database->_escapeParameter($this->getConf('DBTableName'));
			$this->functions->database->prepare("INSERT INTO `$table` (query, pages, occurency, dw_key, date) VALUES(?, ?, ?, ?, NOW()) ");
			$this->functions->database->execute($event->data['query'], intval(count($event->result)), intval(array_sum($event->result)), auth_browseruid());

			print "<div class=\"search_quickresult searchlogger\">";
			print "<p style=\"float: right; margin:0px 20px;\">The search found: <b>" . intval(count(array_values($event->result))) . " pages</b> with <b>" . intval(array_sum($event->result)) . " occurencies</b>.</p>";
			tpl_searchform();
			print "<div class=\"clearer\">&nbsp;</div></div>";
			flush();
		}
		
		return true;
	}
/*
	function tpl_searchform() {
		global $lang;
		global $ACT;
	
		// don't print the search form if search action has been disabled
		if (!actionOk('search')) return false;
		@require_once(DOKU_INC . 'inc/form.php');
		$form = new Doku_Form('searchlogger__search', wl(), 'post');
		$form->addElement(form_makeOpenTag('div'));
		$form->addElement(form_makeField('text', 'id', ($ACT=='search'?$_REQUEST['id']:''), $lang['btn_search'] . ':', '', '', array('class'=>'edit')));
		$form->addElement(form_makeButton('submit', 'search', $lang['btn_search'], array('class'=>'button', 'title'=>$lang['btn_search'])));
		$form->addElement(form_makeCloseTag('div'));
		
		$form->printForm();
	}
*/
}

//Setup VIM: ex: et ts=2 enc=utf-8 :