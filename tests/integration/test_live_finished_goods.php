<?php

/**
 * TEST: Panel Seri Takip Canlı Güncelleme (Live Polling) Doğrulama Testi
 */

ob_start();
session_start();

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/app/Models/PanelUnit.php';
require_once dirname(__DIR__, 2) . '/app/Services/MesEventIngestionService.php';

$pdo = (new Database())->connect();
$GLOBALS['pdo'] = $pdo;
$panelModel = new PanelUnit($pdo);
$ingestionService = new MesEventIngestionService($pdo);

echo "========================================================================\n";
echo "PANEL SERİ TAKİP CANLI GÜNCELLEME (LIVE POLLING) TEST PROTOKOLÜ\n";
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

function fetchHttp(string $path, ?string $sessCookie = null): array {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $ch = curl_init('http://localhost/stok-takip/public' . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    if ($sessCookie) {
        curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . $sessCookie);
    }

    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $code,
        'body' => $res,
        'json' => json_decode($res, true) ?? []
    ];
}

// ------------------------------------------------------------------------
// 1. Yetkisiz Kullanıcı Güvenlik Testi (HTTP 302 Redirect to Login)
// ------------------------------------------------------------------------
$anonRes = fetchHttp('/finished-goods/live');
reportTest('1. Yetkisiz Kullanıcı Live Endpoint Koruması', $anonRes['code'] === 302 || $anonRes['code'] === 401 || $anonRes['code'] === 403, "HTTP Status: {$anonRes['code']}");

// ------------------------------------------------------------------------
// 2. Yetkili Oturum İle /finished-goods HTML Render Testi
// ------------------------------------------------------------------------
@session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role_id'] = 1;
$sessId = session_id();
session_write_close();

$htmlRes = fetchHttp('/finished-goods', $sessId);
$hasLiveBadge = strpos($htmlRes['body'], 'live-poll-badge') !== false;
$hasTableBody = strpos($htmlRes['body'], 'panel-table-body') !== false;
$hasKpiCounters = strpos($htmlRes['body'], 'kpi-total') !== false && strpos($htmlRes['body'], 'kpi-today-produced') !== false;

reportTest('2. /finished-goods Arayüz Canlı İzleme Elemanları', $htmlRes['code'] === 200 && $hasLiveBadge && $hasTableBody && $hasKpiCounters, "HTTP: {$htmlRes['code']}");

// ------------------------------------------------------------------------
// 3. /finished-goods/live JSON Yanıt Formatı ve Sayaçlar
// ------------------------------------------------------------------------
$initialLastId = $panelModel->getLastPanelId();
$liveRes1 = fetchHttp('/finished-goods/live?last_id=' . $initialLastId, $sessId);

$ok3 = ($liveRes1['code'] === 200 && !empty($liveRes1['json']['success']) && isset($liveRes1['json']['counters']['total']));
reportTest('3. Live Endpoint JSON Yanıtı ve Başlangıç Durumu', $ok3, "Counters Total: " . ($liveRes1['json']['counters']['total'] ?? 0));

// ------------------------------------------------------------------------
// 4. Yeni MES PANEL_COMPLETED Üretim Olayı Gönderimi
// ------------------------------------------------------------------------
// Ensure ample stock
$pdo->exec("UPDATE stock_balances SET quantity = 50000.0 WHERE location_id = 1");

$testWoId = (int)$pdo->query("SELECT id FROM mes_work_orders WHERE status IN ('RUNNING','READY','PLANNED') ORDER BY id ASC LIMIT 1")->fetchColumn();
$woRow = $pdo->query("SELECT * FROM mes_work_orders WHERE id = {$testWoId}")->fetch(PDO::FETCH_ASSOC);

$newSerial = 'SP550W-' . date('Ymd') . '-' . str_pad((string)rand(100000, 999999), 6, '0', STR_PAD_LEFT);
$testEventId = 'MES-LIVE-' . strtoupper(bin2hex(random_bytes(3)));

