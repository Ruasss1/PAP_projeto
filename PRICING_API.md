# 📚 PRICING API REFERENCE

Documentação completa da API de gestão de preços.

---

## 🔧 Funções Disponíveis

### **Estratégias de Preço**

#### `get_price_strategy($product_id)`
Obtém estratégia de preço para um produto.

**Parâmetros:**
- `$product_id` (int) - ID do produto

**Retorna:**
```php
[
    'id' => 1,
    'product_id' => 5,
    'markup_percent' => 35.00,
    'min_price' => 5.00,
    'max_price' => 100.00,
    'notes' => '...'
]
```

**Exemplo:**
```php
$strategy = get_price_strategy(5);
echo $strategy['markup_percent']; // 35.00
```

---

#### `set_price_strategy($product_id, $markup_percent, $min_price, $max_price, $notes)`
Cria ou atualiza estratégia de preço.

**Parâmetros:**
- `$product_id` (int) - ID do produto
- `$markup_percent` (decimal) - Markup em % (ex: 30 para 30%)
- `$min_price` (decimal, nullable) - Preço mínimo
- `$max_price` (decimal, nullable) - Preço máximo
- `$notes` (string, nullable) - Notas

**Retorna:**
```php
123 // ID da estratégia
```

**Exemplo:**
```php
set_price_strategy(5, 35.00, 5.00, 150.00, 'Produto premium');
```

---

#### `calculate_sell_price($product_id, $cost_price = null)`
Calcula preço de venda baseado em estratégia.

**Parâmetros:**
- `$product_id` (int) - ID do produto
- `$cost_price` (decimal, nullable) - Custo (se null, pega do produto)

**Retorna:**
```php
13.50 // Preço calculado
```

**Exemplo:**
```php
// Produto com custo 10€, markup 35%
$sell = calculate_sell_price(5);
echo $sell; // 13.50
```

---

#### `get_category_default_markup($category)`
Obtém markup padrão para categoria.

**Parâmetros:**
- `$category` (string) - Nome da categoria

**Retorna:**
```php
35.00 // Markup em %
```

**Exemplo:**
```php
$markup = get_category_default_markup('frutas');
echo $markup; // 35.00
```

---

#### `get_category_pricing_rules($category = null)`
Obtém regras de preço por categoria.

**Parâmetros:**
- `$category` (string, nullable) - Se null, retorna todas

**Retorna:**
```php
// Uma categoria:
['category' => 'frutas', 'default_markup_percent' => 35.00, ...]

// Todas:
[
    ['category' => 'frutas', ...],
    ['category' => 'padaria', ...],
    ...
]
```

**Exemplo:**
```php
$rules = get_category_pricing_rules('frutas');
echo $rules['min_margin_percent']; // 12.00
```

---

#### `update_category_pricing_rules($category, $default_markup, $min_margin, $max_discount)`
Atualiza regras de preço para categoria.

**Parâmetros:**
- `$category` (string) - Nome da categoria
- `$default_markup` (decimal) - Novo markup padrão
- `$min_margin` (decimal) - Margem mínima
- `$max_discount` (decimal) - Desconto máximo permitido

**Retorna:**
```php
true // Sucesso
```

**Exemplo:**
```php
update_category_pricing_rules('frutas', 40.00, 15.00, 25.00);
```

---

### **Análise de Margem**

#### `calculate_margin($product_id, $cost_price = null, $sell_price = null)`
Calcula margem (lucro) de um produto.

**Parâmetros:**
- `$product_id` (int) - ID do produto
- `$cost_price` (decimal, nullable) - Custo (se null, pega do produto)
- `$sell_price` (decimal, nullable) - Preço (se null, pega do produto)

**Retorna:**
```php
[
    'cost_price' => 10.00,
    'sell_price' => 13.50,
    'margin_amount' => 3.50,        // Lucro em €
    'margin_percent' => 25.93,       // Lucro em %
    'markup_percent' => 35.00        // Markup em %
]
```

**Exemplo:**
```php
$margin = calculate_margin(5);
echo $margin['margin_percent']; // 25.93%
```

---

#### `record_margin_analysis($product_id)`
Registar snapshot de margem para análise histórica.

**Parâmetros:**
- `$product_id` (int) - ID do produto

**Retorna:**
```php
true // Sucesso
```

**Exemplo:**
```php
record_margin_analysis(5);
```

---

