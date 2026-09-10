<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/PurchaseReceipt.php';
require_once __DIR__ . '/../Models/PurchaseOrder.php';
require_once __DIR__ . '/../Services/CsrfService.php';

class PurchaseReceiptController
{
    private PDO $pdo;
    private PurchaseReceipt $receiptModel;
    private PurchaseOrder $poModel;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? (new Database())->connect();
        $this->receiptModel = new PurchaseReceipt($this->pdo);
        $this->poModel = new PurchaseOrder($this->pdo);
    }

    /**
     * Mal Kabul ve Teslimat Listesi (GET /purchase-receipts)
     */
    public function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $filters = [
            'search'       => trim((string)($_GET['search'] ?? '')),
            'status'       => trim((string)($_GET['status'] ?? '')),
            'supplier_id'  => (int)($_GET['supplier_id'] ?? 0),
            'warehouse_id' => (int)($_GET['warehouse_id'] ?? 0),
            'date_from'    => trim((string)($_GET['date_from'] ?? '')),
            'date_to'      => trim((string)($_GET['date_to'] ?? '')),
        ];

        $receipts = $this->receiptModel->getAll($filters, $limit, $offset);
        $totalCount = $this->receiptModel->countAll($filters);
        $totalPages = (int)ceil($totalCount / $limit);

        $kpis = $this->receiptModel->getKpis();
        $filterOptions = $this->receiptModel->getFilterOptions();
        $suppliers = $filterOptions['suppliers'] ?? [];
        $warehouses = $filterOptions['warehouses'] ?? [];
        $statuses = $filterOptions['statuses'] ?? [];

        $pageTitle = 'Mal Kabul & Teslimat Yönetimi';
        $activePage = 'purchase_receipts';

        $viewFile = __DIR__ . '/../../views/purchase-receipts/index.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            echo "View bulunamadı: " . htmlspecialchars($viewFile);
        }
    }

    /**
     * Mal Kabul Detay Ekranı (GET /purchase-receipts/show?id=X)
     */
    public function show(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = 'Geçerli bir mal kabul kaydı belirtilmedi.';
            $this->redirect('/purchase-receipts');
            return;
        }

        $receipt = $this->receiptModel->findById($id);
        if (!$receipt) {
            $_SESSION['error'] = 'Mal kabul kaydı sistemde bulunamadı.';
            $this->redirect('/purchase-receipts');
            return;
        }

        $items = $this->receiptModel->getItems($id);

        $pageTitle = 'Mal Kabul Detayı: ' . $receipt['receipt_no'];
        $activePage = 'purchase_receipts';

        $viewFile = __DIR__ . '/../../views/purchase-receipts/show.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            echo "View bulunamadı: " . htmlspecialchars($viewFile);
        }
    }

    /**
     * Mal Kabul Oluşturma Ekranı (GET /purchase-receipts/create?purchase_order_id=X)
     */
    public function create(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->store();
            return;
        }

        $poId = (int)($_GET['purchase_order_id'] ?? 0);
        if ($poId <= 0) {
            $_SESSION['error'] = 'Geçerli bir Satın Alma Siparişi seçilmelidir.';
            $this->redirect('/purchase-orders');
            return;
        }

        $order = $this->poModel->findById($poId);
        if (!$order) {
            $_SESSION['error'] = 'Satın alma siparişi sistemde bulunamadı.';
            $this->redirect('/purchase-orders');
            return;
        }

        if (!in_array($order['status'], ['CONFIRMED', 'PARTIALLY_RECEIVED'], true)) {
            $_SESSION['error'] = 'Yalnızca Onaylandı (CONFIRMED) veya Kısmi Teslim Alındı (PARTIALLY_RECEIVED) durumundaki siparişlere mal kabul yapılabilir.';
            $this->redirect('/purchase-orders/show?id=' . $poId);
            return;
        }

        $items = $this->poModel->getItems($poId);
        if (empty($items)) {
            $_SESSION['error'] = 'Sarişte teslim alınacak malzeme kalemi bulunamadı.';
            $this->redirect('/purchase-orders/show?id=' . $poId);
            return;
        }

        // Kalan teslimat miktarı olan kalemleri filtrele/hazırla
        $pendingItems = [];
        foreach ($items as $it) {
            $rem = (float)$it['ordered_quantity'] - (float)$it['received_quantity'];
            if ($rem > 0) {
                $it['remaining_quantity'] = $rem;
                $pendingItems[] = $it;
            }
        }

        if (empty($pendingItems)) {
            $_SESSION['error'] = 'Bu siparişin tüm kalemleri zaten eksiksiz teslim alınmıştır.';
            $this->redirect('/purchase-orders/show?id=' . $poId);
            return;
        }

        $warehouses = $this->receiptModel->getWarehousesWithLocations();

        $pageTitle = 'Mal Kabul / Teslim Al: ' . $order['order_no'];
        $activePage = 'purchase_orders';

        $viewFile = __DIR__ . '/../../views/purchase-receipts/create.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            echo "View bulunamadı: " . htmlspecialchars($viewFile);
        }
    }

    /**
     * Mal Kabul İşlemini Kaydeder (POST /purchase-receipts/create)
     */
    public function store(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/purchase-orders');
            return;
        }

        $poId = (int)($_POST['purchase_order_id'] ?? 0);
        if ($poId <= 0) {
            $_SESSION['error'] = 'Geçersiz sipariş referansı.';
            $this->redirect('/purchase-orders');
            return;
        }

        $userId = (int)($_SESSION['user_id'] ?? 1);

        $receiptData = [
            'purchase_order_id'     => $poId,
            'warehouse_id'          => (int)($_POST['warehouse_id'] ?? 0),
            'location_id'           => (int)($_POST['location_id'] ?? 0),
            'receipt_date'          => trim((string)($_POST['receipt_date'] ?? date('Y-m-d'))),
            'delivery_note_no'      => trim((string)($_POST['delivery_note_no'] ?? '')),
            'supplier_document_no'  => trim((string)($_POST['supplier_document_no'] ?? '')),
            'notes'                 => trim((string)($_POST['notes'] ?? '')),
        ];

        $itemData = [
            'purchase_order_item_id' => (int)($_POST['purchase_order_item_id'] ?? 0),
            'received_quantity'      => (float)($_POST['received_quantity'] ?? 0),
            'notes'                  => trim((string)($_POST['item_notes'] ?? '')),
        ];

        try {
            $receiptId = $this->receiptModel->createReceipt($receiptData, $itemData, $userId);
            $receipt = $this->receiptModel->findById($receiptId);
            $recNo = $receipt ? $receipt['receipt_no'] : '';

            $_SESSION['success'] = sprintf(
                "Mal kabul işlemi başarıyla tamamlandı (%s) ve stok girişi kaydedildi.",
                $recNo
            );
            $this->redirect('/purchase-orders/show?id=' . $poId);

        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/purchase-receipts/create?purchase_order_id=' . $poId);
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Mal kabul işlemi sırasında hata oluştu: ' . $e->getMessage();
            $this->redirect('/purchase-receipts/create?purchase_order_id=' . $poId);
        }
    }

    private function redirect(string $path): void
    {
        $base = '/stok-takip/public';
        $url = str_starts_with($path, '/') ? $base . $path : $base . '/' . $path;
        header('Location: ' . $url);
        exit;
    }
}
