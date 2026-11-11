<?php
require_once 'include/config.php';

// Authentication Check: Member must be logged in to scan and record attendance
if (empty($_SESSION['uid'])) {
    header("location: login.php?redirect=scan.php");
    exit();
}

$userId = intval($_SESSION['uid']);
$gymName = get_setting('gym_name', 'Golden Eagle');
$gymTagline = get_setting('gym_tagline', 'FITNESS CLUB');

// Fetch user name
$uStmt = $dbh->prepare("SELECT fname, lname FROM tbluser WHERE id = :uid LIMIT 1");
$uStmt->execute([':uid' => $userId]);
$user = $uStmt->fetch(PDO::FETCH_ASSOC);
$memberName = $user ? ($user['fname'] . ' ' . $user['lname']) : 'Member';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>In-App QR Attendance Scanner — <?php echo htmlspecialchars($gymName); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <?php echo render_favicon_tags(); ?>
    <link rel="stylesheet" href="css/bootstrap.min.css"/>
    <link rel="stylesheet" href="css/font-awesome.min.css"/>
    <link rel="stylesheet" href="css/ios-glass.css"/>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body {
            background: radial-gradient(circle at 50% 10%, #1e1b4b 0%, #09090b 55%, #030712 100%);
            color: #f8fafc;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        .scanner-card {
            background: linear-gradient(145deg, rgba(15, 23, 42, 0.85) 0%, rgba(11, 15, 25, 0.95) 100%);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 22px;
            padding: 24px 20px;
            width: 100%;
            max-width: 440px;
            margin: 0 auto;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.45);
            text-align: center;
        }

        /* Camera Viewfinder Viewport */
        .camera-viewport-wrapper {
            position: relative;
            width: 100%;
            max-width: 320px;
            height: 320px;
            margin: 0 auto 20px;
            border-radius: 24px;
            overflow: hidden;
            background: #000;
            border: 2px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
        }

        #qr-reader {
            width: 100% !important;
            height: 100% !important;
            border: none !important;
        }

        #qr-reader video {
            object-fit: cover !important;
            width: 100% !important;
            height: 100% !important;
            border-radius: 22px;
        }

        /* Aiming Reticle Overlay */
        .scanner-reticle {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .reticle-frame {
            width: 220px;
            height: 220px;
            border: 2px solid rgba(16, 185, 129, 0.5);
            border-radius: 18px;
            position: relative;
            box-shadow: 0 0 0 2000px rgba(0, 0, 0, 0.35);
        }

        /* 4 Corner Accents */
        .reticle-corner {
            position: absolute;
            width: 22px;
            height: 22px;
            border-color: #10b981;
            border-style: solid;
        }
        .corner-tl { top: -2px; left: -2px; border-width: 4px 0 0 4px; border-top-left-radius: 12px; }
        .corner-tr { top: -2px; right: -2px; border-width: 4px 4px 0 0; border-top-right-radius: 12px; }
        .corner-bl { bottom: -2px; left: -2px; border-width: 0 0 4px 4px; border-bottom-left-radius: 12px; }
        .corner-br { bottom: -2px; right: -2px; border-width: 0 4px 4px 0; border-bottom-right-radius: 12px; }

        /* Animated Laser Scan Line */
        .laser-line {
            position: absolute;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, transparent, #10b981, #34d399, transparent);
            box-shadow: 0 0 14px #10b981;
            top: 0;
            animation: laserScan 2.2s ease-in-out infinite alternate;
        }

        @keyframes laserScan {
            0% { top: 5%; opacity: 0.2; }
            50% { opacity: 1; }
            100% { top: 92%; opacity: 0.2; }
        }

        /* Camera Control Toolbar */
        .scanner-toolbar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        .scanner-tool-btn {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #fff;
            padding: 10px 18px;
            border-radius: 14px;
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .scanner-tool-btn:hover {
            background: rgba(255, 255, 255, 0.16);
            border-color: rgba(255, 255, 255, 0.3);
        }

        /* Success Overlay */
        .scan-success-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(16, 185, 129, 0.95);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
            z-index: 50;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            border-radius: 22px;
        }
        .scan-success-overlay.show {
            opacity: 1;
            pointer-events: auto;
        }
    </style>
</head>
<body>

    <?php include 'include/header.php'; ?>

    <div class="site-content-wrapper" style="padding: 24px 16px; flex: 1; display: flex; align-items: center; justify-content: center;">
        <div class="scanner-card">
            
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                <a href="user-dashboard.php" class="ios-btn ios-btn-glass" style="padding: 6px 12px; font-size: 12.5px;">
                    <i class="fa fa-arrow-left"></i> Dashboard
                </a>
                <span class="ios-badge ios-badge-success" style="font-size: 11px; padding: 4px 10px;">
                    <span class="status-pulse"></span> Camera Active
                </span>
            </div>

            <h2 style="font-size: 22px; font-weight: 800; color: #fff; margin-bottom: 4px;">
                Scan Gym Attendance QR
            </h2>
            <p style="font-size: 13.5px; color: #94a3b8; line-height: 1.45; margin-bottom: 18px;">
                Point your camera at the printed QR poster at reception or gym entrance.
            </p>

            <!-- Camera Viewfinder -->
            <div class="camera-viewport-wrapper">
                <div id="qr-reader"></div>

                <div class="scanner-reticle" id="scannerReticle">
                    <div class="reticle-frame">
                        <div class="reticle-corner corner-tl"></div>
                        <div class="reticle-corner corner-tr"></div>
                        <div class="reticle-corner corner-bl"></div>
                        <div class="reticle-corner corner-br"></div>
                        <div class="laser-line"></div>
                    </div>
                </div>

                <!-- Instant Green Flash Overlay upon detection -->
                <div class="scan-success-overlay" id="successOverlay">
                    <div style="font-size: 48px; margin-bottom: 10px;"><i class="fa fa-check-circle"></i></div>
                    <div style="font-size: 20px; font-weight: 800;">QR Detected!</div>
                    <div style="font-size: 13px; opacity: 0.9; margin-top: 4px;">Logging attendance...</div>
                </div>
            </div>

            <!-- Scanner Toolbar Controls -->
            <div class="scanner-toolbar">
                <button type="button" class="scanner-tool-btn" id="btnSwitchCamera" onclick="switchCamera()">
                    <i class="fa fa-refresh"></i> Switch Lens
                </button>
                <button type="button" class="scanner-tool-btn" id="btnTorch" onclick="toggleTorch()">
                    <i class="fa fa-bolt"></i> Flashlight
                </button>
            </div>

            <!-- Permission Denied Fallback Card -->
            <div id="cameraErrorBox" style="display: none; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 16px; padding: 16px; text-align: left; margin-bottom: 16px;">
                <div style="font-weight: 700; color: #fca5a5; font-size: 14px; margin-bottom: 6px;">
                    <i class="fa fa-video-camera"></i> Camera Permission Needed
                </div>
                <div style="font-size: 12.5px; color: #cbd5e1; line-height: 1.5;">
                    Please allow camera access in your mobile browser settings to scan the gym poster directly from the app.
                </div>
                <button type="button" class="ios-btn ios-btn-primary" onclick="startScanner()" style="width: 100%; margin-top: 12px; font-size: 13px; justify-content: center;">
                    <i class="fa fa-refresh"></i> Retry Camera
                </button>
            </div>

            <div style="font-size: 12px; color: #64748b; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 14px;">
                Logged in as <strong><?php echo htmlspecialchars($memberName); ?></strong>
            </div>

        </div>
    </div>

    <?php include 'include/footer.php'; ?>

    <script>
        var html5QrCode = null;
        var currentFacingMode = "environment"; // default back camera
        var isTorchOn = false;
        var videoTrack = null;
        var isProcessing = false;

        function playScanChime() {
            try {
                var AudioContext = window.AudioContext || window.webkitAudioContext;
                if (AudioContext) {
                    var ctx = new AudioContext();
                    var osc = ctx.createOscillator();
                    var gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(659.25, ctx.currentTime); // E5
                    osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.15); // A5
                    gain.gain.setValueAtTime(0.12, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start();
                    osc.stop(ctx.currentTime + 0.25);
                }
                if (navigator.vibrate) {
                    navigator.vibrate([40, 60, 40]);
                }
            } catch(e) {}
        }

        function onScanSuccess(decodedText, decodedResult) {
            if (isProcessing) return;
            isProcessing = true;

            // Audio & Haptic celebration
            playScanChime();

            // Show green confirmation overlay
            var overlay = document.getElementById('successOverlay');
            if (overlay) overlay.classList.add('show');

            // Pause camera
            try {
                if (html5QrCode && html5QrCode.isScanning) {
                    html5QrCode.stop();
                }
            } catch(e) {}

            // Process scan payload
            // If full URL, navigate straight to checkin.php
            setTimeout(function() {
                var targetUrl = decodedText.trim();
                if (targetUrl.indexOf('checkin.php') !== -1) {
                    window.location.href = targetUrl;
                } else if (targetUrl.indexOf('http') === 0) {
                    window.location.href = targetUrl;
                } else {
                    // Raw token or payload
                    window.location.href = 'checkin.php?gym=1&token=' + encodeURIComponent(targetUrl);
                }
            }, 600);
        }

        function onScanFailure(error) {
            // Continuous scanning frame - ignore standard frame mismatch
        }

        function startScanner() {
            document.getElementById('cameraErrorBox').style.display = 'none';

            if (html5QrCode) {
                try { html5QrCode.stop(); } catch(e) {}
            }

            html5QrCode = new Html5Qrcode("qr-reader");

            var config = {
                fps: 15,
                qrbox: { width: 220, height: 220 },
                aspectRatio: 1.0,
                experimentalFeatures: {
                    useBarCodeDetectorIfSupported: true
                }
            };

            html5QrCode.start(
                { facingMode: currentFacingMode },
                config,
                onScanSuccess,
                onScanFailure
            ).then(function() {
                // Get active camera track for torch control
                try {
                    var stream = html5QrCode.getRunningTrackCameraCapabilities();
                } catch(e) {}
            }).catch(function(err) {
                console.warn("Camera init failed:", err);
                document.getElementById('cameraErrorBox').style.display = 'block';
            });
        }

        function switchCamera() {
            currentFacingMode = (currentFacingMode === "environment") ? "user" : "environment";
            startScanner();
        }

        function toggleTorch() {
            try {
                // Toggle video track torch capability if supported
                var videoElem = document.querySelector('#qr-reader video');
                if (videoElem && videoElem.srcObject) {
                    var tracks = videoElem.srcObject.getVideoTracks();
                    if (tracks.length > 0) {
                        var track = tracks[0];
                        isTorchOn = !isTorchOn;
                        track.applyConstraints({
                            advanced: [{ torch: isTorchOn }]
                        }).catch(function() {
                            alert("Flashlight not supported on this camera/device.");
                        });
                    }
                }
            } catch(e) {
                alert("Flashlight not supported on this browser.");
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            startScanner();
        });
    </script>
</body>
</html>