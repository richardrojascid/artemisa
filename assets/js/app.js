/**
 * Artemisa Salón de Té — Aplicación para meseros
 */
(() => {
    const STORAGE_KEY = 'artemisa_comanda_settings';
    const CART_KEY_PREFIX = 'artemisa_comanda_cart';
    const SESSIONS_STORE = 'artemisa_sessions_store';

    let menu = [];
    let cart = [];
    let currentItem = null;
    let editingCartIndex = -1;
    let selectedCategoryId = null;
    let selectedSize = 'simple';
    let tipPercent = window.APP_TIP_PERCENT || 10;
    let orderSession = {
        id: null,
        orderType: 'servir',
        tableNumber: '',
        staffName: '',
        clientName: '',
        confirmed: false,
    };
    let reprintState = { query: '', page: 1, pages: 1 };
    let reprintOrdersCache = [];

    const $ = (sel) => document.querySelector(sel);

    const els = {
        categoryTabs: $('#categoryTabs'),
        menuGrid: $('#menuGrid'),
        cartPanel: $('#cartPanel'),
        cartToggle: $('#cartToggle'),
        cartContent: $('#cartContent'),
        cartCount: $('#cartCount'),
        cartTotal: $('#cartTotal'),
        cartSubtotal: $('#cartSubtotal'),
        cartTip: $('#cartTip'),
        usePercentTip: $('#usePercentTip'),
        manualTip: $('#manualTip'),
        tipPercentLabel: $('#tipPercentLabel'),
        cartTotalPreview: $('#cartTotalPreview'),
        cartItems: $('#cartItems'),
        closeCart: $('#closeCart'),
        btnClearCart: $('#btnClearCart'),
        btnSendOrder: $('#btnSendOrder'),
        itemModal: $('#itemModal'),
        itemForm: $('#itemForm'),
        modalItemName: $('#modalItemName'),
        modalItemDesc: $('#modalItemDesc'),
        modalItemPrice: $('#modalItemPrice'),
        sizeGroup: $('#sizeGroup'),
        sizeToggle: $('#sizeToggle'),
        itemQuantity: $('#itemQuantity'),
        qtyMinus: $('#qtyMinus'),
        qtyPlus: $('#qtyPlus'),
        ingredientsGroup: $('#ingredientsGroup'),
        ingredientsList: $('#ingredientsList'),
        extrasGroup: $('#extrasGroup'),
        extrasList: $('#extrasList'),
        itemNotes: $('#itemNotes'),
        modalLineTotal: $('#modalLineTotal'),
        modalClose: $('#modalClose'),
        modalCancel: $('#modalCancel'),
        settingsModal: $('#settingsModal'),
        btnSettings: $('#btnSettings'),
        settingsClose: $('#settingsClose'),
        printerMode: $('#printerMode'),
        btnConnectPrinter: $('#btnConnectPrinter'),
        printerStatus: $('#printerStatus'),
        sessionModal: $('#sessionModal'),
        sessionForm: $('#sessionForm'),
        sessionFields: $('#sessionFields'),
        sessionStaffServir: $('#sessionStaffServir'),
        sessionStaffLlevar: $('#sessionStaffLlevar'),
        sessionTable: $('#sessionTable'),
        sessionWaiter: $('#sessionWaiter'),
        sessionCashier: $('#sessionCashier'),
        sessionError: $('#sessionError'),
        orderSessionBar: $('#orderSessionBar'),
        orderSessionSummary: $('#orderSessionSummary'),
        btnEditSession: $('#btnEditSession'),
        btnNewSession: $('#btnNewSession'),
        sessionClientName: $('#sessionClientName'),
        btnReprintOrders: $('#btnReprintOrders'),
        reprintModal: $('#reprintModal'),
        reprintClose: $('#reprintClose'),
        reprintSearchForm: $('#reprintSearchForm'),
        reprintSearch: $('#reprintSearch'),
        reprintList: $('#reprintList'),
        reprintPagination: $('#reprintPagination'),
    };

    const fetchOpts = { credentials: 'same-origin' };

    function loadSettings() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {};
        } catch {
            return {};
        }
    }

    function saveSettings(settings) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
    }

    function getCartKey() {
        return orderSession.id ? `${CART_KEY_PREFIX}_${orderSession.id}` : CART_KEY_PREFIX;
    }

    function loadCart() {
        try {
            cart = JSON.parse(localStorage.getItem(getCartKey())) || [];
        } catch {
            cart = [];
        }
    }

    function saveCart() {
        localStorage.setItem(getCartKey(), JSON.stringify(cart));
    }

    function createSessionId() {
        return `s_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
    }

    function getSessionsStore() {
        try {
            const store = JSON.parse(localStorage.getItem(SESSIONS_STORE));
            if (store && typeof store === 'object' && store.sessions) {
                return store;
            }
        } catch {
            /* ignore */
        }
        return { activeId: null, sessions: {} };
    }

    function saveSessionsStore(store) {
        localStorage.setItem(SESSIONS_STORE, JSON.stringify(store));
    }

    function sessionLabel(data) {
        const type = data.orderType === 'llevar' ? 'Llevar' : `Mesa ${data.tableNumber || '?'}`;
        const staff = data.staffName ? ` · ${data.staffName}` : '';
        const client = data.clientName ? ` · ${data.clientName}` : '';
        return `${type}${staff}${client}`;
    }

    function syncOrderSessionFromStore() {
        const store = getSessionsStore();
        const id = store.activeId;
        if (!id || !store.sessions[id]) {
            orderSession = {
                id: null,
                orderType: 'servir',
                tableNumber: '',
                staffName: '',
                clientName: '',
                confirmed: false,
            };
            return false;
        }
        const data = store.sessions[id];
        orderSession = {
            id,
            orderType: data.orderType === 'llevar' ? 'llevar' : 'servir',
            tableNumber: data.tableNumber || '',
            staffName: data.staffName || '',
            clientName: data.clientName || '',
            confirmed: Boolean(data.confirmed),
        };
        return true;
    }

    function persistActiveSession() {
        if (!orderSession.id) return;
        const store = getSessionsStore();
        store.activeId = orderSession.id;
        store.sessions[orderSession.id] = {
            orderType: orderSession.orderType,
            tableNumber: orderSession.tableNumber,
            staffName: orderSession.staffName,
            clientName: orderSession.clientName,
            confirmed: orderSession.confirmed,
            updatedAt: new Date().toISOString(),
        };
        saveSessionsStore(store);
    }

    function createNewSession(openModal = true) {
        const id = createSessionId();
        orderSession = {
            id,
            orderType: 'servir',
            tableNumber: '',
            staffName: '',
            clientName: '',
            confirmed: false,
        };
        const store = getSessionsStore();
        store.activeId = id;
        store.sessions[id] = {
            orderType: 'servir',
            tableNumber: '',
            staffName: '',
            clientName: '',
            confirmed: false,
            updatedAt: new Date().toISOString(),
        };
        saveSessionsStore(store);
        cart = [];
        saveCart();
        if (openModal) {
            openSessionModal();
        }
    }

    function switchSession(sessionId) {
        if (!sessionId) return;
        const store = getSessionsStore();
        if (!store.sessions[sessionId]) return;
        store.activeId = sessionId;
        saveSessionsStore(store);
        syncOrderSessionFromStore();
        loadCart();
        renderCart();
        renderSessionSummary();
    }

    function formatMenuItemName(item) {
        const num = item.sort_order || 0;
        return num > 0 ? `${num}. ${item.name}` : item.name;
    }

    function formatReceiptPreview(order) {
        const cafeName = (window.APP_CAFE_NAME || 'Artemisa Salón de Té').toUpperCase();
        const lines = [];
        lines.push(cafeName);
        lines.push('COMANDA DE PEDIDO');
        if (order.id) {
            lines.push(`Comanda #${order.id}`);
        }
        lines.push('-'.repeat(32));
        lines.push(`Fecha: ${formatOrderDateTime(order.created_at)}`);

        if (order.client_name) {
            lines.push(`Cliente: ${order.client_name}`);
        }

        const isTakeaway = order.order_type === 'llevar'
            || String(order.table_number || '').toUpperCase() === 'PL';
        if (order.table_number) {
            lines.push(isTakeaway ? 'PARA LLEVAR (PL)' : `Mesa: ${order.table_number}`);
        }
        if (order.waiter_name) {
            lines.push(`${isTakeaway ? 'Cajero' : 'Mesero'}: ${order.waiter_name}`);
        }

        lines.push('-'.repeat(32));

        (order.items || []).forEach((item) => {
            const qty = item.quantity || 1;
            const name = item.item_name || item.name || 'Producto';
            lines.push(`${qty}x ${name}`);
            lines.push(`   Precio unit.: ${formatMoney(item.unit_price || 0)}`);

            const removed = item.removed_ingredients || [];
            if (removed.length) {
                lines.push(`   Sin: ${removed.join(', ')}`);
            }

            (item.added_extras || []).forEach((extra) => {
                const extraName = extra.name || extra;
                const extraPrice = extra.price ? ` (+${formatMoney(extra.price)})` : '';
                lines.push(`   + ${extraName}${extraPrice}`);
            });

            if (item.notes) {
                lines.push(`   Nota: ${item.notes}`);
            }

            lines.push(`   Subtotal: ${formatMoney(item.line_total || 0)}`);
            lines.push('');
        });

        lines.push('-'.repeat(32));
        const subtotal = order.subtotal ?? order.total ?? 0;
        const tipAmount = order.tip_amount || 0;
        lines.push(`Subtotal productos: ${formatMoney(subtotal)}`);
        if (tipAmount > 0) {
            const tipPct = subtotal > 0 ? Math.round((tipAmount / subtotal) * 100) : tipPercent;
            lines.push(`Propina ${tipPct}%: ${formatMoney(tipAmount)}`);
        }
        lines.push(`TOTAL: ${formatMoney(order.total || 0)}`);
        lines.push('-'.repeat(32));
        lines.push('Gracias por su preferencia');

        return lines.join('\n');
    }

    function formatOrderDateTime(value) {
        if (!value) return '';
        const normalized = String(value).replace(' ', 'T');
        const date = new Date(normalized);
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleString('es-CL', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function formatMoney(amount) {
        return '$' + Math.round(Number(amount)).toLocaleString('es-CL');
    }

    function getCartSubtotal() {
        return cart.reduce((sum, item) => sum + item.line_total, 0);
    }

    function getCartTip() {
        const manualRaw = els.manualTip?.value.trim() ?? '';
        if (manualRaw !== '') {
            return Math.max(0, parseInt(manualRaw, 10) || 0);
        }
        if (els.usePercentTip?.checked) {
            return Math.round(getCartSubtotal() * (tipPercent / 100));
        }
        return 0;
    }

    function getTipMode() {
        const manualRaw = els.manualTip?.value.trim() ?? '';
        if (manualRaw !== '') return 'manual';
        if (els.usePercentTip?.checked) return 'percent';
        return 'none';
    }

    function getCartTotal() {
        return getCartSubtotal() + getCartTip();
    }

    function getCartCount() {
        return cart.reduce((sum, item) => sum + item.quantity, 0);
    }

    function getUnitPrice(item, size = 'simple') {
        if (size === 'doble' && item.price_double != null) {
            return item.price_double;
        }
        return item.price;
    }

    function formatItemPrice(item) {
        if (item.price_double != null) {
            return `${formatMoney(item.price)} / ${formatMoney(item.price_double)}`;
        }
        return formatMoney(item.price);
    }

    function showToast(message, type = 'success') {
        const existing = $('.toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = `toast ${type} show`;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    async function apiFetch(url, options = {}) {
        const res = await fetch(url, { ...fetchOpts, ...options });
        if (res.status === 401) {
            window.location.href = 'login.php';
            throw new Error('Sesión expirada');
        }
        return res;
    }

    async function loadMenu() {
        els.menuGrid.innerHTML = '<p class="loading">Cargando carta...</p>';

        try {
            const res = await apiFetch('api/menu.php');
            const data = await res.json();

            if (!data.success) {
                throw new Error(data.error || 'Error al cargar menú');
            }

            menu = data.categories;
            if (data.tip_percent) {
                tipPercent = data.tip_percent;
                if (els.tipPercentLabel) els.tipPercentLabel.textContent = tipPercent;
            }
            if (menu.length > 0) {
                const defaultCategory = menu.find(c => c.name === 'Cafés') ?? menu[0];
                selectedCategoryId = defaultCategory.id;
            }
            renderCategoryTabs();
            if (menu.length > 0) {
                renderMenu();
            }
        } catch (err) {
            els.menuGrid.innerHTML = `<p class="loading">${err.message}</p>`;
        }
    }

    function renderCategoryTabs() {
        els.categoryTabs.innerHTML = menu.map(cat => `
            <button type="button" class="tab-btn ${cat.id === selectedCategoryId ? 'active' : ''}"
                    data-category="${cat.id}" role="tab"
                    aria-selected="${cat.id === selectedCategoryId}">
                ${escapeHtml(cat.name)}
            </button>
        `).join('');

        els.categoryTabs.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                selectedCategoryId = parseInt(btn.dataset.category, 10);
                renderCategoryTabs();
                renderMenu();
            });
        });
    }

    function equalizeMenuCardHeights() {
        const cards = [...els.menuGrid.querySelectorAll('.menu-card')];
        if (!cards.length) return;

        cards.forEach((card) => {
            card.style.minHeight = '';
            card.style.height = 'auto';
            card.style.alignSelf = 'start';
        });

        requestAnimationFrame(() => {
            const maxHeight = Math.max(
                0,
                ...cards.map((card) => card.offsetHeight)
            );
            if (maxHeight <= 0) return;

            const heightPx = `${maxHeight}px`;
            cards.forEach((card) => {
                card.style.minHeight = heightPx;
                card.style.height = '';
                card.style.alignSelf = 'stretch';
            });
        });
    }

    function renderMenu() {
        const category = menu.find(c => c.id === selectedCategoryId);
        if (!category) return;

        els.menuGrid.innerHTML = category.items.map(item => `
            <article class="menu-card" data-item-id="${item.id}" tabindex="0" role="button"
                     aria-label="${escapeHtml(formatMenuItemName(item))} ${formatItemPrice(item)}">
                <h3>${escapeHtml(formatMenuItemName(item))}</h3>
                <p class="description">${item.description ? escapeHtml(item.description) : ''}</p>
                <p class="price">${formatItemPrice(item)}</p>
            </article>
        `).join('');

        els.menuGrid.querySelectorAll('.menu-card').forEach(card => {
            const open = () => openItemModal(parseInt(card.dataset.itemId, 10));
            card.addEventListener('click', open);
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    open();
                }
            });
        });

        equalizeMenuCardHeights();
    }

    function findMenuItem(id) {
        for (const cat of menu) {
            const item = cat.items.find(i => i.id === id);
            if (item) return item;
        }
        return null;
    }

    function renderSizeSelector(size = 'simple') {
        selectedSize = size;
        if (!currentItem?.price_double) {
            els.sizeGroup.hidden = true;
            return;
        }

        els.sizeGroup.hidden = false;
        els.sizeToggle.querySelectorAll('.size-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.size === size);
            btn.onclick = () => {
                selectedSize = btn.dataset.size;
                renderSizeSelector(selectedSize);
                updateModalTotal();
            };
        });

        const simplePrice = getUnitPrice(currentItem, 'simple');
        const doblePrice = getUnitPrice(currentItem, 'doble');
        $('#sizeSimple').textContent = `Simple ${formatMoney(simplePrice)}`;
        $('#sizeDoble').textContent = `Doble ${formatMoney(doblePrice)}`;
    }

    function openItemModal(itemId, cartIndex = -1) {
        const item = findMenuItem(itemId);
        if (!item) return;

        currentItem = item;
        editingCartIndex = cartIndex;

        els.modalItemName.textContent = formatMenuItemName(item);
        els.modalItemDesc.textContent = item.description || '';
        els.modalItemPrice.textContent = formatItemPrice(item);

        if (cartIndex >= 0) {
            const cartItem = cart[cartIndex];
            els.itemQuantity.value = cartItem.quantity;
            els.itemNotes.value = cartItem.notes || '';
            renderSizeSelector(cartItem.size || 'simple');
            renderIngredients(cartItem.removed_ingredients);
            renderExtras(cartItem.added_extras.map(e => e.id || e.name));
        } else {
            els.itemQuantity.value = 1;
            els.itemNotes.value = '';
            renderSizeSelector('simple');
            renderIngredients([]);
            renderExtras([]);
        }

        updateModalTotal();
        els.itemModal.showModal();
    }

    function renderIngredients(removed) {
        if (!currentItem.ingredients?.length) {
            els.ingredientsGroup.hidden = true;
            els.ingredientsList.innerHTML = '';
            return;
        }

        els.ingredientsGroup.hidden = false;
        els.ingredientsList.innerHTML = currentItem.ingredients
            .filter(i => i.removable)
            .map(ing => `
                <button type="button" class="chip ${removed.includes(ing.name) ? 'selected' : ''}"
                        data-ingredient="${escapeHtml(ing.name)}">
                    Sin ${escapeHtml(ing.name)}
                </button>
            `).join('');

        els.ingredientsList.querySelectorAll('.chip').forEach(chip => {
            chip.addEventListener('click', () => {
                chip.classList.toggle('selected');
            });
        });
    }

    function renderExtras(selectedExtras) {
        if (!currentItem.extras?.length) {
            els.extrasGroup.hidden = true;
            els.extrasList.innerHTML = '';
            return;
        }

        els.extrasGroup.hidden = false;
        const hasVariants = currentItem.extras.some(e => e.price === 0);

        els.extrasList.innerHTML = currentItem.extras.map(extra => {
            const isSelected = selectedExtras.includes(extra.id) || selectedExtras.includes(extra.name);
            const priceLabel = extra.price > 0 ? ` <span class="chip-price">+${formatMoney(extra.price)}</span>` : '';
            return `
                <button type="button" class="chip ${isSelected ? 'extra-selected' : ''}"
                        data-extra-id="${extra.id}" data-variant="${extra.price === 0 ? '1' : '0'}">
                    ${escapeHtml(extra.name)}${priceLabel}
                </button>
            `;
        }).join('');

        els.extrasList.querySelectorAll('.chip').forEach(chip => {
            chip.addEventListener('click', () => {
                if (chip.dataset.variant === '1') {
                    els.extrasList.querySelectorAll('.chip[data-variant="1"]').forEach(c => {
                        if (c !== chip) c.classList.remove('extra-selected');
                    });
                }
                chip.classList.toggle('extra-selected');
                updateModalTotal();
            });
        });

        if (hasVariants) {
            els.extrasGroup.querySelector('h3').textContent = 'Opciones / agregados';
        } else {
            els.extrasGroup.querySelector('h3').textContent = 'Agregados';
        }
    }

    function getSelectedRemoved() {
        return [...els.ingredientsList.querySelectorAll('.chip.selected')]
            .map(c => c.dataset.ingredient);
    }

    function getSelectedExtras() {
        const selected = [];
        els.extrasList.querySelectorAll('.chip.extra-selected').forEach(chip => {
            const extraId = parseInt(chip.dataset.extraId, 10);
            const extra = currentItem.extras.find(e => e.id === extraId);
            if (extra) selected.push(extra);
        });
        return selected;
    }

    function buildItemName(baseName) {
        if (currentItem.price_double != null) {
            return baseName + (selectedSize === 'doble' ? ' (Doble)' : ' (Simple)');
        }
        return baseName;
    }

    function updateModalTotal() {
        if (!currentItem) return;

        const qty = Math.max(1, parseInt(els.itemQuantity.value, 10) || 1);
        const unitPrice = getUnitPrice(currentItem, selectedSize);
        const extras = getSelectedExtras();
        const extrasTotal = extras.reduce((s, e) => s + e.price, 0);
        const lineTotal = (unitPrice + extrasTotal) * qty;

        els.modalLineTotal.textContent = formatMoney(lineTotal);
    }

    function addToCart() {
        const qty = Math.max(1, parseInt(els.itemQuantity.value, 10) || 1);
        const removed = getSelectedRemoved();
        const extras = getSelectedExtras();
        const unitPrice = getUnitPrice(currentItem, selectedSize);
        const extrasTotal = extras.reduce((s, e) => s + e.price, 0);
        const notes = els.itemNotes.value.trim();

        const cartItem = {
            menu_item_id: currentItem.id,
            item_name: buildItemName(currentItem.name),
            unit_price: unitPrice,
            size: selectedSize,
            quantity: qty,
            extras_total: extrasTotal,
            line_total: (unitPrice + extrasTotal) * qty,
            removed_ingredients: removed,
            added_extras: extras.map(e => ({ id: e.id, name: e.name, price: e.price })),
            notes,
        };

        if (editingCartIndex >= 0) {
            cart[editingCartIndex] = cartItem;
        } else {
            cart.push(cartItem);
        }

        saveCart();
        renderCart();
        els.itemModal.close();
        showToast('Producto agregado al pedido');
    }

    function renderCart() {
        const count = getCartCount();
        const subtotal = getCartSubtotal();
        const tip = getCartTip();
        const total = getCartTotal();

        els.cartCount.textContent = count;
        els.cartSubtotal.textContent = formatMoney(subtotal);
        els.cartTip.textContent = formatMoney(tip);
        els.cartTotal.textContent = formatMoney(total);
        els.cartTotalPreview.textContent = formatMoney(total);
        els.btnSendOrder.disabled = count === 0;

        if (cart.length === 0) {
            els.cartItems.innerHTML = '<li class="empty-cart">No hay productos en el pedido</li>';
            return;
        }

        els.cartItems.innerHTML = cart.map((item, index) => {
            let details = [];
            if (item.added_extras?.length) {
                details.push('Extras: ' + item.added_extras.map(e => {
                    const p = e.price > 0 ? ` (+${formatMoney(e.price)})` : '';
                    return e.name + p;
                }).join(', '));
            }
            if (item.removed_ingredients?.length) {
                details.push('Sin: ' + item.removed_ingredients.join(', '));
            }
            if (item.notes) details.push('Nota: ' + item.notes);

            return `
                <li class="cart-item">
                    <div class="cart-item-header">
                        <span class="cart-item-name">${item.quantity}x ${escapeHtml(item.item_name)}</span>
                        <span class="cart-item-price">${formatMoney(item.line_total)}</span>
                    </div>
                    ${details.length ? `<div class="cart-item-details">${escapeHtml(details.join(' · '))}</div>` : ''}
                    <div class="cart-item-actions">
                        <button type="button" class="btn btn-secondary btn-sm" data-edit="${index}">Editar</button>
                        <button type="button" class="btn btn-danger btn-sm" data-remove="${index}">Quitar</button>
                    </div>
                </li>
            `;
        }).join('');

        els.cartItems.querySelectorAll('[data-edit]').forEach(btn => {
            btn.addEventListener('click', () => {
                openItemModal(cart[parseInt(btn.dataset.edit, 10)].menu_item_id, parseInt(btn.dataset.edit, 10));
            });
        });

        els.cartItems.querySelectorAll('[data-remove]').forEach(btn => {
            btn.addEventListener('click', () => {
                cart.splice(parseInt(btn.dataset.remove, 10), 1);
                saveCart();
                renderCart();
            });
        });
    }

    async function sendOrder() {
        if (cart.length === 0) return;
        if (!requireOrderSession()) return;

        els.btnSendOrder.disabled = true;
        els.btnSendOrder.textContent = 'Enviando...';

        const payload = {
            table_number: getSessionTableForOrder(),
            waiter_name: getSessionStaffForOrder(),
            client_name: orderSession.clientName?.trim() || null,
            order_type: orderSession.orderType,
            tip_mode: getTipMode(),
            tip_amount: getCartTip(),
            tip_percent: tipPercent,
            items: cart.map(item => ({
                menu_item_id: item.menu_item_id,
                quantity: item.quantity,
                size: item.size || 'simple',
                removed_ingredients: item.removed_ingredients,
                added_extras: item.added_extras,
                notes: item.notes,
            })),
        };

        try {
            const res = await apiFetch('api/orders.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });

            const data = await res.json();
            if (!data.success) {
                throw new Error(data.error || 'Error al enviar pedido');
            }

            const settings = {
                ...loadSettings(),
                cafeName: window.APP_CAFE_NAME || 'Artemisa Salón de Té',
            };

            try {
                const printResult = await ThermalPrinter.printOrder(data.order, settings);
                const methodLabels = { bluetooth: 'Bluetooth', browser: 'navegador', network: 'red' };
                let toastMsg = `Comanda #${data.order.id} enviada e impresa (${methodLabels[printResult.method] || 'ok'})`;
                if (data.email?.sent) {
                    toastMsg += ' · Correo enviado';
                } else if (data.email?.saved_path) {
                    toastMsg += ' · Correo guardado en servidor (mail no disponible)';
                } else if (data.email) {
                    toastMsg += ' · Correo no enviado';
                }
                showToast(toastMsg);
            } catch (printErr) {
                let toastMsg = 'Pedido guardado. Impresión: ' + printErr.message;
                if (data.email?.sent) {
                    toastMsg += ' · Correo enviado';
                } else if (data.email?.saved_path) {
                    toastMsg += ' · Correo guardado en servidor (mail no disponible)';
                } else if (data.email) {
                    toastMsg += ' · Correo no enviado (configura MAIL_FROM en Hostgator)';
                }
                showToast(toastMsg, data.email?.sent ? 'success' : 'error');
            }

            cart = [];
            saveCart();
            renderCart();
            els.cartPanel.classList.remove('open');
        } catch (err) {
            showToast(err.message, 'error');
        } finally {
            els.btnSendOrder.disabled = cart.length === 0;
            els.btnSendOrder.textContent = 'Enviar comanda';
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function highlightSearchText(text, query) {
        const safe = escapeHtml(String(text ?? ''));
        const term = String(query ?? '').trim();
        if (!term) {
            return safe;
        }

        const escaped = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = new RegExp(`(${escaped})`, 'gi');
        return safe.replace(regex, '<mark class="reprint-highlight">$1</mark>');
    }

    function getReprintSearchQuery() {
        return els.reprintSearch?.value.trim() || reprintState.query || '';
    }

    function loadOrderSession() {
        syncOrderSessionFromStore();
    }

    function saveOrderSession() {
        persistActiveSession();
    }

    function getOrderTypeInputs() {
        return els.sessionForm?.querySelectorAll('input[name="orderType"]') || [];
    }

    function getSelectedOrderType() {
        const checked = els.sessionForm?.querySelector('input[name="orderType"]:checked');
        return checked?.value === 'llevar' ? 'llevar' : 'servir';
    }

    function updateSessionFieldsVisibility() {
        const isServir = getSelectedOrderType() === 'servir';
        if (els.sessionStaffServir) els.sessionStaffServir.hidden = !isServir;
        if (els.sessionStaffLlevar) els.sessionStaffLlevar.hidden = isServir;
    }

    function onOrderTypeChange() {
        const type = getSelectedOrderType();
        updateSessionFieldsVisibility();
        if (type === 'llevar') {
            if (els.sessionTable) {
                els.sessionTable.value = 'PL';
            }
        } else if (els.sessionTable?.value.trim().toUpperCase() === 'PL') {
            els.sessionTable.value = '';
        }
    }

    function fillSessionForm() {
        getOrderTypeInputs().forEach((input) => {
            input.checked = input.value === orderSession.orderType;
        });
        if (els.sessionTable) {
            els.sessionTable.value = orderSession.tableNumber || '';
        }
        if (els.sessionWaiter) {
            els.sessionWaiter.value = orderSession.orderType === 'servir' ? (orderSession.staffName || '') : '';
        }
        if (els.sessionCashier) {
            els.sessionCashier.value = orderSession.orderType === 'llevar' ? (orderSession.staffName || '') : '';
        }
        updateSessionFieldsVisibility();
        if (orderSession.orderType === 'llevar' && els.sessionTable && !orderSession.tableNumber) {
            els.sessionTable.value = 'PL';
        }
        if (els.sessionClientName) els.sessionClientName.value = orderSession.clientName || '';
        if (els.sessionError) {
            els.sessionError.hidden = true;
            els.sessionError.textContent = '';
        }
    }

    function renderSessionSummary() {
        if (!els.orderSessionBar || !els.orderSessionSummary) return;

        if (!orderSession.confirmed) {
            els.orderSessionBar.hidden = true;
            return;
        }

        let text;
        if (orderSession.orderType === 'llevar') {
            text = `Para llevar · Mesa ${orderSession.tableNumber || 'PL'} · Cajera(o): ${orderSession.staffName}`;
        } else {
            text = `Para servir · Mesa ${orderSession.tableNumber} · Mesera(o): ${orderSession.staffName}`;
        }
        if (orderSession.clientName) {
            text += ` · Cliente: ${orderSession.clientName}`;
        }

        els.orderSessionSummary.textContent = text;
        els.orderSessionBar.hidden = false;
    }

    function setSessionLocked(locked) {
        document.body.classList.toggle('session-locked', locked);
    }

    function openSessionModal() {
        fillSessionForm();
        setSessionLocked(true);
        els.sessionModal.showModal();
        const focusTarget = getSelectedOrderType() === 'servir' ? els.sessionTable : els.sessionCashier;
        setTimeout(() => focusTarget?.focus(), 50);
    }

    function closeSessionModal() {
        els.sessionModal.close();
        if (orderSession.confirmed) {
            setSessionLocked(false);
        }
    }

    function validateSessionForm() {
        const type = getSelectedOrderType();
        const mesa = els.sessionTable?.value.trim() || '';
        if (!mesa) {
            return type === 'llevar'
                ? 'Ingresa la mesa (use PL para para llevar).'
                : 'Ingresa el número de mesa.';
        }

        if (type === 'servir') {
            const mesero = els.sessionWaiter?.value.trim() || '';
            if (!mesero) return 'Ingresa el nombre de la mesera(o).';
            return null;
        }

        const cajero = els.sessionCashier?.value.trim() || '';
        if (!cajero) return 'Ingresa el nombre de la cajera(o).';
        return null;
    }

    function confirmSession() {
        const type = getSelectedOrderType();
        const error = validateSessionForm();
        if (error) {
            if (els.sessionError) {
                els.sessionError.textContent = error;
                els.sessionError.hidden = false;
            }
            return false;
        }

        orderSession.orderType = type;
        orderSession.clientName = els.sessionClientName?.value.trim() || '';
        orderSession.tableNumber = els.sessionTable.value.trim();
        if (type === 'servir') {
            orderSession.staffName = els.sessionWaiter.value.trim();
        } else {
            orderSession.staffName = els.sessionCashier.value.trim();
        }
        if (!orderSession.id) {
            orderSession.id = createSessionId();
        }
        orderSession.confirmed = true;
        saveOrderSession();
        renderSessionSummary();
        closeSessionModal();
        setSessionLocked(false);
        showToast('Pedido configurado');
        return true;
    }

    function getSessionTableForOrder() {
        if (!orderSession.confirmed) return null;
        return orderSession.tableNumber?.trim() || null;
    }

    function getSessionStaffForOrder() {
        return orderSession.confirmed ? orderSession.staffName : null;
    }

    function requireOrderSession() {
        if (orderSession.confirmed) return true;
        openSessionModal();
        showToast('Configure el pedido antes de continuar', 'error');
        return false;
    }

    function initOrderSession() {
        loadOrderSession();
        renderSessionSummary();

        getOrderTypeInputs().forEach((input) => {
            input.addEventListener('change', onOrderTypeChange);
        });

        els.sessionForm?.addEventListener('submit', (e) => {
            e.preventDefault();
            confirmSession();
        });

        els.sessionModal?.addEventListener('cancel', (e) => {
            if (!orderSession.confirmed) {
                e.preventDefault();
            }
        });

        els.sessionModal?.addEventListener('close', () => {
            if (!orderSession.confirmed) {
                setSessionLocked(true);
                els.sessionModal.showModal();
            } else {
                setSessionLocked(false);
            }
        });

        els.btnEditSession?.addEventListener('click', () => openSessionModal());
        els.btnNewSession?.addEventListener('click', () => createNewSession(true));

        const store = getSessionsStore();
        if (!store.activeId || !orderSession.confirmed) {
            if (!store.activeId) {
                createNewSession(true);
            } else {
                openSessionModal();
            }
        } else {
            setSessionLocked(false);
        }
    }

    function orderMatchesReprintQuery(order, query) {
        const term = String(query || '').trim();
        if (!term) return true;
        return buildReprintSummaryLine(order).toLowerCase().includes(term.toLowerCase());
    }

    function filterReprintOrders(orders, query) {
        return (orders || []).filter((order) => orderMatchesReprintQuery(order, query));
    }

    function renderReprintSearchMeta(filteredCount, query, totalInCache = 0) {
        if (!els.reprintPagination) return;

        const term = String(query || '').trim();
        if (!term) {
            return;
        }

        els.reprintPagination.innerHTML = filteredCount > 0
            ? `<span class="reprint-page-info">${filteredCount} comanda(s) con coincidencias</span>`
            : `<span class="reprint-page-info">Sin coincidencias para "${escapeHtml(term)}"</span>`;
    }

    function applyReprintSearchInstant() {
        const query = getReprintSearchQuery();
        reprintState.query = query;
        const filtered = filterReprintOrders(reprintOrdersCache, query);
        renderReprintList(filtered);
        if (query) {
            renderReprintSearchMeta(filtered.length, query, reprintOrdersCache.length);
        }
    }

    async function loadReprintOrders(page = 1, options = {}) {
        const query = options.query ?? getReprintSearchQuery();
        reprintState.page = page;
        reprintState.query = query;
        const silent = Boolean(options.silent);

        const params = new URLSearchParams({
            page: String(page),
            per_page: query ? '50' : '100',
        });
        if (query) {
            params.set('q', query);
        }

        if (!silent) {
            els.reprintList.innerHTML = '<li class="reprint-empty">Cargando comandas...</li>';
        }

        try {
            const res = await apiFetch(`api/orders.php?${params.toString()}`);
            const data = await res.json();
            if (!data.success) {
                throw new Error(data.error || 'Error al cargar comandas');
            }
            reprintState.pages = data.pagination?.pages || 1;
            reprintOrdersCache = data.orders || [];

            const visibleOrders = filterReprintOrders(reprintOrdersCache, query);
            renderReprintList(visibleOrders);

            if (query) {
                renderReprintSearchMeta(visibleOrders.length, query, reprintOrdersCache.length);
            } else {
                renderReprintPagination(data.pagination || {});
            }
        } catch (err) {
            els.reprintList.innerHTML = `<li class="reprint-empty">${escapeHtml(err.message)}</li>`;
            els.reprintPagination.innerHTML = '';
        }
    }

    function buildReprintSummaryLine(order) {
        const parts = [`#${order.id}`, formatOrderDateTime(order.created_at), formatMoney(order.total)];
        const isTakeaway = order.order_type === 'llevar';
        parts.push(isTakeaway ? 'Para llevar' : 'Para servir');
        if (isTakeaway) {
            parts.push('PL');
        } else if (order.table_number) {
            parts.push(`Mesa ${order.table_number}`);
        }
        if (order.waiter_name) {
            parts.push(`${order.staff_label}: ${order.waiter_name}`);
        }
        if (order.client_name) {
            parts.push(`Cliente: ${order.client_name}`);
        }
        return parts.join(' · ');
    }

    async function fetchOrderDetail(orderId) {
        const res = await apiFetch(`api/orders.php?id=${orderId}`);
        const data = await res.json();
        if (!data.success || !data.order) {
            throw new Error(data.error || 'No se pudo cargar la comanda');
        }
        return data.order;
    }

    function renderReprintList(orders) {
        if (!orders.length) {
            els.reprintList.innerHTML = '<li class="reprint-empty">No se encontraron comandas.</li>';
            return;
        }

        els.reprintList.innerHTML = orders.map((order) => {
            const summaryLine = buildReprintSummaryLine(order);
            const highlightedLine = highlightSearchText(summaryLine, getReprintSearchQuery());
            return `
            <li class="reprint-item" data-order-id="${order.id}">
                <button type="button" class="reprint-item-toggle" data-order-id="${order.id}" aria-expanded="false">
                    <span class="reprint-summary-line">${highlightedLine}</span>
                    <span class="reprint-toggle-icon" aria-hidden="true">▼</span>
                </button>
                <div class="reprint-detail" id="reprint-detail-${order.id}" hidden>
                    <p class="reprint-detail-loading">Cargando detalle...</p>
                </div>
            </li>
        `;
        }).join('');

        els.reprintList.querySelectorAll('.reprint-item-toggle').forEach((btn) => {
            btn.addEventListener('click', () => toggleReprintDetail(parseInt(btn.dataset.orderId, 10), btn));
        });
    }

    async function toggleReprintDetail(orderId, toggleBtn) {
        const detailEl = document.getElementById(`reprint-detail-${orderId}`);
        if (!detailEl) return;

        const isOpen = !detailEl.hidden;
        els.reprintList.querySelectorAll('.reprint-detail').forEach((el) => {
            el.hidden = true;
        });
        els.reprintList.querySelectorAll('.reprint-item-toggle').forEach((btn) => {
            btn.setAttribute('aria-expanded', 'false');
            btn.classList.remove('open');
        });

        if (isOpen) {
            return;
        }

        detailEl.hidden = false;
        toggleBtn.setAttribute('aria-expanded', 'true');
        toggleBtn.classList.add('open');
        detailEl.innerHTML = '<p class="reprint-detail-loading">Cargando detalle...</p>';

        try {
            const order = await fetchOrderDetail(orderId);
            const receiptText = formatReceiptPreview(order);
            const highlightedReceipt = highlightSearchText(receiptText, getReprintSearchQuery());
            detailEl.innerHTML = `
                <pre class="reprint-receipt">${highlightedReceipt}</pre>
                <button type="button" class="btn btn-primary btn-sm reprint-btn" data-reprint-id="${orderId}">
                    Reimprimir comanda #${orderId}
                </button>
            `;
            detailEl.querySelector('[data-reprint-id]')?.addEventListener('click', () => {
                reprintOrder(orderId);
            });
        } catch (err) {
            detailEl.innerHTML = `<p class="reprint-detail-error">${escapeHtml(err.message)}</p>`;
        }
    }

    function renderReprintPagination(pagination) {
        const page = pagination.page || 1;
        const pages = pagination.pages || 1;
        const total = pagination.total || 0;

        if (pages <= 1) {
            els.reprintPagination.innerHTML = total > 0
                ? `<span class="reprint-page-info">${total} comanda(s)</span>`
                : '';
            return;
        }

        els.reprintPagination.innerHTML = `
            <button type="button" class="btn btn-secondary btn-sm" data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>Anterior</button>
            <span class="reprint-page-info">Página ${page} de ${pages} (${total} comandas)</span>
            <button type="button" class="btn btn-secondary btn-sm" data-page="${page + 1}" ${page >= pages ? 'disabled' : ''}>Siguiente</button>
        `;

        els.reprintPagination.querySelectorAll('[data-page]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const nextPage = parseInt(btn.dataset.page, 10);
                if (!Number.isNaN(nextPage)) {
                    loadReprintOrders(nextPage);
                }
            });
        });
    }

    async function reprintOrder(orderId) {
        try {
            const settings = {
                ...loadSettings(),
                cafeName: window.APP_CAFE_NAME || 'Artemisa Salón de Té',
            };
            const printResult = await ThermalPrinter.printOrder({ id: orderId }, settings);
            const methodLabels = { bluetooth: 'Bluetooth', browser: 'navegador', network: 'red' };
            showToast(`Comanda #${orderId} reimpresa (${methodLabels[printResult.method] || 'ok'})`);
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    function openReprintModal() {
        reprintState.query = '';
        reprintState.page = 1;
        reprintOrdersCache = [];
        if (els.reprintSearch) els.reprintSearch.value = '';
        els.reprintModal.showModal();
        loadReprintOrders(1);
        setTimeout(() => els.reprintSearch?.focus(), 80);
    }

    function initReprintOrders() {
        let reprintSearchTimer;

        els.btnReprintOrders?.addEventListener('click', openReprintModal);
        els.reprintClose?.addEventListener('click', () => els.reprintModal.close());
        els.reprintSearchForm?.addEventListener('submit', (e) => {
            e.preventDefault();
            applyReprintSearchInstant();
            loadReprintOrders(1, { query: getReprintSearchQuery() });
        });
        els.reprintSearch?.addEventListener('input', () => {
            applyReprintSearchInstant();
            clearTimeout(reprintSearchTimer);
            reprintSearchTimer = setTimeout(() => {
                loadReprintOrders(1, { query: getReprintSearchQuery(), silent: true });
            }, 300);
        });
    }

    function initSettings() {
        const settings = loadSettings();

        if (settings.printerMode) els.printerMode.value = settings.printerMode;

        els.printerMode.addEventListener('change', () => {
            $('#btPrinterGroup').style.display = els.printerMode.value === 'bluetooth' ? 'block' : 'none';
        });
        els.printerMode.dispatchEvent(new Event('change'));

        els.btnSettings.addEventListener('click', () => els.settingsModal.showModal());
        els.settingsClose.addEventListener('click', () => els.settingsModal.close());

        els.settingsModal.querySelector('form').addEventListener('submit', (e) => {
            e.preventDefault();
            saveSettings({ ...loadSettings(), printerMode: els.printerMode.value });
            els.settingsModal.close();
            showToast('Configuración guardada');
        });

        els.btnConnectPrinter.addEventListener('click', async () => {
            try {
                els.btnConnectPrinter.disabled = true;
                const name = await ThermalPrinter.connectBluetooth();
                saveSettings({ ...loadSettings(), printerName: name });
                updatePrinterStatus();
                showToast(`Conectado: ${name}`);
            } catch (err) {
                showToast(err.message, 'error');
            } finally {
                els.btnConnectPrinter.disabled = false;
            }
        });

        updatePrinterStatus();
    }

    function updatePrinterStatus() {
        const settings = loadSettings();
        if (ThermalPrinter.isConnected()) {
            els.printerStatus.textContent = 'Conectada: ' + (settings.printerName || 'Bluetooth');
        } else if (settings.printerName) {
            els.printerStatus.textContent = 'Desconectada (vuelve a conectar)';
        } else {
            els.printerStatus.textContent = 'No conectada';
        }
    }

    function bindEvents() {
        els.cartToggle.addEventListener('click', () => els.cartPanel.classList.toggle('open'));
        els.closeCart.addEventListener('click', () => els.cartPanel.classList.remove('open'));

        els.btnClearCart.addEventListener('click', () => {
            if (cart.length && confirm('¿Vaciar el pedido actual?')) {
                cart = [];
                saveCart();
                renderCart();
            }
        });

        els.btnSendOrder.addEventListener('click', sendOrder);

        els.qtyMinus.addEventListener('click', () => {
            els.itemQuantity.value = Math.max(1, (parseInt(els.itemQuantity.value, 10) || 1) - 1);
            updateModalTotal();
        });
        els.qtyPlus.addEventListener('click', () => {
            els.itemQuantity.value = Math.min(99, (parseInt(els.itemQuantity.value, 10) || 1) + 1);
            updateModalTotal();
        });
        els.itemQuantity.addEventListener('input', updateModalTotal);

        els.usePercentTip?.addEventListener('change', () => {
            if (els.usePercentTip.checked) {
                els.manualTip.value = '';
            }
            renderCart();
        });

        els.manualTip?.addEventListener('input', () => {
            if (els.manualTip.value.trim() !== '') {
                els.usePercentTip.checked = false;
            }
            renderCart();
        });

        els.itemForm.addEventListener('submit', (e) => {
            e.preventDefault();
            addToCart();
        });
        els.modalClose.addEventListener('click', () => els.itemModal.close());
        els.modalCancel.addEventListener('click', () => els.itemModal.close());
    }

    function init() {
        initOrderSession();
        loadCart();
        initSettings();
        initReprintOrders();
        bindEvents();
        renderCart();
        loadMenu();

        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(equalizeMenuCardHeights, 120);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
