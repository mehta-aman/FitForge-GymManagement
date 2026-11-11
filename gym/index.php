<?php 
require_once 'include/config.php';
$uid = $_SESSION['uid'] ?? null;

// Logged-in Member Portal Redirection:
// Route member directly to their Welcome Dashboard unless explicitly requesting public portal (?public=1)
if (!empty($uid) && empty($_GET['public']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('location: user-dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) { 
  if (!verify_csrf_token()) {
    echo "<script>alert('Security validation failed (CSRF token invalid). Please refresh and try again.');</script>";
  } else {
    $pid = intval($_POST['pid']);
    
    // 1. Insert into legacy tblbooking
    $sql = "INSERT INTO tblbooking (package_id, userid) VALUES (:pid, :uid)";
    $query = $dbh->prepare($sql);
    $query->bindParam(':pid', $pid, PDO::PARAM_INT);
    $query->bindParam(':uid', $uid, PDO::PARAM_INT);
    $query->execute();
    $booking_id = $dbh->lastInsertId();

    // 2. Fetch plan details for membership creation
    $planStmt = $dbh->prepare("SELECT Price, PackageDuratiobn FROM tbladdpackage WHERE id = ?");
    $planStmt->execute([$pid]);
    $planData = $planStmt->fetch(PDO::FETCH_ASSOC);

    $months = 3;
    if ($planData && preg_match('/(\d+)/', (string)$planData['PackageDuratiobn'], $m)) {
        $months = intval($m[1]);
    }
    $startDate = date('Y-m-d');
    $expiryDate = date('Y-m-d', strtotime("+$months months"));
    $price = floatval($planData['Price'] ?? 0);

    // 3. Insert into modern tbl_memberships
    $memStmt = $dbh->prepare("INSERT INTO tbl_memberships (user_id, plan_id, booking_id, start_date, expiry_date, status, payment_status, total_amount, paid_amount) 
        VALUES (?, ?, ?, ?, ?, 'pending', 'unpaid', ?, 0.00)");
    $memStmt->execute([$uid, $pid, $booking_id, $startDate, $expiryDate, $price]);

    echo "<script>alert('Package has been booked successfully! You can view the receipt in your bookings timeline.');</script>";
    echo "<script>window.location.href='Booking-History.php';</script>";
    exit();
  }
}

$siteTitle = get_setting('website_title', 'GYM MS — Elevate Your Fitness');
$seoDesc = get_setting('seo_description', 'Next generation fitness club offering personalized training, premium recovery, and flexible membership passes.');
$heroPill = get_setting('hero_pill', 'NEXT GENERATION FITNESS');
$heroTitle = get_setting('hero_title', 'Sculpt Your Body. Elevate Your Mind.');
$heroSub = get_setting('hero_subtitle', 'Experience bespoke coaching, elite recovery zones, and flexible membership passes with iOS-grade precision.');
$heroCtaText = get_setting('hero_cta_text', 'Explore Memberships');
$heroCtaLink = get_setting('hero_cta_link', '#plans');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title><?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?></title>
	<meta name="description" content="<?php echo htmlspecialchars($seoDesc, ENT_QUOTES, 'UTF-8'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php echo render_favicon_tags(); ?>
	<!-- Stylesheets -->
	<link rel="stylesheet" href="css/bootstrap.min.css"/>
	<link rel="stylesheet" href="css/font-awesome.min.css"/>
	<link rel="stylesheet" href="css/animate.css"/>
	<!-- iOS Glassmorphism Engine -->
	<link rel="stylesheet" href="css/ios-glass.css"/>
</head>
<body>

	<!-- Floating iOS Capsule Navbar -->
	<?php include 'include/header.php';?>

	<?php if (!empty($uid) && !empty($_GET['public'])): ?>
		<div style="background: rgba(56, 189, 248, 0.12); border-bottom: 1px solid rgba(56, 189, 248, 0.3); padding: 10px 20px; text-align: center; font-size: 13px; color: #e0f2fe; backdrop-filter: blur(12px);">
			<i class="fa fa-info-circle" style="color: #38bdf8; margin-right: 6px;"></i>
			You are previewing the public portal as a signed-in member.
			<a href="user-dashboard.php" class="ios-btn ios-btn-primary" style="margin-left: 12px; padding: 4px 14px; font-size: 12px; text-decoration: none; border-radius: 8px;">
				<i class="fa fa-th-large"></i> Return to Member Dashboard
			</a>
		</div>
	<?php endif; ?>

	<div class="site-content-wrapper">
		<!-- Hero Section -->
		<section class="ios-hero-section">
			<div class="container">
				<div class="ios-hero-pill">
					<i class="fa fa-sparkles"></i> <?php echo htmlspecialchars($heroPill, ENT_QUOTES, 'UTF-8'); ?>
				</div>
				<h1 class="ios-hero-title">
					<?php echo nl2br(htmlspecialchars($heroTitle, ENT_QUOTES, 'UTF-8')); ?>
				</h1>
				<p class="ios-hero-subtitle">
					<?php echo htmlspecialchars($heroSub, ENT_QUOTES, 'UTF-8'); ?>
				</p>
				<div style="display: flex; justify-content: center; gap: 14px; flex-wrap: wrap;">
					<a href="<?php echo htmlspecialchars($heroCtaLink, ENT_QUOTES, 'UTF-8'); ?>" class="ios-btn ios-btn-primary">
						<i class="fa fa-bolt"></i> <?php echo htmlspecialchars($heroCtaText, ENT_QUOTES, 'UTF-8'); ?>
					</a>
					<a href="about.php" class="ios-btn ios-btn-glass">
						<i class="fa fa-compass"></i> Learn More
					</a>
				</div>
			</div>
		</section>

		<!-- Pricing Section -->
		<section id="plans" style="padding: 40px 20px 80px;">
			<div class="container">
				<div class="text-center" style="margin-bottom: 50px;">
					<h2 style="font-size: 36px; font-weight: 800; letter-spacing: -0.03em; color: #fff; margin-bottom: 10px;">
						Choose Your Pass
					</h2>
					<p style="color: var(--text-secondary); max-width: 500px; margin: 0 auto; font-size: 16px;">
						Flexible plans configured in <?php echo htmlspecialchars(get_setting('currency_code', 'INR')); ?> (<?php echo get_currency_symbol(); ?>) to fit your personal training goals.
					</p>
				</div>

				<div class="row" style="row-gap: 30px;">
					<?php 
					$sql = "SELECT id, category, titlename, PackageType, PackageDuratiobn, Price, uploadphoto, Description, create_date from tbladdpackage WHERE is_active = 1";
					$query = $dbh->prepare($sql);
					$query->execute();
					$results = $query->fetchAll(PDO::FETCH_OBJ);
					if($query->rowCount() > 0) {
						foreach($results as $result) {
					?>
					<div class="col-lg-4 col-md-6">
						<div class="ios-pricing-card">
							<div class="ios-card-badge">Popular</div>
							<div>
								<?php if(!empty($result->uploadphoto)): ?>
									<div style="margin-bottom: 16px; border-radius: 16px; overflow: hidden; max-height: 140px;">
										<img src="<?php echo htmlspecialchars($result->uploadphoto, ENT_QUOTES, 'UTF-8'); ?>" alt="Plan Photo" style="width: 100%; height: 100%; object-fit: cover;">
									</div>
								<?php endif; ?>
								<div class="ios-card-title"><?php echo htmlentities($result->titlename);?></div>
								<div class="ios-card-price-wrap">
									<span class="ios-card-price"><?php echo format_currency($result->Price);?></span>
									<span class="ios-card-duration">/ <?php echo htmlentities($result->PackageDuratiobn);?></span>
								</div>
								<div class="ios-card-desc">
									<?php echo strip_tags($result->Description, '<br>');?>
								</div>
							</div>

							<div>
								<?php if(empty($_SESSION['uid'])): ?>
									<a href="login.php" class="ios-btn ios-btn-primary" style="width: 100%;">
										<i class="fa fa-lock"></i> Sign In to Book
									</a>
								<?php else: ?>
									<form method="post" style="margin: 0;">
										<?php echo csrf_input(); ?>
										<input type="hidden" name="pid" value="<?php echo htmlentities($result->id);?>">
										<button type="submit" name="submit" class="ios-btn ios-btn-accent" style="width: 100%;" onclick="return confirm('Confirm booking for <?php echo addslashes($result->titlename);?>?');">
											<i class="fa fa-check-circle"></i> Book Membership
										</button>
									</form>
								<?php endif; ?>
							</div>
						</div>
					</div>
					<?php } } ?>
				</div>
			</div>
		</section>

		<!-- Footer -->
		<?php include 'include/footer.php'; ?>
	</div>

	<!-- Scripts -->
	<script src="js/vendor/jquery-3.2.1.min.js"></script>
	<script src="js/bootstrap.min.js"></script>
</body>
</html>
