<?php
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$msg = '';
$err = '';
$activeTab = trim($_GET['tab'] ?? 'general');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = "Security token mismatch. Please try again.";
    } else {
        $section = $_POST['section'] ?? 'general';
        $activeTab = $section;

        if ($section === 'general') {
            set_setting('gym_name', trim($_POST['gym_name'] ?? 'GYM MS'), 'general');
            set_setting('gym_tagline', trim($_POST['gym_tagline'] ?? ''), 'general');
            set_setting('contact_email', trim($_POST['contact_email'] ?? ''), 'contact');
            set_setting('contact_phone', trim($_POST['contact_phone'] ?? ''), 'contact');
            set_setting('contact_whatsapp', trim($_POST['contact_whatsapp'] ?? ''), 'contact');
            set_setting('contact_address', trim($_POST['contact_address'] ?? ''), 'contact');
            set_setting('map_link', trim($_POST['map_link'] ?? ''), 'contact');
            $msg = "Gym profile and contact settings updated successfully.";
        }

        elseif ($section === 'seo') {
            set_setting('site_title', trim($_POST['site_title'] ?? 'GYM MS — Luxury Fitness & Wellness'), 'seo');
            set_setting('seo_description', trim($_POST['seo_description'] ?? ''), 'seo');
            set_setting('footer_copy', trim($_POST['footer_copy'] ?? ''), 'footer');
            $msg = "SEO and footer settings updated successfully.";
        }

        elseif ($section === 'membership_financial') {
            set_setting('currency_symbol', trim($_POST['currency_symbol'] ?? '₹'), 'general');
            set_setting('currency_code', strtoupper(trim($_POST['currency_code'] ?? 'INR')), 'general');
            set_setting('renewal_reminder_days', intval($_POST['renewal_reminder_days'] ?? 14), 'membership');
            $msg = "Financial & membership configuration saved.";
        }

        elseif ($section === 'social') {
            set_setting('social_facebook', trim($_POST['social_facebook'] ?? ''), 'social');
            set_setting('social_instagram', trim($_POST['social_instagram'] ?? ''), 'social');
            set_setting('social_twitter', trim($_POST['social_twitter'] ?? ''), 'social');
            set_setting('social_youtube', trim($_POST['social_youtube'] ?? ''), 'social');
            $msg = "Social channels updated successfully.";
        }
    }
}

