-- Importacao Mercantec para TiDB Cloud (esquema completo + dados base + complementos)
SET FOREIGN_KEY_CHECKS=0;
SET NAMES utf8mb4;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `absences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `absences` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int DEFAULT NULL,
  `absence_date` date NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Falta',
  `reason` text COLLATE utf8mb4_unicode_ci,
  `justified` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `absences_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `all_products`;
/*!50001 DROP VIEW IF EXISTS `all_products`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `all_products` AS SELECT 
 1 AS `category`,
 1 AS `id`,
 1 AS `name`,
 1 AS `sell_price`,
 1 AS `stock`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `store_id` int NOT NULL DEFAULT '1',
  `clock_in` datetime NOT NULL,
  `clock_out` datetime DEFAULT NULL,
  `break_minutes` int DEFAULT '0',
  `overtime_minutes` int DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_date` (`clock_in`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resource` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resource_id` int DEFAULT NULL,
  `changes` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_resource` (`resource`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bebidas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bebidas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `sell_price` decimal(10,2) DEFAULT NULL,
  `stock` int DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `breaks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `breaks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `qty` int NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cash_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_movements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shift_id` int NOT NULL,
  `type` enum('entry','withdrawal','adjustment') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `shift_id` (`shift_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `cash_movements_ibfk_1` FOREIGN KEY (`shift_id`) REFERENCES `cash_register_shifts` (`id`),
  CONSTRAINT `cash_movements_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cash_operations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_operations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shift_id` int NOT NULL,
  `user_id` int NOT NULL,
  `operation_type` enum('sangria','reforco','abertura','fecho','ajuste') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `balance_before` decimal(10,2) DEFAULT NULL,
  `balance_after` decimal(10,2) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `authorized_by` int DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `shift_id` (`shift_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `cash_operations_ibfk_1` FOREIGN KEY (`shift_id`) REFERENCES `cash_register_shifts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cash_operations_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cash_register_shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_register_shifts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shift_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `opening_balance` decimal(10,2) DEFAULT '0.00',
  `closing_balance` decimal(10,2) DEFAULT NULL,
  `expected_balance` decimal(10,2) DEFAULT NULL,
  `difference` decimal(10,2) DEFAULT NULL,
  `status` enum('open','closed') COLLATE utf8mb4_unicode_ci DEFAULT 'open',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `opened_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at` timestamp NULL DEFAULT NULL,
  `total_sales` decimal(10,2) DEFAULT '0.00',
  `total_cash` decimal(10,2) DEFAULT '0.00',
  `total_card` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `shift_number` (`shift_number`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `cash_register_shifts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=740 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `congelados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `congelados` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `sell_price` decimal(10,2) DEFAULT NULL,
  `stock` int DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `coupon_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupon_usage` (
  `id` int NOT NULL AUTO_INCREMENT,
  `coupon_id` int NOT NULL,
  `receipt_id` int DEFAULT NULL,
  `customer_id` int DEFAULT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `used_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `coupon_id` (`coupon_id`),
  CONSTRAINT `coupon_usage_ibfk_1` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_type` enum('percentage','fixed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `min_purchase` decimal(10,2) DEFAULT '0.00',
  `max_uses` int DEFAULT NULL,
  `uses_count` int DEFAULT '0',
  `valid_from` date NOT NULL,
  `valid_until` date NOT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nif` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `loyalty_points` int DEFAULT '0',
  `total_spent` decimal(10,2) DEFAULT '0.00',
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `address` text COLLATE utf8mb4_unicode_ci,
  `birth_date` date DEFAULT NULL,
  `loyalty_card_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Ativo',
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `loyalty_card_number` (`loyalty_card_number`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `daily_profit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_profit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `total_sales` decimal(10,2) DEFAULT '0.00',
  `total_cost` decimal(10,2) DEFAULT '0.00',
  `total_profit` decimal(10,2) DEFAULT '0.00',
  `total_orders` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `salary` decimal(10,2) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `store_id` int DEFAULT '1',
  `email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Ativo',
  `department` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nif` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `birth_date` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `photo` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `emergency_contact` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contract_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Efetivo',
  `position` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `base_salary` decimal(10,2) DEFAULT '0.00',
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `financial_report`;
/*!50001 DROP VIEW IF EXISTS `financial_report`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `financial_report` AS SELECT 
 1 AS `mes`,
 1 AS `receita_total`,
 1 AS `perdas`,
 1 AS `lucro_liquido`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `frutas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `frutas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `sell_price` decimal(10,2) DEFAULT NULL,
  `stock` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `higiene`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `higiene` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `sell_price` decimal(10,2) DEFAULT NULL,
  `stock` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `laticinios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `laticinios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `sell_price` decimal(10,2) DEFAULT NULL,
  `stock` int DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `limpeza`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `limpeza` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `sell_price` decimal(10,2) DEFAULT NULL,
  `stock` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mercearia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mercearia` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `sell_price` decimal(10,2) DEFAULT NULL,
  `stock` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT '0',
  `link` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `icon` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Normal',
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `cost_price` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `order_items_ibfk_1` (`order_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=217 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `supplier_id` int NOT NULL,
  `status` enum('pending','processed','shipped','delivered') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `total_cost` decimal(10,2) DEFAULT NULL,
  `received` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `processed_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `padaria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `padaria` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `sell_price` decimal(10,2) DEFAULT NULL,
  `stock` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int DEFAULT NULL,
  `month` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_salary` decimal(10,2) DEFAULT '0.00',
  `overtime_hours` decimal(5,2) DEFAULT '0.00',
  `overtime_amount` decimal(10,2) DEFAULT '0.00',
  `bonus` decimal(10,2) DEFAULT '0.00',
  `deductions` decimal(10,2) DEFAULT '0.00',
  `net_salary` decimal(10,2) DEFAULT '0.00',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Pendente',
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_emp_month` (`employee_id`,`month`),
  CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=139 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `price_change_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `price_change_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `old_sell_price` decimal(10,2) DEFAULT NULL,
  `new_sell_price` decimal(10,2) DEFAULT NULL,
  `old_cost_price` decimal(10,2) DEFAULT NULL,
  `new_cost_price` decimal(10,2) DEFAULT NULL,
  `changed_by` int DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `price_change_log_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `price_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `price_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `old_price` decimal(10,2) DEFAULT NULL,
  `new_price` decimal(10,2) DEFAULT NULL,
  `changed_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `price_history_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `brand` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `barcode` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT '0.00',
  `sell_price` decimal(10,2) DEFAULT '0.00',
  `vat` decimal(5,2) DEFAULT '23.00',
  `stock` int DEFAULT '0',
  `min_stock` int DEFAULT '5',
  `supplier_id` int DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `store_id` int DEFAULT '1',
  `reorder_qty` int DEFAULT NULL COMMENT 'Quantidade a encomendar automaticamente',
  PRIMARY KEY (`id`),
  KEY `fk_products_supplier` (`supplier_id`),
  CONSTRAINT `fk_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `promotion_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promotion_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `promotion_id` int NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_promotion_category` (`promotion_id`,`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `promotion_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promotion_products` (
  `promotion_id` int NOT NULL,
  `product_id` int NOT NULL,
  PRIMARY KEY (`promotion_id`,`product_id`),
  KEY `idx_promotion_products_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `promotions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promotions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` int DEFAULT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('percentage','fixed') COLLATE utf8mb4_unicode_ci DEFAULT 'percentage',
  `value` decimal(10,2) DEFAULT '0.00',
  `discount_type` enum('percentage','fixed') COLLATE utf8mb4_unicode_ci DEFAULT 'percentage',
  `discount_value` decimal(10,2) DEFAULT '0.00',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `is_active` tinyint(1) DEFAULT '1',
  `store_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `qr_code` longtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_promotions_store` (`store_id`),
  KEY `idx_promotions_product` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `receipt_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `receipt_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `receipt_id` int NOT NULL,
  `product_id` int NOT NULL,
  `product_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_sku` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` decimal(10,3) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `discount_percent` decimal(5,2) DEFAULT '0.00',
  `discount_amount` decimal(10,2) DEFAULT '0.00',
  `subtotal` decimal(10,2) NOT NULL,
  `is_weighted` tinyint DEFAULT '0',
  `weight_kg` decimal(10,3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `receipt_id` (`receipt_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `receipt_items_ibfk_1` FOREIGN KEY (`receipt_id`) REFERENCES `receipts` (`id`),
  CONSTRAINT `receipt_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=43848 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `receipt_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `receipt_payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `receipt_id` int NOT NULL,
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `details` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `receipt_id` (`receipt_id`),
  CONSTRAINT `receipt_payments_ibfk_1` FOREIGN KEY (`receipt_id`) REFERENCES `receipts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `receipts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `receipt_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shift_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `customer_id` int DEFAULT NULL,
  `sale_id` int DEFAULT NULL,
  `store_id` int DEFAULT '1',
  `subtotal` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT '0.00',
  `tax_amount` decimal(10,2) DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nif` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('completed','cancelled','refunded') COLLATE utf8mb4_unicode_ci DEFAULT 'completed',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `total_amount` decimal(10,2) DEFAULT '0.00',
  `final_amount` decimal(10,2) DEFAULT '0.00',
  `payment_details` text COLLATE utf8mb4_unicode_ci,
  `loyalty_points_earned` int DEFAULT '0',
  `loyalty_points_redeemed` int DEFAULT '0',
  `completed_at` datetime DEFAULT NULL,
  `invoice_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'recibo',
  `qr_code` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipt_number` (`receipt_number`),
  KEY `user_id` (`user_id`),
  KEY `shift_id` (`shift_id`),
  CONSTRAINT `receipts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `receipts_ibfk_2` FOREIGN KEY (`shift_id`) REFERENCES `cash_register_shifts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8803 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `return_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `return_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `return_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` decimal(10,3) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `refund_amount` decimal(10,2) NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `return_id` (`return_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `return_items_ibfk_1` FOREIGN KEY (`return_id`) REFERENCES `returns` (`id`),
  CONSTRAINT `return_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `returns` (
  `id` int NOT NULL AUTO_INCREMENT,
  `return_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_receipt_id` int NOT NULL,
  `user_id` int NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `total_refund` decimal(10,2) NOT NULL,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `return_number` (`return_number`),
  KEY `original_receipt_id` (`original_receipt_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `returns_ibfk_1` FOREIGN KEY (`original_receipt_id`) REFERENCES `receipts` (`id`),
  CONSTRAINT `returns_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sale_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sale_id` int DEFAULT NULL,
  `category` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=55930 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sale_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `total` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Dinheiro',
  `nif` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `store_id` int DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12495 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schedules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int DEFAULT NULL,
  `shift_id` int DEFAULT NULL,
  `schedule_date` date DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Confirmado',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `shift_id` (`shift_id`),
  CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `schedules_ibfk_2` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_activity` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shifts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `employee_id` int DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `break_duration` int DEFAULT '60',
  `description` text COLLATE utf8mb4_unicode_ci,
  `shift_date` date DEFAULT NULL,
  `department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Ativo',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `shifts_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=176 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_movements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `type` varchar(50) NOT NULL,
  `qty` int NOT NULL,
  `previous_stock` int DEFAULT '0',
  `new_stock` int DEFAULT '0',
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=213 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Lisboa',
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suppliers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `active` tinyint DEFAULT '1',
  `contact` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `delivery_days` int DEFAULT '2',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `nif` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `suspended_sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suspended_sales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `suspension_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `customer_id` int DEFAULT NULL,
  `items` json NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','resumed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `suspension_code` (`suspension_code`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `suspended_sales_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `assigned_to` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Pendente',
  `priority` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Normal',
  `due_date` date DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` enum('sale','break','order','salary') COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `reference_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reference_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'user',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vacation_balance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vacation_balance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `year` int NOT NULL,
  `total_days` int NOT NULL DEFAULT '22',
  `used_days` int NOT NULL DEFAULT '0',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_employee_year` (`employee_id`,`year`),
  KEY `idx_employee_year` (`employee_id`,`year`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vacation_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vacation_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Ferias',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Pendente',
  `reason` text COLLATE utf8mb4_unicode_ci,
  `approved_by` int DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `vacation_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50001 DROP VIEW IF EXISTS `all_products`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `all_products` AS select 'Frutas' AS `category`,`frutas`.`id` AS `id`,`frutas`.`name` AS `name`,`frutas`.`sell_price` AS `sell_price`,`frutas`.`stock` AS `stock` from `frutas` union all select 'Padaria' AS `Padaria`,`padaria`.`id` AS `id`,`padaria`.`name` AS `name`,`padaria`.`sell_price` AS `sell_price`,`padaria`.`stock` AS `stock` from `padaria` union all select 'Laticínios' AS `Laticínios`,`laticinios`.`id` AS `id`,`laticinios`.`name` AS `name`,`laticinios`.`sell_price` AS `sell_price`,`laticinios`.`stock` AS `stock` from `laticinios` union all select 'Mercearia' AS `Mercearia`,`mercearia`.`id` AS `id`,`mercearia`.`name` AS `name`,`mercearia`.`sell_price` AS `sell_price`,`mercearia`.`stock` AS `stock` from `mercearia` union all select 'Bebidas' AS `Bebidas`,`bebidas`.`id` AS `id`,`bebidas`.`name` AS `name`,`bebidas`.`sell_price` AS `sell_price`,`bebidas`.`stock` AS `stock` from `bebidas` union all select 'Congelados' AS `Congelados`,`congelados`.`id` AS `id`,`congelados`.`name` AS `name`,`congelados`.`sell_price` AS `sell_price`,`congelados`.`stock` AS `stock` from `congelados` union all select 'Limpeza' AS `Limpeza`,`limpeza`.`id` AS `id`,`limpeza`.`name` AS `name`,`limpeza`.`sell_price` AS `sell_price`,`limpeza`.`stock` AS `stock` from `limpeza` union all select 'Higiene' AS `Higiene`,`higiene`.`id` AS `id`,`higiene`.`name` AS `name`,`higiene`.`sell_price` AS `sell_price`,`higiene`.`stock` AS `stock` from `higiene` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `financial_report`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `financial_report` AS select `s`.`mes` AS `mes`,`s`.`receita_total` AS `receita_total`,coalesce(`b`.`perdas`,0) AS `perdas`,(`s`.`receita_total` - coalesce(`b`.`perdas`,0)) AS `lucro_liquido` from ((select date_format(`sales`.`sale_date`,'%Y-%m') AS `mes`,sum(`sales`.`total`) AS `receita_total` from `sales` group by date_format(`sales`.`sale_date`,'%Y-%m')) `s` left join (select date_format(`transactions`.`created_at`,'%Y-%m') AS `mes`,(sum(`transactions`.`amount`) * -(1)) AS `perdas` from `transactions` where (`transactions`.`type` = 'break') group by date_format(`transactions`.`created_at`,'%Y-%m')) `b` on((`s`.`mes` = `b`.`mes`))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;



-- ===== DADOS BASE =====

-- MySQL dump 10.13  Distrib 9.5.0, for macos15.7 (arm64)
--
-- Host: localhost    Database: supermercado
-- ------------------------------------------------------
-- Server version	9.5.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- GTID state at the beginning of the backup 
--

SET @@GLOBAL.GTID_PURGED=/*!80000 '+'*/ 'cd8df9f6-efe5-11f0-a228-428f07732c85:1-177266';

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` (`id`, `name`, `description`, `created_at`) VALUES (1,'admin','Administrador com acesso total','2026-01-30 09:57:00'),(2,'manager','Gerente com acesso a relatórios e gestão','2026-01-30 09:57:00'),(3,'cashier','Operador de caixa','2026-01-30 09:57:00'),(4,'employee','Funcionário básico','2026-01-30 09:57:00');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `stores`
--

LOCK TABLES `stores` WRITE;
/*!40000 ALTER TABLE `stores` DISABLE KEYS */;
INSERT INTO `stores` (`id`, `name`, `address`, `city`, `phone`, `email`, `is_default`, `is_active`, `created_at`) VALUES (1,'Supermercado Centro','Rua Augusta, 123','Lisboa',NULL,NULL,1,1,'2026-01-30 09:57:00'),(2,'Supermercado Norte','Av. dos Aliados, 456','Porto',NULL,NULL,1,1,'2026-01-30 09:58:22');
/*!40000 ALTER TABLE `stores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
INSERT INTO `suppliers` (`id`, `name`, `email`, `phone`, `address`, `active`, `contact`, `delivery_days`, `created_at`, `nif`, `website`) VALUES (1,'Frutas & Companhia',NULL,NULL,NULL,1,'contacto@frutas.example',2,'2026-01-12 19:16:47',NULL,NULL),(2,'Padaria Local',NULL,NULL,NULL,1,'padaria@example.com',2,'2026-01-12 19:16:47',NULL,NULL),(3,'Frutas & Companhia',NULL,NULL,NULL,1,'contacto@frutas.example',2,'2026-01-12 19:27:33',NULL,NULL),(4,'Padaria Local',NULL,NULL,NULL,1,'padaria@example.com',2,'2026-01-12 19:27:33',NULL,NULL),(5,'Lactogal','comercial@lactogal.pt','229 861 400','Rua Engº Ezequiel de Campos 488, 4100-232 Porto',1,NULL,2,'2026-02-26 11:33:31','500243272',NULL),(6,'Sumol+Compal','geral@sumolcompal.pt','214 344 000','Estrada da Portela, 2790-124 Carnaxide',1,NULL,2,'2026-02-26 11:33:31','500239850',NULL),(7,'Cerealis','info@cerealis.pt','229 866 700','Rua do Campo Alegre 830, 4150-171 Porto',1,NULL,2,'2026-02-26 11:33:31','500278625',NULL),(8,'Nestlé Portugal','consumidor@pt.nestle.com','217 923 500','Rua Alexandre Herculano 8, 2799-554 Linda-a-Velha',1,NULL,2,'2026-02-26 11:33:31','500019578',NULL),(9,'Mondelez Portugal','portugal@mdlz.com','214 240 800','Sintra Business Park, 2710-089 Sintra',1,NULL,2,'2026-02-26 11:33:31','500271033',NULL),(10,'Unilever FIMA','contact@unilever.pt','219 157 000','Largo Monterroio Mascarenhas 1, 1099-081 Lisboa',1,NULL,2,'2026-02-26 11:33:31','500251855',NULL),(11,'Procter & Gamble Portugal','pgportugal@pg.com','214 124 100','Lagoas Park, Edifício 15, 2740-262 Porto Salvo',1,NULL,2,'2026-02-26 11:33:31','500105880',NULL),(12,'Pescanova Portugal','geral@pescanova.pt','212 599 100','Estrada Nacional 10, 2830-354 Barreiro',1,NULL,2,'2026-02-26 11:33:31','500045790',NULL),(13,'Frutaria','comercial@frutaria.pt','263 509 300','Zona Industrial do Arneiro, 2070-621 Cartaxo',1,NULL,2,'2026-02-26 11:33:31','502874563',NULL),(14,'Grupo Primor','geral@grupoprimor.pt','253 809 200','Lugar da Granja, 4760-485 Vila Nova de Famalicão',1,NULL,2,'2026-02-26 11:33:31','503258463',NULL),(15,'Ramirez','geral@ramirez.pt','233 940 500','Rua Eng. Frederico Ulrich 2650, 4470-605 Maia',1,NULL,2,'2026-02-26 11:33:31','500243736',NULL),(16,'Delta Cafés','geral@deltacafes.pt','245 308 200','Av. Combatentes da Grande Guerra, 7350-155 Campo Maior',1,NULL,2,'2026-02-26 11:33:31','500277820',NULL),(17,'Sovena Portugal','info@sovenagroup.com','210 409 300','Av. Dom José I, 1900-049 Lisboa',1,NULL,2,'2026-02-26 11:33:31','502176060',NULL),(18,'Renova','geral@renova.pt','249 890 100','Estrada da Asseiceira, 2354-002 Torres Novas',1,NULL,2,'2026-02-26 11:33:31','500253156',NULL),(19,'Central de Cervejas','consumidor@centralcervejas.pt','210 987 600','Estrada da Alfarrobeira, 2625-414 Vialonga',1,NULL,2,'2026-02-26 11:33:31','500044681',NULL),(20,'Ruas','vascoruas4@gmail.com','926647303','Sitio Dos Vales, Loja C',1,NULL,2,'2026-03-19 11:20:41','189123122',NULL);
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` (`id`, `name`, `category`, `brand`, `barcode`, `cost_price`, `sell_price`, `vat`, `stock`, `min_stock`, `supplier_id`, `expiry_date`, `active`, `created_at`, `store_id`, `reorder_qty`) VALUES (4,'Maçã Golden','Frutas','Nacional','5601234000001',0.35,0.89,6.00,273,5,13,NULL,1,'2026-02-24 15:48:57',1,NULL),(5,'Banana','Frutas','Importada','5601234000002',0.45,1.19,6.00,342,5,13,NULL,1,'2026-02-24 15:48:57',1,NULL),(6,'Laranja','Frutas','Nacional','5601234000003',0.30,0.79,6.00,434,5,13,NULL,1,'2026-02-24 15:48:57',1,NULL),(7,'Tomate','Legumes','Nacional','5601234000004',0.50,1.29,6.00,257,5,13,NULL,1,'2026-02-24 15:48:57',1,NULL),(8,'Batata','Legumes','Nacional','5601234000005',0.25,0.69,6.00,175,5,13,NULL,1,'2026-02-24 15:48:57',1,NULL),(9,'Cebola','Legumes','Nacional','5601234000006',0.20,0.59,6.00,459,5,13,NULL,1,'2026-02-24 15:48:57',1,NULL),(10,'Alface','Legumes','Nacional','5601234000007',0.40,0.99,6.00,109,5,13,NULL,1,'2026-02-24 15:48:57',1,NULL),(11,'Cenoura','Legumes','Nacional','5601234000008',0.30,0.79,6.00,80,5,13,NULL,1,'2026-02-24 15:48:57',1,NULL),(12,'Leite Mimosa 1L','Laticínios','Mimosa','5601234000010',0.65,1.09,6.00,270,5,5,NULL,1,'2026-02-24 15:48:57',1,NULL),(13,'Iogurte Natural Danone','Laticínios','Danone','5601234000011',0.35,0.69,6.00,332,5,5,NULL,1,'2026-02-24 15:48:57',1,NULL),(14,'Queijo Flamengo','Laticínios','Terra Nostra','5601234000012',2.50,4.99,6.00,304,5,5,NULL,1,'2026-02-24 15:48:57',1,NULL),(15,'Manteiga Mimosa','Laticínios','Mimosa','5601234000013',1.20,2.49,6.00,79,5,5,NULL,1,'2026-02-24 15:48:57',1,NULL),(16,'Natas','Laticínios','Mimosa','5601234000014',0.80,1.59,6.00,92,5,5,NULL,1,'2026-02-24 15:48:57',1,NULL),(17,'Arroz Caçarola 1kg','Mercearia','Caçarola','5601234000020',0.80,1.49,23.00,294,5,7,NULL,1,'2026-02-24 15:48:57',1,NULL),(18,'Massa Esparguete','Mercearia','Milaneza','5601234000021',0.50,0.99,23.00,322,5,7,NULL,1,'2026-02-24 15:48:57',1,NULL),(19,'Azeite Gallo 750ml','Mercearia','Gallo','5601234000022',3.50,6.99,23.00,413,5,17,NULL,1,'2026-02-24 15:48:57',1,NULL),(20,'Açúcar 1kg','Mercearia','Sidul','5601234000023',0.60,1.19,23.00,106,5,1,NULL,1,'2026-02-24 15:48:57',1,20),(21,'Café Delta 250g','Mercearia','Delta','5601234000024',2.00,3.99,23.00,193,5,16,NULL,1,'2026-02-24 15:48:57',1,NULL),(22,'Atum em Lata','Mercearia','Bom Petisco','5601234000025',1.20,2.49,23.00,172,5,15,NULL,1,'2026-02-24 15:48:57',1,NULL),(23,'Feijão Preto','Mercearia','Compal','5601234000026',0.80,1.69,23.00,179,5,NULL,NULL,1,'2026-02-24 15:48:57',1,NULL),(24,'Coca-Cola 1.5L','Bebidas','Coca-Cola','5601234000030',0.90,1.79,23.00,250,5,6,NULL,1,'2026-02-24 15:48:57',1,NULL),(25,'Água Luso 1.5L','Bebidas','Luso','5601234000031',0.25,0.59,6.00,373,5,6,NULL,1,'2026-02-24 15:48:57',1,NULL),(26,'Sumo Compal 1L','Bebidas','Compal','5601234000032',1.00,1.99,23.00,198,5,6,NULL,1,'2026-02-24 15:48:57',1,NULL),(27,'Cerveja Super Bock','Bebidas','Super Bock','5601234000033',0.50,0.99,23.00,314,5,6,NULL,1,'2026-02-24 15:48:57',1,NULL),(28,'Vinho Tinto 750ml','Bebidas','Monte Velho','5601234000034',3.00,5.99,23.00,126,5,6,NULL,1,'2026-02-24 15:48:57',1,NULL),(29,'Pão de Forma','Padaria','Panrico','5601234000040',0.80,1.59,6.00,50,5,7,NULL,1,'2026-02-24 15:48:57',1,NULL),(30,'Croissant','Padaria','Própria','5601234000041',0.30,0.69,6.00,251,5,7,NULL,1,'2026-02-24 15:48:57',1,NULL),(31,'Bolo de Arroz','Padaria','Própria','5601234000042',0.25,0.59,6.00,155,5,7,NULL,1,'2026-02-24 15:48:57',1,NULL),(32,'Frango Inteiro','Carnes','Perdigão','5601234000050',3.50,6.99,6.00,65,5,14,NULL,1,'2026-02-24 15:48:57',1,NULL),(33,'Carne Picada 500g','Carnes','Nacional','5601234000051',2.50,4.99,6.00,152,5,14,NULL,1,'2026-02-24 15:48:57',1,NULL),(34,'Bifes de Peru','Carnes','Perdigão','5601234000052',3.00,5.99,6.00,33,5,14,NULL,1,'2026-02-24 15:48:57',1,NULL),(35,'Lombo de Porco','Carnes','Nacional','5601234000053',4.00,7.99,6.00,119,5,14,NULL,1,'2026-02-24 15:48:57',1,NULL),(36,'Gelado Olá 1L','Congelados','Olá','5601234000060',2.00,3.99,23.00,40,5,8,NULL,1,'2026-02-24 15:48:57',1,NULL),(37,'Ervilhas Congeladas','Congelados','Iglo','5601234000061',1.00,1.99,6.00,119,5,8,NULL,1,'2026-02-24 15:48:57',1,NULL),(38,'Peixe Panado','Congelados','Iglo','5601234000062',2.50,4.99,6.00,68,5,8,NULL,1,'2026-02-24 15:48:57',1,NULL),(39,'Papel Higiénico 12un','Higiene','Scottex','5601234000070',2.50,4.99,23.00,157,5,10,NULL,1,'2026-02-24 15:48:57',1,NULL),(40,'Champô Head&Shoulders','Higiene','Head&Shoulders','5601234000071',2.00,3.99,23.00,295,5,10,NULL,1,'2026-02-24 15:48:57',1,NULL),(41,'Pasta de Dentes Colgate','Higiene','Colgate','5601234000072',1.20,2.49,23.00,203,5,10,NULL,1,'2026-02-24 15:48:57',1,NULL),(42,'Desodorizante Dove','Higiene','Dove','5601234000073',1.80,3.49,23.00,336,5,10,NULL,1,'2026-02-24 15:48:57',1,NULL),(43,'Detergente Roupa Skip','Limpeza','Skip','5601234000080',4.00,7.99,23.00,116,5,10,NULL,1,'2026-02-24 15:48:57',1,NULL),(44,'Lava Loiça Fairy','Limpeza','Fairy','5601234000081',1.50,2.99,23.00,50,5,10,NULL,1,'2026-02-24 15:48:57',1,NULL),(45,'Lixívia Neoblanc','Limpeza','Neoblanc','5601234000082',0.80,1.59,23.00,60,5,10,NULL,1,'2026-02-24 15:48:57',1,NULL),(46,'Maçã Golden','Frutas','Nacional','5609876000001',0.35,0.89,6.00,202,5,13,NULL,1,'2026-02-24 15:48:57',2,NULL),(47,'Banana','Frutas','Importada','5609876000002',0.45,1.19,6.00,120,5,13,NULL,1,'2026-02-24 15:48:57',2,NULL),(48,'Laranja','Frutas','Nacional','5609876000003',0.30,0.79,6.00,208,5,13,NULL,1,'2026-02-24 15:48:57',2,NULL),(49,'Tomate','Legumes','Nacional','5609876000004',0.50,1.29,6.00,71,5,13,NULL,1,'2026-02-24 15:48:57',2,NULL),(50,'Batata','Legumes','Nacional','5609876000005',0.25,0.69,6.00,110,5,13,NULL,1,'2026-02-24 15:48:57',2,NULL),(51,'Cebola','Legumes','Nacional','5609876000006',0.20,0.59,6.00,126,5,13,NULL,1,'2026-02-24 15:48:57',2,NULL),(52,'Alface','Legumes','Nacional','5609876000007',0.40,0.99,6.00,289,5,13,NULL,1,'2026-02-24 15:48:57',2,NULL),(53,'Cenoura','Legumes','Nacional','5609876000008',0.30,0.79,6.00,87,5,13,NULL,1,'2026-02-24 15:48:57',2,NULL),(54,'Leite Mimosa 1L','Laticínios','Mimosa','5609876000010',0.65,1.09,6.00,325,5,5,NULL,1,'2026-02-24 15:48:57',2,NULL),(55,'Iogurte Natural Danone','Laticínios','Danone','5609876000011',0.35,0.69,6.00,98,5,5,NULL,1,'2026-02-24 15:48:57',2,NULL),(56,'Queijo Flamengo','Laticínios','Terra Nostra','5609876000012',2.50,4.99,6.00,195,5,5,NULL,1,'2026-02-24 15:48:57',2,NULL),(57,'Manteiga Mimosa','Laticínios','Mimosa','5609876000013',1.20,2.49,6.00,69,5,5,NULL,1,'2026-02-24 15:48:57',2,NULL),(58,'Natas','Laticínios','Mimosa','5609876000014',0.80,1.59,6.00,273,5,5,NULL,1,'2026-02-24 15:48:57',2,NULL),(59,'Arroz Caçarola 1kg','Mercearia','Caçarola','5609876000020',0.80,1.49,23.00,267,5,7,NULL,1,'2026-02-24 15:48:57',2,NULL),(60,'Massa Esparguete','Mercearia','Milaneza','5609876000021',0.50,0.99,23.00,291,5,7,NULL,1,'2026-02-24 15:48:57',2,NULL),(61,'Azeite Gallo 750ml','Mercearia','Gallo','5609876000022',3.50,6.99,23.00,304,5,17,NULL,1,'2026-02-24 15:48:57',2,NULL),(62,'Açúcar 1kg','Mercearia','Sidul','5609876000023',0.60,1.19,23.00,325,5,NULL,NULL,1,'2026-02-24 15:48:57',2,NULL),(63,'Café Delta 250g','Mercearia','Delta','5609876000024',2.00,3.99,23.00,24,5,16,NULL,1,'2026-02-24 15:48:57',2,NULL),(64,'Atum em Lata','Mercearia','Bom Petisco','5609876000025',1.20,2.49,23.00,191,5,15,NULL,1,'2026-02-24 15:48:57',2,NULL),(65,'Feijão Preto','Mercearia','Compal','5609876000026',0.80,1.69,23.00,161,5,NULL,NULL,1,'2026-02-24 15:48:57',2,NULL),(66,'Coca-Cola 1.5L','Bebidas','Coca-Cola','5609876000030',0.90,1.79,23.00,34,5,6,NULL,1,'2026-02-24 15:48:57',2,NULL),(67,'Água Luso 1.5L','Bebidas','Luso','5609876000031',0.25,0.59,6.00,324,5,6,NULL,1,'2026-02-24 15:48:57',2,NULL),(68,'Sumo Compal 1L','Bebidas','Compal','5609876000032',1.00,1.99,23.00,293,5,6,NULL,1,'2026-02-24 15:48:57',2,NULL),(69,'Cerveja Super Bock','Bebidas','Super Bock','5609876000033',0.50,0.99,23.00,86,5,6,NULL,1,'2026-02-24 15:48:57',2,NULL),(70,'Vinho Tinto 750ml','Bebidas','Monte Velho','5609876000034',3.00,5.99,23.00,137,5,6,NULL,1,'2026-02-24 15:48:57',2,NULL),(71,'Pão de Forma','Padaria','Panrico','5609876000040',0.80,1.59,6.00,27,5,7,NULL,1,'2026-02-24 15:48:57',2,NULL),(72,'Croissant','Padaria','Própria','5609876000041',0.30,0.69,6.00,140,5,7,NULL,1,'2026-02-24 15:48:57',2,NULL),(73,'Bolo de Arroz','Padaria','Própria','5609876000042',0.25,0.59,6.00,112,5,7,NULL,1,'2026-02-24 15:48:57',2,NULL),(74,'Frango Inteiro','Carnes','Perdigão','5609876000050',3.50,6.99,6.00,145,5,14,NULL,1,'2026-02-24 15:48:57',2,NULL),(75,'Carne Picada 500g','Carnes','Nacional','5609876000051',2.50,4.99,6.00,102,5,14,NULL,1,'2026-02-24 15:48:57',2,NULL),(76,'Bifes de Peru','Carnes','Perdigão','5609876000052',3.00,5.99,6.00,118,5,14,NULL,1,'2026-02-24 15:48:57',2,NULL),(77,'Lombo de Porco','Carnes','Nacional','5609876000053',4.00,7.99,6.00,76,5,14,NULL,1,'2026-02-24 15:48:57',2,NULL),(78,'Gelado Olá 1L','Congelados','Olá','5609876000060',2.00,3.99,23.00,317,5,8,NULL,1,'2026-02-24 15:48:57',2,NULL),(79,'Ervilhas Congeladas','Congelados','Iglo','5609876000061',1.00,1.99,6.00,211,5,8,NULL,1,'2026-02-24 15:48:57',2,NULL),(80,'Peixe Panado','Congelados','Iglo','5609876000062',2.50,4.99,6.00,293,5,8,NULL,1,'2026-02-24 15:48:57',2,NULL),(81,'Papel Higiénico 12un','Higiene','Scottex','5609876000070',2.50,4.99,23.00,191,5,10,NULL,1,'2026-02-24 15:48:57',2,NULL),(82,'Champô Head&Shoulders','Higiene','Head&Shoulders','5609876000071',2.00,3.99,23.00,109,5,10,NULL,1,'2026-02-24 15:48:57',2,NULL),(83,'Pasta de Dentes Colgate','Higiene','Colgate','5609876000072',1.20,2.49,23.00,130,5,10,NULL,1,'2026-02-24 15:48:57',2,NULL),(84,'Desodorizante Dove','Higiene','Dove','5609876000073',1.80,3.49,23.00,30,5,10,NULL,1,'2026-02-24 15:48:57',2,NULL),(85,'Detergente Roupa Skip','Limpeza','Skip','5609876000080',4.00,7.99,23.00,32,5,10,NULL,1,'2026-02-24 15:48:57',2,NULL),(86,'Lava Loiça Fairy','Limpeza','Fairy','5609876000081',1.50,2.99,23.00,81,5,10,NULL,1,'2026-02-24 15:48:57',2,NULL),(87,'Lixívia Neoblanc','Limpeza','Neoblanc','5609876000082',0.80,1.59,23.00,149,5,10,NULL,1,'2026-02-24 15:48:57',2,NULL),(88,'sigma','Carnes','Nacional','33334434',3.00,7.00,23.00,80,5,NULL,NULL,1,'2026-03-17 11:44:26',1,NULL),(89,'sigma2','Congelados','Iglo','',0.15,1.00,23.00,10,5,NULL,'2027-10-11',1,'2026-03-19 11:19:19',1,NULL),(90,'rabo de boi','Bebidas','Coca-Cola','1931831',10.00,29.00,23.00,10,5,20,'2000-11-01',1,'2026-04-22 07:54:09',1,NULL);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` (`id`, `name`, `role`, `salary`, `created_at`, `store_id`, `email`, `phone`, `hire_date`, `status`, `department`, `nif`, `address`, `birth_date`, `notes`, `photo`, `emergency_contact`, `contract_type`, `position`, `base_salary`, `created_by`) VALUES (5,'Manuel Silva','Gerente',2500.00,'2026-02-24 15:48:57',1,'manuel.silva@supermercado.pt','912345001','2020-01-15','Ativo','Gestao',NULL,NULL,NULL,NULL,NULL,NULL,'Efetivo','Gerente',2500.00,NULL),(6,'Ana Costa','Subgerente',1800.00,'2026-02-24 15:48:57',1,'ana.costa@supermercado.pt','912345002','2021-03-10','Ativo','Vendas',NULL,NULL,NULL,NULL,NULL,NULL,'Efetivo','Chefe de Seccao',1800.00,NULL),(7,'João Ferreira','Operador de Caixa',950.00,'2026-02-24 15:48:57',1,'joao.ferreira@supermercado.pt','912345003','2022-06-01','Ativo','Loja',NULL,NULL,NULL,NULL,NULL,NULL,'Efetivo','Assistente de Loja',950.00,NULL),(8,'Maria Santos','Operadora de Caixa',950.00,'2026-02-24 15:48:57',1,'maria.santos@supermercado.pt','912345004','2022-08-15','Ativo','Loja',NULL,NULL,NULL,NULL,NULL,NULL,'Efetivo','Assistente de Loja',950.00,NULL),(9,'Pedro Almeida','Repositor',850.00,'2026-02-24 15:48:57',1,'pedro.almeida@supermercado.pt','912345005','2023-01-10','Ativo','Caixa',NULL,NULL,NULL,NULL,NULL,NULL,'Efetivo','Operador de Caixa',850.00,NULL),(10,'Sofia Rodrigues','Repositora',850.00,'2026-02-24 15:48:57',1,'sofia.rodrigues@supermercado.pt','912345006','2023-04-20','Ativo','Caixa',NULL,NULL,NULL,NULL,NULL,NULL,'Efetivo','Operador de Caixa',850.00,NULL),(11,'Carlos Martins','Talho',1100.00,'2026-02-24 15:48:57',1,'carlos.martins@supermercado.pt','912345007','2021-09-01','Ativo','Armazem',NULL,NULL,NULL,NULL,NULL,NULL,'Efetivo','Responsavel de Armazem',1100.00,NULL),(12,'Inês Pereira','Padaria',1000.00,'2026-02-24 15:48:57',1,'ines.pereira@supermercado.pt','912345008','2022-02-14','Ativo','Administrativo',NULL,NULL,NULL,NULL,NULL,NULL,'Efetivo','Assistente Administrativo',1000.00,NULL),(13,'António Oliveira','Gerente',2400.00,'2026-02-24 15:48:57',2,'antonio.oliveira@supermercado.pt','923456001','2019-05-20','Ativo','Gestao',NULL,NULL,NULL,NULL,NULL,NULL,'Efetivo','Sub-Gerente',2400.00,NULL),(14,'Catarina Ribeiro','Subgerente',1750.00,'2026-02-24 15:48:57',2,'catarina.ribeiro@supermercado.pt','923456002','2020-08-01','Ativo','Vendas',NULL,NULL,NULL,NULL,NULL,NULL,'Efetivo','Chefe de Seccao',1750.00,NULL),(15,'Rui Fernandes','Operador de Caixa',950.00,'2026-02-24 15:48:57',2,'rui.fernandes@supermercado.pt','923456003','2021-11-15','Ativo','Loja',NULL,NULL,NULL,NULL,NULL,NULL,'Efetivo','Assistente de Loja',950.00,NULL),(16,'Beatriz Lopes','Operadora de Caixa',950.00,'2026-02-24 15:48:57',2,'beatriz.lopes@supermercado.pt','923456004','2022-03-20','Ativo','Loja',NULL,NULL,NULL,NULL,NULL,NULL,'Efetivo','Assistente de Loja',950.00,NULL),(17,'Miguel Carvalho','Repositor',850.00,'2026-02-24 15:48:57',2,'miguel.carvalho@supermercado.pt','923456005','2022-07-01','Ativo','Caixa',NULL,NULL,NULL,NULL,NULL,NULL,'Efetivo','Operador de Caixa',850.00,NULL),(18,'Helena Sousa','Repositora',850.00,'2026-02-24 15:48:57',2,'helena.sousa@supermercado.pt','923456006','2023-02-10','Ativo','Caixa',NULL,NULL,NULL,NULL,NULL,NULL,'Efetivo','Operador de Caixa',850.00,NULL),(19,'Fernando Teixeira','Talho',1100.00,'2026-02-24 15:48:57',2,'fernando.teixeira@supermercado.pt','923456007','2020-12-01','Ativo','Armazem',NULL,NULL,NULL,NULL,NULL,NULL,'Efetivo','Responsavel de Armazem',1100.00,NULL),(20,'Mariana Gomes','Padaria',1000.00,'2026-02-24 15:48:57',2,'mariana.gomes@supermercado.pt','923456008','2021-06-15','Ativo','Administrativo',NULL,NULL,NULL,NULL,NULL,NULL,'Efetivo','Assistente Administrativo',1000.00,NULL),(22,'Ruas','caixa',0.00,'2026-03-19 12:06:54',1,'vascoruas4@gmail.com','','2026-03-03','Ativo','Caixa','111111111','Sitio Dos Vales',NULL,NULL,NULL,NULL,'Estágio','Caixa',0.00,1),(23,'adadda','aaa',899.97,'2026-04-22 09:55:33',1,'admin@example.com','2212121212','2026-04-02','Ativo','Reposição','121212121','dqadw',NULL,NULL,NULL,NULL,'Permanente','aaa',899.97,1);
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` (`id`, `key`, `value`, `description`, `updated_at`) VALUES (1,'company_name','PAP Market','Nome da empresa','2026-04-21 13:21:22'),(2,'company_nif','123456789','NIF da empresa','2026-04-21 13:21:22'),(3,'company_address','Rua Principal, 123, Lisboa','Morada da empresa','2026-04-21 13:21:22'),(4,'company_phone','210000000','Telefone','2026-04-21 13:21:22'),(5,'company_email','geral@papmarket.pt','Email de contacto','2026-04-21 13:21:22'),(6,'currency','EUR','Moeda','2026-04-21 13:21:22'),(7,'currency_symbol','€','Símbolo da moeda','2026-04-21 13:21:22'),(8,'vat_rate','23','Taxa de IVA padrão (%)','2026-04-21 13:21:22'),(9,'low_stock_threshold','10','Limiar de stock baixo','2026-04-21 13:21:22'),(10,'work_hours_per_day','8','Horas de trabalho por dia','2026-04-21 13:21:22'),(11,'overtime_rate','1.5','Multiplicador horas extra','2026-04-21 13:21:22'),(12,'backup_retention_days','30','Dias de retenção de backups','2026-04-21 13:21:22'),(13,'receipt_footer','Obrigado pela sua visita!','Rodapé do recibo','2026-04-21 13:21:22'),(14,'enable_loyalty','1','Programa fidelidade ativo','2026-04-21 13:21:22'),(15,'loyalty_points_per_euro','1','Pontos por euro gasto','2026-04-21 13:21:22');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `promotions`
--

LOCK TABLES `promotions` WRITE;
/*!40000 ALTER TABLE `promotions` DISABLE KEYS */;
INSERT INTO `promotions` (`id`, `name`, `product_id`, `category`, `type`, `value`, `discount_type`, `discount_value`, `start_date`, `end_date`, `active`, `is_active`, `store_id`, `created_at`, `updated_at`, `qr_code`) VALUES (1,'sigma',NULL,'','percentage',10.00,'percentage',10.00,'2026-03-17','2026-03-19',1,1,1,'2026-03-17 11:49:12','2026-03-17 12:03:07','data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAANIAAADSCAIAAACw+wkVAAAACXBIWXMAAA7EAAAOxAGVKw4bAAADOElEQVR4nO3dS07sMBRAQYLe/rfcTGHiFrLMucmrmrbo75EHucS+Ph7n9Xolr3td1+LRnXe1fuY7+qzfAP8j2RGQHQHZEZAdAdkRkB0B2RGQHYF/64erK/5rM6/ar7+rczOMO/5GVjsCsiMgOwKyIyA7ArIjIDsCsiMgOwJvphRr56YFM++HWLvjtGDHzue12hGQHQHZEZAdAdkRkB0B2RGQHQHZEdiaUjyPfZn+htWOgOwIyI6A7AjIjoDsCMiOgOwIyI6AKcUPJg1/w2pHQHYEZEdAdgRkR0B2BGRHQHYEZEdga0oxc4+jHec+UTX/mPkbWe0IyI6A7AjIjoDsCMiOgOwIyI6A7Ai8mVK4t+C7cydg77jjb2S1IyA7ArIjIDsCsiMgOwKyIyA7ArIjcM38X/uZnne2dsVqR0B2BGRHQHYEZEdAdgRkR0B2BGRHwLkUP5ybQ5hwfGe1IyA7ArIjIDsCsiMgOwKyIyA7ArIjsLXj0851+XN/uzZzWnDHOcTON2m1IyA7ArIjIDsCsiMgOwKyIyA7ArIjkO34NPM0hXPzj2qyUll/XqsdAdkRkB0B2RGQHQHZEZAdAdkRkB2BN5e/qzsedlTvqpol3PF7ttoRkB0B2RGQHQHZEZAdAdkRkB0B2RE4eC7FuR2f1s498znP28NqzWpHQHYEZEdAdgRkR0B2BGRHQHYEZEcg2/Fppjte8b/jvSNWOwKyIyA7ArIjIDsCsiMgOwKyIyA7AhNvLNg0c1qwY+bdITvvympHQHYEZEdAdgRkR0B2BGRHQHYEZEfgzY5PM++0OHddvtpp6nn7XzmXgnFkR0B2BGRHQHYEZEdAdgRkR0B2BLbOpaiucVdmXvGf+bqmFIwjOwKyIyA7ArIjIDsCsiMgOwKyI3Dw9Oznqe6HWKsmOnZ84mZkR0B2BGRHQHYEZEdAdgRkR0B2BEwpfmHm+RAzd6lyLwXjyI6A7AjIjoDsCMiOgOwIyI6A7AhsTSlm7st0zs5V+0p1WoYpBePIjoDsCMiOgOwIyI6A7AjIjoDsCDT//n/UuWnBzF2bqtnJzrdhtSMgOwKyIyA7ArIjIDsCsiMgOwKyI/AFpt/KpS+hqdYAAAAASUVORK5CYII='),(2,'Black Friday Test',NULL,NULL,'percentage',20.00,'percentage',20.00,'2026-03-17','2026-04-16',1,1,1,'2026-03-17 11:54:25','2026-03-17 12:03:07','data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAANIAAADSCAIAAACw+wkVAAAACXBIWXMAAA7EAAAOxAGVKw4bAAADLElEQVR4nO3dQW4CIQBA0U7j/a9st9MNtkHymfG9rWmk+sMCZDi+buf5fNZDeLPjOOohvNl3PQA+kewIyI6A7AjIjoDsCMiOgOwIyI7AY/zyniv+1ar9uved+Zyv+B2Z7QjIjoDsCMiOgOwIyI6A7AjIjoDsCLzYpRjbc9V+hv/o72b+I7MdAdkRkB0B2RGQHQHZEZAdAdkRkB2BqV0Kzsar9uPdgj3PQ6xjtiMgOwKyIyA7ArIjIDsCsiMgOwKyI2CX4m3ud3vEOmY7ArIjIDsCsiMgOwKyIyA7ArIjIDsCU7sUn3YC4Ir2/I7MdgRkR0B2BGRHQHYEZEdAdgRkR0B2BG748/916/IzT21a97dXZLYjIDsCsiMgOwKyIyA7ArIjIDsCsiPw2PO39pzd7zsy2xGQHQHZEZAdAdkRkB0B2RGQHQHZEXAvxS/rTi24W/vMbEdAdgRkR0B2BGRHQHYEZEdAdgRkR+CYWQGvnmJ0xRX/6mlRY9VnZbYjIDsCsiMgOwKyIyA7ArIjIDsCsiOw6YUHV9zhqG6PqEY1875mOwKyIyA7ArIjIDsCsiMgOwKyIyA7AlNL2Huuj49Vpwf2vHm7+iTNdgRkR0B2BGRHQHYEZEdAdgRkR0B2BBY+8WlGdZZixp5nKcaqMZvtCMiOgOwIyI6A7AjIjoDsCMiOgOwITO1S3E+1al+pznCY7QjIjoDsCMiOgOwIyI6A7AjIjoDsCNxwUX7P0xJ7nnioPiuzHQHZEZAdAdkRkB0B2RGQHQHZEZAdgcf45T1PWuz5bKXxqKr7MGasG7PZjoDsCMiOgOwIyI6A7AjIjoDsCMiOwItdirEr/sZ/bM+7tSvrngdltiMgOwKyIyA7ArIjIDsCsiMgOwKyIzC1S8HZujX9ddaNyhOf2I7sCMiOgOwIyI6A7AjIjoDsCMiOgF2Kf6jW9Gf2P9ZxezYXIzsCsiMgOwKyIyA7ArIjIDsCsiMwtUux520K97Pnrd3upeBiZEdAdgRkR0B2BGRHQHYEZEdAdgRe7FLseV/Cp1l3a/fM+84w2xGQHQHZEZAdAdkRkB0B2RGQHQHZEfgBsSLHqHMgvAwAAAAASUVORK5CYII='),(8,'Promoção de Verão',NULL,NULL,'percentage',10.00,'percentage',10.00,'2026-06-01','2026-08-31',1,1,1,'2026-04-17 14:22:11',NULL,NULL),(9,'Desconto Semanal',NULL,NULL,'percentage',5.00,'percentage',5.00,'2026-04-14','2026-04-20',1,1,1,'2026-04-17 14:22:11',NULL,NULL),(10,'Liquidação Congelados',NULL,NULL,'percentage',20.00,'percentage',20.00,'2026-04-01','2026-04-30',1,1,1,'2026-04-17 14:22:11',NULL,NULL),(11,'Aniversário PAP Market',NULL,NULL,'percentage',15.00,'percentage',15.00,'2026-05-01','2026-05-15',1,1,1,'2026-04-17 14:22:11',NULL,NULL),(12,'hj',20,'','percentage',10.00,'percentage',10.00,'2026-04-23',NULL,1,1,1,'2026-04-23 11:24:41','2026-04-23 11:24:41','data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAANIAAADSCAIAAACw+wkVAAAACXBIWXMAAA7EAAAOxAGVKw4bAAADPUlEQVR4nO3dS47cIABAwXE0979yZ5vZ4CgEPbCrtqP0x/3EAgJcX4/z+XwWvfJ1Xf/8b2c+1cz77ulX/QF4I9kRkB0B2RGQHQHZEZAdAdkRkB2B7/Gf1834z1g3az9+5T2fxp6favwkjXYEZEdAdgRkR0B2BGRHQHYEZEdAdgRuVinG1q0WVDPv63Y8VN9oz9/IaEdAdgRkR0B2BGRHQHYEZEdAdgRkR2BqleJtnnfyUsVoR0B2BGRHQHYEZEdAdgRkR0B2BGRHwCrFD+tunthzp0XFaEdAdgRkR0B2BGRHQHYEZEdAdgRkR2BqleJtc+sn2vM3MtoRkB0B2RGQHQHZEZAdAdkRkB0B2RG4WaV42xlHM/shKnt+qjGjHQHZEZAdAdkRkB0B2RGQHQHZEZAdgWvP/2tf2fOu6ecx2hGQHQHZEZAdAdkRkB0B2RGQHQHZEXAvxX+zbh/G81Y4jHYEZEdAdgRkR0B2BGRHQHYEZEdAdgRu9lLM3Plc3Re97n33/EZjez5nox0B2RGQHQHZEZAdAdkRkB0B2RGQHYGb6e/qnoY933esuh+iWnexSsFhZEdAdgRkR0B2BGRHQHYEZEdAdgSyE59m5tarPRwn3lM9Vu20MNoRkB0B2RGQHQHZEZAdAdkRkB0B2RGY2ktx89LLzina8wSkPVWnVI0Z7QjIjoDsCMiOgOwIyI6A7AjIjoDsCNzcS/E2bp7407qnYbQjIDsCsiMgOwKyIyA7ArIjIDsCsiPwtCOMvs6ctZ9x4p4Vox0B2RGQHQHZEZAdAdkRkB0B2RGQHYGFJz6ts+eOh7edUjXzjYx2BGRHQHYEZEdAdgRkR0B2BGRHQHYEpm7PXneb9In7EsZm1jCq1ZExJz5xGNkRkB0B2RGQHQHZEZAdAdkRkB2BqVUK/t6JeynWrX8Y7QjIjoDsCMiOgOwIyI6A7AjIjoDsCFil+KHa8bAn91LwKLIjIDsCsiMgOwKyIyA7ArIjIDsCU6sUJ868j+15E/XM++75GxntCMiOgOwIyI6A7AjIjoDsCMiOgOwIrJo6D83My1fnMu15H8a6b2S0IyA7ArIjIDsCsiMgOwKyIyA7ArIj8BvZJM2ZcwVTZwAAAABJRU5ErkJggg==');
/*!40000 ALTER TABLE `promotions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `promotion_categories`
--

