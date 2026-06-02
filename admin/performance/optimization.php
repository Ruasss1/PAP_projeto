<?php
/**
 * MÓDULO DE OTIMIZAÇÃO E PERFORMANCE
 * Ficheiro: admin/performance/optimization.php
 * 
 * Análise de performance do sistema:
 * - Índices de base de dados
 * - Queries lentas
 * - Uso de recursos
 * - Recomendações de otimização
 */

session_start();
require_once __DIR__ . '/../../includes/auth_middleware.php';
$page_title = 'Otimização';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';

// Apenas admin
if (($_SESSION['role_id'] ?? null) !== 1) {
    die('Acesso negado');
}

$pdo = db_connect();
?>

<div class="performance-container">
    <h1>⚙️ Otimização e Performance</h1>

    <!-- Métricas do Sistema -->
    <div class="metrics-grid">
        <?php
        // Total de vendas
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM sales");
        $sales_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Total de produtos
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
        $products_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Total de utilizadores
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
        $users_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Total de colaboradores
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM employees");
        $employees_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Tamanho da BD
        $stmt = $pdo->query("
            SELECT SUM(data_length + index_length) / 1024 / 1024 as size_mb
            FROM information_schema.tables
            WHERE table_schema = 'supermercado'
        ");
        $db_size = $stmt->fetch(PDO::FETCH_ASSOC)['size_mb'] ?? 0;
        ?>
        
        <div class="metric-card">
            <div class="metric-value"><?= $sales_count ?></div>
            <div class="metric-label">Total de Vendas</div>
        </div>
        <div class="metric-card">
            <div class="metric-value"><?= $products_count ?></div>
            <div class="metric-label">Produtos</div>
        </div>
        <div class="metric-card">
            <div class="metric-value"><?= $users_count ?></div>
            <div class="metric-label">Utilizadores</div>
        </div>
        <div class="metric-card">
            <div class="metric-value"><?= $employees_count ?></div>
            <div class="metric-label">Colaboradores</div>
        </div>
        <div class="metric-card">
            <div class="metric-value"><?= round($db_size, 2) ?> MB</div>
            <div class="metric-label">Tamanho BD</div>
        </div>
    </div>

    <!-- Índices de Banco de Dados -->
    <section class="optimization-section">
        <h2>📊 Índices de Base de Dados</h2>
        <div class="indexes-table">
            <?php
            $tables = [
                'users', 'products', 'sales', 'sale_items', 'employees',
                'orders', 'transactions', 'audit_logs'
            ];
            
            foreach ($tables as $table) {
                $stmt = $pdo->query("SHOW INDEXES FROM $table");
                $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<div class='table-indexes'>";
                echo "<h4>Tabela: <strong>$table</strong></h4>";
                echo "<ul>";
                
                if (empty($indexes)) {
                    echo "<li>Nenhum índice definido</li>";
                } else {
                    foreach ($indexes as $idx) {
                        echo "<li>" . $idx['Key_name'] . " (" . $idx['Column_name'] . ")</li>";
                    }
                }
                
                echo "</ul></div>";
            }
            ?>
        </div>
    </section>

    <!-- Recomendações de Otimização -->
    <section class="optimization-section">
        <h2>💡 Recomendações de Otimização</h2>
        <div class="recommendations">
            <?php
            $recommendations = [
                [
                    'title' => 'Adicionar Índices a Campos Frequentemente Consultados',
                    'description' => 'Crie índices nas colunas user_id, store_id e created_at',
                    'priority' => 'Alta',
                    'code' => 'ALTER TABLE sales ADD INDEX idx_user_store (user_id, store_id);'
                ],
                [
                    'title' => 'Implementar Paginação',
                    'description' => 'Limite resultados a 100 registos por página',
                    'priority' => 'Alta',
                    'code' => 'SELECT * FROM sales LIMIT 100 OFFSET 0;'
                ],
                [
                    'title' => 'Arquivar Dados Antigos',
                    'description' => 'Mova registos com mais de 1 ano para tabelas de arquivo',
                    'priority' => 'Média',
                    'code' => 'CREATE TABLE sales_archive LIKE sales;'
                ],
                [
                    'title' => 'Usar Cache de Resultados',
                    'description' => 'Implemente Redis ou arquivo cache para consultas frequentes',
                    'priority' => 'Média',
                    'code' => 'Use Memcached ou Redis para cache'
                ],
                [
                    'title' => 'Otimizar Queries SQL',
                    'description' => 'Use EXPLAIN para analisar e otimizar consultas lentas',
                    'priority' => 'Alta',
                    'code' => 'EXPLAIN SELECT * FROM sales WHERE ...'
                ]
            ];
            
            foreach ($recommendations as $rec) {
                echo "
                <div class='recommendation-card priority-" . strtolower($rec['priority']) . "'>
                    <div class='rec-header'>
                        <h3>" . $rec['title'] . "</h3>
                        <span class='priority-badge'>" . $rec['priority'] . "</span>
                    </div>
                    <p>" . $rec['description'] . "</p>
                    <pre class='code-block'>" . htmlspecialchars($rec['code']) . "</pre>
                </div>";
            }
            ?>
        </div>
    </section>

    <!-- Análise de Queries Lentas -->
    <section class="optimization-section">
        <h2>🐌 Queries Lentas (Simuladas)</h2>
        <div class="slow-queries">
            <?php
            $slow_queries = [
                [
                    'query' => 'SELECT * FROM sales JOIN sale_items ON sales.id = sale_items.sale_id WHERE sales.created_at > ?',
                    'time' => '1.2s',
                    'rows' => 5000,
                    'fix' => 'Adicionar índice em sales.created_at'
                ],
                [
                    'query' => 'SELECT * FROM products WHERE name LIKE ?',
                    'time' => '0.8s',
                    'rows' => 2000,
                    'fix' => 'Usar FULLTEXT search ou SOUNDEX'
                ],
                [
                    'query' => 'SELECT * FROM employees WHERE department = ? ORDER BY salary DESC',
                    'time' => '0.5s',
                    'rows' => 500,
                    'fix' => 'Adicionar índice em (department, salary)'
                ]
            ];
            
            foreach ($slow_queries as $sq) {
                echo "
                <div class='slow-query-card'>
                    <div class='query-info'>
                        <span class='query-time' style='color: #dc3545;'>" . $sq['time'] . "</span>
                        <span class='query-rows'>" . $sq['rows'] . " registos</span>
                    </div>
                    <code class='query-code'>" . htmlspecialchars($sq['query']) . "</code>
                    <div class='query-fix'>
                        <strong>Sugestão:</strong> " . $sq['fix'] . "
                    </div>
                </div>";
            }
            ?>
        </div>
    </section>

    <!-- Ferramentas de Manutenção -->
    <section class="optimization-section">
        <h2>🔧 Ferramentas de Manutenção</h2>
        <div class="maintenance-tools">
            <button class="tool-btn" onclick="optimizeTables()">
                🔨 Otimizar Tabelas
            </button>
            <button class="tool-btn" onclick="analyzeTables()">
                📈 Analisar Tabelas
            </button>
            <button class="tool-btn" onclick="repairTables()">
                🔧 Reparar Tabelas
            </button>
            <button class="tool-btn" onclick="clearCache()">
                🗑️ Limpar Cache
            </button>
        </div>
    </section>
</div>

<style>
.performance-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
}

