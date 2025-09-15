-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 14, 2025 at 01:06 AM
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
-- Database: `bhps`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','tenant') NOT NULL,
  UNIQUE KEY `uniq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`) VALUES
(1, 'admin', '0192023a7bbd73250516f069df18b500', 'admin'),
(17, 'edgen', '$2y$10$E4jhBkPPmhKsJs4v1qf4NOBVDay4Aa2X40qBmj4duBa8mXltuWzke', 'tenant'),
(18, 'carla', '$2y$10$bCQytG2mAhusItz1aY/h2.jUN9rgXFkBSpJk8N9AI8fVNz1TRKltG', 'tenant'),
(20, 'jarvie', '$2y$10$95PYmkXPbwlFtnYd97vSL.q/e.XsKUfE0JZceqLThHD7tNV9sfAcC', 'tenant'),
(21, 'jan', '$2y$10$LqLup.FuqR85ISx6.SAoE.1zi9bRJY50MtRCiW0NTSVyoNjCfhE5.', 'tenant'),
(22, 'harold', '$2y$10$eDOZwSiYS5Ie.J.E2qsMG.e4.rF/ynq1joTP3Y.kD5U2pV77Hcb6G', 'tenant'),
(23, 'makoy', '$2y$10$Eq0m.Q6HJzKVf.wSYpcSme.SS5Meg/CTIhArnjIu07ATvWr1/dWD6', 'tenant'),
(24, 'jang', '$2y$10$S8AHMCjo6bc90pX90qh.leFGJfd1dUHluxGqUeIQu5uuU.5m0DEkK', 'tenant');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `month_paid` varchar(7) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `status` enum('Paid','Partial','Unpaid') NOT NULL DEFAULT 'Unpaid',
  `remarks` text DEFAULT NULL,
  `date_paid` datetime NOT NULL DEFAULT current_timestamp(),
  `received_by` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `tenant_id`, `month_paid`, `amount`, `payment_method`, `status`, `remarks`, `date_paid`) VALUES
