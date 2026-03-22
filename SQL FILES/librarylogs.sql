-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.4.3 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.8.0.6908
-- --------------------------------------------------------
SET FOREIGN_KEY_CHECKS=0;
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Dumping structure for table librarylogs.departments
DROP TABLE IF EXISTS `departments`;
CREATE TABLE IF NOT EXISTS `departments` (
  `departmentID` varchar(10) NOT NULL,
  `departmentName` varchar(100) NOT NULL,
  PRIMARY KEY (`departmentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table librarylogs.departments: ~17 rows (approximately)
DELETE FROM `departments`;
INSERT INTO `departments` (`departmentID`, `departmentName`) VALUES
	('AGRI', 'College of Agriculture'),
	('CAS', 'College of Arts and Sciences'),
	('CBA', 'College of Business Administration'),
	('CCR', 'College of Criminology'),
	('CEA', 'College of Engineering & Architecture'),
	('CED', 'College of Education'),
	('CICS', 'College of Informatics and Computing Studies'),
	('CMD', 'College of Medicine'),
	('CMT', 'College of Medical Technology'),
	('CMW', 'College of Midwifery'),
	('COA', 'College of Accountancy'),
	('COC', 'College of Communication'),
	('COL', 'College of Law'),
	('COMS', 'College of Music'),
	('CON', 'College of Nursing'),
	('CPT', 'College of Physical Therapy'),
	('CRT', 'College of Respiratory Therapy'),
	('SGS', 'School of Graduate Studies'),
	('SOIR', 'School of International Relations');

-- Dumping structure for table librarylogs.employees
DROP TABLE IF EXISTS `employees`;
CREATE TABLE IF NOT EXISTS `employees` (
  `emplID` int NOT NULL AUTO_INCREMENT,
  `firstName` varchar(50) NOT NULL,
  `lastName` varchar(50) NOT NULL,
  `institutionalEmail` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `departmentID` varchar(10) DEFAULT NULL,
  `role` enum('Faculty/Admin') DEFAULT 'Faculty/Admin',
  `status` enum('Active','Blocked') DEFAULT 'Active',
  `profile_image` varchar(255) DEFAULT 'default.png',
  `block_reason` varchar(255) DEFAULT NULL,
  `date_blocked` datetime DEFAULT NULL,
  `is_admin_approved` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`emplID`),
  UNIQUE KEY `institutionalEmail` (`institutionalEmail`),
  KEY `departmentID` (`departmentID`),
  CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`departmentID`) REFERENCES `departments` (`departmentID`)
) ENGINE=InnoDB AUTO_INCREMENT=2610009 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table librarylogs.employees: ~5 rows (approximately)
DELETE FROM `employees`;
INSERT INTO `employees` (`emplID`, `firstName`, `lastName`, `institutionalEmail`, `password`, `departmentID`, `role`, `status`, `profile_image`, `block_reason`, `date_blocked`, `is_admin_approved`) VALUES
	(2610001, 'Jeremias', 'Esperanza', 'jcesperanza@neu.edu.ph', 'admin123', 'CICS', 'Faculty/Admin', 'Active', '2610001.png', NULL, NULL, 1),
	(2610002, 'Rhyian Joshua', 'Ticbobolan', 'rhyianjoshua.ticbobolan@neu.edu.ph', '123admin', 'CICS', 'Faculty/Admin', 'Active', '2610002.png', NULL, NULL, 1),
	(2610003, 'Nelson ', 'Gaspar', 'ncgaspar@neu.edu.ph', 'admin321', 'CICS', 'Faculty/Admin', 'Active', '2610003.png', NULL, NULL, 1),
	(2610004, 'Irish Paulo', 'Tipay', 'iprtipay@neu.edu.ph', '321admin', 'CICS', 'Faculty/Admin', 'Blocked', 'default.png', NULL, NULL, 1),
	(2610005, 'Donn', 'Alcantara', 'dsalcantara@neu.edu.ph', 'alcantara', 'CEA', 'Faculty/Admin', 'Active', '2610005.png', NULL, NULL, 1);

-- Dumping structure for table librarylogs.history_logs
DROP TABLE IF EXISTS `history_logs`;
CREATE TABLE IF NOT EXISTS `history_logs` (
  `logID` int NOT NULL AUTO_INCREMENT,
  `user_identifier` int NOT NULL,
  `user_type` enum('Student','Employee') NOT NULL,
  `date` date DEFAULT (curdate()),
  `time` time DEFAULT (curtime()),
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  PRIMARY KEY (`logID`)
) ENGINE=InnoDB AUTO_INCREMENT=118 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table librarylogs.history_logs: ~83 rows (approximately)
DELETE FROM `history_logs`;
INSERT INTO `history_logs` (`logID`, `user_identifier`, `user_type`, `date`, `time`, `reason`) VALUES
	(1, 2600001, 'Student', '2026-03-07', '20:10:08', 'Sleeping'),
	(2, 2600002, 'Student', '2026-03-07', '20:11:32', 'Research'),
	(3, 2610001, 'Employee', '2026-03-07', '21:17:51', 'Event'),
	(4, 2610002, 'Employee', '2026-03-07', '21:20:30', 'Borrowing'),
	(5, 2600008, 'Student', '2026-03-07', '21:33:35', 'Research'),
	(6, 2600003, 'Student', '2026-03-07', '21:38:21', 'Study'),
	(7, 2600004, 'Student', '2026-03-07', '21:42:14', 'Study'),
	(8, 2600006, 'Student', '2026-03-07', '21:43:08', 'Borrowing'),
	(9, 2600007, 'Student', '2026-03-07', '21:59:52', 'Event'),
	(10, 2600009, 'Student', '2026-03-07', '22:00:44', 'E-Resources'),
	(11, 2600010, 'Student', '2026-03-07', '22:03:44', 'ID Validation'),
	(12, 2600005, 'Student', '2026-03-07', '22:10:38', 'Group Study'),
	(13, 2610003, 'Employee', '2026-03-07', '22:22:48', 'E-Resources'),
	(14, 2600011, 'Student', '2026-03-07', '22:27:45', 'Resting'),
	(15, 2600001, 'Student', '2026-03-08', '00:36:22', 'Resting'),
	(16, 2600002, 'Student', '2026-03-08', '01:13:41', 'Resting'),
	(17, 2610002, 'Employee', '2026-03-08', '01:25:35', 'Borrowing'),
	(18, 2610001, 'Employee', '2026-03-08', '01:26:09', 'ID Validation'),
	(19, 2600003, 'Student', '2026-03-08', '01:31:39', 'Computer Use'),
	(20, 2600004, 'Student', '2026-03-08', '01:47:43', 'Event'),
	(21, 2600005, 'Student', '2026-03-08', '01:48:18', 'Research'),
	(22, 2600006, 'Student', '2026-03-08', '01:49:47', 'ID Validation'),
	(25, 2600009, 'Student', '2026-03-08', '04:44:14', 'E-Resources'),
	(26, 2600010, 'Student', '2026-03-08', '04:44:29', 'Research'),
	(27, 2610003, 'Employee', '2026-03-08', '04:48:16', 'Study'),
	(28, 2600007, 'Student', '2026-03-08', '20:34:06', 'Event'),
	(29, 2610001, 'Employee', '2026-03-09', '00:56:06', 'Research'),
	(30, 2600001, 'Student', '2026-03-09', '03:52:33', 'Research'),
	(32, 2600007, 'Student', '2026-03-09', '03:57:03', 'Study'),
	(33, 2610003, 'Employee', '2026-03-09', '04:15:47', 'Group Study'),
	(34, 2600002, 'Student', '2026-03-09', '04:31:24', 'Study'),
	(35, 2600005, 'Student', '2026-03-09', '04:42:44', 'Borrowing'),
	(36, 2600003, 'Student', '2026-03-09', '15:55:09', 'Study'),
	(37, 2600005, 'Student', '2026-03-11', '21:05:13', 'Resting'),
	(38, 2600001, 'Student', '2026-03-11', '21:05:42', 'Research'),
	(39, 2610001, 'Employee', '2026-03-11', '22:19:49', 'Event'),
	(40, 2600007, 'Student', '2026-03-11', '23:48:35', 'Computer Use'),
	(41, 2600002, 'Student', '2026-03-11', '23:57:50', 'E-Resources'),
	(42, 2600010, 'Student', '2026-03-12', '00:00:11', 'Study'),
	(43, 2600005, 'Student', '2026-03-12', '00:21:54', 'Borrowing'),
	(44, 2600002, 'Student', '2026-03-12', '02:10:46', 'Study'),
	(45, 2600007, 'Student', '2026-03-12', '03:16:22', 'Study'),
	(46, 2610001, 'Employee', '2026-03-12', '03:17:28', 'Clearance'),
	(47, 2600008, 'Student', '2026-03-12', '03:29:25', 'Group Study'),
	(48, 2600009, 'Student', '2026-03-12', '22:08:50', 'Sleping'),
	(50, 2600011, 'Student', '2026-03-13', '04:20:36', 'Study'),
	(58, 2610002, 'Employee', '2026-03-13', '17:28:34', 'Group Study'),
	(59, 2600002, 'Student', '2026-03-13', '17:29:09', 'E-Resources'),
	(60, 2600009, 'Student', '2026-03-13', '17:50:25', 'Printing'),
	(61, 2600007, 'Student', '2026-03-13', '17:59:17', 'Clearance'),
	(62, 2600004, 'Student', '2026-03-13', '18:00:00', 'Event'),
	(64, 2610003, 'Employee', '2026-03-13', '22:02:36', 'Resting'),
	(66, 2600020, 'Student', '2026-03-13', '23:06:41', 'E-Resources'),
	(72, 2600002, 'Student', '2026-03-14', '02:33:19', 'Computer Use'),
	(73, 2600001, 'Student', '2026-03-14', '02:33:34', 'Research'),
	(74, 2610001, 'Employee', '2026-03-14', '02:33:51', 'E-Resources'),
	(75, 2610002, 'Employee', '2026-03-14', '02:34:06', 'Event'),
	(76, 2600005, 'Student', '2026-03-14', '22:32:11', 'Group Study'),
	(77, 2600005, 'Student', '2026-03-15', '01:22:07', 'E-Resources'),
	(78, 2610002, 'Employee', '2026-03-15', '01:24:28', 'Study'),
	(79, 2600028, 'Student', '2026-03-17', '02:59:19', 'Research'),
	(80, 2610001, 'Employee', '2026-03-17', '02:59:49', 'Study'),
	(81, 2600024, 'Student', '2026-03-17', '03:00:03', 'Group Study'),
	(82, 2600023, 'Student', '2026-03-17', '03:00:17', 'Borrowing'),
	(83, 2600022, 'Student', '2026-03-17', '03:00:33', 'Clearance'),
	(84, 2600020, 'Student', '2026-03-17', '03:00:47', 'ID Validation'),
	(85, 2600007, 'Student', '2026-03-17', '03:01:15', 'Resting'),
	(86, 2600008, 'Student', '2026-03-17', '03:01:31', 'Computer Use'),
	(87, 2600010, 'Student', '2026-03-17', '03:01:52', 'Printing'),
	(88, 2600004, 'Student', '2026-03-17', '03:02:08', 'E-Resources'),
	(89, 2600006, 'Student', '2026-03-17', '03:02:29', 'Event'),
	(90, 2600005, 'Student', '2026-03-17', '03:02:47', 'Research'),
	(91, 2600003, 'Student', '2026-03-17', '03:03:05', 'Study'),
	(92, 2600002, 'Student', '2026-03-17', '03:03:19', 'Group Study'),
	(93, 2600001, 'Student', '2026-03-17', '03:03:30', 'Borrowing'),
	(94, 2610005, 'Employee', '2026-03-17', '03:03:45', 'Clearance'),
	(95, 2610003, 'Employee', '2026-03-17', '03:04:03', 'ID Validation'),
	(96, 2610002, 'Employee', '2026-03-17', '03:04:16', 'Resting'),
	(97, 2600029, 'Student', '2026-03-18', '22:48:26', 'ID Validation'),
	(98, 2600001, 'Student', '2026-03-19', '01:11:24', 'Printing'),
	(99, 2600005, 'Student', '2026-03-19', '01:23:55', 'Research'),
	(115, 2600001, 'Student', '2026-03-22', '18:25:46', 'Study');

-- Dumping structure for table librarylogs.problem_reports
DROP TABLE IF EXISTS `problem_reports`;
CREATE TABLE IF NOT EXISTS `problem_reports` (
  `reportID` int NOT NULL AUTO_INCREMENT,
  `user_identifier` varchar(20) NOT NULL,
  `issue_type` varchar(50) NOT NULL,
  `description` text,
  `status` enum('Pending','In Progress','Resolved') DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reportID`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table librarylogs.problem_reports: ~5 rows (approximately)
DELETE FROM `problem_reports`;
INSERT INTO `problem_reports` (`reportID`, `user_identifier`, `issue_type`, `description`, `status`, `created_at`) VALUES
	(1, '2600001', 'Database/Login Error', 'cant login', 'Pending', '2026-03-07 11:52:31'),
	(2, '2610004', 'Database Error', 'no account', 'Pending', '2026-03-07 14:30:37'),
	(3, '2600001', 'Scanner Issue', 'not working', 'Pending', '2026-03-08 19:02:43'),
	(4, '2600001', 'Database Error', '1st year', 'In Progress', '2026-03-08 20:50:46'),
	(7, '2600001', 'Database Error', 'no account\r\n', 'Pending', '2026-03-12 17:13:37'),
	(8, '2600001', 'QR Issue', 'NO QR APPEARING', 'Pending', '2026-03-16 19:06:13');

-- Dumping structure for table librarylogs.students
DROP TABLE IF EXISTS `students`;
CREATE TABLE IF NOT EXISTS `students` (
  `studentID` int NOT NULL AUTO_INCREMENT,
  `firstName` varchar(50) NOT NULL,
  `lastName` varchar(50) NOT NULL,
  `institutionalEmail` varchar(100) NOT NULL,
  `departmentID` varchar(10) DEFAULT NULL,
  `role` enum('Student') DEFAULT 'Student',
  `status` enum('Active','Blocked') DEFAULT 'Active',
  `profile_image` varchar(255) DEFAULT 'default.png',
  `block_reason` varchar(255) DEFAULT NULL,
  `date_blocked` datetime DEFAULT NULL,
  PRIMARY KEY (`studentID`),
  UNIQUE KEY `institutionalEmail` (`institutionalEmail`),
  KEY `departmentID` (`departmentID`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`departmentID`) REFERENCES `departments` (`departmentID`)
) ENGINE=InnoDB AUTO_INCREMENT=2600042 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table librarylogs.students: ~18 rows (approximately)
DELETE FROM `students`;
INSERT INTO `students` (`studentID`, `firstName`, `lastName`, `institutionalEmail`, `departmentID`, `role`, `status`, `profile_image`, `block_reason`, `date_blocked`) VALUES
	(2600001, 'Rhyian Joshua', 'Ticbobolan', 'rhyianjoshua.ticbobolan@neu.edu.ph', 'CICS', 'Student', 'Active', '2600001.jpg', NULL, NULL),
	(2600002, 'Clark Kent', 'Zuniga', 'clarkkent.zuniga@neu.edu.ph', 'CICS', 'Student', 'Active', '2600002.png', NULL, NULL),
	(2600003, 'Lars Ulrich', 'Galamiton', 'larsulrich.galamiton@neu.edu.ph', 'CICS', 'Student', 'Active', '2600003.png', NULL, NULL),
	(2600004, 'Gabriel Red Ray', 'Perez', 'gabrielredray.perez@neu.edu.ph', 'CICS', 'Student', 'Active', '2600004.png', NULL, NULL),
	(2600005, 'Sebastian Andrew', 'Manilag', 'sebastianandrew.manilag@neu.edu.ph', 'CICS', 'Student', 'Active', '2600005.png', NULL, NULL),
	(2600006, 'John Timothy', 'Gandeza', 'johntimothy.gandeza@neu.edu.ph', 'CICS', 'Student', 'Active', '2600006.png', NULL, NULL),
	(2600007, 'Justine', 'Loterte', 'justine.loterte@neu.edu.ph', 'CICS', 'Student', 'Active', '2600007.png', NULL, NULL),
	(2600008, 'Sanny Cyruz', 'Regalado', 'sannycyruz.regalado@neu.edu.ph', 'CICS', 'Student', 'Active', '2600008.png', NULL, NULL),
	(2600009, 'Aldred John', 'Basmayor', 'aldredjohn.basmayor@neu.edu.ph', 'CICS', 'Student', 'Blocked', '2600009.png', 'Noisy', '2026-03-14 02:40:03'),
	(2600010, 'Tristan', 'Reazon', 'tristan.reazon@neu.edu.ph', 'CICS', 'Student', 'Active', '2600010.png', NULL, NULL),
	(2600011, 'Zymon', 'Cuatriz', 'zymon.cuatriz@neu.edu.ph', 'CICS', 'Student', 'Blocked', '2600011.png', 'eating', '2026-03-13 22:55:05'),
	(2600020, 'Christian', 'Leocario', 'christian.leocario@neu.edu.ph', 'CICS', 'Student', 'Active', '2600020.png', NULL, NULL),
	(2600022, 'Ranezet Vhon', 'Bachiller', 'ranezetvhon.bachiller@neu.edu.ph', 'CICS', 'Student', 'Active', '2600022.png', NULL, NULL),
	(2600023, 'Bernard', 'Lorenzo', 'bernard.lorenzo@neu.edu.ph', 'CICS', 'Student', 'Active', '2600023.png', NULL, NULL),
	(2600024, 'Michael', 'Santiago', 'michael.santiago@neu.edu.ph', 'CICS', 'Student', 'Active', '2600024.png', NULL, NULL),
	(2600028, 'Carl William', 'Paming', 'carlwilliam.paming@neu.edu.ph', 'CICS', 'Student', 'Active', '2600028.png', NULL, NULL),
	(2600033, 'Jan Rey', 'Maranan', 'janrey.maranan@neu.edu.ph', 'CICS', 'Student', 'Active', '2600033.png', NULL, NULL);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
SET FOREIGN_KEY_CHECKS=1;
