<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();
$message = null;
$order_items = []; // Array para armazenar itens adicionados dinamicamente

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
                    $message = "✓ Encomenda #$order_id criada com sucesso!";
                    $order_items = []; // Limpar itens após sucesso
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
        
        <div style="width: 100%; margin: 16px 0;">
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
        
        <div style="background: #f0f8ff; border-left: 4px solid #2196F3; padding: 12px; margin: 16px 0; border-radius: 4px; font-size: 13px;">
            <strong>Total de itens:</strong> <span id="total-items">0</span> produtos
        </div>
        
        <button type="submit" style="background: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">Criar Encomenda</button>
    </form>
</section>

<script>
function removeProductRow(button) {
    button.parentElement.remove();
    updateTotalItems();
}

function addProductRow() {
    const container = document.getElementById('order-products');
    const row = document.createElement('div');
    row.className = 'order-product-row';
    row.style.display = 'flex';
    row.style.gap = '8px';
    row.style.marginBottom = '8px';
    row.style.alignItems = 'center';
    
    row.innerHTML = `
        <input type="text" name="product_sku[]" placeholder="SKU (ex: SKU-0001)" maxlength="20" style="flex: 1.5; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
        <input type="number" name="product_qty[]" placeholder="Quantidade" min="1" style="flex: 0.8; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
        <button type="button" onclick="removeProductRow(this)" style="background: #dc3545; padding: 10px 15px; color: white; border: none; border-radius: 4px; cursor: pointer;">✕</button>
    `;
    
    container.appendChild(row);
    updateTotalItems();
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

// Update total items on input change
document.addEventListener('change', function(e) {
    if (e.target.name === 'product_qty[]') {
        updateTotalItems();
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

