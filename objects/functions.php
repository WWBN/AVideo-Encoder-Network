<?php

function ping($addr) {
    $addr = parse_url($addr);
    exec("ping -n 1 {$addr['host']}", $output, $status);
    $average = end($output);
    $average = explode("=", $average);
    return array('value'=>(intval(end($average))),'average'=>$average, 'output'=>$output);
}
