<?php
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$uid = intval($_GET['id'] ?? 0);
if ($uid <= 0) {
    header('location:members.php');
    exit();
}

$msg = '';
$err = '';

// Handle Admin Notes update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_notes') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = "Security token mismatch. Please try again.";
    } else {
        $notes = trim($_POST['admin_notes'] ?? '');
        $stmt = $dbh->prepare("UPDATE tbluser SET notes = ? WHERE id = ?");
        $stmt->execute([$notes, $uid]);
        $msg = "Member notes updated successfully.";
    }
}

// Handle Status Toggle
if (isset($_GET['action']) && $_GET['action'] === 'toggle') {
    $curStatus = $dbh->query("SELECT status FROM tbluser WHERE id = $uid")->fetchColumn();
    $newStatus = ($curStatus === 'active') ? 'inactive' : 'active';
    $stmt = $dbh->prepare("UPDATE tbluser SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $uid]);
    $msg = "Member status changed to $newStatus.";
}

// Handle Assign / Renew Membership
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_membership') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = "Security token mismatch. Please try again.";
    } else {
        $planId = intval($_POST['plan_id'] ?? 0);
        $startDate = trim($_POST['start_date'] ?? date('Y-m-d'));
        $durationMonths = intval($_POST['duration_months'] ?? 1);
        $customExpiry = trim($_POST['expiry_date'] ?? '');
        
        if (!empty($customExpiry)) {
            $expiryDate = $customExpiry;
        } else {
            $expiryDate = date('Y-m-d', strtotime("+$durationMonths months", strtotime($startDate)));
        }

        $paidAmount = floatval($_POST['paid_amount'] ?? 0);
        $paymentMethod = trim($_POST['payment_method'] ?? 'Cash');
        $txnRef = trim($_POST['transaction_ref'] ?? '');
        $memNotes = trim($_POST['notes'] ?? '');

        // Fetch plan info
        $planStmt = $dbh->prepare("SELECT titlename, Price FROM tbladdpackage WHERE id = ?");
        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch(PDO::FETCH_ASSOC);

        if (!$plan) {
            $err = "Invalid plan selected.";
        } else {
            $planPrice = floatval($plan['Price']);
            $paymentStatus = 'unpaid';
            if ($paidAmount >= $planPrice) {
                $paymentStatus = 'paid';
            } elseif ($paidAmount > 0) {
                $paymentStatus = 'partial';
            }

            // Determine membership active state
            $today = date('Y-m-d');
            $status = ($expiryDate >= $today) ? 'active' : 'expired';

            // 1. Dual-insert into legacy tblbooking
            $legacyStmt = $dbh->prepare("INSERT INTO tblbooking (package_id, userid, payment, paymentType) VALUES (?, ?, ?, ?)");
            $legacyStmt->execute([$planId, $uid, $paidAmount, $paymentMethod]);
            $bookingId = $dbh->lastInsertId();

            // 2. If payment made, record in tblpayment
            if ($paidAmount > 0) {
                $payStmt = $dbh->prepare("INSERT INTO tblpayment (bookingID, paymentType, payment) VALUES (?, ?, ?)");
                $payStmt->execute([$bookingId, $paymentMethod, $paidAmount]);
            }

            // 3. Insert into tbl_memberships
            $memStmt = $dbh->prepare("INSERT INTO tbl_memberships (user_id, plan_id, booking_id, start_date, expiry_date, status, payment_status, total_amount, paid_amount, payment_method, transaction_ref, admin_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $memStmt->execute([
                $uid,
                $planId,
                $bookingId,
                $startDate,
                $expiryDate,
                $status,
                $paymentStatus,
                $planPrice,
                $paidAmount,
                $paymentMethod,
                $txnRef,
                $memNotes
            ]);

            $msg = "Membership assigned successfully!";
        }
    }
}

// Handle Freeze Membership
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'freeze_membership') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = "Security token mismatch. Please try again.";
    } else {
        $memId = intval($_POST['membership_id'] ?? 0);
        $fStart = trim($_POST['freeze_start_date'] ?? date('Y-m-d'));
        $fEnd = trim($_POST['freeze_end_date'] ?? '');
        $fReason = trim($_POST['freeze_reason'] ?? 'Temporary Personal Suspension');
        
        $freezeStmt = $dbh->prepare("UPDATE tbl_memberships SET status = 'frozen', freeze_start_date = ?, freeze_end_date = ?, freeze_reason = ?, admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n[Frozen on " . date('Y-m-d') . ": $fReason]') WHERE id = ? AND user_id = ?");
        $freezeStmt->execute([$fStart, $fEnd ?: NULL, $fReason, $memId, $uid]);
        $msg = "Membership has been frozen/suspended.";
    }
}

