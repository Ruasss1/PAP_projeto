<?php
// modules/pricing.php
// Gestão de preços, promoções e análise de margem

session_start();
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/auth.php';

// Validar permissão ANTES de incluir header
$auth->require_auth('precos', 'view');
$current_user = $auth->get_current_user();

require_once __DIR__ . '/../includes/pricing.php';

$is_ajax = ($_GET['ajax'] ?? '') === '1';

if ($is_ajax) {
    ob_start(); // buffer para limpar HTML anterior na resposta AJAX
}

if (!$is_ajax) {
    require_once __DIR__ . '/../includes/header.php';
}

$view = $_GET['view'] ?? 'dashboard';
$pdo = db_connect();
?>



<div class="module-container">
    <div class="module-header">
        <h2>💰 Gestão de Preços</h2>
        <p>Estratégias de preço, promoções e análise de margem</p>
    </div>
    
    <div class="pricing-tabs">
        <a href="?page=pricing&view=dashboard" class="tab-btn <?php echo $view === 'dashboard' ? 'active' : ''; ?>">
            📊 Dashboard
        </a>
        <a href="?page=pricing&view=strategies" class="tab-btn <?php echo $view === 'strategies' ? 'active' : ''; ?>">
            📈 Estratégias
        </a>
        <a href="?page=pricing&view=promotions" class="tab-btn <?php echo $view === 'promotions' ? 'active' : ''; ?>">
            🎯 Promoções
        </a>
        <a href="?page=pricing&view=margins" class="tab-btn <?php echo $view === 'margins' ? 'active' : ''; ?>">
            💹 Análise de Margem
        </a>
        <a href="?page=pricing&view=categories" class="tab-btn <?php echo $view === 'categories' ? 'active' : ''; ?>">
            📂 Categorias
        </a>
    </div>
    
    <?php if ($view === 'dashboard'): ?>
        <div class="pricing-dashboard">
            <h3>📊 Gestão de Preços</h3>
            
            <?php 
            // Get filter parameters
            $selected_category_price = $_GET['category_price'] ?? 'all';
            $selected_sort_price = $_GET['sort_price'] ?? 'name_az';
            
            $products = list_products(true);
            
            // Filter and sort
            if ($selected_category_price !== 'all') {
                $products = array_filter($products, fn($p) => ($p['category'] ?? '') === $selected_category_price);
            }
            
            usort($products, function($a, $b) use ($selected_sort_price) {
                switch ($selected_sort_price) {
                    case 'name_az':
                        return strcmp($a['name'], $b['name']);
                    case 'name_za':
                        return strcmp($b['name'], $a['name']);
                    case 'price_low':
                        return $a['sell_price'] <=> $b['sell_price'];
                    case 'price_high':
                        return $b['sell_price'] <=> $a['sell_price'];
                    default:
                        return strcmp($a['name'], $b['name']);
                }
            });
            
            $all_categories_pricing = get_all_categories();
            ?>
            
            <!-- Filtros Pricing -->
            <div style="display: flex; gap: 20px; margin: 20px 0; padding: 15px; background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%); border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.2);">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: black;">📂 Categoria</label>
                    <select id="category-price-filter" name="category_price" onchange="updatePricingFiltersAjax()" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background: white; cursor: pointer; color: black;">
                        <option value="all" <?php echo $selected_category_price === 'all' ? 'selected' : ''; ?>>Todas</option>
                        <?php foreach ($all_categories_pricing as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $selected_category_price === $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: black;">🔄 Ordenar por</label>
                    <select id="sort-price-filter" name="sort_price" onchange="updatePricingFiltersAjax()" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background: white; cursor: pointer; color: black;">
                        <option value="name_az" <?php echo $selected_sort_price === 'name_az' ? 'selected' : ''; ?>>A-Z</option>
                        <option value="name_za" <?php echo $selected_sort_price === 'name_za' ? 'selected' : ''; ?>>Z-A</option>
                        <option value="price_low" <?php echo $selected_sort_price === 'price_low' ? 'selected' : ''; ?>>💰 Mais Barato</option>
                        <option value="price_high" <?php echo $selected_sort_price === 'price_high' ? 'selected' : ''; ?>>💸 Mais Caro</option>
                    </select>
                </div>
            </div>
            
            <script>
            function updatePricingFiltersAjax() {
                const categoryFilter = document.getElementById('category-price-filter').value;
                const sortFilter = document.getElementById('sort-price-filter').value;
                const tableContainer = document.getElementById('pricing-table');
                
                const params = new URLSearchParams();
                params.append('page', 'pricing');
                params.append('view', 'dashboard');
                params.append('ajax', '1');
                params.append('category_price', categoryFilter);
                params.append('sort_price', sortFilter);
                
                fetch('/modules/pricing.php?' + params.toString())
                    .then(res => res.text())
                    .then(html => {
                        tableContainer.innerHTML = html;
                    })
                    .catch(() => {
                        window.location.href = '/modules/pricing.php?' + params.toString();
                    });
            }
            </script>
            
            <?php ob_start(); ?>
            <div id="pricing-table" class="table-container">
                <table class="table" style="background: #1f2937; color: #e5e7eb;">
                    <thead>
                        <tr style="background: #111827; border-bottom: 2px solid #667eea;">
                            <th style="color: #93c5fd;">Produto</th>
                            <th style="color: #93c5fd;">Categoria</th>
                            <th style="color: #93c5fd;">Preço de Compra</th>
                            <th style="color: #93c5fd;">Preço de Venda</th>
                            <th style="color: #93c5fd;">IVA (%)</th>
                            <th style="color: #93c5fd;">Preço com IVA</th>
                            <th style="color: #93c5fd;">Margem (€)</th>
                            <th style="color: #93c5fd;">Margem (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $prod): 
                            $margin_value = $prod['sell_price'] - $prod['cost_price'];
                            $margin_percent = $prod['cost_price'] > 0 ? round(($margin_value / $prod['sell_price']) * 100, 2) : 0;
                            $price_with_iva = round($prod['sell_price'] * 1.23, 2);
                        ?>
                        <tr style="border-bottom: 1px solid #374151;">
                            <td><strong style="color: #60a5fa;"><?php echo htmlspecialchars($prod['name']); ?></strong></td>
                            <td style="color: #a78bfa;"><?php echo htmlspecialchars($prod['category'] ?? 'S/ Categoria'); ?></td>
                            <td style="color: #38bdf8; font-weight: 600;">€ <?php echo number_format($prod['cost_price'], 2); ?></td>
                            <td style="color: #34d399; font-weight: 600;">€ <?php echo number_format($prod['sell_price'], 2); ?></td>
                            <td style="text-align: center; color: #fbbf24;">23%</td>
                            <td style="color: #f97316; font-weight: 600;">€ <?php echo number_format($price_with_iva, 2); ?></td>
                            <td style="color: <?php echo $margin_value >= 0 ? '#10b981' : '#ef4444'; ?>; font-weight: 600;">
                                € <?php echo number_format($margin_value, 2); ?>
                            </td>
                            <td style="color: <?php echo $margin_percent >= 0 ? '#10b981' : '#ef4444'; ?>; font-weight: 600;">
                                <?php echo number_format($margin_percent, 2); ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php 
            $pricing_table_html = ob_get_clean();

            if ($is_ajax) {
                ob_clean();
                echo $pricing_table_html;
                exit;
            }

            echo $pricing_table_html;
            ?>

            <h4 style="margin-top: 30px;">📈 Resumo de Margens</h4>
            <div class="stats-grid">
                <?php 
                $total_cost = 0;
                $total_selling = 0;
                $total_margin_value = 0;
                $total_margin_percent = 0;
                
                foreach ($all_products as $prod) {
                    $total_cost += $prod['cost_price'];
                    $total_selling += $prod['selling_price'];
                    $total_margin_value += $prod['margin_value'];
                    $total_margin_percent += $prod['margin_percent'];
                }
                $avg_margin_percent = count($all_products) > 0 ? $total_margin_percent / count($all_products) : 0;
                ?>
                <div class="stat-card">
                    <h4>Total Custo de Compra</h4>
                    <p class="stat-value">€ <?php echo number_format($total_cost, 2); ?></p>
                </div>
                
                <div class="stat-card">
                    <h4>Total Preço de Venda</h4>
                    <p class="stat-value">€ <?php echo number_format($total_selling, 2); ?></p>
                </div>
                
                <div class="stat-card">
                    <h4>Margem Total (€)</h4>
                    <p class="stat-value" style="color: #28a745;">€ <?php echo number_format($total_margin_value, 2); ?></p>
                </div>
                
                <div class="stat-card">
                    <h4>Margem Média (%)</h4>
                    <p class="stat-value" style="color: #0066ff;"><?php echo number_format($avg_margin_percent, 2); ?>%</p>
                </div>
            </div>
            
            <?php if (count($underpriced) > 0): ?>
            <h4 style="color: #e74c3c; margin-top: 30px;">⚠️ Produtos Subpreçados</h4>
            <table class="table">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Custo</th>
                        <th>Preço</th>
                        <th>Margem</th>
                        <th>Mínimo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($underpriced, 0, 10) as $product): ?>
                    <tr style="background: #fef5e7;">
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['category']); ?></td>
                        <td><?php echo number_format($product['cost_price'], 2); ?>€</td>
                        <td><?php echo number_format($product['sell_price'], 2); ?>€</td>
                        <td><?php echo round($product['margin_percent'], 2); ?>%</td>
                        <td><?php echo $product['min_margin_percent'] ? round($product['min_margin_percent'], 2) . '%' : 'N/A'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    
    <?php elseif ($view === 'strategies'): ?>
        <div class="pricing-strategies" style="background: radial-gradient(circle at 10% 20%, #1b2a4a, #0b1220); padding: 20px; border-radius: 14px; border: 1px solid rgba(255,255,255,0.08);">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap: wrap; margin-bottom: 16px; color: #e2e8f0;">
                <div>
                    <div style="font-size:18px; font-weight:700; color:#f8fafc;">Estratégias de Preço</div>
                    <div style="font-size:13px; color:#cbd5e1;">Ajusta markup e margem por produto com um look mais escuro.</div>
                </div>
                <div class="filter-form" style="display:flex; gap:8px; align-items:center;">
                    <input type="text" id="search" placeholder="Procurar produto..." class="form-input" style="width: 260px; background:#0f172a; color:#e2e8f0; border:1px solid #1e293b;">
                    <button onclick="document.getElementById('search').value = ''; location.reload();" class="btn btn-secondary" style="background:#1d4ed8; border:none; color:#fff;">Limpar</button>
                </div>
            </div>
            
            <?php 
            $products = list_products(true);
            $search = $_GET['search'] ?? '';
            if ($search) {
                $products = array_filter($products, fn($p) => stripos($p['name'], $search) !== false);
            }
            ?>

            <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:14px;">
                <div style="background:#0f172a; color:#e2e8f0; padding:12px 14px; border-radius:12px; border:1px solid #1e293b; min-width:160px;">
                    <div style="font-size:12px; color:#94a3b8;">Produtos</div>
                    <div style="font-size:18px; font-weight:700; color:#f8fafc;"><?php echo count($products); ?></div>
                </div>
                <div style="background:#0f172a; color:#e2e8f0; padding:12px 14px; border-radius:12px; border:1px solid #1e293b; min-width:160px;">
                    <div style="font-size:12px; color:#94a3b8;">Com estratégia definida</div>
                    <div style="font-size:18px; font-weight:700; color:#34d399;">
                        <?php 
                        $with_strategy = 0;
                        foreach ($products as $p) { if (get_price_strategy($p['id'])) { $with_strategy++; } }
                        echo $with_strategy;
                        ?>
                    </div>
                </div>
            </div>
            
            <table class="table" style="background:#0f172a; color:#e2e8f0; border:1px solid #1e293b;">
                <thead style="background:#111827; color:#cbd5e1;">
                    <tr>
                        <th>Produto</th>
                        <th>Custo</th>
                        <th>Preço Atual</th>
                        <th>Markup</th>
                        <th>Margem</th>
                        <th>Estratégia</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($products, 0, 50) as $product): ?>
                    <?php $margin = calculate_margin($product['id']); $strategy = get_price_strategy($product['id']); ?>
                    <tr style="background:rgba(255,255,255,0.02);">
                        <td style="font-weight:600; color:#f8fafc;"><?php echo htmlspecialchars($product['name']); ?></td>
                        <td>€ <?php echo number_format($product['cost_price'], 2); ?></td>
                        <td style="color:#34d399;">€ <?php echo number_format($product['sell_price'], 2); ?></td>
                        <td><?php echo round($margin['markup_percent'], 2); ?>%</td>
                        <td style="color:<?php echo $margin['margin_percent'] >= 0 ? '#22c55e' : '#f87171'; ?>;">
                            <?php echo round($margin['margin_percent'], 2); ?>%
                        </td>
                        <td>
                            <?php if ($strategy): ?>
                            <span class="badge badge-success" style="background:#16a34a; color:#0b1220;">Markup <?php echo $strategy['markup_percent']; ?>%</span>
                            <?php else: ?>
                            <span class="badge badge-info" style="background:#334155; color:#e2e8f0;">Padrão</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="#" class="btn-small" style="background:#1d4ed8; color:#fff;" onclick="editStrategy(<?php echo $product['id']; ?>)">Editar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    
    <?php elseif ($view === 'promotions'): ?>
        <div class="pricing-promotions">
            <h3>Promoções</h3>
            
            <?php 
            $promotions = list_promotions(false, 100);
            ?>
            
            <div style="margin-bottom: 20px;">
                <a href="#" class="btn btn-primary" onclick="showPromotionForm()">+ Nova Promoção</a>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>Desconto</th>
                        <th>Período</th>
                        <th>Status</th>
                        <th>Produtos</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($promotions as $promo): ?>
                    <?php 
                    $promo_details = get_promotion($promo['id']);
                    // Use is_active column with a safe fallback to avoid undefined index notices
                    $is_active = $promo['is_active'] ?? ($promo['active'] ?? 0);
                    $status = ($is_active && strtotime($promo['start_date']) <= time() && strtotime($promo['end_date']) >= time()) ? 'Ativa' : 'Inativa';
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($promo['name']); ?></strong></td>
                        <td><?php echo ucfirst($promo['discount_type']); ?></td>
                        <td>
                            <?php 
                            echo $promo['discount_type'] === 'percentage' ? $promo['discount_value'] . '%' : $promo['discount_value'] . '€';
                            ?>
                        </td>
                        <td>
                            <?php echo date('d/m/Y', strtotime($promo['start_date'])); ?> a 
                            <?php echo date('d/m/Y', strtotime($promo['end_date'])); ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $status === 'Ativa' ? 'success' : 'warning'; ?>">
                                <?php echo $status; ?>
                            </span>
                        </td>
                        <td><?php echo count($promo_details['products']) + count($promo_details['categories']); ?></td>
                        <td>
                            <a href="#" class="btn-small">Editar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    
    <?php elseif ($view === 'margins'): ?>
        <div class="pricing-margins">
            <h3>Análise de Margem</h3>
            
            <?php 
            $margin_data = get_category_margin_analysis(null, 90);
            ?>
            
            <h4>Evolução de Margem por Categoria (Últimos 90 dias)</h4>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Categoria</th>
                        <th>Margem Média</th>
                        <th>Mínima</th>
                        <th>Máxima</th>
                        <th>Markup Médio</th>
                        <th>Tendência</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($margin_data as $data): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($data['category']); ?></td>
                        <td>
                            <strong><?php echo round($data['avg_margin'], 2); ?>%</strong>
                            <div class="progress-bar">
                                <div style="width: <?php echo min($data['avg_margin'], 100); ?>%; background: #27ae60;"></div>
                            </div>
                        </td>
                        <td><?php echo round($data['min_margin'], 2); ?>%</td>
                        <td><?php echo round($data['max_margin'], 2); ?>%</td>
                        <td><?php echo round($data['avg_markup'], 2); ?>%</td>
                        <td>
                            <span class="trend">
                                <?php 
                                if ($data['avg_margin'] > 20) {
                                    echo '📈 Boa';
                                } elseif ($data['avg_margin'] > 10) {
                                    echo '➡️ Média';
                                } else {
                                    echo '📉 Baixa';
                                }
                                ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    
    <?php elseif ($view === 'categories'): ?>
        <div class="pricing-categories">
            <h3>Regras de Preço por Categoria</h3>
            
            <?php 
            $category_rules = get_category_pricing_rules();
            ?>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Categoria</th>
                        <th>Markup Padrão</th>
                        <th>Margem Mínima</th>
                        <th>Desconto Máximo</th>
                        <th>Status</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($category_rules as $rule): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($rule['category']); ?></strong></td>
                        <td><?php echo $rule['default_markup_percent']; ?>%</td>
                        <td><?php echo $rule['min_margin_percent']; ?>%</td>
                        <td><?php echo $rule['max_discount_percent']; ?>%</td>
                        <td>
                            <span class="badge badge-<?php echo $rule['active'] ? 'success' : 'warning'; ?>">
                                <?php echo $rule['active'] ? 'Ativa' : 'Inativa'; ?>
                            </span>
                        </td>
                        <td>
                            <a href="#" class="btn-small" onclick="editCategoryRule('<?php echo $rule['category']; ?>')">Editar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    
    <?php endif; ?>
