<?php
/**
 * SCRIPT DE POPULAÇÃO COMPLETA DA BASE DE DADOS
 * Popula: stores, products, employees, customers, suppliers,
 *         sales, orders, vacations, payroll, shifts, notifications
 */

require_once __DIR__ . '/../config/database.php';

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');

// ============================================================
// HELPER
// ============================================================
function rand_date($from, $to) {
    return date('Y-m-d H:i:s', rand(strtotime($from), strtotime($to)));
}
function rand_date_only($from, $to) {
    return date('Y-m-d', rand(strtotime($from), strtotime($to)));
}

echo "=== POPULAÇÃO COMPLETA ===" . PHP_EOL;

// ============================================================
// STORES
// ============================================================
$stores = $pdo->query('SELECT id, name FROM stores')->fetchAll();
if (empty($stores)) {
    $pdo->exec("INSERT INTO stores (name, address, phone, email, active) VALUES
        ('PAP Market Lisboa', 'Av. da Liberdade, 150, Lisboa', '213001001', 'lisboa@papmarket.pt', 1),
        ('PAP Market Porto', 'Rua de Santa Catarina, 80, Porto', '222001001', 'porto@papmarket.pt', 1),
        ('PAP Market Coimbra', 'Rua Ferreira Borges, 45, Coimbra', '239001001', 'coimbra@papmarket.pt', 1)");
    $stores = $pdo->query('SELECT id, name FROM stores')->fetchAll();
    echo "✓ " . count($stores) . " lojas criadas" . PHP_EOL;
} else {
    echo "✓ " . count($stores) . " lojas existentes" . PHP_EOL;
}

// ============================================================
// ROLES
// ============================================================
$roles = $pdo->query('SELECT id FROM roles')->fetchAll(PDO::FETCH_COLUMN);
if (empty($roles)) {
    $pdo->exec("INSERT INTO roles (name, permissions) VALUES
        ('admin', '{\"all\": true}'),
        ('gerente', '{\"sales\": true, \"reports\": true, \"employees\": true}'),
        ('caixa', '{\"sales\": true}'),
        ('repositor', '{\"stock\": true}')");
    echo "✓ Roles criadas" . PHP_EOL;
}
$roles = $pdo->query('SELECT id FROM roles')->fetchAll(PDO::FETCH_COLUMN);

// ============================================================
// SUPPLIERS
// ============================================================
$supplier_count = $pdo->query('SELECT COUNT(*) FROM suppliers')->fetchColumn();
if ($supplier_count < 5) {
    $pdo->exec("INSERT INTO suppliers (name, contact_name, phone, email, address, nif, active) VALUES
        ('Distribuição Nacional Lda', 'João Silva', '210000001', 'geral@dn.pt', 'Zona Industrial, Lisboa', '500000001', 1),
        ('FrutaFresca SA', 'Maria Costa', '220000002', 'vendas@frutafresca.pt', 'Mercado Abastecedor, Porto', '500000002', 1),
        ('Lácteos do Norte Lda', 'António Ferreira', '230000003', 'comercial@lacteosnorte.pt', 'Braga', '500000003', 1),
        ('Padaria Central SA', 'Carla Mendes', '214000004', 'pedidos@padariacentral.pt', 'Amadora, Lisboa', '500000004', 1),
        ('BebidasPt Lda', 'Rui Neves', '225000005', 'vendas@bebidaspt.pt', 'Gaia, Porto', '500000005', 1),
        ('CongeladosMax SA', 'Sofia Alves', '236000006', 'info@congeladosmax.pt', 'Coimbra', '500000006', 1),
        ('HigieneTotal Lda', 'Pedro Rodrigues', '211000007', 'comercial@higienitotal.pt', 'Sintra', '500000007', 1)");
    echo "✓ Fornecedores criados" . PHP_EOL;
}
$suppliers = $pdo->query('SELECT id FROM suppliers')->fetchAll(PDO::FETCH_COLUMN);

// ============================================================
// PRODUCTS (para cada loja)
// ============================================================
$products_data = [
    // [name, barcode_prefix, category, price, cost, stock, unit]
    ['Leite Mimosa Meio-gordo 1L', '5600001', 'Lacticínios', 1.09, 0.65, 120, 'un'],
    ['Leite Agros Magro 1L', '5600002', 'Lacticínios', 0.99, 0.58, 95, 'un'],
    ['Iogurte Natural Danone 4x125g', '5600003', 'Lacticínios', 1.89, 1.10, 80, 'un'],
    ['Manteiga Milhafre 250g', '5600004', 'Lacticínios', 2.29, 1.40, 60, 'un'],
    ['Queijo Flamengo Fatiado 200g', '5600005', 'Lacticínios', 2.79, 1.65, 70, 'un'],
    ['Pão de Forma Bimbo 450g', '5600006', 'Padaria', 1.49, 0.85, 50, 'un'],
    ['Croissant Amanteigado 6un', '5600007', 'Padaria', 2.49, 1.40, 40, 'un'],
    ['Bolo de Arroz 6un', '5600008', 'Padaria', 1.99, 1.10, 35, 'un'],
    ['Broa de Milho 500g', '5600009', 'Padaria', 1.29, 0.70, 30, 'un'],
    ['Pão Integral 400g', '5600010', 'Padaria', 1.69, 0.95, 45, 'un'],
    ['Maçã Fuji 1kg', '5600011', 'Frutas', 1.99, 1.10, 200, 'kg'],
    ['Banana 1kg', '5600012', 'Frutas', 1.49, 0.80, 180, 'kg'],
    ['Laranja 1kg', '5600013', 'Frutas', 1.29, 0.70, 160, 'kg'],
    ['Uvas Brancas 500g', '5600014', 'Frutas', 2.49, 1.40, 80, 'un'],
    ['Morangos 250g', '5600015', 'Frutas', 2.99, 1.70, 60, 'un'],
    ['Água Luso 1.5L', '5600016', 'Bebidas', 0.59, 0.30, 300, 'un'],
    ['Coca-Cola 1.5L', '5600017', 'Bebidas', 1.99, 1.10, 150, 'un'],
    ['Sumol Laranja 0.33L', '5600018', 'Bebidas', 1.09, 0.60, 120, 'un'],
    ['Cerveja Super Bock 0.33L', '5600019', 'Bebidas', 0.99, 0.55, 200, 'un'],
    ['Sumo Compal Maçã 1L', '5600020', 'Bebidas', 1.79, 1.00, 90, 'un'],
    ['Arroz Carolino Bom Sucesso 1kg', '5600021', 'Mercearia', 1.29, 0.70, 150, 'un'],
    ['Massa Esparguete Milaneza 500g', '5600022', 'Mercearia', 0.89, 0.48, 130, 'un'],
    ['Azeite Gallo 0.75L', '5600023', 'Mercearia', 5.49, 3.20, 80, 'un'],
    ['Atum Pê de Pato 120g', '5600024', 'Mercearia', 1.49, 0.85, 100, 'un'],
    ['Feijão Vermelho Compal 400g', '5600025', 'Mercearia', 0.99, 0.55, 90, 'un'],
    ['Açúcar Branco 1kg', '5600026', 'Mercearia', 1.09, 0.60, 120, 'un'],
    ['Farinha Tipo 55 Branca de Neve 1kg', '5600027', 'Mercearia', 0.79, 0.42, 110, 'un'],
    ['Café Delta Grão 250g', '5600028', 'Mercearia', 3.49, 2.00, 70, 'un'],
    ['Frango Inteiro 1.2kg', '5600029', 'Congelados', 4.99, 2.90, 50, 'un'],
    ['Peixe Pangasius Filetes 500g', '5600030', 'Congelados', 3.49, 1.95, 45, 'un'],
    ['Pizza Margherita Dr. Oetker', '5600031', 'Congelados', 3.99, 2.20, 40, 'un'],
    ['Batata Frita McCain 750g', '5600032', 'Congelados', 2.79, 1.55, 55, 'un'],
    ['Gel de Banho Dove 400ml', '5600033', 'Higiene', 3.29, 1.85, 80, 'un'],
    ['Champô Pantene 300ml', '5600034', 'Higiene', 4.49, 2.55, 65, 'un'],
    ['Pasta Dentes Colgate 75ml', '5600035', 'Higiene', 2.19, 1.20, 90, 'un'],
    ['Desodorizante Rexona 150ml', '5600036', 'Higiene', 3.99, 2.25, 70, 'un'],
    ['Detergente Roupa Ariel 1.5L', '5600037', 'Limpeza', 7.99, 4.50, 45, 'un'],
    ['Lixívia Sonasol 1L', '5600038', 'Limpeza', 1.49, 0.80, 80, 'un'],
    ['Detergente Loiça Fairy 500ml', '5600039', 'Limpeza', 2.99, 1.65, 75, 'un'],
    ['Papel Higiénico Renova 12un', '5600040', 'Higiene', 4.99, 2.80, 60, 'un'],
];

$existing_products = $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
if ($existing_products < 10) {
    $insert_prod = $pdo->prepare('INSERT INTO products (name, barcode, category, sell_price, cost_price, stock, unit, store_id, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)');
    foreach ($stores as $store) {
        foreach ($products_data as $i => $p) {
            $barcode = $p[1] . str_pad($store['id'], 2, '0', STR_PAD_LEFT);
            try {
                $insert_prod->execute([$p[0], $barcode, $p[2], $p[3], $p[4], rand($p[5]*0.5, $p[5]*1.5), $p[6], $store['id']]);
            } catch(Exception $e) { /* ignore dups */ }
        }
    }
    echo "✓ Produtos criados" . PHP_EOL;
} else {
    echo "✓ Produtos existentes: $existing_products" . PHP_EOL;
}

// ============================================================
// CUSTOMERS
// ============================================================
$cust_count = $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
if ($cust_count < 20) {
    $names = ['Ana Silva','Bruno Costa','Carla Ferreira','Diogo Santos','Eva Rodrigues',
              'Filipe Alves','Gabriela Pereira','Hugo Martins','Inês Carvalho','João Oliveira',
              'Kristina Sousa','Luís Cunha','Maria Lopes','Nuno Melo','Olga Fonseca',
              'Paulo Ribeiro','Quitéria Neves','Ricardo Teixeira','Sara Vieira','Tiago Pinto',
              'Ursula Gomes','Vasco Correia','Wanda Moreira','Xavier Campos','Yolanda Barros',
              'Zélia Monteiro','André Sequeira','Beatriz Freitas','Carlos Machado','Dina Coelho'];
    $insert_cust = $pdo->prepare('INSERT INTO customers (name, email, phone, nif, points, total_spent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($names as $i => $name) {
        $slug = strtolower(str_replace(' ', '.', iconv('UTF-8', 'ASCII//TRANSLIT', $name)));
        try {
            $insert_cust->execute([
                $name,
                $slug . '@email.pt',
                '9' . rand(10000000, 99999999),
                '1' . str_pad($i + 1, 8, '0', STR_PAD_LEFT),
                rand(0, 5000),
                round(rand(50, 15000) + rand(0,99)/100, 2),
                rand_date('2023-01-01', '2025-12-31')
            ]);
        } catch(Exception $e) {}
    }
    echo "✓ Clientes criados" . PHP_EOL;
}
$customers = $pdo->query('SELECT id FROM customers')->fetchAll(PDO::FETCH_COLUMN);

// ============================================================
// EMPLOYEES
// ============================================================
$emp_count = $pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn();

// Check employees columns
$emp_cols = [];
foreach ($pdo->query('DESCRIBE employees')->fetchAll() as $r) $emp_cols[] = $r['Field'];

if ($emp_count < 5) {
    $emp_names = [
        ['Miguel Ferreira', 'gerente', 1850.00],
        ['Ana Sousa', 'caixa', 820.00],
        ['Pedro Martins', 'caixa', 820.00],
        ['Joana Alves', 'repositor', 780.00],
        ['Rui Costa', 'gerente', 1750.00],
        ['Sofia Lopes', 'caixa', 820.00],
        ['Carlos Silva', 'repositor', 780.00],
        ['Helena Ribeiro', 'caixa', 820.00],
        ['Nuno Ferreira', 'caixa', 820.00],
        ['Marta Oliveira', 'gerente', 1900.00],
        ['Tiago Santos', 'repositor', 780.00],
        ['Vera Cunha', 'caixa', 820.00],
    ];

    // Build insert based on available columns
    foreach ($emp_names as $i => $emp) {
        $store_id = $stores[$i % count($stores)]['id'];
        $hire_date = rand_date_only('2020-01-01', '2024-06-01');
        
        $fields = ['name', 'position', 'salary', 'store_id', 'hire_date', 'active'];
        $vals = [$emp[0], $emp[1], $emp[2], $store_id, $hire_date, 1];
        
        if (in_array('email', $emp_cols)) {
            $fields[] = 'email';
            $slug = strtolower(str_replace(' ', '.', iconv('UTF-8', 'ASCII//TRANSLIT', $emp[0])));
            $vals[] = $slug . '@papmarket.pt';
        }
        if (in_array('phone', $emp_cols)) {
            $fields[] = 'phone';
            $vals[] = '9' . rand(10000000, 99999999);
        }
        if (in_array('nif', $emp_cols)) {
            $fields[] = 'nif';
            $vals[] = '2' . str_pad($i + 1, 8, '0', STR_PAD_LEFT);
        }
        if (in_array('status', $emp_cols)) {
            $fields[] = 'status';
            $vals[] = 'Ativo';
        }

        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $field_names = implode(',', $fields);
        try {
            $pdo->prepare("INSERT INTO employees ($field_names) VALUES ($placeholders)")->execute($vals);
        } catch(Exception $e) { echo "  Emp error: " . $e->getMessage() . PHP_EOL; }
    }
    echo "✓ Funcionários criados" . PHP_EOL;
}
$employees = $pdo->query('SELECT id, store_id FROM employees')->fetchAll();

// ============================================================
// SALES (últimos 12 meses)
// ============================================================
$products_by_store = [];
foreach ($stores as $store) {
    $prods = $pdo->prepare('SELECT id, sell_price as price FROM products WHERE store_id = ? AND active = 1 LIMIT 40');
    $prods->execute([$store['id']]);
    $products_by_store[$store['id']] = $prods->fetchAll();
}

$payment_methods = ['Dinheiro', 'Multibanco', 'MB Way', 'Visa', 'Mastercard'];

$sales_count = $pdo->query('SELECT COUNT(*) FROM sales')->fetchColumn();
if ($sales_count < 100) {
    echo "A criar vendas (pode demorar uns segundos)..." . PHP_EOL;
    
    $insert_sale = $pdo->prepare('INSERT INTO sales (store_id, payment_method, total, sale_date) VALUES (?,?,?,?)');
    $insert_item = $pdo->prepare('INSERT INTO sale_items (sale_id, product_id, quantity, price, cost_price) VALUES (?,?,?,?,?)');
    
    $total_sales = 0;
    foreach ($stores as $store) {
        $store_prods = $products_by_store[$store['id']];
        if (empty($store_prods)) continue;
        
        // Gerar vendas para cada dia dos últimos 365 dias
        for ($day = 365; $day >= 0; $day--) {
            $date = date('Y-m-d', strtotime("-$day days"));
            $weekday = date('N', strtotime($date)); // 1=Mon, 7=Sun
            
            // Mais vendas ao fim de semana, menos à segunda
            $sales_per_day = $weekday >= 6 ? rand(15, 30) : ($weekday == 1 ? rand(5, 12) : rand(8, 20));
            
            // Padrões sazonais (verão/natal com mais vendas)
            $month = (int)date('n', strtotime($date));
            if (in_array($month, [7, 8, 12])) $sales_per_day = (int)($sales_per_day * 1.4);
            
            for ($s = 0; $s < $sales_per_day; $s++) {
                $hour = rand(8, 21);
                $minute = rand(0, 59);
                $sale_date = $date . ' ' . str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minute, 2, '0', STR_PAD_LEFT) . ':00';
                
                $payment = $payment_methods[array_rand($payment_methods)];
                
                $n_items = rand(1, 8);
                $total = 0.0;
                $items = [];
                
                $selected_prods = array_rand(array_column($store_prods, 'id'), min($n_items, count($store_prods)));
                if (!is_array($selected_prods)) $selected_prods = [$selected_prods];
                
                foreach ($selected_prods as $pi) {
                    $qty = rand(1, 4);
                    $price = (float)$store_prods[$pi]['price'];
                    $cost = round($price * 0.6, 2);
                    $total += $qty * $price;
                    $items[] = [$store_prods[$pi]['id'], $qty, $price, $cost];
                }
                $total = round($total, 2);
                
                $insert_sale->execute([$store['id'], $payment, $total, $sale_date]);
                $sale_id = $pdo->lastInsertId();
                
                foreach ($items as $item) {
                    $insert_item->execute([$sale_id, $item[0], $item[1], $item[2], $item[3]]);
                }
                $total_sales++;
            }
        }
    }
    echo "✓ $total_sales vendas criadas" . PHP_EOL;
} else {
    echo "✓ Vendas existentes: $sales_count" . PHP_EOL;
}

// ============================================================
// ORDERS (Encomendas a fornecedores)
// ============================================================
$orders_count = $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
if ($orders_count < 10) {
    // Check orders columns
    $ord_cols = array_column($pdo->query('DESCRIBE orders')->fetchAll(), 'Field');
    
    $statuses = ['pending', 'processed', 'shipped', 'delivered'];
    $insert_order = $pdo->prepare('INSERT INTO orders (supplier_id, status, total_cost, created_at) VALUES (?,?,?,?)');
    $insert_oi = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, cost_price) VALUES (?,?,?,?)');
    
    foreach ($stores as $store) {
        $store_prods = $products_by_store[$store['id']];
        if (empty($store_prods)) continue;
        
        for ($o = 0; $o < 15; $o++) {
            $order_date = rand_date('2025-10-01', '2026-04-17');
            $status = $statuses[array_rand($statuses)];
            $supplier_id = $suppliers[array_rand($suppliers)];
            
            $n_items = rand(3, 10);
            $total = 0.0;
            $order_items = [];
            $selected = array_rand(array_column($store_prods, 'id'), min($n_items, count($store_prods)));
            if (!is_array($selected)) $selected = [$selected];
            
            foreach ($selected as $pi) {
                $qty = rand(10, 100);
                $cost = round((float)$store_prods[$pi]['price'] * 0.6, 2);
                $total += $qty * $cost;
                $order_items[] = [$store_prods[$pi]['id'], $qty, $cost];
            }
            
            try {
                $insert_order->execute([$supplier_id, $status, round($total, 2), $order_date]);
                $order_id = $pdo->lastInsertId();
                foreach ($order_items as $oi) {
                    try { $insert_oi->execute([$order_id, $oi[0], $oi[1], $oi[2]]); } catch(Exception $e) {}
                }
            } catch(Exception $e) { echo "  Order error: " . $e->getMessage() . PHP_EOL; }
        }
    }
    echo "✓ Encomendas criadas" . PHP_EOL;
}

// ============================================================
// VACATION REQUESTS
// ============================================================
$vac_count = $pdo->query('SELECT COUNT(*) FROM vacation_requests')->fetchColumn();
if ($vac_count < 5 && !empty($employees)) {
    $vac_cols = array_column($pdo->query('DESCRIBE vacation_requests')->fetchAll(), 'Field');
    $vac_statuses = ['pending', 'approved', 'rejected'];
    $vac_types = ['Férias', 'Folga', 'Licença Médica', 'Licença Parental'];

    foreach ($employees as $emp) {
        $n_reqs = rand(1, 3);
        for ($v = 0; $v < $n_reqs; $v++) {
            $start = rand_date_only('2025-06-01', '2026-12-31');
            $end = date('Y-m-d', strtotime($start) + rand(3, 21) * 86400);
            $status = $vac_statuses[array_rand($vac_statuses)];
            $type = $vac_types[array_rand($vac_types)];
            
            $fields = ['employee_id', 'start_date', 'end_date', 'status'];
            $vals = [$emp['id'], $start, $end, $status];
            
            if (in_array('type', $vac_cols)) { $fields[] = 'type'; $vals[] = $type; }
            if (in_array('request_type', $vac_cols)) { $fields[] = 'request_type'; $vals[] = $type; }
            if (in_array('notes', $vac_cols) || in_array('reason', $vac_cols)) {
                $col = in_array('notes', $vac_cols) ? 'notes' : 'reason';
                $fields[] = $col; $vals[] = 'Pedido de ' . strtolower($type);
            }
            if (in_array('created_at', $vac_cols)) {
                $fields[] = 'created_at'; $vals[] = rand_date('2025-05-01', '2026-04-01');
            }
            
            $placeholders = implode(',', array_fill(0, count($fields), '?'));
            $field_names = implode(',', $fields);
            try {
                $pdo->prepare("INSERT INTO vacation_requests ($field_names) VALUES ($placeholders)")->execute($vals);
            } catch(Exception $e) { echo "  Vac error: " . $e->getMessage() . PHP_EOL; }
        }
    }
    echo "✓ Férias/pedidos criados" . PHP_EOL;
}

// ============================================================
// PAYROLL
// ============================================================
$pay_count = $pdo->query('SELECT COUNT(*) FROM payroll')->fetchColumn();
if ($pay_count < 5 && !empty($employees)) {
    $pay_cols = array_column($pdo->query('DESCRIBE payroll')->fetchAll(), 'Field');

    foreach ($employees as $emp) {
        $sal = $pdo->prepare('SELECT salary FROM employees WHERE id = ?');
        $sal->execute([$emp['id']]);
        $base = (float)($sal->fetchColumn() ?: 820.00);
        
        for ($m = 11; $m >= 0; $m--) {
            $month = date('Y-m', strtotime("-$m months"));
            [$y, $mo] = explode('-', $month);
            
            $fields = ['employee_id', 'month', 'year', 'base_salary', 'net_salary', 'status'];
            $irs = round($base * 0.15, 2);
            $ss = round($base * 0.11, 2);
            $net = round($base - $irs - $ss, 2);
            $vals = [$emp['id'], (int)$mo, (int)$y, $base, $net, $m > 0 ? 'paid' : 'pending'];
            
            if (in_array('irs_retention', $pay_cols)) { $fields[] = 'irs_retention'; $vals[] = $irs; }
            if (in_array('social_security', $pay_cols)) { $fields[] = 'social_security'; $vals[] = $ss; }
            if (in_array('overtime_hours', $pay_cols)) { $fields[] = 'overtime_hours'; $vals[] = rand(0, 8); }
            if (in_array('overtime_pay', $pay_cols)) { $fields[] = 'overtime_pay'; $vals[] = round(rand(0,8) * ($base/160) * 1.5, 2); }
            if (in_array('payment_date', $pay_cols) && $m > 0) { $fields[] = 'payment_date'; $vals[] = $month . '-28'; }
            
            $placeholders = implode(',', array_fill(0, count($fields), '?'));
            $field_names = implode(',', $fields);
            try {
                $pdo->prepare("INSERT IGNORE INTO payroll ($field_names) VALUES ($placeholders)")->execute($vals);
            } catch(Exception $e) { echo "  Pay error: " . $e->getMessage() . PHP_EOL; }
        }
    }
    echo "✓ Folhas de pagamento criadas" . PHP_EOL;
}

// ============================================================
// SHIFTS / SCHEDULES
// ============================================================
$shift_count = $pdo->query('SELECT COUNT(*) FROM shifts')->fetchColumn();
if ($shift_count < 5 && !empty($employees)) {
    $shift_cols = array_column($pdo->query('DESCRIBE shifts')->fetchAll(), 'Field');
    
    $shift_types = [
        ['Manhã', '08:00:00', '16:00:00'],
        ['Tarde', '14:00:00', '22:00:00'],
        ['Normal', '09:00:00', '18:00:00'],
    ];
    
    foreach ($employees as $emp) {
        foreach ($shift_types as $st) {
            $fields = ['employee_id', 'shift_name', 'start_time', 'end_time'];
            $vals = [$emp['id'], $st[0], $st[1], $st[2]];
            
            if (in_array('store_id', $shift_cols)) { $fields[] = 'store_id'; $vals[] = $emp['store_id']; }
            if (in_array('days_of_week', $shift_cols)) { $fields[] = 'days_of_week'; $vals[] = '1,2,3,4,5'; }
            
            $placeholders = implode(',', array_fill(0, count($fields), '?'));
            $field_names = implode(',', $fields);
            try {
                $pdo->prepare("INSERT INTO shifts ($field_names) VALUES ($placeholders)")->execute($vals);
                break; // só um turno por funcionário
            } catch(Exception $e) {}
        }
    }
    echo "✓ Turnos criados" . PHP_EOL;
}

// ============================================================
// PROMOTIONS
// ============================================================
$promo_count = $pdo->query('SELECT COUNT(*) FROM promotions')->fetchColumn();
if ($promo_count < 3) {
    $promo_cols = array_column($pdo->query('DESCRIBE promotions')->fetchAll(), 'Field');
    
    $promos = [
        ['Promoção de Verão', 'percentage', '10', '2026-06-01', '2026-08-31'],
        ['Desconto Semanal', 'percentage', '5', '2026-04-14', '2026-04-20'],
        ['Liquidação Congelados', 'percentage', '20', '2026-04-01', '2026-04-30'],
        ['Aniversário PAP Market', 'percentage', '15', '2026-05-01', '2026-05-15'],
    ];
    
    foreach ($promos as $p) {
        try { $pdo->prepare('INSERT INTO promotions (name, type, discount_type, value, discount_value, start_date, end_date, active, is_active, store_id) VALUES (?,?,?,?,?,?,?,1,1,?)')->execute([$p[0], $p[1], $p[1], $p[2], $p[2], $p[3], $p[4], $stores[0]['id']]); }
        catch(Exception $e) { echo "  Promo error: " . $e->getMessage() . PHP_EOL; }
    }
    echo "✓ Promoções criadas" . PHP_EOL;
}

// ============================================================
// NOTIFICATIONS
// ============================================================
$notif_count = $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn();
if ($notif_count < 5) {
    $notif_cols = array_column($pdo->query('DESCRIBE notifications')->fetchAll(), 'Field');
    
    $notifications = [
        ['Stock Baixo', 'Leite Mimosa abaixo do nível mínimo (5 unidades)', 'warning'],
        ['Encomenda Recebida', 'Encomenda #23 da FrutaFresca SA foi entregue', 'success'],
        ['Novo Cliente', 'Ana Silva registou-se no programa de fidelidade', 'info'],
        ['Promoção Ativa', 'Desconto Semanal ativo até domingo', 'info'],
        ['Caixa Fechada', 'Turno da manhã fechado — Total: €1.234,56', 'success'],
        ['Produto Expirado', 'Iogurte Danone lote 2026-04 expirado', 'danger'],
        ['Meta Atingida', 'Vendas do mês superaram a meta em 12%', 'success'],
    ];
    
    foreach ($notifications as $n) {
        try {
            $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (1,?,?,?,0,?)')->execute([$n[0], $n[1], $n[2], rand_date('2026-04-01', '2026-04-17')]);
        } catch(Exception $e) { echo "  Notif error: " . $e->getMessage() . PHP_EOL; }
    }
    echo "✓ Notificações criadas" . PHP_EOL;
}

// ============================================================
// FINAL COUNTS
// ============================================================
echo PHP_EOL . "=== RESULTADO FINAL ===" . PHP_EOL;
$tables = ['stores', 'products', 'employees', 'customers', 'suppliers', 'sales', 'sale_items', 'orders', 'order_items', 'vacation_requests', 'payroll', 'shifts', 'promotions', 'notifications'];
foreach ($tables as $t) {
    $c = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    echo str_pad($t, 25) . ": $c" . PHP_EOL;
}

$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
echo PHP_EOL . "✅ Concluído!" . PHP_EOL;
