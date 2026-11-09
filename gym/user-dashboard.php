<?php
require_once 'include/config.php';
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

// 2. Fetch Latest Membership Pass
$memStmt = $dbh->prepare("
    SELECT m.*, p.titlename as plan_name, p.PackageDuratiobn, p.Price,
    DATEDIFF(m.expiry_date, CURDATE()) as days_left
    FROM tbl_memberships m
    LEFT JOIN tbladdpackage p ON m.plan_id = p.id
    WHERE m.user_id = :id
    ORDER BY m.id DESC LIMIT 1
");
$memStmt->execute([':id' => $userId]);
$membership = $memStmt->fetch(PDO::FETCH_ASSOC);

// 3. Fetch Outstanding Dues
$dueStmt = $dbh->prepare("
    SELECT SUM(GREATEST(0, (CAST(p.Price AS DECIMAL(10,2)) + CAST(COALESCE(p.registration_fee,0) AS DECIMAL(10,2)) - CAST(COALESCE(p.discount,0) AS DECIMAL(10,2))) - (SELECT COALESCE(SUM(pay.payment), 0) FROM tblpayment pay WHERE pay.bookingID = b.id))) as dues
    FROM tblbooking b
    JOIN tbladdpackage p ON b.package_id = p.id
    WHERE b.userid = :uid
");
$dueStmt->execute([':uid' => $userId]);
$dues = floatval($dueStmt->fetchColumn());

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
$workoutsStmt = $dbh->prepare("SELECT * FROM tbl_workout_plans WHERE user_id = :uid ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')");
$workoutsStmt->execute([':uid' => $userId]);
$workouts = $workoutsStmt->fetchAll(PDO::FETCH_ASSOC);

// Group workouts by day
$workoutDays = [];
foreach ($workouts as $w) {
    $workoutDays[$w['day_of_week']][] = $w;
}

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
  <title>Member Dashboard — <?php echo htmlspecialchars($gymName); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php echo render_favicon_tags(); ?>
  <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
  <link rel="stylesheet" type="text/css" href="css/font-awesome.min.css">
  <link rel="stylesheet" type="text/css" href="css/ios-glass.css">
  <style>
    body {
      background: radial-gradient(circle at 15% 15%, #1e1b4b 0%, #09090b 55%, #030712 100%);
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
      background: linear-gradient(135deg, rgba(30, 41, 59, 0.85) 0%, rgba(15, 23, 42, 0.95) 100%);
      backdrop-filter: blur(30px);
      border: 1px solid rgba(255, 255, 255, 0.18);
      border-radius: 24px;
      padding: 26px;
      box-shadow: 0 20px 45px rgba(0, 0, 0, 0.5), inset 0 1px 1px rgba(255, 255, 255, 0.2);
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
      background: radial-gradient(circle, rgba(255, 45, 85, 0.15) 0%, transparent 60%);
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
      box-shadow: 0 10px 25px rgba(0,0,0,0.3);
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
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 18px;
      padding: 20px;
      margin-bottom: 20px;
      transition: transform 0.2s;
    }
    .kpi-tile:hover {
      transform: translateY(-2px);
      border-color: rgba(255, 255, 255, 0.2);
    }
    .coach-card {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 20px;
      padding: 22px;
      display: flex;
      align-items: center;
      gap: 16px;
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
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 16px;
      padding: 16px;
      margin-bottom: 12px;
    }
  </style>
</head>
<body>
  <?php include 'include/header.php'; ?>

  <div class="container" style="padding-top: 100px; padding-bottom: 80px;">
    <!-- Welcome Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 16px; margin-bottom: 30px;">
      <div>
        <div style="font-size: 13px; color: var(--color-primary); font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em;">
          Welcome Back, Athlete
        </div>
        <h1 style="font-size: 32px; font-weight: 800; margin: 4px 0 0; color: #fff;">
          <?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?>
        </h1>
        <p style="color: #94a3b8; font-size: 14.5px; margin: 4px 0 0;">
          Member ID: <span style="color: #fff; font-weight: 700; font-family: monospace;"><?php echo $memberCode; ?></span> &bull; 
          <?php echo htmlspecialchars($user['email']); ?>
        </p>
      </div>

      <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="scan.php" class="ios-btn ios-btn-primary" style="padding: 10px 16px; border-radius: 12px; font-weight: 700; box-shadow: 0 4px 14px rgba(56, 189, 248, 0.35); display: inline-flex; align-items: center; gap: 8px;">
          <i class="fa fa-camera"></i> Scan Attendance
        </a>
        <a href="Booking-History.php" class="ios-btn ios-btn-glass" style="padding: 10px 16px; border-radius: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
          <i class="fa fa-history"></i> Bookings
        </a>
        <a href="profile.php" class="ios-btn ios-btn-accent" style="padding: 10px 16px; border-radius: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
          <i class="fa fa-user"></i> Profile
        </a>
      </div>
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

    <div class="row">
      <!-- Left Column: Digital Membership Pass -->
      <div class="col-lg-5">
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
      </div>

      <!-- Right Column: Stats, Coach & Workouts -->
      <div class="col-lg-7">
        <!-- 3 Quick Stats -->
        <div class="row">
          <div class="col-md-4">
            <div class="kpi-tile">
              <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Visits This Month</div>
              <div style="font-size: 26px; font-weight: 800; color: #38bdf8; margin-top: 4px;"><?php echo $monthVisits; ?></div>
              <div style="font-size: 11.5px; color: #94a3b8;">Total workouts: <?php echo $totalVisits; ?></div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="kpi-tile">
              <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Days Remaining</div>
              <div style="font-size: 26px; font-weight: 800; color: #34d399; margin-top: 4px;">
                <?php echo max(0, intval($membership['days_left'] ?? 0)); ?>
              </div>
              <div style="font-size: 11.5px; color: #94a3b8;">On active membership</div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="kpi-tile">
              <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Last Check-in</div>
              <div style="font-size: 18px; font-weight: 800; color: #fff; margin-top: 6px;">
                <?php echo !empty($lastVisit) ? date('d M', strtotime($lastVisit)) : 'None'; ?>
              </div>
              <div style="font-size: 11.5px; color: #94a3b8;">
                <?php echo !empty($lastVisit) ? date('h:i A', strtotime($lastVisit)) : 'No check-ins yet'; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Personal Coach Card -->
        <div style="margin-bottom: 24px;">
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

        <!-- Weekly Workout Plan -->
        <div>
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
            <h4 style="font-size: 17px; font-weight: 700; margin: 0; color: #fff;">
              <i class="fa fa-list-ul" style="color: var(--color-primary); margin-right: 6px;"></i> Weekly Training Routine
            </h4>
            <a href="profile.php#routine" class="btn btn-sm btn-outline-info" style="border-radius: 8px; font-size: 12px;">
              <i class="fa fa-pencil"></i> Customize Weekly Routine
            </a>
          </div>
          <?php if (!empty($workoutDays)): ?>
            <?php foreach ($workoutDays as $day => $exercises): ?>
              <div class="workout-day-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                  <span style="font-weight: 700; color: var(--color-primary); font-size: 14px;"><?php echo $day; ?></span>
                  <span class="ios-badge ios-badge-info" style="font-size: 11px;"><?php echo htmlspecialchars($exercises[0]['target_muscle']); ?></span>
                </div>
                <div style="display: flex; flex-direction: column; gap: 6px;">
                  <?php foreach ($exercises as $ex): ?>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: #cbd5e1; border-bottom: 1px solid rgba(255,255,255,0.04); padding-bottom: 4px;">
                      <span>&bull; <?php echo htmlspecialchars($ex['exercise_name']); ?></span>
                      <span style="color: #94a3b8; font-family: monospace;"><?php echo $ex['sets']; ?> sets &times; <?php echo htmlspecialchars($ex['reps']); ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="workout-day-card" style="text-align: center; color: var(--text-muted); padding: 25px;">
              <i class="fa fa-heartbeat" style="font-size: 28px; color: var(--text-muted); margin-bottom: 8px; display: block;"></i>
              Your coach has not uploaded this week's workout schedule yet. Visit reception to get your tailored workout card.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <?php include 'include/footer.php'; ?>
  <script src="js/vendor/jquery-3.2.1.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
</body>
</html>
