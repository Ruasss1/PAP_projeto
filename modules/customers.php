<?php
/**
 * CLIENTES - PREMIUM
 * Gestão moderna de clientes
 */

header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();
$message = '';
$error = '';

// Criar/Editar cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $nif = trim($_POST['nif'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($name)) {
        $error = "Nome é obrigatório.";
    } else {
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE customers SET name=?, email=?, phone=?, nif=?, address=?, notes=? WHERE id=?");
                $stmt->execute([$name, $email, $phone, $nif, $address, $notes, $id]);
                $message = "Cliente atualizado!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO customers (name, email, phone, nif, address, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $email, $phone, $nif, $address, $notes]);
                $message = "Cliente criado!";
            }
        } catch (PDOException $e) {
            $error = "Erro: " . $e->getMessage();
        }
    }
}

// Eliminar
if (isset($_GET['delete'])) {
    try {
        $pdo->prepare("DELETE FROM customers WHERE id = ?")->execute([intval($_GET['delete'])]);
        $message = "Cliente eliminado.";
    } catch (PDOException $e) {
        $error = "Erro ao eliminar.";
    }
}

// Edição
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $editing = $stmt->fetch();
}

// Pesquisa
$search = $_GET['search'] ?? '';
$sql = "SELECT c.*, 
        (SELECT COUNT(*) FROM sales WHERE nif = c.nif AND c.nif IS NOT NULL) as sale_count,
        (SELECT COALESCE(SUM(total), 0) FROM sales WHERE nif = c.nif AND c.nif IS NOT NULL) as total_spent
        FROM customers c WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.nif LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}

$sql .= " ORDER BY c.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// Estatísticas
$stats = $pdo->query("SELECT 
    COUNT(*) as total,
    (SELECT COUNT(*) FROM customers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_month,
    (SELECT COUNT(DISTINCT c.id) FROM customers c INNER JOIN sales s ON c.nif = s.nif WHERE s.nif IS NOT NULL) as with_purchases
    FROM customers")->fetch();

$total_revenue = $pdo->query("SELECT COALESCE(SUM(s.total), 0) as total FROM sales s INNER JOIN customers c ON s.nif = c.nif WHERE s.nif IS NOT NULL")->fetch()['total'];

$page_title = 'Clientes';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success"><?= $message ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon blue">👥</div>
        </div>
        <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
        <div class="stat-label">Total Clientes</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon green">🆕</div>
        </div>
        <div class="stat-value"><?= $stats['new_month'] ?? 0 ?></div>
        <div class="stat-label">Novos (30 dias)</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon purple">🛒</div>
        </div>
        <div class="stat-value"><?= $stats['with_purchases'] ?? 0 ?></div>
        <div class="stat-label">Com Compras</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon orange">💰</div>
        </div>
        <div class="stat-value money-positive">€<?= number_format($total_revenue, 2, ',', '.') ?></div>
        <div class="stat-label">Receita Total</div>
    </div>
</div>

<!-- Formulário -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3 class="card-title"><?= $editing ? '✏️ Editar Cliente' : '➕ Novo Cliente' ?></h3>
        <?php if ($editing): ?>
        <a href="/modules/customers.php" class="btn btn-secondary btn-sm">✕ Cancelar</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form method="post">
            <?php if ($editing): ?>
            <input type="hidden" name="id" value="<?= $editing['id'] ?>">
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Nome *</label>
                    <input type="text" name="name" class="form-input" required value="<?= htmlspecialchars($editing['name'] ?? '') ?>" placeholder="Nome completo">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($editing['email'] ?? '') ?>" placeholder="email@exemplo.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Telefone</label>
                    <input type="text" name="phone" class="form-input" value="<?= htmlspecialchars($editing['phone'] ?? '') ?>" placeholder="912 345 678">
                </div>
                <div class="form-group">
                    <label class="form-label">NIF</label>
                    <input type="text" name="nif" class="form-input" value="<?= htmlspecialchars($editing['nif'] ?? '') ?>" placeholder="123456789" maxlength="9" minlength="9" pattern="[0-9]{9}" title="O NIF deve ter exatamente 9 dígitos">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Morada</label>
                    <input type="text" name="address" class="form-input" value="<?= htmlspecialchars($editing['address'] ?? '') ?>" placeholder="Morada completa">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Notas</label>
                <textarea name="notes" class="form-input" rows="2" placeholder="Observações..."><?= htmlspecialchars($editing['notes'] ?? '') ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <?= $editing ? '💾 Guardar Alterações' : '➕ Criar Cliente' ?>
            </button>
        </form>
    </div>
</div>

<!-- Pesquisa -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 24px;">
        <form method="get" style="display: flex; gap: 16px; align-items: flex-end;">
            <div class="form-group" style="margin: 0; flex: 1;">
                <label class="form-label">Pesquisar</label>
                <input type="text" name="search" class="form-input" placeholder="Nome, email, telefone ou NIF..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn btn-primary">🔍 Pesquisar</button>
            <?php if ($search): ?>
            <a href="/modules/customers.php" class="btn btn-secondary">Limpar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Lista -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Lista de Clientes (<?= count($customers) ?>)</h3>
    </div>
    <div class="table-container" style="border: none;">
        <table class="table">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Contacto</th>
                    <th>NIF</th>
                    <th>Compras</th>
                    <th>Total Gasto</th>
                    <th style="width: 120px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                <tr>
                    <td colspan="6" class="table-empty">
                        <div class="empty-state">
                            <div class="empty-icon">👥</div>
                            <div class="empty-title">Sem clientes</div>
                            <div class="empty-text">Adicione o primeiro cliente acima.</div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($customers as $customer): ?>
                <tr>
                    <td>
                        <div style="font-weight: 600;"><?= htmlspecialchars($customer['name']) ?></div>
                        <?php if ($customer['address']): ?>
                        <div style="font-size: 12px; color: var(--text-muted);">📍 <?= htmlspecialchars($customer['address']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($customer['email']): ?>
                        <div style="font-size: 13px;">📧 <?= htmlspecialchars($customer['email']) ?></div>
                        <?php endif; ?>
                        <?php if ($customer['phone']): ?>
                        <div style="font-size: 13px;">📞 <?= htmlspecialchars($customer['phone']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($customer['nif'] ?? '-') ?></td>
                    <td>
                        <span class="badge badge-primary"><?= $customer['sale_count'] ?> compras</span>
                    </td>
                    <td>
                        <strong class="money-positive">€<?= number_format($customer['total_spent'], 2) ?></strong>
                    </td>
                    <td>
                        <div style="display: flex; gap: 8px;">
                            <a href="?edit=<?= $customer['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
                            <a href="?delete=<?= $customer['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Eliminar este cliente?')">🗑️</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>