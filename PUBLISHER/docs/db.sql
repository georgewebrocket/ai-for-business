-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Εξυπηρετητής: localhost:3306
-- Χρόνος δημιουργίας: 05 Μάη 2026 στις 14:47:37
-- Έκδοση διακομιστή: 10.3.39-MariaDB
-- Έκδοση PHP: 8.4.19

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Βάση δεδομένων: `aaw_aipublisher`
--

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `accounts`
--

CREATE TABLE `accounts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `company_name` varchar(190) DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `account_users`
--

CREATE TABLE `account_users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `role` enum('owner','admin','editor','author','viewer') NOT NULL DEFAULT 'viewer',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `ai_generation_logs`
--

CREATE TABLE `ai_generation_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `property_id` bigint(20) UNSIGNED DEFAULT NULL,
  `content_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `content_idea_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action_type` enum('suggest_title','suggest_summary','generate_article','rewrite_article','generate_tags','generate_social_post','check_similarity') NOT NULL,
  `provider` varchar(80) DEFAULT 'openai',
  `model` varchar(100) DEFAULT NULL,
  `prompt` longtext DEFAULT NULL,
  `response` longtext DEFAULT NULL,
  `tokens_input` int(10) UNSIGNED DEFAULT NULL,
  `tokens_output` int(10) UNSIGNED DEFAULT NULL,
  `cost_estimate` decimal(10,4) DEFAULT NULL,
  `status` enum('success','failed') DEFAULT 'success',
  `error_message` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `ai_profiles`
--

CREATE TABLE `ai_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `property_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `provider` varchar(80) DEFAULT 'openai',
  `model` varchar(100) NOT NULL,
  `temperature` decimal(3,2) DEFAULT 0.70,
  `max_tokens` int(10) UNSIGNED DEFAULT NULL,
  `system_prompt` text DEFAULT NULL,
  `default_writing_style_id` bigint(20) UNSIGNED DEFAULT NULL,
  `default_template_id` bigint(20) UNSIGNED DEFAULT NULL,
  `default_language` varchar(10) DEFAULT 'el',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `content_categories`
--

CREATE TABLE `content_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `property_id` bigint(20) UNSIGNED NOT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `content_embeddings`
--

CREATE TABLE `content_embeddings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `property_id` bigint(20) UNSIGNED NOT NULL,
  `content_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `content_idea_id` bigint(20) UNSIGNED DEFAULT NULL,
  `embedding` longtext NOT NULL,
  `embedding_model` varchar(100) NOT NULL,
  `source_text_hash` char(64) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `content_ideas`
--

CREATE TABLE `content_ideas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `property_id` bigint(20) UNSIGNED NOT NULL,
  `content_type_id` bigint(20) UNSIGNED DEFAULT NULL,
  `category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `summary` text DEFAULT NULL,
  `prompt` text DEFAULT NULL,
  `ai_response_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `similarity_score` decimal(5,4) DEFAULT NULL,
  `status` enum('suggested','accepted','rejected','converted_to_article') DEFAULT 'suggested',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `content_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `content_items`
--

CREATE TABLE `content_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `property_id` bigint(20) UNSIGNED NOT NULL,
  `content_type_id` bigint(20) UNSIGNED DEFAULT NULL,
  `source_idea_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `body` longtext DEFAULT NULL,
  `status` enum('idea','draft','under_review','approved','scheduled','published','rejected','archived') DEFAULT 'draft',
  `language` varchar(10) DEFAULT 'el',
  `writing_style_id` bigint(20) UNSIGNED DEFAULT NULL,
  `template_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ai_profile_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `content_item_categories`
--

