<?php
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$msg = '';
$err = '';

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=gym_memberships_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Membership ID', 'Member Name', 'Email', 'Plan Title', 'Start Date', 'Expiry Date', 'Status', 'Total Price', 'Paid Amount', 'Payment Status']);
    
    $rows = $dbh->query("SELECT m.id, CONCAT(u.fname, ' ', u.lname) as member_name, u.email, p.titlename, m.start_date, m.expiry_date, m.status, m.total_amount, m.paid_amount, m.payment_status FROM tbl_memberships m LEFT JOIN tbluser u ON m.user_id = u.id LEFT JOIN tbladdpackage p ON m.plan_id = p.id ORDER BY m.id DESC");
    while ($r = $rows->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            $r['id'],
            $r['member_name'],
            $r['email'],
            $r['titlename'],
            $r['start_date'],
            $r['expiry_date'],
            $r['status'],
            $r['total_amount'],
            $r['paid_amount'],
            $r['payment_status']
        ]);
    }
    fclose($out);
    exit();
}

// Filters & Pagination
$tab = trim($_GET['tab'] ?? 'all');
$paymentTab = trim($_GET['pay'] ?? 'all');
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['p'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];

if ($tab === 'active') {
    $where[] = "m.status = 'active' AND m.expiry_date >= CURDATE()";
} elseif ($tab === 'expiring') {
    $where[] = "m.status = 'active' AND m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)";
} elseif ($tab === 'expired') {
    $where[] = "(m.status = 'expired' OR (m.status != 'frozen' AND m.status != 'cancelled' AND m.expiry_date < CURDATE()))";
} elseif ($tab === 'scheduled') {
    $where[] = "(m.status = 'scheduled' OR (m.status = 'active' AND m.start_date > CURDATE()))";
} elseif ($tab === 'frozen') {
    $where[] = "m.status = 'frozen'";
} elseif ($tab === 'cancelled') {
    $where[] = "m.status = 'cancelled'";
}

if ($paymentTab !== 'all' && in_array($paymentTab, ['paid', 'partial', 'unpaid'])) {
    $where[] = "m.payment_status = :pstatus";
    $params[':pstatus'] = $paymentTab;
}

if ($search !== '') {
    $where[] = "(u.fname LIKE :s OR u.lname LIKE :s OR u.email LIKE :s OR p.titlename LIKE :s OR m.id = :sId)";
    $params[':s'] = "%$search%";
    $params[':sId'] = intval($search);
}

$whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

// Count
$countStmt = $dbh->prepare("SELECT count(*) FROM tbl_memberships m LEFT JOIN tbluser u ON m.user_id = u.id LEFT JOIN tbladdpackage p ON m.plan_id = p.id $whereClause");
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Fetch
$queryStmt = $dbh->prepare("SELECT m.*, u.fname, u.lname, u.email, u.mobile, p.titlename, p.PackageDuratiobn, c.category_name FROM tbl_memberships m LEFT JOIN tbluser u ON m.user_id = u.id LEFT JOIN tbladdpackage p ON m.plan_id = p.id LEFT JOIN tblcategory c ON p.category = c.id $whereClause ORDER BY m.id DESC LIMIT $limit OFFSET $offset");
$queryStmt->execute($params);
$memberships = $queryStmt->fetchAll(PDO::FETCH_ASSOC);

