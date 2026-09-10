<?php

$pageTitle = 'Bakım Yönetimi & TPM';
$activePage = 'maintenance';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';

$summary = $summary ?? [];
$assets = $assets ?? [];
$plans = $plans ?? [];
$workOrders = $workOrders ?? [];
$technicians = $technicians ?? [];
?>

<main class="main-content">

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

    <!-- HEADER & ACTION BAR -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
        <div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 26px;">🛠️</span>
                <div>
                    <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
                        TOPLAM VERİMLİ BAKIM &amp; TPM YÖNETİMİ
                    </h1>
                    <p style="font-size: 13px; color: #64748b; margin: 2px 0 0 0;">
                        Ekipman Varlık Yönetimi, Periyodik / Arızi Bakım, MTBF &amp; MTTR ve Yedek Parça Entegrasyonu
                    </p>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="button" onclick="document.getElementById('modal-new-wo').style.display='flex'" class="button button-primary" style="padding: 9px 16px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; background: #dc2626; border-color: #b91c1c;">
                <span>🚨</span> Arıza Bildir / Bakım Talebi Aç
            </button>
        </div>
    </div>

    <!-- 1. TPM & BAKIM KPI ŞERİDİ (6 METRİK) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(175px, 1fr)); gap: 14px; margin-bottom: 24px;">
        
        <!-- MTBF -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; border-left: 4px solid #0284c7; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #0284c7; text-transform: uppercase;">⏱️ MTBF (ARIZALAR ARASI)</div>
            <div style="font-size: 22px; font-weight: 900; color: #0369a1; margin: 3px 0;">
                <?= number_format($summary['mtbf_hours'] ?? 0, 1, ',', '.') ?> <small style="font-size: 12px; font-weight: 600;">Saat</small>
            </div>
            <div style="font-size: 11px; color: #64748b;">
                Net Çalışma / Arıza Adedi
            </div>
        </div>

        <!-- MTTR -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; border-left: 4px solid #d97706; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #d97706; text-transform: uppercase;">🔧 MTTR (ORTALAMA ONARIM)</div>
            <div style="font-size: 22px; font-weight: 900; color: #b45309; margin: 3px 0;">
                <?= number_format($summary['mttr_minutes'] ?? 0, 1, ',', '.') ?> <small style="font-size: 12px; font-weight: 600;">Dakika</small>
            </div>
            <div style="font-size: 11px; color: #64748b;">
                Ortalama Müdahale Süresi
            </div>
        </div>

        <!-- TEKNİK KULLANILABİLİRLİK -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; border-left: 4px solid #16a34a; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #16a34a; text-transform: uppercase;">⚙️ TEKNİK KULLANILABİLİRLİK</div>
            <div style="font-size: 22px; font-weight: 900; color: #15803d; margin: 3px 0;">
                %<?= number_format($summary['technical_availability'] ?? 100, 1, ',', '.') ?>
            </div>
            <div style="font-size: 11px; color: #64748b;">
                MTBF / (MTBF + MTTR)
            </div>
        </div>

        <!-- AÇIK BAKIM İŞLERİ -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; border-left: 4px solid <?= ($summary['open_work_orders_count'] ?? 0) > 0 ? '#ef4444' : '#10b981' ?>; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: <?= ($summary['open_work_orders_count'] ?? 0) > 0 ? '#dc2626' : '#16a34a' ?>; text-transform: uppercase;">📋 AÇIK BAKIM İŞLERİ</div>
            <div style="font-size: 22px; font-weight: 900; color: <?= ($summary['open_work_orders_count'] ?? 0) > 0 ? '#dc2626' : '#16a34a' ?>; margin: 3px 0;">
                <?= (int)($summary['open_work_orders_count'] ?? 0) ?> <small style="font-size: 12px; font-weight: 600;">Adet</small>
            </div>
            <div style="font-size: 11px; color: #64748b;">
                Kritik: <b style="color: #dc2626;"><?= (int)($summary['critical_faults_count'] ?? 0) ?></b>
            </div>
        </div>

        <!-- YAKLAŞAN / GECİKEN PERİYODİK BAKIMLAR -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; border-left: 4px solid <?= ($summary['overdue_pm_count'] ?? 0) > 0 ? '#dc2626' : '#f59e0b' ?>; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #b45309; text-transform: uppercase;">📅 PERİYODİK BAKIMLAR</div>
            <div style="font-size: 22px; font-weight: 900; color: <?= ($summary['overdue_pm_count'] ?? 0) > 0 ? '#dc2626' : '#d97706' ?>; margin: 3px 0;">
                <?= (int)($summary['upcoming_pm_count'] ?? 0) ?> <small style="font-size: 12px; font-weight: 600;">Planlı</small>
            </div>
            <div style="font-size: 11px; color: #64748b;">
                Geciken: <b style="color: #dc2626;"><?= (int)($summary['overdue_pm_count'] ?? 0) ?></b>
            </div>
        </div>

        <!-- TOPLAM BAKIM MALİYETİ -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; border-left: 4px solid #6366f1; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #4338ca; text-transform: uppercase;">💰 BAKIM MALİYETİ</div>
            <div style="font-size: 20px; font-weight: 900; color: #3730a3; margin: 3px 0;">
                <?= number_format($summary['total_maintenance_cost'] ?? 0, 2, ',', '.') ?> <small style="font-size: 12px; font-weight: 600;">TL</small>
            </div>
            <div style="font-size: 11px; color: #64748b;">
                İşçilik + Yedek Parça
            </div>
        </div>

    </div>

    <!-- 2. ÜRETİM EKİPMANLARI & VARLIK MATRİSİ (ASSETS GRID) -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
            <div>
                <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0;">🏭 FABRİKA EKİPMAN &amp; MAKİNE VARLIKLARI (ASSETS)</h3>
                <p style="font-size: 12px; color: #64748b; margin: 2px 0 0 0;">4 Üretim Hattı Altındaki Fiziksel Makineler, Çalışma Saatleri ve Anlık Durumlar</p>
            </div>
            <div style="font-size: 12px; color: #64748b; font-weight: 600;">
                Toplam: <?= count($assets) ?> Ekipman
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px;">
            <?php foreach ($assets as $a): ?>
                <?php
                    $stBadgeBg = '#dcfce7'; $stBadgeColor = '#166534'; $stLabel = '🟢 OPERASYONEL';
                    if ($a['status'] === 'FAULTY') {
                        $stBadgeBg = '#fee2e2'; $stBadgeColor = '#991b1b'; $stLabel = '🔴 ARIZALI';
                    } elseif ($a['status'] === 'UNDER_MAINTENANCE') {
                        $stBadgeBg = '#fef3c7'; $stBadgeColor = '#92400e'; $stLabel = '🟡 BAKIMDA';
                    }
                ?>
                <a href="/stok-takip/public/maintenance/asset?id=<?= $a['id'] ?>" style="text-decoration: none; display: block; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; transition: transform 0.15s, box-shadow 0.15s; color: inherit;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <div>
                            <span style="font-size: 10.5px; font-weight: 800; color: #4338ca; background: #e0e7ff; padding: 2px 6px; border-radius: 4px; font-family: monospace;">
                                <?= htmlspecialchars($a['line_code']) ?>
                            </span>
                            <div style="font-size: 14.5px; font-weight: 800; color: #0f172a; margin-top: 4px;">
                                <?= htmlspecialchars($a['asset_name']) ?>
                            </div>
                        </div>
                        <span style="font-size: 10.5px; font-weight: 800; padding: 3px 8px; border-radius: 12px; background: <?= $stBadgeBg ?>; color: <?= $stBadgeColor ?>;">
                            <?= $stLabel ?>
                        </span>
                    </div>

                    <div style="font-size: 11.5px; color: #64748b; margin-bottom: 10px;">
                        <?= htmlspecialchars($a['manufacturer'] ?: '-') ?> &bull; <?= htmlspecialchars($a['model_no'] ?: '-') ?>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed #e2e8f0; padding-top: 8px; font-size: 11.5px;">
                        <span style="color: #64748b;">Çalışma: <b style="color: #0f172a;"><?= number_format($a['total_running_hours'], 0, ',', '.') ?> sa</b></span>
                        <span style="color: #4338ca; font-weight: 700;">Pasaport &rarr;</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 3. AKTİF BAKIM İŞ EMİRLERİ & YAKLAŞAN PLANLAR (2 KOLON) -->
    <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 20px; align-items: flex-start;">
        
        <!-- SOL: BAKIM İŞ EMİRLERİ TABLOSU -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0;">📋 BAKIM &amp; ARIZA İŞ EMİRLERİ</h3>
                <span style="font-size: 12px; color: #64748b;">Son <?= count($workOrders) ?> Kayıt</span>
            </div>

            <?php if (empty($workOrders)): ?>
                <div style="padding: 30px; text-align: center; color: #94a3b8; font-size: 13.5px;">
                    Aktif bakım iş emri bulunmamaktadır.
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 12.5px; text-align: left;">
                        <thead>
                            <tr style="border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 11px; text-transform: uppercase;">
                                <th style="padding: 8px 10px;">İş Emri No</th>
                                <th style="padding: 8px 10px;">Ekipman</th>
                                <th style="padding: 8px 10px;">Tür</th>
                                <th style="padding: 8px 10px;">Öncelik</th>
                                <th style="padding: 8px 10px;">Durum</th>
                                <th style="padding: 8px 10px;">Teknisyen</th>
                                <th style="padding: 8px 10px; text-align: right;">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($workOrders as $wo): ?>
                                <?php
                                    $stBg = '#f1f5f9'; $stColor = '#475569';
                                    if ($wo['status'] === 'OPEN') { $stBg = '#fee2e2'; $stColor = '#991b1b'; }
                                    elseif ($wo['status'] === 'ASSIGNED') { $stBg = '#e0e7ff'; $stColor = '#3730a3'; }
                                    elseif ($wo['status'] === 'IN_PROGRESS') { $stBg = '#fef3c7'; $stColor = '#92400e'; }
                                    elseif ($wo['status'] === 'COMPLETED') { $stBg = '#dbeafe'; $stColor = '#1e40af'; }
                                    elseif ($wo['status'] === 'VERIFIED') { $stBg = '#dcfce7'; $stColor = '#166534'; }
                                ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 10px 10px; font-family: monospace; font-weight: 700; color: #4338ca;">
                                        <?= htmlspecialchars($wo['work_order_no']) ?>
                                    </td>
                                    <td style="padding: 10px 10px;">
                                        <div style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($wo['asset_name']) ?></div>
                                        <small style="color: #64748b;"><?= htmlspecialchars($wo['line_code']) ?></small>
                                    </td>
                                    <td style="padding: 10px 10px;">
                                        <span style="font-size: 11px; font-weight: 700; color: <?= $wo['maintenance_type'] === 'CORRECTIVE' ? '#dc2626' : '#2563eb' ?>;">
                                            <?= $wo['maintenance_type'] === 'CORRECTIVE' ? '🚨 Arızi' : '📅 Planlı' ?>
                                        </span>
                                    </td>
                                    <td style="padding: 10px 10px;">
                                        <span style="font-size: 10.5px; font-weight: 800; padding: 2px 6px; border-radius: 4px; background: <?= $wo['priority'] === 'CRITICAL' ? '#fee2e2' : '#f1f5f9' ?>; color: <?= $wo['priority'] === 'CRITICAL' ? '#991b1b' : '#475569' ?>;">
                                            <?= htmlspecialchars($wo['priority']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 10px 10px;">
                                        <span style="font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 12px; background: <?= $stBg ?>; color: <?= $stColor ?>;">
                                            <?= htmlspecialchars($wo['status']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 10px 10px; color: #64748b;">
                                        <?= htmlspecialchars($wo['technician_name'] ?: 'Atanmadı') ?>
                                    </td>
                                    <td style="padding: 10px 10px; text-align: right;">
                                        <a href="/stok-takip/public/maintenance/work-order?id=<?= $wo['id'] ?>" class="button button-primary" style="padding: 4px 10px; font-size: 11.5px; text-decoration: none;">
                                            İncele &rarr;
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- SAĞ: YAKLAŞAN PERİYODİK BAKIMLAR (PREVENTIVE) -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0;">📅 PERİYODİK BAKIM TAKİBİ</h3>
                <span style="font-size: 12px; color: #64748b;"><?= count($plans) ?> Plan</span>
            </div>

            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($plans as $p): ?>
                    <?php
                        $barColor = '#10b981';
                        if ($p['is_overdue']) { $barColor = '#ef4444'; }
                        elseif ($p['is_approaching']) { $barColor = '#f59e0b'; }
                    ?>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px;">
                            <div style="font-size: 13px; font-weight: 800; color: #0f172a;">
                                <?= htmlspecialchars($p['title']) ?>
                            </div>
                            <span style="font-size: 10.5px; font-weight: 800; color: <?= $barColor ?>;">
                                <?= $p['is_overdue'] ? '🚨 GECİKTİ' : ($p['is_approaching'] ? '⚠️ YAKLAŞTI' : 'NORMAL') ?>
                            </span>
                        </div>

                        <div style="font-size: 11.5px; color: #64748b; margin-bottom: 6px;">
                            <?= htmlspecialchars($p['asset_name']) ?> &bull; <?= htmlspecialchars($p['plan_code']) ?>
                        </div>

                        <div style="width: 100%; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; margin-bottom: 6px;">
                            <div style="width: <?= min(100, $p['progress_pct']) ?>%; height: 100%; background: <?= $barColor ?>; border-radius: 3px;"></div>
                        </div>

                        <div style="display: flex; justify-content: space-between; font-size: 11px; color: #64748b;">
                            <span>İlerleme: <b>%<?= $p['progress_pct'] ?></b></span>
                            <span><?= htmlspecialchars($p['trigger_type']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

</main>

<!-- MODAL: YENİ BAKIM / ARIZA BİLDİRİMİ -->
<div id="modal-new-wo" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(3px);">
    <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 520px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); margin: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 20px;">🚨</span>
                <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: #0f172a;">Yeni Arıza Bildir / Bakım Talebi Aç</h3>
            </div>
            <button type="button" onclick="document.getElementById('modal-new-wo').style.display='none'" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">&times;</button>
        </div>

        <form action="/stok-takip/public/maintenance/create" method="POST">
            <?= CsrfService::renderInput() ?>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">Arızalanan Ekipman / Makine *</label>
                <select name="asset_id" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
                    <option value="">Ekipman Seçiniz...</option>
                    <?php foreach ($assets as $a): ?>
                        <option value="<?= $a['id'] ?>">
                            [<?= htmlspecialchars($a['line_code']) ?>] <?= htmlspecialchars($a['asset_name']) ?> (<?= htmlspecialchars($a['asset_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div>
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">Bakım Türü *</label>
                    <select name="maintenance_type" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
                        <option value="CORRECTIVE">🚨 Arızi Bakım (Duruşlu)</option>
                        <option value="PREVENTIVE">📅 Periyodik Bakım</option>
                        <option value="EMERGENCY">🔥 Acil Müdahale</option>
                        <option value="CALIBRATION">⚙️ Kalibrasyon</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">Öncelik *</label>
                    <select name="priority" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
                        <option value="CRITICAL">🔴 CRITICAL (Hat Durdu)</option>
                        <option value="HIGH" selected>🟠 HIGH (Yüksek)</option>
                        <option value="MEDIUM">🟡 MEDIUM (Orta)</option>
                        <option value="LOW">⚪ LOW (Düşük)</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">Arıza Kategorisi *</label>
                <select name="failure_category" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
                    <option value="MEKANIK">Mekanik / Vakum / Rulman</option>
                    <option value="ELEKTRIK">Elektrik / Röle / Sigorta</option>
                    <option value="OTOMASYON">Otomasyon / PLC / Sensör</option>
                    <option value="ISITMA">Isıtma &amp; Sıcaklık (Laminatör)</option>
                    <option value="OPTIK">Optik &amp; Test (Flaş Lamba)</option>
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">Arıza Açıklaması &amp; Belirtiler *</label>
                <textarea name="failure_description" required rows="3" placeholder="Arıza belirtilerini, görülen ses/koku/alarm kodunu yazınız..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-family: system-ui, sans-serif;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="document.getElementById('modal-new-wo').style.display='none'" class="button" style="padding: 8px 16px;">İptal</button>
                <button type="submit" class="button button-primary" style="padding: 8px 18px; background: #dc2626; border-color: #b91c1c;">İş Emrini Başlat</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>

