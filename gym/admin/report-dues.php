<?php
require_once 'include/config.php';
require_owner(); // Option A: Restricted Manager Financial Access — Dues reports are Owner-Only

$statusFilter = trim($_GET['status'] ?? 'all');
$search = trim($_GET['search'] ?? '');

// Base SQL
$baseSql = "FROM tblbooking t1
            JOIN tbluser t3 ON t1.userid = t3.id
            JOIN tbladdpackage t2 ON t1.package_id = t2.id
            LEFT JOIN tblcategory t4 ON t2.category = t4.id";

$where = [];
$params = [];

if ($statusFilter === 'unpaid') {
    $where[] = "(SELECT COALESCE(SUM(CAST(p.payment AS DECIMAL(10,2)) - CAST(COALESCE(p.refunded_amount,0) AS DECIMAL(10,2))), 0) FROM tblpayment p WHERE p.bookingID = t1.id) = 0";
} elseif ($statusFilter === 'partial') {
    $where[] = "(SELECT COALESCE(SUM(CAST(p.payment AS DECIMAL(10,2)) - CAST(COALESCE(p.refunded_amount,0) AS DECIMAL(10,2))), 0) FROM tblpayment p WHERE p.bookingID = t1.id) > 0 
                AND (SELECT COALESCE(SUM(CAST(p.payment AS DECIMAL(10,2)) - CAST(COALESCE(p.refunded_amount,0) AS DECIMAL(10,2))), 0) FROM tblpayment p WHERE p.bookingID = t1.id) < (CAST(t2.Price AS DECIMAL(10,2)) + CAST(COALESCE(t2.registration_fee,0) AS DECIMAL(10,2)) - CAST(COALESCE(t2.discount,0) AS DECIMAL(10,2)))";
} else {
    // All dues (unpaid or partial)
    $where[] = "(SELECT COALESCE(SUM(CAST(p.payment AS DECIMAL(10,2)) - CAST(COALESCE(p.refunded_amount,0) AS DECIMAL(10,2))), 0) FROM tblpayment p WHERE p.bookingID = t1.id) < (CAST(t2.Price AS DECIMAL(10,2)) + CAST(COALESCE(t2.registration_fee,0) AS DECIMAL(10,2)) - CAST(COALESCE(t2.discount,0) AS DECIMAL(10,2)))";
}

if (!empty($search)) {
    $where[] = "(t1.id = :sId OR t3.fname LIKE :s OR t3.lname LIKE :s OR t3.email LIKE :s OR t3.mobile LIKE :s OR t2.titlename LIKE :s)";
    $params[':sId'] = intval($search);
    $params[':s'] = "%$search%";
}

$whereSql = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

$querySql = "SELECT t1.id as bookingid, t1.booking_date,
             t3.id as userid, t3.fname, t3.lname, t3.email, t3.mobile,
             t2.titlename as package_name, t2.PackageDuratiobn,
             CAST(t2.Price AS DECIMAL(10,2)) as price,
             CAST(COALESCE(t2.registration_fee, 0) AS DECIMAL(10,2)) as reg_fee,
             CAST(COALESCE(t2.discount, 0) AS DECIMAL(10,2)) as discount,
             (CAST(t2.Price AS DECIMAL(10,2)) + CAST(COALESCE(t2.registration_fee,0) AS DECIMAL(10,2)) - CAST(COALESCE(t2.discount,0) AS DECIMAL(10,2))) as total_fee,
             (SELECT COALESCE(SUM(CAST(p.payment AS DECIMAL(10,2)) - CAST(COALESCE(p.refunded_amount,0) AS DECIMAL(10,2))), 0) FROM tblpayment p WHERE p.bookingID = t1.id) as paid_amount,
             (SELECT MAX(p.payment_date) FROM tblpayment p WHERE p.bookingID = t1.id) as last_payment_date
             $baseSql $whereSql
             ORDER BY ((CAST(t2.Price AS DECIMAL(10,2)) + CAST(COALESCE(t2.registration_fee,0) AS DECIMAL(10,2)) - CAST(COALESCE(t2.discount,0) AS DECIMAL(10,2))) - (SELECT COALESCE(SUM(CAST(p.payment AS DECIMAL(10,2)) - CAST(COALESCE(p.refunded_amount,0) AS DECIMAL(10,2))), 0) FROM tblpayment p WHERE p.bookingID = t1.id)) DESC";

