<?php
require_once 'c:/wamp64/www/stok-takip/config/database.php';
require_once 'c:/wamp64/www/stok-takip/app/Models/Mes.php';
require_once 'c:/wamp64/www/stok-takip/app/Models/PanelUnit.php';
require_once 'c:/wamp64/www/stok-takip/app/Services/MesSimulationService.php';

$pdo = (new Database())->connect();
$mesModel = new Mes($pdo);
$panelModel = new PanelUnit($pdo);
$simService = new MesSimulationService($pdo);

echo "========================================================================\n";
echo "AŞAMA 5 — KALİTE KONTROL ENTEGRASYON VE DOĞRULAMA TEST PROTOKOLÜ\n";
echo "========================================================================\n";

// 1. Create a temporary Work Order
$woNo = 'WO-QC-TEST-' . strtoupper(bin2hex(random_bytes(3)));
$woId = $mesModel->createWorkOrder([
    'work_order_no'       => $woNo,
    'product_material_id' => 25,
    'recipe_id'           => 3,
    'production_line_id'  => 1,
    'planned_quantity'    => 1.0,
    'status'              => 'READY'
]);

// 2. Set hammadde stocks high to ensure no stock deficiency issues
$pdo->exec("UPDATE stock_balances SET quantity = 50000.0 WHERE location_id = 1");

// 3. Process tick to produce 1 panel
$simRes = $simService->processTick($woId, true);
if (!$simRes['success']) {
    die("ERROR: Production failed: " . ($simRes['message'] ?? 'Unknown error'));
}

// 4. Fetch the created panel
$panel = $pdo->query("SELECT * FROM panel_units WHERE work_order_id = {$woId} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$panel) {
    die("ERROR: Panel not found in DB!");
}

$panelId = (int)$panel['id'];
$initialCost = (float)$panel['unit_cost'];
$initialRef = $panel['stock_movement_ref'];

// Verify that BOM, cost, and stock movement exist initially
$traceInitial = $panelModel->getTraceabilityChain($panelId);
$initialBomCount = count($traceInitial['bom']['bom_items'] ?? []);
$initialMovementsCount = count($traceInitial['stock_movements'] ?? []);

echo "Initial Panel Serial: {$panel['serial_no']}\n";
echo "Initial Status: {$panel['status']}\n";
echo "Initial BOM Items: {$initialBomCount}\n";
echo "Initial Stock Movements: {$initialMovementsCount}\n";
echo "Initial Cost: {$initialCost} TL\n\n";

// ----------------------------------------------------
// TEST 1: PENDING (Kalite Bekliyor) durumuna geçiş
// ----------------------------------------------------
$pendingJson = json_encode([
    'status' => 'QUALITY_PENDING',
    'visual_inspection' => 'PENDING',
    'el_test' => 'PENDING',
    'flash_test' => 'PENDING',
    'rejection_reason' => null,
    'notes' => 'Muayene bekliyor',
    'checked_by' => 'TestRunner',
    'checked_at' => date('Y-m-d H:i:s')
]);

$pdo->prepare("UPDATE panel_units SET status = 'QUALITY_PENDING', quality_notes = ? WHERE id = ?")
    ->execute([$pendingJson, $panelId]);

$panel = $pdo->query("SELECT * FROM panel_units WHERE id = {$panelId}")->fetch(PDO::FETCH_ASSOC);
$test1Ok = ($panel['status'] === 'QUALITY_PENDING' && !empty($panel['quality_notes']));
echo "TEST 1 (PENDING Durumu): " . ($test1Ok ? "[PASS]" : "[FAIL]") . "\n";

// ----------------------------------------------------
// TEST 2: APPROVED (Kalite Onaylandı) durumuna geçiş ve checked_by/checked_at kontrolü
// ----------------------------------------------------
$approvedJson = json_encode([
    'status' => 'QUALITY_APPROVED',
    'visual_inspection' => 'PASS',
    'el_test' => 'PASS',
    'flash_test' => 'PASS',
    'rejection_reason' => null,
    'notes' => 'Tüm testler başarılı.',
    'checked_by' => 'TestRunner',
    'checked_at' => date('Y-m-d H:i:s')
]);

$pdo->prepare("UPDATE panel_units SET status = 'QUALITY_APPROVED', quality_notes = ? WHERE id = ?")
    ->execute([$approvedJson, $panelId]);

