<?php

require_once __DIR__ . '/../Services/PermissionService.php';
require_once __DIR__ . '/../Services/ApiAuthService.php';
require_once __DIR__ . '/../Services/AuditService.php';
require_once __DIR__ . '/../Services/CsrfService.php';

class AuthMiddleware
{
    public static function handle(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            @session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => $isHttps
            ]);
            @session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            header('Location: /stok-takip/public/login');
            exit;
        }
    }

    public static function requirePermission(
        PDO $pdo,
        string $permission
    ): void {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_start();
        }

        if (!isset($_SESSION['role_id'])) {
            header('Location: /stok-takip/public/login');
            exit;
        }

        $permissionService = new PermissionService($pdo);

        $hasPermission = $permissionService->hasPermission(
            (int) $_SESSION['role_id'],
            $permission
        );

        if (!$hasPermission) {
            try {
                $audit = new AuditService($pdo);
                $audit->log(
                    action: 'ACCESS_DENIED_403',
                    module: 'SECURITY',
                    description: "Yetkisiz erişim engellendi. Gerekli yetki: '{$permission}'. Kullanıcı rolü: " . ($_SESSION['role_id'] ?? 'N/A')
                );
            } catch (Throwable) {}

            http_response_code(403);
            echo "403 - Bu işlemi yapmaya yetkiniz yok.";
            exit;
        }
    }

    public static function handleApi(
        PDO $pdo,
        array $route,
        string $path
    ): void {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_start();
        }

        $apiAuth = new ApiAuthService($pdo);
        $rawToken = $apiAuth->extractTokenFromRequest();

        if ($rawToken !== null) {
            $tokenRecord = $apiAuth->validateToken($rawToken);

            if (!$tokenRecord) {
                http_response_code(401);
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                echo json_encode([
                    'success' => false,
                    'status'  => 'UNAUTHORIZED',
                    'error'   => 'INVALID_TOKEN',
                    'message' => 'Geçersiz veya süresi dolmuş API token.'
                ]);
                exit;
            }

            $requiredPerm = $route['permission'] ?? 'api.access';
            if ($requiredPerm && !$apiAuth->hasPermission($tokenRecord, $requiredPerm)) {
                http_response_code(403);
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                echo json_encode([
                    'success' => false,
                    'status'  => 'FORBIDDEN',
                    'error'   => 'INSUFFICIENT_PERMISSIONS',
                    'message' => "Bu API kaynağı için '{$requiredPerm}' yetkisi gereklidir."
                ]);
                exit;
            }

            $_SESSION['api_authenticated'] = true;
            $_SESSION['api_user_id'] = $tokenRecord['user_id'];
            $_SESSION['api_token_id'] = $tokenRecord['id'];
            return;
        }

        // Fallback: Session authentication
        if (isset($_SESSION['user_id']) && isset($_SESSION['role_id'])) {
            $requiredPerm = $route['permission'] ?? null;
            if ($requiredPerm) {
                $permissionService = new PermissionService($pdo);
                $hasPerm = $permissionService->hasPermission((int)$_SESSION['role_id'], $requiredPerm);
                if (!$hasPerm) {
                    http_response_code(403);
                    if (!headers_sent()) {
                        header('Content-Type: application/json');
                    }
                    echo json_encode([
                        'success' => false,
                        'status'  => 'FORBIDDEN',
                        'error'   => 'INSUFFICIENT_PERMISSIONS',
                        'message' => "Bu API kaynağı için '{$requiredPerm}' yetkisi gereklidir."
                    ]);
                    exit;
                }
            }
            return;
        }

        http_response_code(401);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'status'  => 'UNAUTHORIZED',
            'error'   => 'AUTHENTICATION_REQUIRED',
            'message' => 'API kimlik doğrulaması gerekli. (Bearer token veya aktif oturum bulunamadı)'
        ]);
        exit;
    }

    public static function validateCsrf(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $submittedToken = CsrfService::extractTokenFromRequest();
            if ($submittedToken === null || !CsrfService::validateToken($submittedToken)) {
                http_response_code(403);
                echo "403 - Güvenlik Hatası: Geçersiz veya eksik CSRF token.";
                exit;
            }
        }
    }
}