-- ==============================================================================
-- FitForge Gym Management System — Priority 2 Migration Rollback
-- Description: Drops additive tables created in 02_priority2_schema_enhancements.sql
-- ==============================================================================

USE `gymdb`;

DROP TABLE IF EXISTS `tbl_workout_plans`;
DROP TABLE IF EXISTS `tbl_member_trainers`;
DROP TABLE IF EXISTS `tbl_trainers`;
DROP TABLE IF EXISTS `tbl_attendance`;
