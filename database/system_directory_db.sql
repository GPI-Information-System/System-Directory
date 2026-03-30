-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Mar 28, 2026 at 06:02 AM
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
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Direct', 1, '2026-03-28 01:23:24', '2026-03-28 02:36:39'),
(2, 'Indirect', 2, '2026-03-28 01:23:24', '2026-03-28 02:36:39'),
(3, 'Support', 3, '2026-03-28 01:23:24', '2026-03-28 02:36:39');

-- --------------------------------------------------------

--
-- Table structure for table `email_recipients`
--

CREATE TABLE `email_recipients` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `name` varchar(100) DEFAULT NULL COMMENT 'Display name / department label',
  `added_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'User ID who added this email',
  `last_used` timestamp NULL DEFAULT NULL COMMENT 'Last time this email was used in a schedule',
  `use_count` int(11) NOT NULL DEFAULT 0 COMMENT 'How many times used — for autocomplete ranking',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Remembered email addresses for maintenance schedule notifications';

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_schedules`
--

CREATE TABLE `maintenance_schedules` (
  `id` int(10) UNSIGNED NOT NULL,
  `system_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `actual_end_datetime` datetime DEFAULT NULL,
  `status` enum('Scheduled','In Progress','Done') NOT NULL DEFAULT 'Scheduled',
  `created_by` int(10) UNSIGNED NOT NULL,
  `done_by_username` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `exceeded_duration` int(11) DEFAULT NULL COMMENT 'Seconds exceeded past end_datetime when marked Done. NULL = not done or did not exceed.',
  `deleted_from_calendar` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = hidden from calendar (soft deleted), still visible in analytics'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- --------------------------------------------------------

--
-- Table structure for table `systems`
--

CREATE TABLE `systems` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL DEFAULT 'Direct',
  `domain` varchar(255) NOT NULL,
  `japanese_domain` varchar(255) DEFAULT NULL,
  `badge_url` varchar(500) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `description` varchar(1000) DEFAULT NULL COMMENT 'System description (max 1000 chars)',
  `japanese_description` text DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT '123',
  `status` enum('online','offline','maintenance','down','archived') DEFAULT 'online',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `exclude_health_check` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = skip this system during automated health checks'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `systems`
--

INSERT INTO `systems` (`id`, `name`, `category`, `domain`, `japanese_domain`, `badge_url`, `logo`, `description`, `japanese_description`, `contact_number`, `status`, `created_at`, `updated_at`, `exclude_health_check`) VALUES
(1, 'Document Management System', 'Direct', 'dms.gpi.com', '192,168,5,228;80', 'https://uptime.gpi.com/api/badge/1/status', 'uploads/logos/logo_69c60295255ae.png', '', '', '1120', 'down', '2026-03-27 04:07:49', '2026-03-28 04:59:35', 0),
(2, 'Equipment Monitoring System', 'Direct', 'ems.gpi.com', '', 'https://uptime.gpi.com/api/badge/15/status', 'uploads/logos/logo_69c60379dbafd.png', '', '', '1120', 'down', '2026-03-27 04:11:37', '2026-03-28 01:51:18', 0),
(3, 'iBoard System', 'Direct', 'iboard.gpi.com', '', 'https://uptime.gpi.com/api/badge/14/status', 'uploads/logos/logo_69c603f904016.png', '', '', '1120', 'down', '2026-03-27 04:13:45', '2026-03-28 01:51:18', 0),
(4, 'LOA Monitoring System', 'Direct', 'glory.lms.com.ph', '', 'https://uptime.gpi.com/api/badge/16/status', 'uploads/logos/logo_69c604f273221.png', '', '', '1120', 'down', '2026-03-27 04:17:54', '2026-03-28 01:51:18', 0),
(5, 'Mold Inventory Management System', 'Direct', 'mims.gpi.com', '', 'https://uptime.gpi.com/api/badge/18/status', 'uploads/logos/logo_69c60555928ec.png', '', '', '1120', 'down', '2026-03-27 04:19:33', '2026-03-28 01:51:18', 0),
(6, 'Parts Order Form System', 'Direct', 'pofs.gpi.com', '', 'https://uptime.gpi.com/api/badge/3/status', 'uploads/logos/logo_69c6243634105.png', '', '', '1120', 'down', '2026-03-27 06:31:18', '2026-03-28 01:51:18', 0),
(7, 'Production Monitoring System', 'Direct', 'pms.gpi.com', '', 'https://uptime.gpi.com/api/badge/20/status', 'uploads/logos/logo_69c62484729f2.png', '', '', '123', 'down', '2026-03-27 06:32:36', '2026-03-28 01:51:18', 0),
(8, 'QC - Trouble Report System', 'Direct', 'qc-trs.gpi.com', '', 'https://uptime.gpi.com/api/badge/4/status', 'uploads/logos/logo_69c628d25dc63.png', '', '', '1120', 'down', '2026-03-27 06:50:58', '2026-03-28 01:51:18', 0),
(9, 'Revision of Product Information System', 'Direct', 'rpis.gpi.com', '', 'https://uptime.gpi.com/api/badge/2/status', 'uploads/logos/logo_69c6291e6ea9a.png', '', '', '1120', 'down', '2026-03-27 06:52:14', '2026-03-28 01:51:18', 0),
(10, 'Production Supplies Inventory System', 'Direct', 'psis.gpi.com', '', 'https://uptime.gpi.com/api/badge/31/status', 'uploads/logos/logo_69c62962bbfc6.png', '', '', '1120', 'down', '2026-03-27 06:53:22', '2026-03-28 01:51:18', 0);

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
(2, 'superadmin', '$2y$10$wmh.Tp7MjURjcfKZ22LNyeavxEAlZLFXLTolQPRrIwEXyeOIqmwbu', 'Super Admin', 'superadmin@demo.com', '2026-02-05 07:16:47'),
(3, 'admin', '$2y$10$wmh.Tp7MjURjcfKZ22LNyeavxEAlZLFXLTolQPRrIwEXyeOIqmwbu', 'Admin', 'admin@demo.com', '2026-02-05 07:16:47'),
(4, 'system_monitor', '$2y$10$placeholder', 'Admin', 'system@gportal.local', '2026-02-18 05:14:31'),
(7, 'adm.andrew', '$2y$10$8.dD/fGE/1QxYtLrSLOvWuRl3B4Bx4t8D0QAVVhEKAKfP9i7kKe.6', 'Super Admin', 'samplesuperadmin@outlook.com', '2026-03-28 04:50:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `email_recipients`
--
ALTER TABLE `email_recipients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email` (`email`),
  ADD KEY `idx_use_count` (`use_count`),
  ADD KEY `fk_email_recipients_user` (`added_by`);

--
-- Indexes for table `maintenance_schedules`
--
ALTER TABLE `maintenance_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_system_id` (`system_id`),
  ADD KEY `idx_start_datetime` (`start_datetime`),
  ADD KEY `idx_end_datetime` (`end_datetime`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_deleted_from_calendar` (`deleted_from_calendar`);

--
-- Indexes for table `status_logs`
--
ALTER TABLE `status_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `changed_by` (`changed_by`),
  ADD KEY `changed_at` (`changed_at`),
  ADD KEY `idx_system_date` (`system_id`,`changed_at`),
  ADD KEY `idx_date_system` (`changed_at`,`system_id`),
  ADD KEY `idx_new_status` (`new_status`),
  ADD KEY `idx_old_new_status` (`old_status`,`new_status`),
  ADD KEY `idx_changed_by_at` (`changed_by`,`changed_at`);
ALTER TABLE `status_logs` ADD FULLTEXT KEY `ft_change_note` (`change_note`);

--
-- Indexes for table `systems`
--
ALTER TABLE `systems`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_updated_at` (`updated_at`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_status_updated` (`status`,`updated_at`),
  ADD KEY `idx_health_check_status` (`exclude_health_check`,`status`),
  ADD KEY `idx_health_check_covering` (`exclude_health_check`,`status`,`id`,`name`(100),`domain`(100),`badge_url`(100));
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
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `email_recipients`
--
ALTER TABLE `email_recipients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `maintenance_schedules`
--
ALTER TABLE `maintenance_schedules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `status_logs`
--
ALTER TABLE `status_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `systems`
--
ALTER TABLE `systems`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `email_recipients`
--
ALTER TABLE `email_recipients`
  ADD CONSTRAINT `fk_email_recipients_user` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `maintenance_schedules`
--
ALTER TABLE `maintenance_schedules`
  ADD CONSTRAINT `fk_maintenance_system` FOREIGN KEY (`system_id`) REFERENCES `systems` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_maintenance_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
