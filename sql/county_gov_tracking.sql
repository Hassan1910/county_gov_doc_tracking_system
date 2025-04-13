-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 13, 2025 at 09:21 PM
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
-- Database: `county_gov_tracking`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 3, 'user_login', 'User logged in successfully', '::1', '2025-04-13 09:21:47'),
(2, 3, 'user_logout', 'User logged out', '::1', '2025-04-13 09:26:49'),
(3, 3, 'user_login', 'User logged in successfully', '::1', '2025-04-13 09:29:31'),
(4, 3, 'register_client', 'Registered new client: Hassan Adan (hassan@gmail.com)', '::1', '2025-04-13 11:17:28'),
(5, 3, 'user_logout', 'User logged out', '::1', '2025-04-13 11:17:38'),
(6, 4, 'user_login', 'User logged in successfully', '::1', '2025-04-13 11:18:04'),
(7, 4, 'user_logout', 'User logged out', '::1', '2025-04-13 11:19:37'),
(8, 4, 'user_login', 'User logged in successfully', '::1', '2025-04-13 11:20:01'),
(9, 4, 'user_logout', 'User logged out', '::1', '2025-04-13 11:34:55'),
(10, 4, 'user_login', 'User logged in successfully', '::1', '2025-04-13 11:35:21'),
(11, 3, 'password_reset', 'Password reset for user ID: 3', '::1', '2025-04-13 12:22:22'),
(12, 3, 'user_login', 'User logged in successfully', '::1', '2025-04-13 12:22:36'),
(13, 3, 'document_upload', 'Uploaded document: test', '::1', '2025-04-13 12:43:52'),
(14, 3, 'document_approved', 'Document ID: DOC-2025-22503', '::1', '2025-04-13 12:48:26'),
(15, 3, 'document_approved', 'Document ID: DOC-2025-22503', '::1', '2025-04-13 12:49:51'),
(16, 3, 'document_upload', 'Uploaded document: terst2 (DOC-2025-61988)', '::1', '2025-04-13 12:50:46'),
(17, 3, 'document_approved', 'Document ID: DOC-2025-61988', '::1', '2025-04-13 12:51:19'),
(18, 3, 'document_upload', 'Uploaded document: tes3 (DOC-2025-63639)', '::1', '2025-04-13 13:13:02'),
(19, 3, 'document_upload', 'Uploaded document: test4 (DOC-2025-20687)', '::1', '2025-04-13 13:42:06'),
(20, 3, 'document_moved', 'Document ID: DOC-2025-61988 moved from HR to IT', '::1', '2025-04-13 14:29:24'),
(21, 3, 'document_moved', 'Document ID: DOC-2025-61988 moved from IT to Legal', '::1', '2025-04-13 14:32:11'),
(22, 3, 'document_moved', 'Document ID: DOC-2025-61988 moved from Legal to IT', '::1', '2025-04-13 14:34:02'),
(23, 3, 'document_moved', 'Document ID: DOC-2025-61988 moved from IT to Procurement', '::1', '2025-04-13 14:52:19'),
(24, 3, 'update_final_destination', 'Changed final destination of document ID: DOC-2025-20687 from  to Legal', '::1', '2025-04-13 16:37:00'),
(25, 3, 'update_final_destination', 'Changed final destination of document ID: DOC-2025-61988 from  to HR', '::1', '2025-04-13 16:54:40'),
(26, 3, 'update_final_destination', 'Changed final destination of document ID: DOC-2025-61988 from HR to HR', '::1', '2025-04-13 16:55:11'),
(27, 3, 'update_final_destination', 'Changed final destination of document ID: DOC-2025-61988 from HR to Administration', '::1', '2025-04-13 16:55:38'),
(28, 3, 'update_final_destination', 'Changed final destination of document ID: DOC-2025-61988 from Administration to IT', '::1', '2025-04-13 16:56:32'),
(29, 3, 'document_approved', 'Document ID: DOC-2025-61988', '::1', '2025-04-13 17:00:56'),
(30, 3, 'document_marked_done', 'Document ID: DOC-2025-61988 marked as COMPLETE with note: you can dowwnload or visit office to pick your documment', '::1', '2025-04-13 17:15:15'),
(31, 3, 'document_marked_done', 'Document ID: DOC-2025-61988 marked as COMPLETE with note: you can dowwnload or visit office to pick your documment', '::1', '2025-04-13 17:16:14'),
(32, 3, 'document_marked_done', 'Document ID: DOC-2025-61988 marked as COMPLETE with note: you can dowwnload or visit office to pick your documment', '::1', '2025-04-13 17:19:28'),
(33, 3, 'document_moved', 'Document ID: DOC-2025-22503 moved from HR to Administration', '::1', '2025-04-13 17:42:40');

