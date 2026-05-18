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

$page_title = 'Exportar & Instalar';
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
.note-alert { display:flex;gap:10px;padding:12px 16px;border-radius:8px;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);margin-top:10px; }
.note-alert p { color:#fbbf24;font-size:12px;margin:0;line-height:1.5; }
</style>

<!-- Download card -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="display:flex;align-items:center;gap:24px;padding:24px;">
        <div style="width:52px;height:52px;background:rgba(99,102,241,.12);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        </div>
        <div style="flex:1">
            <div style="font-size:16px;font-weight:700;margin-bottom:4px;">PAP Supermercado — Projeto Completo</div>
            <div style="font-size:13px;color:var(--text-secondary);">Inclui todos os ficheiros PHP, CSS, JS e migrações SQL. Não inclui <code class="ci">vendor/</code>.</div>
            <div style="display:flex;gap:16px;margin-top:8px;flex-wrap:wrap;">
                <span style="font-size:12px;color:var(--text-muted);display:flex;align-items:center;gap:5px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/></svg>
                    .zip · ~<?= $size_mb ?> MB
                </span>
                <span style="font-size:12px;color:var(--text-muted);display:flex;align-items:center;gap:5px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    Inclui INICIAR_MAC.command e INICIAR_WINDOWS.bat
                </span>
            </div>
        </div>
        <a href="export.php?action=download" class="btn btn-primary" style="white-space:nowrap;display:flex;align-items:center;gap:8px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Descarregar ZIP
        </a>
    </div>
</div>

<!-- Opção A: Online -->
<div class="card" style="margin-bottom:20px;border-color:rgba(99,102,241,.3);">
    <div class="card-body" style="padding:0;">
        <div style="padding:16px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;background:rgba(99,102,241,.05);border-radius:var(--radius-lg) var(--radius-lg) 0 0;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
            <span style="font-size:15px;font-weight:700;color:#a78bfa;">Opção A — Publicar Online com Railway.app</span>
            <span class="badge badge-success" style="margin-left:auto;">Grátis</span>
        </div>
        <div style="padding:24px;">
            <div class="step-item">
                <div class="step-num" style="background:rgba(99,102,241,.15);border-color:rgba(99,102,241,.3);">1</div>
                <div class="step-content">
                    <h4>Fazer upload para o GitHub</h4>
                    <p>Cria um repositório no GitHub e faz push do projeto:</p>
                    <div class="copy-wrap">
                        <button class="copy-btn" onclick="copyCode(this)">Copiar</button>
                        <div class="step-code">cd ~/Downloads/PAP_Supermercado_*
git init && git add . && git commit -m "PAP Supermercado"
git remote add origin https://github.com/SEU_USER/pap.git
git push -u origin main</div>
                    </div>
                </div>
            </div>
            <div style="border-top:1px solid var(--border);margin:20px 0;"></div>
            <div class="step-item">
                <div class="step-num" style="background:rgba(99,102,241,.15);border-color:rgba(99,102,241,.3);">2</div>
                <div class="step-content">
                    <h4>Criar projeto no Railway</h4>
                    <p>Vai a <a href="https://railway.app" target="_blank" style="color:#a78bfa;">railway.app</a> → <strong>New Project</strong> → <strong>Deploy from GitHub repo</strong> → seleciona o repositório.</p>
                </div>
            </div>
            <div style="border-top:1px solid var(--border);margin:20px 0;"></div>
            <div class="step-item">
                <div class="step-num" style="background:rgba(99,102,241,.15);border-color:rgba(99,102,241,.3);">3</div>
                <div class="step-content">
                    <h4>Adicionar MySQL + correr migrações</h4>
                    <p>No Railway → <strong>+ Add Service → Database → MySQL</strong>. Depois adiciona a variável <code class="ci">SETUP_TOKEN</code> e abre:</p>
                    <div class="step-code">https://SEU_PROJETO.up.railway.app/setup.php?token=SEU_TOKEN</div>
                    <div class="note-alert">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <p>Após o setup, remove a variável <code class="ci">SETUP_TOKEN</code> no Railway.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Opção B: Local -->
<div class="card">
    <div class="card-body" style="padding:0;">
        <div style="padding:16px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;border-radius:var(--radius-lg) var(--radius-lg) 0 0;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            <span style="font-size:15px;font-weight:700;">Opção B — Instalar Localmente</span>
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
                    <p>O browser abre automaticamente em <strong>http://localhost:8000</strong>.</p>
                    <p style="margin-top:8px;">Login: <strong>admin</strong> / <strong>admin123</strong></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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
