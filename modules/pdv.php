<?php
/**
 * Módulo PDV (Ponto de Venda)
 * Sistema completo de caixa com múltiplos métodos de pagamento
 */

// Ensure database connection is available
if (!function_exists('db_connect')) {
    require_once __DIR__ . '/../includes/functions.php';
}

// Só define funções se ainda não foram definidas (evita conflito com CAIXA/functions.php)
if (!function_exists('pdv_search_products')) {

/**
 * Buscar produtos para o PDV
 * Suporta busca por código de barras, nome, SKU, categoria
 */
function pdv_search_products($search_term, $limit = 20) {
    $pdo = db_connect();
    
    $sql = "SELECT p.*, p.sell_price AS price, p.category as category_name,
            p.stock as stock_quantity
            FROM products p
            WHERE p.active = 1
            AND (p.barcode LIKE :search 
                OR p.name LIKE :search 
                OR p.category LIKE :search)
            ORDER BY p.name
            LIMIT :limit";
    
    $stmt = $pdo->prepare($sql);
    $search_param = "%{$search_term}%";
    $stmt->bindValue(':search', $search_param, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Buscar produto por código de barras
 */
function pdv_get_product_by_barcode($barcode) {
    $pdo = db_connect();
    
    $sql = "SELECT p.*, p.sell_price AS price, p.category as category_name,
            p.stock as stock_quantity
            FROM products p
            WHERE p.barcode = :barcode
            AND p.active = 1
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':barcode' => $barcode]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Iniciar novo turno de caixa
 */
function pdv_open_shift($user_id, $opening_balance, $notes = null) {
    $pdo = db_connect();
    
    // Verificar se já existe turno aberto para este usuário
    $stmt = $pdo->prepare("SELECT id FROM cash_register_shifts 
                          WHERE user_id = :user_id AND status = 'open'");
    $stmt->execute([':user_id' => $user_id]);
    
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Já existe um turno aberto para este usuário'];
    }
    
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
 * Obter turno aberto do usuário
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
 * Registrar movimento de caixa (sangria/reforço)
 */
function pdv_cash_movement($shift_id, $type, $amount, $reason, $user_id) {
    $pdo = db_connect();
    
    $sql = "INSERT INTO cash_movements 
            (shift_id, type, amount, reason, user_id)
            VALUES (:shift_id, :type, :amount, :reason, :user_id)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':shift_id' => $shift_id,
        ':type' => $type, // 'withdrawal' ou 'deposit'
        ':amount' => $amount,
        ':reason' => $reason,
        ':user_id' => $user_id
    ]);
    
    return ['success' => true, 'movement_id' => $pdo->lastInsertId()];
}

/**
 * Suspender venda
 */
function pdv_suspend_sale($user_id, $items, $customer_id = null, $notes = null) {
    $pdo = db_connect();
    
    $suspension_code = 'SUSP-' . strtoupper(substr(md5(uniqid()), 0, 8));
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
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
                          AND valid_until >= NOW()");
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
function pdv_process_sale($data) {
    $pdo = db_connect();
    
    try {
        $pdo->beginTransaction();
        
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
        
        // Criar recibo
        $sql = "INSERT INTO receipts 
            (receipt_number, user_id, customer_id, subtotal, total, total_amount, 
                discount_amount, final_amount, payment_method, payment_details,
                loyalty_points_earned, loyalty_points_redeemed, notes, completed_at)
            VALUES (:receipt_number, :user_id, :customer_id, :subtotal, :total, :total_amount,
                :discount_amount, :final_amount, :payment_method, :payment_details,
                :points_earned, :points_redeemed, :notes, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':receipt_number' => $receipt_number,
            ':user_id' => $data['user_id'],
            ':customer_id' => $data['customer_id'] ?? null,
            ':subtotal' => $total_amount,
            ':total' => $final_amount,
            ':total_amount' => $total_amount,
            ':discount_amount' => $discount_amount,
            ':final_amount' => $final_amount,
            ':payment_method' => $data['payment_method'],
            ':payment_details' => json_encode($data['payment_details'] ?? []),
            ':points_earned' => $points_earned,
            ':points_redeemed' => $data['points_redeemed'] ?? 0,
            ':notes' => $data['notes'] ?? null
        ]);
        
        $receipt_id = $pdo->lastInsertId();
        
        // Inserir itens do recibo
        $sql_item = "INSERT INTO receipt_items 
                    (receipt_id, product_id, product_name, product_sku, quantity,
                    unit_price, discount_percent, discount_amount, subtotal,
                    is_weighted, weight_kg)
                    VALUES (:receipt_id, :product_id, :product_name, :product_sku,
                    :quantity, :unit_price, :discount_percent, :discount_amount,
                    :subtotal, :is_weighted, :weight_kg)";
        
        $stmt_item = $pdo->prepare($sql_item);
        
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
            
            // BAIXAR STOCK
            $stmt_stock = $pdo->prepare("UPDATE products SET stock = stock - :qty 
                                        WHERE id = :product_id");
            $stmt_stock->execute([
                ':qty' => $item['quantity'],
                ':product_id' => $item['product_id']
            ]);
        }
        
        // Registrar pagamentos (para pagamentos mistos)
        if (isset($data['payments']) && is_array($data['payments'])) {
            $sql_payment = "INSERT INTO receipt_payments 
                           (receipt_id, payment_method, amount, reference)
                           VALUES (:receipt_id, :method, :amount, :reference)";
            $stmt_payment = $pdo->prepare($sql_payment);
            
            foreach ($data['payments'] as $payment) {
                $stmt_payment->execute([
                    ':receipt_id' => $receipt_id,
                    ':method' => $payment['method'],
                    ':amount' => $payment['amount'],
                    ':reference' => $payment['reference'] ?? null
                ]);
            }
        }
        
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
            
            // Incrementar contador de usos do cupom
            $stmt = $pdo->prepare("UPDATE coupons SET uses_count = uses_count + 1 
                                  WHERE id = :id");
            $stmt->execute([':id' => $data['coupon_id']]);
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'receipt_id' => $receipt_id,
            'receipt_number' => $receipt_number,
            'final_amount' => $final_amount,
            'points_earned' => $points_earned
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Processar devolução
 */
function pdv_process_return($original_receipt_id, $user_id, $items, $reason, $refund_method) {
    $pdo = db_connect();
    
    try {
        $pdo->beginTransaction();
        
        $return_number = 'RET-' . date('Ymd') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
        
        $total_refund = 0;
        foreach ($items as $item) {
            $total_refund += $item['refund_amount'];
        }
        
        // Criar devolução
        $sql = "INSERT INTO returns 
                (return_number, original_receipt_id, user_id, total_refund,
                refund_method, reason)
                VALUES (:return_number, :original_receipt_id, :user_id, :total_refund,
                :refund_method, :reason)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':return_number' => $return_number,
            ':original_receipt_id' => $original_receipt_id,
            ':user_id' => $user_id,
            ':total_refund' => $total_refund,
            ':refund_method' => $refund_method,
            ':reason' => $reason
        ]);
        
        $return_id = $pdo->lastInsertId();
        
        // Inserir itens devolvidos
        $sql_item = "INSERT INTO return_items 
                    (return_id, receipt_item_id, product_id, quantity, refund_amount, condition)
                    VALUES (:return_id, :receipt_item_id, :product_id, :quantity, 
                    :refund_amount, :condition)";
        
        $stmt_item = $pdo->prepare($sql_item);
        
        foreach ($items as $item) {
            $stmt_item->execute([
                ':return_id' => $return_id,
                ':receipt_item_id' => $item['receipt_item_id'],
                ':product_id' => $item['product_id'],
                ':quantity' => $item['quantity'],
                ':refund_amount' => $item['refund_amount'],
                ':condition' => $item['condition'] // 'good', 'damaged', 'defective'
            ]);
            
            // DEVOLVER STOCK (só se condição for boa)
            if ($item['condition'] == 'good') {
                $stmt_stock = $pdo->prepare("UPDATE products SET stock = stock + :qty 
                                            WHERE id = :product_id");
                $stmt_stock->execute([
                    ':qty' => $item['quantity'],
                    ':product_id' => $item['product_id']
                ]);
            }
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'return_id' => $return_id,
            'return_number' => $return_number,
            'total_refund' => $total_refund
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
        
        // Obter dados do turno
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
        
        // Calcular movimentos de caixa (sangrias e reforços)
        $stmt = $pdo->prepare("SELECT 
                              SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END) as withdrawals,
                              SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END) as deposits
                              FROM cash_movements
                              WHERE shift_id = :shift_id");
        $stmt->execute([':shift_id' => $shift_id]);
        $movements = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calcular saldo esperado
        $expected_balance = $shift['opening_balance'] 
                          + ($sales['total_cash'] ?? 0)
                          + ($movements['deposits'] ?? 0)
                          - ($movements['withdrawals'] ?? 0);
        
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

/**
 * Obter recibo completo
 */
function pdv_get_receipt($receipt_id) {
    $pdo = db_connect();
    
    // Obter dados do recibo
    $stmt = $pdo->prepare("SELECT r.*, u.name as cashier_name, c.name as customer_name
                          FROM receipts r
                          LEFT JOIN users u ON r.user_id = u.id
                          LEFT JOIN customers c ON r.customer_id = c.id
                          WHERE r.id = :id");
    $stmt->execute([':id' => $receipt_id]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$receipt) {
        return null;
    }
    
    // Obter itens
    $stmt = $pdo->prepare("SELECT * FROM receipt_items WHERE receipt_id = :id");
    $stmt->execute([':id' => $receipt_id]);
    $receipt['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obter pagamentos (se houver múltiplos)
    $stmt = $pdo->prepare("SELECT * FROM receipt_payments WHERE receipt_id = :id");
    $stmt->execute([':id' => $receipt_id]);
    $receipt['payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $receipt;
}

} // end if (!function_exists('pdv_search_products'))

?>
