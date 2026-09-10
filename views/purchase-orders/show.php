<?php
declare(strict_types=1);

$pageTitle = 'Satın Alma Siparişi: ' . ($order['order_no'] ?? '');
$activePage = 'purchase_orders';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';

$can = $can ?? static fn($p) => true;

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

// Durum Rozet ve Açıklama Haritası
$statusMap = [
    'DRAFT' => [
        'label'       => 'Taslak',
        'bg'          => '#f1f5f9',
        'color'       => '#475569',
        'border'      => '#cbd5e1',
        'icon'        => '📝',
        'banner_bg'   => '#f8fafc',
        'banner_bdr'  => '#e2e8f0',
        'banner_text' => 'Sipariş henüz tedarikçiye gönderilmedi. Taslak durumundaki sipariş üzerinde düzenlemeler yapabilir, iptal edebilir veya tedarikçiye iletebilirsiniz.',
    ],
    'SENT' => [
        'label'       => 'Gönderildi',
        'bg'          => '#e0f2fe',
        'color'       => '#0369a1',
        'border'      => '#bae6fd',
        'icon'        => '📤',
        'banner_bg'   => '#f0f9ff',
        'banner_bdr'  => '#bae6fd',
        'banner_text' => 'Sipariş tedarikçiye iletildi. Tedarikçiden teyit bekleniyor veya tedarikçi teyidi sisteme işlenebilir.',
    ],
    'CONFIRMED' => [
        'label'       => 'Onaylandı',
        'bg'          => '#eff6ff',
        'color'       => '#1d4ed8',
        'border'      => '#bfdbfe',
        'icon'        => '🤝',
        'banner_bg'   => '#f5f8ff',
        'banner_bdr'  => '#bfdbfe',
        'banner_text' => 'Sipariş tedarikçi tarafından teyit edildi. Malzemelerin fabrikaya sevkiyatı ve teslimatı bekleniyor.',
    ],
    'PARTIALLY_RECEIVED' => [
        'label'       => 'Kısmi Teslim',
        'bg'          => '#fef3c7',
        'color'       => '#92400e',
        'border'      => '#fde68a',
        'icon'        => '📦',
        'banner_bg'   => '#fffbeb',
        'banner_bdr'  => '#fef3c7',
        'banner_text' => 'Siparişteki malzemelerin bir kısmı depoya teslim alındı. Kalan miktarların sevkiyatı bekleniyor.',
    ],
    'RECEIVED' => [
        'label'       => 'Teslim Alındı',
        'bg'          => '#dcfce7',
        'color'       => '#166534',
        'border'      => '#bbf7d0',
        'icon'        => '✅',
        'banner_bg'   => '#f0fdf4',
        'banner_bdr'  => '#bbf7d0',
        'banner_text' => 'Siparişteki tüm malzeme kalemleri eksiksiz olarak depoya kabul edildi ve süreç tamamlandı.',
    ],
    'CANCELLED' => [
        'label'       => 'İptal Edildi',
        'bg'          => '#f3f4f6',
        'color'       => '#6b7280',
        'border'      => '#e5e7eb',
        'icon'        => '🚫',
        'banner_bg'   => '#f9fafb',
        'banner_bdr'  => '#e5e7eb',
        'banner_text' => 'Sipariş iptal edildi ve işlemden kaldırıldı.',
    ],
];

