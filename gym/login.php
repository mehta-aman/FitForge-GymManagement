<?php
require_once 'include/config.php';
$msg = ""; 
$successMsg = "";

// Check flash messages from registration or applications
if (!empty($_GET['registered'])) {
    $successMsg = "🎉 Account created successfully! Please sign in with your email and password.";
} elseif (!empty($_GET['applied'])) {
    $successMsg = "📋 Staff application submitted! Your account is pending Gym Owner approval. You will receive access once reviewed.";
}

// Redirect parameter for deep linking / QR check-in
$redirectParam = trim($_GET['redirect'] ?? $_POST['redirect'] ?? '');
if (!empty($redirectParam)) {
    // Sanitize: allow only safe relative path without external URL schemes or double slashes
    if (str_starts_with($redirectParam, '/') || str_contains($redirectParam, ':') || str_contains($redirectParam, '\\')) {
        $redirectParam = '';
    }
}

// Check active portal tab ('member' or 'staff')
$portalParam = trim($_GET['portal'] ?? $_POST['portal'] ?? 'member');
$activePortal = in_array($portalParam, ['staff', 'admin'], true) ? 'staff' : 'member';

// If already logged in, redirect immediately to the respective destination
if (!empty($_SESSION['uid'])) {
    $target = !empty($redirectParam) ? $redirectParam : 'user-dashboard.php';
    header("location: " . $target);
    exit();
}
if (!empty($_SESSION['adminid'])) {
    header("location: admin/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = "Security token mismatch. Please try again.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $rawPassword = $_POST['password'] ?? '';
        $chosenPortal = trim($_POST['portal'] ?? 'member');
        $activePortal = ($chosenPortal === 'staff' || $chosenPortal === 'admin') ? 'staff' : 'member';

        // Check brute-force throttling
        $throttle = check_login_throttle($email, $dbh);
        if (!$throttle['allowed']) {
            $msg = $throttle['message'];
        } elseif ($email !== "" && $rawPassword !== "") {
            try {
                // Dual Authentication Strategy with Role Preference
                if ($activePortal === 'staff') {
                    // Try Staff (tbladmin) first
                    $aStmt = $dbh->prepare("SELECT id, name, email, mobile, password, role, status, trainer_id, rejection_reason, gym_id FROM tbladmin WHERE email = :email LIMIT 1");
                    $aStmt->bindParam(':email', $email, PDO::PARAM_STR);
                    $aStmt->execute();
                    $adminRow = $aStmt->fetch(PDO::FETCH_ASSOC);

                    if ($adminRow && verify_and_rehash_password($rawPassword, $adminRow['password'], 'tbladmin', 'id', $adminRow['id'], $dbh)) {
                        $adminStatus = $adminRow['status'] ?? 'pending';
                        if ($adminStatus === 'pending') {
                            $msg = "⏳ Your staff account is currently pending approval by the Gym Owner. You will be able to log in once reviewed.";
                        } elseif ($adminStatus === 'rejected') {
                            $msg = "❌ Your staff registration request was not approved." . (!empty($adminRow['rejection_reason']) ? " Reason: " . htmlspecialchars($adminRow['rejection_reason']) : "");
                        } elseif ($adminStatus === 'suspended') {
                            $msg = "⛔ This staff account has been suspended by the Gym Owner. Please contact management.";
                        } else {
                            // Staff login approved
                            clear_login_attempts($email, $dbh);
                            regenerate_secure_session();
                            $_SESSION['adminid']      = $adminRow['id'];
                            $_SESSION['email']        = $adminRow['email'];
                            $_SESSION['name']         = $adminRow['name'];
                            $_SESSION['admin_role']   = $adminRow['role'] ?? 'floor_staff';
                            $_SESSION['admin_status'] = $adminRow['status'] ?? 'approved';
                            $_SESSION['trainer_id']   = $adminRow['trainer_id'] ?? null;
                            $_SESSION['gym_id']       = $adminRow['gym_id'] ?? 1;

                            $upd = $dbh->prepare("UPDATE tbladmin SET last_login = NOW() WHERE id = :id");
                            $upd->execute([':id' => $adminRow['id']]);

                            header("location: admin/index.php");
                            exit();
                        }
                    } else {
                        // Smart Fallback: Check if this user is actually a Member who selected the Staff tab!
                        $uStmt = $dbh->prepare("SELECT id, fname, lname, email, mobile, password, address, status, gym_id FROM tbluser WHERE email = :email LIMIT 1");
                        $uStmt->bindParam(':email', $email, PDO::PARAM_STR);
                        $uStmt->execute();
                        $userRow = $uStmt->fetch(PDO::FETCH_ASSOC);

                        if ($userRow && verify_and_rehash_password($rawPassword, $userRow['password'], 'tbluser', 'id', $userRow['id'], $dbh)) {
                            if (isset($userRow['status']) && $userRow['status'] === 'inactive') {
                                $msg = "Your member account has been deactivated. Please contact gym administration.";
                            } else {
                                clear_login_attempts($email, $dbh);
                                regenerate_secure_session();
                                $_SESSION['uid']    = $userRow['id'];
                                $_SESSION['email']  = $userRow['email'];
                                $_SESSION['name']   = $userRow['fname'];
                                $_SESSION['gym_id'] = $userRow['gym_id'] ?? 1;

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
                            $msg = "Invalid email or password. Please verify your credentials.";
                        }
                    }
                } else {
                    // Try Member (tbluser) first
                    $uStmt = $dbh->prepare("SELECT id, fname, lname, email, mobile, password, address, status, gym_id FROM tbluser WHERE email = :email LIMIT 1");
                    $uStmt->bindParam(':email', $email, PDO::PARAM_STR);
                    $uStmt->execute();
                    $userRow = $uStmt->fetch(PDO::FETCH_ASSOC);

                    if ($userRow && verify_and_rehash_password($rawPassword, $userRow['password'], 'tbluser', 'id', $userRow['id'], $dbh)) {
                        if (isset($userRow['status']) && $userRow['status'] === 'inactive') {
                            $msg = "Your member account has been deactivated. Please contact gym administration.";
                        } else {
                            clear_login_attempts($email, $dbh);
                            regenerate_secure_session();
                            $_SESSION['uid']    = $userRow['id'];
                            $_SESSION['email']  = $userRow['email'];
                            $_SESSION['name']   = $userRow['fname'];
                            $_SESSION['gym_id'] = $userRow['gym_id'] ?? 1;

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
                        // Smart Fallback: Check if this user is a Staff member who left the tab on Member!
                        $aStmt = $dbh->prepare("SELECT id, name, email, mobile, password, role, status, trainer_id, rejection_reason, gym_id FROM tbladmin WHERE email = :email LIMIT 1");
                        $aStmt->bindParam(':email', $email, PDO::PARAM_STR);
                        $aStmt->execute();
                        $adminRow = $aStmt->fetch(PDO::FETCH_ASSOC);

                        if ($adminRow && verify_and_rehash_password($rawPassword, $adminRow['password'], 'tbladmin', 'id', $adminRow['id'], $dbh)) {
                            $activePortal = 'staff';
                            $adminStatus = $adminRow['status'] ?? 'pending';
                            if ($adminStatus === 'pending') {
                                $msg = "⏳ Your staff account is currently pending approval by the Gym Owner. You will be able to log in once reviewed.";
                            } elseif ($adminStatus === 'rejected') {
                                $msg = "❌ Your staff registration request was not approved." . (!empty($adminRow['rejection_reason']) ? " Reason: " . htmlspecialchars($adminRow['rejection_reason']) : "");
                            } elseif ($adminStatus === 'suspended') {
                                $msg = "⛔ This staff account has been suspended by the Gym Owner.";
                            } else {
                                // Auto-routed to staff admin
                                clear_login_attempts($email, $dbh);
                                regenerate_secure_session();
                                $_SESSION['adminid']      = $adminRow['id'];
                                $_SESSION['email']        = $adminRow['email'];
                                $_SESSION['name']         = $adminRow['name'];
                                $_SESSION['admin_role']   = $adminRow['role'] ?? 'floor_staff';
                                $_SESSION['admin_status'] = $adminRow['status'] ?? 'approved';
                                $_SESSION['trainer_id']   = $adminRow['trainer_id'] ?? null;
                                $_SESSION['gym_id']       = $adminRow['gym_id'] ?? 1;

                                $upd = $dbh->prepare("UPDATE tbladmin SET last_login = NOW() WHERE id = :id");
                                $upd->execute([':id' => $adminRow['id']]);

                                header("location: admin/index.php");
                                exit();
                            }
                        } else {
                            record_failed_login($email, $dbh);
                            $msg = "Invalid email or password. Please verify your credentials.";
                        }
                    }
                }
            } catch (PDOException $e) {
                $msg = "Database Error: " . $e->getMessage();
            }
        } else {
            $msg = "Both email and password are required!";
        }
    }
}

$gymTitle = get_setting('gym_name', 'GYM MS');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Sign In — <?php echo htmlspecialchars($gymTitle); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
	<?php echo render_favicon_tags(); ?>
	<link rel="stylesheet" href="css/bootstrap.min.css"/>
	<link rel="stylesheet" href="css/font-awesome.min.css"/>
	<link rel="stylesheet" href="css/ios-glass.css"/>
	<style>
		.ios-auth-card {
			max-width: 500px;
			margin: 20px auto;
			padding: 30px 26px;
			background: linear-gradient(135deg, rgba(30, 41, 59, 0.78) 0%, rgba(15, 23, 42, 0.9) 100%);
			backdrop-filter: blur(36px) saturate(200%);
			-webkit-backdrop-filter: blur(36px) saturate(200%);
			border: 1px solid rgba(255, 255, 255, 0.16);
			border-radius: 28px;
			box-shadow: 0 30px 65px -15px rgba(0, 0, 0, 0.65), inset 0 1px 1px rgba(255, 255, 255, 0.2);
			text-align: center;
			position: relative;
			overflow: hidden;
		}

		/* Segmented Pill Switcher */
		.ios-portal-switcher {
			display: flex;
			background: rgba(15, 23, 42, 0.7);
			border: 1px solid rgba(255, 255, 255, 0.12);
			border-radius: 16px;
			padding: 4px;
			gap: 4px;
			margin: 16px 0 14px 0;
			position: relative;
		}
		.portal-tab-btn {
			flex: 1;
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			padding: 9px 12px;
			font-size: 13px;
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
			gap: 10px;
			padding: 9px 12px;
			border-radius: 12px;
			font-size: 12px;
			line-height: 1.4;
			margin-bottom: 18px;
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

		/* Input with Icon Wrapper */
		.ios-input-icon-wrapper {
			position: relative;
			display: flex;
			align-items: center;
		}
		.input-prefix-icon {
			position: absolute;
			left: 15px;
			font-size: 15px;
			color: #64748b;
			pointer-events: none;
			transition: color 0.2s ease;
		}
		.ios-input-icon-wrapper .ios-input {
			padding-left: 42px !important;
			padding-right: 44px !important;
		}
		.ios-input:focus + .input-prefix-icon,
		.ios-input:focus ~ .input-prefix-icon {
			color: var(--color-primary);
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

		/* Registration Switcher Section */
		.register-prompt-box {
			margin-top: 18px;
			padding-top: 16px;
			border-top: 1px solid rgba(255, 255, 255, 0.08);
			text-align: center;
		}
		.register-pill-group {
			display: flex;
			gap: 8px;
			justify-content: center;
			margin-top: 8px;
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
			transform: translateY(-1px);
		}
		.reg-pill-staff {
			background: rgba(129, 140, 248, 0.12);
			border: 1px solid rgba(129, 140, 248, 0.3);
			color: #a5b4fc;
		}
		.reg-pill-staff:hover {
			background: rgba(129, 140, 248, 0.24);
			color: #fff;
			transform: translateY(-1px);
		}

		@media (max-width: 576px) {
			.ios-auth-card {
				margin: 16px 8px;
				padding: 26px 18px;
				border-radius: 22px;
			}
			.register-pill-group {
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
				
				<?php $publicLogo = function_exists('get_logo_url') ? get_logo_url() : ''; ?>
				<?php if (!empty($publicLogo)): ?>
					<div style="text-align: center; margin-bottom: 16px;">
						<img src="<?php echo htmlspecialchars($publicLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" style="max-height: 54px; max-width: 220px; object-fit: contain; border-radius: 12px; filter: drop-shadow(0 8px 16px rgba(0,0,0,0.4));">
					</div>
				<?php else: ?>
					<div class="ios-auth-icon" id="authPortalIcon" style="transition: all 0.3s ease;">
						<i class="fa fa-user-circle" id="authIconClass"></i>
					</div>
				<?php endif; ?>

				<h2 class="ios-auth-title" id="authCardTitle">Unified Sign In</h2>
				<p class="ios-auth-subtitle" id="authCardSubtitle">Access your member profile or staff management hub</p>

				<!-- Segmented Role Switcher -->
				<div class="ios-portal-switcher" role="tablist">
					<button type="button" class="portal-tab-btn <?php echo $activePortal === 'member' ? 'active' : ''; ?>" id="tabMember" onclick="switchPortal('member')">
						<i class="fa fa-user"></i>
						<span>Member Access</span>
					</button>
					<button type="button" class="portal-tab-btn <?php echo $activePortal === 'staff' ? 'active staff-active' : ''; ?>" id="tabStaff" onclick="switchPortal('staff')">
						<i class="fa fa-shield"></i>
						<span>Staff &amp; Coaches</span>
					</button>
				</div>

				<!-- Contextual Portal Guidance -->
				<div class="portal-info-box <?php echo $activePortal === 'staff' ? 'staff-box' : 'member-box'; ?>" id="portalInfoBanner">
					<i class="fa <?php echo $activePortal === 'staff' ? 'fa-id-badge' : 'fa-info-circle'; ?>" id="portalInfoIcon" style="font-size: 16px; flex-shrink: 0;"></i>
					<span id="portalInfoText">
						<?php if ($activePortal === 'staff'): ?>
							Authorized portal for Trainers, Floor Coaches, Desk Staff, Managers, and Owners.
						<?php else: ?>
							Sign in to access your workout routines, attendance QR check-in, pass, and profile.
						<?php endif; ?>
					</span>
				</div>

				<?php if (str_contains($redirectParam, 'checkin')): ?>
					<div class="ios-badge ios-badge-info" style="display: flex; align-items: center; gap: 12px; margin-bottom: 22px; padding: 14px 16px; border-radius: 16px; background: rgba(16, 185, 129, 0.16); border: 1px solid rgba(16, 185, 129, 0.35); color: #34d399; text-align: left;">
						<i class="fa fa-qrcode" style="font-size: 26px; flex-shrink: 0; color: #10b981;"></i>
						<div>
							<div style="font-weight: 700; font-size: 14px; color: #fff;">Gym Attendance Check-In</div>
							<div style="font-size: 12.5px; opacity: 0.9; margin-top: 2px;">Sign in to immediately log your gym attendance for today!</div>
						</div>
					</div>
				<?php endif; ?>

				<?php if(!empty($successMsg)): ?>
					<div class="ios-badge ios-badge-success" style="display: flex; align-items: center; margin-bottom: 22px; padding: 13px 16px; border-radius: 14px; text-align: left; background: rgba(16, 185, 129, 0.18); border: 1px solid rgba(16, 185, 129, 0.4); color: #34d399;">
						<i class="fa fa-check-circle" style="font-size: 17px; margin-right: 10px; flex-shrink: 0;"></i>
						<span style="font-size: 13.5px;"><?php echo htmlentities($successMsg); ?></span>
					</div>
				<?php endif; ?>

				<?php if(!empty($msg)): ?>
					<div class="ios-badge ios-badge-danger" style="display: flex; align-items: center; margin-bottom: 22px; padding: 13px 16px; border-radius: 14px; text-align: left;">
						<i class="fa fa-exclamation-circle" style="font-size: 17px; margin-right: 10px; flex-shrink: 0;"></i>
						<span style="font-size: 13.5px;"><?php echo htmlentities($msg); ?></span>
					</div>
				<?php endif; ?>

				<form method="post" autocomplete="off" id="loginForm">
					<?php echo csrf_input(); ?>
					<input type="hidden" name="portal" id="portalField" value="<?php echo htmlspecialchars($activePortal, ENT_QUOTES, 'UTF-8'); ?>">
					<?php if (!empty($redirectParam)): ?>
						<input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirectParam, ENT_QUOTES, 'UTF-8'); ?>">
					<?php endif; ?>

					<div class="ios-form-group" style="text-align: left;">
						<label class="ios-label" for="email" id="emailLabel">
							<?php echo $activePortal === 'staff' ? 'Staff Work Email' : 'Member Email Address'; ?>
						</label>
						<div class="ios-input-icon-wrapper">
							<i class="fa fa-envelope input-prefix-icon"></i>
							<input type="email" name="email" id="email" class="ios-input" placeholder="<?php echo $activePortal === 'staff' ? 'staff@domain.com' : 'name@domain.com'; ?>" required autofocus value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
						</div>
					</div>

					<div class="ios-form-group" style="text-align: left;">
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
							<label class="ios-label" for="password" style="margin-bottom: 0;">Password</label>
							<a href="changepassword.php" style="color: var(--color-primary); font-size: 12px; font-weight: 600; text-decoration: none;">Forgot password?</a>
						</div>
						<div class="ios-input-icon-wrapper">
							<i class="fa fa-lock input-prefix-icon"></i>
							<input type="password" name="password" id="password" class="ios-input" placeholder="Enter your account password" required>
							<button type="button" class="pwd-toggle-btn" onclick="togglePasswordVisibility('password', 'pwdToggleIcon')" aria-label="Toggle password view">
								<i class="fa fa-eye" id="pwdToggleIcon"></i>
							</button>
						</div>
					</div>

					<button type="submit" id="submitBtn" name="submit" class="ios-btn ios-btn-primary" style="width: 100%; margin-top: 8px;">
						<i class="fa <?php echo $activePortal === 'staff' ? 'fa-shield' : 'fa-arrow-right'; ?>" id="submitBtnIcon"></i> 
						<span id="submitBtnText"><?php echo $activePortal === 'staff' ? 'Sign In as Staff / Coach' : 'Sign In as Member'; ?></span>
					</button>

					<!-- Common Registration Options -->
					<div class="register-prompt-box">
						<div style="font-size: 13.5px; color: #94a3b8; font-weight: 500;">
							Don't have an account yet? Register below:
						</div>
						<div class="register-pill-group">
							<a href="registration.php?portal=member<?php echo !empty($redirectParam) ? '&redirect=' . urlencode($redirectParam) : ''; ?>" class="reg-pill reg-pill-member">
								<i class="fa fa-user-plus"></i> Join as Member
							</a>
							<a href="registration.php?portal=staff" class="reg-pill reg-pill-staff">
								<i class="fa fa-briefcase"></i> Apply as Staff / Trainer
							</a>
						</div>
					</div>
				</form>
			</div>
		</div>

		<?php include 'include/footer.php'; ?>
	</div>

	<script src="js/vendor/jquery-3.2.1.min.js"></script>
	<script>
		function switchPortal(type) {
			var portalField = document.getElementById('portalField');
			var tabMember = document.getElementById('tabMember');
			var tabStaff = document.getElementById('tabStaff');
			var infoBanner = document.getElementById('portalInfoBanner');
			var infoIcon = document.getElementById('portalInfoIcon');
			var infoText = document.getElementById('portalInfoText');
			var emailLabel = document.getElementById('emailLabel');
			var emailInput = document.getElementById('email');
			var submitIcon = document.getElementById('submitBtnIcon');
			var submitText = document.getElementById('submitBtnText');
			var authIcon = document.getElementById('authIconClass');

			if (type === 'staff') {
				portalField.value = 'staff';
				tabMember.classList.remove('active');
				tabStaff.classList.add('active', 'staff-active');

				infoBanner.className = 'portal-info-box staff-box';
				if (infoIcon) infoIcon.className = 'fa fa-id-badge';
				infoText.textContent = 'Authorized portal for Trainers, Floor Coaches, Desk Staff, Managers, and Owners.';

				emailLabel.textContent = 'Staff Work Email';
				emailInput.placeholder = 'staff@domain.com';

				submitIcon.className = 'fa fa-shield';
				submitText.textContent = 'Sign In as Staff / Coach';
				if (authIcon) authIcon.className = 'fa fa-shield';
			} else {
				portalField.value = 'member';
				tabStaff.classList.remove('active', 'staff-active');
				tabMember.classList.add('active');

				infoBanner.className = 'portal-info-box member-box';
				if (infoIcon) infoIcon.className = 'fa fa-info-circle';
				infoText.textContent = 'Sign in to access your workout routines, attendance QR check-in, pass, and profile.';

				emailLabel.textContent = 'Member Email Address';
				emailInput.placeholder = 'name@domain.com';

				submitIcon.className = 'fa fa-arrow-right';
				submitText.textContent = 'Sign In as Member';
				if (authIcon) authIcon.className = 'fa fa-user-circle';
			}
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
