<?php
require_once 'include/config.php';
require_once 'include/rbac.php';
require_once '../include/lost_found_helper.php';

if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$adminId = intval($_SESSION['adminid']);
$adminRole = current_admin_role();
$gymId = function_exists('get_current_gym_id') ? get_current_gym_id() : 1;

$msg = '';
$err = '';

// Handle Admin Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = "Security token mismatch. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Staff Logs Item Directly at Front Desk
        if ($action === 'create_desk_item') {
            $itemType = trim($_POST['item_type'] ?? 'found');
            $title = trim($_POST['title'] ?? '');
            $category = trim($_POST['category'] ?? 'other');
            $location = trim($_POST['location_in_gym'] ?? 'Front Desk Reception');
            $caption = trim($_POST['caption'] ?? '');
            $status = trim($_POST['status'] ?? ($itemType === 'found' ? 'handed_to_desk' : 'open'));
            $rewardNotes = trim($_POST['reward_notes'] ?? '');

            if (empty($title) || empty($caption)) {
                $err = "Title and description caption are required.";
            } else {
                $imagePath = null;
                if (!empty($_FILES['item_image']['name'])) {
                    $up = handle_image_upload($_FILES['item_image'], 'uploads/lostfound', 5);
                    if ($up['success']) {
                        $imagePath = $up['path'];
                    }
                }

                $ins = $dbh->prepare("
                    INSERT INTO tbl_lost_found 
                    (gym_id, item_type, title, category, caption, location_in_gym, date_reported, image_path, status, author_type, author_id, author_name, contact_phone, contact_email, reward_notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, 'admin', ?, ?, 'Front Desk Reception', 'frontdesk@fitforge.local', ?, NOW())
                ");
                $ins->execute([
                    $gymId, $itemType, $title, $category, $caption, $location, $imagePath, $status,
                    $adminId, $_SESSION['name'] ?? 'Front Desk Staff', $rewardNotes
                ]);
                $msg = "Item logged into Lost & Found system successfully!";
            }
        }

        // 2. Update Status / Mark Returned
        elseif ($action === 'update_status') {
            $itemId = intval($_POST['item_id'] ?? 0);
            $newStatus = trim($_POST['status'] ?? '');
            $claimedByName = trim($_POST['claimed_by_name'] ?? '');

            $allowedStatuses = ['open', 'handed_to_desk', 'claimed', 'returned'];
            if (in_array($newStatus, $allowedStatuses, true)) {
                $resolvedAt = in_array($newStatus, ['claimed', 'returned'], true) ? date('Y-m-d H:i:s') : null;
                $upd = $dbh->prepare("
                    UPDATE tbl_lost_found
                    SET status = ?, claimed_by_name = ?, resolved_at = ?
                    WHERE id = ? AND gym_id = ?
                ");
                $upd->execute([$newStatus, $claimedByName ?: null, $resolvedAt, $itemId, $gymId]);
                $msg = "Item status updated to " . ucfirst(str_replace('_', ' ', $newStatus)) . "!";
            }
        }
    }
}

