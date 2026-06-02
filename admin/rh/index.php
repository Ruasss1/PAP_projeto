<?php
/**
 * Página Dashboard de RH
 * admin/rh/index.php
 */

require_once __DIR__ . '/../../config/database.php';

// Inicializar variáveis
$total_employees = 0;
$departments = [];
$pending_vacations = 0;
$total_shifts = 0;
$absences_this_month = 0;
$error_message = "";

// Obter estatísticas (com tratamento de erros)
if ($pdo !== null) {
    try {
        // Total de colaboradores
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM employees WHERE status = 'Ativo'");
        if ($stmt) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_employees = $result['total'] ?? 0;
        }
        
        // Departamentos
        $stmt = $pdo->query("SELECT department, COUNT(*) as count FROM employees WHERE status = 'Ativo' GROUP BY department");
        if ($stmt) {
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
        }
        
        // Pedidos de férias pendentes
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM vacation_requests WHERE status = 'Pendente'");
        if ($stmt) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $pending_vacations = $result['total'] ?? 0;
        }
        
        // Turnos
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM shifts");
        if ($stmt) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_shifts = $result['total'] ?? 0;
        }
        
        // Faltas este mês
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM absences WHERE MONTH(absence_date) = MONTH(NOW()) AND YEAR(absence_date) = YEAR(NOW())");
        if ($stmt) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $absences_this_month = $result['total'] ?? 0;
        }
        
    } catch (Exception $e) {
        $error_message = "Erro ao carregar estatísticas: " . $e->getMessage();
        error_log($error_message);
    }
} else {
    $error_message = "Aviso: Base de dados não conectada. Modo demo ativado.";
}
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard RH</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/premium.css">
    <link rel="stylesheet" href="/assets/css/master-ui.css?v=<?= time() ?>">
    <script>
        (function() {
            const theme = localStorage.getItem('pap-theme') || 'dark';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <style>
        .rh-container { max-width: 1200px; margin: 0 auto; padding: 32px; }
        
        .back-link { margin-bottom: 24px; }
        .back-link a { 
            color: var(--accent); 
            text-decoration: none; 
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .back-link a:hover { text-decoration: underline; }
        
        .page-header { margin-bottom: 40px; }
        .page-header h1 { 
            color: var(--text-primary); 
            font-size: 32px; 
            margin-bottom: 10px;
            font-weight: 700;
        }
        .page-header p { color: var(--text-muted); font-size: 15px; }
        
        .alert-box {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-left: 4px solid var(--warning);
            padding: 16px 20px;
            margin-bottom: 24px;
            border-radius: var(--radius-lg);
            color: var(--text-primary);
        }
        
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 20px; 
            margin-bottom: 40px; 
        }
        
        .stat-card { 
            background: var(--bg-secondary); 
            border: 1px solid var(--border);
            padding: 25px; 
            border-radius: var(--radius-lg); 
            transition: var(--transition);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--accent);
        }
        .stat-card.alert { border-left: 4px solid var(--danger); }
        .stat-card.warning { border-left: 4px solid var(--warning); }
        .stat-card.success { border-left: 4px solid var(--success); }
        .stat-card.info { border-left: 4px solid var(--accent); }
        
        .stat-icon { font-size: 32px; margin-bottom: 10px; }
        .stat-label { 
            color: var(--text-muted); 
            font-size: 11px; 
            text-transform: uppercase; 
            margin-bottom: 8px; 
            letter-spacing: 1.5px;
            font-weight: 600;
        }
        .stat-value { font-size: 36px; font-weight: 700; color: var(--text-primary); }
        .stat-subtitle { color: var(--text-muted); font-size: 12px; margin-top: 5px; }
        
        .menu-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); 
            gap: 20px; 
            margin-bottom: 40px; 
        }
        
        .menu-card { 
            background: var(--bg-secondary); 
            border: 1px solid var(--border);
            border-radius: var(--radius-lg); 
            overflow: hidden; 
            transition: var(--transition); 
        }
        .menu-card:hover { 
            transform: translateY(-4px); 
            box-shadow: var(--shadow-lg);
            border-color: var(--accent);
        }
        
        .menu-header { padding: 20px; color: white; }
        .menu-header h3 { font-size: 18px; margin-bottom: 5px; font-weight: 600; }
        .menu-header p { font-size: 13px; opacity: 0.9; }
        
        .menu-body { padding: 20px; background: var(--bg-tertiary); }
        .menu-body p { 
            color: var(--text-secondary); 
            font-size: 14px; 
            margin-bottom: 15px; 
            line-height: 1.6; 
        }
        
        .feature-list {
            color: var(--text-muted);
            font-size: 13px;
            margin-left: 15px;
            line-height: 1.8;
        }
        .feature-list li {
            margin-bottom: 4px;
        }
        
        .info-section {
            background: var(--bg-secondary);
            padding: 30px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            margin-bottom: 40px;
        }
        .info-section h3 {
            margin-bottom: 20px;
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .text-muted {
            color: var(--text-muted);
        }
        
        .menu-actions { display: flex; gap: 10px; margin-top: 20px; }
        .menu-link { 
            display: inline-block; 
            padding: 10px 20px; 
            background: var(--accent); 
            color: white; 
            text-decoration: none; 
            border-radius: var(--radius); 
            font-size: 13px; 
            font-weight: 600;
            transition: var(--transition); 
        }
        .menu-link:hover { 
            background: var(--accent-hover);
            transform: translateY(-1px);
        }
        
        .menu-card.employees .menu-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .menu-card.shifts .menu-header { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .menu-card.vacation .menu-header { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .menu-card.payroll .menu-header { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .menu-card.audit .menu-header { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        
        .quick-actions { 
            background: var(--bg-secondary); 
            border: 1px solid var(--border);
            padding: 30px; 
            border-radius: var(--radius-lg); 
            margin-bottom: 40px; 
        }
        .quick-actions h3 { margin-bottom: 20px; color: var(--text-primary); font-weight: 600; }
        
        .action-buttons { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 15px; 
        }
        .btn { 
            padding: 12px 20px; 
            border: none; 
            border-radius: var(--radius); 
            cursor: pointer; 
            text-decoration: none; 
            font-size: 14px; 
            font-weight: 600; 
            transition: var(--transition); 
            display: inline-block; 
            text-align: center; 
        }
        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { background: var(--accent-hover); transform: translateY(-2px); }
        .btn-secondary { 
            background: var(--bg-tertiary); 
            color: var(--text-primary);
            border: 1px solid var(--border);
        }
        .btn-secondary:hover { background: var(--bg-hover); }
        
        .department-list { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); 
            gap: 10px; 
            margin-top: 15px; 
        }
        .dept-badge { 
            background: var(--bg-tertiary); 
            border: 1px solid var(--border);
            padding: 12px 15px; 
            border-radius: var(--radius); 
            text-align: center; 
        }
        .dept-name { font-weight: 600; color: var(--text-primary); font-size: 14px; }
        .dept-count { color: var(--text-muted); font-size: 12px; margin-top: 4px; }
        
        .system-info {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            padding: 20px;
            border-radius: var(--radius-lg);
            text-align: center;
            color: var(--text-muted);
            font-size: 12px;
        }
        .system-info p {
            margin: 5px 0;
        }
        
        /* Theme Toggle */
        .theme-toggle {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 48px;
            height: 48px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 9999;
            font-size: 20px;
            transition: var(--transition);
        }
        .theme-toggle:hover {
            transform: scale(1.1);
            border-color: var(--accent);
        }
        .theme-toggle .icon-sun, .theme-toggle .icon-moon {
            position: absolute;
            transition: all 0.3s ease;
        }
        .theme-toggle .icon-sun { opacity: 0; transform: rotate(-90deg) scale(0); }
        .theme-toggle .icon-moon { opacity: 1; transform: rotate(0) scale(1); }
        [data-theme="light"] .theme-toggle .icon-sun { opacity: 1; transform: rotate(0) scale(1); }
        [data-theme="light"] .theme-toggle .icon-moon { opacity: 0; transform: rotate(90deg) scale(0); }
    </style>
</head>
<body>
    <div class="rh-container">
        <div class="back-link">
            <a href="/index.php">← Voltar ao Dashboard Principal</a>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert-box">
                <strong>Aviso:</strong> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <h1>Gestão de Recursos Humanos</h1>
            <p>Gerencie colaboradores, turnos, férias e folha de pagamento</p>
        </div>

        <!-- Estatísticas Rápidas -->
        <div class="stats-grid">
            <div class="stat-card success">
                <div class="stat-icon"></div>
                <div class="stat-label">Colaboradores Ativos</div>
                <div class="stat-value"><?php echo $total_employees; ?></div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon"></div>
                <div class="stat-label">Turnos Disponíveis</div>
                <div class="stat-value"><?php echo $total_shifts; ?></div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon"></div>
                <div class="stat-label">Férias Pendentes</div>
                <div class="stat-value"><?php echo $pending_vacations; ?></div>
            </div>
            
            <div class="stat-card alert">
                <div class="stat-icon"></div>
                <div class="stat-label">Faltas Este Mês</div>
                <div class="stat-value"><?php echo $absences_this_month; ?></div>
            </div>
        </div>

        <!-- Ações Rápidas -->
        <div class="quick-actions">
            <h3>Ações Rápidas</h3>
            <div class="action-buttons">
                <a href="/admin/employees/create.php" class="btn btn-primary">+ Novo Colaborador</a>
                <a href="/admin/shifts/list.php" class="btn btn-primary">+ Novo Turno</a>
                <a href="/admin/payroll/list.php" class="btn btn-primary">Gerar Folha</a>
                <a href="/admin/vacation/list.php" class="btn btn-secondary">Férias Pendentes</a>
            </div>
        </div>

        <!-- Departamentos -->
        <div class="info-section">
            <h3>Distribuição por Departamento</h3>
            <div class="department-list">
                <?php if (!empty($departments)): ?>
                    <?php foreach ($departments as $dept): ?>
                        <div class="dept-badge">
                            <div class="dept-name"><?php echo htmlspecialchars($dept['department']); ?></div>
                            <div class="dept-count"><?php echo $dept['count']; ?> colaborador(es)</div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">Nenhum colaborador ainda</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Módulos Principais -->
        <div class="menu-grid">
            <!-- Colaboradores -->
            <div class="menu-card employees">
                <div class="menu-header">
                    <h3>Gestão de Colaboradores</h3>
                    <p>Criar, editar e gerenciar dados dos colaboradores</p>
                </div>
                <div class="menu-body">
                    <p>Acesse o painel completo de colaboradores com:</p>
                    <ul class="feature-list">
                        <li>Listagem e filtros avançados</li>
                        <li>Criar novo colaborador</li>
                        <li>Editar dados pessoais e profissionais</li>
                        <li>Upload de documentos</li>
                        <li>Histórico de alterações</li>
                    </ul>
                    <div class="menu-actions">
                        <a href="/admin/employees/list.php" class="menu-link">Abrir</a>
                        <a href="/admin/employees/create.php" class="menu-link">Novo</a>
                    </div>
                </div>
            </div>

            <!-- Turnos -->
            <div class="menu-card shifts">
                <div class="menu-header">
                    <h3>Gestão de Turnos</h3>
                    <p>Definir e atribuir turnos de trabalho</p>
                </div>
                <div class="menu-body">
                    <p>Crie turnos para o seu supermercado:</p>
                    <ul class="feature-list">
                        <li>Manhã, Tarde, Noite (customizáveis)</li>
                        <li>Duração de pausa configurável</li>
                        <li>Atribuir colaboradores a turnos</li>
                        <li>Deteção de conflitos</li>
                        <li>Visualização em calendário</li>
                    </ul>
                    <div class="menu-actions">
                        <a href="/admin/shifts/list.php" class="menu-link">Gerenciar</a>
                    </div>
                </div>
            </div>

            <!-- Férias -->
            <div class="menu-card vacation">
                <div class="menu-header">
                    <h3>Férias e Licenças</h3>
                    <p>Aprovar e controlar pedidos de férias</p>
                </div>
                <div class="menu-body">
                    <p>Gerencie as férias dos colaboradores:</p>
                    <ul class="feature-list">
                        <li>Saldo automático: 22 dias/ano</li>
                        <li>Pedidos de férias online</li>
                        <li>Aprovação/Rejeição</li>
                        <li>Atualização automática de saldo</li>
                        <li>Controle de faltas</li>
                    </ul>
                    <div class="menu-actions">
                        <a href="/admin/vacation/list.php" class="menu-link">Visualizar</a>
                    </div>
                </div>
            </div>

            <!-- Folha de Pagamento -->
            <div class="menu-card payroll">
                <div class="menu-header">
                    <h3>Folha de Pagamento</h3>
                    <p>Gerar e gerenciar folhas de pagamento</p>
                </div>
                <div class="menu-body">
                    <p>Sistema automático de folha de pagamento:</p>
                    <ul class="feature-list">
                        <li>Salário base automático</li>
                        <li>Comissões de vendas integradas</li>
                        <li>Descontos (SS e IRS)</li>
                        <li>Gerar folha mensal em massa</li>
                        <li>Relatórios por colaborador</li>
                    </ul>
                    <div class="menu-actions">
                        <a href="/admin/payroll/list.php" class="menu-link">Abrir</a>
                    </div>
                </div>
            </div>

            <!-- Auditoria -->
            <div class="menu-card audit">
                <div class="menu-header">
                    <h3>Auditoria RH</h3>
                    <p>Registros de todas as ações do sistema</p>
                </div>
                <div class="menu-body">
                    <p>Controle completo com auditoria automática:</p>
                    <ul class="feature-list">
                        <li>Histórico de todas as alterações</li>
                        <li>Quem fez alterações e quando</li>
                        <li>Valores antigos vs novos</li>
                        <li>Rastreamento por IP</li>
                        <li>Conformidade e segurança</li>
                    </ul>
                    <div class="menu-actions">
                        <button class="menu-link" onclick="alert('Auditoria em desenvolvimento')">Visualizar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informações do Sistema -->
        <div class="system-info">
            <p>Sistema de Gestão RH - PHASE 4 | Desenvolvido em 15 de Janeiro de 2026</p>
            <p>Base de dados: 11 tabelas | Funções: 22 | Interfaces: 8</p>
        </div>
    </div>
    
    <!-- Theme Toggle Button -->
    <button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema">
        <span class="icon-moon">🌙</span>
        <span class="icon-sun">☀️</span>
    </button>
    
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
