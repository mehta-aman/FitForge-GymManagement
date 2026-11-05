<?php
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$msg = '';
$error = '';

// Handle POST: Settings Update or Rotate Token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security token mismatch. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save_settings') {
            $baseUrl = trim($_POST['gym_checkin_base_url'] ?? '');
            $requireToken = isset($_POST['gym_checkin_require_token']) ? '1' : '0';
            $posterTitle = trim($_POST['gym_poster_title'] ?? 'SCAN TO CHECK IN');
            $posterSubtitle = trim($_POST['gym_poster_subtitle'] ?? 'Point your phone camera here to log your attendance instantly');
            $wifiEnabled = isset($_POST['gym_poster_wifi_enabled']) ? '1' : '0';
            $wifiSsid = trim($_POST['gym_poster_wifi_ssid'] ?? '');
            $wifiPass = trim($_POST['gym_poster_wifi_pass'] ?? '');

            set_setting('gym_checkin_base_url', $baseUrl, 'attendance', $dbh);
            set_setting('gym_checkin_require_token', $requireToken, 'attendance', $dbh);
            set_setting('gym_poster_title', $posterTitle, 'attendance', $dbh);
            set_setting('gym_poster_subtitle', $posterSubtitle, 'attendance', $dbh);
            set_setting('gym_poster_wifi_enabled', $wifiEnabled, 'attendance', $dbh);
            set_setting('gym_poster_wifi_ssid', $wifiSsid, 'attendance', $dbh);
            set_setting('gym_poster_wifi_pass', $wifiPass, 'attendance', $dbh);

            // Reload settings
            load_all_settings($dbh);
            $msg = 'Station & Poster settings saved successfully!';
        } elseif ($action === 'rotate_token') {
            $newToken = 'fg_' . substr(bin2hex(random_bytes(8)), 0, 12);
            set_setting('gym_checkin_token', $newToken, 'attendance', $dbh);
            load_all_settings($dbh);
            $msg = 'Check-In Security Token rotated! The QR code has been updated.';
        }
    }
}

// Current values
$baseUrl = get_gym_base_url($dbh);
$token = get_gym_checkin_token($dbh);
$requireToken = get_setting('gym_checkin_require_token', '1', $dbh);
$checkinUrl = get_gym_checkin_url($dbh);

$gymName = get_setting('gym_name', 'Golden Eagle');
$gymTagline = get_setting('gym_tagline', 'FITNESS CLUB');
$gymLogo = get_logo_url($dbh);
$contactPhone = get_setting('contact_phone', '+91 9983665673');
$contactAddress = get_setting('contact_address', 'Radha Krishna Mandir, Nimbaheda Road');

$posterTitle = get_setting('gym_poster_title', 'SCAN TO CHECK IN');
$posterSubtitle = get_setting('gym_poster_subtitle', 'Point your phone camera here to log your attendance instantly');
$wifiEnabled = get_setting('gym_poster_wifi_enabled', '1', $dbh);
$wifiSsid = get_setting('gym_poster_wifi_ssid', 'GoldenEagle-Guest', $dbh);
$wifiPass = get_setting('gym_poster_wifi_pass', 'FitLife2026', $dbh);

// Tunnel detection helper
$detectedTunnel = '';
if (file_exists('/tmp/cloudflared.log')) {
    $cLog = @file_get_contents('/tmp/cloudflared.log');
    if (!empty($cLog) && preg_match_all('/https:\/\/[a-z0-9\-]+\.trycloudflare\.com/', $cLog, $tMatches)) {
        $detectedTunnel = end($tMatches[0]);
    }
}

