<?php
// modules/pricing.php
// Gestão de preços, promoções e análise de margem

// Se chamado diretamente, redirecionar para index.php
if (basename($_SERVER['SCRIPT_FILENAME']) === 'pricing.php') {
    require_once __DIR__ . '/../index.php';
    exit;
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pricing.php';

$auth->require_auth('pricing', 'view');
$current_user = $auth->get_current_user();

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
            <h3>Dashboard de Preços</h3>
            
            <?php 
            $margins = get_category_margin_analysis(null, 30);
            $underpriced = find_underpriced_products();
            $performance = get_pricing_performance_report(30);
            ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Produtos Ativos</h4>
                    <p class="stat-value"><?php echo count(list_products(true)); ?></p>
                </div>
                
                <div class="stat-card">
                    <h4>Margem Média</h4>
                    <p class="stat-value">
                        <?php 
                        $avg_margin = 0;
                        foreach ($margins as $m) {
                            $avg_margin += $m['avg_margin'];
                        }
                        echo count($margins) > 0 ? round($avg_margin / count($margins), 2) . '%' : 'N/A';
                        ?>
                    </p>
                </div>
                
                <div class="stat-card">
                    <h4>Produtos Subpreçados</h4>
                    <p class="stat-value alert"><?php echo count($underpriced); ?></p>
                </div>
                
                <div class="stat-card">
                    <h4>Promoções Ativas</h4>
                    <p class="stat-value">
                        <?php 
                        $active_promos = $pdo->query("SELECT COUNT(*) FROM promotions WHERE active = 1 AND start_date <= NOW() AND end_date >= NOW()")->fetchColumn();
                        echo $active_promos;
                        ?>
                    </p>
                </div>
            </div>
            
            <h4>Margem por Categoria (Últimos 30 dias)</h4>
            <table class="table">
                <thead>
                    <tr>
                        <th>Categoria</th>
                        <th>Margem Média</th>
                        <th>Mínima</th>
                        <th>Máxima</th>
                        <th>Produtos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($margins as $margin): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($margin['category']); ?></td>
                        <td><strong><?php echo round($margin['avg_margin'], 2); ?>%</strong></td>
                        <td><?php echo round($margin['min_margin'], 2); ?>%</td>
                        <td><?php echo round($margin['max_margin'], 2); ?>%</td>
                        <td><?php echo $margin['product_count']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
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
        <div class="pricing-strategies">
            <h3>Estratégias de Preço</h3>
            
            <div class="filter-form">
                <input type="text" id="search" placeholder="Procurar produto..." class="form-input" style="width: 300px;">
                <button onclick="document.getElementById('search').value = ''; location.reload();" class="btn btn-secondary">Limpar</button>
            </div>
            
            <?php 
            $products = list_products(true);
            $search = $_GET['search'] ?? '';
            if ($search) {
                $products = array_filter($products, fn($p) => stripos($p['name'], $search) !== false);
            }
            ?>
            
            <table class="table">
                <thead>
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
                    <tr>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo number_format($product['cost_price'], 2); ?>€</td>
                        <td><?php echo number_format($product['sell_price'], 2); ?>€</td>
                        <td><?php echo round($margin['markup_percent'], 2); ?>%</td>
                        <td><?php echo round($margin['margin_percent'], 2); ?>%</td>
                        <td>
                            <?php if ($strategy): ?>
                            <span class="badge badge-success"><?php echo $strategy['markup_percent']; ?>% markup</span>
                            <?php else: ?>
                            <span class="badge badge-info">Padrão</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="#" class="btn-small" onclick="editStrategy(<?php echo $product['id']; ?>)">Editar</a>
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
                    $status = ($promo['active'] && strtotime($promo['start_date']) <= time() && strtotime($promo['end_date']) >= time()) ? 'Ativa' : 'Inativa';
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
    border-bottom: 2px solid #eee;
    padding-bottom: 0;
}

.tab-btn {
    padding: 12px 20px;
    background: white;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s;
}

.tab-btn:hover {
    border-bottom-color: #667eea;
    color: #667eea;
}

.tab-btn.active {
    border-bottom-color: #667eea;
    color: #667eea;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.stat-card h4 {
    margin: 0 0 10px 0;
    color: #666;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.stat-value {
    margin: 0;
    font-size: 32px;
    font-weight: bold;
    color: #667eea;
}

.stat-value.alert {
    color: #e74c3c;
}

.filter-form {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.progress-bar {
    width: 100px;
    height: 6px;
    background: #ecf0f1;
    border-radius: 3px;
    overflow: hidden;
    margin-top: 5px;
}

.progress-bar div {
    height: 100%;
    transition: width 0.3s;
}

.trend {
    font-size: 14px;
    font-weight: 600;
}

.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.badge-success {
    background: #d4edda;
    color: #155724;
}

.badge-info {
    background: #d1ecf1;
    color: #0c5460;
}

.badge-warning {
    background: #fff3cd;
    color: #856404;
}

.table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    margin-top: 20px;
}

.table th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #ddd;
}

.table td {
    padding: 12px;
    border-bottom: 1px solid #eee;
}

.table tr:hover {
    background: #f9f9f9;
}

.btn-small {
    padding: 4px 8px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    text-decoration: none;
}

.btn-small:hover {
    background: #5568d3;
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
