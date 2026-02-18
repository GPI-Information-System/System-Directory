-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Feb 18, 2026 at 08:14 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `system_directory_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `status_logs`
--

CREATE TABLE `status_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `system_id` int(10) UNSIGNED NOT NULL,
  `old_status` enum('online','offline','maintenance','down','archived') NOT NULL,
  `new_status` enum('online','offline','maintenance','down','archived') NOT NULL,
  `changed_by` int(10) UNSIGNED NOT NULL,
  `change_note` varchar(500) DEFAULT NULL COMMENT 'Optional reason/note for status change (max 500 chars)',
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `status_logs`
--

INSERT INTO `status_logs` (`id`, `system_id`, `old_status`, `new_status`, `changed_by`, `change_note`, `changed_at`) VALUES
(1, 12, 'maintenance', 'online', 1, NULL, '2026-02-10 23:37:38'),
(2, 11, 'maintenance', 'online', 1, NULL, '2026-02-10 23:37:49'),
(3, 6, 'maintenance', 'online', 1, NULL, '2026-02-10 23:37:57'),
(4, 6, 'online', 'maintenance', 1, NULL, '2026-02-10 23:38:08'),
(5, 11, 'online', 'down', 1, 'test', '2026-02-11 00:53:07'),
(7, 12, 'online', 'maintenance', 1, 'Fixed Bug and fixedserver', '2026-02-11 00:52:30'),
(8, 12, 'maintenance', 'online', 1, 'fixed error and fixed server', '2026-02-11 00:57:24'),
(9, 11, 'down', 'online', 1, 'Fixed server', '2026-02-11 00:59:23'),
(10, 12, 'online', 'maintenance', 1, 'test', '2026-02-11 02:55:39'),
(13, 11, 'online', 'maintenance', 1, NULL, '2026-02-11 23:34:46'),
(14, 11, 'maintenance', 'offline', 1, NULL, '2026-02-12 00:06:21'),
(15, 11, 'offline', 'down', 1, 'testing down system 123', '2026-02-12 00:12:55'),
(16, 16, 'online', 'maintenance', 1, 'testing 3.3.3.3.3', '2026-02-12 02:48:54'),
(17, 17, 'online', 'down', 1, 'testing down 4.4.4.4.4', '2026-02-12 02:49:23'),
(18, 15, 'online', 'down', 1, 'Testing email notification - server crash simulation', '2026-02-12 04:08:39'),
(19, 17, 'down', 'online', 1, NULL, '2026-02-12 04:18:09'),
(20, 17, 'online', 'down', 1, 'testing again down system', '2026-02-12 04:18:33'),
(21, 17, 'down', 'online', 1, '33333', '2026-02-12 04:21:32'),
(22, 17, 'online', 'down', 1, 'testing again 1111', '2026-02-12 04:21:45'),
(23, 15, 'down', 'online', 1, 'test 1', '2026-02-12 04:49:47'),
(24, 15, 'online', 'down', 1, 'testing down system 11231123', '2026-02-12 04:50:06'),
(25, 15, 'down', 'online', 1, 'testing `12', '2026-02-12 04:51:01'),
(26, 15, 'online', 'down', 1, 'testing down for email testing notifications.', '2026-02-12 04:51:28'),
(27, 17, 'down', 'online', 1, 'test', '2026-02-12 05:19:42'),
(28, 17, 'online', 'down', 1, 'test down system', '2026-02-12 05:19:54'),
(29, 17, 'down', 'online', 1, 'for testing again', '2026-02-12 05:28:37'),
(30, 17, 'online', 'down', 1, 'testing down again', '2026-02-12 05:31:23'),
(31, 10, 'online', 'maintenance', 1, 'server bug fixed, added new code', '2026-02-13 23:44:56'),
(32, 16, 'maintenance', 'down', 1, NULL, '2026-02-13 23:54:20'),
(33, 12, 'maintenance', 'down', 1, NULL, '2026-02-13 23:54:30'),
(34, 6, 'maintenance', 'down', 1, NULL, '2026-02-13 23:55:05'),
(35, 17, 'down', 'online', 1, 'testing online 123', '2026-02-16 01:48:28'),
(36, 17, 'online', 'down', 1, 'Testing Down', '2026-02-17 23:36:56'),
(37, 10, 'maintenance', 'down', 1, 'Down again testing', '2026-02-17 23:53:40'),
(38, 17, 'down', 'online', 1, 'Update Online', '2026-02-18 04:49:25'),
(39, 16, 'down', 'maintenance', 1, 'Test Maintenance', '2026-02-18 04:49:38'),
(40, 15, 'down', 'online', 1, 'Test online 123', '2026-02-18 04:49:55'),
(43, 15, 'online', 'down', 1, 'Auto-detected: Connection error: URL rejected: Malformed input to a URL function', '2026-02-18 05:45:46'),
(44, 17, 'online', 'down', 1, 'Auto-detected: DNS resolution failed - Could not resolve host', '2026-02-18 05:45:43'),
(45, 16, 'maintenance', 'online', 1, 'Testing online', '2026-02-18 05:49:20');

