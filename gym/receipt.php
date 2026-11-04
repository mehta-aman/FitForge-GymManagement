<?php
require_once __DIR__ . '/include/config.php';

$bookingId = intval($_GET['booking'] ?? ($_GET['id'] ?? 0));
$payId = intval($_GET['pay'] ?? 0);

if ($bookingId <= 0) {
    header('location:index.php');
    exit();
}

// Fetch booking & member info
$stmt = $dbh->prepare("SELECT t1.id as bookingid, t1.booking_date as bookingdate, t1.paymentType,
                       t2.id as package_id, t2.titlename as title, t2.PackageDuratiobn, t2.Price as price, t2.registration_fee, t2.discount, t2.Description,
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
    exit("Booking record not found.");
}

// Authorization guard: Admin or the owning Member
$isAdmin = !empty($_SESSION['adminid']);
$isOwner = !empty($_SESSION['uid']) && (intval($_SESSION['uid']) === intval($booking['userid']));

if (!$isAdmin && !$isOwner) {
    http_response_code(403);
    exit("Access Denied: You do not have permission to inspect this receipt.");
}

// Fetch membership record
$mem = $dbh->prepare("SELECT * FROM tbl_memberships WHERE booking_id = ?");
$mem->execute([$bookingId]);
$membership = $mem->fetch(PDO::FETCH_ASSOC);

// Fetch all payments for this booking
$payStmt = $dbh->prepare("SELECT * FROM tblpayment WHERE bookingID = ? ORDER BY id ASC");
$payStmt->execute([$bookingId]);
$allPayments = $payStmt->fetchAll(PDO::FETCH_ASSOC);

$totalCollected = 0;
$activePayment = null;
foreach ($allPayments as $p) {
    $totalCollected += floatval($p['payment']);
    if ($payId > 0 && $p['id'] == $payId) {
        $activePayment = $p;
    }
}

// If specific installment requested, show its amount; else show total collected
$amountForThisReceipt = $activePayment ? floatval($activePayment['payment']) : $totalCollected;
$paymentMethodUsed = $activePayment ? ($activePayment['payment_method'] ?? $activePayment['paymentType']) : ($booking['paymentType'] ?? 'Cash');
$txnReference = $activePayment ? ($activePayment['transaction_ref'] ?? '') : '';
$receiptDate = $activePayment ? $activePayment['payment_date'] : ($booking['bookingdate'] ?? date('Y-m-d H:i:s'));
$receiptNo = 'REC-' . str_pad($bookingId, 5, '0', STR_PAD_LEFT) . ($activePayment ? ('-' . $activePayment['id']) : '');

$planPrice = floatval($booking['price']);
$regFee = floatval($booking['registration_fee'] ?? 0);
$discount = floatval($booking['discount'] ?? 0);
$netTotal = ($planPrice + $regFee) - $discount;
$balanceDue = max(0, $netTotal - $totalCollected);

