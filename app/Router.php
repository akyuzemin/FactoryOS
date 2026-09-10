<?php

require_once __DIR__ . '/Middleware/AuthMiddleware.php';

class Router
{
    private array $routes;

    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    public function dispatch(string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH);

        $basePath = '/stok-takip/public';

        if (str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }

        if ($path === '') {
            $path = '/';
        }

        if (!isset($this->routes[$path])) {
            http_response_code(404);
            if (str_starts_with($path, '/api/')) {
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                echo json_encode([
                    'success' => false,
                    'status'  => 'NOT_FOUND',
                    'error'   => 'RESOURCE_NOT_FOUND',
                    'message' => 'API endpointi bulunamadı.'
                ]);
            } else {
                echo "404 - Sayfa bulunamadı.";
            }
            return;
        }

        $route = $this->routes[$path];
        $isApi = str_starts_with($path, '/api/');

        if ($isApi) {
            // API Authentication & Authorization
            AuthMiddleware::handleApi($GLOBALS['pdo'], $route, $path);
        } else {
            // Web Session Authentication
            if ($path !== '/login' && $path !== '/logout') {
                AuthMiddleware::handle();

                // CSRF Validation for state-changing Web POST requests
                AuthMiddleware::validateCsrf();
            }

            // Route Permission Check
            if (isset($route['permission'])) {
                AuthMiddleware::requirePermission(
                    $GLOBALS['pdo'],
                    $route['permission']
                );
            }
        }

        $controllerClass = $route['controller'];
        $method = $route['method'];

        require_once __DIR__ . '/Controllers/' . $controllerClass . '.php';

        $controller = new $controllerClass($GLOBALS['pdo']);

        $controller->$method();
    }
}