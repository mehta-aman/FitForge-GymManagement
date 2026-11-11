<?php
require_once 'include/config.php';

$gymId = intval($_GET['gym'] ?? 1);
$incomingToken = trim($_GET['token'] ?? '');
$requireToken = get_setting('gym_checkin_require_token', '1', $dbh);
$validToken = get_gym_checkin_token($dbh);

$tokenValid = true;
if ($requireToken !== '0' && $requireToken !== 'no') {
    if (empty($incomingToken) || !hash_equals($validToken, $incomingToken)) {
        $tokenValid = false;
    }
}

// If token is invalid, show friendly security rejection screen
if (!$tokenValid) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Invalid QR Code — <?php echo htmlspecialchars(get_setting('gym_name', 'Golden Eagle')); ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <?php echo render_favicon_tags(); ?>
        <link rel="stylesheet" href="css/bootstrap.min.css"/>
        <link rel="stylesheet" href="css/font-awesome.min.css"/>
        <link rel="stylesheet" href="css/ios-glass.css"/>
        <style>
            body {
                background: radial-gradient(circle at 15% 15%, #1e1b4b 0%, #09090b 55%, #030712 100%);
                color: #f8fafc;
                min-height: 100vh;
                font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif;
                display: flex;
                flex-direction: column;
            }
        </style>
    </head>
    <body>
        <?php include 'include/header.php'; ?>
        <div class="site-content-wrapper" style="padding: 40px 16px; flex: 1; display: flex; align-items: center; justify-content: center;">
            <div class="ios-card" style="max-width: 440px; width: 100%; text-align: center; padding: 36px 24px; border-radius: 20px; background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(24px); border: 1px solid rgba(239, 68, 68, 0.3); box-shadow: 0 10px 25px rgba(0,0,0,0.45);">
                <div style="width: 76px; height: 76px; border-radius: 50%; background: rgba(239, 68, 68, 0.15); border: 2px solid #ef4444; color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px;">
                    <i class="fa fa-exclamation-triangle"></i>
                </div>
                <h3 style="font-weight: 800; font-size: 22px; margin-bottom: 10px; color: #fff;">Invalid or Expired QR Code</h3>
                <p style="color: #94a3b8; font-size: 14.5px; line-height: 1.55; margin-bottom: 26px;">
                    This QR code security token is not recognized or has been rotated by gym administration. Please scan the official printed poster at the reception counter.
                </p>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <a href="user-dashboard.php" class="ios-btn ios-btn-primary" style="justify-content: center;">
                        <i class="fa fa-dashboard"></i> Go to Member Dashboard
                    </a>
                    <a href="index.php" class="ios-btn ios-btn-glass" style="justify-content: center;">
                        <i class="fa fa-home"></i> Back to Home
                    </a>
                </div>
            </div>
        </div>
        <?php include 'include/footer.php'; ?>
    </body>
    </html>
    <?php
    exit();
}

// If user is NOT logged in, save intent and redirect to login
if (empty($_SESSION['uid'])) {
    $fullCheckinUri = 'checkin.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
    $_SESSION['checkin_pending_redirect'] = $fullCheckinUri;
    header("location: login.php?redirect=" . urlencode($fullCheckinUri));
    exit();
}

// User IS logged in
$userId = intval($_SESSION['uid']);

// 1. Fetch User Record
$userStmt = $dbh->prepare("SELECT id, fname, lname, email, mobile, profile_photo, status, gym_id FROM tbluser WHERE id = :uid LIMIT 1");
$userStmt->execute([':uid' => $userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['status'] === 'inactive') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Account Inactive — GYM MS</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="css/bootstrap.min.css"/>
        <link rel="stylesheet" href="css/ios-glass.css"/>
    </head>
    <body style="background: #09090b; color: #fff; padding: 50px 20px; text-align: center;">
        <div class="ios-card" style="max-width: 440px; margin: 0 auto; padding: 30px;">
            <h3 style="color: #ef4444;">Account Inactive</h3>
            <p>Your member account is currently inactive. Please speak with gym reception.</p>
            <a href="logout.php" class="ios-btn ios-btn-secondary">Log Out</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// 2. Fetch Current Membership Pass
$membership = find_current_membership($userId, $dbh, $gymId);
if (!$membership) {
    $membership = find_scheduled_membership($userId, $dbh, $gymId);
}

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

// 4. Handle Checkout Action (POST)
$justCheckedOut = false;
$sessionMinutes = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $attId = intval($_POST['attendance_id'] ?? 0);
        // Calculate duration before updating
        $durStmt = $dbh->prepare("SELECT check_in_time FROM tbl_attendance WHERE id = :id AND user_id = :uid AND check_out_time IS NULL");
        $durStmt->execute([':id' => $attId, ':uid' => $userId]);
        $inTime = $durStmt->fetchColumn();
        if ($inTime) {
            $sessionMinutes = max(1, round((time() - strtotime($inTime)) / 60));
        }

        $checkoutStmt = $dbh->prepare("UPDATE tbl_attendance SET check_out_time = NOW() WHERE id = :id AND user_id = :uid AND check_out_time IS NULL");
        $checkoutStmt->execute([':id' => $attId, ':uid' => $userId]);
        $justCheckedOut = true;
    }
}

