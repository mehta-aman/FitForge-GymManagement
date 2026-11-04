<?php
$adminGymName = function_exists('get_setting') ? get_setting('gym_name', 'GYM MS') : 'GYM MS';
$adminPrimary = function_exists('get_setting') ? get_setting('theme_primary', '#38bdf8') : '#38bdf8';
$adminAccent = function_exists('get_setting') ? get_setting('theme_accent', '#f97316') : '#f97316';

$adminBtnPrimaryBg = function_exists('get_setting') ? get_setting('btn_primary_bg', $adminPrimary) : $adminPrimary;
$adminBtnPrimaryText = function_exists('get_setting') ? get_setting('btn_primary_text', '#ffffff') : '#ffffff';
$adminBtnSecondaryBg = function_exists('get_setting') ? get_setting('btn_secondary_bg', '#1e293b') : '#1e293b';
$adminBtnSecondaryText = function_exists('get_setting') ? get_setting('btn_secondary_text', '#ffffff') : '#ffffff';
$adminBtnSecondaryBorder = function_exists('get_setting') ? get_setting('btn_secondary_border', '#475569') : '#475569';
$adminLogoUrl = function_exists('get_logo_url') ? get_logo_url() : '';
?>
<!-- Dynamic Admin Theme Variables -->
<style>
:root {
  --color-primary: <?php echo htmlspecialchars($adminPrimary, ENT_QUOTES, 'UTF-8'); ?>;
  --color-primary-glow: <?php echo htmlspecialchars($adminPrimary, ENT_QUOTES, 'UTF-8'); ?>44;
  --color-accent: <?php echo htmlspecialchars($adminAccent, ENT_QUOTES, 'UTF-8'); ?>;
  --color-accent-glow: <?php echo htmlspecialchars($adminAccent, ENT_QUOTES, 'UTF-8'); ?>44;

  --btn-primary-bg: <?php echo htmlspecialchars($adminBtnPrimaryBg, ENT_QUOTES, 'UTF-8'); ?>;
  --btn-primary-text: <?php echo htmlspecialchars($adminBtnPrimaryText, ENT_QUOTES, 'UTF-8'); ?>;
  --btn-secondary-bg: <?php echo htmlspecialchars($adminBtnSecondaryBg, ENT_QUOTES, 'UTF-8'); ?>;
  --btn-secondary-text: <?php echo htmlspecialchars($adminBtnSecondaryText, ENT_QUOTES, 'UTF-8'); ?>;
  --btn-secondary-border: <?php echo htmlspecialchars($adminBtnSecondaryBorder, ENT_QUOTES, 'UTF-8'); ?>;
}

/* Hide default emoji pseudo-element */
.app-header__logo::before {
  display: none !important;
}

.app-header__logo {
  display: flex !important;
  align-items: center !important;
  gap: 12px !important;
  text-decoration: none !important;
}

.app-header__logo-img {
  max-height: 40px;
  max-width: 140px;
  object-fit: contain;
  border-radius: 8px;
}

/* Dynamic Primary Buttons in Admin */
.btn-primary,
.ios-btn-primary,
.ios-btn-accent,
input[type="submit"].btn-primary,
button.btn-primary {
  background: var(--btn-primary-bg) !important;
  background-image: linear-gradient(135deg, var(--btn-primary-bg) 0%, var(--color-accent) 100%) !important;
  color: var(--btn-primary-text) !important;
  border: none !important;
  box-shadow: 0 8px 18px <?php echo htmlspecialchars($adminBtnPrimaryBg, ENT_QUOTES, 'UTF-8'); ?>55 !important;
}

.btn-primary:hover,
.ios-btn-primary:hover,
.ios-btn-accent:hover {
  box-shadow: 0 14px 26px <?php echo htmlspecialchars($adminBtnPrimaryBg, ENT_QUOTES, 'UTF-8'); ?>77 !important;
  color: var(--btn-primary-text) !important;
  transform: translateY(-2px) scale(1.01) !important;
}

