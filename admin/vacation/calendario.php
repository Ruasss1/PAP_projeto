<?php
/**
 * MAPA DE FÉRIAS - CALENDÁRIO VISUAL
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';
$pdo = db_connect();
$page_title = 'Mapa de Férias';

// Navegação de mês
$year  = intval($_GET['year']  ?? date('Y'));
$month = intval($_GET['month'] ?? date('n'));
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$first_dow     = (int)date('N', mktime(0,0,0,$month,1,$year)); // 1=Mon 7=Sun

// Carregar férias aprovadas do mês
$stmt = $pdo->prepare('SELECT vr.*, e.name as emp_name FROM vacation_requests vr JOIN employees e ON e.id = vr.employee_id WHERE vr.status IN ("Aprovado", "approved") AND vr.start_date <= ? AND vr.end_date >= ? ORDER BY e.name ASC');
$stmt->execute(["$year-$month-$days_in_month", "$year-$month-01"]);
$vacations = $stmt->fetchAll();

// Índice: dia => [emp_name1, ...]
$calendar = [];
foreach ($vacations as $v) {
    $start = max(strtotime($v['start_date']), mktime(0,0,0,$month,1,$year));
    $end   = min(strtotime($v['end_date']),   mktime(0,0,0,$month,$days_in_month,$year));
    for ($ts = $start; $ts <= $end; $ts += 86400) {
        $d = (int)date('j', $ts);
        $calendar[$d][] = $v['emp_name'];
    }
}

// Paleta de cores por funcionário
$employees_in_month = array_unique(array_merge(...array_values($calendar ?: [[]])));
$palette = ['#6366f1','#f59e0b','#10b981','#ef4444','#8b5cf6','#06b6d4','#ec4899','#84cc16','#f97316','#14b8a6'];
$emp_colors = [];
foreach (array_values($employees_in_month) as $i => $name) {
    $emp_colors[$name] = $palette[$i % count($palette)];
}

$month_names = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$prev_m = $month - 1; $prev_y = $year; if ($prev_m < 1) { $prev_m = 12; $prev_y--; }
$next_m = $month + 1; $next_y = $year; if ($next_m > 12) { $next_m = 1;  $next_y++; }

require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.cal-nav { display:flex; align-items:center; gap:16px; margin-bottom:20px; }
.cal-nav h2 { font-size:20px; font-weight:700; }
.cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:4px; }
.cal-header-cell { text-align:center; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted); padding:8px 0; }
.cal-day { background:var(--bg-secondary); border:1px solid var(--border); border-radius:8px; min-height:80px; padding:6px; position:relative; }
.cal-day.today { border-color:var(--primary,#6366f1); border-width:2px; }
.cal-day.other-month { opacity:.3; }
.cal-day .day-num { font-size:13px; font-weight:600; margin-bottom:4px; }
.cal-day.weekend .day-num { color:var(--text-muted); }
.emp-tag { font-size:10px; font-weight:600; color:#fff; border-radius:4px; padding:2px 6px; margin-bottom:2px; display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.legend { display:flex; flex-wrap:wrap; gap:10px; margin-top:20px; }
.legend-item { display:flex; align-items:center; gap:6px; font-size:13px; }
.legend-dot { width:12px; height:12px; border-radius:3px; flex-shrink:0; }
</style>

<!-- Navegação -->
<div class="cal-nav">
    <a href="?month=<?= $prev_m ?>&year=<?= $prev_y ?>" class="btn btn-secondary">← Anterior</a>
    <h2><?= $month_names[$month] ?> <?= $year ?></h2>
    <a href="?month=<?= $next_m ?>&year=<?= $next_y ?>" class="btn btn-secondary">Próximo →</a>
    <a href="?month=<?= date('n') ?>&year=<?= date('Y') ?>" class="btn btn-secondary" style="margin-left:auto">Hoje</a>
    <a href="list.php" class="btn btn-secondary">📋 Lista</a>
</div>

<!-- Calendário -->
<div class="cal-grid">
    <?php foreach (['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'] as $h): ?>
    <div class="cal-header-cell"><?= $h ?></div>
    <?php endforeach; ?>

    <?php
    // Espaços antes do primeiro dia
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
        foreach ($calendar[$d] ?? [] as $name) {
            $col = $emp_colors[$name] ?? '#888';
            echo '<span class="emp-tag" style="background:' . $col . '" title="' . htmlspecialchars($name) . '">' . htmlspecialchars(explode(' ', $name)[0]) . '</span>';
        }
        echo '</div>';
    }
    // Preencher últimas células
    $last_dow = (int)date('N', mktime(0,0,0,$month,$days_in_month,$year));
    for ($i = $last_dow; $i < 7; $i++) {
        echo '<div class="cal-day other-month"></div>';
    }
    ?>
</div>

<!-- Legenda -->
<?php if (!empty($emp_colors)): ?>
<div class="legend">
    <strong style="font-size:13px">Legenda:</strong>
    <?php foreach ($emp_colors as $name => $color): ?>
    <div class="legend-item"><div class="legend-dot" style="background:<?= $color ?>"></div><?= htmlspecialchars($name) ?></div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<p style="color:var(--text-muted);margin-top:16px;font-size:13px">Sem férias aprovadas neste mês.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
