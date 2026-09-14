<?php
require_once dirname(__FILE__) . '/../configuration.php';
require_once dirname(__FILE__) . '/Streamer.php';
require_once dirname(__FILE__) . '/functions.php';

class Login
{

    static function run($user, $pass, $aVideoURL, $encodedPass = false)
    {
        global $global;
        // Start denied, including when a previous session exists and this attempt fails.
        $_SESSION['login'] = (object) ['streamer' => false, 'streamers_id' => 0,
            'isLogged' => false, 'isAdmin' => false, 'canUpload' => false, 'canComment' => false];
        if (!is_string($user) || !is_string($pass) || !is_string($aVideoURL) ||
            $user === '' || $pass === '' || !filter_var($aVideoURL, FILTER_VALIDATE_URL) ||
            !in_array(strtolower(parse_url($aVideoURL, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)) {
            return;
        }
        if (substr($aVideoURL, -1) !== '/') {
            $aVideoURL .= "/";
        }

        $postdata = http_build_query(
            array(
                'user' => $user,
                'pass' => $pass,
                'encodedPass' => $encodedPass
            )
        );

        $opts = array(
            "ssl" => array(
                "verify_peer" => true,
                "verify_peer_name" => true,
            ),
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata,
                'timeout' => 15,
                'follow_location' => 0
            )
        );

        $context = stream_context_create($opts);

        if (function_exists('curl_init')) {
            // Preserve POST authentication when allow_url_fopen is disabled.
            $curl = curl_init($aVideoURL . 'login');
            curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $postdata,
                CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS]);
            $result = curl_exec($curl);
            $code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            if ($code < 200 || $code >= 300) { return; }
        } else {
            $result = @file_get_contents($aVideoURL . 'login', false, $context);
        }
        $object = is_string($result) ? json_decode($result) : null;
        if (!is_object($object) || empty($object->isLogged) || empty($object->canUpload)) {
            return;
        } else {
            $object->streamer = $aVideoURL;
            $object->streamers_id = 0;
            if (!empty($object->canUpload)) {
                $object->streamers_id = Streamer::createIfNotExists($user, $pass, $aVideoURL, $encodedPass);
            }
            if ($object->streamers_id) {
                $s = new Streamer($object->streamers_id);
                if (!$encodedPass || $encodedPass === 'false') {
                    $pass = md5($pass);
                }
                // update pass
                $s->setPass($pass);
                $s->save();
            }
        }
        $_SESSION['login'] = $object;
    }

    static function logoff()
    {
        unset($_SESSION['login']);
    }

    static function isLogged()
    {
        return !empty($_SESSION['login']->isLogged);
    }

    static function isAdmin()
    {
        return !empty($_SESSION['login']->isAdmin);
    }

    static function canUpload()
    {
        return !empty($_SESSION['login']->canUpload);
    }

    static function canComment()
    {
        return !empty($_SESSION['login']->canComment);
    }

    static function getStreamerURL()
    {
        if (!static::isLogged()) {
            return false;
        }
        return $_SESSION['login']->streamer;
    }

    static function getStreamerId()
    {
        if (!static::isLogged()) {
            return false;
        }
        return $_SESSION['login']->streamers_id;
    }

}
