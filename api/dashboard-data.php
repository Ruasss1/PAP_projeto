<?php
/**
 * API DO DASHBOARD
 * Retorna dados em tempo real para auto-refresh
 */

header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();

// Obter loja atual
$current_store_id = get_current_store_id();

// Vendas de hoje (filtradas por loja)
$stmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total FROM sales WHERE DATE(sale_date) = CURDATE() AND (store_id = :store_id OR store_id IS NULL)");
$stmt->execute(['store_id' => $current_store_id]);
$today = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'today_sales' => floatval($today['total'] ?? 0),
    'today_count' => intval($today['count'] ?? 0),
    'store_id' => $current_store_id,
    'timestamp' => date('H:i:s')
]);
