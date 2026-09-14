<?php
define('INSTALLER_PRODUCT', 'Network');
// Setup must work without loading the application's database configuration.
function installerRoot() { return str_replace('\\', '/', dirname(__DIR__)) . '/'; }
function installerConfigured() {
    clearstatcache();
    $file = installerRoot() . 'configuration.php';
    return is_file($file) && filesize($file) > 0;
}
function installerSession() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict',
            'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    }
    if (empty($_SESSION['installerToken'])) { $_SESSION['installerToken'] = bin2hex(random_bytes(32)); }
}
function installerURL() {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $path = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php')));
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim($path, '/') . '/';
}
function installerQuote($value) {
    return "'" . str_replace("'", PHP_OS_FAMILY === 'Windows' ? "''" : "'\"'\"'", $value) . "'";
}
function installerPermissionHelp() {
    $root = installerQuote(rtrim(installerRoot(), '/'));
    if (PHP_OS_FAMILY === 'Windows') {
        return ['title' => 'PowerShell as administrator',
            'text' => 'Replace APACHE_ACCOUNT with the account running Apache (Services > Apache > Log On; if XAMPP was started manually, use whoami). Then try again.',
            'command' => 'icacls ' . $root . ' /grant "APACHE_ACCOUNT:(OI)(CI)M"'];
    }
    return ['title' => 'Server terminal', 'text' => 'Replace PHP_USER with the Apache/PHP-FPM account (for example, www-data). Grant access only to that account and try again.',
        'command' => "sudo apt-get install acl\nsudo setfacl -m u:PHP_USER:rwx " . $root];
}
function installerChecks() {
    return [
        ['label' => 'PHP 7.4 or later', 'ok' => version_compare(PHP_VERSION, '7.4', '>='), 'detail' => PHP_VERSION],
        ['label' => 'MySQLi', 'ok' => extension_loaded('mysqli'), 'detail' => 'MySQL / MariaDB'],
        ['label' => 'cURL', 'ok' => extension_loaded('curl'), 'detail' => 'AVideo verification'],
        ['label' => 'Database schema', 'ok' => is_readable(__DIR__ . '/database.sql'), 'detail' => 'install/database.sql'],
        ['label' => 'Configuration write access', 'ok' => is_writable(installerRoot()), 'detail' => 'Application directory'],
    ];
}
class InstallerFailure extends RuntimeException {
    public $help;
    public function __construct($message, array $help = []) { parent::__construct($message); $this->help = $help; }
}
function installerField(array $data, $key, $default = '') {
    $value = $data[$key] ?? $default;
    if (!is_string($value) || strlen($value) > 8192 || strpos($value, "\0") !== false) {
        throw new InstallerFailure('Invalid value for field ' . $key . '.');
    }
    return $value;
}
function installerValidateURL($url, $label) {
    $parts = parse_url($url);
    if (!filter_var($url, FILTER_VALIDATE_URL) || !$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) ||
        isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || strlen($url) > 254) {
        throw new InstallerFailure($label . ': enter a complete HTTP/HTTPS URL without credentials, query parameters, or a fragment.');
    }
    return rtrim($url, '/') . '/';
}
function installerDatabaseMessage($code) {
    // MySQL client errors can use the operating system language.
    $messages = [
        1044 => 'The database account does not have access to this database.',
        1045 => 'Access denied. Check the database username and password.',
        1049 => 'The requested database does not exist.',
        1050 => 'A table with this name already exists.',
        1054 => 'A required database column is missing or incompatible.',
        1062 => 'A duplicate record conflicts with an existing database key.',
        1064 => 'The database rejected a SQL statement. Check the schema and server compatibility.',
        1142 => 'The database account does not have permission to perform this operation.',
        1143 => 'The database account does not have permission to access a required column.',
        1146 => 'A required database table is missing.',
        1227 => 'The database account is missing a required privilege.',
        1273 => 'The database server does not support the requested collation.',
        2002 => 'Unable to connect to the database server. Check the service, host, and port.',
        2003 => 'The database server refused the connection. Check the service, host, and port.',
        2005 => 'The database hostname could not be resolved.',
        2006 => 'The database server closed the connection.',
        2013 => 'The database connection was lost during the operation.',
    ];
    return 'MySQL/MariaDB (' . $code . '): ' . ($messages[$code] ?? 'The database operation failed. Review the database server logs and schema using the diagnostic command below.');
}
function installerDatabaseHelp($code, array $data) {
    $host = $data['databaseHost']; $port = $data['databasePort']; $db = $data['databaseName'];
    $user = str_replace(["\\", "'"], ["\\\\", "''"], $data['databaseUser']);
    $client = 'mysql';
    if (PHP_OS_FAMILY === 'Windows') {
        // Under Apache PHP_BINARY may be httpd.exe, so also inspect the loaded ini location.
        foreach ([dirname(php_ini_loaded_file() ?: PHP_BINARY, 2), dirname(PHP_BINARY, 2)] as $base) {
            $candidate = str_replace('\\', '/', $base) . '/mysql/bin/mysql.exe';
            if (is_file($candidate)) { $client = '& ' . installerQuote($candidate); break; }
        }
    }
    $connect = $client . ' --host=' . installerQuote($host) . ' --port=' . $port . ' --user=' . installerQuote($data['databaseUser']) . ' --password';
    if (in_array($code, [1044, 1045, 1142, 1143, 1227], true)) {
        return ['title' => 'MySQL / MariaDB access',
            'text' => 'Check your username and password. Test the connection using the command below. If permissions are missing, a database administrator must run the SQL; replace ACCOUNT_HOST with the MySQL account host (usually localhost for a local connection). The terminal will prompt for the password.',
            'command' => $connect,
            'sql' => "CREATE DATABASE IF NOT EXISTS `" . $db . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\nGRANT ALL PRIVILEGES ON `" . $db . "`.* TO '" . $user . "'@'ACCOUNT_HOST';"];
    }
    if (in_array($code, [2002, 2003, 2005, 2006, 2013], true)) {
        return ['title' => 'Check the database service',
            'text' => PHP_OS_FAMILY === 'Windows' ? 'Start MySQL in the XAMPP control panel. Check the host and port; for a remote server, also check the firewall. Test the port in PowerShell.' : 'Check the host and port. For a local database, check the service below; for a remote server, also check the firewall.',
            'command' => PHP_OS_FAMILY === 'Windows' ? 'Test-NetConnection -ComputerName ' . installerQuote($host) . ' -Port ' . $port : "sudo systemctl status mariadb\n# If the service is named mysql:\nsudo systemctl status mysql"];
    }
    return ['title' => 'Database diagnostics', 'text' => 'Open the SQL client and review the error and database structure. Do not remove tables containing data. Fix the cause, then try again.', 'command' => $connect . ' ' . installerQuote($db)];
}
function installerConfig(array $data) {
    $content = "<?php\n\$global['disableAdvancedConfigurations'] = 0;\n\$global['videoStorageLimitMinutes'] = 0;\n";
    $content .= "\$global['webSiteRootURL'] = " . var_export($data['webSiteRootURL'], true) . ";\n";
    $content .= "\$global['systemRootPath'] = str_replace('\\\\', '/', __DIR__) . '/';\n\n";
    foreach (['mysqlHost' => 'databaseHost', 'mysqlPort' => 'databasePort', 'mysqlUser' => 'databaseUser', 'mysqlPass' => 'databasePass', 'mysqlDatabase' => 'databaseName'] as $variable => $key) {
        $content .= '$' . $variable . ' = ' . var_export($data[$key], true) . ";\n";
    }
    return $content . "\nrequire_once \$global['systemRootPath'] . 'objects/include_config.php';\n";
}
function installerValidateStreamer(array $data) {
    $curl = curl_init($data['siteURL'] . 'login');
    curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query([
        'user' => $data['inputUser'], 'pass' => md5(hash('whirlpool', sha1($data['inputPassword']))), 'encodedPass' => 'true']),
        CURLOPT_USERAGENT => 'AVideoEncoder Installer',
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 25,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false]);
    $body = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_HTTP_CODE); $errno = curl_errno($curl);
    curl_close($curl);
    $help = ['title' => 'Check the AVideo site', 'text' => 'Check the final URL (without redirects) and administrator credentials. For certificate errors, fix the server TLS certificate chain or configure curl.cainfo in php.ini and restart Apache.',
        'command' => 'curl --head ' . installerQuote($data['siteURL'] . 'login')];
    if ($body === false || $status < 200 || $status >= 300) {
        throw new InstallerFailure('Unable to verify the AVideo site (HTTP ' . $status . ', cURL ' . $errno . ').', $help);
    }
    $response = json_decode($body, true);
    if (!is_array($response) || !in_array($response['isAdmin'] ?? false, [true, 1, '1'], true)) {
        throw new InstallerFailure('The AVideo site did not confirm administrator access. Check the URL, username, and password.', $help);
    }
}

require_once __DIR__ . '/ubuntu-help-functions.php';
