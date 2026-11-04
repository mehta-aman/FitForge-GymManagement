<?php
$footerCopy = get_setting('footer_copy', 'GYM Management System. Crafted with iOS-inspired Glassmorphism & High-Performance Motion.');
$gymName = get_setting('gym_name', 'GYM MS');
$fb = get_setting('social_facebook');
$ig = get_setting('social_instagram');
$tw = get_setting('social_twitter');
$yt = get_setting('social_youtube');
?>
<footer class="ios-footer">
  <div class="container text-center">
    <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 14px;">
      <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10b981; box-shadow: 0 0 10px #10b981;"></span>
      <span style="font-weight: 700; color: #fff; font-size: 15px;"><?php echo htmlspecialchars($gymName, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>

    <!-- Social Media Links -->
    <div style="display: flex; justify-content: center; gap: 14px; margin-bottom: 16px;">
      <?php if ($ig): ?>
        <a href="<?php echo htmlspecialchars($ig, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="ios-btn-glass" style="width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 15px;">
          <i class="fa fa-instagram"></i>
        </a>
      <?php endif; ?>
      <?php if ($fb): ?>
        <a href="<?php echo htmlspecialchars($fb, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="ios-btn-glass" style="width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 15px;">
          <i class="fa fa-facebook"></i>
        </a>
      <?php endif; ?>
      <?php if ($tw): ?>
        <a href="<?php echo htmlspecialchars($tw, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="ios-btn-glass" style="width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 15px;">
          <i class="fa fa-twitter"></i>
        </a>
      <?php endif; ?>
      <?php if ($yt): ?>
        <a href="<?php echo htmlspecialchars($yt, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="ios-btn-glass" style="width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 15px;">
          <i class="fa fa-youtube-play"></i>
        </a>
      <?php endif; ?>
    </div>

    <p style="margin: 0; color: var(--text-muted); font-size: 13px; max-width: 600px; margin: 0 auto;">
      &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($footerCopy, ENT_QUOTES, 'UTF-8'); ?>
    </p>
  </div>
</footer>

<!-- iOS Native Mobile Bottom Tab Bar -->
<nav class="ios-mobile-tab-bar" aria-label="Mobile Bottom Navigation">
  <a href="index.php" class="ios-tab-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
    <i class="fa fa-home"></i>
    <span>Home</span>
  </a>
  <a href="index.php#pricing" class="ios-tab-item">
    <i class="fa fa-tags"></i>
    <span>Plans</span>
  </a>
  <a href="about.php" class="ios-tab-item <?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active' : ''; ?>">
    <i class="fa fa-compass"></i>
    <span>About</span>
  </a>
  <a href="contact.php" class="ios-tab-item <?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : ''; ?>">
    <i class="fa fa-envelope"></i>
    <span>Contact</span>
  </a>
  <?php if(!empty($_SESSION['uid'])): ?>
    <a href="profile.php" class="ios-tab-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['profile.php', 'Booking-History.php', 'booking-details.php', 'changepassword.php']) ? 'active' : ''; ?>">
      <i class="fa fa-user"></i>
      <span>Account</span>
    </a>
  <?php else: ?>
    <a href="login.php" class="ios-tab-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['login.php', 'registration.php']) ? 'active' : ''; ?>">
      <i class="fa fa-sign-in"></i>
      <span>Sign In</span>
    </a>
  <?php endif; ?>
</nav>
