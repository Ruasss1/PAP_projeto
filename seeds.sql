-- seeds.sql - dados de exemplo
USE supermercado;

INSERT IGNORE INTO products (name, category, cost_price, sell_price, stock) VALUES
('Maçã','Frutas',0.20,0.50,50),
('Pão','Padaria',0.10,0.30,40),
('Leite','Bebidas',0.40,0.80,30);

INSERT IGNORE INTO suppliers (name, contact) VALUES
('Frutas & Companhia','contacto@frutas.example'),
('Padaria Local','padaria@example.com');

INSERT IGNORE INTO employees (name, role, salary) VALUES
('João','Gerente',900.00),('Maria','Caixa',700.00);

-- Additional inserts imported from schema.sql
INSERT IGNORE INTO `bebidas` (`id`, `name`, `cost_price`, `sell_price`, `stock`, `expiry_date`, `created_at`) VALUES
(1, 'Água 1.5L', 0.20, 0.50, 120, NULL, '2026-01-12 19:16:47'),
(2, 'Sumo de Laranja', 0.60, 1.20, 45, '2026-05-01', '2026-01-12 19:16:47'),
(3, 'Refrigerante Cola', 0.70, 1.50, 50, '2026-06-01', '2026-01-12 19:16:47'),
(4, 'Cerveja Lata', 0.50, 1.00, 90, '2026-08-01', '2026-01-12 19:16:47');

INSERT IGNORE INTO `congelados` (`id`, `name`, `cost_price`, `sell_price`, `stock`, `expiry_date`, `created_at`) VALUES
(1, 'Pizza Congelada', 2.00, 4.50, 20, '2026-07-01', '2026-01-12 19:16:47'),
(2, 'Hambúrguer Congelado', 1.50, 3.20, 30, '2026-06-15', '2026-01-12 19:16:47');

INSERT IGNORE INTO `employees` (`id`, `name`, `role`, `salary`, `created_at`) VALUES
(1, 'João', 'Gerente', 900.00, '2026-01-12 19:16:47'),
(2, 'Maria', 'Caixa', 700.00, '2026-01-12 19:16:47'),
(3, 'João', 'Gerente', 900.00, '2026-01-12 19:27:33'),
(4, 'Maria', 'Caixa', 700.00, '2026-01-12 19:27:33');

INSERT IGNORE INTO `frutas` (`id`, `name`, `cost_price`, `sell_price`, `stock`, `created_at`) VALUES
(1, 'Maçã', 0.20, 0.50, 50, '2026-01-12 19:16:47'),
(2, 'Banana', 0.15, 0.40, 60, '2026-01-12 19:16:47'),
(3, 'Laranja', 0.18, 0.45, 70, '2026-01-12 19:16:47'),
(4, 'Pera', 0.22, 0.55, 40, '2026-01-12 19:16:47'),
(5, 'Uva', 1.20, 2.50, 25, '2026-01-12 19:16:47');

INSERT IGNORE INTO `higiene` (`id`, `name`, `cost_price`, `sell_price`, `stock`, `created_at`) VALUES
(1, 'Papel Higiénico 6un', 1.50, 3.00, 40, '2026-01-12 19:16:47'),
(2, 'Sabonete', 0.40, 0.90, 60, '2026-01-12 19:16:47'),
(3, 'Champô', 1.80, 3.50, 35, '2026-01-12 19:16:47'),
(4, 'Pasta de Dentes', 1.20, 2.40, 45, '2026-01-12 19:16:47');

INSERT IGNORE INTO `laticinios` (`id`, `name`, `cost_price`, `sell_price`, `stock`, `expiry_date`, `created_at`) VALUES
(1, 'Leite', 0.40, 0.80, 30, '2026-02-15', '2026-01-12 19:16:47'),
(2, 'Iogurte Natural', 0.30, 0.70, 40, '2026-03-10', '2026-01-12 19:16:47'),
(3, 'Queijo Flamengo', 1.80, 3.20, 25, '2026-02-28', '2026-01-12 19:16:47'),
(4, 'Manteiga', 0.90, 1.60, 35, '2026-04-05', '2026-01-12 19:16:47');

INSERT IGNORE INTO `limpeza` (`id`, `name`, `cost_price`, `sell_price`, `stock`, `created_at`) VALUES
(1, 'Detergente Loiça', 1.20, 2.50, 30, '2026-01-12 19:16:47'),
(2, 'Detergente Roupa', 2.80, 5.50, 20, '2026-01-12 19:16:47'),
(3, 'Lixívia', 0.80, 1.60, 25, '2026-01-12 19:16:47');

INSERT IGNORE INTO `mercearia` (`id`, `name`, `cost_price`, `sell_price`, `stock`, `created_at`) VALUES
(1, 'Arroz 1kg', 0.90, 1.50, 100, '2026-01-12 19:16:47'),
(2, 'Massa 500g', 0.45, 0.90, 80, '2026-01-12 19:16:47'),
(3, 'Açúcar 1kg', 0.70, 1.20, 50, '2026-01-12 19:16:47'),
(4, 'Sal 1kg', 0.25, 0.60, 40, '2026-01-12 19:16:47'),
(5, 'Farinha 1kg', 0.50, 1.00, 60, '2026-01-12 19:16:47'),
(6, 'Óleo 1L', 1.20, 2.20, 45, '2026-01-12 19:16:47');

INSERT IGNORE INTO `padaria` (`id`, `name`, `cost_price`, `sell_price`, `stock`, `created_at`) VALUES
(1, 'Pão', 0.10, 0.30, 40, '2026-01-12 19:16:47'),
(2, 'Pão Integral', 0.15, 0.40, 30, '2026-01-12 19:16:47'),
(3, 'Croissant', 0.30, 0.80, 20, '2026-01-12 19:16:47');

INSERT IGNORE INTO `products` (`id`, `name`, `category`, `cost_price`, `sell_price`, `stock`, `expiry_date`, `created_at`) VALUES
(1, 'Maçã', 'Frutas', 0.20, 0.50, 50, NULL, '2026-01-12 19:27:33'),
(2, 'Pão', 'Padaria', 0.10, 0.30, 40, NULL, '2026-01-12 19:27:33'),
(3, 'Leite', 'Bebidas', 0.40, 0.80, 30, NULL, '2026-01-12 19:27:33');

INSERT IGNORE INTO `suppliers` (`id`, `name`, `contact`, `created_at`) VALUES
(1, 'Frutas & Companhia', 'contacto@frutas.example', '2026-01-12 19:16:47'),
(2, 'Padaria Local', 'padaria@example.com', '2026-01-12 19:16:47'),
(3, 'Frutas & Companhia', 'contacto@frutas.example', '2026-01-12 19:27:33'),
(4, 'Padaria Local', 'padaria@example.com', '2026-01-12 19:27:33');

INSERT IGNORE INTO `transactions` (`id`, `type`, `amount`, `description`, `created_at`) VALUES
(1, 'sale', 5.46, 'Venda do produto #4 x 2', '2026-01-12 19:16:47'),
(2, 'break', -3.00, 'Quebra produto #5 x 3', '2026-01-12 19:16:47'),
(3, 'sale', 2.50, 'Venda do produto #6 x 1', '2026-01-12 19:16:47'),
(4, 'break', -1.00, 'Quebra produto #6 x 1', '2026-01-12 19:16:47');
