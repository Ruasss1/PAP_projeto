<?php
require_once 'config/database.php';
$pdo = db_connect();

// Limpar dados antigos (desabilitar constraints)
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('DELETE FROM products');
$pdo->exec('DELETE FROM suppliers');
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
$pdo->exec('ALTER TABLE products AUTO_INCREMENT = 1');
$pdo->exec('ALTER TABLE suppliers AUTO_INCREMENT = 1');

// NOVOS FORNECEDORES (20 fornecedores)
$suppliers = [
    ['name' => 'Luso', 'contact' => 'contacto@luso.pt'],
    ['name' => 'Delta Cafes', 'contact' => 'vendas@delta.pt'],
    ['name' => 'Riberalves', 'contact' => 'comercial@riberalves.pt'],
    ['name' => 'Gallo', 'contact' => 'comercial@gallo.pt'],
    ['name' => 'Super Bock Group', 'contact' => 'sales@superbock.pt'],
    ['name' => 'Lactogal', 'contact' => 'clientes@lactogal.pt'],
    ['name' => 'Mar Atlantico', 'contact' => 'mar@fornecedores.pt'],
    ['name' => 'Hortas de Portugal', 'contact' => 'horta@fornecedores.pt'],
    ['name' => 'Detergentes Skip', 'contact' => 'skip@fornecedores.pt'],
    ['name' => 'Nobre', 'contact' => 'nobre@fornecedores.pt'],
    ['name' => 'Compal', 'contact' => 'vendas@compal.pt'],
    ['name' => 'Nestlé Portugal', 'contact' => 'comercial@nestle.pt'],
    ['name' => 'Danone', 'contact' => 'vendas@danone.pt'],
    ['name' => 'Mondelez', 'contact' => 'comercial@mondelez.pt'],
    ['name' => 'Bimbo Portugal', 'contact' => 'vendas@bimbo.pt'],
    ['name' => 'Cimpor', 'contact' => 'comercial@cimpor.pt'],
    ['name' => 'Frigo', 'contact' => 'vendas@frigo.pt'],
    ['name' => 'Zeta', 'contact' => 'comercial@zeta.pt'],
    ['name' => 'Nespresso', 'contact' => 'vendas@nespresso.pt'],
    ['name' => 'Verde Minho', 'contact' => 'verde@fornecedores.pt'],
];

$supplierStmt = $pdo->prepare('INSERT INTO suppliers (name, contact) VALUES (?, ?)');
$supplierIds = [];
foreach ($suppliers as $idx => $s) {
    $supplierStmt->execute([$s['name'], $s['contact']]);
    $supplierIds[$idx + 1] = $idx + 1;
}

echo "✓ " . count($suppliers) . " fornecedores criados\n\n";

