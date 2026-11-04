<?php  
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

// 1. Core KPIs
$totalMembers = $dbh->query("SELECT count(*) FROM tbluser")->fetchColumn();
$activeMemberships = $dbh->query("SELECT count(*) FROM tbl_memberships WHERE status = 'active' AND expiry_date >= CURDATE()")->fetchColumn();
$expiredMemberships = $dbh->query("SELECT count(*) FROM tbl_memberships WHERE status = 'expired' OR expiry_date < CURDATE()")->fetchColumn();
$upcomingRenewals = $dbh->query("SELECT count(*) FROM tbl_memberships WHERE status = 'active' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)")->fetchColumn();

// 2. Financial KPIs
$totalRevenue = floatval($dbh->query("SELECT SUM(payment) FROM tblpayment")->fetchColumn() ?: 0);
$totalBilled = floatval($dbh->query("SELECT SUM(total_amount) FROM tbl_memberships")->fetchColumn() ?: 0);
$outstandingBalance = max(0, $totalBilled - $totalRevenue);

// 3. Recent Transactions
$recentPayments = $dbh->query("SELECT p.id, p.payment, p.paymentType, p.payment_date, p.bookingID, u.fname, u.lname 
    FROM tblpayment p
    LEFT JOIN tblbooking b ON p.bookingID = b.id
    LEFT JOIN tbluser u ON b.userid = u.id
    ORDER BY p.id DESC LIMIT 5")->fetchAll(PDO::FETCH_OBJ);

// 4. Recent Member Registrations
$recentMembers = $dbh->query("SELECT id, fname, lname, email, mobile, create_date, status FROM tbluser ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_OBJ);

// 5. Interactive Chart Data (Real Database Aggregations)
$monthlyRevenueData = $dbh->query("
    SELECT DATE_FORMAT(payment_date, '%b %Y') as m_label, SUM(payment) as m_revenue
    FROM tblpayment
    GROUP BY DATE_FORMAT(payment_date, '%Y-%m'), m_label
    ORDER BY DATE_FORMAT(payment_date, '%Y-%m') ASC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$chartLabels = json_encode(array_column($monthlyRevenueData, 'm_label'));
$chartRevenue = json_encode(array_map('floatval', array_column($monthlyRevenueData, 'm_revenue')));

$planDistData = $dbh->query("
    SELECT p.titlename, count(m.id) as cnt
    FROM tbl_memberships m
    JOIN tbladdpackage p ON m.plan_id = p.id
    GROUP BY p.id, p.titlename
")->fetchAll(PDO::FETCH_ASSOC);

$planLabels = json_encode(array_column($planDistData, 'titlename'));
$planCounts = json_encode(array_map('intval', array_column($planDistData, 'cnt')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Dashboard — <?php echo htmlspecialchars(get_setting('gym_name', 'GYM MS'), ENT_QUOTES, 'UTF-8'); ?></title>
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
      <div style="display: flex; align-items: center; min-width: 0;">
        <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(56, 189, 248, 0.12); border: 1px solid rgba(56, 189, 248, 0.25); display: inline-flex; align-items: center; justify-content: center; margin-right: 14px; flex-shrink: 0;">
          <i class="fa fa-tachometer" style="color: var(--color-primary); font-size: 18px;"></i>
        </div>
        <div style="min-width: 0;">
          <h1>Control Center</h1>
          <p>Real-time analytics across member subscriptions, revenue streams, and club activities</p>
        </div>
      </div>
      <ul class="app-breadcrumb breadcrumb d-none d-sm-flex">
        <li class="breadcrumb-item"><a href="index.php"><i class="fa fa-home"></i></a></li>
        <li class="breadcrumb-item active">Dashboard</li>
      </ul>
    </div>

    <!-- KPI Row 1: Membership Lifecycle -->
    <div class="row">
      <!-- Total Members -->
      <div class="col-sm-6 col-lg-3">
        <a href="members.php" class="widget-small info">
          <div class="icon"><i class="fa fa-users"></i></div>
          <div class="info">
            <h4>Total Members</h4>
            <p><?php echo $totalMembers; ?></p>
          </div>
        </a>
      </div>

      <!-- Active Memberships -->
      <div class="col-sm-6 col-lg-3">
        <a href="memberships.php?filter=active" class="widget-small primary">
          <div class="icon"><i class="fa fa-check-circle"></i></div>
          <div class="info">
            <h4>Active Subs</h4>
            <p><?php echo $activeMemberships; ?></p>
          </div>
        </a>
      </div>

      <!-- Expiring Soon -->
      <div class="col-sm-6 col-lg-3">
        <a href="memberships.php?filter=renewals" class="widget-small warning">
          <div class="icon"><i class="fa fa-clock-o"></i></div>
          <div class="info">
            <h4>Renewals (14d)</h4>
            <p><?php echo $upcomingRenewals; ?></p>
          </div>
        </a>
      </div>

      <!-- Expired Subs -->
      <div class="col-sm-6 col-lg-3">
        <a href="memberships.php?filter=expired" class="widget-small danger">
          <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
          <div class="info">
            <h4>Expired Subs</h4>
            <p><?php echo $expiredMemberships; ?></p>
          </div>
        </a>
      </div>
    </div>

    <!-- KPI Row 2: Financial Health -->
    <div class="row">
      <!-- Total Revenue -->
      <div class="col-md-6">
        <div class="tile" style="margin-bottom: 20px;">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
              <span style="font-size: 11.5px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Total Collected Revenue</span>
              <h2 style="font-size: 32px; font-weight: 800; color: #10b981; margin: 4px 0 0; letter-spacing: -0.03em; line-height: 1.1;">
                <?php echo format_currency($totalRevenue); ?>
              </h2>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16, 185, 129, 0.14); border: 1px solid rgba(16, 185, 129, 0.28); color: #34d399; font-size: 20px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
              <i class="fa fa-money"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Outstanding Balance -->
      <div class="col-md-6">
        <div class="tile" style="margin-bottom: 20px;">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
              <span style="font-size: 11.5px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Outstanding Member Balance</span>
              <h2 style="font-size: 32px; font-weight: 800; color: #fbbf24; margin: 4px 0 0; letter-spacing: -0.03em; line-height: 1.1;">
                <?php echo format_currency($outstandingBalance); ?>
              </h2>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245, 158, 11, 0.14); border: 1px solid rgba(245, 158, 11, 0.28); color: #fbbf24; font-size: 20px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
              <i class="fa fa-hourglass-half"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Real-Data Analytics & Growth Charts -->
    <div class="row">
      <!-- Left: Revenue Growth Trend -->
      <div class="col-lg-8">
        <div class="tile" style="min-height: 360px; display: flex; flex-direction: column; justify-content: space-between; margin-bottom: 20px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
            <div>
              <h3 class="tile-title" style="margin: 0;"><i class="fa fa-line-chart" style="color: var(--color-primary); margin-right: 8px;"></i> Revenue &amp; Collections Trend</h3>
              <p style="font-size: 12.5px; color: #94a3b8; margin: 3px 0 0;">Historical monthly gross receipts from database ledger</p>
            </div>
            <span class="ios-badge ios-badge-success" style="font-size: 11px; padding: 4px 10px; border-radius: 20px;"><i class="fa fa-circle" style="font-size: 7px; margin-right: 5px;"></i> Live Verified Ledger</span>
          </div>
          <div style="height: 260px; position: relative; width: 100%; flex: 1;">
            <canvas id="revenueTrendChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Right: Active Plan Breakdown -->
      <div class="col-lg-4">
        <div class="tile" style="min-height: 360px; display: flex; flex-direction: column; justify-content: space-between; margin-bottom: 20px;">
          <div style="margin-bottom: 16px;">
            <h3 class="tile-title" style="margin: 0;"><i class="fa fa-pie-chart" style="color: var(--color-accent); margin-right: 8px;"></i> Plan Distribution</h3>
            <p style="font-size: 12.5px; color: #94a3b8; margin: 3px 0 0;">Active pass breakdown by tier</p>
          </div>
          <div style="height: 260px; position: relative; width: 100%; flex: 1; display: flex; align-items: center; justify-content: center;">
            <?php if (!empty($planDistData)): ?>
              <canvas id="planDistChart"></canvas>
            <?php else: ?>
              <div style="text-align: center; color: #94a3b8;">
                <i class="fa fa-pie-chart" style="font-size: 38px; margin-bottom: 10px; opacity: 0.35;"></i>
                <p style="margin: 0; font-size: 13px;">No active subscriptions logged yet</p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions Panel -->
    <div class="row">
      <div class="col-12">
        <div class="tile" style="margin-bottom: 20px; padding: 18px 24px;">
          <h3 class="tile-title" style="margin-bottom: 14px;">
            <i class="fa fa-bolt" style="color: var(--color-primary); margin-right: 8px;"></i> Management Quick Actions
          </h3>
          <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="member-create.php" class="ios-btn ios-btn-primary" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
              <i class="fa fa-user-plus"></i> Add New Member
            </a>
            <a href="add-post.php" class="ios-btn ios-btn-glass" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
              <i class="fa fa-plus-circle"></i> Create Membership Plan
            </a>
            <a href="bookings.php?filter=unpaid" class="ios-btn ios-btn-accent" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
              <i class="fa fa-credit-card"></i> Process Payments
            </a>
            <a href="website-branding.php" class="ios-btn ios-btn-glass" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
              <i class="fa fa-paint-brush"></i> Customize Branding
            </a>
            <a href="theme-settings.php" class="ios-btn ios-btn-glass" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
              <i class="fa fa-magic"></i> Theme &amp; Colors
            </a>
            <a href="reports.php" class="ios-btn ios-btn-glass" style="padding: 8px 16px; font-size: 13px; border-radius: 10px;">
              <i class="fa fa-bar-chart"></i> Reports &amp; Exports
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Two-Column Ledger: Recent Payments & Member Activity -->
    <div class="row">
      <!-- Recent Payments -->
      <div class="col-lg-7">
        <div class="tile" style="margin-bottom: 20px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 class="tile-title" style="margin: 0;"><i class="fa fa-credit-card" style="color: var(--color-primary); margin-right: 8px;"></i> Recent Payments</h3>
            <a href="bookings.php" class="btn btn-sm btn-outline-light" style="border-radius: 8px; font-size: 12px; padding: 4px 12px;">View All</a>
          </div>
          <div class="table-responsive">
            <table class="table table-hover" style="margin: 0;">
              <thead>
                <tr>
                  <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em;">Member</th>
                  <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em;">Type</th>
                  <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em;">Amount</th>
                  <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em;">Date</th>
                  <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em; text-align: right;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($recentPayments)): foreach ($recentPayments as $rp): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($rp->fname . ' ' . $rp->lname, ENT_QUOTES, 'UTF-8'); ?></strong></td>
                  <td>
                    <span class="ios-badge <?php echo ($rp->paymentType === 'Full Payment') ? 'ios-badge-success' : 'ios-badge-warning'; ?>" style="font-size: 11px; padding: 3px 8px;">
                      <?php echo htmlspecialchars($rp->paymentType, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                  </td>
                  <td style="font-weight: 700; color: #10b981;"><?php echo format_currency($rp->payment); ?></td>
                  <td style="color: #94a3b8; font-size: 12.5px;"><?php echo date('d M Y, h:i A', strtotime($rp->payment_date)); ?></td>
                  <td style="text-align: right;">
                    <a href="booking-history-details.php?bookingid=<?php echo $rp->bookingID; ?>" class="btn btn-sm btn-primary" style="padding: 3px 10px; border-radius: 6px; font-size: 11.5px;">Receipt</a>
                  </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="5" style="text-align: center; color: #94a3b8; padding: 20px;">No payment records logged.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Recent Members -->
      <div class="col-lg-5">
        <div class="tile" style="margin-bottom: 20px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 class="tile-title" style="margin: 0;"><i class="fa fa-user-plus" style="color: var(--color-primary); margin-right: 8px;"></i> Recent Members</h3>
            <a href="members.php" class="btn btn-sm btn-outline-light" style="border-radius: 8px; font-size: 12px; padding: 4px 12px;">Directory</a>
          </div>
          <div class="table-responsive">
            <table class="table table-hover" style="margin: 0;">
              <thead>
                <tr>
                  <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em;">Name</th>
                  <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em;">Email</th>
                  <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em;">Joined</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($recentMembers)): foreach ($recentMembers as $rm): ?>
                <tr>
                  <td>
                    <a href="member-profile.php?id=<?php echo $rm->id; ?>" style="color: #fff; font-weight: 600; text-decoration: none;">
                      <?php echo htmlspecialchars($rm->fname . ' ' . $rm->lname, ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                  </td>
                  <td style="color: #94a3b8; font-size: 12.5px;"><?php echo htmlspecialchars($rm->email, ENT_QUOTES, 'UTF-8'); ?></td>
                  <td style="color: #64748b; font-size: 12px;"><?php echo date('d M Y', strtotime($rm->create_date)); ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="3" style="text-align: center; color: #94a3b8; padding: 20px;">No registered members found.</td></tr>
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
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    // 1. Monthly Revenue Trend Line Chart
    const revCanvas = document.getElementById('revenueTrendChart');
    if (revCanvas) {
      const revCtx = revCanvas.getContext('2d');
      const revGrad = revCtx.createLinearGradient(0, 0, 0, 260);
      revGrad.addColorStop(0, 'rgba(56, 189, 248, 0.35)');
      revGrad.addColorStop(1, 'rgba(56, 189, 248, 0.01)');

      new Chart(revCtx, {
        type: 'line',
        data: {
          labels: <?php echo $chartLabels; ?>,
          datasets: [{
            label: 'Gross Collections (₹)',
            data: <?php echo $chartRevenue; ?>,
            borderColor: '#38bdf8',
            borderWidth: 2.5,
            backgroundColor: revGrad,
            fill: true,
            tension: 0.35,
            pointBackgroundColor: '#ffffff',
            pointBorderColor: '#38bdf8',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: 'rgba(15, 23, 42, 0.95)',
              titleColor: '#fff',
              bodyColor: '#38bdf8',
              borderColor: 'rgba(255, 255, 255, 0.12)',
              borderWidth: 1,
              cornerRadius: 10,
              padding: 12,
              callbacks: {
                label: (ctx) => ' Collections: ₹' + Number(ctx.raw).toLocaleString('en-IN')
              }
            }
          },
          scales: {
            x: {
              grid: { color: 'rgba(255, 255, 255, 0.04)' },
              ticks: { color: '#94a3b8', font: { size: 11 } }
            },
            y: {
              grid: { color: 'rgba(255, 255, 255, 0.04)' },
              ticks: {
                color: '#94a3b8',
                font: { size: 11 },
                callback: (v) => '₹' + v
              }
            }
          }
        }
      });
    }

    // 2. Plan Distribution Donut Chart
    const planCanvas = document.getElementById('planDistChart');
    if (planCanvas) {
      const planCtx = planCanvas.getContext('2d');
      new Chart(planCtx, {
        type: 'doughnut',
        data: {
          labels: <?php echo $planLabels; ?>,
          datasets: [{
            data: <?php echo $planCounts; ?>,
            backgroundColor: [
              '#38bdf8',
              '#f59e0b',
              '#10b981',
              '#a855f7',
              '#ec4899',
              '#6366f1'
            ],
            borderColor: '#0f172a',
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                color: '#cbd5e1',
                font: { size: 11, weight: '500' },
                padding: 14,
                boxWidth: 12,
                boxHeight: 12,
                borderRadius: 3,
                useBorderRadius: true
              }
            }
          },
          cutout: '70%'
        }
      });
    }
  </script>
</body>
</html>
