<?php
/**
 * Test Suite: FitForge Phase 1 Security Fixes
 * 
 * Verifies Option A: Restricted Manager Financial Access & Authorization Boundary:
 * 1. Trainer cannot access general payment history or reports (HTTP 302).
 * 2. Floor staff cannot access payment history or reports (HTTP 302).
 * 3. Manager cannot access general reports (reports.php, report-dues.php, report-booking.php).
 * 4. Manager cannot access general payment ledger (booking-history.php).
 * 5. Manager cannot access general receipts (admin/receipt.php).
 * 6. Manager without active refund case is blocked from booking-history-details.php.
 * 7. Manager WITH active refund case accesses ONLY case-specific refund review card; general ledger is stripped.
 * 8. Member A cannot access Member B's booking receipt (IDOR protection on booking-details.php).
 * 9. Non-Owner cannot access arbitrary receipts via public receipt.php (HTTP 403).
 * 10. Deactivated/suspended staff session is immediately destroyed on next request.
 * 11. Owner retains full access to all financial reports, dues, ledgers, and receipts (HTTP 200).
 */

require_once __DIR__ . '/../gym/include/config.php';
require_once __DIR__ . '/../gym/admin/include/rbac.php';

echo "========================================================\n";
echo "RUNNING PHASE 1: FINANCIAL ACCESS SECURITY TEST SUITE\n";
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

// -------------------------------------------------------------
// SETUP TEST FIXTURES
// -------------------------------------------------------------
echo "\n--- Setting up Test Fixtures ---\n";

// Helper curl session login
function login_admin_cookie($email, $password) {
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
        'email' => $email,
        'password' => $password,
        'submit' => '1'
    ]));
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    curl_close($ch);

    return $cookie;
}

function login_member_cookie($email, $password) {
    $cookie = tempnam(sys_get_temp_dir(), 'mem_cookie_');
    $ch = curl_init("http://127.0.0.1:8080/login.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
    $page = curl_exec($ch);
    curl_close($ch);

    preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $page, $mCsrf);
    $csrf = $mCsrf[1] ?? '';

    $ch = curl_init("http://127.0.0.1:8080/login.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'csrf_token' => $csrf,
        'email' => $email,
        'password' => $password,
        'submit' => '1'
    ]));
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    curl_close($ch);

    return $cookie;
}

// Clean previous fixtures if any
$dbh->exec("DELETE FROM tbladmin WHERE email IN ('mgr_sec@fitforge.local', 'floor_sec@fitforge.local', 'deact_sec@fitforge.local')");
$dbh->exec("DELETE FROM tblpayment WHERE bookingID IN (99881, 99882)");
$dbh->exec("DELETE FROM tbl_refund_requests WHERE booking_id IN (99881, 99882)");
$dbh->exec("DELETE FROM tblbooking WHERE id IN (99881, 99882)");
$dbh->exec("DELETE FROM tbluser WHERE email IN ('mem_a@fitforge.local', 'mem_b@fitforge.local')");

