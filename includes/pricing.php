<?php
/**
 * GESTÃO DE PREÇOS E PROMOÇÕES
 * Ficheiro: includes/pricing.php
 * 
 * Funções para estratégias de preços, cálculo de margens,
 * promoções e análise de rentabilidade
 */

require_once __DIR__ . '/functions.php';

// ============================================
// ESTRATÉGIAS DE PREÇOS
// ============================================

/**
 * Obtém ou cria estratégia de preço para um produto
 * 
 * @param int $product_id ID do produto
 * @return array|false Dados da estratégia ou false
 */
function get_price_strategy($product_id)
{
    $pdo = db_connect();
    // Tabela principal: pricing_strategies
    $stmt = $pdo->prepare('SELECT * FROM pricing_strategies WHERE product_id = ?');
    $stmt->execute([$product_id]);
    return $stmt->fetch();
}

/**
 * Cria ou atualiza estratégia de preço
 * 
 * @param int $product_id ID do produto
 * @param float $markup_percent Percentagem de markup
 * @param float|null $min_price Preço mínimo permitido
 * @param float|null $max_price Preço máximo permitido
 * @param string|null $notes Notas adicionais
 * @return int ID da estratégia
 */
function set_price_strategy($product_id, $markup_percent, $min_price = null, $max_price = null, $notes = null)
{
    $pdo = db_connect();
    
    $existing = get_price_strategy($product_id);
    
    if ($existing) {
        // Atualizar estratégia existente
        $stmt = $pdo->prepare('UPDATE pricing_strategies SET markup_percent = ?, min_price = ?, max_price = ?, notes = ? WHERE product_id = ?');
        $stmt->execute([$markup_percent, $min_price, $max_price, $notes, $product_id]);
        return $existing['id'];
    } else {
        // Criar nova estratégia
        $stmt = $pdo->prepare('INSERT INTO pricing_strategies (product_id, markup_percent, min_price, max_price, notes) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$product_id, $markup_percent, $min_price, $max_price, $notes]);
        return $pdo->lastInsertId();
    }
}

/**
 * Calcula preço de venda baseado no custo e estratégia
 * 
 * @param int $product_id ID do produto
 * @param float|null $cost_price Preço de custo (usa do produto se null)
 * @return float Preço de venda calculado
 */
function calculate_sell_price($product_id, $cost_price = null)
{
    $pdo = db_connect();
    
    // Obter preço de custo se não fornecido
    if ($cost_price === null) {
        $product = get_product($product_id);
        $cost_price = $product['cost_price'] ?? 0;
    }
    
    // Verificar se existe estratégia específica
    $strategy = get_price_strategy($product_id);
    
    if (!$strategy) {
        // Usar markup padrão da categoria
        $product = get_product($product_id);
        $category = $product['category'] ?? 'mercearia';
        $markup = get_category_default_markup($category);
    } else {
        $markup = $strategy['markup_percent'];
    }
    
    // Calcular preço: custo * (1 + markup%)
    $calculated_price = $cost_price * (1 + $markup / 100);
    
    // Aplicar limites mínimo/máximo se existir estratégia
    if ($strategy) {
        if ($strategy['min_price'] && $calculated_price < $strategy['min_price']) {
            $calculated_price = $strategy['min_price'];
        }
        if ($strategy['max_price'] && $calculated_price > $strategy['max_price']) {
            $calculated_price = $strategy['max_price'];
        }
    }
    
    return round($calculated_price, 2);
}

/**
 * Obtém markup padrão para uma categoria
 * 
 * @param string $category Nome da categoria
 * @return float Percentagem de markup (padrão 30%)
 */
function get_category_default_markup($category)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT default_markup_percent FROM category_pricing_rules WHERE category = ? AND active = 1');
    $stmt->execute([$category]);
    $row = $stmt->fetch();
    return $row['default_markup_percent'] ?? 30.00;
}

/**
 * Obtém regras de preço por categoria
 * 
 * @param string|null $category Categoria específica ou todas
 * @return array Regras da categoria ou lista de todas
 */
