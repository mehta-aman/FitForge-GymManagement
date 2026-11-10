<?php
require_once 'include/config.php';

// If user is already authenticated as admin, jump straight to admin dashboard
if (!empty($_SESSION['adminid'])) {
    header("location: index.php");
    exit();
}

$msg = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $name = trim($_POST['name'] ?? $_POST['staff_name'] ?? '');
        $email = trim($_POST['email'] ?? $_POST['staff_email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? $_POST['staff_mobile'] ?? '');
        $requestedRole = trim($_POST['requested_role'] ?? 'floor_staff');
        $rawPassword = $_POST['password'] ?? $_POST['staff_password'] ?? '';
        $repeatPassword = $_POST['repeat_password'] ?? $_POST['staff_repeat_password'] ?? '';
        $speciality = trim($_POST['speciality'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $gymId = function_exists('get_current_gym_id') ? get_current_gym_id() : 1;

        $allowedRoles = ['manager', 'trainer', 'floor_staff'];

        if (empty($name) || empty($email) || empty($mobile) || empty($rawPassword)) {
            $error = "All required fields must be filled.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif (!in_array($requestedRole, $allowedRoles, true)) {
            $error = "Please select a valid staff role.";
        } elseif (strlen($rawPassword) < 6) {
            $error = "Password must be at least 6 characters long.";
        } elseif ($rawPassword !== $repeatPassword) {
            $error = "Passwords do not match.";
        } else {
            $chk = $dbh->prepare("SELECT id FROM tbladmin WHERE email = :email LIMIT 1");
            $chk->execute([':email' => $email]);
            if ($chk->fetch()) {
                $error = "An employee account with this email already exists.";
            } else {
                $hash = password_hash($rawPassword, PASSWORD_DEFAULT);
                $trainerId = null;

                if ($requestedRole === 'trainer') {
                    $tSpec = !empty($speciality) ? $speciality : 'General Fitness & Strength';
                    $insTrainer = $dbh->prepare("INSERT INTO tbl_trainers (name, speciality, mobile, email, experience_years, bio, status, gym_id) VALUES (:name, :spec, :mob, :email, 1, :bio, 'inactive', :gym_id)");
                    $insTrainer->execute([
                        ':name' => $name,
                        ':spec' => $tSpec,
                        ':mob' => $mobile,
                        ':email' => $email,
                        ':bio' => $bio,
                        ':gym_id' => $gymId
                    ]);
                    $trainerId = $dbh->lastInsertId();
                }

                $insAdmin = $dbh->prepare("
                    INSERT INTO tbladmin (name, email, mobile, password, role, status, trainer_id, create_date, gym_id)
                    VALUES (:name, :email, :mob, :pwd, :role, 'pending', :tid, NOW(), :gym_id)
                ");
                $insAdmin->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':mob' => $mobile,
                    ':pwd' => $hash,
                    ':role' => $requestedRole,
                    ':tid' => $trainerId,
                    ':gym_id' => $gymId
                ]);

                $_SESSION['reg_success_msg'] = "Staff account request submitted successfully! The Gym Owner will review and activate your account shortly.";
                header("location: login.php");
                exit();
            }
        }
    }
}

$gymTitle = get_setting('gym_name', 'GYM MS');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Staff Registration — <?php echo htmlspecialchars($gymTitle); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
	<?php echo render_favicon_tags(); ?>
	<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
	<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<link rel="stylesheet" type="text/css" href="../css/ios-glass.css">
	<style>
		body {
			background: radial-gradient(circle at 15% 15%, #1e1b4b 0%, #09090b 55%, #030712 100%);
			color: #f8fafc;
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 40px 16px;
			font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif;
		}
		.reg-card {
			background: linear-gradient(135deg, rgba(30, 41, 59, 0.85) 0%, rgba(15, 23, 42, 0.95) 100%);
			backdrop-filter: blur(30px);
			-webkit-backdrop-filter: blur(30px);
			border: 1px solid rgba(255, 255, 255, 0.16);
			border-radius: 28px;
			padding: 36px 30px;
			width: 100%;
			max-width: 540px;
			box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
		}
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
		.role-selector-card {
			border: 1px solid rgba(255, 255, 255, 0.12);
			border-radius: 14px;
			padding: 12px 14px;
			background: rgba(0, 0, 0, 0.25);
			cursor: pointer;
			transition: all 0.2s ease;
			margin-bottom: 8px;
			display: flex;
			align-items: center;
			gap: 12px;
			text-align: left;
		}
		.role-selector-card:hover {
			border-color: rgba(56, 189, 248, 0.4);
			background: rgba(56, 189, 248, 0.08);
		}
		.role-selector-card input[type="radio"] {
			accent-color: #38bdf8;
			width: 18px;
			height: 18px;
			flex-shrink: 0;
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
		.signin-prompt-box {
			margin-top: 24px;
			padding-top: 20px;
			border-top: 1px solid rgba(255, 255, 255, 0.08);
			text-align: center;
		}
		.signin-pill-group {
			display: flex;
			gap: 10px;
			justify-content: center;
			margin-top: 10px;
		}
		.signin-pill {
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
		.signin-pill-member {
			background: rgba(56, 189, 248, 0.12);
			border: 1px solid rgba(56, 189, 248, 0.3);
			color: #38bdf8;
		}
		.signin-pill-member:hover {
			background: rgba(56, 189, 248, 0.22);
			color: #fff;
			text-decoration: none;
		}
		.signin-pill-staff {
			background: rgba(129, 140, 248, 0.12);
			border: 1px solid rgba(129, 140, 248, 0.3);
			color: #a5b4fc;
		}
		.signin-pill-staff:hover {
			background: rgba(129, 140, 248, 0.24);
			color: #fff;
			text-decoration: none;
		}
	</style>
</head>
<body>

	<div class="reg-card">
		<div style="text-align: center; margin-bottom: 12px;">
			<div style="width: 60px; height: 60px; border-radius: 18px; background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.3); color: #38bdf8; font-size: 24px; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px;">
				<i class="fa fa-briefcase"></i>
			</div>
			<h2 style="font-size: 24px; font-weight: 800; color: #fff; margin-bottom: 4px;">Staff Account Application</h2>
			<p style="font-size: 13.5px; color: #94a3b8; margin: 0;">Request employee or trainer access to <?php echo htmlspecialchars($gymTitle); ?></p>
		</div>

		<!-- Segmented Switcher connecting Member & Staff -->
		<div class="ios-portal-switcher" role="tablist">
			<a href="../registration.php?portal=member" class="portal-tab-btn">
				<i class="fa fa-user-plus"></i>
				<span>Join as Member</span>
			</a>
			<span class="portal-tab-btn active">
				<i class="fa fa-briefcase"></i>
				<span>Apply as Staff / Coach</span>
			</span>
		</div>

		<?php if (!empty($error)): ?>
			<div class="ios-badge ios-badge-danger" style="display: flex; margin-bottom: 20px; padding: 12px 16px; border-radius: 14px; text-align: left; background: rgba(239, 68, 68, 0.18); border: 1px solid rgba(239, 68, 68, 0.35); color: #f87171;">
				<i class="fa fa-exclamation-circle" style="font-size: 16px; margin-right: 8px; flex-shrink: 0; margin-top: 2px;"></i>
				<span><?php echo htmlspecialchars($error); ?></span>
			</div>
		<?php endif; ?>

		<form method="post" autocomplete="off" style="text-align: left;">
			<?php echo csrf_input(); ?>

			<div class="ios-form-group">
				<label class="ios-label">Full Name *</label>
				<input type="text" name="name" class="ios-input" placeholder="e.g. Rahul Sharma" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
			</div>

			<div class="row">
				<div class="col-sm-6">
					<div class="ios-form-group">
						<label class="ios-label">Work Email *</label>
						<input type="email" name="email" class="ios-input" placeholder="name@domain.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
					</div>
				</div>
				<div class="col-sm-6">
					<div class="ios-form-group">
						<label class="ios-label">Mobile Number *</label>
						<input type="tel" name="mobile" class="ios-input" placeholder="+91 9876543210" required value="<?php echo htmlspecialchars($_POST['mobile'] ?? ''); ?>">
					</div>
				</div>
			</div>

			<div class="ios-form-group">
				<label class="ios-label">Requested Position / Role *</label>
				
				<label class="role-selector-card">
					<input type="radio" name="requested_role" value="trainer" id="roleTrainer" checked onchange="toggleTrainerFields(true)">
					<div style="flex: 1;">
						<div style="font-weight: 700; color: #34d399; font-size: 13.5px;"><i class="fa fa-heartbeat"></i> Fitness Coach / Trainer</div>
						<div style="font-size: 12px; color: #94a3b8;">Create routines, coach assigned members, log progress metrics</div>
					</div>
				</label>

				<label class="role-selector-card">
					<input type="radio" name="requested_role" value="floor_staff" id="roleFloor" onchange="toggleTrainerFields(false)">
					<div style="flex: 1;">
						<div style="font-weight: 700; color: #a78bfa; font-size: 13.5px;"><i class="fa fa-id-badge"></i> Front Desk &amp; Floor Staff</div>
						<div style="font-size: 12px; color: #94a3b8;">Attendance check-in/out scanner, member lookups, live floor count</div>
					</div>
				</label>

				<label class="role-selector-card">
					<input type="radio" name="requested_role" value="manager" id="roleManager" onchange="toggleTrainerFields(false)">
					<div style="flex: 1;">
						<div style="font-weight: 700; color: #60a5fa; font-size: 13.5px;"><i class="fa fa-briefcase"></i> Operations Manager</div>
						<div style="font-size: 12px; color: #94a3b8;">Manage bookings, plans, memberships, coaches, and daily operations</div>
					</div>
				</label>
			</div>

			<!-- Dynamic Trainer Fields -->
			<div id="trainerExtraFields">
				<div class="ios-form-group">
					<label class="ios-label">Coaching Speciality / Genre</label>
					<input type="text" name="speciality" class="ios-input" placeholder="e.g. Strength & Conditioning, CrossFit, Fat Loss" value="<?php echo htmlspecialchars($_POST['speciality'] ?? ''); ?>">
				</div>
				<div class="ios-form-group">
					<label class="ios-label">Short Bio / Certifications</label>
					<textarea name="bio" class="ios-input" rows="2" placeholder="e.g. ACE Certified Coach with 4 years experience"><?php echo htmlspecialchars($_POST['bio'] ?? ''); ?></textarea>
				</div>
			</div>

			<div class="row">
				<div class="col-sm-6">
					<div class="ios-form-group">
						<label class="ios-label">Password *</label>
						<div class="ios-input-icon-wrapper">
							<input type="password" name="password" id="staffPass" class="ios-input" placeholder="Min 6 characters" required>
							<button type="button" class="pwd-toggle-btn" onclick="togglePass('staffPass', 'eye1')" aria-label="Toggle password view">
								<i class="fa fa-eye" id="eye1"></i>
							</button>
						</div>
					</div>
				</div>
				<div class="col-sm-6">
					<div class="ios-form-group">
						<label class="ios-label">Confirm Password *</label>
						<div class="ios-input-icon-wrapper">
							<input type="password" name="repeat_password" id="staffRepeatPass" class="ios-input" placeholder="Repeat password" required>
							<button type="button" class="pwd-toggle-btn" onclick="togglePass('staffRepeatPass', 'eye2')" aria-label="Toggle password view">
								<i class="fa fa-eye" id="eye2"></i>
							</button>
						</div>
					</div>
				</div>
			</div>

			<div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 12px; padding: 12px; font-size: 12.5px; color: #fde68a; margin-bottom: 20px;">
				<i class="fa fa-info-circle"></i> <strong>Owner Approval Required:</strong> Your account will be submitted to the Gym Owner for verification before you can access the system.
			</div>

			<button type="submit" class="ios-btn ios-btn-primary" style="width: 100%;">
				<i class="fa fa-paper-plane"></i> Submit Registration Request
			</button>
		</form>

		<!-- Common Sign In Prompt -->
		<div class="signin-prompt-box">
			<div style="font-size: 13.5px; color: #94a3b8; font-weight: 500;">
				Already have an account? Sign in below:
			</div>
			<div class="signin-pill-group">
				<a href="../login.php?portal=member" class="signin-pill signin-pill-member">
					<i class="fa fa-user"></i> Member Sign In
				</a>
				<a href="login.php" class="signin-pill signin-pill-staff">
					<i class="fa fa-shield"></i> Staff Sign In
				</a>
			</div>
		</div>
	</div>

	<script>
		function toggleTrainerFields(show) {
			var el = document.getElementById('trainerExtraFields');
			if (el) el.style.display = show ? 'block' : 'none';
		}
		function togglePass(id, eyeId) {
			var el = document.getElementById(id);
			var icon = document.getElementById(eyeId);
			if (!el) return;
			if (el.type === 'password') {
				el.type = 'text';
				if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
			} else {
				el.type = 'password';
				if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
			}
		}
	</script>
</body>
</html>
