<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/MenuRepository.php';
require_once dirname(__DIR__) . '/includes/AdminRepository.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Auth.php';

header('Content-Type: application/json; charset=utf-8');
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

try {
    Database::initialize();
    $db = Database::getConnection();
    $menu = new MenuRepository($db);
    $admin = new AdminRepository($db);
    $settings = new Settings($db);

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        throw new InvalidArgumentException('Datos inválidos.');
    }

    $action = $input['action'] ?? '';

    switch ($action) {
        case 'get_menu':
            echo json_encode([
                'success' => true,
                'categories' => $menu->getFullMenu(true),
                'cafe_name' => $settings->getCafeName(),
                'report_email' => $settings->getReportEmail(),
                'tip_percent' => $settings->getTipPercent(),
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'save_category':
            $id = $admin->saveCategory($input['category'] ?? []);
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'delete_category':
            $admin->deleteCategory((int) ($input['id'] ?? 0));
            echo json_encode(['success' => true]);
            break;

        case 'save_item':
            $id = $admin->saveItem($input['item'] ?? []);
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'delete_item':
            $admin->deleteItem((int) ($input['id'] ?? 0));
            echo json_encode(['success' => true]);
            break;

        case 'change_pin':
            $current = $input['current_pin'] ?? '';
            $newPin = $input['new_pin'] ?? '';
            if (!$settings->verifyPin($current)) {
                throw new InvalidArgumentException('PIN actual incorrecto.');
            }
            if (!preg_match('/^\d{4,8}$/', $newPin)) {
                throw new InvalidArgumentException('El nuevo PIN debe tener 4-8 dígitos.');
            }
            $settings->setPin($newPin);
            echo json_encode(['success' => true]);
            break;

        case 'change_cafe_name':
            $name = trim($input['cafe_name'] ?? '');
            if ($name === '') {
                throw new InvalidArgumentException('El nombre no puede estar vacío.');
            }
            $settings->set('cafe_name', $name);
            echo json_encode(['success' => true]);
            break;

        case 'reseed_menu':
            Database::reseedMenu();
            echo json_encode(['success' => true, 'message' => 'Carta Artemisa 2026 restaurada.']);
            break;

        case 'save_report_settings':
            $email = trim($input['report_email'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Correo de reportes inválido.');
            }
            $tipPercent = (float) ($input['tip_percent'] ?? TIP_PERCENT_DEFAULT);
            if ($tipPercent < 0 || $tipPercent > 100) {
                throw new InvalidArgumentException('La propina debe estar entre 0 y 100%.');
            }
            $settings->set('report_email', $email);
            $settings->set('tip_percent', (string) $tipPercent);
            echo json_encode(['success' => true]);
            break;

        default:
            throw new InvalidArgumentException('Acción no reconocida.');
    }
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en el panel de administración.']);
}
