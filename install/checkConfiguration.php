<?php
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/installer.php';
if (installerConfigured()) {
    echo json_encode(['error' => 'Setup is locked.', 'msg' => 'Installation is already complete.', 'success' => false]);
    return;
}
$steps = []; $stage = 'validation'; $mysqli = null; $lock = null; $temporary = null; $committed = false; $data = [];
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); header('Allow: POST');
        throw new InstallerFailure('Submit the setup form to continue.');
    }
    installerSession();
    if (!hash_equals($_SESSION['installerToken'], installerField($_POST, 'token'))) {
        http_response_code(403);
        throw new InstallerFailure('Your session has expired. Reload the page and try again.');
    }
    session_write_close();
    if (installerConfigured()) {
        throw new InstallerFailure('Installation is already complete. Setup is locked.');
    }
    $action = installerField($_POST, 'action', 'install');
    if (!in_array($action, ['test', 'install'], true)) { throw new InstallerFailure('Invalid action.'); }
    foreach (['databaseHost', 'databasePort', 'databaseName', 'databaseUser', 'databasePass'] as $key) { $data[$key] = installerField($_POST, $key); }
    if (!preg_match('/^[a-zA-Z0-9_.:\-]+$/D', $data['databaseHost']) || strlen($data['databaseHost']) > 253 ||
        !ctype_digit($data['databasePort']) || (int)$data['databasePort'] < 1 || (int)$data['databasePort'] > 65535 ||
        !preg_match('/^[a-zA-Z0-9_\-]{1,64}$/D', $data['databaseName']) || !preg_match('/^[a-zA-Z0-9_.@\-]{1,80}$/D', $data['databaseUser'])) {
        throw new InstallerFailure('Check the host, port (1–65535), username, and database name. Use letters, numbers, hyphens, or underscores in the database name.');
    }
    $stage = 'requirements';
    foreach (($action === 'test' ? [['label' => 'MySQLi', 'ok' => extension_loaded('mysqli'), 'detail' => 'Enable PHP mysqli.']] : installerChecks()) as $check) {
        if (!$check['ok']) {
            $help = installerRequirementHelp($check);
            throw new InstallerFailure('Missing requirement: ' . $check['label'] . '.', $help);
        }
    }
    $steps[] = ['id' => 'requirements', 'label' => 'Environment verified'];
    $stage = 'connection';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $mysqli = mysqli_init(); $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 8);
    $mysqli->real_connect($data['databaseHost'], $data['databaseUser'], $data['databasePass'], null, (int)$data['databasePort']);
    $mysqli->set_charset('utf8mb4');
    $steps[] = ['id' => 'connection', 'label' => 'Database connection confirmed'];
    if ($action === 'test') {
        echo json_encode(['error' => false, 'msg' => 'Connection confirmed. No database, table, or file was created. Creation permissions will be checked during installation.', 'steps' => $steps], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stage = 'validation';
    $data['webSiteRootURL'] = installerValidateURL(trim(installerField($_POST, 'webSiteRootURL')), 'Network URL');
    $data['siteURL'] = installerValidateURL(trim(installerField($_POST, 'siteURL')), 'AVideo URL');
    $data['inputUser'] = trim(installerField($_POST, 'inputUser')); $data['inputPassword'] = installerField($_POST, 'inputPassword');
    if ($data['inputUser'] === '' || strlen($data['inputUser']) > 45 || $data['inputPassword'] === '') {
        throw new InstallerFailure('Enter the AVideo administrator username (up to 45 characters) and password.');
    }
    $encoders = [];
    foreach (preg_split('/\r\n|\r|\n/', installerField($_POST, 'allowedEncoders')) as $line) {
        if (trim($line) !== '') { $encoders[] = installerValidateURL(trim($line), 'Encoder URL'); }
    }
    $encoders = array_values(array_unique($encoders));
    $stage = 'streamer'; installerValidateStreamer($data);
    $steps[] = ['id' => 'streamer', 'label' => 'AVideo administrator verified'];
    $stage = 'configuration';
    $lock = @fopen(installerRoot() . '.installer.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new InstallerFailure('Unable to acquire the installation lock. Wait for any other installation to finish; if the issue persists, check permissions.', installerPermissionHelp());
    }
    if (installerConfigured()) { throw new InstallerFailure('Another installation has already created the configuration. Reload the page.'); }
    // Stage a PHP file in the same directory, never a publicly readable text file.
    $temporary = installerRoot() . '.configuration-' . bin2hex(random_bytes(12)) . '.php';
    $content = installerConfig($data);
    if (@file_put_contents($temporary, $content, LOCK_EX) !== strlen($content)) {
        throw new InstallerFailure('Unable to prepare configuration.php.', installerPermissionHelp());
    }
    if (PHP_OS_FAMILY !== 'Windows') { chmod($temporary, 0600); }
    $stage = 'database'; $db = '`' . $data['databaseName'] . '`';
    // An empty database provisioned by an administrator does not require CREATE privileges.
    try { $mysqli->select_db($data['databaseName']); }
    catch (mysqli_sql_exception $e) {
        if ($e->getCode() !== 1049) { throw $e; }
        $mysqli->query('CREATE DATABASE ' . $db . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $mysqli->select_db($data['databaseName']);
    }
    $steps[] = ['id' => 'database', 'label' => 'Database prepared'];
    $stage = 'tables';
    foreach ($mysqli->query('SHOW TABLES')->fetch_all(MYSQLI_NUM) as $table) {
        if (!in_array($table[0], ['streamers', 'encoders', 'scores'], true) ||
            $mysqli->query('SELECT 1 FROM `' . str_replace('`', '``', $table[0]) . '` LIMIT 1')->num_rows > 0) {
            throw new InstallerFailure('The selected database contains tables or data from another installation. No existing data was changed.',
                ['title' => 'Preserve existing data', 'text' => 'Choose a different database name in the form. To recover an installation, restore configuration.php from a backup instead of reinstalling.']);
        }
    }
    $mysqli->multi_query(file_get_contents(__DIR__ . '/database.sql'));
    do { if ($result = $mysqli->store_result()) { $result->free(); } }
    while ($mysqli->more_results() && $mysqli->next_result());
    foreach (['streamers' => 'id,siteURL,user,pass,created,modified', 'encoders' => 'id,siteURL,name,streamers_id,description,created,modified', 'scores' => 'id,streamers_id,encoders_id,score,comment,created,modified'] as $table => $columns) {
        $mysqli->query('SELECT ' . $columns . ' FROM `' . $table . '` LIMIT 0');
    }
    foreach ($mysqli->query('SHOW TABLE STATUS')->fetch_all(MYSQLI_ASSOC) as $table) {
        if (strcasecmp($table['Engine'] ?? '', 'InnoDB') !== 0) {
            throw new InstallerFailure('Tables must use InnoDB to support rollback if an error occurs.',
                ['title' => 'Review the selected database', 'text' => 'Choose an empty database with a different name or ask your administrator to review the existing tables. Setup does not convert tables automatically.']);
        }
    }
    $steps[] = ['id' => 'tables', 'label' => 'All 3 tables were created and verified'];
    $stage = 'records'; $mysqli->begin_transaction();
    $statement = $mysqli->prepare('INSERT INTO streamers (siteURL, user, pass, created, modified) VALUES (?, ?, ?, NOW(), NOW())');
    // AVideo integration expects this legacy credential format.
    // serverStatus authenticates with encodedPass=true, using the Streamer hash.
    $password = md5(hash('whirlpool', sha1($data['inputPassword'])));
    $statement->bind_param('sss', $data['siteURL'], $data['inputUser'], $password); $statement->execute();
    $streamerId = $mysqli->insert_id;
    $statement = $mysqli->prepare('INSERT INTO encoders (name, siteURL, streamers_id, created, modified) VALUES (?, ?, ?, NOW(), NOW())');
    foreach ($encoders as $url) {
        $name = parse_url($url, PHP_URL_HOST);
        $statement->bind_param('ssi', $name, $url, $streamerId); $statement->execute();
    }
    $mysqli->commit(); $committed = true;
    $steps[] = ['id' => 'records', 'label' => 'AVideo site and ' . count($encoders) . ' encoder(s) registered'];
    $stage = 'configuration';
    if (installerConfigured() || !@rename($temporary, installerRoot() . 'configuration.php')) {
        $command = PHP_OS_FAMILY === 'Windows' ? 'Move-Item -LiteralPath ' . installerQuote($temporary) . ' -Destination ' . installerQuote(installerRoot() . 'configuration.php') . '' : 'mv -n -- ' . installerQuote($temporary) . ' ' . installerQuote(installerRoot() . 'configuration.php');
        throw new InstallerFailure('The database was installed, but configuration.php could not be published. The prepared file has been preserved.',
            ['title' => 'Finish saving the configuration on the server', 'text' => 'Run the command below using the account that manages the application. Do not reinstall the database. Then reload this page.', 'command' => $command]);
    }
    $temporary = null;
    $steps[] = ['id' => 'configuration', 'label' => 'configuration.php created successfully'];
    echo json_encode(['error' => false, 'installed' => true, 'msg' => 'Installation complete. Your encoder network is ready.', 'steps' => $steps, 'url' => $data['webSiteRootURL']], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($mysqli && !$committed) { try { $mysqli->rollback(); } catch (Throwable $ignored) { error_log('AVideo installer: rollback failed (' . $ignored->getCode() . ')'); } }
    $help = $e instanceof InstallerFailure ? $e->help : []; $message = $e->getMessage();
    if ($e instanceof mysqli_sql_exception) {
        $help = installerDatabaseHelp($e->getCode(), $data);
        $message = installerDatabaseMessage($e->getCode());
    } elseif (!$e instanceof InstallerFailure) {
        $message = 'Unable to complete this step. Check the PHP error log on the server.';
        $help = ['title' => 'PHP diagnostics', 'text' => 'Check the error_log configured in the Apache/PHP-FPM php.ini. Fix the cause and try again.', 'command' => "php --ini\nphp -l " . installerQuote(__FILE__)];
        error_log('AVideo installer: ' . get_class($e) . ' at ' . $e->getFile() . ':' . $e->getLine());
    }
    foreach (['databasePass', 'inputPassword'] as $key) {
        if (!empty($data[$key])) { $message = str_replace($data[$key], '[hidden]', $message); }
    }
    echo json_encode(['error' => true, 'msg' => $message, 'stage' => $stage, 'steps' => $steps, 'help' => $help,
        'note' => !$committed && in_array($stage, ['tables', 'records'], true) ? 'Empty tables may have been created. Records from this attempt were rolled back; fix the cause, then try again.' : ''], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} finally {
    if ($temporary && !$committed && is_file($temporary)) { unlink($temporary); }
    if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
}