// Fetch Filters
$statusFilter = trim($_GET['status'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$typeFilter = trim($_GET['type'] ?? '');

$sql = "SELECT * FROM tbl_lost_found WHERE gym_id = :gym_id";
$params = [':gym_id' => $gymId];

if (!empty($statusFilter)) {
    $sql .= " AND status = :status";
    $params[':status'] = $statusFilter;
}
if (!empty($categoryFilter)) {
    $sql .= " AND category = :cat";
    $params[':cat'] = $categoryFilter;
}
if (!empty($typeFilter)) {
    $sql .= " AND item_type = :type";
    $params[':type'] = $typeFilter;
}

$sql .= " ORDER BY CASE WHEN status = 'handed_to_desk' THEN 1 WHEN status = 'open' THEN 2 ELSE 3 END, created_at DESC";
$stmt = $dbh->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counts
$counts = $dbh->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'handed_to_desk' THEN 1 ELSE 0 END) as desk_cnt,
        SUM(CASE WHEN item_type = 'lost' AND status = 'open' THEN 1 ELSE 0 END) as lost_cnt,
        SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned_cnt
    FROM tbl_lost_found
    WHERE gym_id = $gymId
")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Lost &amp; Found Desk — <?php echo htmlspecialchars(get_setting('gym_name', 'FitForge Gym'), ENT_QUOTES, 'UTF-8'); ?></title>
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
                <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.3); display: inline-flex; align-items: center; justify-content: center; margin-right: 14px; flex-shrink: 0;">
                    <i class="fa fa-archive" style="color: #38bdf8; font-size: 18px;"></i>
                </div>
                <div style="min-width: 0;">
                    <h1>Lost &amp; Found Desk</h1>
                    <p>Manage items turned into front desk, log floor recoveries, and verify member claims</p>
                </div>
            </div>
            <ul class="app-breadcrumb breadcrumb d-none d-sm-flex">
                <li class="breadcrumb-item"><a href="index.php"><i class="fa fa-home"></i></a></li>
                <li class="breadcrumb-item active">Lost &amp; Found</li>
            </ul>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-success alert-dismissible fade show" style="border-radius: 14px; background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #fff;">
                <i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>
        <?php if (!empty($err)): ?>
            <div class="alert alert-danger alert-dismissible fade show" style="border-radius: 14px; background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fff;">
                <i class="fa fa-exclamation-triangle"></i> <?php echo htmlspecialchars($err); ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Stat Strip -->
        <div class="row" style="margin-bottom: 24px;">
            <div class="col-sm-6 col-lg-3">
                <a href="lost-found.php?status=handed_to_desk" class="widget-small primary">
                    <div class="icon"><i class="fa fa-building"></i></div>
                    <div class="info">
                        <h4>At Front Desk</h4>
                        <p><?php echo intval($counts['desk_cnt'] ?? 0); ?></p>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="lost-found.php?type=lost&status=open" class="widget-small danger">
                    <div class="icon"><i class="fa fa-search"></i></div>
                    <div class="info">
                        <h4>Open Lost Reports</h4>
                        <p><?php echo intval($counts['lost_cnt'] ?? 0); ?></p>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="lost-found.php?status=returned" class="widget-small success">
                    <div class="icon"><i class="fa fa-check-circle"></i></div>
                    <div class="info">
                        <h4>Reunited / Returned</h4>
                        <p><?php echo intval($counts['returned_cnt'] ?? 0); ?></p>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="lost-found.php" class="widget-small info">
                    <div class="icon"><i class="fa fa-th-list"></i></div>
                    <div class="info">
                        <h4>Total Tracked</h4>
                        <p><?php echo intval($counts['total'] ?? 0); ?></p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Action & Filter Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="lost-found.php" class="btn btn-sm <?php echo empty($statusFilter) && empty($typeFilter) ? 'btn-primary' : 'btn-outline-light'; ?>" style="border-radius: 8px; font-weight: 600;">
                    All Items
                </a>
                <a href="lost-found.php?status=handed_to_desk" class="btn btn-sm <?php echo ($statusFilter === 'handed_to_desk') ? 'btn-info' : 'btn-outline-info'; ?>" style="border-radius: 8px; font-weight: 600;">
                    <i class="fa fa-building"></i> In Front Desk Bin (<?php echo intval($counts['desk_cnt'] ?? 0); ?>)
                </a>
                <a href="lost-found.php?type=lost" class="btn btn-sm <?php echo ($typeFilter === 'lost') ? 'btn-danger' : 'btn-outline-danger'; ?>" style="border-radius: 8px; font-weight: 600;">
                    <i class="fa fa-search"></i> Lost Items (<?php echo intval($counts['lost_cnt'] ?? 0); ?>)
                </a>
                <a href="lost-found.php?status=returned" class="btn btn-sm <?php echo ($statusFilter === 'returned') ? 'btn-success' : 'btn-outline-success'; ?>" style="border-radius: 8px; font-weight: 600;">
                    <i class="fa fa-check"></i> Reunited (<?php echo intval($counts['returned_cnt'] ?? 0); ?>)
                </a>
            </div>

            <div style="display: flex; gap: 8px;">
                <a href="../lost-found.php" target="_blank" class="btn btn-sm btn-outline-light" style="border-radius: 8px; font-weight: 600;">
                    <i class="fa fa-external-link"></i> Public Hub
                </a>
                <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#adminCreateModal" style="border-radius: 8px; font-weight: 700;">
                    <i class="fa fa-plus-circle"></i> Log Recovered Item
                </button>
            </div>
        </div>

        <!-- Inventory Table -->
        <div class="tile" style="border-radius: 18px;">
            <div class="table-responsive">
                <table class="table table-hover" style="margin: 0; vertical-align: middle;">
                    <thead>
                        <tr>
                            <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Item</th>
                            <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Type</th>
                            <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Category</th>
                            <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Location in Gym</th>
                            <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Reported By</th>
                            <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Status</th>
                            <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($items)): foreach ($items as $it): ?>
                            <tr>
                                <td>
                                    <strong style="color: #fff; font-size: 13.5px;"><?php echo htmlspecialchars($it['title']); ?></strong>
                                    <div style="font-size: 11.5px; color: #94a3b8; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars($it['caption']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo $it['item_type'] === 'lost' ? 'badge-danger' : 'badge-success'; ?>" style="font-size: 10px; text-transform: uppercase;">
                                        <?php echo $it['item_type']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo get_category_badge($it['category']); ?>
                                </td>
                                <td>
                                    <span style="font-size: 12.5px; color: #cbd5e1;"><i class="fa fa-map-marker text-danger"></i> <?php echo htmlspecialchars($it['location_in_gym']); ?></span>
                                </td>
                                <td>
                                    <span style="font-size: 12px; color: #fff; font-weight: 600;"><?php echo htmlspecialchars($it['author_name']); ?></span>
                                    <div style="font-size: 11px; color: #94a3b8;"><?php echo date('d M Y', strtotime($it['date_reported'])); ?></div>
                                </td>
                                <td>
                                    <?php echo get_item_status_badge($it['status'], $it['item_type']); ?>
                                </td>
                                <td style="text-align: right;">
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="openStatusModal(<?php echo htmlspecialchars(json_encode($it), ENT_QUOTES); ?>)" style="border-radius: 6px; font-size: 11.5px;">
                                        <i class="fa fa-pencil"></i> Status
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #94a3b8; padding: 35px;">
                                    <i class="fa fa-archive fa-2x d-block mb-2 text-info"></i>
                                    No items recorded matching the filter.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- ADMIN MODAL: LOG RECOVERED ITEM -->
    <div class="modal fade" id="adminCreateModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border-radius: 20px; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); color: #fff;">
                <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
                    <h5 class="modal-title"><i class="fa fa-plus-circle text-primary"></i> Log Item at Front Desk</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form method="post" enctype="multipart/form-data">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="create_desk_item">
                    <div class="modal-body" style="padding: 20px 24px;">
                        <div class="row">
                            <div class="col-sm-6 form-group">
                                <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Type</label>
                                <select name="item_type" class="form-control" style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);">
                                    <option value="found">Found Item (Turned into reception)</option>
                                    <option value="lost">Lost Report (Reported by member at desk)</option>
                                </select>
                            </div>
                            <div class="col-sm-6 form-group">
                                <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Category</label>
                                <select name="category" class="form-control" style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);">
                                    <?php foreach (get_lost_found_categories() as $ck => $cv): ?>
                                        <option value="<?php echo $ck; ?>"><?php echo htmlspecialchars($cv['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Item Name / Headline *</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Set of Car Keys with Red Fob" required style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);">
                        </div>

                        <div class="row">
                            <div class="col-sm-6 form-group">
                                <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Found / Lost Location in Gym *</label>
                                <input type="text" name="location_in_gym" class="form-control" placeholder="e.g. Free Weights Dumbbell Bench" required style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);">
                            </div>
                            <div class="col-sm-6 form-group">
                                <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Status</label>
                                <select name="status" class="form-control" style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);">
                                    <option value="handed_to_desk">Stored at Front Desk Reception</option>
                                    <option value="open">Open / Floor Investigation</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Description Caption &amp; Identifying Notes *</label>
                            <textarea name="caption" rows="3" class="form-control" placeholder="Describe color, brand, condition, or items attached." required style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);"></textarea>
                        </div>

                        <div class="form-group">
                            <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Photo (Optional)</label>
                            <input type="file" name="item_image" accept="image/*" class="form-control-file" style="color: #cbd5e1;">
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid rgba(255, 255, 255, 0.08);">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="border-radius: 8px; font-weight: 700;">Save Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ADMIN MODAL: UPDATE STATUS / RELEASE ITEM -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); color: #fff;">
                <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
                    <h5 class="modal-title"><i class="fa fa-pencil text-info"></i> Update Item Status &amp; Release</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form method="post">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="item_id" id="statusItemId">
                    <div class="modal-body" style="padding: 20px;">
                        <h4 id="statusItemTitle" style="color: #fff; font-size: 16px; font-weight: 700; margin-bottom: 14px;"></h4>

                        <div class="form-group">
                            <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Update Item Status</label>
                            <select name="status" id="statusSelect" class="form-control" style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);">
                                <option value="handed_to_desk">Deposited at Front Desk Reception</option>
                                <option value="open">Active / Searching</option>
                                <option value="claimed">Claim Verification in Progress</option>
                                <option value="returned">Reunited &amp; Released to Member</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Claimed By / Released To Member Name</label>
                            <input type="text" name="claimed_by_name" id="statusClaimedBy" class="form-control" placeholder="Member name upon pickup" style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);">
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid rgba(255, 255, 255, 0.08);">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="border-radius: 8px; font-weight: 700;">Update Status</button>
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
        function openStatusModal(it) {
            document.getElementById('statusItemId').value = it.id;
            document.getElementById('statusItemTitle').textContent = it.title;
            document.getElementById('statusSelect').value = it.status;
            document.getElementById('statusClaimedBy').value = it.claimed_by_name || '';
            $('#statusModal').modal('show');
        }
    </script>
</body>
</html>
