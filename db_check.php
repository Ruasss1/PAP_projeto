<?php
// Proteger com token
$token = getenv('SETUP_TOKEN') ?: 'pap_check_2026';
if (($_GET['token'] ?? '') !== $token) {
    http_response_code(403);
    die('Acesso negado. Usa ?token=SEU_TOKEN');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== PAP DB CHECK ===\n\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n\n";

echo "--- Variáveis de Ambiente ---\n";
$vars = ['MYSQLHOST','MYSQLPORT','MYSQLDATABASE','MYSQL_DATABASE','MYSQLUSER','MYSQLPASSWORD','DB_HOST','DB_PORT','DB_NAME','DB_USER'];
foreach ($vars as $v) {
    $val = getenv($v);
    if ($v === 'MYSQLPASSWORD' || $v === 'DB_PASS') {
        echo "$v = " . ($val ? '*** (definida)' : '(vazia)') . "\n";
    } else {
        echo "$v = " . ($val ?: '(não definida)') . "\n";
    }
}

echo "\n--- Tentativa de Conexão ---\n";
$host = getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: '3306';
$name = getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE') ?: getenv('DB_NAME') ?: 'supermercado';
$user = getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '';

echo "DSN: mysql:host=$host;port=$port;dbname=$name\n";
echo "Utilizador: $user\n\n";

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
    );
    echo "✅ CONEXÃO OK!\n\n";

    // Verificar tabelas
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tabelas encontradas (" . count($tables) . "):\n";
    foreach ($tables as $t) echo "  - $t\n";

    // Fix rápido do admin
    if (isset($_GET['fix_admin'])) {
        // Garantir que existe role admin
        $pdo->exec("INSERT IGNORE INTO roles (id, name, display_name) VALUES (1,'admin','Administrador')");
        $role = $pdo->query("SELECT id FROM roles WHERE name='admin' LIMIT 1")->fetch();
        $role_id = $role ? $role['id'] : null;
        $hash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);
        // Atualizar ou inserir
        $existing = $pdo->query("SELECT id FROM users LIMIT 1")->fetch();
        if ($existing) {
            $pdo->prepare("UPDATE users SET email='admin@example.com', password_hash=?, role_id=?, active=1 WHERE id=?")->execute([$hash, $role_id, $existing['id']]);
        } else {
            $pdo->prepare("INSERT INTO users (name, email, password_hash, role_id, active) VALUES ('Admin','admin@example.com',?,?,1)")->execute([$hash, $role_id]);
        }
        echo "✅ ADMIN CORRIGIDO!\n";
    }

    // Verificar user admin
    try {
        $u = $pdo->query("SELECT id, email, role_id, active, LEFT(password_hash,10) as ph_preview, LENGTH(password_hash) as ph_len FROM users LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        echo "\nUtilizadores (" . count($u) . "):\n";
        foreach ($u as $row) echo "  " . json_encode($row) . "\n";
        
        // Testar password_verify
        $admin = $pdo->query("SELECT password_hash FROM users WHERE email='admin@example.com' LIMIT 1")->fetch();
        if ($admin) {
            $ok = password_verify('admin123', $admin['password_hash']);
            echo "\npassword_verify('admin123'): " . ($ok ? '✅ CORRETO' : '❌ ERRADO') . "\n";
        } else {
            echo "\nadmin@example.com: NÃO EXISTE\n";
        }
    } catch (Exception $e) {
        echo "\nErro ao verificar users: " . $e->getMessage() . "\n";
    }

} catch (PDOException $e) {
    echo "❌ ERRO DE CONEXÃO:\n" . $e->getMessage() . "\n\n";
    echo "Soluções:\n";
    echo "  1. Verifica se adicionaste o serviço MySQL no Railway\n";
    echo "  2. As variáveis MYSQLHOST, MYSQLUSER, etc. são preenchidas automaticamente pelo Railway quando adicionas o MySQL\n";
    echo "  3. Vai ao Railway → o teu serviço → Variables e confirma que existem\n";
}