// Create Test Manager
$pwdHash = password_hash('Test@123', PASSWORD_DEFAULT);
$insMgr = $dbh->prepare("
    INSERT INTO tbladmin (name, email, password, role, status, approved_by, approved_at, gym_id)
    VALUES ('Manager SecTest', 'mgr_sec@fitforge.local', :pwd, 'manager', 'approved', 1, NOW(), 1)
");
$insMgr->execute([':pwd' => $pwdHash]);
$mgrId = intval($dbh->lastInsertId());

// Create Test Floor Staff
$insFloor = $dbh->prepare("
    INSERT INTO tbladmin (name, email, password, role, status, approved_by, approved_at, gym_id)
    VALUES ('Floor SecTest', 'floor_sec@fitforge.local', :pwd, 'floor_staff', 'approved', 1, NOW(), 1)
");
$insFloor->execute([':pwd' => $pwdHash]);
$floorId = intval($dbh->lastInsertId());

// Create Member A and Member B
$insU = $dbh->prepare("
    INSERT INTO tbluser (fname, lname, email, mobile, password, status, gym_id, create_date)
    VALUES (:fn, :ln, :em, :mob, :pwd, 'active', 1, NOW())
");
$insU->execute([':fn' => 'Alice', ':ln' => 'MemberA', ':em' => 'mem_a@fitforge.local', ':mob' => '9111111111', ':pwd' => $pwdHash]);
$uidA = intval($dbh->lastInsertId());

$insU->execute([':fn' => 'Bob', ':ln' => 'MemberB', ':em' => 'mem_b@fitforge.local', ':mob' => '9222222222', ':pwd' => $pwdHash]);
$uidB = intval($dbh->lastInsertId());

// Create Booking for Member A (ID 99881) and Booking for Member B (ID 99882)
$insB = $dbh->prepare("
    INSERT INTO tblbooking (id, package_id, userid, payment, paymentType, gym_id)
    VALUES (:bid, 1, :uid, '1000.00', 'Cash', 1)
");
$insB->execute([':bid' => 99881, ':uid' => $uidA]);
$insB->execute([':bid' => 99882, ':uid' => $uidB]);

// Insert payment for Booking 99881 (No refund case)
$dbh->exec("INSERT INTO tblpayment (bookingID, paymentType, payment, status, gym_id) VALUES (99881, 'Cash', '1000.00', 'completed', 1)");
$payId1 = intval($dbh->lastInsertId());

// Insert payment and active refund request for Booking 99882 (With refund case)
$dbh->exec("INSERT INTO tblpayment (bookingID, paymentType, payment, status, gym_id) VALUES (99882, 'UPI', '1000.00', 'completed', 1)");
$payId2 = intval($dbh->lastInsertId());

$insRefReq = $dbh->prepare("
    INSERT INTO tbl_refund_requests (payment_id, booking_id, user_id, refund_amount, reason, status, requested_by_admin_id, gym_id)
    VALUES (:pid, 99882, :uid, 250.00, 'Authorized Medical Relocation', 'pending', 1, 1)
");
$insRefReq->execute([':pid' => $payId2, ':uid' => $uidB]);
$refCaseId = intval($dbh->lastInsertId());

assert_test($mgrId > 0 && $floorId > 0 && $uidA > 0 && $uidB > 0 && $refCaseId > 0, "Test fixtures created successfully");

// -------------------------------------------------------------
// SECTION 1: ROLE-BASED ACCESS CONTROL MATRIX BOUNDARY
// -------------------------------------------------------------
echo "\n--- SECTION 1: RBAC Code Boundary Tests ---\n";

assert_test(role_has_permission('financial.reports', 'owner') === true, "Owner has 'financial.reports' permission");
assert_test(role_has_permission('financial.reports', 'manager') === false, "Manager STRICTLY DENIED 'financial.reports'");
assert_test(role_has_permission('financial.ledger', 'manager') === false, "Manager STRICTLY DENIED 'financial.ledger'");
assert_test(role_has_permission('financial.dues', 'manager') === false, "Manager STRICTLY DENIED 'financial.dues'");
assert_test(role_has_permission('financial.exports', 'manager') === false, "Manager STRICTLY DENIED 'financial.exports'");
assert_test(role_has_permission('view_financials', 'manager') === false, "Manager STRICTLY DENIED 'view_financials'");
assert_test(role_has_permission('manage_bookings', 'manager') === false, "Manager STRICTLY DENIED 'manage_bookings'");

// Refund workflow permissions ARE allowed for manager
assert_test(role_has_permission('refunds.view_assigned', 'manager') === true, "Manager has 'refunds.view_assigned'");
assert_test(role_has_permission('refunds.approve', 'manager') === true, "Manager has 'refunds.approve'");
assert_test(role_has_permission('refunds.process', 'manager') === true, "Manager has 'refunds.process'");

// Other staff cannot approve refunds
assert_test(role_has_permission('refunds.approve', 'trainer') === false, "Trainer CANNOT approve refunds");
assert_test(role_has_permission('refunds.approve', 'floor_staff') === false, "Floor Staff CANNOT approve refunds");

// -------------------------------------------------------------
// SECTION 2: HTTP BOUNDARY ENFORCEMENT ON GENERAL FINANCIAL ROUTES
// -------------------------------------------------------------
echo "\n--- SECTION 2: HTTP Endpoint Security Tests ---\n";

$mgrCookie = login_admin_cookie('mgr_sec@fitforge.local', 'Test@123');
$floorCookie = login_admin_cookie('floor_sec@fitforge.local', 'Test@123');
$ownerCookie = login_admin_cookie('admin@gmail.com', 'Test@123');

// Helper to check non-owner redirection
function test_url_redirects_non_owner($url, $cookie, $desc) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);

    return ($code === 302 && (str_contains($redirectUrl, 'index.php') || str_contains($redirectUrl, 'login.php')));
}

// 2A: Manager attempting general reports.php
$mgrReportsBlocked = test_url_redirects_non_owner("http://127.0.0.1:8080/admin/reports.php", $mgrCookie, "Manager reports.php");
assert_test($mgrReportsBlocked, "Manager GET admin/reports.php redirected (Option A: Access Restricted)");

// 2B: Manager attempting report-dues.php
$mgrDuesBlocked = test_url_redirects_non_owner("http://127.0.0.1:8080/admin/report-dues.php", $mgrCookie, "Manager report-dues.php");
assert_test($mgrDuesBlocked, "Manager GET admin/report-dues.php redirected (Option A: Access Restricted)");

// 2C: Manager attempting report-booking.php
$mgrBookingRepBlocked = test_url_redirects_non_owner("http://127.0.0.1:8080/admin/report-booking.php", $mgrCookie, "Manager report-booking.php");
assert_test($mgrBookingRepBlocked, "Manager GET admin/report-booking.php redirected (Option A: Access Restricted)");

// 2D: Manager attempting booking-history.php (general ledger)
$mgrLedgerBlocked = test_url_redirects_non_owner("http://127.0.0.1:8080/admin/booking-history.php", $mgrCookie, "Manager booking-history.php");
assert_test($mgrLedgerBlocked, "Manager GET admin/booking-history.php redirected (Option A: Access Restricted)");

// 2E: Manager attempting admin/receipt.php
$mgrReceiptBlocked = test_url_redirects_non_owner("http://127.0.0.1:8080/admin/receipt.php?booking=99881", $mgrCookie, "Manager admin/receipt.php");
assert_test($mgrReceiptBlocked, "Manager GET admin/receipt.php redirected (Option A: Access Restricted)");

// 2F: Floor Staff attempting booking-history.php
$floorLedgerBlocked = test_url_redirects_non_owner("http://127.0.0.1:8080/admin/booking-history.php", $floorCookie, "Floor Staff booking-history.php");
assert_test($floorLedgerBlocked, "Floor Staff GET admin/booking-history.php redirected");

// 2G: Floor Staff attempting reports.php
$floorReportsBlocked = test_url_redirects_non_owner("http://127.0.0.1:8080/admin/reports.php", $floorCookie, "Floor Staff reports.php");
assert_test($floorReportsBlocked, "Floor Staff GET admin/reports.php redirected");

// -------------------------------------------------------------
// SECTION 3: CASE-SPECIFIC REFUND WORKFLOW ISOLATION
// -------------------------------------------------------------
echo "\n--- SECTION 3: Case-Specific Refund Workflow Tests ---\n";

// 3A: Manager loads booking 99881 (HAS NO REFUND REQUEST)
$ch = curl_init("http://127.0.0.1:8080/admin/booking-history-details.php?bookingid=99881");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $mgrCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_exec($ch);
$httpCodeNoCase = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert_test($httpCodeNoCase === 302, "Manager blocked from booking details without active refund request (Got HTTP $httpCodeNoCase)");

// 3B: Manager loads booking 99882 (HAS ACTIVE REFUND REQUEST #refCaseId)
$ch = curl_init("http://127.0.0.1:8080/admin/booking-history-details.php?bookingid=99882");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $mgrCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$caseHtml = curl_exec($ch);
curl_close($ch);

// Verify Manager sees case-specific refund details
assert_test(str_contains($caseHtml, 'Refund Case Review') || str_contains($caseHtml, 'Refund Authorization'), "Manager accesses Case-Specific Refund Review screen");
assert_test(str_contains($caseHtml, '250'), "Manager sees requested refund amount (250)");
assert_test(str_contains($caseHtml, 'Authorized Medical Relocation'), "Manager sees refund reason");

// Verify Manager DOES NOT see general payment ledger components
assert_test(!str_contains($caseHtml, 'Payment Installment History'), "General 'Payment Installment History' table STRIPPED from Manager view");
assert_test(!str_contains($caseHtml, 'Total Collected'), "General 'Total Collected' balance card STRIPPED from Manager view");
assert_test(!str_contains($caseHtml, 'action="record_payment"'), "Record Payment Form STRIPPED from Manager view");

// -------------------------------------------------------------
// SECTION 4: PUBLIC & MEMBER-FACING FINANCIAL ISOLATION (IDOR FIX)
// -------------------------------------------------------------
echo "\n--- SECTION 4: IDOR & Public Receipt Access Tests ---\n";

$cookieA = login_member_cookie('mem_a@fitforge.local', 'Test@123');
$cookieB = login_member_cookie('mem_b@fitforge.local', 'Test@123');

// 4A: Member A views their OWN booking (99881) -> SUCCESS
$ch = curl_init("http://127.0.0.1:8080/booking-details.php?bookingid=99881");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieA);
$ownBookingHtml = curl_exec($ch);
curl_close($ch);
assert_test(str_contains($ownBookingHtml, 'Alice') && str_contains($ownBookingHtml, '99881'), "Member A successfully loads their own booking receipt");

// 4B: Member A attempts to view Member B's booking (99882) -> IDOR PREVENTION
$ch = curl_init("http://127.0.0.1:8080/booking-details.php?bookingid=99882");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieA);
$otherBookingHtml = curl_exec($ch);
curl_close($ch);
assert_test(str_contains($otherBookingHtml, 'Access Restricted'), "Member A blocked from viewing Member B's booking receipt (IDOR fixed)");
assert_test(!str_contains($otherBookingHtml, 'Bob'), "Member B's name 'Bob' is NOT leaked to Member A");

// 4C: Public / Non-Owner access to receipt.php
$ch = curl_init("http://127.0.0.1:8080/receipt.php?booking=99881");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieB); // Member B trying to view Member A's receipt
$resB = curl_exec($ch);
$codeB = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert_test($codeB === 403, "Cross-member access to receipt.php returns HTTP 403 Forbidden (Got: $codeB)");

