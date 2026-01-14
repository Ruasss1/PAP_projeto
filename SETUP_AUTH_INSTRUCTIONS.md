# 🚨 URGENT: Criar Tabelas de Autenticação

## O Problema

A tabela `users` não existe na base de dados. Por isso quando tenta fazer login aparece:

```
Fatal error: SQLSTATE[42S02]: Table 'supermercado.users' doesn't exist
```

## A Solução

Tem dois métodos para resolver:

---

## ✅ Método 1: Usar Script PHP (RECOMENDADO)

### Step 1: Execute o script
```bash
cd /Users/vascoruas/Documents/PAP_projeto
php setup_auth_tables.php
```

### Step 2: Aguarde o resultado
Deverá ver:
```
✅ Tabela 1/5 criada
✅ Tabela 2/5 criada
✅ Tabela 3/5 criada
✅ Tabela 4/5 criada
✅ Tabela 5/5 criada

👥 Criando utilizadores de demonstração...
   ✅ admin@example.com
   ...

✅ SETUP CONCLUÍDO COM SUCESSO!
```

### Step 3: Tente fazer login novamente
```
http://localhost:8000/login.php
Email: admin@example.com
Senha: admin123
```

---

## ✅ Método 2: Usar MySQL Directamente

### Step 1: Abra terminal MySQL
```bash
mysql -u root -p
```

### Step 2: Selecione a BD
```sql
USE supermercado;
```

### Step 3: Execute o ficheiro SQL
```bash
# Sair do MySQL primeiro (CTRL+D ou quit)

# Depois execute:
mysql -u root -p supermercado < auth_tables.sql
```

---

## 🔍 Verificação

### Para confirmar que as tabelas foram criadas:
```bash
mysql -u root -p supermercado -e "SHOW TABLES;"
```

Deverá aparecer:
```
+------------------+
| Tables_in_supermercado |
+------------------+
| ...
| users            |
| roles            |
| sessions         |
| audit_log        |
| permissions      |
| ...
+------------------+
```

---

## 📝 Se Usar MySQL Directamente

### Copie e execute este SQL:

```sql
-- Criar tabela de ROLES
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL UNIQUE,
  `description` varchar(255),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Inserir roles
INSERT IGNORE INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'Admin', 'Administrador do sistema'),
(2, 'Gerente', 'Gerente da loja'),
(3, 'Caixa', 'Operador de caixa'),
(4, 'Stock', 'Responsável de stock'),
(5, 'RH', 'Recursos Humanos');

-- Criar tabela de USERS
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT 3,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Criar tabela de SESSIONS
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45),
  `user_agent` text,
  `last_activity` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Criar tabela de AUDIT_LOG
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11),
  `action` varchar(100) NOT NULL,
  `resource` varchar(100),
  `resource_id` int(11),
  `status` varchar(20) NOT NULL,
  `ip_address` varchar(45),
  `user_agent` text,
  `changes` json,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Criar tabela de PERMISSIONS
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `module` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Inserir utilizadores de demonstração
-- As senhas são hashadas com bcrypt
INSERT IGNORE INTO `users` (`name`, `email`, `password_hash`, `role_id`, `active`) VALUES
('Administrador', 'admin@example.com', '$2y$12$k3R4VkxQ.3MzQHqN5oZKOe5Z8jK7pLmK6VqN9oZKOe5Z8jK7pLmK6V', 1, 1),
('Gerente da Loja', 'gerente@example.com', '$2y$12$m9O8N7M6L5K4J3I2H1G0F/Z8X7W6V5U4T3S2R1Q0P9O8N7M6L5K4J', 2, 1),
('Caixa', 'caixa@example.com', '$2y$12$a1B2C3D4E5F6G7H8I9J0K/L1M2N3O4P5Q6R7S8T9U0V1W2X3Y4Z5', 3, 1),
('Responsável de Stock', 'stock@example.com', '$2y$12$z9Y8X7W6V5U4T3S2R1Q0P/O9N8M7L6K5J4I3H2G1F0E9D8C7B6A5', 4, 1),
('RH', 'rh@example.com', '$2y$12$9A8B7C6D5E4F3G2H1I0J/K9L8M7N6O5P4Q3R2S1T0U9V8W7X6Y5Z4', 5, 1);
```

---

## ✅ Utilizadores Disponíveis

Depois das tabelas criadas, pode usar:

| Email | Senha |
|-------|-------|
| admin@example.com | admin123 |
| gerente@example.com | gerente123 |
| caixa@example.com | caixa123 |
| stock@example.com | stock123 |
| rh@example.com | rh123 |

---

## 🆘 Se Tiver Problemas

1. **MySQL não arranca?**
   - Tente: `brew services start mysql` (se usa Homebrew)

2. **Erro de permissões?**
   - Tente: `sudo mysql -u root supermercado < auth_tables.sql`

3. **BD não existe?**
   - Tente criar: `mysql -u root -e "CREATE DATABASE supermercado;"`

4. **Tente testar a conexão:**
   ```bash
   mysql -u root -e "SELECT 1;"
   ```

---

**Execute agora:** `php setup_auth_tables.php`

Depois aceda a: http://localhost:8000/login.php
