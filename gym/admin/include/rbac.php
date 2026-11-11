<?php
/**
 * Role-Based Access Control (RBAC) & Staff Authorization Engine
 * FitForge Gym Management System
 */

if (!defined('RBAC_ROLES')) {
    define('RBAC_ROLES', ['owner', 'manager', 'trainer', 'floor_staff']);
}

/**
 * Returns the current authenticated admin's role.
 */
function current_admin_role(): string {
    return $_SESSION['admin_role'] ?? 'guest';
}

/**
 * Checks if current admin is the Gym Owner.
 */
function is_owner(): bool {
    return current_admin_role() === 'owner';
}

/**
 * Checks if current admin is an Operations Manager (or Owner).
 */
function is_manager(): bool {
    return in_array(current_admin_role(), ['owner', 'manager'], true);
}

/**
 * Checks if current admin is a Coach / Trainer.
 */
function is_trainer(): bool {
    return current_admin_role() === 'trainer';
}

/**
 * Checks if current admin is Floor Staff / Front Desk.
 */
function is_floor_staff(): bool {
    return current_admin_role() === 'floor_staff';
}

/**
 * Human-readable label for roles.
 */
function get_role_label(string $role): string {
    return match ($role) {
        'owner' => 'Gym Owner',
        'manager' => 'Operations Manager',
        'trainer' => 'Fitness Coach',
        'floor_staff' => 'Front Desk / Floor',
        default => ucfirst(str_replace('_', ' ', $role))
    };
}

/**
 * Render a styled HTML badge for an employee role.
 */
function get_role_badge(string $role): string {
    $map = [
        'owner' => ['bg' => 'rgba(245, 158, 11, 0.2)', 'border' => '#f59e0b', 'color' => '#fbbf24', 'icon' => 'fa-crown', 'label' => 'Gym Owner'],
        'manager' => ['bg' => 'rgba(59, 130, 246, 0.2)', 'border' => '#3b82f6', 'color' => '#60a5fa', 'icon' => 'fa-briefcase', 'label' => 'Manager'],
        'trainer' => ['bg' => 'rgba(16, 185, 129, 0.2)', 'border' => '#10b981', 'color' => '#34d399', 'icon' => 'fa-heartbeat', 'label' => 'Trainer'],
        'floor_staff' => ['bg' => 'rgba(139, 92, 246, 0.2)', 'border' => '#8b5cf6', 'color' => '#a78bfa', 'icon' => 'fa-id-badge', 'label' => 'Floor Staff'],
    ];

    $cfg = $map[$role] ?? ['bg' => 'rgba(100, 116, 139, 0.2)', 'border' => '#64748b', 'color' => '#cbd5e1', 'icon' => 'fa-user', 'label' => ucfirst($role)];
    return '<span style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 8px; font-size: 11.5px; font-weight: 700; background: ' . $cfg['bg'] . '; border: 1px solid ' . $cfg['border'] . '; color: ' . $cfg['color'] . ';">' .
           '<i class="fa ' . $cfg['icon'] . '"></i> ' . htmlspecialchars($cfg['label']) . '</span>';
}

/**
 * Render a styled HTML badge for an employee approval status.
 */
function get_status_badge(string $status): string {
    return match ($status) {
        'approved' => '<span class="badge badge-success" style="font-size: 11px; padding: 5px 10px; border-radius: 6px;"><i class="fa fa-check-circle"></i> Active / Approved</span>',
        'pending' => '<span class="badge badge-warning" style="font-size: 11px; padding: 5px 10px; border-radius: 6px; color: #000;"><i class="fa fa-clock-o"></i> Pending Owner Approval</span>',
        'rejected' => '<span class="badge badge-danger" style="font-size: 11px; padding: 5px 10px; border-radius: 6px;"><i class="fa fa-times-circle"></i> Rejected</span>',
        'suspended' => '<span class="badge badge-secondary" style="font-size: 11px; padding: 5px 10px; border-radius: 6px;"><i class="fa fa-ban"></i> Suspended</span>',
        default => '<span class="badge badge-info">' . htmlspecialchars(ucfirst($status)) . '</span>'
    };
}

// Mandatory Owner-Only Financial Boundary (Option A: Restricted Manager Financial Access)
if (!defined('OWNER_ONLY_FINANCIAL_PERMISSIONS')) {
    define('OWNER_ONLY_FINANCIAL_PERMISSIONS', [
        'financial.dashboard',
        'financial.reports',
        'financial.ledger',
        'financial.dues',
        'financial.receipts_general',
        'financial.exports',
        'view_financials',
        'manage_bookings',
        'manage_settings',
        'manage_staff',
    ]);
}

if (!defined('REFUND_PERMISSIONS')) {
    define('REFUND_PERMISSIONS', [
        'refunds.view_assigned',
        'refunds.request',
        'refunds.approve',
        'refunds.reject',
        'refunds.process',
        'request_refund',
        'approve_refund',
        'execute_refund',
    ]);
}

