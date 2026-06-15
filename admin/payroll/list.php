<?php
/**
 * Página de Gestão de Folha de Pagamento - PREMIUM
 * admin/payroll/list.php
 * Redesigned with global layout integration
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../modules/rh.php';
require_once __DIR__ . '/../../includes/functions.php';

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$message = '';
$error = '';

// Obter mês e ano atual ou do filtro
$current_month = $_GET['month'] ?? date('m');
$current_year = $_GET['year'] ?? date('Y');
$dept_filter = $_GET['dept'] ?? 'all';

// Validar entrada
$current_month = intval($current_month);
$current_year = intval($current_year);

if ($current_month < 1 || $current_month > 12) {
    $current_month = date('m');
}

// Processar ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'generate') {
        $result = generate_payroll($current_month, $current_year);
        if ($result && is_array($result) && count($result) > 0) {
            $message = count($result) . ' folhas de pagamento geradas com sucesso.';
        } else {
            $error = 'Erro ao gerar folhas de pagamento ou já existem registos para este mês.';
        }
    }
    if ($_POST['action'] === 'mark_paid' && isset($_POST['payroll_id'])) {
        try {
            $stmt = $pdo->prepare("UPDATE payroll SET status = 'pago' WHERE id = ?");
            $stmt->execute([intval($_POST['payroll_id'])]);
            $message = 'Pagamento marcado como realizado.';
        } catch (Exception $e) {
            $error = 'Erro ao marcar pagamento.';
        }
    }
}

// Obter folhas de pagamento do mês
try {
    $month_year = sprintf('%04d-%02d', $current_year, $current_month);

    $query = "SELECT p.*, e.name, e.email, e.department, e.position, e.phone
              FROM payroll p
              INNER JOIN employees e ON p.employee_id = e.id
              WHERE p.month = :month_year";
    
    $params = [':month_year' => $month_year];
    
    if ($dept_filter !== 'all') {
        $query .= " AND e.department = :dept";
        $params[':dept'] = $dept_filter;
    }
    
    $query .= " ORDER BY e.department ASC, e.name ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $payroll_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $payroll_records = [];
    $error = "Erro ao obter folhas: " . $e->getMessage();
}

// Obter departamentos únicos
$departments = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department");
    $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Calcular totais
$total_gross = 0;
$total_discounts = 0;
$total_net = 0;
$total_paid = 0;
$total_pending = 0;
$dept_breakdown = [];

foreach ($payroll_records as $record) {
    $gross = ($record['base_salary'] ?? 0) + ($record['overtime_amount'] ?? 0) + ($record['bonus'] ?? 0);
    $total_gross += $gross;
    $total_discounts += $record['deductions'] ?? 0;
    $total_net += $record['net_salary'] ?? 0;
    
    if (strtolower($record['status'] ?? '') === 'pago') {
        $total_paid++;
    } else {
        $total_pending++;
    }
    
    $dept = $record['department'] ?? 'Sem Departamento';
    if (!isset($dept_breakdown[$dept])) $dept_breakdown[$dept] = 0;
    $dept_breakdown[$dept] += $record['net_salary'] ?? 0;
}

// Meses para seleção
$months = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];

$page_title = 'Folha de Pagamento';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .payroll-controls {
        display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 24px;
    }
    .payroll-controls form { display: flex; gap: 10px; align-items: center; }
    .payroll-search {
        flex: 1; min-width: 200px; position: relative;
    }
    .payroll-search input {
        width: 100%; padding: 10px 16px 10px 40px;
        background: var(--bg-secondary); border: 1px solid var(--border);
        border-radius: var(--radius); color: var(--text-primary); font-size: 14px;
    }
    .payroll-search input:focus {
        outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-ring);
    }
    .payroll-search svg {
        position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
        color: var(--text-muted); width: 18px; height: 18px;
    }
    .summary-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px; }
    @media (max-width: 1200px) { .summary-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 768px) { .summary-grid { grid-template-columns: 1fr 1fr; } }
    
    .payroll-table-wrapper {
        background: var(--bg-secondary); border: 1px solid var(--border);
        border-radius: var(--radius-lg); overflow: hidden;
    }
    .payroll-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .payroll-table th {
        text-align: left; padding: 12px 14px; font-size: 10px; font-weight: 600;
        color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;
        background: var(--bg-tertiary); border-bottom: 1px solid var(--border);
        cursor: pointer; user-select: none; white-space: nowrap;
    }
    .payroll-table th:hover { color: var(--text-primary); }
    .payroll-table td {
        padding: 12px 14px; font-size: 13px; border-bottom: 1px solid var(--border);
    }
    .payroll-table tbody tr:hover td { background: var(--bg-hover); }
    .payroll-table tbody tr:last-child td { border-bottom: none; }
    .payroll-table .number {
        text-align: right; font-family: 'Plus Jakarta Sans', monospace;
        font-size: 13px; font-variant-numeric: tabular-nums;
    }
    .payroll-table .total-row td {
        background: var(--bg-tertiary); font-weight: 700; border-top: 2px solid var(--accent);
    }
    .employee-cell { display: flex; align-items: center; gap: 12px; }
    .employee-avatar {
        width: 36px; height: 36px; border-radius: 50%;
        background: linear-gradient(135deg, var(--accent), var(--purple));
        display: flex; align-items: center; justify-content: center;
        font-weight: 600; font-size: 13px; color: white; flex-shrink: 0;
    }
    .employee-name { font-weight: 600; color: var(--text-primary); font-size: 14px; }
    .employee-email { font-size: 12px; color: var(--text-muted); }
    .dept-badge {
        display: inline-flex; padding: 4px 10px; border-radius: 6px;
        font-size: 12px; font-weight: 500; background: var(--bg-tertiary);
        color: var(--text-secondary); border: 1px solid var(--border);
    }
    .status-badge {
        padding: 4px 12px; border-radius: 20px; font-weight: 600;
        font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;
    }
    .status-pago { background: var(--success-subtle); color: var(--success); }
    .status-pendente { background: var(--warning-subtle); color: var(--warning); }
    .action-btn {
        padding: 6px 12px; background: var(--bg-tertiary); border: 1px solid var(--border);
        border-radius: 6px; color: var(--text-secondary); font-size: 12px; font-weight: 500;
        cursor: pointer; text-decoration: none; transition: all 0.2s;
        display: inline-flex; align-items: center; gap: 4px;
    }
    .action-btn:hover { background: var(--accent); color: white; border-color: var(--accent); }
    .action-btn.pay-btn:hover { background: var(--success); border-color: var(--success); }
    .charts-row { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px; }
    @media (max-width: 1024px) { .charts-row { grid-template-columns: 1fr; } }
    .empty-state { text-align: center; padding: 80px 20px; color: var(--text-muted); }
    .empty-state-icon { font-size: 56px; margin-bottom: 16px; opacity: 0.5; }
    @media print {
        .app-sidebar, .app-header, .payroll-controls, .action-btn, .charts-row { display: none !important; }
        .app-main { margin-left: 0 !important; }
    }
</style>

<!-- Back button -->
<div style="margin-bottom: 24px;">
    <a href="/admin/rh/equipa.php" style="display:inline-flex;align-items:center;gap:8px;font-size:11px;font-weight:600;letter-spacing:.15em;text-transform:uppercase;color:var(--text-muted);border:1px solid var(--border);border-radius:8px;padding:9px 16px;background:var(--bg-secondary);text-decoration:none;transition:border-color .15s,color .15s;" onmouseover="this.style.borderColor='var(--border-light)';this.style.color='var(--text-primary)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-muted)'">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Voltar à Equipa
    </a>
</div>

<!-- Title & Actions -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-size: 24px; font-weight: 700; letter-spacing: -0.02em;"><?= $months[$current_month] ?> <?= $current_year ?></h2>
        <p style="color: var(--text-muted); font-size: 14px; margin-top: 4px;"><?= count($payroll_records) ?> colaboradores</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <button onclick="window.print()" class="btn btn-secondary" style="padding: 8px 16px; font-size: 13px;">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Imprimir
        </button>
        <button onclick="exportCSV()" class="btn btn-secondary" style="padding: 8px 16px; font-size: 13px;">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Exportar CSV
        </button>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-success fade-in" style="margin-bottom: 20px;">
    <span class="alert-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
    <div class="alert-content"><div class="alert-message"><?= htmlspecialchars($message) ?></div></div>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger fade-in" style="margin-bottom: 20px;">
    <span class="alert-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
    <div class="alert-content"><div class="alert-message"><?= htmlspecialchars($error) ?></div></div>
</div>
<?php endif; ?>

<!-- Controls -->
<div class="payroll-controls">
    <form method="get">
        <select name="month" class="form-select" style="width: auto;">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $current_month == $m ? 'selected' : '' ?>><?= $months[$m] ?></option>
            <?php endfor; ?>
        </select>
        <select name="year" class="form-select" style="width: auto;">
            <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                <option value="<?= $y ?>" <?= $current_year == $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
        <select name="dept" class="form-select" style="width: auto;">
            <option value="all">Todos os Departamentos</option>
            <?php foreach ($departments as $dept): ?>
                <option value="<?= htmlspecialchars($dept) ?>" <?= $dept_filter === $dept ? 'selected' : '' ?>><?= htmlspecialchars($dept) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">Filtrar</button>
    </form>

    <div class="payroll-search">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" id="searchInput" placeholder="Pesquisar colaborador... (Ctrl+F)" oninput="filterTable(this.value)">
    </div>
    
    <form method="post" style="margin-left: auto;">
        <input type="hidden" name="action" value="generate">
        <button type="submit" class="btn btn-primary" style="padding: 10px 20px; background: var(--success);" onclick="return confirm('Gerar folhas de pagamento para todos os colaboradores?');">
            + Gerar Folhas
        </button>
    </form>
</div>

<!-- Summary Stats -->
<?php if (!empty($payroll_records)): ?>
<div class="summary-grid">
    <div class="stat-card">
        <div class="stat-header"><div class="stat-icon blue"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div>
        <div class="stat-value"><?= number_format($total_gross, 2, ',', '.') ?>€</div>
        <div class="stat-label">Total Bruto</div>
    </div>
    <div class="stat-card">
        <div class="stat-header"><div class="stat-icon red"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg></div></div>
        <div class="stat-value" style="color: var(--danger);"><?= number_format($total_discounts, 2, ',', '.') ?>€</div>
        <div class="stat-label">Total Descontos</div>
    </div>
    <div class="stat-card">
        <div class="stat-header"><div class="stat-icon green"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><circle cx="12" cy="12" r="3"/></svg></div></div>
        <div class="stat-value" style="color: var(--success);"><?= number_format($total_net, 2, ',', '.') ?>€</div>
        <div class="stat-label">Total Líquido</div>
    </div>
    <div class="stat-card">
        <div class="stat-header"><div class="stat-icon green"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div></div>
        <div class="stat-value"><?= $total_paid ?></div>
        <div class="stat-label">Pagos</div>
    </div>
    <div class="stat-card">
        <div class="stat-header"><div class="stat-icon orange"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
        <?php if ($total_pending > 0): ?><div class="stat-change negative">Pendente</div><?php endif; ?></div>
        <div class="stat-value"><?= $total_pending ?></div>
        <div class="stat-label">Pendentes</div>
    </div>
</div>

<!-- Charts -->
<div class="charts-row">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Distribuição Salarial</h3>
            <span style="font-size: 12px; color: var(--text-muted);"><?= $months[$current_month] ?> <?= $current_year ?></span>
        </div>
        <div class="card-body"><div style="height: 250px; position: relative;"><canvas id="salaryChart"></canvas></div></div>
    </div>
    <div class="card">
        <div class="card-header"><h3 class="card-title">Por Departamento</h3></div>
        <div class="card-body"><div style="height: 250px; position: relative;"><canvas id="deptChart"></canvas></div></div>
    </div>
</div>
<?php endif; ?>

<!-- Payroll Table -->
<?php if (!empty($payroll_records)): ?>
<div class="payroll-table-wrapper">
    <table class="payroll-table" id="payrollTable">
        <thead>
            <tr>
                <th onclick="sortTable(0)" style="width:220px">Colaborador ↕</th>
                <th onclick="sortTable(1)" style="width:120px">Departamento ↕</th>
                <th class="number" onclick="sortTable(2)" style="width:110px">Salário Base ↕</th>
                <th class="number" onclick="sortTable(3)" style="width:90px">Bónus ↕</th>
                <th class="number" onclick="sortTable(4)" style="width:90px">Descontos ↕</th>
                <th class="number" onclick="sortTable(5)" style="width:100px">Líquido ↕</th>
                <th style="width:100px">Estado</th>
                <th style="width:110px">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($payroll_records as $record): ?>
                <?php 
                    $nameParts = explode(' ', $record['name'] ?? '');
                    $initials = strtoupper(substr($nameParts[0] ?? '', 0, 1) . substr(end($nameParts) ?: '', 0, 1));
                    $bonus = ($record['overtime_amount'] ?? 0) + ($record['bonus'] ?? 0);
                ?>
                <tr data-name="<?= strtolower(htmlspecialchars($record['name'] ?? '')) ?>">
                    <td>
                        <div class="employee-cell">
                            <div class="employee-avatar"><?= $initials ?></div>
                            <div>
                                <div class="employee-name"><?= htmlspecialchars($record['name'] ?? '') ?></div>
                                <div class="employee-email"><?= htmlspecialchars($record['email'] ?? '') ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="dept-badge"><?= htmlspecialchars($record['department'] ?? 'N/A') ?></span></td>
                    <td class="number"><?= number_format($record['base_salary'] ?? 0, 2, ',', '.') ?>€</td>
                    <td class="number" style="color: var(--success);">+<?= number_format($bonus, 2, ',', '.') ?>€</td>
                    <td class="number" style="color: var(--danger);">-<?= number_format($record['deductions'] ?? 0, 2, ',', '.') ?>€</td>
                    <td class="number"><strong style="color: var(--success);"><?= number_format($record['net_salary'] ?? 0, 2, ',', '.') ?>€</strong></td>
                    <td>
                        <?php if (strtolower($record['status'] ?? '') === 'pago'): ?>
                            <span class="status-badge status-pago"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Pago</span>
                        <?php else: ?>
                            <span class="status-badge status-pendente">Pendente</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 6px;">
                            <a href="/admin/payroll/view.php?id=<?= $record['id'] ?>" class="action-btn">Ver</a>
                            <?php if (strtolower($record['status'] ?? '') !== 'pago'): ?>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="mark_paid">
                                <input type="hidden" name="payroll_id" value="<?= $record['id'] ?>">
                                <button type="submit" class="action-btn pay-btn" onclick="return confirm('Marcar como pago?')">Pagar</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="2"><strong>TOTAL (<?= count($payroll_records) ?> colaboradores)</strong></td>
                <td class="number"><strong><?= number_format(array_sum(array_column($payroll_records, 'base_salary')), 2, ',', '.') ?>€</strong></td>
                <td class="number"></td>
                <td class="number"><strong style="color: var(--danger);">-<?= number_format($total_discounts, 2, ',', '.') ?>€</strong></td>
                <td class="number"><strong style="color: var(--success);"><?= number_format($total_net, 2, ',', '.') ?>€</strong></td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="empty-state card" style="border: 1px solid var(--border);">
    <div class="empty-state-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></div>
    <p>Nenhuma folha de pagamento para <strong><?= $months[$current_month] ?>/<?= $current_year ?></strong></p>
    <form method="post" style="display: inline;">
        <input type="hidden" name="action" value="generate">
        <button type="submit" class="btn btn-primary" onclick="return confirm('Gerar folhas de pagamento?')">+ Gerar Folhas do Mês</button>
    </form>
</div>
<?php endif; ?>

<script>
function filterTable(q) {
    document.querySelectorAll('#payrollTable tbody tr:not(.total-row)').forEach(row => {
        row.style.display = (row.getAttribute('data-name') || '').includes(q.toLowerCase()) ? '' : 'none';
    });
}

let sortDir = {};
function sortTable(col) {
    const tbody = document.querySelector('#payrollTable tbody');
    const rows = Array.from(tbody.querySelectorAll('tr:not(.total-row)'));
    const total = tbody.querySelector('.total-row');
    sortDir[col] = !sortDir[col];
    const d = sortDir[col] ? 1 : -1;
    rows.sort((a, b) => {
        let av = a.cells[col]?.textContent.trim() || '', bv = b.cells[col]?.textContent.trim() || '';
        const an = parseFloat(av.replace(/[^\d.,-]/g, '').replace(',', '.')), bn = parseFloat(bv.replace(/[^\d.,-]/g, '').replace(',', '.'));
        return (!isNaN(an) && !isNaN(bn)) ? (an - bn) * d : av.localeCompare(bv, 'pt') * d;
    });
    rows.forEach(r => tbody.appendChild(r));
    if (total) tbody.appendChild(total);
}

function exportCSV() {
    const table = document.getElementById('payrollTable');
    if (!table) return alert('Nenhum dado');
    let csv = [];
    table.querySelectorAll('tr').forEach(row => {
        csv.push(Array.from(row.querySelectorAll('th,td')).map(c => '"' + c.textContent.trim().replace(/"/g, '""') + '"').join(';'));
    });
    const blob = new Blob(['\uFEFF' + csv.join('\n')], {type: 'text/csv;charset=utf-8;'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'folha_pagamento_<?= $current_year ?>_<?= sprintf('%02d', $current_month) ?>.csv';
    a.click();
}

<?php if (!empty($payroll_records)): ?>
const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
const gridColor = isDark ? '#27272a' : '#e4e4e7';
const txtColor = isDark ? '#71717a' : '#71717a';

new Chart(document.getElementById('salaryChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($r) => explode(' ', $r['name'])[0], $payroll_records)) ?>,
        datasets: [{
            label: 'Salário Líquido',
            data: <?= json_encode(array_map(fn($r) => floatval($r['net_salary'] ?? 0), $payroll_records)) ?>,
            backgroundColor: <?= json_encode(array_map(fn($r) => floatval($r['net_salary'] ?? 0), $payroll_records)) ?>.map((_, i) => {
                return ['rgba(161,161,170,0.7)','rgba(113,113,122,0.7)','rgba(82,82,91,0.7)','rgba(63,63,70,0.7)','rgba(163,163,163,0.7)'][i % 5];
            }),
            borderRadius: 6, borderSkipped: false
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ctx.raw.toLocaleString('pt-PT', {minimumFractionDigits: 2}) + '€' }}},
        scales: {
            y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: txtColor, callback: v => v.toLocaleString() + '€' }},
            x: { grid: { display: false }, ticks: { color: txtColor, maxRotation: 45 }}
        }
    }
});

new Chart(document.getElementById('deptChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_keys($dept_breakdown)) ?>,
        datasets: [{
            data: <?= json_encode(array_values($dept_breakdown)) ?>,
            backgroundColor: ['#a1a1aa','#71717a','#52525b','#3f3f46','#a3a3a3','#78716c','#d4d4d8'],
            borderWidth: 0, hoverOffset: 8
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false, cutout: '65%',
        plugins: {
            legend: { position: 'bottom', labels: { color: txtColor, padding: 12, font: { size: 12 }}},
            tooltip: { callbacks: { label: ctx => ctx.label + ': ' + ctx.raw.toLocaleString('pt-PT', {minimumFractionDigits: 2}) + '€' }}
        }
    }
});
<?php endif; ?>

document.addEventListener('keydown', e => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'f') {
        e.preventDefault();
        document.getElementById('searchInput')?.focus();
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
