<?php
require dirname(__DIR__, 2) . '/config/database.php';
require dirname(__DIR__, 2) . '/app/Models/AdminDashboard.php';

$pdo = (new Database())->connect();
$model = new AdminDashboard($pdo);

$dateInfo = $model->resolveDateRange('this_month');
$kpis = $model->getExecutiveKpis($dateInfo['start_date'], $dateInfo['end_date']);
$trend = $model->getProductionTrend($dateInfo['start_date'], $dateInfo['end_date']);
$topMaterials = $model->getTopMaterialsConsumed($dateInfo['start_date'], $dateInfo['end_date']);
$lineMatrix = $model->getLineComparisonMatrix($dateInfo['start_date'], $dateInfo['end_date']);

ob_start();
include dirname(__DIR__, 2) . '/views/admin/dashboard.php';
$output = ob_get_clean();

echo "Rendered length: " . strlen($output) . " bytes\n";
if (strpos($output, 'Fatal error') !== false || strpos($output, 'Notice:') !== false || strpos($output, 'Warning:') !== false) {
    echo "ERROR detected in render output!\n";
    // echo substr($output, 0, 1000);
} else {
    echo "Render successful without PHP errors.\n";
}
