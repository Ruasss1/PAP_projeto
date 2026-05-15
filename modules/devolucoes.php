<?php
/**
 * DEVOLUÇÕES E REEMBOLSOS
 */
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();
$current_store_id = get_current_store_id();
$page_title = 'Devoluções';
$error = '';
$success = '';

// ── Processar nova devolução ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_return') {
    $sale_id    = intval($_POST['sale_id'] ?? 0);
    $reason     = trim($_POST['reason'] ?? '');
    $items      = $_POST['items'] ?? [];   // [product_id => qty]

    if ($sale_id && $reason && !empty($items)) {
        try {
            $pdo->beginTransaction();

            // Verificar venda
            $sale = $pdo->prepare('SELECT * FROM sales WHERE id = ? AND store_id = ?');
            $sale->execute([$sale_id, $current_store_id]);
            $sale = $sale->fetch();
            if (!$sale) throw new Exception('Venda não encontrada.');

            // Calcular total a reembolsar
            $total_refund = 0;
            $items_to_return = [];
            foreach ($items as $product_id => $qty) {
                $qty = intval($qty);
                if ($qty <= 0) continue;
                $si = $pdo->prepare('SELECT si.*, p.name, p.sell_price FROM sale_items si JOIN products p ON p.id = si.product_id WHERE si.sale_id = ? AND si.product_id = ?');
                $si->execute([$sale_id, $product_id]);
                $si = $si->fetch();
                if (!$si) continue;
                if ($qty > $si['quantity']) $qty = $si['quantity'];
                $unit_price = $si['price'] ?? $si['sell_price'] ?? 0;
                $refund = round($qty * $unit_price, 2);
                $total_refund += $refund;
                $items_to_return[] = ['product_id' => $product_id, 'quantity' => $qty, 'unit_price' => $unit_price, 'refund_amount' => $refund, 'name' => $si['name']];
            }
            if (empty($items_to_return)) throw new Exception('Nenhum item selecionado.');

            // Número da devolução
            $return_num = 'DEV-' . date('Ymd') . '-' . str_pad($pdo->query('SELECT COALESCE(MAX(id),0)+1 FROM returns')->fetchColumn(), 4, '0', STR_PAD_LEFT);

            // Inserir devolução
            $pdo->prepare('INSERT INTO returns (return_number, original_receipt_id, user_id, reason, total_refund, status) VALUES (?,?,?,?,?,?)')
                ->execute([$return_num, $sale_id, $_SESSION['user_id'] ?? 1, $reason, $total_refund, 'approved']);
            $return_id = $pdo->lastInsertId();

            // Inserir itens e repor stock
            $si_stmt  = $pdo->prepare('INSERT INTO return_items (return_id, product_id, quantity, unit_price, refund_amount, reason) VALUES (?,?,?,?,?,?)');
            $stk_stmt = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
            $mov_stmt = $pdo->prepare('INSERT INTO stock_movements (product_id, type, qty, previous_stock, new_stock, reference_type, reference_id, notes) SELECT ?, "in", ?, stock - ?, stock, "return", ?, CONCAT("Devolução ", ?) FROM products WHERE id = ?');

            foreach ($items_to_return as $it) {
                $si_stmt->execute([$return_id, $it['product_id'], $it['quantity'], $it['unit_price'], $it['refund_amount'], $reason]);
                $prev = $pdo->prepare('SELECT stock FROM products WHERE id = ?'); $prev->execute([$it['product_id']]); $prev = $prev->fetchColumn();
                $stk_stmt->execute([$it['quantity'], $it['product_id']]);
                $mov_stmt->execute([$it['product_id'], $it['quantity'], $it['quantity'], $return_id, $return_num, $it['product_id']]);
            }

            // Audit log
            $pdo->prepare('INSERT INTO audit_logs (user_id, action, resource, resource_id, changes, ip_address, status) VALUES (?,?,?,?,?,?,?)')
                ->execute([$_SESSION['user_id'] ?? 1, 'create_return', 'returns', $return_id, json_encode(['return_number' => $return_num, 'total_refund' => $total_refund]), $_SERVER['REMOTE_ADDR'] ?? '', 'success']);

            $pdo->commit();
            $success = "Devolução {$return_num} criada com sucesso. Reembolso: €" . number_format($total_refund, 2, ',', '.');
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    } else {
        $error = 'Preencha todos os campos obrigatórios.';
    }
}

