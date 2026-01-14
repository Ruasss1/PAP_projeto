<?php
// Middleware de autenticação primeiro
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$is_ajax = ($_GET['ajax'] ?? '') === '1';

if ($is_ajax) {
    ob_start(); // buffer to limpar HTML anterior em resposta AJAX
}

if (!$is_ajax) {
    require_once __DIR__ . '/../includes/header.php';
}

// Validar permissão para este módulo
$auth->require_auth('produtos', 'view');

$pdo = db_connect();

$message = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
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
            'supplier_id' => intval($_POST['supplier_id'] ?? 0) ?: null,
            'expiry_date' => $_POST['expiry_date'] ?? null,
        ];
        $id = add_product($data);
        $message = $id ? 'Produto adicionado com sucesso' : 'Erro ao adicionar produto';
    } elseif ($action === 'update') {
        $id = intval($_POST['id']);
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
            'supplier_id' => intval($_POST['supplier_id'] ?? 0) ?: null,
            'expiry_date' => $_POST['expiry_date'] ?? null,
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
        $ok = update_product($id, $data);
        $message = $ok ? 'Produto atualizado' : 'Erro ao atualizar';
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        $ok = delete_product($id);
        $message = $ok ? 'Produto eliminado' : 'Erro ao eliminar';
    } elseif ($action === 'adjust_stock') {
        $id = intval($_POST['id']);
        $qty = intval($_POST['adjust_qty'] ?? 0);
        $reason = trim($_POST['adjust_reason'] ?? 'Ajuste manual');
        $ok = adjust_stock($id, $qty, $reason);
        $message = $ok ? 'Stock ajustado' : 'Erro ao ajustar stock';
    }
}

$products = list_products();
$suppliers = list_suppliers();
$editing = false;
$edit_product = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editing = true;
    $edit_product = get_product(intval($_GET['id']));
}

// Get filter parameters
$selected_category = $_GET['category'] ?? 'all';
$selected_sort = $_GET['sort'] ?? 'name_az';

// Apply filters
$all_categories = get_all_categories();
$products = filter_products(
    $selected_category === 'all' ? null : $selected_category,
    $selected_sort
);
?>
<h1>Produtos</h1>
<?php if (!empty($message)): ?><p class="notice"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>

