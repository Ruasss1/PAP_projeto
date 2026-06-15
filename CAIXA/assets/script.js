/**
 * SuperMarket POS - Professional JavaScript
 */

// Estado global
let cart = [];
let currentCustomer = null;
let currentDiscount = 0;
let currentDiscountPercent = 0;
let currentWeight = '0';
let currentProduct = null;
let lastSaleData = null;
let activeCategory = '';

// Função para mudar de loja no POS
function changePosStore(storeId) {
    fetch('/api/change-store.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ store_id: storeId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Limpar carrinho ao mudar de loja
            cart = [];
            updateCart();
            
            // Recarregar produtos da nova loja
            loadAllProducts();
            
            // Mostrar notificação
            showToast(`Loja alterada para: ${data.store.name}`, 'success');
        } else {
            showToast('Erro ao mudar de loja: ' + data.error, 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showToast('Erro ao mudar de loja', 'error');
    });
}

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    console.log('🛒 SuperMarket POS iniciado');
    
    // Event listeners
    const searchInput = document.getElementById('productSearch');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(() => searchProducts(), 300));
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const val = this.value.trim();
                if (val.length >= 8 && /^\d+$/.test(val)) {
                    searchByBarcode(val);
                    this.value = '';
                }
            }
        });
    }
    
    // Categorias
    document.querySelectorAll('.cat-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            activeCategory = this.dataset.category;
            searchProducts();
        });
    });
    
    // Carregar produtos
    loadAllProducts();
});

function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// ============== PRODUTOS ==============

function searchProducts() {
    const term = document.getElementById('productSearch')?.value || '';
    
    const formData = new FormData();
    formData.append('action', 'search_product');
    formData.append('search', term);
    formData.append('category', activeCategory);
    
    fetch('api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                displayProducts(data.products);
            } else {
                displayProducts([]);
            }
        })
        .catch(() => displayProducts([]));
}

function loadAllProducts() {
    searchProducts('');
}

function displayProducts(products) {
    const grid = document.getElementById('productsGrid');
    
    if (!products || products.length === 0) {
        grid.innerHTML = `
            <div class="loading-state">
                <p>Nenhum produto encontrado</p>
            </div>
        `;
        return;
    }
    
    grid.innerHTML = products.map(p => {
        const stock = parseInt(p.stock || 0);
        const price = parseFloat(p.sell_price || 0);
        const stockClass = stock <= 0 ? 'out' : (stock < 10 ? 'low' : '');
        const noStock = stock <= 0 ? 'no-stock' : '';
        
        return `
            <div class="product-card ${noStock}" onclick='addToCart(${JSON.stringify(p).replace(/'/g, "&#39;")})'>
                <div class="product-icon">${getCategoryIcon(p.category)}</div>
                <div class="product-name">${escapeHtml(p.name)}</div>
                <div class="product-price">€${price.toFixed(2)}</div>
                <div class="product-stock ${stockClass}">Stock: ${stock}</div>
            </div>
        `;
    }).join('');
}

