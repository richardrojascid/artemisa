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
    <meta name="theme-color" content="#0a1628">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title><?= htmlspecialchars($cafeName) ?> — Mesero</title>
    <link rel="icon" type="image/png" href="<?= BRAND_LOGO_CIRCULAR ?>">
    <link rel="apple-touch-icon" href="<?= BRAND_LOGO_CIRCULAR ?>">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="manifest" href="manifest.json">
</head>
<body class="mesero-page">
    <header class="app-header">
        <div class="header-top">
            <div class="header-brand">
                <div class="header-logo-box" aria-hidden="true">
                    <img src="<?= BRAND_LOGO_CIRCULAR ?>" alt="" class="header-logo-circular">
                </div>
                <img src="<?= BRAND_LOGO_BANNER ?>" alt="<?= htmlspecialchars($cafeName) ?>" class="header-logo-banner">
            </div>
            <div class="header-actions">
                <a href="admin/" class="btn-icon" title="Administración">📋</a>
                <button type="button" class="btn-icon" id="btnSettings" aria-label="Configuración">⚙️</button>
                <a href="logout.php" class="btn-icon" title="Salir">🚪</a>
            </div>
        </div>
        <div class="order-meta">
            <label>
                Mesa
                <input type="text" id="tableNumber" placeholder="Ej: 5" inputmode="numeric" autocomplete="off">
            </label>
            <label>
                Mesero
                <input type="text" id="waiterName" placeholder="Tu nombre" autocomplete="name">
            </label>
        </div>
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
    </script>
    <script src="assets/js/printer.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