function get_category_pricing_rules($category = null)
{
    $pdo = db_connect();
    
    if ($category) {
        $stmt = $pdo->prepare('SELECT * FROM category_pricing_rules WHERE category = ?');
        $stmt->execute([$category]);
        return $stmt->fetch();
    } else {
        return $pdo->query('SELECT * FROM category_pricing_rules WHERE active = 1 ORDER BY category')->fetchAll();
    }
}

/**
 * Atualiza regras de preço de uma categoria
 * 
 * @param string $category Nome da categoria
 * @param float $default_markup Markup padrão (%)
 * @param float $min_margin Margem mínima (%)
 * @param float $max_discount Desconto máximo (%)
 * @return bool Sucesso ou falha
 */
function update_category_pricing_rules($category, $default_markup, $min_margin, $max_discount)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('UPDATE category_pricing_rules SET default_markup_percent = ?, min_margin_percent = ?, max_discount_percent = ? WHERE category = ?');
    return $stmt->execute([$default_markup, $min_margin, $max_discount, $category]);
}

// ============================================
// CÁLCULO DE MARGENS
// ============================================

/**
 * Calcula informações de margem para um produto
 * 
 * Margem = (Venda - Custo) / Venda * 100
 * Markup = (Venda - Custo) / Custo * 100
 * 
 * @param int $product_id ID do produto
 * @param float|null $cost_price Preço de custo
 * @param float|null $sell_price Preço de venda
 * @return array Dados da margem
 */
function calculate_margin($product_id, $cost_price = null, $sell_price = null)
{
    $product = get_product($product_id);
    
    // Usar preços do produto se não fornecidos
    if ($cost_price === null) {
        $cost_price = $product['cost_price'] ?? 0;
    }
    if ($sell_price === null) {
        $sell_price = $product['sell_price'] ?? 0;
    }
    
    // Evitar divisão por zero
    if ($cost_price == 0) {
        return [
            'cost_price' => $cost_price,
            'sell_price' => $sell_price,
            'margin_amount' => 0,
            'margin_percent' => 0,
            'markup_percent' => 0
        ];
    }
    
    // Calcular margem e markup
    $margin_amount = $sell_price - $cost_price;
    $margin_percent = ($margin_amount / $sell_price) * 100;
    $markup_percent = ($margin_amount / $cost_price) * 100;
    
    return [
        'cost_price' => $cost_price,
        'sell_price' => $sell_price,
        'margin_amount' => round($margin_amount, 2),
        'margin_percent' => round($margin_percent, 2),
        'markup_percent' => round($markup_percent, 2)
    ];
}

/**
 * Regista snapshot de análise de margem
 * 
 * @param int $product_id ID do produto
 * @return bool Sucesso ou falha
 */
function record_margin_analysis($product_id)
{
    $pdo = db_connect();
    $product = get_product($product_id);
    
    if (!$product) return false;
    
    $margin = calculate_margin($product_id, $product['cost_price'], $product['sell_price']);
    
    $stmt = $pdo->prepare('INSERT INTO margin_analysis (product_id, cost_price, sell_price, margin_amount, margin_percent, markup_percent, category) VALUES (?, ?, ?, ?, ?, ?, ?)');
    return $stmt->execute([
        $product_id,
        $margin['cost_price'],
        $margin['sell_price'],
        $margin['margin_amount'],
        $margin['margin_percent'],
        $margin['markup_percent'],
        $product['category']
    ]);
}

/**
 * Obtém histórico de margens de um produto
 * 
 * @param int $product_id ID do produto
 * @param int $days Dias de histórico (padrão 90)
 * @return array Lista de registos
 */
function get_margin_history($product_id, $days = 90)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT * FROM margin_analysis WHERE product_id = ? AND analyzed_at >= DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY analyzed_at DESC');
    $stmt->execute([$product_id, $days]);
    return $stmt->fetchAll();
}

// ============================================
// HISTÓRICO DE ALTERAÇÕES DE PREÇO
// ============================================

