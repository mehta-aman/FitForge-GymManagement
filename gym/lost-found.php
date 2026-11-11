<?php
require_once 'include/config.php';
require_once 'include/security.php';
require_once 'include/lost_found_helper.php';

// Strict Authentication Gate: User must be logged in with their account to see or access Lost & Found
if (empty($_SESSION['uid']) && empty($_SESSION['adminid'])) {
    $redirectUrl = 'lost-found.php';
    if (!empty($_SERVER['QUERY_STRING'])) {
        $redirectUrl .= '?' . $_SERVER['QUERY_STRING'];
    }
    header('location:login.php?redirect=' . urlencode($redirectUrl));
    exit();
}

$userId = !empty($_SESSION['uid']) ? intval($_SESSION['uid']) : null;
$adminId = !empty($_SESSION['adminid']) ? intval($_SESSION['adminid']) : null;
$adminRole = $_SESSION['admin_role'] ?? null;
$gymId = function_exists('get_current_gym_id') ? get_current_gym_id() : 1;

$msg = '';
$err = '';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = "Security token mismatch. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Create Lost / Found Item Post
        if ($action === 'create_item') {
            $itemType = trim($_POST['item_type'] ?? 'lost');
            if (!in_array($itemType, ['lost', 'found'], true)) $itemType = 'lost';

            $title = trim($_POST['title'] ?? '');
            $category = trim($_POST['category'] ?? 'other');
            $location = trim($_POST['location_in_gym'] ?? '');
            $caption = trim($_POST['caption'] ?? '');
            $dateReported = trim($_POST['date_reported'] ?? date('Y-m-d'));
            $rewardNotes = trim($_POST['reward_notes'] ?? '');
            $atFrontDesk = !empty($_POST['at_front_desk']);

            // Author Identification
            if ($adminId) {
                $authorType = 'admin';
                $authorId = $adminId;
                $authorName = $_SESSION['name'] ?? 'Gym Staff';
                $contactEmail = $_SESSION['email'] ?? 'staff@fitforge.local';
                $contactPhone = trim($_POST['contact_phone'] ?? '');
            } elseif ($userId) {
                $authorType = 'user';
                $authorId = $userId;
                $uStmt = $dbh->prepare("SELECT fname, lname, email, mobile FROM tbluser WHERE id = ?");
                $uStmt->execute([$userId]);
                $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
                $authorName = $uRow ? ($uRow['fname'] . ' ' . $uRow['lname']) : ($_SESSION['name'] ?? 'Member');
                $contactEmail = $uRow['email'] ?? '';
                $contactPhone = trim($_POST['contact_phone'] ?? ($uRow['mobile'] ?? ''));
            } else {
                header('location:login.php?redirect=lost-found.php');
                exit();
            }

            if (empty($title) || empty($location) || empty($caption)) {
                $err = "Please provide an item title, location, and description caption.";
            } else {
                // Optional Image Upload
                $imagePath = null;
                if (!empty($_FILES['item_image']['name'])) {
                    $up = handle_image_upload($_FILES['item_image'], 'uploads/lostfound', 5);
                    if ($up['success']) {
                        $imagePath = $up['path'];
                    } else {
                        $err = "Image upload warning: " . $up['error'];
                    }
                }

                if (empty($err)) {
                    $status = 'open';
                    if ($itemType === 'found' && ($atFrontDesk || $adminId)) {
                        $status = 'handed_to_desk';
                    }

                    $ins = $dbh->prepare("
                        INSERT INTO tbl_lost_found 
                        (gym_id, item_type, title, category, caption, location_in_gym, date_reported, image_path, status, author_type, author_id, author_name, contact_phone, contact_email, reward_notes, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $ins->execute([
                        $gymId, $itemType, $title, $category, $caption, $location, $dateReported, $imagePath, $status,
                        $authorType, $authorId, $authorName, $contactPhone, $contactEmail, $rewardNotes
                    ]);
                    $newItemId = $dbh->lastInsertId();

                    $msg = "Your " . strtoupper($itemType) . " item post has been published! Check the recommendations below for potential matches.";
                }
            }
        }

        // 2. Update Item Post (Caption, Image, Location, Status)
        elseif ($action === 'update_item') {
            $itemId = intval($_POST['item_id'] ?? 0);
            $stmt = $dbh->prepare("SELECT * FROM tbl_lost_found WHERE id = ? AND gym_id = ?");
            $stmt->execute([$itemId, $gymId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                $err = "Item not found.";
            } elseif (!can_manage_lost_found_item($item, $userId, $adminId, $adminRole)) {
                $err = "Unauthorized: You do not have permission to edit this post.";
            } else {
                $title = trim($_POST['title'] ?? $item['title']);
                $category = trim($_POST['category'] ?? $item['category']);
                $location = trim($_POST['location_in_gym'] ?? $item['location_in_gym']);
                $caption = trim($_POST['caption'] ?? $item['caption']);
                $rewardNotes = trim($_POST['reward_notes'] ?? $item['reward_notes']);
                $status = trim($_POST['status'] ?? $item['status']);

                $imagePath = $item['image_path'];
                if (!empty($_FILES['item_image']['name'])) {
                    $up = handle_image_upload($_FILES['item_image'], 'uploads/lostfound', 5);
                    if ($up['success']) {
                        $imagePath = $up['path'];
                    }
                }

                $resolvedAt = in_array($status, ['claimed', 'returned'], true) ? date('Y-m-d H:i:s') : null;

                $upd = $dbh->prepare("
                    UPDATE tbl_lost_found
                    SET title = ?, category = ?, location_in_gym = ?, caption = ?, reward_notes = ?, image_path = ?, status = ?, resolved_at = ?
                    WHERE id = ?
                ");
                $upd->execute([$title, $category, $location, $caption, $rewardNotes, $imagePath, $status, $resolvedAt, $itemId]);

                $msg = "Post updated successfully!";
            }
        }

        // 3. Quick Status Transition: Mark as Reunited / Returned
        elseif ($action === 'quick_status') {
            $itemId = intval($_POST['item_id'] ?? 0);
            $newStatus = trim($_POST['new_status'] ?? 'returned');

            $stmt = $dbh->prepare("SELECT * FROM tbl_lost_found WHERE id = ? AND gym_id = ?");
            $stmt->execute([$itemId, $gymId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                $err = "Item not found.";
            } elseif (!can_manage_lost_found_item($item, $userId, $adminId, $adminRole)) {
                $err = "Unauthorized: You do not have permission to modify this item status.";
            } else {
                $resolvedAt = in_array($newStatus, ['claimed', 'returned'], true) ? date('Y-m-d H:i:s') : null;
                $upd = $dbh->prepare("UPDATE tbl_lost_found SET status = ?, resolved_at = ? WHERE id = ?");
                $upd->execute([$newStatus, $resolvedAt, $itemId]);
                $msg = "Status updated to " . ucfirst(str_replace('_', ' ', $newStatus)) . "!";
            }
        }
    }
}

// Fetch Filters
$typeFilter = trim($_GET['type'] ?? 'all'); // all, lost, found, desk, resolved, my
$catFilter = trim($_GET['category'] ?? '');
$searchQuery = trim($_GET['q'] ?? '');

$query = "SELECT * FROM tbl_lost_found WHERE gym_id = :gym_id";
$params = [':gym_id' => $gymId];

if ($typeFilter === 'lost') {
    $query .= " AND item_type = 'lost' AND status != 'returned'";
} elseif ($typeFilter === 'found') {
    $query .= " AND item_type = 'found' AND status != 'returned'";
} elseif ($typeFilter === 'desk') {
    $query .= " AND status = 'handed_to_desk'";
} elseif ($typeFilter === 'resolved') {
    $query .= " AND status IN ('claimed', 'returned')";
} elseif ($typeFilter === 'my') {
    if ($adminId) {
        $query .= " AND author_type = 'admin' AND author_id = :aid";
        $params[':aid'] = $adminId;
    } elseif ($userId) {
        $query .= " AND author_type = 'user' AND author_id = :uid";
        $params[':uid'] = $userId;
    }
}

if (!empty($catFilter)) {
    $query .= " AND category = :cat";
    $params[':cat'] = $catFilter;
}

if (!empty($searchQuery)) {
    $query .= " AND (title LIKE :sq OR caption LIKE :sq OR location_in_gym LIKE :sq)";
    $params[':sq'] = '%' . $searchQuery . '%';
}

$query .= " ORDER BY CASE WHEN status = 'handed_to_desk' THEN 1 WHEN status = 'open' THEN 2 ELSE 3 END, created_at DESC";
$stmt = $dbh->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Smart Potential Recommendations
$recommendations = get_lost_found_recommendations($dbh, $gymId, null, 4);

// Counts for pill counters
$counts = $dbh->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN item_type = 'lost' AND status != 'returned' THEN 1 ELSE 0 END) as lost_cnt,
        SUM(CASE WHEN item_type = 'found' AND status != 'returned' THEN 1 ELSE 0 END) as found_cnt,
        SUM(CASE WHEN status = 'handed_to_desk' THEN 1 ELSE 0 END) as desk_cnt,
        SUM(CASE WHEN status IN ('claimed', 'returned') THEN 1 ELSE 0 END) as resolved_cnt
    FROM tbl_lost_found
    WHERE gym_id = $gymId
")->fetch(PDO::FETCH_ASSOC);

$gymName = get_setting('gym_name', 'Golden Eagle');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lost &amp; Found Desk — <?php echo htmlspecialchars($gymName); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo render_favicon_tags(); ?>
    <link rel="stylesheet" href="css/bootstrap.min.css"/>
    <link rel="stylesheet" href="css/font-awesome.min.css"/>
    <link rel="stylesheet" href="css/ios-glass.css"/>
    <style>
        .lf-card {
            border-radius: 18px;
            background: rgba(15, 23, 42, 0.65);
            border: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.45);
            transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .lf-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.55);
            border-color: rgba(255, 255, 255, 0.18);
        }
        .lf-card-img {
            width: 100%;
            height: 190px;
            object-fit: cover;
            background: rgba(15, 23, 42, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .lf-type-ribbon {
            position: absolute;
            top: 12px;
            left: 12px;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }
        .lf-type-lost {
            background: #ef4444;
            color: #fff;
        }
        .lf-type-found {
            background: #10b981;
            color: #fff;
        }
        .lf-filter-pill {
            padding: 8px 16px;
            border-radius: 12px;
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #cbd5e1;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .lf-filter-pill:hover, .lf-filter-pill.active {
            background: var(--color-primary);
            color: #fff;
            border-color: var(--color-primary);
            box-shadow: 0 4px 15px rgba(56, 189, 248, 0.3);
        }
    </style>
</head>
<body>

    <?php include 'include/header.php'; ?>

    <div class="site-content-wrapper" style="padding: 40px 20px 80px;">
        <div class="container" style="max-width: 1140px;">
            
            <!-- Hero Header -->
            <div class="text-center" style="margin-bottom: 35px;">
                <div class="ios-hero-pill" style="margin-bottom: 12px;">
                    <i class="fa fa-search text-warning"></i> COMMUNITY RECOVERY ASSISTANCE
                </div>
                <h1 style="font-size: 38px; font-weight: 800; letter-spacing: -0.03em; color: #fff; margin-bottom: 10px;">
                    Gym Lost &amp; Found Hub
                </h1>
                <p style="color: var(--text-secondary); font-size: 15px; max-width: 680px; margin: 0 auto 20px;">
                    Misplaced your gear or found an item on the gym floor? Post it here, check smart match recommendations, or retrieve items kept at the front desk reception.
                </p>
                <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-danger" onclick="openCreateModal('lost')" style="border-radius: 12px; font-weight: 700; padding: 10px 20px;">
                        <i class="fa fa-search"></i> I Lost Something
                    </button>
                    <button type="button" class="btn btn-success" onclick="openCreateModal('found')" style="border-radius: 12px; font-weight: 700; padding: 10px 20px;">
                        <i class="fa fa-hand-grab-o"></i> I Found Something
                    </button>
                </div>
            </div>

            <!-- Flash Alerts -->
            <?php if (!empty($msg)): ?>
                <div class="alert alert-success alert-dismissible fade show" style="border-radius: 14px; background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #fff; margin-bottom: 24px;">
                    <i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>
            <?php if (!empty($err)): ?>
                <div class="alert alert-danger alert-dismissible fade show" style="border-radius: 14px; background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fff; margin-bottom: 24px;">
                    <i class="fa fa-exclamation-triangle"></i> <?php echo htmlspecialchars($err); ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>

            <!-- SMART RECOMMENDATIONS & MATCHES SECTION -->
            <?php if (!empty($recommendations)): ?>
                <div class="tile" style="border-radius: 18px; background: linear-gradient(135deg, rgba(15, 23, 42, 0.85) 0%, rgba(11, 15, 25, 0.95) 100%); border: 1px solid rgba(56, 189, 248, 0.25); box-shadow: 0 10px 25px rgba(0, 0, 0, 0.45); padding: 22px; margin-bottom: 30px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <span class="badge badge-warning" style="font-size: 11px; padding: 4px 8px; border-radius: 6px; text-transform: uppercase;">
                                <i class="fa fa-magic"></i> Smart Match Recommendations
                            </span>
                            <h3 style="font-size: 20px; font-weight: 700; color: #fff; margin: 4px 0 0;">
                                Potential Item Pairings Detected
                            </h3>
                            <p style="font-size: 13px; color: #94a3b8; margin: 2px 0 0;">
                                The system identified matching Lost &amp; Found reports in the same category!
                            </p>
                        </div>
                        <span style="font-size: 12px; color: #38bdf8; font-weight: 600;">
                            <?php echo count($recommendations); ?> Potential Match<?php echo count($recommendations) > 1 ? 'es' : ''; ?>
                        </span>
                    </div>

                    <div class="row">
                        <?php foreach ($recommendations as $rec): ?>
                            <div class="col-md-6 mb-3">
                                <div style="background: rgba(15, 23, 42, 0.65); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 16px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; height: 100%; backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.45);">
                                    <div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                            <?php echo get_category_badge($rec['category']); ?>
                                            <span class="badge badge-info" style="font-size: 10px; border-radius: 6px;">MATCH</span>
                                        </div>
                                        <div style="display: flex; gap: 12px; margin-bottom: 10px;">
                                            <div style="flex: 1; border-right: 1px dashed rgba(255, 255, 255, 0.1); padding-right: 10px;">
                                                <div style="font-size: 10.5px; text-transform: uppercase; color: #ef4444; font-weight: 800;">Reported Lost</div>
                                                <div style="font-size: 13.5px; font-weight: 700; color: #fff;"><?php echo htmlspecialchars($rec['lost_title']); ?></div>
                                                <div style="font-size: 11.5px; color: #94a3b8;"><i class="fa fa-map-marker"></i> <?php echo htmlspecialchars($rec['lost_location']); ?></div>
                                                <div style="font-size: 11px; color: #64748b;">By: <?php echo htmlspecialchars($rec['lost_author']); ?></div>
                                            </div>
                                            <div style="flex: 1; padding-left: 2px;">
                                                <div style="font-size: 10.5px; text-transform: uppercase; color: #10b981; font-weight: 800;">Found &amp; Available</div>
                                                <div style="font-size: 13.5px; font-weight: 700; color: #fff;"><?php echo htmlspecialchars($rec['found_title']); ?></div>
                                                <div style="font-size: 11.5px; color: #94a3b8;"><i class="fa fa-map-marker"></i> <?php echo htmlspecialchars($rec['found_location']); ?></div>
                                                <div style="font-size: 11px; color: #38bdf8;"><i class="fa fa-check-circle"></i> Handed to Front Desk</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="margin-top: 8px; text-align: right;">
                                        <a href="#item-card-<?php echo $rec['found_id']; ?>" class="btn btn-sm btn-outline-info" style="border-radius: 8px; font-size: 11.5px; font-weight: 600;">
                                            <i class="fa fa-eye"></i> View Found Item
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Navigation & Search Filter Strip -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
                <!-- Type Filter Pills -->
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="lost-found.php" class="lf-filter-pill <?php echo $typeFilter === 'all' ? 'active' : ''; ?>">
                        <i class="fa fa-th-large"></i> All (<?php echo intval($counts['total'] ?? 0); ?>)
                    </a>
                    <a href="lost-found.php?type=lost<?php echo !empty($catFilter) ? '&category=' . urlencode($catFilter) : ''; ?>" class="lf-filter-pill <?php echo $typeFilter === 'lost' ? 'active' : ''; ?>">
                        <i class="fa fa-search text-danger"></i> Lost (<?php echo intval($counts['lost_cnt'] ?? 0); ?>)
                    </a>
                    <a href="lost-found.php?type=found<?php echo !empty($catFilter) ? '&category=' . urlencode($catFilter) : ''; ?>" class="lf-filter-pill <?php echo $typeFilter === 'found' ? 'active' : ''; ?>">
                        <i class="fa fa-hand-grab-o text-success"></i> Found (<?php echo intval($counts['found_cnt'] ?? 0); ?>)
                    </a>
                    <a href="lost-found.php?type=desk<?php echo !empty($catFilter) ? '&category=' . urlencode($catFilter) : ''; ?>" class="lf-filter-pill <?php echo $typeFilter === 'desk' ? 'active' : ''; ?>">
                        <i class="fa fa-building text-info"></i> At Front Desk (<?php echo intval($counts['desk_cnt'] ?? 0); ?>)
                    </a>
                    <a href="lost-found.php?type=resolved<?php echo !empty($catFilter) ? '&category=' . urlencode($catFilter) : ''; ?>" class="lf-filter-pill <?php echo $typeFilter === 'resolved' ? 'active' : ''; ?>">
                        <i class="fa fa-heart text-success"></i> Reunited (<?php echo intval($counts['resolved_cnt'] ?? 0); ?>)
                    </a>
                    <?php if ($userId || $adminId): ?>
                        <a href="lost-found.php?type=my" class="lf-filter-pill <?php echo $typeFilter === 'my' ? 'active' : ''; ?>" style="border-color: #f59e0b;">
                            <i class="fa fa-user text-warning"></i> My Posts
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Search Form -->
                <form method="get" style="display: flex; gap: 8px;">
                    <?php if ($typeFilter !== 'all'): ?>
                        <input type="hidden" name="type" value="<?php echo htmlspecialchars($typeFilter); ?>">
                    <?php endif; ?>
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="Search item or location..." value="<?php echo htmlspecialchars($searchQuery); ?>" style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.15); width: 220px;">
                    <button type="submit" class="btn btn-sm btn-primary" style="border-radius: 10px;"><i class="fa fa-search"></i></button>
                </form>
            </div>

            <!-- Category Pills Bar -->
            <div style="display: flex; gap: 8px; margin-bottom: 25px; overflow-x: auto; padding-bottom: 6px;">
                <a href="lost-found.php?type=<?php echo urlencode($typeFilter); ?>" class="badge <?php echo empty($catFilter) ? 'badge-primary' : 'badge-secondary'; ?>" style="font-size: 11.5px; padding: 6px 12px; border-radius: 8px; text-decoration: none;">
                    All Categories
                </a>
                <?php foreach (get_lost_found_categories() as $cKey => $cVal): ?>
                    <a href="lost-found.php?type=<?php echo urlencode($typeFilter); ?>&category=<?php echo urlencode($cKey); ?>" class="badge" style="font-size: 11.5px; padding: 6px 12px; border-radius: 8px; text-decoration: none; background: <?php echo ($catFilter === $cKey) ? $cVal['color'] : $cVal['bg']; ?>; color: <?php echo ($catFilter === $cKey) ? '#000' : $cVal['color']; ?>; border: 1px solid <?php echo $cVal['color']; ?>44;">
                        <i class="fa <?php echo $cVal['icon']; ?>"></i> <?php echo htmlspecialchars($cVal['label']); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Item Cards Grid -->
            <div class="row">
                <?php if (empty($items)): ?>
                    <div class="col-12">
                        <div class="tile text-center" style="border-radius: 18px; padding: 50px 20px; background: rgba(15, 23, 42, 0.65); border: 1px dashed rgba(255, 255, 255, 0.08); backdrop-filter: blur(24px); box-shadow: 0 10px 25px rgba(0, 0, 0, 0.45);">
                            <div style="width: 60px; height: 60px; border-radius: 50%; background: rgba(56, 189, 248, 0.15); color: #38bdf8; display: inline-flex; align-items: center; justify-content: center; font-size: 26px; margin-bottom: 14px;">
                                <i class="fa fa-search"></i>
                            </div>
                            <h4 style="color: #fff; font-weight: 700;">No items found</h4>
                            <p style="color: #94a3b8; font-size: 14px; max-width: 450px; margin: 0 auto 18px;">
                                No lost or found items match your current filter. Try selecting "All" or create a new post if you've misplaced something!
                            </p>
                            <button type="button" class="btn btn-primary" onclick="openCreateModal('lost')" style="border-radius: 10px; font-weight: 600;">
                                <i class="fa fa-plus-circle"></i> Create a Post
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($items as $it): 
                        $canManage = can_manage_lost_found_item($it, $userId, $adminId, $adminRole);
                        $catInfo = get_category_info($it['category']);
                    ?>
                        <div class="col-md-6 col-lg-4 mb-4" id="item-card-<?php echo $it['id']; ?>">
                            <div class="lf-card">
                                <!-- Card Image Header -->
                                <div class="lf-card-img">
                                    <?php if (!empty($it['image_path']) && file_exists(__DIR__ . '/' . $it['image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($it['image_path']); ?>" alt="<?php echo htmlspecialchars($it['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="text-align: center; color: <?php echo $catInfo['color']; ?>;">
                                            <i class="fa <?php echo $catInfo['icon']; ?>" style="font-size: 54px; opacity: 0.7;"></i>
                                            <div style="font-size: 11px; color: #94a3b8; margin-top: 6px;">Photo not provided</div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Ribbon -->
                                    <div class="lf-type-ribbon <?php echo $it['item_type'] === 'lost' ? 'lf-type-lost' : 'lf-type-found'; ?>">
                                        <i class="fa <?php echo $it['item_type'] === 'lost' ? 'fa-search' : 'fa-hand-grab-o'; ?>"></i>
                                        <?php echo strtoupper($it['item_type']); ?>
                                    </div>
                                </div>

                                <!-- Card Body -->
                                <div style="padding: 18px; flex: 1; display: flex; flex-direction: column; justify-content: space-between;">
                                    <div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                            <?php echo get_category_badge($it['category']); ?>
                                            <?php echo get_item_status_badge($it['status'], $it['item_type']); ?>
                                        </div>

                                        <h4 style="font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 6px; line-height: 1.3;">
                                            <?php echo htmlspecialchars($it['title']); ?>
                                        </h4>

                                        <div style="font-size: 12.5px; color: #94a3b8; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                                            <i class="fa fa-map-marker text-danger"></i>
                                            <span><?php echo htmlspecialchars($it['location_in_gym']); ?></span>
                                        </div>

                                        <p style="font-size: 13px; color: #cbd5e1; line-height: 1.45; margin-bottom: 12px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                            <?php echo nl2br(htmlspecialchars($it['caption'])); ?>
                                        </p>

                                        <?php if (!empty($it['reward_notes'])): ?>
                                            <div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 8px; padding: 6px 10px; font-size: 11.5px; color: #fbbf24; margin-bottom: 12px;">
                                                <i class="fa fa-gift"></i> <?php echo htmlspecialchars($it['reward_notes']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div>
                                        <!-- Author / Date Strip -->
                                        <div style="border-top: 1px solid rgba(255, 255, 255, 0.08); padding-top: 10px; margin-top: 10px; display: flex; justify-content: space-between; align-items: center; font-size: 11.5px; color: #94a3b8;">
                                            <div>
                                                <i class="fa fa-user"></i> <?php echo htmlspecialchars($it['author_name']); ?>
                                                <?php if ($it['author_type'] === 'admin'): ?>
                                                    <span class="badge badge-primary" style="font-size: 9px; margin-left: 2px;">STAFF</span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <i class="fa fa-calendar-o"></i> <?php echo date('d M Y', strtotime($it['date_reported'])); ?>
                                            </div>
                                        </div>

                                        <!-- Card Action Buttons -->
                                        <div style="display: flex; gap: 8px; margin-top: 12px;">
                                            <?php if ($it['status'] === 'handed_to_desk'): ?>
                                                <button type="button" class="btn btn-sm btn-info btn-block" onclick="openClaimModal(<?php echo htmlspecialchars(json_encode($it), ENT_QUOTES); ?>)" style="border-radius: 8px; font-weight: 600;">
                                                    <i class="fa fa-building"></i> Claim at Front Desk
                                                </button>
                                            <?php elseif ($canManage): ?>
                                                <button type="button" class="btn btn-sm btn-outline-info" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($it), ENT_QUOTES); ?>)" style="border-radius: 8px; font-weight: 600; flex: 1;">
                                                    <i class="fa fa-pencil"></i> Edit Post
                                                </button>
                                                <?php if ($it['status'] !== 'returned'): ?>
                                                    <form method="post" style="display: inline; flex: 1;" onsubmit="return confirm('Mark this item as returned / reunited?');">
                                                        <?php echo csrf_input(); ?>
                                                        <input type="hidden" name="action" value="quick_status">
                                                        <input type="hidden" name="item_id" value="<?php echo $it['id']; ?>">
                                                        <input type="hidden" name="new_status" value="returned">
                                                        <button type="submit" class="btn btn-sm btn-success btn-block" style="border-radius: 8px; font-weight: 600;">
                                                            <i class="fa fa-check"></i> Reunited
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-light btn-block" onclick="openClaimModal(<?php echo htmlspecialchars(json_encode($it), ENT_QUOTES); ?>)" style="border-radius: 8px; font-weight: 600;">
                                                    <i class="fa fa-comment-o"></i> Contact / Claim
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- MODAL 1: CREATE ITEM POST -->
    <div class="modal fade" id="createItemModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border-radius: 20px; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); color: #fff;">
                <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
                    <h5 class="modal-title" id="createModalTitle">
                        <i class="fa fa-plus-circle text-primary"></i> Create Lost &amp; Found Post
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form method="post" enctype="multipart/form-data">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="create_item">
                    <input type="hidden" name="item_type" id="createItemType" value="lost">

                    <div class="modal-body" style="padding: 20px 24px;">
                        <div class="row">
                            <div class="col-sm-6 form-group">
                                <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Listing Type *</label>
                                <select class="form-control" id="modalTypeSelector" onchange="toggleTypeFields(this.value)" style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);">
                                    <option value="lost">Lost Item (I misplaced something)</option>
                                    <option value="found">Found Item (I discovered an item)</option>
                                </select>
                            </div>
                            <div class="col-sm-6 form-group">
                                <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Category *</label>
                                <select name="category" class="form-control" required style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);">
                                    <?php foreach (get_lost_found_categories() as $ck => $cv): ?>
                                        <option value="<?php echo $ck; ?>"><?php echo htmlspecialchars($cv['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Item Name / Headline *</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Apple AirPods Pro (White case with scratch)" required style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);">
                        </div>

                        <div class="row">
                            <div class="col-sm-6 form-group">
                                <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Location in Gym *</label>
                                <input type="text" name="location_in_gym" class="form-control" placeholder="e.g. Cardio Zone / Treadmill #4, Men's Locker Room" required style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);">
                            </div>
                            <div class="col-sm-6 form-group">
                                <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Date Misplaced / Found *</label>
                                <input type="date" name="date_reported" class="form-control" value="<?php echo date('Y-m-d'); ?>" required style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);">
                            </div>
                        </div>

                        <div class="form-group">
                            <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Detailed Caption &amp; Description *</label>
                            <textarea name="caption" rows="3" class="form-control" placeholder="Describe the item, color, distinctive marks, brand, or circumstances so it can be verified easily." required style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-sm-6 form-group">
                                <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Attach Photo (Optional)</label>
                                <input type="file" name="item_image" accept="image/*" class="form-control-file" style="color: #cbd5e1; font-size: 12.5px;">
                                <small class="text-muted">JPG, PNG, WEBP up to 5MB</small>
                            </div>
                            <div class="col-sm-6 form-group">
                                <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Contact Phone (For verification)</label>
                                <input type="text" name="contact_phone" class="form-control" placeholder="+91 9876543210" style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);">
                            </div>
                        </div>

                        <!-- Specific to Lost Items -->
                        <div id="lostExtraField" class="form-group">
                            <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Reward / Special Note (Optional)</label>
                            <input type="text" name="reward_notes" class="form-control" placeholder="e.g. Cash reward for return! Sentimental value." style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);">
                        </div>

                        <!-- Specific to Found Items -->
                        <div id="foundExtraField" class="form-group" style="display: none;">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" name="at_front_desk" value="1" class="custom-control-input" id="checkFrontDesk">
                                <label class="custom-control-label" for="checkFrontDesk" style="font-size: 13px; color: #38bdf8; font-weight: 600;">
                                    I have already deposited this item at the Front Desk Reception
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid rgba(255, 255, 255, 0.08);">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="border-radius: 8px; font-weight: 700;">
                            <i class="fa fa-save"></i> Publish Post
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 2: EDIT ITEM POST -->
    <div class="modal fade" id="editItemModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border-radius: 20px; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); color: #fff;">
                <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
                    <h5 class="modal-title"><i class="fa fa-pencil text-info"></i> Update Item Post</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form method="post" enctype="multipart/form-data">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="update_item">
                    <input type="hidden" name="item_id" id="editItemId">

                    <div class="modal-body" style="padding: 20px 24px;">
                        <div class="row">
                            <div class="col-sm-6 form-group">
                                <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Status</label>
                                <select name="status" id="editItemStatus" class="form-control" style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);">
                                    <option value="open">Active / Searching</option>
                                    <option value="handed_to_desk">Handed Over to Front Desk</option>
                                    <option value="claimed">Claim Verification Pending</option>
                                    <option value="returned">Reunited &amp; Returned to Owner</option>
                                </select>
                            </div>
                            <div class="col-sm-6 form-group">
                                <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Category</label>
                                <select name="category" id="editItemCategory" class="form-control" style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);">
                                    <?php foreach (get_lost_found_categories() as $ck => $cv): ?>
                                        <option value="<?php echo $ck; ?>"><?php echo htmlspecialchars($cv['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Title</label>
                            <input type="text" name="title" id="editItemTitle" class="form-control" required style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);">
                        </div>

                        <div class="form-group">
                            <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Location in Gym</label>
                            <input type="text" name="location_in_gym" id="editItemLocation" class="form-control" required style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);">
                        </div>

                        <div class="form-group">
                            <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Caption &amp; Description</label>
                            <textarea name="caption" id="editItemCaption" rows="3" class="form-control" required style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-sm-6 form-group">
                                <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Replace Photo (Optional)</label>
                                <input type="file" name="item_image" accept="image/*" class="form-control-file" style="color: #cbd5e1; font-size: 12.5px;">
                            </div>
                            <div class="col-sm-6 form-group">
                                <label style="font-size: 13px; font-weight: 600; color: #cbd5e1;">Reward / Notes</label>
                                <input type="text" name="reward_notes" id="editItemReward" class="form-control" style="border-radius: 10px; background: rgba(30, 41, 59, 0.8); color: #fff; border-color: rgba(255, 255, 255, 0.2);">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid rgba(255, 255, 255, 0.08);">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="border-radius: 8px; font-weight: 700;">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 3: CLAIM & CONTACT DETAILS -->
    <div class="modal fade" id="claimModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); color: #fff;">
                <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
                    <h5 class="modal-title"><i class="fa fa-info-circle text-info"></i> How to Claim This Item</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body" style="padding: 22px;">
                    <h4 id="claimItemTitle" style="color: #fff; font-size: 17px; font-weight: 700; margin-bottom: 6px;"></h4>
                    <div id="claimDeskNotice" class="alert alert-info" style="border-radius: 12px; background: rgba(56, 189, 248, 0.15); border: 1px solid #38bdf8; color: #fff; display: none;">
                        <i class="fa fa-building"></i> <strong>Stored at Front Desk Reception:</strong> This item was handed in for safekeeping. Visit the front desk and verify identifying details to claim it.
                    </div>
                    <p style="font-size: 13.5px; color: #cbd5e1;" id="claimDescription"></p>

                    <div style="background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 14px; margin-top: 14px;">
                        <div style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Reported By</div>
                        <div style="font-size: 14px; font-weight: 700; color: #fff; margin-top: 2px;" id="claimAuthorName"></div>
                        <div style="font-size: 13px; color: #38bdf8; margin-top: 4px;" id="claimContactPhone"></div>
                        <div style="font-size: 12.5px; color: #94a3b8;" id="claimContactEmail"></div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(255, 255, 255, 0.08);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 8px;">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="js/vendor/jquery-2.2.4.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        function openCreateModal(type) {
            document.getElementById('createItemType').value = type;
            document.getElementById('modalTypeSelector').value = type;
            toggleTypeFields(type);
            $('#createItemModal').modal('show');
        }

        function toggleTypeFields(val) {
            document.getElementById('createItemType').value = val;
            const lostField = document.getElementById('lostExtraField');
            const foundField = document.getElementById('foundExtraField');
            const title = document.getElementById('createModalTitle');
            if (val === 'found') {
                lostField.style.display = 'none';
                foundField.style.display = 'block';
                title.innerHTML = '<i class="fa fa-hand-grab-o text-success"></i> Report a Found Item';
            } else {
                lostField.style.display = 'block';
                foundField.style.display = 'none';
                title.innerHTML = '<i class="fa fa-search text-danger"></i> Report a Lost Item';
            }
        }

        function openEditModal(it) {
            document.getElementById('editItemId').value = it.id;
            document.getElementById('editItemTitle').value = it.title;
            document.getElementById('editItemCategory').value = it.category;
            document.getElementById('editItemLocation').value = it.location_in_gym;
            document.getElementById('editItemCaption').value = it.caption;
            document.getElementById('editItemStatus').value = it.status;
            document.getElementById('editItemReward').value = it.reward_notes || '';
            $('#editItemModal').modal('show');
        }

        function openClaimModal(it) {
            document.getElementById('claimItemTitle').textContent = it.title;
            document.getElementById('claimDescription').textContent = it.caption;
            document.getElementById('claimAuthorName').textContent = it.author_name;
            
            const deskNotice = document.getElementById('claimDeskNotice');
            if (it.status === 'handed_to_desk') {
                deskNotice.style.display = 'block';
            } else {
                deskNotice.style.display = 'none';
            }

            const phoneEl = document.getElementById('claimContactPhone');
            if (it.contact_phone) {
                phoneEl.innerHTML = '<i class="fa fa-phone"></i> ' + it.contact_phone;
                phoneEl.style.display = 'block';
            } else {
                phoneEl.style.display = 'none';
            }

            const emailEl = document.getElementById('claimContactEmail');
            if (it.contact_email) {
                emailEl.innerHTML = '<i class="fa fa-envelope"></i> ' + it.contact_email;
                emailEl.style.display = 'block';
            } else {
                emailEl.style.display = 'none';
            }

            $('#claimModal').modal('show');
        }
    </script>
</body>
</html>
