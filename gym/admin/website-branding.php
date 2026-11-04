<?php
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $err = "Security validation failed (CSRF token invalid). Please try again.";
    } else {
        // Handle branding fields
        $fields = [
            'gym_name' => 'general',
            'gym_tagline' => 'general',
            'website_title' => 'general',
            'seo_description' => 'general',
            'contact_email' => 'contact',
            'contact_phone' => 'contact',
            'contact_whatsapp' => 'contact',
            'contact_address' => 'contact',
            'map_link' => 'contact',
            'social_facebook' => 'social',
            'social_instagram' => 'social',
            'social_twitter' => 'social',
            'social_youtube' => 'social'
        ];

        foreach ($fields as $key => $group) {
            if (isset($_POST[$key])) {
                set_setting($key, trim($_POST[$key]), $group, $dbh);
            }
        }

        // Handle Logo Upload if present
        if (!empty($_FILES['gym_logo']['name'])) {
            $uploadLogo = handle_image_upload($_FILES['gym_logo'], 'branding');
            if ($uploadLogo['success']) {
                set_setting('gym_logo', $uploadLogo['path'], 'branding', $dbh);
            } else {
                $err = "Logo upload failed: " . $uploadLogo['error'];
            }
        }

        // Handle Favicon Upload if present
        if (!empty($_FILES['gym_favicon']['name'])) {
            $uploadFavicon = handle_image_upload($_FILES['gym_favicon'], 'branding');
            if ($uploadFavicon['success']) {
                set_setting('gym_favicon', $uploadFavicon['path'], 'branding', $dbh);
                @copy(__DIR__ . '/../' . $uploadFavicon['path'], __DIR__ . '/../favicon.ico');
                @copy(__DIR__ . '/../' . $uploadFavicon['path'], __DIR__ . '/../favicon.png');
            } else {
                $err = "Favicon upload failed: " . $uploadFavicon['error'];
            }
        }

        if (empty($err)) {
            $msg = "Website branding & contact details updated successfully!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Website Branding — Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php echo render_favicon_tags(); ?>
  <link rel="stylesheet" type="text/css" href="css/main.css">
  <link rel="stylesheet" type="text/css" href="css/ios-admin-glass.css">
  <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
</head>
<body class="app sidebar-mini rtl">
  <?php include 'include/header.php'; ?>
  <div class="app-sidebar__overlay" data-toggle="sidebar"></div>
  <?php include 'include/sidebar.php'; ?>

  <main class="app-content">
    <div class="app-title">
      <div>
        <h1><i class="fa fa-paint-brush" style="color: var(--color-primary); margin-right: 8px;"></i> Website Branding &amp; Identity</h1>
        <p>Manage business branding, logo, contact channels, and social media links</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Website Branding</li>
      </ul>
    </div>

    <?php if ($msg): ?>
      <div class="ios-badge ios-badge-success" style="display: flex; margin-bottom: 24px; padding: 14px 20px; border-radius: 16px; font-size: 14px;">
        <i class="fa fa-check-circle" style="font-size: 18px;"></i>
        <span><?php echo htmlentities($msg); ?></span>
      </div>
    <?php endif; ?>

    <?php if ($err): ?>
      <div class="ios-badge ios-badge-danger" style="display: flex; margin-bottom: 24px; padding: 14px 20px; border-radius: 16px; font-size: 14px;">
        <i class="fa fa-exclamation-circle" style="font-size: 18px;"></i>
        <span><?php echo htmlentities($err); ?></span>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <?php echo csrf_input(); ?>

      <!-- 1. Brand Identity -->
      <div class="tile">
        <h3 class="tile-title"><i class="fa fa-id-badge" style="color: var(--color-primary);"></i> Brand Essentials</h3>
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label">Gym / Business Name *</label>
              <input type="text" name="gym_name" class="form-control" value="<?php echo htmlentities(get_setting('gym_name', 'GYM MS')); ?>" required>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label">Brand Tagline / Slogan</label>
              <input type="text" name="gym_tagline" class="form-control" value="<?php echo htmlentities(get_setting('gym_tagline', 'FITNESS CLUB')); ?>">
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label">Website Browser Title</label>
              <input type="text" name="website_title" class="form-control" value="<?php echo htmlentities(get_setting('website_title', 'GYM MS — Elevate Your Fitness')); ?>">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label">SEO Meta Description</label>
              <input type="text" name="seo_description" class="form-control" value="<?php echo htmlentities(get_setting('seo_description', 'Next generation fitness club.')); ?>">
            </div>
          </div>
        </div>

        <!-- Logo & Favicon Upload -->
        <div class="row" style="margin-top: 10px;">
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label">Brand Logo (PNG, SVG, WEBP)</label>
              <input type="file" name="gym_logo" class="form-control" accept="image/*">
              <?php $curLogo = get_setting('gym_logo'); if ($curLogo): ?>
                <div style="margin-top: 10px; display: flex; align-items: center; gap: 10px;">
                  <span style="font-size: 12px; color: var(--text-secondary);">Current Logo:</span>
                  <img src="../<?php echo htmlentities($curLogo); ?>" alt="Logo Preview" style="max-height: 40px; background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 8px;">
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label">Browser Favicon (ICO, PNG, SVG)</label>
              <input type="file" name="gym_favicon" class="form-control" accept="image/*">
              <?php $curFavicon = get_setting('gym_favicon'); if ($curFavicon): ?>
                <div style="margin-top: 10px; display: flex; align-items: center; gap: 10px;">
                  <span style="font-size: 12px; color: var(--text-secondary);">Current Favicon:</span>
                  <img src="../<?php echo htmlentities($curFavicon); ?>" alt="Favicon Preview" style="width: 24px; height: 24px;">
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- 2. Contact Information -->
      <div class="tile">
        <h3 class="tile-title"><i class="fa fa-phone" style="color: var(--color-primary);"></i> Contact Channels</h3>
        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Contact Email</label>
              <input type="email" name="contact_email" class="form-control" value="<?php echo htmlentities(get_setting('contact_email', 'contact@gymclub.com')); ?>" required>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Phone Number</label>
              <input type="text" name="contact_phone" class="form-control" value="<?php echo htmlentities(get_setting('contact_phone', '+91 98765 43210')); ?>" required>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">WhatsApp Number</label>
              <input type="text" name="contact_whatsapp" class="form-control" value="<?php echo htmlentities(get_setting('contact_whatsapp', '+91 98765 43210')); ?>">
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-8">
            <div class="form-group">
              <label class="control-label">Physical Address</label>
              <input type="text" name="contact_address" class="form-control" value="<?php echo htmlentities(get_setting('contact_address', '800 Olympic Way, Suite 400, Mumbai, India')); ?>">
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Google Maps Link</label>
              <input type="url" name="map_link" class="form-control" value="<?php echo htmlentities(get_setting('map_link', 'https://maps.google.com')); ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- 3. Social Media Links -->
      <div class="tile">
        <h3 class="tile-title"><i class="fa fa-share-alt" style="color: var(--color-primary);"></i> Social Media Handles</h3>
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label"><i class="fa fa-instagram"></i> Instagram URL</label>
              <input type="url" name="social_instagram" class="form-control" value="<?php echo htmlentities(get_setting('social_instagram', 'https://instagram.com')); ?>">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label"><i class="fa fa-facebook"></i> Facebook Page URL</label>
              <input type="url" name="social_facebook" class="form-control" value="<?php echo htmlentities(get_setting('social_facebook', 'https://facebook.com')); ?>">
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label"><i class="fa fa-twitter"></i> X / Twitter URL</label>
              <input type="url" name="social_twitter" class="form-control" value="<?php echo htmlentities(get_setting('social_twitter', 'https://twitter.com')); ?>">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label"><i class="fa fa-youtube-play"></i> YouTube Channel URL</label>
              <input type="url" name="social_youtube" class="form-control" value="<?php echo htmlentities(get_setting('social_youtube', 'https://youtube.com')); ?>">
            </div>
          </div>
        </div>

        <div style="margin-top: 20px; display: flex; justify-content: flex-end;">
          <button type="submit" class="btn btn-primary" style="padding: 12px 30px; font-size: 15px;">
            <i class="fa fa-save"></i> Save Website Branding
          </button>
        </div>
      </div>
    </form>
  </main>

  <script src="js/jquery-3.2.1.min.js"></script>
  <script src="js/popper.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/main.js"></script>
</body>
</html>
