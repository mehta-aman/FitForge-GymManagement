-- ==============================================================================
-- Migration: 04_workout_presets.sql
-- Description: Additive schema for FitForge Workout Split Presets and Custom Templates.
-- Target DB: gymdb (Test/Local Environment)
-- ==============================================================================

CREATE TABLE IF NOT EXISTS `tbl_workout_presets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL DEFAULT 0,
  `gym_id` INT NOT NULL DEFAULT 1,
  `title` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(80) NULL,
  `tag` VARCHAR(60) NOT NULL DEFAULT 'Custom Split',
  `difficulty` ENUM('Beginner', 'Intermediate', 'Advanced') DEFAULT 'Intermediate',
  `days_count` INT NOT NULL DEFAULT 3,
  `description` TEXT NULL,
  `exercises_json` LONGTEXT NOT NULL,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_presets_user` (`user_id`, `is_system`),
  INDEX `idx_presets_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
