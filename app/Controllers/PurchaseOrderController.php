<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/PurchaseOrder.php';
require_once __DIR__ . '/../Models/PurchaseRequest.php';

class PurchaseOrderController
{
    private PDO $pdo;
    private PurchaseOrder $orderModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->orderModel = new PurchaseOrder($this->pdo);
    }

    /**
     * Satın Alma Siparişleri Listesi (GET /purchase-orders)
     */
    public function index(): void
    {
        $rawStatus = trim((string)($_GET['status'] ?? ''));
        $validStatuses = PurchaseOrder::statuses();
        $status = in_array($rawStatus, $validStatuses, true) ? $rawStatus : '';

        $rawCurrency = strtoupper(trim((string)($_GET['currency'] ?? '')));
        $validCurrencies = PurchaseOrder::currencies();
        $currency = in_array($rawCurrency, $validCurrencies, true) ? $rawCurrency : '';

        $filters = [
            'status'      => $status,
            'supplier_id' => max(0, (int)($_GET['supplier_id'] ?? 0)),
            'ordered_by'  => max(0, (int)($_GET['ordered_by'] ?? 0)),
            'currency'    => $currency,
            'date_from'   => trim((string)($_GET['date_from'] ?? '')),
            'date_to'     => trim((string)($_GET['date_to'] ?? '')),
            'search'      => trim((string)($_GET['search'] ?? '')),
        ];

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $totalCount = $this->orderModel->countAll($filters);
        $totalPages = max(1, (int)ceil($totalCount / $limit));
        $orders = $this->orderModel->getAll($filters, $limit, $offset);
        $kpis = $this->orderModel->getKpis();

        $suppliers = $this->orderModel->getActiveSuppliers();
        $statuses = $validStatuses;
        $currencies = $validCurrencies;

        $pageTitle = 'Satın Alma Siparişleri';
        $activePage = 'purchase_orders';

        $viewFile = __DIR__ . '/../../views/purchase-orders/index.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        }
    }

    /**
     * Satın Alma Siparişi Detay Ekranı (GET /purchase-orders/show?id=X)
     */
    public function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = 'Geçersiz sipariş numarası.';
            $this->redirect('/purchase-orders');
            return;
        }

        $order = $this->orderModel->findById($id);
        if (!$order) {
            $_SESSION['error'] = 'Satın alma siparişi bulunamadı.';
            $this->redirect('/purchase-orders');
            return;
        }

        $items = $this->orderModel->getItems($id);
        $allowedTransitions = PurchaseOrder::getAllowedTransitions($order['status']);

        $pageTitle = 'Satın Alma Siparişi: ' . $order['order_no'];
        $activePage = 'purchase_orders';

        $viewFile = __DIR__ . '/../../views/purchase-orders/show.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        }
    }

    /**
     * Yeni Satın Alma Siparişi Formu ve Kaydetme (GET/POST /purchase-orders/create)
     */
    public function create(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->store();
            return;
        }

        $suppliers = $this->orderModel->getActiveSuppliers();
        $materials = $this->orderModel->getActiveMaterials();
        $currencies = PurchaseOrder::currencies();

        $nextOrderNo = $this->orderModel->generateOrderNo();

        $pageTitle = 'Yeni Satın Alma Siparişi';
        $activePage = 'purchase_orders';

        $viewFile = __DIR__ . '/../../views/purchase-orders/create.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        }
    }

    /**
     * Yeni Satın Alma Siparişini Veritabanına Kaydeder (POST /purchase-orders/create)
     */
    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/purchase-orders/create');
            return;
        }

        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        if ($currentUserId <= 0) {
            $_SESSION['error'] = 'Oturum bilgisi bulunamadı, lütfen yeniden giriş yapın.';
            $this->redirect('/login');
            return;
        }

        $headerData = [
            'supplier_id'            => (int)($_POST['supplier_id'] ?? 0),
            'ordered_by'             => $currentUserId,
            'order_date'             => trim((string)($_POST['order_date'] ?? date('Y-m-d'))),
            'expected_delivery_date' => !empty($_POST['expected_delivery_date']) ? trim((string)$_POST['expected_delivery_date']) : null,
            'payment_terms'          => !empty($_POST['payment_terms']) ? trim((string)$_POST['payment_terms']) : null,
            'delivery_terms'         => !empty($_POST['delivery_terms']) ? trim((string)$_POST['delivery_terms']) : null,
            'currency'               => trim((string)($_POST['currency'] ?? 'TRY')),
            'tax_rate'               => isset($_POST['tax_rate']) && $_POST['tax_rate'] !== '' ? (float)$_POST['tax_rate'] : 20.00,
            'notes'                  => !empty($_POST['notes']) ? trim((string)$_POST['notes']) : null,
        ];

        $items = $this->parseItemsFromRequest();
        $action = trim((string)($_POST['action'] ?? 'draft'));

        try {
            $newId = $this->orderModel->create($headerData, $items);
            if ($action === 'send') {
                $this->orderModel->send($newId);
                $_SESSION['success'] = 'Satın alma siparişi oluşturuldu ve tedarikçiye gönderildi.';
            } else {
                $_SESSION['success'] = 'Satın alma siparişi taslak olarak kaydedildi.';
            }
            $this->redirect('/purchase-orders/show?id=' . $newId);
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/purchase-orders/create');
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Satın alma siparişi oluşturulamadı: ' . $e->getMessage();
            $this->redirect('/purchase-orders/create');
        }
    }

    /**
     * Satın Alma Siparişi Düzenleme Ekranı (GET/POST /purchase-orders/edit?id=X)
     */
    public function edit(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->update();
            return;
        }

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = 'Geçersiz sipariş numarası.';
            $this->redirect('/purchase-orders');
            return;
        }

        $order = $this->orderModel->findById($id);
        if (!$order) {
            $_SESSION['error'] = 'Satın alma siparişi bulunamadı.';
            $this->redirect('/purchase-orders');
            return;
        }

        if ($order['status'] !== 'DRAFT') {
            $_SESSION['error'] = 'Sadece Taslak (DRAFT) durumundaki siparişler düzenlenebilir.';
            $this->redirect('/purchase-orders/show?id=' . $id);
            return;
        }

        $items = $this->orderModel->getItems($id);
        $suppliers = $this->orderModel->getActiveSuppliers();
        $materials = $this->orderModel->getActiveMaterials();
        $currencies = PurchaseOrder::currencies();

        $pageTitle = 'Siparişi Düzenle: ' . $order['order_no'];
        $activePage = 'purchase_orders';

        $viewFile = __DIR__ . '/../../views/purchase-orders/edit.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        }
    }

    /**
     * Satın Alma Siparişini Günceller (POST /purchase-orders/edit)
     */
    public function update(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/purchase-orders');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = 'Geçersiz sipariş ID.';
            $this->redirect('/purchase-orders');
            return;
        }

        $headerData = [
            'supplier_id'            => (int)($_POST['supplier_id'] ?? 0),
            'order_date'             => trim((string)($_POST['order_date'] ?? date('Y-m-d'))),
            'expected_delivery_date' => !empty($_POST['expected_delivery_date']) ? trim((string)$_POST['expected_delivery_date']) : null,
            'payment_terms'          => !empty($_POST['payment_terms']) ? trim((string)$_POST['payment_terms']) : null,
            'delivery_terms'         => !empty($_POST['delivery_terms']) ? trim((string)$_POST['delivery_terms']) : null,
            'currency'               => trim((string)($_POST['currency'] ?? 'TRY')),
            'tax_rate'               => isset($_POST['tax_rate']) && $_POST['tax_rate'] !== '' ? (float)$_POST['tax_rate'] : 20.00,
            'notes'                  => !empty($_POST['notes']) ? trim((string)$_POST['notes']) : null,
        ];

        $items = $this->parseItemsFromRequest();
        $action = trim((string)($_POST['action'] ?? 'draft'));

        try {
            $this->orderModel->update($id, $headerData, $items);
            if ($action === 'send') {
                $this->orderModel->send($id);
                $_SESSION['success'] = 'Satın alma siparişi güncellendi ve tedarikçiye gönderildi.';
            } else {
                $_SESSION['success'] = 'Satın alma siparişi başarıyla güncellendi.';
            }
            $this->redirect('/purchase-orders/show?id=' . $id);
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/purchase-orders/edit?id=' . $id);
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Satın alma siparişi güncellenemedi: ' . $e->getMessage();
            $this->redirect('/purchase-orders/edit?id=' . $id);
        }
    }

    /**
     * Siparişi tedarikçiye gönderir: DRAFT -> SENT (POST /purchase-orders/send)
     */
    public function send(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/purchase-orders');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = 'Geçersiz sipariş ID.';
            $this->redirect('/purchase-orders');
            return;
        }

        try {
            $this->orderModel->send($id);
            $_SESSION['success'] = 'Sipariş tedarikçiye iletildi olarak işaretlendi.';
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Sipariş gönderilemedi: ' . $e->getMessage();
        }

        $this->redirect('/purchase-orders/show?id=' . $id);
    }

    /**
     * Tedarikçinin siparişi teyit ettiğini işaretler: SENT -> CONFIRMED (POST /purchase-orders/confirm)
     */
    public function confirm(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/purchase-orders');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = 'Geçersiz sipariş ID.';
            $this->redirect('/purchase-orders');
            return;
        }

        try {
            $this->orderModel->confirm($id);
            $_SESSION['success'] = 'Sipariş tedarikçi tarafından teyit edildi (CONFIRMED).';
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Sipariş teyit edilemedi: ' . $e->getMessage();
        }

        $this->redirect('/purchase-orders/show?id=' . $id);
    }

    /**
     * Siparişi iptal eder: DRAFT/SENT/CONFIRMED -> CANCELLED (POST /purchase-orders/cancel)
     */
    public function cancel(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/purchase-orders');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = 'Geçersiz sipariş ID.';
            $this->redirect('/purchase-orders');
            return;
        }

        $reason = isset($_POST['reason']) ? trim((string)$_POST['reason']) : (isset($_POST['notes']) ? trim((string)$_POST['notes']) : null);

        try {
            $this->orderModel->cancel($id, $reason);
            $_SESSION['success'] = 'Sipariş iptal edildi.';
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Sipariş iptal edilemedi: ' . $e->getMessage();
        }

        $this->redirect('/purchase-orders/show?id=' . $id);
    }

    /**
     * Onaylı Satın Alma Talebinden (PR) otomatik PO üretir (POST /purchase-orders/from-request)
     */
    public function createFromRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/purchase-requests');
            return;
        }

        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        if ($currentUserId <= 0) {
            $_SESSION['error'] = 'Oturum bilgisi bulunamadı, lütfen yeniden giriş yapın.';
            $this->redirect('/login');
            return;
        }

        $prId = (int)($_POST['purchase_request_id'] ?? 0);
        if ($prId <= 0) {
            $_SESSION['error'] = 'Geçersiz Satın Alma Talebi ID.';
            $this->redirect('/purchase-requests');
            return;
        }

        $options = [];
        if (!empty($_POST['supplier_id'])) {
            $options['supplier_id'] = (int)$_POST['supplier_id'];
        }
        if (!empty($_POST['expected_delivery_date'])) {
            $options['expected_delivery_date'] = trim((string)$_POST['expected_delivery_date']);
        }
        if (!empty($_POST['payment_terms'])) {
            $options['payment_terms'] = trim((string)$_POST['payment_terms']);
        }
        if (!empty($_POST['delivery_terms'])) {
            $options['delivery_terms'] = trim((string)$_POST['delivery_terms']);
        }
        if (isset($_POST['tax_rate']) && $_POST['tax_rate'] !== '') {
            $options['tax_rate'] = (float)$_POST['tax_rate'];
        }
        if (!empty($_POST['notes'])) {
            $options['notes'] = trim((string)$_POST['notes']);
        }

        try {
            $orderId = $this->orderModel->createFromPurchaseRequest($prId, $currentUserId, $options);
            $_SESSION['success'] = 'Satın alma talebinden sipariş (PO) başarıyla oluşturuldu.';
            $this->redirect('/purchase-orders/show?id=' . $orderId);
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/purchase-requests/show?id=' . $prId);
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Sipariş oluşturulamadı: ' . $e->getMessage();
            $this->redirect('/purchase-requests/show?id=' . $prId);
        }
    }

    /**
     * HTTP isteğinden malzeme kalemlerini hem nesne dizisi hem paralel dizi formatlarını destekleyerek ayrıştırır.
     */
    private function parseItemsFromRequest(): array
    {
        $items = [];

        // Format 1: items[0][material_id], items[0][ordered_quantity], ...
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $rawItem) {
                if (!is_array($rawItem)) {
                    continue;
                }
                $materialId = (int)($rawItem['material_id'] ?? 0);
                if ($materialId <= 0) {
                    continue;
                }
                $items[] = [
                    'material_id'              => $materialId,
                    'ordered_quantity'         => $rawItem['ordered_quantity'] ?? 0,
                    'received_quantity'        => $rawItem['received_quantity'] ?? 0,
                    'unit_price'               => isset($rawItem['unit_price']) && $rawItem['unit_price'] !== '' ? $rawItem['unit_price'] : 0,
                    'currency'                 => trim((string)($rawItem['currency'] ?? 'TRY')),
                    'tax_rate'                 => isset($rawItem['tax_rate']) && $rawItem['tax_rate'] !== '' ? $rawItem['tax_rate'] : 20.00,
                    'purchase_request_item_id' => !empty($rawItem['purchase_request_item_id']) ? (int)$rawItem['purchase_request_item_id'] : null,
                    'notes'                    => trim((string)($rawItem['notes'] ?? '')),
                ];
            }
            return $items;
        }

        // Format 2: material_id[], ordered_quantity[], unit_price[], ...
        if (isset($_POST['material_id']) && is_array($_POST['material_id'])) {
            $count = count($_POST['material_id']);
            for ($i = 0; $i < $count; $i++) {
                $materialId = (int)($_POST['material_id'][$i] ?? 0);
                if ($materialId <= 0) {
                    continue;
                }
                $items[] = [
                    'material_id'              => $materialId,
                    'ordered_quantity'         => $_POST['ordered_quantity'][$i] ?? 0,
                    'received_quantity'        => $_POST['received_quantity'][$i] ?? 0,
                    'unit_price'               => isset($_POST['unit_price'][$i]) && $_POST['unit_price'][$i] !== '' ? $_POST['unit_price'][$i] : 0,
                    'currency'                 => trim((string)($_POST['currency'][$i] ?? 'TRY')),
                    'tax_rate'                 => isset($_POST['tax_rate'][$i]) && $_POST['tax_rate'][$i] !== '' ? $_POST['tax_rate'][$i] : 20.00,
                    'purchase_request_item_id' => !empty($_POST['purchase_request_item_id'][$i]) ? (int)$_POST['purchase_request_item_id'][$i] : null,
                    'notes'                    => trim((string)($_POST['notes'][$i] ?? '')),
                ];
            }
        }

        return $items;
    }

    /**
     * Güvenli yönlendirme yardımcısı
     */
    private function redirect(string $path): void
    {
        $base = '/stok-takip/public';
        $target = str_starts_with($path, '/') ? $base . $path : $base . '/' . $path;

        if (!headers_sent()) {
            header('Location: ' . $target);
            exit;
        }
        echo '<script>window.location.href="' . htmlspecialchars($target) . '";</script>';
        exit;
    }
}

