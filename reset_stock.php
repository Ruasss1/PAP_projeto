<?php
require_once __DIR__ . '/config/database.php';
$pdo = db_connect();

// Stock inicial para cada produto
$stockInitial = [
    'Agua Mineral 1.5L' => 240,
    'Arroz Agulha 1kg' => 180,
    'Atum Posta em Azeite 120g' => 150,
    'Azeite Virgem Extra 750ml' => 90,
    'Bacalhau Demolhado 1kg' => 40,
    'Cafe Moido 250g' => 80,
    'Cerveja Lager 33cl' => 200,
    'Detergente Roupa 3L' => 60,
    'Esparguete 500g' => 160,
    'Fiambre da Perna 200g' => 70,
    'Iogurte Natural 4x125g' => 110,
    'Leite Meio Gordo 1L' => 220,
    'Maca Gala kg' => 130,
    'Pao de Forma 500g' => 90,
    'Queijo Flamengo Barra kg' => 55,
    'Salmao Posta 1kg' => 35,
    'Tomate Cherry 250g' => 80,
    'Uvas Brancas kg' => 75,
    'Vinho Tinto 75cl' => 90,
    'Zimbro em Grao 30g' => 50
];

$stmt = $pdo->prepare('UPDATE products SET stock = ? WHERE name = ?');

foreach ($stockInitial as $name => $stock) {
    try {
        $result = $stmt->execute([$stock, $name]);
        if ($result && $stmt->rowCount() > 0) {
            echo "✓ $name: Stock atualizado para $stock\n";
        } else {
            echo "⚠ $name: Produto não encontrado\n";
        }
    } catch (PDOException $e) {
        echo "✗ $name: Erro - " . $e->getMessage() . "\n";
    }
}

// Verificar total de stock
$total = $pdo->query('SELECT SUM(stock) as total FROM products')->fetch(PDO::FETCH_ASSOC);
echo "\n✅ Stock total atualizado: " . number_format($total['total'], 0) . " unidades\n";

// Listar produtos com stock
echo "\n📊 Stock por Produto:\n";
$products = $pdo->query('SELECT name, stock, category FROM products ORDER BY category, name');
$currentCategory = '';
while ($row = $products->fetch(PDO::FETCH_ASSOC)) {
    if ($row['category'] !== $currentCategory) {
        echo "\n  [{$row['category']}]\n";
        $currentCategory = $row['category'];
    }
    echo "    • {$row['name']}: {$row['stock']} un.\n";
}
?>
