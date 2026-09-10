<?php

require_once __DIR__ . '/../Models/EnergyDashboard.php';

class EnergyReportController
{
    private EnergyDashboard $energyModel;

    public function __construct()
    {
        $this->energyModel = new EnergyDashboard($GLOBALS['pdo']);
    }

    public function index(): void
    {
        $latestDate = $this->energyModel->getLatestDate();

        $range = isset($_GET['range']) && in_array($_GET['range'], ['7days', '30days', 'custom'], true)
            ? $_GET['range']
            : '7days';

        if ($range === '30days') {
            $startDate = date('Y-m-d', strtotime($latestDate . ' -29 days'));
            $endDate = $latestDate;
        } elseif ($range === 'custom' && !empty($_GET['start_date']) && !empty($_GET['end_date'])) {
            $startDate = $_GET['start_date'];
            $endDate = $_GET['end_date'];
        } else {
            $startDate = date('Y-m-d', strtotime($latestDate . ' -6 days'));
            $endDate = $latestDate;
            $range = '7days';
        }

        $report = $this->energyModel->getHistoricalReportData($startDate, $endDate);

        $pageTitle = 'Raporlar';
        $activePage = 'energy-reports';

        require __DIR__ . '/../../views/energy-reports/index.php';
    }
}
