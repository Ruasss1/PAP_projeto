<?php
/**
 * Equipa - Recursos Humanos
 */

require_once __DIR__ . '/../../config/database.php';

$page_title = 'Equipa';

require_once __DIR__ . '/../../includes/header.php';

$total_employees = 0;
$pending_vacations = 0;
$total_shifts = 0;
$absences_this_month = 0;
$error_message = "";

if ($pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM employees");
        if ($stmt) { $r = $stmt->fetch(PDO::FETCH_ASSOC); $total_employees = $r['total'] ?? 0; }

        $stmt = $pdo->query("SELECT COUNT(*) as total FROM shifts");
        if ($stmt) { $r = $stmt->fetch(PDO::FETCH_ASSOC); $total_shifts = $r['total'] ?? 0; }

        $stmt = $pdo->query("SELECT COUNT(*) as total FROM vacation_requests");
        if ($stmt) { $r = $stmt->fetch(PDO::FETCH_ASSOC); $pending_vacations = $r['total'] ?? 0; }

        $stmt = $pdo->query("SELECT COUNT(*) as total FROM absences WHERE MONTH(absence_date) = MONTH(NOW()) AND YEAR(absence_date) = YEAR(NOW())");
        if ($stmt) { $r = $stmt->fetch(PDO::FETCH_ASSOC); $absences_this_month = $r['total'] ?? 0; }

    } catch (Exception $e) {
        $error_message = "Erro ao carregar estatísticas: " . $e->getMessage();
        error_log($error_message);
    }
} else {
    $error_message = "Aviso: Base de dados não conectada. Modo demo ativado.";
}
?>

<style>
    .rh-alert {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-left: 4px solid var(--warning);
        padding: 14px 20px;
        margin-bottom: 28px;
        border-radius: var(--radius-lg);
        color: var(--text-primary);
        font-size: 13.5px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }
    .stat-box {
        background: var(--bg-secondary);
        padding: 28px 30px;
        border-radius: var(--radius-lg);
        border: 1px solid var(--border);
        transition: var(--transition);
    }
    .stat-box:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-3px);
        border-color: var(--border-light);
    }
    .stat-box.success { border-left: 4px solid var(--success); }
    .stat-box.info    { border-left: 4px solid var(--accent); }
    .stat-box.warning { border-left: 4px solid var(--warning); }
    .stat-box.danger  { border-left: 4px solid var(--danger); }
    .stat-label {
        font-size: 11px;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-weight: 600;
        margin-bottom: 10px;
    }
    .stat-value {
        font-size: 48px;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1;
        font-variant-numeric: tabular-nums;
    }

    .modules-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        align-items: stretch;
    }
    .module-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
        transition: var(--transition);
        display: flex;
        flex-direction: column;
    }
    .module-card:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-3px);
        border-color: var(--border-light);
    }
    .module-header {
        padding: 22px 24px;
        background: var(--bg-tertiary);
        border-bottom: 1px solid var(--border);
    }
    .module-header h3 {
        font-size: 17px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
    }
    .module-header p {
        font-size: 13px;
        color: var(--text-muted);
    }
    .module-body {
        padding: 20px 24px;
        flex: 1;
    }
    .module-body ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .module-body ul li {
        font-size: 13.5px;
        color: var(--text-secondary);
        padding: 7px 0;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .module-body ul li:last-child { border-bottom: none; }
    .module-body ul li::before {
        content: '';
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: var(--text-muted);
        flex-shrink: 0;
        opacity: 0.6;
    }
    .module-footer {
        padding: 16px 24px;
        border-top: 1px solid var(--border);
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    @media (max-width: 900px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 600px) { .stats-grid { grid-template-columns: 1fr; } .modules-grid { grid-template-columns: 1fr; } }
</style>

<div>
    <?php if (!empty($error_message)): ?>
    <div class="rh-alert"><strong>Aviso:</strong> <?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-box success">
            <div class="stat-label">Colaboradores</div>
            <div class="stat-value"><?= $total_employees ?></div>
        </div>
        <div class="stat-box info">
            <div class="stat-label">Turnos</div>
            <div class="stat-value"><?= $total_shifts ?></div>
        </div>
        <div class="stat-box warning">
            <div class="stat-label">Férias Pendentes</div>
            <div class="stat-value"><?= $pending_vacations ?></div>
        </div>
        <div class="stat-box danger">
            <div class="stat-label">Faltas Este Mês</div>
            <div class="stat-value"><?= $absences_this_month ?></div>
        </div>
    </div>

    <div class="modules-grid">
        <div class="module-card">
            <div class="module-header">
                <h3>Colaboradores</h3>
                <p>Gestão completa da equipa</p>
            </div>
            <div class="module-body">
                <ul>
                    <li>Criar e editar colaboradores</li>
                    <li>Upload de documentos</li>
                    <li>Histórico de alterações</li>
                    <li>Filtros avançados</li>
                </ul>
            </div>
            <div class="module-footer">
                <a href="/admin/employees/list.php" class="btn btn-primary">Ver lista</a>
                <a href="/admin/employees/create.php" class="btn btn-primary">+ Novo</a>
            </div>
        </div>

        <div class="module-card">
            <div class="module-header">
                <h3>Turnos</h3>
                <p>Gestão de horários de trabalho</p>
            </div>
            <div class="module-body">
                <ul>
                    <li>Criar turnos personalizados</li>
                    <li>Atribuir colaboradores</li>
                    <li>Deteção de conflitos</li>
                    <li>Visualização de horários</li>
                </ul>
            </div>
            <div class="module-footer">
                <a href="/admin/shifts/list.php" class="btn btn-primary">Gerir turnos</a>
            </div>
        </div>

        <div class="module-card">
            <div class="module-header">
                <h3>Férias</h3>
                <p>Controlo de férias e licenças</p>
            </div>
            <div class="module-body">
                <ul>
                    <li>Saldo de 22 dias/ano</li>
                    <li>Pedidos online</li>
                    <li>Aprovação / Rejeição</li>
                    <li>Registo de faltas</li>
                </ul>
            </div>
            <div class="module-footer">
                <a href="/admin/vacation/list.php" class="btn btn-primary">Ver pedidos</a>
                <a href="/admin/vacation/calendario.php" class="btn btn-primary">Calendário</a>
            </div>
        </div>

        <div class="module-card">
            <div class="module-header">
                <h3>Folha de Pagamento</h3>
                <p>Salários e comissões</p>
            </div>
            <div class="module-body">
                <ul>
                    <li>Geração automática</li>
                    <li>Comissões integradas</li>
                    <li>Descontos (SS e IRS)</li>
                    <li>Relatórios mensais</li>
                </ul>
            </div>
            <div class="module-footer">
                <a href="/admin/payroll/list.php" class="btn btn-primary">Ver folhas</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
