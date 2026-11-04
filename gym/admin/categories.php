<?php
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$msg = '';
$err = '';

// Handle Category CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = "Security token mismatch. Please try again.";
    } else {
        $action = $_POST['action'];

        // Add Category
        if ($action === 'add_category') {
            $catName = trim($_POST['category_name'] ?? '');
            if (empty($catName)) {
                $err = "Category name cannot be empty.";
            } else {
                $stmt = $dbh->prepare("INSERT INTO tblcategory (category_name) VALUES (?)");
                $stmt->execute([$catName]);
                $msg = "Category '$catName' created successfully.";
            }
        }

        // Edit Category
        elseif ($action === 'edit_category') {
            $catId = intval($_POST['category_id'] ?? 0);
            $catName = trim($_POST['category_name'] ?? '');
            if (empty($catName) || $catId <= 0) {
                $err = "Category name cannot be empty.";
            } else {
                $stmt = $dbh->prepare("UPDATE tblcategory SET category_name = ? WHERE id = ?");
                $stmt->execute([$catName, $catId]);
                $msg = "Category updated successfully.";
            }
        }

        // Add Sub-package
        elseif ($action === 'add_package_type') {
            $cateId = intval($_POST['cate_id'] ?? 0);
            $pkgName = trim($_POST['PackageName'] ?? '');
            if (empty($pkgName) || $cateId <= 0) {
                $err = "Please select a category and enter a package type name.";
            } else {
                $stmt = $dbh->prepare("INSERT INTO tblpackage (cate_id, PackageName) VALUES (?, ?)");
                $stmt->execute([$cateId, $pkgName]);
                $msg = "Package type '$pkgName' created successfully.";
            }
        }

        // Edit Sub-package
        elseif ($action === 'edit_package_type') {
            $pkgId = intval($_POST['pkg_id'] ?? 0);
            $cateId = intval($_POST['cate_id'] ?? 0);
            $pkgName = trim($_POST['PackageName'] ?? '');
            if (empty($pkgName) || $pkgId <= 0 || $cateId <= 0) {
                $err = "All fields are required.";
            } else {
                $stmt = $dbh->prepare("UPDATE tblpackage SET cate_id = ?, PackageName = ? WHERE id = ?");
                $stmt->execute([$cateId, $pkgName, $pkgId]);
                $msg = "Package type updated successfully.";
            }
        }
    }
}

// Handle Delete Category
if (isset($_GET['del_cat'])) {
    $delCatId = intval($_GET['del_cat']);
    $planCount = $dbh->query("SELECT count(*) FROM tbladdpackage WHERE category = $delCatId")->fetchColumn();
    if ($planCount > 0) {
        $err = "Cannot delete category: $planCount membership plans are currently assigned to it.";
    } else {
        $stmt = $dbh->prepare("DELETE FROM tblcategory WHERE id = ?");
        $stmt->execute([$delCatId]);
        $msg = "Category deleted successfully.";
    }
}

// Handle Delete Package Type
if (isset($_GET['del_pkg'])) {
    $delPkgId = intval($_GET['del_pkg']);
    $planCount = $dbh->query("SELECT count(*) FROM tbladdpackage WHERE PackageType = $delPkgId")->fetchColumn();
    if ($planCount > 0) {
        $err = "Cannot delete package type: $planCount membership plans are currently assigned to it.";
    } else {
        $stmt = $dbh->prepare("DELETE FROM tblpackage WHERE id = ?");
        $stmt->execute([$delPkgId]);
        $msg = "Package type deleted successfully.";
    }
}

