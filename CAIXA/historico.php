<?php
/**
 * HISTÓRICO DE VENDAS - PREMIUM
 * Interface moderna para consulta de vendas
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../config/database.php';

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 25;
$offset = ($page - 1) * $limit;

$dateFilter = $_GET['date'] ?? '';
$searchFilter = $_GET['search'] ?? '';

$whereConditions = [];
$params = [];

if ($dateFilter) {
    $whereConditions[] = "DATE(s.sale_date) = ?";
    $params[] = $dateFilter;
}

if ($searchFilter) {
    $whereConditions[] = "s.id = ?";
    $params[] = intval($searchFilter);
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$countQuery = "SELECT COUNT(*) FROM sales s $whereClause";
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalSales = $stmt->fetchColumn();
$totalPages = ceil($totalSales / $limit);

$query = "
    SELECT 
        s.id as sale_id,
        s.total,
        s.sale_date,
        s.payment_method,
        s.nif,
        (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as total_itens
    FROM sales s
    $whereClause
    ORDER BY s.sale_date DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$todayStats = $pdo->query("
    SELECT 
        COUNT(*) as num_vendas,
        COALESCE(SUM(total), 0) as total_vendas
    FROM sales 
    WHERE DATE(sale_date) = CURDATE()
")->fetch();

$monthStats = $pdo->query("
    SELECT 
        COUNT(*) as num_vendas,
        COALESCE(SUM(total), 0) as total_vendas
    FROM sales 
    WHERE MONTH(sale_date) = MONTH(CURDATE()) AND YEAR(sale_date) = YEAR(CURDATE())
")->fetch();

?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Vendas | POS Premium</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/premium.css">
    <link rel="stylesheet" href="/assets/css/master-ui.css?v=<?= time() ?>">
    <script>
        (function() {
            const theme = localStorage.getItem('pap-theme') || 'dark';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--bg-primary);
            min-height: 100vh;
            color: var(--text-primary);
            padding: 24px;
        }
        
        .container { max-width: 1400px; margin: 0 auto; }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border);
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .back-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .back-btn:hover {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 16px;
        }
        
        .stat-icon.blue { background: var(--bg-tertiary); }
        .stat-icon.green { background: var(--bg-tertiary); }
        .stat-icon.orange { background: var(--bg-tertiary); }
        .stat-icon.purple { background: var(--bg-tertiary); }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 13px;
            color: var(--text-muted);
        }
        
        .filters {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            align-items: flex-end;
        }
        
        .form-group { flex: 1; }
        .form-group label {
            display: block;
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: var(--accent);
            color: white;
        }
        
        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
        }
        
        .btn:hover { transform: translateY(-1px); }
        
        .table-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
        }
        
        .table-header h2 {
            font-size: 18px;
            font-weight: 600;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 16px 24px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        th {
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: var(--bg-tertiary);
        }
        
        tr:hover td { background: var(--bg-tertiary); }
        
        .sale-id {
            font-weight: 600;
            color: var(--accent);
        }
        
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success { background: rgba(34, 197, 94, 0.15); color: var(--success); }
        .badge-primary { background: var(--bg-tertiary); color: var(--accent); }
        .badge-muted { background: var(--bg-tertiary); color: var(--text-muted); }
        
        .total-value {
            font-size: 16px;
            font-weight: 700;
            color: var(--success);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
        }
        
        .pagination a {
            padding: 10px 16px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .pagination a:hover,
        .pagination a.active {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 64px;
            color: var(--text-muted);
        }
        
        .empty-state span { font-size: 48px; display: block; margin-bottom: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Histórico de Vendas</h1>
            <a href="/CAIXA/" class="back-btn">← Voltar ao POS</a>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">📊</div>
                <div class="stat-value"><?= $totalSales ?></div>
                <div class="stat-label">Total Vendas</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">🛒</div>
                <div class="stat-value"><?= $todayStats['num_vendas'] ?></div>
                <div class="stat-label">Vendas Hoje</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">💰</div>
                <div class="stat-value money-positive">€<?= number_format($todayStats['total_vendas'], 2) ?></div>
                <div class="stat-label">Receita Hoje</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple">📅</div>
                <div class="stat-value money-positive">€<?= number_format($monthStats['total_vendas'], 2) ?></div>
                <div class="stat-label">Receita Mês</div>
            </div>
        </div>
        
        <form class="filters" method="get">
            <div class="form-group">
                <label>Data</label>
                <input type="date" name="date" value="<?= htmlspecialchars($dateFilter) ?>">
            </div>
            <div class="form-group">
                <label>Nº Venda</label>
                <input type="text" name="search" placeholder="ID da venda..." value="<?= htmlspecialchars($searchFilter) ?>">
            </div>
            <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
            <a href="historico.php" class="btn btn-secondary">Limpar</a>
        </form>
        
        <div class="table-card">
            <div class="table-header">
                <h2>Vendas (<?= count($sales) ?>)</h2>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Nº</th>
                        <th>Data/Hora</th>
                        <th>Itens</th>
                        <th>Pagamento</th>
                        <th>NIF</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <span>📋</span>
                                <p>Nenhuma venda encontrada</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td><span class="sale-id">#<?= $sale['sale_id'] ?></span></td>
                        <td>
                            <div><?= date('d/m/Y', strtotime($sale['sale_date'])) ?></div>
                            <small style="color: var(--text-muted);"><?= date('H:i:s', strtotime($sale['sale_date'])) ?></small>
                        </td>
                        <td><span class="badge badge-primary"><?= $sale['total_itens'] ?> itens</span></td>
                        <td>
                            <?php
                            $icon = match($sale['payment_method'] ?? '') {
                                'Dinheiro' => '💵',
                                'Cartão' => '💳',
                                'MBWay' => '📱',
                                default => '💰'
                            };
                            ?>
                            <span class="badge badge-muted"><?= $icon ?> <?= $sale['payment_method'] ?? 'N/A' ?></span>
                        </td>
                        <td><?= $sale['nif'] ?: '-' ?></td>
                        <td><span class="total-value money-positive">€<?= number_format($sale['total'], 2) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&date=<?= urlencode($dateFilter) ?>">← Anterior</a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <a href="?page=<?= $i ?>&date=<?= urlencode($dateFilter) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>&date=<?= urlencode($dateFilter) ?>">Próxima →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Theme Toggle -->
    <button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema">
        <span class="icon-moon">🌙</span>
        <span class="icon-sun">☀️</span>
    </button>
    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('pap-theme', next);
        }
    </script>
    <script src="/assets/js/master-ui.js?v=<?= time() ?>"></script>
</body>
</html>
