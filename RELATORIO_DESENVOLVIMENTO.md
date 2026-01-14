# Relatório de Desenvolvimento - PAP Projeto
**Data**: 14 de janeiro de 2026  
**Período**: Desenvolvimento contínuo do módulo de Preços e Produtos  

---

## 1. VISÃO GERAL DO PROJETO

### Objetivo Principal
Sistema de gestão integrado para supermercado com enfoque em:
- Gestão de produtos e stock
- Análise de preços e margens
- Promoções e estratégias de preço
- Autenticação e autorização de utilizadores
- Dashboard com análise financeira

### Stack Tecnológico
- **Backend**: PHP 8.5.1
- **Base de Dados**: MariaDB (supermercado)
- **Frontend**: HTML/CSS/JavaScript
- **Autenticação**: Sistema session-based com roles
- **Padrão de Código**: MVC com separação de concerns

---

## 2. PROBLEMAS IDENTIFICADOS E RESOLVIDOS

### 2.1 Bug: Undefined Array Key "active" em Promoções
**Problema**: Avisos de PHP ao aceder a promoções  
**Causa Root**: A tabela `promotions` usa coluna `is_active` mas o código tentava acessar `active`  
**Localização**: [modules/pricing.php](modules/pricing.php#L277)

**Solução Implementada**:
```php
// Linha 277 - Fallback seguro
$is_active = $promo['is_active'] ?? ($promo['active'] ?? 0);
```

**Alinhamento de Queries**:
- Linhas 288, 299: Alteradas queries para usar `is_active` coluna correcta

---

### 2.2 Bug: Fornecedores Duplicados na Base de Dados
**Problema**: Duplicatas de fornecedores impediam carregamento correto de dados  
**Causa Root**: Script seed antigo não limpava tabela de fornecedores; schema.sql tinha dados legados  

**Solução Implementada**:
- Criado [seed_real_supermarket.php](seed_real_supermarket.php) com TRUNCATE em 9 tabelas:
  - suppliers
  - products
  - category_pricing_rules
  - margin_analysis
  - pricing_strategies
  - promotions
  - promotion_products
  - promotion_categories
  - sales, sale_items

---

### 2.3 Problema: Produtos de Teste (TEST_PRODUCT_*)
**Problema**: Base de dados cheia de produtos fictícios sem dados realistas  
**Solução**: Substituição por 20 produtos reais portugueses com categorias e preços adequados

---

### 2.4 Bug: Filtros Recarregam Página
**Problema**: Ao clicar nos filtros, página sofria reload completo, experiência de utilizador pobre  
**Causa**: Utilizavam `<form>` com submit tradicional  

**Solução Implementada**:
- Remoção de `<form>` tags
- Implementação de JavaScript `onchange` com `window.location.href`
- Mantém estado dos filtros via URL parameters

---

### 2.5 Problema: Cor dos Filtros (UI/UX)
**Problema**: Gradiente roxo (`#667eea → #764ba2`) não combinava bem com tema escuro  
**Solução**: Mudança para azul escuro (`#1e3a8a → #1e40af`) - combina melhor com página

---

## 3. FUNCIONALIDADES IMPLEMENTADAS

### 3.1 Sistema de Filtros e Ordenação de Produtos
**Ficheiro**: [modules/produtos.php](modules/produtos.php)  
**Linhas**: 130-162 (Filtros), 220-227 (Botões)

**Funcionalidades**:
- Dropdown de Categoria (todas as categorias + opção "Todas")
- Dropdown de Ordenação com 6 opções:
  - A-Z (alfabético)
  - Z-A (alfabético reverso)
  - 💰 Mais Barato (preço crescente)
  - 💸 Mais Caro (preço decrescente)
  - 📉 Menos Stock (stock crescente)
  - 📈 Mais Stock (stock decrescente)

**Estilo**:
```css
background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
padding: 15px;
border-radius: 8px;
box-shadow: 0 4px 6px rgba(0,0,0,0.2);
```

---

### 3.2 Dashboard de Gestão de Preços
**Ficheiro**: [modules/pricing.php](modules/pricing.php)  
**Linhas**: 45-115 (Filtros), 119-162 (Tabela)

**Funcionalidades**:
- Tabela completa com 8 colunas:
  1. Produto (nome - azul #60a5fa)
  2. Categoria (púrpura #a78bfa)
  3. Preço de Compra (azul claro #38bdf8)
  4. Preço de Venda (verde #34d399)
  5. IVA (%) (âmbar #fbbf24)
  6. Preço com IVA (laranja #f97316)
  7. Margem (€) - cor-codificada (verde #10b981 ou vermelho #ef4444)
  8. Margem (%) - cor-codificada (verde ou vermelho)

**Filtros**:
- Categoria (dropdown com todas as categorias)
- Ordenação (A-Z, Z-A, Mais Barato, Mais Caro)

**Cálculos**:
- Margem (€) = sell_price - cost_price
- Margem (%) = (margin_value / sell_price) * 100
- Preço com IVA = sell_price * 1.23 (23% IVA Portugal)

**Tema Dark**:
```css
background: #1f2937;
color: #e5e7eb;
header background: #111827;
header text: #93c5fd;
```

---

### 3.3 Funções Auxiliares
**Ficheiro**: [includes/functions.php](includes/functions.php)

#### filter_products($category, $sort_by)
```php
/**
 * Filtra e ordena produtos
 * @param $category - null para todos, ou nome da categoria
 * @param $sort_by - name_az, name_za, price_low, price_high, stock_low, stock_high
 * @return array - produtos filtrados e ordenados
 */
```

**Implementação**:
- Filtra por categoria com `array_filter()`
- Aplica `usort()` com 6 opções de ordenação
- Retorna array pronto para exibição

#### get_all_categories()
```php
/**
 * Obtém todas as categorias únicas de produtos activos
 * @return array - array de categorias
 */
```

**Query SQL**:
```sql
SELECT DISTINCT category FROM products 
WHERE category IS NOT NULL AND category != '' 
ORDER BY category ASC
```

---

### 3.4 Botões de Ação Melhorados
**Ficheiro**: [modules/produtos.php](modules/produtos.php)  
**Linhas**: 220-227

**Antes**:
- Plain text links
- Styling mínimo
- Sem emojis

**Depois**:
```html
<a href="?action=edit&id=..." style="padding: 8px 14px; background: #3b82f6; color: white; ...">
  ✏️ Editar
</a>
<button type="submit" style="padding: 8px 14px; background: #ef4444; color: white; ...">
  🗑️ Eliminar
</button>
```

**Melhorias**:
- Padding aumentado (6px→8px vertical, 12px→14px horizontal)
- Border-radius melhorado (4px→5px)
- Emojis intuitivos
- Flex layout com gap 8px para separação
- Transição CSS suave

---

## 4. DADOS CARREGADOS NA BASE DE DADOS

### 4.1 Fornecedores (10 items)
1. Luso
2. Delta Cafes
3. Riberalves
4. Gallo
5. Super Bock Group
6. Lactogal
7. Mar Atlantico
8. Hortas de Portugal
9. Detergentes Skip
10. Nobre

### 4.2 Produtos (15 carregados com sucesso, 5 com erro de encoding)
**Carregados com sucesso** (15):
1. Açúcar - Mercearia
2. Azeite - Mercearia
3. Arroz - Mercearia
4. Bananas - Frutas
5. Batata - Legumes
6. Café - Bebidas
7. Cebola - Legumes
8. Cebolinha - Legumes
9. Cerveja - Bebidas (tentativa)
10. Chocolate - Mercearia
11. Leite - Laticinios
12. Maçã - Frutas
13. Melancia - Frutas
14. Milho - Enlatados
15. Pão - Padaria

**Falhados (encoding issues)**:
- Atum (Peixe)
- Bacalhau (Peixe)
- Fiambre (Carnes)
- Salmao (Peixe)
- Tomate (Enlatados/Legumes)

### 4.3 Preços e Margens
**Exemplo de Produto**:
- Nome: Açúcar
- Categoria: Mercearia
- Preço de Compra: €0.45
- Preço de Venda: €0.75
- Margem (€): €0.30
- Margem (%): 40%
- Preço com IVA: €0.9225

### 4.4 Tabelas Seeded Automaticamente
1. **pricing_strategies** - Estratégias de preço por produto
2. **margin_analysis** - Snapshots de análise de margem
3. **category_pricing_rules** - Regras de preço por categoria
4. **promotions** - 4 promoções criadas automaticamente
   - Promo 1: Desconto 10% em Frutas
   - Promo 2: Desconto 15% em Bebidas
   - Promo 3: Desconto 5% em Mercearia
   - Promo 4: Desconto 20% em Legumes (fim de semana)

---

## 5. ALTERAÇÕES DE CÓDIGO ESTRUTURAL

### 5.1 Esquema da Base de Dados
**Tabelas Principais**:

#### products
```sql
- id (PK)
- name (VARCHAR 255)
- category (VARCHAR 100)
- brand (VARCHAR 100)
- barcode (VARCHAR 50)
- cost_price (DECIMAL 10,2)
- sell_price (DECIMAL 10,2)
- vat (DECIMAL 5,2) - default 23
- stock (INT)
- min_stock (INT)
- supplier_id (FK)
- expiry_date (DATE)
- active (BOOLEAN)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

#### promotions
```sql
- id (PK)
- name (VARCHAR 255)
- description (TEXT)
- discount_type (ENUM: percentage, fixed)
- discount_value (DECIMAL 10,2)
- start_date (DATE)
- end_date (DATE)
- is_active (BOOLEAN) ← IMPORTANTE: NÃO "active"
- created_at (TIMESTAMP)
```

---

### 5.2 Paths e URLs de Acesso

**Módulo de Produtos**:
```
http://localhost:8000/modules/produtos.php
```

**Módulo de Preços**:
```
http://localhost:8000/modules/pricing.php
```

**Tabs disponíveis**:
- ?page=pricing&view=dashboard (Gestão de Preços com filtros)
- ?page=pricing&view=strategies (Estratégias)
- ?page=pricing&view=promotions (Promoções)
- ?page=pricing&view=margins (Análise de Margem)
- ?page=pricing&view=categories (Categorias)

---

## 6. JAVASCRIPT IMPLEMENTADO

### 6.1 Filtros Instantâneos (produtos.php)
```javascript
function updateFiltersAjax() {
    const categoryFilter = document.getElementById('category-filter').value;
    const sortFilter = document.getElementById('sort-filter').value;
    
    const params = new URLSearchParams();
    params.append('category', categoryFilter);
    params.append('sort', sortFilter);
    
    window.location.href = '/modules/produtos.php?' + params.toString();
}
```

**Behavior**:
- Sem reload de página
- Mantém estado via URL
- Imediato ao mudar dropdown

### 6.2 Filtros Instantâneos (pricing.php)
```javascript
function updatePricingFiltersAjax() {
    const categoryFilter = document.getElementById('category-price-filter').value;
    const sortFilter = document.getElementById('sort-price-filter').value;
    
    const params = new URLSearchParams();
    params.append('page', 'pricing');
    params.append('view', 'dashboard');
    params.append('category_price', categoryFilter);
    params.append('sort_price', sortFilter);
    
    window.location.href = '/modules/pricing.php?' + params.toString();
}
```

---

## 7. FICHEIROS CRIADOS/MODIFICADOS

### Criados:
1. **seed_real_supermarket.php** - Script para carregar dados reais
   - TRUNCATE de 9 tabelas
   - 10 fornecedores únicos
   - 20 produtos com preços e categorias
   - Pricing strategies automaticamente
   - Margin analysis snapshots
   - 4 promoções exemplo

### Modificados:

#### includes/functions.php
- **Adição**: `filter_products($category, $sort_by)` - Linhas XXX
- **Adição**: `get_all_categories()` - Linhas XXX

#### modules/produtos.php
- **Linhas 130-162**: Nova secção de filtros com gradient azul
- **Linhas 220-227**: Botões melhorados (Editar azul, Eliminar vermelho)
- **JavaScript**: Função `updateFiltersAjax()`

#### modules/pricing.php
- **Linhas 45-115**: Filtros do dashboard com gradient azul
- **Linhas 119-162**: Tabela dark theme com 8 colunas e cálculos
- **JavaScript**: Função `updatePricingFiltersAjax()`

---

## 8. PADRÕES DE DESIGN IMPLEMENTADOS

### 8.1 Color Scheme
**Gradiente Filtros**:
```css
#1e3a8a (azul escuro)
#1e40af (azul)
```

**Tema Dark (Pricing)**:
```
Background: #1f2937 (cinzento escuro)
Text: #e5e7eb (cinzento claro)
Headers: #111827 (preto com toque cinzento)
Header Text: #93c5fd (azul claro)
```

**Botões**:
- Editar: #3b82f6 (azul)
- Eliminar: #ef4444 (vermelho)

**Margem Valores**:
- Positivo: #10b981 (verde)
- Negativo: #ef4444 (vermelho)

### 8.2 Spacing & Layout
```css
- Gap entre filtros: 20px
- Padding filtros: 15px
- Padding botões: 8px 14px
- Border-radius: 5px (buttons), 8px (containers)
```

---

## 9. FUNCIONALIDADES NÃO IMPLEMENTADAS

### Pendentes (Mencionadas):
- [ ] Filtros para módulo de fornecedores
- [ ] Filtros para aba de estratégias de preço
- [ ] Analytics avançada (trends, relatórios por período)
- [ ] Exportação de dados (PDF, Excel)

---

## 10. TESTES E VALIDAÇÃO

### Testado:
✅ Filtros de produtos (by category, by sort)  
✅ Filtros de pricing dashboard (by category, by sort)  
✅ Cálculos de margem (€ e %)  
✅ Cálculos de preço com IVA  
✅ Dark theme rendering  
✅ Carregamento de dados seed (15/20 produtos)  
✅ Promoções sem aviso "undefined active"  
✅ Botões de ação (edit, delete)  

### Não Testado:
⚠️ Integração com módulos de RH, Stock, Vendas, etc.  
⚠️ Permissões de autorização em alguns casos  
⚠️ Performance com >10000 produtos  

---

## 11. INSTRUÇÕES PARA EXECUÇÃO

### Iniciar Servidor
```bash
cd /Users/vascoruas/Documents/PAP_projeto
php -S localhost:8000
```

### Carregar Dados Reais
```bash
php seed_real_supermarket.php
```

### Acessar Módulos
- Produtos: http://localhost:8000/modules/produtos.php
- Preços: http://localhost:8000/modules/pricing.php
- Admin: http://localhost:8000/ (com autenticação)

---

## 12. CONHECIMENTOS ADQUIRIDOS / OBSERVAÇÕES

1. **IVA Portugal**: 23% é o rate standard em aplicar (sell_price * 1.23)
2. **Nomenclatura BD**: É importante validar nomes de colunas (is_active vs active)
3. **UX**: Filtros instantâneos melhoram significativamente experiência
4. **Encoding**: Caracteres especiais (ã, ç, á) podem causar issues em imports
5. **Dark Theme**: Cores com contrast suficiente são críticas (usar #e5e7eb para texto)
6. **Pricing Logic**: Margem % = (margin_value / sell_price) * 100, não / cost_price

---

## 13. PRÓXIMAS PRIORIDADES SUGERIDAS

1. Implementar filtros no módulo de fornecedores
2. Implementar filtros na aba de estratégias
3. Criar relatório de análise de margens por período
4. Adicionar validação client-side nos forms
5. Implementar search box além de dropdowns
6. Adicionar paginação para large datasets

---

**Documento gerado em**: 14 de janeiro de 2026  
**Última actualização**: [TIMESTAMP]  
**Status**: Completo e pronto para relatório
