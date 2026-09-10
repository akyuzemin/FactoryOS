<?php

require_once __DIR__ . '/../../app/Services/PermissionService.php';

$permissionService = new PermissionService($GLOBALS['pdo'] ?? (new Database())->connect());
$roleId = (int) ($_SESSION['role_id'] ?? 0);
$userPermissions = $permissionService->getPermissionsForRole($roleId);

/**
 * Kullanıcının belirtilen yetkiye veya yetkilerden en az birine sahip olup olmadığını kontrol eder.
 *
 * @param string|array|null $permission
 * @return bool
 */
$hasPermission = function ($permission) use ($userPermissions, $roleId): bool {
    if ($roleId < 1) {
        return false;
    }
    if ($permission === null || $permission === '' || $permission === []) {
        return true;
    }
    if (is_array($permission)) {
        foreach ($permission as $p) {
            if (in_array($p, $userPermissions, true)) {
                return true;
            }
        }
        return false;
    }
    return in_array($permission, $userPermissions, true);
};

// Global view scope için $can helper alias'ı
$can = $hasPermission;

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$pTitle = $pageTitle ?? '';
$actPage = $activePage ?? '';

/**
 * Menü elemanının o anki route/sayfada aktif olup olmadığını tespit eder.
 */
$isItemActive = function(array $item) use ($requestUri, $pTitle, $actPage): bool {
    if (isset($item['active_custom']) && is_callable($item['active_custom'])) {
        return (bool) $item['active_custom']($requestUri, $pTitle, $actPage);
    }
    if (!empty($item['exclude_route']) && str_contains($requestUri, $item['exclude_route'])) {
        return false;
    }
    if (!empty($item['titles'])) {
        if ($actPage !== '' && in_array($actPage, $item['titles'], true)) {
            return true;
        }
        if ($pTitle !== '' && in_array($pTitle, $item['titles'], true)) {
            return true;
        }
    }
    if (isset($item['extra_active']) && is_callable($item['extra_active']) && $item['extra_active']($requestUri)) {
        return true;
    }
    $route = $item['route'] ?? '';
    if ($route !== '' && $route !== '/') {
        if (str_contains($requestUri, $route)) {
            return true;
        }
    }
    if ($route === '/' && ($requestUri === '/stok-takip/public/' || $requestUri === '/stok-takip/public/dashboard' || $requestUri === '/stok-takip/public')) {
        return true;
    }
    return false;
};

/**
 * Menü grubunun o an açık/aktif olup olmadığını tespit eder.
 */
$isGroupActive = function(array $group, array $visibleChildren) use ($requestUri, $pTitle, $actPage, $isItemActive): bool {
    foreach ($visibleChildren as $child) {
        if ($isItemActive($child)) {
            return true;
        }
    }
    if (isset($group['active_custom']) && is_callable($group['active_custom']) && $group['active_custom']($requestUri, $pTitle, $actPage)) {
        return true;
    }
    if (!empty($group['active_patterns'])) {
        foreach ($group['active_patterns'] as $pattern) {
            if (str_contains($requestUri, $pattern)) {
                return true;
            }
        }
    }
    if (!empty($group['active_titles'])) {
        if ($actPage !== '' && in_array($actPage, $group['active_titles'], true)) {
            return true;
        }
        if ($pTitle !== '' && in_array($pTitle, $group['active_titles'], true)) {
            return true;
        }
        foreach ($group['active_titles'] as $title) {
            if (str_contains($pTitle, $title)) {
                return true;
            }
        }
    }
    return false;
};

