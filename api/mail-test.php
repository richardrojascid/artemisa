<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/Mailer.php';
require_once dirname(__DIR__) . '/includes/Auth.php';

header('Content-Type: application/json; charset=utf-8');
Auth::requireAuth();

try {
    $status = Mailer::getStatus();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode([
            'success' => true,
            'status' => $status,
            'message' => $status['smtp_ready']
                ? 'SMTP Titan configurado. Usa POST para enviar correo de prueba.'
                : 'SMTP no listo. Crea includes/mail.config.php con SMTP_PASS.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido.']);
        exit;
    }

    $to = defined('ORDER_NOTIFY_EMAIL') ? ORDER_NOTIFY_EMAIL : 'rrc.all.services@selfie3dchile.com';
    $result = Mailer::send(
        $to,
        'Prueba SMTP Artemisa — ' . date('Y-m-d H:i:s'),
        "Correo de prueba desde Artemisa.\n\nSi recibes esto, SMTP Titan funciona correctamente.\n"
    );

    echo json_encode([
        'success' => (bool) $result['sent'],
        'status' => $status,
        'result' => $result,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'status' => Mailer::getStatus(),
    ], JSON_UNESCAPED_UNICODE);
}
