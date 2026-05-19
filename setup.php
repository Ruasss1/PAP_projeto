<?php
/**
 * SETUP INICIAL — corre todas as migrações na ordem correta
 * Acede a: /setup.php  (só funciona se a BD ainda não estiver configurada)
 * APAGA OU PROTEGE ESTE FICHEIRO DEPOIS DE CORRER!
 */

// Proteção básica por token (define na variável de ambiente SETUP_TOKEN)
$setup_token = getenv('SETUP_TOKEN') ?: 'pap-setup-2026';
if (($_GET['token'] ?? '') !== $setup_token) {
    http_response_code(403);
    die('<h2>Acesso negado.</h2><p>Usa: /setup.php?token=' . htmlspecialchars($setup_token) . '</p>');
}

// Conexão direta para Railway (suporta MYSQL_DATABASE com underscore)
if (!defined('DB_NAME')) {
    define('DB_HOST', getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: '127.0.0.1');
    define('DB_PORT', getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: '3306');
    define('DB_NAME', getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE') ?: getenv('DB_NAME') ?: 'supermercado');
    define('DB_USER', getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '');
}
if (session_status() === PHP_SESSION_NONE) session_start();
try {
    $pdo = new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die('<h2>Erro de conexão à BD</h2><pre>' . htmlspecialchars($e->getMessage()) . '</pre>');
}

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Setup PAP Supermercado</title>
<style>
body{font-family:-apple-system,sans-serif;background:#0a0a0a;color:#e4e4e7;padding:40px;max-width:800px;margin:0 auto}
h1{font-size:24px;margin-bottom:8px}p{color:#888;margin-bottom:32px}
.step{padding:14px 18px;border-radius:8px;margin-bottom:10px;display:flex;align-items:center;gap:12px;font-size:14px}
.ok{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#4ade80}
.err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171}
.skip{background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.2);color:#a5b4fc}
.warn{background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);color:#fbbf24}
code{background:#1a1a1a;padding:2px 8px;border-radius:4px;font-size:12px}
.done{margin-top:32px;padding:24px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.25);border-radius:12px}
</style>
</head>
<body>
<h1>🚀 Setup PAP Supermercado</h1>
<p>A correr migrações da base de dados...</p>

<?php
// Ficheiros de migração por ordem
$migrations_dir = __DIR__ . '/migrations';
$migration_files = [
    // Schema base exportado da BD local (tem todas as tabelas base)
    '000_base_schema.sql',
    // Migrações incrementais
    '001_supermercado_migration.sql',
    '002_add_security_and_audit.sql',
    '002_pdv_tables.sql',
    '003_add_pricing_management.sql',
    '004_low_stock_settings.sql',
    '005_cash_operations.sql',
    '005_pdv_system.sql',
    '005_rh_management.sql',
    '006_customers_loyalty.sql',
    '007_notifications_alerts.sql',
    '008_new_features.sql',
];

$success = 0; $errors = 0;

// Desativar FK checks para permitir criação de tabelas em qualquer ordem
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec('SET sql_mode=""');

foreach ($migration_files as $file) {
    $path = $migrations_dir . '/' . $file;
    if (!file_exists($path)) {
        echo '<div class="step skip">⏭ <span>Ignorado: <code>' . $file . '</code> (não encontrado)</span></div>';
        continue;
    }
    $sql = file_get_contents($path);
    // Converter "ADD COLUMN IF NOT EXISTS" para "ADD COLUMN" (compatibilidade MySQL <8.0.3)
    $sql = preg_replace('/\bADD COLUMN IF NOT EXISTS\b/i', 'ADD COLUMN', $sql);
    $sql = preg_replace('/\bADD INDEX IF NOT EXISTS\b/i', 'ADD INDEX', $sql);
    $sql = preg_replace('/\bCREATE INDEX IF NOT EXISTS\b/i', 'CREATE INDEX', $sql);
    // Processar DELIMITER $$ (triggers/procedures) — PDO não suporta DELIMITER nativo
    $statements = [];
    $delimiter = ';';
    $current = '';
    foreach (explode("\n", $sql) as $line) {
        $trimmed = trim($line);
        // Detectar mudança de DELIMITER
        if (preg_match('/^DELIMITER\s+(\S+)/i', $trimmed, $m)) {
            $delimiter = $m[1];
            continue;
        }
        $current .= $line . "\n";
        // Verificar se a linha termina com o delimiter atual
        if ($delimiter === ';') {
            if (str_ends_with(rtrim($trimmed), ';')) {
                $stmt = trim(substr(rtrim($current), 0, -1));
                if (!empty($stmt)) $statements[] = $stmt;
                $current = '';
            }
        } else {
            // Delimiter alternativo (ex: $$)
            if (str_ends_with(rtrim($trimmed), $delimiter)) {
                $stmt = trim(preg_replace('/' . preg_quote($delimiter, '/') . '$/', '', rtrim($current)));
                if (!empty($stmt)) $statements[] = $stmt;
                $current = '';
            }
        }
    }
    // Qualquer resto
    if (trim($current) !== '') $statements[] = trim($current);

    $file_errors = 0;
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt) || str_starts_with($stmt, '--') || str_starts_with($stmt, '/*')) continue;
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            $code = (int)$e->getCode();
            // Ignorar erros de "já existe" ou "não existe para alterar"
            if (str_contains($msg, 'already exists') || 
                str_contains($msg, 'Duplicate column') || 
                str_contains($msg, 'Duplicate key') || 
                str_contains($msg, 'Duplicate entry') ||
                str_contains($msg, 'Duplicate index') ||
                str_contains($msg, "Can't DROP") ||
                str_contains($msg, "Key column") || // índice em coluna que não existe nesta versão
                $code === 1060 || // Duplicate column
                $code === 1061 || // Duplicate key name
                $code === 1050    // Table already exists
            ) {
                // OK — já estava criado
            } else {
                $file_errors++;
                echo '<div class="step warn">⚠ <span><code>' . $file . '</code>: ' . htmlspecialchars(substr($msg, 0, 150)) . '</span></div>';
            }
        }
    }
    if ($file_errors === 0) {
        echo '<div class="step ok">✓ <span><code>' . $file . '</code></span></div>';
        $success++;
    } else {
        $errors++;
    }
}

