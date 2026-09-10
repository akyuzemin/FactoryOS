<?php

class Material
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAllActive(): array
    {
        $sql = "
            SELECT
                m.id,
                m.code,
                m.name,
                c.name AS category,
                u.name AS unit,
                u.symbol,
                m.min_stock,
                m.max_stock,
                m.unit_price,
                m.currency,
                m.description,
                COALESCE(SUM(sb.quantity), 0) AS total_stock
            FROM materials m
            INNER JOIN categories c
                ON c.id = m.category_id
            INNER JOIN units u
                ON u.id = m.unit_id
            LEFT JOIN stock_balances sb
                ON sb.material_id = m.id
            WHERE m.is_active = 1
            GROUP BY m.id, m.code, m.name, c.name, u.name, u.symbol, m.min_stock, m.max_stock, m.unit_price, m.currency, m.description
            ORDER BY m.name ASC
        ";

        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Bakım modülü ve iş emirleri için aktif yedek parça malzemelerini döner.
     */
    public function getSpareParts(): array
    {
        $sql = "
            SELECT id, code, name, unit_price, currency 
            FROM materials 
            WHERE is_active = 1 
              AND (code LIKE '%SP-%' OR category_id IN (SELECT id FROM categories WHERE name LIKE '%Yedek%')) 
            ORDER BY name ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Üretim / MES için aktif mamul ve yarı mamul malzemelerini döner.
     */
    public function getFinishedGoodsForProduction(): array
    {
        $sql = "
            SELECT m.id, m.code, m.name 
            FROM materials m
            WHERE m.is_active = 1 
              AND (
                  m.id IN (SELECT DISTINCT output_material_id FROM recipes WHERE output_material_id IS NOT NULL)
                  OR m.category_id IN (SELECT id FROM categories WHERE code = 'MAMUL' OR name LIKE '%Mamul%')
                  OR m.code LIKE 'SOL-MOD%' OR m.code LIKE 'PNL-%'
              )
            ORDER BY m.name ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($results)) {
            $sqlFallback = "SELECT id, code, name FROM materials WHERE is_active = 1 ORDER BY name ASC";
            $stmtFallback = $this->pdo->prepare($sqlFallback);
            $stmtFallback->execute();
            $results = $stmtFallback->fetchAll(PDO::FETCH_ASSOC);
        }

        return $results;
    }

    public function getMaterialDetail(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                m.id,
                m.code,
                m.name,
                m.category_id,
                m.unit_id,
                c.name AS category,
                u.name AS unit,
                u.symbol,
                m.min_stock,
                m.max_stock,
                m.unit_price,
                m.currency,
                m.description,
                COALESCE(SUM(sb.quantity), 0) AS total_stock
            FROM materials m
            INNER JOIN categories c ON c.id = m.category_id
            INNER JOIN units u ON u.id = m.unit_id
            LEFT JOIN stock_balances sb ON sb.material_id = m.id
            WHERE m.id = :id AND m.is_active = 1
            GROUP BY m.id, m.code, m.name, m.category_id, m.unit_id, c.name, u.name, u.symbol, m.min_stock, m.max_stock, m.unit_price, m.currency, m.description
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getLocationBalances(int $materialId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                sb.id,
                sb.location_id,
                sb.quantity,
                sb.updated_at,
                l.code AS location_code,
                l.name AS location_name,
                w.id AS warehouse_id,
                w.code AS warehouse_code,
                w.name AS warehouse_name
            FROM stock_balances sb
            JOIN locations l ON sb.location_id = l.id
            JOIN warehouses w ON l.warehouse_id = w.id
            WHERE sb.material_id = :material_id AND sb.quantity > 0
            ORDER BY w.name ASC, l.name ASC
        ");
        $stmt->execute([':material_id' => $materialId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentMovements(int $materialId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                sm.*,
                l.code AS location_code,
                l.name AS location_name,
                w.code AS warehouse_code,
                w.name AS warehouse_name,
                u.username
            FROM stock_movements sm
            JOIN locations l ON sm.location_id = l.id
            JOIN warehouses w ON l.warehouse_id = w.id
            LEFT JOIN users u ON sm.user_id = u.id
            WHERE sm.material_id = :material_id
            ORDER BY sm.id DESC
            LIMIT " . (int)$limit . "
        ");
        $stmt->execute([':material_id' => $materialId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFormOptions(): array
    {
        $categoryStmt = $this->pdo->prepare(
            'SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC'
        );
        $categoryStmt->execute();

        $unitStmt = $this->pdo->prepare(
            'SELECT id, name, symbol FROM units WHERE is_active = 1 ORDER BY name ASC'
        );
        $unitStmt->execute();

        return [
            'categories' => $categoryStmt->fetchAll(),
            'units' => $unitStmt->fetchAll(),
        ];
    }

    public function findActiveById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, code, name, category_id, unit_id, min_stock, max_stock, description
             FROM materials
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        $material = $stmt->fetch();

        return $material ?: null;
    }

    public function create(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO materials
                (code, name, category_id, unit_id, min_stock, max_stock, description, is_active)
             VALUES
                (:code, :name, :category_id, :unit_id, :min_stock, :max_stock, :description, 1)'
        );
        $stmt->execute($data);
    }

    public function update(int $id, array $data): void
    {
        $data['id'] = $id;

        $stmt = $this->pdo->prepare(
            'UPDATE materials
             SET code = :code,
                 name = :name,
                 category_id = :category_id,
                 unit_id = :unit_id,
                 min_stock = :min_stock,
                 max_stock = :max_stock,
                 description = :description
             WHERE id = :id AND is_active = 1'
        );
        $stmt->execute($data);
    }

    public function deactivate(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE materials SET is_active = 0 WHERE id = :id AND is_active = 1'
        );
        $stmt->execute(['id' => $id]);
    }
}