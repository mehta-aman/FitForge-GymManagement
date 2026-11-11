<?php
require_once 'include/config.php';
require_owner(); // Strictly restricted to Gym Owner

$msg = '';
$error = '';
$ownerId = intval($_SESSION['adminid']);
$gymId = get_current_gym_id();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security token mismatch. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Approve Staff Account
        if ($action === 'approve_staff') {
            $staffId = intval($_POST['staff_id'] ?? 0);
            $assignedRole = trim($_POST['assigned_role'] ?? 'floor_staff');
            $allowedRoles = ['manager', 'trainer', 'floor_staff', 'owner'];

            if (!in_array($assignedRole, $allowedRoles, true)) {
                $error = 'Invalid role specified.';
            } else {
                // Fetch staff record
                $stStmt = $dbh->prepare("SELECT * FROM tbladmin WHERE id = :id");
                $stStmt->execute([':id' => $staffId]);
                $staff = $stStmt->fetch(PDO::FETCH_ASSOC);

                if (!$staff) {
                    $error = 'Staff record not found.';
                } else {
                    $trainerId = $staff['trainer_id'];

                    // If assigned as trainer, ensure record in tbl_trainers
                    if ($assignedRole === 'trainer') {
                        if (!empty($trainerId)) {
                            $updTr = $dbh->prepare("UPDATE tbl_trainers SET status = 'active' WHERE id = :id");
                            $updTr->execute([':id' => $trainerId]);
                        } else {
                            $insTr = $dbh->prepare("INSERT INTO tbl_trainers (name, speciality, mobile, email, experience_years, bio, status, gym_id) VALUES (:name, 'Fitness Coach', :mob, :email, 1, 'Certified Gym Coach', 'active', :gym_id)");
                            $insTr->execute([
                                ':name' => $staff['name'],
                                ':mob' => $staff['mobile'],
                                ':email' => $staff['email'],
                                ':gym_id' => $gymId
                            ]);
                            $trainerId = $dbh->lastInsertId();
                        }
                    }

                    $updAdmin = $dbh->prepare("
                        UPDATE tbladmin 
                        SET status = 'approved', role = :role, trainer_id = :tid, approved_by = :approver, approved_at = NOW(), rejection_reason = NULL
                        WHERE id = :id
                    ");
                    $updAdmin->execute([
                        ':role' => $assignedRole,
                        ':tid' => $trainerId,
                        ':approver' => $ownerId,
                        ':id' => $staffId
                    ]);

                    $msg = "Account for " . htmlspecialchars($staff['name']) . " has been APPROVED as " . get_role_label($assignedRole) . "!";
                }
            }
        }

        // 2. Reject Staff Request
        elseif ($action === 'reject_staff') {
            $staffId = intval($_POST['staff_id'] ?? 0);
            $reason = trim($_POST['rejection_reason'] ?? 'Application rejected by administration.');

            $rej = $dbh->prepare("UPDATE tbladmin SET status = 'rejected', rejection_reason = :reason, approved_by = :approver, approved_at = NOW() WHERE id = :id");
            $rej->execute([':reason' => $reason, ':approver' => $ownerId, ':id' => $staffId]);

            // If linked to trainer, inactivate
            $rejTr = $dbh->prepare("UPDATE tbl_trainers SET status = 'inactive' WHERE id = (SELECT trainer_id FROM tbladmin WHERE id = :id)");
            $rejTr->execute([':id' => $staffId]);

            $msg = "Staff registration request was rejected.";
        }

        // 3. Update Existing Employee Role
        elseif ($action === 'update_role') {
            $staffId = intval($_POST['staff_id'] ?? 0);
            $newRole = trim($_POST['new_role'] ?? '');
            $allowedRoles = ['manager', 'trainer', 'floor_staff', 'owner'];

            if ($staffId === $ownerId) {
                $error = 'You cannot modify your own Owner role.';
            } elseif (in_array($newRole, $allowedRoles, true)) {
                $upd = $dbh->prepare("UPDATE tbladmin SET role = :role WHERE id = :id");
                $upd->execute([':role' => $newRole, ':id' => $staffId]);
                $msg = "Staff role updated successfully to " . get_role_label($newRole) . ".";
            }
        }

        // 4. Toggle Employee Active / Suspended
        elseif ($action === 'toggle_status') {
            $staffId = intval($_POST['staff_id'] ?? 0);
            if ($staffId === $ownerId) {
                $error = 'You cannot suspend your own Owner account.';
            } else {
                $st = $dbh->prepare("SELECT status FROM tbladmin WHERE id = :id");
                $st->execute([':id' => $staffId]);
                $curr = $st->fetchColumn();

                $next = ($curr === 'approved') ? 'suspended' : 'approved';
                $upd = $dbh->prepare("UPDATE tbladmin SET status = :status WHERE id = :id");
                $upd->execute([':status' => $next, ':id' => $staffId]);
                $msg = "Employee status changed to " . ucfirst($next) . ".";
            }
        }

        // 5. Directly Create Staff (Pre-Approved by Owner)
        elseif ($action === 'create_direct') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $role = trim($_POST['role'] ?? 'floor_staff');
            $pwd = $_POST['password'] ?? '';

            if (empty($name) || empty($email) || empty($mobile) || empty($pwd)) {
                $error = 'All fields are required.';
            } elseif (strlen($pwd) < 6) {
                $error = 'Password must be at least 6 characters.';
            } else {
                $chk = $dbh->prepare("SELECT id FROM tbladmin WHERE email = :email");
                $chk->execute([':email' => $email]);
                if ($chk->fetch()) {
                    $error = 'An account with this email already exists.';
                } else {
                    $hash = password_hash($pwd, PASSWORD_DEFAULT);
                    $tid = null;
                    if ($role === 'trainer') {
                        $insTr = $dbh->prepare("INSERT INTO tbl_trainers (name, speciality, mobile, email, status, gym_id) VALUES (:name, 'Fitness Coach', :mob, :email, 'active', :gym_id)");
                        $insTr->execute([':name' => $name, ':mob' => $mobile, ':email' => $email, ':gym_id' => $gymId]);
                        $tid = $dbh->lastInsertId();
                    }

                    $ins = $dbh->prepare("
                        INSERT INTO tbladmin (name, email, mobile, password, role, status, approved_by, approved_at, trainer_id, create_date, gym_id)
                        VALUES (:name, :email, :mob, :pwd, :role, 'approved', :approver, NOW(), :tid, NOW(), :gym_id)
                    ");
                    $ins->execute([
                        ':name' => $name,
                        ':email' => $email,
                        ':mob' => $mobile,
                        ':pwd' => $hash,
                        ':role' => $role,
                        ':approver' => $ownerId,
                        ':tid' => $tid,
                        ':gym_id' => $gymId
                    ]);
                    $msg = "New employee created and approved successfully!";
                }
            }
        }

        // 6. Save Individual Employee Permission Overrides
        elseif ($action === 'save_permissions') {
            $staffId = intval($_POST['staff_id'] ?? 0);
            if ($staffId === $ownerId) {
                $error = 'Owner permissions are immutable and cannot be overridden.';
            } else {
                $chk = $dbh->prepare("SELECT name, role FROM tbladmin WHERE id = :id");
                $chk->execute([':id' => $staffId]);
                $targetStaff = $chk->fetch(PDO::FETCH_ASSOC);

                if (!$targetStaff) {
                    $error = 'Staff member not found.';
                } else {
                    $permsSubmitted = $_POST['perm'] ?? [];
                    $allPerms = get_all_permissions($dbh);
                    $updatedCount = 0;

                    foreach ($allPerms as $p) {
                        $pKey = $p['permission_key'];
                        $isOwnerOnly = intval($p['is_owner_only']) === 1 || in_array($pKey, OWNER_ONLY_FINANCIAL_PERMISSIONS, true);
                        $state = $permsSubmitted[$pKey] ?? 'default';

                        // Block assigning owner-only financial permissions to non-owners
                        if ($isOwnerOnly && $state === 'grant') {
                            continue; // Silently disallow / reject
                        }

                        if ($state === 'grant') {
                            set_admin_permission_override($staffId, $pKey, 1, $ownerId, $dbh);
                            $updatedCount++;
                        } elseif ($state === 'deny') {
                            set_admin_permission_override($staffId, $pKey, 0, $ownerId, $dbh);
                            $updatedCount++;
                        } elseif ($state === 'default') {
                            set_admin_permission_override($staffId, $pKey, null, $ownerId, $dbh);
                        }
                    }

                    $msg = "Permissions and granular overrides successfully updated for " . htmlspecialchars($targetStaff['name']) . "!";
                }
            }
        }
    }
}