// Reativar FK checks
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

// Criar utilizador admin se não existir
try {
    $exists = $pdo->query("SELECT COUNT(*) FROM users WHERE email='admin@example.com'")->fetchColumn();
    // Usar bcrypt cost 12 igual ao auth.php
    $hash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);
    // Obter role_id do admin
    $role = $pdo->query("SELECT id FROM roles WHERE name='admin' LIMIT 1")->fetch();
    $role_id = $role ? $role['id'] : 1;
    if (!$exists) {
        $pdo->prepare("INSERT INTO users (name, email, password_hash, role_id, role, active) VALUES ('Administrador','admin@example.com',?,?,'admin',1)")->execute([$hash, $role_id]);
        echo '<div class="step ok">✓ <span>Utilizador <code>admin@example.com</code> criado (senha: <code>admin123</code>)</span></div>';
    } else {
        $pdo->prepare("UPDATE users SET password_hash=?, role_id=?, active=1 WHERE email='admin@example.com'")->execute([$hash, $role_id]);
        echo '<div class="step skip">ℹ <span>Utilizador <code>admin@example.com</code> atualizado (senha: <code>admin123</code>)</span></div>';
    }
    // Migrar admin@pap.local se existir
    $pdo->exec("UPDATE users SET email='admin@example.com' WHERE email='admin@pap.local'");
} catch (Exception $e) {
    echo '<div class="step warn">⚠ <span>Não foi possível verificar utilizador admin: ' . htmlspecialchars($e->getMessage()) . '</span></div>';
}
?>

<div class="done">
    <strong>✅ Setup concluído!</strong><br><br>
    Migrações processadas: <strong><?= $success ?></strong> &nbsp;|&nbsp; Erros: <strong style="color:<?= $errors > 0 ? '#f87171' : '#4ade80' ?>"><?= $errors ?></strong><br><br>
    <a href="/" style="color:#60a5fa">→ Ir para a aplicação</a>
    &nbsp;&nbsp;
    <span style="color:#888;font-size:13px">⚠ Por segurança, remove a variável <code>SETUP_TOKEN</code> depois do setup.</span>
</div>
</body>
</html>
