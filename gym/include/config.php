<?php
ob_start();
// DB Credentials & Cloud Environment Configuration
$dbHost = getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : 'localhost');
$dbPort = getenv('DB_PORT') ?: '3306';
$dbUser = getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : 'root');
$dbPass = (getenv('DB_PASS') !== false) ? getenv('DB_PASS') : ((getenv('DB_PASSWORD') !== false) ? getenv('DB_PASSWORD') : (defined('DB_PASS') ? DB_PASS : ''));
$dbName = getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : 'gymdb');

$databaseUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
if (!empty($databaseUrl)) {
    $parsed = parse_url($databaseUrl);
    if ($parsed) {
        $dbHost = $parsed['host'] ?? $dbHost;
        $dbPort = $parsed['port'] ?? $dbPort;
        $dbUser = isset($parsed['user']) ? urldecode($parsed['user']) : $dbUser;
        $dbPass = isset($parsed['pass']) ? urldecode($parsed['pass']) : $dbPass;
        $dbName = !empty($parsed['path']) ? ltrim($parsed['path'], '/') : $dbName;
    }
}

if (!defined('DB_HOST')) define('DB_HOST', $dbHost);
if (!defined('DB_PORT')) define('DB_PORT', $dbPort);
if (!defined('DB_USER')) define('DB_USER', $dbUser);
if (!defined('DB_PASS')) define('DB_PASS', $dbPass);
if (!defined('DB_NAME')) define('DB_NAME', $dbName);

// Establish database connection.
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $dbh = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4'",
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    exit("Database Connection Error: " . $e->getMessage());
}

// Load Core Services
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/membership_engine.php';

// Initialize hardened session
init_secure_session();

// Preload settings into cache
load_all_settings($dbh);

// Tenant Context Helper (Foundational Multi-Gym Readiness)
if (!function_exists('get_current_gym_id')) {
    function get_current_gym_id() {
        if (!empty($_SESSION['gym_id'])) {
            return intval($_SESSION['gym_id']);
        }
        return 1; // Default tenant organization
    }
}
