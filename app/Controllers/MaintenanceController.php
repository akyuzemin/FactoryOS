<?php

require_once __DIR__ . '/../Services/MaintenanceService.php';
require_once __DIR__ . '/../Services/CsrfService.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Models/Material.php';

class MaintenanceController
{
    private PDO $pdo;
    private MaintenanceService $maintenanceService;
    private User $userModel;
    private Material $materialModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->maintenanceService = new MaintenanceService($pdo);
        $this->userModel = new User($pdo);
        $this->materialModel = new Material($pdo);
    }

    /**
     * Bakım & TPM Ana Dashboard (GET /maintenance)
     */
    public function index(): void
    {
        $summary = $this->maintenanceService->getDashboardSummary();
        $assets = $this->maintenanceService->getAssets();
        $plans = $this->maintenanceService->getPreventivePlans();

        // Açık İş Emirleri
        $stmtWo = $this->pdo->query("
            SELECT 
                mwo.*,
                a.asset_code,
                a.asset_name,
                pl.code AS line_code,
                pl.name AS line_name,
                u_rep.username AS reporter_name,
                u_ass.username AS technician_name
            FROM maintenance_work_orders mwo
            JOIN maintenance_assets a ON mwo.asset_id = a.id
            JOIN production_lines pl ON a.production_line_id = pl.id
            JOIN users u_rep ON mwo.reported_by_user_id = u_rep.id
            LEFT JOIN users u_ass ON mwo.assigned_user_id = u_ass.id
            ORDER BY 
                CASE mwo.priority 
                    WHEN 'CRITICAL' THEN 1 
                    WHEN 'HIGH' THEN 2 
                    WHEN 'MEDIUM' THEN 3 
                    ELSE 4 
                END,
                mwo.id DESC
            LIMIT 50
        ");
        $workOrders = $stmtWo->fetchAll(PDO::FETCH_ASSOC);

        // Teknisyen / Kullanıcı Listesi (Görev atama için)
        $technicians = $this->userModel->getActiveTechnicians();

        // Yedek Parça Listesi (Stok kartları)
        $spareParts = $this->materialModel->getSpareParts();

        $pageTitle = 'Bakım Yönetimi & TPM';
        $activePage = 'maintenance';

        require __DIR__ . '/../../views/maintenance/index.php';
    }

    /**
     * Ekipman Detayı & Bakım Pasaportu (GET /maintenance/asset?id=...)
     */
    public function assetShow(): void
    {
        $assetId = (int)($_GET['id'] ?? 0);
        if ($assetId <= 0) {
            header('Location: /stok-takip/public/maintenance');
            exit;
        }

        $history = $this->maintenanceService->getAssetHistory($assetId);
        if (empty($history)) {
            http_response_code(404);
            echo "Ekipman bulunamadı.";
            exit;
        }

        $asset = $history['asset'];
        $kpi = $history['kpi'];
        $workOrders = $history['work_orders'];
        $consumedParts = $history['consumed_parts'];

        $pageTitle = 'Ekipman Bakım Pasaportu: ' . $asset['asset_name'];
        $activePage = 'maintenance';

        require __DIR__ . '/../../views/maintenance/asset_show.php';
    }

    /**
     * Bakım İş Emri Detayı (GET /maintenance/work-order?id=...)
     */
    public function show(): void
    {
        $woId = (int)($_GET['id'] ?? 0);
        if ($woId <= 0) {
            header('Location: /stok-takip/public/maintenance');
            exit;
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                mwo.*,
                a.asset_code,
                a.asset_name,
                a.manufacturer,
                a.model_no,
                pl.id AS line_id,
                pl.code AS line_code,
                pl.name AS line_name,
                u_rep.username AS reporter_name,
                u_ass.username AS technician_name,
                u_ver.username AS verifier_name
            FROM maintenance_work_orders mwo
            JOIN maintenance_assets a ON mwo.asset_id = a.id
            JOIN production_lines pl ON a.production_line_id = pl.id
            JOIN users u_rep ON mwo.reported_by_user_id = u_rep.id
            LEFT JOIN users u_ass ON mwo.assigned_user_id = u_ass.id
            LEFT JOIN users u_ver ON mwo.verified_by_user_id = u_ver.id
            WHERE mwo.id = ?
        ");
        $stmt->execute([$woId]);
        $workOrder = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$workOrder) {
            http_response_code(404);
            echo "Bakım iş emri bulunamadı.";
            exit;
        }

        // Harcanan Parçalar
        $stmtParts = $this->pdo->prepare("
            SELECT 
                msp.*,
                m.code AS material_code,
                m.name AS material_name,
                sm.reference_no AS movement_ref
            FROM maintenance_spare_parts msp
            JOIN materials m ON msp.material_id = m.id
            LEFT JOIN stock_movements sm ON msp.stock_movement_id = sm.id
            WHERE msp.maintenance_work_order_id = ?
            ORDER BY msp.id ASC
        ");
        $stmtParts->execute([$woId]);
        $spareParts = $stmtParts->fetchAll(PDO::FETCH_ASSOC);

        // Kullanılabilir Yedek Parçalar
        $availableMaterials = $this->materialModel->getSpareParts();

        // Teknisyen Listesi
        $technicians = $this->userModel->getActiveTechnicians();

        $pageTitle = 'Bakım İş Emri: ' . $workOrder['work_order_no'];
        $activePage = 'maintenance';

        require __DIR__ . '/../../views/maintenance/show.php';
    }

    /**
     * Yeni Bakım İş Emri Açma (POST /maintenance/create)
     */
    public function create(): void
    {
        CsrfService::validateOrAbort();
        $userId = (int)($_SESSION['user_id'] ?? 1);

        $res = $this->maintenanceService->createWorkOrder($_POST, $userId);

        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($res['success']) {
            $_SESSION['success'] = $res['message'];
        } else {
            $_SESSION['error'] = $res['message'];
        }

        header('Location: /stok-takip/public/maintenance');
        exit;
    }

    /**
     * Teknisyen Atama (POST /maintenance/assign)
     */
    public function assign(): void
    {
        CsrfService::validateOrAbort();
        $userId = (int)($_SESSION['user_id'] ?? 1);

        $woId = (int)($_POST['work_order_id'] ?? 0);
        $techId = (int)($_POST['technician_user_id'] ?? 0);

        $res = $this->maintenanceService->assignTechnician($woId, $techId, $userId);

        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            return;
        }

        header("Location: /stok-takip/public/maintenance/work-order?id={$woId}");
        exit;
    }

    /**
     * İşe Başlama (POST /maintenance/start)
     */
    public function startWork(): void
    {
        CsrfService::validateOrAbort();
        $userId = (int)($_SESSION['user_id'] ?? 1);
        $woId = (int)($_POST['work_order_id'] ?? 0);

        $res = $this->maintenanceService->startWork($woId, $userId);

        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            return;
        }

        header("Location: /stok-takip/public/maintenance/work-order?id={$woId}");
        exit;
    }

    /**
     * Yedek Parça Tüketimi (POST /maintenance/spare-part/consume)
     */
    public function consumeSparePart(): void
    {
        CsrfService::validateOrAbort();
        $userId = (int)($_SESSION['user_id'] ?? 1);

        $woId = (int)($_POST['work_order_id'] ?? 0);
        $materialId = (int)($_POST['material_id'] ?? 0);
        $quantity = (float)($_POST['quantity'] ?? 1.0);

        $res = $this->maintenanceService->consumeSparePart($woId, $materialId, $quantity, $userId);

        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($res['success']) {
            $_SESSION['success'] = $res['message'];
        } else {
            $_SESSION['error'] = $res['message'];
        }

        header("Location: /stok-takip/public/maintenance/work-order?id={$woId}");
        exit;
    }

    /**
     * Bakım Tamamlama (POST /maintenance/complete)
     */
    public function complete(): void
    {
        CsrfService::validateOrAbort();
        $userId = (int)($_SESSION['user_id'] ?? 1);
        $woId = (int)($_POST['work_order_id'] ?? 0);

        $res = $this->maintenanceService->completeWork($woId, $_POST, $userId);

        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($res['success']) {
            $_SESSION['success'] = $res['message'];
        } else {
            $_SESSION['error'] = $res['message'];
        }

        header("Location: /stok-takip/public/maintenance/work-order?id={$woId}");
        exit;
    }

    /**
     * Bakım Doğrulama & Devreye Alma (POST /maintenance/verify)
     */
    public function verify(): void
    {
        CsrfService::validateOrAbort();
        $userId = (int)($_SESSION['user_id'] ?? 1);
        $woId = (int)($_POST['work_order_id'] ?? 0);

        $res = $this->maintenanceService->verifyWork($woId, $userId, $_POST['notes'] ?? null);

        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($res['success']) {
            $_SESSION['success'] = $res['message'];
        } else {
            $_SESSION['error'] = $res['message'];
        }

        header("Location: /stok-takip/public/maintenance/work-order?id={$woId}");
        exit;
    }

    /**
     * Canlı Telemetri API (GET /api/maintenance/live)
     */
    public function liveApi(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $summary = $this->maintenanceService->getDashboardSummary();
        echo json_encode(['success' => true, 'data' => $summary], JSON_UNESCAPED_UNICODE);
    }
}

