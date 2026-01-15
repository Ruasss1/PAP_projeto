<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();
$message = null;
$order_items = []; // Array para armazenar itens adicionados dinamicamente

// Handle AJAX requests for product lookup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_product_by_sku') {
    header('Content-Type: application/json');
    $sku = $_POST['sku'] ?? '';
    $stmt = $pdo->prepare('SELECT id, name, cost_price FROM products WHERE sku = ?');
    $stmt->execute([$sku]);
    $product = $stmt->fetch();
    
    if ($product) {
        echo json_encode(['success' => true, 'product' => $product]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Produto não encontrado']);
    }
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_order') {
        $supplier_id = intval($_POST['supplier_id'] ?? 0);
        if (!$supplier_id) {
            $message = 'Selecione um fornecedor';
        } else {
            // Collect products by SKU
            $sku_inputs = $_POST['product_sku'] ?? [];
            $qty_inputs = $_POST['product_qty'] ?? [];
            $items = [];
            
            foreach ($sku_inputs as $i => $sku) {
                if (!empty($sku) && !empty($qty_inputs[$i])) {
                    // Find product by SKU
                    $stmt = $pdo->prepare('SELECT id FROM products WHERE sku = ?');
                    $stmt->execute([$sku]);
                    $product = $stmt->fetch();
                    
                    if ($product) {
                        $items[] = [
                            'product_id' => $product['id'],
                            'qty' => intval($qty_inputs[$i])
                        ];
                    } else {
                        $message = "SKU '$sku' não encontrado!";
                        break;
                    }
                }
            }
            
            if (!$message && empty($items)) {
                $message = 'Adicione pelo menos um produto com SKU válido';
            } elseif (!$message) {
                $order_id = create_order($supplier_id, $items);
                if (is_numeric($order_id)) {
                    // Update stock and register transactions
                    $pdo->beginTransaction();
                    try {
                        $total_cost = 0;
                        foreach ($items as $item) {
                            $product = get_product($item['product_id']);
                            $item_cost = $product['cost_price'] * $item['qty'];
                            $total_cost += $item_cost;
                            
                            // Increase stock (received products)
                            $stmt = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
                            $stmt->execute([$item['qty'], $item['product_id']]);
                            
                            // Register stock movement
                            $old_stock = $product['stock'];
                            $new_stock = $old_stock + $item['qty'];
                            $stmt = $pdo->prepare('INSERT INTO stock_movements (product_id, type, qty, previous_stock, new_stock, reference_type, reference_id, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                            $stmt->execute([$item['product_id'], 'purchase', $item['qty'], $old_stock, $new_stock, 'order', $order_id, 'Encomenda #' . $order_id]);
                        }
                        
                        // Register transaction (expense)
                        $stmt = $pdo->prepare('INSERT INTO transactions (type, amount, reference_type, reference_id, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
                        $stmt->execute(['expense', -$total_cost, 'order', $order_id, 'Encomenda ao fornecedor #' . $order_id]);
                        
                        $pdo->commit();
                        $message = "✓ Encomenda #$order_id criada com sucesso! Stock atualizado e gasto registado.";
                        $order_items = []; // Limpar itens após sucesso
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $message = "Encomenda criada mas erro ao atualizar stock: " . $e->getMessage();
                    }
                } else {
                    $message = "Erro: $order_id";
                }
            }
        }
    } elseif ($action === 'update_status') {
        $order_id = intval($_POST['order_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';
        $ok = update_order_status($order_id, $new_status);
        $message = $ok ? 'Status atualizado' : 'Erro ao atualizar status';
    } elseif ($action === 'receive_order') {
        $order_id = intval($_POST['order_id'] ?? 0);
        $qty_received = intval($_POST['qty_received'] ?? 0);
        $ok = receive_order_items($order_id, [['product_id' => 0, 'qty' => $qty_received]]); // Will need adjustment
        $message = $ok ? 'Encomenda recebida' : 'Erro ao receber';
    }
}

// Get orders
$orders = list_orders();
$suppliers = list_suppliers();
$products = list_products();

// Group orders by status
$orders_by_status = [
    'pending' => [],
    'processed' => [],
    'shipped' => [],
    'delivered' => []
];
foreach ($orders as $order) {
    $status = $order['status'] ?? 'pending';
    if (isset($orders_by_status[$status])) {
        $orders_by_status[$status][] = $order;
    }
}

// Get recent messages (with error handling if table doesn't exist)
try {
    $recent_messages = $pdo->query('SELECT om.*, o.id as order_id FROM order_messages om JOIN orders o ON om.order_id = o.id ORDER BY om.created_at DESC LIMIT 10')->fetchAll();
} catch (PDOException $e) {
    $recent_messages = [];
}
?>
<h1>Encomendas a Fornecedores</h1>
<?php if (!empty($message)): ?><p class="notice"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>

<!-- Criar Nova Encomenda -->
<section class="forms">
    <h2>Criar Nova Encomenda</h2>
    <form method="post">
        <input type="hidden" name="action" value="create_order">
        <label>Fornecedor
            <select name="supplier_id" id="supplier-select" required>
                <option value="">Selecionar fornecedor...</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        
        <div style="display: flex; gap: 20px; width: 100%; margin: 16px 0;">
            <!-- Esquerda: Produtos -->
            <div style="flex: 1;">
                <label style="width: 100%; display: block; margin-bottom: 12px;">
                    <span style="font-weight: 600;">📦 Produtos (use Código SKU)</span>
                    <span style="font-size: 12px; color: #666; display: block; margin-top: 4px;">
                        👉 Encontre o código SKU no documento "SKU_CODIGOS.html"
                    </span>
                </label>
                <div id="order-products" style="margin-top: 12px;">
                    <div class="order-product-row" style="display: flex; gap: 8px; margin-bottom: 8px; align-items: center;">
                        <input type="text" name="product_sku[]" placeholder="SKU (ex: SKU-0001)" maxlength="20" style="flex: 1.5; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                        <input type="number" name="product_qty[]" placeholder="Quantidade" min="1" style="flex: 0.8; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                        <button type="button" onclick="removeProductRow(this)" style="background: #dc3545; padding: 10px 15px; color: white; border: none; border-radius: 4px; cursor: pointer;">✕</button>
                    </div>
                </div>
                <button type="button" onclick="addProductRow()" style="margin-top: 8px; background: #28a745; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">+ Adicionar Produto</button>
            </div>
            
            <!-- Direita: Resumo -->
            <div style="flex: 0.8; background: #f8f9fa; border: 2px solid #dee2e6; border-radius: 8px; padding: 16px; max-height: 400px; overflow-y: auto;">
                <strong style="color: #000; display: block; margin-bottom: 12px; font-size: 14px;">📋 Resumo da Encomenda</strong>
                <div id="order-summary" style="font-size: 13px;">
                    <p style="color: #999; text-align: center; padding: 20px 0;">Nenhum produto adicionado</p>
                </div>
                <div style="border-top: 1px solid #dee2e6; padding-top: 12px; margin-top: 12px; background: #fff; border-radius: 4px; padding: 12px;">
                    <div style="display: flex; justify-content: space-between; font-weight: 600; color: #000; font-size: 13px; margin-bottom: 8px;">
                        <span>Total de itens:</span>
                        <span id="total-items" style="color: #000;">0</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-weight: 600; color: #27ae60; font-size: 14px;">
                        <span>Custo Total:</span>
                        <span id="total-price" style="color: #27ae60;">€ 0.00</span>
                    </div>
                </div>
            </div>
        </div>
        
        <button type="submit" style="background: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">Criar Encomenda</button>
    </form>
</section>

<script>
// Cache de produtos para não fazer múltiplas requisições
const productCache = {};

function removeProductRow(button) {
    button.parentElement.remove();
    updateTotalItems();
    updateOrderSummary();
}

function addProductRow() {
    const container = document.getElementById('order-products');
    const row = document.createElement('div');
    row.className = 'order-product-row';
    row.style.display = 'flex';
    row.style.gap = '8px';
    row.style.marginBottom = '8px';
    row.style.alignItems = 'center';
    
    const skuInput = document.createElement('input');
    skuInput.type = 'text';
    skuInput.name = 'product_sku[]';
    skuInput.placeholder = 'SKU (ex: SKU-0001)';
    skuInput.maxLength = '20';
    skuInput.style.flex = '1.5';
    skuInput.style.padding = '10px';
    skuInput.style.border = '1px solid #ccc';
    skuInput.style.borderRadius = '4px';
    
    const qtyInput = document.createElement('input');
    qtyInput.type = 'number';
    qtyInput.name = 'product_qty[]';
    qtyInput.placeholder = 'Quantidade';
    qtyInput.min = '1';
    qtyInput.style.flex = '0.8';
    qtyInput.style.padding = '10px';
    qtyInput.style.border = '1px solid #ccc';
    qtyInput.style.borderRadius = '4px';
    
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.textContent = '✕';
    removeBtn.style.background = '#dc3545';
    removeBtn.style.padding = '10px 15px';
    removeBtn.style.color = 'white';
    removeBtn.style.border = 'none';
    removeBtn.style.borderRadius = '4px';
    removeBtn.style.cursor = 'pointer';
    removeBtn.onclick = function() { removeProductRow(this); };
    
    // Fetch SKU when leaving the input
    skuInput.addEventListener('blur', function() {
        const sku = this.value.trim().toUpperCase();
        if (sku && sku.length > 3) {
            getProductBySkuAjax(sku, () => {
                updateTotalItems();
                rebuildSummary();
                calculateAndUpdateTotalPrice();
            });
        }
    });
    
    // Update price when quantity changes
    qtyInput.addEventListener('input', function() {
        updateTotalItems();
        rebuildSummary();
        calculateAndUpdateTotalPrice();
    });
    
    row.appendChild(skuInput);
    row.appendChild(qtyInput);
    row.appendChild(removeBtn);
    
    container.appendChild(row);
    updateTotalItems();
    updateOrderSummary();
}

function updateTotalItems() {
    const inputs = document.querySelectorAll('input[name="product_qty[]"]');
    let total = 0;
    inputs.forEach(input => {
        if (input.value) {
            total += parseInt(input.value) || 0;
        }
    });
    document.getElementById('total-items').textContent = total;
}

function getProductBySkuAjax(sku, callback) {
    // Check cache first
    if (productCache[sku]) {
        callback(productCache[sku]);
        return;
    }
    
    // Fetch from PHP
    const formData = new FormData();
    formData.append('action', 'get_product_by_sku');
    formData.append('sku', sku.toUpperCase());
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            productCache[sku.toUpperCase()] = data.product;
            callback(data.product);
        } else {
            callback(null);
        }
    })
    .catch(err => {
        console.error('Erro ao obter produto:', err);
        callback(null);
    });
}

