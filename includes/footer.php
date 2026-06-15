            </div><!-- /.app-content -->
        </main><!-- /.app-main -->
    </div><!-- /.app-layout -->

    <!-- Toast Container -->
    <div id="toast-container" style="position:fixed;bottom:80px;right:24px;z-index:10000;display:flex;flex-direction:column-reverse;gap:8px;pointer-events:none;"></div>

    <!-- Command Palette -->
    <div id="cmd-palette" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.6);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);">
        <div style="max-width:560px;margin:15vh auto 0;background:var(--bg-elevated,#111113);border:1px solid var(--border,#23232a);border-radius:16px;box-shadow:0 24px 80px rgba(0,0,0,0.5);overflow:hidden;">
            <div style="padding:16px;border-bottom:1px solid var(--border,#23232a);display:flex;align-items:center;gap:12px;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--text-muted);flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input id="cmd-input" type="text" placeholder="Pesquisar páginas, ações..." style="flex:1;background:none;border:none;outline:none;font-size:16px;color:var(--text-primary,#ececf1);font-family:inherit;" autocomplete="off">
                <kbd style="padding:2px 8px;background:var(--bg-tertiary,#1c1c1f);border:1px solid var(--border,#23232a);border-radius:4px;font-size:11px;color:var(--text-muted);font-family:'Plus Jakarta Sans',monospace;">ESC</kbd>
            </div>
            <div id="cmd-results" style="max-height:400px;overflow-y:auto;padding:8px;"></div>
        </div>
    </div>

    <script>
        // Change store
        function changeStore(storeId) {
            fetch('/api/change-store.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ store_id: storeId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) location.reload();
                else showToast('Erro ao mudar loja', 'error');
            })
            .catch(() => showToast('Erro ao mudar loja', 'error'));
        }

        // Toast notification system
        function showToast(message, type = 'info', duration = 4000) {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            const icons = { success: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>', error: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>', info: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>', warning: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' };
            const colors = { success: 'var(--success,#22c55e)', error: 'var(--danger,#ef4444)', info: 'var(--accent,#a1a1aa)', warning: 'var(--warning,#eab308)' };
            toast.style.cssText = `pointer-events:auto;display:flex;align-items:center;gap:10px;padding:14px 20px;background:var(--bg-elevated,#111113);border:1px solid var(--border,#23232a);border-left:3px solid ${colors[type]};border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,0.4);font-size:14px;color:var(--text-primary,#ececf1);transform:translateX(120%);transition:transform 0.3s cubic-bezier(0.34,1.56,0.64,1);min-width:280px;`;
            toast.innerHTML = `<span style="color:${colors[type]};font-weight:700;font-size:16px;">${icons[type]}</span><span style="flex:1;">${message}</span>`;
            container.appendChild(toast);
            requestAnimationFrame(() => { toast.style.transform = 'translateX(0)'; });
            setTimeout(() => {
                toast.style.transform = 'translateX(120%)';
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }

        // Command Palette
        const cmdPages = [
            { name: 'Dashboard', desc: 'Página principal', url: '/index.php', icon: '' },
            { name: 'PDV / Caixa', desc: 'Ponto de venda', url: '/CAIXA/', icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>' },
            { name: 'Produtos', desc: 'Gerir catálogo', url: '/modules/produtos.php', icon: '' },
            { name: 'Stock', desc: 'Inventário', url: '/modules/stock.php', icon: '' },
            { name: 'Recibos', desc: 'Histórico de vendas', url: '/modules/recibos.php', icon: '' },
            { name: 'Promoções', desc: 'Gerir promoções', url: '/modules/promocoes.php', icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>' },
            { name: 'Fornecedores', desc: 'Gerir fornecedores', url: '/modules/fornecedores.php', icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 5V8l-7 5V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v16z"/></svg>' },
            { name: 'Encomendas', desc: 'Gerir encomendas', url: '/modules/encomendas.php', icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>' },
            { name: 'Relatórios', desc: 'Relatórios e análises', url: '/modules/relatorios.php', icon: '' },
            { name: 'Analytics', desc: 'Análise avançada', url: '/modules/analytics.php', icon: '' },
            { name: 'Equipa / RH', desc: 'Gestão de equipa', url: '/admin/rh/equipa.php', icon: '' },
            { name: 'Folha de Pagamento', desc: 'Payroll', url: '/admin/payroll/list.php', icon: '' },
            { name: 'Clientes', desc: 'Gestão de clientes', url: '/modules/customers.php', icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>‍<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>‍<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>' },
            { name: 'Alertas', desc: 'Notificações', url: '/modules/alerts.php', icon: '' },
            { name: 'Configurações', desc: 'Sistema', url: '/modules/db_status.php', icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>' },
            { name: 'Alternar Tema', desc: 'Dark / Light mode', action: 'toggleTheme', icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>' },
            { name: 'Sair', desc: 'Logout', url: '/logout.php', icon: '' },
        ];

        let cmdSelected = 0;
        function openCmdPalette() {
            document.getElementById('cmd-palette').style.display = 'block';
            const input = document.getElementById('cmd-input');
            input.value = '';
            input.focus();
            renderCmdResults('');
        }
        function closeCmdPalette() {
            document.getElementById('cmd-palette').style.display = 'none';
        }
        function renderCmdResults(query) {
            const results = document.getElementById('cmd-results');
            const filtered = cmdPages.filter(p => 
                p.name.toLowerCase().includes(query.toLowerCase()) || 
                p.desc.toLowerCase().includes(query.toLowerCase())
            );
            cmdSelected = 0;
            results.innerHTML = filtered.map((p, i) => `
                <div class="cmd-item" data-idx="${i}" style="display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:8px;cursor:pointer;transition:background 0.15s;${i === 0 ? 'background:var(--bg-hover,#26262a);' : ''}" 
                     onclick="${p.action ? p.action + '();closeCmdPalette()' : 'window.location.href=\'' + p.url + '\''}"
                     onmouseenter="this.style.background='var(--bg-hover)'" onmouseleave="this.style.background=${i === cmdSelected ? '\'var(--bg-hover)\'' : '\'transparent\''}">
                    <span style="font-size:20px;width:32px;text-align:center;">${p.icon}</span>
                    <div style="flex:1;">
                        <div style="font-weight:600;font-size:14px;color:var(--text-primary);">${p.name}</div>
                        <div style="font-size:12px;color:var(--text-muted);">${p.desc}</div>
                    </div>
                    ${p.url ? '<span style="font-size:11px;color:var(--text-muted);font-family:monospace;">↵</span>' : ''}
                </div>
            `).join('') || '<div style="padding:40px;text-align:center;color:var(--text-muted);">Nenhum resultado encontrado</div>';
        }

        document.getElementById('cmd-input')?.addEventListener('input', function() { renderCmdResults(this.value); });
        document.getElementById('cmd-palette')?.addEventListener('click', function(e) {
            if (e.target === this) closeCmdPalette();
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Cmd+K to open command palette
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                const palette = document.getElementById('cmd-palette');
                palette.style.display === 'block' ? closeCmdPalette() : openCmdPalette();
            }
            // ESC to close
            if (e.key === 'Escape') closeCmdPalette();
            
            // Arrow nav in command palette
            if (document.getElementById('cmd-palette')?.style.display === 'block') {
                const items = document.querySelectorAll('.cmd-item');
                if (e.key === 'ArrowDown') { e.preventDefault(); cmdSelected = Math.min(cmdSelected + 1, items.length - 1); }
                if (e.key === 'ArrowUp') { e.preventDefault(); cmdSelected = Math.max(cmdSelected - 1, 0); }
                items.forEach((item, i) => item.style.background = i === cmdSelected ? 'var(--bg-hover)' : 'transparent');
                if (e.key === 'Enter' && items[cmdSelected]) items[cmdSelected].click();
            }

            // Alt shortcuts
            if (e.altKey) {
                switch(e.key.toLowerCase()) {
                    case 'v': window.location.href = '/modules/vendas.php'; break;
                    case 'p': window.location.href = '/modules/produtos.php'; break;
                    case 's': window.location.href = '/modules/stock.php'; break;
                    case 'd': window.location.href = '/index.php'; break;
                    case 'c': window.location.href = '/CAIXA/'; break;
                }
            }
        });

        // Auto-hide alerts after 5 seconds
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });

        // Animate on scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in-up');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
        document.querySelectorAll('.card, .stat-card').forEach(el => observer.observe(el));

        // Theme Toggle
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('pap-theme', next);
        }
    </script>
    
    <!-- Theme Toggle Button -->
    <button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema">
        <span class="icon-moon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
            </svg>
        </span>
        <span class="icon-sun">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="5"/>
                <line x1="12" y1="1" x2="12" y2="3"/>
                <line x1="12" y1="21" x2="12" y2="23"/>
                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                <line x1="1" y1="12" x2="3" y2="12"/>
                <line x1="21" y1="12" x2="23" y2="12"/>
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
            </svg>
        </span>
    </button>
    
    <!-- Keyboard shortcut hint -->
    <div style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:100;background:var(--bg-elevated,#111);border:1px solid var(--border,#23232a);border-radius:8px;padding:6px 14px;font-size:12px;color:var(--text-muted);display:flex;align-items:center;gap:8px;opacity:0.7;pointer-events:none;">
        <kbd style="padding:2px 6px;background:var(--bg-tertiary);border:1px solid var(--border);border-radius:3px;font-size:10px;font-family:'Plus Jakarta Sans',monospace;">⌘K</kbd>
        <span>Pesquisa rápida</span>
    </div>
    
    <script src="/assets/js/master-ui.js?v=<?= time() ?>"></script>

    <?php if (isset($extra_js)): ?>
    <script><?= $extra_js ?></script>
    <?php endif; ?>
</body>
</html>