<?php
// includes/header.php
session_start();
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
<header>
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 20px; background-color: #f8f9fa; border-bottom: 1px solid #dee2e6;">
        <nav class="main-nav" style="flex: 1;">
            <a href="/index.php">Dashboard</a>
            <a href="/modules/produtos.php">Produtos</a>
            <a href="/modules/pricing.php">💰 Preços</a>
            <a href="/modules/fornecedores.php">Fornecedores</a>
            <a href="/modules/stock.php">Stock</a>
            <a href="/modules/vendas.php">Vendas</a>
            <a href="/modules/encomendas.php">Encomendas</a>
            <a href="/modules/alerts.php">Alertas<?php echo $unread_alerts > 0 ? " ($unread_alerts)" : ''; ?></a>
            <a href="/modules/auditoria.php">Auditoria</a>
            <a href="/modules/db_status.php">DB Status</a>
            <a href="/modules/rh.php">RH</a>
            <a href="/dashboard/dashboard_teste.php">Relatórios</a>
        </nav>
        
        <?php if ($current_user): ?>
        <div style="display: flex; align-items: center; gap: 15px; border-left: 1px solid #dee2e6; padding-left: 20px;">
            <div style="text-align: right;">
                <div style="font-weight: 600; color: #333;">
                    👤 <?php echo htmlspecialchars($current_user['name']); ?>
                </div>
                <div style="font-size: 12px; color: #666;">
                    <?php echo htmlspecialchars($current_user['role_name']); ?>
                </div>
            </div>
            <div style="display: flex; gap: 8px;">
                <a href="/login.php" style="padding: 6px 12px; background-color: #007bff; color: white; border-radius: 4px; font-size: 12px; text-decoration: none; border: none; cursor: pointer;">
                    🔄 Mudar Conta
                </a>
                <a href="/logout.php" style="padding: 6px 12px; background-color: #dc3545; color: white; border-radius: 4px; font-size: 12px; text-decoration: none; border: none; cursor: pointer;">
                    🚪 Sair
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</header>
<main class="container">