.btn-primary:active,
.ios-btn-primary:active,
.ios-btn-accent:active {
  transform: scale(0.97) !important;
}

/* Dynamic Secondary Buttons in Admin */
.btn-secondary,
.btn-outline-light,
.ios-btn-glass,
.ios-btn-secondary {
  background: var(--btn-secondary-bg) !important;
  color: var(--btn-secondary-text) !important;
  border: 1px solid var(--btn-secondary-border) !important;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25) !important;
}

.btn-secondary:hover,
.btn-outline-light:hover,
.ios-btn-glass:hover,
.ios-btn-secondary:hover {
  background: var(--btn-secondary-border) !important;
  color: var(--btn-secondary-text) !important;
  border-color: var(--btn-secondary-border) !important;
  transform: translateY(-2px) !important;
}

.btn-secondary:active,
.btn-outline-light:active,
.ios-btn-glass:active,
.ios-btn-secondary:active {
  transform: scale(0.97) !important;
}
</style>

<!-- Ambient Background Canvas for Admin -->
<div class="ambient-glow">
  <div class="ambient-orb ambient-orb-1" style="width: 500px; height: 500px; background: radial-gradient(circle, var(--color-primary) 0%, rgba(56,189,248,0) 70%); top: -150px; right: 10%;"></div>
  <div class="ambient-orb ambient-orb-2" style="width: 600px; height: 600px; background: radial-gradient(circle, var(--color-accent) 0%, rgba(99,102,241,0) 70%); bottom: 0; left: 10%;"></div>
</div>

