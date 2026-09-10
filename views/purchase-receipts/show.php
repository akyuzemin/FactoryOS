<?php
declare(strict_types=1);

$pageTitle = 'Mal Kabul Detayı: ' . ($receipt['receipt_no'] ?? '');
$activePage = 'purchase_receipts';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';

$formatDate = static function (?string $date): string {
    if (empty($date)) {
        return 'Belirtilmemiş';
    }
    $ts = strtotime($date);
    return $ts ? date('d.m.Y', $ts) : $date;
};

$formatDateTime = static function (?string $date): string {
    if (empty($date)) {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date('d.m.Y H:i', $ts) : $date;
};

$formatMoney = static function (float|int|string $amount, string $currency = 'TRY'): string {
    return number_format((float)$amount, 2, ',', '.') . ' ' . htmlspecialchars($currency);
};

$formatNumber = static function (float|int|string $num, int $decimals = 2): string {
    return number_format((float)$num, $decimals, ',', '.');
};

// Durum Haritası
$statusMap = [
    'COMPLETED' => [
        'label'       => 'Tamamlandı',
        'bg'          => '#dcfce7',
        'color'       => '#166534',
        'border'      => '#bbf7d0',
        'icon'        => '✅',
        'banner_bg'   => '#f0fdf4',
        'banner_bdr'  => '#bbf7d0',
        'banner_text' => 'Mal kabul işlemi eksiksiz tamamlanmış, depoya fiziksel giriş yapılarak stok bakiyesi artırılmış ve stok hareket kaydı (IN) oluşturulmuştur.',
    ],
    'CANCELLED' => [
        'label'       => 'İptal Edildi',
        'bg'          => '#f3f4f6',
        'color'       => '#6b7280',
        'border'      => '#e5e7eb',
        'icon'        => '🚫',
        'banner_bg'   => '#f9fafb',
        'banner_bdr'  => '#e5e7eb',
        'banner_text' => 'Bu mal kabul makbuzu iptal edilmiştir.',
    ],
];

$currStatus = $receipt['status'] ?? 'COMPLETED';
$stInfo = $statusMap[$currStatus] ?? $statusMap['COMPLETED'];

// Toplam kabul edilen miktar hesabı
$totalAcceptedQty = 0.0;
foreach ($items as $item) {
    $totalAcceptedQty += (float)($item['accepted_quantity'] ?? $item['received_quantity'] ?? 0);
}
?>

<main class="main-content">

    <!-- FLASH BİLDİRİM MESAJLARI -->
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

    <!-- 1. ÜST BAŞLIK VE GERİ BUTONU -->
    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="margin-bottom: 6px; display: flex; gap: 14px; align-items: center;">
                <a href="/stok-takip/public/purchase-receipts" style="font-size: 12.5px; font-weight: 700; color: #16a34a; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                    &larr; Mal Kabullere Dön
                </a>
                <?php if (!empty($receipt['purchase_order_id'])): ?>
                    <span style="color: #cbd5e1;">|</span>
                    <a href="/stok-takip/public/purchase-orders/show?id=<?= (int)$receipt['purchase_order_id'] ?>" style="font-size: 12.5px; font-weight: 700; color: #2563eb; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                        <span>🛒</span> Siparişe Git (<?= htmlspecialchars($receipt['order_no'] ?? '') ?>)
                    </a>
                <?php endif; ?>
            </div>
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <h1 style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em; font-family: monospace;">
                    <?= htmlspecialchars($receipt['receipt_no']) ?>
                </h1>
                <span style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 14px; font-size: 12.5px; font-weight: 800; background: <?= $stInfo['bg'] ?>; color: <?= $stInfo['color'] ?>; border: 1px solid <?= $stInfo['border'] ?>;">
                    <span><?= $stInfo['icon'] ?></span> <?= htmlspecialchars($stInfo['label']) ?>
                </span>
            </div>
            <p style="font-size: 13px; color: #64748b; margin: 6px 0 0 0;">
                Kayıt Tarihi: <strong><?= $formatDateTime($receipt['created_at']) ?></strong> | Son Güncelleme: <strong><?= $formatDateTime($receipt['updated_at']) ?></strong>
            </p>
        </div>

        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <a href="/stok-takip/public/stock-movements" class="button button-secondary" style="padding: 8px 16px; font-size: 13px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; background: #fff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px;">
                <span>📦</span> Stok Hareketleri
            </a>
            <?php if (!empty($receipt['purchase_order_id'])): ?>
                <a href="/stok-takip/public/purchase-orders/show?id=<?= (int)$receipt['purchase_order_id'] ?>" class="button button-primary" style="padding: 8px 18px; font-size: 13px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; background: #2563eb; color: #fff; border-radius: 8px;">
                    <span>🛒</span> Sipariş Detayı
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. DURUM BİLGİ BANDI -->
    <div style="background: <?= $stInfo['banner_bg'] ?>; border: 1px solid <?= $stInfo['banner_bdr'] ?>; border-left: 4px solid <?= $stInfo['color'] ?>; border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
        <div style="font-size: 20px;"><?= $stInfo['icon'] ?></div>
        <div style="font-size: 13.5px; color: <?= $stInfo['color'] ?>; font-weight: 600;">
            <?= htmlspecialchars($stInfo['banner_text']) ?>
        </div>
    </div>

    <!-- 3. 4 ÖZET KARTI (GRID) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 20px;">
        
        <!-- 1. Teslim Tarihi -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">Teslim Tarihi</div>
            <div style="font-size: 15px; font-weight: 800; color: #0f172a;">
                <?= $formatDate($receipt['receipt_date'] ?? '') ?>
            </div>
            <div style="font-size: 11.5px; color: #94a3b8;">Fiziksel kabul tarihi</div>
        </div>

        <!-- 2. Satın Alma Siparişi -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">Sipariş Numarası</div>
            <div style="font-size: 15px; font-weight: 800; color: #2563eb; font-family: monospace;">
                <a href="/stok-takip/public/purchase-orders/show?id=<?= (int)$receipt['purchase_order_id'] ?>" style="color: #2563eb; text-decoration: none;">
                    <?= htmlspecialchars($receipt['order_no'] ?? '-') ?>
                </a>
            </div>
            <div style="font-size: 11.5px; color: #94a3b8;">Bağlı satın alma emri</div>
        </div>

        <!-- 3. Tedarikçi -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">Tedarikçi Firma</div>
            <div style="font-size: 15px; font-weight: 800; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($receipt['supplier_name'] ?? '-') ?>">
                <?= htmlspecialchars($receipt['supplier_name'] ?? '-') ?>
            </div>
            <div style="font-size: 11.5px; color: #94a3b8;">Gönderici şirket</div>
        </div>

        <!-- 4. Teslim Miktarı -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.02); border-left: 3px solid #16a34a;">
            <div style="font-size: 11.5px; font-weight: 700; color: #16a34a; text-transform: uppercase; margin-bottom: 4px;">Toplam Teslim</div>
            <div style="font-size: 18px; font-weight: 900; color: #15803d; font-family: monospace;">
                +<?= $formatNumber($totalAcceptedQty, 2) ?>
            </div>
            <div style="font-size: 11.5px; color: #64748b;">Depoya eklenen miktar</div>
        </div>

    </div>

    <!-- 4. DETAY BİLGİ KARTLARI (3 KOLONLU GRID) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 24px;">
        
        <!-- KART 1: SİPARİŞ VE TEDARİKÇİ -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;">
                <span style="font-size: 16px;">🛒</span>
                <h3 style="font-size: 14px; font-weight: 800; color: #0f172a; margin: 0; text-transform: uppercase; letter-spacing: 0.03em;">
                    Sipariş Bilgileri
                </h3>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 10px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <span style="color: #64748b; font-weight: 600;">Sipariş No:</span>
                    <a href="/stok-takip/public/purchase-orders/show?id=<?= (int)$receipt['purchase_order_id'] ?>" style="font-family: monospace; font-weight: 700; color: #2563eb; text-decoration: none;">
                        <?= htmlspecialchars($receipt['order_no'] ?? '-') ?>
                    </a>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
                    <span style="color: #64748b; font-weight: 600;">Tedarikçi:</span>
                    <strong style="color: #0f172a; text-align: right;"><?= htmlspecialchars($receipt['supplier_name'] ?? 'Belirtilmemiş') ?></strong>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <span style="color: #64748b; font-weight: 600;">PO Durumu:</span>
                    <span style="background: #f1f5f9; padding: 2px 8px; border-radius: 6px; font-size: 11.5px; font-weight: 700; color: #334155;">
                        <?= htmlspecialchars($receipt['po_status'] ?? '-') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- KART 2: DEPO VE TESLİMAT -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;">
                <span style="font-size: 16px;">🏢</span>
                <h3 style="font-size: 14px; font-weight: 800; color: #0f172a; margin: 0; text-transform: uppercase; letter-spacing: 0.03em;">
                    Depo &amp; Personel
                </h3>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 10px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <span style="color: #64748b; font-weight: 600;">Hedef Depo:</span>
                    <strong style="color: #0f172a;"><?= htmlspecialchars($receipt['warehouse_name'] ?? '-') ?></strong>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <span style="color: #64748b; font-weight: 600;">Raf / Lokasyon:</span>
                    <span style="color: #2563eb; font-weight: 700;"><?= htmlspecialchars($receipt['location_name'] ?? '-') ?></span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <span style="color: #64748b; font-weight: 600;">Teslim Alan:</span>
                    <span style="color: #0f172a; font-weight: 600;">
                        <?= htmlspecialchars($receipt['receiver_name'] ?: ($receipt['receiver_username'] ?? 'Belirtilmemiş')) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- KART 3: BELGE VE İRSALİYE -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;">
                <span style="font-size: 16px;">📄</span>
                <h3 style="font-size: 14px; font-weight: 800; color: #0f172a; margin: 0; text-transform: uppercase; letter-spacing: 0.03em;">
                    Belge &amp; İrsaliye
                </h3>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 10px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <span style="color: #64748b; font-weight: 600;">İrsaliye No:</span>
                    <strong style="font-family: monospace; color: #0f172a;"><?= htmlspecialchars($receipt['delivery_note_no'] ?: 'Belirtilmemiş') ?></strong>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <span style="color: #64748b; font-weight: 600;">Tedarikçi Belge No:</span>
                    <span style="font-family: monospace; color: #334155;"><?= htmlspecialchars($receipt['supplier_document_no'] ?: 'Belirtilmemiş') ?></span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
                    <span style="color: #64748b; font-weight: 600;">Notlar:</span>
                    <span style="color: #0f172a; text-align: right;"><?= htmlspecialchars($receipt['notes'] ?: '-') ?></span>
                </div>
            </div>
        </div>

    </div>

    <!-- 5. MAL KABUL KALEMLERİ VE STOK HAREKETİ ENTEGRASYONU -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 18px;">📦</span>
                <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0;">
                    Mal Kabul Kalemleri &amp; Stok Hareketi
                </h3>
            </div>
            <span style="font-size: 12px; font-weight: 700; color: #16a34a; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 4px 10px; border-radius: 6px;">
                Toplam <b><?= count($items) ?></b> Kalem
            </span>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.04em;">
                        <th style="padding: 10px 12px; width: 40px; text-align: center;">#</th>
                        <th style="padding: 10px 12px;">Malzeme Kodu &amp; Adı</th>
                        <th style="padding: 10px 12px; text-align: right;">Sipariş Edilen</th>
                        <th style="padding: 10px 12px; text-align: right;">Teslim Alınan</th>
                        <th style="padding: 10px 12px; text-align: right;">Kabul Edilen</th>
                        <th style="padding: 10px 12px; text-align: right;">Reddedilen</th>
                        <th style="padding: 10px 12px; text-align: center; width: 70px;">Birim</th>
                        <th style="padding: 10px 12px; text-align: right;">Birim Fiyat</th>
                        <th style="padding: 10px 12px; text-align: center;">Stok Hareketi</th>
                        <th style="padding: 10px 12px;">Not / Açıklama</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="10" style="padding: 24px; text-align: center; color: #94a3b8; font-style: italic;">
                                Bu makbuza ait kalem kaydı bulunamadı.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $idx => $item): 
                            $orderedQty = (float)($item['ordered_quantity'] ?? 0);
                            $recQty = (float)($item['received_quantity'] ?? 0);
                            $accQty = (float)($item['accepted_quantity'] ?? $recQty);
                            $rejQty = (float)($item['rejected_quantity'] ?? 0);
                            $unitPrice = (float)($item['unit_price'] ?? 0);
                            $itemCur = $item['currency'] ?? 'TRY';
                            $smId = (int)($item['stock_movement_id'] ?? 0);
                        ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 12px; text-align: center; color: #94a3b8; font-weight: 700;">
                                    <?= $idx + 1 ?>
                                </td>
                                <td style="padding: 12px;">
                                    <div style="font-weight: 700; color: #0f172a;">
                                        <?= htmlspecialchars($item['material_name'] ?? 'Bilinmeyen Malzeme') ?>
                                    </div>
                                    <?php if (!empty($item['material_code'])): ?>
                                        <div style="font-size: 11.5px; font-family: monospace; color: #64748b; margin-top: 2px;">
                                            <?= htmlspecialchars($item['material_code']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px; text-align: right; font-weight: 600; color: #475569; font-family: monospace;">
                                    <?= $formatNumber($orderedQty, 2) ?>
                                </td>
                                <td style="padding: 12px; text-align: right; font-weight: 800; color: #0f172a; font-family: monospace; font-size: 13.5px;">
                                    <?= $formatNumber($recQty, 2) ?>
                                </td>
                                <td style="padding: 12px; text-align: right; font-weight: 800; color: #16a34a; font-family: monospace;">
                                    <?= $formatNumber($accQty, 2) ?>
                                </td>
                                <td style="padding: 12px; text-align: right; font-weight: 600; color: <?= $rejQty > 0 ? '#dc2626' : '#94a3b8' ?>; font-family: monospace;">
                                    <?= $formatNumber($rejQty, 2) ?>
                                </td>
                                <td style="padding: 12px; text-align: center; color: #475569;">
                                    <span style="background: #f1f5f9; padding: 2px 8px; border-radius: 4px; font-size: 11.5px; font-weight: 600;">
                                        <?= htmlspecialchars($item['unit_symbol'] ?? 'Adet') ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; text-align: right; color: #334155; font-family: monospace;">
                                    <?= $formatMoney($unitPrice, $itemCur) ?>
                                </td>
                                <td style="padding: 12px; text-align: center; white-space: nowrap;">
                                    <?php if ($smId > 0): ?>
                                        <a href="/stok-takip/public/stock-movements" style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; border-radius: 6px; font-size: 11.5px; font-weight: 700; text-decoration: none;">
                                            <span>📥</span> Hareket #<?= $smId ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px; color: #64748b; font-size: 12px;">
                                    <?= htmlspecialchars($item['notes'] ?: '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
</body>
</html>

