<?php
header('Content-Type: application/json');
$config = dirname(__FILE__) . '/../configuration.php';
require_once dirname(__FILE__) . '../objects/functions.php';
require_once $config;

$encoders = json_decode(url_get_contents("{$global['webSiteRootURL']}view/score.php"));

$bestEncoder = array('id' => 0, 'ping' => 9999, 'queue_size' => 9999, 'concurrent' => 1, 'memFreeBytes' => 0);

foreach ($encoders as $key => $value) {
    if (empty($value->ping)){
        $value->ping = new stdClass();
        $value->ping->value = 0;
    }
    $ping = floatval($value->ping->value);
    $queue_size = intval($value->serverStatus->queue_size);
    $memFreeBytes = floatval($value->serverStatus->memory->memFreeBytes);
    $siteURL = $value->siteURL;
    
    if (isset($value->serverStatus->concurrent))
        $concurrent = intval($value->serverStatus->concurrent);
    else
        $concurrent = 1;

    if (empty($bestEncoder['id'])) {
        $bestEncoder['id'] = $key;
        $bestEncoder['queue_size'] = $queue_size;
        $bestEncoder['concurrent'] = $concurrent;
        $bestEncoder['ping'] = $ping;
        $bestEncoder['memFreeBytes'] = $memFreeBytes;
        $bestEncoder['siteURL'] = $siteURL;
        continue;
    }

    if ($bestEncoder['queue_size'] / $bestEncoder['concurrent'] > $queue_size / $concurrent) {
        $bestEncoder['id'] = $key;
        $bestEncoder['queue_size'] = $queue_size;
        $bestEncoder['concurrent'] = $concurrent;
        $bestEncoder['ping'] = $ping;
        $bestEncoder['memFreeBytes'] = $memFreeBytes;
        $bestEncoder['siteURL'] = $siteURL;
        continue;
    }
   if ($bestEncoder['queue_size'] / $bestEncoder['concurrent'] < $queue_size / $concurrent) {
        continue;
   }

   if ($bestEncoder['concurrent'] < $concurrent) {
        $bestEncoder['id'] = $key;
        $bestEncoder['queue_size'] = $queue_size;
        $bestEncoder['concurrent'] = $concurrent;
        $bestEncoder['ping'] = $ping;
        $bestEncoder['memFreeBytes'] = $memFreeBytes;
        $bestEncoder['siteURL'] = $siteURL;
        continue;
   }
   if ($bestEncoder['concurrent'] > $concurrent) {
       continue;
   }

  if ($bestEncoder['ping'] > $ping) {
            $bestEncoder['id'] = $key;
            $bestEncoder['queue_size'] = $queue_size;
            $bestEncoder['concurrent'] = $concurrent;
            $bestEncoder['ping'] = $ping;
            $bestEncoder['memFreeBytes'] = $memFreeBytes;
            $bestEncoder['siteURL'] = $siteURL;
            continue;
   }
   if ($bestEncoder['ping'] < $ping) {
       continue;
   }

   if ($bestEncoder['memFreeBytes'] < $memFreeBytes) {
        $bestEncoder['id'] = $key;
        $bestEncoder['queue_size'] = $queue_size;
        $bestEncoder['concurrent'] = $concurrent;
        $bestEncoder['ping'] = $ping;
        $bestEncoder['memFreeBytes'] = $memFreeBytes;
        $bestEncoder['siteURL'] = $siteURL;
        continue;
    }
}

echo json_encode($bestEncoder);
