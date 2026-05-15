<?php
/**
 * NOVA VENDA - PREMIUM
 * Interface moderna de ponto de venda
 */

header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();
$current_store_id = get_current_store_id();
$message = null;
$message_type = 'success';

// Processar venda
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_sale') {
    $items = json_decode($_POST['items'] ?? '[]', true);
    $payment_method = $_POST['payment_method'] ?? 'Dinheiro';
    $customer_id = intval($_POST['customer_id'] ?? 0) ?: null;
    
    if (empty($items)) {
        $message = 'Adicione pelo menos um produto';
        $message_type = 'danger';
    } else {
        $sale_id = create_sale($items, $payment_method, $customer_id);
        if ($sale_id) {
            $message = "Venda #$sale_id registada com sucesso!";
            $message_type = 'success';
        } else {
            $message = 'Erro ao registar venda';
            $message_type = 'danger';
        }
    }
}

// Obter produtos
$products = $pdo->prepare("
    SELECT id, name, barcode, category, sell_price, stock, vat 
    FROM products 
    WHERE store_id = ? AND active = 1 AND stock > 0
    ORDER BY name
");
$products->execute([$current_store_id]);
$products = $products->fetchAll();

// Obter categorias
$categories = array_unique(array_column($products, 'category'));
sort($categories);

// Obter clientes
$customers = $pdo->query("SELECT id, name, phone FROM customers ORDER BY name")->fetchAll();

$page_title = 'Nova Venda';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.pos-layout {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 24px;
    height: calc(100vh - 180px);
    min-height: 500px;
}

.products-panel {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-height: 0;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 12px;
    overflow-y: auto;
    padding: 4px;
    flex: 1;
    min-height: 0;
}

/* Custom scrollbar for products */
.products-grid::-webkit-scrollbar {
    width: 6px;
}

.products-grid::-webkit-scrollbar-thumb {
    background: var(--bg-tertiary);
    border-radius: 3px;
}

.product-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 16px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
}

.product-card:hover {
    border-color: var(--accent);
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.product-card:active {
    transform: translateY(0) scale(0.98);
}

.product-card-name {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.product-card-price {
    font-size: 18px;
    font-weight: 700;
    color: var(--accent);
}

.product-card-stock {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 4px;
}

.cart-panel {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.cart-header {
    padding: 20px;
    border-bottom: 1px solid var(--border);
}

.cart-items {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
}

.cart-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: var(--bg-tertiary);
    border-radius: var(--radius);
    margin-bottom: 8px;
}

.cart-item-info {
    flex: 1;
}

.cart-item-name {
    font-weight: 600;
    font-size: 14px;
}

.cart-item-price {
    font-size: 12px;
    color: var(--text-muted);
}

.cart-item-qty {
    display: flex;
    align-items: center;
    gap: 8px;
}

.cart-item-qty button {
    width: 28px;
    height: 28px;
    border: none;
    background: var(--bg-hover);
    color: var(--text-primary);
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}

.cart-item-qty button:hover {
    background: var(--accent);
    color: white;
}

.cart-item-qty span {
    min-width: 30px;
    text-align: center;
    font-weight: 600;
}

.cart-item-total {
    font-weight: 700;
    min-width: 70px;
    text-align: right;
}

.cart-item-remove {
    color: var(--danger);
    cursor: pointer;
    padding: 4px;
}

.cart-item-remove:hover {
    opacity: 0.7;
}

.cart-footer {
    padding: 20px;
    border-top: 1px solid var(--border);
    background: var(--bg-tertiary);
}

.cart-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.cart-total-label {
    font-size: 18px;
    font-weight: 600;
}

.cart-total-value {
    font-size: 32px;
    font-weight: 800;
    color: var(--accent);
}

.payment-methods {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-bottom: 16px;
}

.payment-btn {
    padding: 12px;
    border: 2px solid var(--border);
    background: transparent;
    border-radius: var(--radius);
    cursor: pointer;
    font-weight: 500;
    color: var(--text-secondary);
    transition: var(--transition);
}

.payment-btn:hover {
    border-color: var(--border-light);
    background: var(--bg-tertiary);
}

.payment-btn.active {
    border-color: var(--accent);
    background: var(--accent-light);
    color: var(--accent);
}

.cart-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--text-muted);
    text-align: center;
    padding: 40px;
}

