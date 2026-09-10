<?php

/**
 * AŞAMA 10 — GÜVENLİK VE KURUMSAL YAPI KAPSAMLI TEST PROTOKOLÜ
 */

ob_start();
session_start();

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/app/Services/ApiAuthService.php';
require_once dirname(__DIR__, 2) . '/app/Services/AuditService.php';
require_once dirname(__DIR__, 2) . '/app/Services/CsrfService.php';
require_once dirname(__DIR__, 2) . '/app/Controllers/AuthController.php';

$pdo = (new Database())->connect();
$GLOBALS['pdo'] = $pdo;

echo "========================================================================\n";
echo "AŞAMA 10 — GÜVENLİK VE KURUMSAL YAPI DOĞRULAMA TEST PROTOKOLÜ\n";
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
// TEST 1: Admin erişimi -> PASS (200)
// ------------------------------------------------------------------------
$res1 = requestHttp('GET', '/users', null, [
    'user_id' => 1,
    'username' => 'admin',
    'role_id' => 1
]);
reportTest('1. Admin Kullanıcı Yönetimi Erişimi', $res1['code'] === 200, "HTTP Status: {$res1['code']}");

// ------------------------------------------------------------------------
// TEST 2: Yönetici erişimi -> PASS (200)
// ------------------------------------------------------------------------
$res2 = requestHttp('GET', '/admin/dashboard', null, [
    'user_id' => 3,
    'username' => 'yonetici',
    'role_id' => 3
]);
reportTest('2. Yönetici Dashboard Erişimi', $res2['code'] === 200, "HTTP Status: {$res2['code']}");

// ------------------------------------------------------------------------
// TEST 3: Depo kullanıcısının yetkisiz admin işlemi -> 403
// ------------------------------------------------------------------------
$res3 = requestHttp('GET', '/users', null, [
    'user_id' => 2,
    'username' => 'depo',
    'role_id' => 2
]);
reportTest('3. Depo Kullanıcısının /users Erişimi Engeli (403)', $res3['code'] === 403, "HTTP Status: {$res3['code']}");

// ------------------------------------------------------------------------
// TEST 4: Operatörün yetkisiz admin işlemi -> 403
// ------------------------------------------------------------------------
$res4 = requestHttp('GET', '/admin/dashboard', null, [
    'user_id' => 4,
    'username' => 'operator',
    'role_id' => 4
]);
reportTest('4. Operatörün /admin/dashboard Erişimi Engeli (403)', $res4['code'] === 403, "HTTP Status: {$res4['code']}");

// ------------------------------------------------------------------------
// TEST 5: Yetkisiz API isteği -> 401
// ------------------------------------------------------------------------
$res5 = requestHttp('GET', '/api/mes/status?work_order_id=1');
$data5 = json_decode($res5['body'], true);
reportTest('5. Yetkisiz API İsteği Koruması (401)', $res5['code'] === 401 && ($data5['error'] ?? '') === 'AUTHENTICATION_REQUIRED', "HTTP Status: {$res5['code']}");

// ------------------------------------------------------------------------
// TEST 6: Geçersiz API token -> 401
// ------------------------------------------------------------------------
$res6 = requestHttp('GET', '/api/mes/status?work_order_id=1', null, null, [
    'Authorization: Bearer stk_invalid_token_1234567890abcdef'
]);
$data6 = json_decode($res6['body'], true);
reportTest('6. Geçersiz API Token Koruması (401)', $res6['code'] === 401 && ($data6['error'] ?? '') === 'INVALID_TOKEN', "HTTP Status: {$res6['code']}");

// ------------------------------------------------------------------------
// TEST 7: Yetkisi olmayan API token -> 403
// ------------------------------------------------------------------------
$apiAuth = new ApiAuthService($pdo);
$readOnlyToken = $apiAuth->generateToken(2, 'Test Read Only Token', ['stock.view', 'material.view']);
$res7 = requestHttp('POST', '/api/mes/events', [
    'event_id' => 'TEST-FORBIDDEN-01'
], null, [
    'Authorization: Bearer ' . $readOnlyToken['plain_token']
]);
$data7 = json_decode($res7['body'], true);
reportTest('7. Yetkisi Yetersiz API Token Koruması (403)', $res7['code'] === 403 && ($data7['error'] ?? '') === 'INSUFFICIENT_PERMISSIONS', "HTTP Status: {$res7['code']}");

// ------------------------------------------------------------------------
// TEST 8: Geçersiz CSRF token -> işlem reddedildi (403)
// ------------------------------------------------------------------------
$res8 = requestHttp('POST', '/materials/delete', [
    'id' => 99999,
    'csrf_token' => 'invalid_forged_csrf_token_xyz'
], [
    'user_id' => 1,
    'username' => 'admin',
    'role_id' => 1,
    'csrf_token' => 'valid_secret_session_token_123'
]);
reportTest('8. Geçersiz CSRF Token Engeli (403)', $res8['code'] === 403, "HTTP Status: {$res8['code']}");

