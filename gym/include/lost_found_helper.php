<?php
/**
 * Lost & Found Helper Functions & Recommendation Engine
 * FitForge Gym Management System
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';

function get_lost_found_categories(): array {
    return [
        'electronics'   => ['label' => 'Electronics & Headphones', 'icon' => 'fa-headphones', 'color' => '#38bdf8', 'bg' => 'rgba(56, 189, 248, 0.15)'],
        'bottles'       => ['label' => 'Water Bottles & Shakers',   'icon' => 'fa-tint',       'color' => '#06b6d4', 'bg' => 'rgba(6, 182, 212, 0.15)'],
        'gym_gear'      => ['label' => 'Gym Gear, Belts & Straps',  'icon' => 'fa-bolt',       'color' => '#f97316', 'bg' => 'rgba(249, 115, 22, 0.15)'],
        'apparel'       => ['label' => 'Apparel, Shoes & Towels',  'icon' => 'fa-tag',        'color' => '#a855f7', 'bg' => 'rgba(168, 85, 247, 0.15)'],
        'keys_wallets'  => ['label' => 'Keys, Wallets & IDs',       'icon' => 'fa-key',        'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.15)'],
        'jewelry'       => ['label' => 'Jewelry & Watches',         'icon' => 'fa-diamond',    'color' => '#fbbf24', 'bg' => 'rgba(251, 191, 36, 0.15)'],
        'other'         => ['label' => 'Other Miscellaneous',       'icon' => 'fa-archive',    'color' => '#94a3b8', 'bg' => 'rgba(148, 163, 184, 0.15)'],
    ];
}

function get_category_info(string $cat): array {
    $cats = get_lost_found_categories();
    return $cats[$cat] ?? $cats['other'];
}

function get_category_badge(string $cat): string {
    $info = get_category_info($cat);
    return '<span style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 700; background: ' . $info['bg'] . '; color: ' . $info['color'] . '; border: 1px solid ' . $info['color'] . '44;">' .
           '<i class="fa ' . $info['icon'] . '"></i> ' . htmlspecialchars($info['label']) . '</span>';
}

function get_item_status_badge(string $status, string $itemType): string {
    return match ($status) {
        'open' => ($itemType === 'lost') 
            ? '<span class="badge badge-danger" style="padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 700;"><i class="fa fa-search"></i> Searching / Lost</span>'
            : '<span class="badge badge-success" style="padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 700;"><i class="fa fa-hand-grab-o"></i> Found / Unclaimed</span>',
        'handed_to_desk' => '<span class="badge badge-info" style="padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; background: #0284c7;"><i class="fa fa-building"></i> At Front Desk</span>',
        'claimed' => '<span class="badge badge-warning" style="padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; color: #000;"><i class="fa fa-check"></i> Claim Pending</span>',
        'returned' => '<span class="badge badge-success" style="padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; background: #10b981;"><i class="fa fa-heart"></i> Reunited &amp; Returned</span>',
        default => '<span class="badge badge-secondary">' . htmlspecialchars(ucfirst($status)) . '</span>'
    };
}

/**
 * Checks if current viewer (user or admin) can edit/update a specific lost & found post.
 */
function can_manage_lost_found_item(array $item, ?int $userId, ?int $adminId, ?string $adminRole): bool {
    // 1. Any staff/admin account can manage/moderate all items
    if (!empty($adminId)) {
        return true;
    }

    // 2. Original member author can manage their own post
    if (!empty($userId) && $item['author_type'] === 'user' && intval($item['author_id']) === $userId) {
        return true;
    }

    return false;
}

/**
 * Smart Recommendation Engine:
 * Pairs active "Lost" items with active "Found" items sharing the same category or title keywords.
 */
function get_lost_found_recommendations(PDO $dbh, int $gymId = 1, ?int $targetItemId = null, int $limit = 6): array {
    if ($targetItemId !== null) {
        // Find recommendations for a single specific item
        $stmt = $dbh->prepare("SELECT * FROM tbl_lost_found WHERE id = ?");
        $stmt->execute([$targetItemId]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$target || in_array($target['status'], ['claimed', 'returned'], true)) {
            return [];
        }

        $oppositeType = ($target['item_type'] === 'lost') ? 'found' : 'lost';
        $category = $target['category'];

        $sql = "
            SELECT * FROM tbl_lost_found
            WHERE gym_id = :gym_id
              AND item_type = :opp_type
              AND status IN ('open', 'handed_to_desk')
              AND category = :category
              AND id != :target_id
            ORDER BY created_at DESC
            LIMIT :lim
        ";
        $recStmt = $dbh->prepare($sql);
        $recStmt->bindValue(':gym_id', $gymId, PDO::PARAM_INT);
        $recStmt->bindValue(':opp_type', $oppositeType, PDO::PARAM_STR);
        $recStmt->bindValue(':category', $category, PDO::PARAM_STR);
        $recStmt->bindValue(':target_id', $targetItemId, PDO::PARAM_INT);
        $recStmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $recStmt->execute();
        return $recStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Gym-wide recommendation matches: pair open lost items with active found items in same category
    $sql = "
        SELECT 
            l.id as lost_id,
            l.title as lost_title,
            l.category,
            l.location_in_gym as lost_location,
            l.date_reported as lost_date,
            l.author_name as lost_author,
            l.image_path as lost_image,
            f.id as found_id,
            f.title as found_title,
            f.location_in_gym as found_location,
            f.date_reported as found_date,
            f.status as found_status,
            f.image_path as found_image,
            f.author_name as found_author
        FROM tbl_lost_found l
        JOIN tbl_lost_found f 
          ON l.category = f.category 
         AND l.gym_id = f.gym_id
        WHERE l.item_type = 'lost' 
          AND f.item_type = 'found'
          AND l.status = 'open'
          AND f.status IN ('open', 'handed_to_desk')
          AND l.gym_id = :gym_id
        ORDER BY f.created_at DESC
        LIMIT :lim
    ";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(':gym_id', $gymId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
