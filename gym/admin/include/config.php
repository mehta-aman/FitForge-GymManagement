<?php
ob_start();
// DB credentials
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'gymdb');

// Establish database connection.
try {
    $dbh = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS, [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4'",
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    exit("Database Connection Error: " . $e->getMessage());
}

// Load Core Services from parent include
require_once __DIR__ . '/../../include/security.php';
require_once __DIR__ . '/../../include/settings.php';

// Initialize hardened session
init_secure_session();

// Preload settings into cache
load_all_settings($dbh);
