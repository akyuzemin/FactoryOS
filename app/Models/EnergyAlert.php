<?php

class EnergyAlert
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * En son okuma / alarm tarihini döner.
     */
    public function getLatestDate(): string
    {
        $stmt = $this->pdo->query("SELECT DATE(MAX(created_at)) FROM energy_alerts");
        $date = $stmt->fetchColumn();
        if (!$date) {
            $stmt = $this->pdo->query("SELECT DATE(MAX(read_at)) FROM energy_readings");
            $date = $stmt->fetchColumn();
        }
        return $date ?: date('Y-m-d');
    }

    /**
     * KPI Kartları için alarm özet istatistiklerini hesaplar.
     */
    public function getSummaryStats(): array
    {
        $latestDate = $this->getLatestDate();

        // 1. Açık Kritik Alarm (is_acknowledged = 0 AND severity = 'CRITICAL')
        $openCrit = (int)$this->pdo->query("
            SELECT COUNT(*) FROM energy_alerts WHERE is_acknowledged = 0 AND severity = 'CRITICAL'
        ")->fetchColumn();

        // 2. Açık Uyarı (is_acknowledged = 0 AND severity = 'WARNING')
        $openWarn = (int)$this->pdo->query("
            SELECT COUNT(*) FROM energy_alerts WHERE is_acknowledged = 0 AND severity = 'WARNING'
        ")->fetchColumn();

        // 3. Bugünkü Alarmlar (latestDate günündeki alarmlar)
        $stmtToday = $this->pdo->prepare("SELECT COUNT(*) FROM energy_alerts WHERE DATE(created_at) = :d");
        $stmtToday->execute([':d' => $latestDate]);
        $todayCount = (int)$stmtToday->fetchColumn();

        // 4. Son 7 Gün Alarmlar
        $stmt7d = $this->pdo->prepare("SELECT COUNT(*) FROM energy_alerts WHERE created_at >= DATE_SUB(:d, INTERVAL 6 DAY)");
        $stmt7d->execute([':d' => $latestDate]);
        $sevenDaysCount = (int)$stmt7d->fetchColumn();

        // 5. Çözülen Alarmlar (is_acknowledged = 2)
        $resolvedCount = (int)$this->pdo->query("
            SELECT COUNT(*) FROM energy_alerts WHERE is_acknowledged = 2
        ")->fetchColumn();

        // 6. Onaylanan Alarmlar (is_acknowledged = 1)
        $ackCount = (int)$this->pdo->query("
            SELECT COUNT(*) FROM energy_alerts WHERE is_acknowledged = 1
        ")->fetchColumn();

        // 7. En Çok Alarm Üreten Hat / Sayaç
        $topMeterStmt = $this->pdo->query("
            SELECT m.name, COUNT(*) as alert_count
            FROM energy_alerts a
            JOIN energy_meters m ON a.meter_id = m.id
            GROUP BY m.id, m.name
            ORDER BY alert_count DESC
            LIMIT 1
        ");
        $topMeter = $topMeterStmt->fetch(PDO::FETCH_ASSOC);

        return [
            'open_critical' => $openCrit,
            'open_warning' => $openWarn,
            'today_alerts' => $todayCount,
            'seven_days_alerts' => $sevenDaysCount,
            'resolved_alerts' => $resolvedCount,
            'acknowledged_alerts' => $ackCount,
            'top_meter_name' => $topMeter['name'] ?? 'Yok',
            'top_meter_count' => (int)($topMeter['alert_count'] ?? 0),
            'latest_date' => $latestDate
        ];
    }

    /**
     * Filtrelenmiş tüm alarmları listeler.
     */
    public function getAllAlerts(array $filters = []): array
    {
        $sql = "
            SELECT 
                a.id,
                a.meter_id,
                a.alert_type,
                a.severity,
                a.threshold_value,
                a.measured_value,
                a.message,
                a.is_acknowledged,
                a.acknowledged_by,
                a.acknowledged_at,
                a.created_at,
                m.code as meter_code,
                m.name as meter_name,
                z.name as zone_name,
                pl.name as line_name,
                u.username as ack_username
            FROM energy_alerts a
            JOIN energy_meters m ON a.meter_id = m.id
            LEFT JOIN energy_facility_zones z ON m.zone_id = z.id
            LEFT JOIN production_lines pl ON m.line_id = pl.id
            LEFT JOIN users u ON a.acknowledged_by = u.id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['severity'])) {
            $sql .= " AND a.severity = :severity";
            $params[':severity'] = $filters['severity'];
        }

        if (!empty($filters['alert_type'])) {
            $sql .= " AND a.alert_type = :alert_type";
            $params[':alert_type'] = $filters['alert_type'];
        }

        if (!empty($filters['meter_id'])) {
            $sql .= " AND a.meter_id = :meter_id";
            $params[':meter_id'] = (int)$filters['meter_id'];
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= " AND a.is_acknowledged = :status";
            $params[':status'] = (int)$filters['status'];
        }

        if (!empty($filters['date'])) {
            $sql .= " AND DATE(a.created_at) = :filter_date";
            $params[':filter_date'] = $filters['date'];
        }

        $sql .= " ORDER BY a.created_at DESC, a.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tekil alarm detayını getirir.
     */
    public function getAlertById(int $id): ?array
    {
        $sql = "
            SELECT 
                a.*,
                m.code as meter_code,
                m.name as meter_name,
                z.name as zone_name,
                pl.name as line_name,
                pl.nominal_power_kw,
                u.username as ack_username
            FROM energy_alerts a
            JOIN energy_meters m ON a.meter_id = m.id
            LEFT JOIN energy_facility_zones z ON m.zone_id = z.id
            LEFT JOIN production_lines pl ON m.line_id = pl.id
            LEFT JOIN users u ON a.acknowledged_by = u.id
            WHERE a.id = :id
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Alarmı Onaylar (is_acknowledged = 1).
     */
    public function acknowledgeAlert(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE energy_alerts 
            SET is_acknowledged = 1, acknowledged_by = :uid, acknowledged_at = NOW() 
            WHERE id = :id
        ");
        return $stmt->execute([':uid' => $userId, ':id' => $id]);
    }

    /**
     * Alarmı Çözüldü Olarak İşaretler (is_acknowledged = 2).
     */
    public function resolveAlert(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE energy_alerts 
            SET is_acknowledged = 2, acknowledged_by = :uid, acknowledged_at = NOW() 
            WHERE id = :id
        ");
        return $stmt->execute([':uid' => $userId, ':id' => $id]);
    }

    /**
     * Idempotent olarak yeni bir alarm kaydeder.
     * Aynı meter_id, alert_type ve aynı dakika/saatte kayıt varsa tekrar eklemez.
     */
    public function createAlertIfNotExist(array $data): ?int
    {
        $checkStmt = $this->pdo->prepare("
            SELECT id FROM energy_alerts 
            WHERE meter_id = :meter_id 
              AND alert_type = :alert_type 
              AND created_at = :created_at
            LIMIT 1
        ");
        $checkStmt->execute([
            ':meter_id' => $data['meter_id'],
            ':alert_type' => $data['alert_type'],
            ':created_at' => $data['created_at']
        ]);
        if ($checkStmt->fetchColumn()) {
            return null; // Zaten mevcut, mükerrer üretilmedi (Idempotent)
        }

        $insertStmt = $this->pdo->prepare("
            INSERT INTO energy_alerts 
            (meter_id, alert_type, severity, threshold_value, measured_value, message, is_acknowledged, created_at)
            VALUES 
            (:meter_id, :alert_type, :severity, :threshold_value, :measured_value, :message, 0, :created_at)
        ");

        $insertStmt->execute([
            ':meter_id' => $data['meter_id'],
            ':alert_type' => $data['alert_type'],
            ':severity' => $data['severity'],
            ':threshold_value' => $data['threshold_value'],
            ':measured_value' => $data['measured_value'],
            ':message' => $data['message'],
            ':created_at' => $data['created_at']
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Filtreleme için sayaç listesi.
     */
    public function getMeters(): array
    {
        return $this->pdo->query("SELECT id, code, name FROM energy_meters ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
}
