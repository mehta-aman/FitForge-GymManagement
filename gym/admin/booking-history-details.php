<?php
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$bookingId = intval($_GET['bookingid'] ?? 0);
if ($bookingId <= 0) {
    header('location:booking-history.php');
    exit();
}

$msg = '';
$err = '';

// Handle Payment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_payment') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = "Security token mismatch. Please try again.";
    } else {
        $payType = trim($_POST['payment_type'] ?? 'Cash');
        $amount = floatval($_POST['payment_amount'] ?? 0);
        $txnRef = trim($_POST['transaction_ref'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($amount <= 0) {
            $err = "Please enter a valid payment amount greater than zero.";
        } else {
            try {
                $dbh->beginTransaction();

                // 1. Insert into tblpayment with payment_method, transaction_ref, notes
                $payStmt = $dbh->prepare("INSERT INTO tblpayment (bookingID, paymentType, payment, payment_method, transaction_ref, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $payStmt->execute([$bookingId, $payType, $amount, $payType, $txnRef, $notes]);
                $newPayId = $dbh->lastInsertId();

                // 2. Update tblbooking paymentType
                $updBooking = $dbh->prepare("UPDATE tblbooking SET paymentType = ? WHERE id = ?");
                $updBooking->execute([$payType, $bookingId]);

                // 3. Sync with tbl_memberships
                $memStmt = $dbh->prepare("SELECT id, total_amount, paid_amount FROM tbl_memberships WHERE booking_id = ?");
                $memStmt->execute([$bookingId]);
                $mem = $memStmt->fetch(PDO::FETCH_ASSOC);

                if ($mem) {
                    $newPaid = floatval($mem['paid_amount']) + $amount;
                    $totAmt = floatval($mem['total_amount']);
                    $newStatus = ($newPaid >= $totAmt) ? 'paid' : 'partial';

                    $updMem = $dbh->prepare("UPDATE tbl_memberships SET paid_amount = ?, payment_status = ? WHERE id = ?");
                    $updMem->execute([$newPaid, $newStatus, $mem['id']]);
                }

                $dbh->commit();
                $msg = "Payment of " . format_currency($amount) . " successfully recorded.";
            } catch (Exception $e) {
                $dbh->rollBack();
                $err = "Transaction Error: " . $e->getMessage();
            }
        }
    }
}

// Fetch booking & member info
$stmt = $dbh->prepare("SELECT t1.id as bookingid, t1.booking_date as bookingdate, t1.paymentType,
                       t2.id as package_id, t2.titlename as title, t2.PackageDuratiobn, t2.Price as price, t2.Description,
                       t3.id as userid, t3.fname, t3.lname, t3.email, t3.mobile, t3.address, t3.city, t3.state,
                       t4.category_name, t5.PackageName
                       FROM tblbooking t1
                       JOIN tbladdpackage t2 ON t1.package_id = t2.id
                       JOIN tbluser t3 ON t1.userid = t3.id
                       LEFT JOIN tblcategory t4 ON t2.category = t4.id
                       LEFT JOIN tblpackage t5 ON t2.PackageType = t5.id
                       WHERE t1.id = ?");
$stmt->execute([$bookingId]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header('location:booking-history.php');
    exit();
}

// Fetch all payment installments
$payStmt = $dbh->prepare("SELECT * FROM tblpayment WHERE bookingID = ? ORDER BY id ASC");
$payStmt->execute([$bookingId]);
$payments = $payStmt->fetchAll(PDO::FETCH_ASSOC);

$totalPaid = 0;
foreach ($payments as $p) {
    $totalPaid += floatval($p['payment']);
}
$packagePrice = floatval($booking['price']);
$balance = max(0, $packagePrice - $totalPaid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Booking #<?php echo $booking['bookingid']; ?> Ledger — Admin</title>
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
        <h1>
          <i class="fa fa-file-text-o" style="color: var(--color-primary); margin-right: 8px;"></i>
          Booking & Payment Ledger #<?php echo $booking['bookingid']; ?>
        </h1>
        <p>Detailed receipt breakdown, payment installment history, and collections</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="booking-history.php">Bookings</a></li>
        <li class="breadcrumb-item active">#<?php echo $booking['bookingid']; ?></li>
      </ul>
    </div>

    <?php if ($msg): ?>
      <div class="ios-badge ios-badge-success" style="display: flex; margin-bottom: 24px; padding: 14px 20px; border-radius: 16px; font-size: 14px;">
        <i class="fa fa-check-circle" style="font-size: 18px; margin-right: 8px;"></i>
        <span><?php echo htmlentities($msg); ?></span>
      </div>
    <?php endif; ?>

    <?php if ($err): ?>
      <div class="ios-badge ios-badge-danger" style="display: flex; margin-bottom: 24px; padding: 14px 20px; border-radius: 16px; font-size: 14px;">
        <i class="fa fa-exclamation-triangle" style="font-size: 18px; margin-right: 8px;"></i>
        <span><?php echo htmlentities($err); ?></span>
      </div>
    <?php endif; ?>

    <div class="row">
      <!-- Member & Package Details -->
      <div class="col-lg-5">
        <!-- Member Summary -->
        <div class="tile">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="font-size: 17px; font-weight: 700; margin: 0;">
              <i class="fa fa-user" style="color: var(--color-primary); margin-right: 6px;"></i> Member Details
            </h3>
            <a href="member-profile.php?id=<?php echo $booking['userid']; ?>" class="btn btn-sm btn-outline-light" style="border-radius: 10px; font-size: 12px; padding: 3px 10px;">
              View Profile
            </a>
          </div>
          <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 14px; padding: 16px; font-size: 13.5px;">
            <div style="margin-bottom: 8px; display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Full Name:</span>
              <strong style="color: #fff;"><?php echo htmlspecialchars($booking['fname'] . ' ' . $booking['lname']); ?></strong>
            </div>
            <div style="margin-bottom: 8px; display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Email:</span>
              <span><?php echo htmlspecialchars($booking['email']); ?></span>
            </div>
            <div style="margin-bottom: 8px; display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Phone:</span>
              <span><?php echo htmlspecialchars($booking['mobile'] ?? 'N/A'); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Location:</span>
              <span><?php echo htmlspecialchars(($booking['city'] ? $booking['city'] . ', ' : '') . ($booking['state'] ?? '')); ?></span>
            </div>
          </div>
        </div>

        <!-- Package Details -->
        <div class="tile">
          <h3 style="font-size: 17px; font-weight: 700; margin-bottom: 16px;">
            <i class="fa fa-cube" style="color: var(--color-accent); margin-right: 6px;"></i> Package Specifications
          </h3>
          <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 14px; padding: 16px; font-size: 13.5px;">
            <div style="margin-bottom: 8px; display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Title:</span>
              <strong style="color: #fff;"><?php echo htmlspecialchars($booking['title']); ?></strong>
            </div>
            <div style="margin-bottom: 8px; display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Category:</span>
              <span><?php echo htmlspecialchars($booking['category_name'] ?? 'General'); ?></span>
            </div>
            <div style="margin-bottom: 8px; display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Duration:</span>
              <span><?php echo htmlspecialchars($booking['PackageDuratiobn'] ?? '1 Month'); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Booking Date:</span>
              <span><?php echo date('d M Y, h:i A', strtotime($booking['bookingdate'])); ?></span>
            </div>
          </div>
        </div>

        <!-- Record Payment Form (if balance remains) -->
        <?php if ($balance > 0): ?>
          <div class="tile" style="border: 1px solid rgba(56, 189, 248, 0.3);">
            <h3 style="font-size: 17px; font-weight: 700; margin-bottom: 16px;">
              <i class="fa fa-plus-circle" style="color: #34d399; margin-right: 6px;"></i> Record Payment Installment
            </h3>
            <form method="post">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="action" value="record_payment">
              
              <div class="form-group">
                <label style="font-weight: 600; font-size: 13px;">Payment Amount (<?php echo get_currency_symbol(); ?>) *</label>
                <div class="input-group">
                  <input type="number" step="0.01" max="<?php echo $balance; ?>" name="payment_amount" id="payAmountInput" class="form-control" value="<?php echo $balance; ?>" required style="border-radius: 12px 0 0 12px;">
                  <div class="input-group-append">
                    <button type="button" class="btn btn-outline-light" onclick="$('#payAmountInput').val('<?php echo $balance; ?>')" style="border-radius: 0 12px 12px 0; font-size: 12px;">
                      Full Balance
                    </button>
                  </div>
                </div>
                <small class="form-text text-muted">Outstanding balance: <?php echo format_currency($balance); ?></small>
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13px;">Payment Method</label>
                <select name="payment_type" class="form-control" style="border-radius: 12px;">
                  <option value="Cash">Cash</option>
                  <option value="UPI / QR">UPI / QR Code</option>
                  <option value="Credit / Debit Card">Credit / Debit Card</option>
                  <option value="Net Banking">Net Banking</option>
                  <option value="Cheque">Cheque</option>
                </select>
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13px;">Transaction Reference / UTR #</label>
                <input type="text" name="transaction_ref" class="form-control" placeholder="e.g. UPI-987123984, POS-0082" style="border-radius: 12px;">
              </div>

              <div class="form-group">
                <label style="font-weight: 600; font-size: 13px;">Payment Notes</label>
                <input type="text" name="notes" class="form-control" placeholder="Optional installment note..." style="border-radius: 12px;">
              </div>

              <button type="submit" class="btn btn-primary btn-block" style="padding: 11px; border-radius: 12px;">
                <i class="fa fa-check"></i> Submit Payment
              </button>
            </form>
          </div>
        <?php endif; ?>
      </div>

      <!-- Financial Ledger & Payment History Column -->
      <div class="col-lg-7">
        <!-- Balance Overview Cards -->
        <div class="tile">
          <div class="row" style="row-gap: 12px;">
            <div class="col-sm-4">
              <div style="background: rgba(0,0,0,0.2); padding: 14px; border-radius: 14px; border: 1px solid var(--glass-border); text-align: center;">
                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Total Plan Fee</div>
                <div style="font-size: 22px; font-weight: 800; color: #fff; margin-top: 4px;">
                  <?php echo format_currency($packagePrice); ?>
                </div>
              </div>
            </div>
            <div class="col-sm-4">
              <div style="background: rgba(0,0,0,0.2); padding: 14px; border-radius: 14px; border: 1px solid var(--glass-border); text-align: center;">
                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Total Collected</div>
                <div style="font-size: 22px; font-weight: 800; color: #34d399; margin-top: 4px;">
                  <?php echo format_currency($totalPaid); ?>
                </div>
              </div>
            </div>
            <div class="col-sm-4">
              <div style="background: rgba(0,0,0,0.2); padding: 14px; border-radius: 14px; border: 1px solid var(--glass-border); text-align: center;">
                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Balance Due</div>
                <div style="font-size: 22px; font-weight: 800; color: <?php echo ($balance > 0) ? '#f87171' : '#34d399'; ?>; margin-top: 4px;">
                  <?php echo format_currency($balance); ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Installments Ledger Table -->
        <div class="tile">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="font-size: 18px; font-weight: 700; margin: 0;">
              <i class="fa fa-history" style="color: var(--color-primary); margin-right: 6px;"></i> Payment Installment History
            </h3>
            <a href="receipt.php?booking=<?php echo $bookingId; ?>" target="_blank" class="btn btn-outline-light btn-sm" style="border-radius: 10px; font-size: 12.5px;">
              <i class="fa fa-print"></i> Official Full Receipt
            </a>
          </div>

          <div class="table-responsive">
            <table class="table table-hover table-bordered" style="margin: 0;">
              <thead>
                <tr>
                  <th style="width: 40px;">#</th>
                  <th>Receipt ID</th>
                  <th>Method</th>
                  <th>Amount</th>
                  <th>Date &amp; Time</th>
                  <th style="text-align: right; width: 90px;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($payments)): $idx = 1; foreach ($payments as $p): ?>
                <tr>
                  <td style="color: var(--text-muted);"><?php echo $idx++; ?></td>
                  <td style="font-weight: 600; color: var(--color-primary);">#REC-<?php echo $p['id']; ?></td>
                  <td>
                    <span class="ios-badge ios-badge-info"><?php echo htmlspecialchars($p['paymentType'] ?? 'Cash'); ?></span>
                    <?php if (!empty($p['transaction_ref'])): ?>
                      <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Ref: <?php echo htmlspecialchars($p['transaction_ref']); ?></div>
                    <?php endif; ?>
                  </td>
                  <td style="font-weight: 700; color: #34d399;"><?php echo format_currency($p['payment']); ?></td>
                  <td style="color: var(--text-muted); font-size: 13px;"><?php echo date('d M Y, h:i A', strtotime($p['payment_date'])); ?></td>
                  <td style="text-align: right;">
                    <a href="receipt.php?booking=<?php echo $bookingId; ?>&pay=<?php echo $p['id']; ?>" target="_blank" class="btn btn-sm btn-outline-info" style="border-radius: 8px; padding: 4px 8px; font-size: 11.5px;">
                      <i class="fa fa-print"></i> Receipt
                    </a>
                  </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">No payments recorded yet for this booking.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="js/jquery-3.2.1.min.js"></script>
  <script src="js/popper.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/main.js"></script>
</body>
</html>
