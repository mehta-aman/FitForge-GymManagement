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
  `status` enum('active','expired','pending','cancelled') NOT NULL DEFAULT 'active',
  `payment_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_method` varchar(50) DEFAULT 'Cash',
  `transaction_ref` varchar(100) DEFAULT NULL,
  `admin_notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `plan_id` (`plan_id`),
  KEY `booking_id` (`booking_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_memberships`
--

LOCK TABLES `tbl_memberships` WRITE;
/*!40000 ALTER TABLE `tbl_memberships` DISABLE KEYS */;
INSERT INTO `tbl_memberships` VALUES (1,1,2,1,'2022-03-05','2022-09-05','expired','paid',800.00,800.00,'Cash',NULL,NULL,'2022-03-05 03:53:21'),(2,1,1,2,'2022-03-05','2022-06-05','expired','paid',600.00,600.00,'Cash',NULL,NULL,'2022-03-05 03:53:28'),(3,5,2,3,'2022-03-08','2022-09-08','expired','paid',800.00,800.00,'Cash',NULL,NULL,'2022-03-08 17:44:18'),(4,5,1,6,'2022-05-22','2022-08-22','expired','unpaid',600.00,0.00,'Cash',NULL,NULL,'2022-05-22 02:16:14'),(5,6,2,7,'2022-05-22','2022-11-22','expired','paid',800.00,800.00,'Cash',NULL,NULL,'2022-05-22 02:32:45');
/*!40000 ALTER TABLE `tbl_memberships` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=140 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_settings`
--

