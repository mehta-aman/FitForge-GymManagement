<?php
session_start();
error_reporting(0);
require_once('include/config.php');
if(strlen($_SESSION["uid"]) == 0) {   
  header('location:login.php');
  exit();
} else {
  $uid = $_SESSION['uid'];

  if(isset($_POST['submit'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
      echo "<script>alert('Security token mismatch. Please try again.');</script>";
    } else {
      $fname = trim($_POST['fname'] ?? '');
      $lname = trim($_POST['lname'] ?? '');
      $mobile = trim($_POST['mobile'] ?? '');
      $city = trim($_POST['city'] ?? '');
      $state = trim($_POST['state'] ?? '');
      $address = trim($_POST['address'] ?? '');
      $sql = "UPDATE tbluser SET fname=:fname, lname=:lname, mobile=:mobile, city=:city, state=:state, address=:Address WHERE id=:uid";
      $query = $dbh->prepare($sql);
      $query->bindParam(':fname', $fname, PDO::PARAM_STR);
      $query->bindParam(':lname', $lname, PDO::PARAM_STR);
      $query->bindParam(':mobile', $mobile, PDO::PARAM_STR);
      $query->bindParam(':city', $city, PDO::PARAM_STR);
      $query->bindParam(':state', $state, PDO::PARAM_STR);
      $query->bindParam(':Address', $address, PDO::PARAM_STR);
      $query->bindParam(':uid', $uid, PDO::PARAM_STR);
      $query->execute();
      $_SESSION['name'] = $fname;
      echo "<script>alert('Profile updated successfully!');</script>";
      echo "<script>window.location.href='profile.php';</script>";
      exit();
    }
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>My Profile — GYM MS</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php echo render_favicon_tags(); ?>
	<link rel="stylesheet" href="css/bootstrap.min.css"/>
	<link rel="stylesheet" href="css/font-awesome.min.css"/>
	<link rel="stylesheet" href="css/ios-glass.css"/>
</head>
<body>

	<?php include 'include/header.php';?>

	<div class="site-content-wrapper" style="padding: 40px 20px 80px;">
		<div class="container" style="max-width: 760px;">
			<div class="text-center" style="margin-bottom: 36px;">
				<div class="ios-hero-pill">
					<i class="fa fa-id-card"></i> ACCOUNT SETTINGS
				</div>
				<h1 style="font-size: 38px; font-weight: 800; letter-spacing: -0.03em; color: #fff; margin-bottom: 6px;">
					Personal Profile
				</h1>
				<p style="color: var(--text-secondary); font-size: 15px;">
					Manage your contact details and registered gym address.
				</p>
			</div>

			<?php 
			$sql = "SELECT id, fname, lname, email, mobile, password, address, state, city, create_date FROM tbluser WHERE id=:uid";
			$query = $dbh->prepare($sql);
			$query->bindParam(':uid', $uid, PDO::PARAM_STR);
			$query->execute();
			$results = $query->fetchAll(PDO::FETCH_OBJ);
			if($query->rowCount() > 0) {
				foreach($results as $result) {
			?>
			<div class="ios-glass" style="padding: 36px;">
				<form method="post" autocomplete="off">
					<?php echo csrf_input(); ?>
					<div class="row">
						<div class="col-md-6">
							<div class="ios-form-group">
								<label class="ios-label">First Name</label>
								<input type="text" name="fname" class="ios-input" value="<?php echo htmlentities($result->fname);?>" required>
							</div>
						</div>
						<div class="col-md-6">
							<div class="ios-form-group">
								<label class="ios-label">Last Name</label>
								<input type="text" name="lname" class="ios-input" value="<?php echo htmlentities($result->lname);?>">
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="ios-form-group">
								<label class="ios-label">Email Address (Read-only)</label>
								<input type="email" name="email" class="ios-input" value="<?php echo htmlentities($result->email);?>" readonly style="opacity: 0.7; cursor: not-allowed;">
							</div>
						</div>
						<div class="col-md-6">
							<div class="ios-form-group">
								<label class="ios-label">Mobile Number</label>
								<input type="text" name="mobile" class="ios-input" value="<?php echo htmlentities($result->mobile);?>" required>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="ios-form-group">
								<label class="ios-label">State / Region</label>
								<input type="text" name="state" class="ios-input" value="<?php echo htmlentities($result->state);?>">
							</div>
						</div>
						<div class="col-md-6">
							<div class="ios-form-group">
								<label class="ios-label">City</label>
								<input type="text" name="city" class="ios-input" value="<?php echo htmlentities($result->city);?>">
							</div>
						</div>
					</div>

					<div class="ios-form-group">
						<label class="ios-label">Residential Address</label>
						<input type="text" name="address" class="ios-input" value="<?php echo htmlentities($result->address);?>" placeholder="Street address">
					</div>

					<div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 10px;">
						<button type="submit" name="submit" class="ios-btn ios-btn-primary">
							<i class="fa fa-save"></i> Save Changes
						</button>
					</div>
				</form>
			</div>
			<?php } } ?>
		</div>

		<?php include 'include/footer.php'; ?>
	</div>

	<script src="js/vendor/jquery-3.2.1.min.js"></script>
	<script src="js/bootstrap.min.js"></script>
</body>
</html>
<?php } ?>
