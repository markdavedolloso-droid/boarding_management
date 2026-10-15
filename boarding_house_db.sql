-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 15, 2026 at 01:28 PM
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
-- Database: `boarding_house_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` bigint(20) UNSIGNED NOT NULL,
  `owner_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `activity` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `owner_id`, `user_id`, `activity`, `created_at`) VALUES
(1, 2, 2, 'Logged in', '2026-10-15 10:49:55'),
(2, 2, 2, 'Added room QA-101', '2026-10-15 10:50:29'),
(3, 2, 2, 'Added tenant QA Tenant', '2026-10-15 10:51:41'),
(4, 1, 1, 'Logged out', '2026-10-15 10:58:19'),
(5, 1, 1, 'Logged in', '2026-10-15 11:00:02'),
(6, 1, 1, 'Added tenant James Dave Manguilimutan', '2026-10-15 11:00:54'),
(7, 1, 1, 'Logged out', '2026-10-15 11:01:25'),
(8, 1, 3, 'Logged in', '2026-10-15 11:01:34'),
(9, 1, 3, 'Logged out', '2026-10-15 11:01:58'),
(10, 1, 1, 'Logged in', '2026-10-15 11:05:30'),
(11, 2, 2, 'Created rental contract #0', '2026-10-15 11:09:30'),
(12, 2, 2, 'Ended contract #1', '2026-10-15 11:10:11'),
(13, 1, 1, 'Downloaded a property backup', '2026-10-15 11:10:58'),
(14, 1, 1, 'Updated tenant #3', '2026-10-15 11:14:41'),
(15, 2, 2, 'Updated tenant #5', '2026-10-15 11:16:33'),
(16, 1, 1, 'Updated tenant #6', '2026-10-15 11:19:08'),
(17, 1, 1, 'Updated tenant #6', '2026-10-15 11:19:17'),
(18, 1, 1, 'Logged out', '2026-10-15 11:19:50'),
(19, 1, 1, 'Logged in', '2026-10-15 11:19:57');

-- --------------------------------------------------------

--
-- Table structure for table `app_meta`
--

CREATE TABLE `app_meta` (
  `meta_key` varchar(100) NOT NULL,
  `meta_value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `app_meta`
--

INSERT INTO `app_meta` (`meta_key`, `meta_value`) VALUES
('schema_version', '2');

-- --------------------------------------------------------

--
-- Table structure for table `app_users`
--

CREATE TABLE `app_users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('owner','tenant') NOT NULL DEFAULT 'owner',
  `status` enum('active','pending','disabled') NOT NULL DEFAULT 'active',
  `username` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `app_users`
--

INSERT INTO `app_users` (`user_id`, `full_name`, `email`, `password_hash`, `created_at`, `role`, `status`, `username`) VALUES
(1, 'Piañar, Mark Dave', 'markdave@gmail.com', '$2y$10$xQhzVpSX4CtBnFTRFsBRoOaehoX7x7MZmthM4Qr4GeUHtW0knDGdG', '2026-10-15 10:22:00', 'owner', 'active', 'user1'),
(2, 'StayWise QA', 'qaowner20261015@example.invalid', '$2y$10$Fh2i/4ahf67yZxmjQ7266eQqQQWpIaJYzOPTtRdKN.UQLGZsXj/la', '2026-10-15 10:49:42', 'owner', 'active', 'qaowner20261015'),
(3, 'James Dave', 'james@gmail.com', '$2y$10$OHSCucKLDq.kb64qesx2recFt7oV3sDh1k4sajua6euRg8PyaCsU.', '2026-10-15 10:59:07', 'tenant', 'active', 'jamesdave');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(10) UNSIGNED NOT NULL,
  `owner_id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `date_sent` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('unread','read') NOT NULL DEFAULT 'unread',
  `source_key` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(10) UNSIGNED NOT NULL,
  `owner_id` int(10) UNSIGNED NOT NULL,
  `contract_id` int(10) UNSIGNED NOT NULL,
  `payment_date` date NOT NULL,
  `billing_month` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_status` enum('paid','pending','overdue') NOT NULL DEFAULT 'paid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rental_contracts`
--

