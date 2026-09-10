<?php

$pageTitle = 'Stok Hareketleri';

$movementBadges = [
    'IN' => [
        'label'  => 'Stok Girişi',
        'bg'     => '#f0fdf4',
        'color'  => '#166534',
        'border' => '#bbf7d0',
        'dot'    => '#22c55e',
        'sign'   => '+',
        'sign_color' => '#16a34a'
    ],
    'OUT' => [
        'label'  => 'Stok Çıkışı',
        'bg'     => '#fef2f2',
        'color'  => '#991b1b',
        'border' => '#fecaca',
        'dot'    => '#ef4444',
        'sign'   => '-',
        'sign_color' => '#dc2626'
    ],
    'TRANSFER_IN' => [
        'label'  => 'Transfer Girişi',
        'bg'     => '#eff6ff',
        'color'  => '#1e40af',
        'border' => '#bfdbfe',
        'dot'    => '#3b82f6',
        'sign'   => '+',
        'sign_color' => '#2563eb'
    ],
    'TRANSFER_OUT' => [
        'label'  => 'Transfer Çıkışı',
        'bg'     => '#eff6ff',
        'color'  => '#1e40af',
        'border' => '#bfdbfe',
        'dot'    => '#3b82f6',
        'sign'   => '-',
        'sign_color' => '#2563eb'
    ],
    'RETURN' => [
        'label'  => 'Stok İadesi',
        'bg'     => '#faf5ff',
        'color'  => '#6b21a8',
        'border' => '#e9d5ff',
        'dot'    => '#a855f7',
        'sign'   => '+',
        'sign_color' => '#9333ea'
    ],
    'ADJUSTMENT' => [
        'label'  => 'Sayım / Düzeltme',
        'bg'     => '#fffbeb',
        'color'  => '#92400e',
        'border' => '#fde68a',
        'dot'    => '#f59e0b',
        'sign'   => '±',
        'sign_color' => '#d97706'
    ],
    'SCRAP' => [
        'label'  => 'Fire / Hurda',
        'bg'     => '#fff1f2',
        'color'  => '#9f1239',
        'border' => '#fecdd3',
        'dot'    => '#f43f5e',
        'sign'   => '-',
        'sign_color' => '#e11d48'
    ]
];

$formatQty = static function (float|int|string|null $value): string {
    if ($value === null || $value === '') {
        return '0';
    }
    $num = (float) $value;
    if (floor($num) == $num) {
        return number_format($num, 0, ',', '.');
    }
    $formatted = number_format($num, 2, ',', '.');
    return rtrim(rtrim($formatted, '0'), ',');
};

$buildPageUrl = static function (int $pageNum) use ($filters): string {
    $params = [];
    foreach ($filters as $key => $val) {
        if ($val !== null && $val !== '') {
            $params[$key] = $val;
        }
    }
    $params['page'] = $pageNum;
    return '/stok-takip/public/stock-movements?' . http_build_query($params);
};

