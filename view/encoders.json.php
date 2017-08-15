<?php
require_once dirname(__FILE__) . '/../configuration.php';
require_once $global['systemRootPath'].'objects/Encoder.php';
header('Content-Type: application/json');
$rows = Encoder::getAll();
$total = Encoder::getTotal();

echo '{  "current": '.$_POST['current'].',"rowCount": '.$_POST['rowCount'].', "total": '.$total.', "rows":'. json_encode($rows).'}';