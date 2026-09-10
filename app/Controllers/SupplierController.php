<?php

require_once __DIR__ . '/../Models/Supplier.php';

class SupplierController
{
    private Supplier $supplier;

    public function __construct(PDO $pdo)
    {
        $this->supplier = new Supplier($pdo);
    }

    public function index(): void
    {
        $suppliers = $this->supplier->getAllActive();

        require __DIR__ . '/../../views/suppliers/index.php';
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
                    $this->supplier->create($this->databaseData($formData));
                    header('Location: /stok-takip/public/suppliers');
                    exit;
                } catch (PDOException $exception) {
                    $error = $this->databaseError($exception);
                }
            }
        }

        $formTitle = 'Yeni Tedarikçi';
        $formAction = '/stok-takip/public/suppliers/create';

        require __DIR__ . '/../../views/suppliers/form.php';
    }

    public function edit(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $supplier = $this->supplier->findActiveById($id);

        if ($supplier === null) {
            http_response_code(404);
            echo '404 - Tedarikçi bulunamadı.';
            return;
        }

        $formData = $supplier;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = array_merge($supplier, $this->readFormData());
            $error = $this->validateFormData($formData);

            if ($error === null) {
                try {
                    $this->supplier->update($id, $this->databaseData($formData));
                    header('Location: /stok-takip/public/suppliers');
                    exit;
                } catch (PDOException $exception) {
                    $error = $this->databaseError($exception);
                }
            }
        }

        $formTitle = 'Tedarikçi Düzenle';
        $formAction = '/stok-takip/public/suppliers/edit?id=' . $id;

        require __DIR__ . '/../../views/suppliers/form.php';
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
            $this->supplier->deactivate($id);
        }

        header('Location: /stok-takip/public/suppliers');
        exit;
    }

    private function emptyFormData(): array
    {
        return [
            'code' => '',
            'name' => '',
            'contact_name' => '',
            'phone' => '',
            'email' => '',
            'address' => '',
        ];
    }

    private function readFormData(): array
    {
        return [
            'code' => trim((string) ($_POST['code'] ?? '')),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'contact_name' => trim((string) ($_POST['contact_name'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'address' => trim((string) ($_POST['address'] ?? '')),
        ];
    }

    private function validateFormData(array $data): ?string
    {
        if ($data['code'] === '' || $data['name'] === '') {
            return 'Tedarikçi kodu ve firma adı zorunludur.';
        }

        if (strlen($data['code']) > 30 || strlen($data['name']) > 150) {
            return 'Tedarikçi kodu veya firma adı izin verilen uzunluğu aşıyor.';
        }

        if (strlen($data['contact_name']) > 100 || strlen($data['phone']) > 30 || strlen($data['email']) > 150) {
            return 'İletişim alanlarından biri izin verilen uzunluğu aşıyor.';
        }

        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return 'Geçerli bir e-posta adresi girin.';
        }

        return null;
    }

    private function databaseData(array $data): array
    {
        return [
            'code' => $data['code'],
            'name' => $data['name'],
            'contact_name' => $data['contact_name'] === '' ? null : $data['contact_name'],
            'phone' => $data['phone'] === '' ? null : $data['phone'],
            'email' => $data['email'] === '' ? null : $data['email'],
            'address' => $data['address'] === '' ? null : $data['address'],
        ];
    }

    private function databaseError(PDOException $exception): string
    {
        if ($exception->getCode() === '23000') {
            return 'Bu tedarikçi kodu zaten kullanılıyor.';
        }

        return 'Tedarikçi kaydedilirken bir hata oluştu.';
    }
}
