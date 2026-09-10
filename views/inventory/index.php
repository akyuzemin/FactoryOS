<?php

$pageTitle = 'Envanter Takibi';
$activePage = 'inventory';

$statusLabels = [
    'IN_STOCK' => 'Stokta',
    'ASSIGNED' => 'Tahsisli',
    'IN_USE' => 'Kullanımda',
    'IN_REPAIR' => 'Bakımda',
    'LOST' => 'Kayıp',
    'RETIRED' => 'Emekli',
    'DISPOSED' => 'Zayi',
];

$statusClasses = [
    'IN_STOCK' => 'status-badge-info',
    'ASSIGNED' => 'status-badge-warning',
    'IN_USE' => 'status-badge-success',
    'IN_REPAIR' => 'status-badge-warning',
    'LOST' => 'status-badge-danger',
    'RETIRED' => 'status-badge-inactive',
    'DISPOSED' => 'status-badge-danger',
];

$formatDate = static function (?string $date): string {
    return $date ? date('d.m.Y', strtotime($date)) : '-';
};

$isWarrantySoon = static function (?string $date): bool {
    if (!$date) {
        return false;
    }
    $timestamp = strtotime($date);
    return $timestamp >= strtotime('today') && $timestamp <= strtotime('+30 days');
};

$buildUrl = static function (array $values): string {
    $params = [];
    foreach ($values as $key => $value) {
        if ($value !== null && $value !== '') {
            $params[$key] = $value;
        }
    }
    return '/stok-takip/public/inventory' . ($params ? '?' . http_build_query($params) : '');
};

