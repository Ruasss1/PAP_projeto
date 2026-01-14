<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pricing.php';

$pdo = db_connect();

date_default_timezone_set('Europe/Lisbon');

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE ?');
        $stmt->execute([$column]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

function safe_wipe(PDO $pdo, string $table): void
{
    if (!table_exists($pdo, $table)) return;
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->exec('TRUNCATE TABLE `' . $table . '`');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    } catch (PDOException $e) {
        try {
            $pdo->exec('DELETE FROM `' . $table . '`');
        } catch (PDOException $e2) {
            // ignore
        }
    }
}

// 1) Clean tables that depend on products
$orderedWipes = [
    'promotion_products',
    'promotion_categories',
    'promotions',
    'margin_analysis',
    'price_change_log',
    'price_history',
    'pricing_strategies',
    'price_strategies',
    'receipts',
    'sale_items',
    'sales',
    'category_pricing_rules',
    'products',
    'suppliers'
];

foreach ($orderedWipes as $tbl) {
    safe_wipe($pdo, $tbl);
}

// Reset auto-increment where tables exist
foreach (['suppliers','products','promotions','promotion_products','promotion_categories','pricing_strategies','price_strategies','margin_analysis','category_pricing_rules','sales','sale_items','receipts'] as $tbl) {
    if (table_exists($pdo, $tbl)) {
        try { $pdo->exec('ALTER TABLE `' . $tbl . '` AUTO_INCREMENT = 1'); } catch (PDOException $e) {}
    }
}

// 2) Suppliers
$suppliers = [
    ['Luso', 'contacto@luso.pt'],
    ['Delta Cafes', 'vendas@delta.pt'],
    ['Riberalves', 'comercial@riberalves.pt'],
    ['Gallo', 'comercial@gallo.pt'],
    ['Super Bock Group', 'sales@superbock.pt'],
    ['Lactogal', 'clientes@lactogal.pt'],
    ['Mar Atlantico', 'mar@fornecedores.pt'],
    ['Hortas de Portugal', 'horta@fornecedores.pt'],
    ['Detergentes Skip', 'skip@fornecedores.pt'],
    ['Nobre', 'nobre@fornecedores.pt']
];

if (table_exists($pdo, 'suppliers')) {
    $stmt = $pdo->prepare('INSERT INTO suppliers (name, contact) VALUES (?, ?) ON DUPLICATE KEY UPDATE contact = VALUES(contact)');
    foreach ($suppliers as $s) {
        try { $stmt->execute([$s[0], $s[1]]); } catch (PDOException $e) {}
    }
}