CREATE TABLE `content_item_categories` (
  `content_item_id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `content_item_tags`
--

CREATE TABLE `content_item_tags` (
  `content_item_id` bigint(20) UNSIGNED NOT NULL,
  `tag_id` bigint(20) UNSIGNED NOT NULL,
  `source` enum('manual','ai','system') DEFAULT 'manual'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `content_plans`
--

CREATE TABLE `content_plans` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `property_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `content_type_id` bigint(20) UNSIGNED DEFAULT NULL,
  `category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `template_id` bigint(20) UNSIGNED DEFAULT NULL,
  `writing_style_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ai_profile_id` bigint(20) UNSIGNED DEFAULT NULL,
  `frequency` enum('manual','daily','weekly','monthly') DEFAULT 'manual',
  `auto_generate` tinyint(1) DEFAULT 0,
  `auto_publish` tinyint(1) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `content_plan_runs`
--

CREATE TABLE `content_plan_runs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `property_id` bigint(20) UNSIGNED NOT NULL,
  `content_plan_id` bigint(20) UNSIGNED NOT NULL,
  `content_idea_id` bigint(20) UNSIGNED DEFAULT NULL,
  `content_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('pending','running','completed','failed') DEFAULT 'pending',
  `run_at` datetime NOT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `content_publications`
--

CREATE TABLE `content_publications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `property_id` bigint(20) UNSIGNED NOT NULL,
  `content_item_id` bigint(20) UNSIGNED NOT NULL,
  `distribution_channel_id` bigint(20) UNSIGNED NOT NULL,
  `external_id` varchar(190) DEFAULT NULL,
  `external_url` varchar(500) DEFAULT NULL,
  `status` enum('pending','scheduled','published','failed','removed') DEFAULT 'pending',
  `scheduled_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `content_reviews`
--

CREATE TABLE `content_reviews` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `property_id` bigint(20) UNSIGNED NOT NULL,
  `content_item_id` bigint(20) UNSIGNED NOT NULL,
  `reviewed_by` bigint(20) UNSIGNED NOT NULL,
  `status` enum('approved','rejected','needs_changes') NOT NULL,
  `comments` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `content_similarity_checks`
--

CREATE TABLE `content_similarity_checks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `property_id` bigint(20) UNSIGNED NOT NULL,
  `content_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `content_idea_id` bigint(20) UNSIGNED DEFAULT NULL,
  `compared_content_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `similarity_score` decimal(5,4) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `content_templates`
--

CREATE TABLE `content_templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `property_id` bigint(20) UNSIGNED DEFAULT NULL,
  `content_type_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `structure_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `content_types`
--

CREATE TABLE `content_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `property_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `default_word_count` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `distribution_channels`
--

CREATE TABLE `distribution_channels` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `property_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` enum('wordpress','facebook','linkedin','instagram','x','custom_api') NOT NULL,
  `credentials_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `settings_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `status` enum('active','inactive','error') DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `image_styles`
--

CREATE TABLE `image_styles` (
  `id` int(11) NOT NULL,
  `property_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` smallint(6) NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `media_assets`
--

CREATE TABLE `media_assets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `property_id` bigint(20) UNSIGNED NOT NULL,
  `content_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` enum('featured_image','inline_image','social_image','other') DEFAULT 'featured_image',
  `source` enum('uploaded','ai_generated','stock','external_url') DEFAULT 'uploaded',
  `prompt` text DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `external_url` varchar(500) DEFAULT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `caption` text DEFAULT NULL,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `properties`
--

CREATE TABLE `properties` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` enum('website','facebook_page','instagram_account','linkedin_page','newsletter','other') DEFAULT 'website',
  `primary_url` varchar(255) DEFAULT NULL,
  `default_language` varchar(10) DEFAULT 'el',
  `timezone` varchar(80) DEFAULT 'Europe/Athens',
  `settings_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `property_users`
--

CREATE TABLE `property_users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `property_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `role` enum('admin','editor','author','viewer') NOT NULL DEFAULT 'viewer',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `tags`
--

CREATE TABLE `tags` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `property_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `source` enum('manual','ai','system') DEFAULT 'manual',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` enum('active','inactive','blocked') DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `last_account_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `writing_styles`
--

CREATE TABLE `writing_styles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `property_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `tone` varchar(80) DEFAULT NULL,
  `language` varchar(10) DEFAULT 'el',
  `instructions` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Ευρετήρια για άχρηστους πίνακες
--

--
-- Ευρετήρια για πίνακα `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`);

--
-- Ευρετήρια για πίνακα `account_users`
--
ALTER TABLE `account_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_account_user` (`account_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Ευρετήρια για πίνακα `ai_generation_logs`
--
ALTER TABLE `ai_generation_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `content_item_id` (`content_item_id`),
  ADD KEY `content_idea_id` (`content_idea_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Ευρετήρια για πίνακα `ai_profiles`
--
ALTER TABLE `ai_profiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `default_writing_style_id` (`default_writing_style_id`),
  ADD KEY `default_template_id` (`default_template_id`);

--
-- Ευρετήρια για πίνακα `content_categories`
--
ALTER TABLE `content_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_category` (`property_id`,`slug`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Ευρετήρια για πίνακα `content_embeddings`
--
ALTER TABLE `content_embeddings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_embedding_source` (`account_id`,`property_id`,`source_text_hash`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `content_item_id` (`content_item_id`),
  ADD KEY `content_idea_id` (`content_idea_id`);

--
-- Ευρετήρια για πίνακα `content_ideas`
--
ALTER TABLE `content_ideas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `content_type_id` (`content_type_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Ευρετήρια για πίνακα `content_items`
--
ALTER TABLE `content_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_content_lookup` (`account_id`,`property_id`,`status`,`published_at`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `content_type_id` (`content_type_id`),
  ADD KEY `source_idea_id` (`source_idea_id`),
  ADD KEY `writing_style_id` (`writing_style_id`),
  ADD KEY `template_id` (`template_id`),
  ADD KEY `ai_profile_id` (`ai_profile_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`);
ALTER TABLE `content_items` ADD FULLTEXT KEY `ft_content` (`title`,`summary`,`body`);

--
-- Ευρετήρια για πίνακα `content_item_categories`
--
ALTER TABLE `content_item_categories`
  ADD PRIMARY KEY (`content_item_id`,`category_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Ευρετήρια για πίνακα `content_item_tags`
--
ALTER TABLE `content_item_tags`
  ADD PRIMARY KEY (`content_item_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Ευρετήρια για πίνακα `content_plans`
--
ALTER TABLE `content_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `content_type_id` (`content_type_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `template_id` (`template_id`),
  ADD KEY `writing_style_id` (`writing_style_id`),
  ADD KEY `ai_profile_id` (`ai_profile_id`);

--
-- Ευρετήρια για πίνακα `content_plan_runs`
--
ALTER TABLE `content_plan_runs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `content_plan_id` (`content_plan_id`),
  ADD KEY `content_idea_id` (`content_idea_id`),
  ADD KEY `content_item_id` (`content_item_id`);

--
-- Ευρετήρια για πίνακα `content_publications`
--
ALTER TABLE `content_publications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `content_item_id` (`content_item_id`),
  ADD KEY `distribution_channel_id` (`distribution_channel_id`);

--
-- Ευρετήρια για πίνακα `content_reviews`
--
ALTER TABLE `content_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `content_item_id` (`content_item_id`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Ευρετήρια για πίνακα `content_similarity_checks`
--
ALTER TABLE `content_similarity_checks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `content_item_id` (`content_item_id`),
  ADD KEY `content_idea_id` (`content_idea_id`),
  ADD KEY `compared_content_item_id` (`compared_content_item_id`);

--
-- Ευρετήρια για πίνακα `content_templates`
--
ALTER TABLE `content_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `content_type_id` (`content_type_id`);

--
-- Ευρετήρια για πίνακα `content_types`
--
ALTER TABLE `content_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_content_type` (`account_id`,`property_id`,`slug`),
  ADD KEY `property_id` (`property_id`);

--
-- Ευρετήρια για πίνακα `distribution_channels`
--
ALTER TABLE `distribution_channels`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `property_id` (`property_id`);

--
-- Ευρετήρια για πίνακα `image_styles`
--
ALTER TABLE `image_styles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `account_id` (`account_id`);

--
-- Ευρετήρια για πίνακα `media_assets`
--
ALTER TABLE `media_assets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `content_item_id` (`content_item_id`);

--
-- Ευρετήρια για πίνακα `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`);

--
-- Ευρετήρια για πίνακα `property_users`
--
ALTER TABLE `property_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_property_user` (`property_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Ευρετήρια για πίνακα `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tag` (`account_id`,`property_id`,`slug`),
  ADD KEY `property_id` (`property_id`);

--
-- Ευρετήρια για πίνακα `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `last_account_id` (`last_account_id`);

--
-- Ευρετήρια για πίνακα `writing_styles`
--
ALTER TABLE `writing_styles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `property_id` (`property_id`);

--
-- AUTO_INCREMENT για άχρηστους πίνακες
--

--
-- AUTO_INCREMENT για πίνακα `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `account_users`
--
ALTER TABLE `account_users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `ai_generation_logs`
--
ALTER TABLE `ai_generation_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `ai_profiles`
--
ALTER TABLE `ai_profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `content_categories`
--
ALTER TABLE `content_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `content_embeddings`
--
ALTER TABLE `content_embeddings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `content_ideas`
--
ALTER TABLE `content_ideas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `content_items`
--
ALTER TABLE `content_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `content_plans`
--
ALTER TABLE `content_plans`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `content_plan_runs`
--
ALTER TABLE `content_plan_runs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `content_publications`
--
ALTER TABLE `content_publications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `content_reviews`
--
ALTER TABLE `content_reviews`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `content_similarity_checks`
--
ALTER TABLE `content_similarity_checks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `content_templates`
--
ALTER TABLE `content_templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `content_types`
--
ALTER TABLE `content_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `distribution_channels`
--
ALTER TABLE `distribution_channels`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `image_styles`
--
ALTER TABLE `image_styles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `media_assets`
--
ALTER TABLE `media_assets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `properties`
--
ALTER TABLE `properties`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `property_users`
--
ALTER TABLE `property_users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `tags`
--
ALTER TABLE `tags`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `writing_styles`
--
ALTER TABLE `writing_styles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Περιορισμοί για άχρηστους πίνακες
--

--
-- Περιορισμοί για πίνακα `account_users`
--
ALTER TABLE `account_users`
  ADD CONSTRAINT `account_users_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `account_users_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Περιορισμοί για πίνακα `ai_generation_logs`
--
ALTER TABLE `ai_generation_logs`
  ADD CONSTRAINT `ai_generation_logs_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `ai_generation_logs_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`),
  ADD CONSTRAINT `ai_generation_logs_ibfk_3` FOREIGN KEY (`content_item_id`) REFERENCES `content_items` (`id`),
  ADD CONSTRAINT `ai_generation_logs_ibfk_4` FOREIGN KEY (`content_idea_id`) REFERENCES `content_ideas` (`id`),
  ADD CONSTRAINT `ai_generation_logs_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Περιορισμοί για πίνακα `ai_profiles`
--
ALTER TABLE `ai_profiles`
  ADD CONSTRAINT `ai_profiles_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `ai_profiles_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`),
  ADD CONSTRAINT `ai_profiles_ibfk_3` FOREIGN KEY (`default_writing_style_id`) REFERENCES `writing_styles` (`id`),
  ADD CONSTRAINT `ai_profiles_ibfk_4` FOREIGN KEY (`default_template_id`) REFERENCES `content_templates` (`id`);

--
-- Περιορισμοί για πίνακα `content_categories`
--
ALTER TABLE `content_categories`
  ADD CONSTRAINT `content_categories_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `content_categories_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`),
  ADD CONSTRAINT `content_categories_ibfk_3` FOREIGN KEY (`parent_id`) REFERENCES `content_categories` (`id`);

--
-- Περιορισμοί για πίνακα `content_embeddings`
--
ALTER TABLE `content_embeddings`
  ADD CONSTRAINT `content_embeddings_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `content_embeddings_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`),
  ADD CONSTRAINT `content_embeddings_ibfk_3` FOREIGN KEY (`content_item_id`) REFERENCES `content_items` (`id`),
  ADD CONSTRAINT `content_embeddings_ibfk_4` FOREIGN KEY (`content_idea_id`) REFERENCES `content_ideas` (`id`);

--
-- Περιορισμοί για πίνακα `content_ideas`
--
ALTER TABLE `content_ideas`
  ADD CONSTRAINT `content_ideas_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `content_ideas_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`),
  ADD CONSTRAINT `content_ideas_ibfk_3` FOREIGN KEY (`content_type_id`) REFERENCES `content_types` (`id`),
  ADD CONSTRAINT `content_ideas_ibfk_4` FOREIGN KEY (`category_id`) REFERENCES `content_categories` (`id`),
  ADD CONSTRAINT `content_ideas_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Περιορισμοί για πίνακα `content_items`
--
ALTER TABLE `content_items`
  ADD CONSTRAINT `content_items_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `content_items_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`),
  ADD CONSTRAINT `content_items_ibfk_3` FOREIGN KEY (`content_type_id`) REFERENCES `content_types` (`id`),
  ADD CONSTRAINT `content_items_ibfk_4` FOREIGN KEY (`source_idea_id`) REFERENCES `content_ideas` (`id`),
  ADD CONSTRAINT `content_items_ibfk_5` FOREIGN KEY (`writing_style_id`) REFERENCES `writing_styles` (`id`),
  ADD CONSTRAINT `content_items_ibfk_6` FOREIGN KEY (`template_id`) REFERENCES `content_templates` (`id`),
  ADD CONSTRAINT `content_items_ibfk_7` FOREIGN KEY (`ai_profile_id`) REFERENCES `ai_profiles` (`id`),
  ADD CONSTRAINT `content_items_ibfk_8` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `content_items_ibfk_9` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Περιορισμοί για πίνακα `content_item_categories`
--
ALTER TABLE `content_item_categories`
  ADD CONSTRAINT `content_item_categories_ibfk_1` FOREIGN KEY (`content_item_id`) REFERENCES `content_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `content_item_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `content_categories` (`id`);

--
-- Περιορισμοί για πίνακα `content_item_tags`
--
ALTER TABLE `content_item_tags`
  ADD CONSTRAINT `content_item_tags_ibfk_1` FOREIGN KEY (`content_item_id`) REFERENCES `content_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `content_item_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`);

--
-- Περιορισμοί για πίνακα `content_plans`
--
ALTER TABLE `content_plans`
  ADD CONSTRAINT `content_plans_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `content_plans_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`),
  ADD CONSTRAINT `content_plans_ibfk_3` FOREIGN KEY (`content_type_id`) REFERENCES `content_types` (`id`),
  ADD CONSTRAINT `content_plans_ibfk_4` FOREIGN KEY (`category_id`) REFERENCES `content_categories` (`id`),
  ADD CONSTRAINT `content_plans_ibfk_5` FOREIGN KEY (`template_id`) REFERENCES `content_templates` (`id`),
  ADD CONSTRAINT `content_plans_ibfk_6` FOREIGN KEY (`writing_style_id`) REFERENCES `writing_styles` (`id`),
  ADD CONSTRAINT `content_plans_ibfk_7` FOREIGN KEY (`ai_profile_id`) REFERENCES `ai_profiles` (`id`);

--
-- Περιορισμοί για πίνακα `content_plan_runs`
--
ALTER TABLE `content_plan_runs`
  ADD CONSTRAINT `content_plan_runs_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `content_plan_runs_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`),
  ADD CONSTRAINT `content_plan_runs_ibfk_3` FOREIGN KEY (`content_plan_id`) REFERENCES `content_plans` (`id`),
  ADD CONSTRAINT `content_plan_runs_ibfk_4` FOREIGN KEY (`content_idea_id`) REFERENCES `content_ideas` (`id`),
  ADD CONSTRAINT `content_plan_runs_ibfk_5` FOREIGN KEY (`content_item_id`) REFERENCES `content_items` (`id`);

--
-- Περιορισμοί για πίνακα `content_publications`
--
ALTER TABLE `content_publications`
  ADD CONSTRAINT `content_publications_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `content_publications_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`),
  ADD CONSTRAINT `content_publications_ibfk_3` FOREIGN KEY (`content_item_id`) REFERENCES `content_items` (`id`),
  ADD CONSTRAINT `content_publications_ibfk_4` FOREIGN KEY (`distribution_channel_id`) REFERENCES `distribution_channels` (`id`);

