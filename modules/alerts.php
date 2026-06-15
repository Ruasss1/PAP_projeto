<?php
/**
 * ALERTAS - PREMIUM
 * Central de alertas e notificações
 */

header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();
$current_store_id = get_current_store_id();

// Marcar como lida
if (isset($_GET['mark_read'])) {
    $pdo->prepare("UPDATE alerts SET is_read = 1 WHERE id = ? AND store_id = ?")->execute([intval($_GET['mark_read']), $current_store_id]);
}

// Marcar todas como lidas
if (isset($_GET['mark_all_read'])) {
    $pdo->prepare("UPDATE alerts SET is_read = 1 WHERE store_id = ?")->execute([$current_store_id]);
    header('Location: /modules/alerts.php');
    exit;
}

// Alertas de stock baixo (gerados dinamicamente)
$low_stock = $pdo->prepare("SELECT id, name, stock, min_stock FROM products WHERE store_id = ? AND stock < min_stock AND active = 1");
$low_stock->execute([$current_store_id]);
$low_stock_products = $low_stock->fetchAll();

// Produtos prestes a expirar (próximos 7 dias)
$expiring = $pdo->prepare("SELECT id, name, expiry_date FROM products WHERE store_id = ? AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND active = 1");
$expiring->execute([$current_store_id]);
$expiring_products = $expiring->fetchAll();

// Produtos expirados
$expired = $pdo->prepare("SELECT id, name, expiry_date FROM products WHERE store_id = ? AND expiry_date < CURDATE() AND active = 1");
$expired->execute([$current_store_id]);
$expired_products = $expired->fetchAll();

// Alertas do sistema (se tabela existir)
$system_alerts = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM alerts WHERE store_id = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([$current_store_id]);
    $system_alerts = $stmt->fetchAll();
} catch (PDOException $e) {
    // Tabela não existe
}

// Estatísticas
$unread_count = 0;
try {
    $unread = $pdo->prepare("SELECT COUNT(*) FROM alerts WHERE store_id = ? AND is_read = 0");
    $unread->execute([$current_store_id]);
    $unread_count = $unread->fetchColumn();
} catch (PDOException $e) {}

$page_title = 'Alertas';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon orange"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg></div>
        </div>
        <div class="stat-value"><?= count($low_stock_products) ?></div>
        <div class="stat-label">Stock Baixo</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon yellow"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/></svg></div>
        </div>
        <div class="stat-value"><?= count($expiring_products) ?></div>
        <div class="stat-label">A Expirar (7 dias)</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon red"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 9l-6 6M9 9l6 6"/></svg></div>
        </div>
        <div class="stat-value"><?= count($expired_products) ?></div>
        <div class="stat-label">Expirados</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon blue"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg></div>
        </div>
        <div class="stat-value"><?= $unread_count ?></div>
        <div class="stat-label">Não Lidas</div>
    </div>
</div>

<!-- Alertas Críticos: Expirados -->
<?php if (!empty($expired_products)): ?>
<div class="card" style="margin-bottom: 24px; border-color: #ef4444;">
    <div class="card-header" style="background: rgba(239, 68, 68, 0.1);">
        <h3 class="card-title" style="color: #ef4444; display:flex; align-items:center; gap:8px;"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 9l-6 6M9 9l6 6"/></svg> Produtos Expirados (<?= count($expired_products) ?>)</h3>
    </div>
    <div class="table-container" style="border: none;">
        <table class="table">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Data de Validade</th>
                    <th>Dias Expirado</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expired_products as $prod): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($prod['name']) ?></strong></td>
                    <td><?= date('d/m/Y', strtotime($prod['expiry_date'])) ?></td>
                    <td>
                        <span class="badge badge-danger">
                            <?= abs((new DateTime())->diff(new DateTime($prod['expiry_date']))->days) ?> dias
                        </span>
                    </td>
                    <td>
                        <a href="/modules/produtos.php?action=edit&id=<?= $prod['id'] ?>" class="btn btn-secondary btn-sm" style="display:inline-flex;align-items:center;gap:5px">
                                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        Ver Produto
                                    </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Alertas: Stock Baixo -->
