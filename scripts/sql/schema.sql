-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Tempo de geração: 12-Jan-2026 às 20:29
-- Versão do servidor: 10.4.28-MariaDB
-- versão do PHP: 8.2.4
--
-- DB credentials (convenience): user=pap_user  password=pap_pass
-- Default DB name: supermercado

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `supermercado`
--

-- --------------------------------------------------------

--
-- Estrutura stand-in para vista `all_products`
-- (Veja abaixo para a view atual)
--
CREATE TABLE `all_products` (
`category` varchar(10)
,`id` int(11)
,`name` varchar(100)
,`sell_price` decimal(10,2)
,`stock` int(11)
);

-- --------------------------------------------------------

--
-- Estrutura da tabela `bebidas`
--

CREATE TABLE `bebidas` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `sell_price` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `bebidas`
--

INSERT INTO `bebidas` (`id`, `name`, `cost_price`, `sell_price`, `stock`, `expiry_date`, `created_at`) VALUES
(1, 'Água 1.5L', 0.20, 0.50, 120, NULL, '2026-01-12 19:16:47'),
(2, 'Sumo de Laranja', 0.60, 1.20, 45, '2026-05-01', '2026-01-12 19:16:47'),
(3, 'Refrigerante Cola', 0.70, 1.50, 50, '2026-06-01', '2026-01-12 19:16:47'),
(4, 'Cerveja Lata', 0.50, 1.00, 90, '2026-08-01', '2026-01-12 19:16:47');

-- --------------------------------------------------------

--
-- Estrutura da tabela `breaks`
--

CREATE TABLE `breaks` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Acionadores `breaks`
--
DELIMITER $$
CREATE TRIGGER `update_stock_on_break` AFTER INSERT ON `breaks` FOR EACH ROW BEGIN
    -- FRUTAS
    IF EXISTS (SELECT 1 FROM frutas WHERE id = NEW.product_id) THEN
        UPDATE frutas SET stock = stock - NEW.qty WHERE id = NEW.product_id;

    -- PADARIA
    ELSEIF EXISTS (SELECT 1 FROM padaria WHERE id = NEW.product_id) THEN
        UPDATE padaria SET stock = stock - NEW.qty WHERE id = NEW.product_id;

    -- LATICÍNIOS
    ELSEIF EXISTS (SELECT 1 FROM laticinios WHERE id = NEW.product_id) THEN
        UPDATE laticinios SET stock = stock - NEW.qty WHERE id = NEW.product_id;

    -- MERCEARIA
    ELSEIF EXISTS (SELECT 1 FROM mercearia WHERE id = NEW.product_id) THEN
        UPDATE mercearia SET stock = stock - NEW.qty WHERE id = NEW.product_id;

    -- BEBIDAS
    ELSEIF EXISTS (SELECT 1 FROM bebidas WHERE id = NEW.product_id) THEN
        UPDATE bebidas SET stock = stock - NEW.qty WHERE id = NEW.product_id;

    -- CONGELADOS
    ELSEIF EXISTS (SELECT 1 FROM congelados WHERE id = NEW.product_id) THEN
        UPDATE congelados SET stock = stock - NEW.qty WHERE id = NEW.product_id;

    -- LIMPEZA
    ELSEIF EXISTS (SELECT 1 FROM limpeza WHERE id = NEW.product_id) THEN
        UPDATE limpeza SET stock = stock - NEW.qty WHERE id = NEW.product_id;

    -- HIGIENE
    ELSEIF EXISTS (SELECT 1 FROM higiene WHERE id = NEW.product_id) THEN
        UPDATE higiene SET stock = stock - NEW.qty WHERE id = NEW.product_id;

    END IF;

    -- Registrar transação de perda
    INSERT INTO transactions(type, amount, description)
    VALUES('break', -NEW.cost, CONCAT('Quebra produto #', NEW.product_id, ' qty ', NEW.qty));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura da tabela `congelados`
--

CREATE TABLE `congelados` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `sell_price` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `congelados`
--

INSERT INTO `congelados` (`id`, `name`, `cost_price`, `sell_price`, `stock`, `expiry_date`, `created_at`) VALUES
(1, 'Pizza Congelada', 2.00, 4.50, 20, '2026-07-01', '2026-01-12 19:16:47'),
(2, 'Hambúrguer Congelado', 1.50, 3.20, 30, '2026-06-15', '2026-01-12 19:16:47');

-- --------------------------------------------------------

--
-- Estrutura da tabela `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` varchar(50) NOT NULL,
  `salary` decimal(10,2) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `employees`
--

