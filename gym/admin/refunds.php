<?php
require_once 'include/config.php';
require_once 'include/rbac.php';

// Strict Authorization: Owner or Staff with authorized refund permissions
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

if (!is_owner() && !has_permission('refunds.view_assigned') && !has_permission('approve_refund') && !has_permission('execute_refund')) {
    $_SESSION['rbac_error'] = "Access Restricted: You do not have permission to access the Refund Cases Queue.";
    header('location:index.php');
    exit();
}

$gymId = function_exists('get_current_gym_id') ? get_current_gym_id() : 1;
$statusFilter = trim($_GET['status'] ?? '');
$allowedStatuses = ['pending', 'approved', 'rejected', 'executed'];

// Build Query: Case-specific refund records only
$sql = "
    SELECT 
        r.id as refund_id,
        r.booking_id,
        r.payment_id,
        r.refund_amount,
        r.reason,
        r.status,
        r.requested_by_admin_id,
        r.authorized_by_admin_id,
        r.executed_at,
        r.created_at,
        r.rejection_reason,
        u.fname,
        u.lname,
        u.email as member_email,
        u.mobile as member_mobile,
        p.titlename as package_name,
        req_admin.name as requester_name
    FROM tbl_refund_requests r
    JOIN tblbooking b ON r.booking_id = b.id
    JOIN tbluser u ON b.userid = u.id
    LEFT JOIN tbladdpackage p ON b.packageid = p.id
    LEFT JOIN tbladmin req_admin ON r.requested_by_admin_id = req_admin.id
    WHERE r.gym_id = :gym_id
";

$params = [':gym_id' => $gymId];
if (in_array($statusFilter, $allowedStatuses, true)) {
    $sql .= " AND r.status = :status";
    $params[':status'] = $statusFilter;
}

$sql .= " ORDER BY CASE WHEN r.status = 'pending' THEN 1 WHEN r.status = 'approved' THEN 2 ELSE 3 END, r.created_at DESC";

$stmt = $dbh->prepare($sql);
$stmt->execute($params);
$refundCases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counts for filter pills
$cntStmt = $dbh->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_cnt,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_cnt,
        SUM(CASE WHEN status = 'executed' THEN 1 ELSE 0 END) as executed_cnt,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_cnt
    FROM tbl_refund_requests
    WHERE gym_id = :gym_id
