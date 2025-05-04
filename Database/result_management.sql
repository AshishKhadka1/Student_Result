-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 02, 2025 at 10:34 AM
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
CREATE DATABASE IF NOT EXISTS `result_management` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `result_management`;

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
CREATE TABLE `classes` (
  `class_id` int(11) NOT NULL AUTO_INCREMENT,
  `class_name` varchar(100) NOT NULL,
  `section` varchar(10) DEFAULT NULL,
  `academic_year` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`class_id`),
  KEY `idx_classes_academic_year` (`academic_year`),
  KEY `idx_classes_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`class_id`, `class_name`, `section`, `academic_year`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Class 10', 'A', '2023-2024', 'Default class for testing', 1, '2025-03-28 04:26:17', '2025-04-03 13:58:45'),
(2, 'Class 10', 'B', '2023-2024', NULL, 1, '2025-04-03 13:58:45', '2025-04-03 13:58:45'),
(3, 'Class 11', 'A', '2023-2024', NULL, 1, '2025-04-03 13:58:45', '2025-04-03 13:58:45'),
(4, 'Class 11', 'B', '2023-2024', NULL, 1, '2025-04-03 13:58:45', '2025-04-03 13:58:45'),
(5, 'Class 12', 'A', '2023-2024', NULL, 1, '2025-04-03 13:58:45', '2025-04-03 13:58:45'),
(6, 'Class 12', 'B', '2023-2024', NULL, 1, '2025-04-03 13:58:45', '2025-04-03 13:58:45');

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

DROP TABLE IF EXISTS `exams`;
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
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`exam_id`),
  KEY `class_id` (`class_id`),
  KEY `idx_exams_academic_year` (`academic_year`),
  KEY `idx_exams_status` (`status`),
  KEY `idx_exams_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exams`
--

INSERT INTO `exams` (`exam_id`, `exam_name`, `exam_type`, `class_id`, `start_date`, `end_date`, `total_marks`, `passing_marks`, `academic_year`, `description`, `status`, `exam_date`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Midterm Exam', 'midterm', 1, '2023-10-01', '2023-10-10', 100, 40, '2023-2024', 'Midterm examination for Class 10', 'upcoming', NULL, 1, '2025-03-28 04:26:17', '2025-04-03 13:58:45');

-- --------------------------------------------------------

--
-- Table structure for table `grading_system`
--

DROP TABLE IF EXISTS `grading_system`;
CREATE TABLE `grading_system` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `grade` varchar(5) NOT NULL,
  `min_percentage` decimal(5,2) NOT NULL,
  `max_percentage` decimal(5,2) NOT NULL,
  `gpa` decimal(3,2) NOT NULL,
  `remarks` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `grade` (`grade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grading_system`
--

INSERT INTO `grading_system` (`id`, `grade`, `min_percentage`, `max_percentage`, `gpa`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 'A+', 90.00, 100.00, 4.00, 'Outstanding', '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
(2, 'A', 80.00, 89.99, 3.70, 'Excellent', '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
(3, 'B+', 70.00, 79.99, 3.30, 'Very Good', '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
(4, 'B', 60.00, 69.99, 3.00, 'Good', '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
(5, 'C+', 50.00, 59.99, 2.70, 'Satisfactory', '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
(6, 'C', 40.00, 49.99, 2.30, 'Acceptable', '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
(7, 'D', 33.00, 39.99, 1.00, 'Partially Acceptable', '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
(8, 'F', 0.00, 32.99, 0.00, 'Fail', '2025-03-28 04:26:17', '2025-05-02 08:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `loginlogs`
--

DROP TABLE IF EXISTS `loginlogs`;
CREATE TABLE `loginlogs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `login_time` datetime NOT NULL,
  `logout_time` datetime DEFAULT NULL,
  `session_duration` int(11) DEFAULT NULL,
  `status` enum('success','failed') NOT NULL DEFAULT 'success',
  `failure_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_loginlogs_login_time` (`login_time`),
  KEY `idx_loginlogs_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
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
  KEY `idx_notifications_created` (`created_at`),
  KEY `idx_notifications_type` (`notification_type`),
  KEY `idx_notifications_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `results`
--

