<?php
require_once 'config/database.php';
$pdo = db_connect();

// Seed category pricing rules
$pdo->exec("DELETE FROM category_pricing_rules;");
$pdo->exec("INSERT INTO category_pricing_rules (category, default_markup_percent, min_margin_percent, max_discount_percent, active) VALUES
    ('Frutas', 35, 10, 40, 1),
    ('Padaria', 30, 8, 35, 1),
    ('Bebidas', 25, 12, 25, 1)
;");

// Seed margin analysis (últimos 90 dias)
$pdo->exec("DELETE FROM margin_analysis;");
$pdo->exec("INSERT INTO margin_analysis (category, product_name, cost_price, sell_price, margin_percent, markup_percent, analyzed_at, product_id) VALUES
    ('Frutas', 'Maçã', 0.20, 0.50, ((0.50-0.20)/0.50*100), ((0.50-0.20)/0.20*100), DATE_SUB(NOW(), INTERVAL 10 DAY), 1),
    ('Padaria', 'Pão', 0.10, 0.30, ((0.30-0.10)/0.30*100), ((0.30-0.10)/0.10*100), DATE_SUB(NOW(), INTERVAL 5 DAY), 2),
    ('Bebidas', 'Leite', 0.40, 0.80, ((0.80-0.40)/0.80*100), ((0.80-0.40)/0.40*100), DATE_SUB(NOW(), INTERVAL 3 DAY), 3)
;");

// Seed promotions
$pdo->exec("DELETE FROM promotions;");
$pdo->exec("INSERT INTO promotions (product_id, name, discount_type, discount_value, start_date, end_date, is_active) VALUES
    (1, 'Promo Maçã 10%', 'percentage', 10, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 1),
    (2, 'Pão -0.05€', 'fixed', 0.05, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 1),
    (3, 'Leite 15%', 'percentage', 15, DATE_SUB(CURDATE(), INTERVAL 7 DAY), DATE_SUB(CURDATE(), INTERVAL 1 DAY), 0)
;");

echo "Seeds ok\n";
