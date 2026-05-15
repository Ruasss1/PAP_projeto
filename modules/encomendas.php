<?php
/**
 * ENCOMENDAS - Multi-produto por fornecedor
 */
header('Cache-Control: no-cache, no-store, must-revalidate');
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();
$current_store_id = get_current_store_id();
$message = '';
$error   = '';

// ─── Criar encomenda multi-produto ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    $product_ids  = array_filter((array)($_POST['product_id']  ?? []), fn($v) => !empty($v));
    $quantities   = array_values((array)($_POST['qty']          ?? []));
    $cost_prices  = array_values((array)($_POST['cost_price']   ?? []));

    if ($supplier_id <= 0) {
        $error = 'Selecione um fornecedor.';
    } elseif (empty($product_ids)) {
        $error = 'Adicione pelo menos um produto.';
    } else {
        $items = [];
        $product_ids = array_values($product_ids); // Re-indexar
        
        foreach ($product_ids as $i => $pid) {
            $pid = intval($pid);
            $qty = intval($quantities[$i] ?? 0);
            $cp  = floatval($cost_prices[$i] ?? 0);
            
            if ($pid > 0 && $qty > 0 && $cp >= 0) {
                $items[] = ['product_id' => $pid, 'qty' => $qty, 'cost_price' => $cp];
            }
        }
        
        if (empty($items)) {
            $error = 'Nenhuma linha válida (produto + quantidade > 0).';
        } else {
            try {
                $pdo->beginTransaction();
                $total_cost = array_sum(array_map(fn($it) => $it['qty'] * $it['cost_price'], $items));
                $stmt = $pdo->prepare("INSERT INTO orders (supplier_id, total_cost, status, created_at) VALUES (?, ?, 'pending', NOW())");
                $stmt->execute([$supplier_id, $total_cost]);
                $order_id = $pdo->lastInsertId();
                
                // Validar antes de inserir
                $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, cost_price) VALUES (?, ?, ?, ?)");
                foreach ($items as $it) {
                    if (empty($it['product_id'])) {
                        throw new Exception('Product ID não pode estar vazio');
                    }
                    $stmt_item->execute([$order_id, $it['product_id'], $it['qty'], $it['cost_price']]);
                }
                
                $pdo->commit();
                $message = "✅ Encomenda #$order_id criada com " . count($items) . " produto(s). Total: €" . number_format($total_cost, 2);
            } catch (\Throwable $e) {
                $pdo->rollBack();
                $error = 'Erro ao criar encomenda: ' . $e->getMessage();
            }
        }
    }
}

// ─── Atualizar estado ─────────────────────────────────────────────────────────
if (isset($_GET['status'], $_GET['order_id'])) {
    $new_status = $_GET['status'];
    $order_id   = intval($_GET['order_id']);
    if (in_array($new_status, ['pending', 'processed', 'shipped', 'delivered'])) {
        try {
            $extra = match($new_status) {
                'processed' => ', processed_at = NOW()',
                'delivered' => ', delivered_at = NOW(), received = 1',
                default     => ''
            };
            $pdo->prepare("UPDATE orders SET status = ? $extra WHERE id = ?")->execute([$new_status, $order_id]);
            if ($new_status === 'delivered') {
                $items_q = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                $items_q->execute([$order_id]);
                foreach ($items_q->fetchAll() as $it) {
                    $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$it['quantity'], $it['product_id']]);
                }
            }
            $message = "Encomenda #$order_id → $new_status.";
        } catch (\Throwable $e) { $error = $e->getMessage(); }
    }
}

// ─── Processar todas as pendentes ────────────────────────────────────────────
if (isset($_GET['process_all']) && $_GET['process_all'] === '1') {
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'processed', processed_at = NOW() WHERE status = 'pending'");
        $stmt->execute();
        $changed = $stmt->rowCount();
        $message = $changed > 0
            ? "✅ $changed encomenda(s) pendente(s) foram processadas."
            : 'Sem encomendas pendentes para processar.';
    } catch (\Throwable $e) {
        $error = 'Erro ao processar encomendas pendentes: ' . $e->getMessage();
    }
}

// ─── Marcar todas pendentes como entregues e atualizar stock ─────────────────
if (isset($_GET['deliver_all']) && $_GET['deliver_all'] === '1') {
    try {
        $pdo->beginTransaction();
        $pending_orders = $pdo->query("SELECT id FROM orders WHERE status != 'delivered'")->fetchAll(PDO::FETCH_COLUMN);
        $total_updated = 0;
        foreach ($pending_orders as $oid) {
            $pdo->prepare("UPDATE orders SET status = 'delivered', delivered_at = NOW(), received = 1 WHERE id = ?")->execute([$oid]);
            $items_q = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $items_q->execute([$oid]);
            foreach ($items_q->fetchAll() as $it) {
                $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$it['quantity'], $it['product_id']]);
            }
            $total_updated++;
        }
        $pdo->commit();
        $message = $total_updated > 0
            ? "✅ $total_updated encomenda(s) marcadas como entregues e stock atualizado."
            : 'Sem encomendas para marcar como entregues.';
    } catch (\Throwable $e) {
        $pdo->rollBack();
        $error = 'Erro: ' . $e->getMessage();
    }
}

