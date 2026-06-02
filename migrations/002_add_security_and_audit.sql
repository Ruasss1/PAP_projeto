-- 002_add_security_and_audit.sql
-- Adds authentication, role-based access control, and audit logging

-- ============================================
-- USERS & AUTHENTICATION
-- ============================================

CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) UNIQUE NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int,
  `active` tinyint(1) DEFAULT 1,
  `last_login_at` datetime,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roles` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(100) UNIQUE NOT NULL,
  `description` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `role_id` int NOT NULL,
  `resource` varchar(100) NOT NULL,
  `action` varchar(50) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `role_resource_action` (`role_id`, `resource`, `action`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- AUDIT LOGGING
-- ============================================

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` int,
  `action` varchar(50) NOT NULL,
  `resource` varchar(100) NOT NULL,
  `resource_id` int,
  `changes` json,
  `ip_address` varchar(45),
  `user_agent` varchar(500),
  `status` varchar(20),
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_user_id` (`user_id`),
  KEY `idx_resource` (`resource`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) PRIMARY KEY,
  `user_id` int NOT NULL,
  `ip_address` varchar(45),
  `user_agent` varchar(500),
  `payload` longtext,
  `last_activity` int,
  `expires_at` datetime,
  KEY `idx_user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSERT DEFAULT ROLES
-- ============================================

INSERT INTO `roles` (`name`, `description`) VALUES 
('admin', 'Administrador - Acesso total ao sistema'),
('gerente', 'Gerente - Acesso a vendas, stock, financeiro e RH'),
('caixa', 'Caixa - Apenas vendas e consulta de stock'),
('stock', 'Responsável de Stock - Gestão de inventário'),
('rh', 'RH - Gestão de funcionários e folhas de pagamento');

-- ============================================
-- INSERT DEFAULT ADMIN PERMISSIONS
-- ============================================

-- Admin pode fazer tudo
INSERT INTO `permissions` (`role_id`, `resource`, `action`) 
SELECT r.id, 'all', 'all' FROM `roles` r WHERE r.name = 'admin';

-- Gerente
INSERT INTO `permissions` (`role_id`, `resource`, `action`) 
SELECT r.id, resource, action FROM `roles` r, 
(SELECT 'sales' as resource, 'create' as action UNION
 SELECT 'sales', 'view' UNION
 SELECT 'products', 'view' UNION
 SELECT 'stock', 'view' UNION
 SELECT 'stock', 'adjust' UNION
 SELECT 'reports', 'view' UNION
 SELECT 'employees', 'view' UNION
 SELECT 'employees', 'edit') t
WHERE r.name = 'gerente';

-- Caixa
INSERT INTO `permissions` (`role_id`, `resource`, `action`) 
SELECT r.id, resource, action FROM `roles` r, 
(SELECT 'sales' as resource, 'create' as action UNION
 SELECT 'products', 'view' UNION
 SELECT 'stock', 'view') t
WHERE r.name = 'caixa';

-- Stock
INSERT INTO `permissions` (`role_id`, `resource`, `action`) 
SELECT r.id, resource, action FROM `roles` r, 
(SELECT 'stock' as resource, 'view' as action UNION
 SELECT 'stock', 'edit' UNION
 SELECT 'stock', 'count' UNION
 SELECT 'products', 'view') t
WHERE r.name = 'stock';

-- RH
INSERT INTO `permissions` (`role_id`, `resource`, `action`) 
SELECT r.id, resource, action FROM `roles` r, 
(SELECT 'employees' as resource, 'view' as action UNION
 SELECT 'employees', 'edit' UNION
 SELECT 'employees', 'create' UNION
 SELECT 'payroll', 'view' UNION
 SELECT 'payroll', 'create') t
WHERE r.name = 'rh';