INSERT INTO `employees` (`id`, `name`, `role`, `salary`, `created_at`) VALUES
(1, 'João', 'Gerente', 900.00, '2026-01-12 19:16:47'),
(2, 'Maria', 'Caixa', 700.00, '2026-01-12 19:16:47'),
(3, 'João', 'Gerente', 900.00, '2026-01-12 19:27:33'),
(4, 'Maria', 'Caixa', 700.00, '2026-01-12 19:27:33');

-- --------------------------------------------------------

--
-- Estrutura stand-in para vista `financial_report`
-- (Veja abaixo para a view atual)
--
CREATE TABLE `financial_report` (
`mes` varchar(7)
,`receita_total` decimal(32,2)
,`perdas` decimal(33,2)
,`lucro_liquido` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Estrutura da tabela `frutas`
--

CREATE TABLE `frutas` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `sell_price` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `frutas`
--

INSERT INTO `frutas` (`id`, `name`, `cost_price`, `sell_price`, `stock`, `created_at`) VALUES
(1, 'Maçã', 0.20, 0.50, 50, '2026-01-12 19:16:47'),
(2, 'Banana', 0.15, 0.40, 60, '2026-01-12 19:16:47'),
(3, 'Laranja', 0.18, 0.45, 70, '2026-01-12 19:16:47'),
(4, 'Pera', 0.22, 0.55, 40, '2026-01-12 19:16:47'),
(5, 'Uva', 1.20, 2.50, 25, '2026-01-12 19:16:47');

-- --------------------------------------------------------

--
-- Estrutura da tabela `higiene`
--

CREATE TABLE `higiene` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `sell_price` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `higiene`
--

INSERT INTO `higiene` (`id`, `name`, `cost_price`, `sell_price`, `stock`, `created_at`) VALUES
(1, 'Papel Higiénico 6un', 1.50, 3.00, 40, '2026-01-12 19:16:47'),
(2, 'Sabonete', 0.40, 0.90, 60, '2026-01-12 19:16:47'),
(3, 'Champô', 1.80, 3.50, 35, '2026-01-12 19:16:47'),
(4, 'Pasta de Dentes', 1.20, 2.40, 45, '2026-01-12 19:16:47');

-- --------------------------------------------------------

--
-- Estrutura da tabela `laticinios`
--

CREATE TABLE `laticinios` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `sell_price` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `laticinios`
--

INSERT INTO `laticinios` (`id`, `name`, `cost_price`, `sell_price`, `stock`, `expiry_date`, `created_at`) VALUES
(1, 'Leite', 0.40, 0.80, 30, '2026-02-15', '2026-01-12 19:16:47'),
(2, 'Iogurte Natural', 0.30, 0.70, 40, '2026-03-10', '2026-01-12 19:16:47'),
(3, 'Queijo Flamengo', 1.80, 3.20, 25, '2026-02-28', '2026-01-12 19:16:47'),
(4, 'Manteiga', 0.90, 1.60, 35, '2026-04-05', '2026-01-12 19:16:47');

-- --------------------------------------------------------

--
-- Estrutura da tabela `limpeza`
--

CREATE TABLE `limpeza` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `sell_price` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `limpeza`
--

INSERT INTO `limpeza` (`id`, `name`, `cost_price`, `sell_price`, `stock`, `created_at`) VALUES
(1, 'Detergente Loiça', 1.20, 2.50, 30, '2026-01-12 19:16:47'),
(2, 'Detergente Roupa', 2.80, 5.50, 20, '2026-01-12 19:16:47'),
(3, 'Lixívia', 0.80, 1.60, 25, '2026-01-12 19:16:47');

-- --------------------------------------------------------

--
-- Estrutura da tabela `mercearia`
--

CREATE TABLE `mercearia` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `sell_price` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `mercearia`
--

INSERT INTO `mercearia` (`id`, `name`, `cost_price`, `sell_price`, `stock`, `created_at`) VALUES
(1, 'Arroz 1kg', 0.90, 1.50, 100, '2026-01-12 19:16:47'),
(2, 'Massa 500g', 0.45, 0.90, 80, '2026-01-12 19:16:47'),
(3, 'Açúcar 1kg', 0.70, 1.20, 50, '2026-01-12 19:16:47'),
(4, 'Sal 1kg', 0.25, 0.60, 40, '2026-01-12 19:16:47'),
(5, 'Farinha 1kg', 0.50, 1.00, 60, '2026-01-12 19:16:47'),
(6, 'Óleo 1L', 1.20, 2.20, 45, '2026-01-12 19:16:47');

-- --------------------------------------------------------

--
-- Estrutura da tabela `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL,
  `received` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Acionadores `orders`
--
DELIMITER $$
CREATE TRIGGER `update_stock_on_receive` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN
    IF NEW.received = 1 AND OLD.received = 0 THEN

        -- FRUTAS
        IF EXISTS (SELECT 1 FROM frutas WHERE id = NEW.product_id) THEN
            UPDATE frutas SET stock = stock + NEW.qty WHERE id = NEW.product_id;

        -- PADARIA
        ELSEIF EXISTS (SELECT 1 FROM padaria WHERE id = NEW.product_id) THEN
            UPDATE padaria SET stock = stock + NEW.qty WHERE id = NEW.product_id;

        -- LATICÍNIOS
        ELSEIF EXISTS (SELECT 1 FROM laticinios WHERE id = NEW.product_id) THEN
            UPDATE laticinios SET stock = stock + NEW.qty WHERE id = NEW.product_id;

        -- MERCEARIA
        ELSEIF EXISTS (SELECT 1 FROM mercearia WHERE id = NEW.product_id) THEN
            UPDATE mercearia SET stock = stock + NEW.qty WHERE id = NEW.product_id;

        -- BEBIDAS
        ELSEIF EXISTS (SELECT 1 FROM bebidas WHERE id = NEW.product_id) THEN
            UPDATE bebidas SET stock = stock + NEW.qty WHERE id = NEW.product_id;

        -- CONGELADOS
        ELSEIF EXISTS (SELECT 1 FROM congelados WHERE id = NEW.product_id) THEN
            UPDATE congelados SET stock = stock + NEW.qty WHERE id = NEW.product_id;

        -- LIMPEZA
        ELSEIF EXISTS (SELECT 1 FROM limpeza WHERE id = NEW.product_id) THEN
            UPDATE limpeza SET stock = stock + NEW.qty WHERE id = NEW.product_id;

        -- HIGIENE
        ELSEIF EXISTS (SELECT 1 FROM higiene WHERE id = NEW.product_id) THEN
            UPDATE higiene SET stock = stock + NEW.qty WHERE id = NEW.product_id;

        END IF;

        -- Criar transação financeira da compra
        INSERT INTO transactions(type, amount, description) 
        VALUES('order', NEW.qty * NEW.cost_price, CONCAT('Compra produto #', NEW.product_id, ' da order #', NEW.id));

    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura da tabela `padaria`
--

CREATE TABLE `padaria` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `sell_price` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `padaria`
--

INSERT INTO `padaria` (`id`, `name`, `cost_price`, `sell_price`, `stock`, `created_at`) VALUES
(1, 'Pão', 0.10, 0.30, 40, '2026-01-12 19:16:47'),
(2, 'Pão Integral', 0.15, 0.40, 30, '2026-01-12 19:16:47'),
(3, 'Croissant', 0.30, 0.80, 20, '2026-01-12 19:16:47');

-- --------------------------------------------------------

--
-- Estrutura da tabela `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT 0.00,
  `sell_price` decimal(10,2) DEFAULT 0.00,
  `stock` int(11) DEFAULT 0,
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `products`
--

INSERT INTO `products` (`id`, `name`, `category`, `cost_price`, `sell_price`, `stock`, `expiry_date`, `created_at`) VALUES
(1, 'Maçã', 'Frutas', 0.20, 0.50, 50, NULL, '2026-01-12 19:27:33'),
(2, 'Pão', 'Padaria', 0.10, 0.30, 40, NULL, '2026-01-12 19:27:33'),
(3, 'Leite', 'Bebidas', 0.40, 0.80, 30, NULL, '2026-01-12 19:27:33');

-- --------------------------------------------------------

--
-- Estrutura da tabela `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `sale_date` datetime DEFAULT current_timestamp(),
  `total` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT 'Dinheiro',
  `nif` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact`, `created_at`) VALUES