$currStatus = $order['status'] ?? 'DRAFT';
$stInfo = $statusMap[$currStatus] ?? $statusMap['DRAFT'];
$currency = $order['currency'] ?? 'TRY';
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

    <!-- 1. ÜST BAŞLIK VE AKSİYON BUTONLARI -->
    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="margin-bottom: 6px;">
                <a href="/stok-takip/public/purchase-orders" style="font-size: 12.5px; font-weight: 700; color: #3b82f6; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                    &larr; Siparişlere Dön
                </a>
            </div>
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <h1 style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em; font-family: monospace;">
                    <?= htmlspecialchars($order['order_no']) ?>
                </h1>
                <span style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 14px; font-size: 12.5px; font-weight: 800; background: <?= $stInfo['bg'] ?>; color: <?= $stInfo['color'] ?>; border: 1px solid <?= $stInfo['border'] ?>;">
                    <span><?= $stInfo['icon'] ?></span> <?= htmlspecialchars($stInfo['label']) ?>
                </span>
            </div>
            <p style="font-size: 13px; color: #64748b; margin: 6px 0 0 0;">
                Oluşturulma: <strong><?= $formatDateTime($order['created_at']) ?></strong> | Son Güncelleme: <strong><?= $formatDateTime($order['updated_at']) ?></strong>
            </p>
        </div>

        <!-- AKSİYON BUTONLARI -->
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <?php if ($currStatus === 'DRAFT'): ?>
                <?php if ($can('purchase.order.update')): ?>
                    <a href="/stok-takip/public/purchase-orders/edit?id=<?= (int)$order['id'] ?>" class="button button-secondary" style="padding: 8px 16px; font-size: 13px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; background: #fff; border: 1px solid #cbd5e1; color: #0f172a;">
                        <span>✏️</span> Düzenle
                    </a>
                <?php endif; ?>
                
                <?php if ($can('purchase.order.send')): ?>
                    <button type="button" onclick="openSendModal();" class="button button-primary" style="padding: 8px 18px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                        <span>📤</span> Tedarikçiye Gönder
                    </button>
                <?php endif; ?>

                <?php if ($can('purchase.order.cancel')): ?>
                    <button type="button" onclick="openCancelModal();" class="button" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 8px 14px; font-size: 13px; font-weight: 600; cursor: pointer; border-radius: 8px;">
                        <span>🚫</span> İptal Et
                    </button>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($currStatus === 'SENT'): ?>
                <?php if ($can('purchase.order.send')): ?>
                    <button type="button" onclick="openConfirmModal();" class="button" style="background: #16a34a; color: #fff; border: 1px solid #15803d; padding: 8px 18px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; border-radius: 8px;">
                        <span>🤝</span> Siparişi Teyit Et
                    </button>
                <?php endif; ?>

                <?php if ($can('purchase.order.cancel')): ?>
                    <button type="button" onclick="openCancelModal();" class="button" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 8px 14px; font-size: 13px; font-weight: 600; cursor: pointer; border-radius: 8px;">
                        <span>🚫</span> İptal Et
                    </button>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (in_array($currStatus, ['CONFIRMED', 'PARTIALLY_RECEIVED'], true)): ?>
                <?php if ($can('stock.in') || $can('purchase.order.create') || $can('purchase.order.send')): ?>
                    <a href="/stok-takip/public/purchase-receipts/create?purchase_order_id=<?= (int)$order['id'] ?>" class="button button-primary" style="padding: 8px 18px; font-size: 13px; font-weight: 700; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; background: #16a34a; color: #fff; border: 1px solid #15803d; border-radius: 8px;">
                        <span>📦</span> Mal Kabul / Teslim Al
                    </a>
                <?php endif; ?>
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
        
        <!-- 1. Sipariş Tarihi -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">Sipariş Tarihi</div>
            <div style="font-size: 15px; font-weight: 800; color: #0f172a;">
                <?= $formatDate($order['order_date'] ?? '') ?>
            </div>
            <div style="font-size: 11.5px; color: #94a3b8;">Siparişin verildiği tarih</div>
        </div>

        <!-- 2. Beklenen Teslim -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">Beklenen Teslim</div>
            <div style="font-size: 15px; font-weight: 800; color: #2563eb;">
                <?= $formatDate($order['expected_delivery_date'] ?? null) ?>
            </div>
            <div style="font-size: 11.5px; color: #94a3b8;">Depoya varış hedefi</div>
        </div>

        <!-- 3. Tedarikçi -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">Tedarikçi Firma</div>
            <div style="font-size: 15px; font-weight: 800; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($order['supplier_name'] ?? '-') ?>">
                <?= htmlspecialchars($order['supplier_name'] ?? '-') ?>
            </div>
            <div style="font-size: 11.5px; color: #94a3b8;"><?= htmlspecialchars($order['supplier_code'] ?? '') ?></div>
        </div>

        <!-- 4. Sipariş Toplamı -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.02); border-left: 3px solid #16a34a;">
            <div style="font-size: 11.5px; font-weight: 700; color: #16a34a; text-transform: uppercase; margin-bottom: 4px;">Sipariş Toplamı</div>
            <div style="font-size: 17px; font-weight: 900; color: #15803d;">
                <?= $formatMoney($order['total_amount'] ?? 0, $currency) ?>
            </div>
            <div style="font-size: 11.5px; color: #64748b;">KDV Dahil Tutar</div>
        </div>

    </div>

    <!-- 4. DETAY BİLGİLER (2 KOLONLU GRID: TEDARİKÇİ & SİPARİŞ BİLGİLERİ) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; margin-bottom: 24px;">
        
        <!-- TEDARİKÇİ BİLGİLERİ KARTI -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;">
                <span style="font-size: 16px;">🏢</span>
                <h3 style="font-size: 14px; font-weight: 800; color: #0f172a; margin: 0; text-transform: uppercase; letter-spacing: 0.03em;">
                    Tedarikçi Bilgileri
                </h3>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 10px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
                    <span style="color: #64748b; font-weight: 600; min-width: 110px;">Firma Adı:</span>
                    <strong style="color: #0f172a; text-align: right;"><?= htmlspecialchars($order['supplier_name'] ?? 'Belirtilmemiş') ?></strong>
                </div>
                
                <?php if (!empty($order['supplier_code'])): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                        <span style="color: #64748b; font-weight: 600; min-width: 110px;">Firma Kodu:</span>
                        <span style="font-family: monospace; font-weight: 700; color: #334155;"><?= htmlspecialchars($order['supplier_code']) ?></span>
                    </div>
                <?php endif; ?>

                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <span style="color: #64748b; font-weight: 600; min-width: 110px;">Yetkili Kişi:</span>
                    <span style="color: #0f172a;"><?= htmlspecialchars($order['supplier_contact'] ?? 'Belirtilmemiş') ?></span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <span style="color: #64748b; font-weight: 600; min-width: 110px;">Telefon:</span>
                    <span style="color: #0f172a;"><?= htmlspecialchars($order['supplier_phone'] ?? 'Belirtilmemiş') ?></span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <span style="color: #64748b; font-weight: 600; min-width: 110px;">E-posta:</span>
                    <?php if (!empty($order['supplier_email'])): ?>
                        <a href="mailto:<?= htmlspecialchars($order['supplier_email']) ?>" style="color: #2563eb; text-decoration: none;">
                            <?= htmlspecialchars($order['supplier_email']) ?>
                        </a>
                    <?php else: ?>
                        <span style="color: #94a3b8;">Belirtilmemiş</span>
                    <?php endif; ?>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
                    <span style="color: #64748b; font-weight: 600; min-width: 110px;">Adres:</span>
                    <span style="color: #0f172a; text-align: right;"><?= htmlspecialchars($order['supplier_address'] ?? 'Belirtilmemiş') ?></span>
                </div>
            </div>
        </div>

        <!-- SİPARİŞ BİLGİLERİ KARTI -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;">
                <span style="font-size: 16px;">📋</span>
                <h3 style="font-size: 14px; font-weight: 800; color: #0f172a; margin: 0; text-transform: uppercase; letter-spacing: 0.03em;">
                    Sipariş Bilgileri
                </h3>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 10px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <span style="color: #64748b; font-weight: 600; min-width: 130px;">Sipariş No:</span>
                    <strong style="font-family: monospace; color: #0f172a; font-size: 13.5px;"><?= htmlspecialchars($order['order_no']) ?></strong>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <span style="color: #64748b; font-weight: 600; min-width: 130px;">Satın Alma Talebi:</span>
                    <?php if (!empty($order['purchase_request_id']) && !empty($order['purchase_request_no'])): ?>
                        <a href="/stok-takip/public/purchase-requests/show?id=<?= (int)$order['purchase_request_id'] ?>" style="color: #2563eb; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                            <span>🔗</span> <?= htmlspecialchars($order['purchase_request_no']) ?>
                        </a>
                    <?php else: ?>
                        <span style="color: #94a3b8;">Bağlı satın alma talebi bulunmuyor.</span>
                    <?php endif; ?>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <span style="color: #64748b; font-weight: 600; min-width: 130px;">Siparişi Oluşturan:</span>
                    <span style="color: #0f172a; font-weight: 600;">
                        <?= htmlspecialchars($order['orderer_name'] ?: ($order['orderer_username'] ?? 'Belirtilmemiş')) ?>
                    </span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <span style="color: #64748b; font-weight: 600; min-width: 130px;">Para Birimi:</span>
                    <strong style="color: #0f172a;"><?= htmlspecialchars($currency) ?></strong>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <span style="color: #64748b; font-weight: 600; min-width: 130px;">Ödeme Koşulları:</span>
                    <span style="color: #0f172a;"><?= htmlspecialchars($order['payment_terms'] ?? 'Belirtilmemiş') ?></span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <span style="color: #64748b; font-weight: 600; min-width: 130px;">Teslimat Koşulları:</span>
                    <span style="color: #0f172a;"><?= htmlspecialchars($order['delivery_terms'] ?? 'Belirtilmemiş') ?></span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
                    <span style="color: #64748b; font-weight: 600; min-width: 130px;">Notlar:</span>
                    <span style="color: #0f172a; text-align: right;"><?= htmlspecialchars($order['notes'] ?? 'Belirtilmemiş') ?></span>
                </div>
            </div>
        </div>

    </div>

    <!-- 5. SİPARİŞ KALEMLERİ TABLOSU -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 18px;">📦</span>
                <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0;">
                    Sipariş Kalemleri
                </h3>
            </div>
            <span style="font-size: 12px; font-weight: 700; color: #64748b; background: #f1f5f9; padding: 4px 10px; border-radius: 6px;">
                Toplam <b><?= count($items) ?></b> Kalem
            </span>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.04em;">
                        <th style="padding: 10px 12px; width: 40px; text-align: center;">#</th>
                        <th style="padding: 10px 12px;">Malzeme Kodu &amp; Adı</th>
                        <th style="padding: 10px 12px; text-align: right;">Sipariş</th>
                        <th style="padding: 10px 12px; text-align: right;">Teslim Alınan</th>
                        <th style="padding: 10px 12px; text-align: right;">Kalan Miktar</th>
                        <th style="padding: 10px 12px; text-align: center; width: 70px;">Birim</th>
                        <th style="padding: 10px 12px; text-align: right;">Birim Fiyat</th>
                        <th style="padding: 10px 12px; text-align: center; width: 80px;">Vergi</th>
                        <th style="padding: 10px 12px; text-align: right;">Satır Tutarı</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="9" style="padding: 24px; text-align: center; color: #94a3b8; font-style: italic;">
                                Bu siparişe ait herhangi bir malzeme kalemi bulunamadı.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $idx => $item): 
                            $itemCurrency = $item['currency'] ?? $currency;
                            $unitPrice = (float)($item['unit_price'] ?? 0);
                            $orderedQty = (float)($item['ordered_quantity'] ?? 0);
                            $receivedQty = (float)($item['received_quantity'] ?? 0);
                            $remainingQty = max(0.0, $orderedQty - $receivedQty);
                            $lineTotal = (float)($item['line_total'] ?? ($orderedQty * $unitPrice));
                            $taxRate = (float)($item['tax_rate'] ?? 20.00);
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
                                    <?php if (!empty($item['notes'])): ?>
                                        <div style="font-size: 11.5px; color: #94a3b8; font-style: italic; margin-top: 2px;">
                                            💬 <?= htmlspecialchars($item['notes']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px; text-align: right; font-weight: 700; color: #0f172a; font-family: monospace;">
                                    <?= $formatNumber($orderedQty, 2) ?>
                                </td>
                                <td style="padding: 12px; text-align: right; font-weight: 700; color: <?= $receivedQty > 0 ? '#16a34a' : '#64748b' ?>; font-family: monospace;">
                                    <?= $formatNumber($receivedQty, 2) ?>
                                </td>
                                <td style="padding: 12px; text-align: right; font-weight: 800; color: <?= $remainingQty > 0 ? '#2563eb' : '#64748b' ?>; font-family: monospace;">
                                    <?= $formatNumber($remainingQty, 2) ?>
                                </td>
                                <td style="padding: 12px; text-align: center; color: #475569;">
                                    <span style="background: #f1f5f9; padding: 2px 8px; border-radius: 4px; font-size: 11.5px; font-weight: 600;">
                                        <?= htmlspecialchars($item['unit_name'] ?? ($item['unit_symbol'] ?? 'Adet')) ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; text-align: right; color: #334155; font-family: monospace;">
                                    <?= $formatMoney($unitPrice, $itemCurrency) ?>
                                </td>
                                <td style="padding: 12px; text-align: center; color: #64748b; font-size: 12px;">
                                    %<?= $formatNumber($taxRate, 0) ?>
                                </td>
                                <td style="padding: 12px; text-align: right; font-weight: 800; color: #0f172a; font-family: monospace;">
                                    <?= $formatMoney($lineTotal, $itemCurrency) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 6. TOPLAM BÖLÜMÜ (SAĞ ALT) -->
        <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end;">
            <div style="min-width: 280px; max-width: 360px; width: 100%; display: flex; flex-direction: column; gap: 8px; font-size: 13.5px;">
                <div style="display: flex; justify-content: space-between; align-items: center; color: #64748b;">
                    <span>Ara Toplam:</span>
                    <strong style="color: #334155; font-family: monospace; font-size: 14px;">
                        <?= $formatMoney($order['subtotal'] ?? 0, $currency) ?>
                    </strong>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; color: #64748b;">
                    <span>Vergi (%<?= $formatNumber((float)($order['tax_rate'] ?? 20.00), 0) ?>):</span>
                    <strong style="color: #334155; font-family: monospace; font-size: 14px;">
                        <?= $formatMoney($order['tax_amount'] ?? 0, $currency) ?>
                    </strong>
                </div>

                <div style="margin-top: 4px; padding-top: 8px; border-top: 2px solid #0f172a; display: flex; justify-content: space-between; align-items: center; font-size: 16px;">
                    <span style="font-weight: 800; color: #0f172a;">Genel Toplam:</span>
                    <strong style="font-weight: 900; color: #16a34a; font-family: monospace; font-size: 18px;">
                        <?= $formatMoney($order['total_amount'] ?? 0, $currency) ?>
                    </strong>
                </div>
            </div>
        </div>

    </div>

    <!-- 7. MAL KABUL & TESLİMAT GEÇMİŞİ -->
    <?php
    require_once __DIR__ . '/../../app/Models/PurchaseReceipt.php';
    $receiptModel = new PurchaseReceipt($GLOBALS['pdo'] ?? (new Database())->connect());
    $receipts = !empty($order['id']) ? $receiptModel->getByPurchaseOrderId((int)$order['id']) : [];
    ?>
    <?php if (!empty($receipts)): ?>
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 18px;">🚚</span>
                    <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0;">
                        Mal Kabul &amp; Teslimat Geçmişi
                    </h3>
                </div>
                <span style="font-size: 12px; font-weight: 700; color: #16a34a; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 4px 10px; border-radius: 6px;">
                    Toplam <b><?= count($receipts) ?></b> Teslimat
                </span>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.04em;">
                            <th style="padding: 10px 12px;">Makbuz No</th>
                            <th style="padding: 10px 12px;">Teslim Tarihi</th>
                            <th style="padding: 10px 12px;">Depo &amp; Lokasyon</th>
                            <th style="padding: 10px 12px;">Teslim Alan</th>
                            <th style="padding: 10px 12px;">İrsaliye No</th>
                            <th style="padding: 10px 12px; text-align: right;">Teslim Miktarı</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($receipts as $rec): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 12px; font-family: monospace; font-weight: 800; color: #2563eb;">
                                    <?= htmlspecialchars($rec['receipt_no']) ?>
                                </td>
                                <td style="padding: 12px; color: #334155;">
                                    <?= $formatDate($rec['receipt_date']) ?>
                                </td>
                                <td style="padding: 12px;">
                                    <div style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($rec['warehouse_name'] ?? '-') ?></div>
                                    <div style="font-size: 11.5px; color: #64748b;"><?= htmlspecialchars($rec['location_name'] ?? '-') ?></div>
                                </td>
                                <td style="padding: 12px; color: #334155;">
                                    <?= htmlspecialchars($rec['receiver_name'] ?: ($rec['receiver_username'] ?? '-')) ?>
                                </td>
                                <td style="padding: 12px; color: #64748b; font-family: monospace;">
                                    <?= htmlspecialchars($rec['delivery_note_no'] ?: '-') ?>
                                </td>
                                <td style="padding: 12px; text-align: right; font-weight: 800; color: #16a34a; font-family: monospace; font-size: 14px;">
                                    +<?= $formatNumber((float)($rec['total_received_quantity'] ?? 0), 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</main>

<!-- ========================================== -->
<!-- MODALLAR & CSRF KORUMALI AKSİYON FORMLARI -->
<!-- ========================================== -->

<!-- 1. GÖNDER MODAL -->
<?php if ($currStatus === 'DRAFT' && $can('purchase.order.send')): ?>
<div id="sendModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div style="background: #ffffff; border-radius: 14px; width: 100%; max-width: 460px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); margin: 16px;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px;">
            <span style="font-size: 24px;">📤</span>
            <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0;">Siparişi Tedarikçiye Gönder</h3>
        </div>
        <p style="font-size: 13.5px; color: #475569; margin: 0 0 20px 0; line-height: 1.5;">
            <strong><?= htmlspecialchars($order['order_no']) ?></strong> numaralı sipariş tedarikçiye iletildi olarak işaretlenecek ve durumu <strong>GÖNDERİLDİ (SENT)</strong> olarak güncellenecektir. Onaylıyor musunuz?
        </p>
        <form method="POST" action="/stok-takip/public/purchase-orders/send" style="margin: 0;">
            <?= CsrfService::tokenField() ?>
            <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeSendModal();" class="button button-secondary" style="padding: 8px 16px; font-size: 13px; font-weight: 600; cursor: pointer; border-radius: 8px;">
                    Vazgeç
                </button>
                <button type="submit" class="button button-primary" style="padding: 8px 18px; font-size: 13px; font-weight: 700; cursor: pointer; border-radius: 8px; background: #2563eb; color: #fff;">
                    Evet, Gönder
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- 2. TEYİT ET MODAL -->
<?php if ($currStatus === 'SENT' && $can('purchase.order.send')): ?>
<div id="confirmModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div style="background: #ffffff; border-radius: 14px; width: 100%; max-width: 460px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); margin: 16px;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px;">
            <span style="font-size: 24px;">🤝</span>
            <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0;">Sipariş Teyidini Kaydet</h3>
        </div>
        <p style="font-size: 13.5px; color: #475569; margin: 0 0 20px 0; line-height: 1.5;">
            Tedarikçinin siparişi onayladığı teyit edilecek ve sipariş durumu <strong>ONAYLANDI (CONFIRMED)</strong> olacaktır. Devam etmek istiyor musunuz?
        </p>
        <form method="POST" action="/stok-takip/public/purchase-orders/confirm" style="margin: 0;">
            <?= CsrfService::tokenField() ?>
            <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeConfirmModal();" class="button button-secondary" style="padding: 8px 16px; font-size: 13px; font-weight: 600; cursor: pointer; border-radius: 8px;">
                    Vazgeç
                </button>
                <button type="submit" class="button" style="padding: 8px 18px; font-size: 13px; font-weight: 700; cursor: pointer; border-radius: 8px; background: #16a34a; color: #fff; border: 1px solid #15803d;">
                    Evet, Teyit Edildi
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- 3. İPTAL ET MODAL -->
<?php if (($currStatus === 'DRAFT' || $currStatus === 'SENT') && $can('purchase.order.cancel')): ?>
<div id="cancelModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div style="background: #ffffff; border-radius: 14px; width: 100%; max-width: 480px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); margin: 16px;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px;">
            <span style="font-size: 24px;">🚫</span>
            <h3 style="font-size: 16px; font-weight: 800; color: #991b1b; margin: 0;">Siparişi İptal Et</h3>
        </div>
        <p style="font-size: 13px; color: #475569; margin: 0 0 16px 0;">
            Bu siparişi iptal etmek istediğinize emin misiniz? İptal edilen siparişler üzerinde tekrar işlem yapılamaz.
        </p>
        <form method="POST" action="/stok-takip/public/purchase-orders/cancel" style="margin: 0;">
            <?= CsrfService::tokenField() ?>
            <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
            
            <div style="margin-bottom: 18px;">
                <label for="cancel_reason" style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    İptal Nedeni / Gerekçesi (İsteğe Bağlı)
                </label>
                <textarea name="reason" id="cancel_reason" rows="3" placeholder="Siparişin iptal edilme gerekçesini belirtin..." style="width: 100%; padding: 10px 12px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 8px; resize: vertical; box-sizing: border-box; font-family: inherit;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeCancelModal();" class="button button-secondary" style="padding: 8px 16px; font-size: 13px; font-weight: 600; cursor: pointer; border-radius: 8px;">
                    Vazgeç
                </button>
                <button type="submit" class="button" style="padding: 8px 18px; font-size: 13px; font-weight: 700; cursor: pointer; border-radius: 8px; background: #dc2626; color: #fff; border: 1px solid #b91c1c;">
                    Siparişi İptal Et
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function openSendModal() {
    var m = document.getElementById('sendModal');
    if (m) m.style.display = 'flex';
}
function closeSendModal() {
    var m = document.getElementById('sendModal');
    if (m) m.style.display = 'none';
}

function openConfirmModal() {
    var m = document.getElementById('confirmModal');
    if (m) m.style.display = 'flex';
}
function closeConfirmModal() {
    var m = document.getElementById('confirmModal');
    if (m) m.style.display = 'none';
}

function openCancelModal() {
    var m = document.getElementById('cancelModal');
    if (m) m.style.display = 'flex';
}
function closeCancelModal() {
    var m = document.getElementById('cancelModal');
    if (m) m.style.display = 'none';
}

window.addEventListener('click', function(e) {
    var sendM = document.getElementById('sendModal');
    var confM = document.getElementById('confirmModal');
    var cancM = document.getElementById('cancelModal');
    if (e.target === sendM) closeSendModal();
    if (e.target === confM) closeConfirmModal();
    if (e.target === cancM) closeCancelModal();
});
</script>