// 5. Check Today's Attendance & Evaluate Eligibility
$attStmt = $dbh->prepare("
    SELECT id, check_in_time, check_out_time, check_in_method, notes
    FROM tbl_attendance
    WHERE user_id = :uid AND DATE(check_in_time) = CURDATE()
    ORDER BY id DESC LIMIT 1
");
$attStmt->execute([':uid' => $userId]);
$existing = $attStmt->fetch(PDO::FETCH_ASSOC);

$isNewCheckIn = false;
$isAlreadyCheckedIn = false;
$currentAttRecord = null;

if (!$justCheckedOut) {
    if ($existing && empty($existing['check_out_time'])) {
        // Already checked in and active on floor!
        $isAlreadyCheckedIn = true;
        $currentAttRecord = $existing;
    } else {
        // Evaluate Access Eligibility (Approved Policy 1, 2, 3, 5)
        $accessEval = evaluate_gym_access_eligibility($userId, $dbh, $gymId);
        if (!$accessEval['eligible']) {
            $rejectionReason = $accessEval['reason'];
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>Access Restricted — GYM MS</title>
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <link rel="stylesheet" href="css/bootstrap.min.css"/>
                <link rel="stylesheet" href="css/font-awesome.min.css"/>
                <link rel="stylesheet" href="css/ios-glass.css"/>
            </head>
            <body style="background: #09090b; color: #fff; padding: 50px 20px; text-align: center;">
                <div class="ios-card" style="max-width: 440px; margin: 0 auto; padding: 30px;">
                    <div style="width: 60px; height: 60px; border-radius: 50%; background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #ef4444; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 16px;">
                        <i class="fa fa-lock"></i>
                    </div>
                    <h3 style="color: #ef4444; font-weight: 800; margin-bottom: 8px;">Access Restricted</h3>
                    <p style="color: #cbd5e1; font-size: 14px; margin-bottom: 24px;"><?php echo htmlspecialchars($rejectionReason); ?></p>
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <a href="user-dashboard.php" class="ios-btn ios-btn-primary" style="padding: 10px 18px; border-radius: 12px; text-decoration: none;">Dashboard</a>
                        <a href="index.php" class="ios-btn ios-btn-secondary" style="padding: 10px 18px; border-radius: 12px; text-decoration: none;">Home</a>
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit();
        }

        // Record brand new check-in
        $insert = $dbh->prepare("
            INSERT INTO tbl_attendance (user_id, check_in_time, check_out_time, check_in_method, notes, gym_id)
            VALUES (:uid, NOW(), NULL, 'qr_scan', 'Gym Poster Self-Scan', :gym)
        ");
        $insert->execute([':uid' => $userId, ':gym' => $gymId]);
        $newId = $dbh->lastInsertId();
        
        $fetchNew = $dbh->prepare("SELECT * FROM tbl_attendance WHERE id = :id");
        $fetchNew->execute([':id' => $newId]);
        $currentAttRecord = $fetchNew->fetch(PDO::FETCH_ASSOC);
        $isNewCheckIn = true;
    }
}

// 6. Calculate Streak & Monthly Visits
$monthVisits = intval($dbh->query("SELECT count(*) FROM tbl_attendance WHERE user_id = $userId AND MONTH(check_in_time) = MONTH(CURDATE()) AND YEAR(check_in_time) = YEAR(CURDATE())")->fetchColumn());
$totalVisits = intval($dbh->query("SELECT count(*) FROM tbl_attendance WHERE user_id = $userId")->fetchColumn());
$streak = get_member_checkin_streak($userId, $dbh);
$gymName = get_setting('gym_name', 'Golden Eagle');
$gymTagline = get_setting('gym_tagline', 'FITNESS CLUB');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $isNewCheckIn ? 'Check-In Confirmed!' : ($isAlreadyCheckedIn ? 'Already Checked In' : 'Workout Complete'); ?> — <?php echo htmlspecialchars($gymName); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <?php echo render_favicon_tags(); ?>
    <link rel="stylesheet" href="css/bootstrap.min.css"/>
    <link rel="stylesheet" href="css/font-awesome.min.css"/>
    <link rel="stylesheet" href="css/ios-glass.css"/>
    <style>
        :root {
            --color-emerald: #10b981;
            --color-emerald-glow: rgba(16, 185, 129, 0.35);
        }
        body {
            background: radial-gradient(circle at 50% 10%, #1e1b4b 0%, #09090b 55%, #030712 100%);
            color: #f8fafc;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }
        .checkin-container {
            max-width: 460px;
            width: 100%;
            margin: 0 auto;
        }
        .checkin-card {
            background: linear-gradient(145deg, rgba(15, 23, 42, 0.85) 0%, rgba(11, 15, 25, 0.95) 100%);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 22px;
            padding: 32px 24px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.45);
            position: relative;
            overflow: hidden;
            text-align: center;
        }
        .checkin-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.12) 0%, transparent 60%);
            pointer-events: none;
        }

        /* Pulsing check badge */
        .checkin-hero-icon {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            position: relative;
            box-shadow: 0 10px 30px var(--color-emerald-glow);
            animation: heroBounce 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .checkin-hero-icon.success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
        }
        .checkin-hero-icon.already {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: #fff;
            box-shadow: 0 10px 30px rgba(14, 165, 233, 0.35);
        }
        .checkin-hero-icon.checkout {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: #fff;
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.35);
        }

        .pulse-ring {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 2px solid #10b981;
            animation: rippleRing 2s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
        }

        @keyframes heroBounce {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }
        @keyframes rippleRing {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.35); opacity: 0.2; }
            100% { transform: scale(1.6); opacity: 0; }
        }

        /* Stat tiles row */
        .checkin-stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin: 22px 0;
        }
        .checkin-stat-tile {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 12px 6px;
            text-align: center;
        }
        .checkin-stat-val {
            font-size: 16px;
            font-weight: 800;
            color: #fff;
            line-height: 1.2;
        }
        .checkin-stat-label {
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }

        .checkin-time-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 30px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            font-size: 13.5px;
            font-weight: 600;
            color: #e2e8f0;
            margin-bottom: 6px;
        }
    </style>