--
-- Περιορισμοί για πίνακα `content_reviews`
--
ALTER TABLE `content_reviews`
  ADD CONSTRAINT `content_reviews_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `content_reviews_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`),
  ADD CONSTRAINT `content_reviews_ibfk_3` FOREIGN KEY (`content_item_id`) REFERENCES `content_items` (`id`),
  ADD CONSTRAINT `content_reviews_ibfk_4` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`);

--
-- Περιορισμοί για πίνακα `content_similarity_checks`
--
ALTER TABLE `content_similarity_checks`
  ADD CONSTRAINT `content_similarity_checks_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `content_similarity_checks_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`),
  ADD CONSTRAINT `content_similarity_checks_ibfk_3` FOREIGN KEY (`content_item_id`) REFERENCES `content_items` (`id`),
  ADD CONSTRAINT `content_similarity_checks_ibfk_4` FOREIGN KEY (`content_idea_id`) REFERENCES `content_ideas` (`id`),
  ADD CONSTRAINT `content_similarity_checks_ibfk_5` FOREIGN KEY (`compared_content_item_id`) REFERENCES `content_items` (`id`);

--
-- Περιορισμοί για πίνακα `content_templates`
--
ALTER TABLE `content_templates`
  ADD CONSTRAINT `content_templates_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `content_templates_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`),
  ADD CONSTRAINT `content_templates_ibfk_3` FOREIGN KEY (`content_type_id`) REFERENCES `content_types` (`id`);

