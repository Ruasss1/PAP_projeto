-- Migration: Enhanced Supermercado Database
-- Adds new columns and tables while keeping existing structure
-- Execute this AFTER the initial schema.sql

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================
-- 1. ADD COLUMNS TO EXISTING TABLES
-- ============================================

-- Products table - add professional fields
ALTER TABLE `products` 
ADD COLUMN `brand` VARCHAR(100) DEFAULT NULL AFTER `category`,
ADD COLUMN `barcode` VARCHAR(50) DEFAULT NULL AFTER `brand`,
ADD COLUMN `vat` DECIMAL(5,2) DEFAULT 23.00 AFTER `sell_price`,
ADD COLUMN `min_stock` INT DEFAULT 5 AFTER `stock`,
ADD COLUMN `supplier_id` INT DEFAULT NULL AFTER `min_stock`,
ADD COLUMN `active` TINYINT DEFAULT 1 AFTER `expiry_date`;

-- Add foreign key for supplier
ALTER TABLE `products` 
ADD CONSTRAINT `fk_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`);

-- Suppliers table - add contact details
ALTER TABLE `suppliers` 
ADD COLUMN `email` VARCHAR(100) DEFAULT NULL AFTER `name`,
ADD COLUMN `phone` VARCHAR(20) DEFAULT NULL AFTER `email`,
ADD COLUMN `address` TEXT DEFAULT NULL AFTER `phone`,
ADD COLUMN `delivery_days` INT DEFAULT 2 AFTER `contact`,
ADD COLUMN `active` TINYINT DEFAULT 1 AFTER `address`;

-- Orders table - add status tracking
ALTER TABLE `orders` 
ADD COLUMN `status` ENUM('pending','processed','shipped','delivered') DEFAULT 'pending' AFTER `supplier_id`,
ADD COLUMN `total_cost` DECIMAL(10,2) DEFAULT NULL AFTER `status`,
ADD COLUMN `processed_at` DATETIME DEFAULT NULL AFTER `created_at`,
ADD COLUMN `delivered_at` DATETIME DEFAULT NULL AFTER `processed_at`;

-- Sales table - add payment method
ALTER TABLE `sales` 
ADD COLUMN `payment_method` VARCHAR(50) DEFAULT 'Dinheiro' AFTER `total`;

-- Sale items table - add cost price tracking
ALTER TABLE `sale_items` 
ADD COLUMN `cost_price` DECIMAL(10,2) DEFAULT NULL AFTER `price`;

-- Employees table - add active status
ALTER TABLE `employees` 
ADD COLUMN `active` TINYINT DEFAULT 1 AFTER `salary`;

-- Transactions table - add reference type
ALTER TABLE `transactions` 
ADD COLUMN `reference_type` VARCHAR(50) DEFAULT NULL AFTER `type`,
ADD COLUMN `reference_id` INT DEFAULT NULL AFTER `reference_type`;

-- ============================================
-- 2. NEW TABLES
-- ============================================

-- Order Messages (notifications for order status)
CREATE TABLE `order_messages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `type` ENUM('created','processed','shipped','delivered','partial') NOT NULL DEFAULT 'created',
  `message` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_order_messages_order` (`order_id`),
  CONSTRAINT `fk_order_messages_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Stock Movements (audit trail)
CREATE TABLE `stock_movements` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `type` ENUM('sale','order','break','expiry','adjustment','return') NOT NULL,
  `qty` INT(11) NOT NULL,
  `previous_stock` INT(11) NOT NULL,
  `new_stock` INT(11) NOT NULL,
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `reference_id` INT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_stock_movements_product` (`product_id`),
  CONSTRAINT `fk_stock_movements_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Price History (track price changes)
CREATE TABLE `price_history` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `price_type` ENUM('cost','sell') NOT NULL,
  `old_price` DECIMAL(10,2) NOT NULL,
  `new_price` DECIMAL(10,2) NOT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `changed_by` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_price_history_product` (`product_id`),
  CONSTRAINT `fk_price_history_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Alerts (notifications for dashboard)
CREATE TABLE `alerts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `alert_type` ENUM('low_stock','expiry','break','negative_profit','order_delivered') NOT NULL,
  `severity` ENUM('info','warning','critical') DEFAULT 'warning',
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `reference_id` INT DEFAULT NULL,
  `read` TINYINT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alerts_unread` (`read`),
  KEY `idx_alerts_type` (`alert_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 3. NEW VIEWS FOR DASHBOARD
-- ============================================

