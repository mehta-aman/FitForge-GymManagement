<?php
require_once 'include/config.php';
require_role(['owner', 'manager']);

// 1. KPI Calculations
$totalRevenue = floatval($dbh->query("SELECT COALESCE(SUM(payment), 0) FROM tblpayment")->fetchColumn());
$totalMembers = intval($dbh->query("SELECT COUNT(*) FROM tbluser")->fetchColumn());
$activeMemberships = intval($dbh->query("SELECT COUNT(*) FROM tbl_memberships WHERE status = 'active' AND expiry_date >= CURDATE()")->fetchColumn());
$expiringSoon = intval($dbh->query("SELECT COUNT(*) FROM tbl_memberships WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)")->fetchColumn());

// Calculate outstanding dues across bookings
$dueQuery = "SELECT SUM(
    GREATEST(0, (CAST(t2.Price AS DECIMAL(10,2)) + CAST(COALESCE(t2.registration_fee,0) AS DECIMAL(10,2)) - CAST(COALESCE(t2.discount,0) AS DECIMAL(10,2))) - (SELECT COALESCE(SUM(p.payment), 0) FROM tblpayment p WHERE p.bookingID = t1.id))
) as total_dues
FROM tblbooking t1
JOIN tbladdpackage t2 ON t1.package_id = t2.id";
$totalDues = floatval($dbh->query($dueQuery)->fetchColumn());

