<?php

$pageTitle = 'Raporlar';

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

$movementLabels = [
    'IN'           => 'Stok Girişi',
    'OUT'          => 'Stok Çıkışı',
    'TRANSFER_IN'  => 'Transfer Girişi',
    'TRANSFER_OUT' => 'Transfer Çıkışı',
    'RETURN'       => 'Stok İadesi',
    'ADJUSTMENT'   => 'Stok Düzeltme',
];

$getStatusBadge = static function (string $status): string {
    switch ($status) {
        case 'Tükendi':
            return '<span class="status-badge status-badge-danger">Tükendi</span>';
        case 'Kritik':
        case 'Kritik Stok':
            return '<span class="status-badge status-badge-warning">Kritik Stok</span>';
        case 'Fazla Stok':
            return '<span class="status-badge status-badge-info">Fazla Stok</span>';
        case 'Normal':
        default:
            return '<span class="status-badge status-badge-success">Normal</span>';
    }
};

$buildExportUrl = static function (string $currentTab, array $currentFilters): string {
    $params = ['tab' => $currentTab];
    foreach ($currentFilters as $k => $v) {
        if ($v !== null && $v !== '') {
            $params[$k] = $v;
        }
    }
    return '/stok-takip/public/reports/export?' . http_build_query($params);
};

