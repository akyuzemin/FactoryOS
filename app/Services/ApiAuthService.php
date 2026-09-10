<?php

class ApiAuthService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Creates a new API token, hashes it with SHA-256 for storage,
     * and returns the plain text token once.
     */
    public function generateToken(
        ?int $userId,
        string $name,
        ?array $permissions = null,
        ?string $expiresAt = null
    ): array {
        $randomHex = bin2hex(random_bytes(20));
        $prefix = 'stk_' . substr($randomHex, 0, 6);
        $plainToken = 'stk_' . $randomHex;
        $tokenHash = hash('sha256', $plainToken);

        $sql = "
            INSERT INTO api_tokens (
                user_id, name, token_prefix, token_hash,
                permissions, is_active, expires_at, created_at
            ) VALUES (
                :user_id, :name, :token_prefix, :token_hash,
                :permissions, 1, :expires_at, NOW()
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id'      => $userId,
            ':name'         => $name,
            ':token_prefix' => $prefix,
            ':token_hash'   => $tokenHash,
            ':permissions'  => $permissions !== null ? json_encode($permissions, JSON_UNESCAPED_UNICODE) : null,
            ':expires_at'   => $expiresAt
        ]);

        $tokenId = (int)$this->pdo->lastInsertId();

        return [
            'token_id'    => $tokenId,
            'name'        => $name,
            'prefix'      => $prefix,
            'plain_token' => $plainToken,
            'expires_at'  => $expiresAt
        ];
    }

    /**
     * Authenticates a request via Bearer header, X-API-KEY header, or query param.
     */
    public function authenticateFromRequest(): ?array
    {
        $token = $this->extractTokenFromRequest();
        if (!$token) {
            return null;
        }

        return $this->validateToken($token);
    }

    /**
     * Extracts token from Authorization header, X-API-KEY header, or $_GET/$_POST.
     */
    public function extractTokenFromRequest(): ?string
    {
        // 1. Authorization: Bearer <token>
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($authHeader === '' && function_exists('getallheaders')) {
            $allHeaders = getallheaders();
            $authHeader = $allHeaders['Authorization'] ?? ($allHeaders['authorization'] ?? '');
        }
        if ($authHeader === '' && function_exists('apache_request_headers')) {
            $apacheHeaders = apache_request_headers();
            $authHeader = $apacheHeaders['Authorization'] ?? ($apacheHeaders['authorization'] ?? '');
        }

        if ($authHeader !== '' && preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // 2. X-API-KEY Header
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? ($_SERVER['REDIRECT_HTTP_X_API_KEY'] ?? '');
        if ($apiKey === '' && function_exists('getallheaders')) {
            $allHeaders = getallheaders();
            $apiKey = $allHeaders['X-API-KEY'] ?? ($allHeaders['x-api-key'] ?? '');
        }
        if (!empty($apiKey)) {
            return trim($apiKey);
        }

        // 3. Fallback: Query parameter / POST body
        if (!empty($_GET['api_token'])) {
            return trim($_GET['api_token']);
        }
        if (!empty($_POST['api_token'])) {
            return trim($_POST['api_token']);
        }

        return null;
    }

    /**
     * Validates a plain text API token against stored SHA-256 hash.
     */
    public function validateToken(string $plainToken): ?array
    {
        $tokenHash = hash('sha256', $plainToken);

        $sql = "
            SELECT 
                t.*,
                u.username,
                u.role_id,
                u.is_active as user_is_active,
                r.name as role_name
            FROM api_tokens t
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE t.token_hash = :hash
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':hash' => $tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        // Check if token is active
        if ((int)$row['is_active'] !== 1) {
            return null;
        }

        // Check user active status if tied to a user
        if ($row['user_id'] !== null && (int)$row['user_is_active'] !== 1) {
            return null;
        }

        // Check expiration
        if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) {
            return null;
        }

        // Update last_used_at timestamp in background (non-blocking)
        try {
            $upStmt = $this->pdo->prepare("UPDATE api_tokens SET last_used_at = NOW() WHERE id = :id");
            $upStmt->execute([':id' => $row['id']]);
        } catch (Throwable) {}

        $row['permissions_array'] = !empty($row['permissions']) ? json_decode($row['permissions'], true) : null;

        return $row;
    }

    /**
     * Checks if token has a specific permission.
     */
    public function hasPermission(array $tokenRecord, string $permissionName): bool
    {
        if ($tokenRecord['permissions_array'] !== null) {
            if (in_array('*', $tokenRecord['permissions_array'], true)) {
                return true;
            }
            return in_array($permissionName, $tokenRecord['permissions_array'], true);
        }

        if (!empty($tokenRecord['role_id'])) {
            require_once __DIR__ . '/PermissionService.php';
            $permService = new PermissionService($this->pdo);
            return $permService->hasPermission((int)$tokenRecord['role_id'], $permissionName);
        }

        return false;
    }

    /**
     * Revokes an API token.
     */
    public function revokeToken(int $tokenId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE api_tokens SET is_active = 0 WHERE id = :id");
        return $stmt->execute([':id' => $tokenId]);
    }
}

