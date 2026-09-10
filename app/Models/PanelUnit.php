<?php

class PanelUnit
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Filtrelenmiş panel serileri listesini döner.
     */
    public function getAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $sql = "
            SELECT 
                pu.id,
                pu.serial_no,
                pu.material_id,
                pu.work_order_id,
                pu.production_event_id,
                pu.production_line_id,
                pu.warehouse_id,
                pu.location_id,
                pu.stock_movement_id,
                pu.stock_movement_ref,
                pu.status,
                pu.quality_notes,
                pu.produced_at,
                pu.created_at,
                m.name AS material_name,
                m.code AS material_code,
                wo.work_order_no,
                pl.name AS line_name,
                pl.code AS line_code,
                w.name AS warehouse_name,
                w.code AS warehouse_code,
                l.name AS location_name,
                l.code AS location_code
            FROM panel_units pu
            JOIN materials m ON pu.material_id = m.id
            LEFT JOIN mes_work_orders wo ON pu.work_order_id = wo.id
            LEFT JOIN production_lines pl ON pu.production_line_id = pl.id
            LEFT JOIN warehouses w ON pu.warehouse_id = w.id
            LEFT JOIN locations l ON pu.location_id = l.id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['serial_no'])) {
            $sql .= " AND pu.serial_no LIKE :serial_no";
            $params[':serial_no'] = '%' . trim($filters['serial_no']) . '%';
        }

        if (!empty($filters['work_order_id'])) {
            $sql .= " AND pu.work_order_id = :work_order_id";
            $params[':work_order_id'] = (int)$filters['work_order_id'];
        }

        if (!empty($filters['work_order_no'])) {
            $sql .= " AND wo.work_order_no LIKE :work_order_no";
            $params[':work_order_no'] = '%' . trim($filters['work_order_no']) . '%';
        }

        if (!empty($filters['status'])) {
            $sql .= " AND pu.status = :status";
            $params[':status'] = trim($filters['status']);
        }

        if (!empty($filters['warehouse_id'])) {
            $sql .= " AND pu.warehouse_id = :warehouse_id";
            $params[':warehouse_id'] = (int)$filters['warehouse_id'];
        }

        if (!empty($filters['material_id'])) {
            $sql .= " AND pu.material_id = :material_id";
            $params[':material_id'] = (int)$filters['material_id'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND pu.produced_at >= :date_from";
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND pu.produced_at <= :date_to";
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $sql .= " ORDER BY pu.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Toplam kayıt sayısını döner.
     */
    public function countAll(array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM panel_units pu
            LEFT JOIN mes_work_orders wo ON pu.work_order_id = wo.id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['serial_no'])) {
            $sql .= " AND pu.serial_no LIKE :serial_no";
            $params[':serial_no'] = '%' . trim($filters['serial_no']) . '%';
        }

        if (!empty($filters['work_order_id'])) {
            $sql .= " AND pu.work_order_id = :work_order_id";
            $params[':work_order_id'] = (int)$filters['work_order_id'];
        }

        if (!empty($filters['work_order_no'])) {
            $sql .= " AND wo.work_order_no LIKE :work_order_no";
            $params[':work_order_no'] = '%' . trim($filters['work_order_no']) . '%';
        }

        if (!empty($filters['status'])) {
            $sql .= " AND pu.status = :status";
            $params[':status'] = trim($filters['status']);
        }

        if (!empty($filters['warehouse_id'])) {
            $sql .= " AND pu.warehouse_id = :warehouse_id";
            $params[':warehouse_id'] = (int)$filters['warehouse_id'];
        }

        if (!empty($filters['material_id'])) {
            $sql .= " AND pu.material_id = :material_id";
            $params[':material_id'] = (int)$filters['material_id'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND pu.produced_at >= :date_from";
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND pu.produced_at <= :date_to";
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * ID ile tek panel pasaportunu döner.
     */
    public function getById(int $id): ?array
    {
        $sql = "
            SELECT 
                pu.*,
                m.name AS material_name,
                m.code AS material_code,
                wo.work_order_no,
                wo.planned_quantity,
                wo.produced_quantity,
                pl.name AS line_name,
                pl.code AS line_code,
                w.name AS warehouse_name,
                w.code AS warehouse_code,
                l.name AS location_name,
                l.code AS location_code,
                sm.reference_no AS movement_ref_no,
                sm.created_at AS movement_created_at
            FROM panel_units pu
            JOIN materials m ON pu.material_id = m.id
            LEFT JOIN mes_work_orders wo ON pu.work_order_id = wo.id
            LEFT JOIN production_lines pl ON pu.production_line_id = pl.id
            LEFT JOIN warehouses w ON pu.warehouse_id = w.id
            LEFT JOIN locations l ON pu.location_id = l.id
            LEFT JOIN stock_movements sm ON pu.stock_movement_id = sm.id
            WHERE pu.id = :id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Seri No ile panel pasaportunu döner.
     */
    public function getBySerial(string $serialNo): ?array
    {
        $sql = "
            SELECT 
                pu.*,
                m.name AS material_name,
                m.code AS material_code,
                wo.work_order_no,
                wo.planned_quantity,
                wo.produced_quantity,
                pl.name AS line_name,
                pl.code AS line_code,
                w.name AS warehouse_name,
                w.code AS warehouse_code,
                l.name AS location_name,
                l.code AS location_code,
                sm.reference_no AS movement_ref_no,
                sm.created_at AS movement_created_at
            FROM panel_units pu
            JOIN materials m ON pu.material_id = m.id
            LEFT JOIN mes_work_orders wo ON pu.work_order_id = wo.id
            LEFT JOIN production_lines pl ON pu.production_line_id = pl.id
            LEFT JOIN warehouses w ON pu.warehouse_id = w.id
            LEFT JOIN locations l ON pu.location_id = l.id
            LEFT JOIN stock_movements sm ON pu.stock_movement_id = sm.id
            WHERE pu.serial_no = :serial_no
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':serial_no' => trim($serialNo)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * İş emrine ait son üretilen panelleri döner.
     */
    public function getRecentByWorkOrder(int $workOrderId, int $limit = 10): array
    {
        $sql = "
            SELECT 
                pu.id,
                pu.serial_no,
                pu.status,
                pu.produced_at,
                w.name AS warehouse_name,
                l.name AS location_name
            FROM panel_units pu
            LEFT JOIN warehouses w ON pu.warehouse_id = w.id
            LEFT JOIN locations l ON pu.location_id = l.id
            WHERE pu.work_order_id = :work_order_id
            ORDER BY pu.id DESC
            LIMIT " . (int)$limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':work_order_id' => $workOrderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Özet durum istatistiklerini döner.
     */
    public function getSummaryStats(): array
    {
        $sql = "
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN status IN ('IN_STOCK', 'QUALITY_APPROVED') THEN 1 ELSE 0 END) AS in_stock,
                SUM(CASE WHEN status IN ('QUALITY_PENDING', 'QUARANTINE', 'QUALITY_REJECTED') THEN 1 ELSE 0 END) AS quarantine,
                SUM(CASE WHEN status = 'SHIPPED' THEN 1 ELSE 0 END) AS shipped,
                SUM(CASE WHEN DATE(produced_at) = CURDATE() THEN 1 ELSE 0 END) AS today_produced
            FROM panel_units
        ";

        $row = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        return [
            'total'          => (int)($row['total'] ?? 0),
            'in_stock'       => (int)($row['in_stock'] ?? 0),
            'quarantine'     => (int)($row['quarantine'] ?? 0),
            'shipped'        => (int)($row['shipped'] ?? 0),
            'today_produced' => (int)($row['today_produced'] ?? 0),
        ];
    }

    /**
     * Get BOM details for a production event.
     */
    public function getEventBomDetails(string $eventId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                e.*,
                wo.work_order_no,
                wo.recipe_id,
                r.code AS recipe_code,
                r.name AS recipe_name,
                r.base_quantity,
                m.code AS product_code,
                m.name AS product_name
            FROM mes_production_events e
            JOIN mes_work_orders wo ON e.work_order_id = wo.id
            JOIN recipes r ON wo.recipe_id = r.id
            JOIN materials m ON e.product_material_id = m.id
            WHERE e.event_id = ?
            LIMIT 1
        ");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            return ['success' => false, 'message' => 'Event not found'];
        }

        $recipeId = (int)$event['recipe_id'];
        $baseQty = (float)($event['base_quantity'] > 0 ? $event['base_quantity'] : 1.0);

        // Fetch snapshot costs of materials from stock_movements (OUT)
        $movementCostMap = [];
        $stockRef = $event['stock_movement_ref'] ?? '';
        if (!empty($stockRef)) {
            $stmtMovCosts = $this->pdo->prepare("
                SELECT material_id, unit_price, total_price, currency 
                FROM stock_movements 
                WHERE reference_no = ? AND movement_type = 'OUT'
            ");
            $stmtMovCosts->execute([$stockRef]);
            while ($mRow = $stmtMovCosts->fetch(PDO::FETCH_ASSOC)) {
                $mId = (int)$mRow['material_id'];
                $movementCostMap[$mId] = [
                    'unit_price' => (float)$mRow['unit_price'],
                    'currency'   => $mRow['currency'] ?: 'TL'
                ];
            }
        }

        $stmtItems = $this->pdo->prepare("
            SELECT 
                ri.material_id,
                m.code AS material_code,
                m.name AS material_name,
                u.symbol AS unit_symbol,
                ri.quantity AS unit_qty,
                ri.scrap_rate_pct,
                ri.is_critical,
                COALESCE(m.unit_price, 0) AS unit_price,
                (ri.quantity / ?) * (1 + ri.scrap_rate_pct/100) AS effective_qty
            FROM recipe_items ri
            JOIN materials m ON m.id = ri.material_id
            LEFT JOIN units u ON u.id = m.unit_id
            WHERE ri.recipe_id = ?
            ORDER BY ri.is_critical DESC, m.name ASC
        ");
        $stmtItems->execute([$baseQty, $recipeId]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as &$item) {
            $matId = (int)$item['material_id'];
            if (isset($movementCostMap[$matId])) {
                $item['unit_price'] = $movementCostMap[$matId]['unit_price'];
            }
        }
        unset($item);

        return [
            'success'    => true,
            'event'      => $event,
            'bom_items'  => $items
        ];
    }

    /**
     * Get stock movements by reference number.
     */
    public function getStockMovementsByRef(string $referenceNo): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                sm.*,
                m.code AS material_code,
                m.name AS material_name,
                u.symbol AS unit_symbol,
                l.code AS location_code,
                l.name AS location_name,
                w.code AS warehouse_code,
                w.name AS warehouse_name
            FROM stock_movements sm
            JOIN materials m ON sm.material_id = m.id
            LEFT JOIN units u ON u.id = m.unit_id
            LEFT JOIN locations l ON sm.location_id = l.id
            LEFT JOIN warehouses w ON l.warehouse_id = w.id
            WHERE sm.reference_no = ?
            ORDER BY sm.created_at ASC
        ");
        $stmt->execute([$referenceNo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get full traceability chain for a panel unit.
     */
    public function getTraceabilityChain(int $panelId): array
    {
        $panel = $this->getById($panelId);
        if (!$panel) {
            return ['success' => false, 'message' => 'Panel not found'];
        }

        $eventId = $panel['production_event_id'] ?? null;
        $stockRef = $panel['stock_movement_ref'] ?? null;

        $bomDetails = [];
        $stockMovements = [];

        if ($eventId) {
            $bomResult = $this->getEventBomDetails($eventId);
            if ($bomResult['success']) {
                $bomDetails = $bomResult;
            }
        }

        if ($stockRef) {
            $stockMovements = $this->getStockMovementsByRef($stockRef);
        }

        return [
            'success'         => true,
            'panel'           => $panel,
            'bom'             => $bomDetails,
            'stock_movements' => $stockMovements
        ];
    }

    /**
     * Belirli bir ID'den sonra üretilmiş yeni panelleri filtrelerle birlikte döner.
     */
    public function getNewPanelsSince(int $lastId, array $filters = [], int $limit = 50): array
    {
        $sql = "
            SELECT 
                pu.id,
                pu.serial_no,
                pu.material_id,
                pu.work_order_id,
                pu.production_event_id,
                pu.production_line_id,
                pu.warehouse_id,
                pu.location_id,
                pu.stock_movement_id,
                pu.stock_movement_ref,
                pu.status,
                pu.quality_notes,
                pu.produced_at,
                pu.created_at,
                m.name AS material_name,
                m.code AS material_code,
                wo.work_order_no,
                pl.name AS line_name,
                pl.code AS line_code,
                w.name AS warehouse_name,
                w.code AS warehouse_code,
                l.name AS location_name,
                l.code AS location_code
            FROM panel_units pu
            JOIN materials m ON pu.material_id = m.id
            LEFT JOIN mes_work_orders wo ON pu.work_order_id = wo.id
            LEFT JOIN production_lines pl ON pu.production_line_id = pl.id
            LEFT JOIN warehouses w ON pu.warehouse_id = w.id
            LEFT JOIN locations l ON pu.location_id = l.id
            WHERE pu.id > :last_id
        ";

        $params = [':last_id' => $lastId];

        if (!empty($filters['serial_no'])) {
            $sql .= " AND pu.serial_no LIKE :serial_no";
            $params[':serial_no'] = '%' . trim($filters['serial_no']) . '%';
        }

        if (!empty($filters['work_order_id'])) {
            $sql .= " AND pu.work_order_id = :work_order_id";
            $params[':work_order_id'] = (int)$filters['work_order_id'];
        }

        if (!empty($filters['work_order_no'])) {
            $sql .= " AND wo.work_order_no LIKE :work_order_no";
            $params[':work_order_no'] = '%' . trim($filters['work_order_no']) . '%';
        }

        if (!empty($filters['status'])) {
            $sql .= " AND pu.status = :status";
            $params[':status'] = trim($filters['status']);
        }

        if (!empty($filters['warehouse_id'])) {
            $sql .= " AND pu.warehouse_id = :warehouse_id";
            $params[':warehouse_id'] = (int)$filters['warehouse_id'];
        }

        if (!empty($filters['material_id'])) {
            $sql .= " AND pu.material_id = :material_id";
            $params[':material_id'] = (int)$filters['material_id'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND pu.produced_at >= :date_from";
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND pu.produced_at <= :date_to";
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $sql .= " ORDER BY pu.id DESC LIMIT " . (int)$limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * En yüksek (son) panel ID'sini döner.
     */
    public function getLastPanelId(): int
    {
        $stmt = $this->pdo->query("SELECT COALESCE(MAX(id), 0) FROM panel_units");
        return (int)$stmt->fetchColumn();
    }
}