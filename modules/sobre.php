<?php
/**
 * SOBRE O SISTEMA — PAP SUPERMERCADO
 * Apresentação das funcionalidades do sistema
 */
session_start();
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Sobre o Sistema';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .sobre-hero {
        margin-bottom: 32px;
    }
    .sobre-hero h2 {
        font-size: 28px;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: var(--text-primary);
        margin-bottom: 8px;
    }
    .sobre-hero p {
        font-size: 14px;
        color: var(--text-secondary);
        max-width: 560px;
        line-height: 1.7;
    }

    /* Mini gráfico SVG para card de relatórios */
    .mini-chart-wrap {
        width: 100%;
        height: 40px;
        margin-bottom: 4px;
    }

    /* Badge overlay no card de relatórios */
    .analytics-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: var(--success-subtle);
        color: var(--success);
        font-size: 11px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 20px;
        margin-top: 4px;
    }

    /* Versão no fundo */
    .version-bar {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 20px 0;
        border-top: 1px solid var(--border);
        margin-top: 8px;
        font-size: 13px;
        color: var(--text-muted);
        flex-wrap: wrap;
    }
    .version-bar strong { color: var(--text-secondary); }
</style>

<!-- Header da página -->
<div class="sobre-hero fade-in">
    <h2>Sobre o PAP Supermercado</h2>
    <p>Sistema de gestão integrada para supermercados — controla vendas, stock, equipa e relatórios numa única plataforma, acessível em qualquer dispositivo.</p>
</div>

<!-- Grid de 5 cards de funcionalidades -->
<div class="features-grid fade-in">

    <!-- 1. Configuração Rápida -->
    <div class="feature-card">
        <div class="feature-icon-wrap">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div class="feature-title">Configuração Rápida</div>
        <div class="feature-desc">Cria a tua loja, define utilizadores e turnos, e começa a vender em poucos minutos. Sem complicações.</div>
    </div>

    <!-- 2. Acesso por Função -->
    <div class="feature-card">
        <div class="feature-icon-wrap">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        </div>
        <div class="feature-title">Acesso por Função</div>
        <div class="feature-desc">Cada utilizador acede apenas ao que precisa — administração, caixa ou recursos humanos — de forma segura e controlada.</div>
    </div>

    <!-- 3. Relatórios e Análises (wide) -->
    <div class="feature-card wide">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div>
                <div class="feature-icon-wrap" style="margin-bottom:12px;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                        <polyline points="17 6 23 6 23 12"/>
                    </svg>
                </div>
                <div class="feature-title">Relatórios e Análises</div>
                <div class="feature-desc" style="margin-top:6px;">Acompanha vendas, lucros e produtos mais vendidos com relatórios claros, filtros por período e gráficos atualizados em tempo real.</div>
                <div class="analytics-badge" style="margin-top:10px;">↑ Dados em tempo real</div>
            </div>
            <!-- Mini gráfico de linha SVG -->
            <div style="flex-shrink:0;align-self:flex-end;">
                <svg width="100" height="48" viewBox="0 0 100 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 40 L20 28 L38 32 L54 14 L70 20 L86 6 L96 10"
                          stroke="var(--text-secondary)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    <path d="M4 40 L20 28 L38 32 L54 14 L70 20 L86 6 L96 10 L96 48 L4 48 Z"
                          fill="var(--bg-tertiary)" opacity="0.7"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- 4. Interface Simples (wide) -->
    <div class="feature-card wide">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
            <div style="flex:1;min-width:160px;">
                <div class="feature-icon-wrap" style="margin-bottom:12px;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <path d="M3 9h18"/>
                        <path d="M9 21V9"/>
                    </svg>
                </div>
                <div class="feature-title">Interface Simples e Intuitiva</div>
                <div class="feature-desc" style="margin-top:6px;">Gere o teu supermercado com uma interface limpa, organizada e fácil de usar, em qualquer ecrã — PC, tablet ou telemóvel.</div>
            </div>
            <!-- Mini preview do dashboard -->
            <div class="mini-preview" style="height:72px;width:140px;flex-shrink:0;align-self:center;">
                <div class="mini-preview-sidebar">
                    <div class="mini-preview-dot"></div>
                    <div class="mini-preview-dot"></div>
                    <div class="mini-preview-dot"></div>
                    <div class="mini-preview-dot"></div>
                    <div class="mini-preview-dot"></div>
                </div>
                <div class="mini-preview-content">
                    <div style="display:flex;gap:3px;margin-bottom:3px;">
                        <div class="mini-preview-bar" style="width:30%;height:6px;"></div>
                        <div class="mini-preview-bar" style="width:30%;height:6px;"></div>
                        <div class="mini-preview-bar" style="width:30%;height:6px;"></div>
                    </div>
                    <div class="mini-preview-bar" style="width:90%;height:24px;border-radius:3px;"></div>
                    <div style="display:flex;gap:3px;margin-top:3px;">
                        <div class="mini-preview-bar" style="width:45%;height:10px;border-radius:3px;"></div>
                        <div class="mini-preview-bar" style="width:45%;height:10px;border-radius:3px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Funciona em Qualquer Loja -->
    <div class="feature-card">
        <div class="feature-icon-wrap">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="2" y1="12" x2="22" y2="12"/>
                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
            </svg>
        </div>
        <div class="feature-title">Funciona em Qualquer Loja</div>
        <div class="feature-desc">Suporta múltiplas lojas e está disponível em qualquer dispositivo, incluindo modo offline (PWA) para usar sem internet.</div>
    </div>

</div><!-- /features-grid -->

<!-- Barra de versão -->
<div class="version-bar fade-in">
    <span><strong>Versão</strong> 1.0.0</span>
    <span>·</span>
    <span><strong>PAP Supermercado</strong> — Sistema de Gestão Integrada</span>
    <span>·</span>
    <a href="/" style="color:var(--text-muted);text-decoration:none;" onmouseover="this.style.color='var(--text-secondary)'" onmouseout="this.style.color='var(--text-muted)'">← Voltar ao Painel</a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
