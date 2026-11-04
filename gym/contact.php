<?php 
require_once 'include/config.php';
$uid = $_SESSION['uid'] ?? null;
$gymName = get_setting('gym_name', 'GYM MS');
$cEmail = get_setting('contact_email', 'contact@gymclub.com');
$cPhone = get_setting('contact_phone', '+91 98765 43210');
$cWhatsapp = get_setting('contact_whatsapp', '+91 98765 43210');
$cAddress = get_setting('contact_address', '800 Olympic Way, Suite 400, Mumbai, India');
$mapLink = get_setting('map_link', 'https://maps.google.com');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Contact Us — <?php echo htmlspecialchars($gymName, ENT_QUOTES, 'UTF-8'); ?></title>
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
			<div class="text-center" style="margin-bottom: 40px;">
				<div class="ios-hero-pill">
					<i class="fa fa-envelope-open"></i> GET IN TOUCH
				</div>
				<h1 style="font-size: 42px; font-weight: 800; letter-spacing: -0.03em; color: #fff; margin-bottom: 12px;">
					We'd Love to Hear From You
				</h1>
				<p style="color: var(--text-secondary); font-size: 16px;">
					Have questions regarding memberships, training schedules, or personal training? Reach out anytime.
				</p>
			</div>

			<div class="row" style="row-gap: 24px;">
				<div class="col-md-4">
					<div class="ios-glass" style="padding: 30px; text-align: center; height: 100%;">
						<div class="ios-auth-icon" style="width: 52px; height: 52px; font-size: 20px; margin-bottom: 14px;">
							<i class="fa fa-envelope"></i>
						</div>
						<h4 style="font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 6px;">Email Support</h4>
						<p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
							<a href="mailto:<?php echo htmlspecialchars($cEmail, ENT_QUOTES, 'UTF-8'); ?>" style="color: var(--color-primary); text-decoration: none;">
								<?php echo htmlspecialchars($cEmail, ENT_QUOTES, 'UTF-8'); ?>
							</a>
						</p>
					</div>
				</div>

				<div class="col-md-4">
					<div class="ios-glass" style="padding: 30px; text-align: center; height: 100%;">
						<div class="ios-auth-icon" style="width: 52px; height: 52px; font-size: 20px; margin-bottom: 14px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2)); border-color: rgba(16, 185, 129, 0.4); color: var(--color-success);">
							<i class="fa fa-phone"></i>
						</div>
						<h4 style="font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 6px;">Call / WhatsApp</h4>
						<p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
							<a href="tel:<?php echo htmlspecialchars($cPhone, ENT_QUOTES, 'UTF-8'); ?>" style="color: #fff; text-decoration: none; font-weight: 600;">
								<?php echo htmlspecialchars($cPhone, ENT_QUOTES, 'UTF-8'); ?>
							</a>
						</p>
					</div>
				</div>

				<div class="col-md-4">
					<div class="ios-glass" style="padding: 30px; text-align: center; height: 100%;">
						<div class="ios-auth-icon" style="width: 52px; height: 52px; font-size: 20px; margin-bottom: 14px; background: linear-gradient(135deg, rgba(249, 115, 22, 0.2), rgba(234, 88, 12, 0.2)); border-color: rgba(249, 115, 22, 0.4); color: var(--color-accent);">
							<i class="fa fa-map-marker"></i>
						</div>
						<h4 style="font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 6px;">Club Location</h4>
						<p style="color: var(--text-secondary); font-size: 14px; margin: 0; line-height: 1.5;">
							<?php echo htmlspecialchars($cAddress, ENT_QUOTES, 'UTF-8'); ?>
						</p>
						<?php if ($mapLink): ?>
							<a href="<?php echo htmlspecialchars($mapLink, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="ios-btn ios-btn-glass" style="margin-top: 12px; padding: 6px 14px; font-size: 12px;">
								<i class="fa fa-location-arrow"></i> Open in Maps
							</a>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>

		<?php include 'include/footer.php'; ?>
	</div>

	<script src="js/vendor/jquery-3.2.1.min.js"></script>
	<script src="js/bootstrap.min.js"></script>
</body>
</html>
