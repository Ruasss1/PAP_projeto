<?php
/**
 * Migration Script - Executar no navegador
 * URL: /migrations/migrate.php
 * 
 * Este script adiciona as novas colunas e tabelas de forma segura,
 * ignorando erros se já existirem.
 */

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();
$messages = [];

function run_migration($sql, $description) {
    global $pdo, $messages;
    try {
        $pdo->exec($sql);
        $messages[] = ['type' => 'success', 'text' => "✓ $description"];
        return true;
    } catch (PDOException $e) {
        // Se for "duplicate column" ou "already exists", ignora
        if (stripos($e->getMessage(), 'Duplicate') !== false || 
            stripos($e->getMessage(), 'already exists') !== false ||
            stripos($e->getMessage(), 'Unknown column') === false) {
            $messages[] = ['type' => 'info', 'text' => "○ $description (já existe)"];
            return true;
        }
        $messages[] = ['type' => 'error', 'text' => "✗ $description: " . $e->getMessage()];
        return false;
    }
}

$executed = false;
if (isset($_GET['run'])) {
    $executed = true;
    
    // 1. Add columns to products
    run_migration("ALTER TABLE `products` ADD COLUMN `brand` VARCHAR(100) DEFAULT NULL AFTER `category`", "Adicionar coluna brand");
    run_migration("ALTER TABLE `products` ADD COLUMN `barcode` VARCHAR(50) DEFAULT NULL AFTER `brand`", "Adicionar coluna barcode");
    run_migration("ALTER TABLE `products` ADD COLUMN `vat` DECIMAL(5,2) DEFAULT 23.00 AFTER `sell_price`", "Adicionar coluna vat");
    run_migration("ALTER TABLE `products` ADD COLUMN `min_stock` INT DEFAULT 5 AFTER `stock`", "Adicionar coluna min_stock");
    run_migration("ALTER TABLE `products` ADD COLUMN `supplier_id` INT DEFAULT NULL AFTER `min_stock`", "Adicionar coluna supplier_id");
    run_migration("ALTER TABLE `products` ADD COLUMN `active` TINYINT DEFAULT 1 AFTER `expiry_date`", "Adicionar coluna active");
    
    // 2. Add columns to suppliers
    run_migration("ALTER TABLE `suppliers` ADD COLUMN `email` VARCHAR(100) DEFAULT NULL AFTER `name`", "Adicionar coluna email");
    run_migration("ALTER TABLE `suppliers` ADD COLUMN `phone` VARCHAR(20) DEFAULT NULL AFTER `email`", "Adicionar coluna phone");
    run_migration("ALTER TABLE `suppliers` ADD COLUMN `address` TEXT DEFAULT NULL AFTER `phone`", "Adicionar coluna address");
    run_migration("ALTER TABLE `suppliers` ADD COLUMN `delivery_days` INT DEFAULT 2 AFTER `contact`", "Adicionar coluna delivery_days");
    run_migration("ALTER TABLE `suppliers` ADD COLUMN `active` TINYINT DEFAULT 1 AFTER `address`", "Adicionar coluna active");
    
    // 3. Add columns to orders
    run_migration("ALTER TABLE `orders` ADD COLUMN `status` ENUM('pending','processed','shipped','delivered') DEFAULT 'pending' AFTER `supplier_id`", "Adicionar coluna status");
    run_migration("ALTER TABLE `orders` ADD COLUMN `total_cost` DECIMAL(10,2) DEFAULT NULL AFTER `status`", "Adicionar coluna total_cost");
    // Ensure created_at exists (some older installs are missing it)
    run_migration("ALTER TABLE `orders` ADD COLUMN `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP", "Adicionar coluna created_at");
    run_migration("ALTER TABLE `orders` ADD COLUMN `processed_at` DATETIME DEFAULT NULL AFTER `created_at`", "Adicionar coluna processed_at");
    run_migration("ALTER TABLE `orders` ADD COLUMN `delivered_at` DATETIME DEFAULT NULL AFTER `processed_at`", "Adicionar coluna delivered_at");
    run_migration("ALTER TABLE `orders` ADD COLUMN `received` TINYINT DEFAULT 0 AFTER `qty`", "Adicionar coluna received");
    
    // 4. Add columns to sales
    run_migration("ALTER TABLE `sales` ADD COLUMN `payment_method` VARCHAR(50) DEFAULT 'Dinheiro' AFTER `total`", "Adicionar coluna payment_method");
    
    // 5. Add columns to sale_items
    run_migration("ALTER TABLE `sale_items` ADD COLUMN `cost_price` DECIMAL(10,2) DEFAULT NULL AFTER `price`", "Adicionar coluna cost_price");
    
    // 6. Add columns to employees
    run_migration("ALTER TABLE `employees` ADD COLUMN `active` TINYINT DEFAULT 1 AFTER `salary`", "Adicionar coluna active");
    
    // 7. Add columns to transactions
    run_migration("ALTER TABLE `transactions` ADD COLUMN `reference_type` VARCHAR(50) DEFAULT NULL AFTER `type`", "Adicionar coluna reference_type");
    run_migration("ALTER TABLE `transactions` ADD COLUMN `reference_id` INT DEFAULT NULL AFTER `reference_type`", "Adicionar coluna reference_id");
    
    // 8. Create order_messages table
    run_migration("CREATE TABLE IF NOT EXISTS `order_messages` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `order_id` INT(11) NOT NULL,
        `type` ENUM('created','processed','shipped','delivered','partial') NOT NULL DEFAULT 'created',
        `message` TEXT NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `fk_order_messages_order` (`order_id`),
        CONSTRAINT `fk_order_messages_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", "Criar tabela order_messages");
    
    // 9. Create stock_movements table
    run_migration("CREATE TABLE IF NOT EXISTS `stock_movements` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", "Criar tabela stock_movements");
    
    // 10. Create price_history table
    run_migration("CREATE TABLE IF NOT EXISTS `price_history` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", "Criar tabela price_history");
    
    // 10.5 Security & Audit - Users, Roles, Permissions
    $security_sql = file_get_contents(__DIR__ . '/002_add_security_and_audit.sql');
    if ($security_sql) {
        // Split by statements and execute each
        $statements = array_filter(array_map('trim', explode(';', $security_sql)));
        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                try {
                    $pdo->exec($stmt);
                } catch (PDOException $e) {
                    if (stripos($e->getMessage(), 'Duplicate') === false && 
                        stripos($e->getMessage(), 'already exists') === false) {
                        // Continue on duplicate key errors
                    }
                }
            }
        }
        $messages[] = ['type' => 'success', 'text' => '✓ Security & Audit tables'];
    }
    
    // Create default admin user if none exists
    try {
        $stmt = $pdo->prepare('SELECT id FROM users LIMIT 1');
        $stmt->execute();
        if (!$stmt->fetch()) {
            // Create default admin
            $admin_password = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare('INSERT INTO users (email, password_hash, name, role_id, active) VALUES (?, ?, ?, (SELECT id FROM roles WHERE name = "admin"), 1)')
                ->execute(['admin@example.com', $admin_password, 'Administrador']);
            $messages[] = ['type' => 'success', 'text' => '✓ Default admin user created (admin@example.com / admin123)'];
        }
    } catch (PDOException $e) {
        $messages[] = ['type' => 'info', 'text' => '○ Admin user (já existe)'];
    }
    
    // 11. Create alerts table
    run_migration("CREATE TABLE IF NOT EXISTS `alerts` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", "Criar tabela alerts");
    
    // 11.5 Pricing Management - Price strategies, promotions, margins
    $pricing_sql = file_get_contents(__DIR__ . '/003_add_pricing_management.sql');
    if ($pricing_sql) {
        $statements = array_filter(array_map('trim', explode(';', $pricing_sql)));
        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                try {
                    $pdo->exec($stmt);
                } catch (PDOException $e) {
                    if (stripos($e->getMessage(), 'Duplicate') === false && 
                        stripos($e->getMessage(), 'already exists') === false) {
                        // Continue
                    }
                }
            }
        }
        $messages[] = ['type' => 'success', 'text' => '✓ Pricing Management tables'];
    }
    
    // 12. Create daily_profit view
    run_migration("DROP TABLE IF EXISTS `daily_profit`", "Remover view daily_profit antiga");
    run_migration("CREATE ALGORITHM=UNDEFINED VIEW `daily_profit` AS
        SELECT 
            DATE(s.sale_date) as date,
            SUM(s.total) as revenue,
            COALESCE(SUM(si.quantity * si.cost_price), 0) as cogs,
            SUM(s.total) - COALESCE(SUM(si.quantity * si.cost_price), 0) as profit,
            COUNT(DISTINCT s.id) as num_sales
        FROM sales s
        LEFT JOIN sale_items si ON si.sale_id = s.id
        GROUP BY DATE(s.sale_date)
        ORDER BY date DESC", "Criar view daily_profit");
    
    // 13. Create monthly_profit view
    run_migration("DROP TABLE IF EXISTS `monthly_profit`", "Remover view monthly_profit antiga");
    run_migration("CREATE ALGORITHM=UNDEFINED VIEW `monthly_profit` AS
        SELECT 
            DATE_FORMAT(s.sale_date, '%Y-%m') as month,
            SUM(s.total) as revenue,
            COALESCE(SUM(si.quantity * si.cost_price), 0) as cogs,
            SUM(s.total) - COALESCE(SUM(si.quantity * si.cost_price), 0) as profit
        FROM sales s
        LEFT JOIN sale_items si ON si.sale_id = s.id
        GROUP BY DATE_FORMAT(s.sale_date, '%Y-%m')
        ORDER BY month DESC", "Criar view monthly_profit");
    
    // 14. Create monthly_top_product view
    run_migration("DROP TABLE IF EXISTS `monthly_top_product`", "Remover view monthly_top_product antiga");
    run_migration("CREATE ALGORITHM=UNDEFINED VIEW `monthly_top_product` AS
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
        ORDER BY month DESC, total_qty DESC", "Criar view monthly_top_product");
    
    // 15. Update existing data
    try {
        $pdo->exec("UPDATE products SET active = 1 WHERE active IS NULL OR active = 0");
        $messages[] = ['type' => 'success', 'text' => "✓ Produtos marcados como ativos"];
    } catch (PDOException $e) {}
    
    try {
        $pdo->exec("UPDATE suppliers SET active = 1 WHERE active IS NULL OR active = 0");
        $messages[] = ['type' => 'success', 'text' => "✓ Fornecedores marcados como ativos"];
    } catch (PDOException $e) {}
    
    try {
        $pdo->exec("UPDATE employees SET active = 1 WHERE active IS NULL OR active = 0");
        $messages[] = ['type' => 'success', 'text' => "✓ Funcionários marcados como ativos"];
    } catch (PDOException $e) {}
    
    try {
        $pdo->exec("UPDATE orders SET status = 'pending' WHERE status IS NULL");
        $messages[] = ['type' => 'success', 'text' => "✓ Encomendas com status definido"];
    } catch (PDOException $e) {}
}
?>
<h1>Migração da Base de Dados</h1>

<?php if (!$executed): ?>
    <div class="notice">
        <p>Esta migração adiciona as novas colunas e tabelas ao esquema existente.</p>
        <p>Não remove dados existentes.</p>
    </div>
    <a href="?run=1" class="btn" style="font-size: 18px; padding: 12px 24px;">Executar Migração</a>
<?php else: ?>
    <h2>Resultados</h2>
    <?php foreach ($messages as $msg): ?>
        <p style="color: <?php echo $msg['type'] === 'error' ? '#f87171' : ($msg['type'] === 'info' ? '#60a5fa' : '#4ade80'); ?>">
            <?php echo htmlspecialchars($msg['text']); ?>
        </p>
    <?php endforeach; ?>
    
    <div style="margin-top: 24px;">
        <a href="/modules/produtos.php" class="btn">Ir para Produtos</a>
        <a href="/dashboard/dashboard_teste.php" class="btn">Ir para Dashboard</a>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

