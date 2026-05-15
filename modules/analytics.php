<?php
/**
 * Módulo de Analytics Avançado
 * modules/analytics.php
 */
$page_title = 'Analytics';
require_once __DIR__ . '/../includes/header.php';

$pdo = db_connect();

// Obter loja atual
$current_store_id = get_current_store_id();
$current_store = get_current_store();

// Obter dados dos últimos 12 meses
$meses = [];
for ($i = 11; $i >= 0; $i--) {
    $meses[] = date('Y-m', strtotime("-$i months"));
}

// Vendas mensais (filtrado por loja)
$vendas_mensais = [];
foreach ($meses as $mes) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as vendas, COALESCE(SUM(total), 0) as valor
        FROM sales 
        WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
          AND (store_id = ? OR store_id IS NULL)
    ");
    $stmt->execute([$mes, $current_store_id]);
    $vendas_mensais[$mes] = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Comparação mês atual vs mês anterior
$mes_atual = date('Y-m');
$mes_anterior = date('Y-m', strtotime('-1 month'));

$stmt = $pdo->prepare("SELECT COUNT(*) as vendas, COALESCE(SUM(total), 0) as valor FROM sales WHERE DATE_FORMAT(created_at, '%Y-%m') = ? AND (store_id = ? OR store_id IS NULL)");
$stmt->execute([$mes_atual, $current_store_id]);
$dados_atual = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt->execute([$mes_anterior, $current_store_id]);
$dados_anterior = $stmt->fetch(PDO::FETCH_ASSOC);

$variacao_vendas = $dados_anterior['vendas'] > 0 ? (($dados_atual['vendas'] - $dados_anterior['vendas']) / $dados_anterior['vendas']) * 100 : 0;
$variacao_valor = $dados_anterior['valor'] > 0 ? (($dados_atual['valor'] - $dados_anterior['valor']) / $dados_anterior['valor']) * 100 : 0;

