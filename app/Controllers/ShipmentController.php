<?php

require_once __DIR__ . '/../Models/Shipment.php';
require_once __DIR__ . '/../Models/PanelUnit.php';

class ShipmentController
{
    private PDO $pdo;
    private Shipment $shipmentModel;
    private PanelUnit $panelModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->shipmentModel = new Shipment($this->pdo);
        $this->panelModel = new PanelUnit($this->pdo);
    }

    /**
     * Sevkiyat Listesi (GET /shipments)
     */
    public function index(): void
    {
        $filters = [
            'shipment_no'   => $_GET['shipment_no'] ?? '',
            'customer_name' => $_GET['customer_name'] ?? '',
            'status'        => $_GET['status'] ?? ''
        ];

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $totalCount = $this->shipmentModel->countAll($filters);
        $totalPages = max(1, (int)ceil($totalCount / $limit));
        $shipments = $this->shipmentModel->getAll($filters, $limit, $offset);

        $pageTitle = 'Sevkiyat Yönetimi';
        require __DIR__ . '/../../views/shipments/index.php';
    }

    /**
     * Yeni Sevkiyat Emri (GET /shipments/create ve POST)
     */
    public function create(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $customerName = trim($_POST['customer_name'] ?? '');
            $shippingAddress = trim($_POST['shipping_address'] ?? '');

            if (empty($customerName) || empty($shippingAddress)) {
                $_SESSION['error'] = 'Lütfen tüm alanları doldurunuz.';
                $pageTitle = 'Yeni Sevkiyat Emri';
                require __DIR__ . '/../../views/shipments/create.php';
                return;
            }

            try {
                $shipmentId = $this->shipmentModel->create([
                    'customer_name'    => $customerName,
                    'shipping_address' => $shippingAddress
                ]);
                $_SESSION['success'] = 'Sevkiyat emri başarıyla oluşturuldu.';
                header('Location: /stok-takip/public/shipments/show?id=' . $shipmentId);
                exit;
            } catch (Throwable $e) {
                $_SESSION['error'] = 'Sevkiyat emri oluşturulamadı: ' . $e->getMessage();
            }
        }

        $pageTitle = 'Yeni Sevkiyat Emri';
        require __DIR__ . '/../../views/shipments/create.php';
    }

    /**
     * Sevkiyat Detay Ekranı (GET /shipments/show?id=X)
     */
    public function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $shipment = $this->shipmentModel->getById($id);

        if (!$shipment) {
            $_SESSION['error'] = 'Sevkiyat emri bulunamadı.';
            header('Location: /stok-takip/public/shipments');
            exit;
        }

        $items = $this->shipmentModel->getItems($id);
        
        // Search filter for adding shippable panels
        $search = trim($_GET['search'] ?? '');
        $shippablePanels = $this->shipmentModel->getShippablePanels($search);

        $pageTitle = 'Sevkiyat Emri: ' . $shipment['shipment_no'];
        require __DIR__ . '/../../views/shipments/show.php';
    }

    /**
     * Sevkiyata Panel Ekle (POST /shipments/add-panel)
     */
    public function addPanel(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error'] = 'Geçersiz istek yöntemi.';
            header('Location: /stok-takip/public/shipments');
            exit;
        }

        $shipmentId = (int)($_POST['shipment_id'] ?? 0);
        $panelUnitId = (int)($_POST['panel_unit_id'] ?? 0);
        $serialNo = trim($_POST['serial_no'] ?? '');

        if ($shipmentId <= 0) {
            $_SESSION['error'] = 'Geçersiz sevkiyat kimliği.';
            header('Location: /stok-takip/public/shipments');
            exit;
        }

        // If serial number is supplied, find the panel ID
        if ($panelUnitId <= 0 && $serialNo !== '') {
            $panel = $this->panelModel->getBySerial($serialNo);
            if ($panel) {
                $panelUnitId = (int)$panel['id'];
            } else {
                $_SESSION['error'] = 'Seri numarasına ait panel bulunamadı.';
                header('Location: /stok-takip/public/shipments/show?id=' . $shipmentId);
                exit;
            }
        }

        if ($panelUnitId <= 0) {
            $_SESSION['error'] = 'Lütfen eklenecek paneli seçiniz.';
            header('Location: /stok-takip/public/shipments/show?id=' . $shipmentId);
            exit;
        }

        $result = $this->shipmentModel->addPanel($shipmentId, $panelUnitId);
        if ($result['success']) {
            $_SESSION['success'] = 'Panel sevkiyat planına başarıyla eklendi.';
        } else {
            $_SESSION['error'] = $result['message'] ?? 'Panel eklenemedi.';
        }

        header('Location: /stok-takip/public/shipments/show?id=' . $shipmentId);
        exit;
    }

    /**
     * Sevkiyattan Panel Çıkar (POST /shipments/remove-panel)
     */
    public function removePanel(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error'] = 'Geçersiz istek yöntemi.';
            header('Location: /stok-takip/public/shipments');
            exit;
        }

        $shipmentId = (int)($_POST['shipment_id'] ?? 0);
        $panelUnitId = (int)($_POST['panel_unit_id'] ?? 0);

        if ($shipmentId <= 0 || $panelUnitId <= 0) {
            $_SESSION['error'] = 'Geçersiz parametreler.';
            header('Location: /stok-takip/public/shipments');
            exit;
        }

        $success = $this->shipmentModel->removePanel($shipmentId, $panelUnitId);
        if ($success) {
            $_SESSION['success'] = 'Panel sevkiyat planından çıkarıldı.';
        } else {
            $_SESSION['error'] = 'Panel plandan çıkarılamadı.';
        }

        header('Location: /stok-takip/public/shipments/show?id=' . $shipmentId);
        exit;
    }

    /**
     * Sevkiyatı Tamamla (POST /shipments/complete)
     */
    public function complete(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error'] = 'Geçersiz istek yöntemi.';
            header('Location: /stok-takip/public/shipments');
            exit;
        }

        $shipmentId = (int)($_POST['shipment_id'] ?? 0);
        if ($shipmentId <= 0) {
            $_SESSION['error'] = 'Geçersiz sevkiyat kimliği.';
            header('Location: /stok-takip/public/shipments');
            exit;
        }

        $userId = (int)($_SESSION['user_id'] ?? 1);
        $result = $this->shipmentModel->complete($shipmentId, $userId);

        if ($result['success']) {
            $_SESSION['success'] = 'Sevkiyat başarıyla tamamlandı ve stok çıkış kayıtları oluşturuldu.';
        } else {
            $_SESSION['error'] = $result['message'] ?? 'Sevkiyat tamamlanırken hata oluştu.';
        }

        header('Location: /stok-takip/public/shipments/show?id=' . $shipmentId);
        exit;
    }

    /**
     * Sevkiyatı İptal Et (POST /shipments/cancel)
     */
    public function cancel(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error'] = 'Geçersiz istek yöntemi.';
            header('Location: /stok-takip/public/shipments');
            exit;
        }

        $shipmentId = (int)($_POST['shipment_id'] ?? 0);
        if ($shipmentId <= 0) {
            $_SESSION['error'] = 'Geçersiz sevkiyat kimliği.';
            header('Location: /stok-takip/public/shipments');
            exit;
        }

        $result = $this->shipmentModel->cancel($shipmentId);
        if ($result['success']) {
            $_SESSION['success'] = 'Sevkiyat emri başarıyla iptal edildi.';
        } else {
            $_SESSION['error'] = $result['message'] ?? 'Sevkiyat iptal edilemedi.';
        }

        header('Location: /stok-takip/public/shipments/show?id=' . $shipmentId);
        exit;
    }
}
