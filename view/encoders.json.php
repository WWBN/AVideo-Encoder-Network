<?php
require_once dirname(__FILE__) . '/../configuration.php';
require_once $global['systemRootPath'] . 'objects/Encoder.php';
header('Content-Type: application/json');
$rows = Encoder::getAll();
$total = Encoder::getTotal();

echo sprintf(
    '{"current": %1$s, "rowCount": %2$s, "total": %3$s, "rows": %4$s}',
    $_POST['current'],
    $_POST['rowCount'],
    $total,
    json_encode($rows)
);
