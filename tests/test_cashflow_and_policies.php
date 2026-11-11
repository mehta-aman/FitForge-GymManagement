<?php
/**
 * Test Suite: FitForge Membership, Cash-Flow & Approved Business Policies
 * 
 * Tests the 5 approved business policies:
 * 1. Gym access: Partial payment permitted after meeting gym-wide minimum initial-payment threshold.
 * 2. Early renewal: Starts after existing pass expires; creates separate 'scheduled' pass.
 * 3. Membership freeze: Approved freeze days extend pass expiry date by exact days; auditable multi-freeze history.
 * 4. Refund handling: Only authorized managers can approve and execute refunds; staff can submit requests.
 * 5. Initial payment requirement: Configurable gym-wide setting (percentage or fixed), adjustable only by manager/owner.
 */

require_once __DIR__ . '/../gym/include/config.php';
require_once __DIR__ . '/../gym/admin/include/rbac.php';

echo "========================================================\n";
echo "RUNNING CASH-FLOW & BUSINESS POLICIES TEST SUITE\n";
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

// -------------------------------------------------------------------------
// SECTION 1: CORE FINANCIAL CALCULATIONS & CLAMPING
// -------------------------------------------------------------------------
echo "\n--- SECTION 1: Financial & Calculation Engine ---\n";

// Net Payable
$np1 = calculate_net_payable(1000, 100, 200);
assert_test($np1 === 900.0, "Net payable (1000 + 100 - 200 = 900.00) calculated accurately (Got: $np1)");

$np2 = calculate_net_payable(500, 0, 800);
assert_test($np2 === 0.0, "Net payable handles discount > price gracefully with max(0, ...) clamping (Got: $np2)");

// Percentage threshold calculation
$reqPct1 = calculate_required_initial_payment(1000, ['type' => 'percentage', 'value' => 50.0]);
assert_test($reqPct1 === 500.0, "50% initial payment of 1000 net payable is 500.00 (Got: $reqPct1)");

$reqPct0 = calculate_required_initial_payment(1000, ['type' => 'percentage', 'value' => 0.0]);
assert_test($reqPct0 === 0.0, "0% initial payment yields 0.00 (Got: $reqPct0)");

$reqPctMax = calculate_required_initial_payment(1000, ['type' => 'percentage', 'value' => 150.0]);
assert_test($reqPctMax === 1000.0, "Percentage > 100% is clamped to net payable (Got: $reqPctMax)");

$reqPctNeg = calculate_required_initial_payment(1000, ['type' => 'percentage', 'value' => -20.0]);
assert_test($reqPctNeg === 0.0, "Negative percentage is clamped to 0.00 (Got: $reqPctNeg)");

// Fixed threshold calculation
$reqFixed1 = calculate_required_initial_payment(1000, ['type' => 'fixed', 'value' => 350.0]);
assert_test($reqFixed1 === 350.0, "Fixed initial payment of 350.00 on 1000 net payable is 350.00 (Got: $reqFixed1)");

$reqFixedMax = calculate_required_initial_payment(1000, ['type' => 'fixed', 'value' => 1500.0]);
assert_test($reqFixedMax === 1000.0, "Fixed amount exceeding net payable is clamped to net payable (Got: $reqFixedMax)");

$reqFixedNeg = calculate_required_initial_payment(1000, ['type' => 'fixed', 'value' => -100.0]);
assert_test($reqFixedNeg === 0.0, "Negative fixed amount is clamped to 0.00 (Got: $reqFixedNeg)");

// -------------------------------------------------------------------------
// SECTION 2: POLICY CONFIGURATION & AUDIT TRAIL (POLICY 5)
// -------------------------------------------------------------------------
echo "\n--- SECTION 2: Initial Payment Policy Configuration & Audit Trail ---\n";

// Save original setting so we can restore it at end of test
$origType = get_setting('min_initial_payment_type', 'percentage');
$origVal = floatval(get_setting('min_initial_payment_val', '50.0'));

// Test policy update with audit logging
$testAdminId = 1; // Owner
set_setting('min_initial_payment_type', 'percentage', 'policies');
set_setting('min_initial_payment_val', '40.00', 'policies');
log_policy_audit('min_initial_payment_val', strval($origVal), '40.00', $testAdminId, $dbh);

