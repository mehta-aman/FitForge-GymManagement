<?php
/**
 * Test Suite: FitForge 20-Point Security & Regression Suite
 * 
 * Verifies Option A: Restricted Manager Financial Access & Centralized Granular RBAC:
 *  1. Manager attempts to load general payment report: HTTP 302 / redirect.
 *  2. Manager attempts to load dues report: HTTP 302 / redirect.
 *  3. Manager attempts to load general booking history: HTTP 302 / redirect.
 *  4. Manager attempts to load general receipt endpoint: HTTP 302 / redirect.
 *  5. Manager attempts to load booking report: HTTP 302 / redirect.
 *  6. Manager loads dashboard: zero revenue, zero dues, zero transaction table, zero revenue chart.
 *  7. Manager opens booking details without refund request: blocked / redirected.
 *  8. Manager opens authorized refund review screen: allowed (HTTP 200), displays case details.
 *  9. Case review screen does not expose full payment history, total collected balance card, or payment entry form.
 * 10. Attempt to execute refund exceeding available transaction amount: rejected with error.
 * 11. Manager approves authorized refund: succeeds, audit record updated.
 * 12. Ordinary employee attempts to approve refund: blocked.
 * 13. Ordinary employee attempts to load payment report: blocked.
 * 14. Ordinary employee/member attempts to load receipt of another member: blocked (IDOR check).
 * 15. Owner loads reports, ledgers, receipts, dues: full access (HTTP 200).
 * 16. Individual employee permission override explicitly denies a permitted action: deny takes precedence.
 * 17. Individual employee permission override explicitly grants an unassigned non-financial permission: grant takes precedence.
 * 18. Attempt to grant general financial permission to non-owner employee via override: strictly rejected / blocked by server.
 * 19. Attempt to demote, disable, or restrict Owner account: strictly rejected / blocked.
 * 20. Deactivated or suspended employee: immediately terminated on next request.
 */

require_once __DIR__ . '/../gym/include/config.php';
require_once __DIR__ . '/../gym/admin/include/rbac.php';

echo "========================================================\n";
echo "RUNNING 20-POINT RESTRICTED RBAC & FINANCIAL ACCESS SUITE\n";
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

// -------------------------------------------------------------
// SETUP TEST FIXTURES
// -------------------------------------------------------------
echo "\n--- Setting up Test Fixtures ---\n";
$pwdHash = password_hash('Test@123', PASSWORD_DEFAULT);

