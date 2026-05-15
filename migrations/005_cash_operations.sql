-- Migration: Cash Operations for PDV
-- Data: 2026-01-17

-- Tabela de operações de caixa (sangrias, reforços, etc.)
CREATE TABLE IF NOT EXISTS cash_operations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_id INT NOT NULL,
    user_id INT NOT NULL,
    operation_type ENUM('sangria', 'reforco', 'abertura', 'fecho', 'ajuste') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    balance_before DECIMAL(10,2),
    balance_after DECIMAL(10,2),
    reason VARCHAR(255),
    authorized_by INT NULL,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shift_id) REFERENCES cash_register_shifts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (authorized_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Adicionar colunas à tabela receipts se não existirem
ALTER TABLE receipts ADD COLUMN IF NOT EXISTS nif VARCHAR(20) DEFAULT NULL;
ALTER TABLE receipts ADD COLUMN IF NOT EXISTS invoice_type ENUM('recibo', 'fatura_simplificada', 'fatura', 'guia_transporte') DEFAULT 'recibo';
ALTER TABLE receipts ADD COLUMN IF NOT EXISTS qr_code TEXT DEFAULT NULL;
ALTER TABLE receipts ADD COLUMN IF NOT EXISTS sent_email TINYINT DEFAULT 0;
ALTER TABLE receipts ADD COLUMN IF NOT EXISTS sent_sms TINYINT DEFAULT 0;
ALTER TABLE receipts ADD COLUMN IF NOT EXISTS email_address VARCHAR(255) DEFAULT NULL;
ALTER TABLE receipts ADD COLUMN IF NOT EXISTS phone_number VARCHAR(20) DEFAULT NULL;

-- Índices para performance
CREATE INDEX IF NOT EXISTS idx_cash_ops_shift ON cash_operations(shift_id);
CREATE INDEX IF NOT EXISTS idx_cash_ops_type ON cash_operations(operation_type);
CREATE INDEX IF NOT EXISTS idx_receipts_type ON receipts(invoice_type);
