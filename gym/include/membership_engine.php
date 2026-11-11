<?php
/**
 * FitForge Core Membership, Access Control, Cash-Flow & Policy Engine
 * Centralized business logic for gym access eligibility, renewals, freezes, and refunds.
 */

// Global cache for policy settings
global $FITFORGE_POLICY_CACHE;
$FITFORGE_POLICY_CACHE = null;

/**
 * Fetch gym-wide minimum initial payment configuration.
 * Returns array: ['type' => 'percentage'|'fixed', 'value' => float]
 */
function get_initial_payment_policy(PDO $dbh, int $gymId = 1): array {
    global $FITFORGE_POLICY_CACHE;
    if ($FITFORGE_POLICY_CACHE !== null && isset($FITFORGE_POLICY_CACHE[$gymId])) {
        return $FITFORGE_POLICY_CACHE[$gymId];
    }

    $type = get_setting('min_initial_payment_type', 'percentage', $dbh);
    if (!in_array($type, ['percentage', 'fixed'], true)) {
        $type = 'percentage';
    }

    $val = floatval(get_setting('min_initial_payment_val', '50.00', $dbh));
    if ($val < 0) {
        $val = 0.00;
    }
    if ($type === 'percentage' && $val > 100.0) {
        $val = 100.0;
    }

    if ($FITFORGE_POLICY_CACHE === null) {
        $FITFORGE_POLICY_CACHE = [];
    }
    $FITFORGE_POLICY_CACHE[$gymId] = [
        'type' => $type,
        'value' => $val
    ];
    return $FITFORGE_POLICY_CACHE[$gymId];
}

/**
 * Invalidate the initial payment policy cache.
 */
function clear_policy_cache(): void {
    global $FITFORGE_POLICY_CACHE;
    $FITFORGE_POLICY_CACHE = null;
}

/**
 * Log auditable changes to policy configurations.
 */
function log_policy_audit(string $key, ?string $oldVal, ?string $newVal, int $adminId, PDO $dbh, int $gymId = 1): void {
    clear_policy_cache();
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $dbh->prepare("INSERT INTO tbl_policy_audit_log (setting_key, old_value, new_value, changed_by_admin_id, ip_address, gym_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$key, $oldVal, $newVal, $adminId, $ip, $gymId]);
    } catch (Exception $e) {
        // Fallback gracefully without breaking transaction
    }
}

/**
 * Calculate net payable amount for a plan or membership.
 * Net Payable = Base Price + Registration Fee - Discount
 */
function calculate_net_payable(float $price, float $regFee = 0.0, float $discount = 0.0): float {
    return max(0.0, ($price + $regFee) - $discount);
}

/**
 * Calculate required initial payment based on the active policy.
 */
function calculate_required_initial_payment(float $netPayable, array $policy): float {
    $netPayable = max(0.0, $netPayable);
    $type = $policy['type'] ?? 'percentage';
    $val = max(0.0, floatval($policy['value'] ?? 0.0));

    if ($type === 'fixed') {
        return min($netPayable, $val);
    }
    // Percentage
    $pct = min(100.0, $val);
    return min($netPayable, round($netPayable * ($pct / 100.0), 2));
}

/**
 * Reconcile and calculate total successful net payments allocated to a membership.
 * Net Allocated Payments = SUM(payment - refunded_amount)
 */
function get_membership_net_paid(int $membershipId, PDO $dbh): float {
    // 1. Check direct payments linked to membership_id
    $stmt1 = $dbh->prepare("SELECT COALESCE(SUM(CAST(payment AS DECIMAL(10,2)) - CAST(COALESCE(refunded_amount,0) AS DECIMAL(10,2))), 0) FROM tblpayment WHERE membership_id = ?");
    $stmt1->execute([$membershipId]);
    $paid1 = floatval($stmt1->fetchColumn());

    if ($paid1 > 0) {
        return max(0.0, $paid1);
    }

    // 2. Fallback to booking_id linkage if membership_id is null
    $stmt2 = $dbh->prepare("SELECT booking_id FROM tbl_memberships WHERE id = ?");
    $stmt2->execute([$membershipId]);
    $bookingId = $stmt2->fetchColumn();

    if ($bookingId) {
        $stmt3 = $dbh->prepare("SELECT COALESCE(SUM(CAST(payment AS DECIMAL(10,2)) - CAST(COALESCE(refunded_amount,0) AS DECIMAL(10,2))), 0) FROM tblpayment WHERE bookingID = ?");
        $stmt3->execute([$bookingId]);
        return max(0.0, floatval($stmt3->fetchColumn()));
    }

    return 0.0;
}

