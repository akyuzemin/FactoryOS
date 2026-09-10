<?php

/**
 * AŞAMA 17: ADVERSARIAL RBAC, PRIVILEGE ESCALATION, IDOR & DEFENSE-IN-DEPTH TEST PROTOKOLÜ
 */

ob_start();

require_once dirname(__DIR__, 2) . '/config/database.php';

$pdo = (new Database())->connect();

echo "========================================================================\n";
echo "=== AŞAMA 17 ADVERSARIAL RBAC & GÜVENLİK DOĞRULAMA PROTOKOLÜ ===\n";
echo "========================================================================\n\n";

$passCount = 0;
$failCount = 0;

function reportTest(string $section, string $testNo, string $title, bool $passed, string $details = ''): void {
    global $passCount, $failCount;
    if ($passed) {
        $passCount++;
        echo "[PASS] [{$section}] Test {$testNo} - {$title}" . ($details ? " -> {$details}" : "") . "\n";
    } else {
        $failCount++;
        echo "[FAIL] [{$section}] Test {$testNo} - {$title}" . ($details ? " -> {$details}" : "") . "\n";
    }
}

// Cookie jar dosyaları ile bağımsız oturum yönetimi
$cookieDir = sys_get_temp_dir() . '/stok_rbac_test_cookies';
if (!is_dir($cookieDir)) {
    @mkdir($cookieDir, 0777, true);
}

function loginRoleUser(string $username, string $password): string {
    global $cookieDir;
    $cookieFile = $cookieDir . '/cookie_' . $username . '.txt';
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
    $res = curl_exec($ch);
    curl_close($ch);

    return $cookieFile;
}

function roleRequest(string $cookieFile, string $path, string $method = 'GET', array $data = []): array {
    $ch = curl_init('http://localhost/stok-takip/public' . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => (int)$code,
        'body' => $body ?: '',
        'json' => json_decode($body ?: '', true) ?? []
    ];
}

// 7 Rolün Giriş Yapması
$adminCookie     = loginRoleUser('admin', 'Test123!');
$depoCookie      = loginRoleUser('depo', 'Test123!');
$yoneticiCookie  = loginRoleUser('yonetici', 'Test123!');
$operatorCookie  = loginRoleUser('operator', 'Test123!');
$uretimCookie    = loginRoleUser('uretim', 'Test123!');
$kaliteCookie    = loginRoleUser('kalite', 'Test123!');
$bakimCookie     = loginRoleUser('bakim', 'Test123!');

// ========================================================================
// 1. ADMIN (Role 1) - TAM ERİŞİM DOĞRULAMASI
// ========================================================================
$ad1 = roleRequest($adminCookie, '/admin/dashboard');
$ad2 = roleRequest($adminCookie, '/users');
$ad3 = roleRequest($adminCookie, '/role-permissions');
$ad4 = roleRequest($adminCookie, '/api-tokens');
$ad5 = roleRequest($adminCookie, '/recipes/create');
$ad6 = roleRequest($adminCookie, '/mes/simulator');
$ad7 = roleRequest($adminCookie, '/maintenance');

$adminOk = ($ad1['code'] === 200 && $ad2['code'] === 200 && $ad3['code'] === 200 && $ad4['code'] === 200 && $ad5['code'] === 200 && $ad6['code'] === 200 && $ad7['code'] === 200);
reportTest('ADMIN', '01', 'Admin: Tüm Yönetim ve Konfigürasyon Modüllerine Erişim', $adminOk, "Dashboard, Users, Roles, Tokens, Recipes, Simulator, Maintenance: 200 OK");

// Sidebar kontrolü
$adSide = roleRequest($adminCookie, '/materials');
$adHasUsers = strpos($adSide['body'], 'Kullanıcılar') !== false || strpos($adSide['body'], '/users') !== false;
$adHasSim = strpos($adSide['body'], 'MES Simülatörü') !== false || strpos($adSide['body'], '/mes/simulator') !== false;
reportTest('ADMIN', '02', 'Admin: Sidebar Üzerinde Tüm Menü Öğelerinin Görünürlüğü', $adHasUsers && $adHasSim, "Kullanıcılar ve Simülatör menüsü DOM'da mevcut.");

