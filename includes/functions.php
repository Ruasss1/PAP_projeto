<?php
// includes/functions.php
// Funções de negócio: vendas, quebras, stocks, cálculos financeiros
require_once __DIR__ . '/../config/database.php';

$pdo = db_connect();      
// Fallback seguro: assegura que `db_connect()` está disponível quando
// `config/database.php` não foi carregado ou não define a função.



if (!function_exists('db_connect')) {
    function db_connect()
    {
        static $pdo = null;
        if ($pdo !== null) return $pdo;

        $host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: '127.0.0.1');
        $db = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'supermercado');
        $user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'root');
        $pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: '');

        $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // Em ambiente de desenvolvimento, mostramos o erro; em produção, registar em log seria preferível.
            die('Database connection failed: ' . $e->getMessage());
        }

        return $pdo;
    }
}

/**
 * Retorna valores permitidos para um campo ENUM (puxa da definição da coluna)
 */
function enum_values($table, $column)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `" . $table . "` LIKE ?");
    $stmt->execute([$column]);
    $row = $stmt->fetch();
    if (!$row) return [];
    $type = $row['Type']; // e.g. enum('A','B')
    if (preg_match("/^enum\((.*)\)$/", $type, $m)) {
        // parse enum('A','B') safely — provide explicit escape param to avoid deprecation warning
        $vals = str_getcsv($m[1], ',', "'", "\\");
        return $vals;
    }
    return [];
}

// ============================================
// PRODUTOS
// ============================================

function get_product($id)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT p.*, s.name as supplier_name FROM products p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function list_products($active_only = true)
{
    $pdo = db_connect();
    // Check if active column exists
    try {
        $sql = 'SELECT p.*, s.name as supplier_name FROM products p LEFT JOIN suppliers s ON p.supplier_id = s.id';
        if ($active_only) {
            $sql .= ' WHERE p.active = 1';
        }
        $sql .= ' ORDER BY p.name';
        return $pdo->query($sql)->fetchAll();
    } catch (PDOException $e) {
        // Fallback for old schema without active/supplier_id
        return $pdo->query('SELECT p.*, "" as supplier_name FROM products p ORDER BY p.name')->fetchAll();
    }
}

function list_low_stock_products($threshold = null)
{
    $pdo = db_connect();
    try {
        if ($threshold === null) {
            $stmt = $pdo->query('SELECT p.*, s.name as supplier_name FROM products p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.active = 1 AND p.stock <= p.min_stock ORDER BY p.stock ASC');
        } else {
            $stmt = $pdo->prepare('SELECT p.*, s.name as supplier_name FROM products p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.active = 1 AND p.stock <= ? ORDER BY p.stock ASC');
            $stmt->execute([$threshold]);
        }
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        // Fallback for old schema
        if ($threshold === null) {
            $threshold = 5;
        }
        $stmt = $pdo->prepare('SELECT p.*, "" as supplier_name FROM products p WHERE p.stock <= ? ORDER BY p.stock ASC');
        $stmt->execute([$threshold]);
        return $stmt->fetchAll();
    }
}

// Alias for low_stock_alerts - returns products with low stock
function low_stock_alerts($threshold = null)
{
    return list_low_stock_products($threshold);
}

function add_product($data)
{
    $pdo = db_connect();
    
    // Check which columns exist in the products table
    $columns = [];
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM products");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $columns = array_flip($existingColumns);
    } catch (PDOException $e) {
        // Fallback to basic columns if we can't check
        $columns = [
            'id' => 1, 'name' => 1, 'category' => 1, 'cost_price' => 1, 
            'sell_price' => 1, 'stock' => 1, 'expiry_date' => 1, 'created_at' => 1
        ];
    }
    
    // Build dynamic INSERT based on existing columns
    $fields = ['name'];
    $placeholders = ['?'];
    $values = [$data['name']];
    
    $optionalFields = [
        'category' => $data['category'] ?? null,
        'brand' => $data['brand'] ?? null,
        'barcode' => $data['barcode'] ?? null,
        'cost_price' => $data['cost_price'] ?? 0,
        'sell_price' => $data['sell_price'] ?? 0,
        'vat' => $data['vat'] ?? 23.00,
        'stock' => $data['stock'] ?? 0,
        'min_stock' => $data['min_stock'] ?? 5,
        'supplier_id' => $data['supplier_id'] ?? null,
        'expiry_date' => $data['expiry_date'] ?? null,
        'active' => 1,
        'created_at' => 'NOW()'
    ];
    
    foreach ($optionalFields as $field => $value) {
        if (isset($columns[$field])) {
            $fields[] = $field;
            $placeholders[] = $value === 'NOW()' ? 'NOW()' : '?';
            if ($value !== 'NOW()') {
                $values[] = $value;
            }
        }
    }
    
    $sql = 'INSERT INTO products (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    
    return $pdo->lastInsertId();
}