// ── Pesquisar venda para devolução ───────────────────────────────────────
$sale_to_return = null;
$sale_items_list = [];
if (isset($_GET['sale_id']) && intval($_GET['sale_id']) > 0) {
    $sid = intval($_GET['sale_id']);
    $stmt = $pdo->prepare('SELECT s.*, COUNT(si.id) as items_count FROM sales s LEFT JOIN sale_items si ON si.sale_id = s.id WHERE s.id = ? AND s.store_id = ? GROUP BY s.id');
    $stmt->execute([$sid, $current_store_id]);
    $sale_to_return = $stmt->fetch();
    if ($sale_to_return) {
        $stmt2 = $pdo->prepare('SELECT si.*, p.name, p.sell_price FROM sale_items si JOIN products p ON p.id = si.product_id WHERE si.sale_id = ?');
        $stmt2->execute([$sid]);
        $sale_items_list = $stmt2->fetchAll();
    }
}

// ── Lista de devoluções ──────────────────────────────────────────────────
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to   = $_GET['date_to']   ?? date('Y-m-d');

$returns_stmt = $pdo->prepare('SELECT r.*, u.name as user_name FROM returns r LEFT JOIN users u ON u.id = r.user_id WHERE DATE(r.created_at) BETWEEN ? AND ? ORDER BY r.created_at DESC LIMIT 100');
$returns_stmt->execute([$date_from, $date_to]);
$returns = $returns_stmt->fetchAll();

$stats = $pdo->prepare('SELECT COUNT(*) as total, COALESCE(SUM(total_refund),0) as total_refund FROM returns WHERE DATE(created_at) BETWEEN ? AND ?');
$stats->execute([$date_from, $date_to]);
$stats = $stats->fetch();

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.search-sale-box { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 24px; margin-bottom: 24px; }
.sale-preview { background: var(--bg-tertiary); border: 1px solid var(--border); border-radius: 10px; padding: 20px; margin-top: 20px; }
.item-row { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border); }
.item-row:last-child { border-bottom: none; }
.item-qty-input { width: 70px; padding: 6px 10px; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 6px; color: var(--text-primary); font-size: 14px; text-align: center; }
.return-badge { display: inline-flex; align-items: center; padding: 3px 8px; border-radius: 6px; font-size: 12px; font-weight: 600; background: var(--success-subtle, rgba(34,197,94,.1)); color: var(--success); }
</style>

<?php if ($error): ?><div class="alert alert-danger" style="margin-bottom:16px">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success" style="margin-bottom:16px">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(3,1fr); margin-bottom:24px">
    <div class="stat-card">
        <div class="stat-header"><div class="stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg></div></div>
        <div class="stat-value"><?= $stats['total'] ?></div>
        <div class="stat-label">Devoluções no período</div>
    </div>
    <div class="stat-card">
        <div class="stat-header"><div class="stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div></div>
        <div class="stat-value">€<?= number_format($stats['total_refund'], 2, ',', '.') ?></div>
        <div class="stat-label">Total reembolsado</div>
    </div>
    <div class="stat-card">
        <div class="stat-header"><div class="stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></div></div>
        <div class="stat-value">€<?= $stats['total'] > 0 ? number_format($stats['total_refund'] / $stats['total'], 2, ',', '.') : '0,00' ?></div>
        <div class="stat-label">Média por devolução</div>
    </div>
</div>

