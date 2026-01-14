<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

$message = null;

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_read' && isset($_POST['alert_id'])) {
        $ok = mark_alert_read(intval($_POST['alert_id']));
        $message = $ok ? 'Alerta marcado como lido' : 'Erro';
    } elseif ($_POST['action'] === 'mark_all_read') {
        $ok = mark_all_alerts_read();
        $message = $ok ? 'Todos os alertas marcados como lidos' : 'Erro';
    }
}

$alerts = get_alerts(false);
$unread_count = get_unread_alerts_count();
$low_stock = list_low_stock_products();
$breaks = list_breaks(10);
?>
<h1>Alertas e Notificações</h1>

<?php if (!empty($message)): ?><p class="notice"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>

<?php if ($unread_count > 0): ?>
    <form method="post" style="margin-bottom: 20px;">
        <input type="hidden" name="action" value="mark_all_read">
        <button type="submit" class="btn">Marcar todos como lidos (<?php echo $unread_count; ?>)</button>
    </form>
<?php endif; ?>

<div class="dashboard-cards">
    <div class="card">
        <h3>Alertas Não Lidos</h3>
        <p class="<?php echo $unread_count > 0 ? 'negative' : 'positive'; ?>">
            <?php echo $unread_count; ?>
        </p>
    </div>
    <div class="card">
        <h3>Produtos Stock Baixo</h3>
        <p class="<?php echo count($low_stock) > 0 ? 'negative' : 'positive'; ?>">
            <?php echo count($low_stock); ?>
        </p>
    </div>
    <div class="card">
        <h3>Quebras Recentes</h3>
        <p class="<?php echo count($breaks) > 0 ? 'negative' : 'positive'; ?>">
            <?php echo count($breaks); ?>
        </p>
    </div>
</div>

<h2>Todos os Alertas</h2>
<?php if (empty($alerts)): ?>
    <p>Sem alertas no momento.</p>
<?php else: ?>
    <div class="alerts-list">
        <?php foreach ($alerts as $alert): ?>
            <div class="alert-item <?php echo $alert['read'] ? 'read' : 'unread'; ?> <?php echo $alert['severity']; ?>">
                <div class="alert-header">
                    <span class="alert-type">
                        <?php
                        $types = [
                            'low_stock' => '📉 Stock Baixo',
                            'expiry' => '⏰ Validade',
                            'break' => '💔 Quebra',
                            'negative_profit' => '📊 Lucro Negativo',
                            'order_delivered' => '📦 Encomenda'
                        ];
                        echo $types[$alert['alert_type']] ?? $alert['alert_type'];
                        ?>
                    </span>
                    <span class="alert-date"><?php echo date('d/m/Y H:i', strtotime($alert['created_at'])); ?></span>
                </div>
                <div class="alert-title"><?php echo htmlspecialchars($alert['title']); ?></div>
                <div class="alert-message"><?php echo htmlspecialchars($alert['message']); ?></div>
                <?php if (!$alert['read']): ?>
                    <form method="post" style="margin-top: 10px;">
                        <input type="hidden" name="action" value="mark_read">
                        <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                        <button type="submit" class="btn" style="padding: 4px 10px; font-size: 12px;">Marcar como lido</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h2>Produtos com Stock Baixo</h2>
<?php if (empty($low_stock)): ?>
    <p>Todos os produtos têm stock adequado.</p>
<?php else: ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Categoria</th>
                    <th>Stock Atual</th>
                    <th>Stock Mínimo</th>
                    <th>Fornecedor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($low_stock as $p): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($p['name']); ?></td>
                        <td><?php echo htmlspecialchars($p['category'] ?? '-'); ?></td>
                        <td class="negative"><?php echo (int)$p['stock']; ?></td>
                        <td><?php echo (int)$p['min_stock']; ?></td>
                        <td><?php echo htmlspecialchars($p['supplier_name'] ?? 'Sem fornecedor'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<h2>Quebras Recentes</h2>
<?php if (empty($breaks)): ?>
    <p>Sem registos de quebra.</p>
<?php else: ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Produto</th>
                    <th>Quantidade</th>
                    <th>Custo</th>
                    <th>Motivo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($breaks as $b): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($b['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($b['product_name']); ?></td>
                        <td><?php echo (int)$b['qty']; ?></td>
                        <td class="negative"><?php echo number_format($b['cost'], 2); ?>€</td>
                        <td><?php echo htmlspecialchars($b['reason']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<style>
.alerts-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.alert-item {
    background: #1e1e2e;
    border: 1px solid #333;
    border-radius: 8px;
    padding: 16px;
    border-left: 4px solid #666;
}

.alert-item.unread {
    background: #252538;
}

.alert-item.warning {
    border-left-color: #fbbf24;
}

.alert-item.critical {
    border-left-color: #f87171;
}

.alert-item.info {
    border-left-color: #60a5fa;
}

.alert-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 12px;
    color: #888;
}

.alert-title {
    font-weight: 600;
    color: #fff;
    margin-bottom: 4px;
}

.alert-message {
    color: #aaa;
    font-size: 14px;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

