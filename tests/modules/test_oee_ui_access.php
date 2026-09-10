<?php

/**
 * TEST: OEE Menü Erişimi, Route ve Arayüz Doğrulama Testi
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
$pdo = (new Database())->connect();
$GLOBALS['pdo'] = $pdo;

echo "========================================================================\n";
echo "OEE MENÜ ERİŞİMİ VE ARAYÜZ DOĞRULAMA TEST PROTOKOLÜ\n";
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

// ------------------------------------------------------------------------
// 1. Yetkisiz Kullanıcı /oee Erişimi (HTTP 302 Login Yönlendirmesi)
// ------------------------------------------------------------------------
$anonRes = fetchHttp('/oee');
$ok1 = ($anonRes['code'] === 302 || $anonRes['code'] === 401 || $anonRes['code'] === 403);
reportTest('1. Yetkisiz Kullanıcı /oee Erişim Engeli', $ok1, "HTTP Status: {$anonRes['code']}");

// ------------------------------------------------------------------------
// 2. Admin Kullanıcı (Role 1) ile /oee Sayfa Render Testi
// ------------------------------------------------------------------------
$adminSessId = loginSession('admin', 'Test123!');

$adminRes = fetchHttp('/oee', $adminSessId);
$hasHeader = strpos($adminRes['body'], 'OEE &amp; Hat Performans Yönetimi') !== false;
$hasGauges = strpos($adminRes['body'], 'GENEL FABRİKA OEE') !== false;
$hasTable = strpos($adminRes['body'], 'Hat Bazında Canlı OEE') !== false;
$hasPareto = strpos($adminRes['body'], 'Duruş Nedenleri Pareto Dağılımı') !== false;

$ok2 = ($adminRes['code'] === 200 && $hasHeader && $hasGauges && $hasTable && $hasPareto);
reportTest('2. Admin Oturumu İle /oee Ekranının Yüklenmesi', $ok2, "HTTP: {$adminRes['code']}, Başlık/Gauges/Tablo/Pareto: " . ($ok2 ? "Mevcut" : "Eksik"));

// ------------------------------------------------------------------------
// 3. Sidebar İçinde OEE Menü Bağlantısının Bulunması
// ------------------------------------------------------------------------
$dashRes = fetchHttp('/', $adminSessId);
$hasSidebarOeeLinkInDash = strpos($dashRes['body'], '/stok-takip/public/oee') !== false && strpos($dashRes['body'], 'OEE &amp; Hat Performansı') !== false;
$hasSidebarOeeLinkInOee = strpos($adminRes['body'], '/stok-takip/public/oee') !== false && strpos($adminRes['body'], 'OEE &amp; Hat Performansı') !== false;

$ok3 = ($hasSidebarOeeLinkInDash && $hasSidebarOeeLinkInOee);
reportTest('3. Sidebar Menüsünde "OEE & Hat Performansı" Bağlantısı', $ok3, "Dash: " . ($hasSidebarOeeLinkInDash ? "Var" : "Yok") . ", OEE: " . ($hasSidebarOeeLinkInOee ? "Var" : "Yok"));

// ------------------------------------------------------------------------
// 4. Yönetici Rolü (Role 3) ile /oee Erişimi
// ------------------------------------------------------------------------
$yoneticiSessId = loginSession('yonetici', 'Test123!');

$yoneticiRes = fetchHttp('/oee', $yoneticiSessId);
$ok4 = ($yoneticiRes['code'] === 200 && strpos($yoneticiRes['body'], 'GENEL FABRİKA OEE') !== false);
reportTest('4. Yönetici (Role 3) İle /oee Erişimi', $ok4, "HTTP: {$yoneticiRes['code']}");

// ------------------------------------------------------------------------
// 5. Operatör Rolü (Role 4) ile /oee Erişimi
// ------------------------------------------------------------------------
$operatorSessId = loginSession('operator', 'Test123!');

$operatorRes = fetchHttp('/oee', $operatorSessId);
$ok5 = ($operatorRes['code'] === 200 && strpos($operatorRes['body'], 'GENEL FABRİKA OEE') !== false);
reportTest('5. Operatör (Role 4) İle /oee Erişimi', $ok5, "HTTP: {$operatorRes['code']}");

// ------------------------------------------------------------------------
// 6. /oee/api/live Canlı JSON Telemetri API Testi
// ------------------------------------------------------------------------
$liveApiRes = fetchHttp('/oee/api/live', $adminSessId);
$hasSuccess = !empty($liveApiRes['json']['success']);
$hasOverallOee = isset($liveApiRes['json']['summary']['overall_oee']);
$hasLines = !empty($liveApiRes['json']['summary']['lines']);

$ok6 = ($liveApiRes['code'] === 200 && $hasSuccess && $hasOverallOee && $hasLines);
reportTest('6. /oee/api/live Telemetri JSON API', $ok6, "HTTP: {$liveApiRes['code']}, OEE: %" . ($liveApiRes['json']['summary']['overall_oee'] ?? 0));

// ------------------------------------------------------------------------
// 7. PHP Warning / Notice Kontrolü
// ------------------------------------------------------------------------
$body = $adminRes['body'];
$hasErrors = (
    strpos($body, 'Fatal error') !== false ||
    strpos($body, 'Parse error') !== false ||
    strpos($body, 'Warning:') !== false ||
    strpos($body, 'Notice:') !== false
);

reportTest('7. PHP Warning / Fatal Error Temizliği', !$hasErrors, "Sayfa çıktısında hiçbir hata/warning tespit edilmedi.");

// ------------------------------------------------------------------------
// 8. Mevcut /dashboard ve /admin/dashboard Bütünlüğü
// ------------------------------------------------------------------------
$adminDashRes = fetchHttp('/admin/dashboard', $adminSessId);
$hasOeeKpi = strpos($adminDashRes['body'], 'TOPLAM ETKİNLİK (OEE)') !== false;

$ok8 = ($dashRes['code'] === 200 && $adminDashRes['code'] === 200 && $hasOeeKpi);
reportTest('8. Mevcut Dashboard ve Admin Dashboard Bütünlüğü', $ok8, "Dashboard: {$dashRes['code']}, Admin Dashboard: {$adminDashRes['code']} (OEE KPI: " . ($hasOeeKpi ? "Var" : "Yok") . ")");

echo "\n========================================================================\n";
echo "OEE ERİŞİM TEST SONUCU: {$passCount} PASS / {$failCount} FAIL\n";
echo "========================================================================\n";

