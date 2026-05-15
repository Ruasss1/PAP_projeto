<?php
/**
 * FORNECEDORES - PREMIUM
 * Gestão moderna de fornecedores
 */

header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db_connect();
$current_store_id = get_current_store_id();
$message = '';
$error = '';

// Ações do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'deactivate' || $action === 'reactivate') {
        $supplier_id = intval($_POST['supplier_id'] ?? 0);
        if ($supplier_id <= 0) {
            $error = 'Fornecedor inválido.';
        } else {
            try {
                $active_value = $action === 'reactivate' ? 1 : 0;
                $stmt = $pdo->prepare("UPDATE suppliers SET active = ? WHERE id = ?");
                $stmt->execute([$active_value, $supplier_id]);
                $message = $action === 'reactivate' ? 'Fornecedor reativado.' : 'Fornecedor desativado.';
            } catch (PDOException $e) {
                $error = 'Erro ao atualizar estado do fornecedor.';
            }
        }
    } else {
        $id = $_POST['id'] ?? null;
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $nif = trim($_POST['nif'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        if (empty($name)) {
            $error = 'Nome do fornecedor é obrigatório.';
        } else {
            try {
                if ($id) {
                    $stmt = $pdo->prepare("UPDATE suppliers SET name=?, email=?, phone=?, address=?, nif=?, active=? WHERE id=?");
                    $stmt->execute([$name, $email, $phone, $address, $nif, $active, $id]);
                    $message = 'Fornecedor atualizado com sucesso!';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO suppliers (name, email, phone, address, nif, active) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $email, $phone, $address, $nif, $active]);
                    $message = 'Fornecedor criado com sucesso!';
                }
            } catch (PDOException $e) {
                $error = 'Erro ao guardar fornecedor.';
            }
        }
    }
}

// Buscar fornecedor para edição
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $editing = $stmt->fetch();
}

// Listar fornecedores
$search = $_GET['search'] ?? '';
$sql = "SELECT s.*, 
        (SELECT COUNT(*) FROM products WHERE supplier_id = s.id) as product_count,
        (SELECT COUNT(*) FROM orders WHERE supplier_id = s.id) as order_count
        FROM suppliers s WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (s.name LIKE ? OR s.email LIKE ? OR s.nif LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

$sql .= " ORDER BY s.active DESC, s.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();

// Estatísticas
$total_active = count(array_filter($suppliers, fn($s) => $s['active']));
$total_products = array_sum(array_column($suppliers, 'product_count'));
$total_orders = array_sum(array_column($suppliers, 'order_count'));

$page_title = 'Fornecedores';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.suppliers-stats { grid-template-columns: repeat(4, 1fr); }
.suppliers-card-gap { margin-bottom: 24px; }
.suppliers-form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.suppliers-address { grid-column: span 2; }
.suppliers-actions { display: flex; gap: 12px; margin-top: 16px; }
.suppliers-search-row { display: flex; gap: 16px; align-items: flex-end; }
.suppliers-row-actions { display: flex; gap: 8px; }
.supplier-inactive { opacity: .5; }

.products-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .55);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
.products-modal-card {
    background: var(--bg-secondary);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    max-width: 640px;
    width: 90%;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,.35);
}

@media (max-width: 1100px) {
    .suppliers-form-grid { grid-template-columns: repeat(2, 1fr); }
    .suppliers-address { grid-column: span 2; }
}
@media (max-width: 700px) {
    .suppliers-stats,
    .suppliers-form-grid { grid-template-columns: 1fr; }
    .suppliers-address { grid-column: span 1; }
    .suppliers-search-row { flex-direction: column; align-items: stretch; }
}
</style>

<?php if ($message): ?>
<div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid suppliers-stats">
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg></div>
        </div>
        <div class="stat-value"><?= count($suppliers) ?></div>
        <div class="stat-label">Total Fornecedores</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
        </div>
        <div class="stat-value"><?= $total_active ?></div>
        <div class="stat-label">Ativos</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg></div>
        </div>
        <div class="stat-value"><?= $total_products ?></div>
        <div class="stat-label">Produtos Associados</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg></div>
        </div>
        <div class="stat-value"><?= $total_orders ?></div>
        <div class="stat-label">Total Encomendas</div>
    </div>
</div>

