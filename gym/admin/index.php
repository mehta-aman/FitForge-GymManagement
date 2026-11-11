<?php  
require_once 'include/config.php';
require_once 'include/rbac.php';

if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$adminRole = current_admin_role();
$adminId = intval($_SESSION['adminid']);
$gymId = function_exists('get_current_gym_id') ? get_current_gym_id() : 1;
$isOwnerAdmin = is_owner();

// 1. Core Lifecycle KPIs (General Operational Data)
$totalMembers = intval($dbh->query("SELECT count(*) FROM tbluser")->fetchColumn() ?: 0);
$activeMemberships = intval($dbh->query("SELECT count(*) FROM tbl_memberships WHERE status = 'active' AND expiry_date >= CURDATE() AND start_date <= CURDATE()")->fetchColumn() ?: 0);
$scheduledMemberships = intval($dbh->query("SELECT count(*) FROM tbl_memberships WHERE status = 'scheduled' OR (status = 'active' AND start_date > CURDATE())")->fetchColumn() ?: 0);
$expiredMemberships = intval($dbh->query("SELECT count(*) FROM tbl_memberships WHERE status = 'expired' OR (status NOT IN ('frozen', 'cancelled') AND expiry_date < CURDATE())")->fetchColumn() ?: 0);
$upcomingRenewals = intval($dbh->query("SELECT count(*) FROM tbl_memberships WHERE status = 'active' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)")->fetchColumn() ?: 0);

