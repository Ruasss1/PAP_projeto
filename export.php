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

require_once __DIR__ . '/includes/header.php';
?>

<style>
:root { --accent-blue: #3b82f6; }
.export-wrap { max-width: 900px; margin: 0 auto; padding: 40px 20px; }

/* Hero */
.export-hero { text-align:center; margin-bottom:48px; }
.export-hero .hero-icon { width:72px;height:72px;background:rgba(59,130,246,.15);border:1px solid rgba(59,130,246,.3);border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 20px; }
.export-hero h1 { font-size:30px;font-weight:700;margin-bottom:10px; }
.export-hero p { color:var(--text-muted,#888);font-size:16px;max-width:520px;margin:0 auto; }

/* Download card */
.dl-card { background:var(--bg-secondary,#111);border:1px solid var(--border,#222);border-radius:16px;padding:32px;display:flex;align-items:center;gap:32px;margin-bottom:32px; }
.dl-card-icon { width:64px;height:64px;background:rgba(59,130,246,.12);border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.dl-card-info { flex:1 }
.dl-card-info h2 { font-size:20px;font-weight:700;margin-bottom:6px; }
.dl-card-info p { color:var(--text-muted,#888);font-size:14px;line-height:1.5; }
.dl-card-meta { display:flex;gap:16px;margin-top:10px;flex-wrap:wrap; }
.dl-meta-item { display:flex;align-items:center;gap:6px;font-size:13px;color:var(--text-muted,#888); }
.dl-btn { display:inline-flex;align-items:center;gap:10px;padding:14px 28px;background:var(--accent-blue);color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;text-decoration:none;white-space:nowrap;transition:opacity .2s,transform .2s;font-family:inherit; }
.dl-btn:hover { opacity:.88;transform:translateY(-2px); }

/* Tabs steps */
.steps-card { background:var(--bg-secondary,#111);border:1px solid var(--border,#222);border-radius:16px;overflow:hidden; }
.steps-header { padding:20px 28px;border-bottom:1px solid var(--border,#222);display:flex;align-items:center;gap:10px; }
.steps-header h2 { font-size:18px;font-weight:700; }
.steps-body { padding:28px; }
.step-item { display:flex;gap:20px;margin-bottom:28px; }
.step-item:last-child { margin-bottom:0; }
.step-num { width:36px;height:36px;border-radius:50%;background:rgba(59,130,246,.15);border:1px solid rgba(59,130,246,.3);color:#60a5fa;font-size:15px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px; }
.step-content h4 { font-size:15px;font-weight:600;margin-bottom:5px; }
.step-content p { color:var(--text-muted,#888);font-size:14px;line-height:1.6;margin:0; }
.step-code { background:var(--bg-tertiary,#0a0a0a);border:1px solid var(--border,#222);border-radius:8px;padding:12px 16px;margin-top:10px;font-family:'Monaco','Consolas',monospace;font-size:13px;color:#4ade80;overflow-x:auto;white-space:pre; }
.step-code-inline { background:var(--bg-tertiary,#0a0a0a);padding:2px 8px;border-radius:4px;font-family:'Monaco','Consolas',monospace;font-size:12px;color:#4ade80;border:1px solid var(--border,#222); }
.copy-btn { background:rgba(255,255,255,.06);border:1px solid var(--border,#222);color:var(--text-muted,#888);padding:4px 10px;border-radius:5px;font-size:11px;cursor:pointer;float:right;margin-bottom:4px;font-family:inherit;transition:background .2s; }
.copy-btn:hover { background:rgba(255,255,255,.12); }
.copy-wrap { position:relative; }
.divider { border:none;border-top:1px solid var(--border,#222);margin:28px 0; }
.badge-req { display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:6px;font-size:12px;font-weight:500;background:rgba(59,130,246,.12);color:#60a5fa;border:1px solid rgba(59,130,246,.25);margin-right:6px;margin-bottom:6px; }
.reqs-row { margin-top:12px; }
.note-box { display:flex;gap:12px;padding:14px 18px;border-radius:10px;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.25);margin-top:14px; }
.note-box p { color:#fbbf24;font-size:13px;margin:0;line-height:1.5; }
</style>

<div class="export-wrap">

    <div class="export-hero">
        <div class="hero-icon">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#60a5fa" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        </div>
        <h1>Exportar &amp; Instalar</h1>
        <p>Descarrega o projeto completo e instala-o em qualquer computador com PHP e MySQL</p>
    </div>

    <!-- Download direto -->
    <div class="dl-card">
        <div class="dl-card-icon">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#60a5fa" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        </div>
        <div class="dl-card-info">
            <h2>PAP Supermercado — Projeto Completo</h2>
            <p>Inclui todos os ficheiros PHP, CSS, JS, migrações SQL e guia de instalação (SETUP.md). Não inclui <code class="step-code-inline">vendor/</code> nem <code class="step-code-inline">node_modules/</code>.</p>
            <div class="dl-card-meta">
                <div class="dl-meta-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
                    Arquivo .zip · ~<?= $size_mb ?> MB
                </div>
                <div class="dl-meta-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Gerado em tempo real
                </div>
                <div class="dl-meta-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    Inclui SETUP.md
                </div>
            </div>
        </div>
        <a href="export.php?action=download" class="dl-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Descarregar ZIP
        </a>
    </div>

    <!-- Deploy online -->
    <div class="steps-card" style="margin-bottom:24px;border-color:rgba(99,102,241,.35)">
        <div class="steps-header" style="background:rgba(99,102,241,.07)">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
            <h2 style="color:#a78bfa">Opção A — Publicar Online (Railway.app) — URL pública grátis</h2>
        </div>
        <div class="steps-body">
            <div class="step-item">
                <div class="step-num" style="background:rgba(99,102,241,.15);border-color:rgba(99,102,241,.3);color:#a78bfa">1</div>
                <div class="step-content">
                    <h4>Criar conta no GitHub e fazer upload do projeto</h4>
                    <p>O Railway liga-se ao GitHub. Cria um repositório (pode ser privado) e faz push do projeto:</p>
                    <div class="copy-wrap">
                        <button class="copy-btn" onclick="copyCode(this)">Copiar</button>
                        <div class="step-code">cd ~/Downloads/PAP_Supermercado_*
git init
git add .
git commit -m "PAP Supermercado"
git remote add origin https://github.com/SEU_UTILIZADOR/pap-supermercado.git
git push -u origin main</div>
                    </div>
                </div>
            </div>
            <hr class="divider">
            <div class="step-item">
                <div class="step-num" style="background:rgba(99,102,241,.15);border-color:rgba(99,102,241,.3);color:#a78bfa">2</div>
                <div class="step-content">
                    <h4>Criar projeto no Railway</h4>
                    <p>Vai a <a href="https://railway.app" target="_blank" style="color:#a78bfa">railway.app</a> → <strong>New Project</strong> → <strong>Deploy from GitHub repo</strong> → seleciona o repositório.</p>
                </div>
            </div>
            <hr class="divider">
            <div class="step-item">
                <div class="step-num" style="background:rgba(99,102,241,.15);border-color:rgba(99,102,241,.3);color:#a78bfa">3</div>
                <div class="step-content">
                    <h4>Adicionar base de dados MySQL</h4>
                    <p>No painel do Railway → <strong>+ Add Service</strong> → <strong>Database</strong> → <strong>MySQL</strong>. As variáveis de ambiente são preenchidas automaticamente.</p>
                </div>
            </div>
            <hr class="divider">
            <div class="step-item">
                <div class="step-num" style="background:rgba(99,102,241,.15);border-color:rgba(99,102,241,.3);color:#a78bfa">4</div>
                <div class="step-content">
                    <h4>Correr as migrações (uma única vez)</h4>
                    <p>No Railway, adiciona a variável de ambiente <code class="step-code-inline">SETUP_TOKEN</code> com qualquer valor secreto. Depois abre no browser:</p>
                    <div class="step-code">https://SEU_PROJETO.up.railway.app/setup.php?token=SEU_TOKEN</div>
                    <div class="note-box">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2" style="flex-shrink:0;margin-top:2px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <p>Depois de correr o setup, remove a variável <code class="step-code-inline">SETUP_TOKEN</code> no Railway para desativar o endpoint.</p>
                    </div>
                </div>
            </div>
            <hr class="divider">
            <div class="step-item">
                <div class="step-num" style="background:rgba(99,102,241,.15);border-color:rgba(99,102,241,.3);color:#a78bfa">5</div>
                <div class="step-content">
                    <h4>Partilhar o link!</h4>
                    <p>O Railway gera automaticamente um URL como <code class="step-code-inline">pap-supermercado.up.railway.app</code>. Qualquer pessoa pode aceder sem instalar nada.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Guia passo a passo local -->
    <div class="steps-card">
        <div class="steps-header">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#60a5fa" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <h2>Opção B — Instalar Localmente (no próprio computador)</h2>
        </div>
        <div class="steps-body">

            <div class="step-item">
                <div class="step-num">1</div>
                <div class="step-content">
                    <h4>Instalar os pré-requisitos</h4>
                    <p>Certifica-te que tens instalado no computador de destino:</p>
                    <div class="reqs-row">
                        <span class="badge-req"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>PHP 8.1+</span>
                        <span class="badge-req"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>MySQL 8+ ou MariaDB</span>
                        <span class="badge-req"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Composer</span>
                    </div>
                    <div class="note-box" style="margin-top:12px">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2" style="flex-shrink:0;margin-top:2px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <p>No macOS podes instalar tudo via <strong>Homebrew</strong>: <code class="step-code-inline">brew install php mysql composer</code></p>
                    </div>
                </div>
            </div>

            <hr class="divider">

            <div class="step-item">
                <div class="step-num">2</div>
                <div class="step-content">
                    <h4>Extrair o ZIP e instalar dependências</h4>
                    <p>Extrai o ficheiro descarregado e corre o Composer dentro da pasta:</p>
                    <div class="copy-wrap">
                        <button class="copy-btn" onclick="copyCode(this)">Copiar</button>
                        <div class="step-code">cd ~/Downloads/PAP_Supermercado_*
composer install</div>
                    </div>
                </div>
            </div>

            <hr class="divider">

            <div class="step-item">
                <div class="step-num">3</div>
                <div class="step-content">
                    <h4>Criar a base de dados MySQL</h4>
                    <p>Abre o terminal e executa:</p>
                    <div class="copy-wrap">
                        <button class="copy-btn" onclick="copyCode(this)">Copiar</button>
                        <div class="step-code">mysql -u root -p -e "
CREATE DATABASE supermercado CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pap_user'@'localhost' IDENTIFIED BY 'pap_pass';
GRANT ALL PRIVILEGES ON supermercado.* TO 'pap_user'@'localhost';
FLUSH PRIVILEGES;"</div>
                    </div>
                </div>
            </div>

            <hr class="divider">

            <div class="step-item">
                <div class="step-num">4</div>
                <div class="step-content">
                    <h4>Importar as migrações (por ordem)</h4>
                    <p>Executa todos os ficheiros SQL da pasta <code class="step-code-inline">migrations/</code>:</p>
                    <div class="copy-wrap">
                        <button class="copy-btn" onclick="copyCode(this)">Copiar</button>
                        <div class="step-code">cd ~/Downloads/PAP_Supermercado_*/migrations
for f in 001 002 003 004 005 006 007 008; do
  for sql in ${f}_*.sql; do
    [ -f "$sql" ] && mysql -u pap_user -ppap_pass supermercado < "$sql" && echo "OK: $sql"
  done
done</div>
                    </div>
                </div>
            </div>

            <hr class="divider">

            <div class="step-item">
                <div class="step-num">5</div>
                <div class="step-content">
                    <h4>Iniciar o servidor PHP</h4>
                    <p>Na pasta do projeto, inicia o servidor de desenvolvimento:</p>
                    <div class="copy-wrap">
                        <button class="copy-btn" onclick="copyCode(this)">Copiar</button>
                        <div class="step-code">cd ~/Downloads/PAP_Supermercado_*
php -S localhost:8000</div>
                    </div>
                    <div class="note-box">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2" style="flex-shrink:0;margin-top:2px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <p>Deixa este terminal aberto enquanto usas a aplicação.</p>
                    </div>
                </div>
            </div>

            <hr class="divider">

            <div class="step-item">
                <div class="step-num">6</div>
                <div class="step-content">
                    <h4>Abrir no browser</h4>
                    <p>Abre o browser e vai a:</p>
                    <div class="step-code">http://localhost:8000</div>
                    <p style="margin-top:10px">Utilizador: <strong>admin</strong> · Senha: <strong>admin123</strong></p>
                </div>
            </div>

        </div>
    </div>

</div>

<script>
function copyCode(btn) {
    const code = btn.closest('.copy-wrap').querySelector('.step-code');
    navigator.clipboard.writeText(code.textContent.trim()).then(() => {
        btn.textContent = 'Copiado!';
        btn.style.color = '#4ade80';
        setTimeout(() => { btn.textContent = 'Copiar'; btn.style.color = ''; }, 2000);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
