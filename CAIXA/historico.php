<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../config/database.php';

$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit  = 25;
$offset = ($page - 1) * $limit;

$dateFilter   = $_GET['date']   ?? '';
$searchFilter = $_GET['search'] ?? '';

$whereConditions = [];
$params = [];
if ($dateFilter)   { $whereConditions[] = "DATE(s.sale_date) = ?"; $params[] = $dateFilter; }
if ($searchFilter) { $whereConditions[] = "s.id = ?";              $params[] = intval($searchFilter); }
$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM sales s $whereClause");
$stmt->execute($params);
$totalSales = $stmt->fetchColumn();
$totalPages = ceil($totalSales / $limit);

$stmt = $pdo->prepare("
    SELECT s.id as sale_id, s.total, s.sale_date, s.payment_method, s.nif,
           (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as total_itens
    FROM sales s $whereClause ORDER BY s.sale_date DESC LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$todayStats = $pdo->query("
    SELECT COUNT(*) as num_vendas, COALESCE(SUM(total),0) as total_vendas
    FROM sales WHERE DATE(sale_date) = CURDATE()
")->fetch();

$monthStats = $pdo->query("
    SELECT COUNT(*) as num_vendas, COALESCE(SUM(total),0) as total_vendas
    FROM sales WHERE MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE())
")->fetch();
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Vendas</title>
    <link rel="stylesheet" href="/assets/css/design-system.css?v=<?= time() ?>">
    <script>(function(){const t=localStorage.getItem('pap-theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
    <style>
        /* ── Reset & base ─────────────────────────────────────────────────── */
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,sans-serif;
            background:var(--bg-primary);min-height:100vh;
            color:var(--text-primary);padding:28px 24px;
            font-size:14px;line-height:1.5;
        }
        .container{max-width:1400px;margin:0 auto}

        /* ── Header ───────────────────────────────────────────────────────── */
        .header{
            display:flex;justify-content:space-between;align-items:center;
            margin-bottom:28px;padding-bottom:20px;border-bottom:1px solid var(--border);
        }
        .header h1{
            font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;
            font-size:26px;font-weight:700;letter-spacing:-.01em;
            display:flex;align-items:center;gap:12px;
        }
        .back-btn{
            display:inline-flex;align-items:center;gap:7px;
            padding:9px 18px;
            background:var(--bg-secondary);border:1px solid var(--border);
            border-radius:8px;color:var(--text-secondary);
            text-decoration:none;font-size:13px;font-weight:500;transition:all .18s;
        }
        .back-btn:hover{background:var(--bg-hover);border-color:var(--border-light);color:var(--text-primary)}

        /* ── Stat cards ───────────────────────────────────────────────────── */
        .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
        .stat-card{
            background:var(--bg-secondary);border:1px solid var(--border);
            border-radius:12px;padding:18px 20px;
            position:relative;overflow:hidden;
        }
        .stat-card::before{
            content:'';position:absolute;top:0;left:0;
            width:3px;height:100%;border-radius:3px 0 0 3px;
            background:var(--border);
        }
        .stat-card.card-green::before{background:var(--success)}
        .stat-card.card-neutral::before{background:var(--border-light)}
        .stat-icon{
            width:36px;height:36px;border-radius:8px;
            display:flex;align-items:center;justify-content:center;
            margin-bottom:14px;background:var(--bg-tertiary);
        }
        .stat-value{
            font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;
            font-size:22px;font-weight:700;margin-bottom:3px;
            letter-spacing:-.01em;
        }
        .stat-value.v-green{color:var(--success)}
        .stat-label{
            font-size:11px;color:var(--text-muted);
            text-transform:uppercase;letter-spacing:.5px;font-weight:500;
        }

        /* ── Filters ──────────────────────────────────────────────────────── */
        .filters{
            background:var(--bg-secondary);border:1px solid var(--border);
            border-radius:12px;padding:18px 20px;
            margin-bottom:20px;display:flex;gap:14px;align-items:flex-end;
        }
        .form-group{flex:1}
        .form-group label{
            display:block;font-size:11px;color:var(--text-muted);
            margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;font-weight:600;
        }
        .form-group input{
            width:100%;padding:10px 14px;
            background:var(--bg-tertiary);border:1px solid var(--border);
            border-radius:8px;color:var(--text-primary);
            font-size:13.5px;font-family:inherit;transition:border-color .18s;
        }
        .form-group input:focus{
            outline:none;border-color:var(--border-focus);
            box-shadow:0 0 0 3px var(--accent-ring);
        }
        .btn{
            display:inline-flex;align-items:center;gap:7px;
            padding:10px 20px;border-radius:8px;
            font-size:13px;font-weight:600;cursor:pointer;
            font-family:inherit;white-space:nowrap;
            text-decoration:none;
        }

        /* ── Table ────────────────────────────────────────────────────────── */
        .table-card{
            background:var(--bg-secondary);border:1px solid var(--border);
            border-radius:12px;overflow:hidden;
        }
        .table-header{
            display:flex;justify-content:space-between;align-items:center;
            padding:18px 22px;border-bottom:1px solid var(--border);
        }
        .table-header h2{
            font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;
            font-size:17px;font-weight:700;
        }
        .record-count{
            font-size:12px;color:var(--text-muted);
            background:var(--bg-tertiary);border:1px solid var(--border);
            padding:4px 10px;border-radius:6px;font-weight:500;
        }
        table{width:100%;border-collapse:collapse}
        th,td{padding:12px 22px;text-align:left;border-bottom:1px solid var(--border)}
        th{
            font-size:10.5px;font-weight:600;color:var(--text-muted);
            text-transform:uppercase;letter-spacing:.5px;background:var(--bg-tertiary);
        }
        tr:last-child td{border-bottom:none}
        tr:hover td{background:var(--bg-hover)}

        /* ── Sale ID ──────────────────────────────────────────────────────── */
        .sale-id{
            font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;
            font-size:15px;font-weight:700;color:var(--text-primary);
            letter-spacing:-.01em;
        }

        /* ── Badges ───────────────────────────────────────────────────────── */
        .badge{
            display:inline-flex;align-items:center;gap:5px;
            padding:4px 10px;border-radius:6px;
            font-size:11.5px;font-weight:600;
        }
        .badge-items{background:var(--bg-tertiary);color:var(--text-secondary)}
        .badge-pay{background:var(--bg-tertiary);color:var(--text-muted)}
        .badge-pay svg{opacity:.7}

        /* ── Money ────────────────────────────────────────────────────────── */
        .amount{
            font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;
            font-size:15px;font-weight:700;color:var(--success);
            letter-spacing:-.01em;font-variant-numeric:tabular-nums;
        }

        /* ── Pagination ───────────────────────────────────────────────────── */
        .pagination{display:flex;justify-content:center;gap:6px;margin-top:20px}
        .pagination a{
            display:inline-flex;align-items:center;gap:5px;
            padding:8px 14px;
            background:var(--bg-secondary);border:1px solid var(--border);
            border-radius:7px;color:var(--text-secondary);
            text-decoration:none;font-size:13px;font-weight:500;transition:all .18s;
        }
        .pagination a:hover,.pagination a.active{
            background:var(--accent);border-color:var(--accent);color:#fff;
        }

        /* ── Empty state ──────────────────────────────────────────────────── */
        .empty-state{text-align:center;padding:64px 24px;color:var(--text-muted)}
        .empty-state svg{opacity:.2;margin-bottom:14px}
        .empty-state p{font-size:13.5px;margin-top:6px}

        /* ── NIF ──────────────────────────────────────────────────────────── */
        .nif-value{font-size:13px;color:var(--text-secondary);font-variant-numeric:tabular-nums}
        .nif-dash{color:var(--text-disabled)}

        /* ── Light mode ───────────────────────────────────────────────────── */
        [data-theme="light"] body{background:var(--bg-primary);color:var(--text-primary)}
        [data-theme="light"] .stat-card{background:var(--bg-secondary);border-color:var(--border);box-shadow:var(--shadow)}
        [data-theme="light"] .stat-icon{background:var(--bg-tertiary)}
        [data-theme="light"] .filters{background:var(--bg-secondary);border-color:var(--border);box-shadow:var(--shadow-xs)}
        [data-theme="light"] .form-group input{background:var(--bg-secondary);border-color:var(--border);color:var(--text-primary)}
        [data-theme="light"] .table-card{background:var(--bg-secondary);border-color:var(--border);box-shadow:var(--shadow)}
        [data-theme="light"] th{background:var(--bg-tertiary);color:var(--text-muted)}
        [data-theme="light"] td{border-bottom-color:var(--border);color:var(--text-primary)}
        [data-theme="light"] tr:hover td{background:var(--bg-hover);color:var(--text-primary)}
        [data-theme="light"] .badge-items,[data-theme="light"] .badge-pay{background:var(--bg-tertiary)}
        [data-theme="light"] .record-count{background:var(--bg-tertiary);border-color:var(--border)}
        [data-theme="light"] .pagination a{background:var(--bg-secondary);border-color:var(--border);color:var(--text-secondary)}
        [data-theme="light"] .pagination a:hover,[data-theme="light"] .pagination a.active{background:var(--accent);border-color:var(--accent);color:#fff}
        [data-theme="light"] .back-btn{background:var(--bg-secondary);border-color:var(--border);color:var(--text-secondary)}
        [data-theme="light"] .btn-secondary:hover{background:var(--bg-hover);color:var(--text-primary)}
    </style>
</head>
<body>
<div class="container">

    <div class="header">
        <h1>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="opacity:.7">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
                <polyline points="10 9 9 9 8 9"/>
            </svg>
            Histórico de Vendas
        </h1>
        <a href="/CAIXA/" class="back-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M5 12l7 7M5 12l7-7"/>
            </svg>
            Voltar ao POS
        </a>
    </div>

    <div class="stats-grid">
        <div class="stat-card card-neutral">
            <div class="stat-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity:.55">
                    <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/>
                    <line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/>
                    <line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
                </svg>
            </div>
            <div class="stat-value"><?= number_format($totalSales) ?></div>
            <div class="stat-label">Total de Vendas</div>
        </div>
        <div class="stat-card card-neutral">
            <div class="stat-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity:.55">
                    <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
            </div>
            <div class="stat-value"><?= number_format($todayStats['num_vendas']) ?></div>
            <div class="stat-label">Vendas Hoje</div>
        </div>
        <div class="stat-card card-green">
            <div class="stat-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"/>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
            </div>
            <div class="stat-value v-green">€<?= number_format($todayStats['total_vendas'], 2) ?></div>
            <div class="stat-label">Receita Hoje</div>
        </div>
        <div class="stat-card card-green">
            <div class="stat-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                </svg>
            </div>
            <div class="stat-value v-green">€<?= number_format($monthStats['total_vendas'], 2) ?></div>
            <div class="stat-label">Receita do Mês</div>
        </div>
    </div>

    <form class="filters" method="get">
        <div class="form-group">
            <label>Data</label>
            <input type="date" name="date" value="<?= htmlspecialchars($dateFilter) ?>">
        </div>
        <div class="form-group">
            <label>N.º Venda</label>
            <input type="text" name="search" placeholder="ID da venda…" value="<?= htmlspecialchars($searchFilter) ?>">
        </div>
        <button type="submit" class="btn btn-primary">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            Filtrar
        </button>
        <a href="historico.php" class="btn btn-secondary">Limpar</a>
    </form>

    <div class="table-card">
        <div class="table-header">
            <h2>Vendas</h2>
            <span class="record-count"><?= count($sales) ?> de <?= $totalSales ?> registos</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>N.º</th>
                    <th>Data / Hora</th>
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
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" display="block" style="margin:0 auto">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                            </svg>
                            <p>Nenhuma venda encontrada</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($sales as $sale):
                    $payMethod = $sale['payment_method'] ?? 'N/A';
                    $payIcon = match($payMethod) {
                        'Dinheiro' => '<path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
                        'Cartão'   => '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
                        'MBWay'    => '<rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/>',
                        default    => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
                    };
                ?>
                <tr>
                    <td><span class="sale-id">#<?= $sale['sale_id'] ?></span></td>
                    <td>
                        <div style="font-size:13.5px;font-weight:600"><?= date('d/m/Y', strtotime($sale['sale_date'])) ?></div>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:2px"><?= date('H:i', strtotime($sale['sale_date'])) ?></div>
                    </td>
                    <td>
                        <span class="badge badge-items"><?= $sale['total_itens'] ?> <?= $sale['total_itens'] == 1 ? 'item' : 'itens' ?></span>
                    </td>
                    <td>
                        <span class="badge badge-pay">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $payIcon ?></svg>
                            <?= htmlspecialchars($payMethod) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($sale['nif']): ?>
                            <span class="nif-value"><?= htmlspecialchars($sale['nif']) ?></span>
                        <?php else: ?>
                            <span class="nif-dash">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="amount">€<?= number_format($sale['total'], 2) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>&date=<?= urlencode($dateFilter) ?>">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
            Anterior
        </a>
        <?php endif; ?>

        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
        <a href="?page=<?= $i ?>&date=<?= urlencode($dateFilter) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>&date=<?= urlencode($dateFilter) ?>">
            Seguinte
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema">
    <span class="icon-moon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></span>
    <span class="icon-sun"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg></span>
</button>
<script>
function toggleTheme(){
    const h=document.documentElement;
    const n=h.getAttribute('data-theme')==='dark'?'light':'dark';
    h.setAttribute('data-theme',n);
    localStorage.setItem('pap-theme',n);
}
</script>
<script src="/assets/js/master-ui.js?v=<?= time() ?>"></script>
</body>
</html>
