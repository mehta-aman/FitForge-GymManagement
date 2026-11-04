<?php
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$msg = '';
$err = '';

// Handle Status Toggle Action
if (isset($_GET['action']) && isset($_GET['uid'])) {
    $uid = intval($_GET['uid']);
    if ($_GET['action'] === 'toggle') {
        $curStatus = $dbh->query("SELECT status FROM tbluser WHERE id = $uid")->fetchColumn();
        $newStatus = ($curStatus === 'active') ? 'inactive' : 'active';
        $stmt = $dbh->prepare("UPDATE tbluser SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $uid]);
        $msg = "Member status changed to $newStatus.";
    }
}

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=gym_members_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Member ID', 'First Name', 'Last Name', 'Email', 'Mobile', 'City', 'State', 'Status', 'Registered Date']);
    
    $membersExport = $dbh->query("SELECT id, fname, lname, email, mobile, city, state, status, create_date FROM tbluser ORDER BY id ASC");
    while ($row = $membersExport->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['fname'],
            $row['lname'],
            $row['email'],
            $row['mobile'],
            $row['city'],
            $row['state'],
            $row['status'],
            $row['create_date']
        ]);
    }
    fclose($output);
    exit();
}

// Search & Filtering
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$page = max(1, intval($_GET['p'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$whereClauses = [];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(id = :searchId OR fname LIKE :search OR lname LIKE :search OR email LIKE :search OR mobile LIKE :search)";
    $params[':searchId'] = intval($search);
    $params[':search'] = "%$search%";
}

if ($statusFilter !== 'all' && in_array($statusFilter, ['active', 'inactive'])) {
    $whereClauses[] = "status = :stFilter";
    $params[':stFilter'] = $statusFilter;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(' AND ', $whereClauses) : "";

// Count Total
$countStmt = $dbh->prepare("SELECT count(*) FROM tbluser $whereSql");
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Fetch Paginated
$listStmt = $dbh->prepare("SELECT id, fname, lname, email, mobile, city, state, status, create_date FROM tbluser $whereSql ORDER BY id DESC LIMIT $limit OFFSET $offset");
$listStmt->execute($params);
$members = $listStmt->fetchAll(PDO::FETCH_OBJ);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Member Directory — Admin</title>
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
        <h1><i class="fa fa-address-book" style="color: var(--color-primary); margin-right: 8px;"></i> Member Directory</h1>
        <p>Search, filter, view profiles, and manage club memberships</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Members</li>
      </ul>
    </div>

    <?php if ($msg): ?>
      <div class="ios-badge ios-badge-success" style="display: flex; margin-bottom: 24px; padding: 14px 20px; border-radius: 16px; font-size: 14px;">
        <i class="fa fa-check-circle" style="font-size: 18px;"></i>
        <span><?php echo htmlentities($msg); ?></span>
      </div>
    <?php endif; ?>

    <!-- Controls Bar -->
    <div class="tile" style="padding: 20px 24px;">
      <form method="get" class="row align-items-center" style="row-gap: 12px;">
        <div class="col-md-5">
          <input type="text" name="search" class="form-control" placeholder="Search by name, email, phone, or ID..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-3">
          <select name="status" class="form-control" onchange="this.form.submit()">
            <option value="all" <?php echo ($statusFilter === 'all') ? 'selected' : ''; ?>>All Member Statuses</option>
            <option value="active" <?php echo ($statusFilter === 'active') ? 'selected' : ''; ?>>Active Members Only</option>
            <option value="inactive" <?php echo ($statusFilter === 'inactive') ? 'selected' : ''; ?>>Deactivated Members</option>
          </select>
        </div>
        <div class="col-md-4 text-md-right">
          <button type="submit" class="btn btn-primary" style="padding: 8px 16px; border-radius: 12px;">
            <i class="fa fa-search"></i> Search
          </button>
          <a href="members.php?export=csv" class="btn btn-outline-light" style="padding: 8px 16px; border-radius: 12px; margin-left: 6px;">
            <i class="fa fa-download"></i> Export CSV
          </a>
          <a href="member-create.php" class="ios-btn ios-btn-accent" style="padding: 8px 16px; font-size: 13px; border-radius: 12px; margin-left: 6px;">
            <i class="fa fa-plus"></i> New Member
          </a>
        </div>
      </form>
    </div>

    <!-- Members Table -->
    <div class="tile">
      <div class="table-responsive">
        <table class="table table-hover table-bordered" style="margin: 0;">
          <thead>
            <tr>
              <th style="width: 70px;">ID</th>
              <th>Member Name</th>
              <th>Email</th>
              <th>Mobile</th>
              <th>Location</th>
              <th>Status</th>
              <th>Joined Date</th>
              <th style="text-align: right; width: 180px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($members)): foreach ($members as $m): ?>
            <tr>
              <td style="color: var(--text-muted); font-weight: 600;">#<?php echo $m->id; ?></td>
              <td>
                <a href="member-profile.php?id=<?php echo $m->id; ?>" style="color: #fff; font-weight: 700; text-decoration: none;">
                  <?php echo htmlspecialchars($m->fname . ' ' . $m->lname, ENT_QUOTES, 'UTF-8'); ?>
                </a>
              </td>
              <td><?php echo htmlspecialchars($m->email, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($m->mobile, ENT_QUOTES, 'UTF-8'); ?></td>
              <td style="color: var(--text-secondary);"><?php echo htmlspecialchars(($m->city ? $m->city . ', ' : '') . $m->state, ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php if ($m->status === 'active'): ?>
                  <span class="ios-badge ios-badge-success">Active</span>
                <?php else: ?>
                  <span class="ios-badge ios-badge-danger">Inactive</span>
                <?php endif; ?>
              </td>
              <td style="color: var(--text-muted); font-size: 13px;"><?php echo date('d M Y', strtotime($m->create_date)); ?></td>
              <td style="text-align: right;">
                <a href="member-profile.php?id=<?php echo $m->id; ?>" class="btn btn-sm btn-primary" title="View Profile" style="border-radius: 8px; padding: 4px 10px;">
                  <i class="fa fa-id-card-o"></i> View
                </a>
                <a href="members.php?action=toggle&uid=<?php echo $m->id; ?>" class="btn btn-sm <?php echo ($m->status === 'active') ? 'btn-danger' : 'btn-success'; ?>" title="<?php echo ($m->status === 'active') ? 'Deactivate' : 'Activate'; ?>" style="border-radius: 8px; padding: 4px 10px;" onclick="return confirm('Change status for this member?');">
                  <i class="fa <?php echo ($m->status === 'active') ? 'fa-ban' : 'fa-check'; ?>"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">No members match the search criteria.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px;">
        <span style="font-size: 13px; color: var(--text-secondary);">Showing page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $totalRows; ?> members)</span>
        <ul class="pagination" style="margin: 0;">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
              <a class="page-link" href="members.php?p=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>"><?php echo $i; ?></a>
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
