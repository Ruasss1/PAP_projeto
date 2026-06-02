<?php
/**
 * PROMOÇÕES - PREMIUM
 * Gestão moderna de promoções e descontos
 */

header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/qrcode.php';

$pdo = db_connect();
$current_store_id = get_current_store_id();
$message = '';
$error = '';
$qr_code_modal = null;  // Para armazenar dados do QR code a mostrar

// Carregar produtos
$products = $pdo->prepare("SELECT id, name, sell_price FROM products WHERE store_id = ? AND active = 1 ORDER BY name");
$products->execute([$current_store_id]);
$products = $products->fetchAll();

// Criar/Editar promoção
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? 'percentage';
    $value = floatval($_POST['value'] ?? 0);
    $product_id = $_POST['product_id'] ?: null;
    $category = trim($_POST['category'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '') ?: null;
    $end_date = trim($_POST['end_date'] ?? '') ?: null;
    $active = isset($_POST['active']) ? 1 : 0;
    
    if (empty($name)) {
        $error = "Nome da promoção é obrigatório.";
    } elseif ($value <= 0) {
        $error = "Valor do desconto deve ser maior que 0.";
    } else {
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE promotions SET name=?, type=?, value=?, discount_type=?, discount_value=?, product_id=?, category=?, start_date=?, end_date=?, active=?, is_active=? WHERE id=?");
                $stmt->execute([$name, $type, $value, $type, $value, $product_id, $category, $start_date, $end_date, $active, $active, $id]);
                $message = "Promoção atualizada!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO promotions (name, type, value, discount_type, discount_value, product_id, category, start_date, end_date, active, is_active, store_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $type, $value, $type, $value, $product_id, $category, $start_date, $end_date, $active, $active, $current_store_id]);
                $new_id = $pdo->lastInsertId();
                
                // Gerar QR code automaticamente
                auto_generate_qrcode($pdo, $new_id);
                
                // Carregar dados do QR code para mostrar no modal
                $stmt_qr = $pdo->prepare("SELECT id, name, type, value, qr_code FROM promotions WHERE id = ?");
                $stmt_qr->execute([$new_id]);
                $qr_code_modal = $stmt_qr->fetch(PDO::FETCH_ASSOC);
                
                $message = "Promoção criada com sucesso!";
            }
        } catch (PDOException $e) {
            $error = "Erro: " . $e->getMessage();
        }
    }
}

// Eliminar
if (isset($_GET['delete'])) {
    try {
        $pdo->prepare("DELETE FROM promotions WHERE id = ? AND store_id = ?")->execute([intval($_GET['delete']), $current_store_id]);
        $message = "Promoção eliminada.";
    } catch (PDOException $e) {
        $error = "Erro ao eliminar.";
    }
}

// Toggle ativo
if (isset($_GET['toggle'])) {
    $pdo->prepare("UPDATE promotions SET active = NOT active, is_active = NOT is_active WHERE id = ? AND store_id = ?")->execute([intval($_GET['toggle']), $current_store_id]);
    header('Location: /modules/promocoes.php');
    exit;
}

// Regenerar QR code
if (isset($_GET['regenerate_qr'])) {
    $promo_id = intval($_GET['regenerate_qr']);
    if (regenerate_qrcode($pdo, $promo_id)) {
        $message = "QR code regenerado com sucesso!";
    } else {
        $error = "Erro ao regenerar QR code. Verifique a conexão à internet.";
    }
}

// Edição
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM promotions WHERE id = ? AND store_id = ?");
    $stmt->execute([intval($_GET['edit']), $current_store_id]);
    $editing = $stmt->fetch();
}

