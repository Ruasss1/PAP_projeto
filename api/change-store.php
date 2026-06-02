<?php
/**
 * API para mudar de loja
 * Recebe store_id e guarda na sessão
 */
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../includes/functions.php';

$store_id = 0;

// Suporta JSON e form-data
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($content_type, 'application/json')) {
    $body = json_decode(file_get_contents('php://input'), true);
    $store_id = isset($body['store_id']) ? (int)$body['store_id'] : 0;
} else {
    $store_id = isset($_POST['store_id']) ? (int)$_POST['store_id'] : 0;
}

if ($store_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de loja inválido']);
    exit;
}

// Verificar se a loja existe e está ativa
$store = get_store($store_id);

if (!$store || !$store['is_active']) {
    echo json_encode(['success' => false, 'error' => 'Loja não encontrada ou inativa']);
    exit;
}

// Guardar na sessão
set_current_store($store_id);

echo json_encode([
    'success' => true,
    'store' => [
        'id' => $store['id'],
        'name' => $store['name'],
        'city' => $store['city'] ?? 'Lisboa'
    ],
    'message' => 'Loja alterada para: ' . $store['name']
]);
