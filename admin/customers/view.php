<?php
/**
 * Ver Detalhes do Cliente
 * admin/customers/view.php
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Verificar acesso
if (!$auth->is_authenticated()) {
    header('Location: /login.php');
    exit;
}

// Obter ID do cliente
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$customer_id) {
    header('Location: index.php');
    exit;
}

// Buscar dados do cliente
$customer = get_customer($customer_id);

if (!$customer) {
    header('Location: index.php?error=Cliente não encontrado');
    exit;
}

// Buscar histórico de compras
$pdo = db_connect();
$stmt = $pdo->prepare("
    SELECT s.*, 
           COUNT(si.id) as item_count
    FROM sales s
    LEFT JOIN sale_items si ON si.sale_id = s.id
    WHERE s.customer_id = ?
    GROUP BY s.id
    ORDER BY s.sale_date DESC
    LIMIT 20
");
$stmt->execute([$customer_id]);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cliente: <?= htmlspecialchars($customer['name']) ?> - PAP Supermercado</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .view-container {
            max-width: 1000px;
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
            border-left: 4px solid #00d4ff;
        }
        
        .header h1 {
            color: #00d4ff;
            margin: 0;
            font-size: 24px;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
        }
        
        .btn-edit {
            background: #00d4ff;
            color: #000;
        }
        
        .btn-back {
            background: #555;
            color: white;
        }
        
        .loyalty-card {
            background: linear-gradient(135deg, #00d4ff, #00a8cc);
            color: #000;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .loyalty-card .number {
            font-family: monospace;
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .loyalty-card .name {
            font-size: 18px;
            opacity: 0.8;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #1a1a1a;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #333;
            text-align: center;
        }
        
        .stat-card h4 {
            color: #888;
            font-size: 12px;
            margin: 0 0 10px 0;
            text-transform: uppercase;
        }
        
        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
        }
        
        .stat-card .value.points { color: #ffaa00; }
        .stat-card .value.money { color: #10b981; }
        .stat-card .value.purchases { color: #00d4ff; }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: #1a1a1a;
            padding: 25px;
            border-radius: 8px;
            border: 1px solid #333;
        }
        
        .info-card h3 {
            color: #00d4ff;
            margin: 0 0 20px 0;
            font-size: 16px;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #222;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #888;
        }
        
        .info-value {
            color: #fff;
            font-weight: 500;
        }
        
        .purchases-table {
            width: 100%;
            border-collapse: collapse;
            background: #1a1a1a;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .purchases-table th {
            background: #222;
            color: #00d4ff;
            padding: 15px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        .purchases-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #333;
            color: #ddd;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-ativo { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .status-inativo { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
    </style>
</head>
<body class="dark-theme">
    <?php include '../../includes/header.php'; ?>
    
    <div class="view-container">
        <div class="header">
            <h1>👤 <?= htmlspecialchars($customer['name']) ?></h1>
            <div class="header-actions">
                <a href="edit.php?id=<?= $customer_id ?>" class="btn btn-edit">✏️ Editar</a>
                <a href="index.php" class="btn btn-back">← Voltar</a>
            </div>
        </div>
        
        <!-- Cartão de Fidelidade -->
        <div class="loyalty-card">
            <div class="number">🎫 <?= htmlspecialchars($customer['loyalty_card_number']) ?></div>
            <div class="name"><?= htmlspecialchars($customer['name']) ?></div>
        </div>
        
        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <h4>Pontos Atuais</h4>
                <div class="value points"><?= $customer['points_balance'] ?? 0 ?></div>
            </div>
            <div class="stat-card">
                <h4>Total Gasto</h4>
                <div class="value money">€<?= number_format($customer['total_spent'] ?? 0, 2) ?></div>
            </div>
            <div class="stat-card">
                <h4>Compras</h4>
                <div class="value purchases"><?= $customer['total_purchases'] ?? 0 ?></div>
            </div>
            <div class="stat-card">
                <h4>Status</h4>
                <div class="value">
                    <span class="status-badge status-<?= strtolower($customer['status'] ?? 'inativo') ?>">
                        <?= htmlspecialchars($customer['status'] ?? 'N/A') ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Informações -->
        <div class="info-grid">
            <div class="info-card">
                <h3>📋 Dados Pessoais</h3>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?= htmlspecialchars($customer['email'] ?? '-') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Telefone</span>
                    <span class="info-value"><?= htmlspecialchars($customer['phone'] ?? '-') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">NIF</span>
                    <span class="info-value"><?= htmlspecialchars($customer['nif'] ?? '-') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Data Nascimento</span>
                    <span class="info-value"><?= $customer['birth_date'] ? date('d/m/Y', strtotime($customer['birth_date'])) : '-' ?></span>
                </div>
            </div>
            
            <div class="info-card">
                <h3>📍 Outras Informações</h3>
                <div class="info-row">
                    <span class="info-label">Morada</span>
                    <span class="info-value"><?= htmlspecialchars($customer['address'] ?? '-') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Cliente desde</span>
                    <span class="info-value"><?= $customer['created_at'] ? date('d/m/Y', strtotime($customer['created_at'])) : '-' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Notas</span>
                    <span class="info-value"><?= htmlspecialchars($customer['notes'] ?? '-') ?></span>
                </div>
            </div>
        </div>
        
        <!-- Histórico de Compras -->
        <div class="info-card" style="margin-bottom: 30px;">
            <h3>🛒 Últimas Compras</h3>
            <?php if (!empty($purchases)): ?>
                <table class="purchases-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Itens</th>
                            <th>Total</th>
                            <th>Pagamento</th>
                            <th>Pontos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($purchases as $p): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($p['sale_date'])) ?></td>
                                <td><?= $p['item_count'] ?> itens</td>
                                <td>€<?= number_format($p['total'], 2) ?></td>
                                <td><?= htmlspecialchars($p['payment_method'] ?? 'N/A') ?></td>
                                <td style="color: #ffaa00;">+<?= $p['points_earned'] ?? 0 ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 30px;">Nenhuma compra registada.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>