function calculateAndUpdateTotalPrice() {
    const skuInputs = document.querySelectorAll('input[name="product_sku[]"]');
    const qtyInputs = document.querySelectorAll('input[name="product_qty[]"]');
    let totalPrice = 0;
    let allLoaded = true;
    
    skuInputs.forEach((skuInput, index) => {
        const sku = skuInput.value.trim().toUpperCase();
        const qty = parseInt(qtyInputs[index].value) || 0;
        
        if (sku && qty > 0) {
            if (productCache[sku]) {
                const product = productCache[sku];
                totalPrice += (product.cost_price || 0) * qty;
            } else {
                allLoaded = false;
            }
        }
    });
    
    const totalPriceSpan = document.getElementById('total-price');
    if (totalPriceSpan) {
        totalPriceSpan.textContent = '€ ' + totalPrice.toFixed(2);
    }
}

function updateOrderSummary() {
    const skuInputs = document.querySelectorAll('input[name="product_sku[]"]');
    const qtyInputs = document.querySelectorAll('input[name="product_qty[]"]');
    const summaryDiv = document.getElementById('order-summary');
    
    let itemCount = 0;
    let skuToFetch = [];
    
    // Count items with data and identify SKUs to fetch
    skuInputs.forEach((skuInput, index) => {
        const sku = skuInput.value.trim().toUpperCase();
        const qty = parseInt(qtyInputs[index].value) || 0;
        if (sku && qty > 0) {
            itemCount++;
            if (!productCache[sku]) {
                skuToFetch.push({sku, index});
            }
        }
    });
    
    // Fetch missing products
    skuToFetch.forEach(({sku, index}) => {
        getProductBySkuAjax(sku, (product) => {
            if (product) {
                rebuildSummary();
                calculateAndUpdateTotalPrice();
            }
        });
    });
    
    if (itemCount === 0) {
        summaryDiv.innerHTML = '<p style="color: #999; text-align: center; padding: 20px 0;">Nenhum produto adicionado</p>';
        document.getElementById('total-price').textContent = '€ 0.00';
        return;
    }
    
    rebuildSummary();
    calculateAndUpdateTotalPrice();
}

