# 🔐 Sistema de Autenticação Melhorado - Guia Completo

## ✅ Login Melhorado

### Novas Funcionalidades

1. **Interface de Login Profissional**
   - Design lado-a-lado (esquerda: info, direita: formulário)
   - Gradiente de cor: roxo a azul
   - Responsivo para mobile
   - Criação automática de utilizadores de demonstração

2. **Utilizadores de Demonstração Pré-criados**
   - Nomes, emails e senhas diferentes por role
   - Permissões corretas configuradas
   - Informações visíveis na página de login

3. **Middleware de Autenticação Global**
   - Bloqueia acesso sem login
   - Redireciona automaticamente para login
   - Valida permissões por módulo
   - Audit logging de acessos

---

## 👥 Utilizadores de Demonstração

### 1. **Administrador** 👨‍💼

```
Email:    admin@example.com
Senha:    admin123
Role:     Admin
```

**Permissões:** Acesso total a tudo

---

### 2. **Gerente da Loja** 📊

```
Email:    gerente@example.com
Senha:    gerente123
Role:     Gerente
```

**Permissões:**
- ✅ Produtos (ver, criar, editar)
- ✅ Preços (ver, editar)
- ✅ Promoções (criar, gerenciar)
- ✅ Vendas (ver, relatórios)
- ✅ Stock (ver, alertas)
- ✅ Fornecedores (ver, contactar)
- ⛔ Auditoria (apenas ver)
- ⛔ RH (não tem)

---

### 3. **Caixa** 💰

```
Email:    caixa@example.com
Senha:    caixa123
Role:     Caixa
```

**Permissões:**
- ✅ Vendas (criar, processar)
- ✅ Produtos (apenas consultar preço)
- ✅ Stock (apenas consultar quantidade)
- ⛔ Editar preços
- ⛔ Relatórios
- ⛔ Auditoria
- ⛔ Acesso a RH

---

### 4. **Responsável de Stock** 📦

```
Email:    stock@example.com
Senha:    stock123
Role:     Stock
```

**Permissões:**
- ✅ Stock (ver, editar, contagens)
- ✅ Produtos (ver detalhes)
- ✅ Fornecedores (ver, criar encomendas)
- ✅ Alertas (ver, resolver)
- ⛔ Vendas (não tem)
- ⛔ Preços (apenas consultar)
- ⛔ Auditoria

---

### 5. **RH** 👤

```
Email:    rh@example.com
Senha:    rh123
Role:     RH
```

**Permissões:**
- ✅ RH (gestão de pessoal)
- ✅ Horários (criar, editar)
- ✅ Relatórios de RH
- ⛔ Produtos
- ⛔ Vendas
- ⛔ Stock
- ⛔ Preços

---

## 🔒 Como o Sistema Funciona

### 1. **Fluxo de Login**

```
Acede a qualquer página
    ↓
Verifica autenticação (auth_middleware.php)
    ↓
Se não autenticado → Redireciona para login.php
    ↓
Introduz email e senha
    ↓
Sistema verifica credenciais (bcrypt)
    ↓
Se OK → Cria sessão + registra audit log
    ↓
Redireciona para página solicitada
    ↓
Se permissões OK → Carrega página
    ↓
Se permissões negadas → Mostra erro 403
```

### 2. **Verificação de Permissões**

```php
// Em cada módulo:
require_once '../includes/auth_middleware.php';  // Bloqueio global
$auth->require_auth('modulo', 'ação');          // Permissão específica
```

---

## 📁 Ficheiros Modificados

### 1. **login.php** (Novo - Totalmente Redesenhado)
- Interface profissional lado-a-lado
- Lista de utilizadores de demonstração
- Criação automática de utilizadores
- Audit logging de tentativas de login
- Responsive design

### 2. **includes/auth_middleware.php** (Novo)
- Middleware global de autenticação
- Bloqueia acesso sem login
- Redireciona automaticamente
- Valida permissões

### 3. **index.php** (Atualizado)
- Inclui auth_middleware.php primeiro
- Garante bloqueio de acesso

### 4. **modules/produtos.php** (Atualizado)
- Inclui auth_middleware.php
- Valida permissão 'produtos' → 'view'
- Padrão para todos os módulos

---

## 🚀 Como Começar

### Step 1: Fazer Login

Aceda a: `http://localhost/login.php`

### Step 2: Escolher Um Utilizador

Use qualquer um dos utilizadores de demonstração:

```
Admin:    admin@example.com / admin123
Gerente:  gerente@example.com / gerente123
Caixa:    caixa@example.com / caixa123
Stock:    stock@example.com / stock123
RH:       rh@example.com / rh123
```