function update_product($id, $data)
{
    $pdo = db_connect();
    
    // Get current product for history
    $current = get_product($id);
    if (!$current) return false;
    
    // Track price changes
    if (isset($data['cost_price']) && $data['cost_price'] != $current['cost_price']) {
        record_price_change($id, 'cost', $current['cost_price'], $data['cost_price'], $data['reason'] ?? 'Atualização de preço');
    }
    if (isset($data['sell_price']) && $data['sell_price'] != $current['sell_price']) {
        record_price_change($id, 'sell', $current['sell_price'], $data['sell_price'], $data['reason'] ?? 'Atualização de preço');
    }
    
    // Check which columns exist in the products table
    $columns = [];
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM products");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $columns = array_flip($existingColumns);
    } catch (PDOException $e) {
        $columns = [
            'id' => 1, 'name' => 1, 'category' => 1, 'cost_price' => 1, 
            'sell_price' => 1, 'stock' => 1, 'expiry_date' => 1, 'created_at' => 1
        ];
    }
    
    // Build dynamic UPDATE based on existing columns
    $updates = [];
    $values = [];
    
    $updatableFields = [
        'name' => $data['name'] ?? $current['name'],
        'category' => $data['category'] ?? $current['category'],
        'brand' => $data['brand'] ?? $current['brand'] ?? null,
        'barcode' => $data['barcode'] ?? $current['barcode'] ?? null,
        'cost_price' => $data['cost_price'] ?? $current['cost_price'],
        'sell_price' => $data['sell_price'] ?? $current['sell_price'],
        'vat' => $data['vat'] ?? $current['vat'] ?? 23.00,
        'stock' => $data['stock'] ?? $current['stock'],
        'min_stock' => $data['min_stock'] ?? $current['min_stock'] ?? 5,
        'supplier_id' => $data['supplier_id'] ?? $current['supplier_id'] ?? null,
        'expiry_date' => $data['expiry_date'] ?? $current['expiry_date'] ?? null,
        'active' => $data['active'] ?? $current['active'] ?? 1,
    ];
    
    foreach ($updatableFields as $field => $value) {
        if (isset($columns[$field])) {
            $updates[] = "$field = ?";
            $values[] = $value;
        }
    }
    
    if (empty($updates)) {
        return false;
    }
    
    $values[] = $id;
    $sql = 'UPDATE products SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($values);
}

function delete_product($id)
{
    // Soft delete - just set active = 0
    $pdo = db_connect();
    $stmt = $pdo->prepare('UPDATE products SET active = 0 WHERE id = ?');
    return $stmt->execute([$id]);
}

function get_product_price_history($product_id)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT * FROM price_history WHERE product_id = ? ORDER BY created_at DESC');
    $stmt->execute([$product_id]);
    return $stmt->fetchAll();
}

function record_price_change($product_id, $price_type, $old_price, $new_price, $reason = null, $changed_by = null)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('INSERT INTO price_history (product_id, price_type, old_price, new_price, reason, changed_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    return $stmt->execute([$product_id, $price_type, $old_price, $new_price, $reason, $changed_by]);
}

// ============================================
// FORNECEDORES
// ============================================

function list_suppliers($active_only = true)
{
    $pdo = db_connect();
    $sql = 'SELECT * FROM suppliers';
    if ($active_only) {
        $sql .= ' WHERE active = 1';
    }
    $sql .= ' ORDER BY name';
    return $pdo->query($sql)->fetchAll();
}

