-- ========================================
-- MIGRATION 007: NOTIFICATIONS AND ALERTS SYSTEM
-- PHASE 7: Notifications, Alerts & Tasks
-- Data: 15 de janeiro de 2026
-- ========================================

-- Tabela de notificações
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) DEFAULT NULL COMMENT 'NULL = todos os utilizadores',
    `type` ENUM('Info', 'Sucesso', 'Aviso', 'Erro', 'Stock', 'Venda', 'RH', 'Cliente', 'Sistema') NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `link` VARCHAR(255) DEFAULT NULL COMMENT 'URL para ação relacionada',
    `icon` VARCHAR(50) DEFAULT NULL COMMENT 'Emoji ou classe de ícone',
    `priority` ENUM('Baixa', 'Normal', 'Alta', 'Urgente') DEFAULT 'Normal',
    `is_read` TINYINT(1) DEFAULT 0,
    `read_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME DEFAULT NULL COMMENT 'Data de expiração (opcional)',
    PRIMARY KEY (`id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_priority` (`priority`),
    INDEX `idx_read` (`is_read`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabela de alertas automáticos
CREATE TABLE IF NOT EXISTS `system_alerts` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `alert_type` ENUM('Stock Baixo', 'Stock Critico', 'Produto Esgotado', 'Aniversario Cliente', 
                      'Cliente Inativo', 'Ferias Pendentes', 'Venda Grande', 'Meta Atingida') NOT NULL,
    `entity_type` VARCHAR(50) NOT NULL COMMENT 'produto, cliente, colaborador, etc',
    `entity_id` INT(11) NOT NULL COMMENT 'ID da entidade',
    `threshold_value` DECIMAL(10,2) DEFAULT NULL COMMENT 'Valor do limite que disparou alerta',
    `current_value` DECIMAL(10,2) DEFAULT NULL COMMENT 'Valor atual',
    `message` TEXT NOT NULL,
    `status` ENUM('Ativo', 'Resolvido', 'Ignorado') DEFAULT 'Ativo',
    `resolved_at` DATETIME DEFAULT NULL,
    `resolved_by` INT(11) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_type` (`alert_type`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabela de tarefas/lembretes
CREATE TABLE IF NOT EXISTS `tasks` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `assigned_to` INT(11) DEFAULT NULL COMMENT 'User ID responsável',
    `created_by` INT(11) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `category` ENUM('Geral', 'Stock', 'RH', 'Clientes', 'Financeiro', 'Manutencao') DEFAULT 'Geral',
    `priority` ENUM('Baixa', 'Normal', 'Alta', 'Urgente') DEFAULT 'Normal',
    `status` ENUM('Pendente', 'Em Progresso', 'Concluida', 'Cancelada') DEFAULT 'Pendente',
    `due_date` DATE DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `completed_by` INT(11) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_assigned` (`assigned_to`),
    INDEX `idx_status` (`status`),
    INDEX `idx_priority` (`priority`),
    INDEX `idx_due` (`due_date`),
    INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabela de configurações de alertas
CREATE TABLE IF NOT EXISTS `alert_settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `alert_type` VARCHAR(100) NOT NULL UNIQUE,
    `is_enabled` TINYINT(1) DEFAULT 1,
    `threshold_value` DECIMAL(10,2) DEFAULT NULL COMMENT 'Valor do limite',
    `notification_method` SET('Sistema', 'Email', 'SMS') DEFAULT 'Sistema',
    `recipients` TEXT DEFAULT NULL COMMENT 'JSON com user_ids ou emails',
    `check_frequency_minutes` INT(11) DEFAULT 60 COMMENT 'Frequência de verificação',
    `last_check_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ux_alert_type` (`alert_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabela de histórico de envio de emails
CREATE TABLE IF NOT EXISTS `email_log` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `recipient` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `body` TEXT DEFAULT NULL,
    `status` ENUM('Enviado', 'Falhado', 'Pendente') DEFAULT 'Pendente',
    `error_message` TEXT DEFAULT NULL,
    `sent_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_recipient` (`recipient`),
    INDEX `idx_sent` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- View de notificações não lidas por utilizador
CREATE OR REPLACE VIEW `unread_notifications_count` AS
SELECT 
    user_id,
    COUNT(*) as unread_count,
    SUM(CASE WHEN priority = 'Urgente' THEN 1 ELSE 0 END) as urgent_count,
    SUM(CASE WHEN priority = 'Alta' THEN 1 ELSE 0 END) as high_count
FROM notifications
WHERE is_read = 0 
  AND (expires_at IS NULL OR expires_at > NOW())
GROUP BY user_id;

-- View de alertas ativos por tipo
CREATE OR REPLACE VIEW `active_alerts_summary` AS
SELECT 
    alert_type,
    COUNT(*) as total_alerts,
    COUNT(DISTINCT entity_id) as affected_entities,
    MIN(created_at) as oldest_alert,
    MAX(created_at) as newest_alert
FROM system_alerts
WHERE status = 'Ativo'
GROUP BY alert_type
ORDER BY total_alerts DESC;

-- View de tarefas pendentes por utilizador
CREATE OR REPLACE VIEW `pending_tasks_summary` AS
SELECT 
    assigned_to as user_id,
    COUNT(*) as total_tasks,
    SUM(CASE WHEN priority = 'Urgente' THEN 1 ELSE 0 END) as urgent_tasks,
    SUM(CASE WHEN priority = 'Alta' THEN 1 ELSE 0 END) as high_tasks,
    SUM(CASE WHEN due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_tasks,
    SUM(CASE WHEN due_date = CURDATE() THEN 1 ELSE 0 END) as due_today
FROM tasks
WHERE status IN ('Pendente', 'Em Progresso')
GROUP BY assigned_to;

-- Trigger para criar notificação quando stock fica baixo
DELIMITER $$
CREATE TRIGGER `notify_low_stock_on_sale` 
AFTER UPDATE ON `products` 
FOR EACH ROW
BEGIN
    -- Se stock passou abaixo de 10 e ainda não há alerta ativo
    IF NEW.stock < 10 AND OLD.stock >= 10 THEN
        -- Criar alerta
        INSERT INTO system_alerts 
            (alert_type, entity_type, entity_id, threshold_value, current_value, message, status)
        VALUES 
            ('Stock Baixo', 'produto', NEW.id, 10, NEW.stock, 
             CONCAT('Stock baixo: ', NEW.name, ' - Apenas ', NEW.stock, ' unidades'), 'Ativo');
        
        -- Criar notificação
        INSERT INTO notifications 
            (type, title, message, link, icon, priority)
        VALUES 
            ('Stock', 'Stock Baixo', 
             CONCAT('O produto "', NEW.name, '" tem apenas ', NEW.stock, ' unidades em stock'),
             '/modules/stock.php', '📦', 'Alta');
    END IF;
    
    -- Se produto esgotou
    IF NEW.stock = 0 AND OLD.stock > 0 THEN
        INSERT INTO system_alerts 
            (alert_type, entity_type, entity_id, current_value, message, status)
        VALUES 
            ('Produto Esgotado', 'produto', NEW.id, 0, 
             CONCAT('Produto esgotado: ', NEW.name), 'Ativo');
             
        INSERT INTO notifications 
            (type, title, message, link, icon, priority)
        VALUES 
            ('Stock', 'Produto Esgotado', 
             CONCAT('O produto "', NEW.name, '" esgotou!'),
             '/modules/stock.php', '🚨', 'Urgente');
    END IF;
END$$
DELIMITER ;

-- Trigger para notificar vendas grandes
DELIMITER $$
CREATE TRIGGER `notify_large_sale` 
AFTER INSERT ON `sales` 
FOR EACH ROW
BEGIN
    IF NEW.total > 100 THEN
        INSERT INTO notifications 
            (type, title, message, link, icon, priority)
        VALUES 
            ('Venda', 'Venda Grande', 
             CONCAT('Venda de ', FORMAT(NEW.total, 2), '€ realizada'),
             CONCAT('/modules/vendas.php?id=', NEW.id), '💰', 'Normal');
    END IF;
END$$
DELIMITER ;

-- Trigger para marcar notificação como lida
DELIMITER $$
CREATE TRIGGER `set_read_timestamp` 
BEFORE UPDATE ON `notifications` 
FOR EACH ROW
BEGIN
    IF NEW.is_read = 1 AND OLD.is_read = 0 THEN
        SET NEW.read_at = NOW();
    END IF;
END$$
DELIMITER ;

-- Dados de exemplo para configurações de alertas
INSERT INTO `alert_settings` 
    (`alert_type`, `is_enabled`, `threshold_value`, `notification_method`, `check_frequency_minutes`) 
VALUES
    ('stock_baixo', 1, 10, 'Sistema,Email', 60),
    ('stock_critico', 1, 5, 'Sistema,Email', 30),
    ('produto_esgotado', 1, 0, 'Sistema,Email', 15),
    ('cliente_inativo', 1, 90, 'Sistema', 1440),
    ('aniversario_cliente', 1, NULL, 'Sistema,Email', 1440),
    ('venda_grande', 1, 100, 'Sistema', 5),
    ('ferias_pendentes', 1, NULL, 'Sistema', 1440);

-- FIM DA MIGRATION 007
