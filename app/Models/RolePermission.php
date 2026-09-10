<?php

class RolePermission
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Tüm sistem rollerini döner.
     */
    public function getRoles(): array
    {
        $stmt = $this->pdo->query("SELECT id, name, description FROM roles ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Belirtilen rolün bilgilerini döner.
     */
    public function getRoleById(int $roleId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, name, description FROM roles WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $roleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Tüm teknik izin kodlarının sade ve doğal Türkçe karşılıklarını döner.
     *
     * @return array<string, string>
     */
    public static function getPermissionLabels(): array
    {
        return [
            'dashboard.view'         => 'Dashboard görüntüleme',
            'material.view'          => 'Malzemeleri görüntüleme',
            'material.create'        => 'Yeni malzeme oluşturma',
            'material.update'        => 'Malzeme bilgilerini düzenleme',
            'material.delete'        => 'Malzeme silme',
            'stock.view'             => 'Stokları görüntüleme',
            'stock.in'               => 'Stok girişi yapma',
            'stock.out'              => 'Stok çıkışı yapma',
            'stock.transfer'         => 'Stok transferi yapma',
            'stock.return'           => 'Stok iadesi yapma',
            'stock.adjustment'       => 'Stok düzeltmesi yapma',
            'stock.scrap'            => 'Fire / hurda kaydı oluşturma',
            'warehouse.view'         => 'Depoları görüntüleme',
            'warehouse.manage'       => 'Depo ve lokasyonları yönetme',
            'supplier.view'          => 'Tedarikçileri görüntüleme',
            'supplier.manage'        => 'Tedarikçileri yönetme',
            'report.view'            => 'Raporları görüntüleme',
            'report.export'          => 'Raporları dışa aktarma',
            'user.view'              => 'Kullanıcıları görüntüleme',
            'user.manage'            => 'Kullanıcıları yönetme',
            'audit.view'             => 'Denetim kayıtlarını görüntüleme',
            'role.view'              => 'Rolleri ve yetkileri görüntüleme',
            'role.manage'            => 'Rol yetkilerini yönetme',
            'api.access'             => 'API erişimi',
            'api.manage'             => 'API anahtarlarını yönetme',
            'production.view'        => 'Üretimi görüntüleme',
            'production.create'      => 'Yeni üretim / iş emri oluşturma',
            'production.execute'     => 'Üretimi yürütme',
            'quality.view'           => 'Kalite kayıtlarını görüntüleme',
            'quality.inspect'        => 'Kalite kontrolü yapma',
            'shipment.view'          => 'Sevkiyatları görüntüleme',
            'shipment.create'        => 'Sevkiyat oluşturma',
            'shipment.complete'      => 'Sevkiyatı tamamlama',
            'energy.view'            => 'Enerji verilerini görüntüleme',
            'energy.manage'          => 'Enerji yönetimi',
            'recipe.manage'          => 'Üretim reçetelerini yönetme',
            'mes.create'             => 'MES iş emirlerini yönetme',
            'mes.simulate'           => 'MES simülasyonunu çalıştırma',
            'oee.view'               => 'OEE ve hat performansını görüntüleme',
            'maintenance.view'       => 'Bakım kayıtlarını görüntüleme',
            'maintenance.create'     => 'Bakım talebi oluşturma',
            'maintenance.execute'    => 'Bakım işlemlerini yürütme',
            'inventory.view'         => 'Envanteri görüntüleme',
            'inventory.create'       => 'Yeni varlık oluşturma',
            'inventory.update'       => 'Varlık bilgilerini ve zimmetleri yönetme',
            'inventory.export'       => 'Envanteri Excel\'e aktarma',
            'employee.view'          => 'Çalışanları görüntüleme',
            'employee.manage'        => 'Çalışanları yönetme',
            'purchase.view'          => 'Satın alma taleplerini görüntüleme',
            'purchase.request'       => 'Satın alma talebi oluşturma ve düzenleme',
            'purchase.approve'       => 'Satın alma talebini onaylama / reddetme',
            'purchase.order.view'    => 'Satın alma siparişlerini görüntüleme',
            'purchase.order.create'  => 'Satın alma siparişi oluşturma',
            'purchase.order.update'  => 'Satın alma siparişini düzenleme',
            'purchase.order.send'    => 'Siparişi gönderme / teyit etme',
            'purchase.order.cancel'  => 'Satın alma siparişini iptal etme',
        ];
    }

    /**
     * Sistemdeki tüm izinleri mantıksal modül/kategori gruplarına ayırarak döner.
     */
    public function getPermissionsGrouped(): array
    {
        $stmt = $this->pdo->query("SELECT id, name, description FROM permissions ORDER BY id ASC");
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $groups = [
            'Dashboard' => [
                'icon' => '📊',
                'description' => 'Genel özet ve gösterge panelleri erişimi',
                'permissions' => []
            ],
            'Malzeme & Stok İşlemleri' => [
                'icon' => '📦',
                'description' => 'Malzeme kartları, stok giriş/çıkış/transfer ve sevkiyat yönetimi',
                'permissions' => []
            ],
            'Depo & Lokasyon Yönetimi' => [
                'icon' => '🏢',
                'description' => 'Depo alanları, raflar ve lokasyon tanımları',
                'permissions' => []
            ],
            'Üretim & MES & Kalite' => [
                'icon' => '🏭',
                'description' => 'Üretim emirleri, reçeteler (BOM), MES simülasyonu, OEE ve kalite kontrol',
                'permissions' => []
            ],
            'Satın Alma & Tedarik' => [
                'icon' => '🛒',
                'description' => 'Satın alma talepleri (PR) ve satın alma siparişleri (PO) yönetimi',
                'permissions' => []
            ],
            'Envanter Takibi' => [
                'icon' => '🧰',
                'description' => 'Varlık ve ekipman kartları, zimmet takibi ve Excel aktarımı',
                'permissions' => []
            ],
            'Çalışan Yönetimi' => [
                'icon' => '👥',
                'description' => 'Personel listesi, vardiya planlaması ve çalışan işlemleri',
                'permissions' => []
            ],
            'Tedarikçi Yönetimi' => [
                'icon' => '🤝',
                'description' => 'Tedarikçi firma ve iletişim kayıtları yönetimi',
                'permissions' => []
            ],
            'Bakım & TPM' => [
                'icon' => '🔧',
                'description' => 'Ekipman koruyucu bakım, arıza bildirimi ve iş emri tamamlama',
                'permissions' => []
            ],
            'Enerji İzleme & Yönetim' => [
                'icon' => '⚡',
                'description' => 'Tüketim analizleri, GES performansı ve enerji alarmları',
                'permissions' => []
            ],
            'Raporlama' => [
                'icon' => '📈',
                'description' => 'Yönetici göstergeleri ve analitik raporlar',
                'permissions' => []
            ],
            'Kullanıcı, Sistem & Denetim' => [
                'icon' => '🔐',
                'description' => 'Kullanıcı hesapları, roller, denetim izleri (Audit) ve API anahtarları',
                'permissions' => []
            ],
        ];

        foreach ($all as $p) {
            $name = $p['name'];
            if (str_starts_with($name, 'dashboard.')) {
                $groups['Dashboard']['permissions'][] = $p;
            } elseif (str_starts_with($name, 'material.') || str_starts_with($name, 'stock.') || str_starts_with($name, 'shipment.')) {
                $groups['Malzeme & Stok İşlemleri']['permissions'][] = $p;
            } elseif (str_starts_with($name, 'warehouse.')) {
                $groups['Depo & Lokasyon Yönetimi']['permissions'][] = $p;
            } elseif (str_starts_with($name, 'production.') || str_starts_with($name, 'recipe.') || str_starts_with($name, 'mes.') || str_starts_with($name, 'oee.') || str_starts_with($name, 'quality.')) {
                $groups['Üretim & MES & Kalite']['permissions'][] = $p;
            } elseif (str_starts_with($name, 'purchase.')) {
                $groups['Satın Alma & Tedarik']['permissions'][] = $p;
            } elseif (str_starts_with($name, 'inventory.')) {
                $groups['Envanter Takibi']['permissions'][] = $p;
            } elseif (str_starts_with($name, 'employee.')) {
                $groups['Çalışan Yönetimi']['permissions'][] = $p;
            } elseif (str_starts_with($name, 'supplier.')) {
                $groups['Tedarikçi Yönetimi']['permissions'][] = $p;
            } elseif (str_starts_with($name, 'maintenance.')) {
                $groups['Bakım & TPM']['permissions'][] = $p;
            } elseif (str_starts_with($name, 'energy.')) {
                $groups['Enerji İzleme & Yönetim']['permissions'][] = $p;
            } elseif (str_starts_with($name, 'report.')) {
                $groups['Raporlama']['permissions'][] = $p;
            } else {
                $groups['Kullanıcı, Sistem & Denetim']['permissions'][] = $p;
            }
        }

        return $groups;
    }

    /**
     * Belirtilen role atanmış izin ID'lerinin dizisini döner.
     */
    public function getRolePermissionIds(int $roleId): array
    {
        $stmt = $this->pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = :role_id");
        $stmt->execute([':role_id' => $roleId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Rolün izinlerini atomik olarak günceller ve güvenlik kısıtlarını doğrular.
     * 
     * @param int $roleId Güncellenecek rol ID
     * @param array $permissionIds Seçilen izin ID dizisi
     * @param int $adminUserId İşlemi gerçekleştiren admin kullanıcı ID
     * @throws InvalidArgumentException Güvenlik veya doğrulama ihlali durumunda
     */
    public function updateRolePermissions(int $roleId, array $permissionIds, int $adminUserId): void
    {
        // 1. Rol varlık kontrolü
        $role = $this->getRoleById($roleId);
        if ($role === null) {
            throw new InvalidArgumentException('Geçersiz rol seçildi.');
        }

        // 2. Sistemde en az 1 aktif Admin kaldığını doğrula
        $activeAdminCount = (int)$this->pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 1 AND is_active = 1")->fetchColumn();
        if ($activeAdminCount < 1) {
            throw new InvalidArgumentException('Sistemde en az 1 aktif Admin kullanıcı bulunmalıdır.');
        }

        // 3. Sistemdeki tüm geçerli izinleri çek
        $allPermStmt = $this->pdo->query("SELECT id, name FROM permissions");
        $validPerms = [];
        $permNameToId = [];
        while ($row = $allPermStmt->fetch(PDO::FETCH_ASSOC)) {
            $validPerms[(int)$row['id']] = $row['name'];
            $permNameToId[$row['name']] = (int)$row['id'];
        }

        // 4. Gelen ID'leri sanitize et ve duplicate'leri temizle
        $cleanedIds = [];
        foreach ($permissionIds as $rawId) {
            $cleanId = (int)$rawId;
            if ($cleanId > 0) {
                if (!isset($validPerms[$cleanId])) {
                    throw new InvalidArgumentException("Geçersiz izin ID'si tespit edildi: " . $cleanId);
                }
                $cleanedIds[] = $cleanId;
            }
        }
        $cleanedIds = array_values(array_unique($cleanedIds));

        // 5. Admin Rolü (ID: 1) Kilitleme Koruması (Self-Lockout Prevention)
        // Admin rolünden dashboard.view, user.view, user.manage izinleri ASLA kaldırılamaz!
        if ($roleId === 1) {
            $requiredCodes = ['dashboard.view', 'user.view', 'user.manage'];
            $missingCodes = [];
            foreach ($requiredCodes as $reqCode) {
                $reqId = $permNameToId[$reqCode] ?? null;
                if ($reqId === null || !in_array($reqId, $cleanedIds, true)) {
                    $missingCodes[] = $reqCode;
                }
            }

            if (!empty($missingCodes)) {
                throw new InvalidArgumentException('Admin rolünden temel yönetim izinleri (' . implode(', ', $missingCodes) . ') kaldırılamaz. Sistem güvenliği için bu izinler zorunludur.');
            }
        }

        // 6. Mevcut kayıtları yedekle (MyISAM fallback güvencesi için)
        $backupPermIds = $this->getRolePermissionIds($roleId);

        // 7. Transaction ve Veritabanı Güncellemesi
        $inTransaction = false;
        try {
            $this->pdo->beginTransaction();
            $inTransaction = true;

            // Önce mevcut izinleri temizle
            $stmtDel = $this->pdo->prepare("DELETE FROM role_permissions WHERE role_id = :role_id");
            $stmtDel->execute([':role_id' => $roleId]);

            // Yeni izinleri ekle
            if (!empty($cleanedIds)) {
                $stmtIns = $this->pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)");
                foreach ($cleanedIds as $pId) {
                    $stmtIns->execute([':role_id' => $roleId, ':permission_id' => $pId]);
                }
            }

            $this->pdo->commit();
            $inTransaction = false;

        } catch (Throwable $e) {
            if ($inTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // MyISAM fallback: eğer rollback tablosu desteklemediyse eski kayıtları geri yükle
            try {
                $currentCount = (int)$this->pdo->query("SELECT COUNT(*) FROM role_permissions WHERE role_id = " . (int)$roleId)->fetchColumn();
                if ($currentCount === 0 && !empty($backupPermIds)) {
                    $stmtRestore = $this->pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)");
                    foreach ($backupPermIds as $oldId) {
                        $stmtRestore->execute([':role_id' => $roleId, ':permission_id' => $oldId]);
                    }
                }
            } catch (Throwable) {
                // Ignore fallback error and throw main exception
            }

            throw new RuntimeException('Yetkiler güncellenirken veritabanı hatası oluştu: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Tüm 7 rol için 56 izin üzerinden doğrulanmış merkezi varsayılan rol-yetki matrisini döner.
     *
     * @return array<int, array<string>>
     */
    public static function getDefaultPermissionsByRole(): array
    {
        return [
            1 => [ // Admin (56 izin - Tüm sistem yetkileri)
                'dashboard.view', 'material.view', 'material.create', 'material.update', 'material.delete',
                'stock.view', 'stock.in', 'stock.out', 'stock.transfer', 'stock.return', 'stock.adjustment', 'stock.scrap',
                'warehouse.view', 'warehouse.manage', 'supplier.view', 'supplier.manage',
                'report.view', 'report.export', 'user.view', 'user.manage', 'audit.view',
                'production.view', 'production.create', 'production.execute',
                'quality.view', 'quality.inspect',
                'shipment.view', 'shipment.create', 'shipment.complete',
                'energy.view', 'energy.manage',
                'role.view', 'role.manage',
                'api.access', 'api.manage',
                'maintenance.view', 'maintenance.create', 'maintenance.execute',
                'recipe.manage', 'mes.create', 'mes.simulate', 'oee.view',
                'inventory.view', 'inventory.create', 'inventory.update', 'inventory.export',
                'employee.view', 'employee.manage',
                'purchase.view', 'purchase.request', 'purchase.approve',
                'purchase.order.view', 'purchase.order.create', 'purchase.order.update', 'purchase.order.send', 'purchase.order.cancel'
            ],
            2 => [ // Depo Personeli (16 izin)
                'dashboard.view', 'material.view',
                'stock.view', 'stock.in', 'stock.out', 'stock.transfer', 'stock.return', 'stock.adjustment', 'stock.scrap',
                'warehouse.view',
                'shipment.view', 'shipment.create', 'shipment.complete',
                'inventory.view',
                'purchase.view',
                'purchase.order.view'
            ],
            3 => [ // Yönetici (28 izin)
                'dashboard.view',
                'material.view',
                'stock.view',
                'warehouse.view',
                'supplier.view', 'supplier.manage',
                'report.view', 'report.export',
                'user.view',
                'audit.view',
                'production.view',
                'quality.view',
                'shipment.view',
                'energy.view',
                'oee.view',
                'maintenance.view',
                'inventory.view', 'inventory.export',
                'employee.view', 'employee.manage',
                'purchase.view', 'purchase.request', 'purchase.approve',
                'purchase.order.view', 'purchase.order.create', 'purchase.order.update', 'purchase.order.send', 'purchase.order.cancel'
            ],
            4 => [ // Operatör (6 izin)
                'dashboard.view',
                'production.view',
                'maintenance.create',
                'oee.view',
                'purchase.view',
                'purchase.order.view'
            ],
            5 => [ // Üretim Personeli (8 izin)
                'dashboard.view',
                'material.view',
                'stock.view',
                'production.view', 'production.create', 'production.execute',
                'recipe.manage',
                'mes.create'
            ],
            6 => [ // Kalite Personeli (6 izin)
                'dashboard.view',
                'material.view',
                'stock.view',
                'quality.view', 'quality.inspect',
                'production.view'
            ],
            7 => [ // Bakım Personeli (9 izin)
                'dashboard.view',
                'material.view',
                'stock.view',
                'warehouse.view',
                'maintenance.view', 'maintenance.create', 'maintenance.execute',
                'energy.view',
                'oee.view'
            ]
        ];
    }

    /**
     * Projenin varsayılan rol-yetki matrisini döner (Geriye uyumluluk takma adı).
     */
    public static function getDefaultMatrix(): array
    {
        return self::getDefaultPermissionsByRole();
    }

    /**
     * Belirtilen TEK bir rolün izinlerini sistem varsayılanlarına güvenli şekilde döndürür.
     * Sadece seçili role ait kayıtları günceller, diğer 6 role kesinlikle dokunmaz.
     * 
     * @param int $roleId Sıfırlanacak rol ID
     * @param int $adminUserId İşlemi tetikleyen admin kullanıcı ID
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function resetRolePermissions(int $roleId, int $adminUserId): void
    {
        // 1. Rolün varlığını doğrula
        $role = $this->getRoleById($roleId);
        if ($role === null) {
            throw new InvalidArgumentException("Geçersiz rol seçildi (Rol ID: {$roleId}).");
        }

        // 2. Sistemde en az 1 aktif Admin kaldığını doğrula
        $activeAdminCount = (int)$this->pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 1 AND is_active = 1")->fetchColumn();
        if ($activeAdminCount < 1) {
            throw new InvalidArgumentException('Sistemde en az 1 aktif Admin kullanıcı bulunmalıdır.');
        }

        // 3. İlgili rolün varsayılan izin listesini al
        $matrix = self::getDefaultPermissionsByRole();
        if (!isset($matrix[$roleId])) {
            throw new InvalidArgumentException("Bu rol için tanımlı varsayılan izin matrisi bulunamadı (Rol: {$role['name']}).");
        }
        $defaultPermNames = $matrix[$roleId];

        // 4. Sistemdeki geçerli izinleri haritalandır
        $allPermStmt = $this->pdo->query("SELECT id, name FROM permissions");
        $permNameToId = [];
        while ($row = $allPermStmt->fetch(PDO::FETCH_ASSOC)) {
            $permNameToId[$row['name']] = (int)$row['id'];
        }

        // 5. Seçili rolün mevcut izinlerini yedekle (MyISAM fallback için)
        $backupPermStmt = $this->pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = :role_id");
        $backupPermStmt->execute([':role_id' => $roleId]);
        $backupPermIds = array_map('intval', $backupPermStmt->fetchAll(PDO::FETCH_COLUMN));

        // 6. Transaction ile SADECE bu rolün kayıtlarını güncelle
        $inTransaction = false;
        try {
            $this->pdo->beginTransaction();
            $inTransaction = true;

            // YALNIZCA seçili role ait izinleri sil
            $deleteStmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE role_id = :role_id");
            $deleteStmt->execute([':role_id' => $roleId]);

            // Varsayılan izinleri ekle
            $insertStmt = $this->pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)");
            foreach ($defaultPermNames as $pName) {
                $pId = $permNameToId[$pName] ?? null;
                if ($pId !== null) {
                    $insertStmt->execute([
                        ':role_id'       => $roleId,
                        ':permission_id' => $pId
                    ]);
                }
            }

            $this->pdo->commit();
            $inTransaction = false;

        } catch (Throwable $e) {
            if ($inTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // MyISAM fallback: Eğer tablo boş kaldıysa eski kayıtları geri yükle
            try {
                $checkCount = (int)$this->pdo->query("SELECT COUNT(*) FROM role_permissions WHERE role_id = " . (int)$roleId)->fetchColumn();
                if ($checkCount === 0 && !empty($backupPermIds)) {
                    $stmtRestore = $this->pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)");
                    foreach ($backupPermIds as $oldId) {
                        $stmtRestore->execute([':role_id' => $roleId, ':permission_id' => $oldId]);
                    }
                }
            } catch (Throwable) {
                // Ignore fallback error
            }

            throw new RuntimeException("Varsayılan yetkiler yüklenirken veritabanı hatası oluştu: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Rollerin izinlerini projenin varsayılan matrisine geri döndürür.
     * Eğer $roleId verilmişse sadece o rolü, verilmemişse tüm rolleri günceller.
     * 
     * @param int $adminUserId İşlemi tetikleyen admin kullanıcı ID
     * @param int|null $roleId Sıfırlanacak rol ID (null ise tüm roller)
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function resetToDefaultPermissions(int $adminUserId, ?int $roleId = null): void
    {
        if ($roleId !== null) {
            $this->resetRolePermissions($roleId, $adminUserId);
            return;
        }

        // 1. Sistemde en az 1 aktif Admin kaldığını doğrula
        $activeAdminCount = (int)$this->pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 1 AND is_active = 1")->fetchColumn();
        if ($activeAdminCount < 1) {
            throw new InvalidArgumentException('Sistemde en az 1 aktif Admin kullanıcı bulunmalıdır.');
        }

        // 2. Sistemdeki tüm geçerli izinleri ve rolleri haritalandır
        $allPermStmt = $this->pdo->query("SELECT id, name FROM permissions");
        $permNameToId = [];
        while ($row = $allPermStmt->fetch(PDO::FETCH_ASSOC)) {
            $permNameToId[$row['name']] = (int)$row['id'];
        }

        $allRolesStmt = $this->pdo->query("SELECT id FROM roles");
        $validRoleIds = array_map('intval', $allRolesStmt->fetchAll(PDO::FETCH_COLUMN));

        $defaultMatrix = self::getDefaultPermissionsByRole();

        // 3. Mevcut tüm kayıtları yedekle (MyISAM fallback güvencesi)
        $allCurrentRows = $this->pdo->query("SELECT role_id, permission_id FROM role_permissions")->fetchAll(PDO::FETCH_ASSOC);

        // 4. Transaction ile tüm role_permissions tablosunu varsayılana eşle
        $inTransaction = false;
        try {
            $this->pdo->beginTransaction();
            $inTransaction = true;

            // Tüm mevcut izin eşleşmelerini temizle
            $this->pdo->exec("DELETE FROM role_permissions");

            $stmtInsert = $this->pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)");

            foreach ($defaultMatrix as $rId => $permNames) {
                if (!in_array($rId, $validRoleIds, true)) {
                    continue;
                }

                foreach ($permNames as $pName) {
                    $pId = $permNameToId[$pName] ?? null;
                    if ($pId !== null) {
                        $stmtInsert->execute([
                            ':role_id'       => $rId,
                            ':permission_id' => $pId
                        ]);
                    }
                }
            }

            $this->pdo->commit();
            $inTransaction = false;

        } catch (Throwable $e) {
            if ($inTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // MyISAM fallback: Eğer tablo boş kaldıysa eski kayıtları geri yükle
            try {
                $count = (int)$this->pdo->query("SELECT COUNT(*) FROM role_permissions")->fetchColumn();
                if ($count === 0 && !empty($allCurrentRows)) {
                    $stmtRestore = $this->pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)");
                    foreach ($allCurrentRows as $row) {
                        $stmtRestore->execute([
                            ':role_id'       => $row['role_id'],
                            ':permission_id' => $row['permission_id']
                        ]);
                    }
                }
            } catch (Throwable) {
                // Ignore fallback error
            }

            throw new RuntimeException('Varsayılan yetkiler yüklenirken veritabanı hatası oluştu: ' . $e->getMessage(), 0, $e);
        }
    }
}
