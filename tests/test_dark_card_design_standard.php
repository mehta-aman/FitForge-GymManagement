<?php
/**
 * Test Suite: Unified Dark Glass Card Design Consistency
 * Validates that all darker cards across the website adhere to the Fitness Metrics card design reference.
 */

require_once __DIR__ . '/../gym/include/config.php';

$passCount = 0;
$failCount = 0;

function assert_test($condition, $message) {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] $message\n";
        $passCount++;
    } else {
        echo "  [FAIL] $message\n";
        $failCount++;
    }
}

echo "========================================================\n";
echo "RUNNING UNIFIED DARK GLASS CARD DESIGN CONSISTENCY SUITE\n";
echo "========================================================\n\n";

// --- Section 1: Core Design System Tokens in ios-glass.css ---
echo "--- Section 1: Design Tokens in css/ios-glass.css ---\n";
$cssFile = file_get_contents(__DIR__ . '/../gym/css/ios-glass.css');

assert_test(strpos($cssFile, '--bg-card-dark: rgba(15, 23, 42, 0.65);') !== false, "1a. CSS variable --bg-card-dark defined with Fitness Metrics baseline (rgba(15, 23, 42, 0.65))");
assert_test(strpos($cssFile, '--glass-border-dark: rgba(255, 255, 255, 0.08);') !== false, "1b. CSS variable --glass-border-dark defined (rgba(255, 255, 255, 0.08))");
assert_test(strpos($cssFile, '--glass-shadow-dark: 0 10px 25px rgba(0, 0, 0, 0.45);') !== false, "1c. CSS variable --glass-shadow-dark defined");
assert_test(strpos($cssFile, '.ios-dark-card') !== false && strpos($cssFile, '.dark-card') !== false, "1d. Universal .ios-dark-card and .dark-card utility classes defined");
assert_test(strpos($cssFile, '.ios-glass') !== false, "1e. Base .ios-glass panel standardized to dark glass");
assert_test(strpos($cssFile, '.ios-pricing-card') !== false, "1f. .ios-pricing-card updated to dark glass");
assert_test(strpos($cssFile, '.ios-glass-table-wrapper') !== false, "1g. .ios-glass-table-wrapper updated to dark glass");
assert_test(strpos($cssFile, '.ios-auth-card') !== false, "1h. .ios-auth-card updated to dark glass");

// --- Section 2: Fitness Metrics Page (Reference Benchmark) ---
echo "\n--- Section 2: Fitness Metrics Benchmark in profile.php ---\n";
$profilePhp = file_get_contents(__DIR__ . '/../gym/profile.php');

assert_test(strpos($profilePhp, '.metric-card {') !== false, "2a. .metric-card defined in profile.php");
assert_test(strpos($profilePhp, 'background: rgba(15, 23, 42, 0.65);') !== false, "2b. .metric-card utilizes rgba(15, 23, 42, 0.65)");
assert_test(strpos($profilePhp, 'border: 1px solid rgba(255, 255, 255, 0.08);') !== false, "2c. .metric-card utilizes 1px border rgba(255, 255, 255, 0.08)");
assert_test(strpos($profilePhp, 'border-radius: 18px;') !== false, "2d. .metric-card utilizes 18px border radius");
assert_test(strpos($profilePhp, '.exercise-catalog-card {') !== false && strpos($profilePhp, 'background: rgba(15, 23, 42, 0.65);') !== false, "2e. .exercise-catalog-card matches .metric-card dark glass styling");

// --- Section 3: Member Welcome Dashboard (user-dashboard.php) ---
echo "\n--- Section 3: Member Dashboard in user-dashboard.php ---\n";
$dashPhp = file_get_contents(__DIR__ . '/../gym/user-dashboard.php');

