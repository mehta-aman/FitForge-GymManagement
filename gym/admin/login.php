<?php
require_once 'include/config.php';

// If user is already authenticated as admin, jump straight to admin dashboard
if (!empty($_SESSION['adminid'])) {
    header("location: index.php");
    exit();
}

$msg = ""; 
$successMsg = $_SESSION['reg_success_msg'] ?? '';
unset($_SESSION['reg_success_msg']);

if (isset($_POST['submit'])) {
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
                $query = "SELECT id, name, email, mobile, password, role, status, trainer_id, rejection_reason, create_date, gym_id FROM tbladmin WHERE email = :email";
                $stmt = $dbh->prepare($query);
                $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row && verify_and_rehash_password($rawPassword, $row['password'], 'tbladmin', 'id', $row['id'], $dbh)) {
                    // Check staff account approval status
                    $status = $row['status'] ?? 'pending';
                    if ($status === 'pending') {
                        $msg = "⏳ Your staff account is currently pending approval by the Gym Owner. You will be able to log in once reviewed.";
                    } elseif ($status === 'rejected') {
                        $msg = "❌ Your staff registration request was not approved by the owner." . (!empty($row['rejection_reason']) ? " Reason: " . htmlspecialchars($row['rejection_reason']) : "");
                    } elseif ($status === 'suspended') {
                        $msg = "⛔ This staff account has been suspended by the Gym Owner. Please contact management.";
                    } else {
                        // Approved: Proceed to login
                        clear_login_attempts($email, $dbh);
                        regenerate_secure_session();
                        $_SESSION['adminid']      = $row['id'];
                        $_SESSION['email']        = $row['email'];
                        $_SESSION['name']         = $row['name'];
                        $_SESSION['admin_role']   = $row['role'] ?? 'floor_staff';
                        $_SESSION['admin_status'] = $row['status'] ?? 'approved';
                        $_SESSION['trainer_id']   = $row['trainer_id'] ?? null;
                        $_SESSION['gym_id']       = $row['gym_id'] ?? 1;

                        // Update last_login
                        $upd = $dbh->prepare("UPDATE tbladmin SET last_login = NOW() WHERE id = :id");
                        $upd->execute([':id' => $row['id']]);

                        header("location: index.php");
                        exit();
                    }
                } else {
                    record_failed_login($email, $dbh);
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

$gymTitle = get_setting('gym_name', 'GYM MS');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Staff Portal — <?php echo htmlspecialchars($gymTitle); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
	<?php echo render_favicon_tags(); ?>
	<link rel="stylesheet" type="text/css" href="css/main.css">
	<link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
	<link rel="stylesheet" type="text/css" href="css/ios-admin-glass.css">
	<style>
		.ios-portal-switcher {
			display: flex;
			background: rgba(15, 23, 42, 0.7);
			border: 1px solid rgba(255, 255, 255, 0.12);
			border-radius: 16px;
			padding: 4px;
			gap: 4px;
			margin: 20px 0 18px 0;
		}
		.portal-tab-btn {
			flex: 1;
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			padding: 10px 14px;
			font-size: 13.5px;
			font-weight: 700;
			color: #94a3b8;
			background: transparent;
			border: 1px solid transparent;
			border-radius: 12px;
			text-decoration: none;
			transition: all 0.25s ease;
		}
		.portal-tab-btn:hover {
			color: #fff;
			background: rgba(255, 255, 255, 0.05);
			text-decoration: none;
		}
		.portal-tab-btn.active {
			color: #ffffff;
			background: linear-gradient(135deg, rgba(99, 102, 241, 0.28) 0%, rgba(168, 85, 247, 0.32) 100%);
			border-color: rgba(129, 140, 248, 0.5);
			box-shadow: 0 4px 16px rgba(99, 102, 241, 0.3);
			text-decoration: none;
		}

		.portal-info-box {
			display: flex;
			align-items: center;
			gap: 10px;
			padding: 10px 14px;
			border-radius: 12px;
			font-size: 12.5px;
			line-height: 1.45;
			margin-bottom: 22px;
			text-align: left;
			background: rgba(129, 140, 248, 0.12);
			border: 1px solid rgba(129, 140, 248, 0.3);
			color: #c7d2fe;
		}

		.ios-input-icon-wrapper {
			position: relative;
			display: flex;
			align-items: center;
		}
		.pwd-toggle-btn {
			position: absolute;
			right: 8px;
			top: 50%;
			transform: translateY(-50%);
			background: transparent;
			border: none;
			color: #94a3b8;
			padding: 8px 12px;
			cursor: pointer;
			font-size: 15px;
			border-radius: 8px;
			transition: color 0.2s ease;
		}
		.pwd-toggle-btn:hover {
			color: #fff;
		}

		.register-prompt-box {
			margin-top: 24px;
			padding-top: 20px;
			border-top: 1px solid rgba(255, 255, 255, 0.08);
			text-align: center;
		}
		.register-pill-group {
			display: flex;
			gap: 10px;
			justify-content: center;
			margin-top: 10px;
		}
		.reg-pill {
			flex: 1;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 6px;
			padding: 9px 12px;
			border-radius: 12px;
			font-size: 12.5px;
			font-weight: 600;
			text-decoration: none;
			transition: all 0.2s ease;
		}
		.reg-pill-member {
			background: rgba(56, 189, 248, 0.12);
			border: 1px solid rgba(56, 189, 248, 0.3);
			color: #38bdf8;
		}
		.reg-pill-member:hover {
			background: rgba(56, 189, 248, 0.22);
			color: #fff;
			text-decoration: none;
		}
		.reg-pill-staff {
			background: rgba(129, 140, 248, 0.12);
			border: 1px solid rgba(129, 140, 248, 0.3);
			color: #a5b4fc;
		}
		.reg-pill-staff:hover {
			background: rgba(129, 140, 248, 0.24);
			color: #fff;
			text-decoration: none;
		}
	</style>
</head>
<body style="min-height: 100vh; display: flex; align-items: center; justify-content: center; position: relative; padding: 20px 14px;">

	<!-- Ambient Light Background Canvas -->
	<div class="ambient-glow">
		<div class="ambient-orb ambient-orb-1" style="background: radial-gradient(circle, #6366f1 0%, rgba(99,102,241,0) 70%); width: 600px; height: 600px; top: -100px; right: -50px;"></div>
		<div class="ambient-orb ambient-orb-2" style="background: radial-gradient(circle, #38bdf8 0%, rgba(56,189,248,0) 70%); width: 500px; height: 500px; bottom: -50px; left: -100px;"></div>
	</div>

	<div class="site-content-wrapper" style="width: 100%; max-width: 480px;">
		<div class="ios-auth-card" style="margin: 0; padding: 36px 30px; text-align: center;">
			<?php $adminLogoUrl = function_exists('get_logo_url') ? get_logo_url() : ''; ?>
			<?php if (!empty($adminLogoUrl)): ?>
				<div style="text-align: center; margin-bottom: 18px;">
					<img src="<?php echo htmlspecialchars($adminLogoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" style="max-height: 56px; max-width: 220px; object-fit: contain; border-radius: 12px; filter: drop-shadow(0 8px 16px rgba(0,0,0,0.4));">
				</div>
			<?php else: ?>
				<div class="ios-auth-icon" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.25) 0%, rgba(56, 189, 248, 0.25) 100%); border-color: rgba(99, 102, 241, 0.4); color: #818cf8; margin: 0 auto 16px; width: 60px; height: 60px; border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 26px;">
					<i class="fa fa-shield"></i>
				</div>
			<?php endif; ?>
			<h2 class="ios-auth-title" style="font-size: 24px; font-weight: 800; color: #fff; margin-bottom: 4px;">Staff Management Portal</h2>
			<p class="ios-auth-subtitle" style="font-size: 13.5px; color: #94a3b8; margin: 0;"><?php echo htmlspecialchars($gymTitle); ?> &bull; Authorized Personnel Only</p>

			<!-- Segmented Switcher connecting Member & Staff -->
			<div class="ios-portal-switcher" role="tablist">
				<a href="../login.php?portal=member" class="portal-tab-btn">
					<i class="fa fa-user"></i>
					<span>Member Access</span>
				</a>
				<span class="portal-tab-btn active">
					<i class="fa fa-shield"></i>
					<span>Staff &amp; Coaches</span>
				</span>
			</div>

			<div class="portal-info-box">
				<i class="fa fa-id-badge" style="font-size: 16px; flex-shrink: 0;"></i>
				<span>Sign in for Trainers, Floor Coaches, Front Desk, Managers, and Owners.</span>
			</div>

			<?php if(!empty($successMsg)): ?>
				<div class="ios-badge ios-badge-success" style="display: flex; margin-bottom: 22px; padding: 13px 16px; border-radius: 14px; text-align: left; background: rgba(16, 185, 129, 0.18); border: 1px solid rgba(16, 185, 129, 0.35); color: #34d399;">
					<i class="fa fa-check-circle" style="font-size: 17px; margin-right: 10px; flex-shrink: 0; margin-top: 2px;"></i>
					<span style="font-size: 13.5px;"><?php echo htmlentities($successMsg); ?></span>
				</div>
			<?php endif; ?>

			<?php if(!empty($msg)): ?>
				<div class="ios-badge ios-badge-danger" style="display: flex; margin-bottom: 22px; padding: 13px 16px; border-radius: 14px; text-align: left; background: rgba(239, 68, 68, 0.18); border: 1px solid rgba(239, 68, 68, 0.35); color: #f87171;">
					<i class="fa fa-exclamation-circle" style="font-size: 17px; margin-right: 10px; flex-shrink: 0; margin-top: 2px;"></i>
					<span style="font-size: 13.5px;"><?php echo htmlentities($msg); ?></span>
				</div>
			<?php endif; ?>

			<form method="post" autocomplete="off" style="text-align: left;">
				<?php echo csrf_input(); ?>
				<div class="ios-form-group">
					<label class="ios-label">Staff Work Email</label>
					<input class="ios-input" name="email" id="email" type="email" placeholder="staff@domain.com" required autofocus value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
				</div>
				<div class="ios-form-group">
					<label class="ios-label">Account Password</label>
					<div class="ios-input-icon-wrapper">
						<input class="ios-input" name="password" id="adminPassword" type="password" placeholder="Enter staff password" required>
						<button type="button" class="pwd-toggle-btn" onclick="toggleAdminPassword()" aria-label="Toggle password view">
							<i class="fa fa-eye" id="adminEye"></i>
						</button>
					</div>
				</div>

				<button type="submit" name="submit" id="submit" class="ios-btn ios-btn-primary" style="width: 100%; margin-top: 10px;">
					<i class="fa fa-unlock-alt"></i> Authenticate Staff Portal
				</button>
			</form>

			<!-- Common Registration Link Box -->
			<div class="register-prompt-box">
				<div style="font-size: 13.5px; color: #94a3b8;">
					Don't have an account yet? Register below:
				</div>
				<div class="register-pill-group">
					<a href="../registration.php?portal=member" class="reg-pill reg-pill-member">
						<i class="fa fa-user-plus"></i> Join as Member
					</a>
					<a href="register.php" class="reg-pill reg-pill-staff">
						<i class="fa fa-briefcase"></i> Apply as Staff
					</a>
				</div>
			</div>

			<div style="margin-top: 22px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.08); text-align: center;">
				<a href="../index.php" style="color: var(--text-secondary); text-decoration: none; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px;">
					<i class="fa fa-arrow-left"></i> Return to Public Site
				</a>
			</div>
		</div>
	</div>

	<script>
		function toggleAdminPassword() {
			var field = document.getElementById('adminPassword');
			var icon = document.getElementById('adminEye');
			if (!field) return;

			if (field.type === 'password') {
				field.type = 'text';
				if (icon) {
					icon.classList.remove('fa-eye');
					icon.classList.add('fa-eye-slash');
				}
			} else {
				field.type = 'password';
				if (icon) {
					icon.classList.remove('fa-eye-slash');
					icon.classList.add('fa-eye');
				}
			}
		}
	</script>
	<script src="js/jquery-3.2.1.min.js"></script>
	<script src="js/popper.min.js"></script>
	<script src="js/bootstrap.min.js"></script>
</body>
</html>
