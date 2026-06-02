-- ========================================
-- MIGRATION 006: CUSTOMERS AND LOYALTY SYSTEM
-- PHASE 6: Customer Management & Loyalty Program
-- Data: 15 de janeiro de 2026
-- ========================================

-- Tabela de clientes
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `nif` VARCHAR(9) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `birth_date` DATE DEFAULT NULL,
    `loyalty_card_number` VARCHAR(50) UNIQUE DEFAULT NULL,
    `points_balance` INT(11) DEFAULT 0,
    `total_spent` DECIMAL(10,2) DEFAULT 0.00,
    `total_purchases` INT(11) DEFAULT 0,
    `status` ENUM('Ativo', 'Inativo', 'Bloqueado') DEFAULT 'Ativo',
    `registration_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `last_purchase_date` DATETIME DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ux_email` (`email`),
    UNIQUE KEY `ux_nif` (`nif`),
    INDEX `idx_loyalty_card` (`loyalty_card_number`),
    INDEX `idx_status` (`status`),
    INDEX `idx_registration` (`registration_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabela de histórico de pontos
CREATE TABLE IF NOT EXISTS `loyalty_points_history` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `customer_id` INT(11) NOT NULL,
    `points` INT(11) NOT NULL COMMENT 'Positivo = ganhou, Negativo = gastou',
    `type` ENUM('Compra', 'Resgate', 'Bonus', 'Aniversario', 'Promocao', 'Ajuste', 'Expiracao') NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `sale_id` INT(11) DEFAULT NULL COMMENT 'Link para venda (se aplicável)',
    `reward_id` INT(11) DEFAULT NULL COMMENT 'Link para recompensa resgatada',
    `balance_after` INT(11) NOT NULL COMMENT 'Saldo após operação',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT(11) DEFAULT NULL COMMENT 'User ID que registou',
    PRIMARY KEY (`id`),
    INDEX `idx_customer` (`customer_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabela de recompensas/prémios
CREATE TABLE IF NOT EXISTS `loyalty_rewards` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `points_required` INT(11) NOT NULL,
    `reward_type` ENUM('Desconto', 'Produto', 'Voucher', 'Cashback') NOT NULL,
    `reward_value` DECIMAL(10,2) DEFAULT NULL COMMENT 'Valor em € ou % ou ID produto',
    `stock_quantity` INT(11) DEFAULT NULL COMMENT 'Quantidade disponível (null = ilimitado)',
    `valid_from` DATE DEFAULT NULL,
    `valid_until` DATE DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `times_redeemed` INT(11) DEFAULT 0,
    `image_url` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_points` (`points_required`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabela de resgates de recompensas
CREATE TABLE IF NOT EXISTS `loyalty_redemptions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `customer_id` INT(11) NOT NULL,
    `reward_id` INT(11) NOT NULL,
    `points_spent` INT(11) NOT NULL,
    `voucher_code` VARCHAR(50) DEFAULT NULL,
    `status` ENUM('Pendente', 'Utilizado', 'Expirado', 'Cancelado') DEFAULT 'Pendente',
    `used_at` DATETIME DEFAULT NULL,
    `used_in_sale_id` INT(11) DEFAULT NULL,
    `expires_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_customer` (`customer_id`),
    INDEX `idx_reward` (`reward_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_voucher` (`voucher_code`),
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`reward_id`) REFERENCES `loyalty_rewards`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabela de campanhas promocionais
CREATE TABLE IF NOT EXISTS `marketing_campaigns` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `type` ENUM('Pontos Bonus', 'Desconto', 'Produto Gratis', 'Cashback') NOT NULL,
    `bonus_multiplier` DECIMAL(3,2) DEFAULT 1.00 COMMENT 'Ex: 2.00 = pontos dobrados',
    `discount_percentage` DECIMAL(5,2) DEFAULT NULL,
    `target_customers` ENUM('Todos', 'Novos', 'VIP', 'Inativos') DEFAULT 'Todos',
    `start_date` DATETIME NOT NULL,
    `end_date` DATETIME NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `customers_reached` INT(11) DEFAULT 0,
    `total_impact` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Valor total gerado',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Adicionar coluna customer_id à tabela sales
ALTER TABLE `sales` 
ADD COLUMN `customer_id` INT(11) DEFAULT NULL AFTER `id`,
ADD COLUMN `points_earned` INT(11) DEFAULT 0 AFTER `total`,
ADD COLUMN `points_used` INT(11) DEFAULT 0 AFTER `points_earned`,
ADD INDEX `idx_customer` (`customer_id`);

-- View de clientes VIP (top spenders)
CREATE OR REPLACE VIEW `vip_customers` AS
SELECT 
    c.id,
    c.name,
    c.email,
    c.phone,
    c.loyalty_card_number,
    c.points_balance,
    c.total_spent,
    c.total_purchases,
    c.registration_date,
    c.last_purchase_date,
    ROUND(c.total_spent / NULLIF(c.total_purchases, 0), 2) as avg_purchase_value,
    DATEDIFF(NOW(), c.last_purchase_date) as days_since_last_purchase
FROM customers c
WHERE c.status = 'Ativo' 
  AND c.total_spent > 500
ORDER BY c.total_spent DESC;

-- View de clientes inativos (>90 dias sem comprar)
CREATE OR REPLACE VIEW `inactive_customers` AS
SELECT 
    c.id,
    c.name,
    c.email,
    c.phone,
    c.total_spent,
    c.total_purchases,
    c.last_purchase_date,
    DATEDIFF(NOW(), c.last_purchase_date) as days_inactive
FROM customers c
WHERE c.status = 'Ativo' 
  AND c.last_purchase_date IS NOT NULL
  AND DATEDIFF(NOW(), c.last_purchase_date) > 90
ORDER BY days_inactive DESC;

-- View de histórico completo do cliente
CREATE OR REPLACE VIEW `customer_full_history` AS
SELECT 
    c.id as customer_id,
    c.name as customer_name,
    s.id as sale_id,
    s.sale_date,
    s.total as sale_total,
    s.payment_method,
    s.points_earned,
    s.points_used,
    COUNT(si.id) as items_count
FROM customers c
LEFT JOIN sales s ON s.customer_id = c.id
LEFT JOIN sale_items si ON si.sale_id = s.id
GROUP BY c.id, s.id
ORDER BY c.id, s.sale_date DESC;

-- Trigger para atualizar totais do cliente após venda
DELIMITER $$
CREATE TRIGGER `update_customer_totals_after_sale` 
AFTER INSERT ON `sales` 
FOR EACH ROW
BEGIN
    IF NEW.customer_id IS NOT NULL THEN
        UPDATE customers 
        SET 
            total_spent = total_spent + NEW.total,
            total_purchases = total_purchases + 1,
            last_purchase_date = NEW.sale_date
        WHERE id = NEW.customer_id;
    END IF;
END$$
DELIMITER ;

-- Trigger para adicionar pontos no histórico
DELIMITER $$
CREATE TRIGGER `log_points_on_sale` 
AFTER INSERT ON `sales` 
FOR EACH ROW
BEGIN
    IF NEW.customer_id IS NOT NULL AND NEW.points_earned > 0 THEN
        INSERT INTO loyalty_points_history 
            (customer_id, points, type, description, sale_id, balance_after)
        VALUES 
            (NEW.customer_id, NEW.points_earned, 'Compra', 
             CONCAT('Venda #', NEW.id), NEW.id,
             (SELECT points_balance FROM customers WHERE id = NEW.customer_id));
    END IF;
END$$
DELIMITER ;

-- Trigger para atualizar saldo de pontos
DELIMITER $$
CREATE TRIGGER `update_points_balance` 
AFTER INSERT ON `loyalty_points_history` 
FOR EACH ROW
BEGIN
    UPDATE customers 
    SET points_balance = points_balance + NEW.points
    WHERE id = NEW.customer_id;
END$$
DELIMITER ;

-- Dados de exemplo para recompensas
INSERT INTO `loyalty_rewards` 
    (`name`, `description`, `points_required`, `reward_type`, `reward_value`, `stock_quantity`, `is_active`) 
VALUES
    ('Desconto 5€', 'Vale de desconto de 5€ na próxima compra', 500, 'Voucher', 5.00, NULL, 1),
    ('Desconto 10€', 'Vale de desconto de 10€ na próxima compra', 1000, 'Voucher', 10.00, NULL, 1),
    ('Desconto 20€', 'Vale de desconto de 20€ na próxima compra', 2000, 'Voucher', 20.00, NULL, 1),
    ('Café Grátis', 'Café premium grátis', 200, 'Produto', NULL, 100, 1),
    ('Cashback 5%', 'Devolução de 5% em pontos na próxima compra', 1500, 'Cashback', 5.00, NULL, 1);

-- FIM DA MIGRATION 006
