<?php
/**
 * CONTROLO DE PONTO
 */
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();
$current_store_id = get_current_store_id();
$page_title = 'Controlo de Ponto';
$error = ''; $success = '';

// ── Ações ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = $_POST['action'] ?? '';
    $employee_id = intval($_POST['employee_id'] ?? 0);

    if ($action === 'clock_in' && $employee_id) {
        // Verificar se já tem entrada aberta hoje
        $open = $pdo->prepare('SELECT id FROM attendance WHERE employee_id = ? AND clock_out IS NULL AND DATE(clock_in) = CURDATE()');
        $open->execute([$employee_id]); $open = $open->fetchColumn();
        if ($open) {
            $error = 'Este funcionário já tem uma entrada aberta hoje.';
        } else {
            $pdo->prepare('INSERT INTO attendance (employee_id, store_id, clock_in, notes) VALUES (?,?,NOW(),?)')->execute([$employee_id, $current_store_id, trim($_POST['notes'] ?? '')]);
            $success = 'Entrada registada às ' . date('H:i:s');
        }
    } elseif ($action === 'clock_out' && $employee_id) {
        $open = $pdo->prepare('SELECT id FROM attendance WHERE employee_id = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1');
        $open->execute([$employee_id]); $open = $open->fetchColumn();
        if (!$open) {
            $error = 'Nenhuma entrada aberta para este funcionário.';
        } else {
            $break_min = max(0, intval($_POST['break_minutes'] ?? 0));
            $pdo->prepare('UPDATE attendance SET clock_out = NOW(), break_minutes = ?, overtime_minutes = GREATEST(0, TIMESTAMPDIFF(MINUTE, clock_in, NOW()) - break_minutes - (SELECT value*60 FROM settings WHERE `key`="work_hours_per_day" LIMIT 1)) WHERE id = ?')->execute([$break_min, $open]);
            $success = 'Saída registada às ' . date('H:i:s');
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['attendance_id'] ?? 0);
        if ($id) { $pdo->prepare('DELETE FROM attendance WHERE id = ?')->execute([$id]); $success = 'Registo eliminado.'; }
    }
}

// Lista de funcionários
$employees = $pdo->prepare('SELECT id, name, role, department FROM employees WHERE store_id = ? AND (status = "Ativo" OR status = "active") ORDER BY name');
$employees->execute([$current_store_id]); $employees = $employees->fetchAll();

// Quem está presente agora
$present = $pdo->prepare('SELECT a.*, e.name as emp_name, e.role FROM attendance a JOIN employees e ON e.id = a.employee_id WHERE a.store_id = ? AND a.clock_out IS NULL ORDER BY a.clock_in ASC');
$present->execute([$current_store_id]); $present = $present->fetchAll();

// Filtros histórico
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to   = $_GET['date_to']   ?? date('Y-m-d');
$emp_filter = intval($_GET['employee_id'] ?? 0);

// Export CSV
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ponto_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['Funcionário','Data','Entrada','Saída','Pausa (min)','Total (h)','Horas Extra (h)','Notas'], ';');
    $q = "SELECT a.*, e.name as emp_name FROM attendance a JOIN employees e ON e.id = a.employee_id WHERE a.store_id = ? AND DATE(a.clock_in) BETWEEN ? AND ?";
    $par = [$current_store_id, $date_from, $date_to];
    if ($emp_filter) { $q .= ' AND a.employee_id = ?'; $par[] = $emp_filter; }
    $q .= ' ORDER BY a.clock_in DESC';
    $st = $pdo->prepare($q); $st->execute($par);
    while ($r = $st->fetch()) {
        $total_min = $r['clock_out'] ? (int)((strtotime($r['clock_out'])-strtotime($r['clock_in']))/60) - $r['break_minutes'] : 0;
        fputcsv($out, [$r['emp_name'], date('d/m/Y',strtotime($r['clock_in'])), date('H:i',strtotime($r['clock_in'])), $r['clock_out']?date('H:i',strtotime($r['clock_out'])):'Em curso', $r['break_minutes'], number_format($total_min/60,2,',','.'), number_format($r['overtime_minutes']/60,2,',','.'), $r['notes']], ';');
    }
    fclose($out); exit;
}