-- --------------------------------------------------------

--
-- Table structure for table `client_notifications`
--

CREATE TABLE `client_notifications` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_notifications`
--

INSERT INTO `client_notifications` (`id`, `client_id`, `document_id`, `message`, `is_read`, `created_at`) VALUES
(1, 3, 1, 'Your document \"test\" has been approved.', 0, '2025-04-13 16:02:39'),
(2, 3, 2, 'Your document \"terst2\" has been approved.', 0, '2025-04-13 16:02:39'),
(3, 4, 3, 'Your document \"tes3\" (DOC-2025-63639) is being processed.', 1, '2025-04-13 17:04:22'),
(4, 4, 2, 'Your document \"terst2\" has been marked as COMPLETE and is ready for collection or download. Note: \"you can dowwnload or visit office to pick your documment\"', 1, '2025-04-13 20:15:15'),
(5, 4, 2, 'Your document \"terst2\" has been marked as COMPLETE and is ready for collection or download. Note: \"you can dowwnload or visit office to pick your documment\"', 1, '2025-04-13 20:16:14'),
(6, 4, 2, 'Your document \"terst2\" has been marked as COMPLETE and is ready for collection or download. Note: \"you can dowwnload or visit office to pick your documment\"', 1, '2025-04-13 20:19:28');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `version` int(11) DEFAULT 1,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `title` varchar(255) NOT NULL,
  `type` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `final_destination` varchar(100) DEFAULT NULL,
  `finalized_at` datetime DEFAULT NULL,
  `finalized_by` int(11) DEFAULT NULL,
  `finalization_note` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` datetime NOT NULL,
  `doc_unique_id` varchar(50) DEFAULT NULL,
  `submitter_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `file_name`, `file_path`, `version`, `uploaded_by`, `uploaded_at`, `title`, `type`, `department`, `final_destination`, `finalized_at`, `finalized_by`, `finalization_note`, `status`, `created_at`, `doc_unique_id`, `submitter_id`) VALUES
(1, '', 'uploads/67fbb1881b72d_DOC-2025-84908.pdf', 1, 3, '2025-04-13 12:43:52', 'test', 'Contract', 'Administration', NULL, NULL, NULL, NULL, 'in_movement', '2025-04-13 15:43:52', 'DOC-2025-22503', 4),
(2, '', 'uploads/67fbb32675054_DOC-2025-61988.pdf', 1, 3, '2025-04-13 12:50:46', 'terst2', 'Contract', 'Procurement', 'Legal', NULL, NULL, NULL, 'done', '2025-04-13 15:50:46', 'DOC-2025-61988', 4),
(3, '', 'uploads/67fbb85e6b5b2_DOC-2025-63639.pdf', 1, 3, '2025-04-13 13:13:02', 'tes3', 'Contract', 'HR', NULL, NULL, NULL, NULL, 'pending', '2025-04-13 16:13:02', 'DOC-2025-63639', 4),
(4, '', 'uploads/67fbbf2e9cb0c_DOC-2025-20687.pdf', 1, 3, '2025-04-13 13:42:06', 'test4', 'Contract', 'HR', 'Legal', NULL, NULL, NULL, 'pending', '2025-04-13 16:42:06', 'DOC-2025-20687', NULL);

--
-- Triggers `documents`
--
DELIMITER $$
CREATE TRIGGER `update_status_on_final_destination` AFTER UPDATE ON `documents` FOR EACH ROW BEGIN
    -- If document has reached its final destination and status is still 'in_movement'
    IF NEW.department = NEW.final_destination AND NEW.status = 'in_movement' THEN
        -- Update the status to 'pending_approval'
        UPDATE `documents` SET status = 'pending_approval' WHERE id = NEW.id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `document_approvals`
--

CREATE TABLE `document_approvals` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `approved_by` int(11) NOT NULL,
  `action` enum('approve','reject') NOT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_approvals`
--

INSERT INTO `document_approvals` (`id`, `document_id`, `approved_by`, `action`, `comments`, `created_at`) VALUES
(1, 1, 3, 'approve', 'test', '2025-04-13 12:48:26'),
(2, 1, 3, 'approve', 'test', '2025-04-13 12:49:51'),
(3, 2, 3, 'approve', 'test2', '2025-04-13 12:51:19'),
(4, 2, 3, 'approve', '', '2025-04-13 17:00:56');

