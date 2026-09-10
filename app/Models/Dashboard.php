<?php

class Dashboard
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getTotalMaterials(): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM materials
            WHERE is_active = 1
        ";

        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    public function getTotalWarehouses(): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM warehouses
            WHERE is_active = 1
        ";

        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    public function getTotalStock(): float
    {
        $sql = "
            SELECT COALESCE(SUM(sb.quantity), 0)
            FROM stock_balances sb
            INNER JOIN materials m
                ON m.id = sb.material_id AND m.is_active = 1
            INNER JOIN locations l
                ON l.id = sb.location_id AND l.is_active = 1
            INNER JOIN warehouses w
                ON w.id = l.warehouse_id AND w.is_active = 1
        ";

        return (float) $this->pdo->query($sql)->fetchColumn();
    }

    public function getCriticalStockCount(): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM (
                SELECT
                    m.id,
                    m.min_stock,
                    COALESCE(SUM(sb.quantity), 0) AS current_stock
                FROM materials m
                LEFT JOIN (
                    SELECT sb2.material_id, sb2.quantity
                    FROM stock_balances sb2
                    INNER JOIN locations l2
                        ON l2.id = sb2.location_id AND l2.is_active = 1
                    INNER JOIN warehouses w2
                        ON w2.id = l2.warehouse_id AND w2.is_active = 1
                ) sb ON sb.material_id = m.id
                WHERE m.is_active = 1
                GROUP BY m.id, m.min_stock
                HAVING current_stock <= m.min_stock
            ) AS critical_materials
        ";

        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    public function getTodayMovementsSummary(): array
    {
        $sql = "
            SELECT
                COALESCE(SUM(CASE
                    WHEN movement_type IN ('IN', 'TRANSFER_IN', 'RETURN') THEN quantity
                    ELSE 0
                END), 0) AS in_quantity,
                COALESCE(SUM(CASE
                    WHEN movement_type IN ('IN', 'TRANSFER_IN', 'RETURN') THEN 1
                    ELSE 0
                END), 0) AS in_count,
                COALESCE(SUM(CASE
                    WHEN movement_type IN ('OUT', 'TRANSFER_OUT') THEN quantity
                    ELSE 0
                END), 0) AS out_quantity,
                COALESCE(SUM(CASE
                    WHEN movement_type IN ('OUT', 'TRANSFER_OUT') THEN 1
                    ELSE 0
                END), 0) AS out_count
            FROM stock_movements
            WHERE DATE(created_at) = CURDATE()
        ";

        $stmt = $this->pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'in_quantity'  => (float) ($result['in_quantity'] ?? 0),
            'in_count'     => (int) ($result['in_count'] ?? 0),
            'out_quantity' => (float) ($result['out_quantity'] ?? 0),
            'out_count'    => (int) ($result['out_count'] ?? 0),
        ];
    }

    public function getCriticalMaterials(int $limit = 5): array
    {
        $sql = "
            SELECT
                m.id,
                m.code,
                m.name,
                c.name AS category_name,
                u.symbol AS unit_symbol,
                m.min_stock,
                COALESCE(SUM(sb.quantity), 0) AS current_stock,
                CASE
                    WHEN COALESCE(SUM(sb.quantity), 0) <= 0 THEN 'OUT_OF_STOCK'
                    ELSE 'CRITICAL'
                END AS stock_status
            FROM materials m
            INNER JOIN categories c
                ON c.id = m.category_id
            INNER JOIN units u
                ON u.id = m.unit_id
            LEFT JOIN (
                SELECT sb2.material_id, sb2.quantity
                FROM stock_balances sb2
                INNER JOIN locations l2
                    ON l2.id = sb2.location_id AND l2.is_active = 1
                INNER JOIN warehouses w2
                    ON w2.id = l2.warehouse_id AND w2.is_active = 1
            ) sb ON sb.material_id = m.id
            WHERE m.is_active = 1
            GROUP BY m.id, m.code, m.name, c.name, u.symbol, m.min_stock
            HAVING current_stock <= m.min_stock
            ORDER BY current_stock ASC, (m.min_stock - current_stock) DESC, m.name ASC
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getWarehouseStockSummary(): array
    {
        $sql = "
            SELECT
                w.id,
                w.code,
                w.name,
                COUNT(DISTINCT CASE WHEN sb.quantity > 0 THEN sb.material_id END) AS material_count,
                COALESCE(SUM(sb.quantity), 0) AS total_quantity
            FROM warehouses w
            LEFT JOIN locations l
                ON l.warehouse_id = w.id AND l.is_active = 1
            LEFT JOIN (
                SELECT sb2.location_id, sb2.material_id, sb2.quantity
                FROM stock_balances sb2
                INNER JOIN materials m2
                    ON m2.id = sb2.material_id AND m2.is_active = 1
            ) sb ON sb.location_id = l.id
            WHERE w.is_active = 1
            GROUP BY w.id, w.code, w.name
            ORDER BY total_quantity DESC, w.name ASC
        ";

        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    /**
     * Günlük stok hareketleri trend verilerini döner.
     */
    public function getDailyMovementsTrend(int $days = 14): array
    {
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $stmt = $this->pdo->prepare("
            SELECT DATE(created_at) as mov_date, COUNT(*) as mov_count, SUM(quantity) as total_qty
            FROM stock_movements
            WHERE DATE(created_at) BETWEEN :s AND :e
            GROUP BY DATE(created_at)
            ORDER BY mov_date ASC
        ");
        $stmt->execute([':s' => $startDate, ':e' => $endDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dbMap = [];
        foreach ($rows as $r) {
            $dbMap[$r['mov_date']] = (int)$r['mov_count'];
        }

        // 14 günlük zaman çizelgesi
        $trend = [];
        for ($i = $days; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $shortDate = date('d M', strtotime($d));
            $count = $dbMap[$d] ?? 0;
            $trend[] = [
                'date' => $d,
                'label' => $shortDate,
                'count' => $count
            ];
        }
        return $trend;
    }

    /**
     * Kategorilere göre malzeme sayısı ve stok dağılımını döner.
     */
    public function getCategoryDistribution(): array
    {
        $stmt = $this->pdo->query("
            SELECT c.name as category_name, COUNT(m.id) as material_count, COALESCE(SUM(sb.quantity), 0) as total_stock
            FROM categories c
            LEFT JOIN materials m ON m.category_id = c.id AND m.is_active = 1
            LEFT JOIN stock_balances sb ON sb.material_id = m.id
            GROUP BY c.id, c.name
            ORDER BY total_stock DESC, material_count DESC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalStockAll = 0;
        foreach ($rows as $r) {
            $totalStockAll += (float)$r['total_stock'];
        }

        $colors = ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];
        $result = [];
        foreach ($rows as $i => $r) {
            $pct = $totalStockAll > 0 ? (($r['total_stock'] / $totalStockAll) * 100) : (100 / max(1, count($rows)));
            $result[] = [
                'name' => $r['category_name'],
                'stock' => (float)$r['total_stock'],
                'count' => (int)$r['material_count'],
                'percentage' => round($pct, 1),
                'color' => $colors[$i % count($colors)]
            ];
        }
        return $result;
    }

    /**
     * Tüm aktif malzemelerin mevcut stok bakiyelerini ve kritik stok durumlarını döner.
     * Stok miktarına göre büyükten küçüğe sıralanır.
     */
    public function getMaterialStockDistribution(?int $limit = null): array
    {
        $sql = "
            SELECT
                m.id,
                m.code,
                m.name,
                c.name AS category_name,
                u.symbol AS unit_symbol,
                m.min_stock,
                COALESCE(SUM(sb.quantity), 0) AS current_stock,
                CASE
                    WHEN COALESCE(SUM(sb.quantity), 0) <= 0 THEN 'OUT_OF_STOCK'
                    WHEN COALESCE(SUM(sb.quantity), 0) <= m.min_stock THEN 'CRITICAL'
                    ELSE 'NORMAL'
                END AS stock_status
            FROM materials m
            LEFT JOIN categories c
                ON c.id = m.category_id
            LEFT JOIN units u
                ON u.id = m.unit_id
            LEFT JOIN (
                SELECT sb2.material_id, sb2.quantity
                FROM stock_balances sb2
                INNER JOIN locations l2
                    ON l2.id = sb2.location_id AND l2.is_active = 1
                INNER JOIN warehouses w2
                    ON w2.id = l2.warehouse_id AND w2.is_active = 1
            ) sb ON sb.material_id = m.id
            WHERE m.is_active = 1
            GROUP BY m.id, m.code, m.name, c.name, u.symbol, m.min_stock
            ORDER BY current_stock DESC, m.name ASC
        ";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}