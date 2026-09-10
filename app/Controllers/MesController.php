<?php

require_once __DIR__ . '/../Models/Mes.php';
require_once __DIR__ . '/../Models/Recipe.php';
require_once __DIR__ . '/../Models/Material.php';
require_once __DIR__ . '/../Services/MesProductionIntegrationService.php';
require_once __DIR__ . '/../Services/MesEventIngestionService.php';
require_once __DIR__ . '/../Services/MesSimulationService.php';
require_once __DIR__ . '/../Services/MesBomConsumptionService.php';
require_once __DIR__ . '/../Services/MesCostService.php';

class MesController
{
    private PDO $pdo;
    private Mes $mesModel;
    private Recipe $recipeModel;
    private Material $materialModel;
    private MesProductionIntegrationService $integrationService;
    private MesEventIngestionService $ingestionService;
    private MesSimulationService $simulationService;
    private MesBomConsumptionService $bomService;
    private MesCostService $costService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->mesModel = new Mes($this->pdo);
        $this->recipeModel = new Recipe($this->pdo);
        $this->materialModel = new Material($this->pdo);
        $this->integrationService = new MesProductionIntegrationService($this->pdo);
        $this->ingestionService = new MesEventIngestionService($this->pdo);
        $this->simulationService = new MesSimulationService($this->pdo);
        $this->bomService = new MesBomConsumptionService($this->pdo);
        $this->costService = new MesCostService($this->pdo);
    }

    /**
     * Display list of work orders and MES KPIs.
     */
    public function index(): void
    {
        $status = $_GET['status'] ?? null;
        $search = trim($_GET['search'] ?? '');
        $workOrders = $this->mesModel->getWorkOrders($status, $search !== '' ? $search : null);
        $stats = $this->mesModel->getWorkOrderStats();
        $productionLines = $this->mesModel->getProductionLinesWithStatus();

        $pageTitle = 'İş Emirleri (MES)';
        $activePage = 'mes';

        require __DIR__ . '/../../views/mes/index.php';
    }

    /**
     * Show create work order form.
     */
    public function createWorkOrder(): void
    {
        // Get active finished good materials (Mamuller / Reçetesi olan ürünler)
        $materials = $this->materialModel->getFinishedGoodsForProduction();

        // Get production lines
        $lines = $this->mesModel->getActiveProductionLines();

        // Suggested Work Order Number
        $suggestedNo = 'WO-' . date('Y') . '-' . str_pad((string)(($this->mesModel->getWorkOrderStats()['total_count'] ?? 0) + 1), 5, '0', STR_PAD_LEFT);

        $pageTitle = 'Yeni İş Emri Oluştur';
        $activePage = 'mes';

        require __DIR__ . '/../../views/mes/create.php';
    }

    /**
     * Store a new work order.
     */
    public function storeWorkOrder(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /stok-takip/public/mes');
            exit;
        }

        $workOrderNo = trim($_POST['work_order_no'] ?? '');
        $productMaterialId = (int)($_POST['product_material_id'] ?? 0);
        $productionLineId = (int)($_POST['production_line_id'] ?? 0);
        $plannedQuantity = (float)($_POST['planned_quantity'] ?? 0);
        $intervalSeconds = (int)($_POST['interval_seconds'] ?? 180);
        if ($intervalSeconds < 1) $intervalSeconds = 180;

        // Auto-find active recipe for this output product
        $recipeId = (int)($_POST['recipe_id'] ?? 0);
        if ($recipeId <= 0 && $productMaterialId > 0) {
            $recipeId = $this->recipeModel->findActiveRecipeByProduct($productMaterialId) ?? 3;
        }

        if ($workOrderNo === '' || $productMaterialId <= 0 || $recipeId <= 0 || $productionLineId <= 0 || $plannedQuantity <= 0) {
            $_SESSION['error'] = 'Lütfen tüm zorunlu alanları eksiksiz doldurun.';
            header('Location: /stok-takip/public/mes/work-orders/create');
            exit;
        }

        try {
            // New work orders strictly start in PLANNED status
            $status = 'PLANNED';
            $plannedStartAt = date('Y-m-d H:i:s');

            $id = $this->mesModel->createWorkOrder([
                'work_order_no'        => $workOrderNo,
                'product_material_id'  => $productMaterialId,
                'recipe_id'            => $recipeId,
                'production_line_id'   => $productionLineId,
                'planned_quantity'     => $plannedQuantity,
                'status'               => $status,
                'planned_start_at'     => $plannedStartAt,
                'planned_end_at'       => null,
            ]);

            // Initialize simulation entry in idle (is_active = 0) state
            $this->simulationService->getOrCreateSimulation($id);
            $stmtUpSim = $this->pdo->prepare("
                UPDATE mes_simulations 
                SET interval_seconds = ?, is_active = 0, paused_remaining_seconds = ?, updated_at = NOW() 
                WHERE work_order_id = ?
            ");
            $stmtUpSim->execute([$intervalSeconds, $intervalSeconds, $id]);

            $_SESSION['success'] = "İş emri {$workOrderNo} başarıyla oluşturuldu (Durum: Planlandı).";
            if (!headers_sent()) {
                header('Location: /stok-takip/public/mes/work-orders/show?id=' . $id);
                exit;
            }
            return;

        } catch (Throwable $e) {
            $_SESSION['error'] = 'İş emri oluşturulamadı: ' . $e->getMessage();
            if (!headers_sent()) {
                header('Location: /stok-takip/public/mes/work-orders/create');
                exit;
            }
            return;
        }
    }

    /**
     * Update work order lifecycle status (AJAX/POST).
     */
    public function updateWorkOrderStatus(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Geçersiz istek yöntemi.']);
            return;
        }

        $id = (int)($_POST['work_order_id'] ?? 0);
        $newStatus = strtoupper(trim($_POST['status'] ?? ''));

        $allowed = ['PLANNED', 'READY', 'RUNNING', 'PAUSED', 'FAILED', 'COMPLETED', 'CANCELLED'];
        if ($id <= 0 || !in_array($newStatus, $allowed, true)) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz iş emri ID veya durum.']);
            return;
        }

        $wo = $this->mesModel->getWorkOrderById($id);
        if (!$wo) {
            echo json_encode(['success' => false, 'message' => 'İş emri bulunamadı.']);
            return;
        }

        $plannedQty = (float)$wo['planned_quantity'];
        $producedQty = (float)$wo['produced_quantity'];
        $isCompleted = ($wo['status'] === 'COMPLETED' || ($producedQty >= $plannedQty && $plannedQty > 0));

        // 1. Block retrying or re-running COMPLETED work order
        if ($isCompleted && in_array($newStatus, ['RUNNING', 'READY', 'PLANNED'], true)) {
            echo json_encode([
                'success' => false,
                'code'    => 'WORK_ORDER_COMPLETED',
                'message' => 'Bu iş emri hedefine ulaşmış ve tamamlanmıştır (COMPLETED). Yeniden üretime sokulamaz. Yeni üretim için yeni iş emri oluşturunuz.'
            ]);
            return;
        }

        // 2. If work order was FAILED and user is retrying (READY or RUNNING):
        if ($wo['status'] === 'FAILED' && in_array($newStatus, ['READY', 'RUNNING'], true)) {
            $stmtResetSim = $this->pdo->prepare("
                UPDATE mes_simulations 
                SET last_error = NULL, last_error_code = NULL, retry_count = 0, next_retry_at = NULL, last_status = 'PAUSED', updated_at = NOW() 
                WHERE work_order_id = ?
            ");
            $stmtResetSim->execute([$id]);

            $this->mesModel->logEvent(
                'SIM-RETRY-' . $id,
                'SIMULATION_RETRY',
                "↻ Üretim yeniden başlatıldı. İş emri durumu {$newStatus} olarak kuyruğa alındı."
            );
        }

        // Side-effects on simulation engine & line validation
        if ($newStatus === 'RUNNING') {
            $lineCheck = $this->mesModel->canStartWorkOrderOnLine((int)$wo['production_line_id'], $id);
            if (!$lineCheck['allowed']) {
                echo json_encode([
                    'success' => false,
                    'code'    => $lineCheck['code'],
                    'message' => $lineCheck['reason']
                ]);
                return;
            }

            $simRes = $this->simulationService->startSimulation($id);
            if (!empty($simRes['status']) && $simRes['status'] === 'LINE_NOT_AVAILABLE') {
                echo json_encode([
                    'success' => false,
                    'code'    => $simRes['code'] ?? 'LINE_NOT_AVAILABLE',
                    'message' => $simRes['message'] ?? 'Hat üretime uygun değil.'
                ]);
                return;
            }
        } elseif ($newStatus === 'PAUSED' || $newStatus === 'READY' || $newStatus === 'PLANNED') {
            $this->simulationService->pauseSimulation($id, "Durum {$newStatus} olarak güncellendi.");
        }

        $this->mesModel->updateWorkOrderStatus($id, $newStatus);

        echo json_encode([
            'success' => true,
            'status' => $newStatus,
            'message' => "İş emri durumu {$newStatus} olarak güncellendi.",
            'telemetry' => $this->simulationService->getStatus($id)
        ]);
        return;
    }

    /**
     * Show single work order details.
     */
    public function showWorkOrder(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $workOrder = $this->mesModel->getWorkOrderById($id);

        if (!$workOrder) {
            $_SESSION['error'] = 'İş emri bulunamadı.';
            header('Location: /stok-takip/public/mes');
            exit;
        }

        $events = $this->mesModel->getEventsByWorkOrder($id, 50);
        $simStatus = $this->simulationService->getStatus($id);
        $recipe = $this->recipeModel->getById((int)$workOrder['recipe_id']);
        $bomConsumption = $this->bomService->getWorkOrderBomConsumption($id);
        $costSummary = $this->costService->getWorkOrderCostSummary($id);

        $latestAction = $simStatus['latest_action_summary'] ?? [
            'level'    => 'INFO',
            'icon'     => '⚡',
            'title'    => 'İş Emri Hazır',
            'subtitle' => 'Üretim başlatılmaya hazır bekliyor.',
            'time'     => date('H:i:s'),
            'bg'       => '#f8fafc',
            'color'    => '#475569',
            'border'   => '#e2e8f0'
        ];
        $formattedEvents = $simStatus['recent_events_human'] ?? [];
        $telemetry = $simStatus;
        $telemetry['cost_summary'] = $costSummary;

        $pageTitle = 'İş Emri Detayı: ' . $workOrder['work_order_no'];
        $activePage = 'mes';

        require __DIR__ . '/../../views/mes/show.php';
    }

    /**
     * API: Get BOM consumption breakdown for a work order.
     */
    public function apiWorkOrderConsumption(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $id = (int)($_GET['id'] ?? ($_GET['work_order_id'] ?? 0));
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz İş Emri ID']);
            return;
        }

        $consumption = $this->bomService->getWorkOrderBomConsumption($id);
        echo json_encode($consumption);
    }

    /**
     * API: Get itemized BOM consumption for a single MES event.
     */
    public function apiEventConsumption(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $eventId = trim($_GET['event_id'] ?? ($_GET['id'] ?? ''));
        if ($eventId === '') {
            echo json_encode(['success' => false, 'message' => 'Geçersiz Event ID']);
            return;
        }

        $consumption = $this->bomService->getEventBomConsumption($eventId);
        echo json_encode($consumption);
    }

    /**
     * API: Get full traceability chain for a work order.
     */
    public function apiWorkOrderTraceability(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $id = (int)($_GET['id'] ?? ($_GET['work_order_id'] ?? 0));
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz İş Emri ID']);
            return;
        }

        $chain = $this->bomService->getWorkOrderTraceabilityChain($id);
        echo json_encode($chain);
    }

    /**
     * API: Get cost summary for a work order (GET /api/mes/work-order/cost).
     */
    public function apiWorkOrderCost(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $id = (int)($_GET['id'] ?? ($_GET['work_order_id'] ?? 0));
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz İş Emri ID']);
            return;
        }

        $cost = $this->costService->getWorkOrderCostSummary($id);
        echo json_encode($cost);
    }

    /**
     * API: Get recipe theoretical unit cost (GET /api/mes/recipe/cost).
     */
    public function apiRecipeCost(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $id = (int)($_GET['id'] ?? ($_GET['recipe_id'] ?? 0));
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz Reçete ID']);
            return;
        }

        $cost = $this->costService->getRecipeTheoreticalCost($id);
        echo json_encode($cost);
    }

    /**
     * API: Get snapshot cost details for a single MES event (GET /api/mes/event/cost).
     */
    public function apiEventCost(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $eventId = trim($_GET['event_id'] ?? ($_GET['id'] ?? ''));
        if ($eventId === '') {
            echo json_encode(['success' => false, 'message' => 'Geçersiz Event ID']);
            return;
        }

        $cost = $this->costService->getEventCostDetails($eventId);
        echo json_encode($cost);
    }

    /**
     * MES Simulator Page.
     */
    public function simulator(): void
    {
        $workOrders = $this->mesModel->getWorkOrders();
        $selectedWoId = (int)($_GET['wo_id'] ?? ($workOrders[0]['id'] ?? 0));
        
        $telemetry = $selectedWoId > 0 ? $this->simulationService->getStatus($selectedWoId) : [];
        $recentEvents = $this->mesModel->getRecentEvents(25);
        $stats = $this->mesModel->getWorkOrderStats();

        $pageTitle = 'MES Simülatörü';
        $activePage = 'mes-simulator';

        require __DIR__ . '/../../views/mes/simulator.php';
    }

    /**
     * Start / Resume automated simulation (AJAX/POST).
     */
    public function startSimulation(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        $workOrderId = (int)($_POST['work_order_id'] ?? 0);
        $interval = !empty($_POST['interval_seconds']) ? (int)$_POST['interval_seconds'] : null;
        $targetQty = !empty($_POST['planned_quantity']) ? (float)$_POST['planned_quantity'] : null;

        if ($workOrderId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz iş emri ID.']);
            return;
        }

        require_once __DIR__ . '/../Services/MesWorkerService.php';
        $result = $this->simulationService->startSimulation($workOrderId, $interval, $targetQty);

        // Ensure background worker daemon is alive on the OS
        MesWorkerService::ensureWorkerRunning();

        echo json_encode($result);
        return;
    }

    /**
     * Pause automated simulation (AJAX/POST).
     */
    public function pauseSimulation(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        $workOrderId = (int)($_POST['work_order_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Kullanıcı duraklattı');

        if ($workOrderId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz iş emri ID.']);
            return;
        }

        $result = $this->simulationService->pauseSimulation($workOrderId, $reason);
        echo json_encode($result);
        return;
    }

    /**
     * Simulation tick / step endpoint (AJAX/POST).
     */
    public function tickSimulation(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        $workOrderId = (int)($_POST['work_order_id'] ?? 0);
        $forceStep = !empty($_POST['force_step']);

        if ($workOrderId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz iş emri ID.']);
            return;
        }

        $result = $this->simulationService->processTick($workOrderId, $forceStep);
        echo json_encode($result);
        return;
    }

    /**
     * Get simulation telemetry & status (AJAX/GET).
     */
    public function getSimulationStatus(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        $workOrderId = (int)($_GET['work_order_id'] ?? ($_GET['wo_id'] ?? 0));
        if ($workOrderId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz iş emri ID.']);
            return;
        }

        // Process any due ticks for this work order (if active and next_run_at <= NOW())
        $this->simulationService->runDueSimulations(5, $workOrderId);

        $telemetry = $this->simulationService->getStatus($workOrderId);
        $lines = $this->mesModel->getProductionLinesWithStatus();
        echo json_encode(array_merge([
            'success'               => true,
            'telemetry'             => $telemetry,
            'recent_events_human'   => $telemetry['recent_events_human'] ?? [],
            'latest_action_summary' => $telemetry['latest_action_summary'] ?? [],
            'production_lines'      => $lines
        ], $telemetry));
        return;
    }

    /**
     * Get real-time production lines status (AJAX/GET).
     */
    public function apiProductionLines(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        $lines = $this->mesModel->getProductionLinesWithStatus();
        echo json_encode(['success' => true, 'production_lines' => $lines]);
        return;
    }

    /**
     * Update production line operational status (AJAX/POST).
     */
    public function updateLineStatus(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Geçersiz istek yöntemi.']);
            return;
        }

        $lineId = (int)($_POST['line_id'] ?? 0);
        $status = strtoupper(trim($_POST['status'] ?? ''));
        $note = trim($_POST['status_note'] ?? '');

        if ($lineId <= 0 || !in_array($status, ['IDLE', 'RUNNING', 'MAINTENANCE', 'FAULT'], true)) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz hat ID veya durum.']);
            return;
        }

        // If line is put into MAINTENANCE or FAULT, pause any active simulations on that line
        if ($status === 'MAINTENANCE' || $status === 'FAULT') {
            $stmtActive = $this->pdo->prepare("
                SELECT wo.id, wo.work_order_no 
                FROM mes_work_orders wo 
                JOIN mes_simulations s ON s.work_order_id = wo.id
                WHERE wo.production_line_id = ? AND wo.status = 'RUNNING' AND s.is_active = 1
            ");
            $stmtActive->execute([$lineId]);
            $activeWos = $stmtActive->fetchAll(PDO::FETCH_ASSOC);

            foreach ($activeWos as $awo) {
                $woId = (int)$awo['id'];
                $pauseReason = ($status === 'MAINTENANCE') ? 'Hat bakıma alındı.' : 'Hat arızaya geçti: ' . ($note ?: 'Arıza');
                $this->simulationService->pauseSimulation($woId, $pauseReason);
                $this->mesModel->updateWorkOrderStatus($woId, 'PAUSED');
                $this->mesModel->logEvent(
                    'LINE-STAT-' . $lineId,
                    'LINE_STATUS_CHANGED_' . $status,
                    sprintf('Hat durumu %s olarak güncellendi. İş emri %s duraklatıldı.', $status, $awo['work_order_no'])
                );
            }
        }

        $ok = $this->mesModel->updateProductionLineStatus($lineId, $status, $note ?: null);
        echo json_encode([
            'success'          => $ok,
            'message'          => "Hat durumu {$status} olarak güncellendi.",
            'production_lines' => $this->mesModel->getProductionLinesWithStatus()
        ]);
        return;
    }

    /**
     * Standart MES API JSON Ingestion Endpoint (POST /api/mes/events)
     */
    public function apiIngest(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);

        if (!is_array($data) || empty($data)) {
            $data = $_POST;
        }

        if (empty($data)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'status'  => 'BAD_REQUEST',
                'message' => 'Geçersiz veya boş JSON MES yükü.'
            ]);
            return;
        }

        $result = $this->ingestionService->ingestEvent($data);

        if (!empty($result['success'])) {
            http_response_code(200);
        } elseif (($result['status'] ?? '') === 'WORK_ORDER_COMPLETED' || ($result['status'] ?? '') === 'PLANNED_QUANTITY_EXCEEDED') {
            http_response_code(422);
        } else {
            http_response_code(400);
        }

        echo json_encode($result);
        return;
    }

    /**
     * Simulator produce action (POST /mes/simulator/produce)
     */
    public function simulateProductionEvent(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        $workOrderId = (int)($_POST['work_order_id'] ?? 0);
        $qty = (float)($_POST['quantity'] ?? 1.0);
        $customEventId = trim($_POST['event_id'] ?? '');

        if ($workOrderId <= 0 || $qty <= 0) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz parametreler.']);
            return;
        }

        $wo = $this->mesModel->getWorkOrderById($workOrderId);
        if (!$wo) {
            echo json_encode(['success' => false, 'message' => 'İş emri bulunamadı.']);
            return;
        }

        $lineClean = preg_replace('/[^A-Za-z0-9]/', '', $wo['line_code'] ?: 'LAM1');
        $eventId = $customEventId !== '' ? $customEventId : sprintf('MES-%s-%s-%06d-%s', date('Ymd'), $lineClean, (int)$wo['produced_quantity'] + 1, strtoupper(bin2hex(random_bytes(2))));

        $payload = [
            'event_id'            => $eventId,
            'event_type'          => 'PANEL_COMPLETED',
            'product_code'        => $wo['product_code'],
            'product_material_id' => (int)$wo['product_material_id'],
            'quantity'            => $qty,
            'production_line'     => $wo['line_code'],
            'production_line_id'  => (int)$wo['production_line_id'],
            'work_order_no'       => $wo['work_order_no'],
            'work_order_id'       => (int)$wo['id'],
            'event_time'          => date('Y-m-d H:i:s'),
            'source'              => 'SIMULATOR'
        ];

        $result = $this->ingestionService->ingestEvent($payload);
        $telemetry = $this->simulationService->getStatus($workOrderId);
        $result['telemetry'] = $telemetry;

        echo json_encode($result);
        return;
    }
}
