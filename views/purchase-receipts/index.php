<?php
declare(strict_types=1);

$pageTitle = 'Mal Kabul & Teslimat Yönetimi';
$activePage = 'purchase_receipts';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';

$formatDate = static function (?string $date): string {
    if (empty($date)) {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date('d.m.Y', $ts) : $date;
};

$formatNumber = static function (float|int|string $num, int $decimals = 2): string {
    return number_format((float)$num, $decimals, ',', '.');
};

// Durum Rozet Stilleri
$statusStyleMap = [
    'COMPLETED' => ['label' => 'Tamamlandı', 'bg' => '#dcfce7', 'color' => '#166534', 'border' => '#bbf7d0', 'icon' => '✅'],
    'CANCELLED' => ['label' => 'İptal',      'bg' => '#f3f4f6', 'color' => '#6b7280', 'border' => '#e5e7eb', 'icon' => '🚫'],
];

// Pagination link query helper
$buildQuery = static function (array $overrides = []) use ($filters): string {
    $params = array_merge($filters, $overrides);
    $params = array_filter($params, static fn($v) => $v !== '' && $v !== 0 && $v !== '0');
    return http_build_query($params);
};

$hasActiveFilters = !empty($filters['search']) 
    || !empty($filters['status']) 
    || !empty($filters['supplier_id']) 
    || !empty($filters['warehouse_id']) 
    || !empty($filters['date_from']) 
    || !empty($filters['date_to']);
?>

<main class="main-content">

    <!-- FLASH MESAJLARI -->
    <?php if (!empty($_SESSION['success'])): ?>
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 12px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 13.5px; font-weight: 600; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span>✅</span> <?= htmlspecialchars($_SESSION['success']) ?>
            </div>
            <button type="button" onclick="this.parentElement.remove();" style="background: none; border: none; font-size: 16px; cursor: pointer; color: #166534;">&times;</button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 13.5px; font-weight: 600; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span>⚠️</span> <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <button type="button" onclick="this.parentElement.remove();" style="background: none; border: none; font-size: 16px; cursor: pointer; color: #991b1b;">&times;</button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- 1. ÜST BAŞLIK VE ÖZET BİLGİ -->
    <header class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">
        <div>
            <p class="page-kicker" style="font-size: 11.5px; font-weight: 800; color: #16a34a; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 4px 0;">
                📦 Satın Alma &amp; Mal Kabul
            </p>
            <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
                Mal Kabul &amp; Teslimat Listesi
            </h1>
            <p class="page-description" style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">
                Tedarikçilerden fabrikaya ulaşan sipariş teslimatlarını, irsaliyeleri ve depo stok girişlerini takip edin.
            </p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <div class="page-count" style="background: #ffffff; border: 1px solid #e2e8f0; padding: 7px 14px; border-radius: 8px; font-size: 13px; font-weight: 700; color: #334155; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
                Toplam: <b style="color: #16a34a;"><?= (int)($kpis['total_count'] ?? 0) ?></b> teslimat
            </div>
            <a href="/stok-takip/public/purchase-orders" class="button button-secondary" style="padding: 7px 14px; font-size: 13px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; background: #ffffff; border: 1px solid #cbd5e1; color: #334155; border-radius: 8px;">
                <span>🛒</span> Siparişlere Git
            </a>
        </div>
    </header>

    <!-- 2. KPI GÖSTERGE KARTLARI (4 METRİK) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 20px;">
        
        <!-- TOPLAM MAL KABUL -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; border-left: 4px solid #16a34a; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #16a34a; text-transform: uppercase;">
                📦 TOPLAM MAL KABUL
            </div>
            <div style="font-size: 24px; font-weight: 900; color: #15803d; margin: 4px 0 2px 0;">
                <?= (int)($kpis['total_count'] ?? 0) ?>
            </div>
            <div style="font-size: 11.5px; color: #64748b;">
                Tüm Teslimat Makbuzları
            </div>
        </div>

        <!-- BUGÜN YAPILAN -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; border-left: 4px solid #0284c7; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #0284c7; text-transform: uppercase;">
                📅 BUGÜN YAPILAN
            </div>
            <div style="font-size: 24px; font-weight: 900; color: #0369a1; margin: 4px 0 2px 0;">
                <?= (int)($kpis['today_count'] ?? 0) ?>
            </div>
            <div style="font-size: 11.5px; color: #64748b;">
                Bugünkü Mal Kabuller
            </div>
        </div>

        <!-- BU AY YAPILAN -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; border-left: 4px solid #8b5cf6; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #7c3aed; text-transform: uppercase;">
                📊 BU AY YAPILAN
            </div>
            <div style="font-size: 24px; font-weight: 900; color: #6d28d9; margin: 4px 0 2px 0;">
                <?= (int)($kpis['month_count'] ?? 0) ?>
            </div>
            <div style="font-size: 11.5px; color: #64748b;">
                Cari Ay Girişleri
            </div>
        </div>

        <!-- TOPLAM TESLİM ALINAN MİKTAR -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; border-left: 4px solid #f59e0b; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #d97706; text-transform: uppercase;">
                ⚖️ TOPLAM TESLİM MİKTARI
            </div>
            <div style="font-size: 24px; font-weight: 900; color: #b45309; margin: 4px 0 2px 0;">
                <?= $formatNumber((float)($kpis['total_received_qty'] ?? 0), 2) ?>
            </div>
            <div style="font-size: 11.5px; color: #64748b;">
                Kabul Edilen Miktar
            </div>
        </div>

    </div>

    <!-- 3. FİLTRE ALANI -->
    <form method="GET" action="/stok-takip/public/purchase-receipts" class="filter-panel" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; align-items: flex-end;">
            
            <!-- Arama -->
            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 4px;">Arama</label>
                <input type="search" name="search" placeholder="Makbuz no, sipariş no, irsaliye..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; box-sizing: border-box;">
            </div>

            <!-- Tedarikçi -->
            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 4px;">Tedarikçi</label>
                <select name="supplier_id" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; box-sizing: border-box; background: #fff;">
                    <option value="">Tüm Tedarikçiler</option>
                    <?php foreach ($suppliers as $sup): ?>
                        <option value="<?= (int)$sup['id'] ?>" <?= ((int)($filters['supplier_id'] ?? 0) === (int)$sup['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sup['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Depo -->
            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 4px;">Depo</label>
                <select name="warehouse_id" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; box-sizing: border-box; background: #fff;">
                    <option value="">Tüm Depolar</option>
                    <?php foreach ($warehouses as $wh): ?>
                        <option value="<?= (int)$wh['id'] ?>" <?= ((int)($filters['warehouse_id'] ?? 0) === (int)$wh['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($wh['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Durum -->
            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 4px;">Durum</label>
                <select name="status" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; box-sizing: border-box; background: #fff;">
                    <option value="">Tüm Durumlar</option>
                    <?php foreach ($statuses as $st): ?>
                        <option value="<?= htmlspecialchars($st) ?>" <?= (($filters['status'] ?? '') === $st) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($statusStyleMap[$st]['label'] ?? $st) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Başlangıç Tarihi -->
            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 4px;">Teslim Başlangıç</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; box-sizing: border-box;">
            </div>

            <!-- Bitiş Tarihi -->
            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 4px;">Teslim Bitiş</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; box-sizing: border-box;">
            </div>

            <!-- Butonlar -->
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="button button-primary" style="padding: 8px 16px; font-size: 13px; font-weight: 700; height: 38px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; background: #16a34a; border-color: #15803d;">
                    <span>🔍</span> Filtrele
                </button>
                <a href="/stok-takip/public/purchase-receipts" class="button button-secondary" style="padding: 8px 14px; font-size: 13px; font-weight: 600; height: 38px; text-decoration: none; display: inline-flex; align-items: center; background: #ffffff; border: 1px solid #cbd5e1; color: #475569;">
                    Temizle
                </a>
            </div>

        </div>
    </form>

    <!-- 4. MAL KABUL TABLOSU -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 20px;">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 12px 14px; font-weight: 700; color: #475569; white-space: nowrap;">Makbuz No</th>
                        <th style="padding: 12px 14px; font-weight: 700; color: #475569; white-space: nowrap;">Teslim Tarihi</th>
                        <th style="padding: 12px 14px; font-weight: 700; color: #475569; white-space: nowrap;">Sipariş No</th>
                        <th style="padding: 12px 14px; font-weight: 700; color: #475569; white-space: nowrap;">Tedarikçi</th>
                        <th style="padding: 12px 14px; font-weight: 700; color: #475569; white-space: nowrap;">Depo / Lokasyon</th>
                        <th style="padding: 12px 14px; font-weight: 700; color: #475569; white-space: nowrap;">Teslim Alan</th>
                        <th style="padding: 12px 14px; font-weight: 700; color: #475569; white-space: nowrap;">İrsaliye No</th>
                        <th style="padding: 12px 14px; font-weight: 700; color: #475569; text-align: right; white-space: nowrap;">Teslim Miktarı</th>
                        <th style="padding: 12px 14px; font-weight: 700; color: #475569; text-align: center; white-space: nowrap;">Durum</th>
                        <th style="padding: 12px 14px; font-weight: 700; color: #475569; text-align: center; white-space: nowrap;">Kalem</th>
                        <th style="padding: 12px 14px; font-weight: 700; color: #475569; text-align: right; white-space: nowrap;">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($receipts)): ?>
                        <tr>
                            <td colspan="11" style="padding: 48px 20px; text-align: center;">
                                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px;">
                                    <span style="font-size: 38px;">🚚</span>
                                    <div style="font-size: 15px; font-weight: 700; color: #334155;">
                                        <?= $hasActiveFilters ? 'Filtrelere uygun mal kabul kaydı bulunamadı.' : 'Henüz mal kabul kaydı bulunmuyor.' ?>
                                    </div>
                                    <p style="font-size: 13px; color: #64748b; margin: 0; max-width: 440px;">
                                        <?= $hasActiveFilters ? 'Filtre kriterlerini değiştirerek veya temizleyerek tekrar deneyebilirsiniz.' : 'Onaylanan satın alma siparişleri için mal kabul yapıldığında makbuzlar burada listelenecektir.' ?>
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($receipts as $r): ?>
                            <?php
                            $stInfo = $statusStyleMap[$r['status']] ?? ['label' => $r['status'], 'bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#cbd5e1', 'icon' => ''];
                            ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background='#f8fafc';" onmouseout="this.style.background='#ffffff';">
                                
                                <!-- Makbuz No -->
                                <td style="padding: 12px 14px; font-family: monospace; font-weight: 800;">
                                    <a href="/stok-takip/public/purchase-receipts/show?id=<?= (int)$r['id'] ?>" style="color: #16a34a; text-decoration: none; font-weight: 800;">
                                        <?= htmlspecialchars($r['receipt_no']) ?>
                                    </a>
                                </td>

                                <!-- Teslim Tarihi -->
                                <td style="padding: 12px 14px; color: #334155; white-space: nowrap;">
                                    <?= $formatDate($r['receipt_date']) ?>
                                </td>

                                <!-- Sipariş No -->
                                <td style="padding: 12px 14px; font-family: monospace; white-space: nowrap;">
                                    <a href="/stok-takip/public/purchase-orders/show?id=<?= (int)$r['purchase_order_id'] ?>" style="color: #2563eb; text-decoration: none; font-weight: 700;">
                                        <?= htmlspecialchars($r['order_no'] ?? '-') ?>
                                    </a>
                                </td>

                                <!-- Tedarikçi -->
                                <td style="padding: 12px 14px; font-weight: 600; color: #0f172a; white-space: nowrap;">
                                    <span style="display: inline-block; padding: 2px 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 12px;">
                                        <?= htmlspecialchars($r['supplier_name'] ?? '-') ?>
                                    </span>
                                </td>

                                <!-- Depo / Lokasyon -->
                                <td style="padding: 12px 14px; white-space: nowrap;">
                                    <div style="font-weight: 700; color: #0f172a; font-size: 12.5px;">
                                        <?= htmlspecialchars($r['warehouse_name'] ?? '-') ?>
                                    </div>
                                    <div style="font-size: 11.5px; color: #64748b;">
                                        <?= htmlspecialchars($r['location_name'] ?? '-') ?>
                                    </div>
                                </td>

                                <!-- Teslim Alan -->
                                <td style="padding: 12px 14px; color: #334155; white-space: nowrap;">
                                    <?= htmlspecialchars($r['receiver_name'] ?: ($r['receiver_username'] ?? '-')) ?>
                                </td>

                                <!-- İrsaliye No -->
                                <td style="padding: 12px 14px; color: #475569; font-family: monospace; white-space: nowrap;">
                                    <?= htmlspecialchars($r['delivery_note_no'] ?: '-') ?>
                                </td>

                                <!-- Teslim Miktarı -->
                                <td style="padding: 12px 14px; text-align: right; font-weight: 800; color: #16a34a; font-family: monospace; white-space: nowrap; font-size: 13.5px;">
                                    +<?= $formatNumber((float)$r['total_received_quantity'], 2) ?>
                                </td>

                                <!-- Durum -->
                                <td style="padding: 12px 14px; text-align: center; white-space: nowrap;">
                                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: <?= $stInfo['bg'] ?>; color: <?= $stInfo['color'] ?>; border: 1px solid <?= $stInfo['border'] ?>;">
                                        <span><?= $stInfo['icon'] ?></span> <?= htmlspecialchars($stInfo['label']) ?>
                                    </span>
                                </td>

                                <!-- Kalem Sayısı -->
                                <td style="padding: 12px 14px; text-align: center; white-space: nowrap;">
                                    <span style="display: inline-block; padding: 2px 8px; background: #f1f5f9; color: #334155; border-radius: 6px; font-weight: 700; font-size: 12px;">
                                        <?= (int)($r['item_count'] ?? 1) ?> kalem
                                    </span>
                                </td>

                                <!-- İşlem -->
                                <td style="padding: 12px 14px; text-align: right; white-space: nowrap;">
                                    <a href="/stok-takip/public/purchase-receipts/show?id=<?= (int)$r['id'] ?>" class="button button-small" style="padding: 4px 10px; font-size: 12px; text-decoration: none; background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; display: inline-flex; align-items: center; gap: 4px;">
                                        <span>👁️</span> Detay
                                    </a>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 5. SAYFALAMA (PAGINATION) -->
    <?php if ($totalPages > 1): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 12px 0;">
            <div style="font-size: 12.5px; color: #64748b;">
                Toplam <b><?= $totalCount ?></b> kayıttan <b><?= min($totalCount, $offset + 1) ?> - <?= min($totalCount, $offset + $limit) ?></b> arası gösteriliyor
            </div>
            <div style="display: flex; gap: 6px; align-items: center;">
                <?php if ($page > 1): ?>
                    <a href="/stok-takip/public/purchase-receipts?<?= $buildQuery(['page' => $page - 1]) ?>" class="button button-small" style="padding: 6px 12px; font-size: 12px; text-decoration: none; background: #fff; border: 1px solid #cbd5e1;">
                        &laquo; Önceki
                    </a>
                <?php endif; ?>

                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                    <a href="/stok-takip/public/purchase-receipts?<?= $buildQuery(['page' => $p]) ?>" class="button button-small" style="padding: 6px 12px; font-size: 12px; text-decoration: none; <?= $p === $page ? 'background: #16a34a; color: #fff; border-color: #16a34a;' : 'background: #fff; border: 1px solid #cbd5e1; color: #334155;' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="/stok-takip/public/purchase-receipts?<?= $buildQuery(['page' => $page + 1]) ?>" class="button button-small" style="padding: 6px 12px; font-size: 12px; text-decoration: none; background: #fff; border: 1px solid #cbd5e1;">
                        Sonraki &raquo;
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</main>
</body>
</html>

