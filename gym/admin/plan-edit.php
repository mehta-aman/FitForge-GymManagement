<?php
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$id = intval($_GET['id'] ?? 0);
$isEdit = ($id > 0);
$msg = '';
$err = '';

$plan = [
    'titlename' => '',
    'category' => '',
    'PackageType' => '',
    'PackageDuratiobn' => '1 Month',
    'Price' => '',
    'uploadphoto' => '',
    'Description' => '',
    'is_active' => 1
];

if ($isEdit) {
    $stmt = $dbh->prepare("SELECT * FROM tbladdpackage WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        header('location:plans.php');
        exit();
    }
    $plan = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = "Security token mismatch. Please try again.";
    } else {
        $title = trim($_POST['titlename'] ?? '');
        $category = intval($_POST['category'] ?? 0);
        $packageType = intval($_POST['PackageType'] ?? 0);
        $duration = trim($_POST['PackageDuratiobn'] ?? '1 Month');
        $price = floatval($_POST['Price'] ?? 0);
        $description = trim($_POST['Description'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $photoPath = $plan['uploadphoto'];

        if (empty($title) || $category <= 0 || $price <= 0) {
            $err = "Please enter a valid plan title, category, and price.";
        } else {
            // Check for photo upload
            if (isset($_FILES['plan_photo']) && $_FILES['plan_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadRes = handle_image_upload($_FILES['plan_photo'], 'uploads/plans');
                if ($uploadRes['success']) {
                    $photoPath = $uploadRes['path'];
                } else {
                    $err = "Photo upload error: " . $uploadRes['error'];
                }
            }

            if (empty($err)) {
                if ($isEdit) {
                    $updateStmt = $dbh->prepare("UPDATE tbladdpackage SET titlename = ?, category = ?, PackageType = ?, PackageDuratiobn = ?, Price = ?, Description = ?, uploadphoto = ?, is_active = ? WHERE id = ?");
                    $updateStmt->execute([$title, $category, $packageType, $duration, $price, $description, $photoPath, $isActive, $id]);
                    header("location:plans.php?msg=" . urlencode("Plan updated successfully!"));
                    exit();
                } else {
                    $insStmt = $dbh->prepare("INSERT INTO tbladdpackage (titlename, category, PackageType, PackageDuratiobn, Price, Description, uploadphoto, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $insStmt->execute([$title, $category, $packageType, $duration, $price, $description, $photoPath, $isActive]);
                    header("location:plans.php?msg=" . urlencode("New membership plan created successfully!"));
                    exit();
                }
            }
        }
    }
}

// Fetch categories and types
$categories = $dbh->query("SELECT id, category_name FROM tblcategory ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$packageTypes = $dbh->query("SELECT id, PackageName, cate_id FROM tblpackage ORDER BY PackageName ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?php echo $isEdit ? 'Edit Plan' : 'New Plan'; ?> — Admin</title>
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
        <h1>
          <i class="fa <?php echo $isEdit ? 'fa-pencil-square-o' : 'fa-plus-circle'; ?>" style="color: var(--color-primary); margin-right: 8px;"></i>
          <?php echo $isEdit ? 'Edit Membership Plan' : 'Create New Membership Plan'; ?>
        </h1>
        <p><?php echo $isEdit ? 'Update pricing, benefits, and media for this package' : 'Configure a new subscription tier for your members'; ?></p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="plans.php">Plans</a></li>
        <li class="breadcrumb-item active"><?php echo $isEdit ? 'Edit' : 'Create'; ?></li>
      </ul>
    </div>

    <?php if ($err): ?>
      <div class="ios-badge ios-badge-danger" style="display: flex; margin-bottom: 24px; padding: 14px 20px; border-radius: 16px; font-size: 14px;">
        <i class="fa fa-exclamation-triangle" style="font-size: 18px; margin-right: 8px;"></i>
        <span><?php echo htmlentities($err); ?></span>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <?php echo csrf_input(); ?>
      <div class="row">
        <!-- Main Plan Details -->
        <div class="col-lg-8">
          <div class="tile">
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">
              <i class="fa fa-info-circle" style="color: var(--color-primary); margin-right: 6px;"></i> Plan Specifications
            </h3>

            <div class="form-group">
              <label style="font-weight: 600; font-size: 13.5px;">Plan Title Name *</label>
              <input type="text" name="titlename" class="form-control" required placeholder="e.g. VIP All-Access Quarterly Pass" value="<?php echo htmlspecialchars($plan['titlename']); ?>">
            </div>

            <div class="row">
              <div class="col-md-6 form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Category *</label>
                <select name="category" id="categorySelect" class="form-control" required>
                  <option value="" disabled selected>-- Select Category --</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo ($plan['category'] == $cat['id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($cat['category_name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-6 form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Package Type / Subcategory</label>
                <select name="PackageType" class="form-control">
                  <option value="0">-- Optional / General --</option>
                  <?php foreach ($packageTypes as $pt): ?>
                    <option value="<?php echo $pt['id']; ?>" <?php echo ($plan['PackageType'] == $pt['id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($pt['PackageName']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Duration *</label>
                <input type="text" name="PackageDuratiobn" class="form-control" required placeholder="e.g. 1 Month, 3 Months, 1 Year" value="<?php echo htmlspecialchars($plan['PackageDuratiobn'] ?? '1 Month'); ?>">
              </div>

              <div class="col-md-6 form-group">
                <label style="font-weight: 600; font-size: 13.5px;">Price (<?php echo get_currency_symbol(); ?>) *</label>
                <input type="number" step="0.01" name="Price" class="form-control" required placeholder="0.00" value="<?php echo htmlspecialchars($plan['Price']); ?>">
              </div>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
              <label style="font-weight: 600; font-size: 13.5px;">Description & Features Included</label>
              <textarea name="Description" class="form-control" rows="6" placeholder="Describe the inclusions (e.g. Unlimited gym floor access, locker room, sauna, free fitness evaluation...)"><?php echo htmlspecialchars($plan['Description'] ?? ''); ?></textarea>
              <small class="form-text text-muted">Use clear bullet lines for the best presentation on the website.</small>
            </div>
          </div>
        </div>

        <!-- Sidebar / Media & Status -->
        <div class="col-lg-4">
          <div class="tile">
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">
              <i class="fa fa-image" style="color: var(--color-accent); margin-right: 6px;"></i> Image & Visibility
            </h3>

            <div class="form-group">
              <label style="font-weight: 600; font-size: 13px;">Plan Cover Photo</label>
              <?php if (!empty($plan['uploadphoto'])): 
                $photoPath = $plan['uploadphoto'];
                $src = (strpos($photoPath, 'uploads/') !== false) ? ('../img/' . $photoPath) : ('packageimage/' . $photoPath);
              ?>
                <div style="margin-bottom: 12px; border-radius: 14px; overflow: hidden; border: 1px solid var(--glass-border); max-height: 180px;">
                  <img src="<?php echo htmlspecialchars($src); ?>" alt="Plan Photo" style="width: 100%; height: auto; object-fit: cover;">
                </div>
              <?php endif; ?>
              <input type="file" name="plan_photo" class="form-control" accept="image/*" style="padding: 6px;">
              <small class="form-text text-muted">Recommended: 800x600 JPG, PNG or WebP</small>
            </div>

            <div class="form-group">
              <label style="font-weight: 600; font-size: 13px;">Public Visibility</label>
              <div style="background: rgba(255,255,255,0.03); padding: 12px 16px; border-radius: 12px; border: 1px solid var(--glass-border);">
                <label style="margin: 0; cursor: pointer; display: flex; align-items: center; justify-content: space-between;">
                  <span>Active & Listed on Website</span>
                  <input type="checkbox" name="is_active" value="1" <?php echo !empty($plan['is_active']) ? 'checked' : ''; ?> style="width: 18px; height: 18px;">
                </label>
              </div>
            </div>

            <hr style="border-color: var(--glass-border); margin: 24px 0;">

            <button type="submit" class="ios-btn ios-btn-accent btn-block" style="padding: 12px; font-size: 15px;">
              <i class="fa fa-save"></i> <?php echo $isEdit ? 'Save Changes' : 'Publish Plan'; ?>
            </button>
            <a href="plans.php" class="btn btn-outline-light btn-block" style="border-radius: 14px; padding: 10px; margin-top: 10px;">
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
</body>
</html>
