<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Settings.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/branding.php';

Auth::startSession();

if (!file_exists(DB_PATH)) {
    header('Location: install.php');
    exit;
}

Database::initialize();
$settings = new Settings(Database::getConnection());

if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = trim($_POST['pin'] ?? '');
    if ($pin === '') {
        $error = 'Ingresa tu PIN de acceso.';
    } elseif (Auth::login($pin, $settings)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'PIN incorrecto. Intenta de nuevo.';
    }
}

$cafeName = $settings->getCafeName();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0a1628">
    <title>Acceso — <?= htmlspecialchars($cafeName) ?></title>
    <link rel="icon" type="image/png" href="<?= BRAND_LOGO_CIRCULAR ?>">
    <link rel="apple-touch-icon" href="<?= BRAND_LOGO_CIRCULAR ?>">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="login-page">
    <main class="login-card">
        <div class="login-brand">
            <img src="<?= BRAND_LOGO_CIRCULAR ?>" alt="<?= htmlspecialchars($cafeName) ?>" class="login-logo-circular" width="200" height="200">
            <p class="login-subtitle">Acceso para personal</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" class="pin-form" id="pinForm">
            <label for="pin" class="sr-only">PIN de acceso</label>
            <input type="password" id="pin" name="pin" inputmode="numeric" pattern="[0-9]*"
                   maxlength="8" placeholder="Ingresa tu PIN" autocomplete="off" required autofocus>
            <button type="submit" class="btn btn-primary btn-block">Entrar</button>
        </form>

        <div class="pin-pad" id="pinPad" aria-label="Teclado numérico">
            <?php foreach (['1','2','3','4','5','6','7','8','9','','0','⌫'] as $key): ?>
                <?php if ($key === ''): ?>
                    <span class="pin-key empty"></span>
                <?php else: ?>
                    <button type="button" class="pin-key" data-key="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($key) ?></button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </main>
    <script>
    (() => {
        const pinInput = document.getElementById('pin');
        document.getElementById('pinPad').addEventListener('click', (e) => {
            const key = e.target.closest('.pin-key')?.dataset.key;
            if (!key) return;
            if (key === '⌫') {
                pinInput.value = pinInput.value.slice(0, -1);
            } else if (pinInput.value.length < 8) {
                pinInput.value += key;
            }
        });
    })();
    </script>
</body>
</html>
