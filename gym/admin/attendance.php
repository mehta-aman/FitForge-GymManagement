<?php
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$msg = '';
$error = '';
$filterDate = trim($_GET['date'] ?? date('Y-m-d'));
$search = trim($_GET['search'] ?? '');

// 1. Process Manual or QR Check-In
if (isset($_POST['action']) && $_POST['action'] === 'checkin') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security token mismatch. Please try again.';
    } else {
        $memberQuery = trim($_POST['member_query'] ?? '');
        $checkMethod = trim($_POST['method'] ?? 'manual');
        
        // Find member by ID, mobile, or email
        $findUser = $dbh->prepare("SELECT id, fname, lname, mobile, email, status FROM tbluser WHERE id = :id OR mobile = :mobile OR email = :email LIMIT 1");
        $findUser->execute([
            ':id' => intval($memberQuery),
            ':mobile' => $memberQuery,
            ':email' => $memberQuery
        ]);
        $user = $findUser->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = "Member not found with query: " . htmlspecialchars($memberQuery);
        } else {
            $userId = $user['id'];
            
            // Check if user is already checked in today without checking out
            $checkExisting = $dbh->prepare("SELECT id FROM tbl_attendance WHERE user_id = :uid AND DATE(check_in_time) = CURDATE() AND check_out_time IS NULL ORDER BY id DESC LIMIT 1");
            $checkExisting->execute([':uid' => $userId]);
            $existing = $checkExisting->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $error = "Member " . htmlspecialchars($user['fname'] . ' ' . $user['lname']) . " is ALREADY checked in right now!";
            } else {
                // Check membership status & dues for alert message
                $memStmt = $dbh->prepare("SELECT status, expiry_date FROM tbl_memberships WHERE user_id = :uid ORDER BY id DESC LIMIT 1");
                $memStmt->execute([':uid' => $userId]);
                $mem = $memStmt->fetch(PDO::FETCH_ASSOC);

                $dueStmt = $dbh->prepare("
                    SELECT SUM(GREATEST(0, (CAST(p.Price AS DECIMAL(10,2)) + CAST(COALESCE(p.registration_fee,0) AS DECIMAL(10,2)) - CAST(COALESCE(p.discount,0) AS DECIMAL(10,2))) - (SELECT COALESCE(SUM(pay.payment), 0) FROM tblpayment pay WHERE pay.bookingID = b.id))) as dues
                    FROM tblbooking b
                    JOIN tbladdpackage p ON b.package_id = p.id
                    WHERE b.userid = :uid
                ");
                $dueStmt->execute([':uid' => $userId]);
                $dueAmt = floatval($dueStmt->fetchColumn());

                // Record Check-in
                $gymId = get_current_gym_id();
                $insert = $dbh->prepare("INSERT INTO tbl_attendance (user_id, check_in_time, check_in_method, gym_id) VALUES (:uid, NOW(), :method, :gym_id)");
                $insert->execute([':uid' => $userId, ':method' => $checkMethod, ':gym_id' => $gymId]);

                $msg = "Checked in " . htmlspecialchars($user['fname'] . ' ' . $user['lname']) . " successfully!";
                if ($dueAmt > 0) {
                    $msg .= " ⚠️ NOTE: Member has ₹" . number_format($dueAmt, 2) . " outstanding dues!";
                }
                if ($mem && $mem['status'] !== 'active') {
                    $msg .= " ⚠️ Pass Status: " . strtoupper($mem['status']) . "!";
                }
            }
        }
    }
}

// 2. Process Check-Out
if (isset($_POST['action']) && $_POST['action'] === 'checkout') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $attId = intval($_POST['attendance_id'] ?? 0);
        $checkoutStmt = $dbh->prepare("UPDATE tbl_attendance SET check_out_time = NOW() WHERE id = :id AND check_out_time IS NULL");
        $checkoutStmt->execute([':id' => $attId]);
        $msg = "Member checked out successfully.";
    }
}

// 3. Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $expSql = "SELECT a.id, a.user_id, u.fname, u.lname, u.mobile, u.email, a.check_in_time, a.check_out_time, a.check_in_method
               FROM tbl_attendance a
               JOIN tbluser u ON a.user_id = u.id
               WHERE DATE(a.check_in_time) = :fdate
               ORDER BY a.check_in_time DESC";
    $expStmt = $dbh->prepare($expSql);
    $expStmt->execute([':fdate' => $filterDate]);
    $expRows = $expStmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=attendance_' . $filterDate . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Record ID', 'Member ID', 'Member Name', 'Mobile', 'Email', 'Check In Time', 'Check Out Time', 'Method'], ',', '"', "\\");
    foreach ($expRows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['user_id'],
            $r['fname'] . ' ' . $r['lname'],
            $r['mobile'],
            $r['email'],
            $r['check_in_time'],
            $r['check_out_time'] ?? 'In Gym',
            $r['check_in_method']
        ], ',', '"', "\\");
    }
    fclose($out);
    exit();
}

