<?php
declare(strict_types=1);

$pageTitle = 'Satın Alma Talebi: ' . ($request['request_no'] ?? '');
$activePage = 'purchase_requests';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';

$formatDate = static function (?string $date): string {
    if (empty($date)) {
        return '-';
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

$formatNumber = static function (float $num, int $decimals = 2): string {
    return number_format($num, $decimals, ',', '.');
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
        'banner_text' => 'Talep henüz onaya gönderilmedi. Taslak durumundaki talebi düzenleyebilir veya inceledikten sonra onaya gönderebilirsiniz.',
    ],
    'SUBMITTED' => [
        'label'       => 'Onay Bekliyor',
        'bg'          => '#fef3c7',
        'color'       => '#92400e',
        'border'      => '#fde68a',
        'icon'        => '⏳',
        'banner_bg'   => '#fffbeb',
        'banner_bdr'  => '#fef3c7',
        'banner_text' => 'Talep yönetici onayı bekliyor. Yetkili yöneticiler tarafından incelenerek onaylanabilir veya gerekçe belirtilerek reddedilebilir.',
    ],
    'APPROVED' => [
        'label'       => 'Onaylandı',
        'bg'          => '#dcfce7',
        'color'       => '#166534',
        'border'      => '#bbf7d0',
        'icon'        => '✅',
        'banner_bg'   => '#f0fdf4',
        'banner_bdr'  => '#bbf7d0',
        'banner_text' => 'Talep onaylandı ve satın alma sürecine hazır. Tedarikçi siparişi açılabilir.',
    ],
    'REJECTED' => [
        'label'       => 'Reddedildi',
        'bg'          => '#fee2e2',
        'color'       => '#991b1b',
        'border'      => '#fecaca',
        'icon'        => '❌',
        'banner_bg'   => '#fef2f2',
        'banner_bdr'  => '#fecaca',
        'banner_text' => 'Talep yetkili tarafından reddedildi. Red gerekçesi aşağıdaki onay/ret kartında yer almaktadır.',
    ],
    'ORDERED' => [
        'label'       => 'Siparişleşti',
        'bg'          => '#e0e7ff',
        'color'       => '#3730a3',
        'border'      => '#c7d2fe',
        'icon'        => '🛒',
        'banner_bg'   => '#eef2ff',
        'banner_bdr'  => '#c7d2fe',
        'banner_text' => 'Talep satın alma siparişine dönüştürüldü.',
    ],
    'CANCELLED' => [
        'label'       => 'İptal Edildi',
        'bg'          => '#f3f4f6',
        'color'       => '#6b7280',
        'border'      => '#e5e7eb',
        'icon'        => '🚫',
        'banner_bg'   => '#f9fafb',
        'banner_bdr'  => '#e5e7eb',
        'banner_text' => 'Talep iptal edildi ve işlemden kaldırıldı.',
    ],
];

$priorityMap = [
    'LOW'    => ['label' => 'Düşük Öncelik',  'bg' => '#f0fdf4', 'color' => '#166534', 'border' => '#bbf7d0'],
    'MEDIUM' => ['label' => 'Normal Öncelik', 'bg' => '#eff6ff', 'color' => '#1e40af', 'border' => '#bfdbfe'],
    'HIGH'   => ['label' => 'Yüksek Öncelik', 'bg' => '#fff7ed', 'color' => '#c2410c', 'border' => '#fed7aa'],
    'URGENT' => ['label' => 'Acil Öncelik',   'bg' => '#fef2f2', 'color' => '#991b1b', 'border' => '#fecaca'],
];

$currStatus = $request['status'] ?? 'DRAFT';
$stInfo = $statusMap[$currStatus] ?? $statusMap['DRAFT'];
$prInfo = $priorityMap[$request['priority'] ?? 'MEDIUM'] ?? $priorityMap['MEDIUM'];

// Para birimlerine göre toplamları hesapla
$currencyTotals = [];
foreach ($items as $item) {
    $curr = $item['currency'] ?? 'TL';
    if (!isset($currencyTotals[$curr])) {
        $currencyTotals[$curr] = 0.0;
    }
    if ($item['estimated_unit_price'] !== null && $item['estimated_unit_price'] !== '') {
        $currencyTotals[$curr] += ((float)$item['requested_quantity'] * (float)$item['estimated_unit_price']);
    }
}
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

    <!-- 1. ÜST BAŞLIK VE AKSİYONLAR -->
    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="margin-bottom: 6px;">
                <a href="/stok-takip/public/purchase-requests" style="font-size: 12.5px; font-weight: 700; color: #3b82f6; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                    &larr; Satın Alma Taleplerine Dön
                </a>
            </div>
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <h1 style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em; font-family: monospace;">
                    <?= htmlspecialchars($request['request_no']) ?>
                </h1>
                <span style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 14px; font-size: 12.5px; font-weight: 800; background: <?= $stInfo['bg'] ?>; color: <?= $stInfo['color'] ?>; border: 1px solid <?= $stInfo['border'] ?>;">
                    <span><?= $stInfo['icon'] ?></span> <?= htmlspecialchars($stInfo['label']) ?>
                </span>
                <span style="display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 14px; font-size: 12px; font-weight: 700; background: <?= $prInfo['bg'] ?>; color: <?= $prInfo['color'] ?>; border: 1px solid <?= $prInfo['border'] ?>;">
                    <?= htmlspecialchars($prInfo['label']) ?>
                </span>
            </div>
            <p style="font-size: 13px; color: #64748b; margin: 6px 0 0 0;">
                Oluşturulma: <strong><?= $formatDateTime($request['created_at']) ?></strong> | Son Güncelleme: <strong><?= $formatDateTime($request['updated_at']) ?></strong>
            </p>
        </div>

        <!-- ÜST BUTONLAR -->
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <?php if ($currStatus === 'DRAFT' && $can('purchase.request')): ?>
                <a href="/stok-takip/public/purchase-requests/edit?id=<?= (int)$request['id'] ?>" class="button button-secondary" style="padding: 8px 16px; font-size: 13px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; background: #fff; border: 1px solid #cbd5e1; color: #0f172a;">
                    <span>✏️</span> Düzenle
                </a>
                <button type="button" onclick="openSubmitModal();" class="button button-primary" style="padding: 8px 18px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                    <span>🚀</span> Onaya Gönder
                </button>
            <?php endif; ?>

            <?php if ($currStatus === 'SUBMITTED' && $can('purchase.approve')): ?>
                <button type="button" onclick="openApproveModal();" class="button" style="background: #16a34a; color: #fff; border: 1px solid #15803d; padding: 8px 18px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; border-radius: 8px;">
                    <span>✓</span> Talebi Onayla
                </button>
                <button type="button" onclick="openRejectModal();" class="button" style="background: #dc2626; color: #fff; border: 1px solid #b91c1c; padding: 8px 18px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; border-radius: 8px;">
                    <span>✕</span> Talebi Reddet
                </button>
            <?php endif; ?>

            <?php if ($currStatus === 'APPROVED' && $can('purchase.order.create')): ?>
                <button type="button" onclick="openCreatePoModal();" class="button button-primary" style="padding: 8px 18px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; background: #2563eb; color: #fff; border-radius: 8px;">
                    <span>📦</span> Sipariş Oluştur (PO)
                </button>
            <?php endif; ?>

            <?php if (in_array($currStatus, ['DRAFT', 'SUBMITTED'], true) && $can('purchase.request')): ?>
                <button type="button" onclick="openCancelModal();" class="button" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 8px 14px; font-size: 13px; font-weight: 600; cursor: pointer; border-radius: 8px;">
                    <span>🚫</span> İptal Et
                </button>
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

    <!-- 3. ÖZET BİLGİ KARTLARI (GRID) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 20px;">
        
        <!-- Departman -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">Talep Eden Departman</div>
            <div style="font-size: 15px; font-weight: 800; color: #0f172a;">
                <?= htmlspecialchars($request['department_name'] ?? '-') ?>
            </div>
            <div style="font-size: 11.5px; color: #94a3b8;"><?= htmlspecialchars($request['department_code'] ?? '') ?></div>
        </div>

        <!-- Talep Eden -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">Talep Eden Personel</div>
            <div style="font-size: 15px; font-weight: 800; color: #0f172a;">
                <?= htmlspecialchars($request['requester_name'] ?? ($request['requester_username'] ?? 'Belirtilmemiş')) ?>
            </div>
            <div style="font-size: 11.5px; color: #94a3b8;"><?= htmlspecialchars($request['requester_email'] ?? '') ?></div>
        </div>

        <!-- Talep Tarihi -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">Talep Tarihi</div>
            <div style="font-size: 15px; font-weight: 800; color: #0f172a;">
                <?= $formatDate($request['request_date']) ?>
            </div>
            <div style="font-size: 11.5px; color: #94a3b8;">Talep giriş tarihi</div>
        </div>

        <!-- İhtiyaç Tarihi -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">İhtiyaç Duyulan Tarih</div>
            <div style="font-size: 15px; font-weight: 800; color: #2563eb;">
                <?= $formatDate($request['required_date']) ?>
            </div>
            <div style="font-size: 11.5px; color: #94a3b8;">Fabrikaya teslim hedefi</div>
        </div>

    </div>

    <!-- 4. ONAY / RET BİLGİSİ KARTI (Varsa) -->
    <?php if (!empty($request['approved_by']) || in_array($currStatus, ['APPROVED', 'REJECTED'], true)): ?>
        <div style="background: <?= $currStatus === 'REJECTED' ? '#fef2f2' : '#f0fdf4' ?>; border: 1px solid <?= $currStatus === 'REJECTED' ? '#fecaca' : '#bbf7d0' ?>; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px;">
            <h3 style="font-size: 14px; font-weight: 800; color: <?= $currStatus === 'REJECTED' ? '#991b1b' : '#166534' ?>; margin: 0 0 10px 0; display: flex; align-items: center; gap: 6px;">
                <span><?= $currStatus === 'REJECTED' ? '❌ Ret Bilgileri' : '✅ Onay Bilgileri' ?></span>
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; font-size: 13px;">
                <div>
                    <span style="color: #64748b; font-weight: 600;"><?= $currStatus === 'REJECTED' ? 'Reddeden:' : 'Onaylayan:' ?></span>
                    <strong style="color: #0f172a; margin-left: 4px;"><?= htmlspecialchars($request['approver_name'] ?: ($request['approver_username'] ?? '-')) ?></strong>
                </div>
                <div>
                    <span style="color: #64748b; font-weight: 600;">İşlem Tarihi:</span>
                    <strong style="color: #0f172a; margin-left: 4px;"><?= $formatDateTime($request['approved_at']) ?></strong>
                </div>
            </div>
            <?php if (!empty($request['approval_notes'])): ?>
                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed <?= $currStatus === 'REJECTED' ? '#fca5a5' : '#86efac' ?>; font-size: 13px;">
                    <span style="font-weight: 700; color: <?= $currStatus === 'REJECTED' ? '#991b1b' : '#166534' ?>;">
                        <?= $currStatus === 'REJECTED' ? 'Ret Gerekçesi:' : 'Onay Notu:' ?>
                    </span>
                    <p style="margin: 4px 0 0 0; color: #334155; white-space: pre-wrap; font-style: italic;">
                        <?= htmlspecialchars($request['approval_notes']) ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- 5. GENEL TALEP GEREKÇESİ / AÇIKLAMA -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <h3 style="font-size: 14px; font-weight: 800; color: #0f172a; margin: 0 0 8px 0; display: flex; align-items: center; gap: 6px;">
            <span>📄</span> Talep Gerekçesi &amp; Genel Açıklama
        </h3>
        <p style="font-size: 13.5px; color: <?= !empty($request['description']) ? '#334155' : '#94a3b8' ?>; margin: 0; line-height: 1.5; white-space: pre-wrap;">
            <?= !empty($request['description']) ? htmlspecialchars($request['description']) : 'Açıklama belirtilmemiş.' ?>
        </p>
    </div>

    <!-- 6. TALEP KALEMLERİ TABLOSU -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 24px;">
        <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
                <h2 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <span>📦</span> Talep Kalemleri
                </h2>
                <p style="font-size: 12.5px; color: #64748b; margin: 2px 0 0 0;">
                    Talep edilen malzeme listesi ve tahmini maliyetler
                </p>
            </div>
            <div style="font-size: 13px; font-weight: 700; color: #334155; background: #f8fafc; padding: 6px 12px; border-radius: 8px; border: 1px solid #e2e8f0;">
                Toplam: <b style="color: #2563eb;"><?= count($items) ?></b> kalem
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 12px 14px; font-weight: 700; color: #475569; width: 40px; text-align: center;">#</th>
                        <th style="padding: 12px 14px; font-weight: 700; color: #475569;">Malzeme Kodu</th>
                        <th style="padding: 12px 14px; font-weight: 700; color: #475569;">Malzeme Adı</th>
                        <th style="padding: 12px 14px; font-weight: 700; color: #475569; text-align: right;">Talep Miktarı</th>
                        <th style="padding: 12px 14px; font-weight: 700; color: #475569; text-align: center;">Birim</th>
                        <th style="padding: 12px 14px; font-weight: 700; color: #475569; text-align: right;">Tahmini Birim Fiyat</th>
                        <th style="padding: 12px 14px; font-weight: 700; color: #475569; text-align: center;">Kur</th>
                        <th style="padding: 12px 14px; font-weight: 700; color: #475569; text-align: right;">Tahmini Satır Tutarı</th>
                        <th style="padding: 12px 14px; font-weight: 700; color: #475569;">Önerilen Tedarikçi</th>
                        <th style="padding: 12px 14px; font-weight: 700; color: #475569;">Not</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="10" style="padding: 30px; text-align: center; color: #64748b;">
                                Bu talebe ait malzeme kalemi bulunamadı.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $rowNum = 1; ?>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $qty = (float)($item['requested_quantity'] ?? 0);
                            $price = $item['estimated_unit_price'] !== null && $item['estimated_unit_price'] !== '' ? (float)$item['estimated_unit_price'] : null;
                            $lineTotal = $price !== null ? ($qty * $price) : null;
                            $curr = $item['currency'] ?? 'TL';
                            ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background='#f8fafc';" onmouseout="this.style.background='#ffffff';">
                                <td style="padding: 12px 14px; text-align: center; color: #94a3b8; font-weight: 700;">
                                    <?= $rowNum++ ?>
                                </td>
                                <td style="padding: 12px 14px; font-family: monospace; font-weight: 700; color: #1e40af; white-space: nowrap;">
                                    <?= htmlspecialchars($item['material_code'] ?? '-') ?>
                                </td>
                                <td style="padding: 12px 14px; font-weight: 600; color: #0f172a;">
                                    <?= htmlspecialchars($item['material_name'] ?? '-') ?>
                                </td>
                                <td style="padding: 12px 14px; text-align: right; font-weight: 700; color: #0f172a; white-space: nowrap;">
                                    <?= $formatNumber($qty, 3) ?>
                                </td>
                                <td style="padding: 12px 14px; text-align: center; white-space: nowrap;">
                                    <span style="display: inline-block; padding: 2px 8px; background: #f1f5f9; color: #475569; border-radius: 6px; font-weight: 700; font-size: 11.5px;">
                                        <?= htmlspecialchars($item['unit_symbol'] ?: ($item['unit_name'] ?? '-')) ?>
                                    </span>
                                </td>
                                <td style="padding: 12px 14px; text-align: right; color: #334155; white-space: nowrap;">
                                    <?= $price !== null ? $formatNumber($price, 4) : '<span style="color:#94a3b8;">—</span>' ?>
                                </td>
                                <td style="padding: 12px 14px; text-align: center; font-weight: 700; color: #64748b; white-space: nowrap;">
                                    <?= htmlspecialchars($curr) ?>
                                </td>
                                <td style="padding: 12px 14px; text-align: right; font-weight: 700; color: #2563eb; white-space: nowrap;">
                                    <?= $lineTotal !== null ? $formatNumber($lineTotal, 2) . ' ' . htmlspecialchars($curr) : '<span style="color:#94a3b8;">—</span>' ?>
                                </td>
                                <td style="padding: 12px 14px; color: #475569; white-space: nowrap;">
                                    <?= !empty($item['supplier_name']) ? htmlspecialchars($item['supplier_name']) : '<span style="color:#94a3b8;">(Belirtilmedi)</span>' ?>
                                </td>
                                <td style="padding: 12px 14px; color: #64748b; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?= !empty($item['notes']) ? htmlspecialchars($item['notes']) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- TABLO ALTI TOPLAM BİLGİSİ (Para Birimlerine Göre Ayrı Ayrı) -->
        <div style="padding: 16px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div style="font-size: 13.5px; font-weight: 700; color: #475569;">
                Tahmini Toplam Tutar:
            </div>
            <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <?php if (empty($currencyTotals)): ?>
                    <span style="font-size: 16px; font-weight: 800; color: #0f172a;">0,00 TL</span>
                <?php else: ?>
                    <?php foreach ($currencyTotals as $curr => $tot): ?>
                        <div style="background: #ffffff; border: 1px solid #cbd5e1; padding: 6px 14px; border-radius: 8px; font-size: 15px; font-weight: 800; color: #2563eb; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
                            <?= $formatNumber($tot, 2) ?> <span style="font-size: 13px; color: #64748b; font-weight: 700;"><?= htmlspecialchars($curr) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 7. ALT GERİ DÖNÜŞ VE AKSİYONLAR -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-top: 10px;">
        <a href="/stok-takip/public/purchase-requests" class="button button-secondary" style="padding: 8px 16px; font-size: 13px; font-weight: 600; text-decoration: none; background: #fff; border: 1px solid #cbd5e1; color: #475569;">
            &larr; Satın Alma Talepleri Listesi
        </a>

        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if ($currStatus === 'DRAFT' && $can('purchase.request')): ?>
                <a href="/stok-takip/public/purchase-requests/edit?id=<?= (int)$request['id'] ?>" class="button button-secondary" style="padding: 8px 16px; font-size: 13px; font-weight: 700; text-decoration: none; background: #fff; border: 1px solid #cbd5e1; color: #0f172a;">
                    <span>✏️</span> Talebi Düzenle
                </a>
            <?php endif; ?>
        </div>
    </div>

</main>

<!-- ONAYA GÖNDER MODALI -->
<div id="submitModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div style="background: #fff; border-radius: 14px; width: 100%; max-width: 440px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
        <h3 style="font-size: 17px; font-weight: 800; color: #0f172a; margin: 0 0 8px 0; display: flex; align-items: center; gap: 8px;">
            <span>🚀</span> Talebi Onaya Gönder
        </h3>
        <p style="font-size: 13.5px; color: #64748b; margin: 0 0 20px 0; line-height: 1.4;">
            <strong><?= htmlspecialchars($request['request_no']) ?></strong> numaralı talep yönetici onayına sunulacak. Bu işlemden sonra talep içeriği düzenlenemez. Devam etmek istiyor musunuz?
        </p>
        <form method="POST" action="/stok-takip/public/purchase-requests/submit" style="margin: 0;">
            <?= CsrfService::tokenField() ?>
            <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeSubmitModal();" class="button button-secondary" style="padding: 8px 16px; font-size: 13px; font-weight: 600; cursor: pointer; background: #fff; border: 1px solid #cbd5e1;">
                    Vazgeç
                </button>
                <button type="submit" class="button button-primary" style="padding: 8px 18px; font-size: 13px; font-weight: 700; cursor: pointer;">
                    Evet, Onaya Gönder
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ONAYLAMA MODALI -->
<div id="approveModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div style="background: #fff; border-radius: 14px; width: 100%; max-width: 460px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
        <h3 style="font-size: 17px; font-weight: 800; color: #166534; margin: 0 0 8px 0; display: flex; align-items: center; gap: 8px;">
            <span>✅</span> Talebi Onayla
        </h3>
        <p style="font-size: 13.5px; color: #64748b; margin: 0 0 16px 0; line-height: 1.4;">
            <strong><?= htmlspecialchars($request['request_no']) ?></strong> numaralı talebi onaylayarak satın alma sürecine aktarmak üzeresiniz.
        </p>
        <form method="POST" action="/stok-takip/public/purchase-requests/approve" style="margin: 0;">
            <?= CsrfService::tokenField() ?>
            <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    Onay Notu <small style="color: #94a3b8; font-weight: 400;">(Opsiyonel)</small>
                </label>
                <textarea name="approval_notes" rows="2" placeholder="Varsa onay notu veya sipariş talimatı yazabilirsiniz..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-family: inherit; resize: vertical; box-sizing: border-box;"></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeApproveModal();" class="button button-secondary" style="padding: 8px 16px; font-size: 13px; font-weight: 600; cursor: pointer; background: #fff; border: 1px solid #cbd5e1;">
                    Vazgeç
                </button>
                <button type="submit" class="button" style="background: #16a34a; color: #fff; border: 1px solid #15803d; padding: 8px 20px; font-size: 13px; font-weight: 700; cursor: pointer; border-radius: 8px;">
                    Talebi Onayla
                </button>
            </div>
        </form>
    </div>
</div>

<!-- REDDETME MODALI -->
<div id="rejectModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div style="background: #fff; border-radius: 14px; width: 100%; max-width: 460px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
        <h3 style="font-size: 17px; font-weight: 800; color: #991b1b; margin: 0 0 8px 0; display: flex; align-items: center; gap: 8px;">
            <span>❌</span> Talebi Reddet
        </h3>
        <p style="font-size: 13.5px; color: #64748b; margin: 0 0 16px 0; line-height: 1.4;">
            <strong><?= htmlspecialchars($request['request_no']) ?></strong> numaralı talebi reddetmek için lütfen gerekçe belirtiniz.
        </p>
        <form method="POST" action="/stok-takip/public/purchase-requests/reject" style="margin: 0;" onsubmit="return validateRejectForm(this);">
            <?= CsrfService::tokenField() ?>
            <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    Red Gerekçesi <span style="color: #ef4444;">*</span>
                </label>
                <textarea name="rejection_notes" id="rejectReasonInput" required rows="3" placeholder="Örn: Stoklarımızda yeterli miktar bulunmaktadır / Bütçe aşımı..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-family: inherit; resize: vertical; box-sizing: border-box;"></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeRejectModal();" class="button button-secondary" style="padding: 8px 16px; font-size: 13px; font-weight: 600; cursor: pointer; background: #fff; border: 1px solid #cbd5e1;">
                    Vazgeç
                </button>
                <button type="submit" class="button" style="background: #dc2626; color: #fff; border: 1px solid #b91c1c; padding: 8px 20px; font-size: 13px; font-weight: 700; cursor: pointer; border-radius: 8px;">
                    Talebi Reddet
                </button>
            </div>
        </form>
    </div>
</div>

<!-- İPTAL MODALI -->
<div id="cancelModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div style="background: #fff; border-radius: 14px; width: 100%; max-width: 440px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
        <h3 style="font-size: 17px; font-weight: 800; color: #0f172a; margin: 0 0 8px 0; display: flex; align-items: center; gap: 8px;">
            <span>🚫</span> Talebi İptal Et
        </h3>
        <p style="font-size: 13.5px; color: #64748b; margin: 0 0 16px 0; line-height: 1.4;">
            <strong><?= htmlspecialchars($request['request_no']) ?></strong> numaralı talep iptal edilecek. Bu işlem geri alınamaz.
        </p>
        <form method="POST" action="/stok-takip/public/purchase-requests/cancel" style="margin: 0;">
            <?= CsrfService::tokenField() ?>
            <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    İptal Nedeni <small style="color: #94a3b8; font-weight: 400;">(Opsiyonel)</small>
                </label>
                <input type="text" name="reason" placeholder="Örn: İhtiyaç kalmadı..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; box-sizing: border-box;">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeCancelModal();" class="button button-secondary" style="padding: 8px 16px; font-size: 13px; font-weight: 600; cursor: pointer; background: #fff; border: 1px solid #cbd5e1;">
                    Vazgeç
                </button>
                <button type="submit" class="button" style="background: #475569; color: #fff; border: 1px solid #334155; padding: 8px 18px; font-size: 13px; font-weight: 700; cursor: pointer; border-radius: 8px;">
                    Evet, İptal Et
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($currStatus === 'APPROVED' && $can('purchase.order.create')): ?>
<!-- 4. PO OLUŞTUR MODAL -->
<div id="createPoModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div style="background: #ffffff; border-radius: 14px; width: 100%; max-width: 480px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); margin: 16px;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px;">
            <span style="font-size: 24px;">📦</span>
            <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0;">Satın Alma Siparişi (PO) Oluştur</h3>
        </div>
        <p style="font-size: 13.5px; color: #475569; margin: 0 0 16px 0; line-height: 1.5;">
            <strong><?= htmlspecialchars($request['request_no']) ?></strong> numaralı onaylı satın alma talebi, önerilen tedarikçi ve birim fiyat bilgileriyle otomatik olarak <strong>Satın Alma Siparişine (PO)</strong> dönüştürülecektir.
        </p>
        <form method="POST" action="/stok-takip/public/purchase-orders/from-request" style="margin: 0;">
            <?= CsrfService::tokenField() ?>
            <input type="hidden" name="purchase_request_id" value="<?= (int)$request['id'] ?>">
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeCreatePoModal();" class="button button-secondary" style="padding: 8px 16px; font-size: 13px; font-weight: 600; cursor: pointer; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px;">
                    Vazgeç
                </button>
                <button type="submit" class="button button-primary" style="padding: 8px 18px; font-size: 13px; font-weight: 700; cursor: pointer; border-radius: 8px; background: #2563eb; color: #fff;">
                    Evet, Siparişi Oluştur
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- JAVASCRIPT MODAL YÖNETİMİ -->
<script>
function openSubmitModal() {
    document.getElementById('submitModal').style.display = 'flex';
}
function closeSubmitModal() {
    document.getElementById('submitModal').style.display = 'none';
}

function openApproveModal() {
    document.getElementById('approveModal').style.display = 'flex';
}
function closeApproveModal() {
    document.getElementById('approveModal').style.display = 'none';
}

function openRejectModal() {
    document.getElementById('rejectModal').style.display = 'flex';
}
function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}

function openCancelModal() {
    document.getElementById('cancelModal').style.display = 'flex';
}
function closeCancelModal() {
    document.getElementById('cancelModal').style.display = 'none';
}

function openCreatePoModal() {
    var m = document.getElementById('createPoModal');
    if (m) m.style.display = 'flex';
}
function closeCreatePoModal() {
    var m = document.getElementById('createPoModal');
    if (m) m.style.display = 'none';
}

function validateRejectForm(form) {
    var val = document.getElementById('rejectReasonInput').value.trim();
    if (!val) {
        alert('Lütfen red gerekçesini yazınız.');
        return false;
    }
    return true;
}

// ESC ile modal kapatma
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSubmitModal();
        closeApproveModal();
        closeRejectModal();
        closeCancelModal();
        closeCreatePoModal();
    }
});
</script>
</body>
</html>