// ─── Carregar fornecedores ────────────────────────────────────────────────────
$suppliers = $pdo->query("SELECT id, name FROM suppliers WHERE active = 1 ORDER BY name")->fetchAll();

// ─── Filtros e listagem ───────────────────────────────────────────────────────
$status_filter   = $_GET['status_filter']   ?? '';
$supplier_filter = intval($_GET['supplier_filter'] ?? 0);
$sql = "SELECT o.id, o.created_at, o.status, o.total_cost, o.received,
               s.name AS supplier_name, COUNT(oi.id) AS item_count
        FROM orders o
        JOIN suppliers s ON o.supplier_id = s.id
        LEFT JOIN order_items oi ON oi.order_id = o.id
        WHERE 1=1";
$params = [];
if ($status_filter) { $sql .= " AND o.status = ?"; $params[] = $status_filter; }
if ($supplier_filter > 0) { $sql .= " AND o.supplier_id = ?"; $params[] = $supplier_filter; }
$sql .= " GROUP BY o.id ORDER BY (o.status = 'delivered') ASC, o.id DESC LIMIT 100";
$orders_stmt = $pdo->prepare($sql);
$orders_stmt->execute($params);
$orders = $orders_stmt->fetchAll();

$order_ids = array_column($orders, 'id');
$order_items_map = [];
if (!empty($order_ids)) {
    $in = implode(',', array_fill(0, count($order_ids), '?'));
    $items_stmt = $pdo->prepare("SELECT oi.order_id, oi.quantity, oi.cost_price, p.name AS product_name FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id IN ($in) ORDER BY p.name ASC");
    $items_stmt->execute($order_ids);
    foreach ($items_stmt->fetchAll() as $row) { $order_items_map[$row['order_id']][] = $row; }
}

$stats = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending, SUM(CASE WHEN status='processed' THEN 1 ELSE 0 END) as processed, SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) as delivered FROM orders")->fetch();

$page_title = 'Encomendas';
require_once __DIR__ . '/../includes/header.php';

?>

<?php if ($message): ?>
<div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-header"><div class="stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg></div></div>
        <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
        <div class="stat-label">Total</div>
    </div>
    <div class="stat-card" style="cursor:pointer" onclick="location='?status_filter=pending'">
        <div class="stat-header"><div class="stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div></div>
        <div class="stat-value"><?= $stats['pending'] ?? 0 ?></div>
        <div class="stat-label">Pendentes</div>
    </div>
    <div class="stat-card" style="cursor:pointer" onclick="location='?status_filter=processed'">
        <div class="stat-header"><div class="stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div></div>
        <div class="stat-value"><?= $stats['processed'] ?? 0 ?></div>
        <div class="stat-label">Processadas</div>
    </div>
    <div class="stat-card" style="cursor:pointer" onclick="location='?status_filter=delivered'">
        <div class="stat-header"><div class="stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg></div></div>
        <div class="stat-value"><?= $stats['delivered'] ?? 0 ?></div>
        <div class="stat-label">Entregues</div>
    </div>
</div>

