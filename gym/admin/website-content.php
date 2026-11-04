<?php
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$msg = '';
$err = '';

// Handle Feature Action (Delete or Toggle)
if (isset($_GET['action']) && isset($_GET['fid'])) {
    $fid = intval($_GET['fid']);
    if ($_GET['action'] === 'delete') {
        $stmt = $dbh->prepare("DELETE FROM tbl_website_features WHERE id = ?");
        $stmt->execute([$fid]);
        $msg = "Feature removed successfully.";
    } elseif ($_GET['action'] === 'toggle') {
        $stmt = $dbh->prepare("UPDATE tbl_website_features SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$fid]);
        $msg = "Feature status updated.";
    }
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $err = "Security token mismatch. Please try again.";
    } else {
        // 1. Add new feature
        if (isset($_POST['add_feature'])) {
            $f_title = trim($_POST['f_title']);
            $f_desc = trim($_POST['f_desc']);
            $f_icon = trim($_POST['f_icon'] ?: 'fa-bolt');
            $f_order = intval($_POST['f_order'] ?: 0);
            if ($f_title && $f_desc) {
                $stmt = $dbh->prepare("INSERT INTO tbl_website_features (title, description, icon, display_order, is_active) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$f_title, $f_desc, $f_icon, $f_order]);
                $msg = "New facility feature added!";
            } else {
                $err = "Please provide both feature title and description.";
            }
        } 
        // 2. Update Copy (Hero, About, Footer)
        else {
            $contentFields = [
                'hero_pill' => 'hero',
                'hero_title' => 'hero',
                'hero_subtitle' => 'hero',
                'hero_cta_text' => 'hero',
                'hero_cta_link' => 'hero',
                'about_pill' => 'about',
                'about_title' => 'about',
                'about_text' => 'about',
                'footer_copy' => 'footer'
            ];

            foreach ($contentFields as $key => $grp) {
                if (isset($_POST[$key])) {
                    set_setting($key, trim($_POST[$key]), $grp, $dbh);
                }
            }
            $msg = "Website content sections updated successfully!";
        }
    }
}

// Fetch all features
$features = $dbh->query("SELECT * FROM tbl_website_features ORDER BY display_order ASC, id ASC")->fetchAll(PDO::FETCH_OBJ);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Website Content — Admin</title>
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
        <h1><i class="fa fa-file-text-o" style="color: var(--color-primary); margin-right: 8px;"></i> Website Content &amp; Copy</h1>
        <p>Edit Homepage Hero, About mission, facilities list, and footer information</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Website Content</li>
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

    <!-- 1. Hero Section -->
    <form method="post">
      <?php echo csrf_input(); ?>
      <div class="tile">
        <h3 class="tile-title"><i class="fa fa-desktop" style="color: var(--color-primary);"></i> Homepage Hero Section</h3>
        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Hero Badge / Pill Tag</label>
              <input type="text" name="hero_pill" class="form-control" value="<?php echo htmlentities(get_setting('hero_pill', 'NEXT GENERATION FITNESS')); ?>">
            </div>
          </div>
          <div class="col-md-8">
            <div class="form-group">
              <label class="control-label">Hero Headline Title *</label>
              <input type="text" name="hero_title" class="form-control" value="<?php echo htmlentities(get_setting('hero_title', 'Sculpt Your Body. Elevate Your Mind.')); ?>" required>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="control-label">Hero Subtitle</label>
          <textarea name="hero_subtitle" class="form-control" rows="3"><?php echo htmlentities(get_setting('hero_subtitle', 'Experience bespoke coaching, elite recovery zones, and flexible membership passes with iOS-grade precision.')); ?></textarea>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label">Primary Call to Action (Button Text)</label>
              <input type="text" name="hero_cta_text" class="form-control" value="<?php echo htmlentities(get_setting('hero_cta_text', 'Explore Memberships')); ?>">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label">CTA Button Link / Anchor</label>
              <input type="text" name="hero_cta_link" class="form-control" value="<?php echo htmlentities(get_setting('hero_cta_link', '#plans')); ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- 2. About Mission Section -->
      <div class="tile">
        <h3 class="tile-title"><i class="fa fa-info-circle" style="color: var(--color-primary);"></i> About Us Mission Section</h3>
        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">About Pill Tag</label>
              <input type="text" name="about_pill" class="form-control" value="<?php echo htmlentities(get_setting('about_pill', 'OUR MISSION')); ?>">
            </div>
          </div>
          <div class="col-md-8">
            <div class="form-group">
              <label class="control-label">About Main Title *</label>
              <input type="text" name="about_title" class="form-control" value="<?php echo htmlentities(get_setting('about_title', 'Empowering Lifelong Fitness')); ?>" required>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="control-label">About Description / Mission Statement</label>
          <textarea name="about_text" class="form-control" rows="5"><?php echo htmlentities(get_setting('about_text', 'Welcome to GYM MS! We are a collective of fitness enthusiasts, certified coaches, and nutritionists dedicated to redefining your health journey.')); ?></textarea>
        </div>
      </div>

      <!-- 3. Footer Copy -->
      <div class="tile">
        <h3 class="tile-title"><i class="fa fa-level-down" style="color: var(--color-primary);"></i> Footer Disclaimer &amp; Branding</h3>
        <div class="form-group">
          <label class="control-label">Footer Copyright / Blurb</label>
          <input type="text" name="footer_copy" class="form-control" value="<?php echo htmlentities(get_setting('footer_copy', 'GYM Management System. Crafted with iOS-inspired Glassmorphism & High-Performance Motion.')); ?>">
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 15px;">
          <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">
            <i class="fa fa-save"></i> Save Content Sections
          </button>
        </div>
      </div>
    </form>

    <!-- 4. Gym Facilities / Highlights CRUD -->
    <div class="tile">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <h3 class="tile-title" style="margin: 0;"><i class="fa fa-star" style="color: var(--color-primary);"></i> Gym Facilities &amp; Pillar Highlights</h3>
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addFeatureModal">
          <i class="fa fa-plus"></i> Add Highlight Feature
        </button>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-bordered">
          <thead>
            <tr>
              <th style="width: 60px;">Icon</th>
              <th>Feature Title</th>
              <th>Description</th>
              <th style="width: 80px;">Order</th>
              <th style="width: 100px;">Status</th>
              <th style="width: 130px; text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($features)): foreach ($features as $f): ?>
            <tr>
              <td style="text-align: center; font-size: 18px; color: var(--color-primary);">
                <i class="fa <?php echo htmlentities($f->icon); ?>"></i>
              </td>
              <td><strong><?php echo htmlentities($f->title); ?></strong></td>
              <td style="color: var(--text-secondary);"><?php echo htmlentities($f->description); ?></td>
              <td><?php echo $f->display_order; ?></td>
              <td>
                <?php if ($f->is_active): ?>
                  <span class="ios-badge ios-badge-success">Active</span>
                <?php else: ?>
                  <span class="ios-badge" style="background: rgba(255,255,255,0.08); color: var(--text-muted);">Hidden</span>
                <?php endif; ?>
              </td>
              <td style="text-align: right;">
                <a href="website-content.php?action=toggle&fid=<?php echo $f->id; ?>" class="btn btn-sm btn-info" title="Toggle Visibility" style="border-radius: 8px;">
                  <i class="fa <?php echo $f->is_active ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                </a>
                <a href="website-content.php?action=delete&fid=<?php echo $f->id; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this highlight feature?');" title="Delete" style="border-radius: 8px;">
                  <i class="fa fa-trash"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">No features added yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <!-- Add Feature Modal -->
  <div class="modal fade" id="addFeatureModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content" style="background: rgba(15,23,42,0.95); backdrop-filter: blur(28px); border: 1px solid rgba(255,255,255,0.18); border-radius: 24px; color: #fff;">
        <div class="modal-header" style="border-bottom: 1px solid rgba(255,255,255,0.1);">
          <h5 class="modal-title"><i class="fa fa-plus-circle" style="color: var(--color-primary);"></i> Add Facility Feature</h5>
          <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.8;">&times;</button>
        </div>
        <form method="post">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="add_feature" value="1">
          <div class="modal-body">
            <div class="form-group">
              <label class="control-label">Feature Title *</label>
              <input type="text" name="f_title" class="form-control" placeholder="e.g. Steam & Sauna Deck" required>
            </div>
            <div class="form-group">
              <label class="control-label">Short Description *</label>
              <textarea name="f_desc" class="form-control" rows="3" placeholder="Brief summary of benefits or access." required></textarea>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label">FontAwesome Icon Class</label>
                  <input type="text" name="f_icon" class="form-control" value="fa-bolt" placeholder="e.g. fa-trophy, fa-heartbeat">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label">Display Order</label>
                  <input type="number" name="f_order" class="form-control" value="1">
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer" style="border-top: 1px solid rgba(255,255,255,0.1);">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Add Feature</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="js/jquery-3.2.1.min.js"></script>
  <script src="js/popper.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/main.js"></script>
</body>
</html>