// Fetch Today's QR Self-Check-Ins
$logStmt = $dbh->prepare("
    SELECT a.id, a.user_id, a.check_in_time, a.check_out_time, a.check_in_method, a.notes,
           u.fname, u.lname, u.email, u.mobile, u.profile_photo
    FROM tbl_attendance a
    JOIN tbluser u ON a.user_id = u.id
    WHERE DATE(a.check_in_time) = CURDATE()
    ORDER BY a.check_in_time DESC
");
$logStmt->execute();
$todayCheckins = $logStmt->fetchAll(PDO::FETCH_ASSOC);

$totalToday = count($todayCheckins);
$qrToday = 0;
$floorCount = 0;
foreach ($todayCheckins as $tc) {
    if ($tc['check_in_method'] === 'qr_scan') $qrToday++;
    if (empty($tc['check_out_time'])) $floorCount++;
}

// Standalone Poster Mode (?poster_only=1 or ?print=1)
$isPosterOnly = isset($_GET['poster_only']) || isset($_GET['print']);
if ($isPosterOnly):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gym Self Check-In Poster — <?php echo htmlspecialchars($gymName); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"/>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #ffffff;
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .print-btn-bar {
            position: fixed;
            top: 15px;
            right: 15px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }
        .btn-action {
            background: #0f172a;
            color: #fff;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-action.back { background: #64748b; }
        
        /* A4 / Letter Poster Frame */
        .poster-frame {
            width: 100%;
            max-width: 680px;
            background: #ffffff;
            border: 4px solid #0f172a;
            border-radius: 28px;
            padding: 44px 36px 36px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            position: relative;
        }
        .poster-badge {
            display: inline-block;
            background: #10b981;
            color: #ffffff;
            padding: 6px 18px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 16px;
        }
        .poster-gym-title {
            font-size: 36px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: -0.5px;
            line-height: 1.15;
            margin-bottom: 4px;
        }
        .poster-gym-subtitle {
            font-size: 15px;
            font-weight: 600;
            color: #64748b;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 24px;
        }
        .qr-wrapper {
            background: #ffffff;
            display: inline-block;
            padding: 18px;
            border: 3px solid #0f172a;
            border-radius: 24px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.06);
            margin-bottom: 20px;
        }
        .qr-wrapper canvas, .qr-wrapper img {
            display: block;
            width: 280px;
            height: 280px;
        }
        .poster-action-title {
            font-size: 28px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
        }
        .poster-instructions {
            font-size: 15px;
            color: #475569;
            max-width: 480px;
            margin: 0 auto 24px;
            line-height: 1.5;
        }
        .poster-steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 26px;
            padding-top: 18px;
            border-top: 1px dashed #cbd5e1;
        }
        .step-box {
            text-align: center;
        }
        .step-num {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #0f172a;
            color: #ffffff;
            font-weight: 800;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
        }
        .step-text {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
        }
        .step-sub {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
        }
        .wifi-box {
            background: #f1f5f9;
            border-radius: 14px;
            padding: 10px 16px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: #334155;
            margin-bottom: 18px;
        }
        .poster-footer {
            font-size: 11.5px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 14px;
        }

        @media print {
            body { padding: 0; background: #fff; }
            .print-btn-bar { display: none !important; }
            .poster-frame {
                border: 3px solid #000;
                box-shadow: none;
                max-width: 100%;
                width: 100%;
                height: 100vh;
                border-radius: 0;
                padding: 40px 30px;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }
            .qr-wrapper {
                border-color: #000;
                box-shadow: none;
                padding: 14px;
            }
            .qr-wrapper canvas, .qr-wrapper img {
                width: 320px;
                height: 320px;
            }
            @page {
                size: A4 portrait;
                margin: 10mm;
            }
        }
    </style>
</head>
<body>
    <div class="print-btn-bar">
        <button class="btn-action" onclick="window.print();"><i class="fa fa-print"></i> Print Poster</button>
        <a href="gym-qr.php" class="btn-action back"><i class="fa fa-arrow-left"></i> Back to Admin</a>
    </div>

    <div class="poster-frame">
        <div>
            <div class="poster-badge"><i class="fa fa-bolt"></i> FAST ATTENDANCE STATION</div>
            <h1 class="poster-gym-title"><?php echo htmlspecialchars($gymName); ?></h1>
            <div class="poster-gym-subtitle"><?php echo htmlspecialchars($gymTagline); ?></div>
        </div>

        <div>
            <div class="qr-wrapper">
                <div id="qrcode-print"></div>
                <noscript>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=<?php echo urlencode($checkinUrl); ?>" alt="Check-in QR Code">
                </noscript>
            </div>

            <div class="poster-action-title"><?php echo htmlspecialchars($posterTitle); ?></div>
            <div class="poster-instructions"><?php echo htmlspecialchars($posterSubtitle); ?></div>
        </div>

        <div>
            <div class="poster-steps">
                <div class="step-box">
                    <div class="step-num">1</div>
                    <div class="step-text">Open Camera</div>
                    <div class="step-sub">On iPhone / Android</div>
                </div>
                <div class="step-box">
                    <div class="step-num">2</div>
                    <div class="step-text">Scan QR Code</div>
                    <div class="step-sub">Tap link prompt</div>
                </div>
                <div class="step-box">
                    <div class="step-num">3</div>
                    <div class="step-text">Instant Check-In</div>
                    <div class="step-sub">Attendance logged!</div>
                </div>
            </div>

            <?php if ($wifiEnabled && (!empty($wifiSsid) || !empty($wifiPass))): ?>
                <div class="wifi-box">
                    <i class="fa fa-wifi" style="font-size: 16px; color: #0f172a;"></i>
                    <span><strong>Member Wi-Fi:</strong> <?php echo htmlspecialchars($wifiSsid); ?></span>
                    <?php if (!empty($wifiPass)): ?>
                        <span>• <strong>Password:</strong> <?php echo htmlspecialchars($wifiPass); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="poster-footer">
                <?php echo htmlspecialchars($gymName); ?> • <?php echo htmlspecialchars($contactAddress); ?> • Reception: <?php echo htmlspecialchars($contactPhone); ?>
            </div>
        </div>
    </div>

    <script src="../js/qrcode.min.js"></script>
    <script>
        (function() {
            var container = document.getElementById('qrcode-print');
            var url = <?php echo json_encode($checkinUrl); ?>;
            try {
                if (typeof QRCode !== 'undefined') {
                    new QRCode(container, {
                        text: url,
                        width: 280,
                        height: 280,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.H
                    });
                } else {
                    container.innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' + encodeURIComponent(url) + '" width="280" height="280" alt="QR Code">';
                }
            } catch(e) {
                container.innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' + encodeURIComponent(url) + '" width="280" height="280" alt="QR Code">';
            }

            <?php if (isset($_GET['print'])): ?>
            window.addEventListener('load', function() {
                setTimeout(function() { window.print(); }, 400);
            });
            <?php endif; ?>
        })();
    </script>
</body>
</html>
<?php
exit();
endif;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gym QR Poster &amp; Station — <?php echo htmlspecialchars($gymName); ?></title>
    <?php echo render_favicon_tags(); ?>
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="stylesheet" type="text/css" href="css/ios-admin-glass.css">
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .station-grid {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        @media (max-width: 991px) {
            .station-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Poster Standee Card Preview */
        .poster-preview-card {
            background: #ffffff;
            color: #0f172a;
            border-radius: 24px;
            padding: 32px 24px;
            text-align: center;
            border: 3px solid #0f172a;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            position: relative;
            max-width: 440px;
            margin: 0 auto;
        }
        .poster-preview-card .badge-station {
            display: inline-block;
            background: #10b981;
            color: #ffffff;
            padding: 4px 14px;
            border-radius: 16px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .poster-preview-card h3 {
            font-size: 24px;
            font-weight: 900;
            margin-bottom: 2px;
            color: #0f172a;
        }
        .poster-preview-card .tagline {
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 18px;
        }
        .qr-display-box {
            background: #ffffff;
            display: inline-block;
            padding: 14px;
            border: 2px solid #0f172a;
            border-radius: 18px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            margin-bottom: 14px;
        }
        .qr-display-box canvas, .qr-display-box img {
            display: block;
            width: 220px;
            height: 220px;
        }
        .poster-preview-card .cta-title {
            font-size: 20px;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .poster-preview-card .cta-sub {
            font-size: 12.5px;
            color: #475569;
            margin-bottom: 16px;
            line-height: 1.4;
        }
        .steps-mini-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            padding-top: 14px;
            border-top: 1px dashed #cbd5e1;
            margin-bottom: 14px;
        }
        .step-mini-num {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #0f172a;
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 4px;
        }
        .step-mini-text {
            font-size: 11px;
            font-weight: 700;
            color: #1e293b;
        }
        .wifi-mini-pill {
            background: #f1f5f9;
            border-radius: 10px;
            padding: 6px 12px;
            font-size: 11.5px;
            color: #334155;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* Stat tiles */
        .stat-card-custom {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 18px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .stat-icon-custom {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        /* Print media rules */
        @media print {
            .app-header, .app-sidebar, .app-title, .breadcrumb, .no-print, .station-controls-col, .activity-feed-section {
                display: none !important;
            }
            .app-content {
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
            }
            .station-grid {
                display: block !important;
            }
            .poster-preview-card {
                max-width: 100% !important;
                width: 100% !important;
                border: 3px solid #000 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                padding: 40px !important;
                height: 100vh !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: space-between !important;
            }
            .qr-display-box {
                border-color: #000 !important;
                padding: 16px !important;
            }
            .qr-display-box canvas, .qr-display-box img {
                width: 320px !important;
                height: 320px !important;
            }
            @page {
                size: A4 portrait;
                margin: 10mm;
            }
        }
    </style>
</head>
<body class="app sidebar-mini rtl">
    <?php include 'include/header.php'; ?>
    <div class="app-sidebar__overlay" data-toggle="sidebar"></div>
    <?php include 'include/sidebar.php'; ?>

    <main class="app-content">
        <div class="app-title no-print">
            <div>
                <h1><i class="fa fa-qrcode" style="color: var(--color-primary); margin-right: 8px;"></i> Gym Self Check-In Station &amp; QR Poster</h1>
                <p>Printable QR poster for front desk reception &amp; member mobile self-attendance</p>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <a href="attendance.php" class="btn btn-outline-light" style="border-radius: 10px; font-weight: 600;">
                    <i class="fa fa-users"></i> Live Floor Ledger
                </a>
                <button type="button" class="btn btn-primary" onclick="printOfficialPoster();" style="border-radius: 10px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);">
                    <i class="fa fa-print"></i> Print Official Poster
                </button>
            </div>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-success alert-dismissible fade show no-print" style="border-radius: 14px; background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #fff;">
                <i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show no-print" style="border-radius: 14px; background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fff;">
                <i class="fa fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Stat Tiles -->
        <div class="row no-print" style="margin-bottom: 24px;">
            <div class="col-md-4 mb-3">
                <div class="stat-card-custom">
                    <div class="stat-icon-custom" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                        <i class="fa fa-qrcode"></i>
                    </div>
                    <div>
                        <div style="font-size: 26px; font-weight: 800; color: #fff; line-height: 1;"><?php echo $qrToday; ?></div>
                        <div style="font-size: 13px; color: #94a3b8; margin-top: 4px;">QR Self-Scans Today</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card-custom">
                    <div class="stat-icon-custom" style="background: rgba(14, 165, 233, 0.15); color: #0ea5e9;">
                        <i class="fa fa-users"></i>
                    </div>
                    <div>
                        <div style="font-size: 26px; font-weight: 800; color: #fff; line-height: 1;"><?php echo $floorCount; ?></div>
                        <div style="font-size: 13px; color: #94a3b8; margin-top: 4px;">Currently on Floor</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card-custom">
                    <div class="stat-icon-custom" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">
                        <i class="fa fa-calendar-check-o"></i>
                    </div>
                    <div>
                        <div style="font-size: 26px; font-weight: 800; color: #fff; line-height: 1;"><?php echo $totalToday; ?></div>
                        <div style="font-size: 13px; color: #94a3b8; margin-top: 4px;">Total Check-Ins Today</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="station-grid">
            <!-- Left Column: Controls & Configuration -->
            <div class="station-controls-col no-print">
                <div class="tile" style="border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.1); background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(20px);">
                    <h3 class="tile-title" style="font-size: 18px; font-weight: 700; color: #fff; display: flex; align-items: center; justify-content: space-between;">
                        <span><i class="fa fa-sliders text-primary"></i> Station Configuration</span>
                        <span class="badge badge-success" style="font-size: 12px; padding: 6px 12px; border-radius: 12px;"><i class="fa fa-circle text-white"></i> Active Station</span>
                    </h3>
                    <div class="tile-body">

                        <!-- Target Check-In URL Box -->
                        <div style="background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 16px; padding: 18px; margin-bottom: 24px;">
                            <label style="font-size: 13px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block;">
                                Active Check-In URL (Embedded in QR)
                            </label>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" id="activeUrlField" class="form-control" value="<?php echo htmlspecialchars($checkinUrl); ?>" readonly style="background: rgba(0, 0, 0, 0.4); border-color: rgba(255, 255, 255, 0.15); color: #38bdf8; font-family: monospace; font-size: 13px; border-radius: 10px;">
                                <button type="button" class="btn btn-outline-info" onclick="copyCheckinLink();" style="border-radius: 10px; font-weight: 600;" title="Copy check-in link">
                                    <i class="fa fa-copy"></i>
                                </button>
                                <a href="<?php echo htmlspecialchars($checkinUrl); ?>" target="_blank" class="btn btn-outline-success" style="border-radius: 10px; font-weight: 600;" title="Test check-in flow">
                                    <i class="fa fa-external-link"></i>
                                </a>
                            </div>

                            <!-- Fast Base URL Presets -->
                            <div style="margin-top: 12px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
                                <span style="font-size: 12px; color: #64748b;">Quick Presets:</span>
                                <?php if (!empty($detectedTunnel)): ?>
                                    <button type="button" class="btn btn-sm btn-outline-success" onclick="setBaseUrl('<?php echo htmlspecialchars($detectedTunnel); ?>');" style="border-radius: 8px; font-size: 11.5px;">
                                        <i class="fa fa-globe"></i> Cloudflare Tunnel (Live Phone Scan)
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setBaseUrl('http://127.0.0.1:8080');" style="border-radius: 8px; font-size: 11.5px;">
                                    <i class="fa fa-laptop"></i> Localhost (127.0.0.1:8080)
                                </button>
                            </div>
                        </div>

                        <!-- Poster Customization Form -->
                        <form method="post" id="stationConfigForm">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="action" value="save_settings">

                            <div class="form-group">
                                <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Target Base Domain / URL</label>
                                <input type="text" name="gym_checkin_base_url" id="baseUrlInput" class="form-control" value="<?php echo htmlspecialchars($baseUrl); ?>" placeholder="e.g. https://your-gym.com or https://xxx.trycloudflare.com" style="border-radius: 10px; background: rgba(15, 23, 42, 0.4); border-color: rgba(255, 255, 255, 0.15); color: #fff;">
                                <small class="form-text text-muted">Leave empty to auto-detect current web address or tunnel.</small>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Poster Headline</label>
                                    <input type="text" name="gym_poster_title" class="form-control" value="<?php echo htmlspecialchars($posterTitle); ?>" style="border-radius: 10px; background: rgba(15, 23, 42, 0.4); border-color: rgba(255, 255, 255, 0.15); color: #fff;">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Poster Subtitle</label>
                                    <input type="text" name="gym_poster_subtitle" class="form-control" value="<?php echo htmlspecialchars($posterSubtitle); ?>" style="border-radius: 10px; background: rgba(15, 23, 42, 0.4); border-color: rgba(255, 255, 255, 0.15); color: #fff;">
                                </div>
                            </div>

                            <!-- Member Wi-Fi on Poster -->
                            <div style="background: rgba(15, 23, 42, 0.4); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 14px; padding: 14px 16px; margin-bottom: 20px;">
                                <div class="custom-control custom-switch mb-2">
                                    <input type="checkbox" class="custom-control-input" id="wifiSwitch" name="gym_poster_wifi_enabled" value="1" <?php echo ($wifiEnabled === '1' || $wifiEnabled === 'yes') ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="wifiSwitch" style="font-weight: 600; color: #cbd5e1;">Print Member Wi-Fi on Poster</label>
                                </div>
                                <div class="row mt-2" id="wifiFields">
                                    <div class="col-sm-6 form-group mb-1">
                                        <label style="font-size: 12px; color: #94a3b8;">Wi-Fi Network (SSID)</label>
                                        <input type="text" name="gym_poster_wifi_ssid" class="form-control form-control-sm" value="<?php echo htmlspecialchars($wifiSsid); ?>" style="border-radius: 8px; background: rgba(0, 0, 0, 0.3); border-color: rgba(255, 255, 255, 0.1); color: #fff;">
                                    </div>
                                    <div class="col-sm-6 form-group mb-1">
                                        <label style="font-size: 12px; color: #94a3b8;">Wi-Fi Password</label>
                                        <input type="text" name="gym_poster_wifi_pass" class="form-control form-control-sm" value="<?php echo htmlspecialchars($wifiPass); ?>" style="border-radius: 8px; background: rgba(0, 0, 0, 0.3); border-color: rgba(255, 255, 255, 0.1); color: #fff;">
                                    </div>
                                </div>
                            </div>

                            <!-- Security Token Configuration -->
                            <div style="background: rgba(15, 23, 42, 0.4); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 14px; padding: 14px 16px; margin-bottom: 20px;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="tokenSwitch" name="gym_checkin_require_token" value="1" <?php echo ($requireToken === '1' || $requireToken === 'yes') ? 'checked' : ''; ?>>
                                        <label class="custom-control-label" for="tokenSwitch" style="font-weight: 600; color: #cbd5e1;">Enforce QR Security Token</label>
                                    </div>
                                    <span class="badge badge-info" style="font-family: monospace; font-size: 12px; padding: 4px 8px; border-radius: 6px;"><?php echo htmlspecialchars($token); ?></span>
                                </div>
                                <small class="text-muted d-block">Prevents outsiders from guessing the check-in URL. Members must scan the physical poster.</small>
                            </div>

                            <div style="display: flex; gap: 10px; margin-top: 10px;">
                                <button type="submit" class="btn btn-primary" style="flex: 1; border-radius: 10px; font-weight: 600; padding: 10px;">
                                    <i class="fa fa-save"></i> Save Station Settings
                                </button>
                            </div>
                        </form>

                        <!-- Rotate Token Separate Form -->
                        <form method="post" style="margin-top: 12px;">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="action" value="rotate_token">
                            <button type="submit" class="btn btn-outline-warning btn-sm" onclick="return confirm('Rotate check-in token? Any existing old posters will need to be re-printed.');" style="width: 100%; border-radius: 10px; font-weight: 600;">
                                <i class="fa fa-refresh"></i> Rotate / Regenerate Security Token
                            </button>
                        </form>

                        <!-- Print & Download Quick Action Buttons -->
                        <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid rgba(255, 255, 255, 0.1); display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <button type="button" class="btn btn-success" onclick="printOfficialPoster();" style="border-radius: 12px; font-weight: 700; padding: 12px;">
                                <i class="fa fa-print"></i> Print Poster
                            </button>
                            <button type="button" class="btn btn-info" onclick="downloadPosterPng();" style="border-radius: 12px; font-weight: 700; padding: 12px;">
                                <i class="fa fa-download"></i> Download PNG
                            </button>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Right Column: Live Poster Standee Preview -->
            <div class="poster-preview-col">
                <div class="d-flex justify-content-between align-items-center mb-2 no-print">
                    <span style="font-size: 14px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">
                        <i class="fa fa-eye"></i> Official Poster Preview
                    </span>
                    <a href="gym-qr.php?poster_only=1" target="_blank" class="btn btn-sm btn-outline-light" style="border-radius: 8px;">
                        <i class="fa fa-external-link"></i> Full Page Pop-Out
                    </a>
                </div>

                <div class="poster-preview-card" id="printablePosterArea">
                    <div class="badge-station"><i class="fa fa-bolt"></i> FAST ATTENDANCE STATION</div>
                    
                    <?php if (!empty($gymLogo)): ?>
                        <div style="margin-bottom: 8px;">
                            <img src="<?php echo htmlspecialchars($gymLogo); ?>" alt="Logo" style="max-height: 44px; max-width: 180px; object-fit: contain;">
                        </div>
                    <?php endif; ?>

                    <h3 id="previewGymName"><?php echo htmlspecialchars($gymName); ?></h3>
                    <div class="tagline" id="previewGymTagline"><?php echo htmlspecialchars($gymTagline); ?></div>

                    <div class="qr-display-box">
                        <div id="qrcode-display"></div>
                        <noscript>
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?php echo urlencode($checkinUrl); ?>" alt="QR Code">
                        </noscript>
                    </div>

                    <div class="cta-title" id="previewPosterTitle"><?php echo htmlspecialchars($posterTitle); ?></div>
                    <div class="cta-sub" id="previewPosterSubtitle"><?php echo htmlspecialchars($posterSubtitle); ?></div>

                    <div class="steps-mini-grid">
                        <div>
                            <div class="step-mini-num">1</div>
                            <div class="step-mini-text">Open Camera</div>
                        </div>
                        <div>
                            <div class="step-mini-num">2</div>
                            <div class="step-mini-text">Point at QR</div>
                        </div>
                        <div>
                            <div class="step-mini-num">3</div>
                            <div class="step-mini-text">Checked In!</div>
                        </div>
                    </div>

                    <?php if ($wifiEnabled && (!empty($wifiSsid) || !empty($wifiPass))): ?>
                        <div class="wifi-mini-pill" id="previewWifiPill">
                            <i class="fa fa-wifi text-success"></i>
                            <span><strong>Wi-Fi:</strong> <?php echo htmlspecialchars($wifiSsid); ?></span>
                            <?php if (!empty($wifiPass)): ?>
                                <span>• <strong>Pass:</strong> <?php echo htmlspecialchars($wifiPass); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div style="font-size: 11px; color: #94a3b8; margin-top: 14px; border-top: 1px solid #e2e8f0; padding-top: 10px;">
                        <?php echo htmlspecialchars($gymName); ?> • <?php echo htmlspecialchars($contactAddress); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Real-Time Activity Feed: Today's Self Check-Ins -->
        <div class="tile activity-feed-section no-print" style="border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.1); background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(20px);">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="tile-title mb-0" style="font-size: 18px; font-weight: 700; color: #fff;">
                    <i class="fa fa-bolt text-warning" style="margin-right: 6px;"></i> Today's Check-Ins Feed
                </h3>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn btn-sm btn-outline-info" onclick="location.reload();" style="border-radius: 8px;">
                        <i class="fa fa-refresh"></i> Refresh Feed
                    </button>
                    <a href="attendance.php" class="btn btn-sm btn-outline-light" style="border-radius: 8px;">
                        <i class="fa fa-list"></i> Full Attendance Ledger
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover" style="color: #cbd5e1; vertical-align: middle;">
                    <thead>
                        <tr style="border-bottom: 2px solid rgba(255, 255, 255, 0.1); color: #94a3b8; font-size: 12px; text-transform: uppercase;">
                            <th>Member</th>
                            <th>Contact</th>
                            <th>Check-In Time</th>
                            <th>Method</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($todayCheckins)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 30px; color: #94a3b8;">
                                    <i class="fa fa-calendar-o fa-2x mb-2 d-block"></i>
                                    No check-ins recorded yet today. Members scanning the QR poster will appear here in real-time.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($todayCheckins as $row): 
                                $isOnFloor = empty($row['check_out_time']);
                                $isQr = ($row['check_in_method'] === 'qr_scan');
                            ?>
                                <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.05);">
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div style="width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #fff; font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                                                <?php echo strtoupper(substr($row['fname'], 0, 1) . substr($row['lname'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong style="color: #fff; font-size: 14px;"><?php echo htmlspecialchars($row['fname'] . ' ' . $row['lname']); ?></strong>
                                                <div style="font-size: 12px; color: #94a3b8;">ID: #<?php echo $row['user_id']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 13px; color: #e2e8f0;"><?php echo htmlspecialchars($row['mobile']); ?></div>
                                        <div style="font-size: 11.5px; color: #94a3b8;"><?php echo htmlspecialchars($row['email']); ?></div>
                                    </td>
                                    <td>
                                        <span style="font-weight: 600; color: #34d399;">
                                            <i class="fa fa-clock-o"></i> <?php echo date('h:i A', strtotime($row['check_in_time'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($isQr): ?>
                                            <span class="badge badge-success" style="font-size: 11px; padding: 4px 8px; border-radius: 8px;">
                                                <i class="fa fa-qrcode"></i> Poster QR Scan
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary" style="font-size: 11px; padding: 4px 8px; border-radius: 8px;">
                                                <?php echo htmlspecialchars($row['check_in_method']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isOnFloor): ?>
                                            <span class="badge badge-success" style="font-size: 11.5px; padding: 5px 10px; border-radius: 8px;">
                                                <span class="status-pulse" style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #fff; margin-right: 4px;"></span> On Floor
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-size: 12.5px;">
                                                <i class="fa fa-sign-out"></i> Out at <?php echo date('h:i A', strtotime($row['check_out_time'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Hidden canvas for high-definition 1200x1200px PNG composite download -->
    <canvas id="exportCanvas" width="1200" height="1500" style="display: none;"></canvas>

    <script src="js/jquery-3.2.1.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/main.js"></script>
    <script src="../js/qrcode.min.js"></script>

    <script>
        var qrCodeInstance = null;
        var checkinUrl = <?php echo json_encode($checkinUrl); ?>;

        function renderQrCode(url) {
            var container = document.getElementById('qrcode-display');
            if (!container) return;
            container.innerHTML = '';
            try {
                if (typeof QRCode !== 'undefined') {
                    qrCodeInstance = new QRCode(container, {
                        text: url,
                        width: 220,
                        height: 220,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.H
                    });
                } else {
                    container.innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(url) + '" width="220" height="220" alt="QR Code">';
                }
            } catch(e) {
                container.innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(url) + '" width="220" height="220" alt="QR Code">';
            }
        }

        function setBaseUrl(url) {
            document.getElementById('baseUrlInput').value = url;
            // Recalculate target active URL
            var token = <?php echo json_encode($token); ?>;
            var reqToken = <?php echo json_encode($requireToken); ?>;
            var gymId = 1;
            var newTarget = url + '/checkin.php?gym=' + gymId;
            if (reqToken !== '0' && reqToken !== 'no') {
                newTarget += '&token=' + encodeURIComponent(token);
            }
            document.getElementById('activeUrlField').value = newTarget;
            checkinUrl = newTarget;
            renderQrCode(newTarget);
        }

        function copyCheckinLink() {
            var input = document.getElementById('activeUrlField');
            input.select();
            input.setSelectionRange(0, 99999);
            if (navigator.clipboard) {
                navigator.clipboard.writeText(input.value).then(function() {
                    alert('Check-In Link copied to clipboard!\n' + input.value);
                });
            } else {
                document.execCommand('copy');
                alert('Check-In Link copied to clipboard!\n' + input.value);
            }
        }

        function printOfficialPoster() {
            // Open dedicated clean poster pop-up formatted for A4 print
            var printWin = window.open('gym-qr.php?poster_only=1&print=1', '_blank', 'width=800,height=900');
            if (!printWin) {
                // Fallback to local window print if popups blocked
                window.print();
            }
        }

        function downloadPosterPng() {
            var canvas = document.getElementById('exportCanvas');
            var ctx = canvas.getContext('2d');
            var w = 1200;
            var h = 1500;

            // Background
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, w, h);

            // Outer border
            ctx.lineWidth = 14;
            ctx.strokeStyle = '#0f172a';
            ctx.strokeRect(40, 40, w - 80, h - 80);

            // Header Pill Badge
            ctx.fillStyle = '#10b981';
            var pillX = (w - 400) / 2;
            var pillY = 90;
            roundRect(ctx, pillX, pillY, 400, 50, 25, true, false);

            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 22px -apple-system, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('⚡ FAST ATTENDANCE STATION', w / 2, pillY + 33);

            // Gym Name
            ctx.fillStyle = '#0f172a';
            ctx.font = '900 64px -apple-system, sans-serif';
            ctx.fillText(<?php echo json_encode($gymName); ?>, w / 2, 210);

            // Tagline
            ctx.fillStyle = '#64748b';
            ctx.font = '700 24px -apple-system, sans-serif';
            ctx.fillText(<?php echo json_encode($gymTagline); ?>, w / 2, 255);

            // Divider
            ctx.strokeStyle = '#e2e8f0';
            ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.moveTo(120, 290);
            ctx.lineTo(w - 120, 290);
            ctx.stroke();

            // QR Code Box
            var qrBoxSize = 540;
            var qrBoxX = (w - qrBoxSize) / 2;
            var qrBoxY = 330;
            ctx.fillStyle = '#ffffff';
            ctx.strokeStyle = '#0f172a';
            ctx.lineWidth = 8;
            roundRect(ctx, qrBoxX, qrBoxY, qrBoxSize, qrBoxSize, 36, true, true);

            // Draw QR onto canvas
            var qrImg = new Image();
            qrImg.crossOrigin = 'anonymous';
            qrImg.onload = function() {
                ctx.drawImage(qrImg, qrBoxX + 30, qrBoxY + 30, qrBoxSize - 60, qrBoxSize - 60);

                // Headline under QR
                ctx.fillStyle = '#0f172a';
                ctx.font = '900 52px -apple-system, sans-serif';
                ctx.fillText(<?php echo json_encode($posterTitle); ?>, w / 2, 950);

                ctx.fillStyle = '#475569';
                ctx.font = '500 26px -apple-system, sans-serif';
                ctx.fillText(<?php echo json_encode($posterSubtitle); ?>, w / 2, 1000);

                // 3 Step boxes
                var stepY = 1070;
                var stepCols = [260, 600, 940];
                var stepTitles = ['1. Open Camera', '2. Point at QR', '3. Checked In!'];
                var stepSubs = ['iPhone / Android', 'Tap browser banner', 'Attendance recorded'];

                for (var i = 0; i < 3; i++) {
                    ctx.fillStyle = '#0f172a';
                    ctx.font = 'bold 26px -apple-system, sans-serif';
                    ctx.fillText(stepTitles[i], stepCols[i], stepY);
                    ctx.fillStyle = '#64748b';
                    ctx.font = '500 20px -apple-system, sans-serif';
                    ctx.fillText(stepSubs[i], stepCols[i], stepY + 32);
                }

                // Wi-Fi Banner
                <?php if ($wifiEnabled && (!empty($wifiSsid) || !empty($wifiPass))): ?>
                    var wifiText = '📶 Member Wi-Fi: ' + <?php echo json_encode($wifiSsid); ?> + ' • Password: ' + <?php echo json_encode($wifiPass); ?>;
                    ctx.fillStyle = '#f1f5f9';
                    roundRect(ctx, 160, 1200, w - 320, 65, 18, true, false);
                    ctx.fillStyle = '#1e293b';
                    ctx.font = 'bold 24px -apple-system, sans-serif';
                    ctx.fillText(wifiText, w / 2, 1242);
                <?php endif; ?>

                // Footer
                ctx.fillStyle = '#94a3b8';
                ctx.font = '500 20px -apple-system, sans-serif';
                ctx.fillText(<?php echo json_encode($gymName . ' • ' . $contactAddress . ' • ' . $contactPhone); ?>, w / 2, 1400);

                // Trigger Download
                var link = document.createElement('a');
                link.download = 'FitForge-Gym-CheckIn-QR-Poster.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            };
            qrImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=480x480&margin=0&data=' + encodeURIComponent(checkinUrl);
        }

        function roundRect(ctx, x, y, width, height, radius, fill, stroke) {
            ctx.beginPath();
            ctx.moveTo(x + radius, y);
            ctx.lineTo(x + width - radius, y);
            ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
            ctx.lineTo(x + width, y + height - radius);
            ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
            ctx.lineTo(x + radius, y + height);
            ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
            ctx.lineTo(x, y + radius);
            ctx.quadraticCurveTo(x, y, x + radius, y);
            ctx.closePath();
            if (fill) ctx.fill();
            if (stroke) ctx.stroke();
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            renderQrCode(checkinUrl);
        });
    </script>
</body>
</html>