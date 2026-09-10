<?php
$pageTitle = 'Sevkiyat Emri: ' . $shipment['shipment_no'];
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">
    <div style="margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: #64748b; margin-bottom: 8px;">
            <a href="/stok-takip/public/shipments" style="color: #4338ca; text-decoration: none; font-weight: 600;">Sevkiyat Yönetimi</a>
            <span>&rsaquo;</span>
            <span style="color: #0f172a; font-weight: 600;"><?= htmlspecialchars($shipment['shipment_no']) ?></span>
        </div>
        
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div>
                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 6px; background: #e0e7ff; color: #3730a3; font-weight: 800; font-size: 13px; font-family: monospace; border: 1px solid #c7d2fe; margin-bottom: 6px;">
                    <?= htmlspecialchars($shipment['shipment_no']) ?>
                </span>
                <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0;"><?= htmlspecialchars($shipment['customer_name']) ?></h1>
            </div>
            
            <div style="display: flex; gap: 8px;">
                <?php if ($shipment['status'] === 'DRAFT'): ?>
                    <form method="POST" action="/stok-takip/public/shipments/complete" onsubmit="return confirm('Bu sevkiyatı tamamlamak istediğinize emin misiniz? Seçilen paneller sevk durumuna geçirilecek ve stok çıkışları yapılacaktır.');">
            <?= CsrfService::tokenField() ?>
                        <input type="hidden" name="shipment_id" value="<?= (int)$shipment['id'] ?>">
                        <button type="submit" class="button button-success" style="padding: 10px 18px; font-weight: 700; font-size: 13.5px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; background: #10b981; border: none; border-radius: 8px; color: white;">
                            ✅ Sevkiyatı Tamamla (Sevk Et)
                        </button>
                    </form>
                    
                    <form method="POST" action="/stok-takip/public/shipments/cancel" onsubmit="return confirm('Bu sevkiyat emrini iptal etmek istediğinize emin misiniz?');">
                        <input type="hidden" name="shipment_id" value="<?= (int)$shipment['id'] ?>">
                        <button type="submit" class="button" style="padding: 10px 16px; font-size: 13.5px; cursor: pointer; border-radius: 8px; color: #b91c1c; border: 1px solid #fca5a5; background: #fef2f2;">
                            ❌ Sevkiyatı İptal Et
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Alert Mesajları -->
    <?php if (isset($_SESSION['success'])): ?>
        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; font-size: 13.5px; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
            <span>✅</span> <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; font-size: 13.5px; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
            <span>⚠️</span> <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Sevkiyat Bilgileri & Durum -->
    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px; margin-bottom: 24px; align-items: start;">
        
        <!-- Sol: Genel Bilgiler -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
            <h3 style="font-size: 14px; font-weight: 700; color: #0f172a; margin: 0 0 16px 0; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
                📋 Sevkiyat Detayları
            </h3>
            <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 8px 0; color: #64748b; font-weight: 600;">Durum:</td>
                    <td style="padding: 8px 0; text-align: right;">
                        <?php if ($shipment['status'] === 'DRAFT'): ?>
                            <span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: #fef9c3; color: #854d0e; border: 1px solid #fef08a;">
                                🟡 TASLAK
                            </span>
                        <?php elseif ($shipment['status'] === 'COMPLETED'): ?>
                            <span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">
                                ✅ TAMAMLANDI
                            </span>
                        <?php else: ?>
                            <span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;">
                                🔴 İPTAL EDİLDİ
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 8px 0; color: #64748b; font-weight: 600;">Oluşturma:</td>
                    <td style="padding: 8px 0; color: #0f172a; text-align: right;"><?= date('d.m.Y H:i:s', strtotime($shipment['created_at'])) ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 8px 0; color: #64748b; font-weight: 600;">Son Güncelleme:</td>
                    <td style="padding: 8px 0; color: #0f172a; text-align: right;"><?= date('d.m.Y H:i:s', strtotime($shipment['updated_at'])) ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #64748b; font-weight: 600; vertical-align: top;">Sevk Adresi:</td>
                    <td style="padding: 8px 0; color: #334155; text-align: right; white-space: pre-wrap; font-size: 12.5px;"><?= htmlspecialchars($shipment['shipping_address']) ?></td>
                </tr>
            </table>
        </div>

        <!-- Sağ: Sevkiyattaki Paneller -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
            <h3 style="font-size: 14px; font-weight: 700; color: #0f172a; margin: 0 0 16px 0; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                <span>📦 Sevkiyat Planındaki Paneller</span>
                <span style="font-size: 12px; background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 8px;">Toplam: <?= count($items) ?> Adet</span>
            </h3>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                            <th style="padding: 10px; font-weight: 700; color: #475569;">Seri No</th>
                            <th style="padding: 10px; font-weight: 700; color: #475569;">Ürün</th>
                            <th style="padding: 10px; font-weight: 700; color: #475569;">Bulunduğu Depo / Raf</th>
                            <th style="padding: 10px; font-weight: 700; color: #475569; text-align: right;">Birim Maliyet</th>
                            <?php if ($shipment['status'] === 'DRAFT'): ?>
                                <th style="padding: 10px; font-weight: 700; color: #475569; text-align: center;">İşlem</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="<?= $shipment['status'] === 'DRAFT' ? 5 : 4 ?>" style="padding: 20px; text-align: center; color: #64748b;">
                                    Bu sevkiyata henüz hiç panel eklenmemiş.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 10px; font-family: monospace; font-weight: 700;">
                                        <a href="/stok-takip/public/finished-goods/show?id=<?= (int)$item['id'] ?>" style="color: #2563eb; text-decoration: none;">
                                            <?= htmlspecialchars($item['serial_no']) ?>
                                        </a>
                                    </td>
                                    <td style="padding: 10px; color: #334155;"><?= htmlspecialchars($item['material_name']) ?></td>
                                    <td style="padding: 10px; color: #64748b;">
                                        <?php if ($item['status'] === 'SHIPPED'): ?>
                                            <span style="color: #475569; font-weight: 600;">🚚 Sevk Edildi</span>
                                        <?php else: ?>
                                            <?= htmlspecialchars($item['warehouse_name'] ?? '-') ?> (<?= htmlspecialchars($item['location_code'] ?? '-') ?>)
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 10px; text-align: right; font-weight: 600; color: #0f172a;">
                                        <?= number_format($item['unit_cost'], 2, ',', '.') ?> TL
                                    </td>
                                    <?php if ($shipment['status'] === 'DRAFT'): ?>
                                        <td style="padding: 10px; text-align: center;">
                                            <form method="POST" action="/stok-takip/public/shipments/remove-panel" style="margin: 0;">
                                                <input type="hidden" name="shipment_id" value="<?= (int)$shipment['id'] ?>">
                                                <input type="hidden" name="panel_unit_id" value="<?= (int)$item['id'] ?>">
                                                <button type="submit" style="background: none; border: none; color: #dc2626; font-weight: 600; cursor: pointer; padding: 2px 6px; font-size: 12px;">Kaldır</button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PANEL EKLEME ALANI (Sadece DRAFT durumunda) -->
    <?php if ($shipment['status'] === 'DRAFT'): ?>
        <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 24px; margin-bottom: 24px;">
            
            <!-- Sol: Seri Numarasıyla Doğrudan Ekleme -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
                <h3 style="font-size: 14px; font-weight: 700; color: #0f172a; margin: 0 0 16px 0; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
                    ⚡ Seri No ile Hızlı Ekle
                </h3>
                
                <form method="POST" action="/stok-takip/public/shipments/add-panel">
                    <input type="hidden" name="shipment_id" value="<?= (int)$shipment['id'] ?>">
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-weight: 700; font-size: 12px; color: #475569; margin-bottom: 6px;">Panel Seri Numarası:</label>
                        <input type="text" name="serial_no" required placeholder="SP550W-..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-family: monospace;">
                    </div>
                    
                    <button type="submit" class="button button-primary" style="width: 100%; padding: 10px; font-weight: 700; font-size: 13px; cursor: pointer;">
                        Plana Ekle
                    </button>
                </form>
            </div>

            <!-- Sağ: Sevk Edilebilir Paneller Listesinden Seçerek Ekleme -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
                <h3 style="font-size: 14px; font-weight: 700; color: #0f172a; margin: 0 0 16px 0; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                    <span>🔍 Sevk Edilebilir Paneller Listesi</span>
                    <form method="GET" action="/stok-takip/public/shipments/show" style="display: flex; gap: 6px; margin: 0;">
                        <input type="hidden" name="id" value="<?= (int)$shipment['id'] ?>">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Seri no / ürün ara..." style="padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; width: 150px;">
                        <button type="submit" class="button button-small" style="padding: 4px 8px; font-size: 12px;">Ara</button>
                    </form>
                </h3>

                <div style="max-height: 250px; overflow-y: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 12.5px;">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0;">
                                <th style="padding: 8px; font-weight: 700; color: #475569;">Seri No</th>
                                <th style="padding: 8px; font-weight: 700; color: #475569;">Ürün</th>
                                <th style="padding: 8px; font-weight: 700; color: #475569; text-align: center;">Ekle</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($shippablePanels)): ?>
                                <tr>
                                    <td colspan="3" style="padding: 16px; text-align: center; color: #64748b;">
                                        Sevk edilebilir durumda (kalite onaylı ve serbest) panel bulunamadı.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($shippablePanels as $sp): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 8px; font-family: monospace; font-weight: 600;"><?= htmlspecialchars($sp['serial_no']) ?></td>
                                        <td style="padding: 8px; color: #475569;"><?= htmlspecialchars($sp['material_name']) ?></td>
                                        <td style="padding: 8px; text-align: center;">
                                            <form method="POST" action="/stok-takip/public/shipments/add-panel" style="margin: 0;">
                                                <input type="hidden" name="shipment_id" value="<?= (int)$shipment['id'] ?>">
                                                <input type="hidden" name="panel_unit_id" value="<?= (int)$sp['id'] ?>">
                                                <button type="submit" class="button button-small" style="padding: 3px 8px; font-size: 11.5px; font-weight: 600; cursor: pointer;">
                                                    ➕ Ekle
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    <?php endif; ?>

</main>


