-- FitForge Priority 1 Rollback Script
-- Reverts schema enhancements safely

-- 1. Drop login attempts
DROP TABLE IF EXISTS `tbl_login_attempts`;

-- 2. Remove columns from tblpayment
ALTER TABLE `tblpayment` 
  DROP COLUMN IF EXISTS `notes`,
  DROP COLUMN IF EXISTS `transaction_ref`,
  DROP COLUMN IF EXISTS `payment_method`,
  DROP INDEX IF EXISTS `idx_payment_gym`,
  DROP COLUMN IF EXISTS `gym_id`;

-- 3. Remove columns from tbladdpackage
ALTER TABLE `tbladdpackage` 
  DROP COLUMN IF EXISTS `benefits`,
  DROP COLUMN IF EXISTS `display_order`,
  DROP COLUMN IF EXISTS `discount`,
  DROP COLUMN IF EXISTS `registration_fee`,
  DROP INDEX IF EXISTS `idx_pkg_gym`,
  DROP COLUMN IF EXISTS `gym_id`;

-- 4. Revert tbl_memberships
ALTER TABLE `tbl_memberships` 
  DROP COLUMN IF EXISTS `renewed_from_id`,
  DROP COLUMN IF EXISTS `cancel_reason`,
  DROP COLUMN IF EXISTS `cancelled_at`,
  DROP COLUMN IF EXISTS `freeze_reason`,
  DROP COLUMN IF EXISTS `freeze_end_date`,
  DROP COLUMN IF EXISTS `freeze_start_date`,
  DROP INDEX IF EXISTS `idx_membership_gym`,
  DROP COLUMN IF EXISTS `gym_id`;

ALTER TABLE `tbl_memberships` MODIFY COLUMN `status` ENUM('active','expired','pending','cancelled') NOT NULL DEFAULT 'active';

-- 5. Remove gym_id from other tables
ALTER TABLE `tbl_website_features` DROP INDEX IF EXISTS `idx_features_gym`, DROP COLUMN IF EXISTS `gym_id`;
ALTER TABLE `tblbooking` DROP INDEX IF EXISTS `idx_booking_gym`, DROP COLUMN IF EXISTS `gym_id`;
ALTER TABLE `tblcategory` DROP INDEX IF EXISTS `idx_cat_gym`, DROP COLUMN IF EXISTS `gym_id`;
ALTER TABLE `tbladmin` DROP INDEX IF EXISTS `idx_admin_gym`, DROP COLUMN IF EXISTS `gym_id`;
ALTER TABLE `tbluser` DROP INDEX IF EXISTS `idx_user_gym`, DROP COLUMN IF EXISTS `gym_id`;

-- 6. Drop tblgyms
DROP TABLE IF EXISTS `tblgyms`;