assert_test(strpos($dashPhp, '.kpi-tile {') !== false && strpos($dashPhp, 'background: rgba(15, 23, 42, 0.65);') !== false, "3a. .kpi-tile matches .metric-card dark glass styling");
assert_test(strpos($dashPhp, '.coach-card {') !== false && strpos($dashPhp, 'background: rgba(15, 23, 42, 0.65);') !== false, "3b. .coach-card matches .metric-card dark glass styling");
assert_test(strpos($dashPhp, '.workout-day-card {') !== false && strpos($dashPhp, 'background: rgba(15, 23, 42, 0.65);') !== false, "3c. .workout-day-card matches .metric-card dark glass styling");
assert_test(strpos($dashPhp, '.digital-pass {') !== false && strpos($dashPhp, 'rgba(15, 23, 42,') !== false, "3d. .digital-pass matches dark glass styling");

// --- Section 4: Lost & Found Hub (lost-found.php) ---
echo "\n--- Section 4: Lost & Found Hub in lost-found.php ---\n";
$lfPhp = file_get_contents(__DIR__ . '/../gym/lost-found.php');

assert_test(strpos($lfPhp, '.lf-card {') !== false && strpos($lfPhp, 'background: rgba(15, 23, 42, 0.65);') !== false, "4a. .lf-card matches .metric-card dark glass styling");
assert_test(strpos($lfPhp, 'border: 1px solid rgba(255, 255, 255, 0.08);') !== false, "4b. .lf-card uses 1px solid rgba(255, 255, 255, 0.08)");

// --- Section 5: Auth & Scanner Pages (login.php, registration.php, scan.php, checkin.php) ---
echo "\n--- Section 5: Auth, Scan & Check-in Cards ---\n";
$loginPhp = file_get_contents(__DIR__ . '/../gym/login.php');
$regPhp = file_get_contents(__DIR__ . '/../gym/registration.php');
$scanPhp = file_get_contents(__DIR__ . '/../gym/scan.php');
$checkinPhp = file_get_contents(__DIR__ . '/../gym/checkin.php');

assert_test(strpos($loginPhp, 'rgba(15, 23, 42,') !== false && strpos($loginPhp, 'rgba(255, 255, 255, 0.08)') !== false, "5a. login.php .ios-auth-card matches dark glass styling");
assert_test(strpos($regPhp, 'rgba(15, 23, 42,') !== false && strpos($regPhp, 'rgba(255, 255, 255, 0.08)') !== false, "5b. registration.php .ios-auth-card matches dark glass styling");
assert_test(strpos($scanPhp, 'rgba(15, 23, 42,') !== false && strpos($scanPhp, 'rgba(255, 255, 255, 0.08)') !== false, "5c. scan.php .scanner-card matches dark glass styling");
assert_test(strpos($checkinPhp, 'rgba(15, 23, 42,') !== false && strpos($checkinPhp, 'rgba(255, 255, 255, 0.08)') !== false, "5d. checkin.php .checkin-card matches dark glass styling");

// --- Section 6: Live HTTP Endpoint Render Verification ---
echo "\n--- Section 6: Live HTTP Endpoint Verification ---\n";
$pages = [
    'user-dashboard.php' => 'User Dashboard',
    'profile.php' => 'Member Profile',
    'lost-found.php' => 'Lost & Found',
    'Booking-History.php' => 'Booking History',
    'index.php?public=1' => 'Public Homepage (Preview)',
    'about.php' => 'About Us',
    'contact.php' => 'Contact Us',
];

$cookie = tempnam(sys_get_temp_dir(), 'test_mem_');
$ch = curl_init("http://127.0.0.1:8080/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
$loginPage = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $loginPage, $mCsrf);
$csrfToken = $mCsrf[1] ?? '';

$ch = curl_init("http://127.0.0.1:8080/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'email' => 'atul@gmail.com',
    'password' => 'Test@123',
    'portal' => 'member',
    'login' => '1',
    'csrf_token' => $csrfToken
]));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_exec($ch);
curl_close($ch);

foreach ($pages as $uri => $name) {
    $ch = curl_init("http://127.0.0.1:8080/" . $uri);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    assert_test($code === 200, "6. $name ($uri) loads successfully with HTTP 200");
}
@unlink($cookie);

echo "\n========================================================\n";
echo "DARK GLASS CARD DESIGN CONSISTENCY SUMMARY:\n";
echo "PASSED: $passCount\n";
echo "FAILED: $failCount\n";
echo "========================================================\n";

if ($failCount > 0) exit(1);
