<?php
require_once 'include/config.php';
require_owner();

$msg = '';
$err = '';
$activeTab = trim($_GET['tab'] ?? 'profile');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = "Security token mismatch. Please try again.";
    } else {
        $section = $_POST['section'] ?? 'profile';
        $activeTab = $section;

        if ($section === 'profile') {
            set_setting('gym_name', trim($_POST['gym_name'] ?? 'GYM MS'), 'general');
            set_setting('gym_tagline', trim($_POST['gym_tagline'] ?? ''), 'general');
            set_setting('contact_email', trim($_POST['contact_email'] ?? ''), 'contact');
            set_setting('contact_phone', trim($_POST['contact_phone'] ?? ''), 'contact');
            set_setting('contact_whatsapp', trim($_POST['contact_whatsapp'] ?? ''), 'contact');
            set_setting('contact_address', trim($_POST['contact_address'] ?? ''), 'contact');
            set_setting('map_link', trim($_POST['map_link'] ?? ''), 'contact');
            set_setting('business_hours', trim($_POST['business_hours'] ?? 'Mon - Fri: 06:00 AM - 10:00 PM | Sat - Sun: 07:00 AM - 08:00 PM'), 'contact');
            $msg = "Gym profile and contact settings updated successfully.";
        }

        elseif ($section === 'website') {
            set_setting('website_visible', isset($_POST['website_visible']) ? '1' : '0', 'website');
            set_setting('website_title', trim($_POST['website_title'] ?? 'GYM MS — Luxury Fitness'), 'website');
            set_setting('seo_description', trim($_POST['seo_description'] ?? ''), 'website');
            set_setting('footer_copy', trim($_POST['footer_copy'] ?? ''), 'footer');
            $msg = "Website visibility and SEO settings saved.";
        }

        elseif ($section === 'appearance') {
            $mode = in_array($_POST['theme_mode'] ?? '', ['dark', 'light', 'system']) ? $_POST['theme_mode'] : 'dark';
            set_setting('theme_mode', $mode, 'theme');
            $msg = "Theme mode and appearance preference saved.";
        }

        elseif ($section === 'policies') {
            set_setting('default_plan_duration', intval($_POST['default_plan_duration'] ?? 3), 'membership');
            set_setting('renewal_reminder_days', intval($_POST['renewal_reminder_days'] ?? 14), 'membership');
            set_setting('grace_period_days', intval($_POST['grace_period_days'] ?? 7), 'membership');
            set_setting('max_freeze_days', intval($_POST['max_freeze_days'] ?? 30), 'membership');
            set_setting('freeze_requires_approval', isset($_POST['freeze_requires_approval']) ? '1' : '0', 'membership');
            set_setting('cancellation_policy', trim($_POST['cancellation_policy'] ?? ''), 'membership');
            $msg = "Membership lifecycle and policy rules saved successfully.";
        }

        elseif ($section === 'payments') {
            set_setting('currency_symbol', trim($_POST['currency_symbol'] ?? '₹'), 'general');
            set_setting('currency_code', strtoupper(trim($_POST['currency_code'] ?? 'INR')), 'general');
            
            $methods = $_POST['methods'] ?? ['cash'];
            if (!is_array($methods)) $methods = ['cash'];
            set_setting('payment_methods_enabled', implode(',', array_map('trim', $methods)), 'payment');
            
            set_setting('receipt_notes', trim($_POST['receipt_notes'] ?? 'Thank you for training with us!'), 'payment');
            set_setting('refund_policy', trim($_POST['refund_policy'] ?? 'Memberships are non-refundable once activated.'), 'payment');
            $msg = "Payment methods, currency, and receipt branding updated.";
        }

        elseif ($section === 'notifications') {
            set_setting('notify_email_enabled', isset($_POST['notify_email_enabled']) ? '1' : '0', 'notifications');
            set_setting('notify_sms_enabled', isset($_POST['notify_sms_enabled']) ? '1' : '0', 'notifications');
            set_setting('notify_whatsapp_enabled', isset($_POST['notify_whatsapp_enabled']) ? '1' : '0', 'notifications');
            set_setting('notify_welcome_tpl', trim($_POST['notify_welcome_tpl'] ?? 'Welcome {member_name} to {gym_name}! Your {plan_name} membership is active until {expiry_date}.'), 'notifications');
            set_setting('notify_renewal_tpl', trim($_POST['notify_renewal_tpl'] ?? 'Hi {member_name}, your membership at {gym_name} expires on {expiry_date}. Renew today to continue uninterrupted access!'), 'notifications');
            $msg = "Notification preferences and message templates saved.";
        }

        elseif ($section === 'security') {
            set_setting('session_timeout_minutes', max(5, intval($_POST['session_timeout_minutes'] ?? 30)), 'security');
            set_setting('max_login_attempts', max(3, intval($_POST['max_login_attempts'] ?? 5)), 'security');
            set_setting('lockout_minutes', max(5, intval($_POST['lockout_minutes'] ?? 15)), 'security');
            $msg = "Security and session parameters updated.";
        }
    }
}