<!-- Pesquisar venda -->
<div class="search-sale-box">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:16px">Nova Devolução</h3>
    <form method="get" style="display:flex;gap:10px;align-items:flex-end">
        <div style="flex:1">
            <label class="form-label">Nº da Venda</label>
            <input type="number" name="sale_id" class="form-input" placeholder="Ex: 1234" value="<?= htmlspecialchars($_GET['sale_id'] ?? '') ?>" min="1">
        </div>
        <input type="hidden" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
        <input type="hidden" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
        <button type="submit" class="btn btn-primary">🔍 Pesquisar Venda</button>
    </form>

    <?php if ($sale_to_return): ?>
    <div class="sale-preview">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <div>
                <strong>Venda #<?= $sale_to_return['id'] ?></strong>
                <span style="color:var(--text-muted);margin-left:12px;font-size:13px"><?= date('d/m/Y H:i', strtotime($sale_to_return['sale_date'])) ?></span>
            </div>
            <strong>€<?= number_format($sale_to_return['total'], 2, ',', '.') ?></strong>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="create_return">
            <input type="hidden" name="sale_id" value="<?= $sale_to_return['id'] ?>">

            <div style="margin-bottom:16px">
                <label class="form-label">Motivo da Devolução *</label>
                <select name="reason" class="form-select" required>
                    <option value="">— Selecionar —</option>
                    <option value="Produto danificado">Produto danificado</option>
                    <option value="Produto incorreto">Produto incorreto</option>
                    <option value="Produto expirado">Produto expirado</option>
                    <option value="Arrependimento do cliente">Arrependimento do cliente</option>
                    <option value="Erro de faturação">Erro de faturação</option>
                    <option value="Outro">Outro</option>
                </select>
            </div>

            <div style="margin-bottom:16px">
                <label class="form-label">Itens a devolver (quantidade)</label>
                <?php foreach ($sale_items_list as $item): ?>
                <div class="item-row">
                    <input type="number" name="items[<?= $item['product_id'] ?>]" class="item-qty-input" min="0" max="<?= $item['quantity'] ?>" value="0">
                    <div style="flex:1">
                        <div style="font-weight:600;font-size:14px"><?= htmlspecialchars($item['name']) ?></div>
                        <div style="font-size:12px;color:var(--text-muted)">Qtd vendida: <?= $item['quantity'] ?> · €<?= number_format($item['price'] ?? 0, 2, ',', '.') ?>/un</div>
                    </div>
                    <div style="text-align:right;font-weight:600">€<?= number_format(($item['price'] ?? 0) * $item['quantity'], 2, ',', '.') ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn btn-primary">↩️ Processar Devolução</button>
        </form>
    </div>
    <?php elseif (isset($_GET['sale_id'])): ?>
    <div style="color:var(--danger);margin-top:16px">⚠️ Venda não encontrada nesta loja.</div>
    <?php endif; ?>
</div>

<!-- Filtros período -->
<div style="display:flex;gap:10px;align-items:flex-end;margin-bottom:20px">
    <form method="get" style="display:flex;gap:10px;align-items:flex-end">
        <div><label class="form-label">De</label><input type="date" name="date_from" class="form-input" value="<?= $date_from ?>"></div>
        <div><label class="form-label">Até</label><input type="date" name="date_to" class="form-input" value="<?= $date_to ?>"></div>
        <button type="submit" class="btn btn-secondary">Filtrar</button>
    </form>
</div>

<!-- Lista de devoluções -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Histórico de Devoluções</h3>
        <span style="font-size:12px;color:var(--text-muted)"><?= count($returns) ?> devoluções</span>
    </div>
    <div class="table-container">
        <table class="table">
            <thead><tr>
                <th>Nº Devolução</th><th>Venda Original</th><th>Data</th>
                <th>Motivo</th><th>Reembolso</th><th>Estado</th>
            </tr></thead>
            <tbody>
            <?php if (empty($returns)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:40px">Sem devoluções no período</td></tr>
            <?php else: foreach ($returns as $r): ?>
            <tr>
                <td><strong><?= htmlspecialchars($r['return_number']) ?></strong></td>
                <td><a href="?sale_id=<?= $r['original_receipt_id'] ?>" style="color:var(--text-primary);text-decoration:underline">#<?= $r['original_receipt_id'] ?></a></td>
                <td><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                <td><?= htmlspecialchars($r['reason']) ?></td>
                <td><strong>€<?= number_format($r['total_refund'], 2, ',', '.') ?></strong></td>
                <td><span class="return-badge">✓ <?= ucfirst($r['status']) ?></span></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
