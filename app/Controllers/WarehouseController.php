<?php

require_once __DIR__ . '/../Models/Warehouse.php';

class WarehouseController
{
    private Warehouse $warehouse;

    public function __construct(PDO $pdo)
    {
        $this->warehouse = new Warehouse($pdo);
    }

    public function index(): void
    {
        $warehouses = $this->warehouse->getAllActive();

        require __DIR__ . '/../../views/warehouses/index.php';
    }

    public function create(): void
    {
        $formData = $this->emptyFormData();
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = $this->readFormData();
            $error = $this->validateFormData($formData);

            if ($error === null) {
                try {
                    $this->warehouse->create($this->databaseData($formData));
                    header('Location: /stok-takip/public/warehouses');
                    exit;
                } catch (PDOException $exception) {
                    $error = $this->databaseError($exception);
                }
            }
        }

        $formTitle = 'Yeni Depo';
        $formAction = '/stok-takip/public/warehouses/create';

        require __DIR__ . '/../../views/warehouses/form.php';
    }

    public function edit(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $warehouse = $this->warehouse->findActiveById($id);

        if ($warehouse === null) {
            http_response_code(404);
            echo '404 - Depo bulunamadı.';
            return;
        }

        $formData = $warehouse;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = array_merge($warehouse, $this->readFormData());
            $error = $this->validateFormData($formData);

            if ($error === null) {
                try {
                    $this->warehouse->update($id, $this->databaseData($formData));
                    header('Location: /stok-takip/public/warehouses');
                    exit;
                } catch (PDOException $exception) {
                    $error = $this->databaseError($exception);
                }
            }
        }

        $formTitle = 'Depo Düzenle';
        $formAction = '/stok-takip/public/warehouses/edit?id=' . $id;

        require __DIR__ . '/../../views/warehouses/form.php';
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
            $this->warehouse->deactivate($id);
        }

        header('Location: /stok-takip/public/warehouses');
        exit;
    }

    private function emptyFormData(): array
    {
        return [
            'code' => '',
            'name' => '',
            'description' => '',
        ];
    }

    private function readFormData(): array
    {
        return [
            'code' => trim((string) ($_POST['code'] ?? '')),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
        ];
    }

    private function validateFormData(array $data): ?string
    {
        if ($data['code'] === '' || $data['name'] === '') {
            return 'Depo kodu ve depo adı zorunludur.';
        }

        if (strlen($data['code']) > 30 || strlen($data['name']) > 100) {
            return 'Depo kodu veya depo adı izin verilen uzunluğu aşıyor.';
        }

        return null;
    }

    private function databaseData(array $data): array
    {
        return [
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] === '' ? null : $data['description'],
        ];
    }

    private function databaseError(PDOException $exception): string
    {
        if ($exception->getCode() === '23000') {
            return 'Bu depo kodu zaten kullanılıyor.';
        }

        return 'Depo kaydedilirken bir hata oluştu.';
    }
}
