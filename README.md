# Mercantec

Sistema de gestão de supermercado — stock, vendas, RH, caixa e relatórios.

---

## Instalação local

### Requisitos

- PHP 8.1+
- MySQL 8.0+ ou MariaDB 10.4+
- Composer 2.x
- XAMPP (recomendado) ou servidor Apache/MySQL separado

### Passo a passo

**1. Clonar o repositório**

```bash
git clone https://github.com/Ruasss1/PAP_projeto.git
cd PAP_projeto
```

**2. Instalar dependências PHP**

```bash
composer install
```

**3. Criar a base de dados**

Abre o phpMyAdmin em `http://127.0.0.1/phpmyadmin` e executa:

```sql
CREATE DATABASE supermercado CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**4. Importar as migrações**

Importa os ficheiros da pasta `migrations/` por ordem:

```bash
mysql -u root supermercado < migrations/000_base_schema.sql
mysql -u root supermercado < migrations/001_supermercado_migration.sql
mysql -u root supermercado < migrations/002_add_security_and_audit.sql
mysql -u root supermercado < migrations/002_pdv_tables.sql
mysql -u root supermercado < migrations/003_add_pricing_management.sql
mysql -u root supermercado < migrations/004_low_stock_settings.sql
mysql -u root supermercado < migrations/005_cash_operations.sql
mysql -u root supermercado < migrations/005_pdv_system.sql
mysql -u root supermercado < migrations/005_rh_management.sql
mysql -u root supermercado < migrations/006_customers_loyalty.sql
mysql -u root supermercado < migrations/007_notifications_alerts.sql
mysql -u root supermercado < migrations/008_new_features.sql
mysql -u root supermercado < migrations/seed_data.sql
```

Ou importa cada ficheiro manualmente via phpMyAdmin (Importar → selecionar ficheiro).

**5. Verificar a ligação à base de dados**

As configurações predefinidas em `config/database.php` usam:

| Parâmetro | Valor |
|-----------|-------|
| Host | `127.0.0.1` |
| Porto | `3306` |
| Base de dados | `supermercado` |
| Utilizador | `root` |
| Password | *(em branco)* |

Se o teu ambiente for diferente, define as variáveis de ambiente `MYSQLDATABASE`, `MYSQLUSER` e `MYSQLPASSWORD`.

**6. Iniciar o servidor**

```bash
composer serve
```

Ou manualmente:

```bash
php -S 127.0.0.1:8000 -t .
```

**7. Popular dados de exemplo** *(opcional)*

```bash
composer db:seed
```

**8. Abrir no browser**

```
http://127.0.0.1:8000
```

> Se `localhost:8000` não responder, usa sempre `127.0.0.1:8000`.

---

## Credenciais de teste

> Página de credenciais: `http://127.0.0.1:8000/credenciais.php`

| Perfil | Email | Password |
|--------|-------|----------|
| Admin | admin@papmarket.pt | admin123 |
| Gerente | gerente@papmarket.pt | gerente123 |
| Caixa | caixa@papmarket.pt | caixa123 |
| Funcionário | func@papmarket.pt | func123 |

---

## Módulos

| Módulo | URL |
|--------|-----|
| Dashboard | `/` |
| PDV / Caixa | `/CAIXA/` |
| Produtos | `/modules/produtos.php` |
| Stock | `/modules/stock.php` |
| Fornecedores | `/modules/fornecedores.php` |
| Encomendas | `/modules/encomendas.php` |
| Recursos Humanos | `/modules/rh.php` |
| Clientes | `/modules/customers.php` |
| Promoções | `/modules/promocoes.php` |
| Relatórios | `/modules/relatorios.php` |
| Analytics | `/modules/analytics.php` |
| Notificações | `/modules/notifications.php` |
| Configurações | `/modules/configuracoes.php` |

---

## Documentação

- `Manual_Utilizador_Mercantec.pdf` — manual completo de utilizador e guia de instalação
- `Mercantec_PAP_Vasco_Ruas.docx` — relatório PAP
- `Secao_Modelo_Dados_PAP.docx` — secção de modelo de dados do relatório

---

PAP 2025/2026 — Vasco Ruas
