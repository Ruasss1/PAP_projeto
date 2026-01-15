<?php
require_once 'config/database.php';
$pdo = db_connect();

echo "Produtos e seus IDs:\n\n";
$products = $pdo->query('SELECT id, name FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
foreach ($products as $p) {
    echo "{$p['id']}: {$p['name']}\n";
}
?>
