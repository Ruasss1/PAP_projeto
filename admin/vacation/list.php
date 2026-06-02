<?php
/**
 * Página de Gestão de Férias
 * admin/vacation/list.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../modules/rh.php';
require_once __DIR__ . '/../../includes/functions.php';

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$message = '';
$error = '';

// Obter todas as solicitações de férias
try {
    $query = "SELECT vr.*, e.name as employee_name, e.email, e.department 
              FROM vacation_requests vr
              INNER JOIN employees e ON vr.employee_id = e.id
              ORDER BY vr.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $vacation_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $vacation_requests = [];
    $error = "Erro ao obter solicitações: " . $e->getMessage();
}

// Processar aprovação/rejeição
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $request_id = $_POST['vacation_id'] ?? null;
    
    if ($request_id && isset($_POST['action'])) {
        $action = $_POST['action'];
        
        try {
            if ($action === 'approve') {
                $query = "UPDATE vacation_requests 
                         SET status = 'Aprovado', approved_by = :user_id, approved_at = NOW()
                         WHERE id = :id";
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    ':id' => $request_id,
                    ':user_id' => $_SESSION['user_id']
                ]);
                
                // Atualizar saldo
                $vacation = null;
                foreach ($vacation_requests as $vr) {
                    if ($vr['id'] == $request_id) {
                        $vacation = $vr;
                        break;
                    }
                }
                
                if ($vacation) {
                    $days_to_use = (int)($vacation['number_of_days'] ?? $vacation['days'] ?? 0);

                    // Garantir tabela e linha de saldo para evitar erro 1146 (tabela inexistente)
                    if (function_exists('ensure_vacation_balance_table')) {
                        ensure_vacation_balance_table();
                    }
                    if (function_exists('create_vacation_balance')) {
                        create_vacation_balance($vacation['employee_id'], date('Y'));
                    }

                    try {
                        $update_query = "UPDATE vacation_balance 
                                       SET used_days = used_days + :days
                                       WHERE employee_id = :emp_id AND year = :year";
                        $stmt = $pdo->prepare($update_query);
                        $stmt->execute([
                            ':days' => $days_to_use,
                            ':emp_id' => $vacation['employee_id'],
                            ':year' => date('Y')
                        ]);
                    } catch (Exception $e) {
                        error_log('Aviso ao atualizar saldo de férias: ' . $e->getMessage());
                    }
                }
                
                $message = 'Férias aprovadas com sucesso.';
            } elseif ($action === 'reject') {
                $reason = $_POST['rejection_reason'] ?? '';
                $query = "UPDATE vacation_requests 
                         SET status = 'Rejeitado', approved_by = :user_id, 
                             approved_at = NOW(), rejection_reason = :reason
                         WHERE id = :id";
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    ':id' => $request_id,
                    ':user_id' => $_SESSION['user_id'],
                    ':reason' => $reason
                ]);
                $message = 'Férias rejeitadas.';
            }
            
            // Recarregar lista
            $stmt = $pdo->prepare($query_reload = "SELECT vr.*, e.name as employee_name, e.email, e.department 
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
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Férias</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body { 
            background: #0a0a0a; 
            color: #ffffff; 
        }
        
        .container { 
            max-width: 1400px; 
            margin: 0 auto; 
            padding: 32px; 
        }
        
        .back-link { 
            margin-bottom: 24px; 
        }
        
        .back-link a { 
            color: #a1a1aa; 
            text-decoration: none; 
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        
        .back-link a:hover {
            color: #1d4ed8;
            transform: translateX(-4px);
        }
        
        .header { 
            margin-bottom: 40px; 
        }
        
        .header h1 {
            color: #ffffff;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin: 0;
        }
        
        .message { 
            padding: 16px 20px; 
            margin-bottom: 24px; 
            border-radius: 8px; 
            font-weight: 500;
        }
        
        .message.success { 
            background: rgba(16, 185, 129, 0.2); 
            color: #10b981; 
            border: 1px solid #10b981;
        }
        
        .message.error { 
            background: rgba(239, 68, 68, 0.2); 
            color: #ef4444; 
            border: 1px solid #ef4444;
        }
        
        .filters { 
            background: #141414; 
            padding: 24px; 
            border-radius: 12px; 
            margin-bottom: 24px; 
            border: 1px solid #222222;
            box-shadow: 0 1px 3px rgba(0,0,0,0.5);
        }
        
        .filter-row { 
            display: flex; 
            gap: 16px; 
            margin-bottom: 0; 
            align-items: center;
        }
        
        .filter-row select { 
            padding: 12px 16px; 
            border: 1px solid #222222; 
            border-radius: 8px;
            background: #1a1a1a;
            color: #ffffff;
            font-size: 14px;
            min-width: 200px;
            transition: all 0.2s ease;
        }
        
        .filter-row select:focus {
            outline: none;
            border-color: #a1a1aa;
            background: #111111;
            box-shadow: 0 0 0 3px rgba(161, 161, 170, 0.1);
        }
        
        .table { 
            width: 100%; 
            border-collapse: collapse;
            background: #141414;
            border: 1px solid #222222;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.5);
        }
        
        .table th { 
            background: #111111; 
            color: #a0a0a0; 
            padding: 16px 20px; 
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            border-bottom: 1px solid #222222;
        }
        
        .table td { 
            padding: 16px 20px; 
            border-bottom: 1px solid #222222;
            color: #ffffff;
        }
        
        .table tr:hover { 
            background: #1a1a1a; 
        }
        
        .table tr:last-child td {
            border-bottom: none;
        }
        
        .status { 
            padding: 6px 12px; 
            border-radius: 20px; 
            font-size: 12px; 
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }
        
        .status-pendente { 
            background: rgba(245, 158, 11, 0.2); 
            color: #f59e0b;
            border: 1px solid #f59e0b;
        }
        
        .status-aprovado { 
            background: rgba(16, 185, 129, 0.2); 
            color: #10b981;
            border: 1px solid #10b981;
        }
        
        .status-rejeitado { 
            background: rgba(239, 68, 68, 0.2); 
            color: #ef4444;
            border: 1px solid #ef4444;
        }
        
        .btn { 
            padding: 8px 16px; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .btn-approve { 
            background: #10b981; 
            color: white; 
        }
        
        .btn-approve:hover { 
            background: #059669;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.4);
        }
        
        .btn-reject { 
            background: #ef4444; 
            color: white; 
        }
        
        .btn-reject:hover { 
            background: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.4);
        }
        
        .actions { 
            display: flex; 
            gap: 8px; 
        }
        
        .modal { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            right: 0; 
            bottom: 0; 
            background: rgba(0,0,0,0.8); 
            z-index: 1000; 
            align-items: center; 
            justify-content: center;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
        
        .modal.active { 
            display: flex; 
        }
        
        .modal-content { 
            background: #141414; 
            padding: 32px; 
            border-radius: 12px; 
            max-width: 500px; 
            width: 90%;
            border: 1px solid #222222;
            box-shadow: 0 20px 60px rgba(0,0,0,0.8);
        }
        
        .modal-content h3 {
            color: #ffffff;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 24px;
        }
        
        .modal-content label {
            display: block;
            margin-bottom: 10px;
            color: #a0a0a0;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .modal-content textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #222222;
            border-radius: 8px;
            background: #1a1a1a;
            color: #ffffff;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            transition: all 0.2s ease;
        }
        
        .modal-content textarea:focus {
            outline: none;
            border-color: #a1a1aa;
            background: #111111;
            box-shadow: 0 0 0 3px rgba(161, 161, 170, 0.1);
        }
        
        .modal-close { 
            background: #3a3a3a; 
            color: white; 
            border: none; 
            padding: 10px 20px; 
            border-radius: 8px; 
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .modal-close:hover {
            background: #4a4a4a;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            line-height: 1;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            text-decoration: none;
            vertical-align: middle;
        }
        
        .filter-btn {
            background: #a1a1aa;
            color: white;
        }
        
        .filter-btn:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(161, 161, 170, 0.4);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666666;
            font-size: 15px;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .vacation-type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(161, 161, 170, 0.2);
            color: #a1a1aa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="back-link">
            <a href="/admin/rh/equipa.php">← Voltar ao Dashboard RH</a>
        </div>

        <div class="header">
            <h1>Gestão de Férias e Licenças</h1>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="filters">
            <form method="get">
                <div class="filter-row">
                    <select name="status">
                        <option value="">Todos os Status</option>
                        <option value="Pendente" <?php echo ($_GET['status'] ?? '') === 'Pendente' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="Aprovado" <?php echo ($_GET['status'] ?? '') === 'Aprovado' ? 'selected' : ''; ?>>Aprovado</option>
                        <option value="Rejeitado" <?php echo ($_GET['status'] ?? '') === 'Rejeitado' ? 'selected' : ''; ?>>Rejeitado</option>
                    </select>
                    <button type="submit" class="btn filter-btn">Filtrar</button>
                </div>
            </form>
        </div>

        <!-- Tabela de Solicitações -->
        <?php if (!empty($vacation_requests)): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Colaborador</th>
                        <th>Departamento</th>
                        <th>Tipo</th>
                        <th>Data Início</th>
                        <th>Data Fim</th>
                        <th>Dias</th>
                        <th>Status</th>
                        <th>Solicitado em</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vacation_requests as $vr):
                        $show = !isset($_GET['status']) || $_GET['status'] === '' || $_GET['status'] === $vr['status'];
                        if (!$show) continue;

                        $vacation_type = trim((string)($vr['vacation_type'] ?? $vr['type'] ?? 'Férias'));
                        if ($vacation_type === '') {
                            $vacation_type = 'Férias';
                        }

                        $number_of_days = $vr['number_of_days'] ?? $vr['days'] ?? null;
                        if ($number_of_days === null || $number_of_days === '') {
                            if (!empty($vr['start_date']) && !empty($vr['end_date']) && function_exists('calculate_business_days')) {
                                $number_of_days = calculate_business_days($vr['start_date'], $vr['end_date']);
                            } else {
                                $number_of_days = 0;
                            }
                        }
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($vr['employee_name']); ?></strong></td>
                            <td style="color: #a0a0a0;"><?php echo htmlspecialchars($vr['department']); ?></td>
                            <td>
                                <span class="vacation-type-badge">
                                    <?php echo htmlspecialchars($vacation_type); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($vr['start_date'])); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($vr['end_date'])); ?></td>
                            <td><strong><?php echo (int)$number_of_days; ?></strong> dias</td>
                            <td>
                                <span class="status status-<?php echo strtolower(str_replace(' ', '', $vr['status'])); ?>">
                                    <?php echo htmlspecialchars($vr['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($vr['created_at'])); ?></td>
                            <td>
                                <div class="actions">
                                    <?php if ($vr['status'] === 'Pendente'): ?>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="vacation_id" value="<?php echo $vr['id']; ?>">
                                            <button type="submit" class="btn btn-approve" onclick="return confirm('Aprovar férias?');">Aprovar</button>
                                        </form>
                                        <button class="btn btn-reject" onclick="openRejectModal(<?php echo $vr['id']; ?>)">Rejeitar</button>
                                    <?php else: ?>
                                        <em style="color: #666666;">Processado</em>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon"></div>
                <p>Nenhuma solicitação de férias encontrada.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal de Rejeição -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <h3>Rejeitar Solicitação de Férias</h3>
            <form method="post" id="rejectForm">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="vacation_id" id="vacationIdInput">
                
                <div style="margin: 20px 0;">
                    <label for="rejection_reason">Motivo da Rejeição:</label>
                    <textarea id="rejection_reason" name="rejection_reason" style="width: 100%; padding: 10px; 
                              border: 1px solid #ddd; border-radius: 4px; min-height: 80px;"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="modal-close" onclick="closeRejectModal()">Cancelar</button>
                    <button type="submit" style="background: #dc3545; color: white; border: none; 
                           padding: 8px 16px; border-radius: 4px; cursor: pointer;">Rejeitar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openRejectModal(vacationId) {
            document.getElementById('vacationIdInput').value = vacationId;
            document.getElementById('rejectModal').classList.add('active');
        }
        
        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('active');
            document.getElementById('rejection_reason').value = '';
        }
    </script>
</body>
</html>
