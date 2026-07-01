/**
 * Artemisa Salón de Té — Aplicación para meseros
 */
(() => {
    const STORAGE_KEY = 'artemisa_comanda_settings';
    const CART_KEY = 'artemisa_comanda_cart';
    const SESSION_KEY = 'artemisa_order_session';

    let menu = [];
    let cart = [];
    let currentItem = null;
    let editingCartIndex = -1;
    let selectedCategoryId = null;
    let selectedSize = 'simple';
    let tipPercent = window.APP_TIP_PERCENT || 10;
    let orderSession = {
        orderType: 'servir',
        tableNumber: '',
        staffName: '',
        confirmed: false,
    };

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
        sessionFieldsServir: $('#sessionFieldsServir'),
        sessionFieldsLlevar: $('#sessionFieldsLlevar'),
        sessionTable: $('#sessionTable'),
        sessionWaiter: $('#sessionWaiter'),
        sessionCashier: $('#sessionCashier'),
        sessionError: $('#sessionError'),
        orderSessionBar: $('#orderSessionBar'),
        orderSessionSummary: $('#orderSessionSummary'),
        btnEditSession: $('#btnEditSession'),
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

    function loadCart() {
        try {
            cart = JSON.parse(localStorage.getItem(CART_KEY)) || [];
        } catch {
            cart = [];
        }
    }

    function saveCart() {
        localStorage.setItem(CART_KEY, JSON.stringify(cart));
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

    function renderMenu() {
        const category = menu.find(c => c.id === selectedCategoryId);
        if (!category) return;

        els.menuGrid.innerHTML = category.items.map(item => `
            <article class="menu-card" data-item-id="${item.id}" tabindex="0" role="button"
                     aria-label="${escapeHtml(item.name)} ${formatItemPrice(item)}">
                <h3>${escapeHtml(item.name)}</h3>
                ${item.description ? `<p class="description">${escapeHtml(item.description)}</p>` : ''}
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

        els.modalItemName.textContent = item.name;
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
                showToast(`Comanda enviada e impresa (${methodLabels[printResult.method] || 'ok'})`);
            } catch (printErr) {
                showToast('Pedido guardado. Impresión: ' + printErr.message, 'error');
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

    function loadOrderSession() {
        try {
            const saved = JSON.parse(sessionStorage.getItem(SESSION_KEY));
            if (saved && typeof saved === 'object') {
                orderSession = {
                    orderType: saved.orderType === 'llevar' ? 'llevar' : 'servir',
                    tableNumber: saved.tableNumber || '',
                    staffName: saved.staffName || '',
                    confirmed: Boolean(saved.confirmed),
                };
            }
        } catch {
            orderSession = { orderType: 'servir', tableNumber: '', staffName: '', confirmed: false };
        }
    }

    function saveOrderSession() {
        sessionStorage.setItem(SESSION_KEY, JSON.stringify(orderSession));
    }

    function getOrderTypeInputs() {
        return els.sessionForm?.querySelectorAll('input[name="orderType"]') || [];
    }

    function getSelectedOrderType() {
        const checked = els.sessionForm?.querySelector('input[name="orderType"]:checked');
        return checked?.value === 'llevar' ? 'llevar' : 'servir';
    }

    function updateSessionFieldsVisibility() {
        const type = getSelectedOrderType();
        const isServir = type === 'servir';
        if (els.sessionFieldsServir) els.sessionFieldsServir.hidden = !isServir;
        if (els.sessionFieldsLlevar) els.sessionFieldsLlevar.hidden = isServir;
    }

    function fillSessionForm() {
        getOrderTypeInputs().forEach((input) => {
            input.checked = input.value === orderSession.orderType;
        });
        updateSessionFieldsVisibility();
        if (els.sessionTable) els.sessionTable.value = orderSession.tableNumber || '';
        if (els.sessionWaiter) els.sessionWaiter.value = orderSession.orderType === 'servir' ? (orderSession.staffName || '') : '';
        if (els.sessionCashier) els.sessionCashier.value = orderSession.orderType === 'llevar' ? (orderSession.staffName || '') : '';
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
            text = `Para llevar · PL · Cajera(o): ${orderSession.staffName}`;
        } else {
            text = `Para servir · Mesa ${orderSession.tableNumber} · Mesera(o): ${orderSession.staffName}`;
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
        if (type === 'servir') {
            const mesa = els.sessionTable?.value.trim() || '';
            const mesero = els.sessionWaiter?.value.trim() || '';
            if (!mesa) return 'Ingresa el número de mesa.';
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
        if (type === 'servir') {
            orderSession.tableNumber = els.sessionTable.value.trim();
            orderSession.staffName = els.sessionWaiter.value.trim();
        } else {
            orderSession.tableNumber = 'PL';
            orderSession.staffName = els.sessionCashier.value.trim();
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
        return orderSession.orderType === 'llevar' ? 'PL' : orderSession.tableNumber;
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
            input.addEventListener('change', updateSessionFieldsVisibility);
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

        if (!orderSession.confirmed) {
            openSessionModal();
        } else {
            setSessionLocked(false);
        }
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
        loadCart();
        initSettings();
        initOrderSession();
        bindEvents();
        renderCart();
        loadMenu();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
