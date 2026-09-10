<?php

require_once __DIR__ . '/../Models/Stock.php';

class StockController
{
    private Stock $stock;

    public function __construct(PDO $pdo)
    {
        $this->stock = new Stock($pdo);
    }

    public function in(): void
    {
        $formData = [
            'material_id' => trim((string) ($_GET['material_id'] ?? '')),
            'location_id' => '',
            'quantity' => '',
            'description' => '',
        ];
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = [
                'material_id' => trim((string) ($_POST['material_id'] ?? '')),
                'location_id' => trim((string) ($_POST['location_id'] ?? '')),
                'quantity' => trim((string) ($_POST['quantity'] ?? '')),
                'description' => trim((string) ($_POST['description'] ?? '')),
            ];

            $error = $this->validate($formData);

            if ($error === null) {
                try {
                    $this->stock->recordEntry(
                        (int) $formData['material_id'],
                        (int) $formData['location_id'],
                        (float) $formData['quantity'],
                        (int) $_SESSION['user_id'],
                        $formData['description'] === '' ? null : $formData['description']
                    );

                    header('Location: /stok-takip/public/stock-movements');
                    exit;
                } catch (InvalidArgumentException $exception) {
                    $error = $exception->getMessage();
                } catch (PDOException $exception) {
                    $error = 'Stok girişi kaydedilirken bir hata oluştu.';
                }
            }
        }

        $options = $this->stock->getFormOptions();
        $materials = $options['materials'];
        $locations = $options['locations'];

