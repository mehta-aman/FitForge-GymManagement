<?php 
session_start();
error_reporting(0);
require_once('include/config.php');
if(strlen($_SESSION["uid"]) == 0) {   
  header('location:login.php');
  exit();
} else {
  $uid = $_SESSION['uid'];
  $bookindid = $_GET['bookingid'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Booking Receipt #<?php echo htmlentities($bookindid);?> — GYM MS</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php echo render_favicon_tags(); ?>
	<link rel="stylesheet" href="css/bootstrap.min.css"/>
	<link rel="stylesheet" href="css/font-awesome.min.css"/>
	<link rel="stylesheet" href="css/ios-glass.css"/>
</head>
<body>

	<?php include 'include/header.php';?>

	<div class="site-content-wrapper" style="padding: 40px 20px 80px;">
		<div class="container" style="max-width: 900px;">
			<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
				<a href="Booking-History.php" class="ios-btn ios-btn-glass" style="padding: 8px 16px; font-size: 13px;">
					<i class="fa fa-arrow-left"></i> Back to Bookings
				</a>
				<div class="ios-hero-pill" style="margin: 0;">
					<i class="fa fa-ticket"></i> RECEIPT #<?php echo htmlentities($bookindid);?>
				</div>
			</div>

			<?php 
			$sql = "SELECT t1.id as bookingid, t3.fname as Name, t3.email as email, t1.booking_date as bookingdate, t2.titlename as title, t2.PackageDuratiobn as PackageDuratiobn, t2.Price as Price, t2.Description as Description, t4.category_name as category_name, t5.PackageName as PackageName, payment, paymentType 
			FROM tblbooking as t1
			JOIN tbladdpackage as t2 ON t1.package_id = t2.id
			JOIN tbluser as t3 ON t1.userid = t3.id
			JOIN tblcategory as t4 ON t2.category = t4.id
			JOIN tblpackage as t5 ON t2.PackageType = t5.id
			WHERE t1.id = :bookindid";
			$query = $dbh->prepare($sql);
			$query->bindParam(':bookindid', $bookindid, PDO::PARAM_STR);
			$query->execute();
			$results = $query->fetchAll(PDO::FETCH_OBJ);
			if($query->rowCount() > 0) {
				foreach($results as $result) {
					$planPrice = floatval($result->Price);
			?>

			<!-- Main Receipt Card -->
			<div class="ios-glass" style="padding: 36px; margin-bottom: 30px;">
				<div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 24px; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
					<div>
						<span style="font-size: 13px; font-weight: 600; text-transform: uppercase; color: var(--color-primary); letter-spacing: 0.05em;">Membership Package</span>
						<h2 style="font-size: 28px; font-weight: 800; color: #fff; margin: 4px 0 8px;"><?php echo htmlentities($result->title);?></h2>
						<span class="ios-badge" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); color: var(--text-secondary);">
							<?php echo htmlentities($result->category_name);?> &bull; <?php echo htmlentities($result->PackageName);?>
						</span>
					</div>

					<div style="text-align: right;">
						<div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Plan Price</div>
						<div style="font-size: 36px; font-weight: 800; color: #fff; letter-spacing: -0.04em;"><?php echo format_currency($result->Price);?></div>
						<span class="ios-badge ios-badge-primary">
							<i class="fa fa-clock-o"></i> <?php echo htmlentities($result->PackageDuratiobn);?>
						</span>
					</div>
				</div>

				<div class="row" style="row-gap: 20px;">
					<div class="col-md-6">
						<div style="font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">Member Details</div>
						<div style="font-weight: 700; color: #fff; font-size: 16px;"><?php echo htmlentities($result->Name);?></div>
						<div style="color: var(--text-secondary); font-size: 14px;"><?php echo htmlentities($result->email);?></div>
					</div>

					<div class="col-md-6">
						<div style="font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">Booking Registered</div>
						<div style="font-weight: 600; color: #fff; font-size: 15px;"><?php echo date('F d, Y \a\t h:i A', strtotime($result->bookingdate));?></div>
						<div style="margin-top: 6px;">
							<?php if($result->paymentType == 'Full Payment'): ?>
								<span class="ios-badge ios-badge-success"><i class="fa fa-check-circle"></i> Paid in Full</span>
							<?php elseif($result->paymentType == 'Partial Payment'): ?>
								<span class="ios-badge ios-badge-warning"><i class="fa fa-hourglass-half"></i> Partial Payment</span>
							<?php else: ?>
								<span class="ios-badge ios-badge-danger"><i class="fa fa-exclamation-triangle"></i> Pending Payment</span>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<?php if(!empty($result->Description)): ?>
				<div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.06);">
					<div style="font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; margin-bottom: 6px;">Plan Description</div>
					<div style="color: var(--text-secondary); font-size: 14px; line-height: 1.6;">
						<?php echo strip_tags($result->Description, '<br>'); ?>
					</div>
				</div>
				<?php endif; ?>
			</div>
			<?php } } ?>

			<!-- Payment Ledger Card -->
			<?php 
			$sql = "SELECT * FROM tblpayment WHERE bookingID = :bookindid ORDER BY id ASC";
			$query = $dbh->prepare($sql);
			$query->bindParam(':bookindid', $bookindid, PDO::PARAM_STR);
			$query->execute();
			$payResults = $query->fetchAll(PDO::FETCH_OBJ);
			$gpayment = 0;
			?>
			<div class="ios-glass" style="padding: 30px;">
				<h3 style="font-size: 20px; font-weight: 800; color: #fff; margin-bottom: 18px; display: flex; align-items: center; gap: 10px;">
					<i class="fa fa-credit-card" style="color: var(--color-primary);"></i> Payment Transactions
				</h3>

				<?php if($query->rowCount() > 0): ?>
				<div class="ios-glass-table-wrapper" style="margin: 0 0 20px 0;">
					<table class="ios-glass-table">
						<thead>
							<tr>
								<th>Transaction Type</th>
								<th>Date Processed</th>
								<th style="text-align: right;">Amount Paid</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach($payResults as $pay): 
								$amount = floatval($pay->payment);
								$gpayment += $amount;
							?>
							<tr>
								<td>
									<span class="ios-badge <?php echo ($pay->paymentType == 'Full Payment') ? 'ios-badge-success' : 'ios-badge-warning'; ?>">
										<?php echo htmlentities($pay->paymentType);?>
									</span>
								</td>
								<td style="color: var(--text-secondary);">
									<?php echo date('M d, Y \a\t h:i A', strtotime($pay->payment_date)); ?>
								</td>
								<td style="text-align: right; font-weight: 700; color: #fff; font-size: 16px;">
									<?php echo format_currency($amount);?>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; background: rgba(255,255,255,0.04); border-radius: 16px; border: 1px solid rgba(255,255,255,0.08);">
					<span style="font-weight: 600; color: var(--text-secondary); text-transform: uppercase; font-size: 13px;">Total Amount Paid to Date</span>
					<span style="font-size: 24px; font-weight: 800; color: #34d399;"><?php echo format_currency($gpayment);?></span>
				</div>
				<?php else: ?>
				<div style="text-align: center; padding: 30px; color: var(--text-muted);">
					<i class="fa fa-clock-o" style="font-size: 28px; display: block; margin-bottom: 8px;"></i>
					No payment records entered yet for this booking.
				</div>
				<?php endif; ?>
			</div>
		</div>

		<?php include 'include/footer.php'; ?>
	</div>

	<script src="js/vendor/jquery-3.2.1.min.js"></script>
	<script src="js/bootstrap.min.js"></script>
</body>
</html>
<?php } ?>
