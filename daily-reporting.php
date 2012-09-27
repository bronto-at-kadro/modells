<?php

/* @var $bootstrap Bootstrap */
/* @var $logger Zend_Log */
/* @var $api Bronto_Api */
/* @var $uuid Bronto_Util_Uuid */
/* @var $logsTable DbTable_Logs */
/* @var $contactsTable DbTable_Contacts */

// Bootstrap
define('LOG_APPEND', '_process');
define('CURRENT_SCRIPT', __FILE__);
require_once 'index.php';

try {
	$cmdTo = date('Y-m-d');
	$cmdFrom = date('Y-m-d', time()-60*60*24);
	$logName = LOG_DIR.$cmdFrom.'_dailyreport.log';
	
	$command = 'php ' . APPLICATION_PATH . DS . "activities.php --from={$cmdFrom} --to={$cmdTo}  >> $logName";
	
	$logger->info("STARTING REPORTING WITH THIS COMMAND = ".$command);
	
//	echo $command;
//	exit();
 	$output = shell_exec($command." &");


} catch (Exception $e) {
    $logger->emerg($e);
    exit(1);
}


?>