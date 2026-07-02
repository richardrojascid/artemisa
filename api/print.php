<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/MenuRepository.php';
require_once dirname(__DIR__) . '/includes/OrderService.php';
require_once dirname(__DIR__) . '/includes/EscPosPrinter.php';
require_once dirname(__DIR__) . '/includes/OrderReceipt.php';
require_once dirname(__DIR__) . '/includes/Auth.php';

header('Content-Type: application/json; charset=utf-8');
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

try {
    if (!file_exists(DB_PATH)) {
        http_response_code(503);
        echo json_encode(['error' => 'La aplicación no está instalada.']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        throw new InvalidArgumentException('Datos inválidos.');
    }

    Database::initialize();
    $db = Database::getConnection();
    $menu = new MenuRepository($db);
    $orders = new OrderService($db, $menu);

    $order = null;
    $items = [];

    if (!empty($input['order_id'])) {
        $order = $orders->getOrder((int) $input['order_id']);
        if (!$order) {
            throw new InvalidArgumentException('Orden no encontrada.');
        }
        $items = $order['items'];
    } elseif (!empty($input['order'])) {
        $order = $input['order'];
        $items = $order['items'] ?? [];
    } else {
        throw new InvalidArgumentException('Se requiere order_id o datos de order.');
    }

    $cafeName = $input['cafe_name'] ?? APP_NAME;
    $receipt = EscPosPrinter::buildReceipt($order, $items, strtoupper($cafeName));

    $printed = false;
    $printError = null;

    if (PRINTER_ENABLED) {
        $printed = EscPosPrinter::sendToNetwork($receipt, PRINTER_HOST, PRINTER_PORT, PRINTER_TIMEOUT);
        if (!$printed) {
            $printError = 'No se pudo conectar con la impresora de red.';
        }
    }

    echo json_encode([
        'success' => true,
        'printed_network' => $printed,
        'print_error' => $printError,
        'receipt_base64' => base64_encode($receipt),
        'receipt_text' => OrderReceipt::toPlainText($order, $items, $cafeName),
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al generar la comanda.']);
}
