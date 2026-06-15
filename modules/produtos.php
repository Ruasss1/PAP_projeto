<?php
/**
 * GESTÃO DE PRODUTOS - PREMIUM
 * Design moderno e elegante
 */

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->require_auth('produtos', 'view');

$pdo = db_connect();
$message = null;
$message_type = 'success';
$corner_toast_message = null;
$corner_toast_type = 'warning';

// Processar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $expiry_input = trim($_POST['expiry_date'] ?? '');
    $expiry_date = ($expiry_input === '') ? null : $expiry_input;
    
    if ($action === 'add') {
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'category' => trim($_POST['category'] ?? ''),
            'brand' => trim($_POST['brand'] ?? ''),
            'barcode' => trim($_POST['barcode'] ?? ''),
            'cost_price' => floatval($_POST['cost_price'] ?? 0),
            'sell_price' => floatval($_POST['sell_price'] ?? 0),
            'vat' => floatval($_POST['vat'] ?? 23),
            'stock' => intval($_POST['stock'] ?? 0),
            'min_stock' => intval($_POST['min_stock'] ?? 5),
            'reorder_qty' => intval($_POST['reorder_qty'] ?? 0) ?: null,
            'supplier_id' => intval($_POST['supplier_id'] ?? 0) ?: null,
            'expiry_date' => $expiry_date,
        ];
        $id = add_product($data);
        $message = $id ? 'Produto adicionado com sucesso!' : 'Erro ao adicionar produto';
        $message_type = $id ? 'success' : 'danger';
    
    } elseif ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'category' => trim($_POST['category'] ?? ''),
            'brand' => trim($_POST['brand'] ?? ''),
            'barcode' => trim($_POST['barcode'] ?? ''),
            'cost_price' => floatval($_POST['cost_price'] ?? 0),
            'sell_price' => floatval($_POST['sell_price'] ?? 0),
            'vat' => floatval($_POST['vat'] ?? 23),
            'stock' => intval($_POST['stock'] ?? 0),
            'min_stock' => intval($_POST['min_stock'] ?? 5),
            'reorder_qty' => intval($_POST['reorder_qty'] ?? 0) ?: null,
            'supplier_id' => intval($_POST['supplier_id'] ?? 0) ?: null,
            'expiry_date' => $expiry_date,
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
        $update_meta = [];
        $ok = $id > 0 && !empty($data['name']) ? update_product($id, $data, $update_meta) : false;
        $message = $ok ? 'Produto atualizado!' : 'Erro ao atualizar';
        $message_type = $ok ? 'success' : 'danger';

        if ($ok) {
            $auto = $update_meta['auto_orders'] ?? null;
            $orders_created = intval($auto['orders_created'] ?? 0);
            $items_created = intval($auto['items_created'] ?? 0);

            if ($orders_created > 0) {
                $message .= " Encomenda automática criada ({$orders_created} encomenda(s), {$items_created} produto(s)).";
                $corner_toast_message = "<svg width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z'/><polyline points='3.27 6.96 12 12.01 20.73 6.96'/><line x1='12' y1='22.08' x2='12' y2='12'/></svg> Encomenda automática criada ({$orders_created} encomenda(s), {$items_created} produto(s)).";
                $corner_toast_type = 'warning';
            }
        }
    
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $ok = $id > 0 ? delete_product($id) : false;
        $message = $ok ? 'Produto eliminado!' : 'Erro ao eliminar';
        $message_type = $ok ? 'success' : 'danger';
    }
}

// Obter dados
$products = list_products();
$suppliers = list_suppliers();
$all_categories = get_all_categories();
$all_brands = get_all_brands();

// Modo edição
$editing = false;
$edit_product = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editing = true;
    $edit_product = get_product(intval($_GET['id']));
    if (!$edit_product) {
        $editing = false;
        $message = 'Produto não encontrado para edição.';
        $message_type = 'danger';
    }
}

// Filtros
$selected_category = $_GET['category'] ?? 'all';
$selected_sort = $_GET['sort'] ?? 'name_az';
$search_query = trim($_GET['q'] ?? '');
$products = filter_products(
    $selected_category === 'all' ? null : $selected_category,
    $selected_sort
);

