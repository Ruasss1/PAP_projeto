<?php
/**
 * Gestão de Férias — Standalone (estilo equipa/turnos)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../modules/rh.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$message = '';
$error = '';

// Carregar pedidos
try {
    $stmt = $pdo->prepare("SELECT vr.*, e.name as employee_name, e.email, e.department
                           FROM vacation_requests vr
                           INNER JOIN employees e ON vr.employee_id = e.id
                           ORDER BY vr.created_at DESC");
    $stmt->execute();
    $vacation_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $vacation_requests = [];
    $error = "Erro ao obter solicitações: " . $e->getMessage();
}

// Processar aprovação/rejeição
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $request_id = $_POST['vacation_id'] ?? null;
    if ($request_id) {
        $action = $_POST['action'];
        try {
            if ($action === 'approve') {
                $pdo->prepare("UPDATE vacation_requests SET status = 'Aprovado', approved_by = :uid, approved_at = NOW() WHERE id = :id")
                    ->execute([':id' => $request_id, ':uid' => $_SESSION['user_id']]);
                // Atualizar saldo
                foreach ($vacation_requests as $vr) {
                    if ($vr['id'] == $request_id) {
                        $days_to_use = (int)($vr['number_of_days'] ?? $vr['days'] ?? 0);
                        if (function_exists('ensure_vacation_balance_table')) ensure_vacation_balance_table();
                        if (function_exists('create_vacation_balance')) create_vacation_balance($vr['employee_id'], date('Y'));
                        try {
                            $pdo->prepare("UPDATE vacation_balance SET used_days = used_days + :d WHERE employee_id = :e AND year = :y")
                                ->execute([':d' => $days_to_use, ':e' => $vr['employee_id'], ':y' => date('Y')]);
                        } catch (Exception $ex) {}
                        break;
                    }
                }
                $message = 'Férias aprovadas com sucesso.';
            } elseif ($action === 'reject') {
                $reason = $_POST['rejection_reason'] ?? '';
                $pdo->prepare("UPDATE vacation_requests SET status = 'Rejeitado', approved_by = :uid, approved_at = NOW(), rejection_reason = :r WHERE id = :id")
                    ->execute([':id' => $request_id, ':uid' => $_SESSION['user_id'], ':r' => $reason]);
                $message = 'Férias rejeitadas.';
            }
            // Recarregar
            $stmt = $pdo->prepare("SELECT vr.*, e.name as employee_name, e.email, e.department
                                   FROM vacation_requests vr
                                   INNER JOIN employees e ON vr.employee_id = e.id
                                   ORDER BY vr.created_at DESC");
            $stmt->execute();
            $vacation_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = 'Erro ao processar solicitação: ' . $e->getMessage();
        }
    }
}

$status_filter = $_GET['status'] ?? '';

// Estatísticas
$total    = count($vacation_requests);
$pending  = count(array_filter($vacation_requests, fn($r) => $r['status'] === 'Pendente'));
$approved = count(array_filter($vacation_requests, fn($r) => $r['status'] === 'Aprovado'));
$rejected = count(array_filter($vacation_requests, fn($r) => $r['status'] === 'Rejeitado'));
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Férias — PAP Market</title>
    <link rel="icon" href="/assets/icons/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>(function(){const t=localStorage.getItem('pap-theme')||'dark';document.documentElement.setAttribute('data-theme',t);})()</script>
    <style>
    :root {
        --bg:   #060606; --bg2: #0d0d0d; --bg3: #141414;
        --border: #1f1f1f; --border2: #2a2a2a;
        --txt:  #eeeeee; --txt2: #8a8a98; --txt3: #50505a;
        --success: #4ade80; --warning: #facc15; --danger: #f87171;
    }
    [data-theme="light"] {
        --bg:   #ffffff; --bg2: #f7f7f9; --bg3: #efeff2;
        --border: #e2e2e8; --border2: #d0d0da;
        --txt:  #111116; --txt2: #5a5a6e; --txt3: #8a8a9e;
        --success: #16a34a; --warning: #ca8a04; --danger: #dc2626;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { -webkit-font-smoothing: antialiased; }
    body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--txt); min-height: 100vh; }
    a { color: inherit; text-decoration: none; }

    .page { max-width: 1300px; margin: 0 auto; padding: 48px 32px 64px; position: relative; }

    .bg-deco {
        position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%);
        font-size: clamp(160px, 20vw, 280px); font-weight: 800;
        letter-spacing: -.08em; color: var(--bg2);
        pointer-events: none; user-select: none; z-index: 0; opacity: .5;
    }
    .content { position: relative; z-index: 1; }

    .nav { display: flex; align-items: center; gap: 16px; margin-bottom: 40px; flex-wrap: wrap; }
    .back-btn {
        display: inline-flex; align-items: center; gap: 8px;
        font-size: 11px; font-weight: 600; letter-spacing: .15em; text-transform: uppercase;
        color: var(--txt3); border: 1px solid var(--border); border-radius: 8px;
        padding: 9px 16px; background: var(--bg2); transition: border-color .15s, color .15s;
    }
    .back-btn:hover { border-color: var(--border2); color: var(--txt); }

    .page-head { margin-bottom: 32px; }
    .page-head h1 {
        font-size: clamp(28px, 4vw, 42px); font-weight: 800;
        letter-spacing: -.04em; line-height: 1.1; color: var(--txt);
    }
    .page-head p { color: var(--txt3); font-size: 13px; margin-top: 6px; }

    /* Stats */
    .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 28px; }
    .stat-card {
        background: var(--bg2); border: 1px solid var(--border);
        border-radius: 12px; padding: 18px 20px;
    }
    .stat-card:hover { border-color: var(--border2); }
    .stat-val { font-size: 28px; font-weight: 800; letter-spacing: -.04em; line-height: 1; margin-bottom: 4px; }
    .stat-lbl { font-size: 11px; font-weight: 600; letter-spacing: .1em; text-transform: uppercase; color: var(--txt3); }

    /* Alerts */
    .alert {
        padding: 13px 18px; border-radius: 10px; font-size: 13px; font-weight: 500; margin-bottom: 20px;
        display: flex; align-items: center; gap: 10px;
    }
    .alert-success { background: rgba(74,222,128,.08); color: var(--success); border: 1px solid rgba(74,222,128,.2); }
    .alert-danger  { background: rgba(248,113,113,.08); color: var(--danger);  border: 1px solid rgba(248,113,113,.2); }

    /* Filter card */
    .filter-card {
        background: var(--bg2); border: 1px solid var(--border); border-radius: 12px;
        padding: 16px 20px; margin-bottom: 20px;
        display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    }
    .filter-card label { font-size: 11px; font-weight: 600; letter-spacing: .1em; text-transform: uppercase; color: var(--txt3); }
    .filter-card select {
        background: var(--bg3); border: 1px solid var(--border); border-radius: 8px;
        color: var(--txt); font-size: 13px; padding: 8px 12px; font-family: inherit;
    }
    .filter-card select:focus { outline: none; border-color: var(--border2); }
    .filter-btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 8px 14px; background: var(--txt); color: var(--bg);
        border: none; border-radius: 8px; font-size: 12px; font-weight: 600;
        cursor: pointer; font-family: inherit; letter-spacing: .05em; transition: opacity .15s;
    }
    .filter-btn:hover { opacity: .85; }
    .cal-link {
        margin-left: auto; display: inline-flex; align-items: center; gap: 6px;
        padding: 8px 14px; background: var(--bg3); border: 1px solid var(--border);
        border-radius: 8px; font-size: 12px; font-weight: 600; color: var(--txt2);
        transition: border-color .15s, color .15s;
    }
    .cal-link:hover { border-color: var(--border2); color: var(--txt); }

    /* Table card */
    .table-card { background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
    .table-card-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 16px 20px; border-bottom: 1px solid var(--border);
    }
    .table-card-title { font-size: 14px; font-weight: 700; letter-spacing: -.02em; }
    .table-card-count { font-size: 11px; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; color: var(--txt3); }

    table.vac { width: 100%; border-collapse: collapse; }
    table.vac th {
        padding: 10px 16px; text-align: left; font-size: 10px; font-weight: 600;
        letter-spacing: .12em; text-transform: uppercase; color: var(--txt3);
        background: var(--bg3); border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }
    table.vac td { padding: 13px 16px; border-bottom: 1px solid var(--border); font-size: 13px; vertical-align: middle; }
    table.vac tbody tr:last-child td { border-bottom: none; }
    table.vac tbody tr:hover td { background: var(--bg3); }

    .emp-name { font-weight: 600; }
    .emp-dept { font-size: 11px; color: var(--txt3); margin-top: 2px; }
    .type-badge {
        display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 500;
        background: var(--bg3); border: 1px solid var(--border); color: var(--txt2);
    }
    .status-badge {
        padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 600;
        letter-spacing: .06em; text-transform: uppercase; display: inline-block;
    }
    .s-pendente  { background: rgba(250,204,21,.1);  color: var(--warning); border: 1px solid rgba(250,204,21,.2); }
    .s-aprovado  { background: rgba(74,222,128,.1);  color: var(--success); border: 1px solid rgba(74,222,128,.2); }
    .s-rejeitado { background: rgba(248,113,113,.1); color: var(--danger);  border: 1px solid rgba(248,113,113,.2); }

    .actions { display: flex; gap: 6px; flex-wrap: wrap; }
    .action-btn {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 6px 12px; border-radius: 7px; font-size: 11px; font-weight: 600;
        border: 1px solid var(--border); background: var(--bg3); color: var(--txt2);
        cursor: pointer; font-family: inherit; transition: background .15s, color .15s, border-color .15s;
        white-space: nowrap;
    }
    .action-btn:hover { border-color: var(--border2); color: var(--txt); background: var(--bg2); }
    .action-btn.approve:hover { background: rgba(74,222,128,.1); color: var(--success); border-color: rgba(74,222,128,.3); }
    .action-btn.reject:hover  { background: rgba(248,113,113,.1); color: var(--danger);  border-color: rgba(248,113,113,.3); }

    .empty { text-align: center; padding: 64px 20px; color: var(--txt3); }

    /* Modal */
    .modal-overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,.6); backdrop-filter: blur(4px);
        z-index: 1000; align-items: center; justify-content: center;
    }
    .modal-overlay.active { display: flex; }
    .modal-box {
        background: var(--bg2); border: 1px solid var(--border2);
        border-radius: 14px; padding: 28px; max-width: 480px; width: 90%;
        box-shadow: 0 20px 60px rgba(0,0,0,.6);
    }
    .modal-box h3 { font-size: 18px; font-weight: 700; letter-spacing: -.02em; margin-bottom: 20px; }
    .modal-label {
        display: block; font-size: 11px; font-weight: 600; letter-spacing: .1em;
        text-transform: uppercase; color: var(--txt3); margin-bottom: 8px;
    }
    .modal-textarea {
        width: 100%; padding: 10px 14px; background: var(--bg3);
        border: 1px solid var(--border); border-radius: 8px; color: var(--txt);
        font-size: 13px; font-family: inherit; resize: vertical; min-height: 80px;
    }
    .modal-textarea:focus { outline: none; border-color: var(--border2); }
    .modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
    .modal-cancel {
        padding: 8px 16px; background: var(--bg3); border: 1px solid var(--border);
        border-radius: 8px; color: var(--txt2); cursor: pointer; font-size: 12px; font-weight: 600;
        font-family: inherit; transition: color .15s;
    }
    .modal-cancel:hover { color: var(--txt); }
    .modal-reject-btn {
        padding: 8px 16px; background: rgba(248,113,113,.15); border: 1px solid rgba(248,113,113,.3);
        border-radius: 8px; color: var(--danger); cursor: pointer; font-size: 12px; font-weight: 600;
        font-family: inherit; transition: background .15s;
    }
    .modal-reject-btn:hover { background: rgba(248,113,113,.25); }

    /* Theme toggle */
    .theme-toggle {
        position: fixed; bottom: 24px; right: 24px; z-index: 200;
        width: 38px; height: 38px; border-radius: 50%;
        background: var(--bg2); border: 1px solid var(--border);
        color: var(--txt2); cursor: pointer; display: flex; align-items: center; justify-content: center;
        font-size: 16px; transition: border-color .15s, color .15s; box-shadow: 0 4px 12px rgba(0,0,0,.3);
    }
    .theme-toggle:hover { border-color: var(--border2); color: var(--txt); }

    @media (max-width: 900px) {
        .stats { grid-template-columns: repeat(2, 1fr); }
        .page { padding: 32px 16px 48px; }
    }
    </style>