<!-- Form -->
<div class="card suppliers-card-gap">
    <div class="card-header">
        <h3 class="card-title"><?= $editing ? 'Editar Fornecedor' : 'Novo Fornecedor' ?></h3>
        <?php if ($editing): ?>
        <a href="/modules/fornecedores.php" class="btn btn-secondary btn-sm">Cancelar</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form method="post">
            <?php if ($editing): ?>
            <input type="hidden" name="id" value="<?= $editing['id'] ?>">
            <?php endif; ?>
            
            <div class="suppliers-form-grid">
                <div class="form-group">
                    <label class="form-label">Nome *</label>
                    <input type="text" name="name" class="form-input" required value="<?= htmlspecialchars($editing['name'] ?? '') ?>" placeholder="Nome do fornecedor">
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
                <div class="form-group suppliers-address">
                    <label class="form-label">Morada</label>
                    <input type="text" name="address" class="form-input" value="<?= htmlspecialchars($editing['address'] ?? '') ?>" placeholder="Morada completa">
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 8px;">
                <label class="form-checkbox">
                    <input type="checkbox" name="active" <?= ($editing['active'] ?? 1) ? 'checked' : '' ?>>
                    <span>Fornecedor Ativo</span>
                </label>
            </div>
            
            <div class="suppliers-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $editing ? 'Guardar Alterações' : 'Criar Fornecedor' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Pesquisa -->
