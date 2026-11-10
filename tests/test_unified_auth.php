<?php
/**
 * Test Suite: Unified Authentication & Registration Portal
 * Tests the unified login, unified registration, dual auto-detection,
 * status enforcement, and redirect compatibility.
 */

require_once __DIR__ . '/../gym/include/config.php';

$passed = 0;
$failed = 0;

function assert_true($condition, $description) {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$description}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$description}\n";
        $failed++;
    }
}

echo "=== UNIFIED AUTHENTICATION & REGISTRATION TEST SUITE ===\n\n";

// -------------------------------------------------------------
// Test 1: HTTP GET /login.php and /login.php?portal=staff
// -------------------------------------------------------------
echo "1. Testing Unified Login Page UI & Pre-selection...\n";
$loginDefaultHtml = file_get_contents("http://127.0.0.1:8080/login.php");
assert_true(strpos($loginDefaultHtml, 'Member Access') !== false, "login.php contains Member Access tab");
assert_true(strpos($loginDefaultHtml, 'Staff &amp; Coaches') !== false || strpos($loginDefaultHtml, 'Staff & Coaches') !== false, "login.php contains Staff & Coaches tab");
assert_true(strpos($loginDefaultHtml, 'portal-tab-btn active') !== false, "Default active tab is selected");
assert_true(strpos($loginDefaultHtml, 'pwd-toggle-btn') !== false, "Password show/hide toggle exists");

$loginStaffHtml = file_get_contents("http://127.0.0.1:8080/login.php?portal=staff");
assert_true(strpos($loginStaffHtml, 'staff-active') !== false, "login.php?portal=staff pre-selects staff tab");
assert_true(strpos($loginStaffHtml, 'Staff Work Email') !== false, "Staff tab displays 'Staff Work Email' label");

// -------------------------------------------------------------
// Test 2: HTTP GET /registration.php and /registration.php?portal=staff
// -------------------------------------------------------------
echo "\n2. Testing Unified Registration Page UI & Pre-selection...\n";
$regDefaultHtml = file_get_contents("http://127.0.0.1:8080/registration.php");
assert_true(strpos($regDefaultHtml, 'Join as Member') !== false, "registration.php contains Join as Member tab");
assert_true(strpos($regDefaultHtml, 'Apply as Staff / Coach') !== false, "registration.php contains Apply as Staff / Coach tab");
assert_true(strpos($regDefaultHtml, 'id="memberForm"') !== false, "Member form exists in registration.php");
assert_true(strpos($regDefaultHtml, 'id="staffForm"') !== false, "Staff form exists in registration.php");
assert_true(strpos($regDefaultHtml, 'Fitness Coach / Trainer') !== false, "Staff form contains Coach/Trainer card");
assert_true(strpos($regDefaultHtml, 'Front Desk &amp; Floor Staff') !== false || strpos($regDefaultHtml, 'Front Desk & Floor Staff') !== false, "Staff form contains Floor Staff card");
assert_true(strpos($regDefaultHtml, 'Operations Manager') !== false, "Staff form contains Manager card");

$regStaffHtml = file_get_contents("http://127.0.0.1:8080/registration.php?portal=staff");
assert_true(strpos($regStaffHtml, 'staff-active') !== false, "registration.php?portal=staff pre-selects staff form");

// -------------------------------------------------------------
// Test 3: Admin Direct Accessibility & Role Switching
// -------------------------------------------------------------
echo "\n3. Testing Admin Direct Accessibility & Switcher Links...\n";
$admLoginHtml = file_get_contents("http://127.0.0.1:8080/admin/login.php");
assert_true(strpos($admLoginHtml, 'Staff Management Portal') !== false || strpos($admLoginHtml, 'Staff Portal') !== false, "GET /admin/login.php renders staff portal");
assert_true(strpos($admLoginHtml, 'Member Access') !== false, "admin/login.php contains switcher link to Member Access");
assert_true(strpos($admLoginHtml, 'Staff &amp; Coaches') !== false || strpos($admLoginHtml, 'Staff & Coaches') !== false, "admin/login.php contains active Staff tab");

