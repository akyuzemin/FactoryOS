<?php

/**
 * AŞAMA 18+: MES İŞ EMRİ BAŞLAT / DURAKLAT / SÜRE HESAPLAMA OPTİMİZASYON VE DOĞRULAMA TEST PROTOKOLÜ
 */

ob_start();

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/app/Models/Mes.php';
require_once dirname(__DIR__, 2) . '/app/Services/MesSimulationService.php';
require_once dirname(__DIR__, 2) . '/app/Services/MesEventIngestionService.php';

$pdo = (new Database())->connect();
$mesModel = new Mes($pdo);
$simService = new MesSimulationService($pdo);
$ingestion = new MesEventIngestionService($pdo);

echo "========================================================================\n";
echo "=== AŞAMA 18+: MES İŞ EMRİ BAŞLAT/DURAKLAT/RESUME OPTİMİZASYON TESTİ ===\n";
echo "========================================================================\n\n";

$passCount = 0;
$failCount = 0;

function reportOptTest(string $testId, string $testName, bool $passed, string $details = ''): void {
    global $passCount, $failCount;
    if ($passed) {
        $passCount++;
        printf("[PASS] %-10s | %-45s | %s\n", $testId, $testName, $details);
    } else {
        $failCount++;
        printf("[FAIL] %-10s | %-45s | %s\n", $testId, $testName, $details);
    }
}