// =========================================================================
// MERKEZİ SIDEBAR MENÜ YAPILANDIRMASI
// =========================================================================
$menu = [
    // 1. DASHBOARD
    [
        'type'          => 'link',
        'label'         => 'Dashboard',
        'icon'          => '🏠',
        'url'           => '/stok-takip/public/',
        'route'         => '/',
        'titles'        => ['Dashboard', 'Stok İadeleri Dashboard'],
        'permission'    => 'dashboard.view',
        'active_custom' => function(string $requestUri, string $pTitle, string $actPage): bool {
            return ($actPage === 'Dashboard' || $pTitle === 'Dashboard' || $actPage === 'Stok İadeleri Dashboard' || $pTitle === 'Stok İadeleri Dashboard' || $requestUri === '/stok-takip/public/' || $requestUri === '/stok-takip/public/dashboard' || $requestUri === '/stok-takip/public')
                && !str_contains($requestUri, '/energy')
                && !str_contains($requestUri, '/admin');
        }
    ],

    // 1.5. YÖNETİCİ DASHBOARD & RAPORLAR
    [
        'type'       => 'link',
        'label'      => 'Yönetici Dashboard',
        'icon'       => '📊',
        'url'        => '/stok-takip/public/admin/dashboard',
        'route'      => '/admin/dashboard',
        'titles'     => ['Yönetici Dashboard'],
        'permission' => 'report.view',
    ],
    [
        'type'       => 'link',
        'label'      => 'Raporlar',
        'icon'       => '📊',
        'url'        => '/stok-takip/public/reports',
        'route'      => '/reports',
        'titles'     => ['Raporlar'],
        'permission' => 'report.view',
    ],

    // 1.6. ENVANTER TAKİBİ
    [
        'type'       => 'link',
        'label'      => 'Envanter Takibi',
        'icon'       => '🧰',
        'url'        => '/stok-takip/public/inventory',
        'route'      => '/inventory',
        'titles'     => ['Envanter Takibi'],
        'permission' => 'inventory.view',
    ],

    // 1.8. 🛒 SATIN ALMA & TEDARİK (ACCORDION)
    [
        'type'             => 'group',
        'id'               => 'menu-purchase',
        'label'            => 'Satın Alma',
        'icon'             => '🛒',
        'group_permission' => [
            'purchase.view', 'purchase.request', 'purchase.approve',
            'purchase.order.view', 'purchase.order.create', 'purchase.order.update',
            'purchase.order.send', 'purchase.order.cancel', 'stock.in'
        ],
        'active_patterns'  => ['/purchase-requests', '/purchase-orders', '/purchase-receipts'],
        'active_titles'    => [
            'Satın Alma Talepleri', 'Yeni Satın Alma Talebi', 'purchase_requests',
            'Satın Alma Siparişleri', 'Yeni Satın Alma Siparişi', 'purchase_orders',
            'purchase_orders_show', 'purchase_orders_create', 'Satın Alma Talebi:', 'Satın Alma Siparişi:',
            'Mal Kabul & Teslimat Yönetimi', 'Mal Kabul', 'purchase_receipts', 'purchase_receipts_show', 'purchase_receipts_create', 'Mal Kabul / Teslim Al:', 'Mal Kabul Detayı:'
        ],
        'children'         => [
            [
                'label'      => 'Satın Alma Talepleri',
                'url'        => '/stok-takip/public/purchase-requests',
                'route'      => '/purchase-requests',
                'titles'     => ['Satın Alma Talepleri', 'Yeni Satın Alma Talebi', 'purchase_requests'],
                'permission' => 'purchase.view',
            ],
            [
                'label'      => 'Satın Alma Siparişleri',
                'url'        => '/stok-takip/public/purchase-orders',
                'route'      => '/purchase-orders',
                'titles'     => ['Satın Alma Siparişleri', 'Yeni Satın Alma Siparişi', 'purchase_orders', 'purchase_orders_show', 'purchase_orders_create'],
                'permission' => 'purchase.order.view',
            ],
            [
                'label'      => 'Mal Kabul',
                'url'        => '/stok-takip/public/purchase-receipts',
                'route'      => '/purchase-receipts',
                'titles'     => ['Mal Kabul & Teslimat Yönetimi', 'Mal Kabul', 'purchase_receipts', 'purchase_receipts_show', 'purchase_receipts_create', 'Mal Kabul / Teslim Al:', 'Mal Kabul Detayı:'],
                'permission' => 'purchase.order.view',
            ],
        ],
    ],

    // 2. 📦 STOK İŞLEMLERİ (ACCORDION)
    [
        'type'             => 'group',
        'id'               => 'menu-stock',
        'label'            => 'Stok İşlemleri',
        'icon'             => '📦',
        'group_permission' => ['stock.in', 'stock.out', 'stock.transfer', 'stock.view', 'stock.return', 'stock.adjustment', 'stock.scrap', 'material.view', 'shipment.view'],
        'active_patterns'  => ['/materials', '/stock-movements', '/stock/', '/shipments'],
        'active_titles'    => ['Malzemeler', 'Yeni Malzeme', 'Malzeme Düzenle', 'Stok Hareketleri', 'Stok Girişi', 'Stok Çıkışı', 'Stok Transferi', 'Stok İadesi', 'Stok İadeleri Dashboard', 'Stok Düzeltme', 'Stok Fire/Hurda', 'Sevkiyat Yönetimi', 'Yeni Sevkiyat Emri'],
        'children'         => [
            [
                'label'        => 'Malzemeler',
                'url'          => '/stok-takip/public/materials',
                'route'        => '/materials',
                'titles'       => ['Malzemeler', 'Yeni Malzeme', 'Malzeme Düzenle'],
                'permission'   => 'material.view',
                'extra_active' => function(string $uri): bool { return str_contains($uri, '/stock/'); },
            ],
            [
                'label'      => 'Stok Hareketleri',
                'url'        => '/stok-takip/public/stock-movements',
                'route'      => '/stock-movements',
                'titles'     => ['Stok Hareketleri'],
                'permission' => ['stock.view', 'material.view'],
            ],
            [
                'label'      => 'Sevkiyat Yönetimi',
                'url'        => '/stok-takip/public/shipments',
                'route'      => '/shipments',
                'titles'     => ['Sevkiyat Yönetimi', 'Yeni Sevkiyat Emri', 'Sevkiyat Emri:'],
                'permission' => 'shipment.view',
            ],
        ],
    ],

    // 3. 🏢 DEPO İŞLEMLERİ (ACCORDION)
    [
        'type'             => 'group',
        'id'               => 'menu-warehouses',
        'label'            => 'Depo İşlemleri',
        'icon'             => '🏢',
        'group_permission' => ['warehouse.manage', 'warehouse.view'],
        'active_patterns'  => ['/warehouses', '/locations'],
        'active_titles'    => ['Depolar', 'Yeni Depo', 'Depo Düzenle', 'Lokasyonlar', 'Yeni Lokasyon', 'Lokasyon Düzenle'],
        'children'         => [
            [
                'label'      => 'Depolar',
                'url'        => '/stok-takip/public/warehouses',
                'route'      => '/warehouses',
                'titles'     => ['Depolar', 'Yeni Depo', 'Depo Düzenle'],
                'permission' => ['warehouse.view', 'warehouse.manage'],
            ],
            [
                'label'      => 'Raf / Lokasyonlar',
                'url'        => '/stok-takip/public/locations',
                'route'      => '/locations',
                'titles'     => ['Lokasyonlar', 'Yeni Lokasyon', 'Lokasyon Düzenle'],
                'permission' => ['warehouse.view', 'warehouse.manage'],
            ],
        ],
    ],

    // 4. 🏭 ÜRETİM (ACCORDION - PLANLAMA & OPERASYON)
    [
        'type'             => 'group',
        'id'               => 'menu-production',
        'label'            => 'Üretim',
        'icon'             => '🏭',
        'group_permission' => ['production.execute', 'production.create', 'production.view', 'recipe.manage', 'mes.create'],
        'active_patterns'  => ['/production', '/recipes'],
        'active_titles'    => ['Üretim & Tüketim', 'Üretim Kayıtları & Stok Tüketimi', 'Yeni Üretim & Stok Tüketimi', 'Üretim & Stok Tüketim Detayı', 'Üretim Reçeteleri (BOM)', 'Yeni Reçete Oluştur', 'Reçeteyi Düzenle', 'İş Emirleri (MES)', 'Yeni İş Emri Oluştur'],
        'active_custom'    => function(string $uri): bool { return str_contains($uri, '/mes') && !str_contains($uri, '/simulator'); },
        'children'         => [
            [
                'label'      => 'Üretim &amp; Tüketim',
                'url'        => '/stok-takip/public/production',
                'route'      => '/production',
                'titles'     => ['Üretim Kayıtları & Stok Tüketimi', 'Yeni Üretim & Stok Tüketimi', 'Üretim & Stok Tüketim Detayı'],
                'permission' => ['production.execute', 'production.create', 'production.view'],
            ],
            [
                'label'      => 'Üretim Reçeteleri',
                'url'        => '/stok-takip/public/recipes',
                'route'      => '/recipes',
                'titles'     => ['Üretim Reçeteleri (BOM)', 'Yeni Reçete Oluştur', 'Reçeteyi Düzenle'],
                'permission' => ['recipe.manage', 'production.view'],
            ],
            [
                'label'         => 'İş Emirleri (MES)',
                'url'           => '/stok-takip/public/mes',
                'route'         => '/mes',
                'titles'        => ['İş Emirleri (MES)', 'Yeni İş Emri Oluştur', 'mes'],
                'permission'    => ['mes.create', 'production.view'],
                'exclude_route' => '/simulator',
            ],
        ],
    ],

    // 5. 📡 MES & İZLEME (ACCORDION - TELEMETRİ, KALİTE & OEE)
    [
        'type'             => 'group',
        'id'               => 'menu-mes',
        'label'            => 'MES &amp; İzleme',
        'icon'             => '📡',
        'group_permission' => ['mes.simulate', 'quality.inspect', 'quality.view', 'oee.view', 'production.view'],
        'active_patterns'  => ['/mes/simulator', '/finished-goods', '/oee', '/andon'],
        'active_titles'    => ['MES Simülatörü', 'mes-simulator', 'Panel Seri Takip & Mamul Deposu', 'Panel Pasaportu', 'OEE & Hat Performans Yönetimi', 'OEE & Hat Performansı', 'Canlı Andon & Fabrika Vitrini', 'Canlı Andon', 'Andon'],
        'children'         => [
            [
                'label'      => 'MES Simülatörü',
                'url'        => '/stok-takip/public/mes/simulator',
                'route'      => '/mes/simulator',
                'titles'     => ['MES Simülatörü', 'mes-simulator'],
                'permission' => 'mes.simulate',
            ],
            [
                'label'      => 'Panel Seri Takip',
                'url'        => '/stok-takip/public/finished-goods',
                'route'      => '/finished-goods',
                'titles'     => ['Panel Seri Takip & Mamul Deposu', 'Panel Pasaportu'],
                'permission' => ['quality.inspect', 'quality.view'],
            ],
            [
                'label'      => 'OEE &amp; Hat Performansı',
                'url'        => '/stok-takip/public/oee',
                'route'      => '/oee',
                'titles'     => ['OEE & Hat Performans Yönetimi', 'OEE & Hat Performansı'],
                'permission' => 'oee.view',
            ],
            [
                'label'      => '📺 Canlı Andon Ekranı',
                'url'        => '/stok-takip/public/andon',
                'route'      => '/andon',
                'titles'     => ['Canlı Andon & Fabrika Vitrini', 'Canlı Andon', 'Andon'],
                'permission' => ['production.view', 'oee.view'],
            ],
        ],
    ],

    // 6. 🛠️ BAKIM & TPM (ACCORDION - CMMS & EKİPMAN BAKIMI)
    [
        'type'             => 'group',
        'id'               => 'menu-maintenance',
        'label'            => 'Bakım &amp; TPM',
        'icon'             => '🛠️',
        'group_permission' => ['maintenance.view', 'maintenance.execute', 'maintenance.create'],
        'active_patterns'  => ['/maintenance'],
        'active_titles'    => ['Bakım Yönetimi & TPM', 'Bakım', 'TPM', 'Bakım & TPM', 'Ekipman Pasaportu:'],
        'children'         => [
            [
                'label'      => 'Bakım Yönetimi &amp; TPM',
                'url'        => '/stok-takip/public/maintenance',
                'route'      => '/maintenance',
                'titles'     => ['Bakım Yönetimi & TPM', 'Bakım', 'TPM', 'Bakım & TPM'],
                'permission' => ['maintenance.view', 'maintenance.execute', 'maintenance.create'],
            ],
        ],
    ],

    // 7. ⚡ ENERJİ (ACCORDION)
    [
        'type'             => 'group',
        'id'               => 'menu-energy',
        'label'            => 'Enerji',
        'icon'             => '⚡',
        'group_permission' => ['energy.view', 'energy.manage'],
        'active_patterns'  => ['/energy', '/energy-dashboard', '/energy-cost', '/energy-alerts', '/energy-reports'],
        'active_titles'    => ['Enerji Dashboard', 'Tüketim Analizi', 'GES Performansı', 'Üretim Hatları', 'Maliyet & Tasarruf', 'Akıllı Alarmlar', 'Enerji Raporları', 'Sayaçlar & Tesis'],
        'children'         => [
            [
                'label'      => 'Enerji Dashboard',
                'url'        => '/stok-takip/public/energy-dashboard',
                'route'      => '/energy-dashboard',
                'titles'     => ['Enerji Dashboard', 'Genel Bakış', 'energy-dashboard'],
                'permission' => 'energy.view',
            ],
            [
                'label'      => 'Tüketim',
                'url'        => '/stok-takip/public/energy/consumption',
                'route'      => '/energy/consumption',
                'titles'     => ['Enerji Tüketimi', 'energy-consumption'],
                'permission' => 'energy.view',
            ],
            [
                'label'      => 'Üretim',
                'url'        => '/stok-takip/public/energy/production',
                'route'      => '/energy/production',
                'titles'     => ['Üretim Hatları', 'energy-production'],
                'permission' => 'energy.view',
            ],
            [
                'label'        => 'Maliyet &amp; Tasarruf',
                'url'          => '/stok-takip/public/energy/cost',
                'route'        => '/energy/cost',
                'titles'       => ['Maliyet & Tasarruf', 'energy-cost'],
                'permission'   => 'energy.view',
                'extra_active' => function(string $uri): bool { return str_contains($uri, '/energy-cost'); },
            ],
            [
                'label'        => 'Alarmlar',
                'url'          => '/stok-takip/public/energy/alerts',
                'route'        => '/energy/alerts',
                'titles'       => ['Akıllı Alarmlar', 'energy-alerts'],
                'permission'   => 'energy.view',
                'extra_active' => function(string $uri): bool { return str_contains($uri, '/energy-alerts'); },
            ],
            [
                'label'      => 'Raporlar',
                'url'        => '/stok-takip/public/energy/reports',
                'route'      => '/energy/reports',
                'titles'     => ['Enerji Raporları', 'energy-reports'],
                'permission' => 'energy.view',
            ],
        ],
    ],

    // 8. 👥 YÖNETİM (ACCORDION)
    [
        'type'             => 'group',
        'id'               => 'menu-management',
        'label'            => 'Yönetim',
        'icon'             => '👥',
        'group_permission' => ['employee.view', 'employee.manage', 'user.view', 'user.manage', 'role.view', 'role.manage', 'supplier.view', 'supplier.manage', 'audit.view', 'api.manage'],
        'active_patterns'  => ['/employees', '/users', '/role-permissions', '/suppliers', '/audit-logs', '/api-tokens', '/portal'],
        'active_titles'    => ['Çalışan Yönetimi', 'Çalışan Dashboardu', 'Kullanıcılar', 'Yeni Kullanıcı', 'Kullanıcı Düzenle', 'Rol & Yetki Yönetimi', 'Tedarikçiler', 'Denetim İzi (Audit Logs)', 'API Anahtarları Yönetimi', 'Dijital Yönetim Merkezi'],
        'children'         => [
            [
                'label'      => '📊 Çalışan Dashboardu',
                'url'        => '/stok-takip/public/employees/dashboard',
                'route'      => '/employees/dashboard',
                'titles'     => ['Çalışan Dashboardu', 'employee-dashboard'],
                'permission' => 'employee.view',
            ],
            [
                'label'         => 'Çalışan Yönetimi',
                'url'           => '/stok-takip/public/employees',
                'route'         => '/employees',
                'titles'        => ['Çalışan Yönetimi', 'employees'],
                'permission'    => 'employee.view',
                'exclude_route' => '/employees/dashboard',
            ],
            [
                'label'      => 'Kullanıcılar',
                'url'        => '/stok-takip/public/users',
                'route'      => '/users',
                'titles'     => ['Kullanıcılar', 'Yeni Kullanıcı', 'Kullanıcı Düzenle'],
                'permission' => 'user.view',
            ],
            [
                'label'      => 'Rol &amp; Yetki Yönetimi',
                'url'        => '/stok-takip/public/role-permissions',
                'route'      => '/role-permissions',
                'titles'     => ['Rol & Yetki Yönetimi', 'Rol &amp; Yetki Yönetimi'],
                'permission' => ['role.view', 'role.manage'],
            ],
            [
                'label'      => 'Tedarikçiler',
                'url'        => '/stok-takip/public/suppliers',
                'route'      => '/suppliers',
                'titles'     => ['Tedarikçiler', 'Yeni Tedarikçi', 'Tedarikçi Düzenle'],
                'permission' => 'supplier.view',
            ],
            [
                'label'      => 'Denetim İzi (Audit)',
                'url'        => '/stok-takip/public/audit-logs',
                'route'      => '/audit-logs',
                'titles'     => ['Denetim İzi (Audit Logs)', 'audit-logs'],
                'permission' => 'audit.view',
            ],
            [
                'label'      => 'API Anahtarları',
                'url'        => '/stok-takip/public/api-tokens',
                'route'      => '/api-tokens',
                'titles'     => ['API Anahtarları Yönetimi', 'api-tokens'],
                'permission' => 'api.manage',
            ],
            [
                'label'      => 'Ayarlar &amp; Portal',
                'url'        => '/stok-takip/public/portal',
                'route'      => '/portal',
                'titles'     => ['Dijital Yönetim Merkezi', 'portal'],
                'permission' => ['user.manage', 'api.manage'],
            ],
        ],
    ],
];
?>

