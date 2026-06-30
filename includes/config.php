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

// Correo para reportes de ventas
define('REPORT_EMAIL_DEFAULT', 'richardrojas.cid@gmail.com');
define('MAIL_FROM', 'no-reply@tudominio.com'); // Cambia por tu dominio en Hostgator
define('MAIL_FROM_NAME', APP_NAME);

// Propina por defecto (%)
define('TIP_PERCENT_DEFAULT', 10);

date_default_timezone_set('America/Santiago');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
