<?php 
require_once 'include/config.php';
$uid = $_SESSION['uid'] ?? null;
$aboutPill = get_setting('about_pill', 'OUR MISSION');
$aboutTitle = get_setting('about_title', 'Empowering Lifelong Fitness');
$aboutText = get_setting('about_text', 'Welcome to GYM MS! We are a collective of fitness enthusiasts, certified personal coaches, and sports nutritionists dedicated to redefining your health journey.');

// Fetch active highlight features from database
$features = $dbh->query("SELECT * FROM tbl_website_features WHERE is_active = 1 ORDER BY display_order ASC, id ASC")->fetchAll(PDO::FETCH_OBJ);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>About Us — <?php echo htmlspecialchars(get_setting('gym_name', 'GYM MS'), ENT_QUOTES, 'UTF-8'); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php echo render_favicon_tags(); ?>
	<link rel="stylesheet" href="css/bootstrap.min.css"/>
	<link rel="stylesheet" href="css/font-awesome.min.css"/>
	<link rel="stylesheet" href="css/ios-glass.css"/>
</head>
<body>

	<?php include 'include/header.php';?>

	<div class="site-content-wrapper" style="padding: 40px 20px 80px;">
		<div class="container" style="max-width: 950px;">
			<div class="text-center" style="margin-bottom: 40px;">
				<div class="ios-hero-pill">
					<i class="fa fa-info-circle"></i> <?php echo htmlspecialchars($aboutPill, ENT_QUOTES, 'UTF-8'); ?>
				</div>
				<h1 style="font-size: 42px; font-weight: 800; letter-spacing: -0.03em; color: #fff; margin-bottom: 12px;">
					<?php echo htmlspecialchars($aboutTitle, ENT_QUOTES, 'UTF-8'); ?>
				</h1>
				<p style="color: var(--text-secondary); font-size: 16px; max-width: 650px; margin: 0 auto;">
					State-of-the-art facilities, tailored nutrition programs, and an encouraging community to fuel your goals.
				</p>
			</div>

			<div class="ios-glass" style="padding: 40px; margin-bottom: 30px;">
				<h3 style="font-size: 22px; font-weight: 700; color: #fff; margin-bottom: 16px;">
					Our Philosophy &amp; Facilities
				</h3>
				<p style="color: var(--text-secondary); font-size: 15px; line-height: 1.8;">
					<?php echo nl2br(htmlspecialchars($aboutText, ENT_QUOTES, 'UTF-8')); ?>
				</p>

				<?php if (!empty($features)): ?>
				<div class="row" style="margin-top: 36px; row-gap: 20px;">
					<?php foreach ($features as $f): ?>
					<div class="col-md-6 col-lg-3">
						<div style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 20px; padding: 22px 18px; text-align: center; height: 100%;">
							<div class="ios-auth-icon" style="margin-bottom: 12px; width: 48px; height: 48px; font-size: 20px;">
								<i class="fa <?php echo htmlspecialchars($f->icon, ENT_QUOTES, 'UTF-8'); ?>"></i>
							</div>
							<h4 style="font-size: 15px; font-weight: 700; color: #fff; margin-bottom: 6px;"><?php echo htmlspecialchars($f->title, ENT_QUOTES, 'UTF-8'); ?></h4>
							<p style="font-size: 12px; color: var(--text-muted); margin: 0; line-height: 1.5;"><?php echo htmlspecialchars($f->description, ENT_QUOTES, 'UTF-8'); ?></p>
						</div>
					</div>
					<?php endforeach; ?>
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
