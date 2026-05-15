<?php
session_start();
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../modules/pdv.php';

// Verificar autenticação (substituiu check_login que não existia)
if (!$auth->is_authenticated()) {
    header('Location: /login.php');
    exit;
}

$pdo = db_connect();
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Verificar/criar turno aberto
$current_shift = pdv_get_open_shift($user_id);
$shift_open = !empty($current_shift);

// Processar ações
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'search_product':
            $search = $_POST['search'] ?? '';
            $products = pdv_search_products($search, 10);
            echo json_encode(['success' => true, 'products' => $products]);
            exit;
            
        case 'get_product_barcode':
            $barcode = $_POST['barcode'] ?? '';
            $product = pdv_get_product_by_barcode($barcode);
            if ($product) {
                echo json_encode(['success' => true, 'product' => $product]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
            }
            exit;
            
        case 'search_customer':
            $search = $_POST['search'] ?? '';
            $customers = search_customers($search);
            echo json_encode(['success' => true, 'customers' => $customers]);
            exit;
            
        case 'validate_coupon':
            $code = $_POST['code'] ?? '';
            $amount = floatval($_POST['amount'] ?? 0);
            $result = pdv_validate_coupon($code, $amount);
            echo json_encode($result);
            exit;
            
        case 'suspend_sale':
            $items = json_decode($_POST['items'] ?? '[]', true);
            $customer_id = $_POST['customer_id'] ?? null;
            $notes = $_POST['notes'] ?? null;
            $result = pdv_suspend_sale($user_id, $items, $customer_id, $notes);
            echo json_encode($result);
            exit;
            
        case 'resume_sale':
            $code = $_POST['code'] ?? '';
            $result = pdv_resume_sale($code);
            echo json_encode($result);
            exit;
            
        case 'process_sale':
            $data = [
                'user_id' => $user_id,
                'customer_id' => $_POST['customer_id'] ?? null,
                'items' => json_decode($_POST['items'], true),
                'discount_amount' => floatval($_POST['discount_amount'] ?? 0),
                'payment_method' => $_POST['payment_method'],
                'payment_details' => json_decode($_POST['payment_details'] ?? '{}', true),
                'payments' => json_decode($_POST['payments'] ?? '[]', true),
                'points_redeemed' => intval($_POST['points_redeemed'] ?? 0),
                'coupon_id' => $_POST['coupon_id'] ?? null,
                'notes' => $_POST['notes'] ?? null
            ];
            
            $result = pdv_process_sale($data);
            echo json_encode($result);
            exit;
            
        case 'open_shift':
            $opening_balance = floatval($_POST['opening_balance']);
            $notes = $_POST['notes'] ?? null;
            $result = pdv_open_shift($user_id, $opening_balance, $notes);
            echo json_encode($result);
            exit;
            
        case 'close_shift':
            $shift_id = intval($_POST['shift_id']);
            $closing_balance = floatval($_POST['closing_balance']);
            $notes = $_POST['notes'] ?? null;
            $result = pdv_close_shift($shift_id, $closing_balance, $notes);
            echo json_encode($result);
            exit;
    }
}

// Listar vendas suspensas
$suspended_sales = pdv_list_suspended_sales($user_id);

