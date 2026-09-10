<?php

require_once __DIR__ . '/../Models/EnergyCost.php';
require_once __DIR__ . '/../Services/EnergyCostService.php';

class EnergyCostController
{
    private EnergyCost $model;
    private EnergyCostService $service;

    public function __construct()
    {
        $this->model = new EnergyCost($GLOBALS['pdo']);
        $this->service = new EnergyCostService($this->model);
    }

    public function index(): void
    {
        $latestDate = $this->model->getLatestDate();

        // Tarih ve Aralık parametrelerini doğrula
        $targetDate = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) 
            ? $_GET['date'] 
            : $latestDate;

        $range = isset($_GET['range']) && in_array($_GET['range'], ['today', '7days', '30days', 'custom'], true)
            ? $_GET['range']
            : 'today';

        $startDate = isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date'])
            ? $_GET['start_date']
            : null;

        $endDate = isset($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date'])
            ? $_GET['end_date']
            : null;

        // Karar Destek Raporunu Çek
        $report = $this->service->getDecisionReport($targetDate, $range, $startDate, $endDate);

        $summary = $report['summary'] ?? [];
        $tariffs = $report['tariffs'] ?? [];
        $peak = $report['peak'] ?? [];
        $idle = $report['idle'] ?? [];
        $sec = $report['sec'] ?? [];
        $solar = $report['solar'] ?? [];
        $scenarios = $report['scenarios'] ?? [];
        $savingsEngine = $report['savings_engine'] ?? ['rules' => array_values($scenarios)];
        $shiftScenario = $report['shiftScenario'] ?? ($scenarios['peak_shift'] ?? []);
        $chartData = $report['chart_data'] ?? [];
        $totalMonthlySavingTl = $report['total_monthly_saving_tl'] ?? 0.0;
        $totalAnnualSavingTl = $report['total_annual_saving_tl'] ?? 0.0;

        $pageTitle = 'Maliyet & Tasarruf';
        $activePage = 'energy-cost';

        require __DIR__ . '/../../views/energy-cost/index.php';
    }
}