/**
 * Regista alteração de preço no log
 * 
 * @param int $product_id ID do produto
 * @param float $old_cost Preço de custo antigo
 * @param float $new_cost Preço de custo novo
 * @param float $old_sell Preço de venda antigo
 * @param float $new_sell Preço de venda novo
 * @param string|null $reason Motivo da alteração
 * @return bool Sucesso ou falha
 */
function log_price_change($product_id, $old_cost, $new_cost, $old_sell, $new_sell, $reason = null)
{
    $pdo = db_connect();
    $user_id = $_SESSION['user_id'] ?? null;
    
    // Calcular margens antiga e nova
    $old_margin = null;
    $new_margin = null;
    
    if ($old_cost > 0) {
        $old_margin = (($old_sell - $old_cost) / $old_sell) * 100;
    }
    if ($new_cost > 0) {
        $new_margin = (($new_sell - $new_cost) / $new_sell) * 100;
    }
    
    $stmt = $pdo->prepare('INSERT INTO price_change_log (product_id, old_cost_price, new_cost_price, old_sell_price, new_sell_price, old_margin, new_margin, change_reason, changed_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    return $stmt->execute([
        $product_id,
        $old_cost,
        $new_cost,
        $old_sell,
        $new_sell,
        $old_margin,
        $new_margin,
        $reason,
        $user_id
    ]);
}

/**
 * Obtém histórico de alterações de preço
 * 
 * @param int|null $product_id ID do produto ou null para todos
 * @param int $limit Limite de registos
 * @return array Lista de alterações
 */
function get_price_change_history($product_id = null, $limit = 100)
{
    $pdo = db_connect();
    
    if ($product_id) {
        $stmt = $pdo->prepare('SELECT pcl.*, p.name, u.name as changed_by_name FROM price_change_log pcl LEFT JOIN products p ON pcl.product_id = p.id LEFT JOIN users u ON pcl.changed_by = u.id WHERE pcl.product_id = ? ORDER BY pcl.changed_at DESC LIMIT ?');
        $stmt->execute([$product_id, $limit]);
    } else {
        $stmt = $pdo->prepare('SELECT pcl.*, p.name, u.name as changed_by_name FROM price_change_log pcl LEFT JOIN products p ON pcl.product_id = p.id LEFT JOIN users u ON pcl.changed_by = u.id ORDER BY pcl.changed_at DESC LIMIT ?');
        $stmt->execute([$limit]);
    }
    
    return $stmt->fetchAll();
}

// ============================================
// PROMOÇÕES
// ============================================

/**
 * Cria uma nova promoção
 * 
 * @param string $name Nome da promoção
 * @param string $description Descrição
 * @param string $discount_type Tipo: 'percentage' ou 'fixed'
 * @param float $discount_value Valor do desconto
 * @param string $start_date Data de início
 * @param string $end_date Data de fim
 * @param string $apply_to Aplicar a: 'product' ou 'category'
 * @return int ID da promoção criada
 */
function create_promotion($name, $description, $discount_type, $discount_value, $start_date, $end_date, $apply_to = 'product')
{
    $pdo = db_connect();
    $user_id = $_SESSION['user_id'] ?? null;
    
    $stmt = $pdo->prepare('INSERT INTO promotions (name, description, discount_type, discount_value, start_date, end_date, apply_to, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$name, $description, $discount_type, $discount_value, $start_date, $end_date, $apply_to, $user_id]);
    
    return $pdo->lastInsertId();
}

/**
 * Adiciona produto a uma promoção
 * 
 * @param int $promotion_id ID da promoção
 * @param int $product_id ID do produto
 * @return bool Sucesso ou falha
 */
function add_product_to_promotion($promotion_id, $product_id)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('INSERT IGNORE INTO promotion_products (promotion_id, product_id) VALUES (?, ?)');
    return $stmt->execute([$promotion_id, $product_id]);
}

/**
 * Adiciona categoria a uma promoção
 * 
 * @param int $promotion_id ID da promoção
 * @param string $category Nome da categoria
 * @return bool Sucesso ou falha
 */
