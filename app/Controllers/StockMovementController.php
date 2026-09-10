<?php

require_once __DIR__ . '/../Models/StockMovement.php';

class StockMovementController
{
    private StockMovement $stockMovement;

    public function __construct(PDO $pdo)
    {
        $this->stockMovement = new StockMovement($pdo);
    }

    public function index(): void
    {
        $startDate = trim($_GET['start_date'] ?? '');
        $endDate = trim($_GET['end_date'] ?? '');

        if ($startDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = '';
        }

        if ($endDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $endDate = '';
        }

        $materialId = !empty($_GET['material_id']) ? (int) $_GET['material_id'] : null;
        if ($materialId !== null && $materialId <= 0) {
            $materialId = null;
        }

        $warehouseId = !empty($_GET['warehouse_id']) ? (int) $_GET['warehouse_id'] : null;
        if ($warehouseId !== null && $warehouseId <= 0) {
            $warehouseId = null;
        }

        $locationId = !empty($_GET['location_id']) ? (int) $_GET['location_id'] : null;
        if ($locationId !== null && $locationId <= 0) {
            $locationId = null;
        }

        $allowedMovementTypes = ['IN', 'OUT', 'TRANSFER_IN', 'TRANSFER_OUT', 'RETURN', 'ADJUSTMENT'];
        $movementType = trim($_GET['movement_type'] ?? '');
        if (!in_array($movementType, $allowedMovementTypes, true)) {
            $movementType = '';
        }

        $userId = !empty($_GET['user_id']) ? (int) $_GET['user_id'] : null;
        if ($userId !== null && $userId <= 0) {
            $userId = null;
        }

        $search = trim($_GET['search'] ?? '');
        if (mb_strlen($search) > 100) {
            $search = mb_substr($search, 0, 100);
        }

        $filters = [
            'start_date'    => $startDate !== '' ? $startDate : null,
            'end_date'      => $endDate !== '' ? $endDate : null,
            'material_id'   => $materialId,
            'warehouse_id'  => $warehouseId,
            'location_id'   => $locationId,
            'movement_type' => $movementType !== '' ? $movementType : null,
            'user_id'       => $userId,
            'search'        => $search !== '' ? $search : null,
        ];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 15;

        $totalMovements = $this->stockMovement->getFilteredCount($filters);
        $totalPages = max(1, (int) ceil($totalMovements / $perPage));

        if ($page > $totalPages && $totalMovements > 0) {
            $page = $totalPages;
        }

        $movements = $this->stockMovement->getFiltered($filters, $page, $perPage);
        $filterOptions = $this->stockMovement->getFilterOptions();
        $summaryStats = $this->stockMovement->getSummaryStats($filters);
        $currentPage = $page;

        require __DIR__ . '/../../views/stock-movements/index.php';
    }
}
