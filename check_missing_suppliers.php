<?php
require_once 'config/database.php';
$pdo = db_connect();

echo "Produtos sem fornecedor:\n";
$result = $pdo->query('SELECT id, name, category FROM products WHERE supplier_id IS NULL OR supplier_id = 0');
$missing = [];
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$row['id']}: {$row['name']} ({$row['category']})\n";
    $missing[] = $row;
}

if (empty($missing)) {
    echo "  ✅ Todos os produtos têm fornecedor!\n";
}
?>
