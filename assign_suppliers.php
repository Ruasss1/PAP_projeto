<?php
require_once 'config/database.php';
$pdo = db_connect();

// Atribuição de fornecedores a produtos baseado em categoria/nome
$assignments = [
    // Bebidas
    1 => 1, // Agua Mineral -> Luso
    5 => 5, // Cerveja -> Super Bock Group
    17 => 3, // Vinho -> Riberalves
    
    // Frutas/Legumes
    12 => 8, // Maçã -> Hortas de Portugal
    18 => 8, // Uvas -> Hortas de Portugal
    16 => 8, // Tomate -> Hortas de Portugal
    
    // Mercearia
    2 => 4, // Arroz -> Gallo
    3 => 4, // Azeite -> Gallo
    4 => 2, // Café -> Delta Cafes
    7 => 4, // Esparguete -> Gallo
    20 => 4, // Zimbro -> Gallo
    
    // Laticinios
    8 => 6, // Iogurte -> Lactogal
    11 => 6, // Leite -> Lactogal
    15 => 6, // Queijo -> Lactogal
    
    // Carnes/Peixe
    9 => 10, // Fiambre -> Nobre
    6 => 7, // Bacalhau -> Mar Atlântico
    19 => 7, // Salmão -> Mar Atlântico
    
    // Limpeza
    14 => 9, // Detergente -> Detergentes Skip
    
    // Padaria
    13 => 1, // Pão -> Luso
];

echo "Atribuindo fornecedores aos produtos...\n\n";

$stmt = $pdo->prepare('UPDATE products SET supplier_id = ? WHERE id = ?');

foreach ($assignments as $product_id => $supplier_id) {
    try {
        $result = $stmt->execute([$supplier_id, $product_id]);
        
        // Obter nome do produto e fornecedor
        $product = $pdo->query("SELECT name FROM products WHERE id = $product_id")->fetch(PDO::FETCH_ASSOC);
        $supplier = $pdo->query("SELECT name FROM suppliers WHERE id = $supplier_id")->fetch(PDO::FETCH_ASSOC);
        
        echo "✓ {$product['name']} -> {$supplier['name']}\n";
    } catch (Exception $e) {
        echo "✗ Erro ao atribuir produto ID $product_id: " . $e->getMessage() . "\n";
    }
}

echo "\n✅ Fornecedores atribuídos com sucesso!\n\n";

// Verificar final
echo "Status final:\n";
$result = $pdo->query('SELECT p.id, p.name, s.name as supplier FROM products p LEFT JOIN suppliers s ON p.supplier_id = s.id ORDER BY p.id');
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $supplier = $row['supplier'] ?? 'Nenhum';
    echo "  {$row['id']}: {$row['name']} -> $supplier\n";
}
?>
