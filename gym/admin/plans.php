<?php
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$msg = '';
$err = '';

// Toggle active status
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $planId = intval($_GET['id']);
    $curActive = $dbh->query("SELECT is_active FROM tbladdpackage WHERE id = $planId")->fetchColumn();
    $newActive = ($curActive == 1) ? 0 : 1;
    $stmt = $dbh->prepare("UPDATE tbladdpackage SET is_active = ? WHERE id = ?");
    $stmt->execute([$newActive, $planId]);
    $msg = "Plan status updated successfully.";
}

// Delete Plan
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $planId = intval($_GET['id']);
    // Check if memberships or bookings exist for this plan
    $hasSubs = $dbh->query("SELECT count(*) FROM tbl_memberships WHERE plan_id = $planId")->fetchColumn();
    $hasBookings = $dbh->query("SELECT count(*) FROM tblbooking WHERE package_id = '$planId'")->fetchColumn();
    if ($hasSubs > 0 || $hasBookings > 0) {
        $err = "Cannot delete this plan because active member subscriptions or historical bookings reference it. To preserve member history, deactivate the plan instead.";
    } else {
        $stmt = $dbh->prepare("DELETE FROM tbladdpackage WHERE id = ?");
        $stmt->execute([$planId]);
        $msg = "Plan deleted successfully.";
    }
}

// Search & Filter
$search = trim($_GET['search'] ?? '');
$catFilter = intval($_GET['cat'] ?? 0);

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(p.titlename LIKE :s OR p.Description LIKE :s)";
    $params[':s'] = "%$search%";
}

if ($catFilter > 0) {
    $where[] = "p.category = :cat";
    $params[':cat'] = $catFilter;
}

$whereSql = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

$plans = $dbh->prepare("SELECT p.*, c.category_name, pkg.PackageName FROM tbladdpackage p LEFT JOIN tblcategory c ON p.category = c.id LEFT JOIN tblpackage pkg ON p.PackageType = pkg.id $whereSql ORDER BY p.id DESC");
$plans->execute($params);
$planList = $plans->fetchAll(PDO::FETCH_ASSOC);

$categories = $dbh->query("SELECT id, category_name FROM tblcategory ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Membership Plans Catalog — Admin</title>
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
        <h1><i class="fa fa-cubes" style="color: var(--color-primary); margin-right: 8px;"></i> Membership Plans Catalog</h1>
        <p>Create, customize, and price your gym packages and membership offerings</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Plans</li>
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

    <!-- Controls -->
    <div class="tile" style="padding: 20px 24px;">
      <form method="get" class="row align-items-center" style="row-gap: 12px;">
        <div class="col-md-5">
          <input type="text" name="search" class="form-control" placeholder="Search plan name or description..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="col-md-3">
          <select name="cat" class="form-control" onchange="this.form.submit()">
            <option value="0">All Categories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?php echo $cat['id']; ?>" <?php echo ($catFilter == $cat['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($cat['category_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 text-md-right">
          <button type="submit" class="btn btn-primary" style="padding: 8px 16px; border-radius: 12px;">
            <i class="fa fa-search"></i> Filter
          </button>
          <a href="plan-edit.php" class="ios-btn ios-btn-accent" style="padding: 8px 18px; font-size: 13.5px; border-radius: 12px; margin-left: 6px;">
            <i class="fa fa-plus"></i> New Plan
          </a>
        </div>
      </form>
    </div>

    <!-- Plans Table -->
    <div class="tile">
      <div class="table-responsive">
        <table class="table table-hover table-bordered" style="margin: 0;">
          <thead>
            <tr>
              <th style="width: 70px;">Image</th>
              <th>Plan Title</th>
              <th>Category</th>
              <th>Duration</th>
              <th>Price</th>
              <th>Created Date</th>
              <th>Status</th>
              <th style="text-align: right; width: 150px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($planList)): foreach ($planList as $p): 
              $photo = $p['uploadphoto'];
              $imgSrc = 'img/default-plan.jpg';
              if (!empty($photo)) {
                if (file_exists('../' . $photo)) {
                  $imgSrc = '../' . $photo;
                } elseif (file_exists('../admin/packageimage/' . $photo)) {
                  $imgSrc = 'packageimage/' . $photo;
                }
              }
            ?>
            <tr>
              <td>
                <div style="width: 48px; height: 48px; border-radius: 12px; overflow: hidden; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; border: 1px solid var(--glass-border);">
                  <?php if (!empty($photo)): ?>
                    <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="Plan" style="width: 100%; height: 100%; object-fit: cover;">
                  <?php else: ?>
                    <i class="fa fa-cube text-muted"></i>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <strong style="color: #fff; font-size: 14.5px;"><?php echo htmlspecialchars($p['titlename']); ?></strong>
                <div style="font-size: 12px; color: var(--text-muted); max-width: 320px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                  <?php echo htmlspecialchars(strip_tags($p['Description'] ?? '')); ?>
                </div>
              </td>
              <td>
                <span class="ios-badge ios-badge-info"><?php echo htmlspecialchars($p['category_name'] ?? 'General'); ?></span>
              </td>
              <td style="color: var(--text-secondary);"><?php echo htmlspecialchars($p['PackageDuratiobn'] ?? '1 Month'); ?></td>
              <td style="font-weight: 700; color: #34d399; font-size: 15px;"><?php echo format_currency($p['Price']); ?></td>
              <td style="color: var(--text-muted); font-size: 13px;"><?php echo date('d M Y', strtotime($p['create_date'])); ?></td>
              <td>
                <?php if (!empty($p['is_active'])): ?>
                  <span class="ios-badge ios-badge-success">Active</span>
                <?php else: ?>
                  <span class="ios-badge ios-badge-danger">Inactive</span>
                <?php endif; ?>
              </td>
              <td style="text-align: right;">
                <a href="plan-edit.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-primary" title="Edit Plan" style="border-radius: 8px; padding: 4px 10px;">
                  <i class="fa fa-pencil"></i>
                </a>
                <a href="plans.php?action=toggle&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-secondary" title="<?php echo !empty($p['is_active']) ? 'Deactivate' : 'Activate'; ?>" style="border-radius: 8px; padding: 4px 10px;">
                  <i class="fa <?php echo !empty($p['is_active']) ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                </a>
                <a href="plans.php?action=delete&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-danger" title="Delete Plan" style="border-radius: 8px; padding: 4px 10px;" onclick="return confirm('Are you sure you want to delete this plan?');">
                  <i class="fa fa-trash"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">No membership plans found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <script src="js/jquery-3.2.1.min.js"></script>
  <script src="js/popper.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/main.js"></script>
</body>
</html>
