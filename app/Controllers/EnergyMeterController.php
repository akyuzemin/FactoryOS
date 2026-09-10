<?php

require_once __DIR__ . '/../Models/EnergyDashboard.php';

class EnergyMeterController
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
        $meters = $this->energyModel->getMetersWithTelemetry($targetDate);
        $zones = $this->energyModel->getFacilityZonesSummary($targetDate);

        $pageTitle = 'Sayaçlar & Tesis';
        $activePage = 'energy-meters';

        require __DIR__ . '/../../views/energy/meters.php';
    }
}