// ------------------------------------------------------------------------
// TEST 9: Geçerli CSRF token -> işlem başarılı
// ------------------------------------------------------------------------
$validSecret = 'valid_secret_session_token_123';
$res9 = requestHttp('POST', '/materials/delete', [
    'id' => 99999,
    'csrf_token' => $validSecret
], [
    'user_id' => 1,
    'username' => 'admin',
    'role_id' => 1,
    'csrf_token' => $validSecret
]);
reportTest('9. Geçerli CSRF Token Doğrulaması', $res9['code'] === 302 || $res9['code'] === 200, "HTTP Status: {$res9['code']}");

// ------------------------------------------------------------------------
// TEST 10: IDOR erişim denemesi -> reddedildi (404)
// ------------------------------------------------------------------------
$res10 = requestHttp('GET', '/materials/edit?id=99999999', null, [
    'user_id' => 1,
    'username' => 'admin',
    'role_id' => 1
]);
reportTest('10. IDOR ve Geçersiz Kayıt Koruması (404)', $res10['code'] === 404, "HTTP Status: {$res10['code']}");

// ------------------------------------------------------------------------
// TEST 11: Başarılı login audit log -> oluşturuldu
// ------------------------------------------------------------------------
$res11 = requestHttp('POST', '/login', [
    'username' => 'admin',
    'password' => 'Test123!'
]);

$auditLogLogin = $pdo->query("SELECT * FROM audit_logs WHERE action = 'LOGIN_SUCCESS' AND user_id = 1 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
reportTest('11. Başarılı Login Audit Kaydı', !empty($auditLogLogin), "Audit ID: " . ($auditLogLogin['id'] ?? 'N/A'));

// ------------------------------------------------------------------------
// TEST 12: Başarısız login audit log -> oluşturuldu
// ------------------------------------------------------------------------
$res12 = requestHttp('POST', '/login', [
    'username' => 'unauthorized_hacker',
    'password' => 'wrong_pass'
]);

$auditLogFail = $pdo->query("SELECT * FROM audit_logs WHERE action = 'LOGIN_FAILED' AND description LIKE '%unauthorized_hacker%' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
reportTest('12. Başarısız Login Denemesi Audit Kaydı', !empty($auditLogFail), "Audit ID: " . ($auditLogFail['id'] ?? 'N/A'));

// ------------------------------------------------------------------------
// TEST 13: Kritik işlem audit log -> oluşturuldu
// ------------------------------------------------------------------------
$auditService = new AuditService($pdo);
$auditLogged = $auditService->logAction('TEST_MODULE', 'CRITICAL_ACTION_TEST', 'test_entity', 123, 'Kritik güvenlik denetimi testi');
$auditRecord = $pdo->query("SELECT * FROM audit_logs WHERE action = 'CRITICAL_ACTION_TEST' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
reportTest('13. Kritik İşlem Audit Kaydı Doğrulaması', $auditLogged && !empty($auditRecord), "Audit ID: " . ($auditRecord['id'] ?? 'N/A'));

// ------------------------------------------------------------------------
// TEST 14: MES API authentication -> başarılı
// ------------------------------------------------------------------------
$mesToken = $apiAuth->generateToken(1, 'MES Worker Integration Token', ['production.execute', 'production.view', 'api.access']);
$res14 = requestHttp('GET', '/api/mes/lines', null, null, [
    'Authorization: Bearer ' . $mesToken['plain_token']
]);
$data14 = json_decode($res14['body'], true);
reportTest('14. MES API Bearer Token Kimlik Doğrulama', $res14['code'] === 200 && !empty($data14['production_lines']), "HTTP Status: {$res14['code']}");

// ------------------------------------------------------------------------
// TEST 15: MES Worker -> API iletişimi -> başarılı
// ------------------------------------------------------------------------
require_once dirname(__DIR__, 2) . '/app/Services/MesSimulationService.php';
$simService = new MesSimulationService($pdo);
$batchRes = $simService->runDueSimulations(1);
reportTest('15. MES Worker Servis ve Yürütme İletişimi', isset($batchRes['due_count']), "İşlenen simülasyon: {$batchRes['due_count']}");

// ------------------------------------------------------------------------
// TEST 16: MES Simulator -> API iletişimi -> başarılı
// ------------------------------------------------------------------------
$simRes = requestHttp('GET', '/api/mes/status?work_order_id=1', null, [
    'user_id' => 1,
    'username' => 'admin',
    'role_id' => 1
]);
reportTest('16. MES Simulator API Telemetri İletişimi', $simRes['code'] === 200 || $simRes['code'] === 400, "HTTP Status: {$simRes['code']}");

// Temizlik
$pdo->exec("DELETE FROM api_tokens WHERE name LIKE '%Test%'");
$pdo->exec("DELETE FROM audit_logs WHERE action = 'CRITICAL_ACTION_TEST'");

echo "\n========================================================================\n";
echo "AŞAMA 10 GÜVENLİK TESTİ SONUCU: {$passCount} PASS / {$failCount} FAIL\n";
echo "========================================================================\n";

