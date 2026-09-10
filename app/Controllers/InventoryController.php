<?php

require_once __DIR__ . '/../Models/Inventory.php';
require_once __DIR__ . '/../Services/XlsxExportService.php';

class InventoryController
{
    private Inventory $inventory;

    public function __construct(PDO $pdo)
    {
        $this->inventory = new Inventory($pdo);
    }

    public function index(): void
    {
        $filters = $this->readFilters();
        $filterOptions = $this->inventory->getFilterOptions();
        $assets = $this->inventory->getAssets($filters);
        $kpis = $this->inventory->getKpis();

        $pageTitle = 'Envanter Takibi';
        $activePage = 'inventory';

        require __DIR__ . '/../../views/inventory/index.php';
    }

    public function export(): void
    {
        try {
            $filters = $this->readFilters();
            $assets = $this->inventory->getAssets($filters);

            $statusLabels = [
                'IN_STOCK'  => 'Stokta',
                'ASSIGNED'  => 'Tahsisli',
                'IN_USE'    => 'Kullanımda',
                'IN_REPAIR' => 'Bakımda',
                'LOST'      => 'Kayıp',
                'RETIRED'   => 'Emekli',
                'DISPOSED'  => 'Zayi',
            ];

            $formatDate = static function (?string $date): string {
                if (empty($date)) {
                    return '';
                }
                $timestamp = strtotime($date);
                return $timestamp ? date('d.m.Y', $timestamp) : $date;
            };

            $headers = [
                'Varlık Kodu',
                'Varlık Adı',
                'Kategori',
                'Seri No',
                'Üretici',
                'Model No',
                'Durum',
                'Sorumlu Kişi',
                'Depo',
                'Lokasyon',
                'Üretim Hattı',
                'Tedarikçi',
                'Satın Alma Tarihi',
                'Satın Alma Bedeli',
                'Para Birimi',
                'Fatura No',
                'Garanti Başlangıcı',
                'Garanti Bitişi',
                'Açıklama',
            ];

            $rows = [];
            foreach ($assets as $asset) {
                $statusKey = (string)($asset['status'] ?? '');
                $statusText = $statusLabels[$statusKey] ?? $statusKey;

                $purchaseCost = null;
                if (isset($asset['purchase_cost']) && $asset['purchase_cost'] !== null && $asset['purchase_cost'] !== '') {
                    $purchaseCost = (float)$asset['purchase_cost'];
                }

                $rows[] = [
                    $asset['asset_code'] ?? '',
                    $asset['asset_name'] ?? '',
                    $asset['category_name'] ?? '',
                    $asset['serial_no'] ?? '',
                    $asset['manufacturer'] ?? '',
                    $asset['model_no'] ?? '',
                    $statusText,
                    $asset['responsible_name'] ?? '',
                    $asset['warehouse_name'] ?? '',
                    $asset['location_name'] ?? '',
                    $asset['production_line_name'] ?? '',
                    $asset['supplier_name'] ?? '',
                    $formatDate($asset['purchase_date'] ?? null),
                    $purchaseCost,
                    $asset['currency'] ?? '',
                    $asset['invoice_no'] ?? '',
                    $formatDate($asset['warranty_start_date'] ?? null),
                    $formatDate($asset['warranty_end_date'] ?? null),
                    $asset['description'] ?? '',
                ];
            }

            $filename = 'envanter_' . date('Y-m-d') . '.xlsx';
            $exportService = new XlsxExportService();
            $exportService->download($filename, 'Envanter', $headers, $rows);

        } catch (Throwable $e) {
            http_response_code(500);
            echo "Envanter dışa aktarılırken bir hata oluştu. Lütfen tekrar deneyiniz.";
        }
    }

    public function suggestions(): void
    {
        $allowedFields = ['asset_name', 'manufacturer', 'model_no'];
        $field = trim((string) ($_GET['field'] ?? ''));
        $query = trim((string) ($_GET['q'] ?? ''));

        if (!in_array($field, $allowedFields, true)) {
            $this->jsonResponse(['success' => false, 'suggestions' => []], 400);
            return;
        }

        if (mb_strlen($query) > 100) {
            $this->jsonResponse(['success' => false, 'suggestions' => []], 400);
            return;
        }

        $suggestions = $query === '' ? [] : $this->inventory->getSuggestions($field, $query);
        $this->jsonResponse(['success' => true, 'suggestions' => $suggestions]);
    }

