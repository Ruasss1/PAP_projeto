<?php
// setup_auth_tables.php
// Script para criar tabelas de autenticação na BD

echo "🔧 Criando tabelas de autenticação...\n\n";

require_once __DIR__ . '/../../config/database.php';

$pdo = db_connect();

// SQL para criar tabelas - ORDEM CORRETA (roles first!)
$sql = [
    // 1. Tabela ROLES (sem dependências)
    "CREATE TABLE IF NOT EXISTS `roles` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(50) NOT NULL UNIQUE,
      `description` varchar(255),
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    // 2. Tabela USERS (depende de roles)
    "CREATE TABLE IF NOT EXISTS `users` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    // 3. Tabela SESSIONS (depende de users)
    "CREATE TABLE IF NOT EXISTS `sessions` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    // 4. Tabela AUDIT_LOG (depende de users)
    "CREATE TABLE IF NOT EXISTS `audit_log` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    // 5. Tabela PERMISSIONS (depende de roles)
    "CREATE TABLE IF NOT EXISTS `permissions` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `role_id` int(11) NOT NULL,
      `module` varchar(50) NOT NULL,
      `action` varchar(50) NOT NULL,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_permission` (`role_id`, `module`, `action`),
      KEY `role_id` (`role_id`),
      CONSTRAINT `fk_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"
];

// Executar criação das tabelas
try {
    foreach ($sql as $i => $statement) {
        $pdo->exec($statement);
        $table_names = ['ROLES', 'USERS', 'SESSIONS', 'AUDIT_LOG', 'PERMISSIONS'];
        echo "✅ " . $table_names[$i] . " criada\n";
    }
    echo "\n✅ Todas as tabelas criadas com sucesso!\n\n";
} catch (PDOException $e) {
    echo "❌ Erro ao criar tabelas: " . $e->getMessage() . "\n";
    exit(1);
}

// Inserir roles
echo "📌 Inserindo roles...\n";
try {
    $roles = [
        ['name' => 'Admin', 'description' => 'Administrador do sistema'],
        ['name' => 'Gerente', 'description' => 'Gerente da loja'],
        ['name' => 'Caixa', 'description' => 'Operador de caixa'],
        ['name' => 'Stock', 'description' => 'Responsável de stock'],
        ['name' => 'RH', 'description' => 'Recursos Humanos']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO roles (name, description) VALUES (?, ?)");
    foreach ($roles as $role) {
        $stmt->execute([$role['name'], $role['description']]);
    }
    echo "✅ Roles inseridos\n\n";
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
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO users (name, email, password_hash, role_id, active)
        SELECT ?, ?, ?, r.id, 1
        FROM roles r
        WHERE r.name = ?
    ");
    
    foreach ($users as $user) {
        $hash = password_hash($user['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $result = $stmt->execute([
            $user['name'],
            $user['email'],
            $hash,
            $user['role']
        ]);
        if ($result) {
            echo "   ✅ " . $user['email'] . " (Senha: " . $user['password'] . ")\n";
        }
    }
    echo "\n✅ Utilizadores criados\n\n";
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
        ['role' => 'Caixa', 'module' => 'vendas', 'view'],
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
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO permissions (role_id, module, action)
        SELECT r.id, ?, ?
        FROM roles r
        WHERE r.name = ?
    ");
    
    foreach ($permissions as $perm) {
        $stmt->execute([$perm['module'], $perm['action'], $perm['role']]);
    }
    echo "✅ Permissões configuradas\n\n";
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

echo "🚀 Aceda a: http://localhost:8000/login.php\n";
?>
