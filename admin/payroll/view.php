<?php
/**
 * Detalhe da Folha de Pagamento
 * admin/payroll/view.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../modules/rh.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: /admin/payroll/list.php');
    exit;
}

// Fetch record with employee info
try {
    $stmt = $pdo->prepare("
        SELECT p.*, e.name, e.email, e.phone, e.department, e.position, e.nif, e.hire_date
        FROM payroll p
        INNER JOIN employees e ON p.employee_id = e.id
        WHERE p.id = :id
    ");
    $stmt->execute([':id' => $id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $record = null;
}

if (!$record) {
    header('Location: /admin/payroll/list.php');
    exit;
}

// Parse month label
$months_pt = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
    '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
    '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
];
[$year_str, $month_str] = explode('-', $record['month']);
$month_label = ($months_pt[$month_str] ?? $month_str) . ' ' . $year_str;

$gross = ($record['base_salary'] ?? 0) + ($record['overtime_amount'] ?? 0) + ($record['bonus'] ?? 0);
$is_paid = strtolower($record['status'] ?? '') === 'pago';

// Handle mark as paid
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_paid') {
    try {
        $pdo->prepare("UPDATE payroll SET status = 'Pago', paid_at = NOW() WHERE id = :id")
            ->execute([':id' => $id]);
        header("Location: /admin/payroll/view.php?id={$id}&success=1");
        exit;
    } catch (Exception $e) {
        $error = 'Erro ao marcar como pago: ' . $e->getMessage();
    }
}
if (isset($_GET['success'])) {
    $message = 'Folha marcada como paga com sucesso.';
    // Refresh record
    $stmt->execute([':id' => $id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    $is_paid = strtolower($record['status'] ?? '') === 'pago';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Folha de Pagamento — <?php echo htmlspecialchars($record['name'] ?? ''); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body {
            background: #0a0a0a;
            color: #ffffff;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            line-height: 1.6;
        }
        .container { max-width: 860px; margin: 0 auto; padding: 32px; }
        .back-link { margin-bottom: 24px; }
        .back-link a {
            color: #a1a1aa; text-decoration: none; font-weight: 600;
            display: inline-flex; align-items: center; gap: 6px;
            transition: all 0.2s ease;
        }
        .back-link a:hover { transform: translateX(-4px); color: #1d4ed8; }

        /* Payslip card */
        .payslip {
            background: #141414;
            border: 1px solid #222;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.6);
        }
        .payslip-header {
            background: linear-gradient(135deg, #27272a 0%, #a1a1aa 100%);
            padding: 32px 36px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
        }
        .payslip-header h1 { font-size: 22px; font-weight: 700; margin: 0 0 4px; color: #fff; }
        .payslip-header .subtitle { color: rgba(255,255,255,0.75); font-size: 14px; }
        .payslip-header .period {
            text-align: right;
        }
        .payslip-header .period .label { font-size: 12px; color: rgba(255,255,255,0.6); text-transform: uppercase; letter-spacing: 1px; }
        .payslip-header .period .value { font-size: 20px; font-weight: 700; color: #fff; }

        .payslip-body { padding: 32px 36px; }

        /* Two-column info grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 32px;
            margin-bottom: 32px;
            padding-bottom: 28px;
            border-bottom: 1px solid #222;
        }
        .info-item .label {
            font-size: 11px; color: #666; text-transform: uppercase;
            letter-spacing: 1px; font-weight: 600; margin-bottom: 4px;
        }
        .info-item .value { font-size: 15px; color: #e0e0e0; font-weight: 500; }

        /* Breakdown table */
        .section-title {
            font-size: 12px; color: #666; text-transform: uppercase;
            letter-spacing: 1px; font-weight: 600; margin: 0 0 16px;
        }
        .breakdown {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 28px;
        }
        .breakdown td {
            padding: 12px 0;
            border-bottom: 1px solid #1a1a1a;
            font-size: 14px;
            color: #d0d0d0;
        }
        .breakdown td:last-child { text-align: right; font-family: monospace; font-size: 15px; }
        .breakdown tr:last-child td { border-bottom: none; }
        .breakdown .category td {
            font-size: 11px; color: #555; text-transform: uppercase;
            letter-spacing: 1px; padding-top: 20px; padding-bottom: 6px;
            border-bottom: none;
        }
        .breakdown .positive td:last-child { color: #10b981; }
        .breakdown .negative td:last-child { color: #ef4444; }
        .breakdown .subtotal td {
            font-weight: 700; color: #fff; font-size: 15px;
            border-top: 1px solid #333; border-bottom: 1px solid #333;
        }

        /* Net salary box */
        .net-box {
            background: linear-gradient(135deg, #064e3b, #065f46);
            border: 1px solid #10b981;
            border-radius: 12px;
            padding: 24px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }
        .net-box .label { font-size: 14px; color: rgba(255,255,255,0.8); font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .net-box .amount { font-size: 36px; font-weight: 800; color: #10b981; font-family: monospace; }

        /* Status & actions */
        .footer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            padding-top: 24px;
            border-top: 1px solid #222;
        }
        .status-badge {
            padding: 8px 18px; border-radius: 20px;
            font-weight: 700; font-size: 13px;
            text-transform: uppercase; letter-spacing: 0.5px;
            display: inline-block;
        }
        .status-pago { background: rgba(16,185,129,0.2); color: #10b981; border: 1px solid #10b981; }
        .status-pendente { background: rgba(245,158,11,0.2); color: #f59e0b; border: 1px solid #f59e0b; }
        .paid-at { font-size: 12px; color: #555; margin-top: 4px; }

        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn {
            padding: 10px 22px; border: none; border-radius: 8px;
            cursor: pointer; font-weight: 600; font-size: 14px;
            transition: all 0.2s ease; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-primary { background: #a1a1aa; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; transform: translateY(-1px); }
        .btn-success { background: #10b981; color: #fff; }
        .btn-success:hover { background: #059669; transform: translateY(-1px); }
        .btn-outline {
            background: transparent; color: #a0a0a0;
            border: 1px solid #333;
        }
        .btn-outline:hover { border-color: #555; color: #fff; }

        .message { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .message.success { background: rgba(16,185,129,0.15); color: #10b981; border: 1px solid #10b981; }
        .message.error { background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid #ef4444; }

        @media print {
            body { background: #fff; color: #000; }
            .payslip { border: 1px solid #ccc; box-shadow: none; }
            .payslip-header { background: #27272a !important; -webkit-print-color-adjust: exact; }
            .back-link, .actions, form { display: none !important; }
        }

        @media (max-width: 600px) {
            .info-grid { grid-template-columns: 1fr; }
            .payslip-header { flex-direction: column; }
            .payslip-header .period { text-align: left; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="back-link">
        <a href="/admin/payroll/list.php?month=<?php echo $month_str; ?>&year=<?php echo $year_str; ?>">
            ← Voltar à Folha de Pagamento
        </a>
    </div>

    <?php if ($message): ?>
        <div class="message success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="payslip">
        <!-- Header -->
        <div class="payslip-header">
            <div>
                <h1><?php echo htmlspecialchars($record['name'] ?? ''); ?></h1>
                <div class="subtitle">
                    <?php echo htmlspecialchars($record['position'] ?? '—'); ?>
                    <?php if ($record['department']): ?>
                        &nbsp;·&nbsp; <?php echo htmlspecialchars($record['department']); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="period">
                <div class="label">Período</div>
                <div class="value"><?php echo htmlspecialchars($month_label); ?></div>
            </div>
        </div>

        <!-- Body -->
        <div class="payslip-body">

            <!-- Employee Info -->
            <div class="info-grid">
                <div class="info-item">
                    <div class="label">Email</div>
                    <div class="value"><?php echo htmlspecialchars($record['email'] ?? '—'); ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Telefone</div>
                    <div class="value"><?php echo htmlspecialchars($record['phone'] ?? '—'); ?></div>
                </div>
                <div class="info-item">
                    <div class="label">NIF</div>
                    <div class="value"><?php echo htmlspecialchars($record['nif'] ?? '—'); ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Data de Admissão</div>
                    <div class="value">
                        <?php echo $record['hire_date'] ? date('d/m/Y', strtotime($record['hire_date'])) : '—'; ?>
                    </div>
                </div>
            </div>

            <!-- Salary Breakdown -->
            <p class="section-title">Detalhes Salariais</p>
            <table class="breakdown">
                <tr class="category"><td colspan="2">Vencimentos</td></tr>
                <tr class="positive">
                    <td>Salário Base</td>
                    <td><?php echo number_format($record['base_salary'] ?? 0, 2, ',', '.'); ?>€</td>
                </tr>
                <?php if (($record['overtime_hours'] ?? 0) > 0): ?>
                <tr class="positive">
                    <td>Horas Extra (<?php echo number_format($record['overtime_hours'], 1); ?>h)</td>
                    <td><?php echo number_format($record['overtime_amount'] ?? 0, 2, ',', '.'); ?>€</td>
                </tr>
                <?php endif; ?>
                <?php if (($record['bonus'] ?? 0) > 0): ?>
                <tr class="positive">
                    <td>Bónus / Prémios</td>
                    <td><?php echo number_format($record['bonus'], 2, ',', '.'); ?>€</td>
                </tr>
                <?php endif; ?>
                <tr class="subtotal">
                    <td>Total Vencimentos</td>
                    <td><?php echo number_format($gross, 2, ',', '.'); ?>€</td>
                </tr>

                <tr class="category"><td colspan="2">Descontos</td></tr>
                <?php
                    $ss = round($gross * 0.11, 2);
                    $irs = round($gross * 0.15, 2);
                    $total_deductions = $record['deductions'] ?? 0;
                ?>
                <tr class="negative">
                    <td>Segurança Social (11%)</td>
                    <td><?php echo number_format($ss, 2, ',', '.'); ?>€</td>
                </tr>
                <tr class="negative">
                    <td>IRS (retenção na fonte, ~15%)</td>
                    <td><?php echo number_format($irs, 2, ',', '.'); ?>€</td>
                </tr>
                <tr class="subtotal">
                    <td>Total Descontos</td>
                    <td><?php echo number_format($total_deductions, 2, ',', '.'); ?>€</td>
                </tr>
            </table>

            <!-- Net salary -->
            <div class="net-box">
                <div class="label">Salário Líquido a Receber</div>
                <div class="amount"><?php echo number_format($record['net_salary'] ?? 0, 2, ',', '.'); ?>€</div>
            </div>

            <!-- Footer: status + actions -->
            <div class="footer-row">
                <div>
                    <span class="status-badge <?php echo $is_paid ? 'status-pago' : 'status-pendente'; ?>">
                        <?php echo htmlspecialchars($record['status'] ?? 'Pendente'); ?>
                    </span>
                    <?php if ($is_paid && $record['paid_at']): ?>
                        <div class="paid-at">Pago em <?php echo date('d/m/Y H:i', strtotime($record['paid_at'])); ?></div>
                    <?php endif; ?>
                </div>
                <div class="actions">
                    <?php if (!$is_paid): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="mark_paid">
                            <button type="submit" class="btn btn-success"
                                onclick="return confirm('Confirmar pagamento desta folha?');">
                                ✓ Marcar como Pago
                            </button>
                        </form>
                    <?php endif; ?>
                    <button class="btn btn-outline" onclick="window.print()">🖨 Imprimir</button>
                    <a href="/admin/payroll/list.php?month=<?php echo $month_str; ?>&year=<?php echo $year_str; ?>"
                       class="btn btn-primary">← Voltar</a>
                </div>
            </div>

        </div><!-- /payslip-body -->
    </div><!-- /payslip -->

</div>
</body>
</html>