// 4. Counts for Today
$activeFloorCount = intval($dbh->query("SELECT count(*) FROM tbl_attendance WHERE DATE(check_in_time) = CURDATE() AND check_out_time IS NULL")->fetchColumn());
$totalTodayCount = intval($dbh->query("SELECT count(*) FROM tbl_attendance WHERE DATE(check_in_time) = CURDATE()")->fetchColumn());
$totalDistinctMembers = intval($dbh->query("SELECT count(DISTINCT user_id) FROM tbl_attendance WHERE DATE(check_in_time) = CURDATE()")->fetchColumn());

// 5. Fetch Attendance Log for Selected Date
$logParams = [':fdate' => $filterDate];
$whereLog = ["DATE(a.check_in_time) = :fdate"];
if (!empty($search)) {
    $whereLog[] = "(u.fname LIKE :s OR u.lname LIKE :s OR u.mobile LIKE :s OR u.id = :sid)";
    $logParams[':s'] = "%$search%";
    $logParams[':sid'] = intval($search);
}
$whereLogSql = implode(' AND ', $whereLog);

$logSql = "SELECT a.id as attendance_id, a.check_in_time, a.check_out_time, a.check_in_method,
           u.id as user_id, u.fname, u.lname, u.mobile, u.email,
           (SELECT m.status FROM tbl_memberships m WHERE m.user_id = u.id ORDER BY m.id DESC LIMIT 1) as membership_status,
           (SELECT m.expiry_date FROM tbl_memberships m WHERE m.user_id = u.id ORDER BY m.id DESC LIMIT 1) as expiry_date
           FROM tbl_attendance a
           JOIN tbluser u ON a.user_id = u.id
           WHERE $whereLogSql
           ORDER BY a.check_in_time DESC";