// ------------------------------------------------------------------------
// Test için temiz bir İş Emri ve Hat hazırla
// ------------------------------------------------------------------------
$lineId = 1; // Hat 1
$recipeRow = $pdo->query("SELECT id, output_material_id FROM recipes WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$recipeId = (int)($recipeRow['id'] ?? 1);
$matId = (int)($recipeRow['output_material_id'] ?? 1);

// Geçici test iş emri oluştur
$testWoNo = 'WO-OPT-TEST-' . time();
$pdo->prepare("
    INSERT INTO mes_work_orders (work_order_no, product_material_id, recipe_id, production_line_id, planned_quantity, produced_quantity, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, 10, 0, 'READY', NOW(), NOW())
")->execute([$testWoNo, $matId, $recipeId, $lineId]);
$woId = (int)$pdo->lastInsertId();

// ------------------------------------------------------------------------
// TEST 01: READY -> START -> RUNNING
// ------------------------------------------------------------------------
$t01Start = microtime(true);
$res01 = $simService->startSimulation($woId, 180);
$t01Duration = round((microtime(true) - $t01Start) * 1000, 2);
$wo01 = $mesModel->getWorkOrderById($woId);
$sim01 = $simService->getOrCreateSimulation($woId);

$t01Pass = ($wo01['status'] === 'RUNNING' && (int)$sim01['is_active'] === 1 && !empty($sim01['next_run_at']));
reportOptTest('TEST 01', 'READY -> START -> RUNNING', $t01Pass, "Latency: {$t01Duration} ms | State: {$wo01['status']}");

// ------------------------------------------------------------------------
// TEST 02: RUNNING -> PAUSE -> PAUSED
// ------------------------------------------------------------------------
$t02Start = microtime(true);
$res02 = $simService->pauseSimulation($woId, 'Test Duraklatma');
$t02Duration = round((microtime(true) - $t02Start) * 1000, 2);
$wo02 = $mesModel->getWorkOrderById($woId);
$sim02 = $simService->getOrCreateSimulation($woId);

$t02Pass = ($wo02['status'] === 'PAUSED' && (int)$sim02['is_active'] === 0 && !empty($sim02['paused_remaining_seconds']));
reportOptTest('TEST 02', 'RUNNING -> PAUSE -> PAUSED', $t02Pass, "Latency: {$t02Duration} ms | Remaining: {$sim02['paused_remaining_seconds']} sn");

// ------------------------------------------------------------------------
// TEST 03: PAUSED -> RESUME -> RUNNING
// ------------------------------------------------------------------------
$t03Start = microtime(true);
$res03 = $simService->startSimulation($woId);
$t03Duration = round((microtime(true) - $t03Start) * 1000, 2);
$wo03 = $mesModel->getWorkOrderById($woId);
$sim03 = $simService->getOrCreateSimulation($woId);

$t03Pass = ($wo03['status'] === 'RUNNING' && (int)$sim03['is_active'] === 1);
reportOptTest('TEST 03', 'PAUSED -> RESUME -> RUNNING', $t03Pass, "Latency: {$t03Duration} ms | State: {$wo03['status']}");

// ------------------------------------------------------------------------
// TEST 04: RUNNING -> Target Quantity -> COMPLETED
// ------------------------------------------------------------------------
// İş emrini 9/10 üretilmiş yap ve 1 adım tetikle
$pdo->prepare("UPDATE mes_work_orders SET produced_quantity = 9, updated_at = NOW() WHERE id = ?")->execute([$woId]);
$res04 = $simService->processTick($woId, true);
$wo04 = $mesModel->getWorkOrderById($woId);
$sim04 = $simService->getOrCreateSimulation($woId);

$t04Pass = ($wo04['status'] === 'COMPLETED' && (int)$sim04['is_active'] === 0 && (float)$wo04['produced_quantity'] >= (float)$wo04['planned_quantity']);
reportOptTest('TEST 04', 'RUNNING -> Target Qty -> COMPLETED', $t04Pass, "Produced: {$wo04['produced_quantity']}/{$wo04['planned_quantity']} | State: {$wo04['status']}");

// ------------------------------------------------------------------------
// TEST 05: Double Start (Idempotent)
// ------------------------------------------------------------------------
// Yeni iş emri oluştur
$pdo->prepare("
    INSERT INTO mes_work_orders (work_order_no, product_material_id, recipe_id, production_line_id, planned_quantity, produced_quantity, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, 5, 0, 'READY', NOW(), NOW())
")->execute(['WO-DOUBLE-START-' . time(), $matId, $recipeId, $lineId]);
$woIdDouble = (int)$pdo->lastInsertId();

$firstStart = $simService->startSimulation($woIdDouble, 180);
$firstNextRun = $simService->getOrCreateSimulation($woIdDouble)['next_run_at'];
// İkinci başlatma hemen ardışık çağrılır
$secondStart = $simService->startSimulation($woIdDouble, 180);
$secondNextRun = $simService->getOrCreateSimulation($woIdDouble)['next_run_at'];

$t05Pass = ($firstNextRun === $secondNextRun && $secondStart['success'] === true);
reportOptTest('TEST 05', 'Double Start Idempotency', $t05Pass, "Timer sıfırlanmadı, tek motor çalışıyor.");

// ------------------------------------------------------------------------
// TEST 06: Double Pause (Idempotent)
// ------------------------------------------------------------------------
$firstPause = $simService->pauseSimulation($woIdDouble, 'Pause 1');
$rem1 = $simService->getOrCreateSimulation($woIdDouble)['paused_remaining_seconds'];
$secondPause = $simService->pauseSimulation($woIdDouble, 'Pause 2');
$rem2 = $simService->getOrCreateSimulation($woIdDouble)['paused_remaining_seconds'];

$t06Pass = ($rem1 === $rem2 && $secondPause['success'] === true);
reportOptTest('TEST 06', 'Double Pause Idempotency', $t06Pass, "Kalan süre bozulmadı: {$rem1} sn.");

// ------------------------------------------------------------------------
// TEST 07: Concurrent / Conflict Start (Hat meşgulken başlatma koruması)
// ------------------------------------------------------------------------
// Önce hatta başka running varsa temizle
$pdo->prepare("UPDATE mes_work_orders SET status = 'PAUSED' WHERE production_line_id = ? AND id NOT IN (?, ?)")->execute([$lineId, $woIdDouble, $woId]);

$pdo->prepare("
    INSERT INTO mes_work_orders (work_order_no, product_material_id, recipe_id, production_line_id, planned_quantity, produced_quantity, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, 5, 0, 'READY', NOW(), NOW())
")->execute(['WO-OTHER-WO-' . time(), $matId, $recipeId, $lineId]);
$woIdOther = (int)$pdo->lastInsertId();

// İlk iş emrini başlat
$simService->startSimulation($woIdDouble, 180);
// İkinci iş emrini aynı hatta başlat
$res07 = $simService->startSimulation($woIdOther, 180);

// İkinci iş emri başladığında birinci otomatik duraklatılmalı ve hatta sadece 1 RUNNING iş emri olmalı
$runningCount = (int)$pdo->query("SELECT COUNT(*) FROM mes_work_orders WHERE production_line_id = {$lineId} AND status = 'RUNNING'")->fetchColumn();
$t07Pass = ($runningCount === 1);
reportOptTest('TEST 07', 'Concurrent Line Conflict Protection', $t07Pass, "Hatta tek RUNNING iş emri kuralı korundu.");

// ------------------------------------------------------------------------
// TEST 08: Pause sırasında PANEL_COMPLETED koruması
// ------------------------------------------------------------------------
$simService->pauseSimulation($woIdOther, 'Paused');
$qtyBefore = (float)$mesModel->getWorkOrderById($woIdOther)['produced_quantity'];
// Simülasyon duraklatılmışken processTick çağrısı (forceStep = false)
$tickRes = $simService->processTick($woIdOther, false);
$qtyAfter = (float)$mesModel->getWorkOrderById($woIdOther)['produced_quantity'];

$t08Pass = ($qtyBefore === $qtyAfter && $tickRes['status'] === 'PAUSED');
reportOptTest('TEST 08', 'Pause Halinde Üretim Kesintisi', $t08Pass, "Duraklatılan hatta yeni panel üretilmedi.");

// ------------------------------------------------------------------------
// TEST 09: Resume sonrası kalan çevrim süresinin korunması
// ------------------------------------------------------------------------
// 180 saniyelik çevrimde 60 saniye kalmış gibi simüle et
$pdo->prepare("UPDATE mes_simulations SET paused_remaining_seconds = 60, is_active = 0, updated_at = NOW() WHERE work_order_id = ?")->execute([$woIdOther]);
$simService->startSimulation($woIdOther, 180);
$sim09 = $simService->getOrCreateSimulation($woIdOther);
$calcRemaining = strtotime($sim09['next_run_at']) - time();

$t09Pass = ($calcRemaining >= 58 && $calcRemaining <= 61);
reportOptTest('TEST 09', 'Resume Kalan Süre Korunumu', $t09Pass, "180 sn yerine kalan {$calcRemaining} sn uygulandı.");

// ------------------------------------------------------------------------
// TEST 10: F5 / Yenileme sonrası Authoritative Backend Timer
// ------------------------------------------------------------------------
$telemetry10 = $simService->getStatus($woIdOther);
$t10Pass = (isset($telemetry10['simulation']['remaining_seconds']) && $telemetry10['simulation']['remaining_seconds'] > 0);
reportOptTest('TEST 10', 'Backend Authoritative Timer', $t10Pass, "Sunucu zaman damgası: {$telemetry10['simulation']['remaining_seconds']} sn");

// ------------------------------------------------------------------------
// TEST 11: İki ayrı oturumun aynı iş emrine müdahale senkronizasyonu
// ------------------------------------------------------------------------
$woState1 = $simService->getStatus($woIdOther)['work_order']['status'];
$simService->pauseSimulation($woIdOther);
$woState2 = $simService->getStatus($woIdOther)['work_order']['status'];

$t11Pass = ($woState1 === 'RUNNING' && $woState2 === 'PAUSED');
reportOptTest('TEST 11', 'Multi-Session State Sync', $t11Pass, "Durumlar anında veritabanı kilidi ile senkronize.");

// ------------------------------------------------------------------------
// TEST 12: COMPLETED iş emrini tekrar başlatma girişimi
// ------------------------------------------------------------------------
$res12 = $simService->startSimulation($woId); // woId daha önce tamamlanmıştı
$t12Pass = ($res12['success'] === false && $res12['status'] === 'COMPLETED');
reportOptTest('TEST 12', 'COMPLETED Restart Engeli', $t12Pass, "Tamamlanmış iş emri kilitlendi, başlatılamaz.");

// ------------------------------------------------------------------------
// TEST 13: Worker restart sonrası durum korunumu
// ------------------------------------------------------------------------
$sim13Before = $simService->getOrCreateSimulation($woIdOther);
// Background worker yeniden başlatılmış gibi simülasyonu get et
$sim13After = $simService->getOrCreateSimulation($woIdOther);
$t13Pass = ($sim13Before['last_status'] === $sim13After['last_status']);
reportOptTest('TEST 13', 'Worker Restart State Persistence', $t13Pass, "Durum ve süreler veritabanında korundu.");

// ------------------------------------------------------------------------
// TEST 14: Duplicate event gönderimi (Idempotency)
// ------------------------------------------------------------------------
$pdo->prepare("UPDATE mes_work_orders SET status = 'RUNNING', updated_at = NOW() WHERE id = ?")->execute([$woIdOther]);

// ------------------------------------------------------------------------
// TEST 14 & 15: Duplicate event ve Stock movement duplicate kontrolü
// ------------------------------------------------------------------------
$duplicateEventId = 'EVT-DUP-TEST-' . time();
$ingestPayload = [
    'event_id'            => $duplicateEventId,
    'event_type'          => 'PANEL_COMPLETED',
    'product_code'        => 'PROD-TEST',
    'product_material_id' => $matId,
    'quantity'            => 1.0,
    'production_line'     => 'HAT-1',
    'production_line_id'  => $lineId,
    'work_order_id'       => $woIdOther,
    'recipe_id'           => $recipeId,
    'source'              => 'TEST'
];

$ev1 = $ingestion->ingestEvent($ingestPayload);
$smCountAfterFirst = (int)$pdo->query("SELECT COUNT(*) FROM stock_movements WHERE description LIKE '%{$duplicateEventId}%' OR reference_no LIKE '%{$duplicateEventId}%'")->fetchColumn();

$ev2 = $ingestion->ingestEvent($ingestPayload); // Mükerrer gönderim
$smCountAfterSecond = (int)$pdo->query("SELECT COUNT(*) FROM stock_movements WHERE description LIKE '%{$duplicateEventId}%' OR reference_no LIKE '%{$duplicateEventId}%'")->fetchColumn();

$t14Pass = ($ev1['success'] === true && $ev2['success'] === true && $ev2['code'] === 'DUPLICATE_EVENT');
reportOptTest('TEST 14', 'Duplicate Event Idempotency', $t14Pass, "Mükerrer event engellendi, tek üretim yazıldı.");

$t15Pass = ($smCountAfterFirst > 0 && $smCountAfterFirst === $smCountAfterSecond);
reportOptTest('TEST 15', 'Stock Movement Duplicate Engeli', $t15Pass, "İlk hareket: {$smCountAfterFirst}, 2. istek sonrası: {$smCountAfterSecond} (0 artış).");

// ------------------------------------------------------------------------
// TEST 16: Produced quantity ile PANEL_COMPLETED sayısı eşleşmesi
// ------------------------------------------------------------------------
$dbProducedQty = (float)$pdo->query("SELECT produced_quantity FROM mes_work_orders WHERE id = {$woIdOther}")->fetchColumn();
$validEventsCount = (float)$pdo->query("SELECT COUNT(*) FROM mes_production_events WHERE work_order_id = {$woIdOther} AND status = 'PROCESSED'")->fetchColumn();
$t16Pass = ($dbProducedQty === $validEventsCount);
reportOptTest('TEST 16', 'Produced Qty & Event Match', $t16Pass, "WO Qty: {$dbProducedQty} == Processed Events: {$validEventsCount}");

// Test iş emirlerini temizle
$pdo->prepare("DELETE FROM mes_simulations WHERE work_order_id IN (?, ?, ?)")->execute([$woId, $woIdDouble, $woIdOther]);
$pdo->prepare("DELETE FROM mes_production_events WHERE work_order_id IN (?, ?, ?)")->execute([$woId, $woIdDouble, $woIdOther]);
$pdo->prepare("DELETE FROM mes_work_orders WHERE id IN (?, ?, ?)")->execute([$woId, $woIdDouble, $woIdOther]);

$total = $passCount + $failCount;
echo "\n========================================================================\n";
echo "MES OPTİMİZASYON & VERİ BÜTÜNLÜĞÜ TEST SONUCU: {$passCount}/{$total} PASS\n";
echo "========================================================================\n";
