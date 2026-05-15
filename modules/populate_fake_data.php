<?php
// populate_fake_data.php
require_once __DIR__ . '/../includes/functions.php'; // Caminho corrigido para o includes do projecto

$pdo = db_connect();

// -----------------------------
// 1. Criar tabelas se não existirem
// -----------------------------
$pdo->exec("
CREATE TABLE IF NOT EXISTS daily_profit (
    day DATE PRIMARY KEY,
    revenue DECIMAL(10,2) DEFAULT 0,
    cogs DECIMAL(10,2) DEFAULT 0,
    profit DECIMAL(10,2) DEFAULT 0
)");

$pdo->exec("
CREATE TABLE IF NOT EXISTS monthly_top_product (
    month CHAR(7) PRIMARY KEY, -- 'YYYY-MM'
    product_id INT NOT NULL,
    total_sold INT NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id)
)");

// -----------------------------
// 2. Popular vendas e lucros dos últimos 6 meses
// -----------------------------
$start = new DateTime('-5 months');
$start->modify('first day of this month'); // começar no primeiro dia do mês
$end = new DateTime(); // hoje

while($start <= $end){
    $day = $start->format('Y-m-d');

    // Inserir venda fake
    $total = round(50 + rand(0,300),2);
    // Inserir venda (sale_date e total — a tabela `sales` não tem `created_at` na versão actual do esquema)
    $stmt = $pdo->prepare("INSERT INTO sales (sale_date, total) VALUES (?, ?)");
    $stmt->execute([$day, $total]);
    $saleId = $pdo->lastInsertId();

    // Produtos fake (1,2,3) com quantidades aleatórias
    for($prodId=1; $prodId<=3; $prodId++){
        $qty = rand(1,10);
        $stmt = $pdo->prepare("
            INSERT INTO sale_items (sale_id, product_id, quantity, price)
            SELECT ?, id, ?, sell_price
            FROM products
            WHERE id = ?
        ");
        $stmt->execute([$saleId, $qty, $prodId]);
    }

    // Calcular lucro diário
    $stmt = $pdo->prepare("
        INSERT INTO daily_profit (day, revenue, cogs, profit)
        SELECT ?, 
               SUM(si.price * si.quantity), 
               SUM(p.cost_price * si.quantity),
               SUM(si.price * si.quantity) - SUM(p.cost_price * si.quantity)
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        JOIN sales s ON s.id = si.sale_id
        WHERE s.id = ?
        ON DUPLICATE KEY UPDATE revenue=VALUES(revenue), cogs=VALUES(cogs), profit=VALUES(profit)
    ");
    $stmt->execute([$day, $saleId]);

    $start->modify('+1 day');
}

// -----------------------------
// 3. Calcular produto mais vendido por mês
// -----------------------------
$pdo->exec("DELETE FROM monthly_top_product"); // limpa tabela

// Inserir top produto por mês
$pdo->exec("
INSERT INTO monthly_top_product (month, product_id, total_sold)
SELECT t.month, t.product_id, t.total_sold
FROM (
    SELECT DATE_FORMAT(s.sale_date, '%Y-%m') AS month, si.product_id, SUM(si.quantity) AS total_sold
    FROM sale_items si
    JOIN sales s ON s.id = si.sale_id
    GROUP BY month, si.product_id
) t
JOIN (
    SELECT month, MAX(total_sold) AS max_sold
    FROM (
        SELECT DATE_FORMAT(s.sale_date, '%Y-%m') AS month, si.product_id, SUM(si.quantity) AS total_sold
        FROM sale_items si
        JOIN sales s ON s.id = si.sale_id
        GROUP BY month, si.product_id
    ) x
    GROUP BY month
) m ON t.month = m.month AND t.total_sold = m.max_sold
");

echo "Dados falsos populados com sucesso para os últimos 6 meses!\n";
