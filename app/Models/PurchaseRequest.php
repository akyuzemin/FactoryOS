<?php
declare(strict_types=1);

class PurchaseRequest
{
    private PDO $pdo;

    public const PRIORITIES = [
        'LOW',
        'MEDIUM',
        'HIGH',
        'URGENT',
    ];

    public const STATUSES = [
        'DRAFT',
        'SUBMITTED',
        'APPROVED',
        'REJECTED',
        'ORDERED',
        'CANCELLED',
    ];

    public const CURRENCIES = [
        'TL',
        'USD',
        'EUR',
    ];

    public const STATUS_TRANSITIONS = [
        'DRAFT'     => ['SUBMITTED', 'CANCELLED'],
        'SUBMITTED' => ['APPROVED', 'REJECTED', 'CANCELLED'],
        'APPROVED'  => ['ORDERED'],
        'REJECTED'  => [],
        'ORDERED'   => [],
        'CANCELLED' => [],
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function priorities(): array
    {
        return self::PRIORITIES;
    }

    public static function statuses(): array
    {
        return self::STATUSES;
    }

    public static function currencies(): array
    {
        return self::CURRENCIES;
    }

    /**
     * İzin verilen durum geçişlerini döner.
     */
    public static function getAllowedTransitions(?string $currentStatus = null): array
    {
        if ($currentStatus === null) {
            return self::STATUS_TRANSITIONS;
        }

        return self::STATUS_TRANSITIONS[$currentStatus] ?? [];
    }

    /**
     * Belirtilen durum geçişine izin verilip verilmediğini kontrol eder.
     */
    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        $allowed = self::STATUS_TRANSITIONS[$fromStatus] ?? [];
        return in_array($toStatus, $allowed, true);
    }

