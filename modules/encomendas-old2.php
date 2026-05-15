<?php
/**
 * ENCOMENDAS - PREMIUM
 * Gestão moderna de encomendas a fornecedores
 */

header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();
$current_store_id = get_current_store_id();
$message = '';
$error = '';

// Carregar fornecedores
$suppliers = $pdo->query("SELECT id, name FROM suppliers WHERE active = 1 ORDER BY name")->fetchAll();

// Carregar produtos
$products = $pdo->prepare("SELECT id, name, barcode, stock, min_stock, cost_price FROM products WHERE store_id = ? AND active = 1 ORDER BY name");
$products->execute([$current_store_id]);
$products = $products->fetchAll();

// Criar encomenda (usa estrutura existente da tabela orders)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    $supplier_id = intval($_POST['supplier_id']);
    $product_id = intval($_POST['product_id']);
    $qty = intval($_POST['qty']);
    $cost_price = floatval($_POST['cost_price'] ?? 0);
    
    if (empty($supplier_id)) {
        $error = "Selecione um fornecedor.";
    } elseif (empty($product_id)) {
        $error = "Selecione um produto.";
    } elseif ($qty <= 0) {
        $error = "Quantidade deve ser maior que 0.";
    } else {
        try {
            $total_cost = $qty * $cost_price;
            $stmt = $pdo->prepare("INSERT INTO orders (supplier_id, product_id, qty, cost_price, total_cost, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$supplier_id, $product_id, $qty, $cost_price, $total_cost]);
            $order_id = $pdo->lastInsertId();
            $message = "Encomenda #$order_id criada com sucesso!";
        } catch (PDOException $e) {
            $error = "Erro: " . $e->getMessage();
        }
    }
}

// Atualizar status
if (isset($_GET['status']) && isset($_GET['order_id'])) {
    $new_status = $_GET['status'];
    $order_id = intval($_GET['order_id']);
    $valid = ['pending', 'processed', 'shipped', 'delivered'];
    
    if (in_array($new_status, $valid)) {
        $update_field = match($new_status) {
            'processed' => ", processed_at = NOW()",
            'delivered' => ", delivered_at = NOW(), received = 1",
            default => ""
        };
        
        $stmt = $pdo->prepare("UPDATE orders SET status = ? $update_field WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);
        
        // Se entregue, atualizar stock
        if ($new_status === 'delivered') {
            $order = $pdo->prepare("SELECT product_id, qty FROM orders WHERE id = ?");
            $order->execute([$order_id]);
            $order_data = $order->fetch();
            if ($order_data) {
                $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$order_data['qty'], $order_data['product_id']]);
            }
        }
        $message = "Encomenda #$order_id atualizada para '$new_status'.";
    }
}

// Filtros
$status_filter = $_GET['status_filter'] ?? '';
$sql = "SELECT o.*, s.name as supplier_name, p.name as product_name
        FROM orders o
        JOIN suppliers s ON o.supplier_id = s.id
        JOIN products p ON o.product_id = p.id
        WHERE 1=1";
$params = [];

