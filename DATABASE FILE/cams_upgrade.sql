-- ============================================================
-- PIMT CAMS — Database Upgrade Script
-- Run this in phpMyAdmin AFTER importing college_attendance.sql
-- ============================================================

USE `college_attendance`;

-- ── 1. New table: tbldepartment ──────────────────────────────
CREATE TABLE IF NOT EXISTS `tbldepartment` (
  `Id` int(10) NOT NULL AUTO_INCREMENT,
  `deptName` varchar(150) NOT NULL,
  `deptCode` varchar(20)  NOT NULL UNIQUE,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `tbldepartment` (`deptName`, `deptCode`) VALUES
('Computer Science & Engineering', 'CSE'),
('Business Administration',        'MBA'),
('Electrical Engineering',         'EE'),
('Mechanical Engineering',         'ME'),
('Civil Engineering',              'CE'),
('Information Technology',         'IT'),
('Pharmacy',                       'PHARM'),
('Management Studies',             'MGMT');

-- ── 2. New table: tblhod ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tblhod` (
  `Id` int(10) NOT NULL AUTO_INCREMENT,
  `firstName`    varchar(100) NOT NULL,
  `lastName`     varchar(100) NOT NULL,
  `emailAddress` varchar(150) NOT NULL UNIQUE,
  `password`     varchar(255) NOT NULL,
  `phoneNo`      varchar(20)  NOT NULL,
  `deptId`       int(10)      NOT NULL,
  `photo`        varchar(255) DEFAULT NULL,
  `dateCreated`  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `tblhod` (`firstName`,`lastName`,`emailAddress`,`password`,`phoneNo`,`deptId`) VALUES
('Dr. Harpreet', 'Singh',  'hod.cse@pimt.edu',  'hod123', '9812345678', 1),
('Dr. Manpreet', 'Kaur',   'hod.mba@pimt.edu',  'hod123', '9812345679', 2);

-- ── 3. Alter tblteacher — add deptId + photo ─────────────────
ALTER TABLE `tblteacher`
  ADD COLUMN IF NOT EXISTS `deptId` int(10) DEFAULT NULL AFTER `phoneNo`,
  ADD COLUMN IF NOT EXISTS `photo`  varchar(255) DEFAULT NULL AFTER `deptId`;

-- ── 4. Alter tblstudent — add gender + photo ─────────────────
ALTER TABLE `tblstudent`
  ADD COLUMN IF NOT EXISTS `gender` ENUM('Male','Female','Other') NOT NULL DEFAULT 'Male' AFTER `lastName`,
  ADD COLUMN IF NOT EXISTS `photo`  varchar(255) DEFAULT NULL AFTER `gender`,
  ADD COLUMN IF NOT EXISTS `phoneNo` varchar(20) DEFAULT NULL AFTER `password`,
  ADD COLUMN IF NOT EXISTS `deptId` int(10) DEFAULT NULL AFTER `courseId`;

-- ── 5. Assign sample teachers to CSE dept ────────────────────
UPDATE `tblteacher` SET deptId = 1 WHERE emailAddress = 'rahul.sharma@college.edu';
UPDATE `tblteacher` SET deptId = 2 WHERE emailAddress = 'priya.patel@college.edu';
UPDATE `tblteacher` SET deptId = 1 WHERE emailAddress = 'amit.verma@college.edu';
