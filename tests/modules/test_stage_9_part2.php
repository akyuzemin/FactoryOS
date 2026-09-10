<?php
require dirname(__DIR__, 2) . '/config/database.php';
require dirname(__DIR__, 2) . '/app/Models/AdminDashboard.php';

$pdo = (new Database())->connect();
$model = new AdminDashboard($pdo);

echo "========================================================================\n";
echo "A?AMA 9 ? PAR?A 2 KPI TEST PROTOKOL?\n";
echo "========================================================================\n\n";

// TEST 1: Date Resolution
$today = $model->resolveDateRange('today');
if ($today['start_date'] == date('Y-m-d') && $today['end_date'] == date('Y-m-d')) {
    echo "[PASS] Tarih Filtresi - Bug?n do?ru ?al???yor.\n";
} else {
    echo "[FAIL] Tarih Filtresi - Bug?n hatal?!\n";
}

$week = $model->resolveDateRange('this_week');
if ($week['start_date'] == date('Y-m-d', strtotime('monday this week')) && $week['end_date'] == date('Y-m-d', strtotime('sunday this week'))) {
    echo "[PASS] Tarih Filtresi - Bu Hafta do?ru ?al???yor.\n";
} else {
    echo "[FAIL] Tarih Filtresi - Bu Hafta hatal?!\n";
}

// TEST 2: Executive KPIs
$kpis = $model->getExecutiveKpis('2020-01-01', '2030-12-31'); // broad range
if (isset($kpis['total_produced_panels']) && isset($kpis['total_wp']) && isset($kpis['total_material_qty'])) {
    echo "[PASS] KPI Metrikleri ba?ar?yla d?nd?r?ld? (total_wp: " . $kpis['total_wp'] . ")\n";
} else {
    echo "[FAIL] KPI Metrikleri eksik d?nd?r?ld?!\n";
}

// TEST 3: Top Materials
$topMaterials = $model->getTopMaterialsConsumed('2020-01-01', '2030-12-31');
if (is_array($topMaterials) && count($topMaterials) <= 5) {
    echo "[PASS] En ?ok t?ketilen hammaddeler ba?ar?yla al?nd? (" . count($topMaterials) . " adet).\n";
} else {
    echo "[FAIL] En ?ok t?ketilen hammaddeler al?namad?!\n";
}

// TEST 4: Line Matrix
$lineMatrix = $model->getLineComparisonMatrix('2020-01-01', '2030-12-31');
if (is_array($lineMatrix)) {
    echo "[PASS] Hat bazl? ?retim matrisi ba?ar?yla al?nd? (" . count($lineMatrix) . " hat).\n";
} else {
    echo "[FAIL] Hat bazl? ?retim matrisi al?namad?!\n";
}

echo "========================================================================\n";