$logStmt = $dbh->prepare($logSql);
$logStmt->execute($logParams);
$attendanceLogs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Attendance & Floor Check-in — FitForge Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php echo render_favicon_tags(); ?>
  <link rel="stylesheet" type="text/css" href="css/main.css">
  <link rel="stylesheet" type="text/css" href="css/ios-admin-glass.css">
  <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
  <!-- HTML5 QR Scanner -->
  <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
  <style>
    .kpi-metric-strip {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }
    .checkin-hero-card {
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(25px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 20px;
      padding: 24px;
      margin-bottom: 24px;
    }
    #qr-reader {
      max-width: 400px;
      margin: 16px auto;
      border-radius: 16px;
      overflow: hidden;
      border: 2px dashed rgba(255, 255, 255, 0.2);
    }
    .status-pulse {
      display: inline-block;
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: #10b981;
      box-shadow: 0 0 0 rgba(16, 185, 129, 0.7);
      animation: pulse 1.6s infinite;
      margin-right: 6px;
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
        <h1><i class="fa fa-qrcode" style="color: var(--color-primary); margin-right: 8px;"></i> Reception Attendance &amp; Check-in</h1>
        <p>Real-time gym floor headcount, QR code entrance scanner, and member verification ledger</p>
      </div>
      <div style="display: flex; gap: 10px; align-items: center;">
        <a href="gym-qr.php" class="btn btn-primary" style="font-weight: 600; border-radius: 10px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);">
          <i class="fa fa-print"></i> Printable Gym QR Poster
        </a>
        <ul class="app-breadcrumb breadcrumb" style="margin-bottom: 0;">
          <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
          <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Attendance</li>
        </ul>
      </div>
    </div>

    <!-- Feedback Alerts -->
    <?php if (!empty($msg)): ?>
      <div class="alert alert-success" style="border-radius: 12px; background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #fff;">
        <i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger" style="border-radius: 12px; background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fff;">
        <i class="fa fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <!-- KPI Metric Cards -->
    <div class="kpi-metric-strip">
      <div class="widget-small primary coloured-icon" style="border-radius: 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
        <i class="icon fa fa-male fa-3x" style="background: rgba(16,185,129,0.2); color: #10b981;"></i>
        <div class="info">
          <h4 style="color: #cbd5e1; font-size: 13px;"><span class="status-pulse"></span> Currently on Floor</h4>
          <p style="font-size: 22px; font-weight: 800; color: #10b981;"><?php echo $activeFloorCount; ?> <span style="font-size: 13px; font-weight: 500; color: #94a3b8;">members</span></p>
        </div>
      </div>

      <div class="widget-small info coloured-icon" style="border-radius: 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
        <i class="icon fa fa-calendar-check-o fa-3x" style="background: rgba(59,130,246,0.2); color: #3b82f6;"></i>
        <div class="info">
          <h4 style="color: #cbd5e1; font-size: 13px;">Today's Check-ins</h4>
          <p style="font-size: 22px; font-weight: 800; color: #fff;"><?php echo $totalTodayCount; ?> <span style="font-size: 13px; font-weight: 500; color: #94a3b8;">total</span></p>
        </div>
      </div>

      <div class="widget-small warning coloured-icon" style="border-radius: 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
        <i class="icon fa fa-users fa-3x" style="background: rgba(245,158,11,0.2); color: #f59e0b;"></i>
        <div class="info">
          <h4 style="color: #cbd5e1; font-size: 13px;">Unique Athletes Today</h4>
          <p style="font-size: 22px; font-weight: 800; color: #f59e0b;"><?php echo $totalDistinctMembers; ?> <span style="font-size: 13px; font-weight: 500; color: #94a3b8;">athletes</span></p>
        </div>
      </div>
    </div>

    <!-- Reception Check-In Action Card -->
    <div class="checkin-hero-card">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
        <div>
          <h3 style="font-size: 18px; font-weight: 700; margin: 0; color: #fff;">
            <i class="fa fa-id-card-o" style="color: var(--color-primary); margin-right: 6px;"></i> Fast Entrance Check-in
          </h3>
          <p style="font-size: 13px; color: var(--text-muted); margin: 4px 0 0;">Scan member QR code or enter Member ID / Phone number to record entrance</p>
        </div>
        <button type="button" id="btn-toggle-scanner" class="btn btn-outline-info btn-sm" style="border-radius: 10px; font-weight: 600;">
          <i class="fa fa-camera"></i> Toggle Camera Scanner
        </button>
      </div>

      <!-- QR Scanner Window (Hidden by default) -->
      <div id="qr-scanner-container" style="display: none; text-align: center;">
        <div id="qr-reader"></div>
        <p style="font-size: 12px; color: var(--text-muted); margin-top: 6px;">Hold member's digital QR pass in front of camera</p>
      </div>

      <!-- Input Form -->
      <form method="post" id="checkin-form" class="row align-items-center" style="row-gap: 12px;">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="checkin">
        <input type="hidden" name="method" id="checkin-method" value="manual">

        <div class="col-md-9">
          <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text" style="background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.1); color: #fff;"><i class="fa fa-search"></i></span>
            </div>
            <input type="text" name="member_query" id="member_query" class="form-control" placeholder="Scan QR or enter Member ID, Phone, or Email..." required autofocus style="border-radius: 0 12px 12px 0; font-size: 15px; font-weight: 600;">
          </div>
        </div>
        <div class="col-md-3">
          <button type="submit" class="ios-btn ios-btn-accent btn-block" style="padding: 10px; border-radius: 12px;">
            <i class="fa fa-sign-in"></i> Check In Member
          </button>
        </div>
      </form>
    </div>

    <!-- Attendance Log Table -->
    <div class="tile">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
        <div>
          <h3 style="font-size: 18px; font-weight: 700; margin: 0; color: #fff;">
            Attendance Roster for <?php echo date('d M Y', strtotime($filterDate)); ?>
            <span class="ios-badge ios-badge-info" style="margin-left: 8px;"><?php echo count($attendanceLogs); ?> visits</span>
          </h3>
        </div>
        <div style="display: flex; gap: 8px;">
          <a href="attendance.php?export=csv&date=<?php echo urlencode($filterDate); ?>" class="btn btn-outline-success btn-sm" style="border-radius: 10px; font-weight: 600;">
            <i class="fa fa-file-excel-o"></i> Export CSV
          </a>
          <button type="button" class="btn btn-outline-light btn-sm" onclick="window.print()" style="border-radius: 10px;">
            <i class="fa fa-print"></i> Print
          </button>
        </div>
      </div>

      <!-- Date Filter Bar -->
      <form method="get" class="row align-items-center mb-3" style="row-gap: 10px;">
        <div class="col-md-4">
          <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($filterDate); ?>" style="border-radius: 12px;">
        </div>
        <div class="col-md-6">
          <input type="text" name="search" class="form-control" placeholder="Search by name, phone, or ID in today's roster..." value="<?php echo htmlspecialchars($search); ?>" style="border-radius: 12px;">
        </div>
        <div class="col-md-2 d-flex" style="gap: 6px;">
          <button type="submit" class="ios-btn ios-btn-accent flex-grow-1" style="padding: 8px; border-radius: 12px;">
            <i class="fa fa-filter"></i> Apply
          </button>
          <a href="attendance.php" class="btn btn-outline-secondary" style="border-radius: 12px; padding: 8px 12px;">
            <i class="fa fa-refresh"></i>
          </a>
        </div>
      </form>

      <!-- Logs -->
      <div class="table-responsive">
        <table class="table table-hover table-bordered" style="margin: 0;">
          <thead>
            <tr>
              <th style="width: 50px;">#</th>
              <th>Member Name &amp; ID</th>
              <th>Contact Phone</th>
              <th>Pass Status</th>
              <th>Check-in Time</th>
              <th>Check-out Time</th>
              <th>Method</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($attendanceLogs)): $cnt = 1; foreach ($attendanceLogs as $row): 
              $isOnFloor = empty($row['check_out_time']);
              $status = $row['membership_status'] ?? 'expired';
            ?>
            <tr>
              <td style="color: var(--text-muted);"><?php echo $cnt++; ?></td>
              <td>
                <div style="font-weight: 700; color: #fff;">
                  <a href="member-profile.php?id=<?php echo $row['user_id']; ?>" style="color: inherit; text-decoration: none;">
                    <?php echo htmlspecialchars($row['fname'] . ' ' . $row['lname']); ?>
                  </a>
                </div>
                <div style="font-size: 11.5px; color: var(--text-muted);">ID: #MEM-<?php echo str_pad($row['user_id'], 4, '0', STR_PAD_LEFT); ?></div>
              </td>
              <td style="color: #cbd5e1; font-weight: 600;"><?php echo htmlspecialchars($row['mobile']); ?></td>
              <td>
                <span class="ios-badge <?php echo ($status === 'active') ? 'ios-badge-success' : (($status === 'frozen') ? 'ios-badge-warning' : 'ios-badge-danger'); ?>" style="font-size: 11px;">
                  <?php echo strtoupper($status); ?>
                </span>
              </td>
              <td style="font-weight: 600; color: #34d399; font-size: 13.5px;">
                <i class="fa fa-clock-o"></i> <?php echo date('h:i A', strtotime($row['check_in_time'])); ?>
              </td>
              <td>
                <?php if ($isOnFloor): ?>
                  <span class="ios-badge ios-badge-success" style="font-size: 11.5px;"><span class="status-pulse"></span> On Floor</span>
                <?php else: ?>
                  <span style="color: var(--text-muted); font-size: 13px;"><i class="fa fa-sign-out"></i> <?php echo date('h:i A', strtotime($row['check_out_time'])); ?></span>
                <?php endif; ?>
              </td>
              <td>
                <span class="ios-badge ios-badge-info" style="font-size: 10.5px; text-transform: uppercase;">
                  <?php echo htmlspecialchars($row['check_in_method']); ?>
                </span>
              </td>
              <td style="text-align: right;">
                <?php if ($isOnFloor): ?>
                  <form method="post" style="display: inline;">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="checkout">
                    <input type="hidden" name="attendance_id" value="<?php echo $row['attendance_id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-warning" style="border-radius: 8px; font-weight: 600;" title="Check out member">
                      <i class="fa fa-sign-out"></i> Check Out
                    </button>
                  </form>
                <?php else: ?>
                  <span style="color: var(--text-muted); font-size: 12px;"><i class="fa fa-check"></i> Completed</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr>
              <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 40px;">
                <i class="fa fa-calendar-times-o" style="font-size: 36px; color: var(--text-muted); margin-bottom: 10px; display: block;"></i>
                No attendance visits recorded for this date.
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
  <script>
    // Camera QR Code Scanner Support
    let html5QrcodeScanner = null;
    let scannerActive = false;

    $('#btn-toggle-scanner').on('click', function() {
      if (!scannerActive) {
        $('#qr-scanner-container').slideDown();
        scannerActive = true;
        if (!html5QrcodeScanner) {
          html5QrcodeScanner = new Html5QrcodeScanner("qr-reader", { fps: 10, qrbox: { width: 250, height: 250 } });
          html5QrcodeScanner.render((decodedText) => {
            // Success QR Scan
            $('#member_query').val(decodedText);
            $('#checkin-method').val('qr_scan');
            $('#checkin-form').submit();
          });
        }
      } else {
        $('#qr-scanner-container').slideUp();
        scannerActive = false;
      }
    });
  </script>
</body>
</html>