</head>
<body>
<div class="bg-deco">RH</div>

<div class="page">
<div class="content">

    <!-- Nav -->
    <div class="nav">
        <a href="/admin/rh/equipa.php" class="back-btn">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Voltar à Equipa
        </a>
    </div>

    <!-- Heading -->
    <div class="page-head">
        <h1>Gestão de Férias</h1>
        <p><?= $total ?> pedido<?= $total !== 1 ? 's' : '' ?> registado<?= $total !== 1 ? 's' : '' ?></p>
    </div>

    <!-- Stats -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-val"><?= $total ?></div>
            <div class="stat-lbl">Total</div>
        </div>
        <div class="stat-card">
            <div class="stat-val" style="color:var(--warning)"><?= $pending ?></div>
            <div class="stat-lbl">Pendentes</div>
        </div>
        <div class="stat-card">
            <div class="stat-val" style="color:var(--success)"><?= $approved ?></div>
            <div class="stat-lbl">Aprovados</div>
        </div>
        <div class="stat-card">
            <div class="stat-val" style="color:var(--danger)"><?= $rejected ?></div>
            <div class="stat-lbl">Rejeitados</div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($message): ?>
    <div class="alert alert-success">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Filter -->
    <div class="filter-card">
        <label for="statusFilter">Estado</label>
        <form method="get" style="display:contents">
            <select name="status" id="statusFilter">
                <option value="" <?= $status_filter === '' ? 'selected' : '' ?>>Todos</option>
                <option value="Pendente"  <?= $status_filter === 'Pendente'  ? 'selected' : '' ?>>Pendente</option>
                <option value="Aprovado"  <?= $status_filter === 'Aprovado'  ? 'selected' : '' ?>>Aprovado</option>
                <option value="Rejeitado" <?= $status_filter === 'Rejeitado' ? 'selected' : '' ?>>Rejeitado</option>
            </select>
            <button type="submit" class="filter-btn">Filtrar</button>
        </form>
        <a href="/admin/vacation/calendario.php" class="cal-link">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Mapa de Férias
        </a>
    </div>

    <!-- Table -->
    <div class="table-card">
        <div class="table-card-header">
            <span class="table-card-title">Pedidos de Férias e Licenças</span>
            <?php
            $shown = array_filter($vacation_requests, fn($vr) => !$status_filter || $vr['status'] === $status_filter);
            ?>
            <span class="table-card-count"><?= count($shown) ?> registo<?= count($shown) !== 1 ? 's' : '' ?></span>
        </div>

        <?php if (empty($shown)): ?>
        <div class="empty">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:.3"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <div style="font-size:14px;font-weight:600;margin-bottom:4px">Sem pedidos</div>
            <div style="font-size:12px">Nenhum pedido encontrado para os filtros selecionados.</div>
        </div>
        <?php else: ?>
        <table class="vac">
            <thead>
                <tr>
                    <th>Colaborador</th>
                    <th>Tipo</th>
                    <th>Início</th>
                    <th>Fim</th>
                    <th>Dias</th>
                    <th>Estado</th>
                    <th>Solicitado</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vacation_requests as $vr):
                    if ($status_filter && $vr['status'] !== $status_filter) continue;
                    $vacation_type = trim((string)($vr['vacation_type'] ?? $vr['type'] ?? 'Férias')) ?: 'Férias';
                    $number_of_days = $vr['number_of_days'] ?? $vr['days'] ?? null;
                    if ($number_of_days === null || $number_of_days === '') {
                        $number_of_days = (!empty($vr['start_date']) && !empty($vr['end_date']) && function_exists('calculate_business_days'))
                            ? calculate_business_days($vr['start_date'], $vr['end_date']) : 0;
                    }
                    $sc = match($vr['status']) {
                        'Pendente'  => 's-pendente',
                        'Aprovado'  => 's-aprovado',
                        'Rejeitado' => 's-rejeitado',
                        default     => ''
                    };
                ?>
                <tr>
                    <td>
                        <div class="emp-name"><?= htmlspecialchars($vr['employee_name']) ?></div>
                        <div class="emp-dept"><?= htmlspecialchars($vr['department']) ?></div>
                    </td>
                    <td><span class="type-badge"><?= htmlspecialchars($vacation_type) ?></span></td>
                    <td><?= date('d/m/Y', strtotime($vr['start_date'])) ?></td>
                    <td><?= date('d/m/Y', strtotime($vr['end_date'])) ?></td>
                    <td><strong><?= (int)$number_of_days ?></strong> dias</td>
                    <td><span class="status-badge <?= $sc ?>"><?= htmlspecialchars($vr['status']) ?></span></td>
                    <td style="font-size:12px;color:var(--txt3)"><?= date('d/m/Y H:i', strtotime($vr['created_at'])) ?></td>
                    <td>
                        <?php if ($vr['status'] === 'Pendente'): ?>
                        <div class="actions">
                            <form method="post" style="display:contents">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="vacation_id" value="<?= $vr['id'] ?>">
                                <button type="submit" class="action-btn approve" onclick="return confirm('Aprovar férias de <?= htmlspecialchars(addslashes($vr['employee_name'])) ?>?')">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    Aprovar
                                </button>
                            </form>
                            <button class="action-btn reject" onclick="openRejectModal(<?= $vr['id'] ?>)">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                Rejeitar
                            </button>
                        </div>
                        <?php else: ?>
                        <span style="font-size:12px;color:var(--txt3)">Processado</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div><!-- .content -->
