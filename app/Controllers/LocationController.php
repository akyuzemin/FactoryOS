<?php

require_once __DIR__ . '/../Models/Location.php';

class LocationController
{
    private Location $location;

    public function __construct(PDO $pdo)
    {
        $this->location = new Location($pdo);
    }

    public function index(): void
    {
        $warehouseId = !empty($_GET['warehouse_id']) ? (int) $_GET['warehouse_id'] : null;
        if ($warehouseId !== null && $warehouseId <= 0) {
            $warehouseId = null;
        }

        $locations = $this->location->getAllActive($warehouseId);
        $warehouses = $this->location->getWarehouseOptions();

        require __DIR__ . '/../../views/locations/index.php';
    }

    public function create(): void
    {
        $formData = $this->emptyFormData();
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = $this->readFormData();
            $error = $this->validateFormData($formData);

            if ($error === null) {
                $warehouseId = (int) $formData['warehouse_id'];
                $code = $formData['code'];

                if ($this->location->isCodeExists($warehouseId, $code)) {
                    $error = "Seçilen depoda '{$code}' raf kodu zaten kullanılıyor.";
                } else {
                    try {
                        $this->location->create($this->databaseData($formData));
                        header('Location: /stok-takip/public/locations');
                        exit;
                    } catch (PDOException $exception) {
                        $error = $this->databaseError($exception);
                    }
                }
            }
        }

        $warehouses = $this->location->getWarehouseOptions();
        $formTitle = 'Yeni Raf / Lokasyon';
        $formAction = '/stok-takip/public/locations/create';

        require __DIR__ . '/../../views/locations/form.php';
    }

    public function edit(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $location = $this->location->findActiveById($id);

        if ($location === null) {
            http_response_code(404);
            echo '404 - Raf / Lokasyon bulunamadı.';
            return;
        }

        $formData = $location;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = array_merge($location, $this->readFormData());
            $error = $this->validateFormData($formData);

            if ($error === null) {
                $warehouseId = (int) $formData['warehouse_id'];
                $code = $formData['code'];

                if ($this->location->isCodeExists($warehouseId, $code, $id)) {
                    $error = "Seçilen depoda '{$code}' raf kodu zaten kullanılıyor.";
                } else {
                    try {
                        $this->location->update($id, $this->databaseData($formData));
                        header('Location: /stok-takip/public/locations');
                        exit;
                    } catch (PDOException $exception) {
                        $error = $this->databaseError($exception);
                    }
                }
            }
        }

        $warehouses = $this->location->getWarehouseOptions();
        $formTitle = 'Raf / Lokasyon Düzenle';
        $formAction = '/stok-takip/public/locations/edit?id=' . $id;

        require __DIR__ . '/../../views/locations/form.php';
    }

    public function delete(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo '405 - Geçersiz istek.';
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {
            $stock = $this->location->getTotalStock($id);

            if ($stock > 0) {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['error'] = 'Bu rafta ' . number_format($stock, 3, ',', '.') . ' miktar stok bulunmaktadır. Pasifleştirmek için önce stok transferi veya çıkışı yapılmalıdır.';
                header('Location: /stok-takip/public/locations');
                exit;
            }

            $this->location->deactivate($id);
        }

        header('Location: /stok-takip/public/locations');
        exit;
    }

    private function emptyFormData(): array
    {
        return [
            'warehouse_id' => '',
            'code'         => '',
            'name'         => '',
            'description'  => '',
        ];
    }

    private function readFormData(): array
    {
        return [
            'warehouse_id' => trim((string) ($_POST['warehouse_id'] ?? '')),
            'code'         => trim((string) ($_POST['code'] ?? '')),
            'name'         => trim((string) ($_POST['name'] ?? '')),
            'description'  => trim((string) ($_POST['description'] ?? '')),
        ];
    }

    private function validateFormData(array $data): ?string
    {
        if (!ctype_digit($data['warehouse_id']) || (int) $data['warehouse_id'] < 1) {
            return 'Lütfen geçerli bir depo seçin.';
        }

        // Depo aktif mi kontrol et
        $warehouses = $this->location->getWarehouseOptions();
        $validWarehouseIds = array_column($warehouses, 'id');
        if (!in_array((int) $data['warehouse_id'], array_map('intval', $validWarehouseIds), true)) {
            return 'Seçilen depo bulunamadı veya pasif durumda.';
        }

        if ($data['code'] === '' || $data['name'] === '') {
            return 'Raf kodu ve raf adı zorunludur.';
        }

        if (strlen($data['code']) > 30) {
            return 'Raf kodu en fazla 30 karakter olabilir.';
        }

        if (strlen($data['name']) > 100) {
            return 'Raf adı en fazla 100 karakter olabilir.';
        }

        return null;
    }

    private function databaseData(array $data): array
    {
        return [
            'warehouse_id' => (int) $data['warehouse_id'],
            'code'         => $data['code'],
            'name'         => $data['name'],
            'description'  => $data['description'] === '' ? null : $data['description'],
        ];
    }

    private function databaseError(PDOException $exception): string
    {
        if ($exception->getCode() === '23000') {
            return 'Bu depoda bu raf kodu zaten kullanılıyor veya seçilen depo geçersiz.';
        }

        return 'Raf kaydedilirken bir hata oluştu.';
    }
}