// ========================================================================
// 2. YÖNETİCİ (Role 3) - RAPOR & TELEMETRİ ERİŞİMİ, YÖNETİM ENGELİ
// ========================================================================
$yo1 = roleRequest($yoneticiCookie, '/admin/dashboard');
$yo2 = roleRequest($yoneticiCookie, '/oee');
$yo3 = roleRequest($yoneticiCookie, '/andon');
$yo4 = roleRequest($yoneticiCookie, '/audit-logs');
$yo5 = roleRequest($yoneticiCookie, '/energy-dashboard');

$yoRptOk = ($yo1['code'] === 200 && $yo2['code'] === 200 && $yo3['code'] === 200 && $yo4['code'] === 200 && $yo5['code'] === 200);
reportTest('YÖNETİCİ', '03', 'Yönetici: Dashboard/OEE/Andon/Audit/Enerji Görüntüleme', $yoRptOk, "Tüm izleme ve rapor sayfaları 200 OK");

// Yönetim sayfalarına doğrudan URL engeli (403)
$yoBlk1 = roleRequest($yoneticiCookie, '/users');
$yoBlk2 = roleRequest($yoneticiCookie, '/role-permissions');
$yoBlk3 = roleRequest($yoneticiCookie, '/api-tokens');
$yoBlk4 = roleRequest($yoneticiCookie, '/recipes/create');
$yoBlkOk = ($yoBlk1['code'] === 403 && $yoBlk2['code'] === 403 && $yoBlk3['code'] === 403 && $yoBlk4['code'] === 403);
reportTest('YÖNETİCİ', '04', 'Yönetici: /users, /role-permissions, /api-tokens, /recipes/create Doğrudan URL Engeli (403)', $yoBlkOk, "Users: {$yoBlk1['code']}, Roles: {$yoBlk2['code']}, Tokens: {$yoBlk3['code']}, RecCreate: {$yoBlk4['code']}");

// ========================================================================
// 3. ÜRETİM PERSONELİ (Role 5) - OPERASYON AÇIK, REÇETE/SİMÜLATÖR/YÖNETİM ENGELİ
// ========================================================================
$ur1 = roleRequest($uretimCookie, '/production');
$ur2 = roleRequest($uretimCookie, '/recipes'); // Görüntüleme modu
$ur3 = roleRequest($uretimCookie, '/mes');
$ur4 = roleRequest($uretimCookie, '/finished-goods');
$ur5 = roleRequest($uretimCookie, '/oee');
$ur6 = roleRequest($uretimCookie, '/andon');

$urOpOk = ($ur1['code'] === 200 && $ur2['code'] === 200 && $ur3['code'] === 200 && $ur4['code'] === 200 && $ur5['code'] === 200 && $ur6['code'] === 200);
reportTest('ÜRETİM', '05', 'Üretim Personeli: Üretim/Reçete Listesi/MES/Panel/OEE/Andon Erişimi', $urOpOk, "Operasyonel sayfalar 200 OK");

// Reçete Değiştirme ve Simülatör Engeli
$urBlk1 = roleRequest($uretimCookie, '/recipes/create');
$urBlk2 = roleRequest($uretimCookie, '/recipes/edit?id=1');
$urBlk3 = roleRequest($uretimCookie, '/recipes/delete', 'POST', ['id' => 1, 'csrf_token' => 'dummy']);
$urBlk4 = roleRequest($uretimCookie, '/mes/simulator/start', 'POST', ['csrf_token' => 'dummy']);
$urBlk5 = roleRequest($uretimCookie, '/users');
$urBlk6 = roleRequest($uretimCookie, '/admin/dashboard');

