-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 23, 2025 at 05:25 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `result_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `class_id` int(11) NOT NULL AUTO_INCREMENT,
  `class_name` varchar(100) NOT NULL,
  `section` varchar(10) DEFAULT NULL,
  `academic_year` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`class_id`, `class_name`, `section`, `academic_year`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Class 10', 'A', '2023-2024', 'Default class for testing', '2025-03-28 04:26:17', '2025-04-03 13:58:45'),
(2, 'Class 10', 'B', '2023-2024', NULL, '2025-04-03 13:58:45', '2025-04-03 13:58:45'),
(3, 'Class 11', 'A', '2023-2024', NULL, '2025-04-03 13:58:45', '2025-04-03 13:58:45'),
(4, 'Class 11', 'B', '2023-2024', NULL, '2025-04-03 13:58:45', '2025-04-03 13:58:45'),
(5, 'Class 12', 'A', '2023-2024', NULL, '2025-04-03 13:58:45', '2025-04-03 13:58:45'),
(6, 'Class 12', 'B', '2023-2024', NULL, '2025-04-03 13:58:45', '2025-04-03 13:58:45');

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

CREATE TABLE `exams` (
  `exam_id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_name` varchar(100) NOT NULL,
  `exam_type` enum('midterm','final','quiz','assignment','project','other') NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `total_marks` int(11) NOT NULL,
  `passing_marks` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('upcoming','ongoing','completed','cancelled') DEFAULT 'upcoming',
  `exam_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`exam_id`),
  KEY `class_id` (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exams`
--

INSERT INTO `exams` (`exam_id`, `exam_name`, `exam_type`, `class_id`, `start_date`, `end_date`, `total_marks`, `passing_marks`, `academic_year`, `description`, `status`, `exam_date`, `created_at`, `updated_at`) VALUES
(1, 'Midterm Exam', 'midterm', 1, '2023-10-01', '2023-10-10', 100, 40, '2023-2024', 'Midterm examination for Class 10', 'upcoming', NULL, '2025-03-28 04:26:17', '2025-04-03 13:58:45');

-- --------------------------------------------------------

--
-- Table structure for table `grading_system`
--

CREATE TABLE `grading_system` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `grade` varchar(5) NOT NULL,
  `min_percentage` decimal(5,2) NOT NULL,
  `gpa` decimal(3,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `grade` (`grade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grading_system`
--

INSERT INTO `grading_system` (`grade`, `min_percentage`, `gpa`) VALUES
('A+', 90.00, 4.00),
('A', 80.00, 3.70),
('B+', 70.00, 3.30),
('B', 60.00, 3.00),
('C+', 50.00, 2.70),
('C', 40.00, 2.30),
('D', 33.00, 1.00),
('F', 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `loginlogs`
--

CREATE TABLE `loginlogs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `login_time` datetime NOT NULL,
  `logout_time` datetime DEFAULT NULL,
  `session_duration` int(11) DEFAULT NULL,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loginlogs`
--

INSERT INTO `loginlogs` (`log_id`, `user_id`, `ip_address`, `user_agent`, `login_time`, `logout_time`, `session_duration`) VALUES
(1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-03 19:47:04', NULL, NULL),
(2, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-03 19:47:14', NULL, NULL),
(3, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-04 09:28:26', NULL, NULL),
(4, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-04 09:34:22', NULL, NULL),
(5, 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-04 09:41:05', NULL, NULL),
(6, 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-04 10:12:41', NULL, NULL),
(7, 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-04 10:13:33', NULL, NULL),
(8, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-04 10:13:39', NULL, NULL),
(9, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-04 10:13:47', NULL, NULL),
(10, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-04 10:14:05', NULL, NULL),
(11, 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-04 10:16:55', NULL, NULL),
(12, 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-04 10:21:09', NULL, NULL),
(13, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-04 10:22:45', NULL, NULL),
(14, 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-04 10:24:10', NULL, NULL),
(15, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-04 10:24:19', NULL, NULL),
(16, 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-04 10:24:27', NULL, NULL),
(17, 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-04 10:28:53', NULL, NULL),
(18, 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-04 19:59:33', NULL, NULL),
(19, 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-04 20:09:39', NULL, NULL),
(20, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-04 20:10:43', NULL, NULL),
(21, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-05 08:18:17', NULL, NULL),
(22, 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-08 09:33:57', NULL, NULL),
(23, 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-11 09:53:00', NULL, NULL),
(24, 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-21 06:42:40', NULL, NULL),
(25, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-21 06:43:07', NULL, NULL),
(26, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-22 07:09:13', NULL, NULL),
(27, 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-22 07:18:25', NULL, NULL),
(28, 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-22 07:19:11', NULL, NULL),
(29, 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-22 07:33:24', NULL, NULL),
(30, 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-22 07:41:21', NULL, NULL),
(31, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-22 20:46:10', NULL, NULL),
(32, 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-22 20:53:16', NULL, NULL),
(33, 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', '2025-04-23 20:31:09', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL COMMENT 'User who triggered the notification',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `notification_type` enum('system','result','exam','announcement','other') NOT NULL,
  `related_id` int(11) DEFAULT NULL COMMENT 'ID of related entity (exam_id, result_id, etc.)',
  `is_read` tinyint(1) DEFAULT 0,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `sender_id` (`sender_id`),
  KEY `idx_notifications_user` (`user_id`,`is_read`),
  KEY `idx_notifications_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `sender_id`, `title`, `message`, `notification_type`, `related_id`, `is_read`, `priority`, `expires_at`, `created_at`) VALUES