/**
 * Checks if a specific role possesses permission for a capability.
 * Enforces strict Owner-Only boundary for general financial access.
 */
function role_has_permission(string $permission, string $role): bool {
    // 1. Mandatory Owner-Only Financial Boundary:
    // General financial access can NEVER be granted to any non-Owner role.
    if (in_array($permission, OWNER_ONLY_FINANCIAL_PERMISSIONS, true)) {
        return $role === 'owner';
    }

    if ($role === 'owner') return true; // Owner has all permissions

    $matrix = [
        'manage_financial_policies' => ['owner', 'manager'],
        'manage_plans'              => ['owner', 'manager'],
        'manage_categories'         => ['owner', 'manager'],
        'manage_members'            => ['owner', 'manager', 'floor_staff'],
        'view_members'              => ['owner', 'manager', 'trainer', 'floor_staff'],
        'manage_freezes'            => ['owner', 'manager'],
        
        // Granular Refund Permissions (Case-specific workflow only)
        'refunds.view_assigned'     => ['owner', 'manager'],
        'refunds.request'           => ['owner', 'manager', 'trainer', 'floor_staff'],
        'refunds.approve'           => ['owner', 'manager'],
        'refunds.reject'            => ['owner', 'manager'],
        'refunds.process'           => ['owner', 'manager'],
        'request_refund'            => ['owner', 'manager', 'trainer', 'floor_staff'],
        'approve_refund'            => ['owner', 'manager'],
        'execute_refund'            => ['owner', 'manager'],

        // Operational Coaching & Member Profile
        'view_member_profile'       => ['owner', 'manager', 'trainer'],
        'manage_routines'           => ['owner', 'manager', 'trainer'],
        'manage_measurements'       => ['owner', 'manager', 'trainer'],

        // Attendance & Station
        'manage_attendance'         => ['owner', 'manager', 'trainer', 'floor_staff'],
        'view_attendance'           => ['owner', 'manager', 'trainer', 'floor_staff'],
        'view_qr_poster'            => ['owner', 'manager', 'trainer', 'floor_staff'],
    ];

    if (!isset($matrix[$permission])) return false;
    return in_array($role, $matrix[$permission], true);
}

/**
 * Checks if a specific admin possesses permission for a capability.
 * Evaluates:
 * 1. Mandatory Owner-Only Financial Boundary (Strict Option A)
 * 2. Owner Immutability (Owner always has full permissions)
 * 3. Individual Employee Overrides (tbl_admin_permissions)
 *    - Explicit Deny (0) takes absolute precedence
 *    - Explicit Grant (1) overrides role default
 * 4. Database Role Permissions (tbl_role_permissions)
 * 5. Fallback Role Matrix
 */
function admin_has_permission(int $adminId, string $permission, ?PDO $dbh = null): bool {
    global $dbh;
    $db = $dbh ?? ($GLOBALS['dbh'] ?? null);

    // Determine target admin role
    $isAdminOwner = false;
    $targetRole = null;
    if ($adminId === intval($_SESSION['adminid'] ?? 0)) {
        $targetRole = current_admin_role();
        $isAdminOwner = is_owner();
    } elseif ($db) {
        try {
            $rStmt = $db->prepare("SELECT role FROM tbladmin WHERE id = ?");
            $rStmt->execute([$adminId]);
            $targetRole = $rStmt->fetchColumn() ?: 'floor_staff';
            $isAdminOwner = ($targetRole === 'owner');
        } catch (Exception $e) {
            $targetRole = 'floor_staff';
        }
    }

    // 1. Mandatory Owner-Only Financial Boundary: Non-owners can NEVER possess financial permissions
    if (in_array($permission, OWNER_ONLY_FINANCIAL_PERMISSIONS, true)) {
        return $isAdminOwner;
    }

    // 2. Owner Authority is protected and immutable
    if ($isAdminOwner) {
        return true;
    }

    // 3. Check Individual Overrides from database if available
    if ($db) {
        try {
            $stmt = $db->prepare("SELECT is_granted FROM tbl_admin_permissions WHERE admin_id = ? AND permission_key = ?");
            $stmt->execute([$adminId, $permission]);
            $override = $stmt->fetchColumn();
            if ($override !== false && $override !== null) {
                // Explicit deny (0) takes precedence; explicit grant (1) overrides role default
                return (intval($override) === 1);
            }

            // 4. Check tbl_role_permissions
            if ($targetRole) {
                $rpStmt = $db->prepare("SELECT COUNT(*) FROM tbl_role_permissions WHERE role_key = ? AND permission_key = ?");
                $rpStmt->execute([$targetRole, $permission]);
                if (intval($rpStmt->fetchColumn()) > 0) {
                    return true;
                }
            }
        } catch (Exception $e) {
            // Table might not exist yet or connection error; fall through to matrix
        }
    }

    return role_has_permission($permission, $targetRole ?? current_admin_role());
}

