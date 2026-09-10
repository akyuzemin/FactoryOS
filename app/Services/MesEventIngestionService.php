<?php

require_once __DIR__ . '/MesEventValidationService.php';
require_once __DIR__ . '/MesProductionIntegrationService.php';
require_once __DIR__ . '/../Models/Mes.php';

class MesEventIngestionService
{
    private PDO $pdo;
    private Mes $mesModel;
    private MesEventValidationService $validationService;
    private MesProductionIntegrationService $integrationService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->mesModel = new Mes($this->pdo);
        $this->validationService = new MesEventValidationService($this->pdo);
        $this->integrationService = new MesProductionIntegrationService($this->pdo);
    }

    /**
     * Get validation service instance.
     */
    public function getValidationService(): MesEventValidationService
    {
        return $this->validationService;
    }

    /**
     * MES'ten gelen ham veya simüle edilmiş JSON/Array üretim olayını valide edip ingest eder.
     * 
     * Pipeline:
     * 1. MesEventValidationService -> Detaylı Doğrulama (Payload, Idempotency, WO, Line, Product, BOM, Target, Stock)
     * 2. mes_production_events -> PENDING Olarak Kayıt
     * 3. MesProductionIntegrationService -> Atomik Transaction İle BOM Tüketimi + Mamul Girişi
     * 4. Başarılı ise İş Emri İlerlemesini Güncelleme & COMPLETED Kontrolü
     */
    public function ingestEvent(array $payload, array $options = []): array
    {
        // 1. Gelişmiş Event Doğrulama (Validation Engine)
        $validation = $this->validationService->validateEvent($payload, $options);
        $eventId = $validation['event_id'] ?? (trim($payload['event_id'] ?? '') ?: 'MES-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3))));

        if (!$validation['valid']) {
            $code = $validation['code'];

            if ($code === 'DUPLICATE_EVENT') {
                $this->mesModel->logEvent($eventId, 'DUPLICATE_INGESTION_SKIPPED', 'Mükerrer MES olayı algılandı. Stok ve üretim tekrar işlenmedi.');
                return [
                    'success'      => true,
                    'event_id'     => $eventId,
                    'status'       => 'ALREADY_PROCESSED',
                    'code'         => 'DUPLICATE_EVENT',
                    'quantity'     => (float)($validation['quantity'] ?? 1.0),
                    'processed_at' => $validation['processed_at'] ?? date('Y-m-d H:i:s'),
                    'message'      => $validation['message']
                ];
            }

            $this->mesModel->logEvent($eventId, 'VALIDATION_FAILED_' . $code, $validation['message']);

            return [
                'success'  => false,
                'event_id' => $eventId,
                'status'   => $code,
                'code'     => $code,
                'message'  => $validation['message'],
                'details'  => $validation['missing_items'] ?? null
            ];
        }

        // 2. Doğrulanmış Varlıklar (Resolved Context)
        $ctx = $validation['context'];
        $workOrder = $ctx['work_order'];
        $product = $ctx['product'];
        $line = $ctx['line'];
        $quantity = (float)$ctx['quantity'];
        $eventType = $ctx['event_type'];
        $eventTime = $ctx['event_time'];
        $source = $ctx['source'];

        $workOrderId = (int)$workOrder['id'];
        $productMaterialId = (int)$product['id'];
        $lineId = (int)$line['id'];
        $plannedQty = (float)$workOrder['planned_quantity'];

        // 3. İdempotency Check on DB row
        $stmtCheck = $this->pdo->prepare("SELECT * FROM mes_production_events WHERE event_id = ?");
        $stmtCheck->execute([$eventId]);
        $existingEvent = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$existingEvent) {
            $stmtIns = $this->pdo->prepare("
                INSERT INTO mes_production_events (
                    event_id, work_order_id, product_material_id, production_line_id,
                    quantity, event_type, event_time, source, status, created_at
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, 'PENDING', NOW()
                )
            ");
            $stmtIns->execute([
                $eventId,
                $workOrderId,
                $productMaterialId,
                $lineId,
                $quantity,
                $eventType,
                $eventTime,
                $source
            ]);

            $this->mesModel->logEvent($eventId, 'MES_EVENT_INGESTED', sprintf('MES Olayı alındı: %s (+%.0f %s, Kaynak: %s)', $eventType, $quantity, $product['code'], $source));
        }

        // 4. Olayı Integration Service ile BOM & Stok Sistemine İşle
        if (empty($options['serial_no'])) {
            $options['serial_no'] = $payload['serial_no'] ?? ($ctx['serial_no'] ?? null);
        }

        $integrationResult = $this->integrationService->processEvent($eventId, $options);

        if ($integrationResult['status'] === 'success') {
            // Fresh values from atomic update in ProductionStockService
            $freshWo = $this->mesModel->getWorkOrderById($workOrderId);
            $newProduced = (float)($freshWo['produced_quantity'] ?? $plannedQty);

            $finalRemaining = max(0, $plannedQty - $newProduced);
            $finalPct = $plannedQty > 0 ? round(($newProduced / $plannedQty) * 100, 1) : 0;

            return [
                'success'            => true,
                'event_id'           => $eventId,
                'status'             => 'PROCESSED',
                'code'               => 'PROCESSED',
                'quantity'           => $quantity,
                'product_code'       => $product['code'],
                'product_name'       => $product['name'],
                'work_order_no'      => $workOrder['work_order_no'],
                'stock_reference'    => $integrationResult['reference_no'] ?? null,
                'unit_cost'          => $integrationResult['unit_cost'] ?? 0.0,
                'total_cost'         => $integrationResult['total_cost'] ?? 0.0,
                'currency'           => $integrationResult['currency'] ?? 'TL',
                'consumed_materials' => count($integrationResult['consumed_movements'] ?? []),
                'produced_quantity'  => $newProduced,
                'planned_quantity'   => $plannedQty,
                'remaining_quantity' => $finalRemaining,
                'progress_pct'       => $finalPct,
                'message'            => sprintf('✓ MES\'ten %.0f panel üretim olayı alındı, BOM hammadde çıkışları yapıldı ve mamul stoğa eklendi.', $quantity)
            ];
        } elseif ($integrationResult['status'] === 'already_processed') {
            return [
                'success'  => true,
                'event_id' => $eventId,
                'status'   => 'ALREADY_PROCESSED',
                'code'     => 'DUPLICATE_EVENT',
                'message'  => $integrationResult['message']
            ];
        } else {
            // Hata / Yetersiz Stok: produced_quantity KESİNLİKLE ARTIRILMAZ
            return [
                'success'  => false,
                'event_id' => $eventId,
                'status'   => 'FAILED',
                'code'     => 'INSUFFICIENT_STOCK',
                'message'  => $integrationResult['message'] ?? ($integrationResult['error'] ?? 'Bilinmeyen entegrasyon hatası'),
                'error'    => $integrationResult['error'] ?? null
            ];
        }
    }
}