$hasActiveFilters = false;
foreach ($filters as $v) {
    if ($v !== null && $v !== '') {
        $hasActiveFilters = true;
        break;
    }
}

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content" style="max-width: 1320px; padding: 24px 32px;">

    <!-- 1. BAŞLIK ALANI -->
    <header style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0; letter-spacing: -0.02em;">STOK HAREKETLERİ</h1>
            <p style="font-size: 13px; color: #64748b; margin: 0;">Tüm depolar ve üretim hatlarındaki gerçek zamanlı hammadde ve mamul akışı.</p>
        </div>
        <div style="font-size: 12.5px; color: #64748b; background: #f8fafc; padding: 6px 14px; border-radius: 8px; border: 1px solid #e2e8f0; font-weight: 600;">
            Toplam <strong style="color: #0f172a;"><?= number_format($totalMovements, 0, ',', '.') ?></strong> hareket listeleniyor
        </div>
    </header>

    <!-- 2. ÜST 4 KPI KARTI -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 20px;">
        
        <!-- 🟢 TOPLAM GİRİŞ -->
        <div style="background: #ffffff; border: 1px solid #bbf7d0; border-radius: 10px; padding: 14px 18px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                <span style="font-size: 11.5px; font-weight: 700; color: #166534; letter-spacing: 0.04em;">🟢 TOPLAM GİRİŞ</span>
                <span style="font-size: 11px; color: #16a34a; font-weight: 600;">Satın Alma &amp; İade</span>
            </div>
            <div style="font-size: 26px; font-weight: 800; color: #15803d; line-height: 1.1;">
                <?= number_format($summaryStats['total_in_count'] ?? 0, 0, ',', '.') ?>
            </div>
            <div style="font-size: 11.5px; color: #64748b; margin-top: 4px;">Giriş hareketi</div>
        </div>

        <!-- 🔴 TOPLAM ÇIKIŞ -->
        <div style="background: #ffffff; border: 1px solid #fecaca; border-radius: 10px; padding: 14px 18px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                <span style="font-size: 11.5px; font-weight: 700; color: #991b1b; letter-spacing: 0.04em;">🔴 TOPLAM ÇIKIŞ</span>
                <span style="font-size: 11px; color: #dc2626; font-weight: 600;">MES &amp; Sarf &amp; Fire</span>
            </div>
            <div style="font-size: 26px; font-weight: 800; color: #b91c1c; line-height: 1.1;">
                <?= number_format($summaryStats['total_out_count'] ?? 0, 0, ',', '.') ?>
            </div>
            <div style="font-size: 11.5px; color: #64748b; margin-top: 4px;">Çıkış hareketi</div>
        </div>

        <!-- 🔵 TRANSFER -->
        <div style="background: #ffffff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 14px 18px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                <span style="font-size: 11.5px; font-weight: 700; color: #1e40af; letter-spacing: 0.04em;">🔵 TRANSFER</span>
                <span style="font-size: 11px; color: #2563eb; font-weight: 600;">Depolar Arası</span>
            </div>
            <div style="font-size: 26px; font-weight: 800; color: #1d4ed8; line-height: 1.1;">
                <?= number_format($summaryStats['total_transfer_count'] ?? 0, 0, ',', '.') ?>
            </div>
            <div style="font-size: 11.5px; color: #64748b; margin-top: 4px;">Lokasyon transferi</div>
        </div>

        <!-- 📦 TOPLAM HAREKET -->
        <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 10px; padding: 14px 18px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                <span style="font-size: 11.5px; font-weight: 700; color: #334155; letter-spacing: 0.04em;">📦 TOPLAM HAREKET</span>
                <span style="font-size: 11px; color: #64748b; font-weight: 600;">Genel Akış</span>
            </div>
            <div style="font-size: 26px; font-weight: 800; color: #0f172a; line-height: 1.1;">
                <?= number_format($summaryStats['total_count'] ?? 0, 0, ',', '.') ?>
            </div>
            <div style="font-size: 11.5px; color: #64748b; margin-top: 4px;">Kayıtlı hareket</div>
        </div>

    </div>

    <!-- 3. SADE FİLTRE ALANI -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px 20px; margin-bottom: 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <form method="GET" action="/stok-takip/public/stock-movements" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) 160px; gap: 12px; align-items: flex-end;">
            
            <!-- Malzeme Ara -->
            <div>
                <label for="search" style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 5px;">Malzeme / Kod Ara</label>
                <input 
                    type="text" 
                    name="search" 
                    id="search" 
                    placeholder="Malzeme adı, kod, ref no..." 
                    value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                    style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 7px; font-size: 13px; outline: none; box-sizing: border-box;"
                >
            </div>

            <!-- Hareket Tipi -->
            <div>
                <label for="movement_type" style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 5px;">Hareket Tipi</label>
                <select name="movement_type" id="movement_type" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 7px; font-size: 13px; outline: none; background: #ffffff; box-sizing: border-box;">
                    <option value="">Tüm Hareketler</option>
                    <option value="IN" <?= ($filters['movement_type'] ?? '') === 'IN' ? 'selected' : '' ?>>🟢 Stok Girişi</option>
                    <option value="OUT" <?= ($filters['movement_type'] ?? '') === 'OUT' ? 'selected' : '' ?>>🔴 Stok Çıkışı</option>
                    <option value="TRANSFER_IN" <?= ($filters['movement_type'] ?? '') === 'TRANSFER_IN' ? 'selected' : '' ?>>🔵 Transfer Girişi</option>
                    <option value="TRANSFER_OUT" <?= ($filters['movement_type'] ?? '') === 'TRANSFER_OUT' ? 'selected' : '' ?>>🔵 Transfer Çıkışı</option>
                    <option value="RETURN" <?= ($filters['movement_type'] ?? '') === 'RETURN' ? 'selected' : '' ?>>🟣 Stok İadesi</option>
                    <option value="ADJUSTMENT" <?= ($filters['movement_type'] ?? '') === 'ADJUSTMENT' ? 'selected' : '' ?>>🟡 Sayım &amp; Düzeltme</option>
                </select>
            </div>

            <!-- Depo / Raf -->
            <div>
                <label for="warehouse_id" style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 5px;">Depo / Raf</label>
                <select name="warehouse_id" id="warehouse_id" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 7px; font-size: 13px; outline: none; background: #ffffff; box-sizing: border-box;">
                    <option value="">Tüm Depolar</option>
                    <?php foreach ($filterOptions['warehouses'] as $wh): ?>
                        <option value="<?= (int)$wh['id'] ?>" <?= ((int)($filters['warehouse_id'] ?? 0) === (int)$wh['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($wh['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Başlangıç Tarihi -->
            <div>
                <label for="start_date" style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 5px;">Başlangıç Tarihi</label>
                <input 
                    type="date" 
                    name="start_date" 
                    id="start_date" 
                    value="<?= htmlspecialchars($filters['start_date'] ?? '') ?>"
                    style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 7px; font-size: 12.5px; outline: none; box-sizing: border-box;"
                >
            </div>

            <!-- Bitiş Tarihi -->
            <div>
                <label for="end_date" style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 5px;">Bitiş Tarihi</label>
                <input 
                    type="date" 
                    name="end_date" 
                    id="end_date" 
                    value="<?= htmlspecialchars($filters['end_date'] ?? '') ?>"
                    style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 7px; font-size: 12.5px; outline: none; box-sizing: border-box;"
                >
            </div>

            <!-- Filtrele & Temizle Butonları -->
            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button type="submit" class="button button-primary" style="flex: 1; padding: 8px 14px; font-size: 13px; font-weight: 600; background: #2563eb; color: #ffffff; border: none; border-radius: 7px; cursor: pointer;">
                    Filtrele
                </button>
                <a href="/stok-takip/public/stock-movements" style="padding: 8px 12px; font-size: 13px; font-weight: 600; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 7px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">
                    Temizle
                </a>
            </div>

        </form>
    </div>

    <!-- 4. MODERN HAREKET TABLOSU -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13.5px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">
                    <th style="padding: 12px 18px;">TARİH</th>
                    <th style="padding: 12px 18px;">MALZEME</th>
                    <th style="padding: 12px 18px; text-align: center;">HAREKET</th>
                    <th style="padding: 12px 18px; text-align: right;">MİKTAR</th>
                    <th style="padding: 12px 18px;">DEPO / RAF</th>
                    <th style="padding: 12px 18px;">AÇIKLAMA</th>
                    <th style="padding: 12px 18px;">KULLANICI</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($movements)): ?>
                <tr>
                    <td colspan="7" style="padding: 36px; text-align: center; color: #64748b; font-size: 14px;">
                        Filtrelere uygun stok hareketi kaydı bulunamadı.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($movements as $movement): ?>
                    <?php 
                    $mType = $movement['movement_type'];
                    $b = $movementBadges[$mType] ?? [
                        'label'  => $mType,
                        'bg'     => '#f1f5f9',
                        'color'  => '#475569',
                        'border' => '#cbd5e1',
                        'dot'    => '#94a3b8',
                        'sign'   => '',
                        'sign_color' => '#475569'
                    ];
                    $qtyFormatted = $formatQty($movement['quantity']);
                    $unit = $movement['unit_symbol'] ?: 'AD';
                    ?>
                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background='#f8fafc';" onmouseout="this.style.background='transparent';">
                        
                        <!-- TARİH -->
                        <td style="padding: 14px 18px; white-space: nowrap;">
                            <div style="font-weight: 700; color: #0f172a; font-size: 13px;">
                                <?= date('d.m.Y', strtotime($movement['created_at'])) ?>
                            </div>
                            <div style="font-size: 11.5px; color: #64748b; font-family: monospace;">
                                <?= date('H:i:s', strtotime($movement['created_at'])) ?>
                            </div>
                        </td>

                        <!-- MALZEME -->
                        <td style="padding: 14px 18px;">
                            <a href="/stok-takip/public/materials/show?id=<?= (int)$movement['material_id'] ?>" style="font-weight: 700; color: #0f172a; text-decoration: none; font-size: 13.5px; display: block; line-height: 1.3;" onmouseover="this.style.color='#2563eb';" onmouseout="this.style.color='#0f172a';">
                                <?= htmlspecialchars($movement['material_name']) ?>
                            </a>
                            <span style="font-size: 11px; color: #64748b; font-family: monospace; background: #f1f5f9; padding: 1px 5px; border-radius: 4px; border: 1px solid #e2e8f0; display: inline-block; margin-top: 3px;">
                                <?= htmlspecialchars($movement['material_code']) ?>
                            </span>
                        </td>

                        <!-- HAREKET (ROZET) -->
                        <td style="padding: 14px 18px; text-align: center; white-space: nowrap;">
                            <span style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 700; background: <?= $b['bg'] ?>; color: <?= $b['color'] ?>; border: 1px solid <?= $b['border'] ?>;">
                                <span style="width: 6px; height: 6px; border-radius: 50%; background: <?= $b['dot'] ?>;"></span>
                                <?= $b['label'] ?>
                            </span>
                        </td>

                        <!-- MİKTAR -->
                        <td style="padding: 14px 18px; text-align: right; white-space: nowrap;">
                            <div style="font-size: 14.5px; font-weight: 800; color: <?= $b['sign_color'] ?>; font-variant-numeric: tabular-nums;">
                                <span><?= $b['sign'] ?><?= $qtyFormatted ?></span>
                                <span style="font-size: 11.5px; font-weight: 600; color: #64748b; margin-left: 2px;"><?= htmlspecialchars($unit) ?></span>
                            </div>
                        </td>

                        <!-- DEPO / RAF -->
                        <td style="padding: 14px 18px;">
                            <div style="font-weight: 700; color: #0f172a; font-size: 13px;">
                                <?= htmlspecialchars($movement['warehouse_name']) ?>
                            </div>
                            <?php if (!empty($movement['location_name'])): ?>
                                <div style="font-size: 11.5px; color: #64748b;">
                                    <?= htmlspecialchars($movement['location_name']) ?> (<?= htmlspecialchars($movement['location_code'] ?? '') ?>)
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- AÇIKLAMA / REFERANS -->
                        <td style="padding: 14px 18px; max-width: 260px;">
                            <?php if (!empty($movement['description']) && $movement['description'] !== '-'): ?>
                                <div style="font-size: 12.5px; color: #334155; line-height: 1.35; word-break: break-word;">
                                    <?= htmlspecialchars($movement['description']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($movement['reference_no'])): ?>
                                <span style="font-family: monospace; font-size: 11px; color: #64748b; background: #f8fafc; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0; display: inline-block; margin-top: 3px;">
                                    Ref: <?= htmlspecialchars($movement['reference_no']) ?>
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- KULLANICI -->
                        <td style="padding: 14px 18px; white-space: nowrap;">
                            <div style="display: flex; align-items: center; gap: 7px;">
                                <span style="width: 22px; height: 22px; border-radius: 50%; background: #e0e7ff; color: #3730a3; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;">
                                    <?= strtoupper(substr($movement['user_name'] ?? 'U', 0, 1)) ?>
                                </span>
                                <span style="font-size: 12.5px; color: #334155; font-weight: 500;">
                                    <?= htmlspecialchars($movement['user_name']) ?>
                                </span>
                            </div>
                        </td>

                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <!-- 5. SAYFALAMA (PAGINATION) -->
        <?php if ($totalMovements > 0): ?>
            <div style="padding: 12px 20px; background: #fafafa; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; font-size: 12.5px; color: #64748b;">
                <div>
                    <?php
                    $from = ($currentPage - 1) * $perPage + 1;
                    $to = min($currentPage * $perPage, $totalMovements);
                    ?>
                    <strong><?= $from ?> - <?= $to ?></strong> / <strong><?= $totalMovements ?></strong> kayıt gösteriliyor
                </div>

                <?php if ($totalPages > 1): ?>
                    <div style="display: flex; gap: 4px; align-items: center;">
                        <?php if ($currentPage > 1): ?>
                            <a href="<?= htmlspecialchars($buildPageUrl($currentPage - 1)) ?>" style="padding: 5px 10px; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; text-decoration: none; font-weight: 600; font-size: 12px;">&laquo; Önceki</a>
                        <?php else: ?>
                            <span style="padding: 5px 10px; border-radius: 6px; border: 1px solid #e2e8f0; background: #f8fafc; color: #94a3b8; font-size: 12px;">&laquo; Önceki</span>
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
                            <span style="padding: 0 4px; color: #94a3b8;">…</span>
                        <?php
                            endif;
                            $prevPage = $p;
                        ?>
                            <?php if ($p === $currentPage): ?>
                                <span style="padding: 5px 10px; border-radius: 6px; background: #2563eb; color: #ffffff; font-weight: 700; font-size: 12px;"><?= $p ?></span>
                            <?php else: ?>
                                <a href="<?= htmlspecialchars($buildPageUrl($p)) ?>" style="padding: 5px 10px; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; text-decoration: none; font-size: 12px; font-weight: 600;"><?= $p ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if ($currentPage < $totalPages): ?>
                            <a href="<?= htmlspecialchars($buildPageUrl($currentPage + 1)) ?>" style="padding: 5px 10px; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; text-decoration: none; font-weight: 600; font-size: 12px;">Sonraki &raquo;</a>
                        <?php else: ?>
                            <span style="padding: 5px 10px; border-radius: 6px; border: 1px solid #e2e8f0; background: #f8fafc; color: #94a3b8; font-size: 12px;">Sonraki &raquo;</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</main>