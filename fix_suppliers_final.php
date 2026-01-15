<?php
require_once 'config/database.php';
$pdo = db_connect();

// Limpar novamente
$pdo->exec('UPDATE products SET supplier_id = NULL');

// Atribuições corretas e lógicas
$correct = [
    // Luso - Bebidas, Laticínios, Pão
    1 => 1,   // Agua Mineral
    11 => 1,  // Leite
    13 => 1,  // Pão
    
    // Delta Cafes - Café
    4 => 2,   // Café
    
    // Riberalves - Vinho, Bebidas Alcoólicas
    17 => 3,  // Vinho
    
    // Gallo - Mercearia (Arroz, Azeite, Esparguete, Zimbro)
    2 => 4,   // Arroz
    3 => 4,   // Azeite
    7 => 4,   // Esparguete
    20 => 4,  // Zimbro
    
    // Super Bock Group - Bebidas (Cerveja)
    5 => 5,   // Cerveja
    
    // Lactogal - Laticínios
    8 => 6,   // Iogurte
    15 => 6,  // Queijo
    
    // Mar Atlantico - Peixe
    6 => 7,   // Bacalhau
    19 => 7,  // Salmão
    
    // Hortas de Portugal - Frutas e Legumes
    12 => 8,  // Maçã
    16 => 8,  // Tomate
    18 => 8,  // Uvas
    
    // Detergentes Skip - Limpeza
    14 => 9,  // Detergente
    
    // Nobre - Carnes
    9 => 10,  // Fiambre
];

$stmt = $pdo->prepare('UPDATE products SET supplier_id = ? WHERE id = ?');

foreach ($correct as $product_id => $supplier_id) {
    $stmt->execute([$supplier_id, $product_id]);
}

echo "✅ Fornecedores corrigidos!\n\n";

// Resumo
echo "📦 Distribuição Final de Produtos:\n\n";
$suppliers = $pdo->query('SELECT * FROM suppliers ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
foreach ($suppliers as $supplier) {
    $products = $pdo->query("SELECT name FROM products WHERE supplier_id = {$supplier['id']} ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($products)) {
        echo "  {$supplier['name']}:\n";
        foreach ($products as $p) {
            echo "    • {$p['name']}\n";
        }
        echo "\n";
    }
}
?>
