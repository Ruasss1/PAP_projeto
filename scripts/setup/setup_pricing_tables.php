<?php
require_once __DIR__ . '/../../config/database.php';

$pdo = db_connect();

echo "Criando tabelas para o sistema de pricing...\n\n";

// Criar tabela categories
$pdo->exec('
    CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
');
echo "✅ Tabela categories criada\n";

// Inserir categorias padrão
$pdo->exec("
    INSERT INTO categories (name, description) VALUES
    ('Frutas', 'Frutas frescas e secas'),
    ('Legumes', 'Legumes e vegetais frescos'),
    ('Laticínios', 'Leite, queijo, iogurtes'),
    ('Carnes', 'Carnes frescas e congeladas'),
    ('Peixe', 'Peixe fresco e congelado'),
    ('Bebidas', 'Bebidas não alcoólicas'),
    ('Bebidas Alcoólicas', 'Vinhos, cervejas, licores'),
    ('Padaria', 'Pão e produtos de pastelaria'),
    ('Enlatados', 'Conservas e enlatados'),
    ('Congelados', 'Produtos congelados'),
    ('Limpeza', 'Produtos de limpeza'),
    ('Higiene Pessoal', 'Produtos de higiene e beleza'),
    ('Snacks', 'Batatas fritas, chocolates, doces'),
    ('Mercearia', 'Arroz, massa, azeite, etc')
    ON DUPLICATE KEY UPDATE name=name
");
echo "✅ Categorias padrão inseridas\n\n";

// Criar tabela pricing_strategies
$pdo->exec('
    CREATE TABLE IF NOT EXISTS pricing_strategies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        markup_percent DECIMAL(5,2),
        margin_percent DECIMAL(5,2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
');
echo "✅ Tabela pricing_strategies criada\n";

// Inserir estratégias padrão
$pdo->exec("
    INSERT INTO pricing_strategies (name, description, markup_percent, margin_percent) VALUES
    ('Baixo Volume', 'Para produtos de baixo volume de vendas', 45.00, 31.00),
    ('Médio Volume', 'Para produtos de volume médio', 35.00, 26.00),
    ('Alto Volume', 'Para produtos de alto volume', 25.00, 20.00),
    ('Premium', 'Para produtos premium', 60.00, 37.50),
    ('Básico', 'Para produtos básicos', 15.00, 13.00)
    ON DUPLICATE KEY UPDATE name=name
");
echo "✅ Estratégias padrão inseridas\n\n";

// Criar tabela promotions
$pdo->exec('
    CREATE TABLE IF NOT EXISTS promotions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT,
        name VARCHAR(200) NOT NULL,
        discount_type ENUM("percentage", "fixed") DEFAULT "percentage",
        discount_value DECIMAL(10,2) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )
');
echo "✅ Tabela promotions criada\n\n";

echo "🎉 TODAS AS TABELAS CRIADAS COM SUCESSO!\n";
echo "\nTabelas criadas:\n";
echo "- categories (14 categorias)\n";
echo "- pricing_strategies (5 estratégias)\n";
echo "- promotions\n";
