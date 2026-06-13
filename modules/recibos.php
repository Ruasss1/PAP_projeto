<?php
/**
 * RECIBOS - PREMIUM
 * Visualização moderna de histórico de vendas
 */

header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();
$current_store_id = get_current_store_id();

// Filtros
$has_filters = !empty($_GET);
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to']   ?? '';
$payment   = $_GET['payment']   ?? '';
$search    = $_GET['search']    ?? '';

// Query vendas
$where  = ['s.store_id = ?'];
$params = [$current_store_id];
if ($date_from) { $where[] = 'DATE(s.sale_date) >= ?'; $params[] = $date_from; }
if ($date_to)   { $where[] = 'DATE(s.sale_date) <= ?'; $params[] = $date_to; }
if ($payment)   { $where[] = 's.payment_method = ?';   $params[] = $payment; }
if ($search)    { $where[] = 's.id = ?';               $params[] = intval($search); }

$sql = "SELECT s.*, COUNT(si.id) as items_count 
        FROM sales s 
        LEFT JOIN sale_items si ON s.id = si.sale_id 
        WHERE " . implode(' AND ', $where) . "
        GROUP BY s.id ORDER BY s.sale_date DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Estatísticas (mesmo filtro)
$swhere  = ['store_id = ?'];
$sparams = [$current_store_id];
if ($date_from) { $swhere[] = 'DATE(sale_date) >= ?'; $sparams[] = $date_from; }
if ($date_to)   { $swhere[] = 'DATE(sale_date) <= ?'; $sparams[] = $date_to; }
if ($payment)   { $swhere[] = 'payment_method = ?';   $sparams[] = $payment; }

$stats_sql = "SELECT 
    COUNT(*) as total_sales,
    COALESCE(SUM(total), 0) as total_revenue,
    COALESCE(AVG(total), 0) as avg_sale,
    COUNT(DISTINCT DATE(sale_date)) as days_with_sales
    FROM sales 
    WHERE " . implode(' AND ', $swhere);
$stats = $pdo->prepare($stats_sql);
$stats->execute($sparams);
$stats = $stats->fetch();

// Ver detalhes de uma venda
$viewing_sale = null;
$sale_items = [];
if (isset($_GET['view'])) {
    $sale_id = intval($_GET['view']);
    $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ? AND store_id = ?");
    $stmt->execute([$sale_id, $current_store_id]);
    $viewing_sale = $stmt->fetch();
    
    if ($viewing_sale) {
        $stmt = $pdo->prepare("SELECT si.*, p.name, p.barcode as sku FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?");
        $stmt->execute([$sale_id]);
        $sale_items = $stmt->fetchAll();
    }
}

$page_title = 'Recibos';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon blue"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></div>
        </div>
        <div class="stat-value"><?= $stats['total_sales'] ?></div>
        <div class="stat-label">Total Vendas</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon green"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg></div>
        </div>
        <div class="stat-value">€<?= number_format($stats['total_revenue'], 2, ',', '.') ?></div>
        <div class="stat-label">Receita Total</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon purple"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></div>
        </div>
        <div class="stat-value">€<?= number_format($stats['avg_sale'], 2) ?></div>
        <div class="stat-label">Média por Venda</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon orange"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
        </div>
        <div class="stat-value"><?= $stats['days_with_sales'] ?></div>
        <div class="stat-label">Dias com Vendas</div>
    </div>
</div>

<!-- Filtros -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 24px;">
        <form method="get" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Data Início</label>
                <input type="date" name="date_from" class="form-input" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Data Fim</label>
                <input type="date" name="date_to" class="form-input" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Pagamento</label>
                <select name="payment" class="form-select">
                    <option value="">Todos</option>
                    <option value="Dinheiro" <?= $payment === 'Dinheiro' ? 'selected' : '' ?>>💵 Dinheiro</option>
                    <option value="Cartão" <?= $payment === 'Cartão' ? 'selected' : '' ?>>💳 Cartão</option>
                    <option value="MBWay" <?= $payment === 'MBWay' ? 'selected' : '' ?>>📱 MBWay</option>
                </select>
            </div>
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Nº Venda</label>
                <input type="text" name="search" class="form-input" placeholder="#" value="<?= htmlspecialchars($search) ?>" style="width: 100px;">
            </div>
            <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
            <a href="/modules/recibos.php" class="btn btn-secondary">Limpar</a>
        </form>
    </div>
</div>

