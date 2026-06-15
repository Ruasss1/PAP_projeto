<?php
/**
 * DASHBOARD PREMIUM - PAP SUPERMERCADO
 * Design moderno inspirado em Vercel, Linear e Stripe
 */

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

session_start();
require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = db_connect();
$current_store_id = get_current_store_id();
$current_store = get_current_store();
$all_stores = get_all_stores();
$today = date('Y-m-d');

// Obter utilizador atual
require_once __DIR__ . '/includes/auth.php';
$current_user = null;
if ($auth->is_authenticated()) {
    $current_user = $auth->get_current_user();
}

// === PERÍODO SELECIONADO ===
$period = $_GET['period'] ?? 'hoje';
$valid_periods = ['hoje', 'ontem', 'semana', 'mes', 'trimestre', 'ano', 'sempre'];
if (!in_array($period, $valid_periods)) $period = 'hoje';

// Calcular datas baseado no período
switch ($period) {
    case 'hoje':
        $date_from = $today;
        $date_to = $today;
        $prev_from = date('Y-m-d', strtotime('-1 day'));
        $prev_to = $prev_from;
        $period_label = 'Hoje';
        $chart_interval = '24 HOUR';
        $chart_group = 'HOUR(sale_date)';
        $chart_format = '%H:00';
        break;
    case 'ontem':
        $date_from = date('Y-m-d', strtotime('-1 day'));
        $date_to = $date_from;
        $prev_from = date('Y-m-d', strtotime('-2 days'));
        $prev_to = $prev_from;
        $period_label = 'Ontem';
        $chart_interval = '24 HOUR';
        $chart_group = 'HOUR(sale_date)';
        $chart_format = '%H:00';
        break;
    case 'semana':
        $date_from = date('Y-m-d', strtotime('monday this week'));
        $date_to = $today;
        $prev_from = date('Y-m-d', strtotime('monday last week'));
        $prev_to = date('Y-m-d', strtotime('sunday last week'));
        $period_label = 'Esta Semana';
        $chart_group = 'DATE(sale_date)';
        $chart_format = '%d/%m';
        break;
    case 'mes':
        $date_from = date('Y-m-01');
        $date_to = $today;
        $prev_from = date('Y-m-01', strtotime('-1 month'));
        $prev_to = date('Y-m-t', strtotime('-1 month'));
        $period_label = 'Este Mês';
        $chart_group = 'DATE(sale_date)';
        $chart_format = '%d/%m';
        break;
    case 'trimestre':
        $quarter = ceil(date('n') / 3);
        $date_from = date('Y-' . str_pad(($quarter - 1) * 3 + 1, 2, '0', STR_PAD_LEFT) . '-01');
        $date_to = $today;
        $prev_quarter_start = date('Y-m-d', strtotime($date_from . ' -3 months'));
        $prev_from = $prev_quarter_start;
        $prev_to = date('Y-m-d', strtotime($date_from . ' -1 day'));
        $period_label = 'Este Trimestre (Q' . $quarter . ')';
        $chart_group = 'YEARWEEK(sale_date, 1)';
        $chart_format = 'Sem %v';
        break;
    case 'ano':
        $date_from = date('Y-01-01');
        $date_to = $today;
        $prev_from = date('Y-01-01', strtotime('-1 year'));
        $prev_to = date('Y-12-31', strtotime('-1 year'));
        $period_label = 'Este Ano (' . date('Y') . ')';
        $chart_group = 'DATE_FORMAT(sale_date, "%Y-%m")';
        $chart_format = '%b';
        break;
    case 'sempre':
        $date_from = '2000-01-01';
        $date_to = $today;
        $prev_from = null;
        $prev_to = null;
        $period_label = 'Todo o Período';
        $chart_group = 'DATE_FORMAT(sale_date, "%Y-%m")';
        $chart_format = '%b %Y';
        break;
}

// === DADOS DO DASHBOARD (baseados no período) ===

// Vendas do período
$stmt = $pdo->prepare('SELECT COALESCE(SUM(total), 0) FROM sales WHERE DATE(sale_date) >= ? AND DATE(sale_date) <= ? AND store_id = ?');
$stmt->execute([$date_from, $date_to, $current_store_id]);
$period_sales = $stmt->fetchColumn();

// Vendas do período anterior (para comparação)
$prev_sales = 0;
if ($prev_from !== null) {
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(total), 0) FROM sales WHERE DATE(sale_date) >= ? AND DATE(sale_date) <= ? AND store_id = ?');
    $stmt->execute([$prev_from, $prev_to, $current_store_id]);
    $prev_sales = $stmt->fetchColumn();
}

// Variação percentual
$sales_change = $prev_sales > 0 ? (($period_sales - $prev_sales) / $prev_sales) * 100 : 0;

// Transações do período
$stmt = $pdo->prepare('SELECT COUNT(*) FROM sales WHERE DATE(sale_date) >= ? AND DATE(sale_date) <= ? AND store_id = ?');
$stmt->execute([$date_from, $date_to, $current_store_id]);
$period_transactions = $stmt->fetchColumn();

// Transações período anterior
$prev_transactions = 0;
if ($prev_from !== null) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sales WHERE DATE(sale_date) >= ? AND DATE(sale_date) <= ? AND store_id = ?');
    $stmt->execute([$prev_from, $prev_to, $current_store_id]);
    $prev_transactions = $stmt->fetchColumn();
}
$transactions_change = $prev_transactions > 0 ? (($period_transactions - $prev_transactions) / $prev_transactions) * 100 : 0;

// Ticket médio
$ticket_medio = $period_transactions > 0 ? $period_sales / $period_transactions : 0;
$prev_ticket = ($prev_transactions > 0 && $prev_sales > 0) ? $prev_sales / $prev_transactions : 0;
$ticket_change = $prev_ticket > 0 ? (($ticket_medio - $prev_ticket) / $prev_ticket) * 100 : 0;

// Total de clientes
$stmt = $pdo->prepare('SELECT COUNT(*) FROM customers');
$total_customers = $stmt->execute() ? $stmt->fetchColumn() : 0;

// Total de produtos
$stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE store_id = ? AND active = 1');
$stmt->execute([$current_store_id]);
$total_products = $stmt->fetchColumn();

// Produtos em stock baixo
$stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE store_id = ? AND stock <= 10 AND active = 1');
$stmt->execute([$current_store_id]);
$low_stock = $stmt->fetchColumn();

// Funcionários presentes hoje
$present_today = $pdo->prepare('SELECT COUNT(*) FROM attendance WHERE store_id = ? AND clock_out IS NULL AND DATE(clock_in) = CURDATE()');
$present_today->execute([$current_store_id]);
$present_today = $present_today->fetchColumn();

// Devoluções hoje
$returns_today = $pdo->prepare('SELECT COUNT(*), COALESCE(SUM(total_refund),0) FROM returns WHERE DATE(created_at) = CURDATE()');
$returns_today->execute();
$returns_today = $returns_today->fetch(PDO::FETCH_NUM);