$urBlkOk = ($urBlk1['code'] === 403 && $urBlk2['code'] === 403 && $urBlk3['code'] === 403 && $urBlk4['code'] === 403 && $urBlk5['code'] === 403 && $urBlk6['code'] === 403);
reportTest('ÜRETİM', '06', 'Üretim Personeli: Reçete Oluşturma/Düzenleme/Silme, Simülatör ve Admin Dashboard Engeli (403)', $urBlkOk, "RecCreate: {$urBlk1['code']}, RecEdit: {$urBlk2['code']}, RecDel: {$urBlk3['code']}, SimStart: {$urBlk4['code']}");

// Reçeteler sayfasında Eylem Butonlarının Gizlenmesi
$urRecPage = roleRequest($uretimCookie, '/recipes');
$urHasCreateBtn = strpos($urRecPage['body'], '/recipes/create') !== false;
reportTest('ÜRETİM', '07', 'Üretim Personeli: Reçeteler Sayfasında "Yeni Reçete Oluştur" Butonunun Gizlenmesi', !$urHasCreateBtn, "Reçete oluştur butonu DOM'dan kaldırılmış.");

// ========================================================================
// 4. OPERATÖR (Role 4) - EN DÜŞÜK SEVİYE OPERASYONEL İZOLASYON
// ========================================================================
$op1 = roleRequest($operatorCookie, '/');
$op2 = roleRequest($operatorCookie, '/mes');
$op3 = roleRequest($operatorCookie, '/oee');
$op4 = roleRequest($operatorCookie, '/andon');

$opOpOk = ($op1['code'] === 200 && $op2['code'] === 200 && $op3['code'] === 200 && $op4['code'] === 200);
reportTest('OPERATÖR', '08', 'Operatör: Dashboard/MES/OEE/Andon Ekranlarına Erişim', $opOpOk, "Hat operasyon ekranları 200 OK");

// Operatör Kısıtlamaları (Reçete, Kalite Kararı, Bakım Kapatma, Stok Sayım Düzeltmesi)
$opBlk1 = roleRequest($operatorCookie, '/recipes/create');
$opBlk2 = roleRequest($operatorCookie, '/finished-goods/quality-control/save', 'POST', ['serial_number' => 'TEST', 'csrf_token' => 'dummy']);
$opBlk3 = roleRequest($operatorCookie, '/maintenance/complete', 'POST', ['work_order_id' => 1, 'csrf_token' => 'dummy']);
$opBlk4 = roleRequest($operatorCookie, '/stock/adjustment');
$opBlk5 = roleRequest($operatorCookie, '/users');

$opBlkOk = ($opBlk1['code'] === 403 && $opBlk2['code'] === 403 && $opBlk3['code'] === 403 && $opBlk4['code'] === 403 && $opBlk5['code'] === 403);
reportTest('OPERATÖR', '09', 'Operatör: Reçete, Kalite Onay/Red, Bakım Kapatma, Stok Düzeltme Engeli (403)', $opBlkOk, "RecCreate: {$opBlk1['code']}, QC: {$opBlk2['code']}, MaintComp: {$opBlk3['code']}, StockAdj: {$opBlk4['code']}");

// ========================================================================
// 5. KALİTE PERSONELİ (Role 6) - PANEL PASAPORTU & KALİTE KONTROL YETKİSİ
// ========================================================================
$ka1 = roleRequest($kaliteCookie, '/finished-goods');
$ka2 = roleRequest($kaliteCookie, '/finished-goods/show?serial=PNL-TEST');

$kaOpOk = ($ka1['code'] === 200);
reportTest('KALİTE', '10', 'Kalite Personeli: Panel Seri Takip & Kalite Kontrol Merkezi Erişimi', $kaOpOk, "Bitmiş Ürünler Listesi 200 OK");

// Kaliteci Yetki Sınırları (Bakım, Reçete, Sevkiyat Tamamlama, Kullanıcı)
$kaBlk1 = roleRequest($kaliteCookie, '/maintenance');
$kaBlk2 = roleRequest($kaliteCookie, '/recipes/create');
$kaBlk3 = roleRequest($kaliteCookie, '/shipments/complete', 'POST', ['id' => 1, 'csrf_token' => 'dummy']);
$kaBlk4 = roleRequest($kaliteCookie, '/users');
$kaBlk5 = roleRequest($kaliteCookie, '/stock/adjustment');

