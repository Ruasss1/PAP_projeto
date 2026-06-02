<?php
/**
 * BACKUP DA BASE DE DADOS
 */
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Backup da Base de Dados';
$backup_dir = __DIR__ . '/../backups/';
if (!is_dir($backup_dir)) { mkdir($backup_dir, 0755, true); }
$success = ''; $error = '';

// ── Download de ficheiro existente ──────────────────────────────────────────
if (isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $full = $backup_dir . $file;
    if (file_exists($full) && pathinfo($full, PATHINFO_EXTENSION) === 'sql') {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($full));
        readfile($full); exit;
    }
}

// ── Eliminar backup ─────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $file = basename($_GET['delete']);
    $full = $backup_dir . $file;
    if (file_exists($full) && pathinfo($full, PATHINFO_EXTENSION) === 'sql') {
        unlink($full);
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    }
}

// ── Criar backup ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'backup') {
    require_once __DIR__ . '/../config/database.php';

    $dbhost = DB_HOST;
    $dbname = DB_NAME;
    $dbuser = DB_USER;
    $dbpass = DB_PASS;

    $filename = 'backup_' . $dbname . '_' . date('Ymd_His') . '.sql';
    $filepath = $backup_dir . $filename;

    // Usar mysqldump
    $cmd = sprintf(
        'mysqldump --no-tablespaces -h %s -u %s -p%s %s > %s 2>&1',
        escapeshellarg($dbhost),
        escapeshellarg($dbuser),
        escapeshellarg($dbpass),
        escapeshellarg($dbname),
        escapeshellarg($filepath)
    );
    exec($cmd, $output, $return_code);

    if ($return_code === 0 && file_exists($filepath) && filesize($filepath) > 0) {
        $success = "Backup criado: $filename (" . round(filesize($filepath)/1024, 1) . " KB)";
        // Limpar backups antigos
        $pdo = db_connect();
        $retention = (int)($pdo->query('SELECT value FROM settings WHERE `key`="backup_retention_days" LIMIT 1')->fetchColumn() ?: 30);
        foreach (glob($backup_dir . '*.sql') as $f) {
            if (filemtime($f) < time() - ($retention * 86400)) { unlink($f); }
        }
    } else {
        $error = "Erro ao criar backup (código $return_code). Verifique se mysqldump está disponível.";
        if (file_exists($filepath)) { unlink($filepath); }
    }
}

// ── Listar backups ──────────────────────────────────────────────────────────
$backups = [];
foreach (glob($backup_dir . '*.sql') as $f) {
    $backups[] = ['name' => basename($f), 'size' => filesize($f), 'date' => filemtime($f)];
}
usort($backups, fn($a,$b) => $b['date'] - $a['date']);

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.backup-hero { background:var(--bg-secondary); border:1px solid var(--border); border-radius:12px; padding:32px; text-align:center; margin-bottom:24px; }
.backup-hero h2 { font-size:22px; font-weight:700; margin-bottom:8px; }
.backup-hero p  { color:var(--text-muted); margin-bottom:20px; }
</style>

<?php if ($error):   ?><div class="alert alert-danger"  style="margin-bottom:16px">⚠️ <?= htmlspecialchars($error)   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success" style="margin-bottom:16px">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="backup-hero">
    <h2>🗄️ Backup da Base de Dados</h2>
    <p>Crie um dump completo da base de dados para salvaguarda ou migração.</p>
    <form method="post" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='A processar...'">
        <input type="hidden" name="action" value="backup">
        <button type="submit" class="btn btn-primary" style="padding:12px 36px;font-size:15px">⬇ Criar Backup Agora</button>
    </form>
</div>

<!-- Lista de backups -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Backups Disponíveis (<?= count($backups) ?>)</h3>
    </div>
    <div class="table-container">
        <table class="table">
            <thead><tr><th>Ficheiro</th><th>Tamanho</th><th>Data</th><th>Ações</th></tr></thead>
            <tbody>
            <?php if (empty($backups)): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:40px">Nenhum backup disponível.</td></tr>
            <?php else: foreach ($backups as $b): ?>
            <tr>
                <td style="font-family:monospace;font-size:13px"><?= htmlspecialchars($b['name']) ?></td>
                <td><?= number_format($b['size']/1024/1024, 2) ?> MB</td>
                <td><?= date('d/m/Y H:i:s', $b['date']) ?></td>
                <td>
                    <a href="?download=<?= urlencode($b['name']) ?>" class="btn btn-secondary" style="font-size:12px;padding:5px 12px">⬇ Download</a>
                    <a href="?delete=<?= urlencode($b['name']) ?>" class="btn btn-secondary" style="font-size:12px;padding:5px 12px;color:var(--danger,#ef4444)" onclick="return confirm('Eliminar este backup?')">🗑 Eliminar</a>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div style="background:var(--bg-secondary);border:1px solid var(--border);border-radius:10px;padding:16px;margin-top:16px;font-size:13px;color:var(--text-muted)">
    <strong>ℹ️ Nota:</strong> Os backups são ficheiros SQL gerados pelo <code>mysqldump</code>. Certifique-se de que o binário está acessível no servidor. Os backups antigos são automaticamente eliminados conforme a política de retenção configurada em <a href="configuracoes.php" style="color:var(--text-primary)">Configurações</a>.
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