    public function create(): void
    {
        $formData = $this->emptyFormData();
        $formData['asset_code'] = $this->inventory->generateAssetCode();
        $error = null;
        $filterOptions = $this->inventory->getFilterOptions();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = $this->readFormData();
            $formData['asset_code'] = $this->inventory->generateAssetCode();
            $error = $this->validateFormData($formData);

            if ($error === null) {
                try {
                    $assetId = $this->inventory->create($this->databaseData($formData));
                    header('Location: /stok-takip/public/inventory/show?id=' . $assetId);
                    exit;
                } catch (PDOException $exception) {
                    if (($exception->errorInfo[1] ?? null) !== 1062) {
                        throw $exception;
                    }
                    $error = $this->databaseError($exception);
                }
            }
        }

        $formTitle = 'Yeni Envanter';
        $formAction = '/stok-takip/public/inventory/create';
        $pageTitle = $formTitle;
        $activePage = 'inventory';

        require __DIR__ . '/../../views/inventory/create.php';
    }

    public function show(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $asset = $this->inventory->findById($id);

        if ($asset === null) {
            http_response_code(404);
            echo '404 - Envanter varlığı bulunamadı.';
            return;
        }

        $movements = $this->inventory->getMovements($id);
        $activeUsers = $this->inventory->getActiveUsersForAssignment();
        $filterOptions = $this->inventory->getFilterOptions();
        $pageTitle = 'Envanter Detayı: ' . $asset['asset_name'];
        $activePage = 'inventory';

        require __DIR__ . '/../../views/inventory/show.php';
    }

    public function assign(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Geçersiz istek yöntemi.';
            return;
        }

        $assetId = $this->positiveIntOrNull($_POST['asset_id'] ?? null);
        $toUserId = $this->positiveIntOrNull($_POST['to_user_id'] ?? null);

        if ($assetId === null) {
            $_SESSION['error'] = 'Geçersiz varlık seçimi.';
            header('Location: /stok-takip/public/inventory');
            exit;
        }

        if ($toUserId === null) {
            $_SESSION['error'] = 'Lütfen tahsis edilecek personeli/kullanıcıyı seçiniz.';
            header('Location: /stok-takip/public/inventory/show?id=' . $assetId);
            exit;
        }

        $movementDate = trim((string) ($_POST['movement_date'] ?? ''));
        if ($movementDate !== '' && !$this->isValidDate($movementDate)) {
            $_SESSION['error'] = 'Geçersiz tahsis tarihi formatı.';
            header('Location: /stok-takip/public/inventory/show?id=' . $assetId);
            exit;
        }

        $meta = [
            'movement_date' => $movementDate !== '' ? $movementDate : date('Y-m-d'),
            'reference_no'  => trim((string) ($_POST['reference_no'] ?? '')),
            'reason'        => trim((string) ($_POST['reason'] ?? '')),
            'notes'         => trim((string) ($_POST['notes'] ?? '')),
        ];

        $performedByUserId = (int) ($_SESSION['user_id'] ?? 0);

        try {
            $this->inventory->assignAsset($assetId, $toUserId, $performedByUserId, $meta);
            $_SESSION['success'] = 'Varlık başarıyla ilgili çalışana tahsis edildi.';
        } catch (InvalidArgumentException | RuntimeException $e) {
            $_SESSION['error'] = $e->getMessage();
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Varlık tahsis edilirken beklenmeyen bir hata oluştu.';
        }

        header('Location: /stok-takip/public/inventory/show?id=' . $assetId);
        exit;
    }

    public function transfer(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Geçersiz istek yöntemi.';
            return;
        }

        $assetId = $this->positiveIntOrNull($_POST['asset_id'] ?? null);
        $toUserId = $this->positiveIntOrNull($_POST['to_user_id'] ?? null);

        if ($assetId === null) {
            $_SESSION['error'] = 'Geçersiz varlık seçimi.';
            header('Location: /stok-takip/public/inventory');
            exit;
        }

        if ($toUserId === null) {
            $_SESSION['error'] = 'Lütfen varlığın devredileceği yeni çalışanı/kullanıcıyı seçiniz.';
            header('Location: /stok-takip/public/inventory/show?id=' . $assetId);
            exit;
        }

        $movementDate = trim((string) ($_POST['movement_date'] ?? ''));
        if ($movementDate !== '' && !$this->isValidDate($movementDate)) {
            $_SESSION['error'] = 'Geçersiz devir tarihi formatı.';
            header('Location: /stok-takip/public/inventory/show?id=' . $assetId);
            exit;
        }

        $meta = [
            'movement_date' => $movementDate !== '' ? $movementDate : date('Y-m-d'),
            'reference_no'  => trim((string) ($_POST['reference_no'] ?? '')),
            'reason'        => trim((string) ($_POST['reason'] ?? '')),
            'notes'         => trim((string) ($_POST['notes'] ?? '')),
        ];

        $performedByUserId = (int) ($_SESSION['user_id'] ?? 0);

        try {
            $this->inventory->transferAsset($assetId, $toUserId, $performedByUserId, $meta);
            $_SESSION['success'] = 'Varlık sorumluluğu başarıyla yeni çalışana devredildi.';
        } catch (InvalidArgumentException | RuntimeException $e) {
            $_SESSION['error'] = $e->getMessage();
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Varlık devredilirken beklenmeyen bir hata oluştu.';
        }

        header('Location: /stok-takip/public/inventory/show?id=' . $assetId);
        exit;
    }

    public function returnAsset(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Geçersiz istek yöntemi.';
            return;
        }

        $assetId = $this->positiveIntOrNull($_POST['asset_id'] ?? null);
        $warehouseId = $this->positiveIntOrNull($_POST['warehouse_id'] ?? null);
        $locationId = $this->positiveIntOrNull($_POST['location_id'] ?? null);

        if ($assetId === null) {
            $_SESSION['error'] = 'Geçersiz varlık seçimi.';
            header('Location: /stok-takip/public/inventory');
            exit;
        }

        if ($warehouseId === null || $locationId === null) {
            $_SESSION['error'] = 'Lütfen teslim alınan depo ve lokasyon bilgilerini eksiksiz seçiniz.';
            header('Location: /stok-takip/public/inventory/show?id=' . $assetId);
            exit;
        }

        $movementDate = trim((string) ($_POST['movement_date'] ?? ''));
        if ($movementDate !== '' && !$this->isValidDate($movementDate)) {
            $_SESSION['error'] = 'Geçersiz iade tarihi formatı.';
            header('Location: /stok-takip/public/inventory/show?id=' . $assetId);
            exit;
        }

        $meta = [
            'movement_date' => $movementDate !== '' ? $movementDate : date('Y-m-d'),
            'reference_no'  => trim((string) ($_POST['reference_no'] ?? '')),
            'reason'        => trim((string) ($_POST['reason'] ?? '')),
            'notes'         => trim((string) ($_POST['notes'] ?? '')),
        ];

        $performedByUserId = (int) ($_SESSION['user_id'] ?? 0);

        try {
            $this->inventory->returnAsset($assetId, $performedByUserId, $warehouseId, $locationId, $meta);
            $_SESSION['success'] = 'Varlık başarıyla depoya iade alındı ve stok statüsüne getirildi.';
        } catch (InvalidArgumentException | RuntimeException $e) {
            $_SESSION['error'] = $e->getMessage();
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Varlık iade edilirken beklenmeyen bir hata oluştu.';
        }

        header('Location: /stok-takip/public/inventory/show?id=' . $assetId);
        exit;
    }

    public function edit(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $asset = $this->inventory->findById($id);

        if ($asset === null) {
            http_response_code(404);
            echo '404 - Envanter varlığı bulunamadı.';
            return;
        }

        $formData = $asset;
        $error = null;
        $filterOptions = $this->inventory->getFilterOptions();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = array_merge($asset, $this->readFormData());
            $error = $this->validateFormData($formData);

            if ($error === null) {
                try {
                    $this->inventory->update($id, $this->databaseData($formData));
                    header('Location: /stok-takip/public/inventory/show?id=' . $id);
                    exit;
                } catch (PDOException $exception) {
                    if (($exception->errorInfo[1] ?? null) !== 1062) {
                        throw $exception;
                    }
                    $error = $this->databaseError($exception);
                }
            }
        }

        $formTitle = 'Envanter Düzenle';
        $formAction = '/stok-takip/public/inventory/edit?id=' . $id;
        $pageTitle = $formTitle;
        $activePage = 'inventory';

        require __DIR__ . '/../../views/inventory/edit.php';
    }

    private function emptyFormData(): array
    {
        return [
            'asset_code' => '',
            'asset_name' => '',
            'inventory_category_id' => '',
            'serial_no' => '',
            'manufacturer' => '',
            'model_no' => '',
            'supplier_id' => '',
            'purchase_date' => '',
            'purchase_cost' => '0.0000',
            'currency' => 'TRY',
            'invoice_no' => '',
            'warranty_start_date' => '',
            'warranty_end_date' => '',
            'status' => 'IN_STOCK',
            'warehouse_id' => '',
            'location_id' => '',
            'production_line_id' => '',
            'responsible_user_id' => '',
            'maintenance_asset_id' => '',
            'description' => '',
        ];
    }

    private function readFilters(): array
    {
        $status = trim((string) ($_GET['status'] ?? ''));
        if (!in_array($status, Inventory::statuses(), true)) {
            $status = '';
        }

        $search = trim((string) ($_GET['search'] ?? ''));
        if (mb_strlen($search) > 100) {
            $search = mb_substr($search, 0, 100);
        }

        $categoryId = $this->positiveIntOrNull($_GET['category_id'] ?? ($_GET['inventory_category_id'] ?? null));

        return [
            'search' => $search,
            'category_id' => $categoryId,
            'status' => $status,
            'warehouse_id' => $this->positiveIntOrNull($_GET['warehouse_id'] ?? null),
            'responsible_user_id' => $this->positiveIntOrNull($_GET['responsible_user_id'] ?? null),
        ];
    }

    private function readFormData(): array
    {
        return [
            'asset_name' => trim((string) ($_POST['asset_name'] ?? '')),
            'inventory_category_id' => $this->positiveIntOrNull($_POST['inventory_category_id'] ?? null),
            'serial_no' => trim((string) ($_POST['serial_no'] ?? '')),
            'manufacturer' => trim((string) ($_POST['manufacturer'] ?? '')),
            'model_no' => trim((string) ($_POST['model_no'] ?? '')),
            'supplier_id' => $this->positiveIntOrNull($_POST['supplier_id'] ?? null),
            'purchase_date' => trim((string) ($_POST['purchase_date'] ?? '')),
            'purchase_cost' => trim((string) ($_POST['purchase_cost'] ?? '0')),
            'currency' => strtoupper(trim((string) ($_POST['currency'] ?? 'TRY'))),
            'invoice_no' => trim((string) ($_POST['invoice_no'] ?? '')),
            'warranty_start_date' => trim((string) ($_POST['warranty_start_date'] ?? '')),
            'warranty_end_date' => trim((string) ($_POST['warranty_end_date'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? '')),
            'warehouse_id' => $this->positiveIntOrNull($_POST['warehouse_id'] ?? null),
            'location_id' => $this->positiveIntOrNull($_POST['location_id'] ?? null),
            'production_line_id' => $this->positiveIntOrNull($_POST['production_line_id'] ?? null),
            'responsible_user_id' => $this->positiveIntOrNull($_POST['responsible_user_id'] ?? null),
            'maintenance_asset_id' => $this->positiveIntOrNull($_POST['maintenance_asset_id'] ?? null),
            'description' => trim((string) ($_POST['description'] ?? '')),
        ];
    }

    private function validateFormData(array $data): ?string
    {
        if ($data['asset_code'] === '' || $data['asset_name'] === '' || empty($data['inventory_category_id'])) {
            return 'Varlık kodu, varlık adı ve kategori zorunludur.';
        }
        if (mb_strlen($data['asset_code']) > 50 || mb_strlen($data['asset_name']) > 150) {
            return 'Varlık kodu veya varlık adı izin verilen uzunluğu aşıyor.';
        }
        if (mb_strlen($data['serial_no']) > 100 || mb_strlen($data['manufacturer']) > 100 || mb_strlen($data['model_no']) > 100) {
            return 'Seri no, marka veya model alanı izin verilen uzunluğu aşıyor.';
        }
        if (mb_strlen($data['currency']) < 3 || mb_strlen($data['currency']) > 10 || !preg_match('/^[A-Z]+$/', $data['currency'])) {
            return 'Para birimi yalnızca 3-10 harf içermelidir.';
        }
        if (!is_numeric($data['purchase_cost']) || (float) $data['purchase_cost'] < 0) {
            return 'Satın alma maliyeti sıfır veya daha büyük bir sayı olmalıdır.';
        }
        if ((float) $data['purchase_cost'] > 9999999999999.9999) {
            return 'Satın alma maliyeti izin verilen sınırı aşıyor.';
        }
        if (!in_array($data['status'], Inventory::statuses(), true)) {
            return 'Seçilen envanter durumu geçersiz.';
        }

        if (in_array($data['status'], ['ASSIGNED', 'IN_USE'], true) && empty($data['responsible_user_id'])) {
            return 'Tahsisli veya kullanımda olan varlıklar için sorumlu kullanıcı seçilmelidir.';
        }

        $hasWarehouse = !empty($data['warehouse_id']);
        $hasLocation = !empty($data['location_id']);
        if ($hasWarehouse !== $hasLocation) {
            return 'Depo ve lokasyon birlikte seçilmelidir.';
        }
        if ($data['status'] === 'IN_STOCK' && (!$hasWarehouse || !$hasLocation)) {
            return 'Stokta olan varlıklar için depo ve lokasyon seçilmelidir.';
        }

        foreach (['purchase_date', 'warranty_start_date', 'warranty_end_date'] as $dateField) {
            if ($data[$dateField] !== '' && !$this->isValidDate($data[$dateField])) {
                return 'Tarih alanlarından biri geçersiz.';
            }
        }
        if ($data['warranty_start_date'] !== '' && $data['warranty_end_date'] !== '' && $data['warranty_end_date'] < $data['warranty_start_date']) {
            return 'Garanti bitiş tarihi başlangıç tarihinden önce olamaz.';
        }

        return $this->inventory->validateReferences($data);
    }

    private function databaseData(array $data): array
    {
        return [
            'asset_code' => $data['asset_code'],
            'asset_name' => $data['asset_name'],
            'inventory_category_id' => $data['inventory_category_id'],
            'serial_no' => $data['serial_no'] === '' ? null : $data['serial_no'],
            'manufacturer' => $data['manufacturer'] === '' ? null : $data['manufacturer'],
            'model_no' => $data['model_no'] === '' ? null : $data['model_no'],
            'supplier_id' => $data['supplier_id'],
            'purchase_date' => $data['purchase_date'] === '' ? null : $data['purchase_date'],
            'purchase_cost' => number_format((float) $data['purchase_cost'], 4, '.', ''),
            'currency' => $data['currency'],
            'invoice_no' => $data['invoice_no'] === '' ? null : $data['invoice_no'],
            'warranty_start_date' => $data['warranty_start_date'] === '' ? null : $data['warranty_start_date'],
            'warranty_end_date' => $data['warranty_end_date'] === '' ? null : $data['warranty_end_date'],
            'status' => $data['status'],
            'warehouse_id' => $data['warehouse_id'],
            'location_id' => $data['location_id'],
            'production_line_id' => $data['production_line_id'],
            'responsible_user_id' => $data['responsible_user_id'],
            'maintenance_asset_id' => $data['maintenance_asset_id'],
            'description' => $data['description'] === '' ? null : $data['description'],
            'created_by' => $_SESSION['user_id'] ?? null,
            'updated_by' => $_SESSION['user_id'] ?? null,
        ];
    }

    private function databaseError(PDOException $exception): string
    {
        if (($exception->errorInfo[1] ?? null) === 1062) {
            if (str_contains((string) ($exception->errorInfo[2] ?? ''), 'maintenance')) {
                return 'Bu bakım varlığı başka bir envanter kaydına bağlı.';
            }
            return 'Bu varlık kodu zaten kullanılıyor.';
        }

        return 'Envanter kaydedilirken bir hata oluştu.';
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        $number = (int) $value;
        return $number > 0 ? $number : null;
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function jsonResponse(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
