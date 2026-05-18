<?php
/**
 * HEADER PREMIUM - PAP MARKET
 * Design moderno com sidebar e navegação elegante
 */

// Garantir que a sessão está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Verificar stock baixo
if (!isset($_SESSION['stock_check_done'])) {
    check_and_send_low_stock_email();
    $_SESSION['stock_check_done'] = true;
}

// Obter alertas não lidos
$unread_alerts = function_exists('get_unread_alerts_count') ? get_unread_alerts_count() : 0;

// Obter utilizador e loja atual
$current_user = $auth->is_authenticated() ? $auth->get_current_user() : null;
$all_stores = get_all_stores();
$current_store = get_current_store();

// Obter contagem de stock baixo
$pdo = db_connect();
$current_store_id = get_current_store_id();
$low_stock_count = 0;
$unread_alerts = 0;
if ($pdo) {
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE store_id = ? AND stock <= min_stock AND active = 1');
        $stmt->execute([$current_store_id]);
        $low_stock_count = $stmt->fetchColumn();
    } catch (\Throwable $e) {
        $low_stock_count = 0;
    }
}

// Determinar página atual para destacar no menu
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

function isActive($pages) {
    global $current_page, $current_dir;
    if (is_array($pages)) {
        return in_array($current_page, $pages) || in_array($current_dir, $pages) ? 'active' : '';
    }
    return $current_page === $pages || $current_dir === $pages ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'PAP Market' ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400;1,700&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Design System v3 -->
    <link rel="stylesheet" href="/assets/css/premium.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/master-ui.css?v=<?= time() ?>">
    
    <!-- Theme Script - Prevent Flash -->
    <script>
        (function() {
            const theme = localStorage.getItem('pap-theme') || 'dark';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    
    <!-- Chart.js for pages that need it -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <?php if (isset($extra_css)): ?>
    <style><?= $extra_css ?></style>
    <?php endif; ?>
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <aside class="app-sidebar">
            <div class="sidebar-header">
                <a href="/" class="sidebar-logo">
                    <div class="sidebar-logo-icon">🛒</div>
                    <span class="sidebar-logo-text">PAP Market</span>
                </a>
            </div>

            <nav class="sidebar-nav">
                <!-- Principal -->
                <div class="nav-section">
                    <div class="nav-section-title">Principal</div>
                    <a href="/index.php" class="nav-item <?= isActive('index') ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                        </svg>
                        Dashboard
                    </a>
                    <a href="/CAIXA/" class="nav-item <?= isActive('CAIXA') ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        PDV / Caixa
                    </a>
                </div>

                <!-- Vendas -->
                <div class="nav-section">
                    <div class="nav-section-title">Vendas</div>
                    <a href="/modules/recibos.php" class="nav-item <?= isActive('recibos') ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Recibos
                    </a>
                    <a href="/modules/devolucoes.php" class="nav-item <?= isActive('devolucoes') ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                        Devoluções
                    </a>
                    <a href="/modules/promocoes.php" class="nav-item <?= isActive('promocoes') ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"></path>
                        </svg>
                        Promoções
                    </a>
                </div>

                <!-- Inventário -->
                <div class="nav-section">
                    <div class="nav-section-title">Inventário</div>
                    <a href="/modules/produtos.php" class="nav-item <?= isActive('produtos') ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        Produtos
                    </a>
                    <a href="/CAIXA/qrcodes_produtos.php" target="_blank" class="nav-item">
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                        </svg>
                        QR Produtos
                    </a>
                    <a href="/modules/stock.php" class="nav-item <?= isActive('stock') ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                        </svg>
                        Stock
                        <?php if ($low_stock_count > 0): ?>
                        <span class="nav-badge"><?= $low_stock_count ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="/modules/encomendas.php" class="nav-item <?= isActive('encomendas') ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                        </svg>
                        Encomendas
                    </a>
                    <a href="/modules/movimentos_stock.php" class="nav-item <?= isActive('movimentos_stock') ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path></svg>
                        Movimentos
                    </a>
                    <a href="/modules/validades.php" class="nav-item <?= isActive('validades') ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Validades
                    </a>
                    <a href="/modules/fornecedores.php" class="nav-item <?= isActive('fornecedores') ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                        Fornecedores
                    </a>
                </div>

                <!-- Relatórios -->
                <div class="nav-section">
                    <div class="nav-section-title">Relatórios</div>
                    <a href="/modules/relatorios.php" class="nav-item <?= isActive('relatorios') ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Relatórios
                    </a>
                    <a href="/modules/analytics.php" class="nav-item <?= isActive('analytics') ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                        Analytics
                    </a>
                </div>

                <!-- Equipa -->
                <div class="nav-section">
                    <div class="nav-section-title">Equipa</div>
                    <a href="/admin/rh/equipa.php" class="nav-item <?= isActive(['rh', 'equipa']) ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        Equipa
                    </a>
                    <a href="/modules/customers.php" class="nav-item <?= isActive('customers') ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        Clientes
                    </a>
                    <a href="/modules/ponto.php" class="nav-item <?= isActive('ponto') ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Controlo de Ponto
                    </a>
                    <a href="/admin/vacation/calendario.php" class="nav-item <?= isActive('calendario') ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Mapa de Férias
                    </a>
                </div>

                <!-- Sistema -->
                <div class="nav-section">
                    <div class="nav-section-title">Sistema</div>
                    <a href="/modules/alerts.php" class="nav-item <?= isActive('alerts') ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        Alertas
                        <?php if ($unread_alerts > 0): ?>
                        <span class="nav-badge"><?= $unread_alerts ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="/modules/configuracoes.php" class="nav-item <?= isActive('configuracoes') ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Configurações
                    </a>
                    <a href="/modules/backup.php" class="nav-item <?= isActive('backup') ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path></svg>
                        Backup BD
                    </a>
                    <a href="/modules/db_status.php" class="nav-item <?= isActive('db_status') ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Estado BD
                    </a>
                </div>
            </nav>

            <div class="sidebar-footer">
                <a href="/download.php" class="sidebar-download-btn" title="Download da aplicação">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    Download App
                </a>
                <div class="user-menu-box">
                    <div class="user-avatar">
                        <?= $current_user ? strtoupper(substr($current_user['name'], 0, 1)) : 'U' ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?= $current_user ? htmlspecialchars($current_user['name']) : 'Utilizador' ?></div>
                        <div class="user-role"><?= $current_user ? htmlspecialchars($current_user['role_name'] ?? 'Admin') : 'Admin' ?></div>
                    </div>
                    <a href="/logout.php" title="Sair" style="color: var(--text-muted); display: flex;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="app-main">
            <header class="app-header">
                <div class="header-left">
                    <h1 class="page-title"><?= $page_title ?? 'Dashboard' ?></h1>
                </div>
                <div class="header-right">
                    <?php if (!empty($all_stores) && count($all_stores) > 1): ?>
                    <select class="form-select" style="width: auto;" onchange="changeStore(this.value)">
                        <?php foreach ($all_stores as $store): ?>
                        <option value="<?= $store['id'] ?>" <?= ($current_store && $store['id'] == $current_store['id']) ? 'selected' : '' ?>>
                            📍 <?= htmlspecialchars($store['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    
                    <a href="/modules/alerts.php" class="btn btn-icon" title="Alertas" style="position: relative;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <?php if ($unread_alerts > 0): ?>
                        <span style="position: absolute; top: -4px; right: -4px; width: 18px; height: 18px; background: var(--danger); border-radius: 50%; font-size: 10px; font-weight: 600; display: flex; align-items: center; justify-content: center;"><?= $unread_alerts ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <a href="/CAIXA/" class="btn btn-primary">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        PDV / Caixa
                    </a>
                </div>
            </header>

            <div class="app-content">
                <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?> fade-in">
                    <div class="alert-content">
                        <div class="alert-message"><?= htmlspecialchars($_SESSION['flash_message']) ?></div>
                    </div>
                </div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
                <?php endif; ?>