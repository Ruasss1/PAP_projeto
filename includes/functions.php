<?php
/**
 * FUNÇÕES DE NEGÓCIO DO SISTEMA
 * Contém todas as funções para: vendas, quebras, stocks, cálculos financeiros
 * Ficheiro: includes/functions.php
 */

require_once __DIR__ . '/../config/database.php';

// Variável global de conexão à base de dados
global $pdo;

// Cria função de conexão se não existir
if (!function_exists('db_connect')) {
    function db_connect() {
        global $pdo;
        return $pdo;
    }
}

/**
 * Obtém os valores permitidos de um campo ENUM da base de dados
 * Exemplo: enum('Ativo','Inativo') retorna ['Ativo','Inativo']
 * 
 * @param string $table Nome da tabela
 * @param string $column Nome da coluna ENUM
 * @return array Lista de valores permitidos
 */
function enum_values($table, $column)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `" . $table . "` LIKE ?");
    $stmt->execute([$column]);
    $row = $stmt->fetch();
    
    if (!$row) return [];
    
    $type = $row['Type'];
    
    // Extrai valores do formato enum('A','B','C')
    if (preg_match("/^enum\((.*)\)$/", $type, $m)) {
        $vals = str_getcsv($m[1], ',', "'", "\\");
        return $vals;
    }
    
    return [];
}

// ============================================
// SECÇÃO: GESTÃO DE LOJAS (MULTI-STORE)
// ============================================

/**
 * Obtém todas as lojas ativas
 * @return array Lista de lojas
 */