</div><!-- .page -->

<!-- Modal de rejeição -->
<div id="rejectModal" class="modal-overlay">
    <div class="modal-box">
        <h3>Rejeitar Solicitação</h3>
        <form method="post" id="rejectForm">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="vacation_id" id="vacationIdInput">
            <label class="modal-label" for="rejection_reason">Motivo da Rejeição</label>
            <textarea id="rejection_reason" name="rejection_reason" class="modal-textarea" placeholder="Indique o motivo..."></textarea>
            <div class="modal-footer">
                <button type="button" class="modal-cancel" onclick="closeRejectModal()">Cancelar</button>
                <button type="submit" class="modal-reject-btn">Rejeitar</button>
            </div>
        </form>
    </div>
</div>

<!-- Theme toggle -->
<button class="theme-toggle" id="themeToggle" title="Alternar tema"><span class="icon-moon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></span><span class="icon-sun"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg></span></button>

<script>
function openRejectModal(id) {
    document.getElementById('vacationIdInput').value = id;
    document.getElementById('rejectModal').classList.add('active');
}
function closeRejectModal() {
    document.getElementById('rejectModal').classList.remove('active');
    document.getElementById('rejection_reason').value = '';
}
document.getElementById('rejectModal').addEventListener('click', function(e){
    if (e.target === this) closeRejectModal();
});

// Theme
const themeToggle = document.getElementById('themeToggle');
function applyTheme(t) {
    document.documentElement.setAttribute('data-theme', t);
    // theme icon updated via CSS
    localStorage.setItem('pap-theme', t);
}
applyTheme(localStorage.getItem('pap-theme') || 'dark');
themeToggle.addEventListener('click', function(){
    applyTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
});
</script>
</body>
</html>
