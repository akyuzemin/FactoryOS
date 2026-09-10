<?php
declare(strict_types=1);

class PurchaseReceipt
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Benzersiz bir Makbuz / Mal Kabul Numarası üretir (Örn: REC-2026-000001).
     */
    public function generateReceiptNo(): string
    {
        $year = date('Y');
        $prefix = "REC-{$year}-";

        $stmt = $this->pdo->prepare("
            SELECT receipt_no 
            FROM purchase_receipts 
            WHERE receipt_no LIKE :prefix 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute([':prefix' => $prefix . '%']);
        $lastNo = $stmt->fetchColumn();

        if ($lastNo) {
            $seq = (int)substr((string)$lastNo, strlen($prefix));
            $nextSeq = $seq + 1;
        } else {
            $nextSeq = 1;
        }

        return sprintf('%s%06d', $prefix, $nextSeq);
    }

    /**
     * Mal kabul işlemini ve stok hareketini tek bir atomik transaction içinde gerçekleştirir.
     *
     * @param array $receiptData [purchase_order_id, warehouse_id, location_id, receipt_date, delivery_note_no, supplier_document_no, notes]
     * @param array $itemData    [purchase_order_item_id, received_quantity, accepted_quantity, rejected_quantity, notes]
     * @param int   $userId      İşlemi yapan kullanıcı ID'si
     * @return int Oluşturulan receipt ID
     */
    public function createReceipt(array $receiptData, array $itemData, int $userId): int
    {
        $poId = (int)($receiptData['purchase_order_id'] ?? 0);
        $warehouseId = (int)($receiptData['warehouse_id'] ?? 0);
        $locationId = (int)($receiptData['location_id'] ?? 0);
        $receiptDate = trim((string)($receiptData['receipt_date'] ?? date('Y-m-d')));
        $deliveryNoteNo = !empty($receiptData['delivery_note_no']) ? trim((string)$receiptData['delivery_note_no']) : null;
        $supplierDocNo = !empty($receiptData['supplier_document_no']) ? trim((string)$receiptData['supplier_document_no']) : null;
        $notes = !empty($receiptData['notes']) ? trim((string)$receiptData['notes']) : null;

        $poItemId = (int)($itemData['purchase_order_item_id'] ?? 0);
        $receivedQty = (float)($itemData['received_quantity'] ?? 0);

        if ($poId <= 0) {
            throw new InvalidArgumentException('Geçerli bir Satın Alma Siparişi seçilmelidir.');
        }

        if ($warehouseId <= 0 || $locationId <= 0) {
            throw new InvalidArgumentException('Depo ve lokasyon seçimi zorunludur.');
        }

        if ($receivedQty <= 0) {
            throw new InvalidArgumentException('Teslim alınan miktar 0\'dan büyük olmalıdır.');
        }

        if ($poItemId <= 0) {
            throw new InvalidArgumentException('Geçerli bir sipariş kalemi seçilmelidir.');
        }

        if ($userId <= 0) {
            throw new InvalidArgumentException('İşlemi yapan kullanıcı oturumu geçersiz.');
        }

        // =====================================================================
        // TEK ATOMİK TRANSACTION BAŞLAT
        // =====================================================================
        $this->pdo->beginTransaction();

        try {
            // 1. Depo & Lokasyon Uyumu Kontrolü
            $stmtLoc = $this->pdo->prepare("
                SELECT l.id, l.name as loc_name, w.id as wh_id, w.name as wh_name 
                FROM locations l
                INNER JOIN warehouses w ON w.id = l.warehouse_id
                WHERE l.id = :loc_id AND w.id = :wh_id AND l.is_active = 1 AND w.is_active = 1
                LIMIT 1
            ");
            $stmtLoc->execute([':loc_id' => $locationId, ':wh_id' => $warehouseId]);
            $locRow = $stmtLoc->fetch(PDO::FETCH_ASSOC);

            if (!$locRow) {
                throw new InvalidArgumentException('Seçilen lokasyon bu depoya ait değil veya pasif durumda.');
            }

            // 2. Sipariş Başlığını Kilitleyerek Oku (FOR UPDATE)
            $stmtPo = $this->pdo->prepare("
                SELECT id, order_no, supplier_id, status, currency 
                FROM purchase_orders 
                WHERE id = :id 
                FOR UPDATE
            ");
            $stmtPo->execute([':id' => $poId]);
            $po = $stmtPo->fetch(PDO::FETCH_ASSOC);

            if (!$po) {
                throw new RuntimeException("ID={$poId} olan satın alma siparişi bulunamadı.");
            }

            if (!in_array($po['status'], ['CONFIRMED', 'PARTIALLY_RECEIVED'], true)) {
                throw new RuntimeException("Yalnızca ONAYLANDI (CONFIRMED) veya KISMİ TESLİM ALINDI (PARTIALLY_RECEIVED) durumundaki siparişlere mal kabul yapılabilir. Mevcut durum: '{$po['status']}'");
            }

            // 3. Sipariş Kalemini Kilitleyerek Oku (FOR UPDATE)
            $stmtPoi = $this->pdo->prepare("
                SELECT poi.*, m.code as material_code, m.name as material_name 
                FROM purchase_order_items poi
                LEFT JOIN materials m ON m.id = poi.material_id
                WHERE poi.id = :item_id AND poi.purchase_order_id = :po_id
                FOR UPDATE
            ");
            $stmtPoi->execute([':item_id' => $poItemId, ':po_id' => $poId]);
            $poItem = $stmtPoi->fetch(PDO::FETCH_ASSOC);

            if (!$poItem) {
                throw new RuntimeException("Siparişe ait malzeme kalemi bulunamadı.");
            }

            $orderedQty = (float)$poItem['ordered_quantity'];
            $currentReceivedQty = (float)$poItem['received_quantity'];
            $remainingQty = round($orderedQty - $currentReceivedQty, 4);

            if ($receivedQty > $remainingQty) {
                throw new InvalidArgumentException(sprintf(
                    "Teslim alınan miktar (%.2f), siparişin kalan teslimat miktarından (%.2f) fazla olamaz.",
                    $receivedQty,
                    $remainingQty
                ));
            }

            $materialId = (int)$poItem['material_id'];
            $unitPrice = (float)$poItem['unit_price'];
            $currency = $poItem['currency'] ?: ($po['currency'] ?: 'TRY');
            $lineTotalPrice = round($receivedQty * $unitPrice, 4);

            // 4. Benzersiz Receipt No Üret
            $receiptNo = $this->generateReceiptNo();

            // 5. purchase_receipts Başlık Kaydı Ekle
            $stmtReceipt = $this->pdo->prepare("
                INSERT INTO purchase_receipts (
                    receipt_no,
                    purchase_order_id,
                    warehouse_id,
                    location_id,
                    received_by,
                    receipt_date,
                    delivery_note_no,
                    supplier_document_no,
                    status,
                    notes,
                    created_at,
                    updated_at
                ) VALUES (
                    :receipt_no,
                    :purchase_order_id,
                    :warehouse_id,
                    :location_id,
                    :received_by,
                    :receipt_date,
                    :delivery_note_no,
                    :supplier_document_no,
                    'COMPLETED',
                    :notes,
                    NOW(),
                    NOW()
                )
            ");
            $stmtReceipt->execute([
                ':receipt_no'            => $receiptNo,
                ':purchase_order_id'     => $poId,
                ':warehouse_id'          => $warehouseId,
                ':location_id'           => $locationId,
                ':received_by'           => $userId,
                ':receipt_date'          => $receiptDate,
                ':delivery_note_no'      => $deliveryNoteNo,
                ':supplier_document_no'  => $supplierDocNo,
                ':notes'                 => $notes,
            ]);
            $receiptId = (int)$this->pdo->lastInsertId();

            // 6. Stok Bakiye Kilitle & Güncelle (stock_balances)
            $stmtLockBal = $this->pdo->prepare("
                SELECT id, quantity 
                FROM stock_balances 
                WHERE material_id = :material_id AND location_id = :location_id 
                FOR UPDATE
            ");
            $stmtLockBal->execute([
                ':material_id' => $materialId,
                ':location_id' => $locationId,
            ]);
            $balanceRow = $stmtLockBal->fetch(PDO::FETCH_ASSOC);

            if ($balanceRow === false) {
                // Yeni bakiye satırı
                $stmtInsBal = $this->pdo->prepare("
                    INSERT INTO stock_balances (material_id, location_id, quantity, updated_at)
                    VALUES (:material_id, :location_id, :quantity, NOW())
                ");
                $stmtInsBal->execute([
                    ':material_id' => $materialId,
                    ':location_id' => $locationId,
                    ':quantity'    => $receivedQty,
                ]);
            } else {
                // Mevcut bakiyeyi artır
                $stmtUpBal = $this->pdo->prepare("
                    UPDATE stock_balances 
                    SET quantity = quantity + :quantity, updated_at = NOW() 
                    WHERE id = :id
                ");
                $stmtUpBal->execute([
                    ':quantity' => $receivedQty,
                    ':id'       => $balanceRow['id'],
                ]);
            }

            // 7. Stok Hareketi Ekle (stock_movements with movement_type = 'IN')
            $movementDesc = sprintf(
                'Mal Kabul - PO No: %s - İrsaliye: %s - Makbuz: %s',
                $po['order_no'],
                $deliveryNoteNo ?: '-',
                $receiptNo
            );

            $stmtMov = $this->pdo->prepare("
                INSERT INTO stock_movements (
                    material_id,
                    location_id,
                    user_id,
                    movement_type,
                    quantity,
                    unit_price,
                    total_price,
                    currency,
                    reference_no,
                    description,
                    created_at
                ) VALUES (
                    :material_id,
                    :location_id,
                    :user_id,
                    'IN',
                    :quantity,
                    :unit_price,
                    :total_price,
                    :currency,
                    :reference_no,
                    :description,
                    NOW()
                )
            ");
            $stmtMov->execute([
                ':material_id'  => $materialId,
                ':location_id'  => $locationId,
                ':user_id'      => $userId,
                ':quantity'     => $receivedQty,
                ':unit_price'   => $unitPrice,
                ':total_price'  => $lineTotalPrice,
                ':currency'     => $currency,
                ':reference_no' => $receiptNo,
                ':description'  => $movementDesc,
            ]);
            $stockMovementId = (int)$this->pdo->lastInsertId();

            // 8. purchase_receipt_items Kalem Kaydı Ekle
            $stmtRecItem = $this->pdo->prepare("
                INSERT INTO purchase_receipt_items (
                    purchase_receipt_id,
                    purchase_order_item_id,
                    material_id,
                    received_quantity,
                    accepted_quantity,
                    rejected_quantity,
                    stock_movement_id,
                    notes,
                    created_at,
                    updated_at
                ) VALUES (
                    :purchase_receipt_id,
                    :purchase_order_item_id,
                    :material_id,
                    :received_quantity,
                    :accepted_quantity,
                    :rejected_quantity,
                    :stock_movement_id,
                    :notes,
                    NOW(),
                    NOW()
                )
            ");
            $stmtRecItem->execute([
                ':purchase_receipt_id'    => $receiptId,
                ':purchase_order_item_id' => $poItemId,
                ':material_id'            => $materialId,
                ':received_quantity'      => $receivedQty,
                ':accepted_quantity'      => $receivedQty,
                ':rejected_quantity'      => 0.000,
                ':stock_movement_id'      => $stockMovementId,
                ':notes'                  => $itemData['notes'] ?? null,
            ]);

            // 9. PO Kalemindeki Teslim Alınan Miktarı Güncelle
            $newPoiReceivedQty = $currentReceivedQty + $receivedQty;
            $stmtUpPoi = $this->pdo->prepare("
                UPDATE purchase_order_items 
                SET received_quantity = :rec_qty, updated_at = NOW() 
                WHERE id = :id
            ");
            $stmtUpPoi->execute([
                ':rec_qty' => $newPoiReceivedQty,
                ':id'      => $poItemId,
            ]);

            // 10. PO Genel Durumunu Hesapla ve Güncelle
            $stmtAllItems = $this->pdo->prepare("
                SELECT ordered_quantity, received_quantity 
                FROM purchase_order_items 
                WHERE purchase_order_id = :po_id
            ");
            $stmtAllItems->execute([':po_id' => $poId]);
            $allItems = $stmtAllItems->fetchAll(PDO::FETCH_ASSOC);

            $allCompleted = true;
            $anyReceived = false;

            foreach ($allItems as $it) {
                $itOrd = (float)$it['ordered_quantity'];
                $itRec = (float)$it['received_quantity'];

                if ($itRec < $itOrd) {
                    $allCompleted = false;
                }
                if ($itRec > 0) {
                    $anyReceived = true;
                }
            }

            $newPoStatus = $allCompleted ? 'RECEIVED' : ($anyReceived ? 'PARTIALLY_RECEIVED' : $po['status']);

            $stmtUpPo = $this->pdo->prepare("
                UPDATE purchase_orders 
                SET status = :status, updated_at = NOW() 
                WHERE id = :id
            ");
            $stmtUpPo->execute([
                ':status' => $newPoStatus,
                ':id'     => $poId,
            ]);

            // 11. Audit Log Kaydı
            $stmtAudit = $this->pdo->prepare("
                INSERT INTO audit_logs (
                    user_id,
                    action,
                    module,
                    entity_type,
                    entity_id,
                    description,
                    table_name,
                    record_id,
                    new_values,
                    ip_address,
                    user_agent,
                    created_at
                ) VALUES (
                    :user_id,
                    'PURCHASE_RECEIPT_CREATED',
                    'PURCHASE',
                    'purchase_receipts',
                    :entity_id,
                    :description,
                    'purchase_receipts',
                    :record_id,
                    :new_values,
                    :ip_address,
                    :user_agent,
                    NOW()
                )
            ");
            $auditNewVals = json_encode([
                'receipt_no'          => $receiptNo,
                'purchase_order_id'   => $poId,
                'order_no'            => $po['order_no'],
                'material_id'         => $materialId,
                'material_code'       => $poItem['material_code'],
                'received_quantity'   => $receivedQty,
                'warehouse_id'        => $warehouseId,
                'location_id'         => $locationId,
                'stock_movement_id'   => $stockMovementId,
                'new_po_status'       => $newPoStatus,
            ], JSON_UNESCAPED_UNICODE);

            $stmtAudit->execute([
                ':user_id'     => $userId,
                ':entity_id'   => $receiptId,
                ':description' => "Mal kabul kaydı oluşturuldu: {$receiptNo} (PO: {$po['order_no']}, Miktar: {$receivedQty})",
                ':record_id'   => $receiptId,
                ':new_values'  => $auditNewVals,
                ':ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                ':user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI/System',
            ]);

            $this->pdo->commit();
            return $receiptId;

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Tekil mal kabul makbuzunu detayları ve kalemleriyle getirir.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                pr.*,
                po.order_no,
                po.status as po_status,
                po.supplier_id,
                s.name as supplier_name,
                s.code as supplier_code,
                w.name as warehouse_name,
                w.code as warehouse_code,
                l.name as location_name,
                l.code as location_code,
                TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) as receiver_name,
                u.username as receiver_username
            FROM purchase_receipts pr
            LEFT JOIN purchase_orders po ON po.id = pr.purchase_order_id
            LEFT JOIN suppliers s ON s.id = po.supplier_id
            LEFT JOIN warehouses w ON w.id = pr.warehouse_id
            LEFT JOIN locations l ON l.id = pr.location_id
            LEFT JOIN users u ON u.id = pr.received_by
            WHERE pr.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Bir makbuza ait kalemleri getirir.
     */
    public function getItems(int $receiptId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                pri.*,
                m.code as material_code,
                m.name as material_name,
                un.symbol as unit_symbol,
                poi.ordered_quantity,
                poi.unit_price,
                poi.currency
            FROM purchase_receipt_items pri
            LEFT JOIN materials m ON m.id = pri.material_id
            LEFT JOIN units un ON un.id = m.unit_id
            LEFT JOIN purchase_order_items poi ON poi.id = pri.purchase_order_item_id
            WHERE pri.purchase_receipt_id = :receipt_id
            ORDER BY pri.id ASC
        ");
        $stmt->execute([':receipt_id' => $receiptId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Bir siparişe (PO) ait tüm mal kabul makbuzlarını kalemleriyle getirir.
     */
    public function getByPurchaseOrderId(int $purchaseOrderId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                pr.*,
                w.name as warehouse_name,
                w.code as warehouse_code,
                l.name as location_name,
                l.code as location_code,
                TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) as receiver_name,
                u.username as receiver_username,
                (SELECT SUM(pri.received_quantity) FROM purchase_receipt_items pri WHERE pri.purchase_receipt_id = pr.id) as total_received_quantity
            FROM purchase_receipts pr
            LEFT JOIN warehouses w ON w.id = pr.warehouse_id
            LEFT JOIN locations l ON l.id = pr.location_id
            LEFT JOIN users u ON u.id = pr.received_by
            WHERE pr.purchase_order_id = :po_id
            ORDER BY pr.id DESC
        ");
        $stmt->execute([':po_id' => $purchaseOrderId]);
        $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($receipts as &$rec) {
            $rec['items'] = $this->getItems((int)$rec['id']);
        }

        return $receipts;
    }

    /**
     * Aktif depoları ve bağlı lokasyonlarını döner (Dependent dropdown için).
     */
    public function getWarehousesWithLocations(): array
    {
        $stmtWh = $this->pdo->query("
            SELECT id, code, name 
            FROM warehouses 
            WHERE is_active = 1 
            ORDER BY id ASC
        ");
        $warehouses = $stmtWh->fetchAll(PDO::FETCH_ASSOC);

        $stmtLoc = $this->pdo->query("
            SELECT id, warehouse_id, code, name 
            FROM locations 
            WHERE is_active = 1 
            ORDER BY warehouse_id ASC, id ASC
        ");
        $locations = $stmtLoc->fetchAll(PDO::FETCH_ASSOC);

        $locMap = [];
        foreach ($locations as $l) {
            $locMap[$l['warehouse_id']][] = $l;
        }

        foreach ($warehouses as &$wh) {
            $wh['locations'] = $locMap[$wh['id']] ?? [];
        }

        return $warehouses;
    }

    /**
     * Mal kabul kayıtlarını filtrelenmiş ve sayfalanmış olarak listeler.
     */
    public function getAll(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $sql = "
            SELECT 
                pr.*,
                po.order_no,
                po.status as po_status,
                s.id as supplier_id,
                s.name as supplier_name,
                w.name as warehouse_name,
                w.code as warehouse_code,
                l.name as location_name,
                l.code as location_code,
                TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) as receiver_name,
                u.username as receiver_username,
                COALESCE((SELECT SUM(pri.received_quantity) FROM purchase_receipt_items pri WHERE pri.purchase_receipt_id = pr.id), 0) as total_received_quantity,
                COALESCE((SELECT COUNT(pri.id) FROM purchase_receipt_items pri WHERE pri.purchase_receipt_id = pr.id), 0) as item_count
            FROM purchase_receipts pr
            LEFT JOIN purchase_orders po ON po.id = pr.purchase_order_id
            LEFT JOIN suppliers s ON s.id = po.supplier_id
            LEFT JOIN warehouses w ON w.id = pr.warehouse_id
            LEFT JOIN locations l ON l.id = pr.location_id
            LEFT JOIN users u ON u.id = pr.received_by
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $sql .= " AND (
                pr.receipt_no LIKE :search 
                OR po.order_no LIKE :search 
                OR s.name LIKE :search 
                OR pr.delivery_note_no LIKE :search 
                OR pr.supplier_document_no LIKE :search
                OR pr.notes LIKE :search
            )";
            $params[':search'] = $search;
        }

        if (!empty($filters['status'])) {
            $sql .= " AND pr.status = :status";
            $params[':status'] = trim((string)$filters['status']);
        }

        if (!empty($filters['supplier_id'])) {
            $sql .= " AND po.supplier_id = :supplier_id";
            $params[':supplier_id'] = (int)$filters['supplier_id'];
        }

        if (!empty($filters['warehouse_id'])) {
            $sql .= " AND pr.warehouse_id = :warehouse_id";
            $params[':warehouse_id'] = (int)$filters['warehouse_id'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND pr.receipt_date >= :date_from";
            $params[':date_from'] = trim((string)$filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND pr.receipt_date <= :date_to";
            $params[':date_to'] = trim((string)$filters['date_to']);
        }

        $sql .= " ORDER BY pr.id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Filtre kriterlerine uyan toplam mal kabul sayısını döner.
     */
    public function countAll(array $filters = []): int
    {
        $sql = "
            SELECT COUNT(DISTINCT pr.id) 
            FROM purchase_receipts pr
            LEFT JOIN purchase_orders po ON po.id = pr.purchase_order_id
            LEFT JOIN suppliers s ON s.id = po.supplier_id
            LEFT JOIN warehouses w ON w.id = pr.warehouse_id
            LEFT JOIN locations l ON l.id = pr.location_id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $sql .= " AND (
                pr.receipt_no LIKE :search 
                OR po.order_no LIKE :search 
                OR s.name LIKE :search 
                OR pr.delivery_note_no LIKE :search 
                OR pr.supplier_document_no LIKE :search
                OR pr.notes LIKE :search
            )";
            $params[':search'] = $search;
        }

        if (!empty($filters['status'])) {
            $sql .= " AND pr.status = :status";
            $params[':status'] = trim((string)$filters['status']);
        }

        if (!empty($filters['supplier_id'])) {
            $sql .= " AND po.supplier_id = :supplier_id";
            $params[':supplier_id'] = (int)$filters['supplier_id'];
        }

        if (!empty($filters['warehouse_id'])) {
            $sql .= " AND pr.warehouse_id = :warehouse_id";
            $params[':warehouse_id'] = (int)$filters['warehouse_id'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND pr.receipt_date >= :date_from";
            $params[':date_from'] = trim((string)$filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND pr.receipt_date <= :date_to";
            $params[':date_to'] = trim((string)$filters['date_to']);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Mal kabul gösterge kartları (KPI) için toplam metrikleri döner.
     */
    public function getKpis(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                COUNT(pr.id) as total_count,
                SUM(CASE WHEN pr.receipt_date = CURDATE() THEN 1 ELSE 0 END) as today_count,
                SUM(CASE WHEN pr.receipt_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 ELSE 0 END) as month_count,
                COALESCE((SELECT SUM(pri.received_quantity) FROM purchase_receipt_items pri), 0) as total_received_qty
            FROM purchase_receipts pr
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_count'        => (int)($row['total_count'] ?? 0),
            'today_count'        => (int)($row['today_count'] ?? 0),
            'month_count'        => (int)($row['month_count'] ?? 0),
            'total_received_qty' => (float)($row['total_received_qty'] ?? 0),
        ];
    }

    /**
     * Filtre seçenekleri için tedarikçi, depo ve durum listelerini döner.
     */
    public function getFilterOptions(): array
    {
        $stmtSup = $this->pdo->query("
            SELECT id, code, name 
            FROM suppliers 
            ORDER BY name ASC
        ");
        $suppliers = $stmtSup->fetchAll(PDO::FETCH_ASSOC);

        $stmtWh = $this->pdo->query("
            SELECT id, code, name 
            FROM warehouses 
            ORDER BY name ASC
        ");
        $warehouses = $stmtWh->fetchAll(PDO::FETCH_ASSOC);

        $statuses = ['COMPLETED', 'CANCELLED'];

        return [
            'suppliers'  => $suppliers,
            'warehouses' => $warehouses,
            'statuses'   => $statuses,
        ];
    }
}

