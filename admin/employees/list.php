<?php
/**
 * Página de Gestão de Colaboradores
 * admin/employees/list.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../modules/rh.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$filters = [
    'status'     => $_GET['status']     ?? '',
    'department' => $_GET['department'] ?? '',
    'search'     => $_GET['search']     ?? ''
];

$employees = list_employees($filters);

$message = '';
$msg_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['employee_id'])) {
        if ($_POST['action'] === 'delete') {
            if (delete_employee($_POST['employee_id'])) {
                $message = 'Colaborador desativado com sucesso.';
            } else {
                $message  = 'Erro ao desativar colaborador.';
                $msg_type = 'error';
            }
            $employees = list_employees($filters);
        }
    }
}

$total     = count($employees);
$ativos    = count(array_filter($employees, fn($e) => ($e['status'] ?? '') === 'Ativo'));
$inativos  = count(array_filter($employees, fn($e) => ($e['status'] ?? '') === 'Inativo'));
$licenca   = count(array_filter($employees, fn($e) => ($e['status'] ?? '') === 'Licença'));
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/assets/css/design-system.css?v=<?= time() ?>">
  <title>Colaboradores &mdash; PAP Market</title>
  <script>(function(){var t=localStorage.getItem('pap-theme')||'dark';document.documentElement.setAttribute('data-theme',t)})();</script>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:      #060606;
      --bg2:     #0b0b0b;
      --bg3:     #131313;
      --border:  #1a1a1a;
      --border2: #2a2a2a;
      --txt:     #ececec;
      --txt2:    #7a7a7a;
      --txt3:    #3e3e3e;
      --btn:     #ececec;
      --btn-txt: #060606;
      --success: #10b981;
      --warning: #f59e0b;
      --danger:  #f87171;
      --info:    #38bdf8;
    }
    [data-theme="light"] {
      --bg:      #f5f4f1;
      --bg2:     #ffffff;
      --bg3:     #eeecea;
      --border:  #dedad5;
      --border2: #c8c4be;
      --txt:     #111111;
      --txt2:    #5c5c5c;
      --txt3:    #aaaaaa;
      --btn:     #111111;
      --btn-txt: #ffffff;
    }

    html { -webkit-font-smoothing: antialiased; height: 100%; }
    body {
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
      min-height: 100vh;
      background: var(--bg);
      color: var(--txt);
      padding: 40px 24px 60px;
    }

    .bg-deco {
      position: fixed; bottom: -120px; right: -40px;
      font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;
      font-size: clamp(240px, 28vw, 360px);
      font-weight: 900; line-height: 1; color: transparent;
      -webkit-text-stroke: 1px rgba(255,255,255,.025);
      user-select: none; pointer-events: none;
      letter-spacing: -.06em; z-index: 0;
    }
    [data-theme="light"] .bg-deco { -webkit-text-stroke: 1px rgba(0,0,0,.035); }

    .wrap { position: relative; z-index: 1; max-width: 1280px; margin: 0 auto; }

    /* ── Top bar ── */
    .topbar {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 48px; flex-wrap: wrap; gap: 16px;
    }
    .topbar-left { display: flex; align-items: center; gap: 20px; }
    .back-btn {
      display: inline-flex; align-items: center; gap: 8px;
      font-size: 11px; font-weight: 600; letter-spacing: .15em;
      text-transform: uppercase; color: var(--txt3);
      text-decoration: none;
      border: 1px solid var(--border); border-radius: 8px;
      padding: 9px 16px;
      background: var(--bg2);
      transition: border-color .2s, color .2s, transform .2s;
    }
    .back-btn:hover { border-color: var(--border2); color: var(--txt2); transform: translateX(-3px); }

    .page-eyebrow {
      font-size: 10px; font-weight: 600; letter-spacing: .22em;
      text-transform: uppercase; color: var(--txt3);
    }
    .page-title {
      font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;
      font-size: clamp(26px, 3vw, 40px);
      font-weight: 900; letter-spacing: -.025em;
      color: var(--txt); line-height: 1.1;
    }
    .page-title em {
      font-style: italic; font-weight: 400; color: var(--txt2);
      font-size: .72em;
    }

    .new-btn {
      display: inline-flex; align-items: stretch;
      background: var(--btn); color: var(--btn-txt);
      border: none; border-radius: 10px; overflow: hidden;
      text-decoration: none; cursor: pointer;
      box-shadow: 0 4px 14px rgba(0,0,0,.3);
      transition: transform .18s cubic-bezier(.34,1.4,.64,1), box-shadow .2s;
      position: relative;
    }
    .new-btn:hover { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(0,0,0,.5); }
    .new-btn::before {
      content: ''; position: absolute;
      top: 0; left: -80%; width: 50%; height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,.12), transparent);
      transform: skewX(-18deg); transition: left .55s ease;
    }
    .new-btn:hover::before { left: 140%; }
    .new-btn-body {
      padding: 0 18px; height: 44px; display: flex; align-items: center;
      font-size: 10.5px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase;
    }
    .new-btn-sep { width: 1px; margin: 10px 0; background: rgba(0,0,0,.15); }
    [data-theme="light"] .new-btn-sep { background: rgba(255,255,255,.25); }
    .new-btn-icon { width: 44px; display: flex; align-items: center; justify-content: center; font-size: 18px; }

    /* ── Stats row ── */
    .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 32px; }
    .stat-card {
      background: var(--bg2); border: 1px solid var(--border);
      border-radius: 14px; padding: 22px 24px;
      animation: up .4s cubic-bezier(.16,1,.3,1) both;
    }
    .stat-num {
      font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;
      font-size: 36px; font-weight: 900;
      letter-spacing: -.04em; line-height: 1;
      margin-bottom: 6px;
    }
    .stat-lbl { font-size: 10px; font-weight: 600; letter-spacing: .18em; text-transform: uppercase; color: var(--txt3); }

    /* ── Alert ── */
    .alert {
      display: flex; align-items: center; gap: 10px;
      border-radius: 9px; padding: 13px 16px;
      font-size: 13px; margin-bottom: 24px;
      border-left: 2px solid;
    }
    .alert-success { background: rgba(16,185,129,.07); border-color: var(--success); color: var(--success); }
    .alert-error   { background: rgba(248,113,113,.07); border-color: var(--danger);  color: var(--danger); }

    /* ── Filter card ── */
    .filter-card {
      background: var(--bg2); border: 1px solid var(--border);
      border-radius: 14px; padding: 20px 24px;
      margin-bottom: 24px;
    }
    .filter-form { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
    .filter-form input,
    .filter-form select {
      height: 40px; padding: 0 14px;
      background: var(--bg3); border: 1px solid var(--border);
      border-radius: 8px; font-family: inherit;
      font-size: 13px; color: var(--txt); outline: none;
      transition: border-color .2s;
      min-width: 180px;
    }
    .filter-form input:focus,
    .filter-form select:focus { border-color: var(--border2); }
    .filter-form input::placeholder { color: var(--txt3); }

    .fbtn {
      height: 40px; padding: 0 18px;
      border: none; border-radius: 8px;
      font-family: inherit; font-size: 11px; font-weight: 700;
      letter-spacing: .13em; text-transform: uppercase;
      cursor: pointer; text-decoration: none;
      display: inline-flex; align-items: center;
      transition: opacity .15s;
    }
    .fbtn:hover { opacity: .8; }
    .fbtn-primary { background: var(--btn); color: var(--btn-txt); }
    .fbtn-ghost { background: transparent; border: 1px solid var(--border); color: var(--txt2); }

    /* ── Table card ── */
    .table-card {
      background: var(--bg2); border: 1px solid var(--border);
      border-radius: 16px; overflow: hidden;
      animation: up .5s .1s cubic-bezier(.16,1,.3,1) both;
    }
    .table-head {
      display: flex; align-items: center; justify-content: space-between;
      padding: 20px 28px; border-bottom: 1px solid var(--border);
    }
    .table-head-title {
      font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;
      font-size: 18px; font-weight: 700; letter-spacing: -.02em;
    }
    .table-head-count {
      font-size: 11px; font-weight: 600; letter-spacing: .15em;
      text-transform: uppercase; color: var(--txt3);
    }

    table { width: 100%; border-collapse: collapse; }
    thead th {
      padding: 12px 20px; text-align: left;
      font-size: 9.5px; font-weight: 600; letter-spacing: .2em;
      text-transform: uppercase; color: var(--txt3);
      background: var(--bg3);
      border-bottom: 1px solid var(--border);
    }
    tbody td {
      padding: 15px 20px;
      border-bottom: 1px solid var(--border);
      font-size: 13.5px; color: var(--txt);
      vertical-align: middle;
    }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr { transition: background .15s; }
    tbody tr:hover td { background: var(--bg3); }

    .emp-name { font-weight: 600; font-size: 14px; }
    .emp-sub  { font-size: 11.5px; color: var(--txt2); margin-top: 2px; }

    .avatar {
      width: 38px; height: 38px; border-radius: 50%;
      background: var(--bg3); border: 1px solid var(--border);
      display: flex; align-items: center; justify-content: center;
      font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;
      font-size: 14px; font-weight: 700; color: var(--txt2);
      flex-shrink: 0;
    }
    .emp-cell { display: flex; align-items: center; gap: 12px; }

    .badge {
      display: inline-block; padding: 4px 10px;
      border-radius: 20px; font-size: 10.5px; font-weight: 600;
      letter-spacing: .06em; text-transform: uppercase;
      border: 1px solid;
    }
    .badge-ativo    { background: rgba(16,185,129,.1);  color: var(--success); border-color: rgba(16,185,129,.2); }
    .badge-inativo  { background: rgba(248,113,113,.1); color: var(--danger);  border-color: rgba(248,113,113,.2); }
    .badge-licenca  { background: rgba(245,158,11,.1);  color: var(--warning); border-color: rgba(245,158,11,.2); }

    .dept-pill {
      display: inline-block; padding: 3px 10px;
      border-radius: 6px; font-size: 11px; font-weight: 500;
      background: var(--bg3); border: 1px solid var(--border);
      color: var(--txt2);
    }

    .action-group { display: flex; gap: 6px; align-items: center; }
    .act-btn {
      height: 30px; padding: 0 12px;
      border: 1px solid var(--border); border-radius: 6px;
      font-family: inherit; font-size: 10.5px; font-weight: 600;
      letter-spacing: .07em; text-transform: uppercase;
      cursor: pointer; text-decoration: none;
      display: inline-flex; align-items: center; gap: 5px;
      background: var(--bg3); color: var(--txt2);
      transition: border-color .15s, color .15s, background .15s;
    }
    .act-btn:hover { border-color: var(--border2); color: var(--txt); background: var(--bg2); }
    .act-btn-del { color: var(--danger); border-color: rgba(248,113,113,.2); background: rgba(248,113,113,.05); }
    .act-btn-del:hover { background: rgba(248,113,113,.1); border-color: rgba(248,113,113,.4); color: var(--danger); }

    /* ── Empty ── */
    .empty-state {
      text-align: center; padding: 64px 32px;
    }
    .empty-icon {
      font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;
      font-size: 64px; opacity: .15; margin-bottom: 16px; line-height: 1;
    }
    .empty-title {
      font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-style:italic; font-size: 20px; color: var(--txt2);
      margin-bottom: 8px;
    }
    .empty-sub { font-size: 12.5px; color: var(--txt3); }

    /* ── Theme btn ── */
    .th-btn {
      position: fixed; bottom: 20px; right: 20px;
      width: 36px; height: 36px;
      background: var(--bg2); border: 1px solid var(--border);
      border-radius: 50%; display: grid; place-items: center;
      font-size: 14px; cursor: pointer; z-index: 99;
      transition: border-color .2s, transform .2s;
    }
    .th-btn:hover { border-color: var(--border2); transform: scale(1.08); }
    .t-sun { display: none; }
    [data-theme="light"] .t-sun  { display: block; }
    [data-theme="light"] .t-moon { display: none; }

    @keyframes up {
      from { opacity: 0; transform: translateY(18px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 900px) {
      .stats { grid-template-columns: repeat(2,1fr); }
      thead th:nth-child(3), tbody td:nth-child(3),
      thead th:nth-child(7), tbody td:nth-child(7) { display: none; }
    }
    @media (max-width: 600px) {
      .stats { grid-template-columns: repeat(2,1fr); }
      .new-btn-body { display: none; }
      .new-btn-sep  { display: none; }
    }
  </style>
</head>
<body>

<div class="bg-deco">RH</div>

<div class="wrap">

  <!-- Top bar -->
  <div class="topbar">
    <div class="topbar-left">
      <a href="/admin/rh/equipa.php" class="back-btn">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Voltar
      </a>
      <div>
        <div class="page-eyebrow">PAP Market &mdash; Recursos Humanos</div>
        <h1 class="page-title">Colaboradores <em>da equipa</em></h1>
      </div>
    </div>
    <a href="/admin/employees/create.php" class="new-btn">
      <span class="new-btn-body">Novo Colaborador</span>
      <span class="new-btn-sep"></span>
      <span class="new-btn-icon">+</span>
    </a>
  </div>

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card" style="animation-delay:.0s">
      <div class="stat-num"><?= $total ?></div>
      <div class="stat-lbl">Total</div>
    </div>
    <div class="stat-card" style="animation-delay:.05s">
      <div class="stat-num" style="color:var(--success)"><?= $ativos ?></div>
      <div class="stat-lbl">Ativos</div>
    </div>
    <div class="stat-card" style="animation-delay:.1s">
      <div class="stat-num" style="color:var(--warning)"><?= $licenca ?></div>
      <div class="stat-lbl">Em Licença</div>
    </div>
    <div class="stat-card" style="animation-delay:.15s">
      <div class="stat-num" style="color:var(--danger)"><?= $inativos ?></div>
      <div class="stat-lbl">Inativos</div>
    </div>
  </div>

  <?php if ($message): ?>
  <div class="alert alert-<?= $msg_type === 'success' ? 'success' : 'error' ?>">
    <?= $msg_type === 'success' ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>' : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' ?> <?= htmlspecialchars($message) ?>
  </div>
  <?php endif; ?>

  <!-- Filters -->
  <div class="filter-card">
    <form method="get" class="filter-form">
      <input type="text" name="search" placeholder="Pesquisar nome, email ou NIF…"
             value="<?= htmlspecialchars($filters['search']) ?>">
      <select name="status">
        <option value="">Todos os estados</option>
        <option value="Ativo"   <?= $filters['status']==='Ativo'   ?'selected':'' ?>>Ativo</option>
        <option value="Inativo" <?= $filters['status']==='Inativo' ?'selected':'' ?>>Inativo</option>
        <option value="Licença" <?= $filters['status']==='Licença' ?'selected':'' ?>>Licença</option>
      </select>
      <select name="department">
        <option value="">Todos os departamentos</option>
        <option value="Caixa"          <?= $filters['department']==='Caixa'          ?'selected':'' ?>>Caixa</option>
        <option value="Reposição"      <?= $filters['department']==='Reposição'      ?'selected':'' ?>>Reposição</option>
        <option value="Limpeza"        <?= $filters['department']==='Limpeza'        ?'selected':'' ?>>Limpeza</option>
        <option value="Administração"  <?= $filters['department']==='Administração'  ?'selected':'' ?>>Administração</option>
      </select>
      <button type="submit" class="fbtn fbtn-primary">Filtrar</button>
      <a href="/admin/employees/list.php" class="fbtn fbtn-ghost">Limpar</a>
    </form>
  </div>

  <!-- Table -->
  <div class="table-card">
    <div class="table-head">
      <div class="table-head-title">Equipa</div>
      <div class="table-head-count"><?= $total ?> colaborador<?= $total !== 1 ? 'es' : '' ?></div>
    </div>

    <?php if (empty($employees)): ?>
    <div class="empty-state">
      <div class="empty-icon">&#9786;</div>
      <div class="empty-title">Nenhum colaborador encontrado.</div>
      <div class="empty-sub">Tente ajustar os filtros ou adicione um novo colaborador.</div>
    </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Colaborador</th>
          <th>Departamento</th>
          <th>NIF</th>
          <th>Cargo</th>
          <th>Estado</th>
          <th>Contratação</th>
          <th style="text-align:right">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($employees as $emp): ?>
        <?php
          $name    = $emp['name']       ?? 'N/A';
          $email   = $emp['email']      ?? '';
          $nif     = $emp['nif']        ?? 'N/A';
          $dept    = $emp['department'] ?? 'N/A';
          $pos     = $emp['position']   ?? 'N/A';
          $status  = $emp['status']     ?? 'Inativo';
          $hire    = isset($emp['hire_date']) && $emp['hire_date'] ? date('d/m/Y', strtotime($emp['hire_date'])) : '—';
          $initials = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', trim($name)))));
          $initials = substr($initials, 0, 2);
          $badge_class = match(strtolower($status)) {
              'ativo'   => 'badge-ativo',
              'licença' => 'badge-licenca',
              default   => 'badge-inativo'
          };
        ?>
        <tr>
          <td>
            <div class="emp-cell">
              <div class="avatar"><?= htmlspecialchars($initials) ?></div>
              <div>
                <div class="emp-name"><?= htmlspecialchars($name) ?></div>
                <?php if ($email): ?>
                <div class="emp-sub"><?= htmlspecialchars($email) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td><span class="dept-pill"><?= htmlspecialchars($dept) ?></span></td>
          <td style="color:var(--txt2);font-size:13px"><?= htmlspecialchars($nif) ?></td>
          <td style="color:var(--txt2)"><?= htmlspecialchars($pos) ?></td>
          <td><span class="badge <?= $badge_class ?>"><?= htmlspecialchars($status) ?></span></td>
          <td style="color:var(--txt2);font-size:13px"><?= $hire ?></td>
          <td>
            <div class="action-group" style="justify-content:flex-end">
              <a href="/admin/employees/view.php?id=<?= $emp['id'] ?>" class="act-btn">Ver</a>
              <a href="/admin/employees/edit.php?id=<?= $emp['id'] ?>" class="act-btn">Editar</a>
              <form method="post" style="display:inline" onsubmit="return confirm('Desativar este colaborador?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="employee_id" value="<?= $emp['id'] ?>">
                <button type="submit" class="act-btn act-btn-del">Remover</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</div><!-- /wrap -->

<button class="th-btn" onclick="toggleTheme()" title="Tema">
  <span class="t-moon">&#127769;</span>
  <span class="t-sun">&#9728;</span>
</button>

<script>
function toggleTheme() {
  var h = document.documentElement;
  var n = h.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  h.setAttribute('data-theme', n);
  localStorage.setItem('pap-theme', n);
}
</script>
</body>
</html>
