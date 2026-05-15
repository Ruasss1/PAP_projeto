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
        * { box-sizing: border-box; }
        body { overflow: hidden; display: flex; height: 100vh; }

        /* SIDEBAR within POS — overlay, does not steal layout space */
        .app-sidebar {
            position: absolute;
            left: 0;
            top: 0;
            z-index: 200;
            height: 100vh;
            width: 52px;
            background: var(--bg-primary);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            transition: width 0.22s cubic-bezier(.4,0,.2,1);
            overflow-x: hidden;
            overflow-y: auto;
        }
        .app-sidebar::-webkit-scrollbar { width: 0; }
        .app-sidebar:hover {
            width: 220px;
            box-shadow: 4px 0 24px rgba(0,0,0,0.25);
        }
        body { position: relative; }
        .app-sidebar .sidebar-header { padding: 14px 8px; border-bottom: 1px solid var(--border); display: flex; align-items: center; overflow: hidden; flex-shrink: 0; }
        .app-sidebar .sidebar-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text-primary); white-space: nowrap; }
        .app-sidebar .sidebar-logo-icon { width: 32px; height: 32px; background: var(--bg-tertiary); border: 1px solid var(--border); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
        .app-sidebar .sidebar-logo-text { font-family: 'Playfair Display', serif; font-weight: 700; font-size: 15px; letter-spacing: -0.02em; opacity: 0; transition: opacity 0.15s ease; white-space: nowrap; }
        .app-sidebar:hover .sidebar-logo-text { opacity: 1; }
        .app-sidebar .sidebar-nav { padding: 10px 6px; flex: 1; overflow-y: auto; overflow-x: hidden; min-height: 0; }
        .app-sidebar .sidebar-nav::-webkit-scrollbar { width: 3px; }
        .app-sidebar .sidebar-nav::-webkit-scrollbar-thumb { background: var(--border); border-radius: 100px; }
        .app-sidebar .nav-section { margin-bottom: 14px; }
        .app-sidebar .nav-section-title { font-size: 9px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-muted); padding: 0 10px; margin-bottom: 4px; opacity: 0; transition: opacity 0.15s ease; white-space: nowrap; overflow: hidden; }
        .app-sidebar:hover .nav-section-title { opacity: 1; }
        .app-sidebar .nav-item { display: flex; align-items: center; gap: 10px; padding: 8px 10px; font-size: 13px; font-weight: 500; color: var(--text-secondary); border-radius: var(--radius); text-decoration: none; transition: background 0.15s, color 0.15s, transform 0.15s; margin-bottom: 1px; position: relative; white-space: nowrap; overflow: hidden; }
        .app-sidebar .nav-item:hover { background: var(--bg-secondary); color: var(--text-primary); }
        .app-sidebar:hover .nav-item:hover { transform: translateX(2px); }
        .app-sidebar .nav-item.active { background: var(--bg-tertiary); color: var(--text-primary); font-weight: 600; }
        .app-sidebar .nav-item.active::before { content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 2px; height: 14px; background: var(--text-primary); border-radius: 0 2px 2px 0; }
        .app-sidebar .nav-icon { width: 17px; height: 17px; flex-shrink: 0; opacity: 0.65; }
        .app-sidebar .nav-item:hover .nav-icon, .app-sidebar .nav-item.active .nav-icon { opacity: 1; }
        .app-sidebar .nav-item span { opacity: 0; transition: opacity 0.15s ease; }
        .app-sidebar:hover .nav-item span { opacity: 1; }
        .app-sidebar .nav-badge { background: var(--danger); color: #fff; border-radius: 100px; font-size: 9px; padding: 1px 5px; font-weight: 700; margin-left: auto; min-width: 16px; text-align: center; }
        .app-sidebar .sidebar-footer { padding: 8px 6px; border-top: 1px solid var(--border); overflow: hidden; }
        .app-sidebar .user-menu-box { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: var(--radius); background: var(--bg-secondary); transition: background 0.15s; cursor: pointer; overflow: hidden; }
        .app-sidebar .user-menu-box:hover { background: var(--bg-tertiary); }
        .app-sidebar .user-avatar { width: 28px; height: 28px; border-radius: 50%; background: var(--bg-tertiary); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0; }
        .app-sidebar .user-info { flex: 1; min-width: 0; opacity: 0; transition: opacity 0.15s ease; }
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
            grid-template-columns: 1fr 420px;
            flex: 1;
            overflow: hidden;
            background: var(--bg-primary);
        }
        
        /* LEFT PANEL */
        .pos-left {
            display: flex;
            flex-direction: column;
            padding: 24px 24px 24px 68px;
            overflow: hidden;
            position: relative;
            background: linear-gradient(180deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
        }
        
        .pos-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 20px;
        }
        
        .pos-info {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .pos-chip {
            padding: 8px 16px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 24px;
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .pos-chip.accent {
            background: var(--bg-tertiary);
            border-color: var(--border);
            color: var(--text-primary);
        }
        
        .pos-chip select {
            background: transparent;
            border: none;
            color: inherit;
            font-size: 12px;
            cursor: pointer;
        }
        
        .pos-actions {
            display: flex;
            gap: 8px;
        }
        
        .pos-actions a {
            padding: 10px 14px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 12px;
            transition: all 0.2s;
        }
        
        .pos-actions a:hover {
            background: var(--bg-tertiary);
            border-color: var(--border);
            color: var(--text-primary);
        }
        
        /* SEARCH */
        .pos-search {
            display: flex;
            align-items: center;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 4px;
            margin-bottom: 16px;
        }
        
        .pos-search:focus-within {
            border-color: var(--accent);
        }
        
        .pos-search span {
            padding: 0 14px;
            color: var(--text-muted);
        }
        
        .pos-search input {
            flex: 1;
            background: none;
            border: none;
            padding: 12px;
            color: var(--text-primary);
            font-size: 14px;
            outline: none;
        }
        
        /* CATEGORIES */
        .pos-categories {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            overflow-x: auto;
            padding-bottom: 8px;
        }
        
        .pos-categories::-webkit-scrollbar { height: 4px; }
        .pos-categories::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
        
        .cat-btn {
            padding: 10px 20px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 24px;
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
        }
        
        .cat-btn:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            transform: translateY(-1px);
        }
        
        .cat-btn.active {
            background: var(--bg-tertiary);
            border-color: var(--border);
            color: var(--text-primary);
            box-shadow: none;
        }
        
        /* PRODUCTS GRID */
        .pos-products {
            flex: 1;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 14px;
            overflow-y: auto;
            padding-right: 8px;
            padding-bottom: 70px;
        }
        
        .pos-products::-webkit-scrollbar { width: 6px; }
        .pos-products::-webkit-scrollbar-thumb { background: var(--border); border-radius: 6px; }
        
        .product-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        
        .product-card:hover {
            background: #1e1e1e;
            border-color: #2a2a2a;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
        }
        
        .product-card:hover .product-name,
        .product-card:hover .product-price,
        .product-card:hover .product-stock {
            color: #ececec;
        }
        
        [data-theme="light"] .product-card:hover {
            background: #ffffff;
            border-color: #d4d4d8;
            box-shadow: 0 8px 24px rgba(0,0,0,.12);
        }
        [data-theme="light"] .product-card:hover .product-name,
        [data-theme="light"] .product-card:hover .product-price,
        [data-theme="light"] .product-card:hover .product-stock {
            color: #09090b;
        }
        
        .product-card:active {
            transform: scale(0.98);
        }
        
        .product-card.no-stock {
            opacity: 0.4;
            pointer-events: none;
        }
        
        .product-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
        }
        
        .product-name {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 6px;
            line-height: 1.4;
            height: 34px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            color: var(--text-primary);
            word-break: break-word;
        }
        
        .product-price {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .product-stock {
            font-size: 11px;
            color: var(--text-secondary);
            padding: 4px 10px;
            background: var(--bg-tertiary);
            border-radius: 10px;
            display: inline-block;
            font-weight: 600;
        }
        
        .product-stock.low { color: #b45309; background: rgba(245, 158, 11, 0.2); }
        .product-stock.out { color: #dc2626; background: rgba(239, 68, 68, 0.2); }
        
        /* Light mode adjustments for stock */
        [data-theme="light"] .product-stock { background: #e4e4e7; color: #3f3f46; }
        [data-theme="light"] .product-stock.low { color: #b45309; background: rgba(245, 158, 11, 0.25); }
        [data-theme="light"] .product-stock.out { color: #dc2626; background: rgba(239, 68, 68, 0.25); }
        
        /* RIGHT PANEL - CART */
        .pos-right {
            background: var(--bg-secondary);
            border-left: 1px solid var(--border);
            display: flex;
            flex-direction: column;
        }
        
        .cart-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .cart-header h2 {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .cart-header h2::before {
            content: '';
            width: 3px;
            height: 14px;
            background: var(--success);
            border-radius: 2px;
            display: inline-block;
            flex-shrink: 0;
        }
        
        .item-count {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-secondary);
            padding: 3px 10px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 20px;
        }
        
        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 12px 14px;
            scrollbar-width: thin;
            scrollbar-color: var(--border) transparent;
        }
        
        .empty-cart {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-muted);
        }
        
        .empty-cart span {
            font-size: 44px;
            display: block;
            margin-bottom: 10px;
            opacity: 0.15;
            filter: grayscale(1);
        }
        
        .cart-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 13px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-left: 3px solid transparent;
            border-radius: 8px;
            margin-bottom: 7px;
            transition: border-color 0.15s, background 0.15s;
            animation: itemIn 0.18s ease;
        }

        @keyframes itemIn {
            from { opacity: 0; transform: translateX(10px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        .cart-item:hover {
            border-left-color: var(--border);
            background: var(--bg-hover);
        }
        
        .cart-item-emoji { font-size: 22px; flex-shrink: 0; }
        
        .cart-item-info { flex: 1; min-width: 0; }
        
        .cart-item-name { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        .cart-item-price { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
        
        .cart-item-qty {
            display: flex;
            align-items: center;
            gap: 5px;
            background: var(--bg-primary);
            padding: 3px 5px;
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        
        .qty-btn {
            width: 26px;
            height: 26px;
            border: 1px solid var(--border);
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 700;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .qty-btn:hover { background: var(--bg-primary); border-color: var(--text-primary); color: var(--text-primary); transform: scale(1.1); }
        .qty-btn.danger:hover { background: var(--danger); border-color: var(--danger); color: #fff; }
        
        .cart-item-total {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-primary);
            min-width: 58px;
            text-align: right;
            letter-spacing: -0.3px;
        }
        
        /* CART FOOTER */
        /* CART SUMMARY */
        .cart-summary {
            padding: 14px 18px;
            background: linear-gradient(180deg, var(--bg-tertiary) 0%, var(--bg-secondary) 100%);
            border-top: 1px solid var(--border);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }
        
        .summary-row.total {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding-top: 12px;
            border-top: 1px solid var(--border);
            margin: 10px 0 0 0;
        }
        .summary-row.total > span:first-child {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-secondary);
        }
        .summary-row.total > span:last-child {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.5px;
        }
        
        /* CART ACTIONS */
        .cart-actions {
            padding: 12px 18px 18px;
            border-top: 1px solid var(--border);
        }
        
        .btn-pay {
            width: 100%;
            padding: 16px;
            background: var(--text-primary);
            border: none;
            border-radius: 10px;
            color: var(--bg-primary);
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.03em;
            cursor: pointer;
            margin-bottom: 10px;
            transition: all 0.2s cubic-bezier(.34,1.4,.64,1);
            box-shadow: 0 4px 16px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-pay:disabled { opacity: 0.3; cursor: not-allowed; box-shadow: none; transform: none !important; }
        .btn-pay:hover:not(:disabled) { 
            transform: translateY(-2px) scale(1.01);
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
            opacity: 0.9;
        }
        .btn-pay:active:not(:disabled) { transform: scale(0.98); }
        
        .secondary-btns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        
        .btn-sec {
            padding: 11px 8px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .btn-sec:hover { background: var(--bg-hover); color: var(--text-primary); transform: translateY(-1px); border-color: var(--border-light); }
        .btn-sec.danger { color: var(--danger); border-color: rgba(239, 68, 68, 0.25); background: rgba(239, 68, 68, 0.06); }
        .btn-sec.danger:hover { background: var(--danger); color: white; border-color: var(--danger); box-shadow: 0 4px 14px rgba(239,68,68,0.3); }
        
        /* MODALS */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active { display: flex; }
        
        .modal-box {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px;
            width: 90%;
            max-width: 450px;
        }
        
        .modal-box h2 {
            text-align: center;
            margin-bottom: 8px;
        }
        
        .modal-box .subtitle {
            text-align: center;
            color: var(--text-muted);
            font-size: 13px;
            margin-bottom: 24px;
        }
        
        .modal-icon {
            text-align: center;
            font-size: 48px;
            margin-bottom: 12px;
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
            padding: 12px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .form-group input:focus {
            border-color: var(--accent);
            outline: none;
        }
        
        .input-large {
            font-size: 28px !important;
            text-align: center;
            font-weight: 700;
        }
        
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--success), #16a34a);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .btn-submit:hover { transform: translateY(-1px); }
        
        /* SHORTCUTS BAR */
        .shortcuts-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-secondary);
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: center;
            gap: 16px;
            padding: 10px;
        }
        
        .shortcut {
            font-size: 11px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .shortcut kbd {
            background: var(--bg-tertiary);
            padding: 3px 8px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 10px;
            color: var(--accent);
        }
        
        /* PAYMENT MODAL */
        .modal-wide { max-width: 700px; }
        
        .payment-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        
        .payment-items {
            background: var(--bg-tertiary);
            border-radius: 10px;
            padding: 14px;
            max-height: 180px;
            overflow-y: auto;
            margin-bottom: 16px;
        }
        
        .payment-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 13px;
            border-bottom: 1px solid var(--border);
        }
        
        .payment-item:last-child { border: none; }
        
        .payment-total {
            background: var(--bg-tertiary);
            padding: 16px;
            border-radius: 10px;
            text-align: center;
        }
        
        .payment-total span { color: var(--text-muted); font-size: 12px; }
        .payment-total div { font-size: 32px; font-weight: 700; color: var(--success); margin-top: 4px; }
        
        .quick-amounts {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin: 16px 0;
        }
        
        .quick-btn {
            padding: 12px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
            cursor: pointer;
        }
        
        .quick-btn:hover { background: var(--accent); border-color: var(--accent); }
        
        .change-box {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid var(--success);
            border-radius: 10px;
            padding: 16px;
            text-align: center;
            margin: 16px 0;
        }
        
        .change-box .label { font-size: 12px; color: var(--success); }
        .change-box .value { font-size: 28px; font-weight: 700; color: var(--success); margin-top: 4px; }
        
        .error-box {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--danger);
            color: var(--danger);
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            font-size: 13px;
            display: none;
        }
        
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        
        .modal-actions button {
            flex: 1;
            padding: 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }
        
        .btn-cancel {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
        }
        
        .btn-confirm {
            background: linear-gradient(135deg, var(--success), #16a34a);
            color: white;
        }
        
        .btn-confirm:disabled { opacity: 0.5; }

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
            border-radius: 12px;
        }
        .btn-discount {
            padding: 5px 12px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 7px;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-discount:hover { background: var(--bg-tertiary); border-color: var(--border); color: var(--text-primary); }
        .btn-discount.active { background: var(--bg-tertiary); border-color: var(--border); color: var(--text-primary); }

        /* Discount Modal */
        .promo-list { display: flex; flex-direction: column; gap: 8px; margin: 16px 0; }
        .promo-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s;
        }
        .promo-item:hover { border-color: var(--border); background: var(--bg-secondary); }
        .promo-item.selected { border-color: var(--text-primary); background: var(--bg-secondary); }
        .promo-name { font-size: 13px; font-weight: 600; }
        .promo-value { font-size: 14px; font-weight: 700; color: var(--text-primary); }

        /* Light mode improvements */
        [data-theme="light"] .pos-left { background: #f8f8fa; }
        [data-theme="light"] .pos-header { background: #ffffff; }
        [data-theme="light"] .pos-right { background: #ffffff; }
        [data-theme="light"] .cart-summary { background: #f4f4f6; }
        [data-theme="light"] .product-card { background: #ffffff; border-color: #e2e2e8; }
        [data-theme="light"] .product-card:hover { background: #f8f8fa; border-color: #d1d1db; box-shadow: 0 4px 12px rgba(0,0,0,.08); }
        [data-theme="light"] .product-price { color: #16a34a; }
        [data-theme="light"] .pos-chip { background: #ffffff; border-color: #e2e2e8; color: #52525b; }
        [data-theme="light"] .cat-btn { background: #ffffff; border-color: #e2e2e8; color: #5a5a6e; }
        [data-theme="light"] .cat-btn:hover, [data-theme="light"] .cat-btn.active { background: #eff0f3; border-color: #d1d1db; color: #111118; }
        [data-theme="light"] .pos-search { background: #ffffff; border-color: #e2e2e8; }
        [data-theme="light"] .cart-item { background: #f4f4f6; border-color: #e2e2e8; }
        [data-theme="light"] .qty-btn { background: #ffffff; border-color: #e2e2e8; color: #111118; }
        [data-theme="light"] .btn-sec { background: #f4f4f6; border-color: #e2e2e8; color: #5a5a6e; }
        [data-theme="light"] .modal-box { background: #ffffff; border-color: #e2e2e8; }
        [data-theme="light"] .quick-btn { background: #f4f4f6; border-color: #e2e2e8; color: #111118; }
        [data-theme="light"] .quick-btn:hover { background: #52525b; border-color: #52525b; color: #ffffff; }
        [data-theme="light"] .app-sidebar { background: #ffffff; border-color: #e2e2e8; }
    </style>
</head>
<body>
    <?php if (!$shift_open): ?>
    <!-- MODAL ABRIR TURNO -->
    <div class="modal-overlay active" id="shiftModal">
        <div class="modal-box">
            <div class="modal-icon"><svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg></div>
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
                <button type="submit" class="btn-submit">✓ Iniciar Turno</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- SIDEBAR -->
    <aside class="app-sidebar">
        <div class="sidebar-header">
            <a href="/" class="sidebar-logo">
                <div class="sidebar-logo-icon">🛒</div>
                <span class="sidebar-logo-text">PAP Market</span>
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
                <a href="/CAIXA/qrcodes_produtos.php" target="_blank" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                    <span>QR Produtos</span>
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
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Relatórios</div>
                <a href="/modules/relatorios.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    <span>Relatórios</span>
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
                <span style="font-size:13px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;">Carrinho</span>
                <span class="item-count" id="itemCount">0 itens</span>
            </div>
            
            <div class="cart-items" id="cartItems">
                <div class="empty-cart">
                    <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto 12px;display:block;opacity:0.2"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <p>Carrinho vazio</p>
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
            <div class="modal-icon"><svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 6l3 13h12l3-13M3 6h18M3 6a2 2 0 01-2-2V3a1 1 0 011-1h20a1 1 0 011 1v1a2 2 0 01-2 2M12 6V3m0 0a2 2 0 100-4 2 2 0 000 4z"/></svg></div>
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
                <button class="btn-confirm" onclick="confirmWeight()">✓ Confirmar</button>
            </div>
        </div>
    </div>

    <!-- MODAL PAGAMENTO -->
    <div class="modal-overlay" id="paymentModal">
        <div class="modal-box modal-wide">
            <h2 style="display:flex;align-items:center;gap:8px;justify-content:center">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                Pagamento
            </h2>
            
            <div class="payment-grid">
                <div>
                    <h4 style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">📋 Resumo</h4>
                    <div class="payment-items" id="paymentItems"></div>
                    <div class="payment-total">
                        <span>Total a Pagar</span>
                        <div id="paymentTotal">€0.00</div>
                    </div>
                </div>
                
                <div>
                    <h4 style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">💵 Valor Entregue</h4>
                    
                    <div class="form-group">
                        <input type="number" id="amountPaid" step="0.01" class="input-large" placeholder="0.00" oninput="calculateChange()">
                    </div>
                    
                    <div class="quick-amounts" id="quickAmounts"></div>
                    
                    <div class="change-box" id="changeBox">
                        <div class="label">Troco</div>
                        <div class="value" id="changeValue">€0.00</div>
                    </div>
                    
                    <div class="error-box" id="paymentError">
                        Valor insuficiente!
                    </div>
                </div>
            </div>
            
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeModal('paymentModal')">Cancelar</button>
                <button class="btn-confirm" onclick="confirmPayment()" id="btnConfirmPayment" disabled>✓ Finalizar Venda</button>
            </div>
        </div>
    </div>

    <!-- MODAL RECIBO -->
    <div class="modal-overlay" id="receiptModal">
        <div class="modal-box">
            <div class="modal-icon">✅</div>
            <h2>Venda Concluída!</h2>
            
            <div style="background: var(--bg-tertiary); padding: 16px; border-radius: 10px; margin: 20px 0;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span>Recibo Nº</span>
                    <span id="receiptNumber">-</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span>Total</span>
                    <span id="receiptTotal">€0.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span>Pago</span>
                    <span id="receiptPaid">€0.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding-top: 12px; border-top: 1px solid var(--border); font-size: 18px; font-weight: 700; color: var(--success);">
                    <span>Troco</span>
                    <span id="receiptChange">€0.00</span>
                </div>
            </div>
            
            <div class="modal-actions">
                <button class="btn-cancel" onclick="finishWithoutReceipt()">Fechar</button>
                <button class="btn-confirm" onclick="printReceipt()">🖨️ Imprimir</button>
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
            <h3 style="font-size:16px;font-weight:700;margin-bottom:6px">📷 Leitor QR Code</h3>
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
                document.getElementById('scanResult').textContent = '⚠️ Sem acesso à câmara: ' + err.message;
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
            document.getElementById('scanResult').textContent = '✓ Lido: ' + val;
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
        <span class="icon-moon">🌙</span>
        <span class="icon-sun">☀️</span>
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
