<?php

require_once 'c:/wamp64/www/stok-takip/config/database.php';
require_once 'c:/wamp64/www/stok-takip/app/Models/Mes.php';
require_once 'c:/wamp64/www/stok-takip/app/Models/Recipe.php';
require_once 'c:/wamp64/www/stok-takip/app/Models/Material.php';
require_once 'c:/wamp64/www/stok-takip/app/Services/ProductionStockService.php';
require_once 'c:/wamp64/www/stok-takip/app/Services/MesProductionIntegrationService.php';
require_once 'c:/wamp64/www/stok-takip/app/Services/MesEventIngestionService.php';
require_once 'c:/wamp64/www/stok-takip/app/Services/MesSimulationService.php';
require_once 'c:/wamp64/www/stok-takip/app/Services/MesBomConsumptionService.php';
require_once 'c:/wamp64/www/stok-takip/app/Services/MesCostService.php';

$pdo = (new Database())->connect();

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $title, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$title}\n";
        if ($details) echo "         {$details}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$title}\n";
        if ($details) echo "         Details: {$details}\n";
    }
}

echo "========================================================================\n";
echo "AŞAMA 3.2 — ÜRETİM MALİYETİ & SNAPSHOT KAPSAMLI TEST PROTOKOLÜ\n";
echo "========================================================================\n\n";

// Hazırlık: Hammadde stoklarını bol miktarda güncelle
$pdo->exec("UPDATE stock_balances SET quantity = 100000 WHERE location_id = 1");

$mesModel = new Mes($pdo);
$costService = new MesCostService($pdo);
$bomService = new MesBomConsumptionService($pdo);
$stockService = new ProductionStockService($pdo);
$integrationService = new MesProductionIntegrationService($pdo);
$ingestionService = new MesEventIngestionService($pdo);
$simService = new MesSimulationService($pdo);

