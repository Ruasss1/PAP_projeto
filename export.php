<?php
session_start();
require_once __DIR__ . '/config/database.php';

// ─── AÇÃO: Gerar e descarregar ZIP ─────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    $project_root = __DIR__;
    $zip_name     = 'PAP_Supermercado_' . date('Ymd_His') . '.zip';

    // Pastas/ficheiros a EXCLUIR do ZIP
    $exclude = [
        'vendor',
        'App/node_modules',
        '.git',
        'write_turno.py',
        'backups',
        '.DS_Store',
    ];

    $zip = new ZipArchive();
    $tmp = sys_get_temp_dir() . '/' . $zip_name;
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        die('Erro ao criar arquivo ZIP.');
    }

    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($project_root, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iter as $file) {
        $real    = $file->getRealPath();
        $rel     = str_replace($project_root . DIRECTORY_SEPARATOR, '', $real);
        $relUnix = str_replace('\\', '/', $rel);

        // Verificar exclusões
        $skip = false;
        foreach ($exclude as $ex) {
            if (str_starts_with($relUnix, $ex) || $relUnix === $ex || basename($real) === '.DS_Store') {
                $skip = true; break;
            }
        }
        if ($skip) continue;

        if ($file->isDir()) {
            $zip->addEmptyDir($relUnix);
        } else {
            $zip->addFile($real, $relUnix);
        }
    }

    // Script de arranque automático para macOS/Linux
    $start_mac = <<<'BASH'
#!/bin/bash
cd "$(dirname "$0")"
echo "🛒 PAP Supermercado — A iniciar..."

# Verificar PHP
if ! command -v php &> /dev/null; then
    echo "❌ PHP não encontrado. Instala com: brew install php"
    read -p "Pressiona Enter para sair..."
    exit 1
fi

# Verificar se MySQL está activo
if ! mysqladmin ping -u pap_user -ppap_pass --silent 2>/dev/null; then
    echo "⚠️  MySQL não detectado. Certifica-te que o MySQL está a correr."
fi

# Iniciar servidor PHP em background
PHP_PID_FILE="/tmp/pap_php_server.pid"
if [ -f "$PHP_PID_FILE" ]; then
    kill $(cat "$PHP_PID_FILE") 2>/dev/null
fi
php -S localhost:8000 &> /tmp/pap_php.log &
echo $! > "$PHP_PID_FILE"

echo "✅ Servidor iniciado em http://localhost:8000"
sleep 1

# Abrir browser automaticamente
if command -v open &> /dev/null; then
    open http://localhost:8000
elif command -v xdg-open &> /dev/null; then
    xdg-open http://localhost:8000
fi

echo ""
echo "🟢 A aplicação está a correr em http://localhost:8000"
echo "   Para parar: pressiona Ctrl+C ou fecha esta janela"
echo ""
wait
BASH;

    // Script de arranque automático para Windows
    $start_win = <<<'BAT'
@echo off
cd /d "%~dp0"
echo =================================
echo  PAP Supermercado - A iniciar...
echo =================================

where php >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ERRO: PHP nao encontrado.
    echo Instala o PHP em https://windows.php.net/download/
    pause
    exit /b 1
)

echo Servidor PHP a iniciar...
start /b php -S localhost:8000
timeout /t 2 /nobreak >nul

echo Servidor iniciado em http://localhost:8000
start http://localhost:8000

echo.
echo A aplicacao esta a correr. Fecha esta janela para parar.
pause
BAT;

    // Adicionar ficheiro README de setup
    $readme = <<<'TXT'
# PAP Supermercado — Guia de Instalação

