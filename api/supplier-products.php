<?php
/**
 * API: Produtos de um Fornecedor
 * GET /api/supplier-products.php?supplier_id=X&store_id=Y
 */
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../includes/functions.php';

$supplier_id = intval($_GET['supplier_id'] ?? 0);
$store_id    = intval($_GET['store_id'] ?? get_current_store_id());

if ($supplier_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'supplier_id inválido']);
    exit;
}

$pdo = db_connect();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Sem ligação à BD']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, name, barcode, stock, min_stock, cost_price, category
    FROM products
    WHERE supplier_id = ? AND store_id = ? AND active = 1
    ORDER BY name ASC
");
$stmt->execute([$supplier_id, $store_id]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'products' => $products]);
