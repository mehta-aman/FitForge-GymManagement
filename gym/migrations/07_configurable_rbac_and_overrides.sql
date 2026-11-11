-- Migration 07: Configurable RBAC, Permissions Catalog, and Individual Employee Overrides
-- Applied to gymdb

-- 1. Upgrade tbladmin.role to support configurable role keys
ALTER TABLE `tbladmin`
  MODIFY COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'floor_staff';

-- 2. Configurable Roles Catalog
CREATE TABLE IF NOT EXISTS `tbl_roles` (
  `role_key` VARCHAR(50) NOT NULL,
  `role_name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL,
  `is_system_role` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Granular Permissions Catalog
CREATE TABLE IF NOT EXISTS `tbl_permissions` (
  `permission_key` VARCHAR(60) NOT NULL,
  `category` VARCHAR(50) NOT NULL DEFAULT 'general',
  `label` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL,
  `is_owner_only` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`permission_key`),
  KEY `idx_perm_category` (`category`),
  KEY `idx_perm_owner_only` (`is_owner_only`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Role-to-Permission Standard Mapping
CREATE TABLE IF NOT EXISTS `tbl_role_permissions` (
  `role_key` VARCHAR(50) NOT NULL,
  `permission_key` VARCHAR(60) NOT NULL,
  PRIMARY KEY (`role_key`, `permission_key`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_key`) REFERENCES `tbl_roles` (`role_key`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_key`) REFERENCES `tbl_permissions` (`permission_key`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Individual Employee Permission Overrides (Explicit Grant = 1, Explicit Deny = 0)
CREATE TABLE IF NOT EXISTS `tbl_admin_permissions` (
  `admin_id` INT NOT NULL,
  `permission_key` VARCHAR(60) NOT NULL,
  `is_granted` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = explicit grant override, 0 = explicit deny override',
  `granted_by_admin_id` INT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`admin_id`, `permission_key`),
  CONSTRAINT `fk_ap_admin` FOREIGN KEY (`admin_id`) REFERENCES `tbladmin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ap_perm` FOREIGN KEY (`permission_key`) REFERENCES `tbl_permissions` (`permission_key`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ap_granter` FOREIGN KEY (`granted_by_admin_id`) REFERENCES `tbladmin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Seed System Roles
INSERT INTO `tbl_roles` (`role_key`, `role_name`, `description`, `is_system_role`) VALUES
('owner', 'Gym Owner', 'Full system access & exclusive financial authority (non-transferable, immutable)', 1),
('manager', 'Operations Manager', 'Operational gym management & authorized case-by-case refund reviews (Restricted financial access)', 1),
('trainer', 'Fitness Coach', 'Client coaching, workout presets, measurements and attendance logging', 1),
('floor_staff', 'Front Desk / Floor Staff', 'Front desk check-ins, member lookups, QR station and desk operations', 1)
ON DUPLICATE KEY UPDATE `role_name` = VALUES(`role_name`), `description` = VALUES(`description`);

-- 7. Seed Granular Permissions Catalog
INSERT INTO `tbl_permissions` (`permission_key`, `category`, `label`, `description`, `is_owner_only`) VALUES
-- Financial Permissions (MANDATORY OWNER-ONLY: Can NEVER be granted to non-owner)
('financial.dashboard', 'financial', 'Financial KPI Dashboard', 'Access gross collection totals, dues widgets, and monthly growth charts', 1),
('financial.reports', 'financial', 'Financial & Revenue Reports', 'Generate and view gym-wide payment reports and date-range collections', 1),
('financial.ledger', 'financial', 'Payment Ledger & History', 'Browse global member payment transactions, booking histories, and receipts', 1),
('financial.dues', 'financial', 'Outstanding Dues Report', 'View club-wide member payment balances and pending receivables', 1),
('financial.receipts_general', 'financial', 'General Receipt Access', 'Access and download billing receipts across arbitrary members', 1),
('financial.exports', 'financial', 'Financial Data Exports', 'Export payment records and accounting ledgers to CSV or external files', 1),
('view_financials', 'financial', 'General Financial View', 'Legacy alias for accessing general financial records', 1),
('manage_bookings', 'financial', 'Manage Booking Transactions', 'Access admin bookings hub and record payments', 1),
('payments.record', 'financial', 'Record Member Payments', 'Collect and log cash/card/UPI installment payments on bookings', 1),
('manage_settings', 'administration', 'Gym & Branding Settings', 'Configure core gym settings, branding, themes, and global preferences', 1),
('manage_staff', 'administration', 'Staff & Permissions Management', 'Approve applicants, adjust roles, and manage employee permission overrides', 1),

-- Membership Management
('manage_plans', 'memberships', 'Manage Membership Plans', 'Create, update, and manage membership pricing tiers and packages', 0),
('manage_categories', 'memberships', 'Manage Categories', 'Create and modify plan categories and workout categories', 0),
('manage_members', 'memberships', 'Manage Member Accounts', 'Register new members, edit contact info, and update member profiles', 0),
('view_members', 'memberships', 'View Member Directory', 'Look up member contact info, active passes, and basic account details', 0),
('manage_freezes', 'memberships', 'Manage Membership Freezes', 'Put memberships on medical/vacation freeze and unfreeze with expiry extension', 0),
('manage_financial_policies', 'memberships', 'Configure Initial Payment Policy', 'Adjust the gym-wide minimum initial payment percentage/amount threshold', 0),

-- Refund Governance (Case-Specific Refund Operations)
('refunds.view_assigned', 'refunds', 'View Refund Cases Queue', 'View pending refund applications submitted within the gym', 0),
('refunds.request', 'refunds', 'Submit Refund Requests', 'Submit a refund request on behalf of a member for owner/manager review', 0),
('refunds.approve', 'refunds', 'Approve Refund Cases', 'Approve submitted refund requests under approved refund policy', 0),
('refunds.reject', 'refunds', 'Reject Refund Cases', 'Reject refund requests with documented operational reason', 0),
('refunds.process', 'refunds', 'Execute & Disburse Refunds', 'Execute authorized refunds and update membership refundable balance', 0),

-- Coaching & Operations
('view_member_profile', 'operations', 'View Coaching Profile', 'View member health profile, measurements, and training history', 0),
('manage_routines', 'operations', 'Manage Workout Routines', 'Assign and update workout presets and exercise programs for members', 0),
('manage_measurements', 'operations', 'Manage Body Measurements', 'Record and track member weight, waist, body fat, and circumferences', 0),

-- Attendance & Access
('manage_attendance', 'attendance', 'Manage Check-Ins', 'Perform manual desk check-ins and check-outs for gym members', 0),
('view_attendance', 'attendance', 'View Attendance Logs', 'View floor headcount and daily attendance logs', 0),
('view_qr_poster', 'attendance', 'Access Gym QR Poster', 'Access and print the gym fast QR attendance station poster', 0)
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`), `description` = VALUES(`description`), `is_owner_only` = VALUES(`is_owner_only`);