function getCategoryIcon(category) {
    const icons = {
        'Frutas':    { c: '#e17055', p: 'M12 2C6 2 4 7 4 12s3 10 8 10 8-5 8-10S18 2 12 2z M12 2c0 0 2-1 4 1' },
        'Legumes':   { c: '#00b894', p: 'M12 22V12m0 0C12 7 7 4 3 6c0 4 3 8 9 6zm0 0c0-5 5-8 9-6-1 4-4 8-9 6z' },
        'Carnes':    { c: '#d63031', p: 'M14.5 9.5L9.5 14.5m0 0L7 17m2.5-2.5L12 17.5M14.5 6.5a5 5 0 11-7 7L5 16a2 2 0 010-2.83l8.83-8.83A2 2 0 0116.66 5L14.5 6.5z' },
        'Peixe':     { c: '#0984e3', p: 'M2 12c2-5 7-8 12-7 2 3 2 7 0 10-5 1-10-2-12-3zm10-3a1 1 0 100 2 1 1 0 000-2z M22 8l-3 4 3 4' },
        'Laticínios':{ c: '#fdcb6e', p: 'M5 8l1 12h12L19 8m0 0H5m14 0l-1-3H6L5 8m3-3h8' },
        'Bebidas':   { c: '#6c5ce7', p: 'M9 3h6l2 5H7L9 3zm-2 5h10l-1.5 10a1 1 0 01-1 .9H9.5a1 1 0 01-1-.9L7 8z' },
        'Padaria':   { c: '#e17055', p: 'M4 13c0-4.4 3.6-8 8-8s8 3.6 8 8v1H4v-1zm-1 3h18v1a2 2 0 01-2 2H5a2 2 0 01-2-2v-1z' },
        'Congelados':{ c: '#74b9ff', p: 'M12 2v20M2 12h20M4.93 4.93l14.14 14.14M19.07 4.93L4.93 19.07' },
        'Higiene':   { c: '#00cec9', p: 'M7 3h10l1 5H6L7 3zm0 5l-1 13h12L17 8M10 8v6m4-6v6' },
        'Limpeza':   { c: '#55efc4', p: 'M9.5 3l-5 8h4l-1 10h9l-1-10h4l-5-8H9.5z' },
        'Mercearia': { c: '#636e72', p: 'M4 6h16v2H4V6zm0 4h16v2H4v-2zm0 4h16v2H4v-2z' }
    };
    const def = { c: '#b2bec3', p: 'M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2zM16 3H8L4 7h16l-4-4z' };
    const e = icons[category] || def;
    return `<span style="display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;background:${e.c}22;color:${e.c}"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">${e.p.split(' M').map((seg,i)=>`<path d="${i===0?seg:'M'+seg}"/>`).join('')}</svg></span>`;
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ============== CARRINHO ==============

function addToCart(product) {
    const stock = parseInt(product.stock || 0);
    if (stock <= 0) {
        showToast('Produto sem stock!', 'error');
        return;
    }
    
    // Produtos que precisam pesar
    const weighted = (typeof WEIGHTED_CATEGORIES !== 'undefined') ? WEIGHTED_CATEGORIES : ['Frutas', 'Legumes', 'Carnes', 'Peixe', 'Congelados'];
    if (weighted.includes(product.category)) {
        showWeightModal(product);
        return;
    }
    
    // Verificar se já existe
    const idx = cart.findIndex(i => i.product_id === product.id && !i.is_weighted);
    
    if (idx >= 0) {
        if (cart[idx].quantity < stock) {
            cart[idx].quantity++;
            cart[idx].subtotal = cart[idx].quantity * cart[idx].unit_price;
        } else {
            showToast('Stock insuficiente!', 'warning');
            return;
        }
    } else {
        cart.push({
            product_id: product.id,
            product_name: product.name,
            product_sku: product.sku || product.barcode || '',
            unit_price: parseFloat(product.sell_price || 0),
            quantity: 1,
            subtotal: parseFloat(product.sell_price || 0),
            is_weighted: 0,
            weight_kg: null,
            max_stock: stock
        });
    }
    
    updateCart();
    playBeep();
}

function showWeightModal(product) {
    currentProduct = product;
    currentWeight = '0';
    document.getElementById('weightDisplay').innerHTML = '0.000 <small>kg</small>';
    document.getElementById('weightProductName').textContent = product.name;
    openModal('weightModal');
}

function addWeightDigit(d) {
    if (d === '.' && currentWeight.includes('.')) return;
    if (currentWeight === '0' && d !== '.') {
        currentWeight = d;
    } else {
        currentWeight += d;
    }
    updateWeightDisplay();
}

function clearWeight() {
    currentWeight = '0';
    updateWeightDisplay();
}

function updateWeightDisplay() {
    const w = parseFloat(currentWeight) || 0;
    document.getElementById('weightDisplay').innerHTML = w.toFixed(3) + ' <small>kg</small>';
}

function confirmWeight() {
    const weight = parseFloat(currentWeight);
    
    if (weight <= 0) {
        showToast('Digite um peso válido!', 'warning');
        return;
    }
    
    if (weight > 50) {
        showToast('Peso muito alto! Max: 50kg', 'warning');
        return;
    }
    
    const price = parseFloat(currentProduct.sell_price || 0);
    
    cart.push({
        product_id: currentProduct.id,
        product_name: `${currentProduct.name} (${weight.toFixed(3)}kg)`,
        product_sku: currentProduct.sku || currentProduct.barcode || '',
        unit_price: price,
        quantity: weight,
        subtotal: weight * price,
        is_weighted: 1,
        weight_kg: weight,
        max_stock: 999
    });
    
    updateCart();
    closeModal('weightModal');
    playBeep();
}

function updateCart() {
    const itemsDiv = document.getElementById('cartItems');
    const countSpan = document.getElementById('itemCount');
    const subtotalSpan = document.getElementById('subtotalValue');
    const totalSpan = document.getElementById('totalValue');
    const btnPay = document.getElementById('btnPay');
    const btnSuspend = document.getElementById('btnSuspend');
    
    if (cart.length === 0) {
        itemsDiv.innerHTML = `
            <div class="empty-cart">
                <svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto 10px;display:block;opacity:0.2"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <p>Carrinho vazio</p>
                <span>Adicione produtos para começar</span>
            </div>
        `;
        countSpan.textContent = '0 itens';
        subtotalSpan.textContent = '€0.00';
        totalSpan.textContent = '€0.00';
        btnPay.disabled = true;
        btnSuspend.disabled = true;
        return;
    }
    
    itemsDiv.innerHTML = cart.map((item, i) => `
        <div class="cart-item">
            <div class="item-info">
                <div class="item-name">${escapeHtml(item.product_name)}</div>
                <div class="item-price">€${item.unit_price.toFixed(2)} ${item.is_weighted ? '/kg' : 'un'}</div>
            </div>
            <div class="item-controls">
                ${!item.is_weighted ? `
                    <button class="qty-btn" onclick="changeQty(${i}, -1)" title="Diminuir">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </button>
                    <div class="qty-display">${item.quantity}</div>
                    <button class="qty-btn" onclick="changeQty(${i}, 1)" title="Aumentar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </button>
                ` : `
                    <div class="qty-display">${item.quantity.toFixed(3)}<span style="font-size:9px;opacity:.6;margin-left:1px">kg</span></div>
                `}
                <div class="item-subtotal">€${item.subtotal.toFixed(2)}</div>
                <button class="remove-btn" onclick="removeItem(${i})" title="Remover">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                </button>
            </div>
        </div>
    `).join('');
    
    const subtotal = cart.reduce((s, i) => s + i.subtotal, 0);
    // Recalculate discount based on current percent
    if (currentDiscountPercent > 0) {
        currentDiscount = (subtotal * currentDiscountPercent) / 100;
    }
    const total = subtotal - currentDiscount;
    
    countSpan.textContent = cart.length + ' ' + (cart.length === 1 ? 'item' : 'itens');
    subtotalSpan.textContent = '€' + subtotal.toFixed(2);
    totalSpan.textContent = '€' + total.toFixed(2);
    
    // Show/hide discount row
    const discountRow = document.getElementById('discountRow');
    const discountValue = document.getElementById('discountValue');
    if (discountRow && discountValue) {
        if (currentDiscount > 0) {
            discountRow.style.display = 'flex';
            discountValue.textContent = '-€' + currentDiscount.toFixed(2);
        } else {
            discountRow.style.display = 'none';
        }
    }
    
    btnPay.disabled = false;
    btnSuspend.disabled = false;
}

function changeQty(index, delta) {
    const item = cart[index];
    const newQty = item.quantity + delta;
    
    if (newQty <= 0) {
        removeItem(index);
        return;
    }
    
    if (newQty > item.max_stock) {
        showToast('Stock insuficiente!', 'warning');
        return;
    }
    
    item.quantity = newQty;
    item.subtotal = item.quantity * item.unit_price;
    updateCart();
}

function removeItem(index) {
    cart.splice(index, 1);
    updateCart();
}

function clearCart() {
    if (cart.length === 0) return;
    if (confirm('Limpar todo o carrinho?')) {
        cart = [];
        currentCustomer = null;
        currentDiscount = 0;
        updateCart();
    }
}

// ============== DESCONTO ==============

function showDiscountModal() {
    const manual = document.getElementById('manualDiscount');
    if (manual) manual.value = currentDiscountPercent || '';
    // Clear promo selections
    document.querySelectorAll('.promo-item').forEach(el => el.classList.remove('selected'));
    openModal('discountModal');
}

function selectPromo(el, percent, name) {
    document.querySelectorAll('.promo-item').forEach(e => e.classList.remove('selected'));
    el.classList.add('selected');
    const manual = document.getElementById('manualDiscount');
    if (manual) manual.value = percent;
}

function clearPromoSelection() {
    document.querySelectorAll('.promo-item').forEach(e => e.classList.remove('selected'));
}

function applyDiscount() {
    const pct = parseFloat(document.getElementById('manualDiscount')?.value || 0);
    if (pct < 0 || pct > 100) {
        showToast('Desconto deve ser entre 0% e 100%', 'warning');
        return;
    }
    const subtotal = cart.reduce((s, i) => s + i.subtotal, 0);
    currentDiscountPercent = pct;
    currentDiscount = (subtotal * pct) / 100;
    closeModal('discountModal');
    updateCart();
    if (pct > 0) {
        showToast(`Desconto de ${pct}% aplicado`, 'success');
        const btn = document.getElementById('btnDiscount');
        if (btn) btn.classList.add('active');
        const lbl = document.getElementById('discountLabel');
        if (lbl) lbl.textContent = `-${pct}%`;
    }
}

function removeDiscount() {
    currentDiscount = 0;
    currentDiscountPercent = 0;
    closeModal('discountModal');
    updateCart();
    const btn = document.getElementById('btnDiscount');
    if (btn) btn.classList.remove('active');
    const lbl = document.getElementById('discountLabel');
    if (lbl) lbl.textContent = 'Aplicar';
}

// ============== PAGAMENTO ==============

function showPaymentModal() {
    if (cart.length === 0) {
        showToast('Carrinho vazio!', 'error');
        return;
    }
    
    // Preencher resumo
    const itemsDiv = document.getElementById('paymentItems');
    itemsDiv.innerHTML = cart.map(i => `
        <div class="payment-item">
            <span class="payment-item-name">${escapeHtml(i.product_name)}</span>
            <span class="payment-item-qty">x${i.is_weighted ? i.quantity.toFixed(3) + 'kg' : i.quantity}</span>
            <span class="payment-item-price">€${i.subtotal.toFixed(2).replace('.', ',')}</span>
        </div>
    `).join('');

    const total = cart.reduce((s, i) => s + i.subtotal, 0) - currentDiscount;
    document.getElementById('paymentTotal').textContent = '€' + total.toFixed(2).replace('.', ',');

    // Quick amounts
    generateQuickAmounts(total);

    // Reset
    document.getElementById('amountPaid').value = '';
    document.getElementById('changeValue').textContent = '€0,00';
    document.getElementById('paymentError').style.display = 'none';
    document.getElementById('btnConfirmPayment').disabled = true;
    
    openModal('paymentModal');
    setTimeout(() => document.getElementById('amountPaid').focus(), 100);
}

function generateQuickAmounts(total) {
    const container = document.getElementById('quickAmounts');
    const amounts = [5, 10, 20, 50, 100].filter(a => a >= total);

    let html = amounts.slice(0, 3).map(a =>
        `<button class="quick-btn" onclick="setQuickAmount(${a})">€${a}</button>`
    ).join('');

    html += `<button class="quick-btn exact" onclick="setExactAmount()">Exacto</button>`;
    container.innerHTML = html;
}

function setQuickAmount(amount) {
    document.getElementById('amountPaid').value = amount.toFixed(2);
    calculateChange();
}

function setExactAmount() {
    const total = cart.reduce((s, i) => s + i.subtotal, 0) - currentDiscount;
    document.getElementById('amountPaid').value = total.toFixed(2);
    calculateChange();
}

function calculateChange() {
    const paid = parseFloat(document.getElementById('amountPaid').value) || 0;
    const total = cart.reduce((s, i) => s + i.subtotal, 0) - currentDiscount;
    const change = paid - total;
    
    const changeBox = document.getElementById('changeBox');
    const changeValue = document.getElementById('changeValue');
    const errorBox = document.getElementById('paymentError');
    const btnConfirm = document.getElementById('btnConfirmPayment');
    
    if (paid === 0) {
        changeValue.textContent = '€0,00';
        changeBox.style.removeProperty('background');
        changeBox.style.removeProperty('border-color');
        errorBox.style.display = 'none';
        btnConfirm.disabled = true;
    } else if (change < 0) {
        changeValue.textContent = '-€' + Math.abs(change).toFixed(2).replace('.', ',');
        changeBox.style.background = 'var(--danger-subtle)';
        changeBox.style.borderColor = 'var(--danger)';
        changeBox.querySelector('.label').style.color = 'var(--danger)';
        changeBox.querySelector('.value').style.color = 'var(--danger)';
        errorBox.style.display = 'block';
        btnConfirm.disabled = true;
    } else {
        changeValue.textContent = '€' + change.toFixed(2).replace('.', ',');
        changeBox.style.background = 'var(--success-subtle)';
        changeBox.style.borderColor = 'var(--success)';
        changeBox.querySelector('.label').style.color = 'var(--success)';
        changeBox.querySelector('.value').style.color = 'var(--success)';
        errorBox.style.display = 'none';
        btnConfirm.disabled = false;
    }
}

function confirmPayment() {
    const paid = parseFloat(document.getElementById('amountPaid').value) || 0;
    const total = cart.reduce((s, i) => s + i.subtotal, 0) - currentDiscount;
    
    if (paid < total) {
        showToast('Valor insuficiente!', 'error');
        return;
    }
    
    const change = paid - total;
    
    // Preparar dados
    const formData = new FormData();
    formData.append('action', 'process_sale');
    formData.append('items', JSON.stringify(cart));
    formData.append('customer_id', currentCustomer ? currentCustomer.id : '');
    formData.append('discount_amount', currentDiscount);
    formData.append('payment_method', 'cash');
    formData.append('payment_details', JSON.stringify({ amount_paid: paid, change: change }));
    
    // Desabilitar botão
    const btn = document.getElementById('btnConfirmPayment');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> A processar…';
    
    fetch('api.php', { method: 'POST', body: formData })
        .then(async (r) => {
            const text = await r.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Resposta inválida do servidor: ' + text.slice(0, 200));
            }
        })
        .then(data => {
            if (data.success) {
                // Guardar dados para recibo
                lastSaleData = {
                    sale_id: data.sale_id,
                    receipt_number: data.receipt_number,
                    total: total,
                    paid: paid,
                    change: change,
                    items: [...cart],
                    points: data.points_earned
                };
                
                // Notificar outras abas/windows que uma venda foi feita (broadcast)
                if (window.BroadcastChannel) {
                    const bc = new BroadcastChannel('sales-channel');
                    bc.postMessage({ type: 'sale_completed', receipt: data.receipt_number });
                    bc.close();
                }
                
                closeModal('paymentModal');
                showReceiptModal();

                // Aviso no canto quando forem criadas encomendas automáticas
                if (data.auto_orders && Number(data.auto_orders.orders_created || 0) > 0) {
                    const o = Number(data.auto_orders.orders_created || 0);
                    const p = Number(data.auto_orders.items_created || 0);
                    showToast(`Encomenda automática criada (${o} encomenda(s), ${p} produto(s)).`, 'warning');
                }
            } else {
                showToast('Erro: ' + data.message, 'error');
                btn.disabled = false;
                btn.innerHTML = '✓ Finalizar Venda';
            }
        })
        .catch(err => {
            console.error('Erro ao processar venda:', err);
            showToast('Erro de conexão!', 'error');
            btn.disabled = false;
            btn.innerHTML = '✓ Finalizar Venda';
        });
}

