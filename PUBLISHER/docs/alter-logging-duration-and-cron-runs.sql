ALTER TABLE `ai_generation_logs`
  ADD COLUMN `duration_seconds` decimal(10,3) DEFAULT NULL AFTER `cost_estimate`;

CREATE TABLE `cron_job_runs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `job_name` varchar(100) NOT NULL,
  `account_id` bigint(20) UNSIGNED DEFAULT NULL,
  `property_id` bigint(20) UNSIGNED DEFAULT NULL,
  `content_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `content_idea_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('running','success','failed','skipped') DEFAULT 'running',
  `started_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  `duration_seconds` decimal(10,3) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

ALTER TABLE `cron_job_runs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `content_item_id` (`content_item_id`),
  ADD KEY `content_idea_id` (`content_idea_id`),
  ADD KEY `idx_cron_job_runs_lookup` (`job_name`,`status`,`started_at`);

ALTER TABLE `cron_job_runs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `cron_job_runs`
  ADD CONSTRAINT `cron_job_runs_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `cron_job_runs_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`),
  ADD CONSTRAINT `cron_job_runs_ibfk_3` FOREIGN KEY (`content_item_id`) REFERENCES `content_items` (`id`),
  ADD CONSTRAINT `cron_job_runs_ibfk_4` FOREIGN KEY (`content_idea_id`) REFERENCES `content_ideas` (`id`);
