-- Database Schema for STC English Learning Platform
-- Version: 1.0
-- Date: October 16, 2025
-- Database: stcenglishlearning

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================
-- 1. ADMIN_USERS TABLE
-- ============================================
CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `admin_role` enum('super_admin','admin') DEFAULT 'admin',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 2. USERS TABLE
-- ============================================
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `personal_email` varchar(255) DEFAULT NULL,
  `mobile_number` varchar(15) NOT NULL,
  `fcm_token` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `profile_picture` mediumblob DEFAULT NULL COMMENT 'Store profile picture as binary data',
  `profile_picture_type` varchar(50) DEFAULT NULL COMMENT 'Image MIME type: image/jpeg, image/png, etc',
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `current_state` varchar(100) DEFAULT NULL,
  `current_city` varchar(100) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `degree` varchar(100) DEFAULT NULL,
  `graduation_year` varchar(10) DEFAULT NULL,
  `program_category` varchar(50) DEFAULT NULL,
  `user_role` enum('user','admin','enterprise') DEFAULT 'user',
  `total_points` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `is_verified` tinyint(1) DEFAULT 0,
  `is_course` tinyint(1) DEFAULT 0,
  `device_type` varchar(25) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `google_uid` varchar(255) DEFAULT NULL,
  `auth_provider` enum('email','google','mobile') DEFAULT 'email',
  `enterprise_id` varchar(100) DEFAULT NULL,
  `is_email_verified` tinyint(1) DEFAULT 0,
  `is_mobile_verified` tinyint(1) DEFAULT 0,
  `is_books` tinyint(1) DEFAULT 0,
  `is_listening` tinyint(1) DEFAULT 0,
  `is_phrases` tinyint(1) DEFAULT 0,
  `is_speaking` tinyint(1) DEFAULT 0,
  `is_reading` tinyint(1) DEFAULT 0,
  `is_videos` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `google_uid` (`google_uid`),
  KEY `idx_mobile` (`mobile_number`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`user_role`),
  KEY `idx_user_active_role` (`is_active`,`user_role`),
  KEY `idx_auth_provider` (`auth_provider`),
  KEY `idx_enterprise_id` (`enterprise_id`),
  KEY `idx_graduation_year` (`graduation_year`),
  KEY `idx_department` (`department`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 3. ENTERPRISES TABLE
-- ============================================
CREATE TABLE `enterprises` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `enterprise_id` varchar(50) NOT NULL COMMENT 'Unique enterprise identifier',
  `user_id` int(11) DEFAULT NULL COMMENT 'Reference to users table',
  `enterprise_name` varchar(255) NOT NULL,
  `enterprise_logo` text DEFAULT NULL COMMENT 'Logo URL or path',
  `enterprise_description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  CONSTRAINT `enterprises_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. MOBILE_OTP TABLE
-- ============================================
CREATE TABLE `mobile_otp` (
  `otp_id` int(11) NOT NULL AUTO_INCREMENT,
  `mobile_number` varchar(15) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `otp_type` enum('signup','login','forgot_password') DEFAULT 'signup',
  `is_verified` tinyint(1) DEFAULT 0,
  `attempts` int(11) DEFAULT 0,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`otp_id`),
  KEY `idx_mobile_otp` (`mobile_number`,`otp_code`,`expires_at`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 5. COURSE_CATEGORIES TABLE
-- ============================================
CREATE TABLE `course_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(200) NOT NULL,
  `category_order` int(11) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 6. COURSES TABLE
-- ============================================
CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL AUTO_INCREMENT,
  `course_name` varchar(200) NOT NULL,
  `course_subtitle` varchar(255) DEFAULT NULL,
  `course_overview` text DEFAULT NULL,
  `course_outcomes` text DEFAULT NULL,
  `course_category_id` int(11) DEFAULT NULL,
  `course_image` varchar(255) DEFAULT NULL,
  `quize_image` varchar(244) NOT NULL,
  `level` varchar(20) DEFAULT NULL,
  `course_image_type` varchar(50) DEFAULT NULL COMMENT 'image/jpeg, image/png',
  `points` int(11) DEFAULT 0,
  `price` decimal(10,2) DEFAULT 0.00,
  `is_free` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`course_id`),
  KEY `idx_active` (`is_active`,`display_order`),
  KEY `fk_course_category` (`course_category_id`),
  CONSTRAINT `fk_course_category` FOREIGN KEY (`course_category_id`) REFERENCES `course_categories` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 7. LESSONS TABLE
-- ============================================
CREATE TABLE `lessons` (
  `lesson_id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `lesson_number` int(11) NOT NULL COMMENT 'Lesson 1, Lesson 2, etc',
  `lesson_title` varchar(200) NOT NULL,
  `lesson_overview` text NOT NULL COMMENT 'Overview/Description of the lesson',
  `lesson_content` longtext DEFAULT NULL COMMENT 'Main lesson content/material',
  `audio_file` varchar(244) DEFAULT NULL COMMENT 'Optional audio lesson',
  `audio_file_name` varchar(255) DEFAULT NULL,
  `audio_mime_type` varchar(50) DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL COMMENT 'Lesson duration in minutes',
  `points` int(11) DEFAULT 10,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `lesson_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`lesson_id`),
  UNIQUE KEY `unique_course_lesson` (`course_id`,`lesson_number`),
  KEY `idx_course_lesson` (`course_id`,`lesson_number`),
  CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 8. QUIZ TABLE
-- ============================================
CREATE TABLE `quiz` (
  `quiz_id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) NOT NULL,
  `option_d` varchar(255) NOT NULL,
  `correct_answer` enum('A','B','C','D') NOT NULL,
  `points` int(11) DEFAULT 10,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`quiz_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 9. ASSESSMENTS TABLE
-- ============================================
CREATE TABLE `assessments` (
  `assessment_id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) DEFAULT NULL,
  `assessment_name` varchar(255) NOT NULL,
  `assessment_description` text DEFAULT NULL,
  `assessment_image` varchar(500) DEFAULT NULL,
  `level` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`assessment_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `assessments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 10. ASSESSMENT_QUESTIONS TABLE