?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDV - Ponto de Venda</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0a0a0a;
            color: #fff;
            overflow: hidden;
        }
        
        .pdv-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            height: 100vh;
        }
        
        /* Lado Esquerdo - Produtos e Busca */
        .left-panel {
            display: flex;
            flex-direction: column;
            background: #1a1a1a;
            border-right: 2px solid #333;
        }
        
        .pdv-header {
            background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .pdv-header h1 {
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .cashier-info {
            text-align: right;
            font-size: 14px;
        }
        
        .search-section {
            padding: 20px;
            background: #222;
            border-bottom: 1px solid #333;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 15px 50px 15px 20px;
            font-size: 16px;
            border: 2px solid #444;
            border-radius: 8px;
            background: #2a2a2a;
            color: #fff;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #00d4ff;
        }
        
        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 24px;
        }
        
        .quick-categories {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .category-btn {
            padding: 8px 16px;
            background: #333;
            border: 1px solid #555;
            border-radius: 20px;
            color: #fff;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
        }
        
        .category-btn:hover {
            background: #00d4ff;
            border-color: #00d4ff;
        }
        
        .products-grid {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            align-content: start;
        }
        
        .product-card {
            background: #2a2a2a;
            border: 2px solid #333;
            border-radius: 10px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .product-card:hover {
            border-color: #00d4ff;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 212, 255, 0.3);
        }
        
        .product-emoji {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .product-name {
            font-size: 13px;
            margin-bottom: 5px;
            min-height: 32px;
        }
        
        .product-price {
            font-size: 18px;
            color: #00d4ff;
            font-weight: bold;
        }
        
        .product-stock {
            font-size: 11px;
            color: #888;
            margin-top: 5px;
        }
        
        /* Lado Direito - Carrinho e Pagamento */
        .right-panel {
            display: flex;
            flex-direction: column;
            background: #151515;
        }
        
        .cart-header {
            background: #222;
            padding: 15px 20px;
            border-bottom: 2px solid #333;
        }
        
        .cart-header h2 {
            font-size: 18px;
            color: #00d4ff;
        }
        
        .customer-section {
            padding: 15px 20px;
            background: #1a1a1a;
            border-bottom: 1px solid #333;
        }
        
        .customer-search {
            display: flex;
            gap: 10px;
        }
        
        .customer-search input {
            flex: 1;
            padding: 10px;
            border: 1px solid #444;
            border-radius: 5px;
            background: #2a2a2a;
            color: #fff;
            font-size: 14px;
        }
        
        .customer-selected {
            background: #2a4a2a;
            padding: 10px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }
        
        .customer-selected .points {
            color: #ffaa00;
            font-weight: bold;
        }
        
        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 15px 20px;
        }
        
        .cart-empty {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .cart-empty .icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        .cart-item {
            background: #222;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
        }
        
        .cart-item-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .cart-item-name {
            font-weight: bold;
            font-size: 14px;
        }
        
        .cart-item-remove {
            color: #ff4444;
            cursor: pointer;
            font-size: 18px;
        }
        
        .cart-item-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .qty-btn {
            width: 28px;
            height: 28px;
            border: 1px solid #555;
            background: #333;
            color: #fff;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        
        .qty-btn:hover {
            background: #444;
        }
        
        .qty-input {
            width: 50px;
            padding: 5px;
            text-align: center;
            border: 1px solid #555;
            background: #2a2a2a;
            color: #fff;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .item-subtotal {
            font-size: 16px;
            color: #00d4ff;
            font-weight: bold;
        }
        
        .cart-totals {
            background: #1a1a1a;
            padding: 20px;
            border-top: 2px solid #333;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }
        
        .total-row.final {
            font-size: 24px;
            font-weight: bold;
            color: #00d4ff;
            border-top: 2px solid #333;
            padding-top: 15px;
            margin-top: 10px;
        }
        
        .discount-row {
            color: #ff6b6b;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn {
            padding: 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-suspend {
            background: #ffaa00;
            color: #000;
        }
        
        .btn-suspend:hover {
            background: #ff9900;
        }
        
        .btn-discount {
            background: #ff6b6b;
            color: #fff;
        }
        
        .btn-discount:hover {
            background: #ff5555;
        }
        
        .btn-coupon {
            background: #9b59b6;
            color: #fff;
        }
        
        .btn-coupon:hover {
            background: #8e44ad;
        }
        
        .btn-clear {
            background: #555;
            color: #fff;
        }
        
        .btn-clear:hover {
            background: #666;
        }
        
        .btn-pay {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, #00ff88 0%, #00cc66 100%);
            color: #000;
            font-size: 18px;
            padding: 18px;
            margin-top: 5px;
        }
        
        .btn-pay:hover {
            transform: scale(1.02);
            box-shadow: 0 5px 20px rgba(0, 255, 136, 0.4);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: #1a1a1a;
            border: 2px solid #333;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            font-size: 24px;
            color: #00d4ff;
        }
        
        .modal-close {
            font-size: 30px;
            cursor: pointer;
            color: #888;
        }
        
        .modal-close:hover {
            color: #fff;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        
        .payment-method {
            background: #2a2a2a;
            border: 2px solid #444;
            border-radius: 10px;
            padding: 20px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
        }
        
        .payment-method:hover {
            border-color: #00d4ff;
        }
        
        .payment-method.selected {
            border-color: #00ff88;
            background: #2a3a2a;
        }
        
        .payment-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .payment-name {
            font-size: 14px;
            font-weight: bold;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #aaa;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #444;
            border-radius: 8px;
            background: #2a2a2a;
            color: #fff;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #00d4ff;
        }
        
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #00ff88 0%, #00cc66 100%);
            border: none;
            border-radius: 8px;
            color: #000;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .btn-submit:hover {
            transform: scale(1.02);
        }
        
        /* Shift Management */
        .shift-warning {
            background: #ff6b6b;
            color: #fff;
            padding: 20px;
            text-align: center;
            border-radius: 10px;
            margin: 20px;
        }
        
        .shift-warning h2 {
            margin-bottom: 15px;
        }
        
        /* Weight Input for Granel Products */
        .weight-input-modal {
            background: #2a2a2a;
            border: 2px solid #00d4ff;
            border-radius: 10px;
            padding: 20px;
            margin-top: 15px;
        }
        
        .weight-display {
            font-size: 48px;
            color: #00ff88;
            text-align: center;
            margin: 20px 0;
            font-weight: bold;
        }
        
        .numpad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 20px;
        }
        
        .numpad-btn {
            padding: 20px;
            font-size: 24px;
            background: #333;
            border: 1px solid #555;
            border-radius: 8px;
            color: #fff;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .numpad-btn:hover {
            background: #444;
        }
        
        .numpad-btn:active {
            transform: scale(0.95);
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #1a1a1a;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #444;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        .suspended-sales {
            padding: 15px 20px;
            background: #222;
            border-bottom: 1px solid #333;
        }
        
        .suspended-item {
            background: #2a2a2a;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .suspended-item:hover {
            background: #333;
        }
        
        .suspended-code {
            color: #ffaa00;
            font-weight: bold;
            font-size: 14px;
        }
        
        .suspended-total {
            color: #00d4ff;
            font-size: 16px;
        }
    </style>
</head>
<body>
    
<?php if (!$shift_open): ?>
    <!-- Modal para Abrir Turno -->
    <div class="modal active" id="shiftModal">
        <div class="modal-content">
            <div class="shift-warning">
                <h2> Turno Não Aberto</h2>
                <p>É necessário abrir um turno antes de iniciar vendas</p>
            </div>
            
            <form id="openShiftForm">
                <input type="hidden" name="action" value="open_shift">
                
                <div class="form-group">
                    <label> Fundo de Abertura (€)</label>
                    <input type="number" name="opening_balance" step="0.01" required 
                           placeholder="Ex: 100.00" value="100.00">
                </div>
                
                <div class="form-group">
                    <label> Observações</label>
                    <textarea name="notes" rows="3" 
                              placeholder="Notas opcionais sobre a abertura do turno"></textarea>
                </div>
                
                <button type="submit" class="btn-submit"> Abrir Turno e Começar</button>
            </form>
        </div>
    </div>
<?php else: ?>

    <div class="pdv-container">
        <!-- LADO ESQUERDO: Produtos -->
        <div class="left-panel">
            <div class="pdv-header">
                <h1> Caixa PDV</h1>
                <div class="cashier-info">
                    <div><strong><?= htmlspecialchars($user_name) ?></strong></div>
                    <div style="font-size: 12px; opacity: 0.8;">
                        Turno: <?= htmlspecialchars($current_shift['shift_number']) ?>
                    </div>
                </div>
            </div>
            
            <div class="search-section">
                <div class="search-box">
                    <input type="text" id="productSearch" 
                           placeholder=" Buscar produto (nome, código, código de barras)..."
                           autofocus>
                    <span class="search-icon"></span>
                </div>
                
                <div class="quick-categories">
                    <button class="category-btn" data-category="frutas"> Frutas</button>
                    <button class="category-btn" data-category="legumes"> Legumes</button>
                    <button class="category-btn" data-category="padaria"> Padaria</button>
                    <button class="category-btn" data-category="bebidas"> Bebidas</button>
                    <button class="category-btn" data-category="laticinios"> Laticínios</button>
                    <button class="category-btn" data-category="carnes"> Carnes</button>
                </div>
            </div>
            
            <div class="products-grid" id="productsGrid">
                <!-- Produtos serão carregados aqui via JS -->
                <div class="cart-empty">
                    <div class="icon"></div>
                    <p>Digite para buscar produtos</p>
                </div>
            </div>
        </div>
        
        <!-- LADO DIREITO: Carrinho -->
        <div class="right-panel">
            <div class="cart-header">
                <h2> Carrinho de Compras</h2>
            </div>
            
            <!-- Seção de Cliente -->
            <div class="customer-section">
                <div class="customer-search">
                    <input type="text" id="customerSearch" 
                           placeholder="Buscar cliente (nome, email, NIF)...">
                </div>
                <div id="customerSelected" style="display: none;"></div>
            </div>
            
            <!-- Vendas Suspensas -->
            <?php if (!empty($suspended_sales)): ?>
            <div class="suspended-sales">
                <strong style="color: #ffaa00;"> Vendas Suspensas:</strong>
                <?php foreach ($suspended_sales as $sale): ?>
                <div class="suspended-item" onclick="resumeSale('<?= $sale['suspension_code'] ?>')">
                    <div class="suspended-code"><?= htmlspecialchars($sale['suspension_code']) ?></div>
                    <div class="suspended-total">€<?= number_format($sale['total_amount'], 2) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Itens do Carrinho -->
            <div class="cart-items" id="cartItems">
                <div class="cart-empty">
                    <div class="icon"></div>
                    <p>Carrinho vazio</p>
                    <p style="font-size: 12px; margin-top: 10px;">
                        Adicione produtos escaneando código de barras ou buscando pelo nome
                    </p>
                </div>
            </div>
            
            <!-- Totais -->
            <div class="cart-totals">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span id="subtotal">€0.00</span>
                </div>
                <div class="total-row discount-row">
                    <span>Desconto:</span>
                    <span id="discount">-€0.00</span>
                </div>
                <div class="total-row final">
                    <span>TOTAL:</span>
                    <span id="total">€0.00</span>
                </div>
                
                <div class="action-buttons">
                    <button class="btn btn-suspend" onclick="suspendSale()">
                        ⏸ Suspender
                    </button>
                    <button class="btn btn-discount" onclick="applyDiscount()">
                         Desconto
                    </button>
                    <button class="btn btn-coupon" onclick="applyCoupon()">
                         Cupom
                    </button>
                    <button class="btn btn-clear" onclick="clearCart()">
                         Limpar
                    </button>
                    <button class="btn btn-pay" id="btnPay" onclick="showPaymentModal()" disabled>
                         FINALIZAR PAGAMENTO
                    </button>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<!-- Modal de Pagamento -->
<div class="modal" id="paymentModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3> Pagamento</h3>
            <span class="modal-close" onclick="closePaymentModal()">×</span>
        </div>
        
        <div class="total-row final" style="margin-bottom: 20px;">
            <span>Total a Pagar:</span>
            <span id="paymentTotal">€0.00</span>
        </div>
        
        <h4 style="margin-bottom: 15px;">Selecione o Método de Pagamento:</h4>
        
        <div class="payment-methods">
            <div class="payment-method" onclick="selectPaymentMethod('cash')">
                <div class="payment-icon"></div>
                <div class="payment-name">Dinheiro</div>
            </div>
            <div class="payment-method" onclick="selectPaymentMethod('debit_card')">
                <div class="payment-icon"></div>
                <div class="payment-name">Multibanco</div>
            </div>
            <div class="payment-method" onclick="selectPaymentMethod('credit_card')">
                <div class="payment-icon"></div>
                <div class="payment-name">Cartão Crédito</div>
            </div>
            <div class="payment-method" onclick="selectPaymentMethod('mbway')">
                <div class="payment-icon"></div>
                <div class="payment-name">MB WAY</div>
            </div>
            <div class="payment-method" onclick="selectPaymentMethod('voucher')">
                <div class="payment-icon"></div>
                <div class="payment-name">Vale/Gift Card</div>
            </div>
            <div class="payment-method" onclick="selectPaymentMethod('mixed')">
                <div class="payment-icon"></div>
                <div class="payment-name">Pagamento Misto</div>
            </div>
        </div>
        
        <div id="paymentDetails" style="display: none; margin-top: 20px;">
            <!-- Detalhes específicos do método serão inseridos aqui -->
        </div>
        
        <button class="btn-submit" id="btnConfirmPayment" style="display: none;" 
                onclick="processPayment()">
             Confirmar Pagamento
        </button>
    </div>
</div>

<!-- Modal de Peso (para produtos a granel) -->
<div class="modal" id="weightModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3> Pesagem de Produto</h3>
            <span class="modal-close" onclick="closeWeightModal()">×</span>
        </div>
        
        <div id="weightProductInfo" style="text-align: center; margin-bottom: 20px;">
            <!-- Info do produto -->
        </div>
        
        <div class="weight-display" id="weightDisplay">0.000 kg</div>
        
        <div class="numpad">
            <button class="numpad-btn" onclick="addWeightDigit('1')">1</button>
            <button class="numpad-btn" onclick="addWeightDigit('2')">2</button>
            <button class="numpad-btn" onclick="addWeightDigit('3')">3</button>
            <button class="numpad-btn" onclick="addWeightDigit('4')">4</button>
            <button class="numpad-btn" onclick="addWeightDigit('5')">5</button>
            <button class="numpad-btn" onclick="addWeightDigit('6')">6</button>
            <button class="numpad-btn" onclick="addWeightDigit('7')">7</button>
            <button class="numpad-btn" onclick="addWeightDigit('8')">8</button>
            <button class="numpad-btn" onclick="addWeightDigit('9')">9</button>
            <button class="numpad-btn" onclick="clearWeight()">⌫</button>
            <button class="numpad-btn" onclick="addWeightDigit('0')">0</button>
            <button class="numpad-btn" onclick="addWeightDigit('.')">.</button>
        </div>
        
        <button class="btn-submit" style="margin-top: 20px;" onclick="confirmWeight()">
             Confirmar Peso
        </button>
    </div>
</div>

<script>
// Estado da aplicação
let cart = [];
let currentCustomer = null;
let currentDiscount = 0;
let currentCoupon = null;
let selectedPaymentMethod = null;
let weightProduct = null;
let weightValue = '';

// Carregar produtos iniciais
document.addEventListener('DOMContentLoaded', function() {
    loadInitialProducts();
    
    // Busca de produtos
    let searchTimeout;
    document.getElementById('productSearch').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => searchProducts(e.target.value), 300);
    });
    
    // Busca de clientes
    let customerTimeout;
    document.getElementById('customerSearch').addEventListener('input', function(e) {
        clearTimeout(customerTimeout);
        customerTimeout = setTimeout(() => searchCustomers(e.target.value), 300);
    });
    
    // Categorias rápidas
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            searchProducts(this.dataset.category);
        });
    });
    
    // Suporte para leitor de código de barras
    document.addEventListener('keypress', function(e) {
        if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
            document.getElementById('productSearch').focus();
        }
    });
    
    // Form de abertura de turno
    const openShiftForm = document.getElementById('openShiftForm');
    if (openShiftForm) {
        openShiftForm.addEventListener('submit', function(e) {
            e.preventDefault();
            openShift(new FormData(this));
        });
    }
});

