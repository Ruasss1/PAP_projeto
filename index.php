<?php
session_start();
require_once __DIR__ . '/includes/auth_middleware.php';  // Validar autenticação primeiro
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Require authentication
$auth->require_auth();
$current_user = $auth->get_current_user();

// Log page visit
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$auth->log_audit('page_view', 'dashboard', $current_user['id'], 'SUCCESS', $ip_address, null);

$pdo = db_connect();

// -------------------------------
// Resumo financeiro
$summary = get_financial_summary();
$low = low_stock_alerts(5);

// -------------------------------
// Dados últimos 6 meses (total por semana)
$weeklyStmt = $pdo->query("
    SELECT DATE_FORMAT(s.created_at, '%Y-%m') AS month,
           WEEK(s.created_at, 1) AS week_num,
           SUM(s.total) AS week_total
    FROM sales s
    WHERE s.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month, week_num
    ORDER BY month DESC, week_num ASC
");
$weeklyData = $weeklyStmt->fetchAll(PDO::FETCH_ASSOC);

// Organiza por mês
$monthlyCharts = [];
foreach ($weeklyData as $row) {
    $month = $row['month'];
    $monthlyCharts[$month]['weeks'][] = 'Semana ' . $row['week_num'];
    $monthlyCharts[$month]['totals'][] = (float)$row['week_total'];
}

// -------------------------------
// Produto mais vendido de cada mês
$productStmt = $pdo->query("
    SELECT DATE_FORMAT(s.created_at, '%Y-%m') AS month,
           p.name AS product_name,
           SUM(si.quantity) AS total_qty
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN products p ON si.product_id = p.id
    WHERE s.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month, p.id
    ORDER BY month DESC, total_qty DESC
");

$topProducts = [];
foreach ($productStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $month = $row['month'];
    // só guardamos o primeiro (mais vendido)
    if (!isset($topProducts[$month])) {
        $topProducts[$month] = $row;
    }
}

?>

<section class="welcome">
    <h2>Bem-vindo ao PAP Supermercado</h2>
    <p>Esta é a página inicial do projecto — aqui encontras um resumo rápido e links para navegar pelo sistema.</p>
    <p><strong>Comandos rápidos:</strong></p>
    <pre style="background:#252538;padding:14px;border-radius:6px;color:#aaa"># Instala dependências
composer install

# Arrancar servidor de desenvolvimento
composer serve

# Popular dados de exemplo
composer db:seed

# Executar testes
composer test</pre>
    <p>Abre <code>http://127.0.0.1:8000</code> no teu browser e clica em <strong>Dashboard</strong> para ver o resumo.</p>
    <p>Consulta <code>README.md</code> no repositório para instruções detalhadas.</p>
</section>

<h1>Dashboard</h1>

<section class="dashboard-cards">
    <div class="card">
        <h3>Receita</h3>
        <p class="positive"><?php echo number_format($summary['revenue'],2); ?>€</p>
    </div>
    <div class="card">
        <h3>Lucro Bruto</h3>
        <p class="<?php echo $summary['gross_profit'] >= 0 ? 'positive' : 'negative'; ?>">
            <?php echo number_format($summary['gross_profit'],2); ?>€
        </p>
    </div>
    <div class="card">
        <h3>Lucro Líquido</h3>
        <p class="<?php echo $summary['net_profit'] >= 0 ? 'positive' : 'negative'; ?>">
            <?php echo number_format($summary['net_profit'],2); ?>€
        </p>
    </div>
</section>

<h2>Produtos com stock baixo</h2>
<?php if (count($low) === 0): ?>
    <p>Sem alertas no momento.</p>
<?php else: ?>
    <ul>
        <?php foreach ($low as $l): ?>
            <li><?php echo htmlspecialchars($l['name']) . ' — stock: ' . $l['stock']; ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<h2>Resumo últimos 6 meses</h2>

<?php foreach ($monthlyCharts as $month => $chart): 
    $top = $topProducts[$month] ?? ['product_name'=>'-', 'total_qty'=>0];
?>
    <h3><?php echo htmlspecialchars($month); ?></h3>
    <p>Produto mais vendido: <strong><?php echo htmlspecialchars($top['product_name']); ?></strong> — <?php echo $top['total_qty']; ?> unidades</p>
    <canvas id="chart_<?php echo str_replace('-', '_', $month); ?>" width="400" height="150"></canvas>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php foreach ($monthlyCharts as $month => $chart): ?>
new Chart(document.getElementById('chart_<?php echo str_replace('-', '_', $month); ?>'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chart['weeks']); ?>,
        datasets: [{
            label: 'Vendas (€)',
            data: <?php echo json_encode($chart['totals']); ?>,
            backgroundColor: '#0d6efd',
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        plugins: { 
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: '#333'
                },
                ticks: {
                    color: '#888'
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    color: '#888'
                }
            }
        }
    }
});
<?php endforeach; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

