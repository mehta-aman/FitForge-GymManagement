-- MySQL dump 10.13  Distrib 5.7.24, for osx11.1 (x86_64)
--
-- Host: localhost    Database: gymdb
-- ------------------------------------------------------
-- Server version	9.3.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `tbl_admin_permissions`
--

DROP TABLE IF EXISTS `tbl_admin_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_admin_permissions` (
  `admin_id` int NOT NULL,
  `permission_key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_granted` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 = explicit grant override, 0 = explicit deny override',
  `granted_by_admin_id` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`admin_id`,`permission_key`),
  KEY `fk_ap_perm` (`permission_key`),
  KEY `fk_ap_granter` (`granted_by_admin_id`),
  CONSTRAINT `fk_ap_admin` FOREIGN KEY (`admin_id`) REFERENCES `tbladmin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ap_granter` FOREIGN KEY (`granted_by_admin_id`) REFERENCES `tbladmin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ap_perm` FOREIGN KEY (`permission_key`) REFERENCES `tbl_permissions` (`permission_key`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_admin_permissions`
--

LOCK TABLES `tbl_admin_permissions` WRITE;
/*!40000 ALTER TABLE `tbl_admin_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_admin_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_attendance`
--

DROP TABLE IF EXISTS `tbl_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_attendance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `check_in_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `check_out_time` timestamp NULL DEFAULT NULL,
  `check_in_method` enum('qr_scan','manual','kiosk') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gym_id` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_att_user` (`user_id`),
  KEY `idx_att_checkin` (`check_in_time`),
  KEY `idx_att_gym` (`gym_id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_attendance`
--

LOCK TABLES `tbl_attendance` WRITE;
/*!40000 ALTER TABLE `tbl_attendance` DISABLE KEYS */;
INSERT INTO `tbl_attendance` VALUES (1,1,'2026-11-04 11:57:56','2026-11-04 12:23:03','manual',NULL,1),(2,7,'2026-11-04 12:23:30','2026-11-04 16:23:16','manual',NULL,1),(3,2,'2026-11-04 12:23:49','2026-11-04 16:23:14','manual',NULL,1),(4,1,'2026-11-04 12:23:57','2026-11-04 16:23:12','manual',NULL,1),(5,4,'2026-11-04 12:24:08','2026-11-04 16:23:10','manual',NULL,1),(7,1,'2026-11-05 07:06:00','2026-11-05 07:06:00','qr_scan','Gym Poster Self-Scan',1),(8,1,'2026-11-05 07:09:44',NULL,'qr_scan','Gym Poster Self-Scan',1),(29,1,'2026-11-10 18:28:01','2026-11-10 18:28:01','qr_scan','Gym Poster Self-Scan',1),(32,1,'2026-11-11 03:06:25','2026-11-11 03:06:25','qr_scan','Gym Poster Self-Scan',1);
/*!40000 ALTER TABLE `tbl_attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_login_attempts`
--

DROP TABLE IF EXISTS `tbl_login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `email` varchar(100) NOT NULL,
  `attempt_time` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip_email_time` (`ip_address`,`email`,`attempt_time`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_login_attempts`
--

LOCK TABLES `tbl_login_attempts` WRITE;
/*!40000 ALTER TABLE `tbl_login_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_lost_found`
--

DROP TABLE IF EXISTS `tbl_lost_found`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_lost_found` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gym_id` int NOT NULL DEFAULT '1',
  `item_type` enum('lost','found') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('electronics','gym_gear','bottles','apparel','keys_wallets','jewelry','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `caption` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_in_gym` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_reported` date NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('open','handed_to_desk','claimed','returned') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `author_type` enum('user','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `author_id` int DEFAULT NULL,
  `author_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_phone` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reward_notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `claimed_by_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `claimed_by_user_id` int DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lf_gym_type` (`gym_id`,`item_type`),
  KEY `idx_lf_status` (`status`),
  KEY `idx_lf_category` (`category`),
  KEY `idx_lf_author` (`author_type`,`author_id`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_lost_found`
--

LOCK TABLES `tbl_lost_found` WRITE;
/*!40000 ALTER TABLE `tbl_lost_found` DISABLE KEYS */;
INSERT INTO `tbl_lost_found` VALUES (1,1,'found','Apple AirPods Pro (2nd Gen)','electronics','Found white charging case with both AirPods inside near treadmill #4 during morning peak hours. Turned over to front desk reception for safekeeping and verification.','Cardio Zone (Treadmill #4)','2026-11-10',NULL,'handed_to_desk','admin',1,'Front Desk Staff','9876543210','frontdesk@fitforge.local','Verify serial number in Bluetooth settings to claim at reception',NULL,NULL,NULL,'2026-11-10 11:34:35','2026-11-10 11:34:35'),(2,1,'lost','Apple AirPods Pro White Case','electronics','Left my AirPods Pro near treadmill #4 or the adjacent stretch mat around 8:30 AM. Has a tiny scratch on the lid edge. Really need them back!','Cardio Zone','2026-11-10',NULL,'returned','user',1,'Atul Sharma','9811223344','atul@gmail.com','Token of appreciation for return',NULL,NULL,'2026-11-10 20:30:57','2026-11-10 11:34:35','2026-11-11 02:00:57'),(3,1,'found','Hydro Flask 32oz Cobalt Blue Bottle','bottles','Found metal water bottle with a mountain sticker sitting on the lower dumbbell rack bench. Deposited at front desk lost & found bin.','Free Weights Dumbbell Rack','2026-11-09',NULL,'handed_to_desk','user',2,'Karan Singh','9822334455','karan@gmail.com','Claim at front desk desk reception',NULL,NULL,NULL,'2026-11-10 11:34:35','2026-11-10 11:34:35'),(4,1,'lost','Nike Leather Weightlifting Lever Belt','gym_gear','Black leather lever belt left near squat rack #2 yesterday evening. Has initials AM written in silver marker on the inner suede lining.','Squat Racks & Platforms','2026-11-08',NULL,'returned','user',1,'Aman Mehta','9833445566','admin@gmail.com','Cash reward for return!',NULL,NULL,'2026-11-10 20:31:12','2026-11-10 11:34:35','2026-11-11 02:01:12'),(5,1,'found','Set of 3 Keys with Red Honda Keyfob','keys_wallets','Found on a changing bench in the men\'s locker room around 7:15 PM. Handed directly to front desk manager.','Men\'s Locker Room Bench','2026-11-09',NULL,'handed_to_desk','admin',3,'Coach Rohan','9844556677','rohan.trainer@gmail.com','Describe keychain accessories at front desk to retrieve',NULL,NULL,NULL,'2026-11-10 11:34:35','2026-11-10 11:34:35'),(6,1,'lost','Garmin Forerunner 255 Slate Grey','electronics','Misplaced watch after 6 PM HIIT coaching session in Studio 1. Reunited thanks to coach Rohan!','Studio 1 (Yoga / Stretch Room)','2026-11-06',NULL,'returned','user',1,'Priya Patel','9855667788','priya@fitforge.local',NULL,'Priya Patel',NULL,'2026-11-10 11:34:35','2026-11-10 11:34:35','2026-11-10 11:34:35');
/*!40000 ALTER TABLE `tbl_lost_found` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_member_measurements`
--

DROP TABLE IF EXISTS `tbl_member_measurements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_member_measurements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `gym_id` int NOT NULL DEFAULT '1',
  `measurement_date` date NOT NULL,
  `weight_kg` decimal(5,2) NOT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL,
  `waist_cm` decimal(5,2) NOT NULL,
  `neck_cm` decimal(5,2) DEFAULT NULL,
  `chest_cm` decimal(5,2) DEFAULT NULL,
  `shoulders_cm` decimal(5,2) DEFAULT NULL,
  `hip_cm` decimal(5,2) DEFAULT NULL,
  `arm_left_cm` decimal(5,2) DEFAULT NULL,
  `arm_right_cm` decimal(5,2) DEFAULT NULL,
  `forearm_left_cm` decimal(5,2) DEFAULT NULL,
  `forearm_right_cm` decimal(5,2) DEFAULT NULL,
  `thigh_left_cm` decimal(5,2) DEFAULT NULL,
  `thigh_right_cm` decimal(5,2) DEFAULT NULL,
  `calf_left_cm` decimal(5,2) DEFAULT NULL,
  `calf_right_cm` decimal(5,2) DEFAULT NULL,
  `body_fat_pct` decimal(4,1) DEFAULT NULL,
  `body_fat_source` enum('manual','scale_device','caliper','dexa','other') COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `input_unit` enum('metric','imperial') COLLATE utf8mb4_unicode_ci DEFAULT 'metric',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_measurements_user_date` (`user_id`,`measurement_date`),
  CONSTRAINT `fk_measurements_user` FOREIGN KEY (`user_id`) REFERENCES `tbluser` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_member_measurements`
--

LOCK TABLES `tbl_member_measurements` WRITE;
/*!40000 ALTER TABLE `tbl_member_measurements` DISABLE KEYS */;
INSERT INTO `tbl_member_measurements` VALUES (1,1,1,'2026-10-07',78.50,178.00,86.00,NULL,101.00,NULL,98.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,21.0,'scale_device','metric','Initial gym baseline assessment','2026-11-04 17:12:50','2026-11-04 17:12:50'),(2,1,1,'2026-10-21',77.20,178.00,84.50,NULL,100.50,NULL,97.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,20.2,'scale_device','metric','Mid-month check-in, feeling leaner','2026-11-04 17:12:50','2026-11-04 17:12:50'),(3,1,1,'2026-11-03',75.80,178.00,83.00,NULL,100.00,NULL,96.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,19.5,'scale_device','metric','Progress weigh-in, strength maintained','2026-11-04 17:12:50','2026-11-04 17:12:50');
/*!40000 ALTER TABLE `tbl_member_measurements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_member_trainers`
--

DROP TABLE IF EXISTS `tbl_member_trainers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_member_trainers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `trainer_id` int NOT NULL,
  `assigned_date` date NOT NULL,
  `status` enum('active','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gym_id` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_mt_user` (`user_id`),
  KEY `idx_mt_trainer` (`trainer_id`),
  KEY `idx_mt_gym` (`gym_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_member_trainers`
--

LOCK TABLES `tbl_member_trainers` WRITE;
/*!40000 ALTER TABLE `tbl_member_trainers` DISABLE KEYS */;
INSERT INTO `tbl_member_trainers` VALUES (1,1,1,'2026-11-01','active','Focusing on strength progression and barbell squats',1),(2,7,2,'2026-11-11','active','',1);
/*!40000 ALTER TABLE `tbl_member_trainers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_membership_freezes`
--

DROP TABLE IF EXISTS `tbl_membership_freezes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_membership_freezes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `membership_id` int NOT NULL,
  `user_id` int NOT NULL,
  `freeze_start_date` date NOT NULL,
  `freeze_end_date` date DEFAULT NULL,
  `approved_days` int NOT NULL DEFAULT '0',
  `reason` varchar(255) DEFAULT NULL,
  `approved_by_admin_id` int DEFAULT NULL,
  `resumed_at` timestamp NULL DEFAULT NULL,
  `resumed_by_admin_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `gym_id` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_freeze_mem` (`membership_id`),
  KEY `idx_freeze_user` (`user_id`),
  KEY `idx_freeze_gym` (`gym_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_membership_freezes`
--

LOCK TABLES `tbl_membership_freezes` WRITE;
/*!40000 ALTER TABLE `tbl_membership_freezes` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_membership_freezes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_memberships`
--

DROP TABLE IF EXISTS `tbl_memberships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_memberships` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `plan_id` int NOT NULL,
  `booking_id` int DEFAULT NULL,
  `start_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `status` enum('active','expired','pending','cancelled','frozen','scheduled') NOT NULL DEFAULT 'active',
  `payment_status` enum('unpaid','partial','paid','partially_refunded','refunded') NOT NULL DEFAULT 'unpaid',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_method` varchar(50) DEFAULT 'Cash',
  `transaction_ref` varchar(100) DEFAULT NULL,
  `admin_notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `gym_id` int NOT NULL DEFAULT '1',
  `freeze_start_date` date DEFAULT NULL,
  `freeze_end_date` date DEFAULT NULL,
  `freeze_reason` varchar(255) DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `renewed_from_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `plan_id` (`plan_id`),
  KEY `booking_id` (`booking_id`),
  KEY `idx_membership_gym` (`gym_id`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_memberships`
--

LOCK TABLES `tbl_memberships` WRITE;
/*!40000 ALTER TABLE `tbl_memberships` DISABLE KEYS */;
INSERT INTO `tbl_memberships` VALUES (1,1,2,1,'2022-03-05','2022-09-05','expired','paid',800.00,5000.00,'Cash',NULL,NULL,'2022-03-05 03:53:21',1,NULL,NULL,NULL,NULL,NULL,NULL),(2,1,1,2,'2022-03-05','2022-06-05','expired','paid',600.00,5000.00,'Cash',NULL,NULL,'2022-03-05 03:53:28',1,NULL,NULL,NULL,NULL,NULL,NULL),(3,5,2,3,'2022-03-08','2022-09-08','expired','paid',800.00,800.00,'Cash',NULL,NULL,'2022-03-08 17:44:18',1,NULL,NULL,NULL,NULL,NULL,NULL),(4,5,1,6,'2022-05-22','2022-08-22','expired','unpaid',600.00,0.00,'Cash',NULL,NULL,'2022-05-22 02:16:14',1,NULL,NULL,NULL,NULL,NULL,NULL),(5,6,2,7,'2022-05-22','2022-11-22','expired','paid',800.00,2000.00,'Cash',NULL,NULL,'2022-05-22 02:32:45',1,NULL,NULL,NULL,NULL,NULL,NULL),(6,7,1,8,'2026-11-04','2027-02-04','pending','unpaid',2500.00,0.00,'Cash',NULL,NULL,'2026-11-04 10:07:16',1,NULL,NULL,NULL,NULL,NULL,NULL),(7,7,1,9,'2026-11-04','2027-02-05','active','paid',2500.00,2500.00,'Cash','','\n[Frozen on 2026-11-04: Personal Travel / Vacation]\n[Resumed on 2026-11-04. Extended by 1 days to 2027-02-05]','2026-11-04 10:07:55',1,NULL,NULL,NULL,NULL,NULL,NULL),(8,5,1,10,'2026-11-10','2027-02-10','active','paid',2500.00,2500.00,'Cash','','','2026-11-10 07:52:35',1,NULL,NULL,NULL,NULL,NULL,NULL),(9,1,1,11,'2026-11-10','2027-02-10','active','paid',2500.00,2500.00,'Cash','','','2026-11-10 07:56:05',1,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `tbl_memberships` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_permissions`
--

DROP TABLE IF EXISTS `tbl_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_permissions` (
  `permission_key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `label` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_owner_only` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`permission_key`),
  KEY `idx_perm_category` (`category`),
  KEY `idx_perm_owner_only` (`is_owner_only`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_permissions`
--

LOCK TABLES `tbl_permissions` WRITE;
/*!40000 ALTER TABLE `tbl_permissions` DISABLE KEYS */;
INSERT INTO `tbl_permissions` VALUES ('financial.dashboard','financial','Financial KPI Dashboard','Access gross collection totals, dues widgets, and monthly growth charts',1,'2026-11-10 11:07:23'),('financial.dues','financial','Outstanding Dues Report','View club-wide member payment balances and pending receivables',1,'2026-11-10 11:07:23'),('financial.exports','financial','Financial Data Exports','Export payment records and accounting ledgers to CSV or external files',1,'2026-11-10 11:07:23'),('financial.ledger','financial','Payment Ledger & History','Browse global member payment transactions, booking histories, and receipts',1,'2026-11-10 11:07:23'),('financial.receipts_general','financial','General Receipt Access','Access and download billing receipts across arbitrary members',1,'2026-11-10 11:07:23'),('financial.reports','financial','Financial & Revenue Reports','Generate and view gym-wide payment reports and date-range collections',1,'2026-11-10 11:07:23'),('manage_attendance','attendance','Manage Check-Ins','Perform manual desk check-ins and check-outs for gym members',0,'2026-11-10 11:07:23'),('manage_bookings','financial','Manage Booking Transactions','Access admin bookings hub and record payments',1,'2026-11-10 11:07:23'),('manage_categories','memberships','Manage Categories','Create and modify plan categories and workout categories',0,'2026-11-10 11:07:23'),('manage_financial_policies','memberships','Configure Initial Payment Policy','Adjust the gym-wide minimum initial payment percentage/amount threshold',0,'2026-11-10 11:07:23'),('manage_freezes','memberships','Manage Membership Freezes','Put memberships on medical/vacation freeze and unfreeze with expiry extension',0,'2026-11-10 11:07:23'),('manage_measurements','operations','Manage Body Measurements','Record and track member weight, waist, body fat, and circumferences',0,'2026-11-10 11:07:23'),('manage_members','memberships','Manage Member Accounts','Register new members, edit contact info, and update member profiles',0,'2026-11-10 11:07:23'),('manage_plans','memberships','Manage Membership Plans','Create, update, and manage membership pricing tiers and packages',0,'2026-11-10 11:07:23'),('manage_routines','operations','Manage Workout Routines','Assign and update workout presets and exercise programs for members',0,'2026-11-10 11:07:23'),('manage_settings','administration','Gym & Branding Settings','Configure core gym settings, branding, themes, and global preferences',1,'2026-11-10 11:07:23'),('manage_staff','administration','Staff & Permissions Management','Approve applicants, adjust roles, and manage employee permission overrides',1,'2026-11-10 11:07:23'),('payments.record','financial','Record Member Payments','Collect and log cash/card/UPI installment payments on bookings',1,'2026-11-10 11:07:23'),('refunds.approve','refunds','Approve Refund Cases','Approve submitted refund requests under approved refund policy',0,'2026-11-10 11:07:23'),('refunds.process','refunds','Execute & Disburse Refunds','Execute authorized refunds and update membership refundable balance',0,'2026-11-10 11:07:23'),('refunds.reject','refunds','Reject Refund Cases','Reject refund requests with documented operational reason',0,'2026-11-10 11:07:23'),('refunds.request','refunds','Submit Refund Requests','Submit a refund request on behalf of a member for owner/manager review',0,'2026-11-10 11:07:23'),('refunds.view_assigned','refunds','View Refund Cases Queue','View pending refund applications submitted within the gym',0,'2026-11-10 11:07:23'),('view_attendance','attendance','View Attendance Logs','View floor headcount and daily attendance logs',0,'2026-11-10 11:07:23'),('view_financials','financial','General Financial View','Legacy alias for accessing general financial records',1,'2026-11-10 11:07:23'),('view_member_profile','operations','View Coaching Profile','View member health profile, measurements, and training history',0,'2026-11-10 11:07:23'),('view_members','memberships','View Member Directory','Look up member contact info, active passes, and basic account details',0,'2026-11-10 11:07:23'),('view_qr_poster','attendance','Access Gym QR Poster','Access and print the gym fast QR attendance station poster',0,'2026-11-10 11:07:23');
/*!40000 ALTER TABLE `tbl_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_policy_audit_log`
--

DROP TABLE IF EXISTS `tbl_policy_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_policy_audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `old_value` text,
  `new_value` text,
  `changed_by_admin_id` int NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `gym_id` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_audit_key` (`setting_key`),
  KEY `idx_audit_admin` (`changed_by_admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_policy_audit_log`
--

LOCK TABLES `tbl_policy_audit_log` WRITE;
/*!40000 ALTER TABLE `tbl_policy_audit_log` DISABLE KEYS */;
INSERT INTO `tbl_policy_audit_log` VALUES (1,'min_initial_payment_val','50','40.00',1,'127.0.0.1','2026-11-10 09:30:56',1),(2,'min_initial_payment_val','40','40.00',1,'127.0.0.1','2026-11-10 09:31:47',1),(3,'min_initial_payment_val','40','40.00',1,'127.0.0.1','2026-11-10 09:33:27',1),(4,'min_initial_payment_val','40','40.00',1,'127.0.0.1','2026-11-10 09:37:01',1),(5,'min_initial_payment_val','40','40.00',1,'127.0.0.1','2026-11-10 09:37:09',1),(6,'min_initial_payment_val','40','40.00',1,'127.0.0.1','2026-11-10 09:38:37',1),(7,'min_initial_payment_val','40','40.00',1,'127.0.0.1','2026-11-10 09:39:11',1),(8,'min_initial_payment_val','40','40.00',1,'127.0.0.1','2026-11-10 11:03:58',1),(9,'min_initial_payment_val','40','40.00',1,'127.0.0.1','2026-11-10 11:20:22',1),(10,'min_initial_payment_val','40','40.00',1,'127.0.0.1','2026-11-10 11:51:17',1),(11,'min_initial_payment_val','40','40.00',1,'127.0.0.1','2026-11-10 17:24:24',1),(12,'min_initial_payment_val','40','40.00',1,'127.0.0.1','2026-11-10 17:25:08',1),(13,'min_initial_payment_val','40','40.00',1,'127.0.0.1','2026-11-10 17:39:50',1),(14,'min_initial_payment_val','40','40.00',1,'127.0.0.1','2026-11-10 17:49:52',1),(15,'min_initial_payment_val','40','40.00',1,'127.0.0.1','2026-11-10 18:03:19',1),(16,'min_initial_payment_val','40','40.00',1,'127.0.0.1','2026-11-10 18:28:15',1),(17,'min_initial_payment_val','40','40.00',1,'127.0.0.1','2026-11-10 18:41:56',1),(18,'min_initial_payment_val','40','40.00',1,'127.0.0.1','2026-11-11 03:05:33',1),(19,'min_initial_payment_val','40','40.00',1,'127.0.0.1','2026-11-11 03:06:29',1);
/*!40000 ALTER TABLE `tbl_policy_audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_refund_requests`
--

DROP TABLE IF EXISTS `tbl_refund_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_refund_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `payment_id` int NOT NULL,
  `membership_id` int DEFAULT NULL,
  `booking_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `refund_amount` decimal(10,2) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected','executed') NOT NULL DEFAULT 'pending',
  `requested_by_admin_id` int NOT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `authorized_by_admin_id` int DEFAULT NULL,
  `executed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `gym_id` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_refund_pay` (`payment_id`),
  KEY `idx_refund_mem` (`membership_id`),
  KEY `idx_refund_user` (`user_id`),
  KEY `idx_refund_gym` (`gym_id`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_refund_requests`
--

LOCK TABLES `tbl_refund_requests` WRITE;
/*!40000 ALTER TABLE `tbl_refund_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_refund_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_role_permissions`
--

DROP TABLE IF EXISTS `tbl_role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_role_permissions` (
  `role_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission_key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`role_key`,`permission_key`),
  KEY `fk_rp_perm` (`permission_key`),
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_key`) REFERENCES `tbl_permissions` (`permission_key`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_key`) REFERENCES `tbl_roles` (`role_key`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_role_permissions`
--

LOCK TABLES `tbl_role_permissions` WRITE;
/*!40000 ALTER TABLE `tbl_role_permissions` DISABLE KEYS */;
INSERT INTO `tbl_role_permissions` VALUES ('floor_staff','manage_attendance'),('manager','manage_attendance'),('trainer','manage_attendance'),('manager','manage_categories'),('manager','manage_financial_policies'),('manager','manage_freezes'),('manager','manage_measurements'),('trainer','manage_measurements'),('floor_staff','manage_members'),('manager','manage_members'),('manager','manage_plans'),('manager','manage_routines'),('trainer','manage_routines'),('manager','refunds.approve'),('manager','refunds.process'),('manager','refunds.reject'),('floor_staff','refunds.request'),('manager','refunds.request'),('trainer','refunds.request'),('manager','refunds.view_assigned'),('floor_staff','view_attendance'),('manager','view_attendance'),('trainer','view_attendance'),('manager','view_member_profile'),('trainer','view_member_profile'),('floor_staff','view_members'),('manager','view_members'),('trainer','view_members'),('floor_staff','view_qr_poster'),('manager','view_qr_poster'),('trainer','view_qr_poster');
/*!40000 ALTER TABLE `tbl_role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_roles`
--

DROP TABLE IF EXISTS `tbl_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_roles` (
  `role_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_system_role` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_roles`
--

LOCK TABLES `tbl_roles` WRITE;
/*!40000 ALTER TABLE `tbl_roles` DISABLE KEYS */;
INSERT INTO `tbl_roles` VALUES ('floor_staff','Front Desk / Floor Staff','Front desk check-ins, member lookups, QR station and desk operations',1,'2026-11-10 11:07:23'),('manager','Operations Manager','Operational gym management & authorized case-by-case refund reviews (Restricted financial access)',1,'2026-11-10 11:07:23'),('owner','Gym Owner','Full system access & exclusive financial authority (non-transferable, immutable)',1,'2026-11-10 11:07:23'),('trainer','Fitness Coach','Client coaching, workout presets, measurements and attendance logging',1,'2026-11-10 11:07:23');
/*!40000 ALTER TABLE `tbl_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_settings`
--

DROP TABLE IF EXISTS `tbl_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_group` varchar(50) NOT NULL DEFAULT 'general',
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=255 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_settings`
--

LOCK TABLES `tbl_settings` WRITE;
/*!40000 ALTER TABLE `tbl_settings` DISABLE KEYS */;
INSERT INTO `tbl_settings` VALUES (1,'general','gym_name','Golden Eagle','2026-11-09 04:41:12'),(2,'general','gym_tagline','FITNESS CLUB','2026-11-04 05:51:53'),(3,'general','website_title','Golden Eagle FITNESS CLUB — Elevate Your Fitness','2026-11-04 06:35:53'),(4,'general','seo_description','Next generation fitness club offering personalized training, premium recovery, and flexible membership passes.','2026-11-04 05:51:53'),(5,'branding','gym_logo','img/branding/37071fc5403ce9978504c7bc1a88a1f5.png','2026-11-04 06:48:03'),(6,'branding','gym_favicon','img/branding/af78ed7a29bc3900dca0c55e7d0c8bbc.png','2026-11-04 06:48:19'),(7,'general','currency_symbol','₹','2026-11-04 06:46:01'),(8,'general','currency_code','INR','2026-11-04 06:46:01'),(9,'localization','timezone','Asia/Kolkata','2026-11-04 05:51:53'),(10,'localization','date_format','d M Y','2026-11-04 05:51:53'),(11,'contact','contact_email','contact@gymclub.com','2026-11-04 05:51:53'),(12,'contact','contact_phone','+91 9983665673','2026-11-04 06:35:53'),(13,'contact','contact_whatsapp','+91 9983665673','2026-11-04 06:35:53'),(14,'contact','contact_address','Radha Krishkna Mandir, Nimbaheda Road, Mangalwad Chouraha','2026-11-04 06:35:53'),(15,'contact','map_link','https://maps.google.com','2026-11-04 05:51:53'),(16,'social','social_facebook','https://facebook.com','2026-11-04 05:51:53'),(17,'social','social_instagram','https://instagram.com','2026-11-04 05:51:53'),(18,'social','social_twitter','https://twitter.com','2026-11-04 05:51:53'),(19,'social','social_youtube','https://youtube.com','2026-11-04 05:51:53'),(20,'hero','hero_pill','NEXT GENERATION FITNESS','2026-11-04 05:51:53'),(21,'hero','hero_title','Sculpt Your Body. Elevate Your Mind.','2026-11-04 05:51:53'),(22,'hero','hero_subtitle','Experience bespoke coaching, elite recovery zones, and flexible membership passes with iOS-grade precision.','2026-11-04 05:51:53'),(23,'hero','hero_cta_text','Explore Memberships','2026-11-04 05:51:53'),(24,'hero','hero_cta_link','#plans','2026-11-04 05:51:53'),(25,'about','about_pill','OUR MISSION','2026-11-04 05:51:53'),(26,'about','about_title','Empowering Lifelong Fitness','2026-11-04 05:51:53'),(27,'about','about_text','Welcome to GYM MS! We are a collective of fitness enthusiasts, certified coaches, and nutritionists dedicated to redefining your health journey.','2026-11-04 05:51:53'),(28,'footer','footer_copy','GYM Management System. Crafted with iOS-inspired Glassmorphism & High-Performance Motion.','2026-11-04 05:51:53'),(29,'theme','theme_primary','#00e04f','2026-11-04 07:01:43'),(30,'theme','theme_accent','#04b7d7','2026-11-04 07:01:43'),(31,'theme','theme_mode','dark','2026-11-04 06:40:17'),(32,'theme','theme_preset','custom','2026-11-04 07:01:43'),(61,'membership','renewal_reminder_days','3','2026-11-04 06:46:01'),(108,'theme','btn_primary_bg','#00c244','2026-11-04 07:01:43'),(109,'theme','btn_primary_text','#ffffff','2026-11-04 06:59:34'),(110,'theme','btn_secondary_bg','#064e3b','2026-11-04 07:00:42'),(111,'theme','btn_secondary_text','#ecfdf5','2026-11-04 07:00:42'),(112,'theme','btn_secondary_border','#047857','2026-11-04 07:00:42'),(140,'attendance','gym_checkin_token','fg_103adbf550a0','2026-11-05 07:00:20'),(167,'attendance','gym_checkin_base_url','http://localhost:8080','2026-11-10 08:43:03'),(168,'attendance','gym_checkin_require_token','1','2026-11-10 08:43:03'),(169,'attendance','gym_poster_title','SCAN TO CHECK IN','2026-11-10 08:43:03'),(170,'attendance','gym_poster_subtitle','Point your phone camera here to log your attendance instantly','2026-11-10 08:43:03'),(171,'attendance','gym_poster_wifi_enabled','1','2026-11-10 08:43:03'),(172,'attendance','gym_poster_wifi_ssid','GoldenEagle-Guest','2026-11-10 08:43:03'),(173,'attendance','gym_poster_wifi_pass','FitLife2026','2026-11-10 08:43:03'),(181,'policies','min_initial_payment_type','percentage','2026-11-10 09:30:56'),(182,'policies','min_initial_payment_val','40','2026-11-11 03:06:30');
/*!40000 ALTER TABLE `tbl_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_trainers`
--

DROP TABLE IF EXISTS `tbl_trainers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_trainers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `speciality` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'General Fitness & Strength',
  `mobile` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `experience_years` int NOT NULL DEFAULT '2',
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `gym_id` int NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_trainer_gym` (`gym_id`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_trainers`
--

LOCK TABLES `tbl_trainers` WRITE;
/*!40000 ALTER TABLE `tbl_trainers` DISABLE KEYS */;
INSERT INTO `tbl_trainers` VALUES (1,'Vikram Sharma','Strength & Hypertrophy Coach','9876543210','vikram@fitforge.local',6,NULL,'Specializes in powerlifting and competitive bodybuilding conditioning.','active',1,'2026-11-04 10:47:37'),(2,'Priya Patel','HIIT, Cardio & Mobility Specialist','9876543211','priya@fitforge.local',4,NULL,'Functional training and high-intensity interval conditioning expert.','active',1,'2026-11-04 10:47:37'),(3,'Rohit Verma','Weight Management & Nutritionist','9876543212','rohit@fitforge.local',5,NULL,'Certified clinical sports nutritionist and fat-loss conditioning trainer.','active',1,'2026-11-04 10:47:37'),(5,'Rohan Verma','Fitness Coach','+91 9876512345','rohan.trainer@gmail.com',1,NULL,'Certified Gym Coach','active',1,'2026-11-09 19:18:18'),(10,'Manoj Rao','Fitness Coach','99825 03946','manoj@gmail.com',1,NULL,'Certified Trainer','active',1,'2026-11-10 07:48:23'),(47,'Coach Vikram Test','Olympic Weightlifting','9876500001','coach_test@fitforge.local',1,NULL,'Certified strength coach','active',1,'2026-11-11 03:06:30');
/*!40000 ALTER TABLE `tbl_trainers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_website_features`
--

DROP TABLE IF EXISTS `tbl_website_features`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_website_features` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `icon` varchar(50) DEFAULT 'fa-bolt',
  `display_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `gym_id` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_features_gym` (`gym_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_website_features`
--

LOCK TABLES `tbl_website_features` WRITE;
/*!40000 ALTER TABLE `tbl_website_features` DISABLE KEYS */;
INSERT INTO `tbl_website_features` VALUES (1,'Elite Coaching','Certified fitness mentors guiding every workout and form.','fa-heartbeat',1,1,1),(2,'Modern Gear','Top-of-the-line biomechanic strength equipment & recovery tools.','fa-trophy',2,1,1),(3,'Vibrant Community','An inclusive network of motivated members keeping you accountable.','fa-users',3,1,1),(4,'Steam & Recovery','Thermal recovery suites and therapeutic stretch stations.','fa-fire',4,1,1);
/*!40000 ALTER TABLE `tbl_website_features` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_workout_plans`
--

DROP TABLE IF EXISTS `tbl_workout_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_workout_plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_muscle` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `exercise_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sets` int NOT NULL DEFAULT '3',
  `reps` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '10-12',
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gym_id` int NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wp_user` (`user_id`),
  KEY `idx_wp_day` (`day_of_week`),
  KEY `idx_wp_gym` (`gym_id`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_workout_plans`
--

LOCK TABLES `tbl_workout_plans` WRITE;
/*!40000 ALTER TABLE `tbl_workout_plans` DISABLE KEYS */;
INSERT INTO `tbl_workout_plans` VALUES (49,1,'Monday','Chest','Barbell Flat Bench Press',4,'8-10 reps','Pyramid weight on each set.',1,'2026-11-04 19:11:16'),(50,1,'Monday','Back','Barbell Bent-Over Row (Overhand)',4,'8-10 reps','Antagonist superset pairing with bench press.',1,'2026-11-04 19:11:16'),(51,1,'Monday','Chest','Incline Dumbbell Bench Press (30°)',4,'10 reps','Deep clavicular stretch.',1,'2026-11-04 19:11:16'),(52,1,'Monday','Back','Wide-Grip Lat Pulldown',4,'10-12 reps','Flare lats wide at the top.',1,'2026-11-04 19:11:16'),(53,1,'Monday','Chest','Dumbbell Pullover (Lying on Bench)',3,'12-15 reps','Expand rib cage and stretch lats.',1,'2026-11-04 19:11:16'),(54,1,'Tuesday','Shoulders','Seated Arnold Dumbbell Press',4,'10 reps','Rotate wrists smoothly from supination to pronation.',1,'2026-11-04 19:11:16'),(55,1,'Tuesday','Shoulders','Standing Dumbbell Lateral Raise',4,'12-15 reps','Lead with elbows.',1,'2026-11-04 19:11:16'),(56,1,'Tuesday','Biceps','Barbell Standing Biceps Curl',4,'10 reps','Strict curl cadence.',1,'2026-11-04 19:11:16'),(57,1,'Tuesday','Triceps','Parallel Bar Triceps Dips (Bodyweight/Weighted)',4,'10-12 reps','Stay upright to focus strictly on triceps.',1,'2026-11-04 19:11:16'),(58,1,'Tuesday','Biceps','Incline Dumbbell Biceps Curl (45°)',3,'12 reps','Long head biceps stretch.',1,'2026-11-04 19:11:16'),(59,1,'Tuesday','Triceps','Cable Overhead Rope Triceps Extension',3,'12-15 reps','Deep tricep long-head extension.',1,'2026-11-04 19:11:16'),(60,1,'Wednesday','Legs','Barbell High-Bar Back Squat',4,'8-10 reps','Full depth.',1,'2026-11-04 19:11:16'),(61,1,'Wednesday','Legs','Barbell Romanian Deadlift (RDL)',4,'10 reps','Hamstring stretch.',1,'2026-11-04 19:11:16'),(62,1,'Wednesday','Legs','Leg Press (45° Incline)',3,'12-15 reps','Quad pump.',1,'2026-11-04 19:11:16'),(63,1,'Wednesday','Legs','Standing Machine Calf Raise',4,'15 reps','Full calf stretch.',1,'2026-11-04 19:11:16'),(64,1,'Wednesday','Abs & Core','Hanging Leg Raises',3,'15-20 reps','Strict core contraction.',1,'2026-11-04 19:11:16'),(65,1,'Thursday','Chest','Flat Dumbbell Bench Press',4,'10 reps','Converge dumbbells at apex.',1,'2026-11-04 19:11:16'),(66,1,'Thursday','Back','Seated Cable Row (Neutral Grip)',4,'10 reps','Squeeze rhomboids.',1,'2026-11-04 19:11:16'),(67,1,'Thursday','Chest','Cable High-to-Low Crossover Fly',3,'12-15 reps','Lower chest focus.',1,'2026-11-04 19:11:16'),(68,1,'Thursday','Back','One-Arm Dumbbell Row',3,'10 reps/side','Pull towards hip pocket.',1,'2026-11-04 19:11:16'),(69,1,'Friday','Shoulders','Standing Barbell Overhead Press (OHP)',4,'8-10 reps','Strict military press.',1,'2026-11-04 19:11:16'),(70,1,'Friday','Shoulders','Cable Lateral Raise',4,'12 reps','Constant lateral delt tension.',1,'2026-11-04 19:11:16'),(71,1,'Friday','Biceps','Dumbbell Hammer Curls',3,'12 reps','Brachialis thickness.',1,'2026-11-04 19:11:16'),(72,1,'Friday','Triceps','Cable Triceps Straight-Bar Pushdown',3,'12-15 reps','Lockout triceps.',1,'2026-11-04 19:11:16'),(73,1,'Saturday','Legs','Hack Squat (Machine)',4,'10-12 reps','Deep knee flexion.',1,'2026-11-04 19:11:16'),(74,1,'Saturday','Legs','Lying Hamstring Leg Curl',4,'12 reps','Hamstring isolation.',1,'2026-11-04 19:11:16'),(75,1,'Saturday','Legs','Walking Dumbbell Lunges',3,'12 steps/leg','Glute burn.',1,'2026-11-04 19:11:16'),(76,1,'Saturday','Abs & Core','Cable Woodchoppers (High-to-Low)',3,'15 reps/side','Rotational core power.',1,'2026-11-04 19:11:16');
/*!40000 ALTER TABLE `tbl_workout_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_workout_presets`
--

DROP TABLE IF EXISTS `tbl_workout_presets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_workout_presets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL DEFAULT '0',
  `gym_id` int NOT NULL DEFAULT '1',
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tag` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Custom Split',
  `difficulty` enum('Beginner','Intermediate','Advanced') COLLATE utf8mb4_unicode_ci DEFAULT 'Intermediate',
  `days_count` int NOT NULL DEFAULT '3',
  `description` text COLLATE utf8mb4_unicode_ci,
  `exercises_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_presets_user` (`user_id`,`is_system`),
  KEY `idx_presets_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_workout_presets`
--

LOCK TABLES `tbl_workout_presets` WRITE;
/*!40000 ALTER TABLE `tbl_workout_presets` DISABLE KEYS */;
INSERT INTO `tbl_workout_presets` VALUES (2,1,1,'My 4-Day Strength & Aesthetic Split',NULL,'4-Day Hypertrophy','Intermediate',4,'Personalized 4-day training plan focusing on chest clavicular growth, V-taper lats, and strict compound lifts.','[{\"day\":\"Monday\",\"muscle\":\"Chest\",\"name\":\"Incline Dumbbell Bench Press (30°)\",\"sets\":4,\"reps\":\"8-10 reps\",\"notes\":\"Deep stretch on clavicular pecs.\"},{\"day\":\"Monday\",\"muscle\":\"Chest\",\"name\":\"Barbell Flat Bench Press\",\"sets\":4,\"reps\":\"6-8 reps\",\"notes\":\"Heavy compound strength.\"},{\"day\":\"Monday\",\"muscle\":\"Triceps\",\"name\":\"Cable Triceps Rope Pushdown\",\"sets\":3,\"reps\":\"12-15 reps\",\"notes\":\"Lockout contraction.\"},{\"day\":\"Tuesday\",\"muscle\":\"Back\",\"name\":\"Barbell Bent-Over Row (Overhand)\",\"sets\":4,\"reps\":\"8-10 reps\",\"notes\":\"Strict form, 45 degree torso.\"},{\"day\":\"Tuesday\",\"muscle\":\"Back\",\"name\":\"Wide-Grip Lat Pulldown\",\"sets\":4,\"reps\":\"10-12 reps\",\"notes\":\"V-taper width.\"},{\"day\":\"Tuesday\",\"muscle\":\"Biceps\",\"name\":\"Incline Dumbbell Biceps Curl (45°)\",\"sets\":3,\"reps\":\"12 reps\",\"notes\":\"Long head biceps peak.\"},{\"day\":\"Thursday\",\"muscle\":\"Legs\",\"name\":\"Barbell High-Bar Back Squat\",\"sets\":4,\"reps\":\"8 reps\",\"notes\":\"Full depth below parallel.\"},{\"day\":\"Thursday\",\"muscle\":\"Legs\",\"name\":\"Barbell Romanian Deadlift (RDL)\",\"sets\":3,\"reps\":\"10 reps\",\"notes\":\"Hamstring stretch.\"},{\"day\":\"Friday\",\"muscle\":\"Shoulders\",\"name\":\"Standing Barbell Overhead Press (OHP)\",\"sets\":4,\"reps\":\"8 reps\",\"notes\":\"Strict military overhead drive.\"},{\"day\":\"Friday\",\"muscle\":\"Shoulders\",\"name\":\"Cable Lateral Raise\",\"sets\":4,\"reps\":\"15 reps\",\"notes\":\"Continuous lateral delt tension.\"}]',0,'2026-11-04 19:03:24','2026-11-04 19:03:24');
/*!40000 ALTER TABLE `tbl_workout_presets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbladdpackage`
--

DROP TABLE IF EXISTS `tbladdpackage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbladdpackage` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category` varchar(45) DEFAULT NULL,
  `titlename` varchar(450) DEFAULT NULL,
  `PackageType` varchar(45) DEFAULT NULL,
  `PackageDuratiobn` varchar(45) DEFAULT NULL,
  `Price` varchar(45) DEFAULT NULL,
  `uploadphoto` varchar(450) DEFAULT NULL,
  `Description` varchar(450) DEFAULT NULL,
  `create_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `gym_id` int NOT NULL DEFAULT '1',
  `registration_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `display_order` int NOT NULL DEFAULT '0',
  `benefits` text,
  PRIMARY KEY (`id`),
  KEY `idx_pkg_gym` (`gym_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbladdpackage`
--

LOCK TABLES `tbladdpackage` WRITE;
/*!40000 ALTER TABLE `tbladdpackage` DISABLE KEYS */;
INSERT INTO `tbladdpackage` VALUES (1,'1','1 Months Membership Package','1','1 Month','2000','img/uploads/plans/1b1253ff20f41ebda6d7376e37ac4196.png','Take your fitness journey to the next level with six months of dedicated gym access. Build strength, improve endurance, and develop healthy habits with consistent training. Ideal for members looking to achieve visible progress and maintain an active lifestyle.','2022-03-05 02:55:34',1,1,0.00,0.00,0,''),(2,'2','6 Months Membership Package','3','6 Month','5000','img/uploads/plans/96bb67620efa7238fafdb7e138740893.png','Take your fitness journey to the next level with six months of dedicated gym access. Build strength, improve endurance, and develop healthy habits with consistent training. Ideal for members looking to achieve visible progress and maintain an active lifestyle.','2022-03-05 02:56:44',1,1,0.00,0.00,0,''),(3,'4','12 Months Membership Package','5','12 Month','10000','img/uploads/plans/90429b03f581ee68e865ffa07ac2ace7.png','Enjoy a full year of fitness with uninterrupted gym access. Stay committed to your fitness goals with long-term training, strength development, and complete workout facilities. The perfect choice for members dedicated to transforming their lifestyle and achieving lasting results.','2022-05-22 02:34:08',1,1,0.00,0.00,0,'');
/*!40000 ALTER TABLE `tbladdpackage` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbladmin`
--

DROP TABLE IF EXISTS `tbladmin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbladmin` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(45) DEFAULT NULL,
  `email` varchar(45) DEFAULT NULL,
  `mobile` varchar(45) DEFAULT NULL,
  `password` varchar(100) DEFAULT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'floor_staff',
  `status` enum('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  `approved_by` int DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `trainer_id` int DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `create_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `gym_id` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_admin_gym` (`gym_id`),
  KEY `fk_admin_approved_by` (`approved_by`),
  KEY `fk_admin_trainer` (`trainer_id`),
  CONSTRAINT `fk_admin_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `tbladmin` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_admin_trainer` FOREIGN KEY (`trainer_id`) REFERENCES `tbl_trainers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=126 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbladmin`
--

LOCK TABLES `tbladmin` WRITE;
/*!40000 ALTER TABLE `tbladmin` DISABLE KEYS */;
INSERT INTO `tbladmin` VALUES (1,'Aman Mehta','admin@gmail.com','9983665673','$2y$12$ZC1dMGu4XTVpxpA35lMuT.IQCuPvo773q/CW8ndRLC0dAO69Upy4u','owner','approved',NULL,'2026-11-09 18:40:36',NULL,NULL,'2026-11-11 03:08:34','2022-01-19 11:25:17',1),(3,'Rohan Verma','rohan.trainer@gmail.com','+91 9876512345','$2y$12$ZC1dMGu4XTVpxpA35lMuT.IQCuPvo773q/CW8ndRLC0dAO69Upy4u','trainer','approved',1,'2026-11-10 07:11:05',NULL,5,NULL,'2026-11-09 18:59:44',1),(4,'Pooja Sharma','pooja.desk@gmail.com','+91 9876554321','$2y$12$ZC1dMGu4XTVpxpA35lMuT.IQCuPvo773q/CW8ndRLC0dAO69Upy4u','floor_staff','pending',1,'2026-11-11 02:59:38',NULL,NULL,NULL,'2026-11-09 18:59:44',1),(9,'Manoj Rao','manoj@gmail.com','99825 03946','$2y$12$Q.qvKnhzFnj1ONLXnBmhpeRwMbMUGi3rLrSqzDOMHZFW/SHO6Rvx2','trainer','approved',1,'2026-11-10 07:50:03',NULL,10,'2026-11-10 12:10:36','2026-11-10 07:48:23',1),(44,'Manager Test','mgr_20point_1794309475@fitforge.local',NULL,'$2y$12$KynITPvAswzcVqXO3B..8u9ls4WQ3ZrR2Vs8/KudxtxpfglScPTum','manager','approved',1,'2026-11-10 11:17:55',NULL,NULL,NULL,'2026-11-10 11:17:55',1),(45,'Staff Test','staff_20point_1794309475@fitforge.local',NULL,'$2y$12$KynITPvAswzcVqXO3B..8u9ls4WQ3ZrR2Vs8/KudxtxpfglScPTum','floor_staff','approved',1,'2026-11-10 11:17:55',NULL,NULL,NULL,'2026-11-10 11:17:55',1),(124,'Coach Vikram Test','coach_test@fitforge.local','9876500001','$2y$12$8RXN8YGCfRPQRamAhYqQbe1YSkDD6x9xBidtXIeTGv0VaKSI65bfK','trainer','approved',1,'2026-11-11 03:06:31',NULL,47,'2026-11-11 03:06:31','2026-11-11 03:06:30',1);
/*!40000 ALTER TABLE `tbladmin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tblbooking`
--

DROP TABLE IF EXISTS `tblbooking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblbooking` (
  `id` int NOT NULL AUTO_INCREMENT,
  `package_id` varchar(45) DEFAULT NULL,
  `userid` varchar(45) DEFAULT NULL,
  `booking_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `payment` varchar(45) DEFAULT NULL,
  `paymentType` varchar(45) DEFAULT NULL,
  `gym_id` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_booking_gym` (`gym_id`)
) ENGINE=InnoDB AUTO_INCREMENT=99909 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblbooking`
--

LOCK TABLES `tblbooking` WRITE;
/*!40000 ALTER TABLE `tblbooking` DISABLE KEYS */;
INSERT INTO `tblbooking` VALUES (1,'2','1','2022-03-05 03:53:21','800','Cash',1),(2,'1','1','2022-03-05 03:53:28','600','Cash',1),(3,'2','5','2022-03-08 17:44:18','300','Full Payment',1),(6,'1','5','2022-05-22 02:16:14',NULL,NULL,1),(7,'2','6','2022-05-22 02:32:45',NULL,'Cash',1),(8,'1','7','2026-11-04 10:07:16',NULL,NULL,1),(9,'1','7','2026-11-04 10:07:55','2500','Cash',1),(10,'1','5','2026-11-10 07:52:35','2500','Cash',1),(11,'1','1','2026-11-10 07:56:05','2500','Cash',1);
/*!40000 ALTER TABLE `tblbooking` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tblcategory`
--

DROP TABLE IF EXISTS `tblcategory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblcategory` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_name` varchar(45) DEFAULT NULL,
  `status` varchar(45) DEFAULT '0',
  `gym_id` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_cat_gym` (`gym_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblcategory`
--

LOCK TABLES `tblcategory` WRITE;
/*!40000 ALTER TABLE `tblcategory` DISABLE KEYS */;
INSERT INTO `tblcategory` VALUES (1,'Basic','0',1),(2,'Premium','0',1),(4,'Elite','0',1);
/*!40000 ALTER TABLE `tblcategory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tblgyms`
--

DROP TABLE IF EXISTS `tblgyms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblgyms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gym_name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `tagline` varchar(150) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `favicon` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text,
  `currency_code` varchar(10) DEFAULT 'INR',
  `currency_symbol` varchar(10) DEFAULT '₹',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblgyms`
--

LOCK TABLES `tblgyms` WRITE;
/*!40000 ALTER TABLE `tblgyms` DISABLE KEYS */;
INSERT INTO `tblgyms` VALUES (1,'Golden Eagle','golden-eagle','FITNESS CLUB',NULL,NULL,NULL,NULL,NULL,'INR','₹','active','2026-11-04 09:49:02','2026-11-04 09:49:02');
/*!40000 ALTER TABLE `tblgyms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tblpackage`
--

DROP TABLE IF EXISTS `tblpackage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblpackage` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cate_id` varchar(45) DEFAULT NULL,
  `PackageName` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblpackage`
--

LOCK TABLES `tblpackage` WRITE;
/*!40000 ALTER TABLE `tblpackage` DISABLE KEYS */;
INSERT INTO `tblpackage` VALUES (1,'1','Monthly'),(3,'2','Quarterly'),(5,'4','Annually');
/*!40000 ALTER TABLE `tblpackage` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tblpayment`
--

DROP TABLE IF EXISTS `tblpayment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblpayment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bookingID` varchar(45) DEFAULT NULL,
  `membership_id` int DEFAULT NULL,
  `paymentType` varchar(45) DEFAULT NULL,
  `payment` varchar(45) DEFAULT NULL,
  `refunded_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('completed','refunded','partially_refunded') NOT NULL DEFAULT 'completed',
  `payment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `gym_id` int NOT NULL DEFAULT '1',
  `recorded_by_admin_id` int DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT 'Cash',
  `transaction_ref` varchar(100) DEFAULT NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `idx_payment_gym` (`gym_id`),
  KEY `idx_payment_membership` (`membership_id`)
) ENGINE=InnoDB AUTO_INCREMENT=112 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblpayment`
--

LOCK TABLES `tblpayment` WRITE;
/*!40000 ALTER TABLE `tblpayment` DISABLE KEYS */;
INSERT INTO `tblpayment` VALUES (1,'1',1,'Partial Payment','300',0.00,'completed','2022-03-05 03:54:10',1,NULL,'Cash',NULL,NULL),(4,'1',1,'Full Payment','500',0.00,'completed','2022-05-22 01:01:58',1,NULL,'Cash',NULL,NULL),(5,'3',3,'Partial Payment','300',0.00,'completed','2022-05-22 01:09:53',1,NULL,'Cash',NULL,NULL),(8,'3',3,'Full Payment','500',0.00,'completed','2022-05-22 01:19:03',1,NULL,'Cash',NULL,NULL),(9,'7',5,'Partial Payment','500',0.00,'completed','2022-05-22 02:40:34',1,NULL,'Cash',NULL,NULL),(10,'7',5,'Full Payment','300',0.00,'completed','2022-05-22 02:41:14',1,NULL,'Cash',NULL,NULL),(11,'7',5,'Cash','400',0.00,'completed','2026-11-04 10:06:02',1,NULL,'Cash',NULL,NULL),(12,'7',5,'Cash','400',0.00,'completed','2026-11-04 10:06:14',1,NULL,'Cash',NULL,NULL),(13,'7',5,'Cash','400',0.00,'completed','2026-11-04 10:06:20',1,NULL,'Cash',NULL,NULL),(14,'9',7,'Cash','2500',0.00,'completed','2026-11-04 10:07:55',1,NULL,'Cash',NULL,NULL),(15,'1',1,'Cash','4200',0.00,'completed','2026-11-04 16:21:50',1,NULL,'Cash','',''),(16,'10',8,'Cash','2500',0.00,'completed','2026-11-10 07:52:35',1,NULL,'Cash',NULL,NULL),(17,'11',9,'Cash','2500',0.00,'completed','2026-11-10 07:56:05',1,NULL,'Cash',NULL,NULL),(18,'2',2,'Cash','2500',0.00,'completed','2026-11-10 09:30:00',1,1,'Cash','65500','Payment received'),(29,'2',2,'Cash','2500',0.00,'completed','2026-11-10 09:42:22',1,1,'Cash','65500','Payment received');
/*!40000 ALTER TABLE `tblpayment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbluser`
--

DROP TABLE IF EXISTS `tbluser`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbluser` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fname` varchar(45) DEFAULT NULL,
  `lname` varchar(45) DEFAULT NULL,
  `email` varchar(45) DEFAULT NULL,
  `mobile` varchar(45) DEFAULT NULL,
  `password` varchar(100) DEFAULT NULL,
  `state` varchar(45) DEFAULT NULL,
  `city` varchar(45) DEFAULT NULL,
  `address` varchar(200) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('male','female','other','prefer_not_to_say') DEFAULT 'prefer_not_to_say',
  `height` decimal(5,2) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `fitness_goal` enum('fat_loss','muscle_gain','strength','endurance','general_fitness','maintenance') DEFAULT 'general_fitness',
  `activity_level` enum('sedentary','lightly_active','moderately_active','very_active','extra_active') DEFAULT 'moderately_active',
  `unit_preference` enum('metric','imperial') DEFAULT 'metric',
  `target_weight` decimal(5,2) DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(30) DEFAULT NULL,
  `create_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `notes` text,
  `gym_id` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_user_gym` (`gym_id`)
) ENGINE=InnoDB AUTO_INCREMENT=108 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbluser`
--

LOCK TABLES `tbluser` WRITE;
/*!40000 ALTER TABLE `tbluser` DISABLE KEYS */;
INSERT INTO `tbluser` VALUES (1,'Aman','Mehta','atul@gmail.com','9983665673','$2y$12$Rf/KKHMrkVUgnLCbe/NbtutwBUiod1dUmnDxKpXsv0toKkudbytwi','Rajasthan','Manglwad','Mehta Restaurant','2002-01-23','male',178.00,'img/profiles/8fe3ab4dd4de84f56728e1657a8e9158.jpg','muscle_gain','very_active','metric',80.00,'2027-01-03','Umesh Mehta','9950506514','2022-02-16 16:48:25','active',NULL,1),(2,'ddd','ddf','df@gmail.com','9968556','e10adc3949ba59abbe56e057f20f883e','fgfg','fsdf',NULL,NULL,'prefer_not_to_say',NULL,NULL,'general_fitness','moderately_active','metric',NULL,NULL,NULL,NULL,'2022-02-16 17:00:20','active',NULL,1),(3,'anuj','kumar','anuj@gmail.com','9999999999','f925916e2754e5e03f75dd58a5733251','up','noida',NULL,NULL,'prefer_not_to_say',NULL,NULL,'general_fitness','moderately_active','metric',NULL,NULL,NULL,NULL,'2022-03-02 15:37:22','active',NULL,1),(4,'sssssss','sssssss','sssssss','sssssss','f925916e2754e5e03f75dd58a5733251','sssssss','sssssss',NULL,NULL,'prefer_not_to_say',NULL,NULL,'general_fitness','moderately_active','metric',NULL,NULL,NULL,NULL,'2022-03-05 03:27:28','active',NULL,1),(5,'Anuj k','kumar','anuj.doca@Gmail.com','1234567890','202cb962ac59075b964b07152d234b70','sghsdg','sahgsh',NULL,NULL,'prefer_not_to_say',NULL,NULL,'general_fitness','moderately_active','metric',NULL,NULL,NULL,NULL,'2022-03-08 17:43:23','active',NULL,1),(6,'John','Doe','john@test.com','1425635241','f925916e2754e5e03f75dd58a5733251','Delhi','New Delhi','ABC Street XYZ Colony',NULL,'prefer_not_to_say',NULL,NULL,'general_fitness','moderately_active','metric',NULL,NULL,NULL,NULL,'2022-05-22 02:31:54','active',NULL,1),(7,'Aman','Mehta','amanmehta519@gmail.com','9983665673','$2y$12$Z.ZxiRYO1F3rCIjg2ngAJOyMtSZllRZoBgiHiuoPOCePQ8qbmcgWK','Rajasthan','Mangalwad Chouraha Dist. Chittorgarh','',NULL,'male',NULL,NULL,'muscle_gain','very_active','metric',80.00,NULL,'','','2026-11-04 05:34:42','active',NULL,1),(36,'Alice','Security','mema_20point_1794309475@fitforge.local','9870000001','$2y$12$KynITPvAswzcVqXO3B..8u9ls4WQ3ZrR2Vs8/KudxtxpfglScPTum',NULL,NULL,NULL,NULL,'prefer_not_to_say',NULL,NULL,'general_fitness','moderately_active','metric',NULL,NULL,NULL,NULL,'2026-11-10 11:17:55','inactive',NULL,1),(37,'Bob','Security','memb_20point_1794309475@fitforge.local','9870000002','$2y$12$KynITPvAswzcVqXO3B..8u9ls4WQ3ZrR2Vs8/KudxtxpfglScPTum',NULL,NULL,NULL,NULL,'prefer_not_to_say',NULL,NULL,'general_fitness','moderately_active','metric',NULL,NULL,NULL,NULL,'2026-11-10 11:17:55','inactive',NULL,1);
/*!40000 ALTER TABLE `tbluser` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-11 11:42:15
