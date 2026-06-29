<?php
declare(strict_types=1);

/**
 * Envío automático del reporte diario (cron Hostgator).
 * Ejemplo cron (23:55 diario): 55 23 * * * php /home/usuario/public_html/scripts/send-daily-report.php
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/SalesReportService.php';

if (!file_exists(DB_PATH)) {
    fwrite(STDERR, "App no instalada.\n");
    exit(1);
}

Database::initialize();
$settings = new Settings(Database::getConnection());
$reports = new SalesReportService(Database::getConnection());

$result = $reports->sendEmailReport(
    $settings->getReportEmail(),
    $settings->getCafeName(),
    date('Y-m-d')
);

if ($result['sent']) {
    echo "Reporte enviado a {$result['to']}\n";
    exit(0);
}

echo "Correo no enviado. Archivo guardado en: " . ($result['saved_path'] ?? 'N/A') . "\n";
exit(0);
