-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: parking_db_v2
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('superadmin','admin','operator') NOT NULL DEFAULT 'operator',
  `full_name` varchar(100) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `floor`
--

DROP TABLE IF EXISTS `floor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `floor` (
  `floor_id` int(11) NOT NULL AUTO_INCREMENT,
  `floor_code` varchar(10) NOT NULL,
  `floor_name` varchar(50) NOT NULL,
  `total_car_slots` int(11) NOT NULL DEFAULT 0,
  `total_motorcycle_slots` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`floor_id`),
  UNIQUE KEY `uk_floor_code` (`floor_code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `operator`
--

DROP TABLE IF EXISTS `operator`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `operator` (
  `operator_id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `shift` varchar(50) DEFAULT NULL,
  `staff_type` enum('admin','operator') DEFAULT 'operator',
  `phone` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`operator_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `parking_rate`
--

DROP TABLE IF EXISTS `parking_rate`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `parking_rate` (
  `rate_id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_type` enum('car','motorcycle') NOT NULL,
  `first_hour_rate` decimal(10,2) NOT NULL,
  `next_hour_rate` decimal(10,2) NOT NULL,
  `daily_max_rate` decimal(10,2) NOT NULL,
  PRIMARY KEY (`rate_id`),
  UNIQUE KEY `uk_vehicle_type` (`vehicle_type`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `parking_slot`
--

DROP TABLE IF EXISTS `parking_slot`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `parking_slot` (
  `slot_id` int(11) NOT NULL AUTO_INCREMENT,
  `slot_number` varchar(10) NOT NULL,
  `slot_type` enum('car','motorcycle') NOT NULL,
  `floor_id` int(11) NOT NULL,
  `status` enum('available','occupied','reserved','maintenance') NOT NULL DEFAULT 'available',
  `is_reservation_only` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`slot_id`),
  UNIQUE KEY `uk_slot_number` (`slot_number`),
  KEY `idx_status` (`status`),
  KEY `idx_floor_type` (`floor_id`,`slot_type`),
  CONSTRAINT `fk_slot_floor` FOREIGN KEY (`floor_id`) REFERENCES `floor` (`floor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `plate_scan_log`
--

DROP TABLE IF EXISTS `plate_scan_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plate_scan_log` (
  `scan_id` int(11) NOT NULL AUTO_INCREMENT,
  `plate_number` varchar(15) DEFAULT NULL,
  `scan_time` datetime NOT NULL DEFAULT current_timestamp(),
  `scan_type` enum('entry','exit') NOT NULL,
  `ticket_code` varchar(20) DEFAULT NULL,
  `matched` tinyint(1) NOT NULL DEFAULT 0,
  `gate_action` enum('open','reject') NOT NULL DEFAULT 'reject',
  PRIMARY KEY (`scan_id`),
  KEY `idx_scan_time` (`scan_time`),
  KEY `idx_ticket_code` (`ticket_code`),
  CONSTRAINT `fk_scan_ticket` FOREIGN KEY (`ticket_code`) REFERENCES `ticket` (`ticket_code`)
) ENGINE=InnoDB AUTO_INCREMENT=802 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reservation`
--

DROP TABLE IF EXISTS `reservation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reservation` (
  `reservation_id` int(11) NOT NULL AUTO_INCREMENT,
  `plate_number` varchar(15) DEFAULT NULL,
  `vehicle_id` int(11) NOT NULL,
  `slot_id` int(11) NOT NULL,
  `reservation_code` varchar(20) NOT NULL,
  `reserved_from` datetime NOT NULL,
  `reserved_until` datetime NOT NULL,
  `status` enum('pending','confirmed','cancelled','expired','used') NOT NULL DEFAULT 'pending',
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`reservation_id`),
  UNIQUE KEY `uk_reservation_code` (`reservation_code`),
  KEY `fk_res_vehicle` (`vehicle_id`),
  KEY `fk_res_slot` (`slot_id`),
  KEY `idx_res_status` (`status`),
  KEY `idx_plate_lookup` (`plate_number`,`status`),
  CONSTRAINT `fk_res_slot` FOREIGN KEY (`slot_id`) REFERENCES `parking_slot` (`slot_id`),
  CONSTRAINT `fk_res_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicle` (`vehicle_id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `shift_attendance`
--

DROP TABLE IF EXISTS `shift_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shift_attendance` (
  `attendance_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `check_in_time` datetime DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`attendance_id`),
  KEY `user_id` (`user_id`),
  KEY `staff_id` (`staff_id`),
  CONSTRAINT `shift_attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `shift_attendance_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `operator` (`operator_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket`
--

DROP TABLE IF EXISTS `ticket`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket` (
  `ticket_id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_code` varchar(20) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `issued_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','used','void') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`ticket_id`),
  UNIQUE KEY `uk_ticket_code` (`ticket_code`),
  KEY `fk_ticket_trx` (`transaction_id`),
  CONSTRAINT `fk_ticket_trx` FOREIGN KEY (`transaction_id`) REFERENCES `transaction` (`transaction_id`)
) ENGINE=InnoDB AUTO_INCREMENT=353 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transaction`
--

DROP TABLE IF EXISTS `transaction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaction` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `slot_id` int(11) NOT NULL,
  `operator_id` int(11) NOT NULL,
  `rate_id` int(11) NOT NULL,
  `reservation_id` int(11) DEFAULT NULL,
  `ticket_code` varchar(20) DEFAULT NULL,
  `check_in_time` datetime NOT NULL DEFAULT current_timestamp(),
  `check_out_time` datetime DEFAULT NULL,
  `duration_hours` decimal(5,2) DEFAULT NULL,
  `total_fee` decimal(10,2) DEFAULT NULL,
  `payment_method` enum('cash','card','e-wallet') NOT NULL DEFAULT 'cash',
  `payment_status` enum('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  `is_lost_ticket` tinyint(1) DEFAULT 0,
  `is_force_checkout` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`transaction_id`),
  KEY `fk_trx_vehicle` (`vehicle_id`),
  KEY `fk_trx_slot` (`slot_id`),
  KEY `fk_trx_operator` (`operator_id`),
  KEY `fk_trx_rate` (`rate_id`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_check_out_time` (`check_out_time`),
  KEY `idx_check_in_time` (`check_in_time`)
) ENGINE=InnoDB AUTO_INCREMENT=354 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transaction_log`
--

DROP TABLE IF EXISTS `transaction_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaction_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) DEFAULT NULL,
  `action_time` datetime DEFAULT current_timestamp(),
  `action_type` varchar(50) DEFAULT NULL,
  `total_fee` decimal(10,2) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`log_id`),
  KEY `idx_trx_log_time` (`action_time`),
  KEY `fk_log_trx` (`transaction_id`),
  CONSTRAINT `fk_log_trx` FOREIGN KEY (`transaction_id`) REFERENCES `transaction` (`transaction_id`)
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vehicle`
--

DROP TABLE IF EXISTS `vehicle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicle` (
  `vehicle_id` int(11) NOT NULL AUTO_INCREMENT,
  `plate_number` varchar(15) DEFAULT NULL,
  `vehicle_type` enum('car','motorcycle') NOT NULL,
  `owner_name` varchar(100) NOT NULL DEFAULT 'Guest',
  `owner_phone` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`vehicle_id`),
  UNIQUE KEY `uk_plate_number` (`plate_number`),
  KEY `idx_plate` (`plate_number`)
) ENGINE=InnoDB AUTO_INCREMENT=373 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-28  0:08:48
