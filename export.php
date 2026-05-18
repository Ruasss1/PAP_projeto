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
.step-code { background:var(--bg-primary);border:1px solid var(--border);border-radius:8px;padding:10px 14px;margin-top:8px;font-family:'Monaco','Consolas',monospace;font-size:12px;color:#4ade80;overflow-x:auto;white-space:pre; }
.ci { background:var(--bg-primary);padding:1px 6px;border-radius:4px;font-family:'Monaco','Consolas',monospace;font-size:11px;color:#4ade80;border:1px solid var(--border); }
.copy-btn { background:var(--bg-secondary);border:1px solid var(--border);color:var(--text-muted);padding:3px 8px;border-radius:4px;font-size:11px;cursor:pointer;float:right;margin-bottom:4px;font-family:inherit;transition:background .2s; }
.copy-btn:hover { background:var(--bg-hover); }
.copy-wrap { position:relative; }
.url-box { display:flex;align-items:center;gap:10px;background:var(--bg-primary);border:1.5px solid rgba(99,102,241,.4);border-radius:10px;padding:14px 18px;margin-top:16px;flex-wrap:wrap; }
.url-text { flex:1;font-family:'Monaco','Consolas',monospace;font-size:14px;color:#a78bfa;word-break:break-all;min-width:0; }
.url-copy { background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.3);color:var(--primary);padding:7px 14px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;font-family:inherit;transition:background .2s; }
.url-copy:hover { background:rgba(99,102,241,.25); }
.note-alert { display:flex;gap:10px;padding:12px 16px;border-radius:8px;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);margin-top:10px; }
.note-alert p { color:#fbbf24;font-size:12px;margin:0;line-height:1.5; }
</style>

<?php $railway_url = 'https://papprojeto-production.up.railway.app'; ?>

<!-- Hero: Link Online -->
<div class="card" style="margin-bottom:20px;border-color:rgba(99,102,241,.35);background:linear-gradient(135deg,rgba(99,102,241,.06) 0%,transparent 60%);">
    <div class="card-body" style="padding:28px;">
        <div style="display:flex;align-items:flex-start;gap:18px;">
            <div style="width:48px;height:48px;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.3);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap;">
                    <span style="font-size:18px;font-weight:700;">Acesso Online — Railway</span>
                    <span class="badge badge-success">Online</span>
                </div>
                <p style="color:var(--text-secondary);font-size:13px;margin:0 0 4px 0;">Partilha este link — qualquer pessoa abre no browser sem instalar nada.</p>
                <div class="url-box">
                    <span class="url-text" id="railway-url"><?= $railway_url ?></span>
                    <button class="url-copy" onclick="copyUrl()">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:5px"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        Copiar link
                    </button>
                    <a href="<?= $railway_url ?>" target="_blank" class="btn btn-primary" style="padding:7px 14px;font-size:12px;display:flex;align-items:center;gap:6px;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        Abrir
                    </a>
                </div>
                <p style="font-size:12px;color:var(--text-muted);margin-top:10px;">Login: <strong>admin</strong> / <strong>admin123</strong> &nbsp;·&nbsp; PDV: <a href="<?= $railway_url ?>/CAIXA/" target="_blank" style="color:var(--primary);"><?= $railway_url ?>/CAIXA/</a></p>
            </div>
        </div>
    </div>
</div>

<!-- Download ZIP -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="display:flex;align-items:center;gap:20px;padding:20px 24px;">
        <div style="width:40px;height:40px;background:rgba(99,102,241,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        </div>
        <div style="flex:1">
            <div style="font-size:14px;font-weight:600;margin-bottom:2px;">Descarregar código fonte</div>
            <div style="font-size:12px;color:var(--text-muted);">ZIP completo para entrega do projeto · ~<?= $size_mb ?> MB · Inclui INICIAR_MAC.command e INICIAR_WINDOWS.bat</div>
        </div>
        <a href="export.php?action=download" class="btn btn-secondary btn-sm" style="display:flex;align-items:center;gap:7px;white-space:nowrap;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Descarregar ZIP
        </a>
    </div>
</div>

<!-- Guia instalação local -->
<div class="card">
    <div class="card-body" style="padding:0;">
        <div style="padding:16px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;border-radius:var(--radius-lg) var(--radius-lg) 0 0;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            <span style="font-size:15px;font-weight:700;">Instalar Localmente (sem internet)</span>
        </div>
        <div style="padding:24px;">

            <div class="step-item">
                <div class="step-num">1</div>
                <div class="step-content">
                    <h4>Instalar pré-requisitos: PHP 8.1+, MySQL 8+, Composer</h4>
                    <p>No macOS via Homebrew:</p>
                    <div class="copy-wrap">
                        <button class="copy-btn" onclick="copyCode(this)">Copiar</button>
                        <div class="step-code">brew install php mysql composer</div>
                    </div>
                </div>
            </div>
            <div style="border-top:1px solid var(--border);margin:20px 0;"></div>

            <div class="step-item">
                <div class="step-num">2</div>
                <div class="step-content">
                    <h4>Extrair o ZIP e instalar dependências</h4>
                    <div class="copy-wrap">
                        <button class="copy-btn" onclick="copyCode(this)">Copiar</button>
                        <div class="step-code">cd ~/Downloads/PAP_Supermercado_*
composer install</div>
                    </div>
                </div>
            </div>
            <div style="border-top:1px solid var(--border);margin:20px 0;"></div>

            <div class="step-item">
                <div class="step-num">3</div>
                <div class="step-content">
                    <h4>Criar base de dados MySQL</h4>
                    <div class="copy-wrap">
                        <button class="copy-btn" onclick="copyCode(this)">Copiar</button>
                        <div class="step-code">mysql -u root -p -e "CREATE DATABASE supermercado; CREATE USER 'pap_user'@'localhost' IDENTIFIED BY 'pap_pass'; GRANT ALL ON supermercado.* TO 'pap_user'@'localhost';"</div>
                    </div>
                </div>
            </div>
            <div style="border-top:1px solid var(--border);margin:20px 0;"></div>

            <div class="step-item">
                <div class="step-num">4</div>
                <div class="step-content">
                    <h4>Importar migrações SQL</h4>
                    <div class="copy-wrap">
                        <button class="copy-btn" onclick="copyCode(this)">Copiar</button>
                        <div class="step-code">cd ~/Downloads/PAP_Supermercado_*/migrations
for f in 001 002 003 004 005 006 007 008; do
  for sql in ${f}_*.sql; do [ -f "$sql" ] && mysql -u pap_user -ppap_pass supermercado < "$sql"; done
done</div>
                    </div>
                </div>
            </div>
            <div style="border-top:1px solid var(--border);margin:20px 0;"></div>

            <div class="step-item">
                <div class="step-num">5</div>
                <div class="step-content">
                    <h4>Iniciar — duplo-clique em <code class="ci">INICIAR_MAC.command</code> ou <code class="ci">INICIAR_WINDOWS.bat</code></h4>
                    <p>O browser abre automaticamente em <strong>http://localhost:8000</strong>. &nbsp; Login: <strong>admin</strong> / <strong>admin123</strong></p>
                </div>
            </div>

        </div>
    </div>
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
function copyCode(btn) {
    const code = btn.closest('.copy-wrap').querySelector('.step-code');
    navigator.clipboard.writeText(code.textContent.trim()).then(() => {
        btn.textContent = 'Copiado ✓';
        btn.style.color = '#4ade80';
        setTimeout(() => { btn.textContent = 'Copiar'; btn.style.color = ''; }, 2000);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
