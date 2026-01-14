<?php
require_once 'config/database.php';
$pdo = db_connect();

$sales = $pdo->query('SELECT COUNT(*) as total FROM sales')->fetchColumn();
$items = $pdo->query('SELECT COUNT(*) as total FROM sale_items')->fetchColumn();
$receipts = $pdo->query('SELECT COUNT(*) as total FROM receipts')->fetchColumn();

echo "✓ Vendas geradas: $sales\n";
echo "✓ Itens de venda: $items\n";
echo "✓ Recibos gerados: $receipts\n";

// Calcular valor total vendido
$totalVendido = $pdo->query('SELECT SUM(total) FROM receipts')->fetchColumn();
echo "✓ Total vendido: €" . number_format($totalVendido, 2) . "\n";

// Distribuição por mês
echo "\n📊 Distribuição por mês:\n";
$byMonth = $pdo->query('SELECT DATE_FORMAT(created_at, "%b %Y") as mes, COUNT(*) as count, SUM(total) as total FROM receipts GROUP BY DATE_FORMAT(created_at, "%Y-%m") ORDER BY created_at DESC');
while ($row = $byMonth->fetch(PDO::FETCH_ASSOC)) {
    echo "  " . $row['mes'] . ": " . $row['count'] . " recibos - €" . number_format($row['total'], 2) . "\n";
}
?>