/**
 * Find the single currently active or frozen membership pass that is valid today.
 * Strictly ignores future scheduled renewals (where start_date > today).
 */
function find_current_membership(int $userId, PDO $dbh, int $gymId = 1): ?array {
    $today = date('Y-m-d');
    
    // 1. Look for currently active or frozen pass that encompasses today
    $stmt = $dbh->prepare("
        SELECT m.*, p.titlename, p.titlename as plan_name, p.PackageDuratiobn, p.Price, 
               p.registration_fee, p.discount, c.category_name,
               DATEDIFF(m.expiry_date, :today1) as days_left
        FROM tbl_memberships m
        LEFT JOIN tbladdpackage p ON m.plan_id = p.id
        LEFT JOIN tblcategory c ON p.category = c.id
        WHERE m.user_id = :uid 
          AND m.gym_id = :gym
          AND m.status IN ('active', 'frozen')
          AND m.start_date <= :today2
          AND m.expiry_date >= :today3
        ORDER BY m.expiry_date DESC, m.id DESC
        LIMIT 1
    ");
    $stmt->execute([
        ':uid' => $userId,
        ':gym' => $gymId,
        ':today1' => $today,
        ':today2' => $today,
        ':today3' => $today
    ]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($current) {
        return $current;
    }

    // 2. If no active pass valid today, find frozen pass (even if expiry is today/past during freeze)
    $fStmt = $dbh->prepare("
        SELECT m.*, p.titlename, p.titlename as plan_name, p.PackageDuratiobn, p.Price, 
               p.registration_fee, p.discount, c.category_name,
               DATEDIFF(m.expiry_date, :today) as days_left
        FROM tbl_memberships m
        LEFT JOIN tbladdpackage p ON m.plan_id = p.id
        LEFT JOIN tblcategory c ON p.category = c.id
        WHERE m.user_id = :uid 
          AND m.gym_id = :gym
          AND m.status = 'frozen'
        ORDER BY m.id DESC
        LIMIT 1
    ");
    $fStmt->execute([':uid' => $userId, ':gym' => $gymId, ':today' => $today]);
    $frozen = $fStmt->fetch(PDO::FETCH_ASSOC);
    if ($frozen) {
        return $frozen;
    }

    // 3. Fallback: Find most recently expired pass (excluding scheduled passes)
    $eStmt = $dbh->prepare("
        SELECT m.*, p.titlename, p.titlename as plan_name, p.PackageDuratiobn, p.Price, 
               p.registration_fee, p.discount, c.category_name,
               DATEDIFF(m.expiry_date, :today) as days_left
        FROM tbl_memberships m
        LEFT JOIN tbladdpackage p ON m.plan_id = p.id
        LEFT JOIN tblcategory c ON p.category = c.id
        WHERE m.user_id = :uid 
          AND m.gym_id = :gym
          AND m.status != 'scheduled'
          AND m.start_date <= :today
        ORDER BY m.expiry_date DESC, m.id DESC
        LIMIT 1
    ");
    $eStmt->execute([':uid' => $userId, ':gym' => $gymId, ':today' => $today]);
    return $eStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Find future scheduled renewal membership (if any).
 */
function find_scheduled_membership(int $userId, PDO $dbh, int $gymId = 1): ?array {
    $today = date('Y-m-d');
    $stmt = $dbh->prepare("
        SELECT m.*, p.titlename, p.titlename as plan_name, p.PackageDuratiobn, p.Price, 
               p.registration_fee, p.discount, c.category_name,
               DATEDIFF(m.start_date, :today) as days_until_start
        FROM tbl_memberships m
        LEFT JOIN tbladdpackage p ON m.plan_id = p.id
        LEFT JOIN tblcategory c ON p.category = c.id
        WHERE m.user_id = :uid 
          AND m.gym_id = :gym
          AND (m.status = 'scheduled' OR (m.status = 'active' AND m.start_date > :today2))
        ORDER BY m.start_date ASC, m.id ASC
        LIMIT 1
    ");
    $stmt->execute([':uid' => $userId, ':gym' => $gymId, ':today' => $today, ':today2' => $today]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Centralized Server-Side Access Eligibility Engine
 * Applies all 5 approved business policies consistently across checkin, QR, and attendance.
 *
 * @return array ['eligible' => bool, 'reason' => string, 'membership' => array|null, 'dues' => float, 'code' => string]
 */
function evaluate_gym_access_eligibility(int $userId, PDO $dbh, int $gymId = 1): array {
    $today = date('Y-m-d');

    // 1. Verify Member Account Exists & Active
    $uStmt = $dbh->prepare("SELECT id, fname, lname, status FROM tbluser WHERE id = :uid LIMIT 1");
    $uStmt->execute([':uid' => $userId]);
    $user = $uStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return [
            'eligible' => false,
            'reason' => 'Member account not found in system.',
            'code' => 'USER_NOT_FOUND',
            'membership' => null,
            'dues' => 0.0
        ];
    }

    if ($user['status'] !== 'active') {
        return [
            'eligible' => false,
            'reason' => 'Member account is currently deactivated or suspended.',
            'code' => 'ACCOUNT_INACTIVE',
            'membership' => null,
            'dues' => 0.0
        ];
    }

    // 2. Fetch Current Membership Pass (Excluding future scheduled passes)
    $current = find_current_membership($userId, $dbh, $gymId);

    // If no current pass exists, check if only a scheduled future pass exists
    if (!$current) {
        $scheduled = find_scheduled_membership($userId, $dbh, $gymId);
        if ($scheduled) {
            $startDateFmt = date('d M Y', strtotime($scheduled['start_date']));
            return [
                'eligible' => false,
                'reason' => "Membership is scheduled and activates on {$startDateFmt}. Access is not yet permitted.",
                'code' => 'MEMBERSHIP_SCHEDULED',
                'membership' => $scheduled,
                'dues' => 0.0
            ];
        }

        return [
            'eligible' => false,
            'reason' => 'No active membership pass found. Please assign or purchase a membership.',
            'code' => 'NO_MEMBERSHIP',
            'membership' => null,
            'dues' => 0.0
        ];
    }

    // 3. Status Gates
    if ($current['status'] === 'frozen') {
        return [
            'eligible' => false,
            'reason' => 'Membership is currently frozen/suspended. Please resume your membership at reception.',
            'code' => 'MEMBERSHIP_FROZEN',
            'membership' => $current,
            'dues' => 0.0
        ];
    }

    if ($current['status'] === 'cancelled') {
        return [
            'eligible' => false,
            'reason' => 'Membership has been cancelled.',
            'code' => 'MEMBERSHIP_CANCELLED',
            'membership' => $current,
            'dues' => 0.0
        ];
    }

    if ($current['status'] === 'expired' || $current['expiry_date'] < $today) {
        $expFmt = date('d M Y', strtotime($current['expiry_date']));
        return [
            'eligible' => false,
            'reason' => "Membership expired on {$expFmt}. Please renew your pass to regain access.",
            'code' => 'MEMBERSHIP_EXPIRED',
            'membership' => $current,
            'dues' => 0.0
        ];
    }

    // 4. Financial Calculations & Initial Payment Requirement (Approved Policy 1 & 5)
    $planPrice = floatval(($current['total_amount'] > 0) ? $current['total_amount'] : ($current['Price'] ?? 0.0));
    $regFee = floatval($current['registration_fee'] ?? 0.0);
    $discount = floatval($current['discount'] ?? 0.0);
    $netPayable = calculate_net_payable($planPrice, $regFee, $discount);

    // Sum actual successful net payments allocated to this exact membership
    $netAllocatedPayments = get_membership_net_paid(intval($current['id']), $dbh);
    $outstandingDues = max(0.0, $netPayable - $netAllocatedPayments);

    // Evaluate against gym-wide minimum initial-payment requirement
    $policy = get_initial_payment_policy($dbh, $gymId);
    $requiredInitialPayment = calculate_required_initial_payment($netPayable, $policy);

    // Rule: Zero-payment membership must NOT receive access unless policy explicitly permits it (threshold = 0)
    if ($netAllocatedPayments <= 0.0 && $requiredInitialPayment > 0.0) {
        $reqFormatted = format_currency($requiredInitialPayment, $dbh);
        return [
            'eligible' => false,
            'reason' => "Minimum initial payment of {$reqFormatted} is required before access is granted. (Paid: 0.00)",
            'code' => 'PAYMENT_REQUIRED',
            'membership' => $current,
            'dues' => $outstandingDues,
            'required_initial' => $requiredInitialPayment,
            'net_paid' => $netAllocatedPayments
        ];
    }

    if ($netAllocatedPayments < $requiredInitialPayment) {
        $reqFormatted = format_currency($requiredInitialPayment, $dbh);
        $paidFormatted = format_currency($netAllocatedPayments, $dbh);
        $shortfall = format_currency($requiredInitialPayment - $netAllocatedPayments, $dbh);
        return [
            'eligible' => false,
            'reason' => "Initial payment threshold unmet. Minimum required: {$reqFormatted} (Paid: {$paidFormatted}, Due for Access: {$shortfall}).",
            'code' => 'INITIAL_THRESHOLD_UNMET',
            'membership' => $current,
            'dues' => $outstandingDues,
            'required_initial' => $requiredInitialPayment,
            'net_paid' => $netAllocatedPayments
        ];
    }

    // 5. Access Granted: Meets all date, status, and initial payment requirements!
    return [
        'eligible' => true,
        'reason' => 'Access Granted',
        'code' => 'ACCESS_GRANTED',
        'membership' => $current,
        'dues' => $outstandingDues,
        'required_initial' => $requiredInitialPayment,
        'net_paid' => $netAllocatedPayments
    ];
}

/**
 * Reconcile a membership's paid_amount and payment_status after a payment or refund.
 */
function reconcile_membership_financials(int $membershipId, PDO $dbh): array {
    $stmt = $dbh->prepare("
        SELECT m.id, m.total_amount, p.Price, p.registration_fee, p.discount
        FROM tbl_memberships m
        LEFT JOIN tbladdpackage p ON m.plan_id = p.id
        WHERE m.id = ?
    ");
    $stmt->execute([$membershipId]);
    $mem = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mem) {
        return ['paid_amount' => 0.0, 'payment_status' => 'unpaid'];
    }

    $netPayable = calculate_net_payable(
        floatval(($mem['total_amount'] > 0) ? $mem['total_amount'] : ($mem['Price'] ?? 0)),
        floatval($mem['registration_fee'] ?? 0),
        floatval($mem['discount'] ?? 0)
    );

    $netPaid = get_membership_net_paid($membershipId, $dbh);

    // Check if any refunds were processed
    $refStmt = $dbh->prepare("SELECT COALESCE(SUM(refunded_amount), 0) FROM tblpayment WHERE membership_id = ?");
    $refStmt->execute([$membershipId]);
    $totalRefunded = floatval($refStmt->fetchColumn());

    $newStatus = 'unpaid';
    if ($totalRefunded > 0) {
        if ($netPaid <= 0.0) {
            $newStatus = 'refunded';
        } else {
            $newStatus = 'partially_refunded';
        }
    } else {
        if ($netPaid >= $netPayable && $netPayable > 0) {
            $newStatus = 'paid';
        } elseif ($netPaid > 0) {
            $newStatus = 'partial';
        }
    }

    $upd = $dbh->prepare("UPDATE tbl_memberships SET paid_amount = ?, payment_status = ? WHERE id = ?");
    $upd->execute([$netPaid, $newStatus, $membershipId]);

    return [
        'net_payable' => $netPayable,
        'paid_amount' => $netPaid,
        'balance_due' => max(0.0, $netPayable - $netPaid),
        'payment_status' => $newStatus
    ];
}
