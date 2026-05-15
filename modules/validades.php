<?php
/**
 * GESTÃO DE VALIDADES
 */
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();
$current_store_id = get_current_store_id();
$page_title = 'Validades';

// Atualizar data de validade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_expiry') {
    $pid    = intval($_POST['product_id']);
    $expiry = $_POST['expiry_date'] ?: null;
    $pdo->prepare('UPDATE products SET expiry_date = ? WHERE id = ? AND store_id = ?')->execute([$expiry, $pid, $current_store_id]);
    $pdo->prepare('INSERT INTO audit_logs (user_id,action,resource,resource_id,changes,ip_address,status) VALUES (?,?,?,?,?,?,?)')->execute([$_SESSION['user_id']??1,'update_expiry','products',$pid,json_encode(['expiry_date'=>$expiry]),$_SERVER['REMOTE_ADDR']??'','success']);
    $_SESSION['flash_message'] = 'Validade atualizada.'; $_SESSION['flash_type'] = 'success';
    header('Location: validades.php'); exit;
}

$filter = $_GET['filter'] ?? 'all'; // expired | warning | ok | all | no_date
$search = trim($_GET['search'] ?? '');
$today  = date('Y-m-d');
$warn   = date('Y-m-d', strtotime('+30 days'));

$q = "SELECT id, name, category, barcode, stock, expiry_date FROM products WHERE store_id = ? AND active = 1";
$par = [$current_store_id];

switch ($filter) {
    case 'expired': $q .= ' AND expiry_date < ?'; $par[] = $today; break;
    case 'warning': $q .= ' AND expiry_date BETWEEN ? AND ?'; $par[] = $today; $par[] = $warn; break;
    case 'ok':      $q .= ' AND expiry_date > ?'; $par[] = $warn; break;
    case 'no_date': $q .= ' AND (expiry_date IS NULL OR expiry_date = "")'; break;
}
if ($search) { $q .= ' AND name LIKE ?'; $par[] = "%$search%"; }
$q .= ' ORDER BY expiry_date ASC, name ASC';

$stmt = $pdo->prepare($q); $stmt->execute($par); $products = $stmt->fetchAll();

