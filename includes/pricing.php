<?php
// includes/pricing.php
// Pricing management functions - estratégias, promoções e análise de margem

require_once __DIR__ . '/functions.php';

/**
 * Get or create price strategy for a product
 */
function get_price_strategy($product_id)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT * FROM price_strategies WHERE product_id = ?');
    $stmt->execute([$product_id]);
    return $stmt->fetch();
}

/**
 * Create or update price strategy
 */
function set_price_strategy($product_id, $markup_percent, $min_price = null, $max_price = null, $notes = null)
{
    $pdo = db_connect();
    
    $existing = get_price_strategy($product_id);
    
    if ($existing) {
        // Update
        $stmt = $pdo->prepare('UPDATE price_strategies SET markup_percent = ?, min_price = ?, max_price = ?, notes = ? WHERE product_id = ?');
        $stmt->execute([$markup_percent, $min_price, $max_price, $notes, $product_id]);
        return $existing['id'];
    } else {
        // Create
        $stmt = $pdo->prepare('INSERT INTO price_strategies (product_id, markup_percent, min_price, max_price, notes) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$product_id, $markup_percent, $min_price, $max_price, $notes]);
        return $pdo->lastInsertId();
    }
}

/**
 * Calculate sell price based on cost and strategy
 */
function calculate_sell_price($product_id, $cost_price = null)
{
    $pdo = db_connect();
    
    if ($cost_price === null) {
        $product = get_product($product_id);
        $cost_price = $product['cost_price'] ?? 0;
    }
    
    $strategy = get_price_strategy($product_id);
    
    if (!$strategy) {
        // Use default for category
        $product = get_product($product_id);
        $category = $product['category'] ?? 'mercearia';
        $markup = get_category_default_markup($category);
    } else {
        $markup = $strategy['markup_percent'];
    }
    
    $calculated_price = $cost_price * (1 + $markup / 100);
    
    // Apply min/max constraints if strategy exists
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
 * Get default markup for category
 */
function get_category_default_markup($category)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT default_markup_percent FROM category_pricing_rules WHERE category = ? AND active = 1');
    $stmt->execute([$category]);
    $row = $stmt->fetch();
    return $row['default_markup_percent'] ?? 30.00; // Default fallback
}

/**
 * Get category pricing rules
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
 * Update category pricing rules
 */
function update_category_pricing_rules($category, $default_markup, $min_margin, $max_discount)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('UPDATE category_pricing_rules SET default_markup_percent = ?, min_margin_percent = ?, max_discount_percent = ? WHERE category = ?');
    return $stmt->execute([$default_markup, $min_margin, $max_discount, $category]);
}

/**
 * Calculate margin information for a product
 */
function calculate_margin($product_id, $cost_price = null, $sell_price = null)
{
    $product = get_product($product_id);
    
    if ($cost_price === null) {
        $cost_price = $product['cost_price'] ?? 0;
    }
    if ($sell_price === null) {
        $sell_price = $product['sell_price'] ?? 0;
    }
    
    if ($cost_price == 0) {
        return [
            'cost_price' => $cost_price,
            'sell_price' => $sell_price,
            'margin_amount' => 0,
            'margin_percent' => 0,
            'markup_percent' => 0
        ];
    }
    
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
 * Record margin analysis snapshot
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
 * Get margin history for a product
 */
function get_margin_history($product_id, $days = 90)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT * FROM margin_analysis WHERE product_id = ? AND analyzed_at >= DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY analyzed_at DESC');
    $stmt->execute([$product_id, $days]);
    return $stmt->fetchAll();
}

/**
 * Log price change
 */
function log_price_change($product_id, $old_cost, $new_cost, $old_sell, $new_sell, $reason = null)
{
    $pdo = db_connect();
    $user_id = $_SESSION['user_id'] ?? null;
    
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
 * Get price change history
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
// PROMOTIONS
// ============================================

/**
 * Create a new promotion
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
 * Add product to promotion
 */
function add_product_to_promotion($promotion_id, $product_id)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('INSERT IGNORE INTO promotion_products (promotion_id, product_id) VALUES (?, ?)');
    return $stmt->execute([$promotion_id, $product_id]);
}

/**
 * Add category to promotion
 */
function add_category_to_promotion($promotion_id, $category)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('INSERT IGNORE INTO promotion_categories (promotion_id, category) VALUES (?, ?)');
    return $stmt->execute([$promotion_id, $category]);
}

/**
 * Get active promotions for a product
 */
function get_active_promotions_for_product($product_id)
{
    $pdo = db_connect();
    
    // By product ID
    $stmt = $pdo->prepare('SELECT p.* FROM promotions p JOIN promotion_products pp ON p.id = pp.promotion_id WHERE pp.product_id = ? AND p.active = 1 AND p.start_date <= NOW() AND p.end_date >= NOW()');
    $stmt->execute([$product_id]);
    $promotions = $stmt->fetchAll();
    
    // By category
    $product = get_product($product_id);
    if ($product && $product['category']) {
        $stmt = $pdo->prepare('SELECT p.* FROM promotions p JOIN promotion_categories pc ON p.id = pc.promotion_id WHERE pc.category = ? AND p.active = 1 AND p.start_date <= NOW() AND p.end_date >= NOW()');
        $stmt->execute([$product['category']]);
        $category_promos = $stmt->fetchAll();
        $promotions = array_merge($promotions, $category_promos);
    }
    
    return array_unique($promotions, SORT_REGULAR);
}

/**
 * Calculate discounted price
 */
function apply_promotions($product_id, $sell_price = null)
{
    if ($sell_price === null) {
        $product = get_product($product_id);
        $sell_price = $product['sell_price'] ?? 0;
    }
    
    $promotions = get_active_promotions_for_product($product_id);
    
    if (empty($promotions)) {
        return [
            'original_price' => $sell_price,
            'discounted_price' => $sell_price,
            'discount_amount' => 0,
            'discount_percent' => 0,
            'promotions' => []
        ];
    }
    
    $total_discount = 0;
    $applied_promos = [];
    
    foreach ($promotions as $promo) {
        if ($promo['discount_type'] === 'percentage') {
            $discount = ($sell_price * $promo['discount_value']) / 100;
        } else {
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
 * Get all promotions (paginated)
 */
function list_promotions($active_only = true, $limit = 50)
{
    $pdo = db_connect();
    
    if ($active_only) {
        $stmt = $pdo->prepare('SELECT * FROM promotions WHERE active = 1 ORDER BY start_date DESC LIMIT ?');
        $stmt->execute([$limit]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM promotions ORDER BY start_date DESC LIMIT ?');
        $stmt->execute([$limit]);
    }
    
    return $stmt->fetchAll();
}

/**
 * Get promotion details with products and categories
 */
function get_promotion($promotion_id)
{
    $pdo = db_connect();
    
    $stmt = $pdo->prepare('SELECT * FROM promotions WHERE id = ?');
    $stmt->execute([$promotion_id]);
    $promotion = $stmt->fetch();
    
    if (!$promotion) return null;
    
    // Get products
    $stmt = $pdo->prepare('SELECT p.* FROM products p JOIN promotion_products pp ON p.id = pp.product_id WHERE pp.promotion_id = ?');
    $stmt->execute([$promotion_id]);
    $promotion['products'] = $stmt->fetchAll();
    
    // Get categories
    $stmt = $pdo->prepare('SELECT category FROM promotion_categories WHERE promotion_id = ?');
    $stmt->execute([$promotion_id]);
    $promotion['categories'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    return $promotion;
}

/**
 * Get category pricing comparison
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
 * Find underpriced products (below category minimum margin)
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
 * Get pricing performance report
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