.cart-empty-icon {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}

@media (max-width: 1024px) {
    .pos-layout {
        grid-template-columns: 1fr;
        height: auto;
    }
    
    .cart-panel {
        order: -1;
        max-height: 400px;
    }
}
</style>

<?php if ($message): ?>
<div class="alert alert-<?= $message_type ?> fade-in">
    <span class="alert-icon"><?= $message_type === 'success' ? '✓' : '⚠️' ?></span>
    <div class="alert-content">
        <div class="alert-message"><?= htmlspecialchars($message) ?></div>
    </div>
</div>
<?php endif; ?>

<div class="pos-layout">
    <!-- Produtos -->
    <div class="products-panel">
        <div class="card" style="margin-bottom: 16px;">
            <div class="card-body" style="padding: 12px 16px;">
                <div style="display: flex; gap: 12px; align-items: center;">
                    <div class="search-box" style="flex: 1;">
                        <svg class="search-icon" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" id="search-product" class="search-input" placeholder="Pesquisar produto ou código..." oninput="filterProducts()">
                    </div>
                    <select id="filter-category" class="form-select" style="width: auto;" onchange="filterProducts()">
                        <option value="">Todas Categorias</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="products-grid" id="products-grid">
            <?php foreach ($products as $p): ?>
            <div class="product-card" onclick="addToCart(<?= htmlspecialchars(json_encode($p)) ?>)" data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>" data-barcode="<?= htmlspecialchars($p['barcode'] ?? '') ?>" data-category="<?= htmlspecialchars($p['category'] ?? '') ?>">
                <div class="product-card-name"><?= htmlspecialchars($p['name']) ?></div>
                <div class="product-card-price">€<?= number_format($p['sell_price'], 2) ?></div>
                <div class="product-card-stock"><?= $p['stock'] ?> em stock</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Carrinho -->
    <div class="cart-panel">
        <div class="cart-header">
            <h3 style="font-size: 18px; font-weight: 700;">🛒 Carrinho</h3>
        </div>
        
        <div class="cart-items" id="cart-items">
            <div class="cart-empty" id="cart-empty">
                <div class="cart-empty-icon">🛒</div>
                <div>Carrinho vazio</div>
                <div style="font-size: 12px; margin-top: 4px;">Clique nos produtos para adicionar</div>
            </div>
        </div>
        
        <div class="cart-footer">
            <div class="cart-total">
                <span class="cart-total-label">Total</span>
                <span class="cart-total-value" id="cart-total">€0.00</span>
            </div>
            
            <div class="payment-methods">
                <button type="button" class="payment-btn active" onclick="setPayment('Dinheiro')">💵 Dinheiro</button>
                <button type="button" class="payment-btn" onclick="setPayment('Cartão')">💳 Cartão</button>
                <button type="button" class="payment-btn" onclick="setPayment('MBWay')">📱 MBWay</button>
            </div>
            
            <form method="post" id="sale-form">
                <input type="hidden" name="action" value="complete_sale">
                <input type="hidden" name="items" id="cart-data">
                <input type="hidden" name="payment_method" id="payment-method" value="Dinheiro">
                <button type="submit" class="btn btn-success w-full" style="padding: 16px; font-size: 16px;" id="complete-btn" disabled>
                    ✓ Finalizar Venda
                </button>
            </form>
            
            <button type="button" class="btn btn-secondary w-full" style="margin-top: 8px;" onclick="clearCart()">
                🗑️ Limpar Carrinho
            </button>
        </div>
    </div>
</div>