// Horas de pico (vendas por hora) - filtrado por loja
$stmt = $pdo->prepare("
    SELECT HOUR(created_at) as hora, COUNT(*) as vendas, SUM(total) as valor
    FROM sales 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      AND (store_id = ? OR store_id IS NULL)
    GROUP BY HOUR(created_at)
    ORDER BY hora
");
$stmt->execute([$current_store_id]);
$vendas_hora = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dias da semana - filtrado por loja
$stmt = $pdo->prepare("
    SELECT DAYOFWEEK(created_at) as dia, COUNT(*) as vendas, SUM(total) as valor
    FROM sales 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
      AND (store_id = ? OR store_id IS NULL)
    GROUP BY DAYOFWEEK(created_at)
    ORDER BY dia
");
$stmt->execute([$current_store_id]);
$vendas_dia_semana = $stmt->fetchAll(PDO::FETCH_ASSOC);
$dias_nomes = ['', 'Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

// Previsão simples (média móvel)
$ultimos_3_meses = array_slice($vendas_mensais, -3);
$media_vendas = array_sum(array_column($ultimos_3_meses, 'valor')) / 3;
$previsao_proximo_mes = $media_vendas * 1.05; // 5% crescimento estimado

// Produtos em tendência (crescimento de vendas)
$stmt = $pdo->query("
    SELECT 
        p.name,
        p.category,
        SUM(CASE WHEN s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN si.quantity ELSE 0 END) as vendas_30d,
        SUM(CASE WHEN s.created_at BETWEEN DATE_SUB(NOW(), INTERVAL 60 DAY) AND DATE_SUB(NOW(), INTERVAL 30 DAY) THEN si.quantity ELSE 0 END) as vendas_anterior
    FROM sale_items si
    JOIN products p ON p.id = si.product_id
    JOIN sales s ON s.id = si.sale_id
    WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
    GROUP BY p.id, p.name, p.category
    HAVING SUM(CASE WHEN s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN si.quantity ELSE 0 END) > 0 
        AND SUM(CASE WHEN s.created_at BETWEEN DATE_SUB(NOW(), INTERVAL 60 DAY) AND DATE_SUB(NOW(), INTERVAL 30 DAY) THEN si.quantity ELSE 0 END) > 0
    ORDER BY (SUM(CASE WHEN s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN si.quantity ELSE 0 END) - SUM(CASE WHEN s.created_at BETWEEN DATE_SUB(NOW(), INTERVAL 60 DAY) AND DATE_SUB(NOW(), INTERVAL 30 DAY) THEN si.quantity ELSE 0 END)) / SUM(CASE WHEN s.created_at BETWEEN DATE_SUB(NOW(), INTERVAL 60 DAY) AND DATE_SUB(NOW(), INTERVAL 30 DAY) THEN si.quantity ELSE 0 END) DESC
    LIMIT 10
");
$tendencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ticket médio por período
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as mes,
        AVG(total) as ticket_medio
    FROM sales 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY mes
");
$ticket_medio = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Taxa de conversão por categoria
$stmt = $pdo->query("
    SELECT 
        p.category,
        COUNT(DISTINCT s.id) as vendas,
        COUNT(DISTINCT si.id) as itens,
        AVG(si.quantity) as media_qtd
    FROM sale_items si
    JOIN products p ON p.id = si.product_id
    JOIN sales s ON s.id = si.sale_id
    WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY p.category
    ORDER BY vendas DESC
");
$metricas_categoria = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.analytics-container {
    max-width: 1400px;
    margin: 0 auto;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    padding: 24px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    border-left: 4px solid var(--purple);
}

.page-header h1 {
    color: var(--text-primary);
    margin: 0;
    font-size: 24px;
    font-weight: 700;
}

.page-header span {
    color: var(--text-muted);
    font-size: 13px;
}

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.kpi-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    padding: 24px;
    border-radius: var(--radius-lg);
    position: relative;
    overflow: hidden;
    transition: var(--transition);
}

.kpi-card:hover {
    border-color: var(--border-light);
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.kpi-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
}

.kpi-card.primary::before { background: var(--purple); }
.kpi-card.success::before { background: var(--success); }
.kpi-card.warning::before { background: var(--warning); }
.kpi-card.danger::before { background: var(--danger); }

.kpi-card .title {
    color: var(--text-muted);
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 12px;
}

.kpi-card .value {
    font-size: 32px;
    font-weight: 800;
    color: var(--text-primary);
    letter-spacing: -0.02em;
}

.kpi-card .change {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    margin-top: 12px;
}

.change.up { background: var(--success-light); color: var(--success); }
.change.down { background: var(--danger-light); color: var(--danger); }

.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

@media (max-width: 1100px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
}

.chart-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    padding: 24px;
    border-radius: var(--radius-lg);
}

.chart-card h3 {
    color: var(--text-primary);
    margin-bottom: 20px;
    font-size: 15px;
    font-weight: 600;
}

.section {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    padding: 24px;
    border-radius: var(--radius-lg);
    margin-bottom: 24px;
}

.section h3 {
    color: var(--text-primary);
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border);
    font-size: 15px;
    font-weight: 600;
}

.section table {
    width: 100%;
    border-collapse: collapse;
}

.section th, .section td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid var(--border);
}

.section th {
    background: var(--bg-tertiary);
    color: var(--text-muted);
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.section td {
    color: var(--text-primary);
    font-size: 14px;
}

.section tr:hover td {
    background: var(--bg-tertiary);
}

.trend-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.trend-badge.up { background: var(--success-light); color: var(--success); }
.trend-badge.down { background: var(--danger-light); color: var(--danger); }

.previsao-box {
    background: var(--bg-tertiary);
    padding: 32px;
    border-radius: var(--radius-lg);
    text-align: center;
    color: white;
    margin-bottom: 32px;
}

.previsao-box .label {
    font-size: 14px;
    opacity: 0.9;
}

.previsao-box .value {
    font-size: 48px;
    font-weight: 800;
    margin: 12px 0;
    letter-spacing: -0.02em;
}

.previsao-box .info {
    font-size: 13px;
    opacity: 0.8;
}
</style>

<div class="analytics-container">
    <div class="page-header">
        <h1>📈 Analytics Avançado</h1>
        <span>Última atualização: <?= date('d/m/Y H:i') ?></span>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card primary">
            <div class="title">Vendas Este Mês</div>
            <div class="value"><?= number_format($dados_atual['vendas']) ?></div>
            <div class="change <?= $variacao_vendas >= 0 ? 'up' : 'down' ?>">
                <?= $variacao_vendas >= 0 ? '↑' : '↓' ?> <?= number_format(abs($variacao_vendas), 1) ?>% vs mês anterior
            </div>
        </div>
        
        <div class="kpi-card success">
            <div class="title">Faturação Este Mês</div>
            <div class="value">€<?= number_format($dados_atual['valor'], 0, ',', '.') ?></div>
            <div class="change <?= $variacao_valor >= 0 ? 'up' : 'down' ?>">
                <?= $variacao_valor >= 0 ? '↑' : '↓' ?> <?= number_format(abs($variacao_valor), 1) ?>% vs mês anterior
            </div>
        </div>
        
        <div class="kpi-card warning">
            <div class="title">Ticket Médio</div>
            <div class="value">€<?= $dados_atual['vendas'] > 0 ? number_format($dados_atual['valor'] / $dados_atual['vendas'], 2, ',', '.') : '0,00' ?></div>
            <div class="change up">Objetivo: €25,00</div>
        </div>
        
        <div class="kpi-card danger">
            <div class="title">Previsão Próximo Mês</div>
            <div class="value">€<?= number_format($previsao_proximo_mes, 0, ',', '.') ?></div>
            <div class="change up">Baseado em média móvel</div>
        </div>
    </div>

    <!-- Previsão Destacada -->
    <div class="previsao-box">
        <div class="label">🔮 Previsão de Faturação - <?= date('F Y', strtotime('+1 month')) ?></div>
        <div class="value">€<?= number_format($previsao_proximo_mes, 2, ',', '.') ?></div>
        <div class="info">Baseado na média dos últimos 3 meses com crescimento de 5%</div>
    </div>

    <!-- Gráficos -->
    <div class="charts-grid">
        <div class="chart-card">
            <h3>📊 Evolução Mensal (12 meses)</h3>
            <canvas id="chartMensal" height="200"></canvas>
        </div>
        
        <div class="chart-card">
            <h3>🕐 Vendas por Hora do Dia</h3>
            <canvas id="chartHoras" height="200"></canvas>
        </div>
        
        <div class="chart-card">
            <h3>📅 Vendas por Dia da Semana</h3>
            <canvas id="chartDias" height="200"></canvas>
        </div>
        
        <div class="chart-card">
            <h3>🎫 Evolução do Ticket Médio</h3>
            <canvas id="chartTicket" height="200"></canvas>
        </div>
    </div>

    <!-- Produtos em Tendência -->
    <div class="section">
        <h3>🔥 Produtos em Tendência</h3>
        <table>
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Categoria</th>
                    <th>Últimos 30 dias</th>
                    <th>30 dias anteriores</th>
                    <th>Variação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tendencias as $prod): 
                    $variacao = $prod['vendas_anterior'] > 0 ? (($prod['vendas_30d'] - $prod['vendas_anterior']) / $prod['vendas_anterior']) * 100 : 0;
                ?>
                <tr>
                    <td><?= htmlspecialchars($prod['name']) ?></td>
                    <td><?= htmlspecialchars($prod['category']) ?></td>
                    <td><?= number_format($prod['vendas_30d']) ?></td>
                    <td><?= number_format($prod['vendas_anterior']) ?></td>
                    <td>
                        <span class="trend-badge <?= $variacao >= 0 ? 'up' : 'down' ?>">
                            <?= $variacao >= 0 ? '↑' : '↓' ?> <?= number_format(abs($variacao), 1) ?>%
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Métricas por Categoria -->
    <div class="section">
        <h3>📦 Desempenho por Categoria (30 dias)</h3>
        <table>
            <thead>
                <tr>
                    <th>Categoria</th>
                    <th>Nº Vendas</th>
                    <th>Itens Vendidos</th>
                    <th>Média por Venda</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($metricas_categoria as $cat): ?>
                <tr>
                    <td><?= htmlspecialchars($cat['category']) ?></td>
                    <td><?= number_format($cat['vendas']) ?></td>
                    <td><?= number_format($cat['itens']) ?></td>
                    <td><?= number_format($cat['media_qtd'], 1) ?> un.</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Dados
const meses = <?= json_encode(array_keys($vendas_mensais)) ?>;
const valoresMensais = <?= json_encode(array_column($vendas_mensais, 'valor')) ?>;
const vendasHora = <?= json_encode($vendas_hora) ?>;
const vendasDia = <?= json_encode($vendas_dia_semana) ?>;
const ticketMedio = <?= json_encode($ticket_medio) ?>;
const diasNomes = <?= json_encode($dias_nomes) ?>;

// Theme colors
function getGridColor() {
    return document.documentElement.getAttribute('data-theme') === 'light' ? '#e4e4e7' : '#333';
}

function getTickColor() {
    return document.documentElement.getAttribute('data-theme') === 'light' ? '#52525b' : '#a1a1aa';
}

const gridColor = getGridColor();
const tickColor = getTickColor();

// Gráfico Mensal
new Chart(document.getElementById('chartMensal'), {
    type: 'bar',
    data: {
        labels: meses.map(m => {
            const [ano, mes] = m.split('-');
            return ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'][parseInt(mes)-1];
        }),
        datasets: [{
            label: 'Faturação (€)',
            data: valoresMensais.map(v => parseFloat(v)),
            backgroundColor: 'rgba(161, 161, 170, 0.6)',
            borderColor: '#a1a1aa',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { 
            y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor } }, 
            x: { grid: { color: gridColor }, ticks: { color: tickColor } } 
        }
    }
});

// Gráfico Horas
new Chart(document.getElementById('chartHoras'), {
    type: 'line',
    data: {
        labels: Array.from({length: 24}, (_, i) => i + 'h'),
        datasets: [{
            label: 'Vendas',
            data: Array.from({length: 24}, (_, i) => {
                const h = vendasHora.find(v => parseInt(v.hora) === i);
                return h ? parseInt(h.vendas) : 0;
            }),
            borderColor: '#a1a1aa',
            backgroundColor: 'rgba(161, 161, 170, 0.1)',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { 
            y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor } }, 
            x: { grid: { color: gridColor }, ticks: { color: tickColor } } 
        }
    }
});

// Gráfico Dias da Semana
new Chart(document.getElementById('chartDias'), {
    type: 'doughnut',
    data: {
        labels: vendasDia.map(d => diasNomes[d.dia]),
        datasets: [{
            data: vendasDia.map(d => parseFloat(d.valor)),
            backgroundColor: ['#a1a1aa', '#71717a', '#52525b', '#3f3f46', '#a3a3a3', '#78716c', '#d4d4d8']
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom', labels: { color: tickColor } } }
    }
});

// Gráfico Ticket Médio
new Chart(document.getElementById('chartTicket'), {
    type: 'line',
    data: {
        labels: ticketMedio.map(t => {
            const [ano, mes] = t.mes.split('-');
            return ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'][parseInt(mes)-1];
        }),
        datasets: [{
            label: 'Ticket Médio (€)',
            data: ticketMedio.map(t => parseFloat(t.ticket_medio)),
            borderColor: '#a1a1aa',
            backgroundColor: 'rgba(161, 161, 170, 0.1)',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { 
            y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor } }, 
            x: { grid: { color: gridColor }, ticks: { color: tickColor } } 
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
