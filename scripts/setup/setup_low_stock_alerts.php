<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = db_connect();

try {
    // Add NIF column to sales if missing
    $stmt = $pdo->query("SHOW COLUMNS FROM sales LIKE 'nif'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE `sales` ADD COLUMN `nif` VARCHAR(20) DEFAULT NULL AFTER `payment_method`");
        echo "✓ NIF column added to sales table\n";
    } else {
        echo "✓ NIF column already exists in sales table\n";
    }
    
    // Create settings table
    $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
    if (!$stmt->fetch()) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
          `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `key` varchar(100) NOT NULL UNIQUE,
          `value` varchar(500) DEFAULT NULL,
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          KEY `idx_key` (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Insert default settings - No SMTP (Notifications on site only)
        $defaults = [
            ['low_stock_global_threshold', '5'],
            ['low_stock_notify_enabled', '1'],
            ['low_stock_notify_email', 'vascoruas4@gmail.com'],
            ['low_stock_last_email_at', null],
            ['recovery_code', 'VGE9FK8QWUARSSMLEBXSG2C2']
        ];
        
        foreach ($defaults as [$key, $value]) {
            $pdo->prepare('INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)')
                ->execute([$key, $value]);
        }
        
        echo "✓ Settings table created\n";
    } else {
        echo "✓ Settings table already exists\n";
        
        // Configure SendGrid SMTP
        try {
            $sendgrid_settings = [
                ['smtp_host', 'smtp.sendgrid.net'],
                ['smtp_port', '587'],
                ['smtp_user', 'apikey'],
                ['smtp_pass', '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'],
                ['email_from', 'sheltzx7@gmail.com'],
                ['email_from_name', 'supermercado'],
                ['recovery_code', 'VGE9FK8QWUARSSMLEBXSG2C2']
            ];
            
            $stmt = $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) 
                                   ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
            
            foreach ($sendgrid_settings as [$key, $value]) {
                $stmt->execute([$key, $value]);
            }
            
            echo "✓ SendGrid SMTP configured\n";
            echo "✓ Recovery code stored\n";
        } catch (Exception $e) {
            echo "✗ Error configuring SendGrid: " . $e->getMessage() . "\n";
        }
    }
    
    // Create email history table
    $stmt = $pdo->query("SHOW TABLES LIKE 'email_history'");
    if (!$stmt->fetch()) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `email_history` (
          `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `recipient` varchar(255) NOT NULL,
          `subject` varchar(255) NOT NULL,
          `product_count` int DEFAULT 0,
          `status` enum('sent', 'failed') DEFAULT 'sent',
          `error_message` varchar(500) DEFAULT NULL,
          `sent_at` datetime DEFAULT CURRENT_TIMESTAMP,
          KEY `idx_recipient` (`recipient`),
          KEY `idx_sent_at` (`sent_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        echo "✓ Email history table created\n";
    } else {
        echo "✓ Email history table already exists\n";
    }
    
    echo "✓ Setup complete!\n";
    echo "✓ Email provider: SendGrid SMTP\n";
    echo "✓ SMTP Host: smtp.sendgrid.net:587\n";
    echo "✓ Recovery code: VGE9FK8QWUARSSMLEBXSG2C2\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
