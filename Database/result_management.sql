-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 11, 2025 at 04:10 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

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

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `update_student_performance` (IN `p_student_id` VARCHAR(20), IN `p_exam_id` INT)   BEGIN
  DECLARE v_avg_marks DECIMAL(5,2);
  DECLARE v_gpa DECIMAL(3,2);
  DECLARE v_total_subjects INT;
  DECLARE v_subjects_passed INT;
  DECLARE v_rank INT;
  
  -- Calculate average marks
  SELECT 
    AVG(theory_marks + practical_marks) INTO v_avg_marks
  FROM 
    results
  WHERE 
    student_id = p_student_id AND exam_id = p_exam_id;
  
  -- Calculate GPA (weighted average)
  SELECT 
    SUM(gpa * credit_hours) / SUM(credit_hours) INTO v_gpa
  FROM 
    results
  WHERE 
    student_id = p_student_id AND exam_id = p_exam_id;
  
  -- Count total subjects
  SELECT 
    COUNT(*) INTO v_total_subjects
  FROM 
    results
  WHERE 
    student_id = p_student_id AND exam_id = p_exam_id;
  
  -- Count passed subjects
  SELECT 
    COUNT(*) INTO v_subjects_passed
  FROM 
    results
  WHERE 
    student_id = p_student_id AND exam_id = p_exam_id AND grade <> 'F';
  
  -- Calculate rank (this is a simplified version)
  SELECT 
    COUNT(*) + 1 INTO v_rank
  FROM 
    (SELECT 
      student_id, 
      AVG(gpa) as avg_gpa
     FROM 
      results
     WHERE 
      exam_id = p_exam_id
     GROUP BY 
      student_id
     HAVING 
      AVG(gpa) > v_gpa) AS better_students;
  
  -- Insert or update performance record
  INSERT INTO student_performance 
    (student_id, exam_id, average_marks, gpa, total_subjects, subjects_passed, rank)
  VALUES 
    (p_student_id, p_exam_id, v_avg_marks, v_gpa, v_total_subjects, v_subjects_passed, v_rank)
  ON DUPLICATE KEY UPDATE
    average_marks = v_avg_marks,
    gpa = v_gpa,
    total_subjects = v_total_subjects,
    subjects_passed = v_subjects_passed,
    rank = v_rank,
    updated_at = CURRENT_TIMESTAMP;
END$$

DELIMITER ;

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
(9, 1, 'MANUAL_ENTRY', 'Added/updated 1 results for Student ID: S002, Exam ID: 1', NULL, '2025-05-05 03:34:24'),
(10, 1, 'DELETE_EXAM', 'Deleted exam ID: 4', '::1', '2025-05-07 02:58:31'),
(11, 1, 'PUBLISH_RESULTS', 'Published results for exam ID: 2', '::1', '2025-05-07 02:58:38'),
(12, 1, 'UNPUBLISH_RESULTS', 'Unpublished results for exam ID: 2', '::1', '2025-05-07 02:58:53'),
(13, 1, 'PUBLISH_RESULTS', 'Published results for exam ID: 2', '::1', '2025-05-08 02:02:55'),
(14, 1, 'UNPUBLISH_RESULTS', 'Unpublished results for exam ID: 2', '::1', '2025-05-08 02:47:14'),
(15, 1, 'MANUAL_ENTRY', 'Added/updated 5 results for Student ID: S002, Exam ID: 2', NULL, '2025-05-09 15:13:25'),
(16, 1, 'MANUAL_ENTRY', 'Added/updated 5 results for Student ID: S002, Exam ID: 5', NULL, '2025-05-09 15:42:48'),
(17, 1, 'ADD_EXAM', 'Added exam: First Terminal', '::1', '2025-05-09 16:49:05'),
(18, 1, 'DELETE_EXAM', 'Deleted exam ID: 7', '::1', '2025-05-09 16:50:25'),
(19, 1, 'UPDATE_EXAM', 'Updated exam ID: 2', '::1', '2025-05-09 17:08:39'),
(20, 1, 'ADD_EXAM', 'Added exam: first', '::1', '2025-05-10 05:51:50'),
(21, 1, 'ADD_EXAM', 'Added exam: First Terminal', '::1', '2025-05-10 05:59:18'),
(22, 1, 'ADD_EXAM', 'Added exam: second Terminal', '::1', '2025-05-10 06:10:17'),
(23, 1, 'ADD_EXAM', 'Added exam: final Terminal', '::1', '2025-05-10 06:29:32'),
(24, 1, 'UPDATE_EXAM', 'Updated exam ID: 9', '::1', '2025-05-10 06:32:03'),
(25, 1, 'DELETE_EXAM', 'Deleted exam ID: 9', '::1', '2025-05-10 06:36:17'),
(26, 1, 'DELETE_EXAM', 'Deleted exam ID: 11', '::1', '2025-05-10 06:36:21'),
(27, 1, 'DELETE_EXAM', 'Deleted exam ID: 10', '::1', '2025-05-10 06:36:23'),
(28, 1, 'DELETE_EXAM', 'Deleted exam ID: 8', '::1', '2025-05-10 06:36:33'),
(29, 1, 'ADD_EXAM', 'Added exam: second Terminal', '::1', '2025-05-10 06:41:16'),
(30, 1, 'PUBLISH_RESULTS', 'Published results for exam ID: 2', '::1', '2025-05-10 06:41:34'),
(31, 1, 'UNPUBLISH_RESULTS', 'Unpublished results for exam ID: 2', '::1', '2025-05-10 06:41:37'),
(32, 1, 'UPDATE_EXAM', 'Updated exam ID: 2', '::1', '2025-05-10 06:46:56'),
(33, 1, 'UPDATE_EXAM', 'Updated exam ID: 5', '::1', '2025-05-10 06:48:00'),
(34, 1, 'UPDATE_EXAM', 'Updated exam ID: 1', '::1', '2025-05-10 06:48:15'),
(35, 1, 'UPDATE_EXAM', 'Updated exam ID: 12', '::1', '2025-05-10 06:48:23');

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
(5, 'Class 12', 12, NULL, 'A', '2023-2024', NULL, 1, '2025-04-03 13:58:45', '2025-05-03 11:16:39'),
(8, 'class 5', 0, NULL, NULL, '2002', '', 1, '2025-05-09 17:06:19', '2025-05-09 17:06:19');

