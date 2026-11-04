<?php
require_once 'include/config.php';
$msg = ""; 

if (isset($_POST['submit'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = "Security token mismatch. Please try again.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $rawPassword = $_POST['password'] ?? '';

        if ($email !== "" && $rawPassword !== "") {
            try {
                $query = "SELECT id, name, email, mobile, password, create_date FROM tbladmin WHERE email = :email";
                $stmt = $dbh->prepare($query);
                $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row && verify_and_rehash_password($rawPassword, $row['password'], 'tbladmin', 'id', $row['id'], $dbh)) {
                    $_SESSION['adminid'] = $row['id'];
                    $_SESSION['email']   = $row['email'];
                    $_SESSION['name']    = $row['name'];
                    header("location: index.php");
                    exit();
                } else {
                    $msg = "Invalid admin username or password!";
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
	<meta charset="utf-8">
	<title>Admin Portal — <?php echo htmlspecialchars(get_setting('gym_name', 'GYM MS')); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php echo render_favicon_tags(); ?>
	<link rel="stylesheet" type="text/css" href="css/main.css">
	<link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
	<link rel="stylesheet" type="text/css" href="css/ios-admin-glass.css">
</head>
<body style="min-height: 100vh; display: flex; align-items: center; justify-content: center; position: relative;">

	<!-- Ambient Light Background Canvas -->
	<div class="ambient-glow">
		<div class="ambient-orb ambient-orb-1" style="background: radial-gradient(circle, #6366f1 0%, rgba(99,102,241,0) 70%); width: 600px; height: 600px; top: -100px; right: -50px;"></div>
		<div class="ambient-orb ambient-orb-2" style="background: radial-gradient(circle, #38bdf8 0%, rgba(56,189,248,0) 70%); width: 500px; height: 500px; bottom: -50px; left: -100px;"></div>
	</div>

	<div class="site-content-wrapper" style="width: 100%; max-width: 460px; padding: 20px;">
		<div class="ios-auth-card" style="margin: 0; padding: 44px 36px;">
			<?php $adminLogoUrl = function_exists('get_logo_url') ? get_logo_url() : ''; ?>
			<?php if (!empty($adminLogoUrl)): ?>
				<div style="text-align: center; margin-bottom: 20px;">
					<img src="<?php echo htmlspecialchars($adminLogoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" style="max-height: 60px; max-width: 220px; object-fit: contain; border-radius: 12px; filter: drop-shadow(0 8px 16px rgba(0,0,0,0.4));">
				</div>
			<?php else: ?>
				<div class="ios-auth-icon" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.25) 0%, rgba(56, 189, 248, 0.25) 100%); border-color: rgba(99, 102, 241, 0.4); color: #818cf8;">
					<i class="fa fa-shield"></i>
				</div>
			<?php endif; ?>
			<h2 class="ios-auth-title">Admin Console</h2>
			<p class="ios-auth-subtitle"><?php echo htmlspecialchars(get_setting('gym_name', 'GYM MS')); ?> &bull; Restricted Access</p>

			<?php if(!empty($msg)): ?>
				<div class="ios-badge ios-badge-danger" style="display: flex; margin-bottom: 24px; padding: 12px 16px; border-radius: 14px; text-align: left;">
					<i class="fa fa-exclamation-circle" style="font-size: 16px; margin-right: 8px;"></i>
					<span><?php echo htmlentities($msg); ?></span>
				</div>
			<?php endif; ?>

			<form method="post" autocomplete="off">
				<?php echo csrf_input(); ?>
				<div class="ios-form-group">
					<label class="ios-label">Admin Email</label>
					<input class="ios-input" name="email" id="email" type="email" placeholder="admin@domain.com" required autofocus value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
				</div>
				<div class="ios-form-group">
					<label class="ios-label">Password</label>
					<input class="ios-input" name="password" id="password" type="password" placeholder="Admin password" required>
				</div>

				<button type="submit" name="submit" id="submit" class="ios-btn ios-btn-primary" style="width: 100%; margin-top: 10px;">
					<i class="fa fa-unlock-alt"></i> Authenticate
				</button>
			</form>

			<div style="margin-top: 28px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.08);">
				<a href="../index.php" style="color: var(--text-secondary); text-decoration: none; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px;">
					<i class="fa fa-arrow-left"></i> Return to Public Site
				</a>
			</div>
		</div>
	</div>

	<script src="js/jquery-3.2.1.min.js"></script>
	<script src="js/popper.min.js"></script>
	<script src="js/bootstrap.min.js"></script>
</body>
</html>