if ($search_query !== '') {
    $products = array_values(array_filter($products, function ($p) use ($search_query) {
        $q = mb_strtolower($search_query);
        return str_contains(mb_strtolower((string)($p['name'] ?? '')), $q)
            || str_contains(mb_strtolower((string)($p['barcode'] ?? '')), $q)
            || str_contains(mb_strtolower((string)($p['brand'] ?? '')), $q)
            || str_contains(mb_strtolower((string)($p['supplier_name'] ?? '')), $q);
    }));
}

// Estatísticas
$total_products = count($products);
$total_value = array_reduce($products, fn($c, $p) => $c + ($p['stock'] * $p['sell_price']), 0);
$low_stock = array_filter($products, fn($p) => $p['stock'] <= $p['min_stock']);
$low_stock_count = count($low_stock);

$page_title = 'Produtos';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.products-stats { grid-template-columns: repeat(4, 1fr); }
.products-form-grid-4 { grid-template-columns: repeat(4, 1fr); }
.products-form-grid-6 { grid-template-columns: repeat(6, 1fr); }
.products-form-grid-3 { grid-template-columns: repeat(3, 1fr); }
.products-actions { display: flex; gap: 12px; margin-top: 16px; }
.products-toolbar { display: flex; gap: 12px; align-items: center; }
.products-search { width: 240px; }
.products-table-wrap { border: none; border-radius: 0; }
.products-row-meta { display: flex; flex-direction: column; }
.products-row-actions { display: flex; gap: 8px; }
.products-reorder-help { font-size: 11px; color: var(--text-muted); }

