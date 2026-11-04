<?php
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$fdate = trim($_POST['fdate'] ?? date('Y-m-01'));
$todate = trim($_POST['todate'] ?? date('Y-m-d'));
$results = [];

if (isset($_POST['Submit'])) {
    $sql = "SELECT id, fname, lname, email, mobile, city, state, status, create_date
            FROM tbluser
            WHERE DATE(create_date) BETWEEN :fdate AND :todate
            ORDER BY id DESC";
    $query = $dbh->prepare($sql);
    $query->execute([':fdate' => $fdate, ':todate' => $todate]);
    $results = $query->fetchAll(PDO::FETCH_OBJ);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Registrations Report — Admin</title>
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
        <h1><i class="fa fa-users" style="color: var(--color-primary); margin-right: 8px;"></i> Member Registrations Report</h1>
        <p>Analyze new member sign-ups and acquisition rates across date periods</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Registration Report</li>
      </ul>
    </div>

    <!-- Date Range Filter -->
    <div class="tile">
      <form method="post" class="row align-items-end" style="row-gap: 12px;">
        <?php echo csrf_input(); ?>
        <div class="col-md-4">
          <label style="font-weight: 600; font-size: 13.5px;">From Date</label>
          <input class="form-control" type="date" name="fdate" value="<?php echo htmlspecialchars($fdate); ?>" required style="border-radius: 12px;">
        </div>
        <div class="col-md-4">
          <label style="font-weight: 600; font-size: 13.5px;">To Date</label>
          <input class="form-control" type="date" name="todate" value="<?php echo htmlspecialchars($todate); ?>" required style="border-radius: 12px;">
        </div>
        <div class="col-md-4">
          <button type="submit" name="Submit" class="ios-btn ios-btn-accent btn-block" style="padding: 10px; border-radius: 12px;">
            <i class="fa fa-filter"></i> Generate Report
          </button>
        </div>
      </form>
    </div>

    <!-- Results Table -->
    <?php if (isset($_POST['Submit'])): ?>
      <div class="tile">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
          <h3 style="font-size: 18px; font-weight: 700; margin: 0;">
            Sign-ups from <?php echo date('d M Y', strtotime($fdate)); ?> to <?php echo date('d M Y', strtotime($todate)); ?>
            <span class="ios-badge ios-badge-info" style="margin-left: 8px;"><?php echo count($results); ?> new members</span>
          </h3>
          <button type="button" class="btn btn-outline-light btn-sm" onclick="window.print()" style="border-radius: 10px;">
            <i class="fa fa-print"></i> Print Report
          </button>
        </div>

        <div class="table-responsive">
          <table class="table table-hover table-bordered" style="margin: 0;">
            <thead>
              <tr>
                <th style="width: 50px;">#</th>
                <th>Member ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Mobile</th>
                <th>Location</th>
                <th>Status</th>
                <th>Joined Date</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($results)): $cnt = 1; foreach ($results as $r): ?>
              <tr>
                <td style="color: var(--text-muted);"><?php echo $cnt++; ?></td>
                <td style="font-weight: 600; color: var(--color-primary);">#<?php echo $r->id; ?></td>
                <td style="font-weight: 600; color: #fff;">
                  <a href="member-profile.php?id=<?php echo $r->id; ?>" style="color: #fff; text-decoration: none;">
                    <?php echo htmlspecialchars($r->fname . ' ' . $r->lname); ?>
                  </a>
                </td>
                <td><?php echo htmlspecialchars($r->email); ?></td>
                <td><?php echo htmlspecialchars($r->mobile); ?></td>
                <td><?php echo htmlspecialchars(($r->city ? $r->city . ', ' : '') . $r->state); ?></td>
                <td>
                  <?php if (($r->status ?? 'active') === 'active'): ?>
                    <span class="ios-badge ios-badge-success">Active</span>
                  <?php else: ?>
                    <span class="ios-badge ios-badge-danger">Inactive</span>
                  <?php endif; ?>
                </td>
                <td style="color: var(--text-muted); font-size: 13px;"><?php echo date('d M Y, h:i A', strtotime($r->create_date)); ?></td>
              </tr>
              <?php endforeach; else: ?>
              <tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">No registrations recorded within this date range.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </main>

  <script src="js/jquery-3.2.1.min.js"></script>
  <script src="js/popper.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/main.js"></script>
</body>
</html>
