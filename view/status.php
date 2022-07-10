<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
require_once dirname(__FILE__) . '/../configuration.php';
require_once '../objects/Encoder.php';
require_once $global['systemRootPath'] . 'objects/functions.php';

$obj = new stdClass();
$obj->queue_size = array();
$obj->is_encoding = array();
$obj->queue_list = array();
$obj->msg = array();
$obj->cmd = array();
$obj->encoderSiteURL = array();
$obj->encoding_status = array();
$obj->encoding = array();
$obj->download_status = array();

$rows = Encoder::getAll();
foreach ($rows as $value) {
    $status = json_decode(url_get_contents($value['siteURL'] . 'status'));
    if (!empty($status)){
        $obj->encoderSiteURL[] = $value['siteURL'];
        $obj->queue_size[] = $status->queue_size;
        $obj->is_encoding[] = $status->is_encoding;
        $obj->queue_list[] = $status->queue_list;
        $obj->msg[] = $status->msg;
        $obj->cmd[] = $status->cmd;
        $obj->encoding_status[] = $status->encoding_status;
        $obj->encoding[] = $status->encoding;
        $obj->download_status[] = $status->download_status;
    }
}

$resp = json_encode($obj);
echo $resp;
