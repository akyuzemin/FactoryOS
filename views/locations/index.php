<?php

$pageTitle = 'Raflar ve Lokasyonlar';

$formatQty = static function (float|int|string|null $value): string {
    if ($value === null || $value === '') {
        return '0';
    }
    $num = (float) $value;
    if (floor($num) == $num) {
        return number_format($num, 0, ',', '.');
    }
    $formatted = number_format($num, 3, ',', '.');
    return rtrim(rtrim($formatted, '0'), ',');
};

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="page-kicker">Operasyon Yönetimi</p>
            <h1>Raflar ve Lokasyonlar</h1>
            <p class="page-description">Depolara bağlı raf ve saklama alanlarını yönetin.</p>
        </div>
        <div style="display: flex; align-items: center; gap: 12px;">
            <?php if ($can('warehouse.manage')): ?>
                <a class="button button-primary" href="/stok-takip/public/locations/create">+ Yeni Raf</a>
            <?php endif; ?>
            <div class="page-count">
                <?= htmlspecialchars((string) count($locations)) ?> aktif raf
            </div>
        </div>
    </header>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="error" style="margin-bottom: 24px;">
            <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div style="margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
        <form method="GET" action="/stok-takip/public/locations" style="display: flex; align-items: center; gap: 10px;">
            <select name="warehouse_id" onchange="this.form.submit()" style="min-width: 240px; height: 38px; padding: 6px 12px; border: 1px solid var(--border); border-radius: 7px; font-size: 13px; color: var(--ink); background: var(--input-bg);">
                <option value="">Tüm Depolar</option>
                <?php foreach ($warehouses as $wh): ?>
                    <option value="<?= (int) $wh['id'] ?>" <?= ((int) ($warehouseId ?? 0) === (int) $wh['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($wh['name']) ?> (<?= htmlspecialchars($wh['code']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($warehouseId)): ?>
                <a href="/stok-takip/public/locations" class="button button-small">Filtreyi Temizle</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-shell">
        <table class="materials-table">
            <thead>
                <tr>
                    <th>Kod</th>
                    <th>Raf / Lokasyon Adı</th>
                    <th>Bağlı Depo</th>
                    <th style="text-align: right;">Mevcut Stok</th>
                    <th>Açıklama</th>
                    <th>Durum</th>
                    <?php if ($can('warehouse.manage')): ?>
                        <th>İşlemler</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($locations)): ?>
                <tr>
                    <td colspan="<?= $can('warehouse.manage') ? '7' : '6' ?>" class="empty-state">
                        Kayıtlı raf / lokasyon bulunamadı.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($locations as $loc): ?>
                    <tr>
                        <td><span class="code-badge"><?= htmlspecialchars($loc['code']) ?></span></td>
                        <td><strong><?= htmlspecialchars($loc['name']) ?></strong></td>
                        <td>
                            <span class="warehouse-name"><?= htmlspecialchars($loc['warehouse_name']) ?></span>
                            <span class="movement-code">(<?= htmlspecialchars($loc['warehouse_code']) ?>)</span>
                        </td>
                        <td class="movement-quantity">
                            <?= htmlspecialchars($formatQty($loc['current_stock'])) ?>
                        </td>
                        <td><?= htmlspecialchars($loc['description'] ?? '-') ?></td>
                        <td><span class="status-badge">Aktif</span></td>
                        <?php if ($can('warehouse.manage')): ?>
                            <td class="location-actions">
                                <a class="button button-small" href="/stok-takip/public/locations/edit?id=<?= (int) $loc['id'] ?>">Düzenle</a>
                                <form method="POST" action="/stok-takip/public/locations/delete" onsubmit="return confirm('Bu raf/lokasyon pasif hale getirilsin mi?');">
            <?= CsrfService::tokenField() ?>
                                    <input type="hidden" name="id" value="<?= (int) $loc['id'] ?>">
                                    <button class="button button-small button-danger" type="submit">Pasifleştir</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>