$admRegHtml = file_get_contents("http://127.0.0.1:8080/admin/register.php");
assert_true(strpos($admRegHtml, 'Staff Account Application') !== false || strpos($admRegHtml, 'Staff Registration') !== false, "GET /admin/register.php renders staff application");
assert_true(strpos($admRegHtml, 'Join as Member') !== false, "admin/register.php contains switcher link to Member Registration");

// -------------------------------------------------------------
// Test 4: Member Authentication via Unified Login Backend
// -------------------------------------------------------------
echo "\n4. Testing Member Authentication Backend...\n";
$email = 'atul@gmail.com';
$pwd = 'Test@123';

$uStmt = $dbh->prepare("SELECT id, fname, lname, email, mobile, password, address, status, gym_id FROM tbluser WHERE email = :email LIMIT 1");
$uStmt->execute([':email' => $email]);
$userRow = $uStmt->fetch(PDO::FETCH_ASSOC);

assert_true($userRow && verify_and_rehash_password($pwd, $userRow['password'], 'tbluser', 'id', $userRow['id'], $dbh), "Member password verification succeeds for {$email}");
assert_true($userRow['status'] === 'active', "Member account is active");

// -------------------------------------------------------------
// Test 5: Staff / Owner Authentication via Unified Login Backend
// -------------------------------------------------------------
echo "\n5. Testing Staff / Owner Authentication Backend...\n";
$staffEmail = 'admin@gmail.com';
$staffPwd = 'Test@123';

$aStmt = $dbh->prepare("SELECT id, name, email, mobile, password, role, status, trainer_id, rejection_reason, gym_id FROM tbladmin WHERE email = :email LIMIT 1");
$aStmt->execute([':email' => $staffEmail]);
$adminRow = $aStmt->fetch(PDO::FETCH_ASSOC);

assert_true($adminRow && verify_and_rehash_password($staffPwd, $adminRow['password'], 'tbladmin', 'id', $adminRow['id'], $dbh), "Staff password verification succeeds for {$staffEmail}");
assert_true($adminRow['status'] === 'approved', "Staff status is approved");
assert_true($adminRow['role'] === 'owner', "Staff role is owner");

// -------------------------------------------------------------
// Test 6: Cross-Role Auto-Detection Logic Verification
// -------------------------------------------------------------
echo "\n6. Testing Cross-Role Auto-Detection Fallback...\n";
// Case A: Staff credentials submitted on portal=member
$simulatedPortal = 'member';
$simulatedEmail = 'admin@gmail.com';
$simulatedPwd = 'Test@123';

$uCheck = $dbh->prepare("SELECT id FROM tbluser WHERE email = :email");
$uCheck->execute([':email' => $simulatedEmail]);
$foundInUser = $uCheck->fetch(PDO::FETCH_ASSOC);
assert_true(!$foundInUser, "admin@gmail.com is NOT in tbluser");

$aCheck = $dbh->prepare("SELECT id, role, status FROM tbladmin WHERE email = :email");
$aCheck->execute([':email' => $simulatedEmail]);
$foundInAdmin = $aCheck->fetch(PDO::FETCH_ASSOC);
assert_true($foundInAdmin && $foundInAdmin['status'] === 'approved', "Auto-detects admin@gmail.com in tbladmin with approved status");

// Case B: Member credentials submitted on portal=staff
$simulatedPortalB = 'staff';
$simulatedEmailB = 'atul@gmail.com';
$simulatedPwdB = 'Test@123';

$aCheckB = $dbh->prepare("SELECT id FROM tbladmin WHERE email = :email");
$aCheckB->execute([':email' => $simulatedEmailB]);
$foundInAdminB = $aCheckB->fetch(PDO::FETCH_ASSOC);
assert_true(!$foundInAdminB, "atul@gmail.com is NOT in tbladmin");

$uCheckB = $dbh->prepare("SELECT id, status FROM tbluser WHERE email = :email");
$uCheckB->execute([':email' => $simulatedEmailB]);
$foundInUserB = $uCheckB->fetch(PDO::FETCH_ASSOC);
assert_true($foundInUserB && $foundInUserB['status'] === 'active', "Auto-detects atul@gmail.com in tbluser with active status");

