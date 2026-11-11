<?php
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$bookingId = intval($_GET['bookingid'] ?? 0);
if ($bookingId <= 0) {
    header('location:index.php');
    exit();
}

$isOwner = is_owner();
$isManager = is_manager();

// Option A: Restricted Manager Financial Access Policy
if (!$isOwner) {
    if (!$isManager) {
        $_SESSION['rbac_error'] = "Access Restricted: You do not have permission to inspect payment records.";
        header('location:index.php');
        exit();
    }
    // Check if Manager has refund permissions
    $hasRefundPerm = has_permission('refunds.view_assigned') || has_permission('refunds.approve') || has_permission('refunds.process') || has_permission('approve_refund') || has_permission('execute_refund');
    if (!$hasRefundPerm) {
        $_SESSION['rbac_error'] = "Access Restricted: Option A Policy — Managers cannot browse payment records without authorized refund capability.";
        header('location:index.php');
        exit();
    }
    // Check if an authorized refund request exists for this booking
    $chkRefundCase = $dbh->prepare("SELECT COUNT(*) FROM tbl_refund_requests WHERE booking_id = ?");
    $chkRefundCase->execute([$bookingId]);
    if (intval($chkRefundCase->fetchColumn()) === 0) {
        $_SESSION['rbac_error'] = "Access Restricted: Option A Policy — Managers cannot browse general payment ledgers. You may only inspect specific refund cases.";
        header('location:index.php');
        exit();
    }
}

$msg = '';
$err = '';

