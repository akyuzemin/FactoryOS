<?php

class Mes
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get all work orders with material, recipe, and line details.
     */
    /**
     * Get all work orders with material, recipe, line, and simulation details.
     */
    public function getWorkOrders(?string $status = null, ?string $search = null): array
    {
        $sql = "
            SELECT 
                wo.*,
                m.code AS product_code,
                m.name AS product_name,
                r.code AS recipe_code,
                r.name AS recipe_name,
                pl.code AS line_code,
                pl.name AS line_name,
                s.is_active AS sim_is_active,
                s.last_status AS sim_last_status,
                s.interval_seconds AS sim_interval_seconds,
                s.next_run_at AS sim_next_run_at,
                s.last_error AS sim_last_error
            FROM mes_work_orders wo
            JOIN materials m ON wo.product_material_id = m.id
            JOIN recipes r ON wo.recipe_id = r.id
            JOIN production_lines pl ON wo.production_line_id = pl.id
            LEFT JOIN mes_simulations s ON s.work_order_id = wo.id
            WHERE 1=1
        ";

        $params = [];
        if ($status !== null && $status !== '' && $status !== 'all') {
            if ($status === 'RUNNING') {
                $sql .= " AND wo.status = 'RUNNING' AND s.is_active = 1";
            } elseif ($status === 'PAUSED') {
                $sql .= " AND (wo.status = 'PAUSED' OR (wo.status = 'RUNNING' AND (s.is_active = 0 OR s.is_active IS NULL)))";
            } elseif ($status === 'FAILED') {
                $sql .= " AND (wo.status = 'FAILED' OR (s.last_status = 'FAILED' AND (s.is_active = 0 OR s.is_active IS NULL)))";
            } else {
                $sql .= " AND wo.status = ?";
                $params[] = $status;
            }
        }

        if ($search !== null && $search !== '') {
            $sql .= " AND (wo.work_order_no LIKE ? OR m.name LIKE ? OR m.code LIKE ? OR pl.name LIKE ?)";
            $term = '%' . trim($search) . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY wo.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($orders as &$o) {
            $planned = (float)($o['planned_quantity'] ?? 0);
            $produced = (float)($o['produced_quantity'] ?? 0);
            $o['remaining_quantity'] = max(0, $planned - $produced);
            $o['progress_pct'] = $planned > 0 ? round(($produced / $planned) * 100, 1) : 0;
            $o['status_info'] = self::classifyStatus($o);
        }

        return $orders;
    }

    /**
     * Classify work order lifecycle status into standard badge & styling info.
     */
    public static function classifyStatus(array $wo): array
    {
        $status = strtoupper((string)($wo['status'] ?? 'PLANNED'));
        $simActive = !empty($wo['sim_is_active']) || !empty($wo['is_active']);
        $simLastStatus = strtoupper((string)($wo['sim_last_status'] ?? ($wo['last_status'] ?? '')));

        if ($status === 'COMPLETED') {
            return [
                'key'    => 'COMPLETED',
                'label'  => 'Tamamlandı',
                'badge'  => '⚫ Tamamlandı',
                'bg'     => '#f8fafc',
                'color'  => '#334155',
                'dot'    => '#64748b',
                'border' => '#cbd5e1'
            ];
        }

        if ($status === 'FAILED' || ($simLastStatus === 'FAILED' && !$simActive)) {
            return [
                'key'    => 'FAILED',
                'label'  => 'Hata / Bekleme',
                'badge'  => '🔴 Hata',
                'bg'     => '#fef2f2',
                'color'  => '#991b1b',
                'dot'    => '#ef4444',
                'border' => '#fecaca'
            ];
        }

        if ($status === 'RUNNING' && $simActive) {
            return [
                'key'    => 'RUNNING',
                'label'  => 'Üretimde',
                'badge'  => '🟢 Üretimde',
                'bg'     => '#f0fdf4',
                'color'  => '#166534',
                'dot'    => '#22c55e',
                'border' => '#bbf7d0'
            ];
        }

        if ($status === 'PAUSED' || ($status === 'RUNNING' && !$simActive)) {
            return [
                'key'    => 'PAUSED',
                'label'  => 'Duraklatıldı',
                'badge'  => '🟡 Duraklatıldı',
                'bg'     => '#fffbeb',
                'color'  => '#92400e',
                'dot'    => '#f59e0b',
                'border' => '#fde68a'
            ];
        }

        if ($status === 'READY') {
            return [
                'key'    => 'READY',
                'label'  => 'Hazır / Kuyrukta',
                'badge'  => '🟣 Hazır',
                'bg'     => '#faf5ff',
                'color'  => '#6b21a8',
                'dot'    => '#a855f7',
                'border' => '#e9d5ff'
            ];
        }

        if ($status === 'CANCELLED') {
            return [
                'key'    => 'CANCELLED',
                'label'  => 'İptal Edildi',
                'badge'  => '⚪ İptal',
                'bg'     => '#f1f5f9',
                'color'  => '#475569',
                'dot'    => '#94a3b8',
                'border' => '#cbd5e1'
            ];
        }

        return [
            'key'    => 'PLANNED',
            'label'  => 'Planlandı',
            'badge'  => '🔵 Planlandı',
            'bg'     => '#eff6ff',
            'color'  => '#1e40af',
            'dot'    => '#3b82f6',
            'border' => '#bfdbfe'
        ];
    }

    /**
     * Get work order statistics for KPIs.
     */
    public function getWorkOrderStats(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                COUNT(*) AS total_count,
                SUM(CASE WHEN wo.status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed_count,
                SUM(CASE WHEN wo.status = 'PLANNED' THEN 1 ELSE 0 END) AS planned_count,
                SUM(CASE WHEN wo.status = 'READY' THEN 1 ELSE 0 END) AS ready_count,
                SUM(CASE WHEN wo.status = 'RUNNING' AND s.is_active = 1 THEN 1 ELSE 0 END) AS running_count,
                SUM(CASE WHEN (wo.status = 'PAUSED' OR (wo.status = 'RUNNING' AND (s.is_active = 0 OR s.is_active IS NULL))) AND wo.status NOT IN ('COMPLETED', 'READY', 'PLANNED', 'CANCELLED', 'FAILED') THEN 1 ELSE 0 END) AS paused_count,
                SUM(CASE WHEN wo.status = 'FAILED' OR (s.last_status = 'FAILED' AND (s.is_active = 0 OR s.is_active IS NULL)) THEN 1 ELSE 0 END) AS failed_count,
                SUM(wo.planned_quantity) AS total_planned_qty,
                SUM(wo.produced_quantity) AS total_produced_qty
            FROM mes_work_orders wo
            LEFT JOIN mes_simulations s ON s.work_order_id = wo.id
        ");
        $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $totalEvents = (int)$this->pdo->query("SELECT COUNT(*) FROM mes_production_events")->fetchColumn();
        $stats['total_events'] = $totalEvents;
        $stats['total_remaining_qty'] = max(0, (float)($stats['total_planned_qty'] ?? 0) - (float)($stats['total_produced_qty'] ?? 0));

        return $stats;
    }

    /**
     * Get a single work order by ID with calculated metrics.
     */
    public function getWorkOrderById(int $id): ?array
    {
        $sql = "
            SELECT 
                wo.*,
                m.code AS product_code,
                m.name AS product_name,
                r.code AS recipe_code,
                r.name AS recipe_name,
                pl.code AS line_code,
                pl.name AS line_name
            FROM mes_work_orders wo
            JOIN materials m ON wo.product_material_id = m.id
            JOIN recipes r ON wo.recipe_id = r.id
            JOIN production_lines pl ON wo.production_line_id = pl.id
            WHERE wo.id = ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            $planned = (float)($order['planned_quantity'] ?? 0);
            $produced = (float)($order['produced_quantity'] ?? 0);
            $order['remaining_quantity'] = max(0, $planned - $produced);
            $order['progress_pct'] = $planned > 0 ? round(($produced / $planned) * 100, 1) : 0;
        }

        return $order ?: null;
    }

    /**
     * Get a work order by its unique number.
     */
    public function getWorkOrderByNo(string $no): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id FROM mes_work_orders WHERE work_order_no = ?");
        $stmt->execute([$no]);
        $id = $stmt->fetchColumn();
        return $id ? $this->getWorkOrderById((int)$id) : null;
    }

    /**
     * Create a new work order.
     */
    public function createWorkOrder(array $data): int
    {
        $sql = "
            INSERT INTO mes_work_orders (
                work_order_no, product_material_id, recipe_id, production_line_id,
                planned_quantity, produced_quantity, status, planned_start_at, planned_end_at
            ) VALUES (
                ?, ?, ?, ?,
                ?, 0.00, ?, ?, ?
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['work_order_no'],
            $data['product_material_id'],
            $data['recipe_id'],
            $data['production_line_id'],
            $data['planned_quantity'],
            $data['status'] ?? 'PLANNED',
            $data['planned_start_at'] ?? null,
            $data['planned_end_at'] ?? null
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update work order status.
     */
    public function updateWorkOrderStatus(int $id, string $status): bool
    {
        $allowed = ['PLANNED', 'READY', 'RUNNING', 'PAUSED', 'FAILED', 'COMPLETED', 'CANCELLED'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $stmt = $this->pdo->prepare("UPDATE mes_work_orders SET status = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    /**
     * Create a MES Production Event with duplicate check and work order quantity increment.
     */
    public function createProductionEvent(array $eventData): array
    {
        $eventId = trim($eventData['event_id'] ?? '');
        if ($eventId === '') {
            $eventId = 'EVT-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        }

        // 1. Duplicate check (Idempotency)
        $stmtCheck = $this->pdo->prepare("SELECT * FROM mes_production_events WHERE event_id = ?");
        $stmtCheck->execute([$eventId]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $this->logEvent($eventId, 'DUPLICATE_IGNORED', 'Event ' . $eventId . ' already exists. Duplicate ignored.');
            return [
                'status'    => 'duplicate',
                'event_id'  => $eventId,
                'event'     => $existing,
                'message'   => 'Bu olay (Event ID: ' . $eventId . ') daha önce işlenmiş. Tekrar kaydedilmedi.'
            ];
        }

        $workOrderId = (int)($eventData['work_order_id'] ?? 0);
        $quantity = (float)($eventData['quantity'] ?? 1.00);
        $source = $eventData['source'] ?? 'SIMULATOR';
        $eventType = $eventData['event_type'] ?? 'PANEL_COMPLETED';
        $eventTime = $eventData['event_time'] ?? date('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            // Fetch and lock work order
            $stmtWo = $this->pdo->prepare("SELECT * FROM mes_work_orders WHERE id = ? FOR UPDATE");
            $stmtWo->execute([$workOrderId]);
            $wo = $stmtWo->fetch(PDO::FETCH_ASSOC);

            if (!$wo) {
                $this->pdo->rollBack();
                return [
                    'status'  => 'error',
                    'message' => 'Geçersiz İş Emri ID: ' . $workOrderId
                ];
            }

            $plannedQty = (float)$wo['planned_quantity'];
            $currentProduced = (float)$wo['produced_quantity'];

            if ($currentProduced >= $plannedQty || $wo['status'] === 'COMPLETED') {
                $this->pdo->rollBack();
                return [
                    'status'  => 'error',
                    'message' => 'İş emri hedef miktarına ulaştığı için yeni olay kabul edilmez.'
                ];
            }

            $productMaterialId = (int)$wo['product_material_id'];
            $productionLineId = (int)$wo['production_line_id'];

            // Insert into mes_production_events
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
                $productionLineId,
                $quantity,
                $eventType,
                $eventTime,
                $source
            ]);

            // Update produced_quantity in mes_work_orders
            $newProduced = $currentProduced + $quantity;
            $newStatus = ($newProduced >= $plannedQty) ? 'COMPLETED' : ($wo['status'] === 'PLANNED' ? 'RUNNING' : $wo['status']);

            $stmtUp = $this->pdo->prepare("
                UPDATE mes_work_orders 
                SET produced_quantity = ?, status = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmtUp->execute([$newProduced, $newStatus, $workOrderId]);

            // Write log
            $this->logEvent(
                $eventId, 
                'EVENT_CREATED', 
                sprintf('Üretim olayı kaydedildi: +%.0f adet (İş Emri: %s, Hat: %d, Kaynak: %s)', $quantity, $wo['work_order_no'], $productionLineId, $source)
            );

            $this->pdo->commit();

            return [
                'status'            => 'success',
                'event_id'          => $eventId,
                'quantity'          => $quantity,
                'produced_quantity' => $newProduced,
                'planned_quantity'  => $plannedQty,
                'remaining_quantity'=> max(0, $plannedQty - $newProduced),
                'progress_pct'      => $plannedQty > 0 ? round(($newProduced / $plannedQty) * 100, 1) : 0,
                'work_order_status' => $newStatus,
                'work_order_no'     => $wo['work_order_no'],
                'message'           => sprintf('+%.0f Panel üretimi başarıyla kaydedildi.', $quantity)
            ];

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return [
                'status'  => 'error',
                'message' => 'MES olay kaydı hatası: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Log an action to mes_event_logs.
     */
    public function logEvent(string $eventId, string $action, string $message): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO mes_event_logs (event_id, action, message, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$eventId, $action, $message]);
    }

    /**
     * Get recent production events.
     */
    public function getRecentEvents(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                e.*,
                wo.work_order_no,
                m.code AS product_code,
                m.name AS product_name,
                pl.code AS line_code,
                pl.name AS line_name
            FROM mes_production_events e
            JOIN mes_work_orders wo ON e.work_order_id = wo.id
            JOIN materials m ON e.product_material_id = m.id
            JOIN production_lines pl ON e.production_line_id = pl.id
            ORDER BY e.id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get events for a specific work order.
     */
    public function getEventsByWorkOrder(int $workOrderId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                e.*,
                pl.code AS line_code,
                pl.name AS line_name
            FROM mes_production_events e
            JOIN production_lines pl ON e.production_line_id = pl.id
            WHERE e.work_order_id = ?
            ORDER BY e.id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $workOrderId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get event logs by event_id.
     */
    public function getEventLogs(string $eventId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM mes_event_logs WHERE event_id = ? ORDER BY id ASC");
        $stmt->execute([$eventId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Aktif üretim hatlarını basit liste olarak döner.
     */
    public function getActiveProductionLines(): array
    {
        $stmt = $this->pdo->prepare("SELECT id, code, name FROM production_lines WHERE is_active = 1 ORDER BY id ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all active production lines with their real-time operational status and active work order.
     */
    public function getProductionLinesWithStatus(): array
    {
        $stmtLines = $this->pdo->query("
            SELECT pl.* 
            FROM production_lines pl 
            WHERE pl.is_active = 1 
            ORDER BY pl.id ASC
        ");
        $lines = $stmtLines ? $stmtLines->fetchAll(PDO::FETCH_ASSOC) : [];

        // Fetch active running work orders
        $stmtActiveWo = $this->pdo->query("
            SELECT 
                wo.id AS work_order_id,
                wo.work_order_no,
                wo.production_line_id,
                wo.planned_quantity,
                wo.produced_quantity,
                wo.status AS wo_status,
                m.name AS product_name,
                m.code AS product_code,
                s.interval_seconds,
                s.next_run_at,
                s.is_active AS sim_is_active
            FROM mes_work_orders wo
            JOIN materials m ON wo.product_material_id = m.id
            JOIN mes_simulations s ON s.work_order_id = wo.id
            WHERE wo.status = 'RUNNING' AND s.is_active = 1
            ORDER BY wo.id DESC
        ");
        $activeWorkOrders = $stmtActiveWo ? $stmtActiveWo->fetchAll(PDO::FETCH_ASSOC) : [];
        $activeByLine = [];
        foreach ($activeWorkOrders as $awo) {
            $lid = (int)$awo['production_line_id'];
            if (!isset($activeByLine[$lid])) {
                $activeByLine[$lid] = $awo;
            }
        }

        foreach ($lines as &$line) {
            $lineId = (int)$line['id'];
            $explicitStatus = strtoupper($line['status'] ?? 'IDLE');
            $activeWo = $activeByLine[$lineId] ?? null;

            if ($explicitStatus === 'MAINTENANCE') {
                $line['effective_status'] = 'MAINTENANCE';
                $line['status_label'] = 'BAKIMDA';
                $line['status_badge'] = '🟡 BAKIMDA';
                $line['badge_bg'] = '#fffbeb';
                $line['badge_color'] = '#92400e';
                $line['badge_border'] = '#fde68a';
                $line['status_desc'] = $line['status_note'] ?: 'Planlı bakım devam ediyor';
                $line['active_work_order'] = null;
            } elseif ($explicitStatus === 'FAULT') {
                $line['effective_status'] = 'FAULT';
                $line['status_label'] = 'ARIZADA';
                $line['status_badge'] = '🔴 ARIZADA';
                $line['badge_bg'] = '#fef2f2';
                $line['badge_color'] = '#991b1b';
                $line['badge_border'] = '#fecaca';
                $line['status_desc'] = $line['status_note'] ?: 'Arıza nedeniyle üretim durduruldu';
                $line['active_work_order'] = null;
            } elseif ($activeWo) {
                $planned = (float)$activeWo['planned_quantity'];
                $produced = (float)$activeWo['produced_quantity'];
                $rem = max(0, $planned - $produced);
                $pct = $planned > 0 ? round(($produced / $planned) * 100, 1) : 0;
                $speedSec = (int)($activeWo['interval_seconds'] ?: 180);
                $remSec = (int)round($rem * $speedSec);
                $estEnd = date('H:i', time() + $remSec);

                $line['effective_status'] = 'RUNNING';
                $line['status_label'] = 'ÇALIŞIYOR';
                $line['status_badge'] = '🟢 ÇALIŞIYOR';
                $line['badge_bg'] = '#f0fdf4';
                $line['badge_color'] = '#166534';
                $line['badge_border'] = '#bbf7d0';
                $line['status_desc'] = $activeWo['product_name'];
                $line['active_work_order'] = [
                    'id'                => (int)$activeWo['work_order_id'],
                    'work_order_no'     => $activeWo['work_order_no'],
                    'product_name'      => $activeWo['product_name'],
                    'product_code'      => $activeWo['product_code'],
                    'planned_quantity'  => $planned,
                    'produced_quantity' => $produced,
                    'progress_pct'      => $pct,
                    'estimated_end'     => $estEnd,
                    'estimated_human'   => sprintf('Tahmini bitiş: %s', $estEnd)
                ];
            } else {
                $line['effective_status'] = 'IDLE';
                $line['status_label'] = 'BOŞ';
                $line['status_badge'] = '⚪ BOŞ';
                $line['badge_bg'] = '#f8fafc';
                $line['badge_color'] = '#475569';
                $line['badge_border'] = '#e2e8f0';
                $line['status_desc'] = 'Üretim bekliyor';
                $line['active_work_order'] = null;
            }
        }

        return $lines;
    }

    /**
     * Check if a work order can start on a given production line.
     */
    public function canStartWorkOrderOnLine(int $lineId, int $workOrderId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM production_lines WHERE id = ?");
        $stmt->execute([$lineId]);
        $line = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$line || empty($line['is_active'])) {
            return ['allowed' => false, 'code' => 'LINE_NOT_FOUND', 'reason' => 'Üretim hattı bulunamadı veya pasif.'];
        }

        $lineStatus = strtoupper($line['status'] ?? 'IDLE');
        if ($lineStatus === 'MAINTENANCE') {
            $note = !empty($line['status_note']) ? " ({$line['status_note']})" : '';
            return [
                'allowed' => false,
                'code'    => 'LINE_MAINTENANCE',
                'reason'  => "Bu üretim hattı ({$line['name']}) bakımda olduğu için üretim başlatılamaz{$note}."
            ];
        }

        if ($lineStatus === 'FAULT') {
            $note = !empty($line['status_note']) ? " ({$line['status_note']})" : '';
            return [
                'allowed' => false,
                'code'    => 'LINE_FAULT',
                'reason'  => "Bu üretim hattı ({$line['name']}) arızalı olduğu için üretim başlatılamaz{$note}."
            ];
        }

        // Check if ANOTHER work order is currently RUNNING on this line
        $stmtRunning = $this->pdo->prepare("
            SELECT wo.id, wo.work_order_no 
            FROM mes_work_orders wo
            JOIN mes_simulations s ON s.work_order_id = wo.id
            WHERE wo.production_line_id = ? 
              AND wo.id != ?
              AND wo.status = 'RUNNING' 
              AND s.is_active = 1
            LIMIT 1
        ");
        $stmtRunning->execute([$lineId, $workOrderId]);
        $otherActiveWo = $stmtRunning->fetch(PDO::FETCH_ASSOC);

        if ($otherActiveWo) {
            return [
                'allowed'             => false,
                'code'                => 'LINE_BUSY',
                'other_work_order_id' => (int)$otherActiveWo['id'],
                'other_work_order_no' => $otherActiveWo['work_order_no'],
                'reason'              => "{$line['name']} üzerinde zaten aktif bir üretim ({$otherActiveWo['work_order_no']}) bulunuyor."
            ];
        }

        return ['allowed' => true, 'code' => 'LINE_AVAILABLE', 'reason' => 'Hat üretime uygun.'];
    }

    /**
     * Update production line status (e.g. MAINTENANCE, FAULT, IDLE).
     */
    public function updateProductionLineStatus(int $lineId, string $status, ?string $note = null): bool
    {
        $allowed = ['IDLE', 'RUNNING', 'MAINTENANCE', 'FAULT'];
        $st = strtoupper(trim($status));
        if (!in_array($st, $allowed, true)) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            UPDATE production_lines 
            SET status = ?, status_note = ?, status_updated_at = NOW(), updated_at = NOW() 
            WHERE id = ?
        ");
        return $stmt->execute([$st, $note, $lineId]);
    }
}
