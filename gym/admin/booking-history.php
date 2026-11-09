<?php
require_once 'include/config.php';
require_role(['owner', 'manager']);

$typeFilter = trim($_GET['type'] ?? 'all');
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['p'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// Base query joining bookings, packages, users, categories
$sqlBase = "FROM tblbooking t1
  JOIN tbladdpackage t2 ON t1.package_id = t2.id
  JOIN tbluser t3 ON t1.userid = t3.id
  LEFT JOIN tblcategory t4 ON t2.category = t4.id
  LEFT JOIN tblpackage t5 ON t2.PackageType = t5.id";

$where = [];
$params = [];

if ($typeFilter === 'new') {
    $where[] = "(t1.paymentType IS NULL OR t1.paymentType = '' OR t1.paymentType = '0' OR (SELECT COALESCE(SUM(p.payment), 0) FROM tblpayment p WHERE p.bookingID = t1.id) = 0)";
} elseif ($typeFilter === 'partial') {
    $where[] = "(SELECT COALESCE(SUM(p.payment), 0) FROM tblpayment p WHERE p.bookingID = t1.id) > 0 AND (SELECT COALESCE(SUM(p.payment), 0) FROM tblpayment p WHERE p.bookingID = t1.id) < CAST(t2.Price AS DECIMAL(10,2))";
} elseif ($typeFilter === 'full') {
    $where[] = "(SELECT COALESCE(SUM(p.payment), 0) FROM tblpayment p WHERE p.bookingID = t1.id) >= CAST(t2.Price AS DECIMAL(10,2))";
}

if ($search !== '') {
    $where[] = "(t1.id = :sId OR t3.fname LIKE :s OR t3.lname LIKE :s OR t3.email LIKE :s OR t2.titlename LIKE :s)";
    $params[':sId'] = intval($search);
    $params[':s'] = "%$search%";
}

$whereSql = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

// Count Total
$countStmt = $dbh->prepare("SELECT count(*) $sqlBase $whereSql");
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Fetch Bookings with Calculated Paid Sum
$selectSql = "SELECT t1.id as bookingid, t1.booking_date as bookingdate, t1.paymentType as bookingPaymentType,
              t3.id as userid, t3.fname, t3.lname, t3.email, t3.mobile,
              t2.titlename as title, t2.PackageDuratiobn, t2.Price as price,
              t4.category_name, t5.PackageName,
              (SELECT COALESCE(SUM(p.payment), 0) FROM tblpayment p WHERE p.bookingID = t1.id) as total_paid
              $sqlBase $whereSql ORDER BY t1.id DESC LIMIT $limit OFFSET $offset";

$query = $dbh->prepare($selectSql);
$query->execute($params);
$bookings = $query->fetchAll(PDO::FETCH_ASSOC);

// Counts for tabs
$totalAll = $dbh->query("SELECT count(*) FROM tblbooking")->fetchColumn();
$totalNew = $dbh->query("SELECT count(*) FROM tblbooking b JOIN tbladdpackage p ON b.package_id = p.id WHERE (SELECT COALESCE(SUM(pay.payment), 0) FROM tblpayment pay WHERE pay.bookingID = b.id) = 0")->fetchColumn();
$totalPartial = $dbh->query("SELECT count(*) FROM tblbooking b JOIN tbladdpackage p ON b.package_id = p.id WHERE (SELECT COALESCE(SUM(pay.payment), 0) FROM tblpayment pay WHERE pay.bookingID = b.id) > 0 AND (SELECT COALESCE(SUM(pay.payment), 0) FROM tblpayment pay WHERE pay.bookingID = b.id) < CAST(p.Price AS DECIMAL(10,2))")->fetchColumn();
$totalFull = $dbh->query("SELECT count(*) FROM tblbooking b JOIN tbladdpackage p ON b.package_id = p.id WHERE (SELECT COALESCE(SUM(pay.payment), 0) FROM tblpayment pay WHERE pay.bookingID = b.id) >= CAST(p.Price AS DECIMAL(10,2))")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Payments & Bookings Hub — Admin</title>
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
        <h1><i class="fa fa-credit-card-alt" style="color: var(--color-primary); margin-right: 8px;"></i> Payments & Bookings Hub</h1>
        <p>Monitor package reservations, installment payments, and outstanding balances</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Bookings & Payments</li>
      </ul>
    </div>

    <!-- Filter Tabs -->
    <div style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;">
      <a href="booking-history.php?type=all" class="btn <?php echo ($typeFilter === 'all') ? 'btn-primary' : 'btn-outline-light'; ?>" style="border-radius: 12px; font-size: 13.5px; padding: 8px 16px;">
        All Bookings (<?php echo $totalAll; ?>)
      </a>
      <a href="booking-history.php?type=new" class="btn <?php echo ($typeFilter === 'new') ? 'btn-primary' : 'btn-outline-light'; ?>" style="border-radius: 12px; font-size: 13.5px; padding: 8px 16px;">
        <i class="fa fa-clock-o text-info"></i> New / Unpaid (<?php echo $totalNew; ?>)
      </a>
      <a href="booking-history.php?type=partial" class="btn <?php echo ($typeFilter === 'partial') ? 'btn-primary' : 'btn-outline-light'; ?>" style="border-radius: 12px; font-size: 13.5px; padding: 8px 16px;">
        <i class="fa fa-adjust text-warning"></i> Partial Payment (<?php echo $totalPartial; ?>)
      </a>
      <a href="booking-history.php?type=full" class="btn <?php echo ($typeFilter === 'full') ? 'btn-primary' : 'btn-outline-light'; ?>" style="border-radius: 12px; font-size: 13.5px; padding: 8px 16px;">
        <i class="fa fa-check-circle text-success"></i> Full Payment (<?php echo $totalFull; ?>)
      </a>
    </div>

    <!-- Controls -->
    <div class="tile" style="padding: 20px 24px;">
      <form method="get" class="row align-items-center" style="row-gap: 12px;">
        <input type="hidden" name="type" value="<?php echo htmlspecialchars($typeFilter); ?>">
        <div class="col-md-8">
          <input type="text" name="search" class="form-control" placeholder="Search by booking #, member name, email, or package title..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="col-md-4 text-md-right">
          <button type="submit" class="btn btn-primary" style="padding: 8px 20px; border-radius: 12px;">
            <i class="fa fa-search"></i> Search
          </button>
          <a href="booking-history.php" class="btn btn-outline-light" style="padding: 8px 16px; border-radius: 12px; margin-left: 6px;">
            Reset
          </a>
        </div>
      </form>
    </div>

    <!-- Bookings Table -->
    <div class="tile">
      <div class="table-responsive">
        <table class="table table-hover table-bordered" style="margin: 0;">
          <thead>
            <tr>
              <th style="width: 70px;">Book #</th>
              <th>Member Name</th>
              <th>Package Title</th>
              <th>Total Fee</th>
              <th>Total Paid</th>
              <th>Balance Due</th>
              <th>Status</th>
              <th>Booking Date</th>
              <th style="text-align: right; width: 130px;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($bookings)): foreach ($bookings as $b): 
              $price = floatval($b['price']);
              $paid = floatval($b['total_paid']);
              $bal = max(0, $price - $paid);
            ?>
            <tr>
              <td style="color: var(--color-primary); font-weight: 700;">#<?php echo $b['bookingid']; ?></td>
              <td>
                <a href="member-profile.php?id=<?php echo $b['userid']; ?>" style="color: #fff; font-weight: 700; text-decoration: none;">
                  <?php echo htmlspecialchars($b['fname'] . ' ' . $b['lname']); ?>
                </a>
                <div style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($b['email']); ?></div>
              </td>
              <td>
                <span style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($b['title']); ?></span>
                <div style="font-size: 12px; color: var(--text-secondary);"><?php echo htmlspecialchars($b['category_name'] ?? 'General'); ?></div>
              </td>
              <td style="font-weight: 600;"><?php echo format_currency($price); ?></td>
              <td style="font-weight: 600; color: #34d399;"><?php echo format_currency($paid); ?></td>
              <td style="font-weight: 600; color: <?php echo ($bal > 0) ? '#f87171' : '#34d399'; ?>;">
                <?php echo format_currency($bal); ?>
              </td>
              <td>
                <?php if ($paid >= $price && $price > 0): ?>
                  <span class="ios-badge ios-badge-success">Full Payment</span>
                <?php elseif ($paid > 0): ?>
                  <span class="ios-badge ios-badge-warning">Partial Payment</span>
                <?php else: ?>
                  <span class="ios-badge ios-badge-danger">Unpaid / New</span>
                <?php endif; ?>
              </td>
              <td style="color: var(--text-muted); font-size: 13px;"><?php echo date('d M Y', strtotime($b['bookingdate'])); ?></td>
              <td style="text-align: right;">
                <a href="booking-history-details.php?bookingid=<?php echo $b['bookingid']; ?>" class="btn btn-sm btn-primary" style="border-radius: 8px; padding: 4px 12px;" title="View Ledger & Collect Payment">
                  <i class="fa fa-folder-open-o"></i> Ledger
                </a>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="9" style="text-align: center; color: var(--text-muted); padding: 30px;">No bookings found for this view.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px;">
        <span style="font-size: 13px; color: var(--text-secondary);">Showing page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
        <ul class="pagination" style="margin: 0;">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
              <a class="page-link" href="booking-history.php?p=<?php echo $i; ?>&type=<?php echo urlencode($typeFilter); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
  </main>

  <script src="js/jquery-3.2.1.min.js"></script>
  <script src="js/popper.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/main.js"></script>
</body>
</html>
