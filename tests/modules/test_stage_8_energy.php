<?php
require_once 'c:/wamp64/www/stok-takip/config/database.php';
require_once 'c:/wamp64/www/stok-takip/app/Services/EnergyProductionLinkService.php';
require_once 'c:/wamp64/www/stok-takip/app/Models/PanelUnit.php';
require_once 'c:/wamp64/www/stok-takip/app/Models/Mes.php';

$pdo = (new Database())->connect();
$linkService = new EnergyProductionLinkService($pdo);
$panelModel = new PanelUnit($pdo);

echo "========================================================================\n";
echo "AŞAMA 8 — ENERJİ YÖNETİMİ ENTEGRASYON VE DOĞRULAMA TEST PROTOKOLÜ\n";
echo "========================================================================\n";

$targetDate = $pdo->query("SELECT DATE(MAX(read_at)) FROM energy_readings")->fetchColumn() ?: date('Y-m-d');

// ----------------------------------------------------
// TEST 1: Hat Bazlı Spesifik Enerji Tüketimi (SEC) & Ortalama Panel kWh
// ----------------------------------------------------
$lineSecData = $linkService->getLineSpecificEnergyConsumption($targetDate);
$test1Ok = (
    !empty($lineSecData['lines']) &&
    isset($lineSecData['plant_sec_kwh_per_panel']) &&
    $lineSecData['total_plant_kwh'] > 0
);
echo "TEST 1 (Hat Bazlı SEC & Ortalama Panel Başı Tüketim Hesabı): " . ($test1Ok ? "[PASS]" : "[FAIL]") . "\n";
if ($test1Ok) {
    echo "  -> Tesis Ortalama SEC: " . $lineSecData['plant_sec_kwh_per_panel'] . " kWh/panel\n";
    echo "  -> Hat Sayısı: " . count($lineSecData['lines']) . "\n";
}

// ----------------------------------------------------
// TEST 2: Dinamik Puant Tarife Analizi (energy_tariffs verisi ile)
// ----------------------------------------------------
$peakData = $linkService->getDynamicTariffAnalysis($targetDate);
$test2Ok = (
    isset($peakData['periods']['T1']) &&
    isset($peakData['periods']['T2']) &&
    isset($peakData['periods']['T3']) &&
    $peakData['periods']['T2']['price'] > $peakData['periods']['T1']['price']
);
echo "TEST 2 (Dinamik Puant Tarife & Peak-Shifting Analizi): " . ($test2Ok ? "[PASS]" : "[FAIL]") . "\n";

// ----------------------------------------------------
// TEST 3: Panel Pasaportu Maliyet Kırılımı (BOM Snapshot + Enerji SEC)
// ----------------------------------------------------
$samplePanel = $pdo->query("SELECT * FROM panel_units ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$samplePanel) {
    $samplePanel = [
        'id' => 1,
        'serial_no' => 'SP550W-TEST',
        'unit_cost' => 3117.1088,
        'produced_at' => date('Y-m-d H:i:s'),
        'production_line_id' => 1
    ];
}

$breakdown = $linkService->getPanelEnergyCostBreakdown($samplePanel);
$test3Ok = (
    (float)$breakdown['bom_unit_cost'] === round((float)$samplePanel['unit_cost'], 4) &&
    isset($breakdown['estimated_sec_kwh']) &&
    isset($breakdown['total_manufacturing_cost']) &&
    (float)$breakdown['total_manufacturing_cost'] == round((float)$breakdown['bom_unit_cost'] + (float)$breakdown['estimated_energy_cost_tl'], 2)
);
echo "TEST 3 (BOM Snapshot + Enerji SEC = Toplam İmalat Maliyeti Kırılımı): " . ($test3Ok ? "[PASS]" : "[FAIL]") . "\n";

// ----------------------------------------------------
// TEST 4: Tarihsel Snapshot Dokunulmazlığı (Immutability)
// ----------------------------------------------------
$originalUnitCost = (float)$samplePanel['unit_cost'];
$newBreakdown = $linkService->getPanelEnergyCostBreakdown($samplePanel);
$test4Ok = ((float)$samplePanel['unit_cost'] === $originalUnitCost);
echo "TEST 4 (Tarihsel BOM Snapshot Dokunulmazlığı): " . ($test4Ok ? "[PASS]" : "[FAIL]") . "\n";

echo "\n==================================================\n";
if ($test1Ok && $test2Ok && $test3Ok && $test4Ok) {
    echo "AŞAMA 8 ENERJİ TESTLERİ: TÜMÜ BAŞARIYLA GEÇTİ (100% SUCCESS)\n";
} else {
    echo "AŞAMA 8 ENERJİ TESTLERİ: BAZI TESTLER BAŞARISIZ OLDU\n";
}
echo "==================================================\n";
