-- ==============================================================================
-- FitForge Gym Management System — Priority 2 Schema Enhancements
-- Description: Additive migration for Attendance, Trainers, Member Workouts,
--              and QR Check-in tracking.
-- Reversible: Yes (see 02_priority2_rollback.sql)
-- ==============================================================================

USE `gymdb`;

-- 1. Attendance & Floor Check-in
CREATE TABLE IF NOT EXISTS `tbl_attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `check_in_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `check_out_time` TIMESTAMP NULL DEFAULT NULL,
  `check_in_method` ENUM('qr_scan', 'manual', 'kiosk') NOT NULL DEFAULT 'manual',
  `notes` VARCHAR(255) NULL DEFAULT NULL,
  `gym_id` INT NOT NULL DEFAULT 1,
  INDEX `idx_att_user` (`user_id`),
  INDEX `idx_att_checkin` (`check_in_time`),
  INDEX `idx_att_gym` (`gym_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Certified Trainers & Coaches
CREATE TABLE IF NOT EXISTS `tbl_trainers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `speciality` VARCHAR(150) NOT NULL DEFAULT 'General Fitness & Strength',
  `mobile` VARCHAR(25) NULL DEFAULT NULL,
  `email` VARCHAR(150) NULL DEFAULT NULL,
  `experience_years` INT NOT NULL DEFAULT 2,
  `photo` VARCHAR(255) NULL DEFAULT NULL,
  `bio` TEXT NULL DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `gym_id` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_trainer_gym` (`gym_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed initial default trainers if empty
INSERT IGNORE INTO `tbl_trainers` (`id`, `name`, `speciality`, `mobile`, `email`, `experience_years`, `bio`, `status`, `gym_id`) VALUES
(1, 'Vikram Sharma', 'Strength & Hypertrophy Coach', '9876543210', 'vikram@fitforge.local', 6, 'Specializes in powerlifting and competitive bodybuilding conditioning.', 'active', 1),
(2, 'Priya Patel', 'HIIT, Cardio & Mobility Specialist', '9876543211', 'priya@fitforge.local', 4, 'Functional training and high-intensity interval conditioning expert.', 'active', 1),
(3, 'Rohit Verma', 'Weight Management & Nutritionist', '9876543212', 'rohit@fitforge.local', 5, 'Certified clinical sports nutritionist and fat-loss conditioning trainer.', 'active', 1);

-- 3. Trainer Member Assignments
CREATE TABLE IF NOT EXISTS `tbl_member_trainers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `trainer_id` INT NOT NULL,
  `assigned_date` DATE NOT NULL,
  `status` ENUM('active', 'completed', 'cancelled') NOT NULL DEFAULT 'active',
  `notes` VARCHAR(255) NULL DEFAULT NULL,
  `gym_id` INT NOT NULL DEFAULT 1,
  INDEX `idx_mt_user` (`user_id`),
  INDEX `idx_mt_trainer` (`trainer_id`),
  INDEX `idx_mt_gym` (`gym_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed assignment for testing
INSERT IGNORE INTO `tbl_member_trainers` (`id`, `user_id`, `trainer_id`, `assigned_date`, `status`, `notes`, `gym_id`) VALUES
(1, 1, 1, '2026-11-01', 'active', 'Focusing on strength progression and barbell squats', 1);

-- 4. Member Daily Workout Routines
CREATE TABLE IF NOT EXISTS `tbl_workout_plans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
  `target_muscle` VARCHAR(100) NOT NULL,
  `exercise_name` VARCHAR(150) NOT NULL,
  `sets` INT NOT NULL DEFAULT 3,
  `reps` VARCHAR(50) NOT NULL DEFAULT '10-12',
  `notes` VARCHAR(255) NULL DEFAULT NULL,
  `gym_id` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_wp_user` (`user_id`),
  INDEX `idx_wp_day` (`day_of_week`),
  INDEX `idx_wp_gym` (`gym_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed sample workout for user 1
INSERT IGNORE INTO `tbl_workout_plans` (`user_id`, `day_of_week`, `target_muscle`, `exercise_name`, `sets`, `reps`, `notes`, `gym_id`) VALUES
(1, 'Monday', 'Chest & Triceps', 'Barbell Bench Press', 4, '8-10 reps', 'Control descent, progressive overload', 1),
(1, 'Monday', 'Chest & Triceps', 'Incline Dumbbell Press', 3, '10-12 reps', 'Full stretch at bottom', 1),
(1, 'Monday', 'Chest & Triceps', 'Cable Tricep Pushdowns', 3, '12-15 reps', 'Squeeze at extension', 1),
(1, 'Wednesday', 'Back & Biceps', 'Lat Pulldowns', 4, '10-12 reps', 'Drive elbows downwards', 1),
(1, 'Wednesday', 'Back & Biceps', 'Seated Cable Rows', 3, '10-12 reps', 'Retract scapula', 1),
(1, 'Friday', 'Legs & Core', 'Barbell Back Squats', 4, '8-10 reps', 'Deep hip hinge below parallel', 1),
(1, 'Friday', 'Legs & Core', 'Hanging Leg Raises', 3, '15 reps', 'Strict form, no swinging', 1);
