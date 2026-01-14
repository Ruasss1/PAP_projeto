# Plano para Resolver Erros no Projeto

## Problemas Identificados:

### 1. **Função `low_stock_alerts()` não existe**
   - Usada em: `modules/stock.php` e `dashboard/dashboard_teste.php`
   - Função existente: `list_low_stock_products()`
   - **Ação**: Criar função `low_stock_alerts()` ou usar `list_low_stock_products()`

### 2. **Assinatura errada de `add_sale()` em `modules/vendas.php`**
   - Função em `functions.php`: `add_sale($items, $payment_method)` - espera array
   - Uso em `vendas.php`: `add_sale($product_id, $qty, $tax)` - parâmetros individuais
   - **Ação**: Corrigir chamada ou adaptar função

### 3. **Coluna `order_date` inexistente**
   - Usada em: `modules/stock.php` linha 22
   - Existe: `created_at`
   - **Ação**: Trocar `order_date` por `created_at`

### 4. **Coluna `received` não existe na tabela orders**
   - Migration não adiciona esta coluna
   - **Ação**: Adicionar à migration

### 5. **Coluna `quantity` vs `qty` inconsistente**
   - Schema usa `qty`, código usa ambos
   - **Ação**: Padronizar para `qty`

### 6. **Tabela `order_messages` pode não existir**
   - Código tenta consultar sem verificar
   - **Ação**: Adicionar verificação de existência

## Plano de Correção:

1. **Atualizar `includes/functions.php`**
   - Adicionar função `low_stock_alerts()`

2. **Corrigir `modules/vendas.php`**
   - Corrigir chamada de `add_sale()`

3. **Corrigir `modules/stock.php`**
   - Trocar `order_date` por `created_at`
   - Adicionar verificação de colunas

4. **Atualizar `migrations/migrate.php`**
   - Adicionar coluna `received` à tabela orders

5. **Corrigir `modules/encomendas.php`**
   - Adicionar tratamento para tabela inexistente

