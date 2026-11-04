-- ==============================================================================
-- Rollback Migration: 03_member_profile_metrics_rollback.sql
-- Description: Drops tbl_member_measurements and removes added profile columns from tbluser.
-- ==============================================================================

DROP TABLE IF EXISTS `tbl_member_measurements`;

ALTER TABLE `tbluser`
  DROP COLUMN `emergency_contact_phone`,
  DROP COLUMN `emergency_contact_name`,
  DROP COLUMN `target_date`,
  DROP COLUMN `target_weight`,
  DROP COLUMN `unit_preference`,
  DROP COLUMN `activity_level`,
  DROP COLUMN `fitness_goal`,
  DROP COLUMN `profile_photo`,
  DROP COLUMN `height`,
  DROP COLUMN `gender`,
  DROP COLUMN `dob`;