$kaBlkOk = ($kaBlk1['code'] === 403 && $kaBlk2['code'] === 403 && $kaBlk3['code'] === 403 && $kaBlk4['code'] === 403 && $kaBlk5['code'] === 403);
reportTest('KALİTE', '11', 'Kalite Personeli: Bakım, Reçete, Sevkiyat Çıkışı, Stok Düzeltme Engeli (403)', $kaBlkOk, "Maint: {$kaBlk1['code']}, RecCreate: {$kaBlk2['code']}, ShipComp: {$kaBlk3['code']}");

// ========================================================================
// 6. BAKIM PERSONELİ (Role 7) - EKİPMAN, İŞ EMRİ & ONARIM YETKİSİ
// ========================================================================
$ba1 = roleRequest($bakimCookie, '/maintenance');
$ba2 = roleRequest($bakimCookie, '/maintenance/asset?id=1');
$ba3 = roleRequest($bakimCookie, '/oee');
$ba4 = roleRequest($bakimCookie, '/andon');

$baOpOk = ($ba1['code'] === 200 && $ba2['code'] === 200 && $ba3['code'] === 200 && $ba4['code'] === 200);
reportTest('BAKIM', '12', 'Bakım Personeli: Bakım Dashboard, Ekipman Pasaportu, OEE & Andon Erişimi', $baOpOk, "Bakım modülü sayfaları 200 OK");

// Bakımcı Yetki Sınırları (Kalite Onay, Reçete, Sevkiyat, Kullanıcı)
$baBlk1 = roleRequest($bakimCookie, '/finished-goods/quality-control/save', 'POST', ['serial' => 'TEST', 'csrf_token' => 'dummy']);
$baBlk2 = roleRequest($bakimCookie, '/recipes/create');
$baBlk3 = roleRequest($bakimCookie, '/shipments');
$baBlk4 = roleRequest($bakimCookie, '/users');
$baBlk5 = roleRequest($bakimCookie, '/api-tokens');

$baBlkOk = ($baBlk1['code'] === 403 && $baBlk2['code'] === 403 && $baBlk3['code'] === 403 && $baBlk4['code'] === 403 && $baBlk5['code'] === 403);
reportTest('BAKIM', '13', 'Bakım Personeli: Kalite Onay/Red, Reçete, Sevkiyat, Kullanıcı Engeli (403)', $baBlkOk, "QC: {$baBlk1['code']}, RecCreate: {$baBlk2['code']}, Ship: {$baBlk3['code']}, Users: {$baBlk4['code']}");

// ========================================================================
// 7. DEPO PERSONELİ (Role 2) - MAL KABUL, DEPO, SEVKİYAT İŞLEMLERİ
// ========================================================================
$de1 = roleRequest($depoCookie, '/materials');
$de2 = roleRequest($depoCookie, '/stock-movements');
$de3 = roleRequest($depoCookie, '/warehouses');
$de4 = roleRequest($depoCookie, '/shipments');

$deOpOk = ($de1['code'] === 200 && $de2['code'] === 200 && $de3['code'] === 200 && $de4['code'] === 200);
reportTest('DEPO', '14', 'Depo Personeli: Malzemeler, Stok Hareketleri, Depolar, Sevkiyat Erişimi', $deOpOk, "Depo ve lojistik sayfaları 200 OK");

// Depocu Yetki Sınırları (Bakım, MES Simülatör, Reçete, Kullanıcı)
$deBlk1 = roleRequest($depoCookie, '/maintenance');
$deBlk2 = roleRequest($depoCookie, '/mes/simulator/start', 'POST', ['csrf_token' => 'dummy']);
$deBlk3 = roleRequest($depoCookie, '/recipes/create');
$deBlk4 = roleRequest($depoCookie, '/users');

