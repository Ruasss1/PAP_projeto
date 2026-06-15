<?php
/**
 * Analytics — Desempenho de Vendas
 */
$page_title = 'Analytics';
require_once __DIR__ . '/../includes/header.php';

$pdo = db_connect();
$current_store_id = get_current_store_id();

// Últimos 12 meses
$meses = [];
for ($i = 11; $i >= 0; $i--) {
    $meses[] = date('Y-m', strtotime("-$i months"));
}

$vendas_mensais = [];
foreach ($meses as $mes) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as vendas, COALESCE(SUM(total),0) as valor FROM sales WHERE DATE_FORMAT(sale_date,'%Y-%m')=? AND store_id=?");
    $stmt->execute([$mes, $current_store_id]);
    $vendas_mensais[$mes] = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Mês atual vs anterior
$mes_atual    = date('Y-m');
$mes_anterior = date('Y-m', strtotime('-1 month'));

$stmt = $pdo->prepare("SELECT COUNT(*) as vendas, COALESCE(SUM(total),0) as valor FROM sales WHERE DATE_FORMAT(sale_date,'%Y-%m')=? AND store_id=?");
$stmt->execute([$mes_atual, $current_store_id]);
$atual = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->execute([$mes_anterior, $current_store_id]);
$anterior = $stmt->fetch(PDO::FETCH_ASSOC);

$var_vendas = $anterior['vendas'] > 0 ? (($atual['vendas'] - $anterior['vendas']) / $anterior['vendas']) * 100 : 0;
$var_valor  = $anterior['valor']  > 0 ? (($atual['valor']  - $anterior['valor'])  / $anterior['valor'])  * 100 : 0;
$ticket_atual = $atual['vendas'] > 0 ? $atual['valor'] / $atual['vendas'] : 0;

// Previsão (média móvel 3 meses × 1,05)
$ultimos3 = array_slice($vendas_mensais, -3);
$media3   = count($ultimos3) > 0 ? array_sum(array_column($ultimos3, 'valor')) / count($ultimos3) : 0;
$previsao = $media3 * 1.05;

// Vendas por hora (30 dias)
$stmt = $pdo->prepare("SELECT HOUR(sale_date) as hora, COUNT(*) as vendas FROM sales WHERE sale_date >= DATE_SUB(NOW(),INTERVAL 30 DAY) AND store_id=? GROUP BY HOUR(sale_date) ORDER BY hora");
$stmt->execute([$current_store_id]);
$vendas_hora = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vendas por dia da semana (90 dias)
$stmt = $pdo->prepare("SELECT DAYOFWEEK(sale_date) as dia, COUNT(*) as vendas, COALESCE(SUM(total),0) as valor FROM sales WHERE sale_date >= DATE_SUB(NOW(),INTERVAL 90 DAY) AND store_id=? GROUP BY DAYOFWEEK(sale_date) ORDER BY dia");
$stmt->execute([$current_store_id]);
$vendas_dia = $stmt->fetchAll(PDO::FETCH_ASSOC);
$dias_nomes = ['','Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

// Ticket médio mensal
$stmt = $pdo->prepare("SELECT DATE_FORMAT(sale_date,'%Y-%m') as mes, AVG(total) as ticket FROM sales WHERE sale_date >= DATE_SUB(NOW(),INTERVAL 12 MONTH) AND store_id=? GROUP BY DATE_FORMAT(sale_date,'%Y-%m') ORDER BY mes");
$stmt->execute([$current_store_id]);
$ticket_mensal = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Produtos em tendência
$stmt = $pdo->prepare("
    SELECT p.name, p.category,
        SUM(CASE WHEN s.sale_date >= DATE_SUB(NOW(),INTERVAL 30 DAY) THEN si.quantity ELSE 0 END) as v30,
        SUM(CASE WHEN s.sale_date BETWEEN DATE_SUB(NOW(),INTERVAL 60 DAY) AND DATE_SUB(NOW(),INTERVAL 30 DAY) THEN si.quantity ELSE 0 END) as v_ant
    FROM sale_items si
    JOIN products p ON p.id = si.product_id
    JOIN sales s ON s.id = si.sale_id
    WHERE s.sale_date >= DATE_SUB(NOW(),INTERVAL 60 DAY) AND s.store_id=?
    GROUP BY p.id, p.name, p.category
    HAVING v30 > 0 AND v_ant > 0
    ORDER BY (v30 - v_ant) / v_ant DESC
    LIMIT 8
");
$stmt->execute([$current_store_id]);
$tendencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Desempenho por categoria
$stmt = $pdo->prepare("
    SELECT p.category, COUNT(DISTINCT s.id) as vendas, SUM(si.quantity) as itens, AVG(si.quantity) as media_qtd
    FROM sale_items si
    JOIN products p ON p.id = si.product_id
    JOIN sales s ON s.id = si.sale_id
    WHERE s.sale_date >= DATE_SUB(NOW(),INTERVAL 30 DAY) AND s.store_id=?
    GROUP BY p.category ORDER BY vendas DESC
");
$stmt->execute([$current_store_id]);
$por_categoria = $stmt->fetchAll(PDO::FETCH_ASSOC);

$mes_nomes = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
?>

<style>
/* ── Analytics — estilos locais ─────────────────────────────────────────── */
.an-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.an-header h1 {
    display: flex;
    align-items: center;
    gap: 10px;
}

.an-meta {
    font-size: 12px;
    color: var(--text-muted);
}

/* KPI cards */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

@media (max-width: 1100px) { .kpi-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 600px)  { .kpi-grid { grid-template-columns: 1fr; } }

.kpi-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 20px;
    position: relative;
    overflow: hidden;
    transition: border-color var(--transition);
}
.kpi-card:hover { border-color: var(--border-light); }

.kpi-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--text-muted);
    margin-bottom: 10px;
}

