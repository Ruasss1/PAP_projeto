<?php
session_start();
require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/includes/header.php';
?>

<style>
    .download-page {
        max-width: 1000px;
        margin: 0 auto;
        padding: 40px 20px;
    }
    
    .download-hero {
        text-align: center;
        margin-bottom: 50px;
    }
    
    .download-hero .icon {
        font-size: 80px;
        margin-bottom: 20px;
    }
    
    .download-hero h1 {
        font-size: 36px;
        margin-bottom: 16px;
    }
    
    .download-hero p {
        color: #a0a0a0;
        font-size: 18px;
        max-width: 600px;
        margin: 0 auto;
    }
    
    .download-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 24px;
        margin-bottom: 50px;
    }
    
    .download-card {
        background: #141414;
        border: 2px solid #222222;
        border-radius: 16px;
        padding: 32px;
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .download-card:hover {
        border-color: #ffffff;
        transform: translateY(-4px);
    }
    
    .download-card .os-icon {
        font-size: 48px;
        margin-bottom: 16px;
    }
    
    .download-card h3 {
        font-size: 20px;
        margin-bottom: 8px;
    }
    
    .download-card .version {
        color: #666666;
        font-size: 14px;
        margin-bottom: 20px;
    }
    
    .download-card .btn-download {
        display: inline-block;
        background: #a1a1aa;
        color: white;
        padding: 14px 32px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.2s ease;
        border: none;
        cursor: pointer;
        font-size: 16px;
    }
    
    .download-card .btn-download:hover {
        background: #1d4ed8;
        transform: translateY(-2px);
    }
    
    .download-card .btn-download.disabled {
        background: #333333;
        cursor: not-allowed;
        opacity: 0.6;
    }
    
    .download-card .size {
        color: #666666;
        font-size: 13px;
        margin-top: 12px;
    }
    
    .features-section {
        background: #141414;
        border-radius: 16px;
        padding: 40px;
        margin-bottom: 50px;
        border: 1px solid #222222;
    }
    
    .features-section h2 {
        text-align: center;
        margin-bottom: 32px;
        font-size: 24px;
    }
    
    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 24px;
    }
    
    .feature-item {
        text-align: center;
        padding: 20px;
    }
    
    .feature-item .icon {
        font-size: 32px;
        margin-bottom: 12px;
    }
    
    .feature-item h4 {
        margin-bottom: 8px;
        font-size: 16px;
    }
    
    .feature-item p {
        color: #a0a0a0;
        font-size: 14px;
    }
    
    .instructions {
        background: #0a0a0a;
        border-radius: 12px;
        padding: 32px;
        border: 1px solid #222222;
    }
    
    .instructions h2 {
        margin-bottom: 24px;
        font-size: 20px;
    }
    
    .instruction-step {
        display: flex;
        gap: 16px;
        margin-bottom: 20px;
        padding-bottom: 20px;
        border-bottom: 1px solid #222222;
    }
    
    .instruction-step:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    
    .step-number {
        background: #a1a1aa;
        color: white;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        flex-shrink: 0;
    }
    
    .step-content h4 {
        margin-bottom: 8px;
    }
    
    .step-content p {
        color: #a0a0a0;
        font-size: 14px;
    }
    
    .step-content code {
        background: #1a1a1a;
        padding: 8px 12px;
        border-radius: 6px;
        display: block;
        margin-top: 8px;
        font-family: 'Monaco', 'Consolas', monospace;
        color: #10b981;
        font-size: 13px;
        overflow-x: auto;
    }
    
    .dev-notice {
        background: rgba(245, 158, 11, 0.1);
        border: 1px solid rgba(245, 158, 11, 0.3);
        border-radius: 8px;
        padding: 16px 20px;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .dev-notice .icon {
        font-size: 24px;
    }
    
    .dev-notice p {
        color: #f59e0b;
        font-size: 14px;
        margin: 0;
    }
</style>

<div class="download-page">
    <div class="download-hero">
        <div class="icon">🏪</div>
        <h1>Download PAP Supermercado</h1>
        <p>Descarrega a aplicação desktop para uma experiência nativa no teu computador</p>
        <a href="/export.php" style="display:inline-flex;align-items:center;gap:8px;margin-top:18px;padding:12px 24px;background:rgba(59,130,246,.15);border:1px solid rgba(59,130,246,.35);border-radius:10px;color:#60a5fa;text-decoration:none;font-size:14px;font-weight:600;transition:opacity .2s" onmouseover="this.style.opacity='.75'" onmouseout="this.style.opacity='1'">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Exportar Projeto como ZIP + Guia de Instalação
        </a>
    </div>
    
    <div class="dev-notice">
        <span class="icon">⚠️</span>
        <p><strong>Modo Desenvolvedor:</strong> A aplicação requer que o servidor PHP esteja a correr. Siga as instruções abaixo para executar.</p>
    </div>
    
    <div class="download-cards">
        <div class="download-card">
            <div class="os-icon">🍎</div>
            <h3>macOS</h3>
            <div class="version">v1.0.0 • macOS 10.15+</div>
            <button class="btn-download disabled" title="Build necessário">
                📥 Descarregar .dmg
            </button>
            <div class="size">Requer build local</div>
        </div>
        
        <div class="download-card">
            <div class="os-icon">🪟</div>
            <h3>Windows</h3>
            <div class="version">v1.0.0 • Windows 10+</div>
            <button class="btn-download disabled" title="Build necessário">
                📥 Descarregar .exe
            </button>
            <div class="size">Requer build local</div>
        </div>
        
        <div class="download-card">
            <div class="os-icon">🐧</div>
            <h3>Linux</h3>
            <div class="version">v1.0.0 • Ubuntu 18.04+</div>
            <button class="btn-download disabled" title="Build necessário">
                📥 Descarregar .AppImage
            </button>
            <div class="size">Requer build local</div>
        </div>
    </div>
    
    <div class="features-section">
        <h2>✨ Funcionalidades da App</h2>
        <div class="features-grid">
            <div class="feature-item">
                <div class="icon">🖥️</div>
                <h4>Interface Nativa</h4>
                <p>Experiência desktop completa com menu e atalhos</p>
            </div>
            <div class="feature-item">
                <div class="icon">⌨️</div>
                <h4>Atalhos de Teclado</h4>
                <p>Ctrl+R, F11, Ctrl+Q e muitos mais</p>
            </div>
            <div class="feature-item">
                <div class="icon">🔍</div>
                <h4>Zoom Flexível</h4>
                <p>Ajusta o tamanho da interface facilmente</p>
            </div>
            <div class="feature-item">
                <div class="icon">📺</div>
                <h4>Ecrã Completo</h4>
                <p>Modo fullscreen para melhor visibilidade</p>
            </div>
            <div class="feature-item">
                <div class="icon">🧭</div>
                <h4>Navegação Rápida</h4>
                <p>Menu com acesso direto a todas as secções</p>
            </div>
            <div class="feature-item">
                <div class="icon">🔒</div>
                <h4>Seguro</h4>
                <p>Isolamento de contexto e proteção de dados</p>
            </div>
        </div>
    </div>
    
    <div class="instructions">
        <h2>📋 Como Executar a Aplicação</h2>
        
        <div class="instruction-step">
            <div class="step-number">1</div>
            <div class="step-content">
                <h4>Instalar Node.js</h4>
                <p>Se ainda não tens Node.js instalado, descarrega de <a href="https://nodejs.org" target="_blank" style="color: #a1a1aa;">nodejs.org</a></p>
            </div>
        </div>
        
        <div class="instruction-step">
            <div class="step-number">2</div>
            <div class="step-content">
                <h4>Instalar dependências</h4>
                <p>Abre o Terminal e navega até à pasta App:</p>
                <code>cd /Users/vascoruas/Documents/PAP_projeto/App && npm install</code>
            </div>
        </div>
        
        <div class="instruction-step">
            <div class="step-number">3</div>
            <div class="step-content">
                <h4>Iniciar o servidor PHP</h4>
                <p>Num terminal separado, inicia o servidor:</p>
                <code>cd /Users/vascoruas/Documents/PAP_projeto && php -S localhost:8000</code>
            </div>
        </div>
        
        <div class="instruction-step">
            <div class="step-number">4</div>
            <div class="step-content">
                <h4>Executar a aplicação</h4>
                <p>Noutro terminal, inicia a aplicação Electron:</p>
                <code>cd /Users/vascoruas/Documents/PAP_projeto/App && npm start</code>
            </div>
        </div>
        
        <div class="instruction-step">
            <div class="step-number">5</div>
            <div class="step-content">
                <h4>Criar instalador (opcional)</h4>
                <p>Para criar um instalador distribuível:</p>
                <code>cd /Users/vascoruas/Documents/PAP_projeto/App && npm run build:mac</code>
                <p style="margin-top: 8px;">Usa <code style="display: inline; padding: 2px 6px;">build:win</code> para Windows ou <code style="display: inline; padding: 2px 6px;">build:linux</code> para Linux</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