-- --------------------------------------------------------

--
-- Table structure for table `document_clients`
--

CREATE TABLE `document_clients` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_clients`
--

INSERT INTO `document_clients` (`id`, `document_id`, `client_id`, `created_at`) VALUES
(1, 1, 4, '2025-04-13 17:04:22'),
(2, 2, 4, '2025-04-13 17:04:22'),
(3, 3, 4, '2025-04-13 17:04:22');

--
-- Triggers `document_clients`
--
DELIMITER $$
CREATE TRIGGER `check_client_role` BEFORE INSERT ON `document_clients` FOR EACH ROW BEGIN
    DECLARE user_role VARCHAR(50);
    
    -- Get the role of the user being assigned to a document
    SELECT role INTO user_role FROM users WHERE id = NEW.client_id;
    
    -- Ensure the user has the 'client' role
    IF user_role != 'client' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Only users with the client role can be assigned to documents in the document_clients table';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `document_movements`
--

CREATE TABLE `document_movements` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `from_department` varchar(100) NOT NULL,
  `to_department` varchar(100) NOT NULL,
  `moved_by` int(11) NOT NULL,
  `note` text DEFAULT NULL COMMENT 'Movement note or reason',
  `notes` text DEFAULT NULL COMMENT 'Alternative column name for note',
  `moved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_movements`
--

INSERT INTO `document_movements` (`id`, `document_id`, `from_department`, `to_department`, `moved_by`, `note`, `notes`, `moved_at`, `created_at`) VALUES
(1, 2, 'HR', 'IT', 3, 'test', NULL, '2025-04-13 14:29:24', '2025-04-13 14:29:24'),
(2, 2, 'IT', 'Legal', 3, '', NULL, '2025-04-13 14:32:11', '2025-04-13 14:32:11'),
(3, 2, 'Legal', 'IT', 3, '', NULL, '2025-04-13 14:34:02', '2025-04-13 14:34:02'),
(4, 2, 'IT', 'Procurement', 3, '', NULL, '2025-04-13 14:52:19', '2025-04-13 14:52:19'),
(5, 1, 'HR', 'Administration', 3, '', NULL, '2025-04-13 17:42:40', '2025-04-13 17:42:40');

-- --------------------------------------------------------

--
-- Table structure for table `document_tags`
--

CREATE TABLE `document_tags` (
  `document_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `file_movements`
--

