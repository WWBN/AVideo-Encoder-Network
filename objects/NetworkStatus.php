<?php
// Normalize only the operational fields used by the dashboard. Never forward credentials or job payloads.
function networkNumber($value, $minimum = 0)
{
    return is_numeric($value) && is_finite((float) $value) && (float) $value >= $minimum ? (float) $value : null;
}

function networkStatusMetrics(array $status)
{
    $memory = isset($status['memory']) && is_array($status['memory']) ? $status['memory'] : [];
    $total = networkNumber($memory['memTotalBytes'] ?? null, 1);
    $free = networkNumber($memory['memFreeBytes'] ?? null);
    $used = networkNumber($memory['memUsedBytes'] ?? null);
    $percent = $total !== null && $used !== null ? min(100, round($used / $total * 100, 1)) : null;
    return [
        'queue' => networkNumber($status['queue_size'] ?? null),
        'capacity' => networkNumber($status['concurrent'] ?? null, 1),
        'encoding' => isset($status['encoding']) && is_array($status['encoding']) ? count($status['encoding']) : null,
        'downloading' => isset($status['downloading']) && is_array($status['downloading']) ? count($status['downloading']) : null,
        'transferring' => isset($status['transferring']) && is_array($status['transferring']) ? count($status['transferring']) : null,
        'memoryTotal' => $total, 'memoryFree' => $free, 'memoryUsedPercent' => $percent,
        'uploadLimit' => isset($status['file_upload_max_size']) && is_scalar($status['file_upload_max_size']) ? substr((string) $status['file_upload_max_size'], 0, 40) : null,
        'version' => isset($status['version']) && is_scalar($status['version']) ? substr((string) $status['version'], 0, 40) : null,
    ];
}

function networkFetchStatus($url, array $credentials)
{
    $result = ['state' => 'unavailable', 'message' => 'Status could not be checked.', 'metrics' => null, 'responseMs' => null, 'checkedAt' => gmdate('c')];
    if (!extension_loaded('curl')) {
        $result['message'] = 'Enable the PHP cURL extension on the network server to check encoder status.';
        return $result;
    }
    if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['https', 'http'], true)) {
        $result['message'] = 'The registered encoder URL is invalid. Ask an administrator to update it.';
        return $result;
    }
    $curl = curl_init(rtrim($url, '/') . '/serverStatus');
    $body = '';
    curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($credentials),
        CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'AVideoEncoderNetwork/monitor',
        CURLOPT_WRITEFUNCTION => function ($handle, $chunk) use (&$body) {
            if (strlen($body) + strlen($chunk) > 2 * 1024 * 1024) { return 0; }
            $body .= $chunk;
            return strlen($chunk);
        }]);
    $success = curl_exec($curl);
    $code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $elapsed = (float) curl_getinfo($curl, CURLINFO_TOTAL_TIME);
    $error = curl_errno($curl);
    curl_close($curl);
    $result['checkedAt'] = gmdate('c');
    $result['httpStatus'] = $code;
    if ($success === false) {
        $result['state'] = $error === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'unavailable';
        $result['message'] = $error === CURLE_OPERATION_TIMEDOUT ? 'No status response within 15 seconds. The encoder may be busy or unable to reach your video site to validate sign-in. Check public access to the video site, or use an encoder on the same network.' :
            ($error === CURLE_PEER_FAILED_VERIFICATION ? 'The encoder TLS certificate could not be verified. Check its certificate chain and the PHP CA configuration.' : 'The network server could not reach this encoder. Check its URL, service, and network connection.');
        return $result;
    }
    if ($code === 401 || $code === 403) {
        $result['state'] = 'restricted';
        $result['message'] = 'The encoder denied status access. Check the current account credentials and allowed streamer sites.';
        return $result;
    }
    if ($code < 200 || $code >= 300) {
        $result['message'] = 'The status endpoint returned HTTP ' . $code . '. Check the encoder service and its final URL.';
        return $result;
    }
    $status = json_decode($body, true);
    if (!is_array($status)) {
        $result['message'] = 'The encoder returned an invalid status response. Check its PHP logs and serverStatus endpoint.';
        return $result;
    }
    if (!empty($status['error'])) {
        $result['state'] = 'restricted';
        $result['message'] = 'The encoder could not authorize this status request. Check its allowed sites and the registered streamer credentials.';
        return $result;
    }
    if (!array_key_exists('queue_size', $status) || networkNumber($status['queue_size']) === null) {
        $result['message'] = 'The response did not include valid queue metrics. Check encoder compatibility and status access.';
        return $result;
    }
    $result['state'] = 'online';
    $result['message'] = 'Status confirmed by the encoder.';
    $result['responseMs'] = max(1, (int) round($elapsed * 1000));
    $result['metrics'] = networkStatusMetrics($status);
    return $result;
}
