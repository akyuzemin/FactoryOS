<?php

class EnergyProductionLinkService
{
    private PDO $pdo;

    // Configurable thresholds for alerts
    private array $thresholdConfig = [
        'sec_high_threshold_kwh'  => 10.0, // kWh/panel above this triggers high SEC alert
        'peak_power_limit_kw'     => 450.0, // kW limit during peak hours (T2)
        'idle_power_limit_kw'     => 15.0,  // kW limit during idle / night hours
    ];

    public function __construct(PDO $pdo, array $thresholdConfig = [])
    {
        $this->pdo = $pdo;
        if (!empty($thresholdConfig)) {
            $this->thresholdConfig = array_merge($this->thresholdConfig, $thresholdConfig);
        }
    }

    /**
     * Hat bazlı Spesifik Enerji Tüketimi (SEC) ve ortalama panel başı kWh/TL hesaplar.
     */
    public function getLineSpecificEnergyConsumption(string $targetDate, string $range = 'today', ?string $startDate = null, ?string $endDate = null): array
    {
        $filter = $this->buildDateFilter($targetDate, $range, $startDate, $endDate);

        // 1. Hat bazında toplam kWh ve maliyet (energy_readings & energy_meters)
        $sqlReadings = "
            SELECT 
                l.id as line_id,
                l.name as line_name,
                l.code as line_code,
                COALESCE(SUM(r.active_import_kwh), 0) as total_kwh,
                COALESCE(SUM(r.cost_amount), 0) as total_cost_tl
            FROM production_lines l
            JOIN energy_meters m ON m.line_id = l.id
            JOIN energy_readings r ON r.meter_id = m.id
            WHERE {$filter['where']}
            GROUP BY l.id, l.name, l.code
        ";
        $stmtReadings = $this->pdo->prepare($sqlReadings);
        $stmtReadings->execute($filter['params']);
        $lineReadings = $stmtReadings->fetchAll(PDO::FETCH_ASSOC);

        // 2. Hat bazında toplam üretilen panel adedi (energy_production_logs)
        $sqlProd = "
            SELECT 
                line_id,
                COALESCE(SUM(panels_produced_qty), 0) as total_panels
            FROM energy_production_logs
            WHERE " . str_replace('r.read_at', 'log_date', $filter['where_date']) . "
            GROUP BY line_id
        ";
        $stmtProd = $this->pdo->prepare($sqlProd);
        $stmtProd->execute($filter['params_date']);
        $lineProd = $stmtProd->fetchAll(PDO::FETCH_KEY_PAIR);

        // 3. Tesis Genel Toplamları
        $totalPlantKwh = 0.0;
        $totalPlantCost = 0.0;
        $totalPlantPanels = array_sum($lineProd);

        $linesData = [];
        foreach ($lineReadings as $lr) {
            $lineId = (int)$lr['line_id'];
            $kwh = (float)$lr['total_kwh'];
            $cost = (float)$lr['total_cost_tl'];
            $panels = (int)($lineProd[$lineId] ?? 0);

            $totalPlantKwh += $kwh;
            $totalPlantCost += $cost;

            $secKwh = ($panels > 0) ? ($kwh / $panels) : 0.0;
            $unitEnergyCost = ($panels > 0) ? ($cost / $panels) : 0.0;

            $linesData[] = [
                'line_id'                     => $lineId,
                'line_name'                   => $lr['line_name'],
                'line_code'                   => $lr['line_code'],
                'total_kwh'                   => $kwh,
                'total_cost_tl'               => $cost,
                'produced_panels'             => $panels,
                'sec_kwh_per_panel'           => round($secKwh, 2),
                'unit_energy_cost_tl_per_panel' => round($unitEnergyCost, 2),
                'status_badge'                => ($secKwh > $this->thresholdConfig['sec_high_threshold_kwh']) ? 'WARNING' : 'NORMAL'
            ];
        }

        $plantSecKwh = ($totalPlantPanels > 0) ? ($totalPlantKwh / $totalPlantPanels) : 0.0;
        $plantUnitEnergyCost = ($totalPlantPanels > 0) ? ($totalPlantCost / $totalPlantPanels) : 0.0;

        return [
            'lines'                           => $linesData,
            'total_plant_kwh'                 => $totalPlantKwh,
            'total_plant_cost_tl'             => $totalPlantCost,
            'total_plant_panels'              => $totalPlantPanels,
            'plant_sec_kwh_per_panel'         => round($plantSecKwh, 2),
            'plant_unit_energy_cost_tl_per_panel' => round($plantUnitEnergyCost, 2),
            'date_label'                      => $filter['label']
        ];
    }