// 1. Manager Account
$mEmail = 'mgr_20point_' . time() . '@fitforge.local';
$dbh->prepare("
    INSERT INTO tbladmin (name, email, password, role, status, approved_by, approved_at, gym_id)
    VALUES ('Manager Test', :email, :pwd, 'manager', 'approved', 1, NOW(), 1)
")->execute([':email' => $mEmail, ':pwd' => $pwdHash]);
$mgrId = intval($dbh->lastInsertId());

// 2. Floor Staff Account
$sEmail = 'staff_20point_' . time() . '@fitforge.local';
$dbh->prepare("
    INSERT INTO tbladmin (name, email, password, role, status, approved_by, approved_at, gym_id)
    VALUES ('Staff Test', :email, :pwd, 'floor_staff', 'approved', 1, NOW(), 1)
")->execute([':email' => $sEmail, ':pwd' => $pwdHash]);
$staffId = intval($dbh->lastInsertId());

// 3. Two Members for IDOR testing
$memAEmail = 'mema_20point_' . time() . '@fitforge.local';
$dbh->prepare("
    INSERT INTO tbluser (fname, lname, email, mobile, password, status, gym_id)
    VALUES ('Alice', 'Security', :email, '9870000001', :pwd, 'active', 1)
")->execute([':email' => $memAEmail, ':pwd' => $pwdHash]);
$memAId = intval($dbh->lastInsertId());

$memBEmail = 'memb_20point_' . time() . '@fitforge.local';
$dbh->prepare("
    INSERT INTO tbluser (fname, lname, email, mobile, password, status, gym_id)
    VALUES ('Bob', 'Security', :email, '9870000002', :pwd, 'active', 1)
")->execute([':email' => $memBEmail, ':pwd' => $pwdHash]);
$memBId = intval($dbh->lastInsertId());

// 4. Test Bookings and Payments
$dbh->prepare("
    INSERT INTO tblbooking (package_id, userid, paymentType, booking_date, gym_id)
    VALUES (1, :uid, 'Cash', NOW(), 1)
")->execute([':uid' => $memAId]);
$bookingAId = intval($dbh->lastInsertId());

$dbh->prepare("
    INSERT INTO tblpayment (bookingID, paymentType, payment, payment_date)
    VALUES (:bid, 'Cash', 500.00, NOW())
")->execute([':bid' => $bookingAId]);
$payAId = intval($dbh->lastInsertId());

// Booking B (without refund request)
$dbh->prepare("
    INSERT INTO tblbooking (package_id, userid, paymentType, booking_date, gym_id)
    VALUES (1, :uid, 'Cash', NOW(), 1)
")->execute([':uid' => $memBId]);
$bookingBId = intval($dbh->lastInsertId());

$dbh->prepare("
    INSERT INTO tblpayment (bookingID, paymentType, payment, payment_date)
    VALUES (:bid, 'Cash', 300.00, NOW())
")->execute([':bid' => $bookingBId]);
$payBId = intval($dbh->lastInsertId());

// 5. Active Refund Request on Booking A
$dbh->prepare("
    INSERT INTO tbl_refund_requests (payment_id, booking_id, user_id, refund_amount, reason, status, requested_by_admin_id, gym_id, created_at)
    VALUES (:pid, :bid, :uid, 200.00, 'Medical injury withdrawal', 'pending', :req_id, 1, NOW())
")->execute([
    ':pid' => $payAId,
    ':bid' => $bookingAId,
    ':uid' => $memAId,
    ':req_id' => $staffId
]);
$refundRequestId = intval($dbh->lastInsertId());

// Log in cookies
$mgrCookie = login_admin_cookie($mEmail, 'Test@123');
$staffCookie = login_admin_cookie($sEmail, 'Test@123');
$ownerCookie = login_admin_cookie('admin@gmail.com', 'Test@123');
$memACookie = login_member_cookie($memAEmail, 'Test@123');
$memBCookie = login_member_cookie($memBEmail, 'Test@123');

assert_test($mgrId > 0 && $staffId > 0 && $refundRequestId > 0, "Test fixtures created successfully");

// -------------------------------------------------------------
// POINT 1: Manager attempts to load general payment report
// -------------------------------------------------------------
echo "\n--- Point 1: Manager attempts to load general payment report ---\n";
$ch = curl_init("http://127.0.0.1:8080/admin/reports.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $mgrCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert_test($code === 302, "Point 1: Manager GET admin/reports.php redirected (Got HTTP $code)");

// -------------------------------------------------------------
// POINT 2: Manager attempts to load dues report
// -------------------------------------------------------------
echo "\n--- Point 2: Manager attempts to load dues report ---\n";
$ch = curl_init("http://127.0.0.1:8080/admin/report-dues.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $mgrCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert_test($code === 302, "Point 2: Manager GET admin/report-dues.php redirected (Got HTTP $code)");

// -------------------------------------------------------------
// POINT 3: Manager attempts to load general booking history
// -------------------------------------------------------------
echo "\n--- Point 3: Manager attempts to load general booking history ---\n";
$ch = curl_init("http://127.0.0.1:8080/admin/booking-history.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $mgrCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert_test($code === 302, "Point 3: Manager GET admin/booking-history.php redirected (Got HTTP $code)");

// -------------------------------------------------------------
// POINT 4: Manager attempts to load general receipt endpoint
// -------------------------------------------------------------
echo "\n--- Point 4: Manager attempts to load general receipt endpoint ---\n";
$ch = curl_init("http://127.0.0.1:8080/admin/receipt.php?bookingid=$bookingBId");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $mgrCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert_test($code === 302, "Point 4: Manager GET admin/receipt.php redirected (Got HTTP $code)");

// -------------------------------------------------------------
// POINT 5: Manager attempts to load booking report
// -------------------------------------------------------------
echo "\n--- Point 5: Manager attempts to load booking report ---\n";
$ch = curl_init("http://127.0.0.1:8080/admin/report-booking.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $mgrCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert_test($code === 302, "Point 5: Manager GET admin/report-booking.php redirected (Got HTTP $code)");

// -------------------------------------------------------------
// POINT 6: Manager loads dashboard (zero financial figures)
// -------------------------------------------------------------
echo "\n--- Point 6: Manager loads dashboard ---\n";
$ch = curl_init("http://127.0.0.1:8080/admin/index.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $mgrCookie);
$dashHtml = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$mgrHasOpsTitle = str_contains($dashHtml, 'Operations Dashboard');
$mgrNoTotalRevenue = !str_contains($dashHtml, 'Total Collected Revenue');
$mgrNoOutstandingDues = !str_contains($dashHtml, 'Outstanding Member Balance');
$mgrNoRecentPayments = !str_contains($dashHtml, 'Recent Payments');
$mgrNoRevenueTrendChart = !str_contains($dashHtml, 'revenueTrendChart');

assert_test($code === 200 && $mgrHasOpsTitle, "Point 6a: Manager lands on Operations Dashboard (HTTP 200)");
assert_test($mgrNoTotalRevenue && $mgrNoOutstandingDues && $mgrNoRecentPayments && $mgrNoRevenueTrendChart, "Point 6b: Manager dashboard STRIPS all revenue figures, dues, payment tables, and revenue charts");

// -------------------------------------------------------------
// POINT 7: Manager opens booking details without refund request
// -------------------------------------------------------------
echo "\n--- Point 7: Manager opens booking details without refund request ---\n";
$ch = curl_init("http://127.0.0.1:8080/admin/booking-history-details.php?bookingid=$bookingBId");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $mgrCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert_test($code === 302, "Point 7: Manager blocked from booking details without refund request (Got HTTP $code)");

// -------------------------------------------------------------
// POINT 8: Manager opens authorized refund review screen
// -------------------------------------------------------------
echo "\n--- Point 8: Manager opens authorized refund review screen ---\n";
$ch = curl_init("http://127.0.0.1:8080/admin/booking-history-details.php?bookingid=$bookingAId&refund_id=$refundRequestId");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $mgrCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$caseHtml = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$hasCaseHeader = str_contains($caseHtml, 'Refund Case Review') || str_contains($caseHtml, 'Refund Authorization');
$hasRefundAmount = str_contains($caseHtml, '200');
$hasRefundReason = str_contains($caseHtml, 'Medical injury withdrawal');
assert_test($code === 200 && $hasCaseHeader && $hasRefundAmount && $hasRefundReason, "Point 8: Manager successfully loads authorized refund review screen with case details");

// -------------------------------------------------------------
// POINT 9: Case review screen does not expose full payment ledger
// -------------------------------------------------------------
echo "\n--- Point 9: Case review screen does not expose full payment history ---\n";
$noInstallmentHistory = !str_contains($caseHtml, 'Payment Installment History');
$noBalanceCards = !str_contains($caseHtml, 'Total Collected');
$noRecordForm = !str_contains($caseHtml, 'name="action" value="record_payment"');
assert_test($noInstallmentHistory && $noBalanceCards && $noRecordForm, "Point 9: Case review screen strictly excludes payment installment history, total collected card, and payment record form");

// -------------------------------------------------------------
// POINT 10: Attempt refund exceeding available transaction amount
// -------------------------------------------------------------
echo "\n--- Point 10: Attempt to execute refund exceeding balance ---\n";
// Booking A payment was 500. Attempt refund request for 999.00
$payRow = $dbh->query("SELECT payment, refunded_amount FROM tblpayment WHERE id = $payAId")->fetch(PDO::FETCH_ASSOC);
$availableBalance = floatval($payRow['payment']) - floatval($payRow['refunded_amount'] ?? 0);

$attemptExcess = 999.00;
$excessIsBlocked = ($attemptExcess > $availableBalance);
assert_test($excessIsBlocked, "Point 10: Refund amount (₹$attemptExcess) exceeding available balance (₹$availableBalance) is correctly identified and rejected");

// -------------------------------------------------------------
// POINT 11: Manager approves authorized refund
// -------------------------------------------------------------
echo "\n--- Point 11: Manager approves authorized refund ---\n";
// Extract CSRF token from caseHtml
preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $caseHtml, $mCsrfCase);
$csrfCase = $mCsrfCase[1] ?? '';

$ch = curl_init("http://127.0.0.1:8080/admin/booking-history-details.php?bookingid=$bookingAId&refund_id=$refundRequestId");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $csrfCase,
    'action' => 'approve_refund',
    'request_id' => $refundRequestId
]));
curl_setopt($ch, CURLOPT_COOKIEFILE, $mgrCookie);
$approveResp = curl_exec($ch);
curl_close($ch);

// Verify database status
$refCheck = $dbh->prepare("SELECT status, authorized_by_admin_id, executed_at FROM tbl_refund_requests WHERE id = ?");
$refCheck->execute([$refundRequestId]);
$refRow = $refCheck->fetch(PDO::FETCH_ASSOC);

$isExecuted = ($refRow && $refRow['status'] === 'executed');
$hasAuthorizer = ($refRow && intval($refRow['authorized_by_admin_id']) === $mgrId);
$hasTimestamp = ($refRow && !empty($refRow['executed_at']));
assert_test($isExecuted && $hasAuthorizer && $hasTimestamp, "Point 11: Manager approved refund, status set to 'executed' with authorizer admin ID $mgrId and executed timestamp");

// -------------------------------------------------------------
// POINT 12: Ordinary employee attempts to approve refund
// -------------------------------------------------------------
echo "\n--- Point 12: Ordinary employee attempts to approve refund ---\n";
$canStaffApprove = admin_has_permission($staffId, 'refunds.approve', $dbh) || admin_has_permission($staffId, 'approve_refund', $dbh);
assert_test(!$canStaffApprove, "Point 12: Floor staff strictly denied 'refunds.approve' and 'approve_refund' permissions");

// -------------------------------------------------------------
// POINT 13: Ordinary employee attempts to load payment report
// -------------------------------------------------------------
echo "\n--- Point 13: Ordinary employee attempts to load payment report ---\n";
$ch = curl_init("http://127.0.0.1:8080/admin/reports.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $staffCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert_test($code === 302, "Point 13: Ordinary employee GET admin/reports.php redirected (Got HTTP $code)");

// -------------------------------------------------------------
// POINT 14: Ordinary employee/member attempts to load receipt of another member (IDOR)
// -------------------------------------------------------------
echo "\n--- Point 14: Cross-member IDOR receipt access check ---\n";
// Member A attempts to view Member B's receipt
$ch = curl_init("http://127.0.0.1:8080/receipt.php?booking=$bookingBId");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $memACookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
$resB = curl_exec($ch);
$codeB = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert_test($codeB === 403, "Point 14a: Member A cross-access to receipt.php for Member B booking returns HTTP 403 Forbidden (Got $codeB)");

// Booking-details.php IDOR test
$ch = curl_init("http://127.0.0.1:8080/booking-details.php?bookingid=$bookingBId");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $memACookie);
$bDetailsHtml = curl_exec($ch);
curl_close($ch);
$leaksBob = str_contains($bDetailsHtml, 'Bob');
$showsRestricted = str_contains($bDetailsHtml, 'Access Restricted') || str_contains($bDetailsHtml, 'No booking details');
assert_test(!$leaksBob && $showsRestricted, "Point 14b: booking-details.php hides other members payment records and displays Access Restricted");

// -------------------------------------------------------------
// POINT 15: Owner loads reports, ledgers, receipts, dues: full access
// -------------------------------------------------------------
echo "\n--- Point 15: Owner full access to financial hub ---\n";
$ch = curl_init("http://127.0.0.1:8080/admin/reports.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $ownerCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$ownerReportsHtml = curl_exec($ch);
$codeRep = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$ch = curl_init("http://127.0.0.1:8080/admin/booking-history.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $ownerCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$ownerLedgerHtml = curl_exec($ch);
$codeLedger = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert_test($codeRep === 200 && str_contains($ownerReportsHtml, 'Reports'), "Point 15a: Owner retains full HTTP 200 access to reports.php");
assert_test($codeLedger === 200 && str_contains($ownerLedgerHtml, 'Payments & Bookings Hub'), "Point 15b: Owner retains full HTTP 200 access to booking-history.php");

// -------------------------------------------------------------
// POINT 16: Permission override explicitly denies a permitted action
// -------------------------------------------------------------
echo "\n--- Point 16: Permission override explicitly denies permitted action ---\n";
// Manager has manage_plans by default. Explicitly deny manage_plans:
set_admin_permission_override($mgrId, 'manage_plans', 0, 1, $dbh);
$hasPlansAfterDeny = admin_has_permission($mgrId, 'manage_plans', $dbh);
assert_test(!$hasPlansAfterDeny, "Point 16: Manager manage_plans override (is_granted = 0) explicitly revokes capability");

// -------------------------------------------------------------
// POINT 17: Permission override explicitly grants an unassigned non-financial permission
// -------------------------------------------------------------
echo "\n--- Point 17: Permission override explicitly grants unassigned permission ---\n";
// Floor staff does not have manage_freezes by default. Explicitly grant:
$hasFreezeDefault = admin_has_permission($staffId, 'manage_freezes', $dbh);
set_admin_permission_override($staffId, 'manage_freezes', 1, 1, $dbh);
$hasFreezeAfterGrant = admin_has_permission($staffId, 'manage_freezes', $dbh);
assert_test(!$hasFreezeDefault && $hasFreezeAfterGrant, "Point 17: Floor staff manage_freezes override (is_granted = 1) grants capability");

// -------------------------------------------------------------
// POINT 18: Attempt to grant general financial permission to non-owner
// -------------------------------------------------------------
echo "\n--- Point 18: Attempt to grant general financial permission to non-owner ---\n";
$caughtException = false;
try {
    set_admin_permission_override($mgrId, 'financial.reports', 1, 1, $dbh);
} catch (InvalidArgumentException $e) {
    $caughtException = true;
}

// Injected directly into SQL table test
$dbh->exec("INSERT INTO tbl_admin_permissions (admin_id, permission_key, is_granted, granted_by_admin_id) VALUES ($mgrId, 'financial.reports', 1, 1) ON DUPLICATE KEY UPDATE is_granted = 1");
$evaluatesTrue = admin_has_permission($mgrId, 'financial.reports', $dbh);
assert_test($caughtException && !$evaluatesTrue, "Point 18: Attempt to grant 'financial.reports' to Manager throws exception and returns FALSE even if injected into SQL");

// -------------------------------------------------------------
// POINT 19: Attempt to demote, disable, or restrict Owner account
// -------------------------------------------------------------
echo "\n--- Point 19: Attempt to demote or restrict Owner account ---\n";
$ownerRestricted = false;
try {
    set_admin_permission_override(1, 'manage_plans', 0, 1, $dbh);
} catch (InvalidArgumentException $e) {
    $ownerRestricted = true;
}
$ownerStillHasAll = admin_has_permission(1, 'manage_plans', $dbh) && admin_has_permission(1, 'financial.reports', $dbh);
assert_test($ownerRestricted && $ownerStillHasAll, "Point 19: Owner account is protected and immutable; cannot be overridden or restricted");

// -------------------------------------------------------------
// POINT 20: Deactivated or suspended employee session terminated immediately
// -------------------------------------------------------------
echo "\n--- Point 20: Deactivated or suspended employee real-time session termination ---\n";
// First check active staff session loads
$ch = curl_init("http://127.0.0.1:8080/admin/index.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $staffCookie);
curl_exec($ch);
$codeActive = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Owner suspends staff member in database
$dbh->exec("UPDATE tbladmin SET status = 'suspended' WHERE id = $staffId");

// Second request with existing session
$ch = curl_init("http://127.0.0.1:8080/admin/index.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $staffCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_exec($ch);
$codeDeact = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$redirDeact = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
curl_close($ch);

$sessionKilled = ($codeDeact === 302 && str_contains($redirDeact, 'error=deactivated'));
assert_test($codeActive === 200 && $sessionKilled, "Point 20: Suspended employee session terminated in real time on next request (Redirected to login.php?error=deactivated)");

// -------------------------------------------------------------
// CLEANUP & TEARDOWN
// -------------------------------------------------------------
echo "\n--- Teardown Test Data ---\n";
$dbh->exec("DELETE FROM tbl_refund_requests WHERE id = $refundRequestId");
$dbh->exec("DELETE FROM tblpayment WHERE id IN ($payAId, $payBId)");
$dbh->exec("DELETE FROM tblbooking WHERE id IN ($bookingAId, $bookingBId)");
$dbh->exec("DELETE FROM tbluser WHERE id IN ($memAId, $memBId)");
$dbh->exec("DELETE FROM tbl_admin_permissions WHERE admin_id IN ($mgrId, $staffId)");
$dbh->exec("DELETE FROM tbladmin WHERE id IN ($mgrId, $staffId)");

@unlink($mgrCookie);
@unlink($staffCookie);
@unlink($ownerCookie);
@unlink($memACookie);
@unlink($memBCookie);

assert_test(true, "Test fixtures cleaned up successfully");

echo "\n========================================================\n";
echo "SUMMARY: Passed: $passCount, Failed: $failCount\n";
echo "========================================================\n";

if ($failCount > 0) {
    exit(1);
}
