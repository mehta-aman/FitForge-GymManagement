<?php
require_once 'include/config.php';
$filter = $_GET['filter'] ?? ($_GET['type'] ?? 'all');
$mapping = [
    'unpaid' => 'new',
    'new' => 'new',
    'partial' => 'partial',
    'paid' => 'full',
    'full' => 'full',
    'all' => 'all'
];
$targetType = $mapping[$filter] ?? 'all';
$query = $_GET;
unset($query['filter']);
$query['type'] = $targetType;
header("Location: booking-history.php?" . http_build_query($query));
exit();
