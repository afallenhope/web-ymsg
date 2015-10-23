<?php
ini_set('max_execution_time', 45);
define("DEBUG",true);
ob_start();

if(DEBUG)
  error_reporting(E_ALL & ~E_NOTICE);
require_once('includes/utilities.inc.php');

$client = new YMSGClient('scsa.msg.yahoo.com','5050');
$client->Connect('YOUR USERNAME','YOUR PASSWORD');

ob_flush();
ob_end_flush();
?>