$deBlkOk = ($deBlk1['code'] === 403 && $deBlk2['code'] === 403 && $deBlk3['code'] === 403 && $deBlk4['code'] === 403);
reportTest('DEPO', '15', 'Depo Personeli: Bakım, Simülasyon Yürütme, Reçete, Kullanıcı Engeli (403)', $deBlkOk, "Maint: {$deBlk1['code']}, SimStart: {$deBlk2['code']}, RecCreate: {$deBlk3['code']}");

// ========================================================================
// 8. PRIVILEGE ESCALATION & IDOR DEFENSE TESTİ
// ========================================================================
// 8.1 Yetkisiz Kullanıcının Kendi Rolünü Admin Yapma Girişimi
$escAttempt = roleRequest($operatorCookie, '/role-permissions/update', 'POST', [
    'role_id' => 4,
    'permissions' => [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20]
]);
reportTest('ESCALATION', '16', 'Operatörün Kendi Rol İzinlerini Yükseltme Girişimi Engeli (403/404)', in_array($escAttempt['code'], [403, 404], true), "Sonuç Kodu: {$escAttempt['code']}");

// 8.2 IDOR: Geçersiz / Var Olmayan veya Yetkisiz Kayıt ID İstekleri (404/403/302)
$idorUser = roleRequest($uretimCookie, '/users/edit?id=99999');
$idorWo   = roleRequest($operatorCookie, '/maintenance/work-order?id=99999');
$idorShip = roleRequest($kaliteCookie, '/shipments/show?id=99999');

$idorOk = ($idorUser['code'] === 403 && $idorWo['code'] === 403 && in_array($idorShip['code'], [403, 404, 302], true));
reportTest('IDOR', '17', 'IDOR ve Nesne Manipülasyon Koruması (Yetkisiz/Geçersiz ID Engeli)', $idorOk, "UserEdit: {$idorUser['code']}, MaintWO: {$idorWo['code']}, ShipShow: {$idorShip['code']}");

// ========================================================================
// 9. GERÇEK VERİTABANI PERMISSION MATRİSİ TABLOSU
// ========================================================================
echo "\n========================================================================\n";
echo "=== GERÇEK VERİTABANI RBAC PERMISSION MATRİSİ TABLOSU ===\n";
echo "========================================================================\n";

$allPerms = $pdo->query("SELECT id, name FROM permissions ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$allRoles = $pdo->query("SELECT id, name FROM roles ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$rolePermMap = [];
$stmtRP = $pdo->query("SELECT role_id, permission_id FROM role_permissions");
while ($r = $stmtRP->fetch(PDO::FETCH_ASSOC)) {
    $rolePermMap[$r['role_id']][$r['permission_id']] = true;
}

printf("%-26s | %-5s | %-8s | %-6s | %-8s | %-6s | %-5s | %-5s\n", "Permission", "Admin", "Yönetici", "Üretim", "Operatör", "Kalite", "Bakım", "Depo");
echo str_repeat("-", 85) . "\n";

foreach ($allPerms as $p) {
    printf("%-26s | %-5s | %-8s | %-6s | %-8s | %-6s | %-5s | %-5s\n",
        $p['name'],
        isset($rolePermMap[1][$p['id']]) ? '  ✓  ' : '  -  ',
        isset($rolePermMap[3][$p['id']]) ? '   ✓    ' : '   -    ',
        isset($rolePermMap[5][$p['id']]) ? '  ✓   ' : '  -   ',
        isset($rolePermMap[4][$p['id']]) ? '   ✓    ' : '   -    ',
        isset($rolePermMap[6][$p['id']]) ? '  ✓   ' : '  -   ',
        isset($rolePermMap[7][$p['id']]) ? '  ✓  ' : '  -  ',
        isset($rolePermMap[2][$p['id']]) ? '  ✓  ' : '  -  '
    );
}

$total = $passCount + $failCount;
echo "\n========================================================================\n";
echo "TOPLAM ADVERSARIAL RBAC TESTLERİ: {$total}\n";
echo "PASS: {$passCount}\n";
echo "FAIL: {$failCount}\n";
echo "========================================================================\n";

