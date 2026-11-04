<?php
error_reporting(0);
require_once('include/config.php');

if(isset($_POST['submit'])) { 
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = "Security token mismatch. Please try again.";
  } else {
    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $Password = $_POST['password'] ?? '';
    $RepeatPassword = $_POST['RepeatPassword'] ?? '';

    $usermatch = $dbh->prepare("SELECT mobile,email FROM tbluser WHERE (email=:usreml || mobile=:mblenmbr)");
    $usermatch->execute(array(':usreml' => $email, ':mblenmbr' => $mobile)); 
    $row = $usermatch->fetch(PDO::FETCH_ASSOC);
    $usrdbeml = $row['email'] ?? '';
    $usrdbmble = $row['mobile'] ?? '';

    if(empty($fname)) {
      $error = "Please Enter First Name";
    } else if(empty($mobile)) {
      $error = "Please Enter Mobile Number";
    } else if(empty($email)) {
      $error = "Please Enter Email";
    } else if($email == $usrdbeml || $mobile == $usrdbmble) {
      $error = "Email ID or Mobile Number already exists!";
    } else if($Password == "" || $RepeatPassword == "") {
      $error = "Password and Confirm Password cannot be empty!";
    } else if($Password != $RepeatPassword) {
      $error = "Passwords do not match!";
    } else {
      $pass = password_hash($Password, PASSWORD_DEFAULT);
      $sql = "INSERT INTO tbluser (fname,lname,email,mobile,state,city,password,status) Values(:fname,:lname,:email,:mobile,:state,:city,:Password,'active')";
      $query = $dbh->prepare($sql);
      $query->bindParam(':fname', $fname, PDO::PARAM_STR);
      $query->bindParam(':lname', $lname, PDO::PARAM_STR);
      $query->bindParam(':email', $email, PDO::PARAM_STR);
      $query->bindParam(':mobile', $mobile, PDO::PARAM_STR);
      $query->bindParam(':state', $state, PDO::PARAM_STR);
      $query->bindParam(':city', $city, PDO::PARAM_STR);
      $query->bindParam(':Password', $pass, PDO::PARAM_STR);
      $query->execute();
      $lastInsertId = $dbh->lastInsertId();
      if($lastInsertId > 0) {
        echo "<script>alert('Registration successful! Please login.');</script>";
        echo "<script>window.location.href='login.php';</script>";
        exit();
      } else {
        $error = "Registration failed, please try again.";
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Create Account — GYM MS</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php echo render_favicon_tags(); ?>
	<link rel="stylesheet" href="css/bootstrap.min.css"/>
	<link rel="stylesheet" href="css/font-awesome.min.css"/>
	<link rel="stylesheet" href="css/ios-glass.css"/>
</head>
<body>

	<?php include 'include/header.php';?>

	<div class="site-content-wrapper" style="padding: 40px 20px;">
		<div class="container">
			<div class="ios-auth-card" style="max-width: 650px;">
				<div class="ios-auth-icon">
					<i class="fa fa-user-plus"></i>
				</div>
				<h2 class="ios-auth-title">Join Gym Club</h2>
				<p class="ios-auth-subtitle">Register in seconds and unlock full access to gym facilities</p>

				<?php if(!empty($error)): ?>
					<div class="ios-badge ios-badge-danger" style="display: flex; margin-bottom: 24px; padding: 12px 16px; border-radius: 14px; text-align: left;">
						<i class="fa fa-exclamation-circle" style="font-size: 16px;"></i>
						<span><?php echo htmlentities($error); ?></span>
					</div>
				<?php endif; ?>

				<form method="post" autocomplete="off">
					<?php echo csrf_input(); ?>
					<div class="row">
						<div class="col-md-6">
							<div class="ios-form-group">
								<label class="ios-label">First Name *</label>
								<input type="text" name="fname" class="ios-input" placeholder="e.g. John" value="<?php echo htmlentities($fname);?>" required>
							</div>
						</div>
						<div class="col-md-6">
							<div class="ios-form-group">
								<label class="ios-label">Last Name</label>
								<input type="text" name="lname" class="ios-input" placeholder="e.g. Doe" value="<?php echo htmlentities($lname);?>">
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="ios-form-group">
								<label class="ios-label">Email Address *</label>
								<input type="email" name="email" class="ios-input" placeholder="john@example.com" value="<?php echo htmlentities($email);?>" required>
							</div>
						</div>
						<div class="col-md-6">
							<div class="ios-form-group">
								<label class="ios-label">Mobile Number *</label>
								<input type="text" name="mobile" class="ios-input" placeholder="e.g. 9876543210" value="<?php echo htmlentities($mobile);?>" required>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="ios-form-group">
								<label class="ios-label">State</label>
								<input type="text" name="state" class="ios-input" placeholder="State/Region" value="<?php echo htmlentities($state);?>">
							</div>
						</div>
						<div class="col-md-6">
							<div class="ios-form-group">
								<label class="ios-label">City</label>
								<input type="text" name="city" class="ios-input" placeholder="City" value="<?php echo htmlentities($city);?>">
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="ios-form-group">
								<label class="ios-label">Password *</label>
								<input type="password" name="password" class="ios-input" placeholder="Create password" required>
							</div>
						</div>
						<div class="col-md-6">
							<div class="ios-form-group">
								<label class="ios-label">Confirm Password *</label>
								<input type="password" name="RepeatPassword" class="ios-input" placeholder="Repeat password" required>
							</div>
						</div>
					</div>

					<button type="submit" name="submit" class="ios-btn ios-btn-primary" style="width: 100%; margin-top: 15px;">
						<i class="fa fa-check"></i> Complete Registration
					</button>

					<div style="margin-top: 24px; font-size: 14px; color: var(--text-secondary);">
						Already have an account? <a href="login.php" style="color: var(--color-primary); font-weight: 600; text-decoration: none;">Sign In here</a>
					</div>
				</form>
			</div>
		</div>

		<?php include 'include/footer.php'; ?>
	</div>

	<script src="js/vendor/jquery-3.2.1.min.js"></script>
	<script src="js/bootstrap.min.js"></script>
</body>
</html>
