<?php

/**
 * AŞAMA 18+: MES İŞ EMRİ OLUŞTURMA CSRF DOĞRULAMA TEST PROTOKOLÜ
 */

ob_start();

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/app/Services/CsrfService.php';

$pdo = (new Database())->connect();

echo "========================================================================\n";
echo "=== MES İŞ EMRİ OLUŞTURMA & CSRF DOĞRULAMA TESTİ ===\n";
echo "========================================================================\n\n";

$passCount = 0;
$failCount = 0;

function reportCsrfTest(string $id, string $name, bool $passed, string $details = ''): void {
    global $passCount, $failCount;
    if ($passed) {
        $passCount++;
        printf("[PASS] %-10s | %-45s | %s\n", $id, $name, $details);
    } else {
        $failCount++;
        printf("[FAIL] %-10s | %-45s | %s\n", $id, $name, $details);
    }
}

$cookieDir = sys_get_temp_dir() . '/stok_csrf_test_cookies';
if (!is_dir($cookieDir)) {
    @mkdir($cookieDir, 0777, true);
}

function loginCsrfUser(string $username, string $password): string {
    global $cookieDir;
    $cookieFile = $cookieDir . '/csrf_' . $username . '.txt';
    if (file_exists($cookieFile)) {
        @unlink($cookieFile);
    }

    $ch = curl_init('http://localhost/stok-takip/public/login');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['username' => $username, 'password' => $password]));
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);

    return $cookieFile;
}

function csrfRequest(string $cookieFile, string $path, string $method = 'GET', array $data = []): array {
    $ch = curl_init('http://localhost/stok-takip/public' . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return [
        'code' => (int)$code,
        'headers' => substr($raw, 0, $headerSize),
        'body' => substr($raw, $headerSize)
    ];
}

// Üretim Personeli oturumu başlat
$uretimCookie = loginCsrfUser('uretim', 'Test123!');

// ------------------------------------------------------------------------
// TEST 0: GET /mes/work-orders/create sayfasında CSRF Token render doğrulaması
// ------------------------------------------------------------------------
$getCreate = csrfRequest($uretimCookie, '/mes/work-orders/create');
$hasCsrfField = strpos($getCreate['body'], 'name="csrf_token"') !== false;
preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $getCreate['body'], $m);
$extractedToken = $m[1] ?? '';

reportCsrfTest('TEST 0', 'GET /mes/work-orders/create Form CSRF Render', $getCreate['code'] === 200 && $hasCsrfField && !empty($extractedToken), "HTTP {$getCreate['code']} | CSRF Token: " . substr($extractedToken, 0, 10) . '...');

// ------------------------------------------------------------------------
// TEST 1: İş Emri Oluştur -> Geçerli CSRF -> Başarılı Oluşturma (HTTP 302)
// ------------------------------------------------------------------------
$testWoNoValid = 'WO-VALID-' . time();
$postValid = csrfRequest($uretimCookie, '/mes/work-orders/store', 'POST', [
    'work_order_no'       => $testWoNoValid,
    'product_material_id' => 1,
    'production_line_id'  => 1,
    'planned_quantity'    => 10,
    'interval_seconds'    => 180,
    'csrf_token'          => $extractedToken
]);

$isRedirectShow = ($postValid['code'] === 302 && strpos($postValid['headers'], '/mes/work-orders/show') !== false);
$dbCreated = (int)$pdo->query("SELECT COUNT(*) FROM mes_work_orders WHERE work_order_no = '{$testWoNoValid}'")->fetchColumn();

reportCsrfTest('TEST 1', 'İş Emri Oluştur (Geçerli CSRF)', $isRedirectShow && $dbCreated === 1, "HTTP {$postValid['code']} -> /mes/work-orders/show | DB Kayıt: {$dbCreated}");

// ------------------------------------------------------------------------
// TEST 2: İş Emri Oluştur -> CSRF Olmadan POST -> HTTP 403 Forbidden
// ------------------------------------------------------------------------
$testWoNoNoCsrf = 'WO-NOCSRF-' . time();
$postNoCsrf = csrfRequest($uretimCookie, '/mes/work-orders/store', 'POST', [
    'work_order_no'       => $testWoNoNoCsrf,
    'product_material_id' => 1,
    'production_line_id'  => 1,
    'planned_quantity'    => 10,
    'interval_seconds'    => 180
    // CSRF token yok!
]);

$dbNotCreated2 = (int)$pdo->query("SELECT COUNT(*) FROM mes_work_orders WHERE work_order_no = '{$testWoNoNoCsrf}'")->fetchColumn();
reportCsrfTest('TEST 2', 'İş Emri Oluştur (CSRF Olmadan)', $postNoCsrf['code'] === 403 && $dbNotCreated2 === 0, "HTTP {$postNoCsrf['code']} Forbidden | DB Kayıt: {$dbNotCreated2}");

// ------------------------------------------------------------------------
// TEST 3: İş Emri Oluştur -> Sahte CSRF -> HTTP 403 Forbidden
// ------------------------------------------------------------------------
$testWoNoFakeCsrf = 'WO-FAKECSRF-' . time();
$postFakeCsrf = csrfRequest($uretimCookie, '/mes/work-orders/store', 'POST', [
    'work_order_no'       => $testWoNoFakeCsrf,
    'product_material_id' => 1,
    'production_line_id'  => 1,
    'planned_quantity'    => 10,
    'interval_seconds'    => 180,
    'csrf_token'          => 'attacker_crafted_fake_csrf_token_123456789'
]);

$dbNotCreated3 = (int)$pdo->query("SELECT COUNT(*) FROM mes_work_orders WHERE work_order_no = '{$testWoNoFakeCsrf}'")->fetchColumn();
reportCsrfTest('TEST 3', 'İş Emri Oluştur (Sahte CSRF)', $postFakeCsrf['code'] === 403 && $dbNotCreated3 === 0, "HTTP {$postFakeCsrf['code']} Forbidden | DB Kayıt: {$dbNotCreated3}");

// Temizlik
$pdo->prepare("DELETE FROM mes_simulations WHERE work_order_id IN (SELECT id FROM mes_work_orders WHERE work_order_no = ?)")->execute([$testWoNoValid]);
$pdo->prepare("DELETE FROM mes_work_orders WHERE work_order_no = ?")->execute([$testWoNoValid]);

$total = $passCount + $failCount;
echo "\n========================================================================\n";
echo "CSRF DOĞRULAMA TEST SONUCU: {$passCount}/{$total} PASS\n";
echo "========================================================================\n";

