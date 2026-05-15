<?php
/**
 * Populate Store 2 (Supermercado Norte - Porto) with RH data:
 * - Shifts for March 2026 (rotating schedule)
 * - Vacation requests
 * - Absences
 * - Schedules linking employees to shifts
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../modules/rh.php';

$store2_employees = [
    ['id' => 13, 'name' => 'Antonio Oliveira',  'dep' => 'Gestao',         'start' => '08:00', 'end' => '16:00', 'break' => 60],
    ['id' => 14, 'name' => 'Catarina Ribeiro',  'dep' => 'Vendas',         'start' => '08:00', 'end' => '16:00', 'break' => 60],
    ['id' => 15, 'name' => 'Rui Fernandes',     'dep' => 'Loja',           'start' => '07:00', 'end' => '15:00', 'break' => 30],
    ['id' => 16, 'name' => 'Beatriz Lopes',     'dep' => 'Loja',           'start' => '14:00', 'end' => '22:00', 'break' => 30],
    ['id' => 17, 'name' => 'Miguel Carvalho',   'dep' => 'Caixa',          'start' => '07:00', 'end' => '15:00', 'break' => 30],
    ['id' => 18, 'name' => 'Helena Sousa',      'dep' => 'Caixa',          'start' => '14:00', 'end' => '22:00', 'break' => 30],
    ['id' => 19, 'name' => 'Fernando Teixeira', 'dep' => 'Armazem',        'start' => '06:00', 'end' => '14:00', 'break' => 30],
    ['id' => 20, 'name' => 'Mariana Gomes',     'dep' => 'Administrativo', 'start' => '09:00', 'end' => '17:00', 'break' => 60],
];

// ── 1. SHIFTS ─────────────────────────────────────────────────────────────────
// Check what's already there
$existing = $pdo->query(
    'SELECT employee_id, COUNT(*) as cnt FROM shifts WHERE employee_id IN (13,14,15,16,17,18,19,20) GROUP BY employee_id'
)->fetchAll(PDO::FETCH_KEY_PAIR);

$shift_stmt = $pdo->prepare(
    'INSERT INTO shifts (name, employee_id, start_time, end_time, break_duration, department, shift_date, status)
     VALUES (:name, :emp, :start, :end, :break, :dep, :date, :status)'
);

$total_shifts = 0;
foreach ($store2_employees as $emp) {
    if (($existing[$emp['id']] ?? 0) > 0) {
        echo "  Shifts for {$emp['name']}: already exists ({$existing[$emp['id']]} records), skipping\n";
        continue;
    }

    // Generate shifts for all of March 2026 (skip Sundays, 1 day off per employee in rotation)
    $day_off_offset = array_search($emp, $store2_employees); // each employee has different day off
    $created = 0;
    for ($day = 1; $day <= 31; $day++) {
        $date = sprintf('2026-03-%02d', $day);
        $dow  = date('N', strtotime($date)); // 1=Mon, 7=Sun
        if ($dow == 7) continue; // skip Sundays
        // Each employee gets 1 day off per week (Mon=1 to Sat=6 → offset by employee index)
        if ($dow == (($day_off_offset % 6) + 1)) continue;

        // Alternate early/late for Loja and Caixa employees on even/odd days
        $start = $emp['start'];
        $end   = $emp['end'];
        if (in_array($emp['dep'], ['Loja', 'Caixa']) && $day % 2 == 0) {
            // swap to other shift
            $start = ($emp['start'] == '07:00') ? '14:00' : '07:00';
            $end   = ($emp['end']   == '15:00') ? '22:00' : '15:00';
        }

        $shift_name = ($start < '12:00' ? 'Manha' : 'Tarde') . ' - ' . $emp['dep'];
        $shift_stmt->execute([
            ':name'   => $shift_name,
            ':emp'    => $emp['id'],
            ':start'  => $start,
            ':end'    => $end,
            ':break'  => $emp['break'],
            ':dep'    => $emp['dep'],
            ':date'   => $date,
            ':status' => 'Confirmado',
        ]);
        $created++;
    }
    $total_shifts += $created;
    echo "  Shifts for {$emp['name']}: {$created} created\n";
}
echo "SHIFTS TOTAL: {$total_shifts}\n\n";

// ── 2. VACATION REQUESTS ──────────────────────────────────────────────────────
$existing_vac = $pdo->query(
    'SELECT employee_id FROM vacation_requests WHERE employee_id IN (13,14,15,16,17,18,19,20)'
)->fetchAll(PDO::FETCH_COLUMN);

$vac_data = [
    ['emp' => 15, 'start' => '2026-04-06', 'end' => '2026-04-18', 'type' => 'Ferias',   'status' => 'Aprovado'],
    ['emp' => 16, 'start' => '2026-05-04', 'end' => '2026-05-15', 'type' => 'Ferias',   'status' => 'Aprovado'],
    ['emp' => 17, 'start' => '2026-04-20', 'end' => '2026-04-24', 'type' => 'Ferias',   'status' => 'Pendente'],
    ['emp' => 19, 'start' => '2026-06-01', 'end' => '2026-06-30', 'type' => 'Ferias',   'status' => 'Aprovado'],
    ['emp' => 20, 'start' => '2026-03-25', 'end' => '2026-03-26', 'type' => 'Licenca',  'status' => 'Aprovado'],
    ['emp' => 13, 'start' => '2026-07-14', 'end' => '2026-07-31', 'type' => 'Ferias',   'status' => 'Pendente'],
];

$vac_stmt = $pdo->prepare(
    'INSERT INTO vacation_requests (employee_id, start_date, end_date, type, status)
     VALUES (:emp, :start, :end, :type, :status)'
);

$total_vac = 0;
foreach ($vac_data as $v) {
    if (in_array($v['emp'], $existing_vac)) {
        echo "  Vacation for emp {$v['emp']}: already exists, skipping\n";
        continue;
    }
    $vac_stmt->execute([':emp' => $v['emp'], ':start' => $v['start'], ':end' => $v['end'], ':type' => $v['type'], ':status' => $v['status']]);
    $total_vac++;
}
echo "VACATION REQUESTS: {$total_vac} created\n\n";

// ── 3. ABSENCES ───────────────────────────────────────────────────────────────
$existing_abs = $pdo->query(
    'SELECT DISTINCT employee_id FROM absences WHERE employee_id IN (13,14,15,16,17,18,19,20)'
)->fetchAll(PDO::FETCH_COLUMN);

$abs_data = [
    ['emp' => 15, 'date' => '2026-03-04', 'type' => 'Doenca',     'reason' => 'Gripe',              'justified' => 1],
    ['emp' => 17, 'date' => '2026-03-10', 'type' => 'Falta',      'reason' => 'Motivo pessoal',     'justified' => 0],
    ['emp' => 18, 'date' => '2026-03-12', 'type' => 'Doenca',     'reason' => 'Consulta medica',    'justified' => 1],
    ['emp' => 16, 'date' => '2026-03-06', 'type' => 'Atraso',     'reason' => 'Transporte publico', 'justified' => 1],
    ['emp' => 20, 'date' => '2026-02-20', 'type' => 'Falta',      'reason' => 'Emergencia familiar','justified' => 1],
];

$abs_stmt = $pdo->prepare(
    'INSERT INTO absences (employee_id, absence_date, type, reason, justified)
     VALUES (:emp, :date, :type, :reason, :justified)'
);

$total_abs = 0;
foreach ($abs_data as $a) {
    if (in_array($a['emp'], $existing_abs)) {
        echo "  Absence for emp {$a['emp']}: already exists, skipping\n";
        continue;
    }
    $abs_stmt->execute([':emp' => $a['emp'], ':date' => $a['date'], ':type' => $a['type'], ':reason' => $a['reason'], ':justified' => $a['justified']]);
    $total_abs++;
}
echo "ABSENCES: {$total_abs} created\n\n";

// ── 4. PAYROLL CHECK ──────────────────────────────────────────────────────────
$payroll_march = $pdo->query(
    "SELECT COUNT(*) FROM payroll p JOIN employees e ON e.id=p.employee_id WHERE e.store_id=2 AND p.month='2026-03'"
)->fetchColumn();

if ($payroll_march < 8) {
    echo "Payroll store 2 March 2026: only {$payroll_march}/8, regenerating...\n";
    // Delete and regenerate for store 2 employees only
    $pdo->query("DELETE FROM payroll WHERE employee_id IN (13,14,15,16,17,18,19,20) AND month='2026-03'");
    foreach ($store2_employees as $emp) {
        $base = $pdo->query("SELECT base_salary FROM employees WHERE id={$emp['id']}")->fetchColumn();
        $gross = $base;
        $ss    = round($gross * 0.11, 2);
        $irs   = round($gross * 0.15, 2);
        $net   = round($gross - $ss - $irs, 2);
        $pdo->prepare("INSERT INTO payroll (employee_id, month, base_salary, deductions, net_salary, status) VALUES (?,?,?,?,?,'Pendente')")
            ->execute([$emp['id'], '2026-03', $base, $ss + $irs, $net]);
    }
    echo "  Payroll regenerated for store 2\n";
} else {
    echo "Payroll store 2 March 2026: {$payroll_march}/8 records OK, skipping\n";
}

echo "\n=== DONE ===\n";
echo "Final counts for store 2 employees:\n";
echo "  Shifts: " . $pdo->query('SELECT COUNT(*) FROM shifts WHERE employee_id IN (13,14,15,16,17,18,19,20)')->fetchColumn() . "\n";
echo "  Vacations: " . $pdo->query('SELECT COUNT(*) FROM vacation_requests WHERE employee_id IN (13,14,15,16,17,18,19,20)')->fetchColumn() . "\n";
echo "  Absences: " . $pdo->query('SELECT COUNT(*) FROM absences WHERE employee_id IN (13,14,15,16,17,18,19,20)')->fetchColumn() . "\n";
echo "  Payroll: " . $pdo->query("SELECT COUNT(*) FROM payroll WHERE employee_id IN (13,14,15,16,17,18,19,20) AND month='2026-03'")->fetchColumn() . "\n";
