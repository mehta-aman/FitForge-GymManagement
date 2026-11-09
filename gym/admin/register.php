<?php
require_once 'include/config.php';

$msg = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $requestedRole = trim($_POST['requested_role'] ?? 'floor_staff');
        $rawPassword = $_POST['password'] ?? '';
        $repeatPassword = $_POST['repeat_password'] ?? '';
        $speciality = trim($_POST['speciality'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $gymId = get_current_gym_id();

        // Allowed self-registration roles (Owner cannot be self-registered)
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
            // Check if email already exists in tbladmin
            $chk = $dbh->prepare("SELECT id FROM tbladmin WHERE email = :email LIMIT 1");
            $chk->execute([':email' => $email]);
            if ($chk->fetch()) {
                $error = "An employee account with this email already exists.";
            } else {
                $hash = password_hash($rawPassword, PASSWORD_DEFAULT);
                $trainerId = null;

                // If trainer, pre-create a profile in tbl_trainers
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

                // Insert staff account with status = 'pending'
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Staff Registration — <?php echo htmlspecialchars(get_setting('gym_name', 'GYM MS')); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
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
			max-width: 520px;
			box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
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
		}
		.role-selector-card:hover {
			border-color: rgba(56, 189, 248, 0.4);
			background: rgba(56, 189, 248, 0.08);
		}
		.role-selector-card input[type="radio"] {
			accent-color: #38bdf8;
			width: 18px;
			height: 18px;
		}
	</style>
</head>
<body>

	<div class="reg-card">
		<div style="text-align: center; margin-bottom: 24px;">
			<div style="width: 64px; height: 64px; border-radius: 20px; background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.3); color: #38bdf8; font-size: 26px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
				<i class="fa fa-user-plus"></i>
			</div>
			<h2 style="font-size: 24px; font-weight: 800; color: #fff; margin-bottom: 4px;">Staff Account Registration</h2>
			<p style="font-size: 13.5px; color: #94a3b8; margin: 0;">Request employee or trainer access to <?php echo htmlspecialchars(get_setting('gym_name', 'Golden Eagle')); ?></p>
		</div>

		<?php if (!empty($error)): ?>
			<div class="ios-badge ios-badge-danger" style="display: flex; margin-bottom: 20px; padding: 12px 16px; border-radius: 14px; text-align: left;">
				<i class="fa fa-exclamation-circle" style="font-size: 16px; margin-right: 8px; flex-shrink: 0; margin-top: 2px;"></i>
				<span><?php echo htmlspecialchars($error); ?></span>
			</div>
		<?php endif; ?>

		<form method="post" autocomplete="off">
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
						<input type="text" name="mobile" class="ios-input" placeholder="+91 9876543210" required value="<?php echo htmlspecialchars($_POST['mobile'] ?? ''); ?>">
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
						<input type="password" name="password" class="ios-input" placeholder="Min 6 characters" required>
					</div>
				</div>
				<div class="col-sm-6">
					<div class="ios-form-group">
						<label class="ios-label">Confirm Password *</label>
						<input type="password" name="repeat_password" class="ios-input" placeholder="Repeat password" required>
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

		<div style="margin-top: 24px; text-align: center; font-size: 13.5px; color: #94a3b8;">
			Already have an approved account? <a href="login.php" style="color: var(--color-primary); font-weight: 600; text-decoration: none;">Staff Sign In</a>
		</div>
	</div>

	<script>
		function toggleTrainerFields(show) {
			var el = document.getElementById('trainerExtraFields');
			if (el) el.style.display = show ? 'block' : 'none';
		}
	</script>
</body>
</html>