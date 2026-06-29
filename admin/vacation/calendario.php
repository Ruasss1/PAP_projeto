<?php
/**
 * MAPA DE FÉRIAS - CALENDÁRIO VISUAL
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';
$pdo = db_connect();
$page_title = 'Mapa de Férias';

$year  = intval($_GET['year']  ?? date('Y'));
$month = intval($_GET['month'] ?? date('n'));
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$days_in_month = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
$first_dow     = (int)date('N', mktime(0,0,0,$month,1,$year));

// Carregar férias aprovadas do mês (inclui departamento)
$stmt = $pdo->prepare('
    SELECT vr.*, e.name as emp_name, e.department
    FROM vacation_requests vr
    JOIN employees e ON e.id = vr.employee_id
    WHERE vr.status IN ("Aprovado", "approved")
      AND vr.start_date <= ?
      AND vr.end_date >= ?
    ORDER BY e.name ASC
');
$stmt->execute(["$year-$month-$days_in_month", "$year-$month-01"]);
$vacations = $stmt->fetchAll();

// Índice: dia => [array de dados da féria]
$calendar = [];
foreach ($vacations as $v) {
    $start = max(strtotime($v['start_date']), mktime(0,0,0,$month,1,$year));
    $end   = min(strtotime($v['end_date']),   mktime(0,0,0,$month,$days_in_month,$year));
    for ($ts = $start; $ts <= $end; $ts += 86400) {
        $d = (int)date('j', $ts);
        $calendar[$d][] = [
            'name'       => $v['emp_name'],
            'department' => $v['department'] ?? '',
            'start_date' => $v['start_date'],
            'end_date'   => $v['end_date'],
            'status'     => $v['status'],
            'days'       => (int)((strtotime($v['end_date']) - strtotime($v['start_date'])) / 86400) + 1,
        ];
    }
}

// Paleta monocromática (um tom por pessoa)
$employees_in_month = [];
foreach ($calendar as $day_entries) {
    foreach ($day_entries as $entry) {
        $employees_in_month[$entry['name']] = $entry;
    }
}
$palette = ['#52525b','#71717a','#a1a1aa','#3f3f46','#27272a','#6b7280','#9ca3af','#4b5563','#374151','#d4d4d8'];
$emp_colors = [];
$idx = 0;
foreach ($employees_in_month as $name => $data) {
    $emp_colors[$name] = $palette[$idx % count($palette)];
    $idx++;
}

$month_names = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$prev_m = $month - 1; $prev_y = $year; if ($prev_m < 1)  { $prev_m = 12; $prev_y--; }
$next_m = $month + 1; $next_y = $year; if ($next_m > 12) { $next_m = 1;  $next_y++; }

require_once __DIR__ . '/../../includes/header.php';
?>
<style>
/* Nav */
.cal-nav {
  display: flex; align-items: center; gap: 12px; margin-bottom: 24px; flex-wrap: wrap;
}
.cal-nav h2 {
  font-family: 'Plus Jakarta Sans',sans-serif; font-weight: 800;
  font-size: 22px; letter-spacing: -.03em; margin: 0;
}
.cal-nav-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 14px; border-radius: 8px; font-size: 12px; font-weight: 600;
  letter-spacing: .06em; color: var(--text-secondary);
  background: var(--bg-secondary); border: 1px solid var(--border);
  text-decoration: none; transition: border-color .15s, color .15s;
}
.cal-nav-btn:hover { border-color: var(--border-light); color: var(--text-primary); }
.cal-nav-today { margin-left: auto; }

/* Grid */
.cal-grid {
  display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px;
}
.cal-header-cell {
  text-align: center; font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .1em;
  color: var(--text-muted); padding: 8px 0;
}
.cal-day {
  background: var(--bg-secondary); border: 1px solid var(--border);
  border-radius: 8px; min-height: 100px; padding: 7px 8px; position: relative;
  transition: border-color .15s;
}
.cal-day:hover { border-color: var(--border-light); }
.cal-day.today { border-color: var(--text-primary); border-width: 2px; }
.cal-day.other-month { opacity: .25; pointer-events: none; }
.cal-day.weekend .day-num { color: var(--text-muted); }
.day-num {
  font-size: 12px; font-weight: 700; margin-bottom: 5px; color: var(--text-secondary);
}
.cal-day.today .day-num { color: var(--text-primary); }