// Contagens para filtros
$counts = $pdo->prepare("SELECT
    SUM(CASE WHEN expiry_date < ? THEN 1 ELSE 0 END) as expired,
    SUM(CASE WHEN expiry_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as warning,
    SUM(CASE WHEN expiry_date > ? THEN 1 ELSE 0 END) as ok,
    SUM(CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END) as no_date,
    COUNT(*) as total
    FROM products WHERE store_id = ? AND active = 1");
$counts->execute([$today, $today, $warn, $warn, $current_store_id]);
$counts = $counts->fetch();

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.exp-expired { color: var(--danger); font-weight: 700; }
.exp-warning { color: var(--warning, #f59e0b); font-weight: 600; }
.exp-ok      { color: var(--success); }
.exp-none    { color: var(--text-muted); font-style: italic; }
.filter-tabs { display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
.filter-tab  { padding:7px 14px; border-radius:8px; font-size:13px; font-weight:500; text-decoration:none; color:var(--text-muted); background:var(--bg-tertiary); border:1px solid var(--border); transition:all .15s; }
.filter-tab:hover, .filter-tab.active { background:var(--bg-card,var(--bg-primary)); color:var(--text-primary); border-color:var(--border-light); }
.expiry-edit-form { display:flex; gap:6px; align-items:center; }
</style>

<!-- Stats cards -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
    <div class="stat-card" style="border-color:var(--danger)">
        <div class="stat-header"><div class="stat-icon red"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 9l-6 6M9 9l6 6"/></svg></div></div>
        <div class="stat-value exp-expired"><?= $counts['expired'] ?? 0 ?></div>
        <div class="stat-label">Expirados</div>
    </div>
    <div class="stat-card" style="border-color:var(--warning,#f59e0b)">
        <div class="stat-header"><div class="stat-icon orange"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg></div></div>
        <div class="stat-value exp-warning"><?= $counts['warning'] ?? 0 ?></div>
        <div class="stat-label">Expiram em 30 dias</div>
    </div>
    <div class="stat-card">
        <div class="stat-header"><div class="stat-icon green"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div></div>
        <div class="stat-value exp-ok"><?= $counts['ok'] ?? 0 ?></div>
        <div class="stat-label">Válidos</div>
    </div>
    <div class="stat-card">
        <div class="stat-header"><div class="stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16v-4m0-4h.01"/></svg></div></div>
        <div class="stat-value"><?= $counts['no_date'] ?? 0 ?></div>
        <div class="stat-label">Sem data definida</div>
    </div>
</div>

<!-- Filtros -->
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px">
    <div class="filter-tabs">
        <?php $tabs = ['all'=>'Todos ('.$counts['total'].')','expired'=>'Expirados ('.$counts['expired'].')','warning'=>'A expirar ('.$counts['warning'].')','ok'=>'Válidos ('.$counts['ok'].')','no_date'=>'Sem data ('.$counts['no_date'].')']; ?>
        <?php foreach ($tabs as $k => $label): ?>
        <a href="?filter=<?= $k ?>&search=<?= urlencode($search) ?>" class="filter-tab <?= $filter===$k?'active':'' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>
    <form method="get" style="display:flex;gap:8px">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <input type="text" name="search" class="form-input" placeholder="Pesquisar produto..." value="<?= htmlspecialchars($search) ?>" style="width:200px">
        <button type="submit" class="btn btn-secondary">🔍</button>
    </form>
</div>

<!-- Tabela -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Produtos — Validades</h3>
        <span style="font-size:12px;color:var(--text-muted)"><?= count($products) ?> produtos</span>
    </div>
    <div class="table-container">
        <table class="table">
            <thead><tr><th>Produto</th><th>Categoria</th><th>Código de barras</th><th>Stock</th><th>Validade</th><th>Estado</th><th>Atualizar</th></tr></thead>
            <tbody>
            <?php if (empty($products)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:40px">Sem produtos com este filtro</td></tr>
            <?php else: foreach ($products as $p):
                $exp = $p['expiry_date'];
                if (!$exp) { $cls = 'exp-none'; $label = 'Não definida'; }
                elseif ($exp < $today) { $cls = 'exp-expired'; $label = '⛔ Expirado'; $days = (int)((strtotime($today)-strtotime($exp))/86400); $label .= " há {$days}d"; }
                elseif ($exp <= $warn) { $cls = 'exp-warning'; $days = (int)((strtotime($exp)-strtotime($today))/86400); $label = "⚠️ Em {$days} dias"; }
                else { $cls = 'exp-ok'; $days = (int)((strtotime($exp)-strtotime($today))/86400); $label = "✅ Em {$days} dias"; }
            ?>
            <tr>
                <td><a href="/modules/produtos.php?action=edit&id=<?= $p['id'] ?>" style="color:var(--text-primary);font-weight:600;text-decoration:none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'"><?= htmlspecialchars($p['name']) ?></a></td>
                <td style="color:var(--text-muted)"><?= htmlspecialchars($p['category']) ?></td>
                <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($p['barcode'] ?? '—') ?></td>
                <td><?= $p['stock'] ?></td>
                <td class="<?= $cls ?>"><?= $exp ? date('d/m/Y', strtotime($exp)) : '—' ?></td>
                <td class="<?= $cls ?>"><?= $label ?></td>
                <td>
                    <form method="post" class="expiry-edit-form">
                        <input type="hidden" name="action" value="update_expiry">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <input type="date" name="expiry_date" class="form-input" value="<?= htmlspecialchars($exp ?? '') ?>" style="padding:4px 8px;font-size:12px;width:140px">
                        <button type="submit" class="btn btn-secondary" style="padding:4px 10px;font-size:12px">Guardar</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
