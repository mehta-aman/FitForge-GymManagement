<?php
/**
 * Test Suite: Gym Common QR Code Attendance & Station
 */
require_once __DIR__ . '/../gym/include/config.php';

echo "========================================================\n";
echo "RUNNING GYM QR ATTENDANCE TEST SUITE\n";
echo "========================================================\n";

$passCount = 0;
$failCount = 0;

function assert_test($condition, $name) {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] $name\n";
        $passCount++;
    } else {
        echo "  [FAIL] $name\n";
        $failCount++;
    }
}

// 1. Verify get_gym_checkin_token and URL helpers
$token = get_gym_checkin_token($dbh);
assert_test(!empty($token) && strlen($token) >= 8, "Gym Check-In Token generated properly ($token)");

$url = get_gym_checkin_url($dbh);
assert_test(str_contains($url, 'checkin.php') && str_contains($url, 'token=' . urlencode($token)), "Gym Check-In URL contains target script and token ($url)");

// 2. Clean test records for today for user 1
$dbh->exec("DELETE FROM tbl_attendance WHERE user_id = 1 AND DATE(check_in_time) = CURDATE()");

// 3. Test HTTP Simulation: Unauthenticated visitor hitting checkin.php
$ch = curl_init("http://127.0.0.1:8080/checkin.php?gym=1&token=" . urlencode($token));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert_test($httpCode === 302, "Unauthenticated checkin returns 302 redirect (Got HTTP $httpCode)");
assert_test(str_contains($response, 'login.php?redirect='), "Redirect targets login.php with checkin redirect query");

// 4. Test Invalid Token
$ch = curl_init("http://127.0.0.1:8080/checkin.php?gym=1&token=invalid_fake_token_123");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$body = curl_exec($ch);
curl_close($ch);
assert_test(str_contains($body, 'Invalid or Expired QR Code'), "Invalid token correctly triggers security rejection page");

// 5. Simulate Authenticated Member Session Check-In
// Log in as user 1
$cookieFile = tempnam(sys_get_temp_dir(), 'gym_cookie_');

// First fetch login page to get CSRF token
$ch = curl_init("http://127.0.0.1:8080/login.php?redirect=checkin.php%3Fgym%3D1%26token%3D" . urlencode($token));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
$loginPage = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $loginPage, $mCsrf);
$csrfToken = $mCsrf[1] ?? '';
assert_test(!empty($csrfToken), "CSRF token extracted from login page");

// POST login credentials
$postData = http_build_query([
    'csrf_token' => $csrfToken,
    'email' => 'atul@gmail.com',
    'password' => 'Test@123',
    'redirect' => 'checkin.php?gym=1&token=' . $token
]);

$ch = curl_init("http://127.0.0.1:8080/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$checkinResultHtml = curl_exec($ch);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

assert_test(str_contains($finalUrl, 'checkin.php'), "Login seamlessly forwards to checkin.php ($finalUrl)");
assert_test(str_contains($checkinResultHtml, 'Check-In Confirmed!'), "Check-In Confirmed screen rendered successfully for member");
assert_test(stripos($checkinResultHtml, 'Aman') !== false || stripos($checkinResultHtml, 'atul') !== false, "Member name dynamically personalized on check-in card");

// Verify record in Database
$stmt = $dbh->prepare("SELECT * FROM tbl_attendance WHERE user_id = 1 AND DATE(check_in_time) = CURDATE() ORDER BY id DESC LIMIT 1");
$stmt->execute();
$attRow = $stmt->fetch(PDO::FETCH_ASSOC);
assert_test($attRow && $attRow['check_in_method'] === 'qr_scan', "Database entry created with method 'qr_scan' (ID: {$attRow['id']})");
assert_test(empty($attRow['check_out_time']), "Check-out time is NULL (member is active on floor)");

// 6. Test Duplicate Scan (Scan again while active on floor)
$ch = curl_init("http://127.0.0.1:8080/checkin.php?gym=1&token=" . urlencode($token));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$rescanHtml = curl_exec($ch);
curl_close($ch);

assert_test(str_contains($rescanHtml, "You're Checked In!"), "Re-scan detects member is already active on floor");
assert_test(str_contains($rescanHtml, "Floor Time"), "Floor time elapsed widget rendered on re-scan");

$countStmt = $dbh->query("SELECT COUNT(*) FROM tbl_attendance WHERE user_id = 1 AND DATE(check_in_time) = CURDATE()");
$totalRecordsToday = intval($countStmt->fetchColumn());
assert_test($totalRecordsToday === 1, "Duplicate prevention verified: exactly 1 attendance record exists (Count: $totalRecordsToday)");

// 7. Test Check-Out from Check-In page
preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $rescanHtml, $mCheckOutCsrf);
$checkoutCsrf = $mCheckOutCsrf[1] ?? '';