function rebuildSummary() {
    const skuInputs = document.querySelectorAll('input[name="product_sku[]"]');
    const qtyInputs = document.querySelectorAll('input[name="product_qty[]"]');
    const summaryDiv = document.getElementById('order-summary');
    
    let summaryHTML = '';
    
    skuInputs.forEach((skuInput, index) => {
        const sku = skuInput.value.trim().toUpperCase();
        const qty = parseInt(qtyInputs[index].value) || 0;
        
        if (sku && qty > 0) {
            if (productCache[sku]) {
                const product = productCache[sku];
                const itemTotal = (product.cost_price || 0) * qty;
                summaryHTML += `
                    <div style="background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 10px; margin-bottom: 8px;">
                        <div style="color: #007bff; font-weight: 600; font-size: 12px; margin-bottom: 4px;">${sku}</div>
                        <div style="color: #333; font-size: 12px; margin-bottom: 4px;">${product.name || 'Produto desconhecido'}</div>
                        <div style="color: #666; font-size: 11px; margin-bottom: 6px;">Preço unitário: € ${parseFloat(product.cost_price || 0).toFixed(2)}</div>
                        <div style="color: #333; font-size: 13px; margin-bottom: 6px;">Quantidade: <strong>${qty}</strong> un.</div>
                        <div style="color: #27ae60; font-weight: 600; font-size: 13px;">Subtotal: € ${itemTotal.toFixed(2)}</div>
                    </div>
                `;
            } else {
                summaryHTML += `
                    <div style="background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 10px; margin-bottom: 8px;">
                        <div style="color: #007bff; font-weight: 600; font-size: 12px; margin-bottom: 4px;">${sku}</div>
                        <div style="color: #999; font-size: 12px;">Carregando...</div>
                    </div>
                `;
            }
        }
    });
    
    if (summaryHTML) {
        summaryDiv.innerHTML = summaryHTML;
    } else {
        summaryDiv.innerHTML = '<p style="color: #999; text-align: center; padding: 20px 0;">Nenhum produto adicionado</p>';
    }
}