<style>
:root {
    --sidebar-width: 260px;
}

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: var(--sidebar-width, 260px);
    height: 100vh;
    background: #0f172a !important;
    border-right: 1px solid #1e293b;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 0;
    z-index: 1000;
    box-sizing: border-box;
}

.sidebar-header {
    padding: 18px 16px 15px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    align-items: center;
    gap: 12px;
}

.sidebar-logo-icon {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
    color: #ffffff;
    font-size: 18px;
    flex-shrink: 0;
}

.sidebar-logo-text {
    display: flex;
    flex-direction: column;
}

.sidebar-logo-title {
    font-size: 14.5px;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: 0.5px;
    line-height: 1.15;
}

.sidebar-logo-subtitle {
    font-size: 10px;
    color: #94a3b8;
    letter-spacing: 0.8px;
    font-weight: 600;
    margin-top: 2px;
}

.sidebar-nav-wrap {
    flex: 1;
    overflow-y: auto;
    padding: 12px 10px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

/* Custom minimal scrollbar */
.sidebar-nav-wrap::-webkit-scrollbar {
    width: 4px;
}
.sidebar-nav-wrap::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 4px;
}
.sidebar-nav-wrap::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.25);
}

/* Top-level direct link */
.sidebar-nav-link {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 10px 12px;
    min-height: 40px;
    border-radius: 8px;
    font-size: 13.5px;
    color: #cbd5e1;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.15s ease;
    box-sizing: border-box;
}

