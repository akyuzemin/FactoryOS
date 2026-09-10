<?php

require_once __DIR__ . '/AuditService.php';

class DowntimeManagementService
{
    private PDO $pdo;
    private AuditService $auditService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditService = new AuditService($pdo);
    }

    /**
     * Hat için yeni duruş başlatır.
     * Transaction + SELECT FOR UPDATE ile aynı hatta çift OPEN duruş engellenir (409 Conflict).
     */
    public function startDowntime(
        int $lineId,
        int $reasonId,
        ?string $note = null,
        ?int $workOrderId = null,
        ?int $userId = null
    ): array {
        if ($lineId <= 0 || $reasonId <= 0) {
            return [
                'success'     => false,
                'code'        => 'INVALID_PARAMETERS',
                'status_code' => 400,
                'message'     => 'Geçersiz hat veya duruş nedeni kimliği.'
            ];
        }

        try {
            $this->pdo->beginTransaction();

            // 1. Hat kontrolü ve satır kilitleme
            $stmtLine = $this->pdo->prepare("SELECT id, code, name, status FROM production_lines WHERE id = :id FOR UPDATE");
            $stmtLine->execute([':id' => $lineId]);
            $line = $stmtLine->fetch(PDO::FETCH_ASSOC);

            if (!$line) {
                $this->pdo->rollBack();
                return [
                    'success'     => false,
                    'code'        => 'LINE_NOT_FOUND',
                    'status_code' => 404,
                    'message'     => "Üretim hattı bulunamadı (ID: {$lineId})."
                ];
            }

            // 2. Açık duruş kontrolü (Concurrency / Race Condition Koruması)
            $stmtCheck = $this->pdo->prepare("
                SELECT id, reason_id, started_at 
                FROM line_downtimes 
                WHERE line_id = :line_id AND status = 'OPEN' 
                FOR UPDATE
            ");
            $stmtCheck->execute([':line_id' => $lineId]);
            $openDowntime = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($openDowntime) {
                $this->pdo->rollBack();
                return [
                    'success'      => false,
                    'code'         => 'LINE_ALREADY_DOWN',
                    'status_code'  => 409,
                    'downtime_id'  => (int)$openDowntime['id'],
                    'started_at'   => $openDowntime['started_at'],
                    'message'      => "Bu hat ({$line['name']}) için zaten aktif bir duruş kaydı mevcuttur. Önce mevcut duruşu kapatınız."
                ];
            }

            // 3. Duruş nedeni kontrolü
            $stmtReason = $this->pdo->prepare("SELECT id, code, name, category, is_planned FROM downtime_reasons WHERE id = :id AND is_active = 1");
            $stmtReason->execute([':id' => $reasonId]);
            $reason = $stmtReason->fetch(PDO::FETCH_ASSOC);

            if (!$reason) {
                $this->pdo->rollBack();
                return [
                    'success'     => false,
                    'code'        => 'REASON_NOT_FOUND',
                    'status_code' => 404,
                    'message'     => 'Geçerli veya aktif bir duruş nedeni bulunamadı.'
                ];
            }

            $isPlanned = (int)$reason['is_planned'];
            $startedAt = date('Y-m-d H:i:s');

            // 4. line_downtimes tablosuna OPEN kaydı ekle
            $stmtIns = $this->pdo->prepare("
                INSERT INTO line_downtimes 
                    (line_id, reason_id, work_order_id, started_at, is_planned, status, operator_note, created_by, created_at)
                VALUES 
                    (:line_id, :reason_id, :work_order_id, :started_at, :is_planned, 'OPEN', :operator_note, :created_by, NOW())
            ");
            $stmtIns->execute([
                ':line_id'       => $lineId,
                ':reason_id'     => $reasonId,
                ':work_order_id' => $workOrderId ?: null,
                ':started_at'    => $startedAt,
                ':is_planned'    => $isPlanned,
                ':operator_note' => $note,
                ':created_by'    => $userId ?: null
            ]);
            $downtimeId = (int)$this->pdo->lastInsertId();

            // 5. production_lines durumunu güncelle
            $newLineStatus = ($isPlanned === 1) ? 'MAINTENANCE' : 'FAULT';
            $statusNote = sprintf('Duruş: %s (%s)', $reason['name'], $reason['code']);

            $stmtUpLine = $this->pdo->prepare("
                UPDATE production_lines 
                SET status = :status, status_note = :status_note, status_updated_at = NOW() 
                WHERE id = :id
            ");
            $stmtUpLine->execute([
                ':status'      => $newLineStatus,
                ':status_note' => $statusNote,
                ':id'          => $lineId
            ]);

            $this->pdo->commit();

            // 6. Audit Kaydı
            $this->auditService->log(
                'LINE_DOWNTIME_STARTED',
                'oee',
                'line_downtimes',
                $downtimeId,
                sprintf('%s duruşu başlatıldı (%s)', $line['name'], $reason['name']),
                null,
                [
                    'line_id'     => $lineId,
                    'line_code'   => $line['code'],
                    'reason_code' => $reason['code'],
                    'is_planned'  => $isPlanned,
                    'started_at'  => $startedAt,
                    'note'        => $note
                ],
                $userId
            );

            return [
                'success'     => true,
                'code'        => 'DOWNTIME_STARTED',
                'status_code' => 201,
                'downtime_id' => $downtimeId,
                'line_id'     => $lineId,
                'line_code'   => $line['code'],
                'reason_name' => $reason['name'],
                'is_planned'  => (bool)$isPlanned,
                'started_at'  => $startedAt,
                'message'     => "{$line['name']} için '{$reason['name']}' duruşu başarıyla başlatıldı."
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return [
                'success'     => false,
                'code'        => 'DATABASE_ERROR',
                'status_code' => 500,
                'message'     => 'Duruş başlatılırken veritabanı hatası oluştu: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Aktif duruşu sonlandırır ve süreyi saniye bazında hesaplar.
     */
    public function endDowntime(int $downtimeId, ?string $note = null, ?string $endedAt = null, ?int $userId = null): array
    {
        if ($downtimeId <= 0) {
            return [
                'success'     => false,
                'code'        => 'INVALID_DOWNTIME_ID',
                'status_code' => 400,
                'message'     => 'Geçersiz duruş kaydı ID.'
            ];
        }

        try {
            $this->pdo->beginTransaction();

            $stmtDt = $this->pdo->prepare("
                SELECT ld.*, pl.code AS line_code, pl.name AS line_name, dr.code AS reason_code, dr.name AS reason_name
                FROM line_downtimes ld
                JOIN production_lines pl ON ld.line_id = pl.id
                JOIN downtime_reasons dr ON ld.reason_id = dr.id
                WHERE ld.id = :id 
                FOR UPDATE
            ");
            $stmtDt->execute([':id' => $downtimeId]);
            $dt = $stmtDt->fetch(PDO::FETCH_ASSOC);

            if (!$dt) {
                $this->pdo->rollBack();
                return [
                    'success'     => false,
                    'code'        => 'DOWNTIME_NOT_FOUND',
                    'status_code' => 404,
                    'message'     => 'Duruş kaydı bulunamadı.'
                ];
            }

            if ($dt['status'] === 'CLOSED') {
                $this->pdo->rollBack();
                return [
                    'success'     => false,
                    'code'        => 'DOWNTIME_ALREADY_CLOSED',
                    'status_code' => 400,
                    'message'     => 'Bu duruş kaydı daha önce kapatılmış.'
                ];
            }

            $finishTime = $endedAt ? date('Y-m-d H:i:s', strtotime($endedAt)) : date('Y-m-d H:i:s');
            $startTs = strtotime($dt['started_at']);
            $endTs = strtotime($finishTime);
            $durationSeconds = max(0, $endTs - $startTs);
            $durationMinutes = round($durationSeconds / 60, 1);

            $finalNote = $dt['operator_note'];
            if (!empty($note)) {
                $finalNote = $finalNote ? ($finalNote . ' | Kapanış: ' . $note) : $note;
            }

            // 1. line_downtimes kaydını güncelle
            $stmtUpDt = $this->pdo->prepare("
                UPDATE line_downtimes 
                SET status = 'CLOSED', ended_at = :ended_at, duration_seconds = :duration_seconds, operator_note = :note, updated_at = NOW() 
                WHERE id = :id
            ");
            $stmtUpDt->execute([
                ':ended_at'          => $finishTime,
                ':duration_seconds'  => $durationSeconds,
                ':note'              => $finalNote,
                ':id'                => $downtimeId
            ]);

            // 2. Hat durumunu geri al (Aktif iş emri varsa RUNNING, yoksa IDLE)
            $lineId = (int)$dt['line_id'];
            $stmtActiveWo = $this->pdo->prepare("SELECT id FROM mes_work_orders WHERE production_line_id = :line_id AND status = 'RUNNING' LIMIT 1");
            $stmtActiveWo->execute([':line_id' => $lineId]);
            $hasActiveWo = (bool)$stmtActiveWo->fetchColumn();

            $newLineStatus = $hasActiveWo ? 'RUNNING' : 'IDLE';
            $stmtUpLine = $this->pdo->prepare("
                UPDATE production_lines 
                SET status = :status, status_note = 'Duruş tamamlandı', status_updated_at = NOW() 
                WHERE id = :id
            ");
            $stmtUpLine->execute([
                ':status' => $newLineStatus,
                ':id'     => $lineId
            ]);

            $this->pdo->commit();

            // 3. Audit Kaydı
            $this->auditService->log(
                'LINE_DOWNTIME_ENDED',
                'oee',
                'line_downtimes',
                $downtimeId,
                sprintf('%s duruşu sonlandırıldı (Süre: %s dk)', $dt['line_name'], $durationMinutes),
                ['status' => 'OPEN'],
                [
                    'status'           => 'CLOSED',
                    'line_id'          => $lineId,
                    'line_code'        => $dt['line_code'],
                    'duration_seconds' => $durationSeconds,
                    'duration_minutes' => $durationMinutes,
                    'ended_at'         => $finishTime
                ],
                $userId
            );

            return [
                'success'          => true,
                'code'             => 'DOWNTIME_CLOSED',
                'status_code'      => 200,
                'downtime_id'      => $downtimeId,
                'line_id'          => $lineId,
                'line_code'        => $dt['line_code'],
                'line_name'        => $dt['line_name'],
                'duration_seconds' => $durationSeconds,
                'duration_minutes' => $durationMinutes,
                'ended_at'         => $finishTime,
                'message'          => "{$dt['line_name']} duruşu tamamlandı (Süre: {$durationMinutes} dk)."
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return [
                'success'     => false,
                'code'        => 'DATABASE_ERROR',
                'status_code' => 500,
                'message'     => 'Duruş kapatılırken veritabanı hatası oluştu: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Hat üzerindeki aktif (OPEN) duruş kaydını döner.
     */
    public function getOpenDowntime(int $lineId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                ld.*,
                pl.name AS line_name,
                pl.code AS line_code,
                dr.code AS reason_code,
                dr.name AS reason_name,
                dr.category AS reason_category,
                dr.color_hex,
                TIMESTAMPDIFF(SECOND, ld.started_at, NOW()) AS current_duration_seconds
            FROM line_downtimes ld
            JOIN production_lines pl ON ld.line_id = pl.id
            JOIN downtime_reasons dr ON ld.reason_id = dr.id
            WHERE ld.line_id = :line_id AND ld.status = 'OPEN'
            LIMIT 1
        ");
        $stmt->execute([':line_id' => $lineId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Tüm aktif (OPEN) duruş kayıtlarını döner.
     */
    public function getAllOpenDowntimes(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                ld.*,
                pl.name AS line_name,
                pl.code AS line_code,
                dr.code AS reason_code,
                dr.name AS reason_name,
                dr.category AS reason_category,
                dr.color_hex,
                TIMESTAMPDIFF(SECOND, ld.started_at, NOW()) AS current_duration_seconds
            FROM line_downtimes ld
            JOIN production_lines pl ON ld.line_id = pl.id
            JOIN downtime_reasons dr ON ld.reason_id = dr.id
            WHERE ld.status = 'OPEN'
            ORDER BY ld.started_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Aktif duruş nedenlerini döner.
     */
    public function getReasons(bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM downtime_reasons";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY is_planned ASC, category ASC, id ASC";

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Geçmiş ve mevcut duruşları listeler.
     */
    public function getDowntimes(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $sql = "
            SELECT 
                ld.*,
                pl.name AS line_name,
                pl.code AS line_code,
                dr.code AS reason_code,
                dr.name AS reason_name,
                dr.category AS reason_category,
                dr.color_hex,
                wo.work_order_no
            FROM line_downtimes ld
            JOIN production_lines pl ON ld.line_id = pl.id
            JOIN downtime_reasons dr ON ld.reason_id = dr.id
            LEFT JOIN mes_work_orders wo ON ld.work_order_id = wo.id
            WHERE 1=1
        ";

        $params = [];
        if (!empty($filters['line_id'])) {
            $sql .= " AND ld.line_id = :line_id";
            $params[':line_id'] = (int)$filters['line_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND ld.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND ld.started_at >= :date_from";
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND ld.started_at <= :date_to";
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $sql .= " ORDER BY ld.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