// NOVOS PRODUTOS (60+ produtos)
$products = [
    // BEBIDAS (15 produtos)
    ['name' => 'Agua Mineral 1.5L', 'category' => 'Bebidas', 'cost' => 0.09, 'sell' => 0.25, 'supplier' => 1],
    ['name' => 'Agua Mineral 5L', 'category' => 'Bebidas', 'cost' => 0.25, 'sell' => 0.55, 'supplier' => 1],
    ['name' => 'Agua com Gas 1.5L', 'category' => 'Bebidas', 'cost' => 0.12, 'sell' => 0.35, 'supplier' => 1],
    ['name' => 'Sumo Compal Laranja 1L', 'category' => 'Bebidas', 'cost' => 0.65, 'sell' => 1.19, 'supplier' => 11],
    ['name' => 'Sumo Compal Multiplos 1L', 'category' => 'Bebidas', 'cost' => 0.60, 'sell' => 1.09, 'supplier' => 11],
    ['name' => 'Refrigerante Cola 2L', 'category' => 'Bebidas', 'cost' => 1.20, 'sell' => 1.99, 'supplier' => 4],
    ['name' => 'Refrigerante Laranja 2L', 'category' => 'Bebidas', 'cost' => 1.00, 'sell' => 1.79, 'supplier' => 4],
    ['name' => 'Cerveja Lager 33cl', 'category' => 'Bebidas', 'cost' => 0.45, 'sell' => 0.89, 'supplier' => 5],
    ['name' => 'Cerveja Preta 33cl', 'category' => 'Bebidas', 'cost' => 0.50, 'sell' => 0.99, 'supplier' => 5],
    ['name' => 'Vinho Tinto 75cl', 'category' => 'Bebidas', 'cost' => 2.20, 'sell' => 4.49, 'supplier' => 3],
    ['name' => 'Vinho Branco 75cl', 'category' => 'Bebidas', 'cost' => 2.00, 'sell' => 4.19, 'supplier' => 3],
    ['name' => 'Champagne Bruto 75cl', 'category' => 'Bebidas', 'cost' => 5.50, 'sell' => 9.99, 'supplier' => 3],
    ['name' => 'Café Moido 250g', 'category' => 'Bebidas', 'cost' => 1.90, 'sell' => 2.99, 'supplier' => 2],
    ['name' => 'Café Moido 500g', 'category' => 'Bebidas', 'cost' => 3.50, 'sell' => 5.49, 'supplier' => 2],
    ['name' => 'Cha Preto 25 Sacos', 'category' => 'Bebidas', 'cost' => 0.85, 'sell' => 1.49, 'supplier' => 12],
    
    // LATICINIOS (12 produtos)
    ['name' => 'Leite Integral 1L', 'category' => 'Laticinios', 'cost' => 0.65, 'sell' => 0.99, 'supplier' => 6],
    ['name' => 'Leite Meio Gordo 1L', 'category' => 'Laticinios', 'cost' => 0.55, 'sell' => 0.79, 'supplier' => 6],
    ['name' => 'Leite Magro 1L', 'category' => 'Laticinios', 'cost' => 0.50, 'sell' => 0.69, 'supplier' => 6],
    ['name' => 'Iogurte Natural 4x125g', 'category' => 'Laticinios', 'cost' => 0.85, 'sell' => 1.39, 'supplier' => 13],
    ['name' => 'Iogurte Fruta 4x125g', 'category' => 'Laticinios', 'cost' => 0.90, 'sell' => 1.49, 'supplier' => 13],
    ['name' => 'Queijo Flamengo 250g', 'category' => 'Laticinios', 'cost' => 1.80, 'sell' => 2.99, 'supplier' => 6],
    ['name' => 'Queijo Meia Cura 200g', 'category' => 'Laticinios', 'cost' => 2.50, 'sell' => 4.29, 'supplier' => 6],
    ['name' => 'Manteiga 250g', 'category' => 'Laticinios', 'cost' => 1.40, 'sell' => 2.49, 'supplier' => 6],
    ['name' => 'Requeijao 100g', 'category' => 'Laticinios', 'cost' => 0.35, 'sell' => 0.59, 'supplier' => 6],
    ['name' => 'Nata Culinaria 200g', 'category' => 'Laticinios', 'cost' => 0.95, 'sell' => 1.69, 'supplier' => 6],
    ['name' => 'Pudim Leite 4x100g', 'category' => 'Laticinios', 'cost' => 0.75, 'sell' => 1.29, 'supplier' => 12],
    ['name' => 'Mousse Chocolate 2x70g', 'category' => 'Laticinios', 'cost' => 0.65, 'sell' => 1.19, 'supplier' => 12],
    
    // MERCEARIA (15 produtos)
    ['name' => 'Arroz Agulha 1kg', 'category' => 'Mercearia', 'cost' => 0.75, 'sell' => 1.09, 'supplier' => 4],
    ['name' => 'Arroz Integral 1kg', 'category' => 'Mercearia', 'cost' => 1.20, 'sell' => 1.89, 'supplier' => 4],
    ['name' => 'Azeite Virgem Extra 750ml', 'category' => 'Mercearia', 'cost' => 4.20, 'sell' => 6.49, 'supplier' => 4],
    ['name' => 'Oleo Girassol 1L', 'category' => 'Mercearia', 'cost' => 1.50, 'sell' => 2.29, 'supplier' => 4],
    ['name' => 'Vinagre Balsamico 500ml', 'category' => 'Mercearia', 'cost' => 2.50, 'sell' => 4.49, 'supplier' => 4],
    ['name' => 'Sal Fino 1kg', 'category' => 'Mercearia', 'cost' => 0.20, 'sell' => 0.49, 'supplier' => 10],
    ['name' => 'Acucar 1kg', 'category' => 'Mercearia', 'cost' => 0.70, 'sell' => 1.09, 'supplier' => 10],
    ['name' => 'Farinha Trigo 1kg', 'category' => 'Mercearia', 'cost' => 0.45, 'sell' => 0.79, 'supplier' => 15],
    ['name' => 'Fermento Quimico 50g', 'category' => 'Mercearia', 'cost' => 0.15, 'sell' => 0.39, 'supplier' => 15],
    ['name' => 'Esparguete 500g', 'category' => 'Mercearia', 'cost' => 0.35, 'sell' => 0.69, 'supplier' => 4],
    ['name' => 'Arroz 500g', 'category' => 'Mercearia', 'cost' => 0.40, 'sell' => 0.79, 'supplier' => 4],
    ['name' => 'Mel 500g', 'category' => 'Mercearia', 'cost' => 1.50, 'sell' => 2.99, 'supplier' => 20],
    ['name' => 'Anis 30g', 'category' => 'Mercearia', 'cost' => 0.80, 'sell' => 1.49, 'supplier' => 4],
    ['name' => 'Pimenta Preta 30g', 'category' => 'Mercearia', 'cost' => 0.60, 'sell' => 1.29, 'supplier' => 4],
    ['name' => 'Canela 20g', 'category' => 'Mercearia', 'cost' => 0.50, 'sell' => 0.99, 'supplier' => 4],
    
    // FRUTAS (10 produtos)
    ['name' => 'Maca Gala kg', 'category' => 'Frutas', 'cost' => 0.90, 'sell' => 1.49, 'supplier' => 8],
    ['name' => 'Maca Fuji kg', 'category' => 'Frutas', 'cost' => 1.00, 'sell' => 1.69, 'supplier' => 8],
    ['name' => 'Pera Rocha kg', 'category' => 'Frutas', 'cost' => 0.95, 'sell' => 1.59, 'supplier' => 8],
    ['name' => 'Banana kg', 'category' => 'Frutas', 'cost' => 0.60, 'sell' => 0.99, 'supplier' => 8],
    ['name' => 'Laranja Seleccao kg', 'category' => 'Frutas', 'cost' => 0.80, 'sell' => 1.29, 'supplier' => 8],
    ['name' => 'Lmao kg', 'category' => 'Frutas', 'cost' => 1.50, 'sell' => 2.49, 'supplier' => 8],
    ['name' => 'Morango 400g', 'category' => 'Frutas', 'cost' => 1.80, 'sell' => 3.49, 'supplier' => 8],
    ['name' => 'Melancia kg', 'category' => 'Frutas', 'cost' => 0.40, 'sell' => 0.79, 'supplier' => 8],
    ['name' => 'Uvas Brancas kg', 'category' => 'Frutas', 'cost' => 1.80, 'sell' => 2.79, 'supplier' => 8],
    ['name' => 'Kiwi kg', 'category' => 'Frutas', 'cost' => 1.20, 'sell' => 1.99, 'supplier' => 8],
    
    // LEGUMES (10 produtos)
    ['name' => 'Tomate Carro kg', 'category' => 'Legumes', 'cost' => 0.85, 'sell' => 1.49, 'supplier' => 8],
    ['name' => 'Alface Iceberg un', 'category' => 'Legumes', 'cost' => 0.30, 'sell' => 0.69, 'supplier' => 8],
    ['name' => 'Alface Roxa un', 'category' => 'Legumes', 'cost' => 0.35, 'sell' => 0.79, 'supplier' => 8],
    ['name' => 'Cenoura kg', 'category' => 'Legumes', 'cost' => 0.50, 'sell' => 0.99, 'supplier' => 8],
    ['name' => 'Cebola Branca kg', 'category' => 'Legumes', 'cost' => 0.40, 'sell' => 0.79, 'supplier' => 8],
    ['name' => 'Alho kg', 'category' => 'Legumes', 'cost' => 2.00, 'sell' => 3.99, 'supplier' => 8],
    ['name' => 'Batata Doce kg', 'category' => 'Legumes', 'cost' => 0.80, 'sell' => 1.49, 'supplier' => 8],
    ['name' => 'Abobora kg', 'category' => 'Legumes', 'cost' => 0.70, 'sell' => 1.29, 'supplier' => 8],
    ['name' => 'Broculos un', 'category' => 'Legumes', 'cost' => 0.60, 'sell' => 1.29, 'supplier' => 8],
    ['name' => 'Espinafre 300g', 'category' => 'Legumes', 'cost' => 0.75, 'sell' => 1.49, 'supplier' => 8],
    
    // PADARIA (5 produtos)
    ['name' => 'Pao de Forma 500g', 'category' => 'Padaria', 'cost' => 0.75, 'sell' => 1.35, 'supplier' => 15],
    ['name' => 'Pao Integral 500g', 'category' => 'Padaria', 'cost' => 0.95, 'sell' => 1.69, 'supplier' => 15],
    ['name' => 'Croissant 50g', 'category' => 'Padaria', 'cost' => 0.25, 'sell' => 0.59, 'supplier' => 15],
    ['name' => 'Bolo Chocolate 400g', 'category' => 'Padaria', 'cost' => 1.50, 'sell' => 2.99, 'supplier' => 15],
    ['name' => 'Bolachas Chocolate 200g', 'category' => 'Padaria', 'cost' => 0.70, 'sell' => 1.29, 'supplier' => 14],
    
    // CARNES (6 produtos)
    ['name' => 'Peito Frango 500g', 'category' => 'Carnes', 'cost' => 3.50, 'sell' => 5.99, 'supplier' => 17],
    ['name' => 'Coxa Frango 500g', 'category' => 'Carnes', 'cost' => 2.50, 'sell' => 4.49, 'supplier' => 17],
    ['name' => 'Carne Picada 500g', 'category' => 'Carnes', 'cost' => 4.50, 'sell' => 7.99, 'supplier' => 17],
    ['name' => 'Bife Vaca 400g', 'category' => 'Carnes', 'cost' => 6.00, 'sell' => 10.99, 'supplier' => 17],
    ['name' => 'Fiambre Perna 200g', 'category' => 'Carnes', 'cost' => 1.20, 'sell' => 1.99, 'supplier' => 10],
    ['name' => 'Fiambre Frango 200g', 'category' => 'Carnes', 'cost' => 0.90, 'sell' => 1.59, 'supplier' => 10],
    
    // PEIXE (6 produtos)
    ['name' => 'Bacalhau Fresco 1kg', 'category' => 'Peixe', 'cost' => 7.50, 'sell' => 9.99, 'supplier' => 7],
    ['name' => 'Salmao Posta 500g', 'category' => 'Peixe', 'cost' => 6.00, 'sell' => 7.99, 'supplier' => 7],
    ['name' => 'Sardinha Fresca 500g', 'category' => 'Peixe', 'cost' => 2.50, 'sell' => 3.99, 'supplier' => 7],
    ['name' => 'Atum Fresco 400g', 'category' => 'Peixe', 'cost' => 4.00, 'sell' => 5.99, 'supplier' => 7],
    ['name' => 'Atum Posta Azeite 120g', 'category' => 'Peixe', 'cost' => 0.65, 'sell' => 1.15, 'supplier' => 7],
    ['name' => 'Sardinha Azeite 120g', 'category' => 'Peixe', 'cost' => 0.55, 'sell' => 0.99, 'supplier' => 7],
    
    // ENLATADOS (8 produtos)
    ['name' => 'Feijao Vermelho 400g', 'category' => 'Enlatados', 'cost' => 0.45, 'sell' => 0.79, 'supplier' => 12],
    ['name' => 'Milho Doce 300g', 'category' => 'Enlatados', 'cost' => 0.35, 'sell' => 0.69, 'supplier' => 12],
    ['name' => 'Ervilhas 300g', 'category' => 'Enlatados', 'cost' => 0.30, 'sell' => 0.59, 'supplier' => 12],
    ['name' => 'Cenoura 300g', 'category' => 'Enlatados', 'cost' => 0.30, 'sell' => 0.59, 'supplier' => 12],
    ['name' => 'Tomate Triturado 400g', 'category' => 'Enlatados', 'cost' => 0.35, 'sell' => 0.69, 'supplier' => 12],
    ['name' => 'Pimento 300g', 'category' => 'Enlatados', 'cost' => 0.40, 'sell' => 0.79, 'supplier' => 12],
    ['name' => 'Sopa Legumes 400g', 'category' => 'Enlatados', 'cost' => 0.50, 'sell' => 0.99, 'supplier' => 12],
    ['name' => 'Sopa Canja 400g', 'category' => 'Enlatados', 'cost' => 0.55, 'sell' => 1.09, 'supplier' => 12],
    
    // LIMPEZA (8 produtos)
    ['name' => 'Detergente Roupa 3L', 'category' => 'Limpeza', 'cost' => 4.50, 'sell' => 6.99, 'supplier' => 9],
    ['name' => 'Detergente Louca 500ml', 'category' => 'Limpeza', 'cost' => 0.80, 'sell' => 1.49, 'supplier' => 9],
    ['name' => 'Desinfetante 750ml', 'category' => 'Limpeza', 'cost' => 1.20, 'sell' => 2.49, 'supplier' => 9],
    ['name' => 'Papel Higienico 12un', 'category' => 'Limpeza', 'cost' => 2.50, 'sell' => 4.99, 'supplier' => 16],
    ['name' => 'Papel Cozinha 4un', 'category' => 'Limpeza', 'cost' => 1.00, 'sell' => 1.99, 'supplier' => 16],
    ['name' => 'Guardanapo 100 un', 'category' => 'Limpeza', 'cost' => 0.35, 'sell' => 0.69, 'supplier' => 16],
    ['name' => 'Esponja Inox 2un', 'category' => 'Limpeza', 'cost' => 0.40, 'sell' => 0.79, 'supplier' => 9],
    ['name' => 'Sacos Lixo 50L 10un', 'category' => 'Limpeza', 'cost' => 0.85, 'sell' => 1.69, 'supplier' => 9],
    
    // HIGIENE (8 produtos)
    ['name' => 'Sabonete Liquido 250ml', 'category' => 'Higiene', 'cost' => 0.50, 'sell' => 0.99, 'supplier' => 18],
    ['name' => 'Shampoo 400ml', 'category' => 'Higiene', 'cost' => 1.50, 'sell' => 2.99, 'supplier' => 18],
    ['name' => 'Condicionador 400ml', 'category' => 'Higiene', 'cost' => 1.50, 'sell' => 2.99, 'supplier' => 18],
    ['name' => 'Sabonete Solido 100g', 'category' => 'Higiene', 'cost' => 0.30, 'sell' => 0.59, 'supplier' => 18],
    ['name' => 'Desodorizante 150ml', 'category' => 'Higiene', 'cost' => 0.80, 'sell' => 1.49, 'supplier' => 18],
    ['name' => 'Escova Dentes', 'category' => 'Higiene', 'cost' => 0.40, 'sell' => 0.89, 'supplier' => 18],
    ['name' => 'Pasta Dentes 75ml', 'category' => 'Higiene', 'cost' => 0.60, 'sell' => 1.19, 'supplier' => 18],
    ['name' => 'Fio Dental 50m', 'category' => 'Higiene', 'cost' => 0.70, 'sell' => 1.29, 'supplier' => 18],
];

$productStmt = $pdo->prepare('INSERT INTO products (name, category, cost_price, sell_price, supplier_id, stock) VALUES (?, ?, ?, ?, ?, ?)');

foreach ($products as $p) {
    $stock = rand(50, 300); // Stock variado
    $productStmt->execute([$p['name'], $p['category'], $p['cost'], $p['sell'], $p['supplier'], $stock]);
}

echo "✓ " . count($products) . " produtos criados\n\n";

// RESUMO FINAL
echo "📊 DISTRIBUIÇÃO FINAL:\n\n";

$suppliers_result = $pdo->query('SELECT id, name FROM suppliers ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
foreach ($suppliers_result as $supplier) {
    $products_result = $pdo->query("SELECT COUNT(*) as cnt FROM products WHERE supplier_id = {$supplier['id']}")->fetch();
    $count = $products_result['cnt'];
    if ($count > 0) {
        echo "{$supplier['name']}: $count produtos\n";
    }
}

echo "\n✅ Base de dados populada com sucesso!\n";
?>
