<?php
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$msg = '';
$gymName = function_exists('get_setting') ? get_setting('gym_name', 'Golden Eagle Gym') : 'Golden Eagle Gym';
$currencySymbol = function_exists('get_setting') ? get_setting('currency_symbol', '₹') : '₹';

// 1. Fetch Expiring Soon (3 days)
$expiring3Days = $dbh->query("
    SELECT m.*, u.fname, u.lname, u.mobile, p.titlename as plan_name,
    DATEDIFF(m.expiry_date, CURDATE()) as days_left
    FROM tbl_memberships m
    JOIN tbluser u ON m.user_id = u.id
    LEFT JOIN tbladdpackage p ON m.plan_id = p.id
    WHERE m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ORDER BY m.expiry_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch Outstanding Dues
$duesAccounts = $dbh->query("
    SELECT b.id as bookingid, u.fname, u.lname, u.mobile, p.titlename as plan_name,
    (CAST(p.Price AS DECIMAL(10,2)) + CAST(COALESCE(p.registration_fee,0) AS DECIMAL(10,2)) - CAST(COALESCE(p.discount,0) AS DECIMAL(10,2))) - (SELECT COALESCE(SUM(pay.payment), 0) FROM tblpayment pay WHERE pay.bookingID = b.id) as balance_due
    FROM tblbooking b
    JOIN tbluser u ON b.userid = u.id
    JOIN tbladdpackage p ON b.package_id = p.id
    HAVING balance_due > 0
    ORDER BY balance_due DESC
")->fetchAll(PDO::FETCH_ASSOC);

// 3. Save Webhook Settings
if (isset($_POST['save_gateway'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        update_setting('sms_gateway_url', trim($_POST['sms_gateway_url'] ?? ''), $dbh);
        update_setting('sms_api_key', trim($_POST['sms_api_key'] ?? ''), $dbh);
        update_setting('whatsapp_phone_number_id', trim($_POST['whatsapp_phone_number_id'] ?? ''), $dbh);
        update_setting('whatsapp_access_token', trim($_POST['whatsapp_access_token'] ?? ''), $dbh);
        $msg = "Notification gateway credentials saved successfully!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Automated Notifications &amp; WhatsApp Hub — Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php echo render_favicon_tags(); ?>
  <link rel="stylesheet" type="text/css" href="css/main.css">
  <link rel="stylesheet" type="text/css" href="css/ios-admin-glass.css">
  <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
  <style>
    .notify-card {
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(25px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 20px;
      padding: 24px;
      margin-bottom: 24px;
    }
  </style>
</head>
<body class="app sidebar-mini rtl">
  <?php include 'include/header.php'; ?>
  <div class="app-sidebar__overlay" data-toggle="sidebar"></div>
  <?php include 'include/sidebar.php'; ?>

  <main class="app-content">
    <div class="app-title">
      <div>
        <h1><i class="fa fa-whatsapp" style="color: #25d366; margin-right: 8px;"></i> Automated Notifications &amp; Alerts Hub</h1>
        <p>Trigger automated WhatsApp renewal alerts, overdue payment reminders, and configure API gateway webhooks</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Notifications Hub</li>
      </ul>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-success" style="border-radius: 12px; background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #fff;">
        <i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
      </div>
    <?php endif; ?>

    <!-- 1. Urgent Expiry Alerts (3 Days) -->
    <div class="notify-card" style="border-left: 4px solid #f59e0b;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
        <div>
          <h3 style="font-size: 18px; font-weight: 700; margin: 0; color: #fff;">
            <i class="fa fa-clock-o text-warning" style="margin-right: 6px;"></i> Urgent Pass Expiration Queue (Next 3 Days)
            <span class="ios-badge ios-badge-warning" style="margin-left: 8px;"><?php echo count($expiring3Days); ?> athletes</span>
          </h3>
          <p style="font-size: 13px; color: var(--text-muted); margin: 4px 0 0;">These memberships are expiring immediately. Send 1-click WhatsApp reminders.</p>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-bordered" style="margin: 0;">
          <thead>
            <tr>
              <th>Member Name</th>
              <th>Phone</th>
              <th>Plan</th>
              <th>Expiry Date</th>
              <th>Time Remaining</th>
              <th style="text-align: right;">Direct WhatsApp</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($expiring3Days)): foreach ($expiring3Days as $row): 
              $cleanPhone = preg_replace('/[^0-9]/', '', $row['mobile']);
              $waText = rawurlencode("Hi " . $row['fname'] . ", your " . $row['plan_name'] . " membership at " . $gymName . " expires on " . date('d M Y', strtotime($row['expiry_date'])) . ". Renew your pass at reception today to keep your fitness momentum going!");
            ?>
            <tr>
              <td style="font-weight: 700; color: #fff;"><?php echo htmlspecialchars($row['fname'] . ' ' . $row['lname']); ?></td>
              <td style="color: #cbd5e1;"><?php echo htmlspecialchars($row['mobile']); ?></td>
              <td style="color: var(--color-primary); font-weight: 600;"><?php echo htmlspecialchars($row['plan_name']); ?></td>
              <td style="font-weight: 700; color: #fff;"><?php echo date('d M Y', strtotime($row['expiry_date'])); ?></td>
              <td>
                <span class="ios-badge ios-badge-danger">
                  <i class="fa fa-hourglass-end"></i> <?php echo $row['days_left']; ?> days left
                </span>
              </td>
              <td style="text-align: right;">
                <a href="https://wa.me/<?php echo $cleanPhone; ?>?text=<?php echo $waText; ?>" target="_blank" class="btn btn-sm" style="background: rgba(37, 211, 102, 0.2); color: #25d366; border: 1px solid rgba(37, 211, 102, 0.3); border-radius: 8px; font-weight: 600;">
                  <i class="fa fa-whatsapp"></i> Send Expiry Alert
                </a>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 25px;">No passes expiring within the next 72 hours.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 2. Urgent Balance Dues Alerts -->
    <div class="notify-card" style="border-left: 4px solid #ef4444;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
        <div>
          <h3 style="font-size: 18px; font-weight: 700; margin: 0; color: #fff;">
            <i class="fa fa-money text-danger" style="margin-right: 6px;"></i> Pending Balance Recovery Queue
            <span class="ios-badge ios-badge-danger" style="margin-left: 8px;"><?php echo count($duesAccounts); ?> pending</span>
          </h3>
          <p style="font-size: 13px; color: var(--text-muted); margin: 4px 0 0;">Members with outstanding membership dues awaiting settlement.</p>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-bordered" style="margin: 0;">
          <thead>
            <tr>
              <th>Member Name</th>
              <th>Phone</th>
              <th>Plan</th>
              <th>Outstanding Balance</th>
              <th style="text-align: right;">Direct WhatsApp</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($duesAccounts)): foreach ($duesAccounts as $row): 
              $cleanPhone = preg_replace('/[^0-9]/', '', $row['mobile']);
              $waText = rawurlencode("Hello " . $row['fname'] . ", this is a courteous payment notice from " . $gymName . ". You have a pending membership balance of " . $currencySymbol . number_format($row['balance_due'], 2) . " on your account. Please visit reception or reply to clear your dues. Thank you!");
            ?>
            <tr>
              <td style="font-weight: 700; color: #fff;"><?php echo htmlspecialchars($row['fname'] . ' ' . $row['lname']); ?></td>
              <td style="color: #cbd5e1;"><?php echo htmlspecialchars($row['mobile']); ?></td>
              <td style="color: #cbd5e1;"><?php echo htmlspecialchars($row['plan_name']); ?></td>
              <td style="font-weight: 800; color: #ef4444; font-size: 15px;"><?php echo $currencySymbol . number_format($row['balance_due'], 2); ?></td>
              <td style="text-align: right;">
                <a href="https://wa.me/<?php echo $cleanPhone; ?>?text=<?php echo $waText; ?>" target="_blank" class="btn btn-sm" style="background: rgba(37, 211, 102, 0.2); color: #25d366; border: 1px solid rgba(37, 211, 102, 0.3); border-radius: 8px; font-weight: 600;">
                  <i class="fa fa-whatsapp"></i> Send Due Notice
                </a>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 25px;">All member balances are cleared!</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 3. WhatsApp / SMS Cloud API Gateway Settings -->
    <div class="tile">
      <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 8px; color: #fff;">
        <i class="fa fa-cogs" style="color: var(--color-primary); margin-right: 6px;"></i> Cloud Gateway &amp; Webhook Credentials
      </h3>
      <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">
        Connect Meta WhatsApp Cloud API or Twilio SMS to enable automated background broadcast dispatches.
      </p>

      <form method="post" class="row" style="row-gap: 16px;">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="save_gateway" value="1">

        <div class="col-md-6">
          <label style="font-weight: 600; font-size: 13px;">WhatsApp Business Phone Number ID</label>
          <input type="text" name="whatsapp_phone_number_id" class="form-control" value="<?php echo htmlspecialchars(get_setting('whatsapp_phone_number_id', '')); ?>" placeholder="e.g. 104829104928" style="border-radius: 10px;">
        </div>

        <div class="col-md-6">
          <label style="font-weight: 600; font-size: 13px;">Meta WhatsApp Permanent Access Token</label>
          <input type="password" name="whatsapp_access_token" class="form-control" value="<?php echo htmlspecialchars(get_setting('whatsapp_access_token', '')); ?>" placeholder="EAAG..." style="border-radius: 10px;">
        </div>

        <div class="col-md-6">
          <label style="font-weight: 600; font-size: 13px;">Webhook / SMS Gateway Endpoint URL</label>
          <input type="url" name="sms_gateway_url" class="form-control" value="<?php echo htmlspecialchars(get_setting('sms_gateway_url', '')); ?>" placeholder="https://api.gateway.com/v1/send" style="border-radius: 10px;">
        </div>

        <div class="col-md-6">
          <label style="font-weight: 600; font-size: 13px;">SMS Gateway API Secret / Key</label>
          <input type="password" name="sms_api_key" class="form-control" value="<?php echo htmlspecialchars(get_setting('sms_api_key', '')); ?>" placeholder="Key or Secret" style="border-radius: 10px;">
        </div>

        <div class="col-12" style="margin-top: 10px;">
          <button type="submit" class="ios-btn ios-btn-accent" style="padding: 10px 24px; border-radius: 12px;">
            <i class="fa fa-save"></i> Save Gateway Configuration
          </button>
        </div>
      </form>
    </div>
  </main>

  <script src="js/jquery-3.2.1.min.js"></script>
  <script src="js/popper.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/main.js"></script>
</body>
</html>
