<?php
// modules/receipts.php - Recibos de venda com filtros por mês, categoria e pesquisa
session_start();
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/auth.php';
$auth->require_auth('precos', 'view');

require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();

$is_ajax = ($_GET['ajax'] ?? '') === '1';

if ($is_ajax) {
    ob_start();
} else {
    $page_title = 'Recibos';
    require_once __DIR__ . '/../includes/header.php';
}

// Garantir que a tabela existe
$tableExists = false;
try {
    $tableExists = (bool)$pdo->query("SHOW TABLES LIKE 'receipts'")->fetchColumn();
} catch (PDOException $e) {
    $tableExists = false;
}

if (!$tableExists) {
    echo '<h1>Recibos de Vendas</h1><p style="color:red;">A tabela receipts ainda não existe. Corre as migrações em /migrations/migrate.php?run=1.</p>';
    if (!$is_ajax) { require_once __DIR__ . '/../includes/footer.php'; }
    exit;
}

// Filtros
$selected_month = $_GET['month'] ?? '';
$search_receipt = trim($_GET['search'] ?? '');
$selected_category = $_GET['category'] ?? 'all';

if (!$is_ajax) {
    // Obter meses disponíveis com nomes de mês em português
    $monthsStmt = $pdo->query("SELECT DISTINCT DATE_FORMAT(r.created_at, '%Y-%m') as month_key
        FROM receipts r
        ORDER BY r.created_at DESC");
    $months = $monthsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $monthsList = [];
    $monthNames = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
                   'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    
    foreach ($months as $m) {
        list($year, $month) = explode('-', $m);
        $monthNum = (int)$month - 1;
        $monthName = $monthNames[$monthNum] ?? $month;
        $monthsList[$m] = $monthName . ' ' . $year;
    }

    // Obter categorias únicas
    $categoriesStmt = $pdo->query("SELECT DISTINCT p.category FROM sale_items si
        JOIN products p ON p.id = si.product_id
        WHERE p.category IS NOT NULL AND p.category != ''
        ORDER BY p.category ASC");
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);
}

// Construir query com filtros
$sql = 'SELECT r.id, r.receipt_number, r.total, r.payment_method, r.created_at, s.sale_date, r.sale_id
    FROM receipts r
    JOIN sales s ON s.id = r.sale_id
    WHERE 1=1';
$params = [];

if (!empty($selected_month)) {
    $sql .= ' AND DATE_FORMAT(r.created_at, "%Y-%m") = ?';
    $params[] = $selected_month;
}

if (!empty($search_receipt)) {
    $sql .= ' AND r.receipt_number LIKE ?';
    $params[] = '%' . $search_receipt . '%';
}

if ($selected_category !== 'all' && !empty($selected_category)) {
    $sql .= ' AND EXISTS (SELECT 1 FROM sale_items si JOIN products p ON p.id = si.product_id WHERE si.sale_id = r.sale_id AND p.category = ?)';
    $params[] = $selected_category;
}

$sql .= ' ORDER BY r.created_at DESC LIMIT 300';

$receiptsStmt = $pdo->prepare($sql);
$receiptsStmt->execute($params);
$receipts = $receiptsStmt->fetchAll(PDO::FETCH_ASSOC);

$saleIds = array_column($receipts, 'sale_id');
$itemMap = [];
if (!empty($saleIds)) {
    $placeholders = implode(',', array_fill(0, count($saleIds), '?'));
    $itemsStmt = $pdo->prepare("SELECT si.sale_id, p.name, p.category, si.quantity, si.price FROM sale_items si JOIN products p ON p.id = si.product_id WHERE si.sale_id IN ($placeholders)");
    $itemsStmt->execute($saleIds);
    while ($row = $itemsStmt->fetch(PDO::FETCH_ASSOC)) {
        $itemMap[$row['sale_id']][] = $row;
    }
}

// Se AJAX, retorna só a tabela
if ($is_ajax) {
    ob_clean();
    ?>
    <table>
        <thead>
            <tr>
                <th># Recibo</th>
                <th>Data</th>
                <th>Pagamento</th>
                <th>Total (€)</th>
                <th>Itens</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($receipts)): ?>
                <tr><td colspan="5" style="text-align: center; padding: 20px; color: #999;">Sem recibos encontrados.</td></tr>
            <?php else: ?>
                <?php foreach ($receipts as $r): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($r['receipt_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($r['sale_date']))); ?></td>
                        <td><?php echo htmlspecialchars($r['payment_method'] ?? ''); ?></td>
                        <td><strong style="color: #10b981;">€ <?php echo number_format($r['total'], 2); ?></strong></td>
                        <td>
                            <?php if (!empty($itemMap[$r['sale_id']])): ?>
                                <ul style="margin: 0; padding-left: 16px; font-size: 12px;">
                                <?php foreach ($itemMap[$r['sale_id']] as $it): ?>
                                    <li><span style="color: #a78bfa; font-weight: 500;">[<?php echo htmlspecialchars($it['category']); ?>]</span> <?php echo htmlspecialchars($it['name']); ?> ×<?php echo (int)$it['quantity']; ?> = €<?php echo number_format($it['price'] * $it['quantity'], 2); ?></li>
                                <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <span style="color: #999;">Sem itens</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
    exit;
}
?>

<h1> Recibos de Vendas</h1>

<!-- Filtros -->
<div style="display: flex; gap: 20px; margin: 20px 0; padding: 15px; background: linear-gradient(135deg, #1f2937 0%, #111827 100%); border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.2);">
    <div style="flex: 1;">
        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: white;">Mês</label>
        <select id="month-filter" onchange="updateFiltersAjax()" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background: white; cursor: pointer; color: black;">
            <option value="">Todos</option>
            <?php foreach ($monthsList as $key => $label): ?>
                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $selected_month === $key ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div style="flex: 1;">
        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: white;">Categoria</label>
        <select id="category-filter" onchange="updateFiltersAjax()" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background: white; cursor: pointer; color: black;">
            <option value="all">Todas</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $selected_category === $cat ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div style="flex: 1;">
        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: white;">Pesquisa</label>
        <input type="text" id="search-filter" placeholder="Nº recibo..." value="<?php echo htmlspecialchars($search_receipt); ?>" onkeyup="updateFiltersAjax()" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background: white; color: black;">
    </div>
</div>

<script>
function updateFiltersAjax() {
    const monthFilter = document.getElementById('month-filter').value;
    const categoryFilter = document.getElementById('category-filter').value;
    const searchFilter = document.getElementById('search-filter').value;
    const tableContainer = document.getElementById('receipts-table');

    const params = new URLSearchParams();
    params.append('ajax', '1');
    if (monthFilter) params.append('month', monthFilter);
    if (categoryFilter && categoryFilter !== 'all') params.append('category', categoryFilter);
    if (searchFilter) params.append('search', searchFilter);

    fetch('/modules/receipts.php?' + params.toString())
        .then(res => res.text())
        .then(html => {
            tableContainer.innerHTML = html;
        })
        .catch(() => {
            window.location.href = '/modules/receipts.php?' + params.toString();
        });
}
</script>

<div id="receipts-table" class="table-container" style="overflow-x: auto;">
    <table>
        <thead>
            <tr>
                <th># Recibo</th>
                <th>Data</th>
                <th>Pagamento</th>
                <th>Total (€)</th>
                <th>Itens</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($receipts)): ?>
                <tr><td colspan="5" style="text-align: center; padding: 20px; color: #999;">Sem recibos encontrados.</td></tr>
            <?php else: ?>
                <?php foreach ($receipts as $r): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($r['receipt_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($r['sale_date']))); ?></td>
                        <td><?php echo htmlspecialchars($r['payment_method'] ?? ''); ?></td>
                        <td><strong style="color: #10b981;">€ <?php echo number_format($r['total'], 2); ?></strong></td>
                        <td>
                            <?php if (!empty($itemMap[$r['sale_id']])): ?>
                                <ul style="margin: 0; padding-left: 16px; font-size: 12px;">
                                <?php foreach ($itemMap[$r['sale_id']] as $it): ?>
                                    <li><span style="color: #a78bfa; font-weight: 500;">[<?php echo htmlspecialchars($it['category']); ?>]</span> <?php echo htmlspecialchars($it['name']); ?> ×<?php echo (int)$it['quantity']; ?> = €<?php echo number_format($it['price'] * $it['quantity'], 2); ?></li>
                                <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <span style="color: #999;">Sem itens</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
