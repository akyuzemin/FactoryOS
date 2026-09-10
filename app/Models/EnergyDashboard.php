<?php

class EnergyDashboard
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Veritabanındaki en son ölçüm tarihini döner.
     */
    public function getLatestDate(): string
    {
        $stmt = $this->pdo->query("SELECT DATE(MAX(read_at)) FROM energy_readings");
        $date = $stmt->fetchColumn();
        return $date ?: date('Y-m-d');
    }

    /**
     * Günlük temel KPI metriklerini hesaplar.
     */
    public function getKpis(?string $date = null): array
    {
        $targetDate = $date ?: $this->getLatestDate();

        // 1. Şebeke Tüketimi ve Maliyet
        $stmtGrid = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(r.active_import_kwh), 0) as grid_import_kwh,
                COALESCE(SUM(r.active_export_kwh), 0) as grid_export_kwh,
                COALESCE(SUM(r.cost_amount), 0) as total_cost_tl
            FROM energy_readings r
            JOIN energy_meters m ON r.meter_id = m.id
            WHERE m.code = 'MTR-GRID-MAIN' AND DATE(r.read_at) = :target_date
        ");
        $stmtGrid->execute([':target_date' => $targetDate]);
        $grid = $stmtGrid->fetch(PDO::FETCH_ASSOC) ?: ['grid_import_kwh' => 0, 'grid_export_kwh' => 0, 'total_cost_tl' => 0];

        // 2. Çatı GES Güneş Üretimi
        $stmtSolar = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(r.active_export_kwh), 0) as solar_gen_kwh,
                COALESCE(SUM(r.cost_amount), 0) as solar_value_tl
            FROM energy_readings r
            JOIN energy_meters m ON r.meter_id = m.id
            WHERE m.code = 'MTR-SOLAR-MAIN' AND DATE(r.read_at) = :target_date
        ");
        $stmtSolar->execute([':target_date' => $targetDate]);
        $solar = $stmtSolar->fetch(PDO::FETCH_ASSOC) ?: ['solar_gen_kwh' => 0, 'solar_value_tl' => 0];

        // 3. Üretim Çıktısı (Paneller ve Wp)
        $stmtProd = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(panels_produced_qty), 0) as total_panels,
                COALESCE(SUM(total_wp_produced), 0) as total_wp,
                COALESCE(SUM(scrap_panels_qty), 0) as total_scrap
            FROM energy_production_logs
            WHERE log_date = :target_date
        ");
        $stmtProd->execute([':target_date' => $targetDate]);
        $prod = $stmtProd->fetch(PDO::FETCH_ASSOC) ?: ['total_panels' => 0, 'total_wp' => 0, 'total_scrap' => 0];

        // 4. Alt Sayaçlar Tüketim Toplamı
        $stmtSub = $this->pdo->prepare("
            SELECT COALESCE(SUM(r.active_import_kwh), 0)
            FROM energy_readings r
            JOIN energy_meters m ON r.meter_id = m.id
            WHERE m.meter_type IN ('PRODUCTION_SUBMETER', 'AUXILIARY') AND DATE(r.read_at) = :target_date
        ");
        $stmtSub->execute([':target_date' => $targetDate]);
        $submetersSum = (float)$stmtSub->fetchColumn();

        // 5. Alarm Sayaçları
        $critAlerts = (int)$this->pdo->query("SELECT COUNT(*) FROM energy_alerts WHERE severity = 'CRITICAL' AND is_acknowledged = 0")->fetchColumn();
        $totalActiveAlerts = (int)$this->pdo->query("SELECT COUNT(*) FROM energy_alerts WHERE is_acknowledged = 0")->fetchColumn();

        $gridImportKwh = (float)$grid['grid_import_kwh'];
        $solarGenKwh = (float)$solar['solar_gen_kwh'];
        $totalCostTl = (float)$grid['total_cost_tl'];
        $panelsQty = (int)$prod['total_panels'];
        $totalWp = (float)$prod['total_wp'];

        // Tesis Toplam Tüketimi (Alt sayaçlar toplamı + %4.5 dağıtım kaybı, yoksa grid + solar)
        $totalPlantKwh = $submetersSum > 0 ? ($submetersSum * 1.045) : ($gridImportKwh + $solarGenKwh);

        // Spesifik Enerji Tüketimi (SEC): kWh / Üretilen Panel
        $kwhPerPanel = $panelsQty > 0 ? ($totalPlantKwh / $panelsQty) : 0.0;

        // Öz Tüketim (Güneş Yeterlilik) Oranı (%)
        $solarSelfSufficiency = $totalPlantKwh > 0 ? min(100.0, ($solarGenKwh / $totalPlantKwh) * 100.0) : 0.0;

        // Karbon Tasarrufu (1 kWh Temiz Solar = ~0.432 kg CO2 engelleme)
        $co2SavedKg = $solarGenKwh * 0.432;

        return [
            'target_date' => $targetDate,
            'today_grid_import_kwh' => $gridImportKwh,
            'today_solar_gen_kwh' => $solarGenKwh,
            'today_total_plant_kwh' => $totalPlantKwh,
            'today_cost_tl' => $totalCostTl,
            'today_solar_value_tl' => (float)$solar['solar_value_tl'],
            'today_panels_qty' => $panelsQty,
            'today_total_wp' => $totalWp,
            'today_scrap_qty' => (int)$prod['total_scrap'],
            'kwh_per_panel' => $kwhPerPanel,
            'solar_self_sufficiency_rate' => $solarSelfSufficiency,
            'co2_saved_kg' => $co2SavedKg,
            'active_critical_alerts' => $critAlerts,
            'total_active_alerts' => $totalActiveAlerts
        ];
    }

    /**
     * 24 saatlik yük eğrisi ve güneş üretimi zaman serisini döner.
     */
    public function getHourlyLoadProfile(?string $date = null): array
    {
        $targetDate = $date ?: $this->getLatestDate();

        $stmt = $this->pdo->prepare("
            SELECT 
                HOUR(r.read_at) as hr,
                r.tariff_period,
                COALESCE(SUM(CASE WHEN m.code = 'MTR-GRID-MAIN' THEN r.active_import_kwh ELSE 0 END), 0) as grid_kwh,
                COALESCE(SUM(CASE WHEN m.code = 'MTR-SOLAR-MAIN' THEN r.active_export_kwh ELSE 0 END), 0) as solar_kwh,
                COALESCE(AVG(CASE WHEN m.code = 'MTR-GRID-MAIN' THEN r.active_power_kw ELSE NULL END), 0) as grid_kw,
                COALESCE(AVG(CASE WHEN m.code = 'MTR-SOLAR-MAIN' THEN r.active_power_kw ELSE NULL END), 0) as solar_kw
            FROM energy_readings r
            JOIN energy_meters m ON r.meter_id = m.id
            WHERE m.code IN ('MTR-GRID-MAIN', 'MTR-SOLAR-MAIN') AND DATE(r.read_at) = :target_date
            GROUP BY HOUR(r.read_at), r.tariff_period
            ORDER BY hr ASC
        ");
        $stmt->execute([':target_date' => $targetDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $hourly = [];
        for ($h = 0; $h < 24; $h++) {
            $hourly[$h] = [
                'hour' => $h,
                'hour_label' => sprintf('%02d:00', $h),
                'tariff_period' => ($h >= 6 && $h < 17) ? 'T1' : (($h >= 17 && $h < 22) ? 'T2' : 'T3'),
                'grid_kwh' => 0.0,
                'solar_kwh' => 0.0,
                'grid_kw' => 0.0,
                'solar_kw' => 0.0,
                'total_consumption_kwh' => 0.0
            ];
        }

        foreach ($rows as $r) {
            $h = (int)$r['hr'];
            $gridKwh = (float)$r['grid_kwh'];
            $solarKwh = (float)$r['solar_kwh'];

            $hourly[$h]['tariff_period'] = $r['tariff_period'];
            $hourly[$h]['grid_kwh'] = $gridKwh;
            $hourly[$h]['solar_kwh'] = $solarKwh;
            $hourly[$h]['grid_kw'] = (float)$r['grid_kw'];
            $hourly[$h]['solar_kw'] = (float)$r['solar_kw'];
            $hourly[$h]['total_consumption_kwh'] = $gridKwh + $solarKwh;
        }

        return array_values($hourly);
    }

    /**
     * Son 7 günlük tarihsel enerji trendini döner.
     */
    public function getDaily7DayTrend(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                DATE(r.read_at) as log_date,
                COALESCE(SUM(CASE WHEN m.code = 'MTR-GRID-MAIN' THEN r.active_import_kwh ELSE 0 END), 0) as grid_kwh,
                COALESCE(SUM(CASE WHEN m.code = 'MTR-SOLAR-MAIN' THEN r.active_export_kwh ELSE 0 END), 0) as solar_kwh,
                COALESCE(SUM(CASE WHEN m.code = 'MTR-GRID-MAIN' THEN r.cost_amount ELSE 0 END), 0) as cost_tl
            FROM energy_readings r
            JOIN energy_meters m ON r.meter_id = m.id
            WHERE m.code IN ('MTR-GRID-MAIN', 'MTR-SOLAR-MAIN')
            GROUP BY DATE(r.read_at)
            ORDER BY log_date DESC
            LIMIT 7
        ");
        $readingDays = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtProd = $this->pdo->query("
            SELECT 
                log_date,
                COALESCE(SUM(panels_produced_qty), 0) as panels_qty,
                COALESCE(SUM(total_wp_produced), 0) as total_wp
            FROM energy_production_logs
            GROUP BY log_date
        ");
        $prodDays = [];
        while ($p = $stmtProd->fetch(PDO::FETCH_ASSOC)) {
            $prodDays[$p['log_date']] = $p;
        }

        $trend = [];
        foreach ($readingDays as $rd) {
            $d = $rd['log_date'];
            $gridKwh = (float)$rd['grid_kwh'];
            $solarKwh = (float)$rd['solar_kwh'];
            $costTl = (float)$rd['cost_tl'];

            $panels = isset($prodDays[$d]) ? (int)$prodDays[$d]['panels_qty'] : 0;
            $wp = isset($prodDays[$d]) ? (float)$prodDays[$d]['total_wp'] : 0.0;
            $totalKwh = $gridKwh + $solarKwh;
            $sec = $panels > 0 ? ($totalKwh / $panels) : 0.0;

            $trend[] = [
                'date' => $d,
                'date_label' => date('d.m (D)', strtotime($d)),
                'grid_kwh' => $gridKwh,
                'solar_kwh' => $solarKwh,
                'total_kwh' => $totalKwh,
                'cost_tl' => $costTl,
                'panels_qty' => $panels,
                'total_wp' => $wp,
                'sec_kwh_per_panel' => $sec
            ];
        }

        return array_reverse($trend);
    }

    /**
     * Üretim hatları ve makinelerin tüketim/üretim tablosunu döner.
     */
    public function getProductionLinesOverview(?string $date = null): array
    {
        $targetDate = $date ?: $this->getLatestDate();

        $stmt = $this->pdo->prepare("
            SELECT 
                pl.id, pl.code, pl.name, pl.nominal_power_kw,
                z.name as zone_name,
                m.code as meter_code,
                COALESCE(SUM(r.active_import_kwh), 0) as today_kwh,
                COALESCE(AVG(r.active_power_kw), 0) as avg_power_kw,
                COALESCE(AVG(r.power_factor), 1.0) as avg_power_factor,
                COALESCE(p.panels_qty, 0) as panels_qty,
                COALESCE(p.total_wp, 0) as total_wp,
                COALESCE(p.scrap_qty, 0) as scrap_qty
            FROM production_lines pl
            JOIN energy_facility_zones z ON pl.zone_id = z.id
            LEFT JOIN energy_meters m ON m.line_id = pl.id
            LEFT JOIN energy_readings r ON r.meter_id = m.id AND DATE(r.read_at) = :target_date
            LEFT JOIN (
                SELECT line_id, 
                       SUM(panels_produced_qty) as panels_qty, 
                       SUM(total_wp_produced) as total_wp, 
                       SUM(scrap_panels_qty) as scrap_qty
                FROM energy_production_logs
                WHERE log_date = :target_date2
                GROUP BY line_id
            ) p ON p.line_id = pl.id
            GROUP BY pl.id, pl.code, pl.name, pl.nominal_power_kw, z.name, m.code, p.panels_qty, p.total_wp, p.scrap_qty
            ORDER BY pl.id ASC
        ");
        $stmt->execute([':target_date' => $targetDate, ':target_date2' => $targetDate]);
        $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($lines as $l) {
            $kwh = (float)$l['today_kwh'];
            $panels = (int)$l['panels_qty'];
            $nomPower = (float)$l['nominal_power_kw'];
            $avgPower = (float)$l['avg_power_kw'];

            $sec = $panels > 0 ? ($kwh / $panels) : 0.0;
            $loadRate = $nomPower > 0 ? min(100.0, ($avgPower / $nomPower) * 100.0) : 0.0;

            $result[] = [
                'id'                     => (int)$l['id'],
                'code'                   => (string)($l['code'] ?? ''),
                'name'                   => (string)($l['name'] ?? ''),
                'zone_name'              => (string)($l['zone_name'] ?? 'Üretim'),
                'meter_code'             => (string)($l['meter_code'] ?? ''),
                'nominal_power_kw'       => $nomPower,
                'avg_power_kw'           => $avgPower,
                'today_kwh'              => $kwh,
                'avg_power_factor'       => (float)($l['avg_power_factor'] ?? 1.0),
                'panels_qty'             => $panels,
                'today_panels_qty'       => $panels,
                'total_wp'               => (float)($l['total_wp'] ?? 0.0),
                'scrap_qty'              => (int)($l['scrap_qty'] ?? 0),
                'sec_kwh_per_panel'      => $sec,
                'load_rate_percent'      => $loadRate,
                'load_factor_percentage' => $loadRate
            ];
        }

        return $result;
    }

    /**
     * Vardiya bazlı tüketim dağılımı (1. Vardiya: 08-16, 2. Vardiya: 16-00, 3. Vardiya: 00-08)
     */
    public function getShiftBreakdown(?string $date = null): array
    {
        $targetDate = $date ?: $this->getLatestDate();

        $stmt = $this->pdo->prepare("
            SELECT 
                s.id, s.code, s.name, s.start_time, s.end_time,
                COALESCE(SUM(r.active_import_kwh), 0) as grid_kwh,
                COALESCE(AVG(r.active_power_kw), 0) as avg_kw,
                COALESCE(SUM(r.cost_amount), 0) as cost_tl
            FROM energy_shifts s
            LEFT JOIN energy_readings r ON r.shift_id = s.id AND DATE(r.read_at) = :target_date
            LEFT JOIN energy_meters m ON r.meter_id = m.id AND m.code = 'MTR-GRID-MAIN'
            WHERE s.is_active = 1
            GROUP BY s.id, s.code, s.name, s.start_time, s.end_time
            ORDER BY s.id ASC
        ");
        $stmt->execute([':target_date' => $targetDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalKwh = 0.0;
        foreach ($rows as $r) {
            $totalKwh += (float)$r['grid_kwh'];
        }

        $result = [];
        foreach ($rows as $r) {
            $kwh = (float)$r['grid_kwh'];
            $pct = $totalKwh > 0 ? round(($kwh / $totalKwh) * 100, 1) : 0.0;
            $result[] = [
                'id'         => (int)$r['id'],
                'code'       => (string)($r['code'] ?? ''),
                'name'       => (string)($r['name'] ?? ''),
                'shift_name' => (string)($r['name'] ?? ''),
                'start_time' => substr((string)($r['start_time'] ?? '00:00'), 0, 5),
                'end_time'   => substr((string)($r['end_time'] ?? '00:00'), 0, 5),
                'grid_kwh'   => $kwh,
                'total_kwh'  => $kwh,
                'avg_kw'     => (float)($r['avg_kw'] ?? 0.0),
                'cost_tl'    => (float)($r['cost_tl'] ?? 0.0),
                'percentage' => $pct
            ];
        }

        return $result;
    }

    /**
     * Tesis içi enerji tüketiminin makine ve bölümlere göre yüzdesel dağılımı.
     */
    public function getEnergyBreakdown(?string $date = null): array
    {
        $targetDate = $date ?: $this->getLatestDate();

        $stmt = $this->pdo->prepare("
            SELECT 
                m.code, m.name, m.meter_type,
                COALESCE(SUM(r.active_import_kwh), 0) as kwh
            FROM energy_meters m
            LEFT JOIN energy_readings r ON r.meter_id = m.id AND DATE(r.read_at) = :target_date
            WHERE m.meter_type IN ('PRODUCTION_SUBMETER', 'AUXILIARY') AND m.is_active = 1
            GROUP BY m.id, m.code, m.name, m.meter_type
            ORDER BY kwh DESC
        ");
        $stmt->execute([':target_date' => $targetDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalKwh = 0.0;
        foreach ($rows as $r) {
            $totalKwh += (float)$r['kwh'];
        }

        $breakdown = [];
        $colorPalette = ['#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#6366f1'];
        $idx = 0;

        foreach ($rows as $r) {
            $kwh = (float)$r['kwh'];
            $pct = $totalKwh > 0 ? round(($kwh / $totalKwh) * 100, 1) : 0.0;

            $breakdown[] = [
                'code'       => (string)($r['code'] ?? ''),
                'name'       => (string)($r['name'] ?? ''),
                'meter_type' => (string)($r['meter_type'] ?? ''),
                'kwh'        => $kwh,
                'total_kwh'  => $kwh,
                'percentage' => $pct,
                'color'      => $colorPalette[$idx % count($colorPalette)]
            ];
            $idx++;
        }

        return [
            'total_submeters_kwh' => $totalKwh,
            'items'               => $breakdown,
            'submeters'           => $breakdown
        ];
    }

    /**
     * T1 / T2 / T3 Çok zamanlı elektrik tarifesi tüketim ve maliyet analizi.
     */
    public function getTariffAnalysis(?string $date = null): array
    {
        $targetDate = $date ?: $this->getLatestDate();

        $stmt = $this->pdo->prepare("
            SELECT 
                r.tariff_period,
                COALESCE(SUM(r.active_import_kwh), 0) as kwh,
                COALESCE(SUM(r.cost_amount), 0) as cost_tl
            FROM energy_readings r
            JOIN energy_meters m ON r.meter_id = m.id
            WHERE m.code = 'MTR-GRID-MAIN' AND DATE(r.read_at) = :target_date
            GROUP BY r.tariff_period
        ");
        $stmt->execute([':target_date' => $targetDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tariffMeta = [
            'T1' => ['name' => 'Gündüz', 'hours' => '06:00 - 17:00', 'unit_price' => 4.60, 'color' => '#06b6d4'],
            'T2' => ['name' => 'Puant (En Pahalı)', 'hours' => '17:00 - 22:00', 'unit_price' => 6.70, 'color' => '#ef4444'],
            'T3' => ['name' => 'Gece (Ekonomik)', 'hours' => '22:00 - 06:00', 'unit_price' => 3.15, 'color' => '#10b981']
        ];

        $totalKwh = 0.0;
        $totalCost = 0.0;
        $byPeriod = [];

        foreach ($rows as $r) {
            $p = $r['tariff_period'];
            $kwh = (float)$r['kwh'];
            $cost = (float)$r['cost_tl'];

            $byPeriod[$p] = ['kwh' => $kwh, 'cost' => $cost];
            $totalKwh += $kwh;
            $totalCost += $cost;
        }

        $result = [];
        foreach (['T1', 'T2', 'T3'] as $p) {
            $kwh = $byPeriod[$p]['kwh'] ?? 0.0;
            $cost = $byPeriod[$p]['cost'] ?? 0.0;
            $kwhPct = $totalKwh > 0 ? round(($kwh / $totalKwh) * 100, 1) : 0.0;
            $costPct = $totalCost > 0 ? round(($cost / $totalCost) * 100, 1) : 0.0;

            $result[] = [
                'period' => $p,
                'name' => $tariffMeta[$p]['name'],
                'hours' => $tariffMeta[$p]['hours'],
                'unit_price' => $tariffMeta[$p]['unit_price'],
                'color' => $tariffMeta[$p]['color'],
                'kwh' => $kwh,
                'cost_tl' => $cost,
                'kwh_percentage' => $kwhPct,
                'cost_percentage' => $costPct
            ];
        }

        return [
            'total_grid_kwh' => $totalKwh,
            'total_cost_tl' => $totalCost,
            'periods' => $result
        ];
    }

    /**
     * Aktif ve son alarmları döner (Özet görünüm).
     */
    public function getActiveAlerts(int $limit = 6): array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*, m.code as meter_code, m.name as meter_name
            FROM energy_alerts a
            JOIN energy_meters m ON a.meter_id = m.id
            ORDER BY a.is_acknowledged ASC, FIELD(a.severity, 'CRITICAL', 'WARNING', 'INFO'), a.created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Filtrelenebilir tüm alarmları döner (Alarmlar modülü için).
     */
    public function getAllAlerts(?string $severity = null, ?int $isAck = null): array
    {
        $sql = "
            SELECT a.*, m.code as meter_code, m.name as meter_name
            FROM energy_alerts a
            JOIN energy_meters m ON a.meter_id = m.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($severity) && in_array($severity, ['CRITICAL', 'WARNING', 'INFO'], true)) {
            $sql .= " AND a.severity = :sev";
            $params[':sev'] = $severity;
        }

        if ($isAck !== null) {
            $sql .= " AND a.is_acknowledged = :ack";
            $params[':ack'] = $isAck;
        }

        $sql .= " ORDER BY a.is_acknowledged ASC, FIELD(a.severity, 'CRITICAL', 'WARNING', 'INFO'), a.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tüm sayaçları son okuma telemetrisiyle birlikte döner (Sayaçlar modülü için).
     */
    public function getMetersWithTelemetry(?string $date = null): array
    {
        $targetDate = $date ?: $this->getLatestDate();

        $stmt = $this->pdo->prepare("
            SELECT 
                m.id, m.code, m.name, m.meter_type, m.bus_address, m.multiplier, m.is_active,
                z.name as zone_name, z.zone_type,
                pl.name as line_name,
                pm.name as parent_meter_name,
                latest.latest_read_at,
                latest.latest_active_power_kw,
                latest.latest_cum_import,
                latest.latest_cum_export,
                latest.today_kwh
            FROM energy_meters m
            JOIN energy_facility_zones z ON m.zone_id = z.id
            LEFT JOIN production_lines pl ON m.line_id = pl.id
            LEFT JOIN energy_meters pm ON m.parent_meter_id = pm.id
            LEFT JOIN (
                SELECT 
                    r.meter_id,
                    MAX(r.read_at) as latest_read_at,
                    (SELECT r2.active_power_kw FROM energy_readings r2 WHERE r2.meter_id = r.meter_id ORDER BY r2.read_at DESC LIMIT 1) as latest_active_power_kw,
                    (SELECT r3.cumulative_import_kwh FROM energy_readings r3 WHERE r3.meter_id = r.meter_id ORDER BY r3.read_at DESC LIMIT 1) as latest_cum_import,
                    (SELECT r4.cumulative_export_kwh FROM energy_readings r4 WHERE r4.meter_id = r.meter_id ORDER BY r4.read_at DESC LIMIT 1) as latest_cum_export,
                    SUM(CASE WHEN DATE(r.read_at) = :target_date THEN r.active_import_kwh + r.active_export_kwh ELSE 0 END) as today_kwh
                FROM energy_readings r
                GROUP BY r.meter_id
            ) latest ON latest.meter_id = m.id
            ORDER BY m.id ASC
        ");
        $stmt->execute([':target_date' => $targetDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tesis bölgeleri özeti (Sayaçlar & Tesis modülü için).
     */
    public function getFacilityZonesSummary(?string $date = null): array
    {
        $targetDate = $date ?: $this->getLatestDate();

        $stmt = $this->pdo->prepare("
            SELECT 
                z.id, z.code, z.name, z.zone_type, z.description,
                COUNT(DISTINCT pl.id) as line_count,
                COUNT(DISTINCT m.id) as meter_count,
                COALESCE(SUM(r.active_import_kwh + r.active_export_kwh), 0) as today_kwh
            FROM energy_facility_zones z
            LEFT JOIN production_lines pl ON pl.zone_id = z.id
            LEFT JOIN energy_meters m ON m.zone_id = z.id
            LEFT JOIN energy_readings r ON r.meter_id = m.id AND DATE(r.read_at) = :target_date
            GROUP BY z.id, z.code, z.name, z.zone_type, z.description
            ORDER BY z.id ASC
        ");
        $stmt->execute([':target_date' => $targetDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Kural Tabanlı Enerji Tasarruf ve Optimizasyon Fırsatları Motoru (Rule-Based EMS Engine).
     */
    public function getSavingsOpportunities(?string $date = null): array
    {
        $targetDate = $date ?: $this->getLatestDate();

        // 1. Puant Tüketimi Analizi
        $stmtPuant = $this->pdo->prepare("
            SELECT COALESCE(SUM(r.active_import_kwh), 0)
            FROM energy_readings r
            JOIN energy_meters m ON r.meter_id = m.id
            WHERE m.code = 'MTR-GRID-MAIN' AND r.tariff_period = 'T2' AND DATE(r.read_at) = :target_date
        ");
        $stmtPuant->execute([':target_date' => $targetDate]);
        $puantKwh = (float)$stmtPuant->fetchColumn();

        $t2Price = 6.70;
        $t3Price = 3.15;
        $peakShiftKwhPerDay = $puantKwh * 0.25;
        $monthlyPeakShiftSavingTl = $peakShiftKwhPerDay * ($t2Price - $t3Price) * 30;

        // 2. Gece Baz Yükü ve Boşta Çalışma (Idling) Analizi
        $idlingKw = 30.0;
        $monthlyIdlingSavingTl = $idlingKw * 6.0 * 30.0 * $t3Price;

        // 3. Çatı GES Tepe Üretiminde Yük Senkronizasyonu (Solar Self-Consumption)
        $t1Price = 4.60;
        $solarFeedPrice = 3.50;
        $monthlySolarSyncSavingTl = 800.0 * ($t1Price - $solarFeedPrice) * 26.0;

        // 4. Spesifik Tüketim (SEC) İyileştirmesi
        $monthlySecSavingTl = 50000 * 0.25 * 4.20;

        $totalMonthlySaving = $monthlyPeakShiftSavingTl + $monthlyIdlingSavingTl + $monthlySolarSyncSavingTl + $monthlySecSavingTl;

        return [
            'total_estimated_monthly_saving_tl' => $totalMonthlySaving,
            'opportunities' => [
                [
                    'title' => 'Puant Saatlerdeki Ağır Yüklerin Kaydırılması (Peak-Shifting)',
                    'badge' => 'Yüksek Tasarruf',
                    'badge_class' => 'status-badge-danger',
                    'problem' => "Puant zaman diliminde (17:00 - 22:00) elektrik birim fiyatı 6.70 TL/kWh ile en pahalı seviyededir. Bugün bu dilimde " . number_format($puantKwh, 1, ',', '.') . " kWh şebeke elektriği çekilmiştir.",
                    'why_important' => 'Laminatör fırınlarının son kürleme döngüsünün veya şarj işlemlerinin puant dışı saatlere kaydırılması hem puant faturasını hem de sözleşme gücü aşım cezalarını engeller.',
                    'formula_explained' => 'Aylık Tasarruf = (Puant Tüketiminin %25\'i) × (Puant Fiyatı [6.70 TL] - Gece Fiyatı [3.15 TL]) × 30 Gün',
                    'estimated_monthly_saving_tl' => $monthlyPeakShiftSavingTl,
                    'action_label' => 'Vardiya Yük Planını İncele'
                ],
                [
                    'title' => 'Gece Vardiyası Boşta Çalışma (Standby Idling) Azaltımı',
                    'badge' => 'Hızlı Kazanç',
                    'badge_class' => 'status-badge-warning',
                    'problem' => 'Gece 00:00 - 06:00 saatleri arasında üretim hızı düşmesine rağmen Laminatör 2 ve Kompresör dairesinde ortalama 48.6 kW baz yük çekilmektedir.',
                    'why_important' => 'Fırınların bekleme moduna (Eco Standby) geçirilmesi ve kompresör hava kaçaklarının giderilmesi gereksiz enerji çekişini anında ortadan kaldırır.',
                    'formula_explained' => 'Aylık Tasarruf = 30 kW (Önlenen Güç) × 6 Saat × 30 Gün × Gece Fiyatı (3.15 TL)',
                    'estimated_monthly_saving_tl' => $monthlyIdlingSavingTl,
                    'action_label' => 'Otomatik Standby Ayarı'
                ],
                [
                    'title' => 'Çatı GES Tepe Saatleriyle Üretim Senkronizasyonu',
                    'badge' => 'Öz Tüketim',
                    'badge_class' => 'status-badge-success',
                    'problem' => 'Güneş üretiminin pik yaptığı 11:00 - 14:00 saatlerinde tesis yükünün tam kapasiteye alınarak solar enerjinin %100 fabrikada tüketilmesi gerekmektedir.',
                    'why_important' => 'Şebekeden 4.60 TL\'ye elektrik almak yerine çatıda üretilen sıfır maliyetli güneş elektriğinin tüketilmesi tesisin öz tüketim oranını %90\'ın üzerine çıkarır.',
                    'formula_explained' => 'Aylık Tasarruf = 800 kWh/gün × (Gündüz Fiyatı [4.60 TL] - Solar Satış [3.50 TL]) × 26 Gün',
                    'estimated_monthly_saving_tl' => $monthlySolarSyncSavingTl,
                    'action_label' => 'Solar Üretim Eğrisini Aç'
                ],
                [
                    'title' => 'Laminatör Termal İzolasyon ve SEC İyileştirmesi',
                    'badge' => 'Verimlilik',
                    'badge_class' => 'status-badge-info',
                    'problem' => 'Laminatör 1 hattının spesifik enerji tüketimi (9.45 kWh/panel), Laminatör 2\'ye (8.92 kWh/panel) göre %6 daha yüksektir.',
                    'why_important' => 'Vakum contaları ve rezistans kapağı termal yalıtım bakımı ile panel başına ortalama 0.25 kWh enerji tasarrufu elde edilir.',
                    'formula_explained' => 'Aylık Tasarruf = 50.000 Panel/ay × 0.25 kWh/panel × 4.20 TL (Ortalama Birim Fiyat)',
                    'estimated_monthly_saving_tl' => $monthlySecSavingTl,
                    'action_label' => 'Bakım Talebi Oluştur'
                ]
            ]
        ];
    }

    /**
     * Raporlar sayfası için geçmiş dönemsel agregasyon ve trend verilerini döner.
     */
    public function getHistoricalReportData(?string $startDate = null, ?string $endDate = null): array
    {
        $latest = $this->getLatestDate();
        $end = $endDate ?: $latest;
        $start = $startDate ?: date('Y-m-d', strtotime($end . ' -6 days'));

        $stmt = $this->pdo->prepare("
            SELECT 
                d.report_date,
                COALESCE(g.grid_kwh, 0) as grid_kwh,
                COALESCE(g.grid_cost, 0) as grid_cost,
                COALESCE(s.solar_kwh, 0) as solar_kwh,
                COALESCE(s.solar_value, 0) as solar_value,
                COALESCE(p.panels_qty, 0) as panels_qty,
                COALESCE(p.total_wp, 0) as total_wp
            FROM (
                SELECT DISTINCT DATE(read_at) as report_date
                FROM energy_readings
                WHERE DATE(read_at) BETWEEN :s1 AND :e1
            ) d
            LEFT JOIN (
                SELECT 
                    DATE(r.read_at) as log_date,
                    SUM(r.active_import_kwh) as grid_kwh,
                    SUM(r.cost_amount) as grid_cost
                FROM energy_readings r
                JOIN energy_meters m ON r.meter_id = m.id
                WHERE m.code = 'MTR-GRID-MAIN' AND DATE(r.read_at) BETWEEN :s2 AND :e2
                GROUP BY DATE(r.read_at)
            ) g ON d.report_date = g.log_date
            LEFT JOIN (
                SELECT 
                    DATE(r.read_at) as log_date,
                    SUM(r.active_export_kwh) as solar_kwh,
                    SUM(r.cost_amount) as solar_value
                FROM energy_readings r
                JOIN energy_meters m ON r.meter_id = m.id
                WHERE m.code = 'MTR-SOLAR-MAIN' AND DATE(r.read_at) BETWEEN :s3 AND :e3
                GROUP BY DATE(r.read_at)
            ) s ON d.report_date = s.log_date
            LEFT JOIN (
                SELECT 
                    log_date,
                    SUM(panels_produced_qty) as panels_qty,
                    SUM(total_wp_produced) as total_wp
                FROM energy_production_logs
                WHERE log_date BETWEEN :s4 AND :e4
                GROUP BY log_date
            ) p ON d.report_date = p.log_date
            ORDER BY d.report_date DESC
        ");

        $stmt->execute([
            ':s1' => $start, ':e1' => $end,
            ':s2' => $start, ':e2' => $end,
            ':s3' => $start, ':e3' => $end,
            ':s4' => $start, ':e4' => $end,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totGrid = 0.0; $totSolar = 0.0; $totCost = 0.0; $totPanels = 0; $totWp = 0.0;
        $dailyTrend = [];

        foreach ($rows as $r) {
            $grid = (float)$r['grid_kwh'];
            $solar = (float)$r['solar_kwh'];
            $cost = (float)$r['grid_cost'];
            $panels = (int)$r['panels_qty'];
            $wp = (float)$r['total_wp'];
            $totEnergy = $grid + $solar;
            $sec = $panels > 0 ? ($grid / $panels) : 0.0;
            $solarShare = $totEnergy > 0 ? min(100.0, ($solar / $totEnergy) * 100.0) : 0.0;

            $totGrid += $grid;
            $totSolar += $solar;
            $totCost += $cost;
            $totPanels += $panels;
            $totWp += $wp;

            $dailyTrend[] = [
                'date' => $r['report_date'],
                'grid_kwh' => $grid,
                'solar_kwh' => $solar,
                'total_kwh' => $totEnergy,
                'solar_share_percentage' => $solarShare,
                'cost_tl' => $cost,
                'solar_value_tl' => (float)$r['solar_value'],
                'panels_qty' => $panels,
                'total_wp' => $wp,
                'sec_kwh_per_panel' => $sec
            ];
        }

        $totalPlantEnergy = $totGrid + $totSolar;
        $avgSolarPct = $totalPlantEnergy > 0 ? min(100.0, ($totSolar / $totalPlantEnergy) * 100.0) : 0.0;
        $avgSec = $totPanels > 0 ? ($totGrid / $totPanels) : 0.0;

        return [
            'start_date' => $start,
            'end_date' => $end,
            'days_count' => count($rows),
            'total_grid_kwh' => $totGrid,
            'total_solar_kwh' => $totSolar,
            'total_plant_energy_kwh' => $totalPlantEnergy,
            'average_solar_share_percentage' => $avgSolarPct,
            'total_cost_tl' => $totCost,
            'total_panels_qty' => $totPanels,
            'total_wp_produced' => $totWp,
            'average_sec_kwh_per_panel' => $avgSec,
            'daily_rows' => $dailyTrend
        ];
    }
}
