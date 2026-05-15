<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../modules/rh.php';

echo "=== STORES ===" . PHP_EOL;
foreach ($pdo->query('SELECT * FROM stores')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  ID={$r['id']} | {$r['name']} | {$r['city']}" . PHP_EOL;
}

echo PHP_EOL . "=== EMPLOYEES PER STORE ===" . PHP_EOL;
foreach ($pdo->query('SELECT store_id, COUNT(*) as total FROM employees GROUP BY store_id')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  store {$r['store_id']}: {$r['total']} employees" . PHP_EOL;
}

echo PHP_EOL . "=== DESCRIBE shifts ===" . PHP_EOL;
foreach ($pdo->query('DESCRIBE shifts')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  {$r['Field']} {$r['Type']}" . PHP_EOL;
}

echo PHP_EOL . "=== SHIFTS ===" . PHP_EOL;
foreach ($pdo->query('SELECT * FROM shifts')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  ID={$r['id']} | {$r['name']} | {$r['start_time']}-{$r['end_time']}" . PHP_EOL;
}

echo PHP_EOL . "=== EMPLOYEES STORE 2 ===" . PHP_EOL;
foreach ($pdo->query('SELECT id, name, base_salary, salary, department, position FROM employees WHERE store_id=2')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  {$r['id']} | {$r['name']} | base={$r['base_salary']} | salary={$r['salary']} | dep={$r['department']} | pos={$r['position']}" . PHP_EOL;
}

echo PHP_EOL . "=== PAYROLL PER STORE ===" . PHP_EOL;
foreach ($pdo->query('SELECT e.store_id, COUNT(p.id) as total FROM payroll p JOIN employees e ON e.id=p.employee_id GROUP BY e.store_id')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  store {$r['store_id']}: {$r['total']} payroll records" . PHP_EOL;
}

echo PHP_EOL . "=== SHOW TABLES ===" . PHP_EOL;
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM) as $r) {
    echo "  {$r[0]}" . PHP_EOL;
}
