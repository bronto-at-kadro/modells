<?php

// Bootstrap
define('APPLICATION_PATH', realpath(dirname(__FILE__)));
define('LOG_DIR', '/var/www/html/modells/logs/');
chdir(APPLICATION_PATH);
require_once '../Bootstrap.php';
$bootstrap = Bootstrap::getInstance()->run();
$logger    = $bootstrap->getLog();
$api       = $bootstrap->getBronto();
$uuid      = $api->getUuid();

// DbTable(s)
$logsTable     = new DbTable_Logs();
$contactsTable = new DbTable_Contacts();

// Check if we need to create tables
if (!file_exists('db_tables.lock')) {
    $contactsTable->createTable();
    $logsTable->createTable();
    touch('db_tables.lock');
    echo "Created DbTables...";
    exit;
}
