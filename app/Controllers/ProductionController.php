<?php

require_once __DIR__ . '/../Models/Production.php';
require_once __DIR__ . '/../Models/Recipe.php';
require_once __DIR__ . '/../Services/ProductionStockService.php';

class ProductionController
{
    private Production $productionModel;
    private Recipe $recipeModel;
    private ProductionStockService $stockService;

    public function __construct(PDO $pdo)
    {
        $this->productionModel = new Production($pdo);
        $this->recipeModel = new Recipe($pdo);
        $this->stockService = new ProductionStockService($pdo);
    }

    public function index(): void
    {
        $lineId = !empty($_GET['line_id']) ? (int)$_GET['line_id'] : null;
        $search = trim($_GET['search'] ?? '');

        $logs = $this->productionModel->getProductionList(50, $lineId, $search !== '' ? $search : null);
        $lines = $this->productionModel->getActiveLines();

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        require __DIR__ . '/../../views/production/index.php';
    }

    public function create(): void
    {
        $lines = $this->productionModel->getActiveLines();
        $shifts = $this->productionModel->getActiveShifts();
        $recipes = $this->productionModel->getActiveRecipes();
        $locations = $this->productionModel->getActiveLocations();

        // Varsayılan hedef lokasyon: Sevkiyat Deposu S-01 (id: 12)
        $defaultTargetLocId = 12;
        $hasTarget = false;
        foreach ($locations as $loc) {
            if ($loc['id'] == $defaultTargetLocId) {
                $hasTarget = true;
                break;
            }
        }
        if (!$hasTarget && !empty($locations)) {
            $defaultTargetLocId = $locations[0]['id'];
        }

        $error = null;
        $formData = [
            'line_id'             => $lines[0]['id'] ?? '',
            'shift_id'            => $shifts[0]['id'] ?? '',
            'log_date'            => date('Y-m-d'),
            'recipe_id'           => $recipes[0]['id'] ?? '',
            'source_location_id'  => $locations[0]['id'] ?? '',
            'target_location_id'  => $defaultTargetLocId,
            'panels_produced_qty' => '100',
            'scrap_panels_qty'    => '0',
            'notes'               => '',
        ];

        require __DIR__ . '/../../views/production/create.php';
    }

    /**
     * AJAX JSON Önizleme Endpoint'i
     */
    public function previewStock(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $recipeId = (int)($_POST['recipe_id'] ?? $_GET['recipe_id'] ?? 0);
        $qty = (float)($_POST['panels_produced_qty'] ?? $_GET['panels_produced_qty'] ?? 0);
        $sourceLocationId = (int)($_POST['source_location_id'] ?? $_POST['location_id'] ?? $_GET['source_location_id'] ?? $_GET['location_id'] ?? 0);
        $targetLocationId = (int)($_POST['target_location_id'] ?? $_GET['target_location_id'] ?? 12);

        if ($recipeId <= 0 || $qty <= 0 || $sourceLocationId <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Lütfen reçete, üretim miktarı ve hammadde kaynak deposu seçiniz.'
            ]);
            exit;
        }

        try {
            $analysis = $this->stockService->calculateStockNeeds($recipeId, $qty, $sourceLocationId, $targetLocationId);
            echo json_encode([
                'success' => true,
                'data'    => $analysis
            ]);
        } catch (Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * Üretimi Onaylama, Hammadde Çıkışı (OUT) ve Mamul Girişi (IN)
     */
    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /stok-takip/public/production/create');
            exit;
        }

        $userId = (int)($_SESSION['user_id'] ?? 1);

        $formData = [
            'line_id'             => (int)($_POST['line_id'] ?? 0),
            'shift_id'            => (int)($_POST['shift_id'] ?? 0),
            'log_date'            => trim((string)($_POST['log_date'] ?? date('Y-m-d'))),
            'recipe_id'           => (int)($_POST['recipe_id'] ?? 0),
            'source_location_id'  => (int)($_POST['source_location_id'] ?? $_POST['location_id'] ?? 0),
            'target_location_id'  => (int)($_POST['target_location_id'] ?? 12),
            'panels_produced_qty' => (int)($_POST['panels_produced_qty'] ?? 0),
            'scrap_panels_qty'    => (int)($_POST['scrap_panels_qty'] ?? 0),
            'total_wp_produced'   => (float)($_POST['total_wp_produced'] ?? 0),
            'notes'               => trim((string)($_POST['notes'] ?? '')),
        ];

        try {
            $result = $this->stockService->executeProduction($formData, $userId);

            $_SESSION['flash_success'] = sprintf(
                'Üretim başarıyla tamamlandı! %s adet %s (%s) stoğa giriş yaptı (IN) ve hammadde sarfiyatları düşüldü (OUT). Referans: %s',
                number_format($result['produced_quantity']),
                $result['output_material_name'],
                $result['output_material_code'],
                $result['reference_no']
            );

            header('Location: /stok-takip/public/production/show?id=' . $result['production_log_id']);
            exit;
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: /stok-takip/public/production/create');
            exit;
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Üretim kaydedilirken beklenmeyen bir hata oluştu: ' . $e->getMessage();
            header('Location: /stok-takip/public/production/create');
            exit;
        }
    }

    /**
     * Üretim Detayı, Mamul Girişi ve Hammadde Çıkış Fişi
     */
    public function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $production = $this->productionModel->getById($id);

        if (!$production) {
            $_SESSION['flash_error'] = 'Üretim kaydı bulunamadı.';
            header('Location: /stok-takip/public/production');
            exit;
        }

        $allMovements = $this->productionModel->getMovementsByRef($production['stock_movement_ref'] ?? '');

        $inMovements = [];
        $outMovements = [];

        foreach ($allMovements as $mv) {
            if ($mv['movement_type'] === 'IN') {
                $inMovements[] = $mv;
            } else {
                $outMovements[] = $mv;
            }
        }

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        require __DIR__ . '/../../views/production/show.php';
    }
}