$gymName = get_setting('gym_name', 'Golden Eagle');
$gymTagline = get_setting('gym_tagline', 'FITNESS CLUB');
$gymLogo = get_logo_url();
$gymPhone = get_setting('contact_phone', '+91 9983665673');
$gymEmail = get_setting('contact_email', 'contact@gymclub.com');
$gymAddress = get_setting('contact_address', 'Mangalwad, Rajasthan, India');
$receiptNotes = get_setting('receipt_notes', 'Thank you for training with us! Please present this receipt upon entry.');
$refundPolicy = get_setting('refund_policy', 'All fees and membership passes are non-refundable once activated.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Official Receipt — <?php echo htmlspecialchars($receiptNo); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php echo render_favicon_tags(); ?>
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
  <style>
    * { box-sizing: border-box; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      background: #0f172a;
      color: #1e293b;
      margin: 0;
      padding: 40px 16px;
    }
    .receipt-container {
      max-width: 780px;
      margin: 0 auto;
      background: #ffffff;
      border-radius: 20px;
      padding: 48px 44px;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
    }
    .receipt-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      border-bottom: 2px solid #f1f5f9;
      padding-bottom: 28px;
      margin-bottom: 30px;
    }
    .brand-section {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .brand-logo {
      max-height: 52px;
      max-width: 160px;
      object-fit: contain;
    }
    .brand-title {
      font-size: 24px;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: -0.03em;
      margin: 0;
    }
    .brand-tagline {
      font-size: 11px;
      font-weight: 700;
      color: #0284c7;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin-top: 2px;
    }
    .receipt-meta {
      text-align: right;
    }
    .receipt-badge {
      display: inline-block;
      background: #e0f2fe;
      color: #0369a1;
      padding: 6px 14px;
      border-radius: 30px;
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 8px;
    }
    .receipt-number {
      font-size: 18px;
      font-weight: 800;
      color: #0f172a;
      margin: 0;
    }
    .receipt-date {
      font-size: 13px;
      color: #64748b;
      margin-top: 4px;
    }
    .parties-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 30px;
      margin-bottom: 32px;
    }
    .party-title {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #94a3b8;
      margin-bottom: 8px;
    }
    .party-name {
      font-size: 16px;
      font-weight: 700;
      color: #0f172a;
      margin-bottom: 4px;
    }
    .party-detail {
      font-size: 13.5px;
      color: #475569;
      line-height: 1.5;
    }
    .receipt-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 24px;
    }
    .receipt-table th {
      background: #f8fafc;
      text-align: left;
      padding: 12px 16px;
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #475569;
      border-top: 1px solid #e2e8f0;
      border-bottom: 1px solid #e2e8f0;
    }
    .receipt-table td {
      padding: 14px 16px;
      font-size: 14px;
      color: #1e293b;
      border-bottom: 1px solid #f1f5f9;
    }
    .totals-area {
      display: flex;
      justify-content: flex-end;
      margin-bottom: 36px;
    }
    .totals-box {
      width: 320px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 14px;
      padding: 18px 20px;
    }
    .totals-row {
      display: flex;
      justify-content: space-between;
      font-size: 13.5px;
      margin-bottom: 8px;
      color: #475569;
    }
    .totals-row.grand {
      font-size: 17px;
      font-weight: 800;
      color: #0f172a;
      border-top: 1px solid #cbd5e1;
      padding-top: 10px;
      margin-top: 10px;
    }
    .totals-row.paid {
      color: #16a34a;
      font-weight: 700;
    }
    .totals-row.balance {
      color: #dc2626;
      font-weight: 700;
    }
    .receipt-footer {
      border-top: 1px solid #e2e8f0;
      padding-top: 24px;
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      gap: 20px;
    }
    .receipt-terms {
      font-size: 11.5px;
      color: #64748b;
      line-height: 1.5;
      max-width: 440px;
    }
    .receipt-signature {
      text-align: center;
      width: 180px;
    }
    .signature-line {
      border-top: 1px solid #94a3b8;
      margin-top: 50px;
      padding-top: 6px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #475569;
    }
    .action-bar {
      max-width: 780px;
      margin: 0 auto 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .btn-action {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      border-radius: 12px;
      font-weight: 600;
      font-size: 14px;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.2s ease;
    }
    .btn-print {
      background: #0284c7;
      color: #ffffff;
      border: none;
    }
    .btn-print:hover { background: #0369a1; }
    .btn-back {
      background: rgba(255, 255, 255, 0.1);
      color: #ffffff;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .btn-back:hover { background: rgba(255, 255, 255, 0.2); }

    @media print {
      body {
        background: #ffffff !important;
        padding: 0 !important;
      }
      .action-bar { display: none !important; }
      .receipt-container {
        box-shadow: none !important;
        padding: 20px 0 !important;
        max-width: 100% !important;
      }
    }
  </style>
</head>
<body>

  <!-- Screen Actions Bar (Hidden on Print) -->
  <div class="action-bar">
    <?php if ($isAdmin): ?>
      <a href="admin/booking-history-details.php?bookingid=<?php echo $bookingId; ?>" class="btn-action btn-back">
        <i class="fa fa-arrow-left"></i> Return to Admin
      </a>
    <?php else: ?>
      <a href="booking-details.php?bookingid=<?php echo $bookingId; ?>" class="btn-action btn-back">
        <i class="fa fa-arrow-left"></i> Return to Booking
      </a>
    <?php endif; ?>
    <button type="button" class="btn-action btn-print" onclick="window.print()">
      <i class="fa fa-print"></i> Print Official Receipt
    </button>
  </div>

  <div class="receipt-container">
    <!-- Receipt Header -->
    <div class="receipt-header">
      <div class="brand-section">
        <?php if (!empty($gymLogo)): ?>
          <img src="<?php echo htmlspecialchars($gymLogo); ?>" alt="Logo" class="brand-logo">
        <?php endif; ?>
        <div>
          <h1 class="brand-title"><?php echo htmlspecialchars($gymName); ?></h1>
          <div class="brand-tagline"><?php echo htmlspecialchars($gymTagline); ?></div>
        </div>
      </div>
      <div class="receipt-meta">
        <span class="receipt-badge">Payment Receipt</span>
        <div class="receipt-number"><?php echo htmlspecialchars($receiptNo); ?></div>
        <div class="receipt-date"><i class="fa fa-calendar"></i> <?php echo date('d M Y, h:i A', strtotime($receiptDate)); ?></div>
      </div>
    </div>

    <!-- Parties Grid -->
    <div class="parties-grid">
      <div>
        <div class="party-title">Issued By</div>
        <div class="party-name"><?php echo htmlspecialchars($gymName); ?></div>
        <div class="party-detail"><?php echo htmlspecialchars($gymAddress); ?></div>
        <div class="party-detail">Phone: <?php echo htmlspecialchars($gymPhone); ?></div>
        <div class="party-detail">Email: <?php echo htmlspecialchars($gymEmail); ?></div>
      </div>
      <div>
        <div class="party-title">Billed To (Member)</div>
        <div class="party-name"><?php echo htmlspecialchars($booking['fname'] . ' ' . $booking['lname']); ?></div>
        <div class="party-detail">Member ID: #MEM-<?php echo str_pad($booking['userid'], 4, '0', STR_PAD_LEFT); ?></div>
        <div class="party-detail">Phone: <?php echo htmlspecialchars($booking['mobile']); ?></div>
        <div class="party-detail">Email: <?php echo htmlspecialchars($booking['email']); ?></div>
        <?php if (!empty($booking['city'])): ?>
          <div class="party-detail"><?php echo htmlspecialchars($booking['city'] . ', ' . $booking['state']); ?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Itemized Table -->
    <table class="receipt-table">
      <thead>
        <tr>
          <th>Membership Description</th>
          <th>Category</th>
          <th>Duration</th>
          <th style="text-align: right;">Rate</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <strong><?php echo htmlspecialchars($booking['title']); ?></strong>
            <div style="font-size: 12px; color: #64748b; margin-top: 3px;">
              Booking Reference: #BK-<?php echo str_pad($bookingId, 5, '0', STR_PAD_LEFT); ?>
              <?php if ($membership): ?>
                &bull; Active: <?php echo date('d M Y', strtotime($membership['start_date'])); ?> &rarr; <?php echo date('d M Y', strtotime($membership['expiry_date'])); ?>
              <?php endif; ?>
            </div>
          </td>
          <td><?php echo htmlspecialchars($booking['category_name'] ?? 'General'); ?></td>
          <td><?php echo htmlspecialchars($booking['PackageDuratiobn'] ?? '1 Month'); ?></td>
          <td style="text-align: right; font-weight: 700;"><?php echo format_currency($planPrice); ?></td>
        </tr>
        <?php if ($regFee > 0): ?>
          <tr>
            <td>Registration / Onboarding Fee</td>
            <td>One-Time</td>
            <td>—</td>
            <td style="text-align: right; font-weight: 700;"><?php echo format_currency($regFee); ?></td>
          </tr>
        <?php endif; ?>
        <?php if ($discount > 0): ?>
          <tr>
            <td>Promotional Discount</td>
            <td>Discount</td>
            <td>—</td>
            <td style="text-align: right; font-weight: 700; color: #16a34a;">-<?php echo format_currency($discount); ?></td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- Totals Area -->
    <div class="totals-area">
      <div class="totals-box">
        <div class="totals-row">
          <span>Subtotal:</span>
          <span><?php echo format_currency($planPrice); ?></span>
        </div>
        <?php if ($regFee > 0): ?>
          <div class="totals-row">
            <span>Registration Fee:</span>
            <span>+<?php echo format_currency($regFee); ?></span>
          </div>
        <?php endif; ?>
        <?php if ($discount > 0): ?>
          <div class="totals-row">
            <span>Discount:</span>
            <span>-<?php echo format_currency($discount); ?></span>
          </div>
        <?php endif; ?>
        <div class="totals-row grand">
          <span>Total Net Due:</span>
          <span><?php echo format_currency($netTotal); ?></span>
        </div>
        <div class="totals-row paid" style="margin-top: 6px;">
          <span>Amount Paid (This Receipt):</span>
          <span><?php echo format_currency($amountForThisReceipt); ?></span>
        </div>
        <div class="totals-row">
          <span>Payment Instrument:</span>
          <span style="font-weight: 600;"><?php echo htmlspecialchars($paymentMethodUsed); ?></span>
        </div>
        <?php if (!empty($txnReference)): ?>
          <div class="totals-row">
            <span>UTR / Reference #:</span>
            <span style="font-family: monospace;"><?php echo htmlspecialchars($txnReference); ?></span>
          </div>
        <?php endif; ?>
        <div class="totals-row balance" style="border-top: 1px dashed #cbd5e1; padding-top: 6px; margin-top: 6px;">
          <span>Remaining Balance:</span>
          <span><?php echo format_currency($balanceDue); ?></span>
        </div>
      </div>
    </div>

    <!-- Receipt Footer & Stamp -->
    <div class="receipt-footer">
      <div class="receipt-terms">
        <div style="font-weight: 700; color: #334155; margin-bottom: 4px;"><?php echo htmlspecialchars($receiptNotes); ?></div>
        <div><?php echo htmlspecialchars($refundPolicy); ?></div>
        <div style="margin-top: 8px; font-size: 10px; color: #94a3b8;">
          Generated electronically by FitForge System &bull; Timestamp: <?php echo date('Y-m-d H:i:s'); ?>
        </div>
      </div>
      <div class="receipt-signature">
        <div class="signature-line">Authorized Signatory</div>
      </div>
    </div>
  </div>

</body>
</html>
