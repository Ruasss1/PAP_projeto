<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// Verificar acesso
if (!$auth->is_authenticated()) {
    header('Location: /login.php');
    exit;
}

$pdo = db_connect();

// Obter lista de clientes
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

$sql = "SELECT * FROM customers WHERE 1=1";
$params = [];
if ($search) {
    $sql .= " AND (name LIKE :search OR email LIKE :search OR phone LIKE :search OR nif LIKE :search)";
    $params[':search'] = "%{$search}%";
}
$sql .= " ORDER BY name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes e Fidelização - PAP Supermercado</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .customers-container {
            max-width: 1200px;
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
            font-size: 28px;
        }

        .btn-new {
            background: #00d4ff;
            color: #000;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-new:hover {
            background: #00a8cc;
            transform: translateY(-2px);
        }

        .filters {
            background: #1a1a1a;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #333;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filters input,
        .filters select {
            background: #2a2a2a;
            color: #fff;
            border: 1px solid #444;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
        }

        .filters button {
            background: #00d4ff;
            color: #000;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }

        .table-wrapper {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            overflow: hidden;
        }

        .customers-table {
            width: 100%;
            border-collapse: collapse;
            color: #ddd;
        }

        .customers-table thead {
            background: #2a2a2a;
            border-bottom: 2px solid #00d4ff;
        }

        .customers-table th {
            padding: 15px;
            text-align: left;
            font-weight: bold;
            color: #00d4ff;
        }

        .customers-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #333;
        }

        .customers-table tbody tr:hover {
            background: #2a2a2a;
        }

        .customer-card-number {
            background: #00d4ff;
            color: #000;
            padding: 4px 8px;
            border-radius: 3px;
            font-family: monospace;
            font-weight: bold;
            font-size: 12px;
        }

        .points-badge {
            background: #ffaa00;
            color: #000;
            padding: 4px 8px;
            border-radius: 3px;
            font-weight: bold;
        }

        .status-active {
            color: #00d4ff;
        }

        .status-inactive {
            color: #888;
        }

        .status-blocked {
            color: #ff4444;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-view,
        .btn-edit {
            background: #00d4ff;
            color: #000;
            border: none;
            padding: 6px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            transition: all 0.3s;
        }

        .btn-view:hover,
        .btn-edit:hover {
            background: #00a8cc;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #888;
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            font-size: 32px;
            font-weight: bold;
        }
    </style>
</head>
<body class="dark-theme">
    <?php include '../../includes/header.php'; ?>

    <div class="customers-container">
        <div class="header">
            <div>
                <h1> Clientes e Fidelização</h1>
            </div>
            <a href="novo.php" class="btn-new">+ Novo Cliente</a>
        </div>

        <!-- Estatísticas -->
        <div class="stats">
            <div class="stat-card">
                <h3>Total de Clientes</h3>
                <div class="value"><?php echo count($customers); ?></div>
            </div>
            <div class="stat-card">
                <h3>Clientes Ativos</h3>
                <div class="value"><?php echo count(array_filter($customers, fn($c) => $c['status'] === 'Ativo')); ?></div>
            </div>
            <div class="stat-card">
                <h3>Pontos em Circulação</h3>
                <div class="value"><?php echo array_sum(array_column($customers, 'points_balance')); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Gasto</h3>
                <div class="value">€<?php echo number_format(array_sum(array_column($customers, 'total_spent')), 2); ?></div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters">
            <form method="get" style="display: flex; gap: 10px; width: 100%; flex-wrap: wrap;">
                <input type="text" name="search" placeholder="Procurar por nome, email ou NIF..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; min-width: 200px;">
                <select name="status">
                    <option value="">Todos os Status</option>
                    <option value="Ativo" <?php echo $status === 'Ativo' ? 'selected' : ''; ?>>Ativo</option>
                    <option value="Inativo" <?php echo $status === 'Inativo' ? 'selected' : ''; ?>>Inativo</option>
                    <option value="Bloqueado" <?php echo $status === 'Bloqueado' ? 'selected' : ''; ?>>Bloqueado</option>
                </select>
                <button type="submit"> Pesquisar</button>
            </form>
        </div>

        <!-- Tabela de Clientes -->
        <div class="table-wrapper">
            <?php if (!empty($customers)): ?>
                <table class="customers-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Cartão Fidelidade</th>
                            <th>Email</th>
                            <th>Pontos</th>
                            <th>Total Gasto</th>
                            <th>Compras</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($customer['name']); ?></strong></td>
                                <td><span class="customer-card-number"><?php echo htmlspecialchars($customer['loyalty_card_number']); ?></span></td>
                                <td><?php echo htmlspecialchars($customer['email'] ?? '-'); ?></td>
                                <td><span class="points-badge"><?php echo $customer['points_balance']; ?> pts</span></td>
                                <td>€<?php echo number_format($customer['total_spent'] ?? 0, 2); ?></td>
                                <td><?php echo $customer['total_purchases'] ?? 0; ?></td>
                                <td>
                                    <span class="status-<?php echo strtolower($customer['status']); ?>">
                                        <?php echo htmlspecialchars($customer['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view.php?id=<?php echo $customer['id']; ?>" class="btn-view">Ver</a>
                                        <a href="edit.php?id=<?php echo $customer['id']; ?>" class="btn-edit">Editar</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 1C6.48 1 2 5.48 2 11s4.48 10 10 10 10-4.48 10-10S17.52 1 12 1zm0 19c-4.96 0-9-4.04-9-9s4.04-9 9-9 9 4.04 9 9-4.04 9-9 9zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 7 15.5 7 14 7.67 14 8.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 7 8.5 7 7 7.67 7 8.5 7.67 10 8.5 10zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/></svg>
                    <h3>Nenhum cliente encontrado</h3>
                    <p><?php echo !empty($filters) ? 'Tente refinar a sua pesquisa' : 'Comece por adicionar o primeiro cliente'; ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>
