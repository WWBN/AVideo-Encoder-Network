<?php

header('Content-Type: application/json');
$config = dirname(__FILE__) . '/../configuration.php';
require_once $config;

$encoders = json_decode(file_get_contents("{$global['webSiteRootURL']}view/score.php"));

$bestEncoder = array('id'=>0, 'ping'=>9999, 'queue_size'=>9999, 'memFreeBytes'=>0);

foreach ($encoders as $key => $value) {
    
    $ping = floatval($value->ping->value);
    $queue_size = intval($value->serverStatus->queue_size);
    $memFreeBytes = intval($value->serverStatus->memory->memFreeBytes);
    
    if(empty($bestEncoder['id'])){
        $bestEncoder['id'] = $key;
        $bestEncoder['queue_size'] = $queue_size;
        $bestEncoder['ping'] = $ping;
        $bestEncoder['memFreeBytes'] = $memFreeBytes;
        continue;
    }
    
    if($bestEncoder['queue_size']>$queue_size){
        $bestEncoder['id'] = $key;
        $bestEncoder['queue_size'] = $queue_size;
        $bestEncoder['ping'] = $ping;
        $bestEncoder['memFreeBytes'] = $memFreeBytes;
        continue;
    }
    
    if($bestEncoder['ping']>$ping){
        $bestEncoder['id'] = $key;
        $bestEncoder['queue_size'] = $queue_size;
        $bestEncoder['ping'] = $ping;
        $bestEncoder['memFreeBytes'] = $memFreeBytes;
        continue;
    }
    
    if($bestEncoder['memFreeBytes']>$memFreeBytes){
        $bestEncoder['id'] = $key;
        $bestEncoder['queue_size'] = $queue_size;
        $bestEncoder['ping'] = $ping;
        $bestEncoder['memFreeBytes'] = $memFreeBytes;
        continue;
    }
}

echo json_encode($bestEncoder);

?>