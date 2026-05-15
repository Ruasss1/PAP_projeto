<?php
require_once __DIR__ . '/includes/functions.php'; // garante que db_connect() existe

$pdo = db_connect();

$startDate = date('Y-m-01', strtotime('-5 months')); // 1º dia do mês, 6 meses atrás
$endDate = date('Y-m-d');

$d = $startDate;
while ($d <= $endDate) {
    $stmt = $pdo->prepare("
        INSERT INTO daily_profit (day, revenue, cogs, profit)
        SELECT 
            :day,
            IFNULL(SUM(s.total),0) AS revenue,
            IFNULL(SUM(si.quantity * p.cost_price),0) AS cogs,
            IFNULL(SUM(s.total),0) - IFNULL(SUM(si.quantity * p.cost_price),0) AS profit
        FROM sales s
        LEFT JOIN sale_items si ON si.sale_id = s.id
        LEFT JOIN products p ON p.id = si.product_id
        WHERE DATE(s.sale_date) = :day
        ON DUPLICATE KEY UPDATE
            revenue = VALUES(revenue),
            cogs = VALUES(cogs),
            profit = VALUES(profit)
    ");
    $stmt->execute(['day' => $d]);
    $d = date('Y-m-d', strtotime($d . ' +1 day'));
}

echo "Tabela daily_profit atualizada com sucesso!";
