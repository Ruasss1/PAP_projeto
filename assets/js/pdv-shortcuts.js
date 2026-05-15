/**
 * Atalhos de Teclado para PDV
 * assets/js/pdv-shortcuts.js
 */

(function() {
    'use strict';

    // Configuração dos atalhos
    const shortcuts = {
        'F1': { action: 'help', description: 'Mostrar ajuda' },
        'F2': { action: 'search', description: 'Pesquisar produto' },
        'F3': { action: 'barcode', description: 'Inserir código de barras' },
        'F4': { action: 'quantity', description: 'Alterar quantidade' },
        'F5': { action: 'discount', description: 'Aplicar desconto' },
        'F6': { action: 'customer', description: 'Selecionar cliente' },
        'F7': { action: 'hold', description: 'Guardar venda' },
        'F8': { action: 'recall', description: 'Recuperar venda' },
        'F9': { action: 'payment', description: 'Pagamento' },
        'F10': { action: 'cash', description: 'Pagamento dinheiro' },
        'F11': { action: 'card', description: 'Pagamento cartão' },
        'F12': { action: 'finalize', description: 'Finalizar venda' },
        'Escape': { action: 'cancel', description: 'Cancelar/Fechar' },
        'Delete': { action: 'remove', description: 'Remover item' },
        '+': { action: 'increase', description: 'Aumentar quantidade' },
        '-': { action: 'decrease', description: 'Diminuir quantidade' },
        '*': { action: 'price', description: 'Alterar preço' },
        '/': { action: 'split', description: 'Dividir pagamento' }
    };

    // Estado atual
    let currentFocus = null;
    let helpVisible = false;

    // Criar modal de ajuda
    function createHelpModal() {
        const modal = document.createElement('div');
        modal.id = 'shortcuts-help';
        modal.innerHTML = `
            <div class="shortcuts-overlay"></div>
            <div class="shortcuts-content">
                <h2>⌨️ Atalhos de Teclado</h2>
                <div class="shortcuts-grid">
                    ${Object.entries(shortcuts).map(([key, data]) => `
                        <div class="shortcut-item">
                            <kbd>${key}</kbd>
                            <span>${data.description}</span>
                        </div>
                    `).join('')}
                </div>
                <p class="shortcuts-tip">Pressione <kbd>F1</kbd> ou <kbd>Escape</kbd> para fechar</p>
            </div>
        `;
        document.body.appendChild(modal);

        // Estilos
        const style = document.createElement('style');
        style.textContent = `
            #shortcuts-help {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 10000;
            }
            #shortcuts-help.visible {
                display: block;
            }
            .shortcuts-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.8);
            }
            .shortcuts-content {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: #1a1a1a;
                padding: 30px;
                border-radius: 16px;
                max-width: 600px;
                width: 90%;
                max-height: 80vh;
                overflow-y: auto;
                box-shadow: 0 10px 40px rgba(0,0,0,0.5);
            }
            .shortcuts-content h2 {
                color: #00d4ff;
                margin-bottom: 20px;
                text-align: center;
            }
            .shortcuts-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 15px;
            }
            .shortcut-item {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 10px;
                background: #222;
                border-radius: 8px;
            }
            .shortcut-item kbd {
                background: linear-gradient(135deg, #333, #222);
                color: #00d4ff;
                padding: 5px 12px;
                border-radius: 6px;
                font-family: monospace;
                font-weight: bold;
                min-width: 50px;
                text-align: center;
                border: 1px solid #444;
            }
            .shortcut-item span {
                color: #ccc;
                font-size: 14px;
            }
            .shortcuts-tip {
                text-align: center;
                color: #666;
                margin-top: 20px;
                font-size: 13px;
            }
            .shortcuts-tip kbd {
                background: #333;
                padding: 2px 8px;
                border-radius: 4px;
                font-size: 12px;
            }
        `;
        document.head.appendChild(style);

        return modal;
    }

    // Mostrar/esconder ajuda
    function toggleHelp() {
        const modal = document.getElementById('shortcuts-help') || createHelpModal();
        helpVisible = !helpVisible;
        modal.classList.toggle('visible', helpVisible);
    }

    // Executar ação do atalho
    function executeAction(action) {
        switch(action) {
            case 'help':
                toggleHelp();
                break;
            
            case 'search':
                const searchInput = document.querySelector('#productSearch, #product-search, #search, [name="search"], .search-input');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
                break;
            
            case 'barcode':
                const barcodeInput = document.querySelector('#productSearch, #barcode, [name="barcode"], .barcode-input');
                if (barcodeInput) {
                    barcodeInput.focus();
                    barcodeInput.value = '';
                }
                break;
            
            case 'quantity':
                const qtyInput = document.querySelector('#quantity, [name="quantity"], .qty-input');
                if (qtyInput) {
                    qtyInput.focus();
                    qtyInput.select();
                }
                break;
            
            case 'discount':
                const discountBtn = document.querySelector('#btn-discount, .btn-discount, [data-action="discount"]');
                if (discountBtn) discountBtn.click();
                break;
            
            case 'customer':
                const customerBtn = document.querySelector('#btn-customer, .btn-customer, [data-action="customer"]');
                if (customerBtn) customerBtn.click();
                break;
            
            case 'payment':
            case 'finalize':
                const payBtn = document.querySelector('#btnPay, #btn-payment, #btn-finalizar, .btn-payment, [data-action="payment"]');
                if (payBtn && !payBtn.disabled) payBtn.click();
                break;
            
            case 'cash':
                const cashBtn = document.querySelector('#btnCash, #btn-cash, .btn-cash, [data-method="cash"]');
                if (cashBtn) cashBtn.click();
                break;
            
            case 'card':
                const cardBtn = document.querySelector('#btnCard, #btn-card, .btn-card, [data-method="card"]');
                if (cardBtn) cardBtn.click();
                break;
            
            case 'cancel':
                if (helpVisible) {
                    toggleHelp();
                } else {
                    // Fechar qualquer modal aberto
                    const openModal = document.querySelector('.modal-overlay.active');
                    if (openModal) {
                        openModal.classList.remove('active');
                    } else {
                        const cancelBtn = document.querySelector('#btn-cancel, .btn-cancel, [data-action="cancel"]');
                        if (cancelBtn) cancelBtn.click();
                    }
                }
                break;
            
            case 'remove':
                const selectedItem = document.querySelector('.cart-item.selected, .item-selected');
                if (selectedItem) {
                    const removeBtn = selectedItem.querySelector('.btn-remove, [data-action="remove"]');
                    if (removeBtn) removeBtn.click();
                }
                break;
            
            case 'increase':
                adjustQuantity(1);
                break;
            
            case 'decrease':
                adjustQuantity(-1);
                break;
            
            case 'hold':
                const holdBtn = document.querySelector('#btn-hold, .btn-hold, [data-action="hold"]');
                if (holdBtn) holdBtn.click();
                break;
            
            case 'recall':
                const recallBtn = document.querySelector('#btn-recall, .btn-recall, [data-action="recall"]');
                if (recallBtn) recallBtn.click();
                break;
            
            default:
                console.log('Ação não implementada:', action);
        }
    }

    // Ajustar quantidade
    function adjustQuantity(delta) {
        const selectedItem = document.querySelector('.cart-item.selected, .item-selected');
        if (selectedItem) {
            const qtyInput = selectedItem.querySelector('input[type="number"], .item-qty');
            if (qtyInput) {
                const currentQty = parseInt(qtyInput.value) || 1;
                const newQty = Math.max(1, currentQty + delta);
                qtyInput.value = newQty;
                qtyInput.dispatchEvent(new Event('change'));
            }
        }
    }

    // Listener de teclas
    function handleKeydown(e) {
        // Ignorar se estiver em input de texto (exceto F keys)
        const isTextInput = ['INPUT', 'TEXTAREA'].includes(e.target.tagName) && 
                           e.target.type !== 'button' && 
                           e.target.type !== 'submit';
        
        const key = e.key;
        
        // Sempre permitir F keys e Escape
        if (shortcuts[key]) {
            // Verificar se é F key ou Escape
            if (key.startsWith('F') || key === 'Escape' || !isTextInput) {
                e.preventDefault();
                executeAction(shortcuts[key].action);
            }
        }
        
        // Atalhos com Ctrl
        if (e.ctrlKey) {
            switch(e.key.toLowerCase()) {
                case 's': // Ctrl+S - Guardar
                    e.preventDefault();
                    executeAction('hold');
                    break;
                case 'f': // Ctrl+F - Pesquisar
                    e.preventDefault();
                    executeAction('search');
                    break;
                case 'n': // Ctrl+N - Nova venda
                    e.preventDefault();
                    const newBtn = document.querySelector('#btn-new, .btn-new, [data-action="new"]');
                    if (newBtn) newBtn.click();
                    break;
                case 'p': // Ctrl+P - Imprimir
                    e.preventDefault();
                    const printBtn = document.querySelector('#btn-print, .btn-print, [data-action="print"]');
                    if (printBtn) printBtn.click();
                    break;
            }
        }
    }

    // Inicializar
    function init() {
        // Adicionar listener de teclas
        document.addEventListener('keydown', handleKeydown);
        
        // Criar modal de ajuda
        createHelpModal();
        
        // Mostrar indicador de atalhos
        const indicator = document.createElement('div');
        indicator.id = 'shortcuts-indicator';
        indicator.innerHTML = `
            <span style="cursor: pointer; padding: 8px 12px; background: rgba(0,212,255,0.1); border-radius: 8px; font-size: 12px; color: #00d4ff;" 
                  onclick="document.getElementById('shortcuts-help').classList.add('visible')">
                ⌨️ F1 para atalhos
            </span>
        `;
        indicator.style.cssText = 'position: fixed; bottom: 20px; right: 20px; z-index: 1000;';
        document.body.appendChild(indicator);
        
        console.log('PDV Shortcuts carregado. Pressione F1 para ver atalhos.');
    }

    // Iniciar quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
