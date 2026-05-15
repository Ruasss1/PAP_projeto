<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = db_connect();

try {
    // Create settings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
      `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `key` varchar(100) NOT NULL UNIQUE,
      `value` varchar(500) DEFAULT NULL,
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY `idx_key` (`key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Insert default settings
    $defaults = [
        ['low_stock_global_threshold', '5'],
        ['low_stock_notify_enabled', '0'],
        ['low_stock_notify_email', null],
        ['low_stock_last_email_at', null]
    ];
    
    foreach ($defaults as [$key, $value]) {
        try {
            $pdo->prepare('INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)')
                ->execute([$key, $value]);
        } catch (Exception $e) {
            // Already exists
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Settings table created successfully']);
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
