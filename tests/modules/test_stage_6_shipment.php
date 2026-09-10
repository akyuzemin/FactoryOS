<?php
require_once 'c:/wamp64/www/stok-takip/config/database.php';
require_once 'c:/wamp64/www/stok-takip/app/Models/Mes.php';
require_once 'c:/wamp64/www/stok-takip/app/Models/PanelUnit.php';
require_once 'c:/wamp64/www/stok-takip/app/Models/Shipment.php';
require_once 'c:/wamp64/www/stok-takip/app/Services/MesSimulationService.php';

$pdo = (new Database())->connect();
$mesModel = new Mes($pdo);
$panelModel = new PanelUnit($pdo);
$shipmentModel = new Shipment($pdo);
$simService = new MesSimulationService($pdo);

echo "========================================================================\n";
echo "AŞAMA 6 — DEPO VE SEVKİYAT ENTEGRASYON VE DOĞRULAMA TEST PROTOKOLÜ\n";
echo "========================================================================\n";

// 1. Create a temporary Work Order & produce a panel
$woNo = 'WO-SHP-TEST-' . strtoupper(bin2hex(random_bytes(3)));
$woId = $mesModel->createWorkOrder([
    'work_order_no'       => $woNo,
    'product_material_id' => 25,
    'recipe_id'           => 3,
    'production_line_id'  => 1,
    'planned_quantity'    => 1.0,
    'status'              => 'READY'
]);

