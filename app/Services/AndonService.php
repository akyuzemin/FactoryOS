<?php

require_once __DIR__ . '/OeeCalculationService.php';
require_once __DIR__ . '/DowntimeManagementService.php';

class AndonService
{
    private PDO $pdo;
    private OeeCalculationService $oeeService;
    private DowntimeManagementService $downtimeService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->oeeService = new OeeCalculationService($pdo);
        $this->downtimeService = new DowntimeManagementService($pdo);
    }

    /**
     * Canlı Andon ekranı için tek sorgu bloğunda tüm fabrika ve hat telemetrisini döner.
     */
    public function getAndonData(): array
    {
        $today = date('Y-m-d');
        $currentHour = (int)date('H');

        // 1. Aktif Vardiya Tespiti (SHIFT-1: 08-16, SHIFT-2: 16-00, SHIFT-3: 00-08)
        $currentShiftId = ($currentHour >= 8 && $currentHour < 16) ? 1 : (($currentHour >= 16 && $currentHour < 24) ? 2 : 3);
        $shiftWindow = $this->oeeService->getShiftWindow($currentShiftId, $today);

        $nowTs = time();
        $startTs = $shiftWindow ? strtotime($shiftWindow['window_start']) : strtotime("{$today} 08:00:00");
        $endTs = $shiftWindow ? strtotime($shiftWindow['window_end']) : strtotime("{$today} 16:00:00");

        $elapsedSeconds = max(0, min(28800, $nowTs - $startTs));
        $elapsedMinutes = round($elapsedSeconds / 60, 1);
        $remainingMinutes = max(0, round((28800 - $elapsedSeconds) / 60, 1));
        $shiftProgressPct = round(($elapsedSeconds / 28800) * 100, 1);

        // 2. Üretim Hatları Sorgusu
        $stmtLines = $this->pdo->query("
            SELECT id, code, name, nominal_power_kw, status, status_note, status_updated_at 
            FROM production_lines 
            WHERE is_active = 1 
            ORDER BY id ASC
        ");
        $rawLines = $stmtLines->fetchAll(PDO::FETCH_ASSOC);

        $linesData = [];
        $totalFactoryProduced = 0;
        $totalFactoryTarget = 0;
        $totalOeeSum = 0;
        $totalAvailSum = 0;
        $totalPerfSum = 0;
        $totalQualSum = 0;
        $totalUnplannedDtMin = 0;
        $activeDowntimesCount = 0;
        $activeDowntimeList = [];

        foreach ($rawLines as $line) {
            $lineId = (int)$line['id'];

            // Hat OEE Metrikleri (OeeCalculationService üzerinden)
            $oeeMetrics = $this->oeeService->calculateShiftOee($lineId, $currentShiftId, $today);

            $producedQty = (int)($oeeMetrics['produced_quantity'] ?? 0);
            $idealCycleSec = (float)($oeeMetrics['ideal_cycle_seconds'] ?? 180.0);

            // Vardiya Hedef Hesabı (Nominal kapasite veya aktif iş emri hedefi)
            $stmtWo = $this->pdo->prepare("
                SELECT id, work_order_no, planned_quantity, produced_quantity, status 
                FROM mes_work_orders 
                WHERE production_line_id = :line_id AND status = 'RUNNING' 
                ORDER BY id DESC LIMIT 1
            ");
            $stmtWo->execute([':line_id' => $lineId]);
            $activeWo = $stmtWo->fetch(PDO::FETCH_ASSOC);

            if ($activeWo && (float)$activeWo['planned_quantity'] > 0) {
                $shiftTarget = (int)$activeWo['planned_quantity'];
            } else {
                // Standart 8 saat nominal hedef: 28.800 saniye / ideal çevrim süresi
                $shiftTarget = ($idealCycleSec > 0) ? (int)floor(28800 / $idealCycleSec) : 160;
            }

            $realizationPct = ($shiftTarget > 0) ? round(($producedQty / $shiftTarget) * 100, 1) : 0.0;

            // Son Üretim Zamanı ve Kaç Saniye Önce Olduğu
            $stmtLastEvent = $this->pdo->prepare("
                SELECT event_time, TIMESTAMPDIFF(SECOND, event_time, NOW()) AS seconds_ago 
                FROM mes_production_events 
                WHERE production_line_id = :line_id AND status = 'PROCESSED' 
                ORDER BY event_time DESC LIMIT 1
            ");
            $stmtLastEvent->execute([':line_id' => $lineId]);
            $lastEvent = $stmtLastEvent->fetch(PDO::FETCH_ASSOC);

            $lastProducedAt = $lastEvent ? $lastEvent['event_time'] : null;
            $secondsSinceLastPanel = $lastEvent ? (int)$lastEvent['seconds_ago'] : null;

            // Son 1 Saatteki Üretim Hızı (Panel / Saat)
            $stmtLastHour = $this->pdo->prepare("
                SELECT COALESCE(SUM(quantity), 0) AS qty_last_hour 
                FROM mes_production_events 
                WHERE production_line_id = :line_id 
                  AND status = 'PROCESSED' 
                  AND event_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmtLastHour->execute([':line_id' => $lineId]);
            $lastHourPace = (int)$stmtLastHour->fetchColumn();

            // Aktif Duruş Tespiti ve Durum Belirleme
            $openDt = $this->downtimeService->getOpenDowntime($lineId);
            $effectiveStatus = $line['status'];

            if ($openDt) {
                $activeDowntimesCount++;
                $isPlanned = (int)$openDt['is_planned'] === 1;
                $effectiveStatus = $isPlanned ? 'MAINTENANCE' : 'FAULT';

                $dtDurationSec = max(0, time() - strtotime($openDt['started_at']));
                $dtMins = floor($dtDurationSec / 60);
                $dtSecs = $dtDurationSec % 60;
                $formattedDuration = sprintf('%02d:%02d', $dtMins, $dtSecs);

                $dtInfo = [
                    'downtime_id'        => (int)$openDt['id'],
                    'reason_id'          => (int)$openDt['reason_id'],
                    'reason_code'        => $openDt['reason_code'],
                    'reason_name'        => $openDt['reason_name'],
                    'category'           => $openDt['reason_category'],
                    'is_planned'         => $isPlanned,
                    'color_hex'          => $openDt['color_hex'] ?: ($isPlanned ? '#eab308' : '#ef4444'),
                    'started_at'         => $openDt['started_at'],
                    'started_at_time'    => date('H:i:s', strtotime($openDt['started_at'])),
                    'duration_seconds'   => $dtDurationSec,
                    'duration_minutes'   => round($dtDurationSec / 60, 1),
                    'formatted_duration' => $formattedDuration,
                    'operator_note'      => $openDt['operator_note']
                ];

                $activeDowntimeList[] = array_merge($dtInfo, [
                    'line_id'   => $lineId,
                    'line_code' => $line['code'],
                    'line_name' => $line['name']
                ]);
            } else {
                $dtInfo = null;
                if ($effectiveStatus !== 'RUNNING' && $effectiveStatus !== 'MAINTENANCE' && $effectiveStatus !== 'FAULT') {
                    $effectiveStatus = ($producedQty > 0 || ($activeWo && $activeWo['status'] === 'RUNNING')) ? 'RUNNING' : 'IDLE';
                }
            }

            $lineItem = [
                'id'                      => $lineId,
                'code'                    => $line['code'],
                'name'                    => $line['name'],
                'nominal_power_kw'        => (float)$line['nominal_power_kw'],
                'status'                  => $effectiveStatus,
                'status_note'             => $line['status_note'],
                'produced_quantity'       => $producedQty,
                'shift_target'            => $shiftTarget,
                'realization_pct'         => $realizationPct,
                'ideal_cycle_seconds'     => $idealCycleSec,
                'last_hour_pace'          => $lastHourPace,
                'last_produced_at'        => $lastProducedAt,
                'seconds_since_last'      => $secondsSinceLastPanel,
                'oee_pct'                 => (float)($oeeMetrics['oee_pct'] ?? 0.0),
                'availability_pct'        => (float)($oeeMetrics['availability_pct'] ?? 0.0),
                'performance_pct'         => (float)($oeeMetrics['performance_pct'] ?? 0.0),
                'quality_pct'             => (float)($oeeMetrics['quality_pct'] ?? 0.0),
                'unplanned_downtime_min'  => (float)($oeeMetrics['unplanned_downtime_min'] ?? 0.0),
                'planned_downtime_min'    => (float)($oeeMetrics['planned_downtime_minutes'] ?? 0.0),
                'active_work_order'       => $activeWo ? $activeWo['work_order_no'] : null,
                'active_downtime'         => $dtInfo
            ];

            $linesData[] = $lineItem;

            $totalFactoryProduced += $producedQty;
            $totalFactoryTarget += $shiftTarget;
            $totalOeeSum += $lineItem['oee_pct'];
            $totalAvailSum += $lineItem['availability_pct'];
            $totalPerfSum += $lineItem['performance_pct'];
            $totalQualSum += $lineItem['quality_pct'];
            $totalUnplannedDtMin += $lineItem['unplanned_downtime_min'];
        }

        $lineCount = max(1, count($rawLines));
        $avgOee = round($totalOeeSum / $lineCount, 1);
        $avgAvail = round($totalAvailSum / $lineCount, 1);
        $avgPerf = round($totalPerfSum / $lineCount, 1);
        $avgQual = round($totalQualSum / $lineCount, 1);
        $factoryRealizationPct = ($totalFactoryTarget > 0) ? round(($totalFactoryProduced / $totalFactoryTarget) * 100, 1) : 0.0;

        // 3. Aktif Alarmlar (energy_alerts tablosundan teyit edilmemiş alarmlar)
        $activeAlertsCount = 0;
        try {
            $stmtAlerts = $this->pdo->query("SELECT COUNT(*) FROM energy_alerts WHERE is_acknowledged = 0");
            $activeAlertsCount = (int)$stmtAlerts->fetchColumn();
        } catch (Throwable $e) {
            $activeAlertsCount = 0;
        }

        return [
            'timestamp'           => date('Y-m-d H:i:s'),
            'current_time_fmt'    => date('H:i:s'),
            'current_date_fmt'    => date('d.m.Y'),
            'shift'               => [
                'id'                => $currentShiftId,
                'code'              => $shiftWindow['shift_code'] ?? "SHIFT-{$currentShiftId}",
                'name'              => $shiftWindow['shift_name'] ?? "Vardiya {$currentShiftId}",
                'start_time'        => $shiftWindow['window_start'] ?? "{$today} 08:00:00",
                'end_time'          => $shiftWindow['window_end'] ?? "{$today} 16:00:00",
                'elapsed_minutes'   => $elapsedMinutes,
                'remaining_minutes' => $remainingMinutes,
                'progress_pct'      => $shiftProgressPct
            ],
            'factory_summary'     => [
                'total_produced_qty'      => $totalFactoryProduced,
                'total_target_qty'        => $totalFactoryTarget,
                'realization_pct'         => $factoryRealizationPct,
                'overall_oee'             => $avgOee,
                'overall_availability'    => $avgAvail,
                'overall_performance'     => $avgPerf,
                'overall_quality'         => $avgQual,
                'active_downtimes_count'  => $activeDowntimesCount,
                'total_unplanned_dt_min'  => round($totalUnplannedDtMin, 1),
                'active_alerts_count'     => $activeAlertsCount
            ],
            'active_downtimes'    => $activeDowntimeList,
            'lines'               => $linesData
        ];
    }
}