-- --------------------------------------------------------

--
-- Table structure for table `classsubjects`
--

CREATE TABLE `classsubjects` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_id` varchar(20) NOT NULL,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT 1,
  `academic_year` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classsubjects`
--

INSERT INTO `classsubjects` (`id`, `class_id`, `subject_id`, `is_mandatory`, `academic_year`, `created_at`, `updated_at`) VALUES
(1, 3, '101', 1, '2023-2024', '2025-05-11 01:37:28', '2025-05-11 01:37:28'),
(2, 3, '102', 1, '2023-2024', '2025-05-11 01:37:28', '2025-05-11 01:37:28'),
(3, 3, '103', 1, '2023-2024', '2025-05-11 01:37:28', '2025-05-11 01:37:28'),
(4, 3, '104', 1, '2023-2024', '2025-05-11 01:37:28', '2025-05-11 01:37:28'),
(5, 3, '105', 1, '2023-2024', '2025-05-11 01:37:28', '2025-05-11 01:37:28'),
(6, 3, '106', 0, '2023-2024', '2025-05-11 01:37:28', '2025-05-11 01:37:28'),
(7, 3, '107', 0, '2023-2024', '2025-05-11 01:37:28', '2025-05-11 01:37:28'),
(8, 3, '108', 0, '2023-2024', '2025-05-11 01:37:28', '2025-05-11 01:37:28'),
(9, 3, '120', 0, '2023-2024', '2025-05-11 01:37:28', '2025-05-11 01:37:28'),
(10, 5, '101', 1, '2023-2024', '2025-05-11 01:37:28', '2025-05-11 01:37:28'),
(11, 5, '102', 1, '2023-2024', '2025-05-11 01:37:28', '2025-05-11 01:37:28'),
(12, 5, '103', 1, '2023-2024', '2025-05-11 01:37:28', '2025-05-11 01:37:28'),
(13, 5, '104', 1, '2023-2024', '2025-05-11 01:37:28', '2025-05-11 01:37:28'),
(14, 5, '105', 1, '2023-2024', '2025-05-11 01:37:28', '2025-05-11 01:37:28'),
(15, 5, '106', 0, '2023-2024', '2025-05-11 01:37:28', '2025-05-11 01:37:28'),
(16, 5, '107', 0, '2023-2024', '2025-05-11 01:37:28', '2025-05-11 01:37:28'),
(17, 5, '108', 0, '2023-2024', '2025-05-11 01:37:28', '2025-05-11 01:37:28'),
(18, 5, '120', 0, '2023-2024', '2025-05-11 01:37:28', '2025-05-11 01:37:28');

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