    /**
     * Panel Pasaportu için kırılımlı maliyet analizi sunar:
     * Birim Hammadde (BOM) Maliyeti + Tahmini Birim Enerji Maliyeti (SEC) = Toplam İmalat Maliyeti
     */
    public function getPanelEnergyCostBreakdown(array $panel): array
    {
        $bomUnitCost = (float)($panel['unit_cost'] ?? 0.0);
        $prodDate = !empty($panel['produced_at']) ? date('Y-m-d', strtotime($panel['produced_at'])) : date('Y-m-d');
        $lineId = (int)($panel['production_line_id'] ?? 1);

        // İlgili gün ve hattın ortalama birim enerji maliyetini bul
        $stmt = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(r.active_import_kwh), 0) as line_kwh,
                COALESCE(SUM(r.cost_amount), 0) as line_cost
            FROM energy_meters m
            JOIN energy_readings r ON r.meter_id = m.id
            WHERE m.line_id = :line_id AND DATE(r.read_at) = :prod_date
        ");
        $stmt->execute([':line_id' => $lineId, ':prod_date' => $prodDate]);
        $energyData = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmtProd = $this->pdo->prepare("
            SELECT COALESCE(SUM(panels_produced_qty), 0)
            FROM energy_production_logs
            WHERE line_id = :line_id AND log_date = :prod_date
        ");
        $stmtProd->execute([':line_id' => $lineId, ':prod_date' => $prodDate]);
        $dayPanels = (int)$stmtProd->fetchColumn();

        $lineKwh = (float)($energyData['line_kwh'] ?? 0);
        $lineCost = (float)($energyData['line_cost'] ?? 0);

        if ($dayPanels > 0) {
            $estimatedSecKwh = $lineKwh / $dayPanels;
            $estimatedEnergyUnitCost = $lineCost / $dayPanels;
        } else {
            // Varsayılan tesis ortalaması (SEC ~ 8.5 kWh/panel | ~ 35 TL/panel)
            $estimatedSecKwh = 8.50;
            $estimatedEnergyUnitCost = 35.70;
        }

        $totalManufacturingCost = $bomUnitCost + $estimatedEnergyUnitCost;

        return [
            'bom_unit_cost'            => round($bomUnitCost, 4),
            'estimated_sec_kwh'        => round($estimatedSecKwh, 2),
            'estimated_energy_cost_tl' => round($estimatedEnergyUnitCost, 2),
            'total_manufacturing_cost' => round($totalManufacturingCost, 2),
            'production_date'          => $prodDate,
            'is_estimated_sec'         => true,
            'disclaimer'               => 'Enerji maliyeti, hat ve üretim gününün Ortalama Spesifik Enerji Tüketimi (SEC) üzerinden dinamik olarak hesaplanmıştır.'
        ];
    }

