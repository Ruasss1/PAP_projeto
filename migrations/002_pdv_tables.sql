-- Migração: Tabelas do PDV (Ponto de Venda)
-- Data: 2026-02-24

-- Tabela de Turnos de Caixa
CREATE TABLE IF NOT EXISTS cash_register_shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_number VARCHAR(50) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    opening_balance DECIMAL(10,2) DEFAULT 0.00,
    closing_balance DECIMAL(10,2) DEFAULT NULL,
    expected_balance DECIMAL(10,2) DEFAULT NULL,
    difference DECIMAL(10,2) DEFAULT NULL,
    status ENUM('open', 'closed') DEFAULT 'open',
    notes TEXT,
    opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Tabela de Movimentos de Caixa
CREATE TABLE IF NOT EXISTS cash_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_id INT NOT NULL,
    type ENUM('entry', 'withdrawal', 'adjustment') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description VARCHAR(255),
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shift_id) REFERENCES cash_register_shifts(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Tabela de Vendas Suspensas
CREATE TABLE IF NOT EXISTS suspended_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    suspension_code VARCHAR(50) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    customer_id INT DEFAULT NULL,
    items JSON NOT NULL,
    notes TEXT,
    status ENUM('pending', 'resumed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Tabela de Cupões
CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    min_purchase DECIMAL(10,2) DEFAULT 0.00,
    max_uses INT DEFAULT NULL,
    uses_count INT DEFAULT 0,
    valid_from DATE NOT NULL,
    valid_until DATE NOT NULL,
    active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Uso de Cupões
CREATE TABLE IF NOT EXISTS coupon_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coupon_id INT NOT NULL,
    receipt_id INT,
    customer_id INT DEFAULT NULL,
    discount_amount DECIMAL(10,2) NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coupon_id) REFERENCES coupons(id)
);

-- Tabela de Recibos
CREATE TABLE IF NOT EXISTS receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(50) NOT NULL UNIQUE,
    shift_id INT,
    user_id INT NOT NULL,
    customer_id INT DEFAULT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50),
    status ENUM('completed', 'cancelled', 'refunded') DEFAULT 'completed',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (shift_id) REFERENCES cash_register_shifts(id)
);

-- Tabela de Itens do Recibo
CREATE TABLE IF NOT EXISTS receipt_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255),
    quantity DECIMAL(10,3) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    discount_percent DECIMAL(5,2) DEFAULT 0.00,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    subtotal DECIMAL(10,2) NOT NULL,
    is_weighted TINYINT DEFAULT 0,
    weight_kg DECIMAL(10,3) DEFAULT NULL,
    FOREIGN KEY (receipt_id) REFERENCES receipts(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Tabela de Pagamentos do Recibo
CREATE TABLE IF NOT EXISTS receipt_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_id INT NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (receipt_id) REFERENCES receipts(id)
);

-- Tabela de Clientes
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(50),
    nif VARCHAR(20),
    points INT DEFAULT 0,
    total_spent DECIMAL(10,2) DEFAULT 0.00,
    active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de Devoluções
CREATE TABLE IF NOT EXISTS returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_number VARCHAR(50) NOT NULL UNIQUE,
    original_receipt_id INT NOT NULL,
    user_id INT NOT NULL,
    reason TEXT,
    total_refund DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (original_receipt_id) REFERENCES receipts(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Tabela de Itens de Devolução
CREATE TABLE IF NOT EXISTS return_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    refund_amount DECIMAL(10,2) NOT NULL,
    reason VARCHAR(255),
    FOREIGN KEY (return_id) REFERENCES returns(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Inserir cupões de exemplo
INSERT IGNORE INTO coupons (code, discount_type, discount_value, min_purchase, max_uses, valid_from, valid_until) VALUES
('WELCOME10', 'percentage', 10.00, 20.00, 100, '2026-01-01', '2026-12-31'),
('DESCONTO5', 'fixed', 5.00, 30.00, 50, '2026-01-01', '2026-06-30'),
('NATAL20', 'percentage', 20.00, 50.00, 200, '2026-12-01', '2026-12-31');

-- Inserir alguns clientes de exemplo
INSERT IGNORE INTO customers (name, email, phone, nif, points) VALUES
('Cliente Geral', NULL, NULL, NULL, 0),
('João Silva', 'joao.silva@email.pt', '912345678', '123456789', 150),
('Maria Santos', 'maria.santos@email.pt', '923456789', '987654321', 320);