#### `get_margin_history($product_id, $days = 90)`
Obtém histórico de margens.

**Parâmetros:**
- `$product_id` (int) - ID do produto
- `$days` (int) - Dias para trás

**Retorna:**
```php
[
    ['product_id' => 5, 'margin_percent' => 25.93, 'analyzed_at' => '2026-01-14 10:00:00'],
    ['product_id' => 5, 'margin_percent' => 24.50, 'analyzed_at' => '2026-01-13 10:00:00'],
    ...
]
```

**Exemplo:**
```php
$history = get_margin_history(5, 30); // Últimos 30 dias
```

---

#### `get_category_margin_analysis($category = null, $days = 30)`
Análise de margem por categoria.

**Parâmetros:**
- `$category` (string, nullable) - Se null, retorna todas
- `$days` (int) - Dias para trás

**Retorna:**
```php
// Uma categoria:
[
    'category' => 'frutas',
    'avg_margin' => 28.45,
    'min_margin' => 15.20,
    'max_margin' => 45.10,
    'product_count' => 12
]

// Todas:
[...]  // Array com múltiplas categorias
```

**Exemplo:**
```php
$analysis = get_category_margin_analysis('frutas', 30);
echo $analysis['avg_margin']; // 28.45%
```

---

#### `find_underpriced_products($min_margin_threshold = null)`
Encontra produtos com margem abaixo do mínimo.

**Parâmetros:**
- `$min_margin_threshold` (decimal, nullable) - Threshold customizado

**Retorna:**
```php
[
    ['id' => 5, 'name' => 'Maçã', 'category' => 'frutas', 'margin_percent' => 5.2],
    ['id' => 12, 'name' => 'Banana', 'category' => 'frutas', 'margin_percent' => 8.5],
    ...
]
```

**Exemplo:**
```php
$underpriced = find_underpriced_products();
foreach ($underpriced as $p) {
    echo $p['name'] . ': ' . $p['margin_percent'] . '%';
}
```

---

#### `get_pricing_performance_report($days = 30)`
Relatório de performance de preços.

**Parâmetros:**
- `$days` (int) - Período a analisar

**Retorna:**
```php
[
    [
        'category' => 'frutas',
        'total_products' => 12,
        'avg_cost' => 2.50,
        'avg_sell_price' => 3.75,
        'avg_margin' => 33.33,
        'underpriced_count' => 2
    ],
    ...
]
```

**Exemplo:**
```php
$report = get_pricing_performance_report(30);
```

---

### **Histórico de Preços**

#### `log_price_change($product_id, $old_cost, $new_cost, $old_sell, $new_sell, $reason)`
Registar mudança de preço.

**Parâmetros:**
- `$product_id` (int) - ID do produto
- `$old_cost` (decimal) - Custo antigo
- `$new_cost` (decimal) - Custo novo
- `$old_sell` (decimal) - Preço antigo
- `$new_sell` (decimal) - Preço novo
- `$reason` (string, nullable) - Motivo da mudança

**Retorna:**
```php
true // Sucesso
```

**Exemplo:**
```php
log_price_change(5, 10, 11, 13.50, 14.85, 'Reajuste inflacionário');
```

---

#### `get_price_change_history($product_id = null, $limit = 100)`
Obtém histórico de mudanças de preço.

**Parâmetros:**
- `$product_id` (int, nullable) - Se null, retorna todas
- `$limit` (int) - Máximo de registos

**Retorna:**
```php
[
    [
        'product_id' => 5,
        'name' => 'Maçã',
        'old_cost_price' => 10,
        'new_cost_price' => 11,
        'old_sell_price' => 13.50,
        'new_sell_price' => 14.85,
        'old_margin' => 25.93,
        'new_margin' => 25.92,
        'change_reason' => 'Reajuste',
        'changed_by_name' => 'João Silva',
        'changed_at' => '2026-01-14 10:00:00'
    ],
    ...
]
```

**Exemplo:**
```php
$history = get_price_change_history(5);
```

---

### **Promoções**

#### `create_promotion($name, $description, $discount_type, $discount_value, $start_date, $end_date, $apply_to)`
Cria nova promoção.

**Parâmetros:**
- `$name` (string) - Nome da promoção
- `$description` (string) - Descrição
- `$discount_type` (string) - 'percentage' ou 'fixed'
- `$discount_value` (decimal) - Valor (% ou €)
- `$start_date` (datetime) - Início
- `$end_date` (datetime) - Fim
- `$apply_to` (string) - 'product', 'category', ou 'all'

