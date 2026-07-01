<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/MenuRepository.php';
require_once dirname(__DIR__) . '/includes/OrderService.php';
require_once dirname(__DIR__) . '/includes/EscPosPrinter.php';
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
        'receipt_text' => receiptToPlainText($order, $items, $cafeName),
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al generar la comanda.']);
}

function formatCLP(float $amount): string
{
    return '$' . number_format($amount, 0, ',', '.');
}

function receiptToPlainText(array $order, array $items, string $cafeName): string
{
    $lines = [];
    $lines[] = strtoupper($cafeName);
    $lines[] = 'COMANDA DE PEDIDO';
    if (!empty($order['id'])) {
        $lines[] = 'Comanda #' . $order['id'];
    }
    $lines[] = str_repeat('-', 32);
    $createdAt = $order['created_at'] ?? date('Y-m-d H:i:s');
    $lines[] = 'Fecha: ' . date('d/m/Y H:i', strtotime($createdAt));

    if (!empty($order['client_name'])) {
        $lines[] = 'Cliente: ' . $order['client_name'];
    }

    if (!empty($order['table_number'])) {
        $isTakeaway = strtoupper((string) $order['table_number']) === 'PL';
        $lines[] = $isTakeaway ? 'PARA LLEVAR (PL)' : 'Mesa: ' . $order['table_number'];
    }
    if (!empty($order['waiter_name'])) {
        $isTakeaway = !empty($order['table_number']) && strtoupper((string) $order['table_number']) === 'PL';
        $lines[] = ($isTakeaway ? 'Cajero' : 'Mesero') . ': ' . $order['waiter_name'];
    }

    $lines[] = str_repeat('-', 32);

    foreach ($items as $item) {
        $qty = (int) ($item['quantity'] ?? 1);
        $name = $item['item_name'] ?? $item['name'] ?? 'Producto';
        $lines[] = "{$qty}x {$name}";
        $lines[] = '   Precio unit.: ' . formatCLP((float) ($item['unit_price'] ?? 0));

        $removed = $item['removed_ingredients'] ?? [];
        if (is_string($removed)) {
            $removed = json_decode($removed, true) ?: [];
        }
        if (!empty($removed)) {
            $lines[] = '   Sin: ' . implode(', ', $removed);
        }

        $extras = $item['added_extras'] ?? [];
        if (is_string($extras)) {
            $extras = json_decode($extras, true) ?: [];
        }
        foreach ($extras as $extra) {
            $extraName = is_array($extra) ? ($extra['name'] ?? '') : $extra;
            $extraPrice = is_array($extra) ? (float) ($extra['price'] ?? 0) : 0;
            $priceStr = $extraPrice > 0 ? ' (+' . formatCLP($extraPrice) . ')' : '';
            $lines[] = "   + {$extraName}{$priceStr}";
        }

        if (!empty($item['notes'])) {
            $lines[] = '   Nota: ' . $item['notes'];
        }

        $lines[] = '   Subtotal: ' . formatCLP((float) ($item['line_total'] ?? 0));
        $lines[] = '';
    }

    $lines[] = str_repeat('-', 32);
    $subtotal = (float) ($order['subtotal'] ?? $order['total'] ?? 0);
    $tipAmount = (float) ($order['tip_amount'] ?? 0);
    $lines[] = 'Subtotal productos: ' . formatCLP($subtotal);
    if ($tipAmount > 0) {
        $tipPercent = (float) ($order['tip_percent'] ?? ($subtotal > 0 ? ($tipAmount / $subtotal) * 100 : 10));
        $tipLabel = ($order['tip_mode'] ?? '') === 'manual'
            ? 'Propina'
            : 'Propina ' . (int) round($tipPercent) . '%';
        $lines[] = "{$tipLabel}: " . formatCLP($tipAmount);
    }
    $lines[] = 'TOTAL: ' . formatCLP((float) ($order['total'] ?? 0));
    $lines[] = str_repeat('-', 32);
    $lines[] = 'Gracias por su preferencia';

    return implode("\n", $lines);
}
