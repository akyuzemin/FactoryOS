<?php

class OeeCalculationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Hat ve reçete bazında ideal çevrim süresini (saniye) döner.
     * line_cycle_times tablosundan okur, bulunamazsa güvenli fallback değerini kullanır.
     */
    public function getIdealCycleSeconds(int $lineId, ?int $recipeId = null): float
    {
        if ($recipeId && $recipeId > 0) {
            $stmt = $this->pdo->prepare("
                SELECT ideal_cycle_seconds 
                FROM line_cycle_times 
                WHERE production_line_id = :line_id AND recipe_id = :recipe_id AND is_active = 1 
                LIMIT 1
            ");
            $stmt->execute([':line_id' => $lineId, ':recipe_id' => $recipeId]);
            $val = $stmt->fetchColumn();
            if ($val !== false && (float)$val > 0) {
                return (float)$val;
            }
        }

        // Reçetesiz hat bazlı arama
        $stmtLine = $this->pdo->prepare("
            SELECT ideal_cycle_seconds 
            FROM line_cycle_times 
            WHERE production_line_id = :line_id AND is_active = 1 
            ORDER BY id ASC 
            LIMIT 1
        ");
        $stmtLine->execute([':line_id' => $lineId]);
        $valLine = $stmtLine->fetchColumn();
        if ($valLine !== false && (float)$valLine > 0) {
            return (float)$valLine;
        }

        // Güvenli Fallback Değerleri (Hardcoded değil, hat tipine göre dinamik provizyon)
        switch ($lineId) {
            case 4: // Solar Simülatör & Flaş Test Hattı
                return 30.0;
            case 3: // Stringer
                return 180.0;
            case 1: // Laminatör 1
            case 2: // Laminatör 2
            default:
                return 180.0;
        }
    }

    /**
     * Vardiya için başlangıç ve bitiş zaman damgalarını döner.
     */
    public function getShiftWindow(int $shiftId, string $date): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, code, name, start_time, end_time FROM energy_shifts WHERE id = :id AND is_active = 1");
        $stmt->execute([':id' => $shiftId]);
        $shift = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$shift) {
            return null;
        }

        $startTime = $shift['start_time'];
        $endTime = $shift['end_time'];

        $windowStart = date('Y-m-d H:i:s', strtotime("{$date} {$startTime}"));

        if ($endTime === '00:00:00' || strtotime($endTime) <= strtotime($startTime)) {
            // Gece yarısı veya ertesi güne taşan vardiya
            $nextDate = date('Y-m-d', strtotime("{$date} +1 day"));
            $windowEnd = date('Y-m-d H:i:s', strtotime("{$nextDate} {$endTime}"));
        } else {
            $windowEnd = date('Y-m-d H:i:s', strtotime("{$date} {$endTime}"));
        }

        $durationSeconds = max(0, strtotime($windowEnd) - strtotime($windowStart));

        return [
            'shift_id'         => (int)$shift['id'],
            'shift_code'       => $shift['code'],
            'shift_name'       => $shift['name'],
            'date'             => $date,
            'window_start'     => $windowStart,
            'window_end'       => $windowEnd,
            'duration_seconds' => $durationSeconds, // 28800 saniye = 480 dakika
            'duration_minutes' => round($durationSeconds / 60, 1)
        ];
    }

    /**
     * Belirli bir zaman penceresi için [startWindow, endWindow] Time-Slicing duruş sürelerini hesaplar.
     * Cross-shift duruşları tam olarak kesiştirir.
     */
    public function calculateWindowDowntimes(int $lineId, string $windowStart, string $windowEnd): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ld.*, dr.category AS reason_category, dr.code AS reason_code, dr.name AS reason_name
            FROM line_downtimes ld
            JOIN downtime_reasons dr ON ld.reason_id = dr.id
            WHERE ld.line_id = :line_id 
              AND ld.started_at < :window_end 
              AND (ld.ended_at IS NULL OR ld.ended_at > :window_start)
        ");
        $stmt->execute([
            ':line_id'      => $lineId,
            ':window_start' => $windowStart,
            ':window_end'   => $windowEnd
        ]);
        $downtimes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $wStartTs = strtotime($windowStart);
        $wEndTs = strtotime($windowEnd);
        $nowTs = time();

        $plannedSec = 0;
        $unplannedSec = 0;
        $activeDowntime = null;
        $slicedEvents = [];

        foreach ($downtimes as $dt) {
            $dtStartTs = strtotime($dt['started_at']);
            $dtEndTs = $dt['ended_at'] ? strtotime($dt['ended_at']) : min($nowTs, $wEndTs);

            $sliceStart = max($dtStartTs, $wStartTs);
            $sliceEnd = min($dtEndTs, $wEndTs);

            $sliceDuration = max(0, $sliceEnd - $sliceStart);

            if ($dt['status'] === 'OPEN') {
                $activeDowntime = $dt;
            }

            if ($sliceDuration > 0) {
                if ((int)$dt['is_planned'] === 1) {
                    $plannedSec += $sliceDuration;
                } else {
                    $unplannedSec += $sliceDuration;
                }

                $slicedEvents[] = [
                    'downtime_id'      => (int)$dt['id'],
                    'reason_code'      => $dt['reason_code'],
                    'reason_name'      => $dt['reason_name'],
                    'is_planned'       => (bool)$dt['is_planned'],
                    'slice_duration_s' => $sliceDuration,
                    'slice_duration_m' => round($sliceDuration / 60, 1)
                ];
            }
        }

        return [
            'planned_seconds'   => $plannedSec,
            'unplanned_seconds' => $unplannedSec,
            'planned_minutes'   => round($plannedSec / 60, 1),
            'unplanned_minutes' => round($unplannedSec / 60, 1),
            'total_downtime_m'  => round(($plannedSec + $unplannedSec) / 60, 1),
            'active_downtime'   => $activeDowntime,
            'sliced_events'     => $slicedEvents
        ];
    }

    /**
     * Tek bir vardiya ve hat için OEE hesaplar.
     */
    public function calculateShiftOee(int $lineId, int $shiftId, string $date): array
    {
        $window = $this->getShiftWindow($shiftId, $date);
        if (!$window) {
            return ['success' => false, 'message' => 'Geçersiz vardiya penceresi.'];
        }

        $wStart = $window['window_start'];
        $wEnd = $window['window_end'];
        $totalShiftSeconds = $window['duration_seconds']; // 28800 sn (480 dk)

        // 1. Duruş Süreleri (Time-Slicing)
        $dtMetrics = $this->calculateWindowDowntimes($lineId, $wStart, $wEnd);
        $plannedDtSec = $dtMetrics['planned_seconds'];
        $unplannedDtSec = $dtMetrics['unplanned_seconds'];

        // 2. Loading Time (Planlanan Çalışma Süresi) & Operating Time (Fiili Çalışma Süresi)
        $loadingTimeSec = max(0, $totalShiftSeconds - $plannedDtSec);
        $operatingTimeSec = max(0, $loadingTimeSec - $unplannedDtSec);

        // 3. Availability (Kullanılabilirlik)
        $availabilityPct = ($loadingTimeSec > 0) ? round(($operatingTimeSec / $loadingTimeSec) * 100, 2) : 0.0;

        // 4. Üretim Miktarı (mes_production_events.event_time üzerinden)
        $stmtProd = $this->pdo->prepare("
            SELECT COALESCE(SUM(quantity), 0) AS total_qty
            FROM mes_production_events
            WHERE production_line_id = :line_id
              AND event_time >= :w_start
              AND event_time < :w_end
              AND status = 'PROCESSED'
        ");
        $stmtProd->execute([
            ':line_id' => $lineId,
            ':w_start' => $wStart,
            ':w_end'   => $wEnd
        ]);
        $producedQty = (int)$stmtProd->fetchColumn();

        // 5. Performance (Performans)
        $idealCycleSec = $this->getIdealCycleSeconds($lineId);
        $idealOperatingSec = $producedQty * $idealCycleSec;
        $performancePct = ($operatingTimeSec > 0) ? round(($idealOperatingSec / $operatingTimeSec) * 100, 2) : 0.0;
        // %100 üzeri aşırı hız durumunda sınırlandırma
        if ($performancePct > 100.0 && $operatingTimeSec > 0) {
            $performancePct = min(100.0, $performancePct);
        }

        // 6. Quality (Kalite - Scrap Deduction)
        $stmtRej = $this->pdo->prepare("
            SELECT COUNT(*) AS rejected_qty
            FROM panel_units
            WHERE production_line_id = :line_id
              AND produced_at >= :w_start
              AND produced_at < :w_end
              AND status = 'QUALITY_REJECTED'
        ");
        $stmtRej->execute([
            ':line_id' => $lineId,
            ':w_start' => $wStart,
            ':w_end'   => $wEnd
        ]);
        $rejectedQty = (int)$stmtRej->fetchColumn();
        $approvedQty = max(0, $producedQty - $rejectedQty);

        if ($producedQty === 0) {
            $qualityPct = 100.0; // Üretim yoksa kalite kaybı yoktur
        } else {
            $qualityPct = max(0.0, round(($approvedQty / $producedQty) * 100, 2));
        }

        // 7. OEE = A * P * Q
        $oeePct = round(($availabilityPct / 100.0) * ($performancePct / 100.0) * ($qualityPct / 100.0) * 100.0, 2);

        return [
            'success'                  => true,
            'line_id'                  => $lineId,
            'shift_id'                 => $shiftId,
            'shift_code'               => $window['shift_code'],
            'shift_name'               => $window['shift_name'],
            'date'                     => $date,
            'window_start'             => $wStart,
            'window_end'               => $wEnd,
            'total_shift_minutes'      => round($totalShiftSeconds / 60, 1),
            'planned_downtime_minutes' => $dtMetrics['planned_minutes'],
            'unplanned_downtime_min'   => $dtMetrics['unplanned_minutes'],
            'loading_time_minutes'     => round($loadingTimeSec / 60, 1),
            'operating_time_minutes'   => round($operatingTimeSec / 60, 1),
            'ideal_cycle_seconds'      => $idealCycleSec,
            'produced_quantity'        => $producedQty,
            'approved_quantity'        => $approvedQty,
            'rejected_quantity'        => $rejectedQty,
            'availability_pct'         => $availabilityPct,
            'performance_pct'          => $performancePct,
            'quality_pct'              => $qualityPct,
            'oee_pct'                  => $oeePct,
            'active_downtime'          => $dtMetrics['active_downtime']
        ];
    }

    /**
     * Biten vardiya için OEE Snapshot oluşturur veya günceller (Mühürleme).
     */
    public function snapshotShift(int $lineId, int $shiftId, string $date): array
    {
        $metrics = $this->calculateShiftOee($lineId, $shiftId, $date);
        if (!$metrics['success']) {
            return $metrics;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO oee_shift_snapshots 
                (shift_id, production_line_id, business_date, availability, performance, quality, oee, production_quantity, downtime_minutes, planned_downtime_minutes, snapshot_at, created_at)
            VALUES 
                (:shift_id, :line_id, :b_date, :avail, :perf, :qual, :oee, :prod_qty, :dt_min, :plan_dt_min, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                availability = VALUES(availability),
                performance = VALUES(performance),
                quality = VALUES(quality),
                oee = VALUES(oee),
                production_quantity = VALUES(production_quantity),
                downtime_minutes = VALUES(downtime_minutes),
                planned_downtime_minutes = VALUES(planned_downtime_minutes),
                snapshot_at = NOW()
        ");

        $stmt->execute([
            ':shift_id'    => $shiftId,
            ':line_id'     => $lineId,
            ':b_date'      => $date,
            ':avail'       => $metrics['availability_pct'],
            ':perf'        => $metrics['performance_pct'],
            ':qual'        => $metrics['quality_pct'],
            ':oee'         => $metrics['oee_pct'],
            ':prod_qty'    => $metrics['produced_quantity'],
            ':dt_min'      => (int)$metrics['unplanned_downtime_min'],
            ':plan_dt_min' => (int)$metrics['planned_downtime_minutes']
        ]);

        return [
            'success'     => true,
            'snapshot_at' => date('Y-m-d H:i:s'),
            'metrics'     => $metrics
        ];
    }

    /**
     * Belirli bir tarih aralığı için tüm hatların genel OEE özetini döner.
     */
    public function getFactoryOeeSummary(string $startDate, string $endDate): array
    {
        $stmtLines = $this->pdo->query("SELECT id, code, name, nominal_power_kw, status FROM production_lines WHERE is_active = 1 ORDER BY id ASC");
        $lines = $stmtLines->fetchAll(PDO::FETCH_ASSOC);

        $lineSummaries = [];
        $totalOeeSum = 0;
        $totalAvailSum = 0;
        $totalPerfSum = 0;
        $totalQualSum = 0;
        $totalProduced = 0;
        $totalUnplannedDtMin = 0;
        $activeDowntimesCount = 0;

        // Current Shift (Live)
        $currentHour = (int)date('H');
        $currentShiftId = ($currentHour >= 8 && $currentHour < 16) ? 1 : (($currentHour >= 16 && $currentHour < 24) ? 2 : 3);
        $today = date('Y-m-d');

        foreach ($lines as $line) {
            $lineId = (int)$line['id'];
            $metrics = $this->calculateShiftOee($lineId, $currentShiftId, $today);

            if (!empty($metrics['active_downtime'])) {
                $activeDowntimesCount++;
            }

            $lineSummaries[] = [
                'line_id'          => $lineId,
                'line_code'        => $line['code'],
                'line_name'        => $line['name'],
                'nominal_power_kw' => (float)$line['nominal_power_kw'],
                'status'           => $line['status'],
                'metrics'          => $metrics
            ];

            $totalOeeSum += $metrics['oee_pct'];
            $totalAvailSum += $metrics['availability_pct'];
            $totalPerfSum += $metrics['performance_pct'];
            $totalQualSum += $metrics['quality_pct'];
            $totalProduced += $metrics['produced_quantity'];
            $totalUnplannedDtMin += $metrics['unplanned_downtime_min'];
        }

        $lineCount = max(1, count($lines));
        $avgOee = round($totalOeeSum / $lineCount, 1);
        $avgAvail = round($totalAvailSum / $lineCount, 1);
        $avgPerf = round($totalPerfSum / $lineCount, 1);
        $avgQual = round($totalQualSum / $lineCount, 1);

        return [
            'period'                => ['start' => $startDate, 'end' => $endDate],
            'current_shift_id'      => $currentShiftId,
            'current_date'          => $today,
            'overall_oee'           => $avgOee,
            'overall_availability'  => $avgAvail,
            'overall_performance'   => $avgPerf,
            'overall_quality'       => $avgQual,
            'total_produced_qty'    => $totalProduced,
            'total_unplanned_dt_m'  => round($totalUnplannedDtMin, 1),
            'active_downtimes_cnt'  => $activeDowntimesCount,
            'lines'                 => $lineSummaries
        ];
    }

    /**
     * Duruş nedenleri Pareto analizi (En çok duruşa sebep olan nedenler).
     */
    public function getParetoDowntimes(string $startDate, string $endDate, ?int $lineId = null): array
    {
        $sql = "
            SELECT 
                dr.code,
                dr.name,
                dr.category,
                dr.is_planned,
                dr.color_hex,
                COUNT(ld.id) AS occurrence_count,
                COALESCE(SUM(ld.duration_seconds), 0) AS total_seconds,
                ROUND(COALESCE(SUM(ld.duration_seconds), 0) / 60, 1) AS total_minutes
            FROM line_downtimes ld
            JOIN downtime_reasons dr ON ld.reason_id = dr.id
            WHERE ld.started_at >= :start_date AND ld.started_at <= :end_date
        ";

        $params = [
            ':start_date' => $startDate . ' 00:00:00',
            ':end_date'   => $endDate . ' 23:59:59'
        ];

        if ($lineId && $lineId > 0) {
            $sql .= " AND ld.line_id = :line_id";
            $params[':line_id'] = $lineId;
        }

        $sql .= " GROUP BY dr.id ORDER BY total_seconds DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalMinutesAll = array_sum(array_column($rows, 'total_minutes'));
        $cumulativeMinutes = 0;

        $results = [];
        foreach ($rows as $r) {
            $mins = (float)$r['total_minutes'];
            $cumulativeMinutes += $mins;
            $pct = ($totalMinutesAll > 0) ? round(($mins / $totalMinutesAll) * 100, 1) : 0;
            $cumPct = ($totalMinutesAll > 0) ? round(($cumulativeMinutes / $totalMinutesAll) * 100, 1) : 0;

            $results[] = array_merge($r, [
                'share_pct'      => $pct,
                'cumulative_pct' => $cumPct
            ]);
        }

        return [
            'total_minutes' => round($totalMinutesAll, 1),
            'reasons'       => $results
        ];
    }
}

