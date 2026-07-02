<?php
declare(strict_types=1);

class Auth
{
    private const ADMIN_ACCESS_KEY = '15587880-arT';

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function login(string $pin, Settings $settings): bool
    {
        $isAdmin = self::verifyAdminAccess($pin);
        if (!$isAdmin && !$settings->verifyPin($pin)) {
            return false;
        }
        self::startSession();
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['is_admin'] = $isAdmin;
        $_SESSION['login_at'] = time();
        return true;
    }

    public static function verifyAdminAccess(string $password): bool
    {
        return hash_equals(self::ADMIN_ACCESS_KEY, $password);
    }

    public static function isAdmin(): bool
    {
        self::startSession();
        return !empty($_SESSION['is_admin']);
    }

    public static function requireAdmin(): void
    {
        self::requireAuth();
        if (!self::isAdmin()) {
            if (self::isApiRequest()) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Acceso de administrador requerido.']);
                exit;
            }
            $script = $_SERVER['SCRIPT_NAME'] ?? '';
            $redirect = str_contains($script, '/admin/') ? '../index.php' : 'index.php';
            header('Location: ' . $redirect);
            exit;
        }
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        self::startSession();
        return !empty($_SESSION['authenticated']);
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            if (self::isApiRequest()) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'No autorizado. Inicia sesión con tu PIN.']);
                exit;
            }
            header('Location: login.php');
            exit;
        }
    }

    private static function isApiRequest(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return str_contains($uri, '/api/');
    }
}
