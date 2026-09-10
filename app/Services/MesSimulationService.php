<?php

require_once __DIR__ . '/MesEventFormatterService.php';
require_once __DIR__ . '/MesEventIngestionService.php';
require_once __DIR__ . '/MesBomConsumptionService.php';
require_once __DIR__ . '/../Models/Mes.php';

class MesSimulationService
{
    private PDO $pdo;
    private Mes $mesModel;
    private MesEventIngestionService $ingestionService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->mesModel = new Mes($this->pdo);
        $this->ingestionService = new MesEventIngestionService($this->pdo);
    }

    /**
     * Get or create simulation state for a work order.
     */
    public function getOrCreateSimulation(int $workOrderId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM mes_simulations WHERE work_order_id = ?");
        $stmt->execute([$workOrderId]);
        $sim = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sim) {
            $stmtIns = $this->pdo->prepare("
                INSERT INTO mes_simulations (work_order_id, is_active, interval_seconds, last_status, created_at, updated_at)
                VALUES (?, 0, 180, 'IDLE', NOW(), NOW())
            ");
            $stmtIns->execute([$workOrderId]);

            $stmt->execute([$workOrderId]);
            $sim = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $now = time();
        $nextRun = !empty($sim['next_run_at']) ? strtotime($sim['next_run_at']) : null;
        if ((int)$sim['is_active'] === 1 && $nextRun && $nextRun > $now) {
            $sim['remaining_seconds'] = $nextRun - $now;
        } elseif ((int)$sim['is_active'] === 0 && !empty($sim['paused_remaining_seconds'])) {
            $sim['remaining_seconds'] = (int)$sim['paused_remaining_seconds'];
        } else {
            $sim['remaining_seconds'] = (int)$sim['interval_seconds'];
        }

        return $sim;
    }

    /**
     * Start / Resume simulation with given interval in seconds and optional target quantity.
     */
    public function startSimulation(int $workOrderId, ?int $intervalSeconds = null, ?float $targetQuantity = null): array
    {
        $this->pdo->beginTransaction();
        try {
            $stmtWo = $this->pdo->prepare("SELECT * FROM mes_work_orders WHERE id = ? FOR UPDATE");
            $stmtWo->execute([$workOrderId]);
            $wo = $stmtWo->fetch(PDO::FETCH_ASSOC);

            if (!$wo) {
                $this->pdo->rollBack();
                return ['success' => false, 'status' => 'ERROR', 'message' => 'İş emri bulunamadı.'];
            }

            if ($targetQuantity !== null && $targetQuantity > 0 && (float)$wo['produced_quantity'] < $targetQuantity) {
                $stmtTarget = $this->pdo->prepare("UPDATE mes_work_orders SET planned_quantity = ?, updated_at = NOW() WHERE id = ?");
                $stmtTarget->execute([$targetQuantity, $workOrderId]);
                $wo['planned_quantity'] = $targetQuantity;
            }

            $plannedQty = (float)$wo['planned_quantity'];
            $producedQty = (float)$wo['produced_quantity'];

            if ($producedQty >= $plannedQty || $wo['status'] === 'COMPLETED') {
                $this->pdo->prepare("UPDATE mes_work_orders SET status = 'COMPLETED', updated_at = NOW() WHERE id = ?")->execute([$workOrderId]);
                $this->pdo->prepare("UPDATE mes_simulations SET is_active = 0, last_status = 'COMPLETED', updated_at = NOW() WHERE work_order_id = ?")->execute([$workOrderId]);
                $this->pdo->commit();
                return ['success' => false, 'status' => 'COMPLETED', 'message' => 'Bu iş emri hedef miktarına ulaştığı için tamamlanmış (COMPLETED).'];
            }

            $stmtSim = $this->pdo->prepare("SELECT * FROM mes_simulations WHERE work_order_id = ? FOR UPDATE");
            $stmtSim->execute([$workOrderId]);
            $sim = $stmtSim->fetch(PDO::FETCH_ASSOC);

            if (!$sim) {
                $this->pdo->prepare("
                    INSERT INTO mes_simulations (work_order_id, is_active, interval_seconds, last_status, created_at, updated_at)
                    VALUES (?, 0, 180, 'IDLE', NOW(), NOW())
                ")->execute([$workOrderId]);
                $stmtSim->execute([$workOrderId]);
                $sim = $stmtSim->fetch(PDO::FETCH_ASSOC);
            }

            // Idempotency: If already running and active with valid countdown, return immediately without resetting timer
            if ((int)$sim['is_active'] === 1 && $wo['status'] === 'RUNNING' && !empty($sim['next_run_at']) && strtotime($sim['next_run_at']) > time()) {
                $this->pdo->commit();
                return array_merge(['success' => true, 'status' => 'RUNNING', 'message' => 'İş emri zaten çalışıyor.'], $this->getStatus($workOrderId));
            }

            $lineCheck = $this->mesModel->canStartWorkOrderOnLine((int)$wo['production_line_id'], $workOrderId);
            if (!$lineCheck['allowed']) {
                if ($lineCheck['code'] === 'LINE_BUSY') {
                    $otherWoId = (int)($lineCheck['other_work_order_id'] ?? 0);
                    if ($otherWoId > 0) {
                        $this->pdo->prepare("
                            UPDATE mes_simulations SET is_active = 0, last_status = 'PAUSED', updated_at = NOW()
                            WHERE work_order_id = ?
                        ")->execute([$otherWoId]);
                        $this->pdo->prepare("
                            UPDATE mes_work_orders SET status = 'PAUSED', updated_at = NOW()
                            WHERE id = ?
                        ")->execute([$otherWoId]);
                    } else {
                        $this->pdo->prepare("
                            UPDATE mes_simulations SET is_active = 0, last_status = 'PAUSED', updated_at = NOW()
                            WHERE work_order_id IN (SELECT id FROM mes_work_orders WHERE production_line_id = ? AND id != ?)
                        ")->execute([(int)$wo['production_line_id'], $workOrderId]);
                        $this->pdo->prepare("
                            UPDATE mes_work_orders SET status = 'PAUSED', updated_at = NOW()
                            WHERE production_line_id = ? AND id != ? AND status = 'RUNNING'
                        ")->execute([(int)$wo['production_line_id'], $workOrderId]);
                    }
                } else {
                    $this->pdo->rollBack();
                    return [
                        'success'   => false,
                        'status'    => 'LINE_NOT_AVAILABLE',
                        'code'      => $lineCheck['code'],
                        'message'   => $lineCheck['reason'],
                        'telemetry' => $this->getStatus($workOrderId)
                    ];
                }
            }

            $intervalSeconds = ($intervalSeconds !== null && $intervalSeconds > 0) ? $intervalSeconds : (int)($sim['interval_seconds'] ?: 180);

            // Kalan süreyi koruma mantığı:
            // Eğer daha önce duraklatılmış ve kalan bir süre varsa (örn: 47 sn), o süreden devam etsin.
            $useSeconds = $intervalSeconds;
            if (!empty($sim['paused_remaining_seconds']) && (int)$sim['paused_remaining_seconds'] > 0 && (int)$sim['paused_remaining_seconds'] <= $intervalSeconds) {
                $useSeconds = (int)$sim['paused_remaining_seconds'];
            }

            $now = date('Y-m-d H:i:s');
            $nextRunAt = date('Y-m-d H:i:s', time() + $useSeconds);

            $stmt = $this->pdo->prepare("
                UPDATE mes_simulations 
                SET is_active = 1, interval_seconds = ?, last_run_at = ?, next_run_at = ?, paused_remaining_seconds = NULL,
                    last_status = 'RUNNING', last_error = NULL, updated_at = NOW()
                WHERE work_order_id = ?
            ");
            $stmt->execute([$intervalSeconds, $now, $nextRunAt, $workOrderId]);

            if (in_array($wo['status'], ['PLANNED', 'READY', 'PAUSED'], true)) {
                $this->pdo->prepare("UPDATE mes_work_orders SET status = 'RUNNING', planned_start_at = COALESCE(planned_start_at, NOW()), updated_at = NOW() WHERE id = ?")->execute([$workOrderId]);
            }

            $this->pdo->commit();

            $this->mesModel->logEvent(
                'SIM-CTRL-' . $workOrderId,
                'SIMULATION_STARTED',
                sprintf('İş Emri %s için canlı üretim simülasyonu başlatıldı. Hız: %d sn/panel (Kalan süre: %d sn).', $wo['work_order_no'], $intervalSeconds, $useSeconds)
            );

            return array_merge(['success' => true, 'status' => 'RUNNING', 'message' => 'Üretim başarıyla başlatıldı.'], $this->getStatus($workOrderId));

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'status' => 'ERROR', 'message' => 'Başlatma hatası: ' . $e->getMessage()];
        }
    }

    /**
     * Pause simulation and preserve remaining seconds.
     */
    public function pauseSimulation(int $workOrderId, ?string $reason = null, bool $clearError = true): array
    {
        $this->pdo->beginTransaction();
        try {
            $stmtWo = $this->pdo->prepare("SELECT * FROM mes_work_orders WHERE id = ? FOR UPDATE");
            $stmtWo->execute([$workOrderId]);
            $wo = $stmtWo->fetch(PDO::FETCH_ASSOC);

            if (!$wo) {
                $this->pdo->rollBack();
                return ['success' => false, 'status' => 'ERROR', 'message' => 'İş emri bulunamadı.'];
            }

            $stmtSim = $this->pdo->prepare("SELECT * FROM mes_simulations WHERE work_order_id = ? FOR UPDATE");
            $stmtSim->execute([$workOrderId]);
            $sim = $stmtSim->fetch(PDO::FETCH_ASSOC);

            if (!$sim) {
                $this->pdo->prepare("
                    INSERT INTO mes_simulations (work_order_id, is_active, interval_seconds, last_status, created_at, updated_at)
                    VALUES (?, 0, 180, 'PAUSED', NOW(), NOW())
                ")->execute([$workOrderId]);
                $stmtSim->execute([$workOrderId]);
                $sim = $stmtSim->fetch(PDO::FETCH_ASSOC);
            }

            // Idempotency: If already paused, return immediately
            if ((int)$sim['is_active'] === 0 && $wo['status'] === 'PAUSED') {
                $this->pdo->commit();
                return array_merge(['success' => true, 'status' => 'PAUSED', 'message' => 'İş emri zaten duraklatılmış.'], $this->getStatus($workOrderId));
            }

            $now = time();
            $nextRun = !empty($sim['next_run_at']) ? strtotime($sim['next_run_at']) : null;
            
            // Duraklatıldığında kalan süreyi hesapla ve sakla
            $pausedRemaining = ($nextRun && $nextRun > $now) ? ($nextRun - $now) : (int)$sim['interval_seconds'];
            $lastError = $clearError ? null : ($sim['last_error'] ?? null);

            $stmt = $this->pdo->prepare("
                UPDATE mes_simulations 
                SET is_active = 0, last_status = 'PAUSED', paused_remaining_seconds = ?, last_error = ?, retry_count = 0, next_retry_at = NULL, updated_at = NOW()
                WHERE work_order_id = ?
            ");
            $stmt->execute([$pausedRemaining, $lastError, $workOrderId]);

            if ($wo['status'] === 'RUNNING') {
                $this->pdo->prepare("UPDATE mes_work_orders SET status = 'PAUSED', updated_at = NOW() WHERE id = ?")->execute([$workOrderId]);
            }

            $this->pdo->commit();

            $this->mesModel->logEvent(
                'SIM-CTRL-' . $workOrderId,
                'SIMULATION_PAUSED',
                sprintf('İş Emri %s için simülasyon duraklatıldı. Kalan süre korundu: %d sn. Neden: %s', $wo['work_order_no'], $pausedRemaining, $reason ?: 'Kullanıcı duraklattı')
            );

            return array_merge(['success' => true, 'status' => 'PAUSED', 'message' => 'Üretim duraklatıldı.'], $this->getStatus($workOrderId));

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'status' => 'ERROR', 'message' => 'Duraklatma hatası: ' . $e->getMessage()];
        }
    }

    /**
     * Process a simulation tick for a work order.
     */
    public function processTick(int $workOrderId, bool $forceStep = false): array
    {
        $this->getOrCreateSimulation($workOrderId);

        $this->pdo->beginTransaction();

        try {
            // Lock simulation row
            $stmtSim = $this->pdo->prepare("SELECT * FROM mes_simulations WHERE work_order_id = ? FOR UPDATE");
            $stmtSim->execute([$workOrderId]);
            $sim = $stmtSim->fetch(PDO::FETCH_ASSOC);

            // Lock and fetch work order
            $stmtWo = $this->pdo->prepare("
                SELECT 
                    wo.*,
                    m.code AS product_code,
                    m.name AS product_name,
                    pl.code AS line_code,
                    pl.name AS line_name
                FROM mes_work_orders wo
                JOIN materials m ON wo.product_material_id = m.id
                JOIN production_lines pl ON wo.production_line_id = pl.id
                WHERE wo.id = ? 
                FOR UPDATE
            ");
            $stmtWo->execute([$workOrderId]);
            $wo = $stmtWo->fetch(PDO::FETCH_ASSOC);

            if (!$wo) {
                $this->pdo->rollBack();
                return ['success' => false, 'status' => 'ERROR', 'message' => 'İş emri bulunamadı.'];
            }

            $plannedQty = (float)$wo['planned_quantity'];
            $producedQty = (float)$wo['produced_quantity'];

            // 1. Check if already completed
            if ($producedQty >= $plannedQty || $wo['status'] === 'COMPLETED') {
                $this->pdo->prepare("
                    UPDATE mes_simulations 
                    SET is_active = 0, last_status = 'COMPLETED', paused_remaining_seconds = NULL, updated_at = NOW() 
                    WHERE work_order_id = ?
                ")->execute([$workOrderId]);

                if ($wo['status'] !== 'COMPLETED') {
                    $this->pdo->prepare("UPDATE mes_work_orders SET status = 'COMPLETED', updated_at = NOW() WHERE id = ?")->execute([$workOrderId]);
                }

                $this->pdo->commit();
                return [
                    'success'   => true,
                    'status'    => 'COMPLETED',
                    'action'    => 'STOPPED',
                    'message'   => 'İş emri hedef miktarına (%100) ulaştı. Simülasyon tamamlandı.',
                    'telemetry' => $this->getStatus($workOrderId)
                ];
            }

            // 2. Check if simulation is active or timer is due
            $now = time();
            $intervalSeconds = (int)($sim['interval_seconds'] ?: 180);
            $nextRun = !empty($sim['next_run_at']) ? strtotime($sim['next_run_at']) : 0;

            if (!$forceStep) {
                if ((int)$sim['is_active'] === 0) {
                    $this->pdo->commit();
                    return [
                        'success'   => true,
                        'status'    => 'PAUSED',
                        'is_active' => false,
                        'action'    => 'IDLE',
                        'telemetry' => $this->getStatus($workOrderId)
                    ];
                }

                if ($nextRun > $now) {
                    $this->pdo->commit();
                    return [
                        'success'           => true,
                        'status'            => 'WAITING',
                        'is_active'         => true,
                        'action'            => 'COUNTDOWN',
                        'remaining_seconds' => $nextRun - $now,
                        'telemetry'         => $this->getStatus($workOrderId)
                    ];
                }
            }

            // 3. Time is due or step forced -> Check line operational status
            $lineCheck = $this->mesModel->canStartWorkOrderOnLine((int)$wo['production_line_id'], $workOrderId);
            if (!$lineCheck['allowed'] && in_array($lineCheck['code'], ['LINE_MAINTENANCE', 'LINE_FAULT'], true)) {
                $this->pdo->prepare("
                    UPDATE mes_simulations 
                    SET is_active = 0, last_status = 'PAUSED', last_error = ?, updated_at = NOW() 
                    WHERE work_order_id = ?
                ")->execute([$lineCheck['reason'], $workOrderId]);
                $this->mesModel->updateWorkOrderStatus($workOrderId, 'PAUSED');
                $this->pdo->commit();

                return [
                    'success'   => false,
                    'status'    => 'LINE_NOT_AVAILABLE',
                    'code'      => $lineCheck['code'],
                    'message'   => $lineCheck['reason'],
                    'telemetry' => $this->getStatus($workOrderId)
                ];
            }

            // Ingest 1 Panel Event
            $panelSeqNum = (int)$producedQty + 1;
            $lineClean = preg_replace('/[^A-Za-z0-9]/', '', $wo['line_code'] ?: 'LAM1');
            $eventId = sprintf('MES-%s-%s-%06d-%s', date('Ymd'), $lineClean, $panelSeqNum, strtoupper(bin2hex(random_bytes(2))));

            $payload = [
                'event_id'            => $eventId,
                'event_type'          => 'PANEL_COMPLETED',
                'product_code'        => $wo['product_code'],
                'product_material_id' => (int)$wo['product_material_id'],
                'quantity'            => 1.0,
                'production_line'     => $wo['line_code'],
                'production_line_id'  => (int)$wo['production_line_id'],
                'work_order_no'       => $wo['work_order_no'],
                'work_order_id'       => (int)$wo['id'],
                'event_time'          => date('Y-m-d H:i:s'),
                'source'              => 'SIMULATOR'
            ];

            // Commit transaction before calling ingestion service
            $this->pdo->commit();

            // 4. Ingest Event via standard ingestion pipeline
            $ingestResult = $this->ingestionService->ingestEvent($payload);

            // 5. Update Simulation State based on ingestion result
            if (!empty($ingestResult['success']) && $ingestResult['status'] === 'PROCESSED') {
                $freshWo = $this->mesModel->getWorkOrderById($workOrderId);
                $newProduced = (float)($freshWo['produced_quantity'] ?? ($producedQty + 1.0));
                $isCompleted = ($newProduced >= $plannedQty || ($freshWo['status'] ?? '') === 'COMPLETED');
                
                $newNextRun = $isCompleted ? null : date('Y-m-d H:i:s', time() + $intervalSeconds);
                $newSimStatus = $isCompleted ? 'COMPLETED' : 'PROCESSED';
                $newSimActive = $isCompleted ? 0 : (int)$sim['is_active'];

                $stmtUpSim = $this->pdo->prepare("
                    UPDATE mes_simulations 
                    SET is_active = ?, last_run_at = NOW(), next_run_at = ?, last_event_id = ?, 
                        last_status = ?, last_error = NULL, last_error_code = NULL,
                        retry_count = 0, last_retry_at = NULL, next_retry_at = NULL,
                        paused_remaining_seconds = NULL,
                        total_simulated_panels = total_simulated_panels + 1,
                        updated_at = NOW()
                    WHERE work_order_id = ?
                ");
                $stmtUpSim->execute([$newSimActive, $newNextRun, $eventId, $newSimStatus, $workOrderId]);

                if ($isCompleted) {
                    $this->mesModel->updateWorkOrderStatus($workOrderId, 'COMPLETED');
                }

                $statusData = $this->getStatus($workOrderId);

                return [
                    'success'            => true,
                    'status'             => 'PROCESSED',
                    'action'             => 'PANEL_PRODUCED',
                    'event_id'           => $eventId,
                    'panel_number'       => $panelSeqNum,
                    'stock_reference'    => $ingestResult['stock_reference'] ?? null,
                    'consumed_materials' => $ingestResult['consumed_materials'] ?? 9,
                    'produced_quantity'  => $newProduced,
                    'planned_quantity'   => $plannedQty,
                    'remaining_quantity' => max(0, $plannedQty - $newProduced),
                    'progress_pct'       => $plannedQty > 0 ? round(($newProduced / $plannedQty) * 100, 1) : 0,
                    'message'            => sprintf('✓ PANEL #%d TAMAMLANDI (Event: %s, Ref: %s)', $panelSeqNum, $eventId, $ingestResult['stock_reference'] ?? ''),
                    'telemetry'          => $statusData
                ];

            } else {
                // Ingestion Failed -> Classify error and decide action
                $errMsg = $ingestResult['message'] ?? 'Bilinmeyen entegrasyon hatası';
                $errCode = $ingestResult['code'] ?? ($ingestResult['status'] ?? 'FAILED');
                $errorClass = self::classifyError($errCode, $errMsg);

                // 1. FATAL / STOCK HALT: Duraklatılması gereken kritik durumlar
                if ($errorClass === 'STOCK_HALT' || $errCode === 'INSUFFICIENT_STOCK') {
                    $stmtFail = $this->pdo->prepare("
                        UPDATE mes_simulations 
                        SET is_active = 0,
                            last_status = 'PAUSED',
                            last_error_code = ?,
                            last_error = ?,
                            retry_count = 0,
                            next_retry_at = NULL,
                            updated_at = NOW()
                        WHERE work_order_id = ?
                    ");
                    $stmtFail->execute([$errCode, $errMsg, $workOrderId]);

                    $this->mesModel->updateWorkOrderStatus($workOrderId, 'PAUSED');

                    $this->mesModel->logEvent(
                        $eventId,
                        'SIMULATION_HALTED_' . $errCode,
                        sprintf('Hammadde yetersizliği nedeniyle simülasyon duraklatıldı: %s', $errMsg)
                    );

                    return [
                        'success'    => false,
                        'status'     => 'PAUSED',
                        'action'     => 'PAUSED_ON_STOCK_HALT',
                        'event_id'   => $eventId,
                        'error_code' => $errCode,
                        'message'    => 'Hammadde yetersizliği nedeniyle simülasyon duraklatıldı: ' . $errMsg,
                        'error'      => $ingestResult['error'] ?? null,
                        'telemetry'  => $this->getStatus($workOrderId)
                    ];
                }

                if ($errorClass === 'PERMANENT') {
                    $stmtFail = $this->pdo->prepare("
                        UPDATE mes_simulations 
                        SET is_active = 0,
                            last_status = 'FAILED',
                            last_error_code = ?,
                            last_error = ?,
                            retry_count = 0,
                            next_retry_at = NULL,
                            updated_at = NOW()
                        WHERE work_order_id = ?
                    ");
                    $stmtFail->execute([$errCode, $errMsg, $workOrderId]);

                    $this->mesModel->updateWorkOrderStatus($workOrderId, 'FAILED');

                    $this->mesModel->logEvent(
                        $eventId,
                        'SIMULATION_HALTED_' . $errCode,
                        sprintf('Kritik sistem hatası nedeniyle simülasyon durduruldu: %s', $errMsg)
                    );

                    return [
                        'success'    => false,
                        'status'     => 'FAILED',
                        'action'     => 'PAUSED_ON_ERROR',
                        'event_id'   => $eventId,
                        'error_code' => $errCode,
                        'message'    => 'Kritik hata: ' . $errMsg,
                        'error'      => $ingestResult['error'] ?? null,
                        'telemetry'  => $this->getStatus($workOrderId)
                    ];
                }

                // 2. RECOVERABLE PANEL HATASI:
                // Tek panelde hata oluşması tüm iş emrini durdurmaz!
                // Timer bir sonraki çevrime ilerletilir, simülasyon RUNNING kalır.
                $nextRunAt = date('Y-m-d H:i:s', time() + $intervalSeconds);

                $stmtContinue = $this->pdo->prepare("
                    UPDATE mes_simulations 
                    SET is_active = 1,
                        last_run_at = NOW(),
                        next_run_at = ?,
                        last_event_id = ?,
                        last_status = 'RUNNING',
                        last_error_code = ?,
                        last_error = ?,
                        retry_count = 0,
                        next_retry_at = NULL,
                        paused_remaining_seconds = NULL,
                        updated_at = NOW()
                    WHERE work_order_id = ?
                ");
                $stmtContinue->execute([$nextRunAt, $eventId, $errCode, $errMsg, $workOrderId]);

                $this->mesModel->logEvent(
                    $eventId,
                    'PANEL_RECOVERABLE_ERROR',
                    sprintf('Panel #%d üretiminde hata oluştu (%s). Simülasyon devam ediyor, sonraki çevrim: %s', $panelSeqNum, $errMsg, date('H:i:s', strtotime($nextRunAt)))
                );

                return [
                    'success'           => false,
                    'status'            => 'RUNNING',
                    'action'            => 'PANEL_FAILED_CONTINUING',
                    'event_id'          => $eventId,
                    'panel_number'      => $panelSeqNum,
                    'error_code'        => $errCode,
                    'message'           => sprintf('Panel #%d üretiminde hata oluştu (%s). Simülasyon kesintisiz devam ediyor.', $panelSeqNum, $errMsg),
                    'telemetry'         => $this->getStatus($workOrderId)
                ];
            }

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $errMsg = $e->getMessage();
            
            // Eğer veritabanı bağlantısı kopmadıysa simülasyonu devam ettir
            $intervalSeconds = (int)($sim['interval_seconds'] ?? 180);
            if ($intervalSeconds <= 0) $intervalSeconds = 180;
            $nextRunAt = date('Y-m-d H:i:s', time() + $intervalSeconds);

            try {
                $this->pdo->prepare("
                    UPDATE mes_simulations 
                    SET is_active = 1, next_run_at = ?, last_status = 'RUNNING', last_error = ?, updated_at = NOW()
                    WHERE work_order_id = ?
                ")->execute([$nextRunAt, $errMsg, $workOrderId]);
            } catch (Throwable $ignore) {}

            return [
                'success'    => false,
                'status'     => 'RUNNING',
                'action'     => 'PANEL_FAILED_CONTINUING',
                'error_code' => 'TRANSIENT_SYSTEM_ERROR',
                'message'    => 'Panel üretiminde hata oluştu: ' . $errMsg . '. Simülasyon devam ediyor.',
                'telemetry'  => $this->getStatus($workOrderId)
            ];
        }
    }

    /**
     * Classify an error into RETRYABLE, PERMANENT, or STOCK_HALT.
     */
    public static function classifyError(string $errorCode, string $errorMessage): string
    {
        $code = strtoupper(trim($errorCode));
        $msg = mb_strtolower($errorMessage, 'UTF-8');

        if ($code === 'INSUFFICIENT_STOCK' || str_contains($msg, 'yetersiz') || str_contains($msg, 'insufficient')) {
            return 'STOCK_HALT';
        }

        $permanentCodes = [
            'WORK_ORDER_NOT_FOUND',
            'TARGET_REACHED',
            'PLANNED_QUANTITY_EXCEEDED',
            'WORK_ORDER_COMPLETED',
            'COMPLETED',
            'CANCELLED',
            'LINE_MAINTENANCE',
            'LINE_FAULT',
            'LINE_NOT_AVAILABLE'
        ];
        if (in_array($code, $permanentCodes, true)) {
            return 'PERMANENT';
        }

        $retryableCodes = [
            'TEMPORARY_DB_ERROR',
            'TRANSIENT_SYSTEM_ERROR',
            'LOCK_TIMEOUT',
            'DEADLOCK',
            'CONNECTION_ERROR',
            'SERVICE_UNAVAILABLE'
        ];
        if (in_array($code, $retryableCodes, true)) {
            return 'RETRYABLE';
        }

        if (
            str_contains($msg, 'lock wait timeout') ||
            str_contains($msg, 'deadlock found') ||
            str_contains($msg, 'connection lost') ||
            str_contains($msg, 'server has gone away') ||
            str_contains($msg, 'transient') ||
            str_contains($msg, 'temporary') ||
            str_contains($msg, 'retry')
        ) {
            return 'RETRYABLE';
        }

        return 'RECOVERABLE';
    }

    /**
     * Is this error eligible for retry?
     */
    public static function isRetryableError(string $errorCode, string $errorMessage): bool
    {
        return self::classifyError($errorCode, $errorMessage) === 'RETRYABLE';
    }

    /**
     * Format duration seconds to human-readable string (e.g. '5 sa 15 dk', '45 dk', '30 sn').
     */
    public static function formatDurationHuman(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0 sn';
        }
        if ($seconds < 60) {
            return $seconds . ' sn';
        }
        if ($seconds < 3600) {
            $m = floor($seconds / 60);
            $s = $seconds % 60;
            return $s > 0 ? sprintf('%d dk %d sn', $m, $s) : sprintf('%d dk', $m);
        }
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        return $m > 0 ? sprintf('%d sa %d dk', $h, $m) : sprintf('%d saat', $h);
    }

    /**
     * Format interval speed to human-readable string (e.g. '3 dk / panel', '5 sn / panel').
     */
    public static function formatSpeedHuman(int $intervalSeconds): string
    {
        if ($intervalSeconds <= 0) {
            return '180 sn / panel';
        }
        if ($intervalSeconds < 60) {
            return $intervalSeconds . ' sn / panel';
        }
        $m = round($intervalSeconds / 60, 1);
        return ($m == (int)$m ? (int)$m : $m) . ' dk / panel';
    }

    /**
     * Format estimated completion timestamp to human-friendly display (e.g. 'Bugün 21:45', 'Yarın 04:30').
     */
    public static function formatCompletionTimeHuman(?string $dateTimeStr): string
    {
        if (!$dateTimeStr) {
            return '-';
        }
        $ts = strtotime($dateTimeStr);
        if (!$ts) {
            return '-';
        }

        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $datePart = date('Y-m-d', $ts);
        $timePart = date('H:i', $ts);
        if ($datePart === $today) {
            return 'Bugün ' . $timePart;
        } elseif ($datePart === $tomorrow) {
            return 'Yarın ' . $timePart;
        } else {
            return date('d.m.Y H:i', $ts);
        }
    }

    /**
     * Get complete live status & telemetry for UI display.
     */
    public function getStatus(int $workOrderId): array
    {
        $wo = $this->mesModel->getWorkOrderById($workOrderId);
        if (!$wo) {
            return [];
        }

        $sim = $this->getOrCreateSimulation($workOrderId);
        $events = $this->mesModel->getEventsByWorkOrder($workOrderId, 25);

        $now = time();
        $nextRun = !empty($sim['next_run_at']) ? strtotime($sim['next_run_at']) : null;
        $intervalSec = (int)($sim['interval_seconds'] ?: 180);
        
        if ((int)$sim['is_active'] === 1) {
            if ($nextRun !== null) {
                $remainingSeconds = max(0, $nextRun - $now);
            } else {
                $remainingSeconds = 0;
            }
        } elseif ((int)$sim['is_active'] === 0 && !empty($sim['paused_remaining_seconds'])) {
            $remainingSeconds = (int)$sim['paused_remaining_seconds'];
        } else {
            $remainingSeconds = $intervalSec;
        }

        $planned = (float)$wo['planned_quantity'];
        $produced = (float)$wo['produced_quantity'];
        $remaining = max(0, $planned - $produced);
        $progressPct = $planned > 0 ? round(($produced / $planned) * 100, 1) : 0;

        $totalEstimatedSec = (int)round($planned * $intervalSec);
        $totalEstimatedHuman = self::formatDurationHuman($totalEstimatedSec);
        $speedHuman = self::formatSpeedHuman($intervalSec);

        $isCompleted = ($produced >= $planned || $wo['status'] === 'COMPLETED');

        if ($isCompleted) {
            $remainingEstSec = 0;
            $remainingEstHuman = '0 dk (Tamamlandı)';
            $estCompletionAt = null;
            $estCompletionHuman = 'Tamamlandı';
        } elseif ((bool)$sim['is_active']) {
            $remainingPanelsAfterNext = max(0, $remaining - 1);
            $remainingEstSec = $remainingSeconds + (int)round($remainingPanelsAfterNext * $intervalSec);
            $remainingEstHuman = self::formatDurationHuman($remainingEstSec);
            $estCompletionAt = date('Y-m-d H:i:s', time() + $remainingEstSec);
            $estCompletionHuman = self::formatCompletionTimeHuman($estCompletionAt);
        } else {
            $firstPanelSec = (!empty($sim['paused_remaining_seconds']) && (int)$sim['paused_remaining_seconds'] > 0)
                ? (int)$sim['paused_remaining_seconds']
                : $intervalSec;
            $remainingPanelsAfterNext = max(0, $remaining - 1);
            $remainingEstSec = $firstPanelSec + (int)round($remainingPanelsAfterNext * $intervalSec);
            $remainingEstHuman = self::formatDurationHuman($remainingEstSec);
            $estCompletionAt = date('Y-m-d H:i:s', time() + $remainingEstSec);
            $estCompletionHuman = sprintf('(Duraklatıldı) ~%s', self::formatDurationHuman($remainingEstSec));
        }

        // Enhance events with Panel sequence labels & human friendly representation
        foreach ($events as $idx => &$ev) {
            $ev['panel_label'] = 'PANEL #' . ((int)$ev['quantity'] > 1 ? (int)$ev['quantity'] . ' ADET' : (count($events) - $idx));
        }

        $bomService = new MesBomConsumptionService($this->pdo);
        $bomConsumption = $bomService->getWorkOrderBomConsumption($workOrderId);

        $formattedEvents = MesEventFormatterService::formatEventList($events, $produced);
        $latestAction = MesEventFormatterService::getLatestActionSummary($formattedEvents, $sim['last_error'] ?? null);

        return [
            'work_order' => [
                'id'                => (int)$wo['id'],
                'work_order_no'     => $wo['work_order_no'],
                'product_name'      => $wo['product_name'],
                'product_code'      => $wo['product_code'],
                'line_name'         => $wo['line_name'],
                'line_code'         => $wo['line_code'],
                'recipe_name'       => $wo['recipe_name'] ?? '',
                'planned_quantity'  => $planned,
                'produced_quantity' => $produced,
                'remaining_quantity'=> $remaining,
                'progress_pct'      => $progressPct,
                'status'            => $wo['status']
            ],
            'simulation' => [
                'is_active'                  => (bool)$sim['is_active'],
                'interval_seconds'           => $intervalSec,
                'interval_human'             => $speedHuman,
                'total_estimated_seconds'    => $totalEstimatedSec,
                'total_estimated_human'      => $totalEstimatedHuman,
                'remaining_estimated_seconds'=> $remainingEstSec,
                'remaining_estimated_human'  => $remainingEstHuman,
                'estimated_completion_at'    => $estCompletionAt,
                'estimated_completion_human' => $estCompletionHuman,
                'paused_remaining_seconds'   => !empty($sim['paused_remaining_seconds']) ? (int)$sim['paused_remaining_seconds'] : null,
                'last_run_at'                => $sim['last_run_at'],
                'next_run_at'                => $sim['next_run_at'],
                'remaining_seconds'          => $remainingSeconds,
                'last_event_id'              => $sim['last_event_id'],
                'last_status'                => $sim['last_status'],
                'last_error'                 => $sim['last_error'],
                'last_error_code'            => $sim['last_error_code'] ?? null,
                'retry_count'                => (int)($sim['retry_count'] ?? 0),
                'last_retry_at'              => $sim['last_retry_at'] ?? null,
                'next_retry_at'              => $sim['next_retry_at'] ?? null,
                'is_retrying'                => ((int)($sim['retry_count'] ?? 0) > 0 && !empty($sim['next_retry_at'])),
                'total_simulated_panels'     => (int)$sim['total_simulated_panels']
            ],
            'recent_events'          => $events,
            'recent_events_human'    => $formattedEvents,
            'latest_action_summary'  => $latestAction,
            'bom_consumption'        => $bomConsumption
        ];
    }

    /**
     * Run all active simulations that are due for execution (Worker / Cron heartbeat).
     */
    public function runDueSimulations(int $maxBatches = 10, ?int $targetWorkOrderId = null): array
    {
        $sql = "
            SELECT s.work_order_id, s.interval_seconds, s.next_run_at, wo.work_order_no
            FROM mes_simulations s
            JOIN mes_work_orders wo ON s.work_order_id = wo.id
            JOIN production_lines pl ON wo.production_line_id = pl.id
            WHERE s.is_active = 1 
              AND wo.status = 'RUNNING'
              AND (pl.status IS NULL OR pl.status NOT IN ('MAINTENANCE', 'FAULT'))
              AND (s.next_run_at IS NULL OR s.next_run_at <= NOW())
        ";
        if ($targetWorkOrderId !== null && $targetWorkOrderId > 0) {
            $sql .= " AND s.work_order_id = " . (int)$targetWorkOrderId;
        }
        $sql .= " ORDER BY s.next_run_at ASC LIMIT " . (int)$maxBatches;

        $stmt = $this->pdo->query($sql);
        $due = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $results = [];
        $processedCount = 0;

        foreach ($due as $row) {
            $woId = (int)$row['work_order_id'];
            $res = $this->processTick($woId, false);
            $results[] = [
                'work_order_id' => $woId,
                'work_order_no' => $row['work_order_no'],
                'result'        => $res
            ];
            if (!empty($res['success']) && ($res['action'] ?? '') === 'PANEL_PRODUCED') {
                $processedCount++;
            }
        }

        return [
            'due_count'       => count($due),
            'processed_count' => $processedCount,
            'results'         => $results
        ];
    }
}