-- 004_low_stock_settings.sql
-- Global settings for low stock alerts and notifications

CREATE TABLE IF NOT EXISTS `settings` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `key` varchar(100) NOT NULL UNIQUE,
  `value` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Defaults
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
('low_stock_global_threshold', '5'),
('low_stock_notify_enabled', '0'),
('low_stock_notify_email', NULL),
('low_stock_last_email_at', NULL);