</div>

<style>
.pricing-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
    border-bottom: 1px solid #1e293b;
    padding-bottom: 0;
}

.tab-btn {
    padding: 12px 20px;
    background: #0f172a;
    color: #e2e8f0;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}

.tab-btn:hover {
    border-bottom-color: #38bdf8;
    color: #38bdf8;
}

.tab-btn.active {
    border-bottom-color: #38bdf8;
    color: #38bdf8;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: linear-gradient(135deg, #0f172a, #111827);
    padding: 18px;
    border-radius: 12px;
    border: 1px solid #1e293b;
    color: #e2e8f0;
}

.stat-card h4 {
    margin: 0 0 8px 0;
    color: #94a3b8;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-value {
    margin: 0;
    font-size: 30px;
    font-weight: 800;
    color: #38bdf8;
}

.stat-value.alert {
    color: #f87171;
}

.filter-form {
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
}

.progress-bar {
    width: 120px;
    height: 6px;
    background: #1e293b;
    border-radius: 3px;
    overflow: hidden;
    margin-top: 5px;
}

.progress-bar div {
    height: 100%;
    transition: width 0.3s;
    background: #22c55e;
}

.trend {
    font-size: 13px;
    font-weight: 700;
    color: #cbd5e1;
}

.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
}

.badge-success {
    background: #16a34a;
    color: #0b1220;
}