LOCK TABLES `tbl_settings` WRITE;
/*!40000 ALTER TABLE `tbl_settings` DISABLE KEYS */;
INSERT INTO `tbl_settings` VALUES (1,'general','gym_name','Golden Eagle','2026-11-04 06:35:53'),(2,'general','gym_tagline','FITNESS CLUB','2026-11-04 05:51:53'),(3,'general','website_title','Golden Eagle FITNESS CLUB — Elevate Your Fitness','2026-11-04 06:35:53'),(4,'general','seo_description','Next generation fitness club offering personalized training, premium recovery, and flexible membership passes.','2026-11-04 05:51:53'),(5,'branding','gym_logo','img/branding/37071fc5403ce9978504c7bc1a88a1f5.png','2026-11-04 06:48:03'),(6,'branding','gym_favicon','img/branding/af78ed7a29bc3900dca0c55e7d0c8bbc.png','2026-11-04 06:48:19'),(7,'general','currency_symbol','₹','2026-11-04 06:46:01'),(8,'general','currency_code','INR','2026-11-04 06:46:01'),(9,'localization','timezone','Asia/Kolkata','2026-11-04 05:51:53'),(10,'localization','date_format','d M Y','2026-11-04 05:51:53'),(11,'contact','contact_email','contact@gymclub.com','2026-11-04 05:51:53'),(12,'contact','contact_phone','+91 9983665673','2026-11-04 06:35:53'),(13,'contact','contact_whatsapp','+91 9983665673','2026-11-04 06:35:53'),(14,'contact','contact_address','Radha Krishkna Mandir, Nimbaheda Road, Mangalwad Chouraha','2026-11-04 06:35:53'),(15,'contact','map_link','https://maps.google.com','2026-11-04 05:51:53'),(16,'social','social_facebook','https://facebook.com','2026-11-04 05:51:53'),(17,'social','social_instagram','https://instagram.com','2026-11-04 05:51:53'),(18,'social','social_twitter','https://twitter.com','2026-11-04 05:51:53'),(19,'social','social_youtube','https://youtube.com','2026-11-04 05:51:53'),(20,'hero','hero_pill','NEXT GENERATION FITNESS','2026-11-04 05:51:53'),(21,'hero','hero_title','Sculpt Your Body. Elevate Your Mind.','2026-11-04 05:51:53'),(22,'hero','hero_subtitle','Experience bespoke coaching, elite recovery zones, and flexible membership passes with iOS-grade precision.','2026-11-04 05:51:53'),(23,'hero','hero_cta_text','Explore Memberships','2026-11-04 05:51:53'),(24,'hero','hero_cta_link','#plans','2026-11-04 05:51:53'),(25,'about','about_pill','OUR MISSION','2026-11-04 05:51:53'),(26,'about','about_title','Empowering Lifelong Fitness','2026-11-04 05:51:53'),(27,'about','about_text','Welcome to GYM MS! We are a collective of fitness enthusiasts, certified coaches, and nutritionists dedicated to redefining your health journey.','2026-11-04 05:51:53'),(28,'footer','footer_copy','GYM Management System. Crafted with iOS-inspired Glassmorphism & High-Performance Motion.','2026-11-04 05:51:53'),(29,'theme','theme_primary','#00e04f','2026-11-04 07:01:43'),(30,'theme','theme_accent','#04b7d7','2026-11-04 07:01:43'),(31,'theme','theme_mode','dark','2026-11-04 06:40:17'),(32,'theme','theme_preset','custom','2026-11-04 07:01:43'),(61,'membership','renewal_reminder_days','3','2026-11-04 06:46:01'),(108,'theme','btn_primary_bg','#00c244','2026-11-04 07:01:43'),(109,'theme','btn_primary_text','#ffffff','2026-11-04 06:59:34'),(110,'theme','btn_secondary_bg','#064e3b','2026-11-04 07:00:42'),(111,'theme','btn_secondary_text','#ecfdf5','2026-11-04 07:00:42'),(112,'theme','btn_secondary_border','#047857','2026-11-04 07:00:42');
/*!40000 ALTER TABLE `tbl_settings` ENABLE KEYS */;
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_website_features`
--

LOCK TABLES `tbl_website_features` WRITE;
/*!40000 ALTER TABLE `tbl_website_features` DISABLE KEYS */;
INSERT INTO `tbl_website_features` VALUES (1,'Elite Coaching','Certified fitness mentors guiding every workout and form.','fa-heartbeat',1,1),(2,'Modern Gear','Top-of-the-line biomechanic strength equipment & recovery tools.','fa-trophy',2,0),(3,'Vibrant Community','An inclusive network of motivated members keeping you accountable.','fa-users',3,1),(4,'Steam & Recovery','Thermal recovery suites and therapeutic stretch stations.','fa-fire',4,1);
/*!40000 ALTER TABLE `tbl_website_features` ENABLE KEYS */;
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbladdpackage`
--

LOCK TABLES `tbladdpackage` WRITE;
/*!40000 ALTER TABLE `tbladdpackage` DISABLE KEYS */;
INSERT INTO `tbladdpackage` VALUES (1,'1','3 Months Membership Package','1','3 Month','2500','img/uploads/plans/1b1253ff20f41ebda6d7376e37ac4196.png','Free Fitness Gear\r\nComplimentary OnePass','2022-03-05 02:55:34',1),(2,'1','6 Months Membership Package','1','6 Month','5000','img/uploads/plans/96bb67620efa7238fafdb7e138740893.png','Book Six Days Different Trainers Class designed for fast Weight Loss / Weight Gain with combination of Latest Workouts in addition to complimentary access to gym area with personal training.','2022-03-05 02:56:44',1),(3,'1','12 Months Membership Package','1','12 Month','10000','img/uploads/plans/90429b03f581ee68e865ffa07ac2ace7.png','hfdgfhfgh<div><br></div><div>fdgdfg</div>','2022-05-22 02:34:08',1);
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
  `create_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbladmin`
--