/**
 * Checks if current user possesses permission for a capability.
 */
function has_permission(string $permission): bool {
    $adminId = intval($_SESSION['adminid'] ?? 0);
    if ($adminId > 0) {
        return admin_has_permission($adminId, $permission);
    }
    return role_has_permission($permission, current_admin_role());
}

/**
 * Fetch all permissions catalog grouped or ordered.
 */
function get_all_permissions(PDO $dbh): array {
    $stmt = $dbh->query("SELECT * FROM tbl_permissions ORDER BY FIELD(category, 'financial', 'memberships', 'refunds', 'operations', 'attendance', 'administration'), label ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch all individual permission overrides for a specific admin.
 * Returns array of [ permission_key => is_granted (1 or 0) ]
 */
function get_admin_overrides(int $adminId, PDO $dbh): array {
    $stmt = $dbh->prepare("SELECT permission_key, is_granted FROM tbl_admin_permissions WHERE admin_id = ?");
    $stmt->execute([$adminId]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

/**
 * Set or remove an individual permission override for an employee.
 * Enforces:
 * - Granter must be Gym Owner
 * - Cannot override Owner permissions
 * - Cannot grant OWNER_ONLY_FINANCIAL_PERMISSIONS to non-owners
 * - isGranted = null removes the override (restores role default)
 */
function set_admin_permission_override(int $adminId, string $permission, ?int $isGranted, int $grantedBy, PDO $dbh): bool {
    // 1. Verify granter is Owner
    $gStmt = $dbh->prepare("SELECT role FROM tbladmin WHERE id = ?");
    $gStmt->execute([$grantedBy]);
    if ($gStmt->fetchColumn() !== 'owner') {
        throw new InvalidArgumentException("Only the Gym Owner can modify employee permission overrides.");
    }

    // 2. Target cannot be Owner
    $tStmt = $dbh->prepare("SELECT role FROM tbladmin WHERE id = ?");
    $tStmt->execute([$adminId]);
    $targetRole = $tStmt->fetchColumn();
    if ($targetRole === 'owner') {
        throw new InvalidArgumentException("Owner permissions are immutable and cannot be overridden.");
    }

    // 3. Prohibit granting Owner-Only financial permissions to non-owners
    if ($isGranted === 1 && in_array($permission, OWNER_ONLY_FINANCIAL_PERMISSIONS, true)) {
        throw new InvalidArgumentException("Financial permissions are strictly reserved for the Gym Owner and cannot be granted to other staff.");
    }

    // 4. Remove override if null
    if ($isGranted === null) {
        $del = $dbh->prepare("DELETE FROM tbl_admin_permissions WHERE admin_id = ? AND permission_key = ?");
        return $del->execute([$adminId, $permission]);
    }

    // 5. Upsert override
    $stmt = $dbh->prepare("
        INSERT INTO tbl_admin_permissions (admin_id, permission_key, is_granted, granted_by_admin_id, updated_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE is_granted = VALUES(is_granted), granted_by_admin_id = VALUES(granted_by_admin_id), updated_at = NOW()
    ");
    return $stmt->execute([$adminId, $permission, $isGranted ? 1 : 0, $grantedBy]);
}

/**
 * Checks if current admin can access a specific refund case.
 */
function can_access_refund_case(int $adminId, int $refundRequestId, PDO $dbh): bool {
    if (is_owner()) return true;
    if (!has_permission('refunds.view_assigned') && !has_permission('approve_refund') && !has_permission('execute_refund')) {
        return false;
    }
    $gymId = function_exists('get_current_gym_id') ? get_current_gym_id() : 1;
    $stmt = $dbh->prepare("SELECT id FROM tbl_refund_requests WHERE id = ? AND gym_id = ?");
    $stmt->execute([$refundRequestId, $gymId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Enforce minimum role or redirect with friendly access denied error.
 */
function require_role(array|string $allowedRoles, string $redirect = 'index.php'): void {
    if (empty($_SESSION['adminid'])) {
        header('location:login.php');
        exit();
    }

    $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    $userRole = current_admin_role();

    // Owner always has access
    if ($userRole === 'owner' || in_array($userRole, $roles, true)) {
        return;
    }

    // Role check failed
    $_SESSION['rbac_error'] = "Access Restricted: You are signed in as '" . get_role_label($userRole) . "', which does not have permission to access that section.";
    header("location: $redirect");
    exit();
}

/**
 * Convenience helper to strictly require Gym Owner privilege.
 */
function require_owner(string $redirect = 'index.php'): void {
    require_role(['owner'], $redirect);
}

/**
 * Returns the count of employee accounts waiting for owner review.
 */
function get_pending_staff_count(PDO $dbh): int {
    try {
        $stmt = $dbh->query("SELECT COUNT(*) FROM tbladmin WHERE status = 'pending'");
        return intval($stmt->fetchColumn());
    } catch (Exception $e) {
        return 0;
    }
}