(45, 17, '2025-08', 700.00, '', 'Paid', NULL, '2025-08-13 23:15:11'),
(46, 17, '2025-09', 700.00, '', 'Paid', NULL, '2025-08-13 23:15:29'),
(47, 17, '2025-10', 0.00, '', 'Unpaid', NULL, '2025-08-13 23:14:53'),
(48, 17, '2025-11', 0.00, '', 'Unpaid', NULL, '2025-08-13 23:14:53'),
(49, 17, '2025-12', 0.00, '', 'Unpaid', NULL, '2025-08-13 23:14:53'),
(50, 17, '2026-01', 0.00, '', 'Unpaid', NULL, '2025-08-13 23:14:53'),
(51, 17, '2026-02', 0.00, '', 'Unpaid', NULL, '2025-08-13 23:14:53'),
(52, 17, '2026-03', 0.00, '', 'Unpaid', NULL, '2025-08-13 23:14:53'),
(53, 17, '2026-04', 0.00, '', 'Unpaid', NULL, '2025-08-13 23:14:53'),
(54, 17, '2026-05', 0.00, '', 'Unpaid', NULL, '2025-08-13 23:14:53'),
(55, 17, '2026-06', 0.00, '', 'Unpaid', NULL, '2025-08-13 23:14:53'),
(56, 17, '2026-07', 0.00, '', 'Unpaid', NULL, '2025-08-13 23:14:53'),
(57, 18, '2025-08', 700.00, '', 'Paid', NULL, '2025-08-13 23:23:03'),
(58, 18, '2025-09', 700.00, '', 'Paid', NULL, '2025-08-13 23:51:10'),
(59, 18, '2025-10', 0.00, '', 'Unpaid', NULL, '2025-08-13 23:22:18'),
(60, 18, '2025-11', 0.00, '', 'Unpaid', NULL, '2025-08-13 23:22:18'),
(61, 18, '2025-12', 0.00, '', 'Unpaid', NULL, '2025-08-13 23:22:18'),
(62, 18, '2026-01', 0.00, '', 'Unpaid', NULL, '2025-08-13 23:22:18'),
(63, 18, '2026-02', 0.00, '', 'Unpaid', NULL, '2025-08-13 23:22:18'),
(64, 18, '2026-03', 0.00, '', 'Unpaid', NULL, '2025-08-13 23:22:18'),
(65, 18, '2026-04', 0.00, '', 'Unpaid', NULL, '2025-08-13 23:22:18'),
(66, 18, '2026-05', 0.00, '', 'Unpaid', NULL, '2025-08-13 23:22:18'),
(67, 18, '2026-06', 0.00, '', 'Unpaid', NULL, '2025-08-13 23:22:18'),
(68, 18, '2026-07', 0.00, '', 'Unpaid', NULL, '2025-08-13 23:22:18'),
(81, 20, '2025-08', 700.00, '', 'Paid', NULL, '2025-08-14 00:02:45'),
(82, 20, '2025-09', 0.00, '', 'Unpaid', NULL, '2025-08-14 00:01:53'),
(83, 20, '2025-10', 0.00, '', 'Unpaid', NULL, '2025-08-14 00:01:53'),
(84, 20, '2025-11', 0.00, '', 'Unpaid', NULL, '2025-08-14 00:01:53'),
(85, 20, '2025-12', 0.00, '', 'Unpaid', NULL, '2025-08-14 00:01:53'),
(86, 20, '2026-01', 0.00, '', 'Unpaid', NULL, '2025-08-14 00:01:53'),
(87, 20, '2026-02', 0.00, '', 'Unpaid', NULL, '2025-08-14 00:01:53'),
(88, 20, '2026-03', 0.00, '', 'Unpaid', NULL, '2025-08-14 00:01:53'),
(89, 20, '2026-04', 0.00, '', 'Unpaid', NULL, '2025-08-14 00:01:53'),
(90, 20, '2026-05', 0.00, '', 'Unpaid', NULL, '2025-08-14 00:01:53'),
(91, 20, '2026-06', 0.00, '', 'Unpaid', NULL, '2025-08-14 00:01:53'),
(92, 20, '2026-07', 0.00, '', 'Unpaid', NULL, '2025-08-14 00:01:53'),
(93, 21, '2025-08', 1000.00, '', 'Paid', NULL, '2025-08-14 06:41:50'),
(94, 21, '2025-09', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:03:35'),
(95, 21, '2025-10', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:03:35'),
(96, 21, '2025-11', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:03:35'),
(97, 21, '2025-12', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:03:35'),
(98, 21, '2026-01', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:03:35'),
(99, 21, '2026-02', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:03:35'),
(100, 21, '2026-03', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:03:35'),
(101, 21, '2026-04', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:03:35'),
(102, 21, '2026-05', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:03:35'),
(103, 21, '2026-06', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:03:35'),
(104, 21, '2026-07', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:03:35'),
(105, 22, '2025-08', 1400.00, '', 'Paid', NULL, '2025-08-14 06:22:56'),
(106, 22, '2025-09', 1000.00, '', 'Partial', NULL, '2025-08-14 06:23:16'),
(107, 22, '2025-10', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:11:07'),
(108, 22, '2025-11', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:11:07'),
(109, 22, '2025-12', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:11:07'),
(110, 22, '2026-01', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:11:07'),
(111, 22, '2026-02', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:11:07'),
(112, 22, '2026-03', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:11:07'),
(113, 22, '2026-04', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:11:07'),
(114, 22, '2026-05', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:11:07'),
(115, 22, '2026-06', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:11:07'),
(116, 22, '2026-07', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:11:07'),
(117, 23, '2025-08', 1000.00, '', 'Paid', NULL, '2025-08-14 06:51:53'),
(118, 23, '2025-09', 500.00, '', 'Partial', NULL, '2025-08-14 06:51:53'),
(119, 23, '2025-10', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:49:23'),
(120, 23, '2025-11', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:49:23'),
(121, 23, '2025-12', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:49:23'),
(122, 23, '2026-01', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:49:23'),
(123, 23, '2026-02', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:49:23'),
(124, 23, '2026-03', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:49:23'),
(125, 23, '2026-04', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:49:23'),
(126, 23, '2026-05', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:49:23'),
(127, 23, '2026-06', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:49:23'),
(128, 23, '2026-07', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:49:23'),
(129, 24, '2025-08', 1500.00, '', 'Paid', NULL, '2025-08-14 06:53:49'),
(130, 24, '2025-09', 1500.00, '', 'Paid', NULL, '2025-08-14 06:53:49'),
(131, 24, '2025-10', 1200.00, '', 'Partial', NULL, '2025-08-14 06:53:49'),
(132, 24, '2025-11', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:53:08'),
(133, 24, '2025-12', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:53:08'),
(134, 24, '2026-01', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:53:08'),
(135, 24, '2026-02', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:53:08'),
(136, 24, '2026-03', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:53:08'),
(137, 24, '2026-04', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:53:08'),
(138, 24, '2026-05', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:53:08'),
(139, 24, '2026-06', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:53:08'),
(140, 24, '2026-07', 0.00, '', 'Unpaid', NULL, '2025-08-14 06:53:08');

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `room_number` varchar(10) NOT NULL,
  `contact` varchar(15) DEFAULT NULL,
  `start_date` date NOT NULL,
  `rent_amount` decimal(10,2) NOT NULL,
  `address` varchar(255) NOT NULL DEFAULT '',
  `monthly_rent` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`tenant_id`, `name`, `room_number`, `contact`, `start_date`, `rent_amount`, `monthly_rent`) VALUES
(17, 'edgen', '001', '09092833358', '2025-08-08', 700.00, 0.00),
(18, 'carla', '002', '09324299232', '2025-08-05', 700.00, 0.00),
(20, 'jarvie', '003', '09328238293', '2025-08-14', 700.00, 0.00),
(21, 'Janklye', '004', '09092133242', '2025-08-14', 1000.00, 0.00),
(22, 'harold ', '005', '092392345231', '2025-08-11', 1400.00, 0.00),
(23, 'makoy', '006', '09322482452', '2025-08-14', 1000.00, 0.00),
(24, 'kuya jang', '007', '09342424252', '2025-08-15', 1500.00, 0.00);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `idx_payments_tenant_month` (`tenant_id`,`month_paid`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`tenant_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=141;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `tenant_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
  