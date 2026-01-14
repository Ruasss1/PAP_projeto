<?php
require_once 'config/database.php';
$pdo = db_connect();

$pdo->exec("CREATE TABLE IF NOT EXISTS price_strategies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    markup_percent DECIMAL(5,2) DEFAULT 30.00,
    min_price DECIMAL(10,2) NULL,
    max_price DECIMAL(10,2) NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);");

$pdo->exec("CREATE TABLE IF NOT EXISTS category_pricing_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(100) NOT NULL UNIQUE,
    default_markup_percent DECIMAL(5,2) DEFAULT 30.00,
    min_margin_percent DECIMAL(5,2) DEFAULT 10.00,
    max_discount_percent DECIMAL(5,2) DEFAULT 50.00,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);");

$pdo->exec("CREATE TABLE IF NOT EXISTS margin_analysis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(100),
    product_name VARCHAR(255),
    cost_price DECIMAL(10,2),
    sell_price DECIMAL(10,2),
    margin_percent DECIMAL(6,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);");

echo "OK\n";