function loadInitialProducts() {
    searchProducts('');
}

function searchProducts(term) {
    const formData = new FormData();
    formData.append('action', 'search_product');
    formData.append('search', term);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            displayProducts(data.products);
        }
    });
}

function displayProducts(products) {
    const grid = document.getElementById('productsGrid');
    
    if (products.length === 0) {
        grid.innerHTML = `
            <div class="cart-empty" style="grid-column: 1 / -1;">
                <div class="icon"></div>
                <p>Nenhum produto encontrado</p>
            </div>
        `;
        return;
    }
    
    grid.innerHTML = products.map(p => `
        <div class="product-card" onclick='addToCart(${JSON.stringify(p)})'>
            <div class="product-emoji">${getProductEmoji(p.category_name)}</div>
            <div class="product-name">${p.name}</div>
            <div class="product-price">€${parseFloat(p.price).toFixed(2)}</div>
            <div class="product-stock">Stock: ${p.stock_quantity}</div>
        </div>
    `).join('');
}

function getProductEmoji(category) {
    const emojis = {
        'Frutas': '',
        'Legumes': '',
        'Padaria': '',
        'Bebidas': '',
        'Laticínios': '',
        'Carnes': '',
        'Mercearia': '',
        'Congelados': '',
        'Higiene': '',
        'Limpeza': ''
    };
    return emojis[category] || '';
}