-- ============================================
CREATE TABLE `assessment_questions` (
  `question_id` int(11) NOT NULL AUTO_INCREMENT,
  `assessment_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `option_a` varchar(500) DEFAULT NULL,
  `option_b` varchar(500) DEFAULT NULL,
  `option_c` varchar(500) DEFAULT NULL,
  `option_d` varchar(500) DEFAULT NULL,
  `correct_option` enum('A','B','C','D') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`question_id`),
  KEY `assessment_id` (`assessment_id`),
  CONSTRAINT `assessment_questions_ibfk_1` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`assessment_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 11. USER_COURSE_PROGRESS TABLE
-- ============================================
CREATE TABLE `user_course_progress` (
  `progress_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `completed_lessons` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of completed lesson IDs' CHECK (json_valid(`completed_lessons`)),
  `last_completed_lesson_id` int(11) DEFAULT NULL,
  `current_lesson_id` int(11) DEFAULT NULL,
  `total_lessons` int(11) DEFAULT 0,
  `completed_lessons_count` int(11) DEFAULT 0,
  `completion_percentage` decimal(5,2) DEFAULT 0.00,
  `total_points_earned` int(11) DEFAULT 0,
  `quiz_completed` tinyint(1) DEFAULT 0,
  `quiz_score` int(11) DEFAULT 0,
  `quiz_total_points` int(11) DEFAULT 0,
  `course_completed` tinyint(1) DEFAULT 0,
  `started_at` timestamp NULL DEFAULT current_timestamp(),
  `last_accessed_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`progress_id`),
  UNIQUE KEY `unique_user_course` (`user_id`,`course_id`),
  KEY `idx_user_progress` (`user_id`),
  KEY `idx_course_progress` (`course_id`),
  KEY `idx_completion` (`course_completed`,`completion_percentage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 12. BOOK_CATEGORIES TABLE
-- ============================================
CREATE TABLE `book_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `category_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 13. BOOKS TABLE
-- ============================================
CREATE TABLE `books` (
  `book_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `book_title` varchar(255) NOT NULL,
  `book_author` varchar(255) NOT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `pdf_file` varchar(255) DEFAULT NULL,
  `is_popular` tinyint(1) DEFAULT 0,
  `is_recommended` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`book_id`),
  KEY `fk_category` (`category_id`),
  CONSTRAINT `fk_category` FOREIGN KEY (`category_id`) REFERENCES `book_categories` (`category_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 14. LISTENING_CONVERSATION TABLE
-- ============================================
CREATE TABLE `listening_conversation` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `category_description` text DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `points` int(10) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 15. LISTENING_CONVERSATION_QUESTIONS TABLE
-- ============================================
CREATE TABLE `listening_conversation_questions` (
  `question_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `audio_file` varchar(500) DEFAULT '',
  `audio_file_name` varchar(255) NOT NULL,
  `audio_file_size` int(11) DEFAULT NULL COMMENT 'File size in bytes',
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) NOT NULL,
  `option_d` varchar(255) NOT NULL,
  `correct_answer` enum('A','B','C','D') NOT NULL,
  `explanation` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`question_id`),
  KEY `idx_category` (`category_id`,`is_active`),
  CONSTRAINT `listening_conversation_questions_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `listening_conversation` (`category_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 16. LISTENING_DIFFERENCE TABLE
