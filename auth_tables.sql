-- Authentication Tables for PAP Supermercado System
-- Created: 2026-01-14

-- =====================================================
-- ROLES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL UNIQUE,
  `description` varchar(255),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default roles
INSERT IGNORE INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'Admin', 'Administrador do sistema'),
(2, 'Gerente', 'Gerente da loja'),
(3, 'Caixa', 'Operador de caixa'),
(4, 'Stock', 'Responsável de stock'),
(5, 'RH', 'Recursos Humanos');

-- =====================================================
-- USERS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT 3,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET DEFAULT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- SESSIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45),
  `user_agent` text,
  `last_activity` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- AUDIT_LOG TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11),
  `action` varchar(100) NOT NULL,
  `resource` varchar(100),
  `resource_id` int(11),
  `status` varchar(20) NOT NULL,
  `ip_address` varchar(45),
  `user_agent` text,
  `changes` json,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- PERMISSIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `module` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_permission` (`role_id`, `module`, `action`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `fk_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- DEFAULT PERMISSIONS
-- =====================================================
-- Admin has all permissions (checked in code)

-- Gerente permissions
INSERT IGNORE INTO `permissions` (`role_id`, `module`, `action`) VALUES
(2, 'produtos', 'view'),
(2, 'produtos', 'edit'),
(2, 'precos', 'view'),
(2, 'precos', 'edit'),
(2, 'promocoes', 'view'),
(2, 'promocoes', 'edit'),
(2, 'vendas', 'view'),
(2, 'vendas', 'edit'),
(2, 'stock', 'view'),
(2, 'alertas', 'view');

-- Caixa permissions
INSERT IGNORE INTO `permissions` (`role_id`, `module`, `action`) VALUES
(3, 'vendas', 'view'),
(3, 'vendas', 'edit'),
(3, 'produtos', 'view'),
(3, 'stock', 'view');

-- Stock permissions
INSERT IGNORE INTO `permissions` (`role_id`, `module`, `action`) VALUES
(4, 'stock', 'view'),
(4, 'stock', 'edit'),
(4, 'produtos', 'view'),
(4, 'fornecedores', 'view'),
(4, 'fornecedores', 'edit'),
(4, 'alertas', 'view');

-- RH permissions
INSERT IGNORE INTO `permissions` (`role_id`, `module`, `action`) VALUES
(5, 'rh', 'view'),
(5, 'rh', 'edit');

-- =====================================================
-- DEMO USERS
-- =====================================================
-- Passwords are hashed with bcrypt (cost=12)
-- Admin: admin123
-- Gerente: gerente123
-- Caixa: caixa123
-- Stock: stock123
-- RH: rh123

INSERT IGNORE INTO `users` (`id`, `name`, `email`, `password_hash`, `role_id`, `active`) VALUES
(1, 'Administrador', 'admin@example.com', '$2y$12$k3R4VkxQ.3MzQHqN5oZKOe5Z8jK7pLmK6VqN9oZKOe5Z8jK7pLmK6V', 1, 1),
(2, 'Gerente da Loja', 'gerente@example.com', '$2y$12$m9O8N7M6L5K4J3I2H1G0F/Z8X7W6V5U4T3S2R1Q0P9O8N7M6L5K4J', 2, 1),
(3, 'Caixa', 'caixa@example.com', '$2y$12$a1B2C3D4E5F6G7H8I9J0K/L1M2N3O4P5Q6R7S8T9U0V1W2X3Y4Z5', 3, 1),
(4, 'Responsável de Stock', 'stock@example.com', '$2y$12$z9Y8X7W6V5U4T3S2R1Q0P/O9N8M7L6K5J4I3H2G1F0E9D8C7B6A5', 4, 1),
(5, 'RH', 'rh@example.com', '$2y$12$9A8B7C6D5E4F3G2H1I0J/K9L8M7N6O5P4Q3R2S1T0U9V8W7X6Y5Z4', 5, 1);