.emp-tag {
  font-size: 10px; font-weight: 600; color: #fff;
  border-radius: 4px; padding: 2px 6px; margin-bottom: 2px;
  display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  cursor: pointer;
}

/* Tooltip */
.emp-tag-wrap { position: relative; }
.emp-tooltip {
  display: none; position: absolute; left: 0; top: calc(100% + 4px); z-index: 100;
  background: var(--bg-primary); border: 1px solid var(--border-light);
  border-radius: 8px; padding: 10px 12px; min-width: 200px; font-size: 11px;
  box-shadow: 0 8px 24px rgba(0,0,0,.4); pointer-events: none;
}
[data-theme="light"] .emp-tooltip { box-shadow: 0 6px 16px rgba(0,0,0,.1); }
.emp-tag-wrap:hover .emp-tooltip { display: block; }
.emp-tooltip-name { font-weight: 700; font-size: 12px; margin-bottom: 4px; }
.emp-tooltip-row { color: var(--text-muted); margin-bottom: 2px; }

/* List view */
.list-card {
  background: var(--bg-secondary); border: 1px solid var(--border);
  border-radius: 12px; overflow: hidden; margin-top: 28px;
}
.list-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px; border-bottom: 1px solid var(--border);
}
.list-title { font-weight: 700; font-size: 14px; letter-spacing: -.02em; }
.list-count { font-size: 11px; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; color: var(--text-muted); }
table.vac-table { width: 100%; border-collapse: collapse; }
table.vac-table th {
  padding: 10px 16px; text-align: left; font-size: 10px; font-weight: 600;
  letter-spacing: .14em; text-transform: uppercase; color: var(--text-muted);
  background: var(--bg-tertiary); border-bottom: 1px solid var(--border);
}
table.vac-table td { padding: 12px 16px; border-bottom: 1px solid var(--border); font-size: 13px; vertical-align: middle; }
table.vac-table tbody tr:last-child td { border-bottom: none; }
table.vac-table tbody tr:hover td { background: var(--bg-tertiary); }