// Counts for tabs
$activeCount = $dbh->query("SELECT count(*) FROM tbl_memberships WHERE status = 'active' AND expiry_date >= CURDATE() AND start_date <= CURDATE()")->fetchColumn();
$scheduledCount = $dbh->query("SELECT count(*) FROM tbl_memberships WHERE status = 'scheduled' OR (status = 'active' AND start_date > CURDATE())")->fetchColumn();
$expiringCount = $dbh->query("SELECT count(*) FROM tbl_memberships WHERE status = 'active' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)")->fetchColumn();
$frozenCount = $dbh->query("SELECT count(*) FROM tbl_memberships WHERE status = 'frozen'")->fetchColumn();
$expiredCount = $dbh->query("SELECT count(*) FROM tbl_memberships WHERE status = 'expired' OR (status NOT IN ('frozen', 'cancelled') AND expiry_date < CURDATE())")->fetchColumn();
$cancelledCount = $dbh->query("SELECT count(*) FROM tbl_memberships WHERE status = 'cancelled'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Memberships Lifecycle — Admin</title>
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
        <h1><i class="fa fa-id-badge" style="color: var(--color-primary); margin-right: 8px;"></i> Membership Subscriptions</h1>
        <p>Monitor membership lifecycles, renewal countdowns, and subscription billing</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Memberships</li>
      </ul>
    </div>

    <!-- Filter Nav Tabs -->
    <div style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;">
      <a href="memberships.php?tab=all" class="btn <?php echo ($tab === 'all') ? 'btn-primary' : 'btn-outline-light'; ?>" style="border-radius: 12px; font-size: 13.5px; padding: 8px 16px;">
        All Subscriptions
      </a>
      <a href="memberships.php?tab=active" class="btn <?php echo ($tab === 'active') ? 'btn-primary' : 'btn-outline-light'; ?>" style="border-radius: 12px; font-size: 13.5px; padding: 8px 16px;">
        <i class="fa fa-check-circle text-success"></i> Active (<?php echo $activeCount; ?>)
      </a>
      <a href="memberships.php?tab=scheduled" class="btn <?php echo ($tab === 'scheduled') ? 'btn-primary' : 'btn-outline-light'; ?>" style="border-radius: 12px; font-size: 13.5px; padding: 8px 16px;">
        <i class="fa fa-calendar text-info"></i> Scheduled (<?php echo $scheduledCount; ?>)
      </a>
      <a href="memberships.php?tab=expiring" class="btn <?php echo ($tab === 'expiring') ? 'btn-primary' : 'btn-outline-light'; ?>" style="border-radius: 12px; font-size: 13.5px; padding: 8px 16px;">
        <i class="fa fa-clock-o text-warning"></i> Expiring in 14d (<?php echo $expiringCount; ?>)
      </a>
      <a href="memberships.php?tab=frozen" class="btn <?php echo ($tab === 'frozen') ? 'btn-primary' : 'btn-outline-light'; ?>" style="border-radius: 12px; font-size: 13.5px; padding: 8px 16px;">
        <i class="fa fa-pause-circle text-warning"></i> Frozen (<?php echo $frozenCount; ?>)
      </a>
      <a href="memberships.php?tab=expired" class="btn <?php echo ($tab === 'expired') ? 'btn-primary' : 'btn-outline-light'; ?>" style="border-radius: 12px; font-size: 13.5px; padding: 8px 16px;">
        <i class="fa fa-times-circle text-danger"></i> Expired (<?php echo $expiredCount; ?>)
      </a>
      <a href="memberships.php?tab=cancelled" class="btn <?php echo ($tab === 'cancelled') ? 'btn-primary' : 'btn-outline-light'; ?>" style="border-radius: 12px; font-size: 13.5px; padding: 8px 16px;">
        <i class="fa fa-ban" style="color: #94a3b8;"></i> Cancelled (<?php echo $cancelledCount; ?>)
      </a>
    </div>

    <!-- Controls Bar -->
    <div class="tile" style="padding: 20px 24px;">
      <form method="get" class="row align-items-center" style="row-gap: 12px;">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
        <div class="col-md-5">
          <input type="text" name="search" class="form-control" placeholder="Search by member, email, plan title, or ID..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="col-md-3">
          <select name="pay" class="form-control" onchange="this.form.submit()">
            <option value="all" <?php echo ($paymentTab === 'all') ? 'selected' : ''; ?>>All Payment States</option>
            <option value="paid" <?php echo ($paymentTab === 'paid') ? 'selected' : ''; ?>>Fully Paid</option>
            <option value="partial" <?php echo ($paymentTab === 'partial') ? 'selected' : ''; ?>>Partial Payment</option>
            <option value="unpaid" <?php echo ($paymentTab === 'unpaid') ? 'selected' : ''; ?>>Unpaid</option>
          </select>
        </div>
        <div class="col-md-4 text-md-right">
          <button type="submit" class="btn btn-primary" style="padding: 8px 16px; border-radius: 12px;">
            <i class="fa fa-search"></i> Filter
          </button>
          <a href="memberships.php?export=csv" class="btn btn-outline-light" style="padding: 8px 16px; border-radius: 12px; margin-left: 6px;">
            <i class="fa fa-download"></i> Export CSV
          </a>
        </div>
      </form>
    </div>

    <!-- Table -->
    <div class="tile">
      <div class="table-responsive">
        <table class="table table-hover table-bordered" style="margin: 0;">
          <thead>
            <tr>
              <th style="width: 70px;">ID</th>
              <th>Member Name</th>
              <th>Plan</th>
              <th>Period</th>
              <th>Expires In</th>
              <th>Fee</th>
              <th>Paid</th>
              <th>Status</th>
              <th style="text-align: right; width: 140px;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($memberships)): foreach ($memberships as $m): 
              $days = (int)ceil((strtotime($m['expiry_date']) - time()) / 86400);
            ?>
            <tr>
              <td style="color: var(--text-muted); font-weight: 600;">#<?php echo $m['id']; ?></td>
              <td>
                <a href="member-profile.php?id=<?php echo $m['user_id']; ?>" style="color: #fff; font-weight: 700; text-decoration: none;">
                  <?php echo htmlspecialchars($m['fname'] . ' ' . $m['lname']); ?>
                </a>
                <div style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($m['email']); ?></div>
              </td>
              <td>
                <span style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($m['titlename'] ?? 'General Plan'); ?></span>
                <div style="font-size: 12px; color: var(--text-secondary);"><?php echo htmlspecialchars($m['category_name'] ?? '-'); ?></div>
              </td>
              <td style="font-size: 13px;">
                <?php echo date('d M Y', strtotime($m['start_date'])); ?> &rarr; <?php echo date('d M Y', strtotime($m['expiry_date'])); ?>
              </td>
              <td>
                <?php if ($m['status'] === 'scheduled' || $m['start_date'] > date('Y-m-d')): 
                  $startsIn = max(0, (int)ceil((strtotime($m['start_date']) - time()) / 86400));
                ?>
                  <span class="ios-badge ios-badge-info"><i class="fa fa-calendar"></i> Starts in <?php echo $startsIn; ?>d</span>
                <?php elseif ($m['status'] === 'frozen'): ?>
                  <span class="ios-badge ios-badge-warning"><i class="fa fa-pause"></i> Frozen</span>
                <?php elseif ($m['status'] === 'cancelled'): ?>
                  <span class="ios-badge ios-badge-danger">Cancelled</span>
                <?php elseif ($m['expiry_date'] < date('Y-m-d')): ?>
                  <span class="ios-badge ios-badge-danger">Expired</span>
                <?php elseif ($days <= 7): ?>
                  <span class="ios-badge ios-badge-warning"><?php echo $days; ?> Days Left</span>
                <?php else: ?>
                  <span class="ios-badge ios-badge-info"><?php echo $days; ?> Days</span>
                <?php endif; ?>
              </td>
              <td style="font-weight: 600;"><?php echo format_currency($m['total_amount']); ?></td>
              <td style="font-weight: 600; color: #34d399;"><?php echo format_currency($m['paid_amount']); ?></td>
              <td>
                <?php if ($m['payment_status'] === 'paid'): ?>
                  <span class="ios-badge ios-badge-success">Paid</span>
                <?php elseif ($m['payment_status'] === 'partial'): ?>
                  <span class="ios-badge ios-badge-warning">Partial</span>
                <?php else: ?>
                  <span class="ios-badge ios-badge-danger">Unpaid</span>
                <?php endif; ?>
              </td>
              <td style="text-align: right;">
                <a href="member-profile.php?id=<?php echo $m['user_id']; ?>" class="btn btn-sm btn-primary" style="border-radius: 8px; padding: 4px 12px;" title="View Member Profile & Renew">
                  <i class="fa fa-user"></i> Manage
                </a>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="9" style="text-align: center; color: var(--text-muted); padding: 30px;">No membership records found for this view.</td></tr>
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
              <a class="page-link" href="memberships.php?p=<?php echo $i; ?>&tab=<?php echo urlencode($tab); ?>&pay=<?php echo urlencode($paymentTab); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
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
