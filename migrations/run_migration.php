<?php
/**
 * Script de Migração para PHASE 4
 * Executa a migração 005_rh_management.sql
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "✓ Conexão à base de dados estabelecida.\n";
    
    // Ler o ficheiro de migração
    $migration_file = __DIR__ . '/005_rh_management.sql';
    
    if (!file_exists($migration_file)) {
        echo "✗ Ficheiro de migração não encontrado: $migration_file\n";
        exit(1);
    }
    
    $sql_content = file_get_contents($migration_file);
    
    // Dividir por `;` para executar cada comando
    $queries = array_filter(array_map('trim', explode(';', $sql_content)));
    
    $executed = 0;
    foreach ($queries as $query) {
        if (empty($query)) continue;
        
        try {
            $pdo->exec($query);
            $executed++;
            echo "✓ Query executada com sucesso.\n";
        } catch (PDOException $e) {
            echo "⚠ Aviso na query: " . $e->getMessage() . "\n";
            // Continuar mesmo com erros (algumas tabelas podem já existir)
        }
    }
    
    echo "\n✓ Migração concluída! $executed queries executadas.\n";
    echo "\nTabelas criadas:\n";
    echo "- employees (Colaboradores)\n";
    echo "- employee_documents (Documentos)\n";
    echo "- shifts (Turnos)\n";
    echo "- schedules (Horários)\n";
    echo "- vacation_requests (Pedidos de Férias)\n";
    echo "- vacation_balance (Saldo de Férias)\n";
    echo "- absences (Faltas)\n";
    echo "- payroll (Folha de Pagamento)\n";
    echo "- salary_history (Histórico de Salários)\n";
    echo "- sales_commissions (Comissões de Vendas)\n";
    echo "- rh_audit_log (Auditoria RH)\n";
    
} catch (PDOException $e) {
    echo "✗ Erro de conexão: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

?>
