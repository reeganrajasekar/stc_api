
--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `email`, `password_hash`, `full_name`, `admin_role`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@gmail.com', '$2y$10$WVqxmaTSN2v8/ypF3.Fkt.RJHDkrGrwWPos5dHKksAZvXsmw0kl2e', 'Sakthi', 'admin', 1, '2025-10-15 03:22:17', '2025-10-06 15:51:12', '2025-10-15 03:22:17');


--
-- Dumping data for table `books`
--

INSERT INTO `books` (`book_id`, `category_id`, `book_title`, `book_author`, `thumbnail`, `pdf_file`, `is_popular`, `is_recommended`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'skskl', 'sslkskk', 'thumb_1759983078_68e735e687f70.png', 'book_1759983078_68e735e689ecb.pdf', 0, 0, 1, '2025-10-09 04:11:18', '2025-10-09 04:11:18'),
(2, 1, 'slkjslkj', 'slksjlkj', 'thumb_1759983273_68e736a93e721.jpg', 'book_1759983273_68e736a93eb41.pdf', 0, 0, 1, '2025-10-09 04:14:33', '2025-10-09 04:19:52'),
(3, 1, 'lskjslk', 'sjslkj', 'thumb_1759983643_68e7381b0a818.jpg', 'book_1759983643_68e7381b0af78.pdf', 1, 0, 1, '2025-10-09 04:20:43', '2025-10-09 04:20:43'),
(4, 1, 'slkjslk', 'shskjhslkjlk', 'thumb_1759983906_68e73922f1702.jpg', 'book_1759983875_68e7390340eae.pdf', 1, 1, 1, '2025-10-09 04:24:35', '2025-10-09 04:25:06'),
(5, 1, 'jlksjslkjlkjlk', 'lksjlksjlk', 'thumb_1759984086_68e739d604da8.jpg', 'book_1759984005_68e739853a52f.pdf', 1, 1, 1, '2025-10-09 04:26:45', '2025-10-09 04:28:06'),
(6, 1, 'slkjslkjslkjslksjslk', 'lskkjslksjlksjlk', 'thumb_1760437120_68ee23806fbcc.jpg', 'book_1760437120_68ee23806fda8.pdf', 0, 0, 1, '2025-10-14 10:18:40', '2025-10-14 10:18:40'),
(7, 1, 'lkjslksjlksjljslks', 'lskjslkjslkjlksjskl', 'thumb_1760437487_68ee24ef4b448.jpg', 'book_1760437487_68ee24ef4b796.pdf', 0, 0, 1, '2025-10-14 10:24:47', '2025-10-14 10:24:47'),
(8, 1, 'lkjslksjlksjljslks (Copy)', 'lskjslkjslkjlksjskliljlk', 'thumb_1760437487_68ee24ef4b448.jpg', 'book_1760437487_68ee24ef4b796.pdf', 0, 0, 1, '2025-10-14 10:24:59', '2025-10-14 10:25:09');

--
-- Dumping data for table `book_categories`
--

INSERT INTO `book_categories` (`category_id`, `category_name`, `category_order`, `created_at`, `updated_at`) VALUES
(1, 'sslkslsKJHKJHKJ', 1, '2025-10-09 04:10:19', '2025-10-14 10:01:59'),
(2, 'slkjslkjl', 2, '2025-10-09 04:28:19', '2025-10-14 10:01:59'),
(3, 'lskjslkjslksjlkjslks', 5, '2025-10-14 10:01:51', '2025-10-14 10:05:32'),
(4, 'slkjslksjlksjslkjlk', 4, '2025-10-14 10:04:57', '2025-10-14 10:05:32'),
(5, 'slksjlksjslkjlskjl', 3, '2025-10-14 10:05:32', '2025-10-14 10:05:32');

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `course_name`, `course_subtitle`, `course_overview`, `course_outcomes`, `course_category_id`, `course_image`, `quize_image`, `course_image_type`, `points`, `price`, `is_free`, `is_active`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 'sljkskjskkljslksjslk lskjjjjjjjjjjjjjjjjjj', 'lskjslkjslks', 'lskjslksjlksjlkjslksj', 'skljslkjslksjlksjslkjslksjlksjslkjslk', 1, 'course_1760083026_68e8bc52dcea7.jpg', 'quiz_1760110753_68e928a1c44ca.jpg', 'image/jpeg', 100, 100.00, 0, 1, 0, '2025-10-10 07:55:37', '2025-10-10 15:39:13'),
(2, 'sljkskjskkljslksjslk lskjjjjjjjjjjjjjjjjjjsssss', 'lskjslkjslkswwwwwwwwwwwww', 'lskjslksjlksjlkjslksjssss', 'skljslkjslksjlksjslkjslksjlksjslkjslk222222222222', 1, 'course_1760083026_68e8bc52dcea7.jpg', 'quiz_1760116007_68e93d27b5e78.jpg', 'image/jpeg', 100, 100.00, 0, 1, 0, '2025-10-10 07:58:56', '2025-10-10 17:06:47');

