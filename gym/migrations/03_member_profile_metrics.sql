-- ==============================================================================
-- Migration: 03_member_profile_metrics.sql
-- Description: Additive schema enhancements for Member Profile, Fitness Metrics,
--              and Chronological Body Measurement History.
-- Target DB: gymdb (Test/Local Environment)
-- ==============================================================================

-- 1. Additive columns to tbluser for Member Fitness Profile & Preferences
ALTER TABLE `tbluser`
  ADD COLUMN `dob` DATE NULL,
  ADD COLUMN `gender` ENUM('male', 'female', 'other', 'prefer_not_to_say') DEFAULT 'prefer_not_to_say',
  ADD COLUMN `height` DECIMAL(5,2) NULL,
  ADD COLUMN `profile_photo` VARCHAR(255) NULL,
  ADD COLUMN `fitness_goal` ENUM('fat_loss', 'muscle_gain', 'strength', 'endurance', 'general_fitness', 'maintenance') DEFAULT 'general_fitness',
  ADD COLUMN `activity_level` ENUM('sedentary', 'lightly_active', 'moderately_active', 'very_active', 'extra_active') DEFAULT 'moderately_active',
  ADD COLUMN `unit_preference` ENUM('metric', 'imperial') DEFAULT 'metric',
  ADD COLUMN `target_weight` DECIMAL(5,2) NULL,
  ADD COLUMN `target_date` DATE NULL,
  ADD COLUMN `emergency_contact_name` VARCHAR(100) NULL,
  ADD COLUMN `emergency_contact_phone` VARCHAR(30) NULL;

-- 2. Dedicated Table for Chronological Body Measurement Tracking
CREATE TABLE IF NOT EXISTS `tbl_member_measurements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `gym_id` INT NOT NULL DEFAULT 1,
  `measurement_date` DATE NOT NULL,
  `weight_kg` DECIMAL(5,2) NOT NULL,
  `height_cm` DECIMAL(5,2) NULL,
  `waist_cm` DECIMAL(5,2) NOT NULL,
  `neck_cm` DECIMAL(5,2) NULL,
  `chest_cm` DECIMAL(5,2) NULL,
  `shoulders_cm` DECIMAL(5,2) NULL,
  `hip_cm` DECIMAL(5,2) NULL,
  `arm_left_cm` DECIMAL(5,2) NULL,
  `arm_right_cm` DECIMAL(5,2) NULL,
  `forearm_left_cm` DECIMAL(5,2) NULL,
  `forearm_right_cm` DECIMAL(5,2) NULL,
  `thigh_left_cm` DECIMAL(5,2) NULL,
  `thigh_right_cm` DECIMAL(5,2) NULL,
  `calf_left_cm` DECIMAL(5,2) NULL,
  `calf_right_cm` DECIMAL(5,2) NULL,
  `body_fat_pct` DECIMAL(4,1) NULL,
  `body_fat_source` ENUM('manual', 'scale_device', 'caliper', 'dexa', 'other') DEFAULT 'manual',
  `input_unit` ENUM('metric', 'imperial') DEFAULT 'metric',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_measurements_user_date` (`user_id`, `measurement_date`),
  CONSTRAINT `fk_measurements_user` FOREIGN KEY (`user_id`) REFERENCES `tbluser` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
