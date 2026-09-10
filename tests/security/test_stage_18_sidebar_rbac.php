<?php

/**
 * AŞAMA 18: ROL BAZLI SİDEBAR & DIRECT URL ERİŞİM TEST PROTOKOLÜ
 */

ob_start();

require_once dirname(__DIR__, 2) . '/config/database.php';

$pdo = (new Database())->connect();

echo "========================================================================\n";
echo "=== AŞAMA 18: ROL BAZLI SİDEBAR VE DOĞRUDAN URL GÜVENLİK TESTİ ===\n";
echo "========================================================================\n\n";

$passCount = 0;
$failCount = 0;

function reportSidebarTest(string $role, string $testName, bool $passed, string $details = ''): void {
    global $passCount, $failCount;
    if ($passed) {
        $passCount++;
        echo "[PASS] [{$role}] {$testName}" . ($details ? " -> {$details}" : "") . "\n";
    } else {
        $failCount++;
        echo "[FAIL] [{$role}] {$testName}" . ($details ? " -> {$details}" : "") . "\n";
    }
}

$cookieDir = sys_get_temp_dir() . '/stok_sb_cookies';
if (!is_dir($cookieDir)) {
    @mkdir($cookieDir, 0777, true);
}

function loginSbUser(string $username, string $password): string {
    global $cookieDir;
    $cookieFile = $cookieDir . '/sb_' . $username . '.txt';
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

function sbRequest(string $cookieFile, string $path): array {
    $ch = curl_init('http://localhost/stok-takip/public' . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => (int)$code,
        'body' => $body ?: ''
    ];
}

$adminCookie     = loginSbUser('admin', 'Test123!');
$yoneticiCookie  = loginSbUser('yonetici', 'Test123!');
$uretimCookie    = loginSbUser('uretim', 'Test123!');
$operatorCookie  = loginSbUser('operator', 'Test123!');
$kaliteCookie    = loginSbUser('kalite', 'Test123!');
$bakimCookie     = loginSbUser('bakim', 'Test123!');
$depoCookie      = loginSbUser('depo', 'Test123!');

// ------------------------------------------------------------------------
// 1. ADMIN
// ------------------------------------------------------------------------
echo "\n--- 1. ADMIN SİDEBAR & DOĞRUDAN ERİŞİM ---\n";
$adHome = sbRequest($adminCookie, '/materials');
$adHasAll = strpos($adHome['body'], 'menu-stock') !== false
    && strpos($adHome['body'], 'menu-warehouses') !== false
    && strpos($adHome['body'], 'menu-production') !== false
    && strpos($adHome['body'], 'menu-mes') !== false
    && strpos($adHome['body'], 'menu-maintenance') !== false
    && strpos($adHome['body'], 'menu-energy') !== false
    && strpos($adHome['body'], 'menu-management') !== false;
reportSidebarTest('ADMIN', 'Sidebar Modül Görünürlüğü', $adHasAll, "Tüm 7 akordeon grubu mevcut.");

// ------------------------------------------------------------------------
// 2. YÖNETİCİ
// ------------------------------------------------------------------------
echo "\n--- 2. YÖNETİCİ SİDEBAR & DOĞRUDAN ERİŞİM ---\n";
$yoHome = sbRequest($yoneticiCookie, '/energy-dashboard');
$yoHasRpt = strpos($yoHome['body'], 'Yönetici Dashboard') !== false
    && strpos($yoHome['body'], 'menu-energy') !== false
    && strpos($yoHome['body'], 'menu-mes') !== false;
$yoNoMgmt = strpos($yoHome['body'], '/users') === false
    && strpos($yoHome['body'], '/role-permissions') === false
    && strpos($yoHome['body'], '/api-tokens') === false;
reportSidebarTest('YÖNETİCİ', 'Sidebar Modül Görünürlüğü & İzolasyonu', $yoHasRpt && $yoNoMgmt, "Rapor/Enerji açık, Kullanıcı/Rol/Token menüleri gizli.");

$yoDirect = sbRequest($yoneticiCookie, '/users')['code'] === 403
    && sbRequest($yoneticiCookie, '/role-permissions')['code'] === 403
    && sbRequest($yoneticiCookie, '/api-tokens')['code'] === 403;
reportSidebarTest('YÖNETİCİ', 'Gizli URL Doğrudan GET Güvenliği (403)', $yoDirect, "/users, /role-permissions, /api-tokens -> 403 Forbidden");

// ------------------------------------------------------------------------
// 3. ÜRETİM PERSONELİ
// ------------------------------------------------------------------------
echo "\n--- 3. ÜRETİM PERSONELİ SİDEBAR & DOĞRUDAN ERİŞİM ---\n";
$urHome = sbRequest($uretimCookie, '/production');
$urHasProd = strpos($urHome['body'], 'menu-production') !== false
    && strpos($urHome['body'], 'Üretim &amp; Tüketim') !== false
    && strpos($urHome['body'], 'İş Emirleri (MES)') !== false
    && strpos($urHome['body'], 'OEE &amp; Hat Performansı') !== false
    && strpos($urHome['body'], 'Canlı Andon Ekranı') !== false;
$urNoRest = strpos($urHome['body'], 'menu-stock') === false
    && strpos($urHome['body'], 'menu-warehouses') === false
    && strpos($urHome['body'], 'menu-energy') === false
    && strpos($urHome['body'], 'menu-management') === false
    && strpos($urHome['body'], 'Üretim Reçeteleri') === false
    && strpos($urHome['body'], 'MES Simülatörü') === false;
reportSidebarTest('ÜRETİM', 'Sidebar Modül Görünürlüğü & İzolasyonu', $urHasProd && $urNoRest, "Üretim/MES/OEE/Andon açık; Stok/Depo/Enerji/Yönetim/Reçeteler/Simülatör tamamen gizli.");

$urDirect = sbRequest($uretimCookie, '/users')['code'] === 403
    && sbRequest($uretimCookie, '/role-permissions')['code'] === 403
    && sbRequest($uretimCookie, '/recipes/create')['code'] === 403
    && sbRequest($uretimCookie, '/api-tokens')['code'] === 403
    && sbRequest($uretimCookie, '/mes/simulator')['code'] === 403;
reportSidebarTest('ÜRETİM', 'Gizli URL Doğrudan GET Güvenliği (403)', $urDirect, "/users, /role-permissions, /recipes/create, /api-tokens, /mes/simulator -> 403 Forbidden");

// ------------------------------------------------------------------------
// 4. OPERATÖR
// ------------------------------------------------------------------------
echo "\n--- 4. OPERATÖR SİDEBAR & DOĞRUDAN ERİŞİM ---\n";
$opHome = sbRequest($operatorCookie, '/mes');
$opHasOp = strpos($opHome['body'], 'İş Emirleri (MES)') !== false
    && strpos($opHome['body'], 'Canlı Andon Ekranı') !== false;
$opNoMgmt = strpos($opHome['body'], 'menu-stock') === false
    && strpos($opHome['body'], 'menu-warehouses') === false
    && strpos($opHome['body'], 'menu-energy') === false
    && strpos($opHome['body'], 'menu-management') === false
    && strpos($opHome['body'], 'menu-maintenance') === false;
reportSidebarTest('OPERATÖR', 'Sidebar Modül Görünürlüğü & İzolasyonu', $opHasOp && $opNoMgmt, "Hat operasyon menüleri açık; yönetimsel ve harici modüller gizli.");

$opDirect = sbRequest($operatorCookie, '/recipes/create')['code'] === 403
    && sbRequest($operatorCookie, '/users')['code'] === 403
    && sbRequest($operatorCookie, '/admin/dashboard')['code'] === 403;
reportSidebarTest('OPERATÖR', 'Gizli URL Doğrudan GET Güvenliği (403)', $opDirect, "/recipes/create, /users, /admin/dashboard -> 403 Forbidden");

// ------------------------------------------------------------------------
// 5. KALİTE PERSONELİ
// ------------------------------------------------------------------------
echo "\n--- 5. KALİTE PERSONELİ SİDEBAR & DOĞRUDAN ERİŞİM ---\n";
$kaHome = sbRequest($kaliteCookie, '/finished-goods');
$kaHasFg = strpos($kaHome['body'], 'Panel Seri Takip') !== false
    && strpos($kaHome['body'], 'Canlı Andon Ekranı') !== false;
$kaNoRest = strpos($kaHome['body'], 'menu-stock') === false
    && strpos($kaHome['body'], 'menu-warehouses') === false
    && strpos($kaHome['body'], 'menu-production') === false
    && strpos($kaHome['body'], 'menu-maintenance') === false
    && strpos($kaHome['body'], 'menu-energy') === false
    && strpos($kaHome['body'], 'menu-management') === false;
reportSidebarTest('KALİTE', 'Sidebar Modül Görünürlüğü & İzolasyonu', $kaHasFg && $kaNoRest, "Panel Takip & Kalite açık; Stok/Depo/Üretim/Bakım/Enerji/Yönetim gizli.");

$kaDirect = sbRequest($kaliteCookie, '/maintenance')['code'] === 403
    && sbRequest($kaliteCookie, '/users')['code'] === 403
    && sbRequest($kaliteCookie, '/recipes/create')['code'] === 403;
reportSidebarTest('KALİTE', 'Gizli URL Doğrudan GET Güvenliği (403)', $kaDirect, "/maintenance, /users, /recipes/create -> 403 Forbidden");

// ------------------------------------------------------------------------
// 6. BAKIM PERSONELİ
// ------------------------------------------------------------------------
echo "\n--- 6. BAKIM PERSONELİ SİDEBAR & DOĞRUDAN ERİŞİM ---\n";
$baHome = sbRequest($bakimCookie, '/maintenance');
$baHasMaint = strpos($baHome['body'], 'menu-maintenance') !== false
    && strpos($baHome['body'], 'menu-energy') !== false
    && strpos($baHome['body'], 'OEE &amp; Hat Performansı') !== false
    && strpos($baHome['body'], 'Canlı Andon Ekranı') !== false;
$baNoRest = strpos($baHome['body'], 'menu-stock') === false
    && strpos($baHome['body'], 'menu-warehouses') === false
    && strpos($baHome['body'], 'menu-production') === false
    && strpos($baHome['body'], 'menu-management') === false;
reportSidebarTest('BAKIM', 'Sidebar Modül Görünürlüğü & İzolasyonu', $baHasMaint && $baNoRest, "Bakım/OEE/Andon/Enerji açık; Stok/Depo/Üretim/Yönetim gizli.");

$baDirect = sbRequest($bakimCookie, '/users')['code'] === 403
    && sbRequest($bakimCookie, '/recipes/create')['code'] === 403
    && sbRequest($bakimCookie, '/shipments')['code'] === 403;
reportSidebarTest('BAKIM', 'Gizli URL Doğrudan GET Güvenliği (403)', $baDirect, "/users, /recipes/create, /shipments -> 403 Forbidden");

// ------------------------------------------------------------------------
// 7. DEPO PERSONELİ
// ------------------------------------------------------------------------
echo "\n--- 7. DEPO PERSONELİ SİDEBAR & DOĞRUDAN ERİŞİM ---\n";
$deHome = sbRequest($depoCookie, '/materials');
$deHasWh = strpos($deHome['body'], 'menu-stock') !== false
    && strpos($deHome['body'], 'menu-warehouses') !== false
    && strpos($deHome['body'], 'Malzemeler') !== false
    && strpos($deHome['body'], 'Stok Hareketleri') !== false
    && strpos($deHome['body'], 'Sevkiyat Yönetimi') !== false
    && strpos($deHome['body'], 'Depolar') !== false
    && strpos($deHome['body'], 'Raf / Lokasyonlar') !== false;
$deNoRest = strpos($deHome['body'], 'menu-production') === false
    && strpos($deHome['body'], 'menu-mes') === false
    && strpos($deHome['body'], 'menu-maintenance') === false
    && strpos($deHome['body'], 'menu-energy') === false
    && strpos($deHome['body'], 'menu-management') === false;
reportSidebarTest('DEPO', 'Sidebar Modül Görünürlüğü & İzolasyonu', $deHasWh && $deNoRest, "Stok ve Depo açık; Üretim/MES/Bakım/Enerji/Yönetim tamamen gizli.");

$deDirect = sbRequest($depoCookie, '/recipes')['code'] === 403
    && sbRequest($depoCookie, '/recipes/create')['code'] === 403
    && sbRequest($depoCookie, '/mes')['code'] === 403
    && sbRequest($depoCookie, '/oee')['code'] === 403
    && sbRequest($depoCookie, '/maintenance')['code'] === 403
    && sbRequest($depoCookie, '/andon')['code'] === 403
    && sbRequest($depoCookie, '/energy-dashboard')['code'] === 403
    && sbRequest($depoCookie, '/users')['code'] === 403;
reportSidebarTest('DEPO', 'Gizli URL Doğrudan GET Güvenliği (403)', $deDirect, "/recipes, /recipes/create, /mes, /oee, /maintenance, /andon, /energy-dashboard, /users -> 403 Forbidden");

$total = $passCount + $failCount;
echo "\n========================================================================\n";
echo "TOPLAM SİDEBAR RBAC TESTLERİ: {$total}\n";
echo "PASS: {$passCount}\n";
echo "FAIL: {$failCount}\n";
echo "========================================================================\n";