<?php if (!empty($low_stock_products)): ?>
<div class="card" style="margin-bottom: 24px; border-color: #f59e0b;">
    <div class="card-header" style="background: rgba(245, 158, 11, 0.1);">
        <h3 class="card-title" style="color: #f59e0b; display:flex; align-items:center; gap:8px;"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2zM16 3H8L4 7h16l-4-4z"/></svg> Stock Baixo (<?= count($low_stock_products) ?>)</h3>
        <a href="/modules/stock.php" class="btn btn-primary btn-sm">Gerir Stock</a>
    </div>
    <div class="table-container" style="border: none;">
        <table class="table">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Stock Atual</th>
                    <th>Stock Mínimo</th>
                    <th>Falta</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($low_stock_products as $prod): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($prod['name']) ?></strong></td>
                    <td>
                        <span class="badge <?= $prod['stock'] == 0 ? 'badge-danger' : 'badge-warning' ?>">
                            <?= $prod['stock'] ?>
                        </span>
                    </td>
                    <td><?= $prod['min_stock'] ?></td>
                    <td>
                        <span class="badge badge-primary"><?= max(0, $prod['min_stock'] - $prod['stock']) ?></span>
                    </td>
                    <td>
                        <a href="/modules/encomendas.php" class="btn btn-secondary btn-sm">Encomendar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Alertas: A Expirar -->
<?php if (!empty($expiring_products)): ?>
<div class="card" style="margin-bottom: 24px; border-color: #eab308;">
    <div class="card-header" style="background: rgba(234, 179, 8, 0.1);">
        <h3 class="card-title" style="color: #eab308; display:flex; align-items:center; gap:8px;"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/></svg> A Expirar em 7 Dias (<?= count($expiring_products) ?>)</h3>
    </div>
    <div class="table-container" style="border: none;">
        <table class="table">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Data de Validade</th>
                    <th>Dias Restantes</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expiring_products as $prod): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($prod['name']) ?></strong></td>
                    <td><?= date('d/m/Y', strtotime($prod['expiry_date'])) ?></td>
                    <td>
                        <?php $days = (new DateTime())->diff(new DateTime($prod['expiry_date']))->days; ?>
                        <span class="badge <?= $days <= 3 ? 'badge-danger' : 'badge-warning' ?>">
                            <?= $days ?> dias
                        </span>
                    </td>
                    <td>
                        <a href="/modules/promocoes.php?product=<?= $prod['id'] ?>" class="btn btn-secondary btn-sm">Criar Promoção</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Notificações do Sistema -->
<?php if (!empty($system_alerts)): ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title" style="display:flex; align-items:center; gap:8px;"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg> Notificações do Sistema</h3>
        <a href="?mark_all_read=1" class="btn btn-secondary btn-sm">Marcar todas como lidas</a>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php foreach ($system_alerts as $alert): ?>
        <div style="padding: 16px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 16px; <?= !$alert['is_read'] ? 'background: var(--accent-light);' : '' ?>">
            <div style="font-size: 24px;">
                <?php
                $icon = match($alert['type'] ?? 'info') {
                    'error' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
                    'warning' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
                    'success' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
                    default => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>'
                };
                echo $icon;
                ?>
            </div>
            <div style="flex: 1;">
                <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($alert['title'] ?? 'Notificação') ?></div>
                <div style="font-size: 14px; color: var(--text-secondary);"><?= htmlspecialchars($alert['message'] ?? '') ?></div>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                    <?= date('d/m/Y H:i', strtotime($alert['created_at'])) ?>
                </div>
            </div>
            <?php if (!$alert['is_read']): ?>
            <a href="?mark_read=<?= $alert['id'] ?>" class="btn btn-secondary btn-sm"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Estado vazio -->
<?php if (empty($low_stock_products) && empty($expiring_products) && empty($expired_products) && empty($system_alerts)): ?>
<div class="card">
    <div class="card-body" style="padding: 64px; text-align: center;">
        <div style="font-size: 64px; margin-bottom: 16px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
        <h3 style="margin-bottom: 8px;">Tudo em Ordem!</h3>
        <p style="color: var(--text-muted);">Não existem alertas ou notificações pendentes.</p>
    </div>
</div>
<?php endif; ?>

<style>
.badge-warning { background: #f59e0b22; color: #f59e0b; }
.stat-icon.yellow { background: rgba(234, 179, 8, 0.1); color: #eab308; }
.stat-icon.red { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>