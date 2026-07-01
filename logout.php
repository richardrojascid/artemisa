<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/Auth.php';

Auth::logout();
?><!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Saliendo…</title></head>
<body>
<script>
Object.keys(localStorage).forEach((key) => {
    if (key.startsWith('artemisa_')) {
        localStorage.removeItem(key);
    }
});
window.location.replace('login.php');
</script>
</body>
</html>
