<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/MenuRepository.php';
require_once dirname(__DIR__) . '/includes/OrderService.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/OrderNotifier.php';
require_once dirname(__DIR__) . '/includes/Auth.php';

header('Content-Type: application/json; charset=utf-8');
Auth::requireAuth();

try {
    if (!file_exists(DB_PATH)) {
        http_response_code(503);
        echo json_encode(['error' => 'La aplicación no está instalada.']);
        exit;
    }

    Database::initialize();
    $db = Database::getConnection();
    $menu = new MenuRepository($db);
    $settings = new Settings($db);
    $orders = new OrderService($db, $menu, $settings);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $query = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(5, min(50, (int) ($_GET['per_page'] ?? 15)));

        if (!empty($_GET['id'])) {
            $order = $orders->getOrder((int) $_GET['id']);
            if (!$order) {
                http_response_code(404);
                echo json_encode(['error' => 'Comanda no encontrada.']);
                exit;
            }
            echo json_encode(['success' => true, 'order' => $order], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $result = $orders->listOrders($query, $page, $perPage);
        echo json_encode([
            'success' => true,
            'orders' => $result['orders'],
            'pagination' => $result['pagination'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido.']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        throw new InvalidArgumentException('Datos de pedido inválidos.');
    }

    $order = $orders->createOrder($input);

    $emailResult = null;
    try {
        $emailResult = OrderNotifier::sendOrderEmail($order, $settings);
    } catch (Throwable $mailError) {
        $emailResult = [
            'sent' => false,
            'error' => $mailError->getMessage(),
            'to' => ORDER_NOTIFY_EMAIL,
        ];
    }

    echo json_encode([
        'success' => true,
        'order' => $order,
        'email' => $emailResult,
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al procesar la solicitud.']);
}