");
$cntStmt->execute([':gym_id' => $gymId]);
$counts = $cntStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Refund Cases Queue — <?php echo htmlspecialchars(get_setting('gym_name', 'FitForge Gym'), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php echo render_favicon_tags(); ?>
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="stylesheet" type="text/css" href="css/ios-admin-glass.css">
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</head>
<body class="app sidebar-mini rtl">
    <?php include 'include/header.php'; ?>
    <div class="app-sidebar__overlay" data-toggle="sidebar"></div>
    <?php include 'include/sidebar.php'; ?>

    <main class="app-content">
        <div class="app-title">
            <div style="display: flex; align-items: center; min-width: 0;">
                <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); display: inline-flex; align-items: center; justify-content: center; margin-right: 14px; flex-shrink: 0;">
                    <i class="fa fa-undo" style="color: #fbbf24; font-size: 18px;"></i>
                </div>
                <div style="min-width: 0;">
                    <h1>Refund Cases Queue</h1>
                    <p>Case-by-case evaluation and authorization of membership refund applications</p>
                </div>
            </div>
            <ul class="app-breadcrumb breadcrumb d-none d-sm-flex">
                <li class="breadcrumb-item"><a href="index.php"><i class="fa fa-home"></i></a></li>
                <li class="breadcrumb-item active">Refund Queue</li>
            </ul>
        </div>

        <!-- Filter Navigation Pills -->
        <div style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;">
            <a href="refunds.php" class="btn btn-sm <?php echo empty($statusFilter) ? 'btn-primary' : 'btn-outline-light'; ?>" style="border-radius: 10px; font-weight: 600;">
                All Cases (<?php echo intval($counts['total'] ?? 0); ?>)
            </a>
            <a href="refunds.php?status=pending" class="btn btn-sm <?php echo ($statusFilter === 'pending') ? 'btn-warning' : 'btn-outline-warning'; ?>" style="border-radius: 10px; font-weight: 600;">
                <i class="fa fa-clock-o"></i> Pending Review (<?php echo intval($counts['pending_cnt'] ?? 0); ?>)
            </a>
            <a href="refunds.php?status=approved" class="btn btn-sm <?php echo ($statusFilter === 'approved') ? 'btn-info' : 'btn-outline-info'; ?>" style="border-radius: 10px; font-weight: 600;">
                <i class="fa fa-check"></i> Approved (<?php echo intval($counts['approved_cnt'] ?? 0); ?>)
            </a>
            <a href="refunds.php?status=executed" class="btn btn-sm <?php echo ($statusFilter === 'executed') ? 'btn-success' : 'btn-outline-success'; ?>" style="border-radius: 10px; font-weight: 600;">
                <i class="fa fa-check-circle"></i> Executed (<?php echo intval($counts['executed_cnt'] ?? 0); ?>)
            </a>
            <a href="refunds.php?status=rejected" class="btn btn-sm <?php echo ($statusFilter === 'rejected') ? 'btn-danger' : 'btn-outline-danger'; ?>" style="border-radius: 10px; font-weight: 600;">
                <i class="fa fa-times-circle"></i> Rejected (<?php echo intval($counts['rejected_cnt'] ?? 0); ?>)
            </a>
        </div>

        <div class="tile" style="border-radius: 18px;">
            <div class="table-responsive">
                <table class="table table-hover" style="margin: 0; vertical-align: middle;">
                    <thead>
                        <tr>
                            <th style="font-size: 11.5px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Case ID</th>
                            <th style="font-size: 11.5px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Member</th>
                            <th style="font-size: 11.5px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Plan</th>
                            <th style="font-size: 11.5px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Requested Amount</th>
                            <th style="font-size: 11.5px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Reason</th>
                            <th style="font-size: 11.5px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Requested By</th>
                            <th style="font-size: 11.5px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Status</th>
                            <th style="font-size: 11.5px; text-transform: uppercase; color: #94a3b8; font-weight: 700; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($refundCases)): foreach ($refundCases as $rc): ?>
                            <tr>
                                <td>
                                    <strong style="color: #38bdf8;">#RF-<?php echo str_pad($rc['refund_id'], 4, '0', STR_PAD_LEFT); ?></strong>
                                    <div style="font-size: 11px; color: #94a3b8;">Bk #<?php echo htmlspecialchars($rc['booking_id']); ?></div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($rc['fname'] . ' ' . $rc['lname']); ?></strong>
                                    <div style="font-size: 11.5px; color: #94a3b8;"><?php echo htmlspecialchars($rc['member_email']); ?></div>
                                </td>
                                <td>
                                    <span style="font-size: 12.5px; color: #cbd5e1;"><?php echo htmlspecialchars($rc['package_name'] ?? 'Membership Pass'); ?></span>
                                </td>
                                <td>
                                    <strong style="color: #fbbf24; font-size: 14px;"><?php echo format_currency(floatval($rc['refund_amount'])); ?></strong>
                                </td>
                                <td>
                                    <span style="font-size: 12px; color: #cbd5e1; display: inline-block; max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($rc['reason']); ?>">
                                        <?php echo htmlspecialchars($rc['reason']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 12px; color: #94a3b8;">
                                        <?php echo htmlspecialchars($rc['requester_name'] ?? 'Staff Desk'); ?>
                                    </span>
                                    <div style="font-size: 11px; color: #64748b;"><?php echo date('d M Y', strtotime($rc['created_at'])); ?></div>
                                </td>
                                <td>
                                    <?php
                                    $st = $rc['status'];
                                    $badge = match ($st) {
                                        'pending' => 'badge-warning',
                                        'approved' => 'badge-info',
                                        'executed' => 'badge-success',
                                        'rejected' => 'badge-danger',
                                        default => 'badge-secondary'
                                    };
                                    ?>
                                    <span class="badge <?php echo $badge; ?>" style="font-size: 11px; padding: 4px 8px; border-radius: 6px; text-transform: uppercase;">
                                        <?php echo htmlspecialchars($st); ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="booking-history-details.php?bookingid=<?php echo urlencode($rc['booking_id']); ?>&refund_id=<?php echo urlencode($rc['refund_id']); ?>" class="btn btn-sm btn-primary" style="border-radius: 8px; font-size: 12px; padding: 4px 12px; font-weight: 600;">
                                        <i class="fa fa-search"></i> Review Case
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: #94a3b8; padding: 40px;">
                                    <i class="fa fa-check-circle-o fa-2x d-block mb-2 text-success"></i>
                                    No refund cases match the selected filter.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="js/jquery-3.2.1.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
