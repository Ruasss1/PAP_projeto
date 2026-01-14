# 🔧 GUIA DE TROUBLESHOOTING - PAP Supermercado

## 🚨 Problema: "Não consigo aceder / Página em branco"

### Solução Rápida (copiar e colar no terminal):

```bash
# 1. Matar qualquer servidor PHP a correr
killall php

# 2. Esperar 1 segundo
sleep 1

# 3. Iniciar servidor no diretório CORRETO
cd /Users/vascoruas/Documents/PAP_projeto && php -S localhost:8000
```

### Verificar se está a funcionar:
- Abre o navegador em: http://localhost:8000/login.php
- Deve aparecer a página de login

---

## 📋 Passos Detalhados

### 1️⃣ VERIFICAR SE O SERVIDOR ESTÁ A CORRER
```bash
lsof -i :8000
```
- Se aparecer `php`, o servidor está ativo
- Se não aparecer nada, o servidor está parado

### 2️⃣ VERIFICAR EM QUE DIRETÓRIO O SERVIDOR ESTÁ
```bash
lsof -i :8000 | grep php | awk '{print $9}'
```
- O servidor DEVE estar em: `/Users/vascoruas/Documents/PAP_projeto`
- Se estiver em `dashboard-creator-studio-main` → ERRADO!

### 3️⃣ PARAR O SERVIDOR (se necessário)
```bash
killall php
```

### 4️⃣ INICIAR O SERVIDOR NO SÍTIO CORRETO
```bash
cd /Users/vascoruas/Documents/PAP_projeto
php -S localhost:8000
```

---

## 🔑 CREDENCIAIS DE LOGIN

### Admin (acesso total):
- **Email**: admin@example.com
- **Password**: admin123

### Gerente:
- **Email**: gerente@example.com
- **Password**: gerente123

### Caixa:
- **Email**: caixa@example.com
- **Password**: caixa123

### Stock:
- **Email**: stock@example.com
- **Password**: stock123

### RH:
- **Email**: rh@example.com
- **Password**: rh123

---

## 🛠️ PROBLEMAS COMUNS

### Problema: "Permissão negada"
**Solução**: Faz login com admin@example.com / admin123

### Problema: "Tabela não existe"
**Solução**: Executa os scripts de setup:
```bash
cd /Users/vascoruas/Documents/PAP_projeto
php setup_auth_simple.php
php setup_pricing_tables.php
```

### Problema: "Headers already sent"
**Causa**: Ficheiro tem espaços ou output antes do `<?php`
**Solução**: Verifica que não há espaços ou texto antes da tag `<?php`

### Problema: Página branca sem erros
1. Verifica que o servidor está a correr
2. Verifica o terminal onde o servidor está - deve mostrar os requests
3. Se aparecer `/src/main.tsx` → servidor no diretório errado!

---

## 📁 ESTRUTURA DO PROJETO

```
PAP_projeto/
├── index.php              → Dashboard principal
├── login.php              → Página de login
├── logout.php             → Terminar sessão
├── config/
│   └── database.php       → Conexão à base de dados
├── includes/
│   ├── auth.php           → Sistema de autenticação
│   ├── auth_middleware.php → Proteção de páginas
│   ├── header.php         → Cabeçalho e menu
│   └── footer.php         → Rodapé
├── modules/
│   ├── pricing.php        → Gestão de Preços ✅
│   ├── produtos.php       → Produtos
│   ├── stock.php          → Stock
│   ├── vendas.php         → Vendas
│   ├── encomendas.php     → Encomendas
│   ├── fornecedores.php   → Fornecedores
│   └── rh.php             → Recursos Humanos
└── assets/
    ├── css/style.css      → Estilos
    └── js/scripts.js      → JavaScript
```

---

## 🗄️ BASE DE DADOS

**Nome**: supermercado

### Tabelas principais:
- `users` - Utilizadores do sistema
- `roles` - Perfis de acesso
- `sessions` - Sessões ativas
- `permissions` - Permissões por role
- `products` - Produtos
- `categories` - Categorias de produtos
- `pricing_strategies` - Estratégias de preço
- `promotions` - Promoções
- `sales` - Vendas
- `stock` - Inventário

---

## 🚀 INICIAR O PROJETO

### Método 1: Terminal único
```bash
cd /Users/vascoruas/Documents/PAP_projeto && php -S localhost:8000
```

### Método 2: Dois terminais
Terminal 1:
```bash
cd /Users/vascoruas/Documents/PAP_projeto
php -S localhost:8000
```

Terminal 2:
```bash
# Fazer outras coisas (testes, queries, etc)
```

---

## ✅ CHECKLIST QUANDO ALGO NÃO FUNCIONA

- [ ] Servidor está a correr? → `lsof -i :8000`
- [ ] Servidor no diretório correto? → Deve estar em `PAP_projeto`
- [ ] Fiz login? → Acede a `/login.php`
- [ ] Tenho permissões? → Admin tem acesso a tudo
- [ ] Tabelas existem? → Corre os scripts `setup_*.php`
- [ ] Vejo erros no terminal? → Lê a mensagem de erro

---

## 📞 COMANDOS ÚTEIS

### Ver logs do servidor
O terminal onde corres `php -S` mostra todos os requests

### Testar conexão à base de dados
```bash
php -r "require 'config/database.php'; \$pdo = db_connect(); echo 'Conexão OK!';"
```

### Listar tabelas
```bash
php -r "require 'config/database.php'; \$pdo = db_connect(); \$tables = \$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN); print_r(\$tables);"
```

### Ver utilizadores
```bash
php -r "require 'config/database.php'; \$pdo = db_connect(); \$users = \$pdo->query('SELECT u.email, r.name FROM users u JOIN roles r ON u.role_id = r.id')->fetchAll(PDO::FETCH_ASSOC); print_r(\$users);"
```

---

**Última atualização**: 14 janeiro 2026
**Versão**: 1.0