function get_supplier($id)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT * FROM suppliers WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function add_supplier($data)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('INSERT INTO suppliers (name, email, phone, address, delivery_days, contact, active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())');
    $stmt->execute([
        $data['name'],
        $data['email'] ?? null,
        $data['phone'] ?? null,
        $data['address'] ?? null,
        $data['delivery_days'] ?? 2,
        $data['contact'] ?? null,
    ]);
    return $pdo->lastInsertId();
}

function update_supplier($id, $data)
{
    $pdo = db_connect();
    $current = get_supplier($id);
    if (!$current) return false;
    
    $stmt = $pdo->prepare('UPDATE suppliers SET name = ?, email = ?, phone = ?, address = ?, delivery_days = ?, contact = ?, active = ? WHERE id = ?');
    return $stmt->execute([
        $data['name'] ?? $current['name'],
        $data['email'] ?? $current['email'],
        $data['phone'] ?? $current['phone'],
        $data['address'] ?? $current['address'],
        $data['delivery_days'] ?? $current['delivery_days'],
        $data['contact'] ?? $current['contact'],
        $data['active'] ?? $current['active'],
        $id
    ]);
}

function delete_supplier($id)
{
    // Soft delete
    $pdo = db_connect();
    $stmt = $pdo->prepare('UPDATE suppliers SET active = 0 WHERE id = ?');
    return $stmt->execute([$id]);
}

// ============================================
// VENDAS
// ============================================

function add_sale($items, $payment_method = 'Dinheiro')
{
    $pdo = db_connect();
    $pdo->beginTransaction();
    try {
        $total = 0;
        $cost_total = 0;
        
        // Create sale
        $stmt = $pdo->prepare('INSERT INTO sales (total, payment_method, sale_date) VALUES (?, ?, NOW())');
        $stmt->execute([0, $payment_method]);
        $sale_id = $pdo->lastInsertId();
        
        foreach ($items as $item) {
            $product = get_product($item['product_id']);
            if (!$product || $product['stock'] < $item['quantity']) {
                throw new Exception('Stock insuficiente para: ' . $product['name']);
            }
            
            $unit_price = $product['sell_price'];
            $line_total = $unit_price * $item['quantity'];
            $line_cost = $product['cost_price'] * $item['quantity'];
            
            $total += $line_total;
            $cost_total += $line_cost;
            
            // Insert sale item with cost price
            $stmt = $pdo->prepare('INSERT INTO sale_items (sale_id, category, product_id, quantity, price, cost_price) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$sale_id, $product['category'], $item['product_id'], $item['quantity'], $unit_price, $product['cost_price']]);
            
            // Update stock
            $stmt = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
            $stmt->execute([$item['quantity'], $item['product_id']]);
            
            // Log stock movement
            $stmt = $pdo->prepare('INSERT INTO stock_movements (product_id, type, qty, previous_stock, new_stock, reference_type, reference_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([
                $item['product_id'], 'sale', -$item['quantity'], 
                $product['stock'], $product['stock'] - $item['quantity'],
                'sale', $sale_id
            ]);
        }
        
        // Update sale total
        $stmt = $pdo->prepare('UPDATE sales SET total = ? WHERE id = ?');
        $stmt->execute([$total, $sale_id]);
        
        // Register transaction
        $stmt = $pdo->prepare('INSERT INTO transactions (type, amount, reference_type, reference_id, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute(['sale', $total, 'sale', $sale_id, "Venda #$sale_id com " . count($items) . " itens"]);
        
        $pdo->commit();
        return $sale_id;
    } catch (Exception $e) {
        $pdo->rollBack();
        return $e->getMessage();
    }
}

function get_sale($id)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT * FROM sales WHERE id = ?');
    $stmt->execute([$id]);
    $sale = $stmt->fetch();
    if ($sale) {
        $stmt = $pdo->prepare('SELECT si.*, p.name as product_name FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?');
        $stmt->execute([$id]);
        $sale['items'] = $stmt->fetchAll();
    }
    return $sale;
}