.sidebar-nav-link:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.08);
}

.sidebar-nav-link.is-active {
    background: #2563eb !important;
    color: #ffffff !important;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.35);
}

/* Accordion Header */
.accordion-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    min-height: 42px;
    border-radius: 8px;
    font-size: 13.5px;
    color: #cbd5e1;
    font-weight: 600;
    cursor: pointer;
    user-select: none;
    transition: all 0.15s ease;
}

.accordion-header:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.06);
}

.accordion-header.is-active-group {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.09);
    border-left: 3px solid #3b82f6;
    padding-left: 9px;
}

.accordion-header-left {
    display: flex;
    align-items: center;
    gap: 11px;
}

.accordion-icon {
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 20px;
}

.accordion-chevron {
    font-size: 12px;
    color: #94a3b8;
    transition: transform 0.2s ease;
}

/* Accordion Sub-Menu Content */
.accordion-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 4px 0 6px 31px;
}

.accordion-item {
    padding: 7px 12px;
    font-size: 12.5px;
    color: #94a3b8;
    text-decoration: none;
    border-radius: 6px;
    transition: all 0.15s ease;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}

.accordion-item:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.06);
}

.accordion-item.is-active {
    color: #ffffff !important;
    background: rgba(37, 99, 235, 0.25) !important;
    border-left: 2px solid #3b82f6;
    font-weight: 600;
}