// 2. Operational Floor & Attendance Data
$activeFloorCount = intval($dbh->query("SELECT count(*) FROM tbl_attendance WHERE check_out_time IS NULL AND DATE(check_in_time) = CURDATE() AND gym_id = $gymId")->fetchColumn() ?: 0);
$todayCheckinsCount = intval($dbh->query("SELECT count(*) FROM tbl_attendance WHERE DATE(check_in_time) = CURDATE() AND gym_id = $gymId")->fetchColumn() ?: 0);
$pendingRefundsCount = intval($dbh->query("SELECT count(*) FROM tbl_refund_requests WHERE status = 'pending' AND gym_id = $gymId")->fetchColumn() ?: 0);
$activeFreezesCount = intval($dbh->query("SELECT count(*) FROM tbl_memberships WHERE status = 'frozen'")->fetchColumn() ?: 0);
$expiring7DaysCount = intval($dbh->query("SELECT count(*) FROM tbl_memberships WHERE status = 'active' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn() ?: 0);

// Live Active Floor Members Feed
$activeFloorMembers = $dbh->query("
    SELECT a.id, a.check_in_time, a.check_in_method, u.id as userid, u.fname, u.lname, u.email,
           TIMESTAMPDIFF(MINUTE, a.check_in_time, NOW()) as elapsed_mins
    FROM tbl_attendance a
    JOIN tbluser u ON a.user_id = u.id
    WHERE a.check_out_time IS NULL AND DATE(a.check_in_time) = CURDATE() AND a.gym_id = $gymId
    ORDER BY a.check_in_time DESC LIMIT 10
")->fetchAll(PDO::FETCH_OBJ);

// Recent Member Registrations
$recentMembers = $dbh->query("SELECT id, fname, lname, email, mobile, create_date, status FROM tbluser ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_OBJ);

// Plan Distribution (Tier popularity breakdown)
$planDistData = $dbh->query("
    SELECT p.titlename, count(m.id) as cnt
    FROM tbl_memberships m
    JOIN tbladdpackage p ON m.plan_id = p.id
    GROUP BY p.id, p.titlename
")->fetchAll(PDO::FETCH_ASSOC);

$planLabels = json_encode(array_column($planDistData, 'titlename'));
$planCounts = json_encode(array_map('intval', array_column($planDistData, 'cnt')));

// 3. Strict Financial Boundary: Query financial figures ONLY if Owner
$grossRevenue = 0.0;
$totalRevenue = 0.0;
$outstandingBalance = 0.0;
$recentPayments = [];
$chartLabels = '[]';
$chartRevenue = '[]';

if ($isOwnerAdmin) {
    $grossRevenue = floatval($dbh->query("SELECT SUM(payment) FROM tblpayment")->fetchColumn() ?: 0);
    $totalRefunds = floatval($dbh->query("SELECT SUM(refund_amount) FROM tbl_refund_requests WHERE status = 'executed'")->fetchColumn() ?: 0);
    $totalRevenue = max(0, $grossRevenue - $totalRefunds);
    $totalBilled = floatval($dbh->query("SELECT SUM(total_amount) FROM tbl_memberships")->fetchColumn() ?: 0);
    $outstandingBalance = max(0, $totalBilled - $totalRevenue);

    $recentPayments = $dbh->query("
        SELECT p.id, p.payment, p.refunded_amount, p.paymentType, p.payment_date, p.bookingID, u.fname, u.lname 
        FROM tblpayment p
        LEFT JOIN tblbooking b ON p.bookingID = b.id
        LEFT JOIN tbluser u ON b.userid = u.id
        ORDER BY p.id DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_OBJ);

    $monthlyRevenueData = $dbh->query("
        SELECT DATE_FORMAT(payment_date, '%b %Y') as m_label, 
               SUM(CAST(payment AS DECIMAL(10,2)) - CAST(COALESCE(refunded_amount,0) AS DECIMAL(10,2))) as m_revenue
        FROM tblpayment
        GROUP BY DATE_FORMAT(payment_date, '%Y-%m'), m_label
        ORDER BY DATE_FORMAT(payment_date, '%Y-%m') ASC
        LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);

    $chartLabels = json_encode(array_column($monthlyRevenueData, 'm_label'));
    $chartRevenue = json_encode(array_map('floatval', array_column($monthlyRevenueData, 'm_revenue')));
}

// 4. Role-Specific Datasets:
// Trainer Data
$myClients = [];
$assignedClientsCount = 0;
if ($adminRole === 'trainer') {
    $tStmt = $dbh->prepare("SELECT trainer_id FROM tbladmin WHERE id = ?");
    $tStmt->execute([$adminId]);
    $trainerProfileId = intval($tStmt->fetchColumn());
    if ($trainerProfileId > 0) {
        $assignedClientsCount = intval($dbh->query("SELECT count(*) FROM tbl_member_trainers WHERE trainer_id = $trainerProfileId AND status = 'active'")->fetchColumn() ?: 0);
        $myClients = $dbh->query("
            SELECT mt.*, u.fname, u.lname, u.email, u.mobile 
            FROM tbl_member_trainers mt 
            JOIN tbluser u ON mt.user_id = u.id 
            WHERE mt.trainer_id = $trainerProfileId AND mt.status = 'active' 
            ORDER BY mt.assigned_date DESC LIMIT 5
        ")->fetchAll(PDO::FETCH_OBJ);
    }
}

// Manager Data: Recent Refund Queue Cases
$managerRefundCases = [];
if ($adminRole === 'manager') {
    $rcStmt = $dbh->prepare("
        SELECT r.*, u.fname, u.lname 
        FROM tbl_refund_requests r 
        JOIN tblbooking b ON r.booking_id = b.id 
        JOIN tbluser u ON b.userid = u.id 
        WHERE r.gym_id = :gym_id 
        ORDER BY CASE WHEN r.status = 'pending' THEN 1 ELSE 2 END, r.created_at DESC 
        LIMIT 5
    ");
    $rcStmt->execute([':gym_id' => $gymId]);
    $managerRefundCases = $rcStmt->fetchAll(PDO::FETCH_OBJ);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Dashboard — <?php echo htmlspecialchars(get_setting('gym_name', 'FitForge Gym'), ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php echo render_favicon_tags(); ?>
  <link rel="stylesheet" type="text/css" href="css/main.css">
  <link rel="stylesheet" type="text/css" href="css/ios-admin-glass.css">
  <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</head>
<body class="app sidebar-mini rtl">
  <?php include 'include/header.php'; ?>
  <div class="app-sidebar__overlay" data-toggle="sidebar"></div>
  <?php include 'include/sidebar.php'; ?>

  <main class="app-content">
    <!-- App Title Header (Customized per role) -->
    <div class="app-title">
      <div style="display: flex; align-items: center; min-width: 0;">
        <?php if ($adminRole === 'owner'): ?>
          <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(56, 189, 248, 0.12); border: 1px solid rgba(56, 189, 248, 0.25); display: inline-flex; align-items: center; justify-content: center; margin-right: 14px; flex-shrink: 0;">
            <i class="fa fa-tachometer" style="color: var(--color-primary); font-size: 18px;"></i>
          </div>
          <div style="min-width: 0;">
            <h1>Control Center</h1>
            <p>Real-time analytics across member subscriptions, revenue streams, and club activities</p>
          </div>
        <?php elseif ($adminRole === 'manager'): ?>
          <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.3); display: inline-flex; align-items: center; justify-content: center; margin-right: 14px; flex-shrink: 0;">
            <i class="fa fa-briefcase" style="color: #60a5fa; font-size: 18px;"></i>
          </div>
          <div style="min-width: 0;">
            <h1>Operations Dashboard</h1>
            <p>Operational administration across memberships, freeze requests, and refund cases</p>
          </div>
        <?php elseif ($adminRole === 'trainer'): ?>
          <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); display: inline-flex; align-items: center; justify-content: center; margin-right: 14px; flex-shrink: 0;">
            <i class="fa fa-heartbeat" style="color: #34d399; font-size: 18px;"></i>
          </div>
          <div style="min-width: 0;">
            <h1>Coaching Dashboard</h1>
            <p>Client workout programming, progress metrics, and gym floor activity</p>
          </div>
        <?php else: ?>
          <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(139, 92, 246, 0.15); border: 1px solid rgba(139, 92, 246, 0.3); display: inline-flex; align-items: center; justify-content: center; margin-right: 14px; flex-shrink: 0;">
            <i class="fa fa-id-badge" style="color: #a78bfa; font-size: 18px;"></i>
          </div>
          <div style="min-width: 0;">
            <h1>Front Desk Station</h1>
            <p>Member check-in desk, live floor attendance, and fast pass verification</p>
          </div>
        <?php endif; ?>
      </div>
      <ul class="app-breadcrumb breadcrumb d-none d-sm-flex">
        <li class="breadcrumb-item"><a href="index.php"><i class="fa fa-home"></i></a></li>
        <li class="breadcrumb-item active">Dashboard</li>
      </ul>
    </div>

    <!-- Role Gatekeeping Alert Banner -->
    <?php if (!empty($_SESSION['rbac_error'])): ?>
      <div class="alert alert-warning alert-dismissible fade show" style="border-radius: 14px; background: rgba(245, 158, 11, 0.2); border: 1px solid #f59e0b; color: #fff;">
        <i class="fa fa-shield"></i> <?php echo htmlspecialchars($_SESSION['rbac_error']); ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
      </div>
      <?php unset($_SESSION['rbac_error']); ?>
    <?php endif; ?>

    <!-- Owner Notice: Pending Staff Approvals -->
    <?php if ($isOwnerAdmin && function_exists('get_pending_staff_count')): 
      $pStaff = get_pending_staff_count($dbh);
      if ($pStaff > 0):
    ?>
      <div class="alert alert-info alert-dismissible fade show" style="border-radius: 14px; background: rgba(56, 189, 248, 0.15); border: 1px solid #38bdf8; color: #fff; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
        <div>
          <i class="fa fa-user-plus text-warning" style="margin-right: 6px;"></i>
          <strong>Staff Approvals Pending:</strong> There <?php echo $pStaff === 1 ? 'is 1 employee' : "are $pStaff employees"; ?> waiting for your owner approval.
        </div>
        <a href="staff.php" class="btn btn-sm btn-info" style="border-radius: 8px; font-weight: 700;">
          <i class="fa fa-check"></i> Review Requests
        </a>
      </div>
    <?php endif; endif; ?>

    <!-- ============================================================== -->
    <!-- VIEW 1: GYM OWNER EXECUTIVE DASHBOARD                           -->
    <!-- ============================================================== -->
    <?php if ($isOwnerAdmin): ?>
      <!-- KPI Row 1: Membership Lifecycle -->
      <div class="row">
        <div class="col-sm-6 col-lg-3">
          <a href="members.php" class="widget-small info">
            <div class="icon"><i class="fa fa-users"></i></div>
            <div class="info">
              <h4>Total Members</h4>
              <p><?php echo $totalMembers; ?></p>
            </div>
          </a>
        </div>
        <div class="col-sm-6 col-lg-3">
          <a href="memberships.php?filter=active" class="widget-small primary">
            <div class="icon"><i class="fa fa-check-circle"></i></div>
            <div class="info">
              <h4>Active Subs</h4>
              <p><?php echo $activeMemberships; ?></p>
            </div>
          </a>
        </div>
        <div class="col-sm-6 col-lg-3">
          <a href="memberships.php?filter=renewals" class="widget-small warning">
            <div class="icon"><i class="fa fa-clock-o"></i></div>
            <div class="info">
              <h4>Renewals (14d)</h4>
              <p><?php echo $upcomingRenewals; ?></p>
            </div>
          </a>
        </div>
        <div class="col-sm-6 col-lg-3">
          <a href="memberships.php?filter=expired" class="widget-small danger">
            <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
            <div class="info">
              <h4>Expired Subs</h4>
              <p><?php echo $expiredMemberships; ?></p>
            </div>
          </a>
        </div>
      </div>

      <!-- KPI Row 2: Financial Health (Strictly Owner Only) -->
      <div class="row">
        <div class="col-md-6">
          <div class="tile" style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <div>
                <span style="font-size: 11.5px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Total Collected Revenue</span>
                <h2 style="font-size: 32px; font-weight: 800; color: #10b981; margin: 4px 0 0; letter-spacing: -0.03em; line-height: 1.1;">
                  <?php echo format_currency($totalRevenue); ?>
                </h2>
              </div>
              <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16, 185, 129, 0.14); border: 1px solid rgba(16, 185, 129, 0.28); color: #34d399; font-size: 20px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i class="fa fa-money"></i>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="tile" style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <div>
                <span style="font-size: 11.5px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Outstanding Member Balance</span>
                <h2 style="font-size: 32px; font-weight: 800; color: #fbbf24; margin: 4px 0 0; letter-spacing: -0.03em; line-height: 1.1;">
                  <?php echo format_currency($outstandingBalance); ?>
                </h2>
              </div>
              <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245, 158, 11, 0.14); border: 1px solid rgba(245, 158, 11, 0.28); color: #fbbf24; font-size: 20px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i class="fa fa-hourglass-half"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Real-Data Analytics & Growth Charts -->
      <div class="row">
        <div class="col-lg-8">
          <div class="tile" style="min-height: 360px; display: flex; flex-direction: column; justify-content: space-between; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
              <div>
                <h3 class="tile-title" style="margin: 0;"><i class="fa fa-line-chart" style="color: var(--color-primary); margin-right: 8px;"></i> Revenue &amp; Collections Trend</h3>
                <p style="font-size: 12.5px; color: #94a3b8; margin: 3px 0 0;">Historical monthly gross receipts from database ledger</p>
              </div>
              <span class="ios-badge ios-badge-success" style="font-size: 11px; padding: 4px 10px; border-radius: 20px;"><i class="fa fa-circle" style="font-size: 7px; margin-right: 5px;"></i> Live Verified Ledger</span>
            </div>
            <div style="height: 260px; position: relative; width: 100%; flex: 1;">
              <canvas id="revenueTrendChart"></canvas>
            </div>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="tile" style="min-height: 360px; display: flex; flex-direction: column; justify-content: space-between; margin-bottom: 20px;">
            <div style="margin-bottom: 16px;">
              <h3 class="tile-title" style="margin: 0;"><i class="fa fa-pie-chart" style="color: var(--color-accent); margin-right: 8px;"></i> Plan Distribution</h3>
              <p style="font-size: 12.5px; color: #94a3b8; margin: 3px 0 0;">Active pass breakdown by tier</p>
            </div>
            <div style="height: 260px; position: relative; width: 100%; flex: 1; display: flex; align-items: center; justify-content: center;">
              <?php if (!empty($planDistData)): ?>
                <canvas id="planDistChart"></canvas>
              <?php else: ?>
                <div style="text-align: center; color: #94a3b8;">
                  <i class="fa fa-pie-chart" style="font-size: 38px; margin-bottom: 10px; opacity: 0.35;"></i>
                  <p style="margin: 0; font-size: 13px;">No active subscriptions logged yet</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions Panel -->
      <div class="row">
        <div class="col-12">
          <div class="tile" style="margin-bottom: 20px; padding: 18px 24px;">
            <h3 class="tile-title" style="margin-bottom: 14px;">
              <i class="fa fa-bolt" style="color: var(--color-primary); margin-right: 8px;"></i> Management Quick Actions
            </h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
              <a href="member-create.php" class="ios-btn ios-btn-primary" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
                <i class="fa fa-user-plus"></i> Add New Member
              </a>
              <a href="add-post.php" class="ios-btn ios-btn-glass" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
                <i class="fa fa-plus-circle"></i> Create Membership Plan
              </a>
              <a href="bookings.php?filter=unpaid" class="ios-btn ios-btn-accent" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
                <i class="fa fa-credit-card"></i> Process Payments
              </a>
              <a href="website-branding.php" class="ios-btn ios-btn-glass" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
                <i class="fa fa-paint-brush"></i> Customize Branding
              </a>
              <a href="theme-settings.php" class="ios-btn ios-btn-glass" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
                <i class="fa fa-magic"></i> Theme &amp; Colors
              </a>
              <a href="reports.php" class="ios-btn ios-btn-glass" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
                <i class="fa fa-bar-chart"></i> Reports &amp; Exports
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Two-Column Ledger: Recent Payments & Member Activity -->
      <div class="row">
        <div class="col-lg-7">
          <div class="tile" style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
              <h3 class="tile-title" style="margin: 0;"><i class="fa fa-credit-card" style="color: var(--color-primary); margin-right: 8px;"></i> Recent Payments</h3>
              <a href="bookings.php" class="btn btn-sm btn-outline-light" style="border-radius: 8px; font-size: 12px; padding: 4px 12px;">View All</a>
            </div>
            <div class="table-responsive">
              <table class="table table-hover" style="margin: 0;">
                <thead>
                  <tr>
                    <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em;">Member</th>
                    <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em;">Type</th>
                    <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em;">Amount</th>
                    <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em;">Date</th>
                    <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em; text-align: right;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($recentPayments)): foreach ($recentPayments as $rp): ?>
                  <tr>
                    <td><strong><?php echo htmlspecialchars($rp->fname . ' ' . $rp->lname, ENT_QUOTES, 'UTF-8'); ?></strong></td>
                    <td>
                      <span class="ios-badge <?php echo ($rp->paymentType === 'Full Payment') ? 'ios-badge-success' : 'ios-badge-warning'; ?>" style="font-size: 11px; padding: 3px 8px;">
                        <?php echo htmlspecialchars($rp->paymentType, ENT_QUOTES, 'UTF-8'); ?>
                      </span>
                    </td>
                    <td style="font-weight: 700; color: #10b981;"><?php echo format_currency($rp->payment); ?></td>
                    <td style="color: #94a3b8; font-size: 12.5px;"><?php echo date('d M Y, h:i A', strtotime($rp->payment_date)); ?></td>
                    <td style="text-align: right;">
                      <a href="booking-history-details.php?bookingid=<?php echo $rp->bookingID; ?>" class="btn btn-sm btn-primary" style="padding: 3px 10px; border-radius: 6px; font-size: 11.5px;">Receipt</a>
                    </td>
                  </tr>
                  <?php endforeach; else: ?>
                  <tr><td colspan="5" style="text-align: center; color: #94a3b8; padding: 20px;">No payment records logged.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="tile" style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
              <h3 class="tile-title" style="margin: 0;"><i class="fa fa-user-plus" style="color: var(--color-primary); margin-right: 8px;"></i> Recent Members</h3>
              <a href="members.php" class="btn btn-sm btn-outline-light" style="border-radius: 8px; font-size: 12px; padding: 4px 12px;">Directory</a>
            </div>
            <div class="table-responsive">
              <table class="table table-hover" style="margin: 0;">
                <thead>
                  <tr>
                    <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em;">Name</th>
                    <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em;">Email</th>
                    <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em;">Joined</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($recentMembers)): foreach ($recentMembers as $rm): ?>
                  <tr>
                    <td>
                      <a href="member-profile.php?id=<?php echo $rm->id; ?>" style="color: #fff; font-weight: 600; text-decoration: none;">
                        <?php echo htmlspecialchars($rm->fname . ' ' . $rm->lname, ENT_QUOTES, 'UTF-8'); ?>
                      </a>
                    </td>
                    <td style="color: #94a3b8; font-size: 12.5px;"><?php echo htmlspecialchars($rm->email, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td style="color: #64748b; font-size: 12px;"><?php echo date('d M Y', strtotime($rm->create_date)); ?></td>
                  </tr>
                  <?php endforeach; else: ?>
                  <tr><td colspan="3" style="text-align: center; color: #94a3b8; padding: 20px;">No registered members found.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

    <!-- ============================================================== -->
    <!-- VIEW 2: OPERATIONS MANAGER DASHBOARD (STRICT OPTION A BOUNDARY) -->
    <!-- ============================================================== -->
    <?php elseif ($adminRole === 'manager'): ?>
      <!-- Row 1: Membership Lifecycle -->
      <div class="row">
        <div class="col-sm-6 col-lg-3">
          <a href="members.php" class="widget-small info">
            <div class="icon"><i class="fa fa-users"></i></div>
            <div class="info">
              <h4>Total Members</h4>
              <p><?php echo $totalMembers; ?></p>
            </div>
          </a>
        </div>
        <div class="col-sm-6 col-lg-3">
          <a href="memberships.php?filter=active" class="widget-small primary">
            <div class="icon"><i class="fa fa-check-circle"></i></div>
            <div class="info">
              <h4>Active Subs</h4>
              <p><?php echo $activeMemberships; ?></p>
            </div>
          </a>
        </div>
        <div class="col-sm-6 col-lg-3">
          <a href="memberships.php?filter=renewals" class="widget-small warning">
            <div class="icon"><i class="fa fa-clock-o"></i></div>
            <div class="info">
              <h4>Renewals (14d)</h4>
              <p><?php echo $upcomingRenewals; ?></p>
            </div>
          </a>
        </div>
        <div class="col-sm-6 col-lg-3">
          <a href="memberships.php?filter=expired" class="widget-small danger">
            <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
            <div class="info">
              <h4>Expired Subs</h4>
              <p><?php echo $expiredMemberships; ?></p>
            </div>
          </a>
        </div>
      </div>

      <!-- Row 2: Operational Health (No revenue figures) -->
      <div class="row">
        <div class="col-sm-6 col-lg-3">
          <a href="gym-qr-hub.php" class="widget-small primary">
            <div class="icon"><i class="fa fa-street-view"></i></div>
            <div class="info">
              <h4>Active on Floor</h4>
              <p><?php echo $activeFloorCount; ?></p>
            </div>
          </a>
        </div>
        <div class="col-sm-6 col-lg-3">
          <a href="refunds.php?status=pending" class="widget-small warning">
            <div class="icon"><i class="fa fa-undo"></i></div>
            <div class="info">
              <h4>Pending Refunds</h4>
              <p><?php echo $pendingRefundsCount; ?></p>
            </div>
          </a>
        </div>
        <div class="col-sm-6 col-lg-3">
          <a href="memberships.php?filter=frozen" class="widget-small info">
            <div class="icon"><i class="fa fa-pause-circle"></i></div>
            <div class="info">
              <h4>Frozen Passes</h4>
              <p><?php echo $activeFreezesCount; ?></p>
            </div>
          </a>
        </div>
        <div class="col-sm-6 col-lg-3">
          <a href="memberships.php?filter=renewals" class="widget-small danger">
            <div class="icon"><i class="fa fa-bell-o"></i></div>
            <div class="info">
              <h4>Expiring (7d)</h4>
              <p><?php echo $expiring7DaysCount; ?></p>
            </div>
          </a>
        </div>
      </div>

      <!-- Operational Distribution & Floor Status -->
      <div class="row">
        <div class="col-lg-6">
          <div class="tile" style="min-height: 320px; display: flex; flex-direction: column; justify-content: space-between; margin-bottom: 20px;">
            <div>
              <h3 class="tile-title" style="margin: 0;"><i class="fa fa-pie-chart" style="color: var(--color-accent); margin-right: 8px;"></i> Membership Tier Distribution</h3>
              <p style="font-size: 12.5px; color: #94a3b8; margin: 3px 0 16px;">Active pass distribution across membership packages</p>
            </div>
            <div style="height: 220px; position: relative; width: 100%; flex: 1; display: flex; align-items: center; justify-content: center;">
              <?php if (!empty($planDistData)): ?>
                <canvas id="planDistChart"></canvas>
              <?php else: ?>
                <div style="text-align: center; color: #94a3b8;">
                  <i class="fa fa-pie-chart" style="font-size: 38px; margin-bottom: 10px; opacity: 0.35;"></i>
                  <p style="margin: 0; font-size: 13px;">No active subscriptions logged yet</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="tile" style="min-height: 320px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
              <h3 class="tile-title" style="margin: 0;"><i class="fa fa-users" style="color: var(--color-primary); margin-right: 8px;"></i> Floor Headcount Feed</h3>
              <a href="gym-qr-hub.php" class="btn btn-sm btn-outline-light" style="border-radius: 8px; font-size: 12px; padding: 4px 12px;">Attendance Hub</a>
            </div>
            <div class="table-responsive">
              <table class="table table-hover" style="margin: 0;">
                <thead>
                  <tr>
                    <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Member</th>
                    <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Check-In</th>
                    <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; text-align: right;">Elapsed</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($activeFloorMembers)): foreach (array_slice($activeFloorMembers, 0, 5) as $afm): ?>
                    <tr>
                      <td>
                        <strong><?php echo htmlspecialchars($afm->fname . ' ' . $afm->lname); ?></strong>
                        <div style="font-size: 11px; color: #94a3b8;"><?php echo htmlspecialchars($afm->email); ?></div>
                      </td>
                      <td style="font-size: 12px; color: #94a3b8;"><?php echo date('h:i A', strtotime($afm->check_in_time)); ?></td>
                      <td style="text-align: right;">
                        <span class="badge badge-success" style="font-size: 11px; border-radius: 6px;">
                          <?php echo intval($afm->elapsed_mins); ?> mins
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; else: ?>
                    <tr><td colspan="3" style="text-align: center; color: #94a3b8; padding: 20px;">No members currently active on the gym floor.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions for Manager -->
      <div class="row">
        <div class="col-12">
          <div class="tile" style="margin-bottom: 20px; padding: 18px 24px;">
            <h3 class="tile-title" style="margin-bottom: 14px;">
              <i class="fa fa-bolt" style="color: #60a5fa; margin-right: 8px;"></i> Operations Quick Actions
            </h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
              <a href="member-create.php" class="ios-btn ios-btn-primary" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
                <i class="fa fa-user-plus"></i> Add New Member
              </a>
              <a href="memberships.php" class="ios-btn ios-btn-glass" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
                <i class="fa fa-id-card"></i> Manage Memberships
              </a>
              <a href="refunds.php" class="ios-btn ios-btn-glass" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
                <i class="fa fa-undo"></i> Refund Cases Queue
              </a>
              <a href="members.php" class="ios-btn ios-btn-glass" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
                <i class="fa fa-search"></i> Member Directory
              </a>
              <a href="gym-qr-hub.php" class="ios-btn ios-btn-glass" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
                <i class="fa fa-qrcode"></i> Attendance Station
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Manager Two-Column: Refund Cases & Recent Members -->
      <div class="row">
        <div class="col-lg-7">
          <div class="tile" style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
              <h3 class="tile-title" style="margin: 0;"><i class="fa fa-undo" style="color: #fbbf24; margin-right: 8px;"></i> Assigned Refund Requests</h3>
              <a href="refunds.php" class="btn btn-sm btn-outline-light" style="border-radius: 8px; font-size: 12px; padding: 4px 12px;">Full Queue</a>
            </div>
            <div class="table-responsive">
              <table class="table table-hover" style="margin: 0;">
                <thead>
                  <tr>
                    <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Case</th>
                    <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Member</th>
                    <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Amount</th>
                    <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Status</th>
                    <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; text-align: right;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($managerRefundCases)): foreach ($managerRefundCases as $mrc): ?>
                    <tr>
                      <td><strong style="color: #38bdf8;">#RF-<?php echo str_pad($mrc->id, 4, '0', STR_PAD_LEFT); ?></strong></td>
                      <td><strong><?php echo htmlspecialchars($mrc->fname . ' ' . $mrc->lname); ?></strong></td>
                      <td style="font-weight: 700; color: #fbbf24;"><?php echo format_currency($mrc->refund_amount); ?></td>
                      <td>
                        <span class="badge <?php echo ($mrc->status === 'pending') ? 'badge-warning' : (($mrc->status === 'approved') ? 'badge-info' : 'badge-success'); ?>" style="font-size: 10.5px; text-transform: uppercase;">
                          <?php echo htmlspecialchars($mrc->status); ?>
                        </span>
                      </td>
                      <td style="text-align: right;">
                        <a href="booking-history-details.php?bookingid=<?php echo urlencode($mrc->booking_id); ?>&refund_id=<?php echo urlencode($mrc->id); ?>" class="btn btn-sm btn-primary" style="padding: 3px 10px; border-radius: 6px; font-size: 11.5px;">Review</a>
                      </td>
                    </tr>
                  <?php endforeach; else: ?>
                    <tr><td colspan="5" style="text-align: center; color: #94a3b8; padding: 20px;">No refund cases assigned.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="tile" style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
              <h3 class="tile-title" style="margin: 0;"><i class="fa fa-user-plus" style="color: var(--color-primary); margin-right: 8px;"></i> Recent Members</h3>
              <a href="members.php" class="btn btn-sm btn-outline-light" style="border-radius: 8px; font-size: 12px; padding: 4px 12px;">Directory</a>
            </div>
            <div class="table-responsive">
              <table class="table table-hover" style="margin: 0;">
                <thead>
                  <tr>
                    <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Name</th>
                    <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Email</th>
                    <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Joined</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($recentMembers)): foreach ($recentMembers as $rm): ?>
                  <tr>
                    <td>
                      <a href="member-profile.php?id=<?php echo $rm->id; ?>" style="color: #fff; font-weight: 600; text-decoration: none;">
                        <?php echo htmlspecialchars($rm->fname . ' ' . $rm->lname); ?>
                      </a>
                    </td>
                    <td style="color: #94a3b8; font-size: 12.5px;"><?php echo htmlspecialchars($rm->email); ?></td>
                    <td style="color: #64748b; font-size: 12px;"><?php echo date('d M Y', strtotime($rm->create_date)); ?></td>
                  </tr>
                  <?php endforeach; else: ?>
                  <tr><td colspan="3" style="text-align: center; color: #94a3b8; padding: 20px;">No registered members found.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

    <!-- ============================================================== -->
    <!-- VIEW 3: FITNESS COACH / TRAINER DASHBOARD                       -->
    <!-- ============================================================== -->
    <?php elseif ($adminRole === 'trainer'): ?>
      <div class="row">
        <div class="col-sm-6 col-lg-3">
          <div class="widget-small primary">
            <div class="icon"><i class="fa fa-heartbeat"></i></div>
            <div class="info">
              <h4>Assigned Clients</h4>
              <p><?php echo $assignedClientsCount; ?></p>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="widget-small info">
            <div class="icon"><i class="fa fa-users"></i></div>
            <div class="info">
              <h4>Active on Floor</h4>
              <p><?php echo $activeFloorCount; ?></p>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="widget-small warning">
            <div class="icon"><i class="fa fa-check-circle"></i></div>
            <div class="info">
              <h4>Today's Check-ins</h4>
              <p><?php echo $todayCheckinsCount; ?></p>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <a href="members.php" class="widget-small danger">
            <div class="icon"><i class="fa fa-id-card"></i></div>
            <div class="info">
              <h4>Club Members</h4>
              <p><?php echo $totalMembers; ?></p>
            </div>
          </a>
        </div>
      </div>

      <!-- Quick Actions for Coach -->
      <div class="row">
        <div class="col-12">
          <div class="tile" style="margin-bottom: 20px; padding: 18px 24px;">
            <h3 class="tile-title" style="margin-bottom: 14px;">
              <i class="fa fa-bolt" style="color: #34d399; margin-right: 8px;"></i> Coaching Quick Actions
            </h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
              <a href="../scan.php" class="ios-btn ios-btn-primary" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
                <i class="fa fa-camera"></i> Quick Check-In Scanner
              </a>
              <a href="members.php" class="ios-btn ios-btn-glass" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
                <i class="fa fa-user"></i> Member Coaching Profiles
              </a>
              <a href="gym-qr-hub.php" class="ios-btn ios-btn-glass" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
                <i class="fa fa-qrcode"></i> Fast QR Station
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Two-Column: Assigned Clients & Active Floor Members -->
      <div class="row">
        <div class="col-lg-6">
          <div class="tile" style="margin-bottom: 20px;">
            <h3 class="tile-title"><i class="fa fa-user-circle text-primary"></i> My Assigned Clients</h3>
            <div class="table-responsive">
              <table class="table table-hover" style="margin: 0;">
                <thead>
                  <tr>
                    <th>Client Name</th>
                    <th>Contact</th>
                    <th style="text-align: right;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($myClients)): foreach ($myClients as $mc): ?>
                    <tr>
                      <td><strong><?php echo htmlspecialchars($mc->fname . ' ' . $mc->lname); ?></strong></td>
                      <td style="font-size: 12px; color: #94a3b8;"><?php echo htmlspecialchars($mc->mobile); ?></td>
                      <td style="text-align: right;">
                        <a href="member-profile.php?id=<?php echo $mc->user_id; ?>" class="btn btn-sm btn-info" style="border-radius: 6px; font-size: 11.5px;">View Profile</a>
                      </td>
                    </tr>
                  <?php endforeach; else: ?>
                    <tr><td colspan="3" style="text-align: center; color: #94a3b8; padding: 20px;">No clients currently assigned to your roster.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="tile" style="margin-bottom: 20px;">
            <h3 class="tile-title"><i class="fa fa-street-view text-success"></i> Members on Floor Right Now</h3>
            <div class="table-responsive">
              <table class="table table-hover" style="margin: 0;">
                <thead>
                  <tr>
                    <th>Member</th>
                    <th>Check-In Time</th>
                    <th style="text-align: right;">Time on Floor</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($activeFloorMembers)): foreach (array_slice($activeFloorMembers, 0, 5) as $afm): ?>
                    <tr>
                      <td><strong><?php echo htmlspecialchars($afm->fname . ' ' . $afm->lname); ?></strong></td>
                      <td style="font-size: 12px; color: #94a3b8;"><?php echo date('h:i A', strtotime($afm->check_in_time)); ?></td>
                      <td style="text-align: right;">
                        <span class="badge badge-success" style="font-size: 11px; border-radius: 6px;">
                          <?php echo intval($afm->elapsed_mins); ?> mins
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; else: ?>
                    <tr><td colspan="3" style="text-align: center; color: #94a3b8; padding: 20px;">Floor is currently empty.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

    <!-- ============================================================== -->
    <!-- VIEW 4: FRONT DESK / FLOOR STAFF DASHBOARD                      -->
    <!-- ============================================================== -->
    <?php else: ?>
      <div class="row">
        <div class="col-sm-6 col-lg-3">
          <div class="widget-small primary">
            <div class="icon"><i class="fa fa-users"></i></div>
            <div class="info">
              <h4>Active on Floor</h4>
              <p><?php echo $activeFloorCount; ?></p>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="widget-small info">
            <div class="icon"><i class="fa fa-check-circle"></i></div>
            <div class="info">
              <h4>Today's Check-ins</h4>
              <p><?php echo $todayCheckinsCount; ?></p>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <a href="memberships.php?filter=active" class="widget-small warning">
            <div class="icon"><i class="fa fa-id-card"></i></div>
            <div class="info">
              <h4>Active Passes</h4>
              <p><?php echo $activeMemberships; ?></p>
            </div>
          </a>
        </div>
        <div class="col-sm-6 col-lg-3">
          <a href="memberships.php?filter=renewals" class="widget-small danger">
            <div class="icon"><i class="fa fa-clock-o"></i></div>
            <div class="info">
              <h4>Expiring (7d)</h4>
              <p><?php echo $expiring7DaysCount; ?></p>
            </div>
          </a>
        </div>
      </div>

      <!-- Quick Actions for Front Desk -->
      <div class="row">
        <div class="col-12">
          <div class="tile" style="margin-bottom: 20px; padding: 18px 24px;">
            <h3 class="tile-title" style="margin-bottom: 14px;">
              <i class="fa fa-bolt" style="color: #a78bfa; margin-right: 8px;"></i> Front Desk Actions
            </h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
              <a href="../scan.php" class="ios-btn ios-btn-primary" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
                <i class="fa fa-camera"></i> Fast Check-In Scanner
              </a>
              <a href="gym-qr-hub.php" class="ios-btn ios-btn-glass" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
                <i class="fa fa-qrcode"></i> Print QR Station Poster
              </a>
              <a href="member-create.php" class="ios-btn ios-btn-glass" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
                <i class="fa fa-user-plus"></i> Register Member
              </a>
              <a href="members.php" class="ios-btn ios-btn-glass" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
                <i class="fa fa-search"></i> Member Lookup
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Two-Column: Live Floor Feed & Recent Registrations -->
      <div class="row">
        <div class="col-lg-7">
          <div class="tile" style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
              <h3 class="tile-title" style="margin: 0;"><i class="fa fa-feed text-primary"></i> Live Floor Attendance Feed</h3>
              <a href="gym-qr-hub.php" class="btn btn-sm btn-outline-light" style="border-radius: 8px; font-size: 12px; padding: 4px 12px;">Attendance Hub</a>
            </div>
            <div class="table-responsive">
              <table class="table table-hover" style="margin: 0;">
                <thead>
                  <tr>
                    <th>Member</th>
                    <th>Check-In Time</th>
                    <th>Method</th>
                    <th style="text-align: right;">Time on Floor</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($activeFloorMembers)): foreach ($activeFloorMembers as $afm): ?>
                    <tr>
                      <td>
                        <strong><?php echo htmlspecialchars($afm->fname . ' ' . $afm->lname); ?></strong>
                        <div style="font-size: 11px; color: #94a3b8;"><?php echo htmlspecialchars($afm->email); ?></div>
                      </td>
                      <td style="font-size: 12px; color: #94a3b8;"><?php echo date('h:i A', strtotime($afm->check_in_time)); ?></td>
                      <td>
                        <span class="badge badge-info" style="font-size: 10.5px; border-radius: 6px;">
                          <?php echo htmlspecialchars($afm->check_in_method); ?>
                        </span>
                      </td>
                      <td style="text-align: right;">
                        <span class="badge badge-success" style="font-size: 11px; border-radius: 6px;">
                          <?php echo intval($afm->elapsed_mins); ?> mins
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; else: ?>
                    <tr><td colspan="4" style="text-align: center; color: #94a3b8; padding: 25px;">No members currently checked in.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="tile" style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
              <h3 class="tile-title" style="margin: 0;"><i class="fa fa-user-plus text-info"></i> Member Directory Lookups</h3>
              <a href="members.php" class="btn btn-sm btn-outline-light" style="border-radius: 8px; font-size: 12px; padding: 4px 12px;">Directory</a>
            </div>
            <div class="table-responsive">
              <table class="table table-hover" style="margin: 0;">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Joined</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($recentMembers)): foreach ($recentMembers as $rm): ?>
                  <tr>
                    <td>
                      <a href="member-profile.php?id=<?php echo $rm->id; ?>" style="color: #fff; font-weight: 600; text-decoration: none;">
                        <?php echo htmlspecialchars($rm->fname . ' ' . $rm->lname); ?>
                      </a>
                    </td>
                    <td style="color: #94a3b8; font-size: 12.5px;"><?php echo htmlspecialchars($rm->email); ?></td>
                    <td style="color: #64748b; font-size: 12px;"><?php echo date('d M Y', strtotime($rm->create_date)); ?></td>
                  </tr>
                  <?php endforeach; else: ?>
                  <tr><td colspan="3" style="text-align: center; color: #94a3b8; padding: 20px;">No registered members found.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </main>

  <script src="js/jquery-3.2.1.min.js"></script>
  <script src="js/popper.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/main.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    <?php if ($isOwnerAdmin): ?>
    // 1. Monthly Revenue Trend Line Chart (Owner Only)
    const revCanvas = document.getElementById('revenueTrendChart');
    if (revCanvas) {
      const revCtx = revCanvas.getContext('2d');
      const revGrad = revCtx.createLinearGradient(0, 0, 0, 260);
      revGrad.addColorStop(0, 'rgba(56, 189, 248, 0.35)');
      revGrad.addColorStop(1, 'rgba(56, 189, 248, 0.01)');

      new Chart(revCtx, {
        type: 'line',
        data: {
          labels: <?php echo $chartLabels; ?>,
          datasets: [{
            label: 'Gross Collections (₹)',
            data: <?php echo $chartRevenue; ?>,
            borderColor: '#38bdf8',
            borderWidth: 2.5,
            backgroundColor: revGrad,
            fill: true,
            tension: 0.35,
            pointBackgroundColor: '#ffffff',
            pointBorderColor: '#38bdf8',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: 'rgba(15, 23, 42, 0.95)',
              titleColor: '#fff',
              bodyColor: '#38bdf8',
              borderColor: 'rgba(255, 255, 255, 0.12)',
              borderWidth: 1,
              cornerRadius: 10,
              padding: 12,
              callbacks: {
                label: (ctx) => ' Collections: ₹' + Number(ctx.raw).toLocaleString('en-IN')
              }
            }
          },
          scales: {
            x: {
              grid: { color: 'rgba(255, 255, 255, 0.04)' },
              ticks: { color: '#94a3b8', font: { size: 11 } }
            },
            y: {
              grid: { color: 'rgba(255, 255, 255, 0.04)' },
              ticks: {
                color: '#94a3b8',
                font: { size: 11 },
                callback: (v) => '₹' + v
              }
            }
          }
        }
      });
    }
    <?php endif; ?>

    // 2. Plan Distribution Donut Chart
    const planCanvas = document.getElementById('planDistChart');
    if (planCanvas) {
      const planCtx = planCanvas.getContext('2d');
      new Chart(planCtx, {
        type: 'doughnut',
        data: {
          labels: <?php echo $planLabels; ?>,
          datasets: [{
            data: <?php echo $planCounts; ?>,
            backgroundColor: [
              '#38bdf8',
              '#f59e0b',
              '#10b981',
              '#a855f7',
              '#ec4899',
              '#6366f1'
            ],
            borderColor: '#0f172a',
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                color: '#cbd5e1',
                font: { size: 11, weight: '500' },
                padding: 14,
                boxWidth: 12,
                boxHeight: 12,
                borderRadius: 3,
                useBorderRadius: true
              }
            }
          },
          cutout: '70%'
        }
      });
    }
  </script>
</body>
</html>