-- ============================================
CREATE TABLE `listening_difference` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `category_description` text DEFAULT NULL,
  `points` int(10) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 17. LISTENING_DIFFERENCE_QUESTIONS TABLE
-- ============================================
CREATE TABLE `listening_difference_questions` (
  `question_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `audio_file` varchar(244) NOT NULL COMMENT 'Store audio file as binary data',
  `audio_file_name` varchar(255) NOT NULL,
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) DEFAULT NULL,
  `option_d` varchar(255) DEFAULT NULL,
  `correct_answer` varchar(10) NOT NULL COMMENT 'A, B, C, or D',
  `explanation` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`question_id`),
  KEY `idx_category` (`category_id`,`is_active`),
  CONSTRAINT `listening_difference_questions_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `listening_difference` (`category_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 18. LISTENING_MISSWORDS TABLE
-- ============================================
CREATE TABLE `listening_misswords` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `category_description` text DEFAULT NULL,
  `points` int(11) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 19. LISTENING_MISSWORDS_QUESTIONS TABLE
-- ============================================
CREATE TABLE `listening_misswords_questions` (
  `question_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `question_text` text NOT NULL COMMENT 'Text with blanks marked as _____ or {1}, {2}, etc.',
  `audio_file` varchar(255) NOT NULL,
  `audio_file_name` varchar(255) NOT NULL,
  `audio_file_size` int(11) DEFAULT NULL COMMENT 'File size in bytes',
  `audio_mime_type` varchar(100) DEFAULT 'audio/mpeg',
  `correct_answers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array of correct answers: ["word1", "word2", "word3"]' CHECK (json_valid(`correct_answers`)),
  `multiple_options` longtext DEFAULT NULL,
  `total_blanks` int(11) NOT NULL,
  `blank_positions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Optional: Store exact positions of blanks' CHECK (json_valid(`blank_positions`)),
  `hint_text` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`question_id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 20. SPEAKING_REPEAT_AFTER TABLE
-- ============================================
CREATE TABLE `speaking_repeat_after` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `category_description` text DEFAULT NULL,
  `points` int(11) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 21. SPEAKING_REPEAT_AFTER_QUESTIONS TABLE
-- ============================================
CREATE TABLE `speaking_repeat_after_questions` (
  `question_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `sentence_text` text NOT NULL,
  `audio_file` varchar(244) NOT NULL COMMENT 'Store audio file as binary data',
  `audio_file_name` varchar(255) NOT NULL,
  `audio_file_size` int(11) DEFAULT NULL COMMENT 'File size in bytes',
  `phonetic_text` text DEFAULT NULL COMMENT 'Phonetic transcription',
  `points` int(11) DEFAULT 10,
  `tips` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`question_id`),
  KEY `idx_category` (`category_id`,`is_active`),
  CONSTRAINT `speaking_repeat_after_questions_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `speaking_repeat_after` (`category_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 22. SPEAKING_STORY20 TABLE
-- ============================================
CREATE TABLE `speaking_story20` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `category_description` text DEFAULT NULL,
  `points` int(11) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 23. SPEAKING_STORY20_QUESTIONS TABLE
-- ============================================
CREATE TABLE `speaking_story20_questions` (
  `story_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `story_title` varchar(200) DEFAULT NULL,
  `sentences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array of sentences: ["sentence1", "sentence2", ...]' CHECK (json_valid(`sentences`)),
  `total_sentences` int(11) NOT NULL,
  `time_limit` int(11) DEFAULT 20 COMMENT 'Time limit in seconds',
  `points` int(11) DEFAULT 20,
  `tips` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `audio_file` varchar(244) NOT NULL COMMENT 'Store audio file as binary data',
  `audio_file_name` varchar(255) NOT NULL,
  `audio_file_size` int(11) NOT NULL,
  PRIMARY KEY (`story_id`),
  KEY `idx_category` (`category_id`,`is_active`),
  CONSTRAINT `speaking_story20_questions_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `speaking_story20` (`category_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 24. READING_READALLOWED TABLE
-- ============================================
CREATE TABLE `reading_readallowed` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `category_description` text DEFAULT NULL,
  `points` int(10) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 25. READING_READALLOWED_QUESTIONS TABLE
-- ============================================
CREATE TABLE `reading_readallowed_questions` (
  `passage_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `passage_title` varchar(200) DEFAULT NULL,
  `passage_text` text NOT NULL,
  `word_count` int(11) DEFAULT NULL,
  `points` int(11) DEFAULT 15,
  `pronunciation_tips` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`passage_id`),
  KEY `idx_category` (`category_id`,`is_active`),
  CONSTRAINT `reading_readallowed_questions_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `reading_readallowed` (`category_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 26. READING_SPEEDREAD TABLE
-- ============================================
CREATE TABLE `reading_speedread` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `category_description` text DEFAULT NULL,
  `points` int(10) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 27. READING_SPEEDREAD_QUESTIONS TABLE
-- ============================================
CREATE TABLE `reading_speedread_questions` (
  `passage_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `passage_title` varchar(200) DEFAULT NULL,
  `passage_text` text NOT NULL,
  `timer_seconds` int(11) NOT NULL COMMENT 'Time limit for reading',
  `points` int(11) DEFAULT 10,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`passage_id`),
  KEY `idx_category` (`category_id`,`is_active`),
  CONSTRAINT `reading_speedread_questions_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `reading_speedread` (`category_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 28. PICTURE_CAPTURE TABLE
-- ============================================
CREATE TABLE `picture_capture` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(255) NOT NULL,
  `category_description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT NULL,
  `points` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 29. PICTURE_CAPTURE_QUESTIONS TABLE
-- ============================================
CREATE TABLE `picture_capture_questions` (
  `question_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `image_files` longtext DEFAULT NULL,
  `hint` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`question_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `picture_capture_questions_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `picture_capture` (`category_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 30. PICTURE_CAPTURE_LISTEN TABLE
-- ============================================
CREATE TABLE `picture_capture_listen` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(255) NOT NULL,
  `category_description` text DEFAULT NULL,
  `points` int(11) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 31. PICTURE_CAPTURE_LISTEN_QUESTIONS TABLE
-- ============================================
CREATE TABLE `picture_capture_listen_questions` (
  `question_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `image_files` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`image_files`)),
  `correct_image` varchar(255) DEFAULT NULL,
  `audio_file` varchar(500) DEFAULT NULL,
  `audio_file_name` varchar(255) DEFAULT NULL,
  `audio_file_size` int(11) DEFAULT NULL,
  `tips` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`question_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `picture_capture_listen_questions_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `picture_capture_listen` (`category_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 32. PHRASES_MAIN TABLE
-- ============================================
CREATE TABLE `phrases_main` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image_file` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 33. PHRASES_SUBCATEGORIES TABLE
-- ============================================
CREATE TABLE `phrases_subcategories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `main_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image_file` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `main_id` (`main_id`),
  CONSTRAINT `phrases_subcategories_ibfk_1` FOREIGN KEY (`main_id`) REFERENCES `phrases_main` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 34. PHRASES_LESSONS TABLE
-- ============================================
CREATE TABLE `phrases_lessons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subcategory_id` int(11) NOT NULL,
  `phrase_title` varchar(255) NOT NULL,
  `meaning` varchar(255) DEFAULT NULL,
  `examples` text DEFAULT NULL,
  `audio_file` varchar(500) DEFAULT NULL,
  `image_file` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `subcategory_id` (`subcategory_id`),
  CONSTRAINT `phrases_lessons_ibfk_1` FOREIGN KEY (`subcategory_id`) REFERENCES `phrases_subcategories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 35. VIDEO_CATEGORY TABLE
-- ============================================
CREATE TABLE `video_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 36. VIDEOS TABLE
-- ============================================
CREATE TABLE `videos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `video_title` varchar(255) NOT NULL,
  `category_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `video_file_path` varchar(500) DEFAULT NULL,
  `video_file_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `videos_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `video_category` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 37. USER_PROGRAM_ACTIVITY TABLE
-- ============================================
CREATE TABLE `user_program_activity` (
  `activity_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) UNSIGNED NOT NULL,
  `program_type` varchar(100) NOT NULL,
  `category_id` int(11) UNSIGNED NOT NULL,
  `current_question_id` int(11) UNSIGNED DEFAULT NULL,
  `category_score` decimal(5,2) DEFAULT 0.00 COMMENT 'Score percentage for this category',
  `category_points` int(11) DEFAULT 0 COMMENT 'Total points earned in this category',
  `status` enum('passed','failed','not_started') DEFAULT 'not_started',
  `passing_criteria` decimal(5,2) DEFAULT 70.00 COMMENT 'Minimum score percentage to pass',
  `time_spent_seconds` int(11) DEFAULT 0 COMMENT 'Total time spent in seconds',
  `last_activity_date` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`activity_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_program_type` (`program_type`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_user_program` (`user_id`,`program_type`),
  KEY `idx_user_category` (`user_id`,`program_type`,`category_id`),
  KEY `idx_status` (`status`),
  KEY `idx_last_activity` (`last_activity_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 38. USER_QUESTION_SUBMISSIONS TABLE
-- ============================================
CREATE TABLE `user_question_submissions` (
  `submission_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `activity_id` int(11) NOT NULL,
  `program_type` varchar(50) NOT NULL,
  `category_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `user_answer` text NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `points_earned` int(11) NOT NULL DEFAULT 0,
  `time_spent_seconds` int(11) NOT NULL DEFAULT 0,
  `revision_count` int(11) DEFAULT 0,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`submission_id`),
  KEY `idx_user_submissions` (`user_id`,`program_type`,`category_id`),
  KEY `idx_activity_submissions` (`activity_id`),
  KEY `idx_question_submissions` (`question_id`,`program_type`),
  KEY `idx_submitted_at` (`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 39. POINTS_HISTORY TABLE
-- ============================================
CREATE TABLE `points_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `points` int(11) NOT NULL,
  `point_type` enum('earned','redeemed','bonus','penalty') DEFAULT 'earned',
  `source` varchar(100) DEFAULT NULL COMMENT 'Source of points: exercise_name, daily_bonus, etc',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`),
  KEY `idx_user_points` (`user_id`,`created_at`),
  CONSTRAINT `points_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 40. USER_ACTIVITY_LOG TABLE
-- ============================================
CREATE TABLE `user_activity_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `activity_description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_user_activity` (`user_id`,`created_at`),
  CONSTRAINT `user_activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 41. SUBSCRIPTION_PLANS TABLE
-- ============================================
CREATE TABLE `subscription_plans` (
  `plan_id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_name` varchar(50) NOT NULL,
  `plan_description` text DEFAULT NULL,
  `duration_days` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`features`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 42. USER_SUBSCRIPTIONS TABLE
-- ============================================
CREATE TABLE `user_subscriptions` (
  `subscription_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `start_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_date` datetime NOT NULL,
  `payment_status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `amount_paid` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `auto_renew` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`subscription_id`),
  UNIQUE KEY `transaction_id` (`transaction_id`),
  KEY `plan_id` (`plan_id`),
  KEY `idx_user_subscription` (`user_id`,`is_active`),
  KEY `idx_end_date` (`end_date`),
  KEY `idx_subscription_active` (`user_id`,`is_active`,`end_date`),
  CONSTRAINT `user_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_subscriptions_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;