<header class="app-header">
  <a class="app-header__logo" href="index.php">
    <?php if (!empty($adminLogoUrl)): ?>
      <img src="<?php echo htmlspecialchars($adminLogoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" class="app-header__logo-img">
    <?php else: ?>
      <div class="ios-logo-badge" style="width: 32px; height: 32px; border-radius: 8px; background: linear-gradient(135deg, var(--color-primary), var(--color-accent)); display: inline-flex; align-items: center; justify-content: center; font-size: 15px; color: #fff;">
        <i class="fa fa-bolt"></i>
      </div>
    <?php endif; ?>
    <span style="font-weight: 800; font-size: 18px; letter-spacing: -0.02em; color: #fff;"><?php echo htmlspecialchars($adminGymName, ENT_QUOTES, 'UTF-8'); ?></span>
  </a>
  <a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Toggle Sidebar">
    <i class="fa fa-bars"></i>
  </a>

  <ul class="app-nav" style="margin: 0; padding: 0; display: flex; align-items: center; gap: 10px;">
    <?php
    $topExpiring = isset($dbh) ? intval($dbh->query("SELECT count(*) FROM tbl_memberships WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn()) : 0;
    $topDues = isset($dbh) ? intval($dbh->query("SELECT count(DISTINCT b.id) FROM tblbooking b JOIN tbladdpackage p ON b.package_id = p.id WHERE (SELECT COALESCE(SUM(pay.payment), 0) FROM tblpayment pay WHERE pay.bookingID = b.id) < (CAST(p.Price AS DECIMAL(10,2)) + CAST(COALESCE(p.registration_fee,0) AS DECIMAL(10,2)) - CAST(COALESCE(p.discount,0) AS DECIMAL(10,2)))")->fetchColumn()) : 0;
    $topFloor = isset($dbh) ? intval($dbh->query("SELECT count(*) FROM tbl_attendance WHERE DATE(check_in_time) = CURDATE() AND check_out_time IS NULL")->fetchColumn()) : 0;
    $totalTopAlerts = $topExpiring + $topDues;
    ?>
    <!-- Live Floor Pill -->
    <li class="d-none d-md-block">
      <a href="attendance.php" class="app-nav__item" style="padding: 6px 14px; border-radius: 20px; background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.3); font-size: 12px; color: #34d399; font-weight: 700; text-decoration: none;">
        <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10b981; margin-right: 6px; box-shadow: 0 0 8px #10b981;"></span>
        <?php echo $topFloor; ?> On Floor
      </a>
    </li>

    <!-- Notification Bell Dropdown -->
    <li class="dropdown">
      <a class="app-nav__item" href="#" data-toggle="dropdown" aria-label="Show Notifications" style="position: relative;">
        <i class="fa fa-bell-o" style="font-size: 16px;"></i>
        <?php if ($totalTopAlerts > 0): ?>
          <span style="position: absolute; top: 8px; right: 8px; width: 8px; height: 8px; background: #ef4444; border-radius: 50%; box-shadow: 0 0 6px #ef4444;"></span>
        <?php endif; ?>
      </a>
      <ul class="dropdown-menu settings-menu dropdown-menu-right" style="min-width: 280px; padding: 12px; background: #181920; border: 1px solid rgba(255,255,255,0.15); border-radius: 16px;">
        <li style="padding: 6px 10px; font-weight: 700; color: #fff; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: space-between;">
          <span>Operational Alerts</span>
          <span class="ios-badge ios-badge-danger" style="font-size: 10px;"><?php echo $totalTopAlerts; ?> Pending</span>
        </li>
        <li style="margin-top: 6px;">
          <a class="dropdown-item" href="report-expirations.php?range=7" style="border-radius: 8px; font-size: 12.5px; padding: 8px 10px; display: flex; align-items: center; gap: 10px;">
            <i class="fa fa-clock-o text-warning" style="font-size: 16px;"></i>
            <div>
              <div style="font-weight: 600; color: #fff;"><?php echo $topExpiring; ?> Expiring Soon (7d)</div>
              <div style="font-size: 11px; color: var(--text-muted);">Send WhatsApp renewals</div>
            </div>
          </a>
        </li>
        <li>
          <a class="dropdown-item" href="report-dues.php" style="border-radius: 8px; font-size: 12.5px; padding: 8px 10px; display: flex; align-items: center; gap: 10px;">
            <i class="fa fa-money text-danger" style="font-size: 16px;"></i>
            <div>
              <div style="font-weight: 600; color: #fff;"><?php echo $topDues; ?> Dues Pending</div>
              <div style="font-size: 11px; color: var(--text-muted);">Collect member balances</div>
            </div>
          </a>
        </li>
        <li>
          <a class="dropdown-item" href="attendance.php" style="border-radius: 8px; font-size: 12.5px; padding: 8px 10px; display: flex; align-items: center; gap: 10px;">
            <i class="fa fa-qrcode text-success" style="font-size: 16px;"></i>
            <div>
              <div style="font-weight: 600; color: #fff;">Attendance Check-in</div>
              <div style="font-size: 11px; color: var(--text-muted);"><?php echo $topFloor; ?> currently training</div>
            </div>
          </a>
        </li>
      </ul>
    </li>

    <li class="dropdown">
      <a class="app-nav__item" href="#" data-toggle="dropdown" aria-label="Open Profile Menu">
        <i class="fa fa-shield" style="color: var(--color-primary);"></i>
        <span><?php echo !empty($_SESSION['name']) ? htmlentities($_SESSION['name']) : 'Admin'; ?></span>
        <i class="fa fa-angle-down" style="font-size: 12px; margin-left: 4px;"></i>
      </a>
      <ul class="dropdown-menu settings-menu dropdown-menu-right">
        <li><a class="dropdown-item" href="profile.php"><i class="fa fa-user fa-lg"></i> Admin Profile</a></li>
        <li><a class="dropdown-item" href="change-password.php"><i class="fa fa-key fa-lg"></i> Change Password</a></li>
        <li><a class="dropdown-item" href="../index.php" target="_blank"><i class="fa fa-external-link fa-lg"></i> Public Portal</a></li>
        <li style="border-top: 1px solid rgba(255,255,255,0.08); margin: 4px 0;"></li>
        <li><a class="dropdown-item" href="logout.php" style="color: #f87171 !important;"><i class="fa fa-sign-out fa-lg"></i> Sign Out</a></li>
      </ul>
    </li>
  </ul>
</header>
