<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/EmployeeDashboard.php';

class EmployeeDashboardController
{
    private EmployeeDashboard $dashboardModel;
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->dashboardModel = new EmployeeDashboard($pdo);
    }

    /**
     * GET /employees/dashboard
     * Çalışanlar, vardiya çizelgesi, departman dağılımı ve zimmet özeti dashboardu.
     */
    public function index(): void
    {
        $today = date('Y-m-d');

        $kpis = $this->dashboardModel->getEmployeeKpis();
        $todayShifts = $this->dashboardModel->getTodayShiftDistribution($today);
        $departments = $this->dashboardModel->getDepartmentDistribution();
        $departmentMatrix = $this->dashboardModel->getDepartmentShiftMatrix($today);
        $upcomingPlan = $this->dashboardModel->getUpcomingShiftPlan($today);
        $inventorySummary = $this->dashboardModel->getInventorySummary();
        $unassignedEmployees = $this->dashboardModel->getUnassignedOrAttentionEmployees($today);

        $pageTitle = 'Çalışan Dashboardu';
        $activePage = 'employee-dashboard';

        require __DIR__ . '/../../views/employees/dashboard.php';
    }
}