$stmt = $dbh->prepare($querySql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totalOutstanding = 0;
$totalCollectedOnDues = 0;
$countUnpaid = 0;
$countPartial = 0;

foreach ($results as &$r) {
    $r['balance_due'] = max(0, floatval($r['total_fee']) - floatval($r['paid_amount']));
    $totalOutstanding += $r['balance_due'];
    $totalCollectedOnDues += floatval($r['paid_amount']);
    if (floatval($r['paid_amount']) <= 0) {
        $countUnpaid++;
    } else {
        $countPartial++;
    }
}
unset($r);

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=outstanding_dues_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Booking ID', 'Member Name', 'Mobile', 'Email', 'Plan Title', 'Total Fee', 'Paid Amount', 'Balance Due', 'Status', 'Last Payment Date', 'Booking Date'], ',', '"', "\\");
    foreach ($results as $row) {
        $statusStr = (floatval($row['paid_amount']) <= 0) ? 'Unpaid' : 'Partial';
        fputcsv($out, [
            $row['bookingid'],
            $row['fname'] . ' ' . $row['lname'],
            $row['mobile'],
            $row['email'],
            $row['package_name'],
            $row['total_fee'],
            $row['paid_amount'],
            $row['balance_due'],
            $statusStr,
            $row['last_payment_date'] ?? 'None',
            $row['booking_date']
        ], ',', '"', "\\");
    }
    fclose($out);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Outstanding Dues Ledger — Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php echo render_favicon_tags(); ?>
  <link rel="stylesheet" type="text/css" href="css/main.css">
  <link rel="stylesheet" type="text/css" href="css/ios-admin-glass.css">
  <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
  <style>
    .dues-kpi-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }
    .dues-kpi-card {
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 16px;
      padding: 18px 20px;
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .dues-kpi-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
    }
    .filter-tab-bar {
      display: flex;
      gap: 8px;
      margin-bottom: 16px;
      flex-wrap: wrap;
    }
    .filter-tab {
      padding: 8px 16px;
      border-radius: 10px;
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.1);
      color: #cbd5e1;
      text-decoration: none;
      font-size: 13px;
      font-weight: 600;
      transition: all 0.2s;
    }
    .filter-tab:hover { background: rgba(255,255,255,0.1); color: #fff; }
    .filter-tab.active {
      background: var(--color-primary);
      color: #fff;
      border-color: transparent;
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
        <h1><i class="fa fa-money" style="color: #f59e0b; margin-right: 8px;"></i> Outstanding Dues Ledger</h1>
        <p>Complete recovery ledger of all pending payments, partial dues, and automated collection reminders</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="reports.php">Reports</a></li>
        <li class="breadcrumb-item active">Outstanding Dues</li>
      </ul>
    </div>

    <!-- Executive Dues KPIs -->
    <div class="dues-kpi-grid">
      <div class="dues-kpi-card" style="border-left: 4px solid #ef4444;">
        <div class="dues-kpi-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
          <i class="fa fa-exclamation-circle"></i>
        </div>
        <div>
          <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Total Balance Due</div>
          <div style="font-size: 22px; font-weight: 800; color: #ef4444;"><?php echo format_currency($totalOutstanding); ?></div>
        </div>
      </div>

      <div class="dues-kpi-card" style="border-left: 4px solid #f59e0b;">
        <div class="dues-kpi-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
          <i class="fa fa-hourglass-half"></i>
        </div>
        <div>
          <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Partial Due Accounts</div>
          <div style="font-size: 22px; font-weight: 800; color: #f59e0b;"><?php echo $countPartial; ?> members</div>
        </div>
      </div>

      <div class="dues-kpi-card" style="border-left: 4px solid #ec4899;">
        <div class="dues-kpi-icon" style="background: rgba(236, 72, 153, 0.15); color: #ec4899;">
          <i class="fa fa-ban"></i>
        </div>
        <div>
          <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Unpaid Accounts</div>
          <div style="font-size: 22px; font-weight: 800; color: #ec4899;"><?php echo $countUnpaid; ?> members</div>
        </div>
      </div>

      <div class="dues-kpi-card" style="border-left: 4px solid #10b981;">
        <div class="dues-kpi-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
          <i class="fa fa-check-circle"></i>
        </div>
        <div>
          <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Collected On These</div>
          <div style="font-size: 22px; font-weight: 800; color: #10b981;"><?php echo format_currency($totalCollectedOnDues); ?></div>
        </div>
      </div>
    </div>

    <!-- Filter Bar & Search -->
    <div class="tile">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
        <div class="filter-tab-bar" style="margin-bottom: 0;">
          <a href="report-dues.php?status=all" class="filter-tab <?php echo ($statusFilter === 'all') ? 'active' : ''; ?>">
            All Dues (<?php echo count($results); ?>)
          </a>
          <a href="report-dues.php?status=partial" class="filter-tab <?php echo ($statusFilter === 'partial') ? 'active' : ''; ?>">
            Partial Due Only
          </a>
          <a href="report-dues.php?status=unpaid" class="filter-tab <?php echo ($statusFilter === 'unpaid') ? 'active' : ''; ?>">
            Fully Unpaid Only
          </a>
        </div>

        <div style="display: flex; gap: 8px;">
          <?php
          $exportQuery = $_GET;
          $exportQuery['export'] = 'csv';
          $exportUrl = 'report-dues.php?' . http_build_query($exportQuery);
          ?>
          <a href="<?php echo htmlspecialchars($exportUrl); ?>" class="btn btn-outline-success btn-sm" style="border-radius: 10px; font-weight: 600;">
            <i class="fa fa-file-excel-o"></i> Export CSV
          </a>
          <button type="button" class="btn btn-outline-light btn-sm" onclick="window.print()" style="border-radius: 10px;">
            <i class="fa fa-print"></i> Print
          </button>
        </div>
      </div>

      <form method="get" class="row align-items-center" style="row-gap: 12px;">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
        <div class="col-md-9">
          <input type="text" name="search" class="form-control" placeholder="Search by Booking ID, Member Name, Mobile, Email, or Plan..." value="<?php echo htmlspecialchars($search); ?>" style="border-radius: 12px;">
        </div>
        <div class="col-md-3 d-flex" style="gap: 8px;">
          <button type="submit" class="ios-btn ios-btn-accent flex-grow-1" style="padding: 10px; border-radius: 12px;">
            <i class="fa fa-search"></i> Search
          </button>
          <?php if (!empty($search)): ?>
            <a href="report-dues.php?status=<?php echo htmlspecialchars($statusFilter); ?>" class="btn btn-outline-secondary" style="border-radius: 12px; padding: 10px 14px;">
              <i class="fa fa-times"></i>
            </a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Ledger Table -->
    <div class="tile">
      <div class="table-responsive">
        <table class="table table-hover table-bordered" style="margin: 0;">
          <thead>
            <tr>
              <th style="width: 70px;">Booking</th>
              <th>Member Name & Contact</th>
              <th>Plan / Package</th>
              <th>Total Fee</th>
              <th>Amount Paid</th>
              <th>Balance Due</th>
              <th>Status</th>
              <th>Last Payment</th>
              <th style="text-align: right;">Recovery Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($results)): foreach ($results as $row): 
              $isUnpaid = (floatval($row['paid_amount']) <= 0);
              $cleanPhone = preg_replace('/[^0-9]/', '', $row['mobile']);
              $gymName = function_exists('get_setting') ? get_setting('gym_name', 'Golden Eagle Gym') : 'Golden Eagle Gym';
              $currencySymbol = function_exists('get_setting') ? get_setting('currency_symbol', '₹') : '₹';
              $waMessage = rawurlencode("Hello " . $row['fname'] . ", this is a payment notification from " . $gymName . ". A pending balance of " . $currencySymbol . number_format($row['balance_due'], 2) . " is outstanding on your " . $row['package_name'] . " membership. Kindly settle your dues at reception at your earliest convenience.");
            ?>
            <tr>
              <td style="font-weight: 700; color: var(--color-primary);">#<?php echo $row['bookingid']; ?></td>
              <td>
                <div style="font-weight: 700; color: #fff;">
                  <a href="member-profile.php?id=<?php echo $row['userid']; ?>" style="color: inherit; text-decoration: none;">
                    <?php echo htmlspecialchars($row['fname'] . ' ' . $row['lname']); ?>
                  </a>
                </div>
                <div style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($row['email']); ?></div>
                <div style="font-size: 12px; color: #94a3b8; font-weight: 600;"><?php echo htmlspecialchars($row['mobile']); ?></div>
              </td>
              <td>
                <div style="font-weight: 600; color: #cbd5e1;"><?php echo htmlspecialchars($row['package_name']); ?></div>
                <div style="font-size: 11.5px; color: var(--text-muted);"><?php echo htmlspecialchars($row['PackageDuratiobn']); ?></div>
              </td>
              <td style="font-weight: 600; color: #fff;"><?php echo format_currency($row['total_fee']); ?></td>
              <td style="font-weight: 600; color: #34d399;"><?php echo format_currency($row['paid_amount']); ?></td>
              <td style="font-size: 15px; font-weight: 800; color: #ef4444;"><?php echo format_currency($row['balance_due']); ?></td>
              <td>
                <?php if ($isUnpaid): ?>
                  <span class="ios-badge ios-badge-danger"><i class="fa fa-times-circle"></i> Unpaid</span>
                <?php else: ?>
                  <span class="ios-badge ios-badge-warning"><i class="fa fa-hourglass-half"></i> Partial</span>
                <?php endif; ?>
              </td>
              <td style="font-size: 12.5px; color: var(--text-muted);">
                <?php echo !empty($row['last_payment_date']) ? date('d M Y', strtotime($row['last_payment_date'])) : '—'; ?>
              </td>
              <td style="text-align: right;">
                <div style="display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap;">
                  <?php if (!empty($cleanPhone)): ?>
                    <a href="https://wa.me/<?php echo $cleanPhone; ?>?text=<?php echo $waMessage; ?>" target="_blank" class="btn btn-sm" style="background: rgba(37, 211, 102, 0.2); color: #25d366; border: 1px solid rgba(37, 211, 102, 0.3); border-radius: 8px; font-weight: 600;" title="Send WhatsApp Due Alert">
                      <i class="fa fa-whatsapp"></i> Remind
                    </a>
                  <?php endif; ?>
                  <a href="booking-history-details.php?bookingid=<?php echo $row['bookingid']; ?>" class="ios-btn ios-btn-accent btn-sm" style="padding: 4px 10px; font-size: 12px; border-radius: 8px;" title="Collect Payment">
                    <i class="fa fa-credit-card"></i> Collect
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <tr style="background: rgba(255,255,255,0.06); font-weight: 800;">
              <td colspan="5" style="text-align: right; color: #fff; font-size: 14px;">Grand Total Outstanding Balance:</td>
              <td colspan="4" style="color: #ef4444; font-size: 18px;"><?php echo format_currency($totalOutstanding); ?></td>
            </tr>
            <?php else: ?>
            <tr>
              <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 40px;">
                <i class="fa fa-check-circle" style="font-size: 36px; color: #34d399; margin-bottom: 10px; display: block;"></i>
                Congratulations! No outstanding dues found matching this criteria.
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