function addToCart(product) {
    // Verificar se é produto a granel (peso)
    if (product.category_name === 'Frutas' || product.category_name === 'Legumes') {
        showWeightModal(product);
        return;
    }
    
    // Verificar stock
    if (product.stock_quantity <= 0) {
        alert(' Produto sem stock disponível!');
        return;
    }
    
    // Verificar se já existe no carrinho
    const existingIndex = cart.findIndex(item => item.product_id === product.id);
    
    if (existingIndex >= 0) {
        cart[existingIndex].quantity++;
        cart[existingIndex].subtotal = cart[existingIndex].quantity * cart[existingIndex].unit_price;
    } else {
        cart.push({
            product_id: product.id,
            product_name: product.name,
            product_sku: product.sku,
            quantity: 1,
            unit_price: parseFloat(product.price),
            discount_percent: 0,
            discount_amount: 0,
            subtotal: parseFloat(product.price),
            is_weighted: 0,
            weight_kg: null
        });
    }
    
    updateCart();
}

function showWeightModal(product) {
    weightProduct = product;
    weightValue = '';
    document.getElementById('weightProductInfo').innerHTML = `
        <h3>${product.name}</h3>
        <p style="font-size: 18px; color: #00d4ff;">€${parseFloat(product.price).toFixed(2)}/kg</p>
    `;
    document.getElementById('weightDisplay').textContent = '0.000 kg';
    document.getElementById('weightModal').classList.add('active');
}

