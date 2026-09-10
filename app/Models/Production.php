<?php

class Production
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Aktif üretim hatlarını döner.
     */
    public function getActiveLines(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM production_lines WHERE is_active = 1 ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Aktif vardiyaları döner.
     */
    public function getActiveShifts(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM energy_shifts WHERE is_active = 1 ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Aktif depoları ve raf/lokasyonları döner.
     */
    public function getActiveLocations(): array
    {
        $sql = "
            SELECT 
                l.id,
                l.warehouse_id,
                w.name AS warehouse_name,
                w.code AS warehouse_code,
                l.code AS loc_code,
                l.name AS loc_name
            FROM locations l
            INNER JOIN warehouses w ON w.id = l.warehouse_id
            WHERE l.is_active = 1 AND w.is_active = 1
            ORDER BY w.id ASC, l.name ASC
        ";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Aktif reçeteleri döner.
     */
    public function getActiveRecipes(): array
    {
        $sql = "
            SELECT 
                r.id,
                r.code,
                r.name,
                r.base_quantity,
                m.code AS output_code,
                m.name AS output_name,
                COUNT(ri.id) AS item_count
            FROM recipes r
            LEFT JOIN materials m ON m.id = r.output_material_id
            LEFT JOIN recipe_items ri ON ri.recipe_id = r.id
            WHERE r.is_active = 1
            GROUP BY r.id, r.code, r.name, r.base_quantity, m.code, m.name
            ORDER BY r.name ASC
        ";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Üretim kayıtlarını listeler.
     */
    public function getProductionList(int $limit = 50, ?int $lineId = null, ?string $search = null): array
    {
        $sql = "
            SELECT 
                epl.id,
                epl.line_id,
                pl.code AS line_code,
                pl.name AS line_name,
                epl.shift_id,
                es.name AS shift_name,
                epl.log_date,
                epl.panels_produced_qty,
                epl.total_wp_produced,
                epl.scrap_panels_qty,
                epl.cells_used_qty,
                epl.stock_movement_ref,
                epl.notes,
                epl.created_at,
                (SELECT COUNT(*) FROM stock_movements sm WHERE sm.reference_no = epl.stock_movement_ref) AS movement_count
            FROM energy_production_logs epl
            INNER JOIN production_lines pl ON pl.id = epl.line_id
            INNER JOIN energy_shifts es ON es.id = epl.shift_id
            WHERE 1=1
        ";

        $params = [];

        if ($lineId !== null && $lineId > 0) {
            $sql .= " AND epl.line_id = :line_id";
            $params[':line_id'] = $lineId;
        }

        if ($search !== null && trim($search) !== '') {
            $sql .= " AND (epl.stock_movement_ref LIKE :search OR epl.notes LIKE :search)";
            $params[':search'] = '%' . trim($search) . '%';
        }

        $sql .= " ORDER BY epl.log_date DESC, epl.id DESC LIMIT " . (int)$limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tekil üretim kaydı detayını döner.
     */
    public function getById(int $id): ?array
    {
        $sql = "
            SELECT 
                epl.*,
                pl.code AS line_code,
                pl.name AS line_name,
                es.name AS shift_name,
                es.start_time,
                es.end_time
            FROM energy_production_logs epl
            INNER JOIN production_lines pl ON pl.id = epl.line_id
            INNER JOIN energy_shifts es ON es.id = epl.shift_id
            WHERE epl.id = :id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Bir üretime ait referans koduyla ilişkili stok hareketlerini döner.
     */
    public function getMovementsByRef(string $referenceNo): array
    {
        if (empty(trim($referenceNo))) {
            return [];
        }

        $sql = "
            SELECT 
                sm.id,
                sm.material_id,
                m.code AS material_code,
                m.name AS material_name,
                c.name AS category_name,
                u.symbol AS unit_symbol,
                sm.location_id,
                l.name AS location_name,
                w.name AS warehouse_name,
                sm.quantity,
                sm.movement_type,
                sm.reference_no,
                sm.description,
                sm.created_at,
                u2.name AS user_name
            FROM stock_movements sm
            INNER JOIN materials m ON m.id = sm.material_id
            LEFT JOIN categories c ON c.id = m.category_id
            LEFT JOIN units u ON u.id = m.unit_id
            LEFT JOIN locations l ON l.id = sm.location_id
            LEFT JOIN warehouses w ON w.id = l.warehouse_id
            LEFT JOIN users u2 ON u2.id = sm.user_id
            WHERE sm.reference_no = :ref COLLATE utf8mb4_turkish_ci
            ORDER BY m.name ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':ref' => trim($referenceNo)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
