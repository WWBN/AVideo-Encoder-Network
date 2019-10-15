<?php
header('Content-Type: application/json');
require_once './Login.php';
require_once './Streamer.php';
$object = new stdClass();
if (empty($_POST['user']) || empty($_POST['pass'])) {
    $object->error = "User and Password can not be blank";
     die(json_encode($object));
}

Login::run($_POST['user'], $_POST['pass'], $_POST['siteURL'], $_POST['encodedPass']);
echo json_encode($_SESSION['login']);
