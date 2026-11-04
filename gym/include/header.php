<?php
require_once __DIR__ . '/config.php';
$gymName = get_setting('gym_name', 'GYM MS');
$gymTagline = get_setting('gym_tagline', 'FITNESS CLUB');
$gymLogo = get_setting('gym_logo', '');
$primaryColor = get_setting('theme_primary', '#38bdf8');
$accentColor = get_setting('theme_accent', '#f97316');
$themeMode = get_setting('theme_mode', 'dark');

$btnPrimaryBg = get_setting('btn_primary_bg', $primaryColor);
$btnPrimaryText = get_setting('btn_primary_text', '#ffffff');
$btnSecondaryBg = get_setting('btn_secondary_bg', '#1e293b');
$btnSecondaryText = get_setting('btn_secondary_text', '#ffffff');
$btnSecondaryBorder = get_setting('btn_secondary_border', '#475569');
?>
<!-- Dynamic Theme Variables -->
<style>
:root {
  --color-primary: <?php echo htmlspecialchars($primaryColor, ENT_QUOTES, 'UTF-8'); ?>;
  --color-primary-glow: <?php echo htmlspecialchars($primaryColor, ENT_QUOTES, 'UTF-8'); ?>55;
  --color-accent: <?php echo htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8'); ?>;
  --color-accent-glow: <?php echo htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8'); ?>55;

  --btn-primary-bg: <?php echo htmlspecialchars($btnPrimaryBg, ENT_QUOTES, 'UTF-8'); ?>;
  --btn-primary-text: <?php echo htmlspecialchars($btnPrimaryText, ENT_QUOTES, 'UTF-8'); ?>;
  --btn-secondary-bg: <?php echo htmlspecialchars($btnSecondaryBg, ENT_QUOTES, 'UTF-8'); ?>;
  --btn-secondary-text: <?php echo htmlspecialchars($btnSecondaryText, ENT_QUOTES, 'UTF-8'); ?>;
  --btn-secondary-border: <?php echo htmlspecialchars($btnSecondaryBorder, ENT_QUOTES, 'UTF-8'); ?>;
}

/* Dynamic Primary Buttons */
.btn-primary,
.ios-btn-primary,
.ios-btn-accent,
input[type="submit"].btn-primary,
button.btn-primary {
  background: var(--btn-primary-bg) !important;
  background-image: linear-gradient(135deg, var(--btn-primary-bg) 0%, var(--color-accent) 100%) !important;
  color: var(--btn-primary-text) !important;
  border: none !important;
  box-shadow: 0 8px 22px <?php echo htmlspecialchars($btnPrimaryBg, ENT_QUOTES, 'UTF-8'); ?>55 !important;
}

.btn-primary:hover,
.ios-btn-primary:hover,
.ios-btn-accent:hover {
  box-shadow: 0 14px 28px <?php echo htmlspecialchars($btnPrimaryBg, ENT_QUOTES, 'UTF-8'); ?>77 !important;
  color: var(--btn-primary-text) !important;
  transform: translateY(-2px) scale(1.01) !important;
}

.btn-primary:active,
.ios-btn-primary:active,
.ios-btn-accent:active {
  transform: scale(0.97) !important;
}

/* Dynamic Secondary Buttons */
.btn-secondary,
.btn-outline-light,
.ios-btn-glass,
.ios-btn-secondary,
.btn-outline-primary {
  background: var(--btn-secondary-bg) !important;
  color: var(--btn-secondary-text) !important;
  border: 1px solid var(--btn-secondary-border) !important;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25) !important;
}

.btn-secondary:hover,
.btn-outline-light:hover,
.ios-btn-glass:hover,
.ios-btn-secondary:hover,
.btn-outline-primary:hover {
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

<?php if ($themeMode === 'light'): ?>
body {
  background-color: #f1f5f9 !important;
  color: #0f172a !important;
}
.ios-glass, .ios-pricing-card, .ios-auth-card, .ios-glass-table-wrapper {
  background: rgba(255, 255, 255, 0.75) !important;
  color: #0f172a !important;
  border-color: rgba(0, 0, 0, 0.08) !important;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08) !important;
}
.ios-glass-navbar {
  background: rgba(255, 255, 255, 0.8) !important;
  border-color: rgba(0, 0, 0, 0.08) !important;
}
.ios-nav-item {
  color: #475569 !important;
}
.ios-hero-title, .ios-card-title, .ios-card-price, .ios-auth-title {
  color: #0f172a !important;
  -webkit-text-fill-color: #0f172a !important;
}
<?php endif; ?>
</style>

