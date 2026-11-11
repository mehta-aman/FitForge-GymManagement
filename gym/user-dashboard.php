<?php
require_once 'include/config.php';
require_once 'include/lost_found_helper.php';
if (empty($_SESSION['uid'])) {
    header('location:login.php');
    exit();
}

$userId = intval($_SESSION['uid']);

// 1. Fetch Member Info
$userStmt = $dbh->prepare("SELECT * FROM tbluser WHERE id = :id");
$userStmt->execute([':id' => $userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('location:logout.php');
    exit();
}

// 2. Fetch Current Active/Frozen Pass (Strictly excludes future scheduled passes)
$membership = find_current_membership($userId, $dbh);
$scheduledMembership = find_scheduled_membership($userId, $dbh);

// 3. Fetch Outstanding Dues
$dues = 0.0;
if ($membership) {
    $netPayable = calculate_net_payable(
        floatval($membership['Price'] ?? $membership['total_amount']),
        floatval($membership['registration_fee'] ?? 0),
        floatval($membership['discount'] ?? 0)
    );
    $netPaid = get_membership_net_paid(intval($membership['id']), $dbh);
    $dues = max(0.0, $netPayable - $netPaid);
}

// 4. Fetch Attendance Stats
$totalVisits = intval($dbh->query("SELECT count(*) FROM tbl_attendance WHERE user_id = $userId")->fetchColumn());
$monthVisits = intval($dbh->query("SELECT count(*) FROM tbl_attendance WHERE user_id = $userId AND MONTH(check_in_time) = MONTH(CURDATE()) AND YEAR(check_in_time) = YEAR(CURDATE())")->fetchColumn());
$lastVisit = $dbh->query("SELECT check_in_time FROM tbl_attendance WHERE user_id = $userId ORDER BY check_in_time DESC LIMIT 1")->fetchColumn();

// 5. Fetch Assigned Trainer
$trainerStmt = $dbh->prepare("
    SELECT t.*, mt.assigned_date, mt.notes as coaching_notes
    FROM tbl_member_trainers mt
    JOIN tbl_trainers t ON mt.trainer_id = t.id
    WHERE mt.user_id = :uid AND mt.status = 'active'
    ORDER BY mt.id DESC LIMIT 1
");
$trainerStmt->execute([':uid' => $userId]);
$trainer = $trainerStmt->fetch(PDO::FETCH_ASSOC);

// 6. Fetch Workout Routine
$workoutsStmt = $dbh->prepare("SELECT * FROM tbl_workout_plans WHERE user_id = :uid ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), id ASC");
$workoutsStmt->execute([':uid' => $userId]);
$workouts = $workoutsStmt->fetchAll(PDO::FETCH_ASSOC);

// Group workouts by day
$workoutDays = [];
foreach ($workouts as $w) {
    $workoutDays[$w['day_of_week']][] = $w;
}

// 7. Today's Exercise Schedule (Strictly Today's Day Only)
$todayDay = date('l'); // e.g. Monday, Tuesday, etc.
$todayExercises = $workoutDays[$todayDay] ?? [];
$todayTargetMuscle = !empty($todayExercises) ? ($todayExercises[0]['target_muscle'] ?? 'General Training') : null;

// 8. Fetch Member Bookings for Welcome Screen
$bookingsStmt = $dbh->prepare("
    SELECT t1.id as bookingid, t1.booking_date, t2.titlename as title, t2.PackageDuratiobn, t2.Price, t2.Description, t4.category_name, t5.PackageName
    FROM tblbooking as t1
    JOIN tbladdpackage as t2 ON t1.package_id = t2.id
    LEFT JOIN tblcategory as t4 ON t2.category = t4.id
    LEFT JOIN tblpackage as t5 ON t2.PackageType = t5.id
    WHERE t1.userid = :uid
    ORDER BY t1.id DESC
    LIMIT 5
");
$bookingsStmt->execute([':uid' => $userId]);
$userBookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC);

// 9. Fetch Member's Lost & Found Posts
$userLostFoundStmt = $dbh->prepare("SELECT * FROM tbl_lost_found WHERE author_type = 'user' AND author_id = :uid ORDER BY created_at DESC LIMIT 3");
$userLostFoundStmt->execute([':uid' => $userId]);
$userLostFound = $userLostFoundStmt->fetchAll(PDO::FETCH_ASSOC);

$gymName = get_setting('gym_name', 'Golden Eagle Gym');
$gymTagline = get_setting('gym_tagline', 'FITNESS CLUB');
$memberCode = "MEM-" . str_pad($userId, 4, '0', STR_PAD_LEFT);
$qrPayload = $memberCode;
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=" . urlencode($qrPayload);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Home — <?php echo htmlspecialchars($gymName); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php echo render_favicon_tags(); ?>
  <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
  <link rel="stylesheet" type="text/css" href="css/font-awesome.min.css">
  <link rel="stylesheet" type="text/css" href="css/ios-glass.css">
  <style>
    body {
      background: radial-gradient(circle at 15% 15%, #0b0f19 0%, #060911 55%, #020408 100%);
      color: #f8fafc;
      min-height: 100vh;
      font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif;
    }
    .member-card-wrapper {
      max-width: 440px;
      margin: 0 auto 30px;
      perspective: 1000px;
    }
    .digital-pass {
      background: linear-gradient(145deg, rgba(15, 23, 42, 0.78) 0%, rgba(11, 15, 25, 0.9) 100%);
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 22px;
      padding: 26px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.45), inset 0 1px 1px rgba(255, 255, 255, 0.08);
      position: relative;
      overflow: hidden;
    }
    .digital-pass::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(255, 45, 85, 0.08) 0%, transparent 60%);
      pointer-events: none;
    }
    .pass-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    .pass-qr-box {
      background: #ffffff;
      padding: 10px;
      border-radius: 16px;
      width: 150px;
      height: 150px;
      margin: 16px auto;
      box-shadow: 0 10px 25px rgba(0,0,0,0.4);
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .pass-qr-box img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }
    .kpi-tile {
      background: rgba(15, 23, 42, 0.65);
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 18px;
      padding: 16px 14px;
      margin-bottom: 12px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.45);
      transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
      height: calc(100% - 12px);
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    .kpi-tile:hover {
      transform: translateY(-2px);
      border-color: rgba(255, 255, 255, 0.18);
      box-shadow: 0 14px 30px rgba(0, 0, 0, 0.55);
    }
    @media (max-width: 575.98px) {
      .kpi-tile {
        padding: 12px 10px;
        border-radius: 14px;
      }
      .digital-pass {
        padding: 20px 16px;
        border-radius: 20px;
      }
    }
    .coach-card {
      background: rgba(15, 23, 42, 0.65);
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 18px;
      padding: 22px;
      display: flex;
      align-items: center;
      gap: 16px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.45);
      transition: transform 0.2s ease, border-color 0.2s ease;
    }
    .coach-card:hover {
      transform: translateY(-2px);
      border-color: rgba(255, 255, 255, 0.18);
    }
    .coach-avatar {
      width: 65px;
      height: 65px;
      border-radius: 16px;
      background: linear-gradient(135deg, var(--color-primary), var(--color-accent));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 26px;
      font-weight: 700;
      color: #fff;
    }
    .workout-day-card {
      background: rgba(15, 23, 42, 0.65);
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 18px;
      padding: 18px 20px;
      margin-bottom: 12px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.45);
      transition: transform 0.2s ease, border-color 0.2s ease;
    }
    .workout-day-card:hover {
      border-color: rgba(255, 255, 255, 0.18);
    }
    .dashboard-main-container {
      max-width: 1240px;
      margin: 0 auto;
      padding-top: 100px;
      padding-bottom: 80px;
    }
    .dashboard-grid-layout {
      display: flex;
      flex-direction: column;
      gap: 0;
    }
    @media (min-width: 992px) {
      .dashboard-grid-layout {
        display: grid;
        grid-template-columns: 400px minmax(0, 1fr);
        grid-template-rows: auto auto auto auto auto;
        grid-template-areas:
          "pass stats"
          "pass routine"
          "pass bookings"
          "pass coach"
          "pass lostfound";
        column-gap: 32px;
        row-gap: 24px;
        align-items: start;
      }
      .grid-area-pass {
        grid-area: pass;
        align-self: start;
        position: sticky;
        top: 90px;
        margin-bottom: 0 !important;
      }
      .grid-area-stats {
        grid-area: stats;
        margin-bottom: 0 !important;
      }
      .grid-area-routine {
        grid-area: routine;
        margin-bottom: 0 !important;
      }
      .grid-area-bookings {
        grid-area: bookings;
        margin-bottom: 0 !important;
      }
      .grid-area-coach {
        grid-area: coach;
        margin-bottom: 0 !important;
      }
      .grid-area-lostfound {
        grid-area: lostfound;
        margin-bottom: 0 !important;
      }
    }
    @media (max-width: 991.98px) {
      .dashboard-main-container {
        max-width: 760px;
        padding-top: 90px;
        padding-bottom: 60px;
      }
      .grid-area-stats {
        margin-bottom: 12px;
      }
      .grid-area-routine {
        margin-bottom: 28px;
      }
      .grid-area-pass {
        margin-bottom: 28px;
      }
      .grid-area-bookings {
        margin-bottom: 28px;
      }
      .grid-area-coach {
        margin-bottom: 28px;
      }
      .grid-area-lostfound {
        margin-bottom: 28px;
      }
    }
  </style>
