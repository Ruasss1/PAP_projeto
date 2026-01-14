<?php
// modules/stock.php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

$message = null;

// Processa reabastecimento automático
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'auto_reorder') {
    $created = auto_reorder(5, intval($_POST['qty'] ?? 20));
    $message = 'Encomendas automáticas geradas: ' . count($created);
}

// Alertas de stock baixo
$alerts = low_stock_alerts(5);

// Lista encomendas pendentes (recebidas < quantidade encomendada)
$pdo = db_connect();
try {
    $orders_raw = $pdo->query('
        SELECT o.*, p.name as product_name, s.name as supplier_name
        FROM orders o
        JOIN products p ON o.product_id = p.id
        JOIN suppliers s ON o.supplier_id = s.id
        ORDER BY o.created_at DESC
    ')->fetchAll();
} catch (PDOException $e) {
    // If orders table has different structure or doesn't exist
    $orders_raw = [];
}

// Filtra em PHP para evitar erro quando a coluna se chama `qty` ou `quantity` ou não existe
$orders = array_values(array_filter($orders_raw, function($o) {
    $qty = $o['qty'] ?? ($o['quantity'] ?? null);
    return $qty !== null && $o['received'] < $qty;
}));

?>

<h1>Stock & Encomendas</h1>

<?php if (!empty($message)): ?>
    <p class="notice"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<h2>Alertas de Stock Baixo</h2>
<?php if (count($alerts) === 0): ?>
    <p>Sem alertas no momento.</p>
<?php else: ?>
    <ul>
        <?php foreach ($alerts as $a): ?>
            <li><?php echo htmlspecialchars($a['name']) . ' — stock: ' . $a['stock']; ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="action" value="auto_reorder">
    <label>Quantidade por encomenda <input type="number" name="qty" value="20"></label>
    <button type="submit">Gerar Encomendas Automáticas</button>
</form>

<?php if (count($orders) === 0): ?>
    <p>Sem encomendas pendentes.</p>
<?php else: ?>
    <h2>Encomendas Pendentes</h2>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Produto</th>
                <th>Fornecedor</th>
                <th>Qty</th>
                <th>Recebidas</th>
                <th>Custo</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><?php echo $o['id']; ?></td>
                    <td><?php echo htmlspecialchars($o['product_name']); ?></td>
                    <td><?php echo htmlspecialchars($o['supplier_name']); ?></td>
                    <td><?php echo $o['qty'] ?? ($o['quantity'] ?? 'N/A'); ?></td>
                    <td><?php echo $o['received']; ?></td>
                    <td><?php echo number_format($o['cost_price'], 2); ?>€</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
