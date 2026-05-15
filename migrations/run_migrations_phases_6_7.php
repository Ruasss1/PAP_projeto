<?php
/**
 * Script para executar migrações PHASE 6 e PHASE 7
 * migrations/run_migrations_phases_6_7.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();

echo "\n========================================\n";
echo "EXECUTAR MIGRAÇÕES - PHASES 6 & 7\n";
echo "========================================\n\n";

$migrations = [
    '006_customers_loyalty.sql' => 'PHASE 6: Sistema de Clientes e Fidelização',
    '007_notifications_alerts.sql' => 'PHASE 7: Notificações e Alertas'
];

$total_success = 0;
$total_errors = 0;

foreach ($migrations as $file => $description) {
    echo "📁 $description\n";
    echo "   Arquivo: $file\n";
    
    $filepath = __DIR__ . '/' . $file;
    
    if (!file_exists($filepath)) {
        echo "   ❌ ERRO: Arquivo não encontrado\n\n";
        $total_errors++;
        continue;
    }
    
    $sql = file_get_contents($filepath);
    
    // Remover comentários SQL
    $sql = preg_replace('/--.*$/m', '', $sql);
    
    // Dividir em statements individuais
    $statements = preg_split('/;(\s*\n|$)/', $sql, -1, PREG_SPLIT_NO_EMPTY);
    
    $success = 0;
    $errors = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        
        if (empty($statement)) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $success++;
        } catch (PDOException $e) {
            // Ignorar erros de "já existe" ou "duplicate"
            if (strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate') !== false ||
                strpos($e->getMessage(), 'Can\'t DROP') !== false) {
                // Aviso, mas não erro crítico
                continue;
            }
            
            echo "   ⚠️  Erro: " . $e->getMessage() . "\n";
            echo "   Statement: " . substr($statement, 0, 100) . "...\n";
            $errors++;
        }
    }
    
    if ($errors == 0) {
        echo "   ✅ Sucesso: $success statements executados\n";
        $total_success++;
    } else {
        echo "   ⚠️  Parcial: $success sucesso, $errors erros\n";
        $total_errors++;
    }
    
    echo "\n";
}

echo "========================================\n";
echo "RESUMO FINAL\n";
echo "========================================\n";
echo "✅ Migrações com sucesso: $total_success\n";
echo "❌ Migrações com erros: $total_errors\n";
echo "\n";

if ($total_errors == 0) {
    echo "🎉 Todas as migrações executadas com sucesso!\n";
    echo "\n";
    echo "📊 Tabelas criadas:\n";
    echo "   PHASE 6:\n";
    echo "   - customers (clientes)\n";
    echo "   - loyalty_points_history (histórico de pontos)\n";
    echo "   - loyalty_rewards (recompensas)\n";
    echo "   - loyalty_redemptions (resgates)\n";
    echo "   - marketing_campaigns (campanhas)\n";
    echo "\n";
    echo "   PHASE 7:\n";
    echo "   - notifications (notificações)\n";
    echo "   - system_alerts (alertas automáticos)\n";
    echo "   - tasks (tarefas)\n";
    echo "   - alert_settings (configurações)\n";
    echo "   - email_log (log de emails)\n";
    echo "\n";
    echo "🔗 Próximos passos:\n";
    echo "   1. Aceder a /admin/customers/list.php (criar interface)\n";
    echo "   2. Aceder a /admin/notifications/dashboard.php (criar interface)\n";
    echo "   3. Popular dados de teste (opcional)\n";
} else {
    echo "⚠️  Algumas migrações falharam. Revê os erros acima.\n";
}

echo "\n";