// 3) Products (realistic assortment A-Z)
// Note: schema.sql products table only has: id, name, category, cost_price, sell_price, stock, expiry_date, created_at
// Categories simplified to match database constraints
$products = [
    ['name' => 'Agua Mineral 1.5L', 'category' => 'Bebidas', 'cost_price' => 0.09, 'sell_price' => 0.25, 'stock' => 240],
    ['name' => 'Arroz Agulha 1kg', 'category' => 'Mercearia', 'cost_price' => 0.75, 'sell_price' => 1.09, 'stock' => 180],
    ['name' => 'Atum Posta em Azeite 120g', 'category' => 'Enlatados', 'cost_price' => 0.65, 'sell_price' => 1.15, 'stock' => 150],
    ['name' => 'Azeite Virgem Extra 750ml', 'category' => 'Mercearia', 'cost_price' => 4.20, 'sell_price' => 6.49, 'stock' => 90],
    ['name' => 'Bacalhau Demolhado 1kg', 'category' => 'Peixe', 'cost_price' => 7.50, 'sell_price' => 9.99, 'stock' => 40],
    ['name' => 'Cafe Moido 250g', 'category' => 'Mercearia', 'cost_price' => 1.90, 'sell_price' => 2.99, 'stock' => 80],
    ['name' => 'Cerveja Lager 33cl', 'category' => 'Bebidas', 'cost_price' => 0.45, 'sell_price' => 0.89, 'stock' => 200],
    ['name' => 'Detergente Roupa 3L', 'category' => 'Limpeza', 'cost_price' => 4.50, 'sell_price' => 6.99, 'stock' => 60],
    ['name' => 'Esparguete 500g', 'category' => 'Mercearia', 'cost_price' => 0.35, 'sell_price' => 0.69, 'stock' => 160],
    ['name' => 'Fiambre da Perna 200g', 'category' => 'Carnes', 'cost_price' => 1.20, 'sell_price' => 1.99, 'stock' => 70],
    ['name' => 'Iogurte Natural 4x125g', 'category' => 'Laticinios', 'cost_price' => 0.85, 'sell_price' => 1.39, 'stock' => 110],
    ['name' => 'Leite Meio Gordo 1L', 'category' => 'Laticinios', 'cost_price' => 0.55, 'sell_price' => 0.79, 'stock' => 220],
    ['name' => 'Maca Gala kg', 'category' => 'Frutas', 'cost_price' => 0.90, 'sell_price' => 1.49, 'stock' => 130],
    ['name' => 'Pao de Forma 500g', 'category' => 'Padaria', 'cost_price' => 0.75, 'sell_price' => 1.35, 'stock' => 90],
    ['name' => 'Queijo Flamengo Barra kg', 'category' => 'Laticinios', 'cost_price' => 4.50, 'sell_price' => 6.99, 'stock' => 55],
    ['name' => 'Salmao Posta 1kg', 'category' => 'Peixe', 'cost_price' => 12.00, 'sell_price' => 15.99, 'stock' => 35],
    ['name' => 'Tomate Cherry 250g', 'category' => 'Legumes', 'cost_price' => 0.95, 'sell_price' => 1.69, 'stock' => 80],
    ['name' => 'Uvas Brancas kg', 'category' => 'Frutas', 'cost_price' => 1.80, 'sell_price' => 2.79, 'stock' => 75],
    ['name' => 'Vinho Tinto 75cl', 'category' => 'Bebidas', 'cost_price' => 2.20, 'sell_price' => 4.49, 'stock' => 90],
    ['name' => 'Zimbro em Grao 30g', 'category' => 'Mercearia', 'cost_price' => 1.50, 'sell_price' => 2.49, 'stock' => 50]
];

