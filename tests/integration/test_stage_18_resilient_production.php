<?php

/**
 * AŞAMA 18: MES HATA TOLERANSLI ÜRETİM & KESİNTİSİZ SİMÜLASYON TEST PROTOKOLÜ
 */

ob_start();

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/app/Models/Mes.php';
require_once dirname(__DIR__, 2) . '/app/Services/MesSimulationService.php';
require_once dirname(__DIR__, 2) . '/app/Services/MesEventIngestionService.php';
require_once dirname(__DIR__, 2) . '/app/Services/ProductionStockService.php';

$pdo = (new Database())->connect();
$mesModel = new Mes($pdo);
$simService = new MesSimulationService($pdo);
$ingestion = new MesEventIngestionService($pdo);
$stockService = new ProductionStockService($pdo);

echo "========================================================================\n";
echo "=== AŞAMA 18: MES HATA TOLERANSLI ÜRETİM TEST PROTOKOLÜ ===\n";
echo "========================================================================\n\n";

$passCount = 0;
$failCount = 0;

function reportResilienceTest(string $id, string $name, bool $passed, string $details = ''): void {
    global $passCount, $failCount;
    if ($passed) {
        $passCount++;
        printf("[PASS] %-10s | %-45s | %s\n", $id, $name, $details);
    } else {
        $failCount++;
        printf("[FAIL] %-10s | %-45s | %s\n", $id, $name, $details);
    }
}