/* Footer Section */
.sidebar-footer {
    padding: 12px 14px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #090e1a;
}

.sidebar-user-pill {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sidebar-user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #334155;
    color: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 13px;
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.sidebar-user-info {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.sidebar-user-name {
    font-size: 13px;
    font-weight: 700;
    color: #ffffff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sidebar-user-role {
    font-size: 10.5px;
    color: #94a3b8;
}

.sidebar-logout-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 8px;
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.25);
    border-radius: 7px;
    color: #fca5a5;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    transition: all 0.15s ease;
}

.sidebar-logout-btn:hover {
    background: #ef4444;
    color: #ffffff;
    border-color: #ef4444;
}

@media (min-width: 993px) {
    .main-content {
        margin-left: var(--sidebar-width, 260px) !important;
        box-sizing: border-box;
    }
}

@media (max-width: 992px) {
    .sidebar {
        position: relative !important;
        width: 100% !important;
        height: auto !important;
    }
    .main-content {
        margin-left: 0 !important;
        padding: 20px 16px !important;
    }
}
</style>

<aside class="sidebar">

    <!-- LOGO -->
    <div class="sidebar-header">
        <div class="sidebar-logo-icon">📦</div>
        <div class="sidebar-logo-text">
            <span class="sidebar-logo-title">STOK TAKİP</span>
            <span class="sidebar-logo-subtitle">YÖNETİM SİSTEMİ</span>
        </div>
    </div>

    <!-- DİNAMİK ACCORDION NAVIGATION LIST -->
    <nav class="sidebar-nav-wrap">
        <?php foreach ($menu as $entry): ?>
            <?php if ($entry['type'] === 'link'): ?>
                <?php if ($hasPermission($entry['permission'] ?? null)): 
                    $active = $isItemActive($entry);
                ?>
                    <a href="<?= htmlspecialchars($entry['url']) ?>" class="sidebar-nav-link <?= $active ? 'is-active' : '' ?>">
                        <span class="accordion-icon"><?= $entry['icon'] ?></span>
                        <span><?= $entry['label'] ?></span>
                    </a>
                <?php endif; ?>
            <?php elseif ($entry['type'] === 'group'): ?>
                <?php
                // Grup düzeyinde yetki koşulu varsa kontrol et
                if (!empty($entry['group_permission']) && !$hasPermission($entry['group_permission'])) {
                    continue;
                }

                // Kullanıcının yetkili olduğu alt menüleri filtrele
                $visibleChildren = [];
                foreach ($entry['children'] as $child) {
                    if ($hasPermission($child['permission'] ?? null)) {
                        $visibleChildren[] = $child;
                    }
                }

                // Eğer kullanıcı için görünür alt link kalmadıysa ana grubu tamamen gizle
                if (empty($visibleChildren)) {
                    continue;
                }

                $groupActive = $isGroupActive($entry, $visibleChildren);
                $groupId = htmlspecialchars($entry['id']);
                ?>
                <div>
                    <div class="accordion-header <?= $groupActive ? 'is-active-group' : '' ?>" onclick="toggleSidebarAccordion('<?= $groupId ?>', this)">
                        <div class="accordion-header-left">
                            <span class="accordion-icon"><?= $entry['icon'] ?></span>
                            <span><?= $entry['label'] ?></span>
                        </div>
                        <span class="accordion-chevron"><?= $groupActive ? '▾' : '▸' ?></span>
                    </div>

                    <div id="<?= $groupId ?>" class="accordion-content" style="display: <?= $groupActive ? 'flex' : 'none' ?>;">
                        <?php foreach ($visibleChildren as $child): 
                            $childActive = $isItemActive($child);
                        ?>
                            <a href="<?= htmlspecialchars($child['url']) ?>" class="accordion-item <?= $childActive ? 'is-active' : '' ?>">
                                <?= $child['label'] ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <!-- SABİT FOOTER VE ÇIKIŞ YAP BUTONU -->
    <div class="sidebar-footer">
        <div class="sidebar-user-pill">
            <div class="sidebar-user-avatar">
                <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="sidebar-user-info">
                <span class="sidebar-user-name"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
                <span class="sidebar-user-role"><?= (int)($_SESSION['role_id'] ?? 1) === 1 ? 'Sistem Yöneticisi' : 'Kullanıcı' ?></span>
            </div>
        </div>

        <a href="/stok-takip/public/logout" class="sidebar-logout-btn">
            <span>🚪</span>
            <span>Çıkış Yap</span>
        </a>
    </div>

</aside>

<script>
function toggleSidebarAccordion(menuId, headerEl) {
    const menu = document.getElementById(menuId);
    if (!menu) return;
    const chevron = headerEl.querySelector('.accordion-chevron');
    
    if (menu.style.display === 'none' || menu.style.display === '') {
        menu.style.display = 'flex';
        if (chevron) chevron.innerText = '▾';
        headerEl.classList.add('is-active-group');
    } else {
        menu.style.display = 'none';
        if (chevron) chevron.innerText = '▸';
        headerEl.classList.remove('is-active-group');
    }
}
</script>
