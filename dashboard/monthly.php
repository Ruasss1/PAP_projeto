<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();

// Obter mês da URL
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

// ---------------------------
// Dados do mês
$stmt = $pdo->prepare("
    SELECT DATE(sale_date) as day,
           SUM(s.total) as revenue,
           SUM(si.quantity * p.cost_price) as cogs
    FROM sales s
    JOIN sale_items si ON si.sale_id = s.id
    JOIN products p ON p.id = si.product_id
    WHERE DATE_FORMAT(s.sale_date,'%Y-%m') = :month
    GROUP BY DATE(s.sale_date)
    ORDER BY day
");
$stmt->execute(['month' => $month]);
$dailyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------
// Produto mais vendido do mês
$stmt = $pdo->prepare("
    SELECT p.name, SUM(si.quantity) as total_qty, SUM(si.quantity * si.price) as total_revenue
    FROM sales s
    JOIN sale_items si ON si.sale_id = s.id
    JOIN products p ON p.id = si.product_id
    WHERE DATE_FORMAT(s.sale_date,'%Y-%m') = :month
    GROUP BY p.id
    ORDER BY total_qty DESC
    LIMIT 5
");
$stmt->execute(['month' => $month]);
$topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------
// Resumo do mês
$monthTotalRevenue = 0;
$monthTotalCogs = 0;
foreach ($dailyData as $d) {
    $monthTotalRevenue += $d['revenue'];
    $monthTotalCogs += $d['cogs'];
}
$monthProfit = $monthTotalRevenue - $monthTotalCogs;

// ---------------------------
// Últimos 6 meses para navegação
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[] = date('Y-m', strtotime("-$i month"));
}
?>

<h1>Análise Mensal - <?php echo $month; ?></h1>

<div class="month-nav">
    <a href="dashboard_teste.php" class="btn">← Voltar à Dashboard</a>
</div>

<div class="dashboard-cards">
    <div class="card">
        <h3>Receita do Mês</h3>
        <p class="positive"><?php echo number_format($monthTotalRevenue, 2); ?>€</p>
    </div>
    <div class="card">
        <h3>Custo das Mercadorias</h3>
        <p><?php echo number_format($monthTotalCogs, 2); ?>€</p>
    </div>
    <div class="card">
        <h3>Lucro do Mês</h3>
        <p class="<?php echo $monthProfit >= 0 ? 'positive' : 'negative'; ?>">
            <?php echo number_format($monthProfit, 2); ?>€
        </p>
    </div>
</div>

<h2>Lucro Diário - <?php echo $month; ?></h2>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Dia da Semana</th>
                <th>Receita (€)</th>
                <th>Custo (€)</th>
                <th>Lucro (€)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if (!empty($dailyData)):
                $days = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
                foreach ($dailyData as $d):
                    $revenue = $d['revenue'];
                    $cogs = $d['cogs'];
                    $profit = $revenue - $cogs;
                    $date = new DateTime($d['day']);
                    $dayOfWeek = $days[$date->format('w')];
                    $colorClass = $profit >= 0 ? 'positive' : 'negative';
            ?>
            <tr>
                <td><?php echo $d['day']; ?></td>
                <td><?php echo $dayOfWeek; ?></td>
                <td><?php echo number_format($revenue, 2); ?></td>
                <td><?php echo number_format($cogs, 2); ?></td>
                <td class="<?php echo $colorClass; ?>">
                    <?php echo number_format($profit, 2); ?>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="5">Sem vendas neste mês</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if (!empty($topProducts)): ?>
<h2>Top 5 Produtos Mais Vendidos</h2>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Posição</th>
                <th>Produto</th>
                <th>Quantidade</th>
                <th>Receita (€)</th>
            </tr>
        </thead>
        <tbody>
            <?php $pos = 1; foreach ($topProducts as $p): ?>
            <tr>
                <td><?php echo $pos++; ?>º</td>
                <td><?php echo htmlspecialchars($p['name']); ?></td>
                <td><?php echo $p['total_qty']; ?> un.</td>
                <td><?php echo number_format($p['total_revenue'], 2); ?>€</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

