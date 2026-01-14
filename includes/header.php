<?php
// includes/header.php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Get unread alerts count safely (works before migration)
$unread_alerts = 0;
if (function_exists('get_unread_alerts_count')) {
    $unread_alerts = get_unread_alerts_count();
}

// Get current user if authenticated
$current_user = null;
if ($auth->is_authenticated()) {
    $current_user = $auth->get_current_user();
}
?>
<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PAP Supermercado</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
</head>
<body>
<header style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); padding: 0; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
    <!-- Top bar com user info -->
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; border-bottom: 1px solid rgba(255,255,255,0.1);">
        <div style="color: white; font-weight: 600; font-size: 18px;">
            🏪 PAP Supermercado
        </div>
        
        <?php if ($current_user): ?>
        <div style="display: flex; align-items: center; gap: 20px;">
            <div style="text-align: right; color: white;">
                <div style="font-weight: 600;">
                    👤 <?php echo htmlspecialchars($current_user['name']); ?>
                </div>
                <div style="font-size: 12px; color: #bbb;">
                    <?php echo htmlspecialchars($current_user['role_name']); ?>
                </div>
            </div>
            <div style="display: flex; gap: 8px;">
                <a href="/login.php" style="padding: 6px 12px; background-color: #0066ff; color: white; border-radius: 4px; font-size: 12px; text-decoration: none; border: none; cursor: pointer; transition: all 0.3s;">
                    🔄 Mudar Conta
                </a>
                <a href="/logout.php" style="padding: 6px 12px; background-color: #cc3333; color: white; border-radius: 4px; font-size: 12px; text-decoration: none; border: none; cursor: pointer; transition: all 0.3s;">
                    🚪 Sair
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Navigation Menu with Accordion -->
    <nav class="main-nav" style="padding: 0; background: #0f0f1e;">
        <a href="/index.php" style="color: white; padding: 12px 20px; text-decoration: none; display: inline-block; transition: all 0.3s;">📊 Dashboard</a>
        
        <!-- Vendas & Encomendas Group -->
        <div class="nav-group" style="display: inline-block; position: relative;">
            <button class="nav-toggle" onclick="toggleMenu(this)" style="color: white; padding: 12px 20px; background: none; border: none; cursor: pointer; font-size: 14px; transition: all 0.3s; display: flex; align-items: center; gap: 5px;">
                💰 Vendas <span style="display: inline-block; transition: transform 0.3s;">▼</span>
            </button>
            <div class="nav-submenu" style="display: none; position: absolute; top: 100%; left: 0; background: #1a1a2e; min-width: 200px; border-top: 2px solid #0066ff; box-shadow: 0 4px 8px rgba(0,0,0,0.3);">
                <a href="/modules/vendas.php" style="color: white; padding: 10px 20px; text-decoration: none; display: block; transition: all 0.3s;" onmouseover="this.style.background='#0066ff'" onmouseout="this.style.background='transparent'">📈 Vendas</a>
                <a href="/modules/receipts.php" style="color: white; padding: 10px 20px; text-decoration: none; display: block; transition: all 0.3s;" onmouseover="this.style.background='#0066ff'" onmouseout="this.style.background='transparent'">🧾 Recibos</a>
                <a href="/modules/encomendas.php" style="color: white; padding: 10px 20px; text-decoration: none; display: block; transition: all 0.3s;" onmouseover="this.style.background='#0066ff'" onmouseout="this.style.background='transparent'">📦 Encomendas</a>
            </div>
        </div>
        
        <!-- Produtos & Fornecedores Group -->
        <div class="nav-group" style="display: inline-block; position: relative;">
            <button class="nav-toggle" onclick="toggleMenu(this)" style="color: white; padding: 12px 20px; background: none; border: none; cursor: pointer; font-size: 14px; transition: all 0.3s; display: flex; align-items: center; gap: 5px;">
                📦 Produtos <span style="display: inline-block; transition: transform 0.3s;">▼</span>
            </button>
            <div class="nav-submenu" style="display: none; position: absolute; top: 100%; left: 0; background: #1a1a2e; min-width: 200px; border-top: 2px solid #0066ff; box-shadow: 0 4px 8px rgba(0,0,0,0.3);">
                <a href="/modules/produtos.php" style="color: white; padding: 10px 20px; text-decoration: none; display: block; transition: all 0.3s;" onmouseover="this.style.background='#0066ff'" onmouseout="this.style.background='transparent'">🏷️ Produtos</a>
                <a href="/modules/pricing.php" style="color: white; padding: 10px 20px; text-decoration: none; display: block; transition: all 0.3s;" onmouseover="this.style.background='#0066ff'" onmouseout="this.style.background='transparent'">💰 Preços</a>
                <a href="/modules/fornecedores.php" style="color: white; padding: 10px 20px; text-decoration: none; display: block; transition: all 0.3s;" onmouseover="this.style.background='#0066ff'" onmouseout="this.style.background='transparent'">🤝 Fornecedores</a>
            </div>
        </div>
        
        <!-- Inventário & Stock Group -->
        <div class="nav-group" style="display: inline-block; position: relative;">
            <button class="nav-toggle" onclick="toggleMenu(this)" style="color: white; padding: 12px 20px; background: none; border: none; cursor: pointer; font-size: 14px; transition: all 0.3s; display: flex; align-items: center; gap: 5px;">
                📊 Stock <span style="display: inline-block; transition: transform 0.3s;">▼</span>
            </button>
            <div class="nav-submenu" style="display: none; position: absolute; top: 100%; left: 0; background: #1a1a2e; min-width: 200px; border-top: 2px solid #0066ff; box-shadow: 0 4px 8px rgba(0,0,0,0.3);">
                <a href="/modules/stock.php" style="color: white; padding: 10px 20px; text-decoration: none; display: block; transition: all 0.3s;" onmouseover="this.style.background='#0066ff'" onmouseout="this.style.background='transparent'">📦 Stock</a>
                <a href="/dashboard/dashboard_teste.php" style="color: white; padding: 10px 20px; text-decoration: none; display: block; transition: all 0.3s;" onmouseover="this.style.background='#0066ff'" onmouseout="this.style.background='transparent'">📊 Relatórios</a>
            </div>
        </div>
        
        <!-- RH Group -->
        <div class="nav-group" style="display: inline-block; position: relative;">
            <button class="nav-toggle" onclick="toggleMenu(this)" style="color: white; padding: 12px 20px; background: none; border: none; cursor: pointer; font-size: 14px; transition: all 0.3s; display: flex; align-items: center; gap: 5px;">
                👥 RH <span style="display: inline-block; transition: transform 0.3s;">▼</span>
            </button>
            <div class="nav-submenu" style="display: none; position: absolute; top: 100%; left: 0; background: #1a1a2e; min-width: 200px; border-top: 2px solid #0066ff; box-shadow: 0 4px 8px rgba(0,0,0,0.3);">
                <a href="/modules/rh.php" style="color: white; padding: 10px 20px; text-decoration: none; display: block; transition: all 0.3s;" onmouseover="this.style.background='#0066ff'" onmouseout="this.style.background='transparent'">👥 Equipa</a>
            </div>
        </div>
        
        <a href="/modules/alerts.php" style="color: white; padding: 12px 20px; text-decoration: none; display: inline-block; transition: all 0.3s;">🔔 Alertas<?php echo $unread_alerts > 0 ? " ($unread_alerts)" : ''; ?></a>
        <a href="/modules/auditoria.php" style="color: white; padding: 12px 20px; text-decoration: none; display: inline-block; transition: all 0.3s;">📋 Auditoria</a>
        <a href="/modules/db_status.php" style="color: white; padding: 12px 20px; text-decoration: none; display: inline-block; transition: all 0.3s;">🔧 DB Status</a>
    </nav>
</header>

<script>
function toggleMenu(button) {
    const submenu = button.nextElementSibling;
    const arrow = button.querySelector('span');
    
    // Close all other menus
    document.querySelectorAll('.nav-submenu').forEach(menu => {
        if (menu !== submenu) {
            menu.style.display = 'none';
            menu.previousElementSibling.querySelector('span').style.transform = 'rotate(0deg)';
        }
    });
    
    // Toggle current menu
    if (submenu.style.display === 'none') {
        submenu.style.display = 'block';
        arrow.style.transform = 'rotate(180deg)';
    } else {
        submenu.style.display = 'none';
        arrow.style.transform = 'rotate(0deg)';
    }
}
</script>
<main class="container">

