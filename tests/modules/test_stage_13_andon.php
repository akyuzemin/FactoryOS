<?php

/**
 * AŞAMA 13: CANLI ANDON & FABRİKA VİTRİNİ KAPSAMLI DOĞRULAMA TEST PROTOKOLÜ
 */

ob_start();

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/app/Services/AndonService.php';
require_once dirname(__DIR__, 2) . '/app/Services/OeeCalculationService.php';
require_once dirname(__DIR__, 2) . '/app/Services/DowntimeManagementService.php';

$pdo = (new Database())->connect();
$GLOBALS['pdo'] = $pdo;

$andonService = new AndonService($pdo);
$oeeService = new OeeCalculationService($pdo);
$downtimeService = new DowntimeManagementService($pdo);

echo "========================================================================\n";
echo "=== AŞAMA 13 CANLI ANDON TEST RAPORU ===\n";
echo "========================================================================\n\n";

$passCount = 0;
$failCount = 0;

function reportTest(string $testNo, string $title, bool $passed, string $details = ''): void {
    global $passCount, $failCount;
    if ($passed) {
        $passCount++;
        echo "[PASS] Test {$testNo} - {$title}" . ($details ? " -> {$details}" : "") . "\n";
    } else {
        $failCount++;
        echo "[FAIL] Test {$testNo} - {$title}" . ($details ? " -> {$details}" : "") . "\n";
    }
}

function loginSession(string $username, string $password): string {
    $ch = curl_init('http://localhost/stok-takip/public/login');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['username' => $username, 'password' => $password]));
    curl_setopt($ch, CURLOPT_HEADER, true);
    $res = curl_exec($ch);
    curl_close($ch);

    preg_match_all('/Set-Cookie:\s*PHPSESSID=([^;]+)/i', $res, $matches);
    return !empty($matches[1]) ? end($matches[1]) : '';
}