<section class="forms">
    <?php if ($editing && $edit_product): ?>
        <h2>Editar Produto #<?php echo $edit_product['id']; ?></h2>
        <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $edit_product['id']; ?>">
            
            <label>Nome <input name="name" value="<?php echo htmlspecialchars($edit_product['name']); ?>" required></label>
            <label>Categoria <input name="category" value="<?php echo htmlspecialchars($edit_product['category']); ?>"></label>
            <label>Marca <input name="brand" value="<?php echo htmlspecialchars($edit_product['brand'] ?? ''); ?>"></label>
            <label>Barcode <input name="barcode" value="<?php echo htmlspecialchars($edit_product['barcode'] ?? ''); ?>"></label>
            <label>Preço Custo <input name="cost_price" type="number" step="0.01" value="<?php echo $edit_product['cost_price']; ?>"></label>
            <label>Preço Venda <input name="sell_price" type="number" step="0.01" value="<?php echo $edit_product['sell_price']; ?>"></label>
            <label>IVA % <input name="vat" type="number" step="0.01" value="<?php echo $edit_product['vat']; ?>"></label>
            <label>Stock <input type="number" name="stock" value="<?php echo (int)$edit_product['stock']; ?>"></label>
            <label>Stock Mínimo <input type="number" name="min_stock" value="<?php echo (int)$edit_product['min_stock']; ?>"></label>
            <label>Fornecedor
                <select name="supplier_id">
                    <option value="">Sem fornecedor</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo ($edit_product['supplier_id'] == $s['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Data Validade <input type="date" name="expiry_date" value="<?php echo htmlspecialchars($edit_product['expiry_date'] ?? ''); ?>"></label>
            <label>Ativo <input type="checkbox" name="active" <?php echo ($edit_product['active'] ?? 1) ? 'checked' : ''; ?>></label>
            <button type="submit">Guardar</button>
            <a class="btn" href="/modules/produtos.php">Cancelar</a>
        </form>
    <?php else: ?>
        <h2>Adicionar Produto</h2>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <label>Nome <input name="name" required></label>
            <label>Categoria <input name="category"></label>
            <label>Marca <input name="brand"></label>
            <label>Barcode <input name="barcode"></label>
            <label>Preço Custo <input name="cost_price" type="number" step="0.01" value="0"></label>
            <label>Preço Venda <input name="sell_price" type="number" step="0.01" value="0"></label>
            <label>IVA % <input name="vat" type="number" step="0.01" value="23"></label>
            <label>Stock <input type="number" name="stock" value="0"></label>
            <label>Stock Mínimo <input type="number" name="min_stock" value="5"></label>
            <label>Fornecedor
                <select name="supplier_id">
                    <option value="">Sem fornecedor</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Data Validade <input type="date" name="expiry_date"></label>
            <button type="submit">Adicionar</button>
        </form>
    <?php endif; ?>
</section>

<h2>Lista de Produtos (<?php echo count($products); ?>)</h2>

<!-- Filtros -->
<div style="display: flex; gap: 20px; margin: 20px 0; padding: 15px; background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%); border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.2);">
    <div style="flex: 1;">
        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: black;">📂 Categoria</label>
        <select id="category-filter" name="category" onchange="updateFiltersAjax()" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background: white; cursor: pointer; color: black;">
            <option value="all" <?php echo $selected_category === 'all' ? 'selected' : ''; ?>>Todas</option>
            <?php foreach ($all_categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $selected_category === $cat ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div style="flex: 1;">
        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: black;">🔄 Ordenar por</label>
        <select id="sort-filter" name="sort" onchange="updateFiltersAjax()" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background: white; cursor: pointer; color: black;">
            <option value="name_az" <?php echo $selected_sort === 'name_az' ? 'selected' : ''; ?>>A-Z</option>
            <option value="name_za" <?php echo $selected_sort === 'name_za' ? 'selected' : ''; ?>>Z-A</option>
            <option value="price_low" <?php echo $selected_sort === 'price_low' ? 'selected' : ''; ?>>💰 Mais Barato</option>
            <option value="price_high" <?php echo $selected_sort === 'price_high' ? 'selected' : ''; ?>>💸 Mais Caro</option>
            <option value="stock_low" <?php echo $selected_sort === 'stock_low' ? 'selected' : ''; ?>>📉 Menos Stock</option>
            <option value="stock_high" <?php echo $selected_sort === 'stock_high' ? 'selected' : ''; ?>>📈 Mais Stock</option>
        </select>
    </div>
</div>

<script>
function updateFiltersAjax() {
    const categoryFilter = document.getElementById('category-filter').value;
    const sortFilter = document.getElementById('sort-filter').value;
    const tableContainer = document.getElementById('products-table');

    const params = new URLSearchParams();
    params.append('ajax', '1');
    params.append('category', categoryFilter);
    params.append('sort', sortFilter);

    fetch('/modules/produtos.php?' + params.toString())
        .then(res => res.text())
        .then(html => {
            tableContainer.innerHTML = html;
        })
        .catch(() => {
            window.location.href = '/modules/produtos.php?' + params.toString();
        });
}
</script>
<?php ob_start(); ?>
<div id="products-table" class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Categoria</th>
                <th>Marca</th>
                <th>Barcode</th>
                <th>Stock</th>
                <th>Mín.</th>
                <th>Preço Venda</th>
                <th>Custo</th>
                <th>IVA</th>
                <th>Fornecedor</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
                <?php $low_stock = $p['stock'] <= $p['min_stock']; ?>
                <tr <?php echo $low_stock ? 'class="low-stock"' : ''; ?>>
                    <td><?php echo $p['id']; ?></td>
                    <td><?php echo htmlspecialchars($p['name']); ?></td>
                    <td><?php echo htmlspecialchars($p['category'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($p['brand'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($p['barcode'] ?? '-'); ?></td>
                    <td class="<?php echo $low_stock ? 'negative' : ''; ?>"><?php echo (int)$p['stock']; ?></td>
                    <td><?php echo (int)$p['min_stock']; ?></td>
                    <td><?php echo number_format($p['sell_price'], 2); ?>€</td>
                    <td><?php echo number_format($p['cost_price'], 2); ?>€</td>
                    <td><?php echo $p['vat']; ?>%</td>
                    <td><?php echo htmlspecialchars($p['supplier_name'] ?? '-'); ?></td>
                    <td style="text-align: center; display: flex; gap: 8px; justify-content: center;">
                        <a href="?action=edit&id=<?php echo $p['id']; ?>" style="display: inline-block; padding: 8px 14px; background: #3b82f6; color: white; text-decoration: none; border-radius: 5px; font-size: 12px; cursor: pointer; font-weight: 500; transition: background 0.2s;">✏️ Editar</a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Eliminar produto?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                            <button type="submit" style="padding: 8px 14px; background: #ef4444; color: white; border: none; border-radius: 5px; font-size: 12px; cursor: pointer; font-weight: 500; transition: background 0.2s;">🗑️ Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
        </tbody>
    </table>
</div>
<?php
$table_html = ob_get_clean();

if ($is_ajax) {
    ob_clean();
    echo $table_html;
    exit;
}

echo $table_html;
?>

<style>
.low-stock { background: rgba(248, 113, 113, 0.1); }
</style>

<?php if (!$is_ajax) { require_once __DIR__ . '/../includes/footer.php'; } ?>

