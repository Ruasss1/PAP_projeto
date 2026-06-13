<?php
/**
 * FUNÇÕES DO SISTEMA DE CAIXA (PDV)
 * Ficheiro: caixa/functions.php
 * 
 * Operações de ponto de venda:
 * - Pesquisa de produtos por nome/código
 * - Gestão de turnos de caixa
 * - Processamento de vendas e pagamentos
 * - Vendas suspensas
 */

require_once __DIR__ . '/config.php';

/**
 * Pesquisa produtos para o PDV
 * Procura por código de barras, nome ou categoria
 * 
 * @param string $search_term Termo de pesquisa
 * @param int $limit Limite de resultados
 * @return array Lista de produtos encontrados
 */
function pdv_search_products($search_term, $limit = 50, $category = '') {
    $pdo = db_connect();
    
    // Obter loja atual
    $current_store_id = get_current_store_id();
    
    $where = "p.active = 1 AND (p.store_id = :store_id OR p.store_id IS NULL)";
    
    // Category filter
    if (!empty($category)) {
        $where .= " AND p.category = :category";
    }
    
    // Search filter
    if (!empty(trim($search_term))) {
        $where .= " AND (p.barcode LIKE :search OR p.name LIKE :search OR p.category LIKE :search)";
    }
    
    $sql = "SELECT p.id, p.name, p.category, p.sell_price, p.stock, p.barcode
            FROM products p
            WHERE {$where}
            ORDER BY p.name
            LIMIT :limit";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':store_id', $current_store_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    if (!empty($category)) {
        $stmt->bindValue(':category', $category, PDO::PARAM_STR);
    }
    if (!empty(trim($search_term))) {
        $search_param = "%" . trim($search_term) . "%";
        $stmt->bindValue(':search', $search_param, PDO::PARAM_STR);
    }
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtém produto por código de barras (leitura com scanner)
 * 
 * @param string $barcode Código de barras
 * @return array|false Dados do produto ou false
 */
function pdv_get_product_by_barcode($barcode) {
        $pdo = db_connect();
        
        // Obter loja atual
        $current_store_id = get_current_store_id();
    
        $sql = "SELECT p.id, p.name, p.category, p.sell_price, p.stock, p.barcode
                        FROM products p
                        WHERE p.barcode = :barcode
                            AND p.active = 1
                        LIMIT 1";
    
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':barcode' => $barcode]);
    
        return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Abre um novo turno de caixa
 * 
 * @param int $user_id ID do utilizador
 * @param float $opening_balance Saldo inicial
 * @param string|null $notes Observações
 * @return array Resultado com success, shift_id e shift_number
 */
function pdv_open_shift($user_id, $opening_balance, $notes = null) {
    $pdo = db_connect();
    
    // Verificar se já existe turno aberto para este utilizador
    $stmt = $pdo->prepare("SELECT id FROM cash_register_shifts 
                          WHERE user_id = :user_id AND status = 'open'");
    $stmt->execute([':user_id' => $user_id]);
    
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Já existe um turno aberto'];
    }
    
    // Gerar número único do turno
    $shift_number = 'SHIFT-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    
    $sql = "INSERT INTO cash_register_shifts 
            (shift_number, user_id, opening_balance, notes, status)
            VALUES (:shift_number, :user_id, :opening_balance, :notes, 'open')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':shift_number' => $shift_number,
        ':user_id' => $user_id,
        ':opening_balance' => $opening_balance,
        ':notes' => $notes
    ]);
    
    return [
        'success' => true,
        'shift_id' => $pdo->lastInsertId(),
        'shift_number' => $shift_number
    ];
}

/**
 * Obtém turno aberto do utilizador
 * 
 * @param int $user_id ID do utilizador
 * @return array|false Dados do turno ou false
 */
