<?php
// test_login.php - Teste do Sistema de Login

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔍 Testando Sistema de Autenticação...\n\n";

// 1. Verificar ficheiros necessários
echo "1️⃣ Verificando ficheiros...\n";
$files = [
    'includes/auth.php' => 'Sistema de Autenticação',
    'includes/auth_middleware.php' => 'Middleware',
    'login.php' => 'Página de Login',
    'index.php' => 'Dashboard',
    'config/database.php' => 'Configuração de BD'
];

foreach ($files as $file => $desc) {
    if (file_exists($file)) {
        echo "   ✅ $file ($desc)\n";
    } else {
        echo "   ❌ $file ($desc) - NÃO ENCONTRADO\n";
    }
}

// 2. Testar conexão à BD
echo "\n2️⃣ Testando Conexão à BD...\n";
try {
    require_once 'config/database.php';
    $pdo = db_connect();
    echo "   ✅ Conexão à BD bem-sucedida\n";
    
    // Verificar tabela de utilizadores
    $result = $pdo->query("SELECT COUNT(*) as total FROM users")->fetch();
    echo "   ✅ Utilizadores na BD: " . $result['total'] . "\n";
    
    // Verificar roles
    $roles = $pdo->query("SELECT COUNT(*) as total FROM roles")->fetch();
    echo "   ✅ Roles na BD: " . $roles['total'] . "\n";
} catch (Exception $e) {
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

// 3. Testar classe AuthManager
echo "\n3️⃣ Testando AuthManager...\n";
try {
    require_once 'includes/auth.php';
    echo "   ✅ AuthManager carregado\n";
    
    // Verificar método is_authenticated
    if (method_exists($auth, 'is_authenticated')) {
        echo "   ✅ Método is_authenticated() existe\n";
    } else {
        echo "   ❌ Método is_authenticated() não existe\n";
    }
    
    // Verificar método login
    if (method_exists($auth, 'login')) {
        echo "   ✅ Método login() existe\n";
    } else {
        echo "   ❌ Método login() não existe\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

// 4. Testar sintaxe PHP
echo "\n4️⃣ Testando Sintaxe PHP...\n";
$files_to_check = [
    'login.php',
    'index.php',
    'includes/auth.php',
    'includes/auth_middleware.php',
    'modules/produtos.php'
];

foreach ($files_to_check as $file) {
    $result = shell_exec("php -l $file 2>&1");
    if (strpos($result, 'No syntax errors') !== false) {
        echo "   ✅ $file - Sintaxe OK\n";
    } else {
        echo "   ⚠️  $file - " . trim($result) . "\n";
    }
}

echo "\n5️⃣ Resumo Final:\n";
echo "   Sistema pronto para testar em: http://localhost/login.php\n";
echo "   Utilizadores de demonstração:\n";
echo "   - admin@example.com / admin123\n";
echo "   - gerente@example.com / gerente123\n";
echo "   - caixa@example.com / caixa123\n";
echo "   - stock@example.com / stock123\n";
echo "   - rh@example.com / rh123\n\n";
echo "✅ Teste concluído!\n";
?>
