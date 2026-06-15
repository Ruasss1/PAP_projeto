<?php
/**
 * POS CAIXA - CLEAN UI
 * Consistente com o resto da aplicação
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$store_id = $_SESSION['store_id'] ?? 1;
$user_id = $_SESSION['user_id'] ?? 1;
$user_name = $_SESSION['username'] ?? 'Operador';

// Verificar turno
$shift = null;
$shift_open = false;
try {
    $stmt = $pdo->prepare("SELECT * FROM cash_register_shifts WHERE user_id = ? AND status = 'open' ORDER BY opened_at DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $shift = $stmt->fetch(PDO::FETCH_ASSOC);
    $shift_open = $shift ? true : false;
} catch (Exception $e) {}

// Lojas
$all_stores = [];
try {
    $stmt = $pdo->query("SELECT * FROM stores ORDER BY name");
    $all_stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$current_store = $all_stores[0] ?? ['id' => 1, 'name' => 'Loja Principal'];
foreach ($all_stores as $s) {
    if ($s['id'] == $store_id) {
        $current_store = $s;
        break;
    }
}

// Vendas suspensas
$suspended_sales = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM suspended_sales WHERE user_id = ? AND status = 'pending' ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $suspended_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Abrir turno via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'open_shift') {
    $opening = floatval($_POST['opening_balance'] ?? 100);
    $notes = $_POST['notes'] ?? '';
    $shift_number = 'T' . date('YmdHis');
    
    $stmt = $pdo->prepare("INSERT INTO cash_register_shifts (shift_number, user_id, opening_balance, notes, opened_at, status) VALUES (?, ?, ?, ?, NOW(), 'open')");
    $stmt->execute([$shift_number, $user_id, $opening, $notes]);
    
    header("Location: /CAIXA/");
    exit;
}

$page_title = 'POS - Caixa';

// Sidebar variables
$current_page = 'CAIXA';
$current_dir = 'CAIXA';
$low_stock_count = 0;
$unread_alerts = 0;
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE store_id = ? AND stock <= min_stock AND active = 1');
    $stmt->execute([$store_id]);
    $low_stock_count = $stmt->fetchColumn();
} catch (Exception $e) {}

function isActiveCaixa($pages) {
    return '';
}

// Fetch active promotions for discount panel
$active_promotions = [];
try {
    $stmt = $pdo->query("SELECT id, name, discount_type, discount_value FROM promotions WHERE active = 1 ORDER BY name");
    $active_promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch categories dynamically
$product_categories = [];
try {
    $stmt = $pdo->prepare("SELECT DISTINCT category FROM products WHERE active = 1 AND (store_id = ? OR store_id IS NULL) ORDER BY category");
    $stmt->execute([$store_id]);
    $product_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Weighted categories (sold by kg)
$weighted_categories = ['Frutas', 'Legumes', 'Carnes', 'Peixe', 'Congelados'];
$weighted_json = json_encode($weighted_categories);
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | SuperMarket</title>
    <link rel="icon" href="/assets/icons/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/design-system.css?v=<?= time() ?>">
    <script>(function(){const t=localStorage.getItem('pap-theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
    <style>
        * { box-sizing: border-box; }
        body { overflow: hidden; display: flex; height: 100vh; }

        /* SIDEBAR within POS — collapsible overlay */
        .app-sidebar {
            position: absolute;
            left: 0; top: 0;
            z-index: 200;
            height: 100vh;
            width: 52px;
            background: var(--bg-primary);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            transition: width 0.2s var(--ease);
            overflow: hidden;
        }
        .app-sidebar:hover { width: 220px; }
        body { position: relative; }
        .app-sidebar .sidebar-header { padding: 14px 8px; border-bottom: 1px solid var(--border); display: flex; align-items: center; overflow: hidden; flex-shrink: 0; }
        .app-sidebar .sidebar-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text-primary); white-space: nowrap; }
        .app-sidebar .sidebar-logo-icon { width: 32px; height: 32px; background: var(--bg-tertiary); border: 1px solid var(--border); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .app-sidebar .sidebar-logo-text { font-weight: 700; font-size: 14px; letter-spacing: -0.02em; opacity: 0; transition: opacity 0.2s; white-space: nowrap; }
        .app-sidebar:hover .sidebar-logo-text { opacity: 1; }
        .app-sidebar .sidebar-nav { padding: 10px 6px; flex: 1; overflow-y: auto; overflow-x: hidden; min-height: 0; }
        .app-sidebar .sidebar-nav::-webkit-scrollbar { width: 2px; }
        .app-sidebar .sidebar-nav::-webkit-scrollbar-thumb { background: var(--border); border-radius: 100px; }
        .app-sidebar .nav-section { margin-bottom: 14px; }
        .app-sidebar .nav-section-title { font-size: 9px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-muted); padding: 0 10px; margin-bottom: 4px; opacity: 0; transition: opacity 0.2s; white-space: nowrap; overflow: hidden; }
        .app-sidebar:hover .nav-section-title { opacity: 1; }
        .app-sidebar .nav-item { display: flex; align-items: center; gap: 10px; padding: 8px 10px; font-size: 13px; font-weight: 500; color: var(--text-secondary); border-radius: var(--radius); text-decoration: none; transition: background 0.2s, color 0.2s; margin-bottom: 1px; position: relative; white-space: nowrap; overflow: hidden; }
        .app-sidebar .nav-item:hover { background: var(--bg-secondary); color: var(--text-primary); }
        .app-sidebar .nav-item.active { background: var(--bg-tertiary); color: var(--text-primary); font-weight: 600; }
        .app-sidebar .nav-item.active::before { content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 2px; height: 14px; background: var(--text-primary); border-radius: 0 2px 2px 0; }
        .app-sidebar .nav-icon { width: 16px; height: 16px; flex-shrink: 0; opacity: 0.5; transition: opacity 0.2s; }
        .app-sidebar .nav-item:hover .nav-icon, .app-sidebar .nav-item.active .nav-icon { opacity: 1; }
        .app-sidebar .nav-item span { opacity: 0; transition: opacity 0.2s; }
        .app-sidebar:hover .nav-item span { opacity: 1; }
        .app-sidebar .nav-badge { background: var(--danger); color: #fff; border-radius: 100px; font-size: 9px; padding: 1px 5px; font-weight: 700; margin-left: auto; min-width: 16px; text-align: center; }
        .app-sidebar .sidebar-footer { padding: 8px 6px; border-top: 1px solid var(--border); overflow: hidden; flex-shrink: 0; }
        .app-sidebar .user-menu-box { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: var(--radius); background: var(--bg-secondary); transition: background 0.2s; cursor: pointer; overflow: hidden; }
        .app-sidebar .user-menu-box:hover { background: var(--bg-tertiary); }
        .app-sidebar .user-avatar { width: 28px; height: 28px; border-radius: 50%; background: var(--bg-tertiary); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0; }
        .app-sidebar .user-info { flex: 1; min-width: 0; opacity: 0; transition: opacity 0.2s; }
        .app-sidebar:hover .user-info { opacity: 1; }
        .app-sidebar .user-name { font-size: 12px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .app-sidebar .user-role { font-size: 10.5px; color: var(--text-muted); }

        /* POS WRAPPER */
        .pos-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }
        
        /* POS LAYOUT */
        .pos-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            flex: 1;
            overflow: hidden;
            background: var(--bg-primary);
        }

        /* LEFT PANEL */
        .pos-left {
            display: flex;
            flex-direction: column;
            padding: 16px 18px 16px 62px;
            overflow: hidden;
            position: relative;
            background: var(--bg-primary);
        }
        
        .pos-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 14px;
            flex-shrink: 0;
        }

        .pos-info {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
        }

        .pos-chip {
            padding: 5px 10px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 11.5px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }
        .pos-chip.accent {
            background: var(--bg-tertiary);
            border-color: var(--border-light);
            color: var(--text-primary);
        }
        .pos-chip select {
            background: transparent;
            border: none;
            color: inherit;
            font-size: 11.5px;
            cursor: pointer;
            outline: none;
        }

        .pos-actions { display: flex; gap: 6px; }
        .pos-actions a {
            padding: 7px 12px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            display: flex; align-items: center; gap: 5px;
            transition: background 0.2s, color 0.2s, border-color 0.2s;
        }
        .pos-actions a:hover {
            background: var(--bg-tertiary);
            border-color: var(--border-light);
            color: var(--text-primary);
        }
        
        /* SEARCH */
        .pos-search {
            display: flex;
            align-items: center;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: 12px;
            transition: border-color 0.2s;
            flex-shrink: 0;
        }
        .pos-search:focus-within { border-color: var(--border-focus); }
        .pos-search input {
            flex: 1;
            background: none;
            border: none;
            padding: 10px 12px;
            color: var(--text-primary);
            font-size: 13.5px;
            outline: none;
            font-family: inherit;
        }
        .pos-search input::placeholder { color: var(--text-muted); }

        /* CATEGORIES */
        .pos-categories {
            display: flex;
            gap: 6px;
            margin-bottom: 14px;
            overflow-x: auto;
            padding-bottom: 4px;
            flex-shrink: 0;
        }
        .pos-categories::-webkit-scrollbar { height: 3px; }
        .pos-categories::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
        
        .cat-btn {
            padding: 6px 14px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.2s, color 0.2s, border-color 0.2s;
            font-family: inherit;
        }
        .cat-btn:hover {
            background: var(--bg-tertiary);
            border-color: var(--border-light);
            color: var(--text-primary);
        }
        .cat-btn.active {
            background: var(--text-primary);
            border-color: var(--text-primary);
            color: var(--bg-primary);
            font-weight: 600;
        }
        
        /* PRODUCTS GRID */
        .pos-products {
            flex: 1;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
            overflow-y: auto;
            padding-right: 6px;
            padding-bottom: 56px;
        }
        .pos-products::-webkit-scrollbar { width: 4px; }
        .pos-products::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

        .product-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 14px 12px 12px;
            text-align: center;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            user-select: none;
        }
        .product-card:hover {
            background: var(--bg-tertiary);
            border-color: var(--border-light);
        }
        .product-card:active { opacity: .7; }
        .product-card.no-stock {
            opacity: 0.35;
            pointer-events: none;
        }

        .product-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .product-name {
            font-size: 12px;
            font-weight: 600;
            line-height: 1.35;
            height: 32px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            color: var(--text-primary);
            word-break: break-word;
            width: 100%;
        }

        .product-price {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.02em;
        }

        .product-stock {
            font-size: 10.5px;
            color: var(--text-muted);
            padding: 2px 8px;
            background: var(--bg-tertiary);
            border-radius: 4px;
            font-weight: 600;
        }
        .product-stock.low { color: var(--warning); background: var(--warning-subtle); }
        .product-stock.out { color: var(--danger); background: var(--danger-subtle); }
        
        /* RIGHT PANEL - CART */
        .pos-right {
            background: var(--bg-secondary);
            border-left: 1px solid var(--border);
            display: flex;
            flex-direction: column;
        }

        .cart-header {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .item-count {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-secondary);
            padding: 2px 8px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: var(--radius);
        }

        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 10px 12px;
            scrollbar-width: thin;
            scrollbar-color: var(--border) transparent;
        }
        .cart-items::-webkit-scrollbar { width: 3px; }
        .cart-items::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

        .empty-cart {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 20px;
            color: var(--text-muted);
            gap: 10px;
        }
        .empty-cart p { font-size: 13px; }
        
        .cart-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 10px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: 5px;
            transition: background 0.2s;
            animation: itemIn 0.15s ease both;
        }
        @keyframes itemIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
        .cart-item:hover { background: var(--bg-hover); }

        .cart-item-info { flex: 1; min-width: 0; }
        .cart-item-name { font-size: 12.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cart-item-price { font-size: 11px; color: var(--text-muted); margin-top: 1px; }

        .cart-item-qty {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .qty-btn {
            width: 24px; height: 24px;
            border: 1px solid var(--border);
            background: var(--bg-secondary);
            color: var(--text-secondary);
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            transition: background 0.2s, color 0.2s, border-color 0.2s;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .qty-btn:hover { background: var(--bg-hover); color: var(--text-primary); border-color: var(--border-light); }
        .qty-btn.danger:hover { background: var(--danger-subtle); border-color: var(--danger); color: var(--danger); }

        .cart-item-total {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-primary);
            min-width: 52px;
            text-align: right;
            letter-spacing: -0.3px;
            flex-shrink: 0;
        }
        
        /* CART SUMMARY */
        .cart-summary {
            padding: 12px 16px;
            background: var(--bg-tertiary);
            border-top: 1px solid var(--border);
            flex-shrink: 0;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .summary-row.total {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding-top: 10px;
            border-top: 1px solid var(--border);
            margin: 8px 0 0 0;
        }
        .summary-row.total > span:first-child {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--text-muted);
        }
        .summary-row.total > span:last-child {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.04em;
            line-height: 1;
        }
        
        /* CART ACTIONS */
        .cart-actions {
            padding: 10px 14px 14px;
            border-top: 1px solid var(--border);
            flex-shrink: 0;
        }

        .btn-pay {
            width: 100%;
            padding: 14px;
            background: var(--text-primary);
            border: none;
            border-radius: var(--radius);
            color: var(--bg-primary);
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.01em;
            cursor: pointer;
            margin-bottom: 8px;
            transition: opacity 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: inherit;
        }
        .btn-pay:disabled { opacity: 0.25; cursor: not-allowed; }
        .btn-pay:hover:not(:disabled) { opacity: 0.88; }
        .btn-pay:active:not(:disabled) { opacity: 0.7; }

        .secondary-btns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
        }

        .btn-sec {
            padding: 9px 8px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, color 0.2s, border-color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            font-family: inherit;
        }
        .btn-sec:hover { background: var(--bg-hover); color: var(--text-primary); border-color: var(--border-light); }
        .btn-sec.danger { color: var(--danger); border-color: var(--border); background: var(--bg-tertiary); }
        .btn-sec.danger:hover { background: var(--danger-subtle); border-color: var(--danger); color: var(--danger); }
        
        /* MODALS */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }

        .modal-box {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 24px;
            width: 100%;
            max-width: 440px;
        }
        .modal-box h2 {
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 6px;
        }
        .modal-box .subtitle {
            text-align: center;
            color: var(--text-muted);
            font-size: 13px;
            margin-bottom: 20px;
        }
        .modal-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px; height: 48px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin: 0 auto 14px;
            color: var(--text-secondary);
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }
        
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text-primary);
            font-size: 13.5px;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group textarea:focus { border-color: var(--border-focus); }

        .input-large {
            font-size: 28px !important;
            text-align: center;
            font-weight: 700;
            letter-spacing: -0.03em;
        }

        .btn-submit {
            width: 100%;
            padding: 13px;
            background: var(--success);
            border: none;
            border-radius: var(--radius);
            color: #000;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: opacity 0.2s;
            font-family: inherit;
            display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .btn-submit:hover { opacity: 0.88; }
        
        /* SHORTCUTS BAR */
        .shortcuts-bar {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            background: var(--bg-primary);
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: center;
            gap: 20px;
            padding: 8px 12px;
        }

        .shortcut {
            font-size: 11px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .shortcut kbd {
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: inherit;
            font-size: 10px;
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        /* PAYMENT MODAL */
        .modal-wide { max-width: 640px; width: 95%; }

        .pay-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border);
        }
        .pay-header-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.02em;
        }
        .pay-header-close {
            width: 30px; height: 30px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: transparent;
            color: var(--text-muted);
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.15s, color 0.15s;
        }
        .pay-header-close:hover { background: var(--bg-hover); color: var(--text-primary); }

        .payment-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            align-items: start;
        }

        /* Left col — order summary */
        .pay-summary-panel {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .pay-label {
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        .payment-items {
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            max-height: 200px;
            overflow-y: auto;
        }
        .payment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 9px 12px;
            font-size: 12.5px;
            border-bottom: 1px solid var(--border);
            gap: 8px;
        }
        .payment-item:last-child { border: none; }
        .payment-item-name {
            color: var(--text-secondary);
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .payment-item-qty {
            font-size: 11px;
            color: var(--text-muted);
            background: var(--bg-hover);
            border-radius: 4px;
            padding: 1px 6px;
            flex-shrink: 0;
        }
        .payment-item-price {
            font-weight: 600;
            color: var(--text-primary);
            flex-shrink: 0;
            font-variant-numeric: tabular-nums;
        }

        .payment-total {
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 4px;
        }
        .payment-total-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--text-muted);
        }
        .payment-total-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.04em;
            line-height: 1;
        }

        /* Right col — input */
        .pay-input-panel {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .input-large {
            width: 100%;
            padding: 12px 16px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text-primary);
            font-size: 32px;
            font-weight: 700;
            text-align: center;
            letter-spacing: -0.04em;
            transition: border-color 0.2s;
            outline: none;
            font-family: inherit;
        }
        .input-large:focus { border-color: var(--border-focus); }
        .input-large::placeholder { color: var(--text-muted); font-size: 20px; font-weight: 400; }

        .quick-amounts {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
        }
        .quick-btn {
            padding: 9px 4px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, color 0.2s, border-color 0.2s;
            text-align: center;
            font-family: inherit;
        }
        .quick-btn:hover {
            background: var(--bg-hover);
            border-color: var(--border-light);
            color: var(--text-primary);
        }
        .quick-btn.exact {
            background: var(--success-subtle);
            border-color: var(--success);
            color: var(--success);
        }

        .change-box {
            background: var(--success-subtle);
            border: 1px solid var(--success);
            border-radius: 10px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .change-box .label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--success);
        }
        .change-box .value {
            font-size: 26px;
            font-weight: 800;
            color: var(--success);
            letter-spacing: -0.04em;
            line-height: 1;
        }

        .error-box {
            background: var(--danger-subtle);
            border: 1px solid var(--danger);
            color: var(--danger);
            padding: 10px 14px;
            border-radius: 8px;
            text-align: center;
            font-size: 12.5px;
            font-weight: 600;
            display: none;
        }
        
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        
        .modal-actions button {
            flex: 1;
            padding: 12px;
            border-radius: var(--radius);
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-family: inherit;
        }

        .btn-cancel {
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            transition: background 0.2s, color 0.2s;
        }
        .btn-cancel:hover { background: var(--bg-hover); color: var(--text-primary); }

        .btn-confirm {
            background: var(--text-primary);
            color: var(--bg-primary);
            transition: opacity 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-confirm:hover:not(:disabled) { opacity: 0.88; }
        .btn-confirm:disabled { opacity: 0.3; cursor: not-allowed; }

        /* Loading spinner */
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner {
            width: 15px; height: 15px;
            border: 2px solid rgba(0,0,0,0.15);
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            flex-shrink: 0;
        }

        /* DISCOUNT SECTION in cart */
        .cart-discount-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 12px;
        }
        .discount-badge {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-secondary);
            padding: 2px 8px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: var(--radius);
        }
        .btn-discount {
            padding: 4px 10px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 11px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
            font-family: inherit;
        }
        .btn-discount:hover { background: var(--bg-hover); color: var(--text-primary); }
        .btn-discount.active { color: var(--text-primary); border-color: var(--border-light); }

        /* Discount Modal */
        .promo-list { display: flex; flex-direction: column; gap: 6px; margin: 14px 0; }
        .promo-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s;
        }
        .promo-item:hover { background: var(--bg-hover); border-color: var(--border-light); }
        .promo-item.selected { border-color: var(--text-primary); background: var(--bg-secondary); }
        .promo-name { font-size: 13px; font-weight: 600; }
        .promo-value { font-size: 13px; font-weight: 700; color: var(--text-primary); }

        /* ── Light mode — all driven by CSS variables, no overrides needed ── */
        /* Active category pill needs flip in light mode */
        [data-theme="light"] .cat-btn.active { background: var(--text-primary); border-color: var(--text-primary); color: var(--bg-primary); }

        /* ── Cart item classes matching JS template ───────────────────────── */
        .item-info { flex: 1; min-width: 0; }
        .item-name {
            font-size: 12.5px; font-weight: 600;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            color: var(--text-primary);
        }
        .item-price { font-size: 11px; color: var(--text-muted); margin-top: 1px; }
        .item-controls { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
        .qty-display {
            font-size: 13px; font-weight: 700;
            min-width: 22px; text-align: center;
            color: var(--text-primary);
        }
        .item-subtotal {
            font-size: 12.5px; font-weight: 700;
            min-width: 50px; text-align: right;
            color: var(--text-primary); letter-spacing: -0.3px; flex-shrink: 0;
        }
        .remove-btn {
            width: 24px; height: 24px;
            background: none; border: 1px solid transparent;
            border-radius: 6px; cursor: pointer;
            color: var(--text-muted);
            display: flex; align-items: center; justify-content: center;
            transition: background 0.2s, border-color 0.2s, color 0.2s; flex-shrink: 0;
        }
        .remove-btn:hover { background: var(--danger-subtle); border-color: var(--danger); color: var(--danger); }
    </style>
</head>
<body>
    <?php if (!$shift_open): ?>
    <!-- MODAL ABRIR TURNO -->
    <div class="modal-overlay active" id="shiftModal">
        <div class="modal-box">
            <div class="modal-icon"><svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24" ><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg></div>
            <h2>Iniciar Turno</h2>
            <p class="subtitle">Configure o saldo inicial para começar</p>
            
            <form method="post">
                <input type="hidden" name="action" value="open_shift">
                <div class="form-group">
                    <label>Saldo Inicial (€)</label>
                    <input type="number" name="opening_balance" step="0.01" value="100.00" class="input-large" required>
                </div>
                <div class="form-group">
                    <label>Notas (opcional)</label>
                    <input type="text" name="notes" placeholder="Observações...">
                </div>
                <button type="submit" class="btn-submit"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Iniciar Turno</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- SIDEBAR -->
    <aside class="app-sidebar">
        <div class="sidebar-header">
            <a href="/" class="sidebar-logo">
                <div class="sidebar-logo-icon">
                        <svg width="20" height="20" viewBox="0 0 512 512" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <g stroke="currentColor" stroke-width="34" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M118 306C158 180 231 151 256 240C282 332 355 342 406 216"/>
                                <path d="M140 222C183 273 225 286 256 244C290 199 333 190 382 230"/>
                            </g>
                        </svg>
                    </div>
                <span class="sidebar-logo-text">Mercantec</span>
            </a>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Principal</div>
                <a href="/index.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    <span>Dashboard</span>
                </a>
                <a href="/CAIXA/" class="nav-item active">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    <span>PDV / Caixa</span>
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Vendas</div>
                <a href="/modules/recibos.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Recibos</span>
                </a>
                <a href="/modules/devolucoes.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                    <span>Devoluções</span>
                </a>
                <a href="/modules/promocoes.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
                    <span>Promoções</span>
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Inventário</div>
                <a href="/modules/produtos.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    <span>Produtos</span>
                </a>
                <a href="/modules/stock.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                    <span>Stock</span>
                    <?php if ($low_stock_count > 0): ?>
                    <span class="nav-badge"><?= $low_stock_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="/modules/encomendas.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <span>Encomendas</span>
                </a>
                <a href="/modules/validades.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>Validades</span>
                </a>
                <a href="/modules/fornecedores.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span>Fornecedores</span>
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Relatórios</div>
                <a href="/modules/relatorios.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    <span>Relatórios</span>
                </a>
                <a href="/modules/analytics.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                    <span>Analítica</span>
                </a>
                <a href="/modules/despesas.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <span>Despesas</span>
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Equipa</div>
                <a href="/admin/rh/equipa.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    <span>Equipa</span>
                </a>
                <a href="/modules/customers.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>Clientes</span>
                </a>
                <a href="/modules/ponto.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Controlo de Ponto</span>
                </a>
                <a href="/admin/vacation/calendario.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>Mapa de Férias</span>
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Sistema</div>
                <a href="/modules/alerts.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    <span>Alertas</span>
                </a>
                <a href="/modules/configuracoes.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>Definições</span>
                </a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <div class="user-menu-box">
                <div class="user-avatar"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($user_name) ?></div>
                    <div class="user-role">Operador</div>
                </div>
                <a href="/logout.php" style="color:var(--text-muted);display:flex;" title="Sair">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                </a>
            </div>
        </div>
    </aside>

    <div class="pos-wrapper">
    <div class="pos-container">
        <!-- LEFT PANEL -->
        <div class="pos-left">
            <header class="pos-header">
                <div class="pos-info">
                    <div class="pos-chip accent">
                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <select id="storeSelect" onchange="changeStore(this.value)">
                            <?php foreach ($all_stores as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $s['id'] == $store_id ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($shift): ?>
                    <div class="pos-chip">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                        <?= $shift['shift_number'] ?>
                    </div>
                    <div class="pos-chip" id="posTime">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/></svg>
                        <?= date('H:i', strtotime($shift['opened_at'])) ?>
                    </div>
                    <div class="pos-chip">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <?= htmlspecialchars($user_name) ?>
                    </div>
                    <?php else: ?>
                    <div class="pos-chip">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <?= htmlspecialchars($user_name) ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="pos-actions">
                    <a href="turno.php">
                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        Turno
                    </a>
                    <a href="historico.php">
                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Histórico
                    </a>
                    <a href="qrcodes_produtos.php" target="_blank">
                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                        QR Codes
                    </a>
                    <a href="/">
                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    </a>
                </div>
            </header>
            
            <div class="pos-search">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 12px;flex-shrink:0;color:var(--text-muted)"><circle cx="11" cy="11" r="8" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35"/></svg>
                <input type="text" id="productSearch" class="search-input" placeholder="Pesquisar produto ou código de barras... (F2)" autocomplete="off" autofocus>
                <button id="scannerBtn" title="Leitor QR Code" style="background:none;border:none;cursor:pointer;padding:0 12px;color:var(--text-muted);display:flex;align-items:center;" onclick="openScanner()">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                </button>
            </div>
            
            <div class="pos-categories">
                <button class="cat-btn active" data-category="">Todos</button>
                <?php foreach ($product_categories as $cat): ?>
                <button class="cat-btn" data-category="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></button>
                <?php endforeach; ?>
            </div>
            
            <div class="pos-products" id="productsGrid">
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                    A carregar produtos...
                </div>
            </div>
            
            <div class="shortcuts-bar">
                <span class="shortcut"><kbd>F1</kbd> Ajuda</span>
                <span class="shortcut"><kbd>F2</kbd> Pesquisar</span>
                <span class="shortcut"><kbd>F9</kbd> Pagamento</span>
                <span class="shortcut"><kbd>ESC</kbd> Fechar</span>
            </div>
        </div>
        
        <!-- RIGHT PANEL - CART -->
        <div class="pos-right">
            <?php if (!empty($suspended_sales)): ?>
            <div style="padding: 10px 16px; background: rgba(245,158,11,0.12); border-bottom: 1px solid rgba(245,158,11,0.25); color: var(--text-secondary); font-size: 12px; display:flex; align-items:center; gap:8px;">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <?= count($suspended_sales) ?> venda(s) suspensa(s)
            </div>
            <?php endif; ?>
            
            <div class="cart-header">
                <span style="font-size:10.5px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.1em;">Carrinho</span>
                <span class="item-count" id="itemCount">0 itens</span>
            </div>

            <div class="cart-items" id="cartItems">
                <div class="empty-cart">
                    <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="opacity:0.2;color:var(--text-muted)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <p style="font-size:12.5px;color:var(--text-muted);">Carrinho vazio</p>
                </div>
            </div>
            
            <div class="cart-summary">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span id="subtotalValue">€0.00</span>
                </div>
                <div class="summary-row" id="discountRow" style="display:none;">
                    <span style="color:var(--danger);">Desconto</span>
                    <span id="discountValue" style="color:var(--danger);">-€0.00</span>
                </div>
                <div class="summary-row total">
                    <span>TOTAL</span>
                    <span id="totalValue">€0.00</span>
                </div>
            </div>
            
            <div class="cart-actions">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                    <span style="font-size:11px;color:var(--text-muted);font-weight:600;letter-spacing:0.04em;text-transform:uppercase;">Desconto</span>
                    <button class="btn-discount" id="btnDiscount" onclick="showDiscountModal()">
                        <span id="discountLabel">Aplicar</span>
                    </button>
                </div>
                <button class="btn-pay" onclick="showPaymentModal()" id="btnPay" data-action="payment" disabled>
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    Pagamento <span style="opacity:.6;font-weight:400;font-size:11px">(F9)</span>
                </button>
                <div class="secondary-btns">
                    <button class="btn-sec" onclick="suspendSale()" id="btnSuspend" disabled>
                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Suspender
                    </button>
                    <button class="btn-sec danger" onclick="clearCart()">
                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Limpar
                    </button>
                </div>
            </div>
        </div>
    </div>
    </div><!-- /pos-wrapper -->

    <!-- MODAL PESO -->
    <div class="modal-overlay" id="weightModal">
        <div class="modal-box">
            <div class="modal-icon"><svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24" ><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 6l3 13h12l3-13M3 6h18M3 6a2 2 0 01-2-2V3a1 1 0 011-1h20a1 1 0 011 1v1a2 2 0 01-2 2M12 6V3m0 0a2 2 0 100-4 2 2 0 000 4z"/></svg></div>
            <h2>Peso</h2>
            <p class="subtitle" id="weightProductName">Produto</p>
            
            <div style="font-size: 42px; font-weight: 700; text-align: center; padding: 20px; background: var(--bg-tertiary); border-radius: 10px; color: var(--accent);" id="weightDisplay">
                0.000 <small style="font-size: 20px; color: var(--text-muted);">kg</small>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin: 20px 0;">
                <button class="quick-btn" onclick="addWeightDigit('1')">1</button>
                <button class="quick-btn" onclick="addWeightDigit('2')">2</button>
                <button class="quick-btn" onclick="addWeightDigit('3')">3</button>
                <button class="quick-btn" onclick="addWeightDigit('4')">4</button>
                <button class="quick-btn" onclick="addWeightDigit('5')">5</button>
                <button class="quick-btn" onclick="addWeightDigit('6')">6</button>
                <button class="quick-btn" onclick="addWeightDigit('7')">7</button>
                <button class="quick-btn" onclick="addWeightDigit('8')">8</button>
                <button class="quick-btn" onclick="addWeightDigit('9')">9</button>
                <button class="quick-btn" style="background: var(--danger);" onclick="clearWeight()">C</button>
                <button class="quick-btn" onclick="addWeightDigit('0')">0</button>
                <button class="quick-btn" onclick="addWeightDigit('.')">.</button>
            </div>
            
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeModal('weightModal')">Cancelar</button>
                <button class="btn-confirm" onclick="confirmWeight()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Confirmar</button>
            </div>
        </div>
    </div>

    <!-- MODAL PAGAMENTO -->
    <div class="modal-overlay" id="paymentModal">
        <div class="modal-box modal-wide">
            <div class="pay-header">
                <span class="pay-header-title">Pagamento</span>
                <button class="pay-header-close" onclick="closeModal('paymentModal')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <div class="payment-grid">
                <!-- Coluna esquerda: resumo da encomenda -->
                <div class="pay-summary-panel">
                    <div class="pay-label">Resumo da venda</div>
                    <div class="payment-items" id="paymentItems"></div>
                    <div class="payment-total">
                        <span class="payment-total-label">Total a pagar</span>
                        <span class="payment-total-value" id="paymentTotal">€0,00</span>
                    </div>
                </div>

                <!-- Coluna direita: valor entregue -->
                <div class="pay-input-panel">
                    <div>
                        <div class="pay-label" style="margin-bottom:6px;">Valor entregue</div>
                        <input type="number" id="amountPaid" step="0.01" class="input-large" placeholder="0,00" oninput="calculateChange()">
                    </div>

                    <div class="quick-amounts" id="quickAmounts"></div>

                    <div class="change-box" id="changeBox">
                        <span class="label">Troco</span>
                        <span class="value" id="changeValue">€0,00</span>
                    </div>

                    <div class="error-box" id="paymentError">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" style="vertical-align:-2px;margin-right:4px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        Valor insuficiente
                    </div>
                </div>
            </div>

            <div class="modal-actions" style="margin-top:20px;">
                <button class="btn-cancel" onclick="closeModal('paymentModal')">Cancelar</button>
                <button class="btn-confirm" onclick="confirmPayment()" id="btnConfirmPayment" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    Finalizar Venda
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL RECIBO -->
    <div class="modal-overlay" id="receiptModal">
        <div class="modal-box">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
                <div>
                    <div style="font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px;">Venda concluída</div>
                    <div style="font-size:18px;font-weight:700;color:var(--text-primary);" id="receiptNumber">—</div>
                </div>
                <div style="width:36px;height:36px;background:var(--bg-tertiary);border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;justify-content:center;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
            </div>

            <div style="background:var(--bg-tertiary);border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:16px;">
                <div style="display:flex;justify-content:space-between;align-items:center;padding:11px 14px;border-bottom:1px solid var(--border);font-size:13px;">
                    <span style="color:var(--text-muted)">Total</span>
                    <span style="font-weight:600;" id="receiptTotal">€0.00</span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:11px 14px;border-bottom:1px solid var(--border);font-size:13px;">
                    <span style="color:var(--text-muted)">Pago</span>
                    <span style="font-weight:600;" id="receiptPaid">€0.00</span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 14px;font-size:20px;font-weight:800;letter-spacing:-0.03em;">
                    <span style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted)">Troco</span>
                    <span id="receiptChange">€0.00</span>
                </div>
            </div>
            
            <div class="modal-actions">
                <button class="btn-cancel" onclick="finishWithoutReceipt()">Fechar</button>
                <button class="btn-confirm" onclick="printReceipt()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm1-4h4v4H10v-4z"/></svg>
                    Imprimir Recibo
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL DESCONTOS -->
    <div class="modal-overlay" id="discountModal">
        <div class="modal-box">
            <h2 style="margin-bottom:6px;">Desconto</h2>
            <p class="subtitle">Selecione uma promoção ou insira um desconto manual</p>

            <div class="promo-list" id="promoList">
                <?php if (!empty($active_promotions)): ?>
                <?php foreach ($active_promotions as $promo): ?>
                <div class="promo-item" onclick="selectPromo(this, <?= $promo['discount_type'] === 'percentage' ? $promo['discount_value'] : 0 ?>, '<?= htmlspecialchars($promo['name']) ?>')">
                    <span class="promo-name"><?= htmlspecialchars($promo['name']) ?></span>
                    <span class="promo-value">
                        <?php if ($promo['discount_type'] === 'percentage'): ?>
                        -<?= number_format($promo['discount_value'], 0) ?>%
                        <?php else: ?>
                        -€<?= number_format($promo['discount_value'], 2) ?>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <p style="text-align:center;color:var(--text-muted);font-size:13px;padding:16px 0;">Sem promoções ativas</p>
                <?php endif; ?>
            </div>

            <div style="border-top:1px solid var(--border);padding-top:14px;margin-top:4px;">
                <label style="font-size:11px;color:var(--text-muted);font-weight:600;letter-spacing:0.04em;text-transform:uppercase;display:block;margin-bottom:8px;">Desconto manual (%)</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="number" id="manualDiscount" min="0" max="100" step="1" placeholder="0"
                        style="flex:1;padding:10px 12px;background:var(--bg-tertiary);border:1px solid var(--border);border-radius:8px;color:var(--text-primary);font-size:16px;font-weight:700;text-align:center;"
                        oninput="clearPromoSelection()">
                    <span style="font-size:20px;color:var(--text-muted);">%</span>
                </div>
            </div>

            <div class="modal-actions">
                <button class="btn-cancel" onclick="removeDiscount()">Remover</button>
                <button class="btn-confirm" onclick="applyDiscount()">Aplicar</button>
            </div>
        </div>
    </div>

    <!-- QR Code Scanner Modal -->
    <div id="scannerModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.9);z-index:9999;align-items:center;justify-content:center;flex-direction:column">
        <div style="background:var(--bg-secondary,#1c1c1e);border-radius:16px;padding:24px;max-width:420px;width:95%;text-align:center">
            <h3 style="font-size:16px;font-weight:700;margin-bottom:6px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 0 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg> Leitor QR Code</h3>
            <p style="font-size:12px;color:#888;margin-bottom:14px">Aponte a câmara para o QR code do produto.</p>
            <div style="position:relative;width:100%;border-radius:10px;overflow:hidden;background:#000;min-height:280px">
                <video id="qrVideo" style="width:100%;display:block" playsinline autoplay muted></video>
                <canvas id="qrCanvas" style="display:none"></canvas>
                <!-- Scanning overlay frame -->
                <div style="position:absolute;inset:0;pointer-events:none;display:flex;align-items:center;justify-content:center">
                    <div style="width:200px;height:200px;border:2px solid rgba(255,255,255,0.6);border-radius:12px;box-shadow:0 0 0 9999px rgba(0,0,0,0.35)"></div>
                </div>
            </div>
            <div id="scanResult" style="margin-top:12px;font-size:13px;color:var(--text-primary,#fff);min-height:20px;font-weight:500"></div>
            <button onclick="closeScanner()" style="margin-top:14px;padding:8px 28px;border-radius:8px;border:1px solid #555;background:none;color:#fff;cursor:pointer;font-size:13px">Fechar</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <script>
    let scannerStream = null;
    let scannerAnimFrame = null;
    let scannerCooldown = false;

    function openScanner() {
        const modal = document.getElementById('scannerModal');
        modal.style.display = 'flex';
        document.getElementById('scanResult').textContent = '';
        scannerCooldown = false;

        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
            .then(stream => {
                scannerStream = stream;
                const video = document.getElementById('qrVideo');
                video.srcObject = stream;
                video.play();
                video.addEventListener('loadedmetadata', () => scanFrame());
            })
            .catch(err => {
                document.getElementById('scanResult').textContent = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Sem acesso à câmara: ' + err.message;
            });
    }

    function scanFrame() {
        const video = document.getElementById('qrVideo');
        const canvas = document.getElementById('qrCanvas');
        if (!video || video.readyState !== video.HAVE_ENOUGH_DATA) {
            scannerAnimFrame = requestAnimationFrame(scanFrame);
            return;
        }
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'dontInvert' });

        if (code && !scannerCooldown) {
            scannerCooldown = true;
            const val = code.data.trim().replace(/\s+/g, '');
            document.getElementById('scanResult').textContent = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Lido: ' + val;
            closeScanner();
            searchByBarcode(val);
            return;
        }
        scannerAnimFrame = requestAnimationFrame(scanFrame);
    }

    function closeScanner() {
        document.getElementById('scannerModal').style.display = 'none';
        if (scannerAnimFrame) { cancelAnimationFrame(scannerAnimFrame); scannerAnimFrame = null; }
        if (scannerStream) { scannerStream.getTracks().forEach(t => t.stop()); scannerStream = null; }
    }

    // Live clock
    (function liveClock() {
        const el = document.getElementById('posTime');
        if (el) {
            const svg = el.querySelector('svg');
            const now = new Date();
            const hms = now.toLocaleTimeString('pt-PT', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
            el.innerHTML = '';
            if (svg) el.appendChild(svg);
            el.appendChild(document.createTextNode('\u00a0' + hms));
        }
        setTimeout(liveClock, 1000);
    })();
    </script>

    <!-- Theme Toggle Button -->
    <button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema" style="bottom: 80px;">
        <span class="icon-moon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></span>
        <span class="icon-sun"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg></span>
    </button>

    <script>
        // Weighted categories from PHP
        const WEIGHTED_CATEGORIES = <?= $weighted_json ?>;
        const ACTIVE_PROMOTIONS = <?= json_encode($active_promotions) ?>;
    </script>
    <script src="assets/script.js"></script>
    <script src="/assets/js/pdv-shortcuts.js"></script>
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