// Update on input change and input event
document.addEventListener('input', function(e) {
    if (e.target.name === 'product_sku[]') {
        // When SKU is entered, fetch the product
        const sku = e.target.value.trim().toUpperCase();
        if (sku && sku.length > 3) {
            getProductBySkuAjax(sku, () => {
                updateTotalItems();
                updateOrderSummary();
            });
        } else {
            updateTotalItems();
            updateOrderSummary();
        }
    } else if (e.target.name === 'product_qty[]') {
        // When quantity changes, immediately update totals
        updateTotalItems();
        rebuildSummary();
        calculateAndUpdateTotalPrice();
    }
});

document.addEventListener('change', function(e) {
    if (e.target.name === 'product_qty[]' || e.target.name === 'product_sku[]') {
        updateTotalItems();
        updateOrderSummary();
    }
});

// Initialize first row with listeners
document.addEventListener('DOMContentLoaded', function() {
    const firstSkuInput = document.querySelector('input[name="product_sku[]"]');
    const firstQtyInput = document.querySelector('input[name="product_qty[]"]');
    
    if (firstSkuInput) {
        // Fetch SKU when leaving the input
        firstSkuInput.addEventListener('blur', function() {
            const sku = this.value.trim().toUpperCase();
            if (sku && sku.length > 3) {
                getProductBySkuAjax(sku, () => {
                    updateTotalItems();
                    rebuildSummary();
                    calculateAndUpdateTotalPrice();
                });
            }
        });
    }
    
    if (firstQtyInput) {
        // Update price when quantity changes
        firstQtyInput.addEventListener('input', function() {
            updateTotalItems();
            rebuildSummary();
            calculateAndUpdateTotalPrice();
        });
    }
});
</script>

