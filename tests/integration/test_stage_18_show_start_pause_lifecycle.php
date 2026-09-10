<?php

/**
 * AŞAMA 18+: İŞ EMRİ DETAY EKRANI BAŞLAT / DURAKLAT / RESUME YAŞAMDÖNGÜSÜ DOĞRULAMA TESTİ
 */

ob_start();

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/app/Models/Mes.php';
require_once dirname(__DIR__, 2) . '/app/Services/CsrfService.php';

$pdo = (new Database())->connect();
$mesModel = new Mes($pdo);

echo "========================================================================\n";
echo "=== İŞ EMRİ DETAY EKRANI BAŞLAT / DURAKLAT YAŞAMDÖNGÜSÜ DOĞRULAMA TESTİ ===\n";
echo "========================================================================\n\n";

$passCount = 0;
$failCount = 0;

function reportShowTest(string $id, string $name, bool $passed, string $details = ''): void {
    global $passCount, $failCount;
    if ($passed) {
        $passCount++;
        printf("[PASS] %-10s | %-45s | %s\n", $id, $name, $details);
    } else {
        $failCount++;
        printf("[FAIL] %-10s | %-45s | %s\n", $id, $name, $details);
    }
}

$cookieDir = sys_get_temp_dir() . '/stok_show_test_cookies';
if (!is_dir($cookieDir)) {
    @mkdir($cookieDir, 0777, true);
}

