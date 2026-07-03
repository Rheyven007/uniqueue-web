-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 05, 2026 at 07:38 AM
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
-- Database: `uniqueue`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `office_id` int(11) DEFAULT NULL,
  `is_super_admin` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `colleges`
--

CREATE TABLE `colleges` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `abbreviation` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `colleges`
--

INSERT INTO `colleges` (`id`, `name`, `abbreviation`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'College of Informatics and Computing Sciences', 'CICS', 1, '2026-06-05 05:37:28', '2026-06-05 05:37:28'),
(2, 'College of Accountancy, Business, Economics and International Hospitality Management', 'CABEIHM', 1, '2026-06-05 05:37:28', '2026-06-05 05:37:28'),
(3, 'College of Arts and Sciences', 'CAS', 1, '2026-06-05 05:37:28', '2026-06-05 05:37:28'),
(4, 'College of Teacher Education', 'CTE', 1, '2026-06-05 05:37:28', '2026-06-05 05:37:28'),
(5, 'College of Nursing and Allied Health Sciences', 'CONAHS', 1, '2026-06-05 05:37:28', '2026-06-05 05:37:28');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `office_id` int(11) NOT NULL,
  `daily_capacity` int(11) DEFAULT 50,
  `processing_time` int(11) DEFAULT NULL COMMENT 'Minutes',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_requirements`
--

CREATE TABLE `document_requirements` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `requirement` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedbacks`
--

CREATE TABLE `feedbacks` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `type` enum('3_away','called') NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `processes`
--

CREATE TABLE `processes` (
  `id` int(11) NOT NULL,
  `office_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `estimated_time` int(11) DEFAULT 10 COMMENT 'Minutes'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
-- --------------------------------------------------------

--
-- Table structure for table `offices`
--

CREATE TABLE `offices` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `office_configs`
--

CREATE TABLE `office_configs` (
  `id` int(11) NOT NULL,
  `office_id` int(11) NOT NULL,
  `start_time` time DEFAULT '08:00:00',
  `end_time` time DEFAULT '17:00:00',
  `daily_capacity` int(11) DEFAULT 100,
  `walkin_enabled` tinyint(1) DEFAULT 1,
  `appointment_enabled` tinyint(1) DEFAULT 1,
  `priority_enabled` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `id` int(11) NOT NULL,
  `college_id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `abbreviation` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`id`, `college_id`, `name`, `abbreviation`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Bachelor of Science in Information Technology', 'BSIT', 1, '2026-06-05 05:37:28', '2026-06-05 05:37:28'),
(2, 2, 'Bachelor of Science in Accountancy', 'BS Accountancy', 1, '2026-06-05 05:37:28', '2026-06-05 05:37:28'),
(3, 2, 'Bachelor of Science in Management Accounting', 'BS MA', 1, '2026-06-05 05:37:28', '2026-06-05 05:37:28'),
(4, 2, 'Bachelor of Science in Business Administration major in Financial Management', 'BS BA FM', 1, '2026-06-05 05:37:28', '2026-06-05 05:37:28'),
(5, 2, 'Bachelor of Science in Business Administration major in Marketing Management', 'BS BA MM', 1, '2026-06-05 05:37:28', '2026-06-05 05:37:28'),
(6, 2, 'Bachelor of Science in Business Administration major in Human Resource Management', 'BS BA HRM', 1, '2026-06-05 05:37:28', '2026-06-05 05:37:28'),
(7, 2, 'Bachelor of Science in Hospitality Management', 'BS HM', 1, '2026-06-05 05:37:28', '2026-06-05 05:37:28'),
(8, 2, 'Bachelor of Science in Tourism Management', 'BS TM', 1, '2026-06-05 05:37:28', '2026-06-05 05:37:28'),
(9, 3, 'Bachelor of Arts in Communication', 'BA Comm', 1, '2026-06-05 05:37:28', '2026-06-05 05:37:28'),
(10, 3, 'Bachelor of Science in Criminology', 'BS Crim', 1, '2026-06-05 05:37:28', '2026-06-05 05:37:28'),
(11, 3, 'Bachelor of Science in Psychology', 'BS Psy', 1, '2026-06-05 05:37:28', '2026-06-05 05:37:28'),
(12, 3, 'Bachelor of Science in Fisheries and Aquatic Sciences', 'BS FAS', 1, '2026-06-05 05:37:28', '2026-06-05 05:37:28'),
(13, 4, 'Bachelor of Elementary Education', 'BEEd', 1, '2026-06-05 05:37:28', '2026-06-05 05:37:28'),
(14, 4, 'Bachelor of Secondary Education major in English', 'BSEd - English', 1, '2026-06-05 05:37:28', '2026-06-05 05:37:28'),
(15, 4, 'Bachelor of Secondary Education major in Mathematics', 'BSEd - Mathematics', 1, '2026-06-05 05:37:28', '2026-06-05 05:37:28'),
(16, 4, 'Bachelor of Secondary Education major in Biological Science', 'BSEd - Biological Science', 1, '2026-06-05 05:37:28', '2026-06-05 05:37:28'),
(17, 5, 'Bachelor of Science in Nursing', 'BS Nursing', 1, '2026-06-05 05:37:28', '2026-06-05 05:37:28');

-- --------------------------------------------------------

--
-- Table structure for table `queue_tickets`
--

CREATE TABLE `queue_tickets` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `office_id` int(11) NOT NULL,
  `window_id` int(11) DEFAULT NULL,
  `queue_number` varchar(20) NOT NULL,
  `type` enum('walkin','appointment') DEFAULT 'walkin',
  `status` enum('waiting','called','in_progress','done','cancelled') DEFAULT 'waiting',
  `priority` tinyint(1) DEFAULT 0,
  `priority_reason` text DEFAULT NULL,
  `appointment_date` date DEFAULT NULL,
  `notified_at_3` datetime DEFAULT NULL,
  `notified_at_called` datetime DEFAULT NULL,
  `joined_at` datetime DEFAULT current_timestamp(),
  `called_at` datetime DEFAULT NULL,
  `done_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `queue_ticket_document`
--

CREATE TABLE `queue_ticket_document` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `sr_code` varchar(20) NOT NULL,
  `college_id` int(11) DEFAULT NULL,
  `program_id` int(11) DEFAULT NULL,
  `year_level` int(11) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `windows`
--

CREATE TABLE `windows` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `office_id` int(11) NOT NULL,
  `status` enum('open','closed') DEFAULT 'closed',
  `speed` enum('fast','normal','slow') DEFAULT 'normal'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `window_document`
--

CREATE TABLE `window_document` (
  `id` int(11) NOT NULL,
  `window_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `office_id` (`office_id`);

--
-- Indexes for table `colleges`
--
ALTER TABLE `colleges`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `office_id` (`office_id`);

--
-- Indexes for table `document_requirements`
--
ALTER TABLE `document_requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`);