    /**
     * Puant Saat (T1/T2/T3) ve Tarife Analizi (Dinamik `energy_tariffs` verisi ile)
     */
    public function getDynamicTariffAnalysis(string $targetDate, string $range = 'today', ?string $startDate = null, ?string $endDate = null): array
    {
        $filter = $this->buildDateFilter($targetDate, $range, $startDate, $endDate);

        // Active Tariff Rates
        $tariffStmt = $this->pdo->query("SELECT * FROM energy_tariffs WHERE is_active = 1 LIMIT 1");
        $tariff = $tariffStmt->fetch(PDO::FETCH_ASSOC);

        $dist = (float)($tariff['distribution_price'] ?? 0.75);
        $priceT1 = (float)($tariff['unit_price_t1'] ?? 3.85) + $dist;
        $priceT2 = (float)($tariff['unit_price_t2'] ?? 5.95) + $dist; // Puant
        $priceT3 = (float)($tariff['unit_price_t3'] ?? 2.40) + $dist; // Gece

        $sql = "
            SELECT 
                tariff_period,
                COALESCE(SUM(active_import_kwh), 0) as kwh,
                COALESCE(SUM(cost_amount), 0) as cost_tl
            FROM energy_readings r
            JOIN energy_meters m ON r.meter_id = m.id
            WHERE m.code = 'MTR-GRID-MAIN' AND {$filter['where']}
            GROUP BY tariff_period
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($filter['params']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $periods = [
            'T1' => ['name' => 'Gündüz (T1)', 'time' => '06:00 - 17:00', 'price' => $priceT1, 'kwh' => 0.0, 'cost_tl' => 0.0, 'color' => '#3b82f6'],
            'T2' => ['name' => 'Puant (T2)',  'time' => '17:00 - 22:00', 'price' => $priceT2, 'kwh' => 0.0, 'cost_tl' => 0.0, 'color' => '#ef4444'],
            'T3' => ['name' => 'Gece (T3)',   'time' => '22:00 - 06:00', 'price' => $priceT3, 'kwh' => 0.0, 'cost_tl' => 0.0, 'color' => '#10b981'],
        ];

        $totalKwh = 0.0;
        $totalCost = 0.0;

        foreach ($rows as $row) {
            $tp = $row['tariff_period'];
            if (isset($periods[$tp])) {
                $periods[$tp]['kwh'] = (float)$row['kwh'];
                $periods[$tp]['cost_tl'] = (float)$row['cost_tl'];
                $totalKwh += (float)$row['kwh'];
                $totalCost += (float)$row['cost_tl'];
            }
        }

        // Percentage calculations
        foreach ($periods as $key => &$p) {
            $p['kwh_share_pct'] = ($totalKwh > 0) ? round(($p['kwh'] / $totalKwh) * 100, 1) : 0.0;
            $p['cost_share_pct'] = ($totalCost > 0) ? round(($p['cost_tl'] / $totalCost) * 100, 1) : 0.0;
        }

        // Peak-shifting savings opportunity calculation (Shifting 20% of T2 peak to T3 night rate)
        $puantKwh = $periods['T2']['kwh'];
        $shiftableKwh = $puantKwh * 0.20;
        $priceDiffPerKwh = max(0.0, $priceT2 - $priceT3);
        $dailySavingTl = $shiftableKwh * $priceDiffPerKwh;
        $monthlySavingTl = $dailySavingTl * 30;

        return [
            'periods'           => $periods,
            'total_kwh'         => $totalKwh,
            'total_cost_tl'     => $totalCost,
            'puant_kwh'         => $puantKwh,
            'puant_cost_tl'     => $periods['T2']['cost_tl'],
            'puant_share_pct'   => $periods['T2']['cost_share_pct'],
            'peak_shift_saving' => [
                'shiftable_kwh'     => round($shiftableKwh, 1),
                'price_diff_per_kwh'=> round($priceDiffPerKwh, 2),
                'monthly_saving_tl' => round($monthlySavingTl, 2),
                'annual_saving_tl'  => round($monthlySavingTl * 12, 2)
            ]
        ];
    }

    private function buildDateFilter(string $targetDate, string $range = 'today', ?string $startDate = null, ?string $endDate = null): array
    {
        if ($range === '7days') {
            $start = date('Y-m-d', strtotime($targetDate . ' -6 days'));
            return [
                'where' => 'DATE(r.read_at) BETWEEN :start_date AND :end_date',
                'params' => [':start_date' => $start, ':end_date' => $targetDate],
                'where_date' => 'log_date BETWEEN :start_date AND :end_date',
                'params_date' => [':start_date' => $start, ':end_date' => $targetDate],
                'label' => 'Son 7 Gün (' . date('d.m', strtotime($start)) . ' - ' . date('d.m.Y', strtotime($targetDate)) . ')'
            ];
        } elseif ($range === '30days') {
            $start = date('Y-m-d', strtotime($targetDate . ' -29 days'));
            return [
                'where' => 'DATE(r.read_at) BETWEEN :start_date AND :end_date',
                'params' => [':start_date' => $start, ':end_date' => $targetDate],
                'where_date' => 'log_date BETWEEN :start_date AND :end_date',
                'params_date' => [':start_date' => $start, ':end_date' => $targetDate],
                'label' => 'Son 30 Gün'
            ];
        } elseif ($range === 'custom' && $startDate && $endDate) {
            return [
                'where' => 'DATE(r.read_at) BETWEEN :start_date AND :end_date',
                'params' => [':start_date' => $startDate, ':end_date' => $endDate],
                'where_date' => 'log_date BETWEEN :start_date AND :end_date',
                'params_date' => [':start_date' => $startDate, ':end_date' => $endDate],
                'label' => date('d.m.Y', strtotime($startDate)) . ' - ' . date('d.m.Y', strtotime($endDate))
            ];
        }

        return [
            'where' => 'DATE(r.read_at) = :target_date',
            'params' => [':target_date' => $targetDate],
            'where_date' => 'log_date = :target_date',
            'params_date' => [':target_date' => $targetDate],
            'label' => 'Bugün (' . date('d.m.Y', strtotime($targetDate)) . ')'
        ];
    }
}
