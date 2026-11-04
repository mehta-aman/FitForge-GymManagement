<?php
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = "Security token mismatch. Please try again.";
    } else {
        $primary = trim($_POST['theme_primary'] ?? '#38bdf8');
        $accent = trim($_POST['theme_accent'] ?? '#f97316');
        $mode = in_array($_POST['theme_mode'] ?? 'dark', ['dark', 'light']) ? $_POST['theme_mode'] : 'dark';
        $preset = trim($_POST['theme_preset'] ?? 'custom');

        $btnPrimaryBg = trim($_POST['btn_primary_bg'] ?? $primary);
        $btnPrimaryText = trim($_POST['btn_primary_text'] ?? '#ffffff');
        $btnSecondaryBg = trim($_POST['btn_secondary_bg'] ?? '#1e293b');
        $btnSecondaryText = trim($_POST['btn_secondary_text'] ?? '#ffffff');
        $btnSecondaryBorder = trim($_POST['btn_secondary_border'] ?? '#475569');

        set_setting('theme_primary', $primary, 'theme');
        set_setting('theme_accent', $accent, 'theme');
        set_setting('theme_mode', $mode, 'theme');
        set_setting('theme_preset', $preset, 'theme');

        set_setting('btn_primary_bg', $btnPrimaryBg, 'theme');
        set_setting('btn_primary_text', $btnPrimaryText, 'theme');
        set_setting('btn_secondary_bg', $btnSecondaryBg, 'theme');
        set_setting('btn_secondary_text', $btnSecondaryText, 'theme');
        set_setting('btn_secondary_border', $btnSecondaryBorder, 'theme');

        $msg = "Theme & Button styles saved successfully! Primary and secondary buttons are now updated across both public website and admin portal.";
    }
}

$curPrimary = get_setting('theme_primary', '#38bdf8');
$curAccent = get_setting('theme_accent', '#f97316');
$curMode = get_setting('theme_mode', 'dark');
$curPreset = get_setting('theme_preset', 'midnight_cyan');

$curBtnPrimaryBg = get_setting('btn_primary_bg', $curPrimary);
$curBtnPrimaryText = get_setting('btn_primary_text', '#ffffff');
$curBtnSecondaryBg = get_setting('btn_secondary_bg', '#1e293b');
$curBtnSecondaryText = get_setting('btn_secondary_text', '#ffffff');
$curBtnSecondaryBorder = get_setting('btn_secondary_border', '#475569');

