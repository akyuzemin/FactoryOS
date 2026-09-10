<?php

class Report
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getSummary(): array
    {
        $summary = [];

        $queries = [
            'total_materials' => "
                SELECT COUNT(*)
                FROM materials
                WHERE is_active = 1
            ",
            'total_warehouses' => "
                SELECT COUNT(*)
                FROM warehouses
                WHERE is_active = 1
            ",
            'total_stock' => "
                SELECT COALESCE(SUM(sb.quantity), 0)
                FROM stock_balances sb
                INNER JOIN locations l ON l.id = sb.location_id
                INNER JOIN warehouses w ON w.id = l.warehouse_id
                INNER JOIN materials m ON m.id = sb.material_id
                WHERE l.is_active = 1 AND w.is_active = 1 AND m.is_active = 1
            ",
            'critical_stock_count' => "
                SELECT COUNT(*)
                FROM (
                    SELECT
                        m.id,
                        m.min_stock,
                        COALESCE(SUM(sb.quantity), 0) AS current_stock
                    FROM materials m
                    LEFT JOIN (
                        stock_balances sb
                        INNER JOIN locations l ON l.id = sb.location_id AND l.is_active = 1
                        INNER JOIN warehouses w ON w.id = l.warehouse_id AND w.is_active = 1
                    ) ON sb.material_id = m.id
                    WHERE m.is_active = 1
                    GROUP BY m.id, m.min_stock
                    HAVING current_stock <= m.min_stock
                ) AS critical_materials
            ",
            'movement_totals' => "
                SELECT
                    COALESCE(SUM(CASE
                        WHEN movement_type IN ('IN', 'RETURN', 'TRANSFER_IN') THEN quantity
                        ELSE 0
                    END), 0) AS total_in,
                    COALESCE(SUM(CASE
                        WHEN movement_type IN ('OUT', 'TRANSFER_OUT') THEN quantity
                        ELSE 0
                    END), 0) AS total_out
                FROM stock_movements
            ",
        ];

        foreach ($queries as $key => $sql) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            if ($key === 'movement_totals') {
                $summary[$key] = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_in' => 0, 'total_out' => 0];
                continue;
            }

            $summary[$key] = $stmt->fetchColumn();
        }

        return $summary;
    }

    public function getRecentMovements(int $limit = 8): array
    {
        $sql = "
            SELECT
                sm.id,
                sm.created_at,
                m.code AS material_code,
                m.name AS material_name,
                u.symbol AS unit_symbol,
                w.name AS warehouse_name,
                l.name AS location_name,
                sm.movement_type,
                sm.quantity,
                sm.reference_no,
                sm.description,
                CONCAT(usr.first_name, ' ', usr.last_name) AS user_name
            FROM stock_movements sm
            INNER JOIN materials m
                ON m.id = sm.material_id
            LEFT JOIN units u
                ON u.id = m.unit_id
            INNER JOIN locations l
                ON l.id = sm.location_id
            INNER JOIN warehouses w
                ON w.id = l.warehouse_id
            INNER JOIN users usr
                ON usr.id = sm.user_id
            ORDER BY sm.created_at DESC, sm.id DESC
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFilterOptions(): array
    {
        $categoriesStmt = $this->pdo->prepare(
            'SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC'
        );
        $categoriesStmt->execute();

        $warehousesStmt = $this->pdo->prepare(
            'SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY name ASC'
        );
        $warehousesStmt->execute();

        $locationsStmt = $this->pdo->prepare(
            'SELECT l.id, l.warehouse_id, l.code, l.name, w.name AS warehouse_name
             FROM locations l
             INNER JOIN warehouses w ON w.id = l.warehouse_id
             WHERE l.is_active = 1 AND w.is_active = 1
             ORDER BY w.name ASC, l.name ASC'
        );
        $locationsStmt->execute();

        $materialsStmt = $this->pdo->prepare(
            'SELECT id, code, name FROM materials WHERE is_active = 1 ORDER BY name ASC'
        );
        $materialsStmt->execute();

        $usersStmt = $this->pdo->prepare(
            'SELECT id, username, first_name, last_name FROM users WHERE is_active = 1 ORDER BY first_name ASC, last_name ASC'
        );
        $usersStmt->execute();

        return [
            'categories' => $categoriesStmt->fetchAll(PDO::FETCH_ASSOC),
            'warehouses' => $warehousesStmt->fetchAll(PDO::FETCH_ASSOC),
            'locations'  => $locationsStmt->fetchAll(PDO::FETCH_ASSOC),
            'materials'  => $materialsStmt->fetchAll(PDO::FETCH_ASSOC),
            'users'      => $usersStmt->fetchAll(PDO::FETCH_ASSOC),
            'movement_types' => [
                'IN'           => 'Stok Girişi',
                'OUT'          => 'Stok Çıkışı',
                'TRANSFER_IN'  => 'Transfer Girişi',
                'TRANSFER_OUT' => 'Transfer Çıkışı',
                'RETURN'       => 'Stok İadesi',
                'ADJUSTMENT'   => 'Stok Düzeltme',
            ],
        ];
    }

    public function getStockStatusReport(array $filters): array
    {
        $conditions = ['m.is_active = 1'];
        $params = [];
        $havingConditions = [];

        if (!empty($filters['category_id'])) {
            $conditions[] = 'm.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(m.name LIKE :search OR m.code LIKE :search)';
            $params['search'] = '%' . trim($filters['search']) . '%';
        }

        if (!empty($filters['status'])) {
            switch ($filters['status']) {
                case 'out_of_stock':
                case 'Tükendi':
                    $havingConditions[] = 'current_stock <= 0';
                    break;
                case 'critical':
                case 'Kritik':
                    $havingConditions[] = 'current_stock > 0 AND current_stock <= m.min_stock';
                    break;
                case 'overstock':
                case 'Fazla Stok':
                    $havingConditions[] = 'm.max_stock IS NOT NULL AND m.max_stock > 0 AND current_stock > m.max_stock';
                    break;
                case 'normal':
                case 'Normal':
                    $havingConditions[] = 'current_stock > m.min_stock AND (m.max_stock IS NULL OR m.max_stock = 0 OR current_stock <= m.max_stock)';
                    break;
            }
        }

        $whereSql = 'WHERE ' . implode(' AND ', $conditions);
        $havingSql = !empty($havingConditions) ? 'HAVING ' . implode(' AND ', $havingConditions) : '';

        $sql = "
            SELECT 
                m.id,
                m.code AS material_code,
                m.name AS material_name,
                c.id AS category_id,
                c.name AS category_name,
                u.symbol AS unit_symbol,
                m.min_stock,
                m.max_stock,
                COALESCE(sb.total_qty, 0) AS current_stock,
                CASE
                    WHEN COALESCE(sb.total_qty, 0) <= 0 THEN 'Tükendi'
                    WHEN COALESCE(sb.total_qty, 0) <= m.min_stock THEN 'Kritik'
                    WHEN m.max_stock IS NOT NULL AND m.max_stock > 0 AND COALESCE(sb.total_qty, 0) > m.max_stock THEN 'Fazla Stok'
                    ELSE 'Normal'
                END AS status
            FROM materials m
            INNER JOIN categories c ON c.id = m.category_id
            INNER JOIN units u ON u.id = m.unit_id
            LEFT JOIN (
                SELECT sb.material_id, SUM(sb.quantity) AS total_qty
                FROM stock_balances sb
                INNER JOIN locations l ON l.id = sb.location_id
                INNER JOIN warehouses w ON w.id = l.warehouse_id
                WHERE l.is_active = 1 AND w.is_active = 1
                GROUP BY sb.material_id
            ) sb ON sb.material_id = m.id
            $whereSql
            GROUP BY m.id, m.code, m.name, c.id, c.name, u.symbol, m.min_stock, m.max_stock, sb.total_qty
            $havingSql
            ORDER BY current_stock DESC, m.name ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCriticalStockReport(array $filters): array
    {
        $conditions = ['m.is_active = 1'];
        $params = [];
        $havingConditions = [];

        if (!empty($filters['category_id'])) {
            $conditions[] = 'm.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(m.name LIKE :search OR m.code LIKE :search)';
            $params['search'] = '%' . trim($filters['search']) . '%';
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'out_of_stock' || $filters['status'] === 'Tükendi') {
                $havingConditions[] = 'current_stock <= 0';
            } elseif ($filters['status'] === 'critical' || $filters['status'] === 'Kritik') {
                $havingConditions[] = 'current_stock > 0 AND current_stock <= m.min_stock';
            } else {
                $havingConditions[] = 'current_stock <= m.min_stock';
            }
        } else {
            $havingConditions[] = 'current_stock <= m.min_stock';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $conditions);
        $havingSql = 'HAVING ' . implode(' AND ', $havingConditions);

        $sql = "
            SELECT 
                m.id,
                m.code AS material_code,
                m.name AS material_name,
                c.id AS category_id,
                c.name AS category_name,
                u.symbol AS unit_symbol,
                m.min_stock,
                m.max_stock,
                COALESCE(sb.total_qty, 0) AS current_stock,
                GREATEST(0, m.min_stock - COALESCE(sb.total_qty, 0)) AS deficit_qty,
                CASE
                    WHEN COALESCE(sb.total_qty, 0) <= 0 THEN 'Tükendi'
                    ELSE 'Kritik Stok'
                END AS status
            FROM materials m
            INNER JOIN categories c ON c.id = m.category_id
            INNER JOIN units u ON u.id = m.unit_id
            LEFT JOIN (
                SELECT sb.material_id, SUM(sb.quantity) AS total_qty
                FROM stock_balances sb
                INNER JOIN locations l ON l.id = sb.location_id
                INNER JOIN warehouses w ON w.id = l.warehouse_id
                WHERE l.is_active = 1 AND w.is_active = 1
                GROUP BY sb.material_id
            ) sb ON sb.material_id = m.id
            $whereSql
            GROUP BY m.id, m.code, m.name, c.id, c.name, u.symbol, m.min_stock, m.max_stock, sb.total_qty
            $havingSql
            ORDER BY current_stock ASC, deficit_qty DESC, m.name ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getWarehouseStockReport(array $filters): array
    {
        $conditions = [
            'sb.quantity > 0',
            'l.is_active = 1',
            'w.is_active = 1',
            'm.is_active = 1'
        ];
        $params = [];

        if (!empty($filters['warehouse_id'])) {
            $conditions[] = 'w.id = :warehouse_id';
            $params['warehouse_id'] = (int) $filters['warehouse_id'];
        }

        if (!empty($filters['location_id'])) {
            $conditions[] = 'l.id = :location_id';
            $params['location_id'] = (int) $filters['location_id'];
        }

        if (!empty($filters['category_id'])) {
            $conditions[] = 'm.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['material_id'])) {
            $conditions[] = 'm.id = :material_id';
            $params['material_id'] = (int) $filters['material_id'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(m.name LIKE :search OR m.code LIKE :search OR w.name LIKE :search OR l.name LIKE :search)';
            $params['search'] = '%' . trim($filters['search']) . '%';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $conditions);

        $sql = "
            SELECT 
                w.id AS warehouse_id,
                w.code AS warehouse_code,
                w.name AS warehouse_name,
                l.id AS location_id,
                l.code AS location_code,
                l.name AS location_name,
                m.id AS material_id,
                m.code AS material_code,
                m.name AS material_name,
                c.name AS category_name,
                u.symbol AS unit_symbol,
                sb.quantity,
                sb.updated_at
            FROM stock_balances sb
            INNER JOIN locations l ON l.id = sb.location_id
            INNER JOIN warehouses w ON w.id = l.warehouse_id
            INNER JOIN materials m ON m.id = sb.material_id
            INNER JOIN categories c ON c.id = m.category_id
            INNER JOIN units u ON u.id = m.unit_id
            $whereSql
            ORDER BY w.name ASC, l.name ASC, m.name ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMovementsReport(array $filters, int $page = 1, int $perPage = 15): array
    {
        [$whereSql, $params] = $this->buildMovementsWhereClause($filters);

        $sql = "
            SELECT
                sm.id,
                sm.created_at,
                sm.movement_type,
                sm.quantity,
                sm.reference_no,
                sm.description,
                m.id AS material_id,
                m.code AS material_code,
                m.name AS material_name,
                u.symbol AS unit_symbol,
                w.id AS warehouse_id,
                w.code AS warehouse_code,
                w.name AS warehouse_name,
                l.id AS location_id,
                l.code AS location_code,
                l.name AS location_name,
                usr.id AS user_id,
                CONCAT(usr.first_name, ' ', usr.last_name) AS user_name,
                CASE
                    WHEN sm.movement_type = 'OUT' AND (sm.description LIKE '%Fire%' OR sm.description LIKE '%Hurda%' OR sm.description LIKE '%fire%' OR sm.description LIKE '%hurda%') THEN 1
                    ELSE 0
                END AS is_scrap
            FROM stock_movements sm
            INNER JOIN materials m ON m.id = sm.material_id
            LEFT JOIN units u ON u.id = m.unit_id
            INNER JOIN locations l ON l.id = sm.location_id
            INNER JOIN warehouses w ON w.id = l.warehouse_id
            INNER JOIN users usr ON usr.id = sm.user_id
            $whereSql
            ORDER BY sm.created_at DESC, sm.id DESC
        ";

        if ($perPage > 0) {
            $page = max(1, $page);
            $offset = ($page - 1) * $perPage;
            $sql .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }

        if ($perPage > 0) {
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMovementsReportCount(array $filters): int
    {
        [$whereSql, $params] = $this->buildMovementsWhereClause($filters);

        $sql = "
            SELECT COUNT(*)
            FROM stock_movements sm
            INNER JOIN materials m ON m.id = sm.material_id
            INNER JOIN locations l ON l.id = sm.location_id
            INNER JOIN warehouses w ON w.id = l.warehouse_id
            INNER JOIN users usr ON usr.id = sm.user_id
            $whereSql
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function getMovementsSummaryTotals(array $filters): array
    {
        [$whereSql, $params] = $this->buildMovementsWhereClause($filters);

        $sql = "
            SELECT
                COUNT(*) AS total_transactions,
                COALESCE(SUM(CASE
                    WHEN sm.movement_type IN ('IN', 'TRANSFER_IN', 'RETURN') THEN sm.quantity
                    ELSE 0
                END), 0) AS total_in,
                COALESCE(SUM(CASE
                    WHEN sm.movement_type IN ('OUT', 'TRANSFER_OUT') THEN sm.quantity
                    ELSE 0
                END), 0) AS total_out,
                COALESCE(SUM(CASE
                    WHEN sm.movement_type = 'OUT' AND (sm.description LIKE '%Fire%' OR sm.description LIKE '%Hurda%' OR sm.description LIKE '%fire%' OR sm.description LIKE '%hurda%') THEN sm.quantity
                    ELSE 0
                END), 0) AS total_scrap
            FROM stock_movements sm
            INNER JOIN materials m ON m.id = sm.material_id
            INNER JOIN locations l ON l.id = sm.location_id
            INNER JOIN warehouses w ON w.id = l.warehouse_id
            INNER JOIN users usr ON usr.id = sm.user_id
            $whereSql
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_transactions' => 0,
            'total_in'           => 0,
            'total_out'          => 0,
            'total_scrap'        => 0,
        ];

        $row['net_change'] = (float) $row['total_in'] - (float) $row['total_out'];

        return $row;
    }

    public function getHistoricalSummary(array $filters): array
    {
        $conditions = [
            'l.is_active = 1',
            'w.is_active = 1'
        ];
        $params = [];

        if (!empty($filters['start_date'])) {
            $conditions[] = 'DATE(sm.created_at) >= :start_date';
            $params['start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $conditions[] = 'DATE(sm.created_at) <= :end_date';
            $params['end_date'] = $filters['end_date'];
        }

        if (!empty($filters['warehouse_id'])) {
            $conditions[] = 'w.id = :warehouse_id';
            $params['warehouse_id'] = (int) $filters['warehouse_id'];
        }

        $whereSql = 'WHERE ' . implode(' AND ', $conditions);

        $sql = "
            SELECT 
                DATE(sm.created_at) AS movement_date,
                COUNT(*) AS total_transactions,
                SUM(CASE WHEN sm.movement_type IN ('IN', 'TRANSFER_IN', 'RETURN') THEN 1 ELSE 0 END) AS in_count,
                SUM(CASE WHEN sm.movement_type IN ('OUT', 'TRANSFER_OUT') THEN 1 ELSE 0 END) AS out_count,
                SUM(CASE WHEN sm.movement_type = 'ADJUSTMENT' THEN 1 ELSE 0 END) AS adj_count,
                COALESCE(SUM(CASE WHEN sm.movement_type IN ('IN', 'TRANSFER_IN', 'RETURN') THEN sm.quantity ELSE 0 END), 0) AS total_in_qty,
                COALESCE(SUM(CASE WHEN sm.movement_type IN ('OUT', 'TRANSFER_OUT') THEN sm.quantity ELSE 0 END), 0) AS total_out_qty,
                COALESCE(SUM(CASE 
                    WHEN sm.movement_type IN ('IN', 'TRANSFER_IN', 'RETURN') THEN sm.quantity 
                    WHEN sm.movement_type IN ('OUT', 'TRANSFER_OUT') THEN -sm.quantity
                    ELSE 0
                END), 0) AS net_change_qty
            FROM stock_movements sm
            INNER JOIN locations l ON l.id = sm.location_id
            INNER JOIN warehouses w ON w.id = l.warehouse_id
            $whereSql
            GROUP BY DATE(sm.created_at)
            ORDER BY movement_date DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildMovementsWhereClause(array $filters): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['start_date'])) {
            $conditions[] = 'DATE(sm.created_at) >= :start_date';
            $params['start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $conditions[] = 'DATE(sm.created_at) <= :end_date';
            $params['end_date'] = $filters['end_date'];
        }

        if (!empty($filters['material_id'])) {
            $conditions[] = 'sm.material_id = :material_id';
            $params['material_id'] = (int) $filters['material_id'];
        }

        if (!empty($filters['warehouse_id'])) {
            $conditions[] = 'l.warehouse_id = :warehouse_id';
            $params['warehouse_id'] = (int) $filters['warehouse_id'];
        }

        if (!empty($filters['movement_type'])) {
            $conditions[] = 'sm.movement_type = :movement_type';
            $params['movement_type'] = $filters['movement_type'];
        }

        if (!empty($filters['user_id'])) {
            $conditions[] = 'sm.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }

        if (isset($filters['is_scrap']) && $filters['is_scrap'] !== '') {
            if ($filters['is_scrap'] === '1' || $filters['is_scrap'] === 'yes') {
                $conditions[] = "(sm.movement_type = 'OUT' AND (sm.description LIKE '%Fire%' OR sm.description LIKE '%Hurda%' OR sm.description LIKE '%fire%' OR sm.description LIKE '%hurda%'))";
            } elseif ($filters['is_scrap'] === '0' || $filters['is_scrap'] === 'no') {
                $conditions[] = "NOT (sm.movement_type = 'OUT' AND (sm.description LIKE '%Fire%' OR sm.description LIKE '%Hurda%' OR sm.description LIKE '%fire%' OR sm.description LIKE '%hurda%'))";
            }
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(m.name LIKE :search OR m.code LIKE :search OR sm.reference_no LIKE :search OR sm.description LIKE :search)';
            $params['search'] = '%' . trim($filters['search']) . '%';
        }

        $whereSql = '';
        if (!empty($conditions)) {
            $whereSql = 'WHERE ' . implode(' AND ', $conditions);
        }

        return [$whereSql, $params];
    }
}