$activeRecipe = $pdo->query("SELECT id, output_material_id FROM recipes WHERE code = 'BOM-SP-550W-MONO' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$recipeId = (int)($activeRecipe['id'] ?? 3);
$outputMatId = (int)($activeRecipe['output_material_id'] ?? 25);

// -------------------------------------------------------------------------
// TEST 1: BOM Teorik Birim Panel Maliyeti Hesaplama
// -------------------------------------------------------------------------
echo "TEST 1: Reçete Teorik Birim Üretim Maliyeti & Kalem Dağılımı\n";
$recipeCost = $costService->getRecipeTheoreticalCost($recipeId);
assertTest($recipeCost['success'] === true, 'Reçete maliyeti başarıyla hesaplandı');
assertTest($recipeCost['theoretical_unit_cost'] > 0, "Birim Panel Maliyeti: {$recipeCost['formatted_unit_cost']}", "Kalem sayısı: {$recipeCost['items_count']}");

$itemsSum = 0.0;
$pctSum = 0.0;
foreach ($recipeCost['items'] as $it) {
    $itemsSum += (float)$it['item_cost'];
    $pctSum += (float)$it['cost_share_pct'];
    assertTest($it['unit_price'] > 0, "BOM Kalemi {$it['material_code']}: {$it['formatted_unit_price']} -> Kalem Maliyeti: {$it['formatted_item_cost']} (%{$it['cost_share_pct']})");
}
assertTest(abs($itemsSum - $recipeCost['theoretical_unit_cost']) < 0.01, "Kalemlerin toplamı ({$itemsSum} TL) teorik birim maliyete ({$recipeCost['theoretical_unit_cost']} TL) eşit.");
assertTest(abs($pctSum - 100.0) < 0.5, "Maliyet payları toplamı %100 (%{$pctSum}).");
echo "\n";

// -------------------------------------------------------------------------
// TEST 2: Tek Panel Üretimi, Stok & Maliyet Snapshot Kaydı
// -------------------------------------------------------------------------
echo "TEST 2: Tek Panel Üretimi ve Snapshot Kayıtlarının Doğrulanması\n";
$testWoNo = 'WO-COST-TEST-' . strtoupper(substr(uniqid(), -4));
$stmtWo = $pdo->prepare("
    INSERT INTO mes_work_orders 
        (work_order_no, product_material_id, recipe_id, production_line_id, planned_quantity, produced_quantity, status, created_at, updated_at)
    VALUES 
        (?, ?, ?, 1, 10, 0, 'RUNNING', NOW(), NOW())
");
$stmtWo->execute([$testWoNo, $outputMatId, $recipeId]);
$testWoId = (int)$pdo->lastInsertId();

$eventId1 = 'MES-COST-EVT-01-' . uniqid();
$payload1 = [
    'event_id'            => $eventId1,
    'event_type'          => 'PANEL_COMPLETED',
    'product_code'        => 'SOL-MOD-550W',
    'product_material_id' => $outputMatId,
    'quantity'            => 1,
    'production_line'     => 'LAM-LINE-1',
    'production_line_id'  => 1,
    'work_order_no'       => $testWoNo,
    'work_order_id'       => $testWoId,
    'event_time'          => date('Y-m-d H:i:s'),
    'source'              => 'TEST_RUNNER'
];

$ingestRes1 = $ingestionService->ingestEvent($payload1);
assertTest($ingestRes1['success'] === true, '1 panel üretim olayı başarıyla işlendi');
assertTest($ingestRes1['unit_cost'] > 0, "Dönen birim maliyet: {$ingestRes1['unit_cost']} TL");
assertTest($ingestRes1['total_cost'] > 0, "Dönen toplam maliyet: {$ingestRes1['total_cost']} TL");

// Veritabanı snapshot alanlarını kontrol et
$stmtSmOut = $pdo->prepare("SELECT COUNT(*) FROM stock_movements WHERE reference_no = ? AND movement_type = 'OUT' AND unit_price > 0 AND total_price > 0");
$stmtSmOut->execute([$ingestRes1['stock_reference']]);
$smOutCount = (int)$stmtSmOut->fetchColumn();
assertTest($smOutCount > 0, "stock_movements (OUT) hammadde çıkışlarında {$smOutCount} kaleme birim ve toplam maliyet snapshot kaydedildi.");

$stmtSmIn = $pdo->prepare("SELECT unit_price, total_price, currency FROM stock_movements WHERE reference_no = ? AND movement_type = 'IN' LIMIT 1");
$stmtSmIn->execute([$ingestRes1['stock_reference']]);
$smInRow = $stmtSmIn->fetch(PDO::FETCH_ASSOC);
assertTest((float)$smInRow['total_price'] > 0, "stock_movements (IN) mamul girişinde snapshot toplam maliyet: {$smInRow['total_price']} {$smInRow['currency']}");

$stmtPanel = $pdo->prepare("SELECT serial_no, unit_cost, currency FROM panel_units WHERE production_event_id = ? LIMIT 1");
$stmtPanel->execute([$eventId1]);
$panelRow = $stmtPanel->fetch(PDO::FETCH_ASSOC);
assertTest((float)$panelRow['unit_cost'] > 0, "panel_units tablosuna seri no ({$panelRow['serial_no']}) ile birim maliyet snapshot ({$panelRow['unit_cost']} {$panelRow['currency']}) kaydedildi.");

$stmtEvt = $pdo->prepare("SELECT unit_cost, total_cost, cost_currency FROM mes_production_events WHERE event_id = ?");
$stmtEvt->execute([$eventId1]);
$evtRow = $stmtEvt->fetch(PDO::FETCH_ASSOC);
assertTest((float)$evtRow['total_cost'] > 0, "mes_production_events tablosuna unit_cost ({$evtRow['unit_cost']}) ve total_cost ({$evtRow['total_cost']}) kaydedildi.");
echo "\n";

// -------------------------------------------------------------------------
// TEST 3: BOM Kalemlerinin Toplamı ile Panel Maliyeti Tutarlılığı
// -------------------------------------------------------------------------
echo "TEST 3: BOM Kalem Maliyetleri Toplamı == Panel Maliyeti Doğrulaması\n";
$stmtSumOut = $pdo->prepare("SELECT SUM(total_price) FROM stock_movements WHERE reference_no = ? AND movement_type = 'OUT'");
$stmtSumOut->execute([$ingestRes1['stock_reference']]);
$totalOutCost = (float)$stmtSumOut->fetchColumn();

assertTest(abs($totalOutCost - (float)$smInRow['total_price']) < 0.01, "Hammadde çıkış toplamı ({$totalOutCost} TL) == Mamul giriş maliyeti ({$smInRow['total_price']} TL)");
assertTest(abs($totalOutCost - (float)$panelRow['unit_cost']) < 0.01, "Hammadde çıkış toplamı ({$totalOutCost} TL) == Panel seri maliyeti ({$panelRow['unit_cost']} TL)");
assertTest(abs($totalOutCost - (float)$evtRow['total_cost']) < 0.01, "Hammadde çıkış toplamı ({$totalOutCost} TL) == Event toplam maliyeti ({$evtRow['total_cost']} TL)");
echo "\n";

// -------------------------------------------------------------------------
// TEST 4: Fiyat Değişimi ve Tarihsel Snapshot Maliyet Dokunulmazlığı
// -------------------------------------------------------------------------
echo "TEST 4: Fiyat Değişikliği ve Tarihsel Snapshot Dokunulmazlığı (Immutability)\n";
// Panel 1'in snapshot maliyetini dondurup kaydet
$oldPanelCost = (float)$panelRow['unit_cost'];
$oldEventCost = (float)$evtRow['total_cost'];
$oldMovementCost = (float)$smInRow['total_price'];

// Malzeme fiyatını değiştir (Örn: Solar Hücre 12 TL -> 25 TL)
$pdo->exec("UPDATE materials SET unit_price = 25.0000 WHERE code = 'SOL-CELL-001'");

// Panel 2'yi üret
$eventId2 = 'MES-COST-EVT-02-' . uniqid();
$payload2 = [
    'event_id'            => $eventId2,
    'event_type'          => 'PANEL_COMPLETED',
    'product_code'        => 'SOL-MOD-550W',
    'product_material_id' => $outputMatId,
    'quantity'            => 1,
    'production_line'     => 'LAM-LINE-1',
    'production_line_id'  => 1,
    'work_order_no'       => $testWoNo,
    'work_order_id'       => $testWoId,
    'event_time'          => date('Y-m-d H:i:s'),
    'source'              => 'TEST_RUNNER'
];
$ingestRes2 = $ingestionService->ingestEvent($payload2);

// Panel 2'nin maliyetini al
$stmtPanel2 = $pdo->prepare("SELECT unit_cost FROM panel_units WHERE production_event_id = ? LIMIT 1");
$stmtPanel2->execute([$eventId2]);
$newPanelCost = (float)$stmtPanel2->fetchColumn();

assertTest($newPanelCost > $oldPanelCost, "Yeni üretilen Panel #2 yeni fiyatla hesaplandı: Eski: {$oldPanelCost} TL -> Yeni: {$newPanelCost} TL");

// KRİTİK: Panel 1'in geçmiş kayıtlarının DEĞİŞMEDİĞİNİ doğrula
$stmtCheckOldPanel = $pdo->prepare("SELECT unit_cost FROM panel_units WHERE production_event_id = ? LIMIT 1");
$stmtCheckOldPanel->execute([$eventId1]);
$stillOldPanelCost = (float)$stmtCheckOldPanel->fetchColumn();

$stmtCheckOldEvt = $pdo->prepare("SELECT total_cost FROM mes_production_events WHERE event_id = ?");
$stmtCheckOldEvt->execute([$eventId1]);
$stillOldEvtCost = (float)$stmtCheckOldEvt->fetchColumn();

$stmtCheckOldMov = $pdo->prepare("SELECT total_price FROM stock_movements WHERE reference_no = ? AND movement_type = 'IN' LIMIT 1");
$stmtCheckOldMov->execute([$ingestRes1['stock_reference']]);
$stillOldMovCost = (float)$stmtCheckOldMov->fetchColumn();

assertTest(abs($stillOldPanelCost - $oldPanelCost) < 0.0001, "Panel #1 snapshot unit_cost DEĞİŞMEDİ (Hâlâ {$stillOldPanelCost} TL)");
assertTest(abs($stillOldEvtCost - $oldEventCost) < 0.0001, "Event #1 snapshot total_cost DEĞİŞMEDİ (Hâlâ {$stillOldEvtCost} TL)");
assertTest(abs($stillOldMovCost - $oldMovementCost) < 0.0001, "Stock Movement #1 snapshot total_price DEĞİŞMEDİ (Hâlâ {$stillOldMovCost} TL)");

// Fiyatı geri al
$pdo->exec("UPDATE materials SET unit_price = 12.0000 WHERE code = 'SOL-CELL-001'");
echo "\n";

// -------------------------------------------------------------------------
// TEST 5: Yetersiz Stok Durumunda Rollback & Maliyet Kaydı Oluşmaması
// -------------------------------------------------------------------------
echo "TEST 5: Yetersiz Stok Durumu, Atomik Rollback & Maliyet Kaydı Oluşmaması\n";
// Solar cam stoğunu 0 yap
$pdo->exec("UPDATE stock_balances SET quantity = 0 WHERE material_id = 3 AND location_id = 1");

$eventIdFail = 'MES-COST-EVT-FAIL-' . uniqid();
$payloadFail = [
    'event_id'            => $eventIdFail,
    'event_type'          => 'PANEL_COMPLETED',
    'product_code'        => 'SOL-MOD-550W',
    'product_material_id' => $outputMatId,
    'quantity'            => 1,
    'production_line'     => 'LAM-LINE-1',
    'production_line_id'  => 1,
    'work_order_no'       => $testWoNo,
    'work_order_id'       => $testWoId,
    'event_time'          => date('Y-m-d H:i:s'),
    'source'              => 'TEST_RUNNER'
];

$ingestFail = $ingestionService->ingestEvent($payloadFail);
assertTest($ingestFail['success'] === false, 'Yetersiz stokta üretim olayı reddedildi (failed)');

$stmtCheckFailUnit = $pdo->prepare("SELECT COUNT(*) FROM panel_units WHERE production_event_id = ?");
$stmtCheckFailUnit->execute([$eventIdFail]);
assertTest((int)$stmtCheckFailUnit->fetchColumn() === 0, 'Rollback: Yetersiz stokta panel_units kaydı oluşturulmadı.');

$stmtCheckFailMov = $pdo->prepare("SELECT COUNT(*) FROM stock_movements WHERE description LIKE ?");
$stmtCheckFailMov->execute(['%' . $eventIdFail . '%']);
assertTest((int)$stmtCheckFailMov->fetchColumn() === 0, 'Rollback: Yetersiz stokta stock_movements kaydı oluşturulmadı.');

// Stoğu geri yükle
$pdo->exec("UPDATE stock_balances SET quantity = 100000 WHERE material_id = 3 AND location_id = 1");
echo "\n";

// -------------------------------------------------------------------------
// TEST 6: Mükerrer (Duplicate) Event Güvenliği
// -------------------------------------------------------------------------
echo "TEST 6: Mükerrer (Duplicate) Event Çağrısı ve Maliyet Koruması\n";
$dupRes = $ingestionService->ingestEvent($payload1); // Daha önce başarıyla işlenmiş payload1
assertTest($dupRes['success'] === true && $dupRes['status'] === 'ALREADY_PROCESSED', 'Duplicate event başarıyla yakalandı (ALREADY_PROCESSED)');

$stmtCountEvt = $pdo->prepare("SELECT COUNT(*) FROM mes_production_events WHERE event_id = ?");
$stmtCountEvt->execute([$eventId1]);
assertTest((int)$stmtCountEvt->fetchColumn() === 1, 'Mükerrer event sonucu ekstra mes_production_events kaydı OLUŞTURULMADI.');

$stmtCountUnits = $pdo->prepare("SELECT COUNT(*) FROM panel_units WHERE production_event_id = ?");
$stmtCountUnits->execute([$eventId1]);
assertTest((int)$stmtCountUnits->fetchColumn() === 1, 'Mükerrer event sonucu ekstra panel_units kaydı OLUŞTURULMADI.');
echo "\n";

// -------------------------------------------------------------------------
// TEST 7: İş Emri Maliyet Özeti & Kalan Bütçe Metrikleri
// -------------------------------------------------------------------------
echo "TEST 7: İş Emri Finansal KPI & Maliyet Özeti (MesCostService)\n";
$woCostSummary = $costService->getWorkOrderCostSummary($testWoId);
assertTest($woCostSummary['success'] === true, 'İş emri maliyet özeti alındı');
assertTest($woCostSummary['unit_cost'] > 0, "Teorik Birim Maliyet: {$woCostSummary['formatted_unit_cost']}");
assertTest($woCostSummary['realized_cost'] > 0, "Gerçekleşen Maliyet (2 Panel): {$woCostSummary['formatted_realized_cost']}");
assertTest($woCostSummary['estimated_total_cost'] > $woCostSummary['realized_cost'], "Tahmini Toplam (10 Panel): {$woCostSummary['formatted_estimated_total_cost']}");
assertTest($woCostSummary['remaining_estimated_cost'] > 0, "Kalan Tahmini Bütçe (8 Panel): {$woCostSummary['formatted_remaining_cost']}");
assertTest(!empty($woCostSummary['itemized_cost']), "Kalem bazlı maliyet detayları mevcut (" . count($woCostSummary['itemized_cost']) . " kalem)");
echo "\n";

// -------------------------------------------------------------------------
// TEST 8: MES Traceability & Event Cost Details API
// -------------------------------------------------------------------------
echo "TEST 8: MES İzlenebilirlik ve Event Maliyet Detayları\n";
$evtCostDetails = $costService->getEventCostDetails($eventId1);
assertTest($evtCostDetails['success'] === true, 'Event maliyet detayı alındı');
assertTest($evtCostDetails['total_cost'] > 0, "Event toplam snapshot maliyeti: {$evtCostDetails['formatted_total_cost']}");
assertTest(!empty($evtCostDetails['consumed_items']), "Event bazlı tüketilen hammadde maliyetleri mevcut (" . count($evtCostDetails['consumed_items']) . " kalem)");
echo "\n";

// -------------------------------------------------------------------------
// TEST 9: Simülatör Telemetri Entegrasyonu & cost_summary
// -------------------------------------------------------------------------
echo "TEST 9: MES Simulator Telemetri & cost_summary Doğrulaması\n";
$simStatus = $simService->getStatus($testWoId);
assertTest(isset($simStatus['bom_consumption']), 'Telemetride bom_consumption mevcut');
assertTest(isset($simStatus['bom_consumption']['cost_summary']), 'Telemetride bom_consumption.cost_summary mevcut');
assertTest($simStatus['bom_consumption']['cost_summary']['unit_cost'] > 0, "Telemetri Birim Panel Maliyeti: {$simStatus['bom_consumption']['cost_summary']['formatted_unit_cost']}");
echo "\n";

// -------------------------------------------------------------------------
// SONUÇ ÖZETİ
// -------------------------------------------------------------------------
echo "========================================================================\n";
echo sprintf("AŞAMA 3.2 TEST SONUCU: %d PASS, %d FAIL\n", $passed, $failed);
echo "========================================================================\n";

if ($failed > 0) {
    exit(1);
}
