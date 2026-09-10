<?php

require_once __DIR__ . '/../Models/Mes.php';
require_once __DIR__ . '/ProductionStockService.php';

class MesEventValidationService
{
    private PDO $pdo;
    private Mes $mesModel;
    private ProductionStockService $stockService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->mesModel = new Mes($this->pdo);
        $this->stockService = new ProductionStockService($this->pdo);
    }

    /**
     * Validate an incoming MES event payload before ingestion and stock execution.
     * 
     * @param array $payload Incoming event payload
     * @param array $options Optional context (source_location_id, etc.)
     * @return array
     */
    public function validateEvent(array $payload, array $options = []): array
    {
        // 1. Basic Payload & Quantity Validation
        $eventId = trim($payload['event_id'] ?? '');
        if ($eventId === '') {
            $eventId = 'MES-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
        }

        $eventType = strtoupper(trim($payload['event_type'] ?? 'PANEL_COMPLETED'));
        $allowedEventTypes = ['PANEL_COMPLETED', 'PRODUCTION_TICK', 'QUALITY_INSPECTED'];
        if (!in_array($eventType, $allowedEventTypes, true)) {
            return [
                'valid'    => false,
                'code'     => 'INVALID_EVENT_TYPE',
                'event_id' => $eventId,
                'message'  => "Geçersiz olay tipi: {$eventType}. Desteklenen: " . implode(', ', $allowedEventTypes)
            ];
        }

        $quantity = (float)($payload['quantity'] ?? 1.0);
        if ($quantity <= 0) {
            return [
                'valid'    => false,
                'code'     => 'INVALID_PAYLOAD',
                'event_id' => $eventId,
                'message'  => 'Geçersiz üretim miktarı: Miktar 0\'dan büyük olmalıdır.'
            ];
        }

        // 2. Duplicate Event (Idempotency) Check
        $stmtCheck = $this->pdo->prepare("SELECT * FROM mes_production_events WHERE event_id = ?");
        $stmtCheck->execute([$eventId]);
        $existingEvent = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existingEvent && $existingEvent['status'] === 'PROCESSED') {
            return [
                'valid'        => false,
                'code'         => 'DUPLICATE_EVENT',
                'event_id'     => $eventId,
                'quantity'     => (float)$existingEvent['quantity'],
                'processed_at' => $existingEvent['processed_at'],
                'message'      => 'Bu üretim olayı (Event ID: ' . $eventId . ') daha önce işlenmiş.'
            ];
        }

        // 3. Work Order Resolution & State Validation
        $workOrderNo = trim($payload['work_order_no'] ?? '');
        $workOrderId = !empty($payload['work_order_id']) ? (int)$payload['work_order_id'] : 0;
        
        $workOrder = null;
        if ($workOrderNo !== '') {
            $workOrder = $this->mesModel->getWorkOrderByNo($workOrderNo);
        }
        if (!$workOrder && $workOrderId > 0) {
            $workOrder = $this->mesModel->getWorkOrderById($workOrderId);
        }

        if (!$workOrder) {
            return [
                'valid'    => false,
                'code'     => 'WORK_ORDER_NOT_FOUND',
                'event_id' => $eventId,
                'message'  => 'İş emri bulunamadı: ' . ($workOrderNo ?: ($workOrderId ? "ID: {$workOrderId}" : 'Belirtilmedi'))
            ];
        }

        $woId = (int)$workOrder['id'];
        $woStatus = strtoupper($workOrder['status']);
        $currentProduced = (float)$workOrder['produced_quantity'];
        $plannedQty = (float)$workOrder['planned_quantity'];

        // 4. Target Protection (Target Limit & Completed Check)
        if ($currentProduced >= $plannedQty || $woStatus === 'COMPLETED') {
            if ($woStatus !== 'COMPLETED') {
                $this->mesModel->updateWorkOrderStatus($woId, 'COMPLETED');
            }
            return [
                'valid'             => false,
                'code'              => 'TARGET_REACHED',
                'event_id'          => $eventId,
                'produced_quantity' => $currentProduced,
                'planned_quantity'  => $plannedQty,
                'message'           => sprintf('İş emri %s hedef üretim miktarına (%s / %s adet) zaten ulaşmış (TARGET_REACHED). Fazladan üretim engellendi.', $workOrder['work_order_no'], number_format($currentProduced, 0, ',', '.'), number_format($plannedQty, 0, ',', '.'))
            ];
        }

        // Check if work order is in valid state for production
        if (!in_array($woStatus, ['RUNNING', 'READY', 'PLANNED'], true)) {
            return [
                'valid'      => false,
                'code'       => 'WORK_ORDER_NOT_RUNNING',
                'event_id'   => $eventId,
                'work_order' => $workOrder['work_order_no'],
                'status'     => $woStatus,
                'message'    => sprintf('İş emri %s üretime uygun durumda değil (Mevcut Durum: %s). Yalnızca RUNNING / READY / PLANNED iş emirleri üretim kabul eder.', $workOrder['work_order_no'], $woStatus)
            ];
        }

        if (($currentProduced + $quantity) > $plannedQty) {
            return [
                'valid'              => false,
                'code'               => 'PLANNED_QUANTITY_EXCEEDED',
                'event_id'           => $eventId,
                'produced_quantity'  => $currentProduced,
                'planned_quantity'   => $plannedQty,
                'attempted_quantity' => $quantity,
                'remaining_allowed'  => max(0, $plannedQty - $currentProduced),
                'message'            => sprintf('Üretim miktarı planlanan hedefi (%s adet) aşamaz. Kalan üretilebilir miktar: %s adet.', number_format($plannedQty, 0, ',', '.'), number_format(max(0, $plannedQty - $currentProduced), 0, ',', '.'))
            ];
        }

        // 5. Product Material Match Validation
        $productCode = trim($payload['product_code'] ?? '');
        $productMatId = !empty($payload['product_material_id']) ? (int)$payload['product_material_id'] : 0;
        $explicitProductSpecified = ($productCode !== '' || $productMatId > 0);

        $product = null;
        if ($productCode !== '') {
            $stmtMat = $this->pdo->prepare("SELECT * FROM materials WHERE code = ? AND is_active = 1");
            $stmtMat->execute([$productCode]);
            $product = $stmtMat->fetch(PDO::FETCH_ASSOC);
        }
        if (!$product && $productMatId > 0) {
            $stmtMat = $this->pdo->prepare("SELECT * FROM materials WHERE id = ? AND is_active = 1");
            $stmtMat->execute([$productMatId]);
            $product = $stmtMat->fetch(PDO::FETCH_ASSOC);
        }
        if (!$product && !$explicitProductSpecified) {
            // Default to work order's product only if not explicitly specified in payload
            $stmtMat = $this->pdo->prepare("SELECT * FROM materials WHERE id = ? AND is_active = 1");
            $stmtMat->execute([(int)$workOrder['product_material_id']]);
            $product = $stmtMat->fetch(PDO::FETCH_ASSOC);
        }

        if (!$product) {
            return [
                'valid'    => false,
                'code'     => 'INVALID_PRODUCT',
                'event_id' => $eventId,
                'message'  => 'Ürün malzeme kartı bulunamadı veya pasif: ' . ($productCode ?: "ID: {$productMatId}")
            ];
        }

        // Verify product matches the work order
        if ((int)$product['id'] !== (int)$workOrder['product_material_id']) {
            return [
                'valid'    => false,
                'code'     => 'INVALID_PRODUCT',
                'event_id' => $eventId,
                'message'  => sprintf('Eventteki ürün (%s) ile iş emrindeki ürün (%s) uyuşmuyor.', $product['code'], $workOrder['product_code'])
            ];
        }

        // 6. Production Line Match Validation
        $lineCode = trim($payload['production_line'] ?? '');
        $lineId = !empty($payload['production_line_id']) ? (int)$payload['production_line_id'] : 0;
        $explicitLineSpecified = ($lineCode !== '' || $lineId > 0);

        $line = null;
        if ($lineCode !== '') {
            $stmtLine = $this->pdo->prepare("SELECT * FROM production_lines WHERE code = ? AND is_active = 1");
            $stmtLine->execute([$lineCode]);
            $line = $stmtLine->fetch(PDO::FETCH_ASSOC);
        }
        if (!$line && $lineId > 0) {
            $stmtLine = $this->pdo->prepare("SELECT * FROM production_lines WHERE id = ? AND is_active = 1");
            $stmtLine->execute([$lineId]);
            $line = $stmtLine->fetch(PDO::FETCH_ASSOC);
        }
        if (!$line && !$explicitLineSpecified) {
            // Default to work order's production line only if not explicitly specified
            $stmtLine = $this->pdo->prepare("SELECT * FROM production_lines WHERE id = ? AND is_active = 1");
            $stmtLine->execute([(int)$workOrder['production_line_id']]);
            $line = $stmtLine->fetch(PDO::FETCH_ASSOC);
        }

        if (!$line) {
            return [
                'valid'    => false,
                'code'     => 'INVALID_LINE',
                'event_id' => $eventId,
                'message'  => 'Üretim hattı bulunamadı veya pasif: ' . ($lineCode ?: "ID: {$lineId}")
            ];
        }

        // Verify line matches the work order
        if ((int)$line['id'] !== (int)$workOrder['production_line_id']) {
            return [
                'valid'    => false,
                'code'     => 'INVALID_LINE',
                'event_id' => $eventId,
                'message'  => sprintf('Eventteki üretim hattı (%s) ile iş emrinin atandığı hat (%s) uyuşmuyor.', $line['name'], $workOrder['line_name'])
            ];
        }

        // Verify line operational status (Maintenance / Fault)
        $lineStatus = strtoupper($line['status'] ?? 'IDLE');
        if ($lineStatus === 'MAINTENANCE') {
            $note = !empty($line['status_note']) ? " ({$line['status_note']})" : '';
            return [
                'valid'    => false,
                'code'     => 'LINE_NOT_AVAILABLE',
                'event_id' => $eventId,
                'message'  => sprintf('Üretim hattı (%s) bakımda olduğu için üretim yapılamaz%s.', $line['name'], $note)
            ];
        }

        if ($lineStatus === 'FAULT') {
            $note = !empty($line['status_note']) ? " ({$line['status_note']})" : '';
            return [
                'valid'    => false,
                'code'     => 'LINE_NOT_AVAILABLE',
                'event_id' => $eventId,
                'message'  => sprintf('Üretim hattı (%s) arızada olduğu için üretim yapılamaz%s.', $line['name'], $note)
            ];
        }

        // 7. Recipe / BOM Match Validation
        $recipeId = (int)$workOrder['recipe_id'];
        $recipeStmt = $this->pdo->prepare("SELECT * FROM recipes WHERE id = ? AND is_active = 1");
        $recipeStmt->execute([$recipeId]);
        $recipe = $recipeStmt->fetch(PDO::FETCH_ASSOC);

        if (!$recipe) {
            return [
                'valid'    => false,
                'code'     => 'INVALID_RECIPE',
                'event_id' => $eventId,
                'message'  => 'İş emrine bağlı BOM reçetesi bulunamadı veya pasif (ID: ' . $recipeId . ').'
            ];
        }

        // Check if recipe items exist
        $itemsStmt = $this->pdo->prepare("SELECT COUNT(*) FROM recipe_items WHERE recipe_id = ?");
        $itemsStmt->execute([$recipeId]);
        $itemCount = (int)$itemsStmt->fetchColumn();

        if ($itemCount === 0) {
            return [
                'valid'    => false,
                'code'     => 'INVALID_RECIPE',
                'event_id' => $eventId,
                'message'  => 'BOM reçetesine tanımlı hammadde kalemi bulunamadı.'
            ];
        }

        // 8. Duplicate Panel Number Check (if provided in payload)
        if (!empty($payload['panel_serial_no']) || !empty($payload['serial_no'])) {
            $serialNo = trim($payload['panel_serial_no'] ?? $payload['serial_no']);
            $stmtSn = $this->pdo->prepare("SELECT id FROM panel_units WHERE serial_no = ?");
            $stmtSn->execute([$serialNo]);
            if ($stmtSn->fetchColumn()) {
                return [
                    'valid'     => false,
                    'code'      => 'DUPLICATE_PANEL',
                    'event_id'  => $eventId,
                    'serial_no' => $serialNo,
                    'message'   => "Mükerrer panel seri numarası: {$serialNo} daha önce üretilmiş ve depoya alınmış."
                ];
            }
        }

        // 9. BOM Ingredient Stock Availability Pre-Validation
        $sourceLocationId = (int)($options['source_location_id'] ?? 1);
        try {
            $stockNeeds = $this->stockService->calculateStockNeeds($recipeId, $quantity, $sourceLocationId);
            if (empty($stockNeeds['can_produce'])) {
                $missingItems = [];
                $missingDetails = [];
                foreach ($stockNeeds['items'] as $it) {
                    if (!$it['is_sufficient']) {
                        $unit = $it['unit_symbol'] ?: 'AD';
                        $missingItems[] = sprintf(
                            '%s (Gerekli: %s %s, Mevcut: %s %s, Eksik: %s %s)',
                            $it['material_name'],
                            number_format($it['required_qty'], ($it['required_qty'] == (int)$it['required_qty'] ? 0 : 2), ',', '.'), $unit,
                            number_format($it['available_qty'], ($it['available_qty'] == (int)$it['available_qty'] ? 0 : 2), ',', '.'), $unit,
                            number_format($it['missing_qty'], ($it['missing_qty'] == (int)$it['missing_qty'] ? 0 : 2), ',', '.'), $unit
                        );
                        $missingDetails[] = [
                            'material_id'   => $it['material_id'],
                            'material_name' => $it['material_name'],
                            'material_code' => $it['material_code'],
                            'required_qty'  => $it['required_qty'],
                            'available_qty' => $it['available_qty'],
                            'missing_qty'   => $it['missing_qty'],
                            'unit_symbol'   => $unit
                        ];
                    }
                }
                return [
                    'valid'           => false,
                    'code'            => 'INSUFFICIENT_STOCK',
                    'event_id'        => $eventId,
                    'missing_items'   => $missingItems,
                    'missing_details' => $missingDetails,
                    'message'         => 'Yetersiz hammadde stoğu: ' . implode('; ', $missingItems)
                ];
            }
        } catch (Throwable $e) {
            return [
                'valid'    => false,
                'code'     => 'INSUFFICIENT_STOCK',
                'event_id' => $eventId,
                'message'  => 'Stok gereksinimi kontrolü sırasında hata: ' . $e->getMessage()
            ];
        }

        // 10. ALL VALIDATIONS PASSED!
        return [
            'valid'    => true,
            'code'     => 'EVENT_VALID',
            'event_id' => $eventId,
            'message'  => 'MES Event doğrulaması başarılı.',
            'context'  => [
                'work_order'        => $workOrder,
                'product'           => $product,
                'line'              => $line,
                'recipe'            => $recipe,
                'quantity'          => $quantity,
                'event_type'        => $eventType,
                'event_time'        => trim($payload['event_time'] ?? date('Y-m-d H:i:s')),
                'source'            => trim($payload['source'] ?? 'MES')
            ]
        ];
    }
}
