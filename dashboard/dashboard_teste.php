<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();

// ---------------------------
// 1. Últimos 6 meses
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[] = date('Y-m', strtotime("-$i month"));
}

// ---------------------------
// 2. Total e lucro por dia de cada mês
$dailyData = [];
foreach ($months as $m) {
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
    $stmt->execute(['month' => $m]);
    $dailyData[$m] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------------------------
// 3. Produto mais vendido de cada mês
$topProducts = [];
foreach ($months as $m) {
    $stmt = $pdo->prepare("
        SELECT p.name, SUM(si.quantity) as total_qty
        FROM sales s
        JOIN sale_items si ON si.sale_id = s.id
        JOIN products p ON p.id = si.product_id
        WHERE DATE_FORMAT(s.sale_date,'%Y-%m') = :month
        GROUP BY p.id
        ORDER BY total_qty DESC
        LIMIT 1
    ");
    $stmt->execute(['month' => $m]);
    $topProducts[$m] = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ---------------------------
// 4. Resumo financeiro global
$summary = get_financial_summary();
?>

<h1>Dashboard - Análise Financeira</h1>

<section class="dashboard-cards">
    <div class="card">
        <h3>Receita Total</h3>
        <p class="positive"><?php echo number_format($summary['revenue'], 2); ?>€</p>
    </div>
    <div class="card">
        <h3>Custo das Mercadorias</h3>
        <p><?php echo number_format($summary['cogs'], 2); ?>€</p>
    </div>
    <div class="card">
        <h3>Salários</h3>
        <p><?php echo number_format($summary['salaries'], 2); ?>€</p>
    </div>
    <div class="card">
        <h3>Quebras</h3>
        <p class="negative"><?php echo number_format($summary['breaks'], 2); ?>€</p>
    </div>
    <div class="card">
        <h3>Lucro Bruto</h3>
        <p class="<?php echo $summary['gross_profit'] >= 0 ? 'positive' : 'negative'; ?>">
            <?php echo number_format($summary['gross_profit'], 2); ?>€
        </p>
    </div>
    <div class="card">
        <h3>Lucro Líquido</h3>
        <p class="<?php echo $summary['net_profit'] >= 0 ? 'positive' : 'negative'; ?>">
            <?php echo number_format($summary['net_profit'], 2); ?>€
        </p>
    </div>
</section>

<h2>Resumo Mensal - Últimos 6 Meses</h2>
<p class="subtitle">Clique num mês para ver o detalhe diário</p>

<div class="dashboard-grid">
    <?php foreach ($months as $m): ?>
        <a href="monthly.php?month=<?php echo $m; ?>" class="card month-card">
            <h3><?php echo $m; ?></h3>
            <?php
            $monthTotal = 0;
            if (!empty($dailyData[$m])) {
                foreach ($dailyData[$m] as $d) {
                    $profit = $d['revenue'] - $d['cogs'];
                    $monthTotal += $profit;
                }
                $colorClass = $monthTotal >= 0 ? 'positive' : 'negative';
            } else {
                $colorClass = 'negative';
                $monthTotal = 0;
            }
            ?>
            <p>Lucro: <span class="<?php echo $colorClass; ?>">
                <?php echo number_format($monthTotal, 2); ?>€
            </span></p>
            <?php if (!empty($topProducts[$m])): ?>
                <p class="top-product">Mais vendido:<br>
                <strong><?php echo htmlspecialchars($topProducts[$m]['name']); ?></strong>
                (<?php echo $topProducts[$m]['total_qty']; ?> un.)</p>
            <?php else: ?>
                <p>Sem vendas</p>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>

<h2>Lucro Diário - Mês Mais Recente (<?php echo end($months); ?>)</h2>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Receita (€)</th>
                <th>Custo (€)</th>
                <th>Lucro (€)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $latestMonth = end($months);
            if (!empty($dailyData[$latestMonth])):
                foreach ($dailyData[$latestMonth] as $d):
                    $revenue = $d['revenue'];
                    $cogs = $d['cogs'];
                    $profit = $revenue - $cogs;
                    $colorClass = $profit >= 0 ? 'positive' : 'negative';
            ?>
            <tr>
                <td><?php echo $d['day']; ?></td>
                <td><?php echo number_format($revenue, 2); ?></td>
                <td><?php echo number_format($cogs, 2); ?></td>
                <td class="<?php echo $colorClass; ?>">
                    <?php echo number_format($profit, 2); ?>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4">Sem vendas neste mês</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

