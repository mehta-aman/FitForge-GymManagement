<?php
session_start();
error_reporting(0);
require_once('include/config.php');
if(empty($_SESSION["uid"])) {   
  header('location:login.php');
  exit();
} else {
  if(isset($_POST['submit'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
      $error = "Security token mismatch. Please try again.";
    } else {
      $curPass = $_POST['password'] ?? '';
      $newPass = $_POST['newpassword'] ?? '';
      $confirmPass = $_POST['confirmpassword'] ?? '';
      $userId = $_SESSION['uid'];

      if (empty($curPass) || empty($newPass)) {
        $error = "Please fill in all password fields.";
      } elseif ($newPass !== $confirmPass) {
        $error = "New Password and Confirm Password do not match!";
      } else {
        $userStmt = $dbh->prepare("SELECT id, password FROM tbluser WHERE id = ?");
        $userStmt->execute([$userId]);
        $userData = $userStmt->fetch(PDO::FETCH_ASSOC);

        if ($userData && verify_and_rehash_password($curPass, $userData['password'], 'tbluser', 'id', $userId, $dbh)) {
          $newHashed = password_hash($newPass, PASSWORD_DEFAULT);
          $upStmt = $dbh->prepare("UPDATE tbluser SET password = ? WHERE id = ?");
          $upStmt->execute([$newHashed, $userId]);
          $msg = "Your password has been successfully updated!";
        } else {
          $error = "Your current password does not match our records.";
        }
      }
    }
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Change Password — GYM MS</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php echo render_favicon_tags(); ?>
	<link rel="stylesheet" href="css/bootstrap.min.css"/>
	<link rel="stylesheet" href="css/font-awesome.min.css"/>
	<link rel="stylesheet" href="css/ios-glass.css"/>
	<script type="text/javascript">
	function checkPass() {
		if(document.chngpwd.newpassword.value != document.chngpwd.confirmpassword.value) {
			alert("New Password and Confirm Password do not match!");
			document.chngpwd.confirmpassword.focus();
			return false;
		}
		return true;
	}
	</script>
</head>
<body>

	<?php include 'include/header.php';?>

	<div class="site-content-wrapper" style="padding: 40px 20px 80px;">
		<div class="container">
			<div class="ios-auth-card">
				<div class="ios-auth-icon" style="background: linear-gradient(135deg, rgba(249, 115, 22, 0.2) 0%, rgba(234, 88, 12, 0.2) 100%); border-color: rgba(249, 115, 22, 0.4); color: var(--color-accent);">
					<i class="fa fa-key"></i>
				</div>
				<h2 class="ios-auth-title">Update Password</h2>
				<p class="ios-auth-subtitle">Ensure your account remains secure with a strong password</p>

				<?php if(!empty($msg)): ?>
					<div class="ios-badge ios-badge-success" style="display: flex; margin-bottom: 24px; padding: 12px 16px; border-radius: 14px; text-align: left;">
						<i class="fa fa-check-circle" style="font-size: 16px;"></i>
						<span><?php echo htmlentities($msg); ?></span>
					</div>
				<?php endif; ?>

				<?php if(!empty($error)): ?>
					<div class="ios-badge ios-badge-danger" style="display: flex; margin-bottom: 24px; padding: 12px 16px; border-radius: 14px; text-align: left;">
						<i class="fa fa-exclamation-circle" style="font-size: 16px;"></i>
						<span><?php echo htmlentities($error); ?></span>
					</div>
				<?php endif; ?>

				<form name="chngpwd" method="post" onSubmit="return checkPass();">
					<?php echo csrf_input(); ?>
					<div class="ios-form-group">
						<label class="ios-label">Current Password</label>
						<input type="password" name="password" class="ios-input" placeholder="Current password" required>
					</div>

					<div class="ios-form-group">
						<label class="ios-label">New Password</label>
						<input type="password" name="newpassword" class="ios-input" placeholder="At least 6 characters" required>
					</div>

					<div class="ios-form-group">
						<label class="ios-label">Confirm New Password</label>
						<input type="password" name="confirmpassword" class="ios-input" placeholder="Repeat new password" required>
					</div>

					<button type="submit" name="submit" class="ios-btn ios-btn-accent" style="width: 100%; margin-top: 10px;">
						<i class="fa fa-refresh"></i> Update Password
					</button>
				</form>
			</div>
		</div>

		<?php include 'include/footer.php'; ?>
	</div>

	<script src="js/vendor/jquery-3.2.1.min.js"></script>
	<script src="js/bootstrap.min.js"></script>
</body>
</html>
<?php } ?>