        require __DIR__ . '/../../views/stock/in.php';
    }

    public function out(): void
    {
        $formData = [
            'material_id' => trim((string) ($_GET['material_id'] ?? '')),
            'location_id' => '',
            'quantity' => '',
            'description' => '',
        ];
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = [
                'material_id' => trim((string) ($_POST['material_id'] ?? '')),
                'location_id' => trim((string) ($_POST['location_id'] ?? '')),
                'quantity' => trim((string) ($_POST['quantity'] ?? '')),
                'description' => trim((string) ($_POST['description'] ?? '')),
            ];

            $error = $this->validate($formData);

            if ($error === null) {
                try {
                    $this->stock->recordOut(
                        (int) $formData['material_id'],
                        (int) $formData['location_id'],
                        (float) $formData['quantity'],
                        (int) $_SESSION['user_id'],
                        $formData['description'] === '' ? null : $formData['description']
                    );

                    header('Location: /stok-takip/public/stock-movements');
                    exit;
                } catch (InvalidArgumentException $exception) {
                    $error = $exception->getMessage();
                } catch (PDOException $exception) {
                    $error = 'Stok çıkışı kaydedilirken bir hata oluştu.';
                }
            }
        }

        $options = $this->stock->getFormOptions();
        $materials = $options['materials'];
        $locations = $options['locations'];

        require __DIR__ . '/../../views/stock/out.php';
    }

    public function transfer(): void
    {
        $formData = [
            'material_id' => trim((string) ($_GET['material_id'] ?? '')),
            'source_location_id' => '',
            'target_location_id' => '',
            'quantity' => '',
            'description' => '',
        ];
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = [
                'material_id' => trim((string) ($_POST['material_id'] ?? '')),
                'source_location_id' => trim((string) ($_POST['source_location_id'] ?? '')),
                'target_location_id' => trim((string) ($_POST['target_location_id'] ?? '')),
                'quantity' => trim((string) ($_POST['quantity'] ?? '')),
                'description' => trim((string) ($_POST['description'] ?? '')),
            ];

            $error = $this->validateTransfer($formData);

            if ($error === null) {
                try {
                    $this->stock->recordTransfer(
                        (int) $formData['material_id'],
                        (int) $formData['source_location_id'],
                        (int) $formData['target_location_id'],
                        (float) $formData['quantity'],
                        (int) $_SESSION['user_id'],
                        $formData['description'] === '' ? null : $formData['description']
                    );

                    header('Location: /stok-takip/public/stock-movements');
                    exit;
                } catch (InvalidArgumentException $exception) {
                    $error = $exception->getMessage();
                } catch (PDOException $exception) {
                    $error = 'Stok transferi kaydedilirken bir hata oluştu.';
                }
            }
        }

        $options = $this->stock->getFormOptions();
        $materials = $options['materials'];
        $locations = $options['locations'];

        require __DIR__ . '/../../views/stock/transfer.php';
    }

    public function return(): void
    {
        $formData = [
            'material_id' => trim((string) ($_GET['material_id'] ?? '')),
            'source_location_id' => '',
            'target_location_id' => '',
            'quantity' => '',
            'description' => '',
        ];
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = [
                'material_id' => trim((string) ($_POST['material_id'] ?? '')),
                'source_location_id' => trim((string) ($_POST['source_location_id'] ?? '')),
                'target_location_id' => trim((string) ($_POST['target_location_id'] ?? '')),
                'quantity' => trim((string) ($_POST['quantity'] ?? '')),
                'description' => trim((string) ($_POST['description'] ?? '')),
            ];

            $error = $this->validateTransfer($formData);

            if ($error === null) {
                try {
                    $this->stock->recordReturn(
                        (int) $formData['material_id'],
                        (int) $formData['source_location_id'],
                        (int) $formData['target_location_id'],
                        (float) $formData['quantity'],
                        (int) $_SESSION['user_id'],
                        $formData['description'] === '' ? null : $formData['description']
                    );

                    header('Location: /stok-takip/public/stock-movements');
                    exit;
                } catch (InvalidArgumentException $exception) {
                    $error = $exception->getMessage();
                } catch (PDOException $exception) {
                    $error = 'Stok iadesi kaydedilirken bir hata oluştu.';
                }
            }
        }

        $options = $this->stock->getFormOptions();
        $materials = $options['materials'];
        $locations = $options['locations'];

        require __DIR__ . '/../../views/stock/return.php';
    }

    public function adjustment(): void
    {
        $formData = [
            'material_id' => trim((string) ($_GET['material_id'] ?? '')),
            'location_id' => '',
            'new_quantity' => '',
            'description' => '',
        ];
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = [
                'material_id' => trim((string) ($_POST['material_id'] ?? '')),
                'location_id' => trim((string) ($_POST['location_id'] ?? '')),
                'new_quantity' => trim((string) ($_POST['new_quantity'] ?? '')),
                'description' => trim((string) ($_POST['description'] ?? '')),
            ];

            $error = $this->validateAdjustment($formData);

            if ($error === null) {
                try {
                    $this->stock->recordAdjustment(
                        (int) $formData['material_id'],
                        (int) $formData['location_id'],
                        (float) $formData['new_quantity'],
                        (int) $_SESSION['user_id'],
                        $formData['description'] === '' ? null : $formData['description']
                    );

                    header('Location: /stok-takip/public/stock-movements');
                    exit;
                } catch (InvalidArgumentException $exception) {
                    $error = $exception->getMessage();
                } catch (PDOException $exception) {
                    $error = 'Stok düzeltmesi kaydedilirken bir hata oluştu.';
                }
            }
        }

        $options = $this->stock->getFormOptions();
        $materials = $options['materials'];
        $locations = $options['locations'];

        require __DIR__ . '/../../views/stock/adjustment.php';
    }

    public function scrap(): void
    {
        $formData = [
            'material_id' => trim((string) ($_GET['material_id'] ?? '')),
            'location_id' => '',
            'quantity' => '',
            'description' => '',
        ];
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = [
                'material_id' => trim((string) ($_POST['material_id'] ?? '')),
                'location_id' => trim((string) ($_POST['location_id'] ?? '')),
                'quantity' => trim((string) ($_POST['quantity'] ?? '')),
                'description' => trim((string) ($_POST['description'] ?? '')),
            ];

            $error = $this->validate($formData);

            if ($error === null) {
                try {
                    $this->stock->recordScrap(
                        (int) $formData['material_id'],
                        (int) $formData['location_id'],
                        (float) $formData['quantity'],
                        (int) $_SESSION['user_id'],
                        $formData['description'] === '' ? null : $formData['description']
                    );

                    header('Location: /stok-takip/public/stock-movements');
                    exit;
                } catch (InvalidArgumentException $exception) {
                    $error = $exception->getMessage();
                } catch (PDOException $exception) {
                    $error = 'Fire/hurda kaydı oluşturulurken bir hata oluştu.';
                }
            }
        }

        $options = $this->stock->getFormOptions();
        $materials = $options['materials'];
        $locations = $options['locations'];

        require __DIR__ . '/../../views/stock/scrap.php';
    }

    private function validate(array $data): ?string
    {
        if (!ctype_digit($data['material_id']) || (int) $data['material_id'] < 1) {
            return 'Geçerli bir malzeme seçin.';
        }

        if (!ctype_digit($data['location_id']) || (int) $data['location_id'] < 1) {
            return 'Geçerli bir depo ve raf seçin.';
        }

        if (!is_numeric($data['quantity']) || (float) $data['quantity'] <= 0 || !is_finite((float) $data['quantity'])) {
            return 'Miktar sıfırdan büyük bir sayı olmalıdır.';
        }

        return null;
    }

    private function validateTransfer(array $data): ?string
    {
        $error = $this->validate([
            'material_id' => $data['material_id'],
            'location_id' => $data['source_location_id'],
            'quantity' => $data['quantity'],
        ]);

        if ($error !== null) {
            return $error;
        }

        if (!ctype_digit($data['target_location_id']) || (int) $data['target_location_id'] < 1) {
            return 'Geçerli bir hedef depo ve raf seçin.';
        }

        if ($data['source_location_id'] === $data['target_location_id']) {
            return 'Kaynak ve hedef depo/raf aynı olamaz.';
        }

        return null;
    }

    private function validateAdjustment(array $data): ?string
    {
        if (!ctype_digit($data['material_id']) || (int) $data['material_id'] < 1) {
            return 'Geçerli bir malzeme seçin.';
        }

        if (!ctype_digit($data['location_id']) || (int) $data['location_id'] < 1) {
            return 'Geçerli bir depo ve raf seçin.';
        }

        if (!is_numeric($data['new_quantity']) || (float) $data['new_quantity'] < 0 || !is_finite((float) $data['new_quantity'])) {
            return 'Yeni stok miktarı sıfır veya daha büyük bir sayı olmalıdır.';
        }

        return null;
    }
}
