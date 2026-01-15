<?php
require_once 'config/database.php';
$pdo = db_connect();

// Limpar tudo
$pdo->exec('UPDATE products SET supplier_id = NULL');

// ATRIBUIÇÃO CORRETA com IDs reais:
$correct = [
    1 => 1,   // Agua Mineral -> Luso
    2 => 4,   // Arroz -> Gallo
    3 => 4,   // Azeite -> Gallo
    4 => 2,   // Cafe -> Delta Cafes (SÓ CAFÉ!)
    5 => 5,   // Cerveja -> Super Bock
    6 => 9,   // Detergente -> Detergentes Skip
    7 => 4,   // Esparguete -> Gallo
    8 => 6,   // Iogurte -> Lactogal
    9 => 1,   // Leite -> Luso
    10 => 8,  // Maca -> Hortas de Portugal
    11 => 1,  // Pao -> Luso
    12 => 6,  // Queijo -> Lactogal
    13 => 8,  // Uvas -> Hortas de Portugal
    14 => 3,  // Vinho -> Riberalves
    15 => 4,  // Zimbro -> Gallo
];

$stmt = $pdo->prepare('UPDATE products SET supplier_id = ? WHERE id = ?');

foreach ($correct as $product_id => $supplier_id) {
    $stmt->execute([$supplier_id, $product_id]);
}

echo "✅ DISTRIBUIÇÃO FINAL CORRIGIDA:\n\n";

$suppliers = $pdo->query('SELECT id, name FROM suppliers ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
foreach ($suppliers as $supplier) {
    $products = $pdo->query("SELECT name FROM products WHERE supplier_id = {$supplier['id']} ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($products)) {
        echo "{$supplier['name']} ({$supplier['id']}):\n";
        foreach ($products as $p) {
            echo "  • {$p['name']}\n";
        }
        echo "\n";
    }
}
?>
