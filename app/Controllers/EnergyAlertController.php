<?php

require_once __DIR__ . '/../Models/EnergyAlert.php';
require_once __DIR__ . '/../Services/EnergyAlertService.php';

class EnergyAlertController
{
    private EnergyAlert $model;
    private EnergyAlertService $service;

    public function __construct()
    {
        $pdo = $GLOBALS['pdo'];
        $this->model = new EnergyAlert($pdo);
        $this->service = new EnergyAlertService($this->model, $pdo);
    }

    public function index(): void
    {
        // Query param üzerinden aksiyon tetiklendiyse yönlendir
        if (isset($_GET['action']) && isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            if ($_GET['action'] === 'ack') {
                $this->acknowledgeAction($id);
                return;
            } elseif ($_GET['action'] === 'resolve') {
                $this->resolveAction($id);
                return;
            }
        }

        // 1. Otomatik Anomali Taramasını Çalıştır (Idempotent: mükerrer kayıt üretmez)
        $this->service->scanAndGenerateAnomalies();

        // 2. Filtreleri Al ve Doğrula
        $filters = [];
        if (!empty($_GET['severity']) && in_array($_GET['severity'], ['CRITICAL', 'WARNING', 'INFO'], true)) {
            $filters['severity'] = $_GET['severity'];
        }
        if (!empty($_GET['alert_type'])) {
            $filters['alert_type'] = $_GET['alert_type'];
        }
        if (!empty($_GET['meter_id']) && is_numeric($_GET['meter_id'])) {
            $filters['meter_id'] = (int)$_GET['meter_id'];
        }
        if (isset($_GET['status']) && $_GET['status'] !== '' && in_array((string)$_GET['status'], ['0', '1', '2'], true)) {
            $filters['status'] = (int)$_GET['status'];
        }
        if (!empty($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) {
            $filters['date'] = $_GET['date'];
        }

        // 3. KPI İstatistikleri ve Sayaç Listesi
        $stats = $this->model->getSummaryStats();
        $meters = $this->model->getMeters();
        $allAlerts = $this->model->getAllAlerts($filters);

        // 4. Her alarm için aksiyon tavsiyesi ekle
        $criticalActive = [];
        $otherAlerts = [];

        foreach ($allAlerts as &$alert) {
            $advice = EnergyAlertService::getRecommendedAction(
                $alert['alert_type'],
                (float)$alert['measured_value'],
                (float)$alert['threshold_value'],
                $alert['meter_code'] ?? null
            );
            $alert['advice'] = $advice;

            if ($alert['is_acknowledged'] == 0 && $alert['severity'] === 'CRITICAL') {
                $criticalActive[] = $alert;
            } else {
                $otherAlerts[] = $alert;
            }
        }
        unset($alert);

        $flashMessage = $_SESSION['alert_flash_message'] ?? null;
        unset($_SESSION['alert_flash_message']);

        $pageTitle = 'Alarmlar &amp; Olay Kayıtları';
        $activePage = 'energy-alerts';

        require __DIR__ . '/../../views/energy-alerts/index.php';
    }

    public function acknowledge(): void
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $this->acknowledgeAction($id);
    }

    public function resolve(): void
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $this->resolveAction($id);
    }

    private function acknowledgeAction(int $id): void
    {
        if ($id > 0) {
            $userId = (int)($_SESSION['user_id'] ?? 1);
            $this->model->acknowledgeAlert($id, $userId);
            $_SESSION['alert_flash_message'] = [
                'type' => 'success',
                'text' => "Alarm (#{$id}) yetkili tarafından başarıyla onaylandı (Acknowledged)."
            ];
        }
        header('Location: /stok-takip/public/energy/alerts');
        exit;
    }

    private function resolveAction(int $id): void
    {
        if ($id > 0) {
            $userId = (int)($_SESSION['user_id'] ?? 1);
            $this->model->resolveAlert($id, $userId);
            $_SESSION['alert_flash_message'] = [
                'type' => 'success',
                'text' => "Alarm (#{$id}) çözüldü (Resolved) olarak işaretlendi ve arşivlendi."
            ];
        }
        header('Location: /stok-takip/public/energy/alerts');
        exit;
    }
}