// Fetch Pending Requests
$pendingStmt = $dbh->prepare("
    SELECT a.*, t.speciality, t.bio
    FROM tbladmin a
    LEFT JOIN tbl_trainers t ON a.trainer_id = t.id
    WHERE a.status = 'pending'
    ORDER BY a.create_date ASC
");
$pendingStmt->execute();
$pendingList = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch All Staff Directory
$staffStmt = $dbh->prepare("
    SELECT a.*, o.name as approver_name, t.speciality
    FROM tbladmin a
    LEFT JOIN tbladmin o ON a.approved_by = o.id
    LEFT JOIN tbl_trainers t ON a.trainer_id = t.id
    ORDER BY FIELD(a.role, 'owner', 'manager', 'trainer', 'floor_staff'), a.create_date DESC
");
$staffStmt->execute();
$staffList = $staffStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch All Permissions & Current Overrides for Fast Modal Rendering
$allPermissions = get_all_permissions($dbh);
$allOverrides = [];
$ovrStmt = $dbh->query("SELECT admin_id, permission_key, is_granted FROM tbl_admin_permissions");
foreach ($ovrStmt->fetchAll(PDO::FETCH_ASSOC) as $o) {
    $allOverrides[intval($o['admin_id'])][$o['permission_key']] = intval($o['is_granted']);
}

$allRolePerms = [];
$rpStmt = $dbh->query("SELECT role_key, permission_key FROM tbl_role_permissions");
foreach ($rpStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $allRolePerms[$r['role_key']][$r['permission_key']] = true;
}

// Counts
$totalPending = count($pendingList);
$totalStaff = count($staffList);
$gymName = get_setting('gym_name', 'Golden Eagle');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Approvals &amp; Employee RBAC — <?php echo htmlspecialchars($gymName); ?></title>
    <?php echo render_favicon_tags(); ?>
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="stylesheet" type="text/css" href="css/ios-admin-glass.css">
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .staff-nav-pills {
            display: flex;
            gap: 10px;
            margin-bottom: 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 14px;
        }
        .staff-nav-pills a {
            padding: 10px 18px;
            border-radius: 12px;
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #cbd5e1;
            font-weight: 600;
            font-size: 13.5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .staff-nav-pills a.active {
            background: var(--color-primary);
            color: #fff;
            border-color: var(--color-primary);
            box-shadow: 0 4px 15px rgba(56, 189, 248, 0.3);
        }
        .staff-badge-counter {
            background: #ef4444;
            color: #fff;
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 800;
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
                <h1><i class="fa fa-users" style="color: var(--color-primary); margin-right: 8px;"></i> Staff Management &amp; Approvals</h1>
                <p>Owner RBAC Control: Review prospective employee requests, assign staff roles, and manage permissions</p>
            </div>
            <ul class="app-breadcrumb breadcrumb">
                <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Staff Approvals</li>
            </ul>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-success alert-dismissible fade show" style="border-radius: 14px; background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #fff;">
                <i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" style="border-radius: 14px; background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fff;">
                <i class="fa fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Stat Strip -->
        <div class="row" style="margin-bottom: 24px;">
            <div class="col-md-4 mb-3">
                <div class="tile mb-0" style="border-radius: 18px; background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(255, 255, 255, 0.1); padding: 18px; display: flex; align-items: center; gap: 16px;">
                    <div style="width: 52px; height: 52px; border-radius: 14px; background: rgba(245, 158, 11, 0.2); color: #fbbf24; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                        <i class="fa fa-clock-o"></i>
                    </div>
                    <div>
                        <div style="font-size: 26px; font-weight: 800; color: #fff; line-height: 1;"><?php echo $totalPending; ?></div>
                        <div style="font-size: 13px; color: #94a3b8; margin-top: 4px;">Pending Approvals</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="tile mb-0" style="border-radius: 18px; background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(255, 255, 255, 0.1); padding: 18px; display: flex; align-items: center; gap: 16px;">
                    <div style="width: 52px; height: 52px; border-radius: 14px; background: rgba(56, 189, 248, 0.2); color: #38bdf8; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                        <i class="fa fa-shield"></i>
                    </div>
                    <div>
                        <div style="font-size: 26px; font-weight: 800; color: #fff; line-height: 1;"><?php echo $totalStaff; ?></div>
                        <div style="font-size: 13px; color: #94a3b8; margin-top: 4px;">Total Staff Accounts</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="tile mb-0" style="border-radius: 18px; background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(255, 255, 255, 0.1); padding: 18px; display: flex; align-items: center; gap: 16px;">
                    <div style="width: 52px; height: 52px; border-radius: 14px; background: rgba(16, 185, 129, 0.2); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                        <i class="fa fa-check-circle"></i>
                    </div>
                    <div>
                        <div style="font-size: 26px; font-weight: 800; color: #fff; line-height: 1;"><?php echo ($totalStaff - $totalPending); ?></div>
                        <div style="font-size: 13px; color: #94a3b8; margin-top: 4px;">Active Employees</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="staff-nav-pills">
            <a href="#tab-pending" data-toggle="pill" class="active">
                <i class="fa fa-inbox"></i> Pending Approvals
                <?php if ($totalPending > 0): ?>
                    <span class="staff-badge-counter"><?php echo $totalPending; ?></span>
                <?php endif; ?>
            </a>
            <a href="#tab-roster" data-toggle="pill">
                <i class="fa fa-list"></i> Employee Directory
            </a>
            <a href="#tab-create" data-toggle="pill">
                <i class="fa fa-plus-circle"></i> Add Staff Directly
            </a>
        </div>

        <div class="tab-content">
            <!-- TAB 1: PENDING APPROVALS -->
            <div class="tab-pane fade show active" id="tab-pending">
                <div class="tile" style="border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.1); background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(20px);">
                    <h3 class="tile-title" style="font-size: 18px; font-weight: 700; color: #fff;">
                        <i class="fa fa-hourglass-half text-warning"></i> Pending Employee Registration Requests
                    </h3>
                    <div class="table-responsive">
                        <table class="table table-hover" style="color: #cbd5e1; vertical-align: middle;">
                            <thead>
                                <tr style="border-bottom: 2px solid rgba(255, 255, 255, 0.1); color: #94a3b8; font-size: 12px; text-transform: uppercase;">
                                    <th>Applicant Name</th>
                                    <th>Contact Details</th>
                                    <th>Requested Role</th>
                                    <th>Speciality / Notes</th>
                                    <th>Date Applied</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pendingList)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">
                                            <i class="fa fa-check-circle-o fa-2x mb-2 d-block text-success"></i>
                                            All staff registrations are up to date! No pending requests waiting for approval.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pendingList as $p): ?>
                                        <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.05);">
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <div style="width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #f59e0b, #d97706); color: #000; font-weight: 800; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                                                        <?php echo strtoupper(substr($p['name'], 0, 2)); ?>
                                                    </div>
                                                    <div>
                                                        <strong style="color: #fff; font-size: 14px;"><?php echo htmlspecialchars($p['name']); ?></strong>
                                                        <div style="font-size: 12px; color: #94a3b8;">Staff ID: #<?php echo $p['id']; ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size: 13px; color: #e2e8f0;"><?php echo htmlspecialchars($p['email']); ?></div>
                                                <div style="font-size: 12px; color: #94a3b8;"><?php echo htmlspecialchars($p['mobile']); ?></div>
                                            </td>
                                            <td>
                                                <?php echo get_role_badge($p['role']); ?>
                                            </td>
                                            <td>
                                                <span style="font-size: 12.5px; color: #cbd5e1;">
                                                    <?php echo htmlspecialchars($p['speciality'] ?? $p['bio'] ?? '—'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-size: 12.5px; color: #94a3b8;">
                                                    <?php echo date('d M Y, h:i A', strtotime($p['create_date'])); ?>
                                                </span>
                                            </td>
                                            <td style="text-align: right;">
                                                <button type="button" class="btn btn-sm btn-success" onclick="openApproveModal(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>', '<?php echo $p['role']; ?>')" style="border-radius: 8px; font-weight: 600;">
                                                    <i class="fa fa-check"></i> Approve
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="openRejectModal(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>')" style="border-radius: 8px; font-weight: 600;">
                                                    <i class="fa fa-times"></i> Reject
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 2: ACTIVE STAFF DIRECTORY -->
            <div class="tab-pane fade" id="tab-roster">
                <div class="tile" style="border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.1); background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(20px);">
                    <h3 class="tile-title" style="font-size: 18px; font-weight: 700; color: #fff;">
                        <i class="fa fa-id-card text-info"></i> Active Employees &amp; Coaches Roster
                    </h3>
                    <div class="table-responsive">
                        <table class="table table-hover" style="color: #cbd5e1; vertical-align: middle;">
                            <thead>
                                <tr style="border-bottom: 2px solid rgba(255, 255, 255, 0.1); color: #94a3b8; font-size: 12px; text-transform: uppercase;">
                                    <th>Employee</th>
                                    <th>Assigned Role</th>
                                    <th>Status</th>
                                    <th>Contact</th>
                                    <th>Approved By</th>
                                    <th>Last Login</th>
                                    <th style="text-align: right;">Manage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staffList as $s): 
                                    $isSelf = ($s['id'] === $ownerId);
                                ?>
                                    <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.05);">
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div style="width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #38bdf8, #0284c7); color: #fff; font-weight: 800; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                                                    <?php echo strtoupper(substr($s['name'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <strong style="color: #fff; font-size: 14px;">
                                                        <?php echo htmlspecialchars($s['name']); ?>
                                                        <?php if ($isSelf): ?>
                                                            <span class="badge badge-warning" style="font-size: 10px; margin-left: 4px;">YOU</span>
                                                        <?php endif; ?>
                                                    </strong>
                                                    <div style="font-size: 12px; color: #94a3b8;">ID: #<?php echo $s['id']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo get_role_badge($s['role']); ?>
                                        </td>
                                        <td>
                                            <?php echo get_status_badge($s['status']); ?>
                                        </td>
                                        <td>
                                            <div style="font-size: 12.5px; color: #e2e8f0;"><?php echo htmlspecialchars($s['email']); ?></div>
                                            <div style="font-size: 11.5px; color: #94a3b8;"><?php echo htmlspecialchars($s['mobile']); ?></div>
                                        </td>
                                        <td>
                                            <span style="font-size: 12px; color: #94a3b8;">
                                                <?php echo !empty($s['approver_name']) ? htmlspecialchars($s['approver_name']) : ($s['role'] === 'owner' ? 'Root Owner' : '—'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="font-size: 12px; color: #94a3b8;">
                                                <?php echo !empty($s['last_login']) ? date('d M, h:i A', strtotime($s['last_login'])) : 'Never'; ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <?php if (!$isSelf): ?>
                                                <button type="button" class="btn btn-sm btn-outline-warning" onclick="openPermissionsModal(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['name'], ENT_QUOTES); ?>', '<?php echo $s['role']; ?>')" style="border-radius: 8px;">
                                                    <i class="fa fa-key"></i> Permissions
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-info" onclick="openEditRoleModal(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['name'], ENT_QUOTES); ?>', '<?php echo $s['role']; ?>')" style="border-radius: 8px;">
                                                    <i class="fa fa-pencil"></i> Role
                                                </button>
                                                <form method="post" style="display: inline;" onsubmit="return confirm('Toggle status for this employee?');">
                                                    <?php echo csrf_input(); ?>
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="staff_id" value="<?php echo $s['id']; ?>">
                                                    <button type="submit" class="btn btn-sm <?php echo ($s['status'] === 'approved') ? 'btn-outline-warning' : 'btn-outline-success'; ?>" style="border-radius: 8px;">
                                                        <?php echo ($s['status'] === 'approved') ? '<i class="fa fa-pause"></i> Suspend' : '<i class="fa fa-play"></i> Activate'; ?>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size: 12px;">Primary Owner</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: ADD EMPLOYEE DIRECTLY -->
            <div class="tab-pane fade" id="tab-create">
                <div class="tile" style="max-width: 600px; border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.1); background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(20px);">
                    <h3 class="tile-title" style="font-size: 18px; font-weight: 700; color: #fff;">
                        <i class="fa fa-plus-circle text-primary"></i> Create Pre-Approved Staff Account
                    </h3>
                    <form method="post" autocomplete="off">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="create_direct">

                        <div class="form-group">
                            <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Employee Full Name *</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Sameer Khan" required style="border-radius: 10px; background: rgba(15, 23, 42, 0.6); color: #fff; border-color: rgba(255, 255, 255, 0.15);">
                        </div>

                        <div class="row">
                            <div class="col-sm-6 form-group">
                                <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Email Address *</label>
                                <input type="email" name="email" class="form-control" placeholder="sameer@fitforge.local" required style="border-radius: 10px; background: rgba(15, 23, 42, 0.6); color: #fff; border-color: rgba(255, 255, 255, 0.15);">
                            </div>
                            <div class="col-sm-6 form-group">
                                <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Mobile Phone *</label>
                                <input type="text" name="mobile" class="form-control" placeholder="+91 9876543210" required style="border-radius: 10px; background: rgba(15, 23, 42, 0.6); color: #fff; border-color: rgba(255, 255, 255, 0.15);">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-sm-6 form-group">
                                <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Assign Position / Role *</label>
                                <select name="role" class="form-control" required style="border-radius: 10px; background: rgba(15, 23, 42, 0.6); color: #fff; border-color: rgba(255, 255, 255, 0.15);">
                                    <option value="floor_staff">Front Desk &amp; Floor Staff</option>
                                    <option value="trainer">Fitness Coach / Trainer</option>
                                    <option value="manager">Operations Manager</option>
                                </select>
                            </div>
                            <div class="col-sm-6 form-group">
                                <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Password *</label>
                                <input type="password" name="password" class="form-control" placeholder="Min 6 characters" required style="border-radius: 10px; background: rgba(15, 23, 42, 0.6); color: #fff; border-color: rgba(255, 255, 255, 0.15);">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="font-weight: 600; border-radius: 10px; padding: 10px 20px;">
                            <i class="fa fa-save"></i> Save &amp; Activate Account
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </main>

    <!-- Approve Staff Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); color: #fff;">
                <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
                    <h5 class="modal-title"><i class="fa fa-check-circle text-success"></i> Approve Staff Account</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form method="post">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="approve_staff">
                    <input type="hidden" name="staff_id" id="approveStaffId">
                    <div class="modal-body">
                        <p style="font-size: 14.5px; color: #cbd5e1;">
                            Approve employee request for <strong id="approveStaffName" style="color: #fff;"></strong>?
                        </p>
                        <div class="form-group">
                            <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Confirmed Role &amp; Access Level</label>
                            <select name="assigned_role" id="approveRoleSelect" class="form-control" style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);">
                                <option value="trainer">Fitness Coach / Trainer</option>
                                <option value="floor_staff">Front Desk &amp; Floor Staff</option>
                                <option value="manager">Operations Manager</option>
                            </select>
                            <small class="form-text text-muted">You can adjust the position from their requested role if needed.</small>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid rgba(255, 255, 255, 0.08);">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                        <button type="submit" class="btn btn-success" style="border-radius: 8px; font-weight: 600;"><i class="fa fa-check"></i> Confirm &amp; Activate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Staff Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); color: #fff;">
                <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
                    <h5 class="modal-title"><i class="fa fa-times-circle text-danger"></i> Reject Staff Application</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form method="post">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="reject_staff">
                    <input type="hidden" name="staff_id" id="rejectStaffId">
                    <div class="modal-body">
                        <p style="font-size: 14.5px; color: #cbd5e1;">
                            Reject employee request for <strong id="rejectStaffName" style="color: #fff;"></strong>?
                        </p>
                        <div class="form-group">
                            <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Rejection Reason (Optional)</label>
                            <textarea name="rejection_reason" class="form-control" rows="3" placeholder="e.g. Position filled, Unverified credentials" style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid rgba(255, 255, 255, 0.08);">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                        <button type="submit" class="btn btn-danger" style="border-radius: 8px; font-weight: 600;"><i class="fa fa-times"></i> Reject Application</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Role Modal -->
    <div class="modal fade" id="editRoleModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); color: #fff;">
                <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
                    <h5 class="modal-title"><i class="fa fa-pencil text-info"></i> Reassign Employee Role</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form method="post">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" name="staff_id" id="editStaffId">
                    <div class="modal-body">
                        <p style="font-size: 14.5px; color: #cbd5e1;">
                            Reassigning role for <strong id="editStaffName" style="color: #fff;"></strong>:
                        </p>
                        <div class="form-group">
                            <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">New Role</label>
                            <select name="new_role" id="editRoleSelect" class="form-control" style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);">
                                <option value="floor_staff">Front Desk &amp; Floor Staff</option>
                                <option value="trainer">Fitness Coach / Trainer</option>
                                <option value="manager">Operations Manager</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid rgba(255, 255, 255, 0.08);">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="border-radius: 8px; font-weight: 600;">Save Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Manage Permissions & Overrides Modal -->
    <div class="modal fade" id="permissionsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border-radius: 20px; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); color: #fff;">
                <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
                    <div>
                        <h5 class="modal-title"><i class="fa fa-key text-warning"></i> Granular Employee Permissions &amp; Overrides</h5>
                        <p style="font-size: 12.5px; color: #94a3b8; margin: 2px 0 0;">
                            Configure individual overrides for <strong id="permStaffName" style="color: #fff;"></strong> (<span id="permStaffRoleBadge" style="color: #38bdf8; font-weight: 700;"></span>)
                        </p>
                    </div>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form method="post">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="save_permissions">
                    <input type="hidden" name="staff_id" id="permStaffId">
                    <div class="modal-body" style="max-height: 520px; overflow-y: auto; padding: 18px 24px;">
                        <div class="alert alert-secondary" style="border-radius: 12px; background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255, 255, 255, 0.1); font-size: 12px; color: #cbd5e1; margin-bottom: 18px;">
                            <i class="fa fa-shield text-warning"></i> <strong>Option A Restricted Financial Policy:</strong> General payment records, ledgers, dues, receipts, and revenue reports remain strictly reserved for the Gym Owner and cannot be granted to non-owners.
                        </div>

                        <?php
                        $groupedPerms = [];
                        foreach ($allPermissions as $p) {
                            $cat = $p['category'];
                            $groupedPerms[$cat][] = $p;
                        }
                        $categoryLabels = [
                            'financial' => ['title' => 'Financial & Accounting (Strict Owner Boundary)', 'icon' => 'fa-money'],
                            'memberships' => ['title' => 'Membership Plans & Packages', 'icon' => 'fa-id-card'],
                            'refunds' => ['title' => 'Case-Specific Refund Operations', 'icon' => 'fa-undo'],
                            'operations' => ['title' => 'Gym Floor & Member Coaching', 'icon' => 'fa-heartbeat'],
                            'attendance' => ['title' => 'Attendance Station & Check-Ins', 'icon' => 'fa-qrcode'],
                            'administration' => ['title' => 'Administration & Hierarchy', 'icon' => 'fa-cogs']
                        ];
                        foreach ($groupedPerms as $catKey => $perms):
                            $catInfo = $categoryLabels[$catKey] ?? ['title' => ucfirst($catKey), 'icon' => 'fa-folder'];
                        ?>
                            <div style="margin-bottom: 20px;">
                                <h6 style="color: #38bdf8; font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; margin-bottom: 10px;">
                                    <i class="fa <?php echo $catInfo['icon']; ?>"></i> <?php echo htmlspecialchars($catInfo['title']); ?>
                                </h6>
                                <?php foreach ($perms as $p): 
                                    $pKey = $p['permission_key'];
                                    $isOwnerOnly = intval($p['is_owner_only']) === 1 || in_array($pKey, OWNER_ONLY_FINANCIAL_PERMISSIONS, true);
                                ?>
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; margin-bottom: 6px; background: rgba(30, 41, 59, 0.5); border: 1px solid rgba(255, 255, 255, 0.06); border-radius: 10px; gap: 12px;">
                                        <div style="min-width: 0; flex: 1;">
                                            <div style="font-size: 13px; font-weight: 600; color: #fff;"><?php echo htmlspecialchars($p['label']); ?></div>
                                            <div style="font-size: 11.5px; color: #94a3b8;"><?php echo htmlspecialchars($p['description']); ?></div>
                                            <div class="perm-default-hint" id="hint_<?php echo htmlspecialchars($pKey); ?>" style="font-size: 11px; color: #64748b; margin-top: 2px;"></div>
                                        </div>
                                        <div style="flex-shrink: 0;">
                                            <?php if ($isOwnerOnly): ?>
                                                <span class="badge badge-secondary" style="font-size: 11px; padding: 5px 10px; border-radius: 6px;">
                                                    <i class="fa fa-lock"></i> Owner Only (Locked)
                                                </span>
                                            <?php else: ?>
                                                <select name="perm[<?php echo htmlspecialchars($pKey); ?>]" id="select_<?php echo htmlspecialchars($pKey); ?>" class="form-control form-control-sm perm-override-select" data-perm="<?php echo htmlspecialchars($pKey); ?>" style="width: 145px; border-radius: 8px; background: rgba(15, 23, 42, 0.9); color: #fff; border-color: rgba(255, 255, 255, 0.2); font-size: 12px;">
                                                    <option value="default">Role Default</option>
                                                    <option value="grant">Explicit Grant</option>
                                                    <option value="deny">Explicit Deny</option>
                                                </select>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid rgba(255, 255, 255, 0.08);">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                        <button type="submit" class="btn btn-warning" style="border-radius: 8px; font-weight: 600; color: #000;">
                            <i class="fa fa-save"></i> Save Permissions
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/jquery-3.2.1.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/main.js"></script>
    <script>
        const allStaffOverrides = <?php echo json_encode($allOverrides); ?>;
        const allRolePerms = <?php echo json_encode($allRolePerms); ?>;

        function openApproveModal(id, name, role) {
            document.getElementById('approveStaffId').value = id;
            document.getElementById('approveStaffName').textContent = name;
            document.getElementById('approveRoleSelect').value = role;
            $('#approveModal').modal('show');
        }

        function openRejectModal(id, name) {
            document.getElementById('rejectStaffId').value = id;
            document.getElementById('rejectStaffName').textContent = name;
            $('#rejectModal').modal('show');
        }

        function openEditRoleModal(id, name, role) {
            document.getElementById('editStaffId').value = id;
            document.getElementById('editStaffName').textContent = name;
            document.getElementById('editRoleSelect').value = role;
            $('#editRoleModal').modal('show');
        }

        function openPermissionsModal(id, name, role) {
            document.getElementById('permStaffId').value = id;
            document.getElementById('permStaffName').textContent = name;
            document.getElementById('permStaffRoleBadge').textContent = role.toUpperCase();
            
            const staffOvr = allStaffOverrides[id] || {};
            const roleP = allRolePerms[role] || {};

            document.querySelectorAll('.perm-override-select').forEach(sel => {
                const pKey = sel.getAttribute('data-perm');
                if (typeof staffOvr[pKey] !== 'undefined') {
                    sel.value = staffOvr[pKey] === 1 ? 'grant' : 'deny';
                } else {
                    sel.value = 'default';
                }

                const hintEl = document.getElementById('hint_' + pKey);
                if (hintEl) {
                    const isGrantedByRole = roleP[pKey] === true;
                    hintEl.textContent = 'Role default (' + role + '): ' + (isGrantedByRole ? 'Allowed' : 'Denied');
                }
            });

            $('#permissionsModal').modal('show');
        }
    </script>
</body>
</html>