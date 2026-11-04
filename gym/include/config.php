<?php
ob_start();
// DB Connection
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

// Load Core Services
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/settings.php';

// Initialize hardened session
init_secure_session();

// Preload settings into cache
load_all_settings($dbh);

// Tenant Context Helper (Foundational Multi-Gym Readiness)
function get_current_gym_id() {
    if (!empty($_SESSION['gym_id'])) {
        return intval($_SESSION['gym_id']);
    }
    return 1; // Default tenant organization
}
