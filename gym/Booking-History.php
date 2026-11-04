<?php 
session_start();
error_reporting(0);
require_once('include/config.php');
if(strlen($_SESSION["uid"]) == 0) {   
  header('location:login.php');
  exit();
} else {
  $uid = $_SESSION['uid'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>My Bookings — GYM MS</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php echo render_favicon_tags(); ?>
	<link rel="stylesheet" href="css/bootstrap.min.css"/>
	<link rel="stylesheet" href="css/font-awesome.min.css"/>
	<link rel="stylesheet" href="css/ios-glass.css"/>
</head>
<body>

	<?php include 'include/header.php';?>

	<div class="site-content-wrapper" style="padding: 40px 20px 80px;">
		<div class="container">
			<div class="text-center" style="margin-bottom: 40px;">
				<div class="ios-hero-pill">
					<i class="fa fa-history"></i> MEMBERSHIP TIMELINE
				</div>
				<h1 style="font-size: 40px; font-weight: 800; letter-spacing: -0.03em; color: #fff; margin-bottom: 8px;">
					Your Booking History
				</h1>
				<p style="color: var(--text-secondary); font-size: 16px;">
					Track your current subscriptions, plan durations, and payment receipts.
				</p>
			</div>

			<div class="ios-glass-table-wrapper">
				<div class="table-responsive">
					<table class="ios-glass-table">
						<thead>
							<tr>
								<th>#</th>
								<th>Date</th>
								<th>Plan Title</th>
								<th>Duration</th>
								<th>Price</th>
								<th>Category</th>
								<th>Type</th>
								<th style="text-align: right;">Action</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$sql = "SELECT t1.id as bookingid, t3.fname as Name, t3.email as email, t1.booking_date as bookingdate, t2.titlename as title, t2.PackageDuratiobn as PackageDuratiobn, t2.Price as Price, t2.Description as Description, t4.category_name as category_name, t5.PackageName as PackageName 
							FROM tblbooking as t1
							JOIN tbladdpackage as t2 ON t1.package_id = t2.id
							JOIN tbluser as t3 ON t1.userid = t3.id
							JOIN tblcategory as t4 ON t2.category = t4.id
							JOIN tblpackage as t5 ON t2.PackageType = t5.id
							WHERE t1.userid = :uid
							ORDER BY t1.id DESC";
							$query = $dbh->prepare($sql);
							$query->bindParam(':uid', $uid, PDO::PARAM_STR);
							$query->execute();
							$results = $query->fetchAll(PDO::FETCH_OBJ);
							$cnt = 1;
							if($query->rowCount() > 0) {
								foreach($results as $result) {
							?>
							<tr>
								<td style="color: var(--text-muted); font-weight: 600;"><?php echo $cnt;?></td>
								<td><?php echo date('M d, Y', strtotime($result->bookingdate)); ?></td>
								<td>
									<div style="font-weight: 700; color: #fff;"><?php echo htmlentities($result->title);?></div>
								</td>
								<td>
									<span class="ios-badge ios-badge-primary">
										<i class="fa fa-clock-o"></i> <?php echo htmlentities($result->PackageDuratiobn);?>
									</span>
								</td>
								<td>
									<span style="font-weight: 700; color: var(--color-primary); font-size: 16px;">
										<?php echo format_currency($result->Price);?>
									</span>
								</td>
								<td>
									<span class="ios-badge" style="background: rgba(255,255,255,0.08); color: var(--text-secondary); border: 1px solid rgba(255,255,255,0.14);">
										<?php echo htmlentities($result->category_name);?>
									</span>
								</td>
								<td>
									<span style="color: var(--text-secondary);"><?php echo htmlentities($result->PackageName);?></span>
								</td>
								<td style="text-align: right;">
									<a href="booking-details.php?bookingid=<?php echo htmlentities($result->bookingid);?>" class="ios-btn ios-btn-primary" style="padding: 6px 14px; font-size: 13px; border-radius: 12px;">
										<i class="fa fa-eye"></i> Receipt
									</a>
								</td>
							</tr>
							<?php 
								$cnt++;
								} 
							} else { 
							?>
							<tr>
								<td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
									<i class="fa fa-folder-open-o" style="font-size: 32px; display: block; margin-bottom: 10px;"></i>
									No membership bookings found. <a href="index.php" style="color: var(--color-primary); font-weight: 600;">Browse Plans</a>
								</td>
							</tr>
							<?php } ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<?php include 'include/footer.php'; ?>
	</div>

	<script src="js/vendor/jquery-3.2.1.min.js"></script>
	<script src="js/bootstrap.min.js"></script>
</body>
</html>
<?php } ?>
