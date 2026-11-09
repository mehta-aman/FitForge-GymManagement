<?php
/**
 * Test Suite: Multi-Role Staff RBAC, Owner Approval Queue & In-App QR Scanner
 */
require_once __DIR__ . '/../gym/include/config.php';
require_once __DIR__ . '/../gym/admin/include/rbac.php';

echo "========================================================\n";
echo "RUNNING PHASE 3: RBAC & IN-APP SCANNER TEST SUITE\n";
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

// 1. Clean test data
$dbh->exec("DELETE FROM tbladmin WHERE email IN ('coach_test@fitforge.local', 'floor_test@fitforge.local', 'manager_test@fitforge.local')");
$dbh->exec("DELETE FROM tbl_trainers WHERE email = 'coach_test@fitforge.local'");

// 2. Test Staff Registration (Prospective Trainer)
$cookieFile = tempnam(sys_get_temp_dir(), 'test_reg_cookie_');

// Fetch register.php for CSRF
$ch = curl_init("http://127.0.0.1:8080/admin/register.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
$regPage = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $regPage, $mCsrf);
$csrf = $mCsrf[1] ?? '';
assert_test(!empty($csrf), "CSRF token extracted from staff registration page");

// Submit Trainer registration
$postData = http_build_query([
    'csrf_token' => $csrf,
    'name' => 'Coach Vikram Test',
    'email' => 'coach_test@fitforge.local',
    'mobile' => '9876500001',
    'requested_role' => 'trainer',
    'speciality' => 'Olympic Weightlifting',
    'bio' => 'Certified strength coach',
    'password' => 'Coach@123',
    'repeat_password' => 'Coach@123'
]);

