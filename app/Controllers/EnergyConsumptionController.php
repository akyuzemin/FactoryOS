<?php

require_once __DIR__ . '/../Models/EnergyDashboard.php';

class EnergyConsumptionController
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
        $hourly = $this->energyModel->getHourlyLoadProfile($targetDate);
        $daily = $this->energyModel->getDaily7DayTrend();
        $shifts = $this->energyModel->getShiftBreakdown($targetDate);
        $breakdown = $this->energyModel->getEnergyBreakdown($targetDate);
        $tariffs = $this->energyModel->getTariffAnalysis($targetDate);

        $pageTitle = 'Tüketim Analizi';
        $activePage = 'energy-consumption';

        require __DIR__ . '/../../views/energy/consumption.php';
    }
}