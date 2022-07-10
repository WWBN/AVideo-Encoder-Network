<?php
header('Content-Type: application/json');
$config = dirname(__FILE__) . '/../configuration.php';
require_once $config;
require_once '../objects/Encoder.php';

$opts = array('http' =>
  array( 'timeout' => 1 )
); 
$context  = stream_context_create($opts);

$file = "{$global['systemRootPath']}cache/score.json";
$lifetimeSeconds = 60;
if (file_exists($file)) {
    $fileAge = time() - filemtime($file);
} else {
    $fileAge = $lifetimeSeconds*2;
}
error_log("SCORE ==> fileAge = $fileAge AND lifetimeSeconds = $lifetimeSeconds");

if ($fileAge > $lifetimeSeconds) {
    $encoders = Encoder::getAll();
    $site = array();

    foreach ($encoders as $value) {
        $site[$value['id']]['ping'] = json_decode(url_get_contents("{$global['webSiteRootURL']}ping/{$value['id']}", $context));
        $site[$value['id']]['siteURL'] = $value['siteURL'];
        $site[$value['id']]['serverStatus'] = json_decode(url_get_contents("{$value['siteURL']}serverStatus", $context));
    }

    $content = json_encode($site);

    file_put_contents($file, $content);

} else {
    $content = url_get_contents($file);
}

echo $content;
