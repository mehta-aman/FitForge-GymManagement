-- Migration 08: Lost & Found Management System
-- Applied to gymdb

CREATE TABLE IF NOT EXISTS `tbl_lost_found` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `gym_id` INT NOT NULL DEFAULT 1,
  `item_type` ENUM('lost', 'found') NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `category` ENUM('electronics', 'gym_gear', 'bottles', 'apparel', 'keys_wallets', 'jewelry', 'other') NOT NULL DEFAULT 'other',
  `caption` TEXT NOT NULL,
  `location_in_gym` VARCHAR(150) NOT NULL,
  `date_reported` DATE NOT NULL,
  `image_path` VARCHAR(255) NULL,
  `status` ENUM('open', 'handed_to_desk', 'claimed', 'returned') NOT NULL DEFAULT 'open',
  `author_type` ENUM('user', 'admin') NOT NULL DEFAULT 'user',
  `author_id` INT NULL,
  `author_name` VARCHAR(100) NOT NULL,
  `contact_phone` VARCHAR(45) NULL,
  `contact_email` VARCHAR(100) NULL,
  `reward_notes` VARCHAR(255) NULL,
  `claimed_by_name` VARCHAR(100) NULL,
  `claimed_by_user_id` INT NULL,
  `resolved_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_lf_gym_type` (`gym_id`, `item_type`),
  KEY `idx_lf_status` (`status`),
  KEY `idx_lf_category` (`category`),
  KEY `idx_lf_author` (`author_type`, `author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Sample Lost & Found Items for Immediate Interactivity & Recommendations
INSERT INTO `tbl_lost_found` 
(`gym_id`, `item_type`, `title`, `category`, `caption`, `location_in_gym`, `date_reported`, `image_path`, `status`, `author_type`, `author_id`, `author_name`, `contact_phone`, `contact_email`, `reward_notes`, `claimed_by_name`, `resolved_at`) 
VALUES
(1, 'found', 'Apple AirPods Pro (2nd Gen)', 'electronics', 'Found white charging case with both AirPods inside near treadmill #4 during morning peak hours. Turned over to front desk reception for safekeeping and verification.', 'Cardio Zone (Treadmill #4)', CURDATE(), NULL, 'handed_to_desk', 'admin', 1, 'Front Desk Staff', '9876543210', 'frontdesk@fitforge.local', 'Verify serial number in Bluetooth settings to claim at reception', NULL, NULL),

(1, 'lost', 'Apple AirPods Pro White Case', 'electronics', 'Left my AirPods Pro near treadmill #4 or the adjacent stretch mat around 8:30 AM. Has a tiny scratch on the lid edge. Really need them back!', 'Cardio Zone', CURDATE(), NULL, 'open', 'user', 1, 'Atul Sharma', '9811223344', 'atul@gmail.com', 'Token of appreciation for return', NULL, NULL),

(1, 'found', 'Hydro Flask 32oz Cobalt Blue Bottle', 'bottles', 'Found metal water bottle with a mountain sticker sitting on the lower dumbbell rack bench. Deposited at front desk lost & found bin.', 'Free Weights Dumbbell Rack', DATE_SUB(CURDATE(), INTERVAL 1 DAY), NULL, 'handed_to_desk', 'user', 2, 'Karan Singh', '9822334455', 'karan@gmail.com', 'Claim at front desk desk reception', NULL, NULL),

(1, 'lost', 'Nike Leather Weightlifting Lever Belt', 'gym_gear', 'Black leather lever belt left near squat rack #2 yesterday evening. Has initials AM written in silver marker on the inner suede lining.', 'Squat Racks & Platforms', DATE_SUB(CURDATE(), INTERVAL 2 DAY), NULL, 'open', 'user', 1, 'Aman Mehta', '9833445566', 'admin@gmail.com', 'Cash reward for return!', NULL, NULL),

(1, 'found', 'Set of 3 Keys with Red Honda Keyfob', 'keys_wallets', 'Found on a changing bench in the men\'s locker room around 7:15 PM. Handed directly to front desk manager.', 'Men\'s Locker Room Bench', DATE_SUB(CURDATE(), INTERVAL 1 DAY), NULL, 'handed_to_desk', 'admin', 3, 'Coach Rohan', '9844556677', 'rohan.trainer@gmail.com', 'Describe keychain accessories at front desk to retrieve', NULL, NULL),

(1, 'lost', 'Garmin Forerunner 255 Slate Grey', 'electronics', 'Misplaced watch after 6 PM HIIT coaching session in Studio 1. Reunited thanks to coach Rohan!', 'Studio 1 (Yoga / Stretch Room)', DATE_SUB(CURDATE(), INTERVAL 4 DAY), NULL, 'returned', 'user', 1, 'Priya Patel', '9855667788', 'priya@fitforge.local', NULL, 'Priya Patel', NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;
