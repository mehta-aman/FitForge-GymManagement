-- Rollback Migration 07: Configurable RBAC, Permissions Catalog, and Individual Employee Overrides

DROP TABLE IF EXISTS `tbl_admin_permissions`;
DROP TABLE IF EXISTS `tbl_role_permissions`;
DROP TABLE IF EXISTS `tbl_permissions`;
DROP TABLE IF EXISTS `tbl_roles`;

ALTER TABLE `tbladmin`
  MODIFY COLUMN `role` ENUM('owner', 'manager', 'trainer', 'floor_staff') NOT NULL DEFAULT 'floor_staff';
