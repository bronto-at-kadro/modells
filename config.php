<?php

$config = array(
    'bronto' => array(
        'token' => '26745610-5490-4248-B830-DB98D5265999',
        'fields' => array(
            'firstname',
            'lastname',
			'RUNDATE',
			'CELL_CD',
			'CAMPAIGN_CD'
        ),
    ),
    'db' => array(
        'type'     => 'pdo_mysql',
        'host'     => 'localhost',
        'username' => 'root',
        'password' => 'vA2h4Wus',
        'dbname'   => 'modells',
        'profiler' => false,
    ),
    'ftp' => array(
        'host'     => '3ftp1.cognitivedata.com',
        'username' => '3client5199f',
        'password' => '3rmstk7',
        'path'     => '//3client5199f/upload/',
    ),
    'log' => array(
        'mail' => array(
            'subject' => '[184.73.208.159] Error Notification: Modell\'s',
	    'to' => 'jhodak@kadro.com'
        ),
    ),
    'opts' => array(
        'long' => array(
            'from::',
            'to::',
            'type::',
            'limit::',
            'worker::',
            'skip-count',
            'restart',
        ),
    ),
    'export' => array(
        'path' => APPLICATION_PATH . DIRECTORY_SEPARATOR . 'reports',
    ),
);

//$config['log']['mail'] = false;

if (APPLICATION_ENVIRONMENT === 'development') {
    $config['db']['username']  = 'root';
    $config['db']['password']  = 'dev';
    $config['db']['profiler']  = false;
//    $config['ftp'] = false;
   // $config['log']['mail'] = false;
}

return $config;