    /**
     * Güvenli ve sıralı Satın Alma Talep Numarası üretir: PR-YYYY-XXXXXX
     * Örnek: PR-2026-000001
     */
    public function generateRequestNo(): string
    {
        $year = date('Y');
        $prefix = "PR-{$year}-";

        $stmt = $this->pdo->prepare("
            SELECT request_no 
            FROM purchase_requests 
            WHERE request_no LIKE :prefix 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute([':prefix' => $prefix . '%']);
        $lastNo = $stmt->fetchColumn();

        $nextSeq = 1;
        if ($lastNo && is_string($lastNo)) {
            $parts = explode('-', $lastNo);
            if (isset($parts[2]) && is_numeric($parts[2])) {
                $nextSeq = (int)$parts[2] + 1;
            }
        }

        return sprintf('PR-%s-%06d', $year, $nextSeq);
    }

    /**
     * Satın alma talebi başlık verilerini doğrular.
     * Hata varsa hata mesajları dizisi döner; geçerliyse boş dizi döner.
     */
    public function validateRequestData(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        // requested_by (Yalnızca oluşturma sırasında zorunlu veya güncelleniyorsa)
        if (!$isUpdate || array_key_exists('requested_by', $data)) {
            $requestedBy = (int)($data['requested_by'] ?? 0);
            if ($requestedBy <= 0) {
                $errors[] = 'Talep eden kullanıcı (requested_by) seçilmelidir.';
            } else {
                $stmt = $this->pdo->prepare("SELECT id, is_active FROM users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $requestedBy]);
                $user = $stmt->fetch();
                if (!$user) {
                    $errors[] = 'Seçilen talep eden kullanıcı sistemde bulunamadı.';
                } elseif ((int)$user['is_active'] !== 1) {
                    $errors[] = 'Talep eden kullanıcı aktif bir kullanıcı olmalıdır.';
                }
            }
        }

        // department_id
        if (!$isUpdate || array_key_exists('department_id', $data)) {
            $deptId = (int)($data['department_id'] ?? 0);
            if ($deptId <= 0) {
                $errors[] = 'Departman seçimi zorunludur.';
            } else {
                $stmt = $this->pdo->prepare("SELECT id, is_active FROM departments WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $deptId]);
                $dept = $stmt->fetch();
                if (!$dept) {
                    $errors[] = 'Seçilen departman sistemde bulunamadı.';
                } elseif ((int)$dept['is_active'] !== 1) {
                    $errors[] = 'Seçilen departman aktif bir departman olmalıdır.';
                }
            }
        }

        // request_date
        $requestDate = trim((string)($data['request_date'] ?? ''));
        if ($requestDate === '') {
            $errors[] = 'Talep tarihi zorunludur.';
        } elseif (!$this->isValidDate($requestDate)) {
            $errors[] = 'Talep tarihi geçerli bir tarih formatında (YYYY-AA-GG) olmalıdır.';
        }

        // required_date
        $requiredDate = trim((string)($data['required_date'] ?? ''));
        if ($requiredDate === '') {
            $errors[] = 'İhtiyaç duyulan tarih (required_date) zorunludur.';
        } elseif (!$this->isValidDate($requiredDate)) {
            $errors[] = 'İhtiyaç duyulan tarih geçerli bir formatta (YYYY-AA-GG) olmalıdır.';
        } elseif ($this->isValidDate($requestDate) && $requiredDate < $requestDate) {
            $errors[] = 'İhtiyaç tarihi, talep tarihinden daha önceki bir tarih olamaz.';
        }

        // priority
        $priority = trim((string)($data['priority'] ?? 'MEDIUM'));
        if (!in_array($priority, self::PRIORITIES, true)) {
            $errors[] = 'Geçersiz öncelik derecesi: ' . htmlspecialchars($priority);
        }

        return $errors;
    }

    /**
     * Tek bir talep kalemi verisini doğrular.
     * Hata varsa hata mesajları dizisi döner; geçerliyse boş dizi döner.
     */
    public function validateItemData(array $item, int $index = 1): array
    {
        $errors = [];
        $prefix = "Kalem #{$index}: ";

        // material_id
        $materialId = (int)($item['material_id'] ?? 0);
        if ($materialId <= 0) {
            $errors[] = $prefix . 'Malzeme seçimi zorunludur.';
        } else {
            $stmt = $this->pdo->prepare("SELECT id, is_active FROM materials WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $materialId]);
            $mat = $stmt->fetch();
            if (!$mat) {
                $errors[] = $prefix . 'Seçilen malzeme bulunamadı.';
            } elseif ((int)$mat['is_active'] !== 1) {
                $errors[] = $prefix . 'Seçilen malzeme aktif değil.';
            }
        }

        // requested_quantity
        if (!isset($item['requested_quantity']) || !is_numeric($item['requested_quantity'])) {
            $errors[] = $prefix . 'Talep miktarı sayısal bir değer olmalıdır.';
        } else {
            $qty = (float)$item['requested_quantity'];
            if ($qty <= 0) {
                $errors[] = $prefix . 'Talep miktarı 0\'dan büyük olmalıdır.';
            }
        }

        // estimated_unit_price (Opsiyonel / NULL veya >= 0)
        if (isset($item['estimated_unit_price']) && $item['estimated_unit_price'] !== null && trim((string)$item['estimated_unit_price']) !== '') {
            if (!is_numeric($item['estimated_unit_price'])) {
                $errors[] = $prefix . 'Tahmini birim fiyat sayısal bir değer olmalıdır.';
            } else {
                $price = (float)$item['estimated_unit_price'];
                if ($price < 0) {
                    $errors[] = $prefix . 'Tahmini birim fiyat negatif olamaz.';
                }
            }
        }

        // currency
        $currency = trim((string)($item['currency'] ?? 'TL'));
        if ($currency === '') {
            $currency = 'TL';
        }
        if (!in_array($currency, self::CURRENCIES, true)) {
            $errors[] = $prefix . 'Geçersiz para birimi: ' . htmlspecialchars($currency) . '. (Geçerli: ' . implode(', ', self::CURRENCIES) . ')';
        }

        // suggested_supplier_id (Opsiyonel)
        if (!empty($item['suggested_supplier_id'])) {
            $supplierId = (int)$item['suggested_supplier_id'];
            $stmt = $this->pdo->prepare("SELECT id, is_active FROM suppliers WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $supplierId]);
            $supp = $stmt->fetch();
            if (!$supp) {
                $errors[] = $prefix . 'Önerilen tedarikçi sistemde bulunamadı.';
            } elseif ((int)$supp['is_active'] !== 1) {
                $errors[] = $prefix . 'Önerilen tedarikçi aktif değil.';
            }
        }

        return $errors;
    }

    /**
     * Yeni bir Satın Alma Talebi oluşturur (Atomik Transaction).
     * Başlık ve en az 1 kalem birlikte eklenir.
     *
     * @param array $data Başlık verileri (department_id, requested_by, request_date, required_date, priority, description)
     * @param array $items Kalem dizisi [[material_id, requested_quantity, estimated_unit_price, currency, suggested_supplier_id, notes], ...]
     * @return int Oluşturulan purchase_requests.id
     * @throws InvalidArgumentException Validasyon hatasında
     * @throws RuntimeException Veritabanı veya işlem hatasında
     */
    public function create(array $data, array $items): int
    {
        if (empty($items)) {
            throw new InvalidArgumentException('Satın alma talebine en az bir malzeme kalemi eklenmelidir.');
        }

        // 1. Başlık validasyonu
        $headerErrors = $this->validateRequestData($data, false);
        if (!empty($headerErrors)) {
            throw new InvalidArgumentException(implode(' | ', $headerErrors));
        }

        // 2. Kalem validasyonu
        $itemErrors = [];
        $index = 1;
        foreach ($items as $item) {
            $errors = $this->validateItemData($item, $index++);
            if (!empty($errors)) {
                $itemErrors = array_merge($itemErrors, $errors);
            }
        }
        if (!empty($itemErrors)) {
            throw new InvalidArgumentException(implode(' | ', $itemErrors));
        }

        // 3. Atomik Transaction
        $this->pdo->beginTransaction();

        try {
            // Talep numarası üret (varsa parametreden, yoksa otomatik)
            $requestNo = !empty($data['request_no']) ? trim((string)$data['request_no']) : $this->generateRequestNo();

            // Çakışma kontrolü
            $checkStmt = $this->pdo->prepare("SELECT id FROM purchase_requests WHERE request_no = :no LIMIT 1");
            $checkStmt->execute([':no' => $requestNo]);
            if ($checkStmt->fetch()) {
                // Eğer manuel verilen veya üretilen numara zaten varsa yenisini üret
                $requestNo = $this->generateRequestNo();
            }

            $stmtHeader = $this->pdo->prepare("
                INSERT INTO purchase_requests (
                    request_no,
                    requested_by,
                    department_id,
                    request_date,
                    required_date,
                    priority,
                    status,
                    description,
                    approval_notes,
                    approved_by,
                    approved_at,
                    created_at,
                    updated_at
                ) VALUES (
                    :request_no,
                    :requested_by,
                    :department_id,
                    :request_date,
                    :required_date,
                    :priority,
                    'DRAFT',
                    :description,
                    NULL,
                    NULL,
                    NULL,
                    NOW(),
                    NOW()
                )
            ");

            $stmtHeader->execute([
                ':request_no'    => $requestNo,
                ':requested_by'  => (int)$data['requested_by'],
                ':department_id' => (int)$data['department_id'],
                ':request_date'  => trim((string)$data['request_date']),
                ':required_date' => trim((string)$data['required_date']),
                ':priority'      => trim((string)($data['priority'] ?? 'MEDIUM')),
                ':description'   => !empty($data['description']) ? trim((string)$data['description']) : null,
            ]);

            $requestId = (int)$this->pdo->lastInsertId();

            // Kalemleri ekle
            $stmtItem = $this->pdo->prepare("
                INSERT INTO purchase_request_items (
                    purchase_request_id,
                    material_id,
                    requested_quantity,
                    estimated_unit_price,
                    currency,
                    suggested_supplier_id,
                    notes,
                    created_at,
                    updated_at
                ) VALUES (
                    :purchase_request_id,
                    :material_id,
                    :requested_quantity,
                    :estimated_unit_price,
                    :currency,
                    :suggested_supplier_id,
                    :notes,
                    NOW(),
                    NOW()
                )
            ");

            foreach ($items as $item) {
                $price = (isset($item['estimated_unit_price']) && $item['estimated_unit_price'] !== null && trim((string)$item['estimated_unit_price']) !== '')
                    ? (float)$item['estimated_unit_price']
                    : null;
                $supplierId = !empty($item['suggested_supplier_id']) ? (int)$item['suggested_supplier_id'] : null;
                $notes = !empty($item['notes']) ? trim((string)$item['notes']) : null;
                $currency = !empty($item['currency']) ? trim((string)$item['currency']) : 'TL';

                $stmtItem->execute([
                    ':purchase_request_id'   => $requestId,
                    ':material_id'           => (int)$item['material_id'],
                    ':requested_quantity'    => (float)$item['requested_quantity'],
                    ':estimated_unit_price'  => $price,
                    ':currency'              => $currency,
                    ':suggested_supplier_id' => $supplierId,
                    ':notes'                 => $notes,
                ]);
            }

            $this->pdo->commit();
            return $requestId;

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Satın alma talebi oluşturulurken hata oluştu: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * ID ile tekil satın alma talebi detayını ilişkileriyle birlikte getirir.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                pr.*,
                d.name AS department_name,
                d.code AS department_code,
                u.first_name AS requester_first_name,
                u.last_name AS requester_last_name,
                u.username AS requester_username,
                u.email AS requester_email,
                TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS requester_name,
                appr.first_name AS approver_first_name,
                appr.last_name AS approver_last_name,
                appr.username AS approver_username,
                appr.email AS approver_email,
                TRIM(CONCAT(COALESCE(appr.first_name, ''), ' ', COALESCE(appr.last_name, ''))) AS approver_name,
                (SELECT COUNT(*) FROM purchase_request_items pri WHERE pri.purchase_request_id = pr.id) AS item_count,
                (SELECT COALESCE(SUM(pri.requested_quantity * COALESCE(pri.estimated_unit_price, 0)), 0) 
                 FROM purchase_request_items pri 
                 WHERE pri.purchase_request_id = pr.id) AS total_estimated_amount
            FROM purchase_requests pr
            LEFT JOIN departments d ON d.id = pr.department_id
            LEFT JOIN users u ON u.id = pr.requested_by
            LEFT JOIN users appr ON appr.id = pr.approved_by
            WHERE pr.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Talep numarası ile tekil talep detayını getirir.
     */
    public function findByRequestNo(string $requestNo): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                pr.*,
                d.name AS department_name,
                d.code AS department_code,
                TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS requester_name,
                TRIM(CONCAT(COALESCE(appr.first_name, ''), ' ', COALESCE(appr.last_name, ''))) AS approver_name
            FROM purchase_requests pr
            LEFT JOIN departments d ON d.id = pr.department_id
            LEFT JOIN users u ON u.id = pr.requested_by
            LEFT JOIN users appr ON appr.id = pr.approved_by
            WHERE pr.request_no = :request_no
            LIMIT 1
        ");

        $stmt->execute([':request_no' => trim($requestNo)]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Bir talebe ait tüm malzeme kalemlerini ilişkileriyle birlikte getirir.
     */
    public function getItems(int $purchaseRequestId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                pri.*,
                m.code AS material_code,
                m.name AS material_name,
                m.category_id,
                un.id AS unit_id,
                un.name AS unit_name,
                un.symbol AS unit_symbol,
                s.code AS supplier_code,
                s.name AS supplier_name,
                (pri.requested_quantity * COALESCE(pri.estimated_unit_price, 0)) AS line_total
            FROM purchase_request_items pri
            LEFT JOIN materials m ON m.id = pri.material_id
            LEFT JOIN units un ON un.id = m.unit_id
            LEFT JOIN suppliers s ON s.id = pri.suggested_supplier_id
            WHERE pri.purchase_request_id = :purchase_request_id
            ORDER BY pri.id ASC
        ");

        $stmt->execute([':purchase_request_id' => $purchaseRequestId]);
        return $stmt->fetchAll();
    }

    /**
     * Filtreli ve sayfalamalı talep listesini getirir.
     */
    public function getAll(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $sql = "
            SELECT 
                pr.*,
                d.name AS department_name,
                d.code AS department_code,
                TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS requester_name,
                u.username AS requester_username,
                TRIM(CONCAT(COALESCE(appr.first_name, ''), ' ', COALESCE(appr.last_name, ''))) AS approver_name,
                (SELECT COUNT(*) FROM purchase_request_items pri WHERE pri.purchase_request_id = pr.id) AS item_count,
                (SELECT COALESCE(SUM(pri.requested_quantity * COALESCE(pri.estimated_unit_price, 0)), 0) 
                 FROM purchase_request_items pri 
                 WHERE pri.purchase_request_id = pr.id) AS total_estimated_amount
            FROM purchase_requests pr
            LEFT JOIN departments d ON d.id = pr.department_id
            LEFT JOIN users u ON u.id = pr.requested_by
            LEFT JOIN users appr ON appr.id = pr.approved_by
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND pr.status = :status";
            $params[':status'] = trim((string)$filters['status']);
        }

        if (!empty($filters['priority'])) {
            $sql .= " AND pr.priority = :priority";
            $params[':priority'] = trim((string)$filters['priority']);
        }

        if (!empty($filters['department_id'])) {
            $sql .= " AND pr.department_id = :department_id";
            $params[':department_id'] = (int)$filters['department_id'];
        }

        if (!empty($filters['requested_by'])) {
            $sql .= " AND pr.requested_by = :requested_by";
            $params[':requested_by'] = (int)$filters['requested_by'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND pr.request_date >= :date_from";
            $params[':date_from'] = trim((string)$filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND pr.request_date <= :date_to";
            $params[':date_to'] = trim((string)$filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (pr.request_no LIKE :search OR pr.description LIKE :search)";
            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $sql .= " ORDER BY pr.id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Filtrelere uyan toplam talep sayısını döner.
     */
    public function countAll(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM purchase_requests pr WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND pr.status = :status";
            $params[':status'] = trim((string)$filters['status']);
        }

        if (!empty($filters['priority'])) {
            $sql .= " AND pr.priority = :priority";
            $params[':priority'] = trim((string)$filters['priority']);
        }

        if (!empty($filters['department_id'])) {
            $sql .= " AND pr.department_id = :department_id";
            $params[':department_id'] = (int)$filters['department_id'];
        }

        if (!empty($filters['requested_by'])) {
            $sql .= " AND pr.requested_by = :requested_by";
            $params[':requested_by'] = (int)$filters['requested_by'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND pr.request_date >= :date_from";
            $params[':date_from'] = trim((string)$filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND pr.request_date <= :date_to";
            $params[':date_to'] = trim((string)$filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (pr.request_no LIKE :search OR pr.description LIKE :search)";
            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Satın alma talepleri için 5 kompakt KPI özetini döner.
     */
    public function getKpis(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN status = 'DRAFT' THEN 1 ELSE 0 END), 0) AS draft_count,
                COALESCE(SUM(CASE WHEN status = 'SUBMITTED' THEN 1 ELSE 0 END), 0) AS submitted_count,
                COALESCE(SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END), 0) AS approved_count,
                COALESCE(SUM(CASE WHEN status = 'ORDERED' THEN 1 ELSE 0 END), 0) AS ordered_count
            FROM purchase_requests
        ");
        $res = $stmt->fetch();

        return [
            'total'     => (int)($res['total'] ?? 0),
            'draft'     => (int)($res['draft_count'] ?? 0),
            'submitted' => (int)($res['submitted_count'] ?? 0),
            'approved'  => (int)($res['approved_count'] ?? 0),
            'ordered'   => (int)($res['ordered_count'] ?? 0),
        ];
    }

    /**
     * Yalnızca DRAFT durumundaki talebi ve opsiyonel olarak kalemlerini günceller.
     *
     * @param int $id purchase_requests.id
     * @param array $data Başlık verileri (department_id, request_date, required_date, priority, description)
     * @param array|null $items Yeni kalem listesi (null verilirse kalemler değiştirilmez)
     * @return bool
     * @throws RuntimeException Talep DRAFT değilse veya veritabanı hatasında
     * @throws InvalidArgumentException Validasyon hatasında
     */
    public function update(int $id, array $data, ?array $items = null): bool
    {
        // 1. Başlık validasyonu
        $headerErrors = $this->validateRequestData($data, true);
        if (!empty($headerErrors)) {
            throw new InvalidArgumentException(implode(' | ', $headerErrors));
        }

        // 2. Kalem validasyonu (varsa)
        if ($items !== null) {
            if (empty($items)) {
                throw new InvalidArgumentException('Talepte en az bir malzeme kalemi bulunmalıdır.');
            }
            $itemErrors = [];
            $index = 1;
            foreach ($items as $item) {
                $errors = $this->validateItemData($item, $index++);
                if (!empty($errors)) {
                    $itemErrors = array_merge($itemErrors, $errors);
                }
            }
            if (!empty($itemErrors)) {
                throw new InvalidArgumentException(implode(' | ', $itemErrors));
            }
        }

        // 3. Atomik Transaction & Durum Kontrolü
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("SELECT * FROM purchase_requests WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $id]);
            $request = $stmt->fetch();

            if (!$request) {
                throw new RuntimeException("ID={$id} olan satın alma talebi bulunamadı.");
            }

            if ($request['status'] !== 'DRAFT') {
                throw new RuntimeException("Sadece TASLAK (DRAFT) durumundaki talepler düzenlenebilir. Mevcut durum: {$request['status']}");
            }

            // Başlık güncelle
            $updateSql = "
                UPDATE purchase_requests SET
                    department_id = :department_id,
                    request_date  = :request_date,
                    required_date = :required_date,
                    priority      = :priority,
                    description   = :description,
                    updated_at    = NOW()
                WHERE id = :id
            ";

            $stmtUpdate = $this->pdo->prepare($updateSql);
            $stmtUpdate->execute([
                ':id'            => $id,
                ':department_id' => (int)$data['department_id'],
                ':request_date'  => trim((string)$data['request_date']),
                ':required_date' => trim((string)$data['required_date']),
                ':priority'      => trim((string)($data['priority'] ?? 'MEDIUM')),
                ':description'   => !empty($data['description']) ? trim((string)$data['description']) : null,
            ]);

            // Kalemler güncelleniyorsa
            if ($items !== null) {
                // Eski kalemleri sil
                $delStmt = $this->pdo->prepare("DELETE FROM purchase_request_items WHERE purchase_request_id = :id");
                $delStmt->execute([':id' => $id]);

                // Yeni kalemleri ekle
                $stmtItem = $this->pdo->prepare("
                    INSERT INTO purchase_request_items (
                        purchase_request_id,
                        material_id,
                        requested_quantity,
                        estimated_unit_price,
                        currency,
                        suggested_supplier_id,
                        notes,
                        created_at,
                        updated_at
                    ) VALUES (
                        :purchase_request_id,
                        :material_id,
                        :requested_quantity,
                        :estimated_unit_price,
                        :currency,
                        :suggested_supplier_id,
                        :notes,
                        NOW(),
                        NOW()
                    )
                ");

                foreach ($items as $item) {
                    $price = (isset($item['estimated_unit_price']) && $item['estimated_unit_price'] !== null && trim((string)$item['estimated_unit_price']) !== '')
                        ? (float)$item['estimated_unit_price']
                        : null;
                    $supplierId = !empty($item['suggested_supplier_id']) ? (int)$item['suggested_supplier_id'] : null;
                    $notes = !empty($item['notes']) ? trim((string)$item['notes']) : null;
                    $currency = !empty($item['currency']) ? trim((string)$item['currency']) : 'TL';

                    $stmtItem->execute([
                        ':purchase_request_id'   => $id,
                        ':material_id'           => (int)$item['material_id'],
                        ':requested_quantity'    => (float)$item['requested_quantity'],
                        ':estimated_unit_price'  => $price,
                        ':currency'              => $currency,
                        ':suggested_supplier_id' => $supplierId,
                        ':notes'                 => $notes,
                    ]);
                }
            }

            $this->pdo->commit();
            return true;

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Satın alma talebi güncellenirken hata oluştu: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Talebi onaya gönderir: DRAFT -> SUBMITTED
     */
    public function submit(int $id): bool
    {
        return $this->transitionStatus($id, 'SUBMITTED', function (array $request) use ($id) {
            // Kalem sayısı kontrolü
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM purchase_request_items WHERE purchase_request_id = :id");
            $stmt->execute([':id' => $id]);
            $count = (int)$stmt->fetchColumn();

            if ($count <= 0) {
                throw new RuntimeException('Kalemi olmayan talep onaya gönderilemez.');
            }
        });
    }

    /**
     * Talebi onaylar: SUBMITTED -> APPROVED
     */
    public function approve(int $id, int $approvedByUserId, ?string $approvalNotes = null): bool
    {
        // Kullanıcı kontrolü
        $this->validateApproverUser($approvedByUserId);

        return $this->transitionStatus($id, 'APPROVED', function (array $request) use ($approvedByUserId, $approvalNotes, $id) {
            $stmt = $this->pdo->prepare("
                UPDATE purchase_requests SET
                    status         = 'APPROVED',
                    approved_by    = :approved_by,
                    approved_at    = NOW(),
                    approval_notes = :approval_notes,
                    updated_at     = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                ':id'             => $id,
                ':approved_by'    => $approvedByUserId,
                ':approval_notes' => $approvalNotes !== null ? trim($approvalNotes) : null,
            ]);
        }, true);
    }

    /**
     * Talebi reddeder: SUBMITTED -> REJECTED
     */
    public function reject(int $id, int $rejectedByUserId, ?string $rejectionNotes = null): bool
    {
        // Kullanıcı kontrolü
        $this->validateApproverUser($rejectedByUserId);

        return $this->transitionStatus($id, 'REJECTED', function (array $request) use ($rejectedByUserId, $rejectionNotes, $id) {
            $stmt = $this->pdo->prepare("
                UPDATE purchase_requests SET
                    status         = 'REJECTED',
                    approved_by    = :approved_by,
                    approved_at    = NOW(),
                    approval_notes = :approval_notes,
                    updated_at     = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                ':id'             => $id,
                ':approved_by'    => $rejectedByUserId,
                ':approval_notes' => $rejectionNotes !== null ? trim($rejectionNotes) : null,
            ]);
        }, true);
    }

    /**
     * Talebi iptal eder: DRAFT -> CANCELLED veya SUBMITTED -> CANCELLED
     */
    public function cancel(int $id, ?string $reason = null): bool
    {
        return $this->transitionStatus($id, 'CANCELLED', function (array $request) use ($reason, $id) {
            if ($reason !== null && trim($reason) !== '') {
                $stmt = $this->pdo->prepare("
                    UPDATE purchase_requests SET
                        status         = 'CANCELLED',
                        approval_notes = :reason,
                        updated_at     = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':id'     => $id,
                    ':reason' => trim($reason),
                ]);
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE purchase_requests SET
                        status     = 'CANCELLED',
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([':id' => $id]);
            }
        }, true);
    }

    /**
     * Siparişe dönüştürüldü olarak işaretler: APPROVED -> ORDERED
     */
    public function markOrdered(int $id): bool
    {
        return $this->transitionStatus($id, 'ORDERED');
    }

    /**
     * Güvenli durum geçişi yönetimi (State Machine Enforcer).
     *
     * @param int $id purchase_requests.id
     * @param string $targetStatus Hedef durum
     * @param callable|null $customExecution Özel update mantığı (null ise standart status UPDATE çalışır)
     * @param bool $customHandlesStatusUpdate Özel execution status kolonunu kendisi güncelliyor mu
     */
    private function transitionStatus(
        int $id,
        string $targetStatus,
        ?callable $customExecution = null,
        bool $customHandlesStatusUpdate = false
    ): bool {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("SELECT * FROM purchase_requests WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $id]);
            $request = $stmt->fetch();

            if (!$request) {
                throw new RuntimeException("ID={$id} olan satın alma talebi bulunamadı.");
            }

            $currentStatus = $request['status'];

            if (!self::canTransition($currentStatus, $targetStatus)) {
                throw new RuntimeException("Geçersiz durum geçişi: '{$currentStatus}' durumundan '{$targetStatus}' durumuna geçilemez.");
            }

            if ($customExecution !== null) {
                $customExecution($request);
            }

            if (!$customHandlesStatusUpdate) {
                $updateStmt = $this->pdo->prepare("
                    UPDATE purchase_requests SET
                        status     = :target_status,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $updateStmt->execute([
                    ':id'            => $id,
                    ':target_status' => $targetStatus,
                ]);
            }

            $this->pdo->commit();
            return true;

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Durum güncellenirken hata oluştu: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Onaylayan / Reddeden kullanıcı ID doğrulaması
     */
    private function validateApproverUser(int $userId): void
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Onaylayan/Reddeden kullanıcı seçilmelidir.');
        }

        $stmt = $this->pdo->prepare("SELECT id, is_active FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new InvalidArgumentException('Onaylayan/Reddeden kullanıcı sistemde bulunamadı.');
        }

        if ((int)$user['is_active'] !== 1) {
            throw new InvalidArgumentException('Onaylayan/Reddeden kullanıcı aktif bir kullanıcı olmalıdır.');
        }
    }

    /**
     * Tarih formatı doğrulaması (YYYY-AA-GG)
     */
    private function isValidDate(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Durum etiketini Türkçe döner.
     */
    public static function statusLabel(string $status): string
    {
        $labels = [
            'DRAFT'     => 'Taslak',
            'SUBMITTED' => 'Onay Bekliyor',
            'APPROVED'  => 'Onaylandı',
            'REJECTED'  => 'Reddedildi',
            'ORDERED'   => 'Siparişe Dönüştü',
            'CANCELLED' => 'İptal Edildi',
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * Öncelik etiketini Türkçe döner.
     */
    public static function priorityLabel(string $priority): string
    {
        $labels = [
            'LOW'    => 'Düşük',
            'MEDIUM' => 'Normal',
            'HIGH'   => 'Yüksek',
            'URGENT' => 'Acil',
        ];

        return $labels[$priority] ?? $priority;
    }

    /**
     * Durum için CSS Badge sınıfı döner.
     */
    public static function statusBadgeClass(string $status): string
    {
        $classes = [
            'DRAFT'     => 'badge-secondary',
            'SUBMITTED' => 'badge-warning',
            'APPROVED'  => 'badge-success',
            'REJECTED'  => 'badge-danger',
            'ORDERED'   => 'badge-info',
            'CANCELLED' => 'badge-dark',
        ];

        return $classes[$status] ?? 'badge-secondary';
    }

    /**
     * Öncelik için CSS Badge sınıfı döner.
     */
    public static function priorityBadgeClass(string $priority): string
    {
        $classes = [
            'LOW'    => 'badge-info',
            'MEDIUM' => 'badge-primary',
            'HIGH'   => 'badge-warning',
            'URGENT' => 'badge-danger',
        ];

        return $classes[$priority] ?? 'badge-secondary';
    }

    /**
     * Aktif departmanları döner.
     */
    public function getActiveDepartments(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, code, name 
            FROM departments 
            WHERE is_active = 1 
            ORDER BY name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Aktif malzemeleri birim bilgisiyle döner.
     */
    public function getActiveMaterials(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                m.id, 
                m.code, 
                m.name, 
                m.unit_price, 
                m.currency,
                u.symbol AS unit_symbol,
                u.name AS unit_name
            FROM materials m
            LEFT JOIN units u ON u.id = m.unit_id
            WHERE m.is_active = 1
            ORDER BY m.name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Aktif tedarikçileri döner.
     */
    public function getActiveSuppliers(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, code, name, contact_name, phone, email 
            FROM suppliers 
            WHERE is_active = 1 
            ORDER BY name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Talep edebilecek aktif kullanıcıları döner.
     */
    public function getActiveRequesters(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                id, 
                username, 
                TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) AS full_name 
            FROM users 
            WHERE is_active = 1 
            ORDER BY first_name ASC, last_name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Kullanıcının bağlı olduğu departmanı çalışan kaydından bulur.
     */
    public function getUserDepartmentId(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $stmt = $this->pdo->prepare("
            SELECT department_id 
            FROM employees 
            WHERE user_id = :user_id AND is_active = 1 
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $userId]);
        return (int)($stmt->fetchColumn() ?: 0);
    }
}