function get_all_stores() {
    $pdo = db_connect();
    try {
        $stmt = $pdo->query("SELECT * FROM stores WHERE is_active = 1 ORDER BY is_default DESC, name ASC");
        $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $stores ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Obtém uma loja pelo ID
 * @param int $id ID da loja
 * @return array|false Dados da loja ou false
 */
function get_store($id) {
    $pdo = db_connect();
    $stmt = $pdo->prepare("SELECT * FROM stores WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Obtém a loja padrão
 * @return array|false Dados da loja padrão
 */
function get_default_store() {
    $pdo = db_connect();
    $stmt = $pdo->query("SELECT * FROM stores WHERE is_default = 1 LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Obtém a loja atual da sessão ou a padrão
 * @return int ID da loja atual
 */
function get_current_store_id() {
    // Garantir que a sessão está iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['current_store_id'])) {
        return (int)$_SESSION['current_store_id'];
    }
    $default = get_default_store();
    $store_id = $default ? $default['id'] : 1;
    
    // Guardar na sessão para próximas chamadas
    $_SESSION['current_store_id'] = $store_id;
    
    return $store_id;
}

/**
 * Define a loja atual na sessão
 * @param int $store_id ID da loja
 */
function set_current_store($store_id) {
    $_SESSION['current_store_id'] = (int)$store_id;
}

/**
 * Obtém dados da loja atual
 * @return array Dados da loja
 */
function get_current_store() {
    return get_store(get_current_store_id());
}

// ============================================
// SECÇÃO: PRODUTOS
// ============================================

/**
 * Obtém um produto pelo ID
 * Inclui nome do fornecedor associado
 * 
 * @param int $id ID do produto
 * @return array|false Dados do produto ou false se não encontrado
 */
function get_product($id)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT p.*, s.name as supplier_name FROM products p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Lista todos os produtos
 * 
 * @param bool $active_only Se true, mostra apenas produtos ativos
 * @param bool $filter_by_store Se true, filtra pela loja atual
 * @return array Lista de produtos
 */
function list_products($active_only = true, $filter_by_store = false)
{
    $pdo = db_connect();
    
    try {
        $sql = 'SELECT p.*, s.name as supplier_name FROM products p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE 1=1';
        $params = [];
        
        if ($active_only) {
            $sql .= ' AND p.active = 1';
        }
        
        if ($filter_by_store) {
            $store_id = get_current_store_id();
            $sql .= ' AND (p.store_id = :store_id OR p.store_id IS NULL)';
            $params['store_id'] = $store_id;
        }
        
        $sql .= ' ORDER BY p.name';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        // Fallback para esquema antigo sem coluna active/supplier_id
        return $pdo->query('SELECT p.*, "" as supplier_name FROM products p ORDER BY p.name')->fetchAll();
    }
}

/**
 * Filtra e ordena produtos por categoria e critério
 * 
 * @param string|null $category Categoria para filtrar (null = todas)
 * @param string $sort_by Critério de ordenação (name_az, price_low, etc.)
 * @param bool $active_only Apenas produtos ativos
 * @return array Lista de produtos filtrados
 */
function filter_products($category = null, $sort_by = 'name', $active_only = true)
{
    $pdo = db_connect();
    $sql = 'SELECT p.*, s.name as supplier_name FROM products p LEFT JOIN suppliers s ON p.supplier_id = s.id';
    
    $conditions = [];
    $params = [];
    
    // Adiciona condição de produtos ativos
    if ($active_only) {
        $conditions[] = 'p.active = 1';
    }
    
    // Adiciona filtro de categoria
    if ($category && $category !== 'all') {
        $conditions[] = 'p.category = ?';
        $params[] = $category;
    }
    
    // Aplica condições WHERE
    if (!empty($conditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    
    // Define ordenação baseada no parâmetro
    $order_by = 'p.name ASC';
    switch ($sort_by) {
        case 'name_az':
            $order_by = 'p.name ASC';
            break;
        case 'name_za':
            $order_by = 'p.name DESC';
            break;
        case 'price_low':
            $order_by = 'p.sell_price ASC';
            break;
        case 'price_high':
            $order_by = 'p.sell_price DESC';
            break;
        case 'stock_low':
            $order_by = 'p.stock ASC';
            break;
        case 'stock_high':
            $order_by = 'p.stock DESC';
            break;
        default:
            $order_by = 'p.name ASC';
    }
    
    $sql .= ' ORDER BY ' . $order_by;
    
    // Executa query com ou sem parâmetros
    if ($params) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } else {
        return $pdo->query($sql)->fetchAll();
    }
}

/**
 * Obtém todas as categorias únicas de produtos
 * 
 * @return array Lista de categorias
 */
function get_all_categories()
{
    $pdo = db_connect();
    
    try {
        $stmt = $pdo->query('SELECT DISTINCT category FROM products WHERE active = 1 AND category IS NOT NULL AND category != "" ORDER BY category ASC');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Obtém todas as marcas únicas de produtos
 * 
 * @return array Lista de marcas
 */
function get_all_brands()
{
    $pdo = db_connect();
    
    try {
        $stmt = $pdo->query('SELECT DISTINCT brand FROM products WHERE active = 1 AND brand IS NOT NULL AND brand != "" ORDER BY brand ASC');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Lista produtos com stock baixo (abaixo do mínimo)
 * 
 * @param int|null $threshold Limite personalizado (null = usa min_stock do produto)
 * @return array Lista de produtos com stock baixo
 */
function list_low_stock_products($threshold = null)
{
    $pdo = db_connect();
    
    try {
        if ($threshold === null) {
            // Usa o min_stock definido em cada produto
            $stmt = $pdo->query('SELECT p.*, s.name as supplier_name FROM products p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.active = 1 AND p.stock <= p.min_stock ORDER BY p.stock ASC');
        } else {
            // Usa limite personalizado
            $stmt = $pdo->prepare('SELECT p.*, s.name as supplier_name FROM products p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.active = 1 AND p.stock <= ? ORDER BY p.stock ASC');
            $stmt->execute([$threshold]);
        }
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        // Fallback para esquema antigo
        if ($threshold === null) {
            $threshold = 5;
        }
        $stmt = $pdo->prepare('SELECT p.*, "" as supplier_name FROM products p WHERE p.stock <= ? ORDER BY p.stock ASC');
        $stmt->execute([$threshold]);
        return $stmt->fetchAll();
    }
}

/**
 * Alias para list_low_stock_products
 * Retorna produtos com stock baixo
 */
function low_stock_alerts($threshold = null)
{
    return list_low_stock_products($threshold);
}

/**
 * Cria encomendas automáticas para produtos abaixo do stock mínimo.
 * Agrupa por fornecedor e evita duplicar produtos que já estejam em encomendas abertas.
 *
 * @param array $product_ids IDs de produtos a verificar
 * @return array Resumo da operação
 */
function create_auto_orders_for_low_stock_products(array $product_ids): array
{
    $pdo = db_connect();
    $owns_tx = false;
    $product_ids = array_values(array_unique(array_filter(array_map('intval', $product_ids), fn($v) => $v > 0)));

    if (empty($product_ids)) {
        return ['orders_created' => 0, 'items_created' => 0, 'products' => []];
    }

    try {
        $in = implode(',', array_fill(0, count($product_ids), '?'));
        $stmt = $pdo->prepare("SELECT id, name, stock, min_stock, reorder_qty, supplier_id, cost_price
                              FROM products
                              WHERE id IN ($in)
                                AND active = 1
                                AND supplier_id IS NOT NULL
                                AND stock <= min_stock");
        $stmt->execute($product_ids);
        $low_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($low_products)) {
            return ['orders_created' => 0, 'items_created' => 0, 'products' => []];
        }

        $low_ids = array_map(fn($p) => (int)$p['id'], $low_products);
        $in_low = implode(',', array_fill(0, count($low_ids), '?'));
        $open_q = $pdo->prepare("SELECT DISTINCT oi.product_id
                                FROM order_items oi
                                JOIN orders o ON o.id = oi.order_id
                                WHERE oi.product_id IN ($in_low)
                                  AND o.status = 'pending'");
        $open_q->execute($low_ids);
        $already_open = array_fill_keys(array_map('intval', $open_q->fetchAll(PDO::FETCH_COLUMN)), true);

        $by_supplier = [];
        foreach ($low_products as $p) {
            $pid = (int)$p['id'];
            if (isset($already_open[$pid])) {
                continue;
            }

            $supplier_id = (int)$p['supplier_id'];
            if ($supplier_id <= 0) {
                continue;
            }

            $custom_qty = intval($p['reorder_qty'] ?? 0);
            $qty_to_order = $custom_qty > 0 ? $custom_qty : max(((int)$p['min_stock'] * 2) - (int)$p['stock'], 1);
            $by_supplier[$supplier_id][] = [
                'product_id' => $pid,
                'name' => $p['name'],
                'qty' => $qty_to_order,
                'cost_price' => (float)($p['cost_price'] ?? 0)
            ];
        }

        if (empty($by_supplier)) {
            return ['orders_created' => 0, 'items_created' => 0, 'products' => []];
        }

        $owns_tx = !$pdo->inTransaction();
        if ($owns_tx) {
            $pdo->beginTransaction();
        }

        $insert_order = $pdo->prepare("INSERT INTO orders (supplier_id, total_cost, status, created_at) VALUES (?, ?, 'pending', NOW())");
        $insert_item  = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, cost_price) VALUES (?, ?, ?, ?)");

        $orders_created = 0;
        $items_created = 0;
        $products_created = [];

        foreach ($by_supplier as $supplier_id => $items) {
            $total_cost = array_sum(array_map(fn($it) => $it['qty'] * $it['cost_price'], $items));
            $insert_order->execute([(int)$supplier_id, $total_cost]);
            $order_id = (int)$pdo->lastInsertId();
            $orders_created++;

            foreach ($items as $it) {
                $insert_item->execute([$order_id, $it['product_id'], $it['qty'], $it['cost_price']]);
                $items_created++;
                $products_created[] = $it['name'];
            }
        }

        try {
            if ($orders_created > 0) {
                $title = 'Encomenda automática criada';
                $msg = "Foram criadas $orders_created encomenda(s) automática(s) para $items_created produto(s) com stock baixo.";
                $pdo->prepare("INSERT INTO notifications (type, title, message, link, icon, priority) VALUES ('Stock', ?, ?, '/modules/encomendas.php', '📦', 'Alta')")
                    ->execute([$title, $msg]);
            }
        } catch (\Throwable $__) {
            // tabela notifications pode não existir em todas as instalações
        }

        if ($owns_tx) {
            $pdo->commit();
        }

        return [
            'orders_created' => $orders_created,
            'items_created' => $items_created,
            'products' => array_values(array_unique($products_created))
        ];
    } catch (\Throwable $e) {
        if ($owns_tx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [
            'orders_created' => 0,
            'items_created' => 0,
            'products' => [],
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Adiciona um novo produto à base de dados
 * 
 * @param array $data Dados do produto (name, category, cost_price, sell_price, etc.)
 * @return int ID do novo produto criado
 */
function add_product($data)
{
    $pdo = db_connect();

    // Normaliza validade: string vazia/invalidada vira NULL
    $expiry_date = $data['expiry_date'] ?? null;
    if ($expiry_date === '' || $expiry_date === false) {
        $expiry_date = null;
    } elseif ($expiry_date !== null) {
        $dt = DateTime::createFromFormat('Y-m-d', (string)$expiry_date);
        if (!$dt || $dt->format('Y-m-d') !== (string)$expiry_date) {
            $expiry_date = null;
        }
    }
    
    // Verifica quais colunas existem na tabela products
    $columns = [];
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM products");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $columns = array_flip($existingColumns);
    } catch (PDOException $e) {
        // Fallback para colunas básicas
        $columns = [
            'id' => 1, 'name' => 1, 'category' => 1, 'cost_price' => 1, 
            'sell_price' => 1, 'stock' => 1, 'expiry_date' => 1, 'created_at' => 1
        ];
    }
    
    // Constrói INSERT dinâmico baseado nas colunas existentes
    $fields = ['name'];
    $placeholders = ['?'];
    $values = [$data['name']];
    
    // Campos opcionais com valores padrão
    $optionalFields = [
        'category' => $data['category'] ?? null,
        'brand' => $data['brand'] ?? null,
        'barcode' => $data['barcode'] ?? null,
        'cost_price' => $data['cost_price'] ?? 0,
        'sell_price' => $data['sell_price'] ?? 0,
        'vat' => $data['vat'] ?? 23.00,
        'stock' => $data['stock'] ?? 0,
        'min_stock' => $data['min_stock'] ?? 5,
        'reorder_qty' => isset($data['reorder_qty']) && intval($data['reorder_qty']) > 0 ? intval($data['reorder_qty']) : null,
        'supplier_id' => $data['supplier_id'] ?? null,
        'expiry_date' => $expiry_date,
        'active' => 1,
        'created_at' => 'NOW()'
    ];
    
    // Adiciona apenas campos que existem na tabela
    foreach ($optionalFields as $field => $value) {
        if (isset($columns[$field])) {
            $fields[] = $field;
            $placeholders[] = $value === 'NOW()' ? 'NOW()' : '?';
            if ($value !== 'NOW()') {
                $values[] = $value;
            }
        }
    }
    
    $sql = 'INSERT INTO products (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    
    return $pdo->lastInsertId();
}

/**
 * Atualiza dados de um produto existente
 * Regista alterações de preço no histórico
 * 
 * @param int $id ID do produto
 * @param array $data Novos dados do produto
 * @return bool Sucesso ou falha
 */
function update_product($id, $data, &$meta = null)
{
    $pdo = db_connect();
    
    // Obtém produto atual para comparação
    $current = get_product($id);
    if (!$current) return false;
    
    // Regista alterações de preço no histórico
    if (isset($data['cost_price']) && $data['cost_price'] != $current['cost_price']) {
        record_price_change($id, 'cost', $current['cost_price'], $data['cost_price'], $data['reason'] ?? 'Atualização de preço');
    }
    if (isset($data['sell_price']) && $data['sell_price'] != $current['sell_price']) {
        record_price_change($id, 'sell', $current['sell_price'], $data['sell_price'], $data['reason'] ?? 'Atualização de preço');
    }
    
    // Verifica colunas existentes
    $columns = [];
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM products");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $columns = array_flip($existingColumns);
    } catch (PDOException $e) {
        $columns = [
            'id' => 1, 'name' => 1, 'category' => 1, 'cost_price' => 1, 
            'sell_price' => 1, 'stock' => 1, 'expiry_date' => 1, 'created_at' => 1
        ];
    }
    
    // Campos que podem ser atualizados
    $updates = [];
    $values = [];

    // Normaliza validade: string vazia/invalidada vira NULL
    $expiry_date = $data['expiry_date'] ?? ($current['expiry_date'] ?? null);
    if ($expiry_date === '' || $expiry_date === false) {
        $expiry_date = null;
    } elseif ($expiry_date !== null) {
        $dt = DateTime::createFromFormat('Y-m-d', (string)$expiry_date);
        if (!$dt || $dt->format('Y-m-d') !== (string)$expiry_date) {
            $expiry_date = null;
        }
    }
    
    $updatableFields = [
        'name' => $data['name'] ?? $current['name'],
        'category' => $data['category'] ?? $current['category'],
        'brand' => $data['brand'] ?? $current['brand'] ?? null,
        'barcode' => $data['barcode'] ?? $current['barcode'] ?? null,
        'cost_price' => $data['cost_price'] ?? $current['cost_price'],
        'sell_price' => $data['sell_price'] ?? $current['sell_price'],
        'vat' => $data['vat'] ?? $current['vat'] ?? 23.00,
        'stock' => $data['stock'] ?? $current['stock'],
        'min_stock' => $data['min_stock'] ?? $current['min_stock'] ?? 5,
        'reorder_qty' => isset($data['reorder_qty']) && intval($data['reorder_qty']) > 0 ? intval($data['reorder_qty']) : ($current['reorder_qty'] ?? null),
        'supplier_id' => $data['supplier_id'] ?? $current['supplier_id'] ?? null,
        'expiry_date' => $expiry_date,
        'active' => $data['active'] ?? $current['active'] ?? 1,
    ];
    
    // Constrói UPDATE dinâmico
    foreach ($updatableFields as $field => $value) {
        if (isset($columns[$field])) {
            $updates[] = "$field = ?";
            $values[] = $value;
        }
    }
    
    if (empty($updates)) {
        return false;
    }
    
    $values[] = $id;
    $sql = 'UPDATE products SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute($values);

    $meta = ['auto_orders' => ['orders_created' => 0, 'items_created' => 0, 'products' => []]];
    if ($ok) {
        // Always check after save — stock may now be below min_stock
        $meta['auto_orders'] = create_auto_orders_for_low_stock_products([$id]);
    }

    return $ok;
}

/**
 * Elimina um produto (soft delete - apenas desativa)
 * 
 * @param int $id ID do produto
 * @return bool Sucesso ou falha
 */
function delete_product($id)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('UPDATE products SET active = 0 WHERE id = ?');
    return $stmt->execute([$id]);
}

/**
 * Obtém histórico de alterações de preço de um produto
 * 
 * @param int $product_id ID do produto
 * @return array Lista de alterações de preço
 */
function get_product_price_history($product_id)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT * FROM price_history WHERE product_id = ? ORDER BY created_at DESC');
    $stmt->execute([$product_id]);
    return $stmt->fetchAll();
}

/**
 * Regista uma alteração de preço no histórico
 * 
 * @param int $product_id ID do produto
 * @param string $price_type Tipo: 'cost' ou 'sell'
 * @param float $old_price Preço anterior
 * @param float $new_price Novo preço
 * @param string|null $reason Motivo da alteração
 * @param int|null $changed_by ID do utilizador que fez a alteração
 * @return bool Sucesso ou falha
 */
function record_price_change($product_id, $price_type, $old_price, $new_price, $reason = null, $changed_by = null)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('INSERT INTO price_history (product_id, price_type, old_price, new_price, reason, changed_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    return $stmt->execute([$product_id, $price_type, $old_price, $new_price, $reason, $changed_by]);
}

// ============================================
// SECÇÃO: FORNECEDORES
// ============================================

/**
 * Lista todos os fornecedores
 * 
 * @param bool $active_only Apenas fornecedores ativos
 * @return array Lista de fornecedores
 */
function list_suppliers($active_only = true)
{
    $pdo = db_connect();
    $sql = 'SELECT * FROM suppliers';
    
    if ($active_only) {
        $sql .= ' WHERE active = 1';
    }
    
    $sql .= ' ORDER BY name';
    return $pdo->query($sql)->fetchAll();
}

/**
 * Obtém um fornecedor pelo ID
 * 
 * @param int $id ID do fornecedor
 * @return array|false Dados do fornecedor
 */
function get_supplier($id)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT * FROM suppliers WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Adiciona um novo fornecedor
 * 
 * @param array $data Dados do fornecedor
 * @return int ID do novo fornecedor
 */
function add_supplier($data)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('INSERT INTO suppliers (name, email, phone, address, delivery_days, contact, active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())');
    $stmt->execute([
        $data['name'],
        $data['email'] ?? null,
        $data['phone'] ?? null,
        $data['address'] ?? null,
        $data['delivery_days'] ?? 2,
        $data['contact'] ?? null,
    ]);
    return $pdo->lastInsertId();
}

/**
 * Atualiza dados de um fornecedor
 * 
 * @param int $id ID do fornecedor
 * @param array $data Novos dados
 * @return bool Sucesso ou falha
 */
function update_supplier($id, $data)
{
    $pdo = db_connect();
    $current = get_supplier($id);
    if (!$current) return false;
    
    $stmt = $pdo->prepare('UPDATE suppliers SET name = ?, email = ?, phone = ?, address = ?, delivery_days = ?, contact = ?, active = ? WHERE id = ?');
    return $stmt->execute([
        $data['name'] ?? $current['name'],
        $data['email'] ?? $current['email'],
        $data['phone'] ?? $current['phone'],
        $data['address'] ?? $current['address'],
        $data['delivery_days'] ?? $current['delivery_days'],
        $data['contact'] ?? $current['contact'],
        $data['active'] ?? $current['active'],
        $id
    ]);
}

/**
 * Elimina um fornecedor (soft delete)
 * 
 * @param int $id ID do fornecedor
 * @return bool Sucesso ou falha
 */
function delete_supplier($id)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('UPDATE suppliers SET active = 0 WHERE id = ?');
    return $stmt->execute([$id]);
}

// ============================================
// SECÇÃO: VENDAS
// ============================================

/**
 * Gera um NIF fictício mas válido em Portugal
 * Formato: 9 dígitos, começa com 1-9
 * 
 * @return string NIF gerado
 */
function generate_nif() {
    $nif = '';
    $nif .= rand(1, 9); // Primeiro dígito não pode ser 0
    
    for ($i = 1; $i < 9; $i++) {
        $nif .= rand(0, 9);
    }
    
    return $nif;
}

/**
 * Regista uma nova venda no sistema
 * Atualiza stock, cria transação e movimentos de stock
 * 
 * @param array $items Lista de itens [{product_id, quantity}, ...]
 * @param string $payment_method Método de pagamento (Dinheiro, Cartão, etc.)
 * @param bool $with_nif Se deve gerar NIF para a venda
 * @return int|string ID da venda ou mensagem de erro
 */
function add_sale($items, $payment_method = 'Dinheiro', $with_nif = false)
{
    $pdo = db_connect();
    $pdo->beginTransaction();
    
    try {
        $total = 0;
        $cost_total = 0;
        $nif = $with_nif ? generate_nif() : null;
        $store_id = get_current_store_id();
        
        // Cria registo da venda (com store_id)
        $stmt = $pdo->prepare('INSERT INTO sales (total, payment_method, nif, sale_date, store_id) VALUES (?, ?, ?, NOW(), ?)');
        $stmt->execute([0, $payment_method, $nif, $store_id]);
        $sale_id = $pdo->lastInsertId();
        
        // Processa cada item da venda
        foreach ($items as $item) {
            $product = get_product($item['product_id']);
            
            // Verifica se há stock suficiente
            if (!$product || $product['stock'] < $item['quantity']) {
                throw new Exception('Stock insuficiente para: ' . $product['name']);
            }
            
            // Calcula valores
            $unit_price = $product['sell_price'];
            $line_total = $unit_price * $item['quantity'];
            $line_cost = $product['cost_price'] * $item['quantity'];
            
            $total += $line_total;
            $cost_total += $line_cost;
            
            // Insere item da venda com preço de custo
            $stmt = $pdo->prepare('INSERT INTO sale_items (sale_id, category, product_id, quantity, price, cost_price) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$sale_id, $product['category'], $item['product_id'], $item['quantity'], $unit_price, $product['cost_price']]);
            
            // Atualiza stock do produto
            $stmt = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
            $stmt->execute([$item['quantity'], $item['product_id']]);
            
            // Regista movimento de stock
            $stmt = $pdo->prepare('INSERT INTO stock_movements (product_id, type, qty, previous_stock, new_stock, reference_type, reference_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([
                $item['product_id'], 
                'sale', 
                -$item['quantity'], 
                $product['stock'], 
                $product['stock'] - $item['quantity'],
                'sale', 
                $sale_id
            ]);
        }
        
        // Atualiza total da venda
        $stmt = $pdo->prepare('UPDATE sales SET total = ? WHERE id = ?');
        $stmt->execute([$total, $sale_id]);
        
        // Regista transação financeira
        $stmt = $pdo->prepare('INSERT INTO transactions (type, amount, reference_type, reference_id, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute(['sale', $total, 'sale', $sale_id, "Venda #$sale_id com " . count($items) . " itens"]);
        
        $pdo->commit();
        
        // Verifica stock baixo e envia email se necessário
        check_and_send_low_stock_email();
        
        return $sale_id;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return $e->getMessage();
    }
}

/**
 * Obtém detalhes de uma venda
 * Inclui todos os itens vendidos
 * 
 * @param int $id ID da venda
 * @return array|false Dados da venda com itens
 */
function get_sale($id)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT * FROM sales WHERE id = ?');
    $stmt->execute([$id]);
    $sale = $stmt->fetch();
    
    if ($sale) {
        // Obtém itens da venda
        $stmt = $pdo->prepare('SELECT si.*, p.name as product_name FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?');
        $stmt->execute([$id]);
        $sale['items'] = $stmt->fetchAll();
    }
    
    return $sale;
}

/**
 * Lista vendas recentes
 * 
 * @param int $limit Número máximo de vendas a retornar
 * @return array Lista de vendas
 */
function list_recent_sales($limit = 20)
{
    $pdo = db_connect();
    $limit = (int)$limit;
    return $pdo->query("SELECT * FROM sales ORDER BY sale_date DESC LIMIT $limit")->fetchAll();
}

// ============================================
// SECÇÃO: ENCOMENDAS E MENSAGENS
// ============================================

/**
 * Cria uma nova encomenda a fornecedor
 * 
 * @param int $supplier_id ID do fornecedor
 * @param array $items Lista de itens [{product_id, qty}, ...]
 * @return int|string ID da encomenda ou erro
 */
function create_order($supplier_id, $items)
{
    $pdo = db_connect();
    $pdo->beginTransaction();
    
    try {
        $total_cost = 0;
        $order_ids = [];
        
        // Cria registo para cada item
        foreach ($items as $item) {
            $product = get_product($item['product_id']);
            $item_cost = $product['cost_price'] * $item['qty'];
            $total_cost += $item_cost;
            
            $stmt = $pdo->prepare('INSERT INTO orders (supplier_id, product_id, qty, cost_price, status, total_cost, created_at) VALUES (?, ?, ?, ?, "pending", ?, NOW())');
            $stmt->execute([$supplier_id, $item['product_id'], $item['qty'], $product['cost_price'], $item_cost]);
            $order_ids[] = $pdo->lastInsertId();
        }
        
        $order_id = $order_ids[0];
        
        // Adiciona mensagem de criação
        add_order_message($order_id, 'created', "Encomenda #$order_id foi criada com " . count($items) . " itens.");
        
        $pdo->commit();
        return $order_id;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return $e->getMessage();
    }
}

/**
 * Obtém detalhes de uma encomenda
 * Inclui itens e mensagens
 * 
 * @param int $id ID da encomenda
 * @return array|false Dados da encomenda
 */
function get_order($id)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT o.*, s.name as supplier_name FROM orders o JOIN suppliers s ON o.supplier_id = s.id WHERE o.id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    
    if ($order) {
        // Obtém itens da encomenda
        $stmt = $pdo->prepare('SELECT o.*, p.name as product_name FROM orders o JOIN products p ON o.product_id = p.id WHERE o.id = ?');
        $stmt->execute([$id]);
        $order['items'] = $stmt->fetchAll();
        
        // Obtém mensagens da encomenda
        $stmt = $pdo->prepare('SELECT * FROM order_messages WHERE order_id = ? ORDER BY created_at ASC');
        $stmt->execute([$id]);
        $order['messages'] = $stmt->fetchAll();
    }
    
    return $order;
}

/**
 * Lista todas as encomendas
 * 
 * @param string|null $status Filtrar por estado (pending, processed, shipped, delivered)
 * @return array Lista de encomendas
 */
function list_orders($status = null)
{
    $pdo = db_connect();

    // Determina coluna para ordenação
    $orderColumn = 'id';
    try {
        $colsStmt = $pdo->query("SHOW COLUMNS FROM orders");
        $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('created_at', $cols, true)) {
            $orderColumn = 'created_at';
        }
    } catch (PDOException $e) {
        $orderColumn = 'id';
    }

    // Segurança: apenas colunas permitidas
    $allowedOrderColumns = ['created_at', 'id'];
    if (!in_array($orderColumn, $allowedOrderColumns, true)) {
        $orderColumn = 'id';
    }

    // Consulta com ou sem filtro de estado
    if ($status) {
        $sql = "SELECT o.*, s.name as supplier_name, p.barcode as product_sku, p.name as product_name 
                FROM orders o 
                JOIN suppliers s ON o.supplier_id = s.id 
                LEFT JOIN products p ON o.product_id = p.id 
                WHERE o.status = ? 
                ORDER BY o.$orderColumn DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status]);
    } else {
        $sql = "SELECT o.*, s.name as supplier_name, p.barcode as product_sku, p.name as product_name 
                FROM orders o 
                JOIN suppliers s ON o.supplier_id = s.id 
                LEFT JOIN products p ON o.product_id = p.id 
                ORDER BY o.$orderColumn DESC";
        $stmt = $pdo->query($sql);
    }

    return $stmt->fetchAll();
}

/**
 * Atualiza o estado de uma encomenda
 * 
 * @param int $order_id ID da encomenda
 * @param string $new_status Novo estado
 * @return bool Sucesso ou falha
 */
function update_order_status($order_id, $new_status)
{
    $pdo = db_connect();
    
    // Valida estado
    $valid_statuses = ['pending', 'processed', 'shipped', 'delivered'];
    if (!in_array($new_status, $valid_statuses)) {
        return false;
    }
    
    // Constrói query de atualização
    $sql = 'UPDATE orders SET status = ?';
    $params = [$new_status];
    
    // Adiciona timestamp conforme o estado
    if ($new_status === 'processed') {
        $sql .= ', processed_at = NOW()';
    }
    if ($new_status === 'delivered') {
        $sql .= ', delivered_at = NOW()';
    }
    
    $sql .= ' WHERE id = ?';
    $params[] = $order_id;
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    // Adiciona mensagem de estado
    if ($result) {
        $messages = [
            'processed' => 'A encomenda foi processada e está pronta para envio.',
            'shipped' => 'A encomenda foi enviada e está a caminho.',
            'delivered' => 'A encomenda foi entregue com sucesso!'
        ];
        add_order_message($order_id, $new_status, $messages[$new_status] ?? "Estado alterado para: $new_status");
    }
    
    return $result;
}

/**
 * Regista receção de itens de uma encomenda
 * Atualiza stock e cria movimentos
 * 
 * @param int $order_id ID da encomenda
 * @param array $items_received Itens recebidos [{product_id, qty}, ...]
 * @return bool|string Sucesso ou erro
 */
function receive_order_items($order_id, $items_received)
{
    $pdo = db_connect();
    $pdo->beginTransaction();
    
    try {
        $order = get_order($order_id);
        if (!$order) throw new Exception('Encomenda não encontrada');
        
        foreach ($items_received as $item) {
            $product = get_product($item['product_id']);
            $old_stock = $product['stock'];
            $new_qty = $item['qty'];
            
            // Atualiza stock
            $stmt = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
            $stmt->execute([$new_qty, $item['product_id']]);
            
            // Regista movimento de stock
            $stmt = $pdo->prepare('INSERT INTO stock_movements (product_id, type, qty, previous_stock, new_stock, reference_type, reference_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $item['product_id'], 
                'order', 
                $new_qty,
                $old_stock, 
                $old_stock + $new_qty,
                'order', 
                $order_id, 
                date('Y-m-d H:i:s')
            ]);
            
            // Regista transação (custo)
            $cost = $product['cost_price'] * $new_qty;
            $stmt = $pdo->prepare('INSERT INTO transactions (type, amount, reference_type, reference_id, description, created_at) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute(['order', -$cost, 'order', $order_id, "Recebimento encomenda #$order_id - {$product['name']} x $new_qty", date('Y-m-d H:i:s')]);
            
            // Marca como recebido
            $stmt = $pdo->prepare('UPDATE orders SET received = 1 WHERE id = ?');
            $stmt->execute([$order_id]);
        }
        
        // Adiciona mensagem de entrega
        add_order_message($order_id, 'delivered', "Encomenda #$order_id foi entregue. Itens recebidos: " . count($items_received));
        
        // Atualiza estado para entregue
        update_order_status($order_id, 'delivered');
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return $e->getMessage();
    }
}

/**
 * Adiciona uma mensagem a uma encomenda
 * 
 * @param int $order_id ID da encomenda
 * @param string $type Tipo da mensagem
 * @param string $message Conteúdo da mensagem
 * @return bool Sucesso ou falha
 */
function add_order_message($order_id, $type, $message)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('INSERT INTO order_messages (order_id, type, message, created_at) VALUES (?, ?, ?, ?)');
    return $stmt->execute([$order_id, $type, $message, date('Y-m-d H:i:s')]);
}

/**
 * Obtém mensagens de uma encomenda
 * 
 * @param int $order_id ID da encomenda
 * @return array Lista de mensagens
 */
function get_order_messages($order_id)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT * FROM order_messages WHERE order_id = ? ORDER BY created_at ASC');
    $stmt->execute([$order_id]);
    return $stmt->fetchAll();
}

// ============================================
// SECÇÃO: QUEBRAS / PERDAS
// ============================================

/**
 * Regista uma quebra/perda de produto
 * Atualiza stock e cria transação de custo
 * 
 * @param int $product_id ID do produto
 * @param int $qty Quantidade perdida
 * @param string $reason Motivo da quebra
 * @return int|string ID da quebra ou erro
 */
function record_break($product_id, $qty, $reason = 'Quebra/Perda')
{
    $pdo = db_connect();
    $pdo->beginTransaction();
    
    try {
        $product = get_product($product_id);
        
        // Verifica stock disponível
        if (!$product || $product['stock'] < $qty) {
            throw new Exception('Stock insuficiente para registar a quebra');
        }

        $cost = $product['cost_price'] * $qty;
        $old_stock = $product['stock'];
        
        // Insere registo de quebra
        $stmt = $pdo->prepare('INSERT INTO breaks (product_id, qty, cost, reason, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$product_id, $qty, $cost, $reason]);
        $break_id = $pdo->lastInsertId();
        
        // Atualiza stock
        $stmt = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
        $stmt->execute([$qty, $product_id]);
        
        // Regista movimento de stock
        $stmt = $pdo->prepare('INSERT INTO stock_movements (product_id, type, qty, previous_stock, new_stock, reference_type, reference_id, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([
            $product_id, 
            'break', 
            -$qty,
            $old_stock, 
            $old_stock - $qty,
            'break', 
            $break_id, 
            $reason
        ]);
        
        // Regista transação (perda)
        $stmt = $pdo->prepare('INSERT INTO transactions (type, amount, reference_type, reference_id, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute(['break', -$cost, 'break', $break_id, "Quebra: {$product['name']} x $qty"]);
        
        // Garante que stock final está correto
        $stmt = $pdo->prepare('UPDATE products SET stock = ? WHERE id = ?');
        $stmt->execute([$old_stock - $qty, $product_id]);
        
        $pdo->commit();
        return $break_id;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return $e->getMessage();
    }
}

/**
 * Lista quebras registadas
 * 
 * @param int $limit Número máximo de registos
 * @return array Lista de quebras
 */
function list_breaks($limit = 50)
{
    $pdo = db_connect();
    $limit = (int)$limit;
    return $pdo->query("SELECT b.*, p.name as product_name FROM breaks b JOIN products p ON b.product_id = p.id ORDER BY b.created_at DESC LIMIT $limit")->fetchAll();
}

// ============================================
// SECÇÃO: MOVIMENTOS DE STOCK
// ============================================

/**
 * Obtém movimentos de stock
 * 
 * @param int|null $product_id Filtrar por produto (null = todos)
 * @param int $limit Número máximo de registos
 * @return array Lista de movimentos
 */
function get_stock_movements($product_id = null, $limit = 100)
{
    $pdo = db_connect();
    $limit = (int)$limit;
    
    if ($product_id) {
        $stmt = $pdo->prepare('SELECT sm.*, p.name as product_name FROM stock_movements sm JOIN products p ON sm.product_id = p.id WHERE sm.product_id = ? ORDER BY sm.created_at DESC LIMIT ' . $limit);
        $stmt->execute([$product_id]);
    } else {
        $stmt = $pdo->query("SELECT sm.*, p.name as product_name FROM stock_movements sm JOIN products p ON sm.product_id = p.id ORDER BY sm.created_at DESC LIMIT $limit");
    }
    
    return $stmt->fetchAll();
}

/**
 * Ajusta stock manualmente
 * 
 * @param int $product_id ID do produto
 * @param int $qty Quantidade a adicionar (negativo para remover)
 * @param string $reason Motivo do ajuste
 * @return bool Sucesso ou falha
 */
function adjust_stock($product_id, $qty, $reason = 'Ajuste manual')
{
    $pdo = db_connect();
    $product = get_product($product_id);
    if (!$product) return false;
    
    $old_stock = $product['stock'];
    $new_stock = $old_stock + $qty;
    
    // Atualiza stock
    $stmt = $pdo->prepare('UPDATE products SET stock = ? WHERE id = ?');
    $stmt->execute([$new_stock, $product_id]);
    
    // Regista movimento
    $stmt = $pdo->prepare('INSERT INTO stock_movements (product_id, type, qty, previous_stock, new_stock, reference_type, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$product_id, 'adjustment', $qty, $old_stock, $new_stock, 'manual', $reason]);
    
    return true;
}

// ============================================
// SECÇÃO: ALERTAS
// ============================================

/**
 * Obtém alertas do sistema
 * 
 * @param bool $unread_only Apenas alertas não lidos
 * @param int $limit Número máximo de alertas
 * @return array Lista de alertas
 */
function get_alerts($unread_only = false, $limit = 50)
{
    $pdo = db_connect();
    $limit = (int)$limit;
    
    if ($unread_only) {
        $stmt = $pdo->query("SELECT * FROM alerts WHERE `read` = 0 ORDER BY created_at DESC LIMIT $limit");
    } else {
        $stmt = $pdo->query("SELECT * FROM alerts ORDER BY created_at DESC LIMIT $limit");
    }
    
    return $stmt->fetchAll();
}

/**
 * Conta alertas não lidos
 * 
 * @return int Número de alertas não lidos
 */
function get_unread_alerts_count()
{
    $pdo = db_connect();
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM alerts WHERE `read` = 0");
        return $stmt->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        // Tabela ainda não existe
        return 0;
    }
}

/**
 * Marca um alerta como lido
 * 
 * @param int $alert_id ID do alerta
 * @return bool Sucesso ou falha
 */
function mark_alert_read($alert_id)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('UPDATE alerts SET `read` = 1 WHERE id = ?');
    return $stmt->execute([$alert_id]);
}

/**
 * Marca todos os alertas como lidos
 * 
 * @return bool Sucesso ou falha
 */
function mark_all_alerts_read()
{
    $pdo = db_connect();
    return $pdo->query('UPDATE alerts SET `read` = 1');
}

/**
 * Cria um novo alerta
 * 
 * @param string $type Tipo de alerta
 * @param string $severity Gravidade (info, warning, critical)
 * @param string $title Título do alerta
 * @param string $message Mensagem do alerta
 * @param string|null $reference_type Tipo de referência
 * @param int|null $reference_id ID da referência
 * @return bool Sucesso ou falha
 */
function create_alert($type, $severity, $title, $message, $reference_type = null, $reference_id = null)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('INSERT INTO alerts (alert_type, severity, title, message, reference_type, reference_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    return $stmt->execute([$type, $severity, $title, $message, $reference_type, $reference_id]);
}

// ============================================
// SECÇÃO: CONFIGURAÇÕES
// ============================================

/**
 * Obtém valor de uma configuração
 * 
 * @param string $key Chave da configuração
 * @param mixed $default Valor padrão se não existir
 * @return mixed Valor da configuração
 */
function get_setting($key, $default = null)
{
    $pdo = db_connect();
    
    try {
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE `key` = ?');
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * Define valor de uma configuração
 * 
 * @param string $key Chave da configuração
 * @param mixed $value Novo valor
 * @return bool Sucesso ou falha
 */
function set_setting($key, $value)
{
    $pdo = db_connect();
    
    try {
        $stmt = $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?, `updated_at` = NOW()');
        return $stmt->execute([$key, $value, $value]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Obtém todas as configurações
 * 
 * @return array Array associativo chave => valor
 */
function get_all_settings()
{
    $pdo = db_connect();
    
    try {
        $stmt = $pdo->query('SELECT `key`, `value` FROM settings ORDER BY `key`');
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        return [];
    }
}

// ============================================
// SECÇÃO: HISTÓRICO DE EMAILS
// ============================================

/**
 * Regista um email enviado no histórico
 * 
 * @param string $recipient Email do destinatário
 * @param string $subject Assunto do email
 * @param int $product_count Número de produtos mencionados
 * @param string $status Estado (sent, failed)
 * @param string|null $error_message Mensagem de erro se falhou
 * @return bool Sucesso ou falha
 */
function log_email_sent($recipient, $subject, $product_count, $status = 'sent', $error_message = null)
{
    $pdo = db_connect();
    
    try {
        $stmt = $pdo->prepare('INSERT INTO email_history (recipient, subject, product_count, status, error_message) VALUES (?, ?, ?, ?, ?)');
        return $stmt->execute([$recipient, $subject, $product_count, $status, $error_message]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Obtém histórico de emails enviados
 * 
 * @param int $limit Número máximo de registos
 * @return array Lista de emails
 */
function get_email_history($limit = 20)
{
    $pdo = db_connect();
    
    try {
        $limit = intval($limit);
        $stmt = $pdo->prepare('SELECT * FROM email_history ORDER BY sent_at DESC LIMIT ' . $limit);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Verifica stock baixo e envia email de notificação
 * 
 * @return bool Se enviou email ou não
 */
function check_and_send_low_stock_email()
{
    // Verifica se notificações estão ativadas
    $notify_enabled = get_setting('low_stock_notify_enabled', 1);
    if (!$notify_enabled) {
        return false;
    }
    
    // Obtém produtos com stock baixo
    $low_stock_products = list_low_stock_products();
    
    // Só envia se houver produtos
    if (empty($low_stock_products)) {
        return false;
    }
    
    // Obtém email de notificação
    $recipient = get_setting('low_stock_notify_email');
    if (!$recipient) {
        return false;
    }
    
    // Envia email
    return send_low_stock_email($low_stock_products, $recipient);
}

// ============================================
// SECÇÃO: NOTIFICAÇÕES POR EMAIL
// ============================================

/**
 * Envia email de alerta de stock baixo
 * Usa PHPMailer com SMTP
 * 
 * @param array $products Lista de produtos com stock baixo
 * @param string|null $recipient Email do destinatário
 * @return bool Se enviou com sucesso
 */
function send_low_stock_email($products, $recipient = null)
{
    if (empty($products)) return false;

    // Obtém email do destinatário
    $email = $recipient ?? get_setting('low_stock_notify_email');
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $subject = 'Stock Baixo - Ação Necessária';
    
    // Template HTML profissional do email
    $html = '<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Arial, sans-serif; color: #2c3e50; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); overflow: hidden;">
        
        <!-- Cabeçalho -->
        <div style="background: linear-gradient(135deg, #d9534f 0%, #c9302c 100%); padding: 30px 20px; text-align: center; color: white;">
            <h1 style="margin: 0; font-size: 28px; font-weight: 600;">Alerta de Stock Baixo</h1>
            <p style="margin: 8px 0 0 0; font-size: 14px; opacity: 0.9;">Ação necessária no seu supermercado</p>
        </div>
        
        <!-- Conteúdo -->
        <div style="padding: 30px 20px;">
            <p style="margin: 0 0 20px 0; font-size: 16px; color: #555;">
                Olá,<br><br>
                <strong>' . count($products) . ' produto(s) têm stock abaixo do limite mínimo configurado.</strong><br>
                Por favor, revise e proceda com as encomendas necessárias.
            </p>
            
            <!-- Tabela de Produtos -->
            <table style="width: 100%; border-collapse: collapse; margin: 25px 0;">
                <thead>
                    <tr style="background: #ecf0f1; border-bottom: 2px solid #d9534f;">
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #2c3e50;">Produto</th>
                        <th style="padding: 12px; text-align: center; font-weight: 600; color: #2c3e50; width: 80px;">Stock Atual</th>
                        <th style="padding: 12px; text-align: center; font-weight: 600; color: #2c3e50; width: 80px;">Mínimo</th>
                        <th style="padding: 12px; text-align: center; font-weight: 600; color: #2c3e50; width: 70px;">Diferença</th>
                    </tr>
                </thead>
                <tbody>';
    
    // Adiciona linha para cada produto
    $total = 0;
    foreach ($products as $p) {
        $diff = intval($p['min_stock']) - intval($p['stock']);
        $html .= '<tr style="border-bottom: 1px solid #ecf0f1;">';
        $html .= '<td style="padding: 14px 12px; color: #2c3e50; font-weight: 500;">' . htmlspecialchars($p['name']) . '</td>';
        $html .= '<td style="padding: 14px 12px; text-align: center; color: #d9534f; font-weight: bold; font-size: 16px;">' . intval($p['stock']) . '</td>';
        $html .= '<td style="padding: 14px 12px; text-align: center; color: #555;">' . intval($p['min_stock']) . '</td>';
        $html .= '<td style="padding: 14px 12px; text-align: center; color: #e67e22; font-weight: bold;">-' . $diff . '</td>';
        $html .= '</tr>';
        $total++;
    }
    
    $html .= '</tbody></table>
            
            <!-- Caixa de Ações -->
            <div style="background: #f8f9fa; border-left: 4px solid #3498db; padding: 15px; border-radius: 4px; margin: 25px 0;">
                <p style="margin: 0; font-size: 14px; color: #555;">
                    <strong>Próximos passos:</strong><br>
                    1. Revise os produtos listados acima<br>
                    2. Proceda com as encomendas necessárias<br>
                    3. Atualize o stock no sistema
                </p>
            </div>
            
            <!-- Resumo -->
            <div style="background: #ecf0f1; padding: 15px; border-radius: 4px; text-align: center;">
                <p style="margin: 0; font-size: 13px; color: #555;">
                    <strong>Total de produtos com stock baixo:</strong> ' . $total . '<br>
                    <strong>Data/Hora:</strong> ' . date('d/m/Y H:i:s') . '
                </p>
            </div>
        </div>
        
        <!-- Rodapé -->
        <div style="background: #2c3e50; color: #ecf0f1; padding: 20px; text-align: center; font-size: 12px;">
            <p style="margin: 0 0 8px 0;">
                Sistema de Gestão - Supermercado
            </p>
            <p style="margin: 0; opacity: 0.8;">
                Este é um email automático. Não responda diretamente para este endereço.
            </p>
        </div>
    </div>
</body>
</html>';
    
    // Envia via SMTP usando PHPMailer
    $sent = false;
    try {
        // Obtém configurações SMTP
        $smtp_host = get_setting('smtp_host');
        $smtp_port = get_setting('smtp_port');
        $smtp_user = get_setting('smtp_user');
        $smtp_pass = get_setting('smtp_pass');
        $email_from = get_setting('email_from', 'sistema@supermercado.local');
        $email_from_name = get_setting('email_from_name', 'Sistema de Stock');
        
        if ($smtp_host && $smtp_user && $smtp_pass) {
            require_once __DIR__ . '/../vendor/autoload.php';
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Configuração SMTP
            $mail->isSMTP();
            $mail->Host = $smtp_host;
            $mail->Port = intval($smtp_port) ?: 587;
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_user;
            $mail->Password = $smtp_pass;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            
            // Desativa verificação SSL para desenvolvimento
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            
            // Configura email
            $mail->setFrom($email_from, $email_from_name);
            $mail->addAddress($email);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = strip_tags($html);
            
            $sent = $mail->send();
            
            // Regista no histórico
            if ($sent) {
                set_setting('low_stock_last_email_at', date('Y-m-d H:i:s'));
                log_email_sent($email, $subject, count($products), 'sent', null);
            } else {
                log_email_sent($email, $subject, count($products), 'failed', 'Envio falhou');
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao enviar email: " . $e->getMessage());
        $sent = false;
        log_email_sent($email ?? 'desconhecido', $subject ?? 'Erro', 0, 'failed', $e->getMessage());
    }

    return $sent;
}

// ============================================
// SECÇÃO: RELATÓRIOS FINANCEIROS
// ============================================

/**
 * Obtém resumo financeiro do negócio
 * Calcula receita, custos, lucros
 * 
 * @return array Resumo financeiro
 */
function get_financial_summary()
{
    $pdo = db_connect();

    // Receita total (vendas)
    $rev = $pdo->query("SELECT COALESCE(SUM(total),0) as total FROM sales")->fetchColumn();

    // Custo das mercadorias vendidas
    $costs = $pdo->query("SELECT COALESCE(SUM(si.quantity * p.cost_price),0) as total_cost FROM sale_items si JOIN products p ON p.id = si.product_id")->fetchColumn();

    // Total de salários (todos os funcionários)
    $salaries = $pdo->query("SELECT COALESCE(SUM(salary),0) FROM employees")->fetchColumn();

    // Total de quebras/perdas
    $breaks = $pdo->query('SELECT COALESCE(SUM(cost),0) FROM breaks')->fetchColumn();

    // Custos fixos (pode ser expandido)
    $fixed = 0;

    // Cálculos de lucro
    $gross_profit = $rev - $costs;
    $net = $rev - ($costs + $salaries + $breaks + $fixed);

    return [
        'revenue' => (float)$rev,
        'cogs' => (float)$costs,
        'salaries' => (float)$salaries,
        'breaks' => (float)$breaks,
        'gross_profit' => (float)$gross_profit,
        'net_profit' => (float)$net,
    ];
}

/**
 * Obtém resumo financeiro de uma loja específica
 * 
 * @param int $store_id ID da loja
 * @return array Resumo financeiro da loja
 */
function get_financial_summary_by_store($store_id)
{
    $pdo = db_connect();

    // Receita total (vendas) da loja
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) as total FROM sales WHERE store_id = :store_id OR store_id IS NULL");
    $stmt->execute(['store_id' => $store_id]);
    $rev = $stmt->fetchColumn();

    // Custo das mercadorias vendidas da loja
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(si.quantity * p.cost_price),0) as total_cost 
        FROM sale_items si 
        JOIN sales s ON s.id = si.sale_id 
        JOIN products p ON p.id = si.product_id
        WHERE s.store_id = :store_id OR s.store_id IS NULL
    ");
    $stmt->execute(['store_id' => $store_id]);
    $costs = $stmt->fetchColumn();

    // Total de salários (todos os funcionários)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(salary),0) FROM employees");
    $stmt->execute();
    $salaries = $stmt->fetchColumn();

    // Total de quebras/perdas (global por agora)
    $breaks = $pdo->query('SELECT COALESCE(SUM(cost),0) FROM breaks')->fetchColumn();

    // Custos fixos (pode ser expandido)
    $fixed = 0;

    // Cálculos de lucro
    $gross_profit = $rev - $costs;
    $net = $rev - ($costs + $salaries + $breaks + $fixed);

    return [
        'revenue' => (float)$rev,
        'cogs' => (float)$costs,
        'salaries' => (float)$salaries,
        'breaks' => (float)$breaks,
        'gross_profit' => (float)$gross_profit,
        'net_profit' => (float)$net,
    ];
}

/**
 * Obtém lucro diário
 * 
 * @param int $days Número de dias a consultar
 * @return array Lucros por dia
 */
function get_daily_profit($days = 30)
{
    $pdo = db_connect();
    $days = (int)$days;
    return $pdo->query("SELECT * FROM daily_profit ORDER BY date DESC LIMIT $days")->fetchAll();
}

/**
 * Obtém lucro mensal
 * 
 * @param int $months Número de meses a consultar
 * @return array Lucros por mês
 */
function get_monthly_profit($months = 12)
{
    $pdo = db_connect();
    $stmt = $pdo->query("SELECT * FROM monthly_profit ORDER BY month DESC LIMIT $months");
    return $stmt->fetchAll();
}

/**
 * Obtém produtos mais vendidos por mês
 * 
 * @param string|null $month Mês específico (YYYY-MM) ou null para todos
 * @param int $limit Número máximo de produtos
 * @return array Lista de top produtos
 */
function get_monthly_top_products($month = null, $limit = 5)
{
    $pdo = db_connect();
    $limit = (int)$limit;
    
    if ($month) {
        $stmt = $pdo->prepare("SELECT * FROM monthly_top_product WHERE month = ? ORDER BY total_qty DESC LIMIT $limit");
        $stmt->execute([$month]);
    } else {
        $stmt = $pdo->query("SELECT * FROM monthly_top_product ORDER BY month DESC, total_qty DESC LIMIT $limit");
    }
    
    return $stmt->fetchAll();
}

// ============================================
// SECÇÃO: REPOSIÇÃO AUTOMÁTICA
// ============================================

/**
 * Cria encomendas automáticas para produtos com stock baixo
 * 
 * @param int|null $threshold Limite de stock (null = usa min_stock)
 * @param int $reorder_qty Quantidade a encomendar
 * @return array Lista de encomendas criadas
 */
function auto_reorder($threshold = null, $reorder_qty = 20)
{
    $pdo = db_connect();
    
    // Obtém produtos com stock baixo
    if ($threshold === null) {
        $products = list_low_stock_products();
    } else {
        $products = list_low_stock_products($threshold);
    }
    
    if (count($products) === 0) return [];

    $created = [];
    
    try {
        $pdo->beginTransaction();

        foreach ($products as $p) {
            // Ignora produtos sem fornecedor
            if (!$p['supplier_id']) continue;
            
            // Verifica se já existe encomenda pendente
            $stmt = $pdo->prepare('SELECT id FROM orders WHERE product_id = ? AND status IN ("pending", "processed", "shipped")');
            $stmt->execute([$p['id']]);
            if ($stmt->fetch()) continue;
            
            // Cria encomenda
            $stmt = $pdo->prepare('INSERT INTO orders (supplier_id, product_id, qty, cost_price, status, created_at) VALUES (?, ?, ?, ?, "pending", NOW())');
            $stmt->execute([$p['supplier_id'], $p['id'], $reorder_qty, $p['cost_price']]);
            $order_id = $pdo->lastInsertId();
            
            $created[] = [
                'order_id' => $order_id, 
                'product_id' => $p['id'], 
                'qty' => $reorder_qty, 
                'product_name' => $p['name']
            ];
            
            // Adiciona mensagem
            add_order_message($order_id, 'created', "Encomenda automática gerada para {$p['name']} (stock: {$p['stock']})");
        }

        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
    }

    return $created;
}

// ============================================
// SECÇÃO: GESTÃO DE CLIENTES
// ============================================

/**
 * Procura clientes por nome ou NIF
 * @param string $search Termo de busca
 * @param int $store_id ID da loja
 * @return array Lista de clientes
 */
function search_customers($search, $store_id = null) {
    $pdo = db_connect();
    
    if (!$store_id) {
        $store_id = get_current_store_id();
    }
    
    try {
        $search_term = '%' . $search . '%';
        $stmt = $pdo->prepare("
            SELECT * FROM customers 
            WHERE (store_id = ? OR store_id IS NULL)
            AND (name LIKE ? OR phone LIKE ? OR nif LIKE ? OR email LIKE ?)
            ORDER BY name
            LIMIT 50
        ");
        $stmt->execute([$store_id, $search_term, $search_term, $search_term, $search_term]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log("Error searching customers: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtém um cliente pelo ID
 * @param int $customer_id ID do cliente
 * @return array|false Dados do cliente ou false
 */
function get_customer($customer_id) {
    $pdo = db_connect();
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$customer_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting customer: " . $e->getMessage());
        return false;
    }
}

/**
 * Cria um novo cliente
 * @param array $data Dados do cliente (name, email, phone, nif, etc)
 * @param int $store_id ID da loja
 * @return int|false ID do novo cliente ou false
 */
function create_customer($data, $store_id = null) {
    $pdo = db_connect();
    
    if (!$store_id) {
        $store_id = get_current_store_id();
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO customers (
                name, email, phone, nif, address, city, postal_code,
                loyalty_points, created_at, store_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW(), ?)
        ");
        
        $stmt->execute([
            $data['name'] ?? null,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['nif'] ?? null,
            $data['address'] ?? null,
            $data['city'] ?? null,
            $data['postal_code'] ?? null,
            $store_id
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error creating customer: " . $e->getMessage());
        return false;
    }
}

/**
 * Atualiza um cliente existente
 * @param int $customer_id ID do cliente
 * @param array $data Dados a atualizar
 * @return bool Sucesso da operação
 */
function update_customer($customer_id, $data) {
    $pdo = db_connect();
    
    try {
        $set_parts = [];
        $values = [];
        
        $fields = ['name', 'email', 'phone', 'nif', 'address', 'city', 'postal_code'];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $set_parts[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($set_parts)) {
            return true;
        }
        
        $values[] = $customer_id;
        
        $stmt = $pdo->prepare("UPDATE customers SET " . implode(', ', $set_parts) . " WHERE id = ?");
        $stmt->execute($values);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error updating customer: " . $e->getMessage());
        return false;
    }
}

/**
 * Deleta um cliente
 * @param int $customer_id ID do cliente
 * @return bool Sucesso da operação
 */
function delete_customer($customer_id) {
    $pdo = db_connect();
    
    try {
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->execute([$customer_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Error deleting customer: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém todos os clientes com paginação
 * @param int $page Número da página
 * @param int $per_page Clientes por página
 * @param int $store_id ID da loja
 * @return array ['data' => [...], 'total' => N, 'pages' => N]
 */
function get_customers_paginated($page = 1, $per_page = 20, $store_id = null) {
    $pdo = db_connect();
    
    if (!$store_id) {
        $store_id = get_current_store_id();
    }
    
    try {
        // Total
        $count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM customers WHERE store_id = ? OR store_id IS NULL");
        $count_stmt->execute([$store_id]);
        $total = $count_stmt->fetch()['total'];
        
        // Dados
        $offset = ($page - 1) * $per_page;
        $stmt = $pdo->prepare("
            SELECT * FROM customers 
            WHERE store_id = ? OR store_id IS NULL
            ORDER BY name
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$store_id, $per_page, $offset]);
        
        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'pages' => ceil($total / $per_page)
        ];
    } catch (PDOException $e) {
        error_log("Error getting customers: " . $e->getMessage());
        return ['data' => [], 'total' => 0, 'pages' => 0];
    }
}

?>
