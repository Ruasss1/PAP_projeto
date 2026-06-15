<?php
/**
 * Módulo de Metas de Vendas
 * modules/metas.php
 */
$page_title = 'Metas';
require_once __DIR__ . '/../includes/header.php';

$pdo = db_connect();

// Criar tabela de metas se não existir
$pdo->exec("
    CREATE TABLE IF NOT EXISTS sales_goals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT,
        month VARCHAR(7),
        goal_amount DECIMAL(10,2) DEFAULT 0,
        goal_sales INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_goal (employee_id, month)
    )
");

// Processar formulário
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'set_goal') {
        $employee_id = intval($_POST['employee_id']);
        $month = $_POST['month'];
        $goal_amount = floatval($_POST['goal_amount']);
        $goal_sales = intval($_POST['goal_sales']);
        
        $stmt = $pdo->prepare("
            INSERT INTO sales_goals (employee_id, month, goal_amount, goal_sales)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE goal_amount = ?, goal_sales = ?
        ");
        $stmt->execute([$employee_id, $month, $goal_amount, $goal_sales, $goal_amount, $goal_sales]);
        $message = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Meta definida com sucesso!';
    }
}

// Obter mês atual
$mes_atual = $_GET['mes'] ?? date('Y-m');

// Obter funcionários com vendas
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.name,
        r.name as role_name,
        COALESCE(g.goal_amount, 0) as meta_valor,
        COALESCE(g.goal_sales, 0) as meta_vendas,
        0 as total_vendas,
        0 as total_valor
    FROM users u
    LEFT JOIN roles r ON r.id = u.role_id
    LEFT JOIN sales_goals g ON g.employee_id = u.id AND g.month = ?
    WHERE r.name IN ('Caixa', 'Gerente', 'Admin')
    GROUP BY u.id
    ORDER BY COALESCE(g.goal_amount, 0) DESC
");
$stmt->execute([$mes_atual]);
$funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Meta global da loja
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(goal_amount), 0) as meta_total,
        COALESCE(SUM(goal_sales), 0) as meta_vendas
    FROM sales_goals 
    WHERE month = ? AND employee_id IS NOT NULL
");
$stmt->execute([$mes_atual]);
$meta_global = $stmt->fetch(PDO::FETCH_ASSOC);