<?php if ($viewing_sale): ?>
<!-- Modal de Detalhes -->
<div class="card" style="margin-bottom: 24px; border-color: var(--accent);">
    <div class="card-header" style="background: var(--accent-light);">
        <h3 class="card-title" style="color: var(--accent);">📋 Detalhes da Venda #<?= $viewing_sale['id'] ?></h3>
        <a href="/modules/recibos.php" class="btn btn-secondary btn-sm">✕ Fechar</a>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 24px;">
            <div>
                <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px;">Data/Hora</div>
                <div style="font-weight: 600;"><?= date('d/m/Y H:i', strtotime($viewing_sale['sale_date'])) ?></div>
            </div>
            <div>
                <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px;">Pagamento</div>
                <div style="font-weight: 600;"><?= $viewing_sale['payment_method'] ?? 'N/A' ?></div>
            </div>
            <div>
                <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px;">Itens</div>
                <div style="font-weight: 600;"><?= count($sale_items) ?> produtos</div>
            </div>
            <div>
                <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px;">Total</div>
                <div style="font-weight: 700; font-size: 24px; color: var(--accent);">€<?= number_format($viewing_sale['total'], 2) ?></div>
            </div>
        </div>
        
        <table class="table" style="border: 1px solid var(--border); border-radius: var(--radius-lg);">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Código</th>
                    <th>Qtd</th>
                    <th>Preço Un.</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sale_items as $item): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                    <td><?= htmlspecialchars($item['sku'] ?? '-') ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td>€<?= number_format($item['price'], 2) ?></td>
                    <td><strong>€<?= number_format($item['price'] * $item['quantity'], 2) ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="display: flex; gap: 12px; margin-top: 16px;">
            <button onclick="openReceipt(<?= $viewing_sale['id'] ?>)" class="btn btn-primary">🧾 Ver Recibo</button>
            <button onclick="window.print()" class="btn btn-secondary">🖨️ Imprimir</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Lista de Vendas -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Histórico de Vendas (<?= count($sales) ?>)</h3>
    </div>
    <div class="table-container" style="border: none;">
        <table class="table">
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Data/Hora</th>
                    <th>Itens</th>
                    <th>Pagamento</th>
                    <th>Total</th>
                    <th style="width: 100px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sales)): ?>
                <tr>
                    <td colspan="6" class="table-empty">
                        <div class="empty-state">
                            <div class="empty-icon">🧾</div>
                            <div class="empty-title">Sem vendas</div>
                            <div class="empty-text">Nenhuma venda encontrada no período selecionado.</div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($sales as $sale): ?>
                <tr>
                    <td><strong>#<?= $sale['id'] ?></strong></td>
                    <td>
                        <div><?= date('d/m/Y', strtotime($sale['sale_date'])) ?></div>
                        <div style="font-size: 12px; color: var(--text-muted);"><?= date('H:i', strtotime($sale['sale_date'])) ?></div>
                    </td>
                    <td><?= $sale['items_count'] ?> itens</td>
                    <td>
                        <?php 
                        $icon = match($sale['payment_method'] ?? '') {
                            'Dinheiro' => '💵',
                            'Cartão' => '💳',
                            'MBWay' => '📱',
                            default => '💰'
                        };
                        ?>
                        <span class="badge badge-muted"><?= $icon ?> <?= $sale['payment_method'] ?? 'N/A' ?></span>
                    </td>
                    <td><strong style="font-size: 16px;">€<?= number_format($sale['total'], 2) ?></strong></td>
                    <td style="display: flex; gap: 6px;">
                        <a href="?view=<?= $sale['id'] ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="btn btn-secondary btn-sm">👁️ Ver</a>
                        <button onclick="openReceipt(<?= $sale['id'] ?>)" class="btn btn-primary btn-sm">🧾 Recibo</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal do Recibo -->
<div id="receiptModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:9999;">
    <div onclick="closeReceipt()" style="position:absolute; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(6px);"></div>
    <div style="position:relative; z-index:1; display:flex; flex-direction:column; align-items:center; height:100%; overflow-y:auto; padding:30px 20px;">
        <div style="display:flex; gap:10px; margin-bottom:20px; flex-shrink:0;">
            <button onclick="closeReceipt()" style="padding:10px 22px; background:#27272a; border:1px solid #3f3f46; color:#a1a1aa; border-radius:10px; cursor:pointer; font-size:14px; font-weight:600;">✕ Fechar</button>
            <button onclick="printReceipt()" style="padding:10px 22px; background:#22c55e; border:none; color:white; border-radius:10px; cursor:pointer; font-size:14px; font-weight:600;">🖨️ Imprimir</button>
            <button id="btnDownload" onclick="downloadReceipt()" style="padding:10px 22px; background:#fafafa; border:1px solid #3f3f46; color:#09090b; border-radius:10px; cursor:pointer; font-size:14px; font-weight:600;">📥 Descarregar</button>
        </div>
        <div id="receiptContent" style="flex-shrink:0;"></div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
let currentReceiptId = null;

function openReceipt(saleId) {
    currentReceiptId = saleId;
    const modal = document.getElementById('receiptModal');
    const content = document.getElementById('receiptContent');
    content.innerHTML = '<div style="color:white;font-size:16px;padding:40px;">⏳ A carregar recibo...</div>';
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';

    fetch('/modules/recibo_print.php?id=' + saleId + '&embed=1')
        .then(r => r.text())
        .then(html => {
            content.innerHTML = html;
        })
        .catch(() => {
            content.innerHTML = '<div style="color:#ef4444;padding:40px;">Erro ao carregar recibo.</div>';
        });
}

function closeReceipt() {
    document.getElementById('receiptModal').style.display = 'none';
    document.body.style.overflow = '';
}

function printReceipt() {
    const content = document.getElementById('receiptContent').innerHTML;
    const win = window.open('', '_blank', 'width=400,height=700');
    win.document.write('<html><head><title>Recibo</title>');
    win.document.write('<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">');
    win.document.write('<link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128&display=swap" rel="stylesheet">');
    win.document.write('</head><body style="display:flex;justify-content:center;padding:10px;">');
    win.document.write(content);
    win.document.write('</body></html>');
    win.document.close();
    setTimeout(() => { win.print(); }, 500);
}

function downloadReceipt() {
    const el = document.getElementById('receiptContent');
    const btn = document.getElementById('btnDownload');
    btn.textContent = '⏳ A gerar...';
    btn.disabled = true;
    html2canvas(el, { backgroundColor: null, scale: 3, useCORS: true, logging: false }).then(canvas => {
        const link = document.createElement('a');
        link.download = 'recibo_' + currentReceiptId + '.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
        btn.textContent = '📥 Descarregar';
        btn.disabled = false;
    }).catch(() => {
        btn.textContent = '📥 Descarregar';
        btn.disabled = false;
    });
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeReceipt(); });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>