.kpi-value {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 28px;
    font-weight: 800;
    letter-spacing: -0.04em;
    color: var(--text-primary);
    line-height: 1;
    margin-bottom: 10px;
}

.kpi-change {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 5px;
}
.kpi-change.up   { background: var(--success-subtle); color: var(--success); }
.kpi-change.down { background: var(--danger-subtle);  color: var(--danger); }
.kpi-change.flat { background: var(--bg-tertiary);    color: var(--text-muted); }

/* Previsão banner */
.previsao-banner {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 24px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 20px;
}

.previsao-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--text-muted);
    margin-bottom: 6px;
}

.previsao-value {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 36px;
    font-weight: 800;
    letter-spacing: -0.04em;
    color: var(--success);
    line-height: 1;
}

.previsao-note {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 6px;
}

/* Chart grid */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}
@media (max-width: 900px) { .charts-grid { grid-template-columns: 1fr; } }

.chart-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 20px;
    transition: border-color var(--transition);
}
.chart-card:hover { border-color: var(--border-light); }

.chart-title {
    font-size: 13px;
    font-weight: 700;
    letter-spacing: -0.02em;
    color: var(--text-primary);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.chart-title svg { color: var(--text-muted); flex-shrink: 0; }

/* Tables section */
.an-section {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 16px;
    transition: border-color var(--transition);
}
.an-section:hover { border-color: var(--border-light); }

.an-section-header {
    padding: 14px 18px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 8px;
}

.an-section-title {
    font-size: 13.5px;
    font-weight: 700;
    letter-spacing: -0.02em;
    color: var(--text-primary);
}

.an-section svg { color: var(--text-muted); }

/* Trend bar */
.trend-bar-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
}
.trend-bar {
    flex: 1;
    height: 4px;
    background: var(--bg-tertiary);
    border-radius: 2px;
    overflow: hidden;
    max-width: 80px;
}
.trend-bar-fill {
    height: 100%;
    border-radius: 2px;
    background: var(--success);
}
.trend-bar-fill.down { background: var(--danger); }

.var-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 11.5px;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 5px;
    white-space: nowrap;
}
.var-badge.up   { background: var(--success-subtle); color: var(--success); }
.var-badge.down { background: var(--danger-subtle);  color: var(--danger); }

/* No data */
.no-data {
    padding: 40px;
    text-align: center;
    color: var(--text-muted);
    font-size: 13px;
}
</style>

<!-- Cabeçalho -->
<div class="an-header">
    <h1>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text-secondary)"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
        Analytics
    </h1>
    <span class="an-meta">Atualizado em <?= date('d/m/Y \à\s H:i') ?></span>
</div>

<!-- KPIs -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Vendas — Mês Atual</div>
        <div class="kpi-value"><?= number_format($atual['vendas']) ?></div>
        <span class="kpi-change <?= $var_vendas >= 0 ? 'up' : 'down' ?>">
            <?= $var_vendas >= 0 ? '↑' : '↓' ?> <?= number_format(abs($var_vendas), 1) ?>% vs anterior
        </span>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Faturação — Mês Atual</div>
        <div class="kpi-value" style="color:var(--success);">€<?= number_format($atual['valor'], 0, ',', '.') ?></div>
        <span class="kpi-change <?= $var_valor >= 0 ? 'up' : 'down' ?>">
            <?= $var_valor >= 0 ? '↑' : '↓' ?> <?= number_format(abs($var_valor), 1) ?>% vs anterior
        </span>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Ticket Médio — Mês Atual</div>
        <div class="kpi-value">€<?= number_format($ticket_atual, 2, ',', '.') ?></div>
        <span class="kpi-change flat">Objetivo: €25,00</span>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Previsão — Próximo Mês</div>
        <div class="kpi-value">€<?= number_format($previsao, 0, ',', '.') ?></div>
        <span class="kpi-change flat">Média móvel × 1,05</span>
    </div>