<!-- Ambient Light Background Canvas -->
<div class="ambient-glow">
  <div class="ambient-orb ambient-orb-1"></div>
  <div class="ambient-orb ambient-orb-2"></div>
  <div class="ambient-orb ambient-orb-3"></div>
</div>

<header class="ios-glass-navbar">
  <a href="index.php" class="ios-nav-logo">
    <?php if ($gymLogo): ?>
      <img src="<?php echo htmlspecialchars($gymLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" style="max-height: 38px; border-radius: 8px;">
    <?php else: ?>
      <div class="ios-logo-badge">
        <i class="fa fa-bolt"></i>
      </div>
    <?php endif; ?>
    <div class="ios-logo-text">
      <?php echo htmlspecialchars($gymName, ENT_QUOTES, 'UTF-8'); ?>
      <?php if ($gymTagline): ?>
        <small style="display: block; font-size: 10px; color: var(--color-primary); letter-spacing: 0.05em; text-transform: uppercase;">
          <?php echo htmlspecialchars($gymTagline, ENT_QUOTES, 'UTF-8'); ?>
        </small>
      <?php endif; ?>
    </div>
  </a>

  <nav class="ios-nav-links">
    <a href="index.php" class="ios-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
      <i class="fa fa-home"></i> Home
    </a>
    <a href="about.php" class="ios-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active' : ''; ?>">
      <i class="fa fa-info-circle"></i> About
    </a>
    <a href="contact.php" class="ios-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : ''; ?>">
      <i class="fa fa-envelope"></i> Contact
    </a>
    <?php if(!empty($_SESSION['uid'])): ?>
      <a href="user-dashboard.php" class="ios-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'user-dashboard.php' ? 'active' : ''; ?>">
        <i class="fa fa-qrcode" style="color: #10b981;"></i> Digital Pass
      </a>
      <a href="Booking-History.php" class="ios-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'Booking-History.php' ? 'active' : ''; ?>">
        <i class="fa fa-calendar-check-o"></i> Bookings
      </a>
      <a href="profile.php" class="ios-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
        <i class="fa fa-user"></i> <?php echo htmlentities($_SESSION['name'] ?? 'Profile'); ?>
      </a>
      <a href="logout.php" class="ios-btn ios-btn-glass" style="padding: 8px 16px; font-size: 13px;">
        <i class="fa fa-sign-out"></i> Logout
      </a>
    <?php else: ?>
      <a href="login.php" class="ios-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'active' : ''; ?>">
        <i class="fa fa-sign-in"></i> Sign In
      </a>
      <a href="registration.php" class="ios-btn ios-btn-primary" style="padding: 8px 18px; font-size: 13px;">
        <i class="fa fa-user-plus"></i> Join Now
      </a>
    <?php endif; ?>
  </nav>

  <!-- Mobile Hamburger Toggle Button -->
  <button type="button" class="ios-mobile-nav-toggle" id="iosNavToggle" aria-label="Toggle navigation">
    <span></span>
    <span></span>
    <span></span>
  </button>
</header>

