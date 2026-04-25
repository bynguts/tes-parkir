-- ============================================================
-- PARKING SYSTEM v2 — 3NF-Compliant Schema
-- Database: parking_db_v2
-- 
-- PERUBAHAN DARI VERSI LAMA (pelanggaran 3NF yang diperbaiki):
--
--  [FIX 1] parking_slot.floor (varchar) → floor_id (INT FK ke tabel floor)
--          Sebelumnya: slot_id → floor_code(string) → floor_name, total_slots
--          Ini transitive dependency karena floor_code bukan PK.
--          Solusi: simpan floor_id sebagai FK, bukan copy floor_code-nya.
--
--  [FIX 2] ticket.plate_number dihapus
--          Sebelumnya: ticket_id → transaction_id → vehicle_id → plate_number
--          plate_number bisa didapat via JOIN, menyimpannya di ticket = redundan
--          dan melanggar 3NF (transitive dependency lewat transaction_id).
--
--  [FIX 3] plate_scan_log.ticket_code → tambah FK ke ticket.ticket_code
--  [FIX 4] transaction_log.transaction_id → tambah FK ke transaction.transaction_id
--          (bukan pelanggaran 3NF, tapi penting untuk referential integrity)
-- ============================================================
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET NAMES utf8mb4;
SET time_zone = "+07:00";

CREATE DATABASE IF NOT EXISTS `parking_db_v2` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `parking_db_v2`;

