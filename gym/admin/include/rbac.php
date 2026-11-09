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

/**
 * Checks if current user possesses permission for a capability.
 */
function has_permission(string $permission): bool {
    $role = current_admin_role();
    if ($role === 'owner') return true; // Owner has all permissions

    $matrix = [
        'manage_settings'     => ['owner'],
        'manage_staff'        => ['owner'],
        'approve_staff'       => ['owner'],
        'view_financials'     => ['owner', 'manager'],
        'manage_bookings'     => ['owner', 'manager'],
        'manage_plans'        => ['owner', 'manager'],
        'manage_categories'   => ['owner', 'manager'],
        'manage_members'      => ['owner', 'manager'],
        'view_member_profile' => ['owner', 'manager', 'trainer'],
        'manage_routines'     => ['owner', 'manager', 'trainer'],
        'manage_measurements' => ['owner', 'manager', 'trainer'],
        'manage_attendance'   => ['owner', 'manager', 'trainer', 'floor_staff'],
        'view_attendance'     => ['owner', 'manager', 'trainer', 'floor_staff'],
        'view_qr_poster'      => ['owner', 'manager', 'trainer', 'floor_staff'],
    ];

    if (!isset($matrix[$permission])) return false;
    return in_array($role, $matrix[$permission], true);
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