<div class="card suppliers-card-gap">
    <div class="card-body" style="padding: 16px 24px;">
        <form method="get" class="suppliers-search-row">
            <div class="form-group" style="margin: 0; flex: 1;">
                <label class="form-label">Pesquisar</label>
                <input type="text" name="search" class="form-input" placeholder="Nome, email ou NIF..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn btn-primary">Pesquisar</button>
            <?php if ($search): ?>
            <a href="/modules/fornecedores.php" class="btn btn-secondary">Limpar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Lista -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Lista de Fornecedores (<?= count($suppliers) ?>)</h3>
    </div>
    <div class="table-container" style="border: none;">
        <table class="table">
            <thead>
                <tr>
                    <th>Fornecedor</th>
                    <th>Contacto</th>
                    <th>NIF</th>
                    <th>Produtos</th>
                    <th>Encomendas</th>
                    <th>Estado</th>
                    <th style="width: 150px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suppliers)): ?>
                <tr>
                    <td colspan="7" class="table-empty">
                        <div class="empty-state">
                            <div class="empty-icon">🏢</div>
                            <div class="empty-title">Sem fornecedores</div>
                            <div class="empty-text">Adicione o primeiro fornecedor acima.</div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($suppliers as $supplier): ?>
                <tr class="<?= !$supplier['active'] ? 'supplier-inactive' : '' ?>">
                    <td>
                        <div style="font-weight: 600;"><?= htmlspecialchars($supplier['name']) ?></div>
                        <?php if ($supplier['address']): ?>
                        <div style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($supplier['address']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($supplier['email']): ?>
                        <div style="font-size: 13px;"><?= htmlspecialchars($supplier['email']) ?></div>
                        <?php endif; ?>
                        <?php if ($supplier['phone']): ?>
                        <div style="font-size: 13px;"><?= htmlspecialchars($supplier['phone']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($supplier['nif'] ?? '-') ?></td>
                    <td>
                        <button class="badge badge-primary" style="cursor:pointer;border:none;" onclick="showProducts(<?= $supplier['id'] ?>, '<?= htmlspecialchars(addslashes($supplier['name']), ENT_QUOTES) ?>')" title="Ver produtos">
                            <?= $supplier['product_count'] ?> produto<?= $supplier['product_count'] != 1 ? 's' : '' ?>
                        </button>
                    </td>
                    <td>
                        <a href="/modules/encomendas.php?supplier_filter=<?= $supplier['id'] ?>" class="badge badge-muted" style="text-decoration:none;" title="Ver encomendas">
                            <?= $supplier['order_count'] ?> encomenda<?= $supplier['order_count'] != 1 ? 's' : '' ?>
                        </a>
                    </td>
                    <td>
                        <?php if ($supplier['active']): ?>
                        <span class="badge badge-success">Ativo</span>
                        <?php else: ?>
                        <span class="badge badge-danger">Inativo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="suppliers-row-actions">
                            <a href="?edit=<?= $supplier['id'] ?>" class="btn btn-secondary btn-sm">Editar</a>
                            <?php if ($supplier['active']): ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Desativar este fornecedor?')">
                                <input type="hidden" name="action" value="deactivate">
                                <input type="hidden" name="supplier_id" value="<?= (int)$supplier['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Desativar</button>
                            </form>
                            <?php else: ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Reativar este fornecedor?')">
                                <input type="hidden" name="action" value="reactivate">
                                <input type="hidden" name="supplier_id" value="<?= (int)$supplier['id'] ?>">
                                <button type="submit" class="btn btn-primary btn-sm">Reativar</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal de Produtos do Fornecedor -->
<div id="productsModal" class="products-modal">
    <div class="products-modal-card">
        <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
            <h3 id="modalTitle" style="margin:0;color:var(--text-primary);font-size:16px;">Produtos</h3>
            <button onclick="closeModal()" style="background:none;border:none;color:var(--text-muted);font-size:22px;cursor:pointer;line-height:1;">&#10005;</button>
        </div>
        <div id="modalBody" style="padding:20px 24px;overflow-y:auto;flex:1;"></div>
        <div style="padding:16px 24px;border-top:1px solid var(--border);display:flex;gap:10px;">
            <a id="modalOrderBtn" href="#" class="btn btn-primary btn-sm">&#128230; Nova Encomenda</a>
            <a id="modalProductsBtn" href="/modules/produtos.php" class="btn btn-secondary btn-sm">&#9881;&#65039; Gerir Produtos</a>
            <button onclick="closeModal()" class="btn btn-secondary btn-sm" style="margin-left:auto;">Fechar</button>
        </div>
    </div>
</div>

<script>
const storeId = <?= $current_store_id ?>;

async function showProducts(supplierId, supplierName) {
    document.getElementById('modalTitle').textContent = '📦 Produtos de ' + supplierName;
    document.getElementById('modalOrderBtn').href = '/modules/encomendas.php';
    document.getElementById('modalBody').innerHTML = '<p style="color:var(--text-muted);font-size:13px">A carregar...</p>';
    document.getElementById('productsModal').style.display = 'flex';

    try {
        const res = await fetch(`/api/supplier-products.php?supplier_id=${supplierId}&store_id=${storeId}`);
        const data = await res.json();
        const body = document.getElementById('modalBody');
        if (!data.success || data.products.length === 0) {
            body.innerHTML = `<div style="text-align:center;padding:20px;color:var(--text-muted);">
                <div style="font-size:36px;margin-bottom:8px;">📦</div>
                <div>Nenhum produto associado a este fornecedor.</div>
                <div style="font-size:12px;margin-top:8px;">Vá a <strong>Produtos</strong> e defina o campo "Fornecedor" para associar.</div>
            </div>`;
        } else {
            let html = `<table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead><tr style="border-bottom:1px solid var(--border);">
                    <th style="padding:8px;text-align:left;color:var(--text-muted);font-weight:500;">Produto</th>
                    <th style="padding:8px;text-align:center;color:var(--text-muted);font-weight:500;">Stock</th>
                    <th style="padding:8px;text-align:right;color:var(--text-muted);font-weight:500;">Preço Custo</th>
                    <th style="padding:8px;text-align:left;color:var(--text-muted);font-weight:500;">Categoria</th>
                </tr></thead><tbody>`;
            data.products.forEach(p => {
                const stockColor = p.stock <= p.min_stock ? 'color:var(--danger)' : 'color:var(--success)';
                html += `<tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:8px;">
                        <strong style="color:var(--text-primary)">${p.name}</strong>
                        ${p.barcode ? `<span style="font-size:11px;color:var(--text-muted);margin-left:6px;">[${p.barcode}]</span>` : ''}
                    </td>
                    <td style="padding:8px;text-align:center;${stockColor};font-weight:600;">${p.stock}</td>
                    <td style="padding:8px;text-align:right;color:var(--text-primary);">€${parseFloat(p.cost_price).toFixed(2)}</td>
                    <td style="padding:8px;color:var(--text-muted)">${p.category||'—'}</td>
                </tr>`;
            });
            html += '</tbody></table>';
            body.innerHTML = html;
        }
    } catch(e) {
        document.getElementById('modalBody').innerHTML = '<p style="color:var(--danger)">Erro ao carregar produtos.</p>';
    }
}

function closeModal() {
    document.getElementById('productsModal').style.display = 'none';
}

document.getElementById('productsModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>