function addWeightDigit(digit) {
    if (digit === '.' && weightValue.includes('.')) return;
    weightValue += digit;
    updateWeightDisplay();
}

function clearWeight() {
    weightValue = weightValue.slice(0, -1);
    updateWeightDisplay();
}

function updateWeightDisplay() {
    const weight = parseFloat(weightValue) || 0;
    document.getElementById('weightDisplay').textContent = weight.toFixed(3) + ' kg';
}

function confirmWeight() {
    const weight = parseFloat(weightValue) || 0;
    
    if (weight <= 0) {
        alert(' Digite um peso válido!');
        return;
    }
    
    const subtotal = weight * parseFloat(weightProduct.price);
    
    cart.push({
        product_id: weightProduct.id,
        product_name: weightProduct.name,
        product_sku: weightProduct.sku,
        quantity: weight,
        unit_price: parseFloat(weightProduct.price),
        discount_percent: 0,
        discount_amount: 0,
        subtotal: subtotal,
        is_weighted: 1,
        weight_kg: weight
    });
    
    closeWeightModal();
    updateCart();
}

function closeWeightModal() {
    document.getElementById('weightModal').classList.remove('active');
    weightProduct = null;
    weightValue = '';
}

function updateCart() {
    const container = document.getElementById('cartItems');
    
    if (cart.length === 0) {
        container.innerHTML = `
            <div class="cart-empty">
                <div class="icon"></div>
                <p>Carrinho vazio</p>
            </div>
        `;
        document.getElementById('btnPay').disabled = true;
    } else {
        container.innerHTML = cart.map((item, index) => `
            <div class="cart-item">
                <div class="cart-item-header">
                    <div class="cart-item-name">${item.product_name}</div>
                    <div class="cart-item-remove" onclick="removeFromCart(${index})"></div>
                </div>
                <div class="cart-item-details">
                    <div class="quantity-controls">
                        ${item.is_weighted ? 
                            `<span>${item.quantity.toFixed(3)} kg × €${item.unit_price.toFixed(2)}/kg</span>` :
                            `
                            <button class="qty-btn" onclick="decreaseQty(${index})">−</button>
                            <input type="number" class="qty-input" value="${item.quantity}" 
                                   onchange="updateQty(${index}, this.value)" min="1">
                            <button class="qty-btn" onclick="increaseQty(${index})">+</button>
                            `
                        }
                    </div>
                    <div class="item-subtotal">€${item.subtotal.toFixed(2)}</div>
                </div>
            </div>
        `).join('');
        
        document.getElementById('btnPay').disabled = false;
    }
    
    updateTotals();
}