// ============== RECIBO ==============

function showReceiptModal() {
    document.getElementById('receiptNumber').textContent = lastSaleData.receipt_number;
    document.getElementById('receiptTotal').textContent = '€' + lastSaleData.total.toFixed(2);
    const receiptPaidEl = document.getElementById('receiptPaid');
    if (receiptPaidEl) {
        receiptPaidEl.textContent = '€' + lastSaleData.paid.toFixed(2);
    }
    document.getElementById('receiptChange').textContent = '€' + lastSaleData.change.toFixed(2);
    
    // Reset NIF
    const wantNifEl = document.getElementById('wantNif');
    const nifInputEl = document.getElementById('nifInput');
    if (wantNifEl) wantNifEl.checked = false;
    if (nifInputEl) {
        nifInputEl.style.display = 'none';
        nifInputEl.value = '';
    }
    
    openModal('receiptModal');
}

function toggleNifInput() {
    const nifInput = document.getElementById('nifInput');
    nifInput.style.display = document.getElementById('wantNif').checked ? 'block' : 'none';
    if (document.getElementById('wantNif').checked) {
        nifInput.focus();
    }
}

function finishWithoutReceipt() {
    closeModal('receiptModal');
    resetForNextSale();
    showToast('Venda concluída!', 'success');
}