.badge-info {
    background: #334155;
    color: #e2e8f0;
}

.badge-warning {
    background: #f59e0b;
    color: #0b1220;
}

.table {
    width: 100%;
    border-collapse: collapse;
    background: #0f172a;
    color: #e2e8f0;
    margin-top: 16px;
    border: 1px solid #1e293b;
}

.table th {
    background: #111827;
    padding: 12px;
    text-align: left;
    font-weight: 700;
    border-bottom: 1px solid #1e293b;
    color: #cbd5e1;
}

.table td {
    padding: 12px;
    border-bottom: 1px solid #1e293b;
}

.table tr:hover {
    background: rgba(56, 189, 248, 0.05);
}

.btn-small {
    padding: 6px 10px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    text-decoration: none;
    transition: background 0.2s;
}

.btn-small:hover {
    background: #1d4ed8;
}
</style>

<script>
function editStrategy(productId) {
    alert('Implementação em progresso...\nProduto ID: ' + productId);
}

function showPromotionForm() {
    alert('Implementação em progresso...\nFormulário de nova promoção');
}

function editCategoryRule(category) {
    alert('Implementação em progresso...\nEditando regras de: ' + category);
}
</script>
<?php if (!$is_ajax) { require_once __DIR__ . '/../includes/footer.php'; } ?>