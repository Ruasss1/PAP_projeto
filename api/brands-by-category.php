<?php
/**
 * API para obter marcas por categoria
 * Retorna as marcas existentes para uma categoria específica
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();

$category = $_GET['category'] ?? '';

if (empty($category)) {
    // Se não há categoria, retornar todas as marcas
    $stmt = $pdo->query("SELECT DISTINCT brand FROM products WHERE active = 1 AND brand IS NOT NULL AND brand != '' ORDER BY brand");
} else {
    // Retornar marcas apenas da categoria selecionada
    $stmt = $pdo->prepare("SELECT DISTINCT brand FROM products WHERE active = 1 AND category = ? AND brand IS NOT NULL AND brand != '' ORDER BY brand");
    $stmt->execute([$category]);
}

$brands = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode([
    'brands' => $brands,
    'count' => count($brands)
]);
