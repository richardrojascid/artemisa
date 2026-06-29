<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/MenuRepository.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
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
    $menu = new MenuRepository(Database::getConnection());
    $settings = new Settings(Database::getConnection());

    echo json_encode([
        'success' => true,
        'app' => $settings->getCafeName(),
        'tip_percent' => $settings->getTipPercent(),
        'categories' => $menu->getFullMenu(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar el menú.']);
}
