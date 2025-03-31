-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 31, 2025 at 09:01 AM
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
  `class_id` int(11) NOT NULL,
  `class_name` varchar(100) NOT NULL,
  `section` varchar(10) DEFAULT NULL,
  `academic_year` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`class_id`, `class_name`, `section`, `academic_year`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Class 10', 'A', '2023-2024', 'Default class for testing', '2025-03-28 04:26:17', '2025-03-28 04:26:17');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exams`
--

INSERT INTO `exams` (`exam_id`, `exam_name`, `exam_type`, `class_id`, `start_date`, `end_date`, `total_marks`, `passing_marks`, `academic_year`, `description`, `status`, `exam_date`, `created_at`, `updated_at`) VALUES
(1, 'Midterm Exam', 'midterm', 1, '2023-10-01', '2023-10-10', 100, 40, '2023-2024', 'Midterm examination for Class 10', 'upcoming', NULL, '2025-03-28 04:26:17', '2025-03-28 04:26:17');

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
(1, 1, 1, 'System Update', 'The system has been updated to version 2.0. Please review the changes.', 'system', NULL, 0, 'high', NULL, '2025-03-28 04:34:06'),
(2, 3, 1, 'New Exam Scheduled', 'Midterm exams for Class 10 have been scheduled from 2023-10-01 to 2023-10-10.', 'exam', 1, 0, 'medium', NULL, '2025-03-28 04:34:06'),
(3, 2, 3, 'Results Published', 'Your results for Midterm Exam have been published. You scored 85 in theory and 90 in practical.', 'result', 1, 0, 'high', NULL, '2025-03-28 04:34:06'),
(4, 1, 1, 'Maintenance Notice', 'System will be down for maintenance on 2023-11-15 from 2:00 AM to 4:00 AM.', 'announcement', NULL, 0, 'medium', '2023-11-15 04:00:00', '2025-03-28 04:34:07'),
(5, 2, NULL, 'Profile Update Reminder', 'Please complete your profile information for better experience.', 'system', NULL, 0, 'low', NULL, '2025-03-28 04:34:07'),
(6, 1, 1, 'System Update', 'The system has been updated to version 2.0.', 'system', NULL, 0, 'high', NULL, '2025-03-28 04:36:40'),
(7, 3, 1, 'New Exam Scheduled', 'Midterm exams for Class 10 have been scheduled.', 'exam', 1, 0, 'medium', NULL, '2025-03-28 04:36:40'),
(8, 2, 3, 'Results Published', 'Your results for Midterm Exam are available.', 'result', 1, 0, 'high', NULL, '2025-03-28 04:36:40'),
(9, 1, 1, 'Maintenance Notice', 'System maintenance scheduled.', 'announcement', NULL, 0, 'medium', '2023-11-15 04:00:00', '2025-03-28 04:36:40'),
(10, 2, NULL, 'Profile Reminder', 'Please complete your profile.', 'system', NULL, 0, 'low', NULL, '2025-03-28 04:36:40'),
(11, 10, NULL, 'Welcome to Result Management System', 'Thank you for registering. Your account has been created successfully.', 'system', NULL, 0, 'medium', NULL, '2025-03-30 01:37:37'),
(12, 11, NULL, 'Welcome to Result Management System', 'Thank you for registering. Your account has been created successfully.', 'system', NULL, 0, 'medium', NULL, '2025-03-30 01:38:18');

-- --------------------------------------------------------

--
-- Table structure for table `results`
--

