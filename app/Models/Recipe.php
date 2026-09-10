<?php

class Recipe
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Tüm reçeteleri ilişkili mamul ve kalem sayısı ile listeler.
     */
    public function getAll(?string $search = null, ?int $isActive = null): array
    {
        $sql = "
            SELECT 
                r.id,
                r.code,
                r.name,
                r.output_material_id,
                m.code AS output_material_code,
                m.name AS output_material_name,
                u.symbol AS output_unit_symbol,
                r.base_quantity,
                r.description,
                r.is_active,
                r.created_at,
                r.updated_at,
                COUNT(ri.id) AS item_count,
                COALESCE(SUM(ri.quantity), 0) AS total_material_qty
            FROM recipes r
            LEFT JOIN materials m ON m.id = r.output_material_id
            LEFT JOIN units u ON u.id = m.unit_id
            LEFT JOIN recipe_items ri ON ri.recipe_id = r.id
            WHERE 1=1
        ";

        $params = [];

        if ($search !== null && trim($search) !== '') {
            $sql .= " AND (r.code LIKE :search OR r.name LIKE :search OR r.description LIKE :search)";
            $params[':search'] = '%' . trim($search) . '%';
        }

        if ($isActive !== null) {
            $sql .= " AND r.is_active = :is_active";
            $params[':is_active'] = $isActive;
        }

        $sql .= "
            GROUP BY r.id, r.code, r.name, r.output_material_id, m.code, m.name, u.symbol, r.base_quantity, r.description, r.is_active, r.created_at, r.updated_at
            ORDER BY r.is_active DESC, r.created_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tekil reçete detayını döner.
     */
    public function getById(int $id): ?array
    {
        $sql = "
            SELECT 
                r.id,
                r.code,
                r.name,
                r.output_material_id,
                m.code AS output_material_code,
                m.name AS output_material_name,
                u.symbol AS output_unit_symbol,
                r.base_quantity,
                r.description,
                r.is_active,
                r.created_at,
                r.updated_at
            FROM recipes r
            LEFT JOIN materials m ON m.id = r.output_material_id
            LEFT JOIN units u ON u.id = m.unit_id
            WHERE r.id = :id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Bir mamul/çıktı malzemesi için aktif reçeteyi döner (veya en son geçerli reçete).
     */
    public function findActiveRecipeByProduct(int $productMaterialId): ?int
    {
        if ($productMaterialId <= 0) {
            return null;
        }

        // 1. Aktif reçete ara
        $stmt = $this->pdo->prepare("SELECT id FROM recipes WHERE output_material_id = :mat_id AND is_active = 1 ORDER BY id DESC LIMIT 1");
        $stmt->execute([':mat_id' => $productMaterialId]);
        $recipeId = (int)$stmt->fetchColumn();

        if ($recipeId > 0) {
            return $recipeId;
        }

        // 2. Pasif/herhangi bir reçete ara
        $stmtFallback = $this->pdo->prepare("SELECT id FROM recipes WHERE output_material_id = :mat_id ORDER BY id DESC LIMIT 1");
        $stmtFallback->execute([':mat_id' => $productMaterialId]);
        $recipeId = (int)$stmtFallback->fetchColumn();

        if ($recipeId > 0) {
            return $recipeId;
        }

        // 3. Genel aktif ilk reçete fallback
        $stmtDefault = $this->pdo->query("SELECT id FROM recipes WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
        $defaultId = (int)$stmtDefault->fetchColumn();

        return $defaultId > 0 ? $defaultId : 3;
    }

    /**
     * Bir reçeteye ait tüm hammadde/bileşen kalemlerini döner.
     */
    public function getItems(int $recipeId): array
    {
        $sql = "
            SELECT 
                ri.id,
                ri.recipe_id,
                ri.material_id,
                m.code AS material_code,
                m.name AS material_name,
                c.name AS category_name,
                u.symbol AS unit_symbol,
                ri.quantity,
                ri.scrap_rate_pct,
                ri.is_critical,
                ri.created_at,
                (SELECT COALESCE(SUM(sb.quantity), 0) FROM stock_balances sb WHERE sb.material_id = m.id) AS current_stock
            FROM recipe_items ri
            INNER JOIN materials m ON m.id = ri.material_id
            LEFT JOIN categories c ON c.id = m.category_id
            LEFT JOIN units u ON u.id = m.unit_id
            WHERE ri.recipe_id = :recipe_id
            ORDER BY ri.is_critical DESC, m.name ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':recipe_id' => $recipeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reçete için seçilebilir aktif malzemeleri döner.
     */
    public function getAvailableMaterials(): array
    {
        $sql = "
            SELECT 
                m.id,
                m.code,
                m.name,
                c.name AS category_name,
                u.symbol AS unit_symbol,
                m.min_stock,
                (SELECT COALESCE(SUM(sb.quantity), 0) FROM stock_balances sb WHERE sb.material_id = m.id) AS current_stock
            FROM materials m
            LEFT JOIN categories c ON c.id = m.category_id
            LEFT JOIN units u ON u.id = m.unit_id
            WHERE m.is_active = 1
            ORDER BY c.name ASC, m.name ASC
        ";

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Yeni bir reçete ve kalemlerini transaction ile güvenli şekilde oluşturur.
     */
    public function create(array $data, array $items = []): int
    {
        $this->validateRecipeData($data);
        $this->validateItems($items);

        $this->pdo->beginTransaction();

        try {
            // 1. Kod benzersizlik kontrolü
            $checkStmt = $this->pdo->prepare("SELECT id FROM recipes WHERE code = :code LIMIT 1");
            $checkStmt->execute([':code' => trim($data['code'])]);
            if ($checkStmt->fetchColumn()) {
                throw new InvalidArgumentException('Bu reçete kodu (' . htmlspecialchars($data['code']) . ') zaten kullanımda.');
            }

            // 2. Reçete Başlığını Ekle
            $sql = "
                INSERT INTO recipes (code, name, output_material_id, base_quantity, description, is_active)
                VALUES (:code, :name, :output_material_id, :base_quantity, :description, :is_active)
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':code'               => trim($data['code']),
                ':name'               => trim($data['name']),
                ':output_material_id' => !empty($data['output_material_id']) ? (int)$data['output_material_id'] : null,
                ':base_quantity'      => (float)($data['base_quantity'] ?? 1.0),
                ':description'        => !empty($data['description']) ? trim($data['description']) : null,
                ':is_active'          => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            ]);

            $recipeId = (int)$this->pdo->lastInsertId();

            // 3. Reçete Kalemlerini Ekle
            $itemSql = "
                INSERT INTO recipe_items (recipe_id, material_id, quantity, scrap_rate_pct, is_critical)
                VALUES (:recipe_id, :material_id, :quantity, :scrap_rate_pct, :is_critical)
            ";
            $itemStmt = $this->pdo->prepare($itemSql);

            foreach ($items as $item) {
                $itemStmt->execute([
                    ':recipe_id'      => $recipeId,
                    ':material_id'    => (int)$item['material_id'],
                    ':quantity'       => (float)$item['quantity'],
                    ':scrap_rate_pct' => (float)($item['scrap_rate_pct'] ?? 0.0),
                    ':is_critical'    => isset($item['is_critical']) ? (int)$item['is_critical'] : 1,
                ]);
            }

            $this->pdo->commit();
            return $recipeId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Mevcut bir reçeteyi ve kalemlerini günceller.
     */
    public function update(int $id, array $data, ?array $items = null): bool
    {
        $existing = $this->getById($id);
        if (!$existing) {
            throw new InvalidArgumentException('Güncellenecek reçete bulunamadı (ID: ' . $id . ').');
        }

        $this->validateRecipeData($data, $id);

        if ($items !== null) {
            $this->validateItems($items);
        }

        $this->pdo->beginTransaction();

        try {
            // 1. Kod benzersizlik kontrolü (kendisi hariç)
            $checkStmt = $this->pdo->prepare("SELECT id FROM recipes WHERE code = :code AND id != :id LIMIT 1");
            $checkStmt->execute([':code' => trim($data['code']), ':id' => $id]);
            if ($checkStmt->fetchColumn()) {
                throw new InvalidArgumentException('Bu reçete kodu (' . htmlspecialchars($data['code']) . ') başka bir reçete tarafından kullanılıyor.');
            }

            // 2. Reçete Başlığını Güncelle
            $sql = "
                UPDATE recipes
                SET code = :code,
                    name = :name,
                    output_material_id = :output_material_id,
                    base_quantity = :base_quantity,
                    description = :description,
                    is_active = :is_active
                WHERE id = :id
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':id'                 => $id,
                ':code'               => trim($data['code']),
                ':name'               => trim($data['name']),
                ':output_material_id' => !empty($data['output_material_id']) ? (int)$data['output_material_id'] : null,
                ':base_quantity'      => (float)($data['base_quantity'] ?? 1.0),
                ':description'        => !empty($data['description']) ? trim($data['description']) : null,
                ':is_active'          => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            ]);

            // 3. Kalemler verilmişse yenile
            if ($items !== null) {
                // Eski kalemleri temizle
                $delStmt = $this->pdo->prepare("DELETE FROM recipe_items WHERE recipe_id = :recipe_id");
                $delStmt->execute([':recipe_id' => $id]);

                // Yeni kalemleri ekle
                $itemSql = "
                    INSERT INTO recipe_items (recipe_id, material_id, quantity, scrap_rate_pct, is_critical)
                    VALUES (:recipe_id, :material_id, :quantity, :scrap_rate_pct, :is_critical)
                ";
                $itemStmt = $this->pdo->prepare($itemSql);

                foreach ($items as $item) {
                    $itemStmt->execute([
                        ':recipe_id'      => $id,
                        ':material_id'    => (int)$item['material_id'],
                        ':quantity'       => (float)$item['quantity'],
                        ':scrap_rate_pct' => (float)($item['scrap_rate_pct'] ?? 0.0),
                        ':is_critical'    => isset($item['is_critical']) ? (int)$item['is_critical'] : 1,
                    ]);
                }
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Reçetenin aktif/pasif durumunu değiştirir.
     */
    public function toggleActive(int $id): bool
    {
        $existing = $this->getById($id);
        if (!$existing) {
            throw new InvalidArgumentException('Reçete bulunamadı.');
        }

        $newStatus = $existing['is_active'] ? 0 : 1;
        $stmt = $this->pdo->prepare("UPDATE recipes SET is_active = :status WHERE id = :id");
        return $stmt->execute([':status' => $newStatus, ':id' => $id]);
    }

    /**
     * Reçeteyi ve ilişkili kalemlerini siler.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM recipes WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Reçete doğrulama kuralları.
     */
    private function validateRecipeData(array $data, ?int $ignoreId = null): void
    {
        if (empty(trim($data['code'] ?? ''))) {
            throw new InvalidArgumentException('Reçete kodu boş bırakılamaz.');
        }

        if (empty(trim($data['name'] ?? ''))) {
            throw new InvalidArgumentException('Reçete adı boş bırakılamaz.');
        }

        $baseQty = isset($data['base_quantity']) ? (float)$data['base_quantity'] : 1.0;
        if ($baseQty <= 0) {
            throw new InvalidArgumentException('Baz üretim miktarı 0\'dan büyük olmalıdır.');
        }

        if (!empty($data['output_material_id'])) {
            $stmt = $this->pdo->prepare("SELECT id FROM materials WHERE id = :id AND is_active = 1 LIMIT 1");
            $stmt->execute([':id' => (int)$data['output_material_id']]);
            if (!$stmt->fetchColumn()) {
                throw new InvalidArgumentException('Seçilen nihai mamul geçerli veya aktif bir malzeme değil.');
            }
        }
    }

    /**
     * Reçete kalemleri doğrulama kuralları.
     */
    private function validateItems(array $items): void
    {
        $seenMaterialIds = [];

        foreach ($items as $idx => $item) {
            $materialId = (int)($item['material_id'] ?? 0);
            $qty = (float)($item['quantity'] ?? 0);
            $scrap = (float)($item['scrap_rate_pct'] ?? 0);

            if ($materialId <= 0) {
                throw new InvalidArgumentException('Geçersiz malzeme seçimi (Satır: ' . ($idx + 1) . ').');
            }

            // Çift malzeme ekleme engeli
            if (isset($seenMaterialIds[$materialId])) {
                throw new InvalidArgumentException('Aynı malzeme (ID: ' . $materialId . ') bir reçeteye birden fazla kez eklenemez.');
            }
            $seenMaterialIds[$materialId] = true;

            // Miktar kontrolü
            if ($qty <= 0) {
                throw new InvalidArgumentException('Malzeme miktarı 0 veya negatif olamaz (Satır: ' . ($idx + 1) . ').');
            }

            // Fire oranı kontrolü
            if ($scrap < 0 || $scrap > 100) {
                throw new InvalidArgumentException('Fire oranı %0 ile %100 arasında olmalıdır (Satır: ' . ($idx + 1) . ').');
            }

            // Malzemenin veritabanında aktif olduğu kontrolü
            $stmt = $this->pdo->prepare("SELECT id, name, is_active FROM materials WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $materialId]);
            $matRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$matRow) {
                throw new InvalidArgumentException('Sistemde bulunmayan malzeme reçeteye eklenemez (ID: ' . $materialId . ').');
            }

            if ((int)$matRow['is_active'] !== 1) {
                throw new InvalidArgumentException('Pasif durumdaki malzeme (' . htmlspecialchars($matRow['name']) . ') reçeteye eklenemez.');
            }
        }
    }
}
