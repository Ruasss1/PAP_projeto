-- =====================================================
-- MIGRATION 008 - New Features
-- Attendance, Settings, Stock movements triggers
-- =====================================================

-- Controlo de Ponto
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    store_id INT NOT NULL DEFAULT 1,
    clock_in DATETIME NOT NULL,
    clock_out DATETIME NULL,
    break_minutes INT DEFAULT 0,
    overtime_minutes INT DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_employee (employee_id),
    KEY idx_date (clock_in)
);

-- Settings defaults
INSERT IGNORE INTO settings (`key`, value, description) VALUES
('company_name', 'PAP Market', 'Nome da empresa'),
('company_nif', '123456789', 'NIF da empresa'),
('company_address', 'Rua Principal, 123, Lisboa', 'Morada da empresa'),
('company_phone', '210000000', 'Telefone'),
('company_email', 'geral@papmarket.pt', 'Email de contacto'),
('currency', 'EUR', 'Moeda'),
('currency_symbol', '€', 'Símbolo da moeda'),
('vat_rate', '23', 'Taxa de IVA padrão (%)'),
('low_stock_threshold', '10', 'Limiar de stock baixo'),
('work_hours_per_day', '8', 'Horas de trabalho por dia'),
('overtime_rate', '1.5', 'Multiplicador horas extra'),
('backup_retention_days', '30', 'Dias de retenção de backups'),
('receipt_footer', 'Obrigado pela sua visita!', 'Rodapé do recibo'),
('enable_loyalty', '1', 'Programa fidelidade ativo'),
('loyalty_points_per_euro', '1', 'Pontos por euro gasto');
