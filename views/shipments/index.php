<?php
$pageTitle = 'Sevkiyat Yönetimi';
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">
    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;">🚚 Sevkiyat Yönetimi</h1>
            <p style="font-size: 13.5px; color: #64748b; margin: 0;">Müşteri sevkiyat siparişleri ve mamul panel çıkış işlemleri</p>
        </div>
        <div>
            <a href="/stok-takip/public/shipments/create" class="button button-primary" style="text-decoration: none; padding: 10px 18px; font-weight: 700; font-size: 13.5px; display: inline-flex; align-items: center; gap: 6px;">
                ➕ Yeni Sevkiyat Emri
            </a>
        </div>
    </div>

    <!-- Filtreler -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <form method="GET" action="/stok-takip/public/shipments" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 1; min-width: 150px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 4px;">Sevkiyat No:</label>
                <input type="text" name="shipment_no" value="<?= htmlspecialchars($filters['shipment_no'] ?? '') ?>" placeholder="SHP-..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
            </div>
            <div style="flex: 1.5; min-width: 180px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 4px;">Müşteri Adı:</label>
                <input type="text" name="customer_name" value="<?= htmlspecialchars($filters['customer_name'] ?? '') ?>" placeholder="Müşteri ara..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
            </div>
            <div style="flex: 1.2; min-width: 130px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 4px;">Durum:</label>
                <select name="status" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
                    <option value="">Tüm Durumlar</option>
                    <option value="DRAFT" <?= ($filters['status'] ?? '') === 'DRAFT' ? 'selected' : '' ?>>DRAFT (Taslak)</option>
                    <option value="COMPLETED" <?= ($filters['status'] ?? '') === 'COMPLETED' ? 'selected' : '' ?>>COMPLETED (Tamamlandı)</option>
                    <option value="CANCELLED" <?= ($filters['status'] ?? '') === 'CANCELLED' ? 'selected' : '' ?>>CANCELLED (İptal Edildi)</option>
                </select>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="button button-primary" style="padding: 8px 16px; font-size: 13px; font-weight: 600; cursor: pointer;">Filtrele</button>
                <a href="/stok-takip/public/shipments" class="button" style="padding: 8px 12px; font-size: 13px; text-decoration: none;">Temizle</a>
            </div>
        </form>
    </div>

    <!-- Liste -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13.5px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <th style="padding: 14px 18px; font-weight: 700; color: #475569;">Sevkiyat No</th>
                    <th style="padding: 14px 18px; font-weight: 700; color: #475569;">Müşteri</th>
                    <th style="padding: 14px 18px; font-weight: 700; color: #475569;">Sevk Adresi</th>
                    <th style="padding: 14px 18px; font-weight: 700; color: #475569; text-align: center;">Durum</th>
                    <th style="padding: 14px 18px; font-weight: 700; color: #475569;">Oluşturma Tarihi</th>
                    <th style="padding: 14px 18px; font-weight: 700; color: #475569; text-align: right;">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($shipments)): ?>
                    <tr>
                        <td colspan="6" style="padding: 30px; text-align: center; color: #64748b;">Kayıtlı sevkiyat emri bulunamadı.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($shipments as $s): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 14px 18px; font-family: monospace; font-weight: 700; color: #0f172a;">
                                <?= htmlspecialchars($s['shipment_no']) ?>
                            </td>
                            <td style="padding: 14px 18px; font-weight: 600; color: #334155;">
                                <?= htmlspecialchars($s['customer_name']) ?>
                            </td>
                            <td style="padding: 14px 18px; color: #64748b; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= htmlspecialchars($s['shipping_address']) ?>
                            </td>
                            <td style="padding: 14px 18px; text-align: center;">
                                <?php if ($s['status'] === 'DRAFT'): ?>
                                    <span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: #fef9c3; color: #854d0e; border: 1px solid #fef08a;">
                                        🟡 TASLAK
                                    </span>
                                <?php elseif ($s['status'] === 'COMPLETED'): ?>
                                    <span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">
                                        ✅ TAMAMLANDI
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;">
                                        🔴 İPTAL EDİLDİ
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 14px 18px; color: #64748b;">
                                <?= date('d.m.Y H:i', strtotime($s['created_at'])) ?>
                            </td>
                            <td style="padding: 14px 18px; text-align: right;">
                                <a href="/stok-takip/public/shipments/show?id=<?= (int)$s['id'] ?>" class="button button-small" style="padding: 5px 10px; font-size: 12px; text-decoration: none;">
                                    Detay &rarr;
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Sayfalama -->
    <?php if ($totalPages > 1): ?>
        <div style="margin-top: 20px; display: flex; justify-content: center; gap: 8px;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="/stok-takip/public/shipments?page=<?= $i ?>&shipment_no=<?= urlencode($filters['shipment_no']) ?>&customer_name=<?= urlencode($filters['customer_name']) ?>&status=<?= urlencode($filters['status']) ?>" class="button <?= $page === $i ? 'button-primary' : '' ?>" style="padding: 6px 12px; font-size: 13px; text-decoration: none;"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

</main>


