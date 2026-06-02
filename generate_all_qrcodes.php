<?php
/**
 * Script para gerar QR codes para todas as promoções
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/qrcode.php';

$pdo = db_connect();

// Buscar promoções sem QR code
$stmt = $pdo->prepare("SELECT id FROM promotions WHERE qr_code IS NULL");
$stmt->execute();
$promos = $stmt->fetchAll();

echo "Gerando QR codes para " . count($promos) . " promoções...\n";

foreach ($promos as $promo) {
    $id = $promo['id'];
    $code = generate_promotion_code();
    $qr = generate_qrcode($id, $code);
    
    if ($qr) {
        $update = $pdo->prepare("UPDATE promotions SET qr_code = ? WHERE id = ?");
        $update->execute([$qr, $id]);
        echo "✅ Promoção $id - QR code gerado\n";
    } else {
        echo "❌ Promoção $id - Falha ao gerar QR code\n";
    }
}

echo "\nConcluído!\n";