DROP TABLE IF EXISTS `results`;
CREATE TABLE `results` (
  `result_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `subject_id` varchar(20) NOT NULL,
  `exam_id` int(11) DEFAULT NULL,
  `theory_marks` decimal(5,2) DEFAULT NULL CHECK (`theory_marks` between 0 and 100),
  `practical_marks` decimal(5,2) DEFAULT NULL CHECK (`practical_marks` between 0 and 100),
  `credit_hours` decimal(3,1) NOT NULL DEFAULT 1.0,
  `grade` varchar(5) DEFAULT NULL,
  `gpa` decimal(3,2) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `upload_id` int(11) DEFAULT NULL COMMENT 'Reference to result_uploads table for batch uploads',
  `status` enum('pending','published','withheld') NOT NULL DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID who created the result',
  `updated_by` int(11) DEFAULT NULL COMMENT 'User ID who last updated the result',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`result_id`),
  UNIQUE KEY `idx_unique_result` (`student_id`,`subject_id`,`exam_id`),
  KEY `student_id` (`student_id`),
  KEY `subject_id` (`subject_id`),
  KEY `exam_id` (`exam_id`),
  KEY `upload_id` (`upload_id`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_results_status` (`status`),
  KEY `idx_results_grade` (`grade`),
  KEY `idx_results_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `result_uploads`
--

DROP TABLE IF EXISTS `result_uploads`;
CREATE TABLE `result_uploads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Draft','Published','Archived','Error') NOT NULL DEFAULT 'Draft',
  `uploaded_by` int(11) NOT NULL,
  `upload_date` datetime NOT NULL,
  `student_count` int(11) NOT NULL DEFAULT 0,
  `success_count` int(11) NOT NULL DEFAULT 0,
  `error_count` int(11) NOT NULL DEFAULT 0,
  `error_details` text DEFAULT NULL,
  `is_manual_entry` tinyint(1) NOT NULL DEFAULT 0,
  `exam_id` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `exam_id` (`exam_id`),
  KEY `class_id` (`class_id`),
  KEY `idx_result_uploads_status` (`status`),
  KEY `idx_result_uploads_date` (`upload_date`),
  KEY `idx_result_uploads_manual` (`is_manual_entry`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether setting is visible to non-admin users',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_settings_public` (`is_public`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_id`, `setting_key`, `setting_value`, `description`, `is_public`, `created_at`, `updated_at`) VALUES
(1, 'school_name', 'ABC School', 'Name of the school', 1, '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
(2, 'school_address', '123 Main Street, City, Country', 'Address of the school', 1, '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
(3, 'school_phone', '+1234567890', 'Contact number of the school', 1, '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
(4, 'school_email', 'info@abcschool.com', 'Email address of the school', 1, '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
(5, 'school_website', 'www.abcschool.com', 'Website of the school', 1, '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
(6, 'academic_year', '2023-2024', 'Current academic year', 1, '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
(7, 'result_publish_date', '2024-03-15', 'Date when results will be published', 1, '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
(8, 'school_logo', '', 'Path to school logo image', 1, '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
(9, 'result_header', 'SECONDARY EDUCATION EXAMINATION', 'Header text for result sheets', 1, '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
(10, 'result_footer', 'This is a computer-generated document. No signature is required.', 'Footer text for result sheets', 1, '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
(11, 'enable_result_download', '1', 'Allow students to download results', 0, '2025-05-02 08:30:00', '2025-05-02 08:30:00'),
(12, 'enable_progress_tracking', '1', 'Enable progress tracking features', 0, '2025-05-02 08:30:00', '2025-05-02 08:30:00'),
(13, 'max_login_attempts', '5', 'Maximum login attempts before account lockout', 0, '2025-05-02 08:30:00', '2025-05-02 08:30:00'),
(14, 'lockout_duration_minutes', '30', 'Account lockout duration in minutes', 0, '2025-05-02 08:30:00', '2025-05-02 08:30:00'),
(15, 'maintenance_mode', '0', 'System maintenance mode (0=off, 1=on)', 0, '2025-05-02 08:30:00', '2025-05-02 08:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
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
  `parent_email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `roll_number` (`roll_number`,`class_id`,`batch_year`),
  UNIQUE KEY `registration_number` (`registration_number`),
  KEY `user_id` (`user_id`),
  KEY `class_id` (`class_id`),
  KEY `idx_students_batch` (`batch_year`),
  KEY `idx_students_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_performance`
--

DROP TABLE IF EXISTS `student_performance`;
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
  KEY `exam_id` (`exam_id`),
  KEY `idx_performance_gpa` (`gpa`),
  KEY `idx_performance_rank` (`rank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
CREATE TABLE `subjects` (
  `subject_id` varchar(20) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `subject_code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `full_marks_theory` decimal(5,2) NOT NULL DEFAULT 100.00,
  `full_marks_practical` decimal(5,2) NOT NULL DEFAULT 0.00,
  `pass_marks_theory` decimal(5,2) NOT NULL DEFAULT 40.00,
  `pass_marks_practical` decimal(5,2) NOT NULL DEFAULT 0.00,
  `credit_hours` decimal(3,1) NOT NULL DEFAULT 1.0,
  `is_optional` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`subject_id`),
  UNIQUE KEY `subject_code` (`subject_code`),
  KEY `idx_subjects_active` (`is_active`),
  KEY `idx_subjects_optional` (`is_optional`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`subject_id`, `subject_name`, `subject_code`, `description`, `full_marks_theory`, `full_marks_practical`, `pass_marks_theory`, `pass_marks_practical`, `credit_hours`, `is_optional`, `is_active`, `created_at`, `updated_at`) VALUES
('101', 'COMP. ENGLISH', 'ENG101', 'Compulsory English', 80.00, 20.00, 32.00, 8.00, 4.0, 0, 1, '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
('102', 'COMP. NEPALI', 'NEP102', 'Compulsory Nepali', 80.00, 20.00, 32.00, 8.00, 4.0, 0, 1, '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
('103', 'COMP. MATHEMATICS', 'MATH103', 'Compulsory Mathematics', 80.00, 20.00, 32.00, 8.00, 4.0, 0, 1, '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
('104', 'COMP. SCIENCE', 'SCI104', 'Compulsory Science', 75.00, 25.00, 30.00, 10.00, 4.0, 0, 1, '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
('105', 'COMP. SOCIAL STUDIES', 'SOC105', 'Compulsory Social Studies', 75.00, 25.00, 30.00, 10.00, 4.0, 0, 1, '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
('106', 'COMP. HEALTH, POP & ENV EDU', 'HPE106', 'Compulsory Health, Population & Environment Education', 75.00, 25.00, 30.00, 10.00, 4.0, 0, 1, '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
('107', 'OPT.I ECONOMICS', 'ECO107', 'Optional Economics', 80.00, 20.00, 32.00, 8.00, 4.0, 1, 1, '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
('108', 'OPT.II OFFICE MGMT & ACCOUNT', 'OMA108', 'Optional Office Management & Account', 70.00, 30.00, 28.00, 12.00, 4.0, 1, 1, '2025-03-28 04:26:17', '2025-05-02 08:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

DROP TABLE IF EXISTS `teachers`;
CREATE TABLE `teachers` (
  `teacher_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `qualification` varchar(100) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`teacher_id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_teachers_department` (`department`),
  KEY `idx_teachers_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teachersubjects`
--

DROP TABLE IF EXISTS `teachersubjects`;
CREATE TABLE `teachersubjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `subject_id` varchar(20) NOT NULL,
  `class_id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `teacher_subject_class_year` (`teacher_id`,`subject_id`,`class_id`,`academic_year`),
  KEY `subject_id` (`subject_id`),
  KEY `class_id` (`class_id`),
  KEY `idx_teachersubjects_academic_year` (`academic_year`),
  KEY `idx_teachersubjects_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teacher_activities`
--

DROP TABLE IF EXISTS `teacher_activities`;
CREATE TABLE `teacher_activities` (
  `activity_id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `activity_type` enum('login','logout','marks_update','view_performance','print_report','other') NOT NULL,
  `description` text NOT NULL,
  `related_id` int(11) DEFAULT NULL COMMENT 'ID of related entity (exam_id, result_id, etc.)',
  `ip_address`  int(11) DEFAULT NULL COMMENT 'ID of related entity (exam_id, result_id, etc.)',
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`activity_id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `idx_activities_type` (`activity_type`),
  KEY `idx_activities_timestamp` (`timestamp`),
  KEY `idx_activities_related` (`related_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','teacher','student') NOT NULL,
  `status` enum('active','inactive','pending','locked') DEFAULT 'active',
  `profile_image` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
  `last_failed_login` datetime DEFAULT NULL,
  `password_reset_token` varchar(100) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `email_verification_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_email_verified` (`email_verified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  ADD CONSTRAINT `results_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_ibfk_3` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_ibfk_4` FOREIGN KEY (`upload_id`) REFERENCES `result_uploads` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `results_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `results_ibfk_6` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `result_uploads`
--
ALTER TABLE `result_uploads`
  ADD CONSTRAINT `result_uploads_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `result_uploads_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `result_uploads_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`) ON DELETE SET NULL;

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
  ADD CONSTRAINT `teachersubjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teachersubjects_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_activities`
--
ALTER TABLE `teacher_activities`
  ADD CONSTRAINT `teacher_activities_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE;

-- Add triggers for automatic grade calculation
DELIMITER $$

CREATE TRIGGER IF NOT EXISTS before_result_insert
BEFORE INSERT ON results
FOR EACH ROW
BEGIN
    DECLARE v_grade VARCHAR(5);
    DECLARE v_gpa DECIMAL(3,2);
    DECLARE v_total_marks DECIMAL(5,2);
    DECLARE v_percentage DECIMAL(5,2);
    
    -- Calculate total marks and percentage
    IF NEW.practical_marks IS NULL THEN
        SET v_total_marks = NEW.theory_marks;
    ELSE
        SET v_total_marks = NEW.theory_marks + NEW.practical_marks;
    END IF;
    
    -- Get grade and GPA from grading system
    SELECT grade, gpa INTO v_grade, v_gpa
    FROM grading_system
    WHERE v_total_marks BETWEEN min_percentage AND max_percentage
    LIMIT 1;
    
    -- Set the calculated values if not provided
    IF NEW.grade IS NULL THEN
        SET NEW.grade = v_grade;
    END IF;
    
    IF NEW.gpa IS NULL THEN
        SET NEW.gpa = v_gpa;
    END IF;
END$$

CREATE TRIGGER IF NOT EXISTS before_result_update
BEFORE UPDATE ON results
FOR EACH ROW
BEGIN
    DECLARE v_grade VARCHAR(5);
    DECLARE v_gpa DECIMAL(3,2);
    DECLARE v_total_marks DECIMAL(5,2);
    DECLARE v_percentage DECIMAL(5,2);
    
    -- Only recalculate if marks have changed
    IF NEW.theory_marks != OLD.theory_marks OR 
       (NEW.practical_marks IS NOT NULL AND OLD.practical_marks IS NOT NULL AND NEW.practical_marks != OLD.practical_marks) OR
       (NEW.practical_marks IS NULL AND OLD.practical_marks IS NOT NULL) OR
       (NEW.practical_marks IS NOT NULL AND OLD.practical_marks IS NULL) THEN
        
        -- Calculate total marks and percentage
        IF NEW.practical_marks IS NULL THEN
            SET v_total_marks = NEW.theory_marks;
        ELSE
            SET v_total_marks = NEW.theory_marks + NEW.practical_marks;
        END IF;
        
        -- Get grade and GPA from grading system
        SELECT grade, gpa INTO v_grade, v_gpa
        FROM grading_system
        WHERE v_total_marks BETWEEN min_percentage AND max_percentage
        LIMIT 1;
        
        -- Set the calculated values
        SET NEW.grade = v_grade;
        SET NEW.gpa = v_gpa;
    END IF;
END$$

DELIMITER ;

-- Add stored procedures for common operations
DELIMITER $$

-- Calculate student GPA across all subjects
CREATE PROCEDURE IF NOT EXISTS CalculateStudentGPA(
    IN p_student_id VARCHAR(20),
    IN p_exam_id INT
)
BEGIN
    DECLARE v_total_points DECIMAL(10,2) DEFAULT 0;
    DECLARE v_total_credits DECIMAL(5,1) DEFAULT 0;
    DECLARE v_gpa DECIMAL(3,2);
    
    -- Calculate total grade points and credits
    SELECT 
        SUM(r.gpa * r.credit_hours) AS total_points,
        SUM(r.credit_hours) AS total_credits
    INTO v_total_points, v_total_credits
    FROM results r
    WHERE r.student_id = p_student_id
    AND (p_exam_id IS NULL OR r.exam_id = p_exam_id);
    
    -- Calculate GPA
    IF v_total_credits > 0 THEN
        SET v_gpa = v_total_points / v_total_credits;
    ELSE
        SET v_gpa = 0;
    END IF;
    
    -- Return the calculated GPA
    SELECT v_gpa AS gpa;
END$$

-- Generate student performance summary
CREATE PROCEDURE IF NOT EXISTS GenerateStudentPerformance(
    IN p_student_id VARCHAR(20),
    IN p_exam_id INT
)
BEGIN
    DECLARE v_gpa DECIMAL(3,2);
    DECLARE v_total_subjects INT;
    DECLARE v_subjects_passed INT;
    DECLARE v_rank INT;
    
    -- Calculate GPA
    CALL CalculateStudentGPA(p_student_id, p_exam_id);
    
    -- Count total subjects and passed subjects
    SELECT 
        COUNT(*) AS total_subjects,
        SUM(CASE WHEN r.grade != 'F' THEN 1 ELSE 0 END) AS subjects_passed
    INTO v_total_subjects, v_subjects_passed
    FROM results r
    WHERE r.student_id = p_student_id
    AND (p_exam_id IS NULL OR r.exam_id = p_exam_id);
    
    -- Calculate rank (if exam_id is provided)
    IF p_exam_id IS NOT NULL THEN
        SELECT COUNT(*) + 1 INTO v_rank
        FROM (
            SELECT s.student_id, AVG(r.gpa) AS avg_gpa
            FROM students s
            JOIN results r ON s.student_id = r.student_id
            WHERE r.exam_id = p_exam_id
            GROUP BY s.student_id
            HAVING AVG(r.gpa) > (
                SELECT AVG(r2.gpa)
                FROM results r2
                WHERE r2.student_id = p_student_id
                AND r2.exam_id = p_exam_id
            )
        ) AS higher_ranks;
    ELSE
        SET v_rank = NULL;
    END IF;
    
    -- Insert or update student performance record
    INSERT INTO student_performance (
        student_id, exam_id, average_marks, gpa, 
        total_subjects, subjects_passed, rank, 
        created_at, updated_at
    )
    VALUES (
        p_student_id, p_exam_id, NULL, v_gpa,
        v_total_subjects, v_subjects_passed, v_rank,
        NOW(), NOW()
    )
    ON DUPLICATE KEY UPDATE
        gpa = v_gpa,
        total_subjects = v_total_subjects,
        subjects_passed = v_subjects_passed,
        rank = v_rank,
        updated_at = NOW();
    
    -- Return the performance data
    SELECT * FROM student_performance
    WHERE student_id = p_student_id
    AND (p_exam_id IS NULL OR exam_id = p_exam_id);
END$$

DELIMITER ;

-- Add sample data for testing
INSERT INTO users (user_id, username, password, full_name, email, role, status, created_at, updated_at) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@example.com', 'admin', 'active', '2025-04-03 13:58:45', '2025-04-03 13:58:45'),
(2, 'student1', '$2y$10$8zUUpfvHvJqMnJ4gJk.Cj.Z/BvWQS1zNFW9CMhbRvDpRRUL2jEjGK', 'John Doe', 'student1@example.com', 'student', 'active', '2025-03-28 04:26:17', '2025-04-03 13:58:45'),
(3, 'teacher1', '$2y$10$8zUUpfvHvJqMnJ4gJk.Cj.Z/BvWQS1zNFW9CMhbRvDpRRUL2jEjGK', 'Jane Smith', 'teacher1@example.com', 'teacher', 'active', '2025-03-28 04:26:17', '2025-04-03 13:58:45');

INSERT INTO students (student_id, user_id, roll_number, registration_number, class_id, batch_year, date_of_birth, gender, created_at, updated_at) VALUES
('S001', 2, 'R001', 'REG001', 1, '2023', '2005-01-01', 'male', '2025-03-28 04:26:17', '2025-04-03 13:58:45');

INSERT INTO teachers (teacher_id, user_id, employee_id, qualification, department, joining_date, created_at, updated_at) VALUES
(1, 3, 'T001', 'M.Sc. in Mathematics', 'Mathematics', '2020-01-01', '2025-03-28 04:26:17', '2025-04-03 13:58:45');

INSERT INTO teachersubjects (id, teacher_id, subject_id, class_id, academic_year, created_at) VALUES
(1, 1, '101', 1, '2023-2024', '2025-03-28 04:26:17');

-- Insert sample result
INSERT INTO results (result_id, student_id, subject_id, exam_id, theory_marks, practical_marks, credit_hours, grade, gpa, created_at, updated_at) VALUES
(1, 'S001', '101', 1, 85.00, 90.00, 4.0, 'A+', 4.00, '2025-03-28 04:26:17', '2025-04-03 13:58:45');

-- Insert sample performance record
INSERT INTO student_performance (performance_id, student_id, exam_id, average_marks, gpa, total_subjects, subjects_passed, rank, remarks, created_at, updated_at) VALUES
(1, 'S001', 1, 87.50, 3.80, 5, 5, 1, 'Excellent performance', '2025-03-30 02:35:48', '2025-04-03 13:58:45');

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