function increaseQty(index) {
    cart[index].quantity++;
    cart[index].subtotal = cart[index].quantity * cart[index].unit_price;
    updateCart();
}

function decreaseQty(index) {
    if (cart[index].quantity > 1) {
        cart[index].quantity--;
        cart[index].subtotal = cart[index].quantity * cart[index].unit_price;
        updateCart();
    }
}

function updateQty(index, value) {
    const qty = parseInt(value);
    if (qty > 0) {
        cart[index].quantity = qty;
        cart[index].subtotal = cart[index].quantity * cart[index].unit_price;
        updateCart();
    }
}

function removeFromCart(index) {
    cart.splice(index, 1);
    updateCart();
}

function clearCart() {
    if (cart.length === 0) return;
    
    if (confirm(' Limpar todo o carrinho?')) {
        cart = [];
        currentDiscount = 0;
        currentCoupon = null;
        updateCart();
    }
}

function updateTotals() {
    const subtotal = cart.reduce((sum, item) => sum + item.subtotal, 0);
    const discount = currentDiscount;
    const total = subtotal - discount;
    
    document.getElementById('subtotal').textContent = '€' + subtotal.toFixed(2);
    document.getElementById('discount').textContent = '-€' + discount.toFixed(2);
    document.getElementById('total').textContent = '€' + total.toFixed(2);
}

