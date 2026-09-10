<?php
declare(strict_types=1);

class PurchaseOrder
{
    private PDO $pdo;

    public const STATUSES = [
        'DRAFT',
        'SENT',
        'CONFIRMED',
        'PARTIALLY_RECEIVED',
        'RECEIVED',
        'CANCELLED',
    ];

    public const CURRENCIES = [
        'TRY',
        'USD',
        'EUR',
    ];

    public const STATUS_TRANSITIONS = [
        'DRAFT'              => ['SENT', 'CANCELLED'],
        'SENT'               => ['CONFIRMED', 'CANCELLED'],
        'CONFIRMED'          => ['PARTIALLY_RECEIVED', 'RECEIVED', 'CANCELLED'],
        'PARTIALLY_RECEIVED' => ['RECEIVED'],
        'RECEIVED'           => [],
        'CANCELLED'          => [],
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
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
     * Güvenli ve sıralı Satın Alma Sipariş Numarası üretir: PO-YYYY-XXXXXX
     * Örnek: PO-2026-000001
     */
    public function generateOrderNo(): string
    {
        $year = date('Y');
        $prefix = "PO-{$year}-";

        $stmt = $this->pdo->prepare("
            SELECT order_no 
            FROM purchase_orders 
            WHERE order_no LIKE :prefix 
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

        return sprintf('PO-%s-%06d', $year, $nextSeq);
    }

    /**
     * Satın alma siparişi başlık verilerini doğrular.
     * Hata varsa hata mesajları dizisi döner; geçerliyse boş dizi döner.
     */
    public function validateOrderData(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        // supplier_id
        if (!$isUpdate || array_key_exists('supplier_id', $data)) {
            $supplierId = (int)($data['supplier_id'] ?? 0);
            if ($supplierId <= 0) {
                $errors[] = 'Tedarikçi (supplier_id) seçimi zorunludur.';
            } else {
                $stmt = $this->pdo->prepare("SELECT id, is_active FROM suppliers WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $supplierId]);
                $supplier = $stmt->fetch();
                if (!$supplier) {
                    $errors[] = 'Seçilen tedarikçi sistemde bulunamadı.';
                } elseif ((int)$supplier['is_active'] !== 1) {
                    $errors[] = 'Seçilen tedarikçi aktif bir tedarikçi olmalıdır.';
                }
            }
        }

        // ordered_by (Yalnızca oluşturma sırasında zorunlu veya güncelleniyorsa)
        if (!$isUpdate || array_key_exists('ordered_by', $data)) {
            $orderedBy = (int)($data['ordered_by'] ?? 0);
            if ($orderedBy <= 0) {
                $errors[] = 'Siparişi veren kullanıcı (ordered_by) seçilmelidir.';
            } else {
                $stmt = $this->pdo->prepare("SELECT id, is_active FROM users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $orderedBy]);
                $user = $stmt->fetch();
                if (!$user) {
                    $errors[] = 'Siparişi veren kullanıcı sistemde bulunamadı.';
                } elseif ((int)$user['is_active'] !== 1) {
                    $errors[] = 'Siparişi veren kullanıcı aktif bir kullanıcı olmalıdır.';
                }
            }
        }

        // order_date
        $orderDate = trim((string)($data['order_date'] ?? ''));
        if ($orderDate === '') {
            $errors[] = 'Sipariş tarihi zorunludur.';
        } elseif (!$this->isValidDate($orderDate)) {
            $errors[] = 'Sipariş tarihi geçerli bir formatta (YYYY-AA-GG) olmalıdır.';
        }

        // expected_delivery_date (Opsiyonel)
        $deliveryDate = trim((string)($data['expected_delivery_date'] ?? ''));
        if ($deliveryDate !== '') {
            if (!$this->isValidDate($deliveryDate)) {
                $errors[] = 'Beklenen teslim tarihi geçerli bir formatta (YYYY-AA-GG) olmalıdır.';
            } elseif ($this->isValidDate($orderDate) && $deliveryDate < $orderDate) {
                $errors[] = 'Beklenen teslim tarihi, sipariş tarihinden daha önceki bir tarih olamaz.';
            }
        }

        // currency
        $currency = strtoupper(trim((string)($data['currency'] ?? 'TRY')));
        if ($currency === 'TL') {
            $currency = 'TRY';
        }
        if (!in_array($currency, self::CURRENCIES, true)) {
            $errors[] = 'Geçersiz para birimi: ' . htmlspecialchars($currency) . '. (Geçerli: ' . implode(', ', self::CURRENCIES) . ')';
        }

        // tax_rate
        if (isset($data['tax_rate']) && $data['tax_rate'] !== null && trim((string)$data['tax_rate']) !== '') {
            if (!is_numeric($data['tax_rate'])) {
                $errors[] = 'Vergi oranı (tax_rate) sayısal olmalıdır.';
            } else {
                $tax = (float)$data['tax_rate'];
                if ($tax < 0 || $tax > 100) {
                    $errors[] = 'Vergi oranı %0 ile %100 arasında olmalıdır.';
                }
            }
        }

        // purchase_request_id (Opsiyonel)
        if (!empty($data['purchase_request_id'])) {
            $prId = (int)$data['purchase_request_id'];
            $stmt = $this->pdo->prepare("SELECT id FROM purchase_requests WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $prId]);
            if (!$stmt->fetch()) {
                $errors[] = 'Bağlı satın alma talebi (PR) sistemde bulunamadı.';
            }
        }

        return $errors;
    }

    /**
     * Tek bir sipariş kalemi verisini doğrular.
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

        // ordered_quantity
        if (!isset($item['ordered_quantity']) || !is_numeric($item['ordered_quantity'])) {
            $errors[] = $prefix . 'Sipariş miktarı sayısal bir değer olmalıdır.';
        } else {
            $qty = (float)$item['ordered_quantity'];
            if ($qty <= 0) {
                $errors[] = $prefix . 'Sipariş miktarı 0\'dan büyük olmalıdır.';
            }
        }

        // received_quantity
        if (isset($item['received_quantity']) && $item['received_quantity'] !== null && trim((string)$item['received_quantity']) !== '') {
            if (!is_numeric($item['received_quantity'])) {
                $errors[] = $prefix . 'Teslim alınan miktar sayısal olmalıdır.';
            } else {
                $recQty = (float)$item['received_quantity'];
                $ordQty = isset($item['ordered_quantity']) && is_numeric($item['ordered_quantity']) ? (float)$item['ordered_quantity'] : 0.0;
                if ($recQty < 0) {
                    $errors[] = $prefix . 'Teslim alınan miktar negatif olamaz.';
                } elseif ($recQty > $ordQty) {
                    $errors[] = $prefix . 'Teslim alınan miktar sipariş miktarından fazla olamaz.';
                }
            }
        }

        // unit_price
        if (!isset($item['unit_price']) || !is_numeric($item['unit_price'])) {
            $errors[] = $prefix . 'Birim fiyat sayısal bir değer olmalıdır.';
        } else {
            $price = (float)$item['unit_price'];
            if ($price < 0) {
                $errors[] = $prefix . 'Birim fiyat negatif olamaz.';
            }
        }

        // currency
        $currency = strtoupper(trim((string)($item['currency'] ?? 'TRY')));
        if ($currency === 'TL') {
            $currency = 'TRY';
        }
        if (!in_array($currency, self::CURRENCIES, true)) {
            $errors[] = $prefix . 'Geçersiz para birimi: ' . htmlspecialchars($currency) . '. (Geçerli: ' . implode(', ', self::CURRENCIES) . ')';
        }

        // tax_rate
        if (isset($item['tax_rate']) && $item['tax_rate'] !== null && trim((string)$item['tax_rate']) !== '') {
            if (!is_numeric($item['tax_rate'])) {
                $errors[] = $prefix . 'Kalem vergi oranı sayısal olmalıdır.';
            } else {
                $tax = (float)$item['tax_rate'];
                if ($tax < 0 || $tax > 100) {
                    $errors[] = $prefix . 'Kalem vergi oranı %0 ile %100 arasında olmalıdır.';
                }
            }
        }

        // purchase_request_item_id (Opsiyonel)
        if (!empty($item['purchase_request_item_id'])) {
            $priId = (int)$item['purchase_request_item_id'];
            $stmt = $this->pdo->prepare("SELECT id FROM purchase_request_items WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $priId]);
            if (!$stmt->fetch()) {
                $errors[] = $prefix . 'Bağlı talep kalemi (PR Item) sistemde bulunamadı.';
            }
        }

        return $errors;
    }

    /**
     * Kalemlerden ve genel vergi oranından finansal toplamları hesaplar.
     */
    public function calculateTotals(array $items, float $defaultTaxRate = 20.00): array
    {
        $subtotal = 0.0;
        $totalTax = 0.0;

        foreach ($items as $item) {
            $qty = (float)($item['ordered_quantity'] ?? 0.0);
            $price = (float)($item['unit_price'] ?? 0.0);
            $taxRate = isset($item['tax_rate']) && is_numeric($item['tax_rate']) ? (float)$item['tax_rate'] : $defaultTaxRate;

            $lineTotal = round($qty * $price, 4);
            $lineTax = round($lineTotal * ($taxRate / 100.0), 4);

            $subtotal += $lineTotal;
            $totalTax += $lineTax;
        }

        $subtotal = round($subtotal, 4);
        $totalTax = round($totalTax, 4);
        $totalAmount = round($subtotal + $totalTax, 4);

        return [
            'subtotal'     => $subtotal,
            'tax_amount'   => $totalTax,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Yeni bir Satın Alma Siparişi (PO) oluşturur (Atomik Transaction).
     *
     * @param array $data Başlık verileri (supplier_id, ordered_by, order_date, expected_delivery_date, payment_terms, delivery_terms, currency, tax_rate, notes, purchase_request_id)
     * @param array $items Kalem dizisi [[material_id, ordered_quantity, unit_price, currency, tax_rate, notes, purchase_request_item_id], ...]
     * @return int Oluşturulan purchase_orders.id
     */
    public function create(array $data, array $items): int
    {
        if (empty($items)) {
            throw new InvalidArgumentException('Satın alma siparişine en az bir malzeme kalemi eklenmelidir.');
        }

        // 1. Başlık validasyonu
        $headerErrors = $this->validateOrderData($data, false);
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

        // 3. Finansal hesaplamalar
        $defaultTaxRate = isset($data['tax_rate']) && is_numeric($data['tax_rate']) ? (float)$data['tax_rate'] : 20.00;
        $totals = $this->calculateTotals($items, $defaultTaxRate);

        $currency = strtoupper(trim((string)($data['currency'] ?? 'TRY')));
        if ($currency === 'TL') {
            $currency = 'TRY';
        }

        // 4. Atomik Transaction
        $this->pdo->beginTransaction();

        try {
            // Sipariş numarası üret
            $orderNo = !empty($data['order_no']) ? trim((string)$data['order_no']) : $this->generateOrderNo();

            // Çakışma kontrolü
            $checkStmt = $this->pdo->prepare("SELECT id FROM purchase_orders WHERE order_no = :no LIMIT 1");
            $checkStmt->execute([':no' => $orderNo]);
            if ($checkStmt->fetch()) {
                $orderNo = $this->generateOrderNo();
            }

            $stmtHeader = $this->pdo->prepare("
                INSERT INTO purchase_orders (
                    order_no,
                    purchase_request_id,
                    supplier_id,
                    ordered_by,
                    order_date,
                    expected_delivery_date,
                    payment_terms,
                    delivery_terms,
                    currency,
                    status,
                    subtotal,
                    tax_rate,
                    tax_amount,
                    total_amount,
                    notes,
                    created_at,
                    updated_at
                ) VALUES (
                    :order_no,
                    :purchase_request_id,
                    :supplier_id,
                    :ordered_by,
                    :order_date,
                    :expected_delivery_date,
                    :payment_terms,
                    :delivery_terms,
                    :currency,
                    'DRAFT',
                    :subtotal,
                    :tax_rate,
                    :tax_amount,
                    :total_amount,
                    :notes,
                    NOW(),
                    NOW()
                )
            ");

            $prId = !empty($data['purchase_request_id']) ? (int)$data['purchase_request_id'] : null;
            $deliveryDate = !empty($data['expected_delivery_date']) ? trim((string)$data['expected_delivery_date']) : null;
            $paymentTerms = !empty($data['payment_terms']) ? trim((string)$data['payment_terms']) : null;
            $deliveryTerms = !empty($data['delivery_terms']) ? trim((string)$data['delivery_terms']) : null;
            $notes = !empty($data['notes']) ? trim((string)$data['notes']) : null;

            $stmtHeader->execute([
                ':order_no'               => $orderNo,
                ':purchase_request_id'    => $prId,
                ':supplier_id'            => (int)$data['supplier_id'],
                ':ordered_by'             => (int)$data['ordered_by'],
                ':order_date'             => trim((string)$data['order_date']),
                ':expected_delivery_date' => $deliveryDate,
                ':payment_terms'          => $paymentTerms,
                ':delivery_terms'         => $deliveryTerms,
                ':currency'               => $currency,
                ':subtotal'               => $totals['subtotal'],
                ':tax_rate'               => $defaultTaxRate,
                ':tax_amount'             => $totals['tax_amount'],
                ':total_amount'           => $totals['total_amount'],
                ':notes'                  => $notes,
            ]);

            $orderId = (int)$this->pdo->lastInsertId();

            // Kalemleri ekle
            $stmtItem = $this->pdo->prepare("
                INSERT INTO purchase_order_items (
                    purchase_order_id,
                    purchase_request_item_id,
                    material_id,
                    ordered_quantity,
                    received_quantity,
                    unit_price,
                    currency,
                    tax_rate,
                    line_total,
                    notes,
                    created_at,
                    updated_at
                ) VALUES (
                    :purchase_order_id,
                    :purchase_request_item_id,
                    :material_id,
                    :ordered_quantity,
                    :received_quantity,
                    :unit_price,
                    :currency,
                    :tax_rate,
                    :line_total,
                    :notes,
                    NOW(),
                    NOW()
                )
            ");

            foreach ($items as $item) {
                $qty = (float)$item['ordered_quantity'];
                $recQty = isset($item['received_quantity']) && is_numeric($item['received_quantity']) ? (float)$item['received_quantity'] : 0.000;
                $price = (float)$item['unit_price'];
                $itemTax = isset($item['tax_rate']) && is_numeric($item['tax_rate']) ? (float)$item['tax_rate'] : $defaultTaxRate;
                $lineTotal = round($qty * $price, 4);
                $itemCurrency = strtoupper(trim((string)($item['currency'] ?? $currency)));
                if ($itemCurrency === 'TL') {
                    $itemCurrency = 'TRY';
                }
                $priId = !empty($item['purchase_request_item_id']) ? (int)$item['purchase_request_item_id'] : null;
                $itemNotes = !empty($item['notes']) ? trim((string)$item['notes']) : null;

                $stmtItem->execute([
                    ':purchase_order_id'         => $orderId,
                    ':purchase_request_item_id'  => $priId,
                    ':material_id'               => (int)$item['material_id'],
                    ':ordered_quantity'          => $qty,
                    ':received_quantity'         => $recQty,
                    ':unit_price'                => $price,
                    ':currency'                  => $itemCurrency,
                    ':tax_rate'                  => $itemTax,
                    ':line_total'                => $lineTotal,
                    ':notes'                     => $itemNotes,
                ]);
            }

            $this->pdo->commit();
            return $orderId;

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Satın alma siparişi oluşturulurken hata oluştu: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * APPROVED durumundaki bir Purchase Request'tan otomatik PO üretir.
     * PR ve kalemlerini doğrular, PO oluşturur ve PR durumunu atomik olarak 'ORDERED' yapar.
     * Duplicate dönüşümlere karşı korumalıdır.
     */
    public function createFromPurchaseRequest(int $purchaseRequestId, int $userId, array $options = []): int
    {
        if ($purchaseRequestId <= 0) {
            throw new InvalidArgumentException('Geçerli bir Satın Alma Talebi (PR ID) belirtilmelidir.');
        }

        if ($userId <= 0) {
            throw new InvalidArgumentException('Siparişi oluşturan kullanıcı belirtilmelidir.');
        }

        $this->pdo->beginTransaction();

        try {
            // 1. PR kaydını kilitleyerek çek
            $stmtPr = $this->pdo->prepare("
                SELECT * 
                FROM purchase_requests 
                WHERE id = :id 
                FOR UPDATE
            ");
            $stmtPr->execute([':id' => $purchaseRequestId]);
            $pr = $stmtPr->fetch();

            if (!$pr) {
                throw new RuntimeException("ID={$purchaseRequestId} olan satın alma talebi bulunamadı.");
            }

            // 2. PR Durum Kontrolü
            if ($pr['status'] !== 'APPROVED') {
                throw new RuntimeException("Yalnızca ONAYLANMIŞ (APPROVED) satın alma talepleri siparişe dönüştürülebilir. Mevcut talep durumu: '{$pr['status']}'");
            }

            // 3. Duplicate kontrolü: Bu PR'a bağlı aktif bir PO var mı?
            $stmtDup = $this->pdo->prepare("
                SELECT id, order_no, status 
                FROM purchase_orders 
                WHERE purchase_request_id = :pr_id 
                  AND status != 'CANCELLED' 
                LIMIT 1
            ");
            $stmtDup->execute([':pr_id' => $purchaseRequestId]);
            $existingPo = $stmtDup->fetch();
            if ($existingPo) {
                throw new RuntimeException("Bu satın alma talebi (PR) için zaten aktif bir sipariş ({$existingPo['order_no']}) bulunmaktadır.");
            }

            // 4. PR Kalemlerini çek
            $stmtItems = $this->pdo->prepare("
                SELECT pri.*, m.unit_price as mat_unit_price, m.currency as mat_currency
                FROM purchase_request_items pri
                LEFT JOIN materials m ON m.id = pri.material_id
                WHERE pri.purchase_request_id = :pr_id
                ORDER BY pri.id ASC
            ");
            $stmtItems->execute([':pr_id' => $purchaseRequestId]);
            $prItems = $stmtItems->fetchAll();

            if (empty($prItems)) {
                throw new RuntimeException('Satın alma talebinde siparişe aktarılacak malzeme kalemi bulunamadı.');
            }

            // 5. Tedarikçi Belirleme
            $supplierId = !empty($options['supplier_id']) ? (int)$options['supplier_id'] : 0;
            if ($supplierId <= 0) {
                $suggestedSuppliers = array_values(array_unique(array_filter(array_map(function($i) {
                    return !empty($i['suggested_supplier_id']) ? (int)$i['suggested_supplier_id'] : null;
                }, $prItems))));

                if (count($suggestedSuppliers) === 1) {
                    $supplierId = (int)$suggestedSuppliers[0];
                } elseif (count($suggestedSuppliers) > 1) {
                    throw new RuntimeException('Talebin kalemlerinde birden fazla farklı tedarikçi önerilmiştir. Lütfen sipariş için tek bir tedarikçi seçiniz.');
                } else {
                    throw new InvalidArgumentException('Talebe tanımlı önerilen tedarikçi bulunamadı. Lütfen sipariş için bir tedarikçi seçiniz.');
                }
            }

            // Tedarikçi aktif mi kontrolü
            $stmtSupp = $this->pdo->prepare("SELECT id, is_active FROM suppliers WHERE id = :id LIMIT 1");
            $stmtSupp->execute([':id' => $supplierId]);
            $supp = $stmtSupp->fetch();
            if (!$supp || (int)$supp['is_active'] !== 1) {
                throw new RuntimeException('Seçilen tedarikçi sistemde bulunamadı veya pasif durumda.');
            }

            // 6. PO Kalemlerini hazırla
            $orderCurrency = strtoupper(trim((string)($options['currency'] ?? '')));
            if ($orderCurrency === '' || $orderCurrency === 'TL') {
                $firstCur = strtoupper(trim((string)($prItems[0]['currency'] ?? 'TRY')));
                $orderCurrency = ($firstCur === 'TL' || $firstCur === '') ? 'TRY' : $firstCur;
            }

            $taxRate = isset($options['tax_rate']) && is_numeric($options['tax_rate']) ? (float)$options['tax_rate'] : 20.00;
            $orderDate = !empty($options['order_date']) ? trim((string)$options['order_date']) : date('Y-m-d');
            $deliveryDate = !empty($options['expected_delivery_date']) 
                ? trim((string)$options['expected_delivery_date']) 
                : (!empty($pr['required_date']) ? $pr['required_date'] : null);

            $poItems = [];
            foreach ($prItems as $pri) {
                $unitPrice = ($pri['estimated_unit_price'] !== null && (float)$pri['estimated_unit_price'] > 0)
                    ? (float)$pri['estimated_unit_price']
                    : (float)($pri['mat_unit_price'] ?? 0.0);

                $itemCurrency = strtoupper(trim((string)($pri['currency'] ?? $orderCurrency)));
                if ($itemCurrency === 'TL') {
                    $itemCurrency = 'TRY';
                }

                $poItems[] = [
                    'purchase_request_item_id' => (int)$pri['id'],
                    'material_id'              => (int)$pri['material_id'],
                    'ordered_quantity'         => (float)$pri['requested_quantity'],
                    'received_quantity'        => 0.000,
                    'unit_price'               => $unitPrice,
                    'currency'                 => $itemCurrency,
                    'tax_rate'                 => $taxRate,
                    'notes'                    => !empty($pri['notes']) ? $pri['notes'] : null,
                ];
            }

            // 7. PO Başlık verilerini hazırla
            $poData = [
                'purchase_request_id'    => $purchaseRequestId,
                'supplier_id'            => $supplierId,
                'ordered_by'             => $userId,
                'order_date'             => $orderDate,
                'expected_delivery_date' => $deliveryDate,
                'payment_terms'          => !empty($options['payment_terms']) ? trim((string)$options['payment_terms']) : null,
                'delivery_terms'         => !empty($options['delivery_terms']) ? trim((string)$options['delivery_terms']) : null,
                'currency'               => $orderCurrency,
                'tax_rate'               => $taxRate,
                'notes'                  => !empty($options['notes']) ? trim((string)$options['notes']) : "PR No: {$pr['request_no']} referansıyla oluşturuldu.",
            ];

            // 8. Hesaplamalar ve PO oluşturma
            $totals = $this->calculateTotals($poItems, $taxRate);
            $orderNo = $this->generateOrderNo();

            $stmtHeader = $this->pdo->prepare("
                INSERT INTO purchase_orders (
                    order_no,
                    purchase_request_id,
                    supplier_id,
                    ordered_by,
                    order_date,
                    expected_delivery_date,
                    payment_terms,
                    delivery_terms,
                    currency,
                    status,
                    subtotal,
                    tax_rate,
                    tax_amount,
                    total_amount,
                    notes,
                    created_at,
                    updated_at
                ) VALUES (
                    :order_no,
                    :purchase_request_id,
                    :supplier_id,
                    :ordered_by,
                    :order_date,
                    :expected_delivery_date,
                    :payment_terms,
                    :delivery_terms,
                    :currency,
                    'DRAFT',
                    :subtotal,
                    :tax_rate,
                    :tax_amount,
                    :total_amount,
                    :notes,
                    NOW(),
                    NOW()
                )
            ");

            $stmtHeader->execute([
                ':order_no'               => $orderNo,
                ':purchase_request_id'    => $purchaseRequestId,
                ':supplier_id'            => $supplierId,
                ':ordered_by'             => $userId,
                ':order_date'             => $orderDate,
                ':expected_delivery_date' => $deliveryDate,
                ':payment_terms'          => $poData['payment_terms'],
                ':delivery_terms'         => $poData['delivery_terms'],
                ':currency'               => $orderCurrency,
                ':subtotal'               => $totals['subtotal'],
                ':tax_rate'               => $taxRate,
                ':tax_amount'             => $totals['tax_amount'],
                ':total_amount'           => $totals['total_amount'],
                ':notes'                  => $poData['notes'],
            ]);

            $orderId = (int)$this->pdo->lastInsertId();

            // Kalemleri ekle
            $stmtItem = $this->pdo->prepare("
                INSERT INTO purchase_order_items (
                    purchase_order_id,
                    purchase_request_item_id,
                    material_id,
                    ordered_quantity,
                    received_quantity,
                    unit_price,
                    currency,
                    tax_rate,
                    line_total,
                    notes,
                    created_at,
                    updated_at
                ) VALUES (
                    :purchase_order_id,
                    :purchase_request_item_id,
                    :material_id,
                    :ordered_quantity,
                    :received_quantity,
                    :unit_price,
                    :currency,
                    :tax_rate,
                    :line_total,
                    :notes,
                    NOW(),
                    NOW()
                )
            ");

            foreach ($poItems as $item) {
                $qty = (float)$item['ordered_quantity'];
                $price = (float)$item['unit_price'];
                $itemTax = (float)$item['tax_rate'];
                $lineTotal = round($qty * $price, 4);

                $stmtItem->execute([
                    ':purchase_order_id'        => $orderId,
                    ':purchase_request_item_id' => $item['purchase_request_item_id'],
                    ':material_id'              => $item['material_id'],
                    ':ordered_quantity'         => $qty,
                    ':received_quantity'        => 0.000,
                    ':unit_price'               => $price,
                    ':currency'                 => $item['currency'],
                    ':tax_rate'                 => $itemTax,
                    ':line_total'               => $lineTotal,
                    ':notes'                    => $item['notes'],
                ]);
            }

            // 9. PR durumunu ORDERED olarak güncelle
            $stmtPrUpdate = $this->pdo->prepare("
                UPDATE purchase_requests SET
                    status     = 'ORDERED',
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmtPrUpdate->execute([':id' => $purchaseRequestId]);

            $this->pdo->commit();
            return $orderId;

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Satın alma talebinden sipariş oluşturulurken hata oluştu: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * ID ile tekil sipariş detayını ilişkileriyle birlikte getirir.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                po.*,
                s.name AS supplier_name,
                s.code AS supplier_code,
                s.phone AS supplier_phone,
                s.email AS supplier_email,
                s.contact_name AS supplier_contact,
                s.address AS supplier_address,
                u.first_name AS orderer_first_name,
                u.last_name AS orderer_last_name,
                u.username AS orderer_username,
                TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS orderer_name,
                pr.request_no AS purchase_request_no,
                pr.request_date AS purchase_request_date,
                (SELECT COUNT(*) FROM purchase_order_items poi WHERE poi.purchase_order_id = po.id) AS item_count,
                (SELECT COALESCE(SUM(poi.ordered_quantity), 0) FROM purchase_order_items poi WHERE poi.purchase_order_id = po.id) AS total_ordered_quantity,
                (SELECT COALESCE(SUM(poi.received_quantity), 0) FROM purchase_order_items poi WHERE poi.purchase_order_id = po.id) AS total_received_quantity
            FROM purchase_orders po
            LEFT JOIN suppliers s ON s.id = po.supplier_id
            LEFT JOIN users u ON u.id = po.ordered_by
            LEFT JOIN purchase_requests pr ON pr.id = po.purchase_request_id
            WHERE po.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Sipariş numarası ile tekil sipariş detayını getirir.
     */
    public function findByOrderNo(string $orderNo): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                po.*,
                s.name AS supplier_name,
                s.code AS supplier_code,
                s.phone AS supplier_phone,
                s.email AS supplier_email,
                s.contact_name AS supplier_contact,
                s.address AS supplier_address,
                TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS orderer_name,
                pr.request_no AS purchase_request_no
            FROM purchase_orders po
            LEFT JOIN suppliers s ON s.id = po.supplier_id
            LEFT JOIN users u ON u.id = po.ordered_by
            LEFT JOIN purchase_requests pr ON pr.id = po.purchase_request_id
            WHERE po.order_no = :order_no
            LIMIT 1
        ");

        $stmt->execute([':order_no' => trim($orderNo)]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Bir siparişe ait tüm malzeme kalemlerini ilişkileriyle birlikte getirir.
     */
    public function getItems(int $purchaseOrderId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                poi.*,
                m.code AS material_code,
                m.name AS material_name,
                m.category_id,
                un.id AS unit_id,
                un.name AS unit_name,
                un.symbol AS unit_symbol,
                pri.requested_quantity AS pr_requested_quantity,
                (poi.ordered_quantity - poi.received_quantity) AS remaining_quantity
            FROM purchase_order_items poi
            LEFT JOIN materials m ON m.id = poi.material_id
            LEFT JOIN units un ON un.id = m.unit_id
            LEFT JOIN purchase_request_items pri ON pri.id = poi.purchase_request_item_id
            WHERE poi.purchase_order_id = :purchase_order_id
            ORDER BY poi.id ASC
        ");

        $stmt->execute([':purchase_order_id' => $purchaseOrderId]);
        return $stmt->fetchAll();
    }

    /**
     * Filtreli ve sayfalamalı sipariş listesini getirir.
     */
    public function getAll(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $sql = "
            SELECT 
                po.*,
                s.name AS supplier_name,
                s.code AS supplier_code,
                TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS orderer_name,
                u.username AS orderer_username,
                pr.request_no AS purchase_request_no,
                (SELECT COUNT(*) FROM purchase_order_items poi WHERE poi.purchase_order_id = po.id) AS item_count,
                (SELECT COALESCE(SUM(poi.ordered_quantity), 0) FROM purchase_order_items poi WHERE poi.purchase_order_id = po.id) AS total_ordered_quantity,
                (SELECT COALESCE(SUM(poi.received_quantity), 0) FROM purchase_order_items poi WHERE poi.purchase_order_id = po.id) AS total_received_quantity
            FROM purchase_orders po
            LEFT JOIN suppliers s ON s.id = po.supplier_id
            LEFT JOIN users u ON u.id = po.ordered_by
            LEFT JOIN purchase_requests pr ON pr.id = po.purchase_request_id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND po.status = :status";
            $params[':status'] = trim((string)$filters['status']);
        }

        if (!empty($filters['supplier_id'])) {
            $sql .= " AND po.supplier_id = :supplier_id";
            $params[':supplier_id'] = (int)$filters['supplier_id'];
        }

        if (!empty($filters['ordered_by'])) {
            $sql .= " AND po.ordered_by = :ordered_by";
            $params[':ordered_by'] = (int)$filters['ordered_by'];
        }

        if (!empty($filters['currency'])) {
            $sql .= " AND po.currency = :currency";
            $params[':currency'] = trim((string)$filters['currency']);
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND po.order_date >= :date_from";
            $params[':date_from'] = trim((string)$filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND po.order_date <= :date_to";
            $params[':date_to'] = trim((string)$filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (po.order_no LIKE :search OR s.name LIKE :search OR po.notes LIKE :search)";
            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $sql .= " ORDER BY po.id DESC LIMIT :limit OFFSET :offset";

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
     * Filtrelere uyan toplam sipariş sayısını döner.
     */
    public function countAll(array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*) 
            FROM purchase_orders po 
            LEFT JOIN suppliers s ON s.id = po.supplier_id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND po.status = :status";
            $params[':status'] = trim((string)$filters['status']);
        }

        if (!empty($filters['supplier_id'])) {
            $sql .= " AND po.supplier_id = :supplier_id";
            $params[':supplier_id'] = (int)$filters['supplier_id'];
        }

        if (!empty($filters['ordered_by'])) {
            $sql .= " AND po.ordered_by = :ordered_by";
            $params[':ordered_by'] = (int)$filters['ordered_by'];
        }

        if (!empty($filters['currency'])) {
            $sql .= " AND po.currency = :currency";
            $params[':currency'] = trim((string)$filters['currency']);
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND po.order_date >= :date_from";
            $params[':date_from'] = trim((string)$filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND po.order_date <= :date_to";
            $params[':date_to'] = trim((string)$filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (po.order_no LIKE :search OR s.name LIKE :search OR po.notes LIKE :search)";
            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Satın alma siparişleri için özet KPI metriklerini döner.
     */
    public function getKpis(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN status = 'DRAFT' THEN 1 ELSE 0 END), 0) AS draft_count,
                COALESCE(SUM(CASE WHEN status = 'SENT' THEN 1 ELSE 0 END), 0) AS sent_count,
                COALESCE(SUM(CASE WHEN status = 'CONFIRMED' THEN 1 ELSE 0 END), 0) AS confirmed_count,
                COALESCE(SUM(CASE WHEN status = 'PARTIALLY_RECEIVED' THEN 1 ELSE 0 END), 0) AS partially_received_count,
                COALESCE(SUM(CASE WHEN status = 'RECEIVED' THEN 1 ELSE 0 END), 0) AS received_count,
                COALESCE(SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END), 0) AS cancelled_count,
                COALESCE(SUM(CASE WHEN status IN ('SENT', 'CONFIRMED', 'PARTIALLY_RECEIVED') THEN total_amount ELSE 0 END), 0) AS open_order_amount
            FROM purchase_orders
        ");
        $res = $stmt->fetch();

        return [
            'total'                  => (int)($res['total'] ?? 0),
            'draft'                  => (int)($res['draft_count'] ?? 0),
            'sent'                   => (int)($res['sent_count'] ?? 0),
            'confirmed'              => (int)($res['confirmed_count'] ?? 0),
            'partially_received'     => (int)($res['partially_received_count'] ?? 0),
            'received'               => (int)($res['received_count'] ?? 0),
            'cancelled'              => (int)($res['cancelled_count'] ?? 0),
            'open_order_amount'      => (float)($res['open_order_amount'] ?? 0.0),
        ];
    }

    /**
     * Yalnızca DRAFT durumundaki siparişi ve opsiyonel olarak kalemlerini günceller.
     */
    public function update(int $id, array $data, ?array $items = null): bool
    {
        // 1. Başlık validasyonu
        $headerErrors = $this->validateOrderData($data, true);
        if (!empty($headerErrors)) {
            throw new InvalidArgumentException(implode(' | ', $headerErrors));
        }

        // 2. Kalem validasyonu (varsa)
        if ($items !== null) {
            if (empty($items)) {
                throw new InvalidArgumentException('Siparişte en az bir malzeme kalemi bulunmalıdır.');
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
            $stmt = $this->pdo->prepare("SELECT * FROM purchase_orders WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $id]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new RuntimeException("ID={$id} olan satın alma siparişi bulunamadı.");
            }

            if ($order['status'] !== 'DRAFT') {
                throw new RuntimeException("Sadece TASLAK (DRAFT) durumundaki siparişler düzenlenebilir. Mevcut durum: {$order['status']}");
            }

            $defaultTaxRate = isset($data['tax_rate']) && is_numeric($data['tax_rate']) ? (float)$data['tax_rate'] : (float)$order['tax_rate'];
            $currency = !empty($data['currency']) ? strtoupper(trim((string)$data['currency'])) : $order['currency'];
            if ($currency === 'TL') {
                $currency = 'TRY';
            }

            // Kalemler güncelleniyorsa yeniden hesapla
            if ($items !== null) {
                $totals = $this->calculateTotals($items, $defaultTaxRate);

                // Eski kalemleri sil
                $delStmt = $this->pdo->prepare("DELETE FROM purchase_order_items WHERE purchase_order_id = :id");
                $delStmt->execute([':id' => $id]);

                // Yeni kalemleri ekle
                $stmtItem = $this->pdo->prepare("
                    INSERT INTO purchase_order_items (
                        purchase_order_id,
                        purchase_request_item_id,
                        material_id,
                        ordered_quantity,
                        received_quantity,
                        unit_price,
                        currency,
                        tax_rate,
                        line_total,
                        notes,
                        created_at,
                        updated_at
                    ) VALUES (
                        :purchase_order_id,
                        :purchase_request_item_id,
                        :material_id,
                        :ordered_quantity,
                        :received_quantity,
                        :unit_price,
                        :currency,
                        :tax_rate,
                        :line_total,
                        :notes,
                        NOW(),
                        NOW()
                    )
                ");

                foreach ($items as $item) {
                    $qty = (float)$item['ordered_quantity'];
                    $recQty = isset($item['received_quantity']) && is_numeric($item['received_quantity']) ? (float)$item['received_quantity'] : 0.000;
                    $price = (float)$item['unit_price'];
                    $itemTax = isset($item['tax_rate']) && is_numeric($item['tax_rate']) ? (float)$item['tax_rate'] : $defaultTaxRate;
                    $lineTotal = round($qty * $price, 4);
                    $itemCurrency = strtoupper(trim((string)($item['currency'] ?? $currency)));
                    if ($itemCurrency === 'TL') {
                        $itemCurrency = 'TRY';
                    }
                    $priId = !empty($item['purchase_request_item_id']) ? (int)$item['purchase_request_item_id'] : null;
                    $itemNotes = !empty($item['notes']) ? trim((string)$item['notes']) : null;

                    $stmtItem->execute([
                        ':purchase_order_id'        => $id,
                        ':purchase_request_item_id' => $priId,
                        ':material_id'              => (int)$item['material_id'],
                        ':ordered_quantity'         => $qty,
                        ':received_quantity'        => $recQty,
                        ':unit_price'               => $price,
                        ':currency'                 => $itemCurrency,
                        ':tax_rate'                 => $itemTax,
                        ':line_total'               => $lineTotal,
                        ':notes'                    => $itemNotes,
                    ]);
                }
            } else {
                $totals = [
                    'subtotal'     => (float)$order['subtotal'],
                    'tax_amount'   => (float)$order['tax_amount'],
                    'total_amount' => (float)$order['total_amount'],
                ];
            }

            // Başlık güncelle
            $updateSql = "
                UPDATE purchase_orders SET
                    supplier_id            = :supplier_id,
                    order_date             = :order_date,
                    expected_delivery_date = :expected_delivery_date,
                    payment_terms          = :payment_terms,
                    delivery_terms         = :delivery_terms,
                    currency               = :currency,
                    subtotal               = :subtotal,
                    tax_rate               = :tax_rate,
                    tax_amount             = :tax_amount,
                    total_amount           = :total_amount,
                    notes                  = :notes,
                    updated_at             = NOW()
                WHERE id = :id
            ";

            $stmtUpdate = $this->pdo->prepare($updateSql);
            $deliveryDate = !empty($data['expected_delivery_date']) ? trim((string)$data['expected_delivery_date']) : null;
            $paymentTerms = !empty($data['payment_terms']) ? trim((string)$data['payment_terms']) : null;
            $deliveryTerms = !empty($data['delivery_terms']) ? trim((string)$data['delivery_terms']) : null;
            $notes = !empty($data['notes']) ? trim((string)$data['notes']) : null;

            $stmtUpdate->execute([
                ':id'                     => $id,
                ':supplier_id'            => (int)($data['supplier_id'] ?? $order['supplier_id']),
                ':order_date'             => trim((string)($data['order_date'] ?? $order['order_date'])),
                ':expected_delivery_date' => $deliveryDate,
                ':payment_terms'          => $paymentTerms,
                ':delivery_terms'         => $deliveryTerms,
                ':currency'               => $currency,
                ':subtotal'               => $totals['subtotal'],
                ':tax_rate'               => $defaultTaxRate,
                ':tax_amount'             => $totals['tax_amount'],
                ':total_amount'           => $totals['total_amount'],
                ':notes'                  => $notes,
            ]);

            $this->pdo->commit();
            return true;

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Satın alma siparişi güncellenirken hata oluştu: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Siparişi tedarikçiye gönderir: DRAFT -> SENT
     */
    public function send(int $id): bool
    {
        return $this->transitionStatus($id, 'SENT', function (array $order) use ($id) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM purchase_order_items WHERE purchase_order_id = :id");
            $stmt->execute([':id' => $id]);
            $count = (int)$stmt->fetchColumn();

            if ($count <= 0) {
                throw new RuntimeException('Kalemi olmayan sipariş tedarikçiye gönderilemez.');
            }
        });
    }

    /**
     * Tedarikçinin siparişi teyit ettiğini işaretler: SENT -> CONFIRMED
     */
    public function confirm(int $id): bool
    {
        return $this->transitionStatus($id, 'CONFIRMED');
    }

    /**
     * Siparişi iptal eder: DRAFT/SENT/CONFIRMED -> CANCELLED
     */
    public function cancel(int $id, ?string $reason = null): bool
    {
        return $this->transitionStatus($id, 'CANCELLED', function (array $order) use ($reason, $id) {
            // Mal kabulü yapılmış mı kontrol et
            $stmt = $this->pdo->prepare("SELECT SUM(received_quantity) FROM purchase_order_items WHERE purchase_order_id = :id");
            $stmt->execute([':id' => $id]);
            $receivedSum = (float)$stmt->fetchColumn();

            if ($receivedSum > 0) {
                throw new RuntimeException('Kısmen veya tamamen teslim alınmış bir sipariş doğrudan iptal edilemez.');
            }

            if ($reason !== null && trim($reason) !== '') {
                $stmtNotes = $this->pdo->prepare("
                    UPDATE purchase_orders SET
                        notes      = CONCAT(COALESCE(notes, ''), '\n[İptal Gerekçesi]: ', :reason),
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmtNotes->execute([':id' => $id, ':reason' => trim($reason)]);
            }
        });
    }

    /**
     * Güvenli durum geçişi yönetimi (State Machine Enforcer).
     */
    private function transitionStatus(
        int $id,
        string $targetStatus,
        ?callable $customExecution = null,
        bool $customHandlesStatusUpdate = false
    ): bool {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("SELECT * FROM purchase_orders WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $id]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new RuntimeException("ID={$id} olan satın alma siparişi bulunamadı.");
            }

            $currentStatus = $order['status'];

            if (!self::canTransition($currentStatus, $targetStatus)) {
                throw new RuntimeException("Geçersiz durum geçişi: '{$currentStatus}' durumundan '{$targetStatus}' durumuna geçilemez.");
            }

            if ($customExecution !== null) {
                $customExecution($order);
            }

            if (!$customHandlesStatusUpdate) {
                $updateStmt = $this->pdo->prepare("
                    UPDATE purchase_orders SET
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
            'DRAFT'              => 'Taslak',
            'SENT'               => 'Tedarikçiye Gönderildi',
            'CONFIRMED'          => 'Teyit Edildi / Onaylandı',
            'PARTIALLY_RECEIVED' => 'Kısmi Teslim Alındı',
            'RECEIVED'           => 'Tamamlandı / Teslim Alındı',
            'CANCELLED'          => 'İptal Edildi',
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * Durum için CSS Badge sınıfı döner.
     */
    public static function statusBadgeClass(string $status): string
    {
        $classes = [
            'DRAFT'              => 'badge-secondary',
            'SENT'               => 'badge-info',
            'CONFIRMED'          => 'badge-primary',
            'PARTIALLY_RECEIVED' => 'badge-warning',
            'RECEIVED'           => 'badge-success',
            'CANCELLED'          => 'badge-dark',
        ];

        return $classes[$status] ?? 'badge-secondary';
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
}

