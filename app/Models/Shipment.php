<?php

class Shipment
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Yeni sevkiyat emri oluşturur.
     */
    public function create(array $data): int
    {
        // Generate shipment number: SHP-YYYYMMDD-XXXX
        $datePrefix = 'SHP-' . date('Ymd') . '-';
        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM shipments WHERE shipment_no LIKE :prefix");
        $stmtCount->execute([':prefix' => $datePrefix . '%']);
        $count = (int)$stmtCount->fetchColumn();
        $shpNo = $datePrefix . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        $stmt = $this->pdo->prepare("
            INSERT INTO shipments (shipment_no, customer_name, shipping_address, status, created_at, updated_at)
            VALUES (:shipment_no, :customer_name, :shipping_address, 'DRAFT', NOW(), NOW())
        ");
        $stmt->execute([
            ':shipment_no'      => $shpNo,
            ':customer_name'    => trim($data['customer_name']),
            ':shipping_address' => trim($data['shipping_address'])
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * ID ile sevkiyat emri detaylarını çeker.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM shipments WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Tüm sevkiyat emirlerini filtreli olarak listeler.
     */
    public function getAll(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $sql = "SELECT * FROM shipments WHERE 1=1";
        $params = [];

        if (!empty($filters['shipment_no'])) {
            $sql .= " AND shipment_no LIKE :shipment_no";
            $params[':shipment_no'] = '%' . $filters['shipment_no'] . '%';
        }
        if (!empty($filters['customer_name'])) {
            $sql .= " AND customer_name LIKE :customer_name";
            $params[':customer_name'] = '%' . $filters['customer_name'] . '%';
        }
        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }

        $sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Filtreli toplam sevkiyat kaydı sayısını döner.
     */
    public function countAll(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM shipments WHERE 1=1";
        $params = [];

        if (!empty($filters['shipment_no'])) {
            $sql .= " AND shipment_no LIKE :shipment_no";
            $params[':shipment_no'] = '%' . $filters['shipment_no'] . '%';
        }
        if (!empty($filters['customer_name'])) {
            $sql .= " AND customer_name LIKE :customer_name";
            $params[':customer_name'] = '%' . $filters['customer_name'] . '%';
        }
        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Sevkiyata eklenmiş panelleri döner.
     */
    public function getItems(int $shipmentId): array
    {
        $sql = "
            SELECT 
                si.id AS item_id,
                pu.*,
                m.name AS material_name,
                m.code AS material_code,
                w.name AS warehouse_name,
                l.code AS location_code
            FROM shipment_items si
            JOIN panel_units pu ON si.panel_unit_id = pu.id
            JOIN materials m ON pu.material_id = m.id
            LEFT JOIN warehouses w ON pu.warehouse_id = w.id
            LEFT JOIN locations l ON pu.location_id = l.id
            WHERE si.shipment_id = :shipment_id
            ORDER BY si.id ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':shipment_id' => $shipmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sevkiyata yeni panel ekler.
     */
    public function addPanel(int $shipmentId, int $panelUnitId): array
    {
        // 1. Check panel eligibility
        $stmtPanel = $this->pdo->prepare("SELECT * FROM panel_units WHERE id = :id LIMIT 1");
        $stmtPanel->execute([':id' => $panelUnitId]);
        $panel = $stmtPanel->fetch(PDO::FETCH_ASSOC);

        if (!$panel) {
            return ['success' => false, 'message' => 'Panel bulunamadı.'];
        }

        if ($panel['status'] !== 'QUALITY_APPROVED') {
            return ['success' => false, 'message' => 'Sadece kalite kontrol onayından geçmiş (QUALITY_APPROVED) paneller sevk edilebilir.'];
        }

        // 2. Check if panel is already added to another active shipment
        $stmtActive = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM shipment_items si
            JOIN shipments s ON si.shipment_id = s.id
            WHERE si.panel_unit_id = :panel_unit_id AND s.status != 'CANCELLED'
        ");
        $stmtActive->execute([':panel_unit_id' => $panelUnitId]);
        if ((int)$stmtActive->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Bu panel zaten başka bir aktif sevkiyat planına eklenmiş.'];
        }

        // 3. Add to items
        try {
            $stmtInsert = $this->pdo->prepare("
                INSERT INTO shipment_items (shipment_id, panel_unit_id, created_at)
                VALUES (:shipment_id, :panel_unit_id, NOW())
            ");
            $stmtInsert->execute([
                ':shipment_id'  => $shipmentId,
                ':panel_unit_id' => $panelUnitId
            ]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Kalem ekleme hatası: ' . $e->getMessage()];
        }
    }

    /**
     * Sevkiyattan panel çıkartır.
     */
    public function removePanel(int $shipmentId, int $panelUnitId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM shipment_items 
            WHERE shipment_id = :shipment_id AND panel_unit_id = :panel_unit_id
        ");
        return $stmt->execute([
            ':shipment_id'  => $shipmentId,
            ':panel_unit_id' => $panelUnitId
        ]);
    }

    /**
     * Sevk edilebilir panelleri filtreler (Kalite onaylı ve aktif bir sevkiyata dahil olmayan).
     */
    public function getShippablePanels(string $search = ''): array
    {
        $sql = "
            SELECT 
                pu.*,
                m.name AS material_name,
                m.code AS material_code
            FROM panel_units pu
            JOIN materials m ON pu.material_id = m.id
            WHERE pu.status = 'QUALITY_APPROVED'
              AND pu.id NOT IN (
                  SELECT si.panel_unit_id 
                  FROM shipment_items si 
                  JOIN shipments s ON si.shipment_id = s.id 
                  WHERE s.status != 'CANCELLED'
              )
        ";
        $params = [];
        if ($search !== '') {
            $sql .= " AND (pu.serial_no LIKE :search OR m.name LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        $sql .= " ORDER BY pu.id DESC LIMIT 50";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sevkiyatı tamamlar (Transaction-safe).
     */
    public function complete(int $shipmentId, int $userId): array
    {
        try {
            $this->pdo->beginTransaction();

            // 1. Get shipment and check status
            $shipment = $this->getById($shipmentId);
            if (!$shipment) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Sevkiyat emri bulunamadı.'];
            }
            if ($shipment['status'] !== 'DRAFT') {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Sadece taslak (DRAFT) durumundaki sevkiyatlar tamamlanabilir.'];
            }

            // Get items
            $items = $this->getItems($shipmentId);
            if (empty($items)) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Sevkiyat emrinde sevk edilecek panel bulunmamaktadır.'];
            }

            // 2. Update status of shipment
            $stmtUpdateShipment = $this->pdo->prepare("
                UPDATE shipments 
                SET status = 'COMPLETED', updated_at = NOW() 
                WHERE id = :id
            ");
            $stmtUpdateShipment->execute([':id' => $shipmentId]);

            // 3. Process each panel
            $stmtUpdatePanel = $this->pdo->prepare("
                UPDATE panel_units 
                SET status = 'SHIPPED', warehouse_id = 0, location_id = 0, updated_at = NOW() 
                WHERE id = :id
            ");

            $stmtInsertMovement = $this->pdo->prepare("
                INSERT INTO stock_movements (material_id, location_id, user_id, movement_type, quantity, unit_price, total_price, currency, reference_no, description, created_at)
                VALUES (:material_id, :location_id, :user_id, 'OUT', :quantity, :unit_price, :total_price, 'TL', :reference_no, :description, NOW())
            ");

            foreach ($items as $item) {
                // Ensure panel status is still QUALITY_APPROVED at this moment
                if ($item['status'] !== 'QUALITY_APPROVED') {
                    throw new Exception("HATA: " . $item['serial_no'] . " seri numaralı panel artık sevk edilebilir (QUALITY_APPROVED) durumda değil.");
                }

                // 3a. Update panel status to SHIPPED and remove warehouse mapping
                $stmtUpdatePanel->execute([':id' => (int)$item['id']]);

                // 3b. Create stock movement OUT record
                $unitCost = (float)($item['unit_cost'] ?? 0.0);
                $stmtInsertMovement->execute([
                    ':material_id'  => (int)$item['material_id'],
                    ':location_id'  => (int)$item['location_id'],
                    ':user_id'      => $userId,
                    ':quantity'     => 1.0, // 1 panel OUT
                    ':unit_price'   => $unitCost,
                    ':total_price'  => $unitCost, // unitCost * 1.0
                    ':reference_no' => $shipment['shipment_no'],
                    ':description'  => "Panel Sevkiyatı - Seri: " . $item['serial_no'] . " | Müşteri: " . $shipment['customer_name']
                ]);
            }

            $this->pdo->commit();
            return ['success' => true];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Sevkiyat tamamlama hatası: ' . $e->getMessage()];
        }
    }

    /**
     * Sevkiyatı iptal eder.
     */
    public function cancel(int $shipmentId): array
    {
        $shipment = $this->getById($shipmentId);
        if (!$shipment) {
            return ['success' => false, 'message' => 'Sevkiyat emri bulunamadı.'];
        }
        if ($shipment['status'] !== 'DRAFT') {
            return ['success' => false, 'message' => 'Sadece taslak (DRAFT) durumundaki sevkiyatlar iptal edilebilir.'];
        }

        $stmt = $this->pdo->prepare("UPDATE shipments SET status = 'CANCELLED', updated_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $shipmentId]);
        return ['success' => true];
    }

    /**
     * Panel pasaportu için sevkiyat geçmişi çeker.
     */
    public function getPanelShipmentHistory(int $panelUnitId): ?array
    {
        $sql = "
            SELECT 
                s.*,
                si.created_at AS added_at
            FROM shipment_items si
            JOIN shipments s ON si.shipment_id = s.id
            WHERE si.panel_unit_id = :panel_unit_id AND s.status != 'CANCELLED'
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':panel_unit_id' => $panelUnitId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