function applyDiscount() {
    const percent = prompt(' Digite o percentual de desconto (0-100):');
    if (percent !== null) {
        const p = parseFloat(percent);
        if (p >= 0 && p <= 100) {
            const subtotal = cart.reduce((sum, item) => sum + item.subtotal, 0);
            currentDiscount = (subtotal * p) / 100;
            updateTotals();
        }
    }
}

function applyCoupon() {
    const code = prompt(' Digite o código do cupom:');
    if (!code) return;
    
    const subtotal = cart.reduce((sum, item) => sum + item.subtotal, 0);
    
    const formData = new FormData();
    formData.append('action', 'validate_coupon');
    formData.append('code', code);
    formData.append('amount', subtotal);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            currentCoupon = data.coupon;
            currentDiscount = data.discount;
            updateTotals();
            alert(' Cupom aplicado! Desconto de €' + data.discount.toFixed(2));
        } else {
            alert(' ' + data.message);
        }
    });
}

function suspendSale() {
    if (cart.length === 0) {
        alert(' Carrinho vazio!');
        return;
    }
    
    const notes = prompt(' Motivo da suspensão (opcional):');
    
    const formData = new FormData();
    formData.append('action', 'suspend_sale');
    formData.append('items', JSON.stringify(cart));
    formData.append('customer_id', currentCustomer ? currentCustomer.id : '');
    formData.append('notes', notes || '');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(' Venda suspensa!\n\nCódigo: ' + data.suspension_code);
            cart = [];
            currentDiscount = 0;
            currentCoupon = null;
            updateCart();
            location.reload();
        } else {
            alert(' Erro ao suspender venda');
        }
    });
}