function loginShowUser(string $username, string $password): string {
    global $cookieDir;
    $cookieFile = $cookieDir . '/show_' . $username . '.txt';
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

function showRequest(string $cookieFile, string $path, string $method = 'GET', array $data = []): array {
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

// Üretim personeli olarak giriş yap
$uretimCookie = loginShowUser('uretim', 'Test123!');

// Temiz bir test iş emri oluştur (PLANNED durumunda)
$recipeRow = $pdo->query("SELECT id, output_material_id FROM recipes WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$recipeId = (int)($recipeRow['id'] ?? 1);
$matId = (int)($recipeRow['output_material_id'] ?? 1);
$lineId = 1;

$testWoNo = 'WO-LIFECYCLE-' . time();
$pdo->prepare("
    INSERT INTO mes_work_orders (work_order_no, product_material_id, recipe_id, production_line_id, planned_quantity, produced_quantity, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, 10, 0, 'PLANNED', NOW(), NOW())
")->execute([$testWoNo, $matId, $recipeId, $lineId]);
$woId = (int)$pdo->lastInsertId();

// ------------------------------------------------------------------------
// 1. GET iş emri detay -> PASS (HTML formatCountdown ve CSRF token içeriyor)
// ------------------------------------------------------------------------
$getShow = showRequest($uretimCookie, '/mes/work-orders/show?id=' . $woId);
$hasFormatCountdown = strpos($getShow['body'], 'function formatCountdown(sec)') !== false;
preg_match('/const mesCsrfToken = \'([a-f0-9]{64})\';/', $getShow['body'], $m);
$csrfToken = $m[1] ?? '';

reportShowTest('TEST 01', 'GET İş Emri Detay Ekranı Render', $getShow['code'] === 200 && $hasFormatCountdown && !empty($csrfToken), "HTTP {$getShow['code']} | JS formatCountdown & CSRF Token mevcut");

// ------------------------------------------------------------------------
// 2. BAŞLAT -> PLANLANDI'dan RUNNING'e geçiyor -> PASS
// ------------------------------------------------------------------------
$postStart = showRequest($uretimCookie, '/mes/work-orders/status', 'POST', [
    'work_order_id' => $woId,
    'status'        => 'RUNNING',
    'csrf_token'    => $csrfToken
]);
$startJson = json_decode($postStart['body'], true);
$woDb2 = $pdo->query("SELECT status, planned_start_at FROM mes_work_orders WHERE id = {$woId}")->fetch(PDO::FETCH_ASSOC);

reportShowTest('TEST 02', 'BAŞLAT -> PLANNED to RUNNING', $startJson['success'] === true && $woDb2['status'] === 'RUNNING', "HTTP {$postStart['code']} | DB Status: {$woDb2['status']}");

// ------------------------------------------------------------------------
// 3. started_at DB'ye yazılıyor -> PASS
// ------------------------------------------------------------------------
$hasStartedAt = !empty($woDb2['planned_start_at']);
reportShowTest('TEST 03', 'started_at DB Kaydı Doğrulaması', $hasStartedAt, "planned_start_at: " . ($woDb2['planned_start_at'] ?? 'NULL'));

// ------------------------------------------------------------------------
// 4. Timer başlıyor -> PASS (next_run_at ve remaining_seconds)
// ------------------------------------------------------------------------
$simDb4 = $pdo->query("SELECT is_active, next_run_at, interval_seconds FROM mes_simulations WHERE work_order_id = {$woId}")->fetch(PDO::FETCH_ASSOC);
$timerRunning = ((int)$simDb4['is_active'] === 1 && !empty($simDb4['next_run_at']) && strtotime($simDb4['next_run_at']) > time());
reportShowTest('TEST 04', 'Timer Başlatma & Sayım', $timerRunning, "is_active: 1 | next_run_at: {$simDb4['next_run_at']}");

// ------------------------------------------------------------------------
// 5. DURAKLAT -> PAUSED -> PASS
// ------------------------------------------------------------------------
$postPause = showRequest($uretimCookie, '/mes/work-orders/status', 'POST', [
    'work_order_id' => $woId,
    'status'        => 'PAUSED',
    'csrf_token'    => $csrfToken
]);
$pauseJson = json_decode($postPause['body'], true);
$woDb5 = $pdo->query("SELECT status FROM mes_work_orders WHERE id = {$woId}")->fetch(PDO::FETCH_ASSOC);
reportShowTest('TEST 05', 'DURAKLAT -> RUNNING to PAUSED', $pauseJson['success'] === true && $woDb5['status'] === 'PAUSED', "HTTP {$postPause['code']} | DB Status: {$woDb5['status']}");

// ------------------------------------------------------------------------
// 6. Timer duruyor -> PASS (paused_remaining_seconds kaydedildi)
// ------------------------------------------------------------------------
$simDb6 = $pdo->query("SELECT is_active, paused_remaining_seconds FROM mes_simulations WHERE work_order_id = {$woId}")->fetch(PDO::FETCH_ASSOC);
$timerPaused = ((int)$simDb6['is_active'] === 0 && !empty($simDb6['paused_remaining_seconds']));
reportShowTest('TEST 06', 'Timer Durdurma & Kalan Süre Kaydı', $timerPaused, "is_active: 0 | paused_remaining_seconds: {$simDb6['paused_remaining_seconds']} sn");

// ------------------------------------------------------------------------
// 7. DEVAM ET -> RUNNING -> PASS
// ------------------------------------------------------------------------
$postResume = showRequest($uretimCookie, '/mes/work-orders/status', 'POST', [
    'work_order_id' => $woId,
    'status'        => 'RUNNING',
    'csrf_token'    => $csrfToken
]);
$resumeJson = json_decode($postResume['body'], true);
$woDb7 = $pdo->query("SELECT status FROM mes_work_orders WHERE id = {$woId}")->fetch(PDO::FETCH_ASSOC);
reportShowTest('TEST 07', 'DEVAM ET -> PAUSED to RUNNING', $resumeJson['success'] === true && $woDb7['status'] === 'RUNNING', "HTTP {$postResume['code']} | DB Status: {$woDb7['status']}");

// ------------------------------------------------------------------------
// 8. Timer kaldığı yerden devam ediyor -> PASS
// ------------------------------------------------------------------------
$simDb8 = $pdo->query("SELECT is_active, next_run_at FROM mes_simulations WHERE work_order_id = {$woId}")->fetch(PDO::FETCH_ASSOC);
$remainingAfterResume = strtotime($simDb8['next_run_at']) - time();
$timerResumed = ((int)$simDb8['is_active'] === 1 && $remainingAfterResume <= (int)$simDb6['paused_remaining_seconds'] + 1);
reportShowTest('TEST 08', 'Timer Kaldığı Yerden Devam Etti', $timerResumed, "Kalan {$remainingAfterResume} sn uygulandı");

// ------------------------------------------------------------------------
// 9. Double-click -> Yalnızca bir state transition -> PASS
// ------------------------------------------------------------------------
$postDouble = showRequest($uretimCookie, '/mes/work-orders/status', 'POST', [
    'work_order_id' => $woId,
    'status'        => 'RUNNING',
    'csrf_token'    => $csrfToken
]);
$doubleJson = json_decode($postDouble['body'], true);
$simDb9 = $pdo->query("SELECT next_run_at FROM mes_simulations WHERE work_order_id = {$woId}")->fetch(PDO::FETCH_ASSOC);
$idempotent = ($doubleJson['success'] === true && $simDb9['next_run_at'] === $simDb8['next_run_at']);
reportShowTest('TEST 09', 'Double-Click / Idempotency Korunumu', $idempotent, "İkinci start çağrısı sayacı sıfırlamadı");

// ------------------------------------------------------------------------
// 10. CSRF'siz start request -> 403 -> PASS
// ------------------------------------------------------------------------
$postNoCsrf = showRequest($uretimCookie, '/mes/work-orders/status', 'POST', [
    'work_order_id' => $woId,
    'status'        => 'PAUSED'
    // CSRF yok
]);
reportShowTest('TEST 10', 'CSRF\'siz Start/Status Talebi Reddi', $postNoCsrf['code'] === 403, "HTTP {$postNoCsrf['code']} Forbidden");

// ------------------------------------------------------------------------
// 11. Geçersiz CSRF -> 403 -> PASS
// ------------------------------------------------------------------------
$postFakeCsrf = showRequest($uretimCookie, '/mes/work-orders/status', 'POST', [
    'work_order_id' => $woId,
    'status'        => 'PAUSED',
    'csrf_token'    => 'attacker_fake_token_987654321'
]);
reportShowTest('TEST 11', 'Sahte CSRF ile Talebi Reddi', $postFakeCsrf['code'] === 403, "HTTP {$postFakeCsrf['code']} Forbidden");

// ------------------------------------------------------------------------
// 12. Mevcut RBAC testleri bozulmuyor -> PASS
// ------------------------------------------------------------------------
// Depo personelinin iş emri durumunu değiştiremediğini test et
$depoCookie = loginShowUser('depo', 'Test123!');
$getDepoShow = showRequest($depoCookie, '/mes/work-orders/show?id=' . $woId);
preg_match('/const mesCsrfToken = \'([a-f0-9]{64})\';/', $getDepoShow['body'], $mDepo);
$depoCsrf = $mDepo[1] ?? '';

$postDepoStatus = showRequest($depoCookie, '/mes/work-orders/status', 'POST', [
    'work_order_id' => $woId,
    'status'        => 'RUNNING',
    'csrf_token'    => $depoCsrf
]);
reportShowTest('TEST 12', 'RBAC Yetki İzolasyonu (Depo Personeli Blok)', $postDepoStatus['code'] === 403, "HTTP {$postDepoStatus['code']} Forbidden (Depo Personeli yetkisiz)");

// Temizlik
$pdo->prepare("DELETE FROM mes_simulations WHERE work_order_id = ?")->execute([$woId]);
$pdo->prepare("DELETE FROM mes_work_orders WHERE id = ?")->execute([$woId]);

$total = $passCount + $failCount;
echo "\n========================================================================\n";
echo "İŞ EMRİ DETAY YAŞAMDÖNGÜSÜ TEST SONUCU: {$passCount}/{$total} PASS\n";
echo "========================================================================\n";