$buildPageUrl = static function (int $pageNum) use ($tab, $filters): string {
    $params = ['tab' => $tab];
    foreach ($filters as $k => $v) {
        if ($v !== null && $v !== '') {
            $params[$k] = $v;
        }
    }
    $params['page'] = $pageNum;
    return '/stok-takip/public/reports?' . http_build_query($params);
};

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="page-kicker">Yönetim ve Analiz</p>
            <h1>Rapor Merkezi</h1>
            <p class="page-description">Stok durumunu, hareketleri, kritik seviyeleri ve depo dağılımlarını detaylı raporlarla inceleyin.</p>
            <p class="report-print-meta">Rapor tarihi: <?= htmlspecialchars(date('d.m.Y H:i')) ?><?php if (!empty($filters['start_date']) || !empty($filters['end_date'])): ?> | Dönem: <?= htmlspecialchars($filters['start_date'] ?? 'Başlangıç yok') ?> - <?= htmlspecialchars($filters['end_date'] ?? 'Bitiş yok') ?><?php endif; ?></p>
        </div>
        <div class="report-actions">
            <button type="button" class="button button-secondary button-small report-print-button" onclick="window.print();">
                🖨 Yazdır
            </button>
            <?php if ($can('report.export')): ?>
                <a href="<?= htmlspecialchars($buildExportUrl($tab, $filters)) ?>" class="button button-primary button-small">
                    📥 CSV Olarak İndir
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Sekmeler (Tabs) -->
    <nav class="report-tabs" aria-label="Rapor Sekmeleri">
        <a href="/stok-takip/public/reports?tab=summary" class="report-tab <?= $tab === 'summary' ? 'is-active' : '' ?>">
            📊 Genel Özet
        </a>
        <a href="/stok-takip/public/reports?tab=stock" class="report-tab <?= $tab === 'stock' ? 'is-active' : '' ?>">
            📦 Stok Durumu
        </a>
        <a href="/stok-takip/public/reports?tab=critical" class="report-tab <?= $tab === 'critical' ? 'is-active' : '' ?>">
            ⚠️ Kritik Stoklar
        </a>
        <a href="/stok-takip/public/reports?tab=warehouses" class="report-tab <?= $tab === 'warehouses' ? 'is-active' : '' ?>">
            🏢 Depo Dağılımı
        </a>
        <a href="/stok-takip/public/reports?tab=movements" class="report-tab <?= $tab === 'movements' ? 'is-active' : '' ?>">
            🔄 Hareket Raporu
        </a>
        <a href="/stok-takip/public/reports?tab=history" class="report-tab <?= $tab === 'history' ? 'is-active' : '' ?>">
            📅 Tarihsel Özet
        </a>
    </nav>

    <div class="report-content">

    <!-- ==================== TAB 1: GENEL ÖZET ==================== -->
    <?php if ($tab === 'summary'): ?>
        <?php $movementSummary = $summary['movement_totals']; ?>
        <section class="report-summary" aria-label="Stok özetleri">
            <div class="report-card">
                <span class="report-card-label">Toplam Aktif Malzeme</span>
                <strong><?= htmlspecialchars((string) $summary['total_materials']) ?></strong>
            </div>
            <div class="report-card">
                <span class="report-card-label">Toplam Aktif Depo</span>
                <strong><?= htmlspecialchars((string) $summary['total_warehouses']) ?></strong>
            </div>
            <div class="report-card">
                <span class="report-card-label">Toplam Stok Miktarı</span>
                <strong><?= htmlspecialchars($formatQty($summary['total_stock'])) ?></strong>
            </div>
            <div class="report-card report-card-alert">
                <span class="report-card-label">Kritik Stoktaki Malzeme</span>
                <strong><?= htmlspecialchars((string) $summary['critical_stock_count']) ?></strong>
            </div>
            <div class="report-card report-card-in">
                <span class="report-card-label">Toplam Stok Girişi</span>
                <strong><?= htmlspecialchars($formatQty($movementSummary['total_in'])) ?></strong>
            </div>
            <div class="report-card report-card-out">
                <span class="report-card-label">Toplam Stok Çıkışı</span>
                <strong><?= htmlspecialchars($formatQty($movementSummary['total_out'])) ?></strong>
            </div>
        </section>

        <section class="report-section">
            <div class="section-heading">
                <div>
                    <p class="page-kicker">Hareket Günlüğü</p>
                    <h2>Son Stok Hareketleri</h2>
                </div>
                <span class="section-meta">Son <?= htmlspecialchars((string) count($recentMovements)) ?> kayıt</span>
            </div>

            <div class="table-shell">
                <table class="materials-table">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Malzeme</th>
                            <th>Depo / Raf</th>
                            <th>Hareket Tipi</th>
                            <th style="text-align: right;">Miktar</th>
                            <th>Referans / Açıklama</th>
                            <th>Kullanıcı</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($recentMovements)): ?>
                        <tr>
                            <td colspan="7" class="empty-state">Kayıtlı stok hareketi bulunamadı.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentMovements as $mov): ?>
                            <?php $mType = $mov['movement_type']; ?>
                            <tr>
                                <td class="movement-date"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($mov['created_at']))) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($mov['material_name']) ?></strong>
                                    <span class="movement-code"><?= htmlspecialchars($mov['material_code']) ?></span>
                                </td>
                                <td>
                                    <span><?= htmlspecialchars($mov['warehouse_name']) ?></span>
                                    <?php if (!empty($mov['location_name'])): ?>
                                        <span class="movement-code"><?= htmlspecialchars($mov['location_name']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="movement-type movement-type-<?= htmlspecialchars(strtolower($mType)) ?>">
                                        <?= htmlspecialchars($movementLabels[$mType] ?? $mType) ?>
                                    </span>
                                </td>
                                <td class="movement-quantity">
                                    <?= htmlspecialchars($formatQty($mov['quantity'])) ?> <?= htmlspecialchars($mov['unit_symbol'] ?? '') ?>
                                </td>
                                <td>
                                    <span><?= htmlspecialchars($mov['description'] ?? '-') ?></span>
                                    <?php if (!empty($mov['reference_no'])): ?>
                                        <span class="movement-reference">Ref: <?= htmlspecialchars($mov['reference_no']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($mov['user_name'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    <!-- ==================== TAB 2: STOK DURUMU ==================== -->
    <?php elseif ($tab === 'stock'): ?>
        <form method="GET" action="/stok-takip/public/reports" class="filter-panel">
            <input type="hidden" name="tab" value="stock">
            <div class="filter-grid" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
                <div class="filter-field">
                    <label for="category_id">Kategori</label>
                    <select name="category_id" id="category_id">
                        <option value="">Tüm Kategoriler</option>
                        <?php foreach ($filterOptions['categories'] as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>" <?= ((int) ($filters['category_id'] ?? 0) === (int) $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="status">Stok Durumu</label>
                    <select name="status" id="status">
                        <option value="">Tüm Durumlar</option>
                        <option value="out_of_stock" <?= ($filters['status'] === 'out_of_stock') ? 'selected' : '' ?>>Tükendi (0)</option>
                        <option value="critical" <?= ($filters['status'] === 'critical') ? 'selected' : '' ?>>Kritik (<= Min Stok)</option>
                        <option value="normal" <?= ($filters['status'] === 'normal') ? 'selected' : '' ?>>Normal</option>
                        <option value="overstock" <?= ($filters['status'] === 'overstock') ? 'selected' : '' ?>>Fazla Stok (> Max)</option>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="search">Malzeme Arama</label>
                    <input type="text" name="search" id="search" placeholder="Kod veya malzeme adı..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                </div>
            </div>

            <div class="filter-actions">
                <a href="/stok-takip/public/reports?tab=stock" class="button button-small">Temizle</a>
                <button type="submit" class="button button-primary button-small">Filtrele</button>
            </div>
        </form>

        <div class="table-shell">
            <table class="materials-table">
                <thead>
                    <tr>
                        <th>Kod</th>
                        <th>Malzeme Adı</th>
                        <th>Kategori</th>
                        <th>Birim</th>
                        <th style="text-align: right;">Mevcut Stok</th>
                        <th style="text-align: right;">Min Stok</th>
                        <th style="text-align: right;">Max Stok</th>
                        <th>Durum</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($reportData)): ?>
                    <tr>
                        <td colspan="8" class="empty-state">Filtrelere uygun malzeme bulunamadı.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reportData as $row): ?>
                        <tr>
                            <td><span class="code-badge"><?= htmlspecialchars($row['material_code']) ?></span></td>
                            <td><strong><?= htmlspecialchars($row['material_name']) ?></strong></td>
                            <td><?= htmlspecialchars($row['category_name']) ?></td>
                            <td><?= htmlspecialchars($row['unit_symbol']) ?></td>
                            <td class="movement-quantity">
                                <strong><?= htmlspecialchars($formatQty($row['current_stock'])) ?></strong>
                            </td>
                            <td style="text-align: right; color: var(--muted);"><?= htmlspecialchars($formatQty($row['min_stock'])) ?></td>
                            <td style="text-align: right; color: var(--muted);"><?= $row['max_stock'] !== null ? htmlspecialchars($formatQty($row['max_stock'])) : '-' ?></td>
                            <td><?= $getStatusBadge($row['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    <!-- ==================== TAB 3: KRİTİK STOKLAR ==================== -->
    <?php elseif ($tab === 'critical'): ?>
        <form method="GET" action="/stok-takip/public/reports" class="filter-panel">
            <input type="hidden" name="tab" value="critical">
            <div class="filter-grid" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
                <div class="filter-field">
                    <label for="category_id">Kategori</label>
                    <select name="category_id" id="category_id">
                        <option value="">Tüm Kategoriler</option>
                        <?php foreach ($filterOptions['categories'] as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>" <?= ((int) ($filters['category_id'] ?? 0) === (int) $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="status">Kritik Seviye</label>
                    <select name="status" id="status">
                        <option value="">Tüm Kritikler (Tükendi + Kritik)</option>
                        <option value="out_of_stock" <?= ($filters['status'] === 'out_of_stock') ? 'selected' : '' ?>>Yalnızca Tükendi (0)</option>
                        <option value="critical" <?= ($filters['status'] === 'critical') ? 'selected' : '' ?>>Yalnızca Kritik (0 < Stok <= Min)</option>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="search">Malzeme Arama</label>
                    <input type="text" name="search" id="search" placeholder="Kod veya malzeme adı..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                </div>
            </div>

            <div class="filter-actions">
                <a href="/stok-takip/public/reports?tab=critical" class="button button-small">Temizle</a>
                <button type="submit" class="button button-primary button-small">Filtrele</button>
            </div>
        </form>

        <div class="table-shell">
            <table class="materials-table">
                <thead>
                    <tr>
                        <th>Kod</th>
                        <th>Malzeme Adı</th>
                        <th>Kategori</th>
                        <th style="text-align: right;">Mevcut Stok</th>
                        <th style="text-align: right;">Min Stok</th>
                        <th style="text-align: right; color: #9f3f32;">Eksik Miktar (İhtiyaç)</th>
                        <th style="text-align: right;">Max Stok</th>
                        <th>Durum</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($reportData)): ?>
                    <tr>
                        <td colspan="8" class="empty-state">Kritik seviyede malzeme bulunamadı. Stok durumunuz güvende!</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reportData as $row): ?>
                        <tr>
                            <td><span class="code-badge"><?= htmlspecialchars($row['material_code']) ?></span></td>
                            <td><strong><?= htmlspecialchars($row['material_name']) ?></strong></td>
                            <td><?= htmlspecialchars($row['category_name']) ?></td>
                            <td class="movement-quantity">
                                <strong><?= htmlspecialchars($formatQty($row['current_stock'])) ?> <?= htmlspecialchars($row['unit_symbol']) ?></strong>
                            </td>
                            <td style="text-align: right;"><?= htmlspecialchars($formatQty($row['min_stock'])) ?> <?= htmlspecialchars($row['unit_symbol']) ?></td>
                            <td style="text-align: right; font-weight: 700; color: #9f3f32;">
                                +<?= htmlspecialchars($formatQty($row['deficit_qty'])) ?> <?= htmlspecialchars($row['unit_symbol']) ?>
                            </td>
                            <td style="text-align: right; color: var(--muted);"><?= $row['max_stock'] !== null ? htmlspecialchars($formatQty($row['max_stock'])) . ' ' . htmlspecialchars($row['unit_symbol']) : '-' ?></td>
                            <td><?= $getStatusBadge($row['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    <!-- ==================== TAB 4: DEPO DAĞILIMI ==================== -->
    <?php elseif ($tab === 'warehouses'): ?>
        <form method="GET" action="/stok-takip/public/reports" class="filter-panel">
            <input type="hidden" name="tab" value="warehouses">
            <div class="filter-grid">
                <div class="filter-field">
                    <label for="warehouse_id">Depo</label>
                    <select name="warehouse_id" id="warehouse_id">
                        <option value="">Tüm Depolar</option>
                        <?php foreach ($filterOptions['warehouses'] as $wh): ?>
                            <option value="<?= (int) $wh['id'] ?>" <?= ((int) ($filters['warehouse_id'] ?? 0) === (int) $wh['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($wh['name']) ?> (<?= htmlspecialchars($wh['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="location_id">Raf / Lokasyon</label>
                    <select name="location_id" id="location_id">
                        <option value="">Tüm Raflar</option>
                        <?php foreach ($filterOptions['locations'] as $loc): ?>
                            <option value="<?= (int) $loc['id'] ?>" <?= ((int) ($filters['location_id'] ?? 0) === (int) $loc['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($loc['warehouse_name']) ?> — <?= htmlspecialchars($loc['name']) ?> (<?= htmlspecialchars($loc['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="category_id">Kategori</label>
                    <select name="category_id" id="category_id">
                        <option value="">Tüm Kategoriler</option>
                        <?php foreach ($filterOptions['categories'] as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>" <?= ((int) ($filters['category_id'] ?? 0) === (int) $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="search">Arama</label>
                    <input type="text" name="search" id="search" placeholder="Malzeme, depo veya raf..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                </div>
            </div>

            <div class="filter-actions">
                <a href="/stok-takip/public/reports?tab=warehouses" class="button button-small">Temizle</a>
                <button type="submit" class="button button-primary button-small">Filtrele</button>
            </div>
        </form>

        <div class="table-shell">
            <table class="materials-table">
                <thead>
                    <tr>
                        <th>Depo</th>
                        <th>Raf / Lokasyon</th>
                        <th>Malzeme Kodu</th>
                        <th>Malzeme Adı</th>
                        <th>Kategori</th>
                        <th style="text-align: right;">Stok Miktarı</th>
                        <th>Son Güncelleme</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($reportData)): ?>
                    <tr>
                        <td colspan="7" class="empty-state">Filtrelere uygun depo stok kaydı bulunamadı.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reportData as $row): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($row['warehouse_name']) ?></strong>
                                <span class="movement-code"><?= htmlspecialchars($row['warehouse_code']) ?></span>
                            </td>
                            <td>
                                <span><?= htmlspecialchars($row['location_name']) ?></span>
                                <span class="movement-code">(<?= htmlspecialchars($row['location_code']) ?>)</span>
                            </td>
                            <td><span class="code-badge"><?= htmlspecialchars($row['material_code']) ?></span></td>
                            <td><strong><?= htmlspecialchars($row['material_name']) ?></strong></td>
                            <td><?= htmlspecialchars($row['category_name']) ?></td>
                            <td class="movement-quantity">
                                <strong><?= htmlspecialchars($formatQty($row['quantity'])) ?> <?= htmlspecialchars($row['unit_symbol']) ?></strong>
                            </td>
                            <td class="movement-date"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($row['updated_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    <!-- ==================== TAB 5: HAREKET RAPORU ==================== -->
    <?php elseif ($tab === 'movements'): ?>
        <?php if (!empty($movementsTotals)): ?>
            <div class="report-metric-strip">
                <div class="metric-box">
                    <span class="metric-box-label">Toplam İşlem Adedi</span>
                    <span class="metric-box-value"><?= (int) $movementsTotals['total_transactions'] ?></span>
                </div>
                <div class="metric-box" style="border-top: 3px solid var(--teal);">
                    <span class="metric-box-label">Toplam Giriş Miktarı</span>
                    <span class="metric-box-value" style="color: var(--teal);">+<?= htmlspecialchars($formatQty($movementsTotals['total_in'])) ?></span>
                </div>
                <div class="metric-box" style="border-top: 3px solid var(--amber);">
                    <span class="metric-box-label">Toplam Çıkış Miktarı</span>
                    <span class="metric-box-value" style="color: var(--amber);">-<?= htmlspecialchars($formatQty($movementsTotals['total_out'])) ?></span>
                    <?php if (!empty($movementsTotals['total_scrap']) && (float) $movementsTotals['total_scrap'] > 0): ?>
                        <div style="font-size: 11px; color: #a4483b; margin-top: 4px;">
                            (Fire/Hurda: <?= htmlspecialchars($formatQty($movementsTotals['total_scrap'])) ?>)
                        </div>
                    <?php endif; ?>
                </div>
                <div class="metric-box" style="border-top: 3px solid #286d78;">
                    <span class="metric-box-label">Net Stok Değişimi</span>
                    <span class="metric-box-value" style="color: <?= ((float) $movementsTotals['net_change'] >= 0) ? 'var(--teal)' : '#9f3f32' ?>;">
                        <?= ((float) $movementsTotals['net_change'] > 0 ? '+' : '') . htmlspecialchars($formatQty($movementsTotals['net_change'])) ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>

        <form method="GET" action="/stok-takip/public/reports" class="filter-panel">
            <input type="hidden" name="tab" value="movements">
            <div class="filter-grid">
                <div class="filter-field">
                    <label for="start_date">Başlangıç Tarihi</label>
                    <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($filters['start_date'] ?? '') ?>">
                </div>

                <div class="filter-field">
                    <label for="end_date">Bitiş Tarihi</label>
                    <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($filters['end_date'] ?? '') ?>">
                </div>

                <div class="filter-field">
                    <label for="warehouse_id">Depo</label>
                    <select name="warehouse_id" id="warehouse_id">
                        <option value="">Tüm Depolar</option>
                        <?php foreach ($filterOptions['warehouses'] as $wh): ?>
                            <option value="<?= (int) $wh['id'] ?>" <?= ((int) ($filters['warehouse_id'] ?? 0) === (int) $wh['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($wh['name']) ?> (<?= htmlspecialchars($wh['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="movement_type">Hareket Tipi</label>
                    <select name="movement_type" id="movement_type">
                        <option value="">Tüm Hareket Tipleri</option>
                        <?php foreach ($filterOptions['movement_types'] as $mKey => $mLabel): ?>
                            <option value="<?= htmlspecialchars($mKey) ?>" <?= (($filters['movement_type'] ?? '') === $mKey) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($mLabel) ?> (<?= htmlspecialchars($mKey) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="material_id">Malzeme</label>
                    <select name="material_id" id="material_id">
                        <option value="">Tüm Malzemeler</option>
                        <?php foreach ($filterOptions['materials'] as $mat): ?>
                            <option value="<?= (int) $mat['id'] ?>" <?= ((int) ($filters['material_id'] ?? 0) === (int) $mat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($mat['code']) ?> - <?= htmlspecialchars($mat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="user_id">Kullanıcı</label>
                    <select name="user_id" id="user_id">
                        <option value="">Tüm Kullanıcılar</option>
                        <?php foreach ($filterOptions['users'] as $usr): ?>
                            <option value="<?= (int) $usr['id'] ?>" <?= ((int) ($filters['user_id'] ?? 0) === (int) $usr['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($usr['first_name'] . ' ' . $usr['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="is_scrap">Fire / Hurda Durumu</label>
                    <select name="is_scrap" id="is_scrap">
                        <option value="">Tüm İşlemler</option>
                        <option value="1" <?= (($filters['is_scrap'] ?? '') === '1') ? 'selected' : '' ?>>Yalnızca Fire / Hurda</option>
                        <option value="0" <?= (($filters['is_scrap'] ?? '') === '0') ? 'selected' : '' ?>>Fire / Hurda Hariç</option>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="search">Serbest Arama</label>
                    <input type="text" name="search" id="search" placeholder="Ref no, açıklama, kod..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                </div>
            </div>

            <div class="filter-actions">
                <a href="/stok-takip/public/reports?tab=movements" class="button button-small">Temizle</a>
                <button type="submit" class="button button-primary button-small">Filtrele</button>
            </div>
        </form>

        <div class="table-shell">
            <table class="materials-table">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Malzeme</th>
                        <th>Depo / Raf</th>
                        <th>Hareket Tipi</th>
                        <th style="text-align: right;">Miktar</th>
                        <th>Açıklama / Referans</th>
                        <th>Kullanıcı</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($reportData)): ?>
                    <tr>
                        <td colspan="7" class="empty-state">Filtrelere uygun hareket kaydı bulunamadı.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reportData as $mov): ?>
                        <?php $mType = $mov['movement_type']; ?>
                        <tr>
                            <td class="movement-date"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($mov['created_at']))) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($mov['material_name']) ?></strong>
                                <span class="movement-code"><?= htmlspecialchars($mov['material_code']) ?></span>
                            </td>
                            <td>
                                <span><?= htmlspecialchars($mov['warehouse_name']) ?></span>
                                <?php if (!empty($mov['location_name'])): ?>
                                    <span class="movement-code"><?= htmlspecialchars($mov['location_name']) ?> (<?= htmlspecialchars($mov['location_code'] ?? '') ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="movement-type movement-type-<?= htmlspecialchars(strtolower($mType)) ?>">
                                    <?= htmlspecialchars($movementLabels[$mType] ?? $mType) ?>
                                </span>
                                <?php if (!empty($mov['is_scrap'])): ?>
                                    <span class="badge-scrap">Fire/Hurda</span>
                                <?php endif; ?>
                            </td>
                            <td class="movement-quantity">
                                <?= htmlspecialchars($formatQty($mov['quantity'])) ?> <?= htmlspecialchars($mov['unit_symbol'] ?? '') ?>
                            </td>
                            <td>
                                <span><?= htmlspecialchars($mov['description'] ?? '-') ?></span>
                                <?php if (!empty($mov['reference_no'])): ?>
                                    <span class="movement-reference">Ref: <?= htmlspecialchars($mov['reference_no']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($mov['user_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination for movements -->
        <?php if ($totalCount > 0): ?>
            <div class="pagination-wrapper">
                <div class="pagination-info">
                    <?php
                    $from = ($currentPage - 1) * $perPage + 1;
                    $to = min($currentPage * $perPage, $totalCount);
                    ?>
                    <strong><?= $from ?> - <?= $to ?></strong> / <strong><?= $totalCount ?></strong> hareket gösteriliyor
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="pagination-nav" aria-label="Sayfalama">
                        <?php if ($currentPage > 1): ?>
                            <a href="<?= htmlspecialchars($buildPageUrl($currentPage - 1)) ?>" class="page-btn">« Önceki</a>
                        <?php else: ?>
                            <span class="page-btn is-disabled">« Önceki</span>
                        <?php endif; ?>

                        <?php
                        $range = 2;
                        $pages = [];
                        for ($i = 1; $i <= $totalPages; $i++) {
                            if ($i === 1 || $i === $totalPages || ($i >= $currentPage - $range && $i <= $currentPage + $range)) {
                                $pages[] = $i;
                            }
                        }

                        $prevPage = 0;
                        foreach ($pages as $p):
                            if ($prevPage > 0 && $p - $prevPage > 1):
                        ?>
                            <span class="page-ellipsis">…</span>
                        <?php
                            endif;
                            $prevPage = $p;
                        ?>
                            <?php if ($p === $currentPage): ?>
                                <span class="page-btn is-active"><?= $p ?></span>
                            <?php else: ?>
                                <a href="<?= htmlspecialchars($buildPageUrl($p)) ?>" class="page-btn"><?= $p ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if ($currentPage < $totalPages): ?>
                            <a href="<?= htmlspecialchars($buildPageUrl($currentPage + 1)) ?>" class="page-btn">Sonraki »</a>
                        <?php else: ?>
                            <span class="page-btn is-disabled">Sonraki »</span>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <!-- ==================== TAB 6: TARİHSEL ÖZET ==================== -->
    <?php elseif ($tab === 'history'): ?>
        <form method="GET" action="/stok-takip/public/reports" class="filter-panel">
            <input type="hidden" name="tab" value="history">
            <div class="filter-grid" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
                <div class="filter-field">
                    <label for="start_date">Başlangıç Tarihi</label>
                    <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($filters['start_date'] ?? '') ?>">
                </div>

                <div class="filter-field">
                    <label for="end_date">Bitiş Tarihi</label>
                    <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($filters['end_date'] ?? '') ?>">
                </div>

                <div class="filter-field">
                    <label for="warehouse_id">Depo</label>
                    <select name="warehouse_id" id="warehouse_id">
                        <option value="">Tüm Depolar</option>
                        <?php foreach ($filterOptions['warehouses'] as $wh): ?>
                            <option value="<?= (int) $wh['id'] ?>" <?= ((int) ($filters['warehouse_id'] ?? 0) === (int) $wh['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($wh['name']) ?> (<?= htmlspecialchars($wh['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="filter-actions">
                <a href="/stok-takip/public/reports?tab=history" class="button button-small">Temizle</a>
                <button type="submit" class="button button-primary button-small">Filtrele</button>
            </div>
        </form>

        <div class="table-shell">
            <table class="materials-table">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th style="text-align: center;">Toplam İşlem</th>
                        <th style="text-align: center;">Giriş Adedi</th>
                        <th style="text-align: center;">Çıkış Adedi</th>
                        <th style="text-align: center;">Düzeltme Adedi</th>
                        <th style="text-align: right; color: var(--teal);">Toplam Giriş Miktarı</th>
                        <th style="text-align: right; color: var(--amber);">Toplam Çıkış Miktarı</th>
                        <th style="text-align: right;">Net Değişim</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($reportData)): ?>
                    <tr>
                        <td colspan="8" class="empty-state">Seçili tarih aralığında hareket kaydı bulunamadı.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reportData as $row): ?>
                        <tr>
                            <td class="movement-date">
                                <strong><?= htmlspecialchars(date('d.m.Y', strtotime($row['movement_date']))) ?></strong>
                            </td>
                            <td style="text-align: center; font-weight: 700;"><?= (int) $row['total_transactions'] ?></td>
                            <td style="text-align: center; color: var(--teal);"><?= (int) $row['in_count'] ?></td>
                            <td style="text-align: center; color: var(--amber);"><?= (int) $row['out_count'] ?></td>
                            <td style="text-align: center; color: #286d78;"><?= (int) $row['adj_count'] ?></td>
                            <td style="text-align: right; font-weight: 700; color: var(--teal);">
                                +<?= htmlspecialchars($formatQty($row['total_in_qty'])) ?>
                            </td>
                            <td style="text-align: right; font-weight: 700; color: var(--amber);">
                                -<?= htmlspecialchars($formatQty($row['total_out_qty'])) ?>
                            </td>
                            <td style="text-align: right; font-weight: 800; color: <?= ((float) $row['net_change_qty'] >= 0) ? 'var(--teal)' : '#9f3f32' ?>;">
                                <?= ((float) $row['net_change_qty'] > 0 ? '+' : '') . htmlspecialchars($formatQty($row['net_change_qty'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    </div>

</main>