$productIds = [];
$stmt = $pdo->prepare('INSERT INTO products (name, category, cost_price, sell_price, stock) VALUES (?, ?, ?, ?, ?)');
foreach ($products as $p) {
    try {
        $stmt->execute([$p['name'], $p['category'], $p['cost_price'], $p['sell_price'], $p['stock']]);
        $productIds[$p['name']] = (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        echo "Erro inserindo " . $p['name'] . ": " . $e->getMessage() . "\n";
    }
}

// 4) Category pricing rules aligned to categories used
$categoryRules = [
    ['category' => 'Frutas', 'default_markup_percent' => 35.0, 'min_margin_percent' => 12.0, 'max_discount_percent' => 35.0],
    ['category' => 'Legumes', 'default_markup_percent' => 30.0, 'min_margin_percent' => 12.0, 'max_discount_percent' => 30.0],
    ['category' => 'Laticinios', 'default_markup_percent' => 28.0, 'min_margin_percent' => 10.0, 'max_discount_percent' => 25.0],
    ['category' => 'Carnes', 'default_markup_percent' => 32.0, 'min_margin_percent' => 15.0, 'max_discount_percent' => 25.0],
    ['category' => 'Peixe', 'default_markup_percent' => 30.0, 'min_margin_percent' => 14.0, 'max_discount_percent' => 25.0],
    ['category' => 'Bebidas', 'default_markup_percent' => 20.0, 'min_margin_percent' => 8.0, 'max_discount_percent' => 15.0],
    ['category' => 'Bebidas Alcoolicas', 'default_markup_percent' => 22.0, 'min_margin_percent' => 10.0, 'max_discount_percent' => 15.0],
    ['category' => 'Limpeza', 'default_markup_percent' => 40.0, 'min_margin_percent' => 20.0, 'max_discount_percent' => 20.0],
    ['category' => 'Mercearia', 'default_markup_percent' => 25.0, 'min_margin_percent' => 10.0, 'max_discount_percent' => 25.0],
    ['category' => 'Enlatados', 'default_markup_percent' => 22.0, 'min_margin_percent' => 10.0, 'max_discount_percent' => 25.0],
    ['category' => 'Padaria', 'default_markup_percent' => 30.0, 'min_margin_percent' => 12.0, 'max_discount_percent' => 20.0]
];

if (table_exists($pdo, 'category_pricing_rules')) {
    $stmt = $pdo->prepare('INSERT INTO category_pricing_rules (category, default_markup_percent, min_margin_percent, max_discount_percent, active) VALUES (?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE default_markup_percent = VALUES(default_markup_percent), min_margin_percent = VALUES(min_margin_percent), max_discount_percent = VALUES(max_discount_percent), active = 1');
    foreach ($categoryRules as $rule) {
        try {
            $stmt->execute([$rule['category'], $rule['default_markup_percent'], $rule['min_margin_percent'], $rule['max_discount_percent']]);
        } catch (PDOException $e) {}
    }
}

// 5) Pricing strategies per product
if (table_exists($pdo, 'pricing_strategies') || table_exists($pdo, 'price_strategies')) {
    foreach ($products as $p) {
        $id = $productIds[$p['name']];
        $markup = $p['cost_price'] > 0 ? round(($p['sell_price'] - $p['cost_price']) / $p['cost_price'] * 100, 2) : 30.00;
        $minPrice = round($p['sell_price'] * 0.9, 2);
        $maxPrice = round($p['sell_price'] * 1.2, 2);
        if (table_exists($pdo, 'pricing_strategies')) {
            try { set_price_strategy($id, $markup, $minPrice, $maxPrice, 'Seed auto'); } catch (Throwable $t) {}
        }
        if (table_exists($pdo, 'price_strategies')) {
            try {
                $stmt = $pdo->prepare('INSERT INTO price_strategies (product_id, markup_percent, min_price, max_price, notes, active) VALUES (?, ?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE markup_percent = VALUES(markup_percent), min_price = VALUES(min_price), max_price = VALUES(max_price), notes = VALUES(notes)');
                $stmt->execute([$id, $markup, $minPrice, $maxPrice, 'Seed auto']);
            } catch (PDOException $e) {}
        }
    }
}

// 6) Margin analysis snapshots
if (table_exists($pdo, 'margin_analysis')) {
    $columns = [];
    try { $columns = $pdo->query('SHOW COLUMNS FROM margin_analysis')->fetchAll(PDO::FETCH_COLUMN); } catch (PDOException $e) {}
    $colMap = array_flip($columns);

    $stmt = null;
    foreach ($products as $p) {
        $id = $productIds[$p['name']];
        $marginAmount = $p['sell_price'] - $p['cost_price'];
        $marginPercent = $p['sell_price'] > 0 ? ($marginAmount / $p['sell_price']) * 100 : 0;
        $markupPercent = $p['cost_price'] > 0 ? ($marginAmount / $p['cost_price']) * 100 : 0;
        $fields = [];
        $values = [];
        $placeholders = [];
        $data = [
            'product_id' => $id,
            'cost_price' => round($p['cost_price'], 2),
            'sell_price' => round($p['sell_price'], 2),
            'margin_amount' => round($marginAmount, 2),
            'margin_percent' => round($marginPercent, 2),
            'markup_percent' => round($markupPercent, 2),
            'category' => $p['category'],
            'analyzed_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 14) . ' days'))
        ];
        foreach ($data as $col => $val) {
            if (isset($colMap[$col])) {
                $fields[] = $col;
                $placeholders[] = '?';
                $values[] = $val;
            }
        }
        if (!empty($fields)) {
            $sql = 'INSERT INTO margin_analysis (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
            } catch (PDOException $e) {}
        }
    }
}

