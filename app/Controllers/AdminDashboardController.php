<?php

require_once __DIR__ . '/../Models/AdminDashboard.php';
require_once __DIR__ . '/../Services/OeeCalculationService.php';
require_once __DIR__ . '/../Services/MaintenanceService.php';
require_once __DIR__ . '/../Services/DowntimeManagementService.php';

class AdminDashboardController
{
    private AdminDashboard $model;
    private OeeCalculationService $oeeService;
    private MaintenanceService $maintenanceService;
    private DowntimeManagementService $downtimeService;

    public function __construct(PDO $pdo)
    {
        $this->model = new AdminDashboard($pdo);
        $this->oeeService = new OeeCalculationService($pdo);
        $this->maintenanceService = new MaintenanceService($pdo);
        $this->downtimeService = new DowntimeManagementService($pdo);
    }

    public function index(): void
    {
        $range = $_GET['range'] ?? 'today';
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        $dateInfo = $this->model->resolveDateRange($range, $startDate, $endDate);
        $sDate = $dateInfo['start_date'];
        $eDate = $dateInfo['end_date'];

        $kpis = $this->model->getExecutiveKpis($sDate, $eDate);
        $trend = $this->model->getProductionTrend($sDate, $eDate);
        $topMaterials = $this->model->getTopMaterialsConsumed($sDate, $eDate);
        $quality = $this->model->getQualityMetrics($sDate, $eDate);
        $shipments = $this->model->getShipmentMetrics($sDate, $eDate);
        $lineMatrix = $this->model->getLineComparisonMatrix($sDate, $eDate);
        $oeeSummary = $this->oeeService->getFactoryOeeSummary($sDate, $eDate);
        $criticalStocks = $this->model->getCriticalStocks(5);
        $maintenanceSummary = $this->maintenanceService->getDashboardSummary();
        $openDowntimes = $this->downtimeService->getAllOpenDowntimes();

        $pageTitle = 'Yönetici Dashboard & Raporlama';
        $activePage = 'admin-dashboard';

        require __DIR__ . '/../../views/admin/dashboard.php';
    }
}
