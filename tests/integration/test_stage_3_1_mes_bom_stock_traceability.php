<?php

date_default_timezone_set('Europe/Istanbul');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role_id'] = 1;
$_SESSION['permissions'] = ['*'];

require_once 'c:/wamp64/www/stok-takip/config/database.php';
require_once 'c:/wamp64/www/stok-takip/app/Router.php';
require_once 'c:/wamp64/www/stok-takip/routes/web.php';
require_once 'c:/wamp64/www/stok-takip/app/Models/Mes.php';
require_once 'c:/wamp64/www/stok-takip/app/Services/MesSimulationService.php';
require_once 'c:/wamp64/www/stok-takip/app/Services/MesProductionIntegrationService.php';
require_once 'c:/wamp64/www/stok-takip/app/Services/MesBomConsumptionService.php';
require_once 'c:/wamp64/www/stok-takip/app/Services/MesEventValidationService.php';

$pdo = (new Database())->connect();
$GLOBALS['pdo'] = $pdo;

function dispatchHttp(string $method, string $uri, array $postData = []): array {
    global $routes;
    
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = '/stok-takip/public' . $uri;
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['role_id'] = 1;
    $_SESSION['permissions'] = ['*'];

    $_GET = [];
    $_POST = [];

    $parts = parse_url($uri);
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $_GET);
    }
    if ($method === 'POST') {
        $_POST = $postData;
    }

    $router = new Router($routes);

    ob_start();
    $code = 200;
    try {
        $router->dispatch('/stok-takip/public' . $uri);
    } catch (Throwable $e) {
        $code = 500;
        echo "Exception: " . $e->getMessage();
    }
    $body = ob_get_clean();

    return ['status' => $code, 'body' => $body];
}

echo "========================================================================\n";
echo "AŞAMA 3.1 — MES → BOM → STOK TÜKETİM VE İZLENEBİLİRLİK DOĞRULAMA TESTİ\n";
echo "========================================================================\n";

$mesModel = new Mes($pdo);
$simService = new MesSimulationService($pdo);
$integrationService = new MesProductionIntegrationService($pdo);
$bomService = new MesBomConsumptionService($pdo);
$validationService = new MesEventValidationService($pdo);

$recipeId = (int)$pdo->query("SELECT id FROM recipes WHERE is_active = 1 LIMIT 1")->fetchColumn();
$outputMatId = (int)$pdo->query("SELECT output_material_id FROM recipes WHERE id = {$recipeId}")->fetchColumn();
$lineId = (int)$pdo->query("SELECT id FROM production_lines WHERE is_active = 1 ORDER BY id ASC LIMIT 1")->fetchColumn();

// Clean up line state
$pdo->exec("UPDATE production_lines SET status = 'IDLE', status_note = NULL WHERE id = {$lineId}");
$pdo->exec("UPDATE mes_simulations SET is_active = 0 WHERE work_order_id IN (SELECT id FROM mes_work_orders WHERE production_line_id = {$lineId})");
$pdo->exec("UPDATE mes_work_orders SET status = 'PAUSED' WHERE status = 'RUNNING' AND production_line_id = {$lineId}");

// Ensure raw materials have sufficient stock in location 1
$rawItems = $pdo->query("SELECT material_id FROM recipe_items WHERE recipe_id = {$recipeId}")->fetchAll(PDO::FETCH_COLUMN);
foreach ($rawItems as $mId) {
    $balExists = $pdo->query("SELECT COUNT(*) FROM stock_balances WHERE material_id = {$mId} AND location_id = 1")->fetchColumn();
    if ($balExists) {
        $pdo->exec("UPDATE stock_balances SET quantity = 50000.0 WHERE material_id = {$mId} AND location_id = 1");
    } else {
        $pdo->exec("INSERT INTO stock_balances (material_id, location_id, quantity, updated_at) VALUES ({$mId}, 1, 50000.0, NOW())");
    }
}