.emp-dot { width: 10px; height: 10px; border-radius: 3px; display: inline-block; margin-right: 8px; }
.dept-badge {
  display: inline-block; padding: 3px 8px; border-radius: 5px; font-size: 11px; font-weight: 500;
  background: var(--bg-tertiary); border: 1px solid var(--border); color: var(--text-secondary);
}
.status-badge {
  padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase;
}
.status-aprovado { background: rgba(16,185,129,.1); color: #10b981; border: 1px solid rgba(16,185,129,.2); }

/* Legend */
.legend { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }
.legend-item { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-secondary); }
.legend-dot { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
</style>

<!-- Breadcrumb / Nav -->
<div class="cal-nav">
  <a href="list.php" class="cal-nav-btn">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    Voltar às Férias
  </a>
  <a href="?month=<?= $prev_m ?>&year=<?= $prev_y ?>" class="cal-nav-btn">← Anterior</a>
  <h2><?= $month_names[$month] ?> <?= $year ?></h2>
  <a href="?month=<?= $next_m ?>&year=<?= $next_y ?>" class="cal-nav-btn">Próximo →</a>
  <a href="?month=<?= date('n') ?>&year=<?= date('Y') ?>" class="cal-nav-btn cal-nav-today">Hoje</a>
</div>

<!-- Calendário -->
<div class="cal-grid">
  <?php foreach (['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'] as $h): ?>
  <div class="cal-header-cell"><?= $h ?></div>
  <?php endforeach; ?>

  <?php
  for ($i = 1; $i < $first_dow; $i++) {
      echo '<div class="cal-day other-month"></div>';
  }
  $today = date('Y-m-d');
  for ($d = 1; $d <= $days_in_month; $d++) {
      $dow = (int)date('N', mktime(0,0,0,$month,$d,$year));
      $is_weekend = ($dow >= 6);
      $is_today   = ($today === sprintf('%04d-%02d-%02d', $year, $month, $d));
      $classes    = 'cal-day' . ($is_weekend ? ' weekend' : '') . ($is_today ? ' today' : '');
      echo '<div class="' . $classes . '">';
      echo '<div class="day-num">' . $d . '</div>';
      foreach ($calendar[$d] ?? [] as $entry) {
          $col = $emp_colors[$entry['name']] ?? '#888';
          $firstName = explode(' ', $entry['name'])[0];
          $dept = htmlspecialchars($entry['department']);
          $startFmt = date('d/m/Y', strtotime($entry['start_date']));
          $endFmt   = date('d/m/Y', strtotime($entry['end_date']));
          echo '<div class="emp-tag-wrap">';
          echo '<span class="emp-tag" style="background:' . $col . '" title="' . htmlspecialchars($entry['name']) . '">' . htmlspecialchars($firstName) . '</span>';
          echo '<div class="emp-tooltip">';
          echo '<div class="emp-tooltip-name">' . htmlspecialchars($entry['name']) . '</div>';
          echo '<div class="emp-tooltip-row">Departamento: ' . $dept . '</div>';
          echo '<div class="emp-tooltip-row">Período: ' . $startFmt . ' → ' . $endFmt . '</div>';
          echo '<div class="emp-tooltip-row">Duração: ' . $entry['days'] . ' dias</div>';
          echo '</div>';
          echo '</div>';
      }
      echo '</div>';
  }
  $last_dow = (int)date('N', mktime(0,0,0,$month,$days_in_month,$year));
  for ($i = $last_dow; $i < 7; $i++) {
      echo '<div class="cal-day other-month"></div>';
  }
  ?>
</div>

<!-- Legenda -->
<?php if (!empty($emp_colors)): ?>
<div class="legend">
  <span style="font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--text-muted)">Legenda:</span>
  <?php foreach ($emp_colors as $name => $color):
    $emp = $employees_in_month[$name];
  ?>
  <div class="legend-item">
    <div class="legend-dot" style="background:<?= $color ?>"></div>
    <?= htmlspecialchars($name) ?>
    <?php if ($emp['department']): ?>
      <span class="dept-badge" style="font-size:10px;padding:1px 6px;"><?= htmlspecialchars($emp['department']) ?></span>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<p style="color:var(--text-muted);margin-top:16px;font-size:13px">Sem férias aprovadas neste mês.</p>
<?php endif; ?>

<!-- Lista detalhada -->
<?php if (!empty($vacations)): ?>
<div class="list-card">
  <div class="list-header">
    <span class="list-title">Detalhe das Férias</span>
    <span class="list-count"><?= count($vacations) ?> registo<?= count($vacations) !== 1 ? 's' : '' ?></span>
  </div>
  <table class="vac-table">
    <thead>
      <tr>
        <th>Colaborador</th>
        <th>Departamento</th>
        <th>Início</th>
        <th>Fim</th>
        <th>Duração</th>
        <th>Estado</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($vacations as $v):
        $col  = $emp_colors[$v['emp_name']] ?? '#888';
        $days = (int)((strtotime($v['end_date']) - strtotime($v['start_date'])) / 86400) + 1;
      ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;">
            <span class="emp-dot" style="background:<?= $col ?>"></span>
            <strong><?= htmlspecialchars($v['emp_name']) ?></strong>
          </div>
        </td>
        <td><span class="dept-badge"><?= htmlspecialchars($v['department'] ?? '—') ?></span></td>
        <td><?= date('d/m/Y', strtotime($v['start_date'])) ?></td>
        <td><?= date('d/m/Y', strtotime($v['end_date'])) ?></td>
        <td><strong><?= $days ?> dia<?= $days !== 1 ? 's' : '' ?></strong></td>
        <td><span class="status-badge status-aprovado"><?= htmlspecialchars($v['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