$ingestResult = $ingestionService->ingestEvent([
    'event_id'            => $testEventId,
    'event_type'          => 'PANEL_COMPLETED',
    'work_order_id'       => $testWoId,
    'work_order_no'       => $woRow['work_order_no'],
    'production_line_id'  => (int)$woRow['production_line_id'],
    'product_material_id' => (int)$woRow['product_material_id'],
    'serial_no'           => $newSerial,
    'quantity'            => 1.0,
    'source'              => 'MES'
]);

reportTest('4. Yeni MES PANEL_COMPLETED Üretim Olayı İşlendi', !empty($ingestResult['success']), "Event: {$testEventId}, Serial: {$newSerial}");

// ------------------------------------------------------------------------
// 5. Polling İle Yeni Panelin Otomatik Yakalanması
// ------------------------------------------------------------------------
$liveRes2 = fetchHttp('/finished-goods/live?last_id=' . $initialLastId, $sessId);
$newItems = $liveRes2['json']['new_items'] ?? [];
$foundNewPanel = false;
foreach ($newItems as $item) {
    if ($item['serial_no'] === $newSerial) {
        $foundNewPanel = true;
        break;
    }
}

$ok5 = ($liveRes2['code'] === 200 && count($newItems) >= 1 && $foundNewPanel);
reportTest('5. Live Polling İle Yeni Panelin Algılanması (F5 Olmadan)', (bool)$ok5, "code=".json_encode($liveRes2['code']).", expected=".$newSerial.", actual=".(isset($newItems[0]['serial_no']) ? $newItems[0]['serial_no'] : 'none'));

// ------------------------------------------------------------------------
// 6. Duplicate Engelleme: İkinci Polling Çağrısında new_items Boş Olmalı
// ------------------------------------------------------------------------
$newLastId = (int)($liveRes2['json']['last_id'] ?? 0);
$liveRes3 = fetchHttp('/finished-goods/live?last_id=' . $newLastId, $sessId);
$itemsAfter = $liveRes3['json']['new_items'] ?? [];

$ok6 = ($liveRes3['code'] === 200 && empty($itemsAfter) && ($liveRes3['json']['new_count'] ?? 0) === 0);
reportTest('6. Duplicate UI Koruması (İkinci Sorguda 0 Yeni Kayıt)', $ok6, "New Count: " . ($liveRes3['json']['new_count'] ?? -1));

// ------------------------------------------------------------------------
// 7. Filtre Bütünlüğü: Başka Bir Seri Filtrelendiğinde Yeni Panelin Filtreye Takılması
// ------------------------------------------------------------------------
$filteredRes = fetchHttp('/finished-goods/live?last_id=' . $initialLastId . '&serial_no=NON_EXISTENT_SERIAL_XYZ', $sessId);
$filteredItems = $filteredRes['json']['new_items'] ?? [];

reportTest('7. Filtre Koruması (Filtre Uymuyorsa Listeye Eklenmez)', empty($filteredItems), "Dönen kayıt: " . count($filteredItems));

// ------------------------------------------------------------------------
// 8. Sayaçların Doğruluğu (Total ve TodayProduced Artışı)
// ------------------------------------------------------------------------
$counterTotal = $liveRes2['json']['counters']['total'] ?? 0;
$counterToday = $liveRes2['json']['counters']['today_produced'] ?? 0;
$dbTotal = (int)$pdo->query("SELECT COUNT(*) FROM panel_units")->fetchColumn();

$ok8 = ($counterTotal === $dbTotal && $counterToday > 0);
reportTest('8. Canlı KPI Sayaçlarının DB ile Birebir Eşleşmesi', $ok8, "DB Total: {$dbTotal}, Canlı Total: {$counterTotal}");

@session_start();

echo "\n========================================================================\n";
echo "TEST SONUCU: {$passCount} PASS / {$failCount} FAIL\n";
echo "========================================================================\n";
