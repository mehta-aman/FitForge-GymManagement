<?php
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$range = trim($_GET['range'] ?? '30');
$search = trim($_GET['search'] ?? '');
$fdate = trim($_GET['fdate'] ?? date('Y-m-d'));
$todate = trim($_GET['todate'] ?? date('Y-m-d', strtotime('+30 days')));

// Query logic
$where = ["1=1"];
$params = [];

if ($range === '7') {
    $where[] = "m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    $rangeLabel = "Expiring in Next 7 Days";
} elseif ($range === '14') {
    $where[] = "m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)";
    $rangeLabel = "Expiring in Next 14 Days";
} elseif ($range === '30') {
    $where[] = "m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    $rangeLabel = "Expiring in Next 30 Days";
} elseif ($range === '60') {
    $where[] = "m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)";
    $rangeLabel = "Expiring in Next 60 Days";
} elseif ($range === 'expired') {
    $where[] = "m.expiry_date < CURDATE()";
    $rangeLabel = "Already Expired";
} elseif ($range === 'upcoming') {
    $where[] = "m.expiry_date >= CURDATE()";
    $rangeLabel = "All Upcoming Expirations";
} elseif ($range === 'custom') {
    $where[] = "m.expiry_date BETWEEN :fdate AND :todate";
    $params[':fdate'] = $fdate;
    $params[':todate'] = $todate;
    $rangeLabel = "Expiring from " . date('d M Y', strtotime($fdate)) . " to " . date('d M Y', strtotime($todate));
} else {
    $where[] = "m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    $rangeLabel = "Expiring in Next 30 Days";
}

if (!empty($search)) {
    $where[] = "(u.fname LIKE :s OR u.lname LIKE :s OR u.email LIKE :s OR u.mobile LIKE :s OR p.titlename LIKE :s)";
    $params[':s'] = "%$search%";
}

$whereSql = implode(' AND ', $where);

$sql = "SELECT m.*, u.fname, u.lname, u.email, u.mobile, p.titlename as plan_name, p.PackageDuratiobn,
        DATEDIFF(m.expiry_date, CURDATE()) as days_left
        FROM tbl_memberships m
        JOIN tbluser u ON m.user_id = u.id
        LEFT JOIN tbladdpackage p ON m.plan_id = p.id
        WHERE $whereSql
        ORDER BY m.expiry_date ASC";

$stmt = $dbh->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=expiring_memberships_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Member Name', 'Mobile', 'Email', 'Plan Name', 'Duration', 'Start Date', 'Expiry Date', 'Days Left', 'Status', 'Payment Status', 'Total Amount', 'Paid Amount'], ',', '"', "\\");
    foreach ($results as $row) {
        fputcsv($out, [
            $row['id'],
            $row['fname'] . ' ' . $row['lname'],
            $row['mobile'],
            $row['email'],
            $row['plan_name'] ?? 'N/A',
            $row['PackageDuratiobn'] ?? 'N/A',
            $row['start_date'],
            $row['expiry_date'],
            $row['days_left'],
            $row['status'],
            $row['payment_status'],
            $row['total_amount'],
            $row['paid_amount']
        ], ',', '"', "\\");
    }
    fclose($out);
    exit();
}