// Handle Resume Membership (Auto-extends expiry by suspended duration)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resume_membership') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = "Security token mismatch. Please try again.";
    } else {
        $memId = intval($_POST['membership_id'] ?? 0);
        $curMem = $dbh->query("SELECT * FROM tbl_memberships WHERE id = $memId AND user_id = $uid")->fetch(PDO::FETCH_ASSOC);
        if ($curMem && $curMem['status'] === 'frozen') {
            $fStart = !empty($curMem['freeze_start_date']) ? strtotime($curMem['freeze_start_date']) : time();
            $frozenDays = max(1, ceil((time() - $fStart) / 86400));
            $newExpiry = date('Y-m-d', strtotime("+$frozenDays days", strtotime($curMem['expiry_date'])));
            
            $resumeStmt = $dbh->prepare("UPDATE tbl_memberships SET status = 'active', expiry_date = ?, freeze_start_date = NULL, freeze_end_date = NULL, freeze_reason = NULL, admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n[Resumed on " . date('Y-m-d') . ". Extended by $frozenDays days to $newExpiry]') WHERE id = ? AND user_id = ?");
            $resumeStmt->execute([$newExpiry, $memId, $uid]);
            $msg = "Membership resumed successfully! Expiration date extended by $frozenDays days to " . date('d M Y', strtotime($newExpiry)) . ".";
        }
    }
}

// Handle Upgrade / Downgrade Membership Plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upgrade_membership') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = "Security token mismatch. Please try again.";
    } else {
        $memId = intval($_POST['membership_id'] ?? 0);
        $newPlanId = intval($_POST['new_plan_id'] ?? 0);
        $paidDiff = floatval($_POST['paid_difference'] ?? 0);
        $payMethod = trim($_POST['payment_method'] ?? 'Cash');
        $txnRef = trim($_POST['transaction_ref'] ?? '');
        
        $newPlan = $dbh->query("SELECT titlename, Price FROM tbladdpackage WHERE id = $newPlanId")->fetch(PDO::FETCH_ASSOC);
        if ($newPlan) {
            $newPrice = floatval($newPlan['Price']);
            $upStmt = $dbh->prepare("UPDATE tbl_memberships SET plan_id = ?, total_amount = ?, paid_amount = paid_amount + ?, payment_status = IF((paid_amount + ?) >= ?, 'paid', 'partial'), admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n[Upgraded to " . addslashes($newPlan['titlename']) . " on " . date('Y-m-d') . "]') WHERE id = ? AND user_id = ?");
            $upStmt->execute([$newPlanId, $newPrice, $paidDiff, $paidDiff, $newPrice, $memId, $uid]);
            
            if ($paidDiff > 0) {
                $bookingId = $dbh->query("SELECT booking_id FROM tbl_memberships WHERE id = $memId")->fetchColumn();
                if ($bookingId) {
                    $dbh->prepare("INSERT INTO tblpayment (bookingID, paymentType, payment, payment_method, transaction_ref) VALUES (?, ?, ?, ?, ?)")->execute([$bookingId, $payMethod, $paidDiff, $payMethod, $txnRef]);
                }
            }
            $msg = "Membership upgraded to " . htmlspecialchars($newPlan['titlename']) . " successfully!";
        }
    }
}

// Handle Cancel Membership
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_membership') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = "Security token mismatch. Please try again.";
    } else {
        $memId = intval($_POST['membership_id'] ?? 0);
        $cancelReason = trim($_POST['cancel_reason'] ?? 'Member requested cancellation');
        $cancelStmt = $dbh->prepare("UPDATE tbl_memberships SET status = 'cancelled', cancelled_at = NOW(), cancel_reason = ?, admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n[Cancelled on " . date('Y-m-d') . ": $cancelReason]') WHERE id = ? AND user_id = ?");
        $cancelStmt->execute([$cancelReason, $memId, $uid]);
        $msg = "Membership cancelled successfully.";
    }
}

// Fetch member details
$userStmt = $dbh->prepare("SELECT * FROM tbluser WHERE id = ?");
$userStmt->execute([$uid]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('location:members.php');
    exit();
}