$ch = curl_init("http://127.0.0.1:8080/admin/register.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, true);
$regRes = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert_test($httpCode === 302, "Staff registration redirects on submission (Got HTTP $httpCode)");

// Verify record in Database has status = 'pending'
$stStmt = $dbh->prepare("SELECT * FROM tbladmin WHERE email = 'coach_test@fitforge.local'");
$stStmt->execute();
$coachRow = $stStmt->fetch(PDO::FETCH_ASSOC);
assert_test($coachRow && $coachRow['status'] === 'pending', "Applicant record inserted with status = 'pending'");
assert_test($coachRow['role'] === 'trainer', "Applicant role recorded as 'trainer'");
assert_test(!empty($coachRow['trainer_id']), "Applicant linked to pre-created trainer profile in tbl_trainers (ID: {$coachRow['trainer_id']})");

// 3. Test That Pending Staff Member Cannot Log In
$ch = curl_init("http://127.0.0.1:8080/admin/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
$loginPage = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $loginPage, $mLogCsrf);
$logCsrf = $mLogCsrf[1] ?? '';

$loginPost = http_build_query([
    'csrf_token' => $logCsrf,
    'email' => 'coach_test@fitforge.local',
    'password' => 'Coach@123',
    'submit' => '1'
]);

$ch = curl_init("http://127.0.0.1:8080/admin/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $loginPost);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
$loginAttemptRes = curl_exec($ch);
curl_close($ch);

assert_test(str_contains($loginAttemptRes, 'pending approval by the Gym Owner'), "Pending employee blocked from login with clear owner approval alert");

// 4. Test Owner Logs In and Approves Staff Member
$ownerCookie = tempnam(sys_get_temp_dir(), 'test_owner_cookie_');

$ch = curl_init("http://127.0.0.1:8080/admin/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $ownerCookie);
$ownerLoginPage = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $ownerLoginPage, $mOwnerCsrf);
$ownerCsrf = $mOwnerCsrf[1] ?? '';

$ch = curl_init("http://127.0.0.1:8080/admin/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $ownerCsrf,
    'email' => 'admin@gmail.com',
    'password' => 'Test@123',
    'submit' => '1'
]));
curl_setopt($ch, CURLOPT_COOKIEFILE, $ownerCookie);
curl_setopt($ch, CURLOPT_COOKIEJAR, $ownerCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_exec($ch);
curl_close($ch);

// Verify Owner can access staff.php
$ch = curl_init("http://127.0.0.1:8080/admin/staff.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $ownerCookie);
$staffHubHtml = curl_exec($ch);
curl_close($ch);

assert_test(str_contains($staffHubHtml, 'Staff Management &amp; Approvals') || str_contains($staffHubHtml, 'Staff Management & Approvals'), "Owner successfully accesses Staff Management & Approvals Hub");
assert_test(str_contains($staffHubHtml, 'Coach Vikram Test'), "Pending applicant visible in Owner Approval Queue");

// Owner Approves the Trainer
preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $staffHubHtml, $mApproveCsrf);
$approveCsrf = $mApproveCsrf[1] ?? '';

$approvePost = http_build_query([
    'csrf_token' => $approveCsrf,
    'action' => 'approve_staff',
    'staff_id' => $coachRow['id'],
    'assigned_role' => 'trainer'
]);

$ch = curl_init("http://127.0.0.1:8080/admin/staff.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $approvePost);
curl_setopt($ch, CURLOPT_COOKIEFILE, $ownerCookie);
$approveResHtml = curl_exec($ch);
curl_close($ch);

assert_test(str_contains($approveResHtml, 'APPROVED as Fitness Coach'), "Owner approval action confirmed via feedback banner");

// Verify in DB
$stStmt->execute();
$updatedCoach = $stStmt->fetch(PDO::FETCH_ASSOC);
assert_test($updatedCoach['status'] === 'approved', "Staff status updated to 'approved' in database");
assert_test($updatedCoach['approved_by'] == 1, "approved_by recorded as Owner ID 1");
assert_test(!empty($updatedCoach['approved_at']), "approved_at timestamp recorded");

// 5. Test Approved Staff Can Now Successfully Log In
$trainerCookie = tempnam(sys_get_temp_dir(), 'test_trainer_cookie_');

$ch = curl_init("http://127.0.0.1:8080/admin/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $trainerCookie);
$tLoginPage = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $tLoginPage, $mTCsrf);
$tCsrf = $mTCsrf[1] ?? '';

$ch = curl_init("http://127.0.0.1:8080/admin/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $tCsrf,
    'email' => 'coach_test@fitforge.local',
    'password' => 'Coach@123',
    'submit' => '1'
]));
curl_setopt($ch, CURLOPT_COOKIEFILE, $trainerCookie);
curl_setopt($ch, CURLOPT_COOKIEJAR, $trainerCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$tDashboardHtml = curl_exec($ch);
$tFinalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

assert_test(str_contains($tFinalUrl, 'index.php'), "Approved trainer logs in successfully and lands on Dashboard ($tFinalUrl)");
assert_test(str_contains($tDashboardHtml, 'Trainer') || str_contains($tDashboardHtml, 'Coach'), "Trainer role badge visible in authenticated view");

// 6. Test Route Gatekeeping: Trainer blocked from Owner & Financial pages
// A. Try accessing settings.php
$ch = curl_init("http://127.0.0.1:8080/admin/settings.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $trainerCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$gateRes1 = curl_exec($ch);
$gateUrl1 = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

assert_test(str_contains($gateUrl1, 'index.php'), "Trainer attempting settings.php redirected to index.php ($gateUrl1)");
assert_test(str_contains($gateRes1, 'Access Restricted') || str_contains($gateRes1, 'does not have permission'), "Role gatekeeping flash error triggered for settings.php");

// B. Try accessing staff.php (Owner only)
$ch = curl_init("http://127.0.0.1:8080/admin/staff.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $trainerCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$gateRes2 = curl_exec($ch);
$gateUrl2 = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

assert_test(str_contains($gateUrl2, 'index.php'), "Trainer attempting staff.php redirected to index.php ($gateUrl2)");

// 7. Test In-App Mobile Camera Scanner (scan.php)
// Unauthenticated access
$ch = curl_init("http://127.0.0.1:8080/scan.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, true);
$scanRes = curl_exec($ch);
$scanCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert_test($scanCode === 302, "Unauthenticated access to scan.php redirects (Got HTTP $scanCode)");
assert_test(str_contains($scanRes, 'login.php?redirect=scan.php'), "Redirect targets login.php?redirect=scan.php");

// Authenticated member access (User 1 Atul)
$memberCookie = tempnam(sys_get_temp_dir(), 'test_mem_cookie_');
$ch = curl_init("http://127.0.0.1:8080/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $memberCookie);
$mLogHtml = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $mLogHtml, $mMC);
$mCsrfTok = $mMC[1] ?? '';

$ch = curl_init("http://127.0.0.1:8080/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $mCsrfTok,
    'email' => 'atul@gmail.com',
    'password' => 'Test@123'
]));
curl_setopt($ch, CURLOPT_COOKIEFILE, $memberCookie);
curl_setopt($ch, CURLOPT_COOKIEJAR, $memberCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_exec($ch);
curl_close($ch);

// Now access scan.php
$ch = curl_init("http://127.0.0.1:8080/scan.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $memberCookie);
$scannerHtml = curl_exec($ch);
$scannerStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert_test($scannerStatus === 200, "Member successfully loads in-app camera scanner (HTTP 200)");
assert_test(str_contains($scannerHtml, 'Scan Gym Attendance QR'), "Scanner title and instructions present");
assert_test(str_contains($scannerHtml, 'id="qr-reader"'), "HTML5 camera viewfinder element initialized");
assert_test(str_contains($scannerHtml, 'Switch Lens'), "Camera lens switcher button present");
assert_test(str_contains($scannerHtml, 'Flashlight'), "Flashlight torch control button present");
assert_test(str_contains($scannerHtml, 'laser-line'), "Targeting reticle and animated laser scan line present");

@unlink($cookieFile);
@unlink($ownerCookie);
@unlink($trainerCookie);
@unlink($memberCookie);

echo "\n========================================================\n";
echo "SUMMARY: Passed: $passCount, Failed: $failCount\n";
echo "========================================================\n";

if ($failCount > 0) {
    exit(1);
}
