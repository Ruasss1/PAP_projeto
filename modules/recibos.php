<?php
/**
 * RECIBOS
 * Histórico de vendas com visualização de recibo
 */

header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();
$current_store_id = get_current_store_id();

// Filtros
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

// Estatísticas (mesmo filtro, sem nº venda)
$swhere  = ['store_id = ?'];
$sparams = [$current_store_id];
if ($date_from) { $swhere[] = 'DATE(sale_date) >= ?'; $sparams[] = $date_from; }
if ($date_to)   { $swhere[] = 'DATE(sale_date) <= ?'; $sparams[] = $date_to; }
if ($payment)   { $swhere[] = 'payment_method = ?';   $sparams[] = $payment; }

$sstmt = $pdo->prepare("SELECT
    COUNT(*) as total_sales,
    COALESCE(SUM(total), 0) as total_revenue,
    COALESCE(AVG(total), 0) as avg_sale,
    COUNT(DISTINCT DATE(sale_date)) as days_with_sales
    FROM sales WHERE " . implode(' AND ', $swhere));
$sstmt->execute($sparams);
$stats = $sstmt->fetch();

// Detalhe de uma venda
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
            <div class="stat-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= number_format($stats['total_sales']) ?></div>
        <div class="stat-label">Total de Vendas</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <div class="stat-value" style="color:var(--success);">€<?= number_format($stats['total_revenue'], 2, ',', '.') ?></div>
        <div class="stat-label">Receita Total</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
        </div>
        <div class="stat-value">€<?= number_format($stats['avg_sale'], 2, ',', '.') ?></div>
        <div class="stat-label">Média por Venda</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= number_format($stats['days_with_sales']) ?></div>
        <div class="stat-label">Dias com Vendas</div>
    </div>
</div>

<!-- Filtros -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 14px 18px;">
        <form method="get" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Data início</label>
                <input type="date" name="date_from" class="form-input" value="<?= htmlspecialchars($date_from) ?>" style="width: 150px;">
            </div>
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Data fim</label>
                <input type="date" name="date_to" class="form-input" value="<?= htmlspecialchars($date_to) ?>" style="width: 150px;">
            </div>
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Pagamento</label>
                <select name="payment" class="form-select" style="width: 140px;">
                    <option value="">Todos</option>
                    <option value="Dinheiro" <?= $payment === 'Dinheiro' ? 'selected' : '' ?>>Dinheiro</option>
                    <option value="Cartão"   <?= $payment === 'Cartão'   ? 'selected' : '' ?>>Cartão</option>
                    <option value="MBWay"    <?= $payment === 'MBWay'    ? 'selected' : '' ?>>MBWay</option>
                </select>
            </div>
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Nº Venda</label>
                <input type="text" name="search" class="form-input" placeholder="#" value="<?= htmlspecialchars($search) ?>" style="width: 90px;">
            </div>
            <button type="submit" class="btn btn-primary">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                Filtrar
            </button>
            <?php if ($date_from || $date_to || $payment || $search): ?>
            <a href="/modules/recibos.php" class="btn btn-ghost">Limpar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($viewing_sale): ?>
<!-- Detalhe da Venda -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header">
        <div style="display:flex; align-items:center; gap:10px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text-secondary);"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            <h3 class="card-title">Venda #<?= $viewing_sale['id'] ?></h3>
        </div>
        <a href="/modules/recibos.php?<?= http_build_query(['date_from'=>$date_from,'date_to'=>$date_to,'payment'=>$payment,'search'=>$search]) ?>" class="btn btn-ghost btn-sm">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            Fechar
        </a>
    </div>

    <!-- Metadados da venda -->
    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:1px; background:var(--border); border-bottom:1px solid var(--border);">
        <?php
        $meta = [
            ['Data', date('d/m/Y', strtotime($viewing_sale['sale_date']))],
            ['Hora', date('H:i', strtotime($viewing_sale['sale_date']))],
            ['Método', $viewing_sale['payment_method'] ?? 'N/A'],
            ['Artigos', count($sale_items) . ' produto' . (count($sale_items) !== 1 ? 's' : '')],
        ];
        foreach ($meta as $m): ?>
        <div style="background:var(--bg-secondary); padding:14px 18px;">
            <div style="font-size:10.5px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);margin-bottom:4px;"><?= $m[0] ?></div>
            <div style="font-size:14px;font-weight:600;color:var(--text-primary);"><?= $m[1] ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Itens -->
    <table class="table" style="border-radius:0;">
        <thead>
            <tr>
                <th>Produto</th>
                <th>Código</th>
                <th style="text-align:right;">Qtd</th>
                <th style="text-align:right;">Preço Un.</th>
                <th style="text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sale_items as $item):
                $line = $item['price'] * $item['quantity'];
            ?>
            <tr>
                <td><span style="font-weight:600;"><?= htmlspecialchars($item['name']) ?></span></td>
                <td style="color:var(--text-muted);font-size:12px;font-family:monospace;"><?= htmlspecialchars($item['sku'] ?? '—') ?></td>
                <td style="text-align:right;">× <?= $item['quantity'] ?></td>
                <td style="text-align:right;color:var(--text-secondary);">€<?= number_format($item['price'], 2, ',', '.') ?></td>
                <td style="text-align:right;font-weight:700;">€<?= number_format($line, 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:var(--bg-tertiary);">
                <td colspan="4" style="text-align:right;font-weight:600;color:var(--text-secondary);">Total</td>
                <td style="text-align:right;font-family:'Plus Jakarta Sans',sans-serif;font-size:20px;font-weight:800;letter-spacing:-0.04em;color:var(--success);">
                    €<?= number_format($viewing_sale['total'], 2, ',', '.') ?>
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="card-footer" style="display:flex;gap:8px;">
        <button onclick="openReceipt(<?= $viewing_sale['id'] ?>)" class="btn btn-primary">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Ver Recibo
        </button>
        <button onclick="printDirectReceipt(<?= $viewing_sale['id'] ?>)" class="btn btn-secondary">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Imprimir
        </button>
    </div>
</div>
<?php endif; ?>

<!-- Lista de Vendas -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Histórico de Vendas</h3>
        <span class="badge badge-neutral"><?= count($sales) ?></span>
    </div>
    <div class="table-container" style="border:none;">
        <table class="table">
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Data</th>
                    <th>Hora</th>
                    <th>Artigos</th>
                    <th>Pagamento</th>
                    <th style="text-align:right;">Total</th>
                    <th style="width:110px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sales)): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="empty-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </div>
                            <div class="empty-title">Sem vendas</div>
                            <div class="empty-text">Nenhuma venda encontrada no período selecionado.</div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($sales as $sale): ?>
                <?php
                $pm = $sale['payment_method'] ?? '';
                $pmIcon = match($pm) {
                    'Dinheiro' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><circle cx="12" cy="12" r="3"/></svg>',
                    'Cartão'   => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
                    'MBWay'    => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>',
                    default    => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
                };
                $isViewing = $viewing_sale && $viewing_sale['id'] == $sale['id'];
                ?>
                <tr style="<?= $isViewing ? 'background:var(--bg-tertiary);' : '' ?>">
                    <td>
                        <span style="font-family:monospace;font-size:12px;color:var(--text-muted);">#<?= str_pad($sale['id'], 6, '0', STR_PAD_LEFT) ?></span>
                    </td>
                    <td style="font-weight:500;"><?= date('d/m/Y', strtotime($sale['sale_date'])) ?></td>
                    <td style="color:var(--text-muted);font-size:12.5px;"><?= date('H:i', strtotime($sale['sale_date'])) ?></td>
                    <td style="color:var(--text-secondary);"><?= $sale['items_count'] ?> item<?= $sale['items_count'] != 1 ? 's' : '' ?></td>
                    <td>
                        <span class="badge badge-neutral"><?= $pmIcon ?> <?= htmlspecialchars($pm ?: 'N/A') ?></span>
                    </td>
                    <td style="text-align:right;">
                        <span style="font-family:'Plus Jakarta Sans',sans-serif;font-size:15px;font-weight:700;letter-spacing:-0.02em;color:var(--success);">€<?= number_format($sale['total'], 2, ',', '.') ?></span>
                    </td>
                    <td>
                        <div style="display:flex;gap:5px;justify-content:flex-end;">
                            <a href="?view=<?= $sale['id'] ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&payment=<?= urlencode($payment) ?>&search=<?= urlencode($search) ?>" class="btn btn-ghost btn-sm" title="Ver detalhe">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                Ver
                            </a>
                            <button onclick="openReceipt(<?= $sale['id'] ?>)" class="btn btn-secondary btn-sm" title="Recibo">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal do Recibo -->
<div id="receiptModal" style="display:none; position:fixed; inset:0; z-index:9999;">
    <!-- Overlay -->
    <div onclick="closeReceipt()" style="position:absolute;inset:0;background:rgba(0,0,0,.8);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);"></div>

    <!-- Content -->
    <div style="position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;height:100%;overflow-y:auto;padding:32px 20px;">

        <!-- Toolbar -->
        <div style="display:flex;gap:8px;margin-bottom:24px;flex-shrink:0;">
            <button onclick="closeReceipt()" class="btn btn-secondary" style="background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.12);color:rgba(255,255,255,.7);">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                Fechar
            </button>
            <button onclick="printCurrentReceipt()" class="btn btn-secondary" style="background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.12);color:rgba(255,255,255,.7);">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Imprimir
            </button>
            <button id="btnDownload" onclick="downloadReceipt()" style="padding:0 16px;height:34px;border-radius:var(--radius);font-family:'Plus Jakarta Sans',sans-serif;font-weight:600;font-size:13px;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:7px;background:#eeeeee;color:#060606;transition:opacity .15s;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Descarregar
            </button>
        </div>

        <!-- Receipt frame -->
        <div id="receiptContent" style="flex-shrink:0;"></div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
let currentReceiptId = null;

function openReceipt(saleId) {
    currentReceiptId = saleId;
    const modal   = document.getElementById('receiptModal');
    const content = document.getElementById('receiptContent');
    content.innerHTML = '<div style="color:rgba(255,255,255,.5);font-size:14px;padding:60px;font-family:Inter,sans-serif;">A carregar...</div>';
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';

    fetch('/modules/recibo_print.php?id=' + saleId + '&embed=1')
        .then(r => r.text())
        .then(html => { content.innerHTML = html; })
        .catch(() => { content.innerHTML = '<div style="color:#f87171;padding:40px;font-family:Inter,sans-serif;">Erro ao carregar recibo.</div>'; });
}

function closeReceipt() {
    document.getElementById('receiptModal').style.display = 'none';
    document.body.style.overflow = '';
}

function printCurrentReceipt() {
    if (!currentReceiptId) return;
    const win = window.open('/modules/recibo_print.php?id=' + currentReceiptId, '_blank', 'width=450,height=750');
    if (win) setTimeout(() => win.print(), 800);
}

function printDirectReceipt(id) {
    const win = window.open('/modules/recibo_print.php?id=' + id, '_blank', 'width=450,height=750');
    if (win) setTimeout(() => win.print(), 800);
}

function downloadReceipt() {
    const el  = document.getElementById('receiptContent');
    const btn = document.getElementById('btnDownload');
    btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> A gerar...';
    btn.disabled = true;
    btn.style.opacity = '.6';

    html2canvas(el, { backgroundColor: null, scale: 3, useCORS: true, logging: false })
        .then(canvas => {
            const a = document.createElement('a');
            a.download = 'recibo_' + currentReceiptId + '.png';
            a.href = canvas.toDataURL('image/png');
            a.click();
        })
        .catch(() => {})
        .finally(() => {
            btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Descarregar';
            btn.disabled = false;
            btn.style.opacity = '1';
        });
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeReceipt(); });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