function resumeSale(code) {
    const formData = new FormData();
    formData.append('action', 'resume_sale');
    formData.append('code', code);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            cart = data.items;
            updateCart();
            alert(' Venda retomada!');
            location.reload();
        } else {
            alert(' ' + data.message);
        }
    });
}

function searchCustomers(term) {
    if (term.length < 2) return;
    
    const formData = new FormData();
    formData.append('action', 'search_customer');
    formData.append('search', term);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        // Implementar lista de sugestões...
        console.log('Customers:', data.customers);
    });
}

function showPaymentModal() {
    const total = cart.reduce((sum, item) => sum + item.subtotal, 0) - currentDiscount;
    document.getElementById('paymentTotal').textContent = '€' + total.toFixed(2);
    document.getElementById('paymentModal').classList.add('active');
}

function closePaymentModal() {
    document.getElementById('paymentModal').classList.remove('active');
    selectedPaymentMethod = null;
    document.querySelectorAll('.payment-method').forEach(el => {
        el.classList.remove('selected');
    });
}

function selectPaymentMethod(method) {
    selectedPaymentMethod = method;
    
    document.querySelectorAll('.payment-method').forEach(el => {
        el.classList.remove('selected');
    });
    event.target.closest('.payment-method').classList.add('selected');
    
    const detailsDiv = document.getElementById('paymentDetails');
    const btnConfirm = document.getElementById('btnConfirmPayment');
    
    if (method === 'cash') {
        detailsDiv.innerHTML = `
            <div class="form-group">
                <label> Valor Recebido (€)</label>
                <input type="number" id="cashReceived" step="0.01" placeholder="0.00" autofocus>
            </div>
            <div id="changeAmount" style="font-size: 24px; text-align: center; color: #00ff88; margin-top: 15px;"></div>
        `;
        detailsDiv.style.display = 'block';
        
        document.getElementById('cashReceived').addEventListener('input', function() {
            const total = parseFloat(document.getElementById('paymentTotal').textContent.replace('€', ''));
            const received = parseFloat(this.value) || 0;
            const change = received - total;
            
            if (change >= 0) {
                document.getElementById('changeAmount').textContent = ' Troco: €' + change.toFixed(2);
            } else {
                document.getElementById('changeAmount').textContent = '';
            }
        });
    } else {
        detailsDiv.style.display = 'none';
    }
    
    btnConfirm.style.display = 'block';
}

function processPayment() {
    if (!selectedPaymentMethod) {
        alert(' Selecione um método de pagamento!');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'process_sale');
    formData.append('items', JSON.stringify(cart));
    formData.append('discount_amount', currentDiscount);
    formData.append('payment_method', selectedPaymentMethod);
    formData.append('payment_details', JSON.stringify({}));
    formData.append('customer_id', currentCustomer ? currentCustomer.id : '');
    formData.append('coupon_id', currentCoupon ? currentCoupon.id : '');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(' Venda concluída!\n\nRecibo: ' + data.receipt_number + '\nTotal: €' + data.final_amount.toFixed(2) + '\nPontos ganhos: ' + data.points_earned);
            
            // Limpar e resetar
            cart = [];
            currentDiscount = 0;
            currentCoupon = null;
            currentCustomer = null;
            selectedPaymentMethod = null;
            
            closePaymentModal();
            updateCart();
        } else {
            alert(' Erro: ' + data.message);
        }
    });
}

function openShift(formData) {
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(' Turno aberto com sucesso!\n\nNúmero: ' + data.shift_number);
            location.reload();
        } else {
            alert(' ' + data.message);
        }
    });
}
</script>

</body>
</html>