function list_recent_sales($limit = 20)
{
    $pdo = db_connect();
    $limit = (int)$limit;
    return $pdo->query("SELECT * FROM sales ORDER BY sale_date DESC LIMIT $limit")->fetchAll();
}

// ============================================
// ENCOMENDAS E MENSAGENS
// ============================================

function create_order($supplier_id, $items)
{
    $pdo = db_connect();
    $pdo->beginTransaction();
    try {
        $total_cost = 0;
        
        // Create order header
        $stmt = $pdo->prepare('INSERT INTO orders (supplier_id, status, total_cost, created_at) VALUES (?, "pending", ?, NOW())');
        $stmt->execute([$supplier_id, 0]);
        $order_id = $pdo->lastInsertId();
        
        foreach ($items as $item) {
            $product = get_product($item['product_id']);
            $item_cost = $product['cost_price'] * $item['qty'];
            $total_cost += $item_cost;
            
            $stmt = $pdo->prepare('INSERT INTO orders (supplier_id, product_id, qty, cost_price, status, total_cost, created_at) VALUES (?, ?, ?, ?, "pending", ?, NOW())');
            $stmt->execute([$supplier_id, $item['product_id'], $item['qty'], $item_cost]);
        }
        
        // Update total cost
        $stmt = $pdo->prepare('UPDATE orders SET total_cost = ? WHERE id = ?');
        $stmt->execute([$total_cost, $order_id]);
        
        // Add order message
        add_order_message($order_id, 'created', "Encomenda #$order_id foi criada com " . count($items) . " itens.");
        
        $pdo->commit();
        return $order_id;
    } catch (Exception $e) {
        $pdo->rollBack();
        return $e->getMessage();
    }
}

function get_order($id)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT o.*, s.name as supplier_name FROM orders o JOIN suppliers s ON o.supplier_id = s.id WHERE o.id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if ($order) {
        // Get order items (if using order_items table) or show all items
        $stmt = $pdo->prepare('SELECT o.*, p.name as product_name FROM orders o JOIN products p ON o.product_id = p.id WHERE o.id = ?');
        $stmt->execute([$id]);
        $order['items'] = $stmt->fetchAll();
        
        // Get messages
        $stmt = $pdo->prepare('SELECT * FROM order_messages WHERE order_id = ? ORDER BY created_at ASC');
        $stmt->execute([$id]);
        $order['messages'] = $stmt->fetchAll();
    }
    return $order;
}

function list_orders($status = null)
{
    $pdo = db_connect();

    // Determine an existing column to use for ordering (prefer created_at, otherwise id)
    $orderColumn = 'id';
    try {
        $colsStmt = $pdo->query("SHOW COLUMNS FROM orders");
        $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('created_at', $cols, true)) {
            $orderColumn = 'created_at';
        } elseif (in_array('id', $cols, true)) {
            $orderColumn = 'id';
        }
    } catch (PDOException $e) {
        // If SHOW COLUMNS fails, fallback to id
        $orderColumn = 'id';
    }

    // Whitelist order columns to avoid injection via column name
    $allowedOrderColumns = ['created_at', 'id'];
    if (!in_array($orderColumn, $allowedOrderColumns, true)) {
        $orderColumn = 'id';
    }

    if ($status) {
        $sql = "SELECT o.*, s.name as supplier_name FROM orders o JOIN suppliers s ON o.supplier_id = s.id WHERE o.status = ? ORDER BY o.$orderColumn DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status]);
    } else {
        $sql = "SELECT o.*, s.name as supplier_name FROM orders o JOIN suppliers s ON o.supplier_id = s.id ORDER BY o.$orderColumn DESC";
        $stmt = $pdo->query($sql);
    }

    return $stmt->fetchAll();
}