// ------------------------------------------------------------------------
// TEST A: 1 Panel Üretimi ve BOM Kalem Tüketim Doğrulaması
// ------------------------------------------------------------------------
$woNoA = 'WO-31A-' . strtoupper(bin2hex(random_bytes(3)));
$woIdA = $mesModel->createWorkOrder([
    'work_order_no'       => $woNoA,
    'product_material_id' => $outputMatId,
    'recipe_id'           => $recipeId,
    'production_line_id'  => $lineId,
    'planned_quantity'    => 10.0,
    'status'              => 'READY'
]);

// Capture starting balances
$startBalances = [];
foreach ($rawItems as $mId) {
    $startBalances[$mId] = (float)$pdo->query("SELECT quantity FROM stock_balances WHERE material_id = {$mId} AND location_id = 1")->fetchColumn();
}

$simResA = $simService->processTick($woIdA, true);
$ok1PanelProd = ($simResA['success'] === true && $simResA['status'] === 'PROCESSED');

// Check actual balances after 1 panel
$bomA = $bomService->getWorkOrderBomConsumption($woIdA);
$allDeductionsCorrect = true;
foreach ($bomA['bom_items'] as $bIt) {
    $mId = $bIt['material_id'];
    $currentBal = (float)$pdo->query("SELECT quantity FROM stock_balances WHERE material_id = {$mId} AND location_id = 1")->fetchColumn();
    $expectedDeduction = $bIt['effective_unit_qty'];
    $actualDiff = round($startBalances[$mId] - $currentBal, 4);
    if (abs($actualDiff - $expectedDeduction) > 0.001) {
        $allDeductionsCorrect = false;
    }
}

$okTestA = ($ok1PanelProd && $allDeductionsCorrect);
echo "  [" . ($okTestA ? 'PASS' : 'FAIL') . "] 1. Test A (1 Panel Üretim): Tüm BOM kalemleri reçeteye göre tam düşüldü\n";

// ------------------------------------------------------------------------
// TEST B: 10 Panel Üretimi ve Kümülatif 10x Tüketim Doğrulaması
// ------------------------------------------------------------------------
$woNoB = 'WO-31B-' . strtoupper(bin2hex(random_bytes(3)));
$woIdB = $mesModel->createWorkOrder([
    'work_order_no'       => $woNoB,
    'product_material_id' => $outputMatId,
    'recipe_id'           => $recipeId,
    'production_line_id'  => $lineId,
    'planned_quantity'    => 10.0,
    'status'              => 'READY'
]);

for ($p = 1; $p <= 10; $p++) {
    $simService->processTick($woIdB, true);
}

$bomB = $bomService->getWorkOrderBomConsumption($woIdB);
$woB = $mesModel->getWorkOrderById($woIdB);
$all10xDeductionsCorrect = true;
foreach ($bomB['bom_items'] as $bIt) {
    $expected10x = round($bIt['effective_unit_qty'] * 10, 4);
    $actualConsumed = round($bIt['actual_consumed'], 4);
    if (abs($actualConsumed - $expected10x) > 0.01) {
        $all10xDeductionsCorrect = false;
    }
}

$okTestB = ($all10xDeductionsCorrect && (float)$woB['produced_quantity'] == 10.0 && $woB['status'] === 'COMPLETED');
echo "  [" . ($okTestB ? 'PASS' : 'FAIL') . "] 2. Test B (10 Panel Üretim): Kümülatif BOM tüketimi 10x ile birebir eşleşti (Durum: COMPLETED)\n";

