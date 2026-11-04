<?php
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$adminid = $_SESSION['adminid'];
$msg = '';
$err = '';

if (isset($_POST['submit'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = "Security token mismatch. Please try again.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');

        if (empty($name)) {
            $err = "Admin name cannot be empty.";
        } else {
            $stmt = $dbh->prepare("UPDATE tbladmin SET name = :name, mobile = :mobile WHERE id = :adminid");
            $stmt->execute([':name' => $name, ':mobile' => $mobile, ':adminid' => $adminid]);
            $_SESSION['name'] = $name;
            $msg = "Profile updated successfully!";
        }
    }
}

$query = $dbh->prepare("SELECT id, name, email, mobile, create_date FROM tbladmin WHERE id = ?");
$query->execute([$adminid]);
$admin = $query->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Profile — <?php echo htmlspecialchars(get_setting('gym_name', 'GYM MS')); ?></title>
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
        <h1><i class="fa fa-user-circle" style="color: var(--color-primary); margin-right: 8px;"></i> Administrator Profile</h1>
        <p>Manage your account contact details and system credentials</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Profile</li>
      </ul>
    </div>

    <div class="row">
      <div class="col-md-6">
        <div class="tile">
          <?php if ($msg): ?>
            <div class="ios-badge ios-badge-success" style="display: flex; margin-bottom: 20px; padding: 12px 18px; border-radius: 14px;">
              <i class="fa fa-check-circle" style="font-size: 16px; margin-right: 8px;"></i>
              <span><?php echo htmlentities($msg); ?></span>
            </div>
          <?php endif; ?>

          <?php if ($err): ?>
            <div class="ios-badge ios-badge-danger" style="display: flex; margin-bottom: 20px; padding: 12px 18px; border-radius: 14px;">
              <i class="fa fa-exclamation-triangle" style="font-size: 16px; margin-right: 8px;"></i>
              <span><?php echo htmlentities($err); ?></span>
            </div>
          <?php endif; ?>

          <form method="post" autocomplete="off">
            <?php echo csrf_input(); ?>
            <div class="form-group">
              <label style="font-weight: 600; font-size: 13.5px;">Full Name *</label>
              <input class="form-control" type="text" name="name" id="name" required value="<?php echo htmlspecialchars($admin['name'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label style="font-weight: 600; font-size: 13.5px;">Email Address</label>
              <input class="form-control" type="text" name="email" id="email" value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" readonly style="opacity: 0.7; cursor: not-allowed;">
              <small class="form-text text-muted">Email address is locked as the primary administrator login key.</small>
            </div>

            <div class="form-group">
              <label style="font-weight: 600; font-size: 13.5px;">Mobile Number</label>
              <input class="form-control" type="text" name="mobile" id="mobile" value="<?php echo htmlspecialchars($admin['mobile'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label style="font-weight: 600; font-size: 13.5px;">Account Registered On</label>
              <input class="form-control" type="text" value="<?php echo date('d M Y, h:i A', strtotime($admin['create_date'])); ?>" readonly style="opacity: 0.7; cursor: not-allowed;">
            </div>

            <button type="submit" name="submit" class="ios-btn ios-btn-accent" style="padding: 10px 24px; border-radius: 12px; margin-top: 10px;">
              <i class="fa fa-save"></i> Save Profile Details
            </button>
            <a href="change-password.php" class="btn btn-outline-light" style="padding: 10px 18px; border-radius: 12px; margin-top: 10px; margin-left: 6px;">
              <i class="fa fa-key"></i> Change Password
            </a>
          </form>
        </div>
      </div>
    </div>
  </main>

  <script src="js/jquery-3.2.1.min.js"></script>
  <script src="js/popper.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/main.js"></script>
</body>
</html>
