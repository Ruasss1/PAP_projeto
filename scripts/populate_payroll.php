<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../modules/rh.php';

// 1. Assign departments/positions and sync base_salary from legacy salary
$assignments = [
    5  => ['Gestao',        'Gerente'],
    6  => ['Vendas',        'Chefe de Seccao'],
    7  => ['Loja',          'Assistente de Loja'],
    8  => ['Loja',          'Assistente de Loja'],
    9  => ['Caixa',         'Operador de Caixa'],
    10 => ['Caixa',         'Operador de Caixa'],
    11 => ['Armazem',       'Responsavel de Armazem'],
    12 => ['Administrativo','Assistente Administrativo'],
    13 => ['Gestao',        'Sub-Gerente'],
    14 => ['Vendas',        'Chefe de Seccao'],
    15 => ['Loja',          'Assistente de Loja'],
    16 => ['Loja',          'Assistente de Loja'],
    17 => ['Caixa',         'Operador de Caixa'],
    18 => ['Caixa',         'Operador de Caixa'],
    19 => ['Armazem',       'Responsavel de Armazem'],
    20 => ['Administrativo','Assistente Administrativo'],
    22 => ['Caixa',         'Caixa'],
];

$stmt = $pdo->prepare('UPDATE employees SET base_salary = salary, department = :dep, position = :pos WHERE id = :id');
foreach ($assignments as $id => [$dep, $pos]) {
    $stmt->execute([':dep' => $dep, ':pos' => $pos, ':id' => $id]);
}
echo "Colaboradores atualizados: " . count($assignments) . PHP_EOL;

// 2. Regenerate payroll for March 2026 with real salaries
$pdo->exec("DELETE FROM payroll WHERE month='2026-03'");
$result = generate_payroll(3, 2026);
echo "Folhas geradas: " . count($result) . PHP_EOL;

// 3. Preview top 5
$stmt = $pdo->query('SELECT e.name, p.base_salary, p.deductions, p.net_salary FROM payroll p JOIN employees e ON e.id=p.employee_id ORDER BY p.net_salary DESC LIMIT 5');
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  {$r['name']} | base={$r['base_salary']} | descontos={$r['deductions']} | liquido={$r['net_salary']}" . PHP_EOL;
}