--
-- Indexes for table `feedbacks`
--
ALTER TABLE `feedbacks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_id` (`ticket_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `ticket_id` (`ticket_id`);

--
-- Indexes for table `offices`
--
ALTER TABLE `offices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `office_configs`
--
ALTER TABLE `office_configs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `office_id` (`office_id`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `college_id` (`college_id`);

--
-- Indexes for table `queue_tickets`
--
ALTER TABLE `queue_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `office_id` (`office_id`),
  ADD KEY `window_id` (`window_id`);

--
-- Indexes for table `queue_ticket_document`
--
ALTER TABLE `queue_ticket_document`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `document_id` (`document_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sr_code` (`sr_code`),
  ADD KEY `college_id` (`college_id`),
  ADD KEY `program_id` (`program_id`);

--
-- Indexes for table `windows`
--
ALTER TABLE `windows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `office_id` (`office_id`);

--
-- Indexes for table `window_document`
--
ALTER TABLE `window_document`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `window_id` (`window_id`,`document_id`),
  ADD KEY `document_id` (`document_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `processes`
--
ALTER TABLE `processes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `colleges`
--
ALTER TABLE `colleges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_requirements`
--
ALTER TABLE `document_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedbacks`
--
ALTER TABLE `feedbacks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offices`
--
ALTER TABLE `offices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `office_configs`
--
ALTER TABLE `office_configs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `queue_tickets`
--
ALTER TABLE `queue_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `queue_ticket_document`
--
ALTER TABLE `queue_ticket_document`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `windows`
--
ALTER TABLE `windows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `window_document`
--
ALTER TABLE `window_document`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD CONSTRAINT `admin_users_ibfk_1` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `processes`
--
ALTER TABLE `processes`
  ADD CONSTRAINT `processes_ibfk_1` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_requirements`
--
ALTER TABLE `document_requirements`
  ADD CONSTRAINT `document_requirements_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedbacks`
--
ALTER TABLE `feedbacks`
  ADD CONSTRAINT `feedbacks_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `queue_tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedbacks_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`ticket_id`) REFERENCES `queue_tickets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `office_configs`
--
ALTER TABLE `office_configs`
  ADD CONSTRAINT `office_configs_ibfk_1` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `programs`
--
ALTER TABLE `programs`
  ADD CONSTRAINT `programs_ibfk_1` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `queue_tickets`
--
ALTER TABLE `queue_tickets`
  ADD CONSTRAINT `queue_tickets_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `queue_tickets_ibfk_2` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `queue_tickets_ibfk_3` FOREIGN KEY (`window_id`) REFERENCES `windows` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `queue_ticket_document`
--
ALTER TABLE `queue_ticket_document`
  ADD CONSTRAINT `queue_ticket_document_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `queue_tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `queue_ticket_document_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `windows`
--
ALTER TABLE `windows`
  ADD CONSTRAINT `windows_ibfk_1` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `window_document`
--
ALTER TABLE `window_document`
  ADD CONSTRAINT `window_document_ibfk_1` FOREIGN KEY (`window_id`) REFERENCES `windows` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `window_document_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
