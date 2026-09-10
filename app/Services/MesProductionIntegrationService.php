<?php

require_once __DIR__ . '/ProductionStockService.php';
require_once __DIR__ . '/../Models/Mes.php';

class MesProductionIntegrationService
{
    private PDO $pdo;
    private ProductionStockService $stockService;
    private Mes $mesModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->stockService = new ProductionStockService($this->pdo);
        $this->mesModel = new Mes($this->pdo);
    }

    /**
     * Bekleyen (PENDING) tüm MES üretim olaylarını getirir.
     */
    public function getPendingEvents(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                e.*,
                wo.work_order_no,
                wo.recipe_id,
                wo.planned_quantity,
                wo.produced_quantity AS wo_produced_qty,
                wo.status AS wo_status,
                m.code AS product_code,
                m.name AS product_name,
                pl.code AS line_code,
                pl.name AS line_name
            FROM mes_production_events e
            JOIN mes_work_orders wo ON e.work_order_id = wo.id
            JOIN materials m ON e.product_material_id = m.id
            JOIN production_lines pl ON e.production_line_id = pl.id
            WHERE e.status = 'PENDING'
            ORDER BY e.id ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tek bir MES üretim olayını işler:
     * 1. Olay ve iş emri bilgilerini doğrular.
     * 2. BOM üzerinden hammadde ihtiyacını hesaplar.
     * 3. ProductionStockService ile tek transaction'da:
     *    - Hammaddeleri OUT olarak düşer.
     *    - Üretilen mamulü IN olarak stoğa ekler.
     *    - energy_production_logs telemetri kaydını oluşturur.
     * 4. Olay durumunu PROCESSED yapar ve loglar.
     * 5. Hata durumunda FAILED yapar ve hatayı loglar (rollback garantili).
     */
    public function processEvent(string|int $eventIdentifier, array $options = []): array
    {
        // 1. Olayı Getir
        if (is_numeric($eventIdentifier) && (int)$eventIdentifier > 0) {
            $stmt = $this->pdo->prepare("SELECT * FROM mes_production_events WHERE id = ?");
            $stmt->execute([(int)$eventIdentifier]);
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM mes_production_events WHERE event_id = ?");
            $stmt->execute([(string)$eventIdentifier]);
        }

        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            return [
                'status'   => 'error',
                'message'  => 'MES üretim olayı bulunamadı: ' . $eventIdentifier
            ];
        }

        $eventId = $event['event_id'];

        // 2. İdempotency / Duplicate Koruması
        if ($event['status'] === 'PROCESSED') {
            return [
                'status'       => 'already_processed',
                'event_id'     => $eventId,
                'processed_at' => $event['processed_at'],
                'message'      => "Olay {$eventId} daha önce başarıyla işlenmiş. Tekrar stok düşülemez."
            ];
        }

        if ($event['status'] === 'IGNORED') {
            return [
                'status'   => 'ignored',
                'event_id' => $eventId,
                'message'  => "Olay {$eventId} iptal/yok sayılmış."
            ];
        }

        // 3. Bağlı İş Emrini Getir
        $workOrder = $this->mesModel->getWorkOrderById((int)$event['work_order_id']);
        if (!$workOrder) {
            $this->markEventFailed($eventId, 'Bağlı iş emri bulunamadı (ID: ' . $event['work_order_id'] . ')');
            return [
                'status'   => 'failed',
                'event_id' => $eventId,
                'message'  => 'Bağlı iş emri bulunamadı.'
            ];
        }

        $quantity = (float)$event['quantity'];
        if ($quantity <= 0) {
            $this->markEventFailed($eventId, 'Geçersiz üretim miktarı: ' . $quantity);
            return [
                'status'   => 'failed',
                'event_id' => $eventId,
                'message'  => 'Üretim miktarı 0\'dan büyük olmalıdır.'
            ];
        }

        // 4. Depo ve Vardiya Ayarları (Options veya Varsayılanlar)
        // Varsayılan Kaynak Lokasyon: 1 (A Rafı - 01 - Ana Hammadde Deposu)
        // Varsayılan Hedef Lokasyon: 12 (Sevkiyat Alanı - 01 - Sevkiyat Deposu)
        $sourceLocationId = (int)($options['source_location_id'] ?? 1);
        $targetLocationId = (int)($options['target_location_id'] ?? 12);
        $shiftId = (int)($options['shift_id'] ?? 1);
        $userId = (int)($options['user_id'] ?? ($_SESSION['user_id'] ?? 1));

        $recipeId = (int)$workOrder['recipe_id'];
        $lineId = (int)$workOrder['production_line_id'];

        // 5. ProductionStockService için Veri Paketi
        $prodData = [
            'work_order_id'       => (int)$workOrder['id'],
            'work_order_no'       => $workOrder['work_order_no'],
            'event_id'            => $eventId,
            'line_id'             => $lineId,
            'shift_id'            => $shiftId,
            'recipe_id'           => $recipeId,
            'source_location_id'  => $sourceLocationId,
            'target_location_id'  => $targetLocationId,
            'panels_produced_qty' => (int)$quantity,
            'scrap_panels_qty'    => 0,
            'log_date'            => date('Y-m-d', strtotime($event['event_time'] ?? 'now')),
            'notes'               => sprintf('MES Olayı: %s | İş Emri: %s | Kaynak: %s', $eventId, $workOrder['work_order_no'], $event['source']),
            'total_wp_produced'   => $quantity * 550.0,
            'serial_no'           => $options['serial_no'] ?? null,
        ];

        // 6. Stok Hareketlerini Tek Transaction İle Gerçekleştir
        try {
            $stockResult = $this->stockService->executeProduction($prodData, $userId);
            $unitCost = (float)($stockResult['unit_cost'] ?? 0.0);
            $totalCost = (float)($stockResult['total_cost'] ?? 0.0);

            // Başarılı: Event'i PROCESSED, stock_movement_ref ve maliyet snapshot'ı ile güncelle
            $stmtUp = $this->pdo->prepare("
                UPDATE mes_production_events 
                SET status = 'PROCESSED', stock_movement_ref = ?, unit_cost = ?, total_cost = ?, cost_currency = 'TL', processed_at = NOW() 
                WHERE id = ?
            ");
            $stmtUp->execute([$stockResult['reference_no'], $unitCost, $totalCost, (int)$event['id']]);

            // İş Emri Durumunu Güncelle (Eğer hedef miktara ulaşıldıysa COMPLETED yap)
            $newProduced = (float)$workOrder['produced_quantity'];
            $planned = (float)$workOrder['planned_quantity'];
            if ($newProduced >= $planned && $workOrder['status'] === 'RUNNING') {
                $this->mesModel->updateWorkOrderStatus((int)$workOrder['id'], 'COMPLETED');
            }

            // mes_event_logs'a Başarı Kaydı Düş
            $successMsg = sprintf(
                'Stok ve maliyet entegrasyonu tamamlandı: +%.0f adet mamul girişi (Ref: %s, Maliyet: %.2f TL, %d hammadde kalemi tüketildi)',
                $quantity,
                $stockResult['reference_no'],
                $totalCost,
                count($stockResult['consumed_movements'] ?? [])
            );
            $this->mesModel->logEvent($eventId, 'STOCK_INTEGRATION_SUCCESS', $successMsg);

            return [
                'status'             => 'success',
                'event_id'           => $eventId,
                'quantity'           => $quantity,
                'reference_no'       => $stockResult['reference_no'],
                'work_order_no'      => $workOrder['work_order_no'],
                'unit_cost'          => $unitCost,
                'total_cost'         => $totalCost,
                'currency'           => 'TL',
                'consumed_movements' => $stockResult['consumed_movements'] ?? [],
                'produced_movement'  => $stockResult['produced_movement'] ?? [],
                'message'            => sprintf('✓ %.0f panel üretildi, mamul stoğuna eklendi, maliyet snapshot kaydedildi ve BOM hammadde çıkışı tamamlandı.', $quantity)
            ];

        } catch (Throwable $e) {
            $errMsg = $e->getMessage();

            // Başarısız: Event'i FAILED olarak güncelle
            $this->markEventFailed($eventId, $errMsg);

            return [
                'status'   => 'failed',
                'event_id' => $eventId,
                'error'    => $errMsg,
                'message'  => 'Üretim işlenemedi: ' . $errMsg
            ];
        }
    }

    /**
     * Bekleyen tüm olayları sırayla işler.
     */
    public function processPendingEvents(int $limit = 50, array $options = []): array
    {
        $pending = $this->getPendingEvents($limit);
        $summary = [
            'total'     => count($pending),
            'success'   => 0,
            'failed'    => 0,
            'results'   => []
        ];

        foreach ($pending as $ev) {
            $res = $this->processEvent($ev['id'], $options);
            $summary['results'][] = $res;
            if ($res['status'] === 'success') {
                $summary['success']++;
            } else {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    /**
     * Olayı FAILED olarak işaretler ve log kütüğüne yazar.
     */
    private function markEventFailed(string $eventId, string $errorMessage): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE mes_production_events 
            SET status = 'FAILED' 
            WHERE event_id = ?
        ");
        $stmt->execute([$eventId]);

        $this->mesModel->logEvent($eventId, 'STOCK_INTEGRATION_FAILED', 'Hata: ' . $errorMessage);
    }
}

