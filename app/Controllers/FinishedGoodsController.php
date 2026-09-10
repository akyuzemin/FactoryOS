<?php

require_once __DIR__ . '/../Models/PanelUnit.php';
require_once __DIR__ . '/../Models/Warehouse.php';
require_once __DIR__ . '/../Models/Mes.php';

class FinishedGoodsController
{
    private PDO $pdo;
    private PanelUnit $panelUnit;
    private Warehouse $warehouseModel;
    private Mes $mesModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->panelUnit = new PanelUnit($this->pdo);
        $this->warehouseModel = new Warehouse($this->pdo);
        $this->mesModel = new Mes($this->pdo);
    }

    /**
     * Panel Serileri ve Mamul Deposu Listesi (GET /finished-goods)
     */
    public function index(): void
    {
        $filters = [
            'serial_no'     => $_GET['serial_no'] ?? '',
            'work_order_id' => !empty($_GET['work_order_id']) ? (int)$_GET['work_order_id'] : null,
            'work_order_no' => $_GET['work_order_no'] ?? '',
            'status'        => $_GET['status'] ?? '',
            'warehouse_id'  => !empty($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : null,
            'material_id'   => !empty($_GET['material_id']) ? (int)$_GET['material_id'] : null,
            'date_from'     => $_GET['date_from'] ?? '',
            'date_to'       => $_GET['date_to'] ?? '',
        ];

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $totalCount = $this->panelUnit->countAll($filters);
        $totalPages = max(1, (int)ceil($totalCount / $limit));
        $panelUnits = $this->panelUnit->getAll($filters, $limit, $offset);

        $summaryStats = $this->panelUnit->getSummaryStats();
        $warehouses = $this->warehouseModel->getAllActive();
        $workOrders = $this->mesModel->getWorkOrders();

        $pageTitle = 'Panel Seri Takip & Mamul Deposu';

        require __DIR__ . '/../../views/finished-goods/index.php';
    }

    /**
      * Panel Detayı ve Pasaportu (GET /finished-goods/show?id=X veya ?serial=SP...)
      */
    public function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $serialNo = trim($_GET['serial'] ?? '');

        if ($id > 0) {
            $panel = $this->panelUnit->getById($id);
        } elseif ($serialNo !== '') {
            $panel = $this->panelUnit->getBySerial($serialNo);
        } else {
            $panel = null;
        }

        if (!$panel) {
            $_SESSION['error'] = 'Panel seri numarası bulunamadı.';
            if (!headers_sent()) {
                header('Location: /stok-takip/public/finished-goods');
                exit;
            }
            return;
        }

        $pageTitle = 'Panel Pasaportu: ' . $panel['serial_no'];

        $traceability = $this->panelUnit->getTraceabilityChain((int)$panel['id']);
        $bomDetails = $traceability['bom'] ?? [];
        $stockMovements = $traceability['stock_movements'] ?? [];

        // Sevkiyat geçmişi sorgusu
        $shipmentStmt = $this->pdo->prepare("
            SELECT s.*, si.created_at AS added_at
            FROM shipment_items si
            JOIN shipments s ON si.shipment_id = s.id
            WHERE si.panel_unit_id = :panel_unit_id AND s.status != 'CANCELLED'
            LIMIT 1
        ");
        $shipmentStmt->execute([':panel_unit_id' => (int)$panel['id']]);
        $shipmentInfo = $shipmentStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // QR Kod Üretimi
        require_once __DIR__ . '/../Services/QrCodeService.php';
        $qrService = new QrCodeService();
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $qrUrl = $protocol . '://' . $host . '/stok-takip/public/finished-goods/serial?serial_no=' . urlencode($panel['serial_no']);
        $qrSvg = $qrService->generateSvg($qrUrl, 160);
        // Enerji Maliyet Kırılımı (BOM + Enerji = Toplam İmalat Maliyeti)
        require_once __DIR__ . '/../Services/EnergyProductionLinkService.php';
        $energyLinkService = new EnergyProductionLinkService($this->pdo);
        $energyCostBreakdown = $energyLinkService->getPanelEnergyCostBreakdown($panel);

        require __DIR__ . '/../../views/finished-goods/show.php';
    }

    /**
     * Seri Numarasına Göre Yönlendirme (GET /finished-goods/serial?serial_no=X)
     */
    public function showBySerial(): void
    {
        $serialNo = trim($_GET['serial_no'] ?? ($_GET['serial'] ?? ''));
        if ($serialNo === '') {
            $_SESSION['error'] = 'Lütfen geçerli bir seri numarası giriniz.';
            if (!headers_sent()) {
                header('Location: /stok-takip/public/finished-goods');
                exit;
            }
            return;
        }

        $panel = $this->panelUnit->getBySerial($serialNo);
        if (!$panel) {
            $_SESSION['error'] = 'Seri numarasına ait panel bulunamadı: ' . htmlspecialchars($serialNo);
            if (!headers_sent()) {
                header('Location: /stok-takip/public/finished-goods');
                exit;
            }
            return;
        }

        if (!headers_sent()) {
            header('Location: /stok-takip/public/finished-goods/show?id=' . (int)$panel['id']);
            exit;
        }
    }

    /**
     * Kalite Kontrol Sonuçlarını Kaydet (POST /finished-goods/quality-control/save)
     */
    public function saveQualityControl(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error'] = 'Geçersiz istek yöntemi.';
            header('Location: /stok-takip/public/finished-goods');
            exit;
        }

        $panelId = (int)($_POST['panel_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $visualInspection = trim($_POST['visual_inspection'] ?? 'PENDING');
        $elTest = trim($_POST['el_test'] ?? 'PENDING');
        $flashTest = trim($_POST['flash_test'] ?? 'PENDING');
        $rejectionReason = trim($_POST['rejection_reason'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        // Validation
        if ($panelId <= 0) {
            $_SESSION['error'] = 'Geçersiz panel kimliği.';
            header('Location: /stok-takip/public/finished-goods');
            exit;
        }

        $panel = $this->panelUnit->getById($panelId);
        if (!$panel) {
            $_SESSION['error'] = 'Panel bulunamadı.';
            header('Location: /stok-takip/public/finished-goods');
            exit;
        }

        $allowedStatuses = ['QUALITY_PENDING', 'QUALITY_APPROVED', 'QUALITY_REJECTED'];
        if (!in_array($status, $allowedStatuses, true)) {
            $_SESSION['error'] = 'Geçersiz kalite durumu seçildi.';
            header('Location: /stok-takip/public/finished-goods/show?id=' . $panelId);
            exit;
        }

        if ($status === 'QUALITY_REJECTED' && empty($rejectionReason)) {
            $_SESSION['error'] = 'Panel reddedildiğinde, red nedenini belirtmek zorunludur.';
            header('Location: /stok-takip/public/finished-goods/show?id=' . $panelId);
            exit;
        }

        // Prepare JSON payload
        $checkedBy = $_SESSION['username'] ?? 'Sistem';
        $checkedAt = date('Y-m-d H:i:s');

        $qualityData = [
            'status'            => $status,
            'visual_inspection' => $visualInspection,
            'el_test'           => $elTest,
            'flash_test'        => $flashTest,
            'rejection_reason'  => $status === 'QUALITY_REJECTED' ? $rejectionReason : null,
            'notes'             => $notes !== '' ? $notes : null,
            'checked_by'        => $checkedBy,
            'checked_at'        => $checkedAt
        ];

        $jsonNotes = json_encode($qualityData, JSON_UNESCAPED_UNICODE);

        try {
            $stmt = $this->pdo->prepare("
                UPDATE panel_units 
                SET status = :status, quality_notes = :quality_notes, updated_at = NOW() 
                WHERE id = :id
            ");
            $stmt->execute([
                ':status'        => $status,
                ':quality_notes' => $jsonNotes,
                ':id'            => $panelId
            ]);

            $_SESSION['success'] = 'Panel kalite kontrol sonuçları başarıyla kaydedildi.';
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Veritabanı güncelleme hatası: ' . $e->getMessage();
        }

        header('Location: /stok-takip/public/finished-goods/show?id=' . $panelId);
        exit;
    }

    /**
     * Canlı Panel Güncellemeleri API Endpoint (GET /finished-goods/live)
     */
    public function live(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        $lastId = (int)($_GET['last_id'] ?? 0);
        $filters = [
            'serial_no'     => $_GET['serial_no'] ?? '',
            'work_order_id' => !empty($_GET['work_order_id']) ? (int)$_GET['work_order_id'] : null,
            'work_order_no' => $_GET['work_order_no'] ?? '',
            'status'        => $_GET['status'] ?? '',
            'warehouse_id'  => !empty($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : null,
            'material_id'   => !empty($_GET['material_id']) ? (int)$_GET['material_id'] : null,
            'date_from'     => $_GET['date_from'] ?? '',
            'date_to'       => $_GET['date_to'] ?? '',
        ];

        $newPanels = [];
        if ($lastId > 0) {
            $newPanels = $this->panelUnit->getNewPanelsSince($lastId, $filters, 50);
        }

        $formattedItems = [];
        foreach ($newPanels as $pu) {
            $formattedItems[] = [
                'id'             => (int)$pu['id'],
                'serial_no'      => $pu['serial_no'],
                'material_name'  => $pu['material_name'],
                'material_code'  => $pu['material_code'],
                'work_order_id'  => (int)($pu['work_order_id'] ?? 0),
                'work_order_no'  => $pu['work_order_no'] ?? '-',
                'line_name'      => $pu['line_name'] ?? '-',
                'warehouse_name' => $pu['warehouse_name'] ?? '-',
                'location_name'  => $pu['location_name'] ?? '-',
                'produced_at'    => date('d.m.Y H:i:s', strtotime($pu['produced_at'])),
                'status'         => $pu['status'],
                'show_url'       => '/stok-takip/public/finished-goods/show?id=' . (int)$pu['id'],
                'qr_url'         => '/stok-takip/public/finished-goods/serial?serial_no=' . urlencode($pu['serial_no']),
            ];
        }

        $summaryStats = $this->panelUnit->getSummaryStats();
        $latestId = $this->panelUnit->getLastPanelId();

        echo json_encode([
            'success'   => true,
            'last_id'   => max($lastId, $latestId),
            'new_count' => count($formattedItems),
            'new_items' => $formattedItems,
            'counters'  => [
                'total'          => $summaryStats['total'],
                'in_stock'       => $summaryStats['in_stock'],
                'today_produced' => $summaryStats['today_produced'],
                'shipped'        => $summaryStats['shipped'],
                'quarantine'     => $summaryStats['quarantine']
            ]
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
}