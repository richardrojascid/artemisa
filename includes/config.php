<?php
declare(strict_types=1);

define('APP_NAME', 'Artemisa Salón de Té');
define('APP_VERSION', '1.1.1');
define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . '/data');
define('DB_PATH', DATA_PATH . '/cafe.db');

define('PRINTER_ENABLED', false);
define('PRINTER_HOST', '192.168.1.100');
define('PRINTER_PORT', 9100);
define('PRINTER_TIMEOUT', 5);

if (is_readable(__DIR__ . '/mail.config.php')) {
    require_once __DIR__ . '/mail.config.php';
}

if (!defined('MAIL_DRIVER')) {
    define('MAIL_DRIVER', 'mail');
}
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'smtp.titan.email');
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', 587);
}
if (!defined('SMTP_ENCRYPTION')) {
    define('SMTP_ENCRYPTION', 'tls');
}
if (!defined('SMTP_USER')) {
    define('SMTP_USER', 'rrc.all.services@selfie3dchile.com');
}
if (!defined('SMTP_PASS')) {
    define('SMTP_PASS', '');
}
if (!defined('SMTP_TIMEOUT')) {
    define('SMTP_TIMEOUT', 20);
}

// Correo para reportes de ventas y notificación de comandas
define('REPORT_EMAIL_DEFAULT', 'rrc.all.services@selfie3dchile.com');
define('ORDER_NOTIFY_EMAIL', 'rrc.all.services@selfie3dchile.com');
define('MAIL_FROM', 'rrc.all.services@selfie3dchile.com');
define('MAIL_FROM_NAME', APP_NAME);

// Propina por defecto (%)
define('TIP_PERCENT_DEFAULT', 10);

date_default_timezone_set('America/Santiago');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
