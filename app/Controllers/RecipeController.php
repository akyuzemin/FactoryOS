<?php

require_once __DIR__ . '/../Models/Recipe.php';

class RecipeController
{
    private Recipe $recipeModel;

    public function __construct(PDO $pdo)
    {
        $this->recipeModel = new Recipe($pdo);
    }

    public function index(): void
    {
        $search = trim($_GET['search'] ?? '');
        $status = isset($_GET['is_active']) && $_GET['is_active'] !== '' ? (int)$_GET['is_active'] : null;

        $recipes = $this->recipeModel->getAll($search !== '' ? $search : null, $status);

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        require __DIR__ . '/../../views/recipes/index.php';
    }

    public function create(): void
    {
        $error = null;
        $formData = [
            'code'               => '',
            'name'               => '',
            'output_material_id' => '',
            'base_quantity'      => '1.000',
            'description'        => '',
            'is_active'          => '1',
        ];
        $formItems = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = [
                'code'               => trim($_POST['code'] ?? ''),
                'name'               => trim($_POST['name'] ?? ''),
                'output_material_id' => trim($_POST['output_material_id'] ?? ''),
                'base_quantity'      => trim($_POST['base_quantity'] ?? '1.000'),
                'description'        => trim($_POST['description'] ?? ''),
                'is_active'          => isset($_POST['is_active']) ? 1 : 0,
            ];

            // Parse dynamic items
            $rawItems = $_POST['items'] ?? [];
            $itemsToSave = [];

            if (is_array($rawItems)) {
                foreach ($rawItems as $raw) {
                    $matId = (int)($raw['material_id'] ?? 0);
                    $qty = (float)($raw['quantity'] ?? 0);
                    $scrap = (float)($raw['scrap_rate_pct'] ?? 0);
                    $isCrit = isset($raw['is_critical']) ? 1 : 0;

                    if ($matId > 0 && $qty > 0) {
                        $itemsToSave[] = [
                            'material_id'    => $matId,
                            'quantity'       => $qty,
                            'scrap_rate_pct' => $scrap,
                            'is_critical'    => $isCrit,
                        ];
                    }
                }
            }

            $formItems = $itemsToSave;

            try {
                if (empty($itemsToSave)) {
                    throw new InvalidArgumentException('Reçeteye en az 1 hammadde/malzeme kalemi eklenmelidir.');
                }

                $recipeId = $this->recipeModel->create($formData, $itemsToSave);

                $_SESSION['flash_success'] = 'Üretim reçetesi (' . htmlspecialchars($formData['name']) . ') başarıyla oluşturuldu.';
                header('Location: /stok-takip/public/recipes');
                exit;
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            } catch (Throwable $e) {
                $error = 'Reçete kaydedilirken beklenmeyen bir hata oluştu: ' . $e->getMessage();
            }
        }

        $availableMaterials = $this->recipeModel->getAvailableMaterials();

        require __DIR__ . '/../../views/recipes/create.php';
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $recipe = $this->recipeModel->getById($id);

        if (!$recipe) {
            $_SESSION['flash_error'] = 'Düzenlenecek reçete bulunamadı.';
            header('Location: /stok-takip/public/recipes');
            exit;
        }

        $error = null;
        $formData = [
            'code'               => $recipe['code'],
            'name'               => $recipe['name'],
            'output_material_id' => $recipe['output_material_id'] ?? '',
            'base_quantity'      => $recipe['base_quantity'],
            'description'        => $recipe['description'] ?? '',
            'is_active'          => $recipe['is_active'],
        ];

        $currentItems = $this->recipeModel->getItems($id);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = [
                'code'               => trim($_POST['code'] ?? ''),
                'name'               => trim($_POST['name'] ?? ''),
                'output_material_id' => trim($_POST['output_material_id'] ?? ''),
                'base_quantity'      => trim($_POST['base_quantity'] ?? '1.000'),
                'description'        => trim($_POST['description'] ?? ''),
                'is_active'          => isset($_POST['is_active']) ? 1 : 0,
            ];

            // Parse dynamic items
            $rawItems = $_POST['items'] ?? [];
            $itemsToSave = [];

            if (is_array($rawItems)) {
                foreach ($rawItems as $raw) {
                    $matId = (int)($raw['material_id'] ?? 0);
                    $qty = (float)($raw['quantity'] ?? 0);
                    $scrap = (float)($raw['scrap_rate_pct'] ?? 0);
                    $isCrit = isset($raw['is_critical']) ? 1 : 0;

                    if ($matId > 0 && $qty > 0) {
                        $itemsToSave[] = [
                            'material_id'    => $matId,
                            'quantity'       => $qty,
                            'scrap_rate_pct' => $scrap,
                            'is_critical'    => $isCrit,
                        ];
                    }
                }
            }

            try {
                if (empty($itemsToSave)) {
                    throw new InvalidArgumentException('Reçeteye en az 1 hammadde/malzeme kalemi eklenmelidir.');
                }

                $this->recipeModel->update($id, $formData, $itemsToSave);

                $_SESSION['flash_success'] = 'Üretim reçetesi (' . htmlspecialchars($formData['name']) . ') başarıyla güncellendi.';
                header('Location: /stok-takip/public/recipes');
                exit;
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
                $currentItems = $itemsToSave;
            } catch (Throwable $e) {
                $error = 'Reçete güncellenirken beklenmeyen bir hata oluştu: ' . $e->getMessage();
                $currentItems = $itemsToSave;
            }
        }

        $availableMaterials = $this->recipeModel->getAvailableMaterials();

        require __DIR__ . '/../../views/recipes/edit.php';
    }

    public function toggle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /stok-takip/public/recipes');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        try {
            $this->recipeModel->toggleActive($id);
            $_SESSION['flash_success'] = 'Reçete durumu başarıyla güncellendi.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Durum güncellenemedi: ' . $e->getMessage();
        }

        header('Location: /stok-takip/public/recipes');
        exit;
    }

    public function delete(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /stok-takip/public/recipes');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        try {
            $this->recipeModel->delete($id);
            $_SESSION['flash_success'] = 'Reçete ve tüm alt kalemleri başarıyla silindi.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Reçete silinirken hata oluştu: ' . $e->getMessage();
        }

        header('Location: /stok-takip/public/recipes');
        exit;
    }
}
