<?php

require_once __DIR__ . '/../Models/EnergyDashboard.php';

class EnergyProductionController
{
    private EnergyDashboard $energyModel;

    public function __construct()
    {
        $this->energyModel = new EnergyDashboard($GLOBALS['pdo']);
    }

    public function index(): void
    {
        $latestDate = $this->energyModel->getLatestDate();
        $targetDate = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) 
            ? $_GET['date'] 
            : $latestDate;

        $kpis = $this->energyModel->getKpis($targetDate);
        $productionLines = $this->energyModel->getProductionLinesOverview($targetDate);
        $lines = $productionLines;
        $breakdown = $this->energyModel->getEnergyBreakdown($targetDate);
        $daily = $this->energyModel->getDaily7DayTrend();

        $pageTitle = 'Üretim Performansı';
        $activePage = 'energy-production';

        require __DIR__ . '/../../views/energy/production.php';
    }
}