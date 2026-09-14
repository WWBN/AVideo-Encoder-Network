<?php
header('Content-Type: application/json');
header('Cache-Control: no-store');
ini_set('display_errors', '0');
require_once __DIR__ . '/Login.php';
require_once __DIR__ . '/Streamer.php';
$object = new stdClass();
if (!is_string($_POST['user'] ?? null) || !is_string($_POST['pass'] ?? null) ||
    !is_string($_POST['siteURL'] ?? null) || !is_string($_POST['encodedPass'] ?? 'false') ||
    empty($_POST['user']) || empty($_POST['pass'])) {
    http_response_code(400);
    $object->error = "User and Password can not be blank";
     die(json_encode($object));
}

try {
    Login::run($_POST['user'], $_POST['pass'], $_POST['siteURL'], $_POST['encodedPass'] ?? 'false');
} catch (Throwable $error) {
    Login::logoff();
    error_log('Network sign-in failed: ' . get_class($error));
    http_response_code(500);
    echo json_encode(['error' => 'Sign-in could not be completed. Check the server logs.']);
    exit;
}
echo json_encode($_SESSION['login']);