<!-- Resumo por Status -->
<div class="dashboard-cards">
    <div class="card">
        <h3>Pendentes</h3>
        <p class="<?php echo count($orders_by_status['pending']) > 0 ? 'warning' : 'positive'; ?>">
            <?php echo count($orders_by_status['pending']); ?>
        </p>
    </div>
    <div class="card">
        <h3>Processadas</h3>
        <p><?php echo count($orders_by_status['processed']); ?></p>
    </div>
    <div class="card">
        <h3>Enviadas</h3>
        <p><?php echo count($orders_by_status['shipped']); ?></p>
    </div>
    <div class="card">
        <h3>Entregues</h3>
        <p class="positive"><?php echo count($orders_by_status['delivered']); ?></p>
    </div>
</div>

<!-- Mensagens Recentes -->
<?php if (!empty($recent_messages)): ?>
<h2>Mensagens de Encomendas</h2>
<div class="messages-list">
    <?php foreach ($recent_messages as $msg): ?>
        <div class="message-item">
            <span class="msg-type"><?php echo $msg['type']; ?></span>
            <span class="msg-order">#<?php echo $msg['order_id']; ?></span>
            <span class="msg-text"><?php echo htmlspecialchars($msg['message']); ?></span>
            <span class="msg-date"><?php echo date('d/m H:i', strtotime($msg['created_at'])); ?></span>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Lista de Encomendas -->
<h2>Todas as Encomendas</h2>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Fornecedor</th>
                <th>Status</th>
                <th>Total</th>
                <th>Criada</th>
                <th>Processada</th>
                <th>Entregue</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><?php echo $o['id']; ?></td>
                    <td><?php echo htmlspecialchars($o['supplier_name']); ?></td>
                    <td>
                        <span class="status-badge <?php echo $o['status'] ?? 'pending'; ?>">
                            <?php echo ucfirst($o['status'] ?? 'pending'); ?>
                        </span>
                    </td>
                    <td><?php echo number_format($o['total_cost'] ?? 0, 2); ?>€</td>
                    <td><?php echo $o['created_at'] ? date('d/m H:i', strtotime($o['created_at'])) : '-'; ?></td>
                    <td><?php echo $o['processed_at'] ? date('d/m H:i', strtotime($o['processed_at'])) : '-'; ?></td>
                    <td><?php echo $o['delivered_at'] ? date('d/m H:i', strtotime($o['delivered_at'])) : '-'; ?></td>
                    <td>
                        <a href="encomendas.php?view=<?php echo $o['id']; ?>">Ver</a>
                        <?php if (($o['status'] ?? 'pending') !== 'delivered'): ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                <select name="new_status" onchange="this.form.submit()" style="padding: 4px; font-size: 12px;">
                                    <option value="">Alterar status...</option>
                                    <?php if (($o['status'] ?? 'pending') === 'pending'): ?>
                                        <option value="processed">Processar</option>
                                    <?php endif; ?>
                                    <?php if (in_array($o['status'] ?? '', ['pending', 'processed'])): ?>
                                        <option value="shipped">Enviar</option>
                                    <?php endif; ?>
                                    <?php if (in_array($o['status'] ?? '', ['pending', 'processed', 'shipped'])): ?>
                                        <option value="delivered">Entregue</option>
                                    <?php endif; ?>
                                </select>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.messages-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 24px;
}

.message-item {
    background: #1e1e2e;
    border: 1px solid #333;
    border-radius: 6px;
    padding: 12px;
    font-size: 14px;
}

.msg-type {
    background: #0d6efd;
    color: #fff;
    padding: 2px 8px;
    border-radius: 4px;
    margin-right: 8px;
    font-size: 11px;
    text-transform: uppercase;
}

.msg-order {
    font-weight: 600;
    margin-right: 8px;
}

.msg-text {
    color: #aaa;
}

.msg-date {
    float: right;
    color: #666;
    font-size: 12px;
}

.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.status-badge.pending {
    background: #fbbf24;
    color: #000;
}

.status-badge.processed {
    background: #60a5fa;
    color: #fff;
}

.status-badge.shipped {
    background: #a78bfa;
    color: #fff;
}

.status-badge.delivered {
    background: #4ade80;
    color: #000;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

