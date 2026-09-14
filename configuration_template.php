<?php
$global['webSiteRootURL'] = 'http://127.0.0.1/AVideo-Encoder-Network/';
$global['systemRootPath'] = str_replace('\\', '/', __DIR__) . '/';

$global['disableConfigurations'] = false;
$global['disableBulkEncode'] = false;

$mysqlHost = 'localhost';
$mysqlPort = '3306';
$mysqlUser = 'root';
$mysqlPass = '';
$mysqlDatabase = 'aVideo-Encoder-Network';

require_once $global['systemRootPath'].'objects/include_config.php';