// Fetch Categories with Plan Counts
$categories = $dbh->query("SELECT c.*, (SELECT count(*) FROM tbladdpackage p WHERE p.category = c.id) as plan_count FROM tblcategory c ORDER BY c.id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Package Types with Category Name
$packageTypes = $dbh->query("SELECT p.*, c.category_name, (SELECT count(*) FROM tbladdpackage ap WHERE ap.PackageType = p.id) as plan_count FROM tblpackage p LEFT JOIN tblcategory c ON p.cate_id = c.id ORDER BY p.id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Categories & Package Types — Admin</title>
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
        <h1><i class="fa fa-tags" style="color: var(--color-primary); margin-right: 8px;"></i> Categories & Package Types</h1>
        <p>Organize gym offerings, disciplines, workout tiers, and package groupings</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Categories</li>
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
      <!-- Primary Categories Card -->
      <div class="col-lg-6">
        <div class="tile">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="font-size: 18px; font-weight: 700; margin: 0;">
              <i class="fa fa-folder-open-o" style="color: var(--color-primary); margin-right: 6px;"></i> Primary Categories
            </h3>
            <button type="button" class="ios-btn ios-btn-accent" style="padding: 7px 14px; font-size: 12.5px; border-radius: 10px;" data-toggle="modal" data-target="#addCatModal">
              <i class="fa fa-plus"></i> New Category
            </button>
          </div>

          <div class="table-responsive">
            <table class="table table-hover table-bordered" style="margin: 0;">
              <thead>
                <tr>
                  <th style="width: 50px;">#</th>
                  <th>Category Name</th>
                  <th>Plans</th>
                  <th style="text-align: right; width: 110px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($categories)): foreach ($categories as $c): ?>
                <tr>
                  <td style="color: var(--text-muted); font-weight: 600;">#<?php echo $c['id']; ?></td>
                  <td style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($c['category_name']); ?></td>
                  <td><span class="ios-badge ios-badge-info"><?php echo $c['plan_count']; ?> plans</span></td>
                  <td style="text-align: right;">
                    <button type="button" class="btn btn-sm btn-primary edit-cat-btn" data-id="<?php echo $c['id']; ?>" data-name="<?php echo htmlspecialchars($c['category_name']); ?>" style="border-radius: 8px; padding: 4px 8px;">
                      <i class="fa fa-pencil"></i>
                    </button>
                    <a href="categories.php?del_cat=<?php echo $c['id']; ?>" class="btn btn-sm btn-danger" style="border-radius: 8px; padding: 4px 8px;" onclick="return confirm('Delete this category?');">
                      <i class="fa fa-trash"></i>
                    </a>
                  </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 20px;">No categories configured.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Package Types / Subcategories Card -->
      <div class="col-lg-6">
        <div class="tile">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="font-size: 18px; font-weight: 700; margin: 0;">
              <i class="fa fa-th-list" style="color: var(--color-accent); margin-right: 6px;"></i> Package Types
            </h3>
            <button type="button" class="ios-btn ios-btn-accent" style="padding: 7px 14px; font-size: 12.5px; border-radius: 10px;" data-toggle="modal" data-target="#addPkgModal">
              <i class="fa fa-plus"></i> New Type
            </button>
          </div>

          <div class="table-responsive">
            <table class="table table-hover table-bordered" style="margin: 0;">
              <thead>
                <tr>
                  <th style="width: 50px;">#</th>
                  <th>Category</th>
                  <th>Type Name</th>
                  <th style="text-align: right; width: 110px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($packageTypes)): foreach ($packageTypes as $pt): ?>
                <tr>
                  <td style="color: var(--text-muted); font-weight: 600;">#<?php echo $pt['id']; ?></td>
                  <td style="color: var(--text-secondary);"><?php echo htmlspecialchars($pt['category_name'] ?? '-'); ?></td>
                  <td style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($pt['PackageName']); ?></td>
                  <td style="text-align: right;">
                    <button type="button" class="btn btn-sm btn-primary edit-pkg-btn" data-id="<?php echo $pt['id']; ?>" data-cat="<?php echo $pt['cate_id']; ?>" data-name="<?php echo htmlspecialchars($pt['PackageName']); ?>" style="border-radius: 8px; padding: 4px 8px;">
                      <i class="fa fa-pencil"></i>
                    </button>
                    <a href="categories.php?del_pkg=<?php echo $pt['id']; ?>" class="btn btn-sm btn-danger" style="border-radius: 8px; padding: 4px 8px;" onclick="return confirm('Delete this package type?');">
                      <i class="fa fa-trash"></i>
                    </a>
                  </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 20px;">No package types configured.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- Modal: Add Category -->
  <div class="modal fade" id="addCatModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content" style="background: rgba(18, 24, 38, 0.95); backdrop-filter: blur(24px); border: 1px solid var(--glass-border); border-radius: 24px; color: #fff;">
        <form method="post">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="action" value="add_category">
          <div class="modal-header" style="border-bottom: 1px solid var(--glass-border); padding: 20px 24px;">
            <h5 class="modal-title" style="font-weight: 700;"><i class="fa fa-folder-open-o text-primary"></i> Add Primary Category</h5>
            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body" style="padding: 24px;">
            <div class="form-group">
              <label style="font-weight: 600; font-size: 13px;">Category Name *</label>
              <input type="text" name="category_name" class="form-control" required placeholder="e.g. Strength & Conditioning, Yoga, CrossFit">
            </div>
          </div>
          <div class="modal-footer" style="border-top: 1px solid var(--glass-border); padding: 16px 24px;">
            <button type="button" class="btn btn-outline-light" data-dismiss="modal">Cancel</button>
            <button type="submit" class="ios-btn ios-btn-accent">Create Category</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: Edit Category -->
  <div class="modal fade" id="editCatModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content" style="background: rgba(18, 24, 38, 0.95); backdrop-filter: blur(24px); border: 1px solid var(--glass-border); border-radius: 24px; color: #fff;">
        <form method="post">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="action" value="edit_category">
          <input type="hidden" name="category_id" id="editCatId">
          <div class="modal-header" style="border-bottom: 1px solid var(--glass-border); padding: 20px 24px;">
            <h5 class="modal-title" style="font-weight: 700;"><i class="fa fa-pencil text-primary"></i> Edit Category</h5>
            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body" style="padding: 24px;">
            <div class="form-group">
              <label style="font-weight: 600; font-size: 13px;">Category Name *</label>
              <input type="text" name="category_name" id="editCatName" class="form-control" required>
            </div>
          </div>
          <div class="modal-footer" style="border-top: 1px solid var(--glass-border); padding: 16px 24px;">
            <button type="button" class="btn btn-outline-light" data-dismiss="modal">Cancel</button>
            <button type="submit" class="ios-btn ios-btn-accent">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: Add Package Type -->
  <div class="modal fade" id="addPkgModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content" style="background: rgba(18, 24, 38, 0.95); backdrop-filter: blur(24px); border: 1px solid var(--glass-border); border-radius: 24px; color: #fff;">
        <form method="post">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="action" value="add_package_type">
          <div class="modal-header" style="border-bottom: 1px solid var(--glass-border); padding: 20px 24px;">
            <h5 class="modal-title" style="font-weight: 700;"><i class="fa fa-th-list text-accent"></i> Add Package Type</h5>
            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body" style="padding: 24px;">
            <div class="form-group">
              <label style="font-weight: 600; font-size: 13px;">Belongs to Category *</label>
              <select name="cate_id" class="form-control" required>
                <option value="" disabled selected>-- Select Category --</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label style="font-weight: 600; font-size: 13px;">Type Name *</label>
              <input type="text" name="PackageName" class="form-control" required placeholder="e.g. Standard, Elite Pass, Weekend Pass">
            </div>
          </div>
          <div class="modal-footer" style="border-top: 1px solid var(--glass-border); padding: 16px 24px;">
            <button type="button" class="btn btn-outline-light" data-dismiss="modal">Cancel</button>
            <button type="submit" class="ios-btn ios-btn-accent">Create Type</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: Edit Package Type -->
  <div class="modal fade" id="editPkgModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content" style="background: rgba(18, 24, 38, 0.95); backdrop-filter: blur(24px); border: 1px solid var(--glass-border); border-radius: 24px; color: #fff;">
        <form method="post">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="action" value="edit_package_type">
          <input type="hidden" name="pkg_id" id="editPkgId">
          <div class="modal-header" style="border-bottom: 1px solid var(--glass-border); padding: 20px 24px;">
            <h5 class="modal-title" style="font-weight: 700;"><i class="fa fa-pencil text-accent"></i> Edit Package Type</h5>
            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body" style="padding: 24px;">
            <div class="form-group">
              <label style="font-weight: 600; font-size: 13px;">Belongs to Category *</label>
              <select name="cate_id" id="editPkgCat" class="form-control" required>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label style="font-weight: 600; font-size: 13px;">Type Name *</label>
              <input type="text" name="PackageName" id="editPkgName" class="form-control" required>
            </div>
          </div>
          <div class="modal-footer" style="border-top: 1px solid var(--glass-border); padding: 16px 24px;">
            <button type="button" class="btn btn-outline-light" data-dismiss="modal">Cancel</button>
            <button type="submit" class="ios-btn ios-btn-accent">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="js/jquery-3.2.1.min.js"></script>
  <script src="js/popper.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/main.js"></script>
  <script>
    $('.edit-cat-btn').on('click', function() {
      $('#editCatId').val($(this).data('id'));
      $('#editCatName').val($(this).data('name'));
      $('#editCatModal').modal('show');
    });

    $('.edit-pkg-btn').on('click', function() {
      $('#editPkgId').val($(this).data('id'));
      $('#editPkgCat').val($(this).data('cat'));
      $('#editPkgName').val($(this).data('name'));
      $('#editPkgModal').modal('show');
    });
  </script>
</body>
</html>