// 7) Promotions with links to products/categories
function insert_promotion(PDO $pdo, array $promo): ?int
{
    if (!table_exists($pdo, 'promotions')) return null;
    $cols = [];
    try { $cols = $pdo->query('SHOW COLUMNS FROM promotions')->fetchAll(PDO::FETCH_COLUMN); } catch (PDOException $e) {}
    $map = array_flip($cols);
    $fields = [];
    $placeholders = [];
    $values = [];
    $data = [
        'name' => $promo['name'],
        'description' => $promo['description'] ?? null,
        'discount_type' => $promo['discount_type'],
        'discount_value' => $promo['discount_value'],
        'start_date' => $promo['start_date'],
        'end_date' => $promo['end_date'],
        'apply_to' => $promo['apply_to'] ?? 'product',
        'active' => $promo['active'] ?? 1,
        'is_active' => $promo['active'] ?? 1,
        'product_id' => $promo['product_id'] ?? null
    ];
    foreach ($data as $col => $val) {
        if (isset($map[$col])) {
            $fields[] = $col;
            $placeholders[] = '?';
            $values[] = $val;
        }
    }
    if (empty($fields)) return null;
    $sql = 'INSERT INTO promotions (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    return (int)$pdo->lastInsertId();
}

$promoProductsTable = table_exists($pdo, 'promotion_products');
$promoCategoriesTable = table_exists($pdo, 'promotion_categories');

$promoDefs = [
    [
        'name' => 'Frescos -10%',
        'description' => 'Campanha frutas e legumes semana atual',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'start_date' => date('Y-m-d 00:00:00', strtotime('-2 days')),
        'end_date' => date('Y-m-d 23:59:59', strtotime('+5 days')),
        'apply_to' => 'category',
        'active' => 1,
        'products' => [],
        'categories' => ['Frutas', 'Legumes']
    ],
    [
        'name' => 'Peixe do Dia -15%',
        'description' => 'Desconto peixe fresco selecionado',
        'discount_type' => 'percentage',
        'discount_value' => 15,
        'start_date' => date('Y-m-d 00:00:00', strtotime('-1 days')),
        'end_date' => date('Y-m-d 23:59:59', strtotime('+3 days')),
        'apply_to' => 'product',
        'active' => 1,
        'products' => ['Salmao Posta 1kg', 'Bacalhau Demolhado 1kg'],
        'categories' => []
    ],
    [
        'name' => 'Roupa Cheirosa -2€',
        'description' => 'Detergente em destaque',
        'discount_type' => 'fixed',
        'discount_value' => 2.00,
        'start_date' => date('Y-m-d 00:00:00', strtotime('-1 days')),
        'end_date' => date('Y-m-d 23:59:59', strtotime('+7 days')),
        'apply_to' => 'product',
        'active' => 1,
        'products' => ['Detergente Roupa 3L'],
        'categories' => []
    ],
    [
        'name' => 'Pack Laticinios 8%',
        'description' => 'Leite, queijo e iogurtes com desconto',
        'discount_type' => 'percentage',
        'discount_value' => 8,
        'start_date' => date('Y-m-d 00:00:00', strtotime('-3 days')),
        'end_date' => date('Y-m-d 23:59:59', strtotime('+10 days')),
        'apply_to' => 'category',
        'active' => 1,
        'products' => [],
        'categories' => ['Laticinios']
    ]
];

foreach ($promoDefs as $promo) {
    $promoId = insert_promotion($pdo, $promo);
    if (!$promoId) continue;

    if ($promoProductsTable && !empty($promo['products'])) {
        $stmt = $pdo->prepare('INSERT IGNORE INTO promotion_products (promotion_id, product_id) VALUES (?, ?)');
        foreach ($promo['products'] as $prodName) {
            if (isset($productIds[$prodName])) {
                try { $stmt->execute([$promoId, $productIds[$prodName]]); } catch (PDOException $e) {}
            }
        }
    }

    if ($promoCategoriesTable && !empty($promo['categories'])) {
        $stmtCat = $pdo->prepare('INSERT IGNORE INTO promotion_categories (promotion_id, category) VALUES (?, ?)');
        foreach ($promo['categories'] as $cat) {
            try { $stmtCat->execute([$promoId, $cat]); } catch (PDOException $e) {}
        }
    }
}

