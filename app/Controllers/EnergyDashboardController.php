<?php

require_once __DIR__ . '/../Models/EnergyDashboard.php';
require_once __DIR__ . '/../Services/EnergyProductionLinkService.php';

class EnergyDashboardController
{
    private EnergyDashboard $energyModel;
    private EnergyProductionLinkService $linkService;

    public function __construct()
    {
        $this->energyModel = new EnergyDashboard($GLOBALS['pdo']);
        $this->linkService = new EnergyProductionLinkService($GLOBALS['pdo']);
    }

    public function index(): void
    {
        $latestDate = $this->energyModel->getLatestDate();
        $targetDate = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) 
            ? $_GET['date'] 
            : $latestDate;

        $kpis = $this->energyModel->getKpis($targetDate);
        $hourly = $this->energyModel->getHourlyLoadProfile($targetDate);
        $daily = $this->energyModel->getDaily7DayTrend();
        $breakdown = $this->energyModel->getEnergyBreakdown($targetDate);
        $savings = $this->energyModel->getSavingsOpportunities($targetDate);
        $latestAlerts = $this->energyModel->getActiveAlerts(4);

        // Hat bazlı Spesifik Enerji Tüketimi (SEC) ve Puant Tarife Verileri
        $lineSecData = $this->linkService->getLineSpecificEnergyConsumption($targetDate);
        $tariffPeakData = $this->linkService->getDynamicTariffAnalysis($targetDate);

        $pageTitle = 'Enerji Dashboard';
        $activePage = 'energy-dashboard';

        require __DIR__ . '/../../views/energy/dashboard.php';
    }
}