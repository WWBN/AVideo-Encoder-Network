<?php

function ping($addr) {
    $addr = parse_url($addr);
    if(getenv("OS")=="Windows_NT") {
        exec("ping -n 1 {$addr['host']}", $output, $status);
        $average = end($output);
        $average = intval(end(explode("=", $average)));
     }
     else {
      $output = exec("ping -c 1 -s 64 -t 64 ".$addr['host']);
      $array = explode("/", end(explode("=", $output )) );
      $average = $array[1];
     }
    
    return array('value'=>$average, 'output'=>$output);
}
