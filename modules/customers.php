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
            <div class="stat-icon blue"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
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
            <div class="stat-icon purple"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg></div>
        </div>
        <div class="stat-value"><?= $stats['with_purchases'] ?? 0 ?></div>
        <div class="stat-label">Com Compras</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon orange"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
        </div>
        <div class="stat-value money-positive">€<?= number_format($total_revenue, 2, ',', '.') ?></div>
        <div class="stat-label">Receita Total</div>
    </div>
</div>

<!-- Formulário -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3 class="card-title"><?= $editing ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Editar Cliente' : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Novo Cliente' ?></h3>
        <?php if ($editing): ?>
        <a href="/modules/customers.php" class="btn btn-secondary btn-sm"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Cancelar</a>
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
                <?= $editing ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Guardar Alterações' : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Criar Cliente' ?>
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
            <button type="submit" class="btn btn-primary"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> Pesquisar</button>
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
                            <div class="empty-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                            <div class="empty-title">Sem clientes</div>
                            <div class="empty-text">Adicione o primeiro cliente acima.</div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($customers as $customer):
                    $cNameParts = explode(' ', trim($customer['name']));
                    $cInitials = strtoupper(substr($cNameParts[0] ?? '', 0, 1) . substr(end($cNameParts) ?: '', 0, 1));
                ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <div style="width:36px;height:36px;border-radius:50%;background:var(--bg-tertiary);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:var(--text-secondary);flex-shrink:0;"><?= htmlspecialchars($cInitials) ?></div>
                            <div>
                                <div style="font-weight:600;"><?= htmlspecialchars($customer['name']) ?></div>
                                <?php if ($customer['address']): ?>
                                <div style="font-size:12px;color:var(--text-muted);"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg> <?= htmlspecialchars($customer['address']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ($customer['email']): ?>
                        <div style="font-size: 13px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg> <?= htmlspecialchars($customer['email']) ?></div>
                        <?php endif; ?>
                        <?php if ($customer['phone']): ?>
                        <div style="font-size: 13px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.59 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.09 6.09l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg> <?= htmlspecialchars($customer['phone']) ?></div>
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
                            <a href="?edit=<?= $customer['id'] ?>" class="btn btn-secondary btn-sm"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
                            <a href="?delete=<?= $customer['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Eliminar este cliente?')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></a>
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