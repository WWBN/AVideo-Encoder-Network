<?php

function ping($addr) {
    $addr = parse_url($addr);
    if(getenv("OS")=="Windows_NT") {
        exec("ping -n 1 {$addr['host']}", $output, $status);
        $average = end($output);
        $out = explode("=", $average);
        $average = intval(end($out));
     }
     else {
      $output = exec("ping -c 1 -s 64 -t 64 ".$addr['host']);
      $v = explode("=", $output );
      $array = explode("/", end($v) );
      
      $average = floatval(@$array[1]);
     }
    
    return array('value'=>$average, 'output'=>$output, 'addr'=>$addr);
}
