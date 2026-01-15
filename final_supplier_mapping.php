<?php
require_once 'config/database.php';
$pdo = db_connect();

// Limpar tudo
$pdo->exec('UPDATE products SET supplier_id = NULL');

// IDs dos produtos (baseado no seed_real_supermarket.php):
// 1: Agua Mineral, 2: Arroz, 3: Azeite, 4: Cafe, 5: Cerveja
// 6: Detergente, 7: Esparguete, 8: Iogurte, 9: Leite, 10: Maca
// 11: Pao, 12: Queijo, 13: Salmao, 14: Tomate, 15: Uvas, 16: Vinho, 17: Zimbro
// (Fiambre, Bacalhau, Tomate Cherry faltam - sem IDs confirmados)

// Fornecedores (IDs):
// 1: Luso, 2: Delta Cafes, 3: Riberalves, 4: Gallo, 5: Super Bock
// 6: Lactogal, 7: Mar Atlantico, 8: Hortas, 9: Detergentes Skip, 10: Nobre

// Atribuição CLARA:
$assignments = [
    1 => 1,   // Agua Mineral -> Luso
    2 => 4,   // Arroz -> Gallo
    3 => 4,   // Azeite -> Gallo
    4 => 2,   // Cafe -> Delta Cafes
    5 => 5,   // Cerveja -> Super Bock
    6 => 9,   // Detergente -> Detergentes Skip
    7 => 4,   // Esparguete -> Gallo
    8 => 6,   // Iogurte -> Lactogal
    9 => 1,   // Leite -> Luso
    10 => 8,  // Maca -> Hortas
    11 => 1,  // Pao -> Luso
    12 => 6,  // Queijo -> Lactogal
    13 => 7,  // Salmao -> Mar Atlantico
    14 => 8,  // Tomate -> Hortas
    15 => 8,  // Uvas -> Hortas
    16 => 3,  // Vinho -> Riberalves
    17 => 4,  // Zimbro -> Gallo
];

$stmt = $pdo->prepare('UPDATE products SET supplier_id = ? WHERE id = ?');

echo "Atualizando:\n";
foreach ($assignments as $product_id => $supplier_id) {
    try {
        $stmt->execute([$supplier_id, $product_id]);
        $p = $pdo->query("SELECT name FROM products WHERE id = $product_id")->fetch();
        $s = $pdo->query("SELECT name FROM suppliers WHERE id = $supplier_id")->fetch();
        echo "  ✓ Produto ID $product_id -> Fornecedor ID $supplier_id\n";
    } catch (Exception $e) {
        echo "  ✗ Erro na atribuição\n";
    }
}

echo "\n✅ Fornecedores corrigidos!\n\n";

// Resumo por fornecedor
echo "📦 DISTRIBUIÇÃO FINAL:\n\n";
$suppliers = $pdo->query('SELECT id, name FROM suppliers ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
foreach ($suppliers as $supplier) {
    $products = $pdo->query("SELECT name FROM products WHERE supplier_id = {$supplier['id']} ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($products)) {
        echo "{$supplier['name']}:\n";
        foreach ($products as $p) {
            echo "  • {$p['name']}\n";
        }
        echo "\n";
    }
}
?>
