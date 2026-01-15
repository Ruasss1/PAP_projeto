<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();
$message = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_order') {
        $supplier_id = intval($_POST['supplier_id'] ?? 0);
        if (!$supplier_id) {
            $message = 'Selecione um fornecedor';
        } else {
            // Collect products
            $product_ids = $_POST['product_id'] ?? [];
            $qtys = $_POST['qty'] ?? [];
            $items = [];
            foreach ($product_ids as $i => $product_id) {
                if (!empty($product_id) && !empty($qtys[$i])) {
                    $items[] = [
                        'product_id' => intval($product_id),
                        'qty' => intval($qtys[$i])
                    ];
                }
            }
            if (empty($items)) {
                $message = 'Adicione pelo menos um produto';
            } else {
                $order_id = create_order($supplier_id, $items);
                if (is_numeric($order_id)) {
                    $message = "Encomenda #$order_id criada com sucesso!";
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
            <select name="supplier_id" id="supplier-select" required onchange="updateProductsBySupplier()">
                <option value="">Selecionar...</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        
        <div style="width: 100%; margin: 16px 0;">
            <label style="width: 100%;">Produtos</label>
            <div id="order-products" style="margin-top: 8px;">
                <div class="order-product-row" style="display: flex; gap: 8px; margin-bottom: 8px;">
                    <select name="product_id[]" class="product-select" style="flex: 2;">
                        <option value="">Selecionar produto...</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>" data-supplier="<?php echo htmlspecialchars($p['supplier_id'] ?? ''); ?>"><?php echo htmlspecialchars($p['name']); ?> (Stock: <?php echo $p['stock']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="qty[]" placeholder="Qty" min="1" style="flex: 1;">
                    <button type="button" onclick="this.parentElement.remove()" style="background: #dc3545;">✕</button>
                </div>
            </div>
            <button type="button" onclick="addProductRow()" style="margin-top: 8px;">+ Adicionar Produto</button>
        </div>
        
        <button type="submit">Criar Encomenda</button>
    </form>
</section>

<script>
function updateProductsBySupplier() {
    const supplierId = document.getElementById('supplier-select').value;
    const productSelects = document.querySelectorAll('.product-select');
    
    productSelects.forEach(select => {
        const options = select.querySelectorAll('option');
        options.forEach(option => {
            if (!option.value) {
                option.style.display = 'block'; // Sempre mostrar opção vazia
            } else {
                const optionSupplier = option.getAttribute('data-supplier');
                if (supplierId && optionSupplier && optionSupplier !== supplierId) {
                    option.style.display = 'none';
                } else if (!supplierId) {
                    option.style.display = 'block'; // Mostrar todos se nenhum fornecedor selecionado
                } else {
                    option.style.display = 'block';
                }
            }
        });
    });
}

function addProductRow() {
    const container = document.getElementById('order-products');
    const row = document.createElement('div');
    row.className = 'order-product-row';
    row.style.display = 'flex';
    row.style.gap = '8px';
    row.style.marginBottom = '8px';
    const supplierId = document.getElementById('supplier-select').value;
    
    let optionsHTML = '<option value="">Selecionar produto...</option>';
    <?php foreach ($products as $p): ?>
        const supplier<?php echo $p['id']; ?> = '<?php echo htmlspecialchars($p['supplier_id'] ?? ''); ?>';
        if (!supplierId || supplier<?php echo $p['id']; ?> === supplierId) {
            optionsHTML += '<option value="<?php echo $p['id']; ?>" data-supplier="<?php echo htmlspecialchars($p['supplier_id'] ?? ''); ?>"><?php echo htmlspecialchars($p['name']); ?> (Stock: <?php echo $p['stock']; ?>)</option>';
        }
    <?php endforeach; ?>
    
    row.innerHTML = `
        <select name="product_id[]" class="product-select" style="flex: 2;">
            ${optionsHTML}
        </select>
        <input type="number" name="qty[]" placeholder="Qty" min="1" style="flex: 1;">
        <button type="button" onclick="this.parentElement.remove()" style="background: #dc3545;">✕</button>
    `;
    container.appendChild(row);
}

// Ao carregar a página, filtrar produtos baseado no fornecedor selecionado
document.addEventListener('DOMContentLoaded', updateProductsBySupplier);
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

