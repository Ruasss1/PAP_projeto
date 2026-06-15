<?php
/**
 * Módulo de Despesas & Saídas
 * modules/despesas.php
 */
$page_title = 'Despesas & Saídas';
require_once __DIR__ . '/../includes/header.php';

$pdo = db_connect();
$current_store_id = get_current_store_id();

// ── Período ───────────────────────────────────────────────────────────────────
$period = $_GET['period'] ?? 'mes';
switch ($period) {
    case 'hoje':
        $date_from = $date_to = date('Y-m-d');
        $period_label = 'Hoje';
        break;
    case 'semana':
        $date_from = date('Y-m-d', strtotime('monday this week'));
        $date_to   = date('Y-m-d');
        $period_label = 'Esta semana';
        break;
    case 'trimestre':
        $date_from = date('Y-m-d', strtotime('first day of -2 months'));
        $date_to   = date('Y-m-d');
        $period_label = 'Trimestre';
        break;
    case 'ano':
        $date_from = date('Y-01-01');
        $date_to   = date('Y-m-d');
        $period_label = 'Este ano';
        break;
    case 'sempre':
        $date_from = '2000-01-01';
        $date_to   = date('Y-m-d');
        $period_label = 'Sempre';
        break;
    default:
        $date_from = date('Y-m-01');
        $date_to   = date('Y-m-d');
        $period_label = 'Este mês';
        $period = 'mes';
}