CREATE TABLE `file_movements` (
  `id` int(11) NOT NULL,
  `document_id` int(11) DEFAULT NULL,
  `from_office` varchar(50) DEFAULT NULL,
  `to_office` varchar(50) DEFAULT NULL,
  `moved_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `finalized_documents_view`
-- (See below for the actual view)
--
CREATE TABLE `finalized_documents_view` (
`id` int(11)
,`file_name` varchar(255)
,`file_path` varchar(255)
,`version` int(11)
,`uploaded_by` int(11)
,`uploaded_at` timestamp
,`title` varchar(255)
,`type` varchar(100)
,`department` varchar(100)
,`final_destination` varchar(100)
,`finalized_at` datetime
,`finalized_by` int(11)
,`finalization_note` text
,`status` varchar(50)
,`created_at` datetime
,`doc_unique_id` varchar(50)
,`submitter_id` int(11)
,`uploader_name` varchar(100)
,`finalizer_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `offices`
--

CREATE TABLE `offices` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `offices`
--

INSERT INTO `offices` (`id`, `name`, `location`, `description`, `created_at`) VALUES
(1, 'Governor Office', 'County HQ, 1st Floor', 'Office of the County Governor', '2025-04-12 08:42:43'),
(2, 'Finance Department', 'County HQ, 2nd Floor', 'Handles all county financial matters', '2025-04-12 08:42:43'),
(3, 'Health Department', 'County Hospital Complex', 'Handles all county health matters', '2025-04-12 08:42:43'),
(4, 'Agriculture Department', 'Extension Building', 'Handles all county agricultural matters', '2025-04-12 08:42:43'),
(5, 'Education Department', 'County HQ, 3rd Floor', 'Handles all county education matters', '2025-04-12 08:42:43');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expires_at`, `created_at`) VALUES
(1, 3, '$2y$10$eBuSF.OvT5qqBBHwIH3J7eyusU14WZHQa69i/hpFuRpySnr2HATqS', '2025-04-13 15:13:08', '2025-04-13 12:13:08');

-- --------------------------------------------------------

--
-- Table structure for table `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE `tags` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','clerk','supervisor','viewer','client') NOT NULL,
  `department` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `registered_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `role`, `department`, `created_at`, `registered_by`) VALUES
(3, 'Admin', 'admin@county.com', NULL, '$2y$10$ICtfT8p.acPU0J60JcqITOnuapc5/NTa07wcaWepO3uuY5Gs/n/0.', 'admin', 'IT', '2025-04-13 09:21:05', NULL),
(4, 'Hassan Adan', 'hassan@gmail.com', NULL, '$2y$10$RZvUnc.l2giCx/FTnamt6eJkFUpZRcfVuccDSOJx8H0.y02RKvo9m', 'client', '', '2025-04-13 11:17:28', NULL);

-- --------------------------------------------------------

--
-- Structure for view `finalized_documents_view`
--
DROP TABLE IF EXISTS `finalized_documents_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `finalized_documents_view`  AS SELECT `d`.`id` AS `id`, `d`.`file_name` AS `file_name`, `d`.`file_path` AS `file_path`, `d`.`version` AS `version`, `d`.`uploaded_by` AS `uploaded_by`, `d`.`uploaded_at` AS `uploaded_at`, `d`.`title` AS `title`, `d`.`type` AS `type`, `d`.`department` AS `department`, `d`.`final_destination` AS `final_destination`, `d`.`finalized_at` AS `finalized_at`, `d`.`finalized_by` AS `finalized_by`, `d`.`finalization_note` AS `finalization_note`, `d`.`status` AS `status`, `d`.`created_at` AS `created_at`, `d`.`doc_unique_id` AS `doc_unique_id`, `d`.`submitter_id` AS `submitter_id`, `u1`.`name` AS `uploader_name`, `u2`.`name` AS `finalizer_name` FROM ((`documents` `d` left join `users` `u1` on(`d`.`uploaded_by` = `u1`.`id`)) left join `users` `u2` on(`d`.`finalized_by` = `u2`.`id`)) WHERE `d`.`status` = 'finalized' ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `client_notifications`
--
ALTER TABLE `client_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `document_id` (`document_id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `fk_documents_submitter` (`submitter_id`),
  ADD KEY `fk_finalized_by_user` (`finalized_by`),
  ADD KEY `idx_document_status` (`status`),
  ADD KEY `idx_document_final_destination` (`final_destination`);

--
-- Indexes for table `document_approvals`
--
ALTER TABLE `document_approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `document_clients`
--
ALTER TABLE `document_clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `document_client_unique` (`document_id`,`client_id`),
  ADD KEY `document_clients_document_id` (`document_id`),
  ADD KEY `document_clients_client_id` (`client_id`);

--
-- Indexes for table `document_movements`
--
ALTER TABLE `document_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `moved_by` (`moved_by`);

--
-- Indexes for table `document_tags`
--
ALTER TABLE `document_tags`
  ADD PRIMARY KEY (`document_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `file_movements`
--
ALTER TABLE `file_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `offices`
--
ALTER TABLE `offices`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `client_notifications`
--
ALTER TABLE `client_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `document_approvals`
--
ALTER TABLE `document_approvals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `document_clients`
--
ALTER TABLE `document_clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `document_movements`
--
ALTER TABLE `document_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `file_movements`
--
ALTER TABLE `file_movements`
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_notifications`
--
ALTER TABLE `client_notifications`
  ADD CONSTRAINT `client_notifications_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_notifications_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`submitter_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_documents_submitter` FOREIGN KEY (`submitter_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_finalized_by_user` FOREIGN KEY (`finalized_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `document_approvals`
--
ALTER TABLE `document_approvals`
  ADD CONSTRAINT `document_approvals_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_approvals_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_clients`
--
ALTER TABLE `document_clients`
  ADD CONSTRAINT `document_clients_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_clients_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_movements`
--
ALTER TABLE `document_movements`
  ADD CONSTRAINT `document_movements_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_movements_ibfk_2` FOREIGN KEY (`moved_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_tags`
--
ALTER TABLE `document_tags`
  ADD CONSTRAINT `document_tags_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `file_movements`
--
ALTER TABLE `file_movements`
  ADD CONSTRAINT `file_movements_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `remember_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
