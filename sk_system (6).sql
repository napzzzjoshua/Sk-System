-- phpMyAdmin SQL Dump
-- version 5.0.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 12, 2026 at 06:30 PM
-- Server version: 10.4.14-MariaDB
-- PHP Version: 7.2.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sk_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `template_used` varchar(100) NOT NULL,
  `status` enum('Draft','Published','Scheduled','Archived') NOT NULL DEFAULT 'Draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `published_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`announcement_id`, `user_id`, `title`, `content`, `template_used`, `status`, `created_at`, `published_at`) VALUES
(1, 0, 'KK ASSEMBLY', 'basta punta kayo ha', 'General Update', 'Published', '2025-10-05 14:22:04', '2025-10-05 16:22:04'),
(2, 0, 'KK ASSEMBLY', 'basta punta kayo ha', 'General Update', 'Published', '2025-10-05 14:23:10', '2025-10-05 16:23:10'),
(3, 0, 'TYPHOON NANDO', 'Mag ingat sa baha gaon', 'Urgent Notice / Advisory', 'Scheduled', '2025-10-05 14:28:56', '2025-10-06 22:28:00'),
(4, 0, 'jjkknjjnk', 'jjkknjjnk', 'Community Event', 'Draft', '2025-10-09 09:17:03', NULL),
(5, 0, 'jjkknjjnk', 'jjkknjjnk', 'Community Event', 'Draft', '2025-10-09 09:21:06', NULL),
(6, 0, 'HELLO', 'HELLO', 'Blank Template (Start from Scratch)', 'Published', '2025-10-09 09:22:47', '2025-10-09 17:22:47'),
(7, 0, 'HELLO', 'HELLO', 'Blank Template (Start from Scratch)', 'Published', '2025-10-09 09:24:48', '2025-10-09 17:24:48'),
(8, 0, 'HELLO', 'HELLO', 'Blank Template (Start from Scratch)', 'Published', '2025-10-09 09:25:08', '2025-10-09 17:25:08'),
(9, 0, 'HELLO', 'HELLO', 'Blank Template (Start from Scratch)', 'Published', '2025-10-09 09:28:45', '2025-10-09 17:28:45'),
(10, 0, 'HELLO', 'HELLO', 'Blank Template (Start from Scratch)', 'Published', '2025-10-09 09:28:59', '2025-10-09 17:28:59'),
(11, 0, 'HELLO', 'HELLO', 'Blank Template (Start from Scratch)', 'Published', '2025-10-09 09:32:17', '2025-10-09 17:32:17'),
(12, 0, 'HELLO', 'HELLO', 'Blank Template (Start from Scratch)', 'Published', '2025-10-09 09:37:01', '2025-10-09 17:37:01'),
(13, 0, 'KK ASSEMBLY', 'cknsdjasmd;asl,;sa,dl;,sad,sa;ldlsaknd', 'Blank Template (Start from Scratch)', 'Scheduled', '2025-10-09 09:45:03', '2025-10-10 15:00:00'),
(14, 0, 'NOTICE', 'xmnaskdnsadklsmnkldan', 'General Announcement', 'Scheduled', '2025-10-09 09:45:57', '2025-10-20 15:00:00'),
(15, 0, 'NOTICE 2', 'sndkladsadasdka;lsd', 'General Announcement', 'Published', '2025-10-09 09:47:44', '2025-10-09 17:47:44'),
(16, 0, 'NOTICE 3', 'kansdklsadlkaskl', 'General Announcement', 'Published', '2025-10-09 09:49:01', '2025-10-25 14:00:00'),
(17, 0, 'NOTICE 4', 'aksdjksajdsadklsa', 'General Announcement', 'Scheduled', '2025-10-09 09:54:12', '2025-10-26 01:00:00'),
(18, 1, 'SK LIGA 2026', '@ GAT YANTOK MJAYJAY LAGUNA\nEveryone will participate in this project', 'General Announcement', 'Published', '2026-01-03 08:07:48', '2026-01-03 16:07:48'),
(19, 1, 'SK LIGA', 'LIGA FOR A CAUSE @ GAT YANTOK MAJAYJAY COURTS', 'General Announcement', 'Published', '2026-01-04 07:17:42', '2026-01-04 15:17:42');

-- --------------------------------------------------------

--
-- Table structure for table `announcement_responses`
--

