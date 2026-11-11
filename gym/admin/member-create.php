<?php
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = "Security token mismatch. Please try again.";
    } else {
        $fname = trim($_POST['fname'] ?? '');
        $lname = trim($_POST['lname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $rawPassword = $_POST['password'] ?? 'Welcome@123';
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $status = in_array($_POST['status'] ?? 'active', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if (empty($fname) || empty($email) || empty($mobile)) {
            $err = "First name, email, and mobile are required.";
        } else {
            // Check if email or mobile exists
            $checkStmt = $dbh->prepare("SELECT id FROM tbluser WHERE email = ? OR mobile = ?");
            $checkStmt->execute([$email, $mobile]);
            if ($checkStmt->fetch()) {
                $err = "A member with this email address or mobile number already exists.";
            } else {
                $hashedPassword = password_hash($rawPassword, PASSWORD_DEFAULT);
                $insStmt = $dbh->prepare("INSERT INTO tbluser (fname, lname, email, mobile, password, state, city, address, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insStmt->execute([$fname, $lname, $email, $mobile, $hashedPassword, $state, $city, $address, $status, $notes]);
                $newUserId = $dbh->lastInsertId();

                // Optional immediate membership assignment
                if (!empty($_POST['assign_plan']) && !empty($_POST['plan_id'])) {
                    $planId = intval($_POST['plan_id']);
                    $startDate = trim($_POST['start_date'] ?? date('Y-m-d'));
                    $durationMonths = intval($_POST['duration_months'] ?? 1);
                    $expiryDate = date('Y-m-d', strtotime("+$durationMonths months", strtotime($startDate)));
                    $paidAmount = floatval($_POST['paid_amount'] ?? 0);
                    $paymentMethod = trim($_POST['payment_method'] ?? 'Cash');

                    $pStmt = $dbh->prepare("SELECT Price FROM tbladdpackage WHERE id = ?");
                    $pStmt->execute([$planId]);
                    $planPrice = floatval($pStmt->fetchColumn() ?: 0);

                    $paymentStatus = 'unpaid';
                    if ($paidAmount >= $planPrice && $planPrice > 0) {
                        $paymentStatus = 'paid';
                    } elseif ($paidAmount > 0) {
                        $paymentStatus = 'partial';
                    }

                    // Dual insert
                    $adminId = intval($_SESSION['adminid'] ?? 1);
                    $bStmt = $dbh->prepare("INSERT INTO tblbooking (package_id, userid, payment, paymentType) VALUES (?, ?, ?, ?)");
                    $bStmt->execute([$planId, $newUserId, $paidAmount, $paymentMethod]);
                    $bookingId = $dbh->lastInsertId();

                    $memStmt = $dbh->prepare("INSERT INTO tbl_memberships (user_id, plan_id, booking_id, start_date, expiry_date, status, payment_status, total_amount, paid_amount, payment_method, admin_notes) VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, 'Initial onboarding')");
                    $memStmt->execute([$newUserId, $planId, $bookingId, $startDate, $expiryDate, $paymentStatus, $planPrice, $paidAmount, $paymentMethod]);
                    $newMemId = $dbh->lastInsertId();

                    if ($paidAmount > 0) {
                        $payStmt = $dbh->prepare("INSERT INTO tblpayment (bookingID, membership_id, paymentType, payment, payment_method, recorded_by_admin_id) VALUES (?, ?, ?, ?, ?, ?)");
                        $payStmt->execute([$bookingId, $newMemId, $paymentMethod, $paidAmount, $paymentMethod, $adminId]);
                    }
                }

                header("location:member-profile.php?id=$newUserId&msg=" . urlencode("Member registered successfully!"));
                exit();
            }
        }
    }
}

// Fetch active plans for selector
$plans = $dbh->query("SELECT id, titlename, Price, PackageDuratiobn FROM tbladdpackage WHERE is_active = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Add New Member — Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php echo render_favicon_tags(); ?>
  <link rel="stylesheet" type="text/css" href="css/main.css">
  <link rel="stylesheet" type="text/css" href="css/ios-admin-glass.css">
  <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
</head>
<body class="app sidebar-mini rtl">
  <?php include 'include/header.php'; ?>
  <div class="app-sidebar__overlay" data-toggle="sidebar"></div>
  <?php include 'include/sidebar.php'; ?>

  <main class="app-content">
    <div class="app-title">
      <div>
        <h1><i class="fa fa-user-plus" style="color: var(--color-primary); margin-right: 8px;"></i> Add New Member</h1>
        <p>Register a new club member and optionally assign their initial membership package</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="members.php">Members</a></li>
        <li class="breadcrumb-item active">New Member</li>
      </ul>
    </div>

    <?php if ($err): ?>
      <div class="ios-badge ios-badge-danger" style="display: flex; margin-bottom: 24px; padding: 14px 20px; border-radius: 16px; font-size: 14px;">
        <i class="fa fa-exclamation-triangle" style="font-size: 18px; margin-right: 8px;"></i>
        <span><?php echo htmlentities($err); ?></span>
      </div>
    <?php endif; ?>

    <form method="post">
      <?php echo csrf_input(); ?>
      <div class="row">
        <!-- Personal Information Column -->
        <div class="col-lg-7">
          <div class="tile">
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">
              <i class="fa fa-id-card-o" style="color: var(--color-primary); margin-right: 6px;"></i> Personal Information
            </h3>

            <div class="row">
              <div class="col-md-6 form-group">
                <label style="font-weight: 600; font-size: 13px;">First Name *</label>
                <input type="text" name="fname" class="form-control" required placeholder="e.g. Rahul" value="<?php echo htmlspecialchars($_POST['fname'] ?? ''); ?>">
              </div>
              <div class="col-md-6 form-group">
                <label style="font-weight: 600; font-size: 13px;">Last Name</label>
                <input type="text" name="lname" class="form-control" placeholder="e.g. Sharma" value="<?php echo htmlspecialchars($_POST['lname'] ?? ''); ?>">
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 form-group">
                <label style="font-weight: 600; font-size: 13px;">Email Address *</label>
                <input type="email" name="email" class="form-control" required placeholder="name@domain.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
              </div>
              <div class="col-md-6 form-group">
                <label style="font-weight: 600; font-size: 13px;">Mobile Number *</label>
                <input type="tel" name="mobile" class="form-control" required placeholder="+91 98765 43210" value="<?php echo htmlspecialchars($_POST['mobile'] ?? ''); ?>">
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 form-group">
                <label style="font-weight: 600; font-size: 13px;">Portal Password (Defaults to <code>Welcome@123</code>)</label>
                <input type="text" name="password" class="form-control" placeholder="Welcome@123" value="<?php echo htmlspecialchars($_POST['password'] ?? 'Welcome@123'); ?>">
              </div>
              <div class="col-md-6 form-group">
                <label style="font-weight: 600; font-size: 13px;">Initial Account Status</label>
                <select name="status" class="form-control">
                  <option value="active">Active (Permit Portal Access)</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 form-group">
                <label style="font-weight: 600; font-size: 13px;">City</label>
                <input type="text" name="city" class="form-control" placeholder="e.g. Mumbai" value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
              </div>
              <div class="col-md-6 form-group">
                <label style="font-weight: 600; font-size: 13px;">State</label>
                <input type="text" name="state" class="form-control" placeholder="e.g. Maharashtra" value="<?php echo htmlspecialchars($_POST['state'] ?? ''); ?>">
              </div>
            </div>

            <div class="form-group">
              <label style="font-weight: 600; font-size: 13px;">Residential Address</label>
              <textarea name="address" class="form-control" rows="2" placeholder="Full street address..."><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
              <label style="font-weight: 600; font-size: 13px;">Internal Admin Notes</label>
              <textarea name="notes" class="form-control" rows="2" placeholder="Medical background, fitness objectives, emergency contacts..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
            </div>
          </div>
        </div>

        <!-- Initial Membership Assignment Column -->
        <div class="col-lg-5">
          <div class="tile" style="border: 1px solid rgba(56, 189, 248, 0.25);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
              <h3 style="font-size: 18px; font-weight: 700; margin: 0;">
                <i class="fa fa-ticket" style="color: var(--color-accent); margin-right: 6px;"></i> Initial Membership
              </h3>
              <div class="form-check" style="padding-left: 0;">
                <label style="font-size: 13px; font-weight: 600; cursor: pointer;">
                  <input type="checkbox" name="assign_plan" value="1" id="assignPlanToggle" <?php echo !empty($_POST['assign_plan']) ? 'checked' : ''; ?>>
                  Assign Plan Now
                </label>
              </div>
            </div>

            <div id="planFields" style="display: <?php echo !empty($_POST['assign_plan']) ? 'block' : 'none'; ?>;">
              <div class="form-group">
                <label style="font-weight: 600; font-size: 13px;">Select Package</label>
                <select name="plan_id" id="planSelect" class="form-control">
                  <option value="" disabled selected>-- Choose plan --</option>
                  <?php foreach ($plans as $p): ?>
                    <option value="<?php echo $p['id']; ?>" data-price="<?php echo $p['Price']; ?>">
                      <?php echo htmlspecialchars($p['titlename']); ?> &bull; <?php echo format_currency($p['Price']); ?> (<?php echo htmlspecialchars($p['PackageDuratiobn'] ?? '1 Month'); ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="row">
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13px;">Start Date</label>
                  <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13px;">Duration</label>
                  <select name="duration_months" class="form-control">
                    <option value="1">1 Month</option>
                    <option value="3">3 Months</option>
                    <option value="6">6 Months</option>
                    <option value="12">12 Months</option>
                  </select>
                </div>
              </div>

              <div class="row">
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13px;">Initial Payment (<?php echo get_currency_symbol(); ?>)</label>
                  <input type="number" step="0.01" name="paid_amount" id="paidAmount" class="form-control" placeholder="0.00">
                </div>
                <div class="col-md-6 form-group">
                  <label style="font-weight: 600; font-size: 13px;">Payment Method</label>
                  <select name="payment_method" class="form-control">
                    <option value="Cash">Cash</option>
                    <option value="UPI / QR">UPI / QR Code</option>
                    <option value="Credit / Debit Card">Credit / Debit Card</option>
                    <option value="Net Banking">Net Banking</option>
                  </select>
                </div>
              </div>
            </div>

            <p id="planHint" style="color: var(--text-muted); font-size: 13px; margin: 0; display: <?php echo !empty($_POST['assign_plan']) ? 'none' : 'block'; ?>;">
              Check "Assign Plan Now" above if this member is subscribing to a membership tier today. Otherwise you can assign it later from their profile.
            </p>

            <hr style="border-color: var(--glass-border); margin: 24px 0;">

            <button type="submit" class="ios-btn ios-btn-accent btn-block" style="padding: 12px; font-size: 15px;">
              <i class="fa fa-user-plus"></i> Complete Registration
            </button>
            <a href="members.php" class="btn btn-outline-light btn-block" style="border-radius: 14px; padding: 10px; margin-top: 10px;">
              Cancel & Return
            </a>
          </div>
        </div>
      </div>
    </form>
  </main>

  <script src="js/jquery-3.2.1.min.js"></script>
  <script src="js/popper.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/main.js"></script>
  <script>
    $('#assignPlanToggle').on('change', function() {
      if ($(this).is(':checked')) {
        $('#planFields').slideDown(200);
        $('#planHint').slideUp(200);
      } else {
        $('#planFields').slideUp(200);
        $('#planHint').slideDown(200);
      }
    });

    $('#planSelect').on('change', function() {
      var price = $(this).find(':selected').data('price');
      if (price !== undefined) {
        $('#paidAmount').val(price);
      }
    });
  </script>
</body>
</html>