-- 8. Seed Default Role Permissions
-- Owner has all permissions (built into core system logic)
-- Manager standard permissions (operational management + refund processing, NO general financial access)
INSERT INTO `tbl_role_permissions` (`role_key`, `permission_key`) VALUES
('manager', 'manage_plans'),
('manager', 'manage_categories'),
('manager', 'manage_members'),
('manager', 'view_members'),
('manager', 'manage_freezes'),
('manager', 'manage_financial_policies'),
('manager', 'refunds.view_assigned'),
('manager', 'refunds.request'),
('manager', 'refunds.approve'),
('manager', 'refunds.reject'),
('manager', 'refunds.process'),
('manager', 'view_member_profile'),
('manager', 'manage_routines'),
('manager', 'manage_measurements'),
('manager', 'manage_attendance'),
('manager', 'view_attendance'),
('manager', 'view_qr_poster'),

-- Trainer standard permissions
('trainer', 'view_members'),
('trainer', 'view_member_profile'),
('trainer', 'manage_routines'),
('trainer', 'manage_measurements'),
('trainer', 'manage_attendance'),
('trainer', 'view_attendance'),
('trainer', 'view_qr_poster'),
('trainer', 'refunds.request'),

-- Floor Staff standard permissions
('floor_staff', 'manage_members'),
('floor_staff', 'view_members'),
('floor_staff', 'manage_attendance'),
('floor_staff', 'view_attendance'),
('floor_staff', 'view_qr_poster'),
('floor_staff', 'refunds.request')
ON DUPLICATE KEY UPDATE `role_key` = VALUES(`role_key`);