LOCK TABLES `tbladmin` WRITE;
/*!40000 ALTER TABLE `tbladmin` DISABLE KEYS */;
INSERT INTO `tbladmin` VALUES (1,'admin','admin@gmail.com','99197896857','$2y$12$ZC1dMGu4XTVpxpA35lMuT.IQCuPvo773q/CW8ndRLC0dAO69Upy4u','2022-01-19 11:25:17');
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblbooking`
--

LOCK TABLES `tblbooking` WRITE;
/*!40000 ALTER TABLE `tblbooking` DISABLE KEYS */;
INSERT INTO `tblbooking` VALUES (1,'2','1','2022-03-05 03:53:21','800','Partial Payment'),(2,'1','1','2022-03-05 03:53:28','600','Partial Payment'),(3,'2','5','2022-03-08 17:44:18','300','Full Payment'),(6,'1','5','2022-05-22 02:16:14',NULL,NULL),(7,'2','6','2022-05-22 02:32:45',NULL,'Full Payment');
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblcategory`
--

LOCK TABLES `tblcategory` WRITE;
/*!40000 ALTER TABLE `tblcategory` DISABLE KEYS */;
INSERT INTO `tblcategory` VALUES (1,'Category1','0'),(2,'Category2','0');
/*!40000 ALTER TABLE `tblcategory` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblpackage`
--

LOCK TABLES `tblpackage` WRITE;
/*!40000 ALTER TABLE `tblpackage` DISABLE KEYS */;
INSERT INTO `tblpackage` VALUES (1,'1','fdgdfg'),(3,'2','Package2');
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
  `paymentType` varchar(45) DEFAULT NULL,
  `payment` varchar(45) DEFAULT NULL,
  `payment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblpayment`
--

LOCK TABLES `tblpayment` WRITE;
/*!40000 ALTER TABLE `tblpayment` DISABLE KEYS */;
INSERT INTO `tblpayment` VALUES (1,'1','Partial Payment','300','2022-03-05 03:54:10'),(4,'1','Full Payment','500','2022-05-22 01:01:58'),(5,'3','Partial Payment','300','2022-05-22 01:09:53'),(8,'3','Full Payment','500','2022-05-22 01:19:03'),(9,'7','Partial Payment','500','2022-05-22 02:40:34'),(10,'7','Full Payment','300','2022-05-22 02:41:14');
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
  `create_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `notes` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbluser`
--

LOCK TABLES `tbluser` WRITE;
/*!40000 ALTER TABLE `tbluser` DISABLE KEYS */;
INSERT INTO `tbluser` VALUES (1,'atul','kumar','atul@gmail.com','8888888888','f925916e2754e5e03f75dd58a5733251','Uttar Pradesh','niuda','e-48 new asholk nagar hdd ','2022-02-16 16:48:25','active',NULL),(2,'ddd','ddf','df@gmail.com','9968556','e10adc3949ba59abbe56e057f20f883e','fgfg','fsdf',NULL,'2022-02-16 17:00:20','active',NULL),(3,'anuj','kumar','anuj@gmail.com','9999999999','f925916e2754e5e03f75dd58a5733251','up','noida',NULL,'2022-03-02 15:37:22','active',NULL),(4,'sssssss','sssssss','sssssss','sssssss','f925916e2754e5e03f75dd58a5733251','sssssss','sssssss',NULL,'2022-03-05 03:27:28','active',NULL),(5,'Anuj k','kumar','anuj.doca@Gmail.com','1234567890','202cb962ac59075b964b07152d234b70','sghsdg','sahgsh',NULL,'2022-03-08 17:43:23','active',NULL),(6,'John','Doe','john@test.com','1425635241','f925916e2754e5e03f75dd58a5733251','Delhi','New Delhi','ABC Street XYZ Colony','2022-05-22 02:31:54','active',NULL),(7,'Aman','Mehta','amanmehta519@gmail.com','9983665673','$2y$12$Z.ZxiRYO1F3rCIjg2ngAJOyMtSZllRZoBgiHiuoPOCePQ8qbmcgWK','Rajasthan','Mangalwad Chouraha Dist. Chittorgarh',NULL,'2026-11-04 05:34:42','active',NULL);
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

-- Dump completed on 2026-11-04 15:18:32
