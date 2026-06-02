-- =====================================================
-- MIGRATION 005: SISTEMA PDV (PONTO DE VENDA)
-- Data: 16/01/2026
-- Descrição: Tabelas para sistema de caixa completo
-- =====================================================

-- Tabela de Recibos/Vendas
CREATE TABLE IF NOT EXISTS receipts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    receipt_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INTEGER NOT NULL,
    customer_id INTEGER NULL,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    final_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(50) NOT NULL,
    payment_details TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'completed',
    loyalty_points_earned INTEGER DEFAULT 0,
    loyalty_points_redeemed INTEGER DEFAULT 0,
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

-- Tabela de Itens do Recibo
CREATE TABLE IF NOT EXISTS receipt_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    receipt_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_sku VARCHAR(100) NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    discount_percent DECIMAL(5,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    subtotal DECIMAL(10,2) NOT NULL,
    is_weighted BOOLEAN DEFAULT 0,
    weight_kg DECIMAL(10,3) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (receipt_id) REFERENCES receipts(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Tabela de Vendas Suspensas
CREATE TABLE IF NOT EXISTS suspended_sales (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    suspension_code VARCHAR(50) UNIQUE NOT NULL,
    user_id INTEGER NOT NULL,
    customer_id INTEGER NULL,
    items_json TEXT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    status VARCHAR(20) DEFAULT 'suspended',
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

-- Tabela de Pagamentos (para pagamentos mistos)
CREATE TABLE IF NOT EXISTS receipt_payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    receipt_id INTEGER NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reference VARCHAR(100) NULL,
    status VARCHAR(20) DEFAULT 'completed',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (receipt_id) REFERENCES receipts(id) ON DELETE CASCADE
);

-- Tabela de Devoluções
CREATE TABLE IF NOT EXISTS returns (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    return_number VARCHAR(50) UNIQUE NOT NULL,
    original_receipt_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    customer_id INTEGER NULL,
    total_refund DECIMAL(10,2) NOT NULL,
    refund_method VARCHAR(50) NOT NULL,
    reason TEXT NULL,
    status VARCHAR(20) DEFAULT 'completed',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (original_receipt_id) REFERENCES receipts(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

-- Tabela de Itens Devolvidos
CREATE TABLE IF NOT EXISTS return_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    return_id INTEGER NOT NULL,
    receipt_item_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    refund_amount DECIMAL(10,2) NOT NULL,
    condition VARCHAR(50) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (return_id) REFERENCES returns(id) ON DELETE CASCADE,
    FOREIGN KEY (receipt_item_id) REFERENCES receipt_items(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Tabela de Cupões/Vouchers
CREATE TABLE IF NOT EXISTS coupons (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    type VARCHAR(20) NOT NULL,
    value DECIMAL(10,2) NOT NULL,
    min_purchase DECIMAL(10,2) DEFAULT 0,
    max_uses INTEGER NULL,
    uses_count INTEGER DEFAULT 0,
    valid_from DATETIME NOT NULL,
    valid_until DATETIME NOT NULL,
    status VARCHAR(20) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Uso de Cupões
CREATE TABLE IF NOT EXISTS coupon_usage (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    coupon_id INTEGER NOT NULL,
    receipt_id INTEGER NOT NULL,
    customer_id INTEGER NULL,
    discount_applied DECIMAL(10,2) NOT NULL,
    used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coupon_id) REFERENCES coupons(id),
    FOREIGN KEY (receipt_id) REFERENCES receipts(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

-- Tabela de Turnos de Caixa
CREATE TABLE IF NOT EXISTS cash_register_shifts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    shift_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INTEGER NOT NULL,
    opening_balance DECIMAL(10,2) NOT NULL,
    closing_balance DECIMAL(10,2) NULL,
    expected_balance DECIMAL(10,2) NULL,
    difference DECIMAL(10,2) NULL,
    total_sales DECIMAL(10,2) DEFAULT 0,
    total_cash DECIMAL(10,2) DEFAULT 0,
    total_card DECIMAL(10,2) DEFAULT 0,
    total_other DECIMAL(10,2) DEFAULT 0,
    notes TEXT NULL,
    opened_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    status VARCHAR(20) DEFAULT 'open',
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Tabela de Movimentos de Caixa (sangrias/reforços)
CREATE TABLE IF NOT EXISTS cash_movements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    shift_id INTEGER NOT NULL,
    type VARCHAR(20) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reason TEXT NULL,
    user_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shift_id) REFERENCES cash_register_shifts(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Índices para Performance
CREATE INDEX IF NOT EXISTS idx_receipts_number ON receipts(receipt_number);
CREATE INDEX IF NOT EXISTS idx_receipts_date ON receipts(created_at);
CREATE INDEX IF NOT EXISTS idx_receipts_user ON receipts(user_id);
CREATE INDEX IF NOT EXISTS idx_receipts_customer ON receipts(customer_id);
CREATE INDEX IF NOT EXISTS idx_receipts_status ON receipts(status);

CREATE INDEX IF NOT EXISTS idx_receipt_items_receipt ON receipt_items(receipt_id);
CREATE INDEX IF NOT EXISTS idx_receipt_items_product ON receipt_items(product_id);

CREATE INDEX IF NOT EXISTS idx_suspended_code ON suspended_sales(suspension_code);
CREATE INDEX IF NOT EXISTS idx_suspended_status ON suspended_sales(status);
CREATE INDEX IF NOT EXISTS idx_suspended_expires ON suspended_sales(expires_at);

CREATE INDEX IF NOT EXISTS idx_returns_number ON returns(return_number);
CREATE INDEX IF NOT EXISTS idx_returns_receipt ON returns(original_receipt_id);

CREATE INDEX IF NOT EXISTS idx_coupons_code ON coupons(code);
CREATE INDEX IF NOT EXISTS idx_coupons_status ON coupons(status);

CREATE INDEX IF NOT EXISTS idx_shifts_number ON cash_register_shifts(shift_number);
CREATE INDEX IF NOT EXISTS idx_shifts_user ON cash_register_shifts(user_id);
CREATE INDEX IF NOT EXISTS idx_shifts_status ON cash_register_shifts(status);

-- =====================================================
-- FIM DA MIGRATION 005
-- =====================================================
