<?php

require_once __DIR__ . '/../Services/EnergyProductionLinkService.php';

class AdminDashboard
{
    private PDO $pdo;
    private EnergyProductionLinkService $energyLinkService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->energyLinkService = new EnergyProductionLinkService($pdo);
    }

    /**
     * Resolves date range filter bounds (today, this_week, this_month, custom).
     */
    public function resolveDateRange(string $range = 'today', ?string $startDate = null, ?string $endDate = null): array
    {
        $today = date('Y-m-d');
        if ($range === 'this_week') {
            $start = date('Y-m-d', strtotime('monday this week'));
            $end = $today;
            $label = 'Bu Hafta (' . date('d.m.Y', strtotime($start)) . ' - ' . date('d.m.Y', strtotime($end)) . ')';
        } elseif ($range === 'this_month') {
            $start = date('Y-m-01');
            $end = $today;
            $label = 'Bu Ay (' . date('01.m.Y') . ' - ' . date('d.m.Y', strtotime($end)) . ')';
        } elseif ($range === 'custom' && !empty($startDate) && !empty($endDate)) {
            $start = $startDate;
            $end = $endDate;
            $label = date('d.m.Y', strtotime($start)) . ' - ' . date('d.m.Y', strtotime($end));
        } else {
            // Default: today
            $start = $today;
            $end = $today;
            $label = 'Bugün (' . date('d.m.Y') . ')';
        }

        return [
            'start_date' => $start,
            'end_date'   => $end,
            'range'      => $range,
            'label'      => $label
        ];
    }

    /**
     * Executive Overview KPIs.
     */
    public function getExecutiveKpis(string $startDate, string $endDate): array
    {
        // 1. Production Quantity & Wp
        $stmtProd = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_panels,
                COALESCE(SUM(unit_cost), 0) as total_bom_cost,
                COALESCE(SUM(CAST(SUBSTRING(serial_no, 3, LOCATE('W', serial_no) - 3) AS UNSIGNED)), 0) as total_wp
            FROM panel_units
            WHERE DATE(produced_at) BETWEEN :start_date AND :end_date
        ");
        $stmtProd->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        $prodData = $stmtProd->fetch(PDO::FETCH_ASSOC) ?: ['total_panels' => 0, 'total_bom_cost' => 0, 'total_wp' => 0];

        $activeWoCount = (int)$this->pdo->query("
            SELECT COUNT(*) FROM mes_work_orders WHERE status IN ('RUNNING', 'READY')
        ")->fetchColumn();

        // 3. Work Order Target Realization Rate
        $stmtWo = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(planned_quantity), 0) as total_planned,
                COALESCE(SUM(produced_quantity), 0) as total_produced
            FROM mes_work_orders
            WHERE DATE(created_at) BETWEEN :start_date AND :end_date
        ");
        $stmtWo->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        $woData = $stmtWo->fetch(PDO::FETCH_ASSOC);
        $plannedQty = (float)($woData['total_planned'] ?? 0);
        $producedQty = (float)($woData['total_produced'] ?? 0);
        $targetRealizationRate = ($plannedQty > 0) ? min(100.0, round(($producedQty / $plannedQty) * 100, 1)) : 100.0;

        // 4. Raw Material Consumption Amount & Cost
        $stmtMatCost = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(quantity), 0) as total_qty,
                COALESCE(SUM(total_price), 0) as total_cost
            FROM stock_movements
            WHERE movement_type = 'OUT' AND reference_no LIKE 'PRD-%' AND DATE(created_at) BETWEEN :start_date AND :end_date
        ");
        $stmtMatCost->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        $matData = $stmtMatCost->fetch(PDO::FETCH_ASSOC);
        $totalMaterialCost = (float)($matData['total_cost'] ?? 0);
        $totalMaterialQty = (float)($matData['total_qty'] ?? 0);

        // 5. Energy Metrics via EnergyProductionLinkService
        $energyData = $this->energyLinkService->getLineSpecificEnergyConsumption($endDate, 'custom', $startDate, $endDate);
        $totalEnergyKwh = (float)$energyData['total_plant_kwh'];
        $totalEnergyCostTl = (float)$energyData['total_plant_cost_tl'];
        $secKwhPerPanel = (float)$energyData['plant_sec_kwh_per_panel'];

        $tariffData = $this->energyLinkService->getDynamicTariffAnalysis($endDate, 'custom', $startDate, $endDate);
        $peakKwh = (float)($tariffData['periods']['T2']['kwh'] ?? 0);
        $peakCost = (float)($tariffData['periods']['T2']['cost_tl'] ?? 0);
        $totalTariffCost = (float)($tariffData['total_cost_tl'] ?? 1);
        $peakCostSharePct = $totalTariffCost > 0 ? round(($peakCost / $totalTariffCost) * 100, 1) : 0;

        // 6. Total Manufacturing Cost (BOM Snapshot + Energy SEC Cost)
        $totalBomCost = (float)$prodData['total_bom_cost'];
        $totalManufacturingCost = $totalBomCost + $totalEnergyCostTl;

        // 7. Quality Inspection Metrics
        $qualityMetrics = $this->getQualityMetrics($startDate, $endDate);

        // 8. Shipment Metrics
        $shipmentMetrics = $this->getShipmentMetrics($startDate, $endDate);

        // 9. Active Energy Alerts Count
        $activeAlertsCount = (int)$this->pdo->query("
            SELECT COUNT(*) FROM energy_alerts WHERE is_acknowledged = 0
        ")->fetchColumn();

        return [
            'total_produced_panels'     => (int)$prodData['total_panels'],
            'total_wp'                  => (float)$prodData['total_wp'],
            'active_work_orders_count'  => $activeWoCount,
            'target_realization_rate'   => $targetRealizationRate,
            'total_material_cost_tl'    => $totalMaterialCost,
            'total_material_qty'        => $totalMaterialQty,
            'total_bom_snapshot_cost_tl'=> $totalBomCost,
            'total_energy_cost_tl'      => $totalEnergyCostTl,
            'total_manufacturing_cost_tl'=> $totalManufacturingCost,
            'quality_total_inspected'   => $qualityMetrics['total_inspected'],
            'quality_approved_count'    => $qualityMetrics['approved'],
            'quality_rejected_count'    => $qualityMetrics['rejected'],
            'quality_pending_count'     => $qualityMetrics['pending'],
            'quality_pass_rate_pct'     => $qualityMetrics['pass_rate_pct'],
            'quality_reject_rate_pct'   => $qualityMetrics['reject_rate_pct'],
            'total_shipped_panels'      => $shipmentMetrics['shipped_panels'],
            'completed_shipments_count' => $shipmentMetrics['completed_shipments'],
            'pending_shipments_count'   => $shipmentMetrics['pending_shipments'],
            'total_energy_kwh'          => $totalEnergyKwh,
            'sec_kwh_per_panel'         => $secKwhPerPanel,
            'energy_cost_per_panel'     => $prodData['total_panels'] > 0 ? round($totalEnergyCostTl / $prodData['total_panels'], 2) : 0,
            'peak_kwh'                  => $peakKwh,
            'peak_cost_share_pct'       => $peakCostSharePct,
            'active_alerts_count'       => $activeAlertsCount
        ];
    }

    /**
     * Daily production trend within selected date range.
     */
    public function getProductionTrend(string $startDate, string $endDate): array
    {
        // Actual Production
        $stmtAct = $this->pdo->prepare("
            SELECT 
                DATE(produced_at) as p_date,
                COUNT(*) as qty
            FROM panel_units
            WHERE DATE(produced_at) BETWEEN :start_date AND :end_date
            GROUP BY DATE(produced_at)
        ");
        $stmtAct->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        $actuals = $stmtAct->fetchAll(PDO::FETCH_KEY_PAIR);

        // Planned Production
        $stmtPlan = $this->pdo->prepare("
            SELECT 
                DATE(created_at) as p_date,
                SUM(planned_quantity) as planned
            FROM mes_work_orders
            WHERE DATE(created_at) BETWEEN :start_date AND :end_date
            GROUP BY DATE(created_at)
        ");
        $stmtPlan->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        $planned = $stmtPlan->fetchAll(PDO::FETCH_KEY_PAIR);

        $dates = array_unique(array_merge(array_keys($actuals), array_keys($planned)));
        sort($dates);

        $trend = [];
        foreach ($dates as $d) {
            $trend[] = [
                'p_date' => $d,
                'qty' => (int)($actuals[$d] ?? 0),
                'planned' => (int)($planned[$d] ?? 0)
            ];
        }
        return $trend;
    }

    /**
     * Top 5 raw materials consumed in production.
     */
    public function getTopMaterialsConsumed(string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                m.id as material_id,
                m.code as material_code,
                m.name as material_name,
                u.symbol as unit_symbol,
                SUM(sm.quantity) as total_qty,
                SUM(sm.total_price) as total_cost_tl
            FROM stock_movements sm
            JOIN materials m ON sm.material_id = m.id
            JOIN units u ON m.unit_id = u.id
            WHERE sm.movement_type = 'OUT' AND DATE(sm.created_at) BETWEEN :start_date AND :end_date
            GROUP BY m.id, m.code, m.name, u.symbol
            ORDER BY total_cost_tl DESC
            LIMIT 5
        ");
        $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * Quality Inspection statistics.
     */
    public function getQualityMetrics(string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                status,
                COUNT(*) as qty
            FROM panel_units
            WHERE DATE(produced_at) BETWEEN :start_date AND :end_date
            GROUP BY status
        ");
        $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $approved = (int)($rows['QUALITY_APPROVED'] ?? 0);
        $rejected = (int)($rows['QUALITY_REJECTED'] ?? 0);
        $pending = (int)($rows['QUALITY_PENDING'] ?? 0) + (int)($rows['IN_STOCK'] ?? 0);
        $totalInspected = $approved + $rejected;
        $totalAll = $totalInspected + $pending;

        $passRatePct = ($totalInspected > 0) ? round(($approved / $totalInspected) * 100, 1) : 100.0;
        $rejectRatePct = ($totalInspected > 0) ? round(($rejected / $totalInspected) * 100, 1) : 0.0;

        return [
            'approved'        => $approved,
            'rejected'        => $rejected,
            'pending'         => $pending,
            'total_inspected' => $totalInspected,
            'total_all'       => $totalAll,
            'pass_rate_pct'   => $passRatePct,
            'reject_rate_pct' => $rejectRatePct
        ];
    }

    /**
     * Shipment statistics.
     */
    public function getShipmentMetrics(string $startDate, string $endDate): array
    {
        $stmtShipped = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM panel_units 
            WHERE status = 'SHIPPED' AND DATE(updated_at) BETWEEN :start_date AND :end_date
        ");
        $stmtShipped->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        $shippedPanels = (int)$stmtShipped->fetchColumn();

        $completedShipments = (int)$this->pdo->query("
            SELECT COUNT(*) FROM shipments WHERE status = 'COMPLETED'
        ")->fetchColumn();

        $pendingShipments = (int)$this->pdo->query("
            SELECT COUNT(*) FROM shipments WHERE status = 'DRAFT'
        ")->fetchColumn();

        return [
            'shipped_panels'     => $shippedPanels,
            'completed_shipments'=> $completedShipments,
            'pending_shipments'  => $pendingShipments
        ];
    }

    /**
     * Production Line Performance Comparison Matrix.
     */
    public function getLineComparisonMatrix(string $startDate, string $endDate): array
    {
        $lines = $this->pdo->query("SELECT id, code, name FROM production_lines ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        $energyData = $this->energyLinkService->getLineSpecificEnergyConsumption($endDate, 'custom', $startDate, $endDate);
        $lineEnergyMap = [];
        foreach ($energyData['lines'] as $el) {
            $lineEnergyMap[$el['line_id']] = $el;
        }

        $matrix = [];
        foreach ($lines as $line) {
            $lineId = (int)$line['id'];

            // Production Qty for this line
            $stmtProd = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM panel_units pu
                JOIN mes_work_orders wo ON pu.work_order_id = wo.id
                WHERE wo.production_line_id = :line_id AND DATE(pu.produced_at) BETWEEN :start_date AND :end_date
            ");
            $stmtProd->execute([':line_id' => $lineId, ':start_date' => $startDate, ':end_date' => $endDate]);
            $prodCount = (int)$stmtProd->fetchColumn();

            // Quality Approved for this line
            $stmtApp = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM panel_units pu
                JOIN mes_work_orders wo ON pu.work_order_id = wo.id
                WHERE wo.production_line_id = :line_id AND pu.status = 'QUALITY_APPROVED' AND DATE(pu.produced_at) BETWEEN :start_date AND :end_date
            ");
            $stmtApp->execute([':line_id' => $lineId, ':start_date' => $startDate, ':end_date' => $endDate]);
            $approvedCount = (int)$stmtApp->fetchColumn();

            $qualityPassRate = ($prodCount > 0) ? round(($approvedCount / $prodCount) * 100, 1) : 100.0;

            // Target Realization for this line
            $stmtTarget = $this->pdo->prepare("
                SELECT SUM(planned_quantity) as planned, SUM(produced_quantity) as produced
                FROM mes_work_orders
                WHERE production_line_id = :line_id AND DATE(created_at) BETWEEN :start_date AND :end_date
            ");
            $stmtTarget->execute([':line_id' => $lineId, ':start_date' => $startDate, ':end_date' => $endDate]);
            $targetData = $stmtTarget->fetch(PDO::FETCH_ASSOC);
            $plannedQty = (int)($targetData['planned'] ?? 0);
            $realizationRate = ($plannedQty > 0) ? round(($targetData['produced'] / $plannedQty) * 100, 1) : 100.0;

            $eng = $lineEnergyMap[$lineId] ?? [
                'total_kwh' => 0.0,
                'total_cost_tl' => 0.0,
                'sec_kwh_per_panel' => 0.0,
                'unit_energy_cost_tl_per_panel' => 0.0
            ];

            $matrix[] = [
                'line_id'                   => $lineId,
                'line_code'                 => $line['code'],
                'line_name'                 => $line['name'],
                'produced_panels'           => $prodCount,
                'realization_rate_pct'      => $realizationRate,
                'quality_pass_rate_pct'     => $qualityPassRate,
                'total_kwh'                 => (float)$eng['total_kwh'],
                'sec_kwh_per_panel'         => $eng['sec_kwh_per_panel'],
                'unit_energy_cost_tl_panel' => $eng['unit_energy_cost_tl_per_panel']
            ];
        }

        return $matrix;
    }

    /**
     * Critical stock alerts for materials below minimum stock level.
     */
    public function getCriticalStocks(int $limit = 5): array
    {
        $limit = max(1, $limit);
        $sql = "
            SELECT 
                m.id AS material_id,
                m.code AS material_code,
                m.name AS material_name,
                c.name AS category_name,
                u.symbol AS unit_symbol,
                COALESCE(SUM(sb.quantity), 0) AS current_stock,
                m.min_stock,
                GREATEST(0, m.min_stock - COALESCE(SUM(sb.quantity), 0)) AS deficit
            FROM materials m
            INNER JOIN categories c ON m.category_id = c.id
            INNER JOIN units u ON m.unit_id = u.id
            LEFT JOIN stock_balances sb ON m.id = sb.material_id
            WHERE m.is_active = 1 AND m.min_stock > 0
            GROUP BY m.id, m.code, m.name, c.name, u.symbol, m.min_stock
            HAVING current_stock <= m.min_stock
            ORDER BY deficit DESC, m.name ASC
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
