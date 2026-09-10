<?php

class EnergyCost
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * En son okuma tarihini döner.
     */
    public function getLatestDate(): string
    {
        $stmt = $this->pdo->query("SELECT DATE(MAX(read_at)) FROM energy_readings");
        $date = $stmt->fetchColumn();
        return $date ?: date('Y-m-d');
    }

    /**
     * Aktif elektrik tarifesini döner.
     */
    public function getActiveTariff(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM energy_tariffs WHERE is_active = 1 LIMIT 1");
        $tariff = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tariff) {
            return [
                'code' => 'TRF-DEFAULT',
                'name' => 'Varsayılan Sanayi Tarifesi',
                'unit_price_t1' => 3.85,
                'unit_price_t2' => 5.95,
                'unit_price_t3' => 2.40,
                'distribution_price' => 0.75,
                'solar_feed_in_tariff' => 3.50,
                't1_total' => 4.60,
                't2_total' => 6.70,
                't3_total' => 3.15
            ];
        }

        $dist = (float)$tariff['distribution_price'];
        $tariff['t1_total'] = (float)$tariff['unit_price_t1'] + $dist;
        $tariff['t2_total'] = (float)$tariff['unit_price_t2'] + $dist;
        $tariff['t3_total'] = (float)$tariff['unit_price_t3'] + $dist;
        $tariff['solar_feed_in_tariff'] = (float)$tariff['solar_feed_in_tariff'];

        return $tariff;
    }

    /**
     * Tarih filtresi için SQL WHERE ve parametreleri üretir.
     */
    private function buildDateFilter(string $targetDate, string $range = 'today', ?string $startDate = null, ?string $endDate = null): array
    {
        if ($range === '7days') {
            $start = date('Y-m-d', strtotime($targetDate . ' -6 days'));
            return [
                'where' => 'DATE(r.read_at) BETWEEN :start_date AND :end_date',
                'params' => [':start_date' => $start, ':end_date' => $targetDate],
                'days_count' => 7,
                'label' => 'Son 7 Gün (' . date('d.m', strtotime($start)) . ' - ' . date('d.m.Y', strtotime($targetDate)) . ')'
            ];
        } elseif ($range === '30days') {
            $start = date('Y-m-d', strtotime($targetDate . ' -29 days'));
            return [
                'where' => 'DATE(r.read_at) BETWEEN :start_date AND :end_date',
                'params' => [':start_date' => $start, ':end_date' => $targetDate],
                'days_count' => 30,
                'label' => 'Son 30 Gün'
            ];
        } elseif ($range === 'custom' && $startDate && $endDate) {
            $d1 = new DateTime($startDate);
            $d2 = new DateTime($endDate);
            $days = max(1, $d2->diff($d1)->days + 1);
            return [
                'where' => 'DATE(r.read_at) BETWEEN :start_date AND :end_date',
                'params' => [':start_date' => $startDate, ':end_date' => $endDate],
                'days_count' => $days,
                'label' => date('d.m.Y', strtotime($startDate)) . ' - ' . date('d.m.Y', strtotime($endDate))
            ];
        }

        return [
            'where' => 'DATE(r.read_at) = :target_date',
            'params' => [':target_date' => $targetDate],
            'days_count' => 1,
            'label' => 'Bugün (' . date('d.m.Y', strtotime($targetDate)) . ')'
        ];
    }

    /**
     * 1. Maliyet ve Tüketim KPI Özeti
     */
    public function getCostSummary(string $targetDate, string $range = 'today', ?string $startDate = null, ?string $endDate = null): array
    {
        $filter = $this->buildDateFilter($targetDate, $range, $startDate, $endDate);
        $tariff = $this->getActiveTariff();

        // Şebeke alımı ve maliyet
        $stmtGrid = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(r.active_import_kwh), 0) as grid_import_kwh,
                COALESCE(SUM(r.cost_amount), 0) as total_cost_tl
            FROM energy_readings r
            JOIN energy_meters m ON r.meter_id = m.id
            WHERE m.code = 'MTR-GRID-MAIN' AND {$filter['where']}
        ");
        $stmtGrid->execute($filter['params']);
        $grid = $stmtGrid->fetch(PDO::FETCH_ASSOC) ?: ['grid_import_kwh' => 0, 'total_cost_tl' => 0];

        // Çatı GES üretimi
        $stmtSolar = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(r.active_export_kwh), 0) as solar_gen_kwh,
                COALESCE(SUM(r.cost_amount), 0) as solar_value_tl
            FROM energy_readings r
            JOIN energy_meters m ON r.meter_id = m.id
            WHERE m.code = 'MTR-SOLAR-MAIN' AND {$filter['where']}
        ");
        $stmtSolar->execute($filter['params']);
        $solar = $stmtSolar->fetch(PDO::FETCH_ASSOC) ?: ['solar_gen_kwh' => 0, 'solar_value_tl' => 0];

        $gridKwh = (float)$grid['grid_import_kwh'];
        $costTl = (float)$grid['total_cost_tl'];
        $solarKwh = (float)$solar['solar_gen_kwh'];
        $solarValueTl = (float)$solar['solar_value_tl'];

        $totalPlantEnergy = $gridKwh + $solarKwh;
        $solarShare = $totalPlantEnergy > 0 ? min(100.0, ($solarKwh / $totalPlantEnergy) * 100.0) : 0.0;

        // Günlük normalize edilmiş maliyet üzerinden aylık projeksiyon
        $daysCount = $filter['days_count'];
        $dailyAvgCost = $daysCount > 0 ? ($costTl / $daysCount) : 0.0;
        $monthlyProjectedCost = $dailyAvgCost * 30.0;
        $annualProjectedCost = $monthlyProjectedCost * 12.0;

        return [
            'range_label' => $filter['label'],
            'days_count' => $daysCount,
            'grid_import_kwh' => $gridKwh,
            'cost_tl' => $costTl,
            'solar_gen_kwh' => $solarKwh,
            'solar_value_tl' => $solarValueTl,
            'solar_share_percentage' => $solarShare,
            'daily_avg_cost_tl' => $dailyAvgCost,
            'monthly_projected_cost_tl' => $monthlyProjectedCost,
            'annual_projected_cost_tl' => $annualProjectedCost,
            'target_date' => $targetDate,
            'range' => $range
        ];
    }

    /**
     * 2. Tarife Analizi (T1, T2, T3)
     */
    public function getTariffAnalysis(string $targetDate, string $range = 'today', ?string $startDate = null, ?string $endDate = null): array
    {
        $filter = $this->buildDateFilter($targetDate, $range, $startDate, $endDate);
        $tariff = $this->getActiveTariff();

        $stmt = $this->pdo->prepare("
            SELECT 
                r.tariff_period,
                COALESCE(SUM(r.active_import_kwh), 0) as kwh,
                COALESCE(SUM(r.cost_amount), 0) as cost_tl
            FROM energy_readings r
            JOIN energy_meters m ON r.meter_id = m.id
            WHERE m.code = 'MTR-GRID-MAIN' AND {$filter['where']}
            GROUP BY r.tariff_period
        ");
        $stmt->execute($filter['params']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $meta = [
            'T1' => ['name' => 'Gündüz', 'hours' => '06:00 - 17:00', 'price' => $tariff['t1_total'], 'color' => '#06b6d4', 'is_peak' => false],
            'T2' => ['name' => 'Puant (En Pahalı Dönem)', 'hours' => '17:00 - 22:00', 'price' => $tariff['t2_total'], 'color' => '#ef4444', 'is_peak' => true],
            'T3' => ['name' => 'Gece (Ekonomik Dönem)', 'hours' => '22:00 - 06:00', 'price' => $tariff['t3_total'], 'color' => '#10b981', 'is_peak' => false]
        ];

        $byPeriod = [];
        $totalKwh = 0.0;
        $totalCost = 0.0;

        foreach ($rows as $r) {
            $p = $r['tariff_period'];
            $kwh = (float)$r['kwh'];
            $cost = (float)$r['cost_tl'];
            $byPeriod[$p] = ['kwh' => $kwh, 'cost' => $cost];
            $totalKwh += $kwh;
            $totalCost += $cost;
        }

        $periods = [];
        foreach (['T1', 'T2', 'T3'] as $p) {
            $kwh = $byPeriod[$p]['kwh'] ?? 0.0;
            $cost = $byPeriod[$p]['cost'] ?? 0.0;
            $kwhPct = $totalKwh > 0 ? round(($kwh / $totalKwh) * 100, 1) : 0.0;
            $costPct = $totalCost > 0 ? round(($cost / $totalCost) * 100, 1) : 0.0;

            $periods[] = [
                'period' => $p,
                'name' => $meta[$p]['name'],
                'hours' => $meta[$p]['hours'],
                'unit_price' => $meta[$p]['price'],
                'color' => $meta[$p]['color'],
                'is_peak' => $meta[$p]['is_peak'],
                'kwh' => $kwh,
                'cost_tl' => $cost,
                'kwh_percentage' => $kwhPct,
                'cost_percentage' => $costPct
            ];
        }

        return [
            'total_kwh'     => $totalKwh,
            'total_cost_tl' => $totalCost,
            'periods'       => $periods,
            'gunduz'        => [
                'name'          => 'Gündüz',
                'hours'         => '06:00 - 17:00',
                'unit_price'    => $meta['T1']['price'],
                'unit_price_tl' => $meta['T1']['price'],
                'kwh'           => $byPeriod['T1']['kwh'] ?? 0.0,
                'cost_tl'       => $byPeriod['T1']['cost'] ?? 0.0
            ],
            'puant'         => [
                'name'          => 'Puant',
                'hours'         => '17:00 - 22:00',
                'unit_price'    => $meta['T2']['price'],
                'unit_price_tl' => $meta['T2']['price'],
                'kwh'           => $byPeriod['T2']['kwh'] ?? 0.0,
                'cost_tl'       => $byPeriod['T2']['cost'] ?? 0.0
            ],
            'gece'          => [
                'name'          => 'Gece',
                'hours'         => '22:00 - 06:00',
                'unit_price'    => $meta['T3']['price'],
                'unit_price_tl' => $meta['T3']['price'],
                'kwh'           => $byPeriod['T3']['kwh'] ?? 0.0,
                'cost_tl'       => $byPeriod['T3']['cost'] ?? 0.0
            ]
        ];
    }

    /**
     * 3A. Puant Saat Kaydırma (Peak-Shifting) Analizi
     */
    public function getPeakShiftingOpportunity(string $targetDate, string $range = 'today', ?string $startDate = null, ?string $endDate = null): array
    {
        $filter = $this->buildDateFilter($targetDate, $range, $startDate, $endDate);
        $tariff = $this->getActiveTariff();

        $stmt = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(r.active_import_kwh), 0) as puant_kwh,
                COALESCE(SUM(r.cost_amount), 0) as puant_cost,
                COALESCE(AVG(r.active_power_kw), 0) as avg_puant_kw,
                COALESCE(MAX(r.active_power_kw), 0) as max_puant_kw
            FROM energy_readings r
            JOIN energy_meters m ON r.meter_id = m.id
            WHERE m.code = 'MTR-GRID-MAIN' AND r.tariff_period = 'T2' AND {$filter['where']}
        ");
        $stmt->execute($filter['params']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $puantKwh = (float)$row['puant_kwh'];
        $puantCost = (float)$row['puant_cost'];
        $daysCount = max(1, $filter['days_count']);
        $dailyPuantKwh = $puantKwh / $daysCount;

        $shiftPercent = 0.25; // %25 Puant Yük Kaydırma
        $shiftedDailyKwh = $dailyPuantKwh * $shiftPercent;

        $t2Price = $tariff['t2_total']; // 6.70
        $t3Price = $tariff['t3_total']; // 3.15
        $priceDiff = $t2Price - $t3Price; // 3.55 TL/kWh

        $dailySavingTl = $shiftedDailyKwh * $priceDiff;
        $monthlySavingTl = $dailySavingTl * 30.0;
        $annualSavingTl = $monthlySavingTl * 12.0;

        $currentMonthlyPuantCost = ($puantCost / $daysCount) * 30.0;
        $proposedMonthlyPuantCost = max(0, $currentMonthlyPuantCost - $monthlySavingTl);

        return [
            'total_puant_kwh' => $puantKwh,
            'daily_puant_kwh' => $dailyPuantKwh,
            'current_puant_cost_tl' => $puantCost,
            'current_monthly_cost_tl' => $currentMonthlyPuantCost,
            'proposed_monthly_cost_tl' => $proposedMonthlyPuantCost,
            'shifted_daily_kwh' => $shiftedDailyKwh,
            'shift_percentage' => $shiftPercent * 100,
            't2_price' => $t2Price,
            't3_price' => $t3Price,
            'price_diff' => $priceDiff,
            'daily_saving_tl' => $dailySavingTl,
            'monthly_saving_tl' => $monthlySavingTl,
            'annual_saving_tl' => $annualSavingTl,
            'avg_puant_kw' => (float)$row['avg_puant_kw'],
            'max_puant_kw' => (float)$row['max_puant_kw']
        ];
    }

    /**
     * 3B. Gece Baz Yükü (Standby Idling) Optimizasyonu
     */
    public function getIdleConsumptionOpportunity(string $targetDate, string $range = 'today', ?string $startDate = null, ?string $endDate = null): array
    {
        $filter = $this->buildDateFilter($targetDate, $range, $startDate, $endDate);
        $tariff = $this->getActiveTariff();

        $stmt = $this->pdo->prepare("
            SELECT 
                m.code, m.name,
                AVG(r.active_power_kw) as avg_night_kw,
                SUM(r.active_import_kwh) as total_night_kwh,
                SUM(r.cost_amount) as total_night_cost
            FROM energy_readings r
            JOIN energy_meters m ON r.meter_id = m.id
            WHERE m.code IN ('MTR-LAM-1', 'MTR-LAM-2', 'MTR-COMP-1')
              AND HOUR(r.read_at) >= 0 AND HOUR(r.read_at) < 6
              AND {$filter['where']}
            GROUP BY m.id, m.code, m.name
            ORDER BY avg_night_kw DESC
        ");
        $stmt->execute($filter['params']);
        $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalNightKw = 0.0;
        foreach ($lines as $l) {
            $totalNightKw += (float)$l['avg_night_kw'];
        }

        $preventableKw = 30.0; // Standby modunda 30 kW azaltım potansiyeli
        $nightHours = 6.0;     // 00:00 - 06:00
        $dailyIdleKwh = $preventableKw * $nightHours; // 180 kWh/gün
        $monthlyIdleKwh = $dailyIdleKwh * 30.0;       // 5,400 kWh/ay
        $annualIdleKwh = $monthlyIdleKwh * 12.0;

        $t3Price = $tariff['t3_total']; // 3.15 TL
        $monthlySavingTl = $monthlyIdleKwh * $t3Price;
        $annualSavingTl = $monthlySavingTl * 12.0;

        return [
            'monitored_lines' => $lines,
            'total_night_baseline_kw' => $totalNightKw,
            'preventable_kw' => $preventableKw,
            'daily_idle_kwh' => $dailyIdleKwh,
            'monthly_idle_kwh' => $monthlyIdleKwh,
            'annual_idle_kwh' => $annualIdleKwh,
            't3_price' => $t3Price,
            'monthly_saving_tl' => $monthlySavingTl,
            'annual_saving_tl' => $annualSavingTl
        ];
    }

    /**
     * 3C. Üretim Hattı SEC (Spesifik Enerji Tüketimi) Optimizasyonu
     */
    public function getSecOptimization(string $targetDate, string $range = 'today', ?string $startDate = null, ?string $endDate = null): array
    {
        $filter = $this->buildDateFilter($targetDate, $range, $startDate, $endDate);
        $tariff = $this->getActiveTariff();

        $stmt = $this->pdo->prepare("
            SELECT 
                pl.id, pl.code, pl.name, pl.nominal_power_kw,
                COALESCE(SUM(r.active_import_kwh), 0) as total_kwh,
                COALESCE(p.panels_qty, 0) as panels_qty
            FROM production_lines pl
            LEFT JOIN energy_meters m ON m.line_id = pl.id
            LEFT JOIN energy_readings r ON r.meter_id = m.id AND {$filter['where']}
            LEFT JOIN (
                SELECT line_id, SUM(panels_produced_qty) as panels_qty
                FROM energy_production_logs
                WHERE " . str_replace('r.read_at', 'log_date', $filter['where']) . "
                GROUP BY line_id
            ) p ON p.line_id = pl.id
            GROUP BY pl.id, pl.code, pl.name, pl.nominal_power_kw, p.panels_qty
            ORDER BY pl.id ASC
        ");
        $stmt->execute($filter['params']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalPlantKwh = 0.0;
        $totalPlantPanels = 0;
        foreach ($rows as $r) {
            $totalPlantKwh += (float)$r['total_kwh'];
            $totalPlantPanels += (int)$r['panels_qty'];
        }
        $plantAvgSec = $totalPlantPanels > 0 ? ($totalPlantKwh / $totalPlantPanels) : 0.0;

        $lineDetails = [];
        $opportunityCount = 0;
        foreach ($rows as $r) {
            $kwh = (float)$r['total_kwh'];
            $panels = (int)$r['panels_qty'];
            $sec = $panels > 0 ? ($kwh / $panels) : 0.0;
            $deviation = $sec - $plantAvgSec;
            $isOpportunity = ($sec > 8.0 && str_contains($r['code'], 'LAM'));
            if ($isOpportunity) {
                $opportunityCount++;
            }

            $lineDetails[] = [
                'id' => (int)$r['id'],
                'code' => $r['code'],
                'name' => $r['name'],
                'nominal_power_kw' => (float)$r['nominal_power_kw'],
                'total_kwh' => $kwh,
                'panels_qty' => $panels,
                'sec_kwh_per_panel' => $sec,
                'deviation' => $deviation,
                'is_opportunity' => $isOpportunity
            ];
        }

        // Laminatör fırınlarında 0.25 kWh/panel termal verim artışı ile aylık 50.000 panel üzerinden tasarruf
        $secImprovementKwhPerPanel = 0.25;
        $monthlyTargetPanels = 50000;
        $avgUnitPrice = 4.20; // Ağırlıklı ortalama birim fiyat
        $monthlySavingTl = $monthlyTargetPanels * $secImprovementKwhPerPanel * $avgUnitPrice; // 52.500 TL
        $annualSavingTl = $monthlySavingTl * 12.0;

        return [
            'plant_avg_sec' => $plantAvgSec,
            'total_plant_kwh' => $totalPlantKwh,
            'total_plant_panels' => $totalPlantPanels,
            'lines' => $lineDetails,
            'opportunity_count' => $opportunityCount,
            'sec_improvement_kwh_per_panel' => $secImprovementKwhPerPanel,
            'monthly_saving_tl' => $monthlySavingTl,
            'annual_saving_tl' => $annualSavingTl
        ];
    }

    /**
     * 3D. Çatı GES Öz Tüketim & Senkronizasyon Analizi
     */
    public function getSolarSelfConsumption(string $targetDate, string $range = 'today', ?string $startDate = null, ?string $endDate = null): array
    {
        $filter = $this->buildDateFilter($targetDate, $range, $startDate, $endDate);
        $tariff = $this->getActiveTariff();

        $stmt = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN m.code = 'MTR-SOLAR-MAIN' THEN r.active_export_kwh ELSE 0 END), 0) as solar_kwh,
                COALESCE(SUM(CASE WHEN m.code = 'MTR-GRID-MAIN' THEN r.active_import_kwh ELSE 0 END), 0) as grid_kwh
            FROM energy_readings r
            JOIN energy_meters m ON r.meter_id = m.id
            WHERE m.code IN ('MTR-SOLAR-MAIN', 'MTR-GRID-MAIN') AND {$filter['where']}
        ");
        $stmt->execute($filter['params']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $solarKwh = (float)$row['solar_kwh'];
        $gridKwh = (float)$row['grid_kwh'];
        $totalPlantEnergy = $solarKwh + $gridKwh;
        $solarShare = $totalPlantEnergy > 0 ? ($solarKwh / $totalPlantEnergy) * 100.0 : 0.0;

        $t1Price = $tariff['t1_total']; // 4.60
        $feedInPrice = $tariff['solar_feed_in_tariff']; // 3.50
        $solarDirectValueTl = $solarKwh * $t1Price;

        // Tepe güneş saatlerinde 800 kWh/günlük yük senkronizasyon optimizasyonu
        $syncKwhPerDay = 800.0;
        $syncWorkDaysPerMonth = 26;
        $monthlySyncSavingTl = $syncKwhPerDay * ($t1Price - $feedInPrice) * $syncWorkDaysPerMonth; // 22.880 TL
        $annualSyncSavingTl = $monthlySyncSavingTl * 12.0;

        return [
            'solar_kwh' => $solarKwh,
            'grid_kwh' => $gridKwh,
            'total_plant_energy_kwh' => $totalPlantEnergy,
            'solar_share_percentage' => $solarShare,
            'solar_direct_value_tl' => $solarDirectValueTl,
            't1_price' => $t1Price,
            'feed_in_price' => $feedInPrice,
            'monthly_sync_saving_tl' => $monthlySyncSavingTl,
            'annual_sync_saving_tl' => $annualSyncSavingTl
        ];
    }
}
