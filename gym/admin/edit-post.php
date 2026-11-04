<?php
require_once 'include/config.php';
$pid = intval($_GET['pid'] ?? 0);
header("location: plan-edit.php" . ($pid > 0 ? "?id=$pid" : ""));
exit();