function printReceipt() {
    if (!lastSaleData || !lastSaleData.sale_id) {
        showToast('Dados da venda não disponíveis.', 'error');
        return;
    }
    const url = '/modules/recibo_print.php?id=' + lastSaleData.sale_id + '&autoprint=1';
    const popup = window.open(url, 'recibo_print', 'width=520,height=800,scrollbars=yes,resizable=yes');
    if (!popup) {
        // Se popup bloqueado, abrir em nova tab
        window.open(url, '_blank');
    }
    closeModal('receiptModal');
    resetForNextSale();
}

function doPrint() {
    window.print();
}

function closePrintAndReset() {
    closeModal('printModal');
    resetForNextSale();
}

function resetForNextSale() {
    cart = [];
    currentCustomer = null;
    currentDiscount = 0;
    lastSaleData = null;
    updateCart();
    loadAllProducts();
    document.getElementById('productSearch').value = '';
    document.getElementById('productSearch').focus();
}

// ============== SUSPENDER/RETOMAR ==============

function suspendSale() {
    if (cart.length === 0) {
        showToast('Carrinho vazio!', 'error');
        return;
    }
    
    const notes = prompt('Motivo da suspensão (opcional):') || '';
    
    const formData = new FormData();
    formData.append('action', 'suspend_sale');
    formData.append('items', JSON.stringify(cart));
    formData.append('customer_id', currentCustomer ? currentCustomer.id : '');
    formData.append('notes', notes);
    
    fetch('api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Venda suspensa: ' + data.suspension_code, 'success');
                cart = [];
                updateCart();
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Erro: ' + data.message, 'error');
            }
        });
}