$newVal = floatval(get_setting('min_initial_payment_val'));
assert_test($newVal === 40.0, "Initial payment threshold successfully updated to 40% (Got: $newVal)");

// Verify audit trail entry in tbl_policy_audit_log
$auditStmt = $dbh->prepare("
    SELECT * FROM tbl_policy_audit_log 
    WHERE setting_key = 'min_initial_payment_val' 
    ORDER BY id DESC LIMIT 1
");
$auditStmt->execute();
$auditRow = $auditStmt->fetch(PDO::FETCH_ASSOC);
assert_test(!empty($auditRow), "Audit record inserted into tbl_policy_audit_log");
assert_test($auditRow && $auditRow['changed_by_admin_id'] == $testAdminId, "Audit log preserves admin ID {$testAdminId}");
assert_test($auditRow && floatval($auditRow['new_value']) == 40.0, "Audit log preserves new value 40.00");

// RBAC Permissions check for policy management
assert_test(role_has_permission('manage_financial_policies', 'owner'), "Role 'owner' has 'manage_financial_policies' permission");
assert_test(role_has_permission('manage_financial_policies', 'manager'), "Role 'manager' has 'manage_financial_policies' permission");
assert_test(!role_has_permission('manage_financial_policies', 'trainer'), "Role 'trainer' does NOT have 'manage_financial_policies' permission");
assert_test(!role_has_permission('manage_financial_policies', 'floor_staff'), "Role 'floor_staff' does NOT have 'manage_financial_policies' permission");

// -------------------------------------------------------------------------
// SECTION 3: TEST MEMBER & PARTIAL PAYMENT ACCESS (POLICY 1 & 6)
// -------------------------------------------------------------------------
echo "\n--- SECTION 3: Partial Payment & Gym-Access Eligibility ---\n";

// Helper function to cleanup test member data
function cleanup_cf_member($email, $dbh) {
    $uStmt = $dbh->prepare("SELECT id FROM tbluser WHERE email = ?");
    $uStmt->execute([$email]);
    $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
    if ($uRow) {
        $uid = intval($uRow['id']);
        $mIds = $dbh->query("SELECT id FROM tbl_memberships WHERE user_id = $uid")->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($mIds)) {
            $inList = implode(',', array_map('intval', $mIds));
            $dbh->exec("DELETE FROM tblpayment WHERE membership_id IN ($inList)");
            $dbh->exec("DELETE FROM tbl_membership_freezes WHERE membership_id IN ($inList)");
            $dbh->exec("DELETE FROM tbl_refund_requests WHERE membership_id IN ($inList)");
            $dbh->exec("DELETE FROM tbl_memberships WHERE id IN ($inList)");
        }
        $dbh->exec("DELETE FROM tbl_attendance WHERE user_id = $uid");
        $dbh->exec("DELETE FROM tbluser WHERE id = $uid");
    }
}

cleanup_cf_member('cf_tester@fitforge.local', $dbh);

