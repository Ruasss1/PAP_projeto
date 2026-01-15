<?php
require_once 'config/database.php';
$pdo = db_connect();

// Ver o schema da tabela
$columns = $pdo->query("SHOW COLUMNS FROM products")->fetchAll();
foreach ($columns as $col) {
    if ($col['Field'] === 'category') {
        echo "Coluna category: {$col['Type']}\n";
    }
}
?>