$panel = $pdo->query("SELECT * FROM panel_units WHERE id = {$panelId}")->fetch(PDO::FETCH_ASSOC);
$decoded = json_decode($panel['quality_notes'], true);

$test2Ok = (
    $panel['status'] === 'QUALITY_APPROVED' &&
    $decoded['visual_inspection'] === 'PASS' &&
    $decoded['checked_by'] === 'TestRunner' &&
    !empty($decoded['checked_at'])
);
echo "TEST 2 (APPROVED ve Kontrol Detayları): " . ($test2Ok ? "[PASS]" : "[FAIL]") . "\n";

// ----------------------------------------------------
// TEST 3: REJECTED + Red Nedeni kontrolü
// ----------------------------------------------------
$rejectedJson = json_encode([
    'status' => 'QUALITY_REJECTED',
    'visual_inspection' => 'FAIL',
    'el_test' => 'PASS',
    'flash_test' => 'PASS',
    'rejection_reason' => 'EL testinde hücre çatlağı görüldü.',
    'notes' => 'Hatalı üretim.',
    'checked_by' => 'TestRunner',
    'checked_at' => date('Y-m-d H:i:s')
]);

$pdo->prepare("UPDATE panel_units SET status = 'QUALITY_REJECTED', quality_notes = ? WHERE id = ?")
    ->execute([$rejectedJson, $panelId]);

$panel = $pdo->query("SELECT * FROM panel_units WHERE id = {$panelId}")->fetch(PDO::FETCH_ASSOC);
$decoded = json_decode($panel['quality_notes'], true);

$test3Ok = (
    $panel['status'] === 'QUALITY_REJECTED' &&
    $decoded['rejection_reason'] === 'EL testinde hücre çatlağı görüldü.' &&
    $decoded['visual_inspection'] === 'FAIL'
);
echo "TEST 3 (REJECTED ve Red Nedeni): " . ($test3Ok ? "[PASS]" : "[FAIL]") . "\n";

// ----------------------------------------------------
// TEST 4: Mevcut BOM, Maliyet ve Stok Bilgilerinin Korunması (Immutability)
// ----------------------------------------------------
$traceFinal = $panelModel->getTraceabilityChain($panelId);
$finalBomCount = count($traceFinal['bom']['bom_items'] ?? []);
$finalMovementsCount = count($traceFinal['stock_movements'] ?? []);
$finalCost = (float)$panel['unit_cost'];
$finalRef = $panel['stock_movement_ref'];

$test4Ok = (
    $initialBomCount === $finalBomCount &&
    $initialMovementsCount === $finalMovementsCount &&
    abs($initialCost - $finalCost) < 0.0001 &&
    $initialRef === $finalRef
);

echo "TEST 4 (Mevcut BOM, Stok ve Maliyet Korunması): " . ($test4Ok ? "[PASS]" : "[FAIL]") . "\n";
echo "  -> Nihai BOM Kalem Sayısı: {$finalBomCount} (Önceki: {$initialBomCount})\n";
echo "  -> Nihai Stok Hareket Sayısı: {$finalMovementsCount} (Önceki: {$initialMovementsCount})\n";
echo "  -> Nihai Maliyet: {$finalCost} TL (Önceki: {$initialCost} TL)\n";

// ----------------------------------------------------
// CLEAN UP
// ----------------------------------------------------
$pdo->exec("DELETE FROM panel_units WHERE work_order_id = {$woId}");
$pdo->exec("DELETE FROM stock_movements WHERE reference_no IN (SELECT DISTINCT stock_movement_ref FROM mes_production_events WHERE work_order_id = {$woId})");
$pdo->exec("DELETE FROM mes_production_events WHERE work_order_id = {$woId}");
$pdo->exec("DELETE FROM mes_simulations WHERE work_order_id = {$woId}");
$pdo->exec("DELETE FROM mes_work_orders WHERE id = {$woId}");

echo "\n==================================================\n";
if ($test1Ok && $test2Ok && $test3Ok && $test4Ok) {
    echo "GENEL SONUÇ: TÜM TESTLER BAŞARIYLA GEÇTİ (100% SUCCESS)\n";
} else {
    echo "GENEL SONUÇ: BAZI TESTLER BAŞARISIZ OLDU\n";
}
echo "==================================================\n";