</div>

<!-- Previsão em destaque -->
<div class="previsao-banner">
    <div>
        <div class="previsao-label">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:4px;"><path d="M12 3l1.88 5.76a1 1 0 0 0 .95.69H21l-4.94 3.67a1 1 0 0 0-.36 1.12L17.56 20 12 16.2 6.44 20l1.86-5.76a1 1 0 0 0-.36-1.12L3 9.45h6.17a1 1 0 0 0 .95-.69L12 3z"/></svg>
            Previsão de Faturação
        </div>
        <div class="previsao-value">€<?= number_format($previsao, 2, ',', '.') ?></div>
        <div class="previsao-note"><?= date('F Y', strtotime('+1 month')) ?> &mdash; baseado na média dos últimos 3 meses com estimativa de crescimento de 5%</div>
    </div>
    <div style="text-align:right;flex-shrink:0;">
        <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">Média 3 meses</div>
        <div style="font-size:20px;font-weight:800;letter-spacing:-0.03em;color:var(--text-primary);">€<?= number_format($media3, 0, ',', '.') ?></div>
    </div>
</div>

<!-- Gráficos -->
<div class="charts-grid">
    <!-- Evolução mensal -->
    <div class="chart-card">
        <div class="chart-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            Faturação Mensal (12 meses)
        </div>
        <canvas id="chartMensal" height="180"></canvas>
    </div>

    <!-- Horas de pico -->
    <div class="chart-card">
        <div class="chart-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Vendas por Hora (últimos 30 dias)
        </div>
        <canvas id="chartHoras" height="180"></canvas>
    </div>

    <!-- Dias da semana -->
    <div class="chart-card">
        <div class="chart-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Faturação por Dia da Semana (90 dias)
        </div>
        <canvas id="chartDias" height="180"></canvas>
    </div>

    <!-- Ticket médio -->
    <div class="chart-card">
        <div class="chart-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            Ticket Médio Mensal
        </div>
        <canvas id="chartTicket" height="180"></canvas>
    </div>
</div>

<!-- Tabelas -->
<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;">

    <!-- Produtos em tendência -->
    <div class="an-section">
        <div class="an-section-header">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            <span class="an-section-title">Produtos em Tendência</span>
        </div>
        <?php if (empty($tendencias)): ?>
        <div class="no-data">Sem dados suficientes</div>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th style="text-align:right;">30d</th>
                    <th style="text-align:right;">Anterior</th>
                    <th style="text-align:right;">Variação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tendencias as $p):
                    $var = $p['v_ant'] > 0 ? (($p['v30'] - $p['v_ant']) / $p['v_ant']) * 100 : 0;
                    $up  = $var >= 0;
                ?>
                <tr>
                    <td>
                        <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($p['name']) ?></div>
                        <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($p['category']) ?></div>
                    </td>
                    <td style="text-align:right;font-weight:600;"><?= number_format($p['v30']) ?></td>
                    <td style="text-align:right;color:var(--text-muted);"><?= number_format($p['v_ant']) ?></td>
                    <td style="text-align:right;">
                        <span class="var-badge <?= $up ? 'up' : 'down' ?>">
                            <?= $up ? '↑' : '↓' ?> <?= number_format(abs($var), 1) ?>%
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Desempenho por categoria -->
    <div class="an-section">
        <div class="an-section-header">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            <span class="an-section-title">Desempenho por Categoria (30 dias)</span>
        </div>
        <?php if (empty($por_categoria)): ?>
        <div class="no-data">Sem dados suficientes</div>
        <?php else:
            $max_vendas = max(array_column($por_categoria, 'vendas')) ?: 1;
        ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Categoria</th>
                    <th style="text-align:right;">Vendas</th>
                    <th style="text-align:right;">Itens</th>
                    <th style="text-align:right;">Média/venda</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($por_categoria as $cat): ?>
                <tr>
                    <td>
                        <div class="trend-bar-wrap">
                            <div style="font-weight:600;font-size:13px;min-width:0;"><?= htmlspecialchars($cat['category']) ?></div>
                        </div>
                    </td>
                    <td style="text-align:right;font-weight:600;"><?= number_format($cat['vendas']) ?></td>
                    <td style="text-align:right;color:var(--text-secondary);"><?= number_format($cat['itens']) ?></td>
                    <td style="text-align:right;color:var(--text-muted);"><?= number_format($cat['media_qtd'], 1) ?> un.</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>