(1, 'Frutas & Companhia', 'contacto@frutas.example', '2026-01-12 19:16:47'),
(2, 'Padaria Local', 'padaria@example.com', '2026-01-12 19:16:47'),
(3, 'Frutas & Companhia', 'contacto@frutas.example', '2026-01-12 19:27:33'),
(4, 'Padaria Local', 'padaria@example.com', '2026-01-12 19:27:33');

-- --------------------------------------------------------

--
-- Estrutura da tabela `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `type` enum('sale','break','order','salary') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `transactions`
--

INSERT INTO `transactions` (`id`, `type`, `amount`, `description`, `created_at`) VALUES
(1, 'sale', 5.46, 'Venda do produto #4 x 2', '2026-01-12 19:16:47'),
(2, 'break', -3.00, 'Quebra produto #5 x 3', '2026-01-12 19:16:47'),
(3, 'sale', 2.50, 'Venda do produto #6 x 1', '2026-01-12 19:16:47'),
(4, 'break', -1.00, 'Quebra produto #6 x 1', '2026-01-12 19:16:47');

-- --------------------------------------------------------

--
-- Estrutura para vista `all_products`
--
DROP TABLE IF EXISTS `all_products`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `all_products`  AS SELECT 'Frutas' AS `category`, `frutas`.`id` AS `id`, `frutas`.`name` AS `name`, `frutas`.`sell_price` AS `sell_price`, `frutas`.`stock` AS `stock` FROM `frutas`union all select 'Padaria' AS `Padaria`,`padaria`.`id` AS `id`,`padaria`.`name` AS `name`,`padaria`.`sell_price` AS `sell_price`,`padaria`.`stock` AS `stock` from `padaria` union all select 'Laticínios' AS `Laticínios`,`laticinios`.`id` AS `id`,`laticinios`.`name` AS `name`,`laticinios`.`sell_price` AS `sell_price`,`laticinios`.`stock` AS `stock` from `laticinios` union all select 'Mercearia' AS `Mercearia`,`mercearia`.`id` AS `id`,`mercearia`.`name` AS `name`,`mercearia`.`sell_price` AS `sell_price`,`mercearia`.`stock` AS `stock` from `mercearia` union all select 'Bebidas' AS `Bebidas`,`bebidas`.`id` AS `id`,`bebidas`.`name` AS `name`,`bebidas`.`sell_price` AS `sell_price`,`bebidas`.`stock` AS `stock` from `bebidas` union all select 'Congelados' AS `Congelados`,`congelados`.`id` AS `id`,`congelados`.`name` AS `name`,`congelados`.`sell_price` AS `sell_price`,`congelados`.`stock` AS `stock` from `congelados` union all select 'Limpeza' AS `Limpeza`,`limpeza`.`id` AS `id`,`limpeza`.`name` AS `name`,`limpeza`.`sell_price` AS `sell_price`,`limpeza`.`stock` AS `stock` from `limpeza` union all select 'Higiene' AS `Higiene`,`higiene`.`id` AS `id`,`higiene`.`name` AS `name`,`higiene`.`sell_price` AS `sell_price`,`higiene`.`stock` AS `stock` from `higiene`  ;

-- --------------------------------------------------------

--
-- Estrutura para vista `financial_report`
--
DROP TABLE IF EXISTS `financial_report`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `financial_report`  AS SELECT `s`.`mes` AS `mes`, `s`.`receita_total` AS `receita_total`, coalesce(`b`.`perdas`,0) AS `perdas`, `s`.`receita_total`- coalesce(`b`.`perdas`,0) AS `lucro_liquido` FROM ((select date_format(`sales`.`sale_date`,'%Y-%m') AS `mes`,sum(`sales`.`total`) AS `receita_total` from `sales` group by date_format(`sales`.`sale_date`,'%Y-%m')) `s` left join (select date_format(`transactions`.`created_at`,'%Y-%m') AS `mes`,sum(`transactions`.`amount`) * -1 AS `perdas` from `transactions` where `transactions`.`type` = 'break' group by date_format(`transactions`.`created_at`,'%Y-%m')) `b` on(`s`.`mes` = `b`.`mes`)) ;

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `bebidas`
--
ALTER TABLE `bebidas`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `breaks`
--
ALTER TABLE `breaks`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `congelados`
--
ALTER TABLE `congelados`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `frutas`
--
ALTER TABLE `frutas`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `higiene`
--
ALTER TABLE `higiene`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `laticinios`
--
ALTER TABLE `laticinios`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `limpeza`
--
ALTER TABLE `limpeza`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `mercearia`
--
ALTER TABLE `mercearia`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Índices para tabela `padaria`
--
ALTER TABLE `padaria`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`);

--
-- Índices para tabela `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `bebidas`
--
ALTER TABLE `bebidas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `breaks`
--
ALTER TABLE `breaks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `congelados`
--
ALTER TABLE `congelados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `frutas`
--
ALTER TABLE `frutas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `higiene`
--
ALTER TABLE `higiene`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `laticinios`
--
ALTER TABLE `laticinios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `limpeza`
--
ALTER TABLE `limpeza`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `mercearia`
--
ALTER TABLE `mercearia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `padaria`
--
ALTER TABLE `padaria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Limitadores para a tabela `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