// Vendas totais do mês
$stmt = $pdo->prepare("
    SELECT COUNT(*) as vendas, COALESCE(SUM(total), 0) as valor
    FROM sales 
    WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
");
$stmt->execute([$mes_atual]);
$vendas_mes = $stmt->fetch(PDO::FETCH_ASSOC);

// Ranking mensal
$stmt = $pdo->prepare("
    SELECT 
        u.name,
        0 as vendas,
        0 as valor
    FROM users u
    LEFT JOIN roles r ON r.id = u.role_id
    WHERE r.name IN ('Caixa', 'Gerente', 'Admin')
    LIMIT 10
");
$stmt->execute([]);
$ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.metas-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #f59e0b;
}

.header h1 {
    color: #f59e0b;
    margin: 0;
    font-size: 24px;
}

.mes-selector {
    display: flex;
    gap: 10px;
    align-items: center;
}

.mes-selector input {
    padding: 10px 15px;
    border-radius: 6px;
    border: 1px solid #333;
    background: #222;
    color: white;
}

.mes-selector button {
    padding: 10px 20px;
    border-radius: 6px;
    border: none;
    background: #f59e0b;
    color: black;
    cursor: pointer;
    font-weight: 600;
}

.message {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, #1a1a1a, #222);
    padding: 25px;
    border-radius: 12px;
    text-align: center;
}

.stat-card .value {
    font-size: 32px;
    font-weight: 700;
    color: #f59e0b;
}

.stat-card .label {
    color: #888;
    font-size: 14px;
    margin-top: 5px;
}

.progress-bar {
    background: #333;
    border-radius: 10px;
    height: 20px;
    overflow: hidden;
    margin-top: 10px;
}

.progress-bar .fill {
    height: 100%;
    border-radius: 10px;
    transition: width 0.5s ease;
}

.section {
    background: #1a1a1a;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.section h3 {
    color: #f59e0b;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #333;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #333;
}

th {
    background: #222;
    color: #f59e0b;
    font-weight: 600;
}

tr:hover {
    background: #222;
}

.btn {
    padding: 8px 16px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    font-size: 13px;
}

.btn-primary { background: #a1a1aa; color: white; }
.btn-success { background: #22c55e; color: white; }
.btn-warning { background: #f59e0b; color: black; }

.ranking-card {
    display: flex;
    align-items: center;
    padding: 15px;
    background: #222;
    border-radius: 8px;
    margin-bottom: 10px;
}

.ranking-card .position {
    font-size: 24px;
    font-weight: 700;
    width: 50px;
    text-align: center;
}

.ranking-card .position.gold { color: #fbbf24; }
.ranking-card .position.silver { color: #9ca3af; }
.ranking-card .position.bronze { color: #cd7f32; }

.ranking-card .info {
    flex: 1;
    margin-left: 15px;
}

.ranking-card .name {
    font-weight: 600;
    color: white;
}

.ranking-card .stats {
    font-size: 13px;
    color: #888;
}

.ranking-card .valor {
    font-size: 18px;
    font-weight: 700;
    color: #22c55e;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.8);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.active { display: flex; }

.modal-content {
    background: #1a1a1a;
    padding: 30px;
    border-radius: 12px;
    max-width: 400px;
    width: 90%;
}

.modal-content h3 {
    color: #f59e0b;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    color: #888;
    margin-bottom: 5px;
    font-size: 14px;
}

.form-group input {
    width: 100%;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #333;
    background: #222;
    color: white;
    box-sizing: border-box;
}

.medal {
    font-size: 24px;
}
</style>

<div class="metas-container">
    <div class="header">
        <h1><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg> Metas de Vendas</h1>
        <form class="mes-selector" method="GET">
            <input type="month" name="mes" value="<?= htmlspecialchars($mes_atual) ?>">
            <button type="submit">Filtrar</button>
        </form>
    </div>

    <?php if ($message): ?>
    <div class="message"><?= $message ?></div>
    <?php endif; ?>

    <!-- Estatísticas Globais -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="value">€<?= number_format($vendas_mes['valor'], 0, ',', '.') ?></div>
            <div class="label">Faturação do Mês</div>
            <?php 
            $percentagem = $meta_global['meta_total'] > 0 ? min(100, ($vendas_mes['valor'] / $meta_global['meta_total']) * 100) : 0;
            $cor = $percentagem >= 100 ? '#22c55e' : ($percentagem >= 70 ? '#f59e0b' : '#ef4444');
            ?>
            <div class="progress-bar">
                <div class="fill" style="width: <?= $percentagem ?>%; background: <?= $cor ?>;"></div>
            </div>
            <small style="color: #888;"><?= number_format($percentagem, 1) ?>% da meta global (€<?= number_format($meta_global['meta_total'], 0, ',', '.') ?>)</small>
        </div>
        
        <div class="stat-card">
            <div class="value"><?= number_format($vendas_mes['vendas']) ?></div>
            <div class="label">Total de Vendas</div>
            <?php 
            $percentagem_v = $meta_global['meta_vendas'] > 0 ? min(100, ($vendas_mes['vendas'] / $meta_global['meta_vendas']) * 100) : 0;
            ?>
            <div class="progress-bar">
                <div class="fill" style="width: <?= $percentagem_v ?>%; background: #a1a1aa;"></div>
            </div>
            <small style="color: #888;"><?= number_format($percentagem_v, 1) ?>% da meta (<?= number_format($meta_global['meta_vendas']) ?> vendas)</small>
        </div>
        
        <div class="stat-card">
            <div class="value"><?= count($funcionarios) ?></div>
            <div class="label">Funcionários Ativos</div>
        </div>
        
        <div class="stat-card">
            <div class="value" style="color: <?= $percentagem >= 100 ? '#22c55e' : '#f59e0b' ?>;">
                <?= $percentagem >= 100 ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> ATINGIDA!' : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg> Em Progresso' ?>
            </div>
            <div class="label">Estado da Meta</div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
        <!-- Metas por Funcionário -->
        <div class="section">
            <h3><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Metas Individuais - <?= date('F Y', strtotime($mes_atual . '-01')) ?></h3>
            <table>
                <thead>
                    <tr>
                        <th>Funcionário</th>
                        <th>Cargo</th>
                        <th>Meta (€)</th>
                        <th>Realizado</th>
                        <th>Progresso</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($funcionarios as $func): 
                        $progresso = $func['meta_valor'] > 0 ? min(100, ($func['total_valor'] / $func['meta_valor']) * 100) : 0;
                        $cor_prog = $progresso >= 100 ? '#22c55e' : ($progresso >= 70 ? '#f59e0b' : '#ef4444');
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($func['name']) ?></td>
                        <td><?= htmlspecialchars($func['role_name']) ?></td>
                        <td>€<?= number_format($func['meta_valor'], 0, ',', '.') ?></td>
                        <td>€<?= number_format($func['total_valor'], 0, ',', '.') ?> (<?= $func['total_vendas'] ?> vendas)</td>
                        <td style="width: 200px;">
                            <div class="progress-bar" style="height: 12px;">
                                <div class="fill" style="width: <?= $progresso ?>%; background: <?= $cor_prog ?>;"></div>
                            </div>
                            <small style="color: <?= $cor_prog ?>;"><?= number_format($progresso, 1) ?>%</small>
                        </td>
                        <td>
                            <button class="btn btn-warning" onclick="openModal(<?= $func['id'] ?>, '<?= htmlspecialchars($func['name']) ?>', <?= $func['meta_valor'] ?>, <?= $func['meta_vendas'] ?>)">
                                Definir Meta
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Ranking -->
        <div class="section">
            <h3><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="8 21 12 21 16 21"/><line x1="12" y1="17" x2="12" y2="21"/><path d="M7 4h10v7a5 5 0 0 1-10 0V4z"/><path d="M5 4a2 2 0 0 0 0 4h-.5"/><path d="M19 4a2 2 0 0 1 0 4h.5"/></svg> Ranking do Mês</h3>
            <?php foreach ($ranking as $i => $r): ?>
            <div class="ranking-card">
                <div class="position <?= $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : '')) ?>">
                    <?php if ($i === 0): ?>1.<?php elseif ($i === 1): ?>2.<?php elseif ($i === 2): ?>3.<?php else: ?>#<?= $i + 1 ?><?php endif; ?>
                </div>
                <div class="info">
                    <div class="name"><?= htmlspecialchars($r['name']) ?></div>
                    <div class="stats"><?= $r['vendas'] ?> vendas</div>
                </div>
                <div class="valor">€<?= number_format($r['valor'], 0, ',', '.') ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modal Definir Meta -->
<div class="modal" id="modalMeta">
    <div class="modal-content">
        <h3><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg> Definir Meta</h3>
        <form method="POST">
            <input type="hidden" name="action" value="set_goal">
            <input type="hidden" name="employee_id" id="modal_employee_id">
            <input type="hidden" name="month" value="<?= htmlspecialchars($mes_atual) ?>">
            
            <div class="form-group">
                <label>Funcionário</label>
                <input type="text" id="modal_employee_name" readonly>
            </div>
            
            <div class="form-group">
                <label>Meta de Faturação (€)</label>
                <input type="number" name="goal_amount" id="modal_goal_amount" step="0.01" min="0" required>
            </div>
            
            <div class="form-group">
                <label>Meta de Vendas (nº)</label>
                <input type="number" name="goal_sales" id="modal_goal_sales" min="0" required>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-success" style="flex: 1;">Guardar</button>
                <button type="button" class="btn" style="flex: 1; background: #333; color: white;" onclick="closeModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id, name, goalAmount, goalSales) {
    document.getElementById('modal_employee_id').value = id;
    document.getElementById('modal_employee_name').value = name;
    document.getElementById('modal_goal_amount').value = goalAmount || '';
    document.getElementById('modal_goal_sales').value = goalSales || '';
    document.getElementById('modalMeta').classList.add('active');
}

function closeModal() {
    document.getElementById('modalMeta').classList.remove('active');
}

// Fechar ao clicar fora
document.getElementById('modalMeta').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
