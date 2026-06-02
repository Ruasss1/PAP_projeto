<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../modules/notifications.php';

if (!$auth->is_authenticated()) {
    header('Location: /login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Obter notificações
$notifications = get_user_notifications($user_id);
$unread_count = count(array_filter($notifications, fn($n) => !$n['is_read']));

// Obter alertas ativos
$active_alerts = list_active_alerts();
$pending_tasks = list_tasks(['assigned_to' => $user_id, 'status' => 'Pendente']);

// Marcar como lida se id fornecido
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    mark_notification_read($_GET['mark_read']);
    header('Location: index.php');
    exit;
}

if (isset($_GET['mark_all_read'])) {
    mark_all_notifications_read($user_id);
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificações e Alertas - PAP Supermercado</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .notifications-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #ff6b6b;
        }

        .header h1 {
            color: #ff6b6b;
            margin: 0;
            font-size: 28px;
        }

        .unread-badge {
            background: #ff6b6b;
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }

        .grid-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .section-card {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            overflow: hidden;
        }

        .section-header {
            background: #2a2a2a;
            padding: 15px 20px;
            border-bottom: 2px solid #00d4ff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-header h2 {
            color: #00d4ff;
            margin: 0;
            font-size: 18px;
        }

        .btn-small {
            background: #00d4ff;
            color: #000;
            border: none;
            padding: 6px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
        }

        .btn-small:hover {
            background: #00a8cc;
        }

        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid #333;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            transition: all 0.3s;
        }

        .notification-item:hover {
            background: #2a2a2a;
        }

        .notification-item.unread {
            background: #2a3a4a;
            border-left: 3px solid #00d4ff;
        }

        .notification-icon {
            font-size: 24px;
            flex-shrink: 0;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            color: #fff;
            font-weight: bold;
            margin: 0 0 5px 0;
        }

        .notification-message {
            color: #aaa;
            font-size: 13px;
            margin: 0 0 8px 0;
        }

        .notification-meta {
            font-size: 11px;
            color: #666;
            display: flex;
            gap: 15px;
        }

        .priority-high {
            color: #ff4444;
            font-weight: bold;
        }

        .priority-normal {
            color: #ffaa00;
        }

        .priority-low {
            color: #00d4ff;
        }

        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: #666;
        }

        .alert-item {
            padding: 12px 15px;
            border-bottom: 1px solid #333;
            border-left: 3px solid #ff6b6b;
        }

        .alert-item:last-child {
            border-bottom: none;
        }

        .alert-type {
            font-weight: bold;
            color: #ff6b6b;
            font-size: 12px;
        }

        .alert-message {
            color: #aaa;
            font-size: 13px;
            margin-top: 4px;
        }

        .task-item {
            padding: 12px 15px;
            border-bottom: 1px solid #333;
            border-left: 3px solid #ffaa00;
        }

        .task-item:last-child {
            border-bottom: none;
        }

        .task-title {
            color: #fff;
            font-weight: bold;
            font-size: 14px;
        }

        .task-due {
            color: #888;
            font-size: 12px;
            margin-top: 4px;
        }

        .task-due.overdue {
            color: #ff4444;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #00d4ff;
            text-align: center;
        }

        .stat-card h3 {
            color: #888;
            font-size: 12px;
            text-transform: uppercase;
            margin: 0 0 10px 0;
        }

        .stat-card .value {
            color: #00d4ff;
            font-size: 28px;
            font-weight: bold;
        }

        .stat-card.alerts .value,
        .stat-card.alerts {
            border-left-color: #ff6b6b;
            color: #ff6b6b;
        }

        .stat-card.alerts .value {
            color: #ff6b6b;
        }

        .stat-card.tasks {
            border-left-color: #ffaa00;
        }

        .stat-card.tasks .value {
            color: #ffaa00;
        }

        @media (max-width: 768px) {
            .grid-layout {
                grid-template-columns: 1fr;
            }
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body class="dark-theme">
    <?php include '../../includes/header.php'; ?>

    <div class="notifications-container">
        <div class="header">
            <div>
                <h1> Notificações e Alertas
                    <?php if ($unread_count > 0): ?>
                        <span class="unread-badge"><?php echo $unread_count; ?> não lidas</span>
                    <?php endif; ?>
                </h1>
            </div>
            <?php if ($unread_count > 0): ?>
                <a href="?mark_all_read=1" class="btn-small">Marcar Tudo como Lido</a>
            <?php endif; ?>
        </div>

        <!-- Estatísticas -->
        <div class="stats">
            <div class="stat-card">
                <h3>Notificações Não Lidas</h3>
                <div class="value"><?php echo $unread_count; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total de Notificações</h3>
                <div class="value"><?php echo count($notifications); ?></div>
            </div>
            <div class="stat-card alerts">
                <h3>Alertas Ativos</h3>
                <div class="value"><?php echo count($active_alerts); ?></div>
            </div>
            <div class="stat-card tasks">
                <h3>Tarefas Pendentes</h3>
                <div class="value"><?php echo count($pending_tasks); ?></div>
            </div>
        </div>

        <!-- Layout Principal -->
        <div class="grid-layout">
            <!-- Notificações -->
            <div class="section-card">
                <div class="section-header">
                    <h2> Notificações</h2>
                </div>
                <?php if (!empty($notifications)): ?>
                    <div>
                        <?php foreach ($notifications as $notif): ?>
                            <div class="notification-item <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                                <span class="notification-icon"><?php
                                    $icons = [
                                        'Info' => '',
                                        'Sucesso' => '',
                                        'Aviso' => '',
                                        'Erro' => '',
                                        'Stock' => '',
                                        'Venda' => '',
                                        'RH' => '',
                                        'Cliente' => '',
                                        'Sistema' => ''
                                    ];
                                    echo $icons[$notif['type']] ?? '';
                                ?></span>
                                <div class="notification-content">
                                    <p class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></p>
                                    <p class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></p>
                                    <div class="notification-meta">
                                        <span class="priority-<?php echo strtolower($notif['priority'] ?? 'normal'); ?>">
                                            <?php echo htmlspecialchars($notif['priority'] ?? 'Normal'); ?>
                                        </span>
                                        <span><?php echo date('d/m/Y H:i', strtotime($notif['created_at'])); ?></span>
                                        <?php if (!$notif['is_read']): ?>
                                            <a href="?mark_read=<?php echo $notif['id']; ?>" style="color: #00d4ff; text-decoration: none;">Marcar Lida</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p> Sem notificações no momento</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Alertas e Tarefas (Coluna Direita) -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <!-- Alertas Ativos -->
                <div class="section-card">
                    <div class="section-header">
                        <h2>  Alertas (<?php echo count($active_alerts); ?>)</h2>
                    </div>
                    <?php if (!empty($active_alerts)): ?>
                        <div>
                            <?php foreach (array_slice($active_alerts, 0, 5) as $alert): ?>
                                <div class="alert-item">
                                    <div class="alert-type"><?php echo htmlspecialchars($alert['alert_type']); ?></div>
                                    <div class="alert-message"><?php echo htmlspecialchars($alert['message'] ?? $alert['alert_type']); ?></div>
                                    <div style="font-size: 11px; color: #666; margin-top: 4px;">
                                        <?php echo date('d/m/Y H:i', strtotime($alert['created_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 20px;">
                            <p style="margin: 0;"> Sem alertas ativos</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Tarefas Pendentes -->
                <div class="section-card">
                    <div class="section-header">
                        <h2> Tarefas (<?php echo count($pending_tasks); ?>)</h2>
                    </div>
                    <?php if (!empty($pending_tasks)): ?>
                        <div>
                            <?php foreach (array_slice($pending_tasks, 0, 5) as $task): ?>
                                <div class="task-item">
                                    <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                    <div class="task-due <?php echo strtotime($task['due_date']) < time() ? 'overdue' : ''; ?>">
                                         <?php echo date('d/m/Y', strtotime($task['due_date'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 20px;">
                            <p style="margin: 0;"> Sem tarefas pendentes</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>
