<?php

require_once __DIR__ . '/../Services/ApiAuthService.php';
require_once __DIR__ . '/../Services/CsrfService.php';

class ApiTokenController
{
    private ApiAuthService $apiAuth;

    public function __construct(PDO $pdo)
    {
        $this->apiAuth = new ApiAuthService($pdo);
    }

    public function index(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_start();
        }

        $flashMessage = $_SESSION['flash_message'] ?? null;
        $createdToken = $_SESSION['api_token_created'] ?? null;
        unset($_SESSION['flash_message'], $_SESSION['api_token_created']);

        $tokens = $this->fetchTokens();
        $users = $this->fetchUsers();

        $pageTitle = 'API Anahtarları Yönetimi';
        $activePage = 'api-tokens';

        require __DIR__ . '/../../views/api_tokens/index.php';
    }

    public function create(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo '405 - Geçersiz istek metodu.';
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_start();
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $userId = isset($_POST['user_id']) && $_POST['user_id'] !== '' ? (int) $_POST['user_id'] : null;
        $expiresAt = trim((string) ($_POST['expires_at'] ?? ''));
        $permissions = isset($_POST['permissions']) && is_array($_POST['permissions'])
            ? array_values(array_filter(array_map('trim', $_POST['permissions'])))
            : ['*'];

        if ($name === '') {
            $_SESSION['flash_message'] = 'Token için bir ad girmeniz gerekiyor.';
            $this->redirectBack();
        }

        if ($expiresAt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt)) {
            $_SESSION['flash_message'] = 'Geçersiz son kullanma tarihi.';
            $this->redirectBack();
        }

        try {
            $created = $this->apiAuth->generateToken(
                $userId,
                $name,
                $permissions,
                $expiresAt !== '' ? $expiresAt : null
            );

            $_SESSION['api_token_created'] = $created;
            $_SESSION['flash_message'] = 'Yeni API token başarıyla oluşturuldu.';
            $this->redirectBack();
        } catch (Throwable $e) {
            $_SESSION['flash_message'] = 'Token oluşturulurken hata oluştu: ' . $e->getMessage();
            $this->redirectBack();
        }
    }

    public function revoke(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo '405 - Geçersiz istek metodu.';
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_start();
        }

        $tokenId = (int) ($_POST['token_id'] ?? 0);

        if ($tokenId <= 0) {
            $_SESSION['flash_message'] = 'İptal edilecek token bulunamadı.';
            $this->redirectBack();
        }

        try {
            $ok = $this->apiAuth->revokeToken($tokenId);
            $_SESSION['flash_message'] = $ok
                ? 'API token başarıyla iptal edildi.'
                : 'API token iptal edilemedi.';
        } catch (Throwable $e) {
            $_SESSION['flash_message'] = 'Token iptal edilirken hata oluştu: ' . $e->getMessage();
        }

        $this->redirectBack();
    }

    private function fetchTokens(): array
    {
        $stmt = $this->apiAuth->getPdo()->prepare(
            'SELECT *
             FROM api_tokens
             ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchUsers(): array
    {
        $pdo = $this->apiAuth->getPdo();
        $stmt = $pdo->prepare(
            'SELECT id, username, first_name, last_name
             FROM users
             WHERE is_active = 1
             ORDER BY username ASC'
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function redirectBack(): never
    {
        if (!headers_sent()) {
            header('Location: /stok-takip/public/api-tokens');
        }
        exit;
    }
}
