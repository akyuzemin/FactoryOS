<?php

class Stock
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getFormOptions(): array
    {
        $materialStmt = $this->pdo->prepare(
            'SELECT id, code, name
             FROM materials
             WHERE is_active = 1
             ORDER BY name ASC'
        );
        $materialStmt->execute();

        $locationStmt = $this->pdo->prepare(
            'SELECT
                l.id,
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

        return [
            'materials' => $materialStmt->fetchAll(),
            'locations' => $locationStmt->fetchAll(),
        ];
    }

    public function recordEntry(
        int $materialId,
        int $locationId,
        float $quantity,
        int $userId,
        ?string $description
    ): void {
        $this->pdo->beginTransaction();

        try {
            $materialStmt = $this->pdo->prepare(
                'SELECT id FROM materials WHERE id = :id AND is_active = 1 LIMIT 1'
            );
            $materialStmt->execute(['id' => $materialId]);

            if (!$materialStmt->fetchColumn()) {
                throw new InvalidArgumentException('Seçilen malzeme bulunamadı veya pasif durumda.');
            }

            $locationStmt = $this->pdo->prepare(
                'SELECT l.id
                 FROM locations l
                 INNER JOIN warehouses w ON w.id = l.warehouse_id
                 WHERE l.id = :id AND l.is_active = 1 AND w.is_active = 1
                 LIMIT 1'
            );
            $locationStmt->execute(['id' => $locationId]);

            if (!$locationStmt->fetchColumn()) {
                throw new InvalidArgumentException('Seçilen depo bulunamadı veya pasif durumda.');
            }

            $balanceStmt = $this->pdo->prepare(
                'SELECT quantity
                 FROM stock_balances
                 WHERE material_id = :material_id AND location_id = :location_id
                 FOR UPDATE'
            );
            $balanceStmt->execute([
                'material_id' => $materialId,
                'location_id' => $locationId,
            ]);

            if ($balanceStmt->fetchColumn() === false) {
                $insertBalanceStmt = $this->pdo->prepare(
                    'INSERT INTO stock_balances (material_id, location_id, quantity)
                     VALUES (:material_id, :location_id, :quantity)'
                );
                $insertBalanceStmt->execute([
                    'material_id' => $materialId,
                    'location_id' => $locationId,
                    'quantity' => $quantity,
                ]);
            } else {
                $updateBalanceStmt = $this->pdo->prepare(
                    'UPDATE stock_balances
                     SET quantity = quantity + :quantity
                     WHERE material_id = :material_id AND location_id = :location_id'
                );
                $updateBalanceStmt->execute([
                    'quantity' => $quantity,
                    'material_id' => $materialId,
                    'location_id' => $locationId,
                ]);
            }

            $movementStmt = $this->pdo->prepare(
                'INSERT INTO stock_movements
                    (material_id, location_id, user_id, movement_type, quantity, description)
                 VALUES
                    (:material_id, :location_id, :user_id, :movement_type, :quantity, :description)'
            );
            $movementStmt->execute([
                'material_id' => $materialId,
                'location_id' => $locationId,
                'user_id' => $userId,
                'movement_type' => 'IN',
                'quantity' => $quantity,
                'description' => $description,
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function recordOut(
        int $materialId,
        int $locationId,
        float $quantity,
        int $userId,
        ?string $description
    ): void {
        $this->pdo->beginTransaction();

        try {
            $materialStmt = $this->pdo->prepare(
                'SELECT id FROM materials WHERE id = :id AND is_active = 1 LIMIT 1'
            );
            $materialStmt->execute(['id' => $materialId]);

            if (!$materialStmt->fetchColumn()) {
                throw new InvalidArgumentException('Seçilen malzeme bulunamadı veya pasif durumda.');
            }

            $locationStmt = $this->pdo->prepare(
                'SELECT l.id
                 FROM locations l
                 INNER JOIN warehouses w ON w.id = l.warehouse_id
                 WHERE l.id = :id AND l.is_active = 1 AND w.is_active = 1
                 LIMIT 1'
            );
            $locationStmt->execute(['id' => $locationId]);

            if (!$locationStmt->fetchColumn()) {
                throw new InvalidArgumentException('Seçilen depo bulunamadı veya pasif durumda.');
            }

            $balanceStmt = $this->pdo->prepare(
                'SELECT quantity
                 FROM stock_balances
                 WHERE material_id = :material_id AND location_id = :location_id
                 FOR UPDATE'
            );
            $balanceStmt->execute([
                'material_id' => $materialId,
                'location_id' => $locationId,
            ]);

            $currentStock = $balanceStmt->fetchColumn();

            if ($currentStock === false || (float) $currentStock < $quantity) {
                $availableStock = $currentStock === false ? 0 : (float) $currentStock;
                throw new InvalidArgumentException(
                    'Yetersiz stok. Mevcut stok: ' . number_format($availableStock, 3, ',', '.')
                );
            }

            $updateBalanceStmt = $this->pdo->prepare(
                'UPDATE stock_balances
                 SET quantity = quantity - :quantity
                 WHERE material_id = :material_id AND location_id = :location_id'
            );
            $updateBalanceStmt->execute([
                'quantity' => $quantity,
                'material_id' => $materialId,
                'location_id' => $locationId,
            ]);

            $movementStmt = $this->pdo->prepare(
                'INSERT INTO stock_movements
                    (material_id, location_id, user_id, movement_type, quantity, description)
                 VALUES
                    (:material_id, :location_id, :user_id, :movement_type, :quantity, :description)'
            );
            $movementStmt->execute([
                'material_id' => $materialId,
                'location_id' => $locationId,
                'user_id' => $userId,
                'movement_type' => 'OUT',
                'quantity' => $quantity,
                'description' => $description,
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function recordScrap(
        int $materialId,
        int $locationId,
        float $quantity,
        int $userId,
        ?string $description
    ): void {
        if ($quantity <= 0 || !is_finite($quantity)) {
            throw new InvalidArgumentException('Fire miktarı sıfırdan büyük bir sayı olmalıdır.');
        }

        $scrapDescription = 'Fire/Hurda';

        if ($description !== null && $description !== '') {
            $scrapDescription .= ' | ' . $description;
        }

        $this->recordOut(
            $materialId,
            $locationId,
            $quantity,
            $userId,
            $scrapDescription
        );
    }

    public function recordTransfer(
        int $materialId,
        int $sourceLocationId,
        int $targetLocationId,
        float $quantity,
        int $userId,
        ?string $description
    ): void {
        if ($sourceLocationId === $targetLocationId) {
            throw new InvalidArgumentException('Kaynak ve hedef depo/raf aynı olamaz.');
        }

        $this->pdo->beginTransaction();

        try {
            $materialStmt = $this->pdo->prepare(
                'SELECT id FROM materials WHERE id = :id AND is_active = 1 LIMIT 1'
            );
            $materialStmt->execute(['id' => $materialId]);

            if (!$materialStmt->fetchColumn()) {
                throw new InvalidArgumentException('Seçilen malzeme bulunamadı veya pasif durumda.');
            }

            $locationStmt = $this->pdo->prepare(
                'SELECT l.id
                 FROM locations l
                 INNER JOIN warehouses w ON w.id = l.warehouse_id
                 WHERE l.id = :id AND l.is_active = 1 AND w.is_active = 1
                 LIMIT 1'
            );

            foreach ([$sourceLocationId, $targetLocationId] as $locationId) {
                $locationStmt->execute(['id' => $locationId]);

                if (!$locationStmt->fetchColumn()) {
                    throw new InvalidArgumentException('Seçilen depo veya raf bulunamadı veya pasif durumda.');
                }
            }

            $locationIds = [$sourceLocationId, $targetLocationId];
            sort($locationIds, SORT_NUMERIC);
            $balances = [];

            foreach ($locationIds as $locationId) {
                $balanceStmt = $this->pdo->prepare(
                    'SELECT quantity
                     FROM stock_balances
                     WHERE material_id = :material_id AND location_id = :location_id
                     FOR UPDATE'
                );
                $balanceStmt->execute([
                    'material_id' => $materialId,
                    'location_id' => $locationId,
                ]);
                $balances[$locationId] = $balanceStmt->fetchColumn();
            }

            $sourceStock = $balances[$sourceLocationId];
            if ($sourceStock === false || (float) $sourceStock < $quantity) {
                $availableStock = $sourceStock === false ? 0 : (float) $sourceStock;
                throw new InvalidArgumentException(
                    'Yetersiz stok. Mevcut stok: ' . number_format($availableStock, 3, ',', '.')
                );
            }

            $sourceUpdateStmt = $this->pdo->prepare(
                'UPDATE stock_balances
                 SET quantity = quantity - :quantity
                 WHERE material_id = :material_id AND location_id = :location_id'
            );
            $sourceUpdateStmt->execute([
                'quantity' => $quantity,
                'material_id' => $materialId,
                'location_id' => $sourceLocationId,
            ]);

            if ($balances[$targetLocationId] === false) {
                $targetInsertStmt = $this->pdo->prepare(
                    'INSERT INTO stock_balances (material_id, location_id, quantity)
                     VALUES (:material_id, :location_id, :quantity)'
                );
                $targetInsertStmt->execute([
                    'material_id' => $materialId,
                    'location_id' => $targetLocationId,
                    'quantity' => $quantity,
                ]);
            } else {
                $targetUpdateStmt = $this->pdo->prepare(
                    'UPDATE stock_balances
                     SET quantity = quantity + :quantity
                     WHERE material_id = :material_id AND location_id = :location_id'
                );
                $targetUpdateStmt->execute([
                    'quantity' => $quantity,
                    'material_id' => $materialId,
                    'location_id' => $targetLocationId,
                ]);
            }

            $movementStmt = $this->pdo->prepare(
                'INSERT INTO stock_movements
                    (material_id, location_id, user_id, movement_type, quantity, description)
                 VALUES
                    (:material_id, :location_id, :user_id, :movement_type, :quantity, :description)'
            );

            $movementData = [
                'material_id' => $materialId,
                'user_id' => $userId,
                'quantity' => $quantity,
                'description' => $description,
            ];

            $movementData['location_id'] = $sourceLocationId;
            $movementData['movement_type'] = 'TRANSFER_OUT';
            $movementStmt->execute($movementData);

            $movementData['location_id'] = $targetLocationId;
            $movementData['movement_type'] = 'TRANSFER_IN';
            $movementStmt->execute($movementData);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function recordReturn(
        int $materialId,
        int $sourceLocationId,
        int $targetLocationId,
        float $quantity,
        int $userId,
        ?string $description
    ): void {
        if ($sourceLocationId === $targetLocationId) {
            throw new InvalidArgumentException('Kaynak ve hedef depo/raf aynı olamaz.');
        }

        $this->pdo->beginTransaction();

        try {
            $materialStmt = $this->pdo->prepare(
                'SELECT id FROM materials WHERE id = :id AND is_active = 1 LIMIT 1'
            );
            $materialStmt->execute(['id' => $materialId]);

            if (!$materialStmt->fetchColumn()) {
                throw new InvalidArgumentException('Seçilen malzeme bulunamadı veya pasif durumda.');
            }

            $locationStmt = $this->pdo->prepare(
                'SELECT l.id
                 FROM locations l
                 INNER JOIN warehouses w ON w.id = l.warehouse_id
                 WHERE l.id = :id AND l.is_active = 1 AND w.is_active = 1
                 LIMIT 1'
            );

            foreach ([$sourceLocationId, $targetLocationId] as $locationId) {
                $locationStmt->execute(['id' => $locationId]);

                if (!$locationStmt->fetchColumn()) {
                    throw new InvalidArgumentException('Seçilen depo veya raf bulunamadı veya pasif durumda.');
                }
            }

            $locationIds = [$sourceLocationId, $targetLocationId];
            sort($locationIds, SORT_NUMERIC);
            $balances = [];

            foreach ($locationIds as $locationId) {
                $balanceStmt = $this->pdo->prepare(
                    'SELECT quantity
                     FROM stock_balances
                     WHERE material_id = :material_id AND location_id = :location_id
                     FOR UPDATE'
                );
                $balanceStmt->execute([
                    'material_id' => $materialId,
                    'location_id' => $locationId,
                ]);
                $balances[$locationId] = $balanceStmt->fetchColumn();
            }

            $sourceStock = $balances[$sourceLocationId];
            if ($sourceStock === false || (float) $sourceStock < $quantity) {
                $availableStock = $sourceStock === false ? 0 : (float) $sourceStock;
                throw new InvalidArgumentException(
                    'Yetersiz stok. Mevcut stok: ' . number_format($availableStock, 3, ',', '.')
                );
            }

            $sourceUpdateStmt = $this->pdo->prepare(
                'UPDATE stock_balances
                 SET quantity = quantity - :quantity
                 WHERE material_id = :material_id AND location_id = :location_id'
            );
            $sourceUpdateStmt->execute([
                'quantity' => $quantity,
                'material_id' => $materialId,
                'location_id' => $sourceLocationId,
            ]);

            if ($balances[$targetLocationId] === false) {
                $targetInsertStmt = $this->pdo->prepare(
                    'INSERT INTO stock_balances (material_id, location_id, quantity)
                     VALUES (:material_id, :location_id, :quantity)'
                );
                $targetInsertStmt->execute([
                    'material_id' => $materialId,
                    'location_id' => $targetLocationId,
                    'quantity' => $quantity,
                ]);
            } else {
                $targetUpdateStmt = $this->pdo->prepare(
                    'UPDATE stock_balances
                     SET quantity = quantity + :quantity
                     WHERE material_id = :material_id AND location_id = :location_id'
                );
                $targetUpdateStmt->execute([
                    'quantity' => $quantity,
                    'material_id' => $materialId,
                    'location_id' => $targetLocationId,
                ]);
            }

            $movementStmt = $this->pdo->prepare(
                'INSERT INTO stock_movements
                    (material_id, location_id, user_id, movement_type, quantity, description)
                 VALUES
                    (:material_id, :location_id, :user_id, :movement_type, :quantity, :description)'
            );

            $movementData = [
                'material_id' => $materialId,
                'user_id' => $userId,
                'quantity' => $quantity,
                'description' => $description,
            ];

            $movementData['location_id'] = $sourceLocationId;
            $movementData['movement_type'] = 'RETURN';
            $movementStmt->execute($movementData);

            $movementData['location_id'] = $targetLocationId;
            $movementStmt->execute($movementData);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function recordAdjustment(
        int $materialId,
        int $locationId,
        float $newQuantity,
        int $userId,
        ?string $description
    ): void {
        if ($newQuantity < 0 || !is_finite($newQuantity)) {
            throw new InvalidArgumentException('Yeni stok miktarı sıfır veya daha büyük bir sayı olmalıdır.');
        }

        $this->pdo->beginTransaction();

        try {
            $materialStmt = $this->pdo->prepare(
                'SELECT id FROM materials WHERE id = :id AND is_active = 1 LIMIT 1'
            );
            $materialStmt->execute(['id' => $materialId]);

            if (!$materialStmt->fetchColumn()) {
                throw new InvalidArgumentException('Seçilen malzeme bulunamadı veya pasif durumda.');
            }

            $locationStmt = $this->pdo->prepare(
                'SELECT l.id
                 FROM locations l
                 INNER JOIN warehouses w ON w.id = l.warehouse_id
                 WHERE l.id = :id AND l.is_active = 1 AND w.is_active = 1
                 LIMIT 1'
            );
            $locationStmt->execute(['id' => $locationId]);

            if (!$locationStmt->fetchColumn()) {
                throw new InvalidArgumentException('Seçilen depo veya raf bulunamadı veya pasif durumda.');
            }

            $balanceStmt = $this->pdo->prepare(
                'SELECT quantity
                 FROM stock_balances
                 WHERE material_id = :material_id AND location_id = :location_id
                 FOR UPDATE'
            );
            $balanceStmt->execute([
                'material_id' => $materialId,
                'location_id' => $locationId,
            ]);

            $currentValue = $balanceStmt->fetchColumn();
            $oldQuantity = $currentValue === false ? 0.0 : (float) $currentValue;
            $difference = $newQuantity - $oldQuantity;

            if ($currentValue === false) {
                $balanceUpdateStmt = $this->pdo->prepare(
                    'INSERT INTO stock_balances (material_id, location_id, quantity)
                     VALUES (:material_id, :location_id, :quantity)'
                );
                $balanceUpdateStmt->execute([
                    'material_id' => $materialId,
                    'location_id' => $locationId,
                    'quantity' => $newQuantity,
                ]);
            } else {
                $balanceUpdateStmt = $this->pdo->prepare(
                    'UPDATE stock_balances
                     SET quantity = :quantity
                     WHERE material_id = :material_id AND location_id = :location_id'
                );
                $balanceUpdateStmt->execute([
                    'quantity' => $newQuantity,
                    'material_id' => $materialId,
                    'location_id' => $locationId,
                ]);
            }

            $adjustmentDescription = sprintf(
                'Eski stok: %s | Yeni stok: %s | Fark: %s%s',
                number_format($oldQuantity, 3, ',', '.'),
                number_format($newQuantity, 3, ',', '.'),
                $difference >= 0 ? '+' : '',
                number_format($difference, 3, ',', '.')
            );

            if ($description !== null && $description !== '') {
                $adjustmentDescription .= ' | ' . $description;
            }

            $movementStmt = $this->pdo->prepare(
                'INSERT INTO stock_movements
                    (material_id, location_id, user_id, movement_type, quantity, description)
                 VALUES
                    (:material_id, :location_id, :user_id, :movement_type, :quantity, :description)'
            );
            $movementStmt->execute([
                'material_id' => $materialId,
                'location_id' => $locationId,
                'user_id' => $userId,
                'movement_type' => 'ADJUSTMENT',
                'quantity' => abs($difference),
                'description' => $adjustmentDescription,
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
