<?php
require_once 'config/database.php';
$pdo = db_connect();

// Verificar coluna supplier_id na tabela products
$columns = $pdo->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_ASSOC);
echo "Colunas da tabela products:\n";
foreach ($columns as $col) {
    echo "  • " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

// Verificar dados
echo "\nProdutos com supplier_id:\n";
$result = $pdo->query('SELECT id, name, supplier_id FROM products LIMIT 5');
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "  ID: {$row['id']}, Nome: {$row['name']}, Supplier: {$row['supplier_id']}\n";
}
?>