**Retorna:**
```php
123 // ID da promoção
```

**Exemplo:**
```php
$promo_id = create_promotion(
    'Desconto Fim Semana',
    'Desconto especial para fim de semana',
    'percentage',
    15,
    '2026-01-18',
    '2026-01-19',
    'category'
);
```

---

#### `add_product_to_promotion($promotion_id, $product_id)`
Adiciona produto à promoção.

**Parâmetros:**
- `$promotion_id` (int) - ID da promoção
- `$product_id` (int) - ID do produto

**Retorna:**
```php
true // Sucesso
```

**Exemplo:**
```php
add_product_to_promotion(5, 12);
```

---

#### `add_category_to_promotion($promotion_id, $category)`
Adiciona categoria à promoção.

**Parâmetros:**
- `$promotion_id` (int) - ID da promoção
- `$category` (string) - Nome da categoria

**Retorna:**
```php
true // Sucesso
```

**Exemplo:**
```php
add_category_to_promotion(5, 'frutas');
```

---

#### `get_active_promotions_for_product($product_id)`
Obtém promoções ativas para um produto.

**Parâmetros:**
- `$product_id` (int) - ID do produto

**Retorna:**
```php
[
    ['id' => 1, 'name' => 'Desconto 15%', 'discount_type' => 'percentage', 'discount_value' => 15],
    ['id' => 2, 'name' => 'Desconto 2€', 'discount_type' => 'fixed', 'discount_value' => 2],
    ...
]
```

**Exemplo:**
```php
$promos = get_active_promotions_for_product(5);
```

---

#### `apply_promotions($product_id, $sell_price = null)`
Calcula preço final com promoções aplicadas.

**Parâmetros:**
- `$product_id` (int) - ID do produto
- `$sell_price` (decimal, nullable) - Preço base

**Retorna:**
```php
[
    'original_price' => 13.50,
    'discounted_price' => 11.48,      // Com desconto
    'discount_amount' => 2.02,         // €
    'discount_percent' => 14.96,       // %
    'promotions' => [
        ['id' => 1, 'name' => 'Desconto 15%', 'type' => 'percentage', 'value' => 15],
        ...
    ]
]
```

**Exemplo:**
```php
$result = apply_promotions(5, 13.50);
echo $result['discounted_price']; // 11.48
```

---

#### `list_promotions($active_only = true, $limit = 50)`
Lista promoções.

**Parâmetros:**
- `$active_only` (bool) - Se true, apenas ativas
- `$limit` (int) - Limite de registos

**Retorna:**
```php
[
    ['id' => 1, 'name' => 'Desconto 15%', 'active' => 1, ...],
    ...
]
```

**Exemplo:**
```php
$active = list_promotions(true);
```

---

#### `get_promotion($promotion_id)`
Obtém detalhes completos de uma promoção.

**Parâmetros:**
- `$promotion_id` (int) - ID da promoção

**Retorna:**
```php
[
    'id' => 1,
    'name' => 'Desconto 15%',
    'products' => [[...], [...], ...],
    'categories' => ['frutas', 'padaria'],
    ...
]
```

**Exemplo:**
```php
$promo = get_promotion(5);
```

---

## 📋 Tabelas de Referência

### **Categorias Pré-configuradas**
```
frutas        → 35% markup, 12% min margin
padaria       → 40% markup, 15% min margin
laticinios    → 30% markup, 10% min margin
mercearia     → 25% markup, 8% min margin
bebidas       → 20% markup, 8% min margin
congelados    → 28% markup, 10% min margin
limpeza       → 45% markup, 20% min margin (alta margem)
higiene       → 40% markup, 18% min margin
```

### **Tipos de Desconto**
```
'percentage'  → Desconto em % (ex: 15%)
'fixed'       → Desconto em € (ex: 2€)
```

---

## 🔒 Permissões Necessárias

Para usar funções de preços, usuário deve ter permissão:
```php
$auth->require_auth('pricing', 'view');  // Ver preços
$auth->require_auth('pricing', 'edit');  // Editar preços
```

---

## ⚠️ Notas de Performance

- Cálculos são otimizados com índices
- Snapshots de margem são gravados diariamente
- Limite padrão: 100 registos por consulta

---

**Referência Completa da Pricing API**  
Versão 1.0 | PHASE 2
