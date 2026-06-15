<?php
/**
 * MOVIMENTOS DE STOCK
 */
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();
$current_store_id = get_current_store_id();
$page_title = 'Movimentos de Stock';

// Filtros
$date_from  = $_GET['date_from']  ?? date('Y-m-d', strtotime('-30 days'));
$date_to    = $_GET['date_to']    ?? date('Y-m-d');
$type       = $_GET['type']       ?? '';
$product_id = intval($_GET['product_id'] ?? 0);
$search     = trim($_GET['search'] ?? '');

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="movimentos_stock_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    fputcsv($out, ['Data','Produto','Categoria','Tipo','Quantidade','Stock Anterior','Stock Novo','Referência','Notas'], ';');
    $q = "SELECT sm.*, p.name as product_name, p.category FROM stock_movements sm JOIN products p ON p.id = sm.product_id WHERE p.store_id = ? AND DATE(sm.created_at) BETWEEN ? AND ?";
    $par = [$current_store_id, $date_from, $date_to];
    if ($type) { $q .= ' AND sm.type = ?'; $par[] = $type; }
    if ($product_id) { $q .= ' AND sm.product_id = ?'; $par[] = $product_id; }
    $q .= ' ORDER BY sm.created_at DESC';
    $st = $pdo->prepare($q); $st->execute($par);
    while ($row = $st->fetch()) {
        fputcsv($out, [
            date('d/m/Y H:i', strtotime($row['created_at'])),
            $row['product_name'], $row['category'],
            $row['type'] === 'in' ? 'Entrada' : 'Saída',
            $row['qty'], $row['previous_stock'], $row['new_stock'],
            $row['reference_type'] . '#' . $row['reference_id'],
            $row['notes']
        ], ';');
    }
    fclose($out); exit;
}

// Ajuste manual de stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'adjust') {
    $pid   = intval($_POST['product_id']);
    $qty   = intval($_POST['qty']);
    $mtype = $_POST['type'] === 'in' ? 'in' : 'out';
    $notes = trim($_POST['notes'] ?? '');
    if ($pid && $qty > 0) {
        $prev = $pdo->prepare('SELECT stock FROM products WHERE id = ? AND store_id = ?');
        $prev->execute([$pid, $current_store_id]); $prev = $prev->fetchColumn();
        if ($prev !== false) {
            $new = $mtype === 'in' ? $prev + $qty : max(0, $prev - $qty);
            $pdo->prepare('UPDATE products SET stock = ? WHERE id = ?')->execute([$new, $pid]);
            $pdo->prepare('INSERT INTO stock_movements (product_id, type, qty, previous_stock, new_stock, reference_type, notes) VALUES (?,?,?,?,?,"manual",?)')->execute([$pid, $mtype, $qty, $prev, $new, $notes ?: 'Ajuste manual']);
            $pdo->prepare('INSERT INTO audit_logs (user_id,action,resource,resource_id,changes,ip_address,status) VALUES (?,?,?,?,?,?,?)')->execute([$_SESSION['user_id'] ?? 1, 'stock_adjust', 'products', $pid, json_encode(['type'=>$mtype,'qty'=>$qty,'from'=>$prev,'to'=>$new]), $_SERVER['REMOTE_ADDR']??'', 'success']);

            $auto = create_auto_orders_for_low_stock_products([$pid]);
            // Also check all other low-stock products that might need orders
            $all_low = $pdo->query("SELECT id FROM products WHERE stock <= min_stock AND active=1 AND supplier_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($all_low)) {
                $extra = create_auto_orders_for_low_stock_products($all_low);
                $auto['orders_created'] = intval($auto['orders_created'] ?? 0) + intval($extra['orders_created'] ?? 0);
                $auto['items_created']  = intval($auto['items_created'] ?? 0)  + intval($extra['items_created'] ?? 0);
            }
            $orders_created = intval($auto['orders_created'] ?? 0);
            $items_created = intval($auto['items_created'] ?? 0);

            $_SESSION['flash_message'] = 'Stock ajustado com sucesso.';
            if ($orders_created > 0) {
                $_SESSION['flash_message'] .= " Encomenda automática criada ({$orders_created} encomenda(s), {$items_created} produto(s)).";
            }
            $_SESSION['flash_type'] = 'success';
        }
    }
    header('Location: movimentos_stock.php'); exit;
}

// Listar produtos para select
$prods = $pdo->prepare('SELECT id, name, category, stock FROM products WHERE store_id = ? AND active = 1 ORDER BY name');
$prods->execute([$current_store_id]); $prods = $prods->fetchAll();

// Query movimentos
$q = "SELECT sm.*, p.name as product_name, p.category FROM stock_movements sm JOIN products p ON p.id = sm.product_id WHERE p.store_id = ? AND DATE(sm.created_at) BETWEEN ? AND ?";
$par = [$current_store_id, $date_from, $date_to];
if ($type) { $q .= ' AND sm.type = ?'; $par[] = $type; }
if ($product_id) { $q .= ' AND sm.product_id = ?'; $par[] = $product_id; }
if ($search) { $q .= ' AND p.name LIKE ?'; $par[] = "%$search%"; }
$q .= ' ORDER BY sm.created_at DESC LIMIT 500';
$stmt = $pdo->prepare($q); $stmt->execute($par); $movements = $stmt->fetchAll();