### Step 3: Aceder ao Dashboard

Depois do login, será redirecionado para o dashboard com as permissões corretas.

### Step 4: Navegar pelo Sistema

O menu de navegação mostra apenas as opções disponíveis para o seu role.

---

## 🔐 Segurança

### Implementado:

✅ **Autenticação Bcrypt**
- Cost factor: 12
- Resistente a ataques de força bruta
- Hashes verificados com `password_verify()`

✅ **Sessões Seguras**
- Guardadas em BD com expiração
- 1 hora de timeout
- IP e User-Agent validados

✅ **RBAC (Role-Based Access Control)**
- 5 roles predefinidos
- Permissões granulares
- Validação em cada módulo

✅ **Audit Logging**
- Registra todos os logins
- Tenta falhas registadas
- Páginas visitadas
- Alterações de dados

✅ **Middleware Global**
- Bloqueia acesso sem login
- Redireciona automaticamente
- Valida permissões por módulo

---

## 📊 Matriz de Permissões

| Módulo | Admin | Gerente | Caixa | Stock | RH |
|--------|-------|---------|-------|-------|-----|
| Dashboard | ✅ | ✅ | ✅ | ✅ | ✅ |
| Produtos | ✅ | ✅ | 👁️ | 👁️ | ⛔ |
| Preços | ✅ | ✅ | ⛔ | 👁️ | ⛔ |
| Vendas | ✅ | ✅ | ✅ | ⛔ | ⛔ |
| Stock | ✅ | ✅ | 👁️ | ✅ | ⛔ |
| Fornecedores | ✅ | ✅ | ⛔ | ✅ | ⛔ |
| Encomendas | ✅ | ✅ | ⛔ | ✅ | ⛔ |
| Alertas | ✅ | ✅ | 👁️ | ✅ | ⛔ |
| Auditoria | ✅ | 👁️ | ⛔ | ⛔ | ⛔ |
| RH | ✅ | ⛔ | ⛔ | ⛔ | ✅ |

**Legenda:**
- ✅ Acesso total (ver + editar)
- 👁️ Apenas visualizar
- ⛔ Sem acesso

---

## 🔄 Fluxo Completo de Autenticação

```
1. Utilizador acede http://localhost/
   ↓
2. auth_middleware.php verifica sessão
   ↓
3. Se não autenticado → Redireciona para /login.php
   ↓
4. Utilizador introduz credenciais
   ↓
5. login.php verifica email + senha
   ↓
6. Se OK:
   - Cria sessão em BD
   - Regista audit log
   - Redireciona para dashboard
   ↓
7. AuthManager valida sessão em cada request
   ↓
8. Se expirou ou IP diferente → Logout automático
   ↓
9. Redireciona para login novamente
```

---

## 💡 Recursos Adicionais

### Para Criar Novo Utilizador

```php
$pdo = db_connect();

$email = 'novo@example.com';
$senha = password_hash('senha123', PASSWORD_BCRYPT, ['cost' => 12]);
$role_id = 3; // Gerente

$stmt = $pdo->prepare("
    INSERT INTO users (name, email, password_hash, role_id, active, created_at)
    VALUES (?, ?, ?, ?, 1, NOW())
");
$stmt->execute(['Nome', $email, $senha, $role_id]);
```

### Para Resetar Senha

```php
$novo_hash = password_hash('nova_senha', PASSWORD_BCRYPT, ['cost' => 12]);
$pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
    ->execute([$novo_hash, $user_id]);
```

---

## 🆘 Troubleshooting

### Problema: "Erro 404" ao aceder a módulos

**Solução:** Use a URL correta com `index.php?page=modulo`
```
❌ http://localhost/modules/pricing.php
✅ http://localhost/index.php?page=pricing
```

### Problema: "Acesso negado" em um módulo

**Solução:** Verifique as permissões do seu role
```
Abra Auditoria → Verifique permissões atribuídas
```

### Problema: Sessão expirou

**Solução:** Faça login novamente
```
A sessão expira após 1 hora
Ou se IP/User-Agent mudarem
```

---

## 📝 Próximas Melhorias

- [ ] "Lembre-se de mim" (remember me)
- [ ] 2FA (autenticação de dois fatores)
- [ ] Recuperação de senha por email
- [ ] Bloqueio após múltiplas tentativas
- [ ] Sessões em múltiplos dispositivos
- [ ] Gestão de utilizadores em painel admin

---

**Sistema de Autenticação Melhorado - Pronto para Usar! ✅**

*Data: 2026-01-14 | Versão: 2.1*
