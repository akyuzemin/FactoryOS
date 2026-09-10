<?php

require_once __DIR__ . '/../Services/PermissionService.php';

class PortalController
{
    private PDO $pdo;
    private PermissionService $permissionService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->permissionService = new PermissionService($pdo);
    }

    public function index(): void
    {
        $roleId = (int) ($_SESSION['role_id'] ?? 0);
        $username = (string) ($_SESSION['username'] ?? '');

        $hasStockView = $roleId > 0 && $this->permissionService->hasPermission($roleId, 'stock.view');

        require __DIR__ . '/../../views/portal/index.php';
    }
}
