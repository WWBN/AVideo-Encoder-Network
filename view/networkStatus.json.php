<?php
ini_set('display_errors', '0');
require_once dirname(__DIR__) . '/configuration.php';
require_once dirname(__DIR__) . '/objects/Login.php';
require_once dirname(__DIR__) . '/objects/Encoder.php';
require_once dirname(__DIR__) . '/objects/NetworkStatus.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!Login::canUpload()) {
    http_response_code(403);
    echo json_encode(['error' => true, 'msg' => 'Your session has expired or does not have upload access. Sign in again.']);
    exit;
}
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => true, 'msg' => 'Select a valid encoder.']);
    exit;
}
// Release the session lock before remote I/O so encoders can be checked concurrently.
$login = $_SESSION['login'] ?? null;
$credentials = ['user' => $login->user ?? '', 'pass' => $login->pass ?? '',
    'siteURL' => Login::getStreamerURL(), 'encodedPass' => 'true'];
session_write_close();
try {
    $encoder = new Encoder($id);
    if (!$encoder->getId()) {
        http_response_code(404);
        echo json_encode(['error' => true, 'msg' => 'This encoder is no longer registered. Reload the dashboard.']);
        exit;
    }
    if (!$credentials['user'] || !$credentials['pass'] || !$credentials['siteURL']) {
        $status = ['state' => 'restricted', 'message' => 'Your session is missing encoder credentials. Sign out and sign in again to reconnect.', 'metrics' => null, 'responseMs' => null, 'checkedAt' => gmdate('c')];
    } else {
        $status = networkFetchStatus($encoder->getSiteURL(), $credentials);
    }
    echo json_encode(['error' => false, 'id' => $id] + $status, JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    error_log('Encoder Network status check failed for encoder ' . (int) $id . ': ' . get_class($error));
    http_response_code(500);
    echo json_encode(['error' => true, 'msg' => 'Unable to check this encoder. Review the network server PHP log.']);
}