// Handle Payment Submission (Strictly Restricted to Gym Owner)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_payment') {
    if (!$isOwner) {
        $err = "Unauthorized: Option A Policy strictly restricts recording payments to the Gym Owner.";
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = "Security token mismatch. Please try again.";
    } else {
        $payType = trim($_POST['payment_type'] ?? 'Cash');
        $amount = floatval($_POST['payment_amount'] ?? 0);
        $txnRef = trim($_POST['transaction_ref'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $adminId = intval($_SESSION['adminid'] ?? 1);

        if ($amount <= 0) {
            $err = "Please enter a valid payment amount greater than zero.";
        } else {
            try {
                $dbh->beginTransaction();

                // 1. Find linked membership
                $memStmt = $dbh->prepare("SELECT id, total_amount, paid_amount FROM tbl_memberships WHERE booking_id = ?");
                $memStmt->execute([$bookingId]);
                $mem = $memStmt->fetch(PDO::FETCH_ASSOC);
                $memId = $mem ? intval($mem['id']) : null;

                // 2. Insert into tblpayment with membership_id and recorded_by_admin_id
                $payStmt = $dbh->prepare("INSERT INTO tblpayment (bookingID, membership_id, paymentType, payment, payment_method, transaction_ref, notes, recorded_by_admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $payStmt->execute([$bookingId, $memId, $payType, $amount, $payType, $txnRef, $notes, $adminId]);
                $newPayId = $dbh->lastInsertId();

                // 3. Update tblbooking paymentType
                $updBooking = $dbh->prepare("UPDATE tblbooking SET paymentType = ? WHERE id = ?");
                $updBooking->execute([$payType, $bookingId]);

                // 4. Reconcile membership status & balance
                if ($memId) {
                    reconcile_membership_financials($memId, $dbh);
                }

                $dbh->commit();
                $msg = "Payment of " . format_currency($amount) . " successfully recorded.";
            } catch (Exception $e) {
                $dbh->rollBack();
                $err = "Transaction Error: " . $e->getMessage();
            }
        }
    }
}

// Handle Refund Request (Staff or Manager)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_refund') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = "Security token mismatch. Please try again.";
    } else {
        $payId = intval($_POST['payment_id'] ?? 0);
        $refundAmount = floatval($_POST['refund_amount'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $adminId = intval($_SESSION['adminid'] ?? 1);

        $chkPay = $dbh->prepare("SELECT p.*, b.userid FROM tblpayment p JOIN tblbooking b ON p.bookingID = b.id WHERE p.id = ? AND p.bookingID = ?");
        $chkPay->execute([$payId, $bookingId]);
        $payRow = $chkPay->fetch(PDO::FETCH_ASSOC);

        if (!$payRow) {
            $err = "Payment transaction record not found.";
        } elseif ($refundAmount <= 0) {
            $err = "Refund amount must be greater than zero.";
        } else {
            $alreadyRefunded = floatval($payRow['refunded_amount'] ?? 0);
            $maxRefundable = floatval($payRow['payment']) - $alreadyRefunded;

            if ($refundAmount > $maxRefundable) {
                $err = "Refund amount (" . format_currency($refundAmount) . ") exceeds refundable balance of " . format_currency($maxRefundable) . ".";
            } elseif (empty($reason)) {
                $err = "Please provide an auditable reason for the refund.";
            } else {
                $insRef = $dbh->prepare("INSERT INTO tbl_refund_requests (payment_id, membership_id, booking_id, user_id, refund_amount, reason, status, requested_by_admin_id) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
                $insRef->execute([$payId, $payRow['membership_id'] ?: NULL, $bookingId, $payRow['userid'], $refundAmount, $reason, $adminId]);
                $msg = "Refund request for " . format_currency($refundAmount) . " submitted. Awaiting manager approval.";
            }
        }
    }
}

// Handle Refund Approval & Execution (Approved Policy 4: Manager-Only with explicit refund permission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_refund') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = "Security token mismatch. Please try again.";
    } elseif (!is_owner() && !has_permission('refunds.approve') && !has_permission('refunds.process') && !has_permission('approve_refund') && !has_permission('execute_refund')) {
        $err = "Unauthorized: You do not possess explicit permission to approve or execute refunds.";
    } else {
        $reqId = intval($_POST['request_id'] ?? 0);
        $adminId = intval($_SESSION['adminid'] ?? 1);

        $findReq = $dbh->prepare("SELECT * FROM tbl_refund_requests WHERE id = ? AND booking_id = ? AND status = 'pending'");
        $findReq->execute([$reqId, $bookingId]);
        $req = $findReq->fetch(PDO::FETCH_ASSOC);

        if (!$req) {
            $err = "Pending refund request not found.";
        } else {
            $payId = $req['payment_id'];
            $chkPay = $dbh->prepare("SELECT * FROM tblpayment WHERE id = ?");
            $chkPay->execute([$payId]);
            $payRow = $chkPay->fetch(PDO::FETCH_ASSOC);

            $alreadyRefunded = floatval($payRow['refunded_amount'] ?? 0);
            $maxRefundable = floatval($payRow['payment']) - $alreadyRefunded;
            $refundAmount = floatval($req['refund_amount']);

            if ($refundAmount > $maxRefundable) {
                $err = "Cannot execute: Refund amount exceeds available transaction balance.";
            } else {
                try {
                    $dbh->beginTransaction();

                    // 1. Update tbl_refund_requests to executed
                    $updReq = $dbh->prepare("UPDATE tbl_refund_requests SET status = 'executed', authorized_by_admin_id = ?, executed_at = NOW() WHERE id = ?");
                    $updReq->execute([$adminId, $reqId]);

                    // 2. Update tblpayment refunded_amount and status (preserves original payment record)
                    $newRefunded = $alreadyRefunded + $refundAmount;
                    $payStatus = ($newRefunded >= floatval($payRow['payment'])) ? 'refunded' : 'partially_refunded';
                    $updPay = $dbh->prepare("UPDATE tblpayment SET refunded_amount = ?, status = ? WHERE id = ?");
                    $updPay->execute([$newRefunded, $payStatus, $payId]);

                    // 3. Reconcile membership financials
                    if (!empty($req['membership_id'])) {
                        reconcile_membership_financials(intval($req['membership_id']), $dbh);
                    } else {
                        // Lookup via booking
                        $memId = $dbh->query("SELECT id FROM tbl_memberships WHERE booking_id = $bookingId LIMIT 1")->fetchColumn();
                        if ($memId) {
                            reconcile_membership_financials(intval($memId), $dbh);
                        }
                    }

                    $dbh->commit();
                    $msg = "Refund of " . format_currency($refundAmount) . " approved and executed successfully by Manager.";
                } catch (Exception $e) {
                    $dbh->rollBack();
                    $err = "Execution Error: " . $e->getMessage();
                }
            }
        }
    }
}

// Handle Refund Rejection (Manager-Only with explicit reject permission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject_refund') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = "Security token mismatch. Please try again.";
    } elseif (!is_owner() && !has_permission('refunds.reject') && !has_permission('refunds.approve') && !has_permission('approve_refund')) {
        $err = "Unauthorized: You do not possess explicit permission to reject refunds.";
    } else {
        $reqId = intval($_POST['request_id'] ?? 0);
        $rejectionReason = trim($_POST['rejection_reason'] ?? 'Rejected by manager');
        $adminId = intval($_SESSION['adminid'] ?? 1);

        $updReq = $dbh->prepare("UPDATE tbl_refund_requests SET status = 'rejected', authorized_by_admin_id = ?, rejection_reason = ? WHERE id = ? AND booking_id = ? AND status = 'pending'");
        $updReq->execute([$adminId, $rejectionReason, $reqId, $bookingId]);
        $msg = "Refund request rejected.";
    }
}

