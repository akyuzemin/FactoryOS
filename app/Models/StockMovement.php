<?php

class StockMovement
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAll(): array
    {
        return $this->getFiltered([], 1, 1000000);
    }

    public function getFiltered(array $filters, int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        [$whereSql, $params] = $this->buildWhereClause($filters);

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
            $whereSql
            ORDER BY sm.created_at DESC, sm.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }

        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFilteredCount(array $filters): int
    {
        [$whereSql, $params] = $this->buildWhereClause($filters);

        $sql = "
            SELECT COUNT(*)
            FROM stock_movements sm
            INNER JOIN materials m
                ON m.id = sm.material_id
            INNER JOIN locations l
                ON l.id = sm.location_id
            INNER JOIN warehouses w
                ON w.id = l.warehouse_id
            INNER JOIN users usr
                ON usr.id = sm.user_id
            $whereSql
        ";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }

        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function getSummaryStats(array $filters = []): array
    {
        [$whereSql, $params] = $this->buildWhereClause($filters);

        $sql = "
            SELECT
                COUNT(*) AS total_count,
                SUM(CASE WHEN sm.movement_type IN ('IN', 'RETURN') THEN 1 ELSE 0 END) AS total_in_count,
                SUM(CASE WHEN sm.movement_type IN ('OUT', 'SCRAP') THEN 1 ELSE 0 END) AS total_out_count,
                SUM(CASE WHEN sm.movement_type IN ('TRANSFER_IN', 'TRANSFER_OUT') THEN 1 ELSE 0 END) AS total_transfer_count
            FROM stock_movements sm
            INNER JOIN materials m
                ON m.id = sm.material_id
            INNER JOIN locations l
                ON l.id = sm.location_id
            INNER JOIN warehouses w
                ON w.id = l.warehouse_id
            INNER JOIN users usr
                ON usr.id = sm.user_id
            $whereSql
        ";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_count'          => (int) ($row['total_count'] ?? 0),
            'total_in_count'       => (int) ($row['total_in_count'] ?? 0),
            'total_out_count'      => (int) ($row['total_out_count'] ?? 0),
            'total_transfer_count' => (int) ($row['total_transfer_count'] ?? 0),
        ];
    }

    public function getFilterOptions(): array
    {
        $materialStmt = $this->pdo->prepare(
            'SELECT id, code, name
             FROM materials
             WHERE is_active = 1
             ORDER BY name ASC'
        );
        $materialStmt->execute();

        $warehouseStmt = $this->pdo->prepare(
            'SELECT id, code, name
             FROM warehouses
             WHERE is_active = 1
             ORDER BY name ASC'
        );
        $warehouseStmt->execute();

        $locationStmt = $this->pdo->prepare(
            'SELECT
                l.id,
                l.warehouse_id,
                l.code,
                l.name,
                w.name AS warehouse_name
             FROM locations l
             INNER JOIN warehouses w
                ON w.id = l.warehouse_id
             WHERE l.is_active = 1 AND w.is_active = 1
             ORDER BY w.name ASC, l.name ASC'
        );
        $locationStmt->execute();

        $userStmt = $this->pdo->prepare(
            'SELECT id, username, first_name, last_name
             FROM users
             WHERE is_active = 1
             ORDER BY first_name ASC, last_name ASC'
        );
        $userStmt->execute();

        return [
            'materials'  => $materialStmt->fetchAll(PDO::FETCH_ASSOC),
            'warehouses' => $warehouseStmt->fetchAll(PDO::FETCH_ASSOC),
            'locations'  => $locationStmt->fetchAll(PDO::FETCH_ASSOC),
            'users'      => $userStmt->fetchAll(PDO::FETCH_ASSOC),
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

    private function buildWhereClause(array $filters): array
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

        if (!empty($filters['location_id'])) {
            $conditions[] = 'sm.location_id = :location_id';
            $params['location_id'] = (int) $filters['location_id'];
        }

        if (!empty($filters['movement_type'])) {
            $conditions[] = 'sm.movement_type = :movement_type';
            $params['movement_type'] = $filters['movement_type'];
        }

        if (!empty($filters['user_id'])) {
            $conditions[] = 'sm.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
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