(1, 1, 1, 'System Update', 'The system has been updated to version 2.0. Please review the changes.', 'system', NULL, 0, 'high', NULL, '2025-03-28 04:34:06'),
(2, 3, 1, 'New Exam Scheduled', 'Midterm exams for Class 10 have been scheduled from 2023-10-01 to 2023-10-10.', 'exam', 1, 0, 'medium', NULL, '2025-03-28 04:34:06'),
(3, 2, 3, 'Results Published', 'Your results for Midterm Exam have been published. You scored 85 in theory and 90 in practical.', 'result', 1, 0, 'high', NULL, '2025-03-28 04:34:06'),
(4, 5, NULL, 'Welcome to Result Management System', 'Thank you for registering. Your account has been created successfully.', 'system', NULL, 0, 'medium', NULL, '2025-04-04 03:55:48'),
(6, 8, NULL, 'Welcome to Result Management System', 'Thank you for registering. Your account has been created successfully.', 'system', NULL, 0, 'medium', NULL, '2025-04-22 01:33:09');

-- --------------------------------------------------------

--
-- Table structure for table `results`
--

CREATE TABLE `results` (
  `result_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `subject_id` varchar(20) NOT NULL,
  `theory_marks` int(11) DEFAULT NULL CHECK (`theory_marks` between 0 and 100),
  `practical_marks` int(11) DEFAULT NULL CHECK (`practical_marks` between 0 and 100),
  `grade` varchar(2) DEFAULT NULL,
  `gpa` decimal(3,2) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`result_id`),
  KEY `student_id` (`student_id`),
  KEY `exam_id` (`exam_id`),
  KEY `subject_id` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `results`
--

INSERT INTO `results` (`result_id`, `student_id`, `exam_id`, `subject_id`, `theory_marks`, `practical_marks`, `grade`, `gpa`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 'S001', 1, '101', 85, 90, 'A+', 4.00, NULL, '2025-03-28 04:26:17', '2025-04-03 13:58:45');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('school_name', 'ABC School', 'Name of the school'),
('school_address', '123 Main Street, City, Country', 'Address of the school'),
('school_phone', '+1234567890', 'Contact number of the school'),
('school_email', 'info@abcschool.com', 'Email address of the school'),
('school_website', 'www.abcschool.com', 'Website of the school'),
('grading_system', 'A:90-100,B:80-89,C:70-79,D:60-69,F:0-59', 'Grading system of the school'),
('academic_year', '2023-2024', 'Current academic year'),
('result_publish_date', '2024-03-15', 'Date when results will be published'),
('school_logo', '', 'Path to school logo image'),
('result_header', 'SECONDARY EDUCATION EXAMINATION', 'Header text for result sheets'),
('result_footer', 'This is a computer-generated document. No signature is required.', 'Footer text for result sheets');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `roll_number` varchar(20) NOT NULL,
  `registration_number` varchar(30) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `batch_year` year(4) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `parent_name` varchar(100) DEFAULT NULL,
  `parent_phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `roll_number` (`roll_number`),
  UNIQUE KEY `registration_number` (`registration_number`),
  KEY `user_id` (`user_id`),
  KEY `class_id` (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `user_id`, `roll_number`, `registration_number`, `class_id`, `batch_year`, `date_of_birth`, `gender`, `address`, `phone`, `parent_name`, `parent_phone`, `created_at`, `updated_at`) VALUES
('S001', 2, 'R001', 'REG001', 1, '2023', '2005-01-01', 'male', '123 Street, City', '1234567890', 'Parent Name', '0987654321', '2025-03-28 04:26:17', '2025-04-03 13:58:45'),
('S002', 8, '23', '23425223', 6, '2025', NULL, NULL, NULL, NULL, NULL, NULL, '2025-04-22 01:33:09', '2025-04-22 01:33:09');

-- --------------------------------------------------------

--
-- Table structure for table `student_performance`
--

CREATE TABLE `student_performance` (
  `performance_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `average_marks` decimal(5,2) DEFAULT NULL,
  `gpa` decimal(3,2) DEFAULT NULL,
  `total_subjects` int(11) DEFAULT NULL,
  `subjects_passed` int(11) DEFAULT NULL,
  `rank` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`performance_id`),
  UNIQUE KEY `student_exam_unique` (`student_id`,`exam_id`),
  KEY `student_id` (`student_id`),
  KEY `exam_id` (`exam_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_performance`
--

INSERT INTO `student_performance` (`performance_id`, `student_id`, `exam_id`, `average_marks`, `gpa`, `total_subjects`, `subjects_passed`, `rank`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 'S001', 1, 87.50, 3.80, 5, 5, 1, 'Excellent performance', '2025-03-30 02:35:48', '2025-04-03 13:58:45');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `subject_id` varchar(20) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`subject_id`, `subject_name`, `description`, `created_at`, `updated_at`) VALUES
('101', 'COMP. ENGLISH', NULL, '2025-03-28 04:26:17', '2025-04-03 13:58:45'),
('102', 'COMP. NEPALI', NULL, '2025-03-28 04:26:17', '2025-04-03 13:58:45'),
('103', 'COMP. MATHEMATICS', NULL, '2025-03-28 04:26:17', '2025-04-03 13:58:45'),
('104', 'COMP. SCIENCE', NULL, '2025-03-28 04:26:17', '2025-04-03 13:58:45'),
('105', 'COMP. SOCIAL STUDIES', NULL, '2025-03-28 04:26:17', '2025-04-03 13:58:45'),
('106', 'COMP. HEALTH, POP & ENV EDU', NULL, '2025-03-28 04:26:17', '2025-04-03 13:58:45'),
('107', 'OPT.I ECONOMICS', NULL, '2025-03-28 04:26:17', '2025-04-03 13:58:45'),
('108', 'OPT.II OFFICE MGMT & ACCOUNT', NULL, '2025-03-28 04:26:17', '2025-04-03 13:58:45');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `teacher_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `qualification` varchar(100) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`teacher_id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`teacher_id`, `user_id`, `employee_id`, `qualification`, `department`, `joining_date`, `phone`, `address`, `created_at`, `updated_at`) VALUES
(1, 3, 'T001', 'M.Sc. in Mathematics', 'Mathematics', '2020-01-01', '1234567890', '456 Street, City', '2025-03-28 04:26:17', '2025-04-03 13:58:45'),
(2, 5, '12', 'Master', 'Bca', NULL, NULL, NULL, '2025-04-04 03:55:48', '2025-04-04 03:55:48');

-- --------------------------------------------------------

--
-- Table structure for table `teachersubjects`
--

CREATE TABLE `teachersubjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `subject_id` varchar(20) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `teacher_id` (`teacher_id`,`subject_id`,`academic_year`),
  KEY `subject_id` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachersubjects`
--

INSERT INTO `teachersubjects` (`id`, `teacher_id`, `subject_id`, `academic_year`, `created_at`) VALUES
(1, 1, '101', '2023-2024', '2025-03-28 04:26:17');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_activities`
--

CREATE TABLE `teacher_activities` (
  `activity_id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `activity_type` enum('login','logout','marks_update','view_performance','print_report','other') NOT NULL,
  `description` text NOT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`activity_id`),
  KEY `teacher_id` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','teacher','student') NOT NULL,
  `status` enum('active','inactive','pending') DEFAULT 'active',
  `profile_image` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `full_name`, `email`, `role`, `status`, `profile_image`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@example.com', 'admin', 'active', NULL, NULL, '2025-04-03 13:58:45', '2025-04-03 13:58:45'),
(2, 'admin123', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin123@example.com', 'admin', 'active', NULL, NULL, '2025-03-28 04:26:17', '2025-04-03 13:58:45'),
(3, 'student1', '$2y$10$8zUUpfvHvJqMnJ4gJk.Cj.Z/BvWQS1zNFW9CMhbRvDpRRUL2jEjGK', 'John Doe', 'student1@example.com', 'student', 'active', NULL, NULL, '2025-03-28 04:26:17', '2025-04-03 13:58:45'),
(4, 'teacher1', '$2y$10$8zUUpfvHvJqMnJ4gJk.Cj.Z/BvWQS1zNFW9CMhbRvDpRRUL2jEjGK', 'Jane Smith', 'teacher1@example.com', 'teacher', 'active', NULL, NULL, '2025-03-28 04:26:17', '2025-04-03 13:58:45'),
(5, 'teacher_1', '$2y$10$ytNjhLEEsIHFtuusMcUSwuRsUqvG8KK2wKxhcoVw1BR/7ibtQnHWm', 'Teacher_1', 'hero@mailinator.com', 'teacher', 'active', NULL, NULL, '2025-04-04 03:55:48', '2025-04-04 03:55:48'),
(8, 'Student', '$2y$10$Ww5b6yZAta13M63z3Jn80e2CZXgK59.d/u1eT/ZdkXPhmp9Q4wcaG', 'Student', 'nykesi@mailinator.com', 'student', 'active', NULL, NULL, '2025-04-22 01:33:09', '2025-04-22 01:33:09');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `exams`
--
ALTER TABLE `exams`
  ADD CONSTRAINT `exams_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`) ON DELETE SET NULL;

--
-- Constraints for table `loginlogs`
--
ALTER TABLE `loginlogs`
  ADD CONSTRAINT `loginlogs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `results`
--
ALTER TABLE `results`
  ADD CONSTRAINT `results_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`) ON DELETE SET NULL;

--
-- Constraints for table `student_performance`
--
ALTER TABLE `student_performance`
  ADD CONSTRAINT `student_performance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_performance_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON DELETE CASCADE;

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `teachersubjects`
--
ALTER TABLE `teachersubjects`
  ADD CONSTRAINT `teachersubjects_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teachersubjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_activities`
--
ALTER TABLE `teacher_activities`
  ADD CONSTRAINT `teacher_activities_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE;

-- Add indexes to improve query performance
CREATE INDEX IF NOT EXISTS idx_results_student_exam ON results(student_id, exam_id);
CREATE INDEX IF NOT EXISTS idx_students_class ON students(class_id);
CREATE INDEX IF NOT EXISTS idx_exams_class ON exams(class_id);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
