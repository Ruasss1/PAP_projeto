<?php
/**
 * RECIBO VISUAL — talão de supermercado
 * Modo standalone (página completa) + embed (só o HTML do talão)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

$sale_id = intval($_GET['id'] ?? 0);
if (!$sale_id || !$pdo) { die('Recibo não encontrado.'); }

$stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sale) { die('Venda não encontrada.'); }

$stmt = $pdo->prepare("SELECT si.quantity, si.price, p.name, p.barcode
    FROM sale_items si JOIN products p ON p.id = si.product_id
    WHERE si.sale_id = ? ORDER BY si.id ASC");
$stmt->execute([$sale_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$subtotal  = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
$iva_rate  = 23;
$iva_value = round($subtotal * $iva_rate / (100 + $iva_rate), 2);
$base_value = $subtotal - $iva_value;

$store_name    = 'Supermercado PAP';
$store_address = 'Rua Principal, nº 123';
$store_city    = '1000-001 Lisboa';
$store_nif     = '999 999 999';
$store_phone   = '210 000 000';

$receipt_number = str_pad($sale['id'], 8, '0', STR_PAD_LEFT);
$date = date('d/m/Y', strtotime($sale['sale_date']));
$time = date('H:i:s', strtotime($sale['sale_date']));

$is_embed = isset($_GET['embed']) && $_GET['embed'] == '1';

if (!$is_embed):
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo #<?= $receipt_number ?> — Mercantec</title>
    <link rel="icon" href="/assets/icons/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/design-system.css?v=<?= time() ?>">
    <script>(function(){const t=localStorage.getItem('pap-theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            min-height: 100vh;
            padding: 40px 20px;
        }

        .receipt-actions {
            display: flex;
            gap: 8px;
            margin-bottom: 32px;
        }

        /* ── Talão ─────────────────────────────────────────────── */
        .receipt-wrapper {
            filter: drop-shadow(0 24px 64px rgba(0,0,0,.6));
        }

        .receipt {
            background: #fefefa;
            width: 320px;
            padding: 30px 22px;
            color: #1a1a1a;
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.55;
            position: relative;
        }

        /* Borda de papel rasgado */
        .receipt::before {
            content: '';
            position: absolute;
            top: -8px; left: 0; right: 0; height: 8px;
            background: linear-gradient(135deg, #fefefa 33.33%, transparent 33.33%) 0 0,
                        linear-gradient(225deg, #fefefa 33.33%, transparent 33.33%) 0 0;
            background-size: 12px 8px;
            background-repeat: repeat-x;
        }
        .receipt::after {
            content: '';
            position: absolute;
            bottom: -8px; left: 0; right: 0; height: 8px;
            background: linear-gradient(315deg, #fefefa 33.33%, transparent 33.33%) 0 0,
                        linear-gradient(45deg,  #fefefa 33.33%, transparent 33.33%) 0 0;
            background-size: 12px 8px;
            background-repeat: repeat-x;
        }

        .store-header  { text-align: center; margin-bottom: 16px; }
        .store-name    { font-size: 16px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; margin: 8px 0 4px; }
        .store-info    { font-size: 10px; color: #666; line-height: 1.6; }
        .divider       { border: none; border-top: 1px dashed #ccc; margin: 12px 0; }
        .divider-solid { border: none; border-top: 2px solid #222; margin: 12px 0; }
        .receipt-meta  { display: flex; justify-content: space-between; font-size: 11px; color: #555; }
        .receipt-meta-row { display: flex; justify-content: space-between; margin-bottom: 2px; }
        .items-header {
            display: flex; justify-content: space-between;
            font-weight: 700; font-size: 10.5px;
            padding-bottom: 5px; border-bottom: 1px solid #222;
            margin-bottom: 7px; text-transform: uppercase; letter-spacing: .06em;
        }
        .item-row   { margin-bottom: 7px; }
        .item-name  { font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 1px; }
        .item-detail { display: flex; justify-content: space-between; font-size: 11px; color: #555; }
        .total-row  { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 3px; }
        .total-row .label { color: #666; }
        .grand-total {
            font-size: 20px; font-weight: 700;
            margin-top: 8px; padding-top: 10px;
            border-top: 2px solid #222;
        }
        .grand-total .label,
        .grand-total .value { color: #1a1a1a; }
        .payment-box {
            text-align: center; margin: 14px 0; padding: 9px 12px;
            background: #f0f0eb; border-radius: 5px;
        }
        .payment-label  { font-size: 9.5px; color: #888; text-transform: uppercase; letter-spacing:.08em; margin-bottom: 3px; }
        .payment-method { font-size: 14px; font-weight: 700; text-transform: uppercase; }
        .iva-table { width: 100%; font-size: 10px; border-collapse: collapse; margin: 6px 0; }
        .iva-table th { text-align: left; font-weight: 600; padding: 3px 0; border-bottom: 1px solid #ddd; font-size: 9px; text-transform: uppercase; color: #888; letter-spacing:.06em; }
        .iva-table td { padding: 3px 0; color: #555; }
        .nif-section   { text-align: center; font-size: 11px; color: #444; margin: 6px 0; }
        .barcode-area  { text-align: center; margin: 14px 0 4px; font-family: 'Libre Barcode 128', monospace; font-size: 40px; color: #1a1a1a; }
        .barcode-text  { text-align: center; font-size: 10px; color: #aaa; margin-top: -2px; }
        .footer-section { text-align: center; margin-top: 16px; }
        .footer-msg    { font-size: 12px; font-weight: 600; margin-bottom: 5px; }
        .footer-small  { font-size: 9.5px; color: #999; line-height: 1.7; }

        @media print {
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            body { background: white !important; padding: 0 !important; display: block !important; min-height: unset !important; }
            .receipt-actions { display: none !important; }
            .receipt-wrapper { filter: none !important; display: block !important; margin: 0 auto !important; }
            .receipt { box-shadow: none !important; border: none !important; width: 320px !important; margin: 0 auto !important; page-break-inside: avoid !important; }
            .receipt::before, .receipt::after { display: none !important; }
        }
    </style>
</head>
<body>

<!-- Ações -->
<div class="receipt-actions">
    <a href="/modules/recibos.php" class="btn btn-ghost">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Voltar
    </a>
    <button onclick="window.print()" class="btn btn-secondary">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Imprimir
    </button>
    <button id="btnDownload" onclick="downloadReceipt()" class="btn btn-primary">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Descarregar
    </button>
</div>

<?php endif; // !is_embed ?>

<?php if ($is_embed): ?>
<style>
    .receipt-wrapper { filter: drop-shadow(0 20px 56px rgba(0,0,0,.55)); }
    .receipt { background:#fefefa;width:310px;padding:28px 20px;color:#1a1a1a;font-family:'JetBrains Mono','Courier New',monospace;font-size:12px;line-height:1.55;position:relative; }
    .receipt::before { content:'';position:absolute;top:-8px;left:0;right:0;height:8px;background:linear-gradient(135deg,#fefefa 33.33%,transparent 33.33%) 0 0,linear-gradient(225deg,#fefefa 33.33%,transparent 33.33%) 0 0;background-size:12px 8px;background-repeat:repeat-x; }
    .receipt::after  { content:'';position:absolute;bottom:-8px;left:0;right:0;height:8px;background:linear-gradient(315deg,#fefefa 33.33%,transparent 33.33%) 0 0,linear-gradient(45deg,#fefefa 33.33%,transparent 33.33%) 0 0;background-size:12px 8px;background-repeat:repeat-x; }
    .store-header  { text-align:center;margin-bottom:14px; }
    .store-name    { font-size:16px;font-weight:700;letter-spacing:2px;text-transform:uppercase;margin:8px 0 4px; }
    .store-info    { font-size:10px;color:#666;line-height:1.6; }
    .divider       { border:none;border-top:1px dashed #ccc;margin:11px 0; }
    .divider-solid { border:none;border-top:2px solid #222;margin:11px 0; }
    .receipt-meta  { display:flex;justify-content:space-between;font-size:11px;color:#555; }
    .receipt-meta-row { display:flex;justify-content:space-between;margin-bottom:2px; }
    .items-header { display:flex;justify-content:space-between;font-weight:700;font-size:10.5px;padding-bottom:5px;border-bottom:1px solid #222;margin-bottom:7px;text-transform:uppercase;letter-spacing:.06em; }
    .item-row     { margin-bottom:7px; }
    .item-name    { font-size:11.5px;font-weight:600;display:block;margin-bottom:1px; }
    .item-detail  { display:flex;justify-content:space-between;font-size:11px;color:#555; }
    .total-row    { display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px; }
    .total-row .label { color:#666; }
    .grand-total  { font-size:20px;font-weight:700;margin-top:8px;padding-top:10px;border-top:2px solid #222; }
    .grand-total .label,.grand-total .value { color:#1a1a1a; }
    .payment-box  { text-align:center;margin:14px 0;padding:9px 12px;background:#f0f0eb;border-radius:5px; }
    .payment-label { font-size:9.5px;color:#888;text-transform:uppercase;letter-spacing:.08em;margin-bottom:3px; }
    .payment-method { font-size:14px;font-weight:700;text-transform:uppercase; }
    .iva-table    { width:100%;font-size:10px;border-collapse:collapse;margin:6px 0; }
    .iva-table th { text-align:left;font-weight:600;padding:3px 0;border-bottom:1px solid #ddd;font-size:9px;text-transform:uppercase;color:#888;letter-spacing:.06em; }
    .iva-table td { padding:3px 0;color:#555; }
    .nif-section  { text-align:center;font-size:11px;color:#444;margin:6px 0; }
    .barcode-area { text-align:center;margin:14px 0 4px;font-family:'Libre Barcode 128',monospace;font-size:40px;color:#1a1a1a; }
    .barcode-text { text-align:center;font-size:10px;color:#aaa;margin-top:-2px; }
    .footer-section { text-align:center;margin-top:14px; }
    .footer-msg   { font-size:12px;font-weight:600;margin-bottom:5px; }
    .footer-small { font-size:9.5px;color:#999;line-height:1.7; }
</style>
<?php endif; ?>

<!-- Talão -->
<div class="receipt-wrapper" id="receipt">
    <div class="receipt">

        <!-- Cabeçalho -->
        <div class="store-header">
            <svg width="42" height="42" viewBox="0 0 512 512" fill="none" xmlns="http://www.w3.org/2000/svg">
                <g stroke="#1a1a1a" stroke-width="34" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M118 306C158 180 231 151 256 240C282 332 355 342 406 216"/>
                    <path d="M140 222C183 273 225 286 256 244C290 199 333 190 382 230"/>
                </g>
            </svg>
            <div class="store-name"><?= htmlspecialchars($store_name) ?></div>
            <div class="store-info">
                <?= htmlspecialchars($store_address) ?><br>
                <?= htmlspecialchars($store_city) ?><br>
                NIF: <?= $store_nif ?> &nbsp;·&nbsp; Tel: <?= $store_phone ?>
            </div>
        </div>

        <hr class="divider-solid">

        <!-- Metadados -->
        <div class="receipt-meta">
            <div>
                <div class="receipt-meta-row"><span>Recibo:</span>&nbsp;<strong><?= $receipt_number ?></strong></div>
                <div class="receipt-meta-row"><span>Operador:</span>&nbsp;<span><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span></div>
            </div>
            <div style="text-align:right;">
                <div class="receipt-meta-row"><span><?= $date ?></span></div>
                <div class="receipt-meta-row"><span><?= $time ?></span></div>
            </div>
        </div>

        <hr class="divider">

        <!-- Itens -->
        <div class="items-header">
            <span>Artigo</span>
            <span>Total</span>
        </div>

        <?php foreach ($items as $item):
            $line = $item['price'] * $item['quantity'];
        ?>
        <div class="item-row">
            <span class="item-name"><?= htmlspecialchars($item['name']) ?></span>
            <div class="item-detail">
                <span><?= $item['quantity'] ?> × €<?= number_format($item['price'], 2, ',', '.') ?></span>
                <strong>€<?= number_format($line, 2, ',', '.') ?></strong>
            </div>
        </div>
        <?php endforeach; ?>

        <hr class="divider">

        <!-- Totais -->
        <div class="total-row">
            <span class="label">Subtotal (<?= count($items) ?> artigo<?= count($items) !== 1 ? 's' : '' ?>)</span>
            <span>€<?= number_format($subtotal, 2, ',', '.') ?></span>
        </div>
        <div class="total-row grand-total">
            <span class="label">TOTAL</span>
            <span class="value">€<?= number_format($sale['total'], 2, ',', '.') ?></span>
        </div>

        <!-- Pagamento -->
        <div class="payment-box">
            <div class="payment-label">Método de Pagamento</div>
            <div class="payment-method"><?= htmlspecialchars($sale['payment_method'] ?? 'Dinheiro') ?></div>
        </div>

        <hr class="divider">

        <!-- Tabela IVA -->
        <table class="iva-table">
            <thead>
                <tr><th>Taxa</th><th>Base</th><th>IVA</th><th>Total</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= $iva_rate ?>%</td>
                    <td>€<?= number_format($base_value, 2, ',', '.') ?></td>
                    <td>€<?= number_format($iva_value, 2, ',', '.') ?></td>
                    <td>€<?= number_format($subtotal, 2, ',', '.') ?></td>
                </tr>
            </tbody>
        </table>

        <?php if (!empty($sale['nif'])): ?>
        <div class="nif-section"><strong>NIF do Cliente:</strong> <?= htmlspecialchars($sale['nif']) ?></div>
        <?php endif; ?>

        <hr class="divider">

        <!-- Código de barras -->
        <div class="barcode-area"><?= $receipt_number ?></div>
        <div class="barcode-text"><?= $receipt_number ?></div>

        <hr class="divider">

        <!-- Rodapé -->
        <div class="footer-section">
            <div class="footer-msg">Obrigado pela sua compra!</div>
            <div class="footer-small">
                Conserve este talão para eventuais<br>
                trocas ou devoluções (prazo: 15 dias).<br><br>
                <?= htmlspecialchars($store_name) ?> &mdash; <?= date('Y') ?>
            </div>
        </div>

    </div>
</div>

<?php if (!$is_embed): ?>
<script>
<?php if (isset($_GET['autoprint'])): ?>
window.addEventListener('load', () => setTimeout(() => { window.print(); }, 700));
window.addEventListener('afterprint', () => window.close());
<?php endif; ?>

function downloadReceipt() {
    const receipt = document.getElementById('receipt');
    const btn     = document.getElementById('btnDownload');
    btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> A gerar...';
    btn.disabled  = true;

    html2canvas(receipt, { backgroundColor: null, scale: 3, useCORS: true, logging: false })
        .then(canvas => {
            const a = document.createElement('a');
            a.download = 'recibo_<?= $receipt_number ?>.png';
            a.href = canvas.toDataURL('image/png');
            a.click();
        })
        .catch(err => { console.error(err); alert('Erro ao gerar imagem.'); })
        .finally(() => {
            btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Descarregar';
            btn.disabled = false;
        });
}
</script>
</body>
</html>
<?php endif; ?>
