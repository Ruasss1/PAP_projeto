<?php
/**
 * RELATÓRIOS - PREMIUM
 * Dashboard de relatórios com gráficos modernos
 */

header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();
$current_store_id = get_current_store_id();

// Período
$period = $_GET['period'] ?? 'month';
$date_from = match($period) {
    'week' => date('Y-m-d', strtotime('-7 days')),
    'month' => date('Y-m-d', strtotime('-30 days')),
    'quarter' => date('Y-m-d', strtotime('-90 days')),
    'year' => date('Y-m-d', strtotime('-365 days')),
    default => date('Y-m-d', strtotime('-30 days'))
};
$date_to = date('Y-m-d');

// Vendas totais
$sales = $pdo->prepare("SELECT 
    COUNT(*) as count,
    COALESCE(SUM(total), 0) as revenue,
    COALESCE(AVG(total), 0) as avg_sale
    FROM sales WHERE store_id = ? AND DATE(sale_date) BETWEEN ? AND ?");
$sales->execute([$current_store_id, $date_from, $date_to]);
$sales = $sales->fetch();

// Vendas por dia (para gráfico)
$daily = $pdo->prepare("SELECT DATE(sale_date) as date, SUM(total) as total, COUNT(*) as count
    FROM sales WHERE store_id = ? AND DATE(sale_date) BETWEEN ? AND ?
    GROUP BY DATE(sale_date) ORDER BY date");
$daily->execute([$current_store_id, $date_from, $date_to]);
$daily = $daily->fetchAll();

// Vendas por método de pagamento
$payments = $pdo->prepare("SELECT payment_method, COUNT(*) as count, SUM(total) as total
    FROM sales WHERE store_id = ? AND DATE(sale_date) BETWEEN ? AND ?
    GROUP BY payment_method ORDER BY total DESC");
$payments->execute([$current_store_id, $date_from, $date_to]);
$payments = $payments->fetchAll();

// Top produtos
$top_products = $pdo->prepare("SELECT p.name, SUM(si.quantity) as qty, SUM(si.quantity * si.price) as total
    FROM sale_items si 
    JOIN products p ON si.product_id = p.id
    JOIN sales s ON si.sale_id = s.id
    WHERE s.store_id = ? AND DATE(s.sale_date) BETWEEN ? AND ?
    GROUP BY p.id ORDER BY total DESC LIMIT 10");
$top_products->execute([$current_store_id, $date_from, $date_to]);
$top_products = $top_products->fetchAll();

// Vendas por categoria
$categories = $pdo->prepare("SELECT p.category, SUM(si.quantity * si.price) as total
    FROM sale_items si 
    JOIN products p ON si.product_id = p.id
    JOIN sales s ON si.sale_id = s.id
    WHERE s.store_id = ? AND DATE(s.sale_date) BETWEEN ? AND ?
    GROUP BY p.category ORDER BY total DESC");
$categories->execute([$current_store_id, $date_from, $date_to]);
$categories = $categories->fetchAll();

// Horários mais vendidos
$hourly = $pdo->prepare("SELECT HOUR(sale_date) as hour, COUNT(*) as count
    FROM sales WHERE store_id = ? AND DATE(sale_date) BETWEEN ? AND ?
    GROUP BY HOUR(sale_date) ORDER BY hour");
$hourly->execute([$current_store_id, $date_from, $date_to]);
$hourly = $hourly->fetchAll();

// Comparação com período anterior
$prev_from = date('Y-m-d', strtotime($date_from) - (strtotime($date_to) - strtotime($date_from)));
$prev = $pdo->prepare("SELECT COALESCE(SUM(total), 0) as revenue FROM sales WHERE store_id = ? AND DATE(sale_date) BETWEEN ? AND ?");
$prev->execute([$current_store_id, $prev_from, $date_from]);
$prev_revenue = $prev->fetch()['revenue'];
$growth = $prev_revenue > 0 ? (($sales['revenue'] - $prev_revenue) / $prev_revenue) * 100 : 0;

// ── Lucro Real ──────────────────────────────────────────────────────────────
$profit_q = $pdo->prepare("SELECT
    COALESCE(SUM(si.quantity * si.price), 0) as revenue,
    COALESCE(SUM(si.quantity * si.cost_price), 0) as cost,
    COALESCE(SUM(si.quantity * (si.price - si.cost_price)), 0) as profit
    FROM sale_items si JOIN sales s ON si.sale_id = s.id
    WHERE s.store_id = ? AND DATE(s.sale_date) BETWEEN ? AND ?");
$profit_q->execute([$current_store_id, $date_from, $date_to]);
$profit_data = $profit_q->fetch();
$margin_pct = $profit_data['revenue'] > 0 ? ($profit_data['profit'] / $profit_data['revenue']) * 100 : 0;

// Lucro por dia
$profit_daily = $pdo->prepare("SELECT DATE(s.sale_date) as date,
    SUM(si.quantity * (si.price - si.cost_price)) as profit
    FROM sale_items si JOIN sales s ON si.sale_id = s.id
    WHERE s.store_id = ? AND DATE(s.sale_date) BETWEEN ? AND ?
    GROUP BY DATE(s.sale_date) ORDER BY date");
$profit_daily->execute([$current_store_id, $date_from, $date_to]);
$profit_daily = $profit_daily->fetchAll();

// ── Comparação de Lojas ─────────────────────────────────────────────────────
$store_cmp = $pdo->prepare("SELECT store_id,
    COUNT(*) as sales_count,
    COALESCE(SUM(total),0) as revenue,
    COALESCE(AVG(total),0) as avg_sale
    FROM sales WHERE DATE(sale_date) BETWEEN ? AND ?
    GROUP BY store_id ORDER BY store_id");
$store_cmp->execute([$date_from, $date_to]);
$store_cmp = $store_cmp->fetchAll();

// ── Previsão de Stock (reorder) ─────────────────────────────────────────────
$reorder = $pdo->prepare("SELECT p.id, p.name, p.stock, p.min_stock,
    COALESCE(SUM(si.quantity),0) / GREATEST(1, DATEDIFF(?, ?)) as daily_avg
    FROM products p
    LEFT JOIN sale_items si ON si.product_id = p.id
    LEFT JOIN sales s ON si.sale_id = s.id AND DATE(s.sale_date) BETWEEN ? AND ?
    WHERE p.store_id = ? AND p.active = 1
    GROUP BY p.id HAVING p.stock <= (daily_avg * 7) OR p.stock <= p.min_stock
    ORDER BY (p.stock / GREATEST(1, daily_avg)) ASC LIMIT 20");
$reorder->execute([$date_to, $date_from, $date_from, $date_to, $current_store_id]);
$reorder = $reorder->fetchAll();

// ── Export CSV ──────────────────────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['Data','Nº Vendas','Receita (€)','Lucro (€)'], ';');
    $exp = $pdo->prepare("SELECT DATE(s.sale_date) as d, COUNT(*) as cnt,
        SUM(s.total) as rev, SUM(si.quantity*(si.price-si.cost_price)) as profit
        FROM sales s LEFT JOIN sale_items si ON si.sale_id = s.id
        WHERE s.store_id = ? AND DATE(s.sale_date) BETWEEN ? AND ?
        GROUP BY DATE(s.sale_date) ORDER BY d");
    $exp->execute([$current_store_id, $date_from, $date_to]);
    while ($r = $exp->fetch()) {
        fputcsv($out, [date('d/m/Y', strtotime($r['d'])), $r['cnt'], number_format($r['rev'],2,',','.'), number_format($r['profit'],2,',','.')], ';');
    }
    fclose($out); exit;
}

$page_title = 'Relatórios';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Filtros de período -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 24px;">
        <div style="display: flex; gap: 8px; align-items: center;">
            <span style="font-size: 14px; color: var(--text-secondary);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Período:</span>
            <a href="?period=week" class="btn <?= $period === 'week' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">7 Dias</a>
            <a href="?period=month" class="btn <?= $period === 'month' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">30 Dias</a>
            <a href="?period=quarter" class="btn <?= $period === 'quarter' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">90 Dias</a>
            <a href="?period=year" class="btn <?= $period === 'year' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">1 Ano</a>
            <span style="margin-left: auto; font-size: 13px; color: var(--text-muted);">
                <?= date('d/m/Y', strtotime($date_from)) ?> - <?= date('d/m/Y', strtotime($date_to)) ?>
            </span>
            <a href="?period=<?= $period ?>&export_csv=1" class="btn btn-secondary btn-sm"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg> CSV</a>
        </div>
    </div>
</div>

<!-- KPIs -->
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon blue"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg></div>
        </div>
        <div class="stat-value">€<?= number_format($sales['revenue'], 2, ',', '.') ?></div>
        <div class="stat-label">Receita Total</div>
        <div style="margin-top: 8px;">
            <?php if ($growth > 0): ?>
            <span class="badge badge-success">↑ <?= number_format($growth, 1) ?>%</span>
            <?php elseif ($growth < 0): ?>
            <span class="badge badge-danger">↓ <?= number_format(abs($growth), 1) ?>%</span>
            <?php else: ?>
            <span class="badge badge-muted">→ 0%</span>
            <?php endif; ?>
            <span style="font-size: 11px; color: var(--text-muted);">vs período anterior</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon green"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></div>
        </div>
        <div class="stat-value"><?= $sales['count'] ?></div>
        <div class="stat-label">Total Vendas</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon purple"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></div>
        </div>
        <div class="stat-value">€<?= number_format($sales['avg_sale'], 2) ?></div>
        <div class="stat-label">Ticket Médio</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon orange"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg></div>
        </div>
        <div class="stat-value">€<?= count($daily) > 0 ? number_format($sales['revenue'] / count($daily), 2) : '0.00' ?></div>
        <div class="stat-label">Média/Dia</div>
    </div>
</div>

<!-- Gráficos principais -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
    <!-- Gráfico de vendas -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg> Evolução de Vendas</h3>
        </div>
        <div class="card-body">
            <canvas id="salesChart" height="300"></canvas>
        </div>
    </div>
    
    <!-- Gráfico de pagamentos -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> Métodos de Pagamento</h3>
        </div>
        <div class="card-body">
            <canvas id="paymentChart" height="300"></canvas>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
    <!-- Top Produtos -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="8 21 12 21 16 21"/><line x1="12" y1="17" x2="12" y2="21"/><path d="M7 4h10v7a5 5 0 0 1-10 0V4z"/><path d="M5 4a2 2 0 0 0 0 4h-.5"/><path d="M19 4a2 2 0 0 1 0 4h.5"/></svg> Top 10 Produtos</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Produto</th>
                        <th>Qtd</th>
                        <th>Receita</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_products as $i => $prod): ?>
                    <tr>
                        <td><span class="badge badge-primary"><?= $i + 1 ?></span></td>
                        <td><?= htmlspecialchars($prod['name']) ?></td>
                        <td><?= $prod['qty'] ?></td>
                        <td><strong class="money-positive">€<?= number_format($prod['total'], 2) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($top_products)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 32px; color: var(--text-muted);">Sem dados</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Categorias -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg> Vendas por Categoria</h3>
        </div>
        <div class="card-body">
            <canvas id="categoryChart" height="250"></canvas>
        </div>
    </div>
</div>

<!-- Gráfico de horários -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Vendas por Hora do Dia</h3>
    </div>
    <div class="card-body">
        <canvas id="hourlyChart" height="150"></canvas>
    </div>
</div>

<script>
function themeColors() {
    const dark = document.documentElement.getAttribute('data-theme') !== 'light';
    return {
        grid: dark ? 'rgba(255,255,255,.04)' : 'rgba(0,0,0,.05)',
        tick: dark ? '#4a4a54' : '#8a8a9e',
        bar:  dark ? 'rgba(236,236,236,.14)' : 'rgba(17,17,17,.10)',
        barBorder: dark ? '#cecece' : '#111111',
        line: dark ? 'rgba(236,236,236,.10)' : 'rgba(17,17,17,.07)',
        lineBorder: dark ? '#e8e8e8' : '#111111',
        doughnut: dark
            ? ['#52525b','#71717a','#a1a1aa','#3f3f46','#27272a']
            : ['#52525b','#71717a','#a1a1aa','#3f3f46','#d4d4d8'],
    };
}

// Dados para gráficos
const dailyData = <?= json_encode($daily) ?>;
const paymentData = <?= json_encode($payments) ?>;
const categoryData = <?= json_encode($categories) ?>;
const hourlyData = <?= json_encode($hourly) ?>;

const tc = themeColors();

// Gráfico de vendas diárias
new Chart(document.getElementById('salesChart'), {
    type: 'line',
    data: {
        labels: dailyData.map(d => {
            const date = new Date(d.date);
            return date.toLocaleDateString('pt-PT', { day: '2-digit', month: 'short' });
        }),
        datasets: [{
            label: 'Receita (€)',
            data: dailyData.map(d => d.total),
            borderColor: tc.lineBorder,
            backgroundColor: tc.line,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: tc.grid }, ticks: { color: tc.tick } },
            x: { grid: { display: false }, ticks: { color: tc.tick } }
        }
    }
});

// Gráfico de pagamentos
new Chart(document.getElementById('paymentChart'), {
    type: 'doughnut',
    data: {
        labels: paymentData.map(p => p.payment_method || 'N/A'),
        datasets: [{ data: paymentData.map(p => p.total), backgroundColor: tc.doughnut }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { color: tc.tick, padding: 16 } } }
    }
});

// Gráfico de categorias
new Chart(document.getElementById('categoryChart'), {
    type: 'bar',
    data: {
        labels: categoryData.map(c => c.category || 'Sem categoria'),
        datasets: [{ label: 'Receita (€)', data: categoryData.map(c => c.total), backgroundColor: tc.bar, borderColor: tc.barBorder, borderWidth: 1 }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: {
            x: { beginAtZero: true, grid: { color: tc.grid }, ticks: { color: tc.tick } },
            y: { grid: { display: false }, ticks: { color: tc.tick } }
        }
    }
});

// Gráfico de horários
const hours = Array.from({length: 24}, (_, i) => i);
const hourCounts = hours.map(h => {
    const found = hourlyData.find(d => d.hour == h);
    return found ? found.count : 0;
});

new Chart(document.getElementById('hourlyChart'), {
    type: 'bar',
    data: {
        labels: hours.map(h => `${h}h`),
        datasets: [{ label: 'Vendas', data: hourCounts, backgroundColor: tc.bar, borderColor: tc.barBorder, borderWidth: 1 }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: tc.grid }, ticks: { color: tc.tick } },
            x: { grid: { display: false }, ticks: { color: tc.tick } }
        }
    }
});
</script>

<!-- ═══════════ LUCRO REAL ═══════════ -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-top:24px;margin-bottom:24px">
    <div class="stat-card">
        <div class="stat-value">€<?= number_format($profit_data['revenue'],2,',','.') ?></div>
        <div class="stat-label">Receita (excl. IVA)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color:var(--danger,#ef4444)">€<?= number_format($profit_data['cost'],2,',','.') ?></div>
        <div class="stat-label">Custo das Mercadorias</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color:var(--success,#10b981)">€<?= number_format($profit_data['profit'],2,',','.') ?></div>
        <div class="stat-label">Lucro Bruto · <span><?= number_format($margin_pct,1) ?>% margem</span></div>
    </div>
</div>

<div class="card" style="margin-bottom:24px">
    <div class="card-header"><h3 class="card-title"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg> Evolução do Lucro Bruto</h3></div>
    <div class="card-body"><canvas id="profitChart" height="140"></canvas></div>
</div>

<!-- ═══════════ COMPARAÇÃO LOJAS ═══════════ -->
<?php if (count($store_cmp) > 1): ?>
<div class="card" style="margin-bottom:24px">
    <div class="card-header"><h3 class="card-title"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> Comparação de Lojas</h3></div>
    <div class="card-body" style="padding:0">
        <table class="table">
            <thead><tr><th>Loja</th><th>Nº Vendas</th><th>Receita</th><th>Ticket Médio</th></tr></thead>
            <tbody>
            <?php foreach ($store_cmp as $sc): ?>
            <tr>
                <td><strong>Loja <?= $sc['store_id'] ?></strong></td>
                <td><?= $sc['sales_count'] ?></td>
                <td>€<?= number_format($sc['revenue'],2,',','.') ?></td>
                <td>€<?= number_format($sc['avg_sale'],2,',','.') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════ PREVISÃO STOCK ═══════════ -->
<?php if (!empty($reorder)): ?>
<div class="card" style="margin-bottom:24px">
    <div class="card-header"><h3 class="card-title"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Previsão de Reabastecimento (próx. 7 dias)</h3></div>
    <div class="card-body" style="padding:0">
        <table class="table">
            <thead><tr><th>Produto</th><th>Stock Atual</th><th>Mín.</th><th>Venda/Dia (média)</th><th>Dias restantes</th><th>Sugestão enc.</th></tr></thead>
            <tbody>
            <?php foreach ($reorder as $r):
                $days_left = $r['daily_avg'] > 0 ? round($r['stock'] / $r['daily_avg']) : 999;
                $suggest   = max(0, ceil($r['daily_avg'] * 30) - $r['stock']);
                $danger    = $days_left <= 3;
            ?>
            <tr style="<?= $danger ? 'color:var(--danger,#ef4444)' : '' ?>">
                <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                <td><?= $r['stock'] ?></td>
                <td><?= $r['min_stock'] ?></td>
                <td><?= number_format($r['daily_avg'],1) ?></td>
                <td <?= $danger?'style="font-weight:700"':'' ?>><?= $days_left < 999 ? $days_left . 'd' : '∞' ?></td>
                <td><?= $suggest > 0 ? $suggest . ' un.' : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
// Gráfico de lucro
const profitDailyData = <?= json_encode($profit_daily) ?>;
const tc2 = themeColors();
new Chart(document.getElementById('profitChart'), {
    type: 'bar',
    data: {
        labels: profitDailyData.map(d => { const dt=new Date(d.date); return dt.toLocaleDateString('pt-PT',{day:'2-digit',month:'short'}); }),
        datasets: [{ label: 'Lucro (€)', data: profitDailyData.map(d => d.profit), backgroundColor: tc2.bar, borderColor: tc2.barBorder, borderWidth: 1 }]
    },
    options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}},
        scales: { y:{beginAtZero:true,grid:{color:tc2.grid},ticks:{color:tc2.tick}}, x:{grid:{display:false},ticks:{color:tc2.tick}} }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>