<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Settings.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/branding.php';

if (!file_exists(DB_PATH)) {
    header('Location: install.php');
    exit;
}

Database::initialize();
Auth::requireAuth();

$settings = new Settings(Database::getConnection());
$cafeName = $settings->getCafeName();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="<?= Auth::isAdmin() ? '#1c1c1c' : '#0a1628' ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title><?= htmlspecialchars($cafeName) ?> — Mesero</title>
    <link rel="icon" type="image/png" href="<?= BRAND_LOGO_CENTRAL ?>">
    <link rel="apple-touch-icon" href="<?= BRAND_LOGO_CENTRAL ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/app.css') ?>">
    <link rel="manifest" href="manifest.json">
</head>
<body class="mesero-page<?= Auth::isAdmin() ? ' admin-comandas-page' : '' ?>">
    <header class="app-header">
        <div class="header-hero">
            <img src="<?= asset_url(BRAND_LOGO_CENTRAL) ?>" alt="<?= htmlspecialchars($cafeName) ?>" class="header-logo-central">
            <div class="header-hero-front">
                <div class="header-actions">
                    <button type="button" class="btn-icon" id="btnReprintOrders" title="Buscar y reimprimir comandas">🖨️</button>
                    <?php if (Auth::isAdmin()): ?>
                    <a href="admin/" class="btn-icon" title="Administración">📋</a>
                    <?php endif; ?>
                    <button type="button" class="btn-icon" id="btnSettings" aria-label="Configuración">⚙️</button>
                    <a href="logout.php" class="btn-icon" title="Salir">🚪</a>
                </div>
            </div>
        </div>
        <?php if (!Auth::isAdmin()): ?>
        <div class="order-session-bar" id="orderSessionBar" hidden>
            <p class="order-session-summary" id="orderSessionSummary"></p>
            <button type="button" class="btn btn-secondary btn-sm" id="btnNewSession">Nueva</button>
            <button type="button" class="btn btn-secondary btn-sm" id="btnEditSession">Editar</button>
        </div>
        <?php else: ?>
        <div class="order-session-bar" id="orderSessionBar" hidden aria-hidden="true">
            <p class="order-session-summary" id="orderSessionSummary"></p>
        </div>
        <?php endif; ?>
        <div class="category-tabs" id="categoryTabs" role="tablist"></div>
    </header>

    <main class="menu-grid" id="menuGrid" aria-live="polite"></main>

    <aside class="cart-panel" id="cartPanel" aria-label="Carrito de pedido">
        <button type="button" class="cart-toggle" id="cartToggle">
            <span class="cart-count" id="cartCount">0</span>
            <span class="cart-label">Ver pedido</span>
            <span class="cart-total" id="cartTotalPreview">$0</span>
        </button>

        <div class="cart-content" id="cartContent">
            <div class="cart-header">
                <h2>Pedido actual</h2>
                <button type="button" class="btn-icon cart-close-mobile" id="closeCart" aria-label="Cerrar">✕</button>
            </div>
            <div class="cart-items-wrap">
                <ul class="cart-items" id="cartItems"></ul>
            </div>
            <div class="cart-footer">
                <div class="cart-summary">
                    <div class="summary-row">
                        <span>Subtotal productos</span>
                        <span id="cartSubtotal">$0</span>
                    </div>
                    <div class="summary-row tip-row">
                        <label class="tip-label" for="usePercentTip">
                            <input type="checkbox" id="usePercentTip" checked>
                            Propina <span id="tipPercentLabel">10</span>%
                        </label>
                        <span id="cartTip">$0</span>
                    </div>
                    <div class="summary-row tip-manual-row">
                        <label for="manualTip">Otra propina (CLP)</label>
                        <input type="number" id="manualTip" min="0" step="1" inputmode="numeric"
                               placeholder="Ej: 1500" aria-label="Monto de propina manual">
                    </div>
                    <div class="summary-row total-row">
                        <span>Total con propina</span>
                        <span id="cartTotal">$0</span>
                    </div>
                </div>
                <div class="cart-actions">
                    <button type="button" class="btn btn-secondary" id="btnClearCart">Vaciar</button>
                    <button type="button" class="btn btn-primary" id="btnSendOrder">Enviar comanda</button>
                </div>
            </div>
        </div>
    </aside>

    <dialog class="modal session-modal" id="sessionModal" aria-labelledby="sessionModalTitle">
        <form id="sessionForm" class="session-form">
            <div class="session-modal-brand">
                <img src="<?= asset_url(BRAND_LOGO_CENTRAL) ?>" alt="" class="session-modal-logo" width="160" height="160">
                <h2 id="sessionModalTitle" class="session-modal-title"><?= htmlspecialchars($cafeName) ?></h2>
                <p class="session-modal-welcome">Bienvenido — configure el pedido para comenzar</p>
            </div>

            <fieldset class="order-type-group">
                <legend class="sr-only">Tipo de pedido</legend>
                <label class="order-type-option">
                    <input type="radio" name="orderType" value="servir" checked>
                    <span>Para servir</span>
                </label>
                <label class="order-type-option">
                    <input type="radio" name="orderType" value="llevar">
                    <span>Para llevar</span>
                </label>
            </fieldset>

            <div class="session-client-group">
                <label for="sessionClientName">Cliente (opcional)</label>
                <input type="text" id="sessionClientName" autocomplete="name" placeholder="Nombre del cliente">
            </div>

            <div class="session-fields" id="sessionFields">
                <div class="session-inline-field session-field-mesa">
                    <span class="session-field-label">Mesa</span>
                    <input type="text" id="sessionTable" inputmode="text" autocomplete="off" placeholder="5">
                </div>
                <div class="session-inline-field session-field-staff" id="sessionStaffServir">
                    <span class="session-field-label">Mesera(o)</span>
                    <input type="text" id="sessionWaiter" autocomplete="name" placeholder="Nombre">
                </div>
                <div class="session-inline-field session-field-staff" id="sessionStaffLlevar" hidden>
                    <span class="session-field-label">Cajera(o)</span>
                    <input type="text" id="sessionCashier" autocomplete="name" placeholder="Nombre">
                </div>
            </div>

            <p class="session-error" id="sessionError" hidden></p>

            <button type="submit" class="btn btn-primary btn-block session-submit">ACEPTAR</button>
        </form>
    </dialog>

    <dialog class="modal reprint-modal" id="reprintModal" aria-labelledby="reprintModalTitle">
        <div class="reprint-modal-inner">
            <div class="modal-header">
                <h2 id="reprintModalTitle">Comandas — buscar y reimprimir</h2>
                <button type="button" class="btn-icon modal-close" id="reprintClose" aria-label="Cerrar">✕</button>
            </div>
            <form id="reprintSearchForm" class="reprint-search">
                <input type="search" id="reprintSearch" placeholder="ID, cliente, mesa, mesera(o), cajera(o)..." autocomplete="off">
                <button type="submit" class="btn btn-secondary btn-sm">Buscar</button>
            </form>
            <div class="reprint-list-wrap" id="reprintListWrap">
                <ul class="reprint-list" id="reprintList" aria-live="polite"></ul>
            </div>
            <div class="reprint-pagination" id="reprintPagination"></div>
        </div>
    </dialog>

    <dialog class="modal" id="itemModal">
        <form method="dialog" id="itemForm">
            <div class="modal-header">
                <h2 id="modalItemName">Producto</h2>
                <button type="button" class="btn-icon modal-close" id="modalClose" aria-label="Cerrar">✕</button>
            </div>
            <p class="modal-description" id="modalItemDesc"></p>
            <p class="modal-price" id="modalItemPrice"></p>

            <div class="form-group" id="sizeGroup" hidden>
                <h3>Tamaño</h3>
                <div class="size-toggle" id="sizeToggle">
                    <button type="button" class="size-btn active" data-size="simple" id="sizeSimple">Simple</button>
                    <button type="button" class="size-btn" data-size="doble" id="sizeDoble">Doble</button>
                </div>
            </div>

            <div class="form-group">
                <label for="itemQuantity">Cantidad</label>
                <div class="quantity-control">
                    <button type="button" class="qty-btn" id="qtyMinus" aria-label="Menos">−</button>
                    <input type="number" id="itemQuantity" value="1" min="1" max="99" inputmode="numeric">
                    <button type="button" class="qty-btn" id="qtyPlus" aria-label="Más">+</button>
                </div>
            </div>

            <div class="form-group" id="ingredientsGroup" hidden>
                <h3>Quitar ingredientes / elegir opciones</h3>
                <div class="chip-list" id="ingredientsList"></div>
            </div>

            <div class="form-group" id="extrasGroup" hidden>
                <h3>Agregados</h3>
                <div class="chip-list" id="extrasList"></div>
            </div>

            <div class="form-group">
                <label for="itemNotes">Notas especiales</label>
                <textarea id="itemNotes" rows="2" placeholder="Ej: sin hielo, poco dulce..."></textarea>
            </div>

            <div class="modal-total">
                <span>Subtotal línea:</span>
                <strong id="modalLineTotal">$0</strong>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="modalCancel">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="modalAdd">Agregar al pedido</button>
            </div>
        </form>
    </dialog>

    <dialog class="modal" id="settingsModal">
        <form method="dialog">
            <div class="modal-header">
                <h2>Configuración</h2>
                <button type="button" class="btn-icon modal-close" id="settingsClose" aria-label="Cerrar">✕</button>
            </div>
            <div class="form-group">
                <label for="printerMode">Modo de impresión</label>
                <select id="printerMode">
                    <option value="bluetooth">Bluetooth (Android)</option>
                    <option value="browser">Imprimir desde navegador</option>
                    <option value="network">Impresora de red (servidor)</option>
                </select>
            </div>
            <div class="form-group" id="btPrinterGroup">
                <label>Impresora Bluetooth</label>
                <button type="button" class="btn btn-secondary" id="btnConnectPrinter">Conectar impresora</button>
                <p class="hint" id="printerStatus">No conectada</p>
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </dialog>

    <div id="printArea" class="print-area" hidden></div>

    <script>
        window.APP_CAFE_NAME = <?= json_encode($cafeName, JSON_UNESCAPED_UNICODE) ?>;
        window.APP_TIP_PERCENT = <?= json_encode($settings->getTipPercent()) ?>;
        window.APP_BRAND_LOGO = <?= json_encode(BRAND_LOGO_CIRCULAR, JSON_UNESCAPED_UNICODE) ?>;
        window.APP_IS_ADMIN = <?= Auth::isAdmin() ? 'true' : 'false' ?>;
    </script>
    <script src="assets/js/printer.js"></script>
    <script src="<?= asset_url('assets/js/app.js') ?>"></script>
</body>
</html>