--
-- Dumping data for table `course_categories`
--

INSERT INTO `course_categories` (`category_id`, `category_name`, `category_order`, `created_at`, `updated_at`) VALUES
(1, 'slksjlk', 1, '2025-10-10 13:16:24', '2025-10-10 13:16:24'),
(2, 'KJLKLKJ55', 1, '2025-10-10 22:01:24', '2025-10-10 22:03:04');

-- --------------------------------------------------------

--
-- Dumping data for table `lessons`
--

INSERT INTO `lessons` (`lesson_id`, `course_id`, `lesson_number`, `lesson_title`, `lesson_overview`, `lesson_content`, `audio_file`, `audio_file_name`, `audio_mime_type`, `duration_minutes`, `points`, `is_active`, `display_order`, `lesson_image`, `created_at`, `updated_at`) VALUES
(11, 2, 9, 'SLKJSLK', 'LKSJLSK', '<p>SLKJSKJSLKJSLKS</p>', 'uploads/lessons/audio/2025/10/audio_20251010_151009_9a466955e68d07eb_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 'audio/mpeg', NULL, 10, 1, 0, 'lesson_1760100723_68e90173c1af8.jpg', '2025-10-10 12:52:03', '2025-10-10 13:10:09'),
(16, 2, 10, 'jljlkjlkj', 'lkjlkjlkj', '<p>jhlkjlkjj <strong>kkjlkj</strong></p>', 'uploads/lessons/audio/2025/10/audio_20251010_153239_cf1f360fe69831a9_ssvid.net--God-Shiva-shanku-sound-conch-sound-God-.mp3', 'ssvid.net--God-Shiva-shanku-sound-conch-sound-God-siva.mp3', 'audio/mpeg', NULL, 10, 1, 0, 'lesson_1760102986_68e90a4a60d1e.jpg', '2025-10-10 13:29:46', '2025-10-10 13:32:39'),
(17, 2, 11, 'jljlkjlkj (Copy)', 'lkjlkjlkj', '<p>jhlkjlkjj <strong>kkjlkj</strong></p>', 'uploads/lessons/audio/2025/10/audio_1760103202_68e90b2233b65.mp3', 'ssvid.net--God-Shiva-shanku-sound-conch-sound-God-siva.mp3', 'audio/mpeg', NULL, 10, 1, 0, 'lesson_1760102986_68e90a4a60d1e.jpg', '2025-10-10 13:33:22', '2025-10-10 13:33:22'),
(18, 2, 12, 'lkjlksjlksjlksjlk', 'lsjlskjslkjslk', '<p>slkjslkjslkjslksjjlks</p>', 'uploads/lessons/audio/2025/10/audio_20251010_153750_09969ee7fb712a17_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 'audio/mpeg', NULL, 10, 1, 0, 'lesson_1760103470_68e90c2e2d257.jpg', '2025-10-10 13:37:50', '2025-10-10 13:37:50');

-- --------------------------------------------------------

--
-- Dumping data for table `listening_conversation`
--

INSERT INTO `listening_conversation` (`category_id`, `category_name`, `category_description`, `display_order`, `points`, `is_active`, `created_at`) VALUES
(3, 'editingglskjslksj', 'slsjlksjl', 3, 30, 1, '2025-10-07 09:17:52'),
(4, 'new category ', 'new updte', 7, 10, 1, '2025-10-10 15:25:24'),
(6, 'slkjsksjlnew', 'lksjslk', 4, 0, 1, '2025-10-10 16:20:41'),
(7, 'new one', 'sljslkj', 2, 10, 1, '2025-10-10 16:59:02'),
(8, 'new one two', 'lkjslksjlk', 1, 0, 1, '2025-10-10 17:18:13'),
(9, 'slkjslksjslkjslkjslksjslk', 'slkjslkjslk', 5, 50, 1, '2025-10-13 13:51:08');

-- --------------------------------------------------------


--
-- Dumping data for table `listening_conversation_questions`
--

INSERT INTO `listening_conversation_questions` (`question_id`, `category_id`, `question_text`, `audio_file`, `audio_file_name`, `audio_file_size`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `explanation`, `is_active`, `created_at`, `updated_at`) VALUES
(26, 3, 'kljlkjlkjddddddd', 'uploads/audio/2025/10/audio_20251007_160640_01f7bf06b31cd5c4_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'jklk', 'jl', 'kj', 'lkj', 'B', '0', 1, '2025-10-07 14:06:40', '2025-10-07 14:06:40'),
(27, 3, 'kljlkjlkjddddddd (Copy)', 'uploads/audio/audio_1759846513_68e520712717d.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'jklk', 'jl', 'kj', 'lkj', 'B', '0', 1, '2025-10-07 14:15:13', '2025-10-07 14:15:13'),
(28, 4, 'slkjslkj lskjlksjlsk sljlksj', 'uploads/audio/2025/10/audio_20251010_172611_44ca1681542386f2_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'jlskjslkjkjl', 'lkdlkjlkj', 'ldjlkj', 'ldkjlk', 'A', '0', 1, '2025-10-10 15:26:11', '2025-10-10 15:26:23'),
(29, 4, 'slkjslkj lskjlksjlsk sljlksj (Copy)', 'uploads/audio/audio_1760109986_68e925a22818e.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'jlskjslkjkjl', 'lkdlkjlkj', 'ldjlkj', 'ldkjlk', 'A', '0', 1, '2025-10-10 15:26:26', '2025-10-10 15:26:26'),
(30, 6, 'sljslksjlksjlkssslkjlkjlkjlk', 'uploads/audio/2025/10/audio_20251010_185958_b9bf1d6d0c801cc5_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'slkjslk', 'lskjlk', 'lkjslk', 'lskjslk', 'B', '0', 1, '2025-10-10 16:59:58', '2025-10-13 18:25:19'),
(32, 8, 'sjslkj lskjslkjs', 'uploads/audio/2025/10/audio_20251010_191847_cbfbfcc7af3d5b2f_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'lkjlksj', 'lkjlkjlkj', 'lkjslkjl', 'kjlskjl', 'B', '0', 1, '2025-10-10 17:18:47', '2025-10-10 17:18:47'),
(33, 8, 'sjslkj lskjslkjs (Copy)', 'uploads/audio/audio_1760116743_68e940070ca34.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'lkjlksj', 'lkjlkjlkj', 'lkjslkjl', 'kjlskjl', 'B', '0', 1, '2025-10-10 17:19:03', '2025-10-10 17:19:03');

-- --------------------------------------------------------

--
-- Dumping data for table `listening_difference`
--

INSERT INTO `listening_difference` (`category_id`, `category_name`, `category_description`, `points`, `display_order`, `is_active`, `created_at`) VALUES
(1, 'bbbbbbbbb', 'hgggggggggggg', 30, 2, 1, '2025-10-07 10:38:10'),
(2, 'lkjlkj', 'lkjlks', 20, 7, 1, '2025-10-10 15:56:22'),
(3, ';jlkjl', 'lkjlkj', 30, 3, 1, '2025-10-10 16:27:37'),
(5, 'kjslkjsl', 'lsjlsjkl', 50, 6, 1, '2025-10-13 15:52:16'),
(6, 'jjlkjljlkjl', 'jljlkjlk', 10, 5, 1, '2025-10-13 17:24:27'),
(7, 'kjlkjlk', 'jlkj', 20, 1, 1, '2025-10-13 19:12:57');

-- --------------------------------------------------------


--
-- Dumping data for table `listening_difference_questions`
--

INSERT INTO `listening_difference_questions` (`question_id`, `category_id`, `question_text`, `audio_file`, `audio_file_name`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `explanation`, `is_active`, `created_at`, `updated_at`) VALUES
(10, 2, 'sjlskjlkjlkjlskjslkj', 'uploads/audio/2025/10/audio_20251013_114041_f1d5895d1a7654b9_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 'slkjslkj', 'lksjlkj', NULL, NULL, 'A', '', 1, '2025-10-13 09:40:41', '2025-10-13 09:40:41'),
(11, 2, 'sljslkjslkj', 'uploads/audio/2025/10/audio_20251013_123213_76226a2233ce4a2e_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 'slkjslk', 'lskjlsk', NULL, NULL, 'A', '', 1, '2025-10-13 10:32:13', '2025-10-13 10:32:13'),
(13, 2, 'lkslskjslkjslksjlkjl', 'uploads/audio/2025/10/audio_20251013_181902_08514e4a26aa7c99_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 'kslkjslkj', 'lsjlkj', NULL, NULL, 'A', '', 1, '2025-10-13 16:19:02', '2025-10-13 16:19:02'),
(14, 2, 'kjslkjsslkjs,snsn', 'uploads/audio/2025/10/audio_20251013_182419_9e34bdf23e019148_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 'lkjslkjslk', 'lkjslk', NULL, NULL, 'A', '', 1, '2025-10-13 16:24:19', '2025-10-13 16:24:19'),
(15, 2, 'lkjlkjlkjlkjlk', 'uploads/audio/2025/10/audio_20251013_201625_50a75f7934eec65c_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 'jlkjlk', 'jlkj', NULL, NULL, 'A', '', 1, '2025-10-13 18:16:25', '2025-10-13 18:16:25'),
(16, 5, 'lkjslkjslkjkjlkjlkjlkj', 'uploads/audio/2025/10/audio_20251013_201854_74705d0eb2d8d0cc_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 'lkjlkjklj', 'lkjl', NULL, NULL, 'A', '', 1, '2025-10-13 18:16:56', '2025-10-13 18:19:06'),
(17, 1, 'kllkjlkjlkjlkjlkj', 'uploads/audio/2025/10/audio_20251013_202555_63918e081b217cf4_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 'lkjl22222222', 'kjl', NULL, NULL, 'A', '', 1, '2025-10-13 18:25:55', '2025-10-13 18:26:53'),
(18, 6, 'lsjkslkjslkskl₹lj', 'uploads/audio/2025/10/audio_20251013_204146_f8f9c1106d4b86df_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 'slkjslkj', 'lskjslkjl', NULL, NULL, 'A', '', 1, '2025-10-13 18:41:46', '2025-10-13 18:41:46'),
(19, 7, 'jlkjlkjlkjjlkjlk', 'uploads/audio/2025/10/audio_20251013_211330_991def45f63db9c1_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 'lkj', 'lkjl', NULL, NULL, 'A', '', 1, '2025-10-13 19:13:30', '2025-10-13 19:13:39'),
(20, 7, 'jlkjlkjjljlkjkllkjkk', 'uploads/audio/2025/10/audio_20251013_211927_44bd410428628408_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 'lkjlkj', 'lkjl', NULL, NULL, 'A', '', 1, '2025-10-13 19:19:27', '2025-10-13 19:20:54'),
(21, 7, 'jljjkjlkjlkjljk', 'uploads/audio/2025/10/audio_20251013_212114_b310d67e0d286533_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 'lkj', 'lkj', NULL, NULL, 'A', '', 1, '2025-10-13 19:21:14', '2025-10-13 19:21:35');

-- --------------------------------------------------------



--
-- Dumping data for table `listening_misswords`
--

INSERT INTO `listening_misswords` (`category_id`, `category_name`, `category_description`, `points`, `display_order`, `is_active`, `created_at`) VALUES
(2, '2111111lksjlsk', 'slkjslk', 10, 8, 1, '2025-10-10 16:25:25'),
(4, 'lkjlkjlkjlkjlkjlk', 'kjhkjhkjlk', 10, 11, 1, '2025-10-13 17:11:11'),
(5, 'lkjkljlkjlk', 'ljlkjlk', 10, 5, 1, '2025-10-13 17:12:38'),
(6, 'jlkjlkjlkjlkjlk', 'jlkjlkjlkjlk', 10, 7, 1, '2025-10-13 17:25:00'),
(7, 'jkjlkjlkjl', 'lkjlkjl', NULL, 6, 1, '2025-10-13 19:04:11'),
(8, 'kjlkjlkj', 'lkjl', NULL, 4, 1, '2025-10-13 19:04:34'),
(10, 'jklkjlklkjlk', 'lkjlkjlkjlkj', 50, 3, 1, '2025-10-13 19:10:11'),
(11, 'slkjslkjslk', 'slkjslkjs', 32, 1, 1, '2025-10-14 13:52:38');

-- --------------------------------------------------------


--
-- Dumping data for table `listening_misswords_questions`
--

INSERT INTO `listening_misswords_questions` (`question_id`, `category_id`, `question_text`, `audio_file`, `audio_file_name`, `audio_file_size`, `audio_mime_type`, `correct_answers`, `multiple_options`, `total_blanks`, `blank_positions`, `hint_text`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'slkjslksjkl_____slkjslk_____lkjslksj_____skjslk__________kjkjhkjhkjh_____', 'uploads/audio/2025/10/audio_20251007_175200_e46eb23b0f2a2fc7_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'audio/mpeg', '[\"kljlkjlkl\",\"slkjslk\",\"slkjslk\",\"slkj\",\"lkjlkjlkjlkjkl\",\"jkhljlkj\"]', NULL, 6, NULL, '', 1, '2025-10-07 15:52:00', '2025-10-07 16:37:29'),
(2, 2, 'jslksjlskjsl_____slkjslkjlk_____jslsjlsk_____slkjslkj_____slkjslk_____ssss', 'uploads/audio/2025/10/audio_20251010_190137_19af7684ce0d3d89_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'audio/mpeg', '[\"slkjslkj\",\"slkjslk\",\"lskjlk\",\"slkj\",\"lkslkj\"]', NULL, 5, NULL, '', 1, '2025-10-10 17:01:37', '2025-10-10 17:01:37'),
(3, 3, 'slkjslkj_____lkjslksjl_____slkjslk_____lksjlk_____kjslksj_____slsjlk', 'uploads/audio/2025/10/audio_20251010_192009_0a0bf562ce8d453f_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'audio/mpeg', '[\"slkjslk\",\"slkjlkl\",\"slkjslkj\",\"slkjslkj\",\"slkjlksjslk\"]', NULL, 5, NULL, '', 1, '2025-10-10 17:20:09', '2025-10-10 17:20:09'),
(4, 2, 'slkjslkj_____slkjslkj_____ljkjlk', 'uploads/audio/2025/10/audio_20251013_114251_17d1a01de7b37063_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'audio/mpeg', '[\"sjlkjslk\",\"ljljsl\"]', NULL, 2, NULL, '', 1, '2025-10-13 09:42:51', '2025-10-13 18:38:20'),
(5, 1, 'jljslkjslkj_____lkjlkjlk_____kjlkjl', 'uploads/audio/2025/10/audio_20251013_192915_7404daeba6b41442_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'audio/mpeg', '[\"ljkjlkj\",\"ljlkjlkj\"]', NULL, 2, NULL, 'l', 1, '2025-10-13 17:29:15', '2025-10-13 17:29:15'),
(6, 2, 'jlkjlkjlkjlkjlkjlkjlkjlk_____', 'uploads/audio/2025/10/audio_20251013_194956_23e5166213d2788d_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'audio/mpeg', '[\"lkjlkjlkjklj\"]', NULL, 1, NULL, '', 1, '2025-10-13 17:49:56', '2025-10-13 17:49:56'),
(7, 2, 'lskjslkjslkjk_____', 'uploads/audio/2025/10/audio_20251013_204645_73036a53ba61749e_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'audio/mpeg', '[\"skjslkjslk\"]', NULL, 1, NULL, '', 1, '2025-10-13 18:46:45', '2025-10-13 18:48:23'),
(8, 5, 'jkslkjslkjslkjsss_____', 'uploads/audio/2025/10/audio_20251013_204901_aaad32b153290dd0_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'audio/mpeg', '[\"lsjlkssljsjlkss\"]', NULL, 1, NULL, 'skjlsk', 1, '2025-10-13 18:49:01', '2025-10-13 18:49:15'),
(9, 2, 'lskjksjlk_____', 'uploads/audio/2025/10/audio_20251013_205120_3964acfe38c365b0_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'audio/mpeg', '[\"slkjslkjkslkjslk\"]', NULL, 1, NULL, '', 1, '2025-10-13 18:51:20', '2025-10-13 18:51:30'),
(10, 6, 'slkjslkjslkjslkjslk_____sss', 'uploads/audio/2025/10/audio_20251013_205709_284c242c56a760b0_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'audio/mpeg', '[\"lkjlskjlkjlkjslkjslsjlk22\"]', NULL, 1, NULL, '', 1, '2025-10-13 18:57:01', '2025-10-13 19:03:24'),
(11, 10, 'jlkjlkjlk _____kjlkkjlk', 'uploads/audio/2025/10/audio_20251013_211047_b4c4729bf12c4f7b_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'audio/mpeg', '[\"lkjlkjlk\"]', NULL, 1, NULL, '', 1, '2025-10-13 19:10:47', '2025-10-13 19:10:58'),
(12, 10, 'hlkjljkl_____kjlk', 'uploads/audio/2025/10/audio_20251013_211406_50f5f601692bbb2d_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'audio/mpeg', '[\"lkjlkj\"]', NULL, 1, NULL, 'jlj', 1, '2025-10-13 19:14:06', '2025-10-13 19:14:15'),
(13, 10, 'hlkjljkl_____kjlk (Copy)kk', 'uploads/audio/audio_1760382865_68ed4f919a48b.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'audio/mpeg', '[\"lkjlkj\"]', NULL, 1, NULL, 'jlj', 1, '2025-10-13 19:14:25', '2025-10-13 19:14:36'),
(14, 11, 'SLLKJSLKJSKLJSLK_____KSSLKJ', 'uploads/audio/2025/10/audio_20251014_161117_273aac82f8389a57_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'audio/mpeg', '[\"SJSLKSJ\"]', '0', 1, NULL, '0', 1, '2025-10-14 14:11:17', '2025-10-14 14:12:07'),
(15, 11, 'LKKJLKJLKJK_____', 'uploads/audio/2025/10/audio_20251014_161452_4f191f8e9c3f4256_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'audio/mpeg', '[\"LKJLKJLK\"]', '0', 1, NULL, '0', 1, '2025-10-14 14:14:52', '2025-10-14 14:17:25'),
(16, 11, 'SLKJSKJLKJSLK_____', 'uploads/audio/2025/10/audio_20251014_162215_8aa86f931df764a2_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'audio/mpeg', '[\"LSKJSLKJSLKJ\"]', '[\"SLSJLKJSL\",\"SKJSLKS\"]', 1, NULL, '', 1, '2025-10-14 14:22:15', '2025-10-14 14:22:42'),
(23, 11, 'skljslksjlk_____', 'uploads/audio/2025/10/audio_20251014_165147_95fe7cfee73b5d7c_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'audio/mpeg', '[\"slksjlksjlk\"]', '[\"slkjslkjslk\",\"slkjslk\"]', 1, NULL, '', 1, '2025-10-14 14:51:47', '2025-10-14 14:51:47'),
(24, 11, 'skjslkjslkjslkjlk_____', 'uploads/audio/2025/10/audio_20251015_052351_a4ce13b82d0ae062_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'audio/mpeg', '[\"lskjslkjskl\"]', '[\"lkjslksjlk\",\"lsjlskjslk\"]', 1, NULL, '', 1, '2025-10-15 03:23:51', '2025-10-15 03:23:51'),
(25, 11, 'sjlskjlksjslkjsk_____', 'uploads/audio/2025/10/audio_20251015_052901_e22c419a932aa730_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'audio/mpeg', '[\"slkjslksjlk\"]', '[\"slkjslkjslk\",\"lsjslkjslksjlk\",\"lksjlskjslkjslk\"]', 1, NULL, '', 1, '2025-10-15 03:29:01', '2025-10-15 03:29:01'),
(26, 11, 'skjslkjlksjslkjslkjlk_____', 'uploads/audio/2025/10/audio_20251015_052928_592a10a047c2e34f_LearningEnglishConversations-20250923-TheEnglishWe.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, 'audio/mpeg', '[\"slkjskljskl\"]', '[\"sjljksjsjl \",\"ojlkjlkjlk\",\"lkjlkjlkjl\",\"lkjlkjl\"]', 1, NULL, '', 1, '2025-10-15 03:29:28', '2025-10-15 03:35:08');

-- --------------------------------------------------------


--
-- Dumping data for table `picture_capture`
--

INSERT INTO `picture_capture` (`category_id`, `category_name`, `category_description`, `points`, `display_order`, `is_active`, `created_at`) VALUES
(1, 'lskjslkjlskjslk', 'lkjslkjslksjlkj', 26, 4, 1, '2025-10-14 11:34:48'),
(2, 'slksjlksjslk', 'lksjlksjslk', 25, 1, 1, '2025-10-14 13:41:49');

-- --------------------------------------------------------


--
-- Dumping data for table `picture_capture_questions`
--

INSERT INTO `picture_capture_questions` (`question_id`, `category_id`, `question_text`, `image_files`, `correct_image`, `audio_file`, `audio_file_name`, `audio_file_size`, `tips`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 1, 'slkjslksjklsjlk', '[\"uploads\\/images\\/2025\\/10\\/img_68ee3f1dabba17.88195205.png\",\"uploads\\/images\\/2025\\/10\\/img_68ee3f1dabf3f2.98816825.jpg\",\"uploads\\/images\\/2025\\/10\\/img_68ee52a95ab323.86010792.jpg\"]', 'image3', 'uploads/audio/2025/10/audio_68ee503d290155.17503121.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, '', 1, '2025-10-14 12:10:01', '2025-10-14 13:39:53'),
(5, 2, 'slkjslksjlskjlskjlk', '[\"uploads\\/images\\/2025\\/10\\/img_68ee53580df821.42737809.jpg\",\"uploads\\/images\\/2025\\/10\\/img_68ee53580e8e18.69072672.jpg\"]', 'image2', 'uploads/audio/2025/10/audio_68ee5358177e98.51024699.mp3', 'LearningEnglishConversations-20250923-TheEnglishWeSpeakNoLegToStandOn.mp3', 2958974, '', 1, '2025-10-14 13:42:48', '2025-10-14 13:42:48');

-- --------------------------------------------------------


--
-- Dumping data for table `quiz`
--

INSERT INTO `quiz` (`quiz_id`, `course_id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `points`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 2, 'jlkjslkjs', 'lksjlksj', 'slkjslk', 'lskjslkj', 'lksjlks', 'A', 10, 1, '2025-10-10 15:07:55', '2025-10-10 15:07:55'),
(2, 2, 'sljkslksjlk', 'slkjslkj', 'slkjslk', 'kljslk', 'slkjs', 'A', 10, 1, '2025-10-10 15:08:05', '2025-10-10 15:08:05'),
(3, 1, 'sljkslkjslkj', 'lkjslksjl', 'lkjslkj', 'lskjslkj', 'lskjslkj', 'A', 10, 1, '2025-10-10 15:38:57', '2025-10-10 15:38:57'),
(4, 2, 'new update', 'jkllkl', 'jlkjlk', 'jlkj', 'lkjlk', 'B', 10, 1, '2025-10-10 17:07:47', '2025-10-10 17:10:37');

-- --------------------------------------------------------


--
-- Dumping data for table `reading_readallowed`
--

INSERT INTO `reading_readallowed` (`category_id`, `category_name`, `category_description`, `points`, `display_order`, `is_active`, `created_at`) VALUES
(1, 'ssk;lk', 'js;lks;lk', NULL, 8, 1, '2025-10-08 15:36:54'),
(4, 'jljklkjlkjlk', 'lkjlkjlk', 15, 6, 1, '2025-10-14 08:40:49'),
(6, 'slsklskjslkjslk', 'lksjlksjlskjslkj', 49, 7, 1, '2025-10-14 08:45:56'),
(7, 'klsjlksjslkj', 'lksjlksjlskj', 48, 5, 1, '2025-10-14 14:40:33'),
(8, 'sijslkjslkj', 'kljslksjlkj', 9, 2, 1, '2025-10-14 14:46:32'),
(9, 'ljljlkj', 'lkjslkjs', 10, 1, 1, '2025-10-14 14:48:17');

-- --------------------------------------------------------


--
-- Dumping data for table `reading_readallowed_questions`
--

INSERT INTO `reading_readallowed_questions` (`passage_id`, `category_id`, `passage_title`, `passage_text`, `word_count`, `points`, `pronunciation_tips`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'slksjslkj', 'lskjslkjl skjlslks lskjslk kkkk jljlkjlk ljkl', 6, 15, 'lk', 1, '2025-10-08 15:37:18', '2025-10-08 15:37:42'),
(2, 1, 'slksjslkj (Copy)', 'lskjslkjl skjlslks lskjslk kkkk jljlkjlk ljkl', 6, 15, 'lk', 1, '2025-10-08 15:37:47', '2025-10-08 15:37:47'),
(3, 1, 'lkjlk', 'lkjlkj lkjlk lkj ljl l', 5, 15, '', 1, '2025-10-08 15:38:34', '2025-10-08 15:38:34'),
(4, 1, 'lkjlkjl', 'slkjslkslkjlskjlskjslksjlskjsl', 1, 15, '', 1, '2025-10-10 17:04:54', '2025-10-10 17:04:54'),
(5, 1, 'jlkjlkjlkjkk', 'lkjlkjlkkljlkjkllkjlk', 1, 15, '', 1, '2025-10-13 19:37:01', '2025-10-13 19:42:05'),
(6, 1, 'lksjlskj', 'ljslksjlslkjslsk', 1, 15, '', 1, '2025-10-13 19:58:13', '2025-10-13 19:58:13'),
(7, 1, 'sljslkjslkjskl', 'lsjslkjslksjlk', 1, 15, '', 1, '2025-10-14 08:39:32', '2025-10-14 08:39:32'),
(8, 1, 'kjhlkjlkjjjjjjjj', 'lkjlkjlkjljlkjlk', 1, 15, '', 1, '2025-10-14 08:40:36', '2025-10-14 08:40:36');

-- --------------------------------------------------------

--
-- Dumping data for table `reading_speedread`
--

INSERT INTO `reading_speedread` (`category_id`, `category_name`, `category_description`, `points`, `display_order`, `is_active`, `created_at`) VALUES
(6, 'skjlsjlksjl', 'lkjlskjslk', 24, 2, 1, '2025-10-14 14:40:02'),
(7, 'slkjslksjlk', 'jlkjslkjslkj', 10, 3, 1, '2025-10-14 14:42:49');

-- --------------------------------------------------------

--
-- Table structure for table `reading_speedread_questions`
--

--
-- Dumping data for table `speaking_repeat_after`
--

INSERT INTO `speaking_repeat_after` (`category_id`, `category_name`, `category_description`, `points`, `display_order`, `is_active`, `created_at`) VALUES
(1, 'sklslkjslk', 'jslkjslk', NULL, 4, 1, '2025-10-07 16:59:38'),
(2, 'lllllllllllllllllll', 'kkkkkkkk', NULL, 3, 1, '2025-10-07 17:00:23'),
(3, 'lskjlksjslkjsl', 'lksjlksjls', 25, 1, 1, '2025-10-14 07:17:21');

-- --------------------------------------------------------

--
-- Table structure for table `speaking_repeat_after_questions`
--