@media (max-width: 1200px) {
    .products-form-grid-6 { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 900px) {
    .products-stats,
    .products-form-grid-4,
    .products-form-grid-3 { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .products-stats,
    .products-form-grid-4,
    .products-form-grid-6,
    .products-form-grid-3 { grid-template-columns: 1fr; }
    .products-toolbar { flex-wrap: wrap; }
    .products-search { width: 100%; }
}
</style>

<!-- Stats -->
<div class="stats-grid products-stats">
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon blue"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2zM16 3H8L4 7h16l-4-4z"/></svg></div>
        </div>
        <div class="stat-value"><?= $total_products ?></div>
        <div class="stat-label">Total Produtos</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon green"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg></div>
        </div>
        <div class="stat-value money-positive">€<?= number_format($total_value, 0, ',', '.') ?></div>
        <div class="stat-label">Valor em Stock</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon purple"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg></div>
        </div>
        <div class="stat-value"><?= count($all_categories) ?></div>
        <div class="stat-label">Categorias</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon <?= $low_stock_count > 0 ? 'red' : 'green' ?>"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg></div>
        </div>
        <div class="stat-value"><?= $low_stock_count ?></div>
        <div class="stat-label">Stock Baixo</div>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $message_type ?> fade-in">
    <span class="alert-icon"><?= $message_type === 'success' ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>' : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' ?></span>
    <div class="alert-content">
        <div class="alert-message"><?= htmlspecialchars($message) ?></div>
    </div>
</div>
<?php endif; ?>

<?php if ($corner_toast_message): ?>
<script>
window.addEventListener('load', function () {
    setTimeout(function () {
        if (typeof showToast === 'function') {
            showToast(<?= json_encode($corner_toast_message) ?>, <?= json_encode($corner_toast_type) ?>);
        }
    }, 250);
});
</script>
<?php endif; ?>

<!-- Formulário -->
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title"><?= $editing ? 'Editar Produto #'.((int)($edit_product['id'] ?? 0)) : 'Adicionar Produto' ?></h3>
        <?php if ($editing): ?>
        <a href="/modules/produtos.php" class="btn btn-secondary btn-sm">← Cancelar</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form method="post" id="product-form">
            <input type="hidden" name="action" value="<?= $editing ? 'update' : 'add' ?>">
            <?php if ($editing): ?>
            <input type="hidden" name="id" value="<?= $edit_product['id'] ?>">
            <?php endif; ?>
            
            <div class="form-row products-form-grid-4">
                <div class="form-group">
                    <label class="form-label">Nome *</label>
                    <input type="text" name="name" class="form-input" required
                           value="<?= $editing ? htmlspecialchars($edit_product['name']) : '' ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Categoria</label>
                    <select name="category" id="category-select" class="form-select" onchange="updateBrands()">
                        <option value="">Selecionar</option>
                        <?php foreach ($all_categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= ($editing && $edit_product['category'] === $cat) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Marca</label>
                    <select name="brand" id="brand-select" class="form-select">
                        <option value="">Selecionar</option>
                        <?php foreach ($all_brands as $brand): ?>
                        <option value="<?= htmlspecialchars($brand) ?>" <?= ($editing && ($edit_product['brand'] ?? '') === $brand) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($brand) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Código de Barras <?= !$editing ? '<span style="font-size:11px;color:var(--text-muted);font-weight:400;">(gerado automaticamente)</span>' : '' ?></label>
                    <input type="text" name="barcode" class="form-input"
                           value="<?= $editing ? htmlspecialchars($edit_product['barcode'] ?? '') : '' ?>"
                           <?= !$editing ? 'placeholder="Gerado ao guardar — ou insere manualmente"' : '' ?>>
                </div>
            </div>
            
            <div class="form-row products-form-grid-6">
                <div class="form-group">
                    <label class="form-label">Preço Custo</label>
                    <input type="number" name="cost_price" step="0.01" class="form-input"
                           value="<?= $editing ? $edit_product['cost_price'] : '0' ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Preço Venda</label>
                    <input type="number" name="sell_price" step="0.01" class="form-input"
                           value="<?= $editing ? $edit_product['sell_price'] : '0' ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">IVA %</label>
                    <select name="vat" class="form-select">
                        <?php $vat = $editing ? $edit_product['vat'] : 23; ?>
                        <option value="0" <?= $vat == 0 ? 'selected' : '' ?>>0% - Isento</option>
                        <option value="6" <?= $vat == 6 ? 'selected' : '' ?>>6% - Reduzido</option>
                        <option value="13" <?= $vat == 13 ? 'selected' : '' ?>>13% - Intermédio</option>
                        <option value="23" <?= $vat == 23 ? 'selected' : '' ?>>23% - Normal</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Stock</label>
                    <input type="number" name="stock" class="form-input"
                           value="<?= $editing ? (int)$edit_product['stock'] : '0' ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Stock Mínimo</label>
                    <input type="number" name="min_stock" class="form-input"
                           value="<?= $editing ? (int)$edit_product['min_stock'] : '5' ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Qtd. Encomenda Auto <span class="products-reorder-help">(vazio = auto)</span></label>
                    <input type="number" name="reorder_qty" class="form-input" min="1"
                           placeholder="Ex: 50"
                           value="<?= $editing ? ((int)($edit_product['reorder_qty'] ?? 0) ?: '') : '' ?>">
                </div>
            </div>
            
            <div class="form-row products-form-grid-3">
                <div class="form-group">
                    <label class="form-label">Fornecedor</label>
                    <select name="supplier_id" class="form-select">
                        <option value="">Sem fornecedor</option>
                        <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($editing && $edit_product['supplier_id'] == $s['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Data Validade</label>
                    <input type="date" name="expiry_date" class="form-input"
                           value="<?= $editing ? ($edit_product['expiry_date'] ?? '') : '' ?>">
                </div>
                <?php if ($editing): ?>
                <div class="form-group">
                    <label class="form-label">Estado</label>
                    <label style="display: flex; align-items: center; gap: 8px; margin-top: 8px; cursor: pointer;">
                        <input type="checkbox" name="active" <?= ($edit_product['active'] ?? 1) ? 'checked' : '' ?>>
                        <span>Produto Ativo</span>
                    </label>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="products-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $editing ? 'Guardar Alterações' : 'Adicionar Produto' ?>
                </button>
                <?php if ($editing): ?>
                <a href="/modules/produtos.php" class="btn btn-secondary">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Filtros e Tabela -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Lista de Produtos (<?= $total_products ?>)</h3>
        <div class="products-toolbar">
            <input id="filter-q" type="text" class="form-input products-search" placeholder="Pesquisar nome, código, marca..." value="<?= htmlspecialchars($search_query) ?>" onkeydown="if(event.key==='Enter'){applyFilters()}" />
            <select id="filter-category" class="form-select" style="width: auto;" onchange="applyFilters()">
                <option value="all" <?= $selected_category === 'all' ? 'selected' : '' ?>>Todas Categorias</option>
                <?php foreach ($all_categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>" <?= $selected_category === $cat ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select id="filter-sort" class="form-select" style="width: auto;" onchange="applyFilters()">
                <option value="name_az" <?= $selected_sort === 'name_az' ? 'selected' : '' ?>>Nome A-Z</option>
                <option value="name_za" <?= $selected_sort === 'name_za' ? 'selected' : '' ?>>Nome Z-A</option>
                <option value="price_low" <?= $selected_sort === 'price_low' ? 'selected' : '' ?>>Preço ↑</option>
                <option value="price_high" <?= $selected_sort === 'price_high' ? 'selected' : '' ?>>Preço ↓</option>
                <option value="stock_low" <?= $selected_sort === 'stock_low' ? 'selected' : '' ?>>Stock ↑</option>
                <option value="stock_high" <?= $selected_sort === 'stock_high' ? 'selected' : '' ?>>Stock ↓</option>
            </select>
        </div>
    </div>
    <div class="table-container products-table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Categoria</th>
                    <th>Stock</th>
                    <th>Preço Custo</th>
                    <th>Preço Venda</th>
                    <th>IVA</th>
                    <th>Fornecedor</th>
                    <th style="width: 140px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                <tr>
                    <td colspan="8" class="table-empty">
                        <div class="empty-state">
                            <div class="empty-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></div>
                            <div class="empty-title">Sem produtos</div>
                            <div class="empty-text">Adicione o primeiro produto usando o formulário acima.</div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($products as $p): ?>
                <?php $is_low = $p['stock'] <= $p['min_stock']; ?>
                <tr class="<?= $is_low ? 'row-low-stock' : '' ?>">
                    <td>
                        <div class="products-row-meta">
                            <strong><?= htmlspecialchars($p['name']) ?></strong>
                            <span class="table-sub-text">
                                <?= htmlspecialchars($p['barcode'] ?? 'Sem código') ?>
                                <?php if ($p['brand']): ?> • <?= htmlspecialchars($p['brand']) ?><?php endif; ?>
                            </span>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-purple"><?= htmlspecialchars($p['category'] ?? '-') ?></span>
                    </td>
                    <td>
                        <span class="<?= $is_low ? 'text-danger font-bold' : '' ?>">
                            <?= (int)$p['stock'] ?>
                            <?php if ($is_low): ?><span style="font-size: 11px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span><?php endif; ?>
                        </span>
                        <span class="table-sub-text">/ mín: <?= (int)$p['min_stock'] ?></span>
                    </td>
                    <td class="cost-value">€<?= number_format($p['cost_price'], 2) ?></td>
                    <td><strong class="money-positive">€<?= number_format($p['sell_price'], 2) ?></strong></td>
                    <td><?= $p['vat'] ?>%</td>
                    <td><?= htmlspecialchars($p['supplier_name'] ?? '-') ?></td>
                    <td>
                        <div class="products-row-actions">
                            <a href="?action=edit&id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">Editar</a>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Eliminar este produto?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function applyFilters() {
    const category = document.getElementById('filter-category').value;
    const sort = document.getElementById('filter-sort').value;
    const q = document.getElementById('filter-q')?.value || '';
    window.location.href = `?category=${encodeURIComponent(category)}&sort=${encodeURIComponent(sort)}&q=${encodeURIComponent(q)}`;
}

function updateBrands() {
    const category = document.getElementById('category-select').value;
    const brandSelect = document.getElementById('brand-select');
    
    if (!category) {
        brandSelect.innerHTML = '<option value="">Primeiro selecione categoria</option>';
        return;
    }
    
    brandSelect.innerHTML = '<option value="">A carregar...</option>';
    
    fetch('/api/brands-by-category.php?category=' + encodeURIComponent(category))
        .then(r => r.json())
        .then(data => {
            let options = '<option value="">Selecionar</option>';
            if (data.brands) {
                data.brands.forEach(b => options += `<option value="${b}">${b}</option>`);
            }
            brandSelect.innerHTML = options;
        })
        .catch(() => brandSelect.innerHTML = '<option value="">Erro</option>');
}
</script>

<style>
.mb-6 { margin-bottom: 24px; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>