CREATE TABLE `announcement_responses` (
  `id` int(11) NOT NULL,
  `announcement_title` varchar(255) NOT NULL,
  `announcement_date` varchar(50) NOT NULL,
  `announcement_content` text NOT NULL,
  `response` text NOT NULL,
  `responder_name` varchar(255) NOT NULL,
  `responder_id` int(11) DEFAULT NULL,
  `response_date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `announcement_responses`
--

INSERT INTO `announcement_responses` (`id`, `announcement_title`, `announcement_date`, `announcement_content`, `response`, `responder_name`, `responder_id`, `response_date`) VALUES
(1, 'SK LIGA 2026', 'January 3, 2026', '@ GAT YANTOK MJAYJAY LAGUNA\nEveryone will participate in this project', 'gege', 'Heidi Bomuel', 53, '2026-01-03 23:43:17'),
(2, 'NOTICE 2', 'October 9, 2025', 'sndkladsadasdka;lsd', 'gege', 'Heidi Bomuel', 53, '2026-01-03 23:43:47'),
(3, 'SK LIGA 2026', 'January 3, 2026', '@ GAT YANTOK MJAYJAY LAGUNA\nEveryone will participate in this project', 'yes po', 'Heidi Bomuel', 53, '2026-01-03 23:44:53'),
(4, 'NOTICE 2', 'October 9, 2025', 'sndkladsadasdka;lsd', 'ok', 'Heidi Bomuel', 53, '2026-01-03 23:45:58'),
(5, 'HELLO', 'October 9, 2025', 'HELLO', 'ssssss', 'Heidi Bomuel', 53, '2026-01-03 23:47:25'),
(6, 'HELLO', 'October 9, 2025', 'HELLO', 'knasdlas', 'Heidi Bomuel', 53, '2026-01-03 23:48:29'),
(7, 'HELLO', 'October 9, 2025', 'HELLO', 'akldnaskld', 'Heidi Bomuel', 53, '2026-01-03 23:48:45'),
(8, 'HELLO', 'October 9, 2025', 'HELLO', 'doajdosajda', 'Heidi Bomuel', 53, '2026-01-03 23:50:03'),
(9, 'HELLO', 'October 9, 2025', 'HELLO', 'doajdosajda', 'Heidi Bomuel', 53, '2026-01-03 23:50:04'),
(10, 'HELLO', 'October 9, 2025', 'HELLO', 'doajdosajda', 'Heidi Bomuel', 53, '2026-01-03 23:50:05'),
(11, 'HELLO', 'October 9, 2025', 'HELLO', 'doajdosajda', 'Heidi Bomuel', 53, '2026-01-03 23:50:05'),
(12, 'HELLO', 'October 9, 2025', 'HELLO', 'doajdosajda', 'Heidi Bomuel', 53, '2026-01-03 23:50:05'),
(13, 'HELLO', 'October 9, 2025', 'HELLO', 'doajdosajda', 'Heidi Bomuel', 53, '2026-01-03 23:50:05'),
(14, 'HELLO', 'October 9, 2025', 'HELLO', 'doajdosajda', 'Heidi Bomuel', 53, '2026-01-03 23:50:05'),
(15, 'HELLO', 'October 9, 2025', 'HELLO', 'doajdosajda', 'Heidi Bomuel', 53, '2026-01-03 23:50:06'),
(16, 'HELLO', 'October 9, 2025', 'HELLO', 'doajdosajda', 'Heidi Bomuel', 53, '2026-01-03 23:50:06'),
(17, 'HELLO', 'October 9, 2025', 'HELLO', 'doajdosajda', 'Heidi Bomuel', 53, '2026-01-03 23:50:06'),
(18, 'HELLO', 'October 9, 2025', 'HELLO', 'doajdosajda', 'Heidi Bomuel', 53, '2026-01-03 23:50:06'),
(19, 'HELLO', 'October 9, 2025', 'HELLO', 'doajdosajda', 'Heidi Bomuel', 53, '2026-01-03 23:50:06'),
(20, 'HELLO', 'October 9, 2025', 'HELLO', 'doajdosajda', 'Heidi Bomuel', 53, '2026-01-03 23:50:06'),
(21, 'HELLO', 'October 9, 2025', 'HELLO', 'doajdosajda', 'Heidi Bomuel', 53, '2026-01-03 23:50:07'),
(22, 'HELLO', 'October 9, 2025', 'HELLO', 'doajdosajda', 'Heidi Bomuel', 53, '2026-01-03 23:50:07'),
(23, 'HELLO', 'October 9, 2025', 'HELLO', 'doajdosajda', 'Heidi Bomuel', 53, '2026-01-03 23:50:07'),
(24, 'HELLO', 'October 9, 2025', 'HELLO', 'doajdosajda', 'Heidi Bomuel', 53, '2026-01-03 23:50:07'),
(25, 'HELLO', 'October 9, 2025', 'HELLO', 'dsadsa', 'Heidi Bomuel', 53, '2026-01-03 23:50:53'),
(26, 'NOTICE 2', 'October 9, 2025', 'sndkladsadasdka;lsd', 'das', 'Heidi Bomuel', 53, '2026-01-03 23:51:41'),
(27, 'HELLO', 'October 9, 2025', 'HELLO', 'xsa', 'Heidi Bomuel', 53, '2026-01-03 23:52:47'),
(28, 'SK LIGA 2026', 'January 3, 2026', '@ GAT YANTOK MJAYJAY LAGUNA\nEveryone will participate in this project', 'HELLO', 'Heidi Bomuel', 53, '2026-01-03 23:53:06'),
(29, 'HELLO', 'October 9, 2025', 'HELLO', 'dad', 'Heidi Bomuel', 53, '2026-01-03 23:55:04');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `admin_name` varchar(100) NOT NULL DEFAULT 'Sk President',
  `sender_fullname` varchar(255) NOT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `messages_content` text NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `sender_id`, `receiver_id`, `admin_name`, `sender_fullname`, `barangay`, `messages_content`, `image_path`, `created_at`, `is_read`) VALUES
(8, 16, 1, 'Sk President', 'Roxas Aljen Jovi Coladilla', 'Suba', 'skhakl', NULL, '2025-10-23 17:17:15', 1),
(9, 16, 1, 'Sk President', 'Roxas Aljen Jovi Coladilla', 'Suba', 'kajhsd', NULL, '2025-10-23 17:17:23', 1),
(10, 11, 1, 'Sk President', 'Napola Joshua Maala', 'San Isidro', 'dhsakds', NULL, '2025-10-23 17:18:11', 1),
(11, 11, 1, 'Sk President', 'Napola Joshua Maala', 'San Isidro', 'hello po', NULL, '2025-10-23 17:19:14', 1),
(13, 1, 11, 'Sk President', 'Admin User', NULL, 'hello', NULL, '2025-10-23 17:26:47', 0),
(14, 11, 1, 'Sk President', 'Napola Joshua Maala', 'San Isidro', 'sk pano po kaya yon tagal mo mag chat', NULL, '2025-10-23 17:38:06', 1),
(15, 1, 11, 'Sk President', 'Admin User', NULL, 'ehh kasi 2 mb lng utak ko', NULL, '2025-10-23 17:39:07', 0),
(16, 52, 1, 'Sk President', 'Millena Jade Anne', 'Tanawan', 'hello po', NULL, '2025-10-29 13:19:53', 1),
(17, 1, 52, 'Sk President', 'Admin User', NULL, 'sino ka?', NULL, '2025-10-29 13:20:36', 0),
(18, 54, 1, 'Sk President', 'Buenaseda Glenn', 'Ibabang Bayucain', 'Hi sk may ask po ako?', NULL, '2025-10-29 16:07:38', 1),
(19, 53, 1, 'Sk President', 'Bomuel Heidi', 'San Isidro', 'hello po', NULL, '2025-11-24 15:00:58', 1),
(20, 1, 53, 'Sk President', 'Admin User', NULL, 'hello', NULL, '2025-11-24 15:01:29', 0),
(21, 1, 54, 'Sk President', 'Admin User', NULL, 'tangina mo', NULL, '2025-11-24 15:02:21', 0),
(22, 18, 1, 'Sk President', 'Conejares Shaun', 'San Francisco', 'hi', NULL, '2026-01-02 07:34:41', 1),
(23, 69, 1, 'Sk President', 'Bomuel Ericka Shane', 'San Isidro', 'Hello po', NULL, '2026-01-02 07:35:22', 1),
(24, 53, 1, 'Sk President', 'Bomuel Heidi', 'San Isidro', 'hello po', NULL, '2026-02-08 05:05:10', 1),
(25, 53, 1, 'Sk President', 'Bomuel Heidi', 'San Isidro', 'kamusta po', NULL, '2026-02-08 05:05:16', 1),
(26, 53, 1, 'Sk President', 'Bomuel Heidi', 'San Isidro', 'sk?', NULL, '2026-02-08 05:05:54', 1),
(27, 53, 1, 'Sk President', 'Bomuel Heidi', 'San Isidro', 'sir ganda nito', NULL, '2026-02-10 06:40:19', 1),
(28, 53, 1, 'Sk President', 'Bomuel Heidi', 'San Isidro', 'hehe', NULL, '2026-02-10 06:42:00', 1),
(29, 53, 1, 'Sk President', 'Bomuel Heidi', 'San Isidro', 'flag', NULL, '2026-02-10 06:44:31', 1),
(31, 53, 1, 'Sk President', 'Bomuel Heidi', 'San Isidro', 'uhm', NULL, '2026-02-10 06:51:15', 1),
(32, 53, 1, 'Sk President', 'Bomuel Heidi', 'San Isidro', 'hello', NULL, '2026-02-10 06:53:52', 1);

-- --------------------------------------------------------

--
-- Table structure for table `document_archive`
--

CREATE TABLE `document_archive` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `document_category` enum('Minutes of Meeting','SK Resolution','Disbursement File','Project Proposal','Other') NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `document_archive`
--

INSERT INTO `document_archive` (`id`, `user_id`, `barangay`, `document_category`, `title`, `file_path`, `status`, `submitted_at`, `updated_at`) VALUES
(3, 53, 'San Isidro', 'Disbursement File', 'Disburesment', 'uploads/document_submissions/doc_6925c731db4f09.62417446.pdf', 'Pending', '2025-11-25 15:11:45', '2026-01-05 12:42:14'),
(4, 53, 'San Isidro', 'Minutes of Meeting', 'Minutes', 'uploads/document_submissions/doc_6925aea67d9474.70230739.pdf', 'Pending', '2025-11-25 13:27:02', '2026-01-05 12:42:15'),
(5, 53, 'San Isidro', 'Other', 'Presentation', 'uploads/document_submissions/doc_6925d3002fa535.76569078.pdf', 'Pending', '2025-11-25 16:02:08', '2026-01-18 17:33:07'),
(6, 53, 'San Isidro', 'Minutes of Meeting', 'APRIL - JUNE MINUTES (SAN ISIDRO)', 'uploads/document_submissions/doc_696d1a8247a704.31240811.pdf', 'Pending', '2026-01-18 17:38:10', '2026-02-08 06:06:38'),
(8, 53, 'San Isidro', 'Minutes of Meeting', 'FOCUS', 'uploads/document_submissions/doc_69886bfc5ea523.18586840.docx', 'Pending', '2026-02-08 10:57:00', '2026-02-19 13:13:00'),
(9, 53, 'San Isidro', 'Minutes of Meeting', 'FERBRUARY 08 2026 (MINUTES)', 'uploads/document_submissions/doc_69885a41e2e9f5.08633240.docx', 'Pending', '2026-02-08 09:41:21', '2026-02-19 13:13:02'),
(10, 53, 'San Isidro', 'SK Resolution', 'SAN ISIDRO RESOLUTION', 'uploads/document_submissions/doc_696d1a6d7cfc79.93412715.pdf', 'Pending', '2026-01-18 17:37:49', '2026-02-19 13:13:05'),
(11, 53, 'San Isidro', 'SK Resolution', 'SAN ISIDRO RESOLUTION', 'uploads/document_submissions/doc_696d1a2b5232e4.52093697.docx', 'Pending', '2026-01-18 17:36:43', '2026-02-19 13:13:07'),
(12, 53, 'San Isidro', 'Minutes of Meeting', 'APRIL-JUNE MINUTES', 'uploads/document_submissions/doc_696d1a190bce66.71805319.docx', 'Pending', '2026-01-18 17:36:25', '2026-02-19 13:13:10'),
(13, 53, 'San Isidro', 'Minutes of Meeting', 'MINUTES 2026 (LATES)', 'uploads/document_submissions/doc_698875af11e831.49966763.docx', 'Pending', '2026-02-08 11:38:23', '2026-02-19 13:20:34');

-- --------------------------------------------------------

--
-- Table structure for table `document_submissions`
--

CREATE TABLE `document_submissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `document_category` enum('Minutes of Meeting','SK Resolution','Disbursement File','Project Proposal','Other') NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `status` varchar(250) NOT NULL DEFAULT 'Pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `document_submissions`
--

INSERT INTO `document_submissions` (`id`, `user_id`, `barangay`, `document_category`, `title`, `file_path`, `status`, `submitted_at`, `updated_at`) VALUES
(67, 53, 'San Isidro', 'Minutes of Meeting', 'Minutes', 'uploads/document_submissions/doc_69b0320069ab87.00759704.docx', 'Pending', '2026-03-10 15:00:16', '2026-03-10 15:00:16'),
(68, 53, 'San Isidro', 'Disbursement File', 'asdsadsadsa', 'uploads/document_submissions/doc_69b0323ef38601.12868592.docx', 'Pending', '2026-03-10 15:01:18', '2026-03-10 15:01:18'),
(69, 53, 'San Isidro', 'SK Resolution', 'dasdd', 'uploads/document_submissions/doc_69b0332008b193.46815754.docx', 'Pending', '2026-03-10 15:05:04', '2026-03-10 15:05:04'),
(70, 53, 'San Isidro', 'Other', 'Attt', 'uploads/document_submissions/doc_69b0339d990be9.51457775.docx', 'View by Admin', '2026-03-10 15:07:09', '2026-03-12 17:13:45'),
(73, 53, 'San Isidro', 'Other', 'Attt', 'uploads/document_submissions/doc_69b03787083104.44942964.docx', 'View by Admin', '2026-03-10 15:23:51', '2026-03-12 17:11:01'),
(74, 53, 'San Isidro', 'Minutes of Meeting', 'MINUTOS', 'uploads/document_submissions/doc_69b2f41e9fa052.73299551.pdf', 'View by Admin', '2026-03-12 17:13:02', '2026-03-12 17:13:28'),
(75, 53, 'San Isidro', 'Minutes of Meeting', 'MINUTOS', 'uploads/document_submissions/doc_69b2f45844fe65.94573207.pdf', 'Pending', '2026-03-12 17:14:00', '2026-03-12 17:14:00');

-- --------------------------------------------------------

--
-- Table structure for table `financial_aid_requests`
--

CREATE TABLE `financial_aid_requests` (
  `id` int(11) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `aid_type` varchar(255) NOT NULL,
  `reason` varchar(250) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `submitted_by` varchar(255) NOT NULL,
  `submitter_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(10,2) NOT NULL,
  `barangay` varchar(255) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `type_or_value` varchar(255) DEFAULT NULL,
  `admin_doc` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `financial_aid_requests`
--

INSERT INTO `financial_aid_requests` (`id`, `student_name`, `aid_type`, `reason`, `status`, `submitted_by`, `submitter_user_id`, `created_at`, `total_amount`, `barangay`, `rejection_reason`, `type_or_value`, `admin_doc`) VALUES
(15, 'Feeding Program', 'In Kind', 'for Feeding program', 'Approved', 'Napola from San Isidro, SK Official', NULL, '2025-10-23 12:54:19', '0.00', 'San Isidro', NULL, 'Lugaw, Sopas, Spaghetti', NULL),
(16, 'Barangay Basketball League', 'In Cash', 'For the championship reward', 'Approved', 'Buenaseda from Ibabang Bayucain, SK Official', NULL, '2025-10-29 16:22:25', '5000.00', 'Ibabang Bayucain', NULL, NULL, NULL),
(17, 'Bayucain Miss Gay', 'In Cash', 'for financing the grand prize', 'Approved', 'Buenaseda from Ibabang Bayucain, SK Official', NULL, '2025-11-06 14:11:44', '20000.00', 'Ibabang Bayucain', NULL, NULL, NULL),
(18, 'Bayucain Drivers Cleaning', 'In Cash', 'For the Foods and anything', 'Approved', 'Buenaseda from Ibabang Bayucain, SK Official', NULL, '2025-11-06 14:13:36', '20000.00', 'Ibabang Bayucain', NULL, NULL, NULL),
(19, 'Brgy Medicare', 'In Kind', 'For the cure the sickness of our barangay', 'Rejected', 'Bomuel from San Isidro, SK Official', NULL, '2025-11-23 13:08:46', '0.00', 'San Isidro', 'mwehehehhe ayaw ko nga', 'Medicine', NULL),
(20, 'Brgy Liga Twenty Twenty Five', 'In Cash', 'For championship reward', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, '2025-11-24 13:56:01', '20000.00', 'San Isidro', NULL, NULL, NULL),
(21, 'Brgy Liga Twenty Twenty Five', 'In Cash', 'For championship reward', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, '2025-11-24 13:57:27', '20000.00', 'San Isidro', NULL, NULL, NULL),
(22, 'Brgy Liga Twenty Twenty Five', 'In Cash', 'For championship reward', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, '2025-11-24 14:00:07', '20000.00', 'San Isidro', NULL, NULL, NULL),
(23, 'Brgy Liga Twenty Twenty Five', 'In Cash', 'For championship reward', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, '2025-11-24 14:04:36', '20000.00', 'San Isidro', NULL, NULL, NULL),
(24, 'Brgy Liga Twenty Twenty Five', 'In Cash', 'For championship reward', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, '2025-11-24 14:05:07', '20000.00', 'San Isidro', NULL, NULL, NULL),
(25, 'Brgy Clean drive', 'In Cash', 'for buying appliances like broom duskfan etc', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, '2025-11-24 14:30:22', '20000.00', 'San Isidro', NULL, NULL, NULL),
(26, 'Brgy Clean drive', 'In Cash', 'for buying appliances like broom duskfan etc', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, '2025-11-24 14:33:18', '20000.00', 'San Isidro', NULL, NULL, NULL),
(27, 'Brgy Clean drive', 'In Cash', 'for buying appliances like broom duskfan etc', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, '2025-11-24 14:34:15', '20000.00', 'San Isidro', NULL, NULL, NULL),
(28, 'Brgy Clean drive', 'In Cash', 'for buying appliances like broom duskfan etc', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, '2025-11-24 14:36:44', '20000.00', 'San Isidro', NULL, NULL, NULL),
(29, 'Brgy Clean drive', 'In Cash', 'for buying appliances like broom duskfan etc', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, '2025-11-24 14:38:02', '20000.00', 'San Isidro', NULL, NULL, NULL),
(30, 'Brgy Clean drive', 'In Cash', 'for buying appliances like broom duskfan etc', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, '2025-11-24 14:45:40', '20000.00', 'San Isidro', NULL, NULL, NULL),
(31, 'Brgy Liga ', 'In Cash', 'Championship Reward', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, '2025-11-24 14:48:38', '30000.00', 'San Isidro', NULL, NULL, NULL),
(32, 'Brgy Miss Gay', 'In Cash', 'For winners', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, '2025-11-24 15:03:03', '30000.00', 'San Isidro', NULL, NULL, NULL),
(33, 'OPLAN PUKPOK TAMBUTSO', 'In Cash', 'The Riders must be deciplined', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, '2026-01-04 13:52:14', '2000.00', 'San Isidro', NULL, NULL, NULL),
(34, 'SAN ISIDRO CASH ASSISTANCE', 'In Cash', 'Student cash assistance for their school needs and support their financial crisis in school', 'Rejected', 'Bomuel from San Isidro, SK Official', NULL, '2026-02-08 09:43:12', '40000.00', 'San Isidro', NULL, NULL, NULL),
(35, 'SAN ISIDRO CASH ASSISTANCE', 'In Cash', 'Student cash assistance for their school needs and support their financial crisis in school', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, '2026-02-08 09:43:30', '40000.00', 'San Isidro', NULL, NULL, NULL),
(36, 'ML Tournament San Isidro', 'In Cash', 'kancklnsan', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, '2026-03-09 15:27:00', '20000.00', 'San Isidro', NULL, NULL, NULL),
(37, 'HOK Tournament', 'In Cash', 'akljdkan', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, '2026-03-09 16:17:09', '20000.00', 'San Isidro', NULL, NULL, 'uploads/admin_docs/admin_doc_37_1773073564.docx'),
(38, 'kldankdnwl', 'In Cash', 'fclamsclla', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, '2026-03-09 16:19:35', '20000.00', 'San Isidro', NULL, NULL, 'uploads/admin_docs/admin_doc_38_1773073656.docx'),
(39, 'akmdlm', 'In Cash', 'clkasnklcnakl', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, '2026-03-09 16:19:40', '2000.00', 'San Isidro', NULL, NULL, 'uploads/admin_docs/admin_doc_39_1773073213.docx'),
(40, 'kdacnaksn', 'In Cash', 'lkanclkna', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, '2026-03-09 16:30:21', '2000.00', 'San Isidro', NULL, NULL, 'uploads/aid_docs/aid_40/admin_doc_40_1773074333.docx'),
(41, 'sadnkalsk', 'In Cash', 'ckanckln', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, '2026-03-09 16:30:30', '2000.00', 'San Isidro', NULL, NULL, 'uploads/aid_docs/aid_41/admin_doc_41_1773073892.docx'),
(42, 'sadnkalsk', 'In Cash', 'ckanckln', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, '2026-03-09 16:33:53', '2000.00', 'San Isidro', NULL, NULL, 'uploads/aid_docs/aid_42/admin_doc_42_1773074075.docx'),
(43, 'kdncan', 'In Cash', 'calmclsl', 'View by Admin', 'Bomuel from San Isidro, SK Official', NULL, '2026-03-10 13:16:28', '2000.00', 'San Isidro', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `recipient_id` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) NOT NULL,
  `icon` varchar(50) NOT NULL,
  `color` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `recipient_id`, `message`, `link`, `icon`, `color`, `created_at`, `is_read`) VALUES
(6, '67', 'Your account was **rejected** by Admin Admin User. Please re-submit your registration with correct details or contact support.', 'registration.php', 'fas fa-user-times', 'red', '2025-11-06 13:43:29', 1),
(7, '0', 'Your account has been **approved** by Admin Admin User. Welcome to the system!', 'manage_users.php', 'fas fa-user-check', 'green', '2025-11-12 15:06:56', 1),
(8, '0', 'Your account has been **approved** by Admin Admin User. Welcome to the system!', 'manage_users.php', 'fas fa-user-check', 'green', '2026-01-02 07:12:35', 1),
(9, '0', 'Your account has been **approved** by Admin Admin User. Welcome to the system!', 'manage_users.php', 'fas fa-user-check', 'green', '2026-01-02 07:15:30', 1),
(10, '68', 'Your account has been **approved** by Admin Admin User. Welcome to the system!', 'manage_users.php', 'fas fa-user-check', 'green', '2026-01-02 07:16:39', 1),
(11, '0', 'Your account has been **approved** by Admin Admin User. Welcome to the system!', 'manage_users.php', 'fas fa-user-check', 'green', '2026-01-02 07:18:01', 1),
(12, '69', 'Your account has been **approved** by Admin Admin User. Welcome to the system!', 'manage_users.php', 'fas fa-user-check', 'green', '2026-01-02 07:27:08', 1),
(13, '0', 'Your account was **rejected** by Admin Admin User. Please re-submit your registration with correct details or contact support.', 'manage_users.php', 'fas fa-user-times', 'red', '2026-01-02 07:27:22', 1),
(14, '70', 'Your account has been **approved** by Admin Admin User. Welcome to the system!', 'manage_users.php', 'fas fa-user-check', 'green', '2026-02-02 12:57:41', 1),
(15, '71', 'Your account was **rejected** by Admin Admin User. Please re-submit your registration with correct details or contact support.', 'manage_users.php', 'fas fa-user-times', 'red', '2026-02-02 13:11:18', 1),
(16, '72', 'Your account was **rejected** by Admin Admin User. Please re-submit your registration with correct details or contact support.', 'manage_users.php', 'fas fa-user-times', 'red', '2026-02-02 13:26:34', 1),
(17, '74', 'Your account was **rejected** by Admin Admin User. Please re-submit your registration with correct details or contact support.', 'manage_users.php', 'fas fa-user-times', 'red', '2026-02-04 13:57:13', 1),
(18, '75', 'Your account was **rejected** by Admin Admin User. Please re-submit your registration with correct details or contact support.', 'manage_users.php', 'fas fa-user-times', 'red', '2026-02-04 14:11:30', 1),
(19, '76', 'Your account has been **approved** by Admin Admin User. Welcome to the system!', 'manage_users.php', 'fas fa-user-check', 'green', '2026-02-04 15:21:17', 1),
(20, '73', 'Your account was **rejected** by Admin Admin User. Please re-submit your registration with correct details or contact support.', 'manage_users.php', 'fas fa-user-times', 'red', '2026-02-05 15:29:37', 1),
(21, '77', 'Your account was **rejected** by Admin Admin User. Please re-submit your registration with correct details or contact support.', 'manage_users.php', 'fas fa-user-times', 'red', '2026-02-05 15:48:35', 1),
(22, '78', 'Your account was **rejected** by Admin Admin User. Please re-submit your registration with correct details or contact support.', 'manage_users.php', 'fas fa-user-times', 'red', '2026-02-08 02:04:55', 1);

-- --------------------------------------------------------

--
-- Table structure for table `proposals`
--

CREATE TABLE `proposals` (
  `id` int(11) NOT NULL,
  `sk_official_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','For Revision') DEFAULT 'Pending',
  `barangay_remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `scholarship_applications`
--

CREATE TABLE `scholarship_applications` (
  `id` int(11) NOT NULL,
  `surname` varchar(255) NOT NULL,
  `firstname` varchar(255) NOT NULL,
  `middlename` varchar(255) DEFAULT NULL,
  `barangay` varchar(255) NOT NULL,
  `educational_level` varchar(255) NOT NULL,
  `student_id` varchar(255) DEFAULT NULL,
  `cor` varchar(255) DEFAULT NULL,
  `grades` varchar(255) DEFAULT NULL,
  `voters_id` varchar(255) DEFAULT NULL,
  `psa` varchar(255) DEFAULT NULL,
  `date_submitted` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `scholarship_applications`
--

INSERT INTO `scholarship_applications` (`id`, `surname`, `firstname`, `middlename`, `barangay`, `educational_level`, `student_id`, `cor`, `grades`, `voters_id`, `psa`, `date_submitted`) VALUES
(2, 'Roxas', 'Aljen Jovi', 'Coladilla', 'San Isidro', 'College', 'uploads/68d2ac85e6591_Screenshot 2025-09-16 003627.png', 'uploads/68d2ac85e679f_Screenshot 2025-09-16 003627.png', 'uploads/68d2ac85e6912_Screenshot 2025-09-16 003627.png', 'uploads/68d2ac85e6aa9_Screenshot 2025-09-16 003627.png', 'uploads/68d2ac85e6c3d_Screenshot 2025-09-16 003627.png', '2025-09-23 14:19:49'),
(6, 'Areja', 'Luis Renzo', 'Rejano', 'Ibabang Bayucain', 'Senior High', 'uploads/scholarship_docs/student_id_6_1759322941.jpg', 'uploads/scholarship_docs/cor_6_1759322941.jpg', 'uploads/scholarship_docs/grades_6_1759322941.jpg', 'uploads/scholarship_docs/voters_id_6_1759322941.jpg', 'uploads/scholarship_docs/psa_6_1759322941.jpg', '2025-10-01 12:49:01'),
(9, 'Napula', 'Joshua', 'Maala', 'San Isidro', 'High School', NULL, NULL, NULL, NULL, NULL, '2026-02-10 06:19:19'),
(11, 'Areja', 'Luis Renzo', 'Rejano', 'San Isidro', 'Senior High', 'uploads/scholarship_docs/student_id_11_1770705284.png', NULL, NULL, NULL, NULL, '2026-02-10 06:34:08'),
(12, 'Montemor', 'June Cezar', 'Calim', 'San Isidro', 'College', NULL, NULL, NULL, NULL, NULL, '2026-02-10 07:17:35');

-- --------------------------------------------------------

--
-- Table structure for table `sec_users`
--

CREATE TABLE `sec_users` (
  `id` int(11) NOT NULL,
  `role` varchar(50) NOT NULL,
  `surname` varchar(255) NOT NULL,
  `firstname` varchar(255) NOT NULL,
  `middlename` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `mobile` varchar(15) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `id_document` varchar(255) DEFAULT NULL,
  `barangay` varchar(255) NOT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `civil_status` varchar(50) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `term_start` date DEFAULT NULL,
  `district` varchar(255) DEFAULT NULL,
  `house_no` varchar(50) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `purok` varchar(255) DEFAULT NULL,
  `municipality` varchar(255) DEFAULT NULL,
  `province` varchar(255) DEFAULT NULL,
  `complete_address` text DEFAULT NULL,
  `household_no` varchar(50) DEFAULT NULL,
  `occupation` varchar(255) DEFAULT NULL,
  `income_bracket` varchar(50) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `sec_users`
--

INSERT INTO `sec_users` (`id`, `role`, `surname`, `firstname`, `middlename`, `email`, `mobile`, `password`, `profile_photo`, `id_document`, `barangay`, `gender`, `dob`, `civil_status`, `position`, `term_start`, `district`, `house_no`, `street`, `purok`, `municipality`, `province`, `complete_address`, `household_no`, `occupation`, `income_bracket`, `status`, `created_at`) VALUES
(0, 'SK Official', 'Areja', 'Luis Renzo', 'Rejano', 'luis@gmail.com', '09192739723', '$2y$10$NlhvlwuiPm92DkNybK1O3ukiUdpt17PwhhvOwyURP.uu7de2Uo/bS', 'uploads/profiles/6914913caf562.jfif', 'uploads/documents/6914913caf861.pdf', 'Suba', 'Male', '2004-03-03', 'Single', 'SK Secretary', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Reject', '2025-11-12 13:53:00'),
(0, 'SK Official', 'Bomuel', 'Erica Shane', '', 'delm22060@gmail.com', '09498372409', '$2y$10$2Nep5oXmb.y6J3LSkZykO.xYM1RHty6jISnvSdCjpwefKHBkKfevy', 'uploads/profiles/69576fc2e503d.jfif', 'uploads/documents/69576fc2e5cce.jfif', 'San Isidro', 'Female', '2003-02-20', 'Single', 'SK Secretary', '2004-02-20', 'District 4`', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Reject', '2026-01-02 07:12:03'),
(0, 'SK Official', 'Bomuel', 'Ericka Shane', '', 'shane@gmail.com', '09921739821', '$2y$10$f3ipcMx8PPqeCEzXs8cJ9.IdVRiVp.2axmtToqyo6q9oCh70iAwHq', 'uploads/profiles/69577081e9483.jfif', 'uploads/documents/69577081e9da5.jfif', 'San Isidro', 'Female', '2004-02-20', 'Single', 'SK Secretary', '2004-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Reject', '2026-01-02 07:15:14'),
(0, 'SK Official', 'Bomuel', 'Ericka Shane', '', 'eshane@gmail.com', '09309217302', '$2y$10$Iqa.FzPNR.yHQtF4ruZdUOv/HQo7dgLhgxekcG6nohU30lPn9yoWm', 'uploads/profiles/6957711c5f1de.jfif', 'uploads/documents/6957711c5f97d.jfif', 'San Isidro', 'Female', '2004-02-20', 'Single', 'SK Secretary', '2004-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Reject', '2026-01-02 07:17:48'),
(0, 'SK Official', 'Bomuel', 'Ericka Shane', '', 'shane@gmail.com', '09391739173', '$2y$10$Bag5gD351fLmx4R6FP86NesZvb4RDEeZg9PAicq9Gs5UZBMZ2G2U2', 'uploads/profiles/695772acc52f8.jfif', 'uploads/documents/695772acc5902.jfif', 'San Isidro', 'Female', '2004-02-20', 'Single', 'SK Secretary', '2004-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Reject', '2026-01-02 07:24:28');

-- --------------------------------------------------------

--
-- Table structure for table `sk_list`
--

CREATE TABLE `sk_list` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('SK Chairperson','SK Members','SK Secretary','SK Treasurer','Admin','SK Official') NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `position` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `sk_list`
--

INSERT INTO `sk_list` (`id`, `first_name`, `middle_name`, `last_name`, `phone_number`, `email`, `profile_photo`, `password_hash`, `role`, `barangay`, `position`, `created_at`) VALUES
(1, 'Heidi', 'B', 'Bomuel', '09123456789', 'heidibomuel@gmail.com', 'uploads/profiles/Bomuel_68f66ebfe3fe2.jfif', '$2y$10$yOFva144gILez3cYTqzZX.F7rASO9P8pVvlWpIv0sgVZh2mQezmcy', 'SK Chairperson', 'San Isidro', 'SK Chairman', '2025-10-20 15:00:44'),
(2, 'Joshua', 'M.', 'Napola', '09485442655', 'napulajoshua20@gmail.com', 'uploads/profiles/Napola_68f66f0dd0da9.jfif', '', 'SK Members', 'San Isidro', 'SK Kagawad (2nd)', '2025-10-20 16:00:16'),
(3, 'Alessandra Moira', 'C.', 'Roxas', '09481193975', 'roxasalessandramoira@gmail.com', 'uploads/profiles/Roxas_68f66f235b1c7.jfif', '', 'SK Members', 'San Isidro', 'SK Kagawad (5th)', '2025-10-20 16:02:06'),
(4, 'karyle', 'A.', 'Bomuel', '09832738721', 'Karylebomuel@gmail.com', 'uploads/profiles/Bomuel_68f66f06ac3bb.jfif', '', 'SK Members', 'San Isidro', 'SK Kagawad (1st)', '2025-10-20 16:25:00'),
(5, 'Michaela Dianne', 'R.', 'Ybuan', '09938217389', 'michaelaybuan@gmail.com', 'uploads/profiles/Ybuan_68f66f3857370.jfif', '', 'SK Treasurer', 'San Isidro', 'SK Treasurer', '2025-10-20 16:28:34'),
(6, 'Ericka Shane', '', 'Bomuel', '09938172897', 'erickabomuel@gmail.com', 'uploads/profiles/Bomuel_68f66f3039d74.jfif', '', 'SK Secretary', 'San Isidro', 'SK Secretary', '2025-10-20 16:30:16'),
(7, 'Audrey Jearld', 'T.', 'Bomuel', '09947509840', 'auderybomuel@gmail.com', 'uploads/profiles/Bomuel_68f66f130c4a7.jfif', '', 'SK Members', 'San Isidro', 'SK Kagawad (3rd)', '2025-10-20 16:31:39'),
(8, 'Jean Claudine', 'P.', 'Jocson', '09487238947', 'jeanclaudinejocson@gmail.com', 'uploads/profiles/Jocson_68f66f1bb8f53.jfif', '', 'SK Members', 'San Isidro', 'SK Kagawad (4th)', '2025-10-20 16:33:27'),
(9, 'Michaella Heart', '', 'Geraldo', '09489237489', 'michaellaheart@gmail.com', 'uploads/profiles/Geraldo_68f66f299e840.jfif', '', 'SK Members', 'San Isidro', 'SK Kagawad (6th)', '2025-10-20 16:34:48'),
(10, 'Jede Anne', 'B.', 'Millena', '09938217389', 'jadeanne@gmail.com', 'uploads/profiles/Millena_69021447be037.jfif', '', 'SK Chairperson', 'Tanawan', 'SK Chairman', '2025-10-29 13:18:53'),
(11, 'Quencel', 'C.', 'Manalo', '09398273892', 'manaloquencel@gmail.com', 'uploads/profiles/Manalo_69021874ca8a8.jfif', '', 'SK Chairperson', 'Tanawan', 'SK Kagawad (1st)', '2025-10-29 13:30:38'),
(12, 'Maricris', 'R.', 'Meregilla', '09498327489', 'meregillamaricris@gmail.com', 'uploads/profiles/Meregilla_6902188039e37.jfif', '', 'SK Chairperson', 'Tanawan', 'SK Kagawad (2nd)', '2025-10-29 13:32:08'),
(13, 'Jhon Calbert', 'F.', 'Panaglima', '09814680173', 'panaglimajhoncalbert@gmail.com', 'uploads/profiles/Panaglima_6902188adc12c.jfif', '', 'SK Chairperson', 'Tanawan', 'SK Kagawad (3rd)', '2025-10-29 13:32:49'),
(14, 'Althea Mae', 'O.', 'Rasay', '09081723981', 'rasayaltheamae@gmail.com', 'uploads/profiles/Rasay_6902189d60404.jfif', '', 'SK Chairperson', 'Tanawan', 'SK Kagawad (4th)', '2025-10-29 13:33:24'),
(15, 'Jasmin Fritzie', 'A.', 'Miel', '09918374698', 'mieljasminfritzie@gmail.com', 'uploads/profiles/default-avatar.png', '', 'SK Chairperson', 'Tanawan', 'SK Kagawad (5th)', '2025-10-29 13:34:01'),
(16, 'Jimboy', 'P.', 'Rubiales', '09912489362', 'rubialesjimboy@gmail.com', 'uploads/profiles/Rubiales_690218aa8a9c5.jfif', '', 'SK Chairperson', 'Tanawan', 'SK Secretary', '2025-10-29 13:35:07'),
(17, 'Jamyca', 'O.', 'Esmejarda', '09123743294', 'esmejardajamyca@gmail.com', 'uploads/profiles/Esmejarda_690218b5dc5fe.jfif', '', 'SK Chairperson', 'Tanawan', 'SK Treasurer', '2025-10-29 13:35:48'),
(18, 'Robin', 'C.', 'Mercurio', '09103872138', 'robinmercurio4@gmail.com', 'uploads/profiles/Mercurio_6902193b3eaaa.jfif', '', 'SK Chairperson', 'Amonoy', 'SK Chairman', '2025-10-29 13:40:03'),
(19, 'Kyla Marie', 'G.', 'Condino', '09921349872', 'kylacondino07@gmail.com', 'uploads/profiles/Condino_69021a0a8daae.jfif', '', 'SK Chairperson', 'Amonoy', 'SK Kagawad (1st)', '2025-10-29 13:40:49'),
(20, 'Jerico', 'B.', 'Ortega', '09210381623', 'jericoortega801@gmail.com', 'uploads/profiles/Ortega_69021a15b9b19.jfif', '', 'SK Chairperson', 'Amonoy', 'SK Kagawad (2nd)', '2025-10-29 13:41:14'),
(21, 'Elmo', 'M.', 'Arsolacia', '09401927302', 'elmoarsolacia096@gmail.com', 'uploads/profiles/default-avatar.png', '', 'SK Chairperson', 'Amonoy', 'SK Kagawad (3rd)', '2025-10-29 13:41:42'),
(22, 'Nica Mae', 'A.', 'Monteagudo', '09392186301', 'monteagudonicamae12@gmail.com', 'uploads/profiles/Monteagudo_69021a27e66c3.jfif', '', 'SK Chairperson', 'Amonoy', 'SK Kagawad (4th)', '2025-10-29 13:42:12'),
(23, 'Irene', 'V.', 'Mia', '09912836891', 'miairene561@gmail.com', 'uploads/profiles/Mia_69021a367dc7d.jfif', '', 'SK Chairperson', 'Amonoy', 'SK Secretary', '2025-10-29 13:42:50'),
(24, 'Aila Marie', 'F.', 'Condino', '09912486218', 'ailamariefresco@gmail.com', 'uploads/profiles/Condino_69021a42c85eb.jfif', '', 'SK Chairperson', 'Amonoy', 'SK Treasurer', '2025-10-29 13:43:21');

-- --------------------------------------------------------

--
-- Table structure for table `sk_notifications`
--

CREATE TABLE `sk_notifications` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `message` text NOT NULL,
  `related_link` varchar(2048) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `sk_notifications`
--

INSERT INTO `sk_notifications` (`id`, `email`, `barangay`, `position`, `message`, `related_link`, `created_at`, `is_read`) VALUES
(85, 'heidi@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted for review by Heidi Bomuel (SK Secretary of San Isidro): **Minutes** (Minutes of Meeting).', 'document_submissions.php?id=9', '2025-11-25 13:27:02', 1),
(86, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted for review by Heidi Bomuel (SK Secretary of San Isidro): **Minutes** (Minutes of Meeting).', 'document_submissions.php?id=9', '2025-11-25 13:27:02', 0),
(87, 'heidi@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted for review by Heidi Bomuel (SK Secretary of San Isidro): **Disburesment** (Disbursement File).', 'document_submissions.php?id=12', '2025-11-25 15:11:45', 1),
(88, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted for review by Heidi Bomuel (SK Secretary of San Isidro): **Disburesment** (Disbursement File).', 'document_submissions.php?id=12', '2025-11-25 15:11:45', 0),
(89, 'heidi@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted for review by Heidi Bomuel (SK Secretary of San Isidro): **San Isidro Resolution** (SK Resolution).', 'document_submissions.php?id=13', '2025-11-25 15:26:21', 1),
(90, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted for review by Heidi Bomuel (SK Secretary of San Isidro): **San Isidro Resolution** (SK Resolution).', 'document_submissions.php?id=13', '2025-11-25 15:26:21', 0),
(91, 'heidi@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted for review by Heidi Bomuel (SK Secretary of San Isidro): **Presentation** (Other).', 'document_submissions.php?id=14', '2025-11-25 16:02:08', 1),
(92, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted for review by Heidi Bomuel (SK Secretary of San Isidro): **Presentation** (Other).', 'document_submissions.php?id=14', '2025-11-25 16:02:08', 0),
(93, 'heidi@gmail.com', 'San Isidro', 'SK Official', 'New Financial Aid Request for \'OPLAN PUKPOK TAMBUTSO\' (In Cash) has been submitted by Bomuel from San Isidro, SK Official.', 'financial_aid.php', '2026-01-04 13:52:14', 0),
(94, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted for review by Heidi Bomuel (SK Chairman of San Isidro): **APRIL-JUNE MINUTES** (Minutes of Meeting).', 'document_submissions.php?id=32', '2026-01-18 17:36:25', 0),
(95, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted for review by Heidi Bomuel (SK Chairman of San Isidro): **APRIL-JUNE MINUTES** (Minutes of Meeting).', 'document_submissions.php?id=32', '2026-01-18 17:36:25', 0),
(96, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted for review by Heidi Bomuel (SK Chairman of San Isidro): **APRIL-JUNE MINUTES** (Minutes of Meeting).', 'document_submissions.php?id=32', '2026-01-18 17:36:25', 0),
(97, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted for review by Heidi Bomuel (SK Chairman of San Isidro): **SAN ISIDRO RESOLUTION** (SK Resolution).', 'document_submissions.php?id=33', '2026-01-18 17:36:43', 0),
(98, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted for review by Heidi Bomuel (SK Chairman of San Isidro): **SAN ISIDRO RESOLUTION** (SK Resolution).', 'document_submissions.php?id=33', '2026-01-18 17:36:43', 0),
(99, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted for review by Heidi Bomuel (SK Chairman of San Isidro): **SAN ISIDRO RESOLUTION** (SK Resolution).', 'document_submissions.php?id=33', '2026-01-18 17:36:43', 0),
(100, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted for review by Heidi Bomuel (SK Chairman of San Isidro): **SAN ISIDRO RESOLUTION** (SK Resolution).', 'document_submissions.php?id=34', '2026-01-18 17:37:49', 0),
(101, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted for review by Heidi Bomuel (SK Chairman of San Isidro): **SAN ISIDRO RESOLUTION** (SK Resolution).', 'document_submissions.php?id=34', '2026-01-18 17:37:49', 0),
(102, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted for review by Heidi Bomuel (SK Chairman of San Isidro): **SAN ISIDRO RESOLUTION** (SK Resolution).', 'document_submissions.php?id=34', '2026-01-18 17:37:49', 0),
(103, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted for review by Heidi Bomuel (SK Chairman of San Isidro): **APRIL - JUNE MINUTES (SAN ISIDRO)** (Minutes of Meeting).', 'document_submissions.php?id=35', '2026-01-18 17:38:10', 0),
(104, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted for review by Heidi Bomuel (SK Chairman of San Isidro): **APRIL - JUNE MINUTES (SAN ISIDRO)** (Minutes of Meeting).', 'document_submissions.php?id=35', '2026-01-18 17:38:10', 0),
(105, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted for review by Heidi Bomuel (SK Chairman of San Isidro): **APRIL - JUNE MINUTES (SAN ISIDRO)** (Minutes of Meeting).', 'document_submissions.php?id=35', '2026-01-18 17:38:10', 0),
(106, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted for review by Heidi Bomuel (SK Chairman of San Isidro): **FERBRUARY 08 2026 (MINUTES)** (Minutes of Meeting).', 'document_submissions.php?id=41', '2026-02-08 09:41:21', 0),
(107, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted for review by Heidi Bomuel (SK Chairman of San Isidro): **FERBRUARY 08 2026 (MINUTES)** (Minutes of Meeting).', 'document_submissions.php?id=41', '2026-02-08 09:41:21', 0),
(108, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted for review by Heidi Bomuel (SK Chairman of San Isidro): **FERBRUARY 08 2026 (MINUTES)** (Minutes of Meeting).', 'document_submissions.php?id=41', '2026-02-08 09:41:21', 0),
(109, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted for review by Heidi Bomuel (SK Chairman of San Isidro): **FERBRUARY 08 2026 (MINUTES)** (Minutes of Meeting).', 'document_submissions.php?id=41', '2026-02-08 09:41:21', 0),
(110, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted for review by Heidi Bomuel (SK Chairman of San Isidro): **FERBRUARY 08 2026 (MINUTES)** (Minutes of Meeting).', 'document_submissions.php?id=41', '2026-02-08 09:41:21', 0),
(111, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Financial Aid Request for \'SAN ISIDRO CASH ASSISTANCE\' (In Cash) has been submitted by Bomuel from San Isidro, SK Official.', 'financial_aid.php', '2026-02-08 09:43:12', 0),
(112, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Financial Aid Request for \'SAN ISIDRO CASH ASSISTANCE\' (In Cash) has been submitted by Bomuel from San Isidro, SK Official.', 'financial_aid.php', '2026-02-08 09:43:30', 0),
(113, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted for review by Heidi Bomuel (SK Chairman of San Isidro): **FOCUS** (Minutes of Meeting).', 'document_submissions.php?id=43', '2026-02-08 10:57:00', 0),
(114, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted for review by Heidi Bomuel (SK Chairman of San Isidro): **FOCUS** (Minutes of Meeting).', 'document_submissions.php?id=43', '2026-02-08 10:57:00', 0),
(115, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted for review by Heidi Bomuel (SK Chairman of San Isidro): **FOCUS** (Minutes of Meeting).', 'document_submissions.php?id=43', '2026-02-08 10:57:00', 0),
(116, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted for review by Heidi Bomuel (SK Chairman of San Isidro): **FOCUS** (Minutes of Meeting).', 'document_submissions.php?id=43', '2026-02-08 10:57:00', 0),
(117, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted for review by Heidi Bomuel (SK Chairman of San Isidro): **FOCUS** (Minutes of Meeting).', 'document_submissions.php?id=43', '2026-02-08 10:57:00', 0),
(118, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Project Proposal titled: \'lkdjalkdsakl\' has been submitted by Heidi Bomuel (SK Official) from San Isidro.', 'document_submissions.php', '2026-02-08 11:01:22', 0),
(119, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **MINUTES 2026 (LATES)**.', 'document_submissions.php?id=44', '2026-02-08 11:38:23', 0),
(120, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **MINUTES 2026 (LATES)**.', 'document_submissions.php?id=44', '2026-02-08 11:38:23', 0),
(121, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **MINUTES 2026 (LATES)**.', 'document_submissions.php?id=44', '2026-02-08 11:38:23', 0),
(122, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **MINUTES 2026 (LATES)**.', 'document_submissions.php?id=44', '2026-02-08 11:38:23', 0),
(123, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **MINUTES 2026 (LATES)**.', 'document_submissions.php?id=44', '2026-02-08 11:38:23', 0),
(124, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Project Proposal titled: \'Linggo ng Kabataan\' has been submitted by Heidi Bomuel (SK Official) from San Isidro.', 'document_submissions.php', '2026-02-10 05:26:57', 0),
(125, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New scholar application for **Joshua Maala Napula** (High School) has been added by Heidi Bomuel (SK Official).', 'scholarship_list.php', '2026-02-10 06:19:56', 0),
(126, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Project Proposal titled: \'Linggo ng Kabalugan 2026\' has been submitted by Heidi Bomuel (SK Official) from San Isidro.', 'document_submissions.php', '2026-03-05 14:10:44', 0),
(127, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Project Proposal titled: \'Kabataan rap battle\' has been submitted by Heidi Bomuel (SK Official) from San Isidro.', 'document_submissions.php', '2026-03-05 15:10:52', 0),
(128, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Project Proposal titled: \'Kabataan Night\' has been submitted by Heidi Bomuel (SK Official) from San Isidro.', 'document_submissions.php', '2026-03-05 15:26:32', 0),
(129, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Project Proposal titled: \'Car Shows\' has been submitted by Heidi Bomuel (SK Official) from San Isidro.', 'document_submissions.php', '2026-03-05 15:51:11', 0),
(130, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Project Proposal titled: \'Tech Kabataan\' has been submitted by Heidi Bomuel (SK Official) from San Isidro.', 'document_submissions.php', '2026-03-09 14:23:51', 0),
(131, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Project Proposal titled: \'Thesis Project\' has been submitted by Heidi Bomuel (SK Official) from San Isidro.', 'document_submissions.php', '2026-03-09 15:09:31', 0),
(132, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Financial Aid Request for \'ML Tournament San Isidro\' (In Cash) submitted by Bomuel from San Isidro, SK Official.', 'financial_aid.php', '2026-03-09 15:27:00', 0),
(133, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Project Proposal titled: \'ndalkcnlan\' has been submitted by Heidi Bomuel (SK Official) from San Isidro.', 'document_submissions.php', '2026-03-09 15:27:25', 0),
(134, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Project Proposal titled: \'DIDODOO\' has been submitted by Heidi Bomuel (SK Official) from San Isidro.', 'document_submissions.php', '2026-03-09 15:51:35', 0),
(135, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Project Proposal titled: \'kanklcnkla\' has been submitted by Heidi Bomuel (SK Official) from San Isidro.', 'document_submissions.php', '2026-03-09 15:51:47', 0),
(136, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Project Proposal titled: \'HAHAHAH\' has been submitted by Heidi Bomuel (SK Official) from San Isidro.', 'document_submissions.php', '2026-03-09 16:01:05', 0),
(137, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Financial Aid Request for \'HOK Tournament\' (In Cash) submitted by Bomuel from San Isidro, SK Official.', 'financial_aid.php', '2026-03-09 16:17:09', 0),
(138, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Financial Aid Request for \'kldankdnwl\' (In Cash) submitted by Bomuel from San Isidro, SK Official.', 'financial_aid.php', '2026-03-09 16:19:35', 0),
(139, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Financial Aid Request for \'akmdlm\' (In Cash) submitted by Bomuel from San Isidro, SK Official.', 'financial_aid.php', '2026-03-09 16:19:40', 0),
(140, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Financial Aid Request for \'kdacnaksn\' (In Cash) submitted by Bomuel from San Isidro, SK Official.', 'financial_aid.php', '2026-03-09 16:30:21', 0),
(141, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Financial Aid Request for \'sadnkalsk\' (In Cash) submitted by Bomuel from San Isidro, SK Official.', 'financial_aid.php', '2026-03-09 16:30:30', 0),
(142, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Financial Aid Request for \'sadnkalsk\' (In Cash) submitted by Bomuel from San Isidro, SK Official.', 'financial_aid.php', '2026-03-09 16:33:53', 0),
(143, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Financial Aid Request for \'kdncan\' (In Cash) submitted by Bomuel from San Isidro, SK Official.', 'financial_aid.php', '2026-03-10 13:16:28', 0),
(144, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Project Proposal titled: \'ndlanlks\' has been submitted by Heidi Bomuel (SK Official) from San Isidro.', 'document_submissions.php', '2026-03-10 13:22:24', 0),
(145, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **MINUTES 2026**.', 'document_submissions.php?id=47', '2026-03-10 13:28:32', 0),
(146, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **MINUTES 2026**.', 'document_submissions.php?id=47', '2026-03-10 13:28:32', 0),
(147, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **MINUTES 2026**.', 'document_submissions.php?id=47', '2026-03-10 13:28:32', 0),
(148, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **MINUTES 2026**.', 'document_submissions.php?id=47', '2026-03-10 13:28:32', 0),
(149, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **MINUTES 2026**.', 'document_submissions.php?id=47', '2026-03-10 13:28:32', 0),
(150, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **San Isidro Attendace**.', 'document_submissions.php?id=48', '2026-03-10 14:05:58', 0),
(151, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **San Isidro Attendace**.', 'document_submissions.php?id=48', '2026-03-10 14:05:58', 0),
(152, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **San Isidro Attendace**.', 'document_submissions.php?id=48', '2026-03-10 14:05:58', 0),
(153, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **San Isidro Attendace**.', 'document_submissions.php?id=48', '2026-03-10 14:05:58', 0),
(154, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **San Isidro Attendace**.', 'document_submissions.php?id=48', '2026-03-10 14:05:58', 0),
(155, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **San Isidro 2026 (REPORT)**.', 'document_submissions.php?id=49', '2026-03-10 14:06:37', 0),
(156, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **San Isidro 2026 (REPORT)**.', 'document_submissions.php?id=49', '2026-03-10 14:06:37', 0),
(157, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **San Isidro 2026 (REPORT)**.', 'document_submissions.php?id=49', '2026-03-10 14:06:37', 0),
(158, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **San Isidro 2026 (REPORT)**.', 'document_submissions.php?id=49', '2026-03-10 14:06:37', 0),
(159, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **San Isidro 2026 (REPORT)**.', 'document_submissions.php?id=49', '2026-03-10 14:06:37', 0),
(160, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Transmittal**.', 'document_submissions.php?id=50', '2026-03-10 14:07:04', 0),
(161, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Transmittal**.', 'document_submissions.php?id=50', '2026-03-10 14:07:04', 0),
(162, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Transmittal**.', 'document_submissions.php?id=50', '2026-03-10 14:07:04', 0),
(163, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Transmittal**.', 'document_submissions.php?id=50', '2026-03-10 14:07:04', 0),
(164, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Transmittal**.', 'document_submissions.php?id=50', '2026-03-10 14:07:04', 0),
(165, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=52', '2026-03-10 14:13:35', 0),
(166, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=52', '2026-03-10 14:13:35', 0),
(167, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=52', '2026-03-10 14:13:35', 0),
(168, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=52', '2026-03-10 14:13:35', 0),
(169, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=52', '2026-03-10 14:13:35', 0),
(170, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=53', '2026-03-10 14:16:57', 0),
(171, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=53', '2026-03-10 14:16:57', 0),
(172, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=53', '2026-03-10 14:16:57', 0),
(173, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=53', '2026-03-10 14:16:57', 0),
(174, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=53', '2026-03-10 14:16:57', 0),
(175, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=54', '2026-03-10 14:18:20', 0),
(176, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=54', '2026-03-10 14:18:20', 0),
(177, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=54', '2026-03-10 14:18:20', 0),
(178, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=54', '2026-03-10 14:18:20', 0),
(179, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=54', '2026-03-10 14:18:20', 0),
(180, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=55', '2026-03-10 14:20:18', 0),
(181, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=55', '2026-03-10 14:20:18', 0),
(182, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=55', '2026-03-10 14:20:18', 0),
(183, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=55', '2026-03-10 14:20:18', 0),
(184, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=55', '2026-03-10 14:20:18', 0),
(185, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=56', '2026-03-10 14:21:38', 0),
(186, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=56', '2026-03-10 14:21:38', 0),
(187, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=56', '2026-03-10 14:21:38', 0),
(188, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=56', '2026-03-10 14:21:38', 0),
(189, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=56', '2026-03-10 14:21:38', 0),
(190, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=57', '2026-03-10 14:24:37', 0),
(191, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=57', '2026-03-10 14:24:37', 0),
(192, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=57', '2026-03-10 14:24:37', 0),
(193, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=57', '2026-03-10 14:24:37', 0),
(194, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **SK Report 2026**.', 'document_submissions.php?id=57', '2026-03-10 14:24:37', 0),
(195, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **xacdas**.', 'document_submissions.php?id=58', '2026-03-10 14:25:06', 0),
(196, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **xacdas**.', 'document_submissions.php?id=58', '2026-03-10 14:25:06', 0),
(197, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **xacdas**.', 'document_submissions.php?id=58', '2026-03-10 14:25:06', 0),
(198, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **xacdas**.', 'document_submissions.php?id=58', '2026-03-10 14:25:06', 0),
(199, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **xacdas**.', 'document_submissions.php?id=58', '2026-03-10 14:25:06', 0),
(200, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **xacdas**.', 'document_submissions.php?id=59', '2026-03-10 14:27:22', 0),
(201, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **xacdas**.', 'document_submissions.php?id=59', '2026-03-10 14:27:22', 0),
(202, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **xacdas**.', 'document_submissions.php?id=59', '2026-03-10 14:27:22', 0),
(203, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **xacdas**.', 'document_submissions.php?id=59', '2026-03-10 14:27:22', 0),
(204, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **xacdas**.', 'document_submissions.php?id=59', '2026-03-10 14:27:22', 0),
(205, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **xacdas**.', 'document_submissions.php?id=60', '2026-03-10 14:27:32', 0),
(206, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **xacdas**.', 'document_submissions.php?id=60', '2026-03-10 14:27:32', 0),
(207, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **xacdas**.', 'document_submissions.php?id=60', '2026-03-10 14:27:32', 0),
(208, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **xacdas**.', 'document_submissions.php?id=60', '2026-03-10 14:27:32', 0),
(209, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **xacdas**.', 'document_submissions.php?id=60', '2026-03-10 14:27:32', 0),
(210, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **daads**.', 'document_submissions.php?id=61', '2026-03-10 14:29:08', 0),
(211, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **daads**.', 'document_submissions.php?id=61', '2026-03-10 14:29:08', 0),
(212, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **daads**.', 'document_submissions.php?id=61', '2026-03-10 14:29:08', 0),
(213, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **daads**.', 'document_submissions.php?id=61', '2026-03-10 14:29:08', 0),
(214, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **daads**.', 'document_submissions.php?id=61', '2026-03-10 14:29:08', 0),
(215, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **daads**.', 'document_submissions.php?id=62', '2026-03-10 14:33:40', 0),
(216, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **daads**.', 'document_submissions.php?id=62', '2026-03-10 14:33:40', 0),
(217, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **daads**.', 'document_submissions.php?id=62', '2026-03-10 14:33:40', 0),
(218, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **daads**.', 'document_submissions.php?id=62', '2026-03-10 14:33:40', 0),
(219, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **daads**.', 'document_submissions.php?id=62', '2026-03-10 14:33:40', 0),
(220, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **lckanlka**.', 'document_submissions.php?id=63', '2026-03-10 14:33:53', 0),
(221, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **lckanlka**.', 'document_submissions.php?id=63', '2026-03-10 14:33:53', 0),
(222, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **lckanlka**.', 'document_submissions.php?id=63', '2026-03-10 14:33:53', 0),
(223, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **lckanlka**.', 'document_submissions.php?id=63', '2026-03-10 14:33:53', 0),
(224, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **lckanlka**.', 'document_submissions.php?id=63', '2026-03-10 14:33:53', 0),
(225, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **Attendace**.', 'document_submissions.php?id=64', '2026-03-10 14:57:27', 0),
(226, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **Attendace**.', 'document_submissions.php?id=64', '2026-03-10 14:57:27', 0),
(227, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **Attendace**.', 'document_submissions.php?id=64', '2026-03-10 14:57:27', 0),
(228, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **Attendace**.', 'document_submissions.php?id=64', '2026-03-10 14:57:27', 0),
(229, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **Attendace**.', 'document_submissions.php?id=64', '2026-03-10 14:57:27', 0),
(230, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **REPORTY**.', 'document_submissions.php?id=65', '2026-03-10 14:58:22', 0),
(231, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **REPORTY**.', 'document_submissions.php?id=65', '2026-03-10 14:58:22', 0),
(232, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **REPORTY**.', 'document_submissions.php?id=65', '2026-03-10 14:58:22', 0),
(233, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **REPORTY**.', 'document_submissions.php?id=65', '2026-03-10 14:58:22', 0),
(234, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **REPORTY**.', 'document_submissions.php?id=65', '2026-03-10 14:58:22', 0),
(235, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **REPORTY**.', 'document_submissions.php?id=66', '2026-03-10 14:59:57', 0),
(236, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **REPORTY**.', 'document_submissions.php?id=66', '2026-03-10 14:59:57', 0),
(237, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **REPORTY**.', 'document_submissions.php?id=66', '2026-03-10 14:59:57', 0),
(238, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **REPORTY**.', 'document_submissions.php?id=66', '2026-03-10 14:59:57', 0),
(239, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **REPORTY**.', 'document_submissions.php?id=66', '2026-03-10 14:59:57', 0),
(240, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **Minutes**.', 'document_submissions.php?id=67', '2026-03-10 15:00:16', 0),
(241, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **Minutes**.', 'document_submissions.php?id=67', '2026-03-10 15:00:16', 0),
(242, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **Minutes**.', 'document_submissions.php?id=67', '2026-03-10 15:00:16', 0),
(243, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **Minutes**.', 'document_submissions.php?id=67', '2026-03-10 15:00:16', 0),
(244, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **Minutes**.', 'document_submissions.php?id=67', '2026-03-10 15:00:16', 0),
(245, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **asdsadsadsa**.', 'document_submissions.php?id=68', '2026-03-10 15:01:19', 0),
(246, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **asdsadsadsa**.', 'document_submissions.php?id=68', '2026-03-10 15:01:19', 0),
(247, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **asdsadsadsa**.', 'document_submissions.php?id=68', '2026-03-10 15:01:19', 0),
(248, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **asdsadsadsa**.', 'document_submissions.php?id=68', '2026-03-10 15:01:19', 0),
(249, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **asdsadsadsa**.', 'document_submissions.php?id=68', '2026-03-10 15:01:19', 0),
(250, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **dasdd**.', 'document_submissions.php?id=69', '2026-03-10 15:05:04', 0),
(251, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **dasdd**.', 'document_submissions.php?id=69', '2026-03-10 15:05:04', 0),
(252, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **dasdd**.', 'document_submissions.php?id=69', '2026-03-10 15:05:04', 0),
(253, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **dasdd**.', 'document_submissions.php?id=69', '2026-03-10 15:05:04', 0),
(254, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **dasdd**.', 'document_submissions.php?id=69', '2026-03-10 15:05:04', 0),
(255, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **Attt**.', 'document_submissions.php?id=70', '2026-03-10 15:07:09', 0),
(256, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **Attt**.', 'document_submissions.php?id=70', '2026-03-10 15:07:09', 0),
(257, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **Attt**.', 'document_submissions.php?id=70', '2026-03-10 15:07:09', 0),
(258, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **Attt**.', 'document_submissions.php?id=70', '2026-03-10 15:07:09', 0),
(259, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **Attt**.', 'document_submissions.php?id=70', '2026-03-10 15:07:09', 0),
(260, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **Attt**.', 'document_submissions.php?id=71', '2026-03-10 15:09:04', 0),
(261, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **Attt**.', 'document_submissions.php?id=71', '2026-03-10 15:09:04', 0),
(262, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **Attt**.', 'document_submissions.php?id=71', '2026-03-10 15:09:04', 0),
(263, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **Attt**.', 'document_submissions.php?id=71', '2026-03-10 15:09:04', 0),
(264, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **Attt**.', 'document_submissions.php?id=71', '2026-03-10 15:09:04', 0),
(265, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **Attt**.', 'document_submissions.php?id=72', '2026-03-10 15:09:36', 0),
(266, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **Attt**.', 'document_submissions.php?id=72', '2026-03-10 15:09:36', 0),
(267, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **Attt**.', 'document_submissions.php?id=72', '2026-03-10 15:09:36', 0),
(268, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **Attt**.', 'document_submissions.php?id=72', '2026-03-10 15:09:36', 0),
(269, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **Attt**.', 'document_submissions.php?id=72', '2026-03-10 15:09:36', 0),
(270, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **Attt**.', 'document_submissions.php?id=73', '2026-03-10 15:23:51', 0),
(271, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **Attt**.', 'document_submissions.php?id=73', '2026-03-10 15:23:51', 0),
(272, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **Attt**.', 'document_submissions.php?id=73', '2026-03-10 15:23:51', 0),
(273, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **Attt**.', 'document_submissions.php?id=73', '2026-03-10 15:23:51', 0),
(274, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **Attt**.', 'document_submissions.php?id=73', '2026-03-10 15:23:51', 0),
(275, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Project Proposal titled: \',xnlkanckas\' has been submitted by Heidi Bomuel (SK Official) from San Isidro.', 'document_submissions.php', '2026-03-10 15:26:25', 0),
(276, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Project Proposal titled: \'djnaknald\' has been submitted by Heidi Bomuel (SK Official) from San Isidro.', 'document_submissions.php', '2026-03-10 15:27:11', 0),
(277, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Project Proposal titled: \'Linggo ng Kabataan\' has been submitted by Heidi Bomuel (SK Official) from San Isidro.', 'document_submissions.php', '2026-03-10 15:50:55', 0),
(278, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Official', 'New Project Proposal titled: \'MUSIKATHA\' has been submitted by Heidi Bomuel (SK Official) from San Isidro.', 'document_submissions.php', '2026-03-10 15:51:34', 0),
(279, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **MINUTOS**.', 'document_submissions.php?id=74', '2026-03-12 17:13:02', 0),
(280, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **MINUTOS**.', 'document_submissions.php?id=74', '2026-03-12 17:13:02', 0),
(281, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **MINUTOS**.', 'document_submissions.php?id=74', '2026-03-12 17:13:02', 0),
(282, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **MINUTOS**.', 'document_submissions.php?id=74', '2026-03-12 17:13:02', 0),
(283, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **MINUTOS**.', 'document_submissions.php?id=74', '2026-03-12 17:13:02', 0),
(284, 'napulajoshua20@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **MINUTOS**.', 'document_submissions.php?id=75', '2026-03-12 17:14:00', 0),
(285, 'aljen@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **MINUTOS**.', 'document_submissions.php?id=75', '2026-03-12 17:14:00', 0),
(286, 'Shane@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **MINUTOS**.', 'document_submissions.php?id=75', '2026-03-12 17:14:00', 0),
(287, 'mik@gmail.com', 'San Isidro', 'SK Secretary', 'New document submitted by Heidi Bomuel (SK Chairman): **MINUTOS**.', 'document_submissions.php?id=75', '2026-03-12 17:14:00', 0),
(288, 'napola@gmail.com', 'San Isidro', 'SK Chairman', 'New document submitted by Heidi Bomuel (SK Chairman): **MINUTOS**.', 'document_submissions.php?id=75', '2026-03-12 17:14:00', 0);

-- --------------------------------------------------------

--
-- Table structure for table `status_log`
--

CREATE TABLE `status_log` (
  `id` int(11) NOT NULL,
  `submission_id` int(11) NOT NULL,
  `status` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `submissions`
--

CREATE TABLE `submissions` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `budget` decimal(10,2) NOT NULL,
  `objectives` text NOT NULL,
  `expected_outcome` text NOT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `submitted_by` varchar(255) NOT NULL,
  `submitter_user_id` int(11) DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `barangay` varchar(255) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `admin_doc` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `submissions`
--

INSERT INTO `submissions` (`id`, `title`, `description`, `budget`, `objectives`, `expected_outcome`, `status`, `submitted_by`, `submitter_user_id`, `document_path`, `barangay`, `rejection_reason`, `created_at`, `admin_doc`) VALUES
(40, 'Linggo ng Kabataan', 'clakncklnackan', '20000.00', 'wksncnaln', 'clkanklnaskl', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, 'uploads/proposal_69b03ddfe77b40.44534410.pdf', 'San Isidro', NULL, '2026-03-10 15:50:55', 'uploads/admin_docs/admin_doc_40_1773157943.docx'),
(41, 'MUSIKATHA', 'clksancklsankcaskn', '30000.00', 'cklasnkclnsakl', 'lcknsaklcnaskl', 'Approved', 'Bomuel from San Isidro, SK Official', NULL, 'uploads/proposal_69b03e06e432f0.54833515.pdf', 'San Isidro', NULL, '2026-03-10 15:51:34', 'uploads/admin_docs/admin_doc_41_1773157935.docx');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role` varchar(50) NOT NULL,
  `surname` varchar(255) NOT NULL,
  `firstname` varchar(255) NOT NULL,
  `middlename` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `mobile` varchar(15) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `id_document` varchar(255) DEFAULT NULL,
  `barangay` varchar(255) NOT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `civil_status` varchar(50) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `term_start` date DEFAULT NULL,
  `district` varchar(255) DEFAULT NULL,
  `house_no` varchar(50) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `purok` varchar(255) DEFAULT NULL,
  `municipality` varchar(255) DEFAULT NULL,
  `province` varchar(255) DEFAULT NULL,
  `complete_address` text DEFAULT NULL,
  `household_no` varchar(50) DEFAULT NULL,
  `occupation` varchar(255) DEFAULT NULL,
  `income_bracket` varchar(50) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role`, `surname`, `firstname`, `middlename`, `email`, `mobile`, `password`, `profile_photo`, `id_document`, `barangay`, `gender`, `dob`, `civil_status`, `position`, `term_start`, `district`, `house_no`, `street`, `purok`, `municipality`, `province`, `complete_address`, `household_no`, `occupation`, `income_bracket`, `status`, `created_at`) VALUES
(18, 'SK Official', 'Conejares', 'Shaun', '', 'shaun@gmail.com', '09981738972', '$2y$10$RD4AYQzu4LwlRzt2bJWxROI8EOEZ10/GA4zHji41WQ0BlzWhYqefO', 'uploads/profiles/68ff75960117d.jfif', 'uploads/documents/68ff7596016a6.jfif', 'San Francisco', 'Male', '2000-12-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 13:37:26'),
(25, 'SK Official', 'Lorida', 'Rommel', '', 'rommel@gmail.com', '09486090184', '$2y$10$bKpX9MLcZo8Qder75u0xrOzdkGvdnkIlg1b0pDVOLghjNRj1Zbaia', 'uploads/profiles/68ff86307929e.jfif', 'uploads/documents/68ff8630796a8.jfif', 'Panalaban', 'Male', '2000-02-02', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 14:48:16'),
(26, 'SK Official', 'Mercurio', 'Robin', '', 'robin@gmail.com', '09858941860', '$2y$10$KNvBKy5JunKWGZQ8nmFc3Ouy4RL5QOGvgFF4U12w3fTO1FDs3KPe2', 'uploads/profiles/68ff873dc9a32.jfif', 'uploads/documents/68ff873dca03a.jfif', 'Amonoy', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 14:52:45'),
(27, 'SK Official', 'Lorida', 'Ricardo', '', 'ricardo@gmail.com', '09813003219', '$2y$10$F4iyXNiRBGeIsSJNkKQ74eno3MKKICFsQIuqjUVH44DGEe/6cZK7K', 'uploads/profiles/68ff87bcbf6ff.jfif', 'uploads/documents/68ff87bcbfbab.jfif', 'Burgos', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 14:54:52'),
(28, 'SK Official', 'Breganza', 'Reisha Mae', 'Ariola', 'reishamae@gmail.com', '09384351165', '$2y$10$OJUYh7Bni9aJ5gSK8rUQvexV8eEkcIbecNUY6hUCxa2/VjG21UJeK', 'uploads/profiles/68ff885272485.jfif', 'uploads/documents/68ff885272955.jfif', 'Suba', 'Female', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 14:57:22'),
(29, 'SK Official', 'Manata', 'Reicelyn Izzy', 'Manata', 'reicelynizzy@gmail.com', '09651841074', '$2y$10$SAWmcsvWj5XTyovBxBBDOuD9BpC73rkg0.FEvoPBgbkkxBZoDiyKO', 'uploads/profiles/68ff88c0628ad.jfif', 'uploads/documents/68ff88c062cee.jfif', 'May-It', 'Female', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 14:59:12'),
(30, 'SK Official', 'Dela Cruz', 'RC', '', 'rc@gmail.com', '09159157111', '$2y$10$cQe/Sji2941Td4FER2upWe67E9gS6qKrajMoOHLRR1/HUZj8cmwkq', 'uploads/profiles/68ff893338aaa.jfif', 'uploads/documents/68ff893339016.jfif', 'Taytay', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:01:07'),
(31, 'SK Official', 'Coronado', 'Rainier', '', 'rainier@gmail.com', '09984549151', '$2y$10$RguvvYo2rykX6pVlUfNNgurSF5y7Md5Ee1D8u5tEpn4v69SXAzGAK', 'uploads/profiles/68ff89cb69f31.jfif', 'uploads/documents/68ff89cb6a296.jfif', 'Balanac', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:03:39'),
(32, 'SK Official', 'Lingo', 'Quentin', 'Dagamac', 'quentin@gmail.com', '09850456946', '$2y$10$8bniAsZfWnOktdNsMycPFeNUQhfabFJSsb9YXH3.6DdRDsjFzpE32', 'uploads/profiles/68ff8a5b78d7e.jfif', 'uploads/documents/68ff8a5b79191.jfif', 'Panglan', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:06:03'),
(33, 'SK Official', 'Brosas', 'Nica Mae', '', 'nicamae@gmail.com', '09663183393', '$2y$10$ZPzXZDUYG.hvSeMpjcbSteJS8MgOwhKVojWJ5JJ3g0q6Q2nCFfWB2', 'uploads/profiles/68ff8c89d6352.jfif', 'uploads/documents/68ff8c89d6cce.jfif', 'San Miguel', 'Female', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:15:21'),
(34, 'SK Official', 'Ronabio', 'Mc Joron', 'Averson', 'mcjoron@gmail.com', '09687554546', '$2y$10$y.Ffewha53BjZTvYS3Aeq.FeO3gFZLzKhe.QOqPRtEdOZdpx70.KC', 'uploads/profiles/68ff8cf5e5e02.jfif', 'uploads/documents/68ff8cf5e5fdd.jfif', 'Malinao', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:17:10'),
(35, 'SK Official', 'Ladines', 'Mark Anthony', '', 'markanthony@gmail.com', '09380668409', '$2y$10$Hilz/9iv36GGS85P.oaq8eWDXwldVfz8CMkIQu.eRTHDsepAVjynC', 'uploads/profiles/68ff8d6478e4b.jfif', 'uploads/documents/68ff8d647915e.jfif', 'Rizal', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:19:00'),
(36, 'SK Official', 'Anoche', 'Mark Andry', 'Cortez', 'markandry@gmail.com', '09455906643', '$2y$10$Vc3L8okqnsowBQ/Tj8uYkO/VZWdx2tmT5h44cGP.I7uiXWeHlJ7C2', 'uploads/profiles/68ff8dd9e2e76.jfif', 'uploads/documents/68ff8dd9e319a.jfif', 'San Roque', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:20:58'),
(37, 'SK Official', 'Monteiro', 'Mark Raven', '', 'markraven@gmail.com', '09454937672', '$2y$10$V2JNKx49NpD6BJW1kCpZv.h1pcddloWxaYp1oBksS5Yxs.EFXz/kC', 'uploads/profiles/68ff8e38ab72a.jfif', 'uploads/documents/68ff8e38ab929.jfif', 'Santa Catalina', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:22:32'),
(38, 'SK Official', 'Mark', 'Jhustine', '', 'jhustine@gmail.com', '09634258807', '$2y$10$IXmWXGxmaoiDzACIcZWWH..gJ9Fk.1Akgdwnq9ktaGKGjR6lHtVtm', 'uploads/profiles/68ff8f880ca8b.jfif', 'uploads/documents/68ff8f880ce67.jfif', 'Piit', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:28:08'),
(39, 'SK Official', 'Arapan', 'Lexus', '', 'lexus@gmail.com', '09872138378', '$2y$10$vVJT8POl8jaKG5b5IiYCV.u.leKe3TkSho8FGrT4i5jnmijlDR9Ke', 'uploads/profiles/68ff8fd0e9869.jfif', 'uploads/documents/68ff8fd0e9da4.jfif', 'Munting Kawayan', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:29:21'),
(40, 'SK Official', 'Bojabe', 'Lester', '', 'lester@gmail.com', '09067800113', '$2y$10$dz0bpTRjyRjUath5s7iEkOsMyBN.NWm10eQ/2hOnY6mbLp/.djci6', 'uploads/profiles/68ff903e53eec.jfif', 'uploads/documents/68ff903e541ad.jfif', 'Isabang', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:31:10'),
(41, 'SK Official', 'Derecho', 'Leonard', '', 'leonard@gmail.com', '09280466724', '$2y$10$qsbPstObog2QhZRT.oqrJOFCQu1j0QCmLAtqEaJUHutPSRQJLEYdS', 'uploads/profiles/68ff9086cc225.jfif', 'uploads/documents/68ff9086cc414.jfif', 'Coralao', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:32:22'),
(42, 'SK Official', 'Ordoñez', 'Lelaina', '', 'lelaina@gmail.com', '09532311691', '$2y$10$gR1b38fRooNRC/U0eK1e9.9REsg/uq8dyGL4XDVnt4SpPti0uq9JW', 'uploads/profiles/68ff910787002.jfif', 'uploads/documents/68ff9107872bb.jfif', 'Banti', 'Female', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:34:31'),
(43, 'SK Official', 'Fajardo', 'Joshua', '', 'joshua@gmail.com', '09858473634', '$2y$10$QRo6vy4YRyhEBineyc.z5.C12mgPXYh2cNwcf3pSOZhwP00cLusCq', 'uploads/profiles/68ff917a5f455.jfif', 'uploads/documents/68ff917a5f7d3.jfif', 'Gagalot', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:36:26'),
(44, 'SK Official', 'Arcenal', 'John Paul', '', 'johnpaul@gmail.com', '09555281809', '$2y$10$WB9j1PJLT/XVsDxDSPwaA.l1cBjCNZVves0X/m5cO2S7mFg1FO/QK', 'uploads/profiles/68ff91d60b55a.jfif', 'uploads/documents/68ff91d60b880.jfif', 'Botocan', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:37:58'),
(45, 'SK Official', 'Zornosa', 'Jm Nash', '', 'jmnash@gmail.com', '09850086984', '$2y$10$88YnN7Jbyo7eST8WFsFBf.gef3bCMRuNDrXeoPrvxIOHXf5qcKpC6', 'uploads/profiles/68ff924c289a7.jfif', 'uploads/documents/68ff924c28cb6.jfif', 'Pangil', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:39:56'),
(46, 'SK Official', 'Romero', 'Jeff', '', 'jeff@gmail.com', '09106910897', '$2y$10$MvCcQgxcq9JF/qr.8o3WAOn5gyPJqR0AjCLOj0ggFjB901wSCcfoy', 'uploads/profiles/68ff92a0090b9.jfif', 'uploads/documents/68ff92a00930d.jfif', 'Ilayang Banga', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-02', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:41:20'),
(47, 'SK Official', 'Gregana', 'Jazlyn Mae', '', 'jazlynmae@gmail.com', '09832789473', '$2y$10$ZvP449pgVk7C0cs1OfbOR.2gVwD4wbd.AXnSNIIOHkrVeIf1oZvue', 'uploads/profiles/68ff92eb80c4a.jfif', 'uploads/documents/68ff92eb80f73.jfif', 'Rizal', 'Female', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:42:35'),
(48, 'SK Official', 'Cangas', 'Jascha Russelle', 'Zoleta', 'jascharusselle@gmail.com', '09318004520', '$2y$10$jGmPOVvMHf31mnHu5asKNefMWENTKVRKig/ycvAjjaVlxuFwstPEG', 'uploads/profiles/68ff933a516bb.jfif', 'uploads/documents/68ff933a51a88.jfif', 'Talortor', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:43:54'),
(49, 'SK Official', 'Janzelle', 'Mutya', '', 'janzelle@gmail.com', '09695916392', '$2y$10$O95OkuX4VbYIRx0Sx8Mcc.xuqSsdH2GsMBSj9mWAieSi75XPU.E/W', 'uploads/profiles/68ff93b6bc71a.jfif', 'uploads/documents/68ff93b6bcb67.jfif', 'Bitaoy', 'Female', '2020-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:45:58'),
(50, 'SK Official', 'Cube', 'James', '', 'james@gmail.com', '09267765954', '$2y$10$FXclT3C3MIb99igrFEhw0.Fyi8zFU5rbi4I189.wlZbekSqkTKuqq', 'uploads/profiles/68ff93f87e11b.jfif', 'uploads/documents/68ff93f87e3a1.jfif', 'Villa Nogales', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:47:04'),
(51, 'SK Official', 'Salamat', 'James Bryan', '', 'jamesbryan@gmail.com', '09633621498', '$2y$10$t/kfU0PcFcESq/tira73.uFEn39I1WSQ9xFxsk683wI/3nHWKp1sa', 'uploads/profiles/68ff945e1315b.jfif', 'uploads/documents/68ff945e13583.jfif', 'Banilad', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:48:46'),
(52, 'SK Official', 'Millena', 'Jade Anne', '', 'jadeanne@gmail.com', '09972661624', '$2y$10$Fu14FhqK7iFJvSFwFqWew.ukCkFqiuU4BAJL7mtmHmK7ubgLx3ZAm', 'uploads/profiles/68ff94a881448.jfif', 'uploads/documents/68ff94a881651.jfif', 'Tanawan', 'Female', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:50:00'),
(53, 'SK Official', 'Bomuel', 'Heidi', '', 'napulajoshua20@gmail.com', '09983728917', '$2y$10$805jHmqj62rCI/v80fezZuwidzYYO4HaF2ptb5EIwKBnO5ufbK.p2', 'uploads/profiles/68ff9511b82c1.jfif', 'uploads/documents/68ff9511b85b3.jfif', 'San Isidro', 'Female', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-27 15:51:45'),
(54, 'SK Official', 'Buenaseda', 'Glenn', '', 'glenn@gmail.com', '09673621026', '$2y$10$DRVMJTxCd/3M04mKSaEecu2kVwVvg9A/3yeHMJbyz6ZlnSHN4TXim', 'uploads/profiles/69021b4946b3d.jfif', 'uploads/documents/69021b4946ec7.jfif', 'Ibabang Bayucain', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-29 13:48:57'),
(57, 'SK Official', 'Gonzaga', 'Girlie', 'Seno', 'girlie@gmail.com', '09633608452', '$2y$10$P6VwpN6dam6WjDIwTtm/b.XnzO8VdV3Jnmb/2noNZOs0zHXUM0rrS', 'uploads/profiles/69021f48e9ca4.jfif', 'uploads/documents/69021f48ea27a.jfif', 'Pook', 'Female', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-29 14:06:01'),
(58, 'SK Official', 'Clado', 'Errah', '', 'errah@gmail.com', '09656213149', '$2y$10$CQjOtYxQG.cWQMaVHcgxCOqnKZPPvZ.LAe34d6NxxyXOn14t/aaHu', 'uploads/profiles/69021f9595dc2.jfif', 'uploads/documents/69021f9595fd8.jfif', 'Origuel', 'Female', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-29 14:07:17'),
(59, 'SK Official', 'Aragañosa', 'Eron', '', 'eron@gmail.com', '09756827141', '$2y$10$sa2vGHbOW8WH/PUP/IMRHe3zCMyrknIs.Z8WiR39thk8YaN8mEt3W', 'uploads/profiles/69021fe83140b.jfif', 'uploads/documents/69021fe831779.jfif', 'Burol', 'Male', '2000-02-02', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-29 14:08:40'),
(60, 'SK Official', 'Teope', 'Daniela', 'Condino', 'daniela@gmail.com', '09764347849', '$2y$10$8OySB6bQoB3ayhFbCRc.euLpPQ1leBWrFqYiOUQbQjHX3g.bG8RI.', 'uploads/profiles/6902203a5d70a.jfif', 'uploads/documents/6902203a5d957.jfif', 'Ibabang Banga', 'Female', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-29 14:10:02'),
(61, 'SK Official', 'Dela Cruz', 'Czarina Jhezreel', '', 'czarina@gmail.com', '09092592449', '$2y$10$uX.M4UUjn/t.4Xm0a0WbvOwUENDqKau6ZMaLTmER3Z35zzgcjo0c2', 'uploads/profiles/69022098f371a.jfif', 'uploads/documents/69022098f3a59.jfif', 'Olla', 'Female', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-29 14:11:37'),
(62, 'SK Official', 'Fresco', 'Brian', '', 'brian@gmail.com', '09077432453', '$2y$10$bmDIA8IQZhmzZ/L9RpGJju4omd2awymlaWpwoM5eweLYlCSdFnJMe', 'uploads/profiles/6902215f03d97.jfif', 'uploads/documents/6902215f0417d.jfif', 'Oobi', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-29 14:14:55'),
(63, 'SK Official', 'Azucena', 'Alexa Jene', '', 'alexa@gmail.com', '09555823456', '$2y$10$K3TqUsJZslRs3kP1yh4KReJdVi7XV/EqqKIMjwlE2t33YkeIMr5im', 'uploads/profiles/690221f16a847.jfif', 'uploads/documents/690221f16b142.jfif', 'Bukal', 'Female', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-29 14:17:21'),
(64, 'SK Official', 'Biticon', 'AJ', 'Romulo', 'aj@gmail.com', '09652930258', '$2y$10$Pd8x1d2naiNg.VoXIME4Qe4.D6GCerOT0dtfNwlT7ZySk0xZHILQa', 'uploads/profiles/6902225d7c7ab.jfif', 'uploads/documents/6902225d7c9f5.jfif', 'Bakia', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-10-29 14:19:09'),
(68, 'SK Official', 'Roxas', 'Aljen Jovi', 'Coladilla', 'aljen@gmail.com', '09938217327', '$2y$10$qm1dhsB0lpQSUb1HxuKDGOS2q4rydct2JJCYIb/YzpzW1ufX9FgPi', 'uploads/profiles/69149665c3787.jpg', 'uploads/documents/69149665c3d8c.jpg', 'San Isidro', 'Male', '2004-04-15', 'Single', 'SK Chairman', '2020-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2025-11-12 14:15:01'),
(69, 'SK Official', 'Bomuel', 'Ericka Shane', '', 'Shane@gmail.com', '09319730921', '$2y$10$fRHJqCWmMCPHBhOBxm7YROyia9XuyqQPzyssJ9J/.UHl5YIvKJPzS', 'uploads/profiles/6957733174ed9.jfif', 'uploads/documents/6957733175329.jfif', 'San Isidro', 'Female', '2004-02-20', 'Single', 'SK Secretary', '2004-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2026-01-02 07:26:41'),
(70, 'SK Official', 'Areja', 'Luis Renzo', 'Rejano', 'luis@gmail.com', '09192709217', '$2y$10$8L8fNPfU5/AkQelvg8xuRurjdCP1AGSRhMmPsPa.Q0XSpyf5O41sK', 'uploads/profiles/69809f17740ac.jpg', 'uploads/documents/69809f17747db.jpg', 'Gagalot', 'Male', '2000-05-03', 'Single', 'SK Treasurer', '2004-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Approve', '2026-02-02 12:56:55'),
(71, 'SK Official', 'Ybuan', 'Mikhaela', 'Ramirez', 'mik@gmail.com', '09301973092', '$2y$10$XOz0KyD.7EBJmihMeXs9juf8RMQEuHIk4GOaObglqMvXLd34Ju5f6', 'uploads/profiles/6980a24fafa76.jpg', 'uploads/documents/6980a24faff22.jpg', 'San Isidro', 'Female', '2004-02-20', 'Single', 'SK Secretary', '2000-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Reject', '2026-02-02 13:10:39'),
(72, 'SK Official', 'ldmlsdnaknl', 'lskdncsl', 'lsdncklnckl', 'asjhsal@gmail.com', '09982173789', '$2y$10$ZIJCUoWcPJ4pWRPmTCcOC.IVG44u6HDOz/oPQZZfdV9xxKuPecrzK', 'uploads/profiles/6980a48b6d1fc.jpg', 'uploads/documents/6980a48b6d86f.jpg', 'Coralao', 'Male', '2000-02-20', 'Single', 'SK Secretary', '2000-02-20', 'Ditrict 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Reject', '2026-02-02 13:20:11'),
(73, 'SK Official', 'Napola', 'Joshua', 'Maala', 'napola@gmail.com', '09817264321', '$2y$10$UpfBQpLlLq2h7ao2NC210eIr6FwpkzSlO8MJpkkv7lWuu7wp3fJt6', NULL, NULL, 'San Isidro', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2000-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', NULL, NULL, NULL, NULL, 'Reject', '2026-02-04 13:10:07'),
(74, 'SK Official', 'lsncklan', 'udbhoidbwo', 'ankldcnakl', 'danldakl@gmail.com', '040932849', '$2y$10$KQH7mKwma32g6IhDhAmB8u5j/JkL9rWevt08d6STpxIXpY0y8imBO', '1770213383_profile_ACTS-DASHBOARD.png', '1770213383_id_ACTS-DASHBOARD.png', 'Amonoy', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2000-02-20', 'District 2', '3918739', '39494', 'kfsnklf', 'Majayjay', 'Laguna', '3918739 39494, Purok kfsnklf, Amonoy, Majayjay, Laguna', 'dlkanldka', 'csancnklsa', 'Low', 'Reject', '2026-02-04 13:56:23'),
(75, 'SK Official', 'Roxas', 'Aljen JOvi', 'Coladilla', 'rocas@gmail.com', '091831y8', '$2y$10$sPLzqxScRKOciHD.xDjwqeum9eM4v3hqU/vxbQ4oQ6d3lGRy2BAW6', '1770214274_profile_ACTS-DASHBOARD.png', '1770214274_id_ACTS-DASHBOARD.png', 'Gagalot', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2000-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', 'Gagalot, Majayjay, Laguna', NULL, NULL, NULL, 'Reject', '2026-02-04 14:11:14'),
(76, 'SK Official', 'clksdmcms', 'nxhlasl', 'lasmd;l', 'kankl@gmail.com', '09309182', '$2y$10$KsjFuZanrwEdpu4bXyqs/OLAmEKVESgybBwwheXsyJlLbesBTCuf.', '1770215150_profile_ACTS-DASHBOARD.png', '1770215150_id_ACTS-DASHBOARD.png', 'Bukal', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2000-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', 'Bukal, Majayjay, Laguna', NULL, NULL, NULL, 'Approve', '2026-02-04 14:25:50'),
(77, 'SK Official', 'Areja', 'Luis Renzo', 'Rejano', 'areja@gmail.com', '09398127397', '$2y$10$eei5SH6jJbmIgzB3PHcS5.T5SFFKYrzm8t.h7aTASWDeUOs9dGYXe', 'uploads/1770299723_profile_567a4bff1c77ebe1cd8725679d8fba7a.jpg', 'uploads/1770299723_id_567a4bff1c77ebe1cd8725679d8fba7a.jpg', 'Gagalot', 'Male', '2000-02-20', 'Single', 'SK Chairman', '2001-02-20', 'District 1', NULL, NULL, NULL, 'Majayjay', 'Laguna', 'Gagalot, Majayjay, Laguna', NULL, NULL, NULL, 'Reject', '2026-02-05 13:55:23'),
(78, 'SK Official', 'Roxas', 'Aljen Jovi', 'Coladilla', 'roxas@gmail.com', '09392183091', '$2y$10$jVP819QGzBee2QDbKNJI8e9EToCeqM1NmzK1mBNMibuAH1CkI3xsK', 'uploads/1770299899_profile_567a4bff1c77ebe1cd8725679d8fba7a.jpg', 'uploads/1770299899_id_567a4bff1c77ebe1cd8725679d8fba7a.jpg', 'Taytay', 'Male', '2000-02-20', 'Single', 'SK Treasurer', '2000-02-20', 'District 4', NULL, NULL, NULL, 'Majayjay', 'Laguna', 'Taytay, Majayjay, Laguna', NULL, NULL, NULL, 'Reject', '2026-02-05 13:58:19');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`);

--
-- Indexes for table `announcement_responses`
--
ALTER TABLE `announcement_responses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `document_archive`
--
ALTER TABLE `document_archive`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `document_submissions`
--
ALTER TABLE `document_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `financial_aid_requests`
--
ALTER TABLE `financial_aid_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `submitted_by` (`submitted_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recipient_id` (`recipient_id`);

--
-- Indexes for table `proposals`
--
ALTER TABLE `proposals`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `scholarship_applications`
--
ALTER TABLE `scholarship_applications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sk_list`
--
ALTER TABLE `sk_list`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `sk_notifications`
--
ALTER TABLE `sk_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sk_notifications_is_read` (`is_read`);

--
-- Indexes for table `status_log`
--
ALTER TABLE `status_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `submission_id` (`submission_id`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `submitted_by` (`submitted_by`);

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
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `announcement_responses`
--
ALTER TABLE `announcement_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `document_submissions`
--
ALTER TABLE `document_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `financial_aid_requests`
--
ALTER TABLE `financial_aid_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `proposals`
--
ALTER TABLE `proposals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scholarship_applications`
--
ALTER TABLE `scholarship_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `sk_list`
--
ALTER TABLE `sk_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `sk_notifications`
--
ALTER TABLE `sk_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=289;

--
-- AUTO_INCREMENT for table `status_log`
--
ALTER TABLE `status_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `submissions`
--
ALTER TABLE `submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `document_submissions`
--
ALTER TABLE `document_submissions`
  ADD CONSTRAINT `document_submissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `status_log`
--
ALTER TABLE `status_log`
  ADD CONSTRAINT `status_log_ibfk_1` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`),
  ADD CONSTRAINT `status_log_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