$buildExportUrl = static function (array $values): string {
    $params = [];
    foreach ($values as $key => $value) {
        if ($value !== null && $value !== '') {
            $params[$key] = $value;
        }
    }
    return '/stok-takip/public/inventory/export' . ($params ? '?' . http_build_query($params) : '');
};

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">
    <header class="page-header">
        <div>
            <p class="page-kicker">Varlık Yönetimi</p>
            <h1>Envanter Takibi</h1>
            <p class="page-description">Şirket varlıklarını, sorumlularını, konumlarını ve bakım bağlantılarını yönetin.</p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <?php if ($can('inventory.export')): ?>
                <a class="button button-secondary" href="<?= htmlspecialchars($buildExportUrl($filters)) ?>">Excel'e Aktar</a>
            <?php endif; ?>
            <?php if ($can('inventory.create')): ?>
                <a class="button button-primary" href="/stok-takip/public/inventory/create">+ Yeni Varlık</a>
            <?php endif; ?>
        </div>
    </header>

    <form method="GET" action="/stok-takip/public/inventory" class="filter-panel">
        <div class="filter-grid inventory-filter-grid">
            <div class="filter-field">
                <label for="search">Arama</label>
                <input type="search" id="search" name="search" placeholder="Varlık kodu, ad, seri no, marka veya model ara..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
            </div>
            <div class="filter-field">
                <label for="category_id">Kategori</label>
                <select id="category_id" name="category_id">
                    <option value="">Tüm kategoriler</option>
                    <?php foreach ($filterOptions['categories'] as $category): ?>
                        <option value="<?= (int) $category['id'] ?>" <?= ((int) ($filters['category_id'] ?? 0) === (int) $category['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="status">Durum</label>
                <select id="status" name="status">
                    <option value="">Tüm durumlar</option>
                    <?php foreach ($filterOptions['statuses'] as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>" <?= (($filters['status'] ?? '') === $status) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($statusLabels[$status] ?? $status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="warehouse_id">Depo</label>
                <select id="warehouse_id" name="warehouse_id">
                    <option value="">Tüm depolar</option>
                    <?php foreach ($filterOptions['warehouses'] as $warehouse): ?>
                        <option value="<?= (int) $warehouse['id'] ?>" <?= ((int) ($filters['warehouse_id'] ?? 0) === (int) $warehouse['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($warehouse['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="responsible_user_id">Sorumlu kişi</label>
                <select id="responsible_user_id" name="responsible_user_id">
                    <option value="">Tüm sorumlular</option>
                    <?php foreach ($filterOptions['users'] as $user): ?>
                        <option value="<?= (int) $user['id'] ?>" <?= ((int) ($filters['responsible_user_id'] ?? 0) === (int) $user['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="filter-actions">
            <a class="button button-small" href="/stok-takip/public/inventory">Temizle</a>
            <button class="button button-primary button-small" type="submit">Filtrele</button>
        </div>
    </form>

    <section class="inventory-kpi-grid" aria-label="Envanter özetleri">
        <div class="inventory-kpi-card">
            <span class="inventory-kpi-label">Toplam Varlık</span>
            <strong><?= (int) $kpis['total'] ?></strong>
        </div>
        <div class="inventory-kpi-card inventory-kpi-success">
            <span class="inventory-kpi-label">Kullanımda</span>
            <strong><?= (int) $kpis['in_use'] ?></strong>
        </div>
        <div class="inventory-kpi-card inventory-kpi-info">
            <span class="inventory-kpi-label">Stokta</span>
            <strong><?= (int) $kpis['in_stock'] ?></strong>
        </div>
        <div class="inventory-kpi-card inventory-kpi-warning">
            <span class="inventory-kpi-label">Bakımda</span>
            <strong><?= (int) $kpis['in_repair'] ?></strong>
        </div>
        <div class="inventory-kpi-card inventory-kpi-danger">
            <span class="inventory-kpi-label">Kullanım Dışı</span>
            <strong><?= (int) $kpis['out_of_use'] ?></strong>
        </div>
    </section>

    <div class="table-shell inventory-table-shell">
        <table class="materials-table inventory-table">
            <thead>
                <tr>
                    <th>Varlık Kodu</th>
                    <th>Varlık</th>
                    <th>Kategori</th>
                    <th>Seri No</th>
                    <th>Marka / Model</th>
                    <th>Sorumlu</th>
                    <th>Depo / Lokasyon</th>
                    <th>Durum</th>
                    <th>Garanti Bitişi</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($assets)): ?>
                <tr><td colspan="10" class="empty-state">Filtrelere uygun envanter kaydı bulunamadı.</td></tr>
            <?php else: ?>
                <?php foreach ($assets as $asset): ?>
                    <?php $status = $asset['status']; ?>
                    <tr>
                        <td><span class="code-badge"><?= htmlspecialchars($asset['asset_code']) ?></span></td>
                        <td>
                            <strong><?= htmlspecialchars($asset['asset_name']) ?></strong>
                            <?php if (!empty($asset['maintenance_asset_code'])): ?>
                                <span class="movement-code">Bakım: <?= htmlspecialchars($asset['maintenance_asset_code']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($asset['category_name']) ?></td>
                        <td><?= htmlspecialchars($asset['serial_no'] ?: '-') ?></td>
                        <td><?= htmlspecialchars(trim(($asset['manufacturer'] ?? '') . ' ' . ($asset['model_no'] ?? '')) ?: '-') ?></td>
                        <td><?= htmlspecialchars($asset['responsible_name'] ?: '-') ?></td>
                        <td>
                            <span><?= htmlspecialchars($asset['warehouse_name'] ?: '-') ?></span>
                            <?php if (!empty($asset['location_name'])): ?><span class="movement-code"><?= htmlspecialchars($asset['location_name']) ?></span><?php endif; ?>
                        </td>
                        <td><span class="status-badge <?= htmlspecialchars($statusClasses[$status] ?? 'status-badge-info') ?>"><?= htmlspecialchars($statusLabels[$status] ?? $status) ?></span></td>
                        <td class="inventory-warranty <?= $isWarrantySoon($asset['warranty_end_date']) ? 'inventory-warranty-soon' : '' ?>">
                            <?= htmlspecialchars($formatDate($asset['warranty_end_date'])) ?>
                            <?php if ($isWarrantySoon($asset['warranty_end_date'])): ?><span>Yaklaşıyor</span><?php endif; ?>
                        </td>
                        <td class="inventory-actions">
                            <a class="button button-small" href="/stok-takip/public/inventory/show?id=<?= (int) $asset['id'] ?>">Detay</a>
                            <?php if ($can('inventory.update')): ?><a class="button button-small" href="/stok-takip/public/inventory/edit?id=<?= (int) $asset['id'] ?>">Düzenle</a><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