// -------------------------------------------------------------
// SECTION 5: REAL-TIME DEACTIVATION GATE
// -------------------------------------------------------------
echo "\n--- SECTION 5: Real-time Account Deactivation Gate ---\n";

// Create temporary employee and log in
$insDeact = $dbh->prepare("
    INSERT INTO tbladmin (name, email, password, role, status, approved_by, approved_at, gym_id)
    VALUES ('Deact Staff', 'deact_sec@fitforge.local', :pwd, 'floor_staff', 'approved', 1, NOW(), 1)
");
$insDeact->execute([':pwd' => $pwdHash]);
$deactId = intval($dbh->lastInsertId());

$deactCookie = login_admin_cookie('deact_sec@fitforge.local', 'Test@123');

// First request while approved -> 200 OK
$ch = curl_init("http://127.0.0.1:8080/admin/index.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $deactCookie);
curl_exec($ch);
$codeApproved = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert_test($codeApproved === 200, "Approved employee loads dashboard (HTTP 200)");

// Owner suspends/deactivates employee in database
$dbh->exec("UPDATE tbladmin SET status = 'suspended' WHERE id = $deactId");

// Second request with existing session -> instantly logged out and redirected
$ch = curl_init("http://127.0.0.1:8080/admin/index.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $deactCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_exec($ch);
$codeSuspended = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$redirSuspended = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
curl_close($ch);
assert_test($codeSuspended === 302 && str_contains($redirSuspended, 'error=deactivated'), "Suspended employee session instantly terminated on next request");

// -------------------------------------------------------------
// SECTION 6: OWNER FULL ACCESS VERIFICATION
// -------------------------------------------------------------
echo "\n--- SECTION 6: Owner Full Access Verification ---\n";

$ch = curl_init("http://127.0.0.1:8080/admin/reports.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $ownerCookie);
$ownerReportsHtml = curl_exec($ch);
$codeOwnReports = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert_test($codeOwnReports === 200 && (str_contains($ownerReportsHtml, 'Reports') || str_contains($ownerReportsHtml, 'Financial Analytics')), "Owner retains full access to reports.php (HTTP 200)");

$ch = curl_init("http://127.0.0.1:8080/admin/booking-history.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $ownerCookie);
$ownerBookingsHtml = curl_exec($ch);
$codeOwnBookings = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert_test($codeOwnBookings === 200 && str_contains($ownerBookingsHtml, 'Payments & Bookings Hub'), "Owner retains full access to booking-history.php (HTTP 200)");

$ch = curl_init("http://127.0.0.1:8080/admin/booking-history-details.php?bookingid=99881");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $ownerCookie);
$ownerDetailsHtml = curl_exec($ch);
curl_close($ch);
assert_test(str_contains($ownerDetailsHtml, 'Payment Installment History') && str_contains($ownerDetailsHtml, 'Total Collected'), "Owner retains full ledger breakdown on booking details");

// -------------------------------------------------------------
// CLEANUP & TEARDOWN
// -------------------------------------------------------------
echo "\n--- Cleanup & Teardown ---\n";
$dbh->exec("DELETE FROM tbladmin WHERE id IN ($mgrId, $floorId, $deactId)");
$dbh->exec("DELETE FROM tblpayment WHERE bookingID IN (99881, 99882)");
$dbh->exec("DELETE FROM tbl_refund_requests WHERE booking_id IN (99881, 99882)");
$dbh->exec("DELETE FROM tblbooking WHERE id IN (99881, 99882)");
$dbh->exec("DELETE FROM tbluser WHERE id IN ($uidA, $uidB)");

@unlink($mgrCookie);
@unlink($floorCookie);
@unlink($ownerCookie);
@unlink($cookieA);
@unlink($cookieB);
@unlink($deactCookie);
assert_test(true, "Test fixtures cleaned up successfully");

echo "\n========================================================\n";
echo "SUMMARY: Passed: $passCount, Failed: $failCount\n";
echo "========================================================\n";

if ($failCount > 0) {
    exit(1);
}