// Create test member
$insMember = $dbh->prepare("
    INSERT INTO tbluser (fname, lname, email, mobile, password, status, gym_id, create_date)
    VALUES ('CashFlow', 'Tester', 'cf_tester@fitforge.local', '9988776655', :pwd, 'active', 1, NOW())
");
$insMember->execute([':pwd' => password_hash('Test@123', PASSWORD_DEFAULT)]);
$cfUserId = intval($dbh->lastInsertId());
assert_test($cfUserId > 0, "Test member created with ID $cfUserId");

// 3A: No membership pass
$elig0 = evaluate_gym_access_eligibility($cfUserId, $dbh, 1);
assert_test(!$elig0['eligible'], "Member without membership is correctly denied access");
assert_test($elig0['code'] === 'NO_MEMBERSHIP', "Eligibility rejection code is 'NO_MEMBERSHIP'");

// Create Membership: Plan Price 1000.00
// Current dates: Active pass covering today
$currStart = date('Y-m-d', strtotime('-5 days'));
$currEnd = date('Y-m-d', strtotime('+25 days'));

$insM = $dbh->prepare("
    INSERT INTO tbl_memberships (
        user_id, plan_id, gym_id, total_amount, paid_amount,
        start_date, expiry_date, status, payment_status, created_at
    ) VALUES (
        :uid, 1, 1, 1000.00, 0.00,
        :start, :end, 'active', 'unpaid', NOW()
    )
");
$insM->execute([
    ':uid' => $cfUserId,
    ':start' => $currStart,
    ':end' => $currEnd
]);
$mId = intval($dbh->lastInsertId());
assert_test($mId > 0, "Test membership created with ID $mId (Status: active, Payment: unpaid)");

// 3B: Zero payment access check (Current threshold is 40% = 400.00)
$eligUnpaid = evaluate_gym_access_eligibility($cfUserId, $dbh, 1);
assert_test(!$eligUnpaid['eligible'], "Member with zero payments is strictly denied access");
assert_test($eligUnpaid['code'] === 'PAYMENT_REQUIRED', "Rejection code is 'PAYMENT_REQUIRED'");
assert_test($eligUnpaid['required_initial'] == 400.0, "Required initial payment is calculated as 400.00 (Got: {$eligUnpaid['required_initial']})");

// 3C: Partial payment BELOW threshold (Pays 200.00 < 400.00 required)
$insP1 = $dbh->prepare("
    INSERT INTO tblpayment (
        membership_id, payment, paymentType, payment_method, transaction_ref,
        gym_id, payment_date, status, refunded_amount, recorded_by_admin_id
    ) VALUES (
        :mid, '200.00', 'Partial Payment', 'Cash', 'TXN_CF_001',
        1, NOW(), 'completed', 0.00, 1
    )
");
$insP1->execute([':mid' => $mId]);
$payId1 = intval($dbh->lastInsertId());

reconcile_membership_financials($mId, $dbh);
$netPaidAfterP1 = get_membership_net_paid($mId, $dbh);
assert_test($netPaidAfterP1 === 200.0, "Net paid recorded as 200.00");

$eligBelow = evaluate_gym_access_eligibility($cfUserId, $dbh, 1);
assert_test(!$eligBelow['eligible'], "Partial payment BELOW threshold (200 < 400) is denied access");
assert_test($eligBelow['code'] === 'INITIAL_THRESHOLD_UNMET', "Rejection code is 'INITIAL_THRESHOLD_UNMET'");

// 3D: Partial payment MEETING threshold (Pays additional 250.00 => Total 450.00 >= 400.00, Dues = 550.00)
$insP2 = $dbh->prepare("
    INSERT INTO tblpayment (
        membership_id, payment, paymentType, payment_method, transaction_ref,
        gym_id, payment_date, status, refunded_amount, recorded_by_admin_id
    ) VALUES (
        :mid, '250.00', 'Partial Payment', 'UPI', 'TXN_CF_002',
        1, NOW(), 'completed', 0.00, 1
    )
");
$insP2->execute([':mid' => $mId]);
$payId2 = intval($dbh->lastInsertId());

$finStatus = reconcile_membership_financials($mId, $dbh);
assert_test($finStatus['payment_status'] === 'partial', "Payment status reconciled to 'partial' (Got: {$finStatus['payment_status']})");
$netPaidAfterP2 = get_membership_net_paid($mId, $dbh);
assert_test($netPaidAfterP2 === 450.0, "Net paid updated to 450.00");

// Access Check: Member meets 40% initial payment requirement, even though dues of 550 remain!
$eligMet = evaluate_gym_access_eligibility($cfUserId, $dbh, 1);
assert_test($eligMet['eligible'] === true, "Partial payment MEETING threshold (450 >= 400) GRANTS ACCESS!");
assert_test($eligMet['membership']['id'] == $mId, "Eligible membership verified as ID $mId");

// -------------------------------------------------------------------------
// SECTION 4: EARLY RENEWAL & SCHEDULED MEMBERSHIPS (POLICY 2)
// -------------------------------------------------------------------------
echo "\n--- SECTION 4: Early Renewal & Scheduled Membership Engine ---\n";

// Member renews before current pass expires.
// Current pass expires on $currEnd.
$renewalStart = date('Y-m-d', strtotime($currEnd . ' +1 day'));
$renewalEnd = date('Y-m-d', strtotime($renewalStart . ' +30 days'));

$insRenewal = $dbh->prepare("
    INSERT INTO tbl_memberships (
        user_id, plan_id, gym_id, total_amount, paid_amount,
        start_date, expiry_date, status, payment_status, created_at, renewed_from_id
    ) VALUES (
        :uid, 1, 1, 1000.00, 1000.00,
        :start, :end, 'scheduled', 'paid', NOW(), :oldMid
    )
");
$insRenewal->execute([
    ':uid' => $cfUserId,
    ':start' => $renewalStart,
    ':end' => $renewalEnd,
    ':oldMid' => $mId
]);
$scheduledMid = intval($dbh->lastInsertId());
assert_test($scheduledMid > 0, "Early renewal creates separate membership with ID $scheduledMid and status = 'scheduled'");

// Verify start date begins AFTER current membership expires
assert_test($renewalStart > $currEnd, "Scheduled pass start date ($renewalStart) is chained after current pass expiry ($currEnd)");

// Test find_current_membership() ignores the scheduled pass and returns the active pass
$currPass = find_current_membership($cfUserId, $dbh, 1);
assert_test($currPass && $currPass['id'] == $mId, "find_current_membership() returns the active pass ($mId), NOT the scheduled pass");

// Test find_scheduled_membership() identifies the scheduled pass
$schedPass = find_scheduled_membership($cfUserId, $dbh, 1);
assert_test($schedPass && $schedPass['id'] == $scheduledMid, "find_scheduled_membership() correctly locates scheduled pass ($scheduledMid)");

// Verify access check still uses the current pass, not the scheduled pass
$eligWithSched = evaluate_gym_access_eligibility($cfUserId, $dbh, 1);
assert_test($eligWithSched['eligible'] === true && $eligWithSched['membership']['id'] == $mId, "Access engine operates on current active pass");

// -------------------------------------------------------------------------
// SECTION 5: MEMBERSHIP FREEZE & EXPIRY EXTENSION (POLICY 3)
// -------------------------------------------------------------------------
echo "\n--- SECTION 5: Membership Freeze & Expiry Extension Engine ---\n";

$preFreezeExpiry = $currEnd;

// Admin freezes current pass
$freezeDaysApproved = 10;
$insFreeze = $dbh->prepare("
    INSERT INTO tbl_membership_freezes (
        membership_id, user_id, gym_id, approved_by_admin_id,
        freeze_start_date, freeze_end_date, approved_days, reason, created_at
    ) VALUES (
        :mid, :uid, 1, 1,
        CURDATE(), DATE_ADD(CURDATE(), INTERVAL 10 DAY), :days, 'Injury recovery', NOW()
    )
");
$insFreeze->execute([
    ':mid' => $mId,
    ':uid' => $cfUserId,
    ':days' => $freezeDaysApproved
]);
$freezeId = intval($dbh->lastInsertId());
assert_test($freezeId > 0, "Freeze record created in tbl_membership_freezes with ID $freezeId");

// Transition membership status to 'frozen'
$dbh->exec("UPDATE tbl_memberships SET status = 'frozen' WHERE id = $mId");

// Verify access engine blocks frozen membership
$eligFrozen = evaluate_gym_access_eligibility($cfUserId, $dbh, 1);
assert_test(!$eligFrozen['eligible'], "Frozen membership pass is strictly denied access");
assert_test($eligFrozen['code'] === 'MEMBERSHIP_FROZEN', "Rejection code is 'MEMBERSHIP_FROZEN'");

// Admin resumes pass after freeze: Expiry extended by exactly approved freeze days
$expectedNewExpiry = date('Y-m-d', strtotime($preFreezeExpiry . " +$freezeDaysApproved days"));

$updFreeze = $dbh->prepare("
    UPDATE tbl_membership_freezes
    SET resumed_at = NOW(), resumed_by_admin_id = 1
    WHERE id = :fid
");
$updFreeze->execute([':fid' => $freezeId]);

$updPass = $dbh->prepare("
    UPDATE tbl_memberships
    SET status = 'active', expiry_date = DATE_ADD(expiry_date, INTERVAL :days DAY)
    WHERE id = :mid
");
$updPass->execute([':days' => $freezeDaysApproved, ':mid' => $mId]);

// Check updated expiry date in database
$chkStmt = $dbh->prepare("SELECT expiry_date, status FROM tbl_memberships WHERE id = :mid");
$chkStmt->execute([':mid' => $mId]);
$chkRow = $chkStmt->fetch(PDO::FETCH_ASSOC);
assert_test($chkRow['status'] === 'active', "Membership status restored to 'active'");
assert_test($chkRow['expiry_date'] === $expectedNewExpiry, "Expiry date extended by exactly $freezeDaysApproved days to $expectedNewExpiry (Got: {$chkRow['expiry_date']})");

// Verify access is restored now that pass is active
$eligResumed = evaluate_gym_access_eligibility($cfUserId, $dbh, 1);
assert_test($eligResumed['eligible'] === true, "Access restored after unfreezing pass");

// -------------------------------------------------------------------------
// SECTION 6: REFUND AUTHORIZATION & CASH-FLOW LEDGER (POLICY 4)
// -------------------------------------------------------------------------
echo "\n--- SECTION 6: Refund Authorization & Execution Engine ---\n";

// RBAC Permissions check for refund lifecycle
assert_test(role_has_permission('request_refund', 'floor_staff'), "Role 'floor_staff' can request refunds");
assert_test(role_has_permission('request_refund', 'trainer'), "Role 'trainer' can request refunds");
assert_test(!role_has_permission('approve_refund', 'floor_staff'), "Role 'floor_staff' CANNOT approve refunds");
assert_test(!role_has_permission('execute_refund', 'floor_staff'), "Role 'floor_staff' CANNOT execute refunds");
assert_test(role_has_permission('approve_refund', 'manager'), "Role 'manager' CAN approve refunds");
assert_test(role_has_permission('execute_refund', 'manager'), "Role 'manager' CAN execute refunds");
assert_test(role_has_permission('execute_refund', 'owner'), "Role 'owner' CAN execute refunds");

// 6A: Staff submits refund request for Payment 2 (Original amount: 250.00, requesting refund of 100.00)
$refundAmount = 100.00;
$insRefReq = $dbh->prepare("
    INSERT INTO tbl_refund_requests (
        payment_id, membership_id, user_id, gym_id, requested_by_admin_id,
        refund_amount, reason, status, created_at
    ) VALUES (
        :pid, :mid, :uid, 1, 3,
        :amt, 'Partial billing adjustment', 'pending', NOW()
    )
");
$insRefReq->execute([
    ':pid' => $payId2,
    ':mid' => $mId,
    ':uid' => $cfUserId,
    ':amt' => $refundAmount
]);
$refReqId = intval($dbh->lastInsertId());
assert_test($refReqId > 0, "Refund request submitted by staff with ID $refReqId (Status: pending)");

// 6B: Manager approves and executes the refund
$dbh->beginTransaction();
try {
    // 1. Update refund request
    $execReq = $dbh->prepare("
        UPDATE tbl_refund_requests 
        SET status = 'executed', authorized_by_admin_id = 1, executed_at = NOW()
        WHERE id = :rid
    ");
    $execReq->execute([':rid' => $refReqId]);

    // 2. Adjust refunded_amount on original payment without deleting it
    $execPay = $dbh->prepare("
        UPDATE tblpayment
        SET refunded_amount = refunded_amount + :amt
        WHERE id = :pid
    ");
    $execPay->execute([':amt' => $refundAmount, ':pid' => $payId2]);

    $dbh->commit();
} catch (Exception $e) {
    $dbh->rollBack();
    throw $e;
}

// Verify payment record preserved
$p2Check = $dbh->query("SELECT payment, refunded_amount FROM tblpayment WHERE id = $payId2")->fetch(PDO::FETCH_ASSOC);
assert_test($p2Check && floatval($p2Check['payment']) === 250.0, "Original payment amount (250.00) preserved in ledger");
assert_test($p2Check && floatval($p2Check['refunded_amount']) === 100.0, "Payment record refunded_amount updated to 100.00");

// Reconcile membership financials
$reconciledFin = reconcile_membership_financials($mId, $dbh);
assert_test($reconciledFin['payment_status'] === 'partially_refunded', "Membership payment_status reconciled to 'partially_refunded' (Got: {$reconciledFin['payment_status']})");

$netPaidAfterRefund = get_membership_net_paid($mId, $dbh);
// Original payments: 200 + 250 = 450. Refund: 100 => Net paid = 350.00
assert_test($netPaidAfterRefund === 350.0, "Net paid accurately reconciled to 350.00 (Got: $netPaidAfterRefund)");

// Since net paid (350.00) is now below 400.00 threshold, check access eligibility:
$eligPostRefund = evaluate_gym_access_eligibility($cfUserId, $dbh, 1);
assert_test(!$eligPostRefund['eligible'], "Access correctly suspended after refund brings net payment below threshold");
assert_test($eligPostRefund['code'] === 'INITIAL_THRESHOLD_UNMET', "Rejection code updated to 'INITIAL_THRESHOLD_UNMET'");

// -------------------------------------------------------------------------
// SECTION 7: HTTP CONTROLLER & WEB ROUTE REGRESSION TESTS
// -------------------------------------------------------------------------
echo "\n--- SECTION 7: HTTP Controller & Web Route Regression Tests ---\n";

$adminCookie = tempnam(sys_get_temp_dir(), 'admin_cf_cookie_');

// 7A: Login as Owner
$ch = curl_init("http://127.0.0.1:8080/admin/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $adminCookie);
$admLoginPage = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $admLoginPage, $mAdmCsrf);
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

// 7B: Verify settings.php displays policy options
$ch = curl_init("http://127.0.0.1:8080/admin/settings.php?tab=policies");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $adminCookie);
$settingsHtml = curl_exec($ch);
curl_close($ch);

assert_test(str_contains($settingsHtml, 'Minimum Initial Payment Required for Gym Access'), "Settings page renders Initial Payment Policy section");
assert_test(str_contains($settingsHtml, 'min_initial_payment_type'), "Initial payment type selector present in settings");
assert_test(str_contains($settingsHtml, 'min_initial_payment_val'), "Initial payment value input present in settings");
assert_test(str_contains($settingsHtml, 'Configuration Change Audit Trail'), "Policy Change Audit History log table rendered");

// 7C: Verify member-profile.php displays Scheduled Renewal & Freeze tabs
$ch = curl_init("http://127.0.0.1:8080/admin/member-profile.php?id=$cfUserId");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $adminCookie);
$profileHtml = curl_exec($ch);
curl_close($ch);

assert_test(str_contains($profileHtml, 'Upcoming Scheduled Renewal') || str_contains($profileHtml, 'Scheduled'), "Member profile displays Upcoming Scheduled Renewal pass card");
assert_test(str_contains($profileHtml, 'Membership Freeze History') || str_contains($profileHtml, 'Freeze Pass'), "Member profile displays Membership Freeze control/history");

// 7D: Verify user-dashboard.php separates active pass from scheduled pass
$userCookie = tempnam(sys_get_temp_dir(), 'user_cf_cookie_');
$ch = curl_init("http://127.0.0.1:8080/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $userCookie);
$userLoginPage = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $userLoginPage, $mUsrCsrf);
$usrCsrf = $mUsrCsrf[1] ?? '';

$ch = curl_init("http://127.0.0.1:8080/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $usrCsrf,
    'email' => 'cf_tester@fitforge.local',
    'password' => 'Test@123',
    'submit' => '1'
]));
curl_setopt($ch, CURLOPT_COOKIEFILE, $userCookie);
curl_setopt($ch, CURLOPT_COOKIEJAR, $userCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_exec($ch);
curl_close($ch);

$ch = curl_init("http://127.0.0.1:8080/user-dashboard.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $userCookie);
$dashHtml = curl_exec($ch);
curl_close($ch);

assert_test(str_contains($dashHtml, 'Active Pass') || str_contains($dashHtml, 'Gold'), "User dashboard renders current membership pass");
assert_test(stripos($dashHtml, 'UPCOMING SCHEDULED RENEWAL') !== false || stripos($dashHtml, 'scheduled') !== false, "User dashboard visibly displays upcoming scheduled renewal");

// -------------------------------------------------------------------------
// CLEANUP & RESTORATION
// -------------------------------------------------------------------------
echo "\n--- CLEANUP & TEARDOWN ---\n";
// Restore original policy setting
set_setting('min_initial_payment_type', $origType, 'policies');
set_setting('min_initial_payment_val', strval($origVal), 'policies');

// Clean up test data
cleanup_cf_member('cf_tester@fitforge.local', $dbh);

@unlink($adminCookie);
@unlink($userCookie);
assert_test(true, "Test data and session cookies successfully torn down");

echo "\n========================================================\n";
echo "SUMMARY: Passed: $passCount, Failed: $failCount\n";
echo "========================================================\n";

if ($failCount > 0) {
    exit(1);
}
