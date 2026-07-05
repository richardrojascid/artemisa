<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/SalesReportService.php';
require_once dirname(__DIR__) . '/includes/Auth.php';

Auth::requireAuth();

try {
    Database::initialize();
    $db = Database::getConnection();
    $settings = new Settings($db);
    $reports = new SalesReportService($db);
    $cafeName = $settings->getCafeName();

    $date = $_GET['date'] ?? date('Y-m-d');
    $report = $reports->getDailyReport($date);

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['format'] ?? '') === 'csv') {
        $csv = $reports->buildCsv($report, $cafeName);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="ventas_' . $date . '.csv"');
        echo $csv;
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['format'] ?? '') === 'excel') {
        $rows = $reports->getDailyOrdersDetailed($date);
        $excel = $reports->buildDetailedSalesExcel($rows, $cafeName, $date);
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="ventas_detalle_' . $date . '.xls"');
        echo $excel;
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido.']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $input['action'] ?? 'preview';
    $reportDate = $input['date'] ?? date('Y-m-d');

    if ($action === 'preview') {
        echo json_encode([
            'success' => true,
            'report' => $reports->getDailyReport($reportDate),
            'report_email' => $settings->getReportEmail(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'send_email') {
        $to = trim($input['email'] ?? $settings->getReportEmail());
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Correo de destino inválido.');
        }

        $result = $reports->sendEmailReport($to, $cafeName, $reportDate);
        echo json_encode([
            'success' => true,
            'email_sent' => $result['sent'],
            'email_to' => $result['to'],
            'saved_path' => $result['saved_path'],
            'message' => $result['sent']
                ? 'Reporte enviado por correo.'
                : 'El correo no pudo enviarse (común en local). El archivo se guardó en data/reports/.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    throw new InvalidArgumentException('Acción no reconocida.');
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Error al generar el reporte.']);
}