function update_order_status($order_id, $new_status)
{
    $pdo = db_connect();
    
    $valid_statuses = ['pending', 'processed', 'shipped', 'delivered'];
    if (!in_array($new_status, $valid_statuses)) {
        return false;
    }
    
    $updates = ['status' => $new_status];
    if ($new_status === 'processed') {
        $updates['processed_at'] = 'NOW()';
    }
    if ($new_status === 'delivered') {
        $updates['delivered_at'] = 'NOW()';
    }
    
    $sql = 'UPDATE orders SET status = ?';
    $params = [$new_status];
    
    if ($new_status === 'processed') {
        $sql .= ', processed_at = NOW()';
    }
    if ($new_status === 'delivered') {
        $sql .= ', delivered_at = NOW()';
    }
    $sql .= ' WHERE id = ?';
    $params[] = $order_id;
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result) {
        $messages = [
            'processed' => 'A encomenda foi processada e está pronta para envio.',
            'shipped' => 'A encomenda foi enviada e está a caminho.',
            'delivered' => 'A encomenda foi entregue com sucesso!'
        ];
        add_order_message($order_id, $new_status, $messages[$new_status] ?? "Status alterado para: $new_status");
    }
    
    return $result;
}

function receive_order_items($order_id, $items_received)
{
    $pdo = db_connect();
    $pdo->beginTransaction();
    try {
        $order = get_order($order_id);
        if (!$order) throw new Exception('Encomenda não encontrada');
        
        foreach ($items_received as $item) {
            $product = get_product($item['product_id']);
            $old_stock = $product['stock'];
            $new_qty = $item['qty'];
            
            // Update stock
            $stmt = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
            $stmt->execute([$new_qty, $item['product_id']]);
            
            // Log stock movement
            $stmt = $pdo->prepare('INSERT INTO stock_movements (product_id, type, qty, previous_stock, new_stock, reference_type, reference_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([
                $item['product_id'], 'order', $new_qty,
                $old_stock, $old_stock + $new_qty,
                'order', $order_id
            ]);
            
            // Register transaction
            $cost = $product['cost_price'] * $new_qty;
            $stmt = $pdo->prepare('INSERT INTO transactions (type, amount, reference_type, reference_id, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
            $stmt->execute(['order', -$cost, 'order', $order_id, "Recebimento encomenda #$order_id - {$product['name']} x $new_qty"]);
            
            // Mark as received in orders table (if there's a received column)
            $stmt = $pdo->prepare('UPDATE orders SET received = 1 WHERE id = ?');
            $stmt->execute([$order_id]);
        }
        
        // Add delivery message
        add_order_message($order_id, 'delivered', "Encomenda #$order_id foi entregue. Itens recebidos: " . count($items_received));
        
        // Update status to delivered
        update_order_status($order_id, 'delivered');
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return $e->getMessage();
    }
}

function add_order_message($order_id, $type, $message)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('INSERT INTO order_messages (order_id, type, message, created_at) VALUES (?, ?, ?, NOW())');
    return $stmt->execute([$order_id, $type, $message]);
}

function get_order_messages($order_id)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT * FROM order_messages WHERE order_id = ? ORDER BY created_at ASC');
    $stmt->execute([$order_id]);
    return $stmt->fetchAll();
}

// ============================================
// QUEBRAS / PERDAS
// ============================================

