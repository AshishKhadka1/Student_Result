-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 05, 2025 at 06:19 AM
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
-- Table structure for table `academic_years`
--

CREATE TABLE `academic_years` (
  `id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_years`
--

INSERT INTO `academic_years` (`id`, `academic_year`, `start_date`, `end_date`, `is_current`, `created_at`, `updated_at`) VALUES
(1, '2023-2024', NULL, NULL, 1, '2025-05-03 11:16:39', '2025-05-03 11:16:39');

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'ADD_EXAM', 'Added exam: Third terminal', NULL, '2025-05-03 14:42:26'),
(2, 1, 'ADD_EXAM', 'Added exam: Third terminal', NULL, '2025-05-03 14:44:49'),
(3, 1, 'ADD_EXAM', 'Added exam: final Terminal', '::1', '2025-05-04 00:45:28'),
(4, 1, 'DELETE_EXAM', 'Deleted exam ID: 3', '::1', '2025-05-04 00:45:44'),
(5, 1, 'UPDATE_EXAM', 'Updated exam ID: 4', '::1', '2025-05-04 00:46:09'),
(6, 1, 'DELETE_EXAM', 'Deleted exam ID: 6', '::1', '2025-05-05 01:59:48'),
(7, 1, 'MANUAL_ENTRY', 'Added/updated 5 results for Student ID: S002, Exam ID: 5', NULL, '2025-05-05 02:57:15'),
(8, 1, 'MANUAL_ENTRY', 'Added/updated 5 results for Student ID: S001, Exam ID: 5', NULL, '2025-05-05 02:59:23'),
(9, 1, 'MANUAL_ENTRY', 'Added/updated 1 results for Student ID: S002, Exam ID: 1', NULL, '2025-05-05 03:34:24');

-- --------------------------------------------------------

--
-- Table structure for table `batch_operations`
--

CREATE TABLE `batch_operations` (
  `batch_id` int(11) NOT NULL,
  `operation_type` enum('entry','update','delete') NOT NULL,
  `subject_id` varchar(20) DEFAULT NULL,
  `exam_id` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `class_id` int(11) NOT NULL,
  `class_name` varchar(100) NOT NULL,
  `class_numeric` int(11) NOT NULL DEFAULT 0,
  `class_teacher_id` int(11) DEFAULT NULL,
  `section` varchar(10) DEFAULT NULL,
  `academic_year` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`class_id`, `class_name`, `class_numeric`, `class_teacher_id`, `section`, `academic_year`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 'Class 11', 11, NULL, 'A', '2023-2024', NULL, 1, '2025-04-03 13:58:45', '2025-05-03 11:16:39'),
(4, 'Class 11', 11, NULL, 'B', '2023-2024', NULL, 1, '2025-04-03 13:58:45', '2025-05-03 11:16:39'),
(5, 'Class 12', 12, NULL, 'A', '2023-2024', NULL, 1, '2025-04-03 13:58:45', '2025-05-03 11:16:39'),
(6, 'Class 12', 12, NULL, 'B', '2023-2024', NULL, 1, '2025-04-03 13:58:45', '2025-05-03 11:16:39');

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

