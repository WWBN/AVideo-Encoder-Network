<?php

function ping($addr) {
    global $global;
    $file = "{$global['systemRootPath']}cache/ping" . md5($addr) . ".json";
    $lifetimeSeconds = 30;
    if (file_exists($file)) {
        $fileAge = time() - filemtime($file);
    } else {
        $fileAge = $lifetimeSeconds*2;
    }
    error_log("PING ==> fileAge = $fileAge AND lifetimeSeconds = $lifetimeSeconds");
    if ($fileAge > $lifetimeSeconds) {
        $addr = parse_url($addr);
        if (getenv("OS") == "Windows_NT") {
            exec("ping -n 1 {$addr['host']}", $output, $status);
            $average = end($output);
            $out = explode("=", $average);
            $average = intval(end($out));
        } else {
            $output = exec("ping -c 1 -s 64 -t 64 " . $addr['host']);
            $v = explode("=", $output);
            $array = explode("/", end($v));
            $average = floatval(@$array[1]);
        }
        $content = json_encode(array('value'=>$average, 'output'=>$output, 'addr'=>$addr));
        file_put_contents($file, $content);
    } else {
        $content = url_get_contents($file);
    }

    return json_decode($content);
}

function hasLastSlash($word) {
    return substr($word, -1) === '/';
}

function addLastSlash($word) {
    return $word . (hasLastSlash($word) ? "" : "/");
}

function getSelfUserAgent($complement = "") {
    global $global;
    $agent = 'AVideoEncoderNeetwork ';
    $agent .= parse_url($global['webSiteRootURL'], PHP_URL_HOST);
    $agent .= " {$complement}";
    return $agent;
}

function url_get_contents($Url, $ctx = "", $timeout = 0) {
    global $global;
    $agent = getSelfUserAgent();
    if (empty($ctx)) {
        $opts = array(
            'http' => array('header' => "User-Agent: {$agent}\r\n"),
            "ssl" => array(
                "verify_peer" => false,
                "verify_peer_name" => false,
                "allow_self_signed" => true
            )
        );
        if (!empty($timeout)) {
            ini_set('default_socket_timeout', $timeout);
            $opts['http']['timeout'] = $timeout;
        }
        $context = stream_context_create($opts);
    } else {
        $context = $ctx;
    }

    // some times the path has special chars
    if (!filter_var($Url, FILTER_VALIDATE_URL)) {
        if (!file_exists($Url)) {
            $Url = utf8_decode($Url);
        }
    }

    if (ini_get('allow_url_fopen')) {
        try {
            $tmp = @file_get_contents($Url, false, $context);
            if ($tmp != false) {
                return ($tmp);
            }
        } catch (ErrorException $e) {
            try {
                fetch_http_file_contents($Url);
            } catch (ErrorException $e) {
                error_log("Error on get Content");
            }
        }
    } else if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        curl_setopt($ch, CURLOPT_URL, $Url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $output = curl_exec($ch);
        curl_close($ch);
        return remove_utf8_bom($output);
    }
    $content = @file_get_contents($Url, false, $context);
    if (empty($content)) {
        return "";
    }
    return $content;
}