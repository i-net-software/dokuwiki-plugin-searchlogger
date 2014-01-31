<?php
/**
 * Search with Scopes
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

 // must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_searchlogger_mostwanted extends DokuWiki_Syntax_Plugin {

	var $functions = null;

    function getType() { return 'substition'; }
    function getPType() { return 'block'; }
    function getSort() { return 98; }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('~~MOSTWANTED:\d*~~', $mode, 'plugin_searchlogger_mostwanted');
    }

    function handle($match, $state, $pos, &$handler) {
		global $ID;
		
		$match = intval(substr($match, 13, -2)); // strip markup - Amount to show
		if ( !$this->functions =& plugin_load('helper', 'searchlogger') ) { return false; }
		if ( !$this->functions->checkDatabase() ) { return false; }
		
		return array($match);

	}            

    function render($mode, &$renderer, $data) {
        global $conf;
		
		list($amount) = $data;

        if ($mode == 'xhtml') {

			$renderer->nocache();
			if ( empty($amount) ) $amount = 10;
			
			if ( !$this->functions =& plugin_load('helper', 'searchlogger') ) { return false; }
			$this->functions->init_database();
			$table = $this->functions->database->_escapeParameter($this->getConf('DBTableName'));
			$this->functions->database->prepare("SELECT query, SUM(occurency) AS occurency, COUNT(ID) AS amount FROM `$table` WHERE NOT query='' GROUP BY query ORDER BY amount DESC, occurency DESC LIMIT ? ;");
			$this->functions->database->execute($amount);
			
			if ( $this->functions->database->num_rows() > 0 ) {
				$cloud = array();
				$data = array(); $this->functions->database->bind_assoc($data);
				while( $this->functions->database->fetch() ) {
					$cloud[$data['query']] = $data['amount'];
				}
				
				$renderer->doc .= $this->functions->_get_cloud($cloud);
			} else {
				$renderer->doc .= "No search queries found.";
			}
			
            return true;
        }
        return false;
    }
}
// vim:ts=4:sw=4:et:enc=utf-8: 