-- Daily Profit View (enhanced)
DROP TABLE IF EXISTS `daily_profit`;
CREATE ALGORITHM=UNDEFINED VIEW `daily_profit` AS
SELECT 
    DATE(s.sale_date) as date,
    SUM(s.total) as revenue,
    COALESCE(SUM(si.quantity * si.cost_price), 0) as cogs,
    SUM(s.total) - COALESCE(SUM(si.quantity * si.cost_price), 0) as profit,
    COUNT(DISTINCT s.id) as num_sales
FROM sales s
LEFT JOIN sale_items si ON si.sale_id = s.id
GROUP BY DATE(s.sale_date)
ORDER BY date DESC;

-- Monthly Profit View (enhanced)
DROP TABLE IF EXISTS `monthly_profit`;
CREATE ALGORITHM=UNDEFINED VIEW `monthly_profit` AS
SELECT 
    DATE_FORMAT(s.sale_date, '%Y-%m') as month,
    SUM(s.total) as revenue,
    COALESCE(SUM(si.quantity * si.cost_price), 0) as cogs,
    SUM(s.total) - COALESCE(SUM(si.quantity * si.cost_price), 0) as profit,
    COALESCE(SUM(t.amount * -1), 0) as breaks,
    SUM(s.total) - COALESCE(SUM(si.quantity * si.cost_price), 0) - COALESCE(SUM(t.amount * -1), 0) as net_profit
FROM sales s
LEFT JOIN sale_items si ON si.sale_id = s.id
LEFT JOIN transactions t ON t.type = 'break' AND DATE_FORMAT(t.created_at, '%Y-%m') = DATE_FORMAT(s.sale_date, '%Y-%m')
GROUP BY DATE_FORMAT(s.sale_date, '%Y-%m')
ORDER BY month DESC;

-- Monthly Top Product View
DROP TABLE IF EXISTS `monthly_top_product`;
CREATE ALGORITHM=UNDEFINED VIEW `monthly_top_product` AS
SELECT 
    DATE_FORMAT(s.sale_date, '%Y-%m') as month,
    p.id as product_id,
    p.name as product_name,
    p.category,
    SUM(si.quantity) as total_qty,
    SUM(si.quantity * si.price) as total_revenue
FROM sale_items si
JOIN sales s ON si.sale_id = s.id
JOIN products p ON si.product_id = p.id
GROUP BY DATE_FORMAT(s.sale_date, '%Y-%m'), p.id
ORDER BY month DESC, total_qty DESC;

-- Stock Alerts View
DROP TABLE IF EXISTS `stock_alerts`;
CREATE ALGORITHM=UNDEFINED VIEW `stock_alerts` AS
SELECT 
    p.id as product_id,
    p.name as product_name,
    p.category,
    p.stock,
    p.min_stock,
    p.supplier_id,
    s.name as supplier_name,
    CASE 
        WHEN p.stock = 0 THEN 'out_of_stock'
        WHEN p.stock <= p.min_stock THEN 'low_stock'
        ELSE 'ok'
    END as alert_status
FROM products p
LEFT JOIN suppliers s ON p.supplier_id = s.id
WHERE p.active = 1 AND p.stock <= p.min_stock
ORDER BY p.stock ASC;

-- ============================================
-- 4. TRIGGERS FOR AUTOMATIC LOGGING
-- ============================================

-- Trigger to log stock movements on product updates
DELIMITER $$
CREATE TRIGGER `log_stock_movement` AFTER UPDATE ON `products`
FOR EACH ROW BEGIN
    IF OLD.stock != NEW.stock THEN
        INSERT INTO stock_movements (product_id, type, qty, previous_stock, new_stock, reference_type, notes)
        VALUES (
            NEW.id,
            'adjustment',
            NEW.stock - OLD.stock,
            OLD.stock,
            NEW.stock,
            'manual',
            CONCAT('Stock adjusted from ', OLD.stock, ' to ', NEW.stock)
        );
    END IF;
END$$
DELIMITER ;

-- Trigger to create alerts on low stock
DELIMITER $$
CREATE TRIGGER `alert_low_stock` AFTER UPDATE ON `products`
FOR EACH ROW BEGIN
    IF NEW.active = 1 AND NEW.stock <= NEW.min_stock AND (OLD.stock > NEW.min_stock OR OLD.stock = 0) THEN
        INSERT INTO alerts (alert_type, severity, title, message, reference_type, reference_id)
        VALUES (
            'low_stock',
            CASE WHEN NEW.stock = 0 THEN 'critical' ELSE 'warning' END,
            CONCAT('Stock Baixo: ', NEW.name),
            CONCAT('O produto "', NEW.name, '" está com stock baixo (', NEW.stock, ' unidades). Stock mínimo: ', NEW.min_stock),
            'product',
            NEW.id
        );
    END IF;
END$$
DELIMITER ;

COMMIT;

