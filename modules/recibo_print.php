<?php
/**
 * RECIBO VISUAL - Estilo talão de supermercado
 * Visualização realista + Download em imagem
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

$sale_id = intval($_GET['id'] ?? 0);

if (!$sale_id || !$pdo) {
    die('Recibo não encontrado.');
}

// Dados da venda
$stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    die('Venda não encontrada.');
}

// Itens da venda
$stmt = $pdo->prepare("SELECT si.quantity, si.price, p.name, p.barcode 
    FROM sale_items si 
    JOIN products p ON p.id = si.product_id 
    WHERE si.sale_id = ?
    ORDER BY si.id ASC");
$stmt->execute([$sale_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular subtotal e IVA
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$iva_rate = 23;
$iva_value = round($subtotal * $iva_rate / (100 + $iva_rate), 2);
$base_value = $subtotal - $iva_value;

// Dados da loja
$store_name = 'Supermercado PAP';
$store_address = 'Rua Principal, nº 123';
$store_city = '1000-001 Lisboa';
$store_nif = '999 999 999';
$store_phone = '210 000 000';

// Número de recibo formatado
$receipt_number = str_pad($sale['id'], 8, '0', STR_PAD_LEFT);
$date = date('d/m/Y', strtotime($sale['sale_date']));
$time = date('H:i:s', strtotime($sale['sale_date']));

// Modo embed — só devolve o HTML do recibo (para modal inline)
$is_embed = isset($_GET['embed']) && $_GET['embed'] == '1';

if (!$is_embed):
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo #<?= $receipt_number ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: #1a1a2e;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            min-height: 100vh;
            padding: 40px 20px;
            font-family: 'JetBrains Mono', monospace;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-bottom: 30px;
        }

        .actions button, .actions a {
            padding: 12px 28px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            font-family: 'Inter', -apple-system, sans-serif;
        }

        .btn-download {
            background: var(--text-primary, #fafafa);
            color: var(--bg-primary, #09090b);
        }
        .btn-download:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.3); }

        .btn-print {
            background: #22c55e;
            color: white;
        }
        .btn-print:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(34,197,94,0.4); }

        .btn-back {
            background: #27272a;
            color: #a1a1aa;
            border: 1px solid #3f3f46;
        }
        .btn-back:hover { background: #3f3f46; color: white; }

        /* ===== RECIBO ESTILO TALÃO ===== */
        .receipt-wrapper {
            position: relative;
            filter: drop-shadow(0 20px 60px rgba(0,0,0,0.5));
        }

        .receipt {
            background: #fefefa;
            width: 320px;
            padding: 30px 24px;
            color: #1a1a1a;
            font-size: 12px;
            line-height: 1.5;
            position: relative;
        }

        /* Efeito de papel rasgado no topo */
        .receipt::before {
            content: '';
            position: absolute;
            top: -8px;
            left: 0;
            right: 0;
            height: 8px;
            background: linear-gradient(135deg, #fefefa 33.33%, transparent 33.33%) 0 0,
                        linear-gradient(225deg, #fefefa 33.33%, transparent 33.33%) 0 0;
            background-size: 12px 8px;
            background-repeat: repeat-x;
        }

        /* Efeito de papel rasgado em baixo */
        .receipt::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            right: 0;
            height: 8px;
            background: linear-gradient(315deg, #fefefa 33.33%, transparent 33.33%) 0 0,
                        linear-gradient(45deg, #fefefa 33.33%, transparent 33.33%) 0 0;
            background-size: 12px 8px;
            background-repeat: repeat-x;
        }

        /* Textura papel */
        .receipt-inner {
            position: relative;
        }

        .receipt-inner::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.03'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 1;
        }

        .store-header {
            text-align: center;
            margin-bottom: 16px;
        }

        .store-logo {
            font-size: 28px;
            margin-bottom: 6px;
        }

        .store-name {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .store-info {
            font-size: 10px;
            color: #666;
            line-height: 1.6;
        }

        .divider {
            border: none;
            border-top: 1px dashed #ccc;
            margin: 12px 0;
        }

        .divider-double {
            border: none;
            border-top: 2px solid #333;
            margin: 12px 0;
        }

        .receipt-meta {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #555;
        }

        .receipt-meta-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
        }

        .items-header {
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            font-size: 11px;
            padding-bottom: 4px;
            border-bottom: 1px solid #333;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .item-row {
            margin-bottom: 6px;
        }

        .item-name {
            font-size: 11.5px;
            font-weight: 500;
            display: block;
            margin-bottom: 1px;
        }

        .item-detail {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #555;
        }

        .item-barcode {
            font-size: 9px;
            color: #999;
        }

        .totals-section {
            margin-top: 8px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            margin-bottom: 3px;
        }

        .total-row.grand-total {
            font-size: 18px;
            font-weight: 700;
            margin-top: 6px;
            padding-top: 8px;
            border-top: 2px solid #333;
        }

        .total-row .label {
            color: #555;
        }

        .total-row.grand-total .label,
        .total-row.grand-total .value {
            color: #1a1a1a;
        }

        .payment-info {
            text-align: center;
            margin: 14px 0;
            padding: 8px;
            background: #f5f5f0;
            border-radius: 4px;
        }

        .payment-method {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .iva-table {
            width: 100%;
            font-size: 10px;
            border-collapse: collapse;
            margin: 8px 0;
        }

        .iva-table th {
            text-align: left;
            font-weight: 600;
            padding: 3px 0;
            border-bottom: 1px solid #ddd;
            font-size: 9px;
            text-transform: uppercase;
            color: #888;
        }

        .iva-table td {
            padding: 3px 0;
            color: #555;
        }

        .footer-section {
            text-align: center;
            margin-top: 16px;
        }

        .footer-msg {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .footer-small {
            font-size: 9px;
            color: #999;
            line-height: 1.6;
        }

        .barcode-area {
            text-align: center;
            margin: 14px 0 6px;
            font-family: 'Libre Barcode 128', monospace;
            font-size: 36px;
            letter-spacing: 2px;
            color: #1a1a1a;
        }

        .barcode-text {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            color: #999;
            margin-top: -4px;
        }

        .nif-section {
            text-align: center;
            font-size: 11px;
            color: #444;
            margin: 6px 0;
        }

        @media print {
            body { background: white; padding: 0; }
            .actions { display: none !important; }
            .receipt-wrapper { filter: none; }
            .receipt { box-shadow: none; }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>
<body>

    <!-- Botões de ação -->
    <div class="actions">
        <a href="/modules/recibos.php" class="btn-back">← Voltar</a>
        <button onclick="window.print()" class="btn-print">🖨️ Imprimir</button>
        <button onclick="downloadReceipt()" class="btn-download">📥 Descarregar</button>
    </div>
<?php endif; // fim do if (!$is_embed) ?>

<?php if ($is_embed): ?>
<style>
    .receipt-wrapper { position: relative; filter: drop-shadow(0 20px 60px rgba(0,0,0,0.5)); }
    .receipt { background: #fefefa; width: 320px; padding: 30px 24px; color: #1a1a1a; font-family: 'JetBrains Mono', 'Courier New', monospace; font-size: 12px; line-height: 1.5; position: relative; }
    .receipt::before { content: ''; position: absolute; top: -8px; left: 0; right: 0; height: 8px; background: linear-gradient(135deg, #fefefa 33.33%, transparent 33.33%) 0 0, linear-gradient(225deg, #fefefa 33.33%, transparent 33.33%) 0 0; background-size: 12px 8px; background-repeat: repeat-x; }
    .receipt::after { content: ''; position: absolute; bottom: -8px; left: 0; right: 0; height: 8px; background: linear-gradient(315deg, #fefefa 33.33%, transparent 33.33%) 0 0, linear-gradient(45deg, #fefefa 33.33%, transparent 33.33%) 0 0; background-size: 12px 8px; background-repeat: repeat-x; }
    .store-header { text-align: center; margin-bottom: 16px; }
    .store-logo { font-size: 28px; margin-bottom: 6px; }
    .store-name { font-size: 18px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 4px; }
    .store-info { font-size: 10px; color: #666; line-height: 1.6; }
    .divider { border: none; border-top: 1px dashed #ccc; margin: 12px 0; }
    .divider-double { border: none; border-top: 2px solid #333; margin: 12px 0; }
    .receipt-meta { display: flex; justify-content: space-between; font-size: 11px; color: #555; }
    .receipt-meta-row { display: flex; justify-content: space-between; margin-bottom: 2px; }
    .items-header { display: flex; justify-content: space-between; font-weight: 700; font-size: 11px; padding-bottom: 4px; border-bottom: 1px solid #333; margin-bottom: 6px; text-transform: uppercase; }
    .item-row { margin-bottom: 6px; }
    .item-name { font-size: 11.5px; font-weight: 500; display: block; margin-bottom: 1px; }
    .item-detail { display: flex; justify-content: space-between; font-size: 11px; color: #555; }
    .item-barcode { font-size: 9px; color: #999; }
    .totals-section { margin-top: 8px; }
    .total-row { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 3px; }
    .total-row.grand-total { font-size: 18px; font-weight: 700; margin-top: 6px; padding-top: 8px; border-top: 2px solid #333; }
    .total-row .label { color: #555; }
    .total-row.grand-total .label, .total-row.grand-total .value { color: #1a1a1a; }
    .payment-info { text-align: center; margin: 14px 0; padding: 8px; background: #f5f5f0; border-radius: 4px; }
    .payment-method { font-size: 13px; font-weight: 700; text-transform: uppercase; }
    .iva-table { width: 100%; font-size: 10px; border-collapse: collapse; margin: 8px 0; }
    .iva-table th { text-align: left; font-weight: 600; padding: 3px 0; border-bottom: 1px solid #ddd; font-size: 9px; text-transform: uppercase; color: #888; }
    .iva-table td { padding: 3px 0; color: #555; }
    .footer-section { text-align: center; margin-top: 16px; }
    .footer-msg { font-size: 12px; font-weight: 600; margin-bottom: 6px; }
    .footer-small { font-size: 9px; color: #999; line-height: 1.6; }
    .barcode-area { text-align: center; margin: 14px 0 6px; font-family: 'Libre Barcode 128', monospace; font-size: 36px; letter-spacing: 2px; color: #1a1a1a; }
    .barcode-text { font-family: 'JetBrains Mono', monospace; font-size: 10px; color: #999; margin-top: -4px; }
    .nif-section { text-align: center; font-size: 11px; color: #444; margin: 6px 0; }
</style>
<?php endif; ?>

    <!-- Recibo -->
    <div class="receipt-wrapper" id="receipt">
        <div class="receipt">
            <div class="receipt-inner">
                
                <!-- Cabeçalho da loja -->
                <div class="store-header">
                    <div class="store-logo">🛒</div>
                    <div class="store-name"><?= htmlspecialchars($store_name) ?></div>
                    <div class="store-info">
                        <?= htmlspecialchars($store_address) ?><br>
                        <?= htmlspecialchars($store_city) ?><br>
                        NIF: <?= $store_nif ?> | Tel: <?= $store_phone ?>
                    </div>
                </div>

                <hr class="divider-double">

                <!-- Metadados do recibo -->
                <div class="receipt-meta">
                    <div>
                        <div class="receipt-meta-row">
                            <span>Recibo Nº:</span>&nbsp;
                            <strong><?= $receipt_number ?></strong>
                        </div>
                        <div class="receipt-meta-row">
                            <span>Operador:</span>&nbsp;
                            <span><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div class="receipt-meta-row">
                            <span><?= $date ?></span>
                        </div>
                        <div class="receipt-meta-row">
                            <span><?= $time ?></span>
                        </div>
                    </div>
                </div>

                <hr class="divider">

                <!-- Cabeçalho dos itens -->
                <div class="items-header">
                    <span>Artigo</span>
                    <span>Total</span>
                </div>

                <!-- Lista de itens -->
                <?php foreach ($items as $item): 
                    $line_total = $item['price'] * $item['quantity'];
                ?>
                <div class="item-row">
                    <span class="item-name"><?= htmlspecialchars($item['name']) ?></span>
                    <div class="item-detail">
                        <span>
                            <?= $item['quantity'] ?> x €<?= number_format($item['price'], 2, ',', '.') ?>
                            <?php if ($item['barcode']): ?>
                                <span class="item-barcode">(<?= htmlspecialchars($item['barcode']) ?>)</span>
                            <?php endif; ?>
                        </span>
                        <span><strong>€<?= number_format($line_total, 2, ',', '.') ?></strong></span>
                    </div>
                </div>
                <?php endforeach; ?>

                <hr class="divider">

                <!-- Totais -->
                <div class="totals-section">
                    <div class="total-row">
                        <span class="label">Subtotal (<?= count($items) ?> artigos)</span>
                        <span>€<?= number_format($subtotal, 2, ',', '.') ?></span>
                    </div>

                    <div class="total-row grand-total">
                        <span class="label">TOTAL</span>
                        <span class="value">€<?= number_format($sale['total'], 2, ',', '.') ?></span>
                    </div>
                </div>

                <!-- Pagamento -->
                <div class="payment-info">
                    <div style="font-size: 10px; color: #888; margin-bottom: 2px;">Método de Pagamento</div>
                    <div class="payment-method"><?= htmlspecialchars($sale['payment_method'] ?? 'Dinheiro') ?></div>
                </div>

                <hr class="divider">

                <!-- Tabela IVA -->
                <table class="iva-table">
                    <thead>
                        <tr>
                            <th>Taxa</th>
                            <th>Base</th>
                            <th>IVA</th>
                            <th>Total</th>
                        </tr>
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
                <div class="nif-section">
                    <strong>NIF do Cliente:</strong> <?= htmlspecialchars($sale['nif']) ?>
                </div>
                <?php endif; ?>

                <hr class="divider">

                <!-- Código de barras simulado -->
                <div class="barcode-area">
                    <?= $receipt_number ?>
                </div>
                <div style="text-align: center;">
                    <span class="barcode-text"><?= $receipt_number ?></span>
                </div>

                <hr class="divider">

                <!-- Rodapé -->
                <div class="footer-section">
                    <div class="footer-msg">Obrigado pela sua compra!</div>
                    <div class="footer-small">
                        Conserve este talão para<br>
                        eventuais trocas ou devoluções.<br>
                        Prazo: 15 dias com talão original.<br><br>
                        <?= $store_name ?> — <?= date('Y') ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

<?php if (!$is_embed): ?>
    <script>
    function downloadReceipt() {
        const receipt = document.getElementById('receipt');
        const btn = document.querySelector('.btn-download');
        btn.textContent = '⏳ A gerar...';
        btn.disabled = true;

        html2canvas(receipt, {
            backgroundColor: null,
            scale: 3,
            useCORS: true,
            logging: false
        }).then(canvas => {
            const link = document.createElement('a');
            link.download = 'recibo_<?= $receipt_number ?>.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
            btn.innerHTML = '📥 Descarregar';
            btn.disabled = false;
        }).catch(err => {
            console.error('Erro ao gerar imagem:', err);
            btn.innerHTML = '📥 Descarregar';
            btn.disabled = false;
            alert('Erro ao gerar imagem do recibo.');
        });
    }
    </script>
</body>
</html>
<?php endif; ?>
