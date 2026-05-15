<?php
/**
 * MIGRAÇÃO: Sistema Multi-Loja
 * Adiciona suporte para múltiplos supermercados
 */
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();

echo "=== MIGRAÇÃO MULTI-LOJA ===\n\n";

try {
    $pdo->beginTransaction();
    
    // 1. Criar tabela de lojas
    echo "1. Criando tabela 'stores'...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS stores (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(10) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            address VARCHAR(255),
            city VARCHAR(100),
            postal_code VARCHAR(20),
            phone VARCHAR(20),
            email VARCHAR(100),
            manager_name VARCHAR(100),
            is_active TINYINT(1) DEFAULT 1,
            is_default TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "   ✓ Tabela 'stores' criada\n";
    
    // 2. Adicionar store_id às tabelas principais
    echo "\n2. Adicionando store_id às tabelas...\n";
    
    // Verificar se já existe a coluna store_id em sales
    $cols = $pdo->query("SHOW COLUMNS FROM sales LIKE 'store_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE sales ADD COLUMN store_id INT DEFAULT 1 AFTER id");
        $pdo->exec("ALTER TABLE sales ADD INDEX idx_store_id (store_id)");
        echo "   ✓ store_id adicionado a 'sales'\n";
    } else {
        echo "   - store_id já existe em 'sales'\n";
    }
    
    // products - cada loja pode ter stock diferente
    $cols = $pdo->query("SHOW COLUMNS FROM products LIKE 'store_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN store_id INT DEFAULT 1 AFTER id");
        $pdo->exec("ALTER TABLE products ADD INDEX idx_products_store (store_id)");
        echo "   ✓ store_id adicionado a 'products'\n";
    } else {
        echo "   - store_id já existe em 'products'\n";
    }
    
    // receipts
    $cols = $pdo->query("SHOW COLUMNS FROM receipts LIKE 'store_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE receipts ADD COLUMN store_id INT DEFAULT 1 AFTER id");
        echo "   ✓ store_id adicionado a 'receipts'\n";
    } else {
        echo "   - store_id já existe em 'receipts'\n";
    }
    
    // employees
    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'store_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN store_id INT DEFAULT 1 AFTER id");
        echo "   ✓ store_id adicionado a 'employees'\n";
    } else {
        echo "   - store_id já existe em 'employees'\n";
    }
    
    // promotions
    $cols = $pdo->query("SHOW COLUMNS FROM promotions LIKE 'store_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE promotions ADD COLUMN store_id INT DEFAULT NULL AFTER id");
        echo "   ✓ store_id adicionado a 'promotions' (NULL = todas as lojas)\n";
    } else {
        echo "   - store_id já existe em 'promotions'\n";
    }
    
    // 3. Inserir loja padrão (atual)
    echo "\n3. Criando lojas...\n";
    
    $check = $pdo->query("SELECT COUNT(*) FROM stores")->fetchColumn();
    if ($check == 0) {
        $pdo->exec("
            INSERT INTO stores (code, name, address, city, postal_code, phone, email, manager_name, is_default) VALUES
            ('LOJA001', 'Supermercado Central', 'Rua Principal, 123', 'Lisboa', '1000-001', '21 123 4567', 'central@supermercado.pt', 'João Silva', 1),
            ('LOJA002', 'Supermercado Norte', 'Avenida do Comércio, 456', 'Porto', '4000-001', '22 234 5678', 'norte@supermercado.pt', 'Maria Santos', 0),
            ('LOJA003', 'Supermercado Sul', 'Praça da República, 789', 'Faro', '8000-001', '28 345 6789', 'sul@supermercado.pt', 'António Costa', 0)
        ");
        echo "   ✓ 3 lojas criadas (Central, Norte, Sul)\n";
    } else {
        echo "   - Lojas já existem\n";
    }
    
    // 4. Atualizar dados existentes para loja 1
    echo "\n4. Associando dados existentes à loja Central (ID=1)...\n";
    $pdo->exec("UPDATE sales SET store_id = 1 WHERE store_id IS NULL OR store_id = 0");
    $pdo->exec("UPDATE products SET store_id = 1 WHERE store_id IS NULL OR store_id = 0");
    $pdo->exec("UPDATE receipts SET store_id = 1 WHERE store_id IS NULL OR store_id = 0");
    $pdo->exec("UPDATE employees SET store_id = 1 WHERE store_id IS NULL OR store_id = 0");
    echo "   ✓ Dados existentes associados à loja Central\n";
    
    $pdo->commit();
    echo "\n✅ MIGRAÇÃO CONCLUÍDA COM SUCESSO!\n";
    
    // Mostrar resumo
    echo "\n=== LOJAS DISPONÍVEIS ===\n";
    $stores = $pdo->query("SELECT * FROM stores ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($stores as $s) {
        $default = $s['is_default'] ? ' [PADRÃO]' : '';
        echo "  [{$s['code']}] {$s['name']} - {$s['city']}{$default}\n";
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
}
