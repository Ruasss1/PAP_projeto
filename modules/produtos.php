<?php
// Middleware de autenticação primeiro
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

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
<div class="table-container">
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
                    <td>
                        <a href="?action=edit&id=<?php echo $p['id']; ?>">Editar</a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Eliminar produto?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                            <button type="submit">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.low-stock { background: rgba(248, 113, 113, 0.1); }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