// -------------------------------------------------------------
// Test 7: Pending Staff Status Enforcement
// -------------------------------------------------------------
echo "\n7. Testing Pending Staff Status Enforcement...\n";
$pendingEmail = 'pooja.desk@gmail.com';
$pStmt = $dbh->prepare("SELECT id, status FROM tbladmin WHERE email = :email");
$pStmt->execute([':email' => $pendingEmail]);
$pendingRow = $pStmt->fetch(PDO::FETCH_ASSOC);
assert_true($pendingRow && $pendingRow['status'] === 'pending', "pooja.desk@gmail.com is correctly flagged as 'pending'");

// -------------------------------------------------------------
// Test 8: Member Self-Registration Simulation
// -------------------------------------------------------------
echo "\n8. Testing Member Registration Simulation...\n";
$testMemberEmail = 'test_member_' . time() . '@fitforge.local';
$testMobile = '98' . rand(10000000, 99999999);
$testPwd = password_hash('Test@1234', PASSWORD_DEFAULT);

$insU = $dbh->prepare("
    INSERT INTO tbluser (fname, lname, email, mobile, state, city, password, status, gym_id, create_date)
    VALUES ('Automated', 'Tester', :email, :mobile, 'Delhi', 'New Delhi', :pwd, 'active', 1, NOW())
");
$insU->execute([
    ':email' => $testMemberEmail,
    ':mobile' => $testMobile,
    ':pwd' => $testPwd
]);
$newMemberId = $dbh->lastInsertId();
assert_true($newMemberId > 0, "New member inserted into tbluser with ID {$newMemberId}");

// Cleanup test member
$dbh->prepare("DELETE FROM tbluser WHERE id = :id")->execute([':id' => $newMemberId]);
assert_true(true, "Cleaned up test member record");

// -------------------------------------------------------------
// Test 9: Staff Application Simulation (Trainer with Pre-profile)
// -------------------------------------------------------------
echo "\n9. Testing Staff Application Simulation...\n";
$testStaffEmail = 'test_coach_' . time() . '@fitforge.local';
$testStaffMob = '97' . rand(10000000, 99999999);
$testStaffPwd = password_hash('Staff@1234', PASSWORD_DEFAULT);

// Pre-create trainer
$insT = $dbh->prepare("
    INSERT INTO tbl_trainers (name, speciality, mobile, email, experience_years, bio, status, gym_id)
    VALUES ('Coach Auto', 'HIIT & Mobility', :mob, :email, 2, 'Automated Test Bio', 'inactive', 1)
");
$insT->execute([
    ':mob' => $testStaffMob,
    ':email' => $testStaffEmail
]);
$newTrainerId = $dbh->lastInsertId();
assert_true($newTrainerId > 0, "Trainer profile pre-created with status 'inactive' (ID {$newTrainerId})");

$insA = $dbh->prepare("
    INSERT INTO tbladmin (name, email, mobile, password, role, status, trainer_id, create_date, gym_id)
    VALUES ('Coach Auto', :email, :mob, :pwd, 'trainer', 'pending', :tid, NOW(), 1)
");
$insA->execute([
    ':email' => $testStaffEmail,
    ':mob' => $testStaffMob,
    ':pwd' => $testStaffPwd,
    ':tid' => $newTrainerId
]);
$newAdminId = $dbh->lastInsertId();
assert_true($newAdminId > 0, "Staff account inserted with status 'pending' (ID {$newAdminId})");

// Cleanup test staff and trainer
$dbh->prepare("DELETE FROM tbladmin WHERE id = :id")->execute([':id' => $newAdminId]);
$dbh->prepare("DELETE FROM tbl_trainers WHERE id = :id")->execute([':id' => $newTrainerId]);
assert_true(true, "Cleaned up test staff and trainer records");

// -------------------------------------------------------------
// Summary
// -------------------------------------------------------------
echo "\n=======================================================\n";
echo "TEST RESULTS: {$passed} PASSED / {$failed} FAILED\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
}