function add_category_to_promotion($promotion_id, $category)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('INSERT IGNORE INTO promotion_categories (promotion_id, category) VALUES (?, ?)');
    return $stmt->execute([$promotion_id, $category]);
}

/**
 * Obtém promoções ativas para um produto
 * Verifica promoções por produto e por categoria
 * 
 * @param int $product_id ID do produto
 * @return array Lista de promoções ativas
 */
function get_active_promotions_for_product($product_id)
{
    $pdo = db_connect();
    
    // Promoções por ID do produto
    $stmt = $pdo->prepare('SELECT p.* FROM promotions p JOIN promotion_products pp ON p.id = pp.promotion_id WHERE pp.product_id = ? AND p.is_active = 1 AND p.start_date <= NOW() AND p.end_date >= NOW()');
    $stmt->execute([$product_id]);
    $promotions = $stmt->fetchAll();
    
    // Promoções por categoria
    $product = get_product($product_id);
    if ($product && $product['category']) {
        $stmt = $pdo->prepare('SELECT p.* FROM promotions p JOIN promotion_categories pc ON p.id = pc.promotion_id WHERE pc.category = ? AND p.is_active = 1 AND p.start_date <= NOW() AND p.end_date >= NOW()');
        $stmt->execute([$product['category']]);
        $category_promos = $stmt->fetchAll();
        $promotions = array_merge($promotions, $category_promos);
    }
    
    // Remover duplicados
    return array_unique($promotions, SORT_REGULAR);
}

/**
 * Aplica promoções e calcula preço com desconto
 * 
 * @param int $product_id ID do produto
 * @param float|null $sell_price Preço de venda base
 * @return array Detalhes do preço com desconto
 */
function apply_promotions($product_id, $sell_price = null)
{
    // Obter preço se não fornecido
    if ($sell_price === null) {
        $product = get_product($product_id);
        $sell_price = $product['sell_price'] ?? 0;
    }
    
    // Obter promoções ativas
    $promotions = get_active_promotions_for_product($product_id);
    
    // Se não há promoções, retornar preço original
    if (empty($promotions)) {
        return [
            'original_price' => $sell_price,
            'discounted_price' => $sell_price,
            'discount_amount' => 0,
            'discount_percent' => 0,
            'promotions' => []
        ];
    }
    
    // Calcular desconto total (soma de todas as promoções)
    $total_discount = 0;
    $applied_promos = [];
    
    foreach ($promotions as $promo) {
        if ($promo['discount_type'] === 'percentage') {
            // Desconto percentual
            $discount = ($sell_price * $promo['discount_value']) / 100;
        } else {
            // Desconto fixo
            $discount = $promo['discount_value'];
        }
        
        $total_discount += $discount;
        $applied_promos[] = [
            'id' => $promo['id'],
            'name' => $promo['name'],
            'type' => $promo['discount_type'],
            'value' => $promo['discount_value']
        ];
    }
    
    // Garantir que preço não fica negativo
    $discounted_price = max(0, $sell_price - $total_discount);
    
    return [
        'original_price' => $sell_price,
        'discounted_price' => round($discounted_price, 2),
        'discount_amount' => round($total_discount, 2),
        'discount_percent' => round(($total_discount / $sell_price) * 100, 2),
        'promotions' => $applied_promos
    ];
}

/**
 * Lista todas as promoções
 * 
 * @param bool $active_only Apenas ativas
 * @param int $limit Limite de resultados
 * @return array Lista de promoções
 */
function list_promotions($active_only = true, $limit = 50)
{
    $pdo = db_connect();
    $limit = max(1, (int)$limit);
    
    if ($active_only) {
        $stmt = $pdo->query("SELECT * FROM promotions WHERE is_active = 1 ORDER BY start_date DESC LIMIT {$limit}");
    } else {
        $stmt = $pdo->query("SELECT * FROM promotions ORDER BY start_date DESC LIMIT {$limit}");
    }
    
    return $stmt->fetchAll();
}

