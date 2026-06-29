/**
 * Panel de administración — Artemisa Salón de Té
 */
(() => {
    const $ = (sel) => document.querySelector(sel);
    let categories = [];

    const fetchOpts = { credentials: 'same-origin', headers: { 'Content-Type': 'application/json' } };

    async function api(action, data = {}) {
        const res = await fetch('../api/admin.php', {
            method: 'POST',
            ...fetchOpts,
            body: JSON.stringify({ action, ...data }),
        });
        if (res.status === 401) {
            window.location.href = '../login.php';
            return null;
        }
        const json = await res.json();
        if (!json.success) {
            throw new Error(json.error || 'Error en la operación');
        }
        return json;
    }

    function formatMoney(amount) {
        return '$' + Math.round(Number(amount)).toLocaleString('es-CL');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast ${type} show`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    async function loadMenu() {
        const data = await api('get_menu');
        if (!data) return;
        categories = data.categories;
        if (data.cafe_name) {
            $('#adminCafeName').value = data.cafe_name;
        }
        if (data.report_email) {
            const el = $('#reportEmail');
            if (el) el.value = data.report_email;
        }
        if (data.tip_percent != null) {
            const el = $('#tipPercentSetting');
            if (el) el.value = data.tip_percent;
        }
        renderMenu();
    }

    function renderMenu() {
        const container = $('#adminMenu');
        if (!categories.length) {
            container.innerHTML = '<p class="empty-cart">No hay categorías. Crea una para comenzar.</p>';
            return;
        }

        container.innerHTML = categories.map(cat => `
            <article class="admin-category ${cat.active ? '' : 'inactive'}">
                <div class="admin-category-header">
                    <div>
                        <h3>${escapeHtml(cat.name)}</h3>
                        <span class="badge">${cat.items.length} productos · Orden ${cat.sort_order}</span>
                    </div>
                    <div class="admin-item-actions">
                        <button type="button" class="btn btn-secondary btn-sm" data-edit-cat="${cat.id}">Editar</button>
                        <button type="button" class="btn btn-primary btn-sm" data-new-item="${cat.id}">+ Producto</button>
                        <button type="button" class="btn btn-danger btn-sm" data-del-cat="${cat.id}">Eliminar</button>
                    </div>
                </div>
                <ul class="admin-items">
                    ${cat.items.map(item => `
                        <li class="admin-item ${item.active ? '' : 'inactive'}">
                            <div>
                                <strong>${escapeHtml(item.name)}</strong>
                                <span>${item.price_double ? formatMoney(item.price) + ' / ' + formatMoney(item.price_double) : formatMoney(item.price)}</span>
                                ${item.description ? `<p>${escapeHtml(item.description)}</p>` : ''}
                            </div>
                            <div class="admin-item-actions">
                                <button type="button" class="btn btn-secondary btn-sm" data-edit-item="${item.id}" data-cat="${cat.id}">Editar</button>
                                <button type="button" class="btn btn-danger btn-sm" data-del-item="${item.id}">Eliminar</button>
                            </div>
                        </li>
                    `).join('')}
                </ul>
            </article>
        `).join('');

        container.querySelectorAll('[data-edit-cat]').forEach(btn => {
            btn.addEventListener('click', () => openCategoryModal(parseInt(btn.dataset.editCat, 10)));
        });
        container.querySelectorAll('[data-new-item]').forEach(btn => {
            btn.addEventListener('click', () => openItemModal(parseInt(btn.dataset.newItem, 10)));
        });
        container.querySelectorAll('[data-del-cat]').forEach(btn => {
            btn.addEventListener('click', () => deleteCategory(parseInt(btn.dataset.delCat, 10)));
        });
        container.querySelectorAll('[data-edit-item]').forEach(btn => {
            btn.addEventListener('click', () => {
                const item = findItem(parseInt(btn.dataset.editItem, 10));
                if (item) openItemModal(parseInt(btn.dataset.cat, 10), item);
            });
        });
        container.querySelectorAll('[data-del-item]').forEach(btn => {
            btn.addEventListener('click', () => deleteItem(parseInt(btn.dataset.delItem, 10)));
        });
    }

    function findItem(id) {
        for (const cat of categories) {
            const item = cat.items.find(i => i.id === id);
            if (item) return item;
        }
        return null;
    }

    function openCategoryModal(id = null) {
        const cat = id ? categories.find(c => c.id === id) : null;
        $('#categoryModalTitle').textContent = cat ? 'Editar categoría' : 'Nueva categoría';
        $('#categoryId').value = cat?.id || '';
        $('#categoryName').value = cat?.name || '';
        $('#categoryOrder').value = cat?.sort_order ?? 0;
        $('#categoryActive').checked = cat ? !!cat.active : true;
        $('#categoryModal').showModal();
    }

    function openItemModal(categoryId, item = null) {
        $('#itemModalTitle').textContent = item ? 'Editar producto' : 'Nuevo producto';
        $('#itemId').value = item?.id || '';
        $('#itemCategoryId').value = categoryId;
        $('#itemName').value = item?.name || '';
        $('#itemDescription').value = item?.description || '';
        $('#itemPrice').value = item?.price ?? '';
        $('#itemPriceDouble').value = item?.price_double ?? '';
        $('#itemOrder').value = item?.sort_order ?? 0;
        $('#itemActive').checked = item ? !!item.active : true;

        $('#itemIngredients').value = (item?.ingredients || [])
            .map(i => (i.removable ? '- ' : '') + i.name)
            .join('\n');

        $('#itemExtras').value = (item?.extras || [])
            .map(e => `${e.name}|${e.price}`)
            .join('\n');

        $('#itemModal').showModal();
    }

    function parseIngredients(text) {
        return text.split('\n')
            .map(line => line.trim())
            .filter(Boolean)
            .map(line => {
                const removable = line.startsWith('-');
                const name = removable ? line.replace(/^-\s*/, '') : line;
                return { name, removable: removable ? 1 : 0 };
            });
    }

    function parseExtras(text) {
        return text.split('\n')
            .map(line => line.trim())
            .filter(Boolean)
            .map(line => {
                const [name, price = '0'] = line.split('|');
                return { name: name.trim(), price: parseFloat(price) || 0 };
            });
    }

    async function deleteCategory(id) {
        if (!confirm('¿Eliminar esta categoría y todos sus productos?')) return;
        await api('delete_category', { id });
        showToast('Categoría eliminada');
        loadMenu();
    }

    async function deleteItem(id) {
        if (!confirm('¿Eliminar este producto?')) return;
        await api('delete_item', { id });
        showToast('Producto eliminado');
        loadMenu();
    }

    function bindModals() {
        document.querySelectorAll('[data-close]').forEach(btn => {
            btn.addEventListener('click', () => btn.closest('dialog')?.close());
        });

        $('#btnNewCategory').addEventListener('click', () => openCategoryModal());

        $('#categoryForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await api('save_category', {
                category: {
                    id: $('#categoryId').value || null,
                    name: $('#categoryName').value.trim(),
                    sort_order: parseInt($('#categoryOrder').value, 10) || 0,
                    active: $('#categoryActive').checked,
                },
            });
            $('#categoryModal').close();
            showToast('Categoría guardada');
            loadMenu();
        });

        $('#itemForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await api('save_item', {
                item: {
                    id: $('#itemId').value || null,
                    category_id: parseInt($('#itemCategoryId').value, 10),
                    name: $('#itemName').value.trim(),
                    description: $('#itemDescription').value.trim(),
                    price: parseFloat($('#itemPrice').value) || 0,
                    price_double: $('#itemPriceDouble').value !== '' ? parseFloat($('#itemPriceDouble').value) : null,
                    sort_order: parseInt($('#itemOrder').value, 10) || 0,
                    active: $('#itemActive').checked,
                    ingredients: parseIngredients($('#itemIngredients').value),
                    extras: parseExtras($('#itemExtras').value),
                },
            });
            $('#itemModal').close();
            showToast('Producto guardado');
            loadMenu();
        });
    }

    function bindForms() {
        $('#settingsForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await api('change_cafe_name', { cafe_name: $('#adminCafeName').value.trim() });
            showToast('Nombre actualizado');
        });

        $('#pinForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await api('change_pin', {
                current_pin: $('#currentPin').value,
                new_pin: $('#newPin').value,
            });
            $('#currentPin').value = '';
            $('#newPin').value = '';
            showToast('PIN actualizado');
        });

        $('#btnReseed').addEventListener('click', async () => {
            if (!confirm('¿Restaurar la carta Artemisa 2026? Se perderán los cambios del menú.')) return;
            await api('reseed_menu');
            showToast('Carta 2026 restaurada');
            loadMenu();
        });

        $('#reportSettingsForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            await api('save_report_settings', {
                report_email: $('#reportEmail').value.trim(),
                tip_percent: parseFloat($('#tipPercentSetting').value) || 10,
            });
            showToast('Configuración de reportes guardada');
        });

        $('#btnPreviewReport')?.addEventListener('click', previewReport);
        $('#btnSendReport')?.addEventListener('click', sendReport);
        $('#btnDownloadCsv')?.addEventListener('click', (e) => {
            e.preventDefault();
            const date = $('#reportDate').value || new Date().toISOString().slice(0, 10);
            window.location.href = `../api/report.php?format=csv&date=${encodeURIComponent(date)}`;
        });
    }

    async function reportApi(action, data = {}) {
        const res = await fetch('../api/report.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, ...data }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.error || 'Error en reporte');
        return json;
    }

    async function previewReport() {
        const date = $('#reportDate').value;
        const data = await reportApi('preview', { date });
        const r = data.report;
        const preview = $('#reportPreview');
        preview.hidden = false;
        preview.innerHTML = `
            <h3>Resumen ${r.date}</h3>
            <p>Pedidos: <strong>${r.orders_count}</strong></p>
            <table class="report-table">
                <thead><tr><th>Producto</th><th>Cant.</th><th>Monto</th></tr></thead>
                <tbody>
                    ${r.products.map(p => `<tr><td>${escapeHtml(p.item_name)}</td><td>${p.total_qty}</td><td>${formatMoney(p.total_amount)}</td></tr>`).join('')}
                </tbody>
            </table>
            <p>Subtotal productos: <strong>${formatMoney(r.subtotal_products)}</strong></p>
            <p>Propinas: <strong>${formatMoney(r.total_tips)}</strong></p>
            <p>Total del día: <strong>${formatMoney(r.grand_total)}</strong></p>
        `;
    }

    async function sendReport() {
        const date = $('#reportDate').value;
        const email = $('#reportEmail').value.trim();
        if (!confirm(`¿Enviar reporte del ${date} a ${email}?`)) return;
        const data = await reportApi('send_email', { date, email });
        showToast(data.message, data.email_sent ? 'success' : 'error');
    }

    bindModals();
    bindForms();
    loadMenu();
})();