CREATE TABLE `exams` (
  `exam_id` int(11) NOT NULL,
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
  `results_published` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Flag indicating if results are published (1) or not (0)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exams`
--

INSERT INTO `exams` (`exam_id`, `exam_name`, `exam_type`, `class_id`, `start_date`, `end_date`, `total_marks`, `passing_marks`, `academic_year`, `description`, `status`, `exam_date`, `is_active`, `created_at`, `updated_at`, `results_published`) VALUES
(1, 'Midterm Exam', 'midterm', NULL, '2023-10-01', '2023-10-10', 100, 40, '2023-2024', 'Midterm examination for Class 10', 'upcoming', NULL, 1, '2025-03-28 04:26:17', '2025-04-03 13:58:45', 0),
(2, 'First Terminal', '', NULL, NULL, NULL, 0, 0, '2025', '', 'upcoming', '2025-09-01', 1, '2025-05-03 14:17:10', '2025-05-03 14:17:10', 0),
(4, 'second terminal', 'other', 4, NULL, NULL, 0, 0, '2026', '', 'upcoming', '2023-08-08', 1, '2025-05-03 14:42:26', '2025-05-04 00:46:09', 0),
(5, 'Third terminal', '', 6, NULL, NULL, 0, 0, '2020', '', '', '2020-02-02', 1, '2025-05-03 14:44:49', '2025-05-03 14:44:49', 0);

-- --------------------------------------------------------

--
-- Table structure for table `grading_system`
--

CREATE TABLE `grading_system` (
  `id` int(11) NOT NULL,
  `grade` varchar(5) NOT NULL,
  `min_percentage` decimal(5,2) NOT NULL,
  `max_percentage` decimal(5,2) NOT NULL,
  `gpa` decimal(3,2) NOT NULL,
  `remarks` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
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

CREATE TABLE `loginlogs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `login_time` datetime NOT NULL,
  `logout_time` datetime DEFAULT NULL,
  `session_duration` int(11) DEFAULT NULL,
  `status` enum('success','failed') NOT NULL DEFAULT 'success',
  `failure_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loginlogs`
--

INSERT INTO `loginlogs` (`log_id`, `user_id`, `ip_address`, `user_agent`, `login_time`, `logout_time`, `session_duration`, `status`, `failure_reason`) VALUES
(3, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-03 16:53:30', NULL, NULL, 'success', NULL),
(4, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-04 06:02:31', NULL, NULL, 'success', NULL),
(5, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-05 07:43:59', NULL, NULL, 'success', NULL),
(6, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-05 08:46:29', NULL, NULL, 'success', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL COMMENT 'User who triggered the notification',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `notification_type` enum('system','result','exam','announcement','other') NOT NULL,
  `related_id` int(11) DEFAULT NULL COMMENT 'ID of related entity (exam_id, result_id, etc.)',
  `is_read` tinyint(1) DEFAULT 0,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `sender_id`, `title`, `message`, `notification_type`, `related_id`, `is_read`, `priority`, `expires_at`, `created_at`) VALUES
(1, 1, NULL, 'Welcome to Result Management System', 'Thank you for registering. Your account has been created successfully.', 'system', NULL, 0, 'medium', NULL, '2025-05-02 14:53:02'),
(2, 2, NULL, 'Welcome to Result Management System', 'Thank you for registering. Your account has been created successfully.', 'system', NULL, 0, 'medium', NULL, '2025-05-02 14:53:26'),
(3, 4, NULL, 'Welcome to Result Management System', 'Thank you for registering. Your account has been created successfully.', 'system', NULL, 0, 'medium', NULL, '2025-05-03 13:31:41'),
(6, 7, NULL, 'Welcome to Result Management System', 'Thank you for registering. Your account has been created successfully.', 'system', NULL, 0, 'medium', NULL, '2025-05-05 02:03:12');

-- --------------------------------------------------------

--
-- Table structure for table `results`
--

CREATE TABLE `results` (
  `result_id` int(11) NOT NULL,
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
  `batch_id` int(11) DEFAULT NULL COMMENT 'ID for grouping batch entries',
  `status` enum('pending','published','withheld') NOT NULL DEFAULT 'pending',
  `status_changed_at` timestamp NULL DEFAULT NULL,
  `status_changed_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID who created the result',
  `updated_by` int(11) DEFAULT NULL COMMENT 'User ID who last updated the result',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `results`
--

INSERT INTO `results` (`result_id`, `student_id`, `subject_id`, `exam_id`, `theory_marks`, `practical_marks`, `credit_hours`, `grade`, `gpa`, `remarks`, `upload_id`, `batch_id`, `status`, `status_changed_at`, `status_changed_by`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(4, 'S001', '106', 2, 80.00, 90.00, 1.0, 'F', 0.00, NULL, NULL, NULL, 'pending', NULL, NULL, 1, 1, '2025-05-04 10:10:41', '2025-05-04 10:10:41'),
(5, 'S001', '103', 2, 30.00, 35.00, 2.0, 'B', 3.00, NULL, NULL, NULL, 'pending', NULL, NULL, 1, 1, '2025-05-04 10:13:20', '2025-05-04 10:13:20'),
(6, 'S001', '104', 2, 13.00, 59.00, 2.0, 'B+', 3.30, NULL, NULL, NULL, 'pending', NULL, NULL, 1, 1, '2025-05-04 14:32:14', '2025-05-04 14:32:14'),
(9, 'S002', '106', 5, 36.00, 3.00, 1.0, 'D', 1.00, 'Aliqua Ex voluptate', 1, NULL, 'pending', NULL, NULL, NULL, NULL, '2025-05-05 02:57:15', '2025-05-05 02:57:15'),
(10, 'S002', '105', 5, 49.00, 90.00, 1.0, 'A+', 4.00, 'Et fugit nulla omni', 1, NULL, 'pending', NULL, NULL, NULL, NULL, '2025-05-05 02:57:15', '2025-05-05 02:57:15'),
(11, 'S002', '104', 5, 70.00, 36.00, 1.0, 'A+', 4.00, 'Doloremque quo neque', 1, NULL, 'pending', NULL, NULL, NULL, NULL, '2025-05-05 02:57:15', '2025-05-05 02:57:15'),
(12, 'S002', '101', 5, 2.00, 0.00, 4.0, 'F', 0.00, '', 3, NULL, 'pending', NULL, NULL, NULL, 1, '2025-05-05 02:57:15', '2025-05-05 04:05:10'),
(13, 'S002', '120', 5, 90.00, 13.00, 1.0, 'A+', 4.00, 'Mollit expedita labo', 1, NULL, 'pending', NULL, NULL, NULL, NULL, '2025-05-05 02:57:15', '2025-05-05 02:57:15'),
(14, 'S001', '104', 5, 76.00, 89.00, 1.0, 'A+', 4.00, 'Doloremque deserunt ', 1, NULL, 'pending', NULL, NULL, NULL, NULL, '2025-05-05 02:59:23', '2025-05-05 02:59:23'),
(15, 'S001', '106', 5, 6.00, 31.00, 1.0, 'D', 1.00, 'Voluptatem dolor pro', 1, NULL, 'pending', NULL, NULL, NULL, NULL, '2025-05-05 02:59:23', '2025-05-05 02:59:23'),
(16, 'S001', '120', 5, 90.00, 82.00, 1.0, 'A+', 4.00, 'Sunt ullam omnis es', 1, NULL, 'pending', NULL, NULL, NULL, NULL, '2025-05-05 02:59:23', '2025-05-05 02:59:23'),
(17, 'S001', '101', 5, 93.00, 78.00, 1.0, 'A+', 4.00, 'Tempora reprehenderi', 1, NULL, 'pending', NULL, NULL, NULL, NULL, '2025-05-05 02:59:23', '2025-05-05 02:59:23'),
(18, 'S002', '107', 1, 0.00, 28.00, 1.0, 'F', 0.00, 'Sapiente sit ut dol', 1, NULL, 'pending', NULL, NULL, NULL, NULL, '2025-05-05 03:34:24', '2025-05-05 03:34:24');

-- --------------------------------------------------------

--
-- Table structure for table `resultuploads`
--

CREATE TABLE `resultuploads` (
  `id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `upload_date` datetime NOT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `exam_id` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `student_count` int(11) DEFAULT 0,
  `success_count` int(11) NOT NULL DEFAULT 0,
  `error_count` int(11) NOT NULL DEFAULT 0,
  `status` enum('Draft','Processing','Published','Failed') NOT NULL DEFAULT 'Draft'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `resultuploads`
--

INSERT INTO `resultuploads` (`id`, `file_name`, `description`, `file_path`, `upload_date`, `uploaded_by`, `exam_id`, `class_id`, `student_count`, `success_count`, `error_count`, `status`) VALUES
(1, 'Manual Entry', 'Manually entered results', NULL, '2025-05-04 08:42:43', 1, NULL, NULL, 0, 0, 0, '');

-- --------------------------------------------------------

--
-- Table structure for table `result_history`
--

CREATE TABLE `result_history` (
  `history_id` int(11) NOT NULL,
  `result_id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `subject_id` varchar(20) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `theory_marks` decimal(5,2) DEFAULT NULL,
  `practical_marks` decimal(5,2) DEFAULT NULL,
  `grade` varchar(5) DEFAULT NULL,
  `gpa` decimal(3,2) DEFAULT NULL,
  `changed_by` int(11) NOT NULL,
  `change_type` enum('create','update','delete') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `result_summary`
-- (See below for the actual view)
--
CREATE TABLE `result_summary` (
`exam_id` int(11)
,`subject_id` varchar(20)
,`class_id` int(11)
,`total_results` bigint(21)
,`average_marks` decimal(10,6)
,`highest_marks` decimal(6,2)
,`lowest_marks` decimal(6,2)
,`fail_count` decimal(22,0)
,`pass_count` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Table structure for table `result_uploads`
--

CREATE TABLE `result_uploads` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `result_uploads`
--

INSERT INTO `result_uploads` (`id`, `file_name`, `description`, `status`, `uploaded_by`, `upload_date`, `student_count`, `success_count`, `error_count`, `error_details`, `is_manual_entry`, `exam_id`, `class_id`, `created_at`, `updated_at`) VALUES
(1, 'Manual Entry', 'Manual entry for Student ID: S002, Exam ID: 5', 'Published', 1, '2025-05-05 08:42:15', 3, 11, 0, NULL, 0, 5, 3, '2025-05-05 02:57:15', '2025-05-05 03:34:24'),
(2, 'Batch Entry', 'Batch entry for subject ID 105', 'Published', 1, '2025-05-05 09:49:31', 2, 2, 0, NULL, 1, 2, 5, '2025-05-05 04:04:31', '2025-05-05 04:04:31'),
(3, 'Batch Entry', 'Batch entry for subject ID 101', 'Published', 1, '2025-05-05 09:50:10', 3, 3, 0, NULL, 1, 5, 3, '2025-05-05 04:05:10', '2025-05-05 04:05:10'),
(4, 'Batch Entry', 'Batch entry for subject ID 102', 'Published', 1, '2025-05-05 10:00:00', 1, 1, 0, NULL, 1, 4, 5, '2025-05-05 04:15:00', '2025-05-05 04:15:00');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `section_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `section_name` varchar(50) NOT NULL,
  `capacity` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`section_id`, `class_id`, `section_name`, `capacity`, `teacher_id`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 3, 'A', NULL, NULL, 1, '2025-05-03 11:16:39', '2025-05-03 11:16:39'),
(4, 4, 'B', NULL, NULL, 1, '2025-05-03 11:16:39', '2025-05-03 11:16:39'),
(5, 5, 'A', NULL, NULL, 1, '2025-05-03 11:16:39', '2025-05-03 11:16:39'),
(6, 6, 'B', NULL, NULL, 1, '2025-05-03 11:16:39', '2025-05-03 11:16:39'),
(8, 5, 'Sacha Heath', 46, 2, 1, '2025-05-03 11:41:38', '2025-05-03 11:41:38'),
(11, 3, 'r', 74, 2, 1, '2025-05-03 11:43:00', '2025-05-03 11:43:00');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether setting is visible to non-admin users',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `user_id`, `roll_number`, `registration_number`, `class_id`, `batch_year`, `date_of_birth`, `gender`, `address`, `phone`, `parent_name`, `parent_phone`, `parent_email`, `is_active`, `created_at`, `updated_at`) VALUES
('S001', 1, '8', '78787878', 5, '2023', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-05-02 14:53:02', '2025-05-02 14:53:02'),
('S002', 7, '1', '123', 3, '2023', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-05-05 02:03:12', '2025-05-05 02:03:12');

-- --------------------------------------------------------

--
-- Table structure for table `student_performance`
--

CREATE TABLE `student_performance` (
  `performance_id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `average_marks` decimal(5,2) DEFAULT NULL,
  `gpa` decimal(3,2) DEFAULT NULL,
  `total_subjects` int(11) DEFAULT NULL,
  `subjects_passed` int(11) DEFAULT NULL,
  `rank` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_performance`
--

INSERT INTO `student_performance` (`performance_id`, `student_id`, `exam_id`, `average_marks`, `gpa`, `total_subjects`, `subjects_passed`, `rank`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 'S002', 5, 77.80, 2.60, 5, 4, 2, NULL, '2025-05-05 02:57:15', '2025-05-05 04:05:10'),
(2, 'S001', 5, 136.25, 3.25, 4, 4, 1, NULL, '2025-05-05 02:59:23', '2025-05-05 04:05:10'),
(3, 'S002', 1, 28.00, 0.00, 1, 0, NULL, NULL, '2025-05-05 03:34:24', '2025-05-05 03:34:24'),
(4, 'S001', 2, 81.75, 1.58, 4, 2, 1, NULL, '2025-05-05 04:04:31', '2025-05-05 04:04:31'),
(5, 'S001', 4, 20.00, 0.00, 1, 0, 1, NULL, '2025-05-05 04:15:00', '2025-05-05 04:15:00');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
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
('108', 'OPT.II OFFICE MGMT & ACCOUNT', 'OMA108', 'Optional Office Management & Account', 70.00, 30.00, 28.00, 12.00, 4.0, 1, 1, '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
('120', 'optinal Math', NULL, '', 100.00, 0.00, 40.00, 0.00, 1.0, 0, 1, '2025-05-03 14:14:33', '2025-05-03 14:14:33');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `teacher_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `qualification` varchar(100) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`teacher_id`, `user_id`, `employee_id`, `qualification`, `department`, `joining_date`, `phone`, `address`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 2, '89', 'Bachelor', 'Bca', NULL, NULL, NULL, 1, '2025-05-02 14:53:26', '2025-05-02 14:53:26'),
(3, 4, '77', 'Master', 'bit', NULL, NULL, NULL, 1, '2025-05-03 13:31:41', '2025-05-03 13:31:41');

-- --------------------------------------------------------

--
-- Table structure for table `teachersubjects`
--

CREATE TABLE `teachersubjects` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `subject_id` varchar(20) NOT NULL,
  `class_id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teacher_activities`
--

CREATE TABLE `teacher_activities` (
  `activity_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `activity_type` enum('login','logout','marks_update','view_performance','print_report','other') NOT NULL,
  `description` text NOT NULL,
  `related_id` int(11) DEFAULT NULL COMMENT 'ID of related entity (exam_id, result_id, etc.)',
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `full_name`, `email`, `role`, `status`, `profile_image`, `last_login`, `failed_login_attempts`, `last_failed_login`, `password_reset_token`, `password_reset_expires`, `email_verified`, `email_verification_token`, `created_at`, `updated_at`) VALUES
(1, 'tilakyzypo', '$2y$10$pf7DlvBIobaFjaCK48N6a.uUMwHrZMiWdbOy/MGVn0pFpZHlXmOt6', 'Thaddeus Lowe', 'fycewo@mailinator.com', 'student', 'active', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, '2025-05-02 14:53:02', '2025-05-02 14:53:45'),
(2, 'nesicisus', '$2y$10$1yooYWZ5MWy4FqOaxlKhA.OyjcFk.2cwnCVjNpyCBoA2PAinyP.c.', 'Martena Buckner', 'ryhenuz@mailinator.com', 'teacher', 'active', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, '2025-05-02 14:53:26', '2025-05-04 02:18:19'),
(4, 'repep', '$2y$10$EpCFJE/UhfDGEBtaJ0XNU.3t/zpdZoY08A2zI1xwzw8w484dP9G9K', 'Ainsley Cobb', 'vimovy@mailinator.com', 'teacher', 'active', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, '2025-05-03 13:31:41', '2025-05-03 13:31:41'),
(7, 'ashish', '$2y$10$u.wKDtAr771cbsckfnEc/upFuLV3GAvF.JRHlSzTrmvzqhEg7HltK', 'Ashish khadka', 'vukaziliw@mailinator.com', 'student', 'active', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, '2025-05-05 02:03:12', '2025-05-05 02:03:20');

-- --------------------------------------------------------

--
-- Structure for view `result_summary`
--
DROP TABLE IF EXISTS `result_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `result_summary`  AS SELECT `r`.`exam_id` AS `exam_id`, `r`.`subject_id` AS `subject_id`, `s`.`class_id` AS `class_id`, count(`r`.`result_id`) AS `total_results`, avg(`r`.`theory_marks` + `r`.`practical_marks`) AS `average_marks`, max(`r`.`theory_marks` + `r`.`practical_marks`) AS `highest_marks`, min(`r`.`theory_marks` + `r`.`practical_marks`) AS `lowest_marks`, sum(case when `r`.`grade` = 'F' then 1 else 0 end) AS `fail_count`, sum(case when `r`.`grade` <> 'F' then 1 else 0 end) AS `pass_count` FROM (`results` `r` join `students` `s` on(`r`.`student_id` = `s`.`student_id`)) GROUP BY `r`.`exam_id`, `r`.`subject_id`, `s`.`class_id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `academic_year` (`academic_year`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_logs_action` (`action`),
  ADD KEY `idx_logs_created_at` (`created_at`);

--
-- Indexes for table `batch_operations`
--
ALTER TABLE `batch_operations`
  ADD PRIMARY KEY (`batch_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `exam_id` (`exam_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`class_id`),
  ADD KEY `idx_classes_academic_year` (`academic_year`),
  ADD KEY `idx_classes_active` (`is_active`),
  ADD KEY `idx_classes_teacher` (`class_teacher_id`);

--
-- Indexes for table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`exam_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `idx_exams_academic_year` (`academic_year`),
  ADD KEY `idx_exams_status` (`status`),
  ADD KEY `idx_exams_active` (`is_active`);

--
-- Indexes for table `grading_system`
--
ALTER TABLE `grading_system`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `grade` (`grade`);

--
-- Indexes for table `loginlogs`
--
ALTER TABLE `loginlogs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_loginlogs_login_time` (`login_time`),
  ADD KEY `idx_loginlogs_status` (`status`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `idx_notifications_user` (`user_id`,`is_read`),
  ADD KEY `idx_notifications_created` (`created_at`),
  ADD KEY `idx_notifications_type` (`notification_type`),
  ADD KEY `idx_notifications_expires` (`expires_at`);

--
-- Indexes for table `results`
--
ALTER TABLE `results`
  ADD PRIMARY KEY (`result_id`),
  ADD UNIQUE KEY `idx_unique_result` (`student_id`,`subject_id`,`exam_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `exam_id` (`exam_id`),
  ADD KEY `upload_id` (`upload_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_results_status` (`status`),
  ADD KEY `idx_results_grade` (`grade`),
  ADD KEY `idx_results_created_at` (`created_at`),
  ADD KEY `results_ibfk_7` (`status_changed_by`),
  ADD KEY `idx_results_batch_id` (`batch_id`);

--
-- Indexes for table `resultuploads`
--
ALTER TABLE `resultuploads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `exam_id` (`exam_id`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `result_history`
--
ALTER TABLE `result_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `result_id` (`result_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `exam_id` (`exam_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `result_uploads`
--
ALTER TABLE `result_uploads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `exam_id` (`exam_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `idx_result_uploads_status` (`status`),
  ADD KEY `idx_result_uploads_date` (`upload_date`),
  ADD KEY `idx_result_uploads_manual` (`is_manual_entry`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`section_id`),
  ADD UNIQUE KEY `class_section_unique` (`class_id`,`section_name`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `idx_sections_active` (`is_active`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_settings_public` (`is_public`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `registration_number` (`registration_number`),
  ADD UNIQUE KEY `roll_number` (`roll_number`,`class_id`,`batch_year`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `idx_students_batch` (`batch_year`),
  ADD KEY `idx_students_active` (`is_active`);

--
-- Indexes for table `student_performance`
--
ALTER TABLE `student_performance`
  ADD PRIMARY KEY (`performance_id`),
  ADD UNIQUE KEY `student_exam_unique` (`student_id`,`exam_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `exam_id` (`exam_id`),
  ADD KEY `idx_performance_gpa` (`gpa`),
  ADD KEY `idx_performance_rank` (`rank`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`subject_id`),
  ADD UNIQUE KEY `subject_code` (`subject_code`),
  ADD KEY `idx_subjects_active` (`is_active`),
  ADD KEY `idx_subjects_optional` (`is_optional`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`teacher_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_teachers_department` (`department`),
  ADD KEY `idx_teachers_active` (`is_active`);

--
-- Indexes for table `teachersubjects`
--
ALTER TABLE `teachersubjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `teacher_subject_class_year` (`teacher_id`,`subject_id`,`class_id`,`academic_year`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `idx_teachersubjects_academic_year` (`academic_year`),
  ADD KEY `idx_teachersubjects_active` (`is_active`);

--
-- Indexes for table `teacher_activities`
--
ALTER TABLE `teacher_activities`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `idx_activities_type` (`activity_type`),
  ADD KEY `idx_activities_timestamp` (`timestamp`),
  ADD KEY `idx_activities_related` (`related_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_status` (`status`),
  ADD KEY `idx_users_email_verified` (`email_verified`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `batch_operations`
--
ALTER TABLE `batch_operations`
  MODIFY `batch_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `class_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `exams`
--
ALTER TABLE `exams`
  MODIFY `exam_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `grading_system`
--
ALTER TABLE `grading_system`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `loginlogs`
--
ALTER TABLE `loginlogs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `results`
--
ALTER TABLE `results`
  MODIFY `result_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `resultuploads`
--
ALTER TABLE `resultuploads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `result_history`
--
ALTER TABLE `result_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `result_uploads`
--
ALTER TABLE `result_uploads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `section_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `student_performance`
--
ALTER TABLE `student_performance`
  MODIFY `performance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `teacher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `teachersubjects`
--
ALTER TABLE `teachersubjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teacher_activities`
--
ALTER TABLE `teacher_activities`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `batch_operations`
--
ALTER TABLE `batch_operations`
  ADD CONSTRAINT `batch_operations_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `batch_operations_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `batch_operations_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `batch_operations_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `fk_classes_teacher` FOREIGN KEY (`class_teacher_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

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
  ADD CONSTRAINT `results_ibfk_6` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `results_ibfk_7` FOREIGN KEY (`status_changed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `results_ibfk_8` FOREIGN KEY (`batch_id`) REFERENCES `batch_operations` (`batch_id`) ON DELETE SET NULL;

--
-- Constraints for table `resultuploads`
--
ALTER TABLE `resultuploads`
  ADD CONSTRAINT `resultuploads_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `resultuploads_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`) ON DELETE SET NULL;

--
-- Constraints for table `result_history`
--
ALTER TABLE `result_history`
  ADD CONSTRAINT `result_history_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `result_history_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `result_history_ibfk_3` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `result_history_ibfk_4` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `result_uploads`
--
ALTER TABLE `result_uploads`
  ADD CONSTRAINT `result_uploads_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `result_uploads_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `result_uploads_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`) ON DELETE SET NULL;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sections_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
