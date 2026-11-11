<?php
/**
 * Test Suite: Admin Member Profile Warning & Error Resolution
 * Validates that member-profile.php renders cleanly with zero PHP warnings or deprecation notices.
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
echo "RUNNING ADMIN MEMBER PROFILE WARNING & ERROR RESOLUTION SUITE\n";
echo "========================================================\n\n";

// 1. Authenticate as Admin
$cookie = tempnam(sys_get_temp_dir(), 'adm_cookie_');
$ch = curl_init("http://127.0.0.1:8080/admin/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
$page = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $page, $mCsrf);
$csrf = $mCsrf[1] ?? '';

$ch = curl_init("http://127.0.0.1:8080/admin/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $csrf,
    'email' => 'admin@gmail.com',
    'password' => 'Test@123',
    'submit' => '1'
]));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_exec($ch);
curl_close($ch);

// 2. Test multiple member profiles (Member 1, Member 2, Member 6, Member 36)
$memberIds = [1, 2, 6, 36];

foreach ($memberIds as $mid) {
    $ch = curl_init("http://127.0.0.1:8080/admin/member-profile.php?id=" . $mid);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    assert_test($code === 200, "Member Profile #$mid loads with HTTP 200");
    assert_test(strpos($html, 'Undefined array key "titlename"') === false, "Member Profile #$mid has no 'Undefined array key \"titlename\"' warning");
    assert_test(strpos($html, 'Passing null to parameter #1 ($string) of type string is deprecated') === false, "Member Profile #$mid has no htmlspecialchars null deprecation warning");
    assert_test(strpos($html, '<b>Warning</b>') === false && strpos($html, '<b>Deprecated</b>') === false, "Member Profile #$mid has zero PHP warnings or deprecation notices");
    assert_test(strpos($html, 'CURRENT MEMBERSHIP') !== false, "Member Profile #$mid renders CURRENT MEMBERSHIP section");
}

@unlink($cookie);

echo "\n========================================================\n";
echo "MEMBER PROFILE TEST SUITE SUMMARY:\n";
echo "PASSED: $passCount\n";
echo "FAILED: $failCount\n";
echo "========================================================\n";

if ($failCount > 0) exit(1);