</head>
<body>
  <?php include 'include/header.php'; ?>

  <div class="container dashboard-main-container">
    <!-- Member Welcome Header -->
    <div style="margin-bottom: 24px;">
      <h1 style="font-size: 30px; font-weight: 800; margin: 0 0 6px; color: #fff;">
        Welcome, <?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?>!
      </h1>
      <p style="color: #94a3b8; font-size: 14px; margin: 0;">
        Member ID: <span style="color: #fff; font-weight: 700; font-family: monospace;"><?php echo $memberCode; ?></span> &bull; 
        Today is <span style="color: #38bdf8; font-weight: 600;"><?php echo date('l, d F Y'); ?></span>
      </p>
    </div>

    <!-- Outstanding Due Warning Banner -->
    <?php if ($dues > 0): ?>
      <div class="alert" style="background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; border-radius: 16px; color: #fff; padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
          <i class="fa fa-exclamation-circle" style="font-size: 26px; color: #ef4444;"></i>
          <div>
            <div style="font-weight: 700; font-size: 15px;">Pending Membership Balance: <?php echo format_currency($dues); ?></div>
            <div style="font-size: 13px; color: #fca5a5;">Please clear your outstanding balance at reception to avoid pass suspension.</div>
          </div>
        </div>
        <a href="Booking-History.php" class="btn btn-sm btn-light" style="border-radius: 10px; font-weight: 700;">
          View Invoice
        </a>
      </div>
    <?php endif; ?>

    <div class="dashboard-grid-layout">
      <!-- 4 Quick Stats (2 Cards per line on phone: col-6 col-md-3) -->
      <div class="grid-area-stats">
        <div class="row" style="margin-left: -6px; margin-right: -6px; margin-bottom: 16px;">
          <div class="col-6 col-md-3" style="padding-left: 6px; padding-right: 6px;">
            <div class="kpi-tile">
              <div style="font-size: 10.5px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em;">Visits This Month</div>
              <div style="font-size: 22px; font-weight: 800; color: #38bdf8; margin-top: 4px;"><?php echo $monthVisits; ?></div>
              <div style="font-size: 10.5px; color: #94a3b8;">Total: <?php echo $totalVisits; ?></div>
            </div>
          </div>

          <div class="col-6 col-md-3" style="padding-left: 6px; padding-right: 6px;">
            <div class="kpi-tile">
              <div style="font-size: 10.5px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em;">Pass Days Left</div>
              <div style="font-size: 22px; font-weight: 800; color: #34d399; margin-top: 4px;">
                <?php echo max(0, intval($membership['days_left'] ?? 0)); ?>
              </div>
              <div style="font-size: 10.5px; color: #94a3b8;">Active pass</div>
            </div>
          </div>

          <div class="col-6 col-md-3" style="padding-left: 6px; padding-right: 6px;">
            <div class="kpi-tile">
              <div style="font-size: 10.5px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em;">Today's Target</div>
              <div style="font-size: 14.5px; font-weight: 800; color: #f59e0b; margin-top: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($todayTargetMuscle ?? 'Rest Day'); ?>">
                <?php echo htmlspecialchars($todayTargetMuscle ?? 'Rest Day'); ?>
              </div>
              <div style="font-size: 10.5px; color: #94a3b8;"><?php echo $todayDay; ?></div>
            </div>
          </div>

          <div class="col-6 col-md-3" style="padding-left: 6px; padding-right: 6px;">
            <div class="kpi-tile">
              <div style="font-size: 10.5px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em;">Last Check-in</div>
              <div style="font-size: 16px; font-weight: 800; color: #fff; margin-top: 6px;">
                <?php echo !empty($lastVisit) ? date('d M', strtotime($lastVisit)) : 'None'; ?>
              </div>
              <div style="font-size: 10.5px; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <?php echo !empty($lastVisit) ? date('h:i A', strtotime($lastVisit)) : 'No check-ins yet'; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Today's Exercise Routine (Strictly Today's Schedule Only) -->
      <div class="grid-area-routine" style="margin-bottom: 28px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
          <div>
            <h4 style="font-size: 17px; font-weight: 700; margin: 0; color: #fff;">
              <i class="fa fa-calendar-check-o" style="color: var(--color-primary); margin-right: 6px;"></i> Today's Routine (<?php echo $todayDay; ?>)
            </h4>
            <span style="font-size: 12px; color: #94a3b8;">Focused exercise target for today</span>
          </div>
          <a href="profile.php#routine" class="btn btn-sm btn-outline-info" style="border-radius: 8px; font-size: 12px;">
            <i class="fa fa-calendar"></i> View Full Weekly Split
          </a>
        </div>

        <?php if (!empty($todayExercises)): ?>
          <div class="workout-day-card" style="border-color: rgba(56, 189, 248, 0.35);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
              <span style="font-weight: 800; color: #fff; font-size: 15px;">
                <i class="fa fa-bolt" style="color: #38bdf8; margin-right: 6px;"></i> <?php echo $todayDay; ?>'s Session
              </span>
              <span class="ios-badge ios-badge-info" style="font-size: 11px; padding: 4px 10px;">
                <?php echo htmlspecialchars($todayTargetMuscle); ?>
              </span>
            </div>
            <div style="display: flex; flex-direction: column; gap: 8px;">
              <?php foreach ($todayExercises as $ex): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 13.5px; color: #f1f5f9; border-bottom: 1px solid rgba(255,255,255,0.04); padding-bottom: 6px;">
                  <div>
                    <strong>&bull; <?php echo htmlspecialchars($ex['exercise_name']); ?></strong>
                    <?php if (!empty($ex['notes'])): ?>
                      <div style="font-size: 11.5px; color: #94a3b8; margin-left: 12px;"><?php echo htmlspecialchars($ex['notes']); ?></div>
                    <?php endif; ?>
                  </div>
                  <span style="color: #38bdf8; font-family: monospace; font-weight: 700; background: rgba(56, 189, 248, 0.12); padding: 3px 10px; border-radius: 6px; font-size: 12px; white-space: nowrap;">
                    <?php echo $ex['sets']; ?> sets &times; <?php echo htmlspecialchars($ex['reps']); ?>
                  </span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php else: ?>
          <div class="workout-day-card" style="text-align: center; padding: 26px 20px; border-style: dashed;">
            <div style="width: 44px; height: 44px; border-radius: 50%; background: rgba(52, 211, 153, 0.15); color: #34d399; display: flex; align-items: center; justify-content: center; font-size: 20px; margin: 0 auto 10px;">
              <i class="fa fa-bed"></i>
            </div>
            <h5 style="color: #fff; font-size: 15px; font-weight: 700; margin: 0 0 4px 0;">Scheduled Rest &amp; Recovery Day</h5>
            <p style="color: #94a3b8; font-size: 12.5px; max-width: 460px; margin: 0 auto 12px;">
              No workout exercises are scheduled for <?php echo $todayDay; ?>. Hydrate well, stretch, and let muscles recover.
            </p>
            <a href="profile.php#routine" class="btn btn-sm btn-outline-light" style="border-radius: 8px; font-size: 12px;">
              <i class="fa fa-calendar"></i> View Full Weekly Split
            </a>
          </div>
        <?php endif; ?>
      </div>

      <!-- Golden Eagle Active Membership Pass & QR Check-in (Moved to the bottom of Today's Routine) -->
      <div class="grid-area-pass" style="margin-bottom: 28px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
          <h4 style="font-size: 17px; font-weight: 700; margin: 0; color: #fff;">
            <i class="fa fa-qrcode" style="color: var(--color-primary); margin-right: 6px;"></i> Active Membership Pass &amp; Check-in
          </h4>
          <span style="font-size: 12px; color: #94a3b8;">Present QR at gym reception camera</span>
        </div>

        <div class="member-card-wrapper">
          <div class="digital-pass">
            <div class="pass-header">
              <div>
                <div style="font-size: 16px; font-weight: 800; color: #fff; text-transform: uppercase;"><?php echo htmlspecialchars($gymName); ?></div>
                <div style="font-size: 10px; color: var(--color-primary); font-weight: 700; letter-spacing: 0.05em;"><?php echo htmlspecialchars($gymTagline); ?></div>
              </div>
              <div>
                <?php if ($membership && $membership['status'] === 'active'): ?>
                  <span class="ios-badge ios-badge-success" style="font-size: 12px; padding: 6px 12px;"><i class="fa fa-check-circle"></i> ACTIVE PASS</span>
                <?php elseif ($membership && $membership['status'] === 'frozen'): ?>
                  <span class="ios-badge ios-badge-warning" style="font-size: 12px; padding: 6px 12px;"><i class="fa fa-pause-circle"></i> FROZEN</span>
                <?php else: ?>
                  <span class="ios-badge ios-badge-danger" style="font-size: 12px; padding: 6px 12px;"><i class="fa fa-times-circle"></i> EXPIRED</span>
                <?php endif; ?>
              </div>
            </div>

            <!-- QR Code Box -->
            <div class="pass-qr-box">
              <img src="<?php echo htmlspecialchars($qrUrl); ?>" alt="Member Check-in QR Code">
            </div>

            <div style="text-align: center; margin-bottom: 16px;">
              <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.06em;">Digital Check-in Token</div>
              <div style="font-size: 18px; font-weight: 800; color: #fff; font-family: monospace; letter-spacing: 0.1em;"><?php echo $memberCode; ?></div>
              <div style="font-size: 12px; color: #94a3b8; margin-top: 2px;">Hold this QR code up at the reception camera to check in</div>
            </div>

            <!-- In-App Camera Scanner CTA -->
            <div style="background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.25); border-radius: 16px; padding: 14px; text-align: center; margin-bottom: 18px;">
              <div style="font-size: 12px; color: #cbd5e1; margin-bottom: 8px;">Or self-check-in by scanning the entrance poster:</div>
              <a href="scan.php" class="ios-btn ios-btn-primary" style="width: 100%; justify-content: center; font-size: 14px; padding: 10px 16px;">
                <i class="fa fa-camera" style="font-size: 15px;"></i> Open Camera Scanner
              </a>
            </div>

            <div style="border-top: 1px dashed rgba(255,255,255,0.15); padding-top: 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
              <div>
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Current Plan</div>
                <div style="font-weight: 700; color: #fff; font-size: 14px;"><?php echo htmlspecialchars($membership['plan_name'] ?? 'No active pass'); ?></div>
              </div>
              <div style="text-align: right;">
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Valid Until</div>
                <div style="font-weight: 700; color: #34d399; font-size: 14px;">
                  <?php echo !empty($membership['expiry_date']) ? date('d M Y', strtotime($membership['expiry_date'])) : '—'; ?>
                </div>
              </div>
            </div>

            <?php if (!empty($membership['booking_id'])): ?>
              <div style="margin-top: 16px; text-align: center;">
                <a href="receipt.php?bookingid=<?php echo $membership['booking_id']; ?>" target="_blank" class="btn btn-sm btn-outline-light btn-block" style="border-radius: 10px; font-size: 12px;">
                  <i class="fa fa-print"></i> Download Official Pass Receipt
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!empty($scheduledMembership)): 
          $startsInDays = max(0, (int)ceil((strtotime($scheduledMembership['start_date']) - time()) / 86400));
        ?>
          <div style="max-width: 440px; margin: 16px auto 0; padding: 20px; border-radius: 18px; border: 1px solid rgba(56, 189, 248, 0.25); background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); box-shadow: 0 10px 25px rgba(0, 0, 0, 0.45);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
              <span class="ios-badge ios-badge-info" style="font-size: 11px;"><i class="fa fa-calendar"></i> UPCOMING SCHEDULED RENEWAL</span>
              <span class="ios-badge <?php echo ($scheduledMembership['payment_status'] === 'paid') ? 'ios-badge-success' : 'ios-badge-warning'; ?>" style="font-size: 11px;">
                <?php echo ucfirst($scheduledMembership['payment_status']); ?>
              </span>
            </div>
            <h4 style="font-size: 17px; font-weight: 700; color: #fff; margin-bottom: 6px;">
              <?php echo htmlspecialchars($scheduledMembership['plan_name'] ?? 'Membership Renewal'); ?>
            </h4>
            <div style="font-size: 13px; color: #94a3b8; margin-bottom: 12px;">
              Activates automatically on <strong><?php echo date('d M Y', strtotime($scheduledMembership['start_date'])); ?></strong> (in <?php echo $startsInDays; ?> days).
            </div>
            <?php if (!empty($scheduledMembership['booking_id'])): ?>
              <a href="receipt.php?bookingid=<?php echo $scheduledMembership['booking_id']; ?>" target="_blank" class="btn btn-sm btn-outline-light btn-block" style="border-radius: 10px; font-size: 12px;">
                <i class="fa fa-file-text-o"></i> View Renewal Invoice
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- My Bookings & Subscription Timeline -->
      <div class="grid-area-bookings" style="margin-bottom: 28px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
          <div>
            <h4 style="font-size: 17px; font-weight: 700; margin: 0; color: #fff;">
              <i class="fa fa-history" style="color: #10b981; margin-right: 6px;"></i> My Bookings &amp; Passes
            </h4>
            <span style="font-size: 12px; color: #94a3b8;">Subscription history and official receipts</span>
          </div>
          <div style="display: flex; gap: 8px;">
            <a href="Booking-History.php" class="btn btn-sm btn-outline-light" style="border-radius: 8px; font-size: 12px;">
              <i class="fa fa-external-link"></i> Full History
            </a>
          </div>
        </div>

        <?php if (!empty($userBookings)): ?>
          <div style="background: rgba(15, 23, 42, 0.65); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 18px; overflow: hidden; backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); box-shadow: 0 10px 25px rgba(0, 0, 0, 0.45);">
            <div class="table-responsive">
              <table class="table" style="margin: 0; color: #cbd5e1; font-size: 13px;">
                <thead>
                  <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.08); background: rgba(255, 255, 255, 0.02); font-size: 11px; text-transform: uppercase; color: #94a3b8;">
                    <th style="border: none; padding: 12px 16px;">Plan Title</th>
                    <th style="border: none; padding: 12px 16px;">Duration</th>
                    <th style="border: none; padding: 12px 16px;">Price</th>
                    <th style="border: none; padding: 12px 16px;">Date</th>
                    <th style="border: none; padding: 12px 16px; text-align: right;">Receipt</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($userBookings as $bk): ?>
                    <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.04);">
                      <td style="border: none; padding: 12px 16px;">
                        <strong style="color: #fff;"><?php echo htmlspecialchars($bk['title']); ?></strong>
                        <?php if (!empty($bk['category_name'])): ?>
                          <div style="font-size: 11px; color: #94a3b8;"><?php echo htmlspecialchars($bk['category_name']); ?></div>
                        <?php endif; ?>
                      </td>
                      <td style="border: none; padding: 12px 16px;">
                        <span class="badge badge-info" style="font-size: 11px;"><?php echo htmlspecialchars($bk['PackageDuratiobn']); ?></span>
                      </td>
                      <td style="border: none; padding: 12px 16px; font-weight: 700; color: #34d399;">
                        <?php echo format_currency($bk['Price']); ?>
                      </td>
                      <td style="border: none; padding: 12px 16px; font-size: 12px; color: #94a3b8;">
                        <?php echo date('d M Y', strtotime($bk['booking_date'])); ?>
                      </td>
                      <td style="border: none; padding: 12px 16px; text-align: right;">
                        <a href="receipt.php?bookingid=<?php echo $bk['bookingid']; ?>" target="_blank" class="btn btn-sm btn-outline-light" style="border-radius: 8px; font-size: 11.5px; padding: 3px 10px;">
                          <i class="fa fa-print"></i> Receipt
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php else: ?>
          <div style="background: #0b0f19; border: 1px dashed rgba(255, 255, 255, 0.08); border-radius: 14px; padding: 20px; text-align: center; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.45);">
            <p style="color: #94a3b8; font-size: 13px; margin: 0;">You have not booked any membership packages yet.</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Personal Coach Card -->
      <div class="grid-area-coach" style="margin-bottom: 28px;">
        <h4 style="font-size: 17px; font-weight: 700; margin-bottom: 12px; color: #fff;">
          <i class="fa fa-user-secret" style="color: var(--color-primary); margin-right: 6px;"></i> My Fitness Coach
        </h4>
        <?php if ($trainer): ?>
          <div class="coach-card">
            <div class="coach-avatar">
              <?php echo strtoupper(substr($trainer['name'], 0, 1)); ?>
            </div>
            <div style="flex: 1;">
              <div style="font-weight: 700; color: #fff; font-size: 16px;"><?php echo htmlspecialchars($trainer['name']); ?></div>
              <div style="font-size: 12.5px; color: var(--color-primary); font-weight: 600;"><?php echo htmlspecialchars($trainer['speciality']); ?></div>
              <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">
                <i class="fa fa-bullseye"></i> <?php echo htmlspecialchars($trainer['coaching_notes'] ?? 'Consistent strength training'); ?>
              </div>
            </div>
            <div>
              <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $trainer['mobile'] ?? ''); ?>" target="_blank" class="btn btn-sm btn-outline-success" style="border-radius: 8px;">
                <i class="fa fa-whatsapp"></i> Chat Coach
              </a>
            </div>
          </div>
        <?php else: ?>
          <div class="coach-card" style="border-style: dashed;">
            <div class="coach-avatar" style="background: rgba(255,255,255,0.08); color: var(--text-muted);">
              <i class="fa fa-plus"></i>
            </div>
            <div>
              <div style="font-weight: 700; color: #fff; font-size: 15px;">No Personal Coach Assigned Yet</div>
              <div style="font-size: 12.5px; color: var(--text-muted);">Request 1-on-1 certified personal training at reception.</div>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Lost & Found Assistance Card -->
      <div class="grid-area-lostfound" style="margin-bottom: 28px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
          <h4 style="font-size: 17px; font-weight: 700; margin: 0; color: #fff;">
            <i class="fa fa-archive text-warning" style="margin-right: 6px;"></i> Lost &amp; Found Assistance
          </h4>
          <a href="lost-found.php" class="btn btn-sm btn-outline-warning" style="border-radius: 8px; font-size: 12px; font-weight: 600;">
            <i class="fa fa-search"></i> Lost &amp; Found Hub
          </a>
        </div>

        <?php if (!empty($userLostFound)): ?>
          <div style="display: flex; flex-direction: column; gap: 10px;">
            <?php foreach ($userLostFound as $ulf): ?>
              <div style="background: rgba(15, 23, 42, 0.65); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 16px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; gap: 10px; backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);">
                <div>
                  <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 3px;">
                    <span class="badge <?php echo $ulf['item_type'] === 'lost' ? 'badge-danger' : 'badge-success'; ?>" style="font-size: 9.5px; text-transform: uppercase;">
                      <?php echo $ulf['item_type']; ?>
                    </span>
                    <strong style="color: #fff; font-size: 14px;"><?php echo htmlspecialchars($ulf['title']); ?></strong>
                  </div>
                  <div style="font-size: 12px; color: #94a3b8;">
                    <i class="fa fa-map-marker"></i> <?php echo htmlspecialchars($ulf['location_in_gym']); ?> &bull; <?php echo date('d M', strtotime($ulf['date_reported'])); ?>
                  </div>
                </div>
                <div>
                  <?php echo get_item_status_badge($ulf['status'], $ulf['item_type']); ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div style="background: rgba(15, 23, 42, 0.65); border: 1px dashed rgba(255, 255, 255, 0.08); border-radius: 18px; padding: 16px; text-align: center; backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);">
            <span style="font-size: 13px; color: #cbd5e1;">Misplaced an item at the gym? Check items turned in at the front desk or post a lost report.</span>
            <div style="margin-top: 10px;">
              <a href="lost-found.php" class="btn btn-sm btn-primary" style="border-radius: 8px; font-size: 12px;">
                <i class="fa fa-plus-circle"></i> Open Lost &amp; Found
              </a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php include 'include/footer.php'; ?>
  <script src="js/vendor/jquery-3.2.1.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
</body>
</html>
