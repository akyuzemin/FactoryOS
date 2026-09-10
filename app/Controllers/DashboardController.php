<?php

require_once __DIR__ . '/../Services/FactoryOverviewService.php';

class DashboardController
{
    private FactoryOverviewService $factoryOverviewService;

    public function __construct(PDO $pdo)
    {
        $this->factoryOverviewService = new FactoryOverviewService($pdo);
    }

    public function index(): void
    {
        $period = isset($_GET['period']) ? trim((string)$_GET['period']) : 'today';
        if (!in_array($period, ['today', 'week', 'month'], true)) {
            $period = 'today';
        }

        $overviewData = $this->factoryOverviewService->getOverviewData($period);

        require __DIR__ . '/../../views/dashboard/index.php';
    }
}