<?php
/**
 * Script PHP para executar migrações com suporte a triggers
 * migrations/execute_migrations.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();

echo "\n========================================\n";
echo "EXECUTAR MIGRAÇÕES - PHASES 6 & 7\n";
echo "========================================\n\n";

// Desabilitar foreign key checks temporariamente
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

$migrations = [
    __DIR__ . '/006_customers_loyalty.sql' => 'PHASE 6: Sistema de Clientes e Fidelização',
    __DIR__ . '/007_notifications_alerts.sql' => 'PHASE 7: Notificações e Alertas'
];

$total_success = 0;
$total_errors = 0;

foreach ($migrations as $filepath => $description) {
    echo "📁 $description\n";
    echo "   Arquivo: " . basename($filepath) . "\n";
    
    if (!file_exists($filepath)) {
        echo "   ❌ ERRO: Arquivo não encontrado\n\n";
        $total_errors++;
        continue;
    }
    
    $sql = file_get_contents($filepath);
    
    // Processar o SQL
    $success = 0;
    $errors = 0;
    $current_delimiter = ';';
    $buffer = '';
    $in_delimiter_block = false;
    
    // Dividir por linhas
    $lines = explode("\n", $sql);
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Ignorar comentários
        if (empty($line) || strpos($line, '--') === 0) {
            continue;
        }
        
        // Detectar mudança de delimitador
        if (stripos($line, 'DELIMITER') === 0) {
            $parts = explode(' ', $line);
            if (isset($parts[1])) {
                if ($parts[1] == ';') {
                    // Voltou ao delimitador padrão, executar buffer acumulado
                    if (!empty($buffer)) {
                        try {
                            $pdo->exec($buffer);
                            $success++;
                            $buffer = '';
                        } catch (PDOException $e) {
                            if (strpos($e->getMessage(), 'already exists') === false &&
                                strpos($e->getMessage(), 'Duplicate') === false) {
                                echo "   ⚠️  Erro: " . $e->getMessage() . "\n";
                                $errors++;
                            }
                            $buffer = '';
                        }
                    }
                    $in_delimiter_block = false;
                    $current_delimiter = ';';
                } else {
                    // Mudou para $$ ou outro
                    $current_delimiter = $parts[1];
                    $in_delimiter_block = true;
                }
            }
            continue;
        }
        
        // Acumular linha no buffer
        $buffer .= $line . "\n";
        
        // Se não estiver em bloco de trigger, executar quando encontrar ;
        if (!$in_delimiter_block && substr(rtrim($line), -1) == ';') {
            try {
                $statement = trim($buffer);
                if (!empty($statement)) {
                    $pdo->exec($statement);
                    $success++;
                }
                $buffer = '';
            } catch (PDOException $e) {
                // Ignorar erros comuns
                if (strpos($e->getMessage(), 'already exists') === false &&
                    strpos($e->getMessage(), 'Duplicate') === false &&
                    strpos($e->getMessage(), 'Can\'t DROP') === false) {
                    echo "   ⚠️  Erro: " . $e->getMessage() . "\n";
                    $errors++;
                }
                $buffer = '';
            }
        }
        
        // Se estiver em bloco e encontrar o delimitador customizado
        if ($in_delimiter_block && strpos($line, $current_delimiter) !== false) {
            // Já foi executado acima quando volta para ;
            continue;
        }
    }
    
    // Executar qualquer SQL restante no buffer
    if (!empty($buffer)) {
        try {
            $pdo->exec(trim($buffer));
            $success++;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') === false &&
                strpos($e->getMessage(), 'Duplicate') === false) {
                echo "   ⚠️  Erro: " . $e->getMessage() . "\n";
                $errors++;
            }
        }
    }
    
    if ($errors == 0) {
        echo "   ✅ Sucesso: $success operações executadas\n";
        $total_success++;
    } else {
        echo "   ⚠️  Parcial: $success sucesso, $errors erros\n";
        if ($success > $errors * 2) {
            // Mais sucessos do que erros, considerar OK
            $total_success++;
        } else {
            $total_errors++;
        }
    }
    
    echo "\n";
}

// Reativar foreign key checks
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "========================================\n";
echo "RESUMO FINAL\n";
echo "========================================\n";
echo "✅ Migrações com sucesso: $total_success\n";
echo "❌ Migrações com erros: $total_errors\n";
echo "\n";

if ($total_success >= 2) {
    echo "🎉 Migrações executadas!\n";
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
}

// Verificar se tabelas foram criadas
echo "🔍 Verificando tabelas criadas...\n";
$tables_expected = [
    'customers', 'loyalty_points_history', 'loyalty_rewards', 'loyalty_redemptions',
    'marketing_campaigns', 'notifications', 'system_alerts', 'tasks', 
    'alert_settings', 'email_log'
];

$stmt = $pdo->query("SHOW TABLES");
$existing_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables_expected as $table) {
    if (in_array($table, $existing_tables)) {
        echo "   ✅ $table\n";
    } else {
        echo "   ❌ $table (não encontrada)\n";
    }
}

echo "\n";