<script>
let cart = [];
let paymentMethod = 'Dinheiro';

function addToCart(product) {
    const existing = cart.find(item => item.id === product.id);
    
    if (existing) {
        if (existing.qty < product.stock) {
            existing.qty++;
        } else {
            alert('Stock insuficiente!');
            return;
        }
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            price: parseFloat(product.sell_price),
            vat: parseFloat(product.vat || 23),
            qty: 1,
            maxStock: product.stock
        });
    }
    
    renderCart();
}

function updateQty(id, delta) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    
    item.qty += delta;
    
    if (item.qty <= 0) {
        cart = cart.filter(i => i.id !== id);
    } else if (item.qty > item.maxStock) {
        item.qty = item.maxStock;
        alert('Stock insuficiente!');
    }
    
    renderCart();
}

function removeFromCart(id) {
    cart = cart.filter(i => i.id !== id);
    renderCart();
}

function clearCart() {
    cart = [];
    renderCart();
}

function setPayment(method) {
    paymentMethod = method;
    document.getElementById('payment-method').value = method;
    
    document.querySelectorAll('.payment-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.textContent.includes(method.split(' ')[0])) {
            btn.classList.add('active');
        }
    });
}

function renderCart() {
    const container = document.getElementById('cart-items');
    const emptyMsg = document.getElementById('cart-empty');
    const totalEl = document.getElementById('cart-total');
    const dataEl = document.getElementById('cart-data');
    const submitBtn = document.getElementById('complete-btn');
    
    if (cart.length === 0) {
        container.innerHTML = `
            <div class="cart-empty" id="cart-empty">
                <div class="cart-empty-icon">🛒</div>
                <div>Carrinho vazio</div>
                <div style="font-size: 12px; margin-top: 4px;">Clique nos produtos para adicionar</div>
            </div>
        `;
        totalEl.textContent = '€0.00';
        dataEl.value = '[]';
        submitBtn.disabled = true;
        return;
    }
    
    let html = '';
    let total = 0;
    
    cart.forEach(item => {
        const itemTotal = item.price * item.qty;
        total += itemTotal;
        
        html += `
            <div class="cart-item">
                <div class="cart-item-info">
                    <div class="cart-item-name">${item.name}</div>
                    <div class="cart-item-price">€${item.price.toFixed(2)} cada</div>
                </div>
                <div class="cart-item-qty">
                    <button type="button" onclick="updateQty(${item.id}, -1)">-</button>
                    <span>${item.qty}</span>
                    <button type="button" onclick="updateQty(${item.id}, 1)">+</button>
                </div>
                <div class="cart-item-total">€${itemTotal.toFixed(2)}</div>
                <div class="cart-item-remove" onclick="removeFromCart(${item.id})">✕</div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    totalEl.textContent = '€' + total.toFixed(2);
    
    // Preparar dados para envio
    const saleItems = cart.map(item => ({
        product_id: item.id,
        quantity: item.qty,
        price: item.price,
        vat: item.vat
    }));
    dataEl.value = JSON.stringify(saleItems);
    submitBtn.disabled = false;
}

function filterProducts() {
    const search = document.getElementById('search-product').value.toLowerCase();
    const category = document.getElementById('filter-category').value;
    
    document.querySelectorAll('.product-card').forEach(card => {
        const name = card.dataset.name;
        const barcode = card.dataset.barcode;
        const cat = card.dataset.category;
        
        const matchesSearch = !search || name.includes(search) || barcode.includes(search);
        const matchesCategory = !category || cat === category;
        
        card.style.display = (matchesSearch && matchesCategory) ? 'block' : 'none';
    });
}

// Suporte a leitor de código de barras
document.addEventListener('keydown', function(e) {
    if (e.target.tagName === 'INPUT') return;
    
    // Foco no campo de pesquisa ao digitar
    if (/^[a-zA-Z0-9]$/.test(e.key)) {
        document.getElementById('search-product').focus();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>