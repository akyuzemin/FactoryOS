<?php

require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Services/AuditService.php';

class AuthController
{
    private User $user;
    private AuditService $auditService;

    public function __construct(PDO $pdo)
    {
        $this->user = new User($pdo);
        $this->auditService = new AuditService($pdo);
    }

    public function login(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => $isHttps
            ]);
            session_start();
        }

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($username === '' || $password === '') {
                $error = 'Kullanıcı adı ve şifre zorunludur.';
            } else {

                $user = $this->user->findByUsername($username);

                if (
                    $user &&
                    $user['is_active'] == 1 &&
                    password_verify($password, $user['password_hash'])
                ) {

                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role_id'] = $user['role_id'];
                    $_SESSION['role_name'] = $user['role_name'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];

                    // Audit Log: Successful Login
                    $this->auditService->logAuth(
                        action: 'LOGIN_SUCCESS',
                        description: "Kullanıcı ({$user['username']}) sisteme başarılı giriş yaptı.",
                        userId: (int)$user['id'],
                        username: $user['username'],
                        success: true
                    );

                    header('Location: /stok-takip/public/dashboard');
                    exit;

                } else {
                    $error = 'Kullanıcı adı veya şifre hatalı.';

                    // Audit Log: Failed Login
                    $this->auditService->logAuth(
                        action: 'LOGIN_FAILED',
                        description: "Başarısız giriş denemesi: '{$username}'",
                        userId: $user ? (int)$user['id'] : null,
                        username: $username,
                        success: false
                    );
                }
            }
        }

        require __DIR__ . '/../../views/auth/login.php';
    }

    public function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $userId = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? 'Bilinmiyor';

        if ($userId) {
            // Audit Log: Logout
            $this->auditService->logAuth(
                action: 'LOGOUT',
                description: "Kullanıcı ({$username}) oturumu kapattı.",
                userId: (int)$userId,
                username: $username,
                success: true
            );
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $cookieParams = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $cookieParams['path'],
                $cookieParams['domain'],
                $cookieParams['secure'],
                $cookieParams['httponly']
            );
        }

        session_destroy();

        header('Location: /stok-takip/public/login');
        exit;
    }
}