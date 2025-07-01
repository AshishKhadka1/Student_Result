-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 01, 2025 at 02:13 PM
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
-- Triggers `exams`
--
DELIMITER $$
CREATE TRIGGER `sync_result_publication_on_exam_update` AFTER UPDATE ON `exams` FOR EACH ROW BEGIN
    IF NEW.results_published != OLD.results_published THEN
        UPDATE results 
        SET is_published = NEW.results_published,
            updated_at = NOW()
        WHERE exam_id = NEW.exam_id;
    END IF;
END
$$
DELIMITER ;

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
-- Triggers `results`
--
DELIMITER $$
CREATE TRIGGER `sync_result_publication_on_insert` AFTER INSERT ON `results` FOR EACH ROW BEGIN
    DECLARE exam_published TINYINT DEFAULT 0;
    
    SELECT results_published INTO exam_published 
    FROM exams 
    WHERE exam_id = NEW.exam_id;
    
    IF exam_published = 1 THEN
        UPDATE results 
        SET is_published = 1,
            updated_at = NOW()
        WHERE result_id = NEW.result_id;
    END IF;
END
$$
DELIMITER ;

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
('S001', 83, '1', 'S001', NULL, '2002', '2002-02-02', 'male', 'mornag', NULL, 'Gita khadka', '9842239607', 'gita@mailinator.com', 1, '2025-05-16 15:23:03', '2025-06-26 15:12:35', NULL);

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
('101', 'COMP. ENGLISH', 'ENG101', 'Compulsory English', 75.00, 25.00, 30.00, 10.00, 4.0, 0, 1, '2025-03-28 04:26:17', '2025-07-01 06:59:06'),
('102', 'COMP. NEPALI', 'NEP102', 'Compulsory Nepali', 75.00, 25.00, 30.00, 10.00, 4.0, 0, 1, '2025-03-28 04:26:17', '2025-07-01 12:02:00'),
('103', 'COMP. MATHEMATICS', 'MATH103', 'Compulsory Mathematics', 100.00, 0.00, 40.00, 0.00, 4.0, 0, 1, '2025-03-28 04:26:17', '2025-07-01 12:01:10'),
('104', 'COMP. SCIENCE', 'SCI104', 'Compulsory Science', 75.00, 25.00, 30.00, 10.00, 4.0, 0, 1, '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
('105', 'COMP. SOCIAL STUDIES', 'SOC105', 'Compulsory Social Studies', 75.00, 25.00, 30.00, 10.00, 4.0, 0, 1, '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
('106', 'COMP. HEALTH, POP & ENV EDU', 'HPE106', 'Compulsory Health, Population & Environment Education', 75.00, 25.00, 30.00, 10.00, 4.0, 0, 1, '2025-03-28 04:26:17', '2025-05-02 08:30:00'),
('107', 'OPT.I ECONOMICS', 'ECO107', 'Optional Economics', 75.00, 25.00, 30.00, 10.00, 4.0, 1, 1, '2025-03-28 04:26:17', '2025-07-01 12:02:18'),
('120', 'OPT.II MATHEMATICS', NULL, 'Optimal Math', 100.00, 0.00, 40.00, 0.00, 4.0, 0, 1, '2025-05-03 14:14:33', '2025-07-01 07:00:58');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `teacher_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `qualification` varchar(100) DEFAULT NULL,
  `experience` varchar(255) DEFAULT NULL,
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

INSERT INTO `teachers` (`teacher_id`, `user_id`, `employee_id`, `qualification`, `experience`, `department`, `joining_date`, `phone`, `address`, `is_active`, `created_at`, `updated_at`) VALUES
(27, 87, '87', 'Bachelor', '2', NULL, '1978-10-10', NULL, 'Sint molestias autem', 1, '2025-05-19 03:11:44', '2025-05-19 03:11:44');

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
  `gender` enum('male','female','other') DEFAULT NULL,
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

INSERT INTO `users` (`user_id`, `username`, `password`, `full_name`, `email`, `gender`, `role`, `status`, `profile_image`, `last_login`, `failed_login_attempts`, `last_failed_login`, `password_reset_token`, `password_reset_expires`, `email_verified`, `email_verification_token`, `created_at`, `updated_at`, `phone`, `address`) VALUES
(1, 'admin', '$2y$10$HEpg59r5H32OQeDScuRh.eJxniPZbKV0Wzlh.ij/Pg4mZn6zvL7oi', '', 'admin@example.com', NULL, 'admin', 'active', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, '2025-05-15 03:41:06', '2025-05-15 03:41:06', NULL, NULL),
(83, 'ayushkhadka', '$2y$10$GOsPhoxvspif75MEku6faO1tgi00LtYyN7cEqZgYyQbKEvvbkDCeK', 'Ayush Khadka', 'ayush@mailinator.com', NULL, 'student', 'active', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, '2025-05-16 15:23:03', '2025-06-23 11:26:16', '9846837536', 'biratchowk'),
(87, 'ashish', '$2y$10$.CddIPQckYn.45cOJVeGKuYru2v3wTyfWuMUCUUM5ykt7hf6/dwfu', 'Ashish Khadka', 'ashish@mailinator.com', NULL, 'teacher', 'active', 'uploads/profile_images/teacher_87_1751077009_Miyamoto Musashi.jpeg', NULL, 0, NULL, NULL, NULL, 0, NULL, '2025-05-19 03:11:44', '2025-06-28 02:16:49', '+1 (392) 208-4575', NULL);

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
  ADD KEY `idx_exams_active` (`is_active`),
  ADD KEY `idx_exams_results_published` (`results_published`);

--
-- Indexes for table `grading_system`
--
ALTER TABLE `grading_system`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `grade` (`grade`);

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
  ADD KEY `idx_results_published` (`is_published`),
  ADD KEY `idx_results_published_exam` (`exam_id`,`is_published`);

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
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `batch_operations`
--
ALTER TABLE `batch_operations`
  MODIFY `batch_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `class_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

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
-- AUTO_INCREMENT for table `resultdetails`
--
ALTER TABLE `resultdetails`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=160;

--
-- AUTO_INCREMENT for table `results`
--
ALTER TABLE `results`
  MODIFY `result_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=130;

--
-- AUTO_INCREMENT for table `result_uploads`
--
ALTER TABLE `result_uploads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `section_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `student_performance`
--
ALTER TABLE `student_performance`
  MODIFY `performance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `teacher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `teachersubjects`
--
ALTER TABLE `teachersubjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

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