-- --------------------------------------------------------

--
-- Table structure for table `systems`
--

CREATE TABLE `systems` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `domain` varchar(255) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `description` varchar(1000) DEFAULT NULL COMMENT 'System description (max 1000 chars)',
  `contact_number` varchar(50) DEFAULT '123',
  `status` enum('online','offline','maintenance','down','archived') DEFAULT 'online',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `systems`
--

INSERT INTO `systems` (`id`, `name`, `domain`, `logo`, `description`, `contact_number`, `status`, `created_at`, `updated_at`) VALUES
(2, 'Finance Portal', 'finance.company.com', NULL, 'Financial management and accounting system', '123', 'down', '2026-02-05 07:16:47', '2026-02-09 23:38:22'),
(3, 'Customer Portal', 'customers.company.com', NULL, 'Customer relationship management and support system', '123', 'down', '2026-02-05 07:16:47', '2026-02-09 23:38:27'),
(4, 'Inventory System', 'inventory.company.com', NULL, 'Warehouse and inventory tracking system.', '123', 'archived', '2026-02-05 07:16:47', '2026-02-09 23:38:34'),
(6, 'Test Add System', 'testadd.com', 'uploads/logos/logo_69857e5582a34.png', NULL, '123', 'down', '2026-02-06 02:41:28', '2026-02-18 01:50:39'),
(9, 'TESTTSSwww', 'TESTTT.COM', 'uploads/logos/logo_69857e4db4a17.png', 'TESTTTDESCWEQQWEQEQ', '672342', 'offline', '2026-02-06 05:28:19', '2026-02-10 00:40:39'),
(10, 'Canteen System!@#!!#!@#!123sssadadaaasaasdadaasdadaasdADAqeqqeqewqqeqwqwqweq', 'youtube.com', 'uploads/logos/logo_698a72264af69.png', 'qweqe3231321112318907!@#!#!!@#!!@#!@#!#', '231213', 'online', '2026-02-08 23:31:13', '2026-02-18 05:33:06'),
(11, 'Test system down 123', 'Testagainupdateddomain.com.ph', NULL, 'testing desc update', '5555', 'online', '2026-02-10 00:02:12', '2026-02-18 05:22:08'),
(12, 'TESTchangenote 2', 'TESTMAINTE.COM', NULL, 'TWETWRWWEWR', '11231', 'down', '2026-02-10 01:00:43', '2026-02-13 23:54:30'),
(15, 'testing Online 1 2 3', 'testind add morehan 2.2.2.2.2', NULL, 'testing 2.2.2.2.2', '123', 'down', '2026-02-12 02:47:27', '2026-02-18 05:22:35'),
(16, 'testing system online', 'reddiiiit.com.ph', NULL, 'testing only', '123', 'online', '2026-02-12 02:48:42', '2026-02-18 06:18:59'),
(17, 'testing online 4.4.4.4', '4.4.4.4.4', NULL, 'testing 4.4.4.4.4', '123', 'down', '2026-02-12 02:49:10', '2026-02-18 05:25:49');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Super Admin','Admin') NOT NULL,
  `email` varchar(255) DEFAULT NULL COMMENT 'Email for notifications (IT specialist will configure)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `email`, `created_at`) VALUES
(1, 'superadmin', '$2y$10$wmh.Tp7MjURjcfKZ22LNyeavxEAlZLFXLTolQPRrIwEXyeOIqmwbu', 'Super Admin', 'superadmin@demo.com', '2026-02-05 07:16:47'),
(2, 'admin', '$2y$10$wmh.Tp7MjURjcfKZ22LNyeavxEAlZLFXLTolQPRrIwEXyeOIqmwbu', 'Admin', 'admin@demo.com', '2026-02-05 07:16:47'),
(999, 'system_monitor', '$2y$10$placeholder', 'Admin', 'system@gportal.local', '2026-02-18 05:14:31');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `status_logs`
--
ALTER TABLE `status_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `system_id` (`system_id`),
  ADD KEY `changed_by` (`changed_by`),
  ADD KEY `changed_at` (`changed_at`),
  ADD KEY `idx_system_date` (`system_id`,`changed_at`),
  ADD KEY `idx_date_system` (`changed_at`,`system_id`),
  ADD KEY `idx_new_status` (`new_status`),
  ADD KEY `idx_old_new_status` (`old_status`,`new_status`);
ALTER TABLE `status_logs` ADD FULLTEXT KEY `ft_change_note` (`change_note`);

--
-- Indexes for table `systems`
--
ALTER TABLE `systems`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_updated_at` (`updated_at`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_status_updated` (`status`,`updated_at`);
ALTER TABLE `systems` ADD FULLTEXT KEY `ft_description` (`description`);
ALTER TABLE `systems` ADD FULLTEXT KEY `ft_name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `status_logs`
--
ALTER TABLE `status_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `systems`
--
ALTER TABLE `systems`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1000;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `status_logs`
--
ALTER TABLE `status_logs`
  ADD CONSTRAINT `fk_status_logs_system` FOREIGN KEY (`system_id`) REFERENCES `systems` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_status_logs_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