CREATE TABLE `rental_contracts` (
  `contract_id` int(10) UNSIGNED NOT NULL,
  `owner_id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `room_id` int(10) UNSIGNED NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `contract_status` enum('active','ended','cancelled') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `monthly_rent` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rental_contracts`
--

INSERT INTO `rental_contracts` (`contract_id`, `owner_id`, `tenant_id`, `room_id`, `start_date`, `end_date`, `contract_status`, `created_at`, `monthly_rent`) VALUES
(1, 2, 5, 1, '2026-10-15', '2026-10-15', 'ended', '2026-10-15 11:09:30', 2500.00);

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(10) UNSIGNED NOT NULL,
  `owner_id` int(10) UNSIGNED NOT NULL,
  `room_number` varchar(60) NOT NULL,
  `floor` int(11) NOT NULL DEFAULT 1,
  `capacity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `monthly_rent` decimal(10,2) NOT NULL,
  `status` enum('available','maintenance') NOT NULL DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`room_id`, `owner_id`, `room_number`, `floor`, `capacity`, `monthly_rent`, `status`, `created_at`) VALUES
(1, 2, 'QA-101', 1, 2, 2500.00, 'available', '2026-10-15 10:50:29');

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `address` varchar(150) NOT NULL,
  `date_registered` date NOT NULL DEFAULT curdate(),
  `owner_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`tenant_id`, `user_id`, `first_name`, `last_name`, `gender`, `contact_number`, `address`, `date_registered`, `owner_id`) VALUES
(1, NULL, 'Juan', 'Dela Cruz', 'Male', '09171234567', 'Cebu City', '2026-08-25', 1),
(3, NULL, 'Mark', 'Piañar', 'Male', '09123451243', 'Taga Sapa', '2026-08-25', 1),
(4, NULL, 'Boss', 'H', 'Female', '06544654165', 'Bogo', '2026-08-25', 1),
(5, NULL, 'QA', 'Tenant', 'Male', '09170000000', 'QA Test Address', '2026-10-15', 2),
(6, 3, 'James Dave', 'Manguilimutan', 'Male', '09475641324', 'Laray', '2026-10-15', 1);

-- --------------------------------------------------------

--
-- Table structure for table `utility_bills`
--

CREATE TABLE `utility_bills` (
  `bill_id` int(10) UNSIGNED NOT NULL,
  `owner_id` int(10) UNSIGNED NOT NULL,
  `contract_id` int(10) UNSIGNED NOT NULL,
  `electricity_bill` decimal(10,2) NOT NULL DEFAULT 0.00,
  `water_bill` decimal(10,2) NOT NULL DEFAULT 0.00,
  `billing_month` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `due_date` date DEFAULT NULL,
  `bill_status` enum('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_activity_owner_date` (`owner_id`,`created_at`),
  ADD KEY `idx_activity_user` (`user_id`);

--
-- Indexes for table `app_meta`
--
ALTER TABLE `app_meta`
  ADD PRIMARY KEY (`meta_key`);

--
-- Indexes for table `app_users`
--
ALTER TABLE `app_users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD UNIQUE KEY `source_key` (`source_key`),
  ADD KEY `idx_notifications_tenant` (`tenant_id`,`status`,`date_sent`),
  ADD KEY `idx_notifications_owner` (`owner_id`,`date_sent`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `idx_payments_owner_date` (`owner_id`,`payment_date`),
  ADD KEY `idx_payments_contract_month` (`contract_id`,`billing_month`);

--
-- Indexes for table `rental_contracts`
--
ALTER TABLE `rental_contracts`
  ADD PRIMARY KEY (`contract_id`),
  ADD KEY `idx_contracts_owner_status` (`owner_id`,`contract_status`,`start_date`,`end_date`),
  ADD KEY `idx_contracts_tenant` (`tenant_id`),
  ADD KEY `idx_contracts_room` (`room_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD UNIQUE KEY `uq_rooms_owner_number` (`owner_id`,`room_number`),
  ADD KEY `idx_rooms_owner_status` (`owner_id`,`status`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`tenant_id`),
  ADD UNIQUE KEY `uq_tenants_user_id` (`user_id`);

--
-- Indexes for table `utility_bills`
--
ALTER TABLE `utility_bills`
  ADD PRIMARY KEY (`bill_id`),
  ADD UNIQUE KEY `uq_bills_contract_month` (`contract_id`,`billing_month`),
  ADD KEY `idx_bills_owner_status` (`owner_id`,`bill_status`,`due_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `app_users`
--
ALTER TABLE `app_users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rental_contracts`
--
ALTER TABLE `rental_contracts`
  MODIFY `contract_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `tenant_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `utility_bills`
--
ALTER TABLE `utility_bills`
  MODIFY `bill_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