LOCK TABLES `promotion_categories` WRITE;
/*!40000 ALTER TABLE `promotion_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `promotion_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `promotion_products`
--

LOCK TABLES `promotion_products` WRITE;
/*!40000 ALTER TABLE `promotion_products` DISABLE KEYS */;
/*!40000 ALTER TABLE `promotion_products` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-19 14:43:44

-- ===== COMPLEMENTOS DO ESQUEMA (equivalente ao migrate.php, seguro/idempotente no TiDB) =====

-- Colunas extra (IF NOT EXISTS evita erro se já existirem)
ALTER TABLE `products`     ADD COLUMN IF NOT EXISTS `brand` VARCHAR(100) DEFAULT NULL;
ALTER TABLE `products`     ADD COLUMN IF NOT EXISTS `barcode` VARCHAR(50) DEFAULT NULL;
ALTER TABLE `products`     ADD COLUMN IF NOT EXISTS `vat` DECIMAL(5,2) DEFAULT 23.00;
ALTER TABLE `products`     ADD COLUMN IF NOT EXISTS `min_stock` INT DEFAULT 5;
ALTER TABLE `products`     ADD COLUMN IF NOT EXISTS `supplier_id` INT DEFAULT NULL;
ALTER TABLE `products`     ADD COLUMN IF NOT EXISTS `active` TINYINT DEFAULT 1;
ALTER TABLE `suppliers`    ADD COLUMN IF NOT EXISTS `email` VARCHAR(100) DEFAULT NULL;
ALTER TABLE `suppliers`    ADD COLUMN IF NOT EXISTS `phone` VARCHAR(20) DEFAULT NULL;
ALTER TABLE `suppliers`    ADD COLUMN IF NOT EXISTS `address` TEXT DEFAULT NULL;
ALTER TABLE `suppliers`    ADD COLUMN IF NOT EXISTS `delivery_days` INT DEFAULT 2;
ALTER TABLE `suppliers`    ADD COLUMN IF NOT EXISTS `active` TINYINT DEFAULT 1;
ALTER TABLE `orders`       ADD COLUMN IF NOT EXISTS `status` ENUM('pending','processed','shipped','delivered') DEFAULT 'pending';
ALTER TABLE `orders`       ADD COLUMN IF NOT EXISTS `total_cost` DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE `orders`       ADD COLUMN IF NOT EXISTS `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `orders`       ADD COLUMN IF NOT EXISTS `processed_at` DATETIME DEFAULT NULL;
ALTER TABLE `orders`       ADD COLUMN IF NOT EXISTS `delivered_at` DATETIME DEFAULT NULL;
ALTER TABLE `orders`       ADD COLUMN IF NOT EXISTS `received` TINYINT DEFAULT 0;
ALTER TABLE `sales`        ADD COLUMN IF NOT EXISTS `payment_method` VARCHAR(50) DEFAULT 'Dinheiro';
ALTER TABLE `sale_items`   ADD COLUMN IF NOT EXISTS `cost_price` DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE `employees`    ADD COLUMN IF NOT EXISTS `active` TINYINT DEFAULT 1;
ALTER TABLE `transactions` ADD COLUMN IF NOT EXISTS `reference_type` VARCHAR(50) DEFAULT NULL;
ALTER TABLE `transactions` ADD COLUMN IF NOT EXISTS `reference_id` INT DEFAULT NULL;

-- Tabelas que faltam
CREATE TABLE IF NOT EXISTS `order_messages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `type` ENUM('created','processed','shipped','delivered','partial') NOT NULL DEFAULT 'created',
  `message` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_order_messages_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `alerts` (
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

-- Views (recria de forma segura)
DROP VIEW IF EXISTS `daily_profit`;
DROP TABLE IF EXISTS `daily_profit`;
CREATE VIEW `daily_profit` AS
  SELECT DATE(s.sale_date) AS date, SUM(s.total) AS revenue,
         COALESCE(SUM(si.quantity * si.cost_price), 0) AS cogs,
         SUM(s.total) - COALESCE(SUM(si.quantity * si.cost_price), 0) AS profit,
         COUNT(DISTINCT s.id) AS num_sales
  FROM sales s LEFT JOIN sale_items si ON si.sale_id = s.id
  GROUP BY DATE(s.sale_date);

DROP VIEW IF EXISTS `monthly_profit`;
DROP TABLE IF EXISTS `monthly_profit`;
CREATE VIEW `monthly_profit` AS
  SELECT DATE_FORMAT(s.sale_date, '%Y-%m') AS month, SUM(s.total) AS revenue,
         COALESCE(SUM(si.quantity * si.cost_price), 0) AS cogs,
         SUM(s.total) - COALESCE(SUM(si.quantity * si.cost_price), 0) AS profit
  FROM sales s LEFT JOIN sale_items si ON si.sale_id = s.id
  GROUP BY DATE_FORMAT(s.sale_date, '%Y-%m');

DROP VIEW IF EXISTS `monthly_top_product`;
DROP TABLE IF EXISTS `monthly_top_product`;
CREATE VIEW `monthly_top_product` AS
  SELECT DATE_FORMAT(s.sale_date, '%Y-%m') AS month, p.id AS product_id, p.name AS product_name,
         p.category, SUM(si.quantity) AS total_qty, SUM(si.quantity * si.price) AS total_revenue
  FROM sale_items si JOIN sales s ON si.sale_id = s.id JOIN products p ON si.product_id = p.id
  GROUP BY DATE_FORMAT(s.sale_date, '%Y-%m'), p.id;

SET FOREIGN_KEY_CHECKS=1;
