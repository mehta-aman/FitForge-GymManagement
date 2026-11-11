-- FitForge Membership Lifecycle, Cash-Flow Ledger, Access Control & Refund Engine Migration
-- Non-destructive, purely additive schema update

-- 1. Extend tbl_memberships status and payment_status enums
ALTER TABLE `tbl_memberships` 
  MODIFY COLUMN `status` ENUM('active','expired','pending','cancelled','frozen','scheduled') NOT NULL DEFAULT 'active',
  MODIFY COLUMN `payment_status` ENUM('unpaid','partial','paid','partially_refunded','refunded') NOT NULL DEFAULT 'unpaid';

-- 2. Add membership tracking and audit columns to tblpayment
ALTER TABLE `tblpayment`
  ADD COLUMN `membership_id` INT DEFAULT NULL AFTER `bookingID`,
  ADD COLUMN `refunded_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `payment`,
  ADD COLUMN `status` ENUM('completed','refunded','partially_refunded') NOT NULL DEFAULT 'completed' AFTER `refunded_amount`,
  ADD COLUMN `recorded_by_admin_id` INT DEFAULT NULL AFTER `gym_id`,
  ADD KEY `idx_payment_membership` (`membership_id`);

-- Backfill membership_id in tblpayment using existing tbl_memberships.booking_id mapping
UPDATE `tblpayment` p
JOIN `tbl_memberships` m ON p.bookingID = m.booking_id
SET p.membership_id = m.id
WHERE p.membership_id IS NULL;

-- 3. Create tbl_membership_freezes for immutable multi-freeze history
CREATE TABLE IF NOT EXISTS `tbl_membership_freezes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `membership_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `freeze_start_date` DATE NOT NULL,
  `freeze_end_date` DATE DEFAULT NULL,
  `approved_days` INT NOT NULL DEFAULT 0,
  `reason` VARCHAR(255) DEFAULT NULL,
  `approved_by_admin_id` INT DEFAULT NULL,
  `resumed_at` TIMESTAMP NULL DEFAULT NULL,
  `resumed_by_admin_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `gym_id` INT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_freeze_mem` (`membership_id`),
  KEY `idx_freeze_user` (`user_id`),
  KEY `idx_freeze_gym` (`gym_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 4. Create tbl_refund_requests for manager-only authorization and audit
CREATE TABLE IF NOT EXISTS `tbl_refund_requests` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `payment_id` INT NOT NULL,
  `membership_id` INT DEFAULT NULL,
  `booking_id` INT DEFAULT NULL,
  `user_id` INT NOT NULL,
  `refund_amount` DECIMAL(10,2) NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `status` ENUM('pending','approved','rejected','executed') NOT NULL DEFAULT 'pending',
  `requested_by_admin_id` INT NOT NULL,
  `rejection_reason` VARCHAR(255) DEFAULT NULL,
  `authorized_by_admin_id` INT DEFAULT NULL,
  `executed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `gym_id` INT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_refund_pay` (`payment_id`),
  KEY `idx_refund_mem` (`membership_id`),
  KEY `idx_refund_user` (`user_id`),
  KEY `idx_refund_gym` (`gym_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 5. Create tbl_policy_audit_log for financial settings audit trail
CREATE TABLE IF NOT EXISTS `tbl_policy_audit_log` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `old_value` TEXT,
  `new_value` TEXT,
  `changed_by_admin_id` INT NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `gym_id` INT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_audit_key` (`setting_key`),
  KEY `idx_audit_admin` (`changed_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 6. Insert initial payment requirement defaults if not already present
INSERT INTO `tbl_settings` (`setting_group`, `setting_key`, `setting_value`)
VALUES 
  ('payment', 'min_initial_payment_type', 'percentage'),
  ('payment', 'min_initial_payment_val', '50.00')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;
