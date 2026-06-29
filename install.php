<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/branding.php';

$message = '';
$error = '';
$defaultPin = '1234';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = trim($_POST['pin'] ?? '');
    $pinConfirm = trim($_POST['pin_confirm'] ?? '');

    if (!preg_match('/^\d{4,8}$/', $pin)) {
        $error = 'El PIN debe tener entre 4 y 8 dígitos numéricos.';
    } elseif ($pin !== $pinConfirm) {
        $error = 'Los PIN no coinciden.';
    } else {
        try {
            Database::install($pin);
            $message = '¡Instalación completada! Carta Artemisa 2026 cargada. Usa tu PIN para entrar.';
        } catch (Throwable $e) {
            $error = 'Error durante la instalación. Verifica permisos de escritura en /data';
        }
    }
}

$installed = file_exists(DB_PATH);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación — <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="icon" type="image/png" href="<?= BRAND_LOGO_CIRCULAR ?>">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="install-page">
    <main class="install-card">
        <img src="<?= BRAND_LOGO_CIRCULAR ?>" alt="<?= htmlspecialchars(APP_NAME) ?>" class="install-logo">
        <h1>Instalación</h1>
        <p>Configura <strong><?= htmlspecialchars(APP_NAME) ?></strong> con la carta 2026 y un PIN de acceso para el personal.</p>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <a href="login.php" class="btn btn-primary">Ir al acceso</a>
        <?php elseif ($installed): ?>
            <div class="alert alert-info">La aplicación ya está instalada.</div>
            <a href="login.php" class="btn btn-primary">Ir al acceso</a>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post" class="install-form">
                <div class="form-group">
                    <label for="pin">PIN de acceso (4-8 dígitos)</label>
                    <input type="password" id="pin" name="pin" inputmode="numeric" pattern="[0-9]{4,8}"
                           maxlength="8" value="<?= htmlspecialchars($defaultPin) ?>" required>
                </div>
                <div class="form-group">
                    <label for="pin_confirm">Confirmar PIN</label>
                    <input type="password" id="pin_confirm" name="pin_confirm" inputmode="numeric"
                           maxlength="8" value="<?= htmlspecialchars($defaultPin) ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">Instalar carta y crear PIN</button>
            </form>
        <?php endif; ?>

        <section class="install-requirements">
            <h2>Requisitos Hostgator</h2>
            <ul>
                <li>PHP 7.4 o superior (recomendado PHP 8.x)</li>
                <li>Extensión PDO SQLite habilitada</li>
                <li>Carpeta <code>/data</code> con permisos de escritura (755 o 775)</li>
                <li>Certificado SSL activo para HTTPS</li>
            </ul>
        </section>
    </main>
</body>
</html>
