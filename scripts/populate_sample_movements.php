<?php
/**
 * Script para popular dados de exemplo para attendance e stock_movements
 */
require_once __DIR__ . '/../config/database.php';

// Attendance - últimos 7 dias
$employees = $pdo->query('SELECT id, store_id FROM employees WHERE (status="Ativo" OR status="active") AND store_id=1 LIMIT 6')->fetchAll();

$ins = $pdo->prepare('INSERT IGNORE INTO attendance (employee_id,store_id,clock_in,clock_out,break_minutes,overtime_minutes) VALUES (?,?,?,?,?,?)');
foreach ($employees as $idx => $e) {
    for ($d = 6; $d >= 1; $d--) {
        // Skip some days randomly
        if ($d % 2 == 0 && $idx % 3 == 0) continue;
        $start_h = 8 + ($idx % 2);
        $end_h   = 17 + (($idx + $d) % 3);
        $ci = date('Y-m-d H:i:s', strtotime("-{$d} day") + $start_h * 3600);
        $co = date('Y-m-d H:i:s', strtotime("-{$d} day") + $end_h   * 3600);
        $ot = max(0, ($end_h - 17) * 60);
        $ins->execute([$e['id'], 1, $ci, $co, 30, $ot]);
    }
}
echo count($employees) . " funcionários, registos inseridos\n";

// Stock movements
$products = $pdo->query('SELECT id, stock FROM products WHERE store_id=1 AND active=1 LIMIT 15')->fetchAll();
$insM = $pdo->prepare('INSERT INTO stock_movements (product_id,type,qty,previous_stock,new_stock,reference_type,notes,created_at) VALUES (?,?,?,?,?,?,?,?)');
$types = [['in','purchase','Encomenda fornecedor'],['in','purchase','Reposição urgente'],['out','sale','Vendas do dia'],['out','sale','Venda balcão'],['in','return','Devolução cliente']];
foreach ($products as $p) {
    for ($d = 6; $d >= 0; $d--) {
        $t = $types[($p['id'] + $d) % count($types)];
        $qty  = rand(2, 30);
        $prev = max(0, $p['stock'] + rand(-20, 20));
        $new  = $t[0] === 'in' ? $prev + $qty : max(0, $prev - $qty);
        $date = date('Y-m-d H:i:s', strtotime("-{$d} day") + rand(8,18) * 3600);
        $insM->execute([$p['id'], $t[0], $qty, $prev, $new, $t[1], $t[2], $date]);
    }
}
echo count($products) . " produtos, movimentos de stock inseridos\n";
echo "Done!\n";
