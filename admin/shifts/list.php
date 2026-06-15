<?php
/**
 * Página de Gestão de Turnos
 * admin/shifts/list.php
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $data = [
        'name'           => $_POST['name'] ?? '',
        'start_time'     => $_POST['start_time'] ?? '',
        'end_time'       => $_POST['end_time'] ?? '',
        'break_duration' => $_POST['break_duration'] ?? 60,
        'description'    => $_POST['description'] ?? ''
    ];
    if (empty($data['name']) || empty($data['start_time']) || empty($data['end_time'])) {
        $error = 'Nome, hora início e hora fim são obrigatórios.';
    } else {
        if (create_shift($data)) {
            $message = 'Turno criado com sucesso.';
        } else {
            $error = 'Erro ao criar turno.';
        }
    }
}

$shifts = list_shifts();
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="/assets/css/design-system.css?v=<?= time() ?>">
  <title>Turnos &mdash; PAP Market</title>
  <script>(function(){var t=localStorage.getItem('pap-theme')||'dark';document.documentElement.setAttribute('data-theme',t)})();</script>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --bg: #060606; --bg2: #0b0b0b; --bg3: #131313;
      --border: #1a1a1a; --border2: #2a2a2a;
      --txt: #ececec; --txt2: #7a7a7a; --txt3: #3e3e3e;
      --btn: #ececec; --btn-txt: #060606;
      --success: #10b981; --warning: #f59e0b; --danger: #f87171;
    }
    [data-theme="light"] {
      --bg: #f5f4f1; --bg2: #ffffff; --bg3: #eeecea;
      --border: #dedad5; --border2: #c8c4be;
      --txt: #111111; --txt2: #5c5c5c; --txt3: #aaaaaa;
      --btn: #111111; --btn-txt: #ffffff;
    }
    html { -webkit-font-smoothing: antialiased; height: 100%; }
    body {
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
      min-height: 100vh; background: var(--bg); color: var(--txt);
      padding: 40px 24px 60px;
    }
    .bg-deco {
      position: fixed; bottom: -120px; right: -40px;
      font-family: 'Plus Jakarta Sans',sans-serif; font-weight: 800;
      font-size: clamp(240px, 28vw, 360px);
      line-height: 1; color: transparent;
      -webkit-text-stroke: 1px rgba(255,255,255,.025);
      user-select: none; pointer-events: none; letter-spacing: -.06em; z-index: 0;
    }
    [data-theme="light"] .bg-deco { -webkit-text-stroke: 1px rgba(0,0,0,.035); }
    .wrap { position: relative; z-index: 1; max-width: 1100px; margin: 0 auto; }

    .topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 48px; flex-wrap: wrap; gap: 16px; }
    .topbar-left { display: flex; align-items: center; gap: 20px; }
    .back-btn {
      display: inline-flex; align-items: center; gap: 8px;
      font-size: 11px; font-weight: 600; letter-spacing: .15em;
      text-transform: uppercase; color: var(--txt3); text-decoration: none;
      border: 1px solid var(--border); border-radius: 8px; padding: 9px 16px;
      background: var(--bg2); transition: border-color .2s, color .2s, transform .2s;
    }
    .back-btn:hover { border-color: var(--border2); color: var(--txt2); transform: translateX(-3px); }
    .page-eyebrow { font-size: 10px; font-weight: 600; letter-spacing: .22em; text-transform: uppercase; color: var(--txt3); }
    .page-title {
      font-family: 'Plus Jakarta Sans',sans-serif; font-weight: 900;
      font-size: clamp(26px, 3vw, 40px); letter-spacing: -.025em;
      color: var(--txt); line-height: 1.1;
    }
    .page-title em { font-style: italic; font-weight: 400; color: var(--txt2); font-size: .72em; }

    .alert {
      display: flex; align-items: center; gap: 10px;
      border-radius: 9px; padding: 13px 16px; font-size: 13px; margin-bottom: 24px; border-left: 2px solid;
    }
    .alert-success { background: rgba(16,185,129,.07); border-color: var(--success); color: var(--success); }
    .alert-error   { background: rgba(248,113,113,.07); border-color: var(--danger); color: var(--danger); }

    .card {
      background: var(--bg2); border: 1px solid var(--border); border-radius: 16px;
      overflow: hidden; margin-bottom: 24px;
      animation: up .4s cubic-bezier(.16,1,.3,1) both;
    }
    .card-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 20px 28px; border-bottom: 1px solid var(--border);
    }
    .card-title {
      font-family: 'Plus Jakarta Sans',sans-serif; font-weight: 700;
      font-size: 15px; letter-spacing: -.02em;
    }
    .card-body { padding: 24px 28px; }

    .form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
    .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-label { font-size: 10px; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; color: var(--txt3); }
    .form-control {
      height: 40px; padding: 0 14px;
      background: var(--bg3); border: 1px solid var(--border);
      border-radius: 8px; font-family: inherit; font-size: 13px; color: var(--txt);
      outline: none; transition: border-color .2s;
    }
    .form-control:focus { border-color: var(--border2); }
    .form-control::placeholder { color: var(--txt3); }

    .submit-btn {
      height: 40px; padding: 0 20px;
      border: none; border-radius: 8px; background: var(--btn); color: var(--btn-txt);
      font-family: inherit; font-size: 11px; font-weight: 700; letter-spacing: .13em;
      text-transform: uppercase; cursor: pointer; display: inline-flex; align-items: center;
      gap: 8px; transition: opacity .15s;
    }
    .submit-btn:hover { opacity: .85; }

    table { width: 100%; border-collapse: collapse; }
    thead th {
      padding: 12px 20px; text-align: left;
      font-size: 9.5px; font-weight: 600; letter-spacing: .2em;
      text-transform: uppercase; color: var(--txt3);
      background: var(--bg3); border-bottom: 1px solid var(--border);
    }
    tbody td { padding: 15px 20px; border-bottom: 1px solid var(--border); font-size: 13.5px; color: var(--txt); vertical-align: middle; }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr { transition: background .15s; }
    tbody tr:hover td { background: var(--bg3); }

    .shift-pill {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 4px 12px; border-radius: 20px;
      font-size: 12px; font-weight: 600;
      background: var(--bg3); border: 1px solid var(--border); color: var(--txt2);
    }
    .duration-pill {
      display: inline-block; padding: 4px 10px; border-radius: 6px;
      font-size: 12px; font-weight: 600;
      background: rgba(16,185,129,.1); color: var(--success); border: 1px solid rgba(16,185,129,.2);
    }

    .empty-state { text-align: center; padding: 64px 32px; }
    .empty-icon { font-size: 48px; opacity: .15; margin-bottom: 16px; }
    .empty-title { font-weight: 700; font-size: 18px; color: var(--txt2); margin-bottom: 8px; }
    .empty-sub { font-size: 12.5px; color: var(--txt3); }

    .th-btn {
      position: fixed; bottom: 20px; right: 20px;
      width: 36px; height: 36px; background: var(--bg2); border: 1px solid var(--border);
      border-radius: 50%; display: grid; place-items: center; font-size: 14px; cursor: pointer; z-index: 99;
    }
    .t-sun { display: none; }
    [data-theme="light"] .t-sun { display: block; }
    [data-theme="light"] .t-moon { display: none; }

    @keyframes up { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }
    @media (max-width: 768px) {
      .form-grid { grid-template-columns: 1fr 1fr; }
      .form-grid-2 { grid-template-columns: 1fr; }
    }
    @media (max-width: 520px) {
      .form-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<div class="bg-deco">RH</div>

<div class="wrap">

  <div class="topbar">
    <div class="topbar-left">
      <a href="/admin/rh/equipa.php" class="back-btn">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Voltar à Equipa
      </a>
      <div>
        <div class="page-eyebrow">PAP Market &mdash; Recursos Humanos</div>
        <h1 class="page-title">Gestão de Turnos <em>da equipa</em></h1>
      </div>
    </div>
  </div>

  <?php if ($message): ?>
  <div class="alert alert-success">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
    <?= htmlspecialchars($message) ?>
  </div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert alert-error">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <!-- Formulário novo turno -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Criar Novo Turno</h3>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="create">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Nome do Turno *</label>
            <input type="text" name="name" class="form-control" required placeholder="Ex: Manhã">
          </div>
          <div class="form-group">
            <label class="form-label">Hora Início *</label>
            <input type="time" name="start_time" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Hora Fim *</label>
            <input type="time" name="end_time" class="form-control" required>
          </div>
        </div>
        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label">Pausa (minutos)</label>
            <input type="number" name="break_duration" class="form-control" value="60" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Descrição</label>
            <input type="text" name="description" class="form-control" placeholder="Descrição adicional (opcional)">
          </div>
        </div>
        <div style="margin-top: 20px;">
          <button type="submit" class="submit-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Criar Turno
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Lista de turnos -->
  <div class="card" style="animation-delay:.1s">
    <div class="card-header">
      <h3 class="card-title">Turnos Disponíveis</h3>
      <span style="font-size:11px;font-weight:600;letter-spacing:.15em;text-transform:uppercase;color:var(--txt3)"><?= count($shifts) ?> turno<?= count($shifts) !== 1 ? 's' : '' ?></span>
    </div>

    <?php if (!empty($shifts)): ?>
    <table>
      <thead>
        <tr>
          <th>Nome</th>
          <th>Horário</th>
          <th>Duração</th>
          <th>Pausa</th>
          <th>Descrição</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($shifts as $shift):
          $start = new DateTime('2000-01-01 ' . $shift['start_time']);
          $end   = new DateTime('2000-01-01 ' . $shift['end_time']);
          $diff  = $end->diff($start);
          $hours = $diff->h + ($diff->i / 60);
        ?>
        <tr>
          <td><span class="shift-pill">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <?= htmlspecialchars($shift['name']) ?>
          </span></td>
          <td style="font-weight:600;font-size:14px">
            <?= substr($shift['start_time'], 0, 5) ?>
            <span style="color:var(--txt3);font-weight:400;margin:0 6px">→</span>
            <?= substr($shift['end_time'], 0, 5) ?>
          </td>
          <td><span class="duration-pill"><?= number_format($hours, 1) ?>h</span></td>
          <td style="color:var(--txt2)"><?= $shift['break_duration'] ?> min</td>
          <td style="color:var(--txt2);font-size:13px"><?= htmlspecialchars($shift['description'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state">
      <div class="empty-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <div class="empty-title">Nenhum turno criado</div>
      <div class="empty-sub">Crie o primeiro turno usando o formulário acima.</div>
    </div>
    <?php endif; ?>
  </div>

</div>

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