<!-- iOS Frosted Mobile Drawer Overlay & Sheet -->
<div class="ios-mobile-drawer-overlay" id="iosDrawerOverlay"></div>
<aside class="ios-mobile-drawer" id="iosDrawer">
  <div class="ios-drawer-header">
    <div class="ios-drawer-brand">
      <?php if ($gymLogo): ?>
        <img src="<?php echo htmlspecialchars($gymLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo">
      <?php else: ?>
        <div class="ios-logo-badge" style="width: 34px; height: 34px; border-radius: 8px; background: linear-gradient(135deg, var(--color-primary), var(--color-accent)); display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 14px;">
          <i class="fa fa-bolt"></i>
        </div>
      <?php endif; ?>
      <div>
        <div class="ios-drawer-title"><?php echo htmlspecialchars($gymName, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php if ($gymTagline): ?>
          <div class="ios-drawer-tagline"><?php echo htmlspecialchars($gymTagline, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
      </div>
    </div>
    <button type="button" class="ios-drawer-close" id="iosDrawerClose" aria-label="Close menu">
      <i class="fa fa-times"></i>
    </button>
  </div>

  <div class="ios-drawer-body">
    <?php if(!empty($_SESSION['uid'])): ?>
      <div class="ios-drawer-user-card">
        <div class="ios-user-avatar">
          <i class="fa fa-user"></i>
        </div>
        <div class="ios-user-info">
          <div class="ios-user-name"><?php echo htmlentities($_SESSION['name'] ?? 'Member'); ?></div>
          <div class="ios-user-badge"><i class="fa fa-check-circle"></i> Active Member</div>
        </div>
      </div>
    <?php endif; ?>

    <div class="ios-drawer-group" style="margin-bottom: 20px;">
      <div class="ios-drawer-label">Menu</div>
      <a href="index.php" class="ios-drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
        <span class="ios-link-icon"><i class="fa fa-home"></i></span>
        <span class="ios-link-text">Home</span>
        <i class="fa fa-angle-right ios-link-chevron"></i>
      </a>
      <a href="about.php" class="ios-drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active' : ''; ?>">
        <span class="ios-link-icon"><i class="fa fa-info-circle"></i></span>
        <span class="ios-link-text">About Gym</span>
        <i class="fa fa-angle-right ios-link-chevron"></i>
      </a>
      <a href="contact.php" class="ios-drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : ''; ?>">
        <span class="ios-link-icon"><i class="fa fa-envelope"></i></span>
        <span class="ios-link-text">Contact &amp; Location</span>
        <i class="fa fa-angle-right ios-link-chevron"></i>
      </a>
      <?php if(!empty($_SESSION['uid'])): ?>
        <a href="user-dashboard.php" class="ios-drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'user-dashboard.php' ? 'active' : ''; ?>">
          <span class="ios-link-icon" style="color: #10b981;"><i class="fa fa-qrcode"></i></span>
          <span class="ios-link-text">Digital Pass &amp; Workouts</span>
          <i class="fa fa-angle-right ios-link-chevron"></i>
        </a>
        <a href="Booking-History.php" class="ios-drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'Booking-History.php' ? 'active' : ''; ?>">
          <span class="ios-link-icon"><i class="fa fa-calendar-check-o"></i></span>
          <span class="ios-link-text">My Bookings</span>
          <i class="fa fa-angle-right ios-link-chevron"></i>
        </a>
        <a href="profile.php" class="ios-drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
          <span class="ios-link-icon"><i class="fa fa-user"></i></span>
          <span class="ios-link-text">My Profile</span>
          <i class="fa fa-angle-right ios-link-chevron"></i>
        </a>
        <a href="changepassword.php" class="ios-drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'changepassword.php' ? 'active' : ''; ?>">
          <span class="ios-link-icon"><i class="fa fa-key"></i></span>
          <span class="ios-link-text">Change Password</span>
          <i class="fa fa-angle-right ios-link-chevron"></i>
        </a>
      <?php endif; ?>
    </div>

    <div class="ios-drawer-actions">
      <?php if(!empty($_SESSION['uid'])): ?>
        <a href="logout.php" class="ios-btn ios-btn-glass w-100 text-center" style="display: block; padding: 12px; border-radius: 14px; text-decoration: none;">
          <i class="fa fa-sign-out"></i> Log Out
        </a>
      <?php else: ?>
        <a href="login.php" class="ios-btn ios-btn-glass w-100 text-center" style="display: block; padding: 12px; border-radius: 14px; text-decoration: none; margin-bottom: 10px;">
          <i class="fa fa-sign-in"></i> Sign In
        </a>
        <a href="registration.php" class="ios-btn ios-btn-primary w-100 text-center" style="display: block; padding: 12px; border-radius: 14px; text-decoration: none;">
          <i class="fa fa-user-plus"></i> Join Now
        </a>
      <?php endif; ?>
    </div>
  </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var toggle = document.getElementById('iosNavToggle');
  var drawer = document.getElementById('iosDrawer');
  var overlay = document.getElementById('iosDrawerOverlay');
  var closeBtn = document.getElementById('iosDrawerClose');

  function openDrawer() {
    if (drawer && overlay) {
      drawer.classList.add('active');
      overlay.classList.add('active');
      document.body.style.overflow = 'hidden';
    }
  }

  function closeDrawer() {
    if (drawer && overlay) {
      drawer.classList.remove('active');
      overlay.classList.remove('active');
      document.body.style.overflow = '';
    }
  }

  if (toggle) toggle.addEventListener('click', openDrawer);
  if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
  if (overlay) overlay.addEventListener('click', closeDrawer);

  var links = document.querySelectorAll('.ios-drawer-link, .ios-drawer-actions a');
  links.forEach(function(l) {
    l.addEventListener('click', closeDrawer);
  });
});
</script>