$presets = [
    'midnight_cyan' => [
        'name' => 'Midnight Cyan',
        'desc' => 'Cyber cyan highlights with deep slate secondary buttons',
        'primary' => '#38bdf8',
        'accent' => '#f97316',
        'btn_primary_bg' => '#38bdf8',
        'btn_primary_text' => '#ffffff',
        'btn_secondary_bg' => '#1e293b',
        'btn_secondary_text' => '#f1f5f9',
        'btn_secondary_border' => '#334155'
    ],
    'neon_emerald' => [
        'name' => 'Neon Emerald',
        'desc' => 'Electric mint energy with dark forest glass secondary buttons',
        'primary' => '#10b981',
        'accent' => '#06b6d4',
        'btn_primary_bg' => '#10b981',
        'btn_primary_text' => '#ffffff',
        'btn_secondary_bg' => '#064e3b',
        'btn_secondary_text' => '#ecfdf5',
        'btn_secondary_border' => '#047857'
    ],
    'sunset_coral' => [
        'name' => 'Sunset Coral',
        'desc' => 'Crimson rose vibrancy with deep wine secondary buttons',
        'primary' => '#f43f5e',
        'accent' => '#fbbf24',
        'btn_primary_bg' => '#f43f5e',
        'btn_primary_text' => '#ffffff',
        'btn_secondary_bg' => '#2d1a24',
        'btn_secondary_text' => '#fff1f2',
        'btn_secondary_border' => '#881337'
    ],
    'deep_purple' => [
        'name' => 'Deep Royal',
        'desc' => 'Regal purple glow with midnight indigo secondary buttons',
        'primary' => '#8b5cf6',
        'accent' => '#ec4899',
        'btn_primary_bg' => '#8b5cf6',
        'btn_primary_text' => '#ffffff',
        'btn_secondary_bg' => '#1e1b4b',
        'btn_secondary_text' => '#faf5ff',
        'btn_secondary_border' => '#4c1d95'
    ],
    'electric_amber' => [
        'name' => 'Electric Amber',
        'desc' => 'High-voltage amber with dark cobalt secondary buttons',
        'primary' => '#f59e0b',
        'accent' => '#3b82f6',
        'btn_primary_bg' => '#f59e0b',
        'btn_primary_text' => '#000000',
        'btn_secondary_bg' => '#1e293b',
        'btn_secondary_text' => '#fef3c7',
        'btn_secondary_border' => '#78350f'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Theme & Button Customizer — Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php echo render_favicon_tags(); ?>
  <link rel="stylesheet" type="text/css" href="css/main.css">
  <link rel="stylesheet" type="text/css" href="css/ios-admin-glass.css">
  <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
  <style>
    .palette-card {
      border: 2px solid var(--glass-border);
      background: rgba(255, 255, 255, 0.03);
      border-radius: 18px;
      padding: 16px;
      cursor: pointer;
      transition: all 0.25s ease;
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .palette-card:hover {
      transform: translateY(-2px);
      border-color: rgba(255,255,255,0.3);
      background: rgba(255, 255, 255, 0.06);
    }
    .palette-card.active {
      border-color: var(--color-primary);
      box-shadow: 0 0 0 1px var(--color-primary), 0 8px 24px rgba(0,0,0,0.3);
      background: rgba(255, 255, 255, 0.08);
    }
    .color-swatch-pair {
      display: flex;
      width: 44px;
      height: 44px;
      border-radius: 12px;
      overflow: hidden;
      flex-shrink: 0;
      box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    }
    .swatch-half {
      width: 50%;
      height: 100%;
    }
    .live-preview-box {
      border-radius: 24px;
      padding: 24px;
      background: rgba(18, 24, 38, 0.85);
      backdrop-filter: blur(24px);
      border: 1px solid rgba(255, 255, 255, 0.15);
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
    }
    .color-input-group {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .color-input-group input[type="color"] {
      width: 48px;
      height: 42px;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      background: transparent;
      padding: 0;
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
        <h1><i class="fa fa-paint-brush" style="color: var(--color-primary); margin-right: 8px;"></i> Theme & Button Customizer</h1>
        <p>Complete control over brand theme colors, primary buttons, and secondary buttons across the entire website and admin panel</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Theme Customizer</li>
      </ul>
    </div>

    <?php if ($msg): ?>
      <div class="ios-badge ios-badge-success" style="display: flex; margin-bottom: 24px; padding: 14px 20px; border-radius: 16px; font-size: 14px;">
        <i class="fa fa-check-circle" style="font-size: 18px; margin-right: 8px;"></i>
        <span><?php echo htmlentities($msg); ?></span>
      </div>
    <?php endif; ?>

    <?php if ($err): ?>
      <div class="ios-badge ios-badge-danger" style="display: flex; margin-bottom: 24px; padding: 14px 20px; border-radius: 16px; font-size: 14px;">
        <i class="fa fa-exclamation-triangle" style="font-size: 18px; margin-right: 8px;"></i>
        <span><?php echo htmlentities($err); ?></span>
      </div>
    <?php endif; ?>

    <form method="post" id="themeForm">
      <?php echo csrf_input(); ?>
      <input type="hidden" name="theme_preset" id="presetInput" value="<?php echo htmlspecialchars($curPreset); ?>">

      <div class="row">
        <!-- Palette Presets & Controls Column -->
        <div class="col-lg-7">
          <!-- Curated Palettes -->
          <div class="tile">
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 16px;">
              <i class="fa fa-th-large" style="color: var(--color-primary); margin-right: 6px;"></i> Curated Themes & Button Presets
            </h3>
            <p style="color: var(--text-muted); font-size: 13.5px; margin-bottom: 20px;">
              Selecting any palette below will automatically configure high-contrast matching colors for texts, primary buttons, and secondary buttons:
            </p>

            <div style="display: flex; flex-direction: column; gap: 12px;">
              <?php foreach ($presets as $pKey => $p): 
                $isActive = ($curPreset === $pKey);
              ?>
                <div class="palette-card <?php echo $isActive ? 'active' : ''; ?>" 
                     data-key="<?php echo $pKey; ?>" 
                     data-primary="<?php echo $p['primary']; ?>" 
                     data-accent="<?php echo $p['accent']; ?>"
                     data-btn-p-bg="<?php echo $p['btn_primary_bg']; ?>"
                     data-btn-p-text="<?php echo $p['btn_primary_text']; ?>"
                     data-btn-s-bg="<?php echo $p['btn_secondary_bg']; ?>"
                     data-btn-s-text="<?php echo $p['btn_secondary_text']; ?>"
                     data-btn-s-border="<?php echo $p['btn_secondary_border']; ?>">
                  <div class="color-swatch-pair">
                    <div class="swatch-half" style="background: <?php echo $p['btn_primary_bg']; ?>;"></div>
                    <div class="swatch-half" style="background: <?php echo $p['btn_secondary_bg']; ?>;"></div>
                  </div>
                  <div style="flex-grow: 1;">
                    <div style="font-weight: 700; color: #fff; font-size: 14.5px;"><?php echo $p['name']; ?></div>
                    <div style="font-size: 12.5px; color: var(--text-muted);"><?php echo $p['desc']; ?></div>
                  </div>
                  <div style="display: flex; gap: 6px; align-items: center;">
                    <span class="badge" style="background: <?php echo $p['btn_primary_bg']; ?>; color: <?php echo $p['btn_primary_text']; ?>; font-size: 11px; padding: 4px 8px; border-radius: 6px;">Primary</span>
                    <span class="badge" style="background: <?php echo $p['btn_secondary_bg']; ?>; color: <?php echo $p['btn_secondary_text']; ?>; border: 1px solid <?php echo $p['btn_secondary_border']; ?>; font-size: 11px; padding: 4px 8px; border-radius: 6px;">Secondary</span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Primary Button Controls -->
          <div class="tile" style="border: 1px solid rgba(56, 189, 248, 0.25);">
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 8px;">
              <i class="fa fa-hand-pointer-o text-primary"></i> Primary Button Styling
            </h3>
            <p style="color: var(--text-muted); font-size: 13.5px; margin-bottom: 20px;">
              Controls major call-to-actions, booking buttons, checkout CTAs, and submit buttons (<code>.btn-primary</code>, <code>.ios-btn-primary</code>, <code>.ios-btn-accent</code>).
            </p>

            <div class="row">
              <div class="col-md-6 form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Primary Button Color / Fill</label>
                <div class="color-input-group">
                  <input type="color" id="btnPrimaryBgPicker" name="btn_primary_bg" value="<?php echo htmlspecialchars($curBtnPrimaryBg); ?>">
                  <input type="text" id="btnPrimaryBgText" class="form-control" value="<?php echo htmlspecialchars($curBtnPrimaryBg); ?>" style="font-family: monospace; font-weight: 600;">
                </div>
                <small class="form-text text-muted">Primary button gradient start & glow.</small>
              </div>

              <div class="col-md-6 form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Primary Button Text Color</label>
                <div class="color-input-group">
                  <input type="color" id="btnPrimaryTextPicker" name="btn_primary_text" value="<?php echo htmlspecialchars($curBtnPrimaryText); ?>">
                  <input type="text" id="btnPrimaryTextText" class="form-control" value="<?php echo htmlspecialchars($curBtnPrimaryText); ?>" style="font-family: monospace; font-weight: 600;">
                </div>
                <small class="form-text text-muted">TextColor for primary buttons (e.g. #ffffff).</small>
              </div>
            </div>
          </div>

          <!-- Secondary Button Controls -->
          <div class="tile" style="border: 1px solid rgba(255, 255, 255, 0.2);">
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 8px;">
              <i class="fa fa-square-o text-secondary"></i> Secondary Button Styling
            </h3>
            <p style="color: var(--text-muted); font-size: 13.5px; margin-bottom: 20px;">
              Controls secondary actions, outline buttons, cancel buttons, and export buttons (<code>.btn-secondary</code>, <code>.btn-outline-light</code>, <code>.ios-btn-glass</code>).
            </p>

            <div class="row">
              <div class="col-md-4 form-group">
                <label style="font-weight: 600; font-size: 13px;">Background Fill</label>
                <div class="color-input-group">
                  <input type="color" id="btnSecondaryBgPicker" name="btn_secondary_bg" value="<?php echo htmlspecialchars($curBtnSecondaryBg); ?>">
                  <input type="text" id="btnSecondaryBgText" class="form-control" value="<?php echo htmlspecialchars($curBtnSecondaryBg); ?>" style="font-family: monospace; font-size: 13px;">
                </div>
              </div>

              <div class="col-md-4 form-group">
                <label style="font-weight: 600; font-size: 13px;">Text Color</label>
                <div class="color-input-group">
                  <input type="color" id="btnSecondaryTextPicker" name="btn_secondary_text" value="<?php echo htmlspecialchars($curBtnSecondaryText); ?>">
                  <input type="text" id="btnSecondaryTextText" class="form-control" value="<?php echo htmlspecialchars($curBtnSecondaryText); ?>" style="font-family: monospace; font-size: 13px;">
                </div>
              </div>

              <div class="col-md-4 form-group">
                <label style="font-weight: 600; font-size: 13px;">Border Line Color</label>
                <div class="color-input-group">
                  <input type="color" id="btnSecondaryBorderPicker" name="btn_secondary_border" value="<?php echo htmlspecialchars($curBtnSecondaryBorder); ?>">
                  <input type="text" id="btnSecondaryBorderText" class="form-control" value="<?php echo htmlspecialchars($curBtnSecondaryBorder); ?>" style="font-family: monospace; font-size: 13px;">
                </div>
              </div>
            </div>
          </div>

          <!-- Brand Accents & Theme Mode -->
          <div class="tile">
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 16px;">
              <i class="fa fa-sliders" style="color: var(--color-accent); margin-right: 6px;"></i> Brand Glow & Canvas Mode
            </h3>

            <div class="row">
              <div class="col-md-6 form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Brand Highlight & Heading Glow</label>
                <div class="color-input-group">
                  <input type="color" id="primaryColorPicker" name="theme_primary" value="<?php echo htmlspecialchars($curPrimary); ?>">
                  <input type="text" id="primaryColorText" class="form-control" value="<?php echo htmlspecialchars($curPrimary); ?>" style="font-family: monospace; font-weight: 600;">
                </div>
                <small class="form-text text-muted">Applied to badges, active nav pills, and titles.</small>
              </div>

              <div class="col-md-6 form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Gradient Pulse Accent</label>
                <div class="color-input-group">
                  <input type="color" id="accentColorPicker" name="theme_accent" value="<?php echo htmlspecialchars($curAccent); ?>">
                  <input type="text" id="accentColorText" class="form-control" value="<?php echo htmlspecialchars($curAccent); ?>" style="font-family: monospace; font-weight: 600;">
                </div>
                <small class="form-text text-muted">Blended with primary for multi-stop glowing gradients.</small>
              </div>
            </div>

            <div class="form-group" style="margin-top: 10px; margin-bottom: 0;">
              <label style="font-weight: 600; font-size: 13.5px;">Interface Canvas Mode</label>
              <div class="row" style="gap: 12px; margin: 0;">
                <label class="col" style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 14px; padding: 14px; cursor: pointer; display: flex; align-items: center; gap: 10px;">
                  <input type="radio" name="theme_mode" value="dark" <?php echo ($curMode === 'dark') ? 'checked' : ''; ?>>
                  <div>
                    <div style="font-weight: 700; color: #fff;">Dark Glass (Default)</div>
                    <div style="font-size: 12px; color: var(--text-muted);">Midnight sapphire backdrop with frosted specular glass</div>
                  </div>
                </label>
                <label class="col" style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 14px; padding: 14px; cursor: pointer; display: flex; align-items: center; gap: 10px;">
                  <input type="radio" name="theme_mode" value="light" <?php echo ($curMode === 'light') ? 'checked' : ''; ?>>
                  <div>
                    <div style="font-weight: 700; color: #fff;">Light Glass</div>
                    <div style="font-size: 12px; color: var(--text-muted);">Clean slate with crystalline light frosted surfaces</div>
                  </div>
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Live Preview Sidebar Column -->
        <div class="col-lg-5">
          <div class="tile" style="position: sticky; top: 80px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
              <h3 style="font-size: 18px; font-weight: 700; margin: 0;">
                <i class="fa fa-eye" style="color: var(--color-primary); margin-right: 6px;"></i> Live Component Preview
              </h3>
              <span class="ios-badge ios-badge-info" style="font-size: 11px;">Real-Time Render</span>
            </div>

            <!-- Preview Box -->
            <div class="live-preview-box" id="previewContainer">
              <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                  <div id="previewLogoBadge" style="width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(135deg, <?php echo $curPrimary; ?>, <?php echo $curAccent; ?>); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 16px;">
                    <i class="fa fa-bolt"></i>
                  </div>
                  <div>
                    <strong style="color: #fff; font-size: 15px; display: block; line-height: 1.1;">GYM MS</strong>
                    <span id="previewTagline" style="font-size: 10px; color: <?php echo $curPrimary; ?>; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;">FITNESS CLUB</span>
                  </div>
                </div>
                <span id="previewBadge" style="font-size: 11px; padding: 4px 10px; border-radius: 20px; font-weight: 600; background: <?php echo $curPrimary; ?>22; color: <?php echo $curPrimary; ?>; border: 1px solid <?php echo $curPrimary; ?>44;">
                  PRO MEMBER
                </span>
              </div>

              <!-- Membership Sample Card -->
              <div style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 18px; padding: 18px; margin-bottom: 20px;">
                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">Selected Membership</div>
                <div style="font-size: 20px; font-weight: 800; color: #fff; margin: 4px 0;">All-Access Quarterly Pass</div>
                <div style="font-size: 24px; font-weight: 800; margin-top: 6px;" id="previewPriceText" style="color: <?php echo $curPrimary; ?>;">
                  ₹4,999 <span style="font-size: 13px; font-weight: normal; color: var(--text-secondary);">/ 3 Months</span>
                </div>
              </div>

              <!-- Live Buttons Demonstration -->
              <div style="margin-bottom: 12px;">
                <div style="font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 8px;">PRIMARY BUTTON:</div>
                <button type="button" id="previewPrimaryBtn" class="btn btn-block" style="background: <?php echo $curBtnPrimaryBg; ?>; background-image: linear-gradient(135deg, <?php echo $curBtnPrimaryBg; ?> 0%, <?php echo $curAccent; ?> 100%); color: <?php echo $curBtnPrimaryText; ?>; font-weight: 700; border: none; border-radius: 14px; padding: 12px; box-shadow: 0 6px 18px <?php echo $curBtnPrimaryBg; ?>55; font-size: 14.5px;">
                  <i class="fa fa-ticket"></i> Book VIP Membership
                </button>
              </div>

              <div>
                <div style="font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 8px;">SECONDARY BUTTON:</div>
                <button type="button" id="previewSecondaryBtn" class="btn btn-block" style="background: <?php echo $curBtnSecondaryBg; ?>; color: <?php echo $curBtnSecondaryText; ?>; border: 1px solid <?php echo $curBtnSecondaryBorder; ?>; font-weight: 600; border-radius: 14px; padding: 12px; font-size: 14.5px;">
                  <i class="fa fa-calendar"></i> View Class Schedule
                </button>
              </div>
            </div>

            <hr style="border-color: var(--glass-border); margin: 24px 0;">

            <button type="submit" class="ios-btn ios-btn-accent btn-block" style="padding: 13px; font-size: 15px; border-radius: 14px;">
              <i class="fa fa-check"></i> Save & Apply Across Entire System
            </button>
            <a href="index.php" class="btn btn-outline-light btn-block" style="border-radius: 14px; padding: 10px; margin-top: 10px;">
              Cancel
            </a>
          </div>
        </div>
      </div>
    </form>
  </main>

  <script src="js/jquery-3.2.1.min.js"></script>
  <script src="js/popper.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/main.js"></script>
  <script>
    function updateLivePreview() {
      var primary = $('#primaryColorPicker').val();
      var accent = $('#accentColorPicker').val();

      var btnPBg = $('#btnPrimaryBgPicker').val();
      var btnPText = $('#btnPrimaryTextPicker').val();

      var btnSBg = $('#btnSecondaryBgPicker').val();
      var btnSText = $('#btnSecondaryTextPicker').val();
      var btnSBorder = $('#btnSecondaryBorderPicker').val();

      // Update Top Badge & Logo
      $('#previewLogoBadge').css('background', 'linear-gradient(135deg, ' + primary + ', ' + accent + ')');
      $('#previewTagline').css('color', primary);
      $('#previewBadge').css({
        'background': primary + '22',
        'color': primary,
        'border': '1px solid ' + primary + '44'
      });
      $('#previewPriceText').css('color', primary);

      // Update Primary Button
      $('#previewPrimaryBtn').css({
        'background': btnPBg,
        'background-image': 'linear-gradient(135deg, ' + btnPBg + ' 0%, ' + accent + ' 100%)',
        'color': btnPText,
        'box-shadow': '0 8px 20px ' + btnPBg + '55'
      });

      // Update Secondary Button
      $('#previewSecondaryBtn').css({
        'background': btnSBg,
        'color': btnSText,
        'border': '1px solid ' + btnSBorder
      });
    }

    // Preset click
    $('.palette-card').on('click', function() {
      $('.palette-card').removeClass('active');
      $(this).addClass('active');

      var key = $(this).data('key');
      var p = $(this).data('primary');
      var a = $(this).data('accent');
      var btnPBg = $(this).data('btn-p-bg');
      var btnPText = $(this).data('btn-p-text');
      var btnSBg = $(this).data('btn-s-bg');
      var btnSText = $(this).data('btn-s-text');
      var btnSBorder = $(this).data('btn-s-border');

      $('#presetInput').val(key);

      $('#primaryColorPicker').val(p);
      $('#primaryColorText').val(p);
      $('#accentColorPicker').val(a);
      $('#accentColorText').val(a);

      $('#btnPrimaryBgPicker').val(btnPBg);
      $('#btnPrimaryBgText').val(btnPBg);
      $('#btnPrimaryTextPicker').val(btnPText);
      $('#btnPrimaryTextText').val(btnPText);

      $('#btnSecondaryBgPicker').val(btnSBg);
      $('#btnSecondaryBgText').val(btnSBg);
      $('#btnSecondaryTextPicker').val(btnSText);
      $('#btnSecondaryTextText').val(btnSText);
      $('#btnSecondaryBorderPicker').val(btnSBorder);
      $('#btnSecondaryBorderText').val(btnSBorder);

      updateLivePreview();
    });

    // Helper to wire pickers to text inputs
    function bindColorSync(pickerId, textId) {
      $('#' + pickerId).on('input', function() {
        $('#' + textId).val($(this).val());
        $('#presetInput').val('custom');
        $('.palette-card').removeClass('active');
        updateLivePreview();
      });

      $('#' + textId).on('change', function() {
        var val = $(this).val();
        if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
          $('#' + pickerId).val(val);
          $('#presetInput').val('custom');
          $('.palette-card').removeClass('active');
          updateLivePreview();
        }
      });
    }

    bindColorSync('primaryColorPicker', 'primaryColorText');
    bindColorSync('accentColorPicker', 'accentColorText');
    bindColorSync('btnPrimaryBgPicker', 'btnPrimaryBgText');
    bindColorSync('btnPrimaryTextPicker', 'btnPrimaryTextText');
    bindColorSync('btnSecondaryBgPicker', 'btnSecondaryBgText');
    bindColorSync('btnSecondaryTextPicker', 'btnSecondaryTextText');
    bindColorSync('btnSecondaryBorderPicker', 'btnSecondaryBorderText');

    // Initial update
    updateLivePreview();
  </script>
</body>
</html>
