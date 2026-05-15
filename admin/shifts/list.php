<?php
/**
 * Página de Gestão de Turnos
 * admin/shifts/list.php
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

// Processar criar novo turno
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $data = [
        'name' => $_POST['name'] ?? '',
        'start_time' => $_POST['start_time'] ?? '',
        'end_time' => $_POST['end_time'] ?? '',
        'break_duration' => $_POST['break_duration'] ?? 60,
        'description' => $_POST['description'] ?? ''
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

// Obter turnos
$shifts = list_shifts();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Turnos</title>
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
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 40px; 
        }
        
        .header h1 {
            color: #ffffff;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin: 0;
        }
        
        .btn { 
            padding: 12px 24px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 14px; 
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .btn-primary { 
            background: #a1a1aa; 
            color: white; 
        }
        
        .btn-primary:hover { 
            background: #1d4ed8; 
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(161, 161, 170, 0.4);
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
        
        .table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 24px; 
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
        
        .form-container { 
            background: #141414; 
            padding: 32px; 
            border-radius: 12px; 
            margin-bottom: 40px; 
            border: 1px solid #222222;
            box-shadow: 0 1px 3px rgba(0,0,0,0.5);
        }
        
        .form-container h3 {
            color: #ffffff;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 24px;
        }
        
        .form-group { 
            margin-bottom: 20px; 
        }
        
        .form-group label { 
            display: block; 
            margin-bottom: 10px; 
            font-weight: 600; 
            color: #a0a0a0;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .form-group input, 
        .form-group textarea { 
            width: 100%; 
            padding: 12px 16px; 
            border: 1px solid #222222; 
            border-radius: 8px; 
            box-sizing: border-box;
            background: #1a1a1a;
            color: #ffffff;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .form-group input:focus, 
        .form-group textarea:focus {
            outline: none;
            border-color: #a1a1aa;
            background: #111111;
            box-shadow: 0 0 0 3px rgba(161, 161, 170, 0.1);
        }
        
        .form-group input::placeholder {
            color: #666666;
        }
        
        .form-row { 
            display: grid; 
            grid-template-columns: 1fr 1fr 1fr; 
            gap: 20px; 
        }
        
        .shift-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(161, 161, 170, 0.2);
            color: #a1a1aa;
            border: 1px solid #a1a1aa;
        }
        
        .duration-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
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
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="back-link">
            <a href="/admin/employees/list.php">← Voltar à Gestão RH</a>
        </div>

        <div class="header">
            <h1>Gestão de Turnos</h1>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Formulário de Criação -->
        <div class="form-container">
            <h3>Criar Novo Turno</h3>
            <form method="post">
                <input type="hidden" name="action" value="create">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Nome do Turno *</label>
                        <input type="text" id="name" name="name" required placeholder="Ex: Manhã">
                    </div>
                    <div class="form-group">
                        <label for="start_time">Hora Início *</label>
                        <input type="time" id="start_time" name="start_time" required>
                    </div>
                    <div class="form-group">
                        <label for="end_time">Hora Fim *</label>
                        <input type="time" id="end_time" name="end_time" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="break_duration">Duração Pausa (minutos)</label>
                        <input type="number" id="break_duration" name="break_duration" value="60" min="0">
                    </div>
                    <div class="form-group">
                        <label for="description">Descrição</label>
                        <input type="text" id="description" name="description" placeholder="Descrição adicional">
                    </div>
                    <div style="display: flex; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Criar Turno</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Lista de Turnos -->
        <h3>Turnos Disponíveis</h3>
        
        <?php if (!empty($shifts)): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Hora Início</th>
                        <th>Hora Fim</th>
                        <th>Duração</th>
                        <th>Pausa</th>
                        <th>Descrição</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shifts as $shift): 
                        $start = new DateTime('2000-01-01 ' . $shift['start_time']);
                        $end = new DateTime('2000-01-01 ' . $shift['end_time']);
                        $duration = $end->diff($start);
                        $hours = $duration->h + ($duration->i / 60);
                    ?>
                        <tr>
                            <td>
                                <span class="shift-badge">
                                    <?php echo htmlspecialchars($shift['name']); ?>
                                </span>
                            </td>
                            <td><strong><?php echo substr($shift['start_time'], 0, 5); ?></strong></td>
                            <td><strong><?php echo substr($shift['end_time'], 0, 5); ?></strong></td>
                            <td>
                                <span class="duration-badge">
                                    <?php echo number_format($hours, 1); ?>h
                                </span>
                            </td>
                            <td><?php echo $shift['break_duration']; ?> min</td>
                            <td style="color: #a0a0a0;"><?php echo htmlspecialchars($shift['description'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">⏰</div>
                <p>Nenhum turno criado ainda. Crie o primeiro turno usando o formulário acima.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