<script>
// Dados PHP → JS
const mesesLabels   = <?= json_encode(array_map(fn($m) => $mes_nomes[(int)explode('-',$m)[1]-1], array_keys($vendas_mensais))) ?>;
const valoresMensais = <?= json_encode(array_map(fn($v) => (float)$v['valor'], array_values($vendas_mensais))) ?>;
const vendasHora    = <?= json_encode($vendas_hora) ?>;
const vendasDia     = <?= json_encode($vendas_dia) ?>;
const ticketMensal  = <?= json_encode($ticket_mensal) ?>;
const diasNomes     = <?= json_encode($dias_nomes) ?>;
const mesMesNomes   = <?= json_encode($mes_nomes) ?>;

// Cores adaptadas ao tema ativo — cor única de destaque
function themeColors() {
    const dark = document.documentElement.getAttribute('data-theme') !== 'light';
    return {
        grid:      dark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.05)',
        tick:      dark ? '#50505a'                : '#8a8a9e',
        bar:       dark ? 'rgba(236,236,236,.12)'  : 'rgba(17,17,17,.08)',
        barBorder: dark ? '#ececec'                : '#111111',
        line:      dark ? 'rgba(236,236,236,.08)'  : 'rgba(17,17,17,.05)',
        lineBorder:dark ? '#ececec'                : '#111111',
    };
}

const c = themeColors();

const baseScales = {
    y: { beginAtZero: true, grid: { color: c.grid }, ticks: { color: c.tick, font: { family: 'Plus Jakarta Sans', size: 11 } }, border: { display: false } },
    x: { grid: { display: false },  ticks: { color: c.tick, font: { family: 'Plus Jakarta Sans', size: 11 } }, border: { display: false } }
};

const basePlugins = {
    legend: { display: false },
    tooltip: {
        backgroundColor: document.documentElement.getAttribute('data-theme') !== 'light' ? '#141414' : '#ffffff',
        borderColor: document.documentElement.getAttribute('data-theme') !== 'light' ? '#2a2a2a' : '#e2e2e8',
        borderWidth: 1,
        titleColor: document.documentElement.getAttribute('data-theme') !== 'light' ? '#eeeeee' : '#111116',
        bodyColor:  document.documentElement.getAttribute('data-theme') !== 'light' ? '#8a8a98' : '#5a5a6e',
        titleFont: { family: 'Plus Jakarta Sans', weight: '700', size: 12 },
        bodyFont:  { family: 'Plus Jakarta Sans', size: 12 },
        padding: 10,
        cornerRadius: 8,
    }
};

// Faturação mensal
new Chart(document.getElementById('chartMensal'), {
    type: 'bar',
    data: {
        labels: mesesLabels,
        datasets: [{
            data: valoresMensais,
            backgroundColor: c.bar,
            borderColor: c.barBorder,
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { ...basePlugins, tooltip: { ...basePlugins.tooltip, callbacks: { label: ctx => '€' + ctx.parsed.y.toLocaleString('pt-PT', {minimumFractionDigits:2}) } } },
        scales: baseScales,
    }
});

// Vendas por hora
const horaData = Array.from({length: 24}, (_, i) => {
    const h = vendasHora.find(v => parseInt(v.hora) === i);
    return h ? parseInt(h.vendas) : 0;
});
new Chart(document.getElementById('chartHoras'), {
    type: 'line',
    data: {
        labels: Array.from({length: 24}, (_, i) => i + 'h'),
        datasets: [{
            data: horaData,
            borderColor: c.lineBorder,
            backgroundColor: c.line,
            fill: true,
            tension: 0.4,
            pointRadius: 3,
            pointBackgroundColor: c.lineBorder,
        }]
    },
    options: { responsive: true, plugins: basePlugins, scales: baseScales }
});

// Dias da semana
new Chart(document.getElementById('chartDias'), {
    type: 'bar',
    data: {
        labels: vendasDia.map(d => diasNomes[d.dia]),
        datasets: [{
            data: vendasDia.map(d => parseFloat(d.valor)),
            backgroundColor: c.bar,
            borderColor: c.barBorder,
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { ...basePlugins, tooltip: { ...basePlugins.tooltip, callbacks: { label: ctx => '€' + ctx.parsed.y.toLocaleString('pt-PT', {minimumFractionDigits:2}) } } },
        scales: baseScales,
    }
});

// Ticket médio
new Chart(document.getElementById('chartTicket'), {
    type: 'line',
    data: {
        labels: ticketMensal.map(t => mesMesNomes[parseInt(t.mes.split('-')[1])-1]),
        datasets: [{
            data: ticketMensal.map(t => parseFloat(t.ticket)),
            borderColor: c.barBorder,
            backgroundColor: c.bar,
            fill: true,
            tension: 0.4,
            pointRadius: 3,
            pointBackgroundColor: c.barBorder,
        }]
    },
    options: {
        responsive: true,
        plugins: { ...basePlugins, tooltip: { ...basePlugins.tooltip, callbacks: { label: ctx => '€' + ctx.parsed.y.toLocaleString('pt-PT', {minimumFractionDigits:2}) } } },
        scales: baseScales,
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