// Fetch active plans for dropdown
$plans = $dbh->query("SELECT p.id, p.titlename, p.Price, p.PackageDuratiobn, c.category_name FROM tbladdpackage p LEFT JOIN tblcategory c ON p.category = c.id WHERE p.is_active = 1 ORDER BY p.id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch membership history
$memHistoryStmt = $dbh->prepare("SELECT m.*, p.titlename, p.PackageDuratiobn, c.category_name FROM tbl_memberships m LEFT JOIN tbladdpackage p ON m.plan_id = p.id LEFT JOIN tblcategory c ON p.category = c.id WHERE m.user_id = ? ORDER BY m.id DESC");
$memHistoryStmt->execute([$uid]);
$membershipHistory = $memHistoryStmt->fetchAll(PDO::FETCH_ASSOC);

// Find current active or frozen membership (if any)
$currentMembership = null;
foreach ($membershipHistory as $mem) {
    if (in_array($mem['status'], ['active', 'frozen']) && ($mem['expiry_date'] >= date('Y-m-d') || $mem['status'] === 'frozen')) {
        $currentMembership = $mem;
        break;
    }
}

// Fetch payment ledger for this member
$payStmt = $dbh->prepare("SELECT p.id as pay_id, p.bookingID, p.paymentType, p.payment, p.payment_date, b.package_id, pkg.titlename FROM tblpayment p JOIN tblbooking b ON p.bookingID = b.id LEFT JOIN tbladdpackage pkg ON b.package_id = pkg.id WHERE b.userid = ? ORDER BY p.payment_date DESC");
$payStmt->execute([$uid]);
$paymentRecords = $payStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?> — Member Profile</title>
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
          <i class="fa fa-user-circle" style="color: var(--color-primary); margin-right: 8px;"></i>
          <?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?>
        </h1>
        <p>Member ID: #<?php echo $user['id']; ?> &bull; Joined <?php echo date('d M Y', strtotime($user['create_date'])); ?></p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="members.php">Members</a></li>
        <li class="breadcrumb-item active">Profile</li>
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
      <!-- Member Personal Details & Status -->
      <div class="col-lg-4">
        <div class="tile" style="text-align: center; padding: 30px 20px;">
          <div style="width: 84px; height: 84px; margin: 0 auto 16px; border-radius: 50%; background: linear-gradient(135deg, var(--color-primary), var(--color-accent)); display: flex; align-items: center; justify-content: center; font-size: 36px; color: #fff; box-shadow: 0 10px 25px rgba(56, 189, 248, 0.35);">
            <i class="fa fa-user"></i>
          </div>
          <h3 style="font-size: 20px; font-weight: 700; margin-bottom: 4px;">
            <?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?>
          </h3>
          <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 12px;"><?php echo htmlspecialchars($user['email']); ?></p>

          <div style="margin-bottom: 20px;">
            <?php if (($user['status'] ?? 'active') === 'active'): ?>
              <span class="ios-badge ios-badge-success" style="font-size: 13px; padding: 6px 14px;">Active Account</span>
            <?php else: ?>
              <span class="ios-badge ios-badge-danger" style="font-size: 13px; padding: 6px 14px;">Deactivated Account</span>
            <?php endif; ?>
          </div>

          <div style="text-align: left; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--glass-border); border-radius: 16px; padding: 16px; margin-bottom: 20px; font-size: 13.5px;">
            <div style="margin-bottom: 10px; display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);"><i class="fa fa-phone" style="width: 20px;"></i> Mobile:</span>
              <span style="font-weight: 600;"><?php echo htmlspecialchars($user['mobile'] ?? 'N/A'); ?></span>
            </div>
            <div style="margin-bottom: 10px; display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);"><i class="fa fa-map-marker" style="width: 20px;"></i> City / State:</span>
              <span style="font-weight: 600;"><?php echo htmlspecialchars(($user['city'] ? $user['city'] . ', ' : '') . ($user['state'] ?? '')); ?></span>
            </div>
            <div style="margin-bottom: 10px; display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);"><i class="fa fa-home" style="width: 20px;"></i> Address:</span>
              <span style="font-weight: 500; text-align: right; max-width: 170px;"><?php echo htmlspecialchars($user['address'] ?? 'N/A'); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);"><i class="fa fa-calendar" style="width: 20px;"></i> Joined:</span>
              <span style="font-weight: 600;"><?php echo date('d M Y', strtotime($user['create_date'])); ?></span>
            </div>
          </div>

          <div style="display: flex; gap: 8px;">
            <a href="members.php?action=toggle&uid=<?php echo $user['id']; ?>" class="btn <?php echo (($user['status'] ?? 'active') === 'active') ? 'btn-outline-danger' : 'btn-success'; ?> btn-block" style="border-radius: 12px; padding: 10px;" onclick="return confirm('Change status for this account?');">
              <i class="fa <?php echo (($user['status'] ?? 'active') === 'active') ? 'fa-ban' : 'fa-check'; ?>"></i>
              <?php echo (($user['status'] ?? 'active') === 'active') ? 'Deactivate Member' : 'Activate Member'; ?>
            </a>
          </div>
        </div>

        <!-- Admin Notes Card -->
        <div class="tile">
          <h4 style="font-size: 16px; font-weight: 700; margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between;">
            <span><i class="fa fa-sticky-note-o" style="color: var(--color-accent); margin-right: 6px;"></i> Admin Notes</span>
          </h4>
          <form method="post">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="update_notes">
            <div class="form-group">
              <textarea name="admin_notes" class="form-control" rows="4" placeholder="Private internal staff notes regarding member fitness goals, health conditions, or payment agreements..."><?php echo htmlspecialchars($user['notes'] ?? ''); ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-block" style="border-radius: 12px; padding: 9px;">
              <i class="fa fa-save"></i> Save Notes
            </button>
          </form>
        </div>
      </div>

      <!-- Main Membership & Financial Column -->
      <div class="col-lg-8">
        <!-- Current Membership Banner -->
        <div class="tile" style="position: relative; overflow: hidden; background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(56, 189, 248, 0.08) 100%);">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 14px; margin-bottom: 18px;">
            <div>
              <?php if ($currentMembership && $currentMembership['status'] === 'frozen'): ?>
                <span class="ios-badge ios-badge-warning" style="margin-bottom: 8px;"><i class="fa fa-pause-circle"></i> MEMBERSHIP SUSPENDED / FROZEN</span>
              <?php else: ?>
                <span class="ios-badge ios-badge-info" style="margin-bottom: 8px;">CURRENT MEMBERSHIP</span>
              <?php endif; ?>
              <h2 style="font-size: 24px; font-weight: 800; margin: 4px 0;">
                <?php echo $currentMembership ? htmlspecialchars($currentMembership['titlename']) : 'No Active Membership'; ?>
              </h2>
              <?php if ($currentMembership): ?>
                <p style="color: var(--text-secondary); margin: 0; font-size: 13.5px;">
                  Category: <strong><?php echo htmlspecialchars($currentMembership['category_name'] ?? 'General'); ?></strong> &bull;
                  Period: <?php echo date('d M Y', strtotime($currentMembership['start_date'])); ?> &rarr; <?php echo date('d M Y', strtotime($currentMembership['expiry_date'])); ?>
                  <?php if (!empty($currentMembership['freeze_reason'])): ?>
                    <br><small style="color: #fbbf24;"><i class="fa fa-info-circle"></i> Freeze Reason: <?php echo htmlspecialchars($currentMembership['freeze_reason']); ?> (Frozen since <?php echo date('d M Y', strtotime($currentMembership['freeze_start_date'])); ?>)</small>
                  <?php endif; ?>
                </p>
              <?php else: ?>
                <p style="color: var(--text-muted); margin: 0; font-size: 13.5px;">This member does not have an active package. Assign or renew a plan below.</p>
              <?php endif; ?>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
              <?php if ($currentMembership): ?>
                <?php if ($currentMembership['status'] === 'frozen'): ?>
                  <button type="button" class="btn btn-success" data-toggle="modal" data-target="#resumeModal" style="border-radius: 12px; font-weight: 600;">
                    <i class="fa fa-play"></i> Resume Pass
                  </button>
                <?php else: ?>
                  <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#freezeModal" style="border-radius: 12px; color: #000; font-weight: 600;">
                    <i class="fa fa-pause"></i> Freeze Pass
                  </button>
                  <button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#upgradeModal" style="border-radius: 12px;">
                    <i class="fa fa-level-up"></i> Upgrade Plan
                  </button>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-danger" data-toggle="modal" data-target="#cancelModal" style="border-radius: 12px;">
                  <i class="fa fa-times"></i> Cancel
                </button>
              <?php endif; ?>
              <button type="button" class="btn btn-primary" style="border-radius: 12px;" data-toggle="modal" data-target="#assignModal">
                <i class="fa fa-refresh"></i> <?php echo $currentMembership ? 'Renew / Extend' : 'Assign Plan'; ?>
              </button>
            </div>
          </div>

          <?php if ($currentMembership): 
            $daysLeft = (int)ceil((strtotime($currentMembership['expiry_date']) - time()) / 86400);
            $bal = floatval($currentMembership['total_amount']) - floatval($currentMembership['paid_amount']);
          ?>
            <div class="row" style="margin-top: 15px; row-gap: 12px;">
              <div class="col-sm-4">
                <div style="background: rgba(0,0,0,0.2); padding: 12px 16px; border-radius: 14px; border: 1px solid var(--glass-border);">
                  <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Days Remaining</div>
                  <div style="font-size: 22px; font-weight: 800; color: <?php echo $daysLeft <= 7 ? '#f87171' : 'var(--color-primary)'; ?>;">
                    <?php echo max(0, $daysLeft); ?> Days
                  </div>
                </div>
              </div>
              <div class="col-sm-4">
                <div style="background: rgba(0,0,0,0.2); padding: 12px 16px; border-radius: 14px; border: 1px solid var(--glass-border);">
                  <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Plan Price / Paid</div>
                  <div style="font-size: 20px; font-weight: 800; color: #fff;">
                    <?php echo format_currency($currentMembership['total_amount']); ?>
                    <span style="font-size: 13px; color: #34d399; font-weight: normal;">(<?php echo format_currency($currentMembership['paid_amount']); ?> paid)</span>
                  </div>
                </div>
              </div>
              <div class="col-sm-4">
                <div style="background: rgba(0,0,0,0.2); padding: 12px 16px; border-radius: 14px; border: 1px solid var(--glass-border);">
                  <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Payment Status</div>
                  <div style="margin-top: 4px;">
                    <?php if ($currentMembership['payment_status'] === 'paid'): ?>
                      <span class="ios-badge ios-badge-success">Fully Paid</span>
                    <?php elseif ($currentMembership['payment_status'] === 'partial'): ?>
                      <span class="ios-badge ios-badge-warning">Partial (Bal: <?php echo format_currency($bal); ?>)</span>
                    <?php else: ?>
                      <span class="ios-badge ios-badge-danger">Unpaid</span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <!-- Membership History Table -->
        <div class="tile">
          <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 16px;">
            <i class="fa fa-history" style="color: var(--color-primary); margin-right: 6px;"></i> Membership History
          </h3>
          <div class="table-responsive">
            <table class="table table-hover table-bordered" style="margin: 0;">
              <thead>
                <tr>
                  <th>Plan</th>
                  <th>Category</th>
                  <th>Valid From</th>
                  <th>Valid Until</th>
                  <th>Fee</th>
                  <th>Paid</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($membershipHistory)): foreach ($membershipHistory as $mh): ?>
                <tr>
                  <td style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($mh['titlename'] ?? 'General Membership'); ?></td>
                  <td style="color: var(--text-secondary);"><?php echo htmlspecialchars($mh['category_name'] ?? '-'); ?></td>
                  <td><?php echo date('d M Y', strtotime($mh['start_date'])); ?></td>
                  <td><?php echo date('d M Y', strtotime($mh['expiry_date'])); ?></td>
                  <td><?php echo format_currency($mh['total_amount']); ?></td>
                  <td style="color: #34d399;"><?php echo format_currency($mh['paid_amount']); ?></td>
                  <td>
                    <?php if ($mh['status'] === 'active'): ?>
                      <span class="ios-badge ios-badge-success">Active</span>
                    <?php elseif ($mh['status'] === 'expired'): ?>
                      <span class="ios-badge ios-badge-danger">Expired</span>
                    <?php else: ?>
                      <span class="ios-badge ios-badge-info"><?php echo ucfirst($mh['status']); ?></span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 24px;">No membership records found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Financial & Payment History Table -->
        <div class="tile">
          <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 16px;">
            <i class="fa fa-credit-card" style="color: #10b981; margin-right: 6px;"></i> Payment Ledger
          </h3>
          <div class="table-responsive">
            <table class="table table-hover table-bordered" style="margin: 0;">
              <thead>
                <tr>
                  <th>Receipt / Txn</th>
                  <th>Plan</th>
                  <th>Payment Method</th>
                  <th>Amount Paid</th>
                  <th>Date & Time</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($paymentRecords)): foreach ($paymentRecords as $pr): ?>
                <tr>
                  <td style="font-weight: 600; color: var(--color-primary);">#PAY-<?php echo $pr['pay_id']; ?></td>
                  <td style="color: #fff;"><?php echo htmlspecialchars($pr['titlename'] ?? ('Booking #' . $pr['bookingID'])); ?></td>
                  <td><span class="ios-badge ios-badge-info"><?php echo htmlspecialchars($pr['paymentType'] ?? 'Cash'); ?></span></td>
                  <td style="font-weight: 700; color: #34d399;"><?php echo format_currency($pr['payment']); ?></td>
                  <td style="color: var(--text-muted); font-size: 13px;"><?php echo date('d M Y, h:i A', strtotime($pr['payment_date'])); ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">No payment records logged for this member yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- Modal: Assign / Renew Membership -->
  <div class="modal fade" id="assignModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content" style="background: rgba(18, 24, 38, 0.95); backdrop-filter: blur(24px); border: 1px solid var(--glass-border); border-radius: 24px; color: #fff;">
        <form method="post">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="action" value="assign_membership">
          
          <div class="modal-header" style="border-bottom: 1px solid var(--glass-border); padding: 20px 24px;">
            <h5 class="modal-title" style="font-weight: 700; font-size: 18px;">
              <i class="fa fa-refresh" style="color: var(--color-primary); margin-right: 6px;"></i> Assign / Renew Membership
            </h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.8;">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>

          <div class="modal-body" style="padding: 24px;">
            <div class="form-group">
              <label style="font-weight: 600; font-size: 13px;">Select Membership Plan *</label>
              <select name="plan_id" id="planSelect" class="form-control" required style="border-radius: 12px;">
                <option value="" disabled selected>-- Choose a package --</option>
                <?php foreach ($plans as $p): ?>
                  <option value="<?php echo $p['id']; ?>" data-price="<?php echo $p['Price']; ?>" data-duration="<?php echo htmlspecialchars($p['PackageDuratiobn'] ?? '1'); ?>">
                    <?php echo htmlspecialchars($p['titlename']); ?> &bull; <?php echo format_currency($p['Price']); ?> (<?php echo htmlspecialchars($p['PackageDuratiobn'] ?? '1 Month'); ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="row">
              <div class="col-md-6 form-group">
                <label style="font-weight: 600; font-size: 13px;">Start Date *</label>
                <input type="date" name="start_date" id="startDate" class="form-control" value="<?php echo date('Y-m-d'); ?>" required style="border-radius: 12px;">
              </div>
              <div class="col-md-6 form-group">
                <label style="font-weight: 600; font-size: 13px;">Duration (Months)</label>
                <select name="duration_months" id="durationMonths" class="form-control" style="border-radius: 12px;">
                  <option value="1">1 Month</option>
                  <option value="3">3 Months</option>
                  <option value="6">6 Months</option>
                  <option value="12">12 Months (1 Year)</option>
                </select>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 form-group">
                <label style="font-weight: 600; font-size: 13px;">Amount Paid (<?php echo get_currency_symbol(); ?>)</label>
                <input type="number" step="0.01" name="paid_amount" id="paidAmount" class="form-control" placeholder="0.00" style="border-radius: 12px;">
              </div>
              <div class="col-md-6 form-group">
                <label style="font-weight: 600; font-size: 13px;">Payment Method</label>
                <select name="payment_method" class="form-control" style="border-radius: 12px;">
                  <option value="Cash">Cash</option>
                  <option value="UPI / QR">UPI / QR Code</option>
                  <option value="Credit / Debit Card">Credit / Debit Card</option>
                  <option value="Net Banking">Net Banking</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label style="font-weight: 600; font-size: 13px;">Transaction Reference / Receipt #</label>
              <input type="text" name="transaction_ref" class="form-control" placeholder="e.g. UPI-987213498, REC-0091" style="border-radius: 12px;">
            </div>

            <div class="form-group">
              <label style="font-weight: 600; font-size: 13px;">Membership Notes</label>
              <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes for this renewal..." style="border-radius: 12px;"></textarea>
            </div>
          </div>

          <div class="modal-footer" style="border-top: 1px solid var(--glass-border); padding: 16px 24px;">
            <button type="button" class="btn btn-outline-light" data-dismiss="modal" style="border-radius: 12px;">Cancel</button>
            <button type="submit" class="ios-btn ios-btn-accent" style="padding: 10px 22px; border-radius: 12px;">
              <i class="fa fa-check"></i> Confirm & Assign
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php if ($currentMembership): ?>
    <!-- Modal: Freeze Membership -->
    <div class="modal fade" id="freezeModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="background: rgba(18, 24, 38, 0.95); backdrop-filter: blur(24px); border: 1px solid var(--glass-border); border-radius: 24px; color: #fff;">
          <form method="post">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="freeze_membership">
            <input type="hidden" name="membership_id" value="<?php echo $currentMembership['id']; ?>">
            
            <div class="modal-header" style="border-bottom: 1px solid var(--glass-border); padding: 20px 24px;">
              <h5 class="modal-title" style="font-weight: 700; font-size: 18px; color: #fbbf24;">
                <i class="fa fa-pause-circle" style="margin-right: 6px;"></i> Freeze / Suspend Membership Pass
              </h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.8;">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>

            <div class="modal-body" style="padding: 24px;">
              <div class="alert alert-warning" style="background: rgba(251, 191, 36, 0.12); border: 1px solid rgba(251, 191, 36, 0.25); border-radius: 14px; font-size: 13px; color: #fef3c7;">
                <i class="fa fa-info-circle"></i> Freezing halts member facility access. Upon resuming, the expiration date is automatically extended by the exact number of suspended days.
              </div>

              <div class="row">
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13px;">Freeze Start Date *</label>
                  <input type="date" name="freeze_start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required style="border-radius: 12px;">
                </div>
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13px;">Expected End Date</label>
                  <input type="date" name="freeze_end_date" class="form-control" style="border-radius: 12px;">
                </div>
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13px;">Reason for Suspension *</label>
                <select name="freeze_reason" class="form-control" style="border-radius: 12px;">
                  <option value="Personal Travel / Vacation">Personal Travel / Vacation</option>
                  <option value="Medical / Health Injury">Medical / Health Injury</option>
                  <option value="Work / Academic Schedule">Work / Academic Schedule</option>
                  <option value="Temporary Financial Break">Temporary Financial Break</option>
                  <option value="Other Administrative Reason">Other Administrative Reason</option>
                </select>
              </div>
            </div>

            <div class="modal-footer" style="border-top: 1px solid var(--glass-border); padding: 16px 24px;">
              <button type="button" class="btn btn-outline-light" data-dismiss="modal" style="border-radius: 12px;">Cancel</button>
              <button type="submit" class="btn btn-warning" style="border-radius: 12px; color: #000; font-weight: 700;">
                <i class="fa fa-pause"></i> Confirm Freeze
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal: Resume Membership -->
    <div class="modal fade" id="resumeModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="background: rgba(18, 24, 38, 0.95); backdrop-filter: blur(24px); border: 1px solid var(--glass-border); border-radius: 24px; color: #fff;">
          <form method="post">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="resume_membership">
            <input type="hidden" name="membership_id" value="<?php echo $currentMembership['id']; ?>">
            
            <div class="modal-header" style="border-bottom: 1px solid var(--glass-border); padding: 20px 24px;">
              <h5 class="modal-title" style="font-weight: 700; font-size: 18px; color: #34d399;">
                <i class="fa fa-play-circle" style="margin-right: 6px;"></i> Resume Membership Pass
              </h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.8;">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>

            <div class="modal-body" style="padding: 24px;">
              <p style="font-size: 14.5px; color: #e2e8f0; margin-bottom: 16px;">
                Are you ready to reactivate gym access for <strong><?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?></strong>?
              </p>
              <div class="alert alert-success" style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 14px; font-size: 13.5px; color: #d1fae5;">
                <i class="fa fa-check-circle"></i> The system will automatically calculate the days spent frozen and push forward the expiration date accordingly.
              </div>
            </div>

            <div class="modal-footer" style="border-top: 1px solid var(--glass-border); padding: 16px 24px;">
              <button type="button" class="btn btn-outline-light" data-dismiss="modal" style="border-radius: 12px;">Cancel</button>
              <button type="submit" class="btn btn-success" style="border-radius: 12px; font-weight: 700;">
                <i class="fa fa-play"></i> Confirm Resume
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal: Upgrade / Downgrade Membership Plan -->
    <div class="modal fade" id="upgradeModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="background: rgba(18, 24, 38, 0.95); backdrop-filter: blur(24px); border: 1px solid var(--glass-border); border-radius: 24px; color: #fff;">
          <form method="post">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="upgrade_membership">
            <input type="hidden" name="membership_id" value="<?php echo $currentMembership['id']; ?>">
            
            <div class="modal-header" style="border-bottom: 1px solid var(--glass-border); padding: 20px 24px;">
              <h5 class="modal-title" style="font-weight: 700; font-size: 18px; color: var(--color-primary);">
                <i class="fa fa-level-up" style="margin-right: 6px;"></i> Upgrade / Change Membership Plan
              </h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.8;">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>

            <div class="modal-body" style="padding: 24px;">
              <div class="form-group">
                <label style="font-weight: 600; font-size: 13px;">Current Plan</label>
                <input type="text" class="form-control" readonly value="<?php echo htmlspecialchars($currentMembership['titlename']); ?> (<?php echo format_currency($currentMembership['total_amount']); ?>)" style="border-radius: 12px; opacity: 0.7;">
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13px;">New Target Plan *</label>
                <select name="new_plan_id" class="form-control" required style="border-radius: 12px;">
                  <option value="" disabled selected>-- Select upgrade package --</option>
                  <?php foreach ($plans as $p): ?>
                    <?php if ($p['id'] != $currentMembership['plan_id']): ?>
                      <option value="<?php echo $p['id']; ?>">
                        <?php echo htmlspecialchars($p['titlename']); ?> &bull; <?php echo format_currency($p['Price']); ?> (<?php echo htmlspecialchars($p['PackageDuratiobn'] ?? '1 Month'); ?>)
                      </option>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="row">
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13px;">Upgrade Payment (<?php echo get_currency_symbol(); ?>)</label>
                  <input type="number" step="0.01" name="paid_difference" class="form-control" placeholder="0.00" style="border-radius: 12px;">
                </div>
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13px;">Payment Method</label>
                  <select name="payment_method" class="form-control" style="border-radius: 12px;">
                    <option value="Cash">Cash</option>
                    <option value="UPI / QR">UPI / QR</option>
                    <option value="Card">Card / POS</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13px;">Transaction Reference</label>
                <input type="text" name="transaction_ref" class="form-control" placeholder="Optional transaction ID..." style="border-radius: 12px;">
              </div>
            </div>

            <div class="modal-footer" style="border-top: 1px solid var(--glass-border); padding: 16px 24px;">
              <button type="button" class="btn btn-outline-light" data-dismiss="modal" style="border-radius: 12px;">Cancel</button>
              <button type="submit" class="btn btn-primary" style="border-radius: 12px;">
                <i class="fa fa-level-up"></i> Confirm Upgrade
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal: Cancel Membership -->
    <div class="modal fade" id="cancelModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="background: rgba(18, 24, 38, 0.95); backdrop-filter: blur(24px); border: 1px solid var(--glass-border); border-radius: 24px; color: #fff;">
          <form method="post">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="cancel_membership">
            <input type="hidden" name="membership_id" value="<?php echo $currentMembership['id']; ?>">
            
            <div class="modal-header" style="border-bottom: 1px solid var(--glass-border); padding: 20px 24px;">
              <h5 class="modal-title" style="font-weight: 700; font-size: 18px; color: #ef4444;">
                <i class="fa fa-exclamation-triangle" style="margin-right: 6px;"></i> Cancel Active Membership
              </h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.8;">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>

            <div class="modal-body" style="padding: 24px;">
              <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 14px; font-size: 13.5px; color: #fee2e2;">
                <strong>Warning:</strong> Cancelling immediately terminates facility privileges for this pass. Previous payment records remain intact in the ledger.
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13px;">Reason for Cancellation *</label>
                <textarea name="cancel_reason" class="form-control" rows="3" required placeholder="Provide reason for member pass termination..." style="border-radius: 12px;"></textarea>
              </div>
            </div>

            <div class="modal-footer" style="border-top: 1px solid var(--glass-border); padding: 16px 24px;">
              <button type="button" class="btn btn-outline-light" data-dismiss="modal" style="border-radius: 12px;">Close</button>
              <button type="submit" class="btn btn-danger" style="border-radius: 12px; font-weight: 700;">
                <i class="fa fa-times"></i> Cancel Membership
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php endif; ?>
        </form>
      </div>
    </div>
  </div>

  <script src="js/jquery-3.2.1.min.js"></script>
  <script src="js/popper.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/main.js"></script>
  <script>
    $('#planSelect').on('change', function() {
      var selected = $(this).find(':selected');
      var price = selected.data('price');
      if (price !== undefined) {
        $('#paidAmount').val(price);
      }
    });
  </script>
</body>
</html>
