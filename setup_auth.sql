-- Setup tabelas de autenticação

-- Criar tabela roles primeiro
CREATE TABLE IF NOT EXISTS roles (
  id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name varchar(100) UNIQUE NOT NULL,
  description text,
  created_at datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir roles padrão
INSERT IGNORE INTO roles (name, description) VALUES 
('admin', 'Administrador com acesso total'),
('manager', 'Gerente com acesso a relatórios e gestão'),
('cashier', 'Operador de caixa'),
('employee', 'Funcionário básico');

-- Criar tabela users
CREATE TABLE IF NOT EXISTS users (
  id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name varchar(255) NOT NULL,
  email varchar(255) UNIQUE NOT NULL,
  password_hash varchar(255) NOT NULL,
  role_id int,
  active tinyint(1) DEFAULT 1,
  last_login_at datetime,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Criar tabela sessions
CREATE TABLE IF NOT EXISTS sessions (
  id varchar(255) PRIMARY KEY,
  user_id int NOT NULL,
  ip_address varchar(45),
  user_agent varchar(500),
  expires_at datetime NOT NULL,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Criar tabela audit_logs  
CREATE TABLE IF NOT EXISTS audit_logs (
  id bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id int,
  action varchar(50) NOT NULL,
  resource varchar(100) NOT NULL,
  resource_id int,
  changes json,
  ip_address varchar(45),
  user_agent varchar(500),
  status varchar(20),
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_id (user_id),
  KEY idx_resource (resource),
  KEY idx_created_at (created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Criar tabela stores
CREATE TABLE IF NOT EXISTS stores (
  id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name varchar(255) NOT NULL,
  address text,
  phone varchar(20),
  email varchar(255),
  is_default tinyint(1) DEFAULT 0,
  active tinyint(1) DEFAULT 1,
  created_at datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir loja padrão
INSERT IGNORE INTO stores (name, is_default, active) VALUES ('Loja Principal', 1, 1);

-- Criar utilizador admin (password: password)
INSERT IGNORE INTO users (name, email, password_hash, role_id) 
SELECT 'Administrador', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', id 
FROM roles WHERE name = 'admin';
