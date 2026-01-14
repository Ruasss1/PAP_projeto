# 🔧 Troubleshooting - Página de Login Não Aparece

## Problemas e Soluções

### ❌ Problema 1: "Página em Branco" ao Aceder a http://localhost/login.php

**Causas Possíveis:**
1. Servidor PHP não iniciado
2. Erro na conexão com a BD
3. Erro de sintaxe PHP
4. Erro no ficheiro `auth.php`

**Soluções:**

#### Step 1: Verificar se o servidor está a correr
```bash
# Verificar se há um servidor a correr na porta 8000
lsof -i :8000

# Se não, iniciar o servidor PHP:
cd /Users/vascoruas/Documents/PAP_projeto
php -S localhost:8000
```

#### Step 2: Verificar ficheiros PHP
```bash
# Testar sintaxe dos ficheiros principais
php -l login.php
php -l index.php
php -l includes/auth.php
php -l includes/auth_middleware.php
```

#### Step 3: Ver logs de erro do PHP
```bash
# Se está a usar Apache, verificar logs
tail -50 /var/log/apache2/error.log

# Se está a usar PHP built-in, verificar consola
```

#### Step 4: Usar a versão simplificada de teste
```
Aceda a: http://localhost:8000/login_simple.php
```

---

### ❌ Problema 2: "Erro 500 - Internal Server Error"

**Causas:**
- Erro na BD
- Função undefined
- Include path errado

**Soluções:**

1. **Verificar BD:**
   ```bash
   # Verificar se a BD existe
   mysql -u root -p supermercado -e "SHOW TABLES;"
   ```

2. **Verificar includes:**
   - Certifique-se que `config/database.php` existe
   - Certifique-se que `includes/auth.php` existe
   - Certifique-se que `includes/functions.php` existe

3. **Adicionar debug:**
   ```php
   // No topo do login.php adicione:
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```

---

### ❌ Problema 3: "Arquivo não encontrado"

**Causa:** PHP não consegue encontrar o ficheiro

**Solução:**
```bash
# Verificar se os ficheiros existem
ls -la /Users/vascoruas/Documents/PAP_projeto/login.php
ls -la /Users/vascoruas/Documents/PAP_projeto/includes/auth.php
```

---

## 📋 Checklist de Verificação

- [ ] Servidor PHP a correr (porta 8000 ou Apache)
- [ ] BD MySQL/MariaDB a correr
- [ ] Ficheiro `login.php` existe
- [ ] Ficheiro `includes/auth.php` existe
- [ ] Ficheiro `config/database.php` com credenciais corretas
- [ ] Tabelas `users` e `roles` existem
- [ ] Sem erros de sintaxe PHP

---

## 🚀 Como Reiniciar Tudo

### Opção 1: Usar servidor PHP built-in (RECOMENDADO para testes)

```bash
# Parar qualquer servidor anterior
pkill -f "php -S"

# Iniciar servidor na pasta do projeto
cd /Users/vascoruas/Documents/PAP_projeto
php -S localhost:8000

# Aceder a:
# http://localhost:8000/login_simple.php
# http://localhost:8000/login.php
```

### Opção 2: Usar Apache

```bash
# Reiniciar Apache
sudo apachectl restart

# Verificar status
sudo apachectl status

# Aceder a:
# http://localhost/login.php
```

---

## 📝 Ficheiros Relacionados

- **login.php** - Página de login com autenticação real
- **login_simple.php** - Versão simplificada sem BD (para testar layout)
- **includes/auth.php** - Sistema de autenticação
- **includes/auth_middleware.php** - Middleware de autenticação global
- **index.php** - Dashboard principal

---

## 💡 Dicas

1. **Para testes rápidos**, use `login_simple.php` que não precisa da BD
2. **Para debug**, adicione `error_reporting(E_ALL);` no topo
3. **Verifique a consola do browser** (F12) para erros JavaScript
4. **Verifique os logs do servidor** para erros PHP

---

## ✅ Se Tudo Funcionar

Você deverá ver:
- ✅ Página com layout roxo/azul
- ✅ Formulário com email e password
- ✅ Lista de utilizadores de demonstração
- ✅ Botão "Fazer Login"

---

**Última atualização: 14 de janeiro de 2026**