// Listar promoções
$promotions = [];
try {
    $stmt = $pdo->prepare("SELECT p.*, pr.name as product_name 
        FROM promotions p 
        LEFT JOIN products pr ON p.product_id = pr.id 
        WHERE p.store_id = ? 
        ORDER BY p.active DESC, p.end_date ASC");
    $stmt->execute([$current_store_id]);
    $promotions = $stmt->fetchAll();
} catch (PDOException $e) {
    // Tabela pode não existir
}

// Estatísticas
$stats_active = count(array_filter($promotions, fn($p) => $p['active']));
$stats_expired = count(array_filter($promotions, fn($p) => $p['end_date'] && strtotime($p['end_date']) < time()));

// Categorias únicas
$categories = $pdo->prepare("SELECT DISTINCT category FROM products WHERE store_id = ? AND category IS NOT NULL AND category != '' ORDER BY category");
$categories->execute([$current_store_id]);
$categories = $categories->fetchAll(PDO::FETCH_COLUMN);

$page_title = 'Promoções';
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
            <div class="stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg></div>
        </div>
        <div class="stat-value"><?= count($promotions) ?></div>
        <div class="stat-label">Total Promoções</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
        </div>
        <div class="stat-value"><?= $stats_active ?></div>
        <div class="stat-label">Ativas</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
        </div>
        <div class="stat-value"><?= $stats_expired ?></div>
        <div class="stat-label">Expiradas</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></div>
        </div>
        <div class="stat-value"><?= count($categories) ?></div>
        <div class="stat-label">Categorias</div>
    </div>
</div>

<!-- Formulário -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3 class="card-title"><?= $editing ? '✏️ Editar Promoção' : '➕ Nova Promoção' ?></h3>
        <?php if ($editing): ?>
        <a href="/modules/promocoes.php" class="btn btn-secondary btn-sm">✕ Cancelar</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form method="post">
            <?php if ($editing): ?>
            <input type="hidden" name="id" value="<?= $editing['id'] ?>">
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Nome da Promoção *</label>
                    <input type="text" name="name" class="form-input" required value="<?= htmlspecialchars($editing['name'] ?? '') ?>" placeholder="Ex: Black Friday -20%">
                </div>
                <div class="form-group">
                    <label class="form-label">Tipo de Desconto</label>
                    <select name="type" class="form-select" id="discountType">
                        <option value="percentage" <?= ($editing['type'] ?? '') === 'percentage' ? 'selected' : '' ?>>% Percentagem</option>
                        <option value="fixed" <?= ($editing['type'] ?? '') === 'fixed' ? 'selected' : '' ?>>€ Valor Fixo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Valor do Desconto *</label>
                    <div style="position: relative;">
                        <input type="number" name="value" class="form-input" step="0.01" min="0" required value="<?= $editing['value'] ?? '' ?>" placeholder="0" style="padding-left: 32px;">
                        <span id="discountSymbol" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted);">%</span>
                    </div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Aplicar a Produto (opcional)</label>
                    <select name="product_id" class="form-select">
                        <option value="">Todos os produtos</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($editing['product_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?> (€<?= number_format($p['sell_price'], 2) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Ou Categoria (opcional)</label>
                    <select name="category" class="form-select">
                        <option value="">Nenhuma categoria específica</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= ($editing['category'] ?? '') === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Data Início</label>
                    <input type="date" name="start_date" class="form-input" value="<?= $editing['start_date'] ?? date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Data Fim</label>
                    <input type="date" name="end_date" class="form-input" value="<?= $editing['end_date'] ?? '' ?>">
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <label class="form-checkbox">
                        <input type="checkbox" name="active" <?= ($editing['active'] ?? 1) ? 'checked' : '' ?>>
                        <span>Promoção Ativa</span>
                    </label>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" style="margin-top: 16px;">
                <?= $editing ? '💾 Guardar Alterações' : '🏷️ Criar Promoção' ?>
            </button>
        </form>
    </div>
</div>

<!-- Lista -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Promoções (<?= count($promotions) ?>)</h3>
    </div>
    <div class="table-container" style="border: none;">
        <table class="table">
            <thead>
                <tr>
                    <th>Promoção</th>
                    <th>Desconto</th>
                    <th>Aplica-se a</th>
                    <th>Período</th>
                    <th>Estado</th>
                    <th style="width: 120px;">QR Code</th>
                    <th style="width: 180px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($promotions)): ?>
                <tr>
                    <td colspan="7" class="table-empty">
                        <div class="empty-state">
                            <div class="empty-icon">🏷️</div>
                            <div class="empty-title">Sem promoções</div>
                            <div class="empty-text">Crie a primeira promoção acima.</div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($promotions as $promo): ?>
                <?php
                $is_expired = $promo['end_date'] && strtotime($promo['end_date']) < time();
                $is_future = $promo['start_date'] && strtotime($promo['start_date']) > time();
                ?>
                <tr style="<?= !$promo['active'] || $is_expired ? 'opacity: 0.6;' : '' ?>">
                    <td>
                        <div style="font-weight: 600;"><?= htmlspecialchars($promo['name']) ?></div>
                    </td>
                    <td>
                        <span class="badge badge-primary" style="font-size: 16px;">
                            <?php if ($promo['type'] === 'percentage'): ?>
                            -<?= $promo['value'] ?>%
                            <?php else: ?>
                            -€<?= number_format($promo['value'], 2) ?>
                            <?php endif; ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($promo['product_name']): ?>
                        <span class="badge badge-muted">📦 <?= htmlspecialchars($promo['product_name']) ?></span>
                        <?php elseif ($promo['category']): ?>
                        <span class="badge badge-muted">📂 <?= htmlspecialchars($promo['category']) ?></span>
                        <?php else: ?>
                        <span class="badge badge-success">🛒 Todos os produtos</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($promo['start_date'] || $promo['end_date']): ?>
                        <div style="font-size: 13px;">
                            <?= $promo['start_date'] ? date('d/m/Y', strtotime($promo['start_date'])) : '...' ?>
                            →
                            <?= $promo['end_date'] ? date('d/m/Y', strtotime($promo['end_date'])) : '...' ?>
                        </div>
                        <?php else: ?>
                        <span class="badge badge-muted">Sem limite</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($is_expired): ?>
                        <span class="badge badge-danger">❌ Expirada</span>
                        <?php elseif ($is_future): ?>
                        <span class="badge badge-warning">📅 Futura</span>
                        <?php elseif ($promo['active']): ?>
                        <span class="badge badge-success">✅ Ativa</span>
                        <?php else: ?>
                        <span class="badge badge-muted">⏸️ Inativa</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                        <?php if ($promo['qr_code']): ?>
                        <img src="<?= $promo['qr_code'] ?>" alt="QR Code" style="width:64px;height:64px;cursor:pointer;border-radius:6px;border:2px solid var(--border);transition:transform .15s;" 
                             onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'"
                             onclick="showQrFullscreen('<?= $promo['id'] ?>','<?= htmlspecialchars(addslashes($promo['name'])) ?>','<?= $promo['type'] ?>','<?= $promo['value'] ?>','<?= $promo['qr_code'] ?>')"
                             title="Clique para ampliar">
                        <?php else: ?>
                        <span class="badge badge-muted" style="cursor:pointer;" onclick="location.href='?regenerate_qr=<?= $promo['id'] ?>'">🔄 Gerar</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                            <?php if ($promo['qr_code']): ?>
                            <a href="?regenerate_qr=<?= $promo['id'] ?>" class="btn btn-secondary btn-sm" title="Regenerar QR Code">🔄</a>
                            <?php endif; ?>
                            <a href="?toggle=<?= $promo['id'] ?>" class="btn btn-secondary btn-sm" title="<?= $promo['active'] ? 'Desativar' : 'Ativar' ?>">
                                <?= $promo['active'] ? '⏸️' : '▶️' ?>
                            </a>
                            <a href="?edit=<?= $promo['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
                            <a href="?delete=<?= $promo['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Eliminar esta promoção?')">🗑️</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.badge-warning { background: #f59e0b22; color: #f59e0b; }

/* QR CODE MODAL - FULLSCREEN */
.modal-qr-overlay {
    display: none;
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
    background: var(--bg-primary, #0f1117);
    z-index: 9999;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0;
    animation: fadeIn 0.25s ease;
}

.modal-qr-overlay.active {
    display: flex;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to   { opacity: 1; }
}

/* Barra do topo */
.modal-qr-topbar {
    position: absolute;
    top: 0; left: 0; right: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 28px;
    background: var(--bg-secondary, #1a1d27);
    border-bottom: 1px solid var(--border, rgba(255,255,255,0.08));
}

.modal-qr-topbar h2 {
    margin: 0;
    font-size: 20px;
    color: var(--text-primary, #e8eaed);
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-qr-topbar .badge-discount {
    background: var(--success, #00b894);
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 20px;
}

/* QR code centralizado a ocupar o máximo de espaço */
.modal-qr-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 16px 20px;
    box-sizing: border-box;
    overflow: hidden;
}

.modal-qr-center {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
}

.modal-qr-frame {
    background: #fff;
    border-radius: 18px;
    padding: 24px;
    box-shadow: 0 8px 48px rgba(0,0,0,0.5);
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.modal-qr-frame img {
    width: min(52vw, 52vh, 380px);
    height: min(52vw, 52vh, 380px);
    display: block;
    image-rendering: pixelated;
}

/* Nome da promoção por baixo do QR */
.modal-qr-label {
    text-align: center;
    padding: 14px 24px 0;
}

.modal-qr-label .promo-name {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-primary, #e8eaed);
    margin: 0 0 4px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 90vw;
}

.modal-qr-label .promo-hint {
    font-size: 13px;
    color: var(--text-muted, #888);
    margin: 0;
}

/* Barra de ações no fundo */
.modal-qr-actions {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    display: flex;
    gap: 12px;
    padding: 14px 24px;
    background: var(--bg-secondary, #1a1d27);
    border-top: 1px solid var(--border, rgba(255,255,255,0.08));
    justify-content: center;
}

.modal-qr-actions button {
    padding: 12px 32px;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
    font-size: 15px;
    transition: opacity 0.2s, transform 0.15s;
    min-width: 150px;
}

.modal-qr-actions button:hover {
    opacity: 0.85;
    transform: translateY(-1px);
}

.btn-qr-close    { background: var(--bg-tertiary, #252836); color: var(--text-primary, #e8eaed); }
.btn-qr-download { background: var(--success, #00b894);     color: #fff; }
.btn-qr-print    { background: var(--primary, #6c63ff);     color: #fff; }
</style>

<!-- QR CODE MODAL - FULLSCREEN -->
<?php if ($qr_code_modal): ?>
<div class="modal-qr-overlay active" id="qrModal">

    <!-- Topo -->
    <div class="modal-qr-topbar">
        <h2>
            ✅ Promoção Criada!
        </h2>
        <span class="badge-discount">
            <?php if ($qr_code_modal['type'] === 'percentage'): ?>
            -<?= $qr_code_modal['value'] ?>%
            <?php else: ?>
            -€<?= number_format($qr_code_modal['value'], 2) ?>
            <?php endif; ?>
        </span>
    </div>

    <!-- QR + Nome -->
    <div class="modal-qr-body">
        <div class="modal-qr-center">
            <div class="modal-qr-frame">
                <?php if ($qr_code_modal['qr_code']): ?>
                <img src="<?= $qr_code_modal['qr_code'] ?>" alt="QR Code" id="qrCodeImg">
                <?php endif; ?>
            </div>
        </div>
        <div class="modal-qr-label">
            <p class="promo-name"><?= htmlspecialchars($qr_code_modal['name']) ?></p>
            <p class="promo-hint">📱 Escaneie para aplicar a promoção</p>
        </div>
    </div>

    <!-- Ações no fundo -->
    <div class="modal-qr-actions">
        <button class="btn-qr-close"    onclick="closeQrModal()">✕ Fechar</button>
        <button class="btn-qr-download" onclick="downloadQr()">⬇ Descarregar</button>
        <button class="btn-qr-print"    onclick="printQr()">🖨 Imprimir</button>
    </div>
</div>

<script>
function closeQrModal() {
    document.getElementById('qrModal').classList.remove('active');
}

function downloadQr() {
    const img = document.getElementById('qrCodeImg');
    const link = document.createElement('a');
    link.href = img.src;
    link.download = 'qr-code-<?= $qr_code_modal['id'] ?>.png';
    link.click();
}

function printQr() {
    const img = document.getElementById('qrCodeImg');
    const printWindow = window.open('', '', 'width=600,height=700');
    printWindow.document.write('<html><head><title>QR Code - <?= htmlspecialchars(addslashes($qr_code_modal['name'])) ?></title>');
    printWindow.document.write('<style>');
    printWindow.document.write('* { margin:0; padding:0; box-sizing:border-box; }');
    printWindow.document.write('body { display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:100vh; background:#fff; font-family:Arial,sans-serif; padding:40px; }');
    printWindow.document.write('img { width:400px; height:400px; display:block; image-rendering:pixelated; }');
    printWindow.document.write('h1 { font-size:24px; margin:24px 0 6px; text-align:center; }');
    printWindow.document.write('p  { font-size:14px; color:#555; text-align:center; margin:4px 0; }');
    printWindow.document.write('.badge { display:inline-block; background:#00b894; color:#fff; font-size:20px; font-weight:700; padding:6px 18px; border-radius:20px; margin-top:12px; }');
    printWindow.document.write('</style></head><body>');
    printWindow.document.write('<img src="' + img.src + '" alt="QR Code">');
    printWindow.document.write('<h1><?= htmlspecialchars($qr_code_modal['name']) ?></h1>');
    printWindow.document.write('<p>Escaneie para aproveitar a promoção</p>');
    printWindow.document.write('<span class="badge">');
    <?php if ($qr_code_modal['type'] === 'percentage'): ?>
    printWindow.document.write('-<?= $qr_code_modal['value'] ?>%');
    <?php else: ?>
    printWindow.document.write('-€<?= number_format($qr_code_modal['value'], 2) ?>');
    <?php endif; ?>
    printWindow.document.write('</span>');
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
}
</script>
<?php endif; ?>

<script>
document.getElementById('discountType').addEventListener('change', function() {
    document.getElementById('discountSymbol').textContent = this.value === 'percentage' ? '%' : '€';
});

// ─── Modal fullscreen reutilizável para QR codes da lista ───────────────
function showQrFullscreen(id, name, type, value, src) {
    const modal = document.getElementById('qrModalList');
    document.getElementById('qrListImg').src      = src;
    document.getElementById('qrListImg').dataset.id = id;
    document.getElementById('qrListName').textContent = name;
    document.getElementById('qrListHint').textContent  = '📱 Escaneie para aplicar a promoção';
    
    const disc = type === 'percentage' ? `-${value}%` : `-€${parseFloat(value).toFixed(2)}`;
    document.getElementById('qrListBadge').textContent = disc;
    
    modal.classList.add('active');
}

function closeQrListModal() {
    document.getElementById('qrModalList').classList.remove('active');
}

function downloadQrList() {
    const img  = document.getElementById('qrListImg');
    const link = document.createElement('a');
    link.href     = img.src;
    link.download = `qr-code-${img.dataset.id}.png`;
    link.click();
}

function printQrList() {
    const img  = document.getElementById('qrListImg');
    const name = document.getElementById('qrListName').textContent;
    const disc = document.getElementById('qrListBadge').textContent;
    const w    = window.open('', '', 'width=600,height=700');
    w.document.write('<html><head><title>QR Code</title>');
    w.document.write('<style>*{margin:0;padding:0;box-sizing:border-box}body{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;background:#fff;font-family:Arial;padding:40px}img{width:400px;height:400px;image-rendering:pixelated}h1{font-size:24px;margin:24px 0 6px;text-align:center}p{font-size:14px;color:#555;text-align:center;margin:4px 0}.badge{display:inline-block;background:#00b894;color:#fff;font-size:20px;font-weight:700;padding:6px 18px;border-radius:20px;margin-top:12px}</style></head><body>');
    w.document.write('<img src="'+img.src+'" alt="QR">');
    w.document.write('<h1>'+name+'</h1>');
    w.document.write('<p>Escaneie para aproveitar a promoção</p>');
    w.document.write('<span class="badge">'+disc+'</span>');
    w.document.write('</body></html>');
    w.document.close();
    w.focus();
    w.print();
}
</script>

<!-- Modal fullscreen reutilizável para a lista de promoções -->
<div class="modal-qr-overlay" id="qrModalList">
    <div class="modal-qr-topbar">
        <h2>📱 QR Code da Promoção</h2>
        <span class="badge-discount" id="qrListBadge"></span>
    </div>
    <div class="modal-qr-body">
        <div class="modal-qr-center">
            <div class="modal-qr-frame">
                <img id="qrListImg" src="" alt="QR Code">
            </div>
        </div>
        <div class="modal-qr-label">
            <p class="promo-name" id="qrListName"></p>
            <p class="promo-hint" id="qrListHint">📱 Escaneie para aplicar a promoção</p>
        </div>
    </div>
    <div class="modal-qr-actions">
        <button class="btn-qr-close"    onclick="closeQrListModal()">✕ Fechar</button>
        <button class="btn-qr-download" onclick="downloadQrList()">⬇ Descarregar</button>
        <button class="btn-qr-print"    onclick="printQrList()">🖨 Imprimir</button>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>