// KPI Counts for filters
$count7 = $dbh->query("SELECT count(*) FROM tbl_memberships WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
$count14 = $dbh->query("SELECT count(*) FROM tbl_memberships WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)")->fetchColumn();
$count30 = $dbh->query("SELECT count(*) FROM tbl_memberships WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$countExpired = $dbh->query("SELECT count(*) FROM tbl_memberships WHERE expiry_date < CURDATE()")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Expiring Memberships Report — Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php echo render_favicon_tags(); ?>
  <link rel="stylesheet" type="text/css" href="css/main.css">
  <link rel="stylesheet" type="text/css" href="css/ios-admin-glass.css">
  <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
  <style>
    .kpi-chip-group { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
    .kpi-chip {
      padding: 10px 16px;
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      color: #cbd5e1;
      text-decoration: none;
      font-size: 13.5px;
      font-weight: 600;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    .kpi-chip:hover {
      background: rgba(255, 255, 255, 0.1);
      color: #fff;
      transform: translateY(-1px);
    }
    .kpi-chip.active {
      background: linear-gradient(135deg, var(--color-primary), var(--color-accent));
      color: #fff;
      border-color: transparent;
      box-shadow: 0 4px 12px rgba(255, 45, 85, 0.3);
    }
    .badge-count {
      background: rgba(0,0,0,0.25);
      padding: 2px 8px;
      border-radius: 10px;
      font-size: 12px;
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
        <h1><i class="fa fa-clock-o" style="color: var(--color-primary); margin-right: 8px;"></i> Expiring Memberships Report</h1>
        <p>Monitor upcoming pass expirations, engage members proactively, and streamline renewal revenue</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="reports.php">Reports</a></li>
        <li class="breadcrumb-item active">Expiring Memberships</li>
      </ul>
    </div>

    <!-- Filter Chips -->
    <div class="kpi-chip-group">
      <a href="report-expirations.php?range=7" class="kpi-chip <?php echo ($range === '7') ? 'active' : ''; ?>">
        <i class="fa fa-exclamation-triangle text-danger"></i> Next 7 Days <span class="badge-count"><?php echo $count7; ?></span>
      </a>
      <a href="report-expirations.php?range=14" class="kpi-chip <?php echo ($range === '14') ? 'active' : ''; ?>">
        <i class="fa fa-clock-o text-warning"></i> Next 14 Days <span class="badge-count"><?php echo $count14; ?></span>
      </a>
      <a href="report-expirations.php?range=30" class="kpi-chip <?php echo ($range === '30') ? 'active' : ''; ?>">
        <i class="fa fa-calendar text-info"></i> Next 30 Days <span class="badge-count"><?php echo $count30; ?></span>
      </a>
      <a href="report-expirations.php?range=60" class="kpi-chip <?php echo ($range === '60') ? 'active' : ''; ?>">
        <i class="fa fa-calendar-check-o"></i> Next 60 Days
      </a>
      <a href="report-expirations.php?range=upcoming" class="kpi-chip <?php echo ($range === 'upcoming') ? 'active' : ''; ?>">
        <i class="fa fa-list"></i> All Upcoming
      </a>
      <a href="report-expirations.php?range=expired" class="kpi-chip <?php echo ($range === 'expired') ? 'active' : ''; ?>">
        <i class="fa fa-times-circle text-muted"></i> Already Expired <span class="badge-count"><?php echo $countExpired; ?></span>
      </a>
    </div>

    <!-- Search & Custom Range Filter -->
    <div class="tile">
      <form method="get" class="row align-items-end" style="row-gap: 12px;">
        <input type="hidden" name="range" value="custom">
        <div class="col-md-3">
          <label style="font-weight: 600; font-size: 13.5px;">Search Member / Plan</label>
          <input type="text" name="search" class="form-control" placeholder="Name, phone, plan..." value="<?php echo htmlspecialchars($search); ?>" style="border-radius: 12px;">
        </div>
        <div class="col-md-3">
          <label style="font-weight: 600; font-size: 13.5px;">From Date</label>
          <input type="date" name="fdate" class="form-control" value="<?php echo htmlspecialchars($fdate); ?>" style="border-radius: 12px;">
        </div>
        <div class="col-md-3">
          <label style="font-weight: 600; font-size: 13.5px;">To Date</label>
          <input type="date" name="todate" class="form-control" value="<?php echo htmlspecialchars($todate); ?>" style="border-radius: 12px;">
        </div>
        <div class="col-md-3 d-flex" style="gap: 8px;">
          <button type="submit" class="ios-btn ios-btn-accent flex-grow-1" style="padding: 10px; border-radius: 12px;">
            <i class="fa fa-filter"></i> Apply
          </button>
          <a href="report-expirations.php" class="btn btn-outline-secondary" style="border-radius: 12px; padding: 10px 14px;" title="Reset filters">
            <i class="fa fa-refresh"></i>
          </a>
        </div>
      </form>
    </div>

    <!-- Results Table -->
    <div class="tile">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
        <div>
          <h3 style="font-size: 18px; font-weight: 700; margin: 0; color: #fff;">
            <?php echo htmlspecialchars($rangeLabel); ?>
            <span class="ios-badge ios-badge-info" style="margin-left: 8px;"><?php echo count($results); ?> memberships</span>
          </h3>
          <p style="margin: 4px 0 0; font-size: 13px; color: var(--text-muted);">Proactively contact members before expiration to maximize recurring gym revenue.</p>
        </div>
        <div style="display: flex; gap: 8px;">
          <?php
          $exportQuery = $_GET;
          $exportQuery['export'] = 'csv';
          $exportUrl = 'report-expirations.php?' . http_build_query($exportQuery);
          ?>
          <a href="<?php echo htmlspecialchars($exportUrl); ?>" class="btn btn-outline-success btn-sm" style="border-radius: 10px; font-weight: 600;">
            <i class="fa fa-file-excel-o"></i> Export CSV
          </a>
          <button type="button" class="btn btn-outline-light btn-sm" onclick="window.print()" style="border-radius: 10px;">
            <i class="fa fa-print"></i> Print
          </button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-bordered" style="margin: 0;">
          <thead>
            <tr>
              <th style="width: 50px;">#</th>
              <th>Member Name</th>
              <th>Phone / WhatsApp</th>
              <th>Current Plan</th>
              <th>Start Date</th>
              <th>Expiry Date</th>
              <th>Days Left</th>
              <th>Status</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($results)): $cnt = 1; foreach ($results as $row): 
              $daysLeft = intval($row['days_left']);
              $cleanPhone = preg_replace('/[^0-9]/', '', $row['mobile']);
              $gymName = function_exists('get_setting') ? get_setting('gym_name', 'Golden Eagle Gym') : 'Golden Eagle Gym';
              $waMessage = rawurlencode("Hello " . $row['fname'] . ", this is a friendly reminder from " . $gymName . ". Your " . ($row['plan_name'] ?? 'membership') . " pass expires on " . date('d M Y', strtotime($row['expiry_date'])) . ". Visit reception or reach out to renew and keep your training streak active!");
            ?>
            <tr>
              <td style="color: var(--text-muted);"><?php echo $cnt++; ?></td>
              <td>
                <div style="font-weight: 700; color: #fff;">
                  <a href="member-profile.php?id=<?php echo $row['user_id']; ?>" style="color: inherit; text-decoration: none;">
                    <?php echo htmlspecialchars($row['fname'] . ' ' . $row['lname']); ?>
                  </a>
                </div>
                <div style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($row['email']); ?></div>
              </td>
              <td>
                <div style="font-weight: 600; color: #cbd5e1;"><?php echo htmlspecialchars($row['mobile']); ?></div>
                <?php if (!empty($cleanPhone)): ?>
                  <a href="https://wa.me/<?php echo $cleanPhone; ?>?text=<?php echo $waMessage; ?>" target="_blank" class="btn btn-sm" style="padding: 2px 8px; font-size: 11px; border-radius: 6px; background: rgba(37, 211, 102, 0.2); color: #25d366; border: 1px solid rgba(37, 211, 102, 0.3); margin-top: 4px; display: inline-flex; align-items: center; gap: 4px;">
                    <i class="fa fa-whatsapp"></i> Send Reminder
                  </a>
                <?php endif; ?>
              </td>
              <td>
                <div style="font-weight: 600; color: var(--color-primary);"><?php echo htmlspecialchars($row['plan_name'] ?? 'Plan #' . $row['plan_id']); ?></div>
                <div style="font-size: 11.5px; color: var(--text-muted);"><?php echo htmlspecialchars($row['PackageDuratiobn'] ?? ''); ?></div>
              </td>
              <td style="font-size: 13px; color: var(--text-muted);"><?php echo date('d M Y', strtotime($row['start_date'])); ?></td>
              <td style="font-size: 13.5px; font-weight: 700; color: #fff;"><?php echo date('d M Y', strtotime($row['expiry_date'])); ?></td>
              <td>
                <?php if ($daysLeft < 0): ?>
                  <span class="ios-badge ios-badge-danger"><i class="fa fa-exclamation-circle"></i> Expired <?php echo abs($daysLeft); ?>d ago</span>
                <?php elseif ($daysLeft == 0): ?>
                  <span class="ios-badge ios-badge-danger" style="animation: pulse 1.5s infinite;"><i class="fa fa-bolt"></i> Expires Today!</span>
                <?php elseif ($daysLeft <= 3): ?>
                  <span class="ios-badge ios-badge-danger"><i class="fa fa-hourglass-end"></i> <?php echo $daysLeft; ?> days left</span>
                <?php elseif ($daysLeft <= 7): ?>
                  <span class="ios-badge ios-badge-warning"><i class="fa fa-clock-o"></i> <?php echo $daysLeft; ?> days left</span>
                <?php else: ?>
                  <span class="ios-badge ios-badge-info"><i class="fa fa-calendar"></i> <?php echo $daysLeft; ?> days left</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="ios-badge <?php echo ($row['status'] === 'active') ? 'ios-badge-success' : (($row['status'] === 'frozen') ? 'ios-badge-warning' : 'ios-badge-danger'); ?>">
                  <?php echo ucfirst($row['status']); ?>
                </span>
              </td>
              <td style="text-align: right;">
                <div class="btn-group" role="group">
                  <a href="member-profile.php?id=<?php echo $row['user_id']; ?>" class="btn btn-sm btn-outline-primary" style="border-radius: 8px; font-weight: 600;" title="View Profile & Renew">
                    <i class="fa fa-repeat"></i> Renew
                  </a>
                  <a href="member-profile.php?id=<?php echo $row['user_id']; ?>" class="btn btn-sm btn-outline-light" style="border-radius: 8px; margin-left: 4px;" title="View Member Profile">
                    <i class="fa fa-eye"></i>
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr>
              <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 40px;">
                <i class="fa fa-check-circle" style="font-size: 36px; color: #34d399; margin-bottom: 10px; display: block;"></i>
                No memberships found matching this expiration criteria.
              </td>
            </tr>
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