// 2. Recent Payments with Receipt link
$recentPayments = $dbh->query("
    SELECT p.*, b.userid, u.fname, u.lname, pkg.titlename as package_name
    FROM tblpayment p
    JOIN tblbooking b ON p.bookingID = b.id
    JOIN tbluser u ON b.userid = u.id
    JOIN tbladdpackage pkg ON b.package_id = pkg.id
    ORDER BY p.id DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// 3. Current Month Registrations
$currMonthSignups = intval($dbh->query("SELECT COUNT(*) FROM tbluser WHERE MONTH(create_date) = MONTH(CURDATE()) AND YEAR(create_date) = YEAR(CURDATE())")->fetchColumn());
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Reports & Financial Analytics — Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php echo render_favicon_tags(); ?>
  <link rel="stylesheet" type="text/css" href="css/main.css">
  <link rel="stylesheet" type="text/css" href="css/ios-admin-glass.css">
  <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
  <style>
    .hub-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 20px;
      margin-bottom: 24px;
    }
    .hub-card {
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(25px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 20px;
      padding: 24px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: all 0.25s ease;
      position: relative;
      overflow: hidden;
    }
    .hub-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 30px rgba(0,0,0,0.3);
      border-color: rgba(255,255,255,0.2);
    }
    .hub-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0; height: 3px;
      background: var(--card-accent, var(--color-primary));
    }
    .hub-header {
      display: flex;
      align-items: center;
      gap: 16px;
      margin-bottom: 16px;
    }
    .hub-icon {
      width: 52px;
      height: 52px;
      border-radius: 14px;
      background: var(--icon-bg, rgba(255,45,85,0.15));
      color: var(--icon-color, var(--color-primary));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
    }
    .hub-title {
      font-size: 17px;
      font-weight: 700;
      color: #fff;
      margin: 0;
    }
    .hub-desc {
      font-size: 13px;
      color: var(--text-muted);
      line-height: 1.5;
      margin-bottom: 20px;
      flex-grow: 1;
    }
    .hub-actions {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .hub-quicklinks {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-top: 10px;
      padding-top: 12px;
      border-top: 1px solid rgba(255,255,255,0.06);
    }
    .hub-quicklinks a {
      font-size: 11.5px;
      color: #94a3b8;
      text-decoration: none;
      padding: 3px 8px;
      border-radius: 6px;
      background: rgba(255,255,255,0.05);
      transition: all 0.15s;
    }
    .hub-quicklinks a:hover {
      background: rgba(255,255,255,0.15);
      color: #fff;
    }
  </style>
</head>
<body class="app sidebar-mini rtl">
  <?php include 'include/header.php'; ?>
  <div class="app-sidebar__overlay" data-toggle="sidebar"></div>
  <?php include 'include/sidebar.php'; ?>

  <main class="app-content">
    <div class="app-title">
      <div>
        <h1><i class="fa fa-bar-chart" style="color: var(--color-primary); margin-right: 8px;"></i> Analytics & Reports Command Center</h1>
        <p>Comprehensive operational intelligence, financial ledgers, renewal alerts, and membership analytics</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Reports Hub</li>
      </ul>
    </div>

    <!-- Live Executive Metrics Strip -->
    <div class="row" style="margin-bottom: 24px;">
      <div class="col-md-6 col-lg-3">
        <div class="widget-small primary coloured-icon" style="border-radius: 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
          <i class="icon fa fa-money fa-3x" style="background: rgba(16,185,129,0.2); color: #10b981;"></i>
          <div class="info">
            <h4 style="color: #cbd5e1; font-size: 13px;">Total Revenue</h4>
            <p style="font-size: 20px; font-weight: 800; color: #10b981;"><b><?php echo format_currency($totalRevenue); ?></b></p>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-lg-3">
        <div class="widget-small warning coloured-icon" style="border-radius: 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
          <i class="icon fa fa-hourglass-half fa-3x" style="background: rgba(239,68,68,0.2); color: #ef4444;"></i>
          <div class="info">
            <h4 style="color: #cbd5e1; font-size: 13px;">Outstanding Dues</h4>
            <p style="font-size: 20px; font-weight: 800; color: #ef4444;"><b><?php echo format_currency($totalDues); ?></b></p>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-lg-3">
        <div class="widget-small danger coloured-icon" style="border-radius: 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
          <i class="icon fa fa-clock-o fa-3x" style="background: rgba(245,158,11,0.2); color: #f59e0b;"></i>
          <div class="info">
            <h4 style="color: #cbd5e1; font-size: 13px;">Expiring (14 Days)</h4>
            <p style="font-size: 20px; font-weight: 800; color: #f59e0b;"><b><?php echo $expiringSoon; ?> passes</b></p>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-lg-3">
        <div class="widget-small info coloured-icon" style="border-radius: 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
          <i class="icon fa fa-users fa-3x" style="background: rgba(59,130,246,0.2); color: #3b82f6;"></i>
          <div class="info">
            <h4 style="color: #cbd5e1; font-size: 13px;">Total Members</h4>
            <p style="font-size: 20px; font-weight: 800; color: #fff;"><b><?php echo $totalMembers; ?></b> <span style="font-size: 12px; color: #34d399;">(+<?php echo $currMonthSignups; ?> mo)</span></p>
          </div>
        </div>
      </div>
    </div>

    <!-- 4 Report Modules Hub -->
    <div class="hub-grid">
      <!-- 1. Expiring Memberships -->
      <div class="hub-card" style="--card-accent: #f59e0b; --icon-bg: rgba(245,158,11,0.15); --icon-color: #f59e0b;">
        <div>
          <div class="hub-header">
            <div class="hub-icon"><i class="fa fa-clock-o"></i></div>
            <div>
              <h3 class="hub-title">Expiring Memberships</h3>
              <span class="ios-badge ios-badge-warning" style="font-size: 11px;">Retention &amp; Renewals</span>
            </div>
          </div>
          <p class="hub-desc">
            Identify memberships expiring within the next 7, 14, or 30 days. Directly trigger automated WhatsApp renewal reminders and process seamless renewals.
          </p>
        </div>
        <div class="hub-actions">
          <a href="report-expirations.php?range=14" class="ios-btn ios-btn-accent btn-block text-center" style="padding: 10px; border-radius: 12px; text-decoration: none;">
            <i class="fa fa-calendar-check-o"></i> Launch Expiration Report
          </a>
          <div class="hub-quicklinks">
            <span style="font-size: 11px; color: var(--text-muted); align-self: center;">Quick:</span>
            <a href="report-expirations.php?range=7">Next 7 Days</a>
            <a href="report-expirations.php?range=30">Next 30 Days</a>
            <a href="report-expirations.php?range=expired">Expired</a>
            <a href="report-expirations.php?export=csv&range=30"><i class="fa fa-download"></i> CSV</a>
          </div>
        </div>
      </div>

      <!-- 2. Outstanding Dues Ledger -->
      <div class="hub-card" style="--card-accent: #ef4444; --icon-bg: rgba(239,68,68,0.15); --icon-color: #ef4444;">
        <div>
          <div class="hub-header">
            <div class="hub-icon"><i class="fa fa-money"></i></div>
            <div>
              <h3 class="hub-title">Outstanding Dues Ledger</h3>
              <span class="ios-badge ios-badge-danger" style="font-size: 11px;">Revenue Recovery</span>
            </div>
          </div>
          <p class="hub-desc">
            Complete accounts receivable ledger for partial payments and unpaid gym packages. Calculate balance totals, send WhatsApp alerts, and collect payments.
          </p>
        </div>
        <div class="hub-actions">
          <a href="report-dues.php?status=all" class="ios-btn ios-btn-accent btn-block text-center" style="padding: 10px; border-radius: 12px; text-decoration: none; background: linear-gradient(135deg, #ef4444, #f59e0b);">
            <i class="fa fa-list-alt"></i> Open Outstanding Dues
          </a>
          <div class="hub-quicklinks">
            <span style="font-size: 11px; color: var(--text-muted); align-self: center;">Quick:</span>
            <a href="report-dues.php?status=partial">Partial Dues</a>
            <a href="report-dues.php?status=unpaid">Unpaid Only</a>
            <a href="report-dues.php?export=csv"><i class="fa fa-download"></i> CSV</a>
          </div>
        </div>
      </div>

      <!-- 3. Sales & Bookings -->
      <div class="hub-card" style="--card-accent: #10b981; --icon-bg: rgba(16,185,129,0.15); --icon-color: #10b981;">
        <div>
          <div class="hub-header">
            <div class="hub-icon"><i class="fa fa-line-chart"></i></div>
            <div>
              <h3 class="hub-title">Sales &amp; Bookings</h3>
              <span class="ios-badge ios-badge-success" style="font-size: 11px;">Financial Records</span>
            </div>
          </div>
          <p class="hub-desc">
            Audit revenue and booked packages within any custom calendar period. Detailed breakdown of plans, package fees, member contracts, and overall gross sales.
          </p>
        </div>
        <div class="hub-actions">
          <a href="report-booking.php" class="ios-btn ios-btn-accent btn-block text-center" style="padding: 10px; border-radius: 12px; text-decoration: none;">
            <i class="fa fa-filter"></i> Generate Sales Report
          </a>
          <div class="hub-quicklinks">
            <span style="font-size: 11px; color: var(--text-muted); align-self: center;">Quick:</span>
            <a href="report-booking.php?Submit=1&fdate=<?php echo date('Y-m-01'); ?>&todate=<?php echo date('Y-m-d'); ?>">This Month</a>
            <a href="report-booking.php?export=csv&fdate=<?php echo date('Y-m-01'); ?>&todate=<?php echo date('Y-m-d'); ?>"><i class="fa fa-download"></i> CSV</a>
          </div>
        </div>
      </div>

      <!-- 4. Member Registrations -->
      <div class="hub-card" style="--card-accent: #3b82f6; --icon-bg: rgba(59,130,246,0.15); --icon-color: #3b82f6;">
        <div>
          <div class="hub-header">
            <div class="hub-icon"><i class="fa fa-user-plus"></i></div>
            <div>
              <h3 class="hub-title">Member Registrations</h3>
              <span class="ios-badge ios-badge-info" style="font-size: 11px;">Member Acquisition</span>
            </div>
          </div>
          <p class="hub-desc">
            Analyze acquisition trends, new sign-up velocity, and demographic distribution. Export member directories and track monthly gym growth targets.
          </p>
        </div>
        <div class="hub-actions">
          <a href="report-registration.php" class="ios-btn ios-btn-accent btn-block text-center" style="padding: 10px; border-radius: 12px; text-decoration: none;">
            <i class="fa fa-users"></i> Member Sign-up Report
          </a>
          <div class="hub-quicklinks">
            <span style="font-size: 11px; color: var(--text-muted); align-self: center;">Quick:</span>
            <a href="report-registration.php?Submit=1&fdate=<?php echo date('Y-m-01'); ?>&todate=<?php echo date('Y-m-d'); ?>">This Month</a>
            <a href="report-registration.php?export=csv&fdate=<?php echo date('Y-m-01'); ?>&todate=<?php echo date('Y-m-d'); ?>"><i class="fa fa-download"></i> CSV</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Payments & Receipts Ledger Strip -->
    <div class="tile">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
        <div>
          <h3 style="font-size: 18px; font-weight: 700; margin: 0; color: #fff;">
            <i class="fa fa-history" style="color: var(--color-primary); margin-right: 6px;"></i> Latest Transactions &amp; Receipts
          </h3>
          <p style="font-size: 13px; color: var(--text-muted); margin: 2px 0 0;">Recently collected payments with official downloadable receipts</p>
        </div>
        <a href="booking-history.php" class="btn btn-outline-light btn-sm" style="border-radius: 10px;">
          View All Transactions <i class="fa fa-arrow-right"></i>
        </a>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-bordered" style="margin: 0;">
          <thead>
            <tr>
              <th style="width: 50px;">#</th>
              <th>Member Name</th>
              <th>Plan</th>
              <th>Amount</th>
              <th>Method</th>
              <th>Ref / UTR</th>
              <th>Date &amp; Time</th>
              <th style="text-align: right;">Official Receipt</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($recentPayments)): foreach ($recentPayments as $p): ?>
            <tr>
              <td style="color: var(--text-muted);">#<?php echo $p['id']; ?></td>
              <td style="font-weight: 700; color: #fff;">
                <a href="member-profile.php?id=<?php echo $p['userid']; ?>" style="color: inherit; text-decoration: none;">
                  <?php echo htmlspecialchars($p['fname'] . ' ' . $p['lname']); ?>
                </a>
              </td>
              <td style="color: #cbd5e1;"><?php echo htmlspecialchars($p['package_name']); ?></td>
              <td style="font-weight: 700; color: #34d399; font-size: 14.5px;"><?php echo format_currency($p['payment']); ?></td>
              <td>
                <span class="ios-badge ios-badge-info" style="font-size: 11.5px;">
                  <i class="fa fa-credit-card"></i> <?php echo htmlspecialchars($p['payment_method'] ?? 'Cash'); ?>
                </span>
              </td>
              <td style="font-family: monospace; font-size: 12.5px; color: #94a3b8;">
                <?php echo !empty($p['transaction_ref']) ? htmlspecialchars($p['transaction_ref']) : '—'; ?>
              </td>
              <td style="font-size: 12.5px; color: var(--text-muted);">
                <?php echo date('d M Y, h:i A', strtotime($p['payment_date'])); ?>
              </td>
              <td style="text-align: right;">
                <a href="receipt.php?bookingid=<?php echo $p['bookingID']; ?>" target="_blank" class="btn btn-sm btn-outline-light" style="border-radius: 8px; font-weight: 600;" title="Print Official Branded Receipt">
                  <i class="fa fa-print"></i> Receipt
                </a>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">No transaction records available.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <script src="js/jquery-3.2.1.min.js"></script>
  <script src="js/popper.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/main.js"></script>
</body>
</html>