CREATE TABLE `results` (
  `result_id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `subject_id` varchar(20) NOT NULL,
  `theory_marks` int(11) DEFAULT NULL CHECK (`theory_marks` between 0 and 100),
  `practical_marks` int(11) DEFAULT NULL CHECK (`practical_marks` between 0 and 100),
  `grade` varchar(2) DEFAULT NULL,
  `gpa` decimal(3,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `results`
--

INSERT INTO `results` (`result_id`, `student_id`, `exam_id`, `subject_id`, `theory_marks`, `practical_marks`, `grade`, `gpa`, `created_at`, `updated_at`) VALUES
(1, 'S001', 1, '101', 85, 90, 'A+', 4.00, '2025-03-28 04:26:17', '2025-03-28 04:26:17');

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `user_id`, `roll_number`, `registration_number`, `class_id`, `batch_year`, `date_of_birth`, `gender`, `address`, `phone`, `parent_name`, `parent_phone`, `created_at`, `updated_at`) VALUES
('S001', 2, 'R001', 'REG001', 1, '2023', '2005-01-01', 'male', '123 Street, City', '1234567890', 'Parent Name', '0987654321', '2025-03-28 04:26:17', '2025-03-28 04:26:17'),
('S010', 10, '90', '992', 1, '2025', NULL, NULL, NULL, NULL, NULL, NULL, '2025-03-30 01:37:37', '2025-03-30 01:37:37');

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
(1, 'S001', 1, 87.50, 3.80, 5, 5, 1, 'Excellent performance', '2025-03-30 02:35:48', '2025-03-30 02:35:48'),
(2, 'S010', 1, 75.20, 3.20, 5, 5, 2, 'Good performance', '2025-03-30 02:35:48', '2025-03-30 02:35:48');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `subject_id` varchar(20) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`subject_id`, `subject_name`, `description`, `created_at`, `updated_at`) VALUES
('101', 'COMP. ENGLISH', NULL, '2025-03-28 04:26:17', '2025-03-28 04:26:17'),
('102', 'COMP. NEPALI', NULL, '2025-03-28 04:26:17', '2025-03-28 04:26:17'),
('103', 'COMP. MATHEMATICS', NULL, '2025-03-28 04:26:17', '2025-03-28 04:26:17'),
('104', 'COMP. SCIENCE', NULL, '2025-03-28 04:26:17', '2025-03-28 04:26:17'),
('105', 'COMP. SOCIAL STUDIES', NULL, '2025-03-28 04:26:17', '2025-03-28 04:26:17'),
('106', 'COMP. HEALTH, POP & ENV EDU', NULL, '2025-03-28 04:26:17', '2025-03-28 04:26:17'),
('107', 'OPT.I ECONOMICS', NULL, '2025-03-28 04:26:17', '2025-03-28 04:26:17'),
('108', 'OPT.II OFFICE MGMT & ACCOUNT', NULL, '2025-03-28 04:26:17', '2025-03-28 04:26:17');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`teacher_id`, `user_id`, `employee_id`, `qualification`, `department`, `joining_date`, `phone`, `address`, `created_at`, `updated_at`) VALUES
(1, 3, 'T001', 'M.Sc. in Mathematics', 'Mathematics', '2020-01-01', '1234567890', '456 Street, City', '2025-03-28 04:26:17', '2025-03-28 04:26:17'),
(2, 11, 'Qui magni laboriosam', 'Id dolore et rerum r', 'Science', NULL, NULL, NULL, '2025-03-30 01:38:18', '2025-03-30 01:38:18');

-- --------------------------------------------------------

--
-- Table structure for table `teachersubjects`
--

CREATE TABLE `teachersubjects` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `subject_id` varchar(20) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachersubjects`
--

INSERT INTO `teachersubjects` (`id`, `teacher_id`, `subject_id`, `academic_year`, `created_at`) VALUES
(1, 1, '101', '2023-2024', '2025-03-28 04:26:17');

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
  `status` enum('active','inactive') DEFAULT 'active',
  `profile_image` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `full_name`, `email`, `role`, `status`, `profile_image`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin123', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin123@example.com', 'admin', 'active', NULL, NULL, '2025-03-28 04:26:17', '2025-03-28 04:26:17'),
(2, 'student1', '$2y$10$8zUUpfvHvJqMnJ4gJk.Cj.Z/BvWQS1zNFW9CMhbRvDpRRUL2jEjGK', 'John Doe', 'student1@example.com', 'student', 'active', NULL, NULL, '2025-03-28 04:26:17', '2025-03-28 04:26:17'),
(3, 'teacher1', '$2y$10$8zUUpfvHvJqMnJ4gJk.Cj.Z/BvWQS1zNFW9CMhbRvDpRRUL2jEjGK', 'Jane Smith', 'teacher1@example.com', 'teacher', 'active', NULL, NULL, '2025-03-28 04:26:17', '2025-03-28 04:26:17'),
(10, 'STUDENT', '$2y$10$cZc6SEgqSQaFeCB1MpHvw.uM49nf5KnvyQp.AlJCDVfcRndWNOpLW', 'Student', 'ruxoqyj@mailinator.com', 'student', 'active', NULL, '2025-03-30 16:06:34', '2025-03-30 01:37:37', '2025-03-30 10:21:34'),
(11, 'Teacher', '$2y$10$g08FUlD2kd5Q6xfduIfOaO4xoC2mQcwmZRl2YsCecEJYdjhDtWIVG', 'Teacher', 'hoqazul@mailinator.com', 'teacher', 'active', NULL, '2025-03-30 07:25:42', '2025-03-30 01:38:18', '2025-03-30 01:40:42'),
(12, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@example.com', 'admin', 'active', NULL, '2025-03-30 16:12:58', '2025-03-30 01:46:29', '2025-03-30 10:27:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`class_id`);

--
-- Indexes for table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`exam_id`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `idx_notifications_user` (`user_id`,`is_read`),
  ADD KEY `idx_notifications_created` (`created_at`);

--
-- Indexes for table `results`
--
ALTER TABLE `results`
  ADD PRIMARY KEY (`result_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `exam_id` (`exam_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `roll_number` (`roll_number`),
  ADD UNIQUE KEY `registration_number` (`registration_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `student_performance`
--
ALTER TABLE `student_performance`
  ADD PRIMARY KEY (`performance_id`),
  ADD UNIQUE KEY `student_exam_unique` (`student_id`,`exam_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `exam_id` (`exam_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`subject_id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`teacher_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `teachersubjects`
--
ALTER TABLE `teachersubjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `teacher_id` (`teacher_id`,`subject_id`,`academic_year`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `class_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `exams`
--
ALTER TABLE `exams`
  MODIFY `exam_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `results`
--
ALTER TABLE `results`
  MODIFY `result_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `student_performance`
--
ALTER TABLE `student_performance`
  MODIFY `performance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `teacher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `teachersubjects`
--
ALTER TABLE `teachersubjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `exams`
--
ALTER TABLE `exams`
  ADD CONSTRAINT `exams_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`) ON DELETE SET NULL;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
