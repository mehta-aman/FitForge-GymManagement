<?php  
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

// 1. Core KPIs
$totalMembers = $dbh->query("SELECT count(*) FROM tbluser")->fetchColumn();
$activeMemberships = $dbh->query("SELECT count(*) FROM tbl_memberships WHERE status = 'active' AND expiry_date >= CURDATE()")->fetchColumn();
$expiredMemberships = $dbh->query("SELECT count(*) FROM tbl_memberships WHERE status = 'expired' OR expiry_date < CURDATE()")->fetchColumn();
$upcomingRenewals = $dbh->query("SELECT count(*) FROM tbl_memberships WHERE status = 'active' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)")->fetchColumn();

// 2. Financial KPIs
$totalRevenue = floatval($dbh->query("SELECT SUM(payment) FROM tblpayment")->fetchColumn() ?: 0);
$totalBilled = floatval($dbh->query("SELECT SUM(total_amount) FROM tbl_memberships")->fetchColumn() ?: 0);
$outstandingBalance = max(0, $totalBilled - $totalRevenue);

// 3. Recent Transactions
$recentPayments = $dbh->query("SELECT p.id, p.payment, p.paymentType, p.payment_date, p.bookingID, u.fname, u.lname 
    FROM tblpayment p
    LEFT JOIN tblbooking b ON p.bookingID = b.id
    LEFT JOIN tbluser u ON b.userid = u.id
    ORDER BY p.id DESC LIMIT 5")->fetchAll(PDO::FETCH_OBJ);

// 4. Recent Member Registrations
$recentMembers = $dbh->query("SELECT id, fname, lname, email, mobile, create_date, status FROM tbluser ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_OBJ);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Dashboard — <?php echo htmlspecialchars(get_setting('gym_name', 'GYM MS'), ENT_QUOTES, 'UTF-8'); ?></title>
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
        <h1><i class="fa fa-tachometer" style="color: var(--color-primary); margin-right: 8px;"></i> Control Center</h1>
        <p>Real-time analytics across member subscriptions, revenue streams, and club activities</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item active">Dashboard</li>
      </ul>
    </div>

    <!-- KPI Row 1: Membership Lifecycle -->
    <div class="row">
      <!-- Total Members -->
      <div class="col-md-6 col-lg-3">
        <a href="members.php" class="widget-small info">
          <div class="icon"><i class="fa fa-users"></i></div>
          <div class="info">
            <h4>Total Members</h4>
            <p><?php echo $totalMembers; ?></p>
          </div>
        </a>
      </div>

      <!-- Active Memberships -->
      <div class="col-md-6 col-lg-3">
        <a href="memberships.php?filter=active" class="widget-small primary">
          <div class="icon" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.25), rgba(5, 150, 105, 0.25)) !important; color: #34d399 !important; border-color: rgba(16, 185, 129, 0.4) !important;">
            <i class="fa fa-check-circle"></i>
          </div>
          <div class="info">
            <h4>Active Subs</h4>
            <p><?php echo $activeMemberships; ?></p>
          </div>
        </a>
      </div>

      <!-- Expiring Soon -->
      <div class="col-md-6 col-lg-3">
        <a href="memberships.php?filter=renewals" class="widget-small warning">
          <div class="icon"><i class="fa fa-clock-o"></i></div>
          <div class="info">
            <h4>Renewals (14d)</h4>
            <p><?php echo $upcomingRenewals; ?></p>
          </div>
        </a>
      </div>

      <!-- Expired Subs -->
      <div class="col-md-6 col-lg-3">
        <a href="memberships.php?filter=expired" class="widget-small danger">
          <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
          <div class="info">
            <h4>Expired Subs</h4>
            <p><?php echo $expiredMemberships; ?></p>
          </div>
        </a>
      </div>
    </div>

    <!-- KPI Row 2: Financial Health -->
    <div class="row">
      <!-- Total Revenue -->
      <div class="col-md-6">
        <div class="tile" style="padding: 24px 28px; margin-bottom: 24px;">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
              <span style="font-size: 13px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em;">Total Collected Revenue</span>
              <h2 style="font-size: 38px; font-weight: 800; color: #34d399; margin: 6px 0 0; letter-spacing: -0.04em;">
                <?php echo format_currency($totalRevenue); ?>
              </h2>
            </div>
            <div class="ios-auth-icon" style="margin: 0; width: 56px; height: 56px; font-size: 24px; background: rgba(16, 185, 129, 0.15); color: #34d399; border-color: rgba(16, 185, 129, 0.35);">
              <i class="fa fa-money"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Outstanding Balance -->
      <div class="col-md-6">
        <div class="tile" style="padding: 24px 28px; margin-bottom: 24px;">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
              <span style="font-size: 13px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em;">Outstanding Member Balance</span>
              <h2 style="font-size: 38px; font-weight: 800; color: #fbbf24; margin: 6px 0 0; letter-spacing: -0.04em;">
                <?php echo format_currency($outstandingBalance); ?>
              </h2>
            </div>
            <div class="ios-auth-icon" style="margin: 0; width: 56px; height: 56px; font-size: 24px; background: rgba(245, 158, 11, 0.15); color: #fbbf24; border-color: rgba(245, 158, 11, 0.35);">
              <i class="fa fa-hourglass-half"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions Panel -->
    <div class="row">
      <div class="col-12">
        <div class="tile" style="padding: 24px 28px;">
          <h3 class="tile-title" style="margin-bottom: 16px;">
            <i class="fa fa-bolt" style="color: var(--color-primary); margin-right: 6px;"></i> Management Quick Actions
          </h3>
          <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <a href="member-create.php" class="ios-btn ios-btn-primary">
              <i class="fa fa-user-plus"></i> Add New Member
            </a>
            <a href="add-post.php" class="ios-btn ios-btn-glass">
              <i class="fa fa-plus-circle"></i> Create Membership Plan
            </a>
            <a href="bookings.php?filter=unpaid" class="ios-btn ios-btn-accent">
              <i class="fa fa-credit-card"></i> Process Payments
            </a>
            <a href="website-branding.php" class="ios-btn ios-btn-glass">
              <i class="fa fa-paint-brush"></i> Customize Branding
            </a>
            <a href="theme-settings.php" class="ios-btn ios-btn-glass">
              <i class="fa fa-magic"></i> Theme &amp; Colors
            </a>
            <a href="reports.php" class="ios-btn ios-btn-glass">
              <i class="fa fa-bar-chart"></i> Reports &amp; Exports
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Two-Column Ledger: Recent Payments & Member Activity -->
    <div class="row">
      <!-- Recent Payments -->
      <div class="col-lg-7">
        <div class="tile">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h3 class="tile-title" style="margin: 0;"><i class="fa fa-credit-card" style="color: var(--color-primary);"></i> Recent Payments</h3>
            <a href="bookings.php" class="btn btn-sm btn-outline-light" style="border-radius: 10px;">View All</a>
          </div>
          <div class="table-responsive">
            <table class="table table-hover table-bordered" style="margin: 0;">
              <thead>
                <tr>
                  <th>Member</th>
                  <th>Type</th>
                  <th>Amount</th>
                  <th>Date</th>
                  <th style="text-align: right;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($recentPayments)): foreach ($recentPayments as $rp): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($rp->fname . ' ' . $rp->lname, ENT_QUOTES, 'UTF-8'); ?></strong></td>
                  <td>
                    <span class="ios-badge <?php echo ($rp->paymentType === 'Full Payment') ? 'ios-badge-success' : 'ios-badge-warning'; ?>">
                      <?php echo htmlspecialchars($rp->paymentType, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                  </td>
                  <td style="font-weight: 700; color: #34d399;"><?php echo format_currency($rp->payment); ?></td>
                  <td style="color: var(--text-secondary); font-size: 13px;"><?php echo date('d M Y, h:i A', strtotime($rp->payment_date)); ?></td>
                  <td style="text-align: right;">
                    <a href="booking-history-details.php?bookingid=<?php echo $rp->bookingID; ?>" class="btn btn-sm btn-primary" style="padding: 4px 10px; border-radius: 8px;">Receipt</a>
                  </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 20px;">No payment records logged.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Recent Members -->
      <div class="col-lg-5">
        <div class="tile">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h3 class="tile-title" style="margin: 0;"><i class="fa fa-user-plus" style="color: var(--color-primary);"></i> Recent Members</h3>
            <a href="members.php" class="btn btn-sm btn-outline-light" style="border-radius: 10px;">Directory</a>
          </div>
          <div class="table-responsive">
            <table class="table table-hover table-bordered" style="margin: 0;">
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
                      <?php echo htmlspecialchars($rm->fname . ' ' . $rm->lname, ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                  </td>
                  <td style="color: var(--text-secondary); font-size: 13px;"><?php echo htmlspecialchars($rm->email, ENT_QUOTES, 'UTF-8'); ?></td>
                  <td style="color: var(--text-muted); font-size: 12px;"><?php echo date('d M Y', strtotime($rm->create_date)); ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 20px;">No registered members found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="js/jquery-3.2.1.min.js"></script>
  <script src="js/popper.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/main.js"></script>
</body>
</html>
