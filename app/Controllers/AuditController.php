<?php

require_once __DIR__ . '/../Services/AuditService.php';

class AuditController
{
    private AuditService $auditService;

    public function __construct(PDO $pdo)
    {
        $this->auditService = new AuditService($pdo);
    }

    public function index(): void
    {
        $perPage = 50;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        // Build filters from query string (sanitized)
        $filters = [];
        if (!empty($_GET['module']) && is_string($_GET['module'])) {
            $filters['module'] = trim($_GET['module']);
        }
        if (!empty($_GET['action']) && is_string($_GET['action'])) {
            $filters['action'] = trim($_GET['action']);
        }
        if (!empty($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date'])) {
            $filters['start_date'] = $_GET['start_date'];
        }
        if (!empty($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date'])) {
            $filters['end_date'] = $_GET['end_date'];
        }
        if (!empty($_GET['user_id']) && ctype_digit((string)$_GET['user_id'])) {
            $filters['user_id'] = (int)$_GET['user_id'];
        }

        $logs = $this->auditService->getLogs($filters, $perPage, $offset);
        $totalLogs = $this->auditService->getLogCount($filters);
        $totalPages = max(1, (int)ceil($totalLogs / $perPage));

        $pageTitle = 'Denetim İzi (Audit Logs)';
        $activePage = 'audit-logs';

        require __DIR__ . '/../../views/audit/index.php';
    }
}

