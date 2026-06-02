-- 003_add_pricing_management.sql
-- Adiciona sistema de gestão de preços com estratégias, promoções e análise de margem

-- ============================================
-- PRICE STRATEGIES
-- ============================================

CREATE TABLE IF NOT EXISTS `price_strategies` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `product_id` int NOT NULL,
  `markup_percent` decimal(5,2) NOT NULL DEFAULT 30.00,
  `min_price` decimal(10,2),
  `max_price` decimal(10,2),
  `notes` text,
  `active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_product_strategy` (`product_id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PROMOTIONS
-- ============================================

CREATE TABLE IF NOT EXISTS `promotions` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(255) NOT NULL,
  `description` text,
  `discount_type` enum('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
  `discount_value` decimal(10,2) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `active` tinyint(1) DEFAULT 1,
  `apply_to` enum('all', 'category', 'product') DEFAULT 'product',
  `created_by` int,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  KEY `idx_active` (`active`),
  KEY `idx_start_date` (`start_date`),
  KEY `idx_end_date` (`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PROMOTION PRODUCTS (Many-to-Many)
-- ============================================

CREATE TABLE IF NOT EXISTS `promotion_products` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `promotion_id` int NOT NULL,
  `product_id` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_promo_product` (`promotion_id`, `product_id`),
  FOREIGN KEY (`promotion_id`) REFERENCES `promotions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PROMOTION CATEGORIES (Many-to-Many)
-- ============================================

CREATE TABLE IF NOT EXISTS `promotion_categories` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `promotion_id` int NOT NULL,
  `category` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_promo_category` (`promotion_id`, `category`),
  FOREIGN KEY (`promotion_id`) REFERENCES `promotions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- MARGIN ANALYSIS
-- ============================================

CREATE TABLE IF NOT EXISTS `margin_analysis` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `product_id` int NOT NULL,
  `cost_price` decimal(10,2) NOT NULL,
  `sell_price` decimal(10,2) NOT NULL,
  `margin_amount` decimal(10,2) NOT NULL,
  `margin_percent` decimal(5,2) NOT NULL,
  `markup_percent` decimal(5,2),
  `category` varchar(100),
  `analyzed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  KEY `idx_margin_percent` (`margin_percent`),
  KEY `idx_category` (`category`),
  KEY `idx_analyzed_at` (`analyzed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PRICE CHANGE LOG
-- ============================================

CREATE TABLE IF NOT EXISTS `price_change_log` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `product_id` int NOT NULL,
  `old_cost_price` decimal(10,2),
  `new_cost_price` decimal(10,2),
  `old_sell_price` decimal(10,2),
  `new_sell_price` decimal(10,2),
  `old_margin` decimal(5,2),
  `new_margin` decimal(5,2),
  `change_reason` varchar(255),
  `changed_by` int,
  `changed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  KEY `idx_product_id` (`product_id`),
  KEY `idx_changed_at` (`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CATEGORY PRICING RULES
-- ============================================

CREATE TABLE IF NOT EXISTS `category_pricing_rules` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `category` varchar(100) NOT NULL,
  `default_markup_percent` decimal(5,2) NOT NULL DEFAULT 30.00,
  `min_margin_percent` decimal(5,2) DEFAULT 10.00,
  `max_discount_percent` decimal(5,2) DEFAULT 20.00,
  `active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSERT DEFAULT CATEGORY PRICING RULES
-- ============================================

INSERT IGNORE INTO `category_pricing_rules` (`category`, `default_markup_percent`, `min_margin_percent`) VALUES
('frutas', 35.00, 12.00),
('padaria', 40.00, 15.00),
('laticinios', 30.00, 10.00),
('mercearia', 25.00, 8.00),
('bebidas', 20.00, 8.00),
('congelados', 28.00, 10.00),
('limpeza', 45.00, 20.00),
('higiene', 40.00, 18.00);
