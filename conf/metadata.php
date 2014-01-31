<?php

$meta['DBType'] = array('multichoice', '_choices' => array('mysqli', 'odbc'));
$meta['DBHost'] = array('string');
$meta['DBName'] = array('string');
$meta['DBUserName'] = array('string');
$meta['DBUserPassword'] = array('password');
$meta['DBTableName'] = array('string');
$meta['check_database'] = array('onoff');
$meta['amount'] = array('numeric');

//Setup VIM: ex: et ts=2 enc=utf-8 :