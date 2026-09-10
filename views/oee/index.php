<?php

$pageTitle = 'OEE & Hat Performans Yönetimi';

require_once __DIR__ . '/../../app/Services/CsrfService.php';
$csrfToken = CsrfService::getToken();

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<style>
@keyframes pulseRed {
    0%, 100% { background-color: #fef2f2; border-color: #fca5a5; }
    50% { background-color: #fee2e2; border-color: #f87171; }
}
.active-downtime-alert {
    animation: pulseRed 2.5s infinite;
}
.oee-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.oee-metric-val {
    font-size: 32px;
    font-weight: 800;
    line-height: 1.1;
    margin-top: 4px;
}
.oee-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
}
.modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.6);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}
.modal-box {
    background: #ffffff;
    border-radius: 14px;
    width: 100%;
    max-width: 500px;
    padding: 24px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
}
</style>

<main class="main-content">

    <!-- PAGE HEADER -->
    <header class="page-header" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                <p class="page-kicker" style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #4338ca; margin: 0;">Üretim Verimliliği &amp; TPM</p>
                <span class="oee-badge" style="background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe;">
                    OEE Standardı: ISO 22400
                </span>
            </div>
            <h1 style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 0 0 6px 0; letter-spacing: -0.02em;">OEE &amp; Hat Performans Yönetimi</h1>
            <p class="page-description" style="font-size: 13.5px; color: #64748b; margin: 0;">Kullanılabilirlik (A), Performans (P), Kalite (Q) ve makine duruş analizleri.</p>
        </div>

        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <button onclick="openStartDowntimeModal()" class="button" style="background: #ef4444; color: #ffffff; border: none; padding: 9px 16px; font-size: 13px; font-weight: 600; cursor: pointer; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px;">
                <span>🛑</span> Duruş Başlat
            </button>
            <button onclick="openSnapshotModal()" class="button" style="background: #4338ca; color: #ffffff; border: none; padding: 9px 16px; font-size: 13px; font-weight: 600; cursor: pointer; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px;">
                <span>📸</span> Vardiya Snapshot
            </button>
        </div>
    </header>

    <!-- ACTIVE DOWNTIME ALERT BANNER (If any) -->
    <?php if (!empty($openDowntimes)): ?>
        <?php foreach ($openDowntimes as $od): ?>
            <div class="active-downtime-alert" style="border: 1px solid #f87171; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="font-size: 24px;">⚠️</div>
                    <div>
                        <div style="font-weight: 800; color: #991b1b; font-size: 15px;">
                            AKTİF DURUŞ: <?= htmlspecialchars($od['line_name']) ?> (<?= htmlspecialchars($od['line_code']) ?>)
                        </div>
                        <div style="font-size: 13px; color: #b91c1c; margin-top: 2px;">
                            Neden: <strong><?= htmlspecialchars($od['reason_name']) ?></strong> | Başlangıç: <?= date('d.m.Y H:i:s', strtotime($od['started_at'])) ?> 
                            (<?= round($od['current_duration_seconds'] / 60, 1) ?> dakikadır duruyor)
                            <?php if (!empty($od['operator_note'])): ?>
                                — <em>"<?= htmlspecialchars($od['operator_note']) ?>"</em>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div>
                    <button onclick="openEndDowntimeModal(<?= (int)$od['id'] ?>, '<?= htmlspecialchars($od['line_name'], ENT_QUOTES) ?>')" class="button button-primary" style="background: #dc2626; border-color: #b91c1c; padding: 8px 16px; font-size: 13px; font-weight: 700; cursor: pointer;">
                        Duruşu Sonlandır &rarr;
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- DATE & LINE FILTER BAR -->
    <div class="card" style="padding: 14px 18px; margin-bottom: 20px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px;">
        <form method="GET" action="/stok-takip/public/oee" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <label style="font-size: 12.5px; font-weight: 700; color: #475569;">Tarih Aralığı:</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
                <span style="color: #94a3b8;">&ndash;</span>
                <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
            </div>

            <div style="display: flex; align-items: center; gap: 8px;">
                <label style="font-size: 12.5px; font-weight: 700; color: #475569;">Hat:</label>
                <select name="line_id" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
                    <option value="">Tüm Hatlar (Fabrika Ortalaması)</option>
                    <?php foreach ($allLines as $ln): ?>
                        <option value="<?= (int)$ln['id'] ?>" <?= $lineId === (int)$ln['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ln['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 8px; margin-left: auto;">
                <button type="submit" class="button button-primary" style="padding: 7px 16px; font-size: 13px; font-weight: 600;">Uygula</button>
                <a href="/stok-takip/public/oee" class="button" style="padding: 7px 12px; font-size: 13px; text-decoration: none;">Sıfırla</a>
            </div>
        </form>
    </div>

    <!-- 4 MAIN OEE GAUGES CARDS -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px;">
        
        <!-- OVERALL OEE -->
        <div class="oee-card" style="border-left: 4px solid #4338ca;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 12px; font-weight: 800; color: #4338ca; text-transform: uppercase; letter-spacing: 0.05em;">GENEL FABRİKA OEE</span>
                <?php if ($summary['overall_oee'] >= 85.0): ?>
                    <span class="oee-badge" style="background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">🌟 DÜNYA STANDARDI</span>
                <?php elseif ($summary['overall_oee'] >= 70.0): ?>
                    <span class="oee-badge" style="background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe;">✓ İYİ SEVİYE</span>
                <?php else: ?>
                    <span class="oee-badge" style="background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;">⚠️ İYİLEŞTİRME GEREKLİ</span>
                <?php endif; ?>
            </div>
            <div class="oee-metric-val" style="color: #0f172a;"><?= number_format($summary['overall_oee'], 1, ',', '.') ?>%</div>
            <div style="font-size: 12px; color: #64748b; margin-top: 6px;">
                $$\text{OEE} = \text{Availability} \times \text{Performance} \times \text{Quality}$$
            </div>
        </div>

        <!-- AVAILABILITY -->
        <div class="oee-card" style="border-left: 4px solid #0284c7;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 12px; font-weight: 800; color: #0284c7; text-transform: uppercase; letter-spacing: 0.05em;">AVAILABILITY (KULLANILABİLİRLİK)</span>
                <span style="font-size: 11px; color: #64748b; font-weight: 600;">Süre Kaybı</span>
            </div>
            <div class="oee-metric-val" style="color: #0369a1;"><?= number_format($summary['overall_availability'], 1, ',', '.') ?>%</div>
            <div style="font-size: 12px; color: #64748b; margin-top: 6px;">
                Plansız Duruş: <strong><?= number_format($summary['total_unplanned_dt_m'], 1, ',', '.') ?> dk</strong>
            </div>
        </div>

        <!-- PERFORMANCE -->
        <div class="oee-card" style="border-left: 4px solid #d97706;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 12px; font-weight: 800; color: #d97706; text-transform: uppercase; letter-spacing: 0.05em;">PERFORMANCE (PERFORMANS)</span>
                <span style="font-size: 11px; color: #64748b; font-weight: 600;">Hız Kaybı</span>
            </div>
            <div class="oee-metric-val" style="color: #b45309;"><?= number_format($summary['overall_performance'], 1, ',', '.') ?>%</div>
            <div style="font-size: 12px; color: #64748b; margin-top: 6px;">
                Gerçekleşen Üretim: <strong><?= number_format($summary['total_produced_qty'], 0, ',', '.') ?> panel</strong>
            </div>
        </div>

        <!-- QUALITY -->
        <div class="oee-card" style="border-left: 4px solid #16a34a;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 12px; font-weight: 800; color: #16a34a; text-transform: uppercase; letter-spacing: 0.05em;">QUALITY (KALİTE ORANI)</span>
                <span style="font-size: 11px; color: #64748b; font-weight: 600;">Fire Kaybı</span>
            </div>
            <div class="oee-metric-val" style="color: #15803d;"><?= number_format($summary['overall_quality'], 1, ',', '.') ?>%</div>
            <div style="font-size: 12px; color: #64748b; margin-top: 6px;">
                First Pass Yield (Scrap Deduction)
            </div>
        </div>

    </div>

    <!-- HAT BAZINDA OEE VE VERİMLİLİK TABLOSU -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.03); overflow: hidden; margin-bottom: 24px;">
        <div style="padding: 16px 20px; background: #fafafa; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 15px; font-weight: 800; color: #0f172a;">Hat Bazında Canlı OEE &amp; Performans Metrikleri</h3>
            <span style="font-size: 12px; color: #64748b; font-weight: 600;">Vardiya <?= $summary['current_shift_id'] ?> (<?= date('d.m.Y') ?>)</span>
        </div>
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13.5px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">
                    <th style="padding: 12px 18px;">Hat Adı</th>
                    <th style="padding: 12px 18px;">Durum</th>
                    <th style="padding: 12px 18px; text-align: right;">Üretim</th>
                    <th style="padding: 12px 18px; text-align: right;">Çevrim Süresi</th>
                    <th style="padding: 12px 18px; text-align: right;">Availability</th>
                    <th style="padding: 12px 18px; text-align: right;">Performance</th>
                    <th style="padding: 12px 18px; text-align: right;">Quality</th>
                    <th style="padding: 12px 18px; text-align: right;">OEE</th>
                    <th style="padding: 12px 18px; text-align: right;">Plansız Duruş</th>
                    <th style="padding: 12px 18px; text-align: center;">İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($summary['lines'] as $l): ?>
                    <?php $m = $l['metrics']; ?>
                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background='#f8fafc';" onmouseout="this.style.background='transparent';">
                        <td style="padding: 14px 18px;">
                            <div style="font-weight: 800; color: #0f172a; font-size: 13.5px;"><?= htmlspecialchars($l['line_name']) ?></div>
                            <span style="font-size: 11px; color: #64748b; font-family: monospace;"><?= htmlspecialchars($l['line_code']) ?> &bull; <?= $l['nominal_power_kw'] ?> kW</span>
                        </td>
                        <td style="padding: 14px 18px;">
                            <?php if (!empty($m['active_downtime'])): ?>
                                <span class="oee-badge" style="background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;">
                                    🔴 DURUŞTA (<?= htmlspecialchars($m['active_downtime']['reason_name']) ?>)
                                </span>
                            <?php elseif ($l['status'] === 'RUNNING'): ?>
                                <span class="oee-badge" style="background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">
                                    🟢 ÇALIŞIYOR
                                </span>
                            <?php elseif ($l['status'] === 'MAINTENANCE'): ?>
                                <span class="oee-badge" style="background: #fef9c3; color: #854d0e; border: 1px solid #fef08a;">
                                    🟡 BAKIM
                                </span>
                            <?php else: ?>
                                <span class="oee-badge" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;">
                                    ⚪ <?= htmlspecialchars($l['status']) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 14px 18px; text-align: right; font-weight: 700; color: #0f172a;">
                            <?= number_format($m['produced_quantity'], 0, ',', '.') ?> adet
                        </td>
                        <td style="padding: 14px 18px; text-align: right; font-family: monospace; color: #475569;">
                            <?= $m['ideal_cycle_seconds'] ?> sn
                        </td>
                        <td style="padding: 14px 18px; text-align: right; font-weight: 700; color: #0284c7;">
                            <?= number_format($m['availability_pct'], 1, ',', '.') ?>%
                        </td>
                        <td style="padding: 14px 18px; text-align: right; font-weight: 700; color: #d97706;">
                            <?= number_format($m['performance_pct'], 1, ',', '.') ?>%
                        </td>
                        <td style="padding: 14px 18px; text-align: right; font-weight: 700; color: #16a34a;">
                            <?= number_format($m['quality_pct'], 1, ',', '.') ?>%
                        </td>
                        <td style="padding: 14px 18px; text-align: right;">
                            <span style="font-size: 15px; font-weight: 800; color: #4338ca;">
                                <?= number_format($m['oee_pct'], 1, ',', '.') ?>%
                            </span>
                        </td>
                        <td style="padding: 14px 18px; text-align: right; color: #b91c1c; font-weight: 600;">
                            <?= $m['unplanned_downtime_min'] ?> dk
                        </td>
                        <td style="padding: 14px 18px; text-align: center; white-space: nowrap;">
                            <?php if (!empty($m['active_downtime'])): ?>
                                <button onclick="openEndDowntimeModal(<?= (int)$m['active_downtime']['id'] ?>, '<?= htmlspecialchars($l['line_name'], ENT_QUOTES) ?>')" class="button button-small" style="background: #dc2626; color: #ffffff; border: none; padding: 5px 10px; font-size: 12px; cursor: pointer; border-radius: 6px;">
                                    Duruş Bitir
                                </button>
                            <?php else: ?>
                                <button onclick="openStartDowntimeModal(<?= (int)$l['line_id'] ?>)" class="button button-small" style="padding: 5px 10px; font-size: 12px; cursor: pointer; border-radius: 6px;">
                                    Duruş Aç
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 2 KOLONLU ALAN: PARETO VE DURUŞ GEÇMİŞİ -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
        
        <!-- DURUŞ NEDENLERİ PARETO ANALİZİ -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="margin: 0; font-size: 15px; font-weight: 800; color: #0f172a;">Duruş Nedenleri Pareto Dağılımı</h3>
                <span style="font-size: 12px; color: #64748b; font-weight: 600;">Toplam: <?= $pareto['total_minutes'] ?> dk</span>
            </div>

            <?php if (empty($pareto['reasons'])): ?>
                <div style="padding: 30px; text-align: center; color: #94a3b8; font-size: 13.5px;">
                    Seçilen dönemde kaydedilmiş duruş bulunmamaktadır.
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($pareto['reasons'] as $pr): ?>
                        <div>
                            <div style="display: flex; justify-content: space-between; font-size: 12.5px; margin-bottom: 4px;">
                                <span style="font-weight: 700; color: #0f172a;">
                                    <?= htmlspecialchars($pr['name']) ?> 
                                    <span style="color: #64748b; font-weight: 400;">(<?= $pr['occurrence_count'] ?> kez)</span>
                                </span>
                                <span style="font-weight: 700; color: #334155;">
                                    <?= $pr['total_minutes'] ?> dk (<?= $pr['share_pct'] ?>%)
                                </span>
                            </div>
                            <div style="width: 100%; height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden;">
                                <div style="width: <?= min(100, $pr['share_pct']) ?>%; height: 100%; background: <?= htmlspecialchars($pr['color_hex'] ?: '#ef4444') ?>; border-radius: 4px;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- SON DURUŞ KAYITLARI -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="margin: 0; font-size: 15px; font-weight: 800; color: #0f172a;">Son Duruş Olay Günlüğü</h3>
                <span style="font-size: 12px; color: #64748b;">Son <?= count($recentDowntimes) ?> kayıt</span>
            </div>

            <?php if (empty($recentDowntimes)): ?>
                <div style="padding: 30px; text-align: center; color: #94a3b8; font-size: 13.5px;">
                    Duruş kaydı bulunmamaktadır.
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                        <thead>
                            <tr style="border-bottom: 1px solid #e2e8f0; color: #64748b; text-align: left;">
                                <th style="padding: 8px 10px;">Hat</th>
                                <th style="padding: 8px 10px;">Neden</th>
                                <th style="padding: 8px 10px;">Başlangıç</th>
                                <th style="padding: 8px 10px;">Süre</th>
                                <th style="padding: 8px 10px; text-align: center;">Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentDowntimes as $rd): ?>
                                <tr style="border-bottom: 1px solid #f8fafc;">
                                    <td style="padding: 8px 10px; font-weight: 700; color: #0f172a;">
                                        <?= htmlspecialchars($rd['line_code']) ?>
                                    </td>
                                    <td style="padding: 8px 10px;">
                                        <?= htmlspecialchars($rd['reason_name']) ?>
                                    </td>
                                    <td style="padding: 8px 10px; color: #64748b;">
                                        <?= date('d.m H:i', strtotime($rd['started_at'])) ?>
                                    </td>
                                    <td style="padding: 8px 10px; font-weight: 700; color: #334155;">
                                        <?= $rd['duration_seconds'] ? round($rd['duration_seconds'] / 60, 1) . ' dk' : 'Devam ediyor' ?>
                                    </td>
                                    <td style="padding: 8px 10px; text-align: center;">
                                        <?php if ($rd['status'] === 'OPEN'): ?>
                                            <span style="padding: 2px 6px; border-radius: 8px; font-size: 10.5px; font-weight: 700; background: #fee2e2; color: #991b1b;">AÇIK</span>
                                        <?php else: ?>
                                            <span style="padding: 2px 6px; border-radius: 8px; font-size: 10.5px; font-weight: 700; background: #f1f5f9; color: #475569;">KAPALI</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