<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3 class="card-title">Nova Encomenda</h3>
    </div>
    <div class="card-body">
        <form method="post" id="orderForm">
            <input type="hidden" name="create_order" value="1">
            <div class="form-group" style="margin-bottom: 20px; max-width: 400px;">
                <label class="form-label">Fornecedor *</label>
                <select name="supplier_id" id="supplierSelect" class="form-select" required onchange="loadSupplierProducts()">
                    <option value="">&#8212; Selecione um fornecedor &#8212;</option>
                    <?php foreach ($suppliers as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="noSupplierMsg" style="color:var(--text-muted);font-size:13px;margin-bottom:16px;">
                Selecione um fornecedor para ver os seus produtos.
            </div>
            <div id="loadingMsg" style="display:none;color:var(--text-muted);font-size:13px;margin-bottom:16px;">
                A carregar produtos...
            </div>
            <div id="noProductsMsg" style="display:none;color:var(--warning);font-size:13px;margin-bottom:16px;">
                Este fornecedor n&#227;o tem produtos associados.
                <a href="/modules/produtos.php" style="color:var(--accent);">Associar produtos</a>
            </div>
            <div id="itemsSection" style="display:none;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <strong style="color:var(--text-primary);">Produtos da Encomenda</strong>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addRow()">+ Adicionar Linha</button>
                </div>
                <div style="overflow-x:auto;">
                    <table class="table" id="itemsTable">
                        <thead>
                            <tr>
                                <th>Produto *</th>
                                <th style="width:110px">Qtd *</th>
                                <th style="width:150px">Pre&#231;o Custo (&#8364;)</th>
                                <th style="width:120px">Subtotal</th>
                                <th style="width:50px"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody"></tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" style="text-align:right;font-weight:600;color:var(--text-secondary);">Total:</td>
                                <td><strong id="totalDisplay" style="color:var(--success);">&#8364;0.00</strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top:16px;">Criar Encomenda</button>
            </div>
        </form>
    </div>
</div>

<div class="card" style="margin-bottom:24px;">
    <div class="card-body" style="padding:16px 24px;">
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <span style="font-size:13px;color:var(--text-muted);">Estado:</span>
            <a href="/modules/encomendas.php" class="btn <?= !$status_filter ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Todas</a>
            <a href="?status_filter=pending"   class="btn <?= $status_filter === 'pending'   ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Pendentes</a>
            <a href="?status_filter=processed" class="btn <?= $status_filter === 'processed' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Processadas</a>
            <a href="?status_filter=shipped"   class="btn <?= $status_filter === 'shipped'   ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Enviadas</a>
            <a href="?status_filter=delivered" class="btn <?= $status_filter === 'delivered' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Entregues</a>
            <span style="font-size:13px;color:var(--text-muted);margin-left:12px;">Fornecedor:</span>
            <select class="form-select" style="height:32px;font-size:13px;min-width:240px;max-width:340px;" onchange="filterBySupplier(this.value)">
                <option value="0">Todos</option>
                <?php foreach ($suppliers as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $supplier_filter == $s['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Encomendas (<?= count($orders) ?>)</h3>
    </div>
    <div class="table-container" style="border:none;">
        <table class="table">
            <thead>
                <tr>
                    <th>N&#186;</th><th>Data</th><th>Fornecedor</th>
                    <th>Produtos</th><th>Total</th><th>Estado</th>
                    <th style="width:180px">A&#231;&#245;es</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr><td colspan="7" class="table-empty">
                    <div class="empty-state">
                        <div class="empty-icon">&#128230;</div>
                        <div class="empty-title">Sem encomendas</div>
                        <div class="empty-text">Crie a primeira encomenda acima.</div>
                    </div>
                </td></tr>
                <?php else: foreach ($orders as $order):
                    $items_of = $order_items_map[$order['id']] ?? [];
                    $bc = match($order['status']) {
                        'pending'=>'badge-warning','processed'=>'badge-primary',
                        'shipped'=>'badge-info','delivered'=>'badge-success',default=>'badge-muted'
                    };
                    $bi = match($order['status']) {
                        'pending'=>'Pendente','processed'=>'Processada','shipped'=>'Enviada','delivered'=>'Entregue',default=>''
                    };
                ?>
                <tr>
                    <td><strong>#<?= $order['id'] ?></strong></td>
                    <td style="white-space:nowrap;font-size:13px"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                    <td><strong><?= htmlspecialchars($order['supplier_name']) ?></strong></td>
                    <td>
                        <?php if (empty($items_of)): ?>
                            <span style="color:var(--text-muted);font-size:12px">&#8212;</span>
                        <?php else: ?>
                            <div style="font-size:13px;line-height:1.8;">
                            <?php foreach ($items_of as $it): ?>
                                <div>
                                    <span class="badge badge-primary" style="font-size:11px"><?= $it['quantity'] ?>&#215;</span>
                                    <?= htmlspecialchars($it['product_name']) ?>
                                    <span style="color:var(--text-muted);font-size:11px"> &#8364;<?= number_format($it['cost_price'], 2) ?>/un</span>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><strong style="color:var(--success)">&#8364;<?= number_format($order['total_cost'] ?? 0, 2) ?></strong></td>
                    <td><span class="badge <?= $bc ?>"><?= $bi ?></span></td>
                    <td>
                        <?php if ($order['status'] !== 'delivered'): ?>
                        <a href="?order_id=<?= $order['id'] ?>&status=delivered<?= $status_filter?'&status_filter='.$status_filter:'' ?>"
                           class="btn btn-success btn-sm"
                           onclick="return confirm('Marcar encomenda #<?= $order['id'] ?> como entregue e aumentar o stock?');">Entregue
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (($stats['pending'] ?? 0) > 0): ?>
    <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;">
        <a href="?deliver_all=1<?= $status_filter ? '&status_filter=' . urlencode($status_filter) : '' ?><?= $supplier_filter > 0 ? '&supplier_filter=' . intval($supplier_filter) : '' ?>"
           class="btn btn-success"
           onclick="return confirm('Marcar todas as encomendas pendentes como entregues e aumentar o stock?');">
            Marcar Todas como Entregues
        </a>
    </div>
    <?php endif; ?>
</div>

<style>
.badge-warning{background:#f59e0b22;color:#f59e0b}
.badge-info{background:#06b6d422;color:#06b6d4}
.badge-muted{background:var(--bg-tertiary);color:var(--text-muted)}
#itemsTable input[type="number"]{background:var(--bg-tertiary);border:1px solid var(--border);color:var(--text-primary);border-radius:var(--radius);padding:6px 10px;width:100%;font-size:14px}
#itemsTable select{background:var(--bg-tertiary);border:1px solid var(--border);color:var(--text-primary);border-radius:var(--radius);padding:6px 10px;width:100%;font-size:13px}
.remove-row-btn{background:none;border:none;color:var(--danger);cursor:pointer;font-size:18px;padding:4px 8px;border-radius:var(--radius);transition:background 0.2s}
.remove-row-btn:hover{background:#ef444422}
</style>

<script>
let supplierProducts = [];
const storeId = <?= $current_store_id ?>;

async function loadSupplierProducts() {
    const supplierId = document.getElementById('supplierSelect').value;
    ['noSupplierMsg','loadingMsg','noProductsMsg'].forEach(id => document.getElementById(id).style.display='none');
    document.getElementById('itemsSection').style.display='none';
    if (!supplierId) { document.getElementById('noSupplierMsg').style.display='block'; return; }
    document.getElementById('loadingMsg').style.display='block';
    try {
        const res  = await fetch(`/api/supplier-products.php?supplier_id=${supplierId}&store_id=${storeId}`);
        const data = await res.json();
        document.getElementById('loadingMsg').style.display='none';
        if (!data.success || data.products.length === 0) {
            document.getElementById('noProductsMsg').style.display='block'; return;
        }
        supplierProducts = data.products;
        document.getElementById('itemsBody').innerHTML='';
        addRow();
        document.getElementById('itemsSection').style.display='block';
    } catch(e) {
        document.getElementById('loadingMsg').style.display='none';
        document.getElementById('noSupplierMsg').style.display='block';
    }
}

function addRow() {
    let options = '<option value="">— Selecione —</option>';
    supplierProducts.forEach(p => {
        const bc = p.barcode ? ` [${p.barcode}]` : '';
        options += `<option value="${p.id}" data-price="${p.cost_price}">${p.name}${bc} — Stock: ${p.stock}</option>`;
    });
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><select name="product_id[]" required onchange="onProductChange(this)">${options}</select></td>
        <td><input type="number" name="qty[]" min="1" value="1" required oninput="recalcRow(this.closest('tr'));updateTotal()"></td>
        <td><input type="number" name="cost_price[]" step="0.01" min="0" value="0.00" oninput="recalcRow(this.closest('tr'));updateTotal()"></td>
        <td class="subtotal-cell" style="font-weight:600;color:var(--text-primary);">&#8364;0.00</td>
        <td><button type="button" class="remove-row-btn" onclick="removeRow(this)">&#10005;</button></td>
    `;
    document.getElementById('itemsBody').appendChild(row);
    updateTotal();
}

function onProductChange(select) {
    const price = parseFloat(select.options[select.selectedIndex].dataset.price)||0;
    const row = select.closest('tr');
    row.querySelector('input[name="cost_price[]"]').value = price.toFixed(2);
    recalcRow(row); updateTotal();
}

function recalcRow(row) {
    const qty = parseFloat(row.querySelector('input[name="qty[]"]').value)||0;
    const cp  = parseFloat(row.querySelector('input[name="cost_price[]"]').value)||0;
    row.querySelector('.subtotal-cell').textContent='\u20AC'+(qty*cp).toFixed(2);
}

function updateTotal() {
    let total=0;
    document.querySelectorAll('.subtotal-cell').forEach(c=>{total+=parseFloat(c.textContent.replace('\u20AC',''))||0;});
    document.getElementById('totalDisplay').textContent='\u20AC'+total.toFixed(2);
}

function removeRow(btn) {
    if(document.getElementById('itemsBody').rows.length<=1)return;
    btn.closest('tr').remove(); updateTotal();
}

function filterBySupplier(id) {
    const p=new URLSearchParams(window.location.search);
    if(id>0)p.set('supplier_filter',id);else p.delete('supplier_filter');
    window.location.search=p.toString();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