echo "Seed concluido: " . count($productIds) . " produtos reais carregados.\n";

// 8) Gerar vendas diárias últimos 6 meses com recibos
if (table_exists($pdo, 'sales') && table_exists($pdo, 'sale_items')) {
    $hasCostPrice = column_exists($pdo, 'sale_items', 'cost_price');
    $hasPayment = column_exists($pdo, 'sales', 'payment_method');
    $hasReceipts = table_exists($pdo, 'receipts');

    $paymentMethods = ['Dinheiro', 'MB Way', 'Cartão'];

    $start = new DateTime('-180 days');
    $end = new DateTime('now');
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end->modify('+1 day'));

    $stmtSale = $pdo->prepare('INSERT INTO sales (sale_date, total' . ($hasPayment ? ', payment_method' : '') . ') VALUES (?, ?' . ($hasPayment ? ', ?' : '') . ')');
    $stmtSaleItem = $pdo->prepare('INSERT INTO sale_items (sale_id, category, product_id, quantity, price' . ($hasCostPrice ? ', cost_price' : '') . ') VALUES (?, ?, ?, ?, ?' . ($hasCostPrice ? ', ?' : '') . ')');
    $stmtUpdateTotal = $pdo->prepare('UPDATE sales SET total = ? WHERE id = ?');
    $stmtReceipt = $hasReceipts ? $pdo->prepare('INSERT INTO receipts (sale_id, receipt_number, total, payment_method, created_at) VALUES (?, ?, ?, ?, ?)') : null;

    foreach ($period as $day) {
        $salesCount = rand(15, 35); // vendas por dia - aumentado para dados mais realistas
        for ($s = 0; $s < $salesCount; $s++) {
            $saleDate = (clone $day)->setTime(rand(8, 21), rand(0, 59), rand(0, 59))->format('Y-m-d H:i:s');
            $payment = $paymentMethods[array_rand($paymentMethods)];

            $stmtSale->execute($hasPayment ? [$saleDate, 0, $payment] : [$saleDate, 0]);
            $saleId = (int)$pdo->lastInsertId();

            $itemsThisSale = rand(2, 5);
            $picked = array_rand($productIds, $itemsThisSale);
            if (!is_array($picked)) { $picked = [$picked]; }
            $totalSale = 0;

            foreach ($picked as $prodName) {
                $pid = $productIds[$prodName];
                $qty = rand(1, 4);

                $prodStmt = $pdo->prepare('SELECT sell_price, cost_price, category, stock FROM products WHERE id = ?');
                $prodStmt->execute([$pid]);
                $prodRow = $prodStmt->fetch(PDO::FETCH_ASSOC);
                if (!$prodRow) continue;

                $price = (float)$prodRow['sell_price'];
                $cost = (float)$prodRow['cost_price'];
                $totalSale += $price * $qty;

                // inserir item
                $params = [$saleId, $prodRow['category'], $pid, $qty, $price];
                if ($hasCostPrice) $params[] = $cost;
                $stmtSaleItem->execute($params);

                // opcional: baixar stock
                try {
                    $newStock = max(0, ($prodRow['stock'] ?? 0) - $qty);
                    $pdo->prepare('UPDATE products SET stock = ? WHERE id = ?')->execute([$newStock, $pid]);
                } catch (PDOException $e) {}
            }

            $stmtUpdateTotal->execute([round($totalSale, 2), $saleId]);

            if ($hasReceipts) {
                $receiptNumber = 'RC-' . date('Ymd', strtotime($saleDate)) . '-' . str_pad((string)$saleId, 5, '0', STR_PAD_LEFT);
                try { $stmtReceipt->execute([$saleId, $receiptNumber, round($totalSale, 2), $payment, $saleDate]); } catch (PDOException $e) {}
            }
        }
    }
}