$coPost = http_build_query([
    'csrf_token' => $checkoutCsrf,
    'action' => 'checkout',
    'attendance_id' => $attRow['id']
]);

$ch = curl_init("http://127.0.0.1:8080/checkin.php?gym=1&token=" . urlencode($token));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $coPost);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$checkoutResultHtml = curl_exec($ch);
curl_close($ch);

assert_test(str_contains($checkoutResultHtml, 'Workout Completed!'), "Check-out executed and workout completed screen shown");

// Verify DB check_out_time is now filled
$stmt->execute();
$updatedAttRow = $stmt->fetch(PDO::FETCH_ASSOC);
assert_test(!empty($updatedAttRow['check_out_time']), "Database check_out_time successfully populated ({$updatedAttRow['check_out_time']})");

// 8. Test Admin Gym QR Page Accessibility
$adminCookie = tempnam(sys_get_temp_dir(), 'admin_cookie_');

// Get admin login CSRF
$ch = curl_init("http://127.0.0.1:8080/admin/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $adminCookie);
$adminLoginPage = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $adminLoginPage, $mAdmCsrf);
$admCsrf = $mAdmCsrf[1] ?? '';

$ch = curl_init("http://127.0.0.1:8080/admin/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $admCsrf,
    'email' => 'admin@gmail.com',
    'password' => 'Test@123',
    'submit' => '1'
]));
curl_setopt($ch, CURLOPT_COOKIEFILE, $adminCookie);
curl_setopt($ch, CURLOPT_COOKIEJAR, $adminCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_exec($ch);
curl_close($ch);

// Fetch admin gym-qr.php
$ch = curl_init("http://127.0.0.1:8080/admin/gym-qr.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $adminCookie);
$gymQrHtml = curl_exec($ch);
curl_close($ch);

assert_test(str_contains($gymQrHtml, 'Gym Self Check-In Station') && str_contains($gymQrHtml, 'QR Poster'), "Admin Gym QR Hub renders successfully");
assert_test(str_contains($gymQrHtml, 'Print Official Poster'), "Print Official Poster action button available");
assert_test(str_contains($gymQrHtml, 'Download PNG'), "Download PNG high-resolution generator available");
assert_test(str_contains($gymQrHtml, 'atul'), "Today's check-in feed displays member attendance");

// 9. Fetch Standalone Poster Pop-out
$ch = curl_init("http://127.0.0.1:8080/admin/gym-qr.php?poster_only=1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $adminCookie);
$posterOnlyHtml = curl_exec($ch);
curl_close($ch);

assert_test(str_contains($posterOnlyHtml, 'FAST ATTENDANCE STATION'), "Standalone poster contains FAST ATTENDANCE STATION badge");
assert_test(str_contains($posterOnlyHtml, 'qrcode.min.js'), "Standalone poster loads local qrcode.min.js");

@unlink($cookieFile);
@unlink($adminCookie);

echo "\n========================================================\n";
echo "SUMMARY: Passed: $passCount, Failed: $failCount\n";
echo "========================================================\n";

if ($failCount > 0) {
    exit(1);
}