## Pré-requisitos
- PHP 8.1+ (https://www.php.net/downloads)
- MySQL 8+ ou MariaDB (https://dev.mysql.com/downloads/)
- Composer (https://getcomposer.org/)

## Passos

### 1. Criar a base de dados
```sql
CREATE DATABASE supermercado CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pap_user'@'localhost' IDENTIFIED BY 'pap_pass';
GRANT ALL PRIVILEGES ON supermercado.* TO 'pap_user'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Importar as migrações (por ordem)
```
mysql -u pap_user -p supermercado < migrations/001_supermercado_migration.sql
mysql -u pap_user -p supermercado < migrations/002_add_security_and_audit.sql
mysql -u pap_user -p supermercado < migrations/002_pdv_tables.sql
mysql -u pap_user -p supermercado < migrations/003_add_pricing_management.sql
mysql -u pap_user -p supermercado < migrations/004_low_stock_settings.sql
mysql -u pap_user -p supermercado < migrations/005_cash_operations.sql
mysql -u pap_user -p supermercado < migrations/005_pdv_system.sql
mysql -u pap_user -p supermercado < migrations/005_rh_management.sql
mysql -u pap_user -p supermercado < migrations/006_customers_loyalty.sql
mysql -u pap_user -p supermercado < migrations/007_notifications_alerts.sql
mysql -u pap_user -p supermercado < migrations/008_new_features.sql
```

### 3. Instalar dependências PHP
```
composer install
```

### 4. Iniciar o servidor
```
php -S localhost:8000
```

### 5. Abrir no browser
Vai a: http://localhost:8000

---
Utilizador padrão: admin / admin123
TXT;
    $zip->addFromString('SETUP.md', $readme);
    $zip->addFromString('INICIAR_MAC.command', $start_mac);
    $zip->addFromString('INICIAR_WINDOWS.bat', $start_win);
    $zip->close();

    $size = filesize($tmp);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zip_name . '"');
    header('Content-Length: ' . $size);
    header('Cache-Control: no-cache');
    readfile($tmp);
    unlink($tmp);
    exit;
}

// Calcular tamanho estimado do projeto
function dirSize(string $dir, array $exclude = []): int {
    $size = 0;
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            $rel = str_replace($dir . '/', '', str_replace('\\', '/', $f->getRealPath()));
            foreach ($exclude as $ex) { if (str_starts_with($rel, $ex)) continue 2; }
            if ($f->isFile()) $size += $f->getSize();
        }
    } catch(Exception $e) {}
    return $size;
}
$exclude_dirs = ['vendor','App/node_modules','.git','backups'];
$raw_size  = dirSize(__DIR__, $exclude_dirs);
$size_mb   = round($raw_size / 1024 / 1024, 1);

$page_title = 'Acesso Online';
require_once __DIR__ . '/includes/header.php';
?>

<style>
.step-num { width:32px;height:32px;border-radius:50%;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.3);color:var(--primary);font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px; }
.step-item { display:flex;gap:18px;margin-bottom:24px; }
.step-item:last-child { margin-bottom:0; }
.step-content h4 { font-size:14px;font-weight:600;margin-bottom:4px; }
.step-content p { color:var(--text-secondary);font-size:13px;line-height:1.6;margin:0; }
.ci { background:var(--bg-primary);padding:1px 6px;border-radius:4px;font-family:'Monaco','Consolas',monospace;font-size:11px;color:#4ade80;border:1px solid var(--border); }
.url-box { display:flex;align-items:center;gap:10px;background:var(--bg-primary);border:1.5px solid rgba(99,102,241,.4);border-radius:10px;padding:14px 18px;margin-top:16px; }
.url-text { flex:1;font-family:'Monaco','Consolas',monospace;font-size:14px;color:#a78bfa;word-break:break-all; }
.url-copy { background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.3);color:var(--primary);padding:7px 14px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;font-family:inherit;transition:background .2s; }
.url-copy:hover { background:rgba(99,102,241,.25); }
</style>

<?php
$railway_domain = $_SERVER['HTTP_HOST'] ?? '';
$is_railway = str_contains($railway_domain, 'railway.app') || str_contains($railway_domain, 'up.railway.app');
$public_url = 'https://' . $railway_domain;
?>

<!-- Hero: Link Online -->
<div class="card" style="margin-bottom:20px;border-color:rgba(99,102,241,.35);background:linear-gradient(135deg,rgba(99,102,241,.06) 0%,transparent 60%);">
    <div class="card-body" style="padding:32px;">
        <div style="display:flex;align-items:flex-start;gap:18px;">
            <div style="width:48px;height:48px;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.3);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            </div>
            <div style="flex:1">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap;">
                    <span style="font-size:18px;font-weight:700;">Acesso Online</span>
                    <span class="badge badge-success">Online agora</span>
                </div>
                <p style="color:var(--text-secondary);font-size:14px;margin:0 0 4px 0;">Partilha este link — qualquer pessoa abre no browser sem instalar nada.</p>
                <div class="url-box">
                    <span class="url-text" id="railway-url"><?= htmlspecialchars($public_url) ?></span>
                    <button class="url-copy" onclick="copyUrl()">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:5px"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        Copiar link
                    </button>
                    <a href="<?= htmlspecialchars($public_url) ?>" target="_blank" class="btn btn-primary" style="padding:7px 14px;font-size:12px;display:flex;align-items:center;gap:6px;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        Abrir
                    </a>
                </div>
                <p style="font-size:12px;color:var(--text-muted);margin-top:10px;">Login padrão: <strong>admin</strong> / <strong>admin123</strong></p>
            </div>
        </div>
    </div>
</div>

<!-- Credenciais -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:20px 24px;">
        <div style="font-size:13px;font-weight:600;margin-bottom:12px;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.05em;">Contas de acesso</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;">
            <div style="background:var(--bg-secondary);border:1px solid var(--border);border-radius:8px;padding:12px 14px;">
                <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">Administrador</div>
                <div style="font-size:13px;font-weight:600;">admin</div>
                <div style="font-size:12px;color:var(--text-secondary);">admin123</div>
            </div>
            <div style="background:var(--bg-secondary);border:1px solid var(--border);border-radius:8px;padding:12px 14px;">
                <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">Caixa (PDV)</div>
                <div style="font-size:12px;color:var(--text-secondary);"><?= htmlspecialchars($public_url) ?>/CAIXA/</div>
            </div>
        </div>
    </div>
</div>

<!-- Download ZIP (discreto) -->
<div style="border-top:1px solid var(--border);padding-top:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
        <div style="font-size:13px;font-weight:600;margin-bottom:2px;">Descarregar código fonte</div>
        <div style="font-size:12px;color:var(--text-muted);">Para entrega do projeto ou uso local · ~<?= $size_mb ?> MB</div>
    </div>
    <a href="export.php?action=download" class="btn btn-secondary btn-sm" style="display:flex;align-items:center;gap:7px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Descarregar ZIP
    </a>
</div>

<script>
function copyUrl() {
    const url = document.getElementById('railway-url').textContent.trim();
    navigator.clipboard.writeText(url).then(() => {
        const btn = document.querySelector('.url-copy');
        btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:5px"><polyline points="20 6 9 17 4 12"/></svg>Copiado!';
        btn.style.background = 'rgba(74,222,128,.15)';
        btn.style.borderColor = 'rgba(74,222,128,.3)';
        btn.style.color = '#4ade80';
        setTimeout(() => {
            btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:5px"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>Copiar link';
            btn.style.background = '';
            btn.style.borderColor = '';
            btn.style.color = '';
        }, 2500);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
