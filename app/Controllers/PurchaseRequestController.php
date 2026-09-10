<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/PurchaseRequest.php';

class PurchaseRequestController
{
    private PDO $pdo;
    private PurchaseRequest $requestModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->requestModel = new PurchaseRequest($this->pdo);
    }

    /**
     * Satın Alma Talepleri Listesi (GET /purchase-requests)
     */
    public function index(): void
    {
        $filters = [
            'status'        => trim((string)($_GET['status'] ?? '')),
            'priority'      => trim((string)($_GET['priority'] ?? '')),
            'department_id' => (int)($_GET['department_id'] ?? 0),
            'requested_by'  => (int)($_GET['requested_by'] ?? 0),
            'date_from'     => trim((string)($_GET['date_from'] ?? '')),
            'date_to'       => trim((string)($_GET['date_to'] ?? '')),
            'search'        => trim((string)($_GET['search'] ?? '')),
        ];

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $totalCount = $this->requestModel->countAll($filters);
        $totalPages = max(1, (int)ceil($totalCount / $limit));
        $requests = $this->requestModel->getAll($filters, $limit, $offset);
        $kpis = $this->requestModel->getKpis();

        $departments = $this->requestModel->getActiveDepartments();
        $requesters = $this->requestModel->getActiveRequesters();
        $priorities = PurchaseRequest::priorities();
        $statuses = PurchaseRequest::statuses();

        $pageTitle = 'Satın Alma Talepleri';
        $activePage = 'purchase_requests';

        $viewFile = __DIR__ . '/../../views/purchase-requests/index.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        }
    }

    /**
     * Satın Alma Talebi Detay Ekranı (GET /purchase-requests/show?id=X)
     */
    public function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = 'Geçersiz talep numarası.';
            $this->redirect('/purchase-requests');
            return;
        }

        $request = $this->requestModel->findById($id);
        if (!$request) {
            $_SESSION['error'] = 'Satın alma talebi bulunamadı.';
            $this->redirect('/purchase-requests');
            return;
        }

        $items = $this->requestModel->getItems($id);
        $allowedTransitions = PurchaseRequest::getAllowedTransitions($request['status']);

        $pageTitle = 'Satın Alma Talebi: ' . $request['request_no'];
        $activePage = 'purchase_requests';

        $viewFile = __DIR__ . '/../../views/purchase-requests/show.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        }
    }

    /**
     * Yeni Satın Alma Talebi Formu ve Kaydetme (GET/POST /purchase-requests/create)
     */
    public function create(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->store();
            return;
        }

        $departments = $this->requestModel->getActiveDepartments();
        $materials = $this->requestModel->getActiveMaterials();
        $suppliers = $this->requestModel->getActiveSuppliers();
        $priorities = PurchaseRequest::priorities();
        $currencies = PurchaseRequest::currencies();

        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        $defaultDepartmentId = $this->requestModel->getUserDepartmentId($currentUserId);
        $nextRequestNo = $this->requestModel->generateRequestNo();

        $pageTitle = 'Yeni Satın Alma Talebi';
        $activePage = 'purchase_requests';

        $viewFile = __DIR__ . '/../../views/purchase-requests/create.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        }
    }

    /**
     * Yeni Satın Alma Talebini Veritabanına Kaydeder (POST /purchase-requests/create)
     */
    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/purchase-requests/create');
            return;
        }

        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        if ($currentUserId <= 0) {
            $_SESSION['error'] = 'Oturum bilgisi bulunamadı, lütfen yeniden giriş yapın.';
            $this->redirect('/login');
            return;
        }

        $headerData = [
            'requested_by'  => $currentUserId,
            'department_id' => (int)($_POST['department_id'] ?? 0),
            'request_date'  => trim((string)($_POST['request_date'] ?? date('Y-m-d'))),
            'required_date' => trim((string)($_POST['required_date'] ?? '')),
            'priority'      => trim((string)($_POST['priority'] ?? 'MEDIUM')),
            'description'   => trim((string)($_POST['description'] ?? '')),
        ];

        $items = $this->parseItemsFromRequest();
        $action = trim((string)($_POST['action'] ?? 'draft'));

        try {
            $newId = $this->requestModel->create($headerData, $items);
            if ($action === 'submit') {
                $this->requestModel->submit($newId);
                $_SESSION['success'] = 'Satın alma talebi başarıyla oluşturuldu ve onaya gönderildi.';
            } else {
                $_SESSION['success'] = 'Satın alma talebi taslak olarak kaydedildi.';
            }
            $this->redirect('/purchase-requests/show?id=' . $newId);
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/purchase-requests/create');
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Satın alma talebi oluşturulamadı: ' . $e->getMessage();
            $this->redirect('/purchase-requests/create');
        }
    }

    /**
     * Satın Alma Talebi Düzenleme Ekranı (GET/POST /purchase-requests/edit?id=X)
     */
    public function edit(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->update();
            return;
        }

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = 'Geçersiz talep numarası.';
            $this->redirect('/purchase-requests');
            return;
        }

        $request = $this->requestModel->findById($id);
        if (!$request) {
            $_SESSION['error'] = 'Satın alma talebi bulunamadı.';
            $this->redirect('/purchase-requests');
            return;
        }

        if ($request['status'] !== 'DRAFT') {
            $_SESSION['error'] = 'Sadece Taslak (DRAFT) durumundaki talepler düzenlenebilir.';
            $this->redirect('/purchase-requests/show?id=' . $id);
            return;
        }

        $items = $this->requestModel->getItems($id);
        $departments = $this->requestModel->getActiveDepartments();
        $materials = $this->requestModel->getActiveMaterials();
        $suppliers = $this->requestModel->getActiveSuppliers();
        $priorities = PurchaseRequest::priorities();
        $currencies = PurchaseRequest::currencies();

        $pageTitle = 'Talebi Düzenle: ' . $request['request_no'];
        $activePage = 'purchase_requests';

        $viewFile = __DIR__ . '/../../views/purchase-requests/edit.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        }
    }

    /**
     * Satın Alma Talebini Günceller (POST /purchase-requests/edit)
     */
    public function update(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/purchase-requests');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = 'Geçersiz talep ID.';
            $this->redirect('/purchase-requests');
            return;
        }

        $headerData = [
            'department_id' => (int)($_POST['department_id'] ?? 0),
            'request_date'  => trim((string)($_POST['request_date'] ?? date('Y-m-d'))),
            'required_date' => trim((string)($_POST['required_date'] ?? '')),
            'priority'      => trim((string)($_POST['priority'] ?? 'MEDIUM')),
            'description'   => trim((string)($_POST['description'] ?? '')),
        ];

        $items = $this->parseItemsFromRequest();
        $action = trim((string)($_POST['action'] ?? 'draft'));

        try {
            $this->requestModel->update($id, $headerData, $items);
            if ($action === 'submit') {
                $this->requestModel->submit($id);
                $_SESSION['success'] = 'Satın alma talebi güncellendi ve onaya gönderildi.';
            } else {
                $_SESSION['success'] = 'Satın alma talebi başarıyla güncellendi.';
            }
            $this->redirect('/purchase-requests/show?id=' . $id);
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/purchase-requests/edit?id=' . $id);
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Satın alma talebi güncellenemedi: ' . $e->getMessage();
            $this->redirect('/purchase-requests/edit?id=' . $id);
        }
    }

    /**
     * Talebi onaya gönderir: DRAFT -> SUBMITTED (POST /purchase-requests/submit)
     */
    public function submit(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/purchase-requests');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = 'Geçersiz talep ID.';
            $this->redirect('/purchase-requests');
            return;
        }

        try {
            $this->requestModel->submit($id);
            $_SESSION['success'] = 'Talep onaya gönderildi.';
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Talep onaya gönderilemedi: ' . $e->getMessage();
        }

        $this->redirect('/purchase-requests/show?id=' . $id);
    }

    /**
     * Talebi onaylar: SUBMITTED -> APPROVED (POST /purchase-requests/approve)
     */
    public function approve(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/purchase-requests');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = 'Geçersiz talep ID.';
            $this->redirect('/purchase-requests');
            return;
        }

        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        if ($currentUserId <= 0) {
            $_SESSION['error'] = 'Oturum bilgisi bulunamadı.';
            $this->redirect('/login');
            return;
        }

        $approvalNotes = isset($_POST['approval_notes']) ? trim((string)$_POST['approval_notes']) : null;

        try {
            $this->requestModel->approve($id, $currentUserId, $approvalNotes);
            $_SESSION['success'] = 'Talep onaylandı.';
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Talep onaylanamadı: ' . $e->getMessage();
        }

        $this->redirect('/purchase-requests/show?id=' . $id);
    }

    /**
     * Talebi reddeder: SUBMITTED -> REJECTED (POST /purchase-requests/reject)
     */
    public function reject(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/purchase-requests');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = 'Geçersiz talep ID.';
            $this->redirect('/purchase-requests');
            return;
        }

        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        if ($currentUserId <= 0) {
            $_SESSION['error'] = 'Oturum bilgisi bulunamadı.';
            $this->redirect('/login');
            return;
        }

        $rejectionNotes = trim((string)($_POST['rejection_notes'] ?? $_POST['reason'] ?? ''));
        if ($rejectionNotes === '') {
            $_SESSION['error'] = 'Red gerekçesi belirtilmelidir.';
            $this->redirect('/purchase-requests/show?id=' . $id);
            return;
        }

        try {
            $this->requestModel->reject($id, $currentUserId, $rejectionNotes);
            $_SESSION['success'] = 'Talep reddedildi.';
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Talep reddedilemedi: ' . $e->getMessage();
        }

        $this->redirect('/purchase-requests/show?id=' . $id);
    }

    /**
     * Talebi iptal eder: DRAFT/SUBMITTED -> CANCELLED (POST /purchase-requests/cancel)
     */
    public function cancel(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/purchase-requests');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = 'Geçersiz talep ID.';
            $this->redirect('/purchase-requests');
            return;
        }

        $reason = isset($_POST['reason']) ? trim((string)$_POST['reason']) : (isset($_POST['approval_notes']) ? trim((string)$_POST['approval_notes']) : null);

        try {
            $this->requestModel->cancel($id, $reason);
            $_SESSION['success'] = 'Talep iptal edildi.';
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Talep iptal edilemedi: ' . $e->getMessage();
        }

        $this->redirect('/purchase-requests/show?id=' . $id);
    }

    /**
     * HTTP isteğinden malzeme kalemlerini hem nesne dizisi hem paralel dizi formatlarını destekleyerek ayrıştırır.
     */
    private function parseItemsFromRequest(): array
    {
        $items = [];

        // Format 1: items[0][material_id], items[0][requested_quantity], ...
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
                    'material_id'           => $materialId,
                    'requested_quantity'    => $rawItem['requested_quantity'] ?? 0,
                    'estimated_unit_price'  => isset($rawItem['estimated_unit_price']) && $rawItem['estimated_unit_price'] !== '' ? $rawItem['estimated_unit_price'] : null,
                    'currency'              => trim((string)($rawItem['currency'] ?? 'TL')),
                    'suggested_supplier_id' => !empty($rawItem['suggested_supplier_id']) ? (int)$rawItem['suggested_supplier_id'] : null,
                    'notes'                 => trim((string)($rawItem['notes'] ?? '')),
                ];
            }
            return $items;
        }

        // Format 2: material_id[], requested_quantity[], estimated_unit_price[], ...
        if (isset($_POST['material_id']) && is_array($_POST['material_id'])) {
            $count = count($_POST['material_id']);
            for ($i = 0; $i < $count; $i++) {
                $materialId = (int)($_POST['material_id'][$i] ?? 0);
                if ($materialId <= 0) {
                    continue;
                }
                $items[] = [
                    'material_id'           => $materialId,
                    'requested_quantity'    => $_POST['requested_quantity'][$i] ?? 0,
                    'estimated_unit_price'  => isset($_POST['estimated_unit_price'][$i]) && $_POST['estimated_unit_price'][$i] !== '' ? $_POST['estimated_unit_price'][$i] : null,
                    'currency'              => trim((string)($_POST['currency'][$i] ?? 'TL')),
                    'suggested_supplier_id' => !empty($_POST['suggested_supplier_id'][$i]) ? (int)$_POST['suggested_supplier_id'][$i] : null,
                    'notes'                 => trim((string)($_POST['notes'][$i] ?? '')),
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

