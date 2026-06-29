<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Auth.php';

if (!file_exists(DB_PATH)) {
    header('Location: ../install.php');
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
    <title>Administración — <?= htmlspecialchars($cafeName) ?></title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="admin-page">
    <header class="admin-header">
        <div>
            <h1>Administración</h1>
            <p><?= htmlspecialchars($cafeName) ?></p>
        </div>
        <div class="header-actions">
            <a href="../index.php" class="btn btn-secondary btn-sm">Volver a comandas</a>
            <a href="../logout.php" class="btn btn-secondary btn-sm">Salir</a>
        </div>
    </header>

    <main class="admin-main">
        <section class="admin-section">
            <h2>Configuración general</h2>
            <form id="settingsForm" class="admin-form">
                <div class="form-group">
                    <label for="adminCafeName">Nombre del local</label>
                    <input type="text" id="adminCafeName" value="<?= htmlspecialchars($cafeName) ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">Guardar nombre</button>
            </form>

            <form id="pinForm" class="admin-form">
                <h3>Cambiar PIN de acceso</h3>
                <div class="form-group">
                    <label for="currentPin">PIN actual</label>
                    <input type="password" id="currentPin" inputmode="numeric" maxlength="8" required>
                </div>
                <div class="form-group">
                    <label for="newPin">Nuevo PIN</label>
                    <input type="password" id="newPin" inputmode="numeric" maxlength="8" required>
                </div>
                <button type="submit" class="btn btn-secondary">Cambiar PIN</button>
            </form>

            <div class="admin-danger-zone">
                <h3>Restaurar carta</h3>
                <p>Recarga el menú Artemisa 2026 original. Esto reemplaza categorías y productos actuales.</p>
                <button type="button" class="btn btn-danger" id="btnReseed">Restaurar carta 2026</button>
            </div>
        </section>

        <section class="admin-section">
            <h2>Reporte de ventas del día</h2>
            <form id="reportSettingsForm" class="admin-form">
                <div class="form-group">
                    <label for="reportEmail">Correo para reportes</label>
                    <input type="email" id="reportEmail" value="<?= htmlspecialchars($settings->getReportEmail()) ?>" required>
                </div>
                <div class="form-group">
                    <label for="tipPercentSetting">Propina por defecto (%)</label>
                    <input type="number" id="tipPercentSetting" value="<?= htmlspecialchars((string) $settings->getTipPercent()) ?>" min="0" max="100" step="1">
                </div>
                <button type="submit" class="btn btn-secondary">Guardar configuración</button>
            </form>

            <div class="form-row" style="margin-top:16px">
                <div class="form-group">
                    <label for="reportDate">Fecha del reporte</label>
                    <input type="date" id="reportDate" value="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <div class="admin-report-actions">
                <button type="button" class="btn btn-secondary" id="btnPreviewReport">Ver resumen</button>
                <a href="#" class="btn btn-secondary" id="btnDownloadCsv">Descargar Excel (CSV)</a>
                <button type="button" class="btn btn-primary" id="btnSendReport">Enviar por correo</button>
            </div>

            <div id="reportPreview" class="report-preview" hidden></div>
        </section>

        <section class="admin-section">
            <div class="section-header">
                <h2>Categorías y productos</h2>
                <button type="button" class="btn btn-primary btn-sm" id="btnNewCategory">+ Categoría</button>
            </div>
            <div id="adminMenu" class="admin-menu-list"></div>
        </section>
    </main>

    <dialog class="modal" id="categoryModal">
        <form id="categoryForm">
            <div class="modal-header">
                <h2 id="categoryModalTitle">Categoría</h2>
                <button type="button" class="btn-icon modal-close" data-close>✕</button>
            </div>
            <input type="hidden" id="categoryId">
            <div class="form-group">
                <label for="categoryName">Nombre</label>
                <input type="text" id="categoryName" required>
            </div>
            <div class="form-group">
                <label for="categoryOrder">Orden</label>
                <input type="number" id="categoryOrder" value="0" min="0">
            </div>
            <label class="checkbox-label">
                <input type="checkbox" id="categoryActive" checked> Activa
            </label>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-close>Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </dialog>

    <dialog class="modal" id="itemModal">
        <form id="itemForm">
            <div class="modal-header">
                <h2 id="itemModalTitle">Producto</h2>
                <button type="button" class="btn-icon modal-close" data-close>✕</button>
            </div>
            <input type="hidden" id="itemId">
            <input type="hidden" id="itemCategoryId">
            <div class="form-group">
                <label for="itemName">Nombre</label>
                <input type="text" id="itemName" required>
            </div>
            <div class="form-group">
                <label for="itemDescription">Descripción</label>
                <textarea id="itemDescription" rows="2"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="itemPrice">Precio simple</label>
                    <input type="number" id="itemPrice" min="0" step="1" required>
                </div>
                <div class="form-group">
                    <label for="itemPriceDouble">Precio doble (opcional)</label>
                    <input type="number" id="itemPriceDouble" min="0" step="1">
                </div>
            </div>
            <div class="form-group">
                <label for="itemOrder">Orden</label>
                <input type="number" id="itemOrder" value="0" min="0">
            </div>
            <label class="checkbox-label">
                <input type="checkbox" id="itemActive" checked> Activo
            </label>

            <div class="form-group">
                <label>Ingredientes / opciones (uno por línea, prefijo - para quitar)</label>
                <textarea id="itemIngredients" rows="3" placeholder="Queso fresco&#10;- Jamón (quitable)"></textarea>
            </div>
            <div class="form-group">
                <label>Agregados (formato: nombre|precio)</label>
                <textarea id="itemExtras" rows="3" placeholder="Leche vegetal|500&#10;Tetera|5000"></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-close>Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar producto</button>
            </div>
        </form>
    </dialog>

    <script src="../assets/js/admin.js"></script>
</body>
</html>