$pdo->exec("UPDATE stock_balances SET quantity = 50000.0 WHERE location_id = 1");
$simRes = $simService->processTick($woId, true);
$panel = $pdo->query("SELECT * FROM panel_units WHERE work_order_id = {$woId} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$panelId = (int)$panel['id'];

$originalWarehouseId = (int)$panel['warehouse_id'];
$originalLocationId = (int)$panel['location_id'];
$originalMaterialId = (int)$panel['material_id'];

echo "Produced Panel Serial: {$panel['serial_no']} | Default Status: {$panel['status']}\n";

// 2. Create two DRAFT shipments
$ship1Id = $shipmentModel->create([
    'customer_name' => 'Test Customer A',
    'shipping_address' => 'Customer A Address'
]);
$ship2Id = $shipmentModel->create([
    'customer_name' => 'Test Customer B',
    'shipping_address' => 'Customer B Address'
]);

$ship1 = $shipmentModel->getById($ship1Id);
echo "Created Shipment 1: {$ship1['shipment_no']} (Status: {$ship1['status']})\n";

// ----------------------------------------------------
// TEST 1: Kalite Onaylı olmayan (IN_STOCK veya QUALITY_PENDING) panelin eklenmesini engelle
// ----------------------------------------------------
$res1 = $shipmentModel->addPanel($ship1Id, $panelId);
$test1Ok = ($res1['success'] === false && str_contains($res1['message'], 'QUALITY_APPROVED'));
echo "TEST 1 (Kalite Onaylanmamış Panel Engeli): " . ($test1Ok ? "[PASS]" : "[FAIL]") . "\n";

// ----------------------------------------------------
// TEST 2: Reddedilmiş (QUALITY_REJECTED) panelin eklenmesini engelle
// ----------------------------------------------------
$pdo->exec("UPDATE panel_units SET status = 'QUALITY_REJECTED' WHERE id = {$panelId}");
$res2 = $shipmentModel->addPanel($ship1Id, $panelId);
$test2Ok = ($res2['success'] === false && str_contains($res2['message'], 'QUALITY_APPROVED'));
echo "TEST 2 (Reddedilmiş Panel Engeli): " . ($test2Ok ? "[PASS]" : "[FAIL]") . "\n";

// ----------------------------------------------------
// TEST 3: Kalite onaylı (QUALITY_APPROVED) panelin eklenmesi
// ----------------------------------------------------
$pdo->exec("UPDATE panel_units SET status = 'QUALITY_APPROVED' WHERE id = {$panelId}");
$res3 = $shipmentModel->addPanel($ship1Id, $panelId);
$test3Ok = ($res3['success'] === true);
echo "TEST 3 (Onaylı Paneli Sevkiyata Ekleme): " . ($test3Ok ? "[PASS]" : "[FAIL]") . "\n";

// ----------------------------------------------------
// TEST 4: Aynı panelin aynı anda iki farklı aktif sevkiyata eklenmesini engelle
// ----------------------------------------------------
$res4 = $shipmentModel->addPanel($ship2Id, $panelId);
$test4Ok = ($res4['success'] === false && str_contains($res4['message'], 'başka bir aktif sevkiyat'));
echo "TEST 4 (Çift Sevkiyat Planlama Koruması): " . ($test4Ok ? "[PASS]" : "[FAIL]") . "\n";

// ----------------------------------------------------
// TEST 5: Sevkiyatı Tamamlama & Stok OUT ve Panel SHIPPED Güncellemeleri (Transaction)
// ----------------------------------------------------
$res5 = $shipmentModel->complete($ship1Id, 1);
$test5Ok = ($res5['success'] === true);

// Verify DB results after completion
$ship1After = $shipmentModel->getById($ship1Id);
$panelAfter = $pdo->query("SELECT * FROM panel_units WHERE id = {$panelId}")->fetch(PDO::FETCH_ASSOC);

// Look for stock OUT movement
$movement = $pdo->prepare("
    SELECT * FROM stock_movements 
    WHERE reference_no = :ref AND material_id = :mat AND movement_type = 'OUT' 
    LIMIT 1
");
$movement->execute([
    ':ref' => $ship1['shipment_no'],
    ':mat' => $originalMaterialId
]);
$outMov = $movement->fetch(PDO::FETCH_ASSOC);

$test5VerifyOk = (
    $ship1After['status'] === 'COMPLETED' &&
    $panelAfter['status'] === 'SHIPPED' &&
    (int)$panelAfter['warehouse_id'] === 0 &&
    (int)$panelAfter['location_id'] === 0 &&
    $outMov !== false &&
    (float)$outMov['quantity'] == 1.0 &&
    (int)$outMov['location_id'] === $originalLocationId
);

echo "TEST 5 (Sevkiyatı Tamamlama ve Stok Çıkışı): " . ($test5Ok && $test5VerifyOk ? "[PASS]" : "[FAIL]") . "\n";

// ----------------------------------------------------
// TEST 6: Panel Pasaportunda Sevkiyat Geçmişi Çekilmesi
// ----------------------------------------------------
$history = $shipmentModel->getPanelShipmentHistory($panelId);
$test6Ok = ($history !== null && $history['shipment_no'] === $ship1['shipment_no']);
echo "TEST 6 (Panel Pasaportu Sevkiyat İlişkisi): " . ($test6Ok ? "[PASS]" : "[FAIL]") . "\n";

// ----------------------------------------------------
// CLEAN UP
// ----------------------------------------------------
$pdo->exec("DELETE FROM shipment_items WHERE shipment_id IN ({$ship1Id}, {$ship2Id})");
$pdo->exec("DELETE FROM shipments WHERE id IN ({$ship1Id}, {$ship2Id})");
$pdo->exec("DELETE FROM panel_units WHERE work_order_id = {$woId}");
$pdo->exec("DELETE FROM stock_movements WHERE reference_no IN (SELECT DISTINCT stock_movement_ref FROM mes_production_events WHERE work_order_id = {$woId})");
$pdo->exec("DELETE FROM stock_movements WHERE reference_no = '{$ship1['shipment_no']}'");
$pdo->exec("DELETE FROM mes_production_events WHERE work_order_id = {$woId}");
$pdo->exec("DELETE FROM mes_simulations WHERE work_order_id = {$woId}");
$pdo->exec("DELETE FROM mes_work_orders WHERE id = {$woId}");

echo "\n==================================================\n";
if ($test1Ok && $test2Ok && $test3Ok && $test4Ok && $test5Ok && $test5VerifyOk && $test6Ok) {
    echo "DEPO SEVKİYAT TESTLERİ: TÜMÜ BAŞARIYLA GEÇTİ (100% SUCCESS)\n";
} else {
    echo "DEPO SEVKİYAT TESTLERİ: BAZI TESTLER BAŞARISIZ OLDU\n";
}
echo "==================================================\n";