$q = "SELECT a.*, e.name as emp_name, e.role, CASE WHEN a.clock_out IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, a.clock_in, a.clock_out) - a.break_minutes ELSE NULL END as total_min FROM attendance a JOIN employees e ON e.id = a.employee_id WHERE a.store_id = ? AND DATE(a.clock_in) BETWEEN ? AND ?";
$par = [$current_store_id, $date_from, $date_to];
if ($emp_filter) { $q .= ' AND a.employee_id = ?'; $par[] = $emp_filter; }
$q .= ' ORDER BY a.clock_in DESC LIMIT 200';
$stmt = $pdo->prepare($q); $stmt->execute($par); $records = $stmt->fetchAll();

// Resumo por funcionário
$summary_q = "SELECT e.name, SUM(CASE WHEN a.clock_out IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,a.clock_in,a.clock_out)-a.break_minutes ELSE 0 END) as total_min, SUM(a.overtime_minutes) as overtime_min, COUNT(*) as days FROM attendance a JOIN employees e ON e.id = a.employee_id WHERE a.store_id = ? AND DATE(a.clock_in) BETWEEN ? AND ? GROUP BY a.employee_id, e.name ORDER BY total_min DESC";
$ss = $pdo->prepare($summary_q); $ss->execute([$current_store_id, $date_from, $date_to]); $summary = $ss->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.present-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:12px; margin-bottom:24px; }
.present-card { background:var(--bg-secondary); border:1px solid var(--success); border-radius:10px; padding:16px; display:flex; align-items:center; gap:12px; }
.present-dot  { width:10px; height:10px; background:var(--success); border-radius:50%; flex-shrink:0; animation:blink 1.5s infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
.clock-form   { background:var(--bg-secondary); border:1px solid var(--border); border-radius:12px; padding:20px; margin-bottom:24px; }
</style>

<?php if ($error): ?><div class="alert alert-danger" style="margin-bottom:16px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> <?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success" style="margin-bottom:16px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> <?= htmlspecialchars($success) ?></div><?php endif; ?>

<!-- Presentes agora -->
<h3 style="font-size:14px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px"><svg width="8" height="8" viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="4"/></svg> Presentes agora (<?= count($present) ?>)</h3>
<?php if (empty($present)): ?>
<div style="color:var(--text-muted);margin-bottom:24px">Nenhum funcionário com entrada em aberto.</div>
<?php else: ?>
<div class="present-grid" style="margin-bottom:24px">
<?php foreach ($present as $p):
    $mins = (int)((time()-strtotime($p['clock_in']))/60);
    $h = floor($mins/60); $m = $mins%60;
?>
    <div class="present-card">
        <div class="present-dot"></div>
        <div>
            <div style="font-weight:600;font-size:14px"><?= htmlspecialchars($p['emp_name']) ?></div>
            <div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($p['role']) ?></div>
            <div style="font-size:12px;color:var(--success)">Desde <?= date('H:i', strtotime($p['clock_in'])) ?> · <?= $h ?>h<?= str_pad($m,2,'0',STR_PAD_LEFT) ?>m</div>
        </div>
        <form method="post" style="margin-left:auto">
            <input type="hidden" name="action" value="clock_out">
            <input type="hidden" name="employee_id" value="<?= $p['employee_id'] ?>">
            <input type="number" name="break_minutes" class="form-input" placeholder="Pausa(min)" min="0" value="0" style="width:90px;font-size:12px;padding:4px 8px;margin-bottom:4px">
            <button type="submit" class="btn btn-secondary" style="width:100%;font-size:12px;padding:5px">Saída</button>
        </form>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Registar -->
<div class="clock-form">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:16px">Registar Entrada</h3>
    <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
        <input type="hidden" name="action" value="clock_in">
        <div style="min-width:200px">
            <label class="form-label">Funcionário</label>
            <select name="employee_id" class="form-select" required>
                <option value="">— Selecionar —</option>
                <?php foreach ($employees as $e): ?>
                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1;min-width:200px">
            <label class="form-label">Notas (opcional)</label>
            <input type="text" name="notes" class="form-input" placeholder="Ex: Entrada via portão traseiro">
        </div>
        <button type="submit" class="btn btn-primary"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Registar Entrada</button>
    </form>
</div>

<!-- Resumo período -->
<?php if (!empty($summary)): ?>
<div class="card" style="margin-bottom:24px">
    <div class="card-header"><h3 class="card-title">Resumo por Funcionário</h3></div>
    <div class="table-container">
        <table class="table">
            <thead><tr><th>Funcionário</th><th>Dias</th><th>Total Horas</th><th>Horas Extra</th></tr></thead>
            <tbody>
            <?php foreach ($summary as $s): ?>
            <tr>
                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                <td><?= $s['days'] ?></td>
                <td><?= floor($s['total_min']/60) ?>h<?= str_pad($s['total_min']%60,2,'0',STR_PAD_LEFT) ?>m</td>
                <td <?= $s['overtime_min']>0?'style="color:var(--warning,#f59e0b);font-weight:600"':'' ?>><?= floor($s['overtime_min']/60) ?>h<?= str_pad($s['overtime_min']%60,2,'0',STR_PAD_LEFT) ?>m</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Filtros histórico -->
<form method="get" style="display:flex;gap:10px;align-items:flex-end;margin-bottom:16px;flex-wrap:wrap">
    <div><label class="form-label">De</label><input type="date" name="date_from" class="form-input" value="<?= $date_from ?>"></div>
    <div><label class="form-label">Até</label><input type="date" name="date_to" class="form-input" value="<?= $date_to ?>"></div>
    <div>
        <label class="form-label">Funcionário</label>
        <select name="employee_id" class="form-select">
            <option value="">Todos</option>
            <?php foreach ($employees as $e): ?>
            <option value="<?= $e['id'] ?>" <?= $emp_filter==$e['id']?'selected':'' ?>><?= htmlspecialchars($e['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary">Filtrar</button>
    <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'1'])) ?>" class="btn btn-secondary"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg> CSV</a>