function fetchHttp(string $path, ?string $sessCookie = null): array {
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

$adminSess = loginSession('admin', 'Test123!');
$yoneticiSess = loginSession('yonetici', 'Test123!');
$operatorSess = loginSession('operator', 'Test123!');

// ------------------------------------------------------------------------
// Test 01: /andon Sayfa Erişimi ve Temel Render (HTTP 200)
// ------------------------------------------------------------------------
$andonPageRes = fetchHttp('/andon', $adminSess);
$hasTitle = strpos($andonPageRes['body'], 'CANLI ANDON &amp; FABRİKA VİTRİNİ') !== false;
$hasClock = strpos($andonPageRes['body'], 'andon-clock') !== false;
$hasLinesGrid = strpos($andonPageRes['body'], 'andon-lines-grid') !== false;

$ok01 = ($andonPageRes['code'] === 200 && $hasTitle && $hasClock && $hasLinesGrid);
reportTest('01', 'Canlı Andon Ekranı Render Doğrulaması (GET /andon HTTP 200)', $ok01, "HTTP: {$andonPageRes['code']}, Başlık/Saat/Grid: Mevcut");

// ------------------------------------------------------------------------
// Test 02: 4 Üretim Hattının Tamamının Görsel Listelenmesi
// ------------------------------------------------------------------------
$hasLam1 = strpos($andonPageRes['body'], 'LINE-LAM-1') !== false;
$hasLam2 = strpos($andonPageRes['body'], 'LINE-LAM-2') !== false;
$hasStr1 = strpos($andonPageRes['body'], 'LINE-STR-1') !== false;
$hasTest1 = strpos($andonPageRes['body'], 'LINE-TEST-1') !== false;

$ok02 = ($hasLam1 && $hasLam2 && $hasStr1 && $hasTest1);
reportTest('02', '4 Üretim Hattının (Laminatör 1-2, Stringer, Flaş Test) Varlığı', $ok02, "Laminatör 1, 2, Stringer, Test hatları DOM'da mevcut.");

// ------------------------------------------------------------------------
// Test 03: /andon/api/live JSON Telemetri Endpoint Doğrulaması
// ------------------------------------------------------------------------
$liveApiRes = fetchHttp('/andon/api/live', $adminSess);
$hasSuccess = !empty($liveApiRes['json']['success']);
$hasData = !empty($liveApiRes['json']['data']);
$linesCount = count($liveApiRes['json']['data']['lines'] ?? []);

$ok03 = ($liveApiRes['code'] === 200 && $hasSuccess && $hasData && $linesCount >= 4);
reportTest('03', '/andon/api/live Canlı Telemetri JSON API', $ok03, "HTTP: {$liveApiRes['code']}, Hat Sayısı: {$linesCount}");

// ------------------------------------------------------------------------
// Test 04: Aktif Vardiya Bilgileri ve Kalan Süre Hesabı
// ------------------------------------------------------------------------
$shiftInfo = $liveApiRes['json']['data']['shift'] ?? [];
$hasShiftName = !empty($shiftInfo['name']);
$hasRemainingMin = isset($shiftInfo['remaining_minutes']) && (float)$shiftInfo['remaining_minutes'] >= 0;
$hasProgressPct = isset($shiftInfo['progress_pct']) && (float)$shiftInfo['progress_pct'] <= 100;

$ok04 = ($hasShiftName && $hasRemainingMin && $hasProgressPct);
reportTest('04', 'Vardiya Sınırları ve Kalan Süre Hesaplama', $ok04, "Vardiya: {$shiftInfo['name']}, Kalan: {$shiftInfo['remaining_minutes']} dk (%{$shiftInfo['progress_pct']})");

// ------------------------------------------------------------------------
// Test 05: Fabrika Geneli Üst KPI Metrikleri
// ------------------------------------------------------------------------
$fs = $liveApiRes['json']['data']['factory_summary'] ?? [];
$hasTotProd = isset($fs['total_produced_qty']);
$hasTotTarget = isset($fs['total_target_qty']);
$hasRealPct = isset($fs['realization_pct']);
$hasOee = isset($fs['overall_oee']);

$ok05 = ($hasTotProd && $hasTotTarget && $hasRealPct && $hasOee);
reportTest('05', 'Fabrika Üst KPI Göstergeleri (Üretim, Hedef, Gerçekleşme, OEE)', $ok05, "Üretim: {$fs['total_produced_qty']}/{$fs['total_target_qty']} (Gerçekleşme: %{$fs['realization_pct']}, OEE: %{$fs['overall_oee']})");

// ------------------------------------------------------------------------
// Test 06: RUNNING / IDLE Durum Mantığı
// ------------------------------------------------------------------------
$lines = $liveApiRes['json']['data']['lines'] ?? [];
$statuses = array_column($lines, 'status');
$validStatuses = ['RUNNING', 'IDLE', 'FAULT', 'MAINTENANCE'];
$allValid = true;
foreach ($statuses as $st) {
    if (!in_array($st, $validStatuses, true)) {
        $allValid = false;
        break;
    }
}

$ok06 = ($allValid && count($statuses) >= 4);
reportTest('06', 'Hat Durum Enum Değerleri (RUNNING, IDLE, FAULT, MAINTENANCE)', $ok06, "Mevcut Durumlar: " . implode(', ', $statuses));

// ------------------------------------------------------------------------
// Test 07: Hat FAULT Duruşuna Geçtiğinde Canlı Andon Bildirimi
// ------------------------------------------------------------------------
// Line 1'e kontrollü test duruşu aç
$pdo->exec("UPDATE line_downtimes SET status = 'CLOSED', ended_at = NOW() WHERE line_id = 1 AND status = 'OPEN'");
$unplanReason = $pdo->query("SELECT id, code, name FROM downtime_reasons WHERE is_planned = 0 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$downtimeService->startDowntime(1, (int)$unplanReason['id'], 'ANDON-TEST-FAULT', null, 1);

// Canlı telemetriyi çek
$faultTeleRes = fetchHttp('/andon/api/live', $adminSess);
$line1Data = null;
foreach ($faultTeleRes['json']['data']['lines'] ?? [] as $l) {
    if ((int)$l['id'] === 1) {
        $line1Data = $l;
        break;
    }
}

$hasFaultStatus = ($line1Data && $line1Data['status'] === 'FAULT');
$hasActiveDt = !empty($line1Data['active_downtime']);
$hasBannerDt = !empty($faultTeleRes['json']['data']['active_downtimes']);

$ok07 = ($hasFaultStatus && $hasActiveDt && $hasBannerDt);
reportTest('07', 'Hat Arızalandığında Canlı FAULT Durumu & Aktif Duruş Bildirimi', $ok07, "Line 1 Durum: " . ($line1Data['status'] ?? 'YOK') . ", Duruş: " . ($line1Data['active_downtime']['reason_name'] ?? 'YOK'));

// ------------------------------------------------------------------------
// Test 08: Aktif Duruş Süresinin Hesaplanması (Stopwatch)
// ------------------------------------------------------------------------
$dtDurationSec = (int)($line1Data['active_downtime']['duration_seconds'] ?? 0);
$hasDuration = ($dtDurationSec >= 0 && !empty($line1Data['active_downtime']['formatted_duration']));

$ok08 = ($hasDuration);
reportTest('08', 'Aktif Duruş Kronometresi (duration_seconds & formatted_duration)', $ok08, "Süre: {$line1Data['active_downtime']['formatted_duration']} ({$dtDurationSec}s)");

// ------------------------------------------------------------------------
// Test 09: Duruş Kapatıldığında Hattın Normale Dönüşü
// ------------------------------------------------------------------------
$openDtId = (int)($line1Data['active_downtime']['downtime_id'] ?? 0);
if ($openDtId > 0) {
    $downtimeService->endDowntime($openDtId, 'ANDON-TEST-RESOLVED', null, 1);
}
$pdo->exec("UPDATE line_downtimes SET status = 'CLOSED', ended_at = NOW() WHERE line_id = 1 AND status = 'OPEN'");

$resolvedTeleRes = fetchHttp('/andon/api/live', $adminSess);
$line1Resolved = null;
foreach ($resolvedTeleRes['json']['data']['lines'] ?? [] as $l) {
    if ((int)$l['id'] === 1) {
        $line1Resolved = $l;
        break;
    }
}

$isRestored = ($line1Resolved && $line1Resolved['status'] !== 'FAULT' && empty($line1Resolved['active_downtime']));
$ok09 = ($isRestored);
reportTest('09', 'Duruş Kapatıldığında Hattın Canlı Olarak Normale Dönüşü', $ok09, "Line 1 Yeni Durum: " . ($line1Resolved['status'] ?? 'YOK'));

// ------------------------------------------------------------------------
// Test 10: OEE ve A/P/Q Metriklerinin OeeCalculationService ile Uyumu
// ------------------------------------------------------------------------
$calcShiftOee = $oeeService->calculateShiftOee(1, (int)$shiftInfo['id'], date('Y-m-d'));
$andonLine1Oee = (float)($line1Resolved['oee_pct'] ?? 0.0);
$srvLine1Oee = (float)($calcShiftOee['oee_pct'] ?? 0.0);

$ok10 = ($andonLine1Oee == $srvLine1Oee);
reportTest('10', 'Andon OEE Değerlerinin OeeCalculationService İle Tutarlılığı', $ok10, "Andon: %{$andonLine1Oee} == OEE Servis: %{$srvLine1Oee}");

// ------------------------------------------------------------------------
// Test 11: TV Kiosk Modu (?kiosk=1) Layout İzolasyonu
// ------------------------------------------------------------------------
$kioskRes = fetchHttp('/andon?kiosk=1', $adminSess);
$hasKioskBody = strpos($kioskRes['body'], 'andon-kiosk-body') !== false;
$hasNoSidebarInKiosk = strpos($kioskRes['body'], 'sidebar-container') === false;

$ok11 = ($kioskRes['code'] === 200 && $hasKioskBody && $hasNoSidebarInKiosk);
reportTest('11', 'TV Kiosk Modu (?kiosk=1) Başlık ve Sidebar İzolasyonu', $ok11, "Kiosk Body: " . ($hasKioskBody ? "Var" : "Yok") . ", Sidebar Gizli: " . ($hasNoSidebarInKiosk ? "Evet" : "Hayır"));

// ------------------------------------------------------------------------
// Test 12: Yetkisiz Anonim Kullanıcı Erişim Engeli (RBAC)
// ------------------------------------------------------------------------
$anonPage = fetchHttp('/andon');
$anonApi = fetchHttp('/andon/api/live');

$ok12 = ($anonPage['code'] === 302 && ($anonApi['code'] === 302 || $anonApi['code'] === 401 || $anonApi['code'] === 403));
reportTest('12', 'Yetkisiz Kullanıcı /andon ve /andon/api/live Erişim Engeli', $ok12, "Anon Page: {$anonPage['code']}, Anon API: {$anonApi['code']}");

// ------------------------------------------------------------------------
// Test 13: Rol Yetki Doğrulaması (Admin, Yönetici, Operatör)
// ------------------------------------------------------------------------
$yoneticiAndon = fetchHttp('/andon', $yoneticiSess);
$operatorAndon = fetchHttp('/andon', $operatorSess);

$ok13 = ($yoneticiAndon['code'] === 200 && $operatorAndon['code'] === 200);
reportTest('13', 'Rol Yetki Doğrulaması (Admin, Yönetici, Operatör Erişimi)', $ok13, "Yönetici HTTP: {$yoneticiAndon['code']}, Operatör HTTP: {$operatorAndon['code']}");

// ------------------------------------------------------------------------
// Test 14: Sidebar Menüsünde "Canlı Andon Ekranı" Bağlantısı
// ------------------------------------------------------------------------
$dashRes = fetchHttp('/', $adminSess);
$hasSidebarAndon = strpos($dashRes['body'], '/stok-takip/public/andon') !== false && strpos($dashRes['body'], 'Canlı Andon Ekranı') !== false;

$ok14 = ($hasSidebarAndon);
reportTest('14', 'Sidebar Üretim Menüsünde "Canlı Andon Ekranı" Bağlantısı', $ok14, "Sidebar Bağlantısı: " . ($hasSidebarAndon ? "Mevcut" : "Eksik"));

// ------------------------------------------------------------------------
// TEST CLEAN-UP
// ------------------------------------------------------------------------
$pdo->exec("DELETE FROM line_downtimes WHERE operator_note LIKE 'ANDON-TEST-%'");
$pdo->exec("UPDATE production_lines SET status = 'IDLE' WHERE status = 'FAULT'");

$totalTests = $passCount + $failCount;

echo "\n========================================================================\n";
echo "TOPLAM: {$totalTests}\n";
echo "PASS: {$passCount}\n";
echo "FAIL: {$failCount}\n";
echo "========================================================================\n";

