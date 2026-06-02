<?php
// modules/auditoria.php
// Audit log management and reporting

require_once __DIR__ . '/../includes/auth.php';

$auth->require_auth('auditoria', 'view');
$current_user = $auth->get_current_user();

$filter_resource = $_GET['resource'] ?? '';
$filter_user = $_GET['user_id'] ?? '';
$audit_logs = $auth->get_audit_logs(500, $filter_resource ?: null, $filter_user ?: null);
?>

<div class="module-container">
    <div class="module-header">
        <h2> Auditoria & Logs</h2>
        <p>Histório completo de todas as ações no sistema</p>
    </div>
    
    <div class="filters">
        <form method="GET" class="filter-form">
            <input type="hidden" name="page" value="auditoria">
            
            <div class="filter-group">
                <label>Recurso</label>
                <select name="resource">
                    <option value="">Todos</option>
                    <option value="users" <?php echo $filter_resource === 'users' ? 'selected' : ''; ?>>Utilizadores</option>
                    <option value="products" <?php echo $filter_resource === 'products' ? 'selected' : ''; ?>>Produtos</option>
                    <option value="sales" <?php echo $filter_resource === 'sales' ? 'selected' : ''; ?>>Vendas</option>
                    <option value="stock" <?php echo $filter_resource === 'stock' ? 'selected' : ''; ?>>Stock</option>
                    <option value="employees" <?php echo $filter_resource === 'employees' ? 'selected' : ''; ?>>Funcionários</option>
                    <option value="login" <?php echo $filter_resource === 'login' ? 'selected' : ''; ?>>Logins</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="?page=auditoria" class="btn btn-secondary">Limpar</a>
        </form>
    </div>
    
    <div class="audit-stats">
        <div class="stat-card">
            <h4>Total de Ações</h4>
            <p class="stat-value"><?php echo count($audit_logs); ?></p>
        </div>
        
        <div class="stat-card">
            <h4>Últimas 24h</h4>
            <p class="stat-value">
                <?php 
                $count_24h = 0;
                foreach ($audit_logs as $log) {
                    $timestamp = strtotime($log['created_at']);
                    if (time() - $timestamp < 86400) {
                        $count_24h++;
                    }
                }
                echo $count_24h;
                ?>
            </p>
        </div>
        
        <div class="stat-card">
            <h4>Utilizadores Ativos</h4>
            <p class="stat-value">
                <?php echo count(array_unique(array_column($audit_logs, 'user_id'))); ?>
            </p>
        </div>
    </div>
    
    <div class="table-container">
        <table class="audit-table">
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Utilizador</th>
                    <th>Ação</th>
                    <th>Recurso</th>
                    <th>ID</th>
                    <th>Status</th>
                    <th>IP</th>
                    <th>Detalhes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($audit_logs as $log): ?>
                <tr class="status-<?php echo strtolower($log['status']); ?>">
                    <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($log['user_name'] ?? 'Sistema'); ?></td>
                    <td><strong><?php echo htmlspecialchars($log['action']); ?></strong></td>
                    <td><?php echo htmlspecialchars($log['resource']); ?></td>
                    <td><?php echo $log['resource_id'] ? '#' . $log['resource_id'] : '-'; ?></td>
                    <td>
                        <span class="badge badge-<?php echo strtolower($log['status']); ?>">
                            <?php echo $log['status']; ?>
                        </span>
                    </td>
                    <td><small><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></small></td>
                    <td>
                        <?php if ($log['changes']): ?>
                        <button class="btn-small" onclick="showChanges(<?php echo htmlspecialchars(json_encode(json_decode($log['changes'], true))); ?>)">Ver</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.audit-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.stat-card h4 {
    margin: 0 0 10px 0;
    color: #666;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
}

.stat-value {
    margin: 0;
    font-size: 32px;
    font-weight: bold;
    color: #667eea;
}

.audit-table {
    width: 100%;
    border-collapse: collapse;
}

.audit-table th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    font-size: 14px;
    border-bottom: 2px solid #ddd;
}

.audit-table td {
    padding: 12px;
    border-bottom: 1px solid #eee;
}

.audit-table tr:hover {
    background: #f9f9f9;
}

.audit-table tr.status-success {
    border-left: 3px solid #27ae60;
}

.audit-table tr.status-failed {
    border-left: 3px solid #e74c3c;
}

.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.badge-success {
    background: #d4edda;
    color: #155724;
}

.badge-failed {
    background: #f8d7da;
    color: #721c24;
}

.filters {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.filter-form {
    display: flex;
    gap: 15px;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
}

.filter-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
}

.filter-group select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
</style>

<script>
function showChanges(changes) {
    alert(JSON.stringify(changes, null, 2));
}
</script>
