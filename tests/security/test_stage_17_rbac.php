<?php

/**
 * AŞAMA 17: KURUMSAL RBAC VE 7 ROL MİMARİSİ KAPSAMLI DOĞRULAMA TEST PROTOKOLÜ
 */

ob_start();

require_once dirname(__DIR__, 2) . '/config/database.php';

$pdo = (new Database())->connect();

echo "========================================================================\n";
echo "=== AŞAMA 17 KURUMSAL RBAC VE 7 ROL TEST PROTOKOLÜ ===\n";
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
    for ($i = 0; $i < 3; $i++) {
        $ch = curl_init('http://localhost/stok-takip/public/login');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['username' => $username, 'password' => $password]));
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $res = curl_exec($ch);
        curl_close($ch);

        preg_match_all('/Set-Cookie:\s*PHPSESSID=([^;]+)/i', $res, $matches);
        if (!empty($matches[1])) {
            return end($matches[1]);
        }
        usleep(100000); // 100ms
    }
    return '';
}

function fetchHttp(string $path, ?string $sessCookie = null, array $postData = []): array {
    $ch = curl_init('http://localhost/stok-takip/public' . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    if ($sessCookie) {
        curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . $sessCookie);
    }

    if (!empty($postData)) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
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

// 7 Rolün Oturumlarını Başlat
$adminSess     = loginSession('admin', 'Test123!');
$depoSess      = loginSession('depo', 'Test123!');
$yoneticiSess  = loginSession('yonetici', 'Test123!');
$operatorSess  = loginSession('operator', 'Test123!');
$uretimSess    = loginSession('uretim', 'Test123!');
$kaliteSess    = loginSession('kalite', 'Test123!');
$bakimSess     = loginSession('bakim', 'Test123!');

// ------------------------------------------------------------------------
// BÖLÜM 1: 7 ROLÜN VERİTABANI DOĞRULAMASI
// ------------------------------------------------------------------------
$roles = $pdo->query("SELECT id, name FROM roles ORDER BY id ASC")->fetchAll(PDO::FETCH_KEY_PAIR);
$all7Exist = isset($roles[1]) && isset($roles[2]) && isset($roles[3]) && isset($roles[4]) && isset($roles[5]) && isset($roles[6]) && isset($roles[7]);
reportTest('01', '7 Rolün Veritabanında Eksiksiz Tanımlanması', $all7Exist, "Roller: " . implode(', ', $roles));

// ------------------------------------------------------------------------
// BÖLÜM 2: ADMIN YETKİ KONTROLLERİ (Role 1)
// ------------------------------------------------------------------------
$rAdminDash  = fetchHttp('/admin/dashboard', $adminSess)['code'];
$rAdminUsers = fetchHttp('/users', $adminSess)['code'];
$rAdminRoles = fetchHttp('/role-permissions', $adminSess)['code'];
$rAdminToken = fetchHttp('/api-tokens', $adminSess)['code'];
$rAdminRecCreate = fetchHttp('/recipes/create', $adminSess)['code'];
$rAdminSim   = fetchHttp('/mes/simulator', $adminSess)['code'];

$okAdmin = ($rAdminDash === 200 && $rAdminUsers === 200 && $rAdminRoles === 200 && $rAdminToken === 200 && $rAdminRecCreate === 200 && $rAdminSim === 200);
reportTest('02', 'Admin: Tüm Yönetim ve Konfigürasyon Ekranlarına Tam Erişim (200 OK)', $okAdmin, "Users: {$rAdminUsers}, Roles: {$rAdminRoles}, RecCreate: {$rAdminRecCreate}");

// ------------------------------------------------------------------------
// BÖLÜM 3: YÖNETİCİ YETKİ VE KORUMA KONTROLLERİ (Role 3)
// ------------------------------------------------------------------------
$rYoneticiDash = fetchHttp('/admin/dashboard', $yoneticiSess)['code'];
$rYoneticiOee  = fetchHttp('/oee', $yoneticiSess)['code'];
$rYoneticiAudit = fetchHttp('/audit-logs', $yoneticiSess)['code'];
$rYoneticiUsers = fetchHttp('/users', $yoneticiSess)['code'];
$rYoneticiRoles = fetchHttp('/role-permissions', $yoneticiSess)['code'];
$rYoneticiRecCreate = fetchHttp('/recipes/create', $yoneticiSess)['code'];

$okYonetici = ($rYoneticiDash === 200 && $rYoneticiOee === 200 && $rYoneticiAudit === 200 && $rYoneticiUsers === 403 && $rYoneticiRoles === 403 && $rYoneticiRecCreate === 403);
reportTest('03', 'Yönetici: Dashboard/OEE/Audit Açık (200), Kullanıcı/Rol/Reçete Engelli (403)', $okYonetici, "Dash: {$rYoneticiDash}, Audit: {$rYoneticiAudit}, Users: {$rYoneticiUsers}, RecCreate: {$rYoneticiRecCreate}");

// ------------------------------------------------------------------------
// BÖLÜM 4: ÜRETİM PERSONELİ YETKİ VE KORUMA KONTROLLERİ (Role 5)
// ------------------------------------------------------------------------
$rUretimProd = fetchHttp('/production', $uretimSess)['code'];
$rUretimMes  = fetchHttp('/mes', $uretimSess)['code'];
$rUretimOee  = fetchHttp('/oee', $uretimSess)['code'];
$rUretimAndon = fetchHttp('/andon', $uretimSess)['code'];
$rUretimRecCreate = fetchHttp('/recipes/create', $uretimSess)['code'];
$rUretimSimStart = fetchHttp('/mes/simulator/start', $uretimSess, ['csrf_token' => 'dummy'])['code'];
$rUretimUsers = fetchHttp('/users', $uretimSess)['code'];
$rUretimAdminDash = fetchHttp('/admin/dashboard', $uretimSess)['code'];

$okUretim = ($rUretimProd === 200 && $rUretimMes === 200 && $rUretimOee === 200 && $rUretimAndon === 200 && $rUretimRecCreate === 403 && $rUretimSimStart === 403 && $rUretimUsers === 403 && $rUretimAdminDash === 403);
reportTest('04', 'Üretim Personeli: Üretim/MES/OEE/Andon Açık (200), Reçete Oluşturma/Simülatör/Yönetim Engelli (403)', $okUretim, "Prod: {$rUretimProd}, MES: {$rUretimMes}, RecCreate: {$rUretimRecCreate}, SimStart: {$rUretimSimStart}");

// ------------------------------------------------------------------------
// BÖLÜM 5: OPERATÖR YETKİ VE KORUMA KONTROLLERİ (Role 4)
// ------------------------------------------------------------------------
$rOpDash = fetchHttp('/', $operatorSess)['code'];
$rOpOee = fetchHttp('/oee', $operatorSess)['code'];
$rOpAndon = fetchHttp('/andon', $operatorSess)['code'];
$rOpRecCreate = fetchHttp('/recipes/create', $operatorSess)['code'];
$rOpUsers = fetchHttp('/users', $operatorSess)['code'];
$rOpAdminDash = fetchHttp('/admin/dashboard', $operatorSess)['code'];
$rOpMaintComp = fetchHttp('/maintenance/complete', $operatorSess, ['csrf_token' => 'dummy'])['code'];

$okOp = ($rOpDash === 200 && $rOpOee === 200 && $rOpAndon === 200 && $rOpRecCreate === 403 && $rOpUsers === 403 && $rOpAdminDash === 403 && $rOpMaintComp === 403);
reportTest('05', 'Operatör: Dashboard/OEE/Andon Açık (200), Reçete/Bakım Kapatma/Admin Engelli (403)', $okOp, "Dash: {$rOpDash}, Andon: {$rOpAndon}, RecCreate: {$rOpRecCreate}, MaintComp: {$rOpMaintComp}");

// ------------------------------------------------------------------------
// BÖLÜM 6: KALİTE PERSONELİ YETKİ VE KORUMA KONTROLLERİ (Role 6)
// ------------------------------------------------------------------------
$rKaliteFg = fetchHttp('/finished-goods', $kaliteSess)['code'];
$rKaliteUsers = fetchHttp('/users', $kaliteSess)['code'];
$rKaliteRoles = fetchHttp('/role-permissions', $kaliteSess)['code'];
$rKaliteMaint = fetchHttp('/maintenance', $kaliteSess)['code'];
$rKaliteRecCreate = fetchHttp('/recipes/create', $kaliteSess)['code'];

$okKalite = ($rKaliteFg === 200 && $rKaliteUsers === 403 && $rKaliteRoles === 403 && $rKaliteMaint === 403 && $rKaliteRecCreate === 403);
reportTest('06', 'Kalite Personeli: Panel Seri & Kalite Takip Açık (200), Bakım/Reçete/Kullanıcı Engelli (403)', $okKalite, "FG: {$rKaliteFg}, Maint: {$rKaliteMaint}, Users: {$rKaliteUsers}");

// ------------------------------------------------------------------------
// BÖLÜM 7: BAKIM PERSONELİ YETKİ VE KORUMA KONTROLLERİ (Role 7)
// ------------------------------------------------------------------------
$rBakimMain = fetchHttp('/maintenance', $bakimSess)['code'];
$rBakimAsset = fetchHttp('/maintenance/asset?id=1', $bakimSess)['code'];
$rBakimOee = fetchHttp('/oee', $bakimSess)['code'];
$rBakimUsers = fetchHttp('/users', $bakimSess)['code'];
$rBakimRoles = fetchHttp('/role-permissions', $bakimSess)['code'];
$rBakimRecCreate = fetchHttp('/recipes/create', $bakimSess)['code'];

$okBakim = ($rBakimMain === 200 && $rBakimAsset === 200 && $rBakimOee === 200 && $rBakimUsers === 403 && $rBakimRoles === 403 && $rBakimRecCreate === 403);
reportTest('07', 'Bakım Personeli: Bakım/Ekipman/OEE Açık (200), Reçete/Kullanıcı/Rol Engelli (403)', $okBakim, "Maint: {$rBakimMain}, Asset: {$rBakimAsset}, Users: {$rBakimUsers}");

// ------------------------------------------------------------------------
// BÖLÜM 8: DEPO PERSONELİ YETKİ VE KORUMA KONTROLLERİ (Role 2)
// ------------------------------------------------------------------------
$rDepoMat = fetchHttp('/materials', $depoSess)['code'];
$rDepoWh = fetchHttp('/warehouses', $depoSess)['code'];
$rDepoShip = fetchHttp('/shipments', $depoSess)['code'];
$rDepoUsers = fetchHttp('/users', $depoSess)['code'];
$rDepoRoles = fetchHttp('/role-permissions', $depoSess)['code'];
$rDepoMaint = fetchHttp('/maintenance', $depoSess)['code'];
$rDepoRecCreate = fetchHttp('/recipes/create', $depoSess)['code'];

$okDepo = ($rDepoMat === 200 && $rDepoWh === 200 && $rDepoShip === 200 && $rDepoUsers === 403 && $rDepoRoles === 403 && $rDepoMaint === 403 && $rDepoRecCreate === 403);
reportTest('08', 'Depo Personeli: Malzeme/Depo/Sevkiyat Açık (200), Bakım/Reçete/Kullanıcı Engelli (403)', $okDepo, "Mat: {$rDepoMat}, Wh: {$rDepoWh}, Ship: {$rDepoShip}, Maint: {$rDepoMaint}");

// ------------------------------------------------------------------------
// BÖLÜM 9: ANONİM / YETKİSİZ URL SALDIRI KORUMALARI
// ------------------------------------------------------------------------
$rAnonUsers = fetchHttp('/users')['code'];
$rAnonRoles = fetchHttp('/role-permissions')['code'];
$rAnonTokens = fetchHttp('/api-tokens')['code'];
$rAnonMaint = fetchHttp('/maintenance')['code'];
$rAnonAdmin = fetchHttp('/admin/dashboard')['code'];

$okAnon = ($rAnonUsers === 302 && $rAnonRoles === 302 && $rAnonTokens === 302 && $rAnonMaint === 302 && $rAnonAdmin === 302);
reportTest('09', 'Anonim Kullanıcı Koruma (Doğrudan URL Girişlerinde HTTP 302 Login Yönlendirmesi)', $okAnon, "Tüm korumalı sayfalar login'e yönlendirildi.");

$totalTests = $passCount + $failCount;

echo "\n========================================================================\n";
echo "TOPLAM RBAC TESTLERİ: {$totalTests}\n";
echo "PASS: {$passCount}\n";
echo "FAIL: {$failCount}\n";
echo "========================================================================\n";

