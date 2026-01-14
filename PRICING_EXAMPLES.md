# 💼 PRICING - EXEMPLOS PRÁTICOS

Exemplos de código real para usar o sistema de preços.

---

## 📚 Índice

1. [Gestão de Estratégias](#gestão-de-estratégias)
2. [Cálculos de Margens](#cálculos-de-margens)
3. [Promoções](#promoções)
4. [Relatórios](#relatórios)
5. [Workflow Completo](#workflow-completo)

---

## Gestão de Estratégias

### Exemplo 1: Configurar estratégia para novo produto

```php
<?php
require_once 'includes/pricing.php';
require_once 'includes/auth.php';

// Verificar permissão
$auth->require_auth('pricing', 'edit');

// Novo produto: Maçã Gala
$product_id = 5;

// Estratégia:
// - Markup: 35% (padrão para frutas)
// - Preço mínimo: €2.50
// - Preço máximo: €4.00
// - Notas: Premium, importada da Espanha

$strategy_id = set_price_strategy(
    $product_id,
    35.00,
    2.50,
    4.00,
    'Premium, importada da Espanha'
);

// Produto tem custo €1.50
$cost = 1.50;

// Calcular preço de venda
$sell_price = calculate_sell_price($product_id, $cost);
echo "Maçã Gala - Preço calculado: €" . number_format($sell_price, 2);
// Output: Maçã Gala - Preço calculado: €2.03

// Registar esta análise
record_margin_analysis($product_id);
```

### Exemplo 2: Ajustar preços por categoria

```php
<?php
require_once 'includes/pricing.php';

// Atualizar regras para categoria "frutas"
// Nova estratégia: aumentar margem
update_category_pricing_rules(
    'frutas',
    40.00,        // Novo markup padrão (era 35%)
    15.00,        // Min margin (era 12%)
    20.00         // Max desconto (novo)
);

// Agora todos os novos produtos em frutas terão:
// - Markup 40% por padrão
// - Não podem ter margem < 15%
// - Desconto máximo 20%

$new_markup = get_category_default_markup('frutas');
echo "Novo markup para frutas: " . $new_markup . "%";
// Output: Novo markup para frutas: 40%
```

### Exemplo 3: Consultar estratégia existente

```php
<?php
require_once 'includes/pricing.php';

// Obter estratégia do produto 5 (Maçã)
$strategy = get_price_strategy(5);

if ($strategy) {
    echo "Produto ID: " . $strategy['product_id'] . "\n";
    echo "Markup: " . $strategy['markup_percent'] . "%\n";
    echo "Preço Mínimo: €" . $strategy['min_price'] . "\n";
    echo "Preço Máximo: €" . $strategy['max_price'] . "\n";
    echo "Notas: " . $strategy['notes'] . "\n";
}
```

---

## Cálculos de Margens

### Exemplo 4: Análise de margem de um produto

```php
<?php
require_once 'includes/pricing.php';

$product_id = 5;
$cost = 1.50;
$sell = 2.03;

// Calcular margem completa
$margin = calculate_margin($product_id, $cost, $sell);

echo "--- ANÁLISE DE MARGEM ---\n";
echo "Custo: €" . number_format($margin['cost_price'], 2) . "\n";
echo "Preço Venda: €" . number_format($margin['sell_price'], 2) . "\n";
echo "Margem (€): €" . number_format($margin['margin_amount'], 2) . "\n";
echo "Margem (%): " . number_format($margin['margin_percent'], 2) . "%\n";
echo "Markup (%): " . number_format($margin['markup_percent'], 2) . "%\n";

/*
Output:
--- ANÁLISE DE MARGEM ---
Custo: €1.50
Preço Venda: €2.03
Margem (€): €0.53
Margem (%): 26.11%
Markup (%): 35.33%
*/
```

### Exemplo 5: Histórico de margens

```php
<?php
require_once 'includes/pricing.php';

// Obter margens dos últimos 30 dias
$history = get_margin_history(5, 30);

echo "HISTÓRICO DE MARGENS - Últimos 30 dias\n";
echo "====================================\n";

foreach ($history as $record) {
    echo $record['analyzed_at'] . ": " . 
         number_format($record['margin_percent'], 2) . "% margin\n";
}

// Calcular tendência
if (count($history) > 1) {
    $first = $history[0]['margin_percent'];
    $last = $history[count($history) - 1]['margin_percent'];
    $change = $last - $first;
    
    echo "Tendência: " . ($change > 0 ? "↑ " : "↓ ") . 
         abs($change) . "%\n";
}
```

### Exemplo 6: Análise por categoria

```php
<?php
require_once 'includes/pricing.php';

// Análise das últimas 30 dias, todas categorias
$analysis = get_category_margin_analysis(null, 30);

echo "ANÁLISE DE MARGENS POR CATEGORIA\n";
echo "==================================\n";

foreach ($analysis as $cat) {
    $status = $cat['avg_margin'] >= 20 ? "✓ Boa" : 
              ($cat['avg_margin'] >= 10 ? "○ Média" : "⚠ Baixa");
    
    echo $cat['category'] . " ($status)\n";
    echo "  Margem Média: " . number_format($cat['avg_margin'], 2) . "%\n";
    echo "  Min/Max: " . number_format($cat['min_margin'], 2) . "% - " . 
         number_format($cat['max_margin'], 2) . "%\n";
    echo "  Produtos: " . $cat['product_count'] . "\n\n";
}
```

### Exemplo 7: Encontrar produtos subprecificados

```php
<?php
require_once 'includes/pricing.php';

// Encontrar produtos com margem abaixo do mínimo
$underpriced = find_underpriced_products();

if (count($underpriced) > 0) {
    echo "⚠️  PRODUTOS SUBPRECIFICADOS\n";
    echo "============================\n\n";
    
    foreach ($underpriced as $product) {
        echo "📦 " . $product['name'] . "\n";
        echo "   Categoria: " . $product['category'] . "\n";
        echo "   Margem Atual: " . number_format($product['margin_percent'], 2) . "%\n";
        echo "   ❌ Abaixo do mínimo para " . $product['category'] . "\n\n";
    }
} else {
    echo "✓ Todos os produtos têm margem adequada\n";
}
```

---

## Promoções

### Exemplo 8: Criar promoção por categoria

```php
<?php
require_once 'includes/pricing.php';
require_once 'includes/auth.php';

$auth->require_auth('pricing', 'edit');

// Promoção: Desconto fim de semana em frutas
$promo_id = create_promotion(
    'Desconto Frutas Fim Semana',
    'Desconto de 15% em frutas todo fim de semana',
    'percentage',           // tipo: percentagem
    15,                     // 15% de desconto
    '2026-01-18 00:00:00',  // Sábado
    '2026-01-19 23:59:59',  // Domingo
    'category'              // aplicar a categoria
);

// Adicionar categoria
add_category_to_promotion($promo_id, 'frutas');

echo "Promoção criada com ID: " . $promo_id . "\n";
```

### Exemplo 9: Criar promoção por produto

```php
<?php
require_once 'includes/pricing.php';

// Promoção: Black Friday em Maçã Gala
$promo_id = create_promotion(
    'Black Friday - Maçã Gala',
    'Desconto especial de €0.50 por unidade',
    'fixed',                // tipo: valor fixo
    0.50,                   // €0.50 de desconto
    '2026-01-24 00:00:00',
    '2026-01-24 23:59:59',
    'product'               // aplicar a produto
);

// Adicionar produto específico
add_product_to_promotion($promo_id, 5);

echo "Promoção Black Friday criada\n";
```

### Exemplo 10: Aplicar promoções ao preço

```php
<?php
require_once 'includes/pricing.php';

$product_id = 5;
$base_price = 2.03;

// Calcular preço com promoções ativas
$result = apply_promotions($product_id, $base_price);

echo "CÁLCULO COM PROMOÇÕES\n";
echo "====================\n";
echo "Preço Base: €" . number_format($result['original_price'], 2) . "\n";
echo "Preço Final: €" . number_format($result['discounted_price'], 2) . "\n";
echo "Desconto: €" . number_format($result['discount_amount'], 2) . 
     " (" . number_format($result['discount_percent'], 2) . "%)\n\n";

if (!empty($result['promotions'])) {
    echo "Promoções Aplicadas:\n";
    foreach ($result['promotions'] as $promo) {
        echo "  • " . $promo['name'] . "\n";
        echo "    " . $promo['value'] . 
             ($promo['type'] == 'percentage' ? "%" : "€") . "\n";
    }
}
```

### Exemplo 11: Listar promoções ativas

```php
<?php
require_once 'includes/pricing.php';

// Listar apenas promoções ativas
$active_promos = list_promotions(true, 20);

echo "PROMOÇÕES ATIVAS\n";
echo "================\n\n";

foreach ($active_promos as $promo) {
    $type_label = $promo['discount_type'] == 'percentage' ? 
                  $promo['discount_value'] . "%" : 
                  "€" . $promo['discount_value'];
    
    echo $promo['name'] . " (" . $type_label . ")\n";
    echo "  Período: " . $promo['start_date'] . " até " . 
         $promo['end_date'] . "\n";
    echo "  Aplica a: " . $promo['apply_to'] . "\n\n";
}
```

---

## Relatórios

### Exemplo 12: Relatório de performance

```php
<?php
require_once 'includes/pricing.php';

// Relatório dos últimos 30 dias
$report = get_pricing_performance_report(30);

echo "RELATÓRIO DE PERFORMANCE - Últimos 30 dias\n";
echo "===========================================\n\n";

$total_products = 0;
$total_underpriced = 0;

foreach ($report as $cat) {
    $total_products += $cat['total_products'];
    $total_underpriced += $cat['underpriced_count'];
    
    $avg_markup = $cat['avg_margin'] * 1.35; // Aprox
    
    echo "📊 " . strtoupper($cat['category']) . "\n";
    echo "   Produtos: " . $cat['total_products'] . "\n";
    echo "   Custo Médio: €" . number_format($cat['avg_cost'], 2) . "\n";
    echo "   Preço Médio: €" . number_format($cat['avg_sell_price'], 2) . "\n";
    echo "   Margem Média: " . number_format($cat['avg_margin'], 2) . "%\n";
    echo "   Subprecificados: " . $cat['underpriced_count'] . "\n\n";
}

echo "--- RESUMO GLOBAL ---\n";
echo "Total Produtos: " . $total_products . "\n";
echo "Total Subprecificados: " . $total_underpriced . "\n";
```

---

## Workflow Completo

### Exemplo 13: Setup completo para novo produto

```php
<?php
require_once 'includes/pricing.php';
require_once 'includes/auth.php';

$auth->require_auth('pricing', 'edit');

// ===== STEP 1: Criar estratégia =====
$product_id = 25;
$category = 'frutas';
$cost = 1.20;

// Obter markup padrão da categoria
$default_markup = get_category_default_markup($category);

// Criar estratégia com regras da categoria
$strategy_id = set_price_strategy(
    $product_id,
    $default_markup,
    0.50,          // Min: €0.50
    5.00,          // Max: €5.00
    'Novo produto adicionado'
);

// ===== STEP 2: Calcular preço =====
$sell_price = calculate_sell_price($product_id, $cost);

// ===== STEP 3: Registar análise =====
record_margin_analysis($product_id);

// ===== STEP 4: Gerar relatório inicial =====
$margin = calculate_margin($product_id, $cost, $sell_price);

echo "✓ NOVO PRODUTO CONFIGURADO\n";
echo "==========================\n";
echo "ID: " . $product_id . "\n";
echo "Categoria: " . $category . "\n";
echo "Custo: €" . number_format($cost, 2) . "\n";
echo "Preço: €" . number_format($sell_price, 2) . "\n";
echo "Margem: " . number_format($margin['margin_percent'], 2) . "%\n";
echo "Markup: " . number_format($margin['markup_percent'], 2) . "%\n";
```

### Exemplo 14: Integrar com sistema de vendas

```php
<?php
// Em um carrinho de compras, calcular preço com promoções:

require_once 'includes/pricing.php';

$items = [
    ['product_id' => 5, 'quantity' => 2],   // Maçã
    ['product_id' => 12, 'quantity' => 1],  // Banana
];

$cart_total = 0;
$cart_discount = 0;

foreach ($items as $item) {
    // Obter preço base
    $strategy = get_price_strategy($item['product_id']);
    $base_price = $strategy['calculated_price'] ?? 
                  calculate_sell_price($item['product_id']);
    
    // Aplicar promoções
    $result = apply_promotions($item['product_id'], $base_price);
    $final_price = $result['discounted_price'];
    
    // Adicionar ao total
    $item_total = $final_price * $item['quantity'];
    $cart_total += $item_total;
    $cart_discount += $result['discount_amount'] * $item['quantity'];
}

echo "Total: €" . number_format($cart_total, 2) . "\n";
echo "Desconto: €" . number_format($cart_discount, 2) . "\n";
```

---

## ✅ Checklist de Implementação

- [x] Sistema de estratégias de preço
- [x] Cálculos automáticos de margem
- [x] Histórico de preços com análise
- [x] Sistema de promoções
- [x] Relatórios de performance
- [ ] Integração com carrinho de compras (próxima iteração)
- [ ] Mobile app para gestão de preços (PHASE 7)

---

**Exemplos Práticos - Pricing**  
Versão 1.0 | PHASE 2