function record_break($product_id, $qty, $reason = 'Quebra/Perda')
{
    $pdo = db_connect();
    $pdo->beginTransaction();
    try {
        $product = get_product($product_id);
        if (!$product || $product['stock'] < $qty) {
            throw new Exception('Stock insuficiente para registar a quebra');
        }

        $cost = $product['cost_price'] * $qty;
        $old_stock = $product['stock'];
        
        // Insert break record
        $stmt = $pdo->prepare('INSERT INTO breaks (product_id, qty, cost, reason, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$product_id, $qty, $cost, $reason]);
        $break_id = $pdo->lastInsertId();
        
        // Update stock
        $stmt = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
        $stmt->execute([$qty, $product_id]);
        
        // Log stock movement
        $stmt = $pdo->prepare('INSERT INTO stock_movements (product_id, type, qty, previous_stock, new_stock, reference_type, reference_id, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([
            $product_id, 'break', -$qty,
            $old_stock, $old_stock - $qty,
            'break', $break_id, $reason
        ]);
        
        // Register transaction
        $stmt = $pdo->prepare('INSERT INTO transactions (type, amount, reference_type, reference_id, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute(['break', -$cost, 'break', $break_id, "Quebra: {$product['name']} x $qty"]);
        
        // Ensure final stock is the expected value to avoid double-decrements from triggers
        $stmt = $pdo->prepare('UPDATE products SET stock = ? WHERE id = ?');
        $stmt->execute([$old_stock - $qty, $product_id]);
        
        $pdo->commit();
        return $break_id;
    } catch (Exception $e) {
        $pdo->rollBack();
        return $e->getMessage();
    }
}

function list_breaks($limit = 50)
{
    $pdo = db_connect();
    $limit = (int)$limit;
    return $pdo->query("SELECT b.*, p.name as product_name FROM breaks b JOIN products p ON b.product_id = p.id ORDER BY b.created_at DESC LIMIT $limit")->fetchAll();
}

// ============================================
// STOCK MOVEMENTS
// ============================================

function get_stock_movements($product_id = null, $limit = 100)
{
    $pdo = db_connect();
    $limit = (int)$limit;
    if ($product_id) {
        $stmt = $pdo->prepare('SELECT sm.*, p.name as product_name FROM stock_movements sm JOIN products p ON sm.product_id = p.id WHERE sm.product_id = ? ORDER BY sm.created_at DESC LIMIT ' . $limit);
        $stmt->execute([$product_id]);
    } else {
        $stmt = $pdo->query("SELECT sm.*, p.name as product_name FROM stock_movements sm JOIN products p ON sm.product_id = p.id ORDER BY sm.created_at DESC LIMIT $limit");
    }
    return $stmt->fetchAll();
}

function adjust_stock($product_id, $qty, $reason = 'Ajuste manual')
{
    $pdo = db_connect();
    $product = get_product($product_id);
    if (!$product) return false;
    
    $old_stock = $product['stock'];
    $new_stock = $old_stock + $qty;
    
    $stmt = $pdo->prepare('UPDATE products SET stock = ? WHERE id = ?');
    $stmt->execute([$new_stock, $product_id]);
    
    $stmt = $pdo->prepare('INSERT INTO stock_movements (product_id, type, qty, previous_stock, new_stock, reference_type, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$product_id, 'adjustment', $qty, $old_stock, $new_stock, 'manual', $reason]);
    
    return true;
}

// ============================================
// ALERTAS
// ============================================

function get_alerts($unread_only = false, $limit = 50)
{
    $pdo = db_connect();
    $limit = (int)$limit; // Ensure limit is an integer
    if ($unread_only) {
        $stmt = $pdo->query("SELECT * FROM alerts WHERE `read` = 0 ORDER BY created_at DESC LIMIT $limit");
    } else {
        $stmt = $pdo->query("SELECT * FROM alerts ORDER BY created_at DESC LIMIT $limit");
    }
    return $stmt->fetchAll();
}

function get_unread_alerts_count()
{
    $pdo = db_connect();
    // Check if alerts table exists and has 'read' column
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM alerts WHERE `read` = 0");
        return $stmt->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        // Table or column doesn't exist yet (migration not run)
        return 0;
    }
}

function mark_alert_read($alert_id)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('UPDATE alerts SET `read` = 1 WHERE id = ?');
    return $stmt->execute([$alert_id]);
}

function mark_all_alerts_read()
{
    $pdo = db_connect();
    return $pdo->query('UPDATE alerts SET `read` = 1');
}

function create_alert($type, $severity, $title, $message, $reference_type = null, $reference_id = null)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('INSERT INTO alerts (alert_type, severity, title, message, reference_type, reference_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    return $stmt->execute([$type, $severity, $title, $message, $reference_type, $reference_id]);
}

// ============================================
// RELATÓRIOS FINANCEIROS
// ============================================

function get_financial_summary()
{
    $pdo = db_connect();

    // Receita total
    $rev = $pdo->query("SELECT COALESCE(SUM(total),0) as total FROM sales")->fetchColumn();

    // Custo das mercadorias vendidas
    $costs = $pdo->query("SELECT COALESCE(SUM(si.quantity * si.cost_price),0) as total_cost FROM sale_items si")->fetchColumn();

    // Salários
    $salaries = $pdo->query('SELECT COALESCE(SUM(salary),0) FROM employees WHERE active = 1')->fetchColumn();

    // Quebras
    $breaks = $pdo->query('SELECT COALESCE(SUM(cost),0) FROM breaks')->fetchColumn();

    // Outros custos fixos
    $fixed = 0;

    $gross_profit = $rev - $costs;
    $net = $rev - ($costs + $salaries + $breaks + $fixed);

    return [
        'revenue' => (float)$rev,
        'cogs' => (float)$costs,
        'salaries' => (float)$salaries,
        'breaks' => (float)$breaks,
        'gross_profit' => (float)$gross_profit,
        'net_profit' => (float)$net,
    ];
}

