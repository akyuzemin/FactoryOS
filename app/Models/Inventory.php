<?php

class Inventory
{
    private PDO $pdo;

    private const STATUSES = [
        'IN_STOCK',
        'ASSIGNED',
        'IN_USE',
        'IN_REPAIR',
        'LOST',
        'RETIRED',
        'DISPOSED',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function statuses(): array
    {
        return self::STATUSES;
    }

    public function generateAssetCode(): string
    {
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $suffix = '';
        for ($index = 0; $index < 6; $index++) {
            $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return 'ENV-' . date('Y') . '-' . $suffix;
    }

    public function getSuggestions(string $field, string $query): array
    {
        $fieldMap = [
            'asset_name' => 'asset_name',
            'manufacturer' => 'manufacturer',
            'model_no' => 'model_no',
        ];

        if (!isset($fieldMap[$field])) {
            return [];
        }

        $column = $fieldMap[$field];
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT {$column} AS suggestion
             FROM inventory_assets
             WHERE is_active = 1
               AND {$column} IS NOT NULL
               AND TRIM({$column}) <> ''
               AND {$column} LIKE :query
             ORDER BY {$column} ASC
             LIMIT 8"
        );
        $stmt->execute(['query' => '%' . $query . '%']);

        return array_values(array_filter(
            array_map(static fn (array $row): string => trim((string) $row['suggestion']), $stmt->fetchAll(PDO::FETCH_ASSOC)),
            static fn (string $suggestion): bool => $suggestion !== ''
        ));
    }

    public function getFilterOptions(): array
    {
        $categories = $this->pdo->query(
            'SELECT id, code, name FROM inventory_categories WHERE is_active = 1 ORDER BY name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $warehouses = $this->pdo->query(
            'SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $locations = $this->pdo->query(
            'SELECT l.id, l.warehouse_id, l.code, l.name, w.name AS warehouse_name
             FROM locations l
             INNER JOIN warehouses w ON w.id = l.warehouse_id
             WHERE l.is_active = 1 AND w.is_active = 1
             ORDER BY w.name ASC, l.name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $users = $this->pdo->query(
            'SELECT id, username, first_name, last_name
             FROM users
             WHERE is_active = 1
             ORDER BY first_name ASC, last_name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $lines = $this->pdo->query(
            'SELECT id, code, name FROM production_lines WHERE is_active = 1 ORDER BY name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $maintenanceAssets = $this->pdo->prepare(
            'SELECT id, asset_code, asset_name
             FROM maintenance_assets
             WHERE status <> :status
             ORDER BY asset_name ASC'
        );
        $maintenanceAssets->execute(['status' => 'DECOMMISSIONED']);

        $suppliers = $this->pdo->query(
            'SELECT id, code, name FROM suppliers WHERE is_active = 1 ORDER BY name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        return [
            'categories' => $categories,
            'warehouses' => $warehouses,
            'locations' => $locations,
            'users' => $users,
            'production_lines' => $lines,
            'maintenance_assets' => $maintenanceAssets->fetchAll(PDO::FETCH_ASSOC),
            'suppliers' => $suppliers,
            'statuses' => self::STATUSES,
        ];
    }

    public function getKpis(): array
    {
        $stmt = $this->pdo->query(
            'SELECT status, COUNT(*) AS total
             FROM inventory_assets
             WHERE is_active = 1
             GROUP BY status'
        );

        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (array_key_exists($row['status'], $counts)) {
                $counts[$row['status']] = (int) $row['total'];
            }
        }

        return [
            'total' => array_sum($counts),
            'in_use' => $counts['ASSIGNED'] + $counts['IN_USE'],
            'in_stock' => $counts['IN_STOCK'],
            'in_repair' => $counts['IN_REPAIR'],
            'out_of_use' => $counts['LOST'] + $counts['RETIRED'] + $counts['DISPOSED'],
        ];
    }

    public function getAssets(array $filters): array
    {
        $conditions = ['a.is_active = 1'];
        $params = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(a.asset_code LIKE :search
                OR a.asset_name LIKE :search
                OR a.serial_no LIKE :search
                OR a.manufacturer LIKE :search
                OR a.model_no LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['category_id'])) {
            $conditions[] = 'a.inventory_category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['status'])) {
            $conditions[] = 'a.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['warehouse_id'])) {
            $conditions[] = 'a.warehouse_id = :warehouse_id';
            $params['warehouse_id'] = (int) $filters['warehouse_id'];
        }
        if (!empty($filters['responsible_user_id'])) {
            $conditions[] = 'a.responsible_user_id = :responsible_user_id';
            $params['responsible_user_id'] = (int) $filters['responsible_user_id'];
        }

        $sql = 'SELECT
                    a.*,
                    c.name AS category_name,
                    w.name AS warehouse_name,
                    l.name AS location_name,
                    s.name AS supplier_name,
                    pl.name AS production_line_name,
                    CONCAT(u.first_name, " ", u.last_name) AS responsible_name,
                    ma.asset_code AS maintenance_asset_code
                FROM inventory_assets a
                INNER JOIN inventory_categories c ON c.id = a.inventory_category_id
                LEFT JOIN suppliers s ON s.id = a.supplier_id
                LEFT JOIN warehouses w ON w.id = a.warehouse_id
                LEFT JOIN locations l ON l.id = a.location_id
                LEFT JOIN production_lines pl ON pl.id = a.production_line_id
                LEFT JOIN users u ON u.id = a.responsible_user_id
                LEFT JOIN maintenance_assets ma ON ma.id = a.maintenance_asset_id
                WHERE ' . implode(' AND ', $conditions) . '
                ORDER BY a.asset_name ASC, a.asset_code ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                a.*,
                c.name AS category_name,
                c.code AS category_code,
                s.name AS supplier_name,
                w.name AS warehouse_name,
                l.name AS location_name,
                pl.name AS production_line_name,
                CONCAT(u.first_name, " ", u.last_name) AS responsible_name,
                ma.asset_code AS maintenance_asset_code,
                ma.asset_name AS maintenance_asset_name
             FROM inventory_assets a
             INNER JOIN inventory_categories c ON c.id = a.inventory_category_id
             LEFT JOIN suppliers s ON s.id = a.supplier_id
             LEFT JOIN warehouses w ON w.id = a.warehouse_id
             LEFT JOIN locations l ON l.id = a.location_id
             LEFT JOIN production_lines pl ON pl.id = a.production_line_id
             LEFT JOIN users u ON u.id = a.responsible_user_id
             LEFT JOIN maintenance_assets ma ON ma.id = a.maintenance_asset_id
             WHERE a.id = :id AND a.is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        $asset = $stmt->fetch(PDO::FETCH_ASSOC);
        return $asset ?: null;
    }

    public function getMovements(int $assetId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                m.*,
                CONCAT(p.first_name, " ", p.last_name) AS performed_by_name,
                CONCAT(fu.first_name, " ", fu.last_name) AS from_user_name,
                CONCAT(tu.first_name, " ", tu.last_name) AS to_user_name,
                fw.name AS from_warehouse_name,
                tw.name AS to_warehouse_name,
                fl.name AS from_location_name,
                tl.name AS to_location_name
             FROM inventory_asset_movements m
             LEFT JOIN users p ON p.id = m.performed_by_user_id
             LEFT JOIN users fu ON fu.id = m.from_user_id
             LEFT JOIN users tu ON tu.id = m.to_user_id
             LEFT JOIN warehouses fw ON fw.id = m.from_warehouse_id
             LEFT JOIN warehouses tw ON tw.id = m.to_warehouse_id
             LEFT JOIN locations fl ON fl.id = m.from_location_id
             LEFT JOIN locations tl ON tl.id = m.to_location_id
             WHERE m.inventory_asset_id = :asset_id
             ORDER BY m.movement_date DESC, m.id DESC'
        );
        $stmt->execute(['asset_id' => $assetId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function validateReferences(array $data): ?string
    {
        if (!$this->activeRecordExists('inventory_categories', (int) $data['inventory_category_id'])) {
            return 'Seçilen envanter kategorisi geçersiz.';
        }
        if (!empty($data['supplier_id']) && !$this->activeRecordExists('suppliers', (int) $data['supplier_id'])) {
            return 'Seçilen tedarikçi geçersiz.';
        }
        if (!empty($data['warehouse_id']) && !$this->activeRecordExists('warehouses', (int) $data['warehouse_id'])) {
            return 'Seçilen depo geçersiz.';
        }
        if (!empty($data['location_id']) && !$this->activeRecordExists('locations', (int) $data['location_id'])) {
            return 'Seçilen lokasyon geçersiz.';
        }
        if (!empty($data['warehouse_id']) && !empty($data['location_id']) && !$this->locationBelongsToWarehouse((int) $data['location_id'], (int) $data['warehouse_id'])) {
            return 'Seçilen lokasyon, seçilen depoya bağlı değil.';
        }
        if (!empty($data['production_line_id']) && !$this->activeRecordExists('production_lines', (int) $data['production_line_id'])) {
            return 'Seçilen üretim hattı geçersiz.';
        }
        if (!empty($data['responsible_user_id']) && !$this->activeRecordExists('users', (int) $data['responsible_user_id'])) {
            return 'Seçilen sorumlu kullanıcı geçersiz.';
        }
        if (!empty($data['maintenance_asset_id']) && !$this->activeRecordExists('maintenance_assets', (int) $data['maintenance_asset_id'])) {
            return 'Seçilen bakım varlığı geçersiz.';
        }

        return null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO inventory_assets (
                asset_code, asset_name, inventory_category_id, serial_no, manufacturer, model_no,
                supplier_id, purchase_date, purchase_cost, currency, invoice_no,
                warranty_start_date, warranty_end_date, status, warehouse_id, location_id,
                production_line_id, responsible_user_id, maintenance_asset_id, description,
                is_active, created_by, updated_by
             ) VALUES (
                :asset_code, :asset_name, :inventory_category_id, :serial_no, :manufacturer, :model_no,
                :supplier_id, :purchase_date, :purchase_cost, :currency, :invoice_no,
                :warranty_start_date, :warranty_end_date, :status, :warehouse_id, :location_id,
                :production_line_id, :responsible_user_id, :maintenance_asset_id, :description,
                1, :created_by, :updated_by
             )'
        );

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $data['asset_code'] = $this->generateAssetCode();
            try {
                $stmt->execute($data);
                return (int) $this->pdo->lastInsertId();
            } catch (PDOException $exception) {
                if (($exception->errorInfo[1] ?? null) !== 1062 || $attempt === 4) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Varlık kodu oluşturulamadı.');
    }

    public function update(int $id, array $data): void
    {
        $data['id'] = $id;
        $stmt = $this->pdo->prepare(
            'UPDATE inventory_assets SET
                asset_code = :asset_code,
                asset_name = :asset_name,
                inventory_category_id = :inventory_category_id,
                serial_no = :serial_no,
                manufacturer = :manufacturer,
                model_no = :model_no,
                supplier_id = :supplier_id,
                purchase_date = :purchase_date,
                purchase_cost = :purchase_cost,
                currency = :currency,
                invoice_no = :invoice_no,
                warranty_start_date = :warranty_start_date,
                warranty_end_date = :warranty_end_date,
                status = :status,
                warehouse_id = :warehouse_id,
                location_id = :location_id,
                production_line_id = :production_line_id,
                responsible_user_id = :responsible_user_id,
                maintenance_asset_id = :maintenance_asset_id,
                description = :description,
                updated_by = :updated_by
             WHERE id = :id AND is_active = 1'
        );
        $stmt->execute($data);
    }

    private function activeRecordExists(string $table, int $id): bool
    {
        $allowedTables = [
            'inventory_categories',
            'suppliers',
            'warehouses',
            'locations',
            'production_lines',
            'users',
            'maintenance_assets',
        ];
        if (!in_array($table, $allowedTables, true) || $id <= 0) {
            return false;
        }

        $condition = $table === 'maintenance_assets' ? 'status <> :inactive' : 'is_active = 1';
        $sql = "SELECT 1 FROM {$table} WHERE id = :id AND {$condition} LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $params = ['id' => $id];
        if ($table === 'maintenance_assets') {
            $params['inactive'] = 'DECOMMISSIONED';
        }
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    private function locationBelongsToWarehouse(int $locationId, int $warehouseId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM locations WHERE id = :location_id AND warehouse_id = :warehouse_id AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([
            'location_id' => $locationId,
            'warehouse_id' => $warehouseId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function getActiveUsersForAssignment(): array
    {
        $sql = 'SELECT 
                    u.id AS user_id,
                    u.username,
                    COALESCE(NULLIF(TRIM(CONCAT(e.first_name, " ", e.last_name)), ""), NULLIF(TRIM(CONCAT(u.first_name, " ", u.last_name)), ""), u.username) AS full_name,
                    e.registration_no,
                    d.name AS department_name,
                    p.title AS position_name
                FROM users u
                LEFT JOIN employees e ON e.user_id = u.id
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN positions p ON p.id = e.position_id
                WHERE u.is_active = 1
                  AND (e.status IS NULL OR e.status <> "TERMINATED")
                ORDER BY full_name ASC';

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assignAsset(int $assetId, int $toUserId, int $performedByUserId, array $meta = []): bool
    {
        if ($assetId <= 0 || $toUserId <= 0) {
            throw new InvalidArgumentException('Geçersiz varlık veya kullanıcı seçimi.');
        }

        $this->pdo->beginTransaction();
        try {
            // 1. Varlık kaydını kilitle ve doğrula
            $stmt = $this->pdo->prepare('SELECT * FROM inventory_assets WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $assetId]);
            $asset = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$asset) {
                throw new RuntimeException('Varlık bulunamadı.');
            }

            if ((int) $asset['is_active'] !== 1) {
                throw new RuntimeException('Pasif durumdaki varlık tahsis edilemez.');
            }

            if (!empty($asset['responsible_user_id'])) {
                throw new RuntimeException('Bu varlık şu anda başka bir kullanıcıya zimmetli.');
            }

            if ($asset['status'] !== 'IN_STOCK') {
                throw new RuntimeException('Bu varlık tahsis edilebilir durumda değil.');
            }

            if (in_array($asset['status'], ['RETIRED', 'DISPOSED', 'LOST'], true)) {
                throw new RuntimeException('Emekli, zayi veya kayıp durumdaki varlıklar tahsis edilemez.');
            }

            // 2. Hedef kullanıcı ve çalışan durumunu kontrol et
            $stmtUser = $this->pdo->prepare('
                SELECT u.id, u.is_active, e.status AS employee_status
                FROM users u
                LEFT JOIN employees e ON e.user_id = u.id
                WHERE u.id = :id
                LIMIT 1
            ');
            $stmtUser->execute(['id' => $toUserId]);
            $targetUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if (!$targetUser) {
                throw new RuntimeException('Hedef kullanıcı bulunamadı.');
            }

            if ((int) $targetUser['is_active'] !== 1) {
                throw new RuntimeException('Seçilen çalışan aktif değil.');
            }

            if (!empty($targetUser['employee_status']) && $targetUser['employee_status'] === 'TERMINATED') {
                throw new RuntimeException('İşten ayrılmış çalışana varlık tahsis edilemez.');
            }

            if ((int) ($asset['responsible_user_id'] ?? 0) === $toUserId) {
                throw new RuntimeException('Bu varlık zaten seçilen kullanıcıya zimmetli.');
            }

            // 3. inventory_assets güncellemesi
            $updateStmt = $this->pdo->prepare('
                UPDATE inventory_assets
                SET responsible_user_id = :to_user_id,
                    status = :status,
                    updated_by = :updated_by
                WHERE id = :id
            ');
            $updateStmt->execute([
                'to_user_id' => $toUserId,
                'status'     => 'ASSIGNED',
                'updated_by' => $performedByUserId > 0 ? $performedByUserId : null,
                'id'         => $assetId,
            ]);

            // 4. inventory_asset_movements kaydı
            $movementDate = !empty($meta['movement_date']) && preg_match('/^\d{4}-\d{2}-\d{2}/', $meta['movement_date'])
                ? (strlen($meta['movement_date']) === 10 ? $meta['movement_date'] . ' ' . date('H:i:s') : $meta['movement_date'])
                : date('Y-m-d H:i:s');

            $movementStmt = $this->pdo->prepare('
                INSERT INTO inventory_asset_movements (
                    inventory_asset_id,
                    movement_type,
                    from_user_id,
                    to_user_id,
                    from_warehouse_id,
                    to_warehouse_id,
                    from_location_id,
                    to_location_id,
                    from_status,
                    to_status,
                    performed_by_user_id,
                    movement_date,
                    reference_no,
                    reason,
                    notes,
                    created_at
                ) VALUES (
                    :inventory_asset_id,
                    "ASSIGN",
                    NULL,
                    :to_user_id,
                    :from_warehouse_id,
                    :to_warehouse_id,
                    :from_location_id,
                    :to_location_id,
                    :from_status,
                    "ASSIGNED",
                    :performed_by_user_id,
                    :movement_date,
                    :reference_no,
                    :reason,
                    :notes,
                    NOW()
                )
            ');

            $movementStmt->execute([
                'inventory_asset_id'   => $assetId,
                'to_user_id'           => $toUserId,
                'from_warehouse_id'    => $asset['warehouse_id'] ?? null,
                'to_warehouse_id'      => $asset['warehouse_id'] ?? null,
                'from_location_id'     => $asset['location_id'] ?? null,
                'to_location_id'       => $asset['location_id'] ?? null,
                'from_status'          => $asset['status'],
                'performed_by_user_id' => $performedByUserId > 0 ? $performedByUserId : null,
                'movement_date'        => $movementDate,
                'reference_no'         => !empty($meta['reference_no']) ? trim((string) $meta['reference_no']) : null,
                'reason'               => !empty($meta['reason']) ? trim((string) $meta['reason']) : null,
                'notes'                => !empty($meta['notes']) ? trim((string) $meta['notes']) : null,
            ]);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function transferAsset(int $assetId, int $toUserId, int $performedByUserId, array $meta = []): bool
    {
        if ($assetId <= 0 || $toUserId <= 0) {
            throw new InvalidArgumentException('Geçersiz varlık veya kullanıcı seçimi.');
        }

        $this->pdo->beginTransaction();
        try {
            // 1. Varlık satırını kilitle ve doğrula
            $stmt = $this->pdo->prepare('SELECT * FROM inventory_assets WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $assetId]);
            $asset = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$asset) {
                throw new RuntimeException('Varlık bulunamadı.');
            }

            if ((int) $asset['is_active'] !== 1) {
                throw new RuntimeException('Pasif durumdaki varlık devredilemez.');
            }

            if (empty($asset['responsible_user_id'])) {
                throw new RuntimeException('Bu varlığın atanmış bir sorumlusu bulunmuyor. Önce tahsis yapılmalıdır.');
            }

            if ($asset['status'] !== 'ASSIGNED') {
                throw new RuntimeException('Yalnızca tahsisli (ASSIGNED) durumdaki varlıklar devredilebilir.');
            }

            if ((int) $asset['responsible_user_id'] === $toUserId) {
                throw new RuntimeException('Varlık zaten seçilen kullanıcıya zimmetlidir. Farklı bir kullanıcı seçiniz.');
            }

            // 2. Hedef kullanıcı ve çalışan durumunu kontrol et
            $stmtUser = $this->pdo->prepare('
                SELECT u.id, u.is_active, e.status AS employee_status
                FROM users u
                LEFT JOIN employees e ON e.user_id = u.id
                WHERE u.id = :id
                LIMIT 1
            ');
            $stmtUser->execute(['id' => $toUserId]);
            $targetUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if (!$targetUser) {
                throw new RuntimeException('Hedef kullanıcı bulunamadı.');
            }

            if ((int) $targetUser['is_active'] !== 1) {
                throw new RuntimeException('Seçilen çalışan aktif değil.');
            }

            if (!empty($targetUser['employee_status']) && $targetUser['employee_status'] === 'TERMINATED') {
                throw new RuntimeException('İşten ayrılmış çalışana varlık devredilemez.');
            }

            $fromUserId = (int) $asset['responsible_user_id'];

            // 3. inventory_assets güncellemesi (Sorumlu değişir, status ASSIGNED kalır)
            $updateStmt = $this->pdo->prepare('
                UPDATE inventory_assets
                SET responsible_user_id = :to_user_id,
                    status = "ASSIGNED",
                    updated_by = :updated_by
                WHERE id = :id
            ');
            $updateStmt->execute([
                'to_user_id' => $toUserId,
                'updated_by' => $performedByUserId > 0 ? $performedByUserId : null,
                'id'         => $assetId,
            ]);

            // 4. inventory_asset_movements kaydı (TRANSFER)
            $movementDate = !empty($meta['movement_date']) && preg_match('/^\d{4}-\d{2}-\d{2}/', $meta['movement_date'])
                ? (strlen($meta['movement_date']) === 10 ? $meta['movement_date'] . ' ' . date('H:i:s') : $meta['movement_date'])
                : date('Y-m-d H:i:s');

            $movementStmt = $this->pdo->prepare('
                INSERT INTO inventory_asset_movements (
                    inventory_asset_id,
                    movement_type,
                    from_user_id,
                    to_user_id,
                    from_warehouse_id,
                    to_warehouse_id,
                    from_location_id,
                    to_location_id,
                    from_status,
                    to_status,
                    performed_by_user_id,
                    movement_date,
                    reference_no,
                    reason,
                    notes,
                    created_at
                ) VALUES (
                    :inventory_asset_id,
                    "TRANSFER",
                    :from_user_id,
                    :to_user_id,
                    :from_warehouse_id,
                    :to_warehouse_id,
                    :from_location_id,
                    :to_location_id,
                    "ASSIGNED",
                    "ASSIGNED",
                    :performed_by_user_id,
                    :movement_date,
                    :reference_no,
                    :reason,
                    :notes,
                    NOW()
                )
            ');

            $movementStmt->execute([
                'inventory_asset_id'   => $assetId,
                'from_user_id'         => $fromUserId,
                'to_user_id'           => $toUserId,
                'from_warehouse_id'    => $asset['warehouse_id'] ?? null,
                'to_warehouse_id'      => $asset['warehouse_id'] ?? null,
                'from_location_id'     => $asset['location_id'] ?? null,
                'to_location_id'       => $asset['location_id'] ?? null,
                'performed_by_user_id' => $performedByUserId > 0 ? $performedByUserId : null,
                'movement_date'        => $movementDate,
                'reference_no'         => !empty($meta['reference_no']) ? trim((string) $meta['reference_no']) : null,
                'reason'               => !empty($meta['reason']) ? trim((string) $meta['reason']) : null,
                'notes'                => !empty($meta['notes']) ? trim((string) $meta['notes']) : null,
            ]);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function returnAsset(int $assetId, int $performedByUserId, int $warehouseId, int $locationId, array $meta = []): bool
    {
        if ($assetId <= 0 || $warehouseId <= 0 || $locationId <= 0 || $performedByUserId <= 0) {
            throw new InvalidArgumentException('Geçersiz varlık, depo, lokasyon veya işlem kullanıcısı.');
        }

        $this->pdo->beginTransaction();
        try {
            // 1. Varlık satırını kilitle ve doğrula
            $stmt = $this->pdo->prepare('SELECT * FROM inventory_assets WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $assetId]);
            $asset = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$asset) {
                throw new RuntimeException('Varlık bulunamadı.');
            }

            if ((int) $asset['is_active'] !== 1) {
                throw new RuntimeException('Pasif durumdaki varlık iade alınamaz.');
            }

            if (empty($asset['responsible_user_id'])) {
                throw new RuntimeException('Bu varlığın atanmış bir sorumlusu bulunmuyor.');
            }

            if ($asset['status'] !== 'ASSIGNED') {
                throw new RuntimeException('Yalnızca tahsisli (ASSIGNED) durumdaki varlıklar iade alınabilir.');
            }

            // 2. Depo ve lokasyon varlığını ve sahipliğini doğrula
            $stmtWarehouse = $this->pdo->prepare('SELECT 1 FROM warehouses WHERE id = :id AND is_active = 1 LIMIT 1');
            $stmtWarehouse->execute(['id' => $warehouseId]);
            if (!$stmtWarehouse->fetchColumn()) {
                throw new RuntimeException('Seçilen depo bulunamadı veya pasif.');
            }

            $stmtLocation = $this->pdo->prepare('
                SELECT 1 
                FROM locations 
                WHERE id = :location_id 
                  AND warehouse_id = :warehouse_id 
                  AND is_active = 1 
                LIMIT 1
            ');
            $stmtLocation->execute([
                'location_id'  => $locationId,
                'warehouse_id' => $warehouseId,
            ]);
            if (!$stmtLocation->fetchColumn()) {
                throw new RuntimeException('Seçilen lokasyon geçersiz veya seçilen depoya bağlı değil.');
            }

            $fromUserId = (int) $asset['responsible_user_id'];
            $fromWarehouseId = $asset['warehouse_id'] ?? null;
            $fromLocationId = $asset['location_id'] ?? null;

            // 3. inventory_assets tablosunu güncelle
            $updateStmt = $this->pdo->prepare('
                UPDATE inventory_assets
                SET responsible_user_id = NULL,
                    status = "IN_STOCK",
                    warehouse_id = :warehouse_id,
                    location_id = :location_id,
                    updated_by = :updated_by
                WHERE id = :id
            ');
            $updateStmt->execute([
                'warehouse_id' => $warehouseId,
                'location_id'  => $locationId,
                'updated_by'   => $performedByUserId > 0 ? $performedByUserId : null,
                'id'           => $assetId,
            ]);

            // 4. inventory_asset_movements kaydı (RETURN)
            $movementDate = !empty($meta['movement_date']) && preg_match('/^\d{4}-\d{2}-\d{2}/', $meta['movement_date'])
                ? (strlen($meta['movement_date']) === 10 ? $meta['movement_date'] . ' ' . date('H:i:s') : $meta['movement_date'])
                : date('Y-m-d H:i:s');

            $movementStmt = $this->pdo->prepare('
                INSERT INTO inventory_asset_movements (
                    inventory_asset_id,
                    movement_type,
                    from_user_id,
                    to_user_id,
                    from_warehouse_id,
                    to_warehouse_id,
                    from_location_id,
                    to_location_id,
                    from_status,
                    to_status,
                    performed_by_user_id,
                    movement_date,
                    reference_no,
                    reason,
                    notes,
                    created_at
                ) VALUES (
                    :inventory_asset_id,
                    "RETURN",
                    :from_user_id,
                    NULL,
                    :from_warehouse_id,
                    :to_warehouse_id,
                    :from_location_id,
                    :to_location_id,
                    "ASSIGNED",
                    "IN_STOCK",
                    :performed_by_user_id,
                    :movement_date,
                    :reference_no,
                    :reason,
                    :notes,
                    NOW()
                )
            ');

            $movementStmt->execute([
                'inventory_asset_id'   => $assetId,
                'from_user_id'         => $fromUserId,
                'from_warehouse_id'    => $fromWarehouseId,
                'to_warehouse_id'      => $warehouseId,
                'from_location_id'     => $fromLocationId,
                'to_location_id'       => $locationId,
                'performed_by_user_id' => $performedByUserId > 0 ? $performedByUserId : null,
                'movement_date'        => $movementDate,
                'reference_no'         => !empty($meta['reference_no']) ? trim((string) $meta['reference_no']) : null,
                'reason'               => !empty($meta['reason']) ? trim((string) $meta['reason']) : null,
                'notes'                => !empty($meta['notes']) ? trim((string) $meta['notes']) : null,
            ]);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