function resumeSale(code) {
    const formData = new FormData();
    formData.append('action', 'resume_sale');
    formData.append('code', code);
    
    fetch('api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                cart = data.items || [];
                updateCart();
                showToast('Venda retomada!', 'success');
                setTimeout(() => location.reload(), 500);
            } else {
                showToast('Erro: ' + data.message, 'error');
            }
        });
}

// ============== CÓDIGO DE BARRAS ==============

function scanBarcode() {
    const code = prompt('Introduza o código de barras:');
    if (!code) return;
    searchByBarcode(code.trim());
}

function searchByBarcode(code) {
    const formData = new FormData();
    formData.append('action', 'get_product_barcode');
    formData.append('barcode', code);
    
    fetch('api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.product) {
                addToCart(data.product);
            } else {
                showToast('Produto não encontrado: ' + code, 'error');
            }
        });
}

// ============== TURNO ==============

function openShift() {
    const balance = parseFloat(document.getElementById('openingBalance').value) || 0;
    const notes = document.getElementById('shiftNotes')?.value || '';
    
    const formData = new FormData();
    formData.append('action', 'open_shift');
    formData.append('opening_balance', balance);
    formData.append('notes', notes);
    
    fetch('api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Turno iniciado!', 'success');
                setTimeout(() => location.reload(), 500);
            } else {
                showToast('Erro: ' + data.message, 'error');
            }
        });
}

// ============== UTILITÁRIOS ==============

function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

function showToast(message, type = 'info') {
    // Criar toast se não existir container
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;';
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    toast.style.cssText = `
        background: ${type === 'error' ? '#ef4444' : type === 'warning' ? '#f59e0b' : type === 'success' ? '#22c55e' : '#a1a1aa'};
        color: white;
        padding: 14px 20px;
        border-radius: 8px;
        margin-bottom: 10px;
        font-weight: 500;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        animation: slideIn 0.2s ease;
    `;
    toast.textContent = message;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.2s ease';
        setTimeout(() => toast.remove(), 200);
    }, 3000);
}

function playBeep() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.value = 800;
        gain.gain.value = 0.1;
        osc.start();
        osc.stop(ctx.currentTime + 0.1);
    } catch (e) {}
}

// CSS para animações toast
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } }
    @keyframes slideOut { to { transform: translateX(100%); opacity: 0; } }
`;
document.head.appendChild(style);
