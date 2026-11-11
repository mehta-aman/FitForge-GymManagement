<?php
require_once 'include/config.php';
require_owner(); // Option A: Restricted Manager Financial Access — Booking & Sales reports are Owner-Only

$fdate = trim($_REQUEST['fdate'] ?? date('Y-m-01'));
$todate = trim($_REQUEST['todate'] ?? date('Y-m-d'));
$results = [];

if (isset($_REQUEST['Submit']) || isset($_GET['export'])) {
    $sql = "SELECT t1.id as bookingid, t3.fname, t3.lname, t3.email, t3.mobile, t1.booking_date as bookingdate,
            t2.titlename as title, t2.PackageDuratiobn, t2.Price as price,
            t4.category_name, t5.PackageName
            FROM tblbooking t1
            JOIN tbladdpackage t2 ON t1.package_id = t2.id
            JOIN tbluser t3 ON t1.userid = t3.id
            LEFT JOIN tblcategory t4 ON t2.category = t4.id
            LEFT JOIN tblpackage t5 ON t2.PackageType = t5.id
            WHERE DATE(t1.booking_date) BETWEEN :fdate AND :todate
            ORDER BY t1.id DESC";
    $query = $dbh->prepare($sql);
    $query->execute([':fdate' => $fdate, ':todate' => $todate]);
    $results = $query->fetchAll(PDO::FETCH_OBJ);

    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=sales_report_' . $fdate . '_to_' . $todate . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Booking ID', 'Member Name', 'Email', 'Mobile', 'Package Title', 'Category', 'Package Type', 'Duration', 'Price', 'Booking Date'], ',', '"', "\\");
        foreach ($results as $r) {
            fputcsv($out, [
                $r->bookingid,
                $r->fname . ' ' . $r->lname,
                $r->email,
                $r->mobile,
                $r->title,
                $r->category_name ?? 'N/A',
                $r->PackageName ?? 'N/A',
                $r->PackageDuratiobn,
                $r->price,
                $r->bookingdate
            ], ',', '"', "\\");
        }
        fclose($out);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Bookings Report — Admin</title>
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
        <h1><i class="fa fa-bar-chart" style="color: var(--color-primary); margin-right: 8px;"></i> Bookings & Sales Report</h1>
        <p>Generate financial and reservation reports within specific date ranges</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Booking Report</li>
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
    <?php if (isset($_REQUEST['Submit'])): ?>
      <div class="tile">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
          <h3 style="font-size: 18px; font-weight: 700; margin: 0;">
            Bookings from <?php echo date('d M Y', strtotime($fdate)); ?> to <?php echo date('d M Y', strtotime($todate)); ?>
            <span class="ios-badge ios-badge-info" style="margin-left: 8px;"><?php echo count($results); ?> entries found</span>
          </h3>
          <div style="display: flex; gap: 8px;">
            <a href="report-booking.php?export=csv&fdate=<?php echo urlencode($fdate); ?>&todate=<?php echo urlencode($todate); ?>" class="btn btn-outline-success btn-sm" style="border-radius: 10px; font-weight: 600;">
              <i class="fa fa-file-excel-o"></i> Export CSV
            </a>
            <button type="button" class="btn btn-outline-light btn-sm" onclick="window.print()" style="border-radius: 10px;">
              <i class="fa fa-print"></i> Print Report
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover table-bordered" style="margin: 0;">
            <thead>
              <tr>
                <th style="width: 50px;">#</th>
                <th>Booking ID</th>
                <th>Member Name</th>
                <th>Email</th>
                <th>Package</th>
                <th>Duration</th>
                <th>Fee</th>
                <th>Booking Date</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($results)): $cnt = 1; $totalRevenue = 0; foreach ($results as $r): 
                $totalRevenue += floatval($r->price);
              ?>
              <tr>
                <td style="color: var(--text-muted);"><?php echo $cnt++; ?></td>
                <td style="font-weight: 600; color: var(--color-primary);">#<?php echo $r->bookingid; ?></td>
                <td style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($r->fname . ' ' . $r->lname); ?></td>
                <td><?php echo htmlspecialchars($r->email); ?></td>
                <td><?php echo htmlspecialchars($r->title); ?></td>
                <td><?php echo htmlspecialchars($r->PackageDuratiobn); ?></td>
                <td style="font-weight: 700; color: #34d399;"><?php echo format_currency($r->price); ?></td>
                <td style="color: var(--text-muted); font-size: 13px;"><?php echo date('d M Y', strtotime($r->bookingdate)); ?></td>
              </tr>
              <?php endforeach; ?>
              <tr style="background: rgba(255,255,255,0.05); font-weight: 800;">
                <td colspan="6" style="text-align: right; color: #fff;">Period Total Plan Value:</td>
                <td colspan="2" style="color: #34d399; font-size: 16px;"><?php echo format_currency($totalRevenue); ?></td>
              </tr>
              <?php else: ?>
              <tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">No bookings recorded within this date range.</td></tr>
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
