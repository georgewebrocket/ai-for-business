ALTER TABLE `content_items`
  ADD COLUMN `meta_title` varchar(60) DEFAULT NULL AFTER `summary`,
  ADD COLUMN `meta_description` varchar(160) DEFAULT NULL AFTER `meta_title`;
