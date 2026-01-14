<?php
require_once 'config/database.php';
$pdo = db_connect();

echo "📅 Distribuição de VENDAS por mês:\n";
$sales = $pdo->query('SELECT DATE_FORMAT(sale_date, "%b %Y") as mes, COUNT(*) as count FROM sales GROUP BY DATE_FORMAT(sale_date, "%Y-%m") ORDER BY sale_date DESC');
while ($row = $sales->fetch(PDO::FETCH_ASSOC)) {
    echo "  " . $row['mes'] . ": " . $row['count'] . " vendas\n";
}

echo "\n📋 Distribuição de RECIBOS por mês:\n";
$receipts = $pdo->query('SELECT DATE_FORMAT(created_at, "%b %Y") as mes, COUNT(*) as count FROM receipts GROUP BY DATE_FORMAT(created_at, "%Y-%m") ORDER BY created_at DESC');
while ($row = $receipts->fetch(PDO::FETCH_ASSOC)) {
    echo "  " . $row['mes'] . ": " . $row['count'] . " recibos\n";
}

echo "\nℹ️ Datas das vendas:\n";
echo "  Primeira: " . $pdo->query('SELECT MIN(sale_date) FROM sales')->fetchColumn() . "\n";
echo "  Última: " . $pdo->query('SELECT MAX(sale_date) FROM sales')->fetchColumn() . "\n";
?>