// ------------------------------------------------------------------------
// TEST C: Event -> Panel Seri No -> BOM Tüketimi -> Stok Referansı İzleme
// ------------------------------------------------------------------------
$eventA = $pdo->query("SELECT * FROM mes_production_events WHERE work_order_id = {$woIdA} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$eventDetails = $bomService->getEventBomConsumption($eventA['event_id']);

$hasEventSerials = !empty($eventDetails['serial_numbers']) && count($eventDetails['serial_numbers']) > 0;
$hasEventItems = !empty($eventDetails['consumed_items']) && count($eventDetails['consumed_items']) > 0;
$hasStockRef = !empty($eventDetails['stock_movement_ref']) && str_starts_with($eventDetails['stock_movement_ref'], 'PRD-');

$okTestC = ($hasEventSerials && $hasEventItems && $hasStockRef && $eventDetails['work_order_no'] === $woNoA);
echo "  [" . ($okTestC ? 'PASS' : 'FAIL') . "] 3. Test C (Event İzlenebilirlik): Event -> Panel Seri ({$eventDetails['primary_serial_no']}) -> BOM Tüketimi -> Ref ({$eventDetails['stock_movement_ref']}) bağlandı\n";

// ------------------------------------------------------------------------
// TEST D: Panel Seri Numarası Üzerinden Geriye Doğru İzlenebilirlik
// ------------------------------------------------------------------------
$sampleSerial = $eventDetails['primary_serial_no'];
$panelRow = $pdo->query("SELECT * FROM panel_units WHERE serial_no = '{$sampleSerial}' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$traceStockMovements = $pdo->query("SELECT * FROM stock_movements WHERE reference_no = '{$panelRow['stock_movement_ref']}'")->fetchAll(PDO::FETCH_ASSOC);

$hasOutMovs = false;
$hasInMov = false;
foreach ($traceStockMovements as $sm) {
    if ($sm['movement_type'] === 'OUT') $hasOutMovs = true;
    if ($sm['movement_type'] === 'IN') $hasInMov = true;
}

$okTestD = ($panelRow && (int)$panelRow['work_order_id'] === $woIdA && $hasOutMovs && $hasInMov);
echo "  [" . ($okTestD ? 'PASS' : 'FAIL') . "] 4. Test D (Seri No -> Stok Hareketleri): {$sampleSerial} için IN ve OUT hareketleri doğrulandı\n";

// ------------------------------------------------------------------------
// TEST E: Tek Malzeme Yetersizliği ve Atomik Transaction Rollback
// ------------------------------------------------------------------------
$firstMatId = $rawItems[0];
$pdo->exec("UPDATE stock_balances SET quantity = 0.0 WHERE material_id = {$firstMatId} AND location_id = 1");

$woNoE = 'WO-31E-' . strtoupper(bin2hex(random_bytes(3)));
$woIdE = $mesModel->createWorkOrder([
    'work_order_no'       => $woNoE,
    'product_material_id' => $outputMatId,
    'recipe_id'           => $recipeId,
    'production_line_id'  => $lineId,
    'planned_quantity'    => 5.0,
    'status'              => 'READY'
]);

$initialStockMovementsCount = (int)$pdo->query("SELECT COUNT(*) FROM stock_movements")->fetchColumn();
$initialPanelUnitsCount = (int)$pdo->query("SELECT COUNT(*) FROM panel_units")->fetchColumn();

$simResE = $simService->processTick($woIdE, true);
$woEAfter = $mesModel->getWorkOrderById($woIdE);

$finalStockMovementsCount = (int)$pdo->query("SELECT COUNT(*) FROM stock_movements")->fetchColumn();
$finalPanelUnitsCount = (int)$pdo->query("SELECT COUNT(*) FROM panel_units")->fetchColumn();

$okRollback = ($simResE['success'] === false && 
               (float)$woEAfter['produced_quantity'] == 0.0 && 
               $initialStockMovementsCount === $finalStockMovementsCount && 
               $initialPanelUnitsCount === $finalPanelUnitsCount);
echo "  [" . ($okRollback ? 'PASS' : 'FAIL') . "] 5. Test E (Tek Malzeme Yetersiz & Rollback): Üretim durduruldu, 0 stok hareketi yazıldı, rollback korundu\n";

// ------------------------------------------------------------------------
// TEST F: Çoklu Yetersiz Malzeme Raporlaması
// ------------------------------------------------------------------------
foreach ($rawItems as $mId) {
    $pdo->exec("UPDATE stock_balances SET quantity = 0.0 WHERE material_id = {$mId} AND location_id = 1");
}

$woNoF = 'WO-31F-' . strtoupper(bin2hex(random_bytes(3)));
$woIdF = $mesModel->createWorkOrder([
    'work_order_no'       => $woNoF,
    'product_material_id' => $outputMatId,
    'recipe_id'           => $recipeId,
    'production_line_id'  => $lineId,
    'planned_quantity'    => 5.0,
    'status'              => 'READY'
]);

$valResF = $validationService->validateEvent([
    'work_order_id'       => $woIdF,
    'product_material_id' => $outputMatId,
    'production_line_id'  => $lineId,
    'quantity'            => 1.0,
    'source'              => 'SIMULATOR'
]);

$reportedMissingCount = count($valResF['missing_items'] ?? []);
$okMultiMissing = ($valResF['valid'] === false && 
                   $valResF['code'] === 'INSUFFICIENT_STOCK' && 
                   $reportedMissingCount === count($rawItems));
echo "  [" . ($okMultiMissing ? 'PASS' : 'FAIL') . "] 6. Test F (Çoklu Malzeme Raporlama): {$reportedMissingCount}/" . count($rawItems) . " yetersiz malzemenin tamamı ve eksik miktarları raporlandı\n";

// Restore stocks
foreach ($rawItems as $mId) {
    $pdo->exec("UPDATE stock_balances SET quantity = 50000.0 WHERE material_id = {$mId} AND location_id = 1");
}

// ------------------------------------------------------------------------
// TEST G: Duplicate Event ile 2. Kez Stok Düşümünün Engellenmesi
// ------------------------------------------------------------------------
$woNoG = 'WO-31G-' . strtoupper(bin2hex(random_bytes(3)));
$woIdG = $mesModel->createWorkOrder([
    'work_order_no'       => $woNoG,
    'product_material_id' => $outputMatId,
    'recipe_id'           => $recipeId,
    'production_line_id'  => $lineId,
    'planned_quantity'    => 5.0,
    'status'              => 'READY'
]);

$evtDataG = [
    'event_id'            => 'EVT-TEST-DUP-' . bin2hex(random_bytes(3)),
    'work_order_id'       => $woIdG,
    'product_material_id' => $outputMatId,
    'production_line_id'  => $lineId,
    'quantity'            => 1.0,
    'source'              => 'PLC'
];
$createEvtRes1 = $mesModel->createProductionEvent($evtDataG);
$processEvtRes1 = $integrationService->processEvent($evtDataG['event_id']);

$stockMovCountBeforeDup = (int)$pdo->query("SELECT COUNT(*) FROM stock_movements WHERE reference_no = '{$processEvtRes1['reference_no']}'")->fetchColumn();

$createEvtRes2 = $mesModel->createProductionEvent($evtDataG);
$processEvtRes2 = $integrationService->processEvent($evtDataG['event_id']);

$stockMovCountAfterDup = (int)$pdo->query("SELECT COUNT(*) FROM stock_movements WHERE reference_no = '{$processEvtRes1['reference_no']}'")->fetchColumn();

$okIdempotentStock = ($createEvtRes2['status'] === 'duplicate' && 
                      $processEvtRes2['status'] === 'already_processed' && 
                      $stockMovCountBeforeDup === $stockMovCountAfterDup);
echo "  [" . ($okIdempotentStock ? 'PASS' : 'FAIL') . "] 7. Test G (Duplicate Event): Mükerrer eventte 2. kez stok düşümü engellendi\n";

// ------------------------------------------------------------------------
// TEST H: COMPLETED İş Emrinde Ekstra Tüketim Engeli
// ------------------------------------------------------------------------
$extraTickRes = $simService->processTick($woIdB, true);
$woBAfterExtra = $mesModel->getWorkOrderById($woIdB);
$okCompletedGuard = ($extraTickRes['status'] === 'COMPLETED' && $extraTickRes['action'] === 'STOPPED' && (float)$woBAfterExtra['produced_quantity'] == 10.0);
echo "  [" . ($okCompletedGuard ? 'PASS' : 'FAIL') . "] 8. Test H (COMPLETED Ekstra Tüketim Engeli): 10/10 tamamlanmış iş emrinde 11. panel üretimi engellendi\n";

// ------------------------------------------------------------------------
// TEST I: API Endpoint Doğrulaması (/api/mes/work-order/consumption & /api/mes/event/consumption)
// ------------------------------------------------------------------------
$apiRes1 = dispatchHttp('GET', '/api/mes/work-order/consumption?id=' . $woIdA);
$apiData1 = json_decode($apiRes1['body'], true);
$okApi1 = ($apiRes1['status'] === 200 && !empty($apiData1['bom_items']) && count($apiData1['bom_items']) > 0);

$apiRes2 = dispatchHttp('GET', '/api/mes/event/consumption?event_id=' . $eventA['event_id']);
$apiData2 = json_decode($apiRes2['body'], true);
$okApi2 = ($apiRes2['status'] === 200 && !empty($apiData2['consumed_items']));

$okApis = ($okApi1 && $okApi2);
echo "  [" . ($okApis ? 'PASS' : 'FAIL') . "] 9. Test I (API Endpointleri): /api/mes/work-order/consumption ve /api/mes/event/consumption HTTP 200 JSON döndü\n";

// ------------------------------------------------------------------------
// TEST J: show.php Render Doğrulaması (0 Warning, 0 Undefined, BOM Tablosu Mevcut)
// ------------------------------------------------------------------------
$showRes = dispatchHttp('GET', '/mes/work-orders/show?id=' . $woIdA);
$showBody = $showRes['body'];

$hasWarning = (stripos($showBody, 'Warning:') !== false || stripos($showBody, 'Notice:') !== false || stripos($showBody, 'Undefined variable') !== false);
$hasBomTable = (stripos($showBody, 'BOM &amp; Malzeme Tüketimi') !== false || stripos($showBody, 'bom-consumption-tbody') !== false);
$hasModal = (stripos($showBody, 'event-detail-modal') !== false);

$okShow = (!$hasWarning && $hasBomTable && $hasModal);
echo "  [" . ($okShow ? 'PASS' : 'FAIL') . "] 10. Test J (show.php Render): 0 Warning / 0 Notice / BOM Tablosu & Tüketim Modalı aktif\n";

// Temizlik
$allTestWoIds = "{$woIdA}, {$woIdB}, {$woIdE}, {$woIdF}, {$woIdG}";
$pdo->exec("DELETE FROM mes_event_logs WHERE event_id IN (SELECT event_id FROM mes_production_events WHERE work_order_id IN ({$allTestWoIds}))");
$pdo->exec("DELETE FROM panel_units WHERE work_order_id IN ({$allTestWoIds})");
$pdo->exec("DELETE FROM stock_movements WHERE reference_no IN (SELECT DISTINCT stock_movement_ref FROM mes_production_events WHERE work_order_id IN ({$allTestWoIds}))");
$pdo->exec("DELETE FROM mes_production_events WHERE work_order_id IN ({$allTestWoIds})");
$pdo->exec("DELETE FROM mes_simulations WHERE work_order_id IN ({$allTestWoIds})");
$pdo->exec("DELETE FROM mes_work_orders WHERE id IN ({$allTestWoIds})");
echo "  [PASS] Test verileri temizlendi.\n";

echo "========================================================================\n";
if ($okTestA && $okTestB && $okTestC && $okTestD && $okRollback && $okMultiMissing && $okIdempotentStock && $okCompletedGuard && $okApis && $okShow) {
    echo ">>> AŞAMA 3.1: MES → BOM → STOK TÜKETİM VE İZLENEBİLİRLİK %100 BAŞARILI! <<<\n";
} else {
    echo ">>> TESTTE HATALAR TESPİT EDİLDİ! <<<\n";
}
echo "========================================================================\n";