--
-- Περιορισμοί για πίνακα `content_types`
--
ALTER TABLE `content_types`
  ADD CONSTRAINT `content_types_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `content_types_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`);

--
-- Περιορισμοί για πίνακα `distribution_channels`
--
ALTER TABLE `distribution_channels`
  ADD CONSTRAINT `distribution_channels_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `distribution_channels_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`);

--
-- Περιορισμοί για πίνακα `image_styles`
--
ALTER TABLE `image_styles`
  ADD CONSTRAINT `image_styles_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`),
  ADD CONSTRAINT `image_styles_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`);

--
-- Περιορισμοί για πίνακα `media_assets`
--
ALTER TABLE `media_assets`
  ADD CONSTRAINT `media_assets_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `media_assets_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`),
  ADD CONSTRAINT `media_assets_ibfk_3` FOREIGN KEY (`content_item_id`) REFERENCES `content_items` (`id`);

--
-- Περιορισμοί για πίνακα `properties`
--
ALTER TABLE `properties`
  ADD CONSTRAINT `properties_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`);

--
-- Περιορισμοί για πίνακα `property_users`
--
ALTER TABLE `property_users`
  ADD CONSTRAINT `property_users_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`),
  ADD CONSTRAINT `property_users_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Περιορισμοί για πίνακα `tags`
--
ALTER TABLE `tags`
  ADD CONSTRAINT `tags_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `tags_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`);

--
-- Περιορισμοί για πίνακα `writing_styles`
--
ALTER TABLE `writing_styles`
  ADD CONSTRAINT `writing_styles_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `writing_styles_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
