-- FitForge Priority 1 Schema Enhancements
-- Safe, additive migrations with zero data loss

-- 1. Tenant Organization Table (tblgyms)
CREATE TABLE IF NOT EXISTS `tblgyms` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `gym_name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `tagline` VARCHAR(150) DEFAULT NULL,
  `logo` VARCHAR(255) DEFAULT NULL,
  `favicon` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `currency_code` VARCHAR(10) DEFAULT 'INR',
  `currency_symbol` VARCHAR(10) DEFAULT '₹',
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Seed default organization ID 1 from existing branding
INSERT INTO `tblgyms` (`id`, `gym_name`, `slug`, `tagline`, `currency_code`, `currency_symbol`, `status`)
VALUES (1, 'Golden Eagle', 'golden-eagle', 'FITNESS CLUB', 'INR', '₹', 'active')
ON DUPLICATE KEY UPDATE `gym_name` = VALUES(`gym_name`);

-- 2. Add gym_id to tenant-owned tables
ALTER TABLE `tbluser` ADD COLUMN `gym_id` INT NOT NULL DEFAULT 1, ADD INDEX `idx_user_gym` (`gym_id`);
ALTER TABLE `tbladmin` ADD COLUMN `gym_id` INT NOT NULL DEFAULT 1, ADD INDEX `idx_admin_gym` (`gym_id`);
ALTER TABLE `tbladdpackage` ADD COLUMN `gym_id` INT NOT NULL DEFAULT 1, ADD INDEX `idx_pkg_gym` (`gym_id`);
ALTER TABLE `tblcategory` ADD COLUMN `gym_id` INT NOT NULL DEFAULT 1, ADD INDEX `idx_cat_gym` (`gym_id`);
ALTER TABLE `tblbooking` ADD COLUMN `gym_id` INT NOT NULL DEFAULT 1, ADD INDEX `idx_booking_gym` (`gym_id`);
ALTER TABLE `tblpayment` ADD COLUMN `gym_id` INT NOT NULL DEFAULT 1, ADD INDEX `idx_payment_gym` (`gym_id`);
ALTER TABLE `tbl_memberships` ADD COLUMN `gym_id` INT NOT NULL DEFAULT 1, ADD INDEX `idx_membership_gym` (`gym_id`);
ALTER TABLE `tbl_website_features` ADD COLUMN `gym_id` INT NOT NULL DEFAULT 1, ADD INDEX `idx_features_gym` (`gym_id`);

-- 3. Membership Lifecycle Enhancements
ALTER TABLE `tbl_memberships` MODIFY COLUMN `status` ENUM('active','expired','pending','cancelled','frozen') NOT NULL DEFAULT 'active';
ALTER TABLE `tbl_memberships` 
  ADD COLUMN `freeze_start_date` DATE DEFAULT NULL,
  ADD COLUMN `freeze_end_date` DATE DEFAULT NULL,
  ADD COLUMN `freeze_reason` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN `cancelled_at` TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN `cancel_reason` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN `renewed_from_id` INT DEFAULT NULL;

-- 4. Plan Enhancements (Registration Fee, Discount, Display Order, Perks)
ALTER TABLE `tbladdpackage` 
  ADD COLUMN `registration_fee` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  ADD COLUMN `discount` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  ADD COLUMN `display_order` INT NOT NULL DEFAULT 0,
  ADD COLUMN `benefits` TEXT DEFAULT NULL;

-- 5. Payment Methods and Transaction Reference Numbers
ALTER TABLE `tblpayment` 
  ADD COLUMN `payment_method` VARCHAR(50) DEFAULT 'Cash',
  ADD COLUMN `transaction_ref` VARCHAR(100) DEFAULT NULL,
  ADD COLUMN `notes` TEXT DEFAULT NULL;

-- 6. Login Attempt Throttling Table
CREATE TABLE IF NOT EXISTS `tbl_login_attempts` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ip_address` VARCHAR(45) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `attempt_time` INT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip_email_time` (`ip_address`, `email`, `attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