</form>

<!-- Tabela histórico -->
<div class="card">
    <div class="card-header"><h3 class="card-title">Histórico</h3><span style="font-size:12px;color:var(--text-muted)"><?= count($records) ?> registos</span></div>
    <div class="table-container">
        <table class="table">
            <thead><tr><th>Funcionário</th><th>Data</th><th>Entrada</th><th>Saída</th><th>Pausa</th><th>Total</th><th>Extra</th><th>Notas</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($records)): ?>
            <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:40px">Sem registos</td></tr>
            <?php else: foreach ($records as $r): $tm = $r['total_min']; ?>
            <tr>
                <td><strong><?= htmlspecialchars($r['emp_name']) ?></strong><br><span style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($r['role']) ?></span></td>
                <td><?= date('d/m/Y', strtotime($r['clock_in'])) ?></td>
                <td><?= date('H:i', strtotime($r['clock_in'])) ?></td>
                <td><?= $r['clock_out'] ? date('H:i', strtotime($r['clock_out'])) : '<span style="color:var(--success);font-weight:600">Em curso</span>' ?></td>
                <td><?= $r['break_minutes'] ?>min</td>
                <td><?= $tm !== null ? floor($tm/60).'h'.str_pad($tm%60,2,'0',STR_PAD_LEFT).'m' : '—' ?></td>
                <td <?= $r['overtime_minutes']>0?'style="color:var(--warning,#f59e0b);font-weight:600"':'' ?>><?= $r['overtime_minutes'] > 0 ? floor($r['overtime_minutes']/60).'h'.str_pad($r['overtime_minutes']%60,2,'0',STR_PAD_LEFT).'m' : '—' ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($r['notes'] ?? '') ?></td>
                <td>
                    <form method="post" onsubmit="return confirm('Eliminar registo?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="attendance_id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn btn-secondary" style="padding:4px 8px;font-size:11px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
