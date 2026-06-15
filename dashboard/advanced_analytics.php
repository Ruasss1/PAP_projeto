<?php
/**
 * DASHBOARD AVANÇADO - ANÁLISES E RELATÓRIOS
 * Ficheiro: dashboard/advanced_analytics.php
 * 
 * Análises detalhadas:
 * - Vendas por hora/dia/semana
 * - Clientes principais
 * - Rentabilidade por categoria
 * - Previsões de tendências
 */

session_start();
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();
$current_store_id = get_current_store_id();

// Período selecionado (últimos 7/30/90 dias)
$period = $_GET['period'] ?? '30';
$period_label = ['7' => 'Última Semana', '30' => 'Último Mês', '90' => 'Últimos 3 Meses'];

// Data inicial
$start_date = date('Y-m-d', strtotime("-$period days"));
$end_date = date('Y-m-d');

?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg> Análises Avançadas</h1>
        <div class="period-selector">
            <select id="period-select" onchange="changePeriod(this.value)">
                <option value="7" <?= $period == 7 ? 'selected' : '' ?>>Última Semana</option>
                <option value="30" <?= $period == 30 ? 'selected' : '' ?>>Último Mês</option>
                <option value="90" <?= $period == 90 ? 'selected' : '' ?>>Últimos 3 Meses</option>
            </select>
        </div>
    </div>

    <div class="analytics-grid">
        <!-- KPIs Principais -->
        <div class="analytics-section kpi-section">
            <h2>KPIs Principais</h2>
            
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-value">
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT COUNT(DISTINCT id) as count 
                            FROM sales 
                            WHERE sale_date >= :start 
                            AND sale_date <= DATE_ADD(:end, INTERVAL 1 DAY)
                            AND (store_id = :store_id OR store_id IS NULL)
                        ");
                        $stmt->execute([
                            ':start' => $start_date,
                            ':end' => $end_date,
                            ':store_id' => $current_store_id
                        ]);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        echo number_format($result['count'], 0, ',', '.');
                        ?>
                    </div>
                    <div class="kpi-label">Total de Vendas</div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-value">
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT COUNT(DISTINCT customer_id) as count 
                            FROM sales 
                            WHERE sale_date >= :start 
                            AND sale_date <= DATE_ADD(:end, INTERVAL 1 DAY)
                            AND (store_id = :store_id OR store_id IS NULL)
                        ");
                        $stmt->execute([
                            ':start' => $start_date,
                            ':end' => $end_date,
                            ':store_id' => $current_store_id
                        ]);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        echo number_format($result['count'], 0, ',', '.');
                        ?>
                    </div>
                    <div class="kpi-label">Clientes Únicos</div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-value">
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT AVG(total) as avg_value 
                            FROM sales 
                            WHERE sale_date >= :start 
                            AND sale_date <= DATE_ADD(:end, INTERVAL 1 DAY)
                            AND (store_id = :store_id OR store_id IS NULL)
                        ");
                        $stmt->execute([
                            ':start' => $start_date,
                            ':end' => $end_date,
                            ':store_id' => $current_store_id
                        ]);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        echo number_format($result['avg_value'], 2, ',', '.') . '€';
                        ?>
                    </div>
                    <div class="kpi-label">Ticket Médio</div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-value">
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT SUM(si.quantity) as qty 
                            FROM sale_items si
                            JOIN sales s ON s.id = si.sale_id
                            WHERE s.sale_date >= :start 
                            AND s.sale_date <= DATE_ADD(:end, INTERVAL 1 DAY)
                            AND (s.store_id = :store_id OR s.store_id IS NULL)
                        ");
                        $stmt->execute([
                            ':start' => $start_date,
                            ':end' => $end_date,
                            ':store_id' => $current_store_id
                        ]);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        echo number_format($result['qty'] ?? 0, 0, ',', '.');
                        ?>
                    </div>
                    <div class="kpi-label">Artigos Vendidos</div>
                </div>
            </div>
        </div>

        <!-- Top Produtos -->
        <div class="analytics-section">
            <h2><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="8 21 12 21 16 21"/><line x1="12" y1="17" x2="12" y2="21"/><path d="M7 4h10v7a5 5 0 0 1-10 0V4z"/><path d="M5 4a2 2 0 0 0 0 4h-.5"/><path d="M19 4a2 2 0 0 1 0 4h.5"/></svg> Top 10 Produtos Mais Vendidos</h2>
            <table class="analytics-table">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Quantidade</th>
                        <th>Receita</th>
                        <th>Lucro</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT p.name, SUM(si.quantity) as qty, 
                               SUM(si.total_price) as revenue,
                               SUM(si.quantity * (p.sell_price - p.cost_price)) as profit
                        FROM sale_items si
                        JOIN sales s ON s.id = si.sale_id
                        JOIN products p ON p.id = si.product_id
                        WHERE s.sale_date >= :start 
                        AND s.sale_date <= DATE_ADD(:end, INTERVAL 1 DAY)
                        AND (s.store_id = :store_id OR s.store_id IS NULL)
                        GROUP BY p.id
                        ORDER BY qty DESC
                        LIMIT 10
                    ");
                    $stmt->execute([
                        ':start' => $start_date,
                        ':end' => $end_date,
                        ':store_id' => $current_store_id
                    ]);
                    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($products as $p) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($p['name']) . "</td>";
                        echo "<td>" . number_format($p['qty'], 0, ',', '.') . "</td>";
                        echo "<td>" . number_format($p['revenue'], 2, ',', '.') . "€</td>";
                        echo "<td>" . number_format($p['profit'], 2, ',', '.') . "€</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Vendas por Categoria -->
        <div class="analytics-section">
            <h2><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg> Vendas por Categoria</h2>
            <table class="analytics-table">
                <thead>
                    <tr>
                        <th>Categoria</th>
                        <th>Quantidade</th>
                        <th>% Total</th>
                        <th>Receita</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT p.category, SUM(si.quantity) as qty,
                               SUM(si.total_price) as revenue
                        FROM sale_items si
                        JOIN sales s ON s.id = si.sale_id
                        JOIN products p ON p.id = si.product_id
                        WHERE s.sale_date >= :start 
                        AND s.sale_date <= DATE_ADD(:end, INTERVAL 1 DAY)
                        AND (s.store_id = :store_id OR s.store_id IS NULL)
                        GROUP BY p.category
                        ORDER BY qty DESC
                    ");
                    $stmt->execute([
                        ':start' => $start_date,
                        ':end' => $end_date,
                        ':store_id' => $current_store_id
                    ]);
                    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $total_qty = array_sum(array_column($categories, 'qty'));
                    
                    foreach ($categories as $cat) {
                        $percent = ($total_qty > 0) ? ($cat['qty'] / $total_qty * 100) : 0;
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($cat['category']) . "</td>";
                        echo "<td>" . number_format($cat['qty'], 0, ',', '.') . "</td>";
                        echo "<td>" . number_format($percent, 1, ',', '.') . "%</td>";
                        echo "<td>" . number_format($cat['revenue'], 2, ',', '.') . "€</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Top Clientes -->
        <div class="analytics-section">
            <h2><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Top Clientes</h2>
            <table class="analytics-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Número Vendas</th>
                        <th>Gasto Total</th>
                        <th>Ticket Médio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT customer_id, COUNT(*) as num_sales,
                               SUM(total) as total_spent,
                               AVG(total) as avg_spent
                        FROM sales
                        WHERE sale_date >= :start 
                        AND sale_date <= DATE_ADD(:end, INTERVAL 1 DAY)
                        AND (store_id = :store_id OR store_id IS NULL)
                        AND customer_id IS NOT NULL
                        GROUP BY customer_id
                        ORDER BY total_spent DESC
                        LIMIT 10
                    ");
                    $stmt->execute([
                        ':start' => $start_date,
                        ':end' => $end_date,
                        ':store_id' => $current_store_id
                    ]);
                    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($customers as $c) {
                        echo "<tr>";
                        echo "<td>Cliente #" . $c['customer_id'] . "</td>";
                        echo "<td>" . $c['num_sales'] . "</td>";
                        echo "<td>" . number_format($c['total_spent'], 2, ',', '.') . "€</td>";
                        echo "<td>" . number_format($c['avg_spent'], 2, ',', '.') . "€</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.analytics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

.analytics-section {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--border);
}

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.kpi-card {
    background: linear-gradient(135deg, var(--accent) 0%, #00a8cc 100%);
    padding: 1.5rem;
    border-radius: 8px;
    color: white;
    text-align: center;
}

.kpi-value {
    font-size: 1.8rem;
    font-weight: bold;
}

.kpi-label {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-top: 0.5rem;
}

.analytics-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.analytics-table th {
    background: var(--accent);
    color: white;
    padding: 0.75rem;
    text-align: left;
    font-weight: 600;
}

.analytics-table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border);
}

.analytics-table tr:hover {
    background: var(--hover-bg);
}

.period-selector select {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--card-bg);
    color: var(--text);
}
</style>

<script>
function changePeriod(period) {
    window.location.href = `?period=${period}`;
}
</script>
