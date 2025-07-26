-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 26, 2025 at 07:37 AM
-- Server version: 8.0.30
-- PHP Version: 8.3.15

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `absen_yati1`
--

-- --------------------------------------------------------

--
-- Table structure for table `absensi`
--

CREATE TABLE `absensi` (
  `id` int NOT NULL,
  `id_siswa` int NOT NULL,
  `waktu_masuk` datetime DEFAULT NULL,
  `waktu_keluar` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `absensi`
--

INSERT INTO `absensi` (`id`, `id_siswa`, `waktu_masuk`, `waktu_keluar`) VALUES
(1, 6, '2025-07-23 16:44:31', NULL),
(2, 2, '2025-07-23 16:44:38', NULL),
(3, 1, '2025-07-23 16:44:43', NULL),
(4, 2, '2025-07-24 09:18:39', NULL),
(5, 1, '2025-07-24 09:19:12', NULL),
(6, 6, '2025-07-24 09:19:19', NULL),
(7, 7, '2025-07-24 09:20:33', NULL),
(8, 8, '2025-07-25 18:49:52', '2025-07-25 18:49:57'),
(9, 9, '2025-07-25 18:50:35', '2025-07-25 18:50:56'),
(10, 10, '2025-07-25 18:56:55', '2025-07-25 18:57:48'),
(11, 2, '2025-07-26 14:27:37', '2025-07-26 14:28:28');

-- --------------------------------------------------------

--
-- Table structure for table `pengaturan_absensi`
--

CREATE TABLE `pengaturan_absensi` (
  `id` int NOT NULL DEFAULT '1',
  `waktu_masuk_mulai` time NOT NULL,
  `waktu_masuk_akhir` time NOT NULL,
  `waktu_keluar_mulai` time NOT NULL,
  `waktu_keluar_akhir` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pengaturan_absensi`
--

INSERT INTO `pengaturan_absensi` (`id`, `waktu_masuk_mulai`, `waktu_masuk_akhir`, `waktu_keluar_mulai`, `waktu_keluar_akhir`) VALUES
(1, '07:00:00', '09:00:00', '12:00:00', '20:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `siswa`
--

CREATE TABLE `siswa` (
  `id` int NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `kelas` varchar(50) NOT NULL,
  `uid_rfid` varchar(50) NOT NULL,
  `telegram_chat_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `siswa`
--

INSERT INTO `siswa` (`id`, `nama_lengkap`, `kelas`, `uid_rfid`, `telegram_chat_id`, `created_at`) VALUES
(1, 'syahril', '7', '0092368391', '215124', '2025-07-23 09:32:43'),
(2, '4', '555t', '0092411326', '66666', '2025-07-23 09:34:00'),
(5, '9', '79', '00924113260092346914', '', '2025-07-23 09:40:27'),
(6, '2412', '214', '0092346914', '', '2025-07-23 09:40:46'),
(7, '786', '8', '0092358884', '', '2025-07-24 02:20:29'),
(8, 'vihjh', '9', '0092280586', '', '2025-07-25 11:49:37'),
(9, 'jnngfchg', '70', '0092254484', '', '2025-07-25 11:50:31'),
(10, 'bjbl', '18', '0092212364', '', '2025-07-25 11:56:30');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_siswa` (`id_siswa`);

--
-- Indexes for table `pengaturan_absensi`
--
ALTER TABLE `pengaturan_absensi`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uid_rfid` (`uid_rfid`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `absensi`
--
ALTER TABLE `absensi`
  ADD CONSTRAINT `absensi_ibfk_1` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