// System diagnostic stats
$phpVersion = PHP_VERSION;
$mysqlVersion = $dbh->query("SELECT VERSION()")->fetchColumn();
$memberCount = $dbh->query("SELECT count(*) FROM tbluser")->fetchColumn();
$bookingCount = $dbh->query("SELECT count(*) FROM tblbooking")->fetchColumn();
$planCount = $dbh->query("SELECT count(*) FROM tbladdpackage")->fetchColumn();
$uploadDirWritable = is_writable('../img/uploads') || is_writable(__DIR__ . '/../img/uploads');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>System Settings — Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php echo render_favicon_tags(); ?>
  <link rel="stylesheet" type="text/css" href="css/main.css">
  <link rel="stylesheet" type="text/css" href="css/ios-admin-glass.css">
  <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
  <style>
    .settings-nav .nav-link {
      color: var(--text-secondary);
      border-radius: 12px;
      padding: 12px 18px;
      font-weight: 600;
      margin-bottom: 6px;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .settings-nav .nav-link:hover {
      background: rgba(255,255,255,0.05);
      color: #fff;
    }
    .settings-nav .nav-link.active {
      background: rgba(56, 189, 248, 0.15);
      color: var(--color-primary);
      border-left: 3px solid var(--color-primary);
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
        <h1><i class="fa fa-sliders" style="color: var(--color-primary); margin-right: 8px;"></i> System Settings</h1>
        <p>Centralized configuration for club branding, currency, SEO, social handles, and server diagnostics</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Settings</li>
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

    <div class="row">
      <!-- Settings Tabs Navigation -->
      <div class="col-lg-3">
        <div class="tile" style="padding: 16px;">
          <div class="nav flex-column settings-nav">
            <a class="nav-link <?php echo ($activeTab === 'general') ? 'active' : ''; ?>" href="settings.php?tab=general">
              <i class="fa fa-building-o"></i> Gym Profile & Info
            </a>
            <a class="nav-link <?php echo ($activeTab === 'financial') ? 'active' : ''; ?>" href="settings.php?tab=financial">
              <i class="fa fa-money"></i> Financial & Currency
            </a>
            <a class="nav-link <?php echo ($activeTab === 'seo') ? 'active' : ''; ?>" href="settings.php?tab=seo">
              <i class="fa fa-globe"></i> SEO & Metadata
            </a>
            <a class="nav-link <?php echo ($activeTab === 'social') ? 'active' : ''; ?>" href="settings.php?tab=social">
              <i class="fa fa-share-alt"></i> Social Media Links
            </a>
            <a class="nav-link <?php echo ($activeTab === 'system') ? 'active' : ''; ?>" href="settings.php?tab=system">
              <i class="fa fa-server"></i> System Diagnostics
            </a>
          </div>
        </div>
      </div>

      <!-- Settings Content Panels -->
      <div class="col-lg-9">
        <?php if ($activeTab === 'general'): ?>
          <!-- Gym Profile Panel -->
          <div class="tile">
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">
              <i class="fa fa-building-o text-primary"></i> Gym Profile & Contact Details
            </h3>
            <form method="post">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="section" value="general">

              <div class="row">
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;">Gym Brand Name *</label>
                  <input type="text" name="gym_name" class="form-control" required value="<?php echo htmlspecialchars(get_setting('gym_name', 'GYM MS')); ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;">Brand Tagline</label>
                  <input type="text" name="gym_tagline" class="form-control" value="<?php echo htmlspecialchars(get_setting('gym_tagline', 'FITNESS CLUB')); ?>">
                </div>
              </div>

              <div class="row">
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;">Contact Email *</label>
                  <input type="email" name="contact_email" class="form-control" required value="<?php echo htmlspecialchars(get_setting('contact_email', 'concierge@gymms.com')); ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;">Phone Number</label>
                  <input type="text" name="contact_phone" class="form-control" value="<?php echo htmlspecialchars(get_setting('contact_phone', '+91 98765 43210')); ?>">
                </div>
              </div>

              <div class="row">
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;">WhatsApp Business Direct</label>
                  <input type="text" name="contact_whatsapp" class="form-control" value="<?php echo htmlspecialchars(get_setting('contact_whatsapp', '+91 98765 43210')); ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;">Google Maps Link</label>
                  <input type="url" name="map_link" class="form-control" value="<?php echo htmlspecialchars(get_setting('map_link', 'https://maps.google.com')); ?>">
                </div>
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Physical Gym Address</label>
                <textarea name="contact_address" class="form-control" rows="2"><?php echo htmlspecialchars(get_setting('contact_address', '800 Olympic Way, Suite 400, Mumbai, India')); ?></textarea>
              </div>

              <button type="submit" class="ios-btn ios-btn-accent" style="padding: 10px 24px; border-radius: 12px; margin-top: 10px;">
                <i class="fa fa-save"></i> Save Profile Settings
              </button>
            </form>
          </div>

        <?php elseif ($activeTab === 'financial'): ?>
          <!-- Financial Panel -->
          <div class="tile">
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">
              <i class="fa fa-money text-success"></i> Financial & Membership Lifecycle Settings
            </h3>
            <form method="post">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="section" value="membership_financial">

              <div class="row">
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;">Currency Symbol *</label>
                  <input type="text" name="currency_symbol" class="form-control" required value="<?php echo htmlspecialchars(get_setting('currency_symbol', '₹')); ?>">
                  <small class="form-text text-muted">e.g. ₹ (INR), $ (USD), € (EUR), £ (GBP)</small>
                </div>
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;">Currency ISO Code *</label>
                  <input type="text" name="currency_code" class="form-control" required value="<?php echo htmlspecialchars(get_setting('currency_code', 'INR')); ?>">
                  <small class="form-text text-muted">e.g. INR, USD, EUR</small>
                </div>
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Upcoming Renewal Alert Threshold (Days)</label>
                <input type="number" name="renewal_reminder_days" class="form-control" style="max-width: 200px;" value="<?php echo intval(get_setting('renewal_reminder_days', 14)); ?>">
                <small class="form-text text-muted">Memberships expiring within this window will be flagged as 'Expiring Soon' on the dashboard.</small>
              </div>

              <button type="submit" class="ios-btn ios-btn-accent" style="padding: 10px 24px; border-radius: 12px; margin-top: 10px;">
                <i class="fa fa-save"></i> Save Financial Settings
              </button>
            </form>
          </div>

        <?php elseif ($activeTab === 'seo'): ?>
          <!-- SEO & Metadata Panel -->
          <div class="tile">
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">
              <i class="fa fa-globe text-primary"></i> Website SEO & Metadata
            </h3>
            <form method="post">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="section" value="seo">

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Browser Tab Title (HTML <code>&lt;title&gt;</code>)</label>
                <input type="text" name="site_title" class="form-control" value="<?php echo htmlspecialchars(get_setting('site_title', 'GYM MS — Luxury Fitness & Wellness')); ?>">
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Search Engine Meta Description</label>
                <textarea name="seo_description" class="form-control" rows="3"><?php echo htmlspecialchars(get_setting('seo_description', 'Experience premier fitness coaching, elite recovery zones, and glassmorphic membership passes.')); ?></textarea>
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Website Footer Copyright Copy</label>
                <input type="text" name="footer_copy" class="form-control" value="<?php echo htmlspecialchars(get_setting('footer_copy', 'GYM Management System. Crafted with iOS-inspired Glassmorphism.')); ?>">
              </div>

              <button type="submit" class="ios-btn ios-btn-accent" style="padding: 10px 24px; border-radius: 12px; margin-top: 10px;">
                <i class="fa fa-save"></i> Save SEO Settings
              </button>
            </form>
          </div>

        <?php elseif ($activeTab === 'social'): ?>
          <!-- Social Handles Panel -->
          <div class="tile">
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">
              <i class="fa fa-share-alt text-accent"></i> Social Media Channels
            </h3>
            <form method="post">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="section" value="social">

              <div class="row">
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;"><i class="fa fa-instagram text-danger"></i> Instagram URL</label>
                  <input type="url" name="social_instagram" class="form-control" value="<?php echo htmlspecialchars(get_setting('social_instagram', 'https://instagram.com')); ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;"><i class="fa fa-facebook-square text-primary"></i> Facebook Page URL</label>
                  <input type="url" name="social_facebook" class="form-control" value="<?php echo htmlspecialchars(get_setting('social_facebook', 'https://facebook.com')); ?>">
                </div>
              </div>

              <div class="row">
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;"><i class="fa fa-twitter text-info"></i> Twitter / X Profile</label>
                  <input type="url" name="social_twitter" class="form-control" value="<?php echo htmlspecialchars(get_setting('social_twitter', 'https://twitter.com')); ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;"><i class="fa fa-youtube-play text-danger"></i> YouTube Channel</label>
                  <input type="url" name="social_youtube" class="form-control" value="<?php echo htmlspecialchars(get_setting('social_youtube', 'https://youtube.com')); ?>">
                </div>
              </div>

              <button type="submit" class="ios-btn ios-btn-accent" style="padding: 10px 24px; border-radius: 12px; margin-top: 10px;">
                <i class="fa fa-save"></i> Save Social Handles
              </button>
            </form>
          </div>

        <?php elseif ($activeTab === 'system'): ?>
          <!-- System Diagnostics Panel -->
          <div class="tile">
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">
              <i class="fa fa-server text-primary"></i> System Diagnostics & Health Status
            </h3>

            <div class="row" style="row-gap: 16px;">
              <div class="col-md-6">
                <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 14px; padding: 18px;">
                  <div style="font-weight: 700; color: #fff; margin-bottom: 12px;">Runtime Environment</div>
                  <div style="font-size: 13.5px; margin-bottom: 8px; display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">PHP Runtime:</span>
                    <strong>v<?php echo $phpVersion; ?></strong>
                  </div>
                  <div style="font-size: 13.5px; margin-bottom: 8px; display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">MySQL Database:</span>
                    <strong>v<?php echo $mysqlVersion; ?></strong>
                  </div>
                  <div style="font-size: 13.5px; margin-bottom: 8px; display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">PDO Driver:</span>
                    <span class="ios-badge ios-badge-success">Active (MySQL)</span>
                  </div>
                  <div style="font-size: 13.5px; display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">Uploads Folder:</span>
                    <span class="ios-badge <?php echo $uploadDirWritable ? 'ios-badge-success' : 'ios-badge-danger'; ?>">
                      <?php echo $uploadDirWritable ? 'Writable' : 'Permission Error'; ?>
                    </span>
                  </div>
                </div>
              </div>

              <div class="col-md-6">
                <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 14px; padding: 18px;">
                  <div style="font-weight: 700; color: #fff; margin-bottom: 12px;">Database Records Tally</div>
                  <div style="font-size: 13.5px; margin-bottom: 8px; display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">Registered Members:</span>
                    <strong><?php echo $memberCount; ?> accounts</strong>
                  </div>
                  <div style="font-size: 13.5px; margin-bottom: 8px; display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">Membership Packages:</span>
                    <strong><?php echo $planCount; ?> tiers</strong>
                  </div>
                  <div style="font-size: 13.5px; margin-bottom: 8px; display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">Historical Bookings:</span>
                    <strong><?php echo $bookingCount; ?> bookings</strong>
                  </div>
                  <div style="font-size: 13.5px; display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">System Architecture:</span>
                    <span class="ios-badge ios-badge-info">Hybrid PDO + Glass</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <script src="js/jquery-3.2.1.min.js"></script>
  <script src="js/popper.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/main.js"></script>
</body>
</html>
