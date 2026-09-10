<?php

require_once __DIR__ . '/../Models/Material.php';

class MaterialController
{
    private PDO $pdo;
    private Material $material;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->material = new Material($pdo);
    }

    public function index(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id > 0) {
            $this->show();
            return;
        }

        $materials = $this->material->getAllActive();

        require __DIR__ . '/../../views/materials/index.php';
    }

    public function show(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $material = $this->material->getMaterialDetail($id);

        if ($material === null) {
            $_SESSION['error'] = 'Malzeme bulunamadı.';
            if (!headers_sent()) {
                header('Location: /stok-takip/public/materials');
                exit;
            }
            return;
        }

        $locationBalances = $this->material->getLocationBalances($id);
        $recentMovements = $this->material->getRecentMovements($id, 30);
        $pageTitle = 'Malzeme Detayı: ' . $material['name'];

        require __DIR__ . '/../../views/materials/show.php';
    }

    /**
     * Stock status classification helper
     *
     * 1. STOK YOK (⚫): stock <= 0
     * 2. KRİTİK (🔴): 0 < stock < min_stock
     * 3. DÜŞÜK (🟡): min_stock <= stock <= min_stock * 1.5
     * 4. YETERLİ (🟢): stock > min_stock * 1.5 (or stock > 0 if min_stock == 0)
     */
    public static function classifyStockStatus(float $stock, float $minStock): array
    {
        if ($stock <= 0) {
            return [
                'key'          => 'out_of_stock',
                'label'        => 'STOK YOK',
                'icon'         => '⚫',
                'bg'           => '#f8fafc',
                'color'        => '#334155',
                'dot'          => '#64748b',
                'border'       => '#cbd5e1',
                'row_bg'       => '#ffffff',
                'order'        => 4
            ];
        }

        if ($minStock > 0 && $stock < $minStock) {
            return [
                'key'          => 'critical',
                'label'        => 'KRİTİK',
                'icon'         => '🔴',
                'bg'           => '#fef2f2',
                'color'        => '#991b1b',
                'dot'          => '#ef4444',
                'border'       => '#fecaca',
                'row_bg'       => '#fffdfd',
                'order'        => 1
            ];
        }

        if ($minStock > 0 && $stock <= ($minStock * 1.5)) {
            return [
                'key'          => 'low',
                'label'        => 'DÜŞÜK',
                'icon'         => '🟡',
                'bg'           => '#fffbeb',
                'color'        => '#92400e',
                'dot'          => '#f59e0b',
                'border'       => '#fde68a',
                'row_bg'       => '#fffefb',
                'order'        => 2
            ];
        }

        return [
            'key'          => 'sufficient',
            'label'        => 'YETERLİ',
            'icon'         => '🟢',
            'bg'           => '#f0fdf4',
            'color'        => '#166534',
            'dot'          => '#22c55e',
            'border'       => '#bbf7d0',
            'row_bg'       => '#ffffff',
            'order'        => 3
        ];
    }

    /**
     * Real-time live stock polling endpoint (GET /api/materials/live-stock)
     */
    public function liveStock(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        // 1. Fetch all materials with aggregated real-time stock
        $rawMaterials = $this->material->getAllActive();

        $totalMaterials = count($rawMaterials);
        $sufficientCount = 0;
        $lowCount = 0;
        $criticalCount = 0;
        $outOfStockCount = 0;
        $materialsData = [];

        foreach ($rawMaterials as $m) {
            $stock = (float)($m['total_stock'] ?? 0);
            $minStock = (float)($m['min_stock'] ?? 0);
            $maxStock = !empty($m['max_stock']) ? (float)$m['max_stock'] : null;
            $unitSymbol = $m['symbol'] ?: ($m['unit'] ?: 'AD');

            $status = self::classifyStockStatus($stock, $minStock);

            if ($status['key'] === 'out_of_stock') {
                $outOfStockCount++;
            } elseif ($status['key'] === 'critical') {
                $criticalCount++;
            } elseif ($status['key'] === 'low') {
                $lowCount++;
            } else {
                $sufficientCount++;
            }

            // Format formatted stock
            $formattedStock = (floor($stock) == $stock)
                ? number_format($stock, 0, ',', '.')
                : rtrim(rtrim(number_format($stock, 2, ',', '.'), '0'), ',');

            $formattedMin = (floor($minStock) == $minStock)
                ? number_format($minStock, 0, ',', '.')
                : rtrim(rtrim(number_format($minStock, 2, ',', '.'), '0'), ',');

            $materialsData[] = [
                'id'              => (int)$m['id'],
                'code'            => $m['code'],
                'name'            => $m['name'],
                'category'        => $m['category'],
                'unit'            => $m['unit'],
                'symbol'          => $unitSymbol,
                'total_stock'     => $stock,
                'formatted_stock' => $formattedStock,
                'display_stock'   => $formattedStock . ' ' . $unitSymbol,
                'min_stock'       => $minStock,
                'formatted_min'   => $formattedMin,
                'max_stock'       => $maxStock,
                'status_key'      => $status['key'],
                'status_label'    => $status['label'],
                'status_icon'     => $status['icon'],
                'status_bg'       => $status['bg'],
                'status_color'    => $status['color'],
                'status_dot'      => $status['dot'],
                'status_border'   => $status['border'],
                'row_bg'          => $status['row_bg']
            ];
        }

        // 3. Active production heartbeat information
        $activeProduction = null;
        try {
            $stmtWo = $this->pdo->query("
                SELECT wo.id, wo.work_order_no, wo.planned_quantity, wo.produced_quantity, wo.status,
                       m.name AS product_name, m.code AS product_code,
                       s.is_active, s.last_status, s.last_error, s.interval_seconds
                FROM mes_work_orders wo
                JOIN materials m ON wo.product_material_id = m.id
                LEFT JOIN mes_simulations s ON s.work_order_id = wo.id
                WHERE s.is_active = 1 OR (s.last_status = 'FAILED' AND s.updated_at >= NOW() - INTERVAL 5 MINUTE)
                ORDER BY s.is_active DESC, s.updated_at DESC, wo.id DESC
                LIMIT 1
            ");
            $prodRow = $stmtWo ? $stmtWo->fetch(PDO::FETCH_ASSOC) : null;
            if ($prodRow) {
                $planned = (float)$prodRow['planned_quantity'];
                $produced = (float)$prodRow['produced_quantity'];
                $activeProduction = [
                    'id'                => (int)$prodRow['id'],
                    'work_order_no'     => $prodRow['work_order_no'],
                    'product_name'      => $prodRow['product_name'],
                    'product_code'      => $prodRow['product_code'],
                    'planned_quantity'  => $planned,
                    'produced_quantity' => $produced,
                    'remaining_quantity'=> max(0, $planned - $produced),
                    'progress_pct'      => $planned > 0 ? round(($produced / $planned) * 100, 1) : 0,
                    'is_active'         => (bool)($prodRow['is_active'] ?? false),
                    'interval_seconds'  => (int)($prodRow['interval_seconds'] ?? 5),
                    'status'            => $prodRow['status'],
                    'last_status'       => $prodRow['last_status'] ?? '',
                    'last_error'        => $prodRow['last_error'] ?? null
                ];
            }
        } catch (Throwable $e) {
            // Ignore
        }

        echo json_encode([
            'success'           => true,
            'timestamp'         => date('Y-m-d H:i:s'),
            'summary'           => [
                'total_materials'    => $totalMaterials,
                'sufficient_count'   => $sufficientCount,
                'low_count'          => $lowCount,
                'critical_count'     => $criticalCount,
                'out_of_stock_count' => $outOfStockCount
            ],
            'active_production' => $activeProduction,
            'materials'         => $materialsData
        ]);
        return;
    }

    public function create(): void
    {
        $material = null;
        $formData = $this->emptyFormData();
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = $this->readFormData();
            $error = $this->validateFormData($formData);

            if ($error === null) {
                try {
                    $this->material->create($this->databaseData($formData));
                    header('Location: /stok-takip/public/materials');
                    exit;
                } catch (PDOException $exception) {
                    $error = $this->databaseError($exception);
                }
            }
        }

        $options = $this->material->getFormOptions();
        $categories = $options['categories'];
        $units = $options['units'];
        $formTitle = 'Yeni Malzeme';
        $formAction = '/stok-takip/public/materials/create';

        require __DIR__ . '/../../views/materials/form.php';
    }

    public function edit(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $material = $this->material->findActiveById($id);

        if ($material === null) {
            http_response_code(404);
            echo '404 - Malzeme bulunamadı.';
            return;
        }

        $formData = $material;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = array_merge($material, $this->readFormData());
            $error = $this->validateFormData($formData);

            if ($error === null) {
                try {
                    $this->material->update($id, $this->databaseData($formData));
                    header('Location: /stok-takip/public/materials');
                    exit;
                } catch (PDOException $exception) {
                    $error = $this->databaseError($exception);
                }
            }
        }

        $options = $this->material->getFormOptions();
        $categories = $options['categories'];
        $units = $options['units'];
        $formTitle = 'Malzeme Düzenle';
        $formAction = '/stok-takip/public/materials/edit?id=' . $id;

        require __DIR__ . '/../../views/materials/form.php';
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
            $this->material->deactivate($id);
        }

        header('Location: /stok-takip/public/materials');
        exit;
    }

    private function emptyFormData(): array
    {
        return [
            'code' => '',
            'name' => '',
            'category_id' => '',
            'unit_id' => '',
            'min_stock' => '0',
            'max_stock' => '',
            'description' => '',
        ];
    }

    private function readFormData(): array
    {
        return [
            'code' => trim((string) ($_POST['code'] ?? '')),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'category_id' => trim((string) ($_POST['category_id'] ?? '')),
            'unit_id' => trim((string) ($_POST['unit_id'] ?? '')),
            'min_stock' => trim((string) ($_POST['min_stock'] ?? '')),
            'max_stock' => trim((string) ($_POST['max_stock'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
        ];
    }

    private function validateFormData(array $data): ?string
    {
        if ($data['code'] === '' || $data['name'] === '') {
            return 'Malzeme kodu ve malzeme adı zorunludur.';
        }

        if (!ctype_digit($data['category_id']) || (int) $data['category_id'] < 1) {
            return 'Geçerli bir kategori seçin.';
        }

        if (!ctype_digit($data['unit_id']) || (int) $data['unit_id'] < 1) {
            return 'Geçerli birim seçin.';
        }

        if (!is_numeric($data['min_stock']) || (float) $data['min_stock'] < 0) {
            return 'Minimum stok sıfır veya daha büyük bir sayı olmalıdır.';
        }

        if ($data['max_stock'] !== '' && (!is_numeric($data['max_stock']) || (float) $data['max_stock'] < 0)) {
            return 'Maksimum stok boş veya sıfırdan büyük bir sayı olmalıdır.';
        }

        if ($data['max_stock'] !== '' && (float) $data['max_stock'] < (float) $data['min_stock']) {
            return 'Maksimum stok, minimum stoktan küçük olamaz.';
        }

        return null;
    }

    private function databaseData(array $data): array
    {
        return [
            'code' => $data['code'],
            'name' => $data['name'],
            'category_id' => (int) $data['category_id'],
            'unit_id' => (int) $data['unit_id'],
            'min_stock' => (float) $data['min_stock'],
            'max_stock' => $data['max_stock'] === '' ? null : (float) $data['max_stock'],
            'description' => $data['description'] === '' ? null : $data['description'],
        ];
    }

    private function databaseError(PDOException $exception): string
    {
        if ($exception->getCode() === '23000') {
            return 'Bu malzeme kodu zaten kullanılıyor veya seçilen ilişki geçersiz.';
        }

        return 'Malzeme kaydedilirken bir hata oluştu.';
    }
}