// System diagnostic stats
$phpVersion = PHP_VERSION;
$mysqlVersion = $dbh->query("SELECT VERSION()")->fetchColumn();
$memberCount = $dbh->query("SELECT count(*) FROM tbluser")->fetchColumn();
$bookingCount = $dbh->query("SELECT count(*) FROM tblbooking")->fetchColumn();
$planCount = $dbh->query("SELECT count(*) FROM tbladdpackage")->fetchColumn();
$membershipCount = $dbh->query("SELECT count(*) FROM tbl_memberships")->fetchColumn();
$uploadDirWritable = is_writable('../img/uploads') || is_writable(__DIR__ . '/../img/uploads');

$activePaymentMethods = explode(',', get_setting('payment_methods_enabled', 'cash,upi,card,bank_transfer'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Centralized Settings — <?php echo htmlspecialchars(get_setting('gym_name', 'GYM MS')); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php echo render_favicon_tags(); ?>
  <link rel="stylesheet" type="text/css" href="css/main.css">
  <link rel="stylesheet" type="text/css" href="css/ios-admin-glass.css">
  <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
  <style>
    .settings-nav .nav-link {
      color: var(--text-secondary);
      border-radius: 12px;
      padding: 12px 16px;
      font-weight: 600;
      margin-bottom: 4px;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 13.5px;
      text-decoration: none !important;
    }
    .settings-nav .nav-link:hover {
      background: rgba(255,255,255,0.06);
      color: #fff;
    }
    .settings-nav .nav-link.active {
      background: rgba(56, 189, 248, 0.15);
      color: var(--color-primary);
      border-left: 3px solid var(--color-primary);
    }
    .settings-nav .nav-link i {
      width: 20px;
      text-align: center;
    }
    .settings-card {
      background: rgba(15, 23, 42, 0.75);
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 20px;
      padding: 24px;
      box-shadow: 0 16px 36px rgba(0, 0, 0, 0.35);
      margin-bottom: 24px;
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
        <h1><i class="fa fa-sliders" style="color: var(--color-primary); margin-right: 8px;"></i> Centralized Settings</h1>
        <p>Complete operational management: branding, policies, payment gateways, notifications, security &amp; diagnostics</p>
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
      <!-- 8-Section Settings Tabs Navigation -->
      <div class="col-lg-3 mb-4">
        <div class="settings-card" style="padding: 14px;">
          <div class="nav flex-column settings-nav">
            <a class="nav-link <?php echo ($activeTab === 'profile') ? 'active' : ''; ?>" href="settings.php?tab=profile">
              <i class="fa fa-building-o"></i> 1. Gym Profile
            </a>
            <a class="nav-link <?php echo ($activeTab === 'website') ? 'active' : ''; ?>" href="settings.php?tab=website">
              <i class="fa fa-globe"></i> 2. Website Settings
            </a>
            <a class="nav-link <?php echo ($activeTab === 'appearance') ? 'active' : ''; ?>" href="settings.php?tab=appearance">
              <i class="fa fa-paint-brush"></i> 3. Appearance &amp; Theme
            </a>
            <a class="nav-link <?php echo ($activeTab === 'policies') ? 'active' : ''; ?>" href="settings.php?tab=policies">
              <i class="fa fa-id-card-o"></i> 4. Membership Policies
            </a>
            <a class="nav-link <?php echo ($activeTab === 'payments') ? 'active' : ''; ?>" href="settings.php?tab=payments">
              <i class="fa fa-credit-card"></i> 5. Payment Settings
            </a>
            <a class="nav-link <?php echo ($activeTab === 'notifications') ? 'active' : ''; ?>" href="settings.php?tab=notifications">
              <i class="fa fa-bell-o"></i> 6. Notifications
            </a>
            <a class="nav-link <?php echo ($activeTab === 'security') ? 'active' : ''; ?>" href="settings.php?tab=security">
              <i class="fa fa-shield"></i> 7. Admin &amp; Security
            </a>
            <a class="nav-link <?php echo ($activeTab === 'system') ? 'active' : ''; ?>" href="settings.php?tab=system">
              <i class="fa fa-server"></i> 8. System Diagnostics
            </a>
          </div>
        </div>
      </div>

      <!-- Settings Content Panels -->
      <div class="col-lg-9">

        <!-- 1. Gym Profile Panel -->
        <?php if ($activeTab === 'profile'): ?>
          <div class="settings-card">
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">
              <i class="fa fa-building-o" style="color: var(--color-primary); margin-right: 8px;"></i> A. Gym Profile &amp; Contact Info
            </h3>
            <form method="post">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="section" value="profile">

              <div class="row">
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;">Gym / Business Name *</label>
                  <input type="text" name="gym_name" class="form-control" required value="<?php echo htmlspecialchars(get_setting('gym_name', 'Golden Eagle')); ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;">Brand Tagline</label>
                  <input type="text" name="gym_tagline" class="form-control" value="<?php echo htmlspecialchars(get_setting('gym_tagline', 'FITNESS CLUB')); ?>">
                </div>
              </div>

              <div class="row">
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;">Contact Email *</label>
                  <input type="email" name="contact_email" class="form-control" required value="<?php echo htmlspecialchars(get_setting('contact_email', 'contact@gymclub.com')); ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;">Phone Number *</label>
                  <input type="text" name="contact_phone" class="form-control" required value="<?php echo htmlspecialchars(get_setting('contact_phone', '+91 9983665673')); ?>">
                </div>
              </div>

              <div class="row">
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;">WhatsApp Business Direct</label>
                  <input type="text" name="contact_whatsapp" class="form-control" value="<?php echo htmlspecialchars(get_setting('contact_whatsapp', '+91 9983665673')); ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;">Google Maps Embed / Location Link</label>
                  <input type="url" name="map_link" class="form-control" value="<?php echo htmlspecialchars(get_setting('map_link', 'https://maps.google.com')); ?>">
                </div>
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Operating Business Hours</label>
                <input type="text" name="business_hours" class="form-control" value="<?php echo htmlspecialchars(get_setting('business_hours', 'Mon - Fri: 06:00 AM - 10:00 PM | Sat - Sun: 07:00 AM - 08:00 PM')); ?>">
                <small class="form-text text-muted">Displayed on the public website footer and contact section.</small>
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Physical Gym Address</label>
                <textarea name="contact_address" class="form-control" rows="2"><?php echo htmlspecialchars(get_setting('contact_address', 'Radha Krishna Mandir, Nimbaheda Road, Mangalwad, India')); ?></textarea>
              </div>

              <div class="d-flex align-items-center gap-3 mt-4">
                <button type="submit" class="btn btn-primary">
                  <i class="fa fa-save"></i> Save Profile Settings
                </button>
                <a href="website-branding.php" class="btn btn-secondary">
                  <i class="fa fa-picture-o"></i> Manage Logo &amp; Favicon Uploads
                </a>
              </div>
            </form>
          </div>

        <!-- 2. Website Settings Panel -->
        <?php elseif ($activeTab === 'website'): ?>
          <div class="settings-card">
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">
              <i class="fa fa-globe" style="color: var(--color-primary); margin-right: 8px;"></i> B. Website &amp; SEO Settings
            </h3>
            <form method="post">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="section" value="website">

              <div class="form-group">
                <div class="custom-control custom-checkbox">
                  <input type="checkbox" class="custom-control-input" id="webVisible" name="website_visible" value="1" <?php echo get_setting('website_visible', '1') === '1' ? 'checked' : ''; ?>>
                  <label class="custom-control-label" for="webVisible" style="font-weight: 600;">Public Portal Active (Allows visitors to browse plans and register)</label>
                </div>
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Browser Tab Title (HTML &lt;title&gt;)</label>
                <input type="text" name="website_title" class="form-control" value="<?php echo htmlspecialchars(get_setting('website_title', 'Golden Eagle FITNESS CLUB — Elevate Your Mind & Body')); ?>">
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13.5px;">SEO Meta Description</label>
                <textarea name="seo_description" class="form-control" rows="3"><?php echo htmlspecialchars(get_setting('seo_description', 'Next generation fitness club offering personalized training, premium equipment, and flexible membership passes.')); ?></textarea>
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Footer Copyright Text</label>
                <input type="text" name="footer_copy" class="form-control" value="<?php echo htmlspecialchars(get_setting('footer_copy', 'FitForge Gym Management System. Crafted with iOS-inspired Glassmorphism.')); ?>">
              </div>

              <div class="d-flex align-items-center gap-3 mt-4">
                <button type="submit" class="btn btn-primary">
                  <i class="fa fa-save"></i> Save Website Settings
                </button>
                <a href="website-content.php" class="btn btn-secondary">
                  <i class="fa fa-pencil-square-o"></i> Edit Hero &amp; Facilities Content
                </a>
              </div>
            </form>
          </div>

        <!-- 3. Appearance Panel -->
        <?php elseif ($activeTab === 'appearance'): ?>
          <div class="settings-card">
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">
              <i class="fa fa-paint-brush" style="color: var(--color-primary); margin-right: 8px;"></i> C. Appearance &amp; Theme Engine
            </h3>
            <form method="post">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="section" value="appearance">

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Display Theme Mode</label>
                <select name="theme_mode" class="form-control" style="max-width: 300px;">
                  <option value="dark" <?php echo get_setting('theme_mode', 'dark') === 'dark' ? 'selected' : ''; ?>>Dark Mode (Midnight Sapphire Mesh)</option>
                  <option value="light" <?php echo get_setting('theme_mode', 'dark') === 'light' ? 'selected' : ''; ?>>Light Mode (Clean Frosted Ceramic)</option>
                  <option value="system" <?php echo get_setting('theme_mode', 'dark') === 'system' ? 'selected' : ''; ?>>Auto (Match System Preference)</option>
                </select>
                <small class="form-text text-muted">Propagates across both the administrative console and member portal.</small>
              </div>

              <div style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 18px; margin: 20px 0;">
                <div style="font-weight: 700; margin-bottom: 8px; color: #fff;">Color Customization &amp; Button Palette</div>
                <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">
                  Configure primary colors, accent gradients, primary button fills, and secondary button styles with live visual previews.
                </p>
                <a href="theme-settings.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                  <i class="fa fa-magic"></i> Open Theme &amp; Button Customizer
                </a>
              </div>

              <button type="submit" class="btn btn-secondary">
                <i class="fa fa-save"></i> Save Theme Mode
              </button>
            </form>
          </div>

        <!-- 4. Membership Policies Panel -->
        <?php elseif ($activeTab === 'policies'): ?>
          <div class="settings-card">
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">
              <i class="fa fa-id-card-o" style="color: var(--color-primary); margin-right: 8px;"></i> D. Membership Lifecycle &amp; Policies
            </h3>
            <form method="post">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="section" value="policies">

              <div class="row">
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;">Default Plan Duration (Months)</label>
                  <input type="number" name="default_plan_duration" class="form-control" min="1" max="36" value="<?php echo intval(get_setting('default_plan_duration', 3)); ?>">
                  <small class="form-text text-muted">Preselected duration when creating new plans or memberships.</small>
                </div>
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;">Renewal Reminder Threshold (Days)</label>
                  <input type="number" name="renewal_reminder_days" class="form-control" min="1" max="60" value="<?php echo intval(get_setting('renewal_reminder_days', 14)); ?>">
                  <small class="form-text text-muted">Memberships expiring within this window will be flagged as 'Expiring Soon'.</small>
                </div>
              </div>

              <div class="row">
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;">Grace Period After Expiration (Days)</label>
                  <input type="number" name="grace_period_days" class="form-control" min="0" max="30" value="<?php echo intval(get_setting('grace_period_days', 7)); ?>">
                  <small class="form-text text-muted">Number of days a member can retain access before hard lockout.</small>
                </div>
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;">Maximum Allowed Freeze Duration (Days)</label>
                  <input type="number" name="max_freeze_days" class="form-control" min="7" max="180" value="<?php echo intval(get_setting('max_freeze_days', 30)); ?>">
                  <small class="form-text text-muted">Upper limit on how long a member can suspend their pass per term.</small>
                </div>
              </div>

              <div class="form-group">
                <div class="custom-control custom-checkbox">
                  <input type="checkbox" class="custom-control-input" id="freezeApproval" name="freeze_requires_approval" value="1" <?php echo get_setting('freeze_requires_approval', '1') === '1' ? 'checked' : ''; ?>>
                  <label class="custom-control-label" for="freezeApproval" style="font-weight: 600;">Freeze requests require administrative authorization</label>
                </div>
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Cancellation Policy &amp; Terms</label>
                <textarea name="cancellation_policy" class="form-control" rows="3"><?php echo htmlspecialchars(get_setting('cancellation_policy', 'Memberships may be cancelled with 7 days written notice. Paid fees for the active billing cycle are non-refundable.')); ?></textarea>
              </div>

              <button type="submit" class="btn btn-primary mt-2">
                <i class="fa fa-save"></i> Save Membership Policies
              </button>
            </form>
          </div>

        <!-- 5. Payment Settings Panel -->
        <?php elseif ($activeTab === 'payments'): ?>
          <div class="settings-card">
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">
              <i class="fa fa-credit-card" style="color: var(--color-primary); margin-right: 8px;"></i> E. Payment Methods &amp; Financial Settings
            </h3>
            <form method="post">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="section" value="payments">

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
                <label style="font-weight: 600; font-size: 13.5px; display: block;">Supported Payment Methods</label>
                <div class="row">
                  <div class="col-sm-3">
                    <div class="custom-control custom-checkbox">
                      <input type="checkbox" class="custom-control-input" id="methodCash" name="methods[]" value="cash" <?php echo in_array('cash', $activePaymentMethods) ? 'checked' : ''; ?>>
                      <label class="custom-control-label" for="methodCash"><i class="fa fa-money"></i> Cash</label>
                    </div>
                  </div>
                  <div class="col-sm-3">
                    <div class="custom-control custom-checkbox">
                      <input type="checkbox" class="custom-control-input" id="methodUpi" name="methods[]" value="upi" <?php echo in_array('upi', $activePaymentMethods) ? 'checked' : ''; ?>>
                      <label class="custom-control-label" for="methodUpi"><i class="fa fa-qrcode"></i> UPI / QR</label>
                    </div>
                  </div>
                  <div class="col-sm-3">
                    <div class="custom-control custom-checkbox">
                      <input type="checkbox" class="custom-control-input" id="methodCard" name="methods[]" value="card" <?php echo in_array('card', $activePaymentMethods) ? 'checked' : ''; ?>>
                      <label class="custom-control-label" for="methodCard"><i class="fa fa-credit-card"></i> Card / POS</label>
                    </div>
                  </div>
                  <div class="col-sm-3">
                    <div class="custom-control custom-checkbox">
                      <input type="checkbox" class="custom-control-input" id="methodBank" name="methods[]" value="bank_transfer" <?php echo in_array('bank_transfer', $activePaymentMethods) ? 'checked' : ''; ?>>
                      <label class="custom-control-label" for="methodBank"><i class="fa fa-university"></i> Bank Transfer</label>
                    </div>
                  </div>
                </div>
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Printable Receipt Header / Footer Notes</label>
                <textarea name="receipt_notes" class="form-control" rows="2"><?php echo htmlspecialchars(get_setting('receipt_notes', 'Thank you for training with Golden Eagle Fitness Club! Please present this receipt for access verification.')); ?></textarea>
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Refund Policy Statement</label>
                <textarea name="refund_policy" class="form-control" rows="2"><?php echo htmlspecialchars(get_setting('refund_policy', 'All membership dues, registration fees, and package purchases are non-refundable once activated.')); ?></textarea>
              </div>

              <button type="submit" class="btn btn-primary mt-2">
                <i class="fa fa-save"></i> Save Payment Settings
              </button>
            </form>
          </div>

        <!-- 6. Notifications Panel -->
        <?php elseif ($activeTab === 'notifications'): ?>
          <div class="settings-card">
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">
              <i class="fa fa-bell-o" style="color: var(--color-primary); margin-right: 8px;"></i> F. Automated Member Notification Channels
            </h3>
            <form method="post">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="section" value="notifications">

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13.5px; display: block;">Active Alert Channels</label>
                <div class="custom-control custom-checkbox mb-2">
                  <input type="checkbox" class="custom-control-input" id="notifyEmail" name="notify_email_enabled" value="1" <?php echo get_setting('notify_email_enabled', '1') === '1' ? 'checked' : ''; ?>>
                  <label class="custom-control-label" for="notifyEmail"><i class="fa fa-envelope-o"></i> Email Reminders (PHP mail() / SMTP)</label>
                </div>
                <div class="custom-control custom-checkbox mb-2">
                  <input type="checkbox" class="custom-control-input" id="notifySms" name="notify_sms_enabled" value="1" <?php echo get_setting('notify_sms_enabled', '0') === '1' ? 'checked' : ''; ?>>
                  <label class="custom-control-label" for="notifySms"><i class="fa fa-comment-o"></i> SMS Text Alerts</label>
                </div>
                <div class="custom-control custom-checkbox">
                  <input type="checkbox" class="custom-control-input" id="notifyWa" name="notify_whatsapp_enabled" value="1" <?php echo get_setting('notify_whatsapp_enabled', '1') === '1' ? 'checked' : ''; ?>>
                  <label class="custom-control-label" for="notifyWa"><i class="fa fa-whatsapp"></i> WhatsApp Dispatch Channel</label>
                </div>
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Welcome Message Template</label>
                <textarea name="notify_welcome_tpl" class="form-control" rows="2"><?php echo htmlspecialchars(get_setting('notify_welcome_tpl', 'Welcome {member_name} to {gym_name}! Your {plan_name} membership has been activated successfully until {expiry_date}.')); ?></textarea>
                <small class="form-text text-muted">Merge tags: <code>{member_name}</code>, <code>{gym_name}</code>, <code>{plan_name}</code>, <code>{expiry_date}</code>, <code>{amount}</code></small>
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Upcoming Renewal Reminder Template</label>
                <textarea name="notify_renewal_tpl" class="form-control" rows="2"><?php echo htmlspecialchars(get_setting('notify_renewal_tpl', 'Hi {member_name}, your membership at {gym_name} will expire on {expiry_date}. Please renew soon to avoid interruptions!')); ?></textarea>
              </div>

              <button type="submit" class="btn btn-primary mt-2">
                <i class="fa fa-save"></i> Save Notification Templates
              </button>
            </form>
          </div>

        <!-- 7. Admin & Security Panel -->
        <?php elseif ($activeTab === 'security'): ?>
          <div class="settings-card">
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">
              <i class="fa fa-shield" style="color: var(--color-primary); margin-right: 8px;"></i> G. Admin Security &amp; Session Protection
            </h3>
            <form method="post">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="section" value="security">

              <div class="row">
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;">Session Inactivity Timeout (Minutes)</label>
                  <input type="number" name="session_timeout_minutes" class="form-control" min="5" max="1440" value="<?php echo intval(get_setting('session_timeout_minutes', 60)); ?>">
                  <small class="form-text text-muted">Auto-terminates idle staff sessions for security.</small>
                </div>
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13.5px;">Max Failed Logins Before Lockout</label>
                  <input type="number" name="max_login_attempts" class="form-control" min="3" max="10" value="<?php echo intval(get_setting('max_login_attempts', 5)); ?>">
                  <small class="form-text text-muted">Consecutive bad attempts before temporary IP block.</small>
                </div>
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Lockout Duration (Minutes)</label>
                <input type="number" name="lockout_minutes" class="form-control" style="max-width: 250px;" min="5" max="120" value="<?php echo intval(get_setting('lockout_minutes', 15)); ?>">
                <small class="form-text text-muted">Duration the actor is denied login attempts.</small>
              </div>

              <div class="d-flex align-items-center gap-3 mt-4">
                <button type="submit" class="btn btn-primary">
                  <i class="fa fa-save"></i> Save Security Configuration
                </button>
                <a href="change-password.php" class="btn btn-secondary">
                  <i class="fa fa-key"></i> Change Admin Password
                </a>
              </div>
            </form>
          </div>

        <!-- 8. System Diagnostics Panel -->
        <?php elseif ($activeTab === 'system'): ?>
          <div class="settings-card">
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">
              <i class="fa fa-server" style="color: var(--color-primary); margin-right: 8px;"></i> H. System Diagnostics &amp; Health Overview
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
                    <span class="ios-badge ios-badge-success">Active (MySQL UTF-8)</span>
                  </div>
                  <div style="font-size: 13.5px; margin-bottom: 8px; display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">Uploads Folder:</span>
                    <span class="ios-badge <?php echo $uploadDirWritable ? 'ios-badge-success' : 'ios-badge-danger'; ?>">
                      <?php echo $uploadDirWritable ? 'Writable (0755)' : 'Permission Error'; ?>
                    </span>
                  </div>
                  <div style="font-size: 13.5px; display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">Multi-Gym Tenant ID:</span>
                    <span class="ios-badge ios-badge-info">Organization #1 (Active)</span>
                  </div>
                </div>
              </div>

              <div class="col-md-6">
                <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 14px; padding: 18px;">
                  <div style="font-weight: 700; color: #fff; margin-bottom: 12px;">Live Database Counts</div>
                  <div style="font-size: 13.5px; margin-bottom: 8px; display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">Registered Members:</span>
                    <strong><?php echo $memberCount; ?> accounts</strong>
                  </div>
                  <div style="font-size: 13.5px; margin-bottom: 8px; display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">Active Memberships:</span>
                    <strong><?php echo $membershipCount; ?> records</strong>
                  </div>
                  <div style="font-size: 13.5px; margin-bottom: 8px; display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">Membership Plans:</span>
                    <strong><?php echo $planCount; ?> tiers</strong>
                  </div>
                  <div style="font-size: 13.5px; margin-bottom: 8px; display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">Historical Bookings:</span>
                    <strong><?php echo $bookingCount; ?> bookings</strong>
                  </div>
                  <div style="font-size: 13.5px; display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">Security Module:</span>
                    <span class="ios-badge ios-badge-success">Bcrypt + Throttling</span>
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
