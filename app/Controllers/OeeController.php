<?php

require_once __DIR__ . '/../Models/Mes.php';
require_once __DIR__ . '/../Services/OeeCalculationService.php';
require_once __DIR__ . '/../Services/DowntimeManagementService.php';

class OeeController
{
    private PDO $pdo;
    private Mes $mesModel;
    private OeeCalculationService $oeeService;
    private DowntimeManagementService $downtimeService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->mesModel = new Mes($pdo);
        $this->oeeService = new OeeCalculationService($pdo);
        $this->downtimeService = new DowntimeManagementService($pdo);
    }

    /**
     * OEE ve Duruş Yönetim Paneli (GET /oee)
     */
    public function index(): void
    {
        $startDate = $_GET['start_date'] ?? date('Y-m-d');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $lineId = !empty($_GET['line_id']) ? (int)$_GET['line_id'] : null;

        $summary = $this->oeeService->getFactoryOeeSummary($startDate, $endDate);
        $openDowntimes = $this->downtimeService->getAllOpenDowntimes();
        $recentDowntimes = $this->downtimeService->getDowntimes(['line_id' => $lineId], 20);
        $reasons = $this->downtimeService->getReasons(true);
        $pareto = $this->oeeService->getParetoDowntimes($startDate, $endDate, $lineId);

        $allLines = $this->mesModel->getActiveProductionLines();

        $pageTitle = 'OEE & Hat Performans Yönetimi';
        $activePage = 'oee';

        require __DIR__ . '/../../views/oee/index.php';
    }

    /**
     * Canlı OEE ve Duruş API (GET /oee/api/live)
     */
    public function live(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
        }

        $startDate = $_GET['start_date'] ?? date('Y-m-d');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $summary = $this->oeeService->getFactoryOeeSummary($startDate, $endDate);
        $openDowntimes = $this->downtimeService->getAllOpenDowntimes();

        echo json_encode([
            'success'        => true,
            'summary'        => $summary,
            'open_downtimes' => $openDowntimes,
            'timestamp'      => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    /**
     * Duruş Başlat (POST /oee/downtime/start)
     */
    public function startDowntime(): void
    {
        $lineId = (int)($_POST['line_id'] ?? 0);
        $reasonId = (int)($_POST['reason_id'] ?? 0);
        $note = trim($_POST['operator_note'] ?? '');
        $workOrderId = !empty($_POST['work_order_id']) ? (int)$_POST['work_order_id'] : null;
        $userId = $_SESSION['user_id'] ?? null;

        $result = $this->downtimeService->startDowntime($lineId, $reasonId, $note, $workOrderId, $userId);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            if (!headers_sent()) {
                http_response_code($result['status_code'] ?? 200);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['message'];
        }

        header('Location: /stok-takip/public/oee');
        exit;
    }

    /**
     * Duruş Kapat (POST /oee/downtime/end)
     */
    public function endDowntime(): void
    {
        $downtimeId = (int)($_POST['downtime_id'] ?? 0);
        $note = trim($_POST['operator_note'] ?? '');
        $userId = $_SESSION['user_id'] ?? null;

        $result = $this->downtimeService->endDowntime($downtimeId, $note, null, $userId);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            if (!headers_sent()) {
                http_response_code($result['status_code'] ?? 200);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['message'];
        }

        header('Location: /stok-takip/public/oee');
        exit;
    }

    /**
     * Vardiya Sonu Snapshot Oluştur (POST /oee/snapshot)
     */
    public function createSnapshot(): void
    {
        $lineId = (int)($_POST['line_id'] ?? 0);
        $shiftId = (int)($_POST['shift_id'] ?? 1);
        $date = trim($_POST['date'] ?? date('Y-m-d'));

        $result = $this->oeeService->snapshotShift($lineId, $shiftId, $date);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($result['success']) {
            $_SESSION['success'] = 'Vardiya OEE snapshot kaydı başarıyla oluşturuldu ve mühürlendi.';
        } else {
            $_SESSION['error'] = $result['message'] ?? 'Snapshot oluşturulamadı.';
        }

        header('Location: /stok-takip/public/oee');
        exit;
    }
}

