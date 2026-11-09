-- Migration 05: Multi-Role Staff RBAC & Owner Approval Hierarchy
-- Applied to gymdb

ALTER TABLE `tbladmin`
  ADD COLUMN `role` ENUM('owner', 'manager', 'trainer', 'floor_staff') NOT NULL DEFAULT 'floor_staff' AFTER `password`,
  ADD COLUMN `status` ENUM('pending', 'approved', 'rejected', 'suspended') NOT NULL DEFAULT 'pending' AFTER `role`,
  ADD COLUMN `approved_by` INT NULL AFTER `status`,
  ADD COLUMN `approved_at` TIMESTAMP NULL AFTER `approved_by`,
  ADD COLUMN `rejection_reason` VARCHAR(255) NULL AFTER `approved_at`,
  ADD COLUMN `trainer_id` INT NULL AFTER `rejection_reason`,
  ADD COLUMN `last_login` TIMESTAMP NULL AFTER `trainer_id`;

-- Create foreign keys if tables exist
ALTER TABLE `tbladmin`
  ADD CONSTRAINT `fk_admin_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `tbladmin`(`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_admin_trainer` FOREIGN KEY (`trainer_id`) REFERENCES `tbl_trainers`(`id`) ON DELETE SET NULL;

-- Guarantee initial admin is the Owner with Approved status
UPDATE `tbladmin` 
SET `role` = 'owner', `status` = 'approved', `approved_at` = NOW() 
WHERE `id` = 1;
