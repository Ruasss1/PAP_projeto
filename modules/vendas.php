<?php
// modules/vendas.php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

$products = list_products();
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'], $_POST['qty'])) {
    $product_id = (int)$_POST['product_id'];
    $qty = (int)$_POST['qty'];
    $iva_rate = floatval($_POST['iva'] ?? 0);
    $product = get_product($product_id);
    if (!$product) {
        $message = 'Produto não encontrado';
    } else {
        // add_sale expects an array of items, not individual parameters
        $items = [
            [
                'product_id' => $product_id,
                'quantity' => $qty
            ]
        ];
        $payment_method = 'Dinheiro'; // Default payment method
        $res = add_sale($items, $payment_method);
        if (is_numeric($res)) {
            $message = 'Venda #' . $res . ' registada com sucesso (IVA: ' . ($iva_rate*100) . '%)';
        } else {
            $message = 'Erro: ' . $res;
        }
    }
}
?>
<h1>Vendas</h1>
<?php if (!empty($message)): ?><p class="notice"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<form method="post">
    <label>Produto
        <select name="product_id">
            <?php foreach ($products as $p): ?>
                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?> (Stock: <?php echo $p['stock']; ?>)</option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Quantidade <input type="number" name="qty" value="1" min="1"></label>
    <label>IVA
        <select name="iva">
            <option value="0">0%</option>
            <option value="0.06">6%</option>
            <option value="0.13">13%</option>
            <option value="0.23" selected>23%</option>
        </select>
    </label>
    <button type="submit">Registar Venda</button>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>