// Fetch booking & member info
$stmt = $dbh->prepare("SELECT t1.id as bookingid, t1.booking_date as bookingdate, t1.paymentType,
                       t2.id as package_id, t2.titlename as title, t2.PackageDuratiobn, t2.Price as price, t2.Description,
                       t3.id as userid, t3.fname, t3.lname, t3.email, t3.mobile, t3.address, t3.city, t3.state,
                       t4.category_name, t5.PackageName
                       FROM tblbooking t1
                       JOIN tbladdpackage t2 ON t1.package_id = t2.id
                       JOIN tbluser t3 ON t1.userid = t3.id
                       LEFT JOIN tblcategory t4 ON t2.category = t4.id
                       LEFT JOIN tblpackage t5 ON t2.PackageType = t5.id
                       WHERE t1.id = ?");
$stmt->execute([$bookingId]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header('location:booking-history.php');
    exit();
}

// Fetch all payment installments
$payStmt = $dbh->prepare("SELECT * FROM tblpayment WHERE bookingID = ? ORDER BY id ASC");
$payStmt->execute([$bookingId]);
$payments = $payStmt->fetchAll(PDO::FETCH_ASSOC);

$totalPaid = 0;
$totalRefunded = 0;
foreach ($payments as $p) {
    $totalPaid += floatval($p['payment']);
    $totalRefunded += floatval($p['refunded_amount'] ?? 0);
}
$netCollected = max(0, $totalPaid - $totalRefunded);
$packagePrice = floatval($booking['price']);
$balance = max(0, $packagePrice - $netCollected);

// Fetch refund requests for this booking
$refReqStmt = $dbh->prepare("
    SELECT r.*, req_adm.name as requester_name, auth_adm.name as authorizer_name
    FROM tbl_refund_requests r
    LEFT JOIN tbladmin req_adm ON r.requested_by_admin_id = req_adm.id
    LEFT JOIN tbladmin auth_adm ON r.authorized_by_admin_id = auth_adm.id
    WHERE r.booking_id = ?
    ORDER BY r.id DESC
");
$refReqStmt->execute([$bookingId]);
$refundRequests = $refReqStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Booking #<?php echo $booking['bookingid']; ?> Ledger — Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php echo render_favicon_tags(); ?>
  <link rel="stylesheet" type="text/css" href="css/main.css">
  <link rel="stylesheet" type="text/css" href="css/ios-admin-glass.css">
  <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
</head>
<body class="app sidebar-mini rtl">
  <?php include 'include/header.php'; ?>
  <div class="app-sidebar__overlay" data-toggle="sidebar"></div>
  <?php include 'include/sidebar.php'; ?>

  <main class="app-content">
    <div class="app-title">
      <div>
        <h1>
          <?php if ($isOwner): ?>
            <i class="fa fa-file-text-o" style="color: var(--color-primary); margin-right: 8px;"></i>
            Booking &amp; Payment Ledger #<?php echo $booking['bookingid']; ?>
          <?php else: ?>
            <i class="fa fa-balance-scale" style="color: #fbbf24; margin-right: 8px;"></i>
            Refund Case Review #<?php echo $booking['bookingid']; ?>
          <?php endif; ?>
        </h1>
        <p><?php echo $isOwner ? 'Detailed receipt breakdown, payment installment history, and collections' : 'Case-specific authorized refund review and execution terminal'; ?></p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="<?php echo $isOwner ? 'booking-history.php' : 'memberships.php'; ?>"><?php echo $isOwner ? 'Bookings' : 'Memberships'; ?></a></li>
        <li class="breadcrumb-item active">#<?php echo $booking['bookingid']; ?></li>
      </ul>
    </div>

    <?php if ($msg): ?>
      <div class="ios-badge ios-badge-success" style="display: flex; margin-bottom: 24px; padding: 14px 20px; border-radius: 16px; font-size: 14px;">
        <i class="fa fa-check-circle" style="font-size: 18px; margin-right: 8px;"></i>
        <span><?php echo htmlentities($msg); ?></span>
      </div>
    <?php endif; ?>

    <?php if ($err): ?>
      <div class="ios-badge ios-badge-danger" style="display: flex; margin-bottom: 24px; padding: 14px 20px; border-radius: 16px; font-size: 14px;">
        <i class="fa fa-exclamation-triangle" style="font-size: 18px; margin-right: 8px;"></i>
        <span><?php echo htmlentities($err); ?></span>
      </div>
    <?php endif; ?>

    <div class="row">
      <!-- Member & Package Details -->
      <div class="col-lg-5">
        <!-- Member Summary -->
        <div class="tile">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="font-size: 17px; font-weight: 700; margin: 0;">
              <i class="fa fa-user" style="color: var(--color-primary); margin-right: 6px;"></i> Member Details
            </h3>
            <a href="member-profile.php?id=<?php echo $booking['userid']; ?>" class="btn btn-sm btn-outline-light" style="border-radius: 10px; font-size: 12px; padding: 3px 10px;">
              View Profile
            </a>
          </div>
          <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 14px; padding: 16px; font-size: 13.5px;">
            <div style="margin-bottom: 8px; display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Full Name:</span>
              <strong style="color: #fff;"><?php echo htmlspecialchars($booking['fname'] . ' ' . $booking['lname']); ?></strong>
            </div>
            <div style="margin-bottom: 8px; display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Email:</span>
              <span><?php echo htmlspecialchars($booking['email']); ?></span>
            </div>
            <div style="margin-bottom: 8px; display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Phone:</span>
              <span><?php echo htmlspecialchars($booking['mobile'] ?? 'N/A'); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Location:</span>
              <span><?php echo htmlspecialchars(($booking['city'] ? $booking['city'] . ', ' : '') . ($booking['state'] ?? '')); ?></span>
            </div>
          </div>
        </div>

        <!-- Package Details -->
        <div class="tile">
          <h3 style="font-size: 17px; font-weight: 700; margin-bottom: 16px;">
            <i class="fa fa-cube" style="color: var(--color-accent); margin-right: 6px;"></i> Package Specifications
          </h3>
          <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 14px; padding: 16px; font-size: 13.5px;">
            <div style="margin-bottom: 8px; display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Title:</span>
              <strong style="color: #fff;"><?php echo htmlspecialchars($booking['title']); ?></strong>
            </div>
            <div style="margin-bottom: 8px; display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Category:</span>
              <span><?php echo htmlspecialchars($booking['category_name'] ?? 'General'); ?></span>
            </div>
            <div style="margin-bottom: 8px; display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Duration:</span>
              <span><?php echo htmlspecialchars($booking['PackageDuratiobn'] ?? '1 Month'); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Booking Date:</span>
              <span><?php echo date('d M Y, h:i A', strtotime($booking['bookingdate'])); ?></span>
            </div>
          </div>
        </div>

        <!-- Record Payment Form (Strictly Owner Only, if balance remains) -->
        <?php if ($isOwner && $balance > 0): ?>
          <div class="tile" style="border: 1px solid rgba(56, 189, 248, 0.3);">
            <h3 style="font-size: 17px; font-weight: 700; margin-bottom: 16px;">
              <i class="fa fa-plus-circle" style="color: #34d399; margin-right: 6px;"></i> Record Payment Installment
            </h3>
            <form method="post">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="action" value="record_payment">
              
              <div class="form-group">
                <label style="font-weight: 600; font-size: 13px;">Payment Amount (<?php echo get_currency_symbol(); ?>) *</label>
                <div class="input-group">
                  <input type="number" step="0.01" max="<?php echo $balance; ?>" name="payment_amount" id="payAmountInput" class="form-control" value="<?php echo $balance; ?>" required style="border-radius: 12px 0 0 12px;">
                  <div class="input-group-append">
                    <button type="button" class="btn btn-outline-light" onclick="$('#payAmountInput').val('<?php echo $balance; ?>')" style="border-radius: 0 12px 12px 0; font-size: 12px;">
                      Full Balance
                    </button>
                  </div>
                </div>
                <small class="form-text text-muted">Outstanding balance: <?php echo format_currency($balance); ?></small>
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13px;">Payment Method</label>
                <select name="payment_type" class="form-control" style="border-radius: 12px;">
                  <option value="Cash">Cash</option>
                  <option value="UPI / QR">UPI / QR Code</option>
                  <option value="Credit / Debit Card">Credit / Debit Card</option>
                  <option value="Net Banking">Net Banking</option>
                  <option value="Cheque">Cheque</option>
                </select>
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13px;">Transaction Reference / UTR #</label>
                <input type="text" name="transaction_ref" class="form-control" placeholder="e.g. UPI-987123984, POS-0082" style="border-radius: 12px;">
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13px;">Payment Notes</label>
                <input type="text" name="notes" class="form-control" placeholder="Optional installment note..." style="border-radius: 12px;">
              </div>

              <button type="submit" class="btn btn-primary btn-block" style="padding: 11px; border-radius: 12px;">
                <i class="fa fa-check"></i> Submit Payment
              </button>
            </form>
          </div>
        <?php endif; ?>
      </div>

      <!-- Financial Ledger & Payment History Column -->
      <div class="col-lg-7">
        <?php if ($isOwner): ?>
        <!-- Balance Overview Cards (Owner Only) -->
        <div class="tile">
          <div class="row" style="row-gap: 12px;">
            <div class="col-sm-4">
              <div style="background: rgba(0,0,0,0.2); padding: 14px; border-radius: 14px; border: 1px solid var(--glass-border); text-align: center;">
                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Total Plan Fee</div>
                <div style="font-size: 22px; font-weight: 800; color: #fff; margin-top: 4px;">
                  <?php echo format_currency($packagePrice); ?>
                </div>
              </div>
            </div>
            <div class="col-sm-4">
              <div style="background: rgba(0,0,0,0.2); padding: 14px; border-radius: 14px; border: 1px solid var(--glass-border); text-align: center;">
                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Total Collected</div>
                <div style="font-size: 22px; font-weight: 800; color: #34d399; margin-top: 4px;">
                  <?php echo format_currency($netCollected); ?>
                </div>
                <?php if ($totalRefunded > 0): ?>
                  <div style="font-size: 11px; color: #f87171; margin-top: 2px;">
                    (-<?php echo format_currency($totalRefunded); ?> refunded)
                  </div>
                <?php endif; ?>
              </div>
            </div>
            <div class="col-sm-4">
              <div style="background: rgba(0,0,0,0.2); padding: 14px; border-radius: 14px; border: 1px solid var(--glass-border); text-align: center;">
                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Balance Due</div>
                <div style="font-size: 22px; font-weight: 800; color: <?php echo ($balance > 0) ? '#f87171' : '#34d399'; ?>; margin-top: 4px;">
                  <?php echo format_currency($balance); ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Installments Ledger Table -->
        <div class="tile">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="font-size: 18px; font-weight: 700; margin: 0;">
              <i class="fa fa-history" style="color: var(--color-primary); margin-right: 6px;"></i> Payment Installment History
            </h3>
            <a href="receipt.php?booking=<?php echo $bookingId; ?>" target="_blank" class="btn btn-outline-light btn-sm" style="border-radius: 10px; font-size: 12.5px;">
              <i class="fa fa-print"></i> Official Full Receipt
            </a>
          </div>

          <div class="table-responsive">
            <table class="table table-hover table-bordered" style="margin: 0;">
              <thead>
                <tr>
                  <th style="width: 40px;">#</th>
                  <th>Receipt ID</th>
                  <th>Method</th>
                  <th>Amount</th>
                  <th>Date &amp; Time</th>
                  <th style="text-align: right; width: 90px;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($payments)): $idx = 1; foreach ($payments as $p): ?>
                <tr>
                  <td style="color: var(--text-muted);"><?php echo $idx++; ?></td>
                  <td style="font-weight: 600; color: var(--color-primary);">#REC-<?php echo $p['id']; ?></td>
                  <td>
                    <span class="ios-badge ios-badge-info"><?php echo htmlspecialchars($p['paymentType'] ?? 'Cash'); ?></span>
                    <?php if (!empty($p['transaction_ref'])): ?>
                      <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Ref: <?php echo htmlspecialchars($p['transaction_ref']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($p['status']) && $p['status'] === 'refunded'): ?>
                      <span class="badge badge-danger" style="font-size: 10px; margin-top: 3px;">Full Refund</span>
                    <?php elseif (!empty($p['status']) && $p['status'] === 'partially_refunded'): ?>
                      <span class="badge badge-warning" style="font-size: 10px; margin-top: 3px;">Refunded: <?php echo format_currency($p['refunded_amount']); ?></span>
                    <?php endif; ?>
                  </td>
                  <td style="font-weight: 700; color: #34d399;">
                    <?php echo format_currency($p['payment']); ?>
                    <?php if (floatval($p['refunded_amount'] ?? 0) > 0): ?>
                      <div style="font-size: 11px; color: #f87171; font-weight: normal;">-<?php echo format_currency($p['refunded_amount']); ?></div>
                    <?php endif; ?>
                  </td>
                  <td style="color: var(--text-muted); font-size: 13px;"><?php echo date('d M Y, h:i A', strtotime($p['payment_date'])); ?></td>
                  <td style="text-align: right; white-space: nowrap;">
                    <a href="receipt.php?booking=<?php echo $bookingId; ?>&pay=<?php echo $p['id']; ?>" target="_blank" class="btn btn-sm btn-outline-info" style="border-radius: 8px; padding: 4px 8px; font-size: 11.5px;" title="Print Receipt">
                      <i class="fa fa-print"></i>
                    </a>
                    <?php 
                    $availRefund = floatval($p['payment']) - floatval($p['refunded_amount'] ?? 0);
                    if ($availRefund > 0): ?>
                      <button type="button" class="btn btn-sm btn-outline-warning" data-toggle="modal" data-target="#refundModal<?php echo $p['id']; ?>" style="border-radius: 8px; padding: 4px 8px; font-size: 11.5px; margin-left: 4px;" title="Request / Process Refund">
                        <i class="fa fa-undo"></i>
                      </button>

                      <!-- Refund Request Modal for Installment #<?php echo $p['id']; ?> -->
                      <div class="modal fade" id="refundModal<?php echo $p['id']; ?>" tabindex="-1" role="dialog" style="text-align: left;">
                        <div class="modal-dialog modal-dialog-centered" role="document">
                          <div class="modal-content" style="background: #18181b; border: 1px solid rgba(255,255,255,0.15); border-radius: 18px; color: #fff;">
                            <div class="modal-header" style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                              <h5 class="modal-title"><i class="fa fa-undo text-warning"></i> Request Refund for #REC-<?php echo $p['id']; ?></h5>
                              <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                            </div>
                            <form method="post">
                              <div class="modal-body">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="action" value="request_refund">
                                <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">

                                <div class="form-group">
                                  <label style="font-weight: 600; font-size: 13px;">Original Transaction Amount</label>
                                  <div style="font-size: 18px; font-weight: 700; color: #34d399;"><?php echo format_currency($p['payment']); ?></div>
                                  <small class="text-muted">Maximum available to refund: <strong><?php echo format_currency($availRefund); ?></strong></small>
                                </div>

                                <div class="form-group">
                                  <label style="font-weight: 600; font-size: 13px;">Refund Amount (<?php echo get_currency_symbol(); ?>) *</label>
                                  <input type="number" step="0.01" min="0.01" max="<?php echo $availRefund; ?>" name="refund_amount" class="form-control" value="<?php echo $availRefund; ?>" required style="border-radius: 10px;">
                                </div>

                                <div class="form-group">
                                  <label style="font-weight: 600; font-size: 13px;">Reason for Refund *</label>
                                  <textarea name="reason" class="form-control" rows="2" placeholder="Explain the medical, relocation, or administrative reason..." required style="border-radius: 10px;"></textarea>
                                </div>

                                <div style="font-size: 12px; color: #94a3b8; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.25); padding: 10px; border-radius: 10px;">
                                  <i class="fa fa-info-circle text-warning"></i> In accordance with club policy, submitted refunds require approval and execution by an authorized Operations Manager.
                                </div>
                              </div>
                              <div class="modal-footer" style="border-top: 1px solid rgba(255,255,255,0.1);">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 10px;">Cancel</button>
                                <button type="submit" class="btn btn-warning" style="border-radius: 10px; font-weight: 700;">Submit Refund Request</button>
                              </div>
                            </form>
                          </div>
                        </div>
                      </div>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">No payments recorded yet for this booking.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; // End isOwner financial ledger ?>

        <!-- Refund Requests & Audit Terminal (Approved Policy 4) -->
        <?php if (!empty($refundRequests)): ?>
          <div class="tile">
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 16px;">
              <i class="fa fa-balance-scale" style="color: #fbbf24; margin-right: 6px;"></i> Refund Authorization &amp; Audit Trail
            </h3>
            <div class="table-responsive">
              <table class="table table-hover table-bordered" style="margin: 0; font-size: 13px;">
                <thead>
                  <tr>
                    <th>Refund ID</th>
                    <th>Payment Ref</th>
                    <th>Amount</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th style="text-align: right;">Authorization</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($refundRequests as $req): ?>
                  <tr>
                    <td><strong>#REF-<?php echo $req['id']; ?></strong></td>
                    <td>#REC-<?php echo $req['payment_id']; ?></td>
                    <td style="font-weight: 700; color: #f87171;"><?php echo format_currency($req['refund_amount']); ?></td>
                    <td>
                      <div><?php echo htmlspecialchars($req['reason']); ?></div>
                      <small style="color: #94a3b8;">Requested by <?php echo htmlspecialchars($req['requester_name'] ?? 'Staff'); ?> on <?php echo date('d M Y, h:i A', strtotime($req['created_at'])); ?></small>
                    </td>
                    <td>
                      <?php if ($req['status'] === 'executed'): ?>
                        <span class="badge badge-success"><i class="fa fa-check-circle"></i> Executed</span>
                      <?php elseif ($req['status'] === 'approved'): ?>
                        <span class="badge badge-info"><i class="fa fa-check"></i> Approved</span>
                      <?php elseif ($req['status'] === 'rejected'): ?>
                        <span class="badge badge-danger"><i class="fa fa-times-circle"></i> Rejected</span>
                      <?php else: ?>
                        <span class="badge badge-warning"><i class="fa fa-clock-o"></i> Pending Manager</span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align: right; white-space: nowrap;">
                      <?php if ($req['status'] === 'pending'): ?>
                        <?php if (is_manager()): ?>
                          <form method="post" style="display: inline-block;">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="action" value="approve_refund">
                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-success" style="border-radius: 8px; font-weight: 700; font-size: 11.5px;" onclick="return confirm('Confirm refund execution of <?php echo format_currency($req['refund_amount']); ?>? This will adjust member ledger.')">
                              <i class="fa fa-check"></i> Approve &amp; Execute
                            </button>
                          </form>
                          <form method="post" style="display: inline-block; margin-left: 4px;">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="action" value="reject_refund">
                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius: 8px; font-size: 11.5px;">
                              Reject
                            </button>
                          </form>
                        <?php else: ?>
                          <span style="color: #94a3b8; font-size: 11.5px;"><i class="fa fa-lock"></i> Manager Required</span>
                        <?php endif; ?>
                      <?php elseif ($req['status'] === 'executed'): ?>
                        <small style="color: #34d399;">
                          <i class="fa fa-check"></i> Authorized by <?php echo htmlspecialchars($req['authorizer_name'] ?? 'Manager'); ?><br>
                          <?php echo date('d M Y, h:i A', strtotime($req['executed_at'])); ?>
                        </small>
                      <?php elseif ($req['status'] === 'rejected'): ?>
                        <small style="color: #f87171;">
                          <?php echo htmlspecialchars($req['rejection_reason'] ?? 'Rejected'); ?>
                        </small>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <script src="js/jquery-3.2.1.min.js"></script>
  <script src="js/popper.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/main.js"></script>
</body>
</html>
