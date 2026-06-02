<?php
// setup_auth_simple.php
// Script simplificado para criar tabelas sem constraints

echo "🔧 Criando tabelas de autenticação (versão simplificada)...\n\n";

require_once __DIR__ . '/../../config/database.php';

$pdo = db_connect();

// SQL para criar tabelas - SEM CONSTRAINTS (mais compatível)
$sql = [
    // 1. Tabela ROLES
    "DROP TABLE IF EXISTS `permissions`;
     DROP TABLE IF EXISTS `audit_log`;
     DROP TABLE IF EXISTS `sessions`;
     DROP TABLE IF EXISTS `users`;
     DROP TABLE IF EXISTS `roles`;",
    
    // 2. Criar ROLES
    "CREATE TABLE `roles` (
      `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `name` varchar(50) NOT NULL UNIQUE,
      `description` varchar(255),
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    // 3. Criar USERS
    "CREATE TABLE `users` (
      `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `name` varchar(100) NOT NULL,
      `email` varchar(100) NOT NULL UNIQUE,
      `password_hash` varchar(255) NOT NULL,
      `role_id` int(11) NOT NULL DEFAULT 3,
      `active` tinyint(1) DEFAULT 1,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      `last_login_at` timestamp NULL DEFAULT NULL,
      KEY `role_id` (`role_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    // 4. Criar SESSIONS
    "CREATE TABLE `sessions` (
      `id` varchar(64) NOT NULL PRIMARY KEY,
      `user_id` int(11) NOT NULL,
      `ip_address` varchar(45),
      `user_agent` text,
      `last_activity` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      `expires_at` timestamp NOT NULL,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      KEY `user_id` (`user_id`),
      KEY `expires_at` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    // 5. Criar AUDIT_LOG
    "CREATE TABLE `audit_log` (
      `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `user_id` int(11),
      `action` varchar(100) NOT NULL,
      `resource` varchar(100),
      `resource_id` int(11),
      `status` varchar(20) NOT NULL,
      `ip_address` varchar(45),
      `user_agent` text,
      `changes` json,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      KEY `user_id` (`user_id`),
      KEY `action` (`action`),
      KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    // 6. Criar PERMISSIONS
    "CREATE TABLE `permissions` (
      `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `role_id` int(11) NOT NULL,
      `module` varchar(50) NOT NULL,
      `action` varchar(50) NOT NULL,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY `unique_permission` (`role_id`, `module`, `action`),
      KEY `role_id` (`role_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"
];

// Executar criação das tabelas
try {
    echo "📋 Preparando base de dados...\n";
    // Drop anterior
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    $pdo->exec("DROP TABLE IF EXISTS `permissions`");
    $pdo->exec("DROP TABLE IF EXISTS `audit_log`");
    $pdo->exec("DROP TABLE IF EXISTS `sessions`");
    $pdo->exec("DROP TABLE IF EXISTS `users`");
    $pdo->exec("DROP TABLE IF EXISTS `roles`");
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    
    echo "\n📌 Criando tabelas...\n";
    $table_names = ['ROLES', 'USERS', 'SESSIONS', 'AUDIT_LOG', 'PERMISSIONS'];
    $start_idx = 1;
    
    foreach ($sql as $i => $statement) {
        if ($i == 0) continue; // Skip drop statement
        $pdo->exec($statement);
        echo "   ✅ " . $table_names[$i-1] . "\n";
    }
    
    echo "\n✅ Todas as tabelas criadas com sucesso!\n\n";
} catch (PDOException $e) {
    echo "❌ Erro ao criar tabelas: " . $e->getMessage() . "\n";
    exit(1);
}

// Inserir roles
echo "🏷️  Inserindo roles...\n";
try {
    $roles = [
        ['name' => 'Admin', 'description' => 'Administrador do sistema'],
        ['name' => 'Gerente', 'description' => 'Gerente da loja'],
        ['name' => 'Caixa', 'description' => 'Operador de caixa'],
        ['name' => 'Stock', 'description' => 'Responsável de stock'],
        ['name' => 'RH', 'description' => 'Recursos Humanos']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
    foreach ($roles as $role) {
        $stmt->execute([$role['name'], $role['description']]);
        echo "   ✅ " . $role['name'] . "\n";
    }
    echo "\n";
} catch (PDOException $e) {
    echo "❌ Erro ao inserir roles: " . $e->getMessage() . "\n";
}

// Inserir utilizadores de demonstração
echo "👥 Criando utilizadores de demonstração...\n";
try {
    $users = [
        ['name' => 'Administrador', 'email' => 'admin@example.com', 'password' => 'admin123', 'role' => 'Admin'],
        ['name' => 'Gerente da Loja', 'email' => 'gerente@example.com', 'password' => 'gerente123', 'role' => 'Gerente'],
        ['name' => 'Caixa', 'email' => 'caixa@example.com', 'password' => 'caixa123', 'role' => 'Caixa'],
        ['name' => 'Responsável de Stock', 'email' => 'stock@example.com', 'password' => 'stock123', 'role' => 'Stock'],
        ['name' => 'RH', 'email' => 'rh@example.com', 'password' => 'rh123', 'role' => 'RH']
    ];
    
    foreach ($users as $user) {
        // Obter role_id
        $role_stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
        $role_stmt->execute([$user['role']]);
        $role = $role_stmt->fetch();
        $role_id = $role ? $role['id'] : 3;
        
        // Criar hash da senha
        $hash = password_hash($user['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        
        // Inserir utilizador
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password_hash, role_id, active)
            VALUES (?, ?, ?, ?, 1)
        ");
        
        $stmt->execute([
            $user['name'],
            $user['email'],
            $hash,
            $role_id
        ]);
        
        echo "   ✅ " . $user['email'] . " (Senha: " . $user['password'] . ")\n";
    }
    echo "\n";
} catch (PDOException $e) {
    echo "❌ Erro ao criar utilizadores: " . $e->getMessage() . "\n";
}

// Inserir permissões
echo "🔐 Configurando permissões...\n";
try {
    $permissions = [
        // Gerente
        ['role' => 'Gerente', 'module' => 'produtos', 'action' => 'view'],
        ['role' => 'Gerente', 'module' => 'produtos', 'action' => 'edit'],
        ['role' => 'Gerente', 'module' => 'precos', 'action' => 'view'],
        ['role' => 'Gerente', 'module' => 'precos', 'action' => 'edit'],
        ['role' => 'Gerente', 'module' => 'vendas', 'action' => 'view'],
        ['role' => 'Gerente', 'module' => 'stock', 'action' => 'view'],
        
        // Caixa
        ['role' => 'Caixa', 'module' => 'vendas', 'action' => 'view'],
        ['role' => 'Caixa', 'module' => 'vendas', 'action' => 'edit'],
        ['role' => 'Caixa', 'module' => 'produtos', 'action' => 'view'],
        
        // Stock
        ['role' => 'Stock', 'module' => 'stock', 'action' => 'view'],
        ['role' => 'Stock', 'module' => 'stock', 'action' => 'edit'],
        ['role' => 'Stock', 'module' => 'produtos', 'action' => 'view'],
        ['role' => 'Stock', 'module' => 'fornecedores', 'action' => 'view'],
        
        // RH
        ['role' => 'RH', 'module' => 'rh', 'action' => 'view'],
        ['role' => 'RH', 'module' => 'rh', 'action' => 'edit']
    ];
    
    foreach ($permissions as $perm) {
        // Obter role_id
        $role_stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
        $role_stmt->execute([$perm['role']]);
        $role = $role_stmt->fetch();
        
        if ($role) {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO permissions (role_id, module, action)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$role['id'], $perm['module'], $perm['action']]);
        }
    }
    echo "   ✅ Permissões configuradas para cada role\n\n";
} catch (PDOException $e) {
    echo "❌ Erro ao configurar permissões: " . $e->getMessage() . "\n";
}

echo "════════════════════════════════════════════════\n";
echo "✅ SETUP CONCLUÍDO COM SUCESSO!\n";
echo "════════════════════════════════════════════════\n\n";

echo "📋 Utilizadores criados:\n";
echo "   Admin:    admin@example.com / admin123\n";
echo "   Gerente:  gerente@example.com / gerente123\n";
echo "   Caixa:    caixa@example.com / caixa123\n";
echo "   Stock:    stock@example.com / stock123\n";
echo "   RH:       rh@example.com / rh123\n\n";

echo "🚀 Próximo passo:\n";
echo "   1. Iniciar servidor: php -S localhost:8000\n";
echo "   2. Aceder a: http://localhost:8000/login.php\n";
echo "   3. Fazer login com as credenciais acima\n\n";
?>