-- ============================================================
-- TABLE: operator
-- ============================================================
CREATE TABLE `operator` (
  `operator_id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `shift` enum('morning','afternoon','night') NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`operator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `operator` VALUES
(1,'Rizky Pratama','morning','089001122334',NOW()),
(2,'Sari Indah','afternoon','089002233445',NOW()),
(3,'Tono Budiman','night','089003344556',NOW()),
(4,'Ulan Permata','morning','089004455667',NOW());

-- ============================================================
-- TABLE: parking_rate
-- ============================================================
CREATE TABLE `parking_rate` (
  `rate_id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_type` enum('car','motorcycle') NOT NULL,
  `first_hour_rate` decimal(10,2) NOT NULL,
  `next_hour_rate` decimal(10,2) NOT NULL,
  `daily_max_rate` decimal(10,2) NOT NULL,
  PRIMARY KEY (`rate_id`),
  UNIQUE KEY `uk_vehicle_type` (`vehicle_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `parking_rate` VALUES
(1,'car',4000.00,2000.00,50000.00),
(2,'motorcycle',2000.00,1000.00,20000.00);

-- ============================================================
-- TABLE: floor
-- ============================================================
CREATE TABLE `floor` (
  `floor_id` int(11) NOT NULL AUTO_INCREMENT,
  `floor_code` varchar(10) NOT NULL,
  `floor_name` varchar(50) NOT NULL,
  `total_car_slots` int(11) NOT NULL DEFAULT 0,
  `total_motorcycle_slots` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`floor_id`),
  UNIQUE KEY `uk_floor_code` (`floor_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `floor` VALUES
(1,'G','Ground Floor',10,10),
(2,'L1','Level 1',10,10),
(3,'L2','Level 2',8,10);

-- ============================================================
-- TABLE: parking_slot
-- [FIX 1] Kolom `floor` varchar(10) diganti `floor_id` INT FK
--
-- Sebelumnya kolom floor menyimpan string seperti 'G','L1','L2'
-- sehingga terjadi transitive dependency:
--   slot_id → floor_code → floor_name, total_car_slots, ...
-- Padahal tabel `floor` sudah ada. Kolom floor seharusnya FK.
-- ============================================================
CREATE TABLE `parking_slot` (
  `slot_id` int(11) NOT NULL AUTO_INCREMENT,
  `slot_number` varchar(10) NOT NULL,
  `slot_type` enum('car','motorcycle') NOT NULL,
  `floor_id` int(11) NOT NULL,                        -- DIUBAH dari: `floor` varchar(10)
  `status` enum('available','occupied','reserved','maintenance') NOT NULL DEFAULT 'available',
  PRIMARY KEY (`slot_id`),
  UNIQUE KEY `uk_slot_number` (`slot_number`),
  KEY `idx_status` (`status`),
  KEY `idx_floor_type` (`floor_id`, `slot_type`),
  CONSTRAINT `fk_slot_floor` FOREIGN KEY (`floor_id`) REFERENCES `floor` (`floor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `parking_slot` (`slot_number`,`slot_type`,`floor_id`) VALUES
-- Ground Floor (floor_id = 1)
('C-G01','car',1),('C-G02','car',1),('C-G03','car',1),('C-G04','car',1),('C-G05','car',1),
('C-G06','car',1),('C-G07','car',1),('C-G08','car',1),('C-G09','car',1),('C-G10','car',1),
('M-G01','motorcycle',1),('M-G02','motorcycle',1),('M-G03','motorcycle',1),('M-G04','motorcycle',1),('M-G05','motorcycle',1),
('M-G06','motorcycle',1),('M-G07','motorcycle',1),('M-G08','motorcycle',1),('M-G09','motorcycle',1),('M-G10','motorcycle',1),
-- Level 1 (floor_id = 2)
('C-L101','car',2),('C-L102','car',2),('C-L103','car',2),('C-L104','car',2),('C-L105','car',2),
('C-L106','car',2),('C-L107','car',2),('C-L108','car',2),('C-L109','car',2),('C-L110','car',2),
('M-L101','motorcycle',2),('M-L102','motorcycle',2),('M-L103','motorcycle',2),('M-L104','motorcycle',2),('M-L105','motorcycle',2),
('M-L106','motorcycle',2),('M-L107','motorcycle',2),('M-L108','motorcycle',2),('M-L109','motorcycle',2),('M-L110','motorcycle',2),
-- Level 2 (floor_id = 3)
('C-L201','car',3),('C-L202','car',3),('C-L203','car',3),('C-L204','car',3),
('C-L205','car',3),('C-L206','car',3),('C-L207','car',3),('C-L208','car',3),('C-L209','car',3),('C-L210','car',3),
('M-L201','motorcycle',3),('M-L202','motorcycle',3),('M-L203','motorcycle',3),('M-L204','motorcycle',3),('M-L205','motorcycle',3),
('M-L206','motorcycle',3),('M-L207','motorcycle',3),('M-L208','motorcycle',3),('M-L209','motorcycle',3),('M-L210','motorcycle',3);

-- ============================================================
-- TABLE: vehicle
-- ============================================================
CREATE TABLE `vehicle` (
  `vehicle_id` int(11) NOT NULL AUTO_INCREMENT,
  `plate_number` varchar(15) DEFAULT NULL,
  `vehicle_type` enum('car','motorcycle') NOT NULL,
  `owner_name` varchar(100) NOT NULL DEFAULT 'Guest',
  `owner_phone` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`vehicle_id`),
  UNIQUE KEY `uk_plate_number` (`plate_number`),
  KEY `idx_plate` (`plate_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: admin_users
-- ============================================================
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `admin_users` (`username`,`password_hash`,`role`,`full_name`) VALUES
('admin',    'PENDING_SETUP','superadmin','Administrator'),
('operator', 'PENDING_SETUP','admin',     'Parking Operator'),
('rizky',    'PENDING_SETUP','operator',  'Rizky Pratama');

-- ============================================================
-- TABLE: reservation
-- ============================================================
CREATE TABLE `reservation` (
  `reservation_id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `slot_id` int(11) NOT NULL,
  `reservation_code` varchar(20) NOT NULL,
  `reserved_from` datetime NOT NULL,
  `reserved_until` datetime NOT NULL,
  `status` enum('pending','confirmed','cancelled','expired','used') NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`reservation_id`),
  UNIQUE KEY `uk_reservation_code` (`reservation_code`),
  KEY `fk_res_vehicle` (`vehicle_id`),
  KEY `fk_res_slot` (`slot_id`),
  KEY `idx_res_status` (`status`),
  CONSTRAINT `fk_res_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicle` (`vehicle_id`),
  CONSTRAINT `fk_res_slot` FOREIGN KEY (`slot_id`) REFERENCES `parking_slot` (`slot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: transaction
-- ============================================================
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
  PRIMARY KEY (`transaction_id`),
  KEY `fk_trx_vehicle` (`vehicle_id`),
  KEY `fk_trx_slot` (`slot_id`),
  KEY `fk_trx_operator` (`operator_id`),
  KEY `fk_trx_rate` (`rate_id`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_check_out_time` (`check_out_time`),
  KEY `idx_check_in_time` (`check_in_time`),
  CONSTRAINT `fk_trx_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicle` (`vehicle_id`),
  CONSTRAINT `fk_trx_slot` FOREIGN KEY (`slot_id`) REFERENCES `parking_slot` (`slot_id`),
  CONSTRAINT `fk_trx_operator` FOREIGN KEY (`operator_id`) REFERENCES `operator` (`operator_id`),
  CONSTRAINT `fk_trx_rate` FOREIGN KEY (`rate_id`) REFERENCES `parking_rate` (`rate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: ticket
-- [FIX 2] Kolom `plate_number` dihapus karena redundan (melanggar 3NF)
--
-- Sebelumnya ada: ticket_id → transaction_id → vehicle_id → plate_number
-- Ini adalah transitive dependency: plate_number bukan atribut ticket,
-- melainkan atribut vehicle yang bisa dicapai via JOIN.
-- Untuk mendapatkan plate_number: JOIN ticket → transaction → vehicle
-- ============================================================
CREATE TABLE `ticket` (
  `ticket_id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_code` varchar(20) NOT NULL,
  `transaction_id` int(11) NOT NULL,
                                    -- `plate_number` DIHAPUS (redundan, lihat komentar di atas)
  `issued_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','used','void') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`ticket_id`),
  UNIQUE KEY `uk_ticket_code` (`ticket_code`),
  KEY `fk_ticket_trx` (`transaction_id`),
  CONSTRAINT `fk_ticket_trx` FOREIGN KEY (`transaction_id`) REFERENCES `transaction` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: plate_scan_log
-- [FIX 3] Tambah FK untuk ticket_code → ticket(ticket_code)
-- ============================================================
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: transaction_log
-- [FIX 4] Tambah FK untuk transaction_id → transaction(transaction_id)
-- ============================================================
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