</head>
<body>

    <?php include 'include/header.php'; ?>

    <div class="site-content-wrapper" style="padding: 30px 16px; flex: 1; display: flex; align-items: center; justify-content: center;">
        <div class="checkin-container">
            <div class="checkin-card">

                <?php if ($justCheckedOut): ?>
                    <!-- STATE 1: CHECKED OUT JUST NOW -->
                    <div class="checkin-hero-icon checkout">
                        <i class="fa fa-flag-checkered"></i>
                    </div>

                    <div class="checkin-time-pill">
                        <i class="fa fa-clock-o text-warning"></i> Session Finished at <?php echo date('h:i A'); ?>
                    </div>

                    <h2 style="font-size: 24px; font-weight: 800; margin: 12px 0 6px; color: #fff;">Workout Completed!</h2>
                    <p style="color: #94a3b8; font-size: 14.5px; line-height: 1.5; margin-bottom: 20px;">
                        Great job crushing your session, <strong style="color: #fff;"><?php echo htmlspecialchars($user['fname']); ?></strong>! You trained for <strong><?php echo $sessionMinutes; ?> mins</strong> today.
                    </p>

                    <div class="checkin-stats-grid">
                        <div class="checkin-stat-tile">
                            <div class="checkin-stat-val text-info"><?php echo $sessionMinutes; ?>m</div>
                            <div class="checkin-stat-label">Duration</div>
                        </div>
                        <div class="checkin-stat-tile">
                            <div class="checkin-stat-val text-success"><?php echo $monthVisits; ?></div>
                            <div class="checkin-stat-label">This Month</div>
                        </div>
                        <div class="checkin-stat-tile">
                            <div class="checkin-stat-val text-warning">🔥 <?php echo $streak; ?>d</div>
                            <div class="checkin-stat-label">Streak</div>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 24px;">
                        <a href="user-dashboard.php" class="ios-btn ios-btn-primary" style="justify-content: center;">
                            <i class="fa fa-dashboard"></i> View Member Dashboard
                        </a>
                        <a href="profile.php#routine" class="ios-btn ios-btn-glass" style="justify-content: center;">
                            <i class="fa fa-calendar-check-o"></i> Review Workout Split
                        </a>
                    </div>

                <?php elseif ($isAlreadyCheckedIn): ?>
                    <!-- STATE 2: ALREADY CHECKED IN (ON FLOOR) -->
                    <?php 
                        $inTimestamp = strtotime($currentAttRecord['check_in_time']);
                        $elapsedMins = max(0, round((time() - $inTimestamp) / 60));
                    ?>
                    <div class="checkin-hero-icon already">
                        <i class="fa fa-check"></i>
                        <div class="pulse-ring" style="border-color: #0ea5e9;"></div>
                    </div>

                    <div class="checkin-time-pill" style="border-color: rgba(14, 165, 233, 0.4); background: rgba(14, 165, 233, 0.15); color: #38bdf8;">
                        <span class="status-pulse" style="background: #38bdf8; box-shadow: 0 0 8px #38bdf8;"></span> Checked In at <?php echo date('h:i A', $inTimestamp); ?>
                    </div>

                    <h2 style="font-size: 24px; font-weight: 800; margin: 12px 0 6px; color: #fff;">You're Checked In!</h2>
                    <p style="color: #94a3b8; font-size: 14px; line-height: 1.5; margin-bottom: 18px;">
                        Welcome back, <strong style="color: #fff;"><?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?></strong>. Your attendance for today is already recorded.
                    </p>

                    <div class="checkin-stats-grid">
                        <div class="checkin-stat-tile">
                            <div class="checkin-stat-val text-info"><?php echo $elapsedMins; ?>m</div>
                            <div class="checkin-stat-label">Floor Time</div>
                        </div>
                        <div class="checkin-stat-tile">
                            <div class="checkin-stat-val text-success">#<?php echo $monthVisits; ?></div>
                            <div class="checkin-stat-label">This Month</div>
                        </div>
                        <div class="checkin-stat-tile">
                            <div class="checkin-stat-val text-warning">🔥 <?php echo $streak; ?>d</div>
                            <div class="checkin-stat-label">Streak</div>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 24px;">
                        <a href="profile.php#routine" class="ios-btn ios-btn-primary" style="justify-content: center; font-size: 15px; padding: 14px 20px;">
                            <i class="fa fa-bolt"></i> View Today's Workout Routine
                        </a>
                        <form method="post" style="margin: 0;">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="action" value="checkout">
                            <input type="hidden" name="attendance_id" value="<?php echo $currentAttRecord['id']; ?>">
                            <button type="submit" class="ios-btn ios-btn-glass" style="width: 100%; justify-content: center; color: #f87171; border-color: rgba(239, 68, 68, 0.3);">
                                <i class="fa fa-sign-out"></i> Check Out (Leaving Gym)
                            </button>
                        </form>
                    </div>

                <?php else: ?>
                    <!-- STATE 3: FRESH CHECK-IN RECORDED -->
                    <div class="checkin-hero-icon success">
                        <i class="fa fa-check"></i>
                        <div class="pulse-ring"></div>
                    </div>

                    <div class="checkin-time-pill" style="border-color: rgba(16, 185, 129, 0.4); background: rgba(16, 185, 129, 0.15); color: #34d399;">
                        <i class="fa fa-check-circle"></i> Logged at <?php echo date('h:i A'); ?> • <?php echo date('D, d M'); ?>
                    </div>

                    <h2 style="font-size: 25px; font-weight: 800; margin: 12px 0 6px; color: #fff;">Check-In Confirmed!</h2>
                    <p style="color: #94a3b8; font-size: 14.5px; line-height: 1.5; margin-bottom: 18px;">
                        Welcome to <strong style="color: #fff;"><?php echo htmlspecialchars($gymName); ?></strong>, <strong style="color: #34d399;"><?php echo htmlspecialchars($user['fname']); ?></strong>! Ready to crush today's goals?
                    </p>

                    <!-- Dues or Expired Pass Warnings -->
                    <?php if ($dues > 0): ?>
                        <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 14px; padding: 10px 14px; margin-bottom: 14px; font-size: 13px; color: #fca5a5; text-align: left; display: flex; align-items: center; gap: 8px;">
                            <i class="fa fa-exclamation-circle" style="font-size: 16px; flex-shrink: 0;"></i>
                            <div>Pending dues of <strong><?php echo format_currency($dues); ?></strong>. Please settle at reception desk.</div>
                        </div>
                    <?php endif; ?>

                    <?php if ($membership && $membership['status'] !== 'active'): ?>
                        <div style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 14px; padding: 10px 14px; margin-bottom: 14px; font-size: 13px; color: #fcd34d; text-align: left; display: flex; align-items: center; gap: 8px;">
                            <i class="fa fa-bell" style="font-size: 16px; flex-shrink: 0;"></i>
                            <div>Your pass is <strong><?php echo strtoupper($membership['status']); ?></strong>. Please see reception to renew.</div>
                        </div>
                    <?php endif; ?>

                    <div class="checkin-stats-grid">
                        <div class="checkin-stat-tile">
                            <div class="checkin-stat-val text-success">#<?php echo $monthVisits; ?></div>
                            <div class="checkin-stat-label">This Month</div>
                        </div>
                        <div class="checkin-stat-tile">
                            <div class="checkin-stat-val text-warning">🔥 <?php echo $streak > 0 ? $streak . 'd' : '1d'; ?></div>
                            <div class="checkin-stat-label">Streak</div>
                        </div>
                        <div class="checkin-stat-tile" style="padding: 10px 4px;">
                            <div class="checkin-stat-val text-info" style="font-size: 13px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 95px; margin: 0 auto;" title="<?php echo htmlspecialchars($membership['plan_name'] ?? 'Active Pass'); ?>">
                                <?php echo htmlspecialchars(!empty($membership['plan_name']) ? (strlen($membership['plan_name']) > 12 ? substr($membership['plan_name'], 0, 11) . '…' : $membership['plan_name']) : 'Active Pass'); ?>
                            </div>
                            <div class="checkin-stat-label">Membership</div>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 24px;">
                        <a href="profile.php#routine" class="ios-btn ios-btn-primary" style="justify-content: center; font-size: 15px; padding: 14px 20px;">
                            <i class="fa fa-bolt"></i> Open Today's Workout Routine
                        </a>
                        <a href="user-dashboard.php" class="ios-btn ios-btn-glass" style="justify-content: center;">
                            <i class="fa fa-id-card-o"></i> View Digital Pass &amp; Dashboard
                        </a>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid rgba(255, 255, 255, 0.08); font-size: 12.5px; color: #64748b; display: flex; justify-content: space-between; align-items: center;">
                    <span>Signed in as <strong><?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?></strong></span>
                    <a href="logout.php" style="color: #94a3b8; text-decoration: underline;">Switch Account</a>
                </div>

            </div>
        </div>
    </div>

    <?php include 'include/footer.php'; ?>

    <!-- Soft Audio Chime & Mobile Haptic Feedback -->
    <script>
        (function() {
            try {
                // Subtle pleasant two-tone C5 -> G5 chord synthesis
                <?php if ($isNewCheckIn): ?>
                var AudioContext = window.AudioContext || window.webkitAudioContext;
                if (AudioContext) {
                    var ctx = new AudioContext();
                    var playTone = function(freq, delay, dur) {
                        setTimeout(function() {
                            var osc = ctx.createOscillator();
                            var gain = ctx.createGain();
                            osc.type = 'sine';
                            osc.frequency.setValueAtTime(freq, ctx.currentTime);
                            gain.gain.setValueAtTime(0.08, ctx.currentTime);
                            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + dur);
                            osc.connect(gain);
                            gain.connect(ctx.destination);
                            osc.start();
                            osc.stop(ctx.currentTime + dur);
                        }, delay);
                    };
                    playTone(523.25, 0, 0.25);   // C5
                    playTone(659.25, 120, 0.35); // E5
                    playTone(783.99, 240, 0.45); // G5
                }
                if (navigator.vibrate) {
                    navigator.vibrate([40, 60, 40]);
                }
                <?php endif; ?>
            } catch(e) {}
        })();
    </script>
</body>
</html>