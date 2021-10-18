<?php

header('Content-Type: application/json');

$obj = new stdClass();
$obj->post = $_POST;

$confFile = "{$_POST['systemRootPath']}configuration.php";

if (file_exists($confFile)) {
    require_once $confFile;
    if (!empty($global['webSiteRootURL'])) {
        $obj->error = "Can not create configuration again";
        error_log($obj->error . json_encode($_SERVER));
        die(json_encode($obj));
    }
}

if (!is_writable($confFile)) {
    $obj->error = "{$confFile} must be writable";
    error_log($obj->error);
    die(json_encode($obj));
}
if (!file_exists($_POST['systemRootPath'] . "index.php")) {
    $obj->error = "Your system path to application ({$_POST['systemRootPath']}) is wrong";
    echo json_encode($obj);
    exit;
}

$mysqli = @new mysqli($_POST['databaseHost'], $_POST['databaseUser'], $_POST['databasePass'], "", $_POST['databasePort']);

/*
 * This is the "official" OO way to do it,
 * BUT $connect_error was broken until PHP 5.2.9 and 5.3.0.
 */
if ($mysqli->connect_error) {
    $obj->error = ('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    echo json_encode($obj);
    exit;
}

if ($_POST['createTables'] == 2) {
    $sql = "CREATE DATABASE IF NOT EXISTS {$_POST['databaseName']}";
    if ($mysqli->query($sql) !== TRUE) {
        $obj->error = "Error creating database: " . $mysqli->error;
        echo json_encode($obj);
        exit;
    }
}
$mysqli->select_db($_POST['databaseName']);

if ($_POST['createTables'] > 0) {
// Temporary variable, used to store current query
    $templine = '';
// Read in entire file
    $lines = file("{$_POST['systemRootPath']}install/database.sql");
// Loop through each line
    $obj->error = "";
    foreach ($lines as $line) {
// Skip it if it's a comment
        if (substr($line, 0, 2) == '--' || $line == '')
            continue;

// Add this line to the current segment
        $templine .= $line;
// If it has a semicolon at the end, it's the end of the query
        if (substr(trim($line), -1, 1) == ';') {
            // Perform the query
            if (!$mysqli->query($templine)) {
                $obj->error = ('Error performing query \'<strong>' . $templine . '\': ' . $mysqli->error . '<br /><br />');
            }
            // Reset temp variable to empty
            $templine = '';
        }
    }
}
$sql = "INSERT INTO streamers (siteURL, user, pass, created, modified) VALUES ('" . $_POST['siteURL'] . "', '" . $_POST['inputUser'] . "', '" . md5($_POST['inputPassword']) . "', now(), now())";
if ($mysqli->query($sql) !== TRUE) {
    $obj->error = "Error creating streamer: " . $mysqli->error;
    echo json_encode($obj);
    exit;
}

foreach (preg_split("/((\r?\n)|(\r\n?))/", $_POST['allowedEncoders']) as $line) {
    $line = trim($line);
    $name = parse_url($line, PHP_URL_HOST);
    $sql = "INSERT INTO `encoders` (`name`, `siteURL`, `streamers_id`) VALUES ('{$name}', '{$line}', '1')";
    if ($mysqli->query($sql) !== TRUE) {
        $obj->error = "Error creating encoder: " . $mysqli->error;
        echo json_encode($obj);
    }
}

$mysqli->close();

$content = "<?php
\$global['disableAdvancedConfigurations'] = 0;
\$global['videoStorageLimitMinutes'] = 0;
//\$global['webSiteRootURL'] = '{$_POST['webSiteRootURL']}';
// get the subdirectory, if exists
\$subDir = str_replace(array(\$_SERVER[\"DOCUMENT_ROOT\"], 'configuration.php'), array('',''), __FILE__);
\$global['webSiteRootURL'] = \"http\".(!empty(\$_SERVER['HTTPS'])?\"s\":\"\").\"://\".\$_SERVER['SERVER_NAME'].\$subDir;

\$global['systemRootPath'] = '{$_POST['systemRootPath']}';


\$mysqlHost = '{$_POST['databaseHost']}';
\$mysqlPort = '{$_POST['databasePort']}';
\$mysqlUser = '{$_POST['databaseUser']}';
\$mysqlPass = '{$_POST['databasePass']}';
\$mysqlDatabase = '{$_POST['databaseName']}';

/**
 * Do NOT change from here
 */

require_once \$global['systemRootPath'].'objects/include_config.php';
";

$fp = fopen($_POST['systemRootPath'] . "configuration.php", "wb");
fwrite($fp, $content);
fclose($fp);

$obj->success = true;
echo json_encode($obj);
