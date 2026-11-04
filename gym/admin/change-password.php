<?php
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$msg = '';
$err = '';

if (isset($_POST['submit'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = "Security token mismatch. Please try again.";
    } else {
        $curPass = $_POST['password'] ?? '';
        $newPass = $_POST['newpassword'] ?? '';
        $confirmPass = $_POST['confirmpassword'] ?? '';
        $adminId = $_SESSION['adminid'];

        if (empty($curPass) || empty($newPass)) {
            $err = "Please enter both old and new passwords.";
        } elseif ($newPass !== $confirmPass) {
            $err = "New Password and Confirm Password do not match!";
        } else {
            $adminStmt = $dbh->prepare("SELECT id, password FROM tbladmin WHERE id = ?");
            $adminStmt->execute([$adminId]);
            $adminData = $adminStmt->fetch(PDO::FETCH_ASSOC);

            if ($adminData && verify_and_rehash_password($curPass, $adminData['password'], 'tbladmin', 'id', $adminId, $dbh)) {
                $newHashed = password_hash($newPass, PASSWORD_DEFAULT);
                $upStmt = $dbh->prepare("UPDATE tbladmin SET password = ? WHERE id = ?");
                $upStmt->execute([$newHashed, $adminId]);
                $msg = "Your admin password has been successfully updated!";
            } else {
                $err = "Your current password does not match our records.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Change Password — Admin</title>
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
        <h1><i class="fa fa-key" style="color: var(--color-primary); margin-right: 8px;"></i> Change Password</h1>
        <p>Update your admin portal authentication credentials</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Change Password</li>
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
              <label style="font-weight: 600; font-size: 13.5px;">Current Password *</label>
              <input type="password" name="password" class="form-control" required placeholder="Enter current password">
            </div>

            <div class="form-group">
              <label style="font-weight: 600; font-size: 13.5px;">New Password *</label>
              <input type="password" name="newpassword" class="form-control" required placeholder="At least 6 characters">
            </div>

            <div class="form-group">
              <label style="font-weight: 600; font-size: 13.5px;">Confirm New Password *</label>
              <input type="password" name="confirmpassword" class="form-control" required placeholder="Repeat new password">
            </div>

            <button type="submit" name="submit" class="ios-btn ios-btn-accent" style="padding: 10px 24px; border-radius: 12px; margin-top: 10px;">
              <i class="fa fa-check"></i> Update Password
            </button>
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