// Produtos a expirar em 7 dias
$expiring_soon = $pdo->prepare('SELECT COUNT(*) FROM products WHERE store_id = ? AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND active = 1');
$expiring_soon->execute([$current_store_id]);
$expiring_soon = $expiring_soon->fetchColumn();

// Top 5 produtos (no período)
$stmt = $pdo->prepare('
    SELECT p.name, p.barcode, SUM(si.quantity) as qty, SUM(si.quantity * si.price) as value
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    JOIN sales s ON si.sale_id = s.id
    WHERE s.store_id = ? AND DATE(s.sale_date) >= ? AND DATE(s.sale_date) <= ?
    GROUP BY p.id ORDER BY qty DESC LIMIT 5
');
$stmt->execute([$current_store_id, $date_from, $date_to]);
$top_products = $stmt->fetchAll();

// Dados do gráfico principal (agrupados por período)
if ($period === 'hoje' || $period === 'ontem') {
    $stmt = $pdo->prepare('
        SELECT HOUR(sale_date) as label, SUM(total) as total, COUNT(*) as count
        FROM sales WHERE DATE(sale_date) = ? AND store_id = ?
        GROUP BY HOUR(sale_date) ORDER BY HOUR(sale_date) ASC
    ');
    $stmt->execute([$date_from, $current_store_id]);
} elseif ($period === 'trimestre') {
    $stmt = $pdo->prepare('
        SELECT YEARWEEK(sale_date, 1) as label, SUM(total) as total, COUNT(*) as count
        FROM sales WHERE DATE(sale_date) >= ? AND DATE(sale_date) <= ? AND store_id = ?
        GROUP BY YEARWEEK(sale_date, 1) ORDER BY YEARWEEK(sale_date, 1) ASC
    ');
    $stmt->execute([$date_from, $date_to, $current_store_id]);
} elseif ($period === 'ano' || $period === 'sempre') {
    $stmt = $pdo->prepare('
        SELECT DATE_FORMAT(sale_date, "%Y-%m") as label, SUM(total) as total, COUNT(*) as count
        FROM sales WHERE DATE(sale_date) >= ? AND DATE(sale_date) <= ? AND store_id = ?
        GROUP BY DATE_FORMAT(sale_date, "%Y-%m") ORDER BY DATE_FORMAT(sale_date, "%Y-%m") ASC
    ');
    $stmt->execute([$date_from, $date_to, $current_store_id]);
} else {
    $stmt = $pdo->prepare('
        SELECT DATE(sale_date) as label, SUM(total) as total, COUNT(*) as count
        FROM sales WHERE DATE(sale_date) >= ? AND DATE(sale_date) <= ? AND store_id = ?
        GROUP BY DATE(sale_date) ORDER BY DATE(sale_date) ASC
    ');
    $stmt->execute([$date_from, $date_to, $current_store_id]);
}
$chart_data = $stmt->fetchAll();

// Preencher horas vazias para gráfico de hoje/ontem
if ($period === 'hoje' || $period === 'ontem') {
    $hours_data = [];
    $existing = array_column($chart_data, null, 'label');
    $max_hour = ($period === 'hoje') ? (int)date('H') : 23;
    for ($h = 8; $h <= $max_hour; $h++) {
        $hours_data[] = [
            'label' => str_pad($h, 2, '0', STR_PAD_LEFT) . ':00',
            'total' => isset($existing[$h]) ? $existing[$h]['total'] : 0,
            'count' => isset($existing[$h]) ? $existing[$h]['count'] : 0
        ];
    }
    $chart_data = $hours_data;
}

// Vendas por mês (últimos 6 meses) - para gráfico secundário
$stmt = $pdo->prepare('
    SELECT DATE_FORMAT(sale_date, "%Y-%m") as month, SUM(total) as revenue, COUNT(*) as orders
    FROM sales WHERE sale_date >= DATE_SUB(?, INTERVAL 6 MONTH) AND store_id = ?
    GROUP BY DATE_FORMAT(sale_date, "%Y-%m") ORDER BY month ASC
');
$stmt->execute([$today, $current_store_id]);
$monthly_data = $stmt->fetchAll();

// Últimas vendas (do período)
$stmt = $pdo->prepare('
    SELECT s.id, s.total, s.sale_date, s.payment_method, COUNT(si.id) as items
    FROM sales s LEFT JOIN sale_items si ON s.id = si.sale_id
    WHERE s.store_id = ? AND DATE(s.sale_date) >= ? AND DATE(s.sale_date) <= ?
    GROUP BY s.id, s.total, s.sale_date, s.payment_method ORDER BY s.sale_date DESC LIMIT 8
');
$stmt->execute([$current_store_id, $date_from, $date_to]);
$recent_sales = $stmt->fetchAll();

// Métodos de pagamento (no período)
$stmt = $pdo->prepare('
    SELECT COALESCE(payment_method, "N/A") as method, COUNT(*) as count, SUM(total) as total
    FROM sales WHERE DATE(sale_date) >= ? AND DATE(sale_date) <= ? AND store_id = ?
    GROUP BY payment_method ORDER BY total DESC
');
$stmt->execute([$date_from, $date_to, $current_store_id]);
$payment_methods = $stmt->fetchAll();

// === CASHFLOW ===
$stmt_receitas = $pdo->prepare('SELECT COALESCE(SUM(total),0) FROM sales WHERE store_id=?');
$stmt_receitas->execute([$current_store_id]);
$total_receitas = (float)$stmt_receitas->fetchColumn();

$total_despesas_encomendas = (float)$pdo->query('SELECT COALESCE(SUM(total_cost),0) FROM orders WHERE status="delivered"')->fetchColumn();
$total_despesas_salarios   = (float)$pdo->query('SELECT COALESCE(SUM(net_salary),0) FROM payroll WHERE status="paid"')->fetchColumn();
$total_despesas   = $total_despesas_encomendas + $total_despesas_salarios;
$saldo_cashflow   = $total_receitas - $total_despesas;

// === DADOS EXTRAS PARA DASHBOARD REFINADO ===

// Distribuição de stock (disponível / baixo / esgotado)
$stock_dist = ['total' => (int)$total_products, 'disponivel' => max(0, (int)$total_products - (int)$low_stock), 'baixo' => (int)$low_stock, 'esgotado' => 0];
try {
    $stmt = $pdo->prepare('SELECT SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as esgotado FROM products WHERE store_id = ? AND active = 1');
    $stmt->execute([$current_store_id]);
    $stock_dist['esgotado'] = (int)$stmt->fetchColumn();
    $stock_dist['baixo'] = max(0, (int)$low_stock - $stock_dist['esgotado']);
    $stock_dist['disponivel'] = max(0, (int)$total_products - (int)$low_stock);
} catch (\Throwable $e) {}

// Novos clientes por dia (últimos 7 dias) para sparkline
$customer_sparkline_raw = [];
try {
    $stmt = $pdo->prepare('
        SELECT DATE(created_at) as dia, COUNT(*) as total
        FROM customers
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at) ORDER BY dia ASC
    ');
    $stmt->execute();
    $customer_sparkline_raw = $stmt->fetchAll();
} catch (\Throwable $e) {}

// Preencher os 7 dias mesmo que sem dados
$customer_sparkline = [];
$sparkline_map = array_column($customer_sparkline_raw, 'total', 'dia');
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $customer_sparkline[] = ['dia' => $day, 'total' => (int)($sparkline_map[$day] ?? 0)];
}
$new_customers_week = array_sum(array_column($customer_sparkline, 'total'));

// Clientes fidelizados
$fidel_customers = 0;
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE loyalty_points > 0');
    $stmt->execute();
    $fidel_customers = (int)$stmt->fetchColumn();
} catch (\Throwable $e) {}
$fidel_pct = $total_customers > 0 ? round($fidel_customers / $total_customers * 100) : 0;

// Total métodos de pagamento para donut
$pm_total_amount = array_sum(array_column($payment_methods, 'total'));

// Última venda
$last_sale = !empty($recent_sales) ? $recent_sales[0] : null;

// Texto de insight gerado a partir dos dados
if ($low_stock > 0 && $total_products > 0) {
    $pct_baixo = round($low_stock / $total_products * 100, 1);
    $insight_bold = "{$low_stock} produtos";
    $insight_rest = " em stock baixo ({$pct_baixo}% do catálogo). Verifica o inventário e coloca encomendas a tempo para evitar ruturas.";
} elseif ($sales_change > 5) {
    $insight_bold = '+' . round($sales_change, 1) . '% nas vendas';
    $insight_rest = " face ao período anterior. O desempenho está positivo — considera expandir as promoções dos produtos mais vendidos.";
} elseif ($sales_change < -5) {
    $insight_bold = round($sales_change, 1) . '% nas vendas';
    $insight_rest = " face ao período anterior. Revê as promoções ativas e verifica se há problemas de stock que possam estar a afetar as vendas.";
} elseif ($expiring_soon > 0) {
    $insight_bold = "{$expiring_soon} produtos";
    $insight_rest = " com validade a expirar nos próximos 7 dias. Aplica descontos ou remove do expositor para evitar desperdício.";
} else {
    $insight_bold = 'Tudo em ordem';
    $insight_rest = ' — stock normalizado, sem alertas críticos e vendas estáveis neste período. Continua o bom trabalho!';
}

$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<style>
        /* DASHBOARD SPECIFIC */
        .content-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 24px; }
        .content-title { font-size: 22px; font-weight: 700; color: var(--text-primary); margin-bottom: 4px; }
        .content-subtitle { font-size: 13px; color: var(--text-muted); }
/* Stats */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 28px; }
        .stat-card { background: var(--bg-card, var(--bg-secondary)); border: 1px solid var(--border); border-radius: 12px; padding: 22px; transition: all 0.18s ease; }
        .stat-card:hover { border-color: var(--border-light); transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,0.12); }
        .stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
        .stat-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0; background: var(--bg-tertiary); }
        .stat-icon.blue, .stat-icon.green, .stat-icon.purple, .stat-icon.orange, .stat-icon.red { background: var(--bg-tertiary); }
        .stat-change { display: inline-flex; align-items: center; gap: 3px; font-size: 12px; font-weight: 600; padding: 3px 8px; border-radius: 6px; }
        .stat-change.positive { background: var(--success-subtle, rgba(34,197,94,0.1)); color: var(--success); }
        .stat-change.negative { background: var(--danger-subtle, rgba(239,68,68,0.1)); color: var(--danger); }
        .stat-value { font-size: 28px; font-weight: 800; letter-spacing: -0.03em; margin-bottom: 2px; font-variant-numeric: tabular-nums; }
        .stat-label { color: var(--text-muted); font-size: 13px; }

        /* Charts */
        .charts-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 24px; }
        .card { background: var(--bg-card, var(--bg-secondary)); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        .card:hover { border-color: var(--border-light); }
        .card-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid var(--border); }
        .card-title { font-size: 15px; font-weight: 600; letter-spacing: -0.01em; }
        .card-body { padding: 22px; }
        .card-body.no-padding { padding: 0; }
        .chart-container { position: relative; height: 280px; }

        /* Products */
        .product-list { display: flex; flex-direction: column; }
        .product-item { display: flex; align-items: center; gap: 14px; padding: 14px 22px; border-bottom: 1px solid var(--border); transition: background 0.18s ease; }
        .product-item:last-child { border-bottom: none; }
        .product-item:hover { background: var(--bg-tertiary); }
        .product-rank { width: 28px; height: 28px; background: var(--bg-tertiary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: var(--text-muted); }
        .product-rank.top { background: var(--text-primary); color: var(--bg-primary); }
        .product-info { flex: 1; min-width: 0; }
        .product-name { font-weight: 600; font-size: 14px; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .product-sku { font-size: 12px; color: var(--text-muted); }
        .product-stats { text-align: right; }
        .product-qty { font-weight: 700; font-size: 14px; }
        .product-value { font-size: 12px; color: var(--text-muted); }

        /* Tables */
        .table-container { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align: left; padding: 12px 22px; font-size: 11.5px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.06em; background: var(--bg-tertiary); border-bottom: 1px solid var(--border); }
        .table td { padding: 14px 22px; border-bottom: 1px solid var(--border); font-size: 14px; }
        .table tr:last-child td { border-bottom: none; }
        .table tr:hover td { background: var(--bg-tertiary); }
        .badge { display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .badge-success { background: var(--success-subtle, rgba(34,197,94,0.1)); color: var(--success); }
        .badge-warning { background: var(--warning-subtle, rgba(245,158,11,0.1)); color: var(--warning); }
        .badge-info { background: var(--bg-tertiary); color: var(--text-secondary); }

        /* Quick Actions */
        .quick-grid { display: grid; gap: 12px; }
        .quick-action { background: var(--bg-primary); border: 1px solid var(--border); border-radius: 10px; padding: 20px; text-align: center; text-decoration: none; color: var(--text-primary); transition: all 0.18s ease; }
        .quick-action:hover { border-color: var(--border-light); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .quick-icon { width: 44px; height: 44px; margin: 0 auto 12px; background: var(--bg-tertiary); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
        .quick-title { font-weight: 600; margin-bottom: 2px; font-size: 14px; }
        .quick-desc { font-size: 12px; color: var(--text-muted); }

        /* Period Selector */
        .period-selector { display: flex; align-items: center; gap: 2px; background: var(--bg-tertiary); border-radius: 10px; padding: 3px; }
        .period-btn { padding: 7px 14px; border-radius: 8px; font-size: 13px; font-weight: 500; color: var(--text-muted); background: transparent; border: none; cursor: pointer; transition: all 0.18s ease; white-space: nowrap; text-decoration: none; }
        .period-btn:hover { color: var(--text-primary); background: var(--bg-hover); }
        .period-btn.active { background: var(--bg-card, var(--bg-primary)); color: var(--text-primary); font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .period-info { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--text-muted); margin-left: 12px; }
        .period-info svg { width: 14px; height: 14px; opacity: 0.5; }

        /* Payment bars */
        .payment-bar { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border); }
        .payment-bar:last-child { border-bottom: none; }
        .payment-bar-label { width: 100px; font-size: 13px; font-weight: 500; color: var(--text-secondary); }
        .payment-bar-track { flex: 1; height: 6px; background: var(--bg-tertiary); border-radius: 100px; overflow: hidden; }
        .payment-bar-fill { height: 100%; border-radius: 100px; transition: width 0.6s ease; }
        .payment-bar-value { font-size: 13px; font-weight: 600; width: 90px; text-align: right; }

        /* Money */
        .money-positive { color: var(--success) !important; }
        .money-negative { color: var(--danger) !important; }

        /* Animations */
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.35s ease-out forwards; }
        .fade-in-delay-1 { animation-delay: 0.05s; opacity: 0; }
        .fade-in-delay-2 { animation-delay: 0.1s; opacity: 0; }
        .fade-in-delay-3 { animation-delay: 0.15s; opacity: 0; }
        .fade-in-delay-4 { animation-delay: 0.2s; opacity: 0; }

        /* Responsive */
        @media (max-width: 1280px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } .charts-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .sidebar { display: none; } .main { margin-left: 0; } .stats-grid { grid-template-columns: 1fr; } .content { padding: 16px; } }
    
</style>


                <!-- Content Header -->
                <div class="content-header fade-in">
                    <div>
                        <h2 class="content-title">
                            <?php 
                                $hour = date('H');
                                $greeting = $hour < 12 ? 'Bom dia' : ($hour < 19 ? 'Boa tarde' : 'Boa noite');
                            ?>
                            <?= $greeting ?>, <?= $current_user ? explode(' ', $current_user['name'])[0] : 'Admin' ?>! 
                        </h2>
                        <p class="content-subtitle">
                            <span id="live-clock" style="font-variant-numeric: tabular-nums;"></span> · <?= date('d \d\e F \d\e Y') ?>
                            <?php if ($low_stock > 0): ?>
                            <span style="margin-left: 12px; color: var(--warning); font-weight: 600;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> <?= $low_stock ?> produtos em stock baixo</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <a href="/CAIXA/" class="btn btn-secondary" style="padding: 8px 16px; font-size: 13px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg> Abrir Caixa</a>
                    </div>
                </div>

                <!-- Period Selector -->
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;" class="fade-in">
                    <div class="period-selector">
                        <a href="?period=hoje" class="period-btn <?= $period === 'hoje' ? 'active' : '' ?>">Hoje</a>
                        <a href="?period=ontem" class="period-btn <?= $period === 'ontem' ? 'active' : '' ?>">Ontem</a>
                        <a href="?period=semana" class="period-btn <?= $period === 'semana' ? 'active' : '' ?>">Semana</a>
                        <a href="?period=mes" class="period-btn <?= $period === 'mes' ? 'active' : '' ?>">Mês</a>
                        <a href="?period=trimestre" class="period-btn <?= $period === 'trimestre' ? 'active' : '' ?>">Trimestre</a>
                        <a href="?period=ano" class="period-btn <?= $period === 'ano' ? 'active' : '' ?>">Ano</a>
                        <a href="?period=sempre" class="period-btn <?= $period === 'sempre' ? 'active' : '' ?>">Sempre</a>
                    </div>
                    <div class="period-info">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <?= date('d/m/Y', strtotime($date_from)) ?><?= $date_from !== $date_to ? ' — ' . date('d/m/Y', strtotime($date_to)) : '' ?>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card fade-in fade-in-delay-1">
                        <div class="stat-header">
                            <div class="stat-icon blue"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                            <?php if ($sales_change != 0): ?>
                            <div class="stat-change <?= $sales_change >= 0 ? 'positive' : 'negative' ?>">
                                <?= $sales_change >= 0 ? '↑' : '↓' ?> <?= abs(round($sales_change, 1)) ?>%
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="stat-value money-positive">€<?= number_format($period_sales, 2, ',', '.') ?></div>
                        <div class="stat-label">Receita · <?= $period_label ?></div>
                    </div>

                    <div class="stat-card fade-in fade-in-delay-2">
                        <div class="stat-header">
                            <div class="stat-icon green"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></div>
                            <?php if ($transactions_change != 0): ?>
                            <div class="stat-change <?= $transactions_change >= 0 ? 'positive' : 'negative' ?>">
                                <?= $transactions_change >= 0 ? '↑' : '↓' ?> <?= abs(round($transactions_change, 1)) ?>%
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="stat-value"><?= $period_transactions ?></div>
                        <div class="stat-label">Transações · <?= $period_label ?></div>
                    </div>

                    <div class="stat-card fade-in fade-in-delay-3">
                        <div class="stat-header">
                            <div class="stat-icon purple"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg></div>
                            <?php if ($ticket_change != 0): ?>
                            <div class="stat-change <?= $ticket_change >= 0 ? 'positive' : 'negative' ?>">
                                <?= $ticket_change >= 0 ? '↑' : '↓' ?> <?= abs(round($ticket_change, 1)) ?>%
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="stat-value">€<?= number_format($ticket_medio, 2, ',', '.') ?></div>
                        <div class="stat-label">Ticket Médio · <?= $period_label ?></div>
                    </div>

                    <div class="stat-card fade-in fade-in-delay-4">
                        <div class="stat-header">
                            <div class="stat-icon <?= $low_stock > 0 ? 'red' : 'green' ?>"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg></div>
                            <?php if ($low_stock > 0): ?>
                            <div class="stat-change negative">Atenção</div>
                            <?php endif; ?>
                        </div>
                        <div class="stat-value"><?= $total_products ?></div>
                        <div class="stat-label">Produtos (<?= $low_stock ?> em stock baixo)</div>
                    </div>

                    <div class="stat-card fade-in" style="animation-delay:.25s;opacity:0">
                        <div class="stat-header">
                            <div class="stat-icon green"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg></div>
                            <?php if ($present_today > 0): ?>
                            <div class="stat-change positive">● Ativo</div>
                            <?php endif; ?>
                        </div>
                        <div class="stat-value"><?= $present_today ?></div>
                        <div class="stat-label">Funcionários presentes hoje</div>
                    </div>

                    <?php if ($expiring_soon > 0): ?>
                    <div class="stat-card fade-in" style="animation-delay:.3s;opacity:0">
                        <div class="stat-header">
                            <div class="stat-icon orange"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
                            <div class="stat-change negative">Urgente</div>
                        </div>
                        <div class="stat-value"><?= $expiring_soon ?></div>
                        <div class="stat-label">Produtos a expirar em 7 dias</div>
                    </div>
                    <?php endif; ?>

                    <div class="stat-card fade-in" style="animation-delay:.35s;opacity:0">
                        <div class="stat-header">
                            <div class="stat-icon <?= $saldo_cashflow >= 0 ? 'green' : 'red' ?>">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                            </div>
                            <div class="stat-change <?= $saldo_cashflow >= 0 ? 'positive' : 'negative' ?>">
                                <?= $saldo_cashflow >= 0 ? '↑ Positivo' : '↓ Negativo' ?>
                            </div>
                        </div>
                        <div class="stat-value <?= $saldo_cashflow >= 0 ? 'money-positive' : '' ?>" style="<?= $saldo_cashflow < 0 ? 'color:var(--danger)' : '' ?>">
                            €<?= number_format(abs($saldo_cashflow), 2, ',', '.') ?>
                        </div>
                        <div class="stat-label">Saldo · Receitas − Despesas</div>
                    </div>
                </div>

                <!-- Charts Grid -->
                <div class="charts-grid">
                    <div class="card fade-in">
                        <div class="card-header">
                            <h3 class="card-title">Receita · <?= $period_label ?></h3>
                            <span style="font-size: 12px; color: var(--text-muted);"><?= date('d/m', strtotime($date_from)) ?><?= $date_from !== $date_to ? ' — ' . date('d/m', strtotime($date_to)) : '' ?></span>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="mainChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="card fade-in">
                        <div class="card-header">
                            <h3 class="card-title">Top Produtos</h3>
                            <span style="font-size: 12px; color: var(--text-muted);"><?= $period_label ?></span>
                        </div>
                        <div class="card-body no-padding">
                            <div class="product-list">
                                <?php if (empty($top_products)): ?>
                                <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                                    Nenhuma venda registada
                                </div>
                                <?php else: ?>
                                <?php foreach ($top_products as $i => $product): ?>
                                <div class="product-item">
                                    <div class="product-rank <?= $i === 0 ? 'top' : '' ?>"><?= $i + 1 ?></div>
                                    <div class="product-info">
                                        <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                                        <div class="product-sku"><?= htmlspecialchars($product['barcode'] ?? 'N/A') ?></div>
                                    </div>
                                    <div class="product-stats">
                                        <div class="product-qty"><?= $product['qty'] ?> un.</div>
                                        <div class="product-value money-positive">€<?= number_format($product['value'], 2) ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Revenue + Sales + Quick Actions -->
                <div class="charts-grid">
                    <div class="card fade-in">
                        <div class="card-header">
                            <h3 class="card-title">Vendas Recentes</h3>
                            <a href="/modules/recibos.php" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">Ver Todas →</a>
                        </div>
                        <div class="card-body no-padding">
                            <div class="table-container">
                                <table class="table">
                                    <thead><tr><th>ID</th><th>Data</th><th>Itens</th><th>Pagamento</th><th>Total</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($recent_sales)): ?>
                                        <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:40px;">Nenhuma venda neste período</td></tr>
                                        <?php else: ?>
                                        <?php foreach ($recent_sales as $sale): ?>
                                        <tr>
                                            <td><strong>#<?= $sale['id'] ?></strong></td>
                                            <td><?= date('d/m H:i', strtotime($sale['sale_date'])) ?></td>
                                            <td><?= $sale['items'] ?> itens</td>
                                            <td><span class="badge badge-info"><?= $sale['payment_method'] ?? 'N/A' ?></span></td>
                                            <td><strong class="money-positive">€<?= number_format($sale['total'], 2) ?></strong></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card fade-in">
                        <div class="card-header">
                            <h3 class="card-title">Métodos de Pagamento</h3>
                            <span style="font-size: 12px; color: var(--text-muted);"><?= $period_label ?></span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($payment_methods)): ?>
                            <div style="text-align: center; color: var(--text-muted); padding: 30px;">Sem dados</div>
                            <?php else: ?>
                            <?php 
                                $max_payment = max(array_column($payment_methods, 'total'));
                                $pm_colors = ['#a1a1aa', '#71717a', '#52525b', '#3f3f46', '#a3a3a3', '#78716c'];
                            ?>
                            <?php foreach ($payment_methods as $i => $pm): ?>
                            <div class="payment-bar">
                                <div class="payment-bar-label"><?= htmlspecialchars($pm['method']) ?></div>
                                <div class="payment-bar-track">
                                    <div class="payment-bar-fill" style="width: <?= $max_payment > 0 ? round($pm['total'] / $max_payment * 100) : 0 ?>%; background: <?= $pm_colors[$i % count($pm_colors)] ?>;"></div>
                                </div>
                                <div class="payment-bar-value">€<?= number_format($pm['total'], 2, ',', '.') ?></div>
                            </div>
                            <?php endforeach; ?>
                            <div style="margin-top: 12px; font-size: 12px; color: var(--text-muted); text-align: center;">
                                <?= array_sum(array_column($payment_methods, 'count')) ?> transações no total
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="charts-grid">
                    <div class="card fade-in">
                        <div class="card-header">
                            <h3 class="card-title">Receita Mensal</h3>
                            <span style="font-size: 12px; color: var(--text-muted);">Últimos 6 meses</span>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="card fade-in">
                        <div class="card-header">
                            <h3 class="card-title">Ações Rápidas</h3>
                        </div>
                        <div class="card-body">
                            <div class="quick-grid" style="grid-template-columns: repeat(2, 1fr);">
                                <a href="/modules/produtos.php" class="quick-action">
                                    <div class="quick-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></div>
                                    <div class="quick-title">Produtos</div>
                                    <div class="quick-desc">Gerir catálogo</div>
                                </a>
                                <a href="/modules/stock.php" class="quick-action">
                                    <div class="quick-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
                                    <div class="quick-title">Stock</div>
                                    <div class="quick-desc">Ver inventário</div>
                                </a>
                                <a href="/modules/relatorios.php" class="quick-action">
                                    <div class="quick-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></div>
                                    <div class="quick-title">Relatórios</div>
                                    <div class="quick-desc">Análises</div>
                                </a>
                                <a href="/modules/devolucoes.php" class="quick-action">
                                    <div class="quick-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 10 4 15 9 20"/><path d="M20 4v7a4 4 0 0 1-4 4H4"/></svg></div>
                                    <div class="quick-title">Devoluções</div>
                                    <div class="quick-desc">Processar reembolso</div>
                                </a>
                                <a href="/modules/ponto.php" class="quick-action">
                                    <div class="quick-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                                    <div class="quick-title">Ponto</div>
                                    <div class="quick-desc">Registar entrada/saída</div>
                                </a>
                                <a href="/modules/validades.php" class="quick-action">
                                    <div class="quick-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                                    <div class="quick-title">Validades</div>
                                    <div class="quick-desc">Produtos a expirar</div>
                                </a>
                                <a href="/modules/configuracoes.php" class="quick-action">
                                    <div class="quick-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></div>
                                    <div class="quick-title">Configurações</div>
                                    <div class="quick-desc">Definições sistema</div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================================================ -->
                <!-- BLOCO REFINADO — 2 Colunas                        -->
                <!-- ================================================ -->
                <div class="dash-refined-grid">

                    <!-- COLUNA ESQUERDA -->
                    <div>
                        <!-- Mini Indicadores (3x % vs período anterior) -->
                        <div class="mini-indicators">
                            <?php
                            $mini_items = [
                                ['label' => 'Receita · ' . $period_label, 'value' => '€' . number_format($period_sales, 0, ',', '.'), 'change' => $sales_change],
                                ['label' => 'Transações · ' . $period_label, 'value' => $period_transactions, 'change' => $transactions_change],
                                ['label' => 'Ticket Médio · ' . $period_label, 'value' => '€' . number_format($ticket_medio, 2, ',', '.'), 'change' => $ticket_change],
                            ];
                            foreach ($mini_items as $mi):
                                $dir = $mi['change'] > 0 ? 'up' : ($mi['change'] < 0 ? 'down' : 'flat');
                                $arrow = $mi['change'] > 0 ? '↑' : ($mi['change'] < 0 ? '↓' : '→');
                            ?>
                            <div class="mini-indicator">
                                <div class="mini-indicator-header">
                                    <span class="mini-indicator-arrow <?= $dir ?>"><?= $arrow ?></span>
                                    <?php if ($mi['change'] != 0): ?>
                                    <span class="mini-indicator-pct <?= $dir ?>"><?= abs(round($mi['change'], 1)) ?>%</span>
                                    <?php else: ?>
                                    <span class="mini-indicator-pct flat">—</span>
                                    <?php endif; ?>
                                    <span style="font-size:11px;color:var(--text-muted);margin-left:4px;">vs anterior</span>
                                </div>
                                <div class="mini-indicator-value"><?= $mi['value'] ?></div>
                                <div class="mini-indicator-label"><?= $mi['label'] ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Card Grande: Faturação Mensal + Gráfico de Área -->
                        <div class="card" style="margin-bottom:16px;">
                            <div class="card-header">
                                <div>
                                    <div class="dash-hero-value money-positive">€<?= number_format($period_sales, 0, ',', '.') ?></div>
                                    <div style="margin-top:4px;font-size:13px;color:var(--text-muted);">Faturação · <?= $period_label ?></div>
                                </div>
                                <?php if ($sales_change != 0): ?>
                                <div class="dash-hero-badge <?= $sales_change >= 0 ? 'up' : 'down' ?>">
                                    <?= $sales_change >= 0 ? '↑' : '↓' ?> <?= abs(round($sales_change, 1)) ?>% nos últimos <?= $period_label ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="height:200px;">
                                    <canvas id="refinedAreaChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- 2 Cards lado a lado: Insights + Distribuição de Stock -->
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                            <!-- Insights -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:-2px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                        Insights
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <p class="insights-text">
                                        <span class="insights-bold"><?= htmlspecialchars($insight_bold) ?></span><?= htmlspecialchars($insight_rest) ?>
                                    </p>
                                    <?php if ($low_stock > 0): ?>
                                    <a href="/modules/stock.php" class="btn btn-secondary" style="margin-top:14px;font-size:12px;padding:6px 12px;">Ver Stock →</a>
                                    <?php elseif ($expiring_soon > 0): ?>
                                    <a href="/modules/validades.php" class="btn btn-secondary" style="margin-top:14px;font-size:12px;padding:6px 12px;">Ver Validades →</a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Distribuição de Stock -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Distribuição de Stock</h3>
                                    <span style="font-size:13px;font-weight:700;color:var(--text-primary);"><?= $stock_dist['total'] ?> produtos</span>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $t = $stock_dist['total'] > 0 ? $stock_dist['total'] : 1;
                                    $pct_disp  = round($stock_dist['disponivel'] / $t * 100);
                                    $pct_baixo_d = round($stock_dist['baixo'] / $t * 100);
                                    $pct_esgo  = round($stock_dist['esgotado'] / $t * 100);
                                    ?>
                                    <div class="dist-row">
                                        <div class="dist-header">
                                            <span>Disponível (<?= $pct_disp ?>%)</span>
                                            <span><?= $stock_dist['disponivel'] ?></span>
                                        </div>
                                        <div class="dist-bar-track">
                                            <div class="dist-bar-seg" style="width:<?= $pct_disp ?>%;background:var(--text-secondary);"></div>
                                        </div>
                                    </div>
                                    <div class="dist-row">
                                        <div class="dist-header">
                                            <span>Stock Baixo (<?= $pct_baixo_d ?>%)</span>
                                            <span><?= $stock_dist['baixo'] ?></span>
                                        </div>
                                        <div class="dist-bar-track">
                                            <div class="dist-bar-seg" style="width:<?= $pct_baixo_d ?>%;background:var(--warning);"></div>
                                        </div>
                                    </div>
                                    <div class="dist-row" style="margin-bottom:0;">
                                        <div class="dist-header">
                                            <span>Esgotado (<?= $pct_esgo ?>%)</span>
                                            <span><?= $stock_dist['esgotado'] ?></span>
                                        </div>
                                        <div class="dist-bar-track">
                                            <div class="dist-bar-seg" style="width:<?= $pct_esgo ?>%;background:var(--danger);"></div>
                                        </div>
                                    </div>
                                    <div class="dist-legend">
                                        <div class="dist-legend-item"><div class="dist-legend-dot" style="background:var(--text-secondary);"></div> Disponível</div>
                                        <div class="dist-legend-item"><div class="dist-legend-dot" style="background:var(--warning);"></div> Stock Baixo</div>
                                        <div class="dist-legend-item"><div class="dist-legend-dot" style="background:var(--danger);"></div> Esgotado</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- /coluna esquerda -->

                    <!-- COLUNA DIREITA -->
                    <div style="display:flex;flex-direction:column;gap:16px;">

                        <!-- Donut: Métodos de Pagamento -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Pagamentos</h3>
                                <span style="font-size:12px;color:var(--text-muted);"><?= $period_label ?></span>
                            </div>
                            <div class="card-body" style="display:flex;flex-direction:column;align-items:center;gap:16px;">
                                <?php
                                $donut_colors = ['#a1a1aa','#71717a','#52525b','#3f3f46','#d4d4d8'];
                                $donut_total = $pm_total_amount > 0 ? $pm_total_amount : 1;
                                $circumference = 2 * M_PI * 44; // r=44
                                $offset = 0;
                                $donut_segments = [];
                                foreach ($payment_methods as $i => $pm) {
                                    $frac = $pm['total'] / $donut_total;
                                    $dash = $frac * $circumference;
                                    $donut_segments[] = [
                                        'dash' => $dash,
                                        'offset' => -$offset,
                                        'color' => $donut_colors[$i % count($donut_colors)],
                                        'method' => $pm['method'],
                                        'total' => $pm['total'],
                                        'pct' => round($frac * 100),
                                    ];
                                    $offset += $dash;
                                }
                                ?>
                                <div class="donut-svg-wrap" style="width:140px;height:140px;">
                                    <svg width="140" height="140" viewBox="0 0 100 100">
                                        <circle cx="50" cy="50" r="44" fill="none" stroke="var(--bg-tertiary)" stroke-width="10"/>
                                        <?php if (empty($donut_segments)): ?>
                                        <circle cx="50" cy="50" r="44" fill="none" stroke="var(--border-light)" stroke-width="10"/>
                                        <?php else: ?>
                                        <?php foreach ($donut_segments as $seg): ?>
                                        <circle cx="50" cy="50" r="44" fill="none"
                                            stroke="<?= $seg['color'] ?>"
                                            stroke-width="10"
                                            stroke-dasharray="<?= round($seg['dash'], 2) ?> <?= round($circumference - $seg['dash'], 2) ?>"
                                            stroke-dashoffset="<?= round($seg['offset'], 2) ?>"
                                            transform="rotate(-90 50 50)"/>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </svg>
                                    <div class="donut-icon-center">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                        <div class="donut-center-value">€<?= number_format($pm_total_amount, 0, ',', '.') ?></div>
                                        <div class="donut-center-label">total</div>
                                    </div>
                                </div>
                                <!-- Legenda -->
                                <div class="donut-legend" style="width:100%;">
                                    <?php foreach ($donut_segments as $seg): ?>
                                    <div class="donut-legend-item">
                                        <div class="donut-legend-dot" style="background:<?= $seg['color'] ?>;"></div>
                                        <span class="donut-legend-label"><?= htmlspecialchars($seg['method']) ?></span>
                                        <span class="donut-legend-val">€<?= number_format($seg['total'], 0, ',', '.') ?> <span style="color:var(--text-muted);font-weight:400;">(<?= $seg['pct'] ?>%)</span></span>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($donut_segments)): ?>
                                    <div style="text-align:center;color:var(--text-muted);font-size:12px;padding:8px 0;">Sem dados neste período</div>
                                    <?php endif; ?>
                                </div>
                                <a href="/modules/recibos.php" class="btn btn-secondary" style="width:100%;text-align:center;font-size:12px;">Ver Detalhe →</a>
                            </div>
                        </div>

                        <!-- Clientes + Sparkline -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Clientes</h3>
                                <?php $fidel_pct_show = $fidel_pct > 0 ? $fidel_pct . '% fidelizados' : ''; ?>
                                <?php if ($fidel_pct_show): ?>
                                <span class="badge badge-info"><?= $fidel_pct_show ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin-bottom:12px;">
                                    <div>
                                        <div style="font-size:32px;font-weight:800;letter-spacing:-0.03em;line-height:1;"><?= $total_customers ?></div>
                                        <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">clientes totais</div>
                                    </div>
                                    <?php if ($new_customers_week > 0): ?>
                                    <div class="dash-hero-badge up">+<?= $new_customers_week ?> esta semana</div>
                                    <?php endif; ?>
                                </div>
                                <!-- Sparkline de barras -->
                                <?php
                                $spark_max = max(1, max(array_column($customer_sparkline, 'total')));
                                ?>
                                <div class="sparkline-wrap">
                                    <?php foreach ($customer_sparkline as $idx => $sp): ?>
                                    <?php $h = max(4, round($sp['total'] / $spark_max * 100)); ?>
                                    <div class="sparkline-bar <?= $idx === count($customer_sparkline)-1 ? 'active' : '' ?>"
                                         style="height:<?= $h ?>%;"
                                         title="<?= date('d/m', strtotime($sp['dia'])) ?>: <?= $sp['total'] ?> clientes"></div>
                                    <?php endforeach; ?>
                                </div>
                                <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);margin-top:4px;">
                                    <span><?= date('d/m', strtotime('-6 days')) ?></span>
                                    <span>Hoje</span>
                                </div>
                                <a href="/modules/customers.php" style="display:block;margin-top:12px;font-size:12px;color:var(--text-muted);text-align:right;">Ver Clientes →</a>
                            </div>
                        </div>

                        <!-- Última Venda -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Última Venda</h3>
                                <?php if ($last_sale): ?>
                                <span class="badge badge-success">Concluída</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if ($last_sale): ?>
                                <div class="detail-list">
                                    <div class="detail-row">
                                        <span class="detail-key">Referência</span>
                                        <span class="detail-val">#<?= $last_sale['id'] ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-key">Data</span>
                                        <span class="detail-val"><?= date('d/m/Y H:i', strtotime($last_sale['sale_date'])) ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-key">Pagamento</span>
                                        <span class="detail-val"><?= htmlspecialchars($last_sale['payment_method'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-key">Itens</span>
                                        <span class="detail-val"><?= $last_sale['items'] ?> itens</span>
                                    </div>
                                    <div class="detail-row" style="border-bottom:none;">
                                        <span class="detail-key">Total</span>
                                        <span class="detail-val money-positive" style="font-size:16px;">€<?= number_format($last_sale['total'], 2, ',', '.') ?></span>
                                    </div>
                                </div>
                                <a href="/modules/recibos.php" class="btn btn-secondary" style="width:100%;text-align:center;font-size:12px;margin-top:14px;">Ver Detalhes →</a>
                                <?php else: ?>
                                <div style="text-align:center;color:var(--text-muted);padding:24px 0;font-size:13px;">Nenhuma venda neste período</div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div><!-- /coluna direita -->
                </div><!-- /dash-refined-grid -->

                </div>
            </div>


<script>
        // Live Clock
        function updateClock() {
            const now = new Date();
            const el = document.getElementById('live-clock');
            if (el) el.textContent = now.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
        updateClock();
        setInterval(updateClock, 1000);
        // Get theme colors dynamically
        function getThemeColors() {
            const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
            return {
                text: isDark ? '#fafafa' : '#09090b',
                textMuted: isDark ? '#71717a' : '#71717a',
                border: isDark ? '#27272a' : '#e4e4e7',
                bg: isDark ? '#18181b' : '#ffffff',
                pointBorder: isDark ? '#09090b' : '#ffffff'
            };
        }
        
        // Chart Data
        const monthlyData = <?= json_encode($monthly_data) ?>;
        const chartData = <?= json_encode($chart_data) ?>;
        const currentPeriod = '<?= $period ?>';
        const colors = getThemeColors();

        // Format raw labels from DB to display labels
        function formatLabel(raw, period) {
            if (period === 'hoje' || period === 'ontem') return raw; // already "HH:00"
            if (period === 'trimestre') {
                // raw = YEARWEEK like 202616 → "Sem 16"
                return 'Sem ' + String(raw).slice(-2).replace(/^0/, '');
            }
            if (period === 'ano' || period === 'sempre') {
                // raw = "2026-04"
                const [y, m] = raw.split('-');
                const months = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
                return months[parseInt(m) - 1] + (period === 'sempre' ? ' ' + y : '');
            }
            // semana/mes: raw = "2026-04-17"
            const d = new Date(raw + 'T00:00:00');
            if (period === 'semana') return d.toLocaleDateString('pt-PT', { weekday: 'short', day: 'numeric' });
            return d.toLocaleDateString('pt-PT', { day: '2-digit', month: '2-digit' });
        }

        // Main Period Chart
        const mainCtx = document.getElementById('mainChart').getContext('2d');
        const mainGradient = mainCtx.createLinearGradient(0, 0, 0, 280);
        
        // Use line chart for longer periods, bar for shorter
        const useLineChart = ['ano', 'sempre', 'trimestre'].includes(currentPeriod);
        
        if (useLineChart) {
            mainGradient.addColorStop(0, 'rgba(161, 161, 170, 0.3)');
            mainGradient.addColorStop(1, 'rgba(161, 161, 170, 0)');
        } else {
            mainGradient.addColorStop(0, 'rgba(161, 161, 170, 0.8)');
            mainGradient.addColorStop(1, 'rgba(161, 161, 170, 0.4)');
        }

        const mainChartConfig = useLineChart ? {
            type: 'line',
            data: {
                labels: chartData.map(item => formatLabel(item.label, currentPeriod)),
                datasets: [{
                    label: 'Receita',
                    data: chartData.map(item => parseFloat(item.total) || 0),
                    borderColor: '#a1a1aa',
                    backgroundColor: mainGradient,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#a1a1aa',
                    pointBorderColor: colors.pointBorder,
                    pointBorderWidth: 3,
                    pointRadius: 4,
                    pointHoverRadius: 7
                }]
            }
        } : {
            type: 'bar',
            data: {
                labels: chartData.map(item => formatLabel(item.label, currentPeriod)),
                datasets: [{
                    label: 'Receita',
                    data: chartData.map(item => parseFloat(item.total) || 0),
                    backgroundColor: chartData.map((item, i) => {
                        // Highlight today or current hour
                        const isHighlight = (currentPeriod === 'hoje' && item.label === new Date().getHours().toString().padStart(2, '0') + ':00');
                        return isHighlight ? 'rgba(113, 113, 122, 0.9)' : 'rgba(161, 161, 170, 0.6)';
                    }),
                    borderRadius: 6,
                    borderSkipped: false
                }]
            }
        };

        new Chart(mainCtx, {
            ...mainChartConfig,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: colors.bg,
                        titleColor: colors.text,
                        bodyColor: colors.textMuted,
                        borderColor: colors.border,
                        borderWidth: 1,
                        padding: 12,
                        displayColors: false,
                        callbacks: {
                            label: ctx => '€' + ctx.raw.toLocaleString('pt-PT', {minimumFractionDigits: 2})
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: colors.border, drawBorder: false },
                        ticks: { color: colors.textMuted, callback: v => '€' + v.toLocaleString() }
                    },
                    x: { grid: { display: false }, ticks: { color: colors.textMuted, maxRotation: 45 } }
                }
            }
        });
        
        // Revenue Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        
        const gradient = ctx.createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, 'rgba(161, 161, 170, 0.3)');
        gradient.addColorStop(1, 'rgba(161, 161, 170, 0)');

        const revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => {
                    const [year, month] = item.month.split('-');
                    const date = new Date(year, month - 1);
                    return date.toLocaleDateString('pt-PT', { month: 'short' });
                }),
                datasets: [{
                    label: 'Receita',
                    data: monthlyData.map(item => parseFloat(item.revenue) || 0),
                    borderColor: '#a1a1aa',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#a1a1aa',
                    pointBorderColor: colors.pointBorder,
                    pointBorderWidth: 3,
                    pointRadius: 5,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: colors.bg,
                        titleColor: colors.text,
                        bodyColor: colors.textMuted,
                        borderColor: colors.border,
                        borderWidth: 1,
                        padding: 12,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return '€' + context.raw.toLocaleString('pt-PT', {minimumFractionDigits: 2});
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: colors.border, drawBorder: false },
                        ticks: {
                            color: colors.textMuted,
                            callback: function(value) {
                                return '€' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: colors.textMuted }
                    }
                }
            }
        });

        // Refined Area Chart (reutiliza chartData do período atual)
        (function() {
            const ctx = document.getElementById('refinedAreaChart');
            if (!ctx) return;
            const c = ctx.getContext('2d');
            const grad = c.createLinearGradient(0, 0, 0, 200);
            grad.addColorStop(0, 'rgba(161,161,170,0.25)');
            grad.addColorStop(1, 'rgba(161,161,170,0)');
            new Chart(c, {
                type: 'line',
                data: {
                    labels: chartData.map(item => formatLabel(item.label, currentPeriod)),
                    datasets: [{
                        data: chartData.map(item => parseFloat(item.total) || 0),
                        borderColor: '#a1a1aa',
                        backgroundColor: grad,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.45,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#a1a1aa',
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: colors.bg, titleColor: colors.text,
                            bodyColor: colors.textMuted, borderColor: colors.border,
                            borderWidth: 1, padding: 10, displayColors: false,
                            callbacks: { label: ctx => '€' + ctx.raw.toLocaleString('pt-PT', {minimumFractionDigits: 2}) }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: colors.border, drawBorder: false },
                             ticks: { color: colors.textMuted, callback: v => '€' + (v >= 1000 ? (v/1000).toFixed(1)+'k' : v) }},
                        x: { grid: { display: false }, ticks: { color: colors.textMuted, maxRotation: 0, maxTicksLimit: 6 }}
                    }
                }
            });
        })();

        // Store Change
        function changeStore(storeId) {
            fetch('/api/change-store.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ store_id: storeId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) location.reload();
                else alert('Erro: ' + (data.error || 'Desconhecido'));
            })
            .catch(err => alert('Erro ao mudar loja'));
        }

        // Keyboard Shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.altKey) {
                switch(e.key) {
                    case 'v': window.location.href = '/modules/vendas.php'; break;
                    case 'p': window.location.href = '/modules/produtos.php'; break;
                    case 's': window.location.href = '/modules/stock.php'; break;
                }
            }
        });
        
        // Theme Toggle
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('pap-theme', next);
            
            // Rebuild chart with new theme colors
            if (typeof revenueChart !== 'undefined') {
                const colors = getThemeColors();
                revenueChart.options.plugins.tooltip.backgroundColor = colors.bg;
                revenueChart.options.plugins.tooltip.titleColor = colors.text;
                revenueChart.options.plugins.tooltip.bodyColor = colors.text;
                revenueChart.options.plugins.tooltip.borderColor = colors.border;
                revenueChart.options.scales.y.grid.color = colors.border;
                revenueChart.options.scales.y.ticks.color = colors.textMuted;
                revenueChart.options.scales.x.ticks.color = colors.textMuted;
                revenueChart.update();
            }
        }
    </script>
    
<?php require_once __DIR__ . '/includes/footer.php'; ?>
