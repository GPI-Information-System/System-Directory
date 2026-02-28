-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Feb 28, 2026 at 07:44 AM
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

--
-- Dumping data for table `email_recipients`
--

INSERT INTO `email_recipients` (`id`, `email`, `name`, `added_by`, `last_used`, `use_count`, `created_at`) VALUES
(1, 'test@company.com', NULL, NULL, '2026-02-28 06:29:47', 11, '2026-02-28 02:47:05');

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
  `status` enum('Scheduled','In Progress','Done') NOT NULL DEFAULT 'Scheduled',
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `exceeded_duration` int(11) DEFAULT NULL COMMENT 'Seconds exceeded past end_datetime when marked Done. NULL = not done or did not exceed.',
  `deleted_from_calendar` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = hidden from calendar (soft deleted), still visible in analytics'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_schedules`
--

INSERT INTO `maintenance_schedules` (`id`, `system_id`, `title`, `description`, `start_datetime`, `end_datetime`, `status`, `created_by`, `created_at`, `updated_at`, `exceeded_duration`, `deleted_from_calendar`) VALUES
(29, 11, 'testing', '', '2026-02-25 12:04:00', '2026-02-25 13:05:00', 'Done', 1, '2026-02-25 04:03:40', '2026-02-25 05:27:53', 1373, 1),
(30, 16, 'test', '', '2026-02-25 14:38:00', '2026-02-25 14:39:00', 'Done', 1, '2026-02-25 06:37:51', '2026-02-25 06:43:20', 260, 1),
(31, 16, 'test', '', '2026-02-25 15:07:00', '2026-02-25 15:08:00', 'Done', 1, '2026-02-25 07:06:49', '2026-02-25 23:25:43', 58663, 1),
(32, 10, 'test', '', '2026-02-25 15:21:00', '2026-02-25 15:22:00', 'Done', 1, '2026-02-25 07:20:50', '2026-02-25 23:26:02', 57842, 1),
(33, 10, 'test', '', '2026-02-26 07:50:00', '2026-02-26 07:51:00', 'Done', 1, '2026-02-25 23:49:19', '2026-02-26 02:55:48', 11088, 1),
(34, 17, 'TESTING', 'TEST', '2026-02-26 13:55:00', '2026-02-26 13:56:00', 'Done', 1, '2026-02-26 05:55:06', '2026-02-26 05:57:13', 73, 1),
(35, 16, 'TESTING', 'TEST', '2026-02-26 13:55:00', '2026-02-26 13:56:00', 'Done', 1, '2026-02-26 05:55:06', '2026-02-26 05:57:22', 82, 1),
(36, 15, 'TESTING', 'TEST', '2026-02-26 13:55:00', '2026-02-26 13:56:00', 'Done', 1, '2026-02-26 05:55:06', '2026-02-26 05:57:33', 93, 1),
(37, 17, 'TESTING', '', '2026-02-26 14:17:00', '2026-02-26 14:18:00', 'In Progress', 1, '2026-02-26 06:16:44', '2026-02-27 22:55:11', NULL, 1),
(38, 16, 'TESTING', '', '2026-02-26 14:17:00', '2026-02-26 14:18:00', 'In Progress', 1, '2026-02-26 06:16:44', '2026-02-27 22:55:13', NULL, 1),
(39, 15, 'TESTING', '', '2026-02-26 14:17:00', '2026-02-26 14:18:00', 'In Progress', 1, '2026-02-26 06:16:44', '2026-02-27 22:55:15', NULL, 1),
(41, 10, 'TESTING', '', '2026-02-28 10:18:00', '2026-02-28 22:19:00', 'Done', 1, '2026-02-28 02:17:21', '2026-02-28 02:20:07', NULL, 1),
(42, 10, 'test', '', '2026-02-28 10:26:00', '2026-02-28 12:26:00', 'Done', 1, '2026-02-28 02:27:27', '2026-02-28 02:27:48', NULL, 1),
(43, 10, 'test', '', '2026-02-28 10:44:00', '2026-02-28 12:43:00', 'Scheduled', 1, '2026-02-28 02:43:21', '2026-02-28 02:44:09', NULL, 1),
(44, 10, 'testing', '', '2026-02-28 10:46:00', '2026-02-28 13:45:00', 'Done', 1, '2026-02-28 02:45:42', '2026-02-28 02:48:09', NULL, 1),
(45, 10, 'test', '', '2026-02-28 12:52:00', '2026-02-28 14:51:00', 'Done', 1, '2026-02-28 04:52:07', '2026-02-28 05:08:28', NULL, 1),
(46, 17, 'test', '', '2026-02-28 13:10:00', '2026-02-28 15:09:00', 'Done', 1, '2026-02-28 05:09:29', '2026-02-28 05:14:53', NULL, 1),
(47, 16, 'test', '', '2026-02-28 13:10:00', '2026-02-28 15:09:00', 'Done', 1, '2026-02-28 05:09:29', '2026-02-28 05:15:02', NULL, 1),
(48, 10, 'test', '', '2026-02-28 13:10:00', '2026-02-28 15:09:00', 'Done', 1, '2026-02-28 05:09:29', '2026-02-28 05:47:45', NULL, 1),
(49, 19, 'test', '', '2026-02-28 14:00:00', '2026-02-28 16:00:00', 'Done', 1, '2026-02-28 06:00:10', '2026-02-28 06:14:25', NULL, 1),
(50, 19, 'testing', '', '2026-02-28 14:15:00', '2026-02-28 16:14:00', 'Done', 1, '2026-02-28 06:14:46', '2026-02-28 06:15:34', NULL, 1),
(51, 18, 'testing', '', '2026-02-28 14:15:00', '2026-02-28 16:14:00', 'Done', 1, '2026-02-28 06:14:46', '2026-02-28 06:15:44', NULL, 1),
(52, 10, 'testing', '', '2026-02-28 14:15:00', '2026-02-28 16:14:00', 'Done', 1, '2026-02-28 06:14:46', '2026-02-28 06:15:53', NULL, 1),
(53, 19, 'TESTING', '', '2026-02-28 14:17:00', '2026-02-28 16:16:00', 'Done', 1, '2026-02-28 06:17:02', '2026-02-28 06:17:28', NULL, 1),
(54, 18, 'TESTING', '', '2026-02-28 14:17:00', '2026-02-28 16:16:00', 'Done', 1, '2026-02-28 06:17:02', '2026-02-28 06:17:35', NULL, 1),
(55, 10, 'TESTING', '', '2026-02-28 14:17:00', '2026-02-28 16:16:00', 'Done', 1, '2026-02-28 06:17:02', '2026-02-28 06:17:48', NULL, 1),
(56, 19, 'TESTING', '', '2026-02-28 14:30:00', '2026-02-28 16:29:00', 'Done', 1, '2026-02-28 06:29:47', '2026-02-28 06:30:51', NULL, 1),
(57, 18, 'TESTING', '', '2026-02-28 14:30:00', '2026-02-28 16:29:00', 'Done', 1, '2026-02-28 06:29:47', '2026-02-28 06:31:00', NULL, 1),
(58, 10, 'TESTING', '', '2026-02-28 14:30:00', '2026-02-28 16:29:00', 'Done', 1, '2026-02-28 06:29:47', '2026-02-28 06:31:11', NULL, 1);

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
(45, 16, 'maintenance', 'online', 1, 'Testing online', '2026-02-18 05:49:20'),
(46, 16, 'online', 'maintenance', 1, 'testing only', '2026-02-19 00:16:31'),
(47, 16, 'maintenance', 'online', 1, '', '2026-02-19 00:16:47'),
(48, 16, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"TESTING ONLY\" started.', '2026-02-19 05:57:55'),
(49, 16, 'maintenance', 'online', 1, 'testing', '2026-02-19 05:58:39'),
(50, 16, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"testing\" started.', '2026-02-19 05:59:18'),
(51, 16, 'maintenance', 'online', 1, 'testing online', '2026-02-19 23:12:29'),
(52, 16, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"TESTING 1\" started.', '2026-02-19 23:37:37'),
(53, 17, 'down', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"testing 2\" started.', '2026-02-19 23:42:18'),
(54, 17, 'maintenance', 'online', 1, '', '2026-02-19 23:55:04'),
(55, 16, 'maintenance', 'online', 1, '', '2026-02-19 23:55:11'),
(56, 11, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"testing downtime\" started.', '2026-02-20 00:02:26'),
(57, 11, 'maintenance', 'online', 1, '', '2026-02-20 00:17:37'),
(58, 11, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"testing downtime\" started.', '2026-02-20 00:18:03'),
(59, 11, 'maintenance', 'online', 1, '', '2026-02-20 00:32:50'),
(60, 11, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"testing 1\" started.', '2026-02-20 00:34:00'),
(61, 11, 'maintenance', 'online', 1, '', '2026-02-20 00:44:50'),
(62, 11, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"testing stop time\" started.', '2026-02-20 00:46:20'),
(63, 11, 'maintenance', 'online', 1, '', '2026-02-20 00:54:31'),
(64, 11, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"testing\" started.', '2026-02-20 00:56:13'),
(65, 11, 'maintenance', 'online', 1, '', '2026-02-20 01:30:43'),
(66, 11, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"testing again\" started.', '2026-02-20 01:32:12'),
(67, 11, 'maintenance', 'online', 1, 'test', '2026-02-23 00:33:58'),
(68, 11, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"TESTING\" started.', '2026-02-23 00:45:03'),
(69, 11, 'maintenance', 'online', 1, 'testing', '2026-02-23 01:51:46'),
(70, 11, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"testing\" started.', '2026-02-23 01:53:18'),
(71, 11, 'maintenance', 'online', 1, '', '2026-02-23 02:24:58'),
(72, 11, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"testing\" started.', '2026-02-23 02:25:00'),
(73, 11, 'maintenance', 'online', 1, '', '2026-02-23 02:44:39'),
(74, 11, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"testing again\" started.', '2026-02-23 02:45:09'),
(75, 11, 'maintenance', 'online', 1, '', '2026-02-23 02:49:07'),
(76, 11, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"test again\" started.', '2026-02-23 02:52:17'),
(77, 11, 'maintenance', 'online', 1, 'Maintenance completed. System marked as ready by specialist.', '2026-02-23 02:54:32'),
(78, 11, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"testing\" started.', '2026-02-23 03:01:00'),
(79, 11, 'maintenance', 'online', 1, 'Maintenance completed. System marked as ready by specialist.', '2026-02-23 04:03:36'),
(80, 11, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"Testing\" started.', '2026-02-23 04:47:01'),
(81, 11, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-23 05:07:16'),
(82, 11, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"testing\" started.', '2026-02-24 23:27:16'),
(83, 11, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-24 23:28:28'),
(84, 12, 'down', 'online', 1, '', '2026-02-24 23:29:47'),
(85, 12, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"testing\" started.', '2026-02-25 00:32:14'),
(86, 12, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-25 00:34:00'),
(87, 12, 'online', 'down', 1, 'Auto-detected: DNS resolution failed - Could not resolve host', '2026-02-25 00:44:04'),
(88, 17, 'online', 'down', 1, 'Auto-detected: DNS resolution failed - Could not resolve host', '2026-02-25 00:44:05'),
(89, 11, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"test again\" started.', '2026-02-25 01:52:00'),
(90, 11, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-25 02:03:21'),
(91, 11, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"testing\" started.', '2026-02-25 04:04:59'),
(92, 11, 'maintenance', 'online', 1, '', '2026-02-25 05:29:04'),
(93, 16, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"test\" started.', '2026-02-25 06:38:05'),
(94, 16, 'maintenance', 'online', 1, '', '2026-02-25 07:06:36'),
(95, 16, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"test\" started.', '2026-02-25 07:07:07'),
(96, 10, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"test\" started.', '2026-02-25 07:21:23'),
(97, 16, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-25 23:25:43'),
(98, 10, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-25 23:26:02'),
(99, 10, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"test\" started.', '2026-02-25 23:50:02'),
(100, 11, 'online', 'down', 1, 'Auto-detected: Connection timeout - Server did not respond in time', '2026-02-26 01:17:37'),
(101, 16, 'online', 'down', 1, 'Auto-detected: HTTP 403 error', '2026-02-26 01:57:38'),
(102, 10, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-26 02:55:48'),
(103, 17, 'down', 'online', 1, '', '2026-02-26 02:55:56'),
(104, 16, 'down', 'online', 1, '', '2026-02-26 02:56:05'),
(105, 17, 'online', 'down', 1, 'Auto-detected: DNS resolution failed - Could not resolve host', '2026-02-26 02:56:07'),
(106, 10, 'online', 'down', 1, 'Auto-detected: Connection timeout - Server did not respond in time', '2026-02-26 02:58:16'),
(107, 16, 'online', 'down', 1, 'Auto-detected: Connection timeout - Server did not respond in time', '2026-02-26 03:00:27'),
(108, 17, 'down', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"TESTING\" started.', '2026-02-26 05:55:14'),
(109, 16, 'down', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"TESTING\" started.', '2026-02-26 05:55:14'),
(110, 15, 'down', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"TESTING\" started.', '2026-02-26 05:55:14'),
(111, 17, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-26 05:57:13'),
(112, 16, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-26 05:57:22'),
(113, 15, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-26 05:57:33'),
(114, 15, 'online', 'down', 1, 'Auto-detected: Connection error: URL rejected: Malformed input to a URL function', '2026-02-26 05:59:21'),
(115, 17, 'online', 'down', 1, 'Auto-detected: DNS resolution failed - Could not resolve host', '2026-02-26 05:59:22'),
(116, 17, 'down', 'online', 1, '', '2026-02-26 06:16:01'),
(117, 15, 'down', 'online', 1, '', '2026-02-26 06:16:07'),
(118, 17, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"TESTING\" started.', '2026-02-26 06:17:07'),
(119, 16, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"TESTING\" started.', '2026-02-26 06:17:07'),
(120, 15, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"TESTING\" started.', '2026-02-26 06:17:07'),
(121, 17, 'maintenance', 'online', 1, '', '2026-02-27 23:07:10'),
(122, 16, 'maintenance', 'online', 1, '', '2026-02-27 23:07:21'),
(123, 15, 'maintenance', 'online', 1, '', '2026-02-27 23:07:33'),
(124, 15, 'online', 'down', 1, 'Auto-detected: Connection error: URL rejected: Malformed input to a URL function', '2026-02-27 23:09:36'),
(125, 17, 'online', 'down', 1, 'Auto-detected: DNS resolution failed - Could not resolve host', '2026-02-27 23:09:36'),
(126, 16, 'online', 'down', 1, 'Auto-detected: HTTP 403 error', '2026-02-28 00:09:58'),
(127, 10, 'down', 'online', 1, '', '2026-02-28 01:20:53'),
(128, 10, 'online', 'down', 1, 'Auto-detected: Connection timeout - Server did not respond in time', '2026-02-28 01:29:14'),
(129, 10, 'down', 'online', 1, '', '2026-02-28 01:29:40'),
(130, 10, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"testing\" started.', '2026-02-28 01:44:22'),
(131, 10, 'maintenance', 'online', 1, '', '2026-02-28 01:54:07'),
(132, 10, 'online', 'down', 1, 'Auto-detected: Connection timeout - Server did not respond in time', '2026-02-28 02:08:14'),
(133, 10, 'down', 'online', 1, '', '2026-02-28 02:10:33'),
(134, 10, 'online', 'down', 1, 'Auto-detected: DNS resolution failed - Could not resolve host', '2026-02-28 02:12:31'),
(135, 10, 'down', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"TESTING\" started.', '2026-02-28 02:18:12'),
(136, 10, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-28 02:20:07'),
(137, 10, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"test\" started.', '2026-02-28 02:27:30'),
(138, 10, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-28 02:27:48'),
(139, 10, 'online', 'down', 1, 'Auto-detected: DNS resolution failed - Could not resolve host', '2026-02-28 02:31:50'),
(140, 10, 'down', 'online', 1, '', '2026-02-28 02:42:41'),
(141, 10, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"test\" started.', '2026-02-28 02:43:33'),
(142, 10, 'maintenance', 'online', 1, '', '2026-02-28 02:44:25'),
(143, 10, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"testing\" started.', '2026-02-28 02:46:19'),
(144, 10, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-28 02:48:09'),
(145, 10, 'online', 'down', 1, 'Auto-detected: Connection timeout - Server did not respond in time', '2026-02-28 04:04:25'),
(146, 10, 'down', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"test\" started.', '2026-02-28 04:52:07'),
(147, 10, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-28 05:08:28'),
(148, 17, 'down', 'online', 1, '', '2026-02-28 05:09:01'),
(149, 16, 'down', 'online', 1, '', '2026-02-28 05:09:10'),
(150, 17, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"test\" started.', '2026-02-28 05:10:10'),
(151, 16, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"test\" started.', '2026-02-28 05:10:10'),
(152, 10, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"test\" started.', '2026-02-28 05:10:10'),
(153, 17, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-28 05:14:53'),
(154, 16, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-28 05:15:02'),
(155, 17, 'online', 'down', 1, 'Auto-detected: DNS resolution failed - Could not resolve host', '2026-02-28 05:15:04'),
(156, 10, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-28 05:47:45'),
(157, 18, 'online', 'down', 1, 'Auto-detected: DNS resolution failed - Could not resolve host', '2026-02-28 05:50:30'),
(158, 16, 'online', 'down', 1, 'Auto-detected: HTTP 403 error', '2026-02-28 05:52:46'),
(159, 19, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"test\" started.', '2026-02-28 06:00:13'),
(160, 18, 'down', 'online', 1, '', '2026-02-28 06:01:14'),
(161, 18, 'online', 'down', 1, 'Auto-detected: DNS resolution failed - Could not resolve host', '2026-02-28 06:03:16'),
(162, 18, 'down', 'online', 1, '', '2026-02-28 06:13:24'),
(163, 19, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-28 06:14:25'),
(164, 19, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"testing\" started.', '2026-02-28 06:15:12'),
(165, 18, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"testing\" started.', '2026-02-28 06:15:12'),
(166, 10, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"testing\" started.', '2026-02-28 06:15:12'),
(167, 19, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-28 06:15:34'),
(168, 19, 'online', 'down', 1, 'Auto-detected: HTTP 403 error', '2026-02-28 06:15:37'),
(169, 18, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-28 06:15:44'),
(170, 10, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-28 06:15:53'),
(171, 19, 'down', 'online', 1, '', '2026-02-28 06:16:05'),
(172, 19, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"TESTING\" started.', '2026-02-28 06:17:05'),
(173, 18, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"TESTING\" started.', '2026-02-28 06:17:06'),
(174, 10, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"TESTING\" started.', '2026-02-28 06:17:06'),
(175, 19, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-28 06:17:28'),
(176, 18, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-28 06:17:35'),
(177, 18, 'online', 'down', 1, 'Auto-detected: DNS resolution failed - Could not resolve host', '2026-02-28 06:17:36'),
(178, 19, 'online', 'down', 1, 'Auto-detected: HTTP 403 error', '2026-02-28 06:17:40'),
(179, 10, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-28 06:17:48'),
(180, 19, 'down', 'online', 1, '', '2026-02-28 06:18:19'),
(181, 18, 'down', 'online', 1, '', '2026-02-28 06:18:37'),
(182, 18, 'online', 'down', 1, 'Auto-detected: DNS resolution failed - Could not resolve host', '2026-02-28 06:20:43'),
(183, 19, 'online', 'down', 1, 'Auto-detected: HTTP 403 error', '2026-02-28 06:20:47'),
(184, 19, 'down', 'online', 1, '', '2026-02-28 06:29:17'),
(185, 18, 'down', 'online', 1, '', '2026-02-28 06:29:26'),
(186, 18, 'online', 'down', 1, 'Auto-detected: DNS resolution failed - Could not resolve host', '2026-02-28 06:29:50'),
(187, 19, 'online', 'down', 1, 'Auto-detected: HTTP 403 error', '2026-02-28 06:29:52'),
(188, 19, 'down', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"TESTING\" started.', '2026-02-28 06:30:23'),
(189, 18, 'down', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"TESTING\" started.', '2026-02-28 06:30:23'),
(190, 10, 'online', 'maintenance', 1, 'Auto-maintenance: Scheduled maintenance \"TESTING\" started.', '2026-02-28 06:30:23'),
(191, 19, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-28 06:30:51'),
(192, 18, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-28 06:31:00'),
(193, 10, 'maintenance', 'online', 1, 'Maintenance completed. System is ready to use.', '2026-02-28 06:31:11'),
(194, 18, 'online', 'down', 1, 'Auto-detected: DNS resolution failed - Could not resolve host', '2026-02-28 06:33:13'),
(195, 19, 'online', 'down', 1, 'Auto-detected: HTTP 403 error', '2026-02-28 06:33:15');

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `exclude_health_check` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = skip this system during automated health checks'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `systems`
--

INSERT INTO `systems` (`id`, `name`, `domain`, `logo`, `description`, `contact_number`, `status`, `created_at`, `updated_at`, `exclude_health_check`) VALUES
(2, 'Finance Portal', 'finance.company.com', NULL, 'Financial management and accounting system', '123', 'down', '2026-02-05 07:16:47', '2026-02-09 23:38:22', 0),
(3, 'Customer Portal', 'customers.company.com', NULL, 'Customer relationship management and support system', '123', 'down', '2026-02-05 07:16:47', '2026-02-09 23:38:27', 0),
(4, 'Inventory System', 'inventory.company.com', NULL, 'Warehouse and inventory tracking system.', '123', 'archived', '2026-02-05 07:16:47', '2026-02-09 23:38:34', 0),
(6, 'Test Add System', 'testadd.com', 'uploads/logos/logo_69857e5582a34.png', NULL, '123', 'down', '2026-02-06 02:41:28', '2026-02-18 01:50:39', 0),
(9, 'TESTTSSwww', 'TESTTT.COM', 'uploads/logos/logo_69857e4db4a17.png', 'TESTTTDESCWEQQWEQEQ', '672342', 'offline', '2026-02-06 05:28:19', '2026-02-10 00:40:39', 0),
(10, 'Canteen System!@#!!#!@#!123sssadadaaasaasdadaasdadaasdADAqeqqeqewqqeqwqwqweq', 'youtube.com', 'uploads/logos/logo_698a72264af69.png', 'qweqe3231321112318907!@#!#!!@#!!@#!@#!#', '1234', 'online', '2026-02-08 23:31:13', '2026-02-28 06:31:11', 0),
(11, 'Test system down 123', 'Testagainupdateddomain.com.ph', NULL, 'testing desc update', '5555', 'down', '2026-02-10 00:02:12', '2026-02-26 01:17:37', 0),
(12, 'TESTchangenote 2', 'TESTMAINTE.COM', NULL, 'TWETWRWWEWR', '11231', 'down', '2026-02-10 01:00:43', '2026-02-25 00:44:04', 0),
(15, 'testing Online 1 2 3', 'testind add morehan 2.2.2.2.2', NULL, 'testing 2.2.2.2.2', '123', 'down', '2026-02-12 02:47:27', '2026-02-27 23:09:36', 0),
(16, 'testing system online', 'reddiiiit.com.ph', NULL, 'testing only', '123', 'down', '2026-02-12 02:48:42', '2026-02-28 05:52:46', 0),
(17, 'testing online 4.4.4.4', '4.4.4.4.4', NULL, 'testing 4.4.4.4.4', '123', 'down', '2026-02-12 02:49:10', '2026-02-28 05:15:04', 0),
(18, 'TEST456', 'TEST456@.COM', 'uploads/logos/system_18_1772257844.png', 'TESTING456', '123222', 'down', '2026-02-28 05:48:53', '2026-02-28 06:33:13', 0),
(19, 'TESTING 789', 'TESTINGSYSTEM.COM', 'uploads/logos/logo_69a28432bd685.png', 'TESTING', '3333', 'down', '2026-02-28 05:59:14', '2026-02-28 06:33:15', 0);

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
-- AUTO_INCREMENT for table `email_recipients`
--
ALTER TABLE `email_recipients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `maintenance_schedules`
--
ALTER TABLE `maintenance_schedules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `status_logs`
--
ALTER TABLE `status_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=196;

--
-- AUTO_INCREMENT for table `systems`
--
ALTER TABLE `systems`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1000;

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
