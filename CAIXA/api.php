<?php
/**
 * API do Sistema de Caixa (PDV)
 * Endpoints para operações assíncronas
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? null;

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'Ação não especificada']);
    exit;
}

switch ($action) {
    case 'search_product':
        $search = $_POST['search'] ?? '';
        $category = $_POST['category'] ?? '';
        $products = pdv_search_products($search, 50, $category);
        echo json_encode(['success' => true, 'products' => $products]);
        break;
    
    case 'get_product_barcode':
        $barcode = $_POST['barcode'] ?? '';
        $product = pdv_get_product_by_barcode($barcode);
        
        if ($product) {
            echo json_encode(['success' => true, 'product' => $product]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
        }
        break;
    
    case 'search_customer':
        $search = $_POST['search'] ?? '';
        $pdo = db_connect();
        
        $sql = "SELECT * FROM customers 
                WHERE name LIKE :search 
                OR nif LIKE :search 
                OR phone LIKE :search
                LIMIT 10";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':search' => "%{$search}%"]);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'customers' => $customers]);
        break;
    
    case 'validate_coupon':
        $code = $_POST['code'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        
        $result = pdv_validate_coupon($code, $amount);
        echo json_encode($result);
        break;
    
    case 'suspend_sale':
        $user_id = $_SESSION['user_id'];
        $items = json_decode($_POST['items'] ?? '[]', true);
        $customer_id = $_POST['customer_id'] ?? null;
        $notes = $_POST['notes'] ?? null;
        
        $result = pdv_suspend_sale($user_id, $items, $customer_id, $notes);
        echo json_encode($result);
        break;
    
    case 'resume_sale':
        $code = $_POST['code'] ?? '';
        $result = pdv_resume_sale($code);
        echo json_encode($result);
        break;
    
    case 'list_suspended':
        $user_id = $_SESSION['user_id'];
        $sales = pdv_list_suspended_sales($user_id);
        echo json_encode(['success' => true, 'sales' => $sales]);
        break;
    
    case 'process_sale':
        $user_id = $_SESSION['user_id'] ?? 1;
        $items = json_decode($_POST['items'] ?? '[]', true);
        // Converter string vazia para null para evitar erro de tipo inteiro
        $customer_id_raw = $_POST['customer_id'] ?? '';
        $customer_id = ($customer_id_raw !== '' && $customer_id_raw !== null) ? intval($customer_id_raw) : null;
        $discount_amount = floatval($_POST['discount_amount'] ?? 0);
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $payment_details = json_decode($_POST['payment_details'] ?? '{}', true);
        // Converter string vazia para null
        $coupon_id_raw = $_POST['coupon_id'] ?? '';
        $coupon_id = ($coupon_id_raw !== '' && $coupon_id_raw !== null) ? intval($coupon_id_raw) : null;
        $points_redeemed = intval($_POST['points_redeemed'] ?? 0);
        $notes = $_POST['notes'] ?? null;
        if ($notes === '') $notes = null;
        
        $sale_data = [
            'user_id' => $user_id,
            'customer_id' => $customer_id,
            'items' => $items,
            'discount_amount' => $discount_amount,
            'payment_method' => $payment_method,
            'payment_details' => $payment_details,
            'coupon_id' => $coupon_id,
            'points_redeemed' => $points_redeemed,
            'notes' => $notes
        ];
        
        $result = pdv_process_sale($sale_data);
        echo json_encode($result);
        break;
    
    case 'open_shift':
        $user_id = $_SESSION['user_id'];
        $opening_balance = floatval($_POST['opening_balance'] ?? 0);
        $notes = $_POST['notes'] ?? null;
        
        $result = pdv_open_shift($user_id, $opening_balance, $notes);
        echo json_encode($result);
        break;
    
    case 'close_shift':
        $shift_id = intval($_POST['shift_id'] ?? 0);
        $closing_balance = floatval($_POST['closing_balance'] ?? 0);
        $notes = $_POST['notes'] ?? null;
        
        $result = pdv_close_shift($shift_id, $closing_balance, $notes);
        echo json_encode($result);
        break;
    
    case 'get_sale_details':
        $sale_id = intval($_GET['sale_id'] ?? 0);
        
        if (!$sale_id) {
            echo json_encode(['success' => false, 'message' => 'ID da venda não especificado']);
            break;
        }
        
        $pdo = db_connect();
        
        // Buscar dados da venda
        $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
        $stmt->execute([$sale_id]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sale) {
            echo json_encode(['success' => false, 'message' => 'Venda não encontrada']);
            break;
        }
        
        // Buscar itens da venda
        $stmt = $pdo->prepare("
            SELECT si.*, p.name, p.barcode as sku
            FROM sale_items si
            JOIN products p ON si.product_id = p.id
            WHERE si.sale_id = ?
            ORDER BY si.id
        ");
        $stmt->execute([$sale_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'sale' => $sale,
            'items' => $items
        ]);
        break;
    
    case 'get_receipt_print':
        $receipt_id = intval($_GET['receipt_id'] ?? 0);
        
        if (!$receipt_id) {
            echo json_encode(['success' => false, 'message' => 'ID do recibo não especificado']);
            break;
        }
        
        $pdo = db_connect();
        
        // Buscar dados do recibo
        $stmt = $pdo->prepare("SELECT r.*, c.name as customer_name, c.nif as customer_nif FROM receipts r LEFT JOIN customers c ON r.customer_id = c.id WHERE r.id = ?");
        $stmt->execute([$receipt_id]);
        $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$receipt) {
            echo json_encode(['success' => false, 'message' => 'Recibo não encontrado']);
            break;
        }
        
        // Buscar itens
        $stmt = $pdo->prepare("SELECT * FROM receipt_items WHERE receipt_id = ?");
        $stmt->execute([$receipt_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Gerar HTML do recibo térmico
        $html = '<div class="receipt-preview">';
        $html .= '<div class="header">';
        $html .= '<div class="store-name">SUPERMARKET</div>';
        $html .= '<div>Rua do Comércio, 123</div>';
        $html .= '<div>1000-001 Lisboa</div>';
        $html .= '<div>NIF: 999999999</div>';
        $html .= '<div style="margin-top:10px">Tel: 21 123 4567</div>';
        $html .= '</div>';
        
        $html .= '<div style="text-align:center;margin:10px 0;font-weight:bold">';
        $html .= strtoupper($receipt['invoice_type'] ?? 'RECIBO');
        $html .= '</div>';
        
        $html .= '<div class="line"><span>Doc:</span><span>' . htmlspecialchars($receipt['receipt_number']) . '</span></div>';
        $html .= '<div class="line"><span>Data:</span><span>' . date('d/m/Y H:i', strtotime($receipt['created_at'])) . '</span></div>';
        
        if ($receipt['nif']) {
            $html .= '<div class="line"><span>NIF Cliente:</span><span>' . htmlspecialchars($receipt['nif']) . '</span></div>';
        }
        
        $html .= '<div style="border-top:1px dashed #000;margin:10px 0"></div>';
        
        foreach ($items as $item) {
            $subtotal = $item['quantity'] * $item['unit_price'];
            $html .= '<div style="margin-bottom:5px">';
            $html .= '<div>' . htmlspecialchars($item['product_name']) . '</div>';
            $html .= '<div class="line"><span>' . $item['quantity'] . ' x €' . number_format($item['unit_price'], 2) . '</span><span>€' . number_format($subtotal, 2) . '</span></div>';
            $html .= '</div>';
        }
        
        $html .= '<div class="total-line">';
        $html .= '<div class="line"><span>TOTAL:</span><span>€' . number_format($receipt['final_amount'] ?? $receipt['total'], 2) . '</span></div>';
        $html .= '</div>';
        
        $html .= '<div style="margin-top:10px;font-size:11px">';
        $html .= '<div class="line"><span>Pagamento:</span><span>' . htmlspecialchars($receipt['payment_method'] ?? 'Dinheiro') . '</span></div>';
        $html .= '</div>';
        
        // QR Code container
        if ($receipt['qr_code']) {
            $html .= '<div class="qr-container"><canvas id="qrcode"></canvas></div>';
        }
        
        $html .= '<div style="text-align:center;margin-top:15px;font-size:10px">';
        $html .= '<div>Obrigado pela preferência!</div>';
        $html .= '<div style="margin-top:5px">Processado por computador</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        echo json_encode([
            'success' => true,
            'html' => $html,
            'qr_data' => $receipt['qr_code'] ?? null,
            'receipt' => $receipt
        ]);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
        break;
}
?>
