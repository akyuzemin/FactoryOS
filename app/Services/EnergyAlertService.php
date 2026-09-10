<?php

require_once __DIR__ . '/../Models/EnergyAlert.php';

class EnergyAlertService
{
    private EnergyAlert $model;
    private PDO $pdo;

    public function __construct(EnergyAlert $model, PDO $pdo)
    {
        $this->model = $model;
        $this->pdo = $pdo;
    }

    /**
     * energy_readings tablosunu kurallara göre analiz eder ve yeni anomalileri
     * idempotent şekilde energy_alerts tablosuna kaydeder.
     */
    public function scanAndGenerateAnomalies(): array
    {
        $createdCount = 0;
        $rulesTriggered = [];

        // Kural A: Aşırı Güç Tüketimi (Nominal Güç Aşımı)
        $stmtA = $this->pdo->query("
            SELECT r.meter_id, r.read_at, r.active_power_kw, m.code as meter_code, m.name as meter_name,
                   pl.name as line_name, pl.nominal_power_kw
            FROM energy_readings r
            JOIN energy_meters m ON r.meter_id = m.id
            JOIN production_lines pl ON m.line_id = pl.id
            WHERE r.active_power_kw > pl.nominal_power_kw
            ORDER BY r.read_at DESC
            LIMIT 5
        ");
        while ($row = $stmtA->fetch(PDO::FETCH_ASSOC)) {
            $pwr = (float)$row['active_power_kw'];
            $nom = (float)$row['nominal_power_kw'];
            $severity = $pwr > ($nom * 1.10) ? 'CRITICAL' : 'WARNING';
            $pctOver = round((($pwr - $nom) / $nom) * 100, 1);

            $id = $this->model->createAlertIfNotExist([
                'meter_id' => (int)$row['meter_id'],
                'alert_type' => 'THRESHOLD_EXCEEDED',
                'severity' => $severity,
                'threshold_value' => $nom,
                'measured_value' => $pwr,
                'message' => "{$row['line_name']} anlık yükü ({$pwr} kW), nominal kapasitesinin ({$nom} kW) %{$pctOver} üzerinde çalışıyor.",
                'created_at' => $row['read_at']
            ]);
            if ($id) {
                $createdCount++;
                $rulesTriggered['Aşırı Güç Tüketimi'] = ($rulesTriggered['Aşırı Güç Tüketimi'] ?? 0) + 1;
            }
        }

        // Kural B: Gece Baz Yük (00:00 - 06:00 arası yüksek bekleme tüketimi)
        $stmtB = $this->pdo->query("
            SELECT r.meter_id, r.read_at, r.active_power_kw, m.code as meter_code, m.name as meter_name
            FROM energy_readings r
            JOIN energy_meters m ON r.meter_id = m.id
            WHERE m.code IN ('MTR-LAM-1', 'MTR-LAM-2', 'MTR-COMP-1')
              AND HOUR(r.read_at) >= 0 AND HOUR(r.read_at) < 6
              AND r.active_power_kw > 80.0
            ORDER BY r.read_at DESC
            LIMIT 5
        ");
        while ($row = $stmtB->fetch(PDO::FETCH_ASSOC)) {
            $pwr = (float)$row['active_power_kw'];
            $id = $this->model->createAlertIfNotExist([
                'meter_id' => (int)$row['meter_id'],
                'alert_type' => 'IDLE_CONSUMPTION',
                'severity' => 'WARNING',
                'threshold_value' => 45.0,
                'measured_value' => $pwr,
                'message' => "{$row['meter_name']} gece vardiyası üretim dışı saatte (" . date('H:i', strtotime($row['read_at'])) . ") " . number_format($pwr, 1) . " kW yüksek baz yük çekmektedir.",
                'created_at' => $row['read_at']
            ]);
            if ($id) {
                $createdCount++;
                $rulesTriggered['Gece Baz Yük'] = ($rulesTriggered['Gece Baz Yük'] ?? 0) + 1;
            }
        }

        // Kural C: Güç Faktörü / Reaktif Oran (cos φ < 0.90)
        $stmtC = $this->pdo->query("
            SELECT r.meter_id, r.read_at, r.power_factor, m.code as meter_code, m.name as meter_name
            FROM energy_readings r
            JOIN energy_meters m ON r.meter_id = m.id
            WHERE r.power_factor < 0.90 AND r.power_factor > 0.0
            ORDER BY r.read_at DESC
            LIMIT 5
        ");
        while ($row = $stmtC->fetch(PDO::FETCH_ASSOC)) {
            $pf = (float)$row['power_factor'];
            $severity = $pf < 0.85 ? 'CRITICAL' : 'WARNING';
            $id = $this->model->createAlertIfNotExist([
                'meter_id' => (int)$row['meter_id'],
                'alert_type' => 'REACTIVE_INDUCTIVE_LIMIT',
                'severity' => $severity,
                'threshold_value' => 0.90,
                'measured_value' => $pf,
                'message' => "{$row['meter_name']} güç analizöründe güç faktörü (cos φ) " . number_format($pf, 4) . " seviyesine geriledi (Limit: 0.9000).",
                'created_at' => $row['read_at']
            ]);
            if ($id) {
                $createdCount++;
                $rulesTriggered['Düşük Güç Faktörü'] = ($rulesTriggered['Düşük Güç Faktörü'] ?? 0) + 1;
            }
        }

        // Kural D: Gerilim Tolerans Dışı (< 380V veya > 420V)
        // Güneş sayacının gece 0 V olması normal kabul edilir, hariç tutulur
        $stmtD = $this->pdo->query("
            SELECT r.meter_id, r.read_at, r.voltage_v, m.code as meter_code, m.name as meter_name
            FROM energy_readings r
            JOIN energy_meters m ON r.meter_id = m.id
            WHERE m.code != 'MTR-SOLAR-MAIN' 
              AND (r.voltage_v < 380.0 OR r.voltage_v > 420.0)
              AND r.voltage_v > 50.0
            ORDER BY r.read_at DESC
            LIMIT 5
        ");
        while ($row = $stmtD->fetch(PDO::FETCH_ASSOC)) {
            $v = (float)$row['voltage_v'];
            $severity = ($v < 375.0 || $v > 425.0) ? 'CRITICAL' : 'WARNING';
            $id = $this->model->createAlertIfNotExist([
                'meter_id' => (int)$row['meter_id'],
                'alert_type' => 'VOLTAGE_ANOMALY',
                'severity' => $severity,
                'threshold_value' => 380.0,
                'measured_value' => $v,
                'message' => "{$row['meter_name']} şebeke geriliminde tolerans dışı sapma tespit edildi: " . number_format($v, 1) . " V (Normal aralık: 380 - 420 V).",
                'created_at' => $row['read_at']
            ]);
            if ($id) {
                $createdCount++;
                $rulesTriggered['Gerilim Anomalisi'] = ($rulesTriggered['Gerilim Anomalisi'] ?? 0) + 1;
            }
        }

        // Kural E: Puant Güç Sıçraması (17:00 - 22:00 arası ana şebekede > 670 kW)
        $stmtE = $this->pdo->query("
            SELECT r.meter_id, r.read_at, r.active_power_kw, m.code as meter_code, m.name as meter_name
            FROM energy_readings r
            JOIN energy_meters m ON r.meter_id = m.id
            WHERE m.code = 'MTR-GRID-MAIN' AND r.tariff_period = 'T2' AND r.active_power_kw > 670.0
            ORDER BY r.read_at DESC
            LIMIT 5
        ");
        while ($row = $stmtE->fetch(PDO::FETCH_ASSOC)) {
            $pwr = (float)$row['active_power_kw'];
            $severity = $pwr > 950.0 ? 'CRITICAL' : 'WARNING';
            $id = $this->model->createAlertIfNotExist([
                'meter_id' => (int)$row['meter_id'],
                'alert_type' => 'PEAK_POWER_SURGE',
                'severity' => $severity,
                'threshold_value' => 650.0,
                'measured_value' => $pwr,
                'message' => "Puant tarifesi saatinde (" . date('H:i', strtotime($row['read_at'])) . ") ana şebeke çekişi " . number_format($pwr, 1) . " kW seviyesine ulaştı.",
                'created_at' => $row['read_at']
            ]);
            if ($id) {
                $createdCount++;
                $rulesTriggered['Puant Güç Sıçraması'] = ($rulesTriggered['Puant Güç Sıçraması'] ?? 0) + 1;
            }
        }

        return [
            'new_alerts_created' => $createdCount,
            'rules_triggered' => $rulesTriggered
        ];
    }

    /**
     * Alarm tipine ve ölçüm sapmasına göre önerilen mühendislik aksiyonunu üretir.
     */
    public static function getRecommendedAction(string $alertType, float $measured, float $threshold, ?string $meterCode = null): array
    {
        switch ($alertType) {
            case 'PEAK_POWER_SURGE':
                return [
                    'problem' => "Puant saat diliminde (17:00 - 22:00 / T2) yüksek pik güç çekişi ({$measured} kW) tespit edildi.",
                    'expected' => "Sözleşme / Hedef Güç < {$threshold} kW",
                    'deviation' => "+" . number_format(max(0, $measured - $threshold), 1) . " kW Eşik Aşımı",
                    'action' => "Hat yükü kontrol edilmeli. Puant saatlerde enerji yoğun laminasyon veya kompresör ön şarjı operasyonlarının gece T3 tarifesine ötelenmesi değerlendirilmelidir."
                ];

            case 'IDLE_CONSUMPTION':
                return [
                    'problem' => "Üretim dışı / gece vardiyası saatlerinde (00:00 - 06:00) gereksiz yüksek baz yük ({$measured} kW) tespit edildi.",
                    'expected' => "Standby Hedef Yükü < {$threshold} kW",
                    'deviation' => "+" . number_format(max(0, $measured - $threshold), 1) . " kW Gereksiz Çekiş",
                    'action' => "Üretim dışı makinelerin otomatik bekleme (Eco-Standby) sıcaklığına alınması ve kompresör hava kaçaklarının giderilmesi önerilir."
                ];

            case 'REACTIVE_INDUCTIVE_LIMIT':
            case 'REACTIVE_CAPACITIVE_LIMIT':
                return [
                    'problem' => "Güç faktörü (cos φ) {$measured} seviyesine düşerek reaktif ceza eşiğini riske attı.",
                    'expected' => "Hedef cos φ ≥ 0.95 (Minimum: {$threshold})",
                    'deviation' => number_format($threshold - $measured, 4) . " Sapma",
                    'action' => "Kompanzasyon panosu kontaktörleri, kondansatör kademeleri ve reaktif güç kontrol rölesi ayarları derhal kontrol edilmelidir."
                ];

            case 'VOLTAGE_ANOMALY':
                return [
                    'problem' => "Şebeke faz geriliminde nominal tolerans dışı dalgalanma ({$measured} V) meydana geldi.",
                    'expected' => "Nominal Aralık: 380.0 V - 420.0 V",
                    'deviation' => ($measured < 380 ? "-" : "+") . number_format(abs($measured - 400), 1) . " V Sapma",
                    'action' => "Trafo kademe ayarı, ana şebeke giriş koruma röleleri ve tesis içi harmonik filtreler gözden geçirilmelidir."
                ];

            case 'SOLAR_UNDERPERFORMANCE':
                return [
                    'problem' => "Gündüz açık hava üretim saatlerinde GES anlık gücü ({$measured} kW) beklenen modelin altında kaldı.",
                    'expected' => "Gündüz Beklenen Pik Güç > {$threshold} kW",
                    'deviation' => "-" . number_format(max(0, $threshold - $measured), 1) . " kW Güneş Kaybı",
                    'action' => "İnvertör MPPT dizi akımları, panel yüzey kirliliği/tozlanması ve DC/AC bağlantıları kontrol edilmelidir."
                ];

            case 'THRESHOLD_EXCEEDED':
            default:
                return [
                    'problem' => "Üretim hattında anlık yük ({$measured} kW) nominal limitin ({$threshold} kW) üzerine çıktı.",
                    'expected' => "Nominal Güç ≤ {$threshold} kW",
                    'deviation' => "+" . number_format(max(0, $measured - $threshold), 1) . " kW Aşım",
                    'action' => "Hat yükü ve motor/rezistans termik koruma değerleri kontrol edilmeli, hat aşırı yük durumunda dengelenmelidir."
                ];
        }
    }
}
