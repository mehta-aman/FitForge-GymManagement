<?php
require_once 'include/config.php';

$error = "";
$success = "";

// Check active portal tab ('member' or 'staff')
$portalParam = trim($_GET['portal'] ?? $_POST['portal'] ?? 'member');
$activePortal = in_array($portalParam, ['staff', 'admin'], true) ? 'staff' : 'member';

// Sanitize redirect parameter
$redirectParam = trim($_GET['redirect'] ?? $_POST['redirect'] ?? '');
if (!empty($redirectParam)) {
    if (str_starts_with($redirectParam, '/') || str_contains($redirectParam, ':') || str_contains($redirectParam, '\\')) {
        $redirectParam = '';
    }
}

// If already logged in, redirect away
if (!empty($_SESSION['uid'])) {
    header("location: user-dashboard.php");
    exit();
}
if (!empty($_SESSION['adminid'])) {
    header("location: admin/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $regType = trim($_POST['portal'] ?? 'member');
        $activePortal = ($regType === 'staff') ? 'staff' : 'member';
        $gymId = function_exists('get_current_gym_id') ? get_current_gym_id() : 1;

        if ($activePortal === 'staff') {
            // ==================== STAFF APPLICATION ====================
            $name = trim($_POST['staff_name'] ?? '');
            $email = trim($_POST['staff_email'] ?? '');
            $mobile = trim($_POST['staff_mobile'] ?? '');
            $requestedRole = trim($_POST['requested_role'] ?? 'trainer');
            $rawPassword = $_POST['staff_password'] ?? '';
            $repeatPassword = $_POST['staff_repeat_password'] ?? '';
            $speciality = trim($_POST['speciality'] ?? '');
            $bio = trim($_POST['bio'] ?? '');

            $allowedRoles = ['manager', 'trainer', 'floor_staff'];

            if (empty($name) || empty($email) || empty($mobile) || empty($rawPassword)) {
                $error = "All required staff fields must be filled.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please enter a valid work email address.";
            } elseif (!in_array($requestedRole, $allowedRoles, true)) {
                $error = "Please select a valid staff position.";
            } elseif (strlen($rawPassword) < 6) {
                $error = "Password must be at least 6 characters long.";
            } elseif ($rawPassword !== $repeatPassword) {
                $error = "Passwords do not match.";
            } else {
                // Check if email exists in tbladmin
                $chkAdmin = $dbh->prepare("SELECT id FROM tbladmin WHERE email = :email LIMIT 1");
                $chkAdmin->execute([':email' => $email]);
                if ($chkAdmin->fetch()) {
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

                    header("location: login.php?portal=staff&applied=1");
                    exit();
                }
            }
        } else {
            // ==================== MEMBER REGISTRATION ====================
            $fname = trim($_POST['fname'] ?? '');
            $lname = trim($_POST['lname'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $rawPassword = $_POST['password'] ?? '';
            $repeatPassword = $_POST['RepeatPassword'] ?? '';

            if (empty($fname)) {
                $error = "Please enter your first name.";
            } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please enter a valid email address.";
            } elseif (empty($mobile)) {
                $error = "Please enter your mobile phone number.";
            } elseif (empty($rawPassword) || empty($repeatPassword)) {
                $error = "Password and confirm password cannot be empty.";
            } elseif (strlen($rawPassword) < 6) {
                $error = "Password must be at least 6 characters long.";
            } elseif ($rawPassword !== $repeatPassword) {
                $error = "Passwords do not match.";
            } else {
                // Check if user already exists
                $chkUser = $dbh->prepare("SELECT id FROM tbluser WHERE email = :email OR mobile = :mobile LIMIT 1");
                $chkUser->execute([':email' => $email, ':mobile' => $mobile]);
                if ($chkUser->fetch()) {
                    $error = "A member account with this email or mobile number already exists!";
                } else {
                    $hash = password_hash($rawPassword, PASSWORD_DEFAULT);
                    $insUser = $dbh->prepare("
                        INSERT INTO tbluser (fname, lname, email, mobile, state, city, password, status, gym_id, create_date)
                        VALUES (:fname, :lname, :email, :mobile, :state, :city, :pwd, 'active', :gym_id, NOW())
                    ");
                    $insUser->execute([
                        ':fname' => $fname,
                        ':lname' => $lname,
                        ':email' => $email,
                        ':mobile' => $mobile,
                        ':state' => $state,
                        ':city' => $city,
                        ':pwd' => $hash,
                        ':gym_id' => $gymId
                    ]);

                    $target = 'login.php?portal=member&registered=1';
                    if (!empty($redirectParam)) {
                        $target .= '&redirect=' . urlencode($redirectParam);
                    }
                    header("location: " . $target);
                    exit();
                }
            }
        }
    }
}

$gymTitle = get_setting('gym_name', 'GYM MS');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Create Account / Apply — <?php echo htmlspecialchars($gymTitle); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
	<?php echo render_favicon_tags(); ?>
	<link rel="stylesheet" href="css/bootstrap.min.css"/>
	<link rel="stylesheet" href="css/font-awesome.min.css"/>
	<link rel="stylesheet" href="css/ios-glass.css"/>
	<style>
		.ios-auth-card {
			max-width: 620px;
			margin: 36px auto;
			padding: 36px 32px;
			background: linear-gradient(135deg, rgba(30, 41, 59, 0.8) 0%, rgba(15, 23, 42, 0.92) 100%);
			backdrop-filter: blur(36px) saturate(200%);
			-webkit-backdrop-filter: blur(36px) saturate(200%);
			border: 1px solid rgba(255, 255, 255, 0.16);
			border-radius: 28px;
			box-shadow: 0 30px 65px -15px rgba(0, 0, 0, 0.65), inset 0 1px 1px rgba(255, 255, 255, 0.2);
			position: relative;
		}

		/* Segmented Switcher */
		.ios-portal-switcher {
			display: flex;
			background: rgba(15, 23, 42, 0.7);
			border: 1px solid rgba(255, 255, 255, 0.12);
			border-radius: 16px;
			padding: 4px;
			gap: 4px;
			margin: 22px 0 20px 0;
			position: relative;
		}
		.portal-tab-btn {
			flex: 1;
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			padding: 11px 14px;
			font-size: 13.5px;
			font-weight: 700;
			color: #94a3b8;
			background: transparent;
			border: 1px solid transparent;
			border-radius: 12px;
			cursor: pointer;
			transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
			outline: none;
			user-select: none;
		}
		.portal-tab-btn:hover {
			color: #f1f5f9;
			background: rgba(255, 255, 255, 0.05);
		}
		.portal-tab-btn.active {
			color: #ffffff;
			background: linear-gradient(135deg, rgba(56, 189, 248, 0.24) 0%, rgba(99, 102, 241, 0.3) 100%);
			border-color: rgba(56, 189, 248, 0.45);
			box-shadow: 0 4px 16px rgba(56, 189, 248, 0.25);
		}
		.portal-tab-btn.active.staff-active {
			background: linear-gradient(135deg, rgba(99, 102, 241, 0.28) 0%, rgba(168, 85, 247, 0.32) 100%);
			border-color: rgba(129, 140, 248, 0.5);
			box-shadow: 0 4px 16px rgba(99, 102, 241, 0.3);
		}

		/* Helper Info Box */
		.portal-info-box {
			display: flex;
			align-items: center;
			gap: 12px;
			padding: 12px 16px;
			border-radius: 14px;
			font-size: 13px;
			line-height: 1.45;
			margin-bottom: 24px;
			text-align: left;
			transition: all 0.2s ease;
		}
		.portal-info-box.member-box {
			background: rgba(56, 189, 248, 0.1);
			border: 1px solid rgba(56, 189, 248, 0.25);
			color: #7dd3fc;
		}
		.portal-info-box.staff-box {
			background: rgba(129, 140, 248, 0.12);
			border: 1px solid rgba(129, 140, 248, 0.3);
			color: #c7d2fe;
		}

		/* Role Selector Card for Staff */
		.staff-role-card {
			border: 1px solid rgba(255, 255, 255, 0.12);
			border-radius: 14px;
			padding: 12px 14px;
			background: rgba(0, 0, 0, 0.25);
			cursor: pointer;
			transition: all 0.2s ease;
			margin-bottom: 10px;
			display: flex;
			align-items: center;
			gap: 12px;
			text-align: left;
		}
		.staff-role-card:hover {
			border-color: rgba(56, 189, 248, 0.4);
			background: rgba(56, 189, 248, 0.08);
		}
		.staff-role-card.selected {
			border-color: rgba(56, 189, 248, 0.6);
			background: rgba(56, 189, 248, 0.14);
		}
		.staff-role-card input[type="radio"] {
			accent-color: #38bdf8;
			width: 18px;
			height: 18px;
			flex-shrink: 0;
		}

		/* Password Toggle */
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

		/* Sign In Switcher Box */
		.signin-prompt-box {
			margin-top: 26px;
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
			transform: translateY(-1px);
		}
		.signin-pill-staff {
			background: rgba(129, 140, 248, 0.12);
			border: 1px solid rgba(129, 140, 248, 0.3);
			color: #a5b4fc;
		}
		.signin-pill-staff:hover {
			background: rgba(129, 140, 248, 0.24);
			color: #fff;
			transform: translateY(-1px);
		}

		@media (max-width: 576px) {
			.ios-auth-card {
				margin: 16px 8px;
				padding: 24px 16px;
				border-radius: 22px;
			}
			.signin-pill-group {
				flex-direction: column;
			}
		}
	</style>
</head>
<body>

	<?php include 'include/header.php';?>

	<div class="site-content-wrapper" style="padding: 40px 16px;">
		<div class="container">
			<div class="ios-auth-card">

				<div style="text-align: center; margin-bottom: 8px;">
					<div class="ios-auth-icon" id="regPortalIcon" style="transition: all 0.3s ease;">
						<i class="fa <?php echo $activePortal === 'staff' ? 'fa-id-badge' : 'fa-user-plus'; ?>" id="regIconClass"></i>
					</div>
					<h2 class="ios-auth-title" id="regCardTitle">
						<?php echo $activePortal === 'staff' ? 'Staff & Coach Application' : 'Join Gym Club'; ?>
					</h2>
					<p class="ios-auth-subtitle" id="regCardSubtitle">
						<?php echo $activePortal === 'staff' ? 'Request staff credentials for trainers, managers & floor crew' : 'Create your personal account for workouts, digital pass & tracking'; ?>
					</p>
				</div>

				<!-- Segmented Switcher for Member vs Staff -->
				<div class="ios-portal-switcher" role="tablist">
					<button type="button" class="portal-tab-btn <?php echo $activePortal === 'member' ? 'active' : ''; ?>" id="tabMember" onclick="switchRegPortal('member')">
						<i class="fa fa-user-plus"></i>
						<span>Join as Member</span>
					</button>
					<button type="button" class="portal-tab-btn <?php echo $activePortal === 'staff' ? 'active staff-active' : ''; ?>" id="tabStaff" onclick="switchRegPortal('staff')">
						<i class="fa fa-briefcase"></i>
						<span>Apply as Staff / Coach</span>
					</button>
				</div>

				<!-- Helper Info Banner -->
				<div class="portal-info-box <?php echo $activePortal === 'staff' ? 'staff-box' : 'member-box'; ?>" id="regInfoBanner">
					<i class="fa <?php echo $activePortal === 'staff' ? 'fa-shield' : 'fa-check-circle'; ?>" id="regInfoIcon" style="font-size: 18px; flex-shrink: 0;"></i>
					<span id="regInfoText">
						<?php if ($activePortal === 'staff'): ?>
							<strong>Staff Application:</strong> Fill in your credentials and position. The Gym Owner will review and approve your account.
						<?php else: ?>
							<strong>Instant Access:</strong> Register your member profile in seconds and gain full access to gym check-in, workouts, and bookings.
						<?php endif; ?>
					</span>
				</div>

				<?php if(!empty($error)): ?>
					<div class="ios-badge ios-badge-danger" style="display: flex; align-items: center; margin-bottom: 22px; padding: 13px 16px; border-radius: 14px; text-align: left;">
						<i class="fa fa-exclamation-circle" style="font-size: 17px; margin-right: 10px; flex-shrink: 0;"></i>
						<span style="font-size: 13.5px;"><?php echo htmlspecialchars($error); ?></span>
					</div>
				<?php endif; ?>

				<!-- ==================== TAB 1: MEMBER REGISTRATION ==================== -->
				<form method="post" autocomplete="off" id="memberForm" style="<?php echo $activePortal === 'member' ? 'display: block;' : 'display: none;'; ?>">
					<?php echo csrf_input(); ?>
					<input type="hidden" name="portal" value="member">
					<?php if (!empty($redirectParam)): ?>
						<input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirectParam, ENT_QUOTES, 'UTF-8'); ?>">
					<?php endif; ?>

					<div class="row">
						<div class="col-sm-6">
							<div class="ios-form-group" style="text-align: left;">
								<label class="ios-label">First Name *</label>
								<input type="text" name="fname" class="ios-input" placeholder="e.g. John" required value="<?php echo htmlspecialchars($_POST['fname'] ?? ''); ?>">
							</div>
						</div>
						<div class="col-sm-6">
							<div class="ios-form-group" style="text-align: left;">
								<label class="ios-label">Last Name</label>
								<input type="text" name="lname" class="ios-input" placeholder="e.g. Doe" value="<?php echo htmlspecialchars($_POST['lname'] ?? ''); ?>">
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-sm-6">
							<div class="ios-form-group" style="text-align: left;">
								<label class="ios-label">Email Address *</label>
								<input type="email" name="email" class="ios-input" placeholder="john@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
							</div>
						</div>
						<div class="col-sm-6">
							<div class="ios-form-group" style="text-align: left;">
								<label class="ios-label">Mobile Number *</label>
								<input type="tel" name="mobile" class="ios-input" placeholder="e.g. 9876543210" required value="<?php echo htmlspecialchars($_POST['mobile'] ?? ''); ?>">
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-sm-6">
							<div class="ios-form-group" style="text-align: left;">
								<label class="ios-label">City</label>
								<input type="text" name="city" class="ios-input" placeholder="City" value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
							</div>
						</div>
						<div class="col-sm-6">
							<div class="ios-form-group" style="text-align: left;">
								<label class="ios-label">State / Region</label>
								<input type="text" name="state" class="ios-input" placeholder="State" value="<?php echo htmlspecialchars($_POST['state'] ?? ''); ?>">
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-sm-6">
							<div class="ios-form-group" style="text-align: left;">
								<label class="ios-label">Password *</label>
								<div class="ios-input-icon-wrapper">
									<input type="password" name="password" id="mPassword" class="ios-input" placeholder="Min 6 characters" required>
									<button type="button" class="pwd-toggle-btn" onclick="togglePasswordVisibility('mPassword', 'mEye1')" aria-label="Toggle password view">
										<i class="fa fa-eye" id="mEye1"></i>
									</button>
								</div>
							</div>
						</div>
						<div class="col-sm-6">
							<div class="ios-form-group" style="text-align: left;">
								<label class="ios-label">Confirm Password *</label>
								<div class="ios-input-icon-wrapper">
									<input type="password" name="RepeatPassword" id="mRepeatPassword" class="ios-input" placeholder="Repeat password" required>
									<button type="button" class="pwd-toggle-btn" onclick="togglePasswordVisibility('mRepeatPassword', 'mEye2')" aria-label="Toggle password view">
										<i class="fa fa-eye" id="mEye2"></i>
									</button>
								</div>
							</div>
						</div>
					</div>

					<button type="submit" name="submit" class="ios-btn ios-btn-primary" style="width: 100%; margin-top: 10px;">
						<i class="fa fa-user-plus"></i> Complete Member Registration
					</button>
				</form>

				<!-- ==================== TAB 2: STAFF APPLICATION ==================== -->
				<form method="post" autocomplete="off" id="staffForm" style="<?php echo $activePortal === 'staff' ? 'display: block;' : 'display: none;'; ?>">
					<?php echo csrf_input(); ?>
					<input type="hidden" name="portal" value="staff">

					<div class="ios-form-group" style="text-align: left;">
						<label class="ios-label">Full Name *</label>
						<input type="text" name="staff_name" class="ios-input" placeholder="e.g. Rahul Sharma" required value="<?php echo htmlspecialchars($_POST['staff_name'] ?? ''); ?>">
					</div>

					<div class="row">
						<div class="col-sm-6">
							<div class="ios-form-group" style="text-align: left;">
								<label class="ios-label">Work Email *</label>
								<input type="email" name="staff_email" class="ios-input" placeholder="staff@domain.com" required value="<?php echo htmlspecialchars($_POST['staff_email'] ?? ''); ?>">
							</div>
						</div>
						<div class="col-sm-6">
							<div class="ios-form-group" style="text-align: left;">
								<label class="ios-label">Mobile Number *</label>
								<input type="tel" name="staff_mobile" class="ios-input" placeholder="+91 9876543210" required value="<?php echo htmlspecialchars($_POST['staff_mobile'] ?? ''); ?>">
							</div>
						</div>
					</div>

					<div class="ios-form-group" style="text-align: left;">
						<label class="ios-label">Requested Position / Role *</label>
						
						<label class="staff-role-card <?php echo ($_POST['requested_role'] ?? 'trainer') === 'trainer' ? 'selected' : ''; ?>" id="cardRoleTrainer">
							<input type="radio" name="requested_role" value="trainer" id="roleTrainer" <?php echo ($_POST['requested_role'] ?? 'trainer') === 'trainer' ? 'checked' : ''; ?> onchange="onRoleChange('trainer')">
							<div style="flex: 1;">
								<div style="font-weight: 700; color: #34d399; font-size: 13.5px;"><i class="fa fa-heartbeat"></i> Fitness Coach / Trainer</div>
								<div style="font-size: 12px; color: #94a3b8;">Create workout routines, coach assigned members, track progress</div>
							</div>
						</label>

						<label class="staff-role-card <?php echo ($_POST['requested_role'] ?? '') === 'floor_staff' ? 'selected' : ''; ?>" id="cardRoleFloor">
							<input type="radio" name="requested_role" value="floor_staff" id="roleFloor" <?php echo ($_POST['requested_role'] ?? '') === 'floor_staff' ? 'checked' : ''; ?> onchange="onRoleChange('floor_staff')">
							<div style="flex: 1;">
								<div style="font-weight: 700; color: #a78bfa; font-size: 13.5px;"><i class="fa fa-id-badge"></i> Front Desk &amp; Floor Staff</div>
								<div style="font-size: 12px; color: #94a3b8;">Attendance check-in/out scanner, member lookups, live floor count</div>
							</div>
						</label>

						<label class="staff-role-card <?php echo ($_POST['requested_role'] ?? '') === 'manager' ? 'selected' : ''; ?>" id="cardRoleManager">
							<input type="radio" name="requested_role" value="manager" id="roleManager" <?php echo ($_POST['requested_role'] ?? '') === 'manager' ? 'checked' : ''; ?> onchange="onRoleChange('manager')">
							<div style="flex: 1;">
								<div style="font-weight: 700; color: #60a5fa; font-size: 13.5px;"><i class="fa fa-briefcase"></i> Operations Manager</div>
								<div style="font-size: 12px; color: #94a3b8;">Manage bookings, plans, memberships, coaches, and daily operations</div>
							</div>
						</label>
					</div>

					<!-- Dynamic Trainer Fields -->
					<div id="trainerExtraFields" style="<?php echo ($_POST['requested_role'] ?? 'trainer') === 'trainer' ? 'display: block;' : 'display: none;'; ?>">
						<div class="ios-form-group" style="text-align: left;">
							<label class="ios-label">Coaching Speciality / Genre</label>
							<input type="text" name="speciality" class="ios-input" placeholder="e.g. Strength & Conditioning, CrossFit, Fat Loss" value="<?php echo htmlspecialchars($_POST['speciality'] ?? ''); ?>">
						</div>
						<div class="ios-form-group" style="text-align: left;">
							<label class="ios-label">Short Bio / Certifications</label>
							<textarea name="bio" class="ios-input" rows="2" placeholder="e.g. Certified Trainer with 4+ years coaching experience"><?php echo htmlspecialchars($_POST['bio'] ?? ''); ?></textarea>
						</div>
					</div>

					<div class="row">
						<div class="col-sm-6">
							<div class="ios-form-group" style="text-align: left;">
								<label class="ios-label">Password *</label>
								<div class="ios-input-icon-wrapper">
									<input type="password" name="staff_password" id="sPassword" class="ios-input" placeholder="Min 6 characters" required>
									<button type="button" class="pwd-toggle-btn" onclick="togglePasswordVisibility('sPassword', 'sEye1')" aria-label="Toggle password view">
										<i class="fa fa-eye" id="sEye1"></i>
									</button>
								</div>
							</div>
						</div>
						<div class="col-sm-6">
							<div class="ios-form-group" style="text-align: left;">
								<label class="ios-label">Confirm Password *</label>
								<div class="ios-input-icon-wrapper">
									<input type="password" name="staff_repeat_password" id="sRepeatPassword" class="ios-input" placeholder="Repeat password" required>
									<button type="button" class="pwd-toggle-btn" onclick="togglePasswordVisibility('sRepeatPassword', 'sEye2')" aria-label="Toggle password view">
										<i class="fa fa-eye" id="sEye2"></i>
									</button>
								</div>
							</div>
						</div>
					</div>

					<div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.28); border-radius: 12px; padding: 12px 14px; font-size: 12.5px; color: #fde68a; margin-bottom: 20px; text-align: left;">
						<i class="fa fa-info-circle"></i> <strong>Gym Owner Approval Required:</strong> Your application will be sent to the Owner for review before access is enabled.
					</div>

					<button type="submit" name="submit" class="ios-btn ios-btn-primary" style="width: 100%;">
						<i class="fa fa-paper-plane"></i> Submit Staff Application
					</button>
				</form>

				<!-- Common Sign In Prompt -->
				<div class="signin-prompt-box">
					<div style="font-size: 13.5px; color: #94a3b8; font-weight: 500;">
						Already have an account? Sign in below:
					</div>
					<div class="signin-pill-group">
						<a href="login.php?portal=member<?php echo !empty($redirectParam) ? '&redirect=' . urlencode($redirectParam) : ''; ?>" class="signin-pill signin-pill-member">
							<i class="fa fa-user"></i> Member Sign In
						</a>
						<a href="login.php?portal=staff" class="signin-pill signin-pill-staff">
							<i class="fa fa-shield"></i> Staff Sign In
						</a>
					</div>
				</div>

			</div>
		</div>

		<?php include 'include/footer.php'; ?>
	</div>

	<script src="js/vendor/jquery-3.2.1.min.js"></script>
	<script>
		function switchRegPortal(type) {
			var memberForm = document.getElementById('memberForm');
			var staffForm = document.getElementById('staffForm');
			var tabMember = document.getElementById('tabMember');
			var tabStaff = document.getElementById('tabStaff');
			var infoBanner = document.getElementById('regInfoBanner');
			var infoIcon = document.getElementById('regInfoIcon');
			var infoText = document.getElementById('regInfoText');
			var cardTitle = document.getElementById('regCardTitle');
			var cardSubtitle = document.getElementById('regCardSubtitle');
			var iconClass = document.getElementById('regIconClass');

			if (type === 'staff') {
				memberForm.style.display = 'none';
				staffForm.style.display = 'block';

				tabMember.classList.remove('active');
				tabStaff.classList.add('active', 'staff-active');

				infoBanner.className = 'portal-info-box staff-box';
				if (infoIcon) infoIcon.className = 'fa fa-shield';
				infoText.innerHTML = '<strong>Staff Application:</strong> Fill in your credentials and position. The Gym Owner will review and approve your account.';

				cardTitle.textContent = 'Staff & Coach Application';
				cardSubtitle.textContent = 'Request staff credentials for trainers, managers & floor crew';
				if (iconClass) iconClass.className = 'fa fa-id-badge';
			} else {
				staffForm.style.display = 'none';
				memberForm.style.display = 'block';

				tabStaff.classList.remove('active', 'staff-active');
				tabMember.classList.add('active');

				infoBanner.className = 'portal-info-box member-box';
				if (infoIcon) infoIcon.className = 'fa fa-check-circle';
				infoText.innerHTML = '<strong>Instant Access:</strong> Register your member profile in seconds and gain full access to gym check-in, workouts, and bookings.';

				cardTitle.textContent = 'Join Gym Club';
				cardSubtitle.textContent = 'Create your personal account for workouts, digital pass & tracking';
				if (iconClass) iconClass.className = 'fa fa-user-plus';
			}
		}

		function onRoleChange(role) {
			var extra = document.getElementById('trainerExtraFields');
			if (extra) {
				extra.style.display = (role === 'trainer') ? 'block' : 'none';
			}
			document.getElementById('cardRoleTrainer').classList.toggle('selected', role === 'trainer');
			document.getElementById('cardRoleFloor').classList.toggle('selected', role === 'floor_staff');
			document.getElementById('cardRoleManager').classList.toggle('selected', role === 'manager');
		}

		function togglePasswordVisibility(inputId, iconId) {
			var field = document.getElementById(inputId);
			var icon = document.getElementById(iconId);
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
</body>
</html>
