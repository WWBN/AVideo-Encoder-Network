<?php
$config = dirname(__DIR__) . '/configuration.php';
if (!is_file($config) || !filesize($config)) {
    require_once dirname(__DIR__) . '/install/installer.php';
    header('Location: ' . installerURL() . 'install/index.php');
    exit;
}
require_once $config;
require_once dirname(__DIR__) . '/objects/Streamer.php';
require_once dirname(__DIR__) . '/objects/Login.php';
require_once dirname(__DIR__) . '/objects/functions.php';
require_once dirname(__DIR__) . '/objects/Encoder.php';
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
function networkEscape($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function networkURL($value) {
    return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($value, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true) ? addLastSlash($value) : '';
}
$streamerURL = networkURL($_REQUEST['webSiteRootURL'] ?? '');
if ($streamerURL && !empty($_REQUEST['user']) && !empty($_REQUEST['pass']) && empty($_REQUEST['justLogin'])) { Login::logoff(); }
if (!$streamerURL) { $streamerURL = networkURL(Streamer::getFirstURL()); }
$loggedIn = Login::canUpload();
$site = $loggedIn ? networkURL(Login::getStreamerURL()) : $streamerURL;
$siteHost = parse_url($site, PHP_URL_HOST) ?: 'Video site';
$base = $global['webSiteRootURL'];
$encoders = [];
if ($loggedIn) {
    foreach (Encoder::getAll() as $row) {
        $url = networkURL($row['siteURL']);
        $launchURL = $url ? $url . '?' . http_build_query(['noNavbar' => 1, 'webSiteRootURL' => $site,
            'user' => $_SESSION['login']->user ?? '', 'pass' => $_SESSION['login']->pass ?? '', 'encodedPass' => 'true']) : '';
        $encoders[] = ['id' => (int) $row['id'], 'name' => $row['name'] ?: (parse_url($url, PHP_URL_HOST) ?: 'Encoder'),
            'url' => $url, 'host' => parse_url($url, PHP_URL_HOST) ?: 'Invalid URL', 'description' => $row['description'] ?? '', 'launchURL' => $launchURL];
    }
}
$autoLogin = !$loggedIn && $streamerURL && !empty($_REQUEST['user']) && !empty($_REQUEST['pass']);
$bootstrap = ['base' => $base, 'encoders' => $encoders, 'autoLogin' => (bool) $autoLogin];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Monitor your encoder network, compare server capacity, and open your encoding workspace.">
    <title>Encoder Network</title>
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="<?= networkEscape($base) ?>view/css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
    <script id="networkConfig" type="application/json"><?= json_encode($bootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
    <script src="<?= networkEscape($base) ?>view/js/main.js?v=<?= filemtime(__DIR__ . '/js/main.js') ?>" defer></script>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="nav-caption">WORKSPACE</div>
        <nav aria-label="Main navigation">
            <a class="nav-item active" href="#overview" aria-current="page"><span class="nav-symbol" aria-hidden="true">▦</span> Overview</a>
            <?php if ($loggedIn): ?>
            <a class="nav-item" href="#encoders"><span class="nav-symbol" aria-hidden="true">▤</span> Encoders <span class="nav-count"><?= count($encoders) ?></span></a>
            <a class="nav-item" href="#guide"><span class="nav-symbol" aria-hidden="true">ⓘ</span> Network guide</a>
            <?php endif; ?>
        </nav>
    </aside>
    <div class="main-shell">
        <header class="topbar"><div class="breadcrumbs">Workspace <span>/</span> <strong><?= $loggedIn ? 'Network overview' : 'Sign in' ?></strong></div><div class="account"><span class="avatar"><?= networkEscape(strtoupper(substr($siteHost, 0, 1))) ?></span><div><strong><?= networkEscape($siteHost) ?></strong><small><?= $loggedIn ? (Login::isAdmin() ? 'Administrator' : 'Uploader') : 'Connected platform' ?></small></div><?php if ($loggedIn): ?><a class="signout" href="<?= networkEscape($base) ?>logoff" title="Sign out" aria-label="Sign out">↗</a><?php endif; ?></div></header>
        <main id="overview">
        <?php if (!$loggedIn): ?>
            <section class="login-layout"><div><div class="eyebrow">YOUR ENCODING WORKSPACE</div><h1>Encoding workspace</h1><p class="lead">Sign in with your account to compare encoders, check capacity, and start your next upload.</p><div class="login-features"><span>✓ Live server status</span><span>✓ Capacity at a glance</span><span>✓ Integrated encoding workspace</span></div></div><form id="loginForm" class="panel login-form"><span class="eyebrow">WELCOME BACK</span><h2>Sign in to your network</h2><p>Use an account with upload access.</p><label for="siteURL">Video site URL</label><input id="siteURL" type="url" value="<?= networkEscape($streamerURL) ?>" required placeholder="https://videos.example.com/"><label for="inputUser">Username</label><input id="inputUser" autocomplete="username" value="<?= networkEscape(is_string($_REQUEST['user'] ?? '') ? ($_REQUEST['user'] ?? '') : '') ?>" required><label for="inputPassword">Password</label><input id="inputPassword" type="password" autocomplete="current-password" value="<?= networkEscape(is_string($_REQUEST['pass'] ?? '') ? ($_REQUEST['pass'] ?? '') : '') ?>" required><p id="loginError" class="error-message" role="alert" hidden></p><button class="button primary" type="submit">Sign in <span aria-hidden="true">→</span></button></form></section>
        <?php else: ?>
            <div class="page-heading"><div><div class="eyebrow">YOUR INFRASTRUCTURE, AT A GLANCE</div><h1>Encoder overview<span class="heading-dot">.</span></h1><p>View encoder availability, capacity, and queues.</p></div><div class="heading-actions"><span id="refreshStatus" class="refresh-label" role="status">Waiting for first check</span><button id="refreshButton" class="button secondary"><span aria-hidden="true">↻</span> Refresh status</button></div></div>
            <div id="globalError" class="notice danger" role="alert" hidden></div>
            <section class="summary-grid" aria-label="Network summary">
                <article class="stat-card"><div class="stat-label">Available encoders <span class="stat-icon" aria-hidden="true">◉</span></div><div class="stat-value"><span id="onlineCount">—</span><small>/ <?= count($encoders) ?></small></div><p id="availabilityNote">Checking registered servers</p></article>
                <article class="stat-card"><div class="stat-label">Jobs in queue <span class="stat-icon" aria-hidden="true">≡</span></div><div class="stat-value" id="queueCount">—</div><p id="queueNote">Across responding encoders</p></article>
                <article class="stat-card"><div class="stat-label">Encoding now <span class="stat-icon" aria-hidden="true">▷</span></div><div class="stat-value" id="encodingCount">—</div><p id="encodingNote">Active encoding jobs</p></article>
                <article class="stat-card"><div class="stat-label">Concurrent capacity <span class="stat-icon" aria-hidden="true">⊞</span></div><div class="stat-value" id="capacityCount">—</div><p id="capacityNote">Parallel jobs on responding servers</p></article>
            </section>
            <section class="recommendation" aria-label="Recommended encoder"><div class="recommendation-icon" aria-hidden="true">✦</div><div><span class="eyebrow">SMART SELECTION</span><h2 id="recommendedName">Finding your best available encoder</h2><p id="recommendedReason">Comparing queue pressure, capacity, and response time.</p></div><button id="useRecommended" class="button dark" disabled>Open recommended <span aria-hidden="true">→</span></button></section>
            <section class="panel fleet-panel" id="encoders">
                <div class="panel-heading"><div><h2>Your encoders <span class="count-chip"><?= count($encoders) ?></span></h2><p>Open an encoder or its queue directly. Switching servers keeps your work open.</p></div><div class="table-tools"><label class="search-box"><span aria-hidden="true">⌕</span><input id="encoderSearch" type="search" placeholder="Search encoders…" aria-label="Search encoders"></label><select id="statusFilter" aria-label="Filter encoder status"><option value="all">All statuses</option><option value="online">Online</option><option value="attention">Needs attention</option></select></div></div>
                <div class="table-wrap"><table><thead><tr><th>Encoder</th><th>Status</th><th>Queue / capacity</th><th>Response</th><th>Upload limit</th><th>Open encoder</th></tr></thead><tbody id="encoderRows"></tbody></table></div>
                <div id="emptyFleet" class="empty-state" hidden><span aria-hidden="true">▤</span><h3>No encoders to show</h3><p id="emptyFleetText">Try another search or status filter.</p></div>
                <div class="table-footer"><span><i class="small-dot"></i> Auto-refresh every 30 seconds while this tab is active</span><span id="fleetCoverage">Status checks have not completed</span></div>
            </section>
            <section id="workspace" class="panel workspace-panel" hidden>
                <div class="workspace-tabs" id="workspaceTabs" role="tablist" aria-label="Encoder workspaces"></div>
                <div class="panel-heading"><div><span class="eyebrow">ENCODER &amp; SHARING QUEUE</span><h2 id="workspaceName">Encoder</h2><p id="workspaceStatus" role="status">Opening the encoder…</p></div>
                    <div class="workspace-actions"><a id="workspaceExternal" class="button primary" target="_blank" rel="noopener noreferrer">Open directly ↗</a><button id="retryWorkspace" class="button secondary">Reload encoder</button><button id="closeWorkspace" class="button secondary">Close this encoder</button></div>
                </div>
                <div id="workspaceWarning" class="workspace-warning" role="status" hidden></div>
                <div id="frameContainer"></div>
                <p class="workspace-help">The sharing queue is inside the encoder above. If it stays blank or cannot sign in, use <strong>Open directly</strong> to load it outside the iframe. Open encoders stay running when you switch tabs.</p>
            </section>
            <details class="advanced-details" id="advancedDetails"><summary>Server metrics &amp; diagnostics <span>Queue, memory, response time, and connection details</span></summary>
            <section id="encoderDetail" class="detail-grid" hidden>
                <article class="panel selected-panel"><div class="panel-heading"><div><span class="eyebrow">SELECTED ENCODER</span><h2 id="selectedName">Encoder details</h2><a id="selectedHost" target="_blank" rel="noopener noreferrer"></a></div><span id="selectedState" class="status-badge">Checking</span></div><div id="selectedNotice" class="detail-notice"></div><div class="detail-stats"><div><span>Queue</span><strong id="selectedQueue">—</strong></div><div><span>Concurrency</span><strong id="selectedCapacity">—</strong></div><div><span>Upload limit</span><strong id="selectedUpload">—</strong></div></div><div class="memory-row"><span>Memory usage</span><strong id="memoryPercent">Not reported</strong></div><div class="meter"><span id="memoryBar"></span></div><p class="memory-caption" id="memoryCaption">Memory metrics will appear when reported by the encoder.</p><div class="activity-row"><span>Encoding <b id="selectedEncoding">—</b></span><span>Downloading <b id="selectedDownloading">—</b></span><span>Transferring <b id="selectedTransferring">—</b></span></div><div class="selected-footer"><span id="encoderVersion">Version not reported</span><button id="openWorkspace" class="button primary">Open workspace <span aria-hidden="true">↗</span></button></div></article>
                <article class="panel response-panel"><div class="panel-heading"><div><h2>Status response time</h2><p>Round trip from this network server</p></div><span class="unit-chip">ms</span></div><div class="response-summary"><strong id="currentResponse">—</strong><span id="responseCaption">Waiting for a successful check</span></div><div id="responseChart" class="response-chart" role="img" aria-label="No response time samples yet"></div><div class="chart-axis"><span>Earlier checks</span><span>Latest check</span></div><p class="chart-note">Up to 20 successful checks from this visit. Includes status processing and authentication; this is not an ICMP ping.</p></article>
            </section>
            </details>
            <section class="guide-grid" id="guide"><div><span class="eyebrow">MAKE THE MOST OF YOUR NETWORK</span><h2>A clearer view of your workflow.</h2><p>Compare availability and capacity before choosing where to encode.</p></div><article><span class="guide-number">01</span><h3>Choose with confidence</h3><p>The recommendation compares queue size per concurrent slot, then capacity and response time. It is a starting point, not a completion-time estimate.</p></article><article><span class="guide-number">02</span><h3>Know what is available</h3><p>Only confirmed responses contribute to totals. A dash means a metric is unavailable, not zero. Check each encoder for access or connection issues.</p></article><article><span class="guide-number">03</span><h3>Keep your work moving</h3><p>Open an encoder or queue in one click. Open tabs retain their work when you switch. Use Open directly if your browser blocks the embedded login.</p></article></section>
        <?php endif; ?>
        <noscript><div class="notice danger">Enable JavaScript to sign in, check encoder status, and open workspaces.</div></noscript>
        </main>
    </div>
</div>
</body>
</html>
