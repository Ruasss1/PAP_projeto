<?php
/**
 * GESTÃO DE TURNO - PREMIUM
 * Sangrias, Reforços, Despesas, Fecho de Caixa
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../config/database.php';

$session_user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ? AND active = 1 LIMIT 1");
$stmt->execute([$session_user_id]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_user) {
    $stmt = $pdo->query("SELECT id, name FROM users WHERE active = 1 ORDER BY id ASC LIMIT 1");
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$current_user) die('Erro: nenhum utilizador ativo.');

$user_id = intval($current_user['id']);
$user_name = $_SESSION['username'] ?? $current_user['name'] ?? 'Operador';

$stmt = $pdo->prepare("SELECT * FROM cash_register_shifts WHERE user_id = ? AND status = 'open' ORDER BY opened_at DESC LIMIT 1");
$stmt->execute([$user_id]);
$current_shift = $stmt->fetch(PDO::FETCH_ASSOC);

$operations = [];
if ($current_shift) {
    $stmt = $pdo->prepare("SELECT * FROM cash_operations WHERE shift_id = ? ORDER BY created_at DESC");
    $stmt->execute([$current_shift['id']]);
    $operations = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$current_balance = $current_shift ? floatval($current_shift['opening_balance']) : 0;
$vendas_cash = 0; $vendas_card = 0;
$total_sangrias = 0; $total_reforcos = 0; $total_despesas = 0; $num_vendas = 0;

if ($current_shift) {
    $stmt = $pdo->prepare("SELECT
        COALESCE(SUM(CASE WHEN operation_type IN ('reforco','abertura') THEN amount ELSE 0 END),0) as entradas,
        COALESCE(SUM(CASE WHEN operation_type = 'sangria' THEN amount ELSE 0 END),0) as saidas,
        COALESCE(SUM(CASE WHEN operation_type = 'despesa' THEN amount ELSE 0 END),0) as despesas
        FROM cash_operations WHERE shift_id = ?");
    $stmt->execute([$current_shift['id']]);
    $totals = $stmt->fetch();
    $total_sangrias = floatval($totals['saidas']);
    $total_reforcos = floatval($totals['entradas']) - floatval($current_shift['opening_balance']);
    $total_despesas = floatval($totals['despesas']);

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) as total, COUNT(*) as num FROM sales WHERE payment_method = 'Dinheiro' AND sale_date >= ?");
    $stmt->execute([$current_shift['opened_at']]);
    $r = $stmt->fetch(); $vendas_cash = floatval($r['total']); $num_vendas = intval($r['num']);

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) as total, COUNT(*) as num FROM sales WHERE payment_method != 'Dinheiro' AND sale_date >= ?");
    $stmt->execute([$current_shift['opened_at']]);
    $r = $stmt->fetch(); $vendas_card = floatval($r['total']); $num_vendas += intval($r['num']);

    $current_balance = floatval($current_shift['opening_balance']) + floatval($totals['entradas']) - $total_sangrias - $total_despesas + $vendas_cash;
}

$closed_shifts_stmt = $pdo->prepare("SELECT s.*, u.name as operator_name,
    (SELECT COUNT(*) FROM cash_operations WHERE shift_id = s.id) as total_ops
    FROM cash_register_shifts s LEFT JOIN users u ON s.user_id = u.id
    WHERE s.status = 'closed' ORDER BY s.closed_at DESC LIMIT 10");
$closed_shifts_stmt->execute();
$closed_shifts = $closed_shifts_stmt->fetchAll(PDO::FETCH_ASSOC);

$message = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'sangria':
                if (!$current_shift) throw new Exception('Nenhum turno aberto');
                $amount = floatval($_POST['amount'] ?? 0);
                $reason = trim($_POST['reason'] ?? '');
                if ($amount <= 0) throw new Exception('Valor invalido');
                if ($amount > $current_balance) throw new Exception('Valor superior ao saldo disponivel (€' . number_format($current_balance,2) . ')');
                if (empty($reason)) throw new Exception('Motivo obrigatorio para sangria');
                $stmt = $pdo->prepare("INSERT INTO cash_operations (shift_id, user_id, operation_type, amount, balance_before, balance_after, reason) VALUES (?,?,'sangria',?,?,?,?)");
                $stmt->execute([$current_shift['id'],$user_id,$amount,$current_balance,$current_balance-$amount,$reason]);
                $message = "Sangria de €".number_format($amount,2)." registada!";
                break;
            case 'reforco':
                if (!$current_shift) throw new Exception('Nenhum turno aberto');
                $amount = floatval($_POST['amount'] ?? 0);
                $reason = trim($_POST['reason'] ?? '');
                if ($amount <= 0) throw new Exception('Valor invalido');
                if (empty($reason)) throw new Exception('Motivo obrigatorio para reforco');
                $stmt = $pdo->prepare("INSERT INTO cash_operations (shift_id, user_id, operation_type, amount, balance_before, balance_after, reason) VALUES (?,?,'reforco',?,?,?,?)");
                $stmt->execute([$current_shift['id'],$user_id,$amount,$current_balance,$current_balance+$amount,$reason]);
                $message = "Reforco de €".number_format($amount,2)." registado!";
                break;
            case 'despesa':
                if (!$current_shift) throw new Exception('Nenhum turno aberto');
                $amount = floatval($_POST['amount'] ?? 0);
                $reason = trim($_POST['reason'] ?? '');
                $category = trim($_POST['category'] ?? 'Outro');
                if ($amount <= 0) throw new Exception('Valor invalido');
                if ($amount > $current_balance) throw new Exception('Valor superior ao saldo disponivel');
                if (empty($reason)) throw new Exception('Descricao obrigatoria para despesa');
                $stmt = $pdo->prepare("INSERT INTO cash_operations (shift_id, user_id, operation_type, amount, balance_before, balance_after, reason) VALUES (?,?,'despesa',?,?,?,?)");
                $stmt->execute([$current_shift['id'],$user_id,$amount,$current_balance,$current_balance-$amount,"[$category] $reason"]);
                $message = "Despesa de €".number_format($amount,2)." registada!";
                break;
            case 'fechar_turno':
                if (!$current_shift) throw new Exception('Nenhum turno aberto');
                $closing_balance = floatval($_POST['closing_balance'] ?? 0);
                $notes = trim($_POST['notes'] ?? '');
                $difference = $closing_balance - $current_balance;
                $stmt = $pdo->prepare("UPDATE cash_register_shifts SET closing_balance=?,expected_balance=?,difference=?,total_sales=?,total_cash=?,total_card=?,notes=?,closed_at=NOW(),status='closed' WHERE id=?");
                $stmt->execute([$closing_balance,$current_balance,$difference,$vendas_cash+$vendas_card,$vendas_cash,$vendas_card,$notes,$current_shift['id']]);
                $message = "Turno fechado! Diferenca: ".($difference>=0?'+':'')."€".number_format($difference,2);
                $current_shift = null;
                break;
            case 'abrir_turno':
                if ($current_shift) throw new Exception('Ja existe um turno aberto');
                $opening_balance = floatval($_POST['opening_balance'] ?? 0);
                $notes = trim($_POST['notes'] ?? '');
                $shift_number = 'SHIFT-'.date('Ymd-His').'-'.strtoupper(bin2hex(random_bytes(2)));
                $stmt = $pdo->prepare("INSERT INTO cash_register_shifts (shift_number, user_id, opening_balance, notes, opened_at, status) VALUES (?,?,?,?,NOW(),'open')");
                $stmt->execute([$shift_number,$user_id,$opening_balance,$notes]);
                $shift_id = $pdo->lastInsertId();
                $stmt = $pdo->prepare("INSERT INTO cash_operations (shift_id, user_id, operation_type, amount, balance_before, balance_after, reason) VALUES (?,?,'abertura',?,0,?,'Abertura de turno')");
                $stmt->execute([$shift_id,$user_id,$opening_balance,$opening_balance]);
                header("Location: turno.php?msg=".urlencode("Turno aberto com €".number_format($opening_balance,2)."!"));
                exit;
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
    $params = [];
    if ($message) $params['msg'] = $message;
    if ($error) $params['err'] = $error;
    header("Location: turno.php".(!empty($params)?('?'.http_build_query($params)):''));
    exit;
}

$message = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';
$active_tab = $_GET['tab'] ?? 'operacoes';

$shift_duration = '';
if ($current_shift) {
    $opened = new DateTime($current_shift['opened_at']);
    $diff = $opened->diff(new DateTime());
    $shift_duration = $diff->h.'h '.$diff->i.'min';
}
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestao de Turno | POS Premium</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/premium.css">
    <link rel="stylesheet" href="/assets/css/master-ui.css?v=<?= time() ?>">
    <script>(function(){const t=localStorage.getItem('pap-theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',-apple-system,sans-serif;background:var(--bg-primary);min-height:100vh;color:var(--text-primary);padding:24px}
        .container{max-width:1400px;margin:0 auto}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;padding-bottom:24px;border-bottom:1px solid var(--border)}
        .header h1{font-size:28px;font-weight:700;display:flex;align-items:center;gap:12px}
        .back-btn{display:flex;align-items:center;gap:8px;padding:12px 20px;background:var(--bg-secondary);border:1px solid var(--border);border-radius:var(--radius);color:var(--text-secondary);text-decoration:none;font-size:14px;font-weight:500;transition:all 0.2s}
        .back-btn:hover{background:var(--accent);border-color:var(--accent);color:white}
        .alert{padding:16px 20px;border-radius:var(--radius);margin-bottom:24px;font-size:14px;display:flex;align-items:center;gap:10px}
        .alert-success{background:rgba(34,197,94,0.12);color:var(--success);border:1px solid rgba(34,197,94,0.3)}
        .alert-danger{background:rgba(239,68,68,0.12);color:var(--danger);border:1px solid rgba(239,68,68,0.3)}
        .stats-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:24px}
        .stat-card{background:var(--bg-secondary);border:1px solid var(--border);border-radius:var(--radius);padding:20px}
        .stat-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:14px;background:var(--bg-tertiary)}
        .stat-value{font-size:22px;font-weight:700;margin-bottom:4px}
        .stat-value.positive{color:var(--success)}
        .stat-label{font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px}
        .shift-info-bar{background:var(--bg-secondary);border:1px solid var(--border);border-radius:var(--radius);padding:16px 24px;margin-bottom:24px;display:flex;gap:28px;align-items:center;flex-wrap:wrap}
        .shift-info-item{display:flex;align-items:center;gap:8px}
        .shift-info-label{font-size:11px;color:var(--text-muted);text-transform:uppercase}
        .shift-info-value{font-size:14px;font-weight:600}
        .shift-status-badge{margin-left:auto;display:flex;align-items:center;gap:6px;padding:6px 14px;border-radius:20px;font-size:13px;font-weight:600;background:rgba(34,197,94,0.15);color:var(--success);border:1px solid rgba(34,197,94,0.3)}
        .shift-status-dot{width:8px;height:8px;border-radius:50%;background:var(--success);animation:pulse 2s infinite}
        @keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
        .tabs{display:flex;gap:4px;background:var(--bg-secondary);border:1px solid var(--border);border-radius:var(--radius);padding:6px;margin-bottom:24px}
        .tab-btn{flex:1;padding:12px 16px;background:transparent;border:none;border-radius:8px;color:var(--text-muted);font-size:13px;font-weight:500;cursor:pointer;transition:all 0.2s;display:flex;align-items:center;justify-content:center;gap:7px;font-family:inherit}
        .tab-btn.active{background:var(--bg-tertiary);color:var(--text-primary)}
        .tab-btn:hover:not(.active){color:var(--text-secondary)}
        .tab-panel{display:none}
        .tab-panel.active{display:block}
        .table-card{background:var(--bg-secondary);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
        .table-header{display:flex;justify-content:space-between;align-items:center;padding:20px 24px;border-bottom:1px solid var(--border)}
        .table-header h2{font-size:18px;font-weight:600;display:flex;align-items:center;gap:10px}
        table{width:100%;border-collapse:collapse}
        th,td{padding:14px 24px;text-align:left;border-bottom:1px solid var(--border)}
        th{font-size:11px;font-weight:500;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;background:var(--bg-tertiary)}
        tr:last-child td{border-bottom:none}
        tr:hover td{background:var(--bg-tertiary)}
        .badge{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:6px;font-size:12px;font-weight:500}
        .badge-sangria{background:rgba(239,68,68,.15);color:var(--danger)}
        .badge-reforco{background:rgba(34,197,94,.15);color:var(--success)}
        .badge-abertura{background:rgba(59,130,246,.15);color:#60a5fa}
        .badge-despesa{background:rgba(245,158,11,.15);color:var(--warning)}
        .badge-muted{background:var(--bg-tertiary);color:var(--text-muted)}
        .amount-positive{color:var(--success);font-weight:700}
        .amount-negative{color:var(--danger);font-weight:700}
        .amount-neutral{color:var(--warning);font-weight:700}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px}
        .form-card{background:var(--bg-secondary);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
        .form-card-header{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
        .form-card-header h3{font-size:16px;font-weight:600}
        .form-card-body{padding:24px}
        .form-group{margin-bottom:16px}
        .form-group label{display:block;font-size:12px;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px}
        .form-group input,.form-group select,.form-group textarea{width:100%;padding:12px 16px;background:var(--bg-tertiary);border:1px solid var(--border);border-radius:8px;color:var(--text-primary);font-size:14px;font-family:inherit;transition:border-color .2s}
        .form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--accent)}
        .form-group select option{background:var(--bg-secondary)}
        .input-large{font-size:24px !important;text-align:center;font-weight:700;padding:16px !important}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 24px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:all .2s;width:100%;font-family:inherit}
        .btn-success{background:var(--success);color:white}
        .btn-danger{background:var(--danger);color:white}
        .btn-warning{background:var(--warning);color:#000}
        .btn-orange{background:#f97316;color:white}
        .btn:hover{opacity:.88;transform:translateY(-1px)}
        .expected-row{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:var(--bg-tertiary);border-radius:8px;margin-bottom:12px;font-size:14px}
        .no-shift-wrapper{display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start}
        .no-shift-info{background:var(--bg-secondary);border:1px solid var(--border);border-radius:var(--radius);padding:48px 32px;text-align:center}
        .no-shift-info h2{font-size:22px;margin-bottom:8px}
        .no-shift-info p{color:var(--text-muted);font-size:14px;line-height:1.6}
        .section-title{font-size:15px;font-weight:600;margin-bottom:16px;margin-top:32px;display:flex;align-items:center;gap:8px;color:var(--text-secondary)}
        .diff-positive{color:var(--success)}
        .diff-negative{color:var(--danger)}
        .empty-state{text-align:center;padding:48px;color:var(--text-muted)}
        .empty-state p{font-size:14px;margin-top:8px}
        .breakdown-list{display:flex;flex-direction:column;gap:10px}
        .breakdown-item{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:var(--bg-tertiary);border-radius:8px;font-size:14px}
        .breakdown-item .label{color:var(--text-muted)}
        .breakdown-divider{border:none;border-top:1px solid var(--border);margin:6px 0}
        .breakdown-item.total{background:transparent;padding:10px 14px 0;font-size:16px;font-weight:700}
        .help-text{font-size:13px;color:var(--text-muted);margin-bottom:20px;line-height:1.5}
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
            Gestao de Turno
        </h1>
        <a href="/CAIXA/" class="back-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M5 12l7 7M5 12l7-7"/></svg>
            Voltar ao POS
        </a>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-success">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($current_shift): ?>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#60a5fa" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg></div>
            <div class="stat-value">€<?= number_format($current_shift['opening_balance'],2) ?></div>
            <div class="stat-label">Fundo Inicial</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
            <div class="stat-value positive">€<?= number_format($vendas_cash,2) ?></div>
            <div class="stat-label">Vendas Dinheiro</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
            <div class="stat-value" style="color:#a78bfa">€<?= number_format($vendas_card,2) ?></div>
            <div class="stat-label">Vendas Cartao/MB</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
            <div class="stat-value"><?= $num_vendas ?></div>
            <div class="stat-label">N Vendas</div>
        </div>
        <div class="stat-card" style="border-color:rgba(34,197,94,.3)">
            <div class="stat-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
            <div class="stat-value positive">€<?= number_format($current_balance,2) ?></div>
            <div class="stat-label">Saldo Atual</div>
        </div>
    </div>

    <div class="shift-info-bar">
        <div class="shift-info-item">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
            <div><div class="shift-info-label">Turno</div><div class="shift-info-value"><?= htmlspecialchars($current_shift['shift_number'] ?? '#'.$current_shift['id']) ?></div></div>
        </div>
        <div class="shift-info-item">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <div><div class="shift-info-label">Abertura</div><div class="shift-info-value"><?= date('d/m/Y H:i',strtotime($current_shift['opened_at'])) ?></div></div>
        </div>
        <div class="shift-info-item">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <div><div class="shift-info-label">Operador</div><div class="shift-info-value"><?= htmlspecialchars($user_name) ?></div></div>
        </div>
        <div class="shift-info-item">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.63-6.9L23 10"/></svg>
            <div><div class="shift-info-label">Duracao</div><div class="shift-info-value" id="shiftDuration"><?= $shift_duration ?></div></div>
        </div>
        <div class="shift-info-item">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <div><div class="shift-info-label">Sangrias</div><div class="shift-info-value" style="color:var(--danger)">-€<?= number_format($total_sangrias,2) ?></div></div>
        </div>
        <div class="shift-info-item">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <div><div class="shift-info-label">Despesas</div><div class="shift-info-value" style="color:var(--warning)">-€<?= number_format($total_despesas,2) ?></div></div>
        </div>
        <div class="shift-status-badge"><div class="shift-status-dot"></div>Em curso</div>
    </div>

    <div class="tabs">
        <button class="tab-btn <?= $active_tab==='operacoes'?'active':'' ?>" onclick="switchTab('operacoes',this)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            Operacoes (<?= count($operations) ?>)
        </button>
        <button class="tab-btn <?= $active_tab==='sangria'?'active':'' ?>" onclick="switchTab('sangria',this)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Sangria
        </button>
        <button class="tab-btn <?= $active_tab==='reforco'?'active':'' ?>" onclick="switchTab('reforco',this)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Reforco
        </button>
        <button class="tab-btn <?= $active_tab==='despesa'?'active':'' ?>" onclick="switchTab('despesa',this)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
            Despesa
        </button>
        <button class="tab-btn <?= $active_tab==='fecho'?'active':'' ?>" onclick="switchTab('fecho',this)" style="color:var(--warning)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Fechar Turno
        </button>
    </div>

    <div class="tab-panel <?= $active_tab==='operacoes'?'active':'' ?>" id="tab-operacoes">
        <div class="table-card">
            <div class="table-header">
                <h2><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/></svg>Operacoes do Turno</h2>
                <span class="badge badge-muted"><?= count($operations) ?> registos</span>
            </div>
            <?php if (empty($operations)): ?>
            <div class="empty-state"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:.3;margin-bottom:12px"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/></svg><p>Sem operacoes registadas</p></div>
            <?php else: ?>
            <table>
                <thead><tr><th>Hora</th><th>Tipo</th><th>Motivo</th><th>Saldo Antes</th><th>Valor</th><th>Saldo Depois</th></tr></thead>
                <tbody>
                <?php foreach ($operations as $op):
                    $isIn = in_array($op['operation_type'],['reforco','abertura']);
                    $badgeClass = match($op['operation_type']){'sangria'=>'badge-sangria','reforco'=>'badge-reforco','abertura'=>'badge-abertura','despesa'=>'badge-despesa',default=>'badge-muted'};
                    $typeLabel = match($op['operation_type']){'sangria'=>'Sangria','reforco'=>'Reforco','abertura'=>'Abertura','despesa'=>'Despesa',default=>ucfirst($op['operation_type'])};
                ?>
                <tr>
                    <td><div style="font-weight:600"><?= date('H:i:s',strtotime($op['created_at'])) ?></div><small style="color:var(--text-muted)"><?= date('d/m/Y',strtotime($op['created_at'])) ?></small></td>
                    <td><span class="badge <?= $badgeClass ?>"><?= $typeLabel ?></span></td>
                    <td style="color:var(--text-secondary)"><?= htmlspecialchars($op['reason']?:'-') ?></td>
                    <td style="color:var(--text-muted)">€<?= number_format($op['balance_before'],2) ?></td>
                    <td><?= $isIn ? '<span class="amount-positive">+€'.number_format($op['amount'],2).'</span>' : '<span class="amount-negative">-€'.number_format($op['amount'],2).'</span>' ?></td>
                    <td style="font-weight:600">€<?= number_format($op['balance_after'],2) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="tab-panel <?= $active_tab==='sangria'?'active':'' ?>" id="tab-sangria">
        <div class="form-grid">
            <div class="form-card">
                <div class="form-card-header"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg><h3>Registar Sangria</h3></div>
                <div class="form-card-body">
                    <p class="help-text">Remocao de dinheiro da caixa para deposito ou transferencia. Saldo disponivel: <strong>€<?= number_format($current_balance,2) ?></strong></p>
                    <form method="post">
                        <input type="hidden" name="action" value="sangria">
                        <div class="form-group"><label>Valor (€)</label><input type="number" name="amount" step="0.01" min="0.01" max="<?= $current_balance ?>" class="input-large" placeholder="0.00" required></div>
                        <div class="form-group"><label>Motivo *</label><input type="text" name="reason" placeholder="Ex: Deposito bancario, Cofre..." required></div>
                        <button type="submit" class="btn btn-danger"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>Registar Sangria</button>
                    </form>
                </div>
            </div>
            <div class="form-card">
                <div class="form-card-header"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg><h3>Sangrias deste Turno</h3></div>
                <div class="form-card-body">
                    <?php $sangrias=array_filter($operations,fn($o)=>$o['operation_type']==='sangria'); ?>
                    <?php if(empty($sangrias)): ?><div class="empty-state" style="padding:32px"><p>Sem sangrias neste turno</p></div>
                    <?php else: ?><div class="breakdown-list">
                        <?php foreach($sangrias as $s): ?><div class="breakdown-item"><div><div style="font-size:13px"><?= htmlspecialchars($s['reason']) ?></div><div style="font-size:11px;color:var(--text-muted)"><?= date('H:i',strtotime($s['created_at'])) ?></div></div><span class="amount-negative">-€<?= number_format($s['amount'],2) ?></span></div><?php endforeach; ?>
                        <hr class="breakdown-divider"><div class="breakdown-item total"><span>Total Sangrias</span><span class="amount-negative">-€<?= number_format($total_sangrias,2) ?></span></div>
                    </div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-panel <?= $active_tab==='reforco'?'active':'' ?>" id="tab-reforco">
        <div class="form-grid">
            <div class="form-card">
                <div class="form-card-header"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg><h3>Registar Reforco</h3></div>
                <div class="form-card-body">
                    <p class="help-text">Adicao de dinheiro a caixa. Use para repor fundo de caixa ou adicionar troco extra.</p>
                    <form method="post">
                        <input type="hidden" name="action" value="reforco">
                        <div class="form-group"><label>Valor (€)</label><input type="number" name="amount" step="0.01" min="0.01" class="input-large" placeholder="0.00" required></div>
                        <div class="form-group"><label>Motivo *</label><input type="text" name="reason" placeholder="Ex: Reposicao de fundo, Troco extra..." required></div>
                        <button type="submit" class="btn btn-success"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>Registar Reforco</button>
                    </form>
                </div>
            </div>
            <div class="form-card">
                <div class="form-card-header"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg><h3>Reforcos deste Turno</h3></div>
                <div class="form-card-body">
                    <?php $reforcos=array_filter($operations,fn($o)=>$o['operation_type']==='reforco'); ?>
                    <?php if(empty($reforcos)): ?><div class="empty-state" style="padding:32px"><p>Sem reforcos neste turno</p></div>
                    <?php else: ?><div class="breakdown-list">
                        <?php foreach($reforcos as $r): ?><div class="breakdown-item"><div><div style="font-size:13px"><?= htmlspecialchars($r['reason']) ?></div><div style="font-size:11px;color:var(--text-muted)"><?= date('H:i',strtotime($r['created_at'])) ?></div></div><span class="amount-positive">+€<?= number_format($r['amount'],2) ?></span></div><?php endforeach; ?>
                        <hr class="breakdown-divider"><div class="breakdown-item total"><span>Total Reforcos</span><span class="amount-positive">+€<?= number_format($total_reforcos,2) ?></span></div>
                    </div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-panel <?= $active_tab==='despesa'?'active':'' ?>" id="tab-despesa">
        <div class="form-grid">
            <div class="form-card">
                <div class="form-card-header"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--warning)" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg><h3>Registar Despesa</h3></div>
                <div class="form-card-body">
                    <p class="help-text">Pagamento de despesas operacionais a partir da caixa. Ficara registado para auditoria.</p>
                    <form method="post">
                        <input type="hidden" name="action" value="despesa">
                        <div class="form-group"><label>Categoria</label><select name="category"><option>Fornecedor</option><option>Material de Limpeza</option><option>Embalagens</option><option>Transporte</option><option>Alimentacao Staff</option><option>Reparacao / Manutencao</option><option>Outro</option></select></div>
                        <div class="form-group"><label>Valor (€) - Saldo: €<?= number_format($current_balance,2) ?></label><input type="number" name="amount" step="0.01" min="0.01" max="<?= $current_balance ?>" class="input-large" placeholder="0.00" required></div>
                        <div class="form-group"><label>Descricao *</label><input type="text" name="reason" placeholder="Ex: Compra de sacos, pagamento fornecedor..." required></div>
                        <button type="submit" class="btn btn-orange"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>Registar Despesa</button>
                    </form>
                </div>
            </div>
            <div class="form-card">
                <div class="form-card-header"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg><h3>Despesas deste Turno</h3></div>
                <div class="form-card-body">
                    <?php $despesas=array_filter($operations,fn($o)=>$o['operation_type']==='despesa'); ?>
                    <?php if(empty($despesas)): ?><div class="empty-state" style="padding:32px"><p>Sem despesas neste turno</p></div>
                    <?php else: ?><div class="breakdown-list">
                        <?php foreach($despesas as $d): ?><div class="breakdown-item"><div><div style="font-size:13px"><?= htmlspecialchars($d['reason']) ?></div><div style="font-size:11px;color:var(--text-muted)"><?= date('H:i',strtotime($d['created_at'])) ?></div></div><span class="amount-neutral">-€<?= number_format($d['amount'],2) ?></span></div><?php endforeach; ?>
                        <hr class="breakdown-divider"><div class="breakdown-item total"><span>Total Despesas</span><span class="amount-neutral">-€<?= number_format($total_despesas,2) ?></span></div>
                    </div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-panel <?= $active_tab==='fecho'?'active':'' ?>" id="tab-fecho">
        <div class="form-grid">
            <div class="form-card">
                <div class="form-card-header"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--warning)" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg><h3>Fechar Turno</h3></div>
                <div class="form-card-body">
                    <p class="help-text">Introduza o valor contado fisicamente na caixa. O sistema calcula a diferenca automaticamente.</p>
                    <form method="post" onsubmit="return confirm('Confirmar fecho de turno? Esta acao nao pode ser revertida.')">
                        <input type="hidden" name="action" value="fechar_turno">
                        <div class="form-group"><label>Saldo Contado (€)</label><input type="number" name="closing_balance" step="0.01" min="0" class="input-large" value="<?= number_format($current_balance,2,'.','') ?>" required id="closingBalanceInput"></div>
                        <div class="expected-row"><span style="color:var(--text-muted)">Saldo esperado:</span><strong>€<?= number_format($current_balance,2) ?></strong></div>
                        <div class="expected-row"><span style="color:var(--text-muted)">Diferenca:</span><strong id="diffValue">€0.00</strong></div>
                        <div class="form-group" style="margin-top:16px"><label>Notas (opcional)</label><textarea name="notes" rows="2" placeholder="Observacoes..."></textarea></div>
                        <button type="submit" class="btn btn-warning"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Confirmar Fecho de Turno</button>
                    </form>
                </div>
            </div>
            <div class="form-card">
                <div class="form-card-header"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg><h3>Resumo do Turno</h3></div>
                <div class="form-card-body">
                    <div class="breakdown-list">
                        <div class="breakdown-item"><span class="label">Fundo Inicial</span><span>€<?= number_format($current_shift['opening_balance'],2) ?></span></div>
                        <div class="breakdown-item"><span class="label">+ Vendas a Dinheiro</span><span class="amount-positive">+€<?= number_format($vendas_cash,2) ?></span></div>
                        <div class="breakdown-item"><span class="label">+ Reforcos</span><span class="amount-positive">+€<?= number_format($total_reforcos,2) ?></span></div>
                        <div class="breakdown-item"><span class="label">- Sangrias</span><span class="amount-negative">-€<?= number_format($total_sangrias,2) ?></span></div>
                        <div class="breakdown-item"><span class="label">- Despesas</span><span class="amount-neutral">-€<?= number_format($total_despesas,2) ?></span></div>
                        <hr class="breakdown-divider">
                        <div class="breakdown-item total"><span>Saldo Esperado</span><span class="amount-positive">€<?= number_format($current_balance,2) ?></span></div>
                        <hr class="breakdown-divider">
                        <div class="breakdown-item"><span class="label">Vendas Cartao/MB</span><span style="color:var(--text-secondary)">€<?= number_format($vendas_card,2) ?></span></div>
                        <div class="breakdown-item"><span class="label">Total Vendas</span><span style="color:var(--text-secondary)">€<?= number_format($vendas_cash+$vendas_card,2) ?></span></div>
                        <div class="breakdown-item"><span class="label">N Vendas</span><span><?= $num_vendas ?></span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <div class="no-shift-wrapper">
        <div class="no-shift-info">
            <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:.2;margin-bottom:16px"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
            <h2>Nenhum turno aberto</h2>
            <p>Abra um turno para comecar a registar vendas, sangrias, reforcos e despesas de caixa.</p>
        </div>
        <div class="form-card">
            <div class="form-card-header"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg><h3>Abrir Novo Turno</h3></div>
            <div class="form-card-body">
                <form method="post">
                    <input type="hidden" name="action" value="abrir_turno">
                    <div class="form-group"><label>Fundo Inicial de Caixa (€)</label><input type="number" name="opening_balance" step="0.01" min="0" class="input-large" value="100.00" required></div>
                    <div class="form-group"><label>Operador</label><input type="text" value="<?= htmlspecialchars($user_name) ?>" disabled style="opacity:.6"></div>
                    <div class="form-group"><label>Notas (opcional)</label><input type="text" name="notes" placeholder="Observacoes iniciais..."></div>
                    <button type="submit" class="btn btn-success"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>Abrir Turno</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($closed_shifts)): ?>
    <div class="section-title">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Ultimos Turnos Fechados
    </div>
    <div class="table-card">
        <table>
            <thead><tr><th>Turno</th><th>Operador</th><th>Abertura</th><th>Fecho</th><th>Fundo</th><th>Vendas</th><th>Esperado</th><th>Contado</th><th>Diferenca</th><th>Ops</th></tr></thead>
            <tbody>
            <?php foreach($closed_shifts as $cs):
                $diff = floatval($cs['difference']??0);
                $diffClass = $diff>0?'diff-positive':($diff<0?'diff-negative':'');
            ?>
            <tr>
                <td style="font-weight:600;font-size:12px;color:var(--accent)"><?= htmlspecialchars($cs['shift_number']??'#'.$cs['id']) ?></td>
                <td><?= htmlspecialchars($cs['operator_name']??'-') ?></td>
                <td><div style="font-size:13px"><?= date('d/m/Y',strtotime($cs['opened_at'])) ?></div><small style="color:var(--text-muted)"><?= date('H:i',strtotime($cs['opened_at'])) ?></small></td>
                <td><?php if($cs['closed_at']): ?><div style="font-size:13px"><?= date('d/m/Y',strtotime($cs['closed_at'])) ?></div><small style="color:var(--text-muted)"><?= date('H:i',strtotime($cs['closed_at'])) ?></small><?php else: ?>-<?php endif; ?></td>
                <td>€<?= number_format($cs['opening_balance'],2) ?></td>
                <td class="amount-positive">€<?= number_format($cs['total_sales']??0,2) ?></td>
                <td>€<?= number_format($cs['expected_balance']??0,2) ?></td>
                <td>€<?= number_format($cs['closing_balance']??0,2) ?></td>
                <td class="<?= $diffClass ?>"><?= $diff>=0?'+':'' ?>€<?= number_format($diff,2) ?></td>
                <td><span class="badge badge-muted"><?= $cs['total_ops'] ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>
<button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema">
    <span class="icon-moon">🌙</span>
    <span class="icon-sun">☀️</span>
</button>
<script>
function toggleTheme(){const h=document.documentElement;const n=h.getAttribute('data-theme')==='dark'?'light':'dark';h.setAttribute('data-theme',n);localStorage.setItem('pap-theme',n);}
function switchTab(name,btn){document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));document.getElementById('tab-'+name).classList.add('active');btn.classList.add('active');}
const closingInput=document.getElementById('closingBalanceInput');
const diffValue=document.getElementById('diffValue');
const expected=<?= $current_balance ?? 0 ?>;
if(closingInput&&diffValue){closingInput.addEventListener('input',function(){const v=parseFloat(this.value)||0;const d=v-expected;diffValue.textContent=(d>=0?'+':'')+'€'+d.toFixed(2);diffValue.style.color=d>0?'var(--success)':d<0?'var(--danger)':'var(--text-primary)';});}
<?php if($current_shift): ?>
const shiftStart=new Date('<?= $current_shift['opened_at'] ?>').getTime();
function updateDuration(){const d=Date.now()-shiftStart;const h=Math.floor(d/3600000);const m=Math.floor((d%3600000)/60000);const s=Math.floor((d%60000)/1000);const el=document.getElementById('shiftDuration');if(el)el.textContent=h+'h '+String(m).padStart(2,'0')+'min '+String(s).padStart(2,'0')+'s';}
updateDuration();setInterval(updateDuration,1000);
<?php endif; ?>
</script>
<script src="/assets/js/master-ui.js?v=<?= time() ?>"></script>
</body>
</html>
