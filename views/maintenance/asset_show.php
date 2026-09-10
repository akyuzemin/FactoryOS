<?php

$pageTitle = 'Ekipman Pasaportu: ' . ($asset['asset_name'] ?? '');
$activePage = 'maintenance';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';

$asset = $asset ?? [];
$kpi = $kpi ?? [];
$workOrders = $workOrders ?? [];
$consumedParts = $consumedParts ?? [];
?>

<main class="main-content">

    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: #64748b; margin-bottom: 6px;">
                <a href="/stok-takip/public/maintenance" style="color: #4338ca; text-decoration: none; font-weight: 600;">Bakım Yönetimi</a>
                <span>&rsaquo;</span>
                <span style="color: #0f172a; font-weight: 600;"><?= htmlspecialchars($asset['asset_code']) ?></span>
            </div>
            <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0;">
                EKİPMAN PASAPORTU: <?= htmlspecialchars($asset['asset_name']) ?>
            </h1>
        </div>

        <div style="display: flex; gap: 10px;">
            <a href="/stok-takip/public/maintenance" class="button" style="padding: 8px 14px; text-decoration: none;">
                &larr; Listeye Dön
            </a>
            <button type="button" onclick="window.print();" class="button button-primary" style="padding: 8px 14px;">
                🖨️ Pasaport Yazdır
            </button>
        </div>
    </div>

    <!-- 1. EKİPMAN KÜNYESİ VE METRİKLER (2x2) -->
    <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 20px; align-items: flex-start; margin-bottom: 24px;">
        
        <!-- EKİPMAN BİLGİLERİ -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0 0 14px 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                🏷️ EKİPMAN KİMLİK KÜNYESİ
            </h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; font-size: 13px;">
                <div>
                    <span style="color: #64748b; display: block; font-size: 11px; text-transform: uppercase;">Ekipman Kodu:</span>
                    <strong style="color: #4338ca; font-family: monospace; font-size: 14px;"><?= htmlspecialchars($asset['asset_code']) ?></strong>
                </div>
                <div>
                    <span style="color: #64748b; display: block; font-size: 11px; text-transform: uppercase;">Bağlı Olduğu Hat:</span>
                    <strong style="color: #0f172a;"><?= htmlspecialchars($asset['line_name']) ?> (<?= htmlspecialchars($asset['line_code']) ?>)</strong>
                </div>
                <div>
                    <span style="color: #64748b; display: block; font-size: 11px; text-transform: uppercase;">Üretici &amp; Model:</span>
                    <strong><?= htmlspecialchars($asset['manufacturer'] ?: '-') ?> / <?= htmlspecialchars($asset['model_no'] ?: '-') ?></strong>
                </div>
                <div>
                    <span style="color: #64748b; display: block; font-size: 11px; text-transform: uppercase;">Seri Numarası:</span>
                    <strong style="font-family: monospace;"><?= htmlspecialchars($asset['serial_no'] ?: '-') ?></strong>
                </div>
                <div>
                    <span style="color: #64748b; display: block; font-size: 11px; text-transform: uppercase;">Kritiklik Seviyesi:</span>
                    <strong style="color: <?= $asset['is_critical'] ? '#dc2626' : '#16a34a' ?>;">
                        <?= $asset['is_critical'] ? '🔴 Kritik Ekipman' : '⚪ Standart Ekipman' ?>
                    </strong>
                </div>
                <div>
                    <span style="color: #64748b; display: block; font-size: 11px; text-transform: uppercase;">Mevcut Durum:</span>
                    <strong style="color: <?= $asset['status'] === 'OPERATIONAL' ? '#16a34a' : '#dc2626' ?>;">
                        <?= htmlspecialchars($asset['status']) ?>
                    </strong>
                </div>
                <div>
                    <span style="color: #64748b; display: block; font-size: 11px; text-transform: uppercase;">Toplam Çalışma Saati:</span>
                    <strong><?= number_format($asset['total_running_hours'], 0, ',', '.') ?> Saat</strong>
                </div>
                <div>
                    <span style="color: #64748b; display: block; font-size: 11px; text-transform: uppercase;">Toplam Çevrim Sayısı:</span>
                    <strong><?= number_format($asset['total_cycles_count'], 0, ',', '.') ?> Çevrim</strong>
                </div>
            </div>
        </div>

        <!-- MTBF / MTTR & KULLANILABİLİRLİK KARTI -->
        <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff; border-radius: 14px; padding: 22px; box-shadow: 0 4px 14px rgba(15, 23, 42, 0.15);">
            <h3 style="font-size: 15px; font-weight: 800; color: #38bdf8; margin: 0 0 16px 0; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px;">
                📊 GÜVENİLİRLİK &amp; BAKIM METRİKLERİ (TPM)
            </h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                <div style="background: rgba(255,255,255,0.06); padding: 12px; border-radius: 10px;">
                    <div style="font-size: 10.5px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">MTBF (Arızalar Arası)</div>
                    <div style="font-size: 24px; font-weight: 900; color: #38bdf8; margin-top: 2px;">
                        <?= number_format($kpi['mtbf_hours'] ?? 0, 1, ',', '.') ?> <small style="font-size: 12px;">sa</small>
                    </div>
                </div>
                <div style="background: rgba(255,255,255,0.06); padding: 12px; border-radius: 10px;">
                    <div style="font-size: 10.5px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">MTTR (Ortalama Onarım)</div>
                    <div style="font-size: 24px; font-weight: 900; color: #fbbf24; margin-top: 2px;">
                        <?= number_format($kpi['mttr_minutes'] ?? 0, 1, ',', '.') ?> <small style="font-size: 12px;">dk</small>
                    </div>
                </div>
            </div>

            <div style="background: rgba(255,255,255,0.06); padding: 14px; border-radius: 10px;">
                <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 6px;">
                    <span style="color: #94a3b8;">Teknik Kullanılabilirlik ($A_t$):</span>
                    <strong style="color: #4ade80; font-size: 15px;">%<?= number_format($kpi['technical_availability_pct'] ?? 100, 1, ',', '.') ?></strong>
                </div>
                <div style="font-size: 11px; color: #94a3b8;">
                    Kayıtlı Arıza: <b><?= (int)($kpi['failures_count'] ?? 0) ?></b> &bull; Toplam Onarım Duruşu: <b><?= number_format($kpi['total_repair_minutes'] ?? 0, 1) ?> dk</b>
                </div>
            </div>
        </div>

    </div>

    <!-- 2. GEÇMİŞ BAKIM İŞ EMİRLERİ -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0 0 14px 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
            📜 MAKİNE BAKIM &amp; ARIZA GEÇMİŞİ
        </h3>

        <?php if (empty($workOrders)): ?>
            <div style="padding: 24px; text-align: center; color: #94a3b8; font-size: 13px;">
                Bu makineye ait geçmiş bakım kaydı bulunmamaktadır.
            </div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; font-size: 12.5px; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 11px; text-transform: uppercase;">
                        <th style="padding: 8px 10px;">İş Emri No</th>
                        <th style="padding: 8px 10px;">Tür</th>
                        <th style="padding: 8px 10px;">Durum</th>
                        <th style="padding: 8px 10px;">Teknisyen</th>
                        <th style="padding: 8px 10px;">Kök Neden</th>
                        <th style="padding: 8px 10px; text-align: right;">Duruş Süresi</th>
                        <th style="padding: 8px 10px; text-align: right;">Toplam Maliyet</th>
                        <th style="padding: 8px 10px; text-align: right;">Detay</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($workOrders as $wo): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 10px; font-family: monospace; font-weight: 700; color: #4338ca;">
                                <?= htmlspecialchars($wo['work_order_no']) ?>
                            </td>
                            <td style="padding: 10px 10px;">
                                <?= $wo['maintenance_type'] === 'CORRECTIVE' ? '🚨 Arızi' : '📅 Planlı' ?>
                            </td>
                            <td style="padding: 10px 10px;">
                                <span style="font-size: 11px; font-weight: 700; padding: 2px 6px; border-radius: 6px; background: #f1f5f9; color: #334155;">
                                    <?= htmlspecialchars($wo['status']) ?>
                                </span>
                            </td>
                            <td style="padding: 10px 10px; color: #64748b;">
                                <?= htmlspecialchars($wo['technician_username'] ?: 'Atanmadı') ?>
                            </td>
                            <td style="padding: 10px 10px;">
                                <?= htmlspecialchars($wo['root_cause_text'] ?: '-') ?>
                            </td>
                            <td style="padding: 10px 10px; text-align: right; font-weight: 700;">
                                <?= number_format($wo['downtime_minutes'], 1, ',', '.') ?> dk
                            </td>
                            <td style="padding: 10px 10px; text-align: right; font-weight: 800; color: #0f172a;">
                                <?= number_format($wo['total_maintenance_cost'], 2, ',', '.') ?> TL
                            </td>
                            <td style="padding: 10px 10px; text-align: right;">
                                <a href="/stok-takip/public/maintenance/work-order?id=<?= $wo['id'] ?>" class="button button-primary" style="padding: 3px 8px; font-size: 11px; text-decoration: none;">
                                    İncele
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</main>

</body>
</html>