// ── Encomendas entregues  (campo correto: delivered_at) ───────────────────────
$stmt = $pdo->prepare('
    SELECT COALESCE(SUM(total_cost),0) AS total, COUNT(*) AS cnt
    FROM orders
    WHERE status = "delivered"
      AND DATE(delivered_at) >= ? AND DATE(delivered_at) <= ?
');
$stmt->execute([$date_from, $date_to]);
$row = $stmt->fetch();
$despesas_encomendas       = (float)$row['total'];
$despesas_encomendas_count = (int)$row['cnt'];

// ── Salários pagos ────────────────────────────────────────────────────────────
$stmt = $pdo->prepare('
    SELECT COALESCE(SUM(net_salary),0) AS total, COUNT(*) AS cnt
    FROM payroll
    WHERE status = "paid"
      AND DATE(paid_at) >= ? AND DATE(paid_at) <= ?
');
$stmt->execute([$date_from, $date_to]);
$row2 = $stmt->fetch();
$despesas_salarios       = (float)$row2['total'];
$despesas_salarios_count = (int)$row2['cnt'];

// ── Devoluções aprovadas ──────────────────────────────────────────────────────
$despesas_devolucoes = 0.0;
$despesas_devolucoes_count = 0;
try {
    $stmt = $pdo->prepare('
        SELECT COALESCE(SUM(total_refund),0) AS total, COUNT(*) AS cnt
        FROM returns
        WHERE status = "approved"
          AND DATE(created_at) >= ? AND DATE(created_at) <= ?
    ');
    $stmt->execute([$date_from, $date_to]);
    $row3 = $stmt->fetch();
    $despesas_devolucoes       = (float)$row3['total'];
    $despesas_devolucoes_count = (int)$row3['cnt'];
} catch (\Throwable $e) {}

// ── Saídas de caixa (levantamentos) ──────────────────────────────────────────
$despesas_caixa = 0.0;
$despesas_caixa_count = 0;
try {
    $stmt = $pdo->prepare('
        SELECT COALESCE(SUM(amount),0) AS total, COUNT(*) AS cnt
        FROM cash_movements
        WHERE type = "withdrawal"
          AND DATE(created_at) >= ? AND DATE(created_at) <= ?
    ');
    $stmt->execute([$date_from, $date_to]);
    $row4 = $stmt->fetch();
    $despesas_caixa       = (float)$row4['total'];
    $despesas_caixa_count = (int)$row4['cnt'];
} catch (\Throwable $e) {}

$total_despesas = $despesas_encomendas + $despesas_salarios + $despesas_devolucoes + $despesas_caixa;

// ── Receitas no período ───────────────────────────────────────────────────────
$stmt = $pdo->prepare('
    SELECT COALESCE(SUM(total),0)
    FROM sales
    WHERE DATE(sale_date) >= ? AND DATE(sale_date) <= ? AND store_id = ?
');
$stmt->execute([$date_from, $date_to, $current_store_id]);
$receitas_periodo = (float)$stmt->fetchColumn();
$saldo = $receitas_periodo - $total_despesas;

// ── Histórico encomendas entregues ────────────────────────────────────────────
$stmt = $pdo->prepare('
    SELECT o.id, o.total_cost, o.delivered_at AS data, s.name AS supplier_name
    FROM orders o
    LEFT JOIN suppliers s ON o.supplier_id = s.id
    WHERE o.status = "delivered"
      AND DATE(o.delivered_at) >= ? AND DATE(o.delivered_at) <= ?
    ORDER BY o.delivered_at DESC
    LIMIT 100
');
$stmt->execute([$date_from, $date_to]);
$historico_encomendas = $stmt->fetchAll();

// ── Histórico salários pagos ──────────────────────────────────────────────────
$stmt = $pdo->prepare('
    SELECT p.id, p.net_salary, p.paid_at AS data, p.month, e.name AS employee_name
    FROM payroll p
    LEFT JOIN employees e ON p.employee_id = e.id
    WHERE p.status = "paid"
      AND DATE(p.paid_at) >= ? AND DATE(p.paid_at) <= ?
    ORDER BY p.paid_at DESC
    LIMIT 100
');
$stmt->execute([$date_from, $date_to]);
$historico_salarios = $stmt->fetchAll();

// ── Histórico devoluções aprovadas ────────────────────────────────────────────
$historico_devolucoes = [];
try {
    $stmt = $pdo->prepare('
        SELECT r.id, r.return_number, r.total_refund, r.reason, r.created_at AS data
        FROM returns r
        WHERE r.status = "approved"
          AND DATE(r.created_at) >= ? AND DATE(r.created_at) <= ?
        ORDER BY r.created_at DESC
        LIMIT 100
    ');
    $stmt->execute([$date_from, $date_to]);
    $historico_devolucoes = $stmt->fetchAll();
} catch (\Throwable $e) {}

// ── Histórico saídas de caixa ─────────────────────────────────────────────────
$historico_caixa = [];
try {
    $stmt = $pdo->prepare('
        SELECT cm.id, cm.amount, cm.description, cm.created_at AS data, u.name AS user_name
        FROM cash_movements cm
        LEFT JOIN users u ON cm.user_id = u.id
        WHERE cm.type = "withdrawal"
          AND DATE(cm.created_at) >= ? AND DATE(cm.created_at) <= ?
        ORDER BY cm.created_at DESC
        LIMIT 100
    ');
    $stmt->execute([$date_from, $date_to]);
    $historico_caixa = $stmt->fetchAll();
} catch (\Throwable $e) {}

// ── Feed unificado de todas as saídas (ordenado por data desc) ────────────────
$todas_saidas = [];
foreach ($historico_encomendas as $r) {
    $todas_saidas[] = [
        'tipo'  => 'encomenda',
        'label' => 'Encomenda — ' . ($r['supplier_name'] ?? 'Fornecedor'),
        'valor' => (float)$r['total_cost'],
        'data'  => $r['data'],
    ];
}
foreach ($historico_salarios as $r) {
    $todas_saidas[] = [
        'tipo'  => 'salario',
        'label' => 'Salário — ' . ($r['employee_name'] ?? 'Funcionário') . ' (' . ($r['month'] ?? '') . ')',
        'valor' => (float)$r['net_salary'],
        'data'  => $r['data'],
    ];
}
foreach ($historico_devolucoes as $r) {
    $todas_saidas[] = [
        'tipo'  => 'devolucao',
        'label' => 'Devolução — ' . ($r['return_number'] ?? '#' . $r['id']),
        'valor' => (float)$r['total_refund'],
        'data'  => $r['data'],
    ];
}
foreach ($historico_caixa as $r) {
    $todas_saidas[] = [
        'tipo'  => 'caixa',
        'label' => 'Saída de caixa — ' . ($r['description'] ?? 'Levantamento'),
        'valor' => (float)$r['amount'],
        'data'  => $r['data'],
    ];
}
usort($todas_saidas, fn($a, $b) => strcmp($b['data'], $a['data']));

// ── Itens de compra (order_items detalhado) ───────────────────────────────────
$historico_itens_compra = [];
$despesas_itens_total   = 0.0;
$compras_agrupadas      = [];
try {
    $stmt = $pdo->prepare('
        SELECT oi.id, oi.quantity, oi.cost_price,
               (oi.quantity * oi.cost_price) AS subtotal,
               oi.created_at AS data,
               p.name AS product_name,
               p.category,
               o.id AS order_id
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE DATE(oi.created_at) >= ? AND DATE(oi.created_at) <= ?
        ORDER BY oi.created_at DESC
        LIMIT 200
    ');
    $stmt->execute([$date_from, $date_to]);
    $historico_itens_compra = $stmt->fetchAll();
    $despesas_itens_total   = array_sum(array_column($historico_itens_compra, 'subtotal'));

    foreach ($historico_itens_compra as $item) {
        $orderId = (int)$item['order_id'];
        if (!isset($compras_agrupadas[$orderId])) {
            $compras_agrupadas[$orderId] = [
                'order_id' => $orderId,
                'data' => $item['data'],
                'total' => 0.0,
                'itens' => [],
            ];
        }

        // Garante a data mais recente para o cabeçalho da compra
        if (!empty($item['data']) && strtotime($item['data']) > strtotime($compras_agrupadas[$orderId]['data'])) {
            $compras_agrupadas[$orderId]['data'] = $item['data'];
        }

        $compras_agrupadas[$orderId]['itens'][] = $item;
        $compras_agrupadas[$orderId]['total'] += (float)$item['subtotal'];
    }

    uasort($compras_agrupadas, function($a, $b) {
        return strcmp($b['data'] ?? '', $a['data'] ?? '');
    });
} catch (\Throwable $e) {}
?>

<style>
/* Page header */
.ph { display:flex; align-items:flex-start; justify-content:space-between; gap:20px; margin-bottom:28px; flex-wrap:wrap; }
.ph-title { font-family:'Plus Jakarta Sans',sans-serif;font-weight:800; font-size:22px; font-weight:900; letter-spacing:-.03em; margin-bottom:4px; }
.ph-sub { font-size:13px; color:var(--text-muted); }

/* Period selector */
.period-row { display:flex; gap:5px; flex-wrap:wrap; }
.p-btn {
    padding:6px 14px; border-radius:7px; font-size:12.5px; font-weight:500;
    cursor:pointer; border:1px solid var(--border);
    background:var(--bg-secondary); color:var(--text-secondary);
    text-decoration:none;
    transition: background-color 0.16s ease, color 0.16s ease, border-color 0.16s ease, transform 0.16s ease, box-shadow 0.16s ease;
}
.p-btn:hover { border-color:var(--border-light); color:var(--text-primary); background:var(--bg-tertiary); transform:translateY(-1px); }
.p-btn.active { background:var(--text-primary); color:var(--bg-primary); border-color:var(--text-primary); }

/* Stats grid */
.sg { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:14px; margin-bottom:28px; }
.sc {
    background:var(--bg-secondary); border:1px solid var(--border);
    border-radius:12px; padding:20px;
    transition: border-color 0.16s ease, transform 0.16s ease, box-shadow 0.16s ease;
}
.sc:hover {
    border-color: var(--border-light);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.25);
}
[data-theme="light"] .sc:hover { box-shadow: 0 6px 16px rgba(0,0,0,0.07); }
.sc-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
.sc-icon {
    width:34px; height:34px; border-radius:8px;
    background:var(--bg-tertiary); border:1px solid var(--border);
    display:flex; align-items:center; justify-content:center;
    color:var(--text-muted);
}
.sc-icon svg { width:16px; height:16px; stroke-width:2; }
.sc-badge {
    font-size:10.5px; font-weight:600; padding:3px 8px;
    border-radius:20px; letter-spacing:.02em;
    background:var(--bg-tertiary); color:var(--text-muted);
    border:1px solid var(--border);
}
.badge-red, .badge-green, .badge-blue { background:var(--bg-tertiary); color:var(--text-muted); border:1px solid var(--border); }
.sc-val { font-family:'Plus Jakarta Sans',sans-serif;font-weight:800; font-size:26px; font-weight:900; letter-spacing:-.04em; line-height:1; margin-bottom:4px; }
.sc-lbl { font-size:12px; color:var(--text-muted); }

/* Card */
.c {
    background:var(--bg-secondary);
    border:1px solid var(--border);
    border-radius:12px;
    overflow:hidden;
    margin-bottom:20px;
}
.c:hover { border-color:var(--border-light); box-shadow:none; }
.c-hd {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:15px 20px;
    border-bottom:1px solid var(--border);
    background:var(--bg-secondary);
}
.c-title { font-size:13.5px; font-weight:600; letter-spacing:-0.01em; }
.c-sub {
    font-size:11px;
    color:var(--text-muted);
    padding:2px 7px;
    border-radius:5px;
    background:var(--bg-tertiary);
    border:1px solid var(--border);
    font-weight:600;
    white-space:nowrap;
}

/* Tabs */
.tb-nav {
    display:flex;
    gap:2px;
    padding:3px;
    border-bottom:1px solid var(--border);
    overflow-x:auto;
    background:var(--bg-secondary);
}
.tb-btn {
    padding:8px 14px;
    font-size:13px;
    font-weight:500;
    border:0;
    background:transparent;
    color:var(--text-muted);
    border-radius:6px;
    cursor:pointer;
    white-space:nowrap;
    transition: background-color 0.16s ease, color 0.16s ease;
    font-family:inherit;
}
.tb-btn:hover { color:var(--text-primary); background:var(--bg-tertiary); }
.tb-btn.active { background:var(--bg-primary); color:var(--text-primary); font-weight:600; }
.tb-pane { display:none; }
.tb-pane.active { display:block; }

/* Table */
.tw { overflow-x:auto; }
table { width:100%; border-collapse:collapse; font-size:13px; }
th {
    padding:10px 20px;
    text-align:left;
    font-weight:600;
    font-size:9.5px;
    text-transform:uppercase;
    letter-spacing:.18em;
    color:var(--text-muted);
    border-bottom:1px solid var(--border);
    background:var(--bg-tertiary);
    white-space:nowrap;
}
td {
    padding:13px 20px;
    border-bottom:1px solid var(--border);
    color:var(--text-primary);
    vertical-align:middle;
}
tr:last-child td { border-bottom:none; }
tr td { transition: background-color 0.12s ease; }
tbody tr:hover td { background:var(--bg-tertiary); }
.empty {
    text-align:center;
    padding:48px 20px;
    color:var(--text-muted);
    font-size:13px;
    background:var(--bg-secondary);
}

/* Feed row badge */
.tipo-badge {
    display:inline-flex;
    align-items:center;
    font-size:10px;
    font-weight:700;
    line-height:1;
    padding:4px 10px;
    border-radius:6px;
    letter-spacing:.06em;
    text-transform:uppercase;
}
.tipo-encomenda { background:rgba(99,102,241,.1); color:#818cf8; border:1px solid rgba(99,102,241,.2); }
.tipo-salario   { background:rgba(16,185,129,.1); color:var(--success); border:1px solid rgba(16,185,129,.2); }
.tipo-devolucao { background:rgba(245,158,11,.1); color:var(--warning); border:1px solid rgba(245,158,11,.2); }
.tipo-caixa     { background:rgba(248,113,113,.1); color:var(--danger); border:1px solid rgba(248,113,113,.2); }

/* Valor saída */
.val-out { font-weight:700; color:var(--danger); letter-spacing:-.01em; }
.val-pos  { font-weight:700; color:var(--success); }

/* Compras agrupadas (clean) */
.compra-item { border-bottom:1px solid var(--border); }
.compra-item:last-child { border-bottom:none; }
.compra-head {
    width:100%;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:12px 16px;
    background:none;
    border:none;
    color:var(--text-primary);
    cursor:pointer;
    text-align:left;
    font-family:inherit;
    transition: background-color 0.16s ease, color 0.16s ease, border-color 0.16s ease;
}
.compra-head:hover { background:var(--bg-tertiary); }
.compra-left { display:flex; flex-direction:column; gap:2px; min-width:0; }
.compra-title { font-weight:500; font-size:13px; color:var(--text-primary); }
.compra-meta { font-size:12px; color:var(--text-muted); font-weight:400; }
.compra-right { display:flex; align-items:center; gap:10px; }
.compra-total { font-weight:500; color:var(--text-primary); white-space:nowrap; font-size:13px; }
.compra-arrow { color:var(--text-muted); transition:transform .2s ease; font-size:11px; }
.compra-item.open .compra-arrow { transform:rotate(180deg); }
.compra-detail {
    display:none;
    padding:0 16px 12px 16px;
}
.compra-item.open .compra-detail { display:block; }
.compra-detail table { margin-top:6px; }
.compra-total-row td { font-weight:700; }
</style>

<div class="ph">
    <div>
        <div class="ph-title">Despesas &amp; Saídas</div>
        <div class="ph-sub">Todas as saídas financeiras · <?= htmlspecialchars($period_label) ?></div>
    </div>
    <div class="period-row">
        <?php foreach(['hoje'=>'Hoje','mes'=>'Este mês','trimestre'=>'Trimestre','ano'=>'Este ano','sempre'=>'Sempre'] as $k=>$v): ?>
        <a href="?period=<?= $k ?>" class="p-btn <?= $period===$k?'active':'' ?>"><?= $v ?></a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Métricas -->
<div class="sg">
    <div class="sc">
        <div class="sc-row">
            <div class="sc-icon" aria-hidden="true">
                <svg fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M12 3v18M17 7.5c0-1.9-2.24-3.5-5-3.5S7 5.6 7 7.5 9.24 11 12 11s5 1.6 5 3.5S14.76 18 12 18s-5-1.6-5-3.5"/></svg>
            </div>
            <span class="sc-badge">Total saídas</span>
        </div>
        <div class="sc-val" style="color:var(--danger)">€<?= number_format($total_despesas,2,',','.') ?></div>
        <div class="sc-lbl">Total de saídas · <?= $period_label ?></div>
    </div>

    <div class="sc">
        <div class="sc-row">
            <div class="sc-icon" aria-hidden="true">
                <svg fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M21 8.5 12 3 3 8.5m18 0V16L12 21m9-12.5L12 13M3 8.5V16L12 21m0-8V21"/></svg>
            </div>
            <span class="sc-badge"><?= $despesas_encomendas_count ?> enc.</span>
        </div>
        <div class="sc-val">€<?= number_format($despesas_encomendas,2,',','.') ?></div>
        <div class="sc-lbl">Encomendas entregues</div>
    </div>

    <div class="sc">
        <div class="sc-row">
            <div class="sc-icon" aria-hidden="true">
                <svg fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm-8 13a5 5 0 0 1 10 0"/></svg>
            </div>
            <span class="sc-badge"><?= $despesas_salarios_count ?> sal.</span>
        </div>
        <div class="sc-val">€<?= number_format($despesas_salarios,2,',','.') ?></div>
        <div class="sc-lbl">Salários pagos</div>
    </div>

    <?php if ($despesas_devolucoes > 0 || $despesas_devolucoes_count > 0): ?>
    <div class="sc">
        <div class="sc-row">
            <div class="sc-icon" aria-hidden="true">
                <svg fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M9 10 4 15m0 0 5 5m-5-5h10a6 6 0 1 0 0-12h-1"/></svg>
            </div>
            <span class="sc-badge"><?= $despesas_devolucoes_count ?></span>
        </div>
        <div class="sc-val">€<?= number_format($despesas_devolucoes,2,',','.') ?></div>
        <div class="sc-lbl">Devoluções aprovadas</div>
    </div>
    <?php endif; ?>

    <?php if ($despesas_caixa > 0 || $despesas_caixa_count > 0): ?>
    <div class="sc">
        <div class="sc-row">
            <div class="sc-icon" aria-hidden="true">
                <svg fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M3 7h18v10H3zM7 12h.01M17 12h.01M12 12a2 2 0 1 0 0-.01Z"/></svg>
            </div>
            <span class="sc-badge"><?= $despesas_caixa_count ?> mov.</span>
        </div>
        <div class="sc-val">€<?= number_format($despesas_caixa,2,',','.') ?></div>
        <div class="sc-lbl">Saídas de caixa</div>
    </div>
    <?php endif; ?>

    <div class="sc">
        <div class="sc-row">
            <div class="sc-icon" aria-hidden="true">
                <svg fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M4 19h16M7 16V9m5 7V5m5 11v-4"/></svg>
            </div>
            <span class="sc-badge"><?= $saldo>=0?'Positivo':'Negativo' ?></span>
        </div>
        <div class="sc-val" style="color:<?= $saldo>=0?'var(--success)':'var(--danger)' ?>">
            <?= $saldo<0?'-':'' ?>€<?= number_format(abs($saldo),2,',','.') ?>
        </div>
        <div class="sc-lbl">Receitas − Despesas</div>
    </div>
</div>

<!-- Histórico detalhado com tabs -->
<div class="c">
    <div class="c-hd">
        <span class="c-title">Histórico de Saídas</span>
        <span class="c-sub"><?= count($todas_saidas) ?> registos · <?= htmlspecialchars($period_label) ?></span>
    </div>
    <div class="tb-nav">
        <button class="tb-btn active" onclick="switchTab('todas',this)">Todas as saídas (<?= count($todas_saidas) ?>)</button>
        <button class="tb-btn" onclick="switchTab('encomendas',this)">Encomendas (<?= $despesas_encomendas_count ?>)</button>
        <button class="tb-btn" onclick="switchTab('salarios',this)">Salários (<?= $despesas_salarios_count ?>)</button>
        <?php if ($despesas_devolucoes_count > 0): ?>
        <button class="tb-btn" onclick="switchTab('devolucoes',this)">Devoluções (<?= $despesas_devolucoes_count ?>)</button>
        <?php endif; ?>
        <?php if ($despesas_caixa_count > 0): ?>
        <button class="tb-btn" onclick="switchTab('caixa',this)">Caixa (<?= $despesas_caixa_count ?>)</button>
        <?php endif; ?>
        <?php if (!empty($historico_itens_compra)): ?>
        <button class="tb-btn" onclick="switchTab('itens',this)">Itens Comprados (<?= count($historico_itens_compra) ?>)</button>
        <?php endif; ?>
    </div>

    <!-- TAB: Todas as saídas -->
    <div id="tab-todas" class="tb-pane active">
        <div class="tw">
        <?php if (empty($todas_saidas)): ?>
            <div class="empty">Sem saídas registadas no período selecionado.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Descrição</th>
                    <th style="text-align:right">Valor</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $tipo_labels = [
                'encomenda' => 'Encomenda',
                'salario'   => 'Salário',
                'devolucao' => 'Devolução',
                'caixa'     => 'Saída Caixa',
            ];
            foreach ($todas_saidas as $s): ?>
            <tr>
                <td style="color:var(--text-muted);white-space:nowrap;font-variant-numeric:tabular-nums">
                    <?= $s['data'] ? date('d/m/Y H:i', strtotime($s['data'])) : '—' ?>
                </td>
                <td>
                    <span class="tipo-badge tipo-<?= $s['tipo'] ?>">
                        <?= $tipo_labels[$s['tipo']] ?? $s['tipo'] ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($s['label']) ?></td>
                <td style="text-align:right;font-variant-numeric:tabular-nums">
                    <span class="val-out">−€<?= number_format($s['valor'],2,',','.') ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        </div>
    </div>

    <!-- TAB: Encomendas -->
    <div id="tab-encomendas" class="tb-pane">
        <div class="tw">
        <?php if (empty($historico_encomendas)): ?>
            <div class="empty">Sem encomendas entregues no período.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>ID</th><th>Fornecedor</th><th>Data Entrega</th><th style="text-align:right">Custo</th></tr>
            </thead>
            <tbody>
            <?php foreach ($historico_encomendas as $r): ?>
            <tr>
                <td style="color:var(--text-muted)">#<?= $r['id'] ?></td>
                <td><?= htmlspecialchars($r['supplier_name'] ?? '—') ?></td>
                <td style="color:var(--text-muted)">
                    <?= $r['data'] ? date('d/m/Y', strtotime($r['data'])) : '—' ?>
                </td>
                <td style="text-align:right"><span class="val-out">€<?= number_format($r['total_cost'],2,',','.') ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        </div>
    </div>

    <!-- TAB: Salários -->
    <div id="tab-salarios" class="tb-pane">
        <div class="tw">
        <?php if (empty($historico_salarios)): ?>
            <div class="empty">Sem salários pagos no período.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>ID</th><th>Funcionário</th><th>Mês</th><th>Data Pagamento</th><th style="text-align:right">Valor</th></tr>
            </thead>
            <tbody>
            <?php foreach ($historico_salarios as $r): ?>
            <tr>
                <td style="color:var(--text-muted)">#<?= $r['id'] ?></td>
                <td><?= htmlspecialchars($r['employee_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($r['month'] ?? '—') ?></td>
                <td style="color:var(--text-muted)">
                    <?= $r['data'] ? date('d/m/Y', strtotime($r['data'])) : '—' ?>
                </td>
                <td style="text-align:right"><span class="val-out">€<?= number_format($r['net_salary'],2,',','.') ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        </div>
    </div>

    <!-- TAB: Devoluções -->
    <div id="tab-devolucoes" class="tb-pane">
        <div class="tw">
        <?php if (empty($historico_devolucoes)): ?>
            <div class="empty">Sem devoluções no período.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>Nº Devolução</th><th>Motivo</th><th>Data</th><th style="text-align:right">Reembolso</th></tr>
            </thead>
            <tbody>
            <?php foreach ($historico_devolucoes as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['return_number'] ?? '#'.$r['id']) ?></td>
                <td><?= htmlspecialchars(substr($r['reason'] ?? '—', 0, 80)) ?></td>
                <td style="color:var(--text-muted)">
                    <?= $r['data'] ? date('d/m/Y H:i', strtotime($r['data'])) : '—' ?>
                </td>
                <td style="text-align:right"><span class="val-out">€<?= number_format($r['total_refund'],2,',','.') ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        </div>
    </div>

    <!-- TAB: Saídas de caixa -->
    <div id="tab-caixa" class="tb-pane">
        <div class="tw">
        <?php if (empty($historico_caixa)): ?>
            <div class="empty">Sem saídas de caixa no período.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>Descrição</th><th>Operador</th><th>Data</th><th style="text-align:right">Valor</th></tr>
            </thead>
            <tbody>
            <?php foreach ($historico_caixa as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['description'] ?? 'Levantamento') ?></td>
                <td><?= htmlspecialchars($r['user_name'] ?? '—') ?></td>
                <td style="color:var(--text-muted)">
                    <?= $r['data'] ? date('d/m/Y H:i', strtotime($r['data'])) : '—' ?>
                </td>
                <td style="text-align:right"><span class="val-out">€<?= number_format($r['amount'],2,',','.') ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        </div>
    </div>

    <!-- TAB: Itens de Compra -->
    <div id="tab-itens" class="tb-pane">
        <?php if (!empty($historico_itens_compra)): ?>
        <div style="padding:12px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;">
            <span style="font-size:13px;color:var(--text-muted);">Total comprado no período:</span>
            <span style="font-weight:700;color:var(--danger);font-size:14px;">€<?= number_format($despesas_itens_total,2,',','.') ?></span>
        </div>
        <?php endif; ?>
        <div class="tw">
        <?php if (empty($compras_agrupadas)): ?>
            <div class="empty">Sem compras no período.</div>
        <?php else: ?>
            <?php foreach ($compras_agrupadas as $compra): ?>
            <div class="compra-item">
                <button type="button" class="compra-head" onclick="toggleCompra(this)">
                    <div class="compra-left">
                        <div class="compra-title">Compra #<?= (int)$compra['order_id'] ?></div>
                        <div class="compra-meta">
                            <?= !empty($compra['data']) ? date('d/m/Y H:i', strtotime($compra['data'])) : 'Data indisponível' ?>
                            · <?= count($compra['itens']) ?> item(ns)
                        </div>
                    </div>
                    <div class="compra-right">
                        <span class="compra-total">€<?= number_format($compra['total'],2,',','.') ?></span>
                        <span class="compra-arrow">▼</span>
                    </div>
                </button>

                <div class="compra-detail">
                    <table>
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Categoria</th>
                                <th style="text-align:right">Qtd</th>
                                <th style="text-align:right">Custo Unit.</th>
                                <th style="text-align:right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($compra['itens'] as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['product_name'] ?? 'Produto #'.$r['id']) ?></td>
                            <td style="color:var(--text-muted)"><?= htmlspecialchars($r['category'] ?? '—') ?></td>
                            <td style="text-align:right"><?= number_format($r['quantity'],0,',','.') ?></td>
                            <td style="text-align:right">€<?= number_format($r['cost_price'],2,',','.') ?></td>
                            <td style="text-align:right"><span class="val-out">€<?= number_format($r['subtotal'],2,',','.') ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="compra-total-row">
                            <td colspan="4" style="text-align:right">Total da compra</td>
                            <td style="text-align:right"><span class="val-out">€<?= number_format($compra['total'],2,',','.') ?></span></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>
</div>

<script>
function switchTab(name, btn) {
    document.querySelectorAll('.tb-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tb-btn').forEach(b => b.classList.remove('active'));
    const pane = document.getElementById('tab-' + name);
    if (pane) pane.classList.add('active');
    btn.classList.add('active');
}

function toggleCompra(el) {
    const wrapper = el.closest('.compra-item');
    if (wrapper) wrapper.classList.toggle('open');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
