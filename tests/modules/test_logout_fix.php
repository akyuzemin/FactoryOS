<?php

/**
 * LOGOUT & CSRF FIX VERIFICATION SUITE
 */

ob_start();
session_start();

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/app/Services/ApiAuthService.php';
require_once dirname(__DIR__, 2) . '/app/Services/AuditService.php';
require_once dirname(__DIR__, 2) . '/app/Services/CsrfService.php';

$pdo = (new Database())->connect();
$GLOBALS['pdo'] = $pdo;

echo "========================================================================\n";
echo "LOGOUT & CSRF FIX DOĞRULAMA TEST PROTOKOLÜ\n";
echo "========================================================================\n\n";

$passCount = 0;
$failCount = 0;

function reportTest(string $title, bool $passed, string $details = ''): void {
    global $passCount, $failCount;
    if ($passed) {
        $passCount++;
        echo "  [PASS] {$title}" . ($details ? " -> {$details}" : "") . "\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$title}" . ($details ? " -> {$details}" : "") . "\n";
    }
}

function requestHttp(
    string $method,
    string $url,
    ?array $postData = null,
    ?array $sessionData = null,
    array $headers = []
): array {
    $ch = curl_init();

    if ($sessionData !== null) {
        foreach ($sessionData as $k => $v) {
            $_SESSION[$k] = $v;
        }
    }

    $sessionId = session_id();
    session_write_close();

    $fullUrl = "http://localhost/stok-takip/public" . $url;
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    if ($sessionData !== null) {
        curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . $sessionId);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($postData !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        }
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    @session_start();

    return [
        'code' => $httpCode,
        'body' => $response
    ];
}

// ------------------------------------------------------------------------
// TEST 1: /logout → fatal error vermemeli (HTTP 302 Redirect)
// ------------------------------------------------------------------------
$res1 = requestHttp('GET', '/logout', null, [
    'user_id' => 1,
    'username' => 'admin',
    'role_id' => 1
]);
$hasFatal = (stripos($res1['body'], 'Fatal error') !== false || stripos($res1['body'], 'Call to undefined method') !== false);
reportTest('TEST 1 (/logout fatal error vermemeli)', $res1['code'] === 302 && !$hasFatal, "HTTP Status: {$res1['code']}");

// ------------------------------------------------------------------------
// TEST 2: logout sonrası session sonlanmalı
// ------------------------------------------------------------------------
$testSessionId = 'test_logout_sess_' . bin2hex(random_bytes(8));
session_id($testSessionId);
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role_id'] = 1;
session_write_close();

$ch = curl_init("http://localhost/stok-takip/public/logout");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . $testSessionId);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_exec($ch);
curl_close($ch);

// Now try accessing protected page with same session ID
$ch2 = curl_init("http://localhost/stok-takip/public/users");
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_COOKIE, "PHPSESSID=" . $testSessionId);
curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, false);
curl_exec($ch2);
$protectedHttpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

reportTest('TEST 2 (Logout sonrası oturum tamamen sonlandı)', $protectedHttpCode === 302, "Korumalı sayfa yönlendirme kodu: {$protectedHttpCode}");

// ------------------------------------------------------------------------
// TEST 3: LOGOUT audit kaydı oluşmalı
// ------------------------------------------------------------------------
$auditLogout = $pdo->query("SELECT * FROM audit_logs WHERE action = 'LOGOUT' AND user_id = 1 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
reportTest('TEST 3 (LOGOUT audit log kaydı oluşturuldu)', !empty($auditLogout), "Audit ID: " . ($auditLogout['id'] ?? 'N/A'));

// ------------------------------------------------------------------------
// TEST 4: Geçersiz CSRF token ile POST istekleri 403 vermeli
// ------------------------------------------------------------------------
$res4 = requestHttp('POST', '/materials/delete', [
    'id' => 99999,
    'csrf_token' => 'invalid_fake_token_123'
], [
    'user_id' => 1,
    'username' => 'admin',
    'role_id' => 1,
    'csrf_token' => 'valid_secret_token_abc'
]);
reportTest('TEST 4 (Geçersiz CSRF ile POST isteği engellendi - 403)', $res4['code'] === 403, "HTTP Status: {$res4['code']}");

// ------------------------------------------------------------------------
// TEST 5: Geçerli CSRF token ile POST istekleri çalışmalı
// ------------------------------------------------------------------------
$validSecret = 'valid_secret_token_abc';
$res5 = requestHttp('POST', '/materials/delete', [
    'id' => 99999,
    'csrf_token' => $validSecret
], [
    'user_id' => 1,
    'username' => 'admin',
    'role_id' => 1,
    'csrf_token' => $validSecret
]);
reportTest('TEST 5 (Geçerli CSRF ile POST isteği çalıştı)', $res5['code'] === 302 || $res5['code'] === 200, "HTTP Status: {$res5['code']}");

// ------------------------------------------------------------------------
// TEST 6: API Bearer Token kullanan API istekleri CSRF engeline takılmamalı
// ------------------------------------------------------------------------
$apiAuth = new ApiAuthService($pdo);
$mesToken = $apiAuth->generateToken(1, 'Logout Test MES Token', ['production.execute', 'api.access']);
$res6 = requestHttp('POST', '/api/mes/events', [
    'event_id' => 'EVT-LOGOUT-TEST-' . bin2hex(random_bytes(3))
], null, [
    'Authorization: Bearer ' . $mesToken['plain_token']
]);
reportTest('TEST 6 (Bearer Token API isteği CSRF engeline takılmadı)', $res6['code'] !== 403 || strpos($res6['body'], 'CSRF') === false, "HTTP Status: {$res6['code']}");

// ------------------------------------------------------------------------
// TEST 7: /admin/dashboard RBAC çalışmaya devam etmeli
// ------------------------------------------------------------------------
$res7Admin = requestHttp('GET', '/admin/dashboard', null, [
    'user_id' => 3,
    'username' => 'yonetici',
    'role_id' => 3
]);
$res7Operator = requestHttp('GET', '/admin/dashboard', null, [
    'user_id' => 4,
    'username' => 'operator',
    'role_id' => 4
]);
$okRbac = ($res7Admin['code'] === 200 && $res7Operator['code'] === 403);
reportTest('TEST 7 (/admin/dashboard RBAC yetki kontrolü aktif)', $okRbac, "Yönetici: {$res7Admin['code']} | Operatör: {$res7Operator['code']}");

// Temizlik
$pdo->exec("DELETE FROM api_tokens WHERE name = 'Logout Test MES Token'");

echo "\n========================================================================\n";
echo "TEST SONUCU: {$passCount} PASS / {$failCount} FAIL\n";
echo "========================================================================\n";