function pdv_get_open_shift($user_id) {
    $pdo = db_connect();
    
    $stmt = $pdo->prepare("SELECT * FROM cash_register_shifts 
                          WHERE user_id = :user_id AND status = 'open'
                          ORDER BY opened_at DESC LIMIT 1");
    $stmt->execute([':user_id' => $user_id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Suspender venda
 */
function pdv_suspend_sale($user_id, $items, $customer_id = null, $notes = null) {
    $pdo = db_connect();
    
    $suspension_code = 'SUSP-' . strtoupper(substr(md5(uniqid()), 0, 8));
    $expires_at = date('Y-m-d H:i:s', strtotime('+' . PDV_SUSPENDED_SALE_EXPIRY_HOURS . ' hours'));
    
    $total_amount = 0;
    foreach ($items as $item) {
        $total_amount += $item['subtotal'];
    }
    
    $sql = "INSERT INTO suspended_sales 
            (suspension_code, user_id, customer_id, items_json, total_amount, notes, expires_at)
            VALUES (:code, :user_id, :customer_id, :items_json, :total, :notes, :expires_at)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':code' => $suspension_code,
        ':user_id' => $user_id,
        ':customer_id' => $customer_id,
        ':items_json' => json_encode($items),
        ':total' => $total_amount,
        ':notes' => $notes,
        ':expires_at' => $expires_at
    ]);
    
    return [
        'success' => true,
        'suspension_code' => $suspension_code,
        'expires_at' => $expires_at
    ];
}

/**
 * Retomar venda suspensa
 */
function pdv_resume_sale($suspension_code) {
    $pdo = db_connect();
    
    $stmt = $pdo->prepare("SELECT * FROM suspended_sales 
                          WHERE suspension_code = :code 
                          AND status = 'suspended'
                          AND expires_at > NOW()");
    $stmt->execute([':code' => $suspension_code]);
    
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sale) {
        return ['success' => false, 'message' => 'Venda não encontrada ou expirada'];
    }
    
    // Marcar como retomada
    $stmt = $pdo->prepare("UPDATE suspended_sales SET status = 'resumed' WHERE id = :id");
    $stmt->execute([':id' => $sale['id']]);
    
    return [
        'success' => true,
        'items' => json_decode($sale['items_json'], true),
        'customer_id' => $sale['customer_id'],
        'notes' => $sale['notes']
    ];
}

/**
 * Listar vendas suspensas
 */
function pdv_list_suspended_sales($user_id = null) {
    $pdo = db_connect();
    
    $sql = "SELECT ss.*, u.name as user_name, c.name as customer_name
            FROM suspended_sales ss
            LEFT JOIN users u ON ss.user_id = u.id
            LEFT JOIN customers c ON ss.customer_id = c.id
            WHERE ss.status = 'suspended'
            AND ss.expires_at > NOW()";
    
    if ($user_id) {
        $sql .= " AND ss.user_id = :user_id";
    }
    
    $sql .= " ORDER BY ss.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    if ($user_id) {
        $stmt->execute([':user_id' => $user_id]);
    } else {
        $stmt->execute();
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Validar cupom
 */
function pdv_validate_coupon($code, $purchase_amount) {
    $pdo = db_connect();
    
    $stmt = $pdo->prepare("SELECT * FROM coupons 
                          WHERE code = :code 
                          AND status = 'active'
                          AND valid_from <= NOW()
                          AND (valid_until IS NULL OR valid_until >= NOW())");
    $stmt->execute([':code' => $code]);
    
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coupon) {
        return ['success' => false, 'message' => 'Cupom inválido ou expirado'];
    }
    
    if ($coupon['max_uses'] && $coupon['uses_count'] >= $coupon['max_uses']) {
        return ['success' => false, 'message' => 'Cupom já atingiu o limite de usos'];
    }
    
    if ($purchase_amount < $coupon['min_purchase']) {
        return [
            'success' => false, 
            'message' => 'Compra mínima de €' . number_format($coupon['min_purchase'], 2)
        ];
    }
    
    // Calcular desconto
    $discount = 0;
    if ($coupon['type'] == 'percentage') {
        $discount = ($purchase_amount * $coupon['value']) / 100;
    } else if ($coupon['type'] == 'fixed') {
        $discount = $coupon['value'];
    }
    
    return [
        'success' => true,
        'coupon' => $coupon,
        'discount' => $discount
    ];
}

/**
 * Processar venda completa
 */
function pdv_auto_create_low_stock_orders(PDO $pdo, array $product_ids): array {
    $product_ids = array_values(array_unique(array_filter(array_map('intval', $product_ids), fn($v) => $v > 0)));
    if (empty($product_ids)) {
        return ['orders_created' => 0, 'items_created' => 0, 'products' => []];
    }

    $in = implode(',', array_fill(0, count($product_ids), '?'));

    // Produtos que ficaram abaixo do nível definido e têm fornecedor
    $stmt = $pdo->prepare("SELECT id, name, stock, min_stock, reorder_qty, cost_price, supplier_id
                          FROM products
                          WHERE id IN ($in)
                            AND active = 1
                            AND supplier_id IS NOT NULL
                            AND stock <= min_stock");
    $stmt->execute($product_ids);
    $low_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($low_products)) {
        return ['orders_created' => 0, 'items_created' => 0, 'products' => []];
    }

    // Evitar duplicar produtos que já estejam em encomendas abertas
    $low_ids = array_values(array_map(fn($p) => (int)$p['id'], $low_products));
    $in_low = implode(',', array_fill(0, count($low_ids), '?'));
    $open_q = $pdo->prepare("SELECT DISTINCT oi.product_id
                            FROM order_items oi
                            JOIN orders o ON o.id = oi.order_id
                            WHERE oi.product_id IN ($in_low)
                              AND o.status = 'pending'");
    $open_q->execute($low_ids);
    $already_open = array_map('intval', $open_q->fetchAll(PDO::FETCH_COLUMN));
    $already_open_map = array_fill_keys($already_open, true);

    $by_supplier = [];
    foreach ($low_products as $p) {
        $pid = (int)$p['id'];
        if (isset($already_open_map[$pid])) {
            continue;
        }
        $supplier_id = (int)$p['supplier_id'];
        if ($supplier_id <= 0) {
            continue;
        }

        $custom_qty = intval($p['reorder_qty'] ?? 0);
        $qty_to_order = $custom_qty > 0 ? $custom_qty : max(((int)$p['min_stock'] * 2) - (int)$p['stock'], 1);
        $cost_price = (float)($p['cost_price'] ?? 0);

        $by_supplier[$supplier_id][] = [
            'product_id' => $pid,
            'name' => $p['name'],
            'qty' => $qty_to_order,
            'cost_price' => $cost_price
        ];
    }

    if (empty($by_supplier)) {
        return ['orders_created' => 0, 'items_created' => 0, 'products' => []];
    }

    $insert_order = $pdo->prepare("INSERT INTO orders (supplier_id, total_cost, status, created_at) VALUES (?, ?, 'pending', NOW())");
    $insert_item  = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, cost_price) VALUES (?, ?, ?, ?)");

    $orders_created = 0;
    $items_created = 0;
    $products_created = [];
    $order_ids = [];

    foreach ($by_supplier as $supplier_id => $items) {
        $total_cost = 0;
        foreach ($items as $it) {
            $total_cost += $it['qty'] * $it['cost_price'];
        }

        $insert_order->execute([(int)$supplier_id, $total_cost]);
        $order_id = (int)$pdo->lastInsertId();
        $order_ids[] = $order_id;
        $orders_created++;

        foreach ($items as $it) {
            $insert_item->execute([$order_id, $it['product_id'], $it['qty'], $it['cost_price']]);
            $items_created++;
            $products_created[] = $it['name'];
        }
    }

    // Notificação no centro de notificações (se tabela existir)
    try {
        if ($orders_created > 0) {
            $title = 'Encomenda automática criada';
            $msg = "Foram criadas $orders_created encomenda(s) automática(s) para $items_created produto(s) com stock baixo.";
            $pdo->prepare("INSERT INTO notifications (type, title, message, link, icon, priority) VALUES ('Stock', ?, ?, '/modules/encomendas.php', '📦', 'Alta')")
                ->execute([$title, $msg]);
        }
    } catch (\Throwable $__) {
        // Sistema pode não ter tabela notifications em todos os ambientes
    }

    return [
        'orders_created' => $orders_created,
        'items_created' => $items_created,
        'products' => array_values(array_unique($products_created)),
        'order_ids' => $order_ids
    ];
}

function pdv_process_sale($data) {
    $pdo = db_connect();
    
    try {
        $pdo->beginTransaction();
        
        // Forçar método de pagamento para 'Dinheiro' enquanto outros métodos estão desativados
        $data['payment_method'] = 'cash';
        $data['payment_details'] = [];

        // Gerar número do recibo
        $receipt_number = 'REC-' . date('Ymd') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
        
        // Calcular totais
        $total_amount = 0;
        foreach ($data['items'] as $item) {
            $total_amount += $item['subtotal'];
        }
        
        $discount_amount = $data['discount_amount'] ?? 0;
        $final_amount = $total_amount - $discount_amount;
        
        // Calcular pontos de fidelidade (1 ponto por 1€)
        $points_earned = floor($final_amount);
        
        // ========================================
        // 1. CRIAR VENDA NA TABELA 'sales' (para dashboard)
        // ========================================
        $payment_method_label = 'Dinheiro'; // Por agora só dinheiro
        $current_store_id = get_current_store_id();
        $stmt = $pdo->prepare("INSERT INTO sales (total, payment_method, sale_date, nif, store_id) 
                              VALUES (:total, :payment_method, NOW(), :nif, :store_id)");
        $stmt->execute([
            ':total' => $final_amount,
            ':payment_method' => $payment_method_label,
            ':nif' => $data['nif'] ?? null,
            ':store_id' => $current_store_id
        ]);
        $sale_id = $pdo->lastInsertId();
        
        // ========================================
        // 2. CRIAR ITENS DA VENDA NA TABELA 'sale_items'
        // ========================================
        $stmt_sale_item = $pdo->prepare("INSERT INTO sale_items 
                                        (sale_id, category, product_id, quantity, price, cost_price) 
                                        VALUES (:sale_id, :category, :product_id, :quantity, :price, :cost_price)");
        
        foreach ($data['items'] as $item) {
            // Obter categoria e custo do produto
            $stmt_prod = $pdo->prepare("SELECT category, cost_price FROM products WHERE id = :id");
            $stmt_prod->execute([':id' => $item['product_id']]);
            $prod_info = $stmt_prod->fetch(PDO::FETCH_ASSOC);
            
            $stmt_sale_item->execute([
                ':sale_id' => $sale_id,
                ':category' => $prod_info['category'] ?? 'Outros',
                ':product_id' => $item['product_id'],
                ':quantity' => $item['quantity'],
                ':price' => $item['unit_price'],
                ':cost_price' => $prod_info['cost_price'] ?? 0
            ]);
        }
        
        // ========================================
        // 3. CRIAR RECIBO NA TABELA 'receipts' (ligado à venda)
        // ========================================
        $sql = "INSERT INTO receipts 
            (sale_id, receipt_number, user_id, customer_id, subtotal, total, total_amount, 
            discount_amount, final_amount, payment_method, payment_details,
            loyalty_points_earned, loyalty_points_redeemed, notes, nif, completed_at, store_id)
            VALUES (:sale_id, :receipt_number, :user_id, :customer_id, :subtotal, :total, :total_amount,
            :discount_amount, :final_amount, :payment_method, :payment_details,
            :points_earned, :points_redeemed, :notes, :nif, NOW(), :store_id)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':sale_id' => $sale_id,
            ':receipt_number' => $receipt_number,
            ':user_id' => $data['user_id'],
            ':customer_id' => $data['customer_id'] ?? null,
            ':subtotal' => $total_amount,
            ':total' => $total_amount,
            ':total_amount' => $total_amount,
            ':discount_amount' => $discount_amount,
            ':final_amount' => $final_amount,
            ':payment_method' => $data['payment_method'],
            ':payment_details' => json_encode($data['payment_details'] ?? []),
            ':points_earned' => $points_earned,
            ':points_redeemed' => $data['points_redeemed'] ?? 0,
            ':notes' => $data['notes'] ?? null,
            ':nif' => $data['nif'] ?? null,
            ':store_id' => $current_store_id
        ]);
        
        $receipt_id = $pdo->lastInsertId();
        
        // ========================================
        // 4. CRIAR ITENS DO RECIBO E BAIXAR STOCK
        // ========================================
        $sql_item = "INSERT INTO receipt_items 
                    (receipt_id, product_id, product_name, product_sku, quantity,
                    unit_price, discount_percent, discount_amount, subtotal,
                    is_weighted, weight_kg)
                    VALUES (:receipt_id, :product_id, :product_name, :product_sku,
                    :quantity, :unit_price, :discount_percent, :discount_amount,
                    :subtotal, :is_weighted, :weight_kg)";
        
        $stmt_item = $pdo->prepare($sql_item);
        
        $sold_product_ids = [];
        foreach ($data['items'] as $item) {
            $stmt_item->execute([
                ':receipt_id' => $receipt_id,
                ':product_id' => $item['product_id'],
                ':product_name' => $item['product_name'],
                ':product_sku' => $item['product_sku'],
                ':quantity' => $item['quantity'],
                ':unit_price' => $item['unit_price'],
                ':discount_percent' => $item['discount_percent'] ?? 0,
                ':discount_amount' => $item['discount_amount'] ?? 0,
                ':subtotal' => $item['subtotal'],
                ':is_weighted' => $item['is_weighted'] ?? 0,
                ':weight_kg' => $item['weight_kg'] ?? null
            ]);
            
            // BAIXAR STOCK AUTOMATICAMENTE (alinhado com schema: products.stock)
            $stmt_stock = $pdo->prepare("UPDATE products SET stock = stock - :qty 
                                        WHERE id = :product_id");
            $stmt_stock->execute([
                ':qty' => $item['quantity'],
                ':product_id' => $item['product_id']
            ]);

            $sold_product_ids[] = (int)$item['product_id'];
        }

        // 4.1. Encomenda automática para produtos abaixo do stock mínimo
        $auto_orders = pdv_auto_create_low_stock_orders($pdo, $sold_product_ids);
        
        // ========================================
        // 5. REGISTAR TRANSAÇÃO FINANCEIRA
        // ========================================
        $stmt = $pdo->prepare("INSERT INTO transactions (type, amount, reference_type, reference_id, description, created_at) 
                              VALUES ('sale', :amount, 'sale', :sale_id, :description, NOW())");
        $stmt->execute([
            ':amount' => $final_amount,
            ':sale_id' => $sale_id,
            ':description' => "Venda PDV #{$receipt_number} - " . count($data['items']) . " itens"
        ]);
        
        // Atualizar pontos do cliente
        if ($data['customer_id']) {
            $stmt = $pdo->prepare("UPDATE customers 
                                  SET loyalty_points = loyalty_points + :earned - :redeemed
                                  WHERE id = :customer_id");
            $stmt->execute([
                ':earned' => $points_earned,
                ':redeemed' => $data['points_redeemed'] ?? 0,
                ':customer_id' => $data['customer_id']
            ]);
        }
        
        // Registrar uso de cupom
        if (isset($data['coupon_id'])) {
            $stmt = $pdo->prepare("INSERT INTO coupon_usage 
                                  (coupon_id, receipt_id, customer_id, discount_applied)
                                  VALUES (:coupon_id, :receipt_id, :customer_id, :discount)");
            $stmt->execute([
                ':coupon_id' => $data['coupon_id'],
                ':receipt_id' => $receipt_id,
                ':customer_id' => $data['customer_id'] ?? null,
                ':discount' => $discount_amount
            ]);
            
            $stmt = $pdo->prepare("UPDATE coupons SET uses_count = uses_count + 1 
                                  WHERE id = :id");
            $stmt->execute([':id' => $data['coupon_id']]);
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'sale_id' => $sale_id,
            'receipt_id' => $receipt_id,
            'receipt_number' => $receipt_number,
            'final_amount' => $final_amount,
            'points_earned' => $points_earned,
            'auto_orders' => $auto_orders
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Fechar turno de caixa
 */
function pdv_close_shift($shift_id, $closing_balance, $notes = null) {
    $pdo = db_connect();
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT * FROM cash_register_shifts WHERE id = :id");
        $stmt->execute([':id' => $shift_id]);
        $shift = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$shift) {
            throw new Exception('Turno não encontrado');
        }
        
        // Calcular vendas do turno
        $stmt = $pdo->prepare("SELECT 
                              SUM(final_amount) as total_sales,
                              SUM(CASE WHEN payment_method = 'cash' THEN final_amount ELSE 0 END) as total_cash,
                              SUM(CASE WHEN payment_method IN ('debit_card', 'credit_card') THEN final_amount ELSE 0 END) as total_card,
                              SUM(CASE WHEN payment_method NOT IN ('cash', 'debit_card', 'credit_card') THEN final_amount ELSE 0 END) as total_other
                              FROM receipts
                              WHERE user_id = :user_id
                              AND created_at >= :opened_at");
        $stmt->execute([
            ':user_id' => $shift['user_id'],
            ':opened_at' => $shift['opened_at']
        ]);
        $sales = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $expected_balance = $shift['opening_balance'] + ($sales['total_cash'] ?? 0);
        $difference = $closing_balance - $expected_balance;
        
        // Atualizar turno
        $stmt = $pdo->prepare("UPDATE cash_register_shifts SET
                              closing_balance = :closing_balance,
                              expected_balance = :expected_balance,
                              difference = :difference,
                              total_sales = :total_sales,
                              total_cash = :total_cash,
                              total_card = :total_card,
                              total_other = :total_other,
                              notes = :notes,
                              closed_at = NOW(),
                              status = 'closed'
                              WHERE id = :id");
        $stmt->execute([
            ':closing_balance' => $closing_balance,
            ':expected_balance' => $expected_balance,
            ':difference' => $difference,
            ':total_sales' => $sales['total_sales'] ?? 0,
            ':total_cash' => $sales['total_cash'] ?? 0,
            ':total_card' => $sales['total_card'] ?? 0,
            ':total_other' => $sales['total_other'] ?? 0,
            ':notes' => $notes,
            ':id' => $shift_id
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'expected_balance' => $expected_balance,
            'closing_balance' => $closing_balance,
            'difference' => $difference,
            'total_sales' => $sales['total_sales'] ?? 0
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

?>