function get_daily_profit($days = 30)
{
    $pdo = db_connect();
    $days = (int)$days;
    return $pdo->query("SELECT * FROM daily_profit ORDER BY date DESC LIMIT $days")->fetchAll();
}

function get_monthly_profit($months = 12)
{
    $pdo = db_connect();
    $stmt = $pdo->query("SELECT * FROM monthly_profit ORDER BY month DESC LIMIT $months");
    return $stmt->fetchAll();
}

function get_monthly_top_products($month = null, $limit = 5)
{
    $pdo = db_connect();
    $limit = (int)$limit;
    if ($month) {
        $stmt = $pdo->prepare("SELECT * FROM monthly_top_product WHERE month = ? ORDER BY total_qty DESC LIMIT $limit");
        $stmt->execute([$month]);
    } else {
        $stmt = $pdo->query("SELECT * FROM monthly_top_product ORDER BY month DESC, total_qty DESC LIMIT $limit");
    }
    return $stmt->fetchAll();
}

// ============================================
// FUNCIONÁRIOS
// ============================================

function list_employees($active_only = true)
{
    $pdo = db_connect();
    $sql = 'SELECT * FROM employees';
    if ($active_only) {
        $sql .= ' WHERE active = 1';
    }
    $sql .= ' ORDER BY name';
    return $pdo->query($sql)->fetchAll();
}

function get_employee($id)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT * FROM employees WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function add_employee($data)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('INSERT INTO employees (name, role, salary, active, created_at) VALUES (?, ?, ?, 1, NOW())');
    $stmt->execute([
        $data['name'],
        $data['role'],
        $data['salary'] ?? 0,
    ]);
    return $pdo->lastInsertId();
}

function update_employee($id, $data)
{
    $pdo = db_connect();
    $current = get_employee($id);
    if (!$current) return false;
    
    $stmt = $pdo->prepare('UPDATE employees SET name = ?, role = ?, salary = ?, active = ? WHERE id = ?');
    return $stmt->execute([
        $data['name'] ?? $current['name'],
        $data['role'] ?? $current['role'],
        $data['salary'] ?? $current['salary'],
        $data['active'] ?? $current['active'],
        $id
    ]);
}

// ============================================
// AUTO REORDER
// ============================================

function auto_reorder($threshold = null, $reorder_qty = 20)
{
    $pdo = db_connect();
    
    // Get products with low stock
    if ($threshold === null) {
        $products = list_low_stock_products();
    } else {
        $products = list_low_stock_products($threshold);
    }
    
    if (count($products) === 0) return [];

    $created = [];
    try {
        $pdo->beginTransaction();

        foreach ($products as $p) {
            // Skip if no supplier
            if (!$p['supplier_id']) continue;
            
            // Check if there's already a pending order
            $stmt = $pdo->prepare('SELECT id FROM orders WHERE product_id = ? AND status IN ("pending", "processed", "shipped")');
            $stmt->execute([$p['id']]);
            if ($stmt->fetch()) continue;
            
            // Create order
            $stmt = $pdo->prepare('INSERT INTO orders (supplier_id, product_id, qty, cost_price, status, created_at) VALUES (?, ?, ?, ?, "pending", NOW())');
            $stmt->execute([$p['supplier_id'], $p['id'], $reorder_qty, $p['cost_price']]);
            $order_id = $pdo->lastInsertId();
            
            $created[] = ['order_id' => $order_id, 'product_id' => $p['id'], 'qty' => $reorder_qty, 'product_name' => $p['name']];
            
            // Add message
            add_order_message($order_id, 'created', "Encomenda automática gerada para {$p['name']} (stock: {$p['stock']})");
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
    }

    return $created;
}

