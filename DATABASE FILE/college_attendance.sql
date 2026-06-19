-- ============================================================
-- College Attendance Management System (CAMS)
-- Database: college_attendance
-- Version: 1.0 | Created: 2026
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- Create and use database
CREATE DATABASE IF NOT EXISTS `college_attendance` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `college_attendance`;

-- ============================================================
-- Table: tbldirector (Director / Admin accounts)
-- ============================================================
DROP TABLE IF EXISTS `tbldirector`;
CREATE TABLE `tbldirector` (
  `Id` int(10) NOT NULL AUTO_INCREMENT,
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `emailAddress` varchar(150) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `phoneNo` varchar(20) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tbldirector` (`firstName`, `lastName`, `emailAddress`, `password`) VALUES
('College', 'Director', 'director@college.edu', 'director123');

-- ============================================================
-- Table: tbldepartment (College departments)
-- ============================================================
DROP TABLE IF EXISTS `tbldepartment`;
CREATE TABLE `tbldepartment` (
  `Id` int(10) NOT NULL AUTO_INCREMENT,
  `deptName` varchar(150) NOT NULL,
  `deptCode` varchar(20) NOT NULL UNIQUE,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tbldepartment` (`deptName`, `deptCode`) VALUES
('Computer Science & Engineering', 'CSE'),
('Business Administration', 'MBA'),
('Electrical Engineering', 'EE'),
('Mechanical Engineering', 'ME'),
('Civil Engineering', 'CE'),
('Information Technology', 'IT'),
('Pharmacy', 'PHARM'),
('Management Studies', 'MGMT');

-- ============================================================
-- Table: tblhod (Head of Department accounts)
-- ============================================================
DROP TABLE IF EXISTS `tblhod`;
CREATE TABLE `tblhod` (
  `Id` int(10) NOT NULL AUTO_INCREMENT,
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `emailAddress` varchar(150) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `phoneNo` varchar(20) NOT NULL,
  `deptId` int(10) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tblhod` (`firstName`,`lastName`,`emailAddress`,`password`,`phoneNo`,`deptId`) VALUES
('Dr. Harpreet', 'Singh', 'hod.cse@pimt.edu', 'hod123', '9812345678', 1),
('Dr. Manpreet', 'Kaur', 'hod.mba@pimt.edu', 'hod123', '9812345679', 2);

-- ============================================================
-- Table: tblteacher (Teacher accounts)
-- ============================================================
DROP TABLE IF EXISTS `tblteacher`;
CREATE TABLE `tblteacher` (
  `Id` int(10) NOT NULL AUTO_INCREMENT,
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `emailAddress` varchar(150) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `phoneNo` varchar(20) NOT NULL,
  `deptId` int(10) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tblteacher` (`firstName`, `lastName`, `emailAddress`, `password`, `phoneNo`, `deptId`) VALUES
('Rahul', 'Sharma', 'rahul.sharma@college.edu', 'teacher123', '9876543210', 1),
('Priya', 'Patel', 'priya.patel@college.edu', 'teacher123', '9876543211', 2),
('Amit', 'Verma', 'amit.verma@college.edu', 'teacher123', '9876543212', 1);

-- ============================================================
-- Table: tblcourse (Popular college courses)
-- ============================================================
DROP TABLE IF EXISTS `tblcourse`;
CREATE TABLE `tblcourse` (
  `Id` int(10) NOT NULL AUTO_INCREMENT,
  `courseName` varchar(255) NOT NULL,
  `courseCode` varchar(20) NOT NULL UNIQUE,
  `description` text NOT NULL,
  `duration` varchar(50) NOT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tblcourse` (`courseName`, `courseCode`, `description`, `duration`) VALUES
('Computer Science & Engineering', 'CSE101', 'Study of computation, algorithms, programming languages, software and hardware design.', '4 Years'),
('Business Administration (MBA)', 'MBA201', 'Graduate program covering management, finance, marketing, and leadership.', '2 Years'),
('Data Science & Machine Learning', 'DSML301', 'Advanced study of data analysis, statistical modeling, and ML algorithms.', '2 Years'),
('Artificial Intelligence', 'AI401', 'Deep learning, neural networks, NLP, computer vision and AI applications.', '4 Years'),
('Civil Engineering', 'CE501', 'Design and construction of roads, bridges, buildings and infrastructure.', '4 Years'),
('Mechanical Engineering', 'ME601', 'Study of motion, energy, force and the design of mechanical systems.', '4 Years'),
('Electrical & Electronics Engineering', 'EEE701', 'Study of electrical systems, circuits, power, and electronic devices.', '4 Years'),
('Medical Laboratory Science', 'MLS801', 'Laboratory analysis of biological samples to diagnose and monitor diseases.', '4 Years'),
('Pharmacy (B.Pharm)', 'PHARM901', 'Study of drugs, their properties, synthesis, and therapeutic uses.', '4 Years'),
('Mass Communication & Journalism', 'MCJ001', 'Media studies, journalism, broadcasting, and digital communications.', '3 Years'),
('Law (LLB)', 'LLB002', 'Study of legal systems, constitutional law, criminal law, and justice.', '3 Years'),
('Architecture', 'ARCH003', 'Design of buildings, spaces, and structures with aesthetic and functional principles.', '5 Years'),
('Nursing (B.Sc)', 'NURS004', 'Healthcare, patient care, clinical practice, and nursing science.', '4 Years'),
('Psychology (B.Sc)', 'PSY005', 'Study of human behavior, mental processes, cognition, and therapy.', '3 Years'),
('Economics (B.Sc)', 'ECO006', 'Micro and macroeconomics, economic theory, policy, and financial markets.', '3 Years'),
('Biotechnology', 'BIO007', 'Application of biology and technology in medicine, agriculture, and industry.', '4 Years'),
('Information Technology', 'IT008', 'Systems design, networking, database management, and IT infrastructure.', '4 Years'),
('Accounting & Finance', 'AF009', 'Financial accounting, auditing, taxation, and corporate finance.', '3 Years');

-- ============================================================
-- Table: tblteacher_course (Teacher-Course assignments)
-- ============================================================
DROP TABLE IF EXISTS `tblteacher_course`;
CREATE TABLE `tblteacher_course` (
  `Id` int(10) NOT NULL AUTO_INCREMENT,
  `teacherId` int(10) NOT NULL,
  `courseId` int(10) NOT NULL,
  `dateAssigned` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `unique_assignment` (`teacherId`, `courseId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample assignments
INSERT INTO `tblteacher_course` (`teacherId`, `courseId`) VALUES
(1, 1),
(1, 4),
(2, 2),
(2, 3),
(3, 5);

-- ============================================================
-- Table: tblhod_course (Director-to-HOD course assignments)
-- ============================================================
DROP TABLE IF EXISTS `tblhod_course`;
CREATE TABLE `tblhod_course` (
  `Id` int(10) NOT NULL AUTO_INCREMENT,
  `hodId` int(10) NOT NULL,
  `courseId` int(10) NOT NULL,
  `assignedByDirectorId` int(10) DEFAULT NULL,
  `dateAssigned` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `unique_hod_course` (`hodId`, `courseId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tblhod_course` (`hodId`, `courseId`, `assignedByDirectorId`) VALUES
(1, 1, 1),
(1, 4, 1),
(2, 2, 1),
(2, 3, 1);

-- ============================================================
-- Table: tblstudent (Students enrolled per course)
-- ============================================================
DROP TABLE IF EXISTS `tblstudent`;
CREATE TABLE `tblstudent` (
  `Id` int(10) NOT NULL AUTO_INCREMENT,
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `gender` ENUM('Male','Female','Other') NOT NULL DEFAULT 'Male',
  `photo` varchar(255) DEFAULT NULL,
  `rollNumber` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `phoneNo` varchar(20) DEFAULT NULL,
  `courseId` int(10) NOT NULL,
  `deptId` int(10) DEFAULT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample students for CSE101
INSERT INTO `tblstudent` (`firstName`, `lastName`, `rollNumber`, `password`, `courseId`, `deptId`) VALUES
('Arjun', 'Mehta', 'CSE2024001', 'student123', 1, 1),
('Sneha', 'Gupta', 'CSE2024002', 'student123', 1, 1),
('Rohan', 'Singh', 'CSE2024003', 'student123', 1, 1),
('Anjali', 'Kumar', 'CSE2024004', 'student123', 1, 1),
('Vivek', 'Joshi', 'CSE2024005', 'student123', 1, 1),
('Pooja', 'Mishra', 'MBA2024001', 'student123', 2, 2),
('Suresh', 'Nair', 'MBA2024002', 'student123', 2, 2),
('Lakshmi', 'Pillai', 'MBA2024003', 'student123', 2, 2);

-- ============================================================
-- Table: tblstudent_course (Multiple course assignments per student)
-- ============================================================
DROP TABLE IF EXISTS `tblstudent_course`;
CREATE TABLE `tblstudent_course` (
  `Id` int(10) NOT NULL AUTO_INCREMENT,
  `studentId` int(10) NOT NULL,
  `courseId` int(10) NOT NULL,
  `assignedByRole` varchar(30) DEFAULT NULL,
  `assignedById` int(10) DEFAULT NULL,
  `dateAssigned` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `unique_student_course` (`studentId`, `courseId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tblstudent_course` (`studentId`, `courseId`, `assignedByRole`, `assignedById`) VALUES
(1, 1, 'Director', 1),
(2, 1, 'Director', 1),
(3, 1, 'Director', 1),
(4, 1, 'Director', 1),
(5, 1, 'Director', 1),
(6, 2, 'Director', 1),
(7, 2, 'Director', 1),
(8, 2, 'Director', 1);

-- ============================================================
-- Table: tblattendance (Daily attendance records)
-- ============================================================
DROP TABLE IF EXISTS `tblattendance`;
CREATE TABLE `tblattendance` (
  `Id` int(10) NOT NULL AUTO_INCREMENT,
  `studentId` int(10) NOT NULL,
  `courseId` int(10) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=absent, 1=present',
  `dateTaken` date NOT NULL,
  `takenByTeacherId` int(10) NOT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `unique_attendance` (`studentId`, `courseId`, `dateTaken`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: tblteacher_attendance (HOD-marked teacher attendance)
-- ============================================================
DROP TABLE IF EXISTS `tblteacher_attendance`;
CREATE TABLE `tblteacher_attendance` (
  `Id` int(10) NOT NULL AUTO_INCREMENT,
  `teacherId` int(10) NOT NULL,
  `deptId` int(10) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=absent, 1=present',
  `dateTaken` date NOT NULL,
  `takenByHodId` int(10) NOT NULL,
  `dateTimeTaken` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `unique_teacher_attendance` (`teacherId`, `dateTaken`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
