<?php
/**
 * PAINEL DE CONFIGURAÇÕES
 */
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = db_connect();
$page_title = 'Configurações';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'company_name','company_nif','company_address','company_phone','company_email',
        'vat_rate','currency','low_stock_threshold','work_hours_per_day','overtime_rate',
        'receipt_footer','enable_loyalty','backup_retention_days','receipt_header','tax_id'
    ];
    $stmt = $pdo->prepare('INSERT INTO settings (`key`,value,description) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value), updated_at=NOW()');
    foreach ($fields as $k) {
        if (isset($_POST[$k])) {
            $stmt->execute([$k, trim($_POST[$k]), '']);
        }
    }
    $success = 'Configurações guardadas com sucesso!';
}

// Carregar todas as configurações
$rows = $pdo->query('SELECT `key`, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
function cfg(string $k, string $default = ''): string {
    global $rows;
    return htmlspecialchars($rows[$k] ?? $default);
}

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.settings-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
.settings-section { background:var(--bg-secondary); border:1px solid var(--border); border-radius:12px; padding:24px; }
.settings-section h3 { font-size:14px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted); margin-bottom:16px; padding-bottom:10px; border-bottom:1px solid var(--border); }
.settings-section .form-group { margin-bottom:14px; }
@media(max-width:768px){ .settings-grid{grid-template-columns:1fr} }
</style>

<?php if ($success): ?><div class="alert alert-success" style="margin-bottom:20px">✓ <?= $success ?></div><?php endif; ?>

<form method="post">
<div class="settings-grid">

    <!-- Empresa -->
    <div class="settings-section">
        <h3>🏪 Empresa</h3>
        <div class="form-group"><label class="form-label">Nome da Empresa</label><input type="text" name="company_name" class="form-input" value="<?= cfg('company_name','PAP Market') ?>"></div>
        <div class="form-group"><label class="form-label">NIF</label><input type="text" name="company_nif" class="form-input" value="<?= cfg('company_nif') ?>" placeholder="Ex: PT123456789"></div>
        <div class="form-group"><label class="form-label">Morada</label><input type="text" name="company_address" class="form-input" value="<?= cfg('company_address') ?>" placeholder="Rua, cidade, CP"></div>
        <div class="form-group"><label class="form-label">Telefone</label><input type="text" name="company_phone" class="form-input" value="<?= cfg('company_phone') ?>" placeholder="Ex: +351 200 000 000"></div>
        <div class="form-group"><label class="form-label">Email</label><input type="email" name="company_email" class="form-input" value="<?= cfg('company_email') ?>"></div>
    </div>

    <!-- Financeiro -->
    <div class="settings-section">
        <h3>💶 Financeiro</h3>
        <div class="form-group"><label class="form-label">Taxa IVA Padrão (%)</label><input type="number" name="vat_rate" class="form-input" value="<?= cfg('vat_rate','23') ?>" step="0.01" min="0" max="100"></div>
        <div class="form-group"><label class="form-label">Moeda</label><select name="currency" class="form-select"><option value="EUR" <?= cfg('currency','EUR')==='EUR'?'selected':'' ?>>EUR (€)</option><option value="USD" <?= cfg('currency')==='USD'?'selected':'' ?>>USD ($)</option><option value="GBP" <?= cfg('currency')==='GBP'?'selected':'' ?>>GBP (£)</option></select></div>
        <div class="form-group"><label class="form-label">Rodapé do Recibo</label><textarea name="receipt_footer" class="form-input" rows="2" style="resize:vertical"><?= cfg('receipt_footer','Obrigado pela sua visita!') ?></textarea></div>
        <div class="form-group"><label class="form-label">Cabeçalho do Recibo</label><input type="text" name="receipt_header" class="form-input" value="<?= cfg('receipt_header') ?>" placeholder="Ex: Loja Principal"></div>
        <div class="form-group"><label class="form-label">NIF Fiscal</label><input type="text" name="tax_id" class="form-input" value="<?= cfg('tax_id') ?>" placeholder="PT123456789"></div>
    </div>

    <!-- Recursos Humanos -->
    <div class="settings-section">
        <h3>👥 Recursos Humanos</h3>
        <div class="form-group"><label class="form-label">Horas de Trabalho por Dia</label><input type="number" name="work_hours_per_day" class="form-input" value="<?= cfg('work_hours_per_day','8') ?>" step="0.5" min="1" max="24"></div>
        <div class="form-group"><label class="form-label">Multiplicador Horas Extra</label><input type="number" name="overtime_rate" class="form-input" value="<?= cfg('overtime_rate','1.5') ?>" step="0.05" min="1" max="5"></div>
    </div>

    <!-- Sistema -->
    <div class="settings-section">
        <h3>⚙️ Sistema</h3>
        <div class="form-group"><label class="form-label">Limite Stock Mínimo (aviso)</label><input type="number" name="low_stock_threshold" class="form-input" value="<?= cfg('low_stock_threshold','10') ?>" min="0"></div>
        <div class="form-group"><label class="form-label">Retenção de Backups (dias)</label><input type="number" name="backup_retention_days" class="form-input" value="<?= cfg('backup_retention_days','30') ?>" min="1"></div>
        <div class="form-group">
            <label class="form-label">Programa de Fidelidade</label>
            <select name="enable_loyalty" class="form-select">
                <option value="1" <?= cfg('enable_loyalty','1')==='1'?'selected':'' ?>>Ativado</option>
                <option value="0" <?= cfg('enable_loyalty','1')==='0'?'selected':'' ?>>Desativado</option>
            </select>
        </div>
    </div>

</div>
<div style="margin-top:24px;text-align:right">
    <button type="submit" class="btn btn-primary" style="padding:12px 32px;font-size:15px">💾 Guardar Configurações</button>
</div>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