if ($status_filter) {
    $sql .= " AND o.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY o.created_at DESC LIMIT 50";
$orders_stmt = $pdo->prepare($sql);
$orders_stmt->execute($params);
$orders = $orders_stmt->fetchAll();

// Estatísticas
$stats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed,
    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered
    FROM orders")->fetch();

$page_title = 'Encomendas';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success"><?= $message ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon blue">📦</div>
        </div>
        <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
        <div class="stat-label">Total Encomendas</div>
    </div>
    <div class="stat-card" style="cursor: pointer;" onclick="window.location='?status_filter=pending'">
        <div class="stat-header">
            <div class="stat-icon orange">⏳</div>
        </div>
        <div class="stat-value"><?= $stats['pending'] ?? 0 ?></div>
        <div class="stat-label">Pendentes</div>
    </div>
    <div class="stat-card" style="cursor: pointer;" onclick="window.location='?status_filter=processed'">
        <div class="stat-header">
            <div class="stat-icon purple">✅</div>
        </div>
        <div class="stat-value"><?= $stats['processed'] ?? 0 ?></div>
        <div class="stat-label">Processadas</div>
    </div>
    <div class="stat-card" style="cursor: pointer;" onclick="window.location='?status_filter=delivered'">
        <div class="stat-header">
            <div class="stat-icon green">📬</div>
        </div>
        <div class="stat-value"><?= $stats['delivered'] ?? 0 ?></div>
        <div class="stat-label">Entregues</div>
    </div>
</div>

<!-- Nova Encomenda -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3 class="card-title">➕ Nova Encomenda</h3>
    </div>
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="create_order" value="1">
            
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Fornecedor *</label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Produto *</label>
                    <select name="product_id" class="form-select" required id="productSelect" onchange="updatePrice()">
                        <option value="">Selecione...</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" data-price="<?= $p['cost_price'] ?>">
                            <?= htmlspecialchars($p['name']) ?> (Stock: <?= $p['stock'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Quantidade *</label>
                    <input type="number" name="qty" class="form-input" min="1" value="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Preço Custo (€)</label>
                    <input type="number" name="cost_price" id="costPrice" class="form-input" step="0.01" min="0" placeholder="0.00">
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" style="margin-top: 16px;">📦 Criar Encomenda</button>
        </form>
    </div>
</div>

<!-- Filtro -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 24px;">
        <div style="display: flex; gap: 8px; align-items: center;">
            <span style="font-size: 14px; color: var(--text-secondary);">Filtrar:</span>
            <a href="/modules/encomendas.php" class="btn <?= !$status_filter ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Todas</a>
            <a href="?status_filter=pending" class="btn <?= $status_filter === 'pending' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">⏳ Pendentes</a>
            <a href="?status_filter=processed" class="btn <?= $status_filter === 'processed' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">✅ Processadas</a>
            <a href="?status_filter=shipped" class="btn <?= $status_filter === 'shipped' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">🚚 Enviadas</a>
            <a href="?status_filter=delivered" class="btn <?= $status_filter === 'delivered' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">📬 Entregues</a>
        </div>
    </div>
</div>

<!-- Lista -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Encomendas (<?= count($orders) ?>)</h3>
    </div>
    <div class="table-container" style="border: none;">
        <table class="table">
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Data</th>
                    <th>Fornecedor</th>
                    <th>Produto</th>
                    <th>Qtd</th>
                    <th>Custo Total</th>
                    <th>Estado</th>
                    <th style="width: 200px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="8" class="table-empty">
                        <div class="empty-state">
                            <div class="empty-icon">📦</div>
                            <div class="empty-title">Sem encomendas</div>
                            <div class="empty-text">Crie a primeira encomenda acima.</div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td><strong>#<?= $order['id'] ?></strong></td>
                    <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                    <td><strong><?= htmlspecialchars($order['supplier_name']) ?></strong></td>
                    <td><?= htmlspecialchars($order['product_name']) ?></td>
                    <td><span class="badge badge-primary"><?= $order['qty'] ?></span></td>
                    <td><strong class="cost-value">€<?= number_format($order['total_cost'] ?? ($order['qty'] * $order['cost_price']), 2) ?></strong></td>
                    <td>
                        <?php
                        $badge = match($order['status']) {
                            'pending' => 'badge-warning',
                            'processed' => 'badge-primary',
                            'shipped' => 'badge-info',
                            'delivered' => 'badge-success',
                            default => 'badge-muted'
                        };
                        $icon = match($order['status']) {
                            'pending' => '⏳',
                            'processed' => '✅',
                            'shipped' => '🚚',
                            'delivered' => '📬',
                            default => '❓'
                        };
                        ?>
                        <span class="badge <?= $badge ?>"><?= $icon ?> <?= ucfirst($order['status']) ?></span>
                    </td>
                    <td>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <?php if ($order['status'] === 'pending'): ?>
                            <a href="?order_id=<?= $order['id'] ?>&status=processed" class="btn btn-primary btn-sm">✅ Processar</a>
                            <?php elseif ($order['status'] === 'processed'): ?>
                            <a href="?order_id=<?= $order['id'] ?>&status=shipped" class="btn btn-primary btn-sm">🚚 Enviada</a>
                            <?php elseif ($order['status'] === 'shipped'): ?>
                            <a href="?order_id=<?= $order['id'] ?>&status=delivered" class="btn btn-success btn-sm">📬 Entregue</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.badge-warning { background: #f59e0b22; color: #f59e0b; }
.badge-info { background: #06b6d422; color: #06b6d4; }
</style>

<script>
function updatePrice() {
    const select = document.getElementById('productSelect');
    const priceInput = document.getElementById('costPrice');
    const selectedOption = select.options[select.selectedIndex];
    const price = selectedOption.dataset.price || 0;
    priceInput.value = parseFloat(price).toFixed(2);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
