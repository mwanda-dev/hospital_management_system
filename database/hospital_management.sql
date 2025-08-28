-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 19, 2025 at 02:55 PM
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
-- Database: `hospital_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `admissions`
--

CREATE TABLE `admissions` (
  `admission_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `bed_id` int(11) NOT NULL,
  `admitting_doctor_id` int(11) NOT NULL,
  `admission_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `discharge_date` timestamp NULL DEFAULT NULL,
  `reason` text NOT NULL,
  `status` enum('admitted','discharged','transferred') DEFAULT 'admitted',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `status` enum('scheduled','completed','canceled','no_show') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `patient_id`, `doctor_id`, `appointment_date`, `start_time`, `end_time`, `purpose`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 10, 8, '2025-07-19', '09:00:00', '09:30:00', 'coz he wants', 'scheduled', 'wants to see wassup', 1, '2025-07-19 12:22:50', '2025-07-19 12:22:50'),
(2, 7, 12, '2025-07-19', '09:00:00', '09:30:00', 'fibroid ', 'scheduled', 'need root removal', 1, '2025-07-19 12:24:43', '2025-07-19 12:24:43');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `table_affected` varchar(50) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `action_timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `beds`
--

CREATE TABLE `beds` (
  `bed_id` int(11) NOT NULL,
  `ward_id` int(11) NOT NULL,
  `bed_number` varchar(10) NOT NULL,
  `status` enum('available','occupied','maintenance') DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `billing`
--

CREATE TABLE `billing` (
  `invoice_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','partial','paid','overdue','canceled') DEFAULT 'pending',
  `payment_method` enum('cash','credit_card','mobile_money','bank_transfer','insurance') DEFAULT NULL,
  `payment_details` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `billing_items`
--

CREATE TABLE `billing_items` (
  `item_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `amount` decimal(10,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `item_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `item_type` enum('medication','supply','equipment') NOT NULL,
  `description` text DEFAULT NULL,
  `quantity_in_stock` int(11) NOT NULL DEFAULT 0,
  `unit_of_measure` varchar(20) DEFAULT NULL,
  `reorder_level` int(11) DEFAULT 10,
  `cost_per_unit` decimal(10,2) DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `last_restocked` date DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medical_records`
--

CREATE TABLE `medical_records` (
  `record_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `record_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `record_type` enum('diagnosis','treatment','lab_result','prescription','vital_signs','progress_note') NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `diagnosis_code` varchar(20) DEFAULT NULL,
  `treatment_plan` text DEFAULT NULL,
  `prescribed_medication` text DEFAULT NULL,
  `lab_results` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `patient_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female') NOT NULL,
  `blood_type` enum('Unknown','A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `insurance_provider` varchar(100) DEFAULT NULL,
  `insurance_policy_number` varchar(50) DEFAULT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`patient_id`, `first_name`, `last_name`, `date_of_birth`, `gender`, `blood_type`, `phone`, `email`, `address`, `emergency_contact_name`, `emergency_contact_phone`, `insurance_provider`, `insurance_policy_number`, `registration_date`, `updated_at`) VALUES
(1, 'Alice', 'Mwansa', '1990-05-12', 'female', 'O+', '+260771111111', NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-19 10:19:30', '2025-07-19 10:19:30'),
(2, 'Brian', 'Zimba', '1985-03-25', 'male', 'A+', '+260772222222', NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-19 10:19:30', '2025-07-19 10:19:30'),
(3, 'Cathy', 'Tembo', '1992-07-01', 'female', 'B+', '+260773333333', NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-19 10:19:30', '2025-07-19 10:19:30'),
(4, 'David', 'Lungu', '1978-11-30', 'male', 'AB+', '+260774444444', NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-19 10:19:30', '2025-07-19 10:19:30'),
(5, 'Emma', 'Phiri', '2000-01-15', 'female', 'O-', '+260775555555', NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-19 10:19:30', '2025-07-19 10:19:30'),
(6, 'Frank', 'Sakala', '1989-09-09', 'male', 'A-', '+260776666666', NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-19 10:19:30', '2025-07-19 10:19:30'),
(7, 'Grace', 'Ngoma', '1995-04-20', 'female', 'B-', '+260777777777', NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-19 10:19:30', '2025-07-19 10:19:30'),
(8, 'Henry', 'Mumba', '1983-02-02', 'male', 'O+', '+260778888888', NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-19 10:19:30', '2025-07-19 10:19:30'),
(9, 'Irene', 'Chileshe', '1998-08-08', 'female', 'AB-', '+260779999999', NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-19 10:19:30', '2025-07-19 10:19:30'),
(10, 'James', 'Banda', '1975-12-12', 'male', 'A+', '+260770000000', NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-19 10:19:30', '2025-07-19 10:19:30');

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `prescription_id` int(11) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `prescription_date` date NOT NULL,
  `status` enum('active','completed','canceled') DEFAULT 'active',
  `instructions` text DEFAULT NULL,
  `refills_remaining` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescription_items`
--

CREATE TABLE `prescription_items` (
  `item_id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `medication_name` varchar(100) NOT NULL,
  `dosage` varchar(50) NOT NULL,
  `frequency` varchar(50) NOT NULL,
  `duration` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `role` enum('admin','doctor','nurse','receptionist','lab_technician','pharmacist') NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `status` enum('active','inactive','on_leave') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password_hash`, `email`, `first_name`, `last_name`, `role`, `specialization`, `phone`, `address`, `hire_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@hospital.com', 'System', 'Administrator', 'admin', NULL, '+260123456789', NULL, '2025-07-18', 'active', '2025-07-18 12:09:38', '2025-07-18 12:09:38'),
(2, 'dr.smith', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dr.smith@hospital.com', 'John', 'Smith', 'doctor', 'Cardiology', '+260987654321', NULL, '2023-07-18', 'active', '2025-07-18 12:09:38', '2025-07-18 12:09:38'),
(3, 'lab.tech1', 'password', 'lab1@hospital.com', 'Lydia', 'Mutale', 'lab_technician', NULL, '+260711000001', NULL, '2024-01-10', 'active', '2025-07-19 10:19:47', '2025-07-19 10:19:47'),
(4, 'lab.tech2', 'password', 'lab2@hospital.com', 'Kelvin', 'Zimba', 'lab_technician', NULL, '+260711000002', NULL, '2024-01-11', 'active', '2025-07-19 10:19:47', '2025-07-19 10:19:47'),
(5, 'lab.tech3', 'password', 'lab3@hospital.com', 'Memory', 'Phiri', 'lab_technician', NULL, '+260711000003', NULL, '2024-01-12', 'active', '2025-07-19 10:19:47', '2025-07-19 10:19:47'),
(6, 'lab.tech4', 'password', 'lab4@hospital.com', 'Noah', 'Kunda', 'lab_technician', NULL, '+260711000004', NULL, '2024-01-13', 'active', '2025-07-19 10:19:47', '2025-07-19 10:19:47'),
(7, 'lab.tech5', 'password', 'lab5@hospital.com', 'Olivia', 'Bwalya', 'lab_technician', NULL, '+260711000005', NULL, '2024-01-14', 'active', '2025-07-19 10:19:47', '2025-07-19 10:19:47'),
(8, 'dr.chileshe', 'password', 'drchileshe@hospital.com', 'Peter', 'Chileshe', 'doctor', 'Pediatrics', '+260721000001', NULL, '2023-08-01', 'active', '2025-07-19 10:20:04', '2025-07-19 10:20:04'),
(9, 'dr.kapeya', 'password', 'drkapeya@hospital.com', 'Martha', 'Kapeya', 'doctor', 'Neurology', '+260721000002', NULL, '2023-08-02', 'active', '2025-07-19 10:20:04', '2025-07-19 10:20:04'),
(10, 'dr.tembo', 'password', 'drtembo@hospital.com', 'Alex', 'Tembo', 'doctor', 'Orthopedics', '+260721000003', NULL, '2023-08-03', 'active', '2025-07-19 10:20:04', '2025-07-19 10:20:04'),
(11, 'dr.sampa', 'password', 'drsampa@hospital.com', 'Lucy', 'Sampa', 'doctor', 'Dermatology', '+260721000004', NULL, '2023-08-04', 'active', '2025-07-19 10:20:04', '2025-07-19 10:20:04'),
(12, 'dr.kasonde', 'password', 'drkasonde@hospital.com', 'Chris', 'Kasonde', 'doctor', 'Surgery', '+260721000005', NULL, '2023-08-05', 'active', '2025-07-19 10:20:04', '2025-07-19 10:20:04'),
(13, 'reception1', 'password', 'reception@hospital.com', 'Sandra', 'Mwape', 'receptionist', NULL, '+260731000001', NULL, '2024-02-01', 'active', '2025-07-19 10:20:53', '2025-07-19 10:20:53'),
(14, 'pharma1', 'password', 'pharma1@hospital.com', 'George', 'Muleya', 'pharmacist', NULL, '+260741000001', NULL, '2024-03-01', 'active', '2025-07-19 10:21:17', '2025-07-19 10:21:17'),
(15, 'pharma2', 'password', 'pharma2@hospital.com', 'Agnes', 'Kabwe', 'pharmacist', NULL, '+260741000002', NULL, '2024-03-02', 'active', '2025-07-19 10:21:17', '2025-07-19 10:21:17');

-- --------------------------------------------------------

--
-- Table structure for table `wards`
--

CREATE TABLE `wards` (
  `ward_id` int(11) NOT NULL,
  `ward_name` varchar(50) NOT NULL,
  `ward_type` enum('general','icu','maternity','pediatric','surgical') NOT NULL,
  `capacity` int(11) NOT NULL,
  `current_occupancy` int(11) DEFAULT 0,
  `charge_per_day` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wards`
--

INSERT INTO `wards` (`ward_id`, `ward_name`, `ward_type`, `capacity`, `current_occupancy`, `charge_per_day`) VALUES
(1, 'Emergency Ward', 'general', 10, 0, 10.00);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admissions`
--
ALTER TABLE `admissions`
  ADD PRIMARY KEY (`admission_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `bed_id` (`bed_id`),
  ADD KEY `admitting_doctor_id` (`admitting_doctor_id`),
  ADD KEY `status` (`status`,`admission_date`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `appointment_date` (`appointment_date`,`status`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `beds`
--
ALTER TABLE `beds`
  ADD PRIMARY KEY (`bed_id`),
  ADD UNIQUE KEY `ward_id` (`ward_id`,`bed_number`);

--
-- Indexes for table `billing`
--
ALTER TABLE `billing`
  ADD PRIMARY KEY (`invoice_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `invoice_date` (`invoice_date`,`status`);

--
-- Indexes for table `billing_items`
--
ALTER TABLE `billing_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `item_name` (`item_name`,`item_type`);

--
-- Indexes for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `patient_id` (`patient_id`,`record_date`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`patient_id`),
  ADD KEY `last_name` (`last_name`,`first_name`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`prescription_id`),
  ADD KEY `record_id` (`record_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `prescription_id` (`prescription_id`),
  ADD KEY `inventory_id` (`inventory_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `wards`
--
ALTER TABLE `wards`
  ADD PRIMARY KEY (`ward_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admissions`
--
ALTER TABLE `admissions`
  MODIFY `admission_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `beds`
--
ALTER TABLE `beds`
  MODIFY `bed_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `invoice_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `billing_items`
--
ALTER TABLE `billing_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medical_records`
--
ALTER TABLE `medical_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `prescription_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescription_items`
--
ALTER TABLE `prescription_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `wards`
--
ALTER TABLE `wards`
  MODIFY `ward_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admissions`
--
ALTER TABLE `admissions`
  ADD CONSTRAINT `admissions_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `admissions_ibfk_2` FOREIGN KEY (`bed_id`) REFERENCES `beds` (`bed_id`),
  ADD CONSTRAINT `admissions_ibfk_3` FOREIGN KEY (`admitting_doctor_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `beds`
--
ALTER TABLE `beds`
  ADD CONSTRAINT `beds_ibfk_1` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`ward_id`);

--
-- Constraints for table `billing`
--
ALTER TABLE `billing`
  ADD CONSTRAINT `billing_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `billing_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `billing_items`
--
ALTER TABLE `billing_items`
  ADD CONSTRAINT `billing_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `billing` (`invoice_id`) ON DELETE CASCADE;

--
-- Constraints for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD CONSTRAINT `medical_records_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `medical_records_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `medical_records` (`record_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `prescriptions_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD CONSTRAINT `prescription_items_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`prescription_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescription_items_ibfk_2` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`item_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