</main>

<!-- 1. DURUŞ BAŞLAT MODALI -->
<div id="modal-start-downtime" class="modal-overlay">
    <div class="modal-box">
        <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 800; color: #0f172a;">🛑 Makine Duruşu Başlat</h3>
        <form method="POST" action="/stok-takip/public/oee/downtime/start">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            
            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">Üretim Hattı:</label>
                <select name="line_id" id="start-dt-line-id" required style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
                    <?php foreach ($allLines as $l): ?>
                        <option value="<?= (int)$l['id'] ?>"><?= htmlspecialchars($l['name']) ?> (<?= htmlspecialchars($l['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">Duruş Nedeni:</label>
                <select name="reason_id" required style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
                    <?php foreach ($reasons as $rs): ?>
                        <option value="<?= (int)$rs['id'] ?>">
                            <?= $rs['is_planned'] ? '[PLANLI] ' : '[PLANSIZ] ' ?> <?= htmlspecialchars($rs['name']) ?> (<?= htmlspecialchars($rs['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom: 18px;">
                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">Operatör Notu (Opsiyonel):</label>
                <textarea name="operator_note" rows="3" placeholder="Arıza detayları, yapılan müdahale..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeModal('modal-start-downtime')" class="button" style="padding: 8px 16px; font-size: 13px;">İptal</button>
                <button type="submit" class="button button-primary" style="background: #ef4444; border-color: #dc2626; padding: 8px 18px; font-size: 13px; font-weight: 700;">Duruşu Başlat</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. DURUŞ BİTİR MODALI -->
<div id="modal-end-downtime" class="modal-overlay">
    <div class="modal-box">
        <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 800; color: #0f172a;">✅ Duruşu Sonlandır</h3>
        <form method="POST" action="/stok-takip/public/oee/downtime/end">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="downtime_id" id="end-downtime-id" value="">

            <p style="font-size: 13.5px; color: #475569; margin-bottom: 14px;">
                <strong id="end-downtime-line-name"></strong> üzerindeki duruş kapatılacak ve hat tekrar çalışmaya hazır (IDLE/RUNNING) hale gelecektir.
            </p>

            <div style="margin-bottom: 18px;">
                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">Kapanış Notu / Yapılan İşlem:</label>
                <textarea name="operator_note" rows="3" placeholder="Arıza giderildi, sensör temizlendi..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeModal('modal-end-downtime')" class="button" style="padding: 8px 16px; font-size: 13px;">İptal</button>
                <button type="submit" class="button button-primary" style="padding: 8px 18px; font-size: 13px; font-weight: 700;">Duruşu Kapat</button>
            </div>
        </form>
    </div>
</div>

<!-- 3. VARDIYA SNAPSHOT MODALI -->
<div id="modal-snapshot" class="modal-overlay">
    <div class="modal-box">
        <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 800; color: #0f172a;">📸 Vardiya OEE Snapshot Kaydet</h3>
        <p style="font-size: 13px; color: #64748b; margin-bottom: 14px;">
            Seçilen vardiyanın OEE, A, P, Q ve duruş metrikleri hesaplanıp mühürlenecektir.
        </p>
        <form method="POST" action="/stok-takip/public/oee/snapshot">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            
            <div style="margin-bottom: 12px;">
                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 4px;">Tarih:</label>
                <input type="date" name="date" value="<?= date('Y-m-d') ?>" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
            </div>

            <div style="margin-bottom: 12px;">
                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 4px;">Vardiya:</label>
                <select name="shift_id" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
                    <option value="1">1. Vardiya (08:00 - 16:00)</option>
                    <option value="2">2. Vardiya (16:00 - 00:00)</option>
                    <option value="3">3. Vardiya (00:00 - 08:00)</option>
                </select>
            </div>

            <div style="margin-bottom: 18px;">
                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 4px;">Hat:</label>
                <select name="line_id" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
                    <?php foreach ($allLines as $l): ?>
                        <option value="<?= (int)$l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeModal('modal-snapshot')" class="button" style="padding: 8px 16px; font-size: 13px;">İptal</button>
                <button type="submit" class="button button-primary" style="padding: 8px 18px; font-size: 13px; font-weight: 700;">Mühürle &amp; Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
function openStartDowntimeModal(lineId = null) {
    if (lineId) {
        document.getElementById('start-dt-line-id').value = lineId;
    }
    document.getElementById('modal-start-downtime').style.display = 'flex';
}

function openEndDowntimeModal(downtimeId, lineName) {
    document.getElementById('end-downtime-id').value = downtimeId;
    document.getElementById('end-downtime-line-name').textContent = lineName;
    document.getElementById('modal-end-downtime').style.display = 'flex';
}

function openSnapshotModal() {
    document.getElementById('modal-snapshot').style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modal when clicking on overlay background
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
});
</script>

