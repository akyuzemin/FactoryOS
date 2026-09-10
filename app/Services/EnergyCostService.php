<?php

require_once __DIR__ . '/../Models/EnergyCost.php';

class EnergyCostService
{
    private EnergyCost $model;

    public function __construct(EnergyCost $model)
    {
        $this->model = $model;
    }

    /**
     * Karar Destek Sistemi için tüm maliyet, tarife ve tasarruf paketini üretir.
     */
    public function getDecisionReport(string $targetDate, string $range = 'today', ?string $startDate = null, ?string $endDate = null): array
    {
        $summary = $this->model->getCostSummary($targetDate, $range, $startDate, $endDate);
        $tariffs = $this->model->getTariffAnalysis($targetDate, $range, $startDate, $endDate);
        $peak = $this->model->getPeakShiftingOpportunity($targetDate, $range, $startDate, $endDate);
        $idle = $this->model->getIdleConsumptionOpportunity($targetDate, $range, $startDate, $endDate);
        $sec = $this->model->getSecOptimization($targetDate, $range, $startDate, $endDate);
        $solar = $this->model->getSolarSelfConsumption($targetDate, $range, $startDate, $endDate);

        // 4 Senaryo Detayları
        $scenarios = [
            'peak_shift' => [
                'id' => 'scenario-1',
                'code' => 'PEAK_SHIFT',
                'title' => 'Puant Saatlerdeki Ağır Yüklerin Kaydırılması (Peak-Shifting)',
                'priority' => 'Yüksek Öncelik',
                'priority_class' => 'status-badge-danger',
                'icon' => '⚡',
                'problem' => 'Puant zaman diliminde (17:00 - 22:00) elektrik birim fiyatı 6.70 TL/kWh ile en pahalı seviyededir. Dönem boyunca ' . number_format($peak['total_puant_kwh'], 1, ',', '.') . ' kWh şebeke elektriği çekilmiştir (Mevcut Maliyet: ₺' . number_format($peak['current_puant_cost_tl'], 2, ',', '.') . ').',
                'data_source' => 'energy_readings (MTR-GRID-MAIN, T2 Dönemi), energy_tariffs',
                'recommendation' => 'Laminatör fırınlarının son kürleme döngüleri ve kompresör hava tankı ön şarjının puant saatler dışına (T3 Gece veya T1 Gündüz) kaydırılması.',
                'formula' => 'Aylık Tasarruf = (Günlük Puant kWh × %25) × (Puant Fiyatı [6.70 TL] - Gece Fiyatı [3.15 TL]) × 30 Gün',
                'monthly_saving_tl' => $peak['monthly_saving_tl'],
                'annual_saving_tl' => $peak['annual_saving_tl'],
                'color' => '#ef4444'
            ],
            'idle_reduction' => [
                'id' => 'scenario-2',
                'code' => 'IDLE_REDUCTION',
                'title' => 'Gece Vardiyası Boşta Çalışma (Standby Idling) Azaltımı',
                'priority' => 'Hızlı Kazanç',
                'priority_class' => 'status-badge-warning',
                'icon' => '🌙',
                'problem' => 'Gece 00:00 - 06:00 saatleri arasında üretim düşükken Laminatör 1, 2 ve Kompresör dairesinde ortalama ' . number_format($idle['total_night_baseline_kw'], 1, ',', '.') . ' kW gereksiz baz yük çekilmektedir (Aylık ' . number_format($idle['monthly_idle_kwh'], 0, ',', '.') . ' kWh kayıp).',
                'data_source' => 'energy_readings (MTR-LAM-1, MTR-LAM-2, MTR-COMP-1, 00:00-06:00 Aralığı)',
                'recommendation' => 'Laminatör fırınlarının üretim aralarında otomatik "Eco-Standby" moduna geçirilmesi ve basınçlı hava hatlarındaki kaçakların giderilmesi ile 30 kW yük tasarrufu.',
                'formula' => 'Aylık Tasarruf = 30 kW (Önlenen Güç) × 6 Saat × 30 Gün × Gece Tarifesi (3.15 TL)',
                'monthly_saving_tl' => $idle['monthly_saving_tl'],
                'annual_saving_tl' => $idle['annual_saving_tl'],
                'color' => '#f59e0b'
            ],
            'sec_optimization' => [
                'id' => 'scenario-3',
                'code' => 'SEC_OPTIMIZATION',
                'title' => 'Laminatör Termal İzolasyon ve SEC İyileştirmesi',
                'priority' => 'Verimlilik & Bakım',
                'priority_class' => 'status-badge-info',
                'icon' => '🔬',
                'problem' => 'Laminatör 1 ve Laminatör 2 hatlarının spesifik enerji tüketimi (SEC), tesis ortalamasının (' . number_format($sec['plant_avg_sec'], 2, ',', '.') . ' kWh/panel) üzerindedir. Termal kayıplar enerji yoğunluğunu artırmaktadır.',
                'data_source' => 'production_lines, energy_production_logs, energy_meters',
                'recommendation' => 'Vakum contalarının yenilenmesi, rezistans kapağı termal yalıtım kılıfı uygulaması ile üretilen her panel başına 0.25 kWh enerji tasarrufu.',
                'formula' => 'Aylık Tasarruf = 50.000 Panel/Ay × 0.25 kWh/Panel × 4.20 TL (Ortalama Enerji Birim Fiyatı)',
                'monthly_saving_tl' => $sec['monthly_saving_tl'],
                'annual_saving_tl' => $sec['annual_saving_tl'],
                'color' => '#8b5cf6'
            ],
            'solar_sync' => [
                'id' => 'scenario-4',
                'code' => 'SOLAR_SYNC',
                'title' => 'Çatı GES Tepe Üretimiyle Tesis Yükü Senkronizasyonu',
                'priority' => 'Öz Tüketim',
                'priority_class' => 'status-badge-success',
                'icon' => '☀️',
                'problem' => 'Güneş üretiminin pik yaptığı 11:00 - 14:00 saatlerinde solar enerjiyi şebekeye 3.50 TL\'den satmak yerine fabrikada 4.60 TL\'lik şebeke alımını ikame etmek daha karlıdır.',
                'data_source' => 'energy_readings (MTR-SOLAR-MAIN, MTR-GRID-MAIN), energy_tariffs',
                'recommendation' => 'Enerji yoğun laminasyon kürleme döngülerinin 11:00-14:00 aralığına denk getirilmesi ile günde ortalama 800 kWh solar enerjinin doğrudan öz tüketilmesi.',
                'formula' => 'Aylık Tasarruf = 800 kWh/Gün × (Gündüz Fiyatı [4.60 TL] - Solar Satış [3.50 TL]) × 26 İş Günü',
                'monthly_saving_tl' => $solar['monthly_sync_saving_tl'],
                'annual_saving_tl' => $solar['annual_sync_saving_tl'],
                'color' => '#10b981'
            ]
        ];

        // Toplam Tasarruf Hesabı
        $totalMonthlySaving = 0.0;
        $chartData = [];
        foreach ($scenarios as $sc) {
            $totalMonthlySaving += (float)$sc['monthly_saving_tl'];
            $chartData[] = [
                'name' => $sc['title'],
                'code' => $sc['code'],
                'monthly_saving_tl' => (float)$sc['monthly_saving_tl'],
                'color' => $sc['color']
            ];
        }
        $totalAnnualSaving = $totalMonthlySaving * 12.0;

        // KPI kartındaki Tasarruf potansiyeli alanını güncelle
        $summary['monthly_savings_potential_tl'] = $totalMonthlySaving;
        $summary['annual_savings_potential_tl'] = $totalAnnualSaving;

        return [
            'summary'                 => $summary,
            'tariffs'                 => $tariffs,
            'peak'                    => $peak,
            'idle'                    => $idle,
            'sec'                     => $sec,
            'solar'                   => $solar,
            'scenarios'               => $scenarios,
            'savings_engine'          => ['rules' => array_values($scenarios)],
            'savingsEngine'           => ['rules' => array_values($scenarios)],
            'shiftScenario'           => $scenarios['peak_shift'] ?? [],
            'chart_data'              => $chartData,
            'total_monthly_saving_tl' => $totalMonthlySaving,
            'total_annual_saving_tl'  => $totalAnnualSaving
        ];
    }
}
