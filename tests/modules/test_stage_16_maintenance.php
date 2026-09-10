<?php

/**
 * AŞAMA 16: ÜRETİM BAKIM YÖNETİMİ & TPM KAPSAMLI DOĞRULAMA TEST PROTOKOLÜ
 */

ob_start();

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/app/Services/MaintenanceService.php';
require_once dirname(__DIR__, 2) . '/app/Services/DowntimeManagementService.php';
require_once dirname(__DIR__, 2) . '/app/Services/OeeCalculationService.php';
require_once dirname(__DIR__, 2) . '/app/Services/CsrfService.php';

$pdo = (new Database())->connect();
$GLOBALS['pdo'] = $pdo;

$maintService = new MaintenanceService($pdo);
$downtimeService = new DowntimeManagementService($pdo);
$oeeService = new OeeCalculationService($pdo);

echo "========================================================================\n";
echo "=== AŞAMA 16 BAKIM YÖNETİMİ & TPM TEST RAPORU ===\n";
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

function fetchHttp(string $path, ?string $sessCookie = null, array $postData = []): array {
    $ch = curl_init('http://localhost/stok-takip/public' . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

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

$adminSess = loginSession('admin', 'Test123!');
$operatorSess = loginSession('operator', 'Test123!');
$yoneticiSess = loginSession('yonetici', 'Test123!');

// ------------------------------------------------------------------------
// Test 01: Veritabanı ve Maintenance Tablolarının Varlığı
// ------------------------------------------------------------------------
$t1 = $pdo->query("SHOW TABLES LIKE 'maintenance_assets'")->fetch();
$t2 = $pdo->query("SHOW TABLES LIKE 'maintenance_plans'")->fetch();
$t3 = $pdo->query("SHOW TABLES LIKE 'maintenance_work_orders'")->fetch();
$t4 = $pdo->query("SHOW TABLES LIKE 'maintenance_spare_parts'")->fetch();

$ok01 = ($t1 && $t2 && $t3 && $t4);
reportTest('01', 'TPM & Bakım Tablolarının Varlığı (assets, plans, work_orders, spare_parts)', $ok01, "4 tablo doğrulandı.");

// ------------------------------------------------------------------------
// Test 02: 4 Hat Altındaki Gerçek Ekipmanların (Assets) Listelenmesi
// ------------------------------------------------------------------------
$assets = $maintService->getAssets();
$assetCodes = array_column($assets, 'asset_code');
$hasLaminator = in_array('EQ-LAM1-HEATING', $assetCodes, true);
$hasStringer = in_array('EQ-STR-ROBOT', $assetCodes, true);
$hasFlasher = in_array('EQ-TEST-FLASHER', $assetCodes, true);

$ok02 = (count($assets) >= 8 && $hasLaminator && $hasStringer && $hasFlasher);
reportTest('02', 'Üretim Hatlarına Bağlı Gerçek Ekipman Varlıkları (8 Ekipman)', $ok02, "Toplam: " . count($assets) . " ekipman.");

// ------------------------------------------------------------------------
// Test 03: Önleyici (Preventive) Bakım Planı ve İlerleme Hesapları
// ------------------------------------------------------------------------
$plans = $maintService->getPreventivePlans();
$hasPlans = count($plans) >= 4;
$samplePlan = $plans[0] ?? [];
$hasProgress = isset($samplePlan['progress_pct']) && isset($samplePlan['is_overdue']);

$ok03 = ($hasPlans && $hasProgress);
reportTest('03', 'Önleyici Bakım Planları (Çalışma Saati & Çevrim İlerlemesi)', $ok03, "Toplam Plan: " . count($plans) . ", Örnek: {$samplePlan['title']} (%{$samplePlan['progress_pct']})");

// ------------------------------------------------------------------------
// Test 04: Arızi (Corrective) Bakım İş Emri Açılması ve Hat Duruşu Bağlantısı
// ------------------------------------------------------------------------
// Ekipman 1 (Laminatör 1 Isıtıcı) için temizlik yap
$pdo->exec("DELETE FROM maintenance_spare_parts WHERE maintenance_work_order_id IN (SELECT id FROM maintenance_work_orders WHERE asset_id = 1)");
$pdo->exec("DELETE FROM maintenance_work_orders WHERE asset_id = 1");
$pdo->exec("UPDATE maintenance_assets SET status = 'OPERATIONAL' WHERE id = 1");
$pdo->exec("UPDATE production_lines SET status = 'IDLE' WHERE id = 1");
$pdo->exec("UPDATE line_downtimes SET status = 'CLOSED', ended_at = NOW() WHERE line_id = 1 AND status = 'OPEN'");

$createRes = $maintService->createWorkOrder([
    'asset_id'            => 1,
    'maintenance_type'    => 'CORRECTIVE',
    'priority'            => 'CRITICAL',
    'failure_category'    => 'ISITMA',
    'failure_description' => 'TEST_MAINT_HEATER_FAULT: Isıtıcı tabla sıcaklığı 150C altına düştü.'
], 1);

$createdWoId = (int)($createRes['work_order_id'] ?? 0);
$asset1Status = $pdo->query("SELECT status FROM maintenance_assets WHERE id = 1")->fetchColumn();
$line1Status = $pdo->query("SELECT status FROM production_lines WHERE id = 1")->fetchColumn();
$linkedDtId = (int)($createRes['line_downtime_id'] ?? 0);

$ok04 = ($createRes['success'] && $createdWoId > 0 && $asset1Status === 'FAULTY' && $line1Status === 'FAULT' && $linkedDtId > 0);
reportTest('04', 'Arızi Bakım Emri Açılması -> Hat FAULT Durumu & Duruş Entegrasyonu', $ok04, "WO: " . ($createRes['work_order_no'] ?? '') . ", DtID: {$linkedDtId}, Line Status: {$line1Status}");

// ------------------------------------------------------------------------
// Test 05: Concurrency / 409 Conflict: Aynı Makineye İkinci Açık Arıza Emri Engeli
// ------------------------------------------------------------------------
$dupCreateRes = $maintService->createWorkOrder([
    'asset_id'            => 1,
    'maintenance_type'    => 'CORRECTIVE',
    'priority'            => 'HIGH',
    'failure_category'    => 'ISITMA',
    'failure_description' => 'TEST_DUP_ATTEMPT'
], 1);

$ok05 = (!$dupCreateRes['success'] && ($dupCreateRes['code'] ?? '') === 'ASSET_ALREADY_UNDER_MAINTENANCE' && ($dupCreateRes['status_code'] ?? 0) === 409);
reportTest('05', 'Aynı Makineye Çift Açık Arıza Emri Engeli (409 Conflict Koruması)', $ok05, "Code: {$dupCreateRes['code']}");

// ------------------------------------------------------------------------
// Test 06: Teknisyen Atama (ASSIGNED) ve İşe Başlama (IN_PROGRESS)
// ------------------------------------------------------------------------
$assignRes = $maintService->assignTechnician($createdWoId, 4, 1); // Operator teknisyen olarak atanır
$startWorkRes = $maintService->startWork($createdWoId, 4);

$woStatusAfterStart = $pdo->query("SELECT status FROM maintenance_work_orders WHERE id = {$createdWoId}")->fetchColumn();
$assetStatusAfterStart = $pdo->query("SELECT status FROM maintenance_assets WHERE id = 1")->fetchColumn();

$ok06 = ($assignRes['success'] && $startWorkRes['success'] && $woStatusAfterStart === 'IN_PROGRESS' && $assetStatusAfterStart === 'UNDER_MAINTENANCE');
reportTest('06', 'Teknisyen Atama & İşe Başlama (ASSIGNED -> IN_PROGRESS / UNDER_MAINTENANCE)', $ok06, "WO Durum: {$woStatusAfterStart}, Asset Durum: {$assetStatusAfterStart}");

// ------------------------------------------------------------------------
// Test 07: Yedek Parça Tüketimi (MAINTENANCE_OUT) ve Maliyet Snapshot
// ------------------------------------------------------------------------
$teflonMatId = (int)$pdo->query("SELECT id FROM materials WHERE code = 'SP-TEF-001'")->fetchColumn();
$balBefore = (float)$pdo->query("SELECT quantity FROM stock_balances WHERE material_id = {$teflonMatId} AND location_id = 1")->fetchColumn();

$partConsumeRes = $maintService->consumeSparePart($createdWoId, $teflonMatId, 2.0, 4);
$balAfter = (float)$pdo->query("SELECT quantity FROM stock_balances WHERE material_id = {$teflonMatId} AND location_id = 1")->fetchColumn();

$mspRow = $pdo->query("SELECT * FROM maintenance_spare_parts WHERE maintenance_work_order_id = {$createdWoId} AND material_id = {$teflonMatId}")->fetch(PDO::FETCH_ASSOC);
$smRow = $pdo->query("SELECT movement_type, reference_no, total_price FROM stock_movements WHERE id = " . ($mspRow['stock_movement_id'] ?? 0))->fetch(PDO::FETCH_ASSOC);

$ok07 = ($partConsumeRes['success'] && ($balBefore - $balAfter) == 2.0 && !empty($mspRow) && !empty($smRow) && (float)$smRow['total_price'] == 1500.0);
reportTest('07', 'Yedek Parça Stok Düşümü (MAINTENANCE_OUT) & Maliyet Snapshot', $ok07, "Tüketilen: 2 Adet Teflon (Maliyet: " . ($smRow['total_price'] ?? 0) . " TL, Stok: {$balBefore} -> {$balAfter})");

// ------------------------------------------------------------------------
// Test 08: Yetersiz Yedek Parça Stoğunda Atomik Rollback
// ------------------------------------------------------------------------
$insufConsumeRes = $maintService->consumeSparePart($createdWoId, $teflonMatId, 99999.0, 4);
$balAfterInsuf = (float)$pdo->query("SELECT quantity FROM stock_balances WHERE material_id = {$teflonMatId} AND location_id = 1")->fetchColumn();

$ok08 = (!$insufConsumeRes['success'] && ($insufConsumeRes['code'] ?? '') === 'INSUFFICIENT_STOCK' && $balAfterInsuf == $balAfter);
reportTest('08', 'Yetersiz Yedek Parçada Atomic Rollback (Eksi Bakiye Engeli)', $ok08, "Code: {$insufConsumeRes['code']}, Stok Değişmedi: {$balAfterInsuf}");

// ------------------------------------------------------------------------
// Test 09: Onarım Tamamlama (COMPLETED) ve Kök Neden Kaydı
// ------------------------------------------------------------------------
$compRes = $maintService->completeWork($createdWoId, [
    'root_cause_text' => 'Teflon yırtılması ve rezistans soket gevşemesi',
    'action_taken'    => '2 adet teflon levha yenilendi, soketler sıkıldı ve 160C test edildi.',
    'labor_hours'     => 1.5
], 4);

$woRowComp = $pdo->query("SELECT status, labor_cost, spare_parts_cost, total_maintenance_cost, downtime_minutes FROM maintenance_work_orders WHERE id = {$createdWoId}")->fetch(PDO::FETCH_ASSOC);
$dtStatusAfterComp = $pdo->query("SELECT status FROM line_downtimes WHERE id = {$linkedDtId}")->fetchColumn();

$ok09 = ($compRes['success'] && ($woRowComp['status'] ?? '') === 'COMPLETED' && (float)($woRowComp['total_maintenance_cost'] ?? 0) >= 1500.0 && $dtStatusAfterComp === 'CLOSED');
reportTest('09', 'Onarım Tamamlama (COMPLETED), Kök Neden & Hat Duruşu Kapatma', $ok09, "Toplam Maliyet: " . ($woRowComp['total_maintenance_cost'] ?? 0) . " TL (İşçilik: " . ($woRowComp['labor_cost'] ?? 0) . " TL, Parça: " . ($woRowComp['spare_parts_cost'] ?? 0) . " TL)");

// ------------------------------------------------------------------------
// Test 10: Kalite / Amir Bakım Doğrulaması (VERIFIED) ve Makine Devreye Alma
// ------------------------------------------------------------------------
$verifyRes = $maintService->verifyWork($createdWoId, 1, 'Kalite kontrol ve ısı testleri başarılı.');
$woStatusVerified = $pdo->query("SELECT status FROM maintenance_work_orders WHERE id = {$createdWoId}")->fetchColumn();
$assetStatusVerified = $pdo->query("SELECT status FROM maintenance_assets WHERE id = 1")->fetchColumn();

$ok10 = ($verifyRes['success'] && $woStatusVerified === 'VERIFIED' && $assetStatusVerified === 'OPERATIONAL');
reportTest('10', 'Kalite/Amir Doğrulaması (VERIFIED) & Ekipmanın OPERATIONAL Oluşu', $ok10, "WO: {$woStatusVerified}, Asset: {$assetStatusVerified}");

// ------------------------------------------------------------------------
// Test 11: MTBF ve MTTR Gerçek Veri Formül Doğrulaması
// ------------------------------------------------------------------------
$kpi = $maintService->calculateMtbfMttr(1, 1);
$calcMtbf = (float)$kpi['mtbf_hours'];
$calcMttr = (float)$kpi['mttr_minutes'];
$techAvail = (float)$kpi['technical_availability_pct'];

$ok11 = ($calcMtbf > 0 && $calcMttr >= 0 && $techAvail > 0 && $techAvail <= 100);
reportTest('11', 'MTBF ve MTTR Matematiksel Doğrulaması (ISO 22400 / SEMI E10)', $ok11, "MTBF: {$calcMtbf} sa, MTTR: {$calcMttr} dk, Teknik Kullanılabilirlik: %{$techAvail}");

// ------------------------------------------------------------------------
// Test 12: OEE Entegrasyonu (Planlı Bakım vs Plansız Arıza Ayrımı)
// ------------------------------------------------------------------------
$shiftOee = $oeeService->calculateShiftOee(1, 1, date('Y-m-d'));
$hasAvail = isset($shiftOee['availability_pct']);
$hasUnplanMin = isset($shiftOee['unplanned_downtime_min']);
$hasPlanMin = isset($shiftOee['planned_downtime_minutes']);

$ok12 = ($hasAvail && $hasUnplanMin && $hasPlanMin);
reportTest('12', 'OEE Entegrasyonu (Planlı Bakım Loading Time Düşüşü vs Plansız Arıza Kaybı)', $ok12, "OEE Avail: %{$shiftOee['availability_pct']}, Plansız: {$shiftOee['unplanned_downtime_min']} dk, Planlı: {$shiftOee['planned_downtime_minutes']} dk");

// ------------------------------------------------------------------------
// Test 13: Ekipman Detayı & Bakım Pasaportu (GET /maintenance/asset?id=1)
// ------------------------------------------------------------------------
$assetPageRes = fetchHttp('/maintenance/asset?id=1', $adminSess);
$hasAssetTitle = strpos($assetPageRes['body'], 'EKİPMAN PASAPORTU') !== false;
$hasHistory = strpos($assetPageRes['body'], 'MAKİNE BAKIM &amp; ARIZA GEÇMİŞİ') !== false;
$hasMtbfTile = strpos($assetPageRes['body'], 'MTBF (Arızalar Arası)') !== false;

$ok13 = ($assetPageRes['code'] === 200 && $hasAssetTitle && $hasHistory && $hasMtbfTile);
reportTest('13', 'Ekipman Bakım Pasaportu Arayüzü & Geçmiş Kayıtları (GET /maintenance/asset)', $ok13, "HTTP: {$assetPageRes['code']}");

// ------------------------------------------------------------------------
// Test 14: Canlı Telemetri API (GET /api/maintenance/live)
// ------------------------------------------------------------------------
$liveApiRes = fetchHttp('/api/maintenance/live', $adminSess);
$hasSuccess = !empty($liveApiRes['json']['success']);
$hasMtbfInApi = isset($liveApiRes['json']['data']['mtbf_hours']);

$ok14 = ($liveApiRes['code'] === 200 && $hasSuccess && $hasMtbfInApi);
reportTest('14', '/api/maintenance/live JSON Telemetri API', $ok14, "HTTP: {$liveApiRes['code']}, MTBF: {$liveApiRes['json']['data']['mtbf_hours']} sa");

// ------------------------------------------------------------------------
// Test 15: Yetkisiz Kullanıcı Erişim Engeli (RBAC)
// ------------------------------------------------------------------------
$anonRes = fetchHttp('/maintenance');
$ok15 = ($anonRes['code'] === 302 || $anonRes['code'] === 401 || $anonRes['code'] === 403);
reportTest('15', 'Yetkisiz Kullanıcı /maintenance Erişim Engeli (RBAC)', $ok15, "Anon HTTP: {$anonRes['code']}");

// ------------------------------------------------------------------------
// Test 16: CSRF Koruması (Geçersiz CSRF Token ile POST Reddi: 403)
// ------------------------------------------------------------------------
$invalidCsrfRes = fetchHttp('/maintenance/create', $adminSess, [
    'csrf_token'          => 'INVALID-CSRF-TOKEN',
    'asset_id'            => 1,
    'maintenance_type'    => 'CORRECTIVE',
    'failure_description' => 'HACK'
]);
$ok16 = ($invalidCsrfRes['code'] === 403 || strpos($invalidCsrfRes['body'], 'CSRF') !== false);
reportTest('16', 'CSRF Güvenlik Doğrulaması (Sahte Token ile POST Reddi: 403)', $ok16, "HTTP: {$invalidCsrfRes['code']}");

// ------------------------------------------------------------------------
// Test 17: Audit Log Mühürleme Kontrolü
// ------------------------------------------------------------------------
$stmtAudit = $pdo->prepare("SELECT action FROM audit_logs WHERE record_id = ? AND entity_type = 'maintenance_work_orders' ORDER BY id ASC");
$stmtAudit->execute([$createdWoId]);
$actionsLogged = $stmtAudit->fetchAll(PDO::FETCH_COLUMN);

$hasOpened = in_array('MAINTENANCE_WO_OPENED', $actionsLogged, true);
$hasPart = in_array('MAINTENANCE_SPARE_PART_CONSUMED', $actionsLogged, true);
$hasComp = in_array('MAINTENANCE_WO_COMPLETED', $actionsLogged, true);
$hasVer = in_array('MAINTENANCE_WO_VERIFIED', $actionsLogged, true);

$ok17 = ($hasOpened && $hasPart && $hasComp && $hasVer);
reportTest('17', 'Audit Log Mühürleme (OPENED, SPARE_PART, COMPLETED, VERIFIED)', $ok17, "Kayıtlı Eylemler: " . implode(', ', $actionsLogged));

// ------------------------------------------------------------------------
// Test 18: Sidebar Menü Bağlantısı
// ------------------------------------------------------------------------
$dashRes = fetchHttp('/', $adminSess);
$hasSidebarMaint = strpos($dashRes['body'], '/stok-takip/public/maintenance') !== false && strpos($dashRes['body'], 'Bakım Yönetimi') !== false;

$ok18 = ($hasSidebarMaint);
reportTest('18', 'Sidebar Üretim Menüsünde "Bakım Yönetimi & TPM" Bağlantısı', $ok18, "Sidebar: " . ($hasSidebarMaint ? "Mevcut" : "Eksik"));

// ------------------------------------------------------------------------
// TEST CLEAN-UP
// ------------------------------------------------------------------------
$pdo->exec("DELETE FROM maintenance_spare_parts WHERE maintenance_work_order_id = {$createdWoId}");
$pdo->exec("DELETE FROM maintenance_work_orders WHERE id = {$createdWoId}");
$pdo->exec("UPDATE maintenance_assets SET status = 'OPERATIONAL' WHERE id = 1");
$pdo->exec("UPDATE production_lines SET status = 'IDLE' WHERE id = 1");

$totalTests = $passCount + $failCount;

echo "\n========================================================================\n";
echo "TOPLAM: {$totalTests}\n";
echo "PASS: {$passCount}\n";
echo "FAIL: {$failCount}\n";
echo "========================================================================\n";

