<?php
require dirname(__DIR__, 2) . '/config/database.php';
require dirname(__DIR__, 2) . '/app/Models/AdminDashboard.php';

$pdo = (new Database())->connect();
$model = new AdminDashboard($pdo);

echo "========================================================================\n";
echo "A?AMA 9 ? PAR?A 3 KPI TEST PROTOKOL?\n";
echo "========================================================================\n\n";

$kpis = $model->getExecutiveKpis('2020-01-01', '2030-12-31');

// KAL?TE
if (isset($kpis['quality_total_inspected']) && isset($kpis['quality_approved_count']) && isset($kpis['quality_pending_count'])) {
    echo "[PASS] KAL?TE KPI'lar? ba?ar?yla d?nd?r?ld?. (Toplam: " . $kpis['quality_total_inspected'] . ")\n";
} else {
    echo "[FAIL] KAL?TE KPI'lar? eksik!\n";
}

// SEVK?YAT
if (isset($kpis['total_shipped_panels']) && isset($kpis['pending_shipments_count'])) {
    echo "[PASS] SEVK?YAT KPI'lar? ba?ar?yla d?nd?r?ld?. (Tamamlanan: " . $kpis['completed_shipments_count'] . ")\n";
} else {
    echo "[FAIL] SEVK?YAT KPI'lar? eksik!\n";
}

// ENERJ?
if (isset($kpis['peak_kwh']) && isset($kpis['peak_cost_share_pct']) && isset($kpis['energy_cost_per_panel'])) {
    echo "[PASS] ENERJ? KPI'lar? ba?ar?yla d?nd?r?ld?. (Puant kWh: " . $kpis['peak_kwh'] . ", Maliyet/Panel: " . $kpis['energy_cost_per_panel'] . ")\n";
} else {
    echo "[FAIL] ENERJ? KPI'lar? eksik!\n";
}

echo "========================================================================\n";
