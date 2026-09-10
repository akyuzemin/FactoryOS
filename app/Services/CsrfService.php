<?php

class CsrfService
{
    /**
     * Generates or retrieves existing CSRF token for the session.
     */
    public static function getToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Alias for getToken().
     */
    public static function generateToken(): string
    {
        return self::getToken();
    }

    /**
     * Validates incoming CSRF token against session.
     */
    public static function validateToken(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_start();
        }

        $sessionToken = $_SESSION['csrf_token'] ?? null;
        if (!$sessionToken || !$token) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Extracts CSRF token from POST body or HTTP headers.
     */
    public static function extractTokenFromRequest(): ?string
    {
        if (!empty($_POST['csrf_token'])) {
            return trim($_POST['csrf_token']);
        }
        if (!empty($_POST['_token'])) {
            return trim($_POST['_token']);
        }
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return trim($_SERVER['HTTP_X_CSRF_TOKEN']);
        }
        if (!empty($_SERVER['HTTP_X_XSRF_TOKEN'])) {
            return trim($_SERVER['HTTP_X_XSRF_TOKEN']);
        }
        return null;
    }

    /**
     * Generates an HTML hidden input tag with CSRF token.
     */
    public static function tokenField(): string
    {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    /**
     * Alias for tokenField().
     */
    public static function renderInput(): string
    {
        return self::tokenField();
    }
}