// ------------------------------------------------------------------------
// Test için temiz bir İş Emri ve Hat hazırla
// ------------------------------------------------------------------------
$recipeRow = $pdo->query("SELECT id, output_material_id FROM recipes WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$recipeId = (int)($recipeRow['id'] ?? 1);
$matId = (int)($recipeRow['output_material_id'] ?? 1);
$lineId = 1;

// Geçici test iş emri oluştur (Hedef: 3 panel)
$testWoNo = 'WO-RESILIENT-' . time();
$pdo->prepare("
    INSERT INTO mes_work_orders (work_order_no, product_material_id, recipe_id, production_line_id, planned_quantity, produced_quantity, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, 3, 0, 'READY', NOW(), NOW())
")->execute([$testWoNo, $matId, $recipeId, $lineId]);
$woId = (int)$pdo->lastInsertId();

// ------------------------------------------------------------------------
// TEST 01: Başarılı Panel Üretimi (Adım 1)
// ------------------------------------------------------------------------
$simService->startSimulation($woId, 180);
$resPanel1 = $simService->processTick($woId, true);
$woAfter1 = $mesModel->getWorkOrderById($woId);
$simAfter1 = $simService->getOrCreateSimulation($woId);

$t01Pass = ($resPanel1['success'] === true && (float)$woAfter1['produced_quantity'] === 1.0 && $woAfter1['status'] === 'RUNNING' && (int)$simAfter1['is_active'] === 1);
reportResilienceTest('TEST 01', 'Başarılı 1. Panel Üretimi', $t01Pass, "Üretilen: {$woAfter1['produced_quantity']}/3 | Durum: {$woAfter1['status']}");

// ------------------------------------------------------------------------
// TEST 02: Panel Hatası -> Transaction Rollback & Kesintisiz Çalışma
// ------------------------------------------------------------------------
$fgBalanceBefore = (float)$pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM stock_balances WHERE material_id = {$matId}")->fetchColumn();
$smCountBefore = (int)$pdo->query("SELECT COUNT(*) FROM stock_movements")->fetchColumn();

// Reçeteyi geçici olarak pasif yapalım (Simüle edilmiş geçici panel entegrasyon hatası)
$pdo->prepare("UPDATE recipes SET is_active = 0 WHERE id = ?")->execute([$recipeId]);

$resPanel2Fail = $simService->processTick($woId, true);

// Eski haline getir
$pdo->prepare("UPDATE recipes SET is_active = 1 WHERE id = ?")->execute([$recipeId]);

$woAfter2 = $mesModel->getWorkOrderById($woId);
$simAfter2 = $simService->getOrCreateSimulation($woId);
$fgBalanceAfterFail = (float)$pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM stock_balances WHERE material_id = {$matId}")->fetchColumn();
$smCountAfterFail = (int)$pdo->query("SELECT COUNT(*) FROM stock_movements")->fetchColumn();

// Panel hatasında:
// 1. produced_quantity artmamalı (hâlâ 1 olmalı)
// 2. İş emri ve simülasyon RUNNING kalmalı (durdurulmamalı!)
// 3. Mamul stoğu artmamalı
// 4. Yarım stok hareketi yazılmamalı
$t02Pass = (
    (float)$woAfter2['produced_quantity'] === 1.0 &&
    $woAfter2['status'] === 'RUNNING' &&
    (int)$simAfter2['is_active'] === 1 &&
    $fgBalanceBefore === $fgBalanceAfterFail &&
    $smCountBefore === $smCountAfterFail &&
    ($resPanel2Fail['action'] ?? '') === 'PANEL_FAILED_CONTINUING'
);
reportResilienceTest('TEST 02', 'Panel Hatası Rollback & RUNNING Korunumu', $t02Pass, "Üretilen: {$woAfter2['produced_quantity']}/3 | Durum: {$woAfter2['status']} | Stok Artışı: 0");

// ------------------------------------------------------------------------
// TEST 03: Hatalı Panel Audit Log Kaydı
// ------------------------------------------------------------------------
$errorLogsCount = (int)$pdo->query("SELECT COUNT(*) FROM mes_event_logs WHERE action LIKE '%PANEL_RECOVERABLE_ERROR%' OR action LIKE '%VALIDATION_FAILED%'")->fetchColumn();
reportResilienceTest('TEST 03', 'Hata Detayı Audit Log Kaydı', $errorLogsCount > 0, "Loglanan hata kayıt sayısı: {$errorLogsCount}");

// ------------------------------------------------------------------------
// TEST 04: Timer Sayımının Kesintisiz Devam Etmesi
// ------------------------------------------------------------------------
$telemetryAfterFail = $simService->getStatus($woId);
$timerContinues = (!empty($simAfter2['next_run_at']) && strtotime($simAfter2['next_run_at']) > time());
reportResilienceTest('TEST 04', 'Timer & Countdown Kesintisiz Devam', $timerContinues, "Sonraki çevrim: {$simAfter2['next_run_at']} (Kalan: {$telemetryAfterFail['simulation']['remaining_seconds']} sn)");

// ------------------------------------------------------------------------
// TEST 05: Hata Sonrası 2. Başarılı Panel Üretimi (Toparlanma)
// ------------------------------------------------------------------------
$resPanel3 = $simService->processTick($woId, true);
$woAfter3 = $mesModel->getWorkOrderById($woId);
$simAfter3 = $simService->getOrCreateSimulation($woId);

$t05Pass = ($resPanel3['success'] === true && (float)$woAfter3['produced_quantity'] === 2.0 && $woAfter3['status'] === 'RUNNING');
reportResilienceTest('TEST 05', 'Hata Sonrası Başarılı Panel (Auto-Recovery)', $t05Pass, "Üretilen: {$woAfter3['produced_quantity']}/3 | Durum: {$woAfter3['status']}");

// ------------------------------------------------------------------------
// TEST 06: Hedef Adede Ulaşma (3/3 Panel) -> COMPLETED Geçişi
// ------------------------------------------------------------------------
$resPanel4 = $simService->processTick($woId, true);
$woAfter4 = $mesModel->getWorkOrderById($woId);
$simAfter4 = $simService->getOrCreateSimulation($woId);

$t06Pass = ($resPanel4['success'] === true && (float)$woAfter4['produced_quantity'] === 3.0 && $woAfter4['status'] === 'COMPLETED' && (int)$simAfter4['is_active'] === 0);
reportResilienceTest('TEST 06', 'Hedefe Ulaşınca Otomatik COMPLETED', $t06Pass, "Üretilen: {$woAfter4['produced_quantity']}/3 | Durum: {$woAfter4['status']}");

// ------------------------------------------------------------------------
// TEST 07: Fatal Hata (Hat Bakımı / Arızası) -> Simülasyon Durdurma
// ------------------------------------------------------------------------
$pdo->prepare("
    INSERT INTO mes_work_orders (work_order_no, product_material_id, recipe_id, production_line_id, planned_quantity, produced_quantity, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, 5, 0, 'READY', NOW(), NOW())
")->execute(['WO-FATAL-TEST-' . time(), $matId, $recipeId, $lineId]);
$woIdFatal = (int)$pdo->lastInsertId();

// Önce iş emrini başlat
$simService->startSimulation($woIdFatal, 180);

// Hattı geçici olarak bakıma alalım
$pdo->prepare("UPDATE production_lines SET status = 'MAINTENANCE' WHERE id = ?")->execute([$lineId]);

$resFatal = $simService->processTick($woIdFatal, true);
$woFatalDb = $mesModel->getWorkOrderById($woIdFatal);
$simFatalDb = $simService->getOrCreateSimulation($woIdFatal);

// Hattı geri aktif edelim
$pdo->prepare("UPDATE production_lines SET status = 'RUNNING' WHERE id = ?")->execute([$lineId]);

$t07Pass = ($resFatal['status'] === 'LINE_NOT_AVAILABLE' && $woFatalDb['status'] === 'PAUSED' && (int)$simFatalDb['is_active'] === 0);
reportResilienceTest('TEST 07', 'Fatal Hat Hatasında Simülasyon Durdurma', $t07Pass, "Hat bakımdayken simülasyon güvenle PAUSED durumuna geçti");

// ------------------------------------------------------------------------
// TEST 08: Panel Bazlı Entegrasyon Hatası İzolasyonu
// ------------------------------------------------------------------------
$pdo->prepare("
    INSERT INTO mes_work_orders (work_order_no, product_material_id, recipe_id, production_line_id, planned_quantity, produced_quantity, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, 5, 0, 'READY', NOW(), NOW())
")->execute(['WO-STOCK-HALT-' . time(), $matId, $recipeId, $lineId]);
$woIdStock = (int)$pdo->lastInsertId();

$simService->startSimulation($woIdStock, 180);

// Reçeteyi geçici pasif yaparak tek panel hatası simüle et
$pdo->prepare("UPDATE recipes SET is_active = 0 WHERE id = ?")->execute([$recipeId]);
$resStockTick = $simService->processTick($woIdStock, true);
$pdo->prepare("UPDATE recipes SET is_active = 1 WHERE id = ?")->execute([$recipeId]);

$woStockDb = $mesModel->getWorkOrderById($woIdStock);
$simStockDb = $simService->getOrCreateSimulation($woIdStock);

$t08Pass = ($woStockDb['status'] === 'RUNNING' && (int)$simStockDb['is_active'] === 1);
reportResilienceTest('TEST 08', 'Panel Bazlı Entegrasyon Hatası İzolasyonu', $t08Pass, "Geçersiz reçete çağrısı ana simülasyonu çökertmedi, RUNNING kaldı");

// Temizlik
$pdo->prepare("DELETE FROM mes_simulations WHERE work_order_id IN (?, ?, ?)")->execute([$woId, $woIdFatal, $woIdStock]);
$pdo->prepare("DELETE FROM mes_production_events WHERE work_order_id IN (?, ?, ?)")->execute([$woId, $woIdFatal, $woIdStock]);
$pdo->prepare("DELETE FROM mes_work_orders WHERE id IN (?, ?, ?)")->execute([$woId, $woIdFatal, $woIdStock]);

$total = $passCount + $failCount;
echo "\n========================================================================\n";
echo "MES HATA TOLERANSLI ÜRETİM TEST PROTOKOLÜ SONUCU: {$passCount}/{$total} PASS\n";
echo "========================================================================\n";
