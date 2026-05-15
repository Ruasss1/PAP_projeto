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

require_once __DIR__ . '/config/database.php';

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
$migrations_dir = __DIR__ . '/migrations';
$migration_files = [
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

foreach ($migration_files as $file) {
    $path = $migrations_dir . '/' . $file;
    if (!file_exists($path)) {
        echo '<div class="step skip">⏭ <span>Ignorado: <code>' . $file . '</code> (não encontrado)</span></div>';
        continue;
    }
    $sql = file_get_contents($path);
    // Divide em statements individuais
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $file_errors = 0;
    foreach ($statements as $stmt) {
        if (empty($stmt) || str_starts_with(ltrim($stmt), '--')) continue;
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            // Ignorar erros de "já existe" (tabela, coluna, índice)
            $msg = $e->getMessage();
            if (str_contains($msg, 'already exists') || str_contains($msg, 'Duplicate column') || str_contains($msg, 'Duplicate key')) {
                // OK — já estava criado
            } else {
                $file_errors++;
                echo '<div class="step warn">⚠ <span><code>' . $file . '</code>: ' . htmlspecialchars(substr($msg, 0, 120)) . '</span></div>';
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

// Criar utilizador admin se não existir
try {
    $exists = $pdo->query("SELECT COUNT(*) FROM users WHERE username='admin'")->fetchColumn();
    if (!$exists) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name, username, email, password, role, active) VALUES ('Administrador','admin','admin@pap.local',?,'admin',1)")->execute([$hash]);
        echo '<div class="step ok">✓ <span>Utilizador <code>admin</code> criado (senha: <code>admin123</code>)</span></div>';
    } else {
        echo '<div class="step skip">ℹ <span>Utilizador <code>admin</code> já existe</span></div>';
    }
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
