<?php

class MesBomConsumptionService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get comprehensive BOM consumption summary and stock balance status for a work order.
     */
    public function getWorkOrderBomConsumption(int $workOrderId): array
    {
        // 1. Fetch work order details
        $stmtWo = $this->pdo->prepare("
            SELECT 
                wo.*,
                m.code AS product_code,
                m.name AS product_name,
                r.code AS recipe_code,
                r.name AS recipe_name,
                r.base_quantity AS recipe_base_qty,
                pl.code AS line_code,
                pl.name AS line_name
            FROM mes_work_orders wo
            JOIN materials m ON wo.product_material_id = m.id
            JOIN recipes r ON wo.recipe_id = r.id
            JOIN production_lines pl ON wo.production_line_id = pl.id
            WHERE wo.id = ?
        ");
        $stmtWo->execute([$workOrderId]);
        $wo = $stmtWo->fetch(PDO::FETCH_ASSOC);

        if (!$wo) {
            return [
                'success' => false,
                'message' => 'İş emri bulunamadı (ID: ' . $workOrderId . ')'
            ];
        }

        $recipeId = (int)$wo['recipe_id'];
        $baseQty = (float)($wo['recipe_base_qty'] > 0 ? $wo['recipe_base_qty'] : 1.0);
        $plannedQty = (float)$wo['planned_quantity'];
        $producedQty = (float)$wo['produced_quantity'];
        $remainingQty = max(0, $plannedQty - $producedQty);

        // 2. Fetch recipe items (BOM definition)
        $stmtItems = $this->pdo->prepare("
            SELECT 
                ri.id AS item_id,
                ri.material_id,
                m.code AS material_code,
                m.name AS material_name,
                c.name AS category_name,
                u.symbol AS unit_symbol,
                ri.quantity AS unit_quantity,
                ri.scrap_rate_pct,
                ri.is_critical,
                COALESCE(m.unit_price, 0.0000) AS unit_price,
                COALESCE(m.currency, 'TL') AS currency,
                (SELECT COALESCE(SUM(sb.quantity), 0) FROM stock_balances sb WHERE sb.material_id = m.id) AS current_stock
            FROM recipe_items ri
            JOIN materials m ON m.id = ri.material_id
            LEFT JOIN categories c ON c.id = m.category_id
            LEFT JOIN units u ON u.id = m.unit_id
            WHERE ri.recipe_id = ?
            ORDER BY ri.is_critical DESC, m.name ASC
        ");
        $stmtItems->execute([$recipeId]);
        $rawItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        // 3. Find all stock movement references tied to this work order
        $stmtRefs = $this->pdo->prepare("
            SELECT DISTINCT stock_movement_ref 
            FROM panel_units 
            WHERE work_order_id = ? AND stock_movement_ref IS NOT NULL AND stock_movement_ref != ''
            UNION
            SELECT DISTINCT stock_movement_ref 
            FROM mes_production_events 
            WHERE work_order_id = ? AND stock_movement_ref IS NOT NULL AND stock_movement_ref != ''
        ");
        $stmtRefs->execute([$workOrderId, $workOrderId]);
        $refs = $stmtRefs->fetchAll(PDO::FETCH_COLUMN);

        // Map actual consumed quantity and cost by material_id from stock_movements (OUT)
        $actualConsumedByMat = [];
        $actualCostByMat = [];
        $totalRealizedCost = 0.0;
        if (!empty($refs)) {
            $inPlaceholders = implode(',', array_fill(0, count($refs), '?'));
            $stmtConsumed = $this->pdo->prepare("
                SELECT material_id, SUM(quantity) AS total_consumed, SUM(total_price) AS total_cost 
                FROM stock_movements 
                WHERE movement_type = 'OUT' AND reference_no IN ({$inPlaceholders})
                GROUP BY material_id
            ");
            $stmtConsumed->execute($refs);
            while ($row = $stmtConsumed->fetch(PDO::FETCH_ASSOC)) {
                $matId = (int)$row['material_id'];
                $actualConsumedByMat[$matId] = (float)$row['total_consumed'];
                $actualCostByMat[$matId] = (float)$row['total_cost'];
                $totalRealizedCost += (float)$row['total_cost'];
            }
        }

        $items = [];
        $allSufficient = true;
        $shortageCount = 0;
        $totalTheoreticalUnitCost = 0.0;

        foreach ($rawItems as $it) {
            $matId = (int)$it['material_id'];
            $rawUnitQty = (float)$it['unit_quantity'];
            $scrapPct = (float)$it['scrap_rate_pct'];
            $effectiveUnitQty = round(($rawUnitQty / $baseQty) * (1 + ($scrapPct / 100)), 4);
            $unitPrice = (float)$it['unit_price'];
            $currency = $it['currency'] ?: 'TL';

            $unitCostShare = round($effectiveUnitQty * $unitPrice, 4);
            $totalTheoreticalUnitCost += $unitCostShare;

            $plannedTotal = round($plannedQty * $effectiveUnitQty, 4);
            $expectedProducedTotal = round($producedQty * $effectiveUnitQty, 4);
            $actualConsumed = $actualConsumedByMat[$matId] ?? $expectedProducedTotal;
            $actualItemCost = $actualCostByMat[$matId] ?? round($actualConsumed * $unitPrice, 4);
            $plannedTotalCost = round($plannedTotal * $unitPrice, 4);

            $remainingNeeded = max(0, round($plannedTotal - $actualConsumed, 4));
            $currentStock = (float)($it['current_stock'] ?? 0);
            $isSufficient = ($currentStock >= $remainingNeeded);

            if (!$isSufficient && (int)$it['is_critical'] === 1 && $remainingNeeded > 0) {
                $allSufficient = false;
                $shortageCount++;
            }

            $missingQty = max(0, round($remainingNeeded - $currentStock, 4));

            // Determine status label and styling badge
            if ($remainingNeeded <= 0) {
                $statusLabel = 'Tamamlandı';
                $statusBadge = ['bg' => '#f0fdf4', 'color' => '#166534', 'border' => '#bbf7d0', 'icon' => '✓'];
            } elseif ($isSufficient) {
                $statusLabel = 'Yeterli';
                $statusBadge = ['bg' => '#f0fdf4', 'color' => '#166534', 'border' => '#bbf7d0', 'icon' => '✓'];
            } elseif ($currentStock > 0) {
                $statusLabel = 'Kritik Stok';
                $statusBadge = ['bg' => '#fffbeb', 'color' => '#92400e', 'border' => '#fde68a', 'icon' => '⚠'];
            } else {
                $statusLabel = 'Yetersiz';
                $statusBadge = ['bg' => '#fef2f2', 'color' => '#991b1b', 'border' => '#fecaca', 'icon' => '🔴'];
            }

            $items[] = [
                'material_id'            => $matId,
                'material_code'          => $it['material_code'],
                'material_name'          => $it['material_name'],
                'category_name'          => $it['category_name'] ?: 'Hammadde',
                'unit_symbol'            => $it['unit_symbol'] ?: 'AD',
                'unit_quantity'          => $rawUnitQty,
                'scrap_rate_pct'         => $scrapPct,
                'effective_unit_qty'     => $effectiveUnitQty,
                'unit_price'             => $unitPrice,
                'currency'               => $currency,
                'unit_cost_share'        => $unitCostShare,
                'actual_consumed_cost'   => $actualItemCost,
                'planned_total_cost'     => $plannedTotalCost,
                'formatted_unit_price'   => number_format($unitPrice, 2, ',', '.') . ' ' . $currency,
                'formatted_unit_cost'    => number_format($unitCostShare, 2, ',', '.') . ' ' . $currency,
                'formatted_actual_cost'  => number_format($actualItemCost, 2, ',', '.') . ' ' . $currency,
                'formatted_planned_cost' => number_format($plannedTotalCost, 2, ',', '.') . ' ' . $currency,
                'planned_total_required' => $plannedTotal,
                'actual_consumed'        => $actualConsumed,
                'remaining_needed'       => $remainingNeeded,
                'current_stock'          => $currentStock,
                'missing_qty'            => $missingQty,
                'is_sufficient'          => $isSufficient,
                'is_critical'            => (int)$it['is_critical'] === 1,
                'status_label'           => $statusLabel,
                'status_badge'           => $statusBadge
            ];
        }

        // Percentage share
        foreach ($items as &$it) {
            $it['cost_share_pct'] = $totalTheoreticalUnitCost > 0 ? round(($it['unit_cost_share'] / $totalTheoreticalUnitCost) * 100, 2) : 0.0;
        }
        unset($it);

        if ($producedQty > 0 && $totalRealizedCost <= 0) {
            $totalRealizedCost = round($producedQty * $totalTheoreticalUnitCost, 4);
        }

        $estimatedTotalCost = round($totalRealizedCost + ($remainingQty * $totalTheoreticalUnitCost), 4);
        $remainingEstimatedCost = max(0, round($estimatedTotalCost - $totalRealizedCost, 4));

        return [
            'success'    => true,
            'work_order' => [
                'id'                => (int)$wo['id'],
                'work_order_no'     => $wo['work_order_no'],
                'product_code'      => $wo['product_code'],
                'product_name'      => $wo['product_name'],
                'recipe_code'       => $wo['recipe_code'],
                'recipe_name'       => $wo['recipe_name'],
                'line_code'         => $wo['line_code'],
                'line_name'         => $wo['line_name'],
                'planned_quantity'  => $plannedQty,
                'produced_quantity' => $producedQty,
                'remaining_quantity'=> $remainingQty,
                'status'            => $wo['status']
            ],
            'summary' => [
                'total_bom_items'         => count($items),
                'all_materials_sufficient'=> $allSufficient,
                'shortage_count'          => $shortageCount,
                'total_references_count'  => count($refs)
            ],
            'cost_summary' => [
                'unit_cost'                    => round($totalTheoreticalUnitCost, 4),
                'realized_cost'                => round($totalRealizedCost, 4),
                'estimated_total_cost'         => round($estimatedTotalCost, 4),
                'remaining_estimated_cost'     => round($remainingEstimatedCost, 4),
                'currency'                     => 'TL',
                'formatted_unit_cost'          => number_format($totalTheoreticalUnitCost, 2, ',', '.') . ' TL',
                'formatted_realized_cost'      => number_format($totalRealizedCost, 2, ',', '.') . ' TL',
                'formatted_estimated_total_cost'=> number_format($estimatedTotalCost, 2, ',', '.') . ' TL',
                'formatted_remaining_cost'     => number_format($remainingEstimatedCost, 2, ',', '.') . ' TL',
            ],
            'bom_items' => $items
        ];
    }

    /**
     * Get itemized BOM consumption for a specific production event / panel.
     */
    public function getEventBomConsumption(string|int $eventIdentifier): array
    {
        if (is_numeric($eventIdentifier) && (int)$eventIdentifier > 0) {
            $stmtEvt = $this->pdo->prepare("SELECT * FROM mes_production_events WHERE id = ?");
            $stmtEvt->execute([(int)$eventIdentifier]);
        } else {
            $stmtEvt = $this->pdo->prepare("SELECT * FROM mes_production_events WHERE event_id = ?");
            $stmtEvt->execute([(string)$eventIdentifier]);
        }
        $evt = $stmtEvt->fetch(PDO::FETCH_ASSOC);

        if (!$evt) {
            return [
                'success' => false,
                'message' => 'Üretim olayı bulunamadı: ' . $eventIdentifier
            ];
        }

        $eventId = $evt['event_id'];
        $workOrderId = (int)$evt['work_order_id'];
        $quantity = (float)$evt['quantity'];

        // Fetch Work Order & Recipe
        $stmtWo = $this->pdo->prepare("
            SELECT 
                wo.*,
                m.code AS product_code,
                m.name AS product_name,
                r.code AS recipe_code,
                r.name AS recipe_name,
                r.base_quantity AS recipe_base_qty,
                pl.name AS line_name
            FROM mes_work_orders wo
            JOIN materials m ON wo.product_material_id = m.id
            JOIN recipes r ON wo.recipe_id = r.id
            JOIN production_lines pl ON wo.production_line_id = pl.id
            WHERE wo.id = ?
        ");
        $stmtWo->execute([$workOrderId]);
        $wo = $stmtWo->fetch(PDO::FETCH_ASSOC);

        // Fetch Panel Units / Serial Numbers created for this event
        $stmtUnits = $this->pdo->prepare("
            SELECT serial_no, stock_movement_ref, produced_at, status 
            FROM panel_units 
            WHERE production_event_id = ? OR (work_order_id = ? AND stock_movement_ref = ?)
            ORDER BY id ASC
        ");
        $stockRef = $evt['stock_movement_ref'] ?? '';
        $stmtUnits->execute([$eventId, $workOrderId, $stockRef]);
        $units = $stmtUnits->fetchAll(PDO::FETCH_ASSOC);

        $serialNumbers = array_column($units, 'serial_no');
        $primaryStockRef = $stockRef ?: ($units[0]['stock_movement_ref'] ?? null);

        // If no stockRef in event, attempt to find from stock_movements
        if (empty($primaryStockRef)) {
            $stmtSMRef = $this->pdo->prepare("
                SELECT reference_no 
                FROM stock_movements 
                WHERE description LIKE ? 
                LIMIT 1
            ");
            $stmtSMRef->execute(['%' . $eventId . '%']);
            $primaryStockRef = $stmtSMRef->fetchColumn() ?: null;
        }

        // Fetch Snapshot Costs from stock_movements (OUT) for this reference
        $movementCostMap = [];
        $totalEventCost = 0.0;
        if (!empty($primaryStockRef)) {
            $stmtMovCosts = $this->pdo->prepare("
                SELECT material_id, quantity, unit_price, total_price, currency 
                FROM stock_movements 
                WHERE reference_no = ? AND movement_type = 'OUT'
            ");
            $stmtMovCosts->execute([$primaryStockRef]);
            while ($mRow = $stmtMovCosts->fetch(PDO::FETCH_ASSOC)) {
                $mId = (int)$mRow['material_id'];
                $movementCostMap[$mId] = [
                    'unit_price'  => (float)$mRow['unit_price'],
                    'total_price' => (float)$mRow['total_price'],
                    'currency'    => $mRow['currency'] ?: 'TL'
                ];
                $totalEventCost += (float)$mRow['total_price'];
            }
        }

        // Fetch Recipe Items
        $recipeId = (int)$wo['recipe_id'];
        $baseQty = (float)($wo['recipe_base_qty'] > 0 ? $wo['recipe_base_qty'] : 1.0);

        $stmtItems = $this->pdo->prepare("
            SELECT 
                ri.material_id,
                m.code AS material_code,
                m.name AS material_name,
                u.symbol AS unit_symbol,
                ri.quantity AS unit_quantity,
                ri.scrap_rate_pct,
                ri.is_critical,
                COALESCE(m.unit_price, 0.0000) AS fallback_unit_price,
                COALESCE(m.currency, 'TL') AS currency
            FROM recipe_items ri
            JOIN materials m ON m.id = ri.material_id
            LEFT JOIN units u ON u.id = m.unit_id
            WHERE ri.recipe_id = ?
            ORDER BY ri.is_critical DESC, m.name ASC
        ");
        $stmtItems->execute([$recipeId]);
        $rawItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        $consumedItems = [];
        foreach ($rawItems as $it) {
            $matId = (int)$it['material_id'];
            $rawUnitQty = (float)$it['unit_quantity'];
            $scrapPct = (float)$it['scrap_rate_pct'];
            $qtyPerPanel = round(($rawUnitQty / $baseQty) * (1 + ($scrapPct / 100)), 4);
            $totalDeducted = round($qtyPerPanel * $quantity, 4);

            $snapshotUnitPrice = isset($movementCostMap[$matId]) ? $movementCostMap[$matId]['unit_price'] : (float)$it['fallback_unit_price'];
            $snapshotTotalPrice = isset($movementCostMap[$matId]) ? $movementCostMap[$matId]['total_price'] : round($totalDeducted * $snapshotUnitPrice, 4);
            $itemCurrency = isset($movementCostMap[$matId]) ? $movementCostMap[$matId]['currency'] : ($it['currency'] ?: 'TL');

            if ($totalEventCost <= 0) {
                $totalEventCost += $snapshotTotalPrice;
            }

            $consumedItems[] = [
                'material_id'          => $matId,
                'material_code'        => $it['material_code'],
                'material_name'        => $it['material_name'],
                'unit_symbol'          => $it['unit_symbol'] ?: 'AD',
                'quantity_per_panel'   => $qtyPerPanel,
                'consumed_quantity'    => $totalDeducted,
                'unit_price'           => $snapshotUnitPrice,
                'total_price'          => $snapshotTotalPrice,
                'currency'             => $itemCurrency,
                'formatted_unit_price' => number_format($snapshotUnitPrice, 2, ',', '.') . ' ' . $itemCurrency,
                'formatted_total_price'=> number_format($snapshotTotalPrice, 2, ',', '.') . ' ' . $itemCurrency,
                'formatted'            => sprintf('-%s %s', number_format($totalDeducted, ($totalDeducted == (int)$totalDeducted ? 0 : 2), ',', '.'), $it['unit_symbol'] ?: 'AD')
            ];
        }

        if ($totalEventCost <= 0 && !empty($evt['total_cost'])) {
            $totalEventCost = (float)$evt['total_cost'];
        }

        $unitCost = $quantity > 0 ? round($totalEventCost / $quantity, 4) : 0.0;

        return [
            'success'            => true,
            'event_id'           => $eventId,
            'work_order_id'      => $workOrderId,
            'work_order_no'      => $wo['work_order_no'] ?? '',
            'product_name'       => $wo['product_name'] ?? '',
            'product_code'       => $wo['product_code'] ?? '',
            'line_name'          => $wo['line_name'] ?? '',
            'recipe_name'        => $wo['recipe_name'] ?? '',
            'quantity'           => $quantity,
            'unit_cost'          => $unitCost,
            'total_cost'         => round($totalEventCost, 4),
            'currency'           => $evt['cost_currency'] ?: 'TL',
            'formatted_unit_cost'=> number_format($unitCost, 2, ',', '.') . ' ' . ($evt['cost_currency'] ?: 'TL'),
            'formatted_total_cost'=> number_format($totalEventCost, 2, ',', '.') . ' ' . ($evt['cost_currency'] ?: 'TL'),
            'event_time'         => $evt['event_time'] ?? $evt['created_at'],
            'status'             => $evt['status'],
            'stock_movement_ref' => $primaryStockRef,
            'serial_numbers'     => $serialNumbers,
            'primary_serial_no'  => $serialNumbers[0] ?? null,
            'consumed_items'     => $consumedItems,
            'output_item'        => [
                'product_code' => $wo['product_code'] ?? '',
                'product_name' => $wo['product_name'] ?? '',
                'produced_qty' => $quantity,
                'formatted'    => sprintf('+%.0f AD', $quantity)
            ]
        ];
    }

    /**
     * Get complete traceability chain for a work order.
     */
    public function getWorkOrderTraceabilityChain(int $workOrderId): array
    {
        $bomSummary = $this->getWorkOrderBomConsumption($workOrderId);
        if (!$bomSummary['success']) {
            return $bomSummary;
        }

        // Fetch all events
        $stmtEvts = $this->pdo->prepare("
            SELECT * 
            FROM mes_production_events 
            WHERE work_order_id = ? 
            ORDER BY id ASC
        ");
        $stmtEvts->execute([$workOrderId]);
        $events = $stmtEvts->fetchAll(PDO::FETCH_ASSOC);

        $eventChains = [];
        foreach ($events as $idx => $ev) {
            $eventDetails = $this->getEventBomConsumption($ev['event_id']);
            $eventDetails['panel_number'] = $idx + 1;
            $eventChains[] = $eventDetails;
        }

        return [
            'success'      => true,
            'work_order'   => $bomSummary['work_order'],
            'bom_items'    => $bomSummary['bom_items'],
            'summary'      => $bomSummary['summary'],
            'event_chains' => $eventChains
        ];
    }
}