// Stats
$sq = "SELECT COUNT(*) as total, COALESCE(SUM(CASE WHEN sm.type='in' THEN sm.qty ELSE 0 END),0) as total_in, COALESCE(SUM(CASE WHEN sm.type='out' THEN sm.qty ELSE 0 END),0) as total_out FROM stock_movements sm JOIN products p ON p.id = sm.product_id WHERE p.store_id = ? AND DATE(sm.created_at) BETWEEN ? AND ?";
$ss = $pdo->prepare($sq); $ss->execute([$current_store_id, $date_from, $date_to]); $mv_stats = $ss->fetch();

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.type-in  { color: var(--success); font-weight: 600; }
.type-out { color: var(--danger);  font-weight: 600; }
</style>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
    <div class="stat-card"><div class="stat-header"><div class="stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></div></div><div class="stat-value"><?= $mv_stats['total'] ?></div><div class="stat-label">Total de movimentos</div></div>
    <div class="stat-card"><div class="stat-header"><div class="stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></div></div><div class="stat-value stat-value--success" style="color:var(--success)"><?= $mv_stats['total_in'] ?></div><div class="stat-label">Entradas (unidades)</div></div>
    <div class="stat-card"><div class="stat-header"><div class="stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg></div></div><div class="stat-value" style="color:var(--danger)"><?= $mv_stats['total_out'] ?></div><div class="stat-label">Saídas (unidades)</div></div>
</div>

<!-- Ajuste Manual -->
<div class="card" style="margin-bottom:24px">
    <div class="card-header"><h3 class="card-title">Ajuste Manual de Stock</h3></div>
    <div class="card-body">
        <form method="post" style="display:grid;grid-template-columns:2fr 1fr 1fr 2fr auto;gap:12px;align-items:flex-end">
            <input type="hidden" name="action" value="adjust">
            <div>
                <label class="form-label">Produto</label>
                <select name="product_id" class="form-select" required>
                    <option value="">— Selecionar produto —</option>
                    <?php foreach ($prods as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (stock: <?= $p['stock'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Tipo</label>
                <select name="type" class="form-select" required>
                    <option value="in"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Entrada</option>
                    <option value="out"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> Saída</option>
                </select>
            </div>
            <div>
                <label class="form-label">Quantidade</label>
                <input type="number" name="qty" class="form-input" min="1" value="1" required>
            </div>
            <div>
                <label class="form-label">Notas</label>
                <input type="text" name="notes" class="form-input" placeholder="Motivo do ajuste">
            </div>
            <button type="submit" class="btn btn-primary" style="white-space:nowrap">Registar</button>
        </form>
    </div>
</div>

<!-- Filtros -->
<form method="get" style="display:flex;gap:10px;align-items:flex-end;margin-bottom:20px;flex-wrap:wrap">
    <div><label class="form-label">De</label><input type="date" name="date_from" class="form-input" value="<?= $date_from ?>"></div>
    <div><label class="form-label">Até</label><input type="date" name="date_to" class="form-input" value="<?= $date_to ?>"></div>
    <div>
        <label class="form-label">Tipo</label>
        <select name="type" class="form-select">
            <option value="">Todos</option>
            <option value="in" <?= $type==='in'?'selected':'' ?>>Entradas</option>
            <option value="out" <?= $type==='out'?'selected':'' ?>>Saídas</option>
        </select>
    </div>
    <div><label class="form-label">Produto</label><input type="text" name="search" class="form-input" placeholder="Nome..." value="<?= htmlspecialchars($search) ?>"></div>
    <button type="submit" class="btn btn-secondary">Filtrar</button>
    <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>" class="btn btn-secondary"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg> CSV</a>
</form>

<!-- Tabela -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Movimentos</h3>
        <span style="font-size:12px;color:var(--text-muted)"><?= count($movements) ?> registos</span>
    </div>
    <div class="table-container">
        <table class="table">
            <thead><tr><th>Data</th><th>Produto</th><th>Categoria</th><th>Tipo</th><th>Qtd</th><th>Stock Anterior</th><th>Stock Novo</th><th>Origem</th><th>Notas</th></tr></thead>
            <tbody>
            <?php if (empty($movements)): ?>
            <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:40px">Sem movimentos no período</td></tr>
            <?php else: foreach ($movements as $m): ?>
            <tr>
                <td style="white-space:nowrap"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
                <td><?= htmlspecialchars($m['product_name']) ?></td>
                <td style="color:var(--text-muted)"><?= htmlspecialchars($m['category']) ?></td>
                <td class="type-<?= $m['type'] ?>"><?= $m['type']==='in'?'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Entrada':'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> Saída' ?></td>
                <td><strong><?= $m['qty'] ?></strong></td>
                <td style="color:var(--text-muted)"><?= $m['previous_stock'] ?></td>
                <td><strong><?= $m['new_stock'] ?></strong></td>
                <td style="font-size:12px;color:var(--text-muted)"><?= ucfirst($m['reference_type']) ?><?= $m['reference_id'] ? ' #'.$m['reference_id'] : '' ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($m['notes'] ?? '') ?></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