/**
 * Obtém detalhes de uma promoção com produtos e categorias
 * 
 * @param int $promotion_id ID da promoção
 * @return array|null Dados da promoção ou null
 */
function get_promotion($promotion_id)
{
    $pdo = db_connect();
    
    // Dados base da promoção
    $stmt = $pdo->prepare('SELECT * FROM promotions WHERE id = ?');
    $stmt->execute([$promotion_id]);
    $promotion = $stmt->fetch();
    
    if (!$promotion) return null;
    
    // Produtos incluídos na promoção
    $stmt = $pdo->prepare('SELECT p.* FROM products p JOIN promotion_products pp ON p.id = pp.product_id WHERE pp.promotion_id = ?');
    $stmt->execute([$promotion_id]);
    $promotion['products'] = $stmt->fetchAll();
    
    // Categorias incluídas na promoção
    $stmt = $pdo->prepare('SELECT category FROM promotion_categories WHERE promotion_id = ?');
    $stmt->execute([$promotion_id]);
    $promotion['categories'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    return $promotion;
}

// ============================================
// ANÁLISE E RELATÓRIOS
// ============================================

/**
 * Análise de margens por categoria
 * 
 * @param string|null $category Categoria específica ou todas
 * @param int $days Período em dias
 * @return array Estatísticas de margem
 */
function get_category_margin_analysis($category = null, $days = 30)
{
    $pdo = db_connect();
    
    if ($category) {
        $sql = 'SELECT category, AVG(margin_percent) as avg_margin, MIN(margin_percent) as min_margin, MAX(margin_percent) as max_margin, AVG(markup_percent) as avg_markup, COUNT(DISTINCT product_id) as product_count FROM margin_analysis WHERE category = ? AND analyzed_at >= DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY category';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$category, $days]);
        return $stmt->fetch();
    } else {
        $sql = 'SELECT category, AVG(margin_percent) as avg_margin, MIN(margin_percent) as min_margin, MAX(margin_percent) as max_margin, AVG(markup_percent) as avg_markup, COUNT(DISTINCT product_id) as product_count FROM margin_analysis WHERE analyzed_at >= DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY category ORDER BY avg_margin DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }
}

/**
 * Encontra produtos com preço abaixo da margem mínima
 * 
 * @param float|null $min_margin_threshold Limiar de margem
 * @return array Lista de produtos subvalorizados
 */
function find_underpriced_products($min_margin_threshold = null)
{
    $pdo = db_connect();
    
    $sql = 'SELECT p.id, p.name, p.category, p.cost_price, p.sell_price, 
                   ((p.sell_price - p.cost_price) / p.sell_price * 100) as margin_percent,
                   cpr.min_margin_percent
            FROM products p 
            LEFT JOIN category_pricing_rules cpr ON p.category = cpr.category 
            WHERE p.active = 1 
            AND ((p.sell_price - p.cost_price) / p.sell_price * 100) < COALESCE(cpr.min_margin_percent, 10)
            ORDER BY margin_percent ASC';
    
    return $pdo->query($sql)->fetchAll();
}

/**
 * Relatório de desempenho de preços por categoria
 * 
 * @param int $days Período em dias
 * @return array Dados do relatório
 */
function get_pricing_performance_report($days = 30)
{
    $pdo = db_connect();
    
    $sql = 'SELECT 
                p.category,
                COUNT(DISTINCT p.id) as total_products,
                AVG(p.cost_price) as avg_cost,
                AVG(p.sell_price) as avg_sell_price,
                AVG((p.sell_price - p.cost_price) / p.sell_price * 100) as avg_margin,
                SUM(CASE WHEN ((p.sell_price - p.cost_price) / p.sell_price * 100) < 10 THEN 1 ELSE 0 END) as underpriced_count
            FROM products p
            WHERE p.active = 1
            GROUP BY p.category
            ORDER BY avg_margin DESC';
    
    return $pdo->query($sql)->fetchAll();
}
