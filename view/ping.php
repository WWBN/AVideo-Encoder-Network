<?php
require_once dirname(__FILE__) . '/../configuration.php';
require_once $global['systemRootPath'].'objects/functions.php';
require_once $global['systemRootPath'].'objects/Encoder.php';
header('Content-Type: application/json');
if (empty($_GET['id'])) {
    echo 0;
    exit;
}
$e = new Encoder($_GET['id']);
echo json_encode(ping($e->getSiteURL()));
