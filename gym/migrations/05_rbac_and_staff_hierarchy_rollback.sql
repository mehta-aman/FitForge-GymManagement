-- Rollback Migration 05: Multi-Role Staff RBAC & Owner Approval Hierarchy

ALTER TABLE `tbladmin`
  DROP FOREIGN KEY IF EXISTS `fk_admin_approved_by`,
  DROP FOREIGN KEY IF EXISTS `fk_admin_trainer`,
  DROP COLUMN IF EXISTS `last_login`,
  DROP COLUMN IF EXISTS `trainer_id`,
  DROP COLUMN IF EXISTS `rejection_reason`,
  DROP COLUMN IF EXISTS `approved_at`,
  DROP COLUMN IF EXISTS `approved_by`,
  DROP COLUMN IF EXISTS `status`,
  DROP COLUMN IF EXISTS `role`;
