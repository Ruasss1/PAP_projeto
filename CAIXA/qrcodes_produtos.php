<?php
/**
 * Etiquetas QR Code para produtos (não pesados)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';

$store_id = $_SESSION['store_id'] ?? 1;
$weighted = ['Frutas', 'Legumes', 'Carnes', 'Peixe', 'Congelados'];
$placeholders = implode(',', array_fill(0, count($weighted), '?')); 

$stmt = $pdo->prepare("
    SELECT id, name, barcode, category, sell_price
    FROM products
    WHERE active = 1
      AND category NOT IN ($placeholders)
    ORDER BY category, name
");
$stmt->execute($weighted);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>QR Codes Produtos</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Inter, sans-serif; background: #f4f4f4; padding: 20px; }
  .controls { background: #fff; padding: 16px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; gap: 12px; align-items: center; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
  .controls button { padding: 8px 20px; border-radius: 7px; border: none; background: #111; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; }
  .controls button:hover { background: #333; }
  .grid { display: grid; grid-template-columns: repeat(auto-fill, 120px); gap: 12px; }
  .label {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 10px 8px 8px;
    text-align: center;
    break-inside: avoid;
  }
  .label canvas { display: block; margin: 0 auto 6px; }
  .label-name { font-size: 10px; font-weight: 600; line-height: 1.3; margin-bottom: 3px; color: #111; }
  .label-barcode { font-size: 8px; color: #888; font-family: monospace; }
  .label-price { font-size: 11px; font-weight: 700; color: #111; margin-top: 3px; }
  @media print {
    body { background: #fff; padding: 0; }
    .controls { display: none; }
    .grid { gap: 6px; }
    .label { border: 1px solid #ccc; border-radius: 4px; }
  }
</style>
</head>
<body>

<div class="controls">
  <strong style="font-size:15px">QR Codes — <?= count($products) ?> produtos</strong>
  <button onclick="window.print()">🖨️ Imprimir</button>
  <a href="/CAIXA/" style="padding:8px 20px;border-radius:7px;border:1px solid #ddd;font-size:13px;text-decoration:none;color:#333;">← Voltar à Caixa</a>
  <span style="font-size:12px;color:#888;margin-left:auto;">Categorias pesadas excluídas (Frutas, Legumes, Carnes, Peixe, Congelados)</span>
</div>

<div class="grid" id="labelGrid">
  <?php foreach ($products as $p): ?>
  <div class="label">
    <div id="qr_<?= $p['id'] ?>" style="display:flex;justify-content:center;margin-bottom:6px"></div>
    <div class="label-name"><?= htmlspecialchars($p['name']) ?></div>
    <div class="label-barcode"><?= htmlspecialchars($p['barcode']) ?></div>
    <div class="label-price">€<?= number_format($p['sell_price'], 2) ?></div>
  </div>
  <?php endforeach; ?>
</div>

<script>
const products = <?= json_encode(array_map(fn($p) => ['id' => $p['id'], 'barcode' => !empty($p['barcode']) ? $p['barcode'] : 'PROD-'.$p['id']], $products)) ?>;

products.forEach(p => {
  const el = document.getElementById('qr_' + p.id);
  if (!el) return;
  try {
    new QRCode(el, {
      text: p.barcode,
      width: 90,
      height: 90,
      colorDark: '#000000',
      colorLight: '#ffffff',
      correctLevel: QRCode.CorrectLevel.M
    });
  } catch(e) {
    el.innerHTML = '<div style="width:90px;height:90px;display:flex;align-items:center;justify-content:center;background:#f5f5f5;font-size:9px;color:#999;border-radius:4px">Sem código</div>';
  }
});
</script>
</body>
</html>
