<?php

class ProductionStockService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Reçete, üretim miktarı ve lokasyonlara göre hammadde tüketim ve mamul giriş durumunu hesaplar.
     * 
     * Formül:
     * Tüketim = (Üretim Miktarı / Baz Miktar) * Reçete Birim Miktarı * (1 + Fire Oranı / 100)
     */
    public function calculateStockNeeds(int $recipeId, float $producedQty, int $sourceLocationId, ?int $targetLocationId = null): array
    {
        if ($producedQty <= 0) {
            throw new InvalidArgumentException('Üretim miktarı 0\'dan büyük bir değer olmalıdır.');
        }

        // 1. Reçete Başlığı ve Nihai Mamul Bilgilerini Doğrula
        $recipeStmt = $this->pdo->prepare("
            SELECT 
                r.*, 
                m.id AS output_mat_id,
                m.code AS output_code, 
                m.name AS output_name, 
                u.symbol AS output_unit
            FROM recipes r
            LEFT JOIN materials m ON m.id = r.output_material_id
            LEFT JOIN units u ON u.id = m.unit_id
            WHERE r.id = :id AND r.is_active = 1
            LIMIT 1
        ");
        $recipeStmt->execute([':id' => $recipeId]);
        $recipe = $recipeStmt->fetch(PDO::FETCH_ASSOC);

        if (!$recipe) {
            throw new InvalidArgumentException('Seçilen reçete bulunamadı veya pasif durumda (ID: ' . $recipeId . ').');
        }

        $baseQty = (float)($recipe['base_quantity'] > 0 ? $recipe['base_quantity'] : 1.0);

        // 2. Kaynak Lokasyonu Doğrula (Hammadde çıkışı yapılacak yer)
        $srcLocStmt = $this->pdo->prepare("
            SELECT l.id, l.name as loc_name, l.code as loc_code, w.id as wh_id, w.name as wh_name, w.code as wh_code
            FROM locations l
            INNER JOIN warehouses w ON w.id = l.warehouse_id
            WHERE l.id = :id AND l.is_active = 1 AND w.is_active = 1
            LIMIT 1
        ");
        $srcLocStmt->execute([':id' => $sourceLocationId]);
        $sourceLocation = $srcLocStmt->fetch(PDO::FETCH_ASSOC);

        if (!$sourceLocation) {
            throw new InvalidArgumentException('Seçilen hammadde kaynak deposu/lokasyonu bulunamadı veya pasif.');
        }

        // 3. Hedef Lokasyonu Doğrula (Mamul girişi yapılacak yer)
        $targetLocation = null;
        $currentTargetStock = 0.0;
        if ($targetLocationId !== null && $targetLocationId > 0) {
            $tgtLocStmt = $this->pdo->prepare("
                SELECT l.id, l.name as loc_name, l.code as loc_code, w.id as wh_id, w.name as wh_name, w.code as wh_code
                FROM locations l
                INNER JOIN warehouses w ON w.id = l.warehouse_id
                WHERE l.id = :id AND l.is_active = 1 AND w.is_active = 1
                LIMIT 1
            ");
            $tgtLocStmt->execute([':id' => $targetLocationId]);
            $targetLocation = $tgtLocStmt->fetch(PDO::FETCH_ASSOC);

            if ($targetLocation && !empty($recipe['output_mat_id'])) {
                $tgtBalStmt = $this->pdo->prepare("
                    SELECT COALESCE(quantity, 0) 
                    FROM stock_balances 
                    WHERE material_id = :material_id AND location_id = :location_id
                    LIMIT 1
                ");
                $tgtBalStmt->execute([
                    ':material_id' => (int)$recipe['output_mat_id'],
                    ':location_id' => $targetLocationId
                ]);
                $currentTargetStock = (float)($tgtBalStmt->fetchColumn() ?: 0);
            }
        }

        // 4. Reçete Kalemlerini Getir
        $itemsStmt = $this->pdo->prepare("
            SELECT 
                ri.id as item_id,
                ri.material_id,
                m.code as material_code,
                m.name as material_name,
                c.name as category_name,
                u.symbol as unit_symbol,
                ri.quantity as unit_quantity,
                ri.scrap_rate_pct,
                ri.is_critical,
                COALESCE(m.unit_price, 0.0000) as unit_price,
                COALESCE(m.currency, 'TL') as currency
            FROM recipe_items ri
            INNER JOIN materials m ON m.id = ri.material_id
            LEFT JOIN categories c ON c.id = m.category_id
            LEFT JOIN units u ON u.id = m.unit_id
            WHERE ri.recipe_id = :recipe_id AND m.is_active = 1
            ORDER BY ri.is_critical DESC, m.name ASC
        ");
        $itemsStmt->execute([':recipe_id' => $recipeId]);
        $rawItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rawItems)) {
            throw new InvalidArgumentException('Bu reçeteye tanımlı hammadde kalemi bulunamadı.');
        }

        $calculatedItems = [];
        $allCriticalSufficient = true;
        $totalRequiredItemsCount = count($rawItems);
        $sufficientItemsCount = 0;

        foreach ($rawItems as $item) {
            $unitQty = (float)$item['unit_quantity'];
            $scrapRate = (float)$item['scrap_rate_pct'];
            $isCritical = (int)$item['is_critical'] === 1;
            $unitPrice = (float)$item['unit_price'];
            $currency = $item['currency'] ?: 'TL';

            // Formül: Üretim Miktarı * Reçete Miktarı * (1 + Fire% / 100)
            $requiredQty = ($producedQty / $baseQty) * $unitQty * (1 + ($scrapRate / 100));
            $requiredQty = round($requiredQty, 4);
            $totalCost = round($requiredQty * $unitPrice, 4);

            // Kaynak Lokasyondaki Stok Bakiyesi
            $balStmt = $this->pdo->prepare("
                SELECT COALESCE(quantity, 0) 
                FROM stock_balances 
                WHERE material_id = :material_id AND location_id = :location_id
                LIMIT 1
            ");
            $balStmt->execute([
                ':material_id' => (int)$item['material_id'],
                ':location_id' => $sourceLocationId
            ]);
            $availableQty = (float)($balStmt->fetchColumn() ?: 0);

            $missingQty = max(0, $requiredQty - $availableQty);
            $isSufficient = ($availableQty >= $requiredQty);

            if ($isSufficient) {
                $sufficientItemsCount++;
            } else {
                if ($isCritical) {
                    $allCriticalSufficient = false;
                }
            }

            $calculatedItems[] = [
                'material_id'     => (int)$item['material_id'],
                'material_code'   => $item['material_code'],
                'material_name'   => $item['material_name'],
                'category_name'   => $item['category_name'],
                'unit_symbol'     => $item['unit_symbol'] ?: 'AD',
                'unit_quantity'   => $unitQty,
                'scrap_rate_pct'  => $scrapRate,
                'is_critical'     => $isCritical,
                'required_qty'    => $requiredQty,
                'available_qty'   => $availableQty,
                'missing_qty'     => $missingQty,
                'is_sufficient'   => $isSufficient,
                'unit_price'      => $unitPrice,
                'currency'        => $currency,
                'total_cost'      => $totalCost
            ];
        }

        return [
            'recipe'                   => $recipe,
            'source_location'          => $sourceLocation,
            'target_location'          => $targetLocation,
            'output_material'          => [
                'id'                   => $recipe['output_mat_id'],
                'code'                 => $recipe['output_code'],
                'name'                 => $recipe['output_name'],
                'unit_symbol'          => $recipe['output_unit'] ?: 'AD',
                'produced_qty'         => $producedQty,
                'current_target_stock' => $currentTargetStock,
            ],
            'produced_quantity'        => $producedQty,
            'can_produce'              => $allCriticalSufficient,
            'items'                    => $calculatedItems,
            'total_items_count'        => $totalRequiredItemsCount,
            'sufficient_items_count'   => $sufficientItemsCount,
        ];
    }

    /**
     * Üretim kaydı, hammadde stok çıkışı (OUT) ve mamul stok girişini (IN)
     * TEK bir atomik transaction içinde gerçekleştirir.
     */
    public function executeProduction(array $data, int $userId): array
    {
        $lineId = (int)($data['line_id'] ?? 0);
        $shiftId = (int)($data['shift_id'] ?? 0);
        $recipeId = (int)($data['recipe_id'] ?? 0);
        $sourceLocationId = (int)($data['source_location_id'] ?? $data['location_id'] ?? 0);
        $targetLocationId = (int)($data['target_location_id'] ?? 0);
        $producedQty = (int)($data['panels_produced_qty'] ?? 0);
        $scrapPanelsQty = (int)($data['scrap_panels_qty'] ?? 0);
        $logDate = trim($data['log_date'] ?? date('Y-m-d'));
        $notes = !empty($data['notes']) ? trim($data['notes']) : null;
        $totalWpProduced = (float)($data['total_wp_produced'] ?? ($producedQty * 550.0));

        // 1. Temel Girdi Doğrulamaları
        if ($lineId <= 0) {
            throw new InvalidArgumentException('Geçerli bir üretim hattı seçilmelidir.');
        }
        if ($shiftId <= 0) {
            throw new InvalidArgumentException('Geçerli bir vardiya seçilmelidir.');
        }
        if ($producedQty <= 0) {
            throw new InvalidArgumentException('Üretilen sağlam panel adedi 0\'dan büyük olmalıdır.');
        }
        if ($scrapPanelsQty < 0) {
            throw new InvalidArgumentException('Fire panel adedi negatif olamaz.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $logDate)) {
            throw new InvalidArgumentException('Geçersiz üretim tarihi formatı (YYYY-AA-GG).');
        }

        // 2. Hat ve Vardiya Kontrolü
        $lineCheck = $this->pdo->prepare("SELECT id, code, name FROM production_lines WHERE id = :id AND is_active = 1 LIMIT 1");
        $lineCheck->execute([':id' => $lineId]);
        $lineRow = $lineCheck->fetch(PDO::FETCH_ASSOC);
        if (!$lineRow) {
            throw new InvalidArgumentException('Seçilen üretim hattı bulunamadı veya pasif.');
        }

        $shiftCheck = $this->pdo->prepare("SELECT id, code, name FROM energy_shifts WHERE id = :id AND is_active = 1 LIMIT 1");
        $shiftCheck->execute([':id' => $shiftId]);
        $shiftRow = $shiftCheck->fetch(PDO::FETCH_ASSOC);
        if (!$shiftRow) {
            throw new InvalidArgumentException('Seçilen vardiya bulunamadı veya pasif.');
        }

        // 3. Hedef Lokasyon Fallback (Eğer belirtilmemişse Sevkiyat Deposu S-01 (id: 12) veya kaynak lokasyon)
        if ($targetLocationId <= 0) {
            $defaultTarget = $this->pdo->query("
                SELECT l.id FROM locations l 
                INNER JOIN warehouses w ON w.id = l.warehouse_id 
                WHERE w.code = 'DEP-005' AND l.code = 'S-01' LIMIT 1
            ")->fetchColumn();
            $targetLocationId = $defaultTarget ? (int)$defaultTarget : $sourceLocationId;
        }

        // 4. Stok İhtiyaçlarını Hesapla ve Doğrula
        $analysis = $this->calculateStockNeeds($recipeId, (float)$producedQty, $sourceLocationId, $targetLocationId);

        if (!$analysis['can_produce']) {
            $missingSummary = [];
            foreach ($analysis['items'] as $it) {
                if (!$it['is_sufficient'] && $it['is_critical']) {
                    $missingSummary[] = "{$it['material_name']} (Gerekli: {$it['required_qty']}, Mevcut: {$it['available_qty']})";
                }
            }
            throw new InvalidArgumentException(
                'Yetersiz hammadde stoğu nedeniyle üretim onaylanamaz. Eksik malzemeler: ' . implode(', ', $missingSummary)
            );
        }

        // Mamul Kartı Kontrolü
        $outputMaterial = $analysis['output_material'];
        if (empty($outputMaterial['id'])) {
            throw new InvalidArgumentException('Seçilen reçeteye bağlı bir nihai mamul kartı tanımlanmamış.');
        }

        // 5. Benzersiz Üretim Referans Numarası Üret
        $dateCompact = str_replace('-', '', $logDate);
        $lineCodeClean = preg_replace('/[^A-Za-z0-9]/', '', $lineRow['code']);
        $uniqueSuffix = strtoupper(substr(uniqid(), -4));
        $referenceNo = sprintf('PRD-%s-%s-SH%d-%s', $dateCompact, $lineCodeClean, $shiftId, $uniqueSuffix);

        // Çift onay / Idempotency koruması
        $dupCheck = $this->pdo->prepare("SELECT id FROM stock_movements WHERE reference_no = :ref LIMIT 1");
        $dupCheck->execute([':ref' => $referenceNo]);
        if ($dupCheck->fetchColumn()) {
            throw new InvalidArgumentException('Bu üretim referansı zaten sistemde kayıtlı.');
        }

        // =====================================================================
        // 6. TEK BİR ATOMİK TRANSACTION BAŞLAT
        // =====================================================================
        $this->pdo->beginTransaction();

        try {
            $consumedMovements = [];
            $cellsUsedQty = 0.0;
            $totalProductionCost = 0.0;

            $workOrderId = (int)($data['work_order_id'] ?? 0);
            $workOrderNo = trim($data['work_order_no'] ?? '');
            $eventId = !empty($data['event_id']) ? trim($data['event_id']) : null;

            // Strict Concurrency & Target Guard: Lock work order row
            if ($workOrderId > 0) {
                $stmtLockWo = $this->pdo->prepare("
                    SELECT id, work_order_no, planned_quantity, produced_quantity, status 
                    FROM mes_work_orders 
                    WHERE id = ? 
                    FOR UPDATE
                ");
                $stmtLockWo->execute([$workOrderId]);
                $woRow = $stmtLockWo->fetch(PDO::FETCH_ASSOC);

                if ($woRow) {
                    if (empty($workOrderNo)) {
                        $workOrderNo = (string)$woRow['work_order_no'];
                    }
                    $curProduced = (float)$woRow['produced_quantity'];
                    $planned = (float)$woRow['planned_quantity'];

                    if ($curProduced >= $planned || $woRow['status'] === 'COMPLETED') {
                        $this->pdo->rollBack();
                        throw new InvalidArgumentException("İş emri ({$workOrderNo}) zaten hedeflenen miktara ulaştı veya tamamlandı.");
                    }

                    $newProduced = $curProduced + (float)$producedQty;
                    $newStatus = ($newProduced >= $planned) ? 'COMPLETED' : ($woRow['status'] === 'PLANNED' ? 'RUNNING' : $woRow['status']);

                    $upWoStmt = $this->pdo->prepare("
                        UPDATE mes_work_orders 
                        SET produced_quantity = ?, status = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $upWoStmt->execute([$newProduced, $newStatus, $workOrderId]);
                }
            }

            // A) HAMMADDE ÇIKIŞLARI (OUT)
            foreach ($analysis['items'] as $item) {
                $matId = $item['material_id'];
                $requiredQty = $item['required_qty'];
                $unitPrice = (float)($item['unit_price'] ?? 0.0);
                $currency = $item['currency'] ?: 'TL';
                $totalPrice = round($requiredQty * $unitPrice, 4);
                $totalProductionCost += $totalPrice;

                if (stripos($item['material_code'], 'CELL') !== false || stripos($item['material_name'], 'Hücre') !== false) {
                    $cellsUsedQty += $requiredQty;
                }

                // Row-level lock (FOR UPDATE)
                $lockStmt = $this->pdo->prepare("
                    SELECT quantity 
                    FROM stock_balances 
                    WHERE material_id = :material_id AND location_id = :location_id 
                    FOR UPDATE
                ");
                $lockStmt->execute([
                    ':material_id' => $matId,
                    ':location_id' => $sourceLocationId
                ]);
                $currentQty = $lockStmt->fetchColumn();

                if ($currentQty === false || (float)$currentQty < $requiredQty) {
                    $available = $currentQty === false ? 0.0 : (float)$currentQty;
                    throw new InvalidArgumentException(
                        "İşlem anında yetersiz hammadde stoğu: {$item['material_name']} ({$item['material_code']}). " .
                        "Gereken: " . number_format($requiredQty, 2) . " {$item['unit_symbol']}, " .
                        "Mevcut: " . number_format($available, 2) . " {$item['unit_symbol']}."
                    );
                }

                // Bakiye Azaltma (UPDATE stock_balances)
                $updateBal = $this->pdo->prepare("
                    UPDATE stock_balances 
                    SET quantity = quantity - :qty 
                    WHERE material_id = :material_id AND location_id = :location_id
                ");
                $updateBal->execute([
                    ':qty'         => $requiredQty,
                    ':material_id' => $matId,
                    ':location_id' => $sourceLocationId
                ]);

                // Stok Hareketi OUT (INSERT stock_movements with cost snapshot)
                $descOut = $workOrderNo
                    ? sprintf('Üretim tüketimi - İş Emri: %s - Event: %s - %s - %d %s', $workOrderNo, $eventId ?: $referenceNo, $analysis['recipe']['code'], $producedQty, $outputMaterial['unit_symbol'])
                    : sprintf('Üretim tüketimi - %s - %d %s', $analysis['recipe']['code'], $producedQty, $outputMaterial['unit_symbol']);

                $insertMovOut = $this->pdo->prepare("
                    INSERT INTO stock_movements 
                        (material_id, location_id, user_id, movement_type, quantity, unit_price, total_price, currency, reference_no, description, created_at)
                    VALUES 
                        (:material_id, :location_id, :user_id, 'OUT', :quantity, :unit_price, :total_price, :currency, :reference_no, :description, NOW())
                ");
                $insertMovOut->execute([
                    ':material_id'  => $matId,
                    ':location_id'  => $sourceLocationId,
                    ':user_id'      => $userId,
                    ':quantity'     => $requiredQty,
                    ':unit_price'   => $unitPrice,
                    ':total_price'  => $totalPrice,
                    ':currency'     => $currency,
                    ':reference_no' => $referenceNo,
                    ':description'  => $descOut,
                ]);

                $consumedMovements[] = [
                    'material_id'   => $matId,
                    'material_code' => $item['material_code'],
                    'material_name' => $item['material_name'],
                    'unit_symbol'   => $item['unit_symbol'],
                    'quantity'      => $requiredQty,
                    'unit_price'    => $unitPrice,
                    'total_price'   => $totalPrice,
                    'currency'      => $currency,
                ];
            }

            // B) MAMUL STOK GİRİŞİ (IN)
            $outMatId = (int)$outputMaterial['id'];

            // Mamul bakiyesini kilitle (FOR UPDATE)
            $lockTgtStmt = $this->pdo->prepare("
                SELECT quantity 
                FROM stock_balances 
                WHERE material_id = :material_id AND location_id = :location_id 
                FOR UPDATE
            ");
            $lockTgtStmt->execute([
                ':material_id' => $outMatId,
                ':location_id' => $targetLocationId
            ]);
            $currentTgtQty = $lockTgtStmt->fetchColumn();

            if ($currentTgtQty === false) {
                // Bakiye kaydı yoksa yeni ekle
                $insertTgtBal = $this->pdo->prepare("
                    INSERT INTO stock_balances (material_id, location_id, quantity, updated_at)
                    VALUES (:material_id, :location_id, :quantity, NOW())
                ");
                $insertTgtBal->execute([
                    ':material_id' => $outMatId,
                    ':location_id' => $targetLocationId,
                    ':quantity'    => $producedQty,
                ]);
            } else {
                // Mevcut bakiyeyi artır
                $updateTgtBal = $this->pdo->prepare("
                    UPDATE stock_balances 
                    SET quantity = quantity + :quantity, updated_at = NOW()
                    WHERE material_id = :material_id AND location_id = :location_id
                ");
                $updateTgtBal->execute([
                    ':quantity'    => $producedQty,
                    ':material_id' => $outMatId,
                    ':location_id' => $targetLocationId,
                ]);
            }

            // Stok Hareketi IN (INSERT stock_movements with cost snapshot)
            $unitCost = $producedQty > 0 ? round($totalProductionCost / $producedQty, 4) : 0.0;
            $descIn = $workOrderNo
                ? sprintf('Üretim girişi - İş Emri: %s - Event: %s - %s - %d %s (Maliyet: %.2f TL)', $workOrderNo, $eventId ?: $referenceNo, $outputMaterial['code'], $producedQty, $outputMaterial['unit_symbol'], $totalProductionCost)
                : sprintf('Üretim girişi - %s - %d %s (Maliyet: %.2f TL)', $outputMaterial['code'], $producedQty, $outputMaterial['unit_symbol'], $totalProductionCost);

            $insertMovIn = $this->pdo->prepare("
                INSERT INTO stock_movements 
                    (material_id, location_id, user_id, movement_type, quantity, unit_price, total_price, currency, reference_no, description, created_at)
                VALUES 
                    (:material_id, :location_id, :user_id, 'IN', :quantity, :unit_price, :total_price, 'TL', :reference_no, :description, NOW())
            ");
            $insertMovIn->execute([
                ':material_id'  => $outMatId,
                ':location_id'  => $targetLocationId,
                ':user_id'      => $userId,
                ':quantity'     => $producedQty,
                ':unit_price'   => $unitCost,
                ':total_price'  => $totalProductionCost,
                ':reference_no' => $referenceNo,
                ':description'  => $descIn,
            ]);
            $stockMovementInId = (int)$this->pdo->lastInsertId();

            // C) ÜRETİM TELEMETRİ GÜNLÜĞÜ (energy_production_logs)
            $logNote = sprintf(
                'Reçete: %s (%s). Üretilen: %d %s. Maliyet: %.2f TL. %s',
                $analysis['recipe']['name'],
                $analysis['recipe']['code'],
                $producedQty,
                $outputMaterial['unit_symbol'],
                $totalProductionCost,
                $notes ?: 'BOM entegreli tam üretim ve mamul girişi.'
            );

            $insertLog = $this->pdo->prepare("
                INSERT INTO energy_production_logs
                    (line_id, shift_id, log_date, panels_produced_qty, total_wp_produced, scrap_panels_qty, cells_used_qty, stock_movement_ref, notes, created_at, updated_at)
                VALUES
                    (:line_id, :shift_id, :log_date, :panels_qty, :total_wp, :scrap_qty, :cells_used, :stock_ref, :notes, NOW(), NOW())
            ");
            $insertLog->execute([
                ':line_id'      => $lineId,
                ':shift_id'     => $shiftId,
                ':log_date'     => $logDate,
                ':panels_qty'   => $producedQty,
                ':total_wp'     => $totalWpProduced,
                ':scrap_qty'    => $scrapPanelsQty,
                ':cells_used'   => $cellsUsedQty,
                ':stock_ref'    => $referenceNo,
                ':notes'        => $logNote,
            ]);
            $productionLogId = (int)$this->pdo->lastInsertId();

            // D) PANEL SERİ NUMARASI KAYITLARI (panel_units)
            $workOrderId = (int)($data['work_order_id'] ?? 0);
            $eventId = $data['event_id'] ?? null;
            $createdSerials = [];

            if ($workOrderId <= 0) {
                $fallbackWo = (int)$this->pdo->query("SELECT id FROM mes_work_orders WHERE status = 'RUNNING' ORDER BY id DESC LIMIT 1")->fetchColumn();
                $workOrderId = $fallbackWo ?: 1;
            }

            // Target Warehouse ID
            $whStmt = $this->pdo->prepare("SELECT warehouse_id FROM locations WHERE id = ? LIMIT 1");
            $whStmt->execute([$targetLocationId]);
            $targetWarehouseId = (int)($whStmt->fetchColumn() ?: 5);

            $prefix = 'SP550W-' . date('Ymd');
            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM panel_units WHERE serial_no LIKE :prefix");
            $countStmt->execute([':prefix' => $prefix . '-%']);
            $seq = (int)$countStmt->fetchColumn() + 1;

            $checkSerialStmt = $this->pdo->prepare("SELECT id FROM panel_units WHERE serial_no = :serial LIMIT 1");
            $insertUnitStmt = $this->pdo->prepare("
                INSERT INTO panel_units 
                    (serial_no, material_id, work_order_id, production_event_id, production_line_id, warehouse_id, location_id, stock_movement_id, stock_movement_ref, status, unit_cost, currency, produced_at, created_at, updated_at)
                VALUES
                    (:serial_no, :material_id, :work_order_id, :production_event_id, :production_line_id, :warehouse_id, :location_id, :stock_movement_id, :stock_movement_ref, 'IN_STOCK', :unit_cost, 'TL', NOW(), NOW(), NOW())
            ");

            $explicitSerial = !empty($data['serial_no']) ? trim($data['serial_no']) : (!empty($data['panel_serial_no']) ? trim($data['panel_serial_no']) : null);

            for ($p = 0; $p < (int)$producedQty; $p++) {
                if ($explicitSerial && (int)$producedQty === 1) {
                    $serialNo = $explicitSerial;
                } else {
                    $serialNo = sprintf('%s-%06d', $prefix, $seq);
                    while (true) {
                        $checkSerialStmt->execute([':serial' => $serialNo]);
                        if (!$checkSerialStmt->fetchColumn()) {
                            break;
                        }
                        $seq++;
                        $serialNo = sprintf('%s-%06d', $prefix, $seq);
                    }
                    $seq++;
                }

                $insertUnitStmt->execute([
                    ':serial_no'            => $serialNo,
                    ':material_id'          => $outMatId,
                    ':work_order_id'        => $workOrderId,
                    ':production_event_id'  => $eventId,
                    ':production_line_id'   => $lineId,
                    ':warehouse_id'         => $targetWarehouseId,
                    ':location_id'          => $targetLocationId,
                    ':stock_movement_id'    => $stockMovementInId,
                    ':stock_movement_ref'   => $referenceNo,
                    ':unit_cost'            => $unitCost,
                ]);

                $createdSerials[] = $serialNo;
                $seq++;
            }

            // E) MES PRODUCTION EVENT STOCK REFERENCE GÜNCELLEMESİ
            if (!empty($eventId)) {
                $stmtUpEvt = $this->pdo->prepare("
                    UPDATE mes_production_events 
                    SET stock_movement_ref = ?, unit_cost = ?, total_cost = ?, cost_currency = 'TL' 
                    WHERE event_id = ?
                ");
                $stmtUpEvt->execute([$referenceNo, $unitCost, $totalProductionCost, $eventId]);
            }

            // F) COMMIT TRANSACTION
            $this->pdo->commit();

            return [
                'success'              => true,
                'production_log_id'    => $productionLogId,
                'reference_no'         => $referenceNo,
                'produced_quantity'    => $producedQty,
                'unit_cost'            => $unitCost,
                'total_cost'           => round($totalProductionCost, 4),
                'currency'             => 'TL',
                'created_serials'      => $createdSerials,
                'total_wp_produced'    => $totalWpProduced,
                'recipe_name'          => $analysis['recipe']['name'],
                'recipe_code'          => $analysis['recipe']['code'],
                'output_material_name' => $outputMaterial['name'],
                'output_material_code' => $outputMaterial['code'],
                'output_unit_symbol'   => $outputMaterial['unit_symbol'],
                'line_name'            => $lineRow['name'],
                'shift_name'           => $shiftRow['name'],
                'source_location_name' => $analysis['source_location']['loc_name'],
                'source_warehouse_name'=> $analysis['source_location']['wh_name'],
                'target_location_name' => $analysis['target_location']['loc_name'] ?? 'Sevkiyat Alanı',
                'target_warehouse_name'=> $analysis['target_location']['wh_name'] ?? 'Sevkiyat Deposu',
                'consumed_items'       => $consumedMovements,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
