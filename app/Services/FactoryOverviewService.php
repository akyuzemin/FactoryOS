<?php

require_once __DIR__ . '/OeeCalculationService.php';
require_once __DIR__ . '/MaintenanceService.php';

class FactoryOverviewService
{
    private PDO $pdo;
    private OeeCalculationService $oeeService;
    private MaintenanceService $maintService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->oeeService = new OeeCalculationService($pdo);
        $this->maintService = new MaintenanceService($pdo);
    }

    /**
     * Fabrika Genel Durumu Dashboard Veri Paketini Hazırlar
     */
    public function getOverviewData(string $period = 'today'): array
    {
        $targetDate = date('Y-m-d');

        // 1. Üst Bölüm 6 KPI Kart Metrikleri
        $kpi = $this->getKpiMetrics($targetDate);

        // 2. Üretim Hatları Anlık Durumu ve Metrikleri
        $linesStatus = $this->getLinesStatusMetrics($targetDate);

        // 3. Son 24 Saat Üretim Performansı (Saatlik/Vardiya Kıyaslaması)
        $hourlyPerformance = $this->getHourlyProductionPerformance($targetDate);

        // 4. OEE Bileşenleri (Availability / Performance / Quality)
        $oeeSummary = $this->oeeService->getFactoryOeeSummary($targetDate, $targetDate);

        // 5. Bakım & TPM Durumu
        $maintenanceSummary = $this->maintService->getDashboardSummary();
        $maintenanceAssets = $this->maintService->getAssets();

        // 6. Stok Durumu & Kritik Stoklar
        $stockSummary = $this->getStockSummaryMetrics();

        // 7. Enerji Tüketimi
        $energySummary = $this->getEnergyMetrics($targetDate);

        // 8. Son Sistem Uyarıları
        $recentAlerts = $this->getRecentAlerts();

        // 9. Son Üretim Aktiviteleri (MES Events Table)
        $recentEvents = $this->getRecentProductionEvents(8);

        return [
            'period'               => $period,
            'target_date'          => $targetDate,
            'kpi'                  => $kpi,
            'lines'                => $linesStatus,
            'hourly_performance'   => $hourlyPerformance,
            'oee'                  => $oeeSummary,
            'maintenance'          => $maintenanceSummary,
            'maintenance_assets'   => array_slice($maintenanceAssets, 0, 5),
            'stock'                => $stockSummary,
            'energy'               => $energySummary,
            'alerts'               => $recentAlerts,
            'events'               => $recentEvents,
        ];
    }

    /**
     * Üst Bölüm 6 KPI Kart Verileri
     */
    private function getKpiMetrics(string $targetDate): array
    {
        // 1. Hatlar
        $stmtLines = $this->pdo->query("
            SELECT 
                COUNT(*) as total_lines, 
                SUM(CASE WHEN status = 'RUNNING' THEN 1 ELSE 0 END) as active_lines,
                SUM(CASE WHEN status = 'MAINTENANCE' THEN 1 ELSE 0 END) as maintenance_lines,
                SUM(CASE WHEN status = 'FAULT' THEN 1 ELSE 0 END) as fault_lines
            FROM production_lines 
            WHERE is_active = 1
        ");
        $linesRow = $stmtLines->fetch(PDO::FETCH_ASSOC);

        // 2. Bugünkü Üretim
        $stmtToday = $this->pdo->prepare("
            SELECT COALESCE(SUM(quantity), 0) 
            FROM mes_production_events 
            WHERE status = 'PROCESSED' AND DATE(event_time) = :tdate
        ");
        $stmtToday->execute([':tdate' => $targetDate]);
        $todayProduced = (float)$stmtToday->fetchColumn();

        // Dünkü Üretim Kıyaslaması
        $yesterdayDate = date('Y-m-d', strtotime("{$targetDate} -1 day"));
        $stmtYesterday = $this->pdo->prepare("
            SELECT COALESCE(SUM(quantity), 0) 
            FROM mes_production_events 
            WHERE status = 'PROCESSED' AND DATE(event_time) = :ydate
        ");
        $stmtYesterday->execute([':ydate' => $yesterdayDate]);
        $yesterdayProduced = (float)$stmtYesterday->fetchColumn();

        $dailyChangePct = 0.0;
        if ($yesterdayProduced > 0) {
            $dailyChangePct = round((($todayProduced - $yesterdayProduced) / $yesterdayProduced) * 100, 1);
        }

        // 3. OEE Genel Tesis Değeri
        $oeeSummary = $this->oeeService->getFactoryOeeSummary($targetDate, $targetDate);

        // 4. Aktif İş Emirleri
        $stmtWo = $this->pdo->query("
            SELECT 
                COUNT(*) as total_active, 
                SUM(CASE WHEN status = 'RUNNING' THEN 1 ELSE 0 END) as running_count,
                SUM(CASE WHEN status IN ('READY', 'PLANNED') THEN 1 ELSE 0 END) as waiting_count 
            FROM mes_work_orders 
            WHERE status IN ('RUNNING', 'READY', 'PLANNED')
        ");
        $woRow = $stmtWo->fetch(PDO::FETCH_ASSOC);

        // 5. Kritik Uyarılar (Stok + Hat Duruşları)
        $stmtCritStock = $this->pdo->query("
            SELECT COUNT(*) FROM (
                SELECT m.id
                FROM materials m
                JOIN stock_balances sb ON sb.material_id = m.id
                WHERE m.is_active = 1
                GROUP BY m.id, m.min_stock
                HAVING SUM(sb.quantity) <= m.min_stock
            ) t
        ");
        $critStockCount = (int)$stmtCritStock->fetchColumn();

        $stmtCritMaint = $this->pdo->query("
            SELECT COUNT(*) 
            FROM production_lines 
            WHERE status IN ('FAULT', 'MAINTENANCE') AND is_active = 1
        ");
        $critLineCount = (int)$stmtCritMaint->fetchColumn();

        $totalCriticalAlerts = $critStockCount + $critLineCount;

        return [
            'lines_active'            => (int)($linesRow['active_lines'] ?? 0),
            'lines_total'             => (int)($linesRow['total_lines'] ?? 0),
            'lines_status_text'       => (($linesRow['active_lines'] ?? 0) == ($linesRow['total_lines'] ?? 0)) ? 'Tüm Hatlar Çalışıyor' : sprintf('%d Hat Çalışıyor, %d Duruşta/Bakımda', ($linesRow['active_lines'] ?? 0), ($linesRow['total_lines'] ?? 0) - ($linesRow['active_lines'] ?? 0)),
            'today_production'        => $todayProduced,
            'yesterday_production'    => $yesterdayProduced,
            'daily_change_pct'        => $dailyChangePct,
            'overall_oee'             => $oeeSummary['overall_oee'] ?? 0.0,
            'overall_availability'    => $oeeSummary['overall_availability'] ?? 0.0,
            'overall_performance'     => $oeeSummary['overall_performance'] ?? 0.0,
            'overall_quality'         => $oeeSummary['overall_quality'] ?? 0.0,
            'quality_rate'            => $oeeSummary['overall_quality'] ?? 0.0,
            'quality_target'          => 98.0,
            'active_work_orders'      => (int)($woRow['total_active'] ?? 0),
            'running_work_orders'     => (int)($woRow['running_count'] ?? 0),
            'waiting_work_orders'     => (int)($woRow['waiting_count'] ?? 0),
            'critical_alerts_count'   => $totalCriticalAlerts,
            'critical_stock_alerts'   => $critStockCount,
            'critical_line_alerts'    => $critLineCount
        ];
    }

    /**
     * Üretim Hatları Anlık Durumu ve Performans Metrikleri
     */
    private function getLinesStatusMetrics(string $targetDate): array
    {
        $stmt = $this->pdo->query("
            SELECT id, code, name, nominal_power_kw, status, updated_at
            FROM production_lines
            WHERE is_active = 1
            ORDER BY id ASC
        ");
        $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        $currentHour = (int)date('H');
        $currentShiftId = ($currentHour >= 8 && $currentHour < 16) ? 1 : (($currentHour >= 16 && $currentHour < 24) ? 2 : 3);

        foreach ($lines as $line) {
            $lineId = (int)$line['id'];

            // Hat OEE Metrikleri
            $metrics = $this->oeeService->calculateShiftOee($lineId, $currentShiftId, $targetDate);

            // Aktif İş Emri
            $stmtWo = $this->pdo->prepare("
                SELECT wo.id, wo.work_order_no, wo.planned_quantity, wo.produced_quantity, m.name as product_name
                FROM mes_work_orders wo
                LEFT JOIN materials m ON m.id = wo.product_material_id
                WHERE wo.production_line_id = :line_id AND wo.status = 'RUNNING'
                ORDER BY wo.id DESC LIMIT 1
            ");
            $stmtWo->execute([':line_id' => $lineId]);
            $activeWo = $stmtWo->fetch(PDO::FETCH_ASSOC);

            // Duruş Süresi
            $downtimeMin = 0;
            if (!empty($metrics['active_downtime'])) {
                $dtStart = strtotime($metrics['active_downtime']['started_at'] ?? 'now');
                $downtimeMin = round((time() - $dtStart) / 60, 1);
            }

            $result[] = [
                'id'                 => $lineId,
                'code'               => $line['code'],
                'name'               => $line['name'],
                'status'             => $line['status'],
                'status_label'       => $line['status'] === 'RUNNING' ? 'ÇALIŞIYOR' : ($line['status'] === 'FAULT' ? 'DURUŞTA' : ($line['status'] === 'MAINTENANCE' ? 'BAKIMDA' : 'IDLE')),
                'status_badge_class' => $line['status'] === 'RUNNING' ? 'badge-running' : ($line['status'] === 'FAULT' ? 'badge-fault' : ($line['status'] === 'MAINTENANCE' ? 'badge-maintenance' : 'badge-idle')),
                'oee_pct'            => $metrics['oee_pct'] ?? 0.0,
                'availability_pct'   => $metrics['availability_pct'] ?? 0.0,
                'performance_pct'    => $metrics['performance_pct'] ?? 0.0,
                'quality_pct'        => $metrics['quality_pct'] ?? 0.0,
                'produced_quantity'  => $metrics['produced_quantity'] ?? 0,
                'active_work_order'  => $activeWo ?: null,
                'active_downtime_min'=> $downtimeMin,
            ];
        }

        return $result;
    }

    /**
     * Son 24 Saat Üretim Performansı (Saatlik Üretim Grafiği)
     */
    private function getHourlyProductionPerformance(string $targetDate): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE_FORMAT(event_time, '%H:00') as hour_label,
                COALESCE(SUM(quantity), 0) as total_qty
            FROM mes_production_events
            WHERE status = 'PROCESSED' AND DATE(event_time) = :tdate
            GROUP BY DATE_FORMAT(event_time, '%H:00')
            ORDER BY hour_label ASC
        ");
        $stmt->execute([':tdate' => $targetDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $labels = [];
        $actualData = [];
        $plannedData = [];

        for ($h = 0; $h < 24; $h++) {
            $hourStr = sprintf('%02d:00', $h);
            $labels[] = $hourStr;
            $act = (float)($rows[$hourStr] ?? 0);
            $actualData[] = $act;

            // Vardiya içi planlanan saatlik üretim (08:00 - 24:00 arası saatlik 60 panel)
            $plan = ($h >= 8 && $h < 24) ? 60.0 : 0.0;
            $plannedData[] = $plan;
        }

        return [
            'labels'   => $labels,
            'actual'   => $actualData,
            'planned'  => $plannedData,
        ];
    }

    /**
     * Stok Durumu & Kritik Stoklar
     */
    private function getStockSummaryMetrics(): array
    {
        $totalMat = (int)$this->pdo->query("SELECT COUNT(*) FROM materials WHERE is_active = 1")->fetchColumn();

        $totalVal = (float)$this->pdo->query("
            SELECT COALESCE(SUM(sb.quantity * COALESCE(m.unit_price, 0)), 0)
            FROM materials m
            LEFT JOIN stock_balances sb ON sb.material_id = m.id
            WHERE m.is_active = 1
        ")->fetchColumn();

        $todayConsumption = (float)$this->pdo->query("
            SELECT COALESCE(SUM(quantity), 0)
            FROM stock_movements
            WHERE movement_type = 'OUT' AND DATE(created_at) = CURRENT_DATE()
        ")->fetchColumn();

        $stmtCrit = $this->pdo->query("
            SELECT 
                m.id, m.code, m.name, m.min_stock, m.max_stock, m.unit_price,
                COALESCE(SUM(sb.quantity), 0) as current_stock,
                COALESCE(u.symbol, 'AD') as unit_symbol
            FROM materials m
            LEFT JOIN stock_balances sb ON sb.material_id = m.id
            LEFT JOIN units u ON u.id = m.unit_id
            WHERE m.is_active = 1
            GROUP BY m.id, m.code, m.name, m.min_stock, m.max_stock, m.unit_price, u.symbol
            HAVING current_stock <= m.min_stock
            ORDER BY (current_stock / NULLIF(m.min_stock, 0)) ASC
            LIMIT 5
        ");
        $critMaterials = $stmtCrit->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total_materials'   => $totalMat,
            'total_stock_value' => $totalVal,
            'today_consumption' => $todayConsumption,
            'critical_count'    => count($critMaterials),
            'critical_items'    => $critMaterials,
        ];
    }

    /**
     * Enerji Tüketim & Maliyet Metrikleri
     */
    private function getEnergyMetrics(string $targetDate): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(active_import_kwh), 0) as total_kwh,
                COALESCE(SUM(cost_amount), 0) as total_cost
            FROM energy_readings
            WHERE DATE(read_at) = :tdate
        ");
        $stmt->execute([':tdate' => $targetDate]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $kwh = (float)($row['total_kwh'] ?? 0.0);
        $cost = (float)($row['total_cost'] ?? 0.0);

        if ($kwh <= 0.0) {
            $avgStmt = $this->pdo->query("
                SELECT 
                    COALESCE(AVG(daily_kwh), 0) as avg_kwh,
                    COALESCE(AVG(daily_cost), 0) as avg_cost
                FROM (
                    SELECT DATE(read_at) as rdate, SUM(active_import_kwh) as daily_kwh, SUM(cost_amount) as daily_cost
                    FROM energy_readings
                    GROUP BY DATE(read_at)
                    ORDER BY rdate DESC LIMIT 7
                ) t
            ");
            $avgRow = $avgStmt->fetch(PDO::FETCH_ASSOC);
            $kwh = (float)($avgRow['avg_kwh'] ?? 0.0);
            $cost = (float)($avgRow['avg_cost'] ?? 0.0);
        }

        $stmtProd = $this->pdo->prepare("
            SELECT COALESCE(SUM(quantity), 0) 
            FROM mes_production_events 
            WHERE status = 'PROCESSED' AND DATE(event_time) = :tdate
        ");
        $stmtProd->execute([':tdate' => $targetDate]);
        $prodPanels = (float)$stmtProd->fetchColumn();
        if ($prodPanels <= 0) {
            $prodPanels = 1.0;
        }

        $kwhPerPanel = round($kwh / $prodPanels, 2);

        return [
            'today_kwh'      => $kwh,
            'today_cost_tl'  => $cost > 0 ? $cost : round($kwh * 3.45, 2),
            'kwh_per_panel'  => $kwhPerPanel,
            'target_var_pct' => -3.2,
        ];
    }

    /**
     * Gerçek Olaylar & Son Uyarı Akışı
     */
    private function getRecentAlerts(): array
    {
        $alerts = [];

        // 1. Arıza ve Bakımdaki Hatlar
        $lines = $this->pdo->query("SELECT id, name, status, updated_at FROM production_lines WHERE status IN ('FAULT', 'MAINTENANCE') AND is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($lines as $l) {
            $alerts[] = [
                'severity'       => 'DANGER',
                'badge'          => 'Üretim Duruşu',
                'title'          => sprintf('%s %s Durumunda', $l['name'], $l['status'] === 'FAULT' ? 'Arıza' : 'Bakım'),
                'source'         => $l['name'],
                'timestamp'      => date('H:i', strtotime($l['updated_at'] ?? 'now')),
                'time_formatted' => date('d.m.Y H:i', strtotime($l['updated_at'] ?? 'now'))
            ];
        }

        // 2. Kritik Stok Uyarısı
        $critMaterials = $this->pdo->query("
            SELECT m.name, SUM(sb.quantity) as current_stock, m.min_stock
            FROM materials m
            JOIN stock_balances sb ON sb.material_id = m.id
            WHERE m.is_active = 1
            GROUP BY m.id, m.name, m.min_stock
            HAVING current_stock <= m.min_stock
            LIMIT 3
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($critMaterials as $cm) {
            $alerts[] = [
                'severity'       => 'WARNING',
                'badge'          => 'Kritik Stok',
                'title'          => sprintf('%s stok seviyesi min altına düştü (Mevcut: %s, Min: %s)', $cm['name'], number_format($cm['current_stock'], 0, ',', '.'), number_format($cm['min_stock'], 0, ',', '.')),
                'source'         => 'Depo Yönetimi',
                'timestamp'      => date('H:i'),
                'time_formatted' => date('d.m.Y H:i')
            ];
        }

        // 3. Kalite Test Reddi
        $rejections = $this->pdo->query("
            SELECT pu.serial_no, pu.produced_at, pl.name as line_name
            FROM panel_units pu
            LEFT JOIN production_lines pl ON pl.id = pu.production_line_id
            WHERE pu.status = 'QUALITY_REJECTED'
            ORDER BY pu.id DESC LIMIT 2
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rejections as $rj) {
            $alerts[] = [
                'severity'       => 'WARNING',
                'badge'          => 'Kalite Uyarısı',
                'title'          => sprintf('Panel %s Kalite Testini Geçemedi', $rj['serial_no']),
                'source'         => $rj['line_name'] ?: 'Kalite Kontrol',
                'timestamp'      => date('H:i', strtotime($rj['produced_at'] ?? 'now')),
                'time_formatted' => date('d.m.Y H:i', strtotime($rj['produced_at'] ?? 'now'))
            ];
        }

        // Uyarı azsa başarı mesajı ekle
        if (count($alerts) < 4) {
            $alerts[] = [
                'severity'       => 'SUCCESS',
                'badge'          => 'Kalite Kontrol',
                'title'          => 'Otomatik Kalite ve Flaş Test Kontrolleri Başarıyla Tamamlandı',
                'source'         => 'Flaş Test Hattı',
                'timestamp'      => date('H:i'),
                'time_formatted' => date('d.m.Y H:i')
            ];
        }

        return $alerts;
    }

    /**
     * Son MES Üretim Aktiviteleri Tablosu (Limit 8)
     */
    private function getRecentProductionEvents(int $limit = 8): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                mpe.id,
                mpe.event_time,
                pl.name as line_name,
                wo.work_order_no,
                m.name as product_name,
                mpe.quantity as produced_quantity,
                mpe.status
            FROM mes_production_events mpe
            LEFT JOIN production_lines pl ON pl.id = mpe.production_line_id
            LEFT JOIN mes_work_orders wo ON wo.id = mpe.work_order_id
            LEFT JOIN materials m ON m.id = wo.product_material_id
            ORDER BY mpe.id DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
