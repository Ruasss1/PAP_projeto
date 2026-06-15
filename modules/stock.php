<?php
/**
 * GESTÃO DE STOCK - PREMIUM
 * Visualização moderna do inventário
 */

header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();
$current_store_id = get_current_store_id();
$message = null;

// Processar ajuste de stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'adjust') {
        $product_id = intval($_POST['product_id']);
        $adjustment = intval($_POST['adjustment']);
        $reason = trim($_POST['reason'] ?? 'Ajuste manual');
        
        if ($adjustment !== 0) {
            $ok = adjust_stock($product_id, $adjustment, $reason);
            $message = $ok ? 'Stock ajustado com sucesso!' : 'Erro ao ajustar stock';
        }
    }
}

// Filtros
$filter = $_GET['filter'] ?? 'all';
$category = $_GET['category'] ?? 'all';
$search = $_GET['search'] ?? '';

// Query base
$sql = "SELECT p.*, s.name as supplier_name 
        FROM products p 
        LEFT JOIN suppliers s ON p.supplier_id = s.id 
        WHERE p.store_id = ? AND p.active = 1";
$params = [$current_store_id];

// Aplicar filtros
if ($filter === 'low') {
    $sql .= " AND p.stock <= p.min_stock";
} elseif ($filter === 'out') {
    $sql .= " AND p.stock = 0";
} elseif ($filter === 'ok') {
    $sql .= " AND p.stock > p.min_stock";
}

if ($category !== 'all') {
    $sql .= " AND p.category = ?";
    $params[] = $category;
}

if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.barcode LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY p.stock ASC, p.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Obter categorias
$categories = $pdo->query("SELECT DISTINCT category FROM products WHERE store_id = $current_store_id AND category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Estatísticas
$stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN stock > 0 AND stock <= min_stock THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN stock > min_stock THEN 1 ELSE 0 END) as ok_stock,
        SUM(stock * sell_price) as total_value
    FROM products WHERE store_id = ? AND active = 1
");
$stats->execute([$current_store_id]);
$stats = $stats->fetch();

$page_title = 'Stock';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(5, 1fr);">
    <div class="stat-card" onclick="filterStock('all')" style="cursor:pointer">
        <div class="stat-header">
            <div class="stat-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= $stats['total'] ?></div>
        <div class="stat-label">Total Produtos</div>
    </div>
    <div class="stat-card" onclick="filterStock('ok')" style="cursor:pointer">
        <div class="stat-header">
            <div class="stat-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= $stats['ok_stock'] ?></div>
        <div class="stat-label">Stock OK</div>
    </div>
    <div class="stat-card" onclick="filterStock('low')" style="cursor:pointer">
        <div class="stat-header">
            <div class="stat-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= $stats['low_stock'] ?></div>
        <div class="stat-label">Stock Baixo</div>
    </div>
    <div class="stat-card" onclick="filterStock('out')" style="cursor:pointer">
        <div class="stat-header">
            <div class="stat-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= $stats['out_of_stock'] ?></div>
        <div class="stat-label">Sem Stock</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <div class="stat-value">€<?= number_format($stats['total_value'], 0, ',', '.') ?></div>
        <div class="stat-label">Valor Total</div>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-success fade-in">
    <span class="alert-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
    <div class="alert-content">
        <div class="alert-message"><?= htmlspecialchars($message) ?></div>
    </div>
</div>
<?php endif; ?>

<!-- Filtros -->
<div class="card" style="margin-bottom: 16px;">
    <div class="card-body">
        <form method="get" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <div class="search-box" style="flex: 1; min-width: 200px;">
                <svg class="search-icon" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" name="search" class="search-input" placeholder="Pesquisar produto..." value="<?= htmlspecialchars($search) ?>">
            </div>
            
            <select name="filter" class="form-select" style="width: auto;">
                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Todos</option>
                <option value="ok" <?= $filter === 'ok' ? 'selected' : '' ?>>Stock OK</option>
                <option value="low" <?= $filter === 'low' ? 'selected' : '' ?>>Stock Baixo</option>
                <option value="out" <?= $filter === 'out' ? 'selected' : '' ?>>Sem Stock</option>
            </select>
            
            <select name="category" class="form-select" style="width: auto;">
                <option value="all">Todas Categorias</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="/modules/stock.php" class="btn btn-secondary">Limpar</a>
        </form>
    </div>
</div>

<!-- Tabela de Stock -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Inventário (<?= count($products) ?> produtos)</h3>
        <a href="/modules/encomendas.php" class="btn btn-primary btn-sm">Nova Encomenda</a>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Categoria</th>
                    <th>Stock Atual</th>
                    <th>Stock Mínimo</th>
                    <th>Estado</th>
                    <th>Valor em Stock</th>
                    <th>Fornecedor</th>
                    <th style="width: 200px;">Ajustar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                <tr>
                    <td colspan="8" class="table-empty">
                        <div class="empty-state">
                            <div class="empty-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></div>
                            <div class="empty-title">Sem produtos</div>
                            <div class="empty-text">Nenhum produto encontrado com os filtros selecionados.</div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($products as $p): ?>
                <?php 
                    $is_out = $p['stock'] == 0;
                    $is_low = $p['stock'] > 0 && $p['stock'] <= $p['min_stock'];
                    $is_ok = $p['stock'] > $p['min_stock'];
                    $weighted_cats = ['Frutas', 'Legumes', 'Carnes', 'Peixe', 'Congelados'];
                    $is_weighted = in_array($p['category'], $weighted_cats);
                    $stock_unit = $is_weighted ? ' kg' : ' un';
                    $value = $p['stock'] * $p['sell_price'];
                ?>
                <tr<?= $is_out ? ' class="row-low-stock"' : ($is_low ? ' style="border-left: 2px solid rgba(250,204,21,0.4);"' : '') ?>>
                    <td>
                        <div>
                            <strong><?= htmlspecialchars($p['name']) ?></strong>
                            <div class="table-sub-text"><?= $p['barcode'] ?? '-' ?></div>
                        </div>
                    </td>
                    <td><span class="badge badge-purple"><?= htmlspecialchars($p['category'] ?? '-') ?></span></td>
                    <td>
                        <span style="font-size: 18px; font-weight: 700; font-family:'Plus Jakarta Sans',sans-serif;font-weight:800; <?= $is_out ? 'color: var(--danger);' : ($is_low ? 'color: var(--warning);' : 'color: var(--success);') ?>">
                            <?= $is_weighted ? number_format($p['stock'], 1) : (int)$p['stock'] ?><small style="font-size:11px;font-weight:500;opacity:0.7;"><?= $stock_unit ?></small>
                        </span>
                    </td>
                    <td><?= (int)$p['min_stock'] ?></td>
                    <td>
                        <?php if ($is_out): ?>
                        <span class="badge badge-danger">Sem Stock</span>
                        <?php elseif ($is_low): ?>
                        <span class="badge badge-warning">Stock Baixo</span>
                        <?php else: ?>
                        <span class="badge badge-success">OK</span>
                        <?php endif; ?>
                    </td>
                    <td>€<?= number_format($value, 2) ?></td>
                    <td><?= htmlspecialchars($p['supplier_name'] ?? '-') ?></td>
                    <td>
                        <form method="post" style="display: flex; gap: 8px; align-items: center;">
                            <input type="hidden" name="action" value="adjust">
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <input type="number" name="adjustment" class="form-input" style="width: 80px; padding: 6px 10px;" placeholder="+/-">
                            <button type="submit" class="btn btn-secondary btn-sm" title="Ajustar">OK</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function filterStock(type) {
    window.location.href = `?filter=${type}`;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>