CREATE TABLE `exams` (
  `exam_id` int(11) NOT NULL,
  `exam_name` varchar(100) NOT NULL,
  `exam_type` enum('midterm','final','quiz','assignment','project','other','First Terminal','Second Terminal','Third Terminal','Final Terminal') NOT NULL,
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
(1, 'Midterm Exam', 'Third Terminal', 3, '2023-10-01', '2023-10-10', 100, 40, '2023-2024', 'Midterm examination for Class 10', 'upcoming', '2020-09-09', 1, '2025-03-28 04:26:17', '2025-05-10 06:48:15', 0),
(2, 'First Terminal', 'Second Terminal', 8, NULL, NULL, 0, 0, '2025', '', 'upcoming', '2025-09-01', 1, '2025-05-03 14:17:10', '2025-05-10 06:46:56', 0),
(5, 'Third terminal', 'First Terminal', 3, NULL, NULL, 0, 0, '2020', '', '', '2020-02-02', 1, '2025-05-03 14:44:49', '2025-05-10 06:48:00', 0),
(12, 'second Terminal', 'Final Terminal', 3, NULL, NULL, 100, 40, '2026', '', 'upcoming', '1010-09-09', 1, '2025-05-10 06:41:16', '2025-05-10 06:48:23', 0);

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
(6, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-05 08:46:29', NULL, NULL, 'success', NULL),
(7, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-06 06:42:25', NULL, NULL, 'success', NULL),
(8, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-06 06:44:26', NULL, NULL, 'success', NULL),
(9, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-06 16:26:21', NULL, NULL, 'success', NULL),
(10, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-07 06:41:46', NULL, NULL, 'success', NULL),
(11, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-07 08:56:05', NULL, NULL, 'success', NULL),
(12, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-08 07:46:19', NULL, NULL, 'success', NULL),
(13, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-08 09:55:03', NULL, NULL, 'success', NULL),
(14, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 06:53:06', NULL, NULL, 'success', NULL),
(15, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 08:25:57', NULL, NULL, 'success', NULL),
(16, 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 08:35:16', NULL, NULL, 'success', NULL),
(17, 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 08:37:24', NULL, NULL, 'success', NULL),
(18, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 08:40:51', NULL, NULL, 'success', NULL),
(19, 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 09:55:35', NULL, NULL, 'success', NULL),
(20, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 09:58:54', NULL, NULL, 'success', NULL),
(21, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 10:00:42', NULL, NULL, 'success', NULL),
(22, 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 10:00:56', NULL, NULL, 'success', NULL),
(23, 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 10:10:34', NULL, NULL, 'success', NULL),
(24, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 10:10:54', NULL, NULL, 'success', NULL),
(25, 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 16:08:10', NULL, NULL, 'success', NULL),
(26, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 16:09:07', NULL, NULL, 'success', NULL),
(27, 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 16:17:34', NULL, NULL, 'success', NULL),
(28, 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 17:14:45', NULL, NULL, 'success', NULL),
(29, 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 17:18:46', NULL, NULL, 'success', NULL),
(30, 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 17:27:35', NULL, NULL, 'success', NULL),
(31, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 17:27:44', NULL, NULL, 'success', NULL),
(32, 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 20:05:07', NULL, NULL, 'success', NULL),
(33, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 20:15:24', NULL, NULL, 'success', NULL),
(34, 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 20:16:09', NULL, NULL, 'success', NULL),
(35, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 20:16:42', NULL, NULL, 'success', NULL),
(36, 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 20:27:45', NULL, NULL, 'success', NULL),
(37, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 20:33:27', NULL, NULL, 'success', NULL),
(38, 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 21:00:08', NULL, NULL, 'success', NULL),
(39, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 21:00:46', NULL, NULL, 'success', NULL),
(40, 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 21:02:00', NULL, NULL, 'success', NULL),
(41, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 21:04:14', NULL, NULL, 'success', NULL),
(42, 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 21:12:53', NULL, NULL, 'success', NULL),
(43, 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 21:26:48', NULL, NULL, 'success', NULL),
(44, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 21:27:13', NULL, NULL, 'success', NULL),
(45, 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 21:28:19', NULL, NULL, 'success', NULL),
(46, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 21:30:16', NULL, NULL, 'success', NULL),
(47, 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 21:43:14', NULL, NULL, 'success', NULL),
(48, 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 21:57:32', NULL, NULL, 'success', NULL),
(49, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-09 21:58:42', NULL, NULL, 'success', NULL),
(50, 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-10 10:50:47', NULL, NULL, 'success', NULL),
(51, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-10 10:56:08', NULL, NULL, 'success', NULL),
(52, 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-10 12:32:07', NULL, NULL, 'success', NULL),
(53, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-10 12:32:27', NULL, NULL, 'success', NULL),
(54, 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-10 12:37:05', NULL, NULL, 'success', NULL),
(55, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-10 12:41:14', NULL, NULL, 'success', NULL),
(56, 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-11 07:02:02', NULL, NULL, 'success', NULL),
(57, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-11 07:14:50', NULL, NULL, 'success', NULL),
(58, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-11 19:40:24', NULL, NULL, 'success', NULL);

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
(7, 8, NULL, 'Welcome to Result Management System', 'Thank you for registering. Your account has been created successfully.', 'system', NULL, 0, 'medium', NULL, '2025-05-09 02:49:30'),
(8, 9, NULL, 'Welcome to Result Management System', 'Thank you for registering. Your account has been created successfully.', 'system', NULL, 0, 'medium', NULL, '2025-05-09 02:50:10');

-- --------------------------------------------------------

--
-- Table structure for table `resultdetails`
--

CREATE TABLE `resultdetails` (
  `detail_id` int(11) NOT NULL,
  `result_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `marks_obtained` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_marks` decimal(10,2) NOT NULL DEFAULT 100.00,
  `percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `grade` varchar(5) DEFAULT NULL,
  `is_pass` tinyint(1) NOT NULL DEFAULT 0,
  `remarks` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `resultdetails`
--

INSERT INTO `resultdetails` (`detail_id`, `result_id`, `subject_id`, `marks_obtained`, `total_marks`, `percentage`, `grade`, `is_pass`, `remarks`) VALUES
(1, 5, 101, 79.00, 100.00, 79.00, 'B+', 1, NULL),
(2, 5, 102, 33.00, 100.00, 39.00, 'A', 1, NULL),
(3, 5, 103, 60.00, 100.00, 87.00, 'B', 1, NULL),
(4, 5, 104, 81.00, 100.00, 93.00, 'B', 1, NULL),
(5, 5, 105, 33.00, 100.00, 31.00, 'B+', 1, NULL),
(6, 5, 106, 38.00, 100.00, 96.00, 'C+', 0, NULL),
(7, 5, 107, 54.00, 100.00, 30.00, 'B', 1, NULL),
(8, 5, 108, 50.00, 100.00, 88.00, 'B', 1, NULL),
(9, 5, 120, 36.00, 100.00, 36.00, 'B', 1, NULL),
(10, 6, 101, 56.00, 100.00, 25.00, 'A', 1, NULL),
(11, 6, 102, 55.00, 100.00, 47.00, 'A', 1, NULL),
(12, 6, 103, 91.00, 100.00, 63.00, 'B+', 1, NULL),
(13, 6, 104, 82.00, 100.00, 72.00, 'A+', 1, NULL),
(14, 6, 105, 66.00, 100.00, 90.00, 'B', 1, NULL),
(15, 6, 106, 67.00, 100.00, 21.00, 'B+', 1, NULL),
(16, 6, 107, 71.00, 100.00, 95.00, 'D', 0, NULL),
(17, 6, 108, 41.00, 100.00, 36.00, 'B', 1, NULL),
(18, 6, 120, 41.00, 100.00, 80.00, 'A+', 1, NULL),
(19, 9, 101, 66.00, 100.00, 56.00, 'C', 1, NULL),
(20, 9, 102, 79.00, 100.00, 33.00, 'B+', 1, NULL),
(21, 9, 103, 33.00, 100.00, 98.00, 'A', 1, NULL),
(22, 9, 104, 39.00, 100.00, 23.00, 'D', 1, NULL),
(23, 9, 105, 68.00, 100.00, 52.00, 'A', 1, NULL),
(24, 9, 106, 30.00, 100.00, 80.00, 'A', 1, NULL),
(25, 9, 107, 43.00, 100.00, 64.00, 'A+', 1, NULL),
(26, 9, 108, 49.00, 100.00, 53.00, 'A+', 1, NULL),
(27, 9, 120, 40.00, 100.00, 50.00, 'B', 1, NULL),
(28, 10, 101, 46.00, 100.00, 91.00, 'C+', 0, NULL),
(29, 10, 102, 63.00, 100.00, 54.00, 'C+', 1, NULL),
(30, 10, 103, 55.00, 100.00, 30.00, 'C+', 1, NULL),
(31, 10, 104, 41.00, 100.00, 87.00, 'B+', 0, NULL),
(32, 10, 105, 46.00, 100.00, 39.00, 'B+', 1, NULL),
(33, 10, 106, 58.00, 100.00, 96.00, 'A', 1, NULL),
(34, 10, 107, 79.00, 100.00, 44.00, 'C', 1, NULL),
(35, 10, 108, 79.00, 100.00, 94.00, 'B+', 1, NULL),
(36, 10, 120, 64.00, 100.00, 92.00, 'B', 1, NULL),
(37, 11, 101, 66.00, 100.00, 23.00, 'C+', 1, NULL),
(38, 11, 102, 95.00, 100.00, 26.00, 'B+', 0, NULL),
(39, 11, 103, 21.00, 100.00, 77.00, 'B', 1, NULL),
(40, 11, 104, 22.00, 100.00, 23.00, 'B', 1, NULL),
(41, 11, 105, 78.00, 100.00, 68.00, 'B', 1, NULL),
(42, 11, 106, 63.00, 100.00, 85.00, 'A', 1, NULL),
(43, 11, 107, 33.00, 100.00, 41.00, 'B', 1, NULL),
(44, 11, 108, 45.00, 100.00, 76.00, 'A', 0, NULL),
(45, 11, 120, 37.00, 100.00, 84.00, 'C+', 1, NULL),
(46, 12, 101, 22.00, 100.00, 38.00, 'C+', 1, NULL),
(47, 12, 102, 73.00, 100.00, 58.00, 'B', 1, NULL),
(48, 12, 103, 87.00, 100.00, 91.00, 'A+', 1, NULL),
(49, 12, 104, 98.00, 100.00, 24.00, 'B', 1, NULL),
(50, 12, 105, 39.00, 100.00, 93.00, 'F', 1, NULL),
(51, 12, 106, 97.00, 100.00, 66.00, 'B+', 1, NULL),
(52, 12, 107, 80.00, 100.00, 77.00, 'B', 1, NULL),
(53, 12, 108, 91.00, 100.00, 70.00, 'C+', 1, NULL),
(54, 12, 120, 76.00, 100.00, 36.00, 'A+', 0, NULL),
(55, 13, 101, 91.00, 100.00, 91.00, 'A+', 1, NULL),
(56, 13, 102, 75.00, 100.00, 68.00, 'A+', 0, NULL),
(57, 13, 103, 59.00, 100.00, 35.00, 'A', 1, NULL),
(58, 13, 104, 61.00, 100.00, 87.00, 'B+', 0, NULL),
(59, 13, 105, 51.00, 100.00, 80.00, 'B+', 1, NULL),
(60, 13, 106, 20.00, 100.00, 25.00, 'B', 1, NULL),
(61, 13, 107, 23.00, 100.00, 60.00, 'B+', 1, NULL),
(62, 13, 108, 42.00, 100.00, 49.00, 'B', 1, NULL),
(63, 13, 120, 88.00, 100.00, 88.00, 'A', 1, NULL),
(64, 14, 101, 45.00, 100.00, 29.00, 'B', 0, NULL),
(65, 14, 102, 85.00, 100.00, 25.00, 'A+', 1, NULL),
(66, 14, 103, 65.00, 100.00, 29.00, 'A+', 0, NULL),
(67, 14, 104, 69.00, 100.00, 94.00, 'B+', 1, NULL),
(68, 14, 105, 43.00, 100.00, 21.00, 'B', 1, NULL),
(69, 14, 106, 88.00, 100.00, 71.00, 'C+', 1, NULL),
(70, 14, 107, 78.00, 100.00, 44.00, 'A', 0, NULL),
(71, 14, 108, 31.00, 100.00, 46.00, 'B+', 1, NULL),
(72, 14, 120, 27.00, 100.00, 77.00, 'C+', 1, NULL),
(73, 15, 101, 38.00, 100.00, 98.00, 'B+', 1, NULL),
(74, 15, 102, 55.00, 100.00, 59.00, 'B', 1, NULL),
(75, 15, 103, 86.00, 100.00, 45.00, 'B+', 1, NULL),
(76, 15, 104, 86.00, 100.00, 44.00, 'B+', 1, NULL),
(77, 15, 105, 32.00, 100.00, 62.00, 'B+', 0, NULL),
(78, 15, 106, 41.00, 100.00, 32.00, 'A+', 1, NULL),
(79, 15, 107, 40.00, 100.00, 71.00, 'B', 0, NULL),
(80, 15, 108, 28.00, 100.00, 57.00, 'B', 0, NULL),
(81, 15, 120, 72.00, 100.00, 31.00, 'B+', 1, NULL),
(82, 16, 101, 45.00, 100.00, 20.00, 'B+', 1, NULL),
(83, 16, 102, 33.00, 100.00, 20.00, 'B+', 1, NULL),
(84, 16, 103, 41.00, 100.00, 86.00, 'C+', 1, NULL),
(85, 16, 104, 85.00, 100.00, 72.00, 'B+', 1, NULL),
(86, 16, 105, 46.00, 100.00, 77.00, 'B+', 1, NULL),
(87, 16, 106, 49.00, 100.00, 71.00, 'C+', 1, NULL),
(88, 16, 107, 80.00, 100.00, 44.00, 'B', 1, NULL),
(89, 16, 108, 22.00, 100.00, 82.00, 'A', 1, NULL),
(90, 16, 120, 60.00, 100.00, 55.00, 'B', 1, NULL),
(91, 17, 101, 92.00, 100.00, 65.00, 'A', 1, NULL),
(92, 17, 102, 78.00, 100.00, 22.00, 'A+', 1, NULL),
(93, 17, 103, 89.00, 100.00, 24.00, 'B', 1, NULL),
(94, 17, 104, 23.00, 100.00, 43.00, 'A', 1, NULL),
(95, 17, 105, 53.00, 100.00, 86.00, 'A+', 0, NULL),
(96, 17, 106, 53.00, 100.00, 20.00, 'A', 1, NULL),
(97, 17, 107, 40.00, 100.00, 48.00, 'B', 1, NULL),
(98, 17, 108, 41.00, 100.00, 54.00, 'C+', 1, NULL),
(99, 17, 120, 78.00, 100.00, 60.00, 'A', 0, NULL),
(100, 23, 101, 92.00, 100.00, 92.00, 'A+', 1, NULL),
(101, 23, 102, 84.00, 100.00, 84.00, 'A', 1, NULL),
(102, 23, 103, 91.00, 100.00, 91.00, 'A+', 1, NULL),
(103, 23, 104, 67.00, 100.00, 67.00, 'B', 1, NULL),
(104, 23, 105, 68.00, 100.00, 68.00, 'B', 1, NULL),
(105, 27, 101, 92.00, 100.00, 92.00, 'A+', 1, NULL),
(106, 27, 102, 65.00, 100.00, 65.00, 'B', 1, NULL),
(107, 27, 103, 66.00, 100.00, 66.00, 'B', 1, NULL),
(108, 27, 104, 79.00, 100.00, 79.00, 'B+', 1, NULL),
(109, 27, 105, 71.00, 100.00, 71.00, 'B+', 1, NULL);

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `is_pass` tinyint(1) NOT NULL DEFAULT 0,
  `total_marks` decimal(10,2) NOT NULL DEFAULT 0.00,
  `marks_obtained` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `results`
--

INSERT INTO `results` (`result_id`, `student_id`, `subject_id`, `exam_id`, `theory_marks`, `practical_marks`, `credit_hours`, `grade`, `gpa`, `remarks`, `upload_id`, `batch_id`, `status`, `status_changed_at`, `status_changed_by`, `created_by`, `updated_by`, `created_at`, `updated_at`, `is_published`, `percentage`, `is_pass`, `total_marks`, `marks_obtained`) VALUES
(5, 'S001', '103', 2, 30.00, 35.00, 2.0, 'B', 3.00, NULL, NULL, NULL, 'pending', NULL, NULL, 1, 1, '2025-05-04 10:13:20', '2025-05-06 14:36:56', 0, 0.00, 0, 0.00, 0.00);

--
-- Triggers `results`
--
DELIMITER $$
CREATE TRIGGER `after_result_insert` AFTER INSERT ON `results` FOR EACH ROW BEGIN
  CALL update_student_performance(NEW.student_id, NEW.exam_id);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_result_update` AFTER UPDATE ON `results` FOR EACH ROW BEGIN
  CALL update_student_performance(NEW.student_id, NEW.exam_id);
END
$$
DELIMITER ;

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
(2, 'Batch Entry', 'Batch entry for subject ID 105', 'Published', 1, '2025-05-05 09:49:31', 2, 2, 0, NULL, 1, 2, 5, '2025-05-05 04:04:31', '2025-05-05 04:04:31'),
(3, 'Batch Entry', 'Batch entry for subject ID 101', 'Published', 1, '2025-05-05 09:50:10', 3, 3, 0, NULL, 1, 5, 3, '2025-05-05 04:05:10', '2025-05-05 04:05:10'),
(4, 'Batch Entry', 'Batch entry for subject ID 102', 'Published', 1, '2025-05-05 10:00:00', 1, 1, 0, NULL, 1, NULL, 5, '2025-05-05 04:15:00', '2025-05-11 01:56:13');

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
(5, 5, 'A', NULL, NULL, 1, '2025-05-03 11:16:39', '2025-05-03 11:16:39'),
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `section_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `user_id`, `roll_number`, `registration_number`, `class_id`, `batch_year`, `date_of_birth`, `gender`, `address`, `phone`, `parent_name`, `parent_phone`, `parent_email`, `is_active`, `created_at`, `updated_at`, `section_id`) VALUES
('S001', 1, '8', '78787878', 5, '2023', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-05-02 14:53:02', '2025-05-02 14:53:02', NULL),
('S002', 9, '111', '11111', 3, '2025', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-05-09 02:50:10', '2025-05-09 02:50:10', NULL);

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
(2, 'S001', 5, 136.25, 3.25, 4, 4, 1, NULL, '2025-05-05 02:59:23', '2025-05-05 04:05:10'),
(4, 'S001', 2, 68.50, 3.15, 2, 2, 1, NULL, '2025-05-05 04:04:31', '2025-05-07 02:51:35'),
(21, 'S002', 2, 81.40, 2.88, 5, 4, 2, NULL, '2025-05-09 15:13:25', '2025-05-11 01:37:48'),
(26, 'S002', 5, 107.50, 3.65, 4, 4, 1, NULL, '2025-05-09 15:42:48', '2025-05-11 01:38:00');

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
('101', 'COMP. ENGLISH', 'ENG101', 'Compulsory English', 80.00, 20.00, 32.00, 8.00, 4.0, 0, 1, '2025-03-28 04:26:17', '2025-05-11 04:37:45'),
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
(4, 8, '11', 'Master', 'Bca', NULL, NULL, NULL, 1, '2025-05-09 02:49:30', '2025-05-09 02:49:30');

-- --------------------------------------------------------

--
-- Table structure for table `teachersubjects`
--

CREATE TABLE `teachersubjects` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachersubjects`
--

INSERT INTO `teachersubjects` (`id`, `teacher_id`, `subject_id`, `class_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 101, 3, 1, '2025-05-11 02:36:11', '2025-05-11 02:36:11'),
(2, 4, 101, 3, 1, '2025-05-11 04:37:51', '2025-05-11 04:37:51');

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

--
-- Dumping data for table `teacher_activities`
--

INSERT INTO `teacher_activities` (`activity_id`, `teacher_id`, `activity_type`, `description`, `related_id`, `ip_address`, `timestamp`) VALUES
(1, 4, 'login', 'Logged into the system', NULL, NULL, '2025-05-09 08:37:24'),
(2, 4, '', 'Viewed dashboard', NULL, NULL, '2025-05-09 08:37:24'),
(3, 4, 'login', 'Logged into the system', NULL, NULL, '2025-05-09 08:38:50'),
(4, 4, '', 'Viewed dashboard', NULL, NULL, '2025-05-09 08:38:50'),
(5, 4, 'login', 'Logged into the system', NULL, NULL, '2025-05-09 08:39:24'),
(6, 4, '', 'Viewed dashboard', NULL, NULL, '2025-05-09 08:39:24'),
(7, 4, 'login', 'Logged into the system', NULL, NULL, '2025-05-09 09:55:35'),
(8, 4, '', 'Viewed dashboard', NULL, NULL, '2025-05-09 09:55:35'),
(9, 4, 'login', 'Logged into the system', NULL, NULL, '2025-05-09 09:57:42'),
(10, 4, '', 'Viewed dashboard', NULL, NULL, '2025-05-09 09:57:42'),
(11, 4, 'login', 'Logged into the system', NULL, NULL, '2025-05-09 09:57:50'),
(12, 4, '', 'Viewed dashboard', NULL, NULL, '2025-05-09 09:57:50'),
(13, 4, 'login', 'Logged into the system', NULL, NULL, '2025-05-09 10:10:34'),
(14, 4, '', 'Viewed dashboard', NULL, NULL, '2025-05-09 10:10:34'),
(15, 4, 'login', 'Logged into the system', NULL, NULL, '2025-05-09 10:10:40'),
(16, 4, '', 'Viewed dashboard', NULL, NULL, '2025-05-09 10:10:40'),
(17, 4, 'login', 'Logged into the system', NULL, NULL, '2025-05-09 16:08:10'),
(18, 4, '', 'Viewed dashboard', NULL, NULL, '2025-05-09 16:08:10'),
(19, 4, 'login', 'Accessed teacher dashboard', NULL, NULL, '2025-05-09 16:46:07'),
(20, 4, 'login', 'Accessed teacher dashboard', NULL, NULL, '2025-05-09 16:46:52'),
(21, 4, 'login', 'Accessed teacher dashboard', NULL, NULL, '2025-05-09 16:47:13'),
(22, 4, 'login', 'Accessed teacher dashboard', NULL, NULL, '2025-05-09 16:48:49'),
(23, 4, 'login', 'Accessed teacher dashboard', NULL, NULL, '2025-05-09 16:51:06'),
(24, 4, 'login', 'Accessed teacher dashboard', NULL, NULL, '2025-05-09 16:59:54'),
(25, 4, 'login', 'Accessed teacher dashboard', NULL, NULL, '2025-05-09 17:00:06'),
(26, 4, 'login', 'Accessed teacher dashboard', NULL, NULL, '2025-05-09 17:00:55'),
(27, 4, 'login', 'Accessed teacher dashboard', NULL, NULL, '2025-05-09 17:12:03'),
(28, 4, 'login', 'Accessed teacher dashboard', NULL, NULL, '2025-05-09 17:12:04'),
(29, 4, 'login', 'Accessed teacher dashboard', NULL, NULL, '2025-05-09 17:13:01'),
(30, 4, 'login', 'Accessed teacher dashboard', NULL, NULL, '2025-05-09 17:14:33'),
(31, 4, 'login', 'Accessed teacher dashboard', NULL, NULL, '2025-05-09 17:14:46'),
(32, 4, 'login', 'Accessed teacher dashboard', NULL, NULL, '2025-05-09 17:18:38');

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `full_name`, `email`, `role`, `status`, `profile_image`, `last_login`, `failed_login_attempts`, `last_failed_login`, `password_reset_token`, `password_reset_expires`, `email_verified`, `email_verification_token`, `created_at`, `updated_at`, `phone`, `address`) VALUES
(1, 'tilakyzypo', '$2y$10$pf7DlvBIobaFjaCK48N6a.uUMwHrZMiWdbOy/MGVn0pFpZHlXmOt6', 'Thaddeus Lowe', 'fycewo@mailinator.com', 'student', 'active', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, '2025-05-02 14:53:02', '2025-05-02 14:53:45', NULL, NULL),
(2, 'nesicisus', '$2y$10$1yooYWZ5MWy4FqOaxlKhA.OyjcFk.2cwnCVjNpyCBoA2PAinyP.c.', 'Martena Buckner', 'ryhenuz@mailinator.com', 'teacher', 'active', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, '2025-05-02 14:53:26', '2025-05-04 02:18:19', NULL, NULL),
(8, 'ashish', '$2y$10$mdZ4RlQlSZGfaVGhRPSZ8.FDc/GevaqmtyhKg440Sfpj0vQAGvV/i', 'Ashish', 'kamu@mailinator.com', 'teacher', 'active', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, '2025-05-09 02:49:30', '2025-05-09 02:49:30', NULL, NULL),
(9, 'ayush', '$2y$10$P1NimgADbWGRAl9RVEjSnuM79cVS/BHZNbFcgh/tU5hxPIfhIxd2G', 'aysuh', 'waqujoka@mailinator.com', 'student', 'active', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, '2025-05-09 02:50:10', '2025-05-09 04:15:48', NULL, NULL);

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
-- Indexes for table `classsubjects`
--
ALTER TABLE `classsubjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `class_subject_unique` (`class_id`,`subject_id`,`academic_year`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `idx_classsubjects_academic_year` (`academic_year`),
  ADD KEY `idx_classsubjects_mandatory` (`is_mandatory`);

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
-- Indexes for table `resultdetails`
--
ALTER TABLE `resultdetails`
  ADD PRIMARY KEY (`detail_id`),
  ADD UNIQUE KEY `unique_result_subject` (`result_id`,`subject_id`),
  ADD KEY `result_id` (`result_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `idx_resultdetails_result` (`result_id`),
  ADD KEY `idx_resultdetails_subject` (`subject_id`);

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
  ADD KEY `idx_results_batch_id` (`batch_id`),
  ADD KEY `idx_results_student` (`student_id`),
  ADD KEY `idx_results_exam` (`exam_id`),
  ADD KEY `idx_results_published` (`is_published`);

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
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `class_id` (`class_id`);

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
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `batch_operations`
--
ALTER TABLE `batch_operations`
  MODIFY `batch_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `class_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `classsubjects`
--
ALTER TABLE `classsubjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `exams`
--
ALTER TABLE `exams`
  MODIFY `exam_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `grading_system`
--
ALTER TABLE `grading_system`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `loginlogs`
--
ALTER TABLE `loginlogs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `resultdetails`
--
ALTER TABLE `resultdetails`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- AUTO_INCREMENT for table `results`
--
ALTER TABLE `results`
  MODIFY `result_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `result_history`
--
ALTER TABLE `result_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `result_uploads`
--
ALTER TABLE `result_uploads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  MODIFY `performance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `teacher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `teachersubjects`
--
ALTER TABLE `teachersubjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `teacher_activities`
--
ALTER TABLE `teacher_activities`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

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
-- Constraints for table `classsubjects`
--
ALTER TABLE `classsubjects`
  ADD CONSTRAINT `classsubjects_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `classsubjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE;

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
