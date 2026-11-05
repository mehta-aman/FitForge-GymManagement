<?php
require_once 'include/config.php';
$msg = ""; 

$redirectParam = trim($_GET['redirect'] ?? $_POST['redirect'] ?? '');
if (!empty($redirectParam)) {
    // Sanitize: allow only safe relative path without external URL schemes or double slashes
    if (str_starts_with($redirectParam, '/') || str_contains($redirectParam, ':') || str_contains($redirectParam, '\\')) {
        $redirectParam = '';
    }
}

// If already logged in, redirect immediately
if (!empty($_SESSION['uid'])) {
    $target = !empty($redirectParam) ? $redirectParam : 'user-dashboard.php';
    header("location: " . $target);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = "Security token mismatch. Please try again.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $rawPassword = $_POST['password'] ?? '';

        // Check brute-force throttling
        $throttle = check_login_throttle($email, $dbh);
        if (!$throttle['allowed']) {
            $msg = $throttle['message'];
        } elseif ($email !== "" && $rawPassword !== "") {
            try {
                $query = "SELECT id, fname, lname, email, mobile, password, address, status, create_date, gym_id FROM tbluser WHERE email = :email";
                $stmt = $dbh->prepare($query);
                $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row && verify_and_rehash_password($rawPassword, $row['password'], 'tbluser', 'id', $row['id'], $dbh)) {
                    if (isset($row['status']) && $row['status'] === 'inactive') {
                        $msg = "Your account has been deactivated. Please contact gym administration.";
                    } else {
                        clear_login_attempts($email, $dbh);
                        regenerate_secure_session();
                        $_SESSION['uid']   = $row['id'];
                        $_SESSION['email'] = $row['email'];
                        $_SESSION['name']  = $row['fname'];
                        $_SESSION['gym_id'] = $row['gym_id'] ?? 1;

                        $target = 'user-dashboard.php';
                        if (!empty($redirectParam)) {
                            $target = $redirectParam;
                        } elseif (!empty($_SESSION['checkin_pending_redirect'])) {
                            $target = $_SESSION['checkin_pending_redirect'];
                            unset($_SESSION['checkin_pending_redirect']);
                        }
                        header("location: " . $target);
                        exit();
                    }
                } else {
                    record_failed_login($email, $dbh);
                    $msg = "Invalid email or password!";
                }
            } catch (PDOException $e) {
                $msg = "Database Error: " . $e->getMessage();
            }
        } else {
            $msg = "Both fields are required!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Sign In — <?php echo htmlspecialchars(get_setting('gym_name', 'GYM MS')); ?></title>
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
			<div class="ios-auth-card">
				<?php $publicLogo = function_exists('get_logo_url') ? get_logo_url() : ''; ?>
				<?php if (!empty($publicLogo)): ?>
					<div style="text-align: center; margin-bottom: 20px;">
						<img src="<?php echo htmlspecialchars($publicLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" style="max-height: 60px; max-width: 220px; object-fit: contain; border-radius: 12px; filter: drop-shadow(0 8px 16px rgba(0,0,0,0.4));">
					</div>
				<?php else: ?>
					<div class="ios-auth-icon">
						<i class="fa fa-user-circle"></i>
					</div>
				<?php endif; ?>
				<h2 class="ios-auth-title">Member Sign In</h2>
				<p class="ios-auth-subtitle">Access your gym bookings, workouts, and profile</p>

				<?php if (str_contains($redirectParam, 'checkin')): ?>
					<div class="ios-badge ios-badge-info" style="display: flex; align-items: center; gap: 12px; margin-bottom: 24px; padding: 14px 16px; border-radius: 16px; background: rgba(16, 185, 129, 0.16); border: 1px solid rgba(16, 185, 129, 0.35); color: #34d399; text-align: left;">
						<i class="fa fa-qrcode" style="font-size: 26px; flex-shrink: 0; color: #10b981;"></i>
						<div>
							<div style="font-weight: 700; font-size: 14px; color: #fff;">Gym Self Check-In</div>
							<div style="font-size: 12.5px; opacity: 0.9; margin-top: 2px;">Sign in to immediately log your attendance for today's session!</div>
						</div>
					</div>
				<?php endif; ?>

				<?php if(!empty($msg)): ?>
					<div class="ios-badge ios-badge-danger" style="display: flex; margin-bottom: 24px; padding: 12px 16px; border-radius: 14px; text-align: left;">
						<i class="fa fa-exclamation-circle" style="font-size: 16px; margin-right: 8px;"></i>
						<span><?php echo htmlentities($msg); ?></span>
					</div>
				<?php endif; ?>

				<form method="post" autocomplete="off">
					<?php echo csrf_input(); ?>
					<?php if (!empty($redirectParam)): ?>
						<input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirectParam, ENT_QUOTES, 'UTF-8'); ?>">
					<?php endif; ?>
					<div class="ios-form-group">
						<label class="ios-label" for="email">Email Address</label>
						<input type="email" name="email" id="email" class="ios-input" placeholder="name@domain.com" required autofocus value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
					</div>

					<div class="ios-form-group">
						<label class="ios-label" for="password">Password</label>
						<input type="password" name="password" id="password" class="ios-input" placeholder="Enter your password" required>
					</div>

					<button type="submit" id="submit" name="submit" class="ios-btn ios-btn-primary" style="width: 100%; margin-top: 10px;">
						<i class="fa fa-arrow-right"></i> Sign In
					</button>

					<div style="margin-top: 24px; font-size: 14px; color: var(--text-secondary);">
						Don't have an account? <a href="registration.php<?php echo !empty($redirectParam) ? '?redirect=' . urlencode($redirectParam) : ''; ?>" style="color: var(--color-primary); font-weight: 600; text-decoration: none;">Create one here</a>
					</div>
				</form>
			</div>
		</div>

		<?php include 'include/footer.php'; ?>
	</div>

	<script src="js/vendor/jquery-3.2.1.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
	<script src="js/bootstrap.min.js"></script>
</body>
</html>
