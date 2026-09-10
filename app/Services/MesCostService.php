<?php

class MesCostService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Reçetenin teorik birim üretim maliyetini ve BOM kalem bazlı maliyet dağılımını hesaplar.
     */
    public function getRecipeTheoreticalCost(int $recipeId): array
    {
        $stmtRecipe = $this->pdo->prepare("
            SELECT r.*, m.name AS output_product_name, m.code AS output_product_code, u.symbol AS output_unit
            FROM recipes r
            JOIN materials m ON r.output_material_id = m.id
            LEFT JOIN units u ON m.unit_id = u.id
            WHERE r.id = ?
        ");
        $stmtRecipe->execute([$recipeId]);
        $recipe = $stmtRecipe->fetch(PDO::FETCH_ASSOC);

        if (!$recipe) {
            return [
                'success' => false,
                'message' => 'Reçete bulunamadı (ID: ' . $recipeId . ')'
            ];
        }

        $baseQty = (float)($recipe['base_quantity'] > 0 ? $recipe['base_quantity'] : 1.0);

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
                COALESCE(m.currency, 'TL') AS currency
            FROM recipe_items ri
            JOIN materials m ON ri.material_id = m.id
            LEFT JOIN categories c ON m.category_id = c.id
            LEFT JOIN units u ON m.unit_id = u.id
            WHERE ri.recipe_id = ?
            ORDER BY ri.is_critical DESC, m.name ASC
        ");
        $stmtItems->execute([$recipeId]);
        $rawItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        $totalUnitCost = 0.0;

        foreach ($rawItems as $it) {
            $rawQty = (float)$it['unit_quantity'];
            $scrapPct = (float)$it['scrap_rate_pct'];
            $effectiveQty = round(($rawQty / $baseQty) * (1 + ($scrapPct / 100)), 4);
            $unitPrice = (float)$it['unit_price'];
            $itemCost = round($effectiveQty * $unitPrice, 4);

            $totalUnitCost += $itemCost;

            $items[] = [
                'item_id'              => (int)$it['item_id'],
                'material_id'          => (int)$it['material_id'],
                'material_code'        => $it['material_code'],
                'material_name'        => $it['material_name'],
                'category_name'        => $it['category_name'] ?: 'Hammadde',
                'unit_symbol'          => $it['unit_symbol'] ?: 'AD',
                'unit_quantity'        => $rawQty,
                'scrap_rate_pct'       => $scrapPct,
                'effective_unit_qty'   => $effectiveQty,
                'unit_price'           => $unitPrice,
                'currency'             => $it['currency'],
                'item_cost'            => $itemCost,
                'formatted_unit_price' => self::formatCurrency($unitPrice, $it['currency']),
                'formatted_item_cost'  => self::formatCurrency($itemCost, $it['currency'])
            ];
        }

        // Calculate percentage shares
        foreach ($items as &$it) {
            $it['cost_share_pct'] = $totalUnitCost > 0 ? round(($it['item_cost'] / $totalUnitCost) * 100, 2) : 0.0;
        }
        unset($it);

        return [
            'success'              => true,
            'recipe_id'            => (int)$recipe['id'],
            'recipe_code'          => $recipe['code'],
            'recipe_name'          => $recipe['name'],
            'output_product'       => $recipe['output_product_name'],
            'output_code'          => $recipe['output_product_code'],
            'currency'             => 'TL',
            'theoretical_unit_cost'=> round($totalUnitCost, 4),
            'formatted_unit_cost'  => self::formatCurrency($totalUnitCost, 'TL'),
            'items'                => $items,
            'items_count'          => count($items)
        ];
    }

    /**
     * İş emri seviyesinde detaylı üretim maliyet özetini hesaplar.
     */
    public function getWorkOrderCostSummary(int $workOrderId): array
    {
        $stmtWo = $this->pdo->prepare("
            SELECT 
                wo.*,
                m.code AS product_code,
                m.name AS product_name,
                r.code AS recipe_code,
                r.name AS recipe_name,
                r.base_quantity AS recipe_base_qty
            FROM mes_work_orders wo
            JOIN materials m ON wo.product_material_id = m.id
            JOIN recipes r ON wo.recipe_id = r.id
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
        $plannedQty = (float)$wo['planned_quantity'];
        $producedQty = (float)$wo['produced_quantity'];
        $remainingQty = max(0, $plannedQty - $producedQty);

        // 1. Get theoretical recipe cost
        $theoretical = $this->getRecipeTheoreticalCost($recipeId);
        $theoreticalUnitCost = (float)($theoretical['theoretical_unit_cost'] ?? 0.0);

        // 2. Fetch actual realized costs from stock_movements (OUT) linked to this work order
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

        $realizedCost = 0.0;
        $actualCostByMaterial = [];
        $actualQtyByMaterial = [];

        if (!empty($refs)) {
            $inPlaceholders = implode(',', array_fill(0, count($refs), '?'));
            $stmtMov = $this->pdo->prepare("
                SELECT material_id, SUM(quantity) AS total_qty, SUM(total_price) AS total_cost
                FROM stock_movements
                WHERE movement_type = 'OUT' AND reference_no IN ({$inPlaceholders})
                GROUP BY material_id
            ");
            $stmtMov->execute($refs);
            while ($row = $stmtMov->fetch(PDO::FETCH_ASSOC)) {
                $matId = (int)$row['material_id'];
                $matCost = (float)$row['total_cost'];
                $matQty = (float)$row['total_qty'];
                $realizedCost += $matCost;
                $actualCostByMaterial[$matId] = $matCost;
                $actualQtyByMaterial[$matId] = $matQty;
            }
        }

        // If no stock movements had total_price yet, fallback to producedQty * theoreticalUnitCost
        if ($producedQty > 0 && $realizedCost <= 0) {
            $realizedCost = round($producedQty * $theoreticalUnitCost, 4);
        }

        $realizedUnitCost = $producedQty > 0 ? round($realizedCost / $producedQty, 4) : $theoreticalUnitCost;

        // Estimated Total Cost = Realized Cost + (Remaining Quantity * Theoretical Unit Cost)
        $estimatedTotalCost = round($realizedCost + ($remainingQty * $theoreticalUnitCost), 4);
        $remainingEstimatedCost = max(0, round($estimatedTotalCost - $realizedCost, 4));

        // Build itemized breakdown with cost details
        $itemizedCost = [];
        if (!empty($theoretical['items'])) {
            foreach ($theoretical['items'] as $it) {
                $matId = $it['material_id'];
                $effectiveUnitQty = $it['effective_unit_qty'];
                $unitPrice = $it['unit_price'];

                $plannedTotalCost = round($plannedQty * $effectiveUnitQty * $unitPrice, 4);
                $actualItemCost = $actualCostByMaterial[$matId] ?? round($producedQty * $effectiveUnitQty * $unitPrice, 4);
                $remainingItemCost = max(0, round($plannedTotalCost - $actualItemCost, 4));

                $itemizedCost[] = array_merge($it, [
                    'planned_total_cost'           => $plannedTotalCost,
                    'actual_consumed_cost'         => $actualItemCost,
                    'remaining_needed_cost'        => $remainingItemCost,
                    'formatted_planned_total_cost' => self::formatCurrency($plannedTotalCost, $it['currency']),
                    'formatted_actual_cost'        => self::formatCurrency($actualItemCost, $it['currency']),
                    'formatted_remaining_cost'     => self::formatCurrency($remainingItemCost, $it['currency']),
                ]);
            }
        }

        return [
            'success'                      => true,
            'work_order_id'                => (int)$wo['id'],
            'work_order_no'                => $wo['work_order_no'],
            'product_name'                 => $wo['product_name'],
            'product_code'                 => $wo['product_code'],
            'recipe_code'                  => $wo['recipe_code'],
            'planned_quantity'             => $plannedQty,
            'produced_quantity'            => $producedQty,
            'remaining_quantity'           => $remainingQty,
            'currency'                     => 'TL',
            'unit_cost'                    => $theoreticalUnitCost,
            'realized_unit_cost'           => $realizedUnitCost,
            'realized_cost'                => round($realizedCost, 4),
            'estimated_total_cost'         => round($estimatedTotalCost, 4),
            'remaining_estimated_cost'     => round($remainingEstimatedCost, 4),
            'formatted_unit_cost'          => self::formatCurrency($theoreticalUnitCost, 'TL'),
            'formatted_realized_unit_cost' => self::formatCurrency($realizedUnitCost, 'TL'),
            'formatted_realized_cost'      => self::formatCurrency($realizedCost, 'TL'),
            'formatted_estimated_total_cost'=> self::formatCurrency($estimatedTotalCost, 'TL'),
            'formatted_remaining_cost'     => self::formatCurrency($remainingEstimatedCost, 'TL'),
            'itemized_cost'                => $itemizedCost
        ];
    }

    /**
     * Belirli bir MES olayının (tekil panel üretiminin) snapshot maliyet detaylarını getirir.
     */
    public function getEventCostDetails(string $eventId): array
    {
        $stmtEvent = $this->pdo->prepare("
            SELECT e.*, wo.work_order_no, wo.recipe_id, m.name AS product_name, m.code AS product_code
            FROM mes_production_events e
            JOIN mes_work_orders wo ON e.work_order_id = wo.id
            JOIN materials m ON e.product_material_id = m.id
            WHERE e.event_id = ?
        ");
        $stmtEvent->execute([$eventId]);
        $event = $stmtEvent->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            return [
                'success' => false,
                'message' => 'MES üretim olayı bulunamadı: ' . $eventId
            ];
        }

        $stockRef = $event['stock_movement_ref'];
        $consumedItems = [];
        $totalCost = 0.0;

        if (!empty($stockRef)) {
            $stmtMov = $this->pdo->prepare("
                SELECT sm.*, m.code AS material_code, m.name AS material_name, u.symbol AS unit_symbol
                FROM stock_movements sm
                JOIN materials m ON sm.material_id = m.id
                LEFT JOIN units u ON m.unit_id = u.id
                WHERE sm.reference_no = ? AND sm.movement_type = 'OUT'
                ORDER BY sm.id ASC
            ");
            $stmtMov->execute([$stockRef]);
            $rows = $stmtMov->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $r) {
                $qty = (float)$r['quantity'];
                $uPrice = (float)$r['unit_price'];
                $tPrice = (float)$r['total_price'];
                $totalCost += $tPrice;

                $consumedItems[] = [
                    'material_id'          => (int)$r['material_id'],
                    'material_code'        => $r['material_code'],
                    'material_name'        => $r['material_name'],
                    'unit_symbol'          => $r['unit_symbol'] ?: 'AD',
                    'quantity'             => $qty,
                    'unit_price'           => $uPrice,
                    'total_price'          => $tPrice,
                    'currency'             => $r['currency'] ?: 'TL',
                    'formatted_unit_price' => self::formatCurrency($uPrice, $r['currency'] ?: 'TL'),
                    'formatted_total_price'=> self::formatCurrency($tPrice, $r['currency'] ?: 'TL')
                ];
            }
        }

        if ($totalCost <= 0 && !empty($event['unit_cost'])) {
            $totalCost = (float)$event['unit_cost'] * (float)$event['quantity'];
        }

        return [
            'success'              => true,
            'event_id'             => $event['event_id'],
            'work_order_no'        => $event['work_order_no'],
            'product_name'         => $event['product_name'],
            'product_code'         => $event['product_code'],
            'quantity'             => (float)$event['quantity'],
            'unit_cost'            => round($totalCost / max(1.0, (float)$event['quantity']), 4),
            'total_cost'           => round($totalCost, 4),
            'currency'             => $event['cost_currency'] ?: 'TL',
            'formatted_unit_cost'  => self::formatCurrency($totalCost / max(1.0, (float)$event['quantity']), $event['cost_currency'] ?: 'TL'),
            'formatted_total_cost' => self::formatCurrency($totalCost, $event['cost_currency'] ?: 'TL'),
            'stock_movement_ref'   => $stockRef,
            'consumed_items'       => $consumedItems
        ];
    }

    /**
     * Sayısal tutarı Türkiye para birimi formatına çevirir (örn: 2.850,64 TL).
     */
    public static function formatCurrency(float $amount, string $currency = 'TL'): string
    {
        return number_format($amount, 2, ',', '.') . ' ' . $currency;
    }
}

