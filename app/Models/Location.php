<?php

class Location
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAllActive(?int $warehouseId = null): array
    {
        $sql = "
            SELECT
                l.id,
                l.code,
                l.name,
                l.description,
                l.is_active,
                l.warehouse_id,
                w.code AS warehouse_code,
                w.name AS warehouse_name,
                COALESCE(SUM(sb.quantity), 0) AS current_stock
            FROM locations l
            INNER JOIN warehouses w
                ON w.id = l.warehouse_id
            LEFT JOIN stock_balances sb
                ON sb.location_id = l.id
            WHERE l.is_active = 1 AND w.is_active = 1
        ";

        $params = [];

        if ($warehouseId !== null && $warehouseId > 0) {
            $sql .= " AND l.warehouse_id = :warehouse_id";
            $params['warehouse_id'] = $warehouseId;
        }

        $sql .= " GROUP BY l.id, l.code, l.name, l.description, l.is_active, l.warehouse_id, w.code, w.name";
        $sql .= " ORDER BY w.name ASC, l.name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findActiveById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT l.id, l.warehouse_id, l.code, l.name, l.description, l.is_active,
                    w.code AS warehouse_code, w.name AS warehouse_name
             FROM locations l
             INNER JOIN warehouses w
                ON w.id = l.warehouse_id
             WHERE l.id = :id AND l.is_active = 1 AND w.is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        $location = $stmt->fetch(PDO::FETCH_ASSOC);

        return $location ?: null;
    }

    public function getWarehouseOptions(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, code, name
             FROM warehouses
             WHERE is_active = 1
             ORDER BY name ASC'
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isCodeExists(int $warehouseId, string $code, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM locations WHERE warehouse_id = :warehouse_id AND code = :code AND is_active = 1';
        $params = [
            'warehouse_id' => $warehouseId,
            'code'         => $code,
        ];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function getTotalStock(int $locationId): float
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(quantity), 0)
             FROM stock_balances
             WHERE location_id = :location_id'
        );
        $stmt->execute(['location_id' => $locationId]);

        return (float) $stmt->fetchColumn();
    }

    public function create(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO locations (warehouse_id, code, name, description, is_active)
             VALUES (:warehouse_id, :code, :name, :description, 1)'
        );
        $stmt->execute($data);
    }

    public function update(int $id, array $data): void
    {
        $data['id'] = $id;

        $stmt = $this->pdo->prepare(
            'UPDATE locations
             SET warehouse_id = :warehouse_id,
                 code = :code,
                 name = :name,
                 description = :description
             WHERE id = :id AND is_active = 1'
        );
        $stmt->execute($data);
    }

    public function deactivate(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE locations
             SET is_active = 0
             WHERE id = :id AND is_active = 1'
        );
        $stmt->execute(['id' => $id]);
    }
}