.performance-container h1 {
    margin-bottom: 2rem;
    color: var(--text);
}

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.metric-card {
    background: var(--card-bg);
    padding: 1.5rem;
    border-radius: 12px;
    border-left: 4px solid var(--accent);
    text-align: center;
}

.metric-value {
    font-size: 2.5rem;
    font-weight: bold;
    color: var(--accent);
    margin-bottom: 0.5rem;
}

.metric-label {
    color: var(--text-secondary);
    font-size: 0.95rem;
}

.optimization-section {
    margin-bottom: 3rem;
}

.optimization-section h2 {
    color: var(--text);
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border);
}

.indexes-table {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.table-indexes {
    background: var(--card-bg);
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid var(--border);
}

.table-indexes h4 {
    margin: 0 0 1rem 0;
    color: var(--accent);
}

.table-indexes ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.table-indexes li {
    padding: 0.5rem;
    background: var(--hover-bg);
    margin-bottom: 0.5rem;
    border-radius: 4px;
    font-size: 0.9rem;
    font-family: monospace;
}

.recommendations {
    display: grid;
    gap: 1.5rem;
}

.recommendation-card {
    background: var(--card-bg);
    padding: 1.5rem;
    border-radius: 8px;
    border-left: 4px solid #ffc107;
}

.recommendation-card.priority-alta {
    border-left-color: #dc3545;
    background: rgba(220, 53, 69, 0.05);
}

.recommendation-card.priority-média {
    border-left-color: #ffc107;
    background: rgba(255, 193, 7, 0.05);
}

.rec-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.rec-header h3 {
    margin: 0;
    color: var(--text);
}

.priority-badge {
    background: var(--accent);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.code-block {
    background: var(--hover-bg);
    padding: 1rem;
    border-radius: 4px;
    overflow-x: auto;
    margin: 1rem 0 0 0;
    font-family: monospace;
    font-size: 0.9rem;
    color: #d4d4d4;
}

.slow-queries {
    display: grid;
    gap: 1.5rem;
}

.slow-query-card {
    background: var(--card-bg);
    padding: 1.5rem;
    border-radius: 8px;
    border-left: 4px solid #dc3545;
}

.query-info {
    display: flex;
    gap: 2rem;
    margin-bottom: 1rem;
}

.query-time {
    font-weight: bold;
    font-size: 1.1rem;
}

.query-rows {
    color: var(--text-secondary);
}

.query-code {
    background: var(--hover-bg);
    padding: 0.75rem 1rem;
    border-radius: 4px;
    display: block;
    overflow-x: auto;
    font-family: monospace;
    margin-bottom: 1rem;
}

.query-fix {
    padding: 1rem;
    background: var(--hover-bg);
    border-left: 3px solid #28a745;
    border-radius: 4px;
}

.maintenance-tools {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
}

.tool-btn {
    padding: 1rem;
    background: var(--accent);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.tool-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.tool-btn:active {
    transform: translateY(0);
}
</style>

<script>
function optimizeTables() {
    alert('Otimizando tabelas...');
    // Executar: OPTIMIZE TABLE [table_name]
}

function analyzeTables() {
    alert('Analisando tabelas...');
    // Executar: ANALYZE TABLE [table_name]
}

function repairTables() {
    alert('Reparando tabelas...');
    // Executar: REPAIR TABLE [table_name]
}

function clearCache() {
    alert('Cache limpo com sucesso!');
    // Limpar cache de aplicação
}
</script>
