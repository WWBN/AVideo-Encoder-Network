<?php

header('Content-Type: application/json');
$config = dirname(__FILE__) . '/../configuration.php';
require_once $config;

$encoders = json_decode(file_get_contents("{$global['webSiteRootURL']}view/score.php"));

foreach ($encoders as $value) {
    $ping = floatval($value->ping);
    $queue_size = intval($value->serverStatus->queue_size);
    $memFreeBytes = intval($value->serverStatus->memory->memFreeBytes);
    var_dump($ping, $queue_size, $memFreeBytes);
}

?>