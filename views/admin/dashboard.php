<?php
$pageTitle = 'Yönetici Dashboard & Raporlama';
$activePage = 'admin-dashboard';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content" style="max-width: 1360px; padding: 24px 32px;">

    <!-- 1. ÜST BAŞLIK VE ZAMAN FİLTRESİ -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; background: #ffffff; padding: 18px 24px; border-radius: 14px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 22px;">📊</span>
                <h1 style="font-size: 22px; font-weight: 800; color: #0f172a; margin: 0;">Yönetici Dashboard &amp; Raporlama Paneli</h1>
            </div>
            <p style="font-size: 13px; color: #64748b; margin: 2px 0 0 30px;">
                Fabrika geneli üretim, maliyet, kalite, sevkiyat ve enerji izlenebilirlik özeti &bull; <strong style="color: #2563eb;"><?= htmlspecialchars($dateInfo['label']) ?></strong>
            </p>
        </div>

        <!-- HIZLI ZAMAN FİLTRELERİ -->
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <a href="?range=today" class="button" style="padding: 7px 14px; font-size: 12.5px; font-weight: 700; text-decoration: none; <?= ($dateInfo['range'] === 'today') ? 'background: #0f172a; color: white;' : 'background: #f1f5f9; color: #475569;' ?>">
                Bugün
            </a>
            <a href="?range=this_week" class="button" style="padding: 7px 14px; font-size: 12.5px; font-weight: 700; text-decoration: none; <?= ($dateInfo['range'] === 'this_week') ? 'background: #0f172a; color: white;' : 'background: #f1f5f9; color: #475569;' ?>">
                Bu Hafta
            </a>
            <a href="?range=this_month" class="button" style="padding: 7px 14px; font-size: 12.5px; font-weight: 700; text-decoration: none; <?= ($dateInfo['range'] === 'this_month') ? 'background: #0f172a; color: white;' : 'background: #f1f5f9; color: #475569;' ?>">
                Bu Ay
            </a>

            <!-- Özel Tarih Aralığı Formu -->
            <form method="GET" action="" style="display: flex; gap: 6px; align-items: center; margin-left: 6px;">
                <input type="hidden" name="range" value="custom">
                <input type="date" name="start_date" value="<?= htmlspecialchars($dateInfo['start_date']) ?>" style="padding: 6px 10px; font-size: 12px; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                <span style="font-size: 12px; color: #64748b;">-</span>
                <input type="date" name="end_date" value="<?= htmlspecialchars($dateInfo['end_date']) ?>" style="padding: 6px 10px; font-size: 12px; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                <button type="submit" class="button button-primary" style="padding: 6px 12px; font-size: 12px; font-weight: 700;">Uygula</button>
                <a href="/stok-takip/public/admin/dashboard" class="button" style="padding: 6px 12px; font-size: 12px; font-weight: 700; background: #e2e8f0; color: #475569; text-decoration: none;" title="Filtreleri Temizle / Yenile">↻ Yenile</a>
            </form>
        </div>
    </div>

    <!-- 2. YÖNETİCİ KPI KARTLARI (10 METRİK) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">

        <!-- KPI 1: Üretilen Panel & Wp -->
        <a href="/stok-takip/public/finished-goods" style="text-decoration: none; color: inherit; display: block; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: transform 0.15s, box-shadow 0.15s;">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase;">📦 DÖNEMSEL ÜRETİM</div>
            <div style="font-size: 22px; font-weight: 900; color: #0f172a; margin: 4px 0 2px 0;">
                <?= number_format((int)$kpis['total_produced_panels'], 0, ',', '.') ?> <small style="font-size: 13px; font-weight: 600;">Panel</small>
            </div>
            <div style="font-size: 11.5px; color: #16a34a; font-weight: 600;">
                Toplam: <?= number_format((float)$kpis['total_wp'], 0, ',', '.') ?> Wp
            </div>
        </a>

        <!-- KPI 2: Aktif İş Emirleri -->
        <a href="/stok-takip/public/mes" style="text-decoration: none; color: inherit; display: block; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: transform 0.15s, box-shadow 0.15s;">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase;">🏭 AKTİF İŞ EMİRLERİ</div>
            <div style="font-size: 22px; font-weight: 900; color: #2563eb; margin: 4px 0 2px 0;">
                <?= number_format((int)$kpis['active_work_orders_count'], 0, ',', '.') ?> <small style="font-size: 13px; font-weight: 600;">İş Emri</small>
            </div>
            <div style="font-size: 11.5px; color: #64748b;">Çalışan &amp; Hazır statüde</div>
        </a>

        <!-- KPI 3: Hedef Gerçekleşme % -->
        <a href="/stok-takip/public/mes" style="text-decoration: none; color: inherit; display: block; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: transform 0.15s, box-shadow 0.15s;">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase;">📈 ÜRETİM GERÇEKLEŞME</div>
            <div style="font-size: 22px; font-weight: 900; color: #059669; margin: 4px 0 2px 0;">
                %<?= number_format((float)$kpis['target_realization_rate'], 1, ',', '.') ?>
            </div>
            <div style="font-size: 11.5px; color: #64748b;">Planlanan / Gerçekleşen</div>
        </a>

        <!-- KPI 4: Hammadde Tüketimi -->
        <a href="/stok-takip/public/stock-movements" style="text-decoration: none; color: inherit; display: block; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: transform 0.15s, box-shadow 0.15s;">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase;">🧱 HAMMADDE TÜKETİMİ</div>
            <div style="font-size: 20px; font-weight: 900; color: #d97706; margin: 4px 0 2px 0;">
                <?= number_format((float)$kpis['total_material_qty'], 0, ',', '.') ?> <small style="font-size: 13px; font-weight: 600;">Adet/Birim</small>
            </div>
            <div style="font-size: 11.5px; color: #64748b;">
                Maliyet: ₺<?= number_format((float)$kpis['total_material_cost_tl'], 2, ',', '.') ?>
            </div>
        </a>

        <!-- KPI 5: Toplam İmalat Maliyeti -->
        <a href="/stok-takip/public/recipes" style="text-decoration: none; color: inherit; display: block; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: transform 0.15s, box-shadow 0.15s;">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase;">💰 TOPLAM İMALAT MALİYETİ</div>
            <div style="font-size: 20px; font-weight: 900; color: #4338ca; margin: 4px 0 2px 0;">
                ₺<?= number_format((float)$kpis['total_manufacturing_cost_tl'], 2, ',', '.') ?>
            </div>
            <div style="font-size: 11.5px; color: #4338ca; font-weight: 600;">BOM Snapshot + Enerji</div>
        </a>

        <!-- KPI 6: Kalite Kontrol -->
        <a href="/stok-takip/public/finished-goods" style="text-decoration: none; color: inherit; display: block; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: transform 0.15s, box-shadow 0.15s;">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase;">✅ KALİTE KONTROL</div>
            <div style="font-size: 22px; font-weight: 900; color: #16a34a; margin: 4px 0 2px 0;">
                %<?= number_format((float)$kpis['quality_pass_rate_pct'], 1, ',', '.') ?> <small style="font-size: 13px; font-weight: 600;">Onay</small>
            </div>
            <div style="font-size: 11px; color: #64748b; margin-top: 4px; display: grid; grid-template-columns: 1fr 1fr; gap: 4px;">
                <div>Onay: <b style="color: #16a34a;"><?= (int)$kpis['quality_approved_count'] ?></b></div>
                <div>Red: <b style="color: #dc2626;"><?= (int)$kpis['quality_rejected_count'] ?></b> (%<?= number_format((float)$kpis['quality_reject_rate_pct'], 1, ',', '.') ?>)</div>
            </div>
        </a>

        <!-- KPI 7: Sevkiyat Miktarı -->
        <a href="/stok-takip/public/shipments" style="text-decoration: none; color: inherit; display: block; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: transform 0.15s, box-shadow 0.15s;">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase;">🚚 SEVK EDİLEN PANEL</div>
            <div style="font-size: 22px; font-weight: 900; color: #0284c7; margin: 4px 0 2px 0;">
                <?= number_format((int)$kpis['total_shipped_panels'], 0, ',', '.') ?> <small style="font-size: 13px; font-weight: 600;">Panel</small>
            </div>
            <div style="font-size: 11px; color: #64748b; display: flex; justify-content: space-between; margin-top: 4px;">
                <span>Tamamlanan: <b><?= (int)$kpis['completed_shipments_count'] ?></b></span>
                <span>Taslak: <b><?= (int)$kpis['pending_shipments_count'] ?></b></span>
            </div>
        </a>

        <!-- KPI 8: Toplam Enerji Tüketimi -->
        <a href="/stok-takip/public/energy-cost" style="text-decoration: none; color: inherit; display: block; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: transform 0.15s, box-shadow 0.15s;">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase;">⚡ ENERJİ TÜKETİMİ</div>
            <div style="font-size: 20px; font-weight: 900; color: #7c3aed; margin: 4px 0 2px 0;">
                <?= number_format((float)$kpis['total_energy_kwh'], 1, ',', '.') ?> <small style="font-size: 13px; font-weight: 600;">kWh</small>
            </div>
            <div style="font-size: 11px; color: #64748b; display: flex; flex-direction: column; gap: 2px;">
                <span>Maliyet: <b>₺<?= number_format((float)$kpis['total_energy_cost_tl'], 2, ',', '.') ?></b></span>
                <span>Puant Payı: <b>%<?= number_format((float)$kpis['peak_cost_share_pct'], 1, ',', '.') ?></b></span>
            </div>
        </a>

        <!-- KPI 9: Ortalama Enerji / Panel (SEC) -->
        <a href="/stok-takip/public/energy-cost" style="text-decoration: none; color: inherit; display: block; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: transform 0.15s, box-shadow 0.15s;">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase;">📈 ORTALAMA ENERJİ / PANEL</div>
            <div style="font-size: 22px; font-weight: 900; color: #0284c7; margin: 4px 0 2px 0;">
                <?= number_format((float)$kpis['sec_kwh_per_panel'], 2, ',', '.') ?> <small style="font-size: 12px; font-weight: 600;">kWh/pnl</small>
            </div>
            <div style="font-size: 11.5px; color: #64748b;">Maliyet: <b>₺<?= number_format((float)$kpis['energy_cost_per_panel'], 2, ',', '.') ?></b> / panel</div>
        </a>

        <!-- KPI 10: Aktif Enerji Alarmları -->
        <a href="/stok-takip/public/energy-alerts" style="text-decoration: none; display: block; background: #ffffff; border: 1px solid <?= ($kpis['active_alerts_count'] > 0) ? '#fca5a5' : '#e2e8f0' ?>; border-radius: 12px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: transform 0.15s, box-shadow 0.15s; cursor: pointer;">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase;">🚨 AKTİF ALARMLAR</div>
            <div style="font-size: 22px; font-weight: 900; color: <?= ($kpis['active_alerts_count'] > 0) ? '#dc2626' : '#16a34a' ?>; margin: 4px 0 2px 0;">
                <?= (int)$kpis['active_alerts_count'] ?> <small style="font-size: 13px; font-weight: 600;">Olay</small>
            </div>
            <div style="font-size: 11.5px; color: <?= ($kpis['active_alerts_count'] > 0) ? '#dc2626' : '#64748b' ?>; font-weight: <?= ($kpis['active_alerts_count'] > 0) ? '700' : '400' ?>; display: flex; justify-content: space-between; align-items: center;">
                <span><?= ($kpis['active_alerts_count'] > 0) ? 'İncelemek için tıkla' : 'Yeni bildirim yok' ?></span>
                <span style="font-size: 14px;">➔</span>
            </div>
        </a>

        <!-- KPI 11: Fabrika OEE -->
        <a href="/stok-takip/public/oee" style="text-decoration: none; display: block; background: #ffffff; border: 1px solid #c7d2fe; border-radius: 12px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: transform 0.15s, box-shadow 0.15s; cursor: pointer;">
            <div style="font-size: 11.5px; font-weight: 700; color: #4338ca; text-transform: uppercase;">⚙️ TOPLAM ETKİNLİK (OEE)</div>
            <div style="font-size: 22px; font-weight: 900; color: #3730a3; margin: 4px 0 2px 0;">
                %<?= number_format($oeeSummary['overall_oee'] ?? 0.0, 1, ',', '.') ?>
            </div>
            <div style="font-size: 11.5px; color: #64748b; display: flex; justify-content: space-between; align-items: center;">
                <span>A: %<?= number_format($oeeSummary['overall_availability'] ?? 0.0, 0) ?> | P: %<?= number_format($oeeSummary['overall_performance'] ?? 0.0, 0) ?> | Q: %<?= number_format($oeeSummary['overall_quality'] ?? 0.0, 0) ?></span>
                <span style="font-size: 14px; color: #4338ca;">➔</span>
            </div>
        </a>

    </div>

    <!-- 2.5 YÖNETİCİ ÖZETİ (HIGHLIGHTS) -->
    <div style="display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 24px;">
        <?php
            $bestLine = null;
            $maxRealization = -1;
            
            $worstQualityLine = null;
            $minQuality = 101;
            
            foreach ($lineMatrix as $lm) {
                if ($lm['realization_rate_pct'] > $maxRealization) {
                    $maxRealization = $lm['realization_rate_pct'];
                    $bestLine = $lm['line_name'];
                }
                if ($lm['quality_pass_rate_pct'] < $minQuality && $lm['produced_panels'] > 0) {
                    $minQuality = $lm['quality_pass_rate_pct'];
                    $worstQualityLine = $lm['line_name'];
                }
            }
            $highestMaterial = !empty($topMaterials) ? $topMaterials[0]['material_name'] : '-';
        ?>
        <div style="flex: 1; min-width: 200px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 16px; display: flex; align-items: center; gap: 12px;">
            <div style="font-size: 24px;">🏆</div>
            <div>
                <div style="font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase;">En İyi Üretim Hattı</div>
                <div style="font-size: 13.5px; font-weight: 800; color: #0f172a;"><?= htmlspecialchars($bestLine ?? '-') ?> (%<?= $maxRealization ?>)</div>
            </div>
        </div>
        <div style="flex: 1; min-width: 200px; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 10px; padding: 12px 16px; display: flex; align-items: center; gap: 12px;">
            <div style="font-size: 24px;">⚠️</div>
            <div>
                <div style="font-size: 11px; color: #9f1239; font-weight: 700; text-transform: uppercase;">En Düşük Kalite Oranı</div>
                <div style="font-size: 13.5px; font-weight: 800; color: #881337;"><?= htmlspecialchars($worstQualityLine ?? '-') ?> (Onay: %<?= $minQuality > 100 ? '-' : $minQuality ?>)</div>
            </div>
        </div>
        <div style="flex: 1; min-width: 200px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 12px 16px; display: flex; align-items: center; gap: 12px;">
            <div style="font-size: 24px;">📦</div>
            <div>
                <div style="font-size: 11px; color: #b45309; font-weight: 700; text-transform: uppercase;">En Çok Tüketilen Hammadde</div>
                <div style="font-size: 13.5px; font-weight: 800; color: #78350f;"><?= htmlspecialchars($highestMaterial) ?></div>
            </div>
        </div>
    </div>

    <!-- 3. AKSİYON GEREKTİRENLER (ACTION REQUIRED) -->
    <?php
        $overduePmCount = (int)($maintenanceSummary['overdue_pm_count'] ?? 0);
        $criticalFaultsCount = (int)($maintenanceSummary['critical_faults_count'] ?? 0);
        $openWoCount = (int)($maintenanceSummary['open_work_orders_count'] ?? 0);
        $openDtCount = count($openDowntimes ?? []);
        $criticalStockCount = count($criticalStocks ?? []);
        $hasAnyAction = ($criticalStockCount > 0 || $openDtCount > 0 || $overduePmCount > 0 || $criticalFaultsCount > 0);
    ?>
    <div style="background: #ffffff; border: 1px solid <?= $hasAnyAction ? '#fecaca' : '#e2e8f0' ?>; border-radius: 14px; padding: 20px 24px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); margin-bottom: 24px;">
        
        <!-- BAŞLIK VE ÖZET SAYAÇLARI -->
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 14px; margin-bottom: 18px; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 22px;">⚠️</span>
                <div>
                    <h3 style="font-size: 16px; font-weight: 800; color: #991b1b; margin: 0;">
                        Aksiyon Gerektirenler
                    </h3>
                    <p style="font-size: 12px; color: #64748b; margin: 2px 0 0 0;">
                        Minimum seviye altındaki kritik stoklar, geciken bakım planları ve canlı üretim hattı duruşları
                    </p>
                </div>
            </div>
            
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <!-- Kritik Stok Sayacı -->
                <span style="font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 6px; <?= $criticalStockCount > 0 ? 'background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;' : 'background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;' ?>">
                    📦 Kritik Stok: <strong><?= $criticalStockCount ?></strong>
                </span>

                <!-- Geciken Bakım Sayacı -->
                <span style="font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 6px; <?= $overduePmCount > 0 ? 'background: #fef3c7; color: #92400e; border: 1px solid #fde68a;' : 'background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;' ?>">
                    🛠️ Geciken Bakım: <strong><?= $overduePmCount ?></strong>
                </span>

                <!-- Aktif Duruş Sayacı -->
                <span style="font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 6px; <?= $openDtCount > 0 ? 'background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;' : 'background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;' ?>">
                    🛑 Aktif Duruş: <strong><?= $openDtCount ?></strong>
                </span>
            </div>
        </div>

        <!-- ÜST KARTLAR: BAKIM & CANLI HAT DURUŞLARI (2 KOLONLU GRID) -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; margin-bottom: 20px;">
            
            <!-- 1. BAKIM VE TPM DURUMU -->
            <div style="background: #fafafa; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px 18px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px solid #f3f4f6; padding-bottom: 8px;">
                    <div style="font-size: 12.5px; font-weight: 700; color: #374151; display: flex; align-items: center; gap: 6px;">
                        <span>⚙️</span> Bakım &amp; TPM Aksiyonları
                    </div>
                    <a href="/stok-takip/public/maintenance" style="font-size: 11.5px; color: #2563eb; font-weight: 600; text-decoration: none;">Bakım Paneli &rarr;</a>
                </div>

                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <?php if ($overduePmCount > 0 || $criticalFaultsCount > 0): ?>
                        <?php if ($overduePmCount > 0): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 8px 12px; font-size: 12px; color: #92400e;">
                                <span>⚠️ <strong><?= $overduePmCount ?></strong> adet periyodik bakım planı gecikmede!</span>
                                <a href="/stok-takip/public/maintenance" style="font-weight: 700; color: #b45309; text-decoration: none; font-size: 11px;">İncele &rarr;</a>
                            </div>
                        <?php endif; ?>
                        <?php if ($criticalFaultsCount > 0): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 8px 12px; font-size: 12px; color: #991b1b;">
                                <span>🔴 <strong><?= $criticalFaultsCount ?></strong> adet acil/kritik arıza iş emri açık!</span>
                                <a href="/stok-takip/public/maintenance" style="font-weight: 700; color: #dc2626; text-decoration: none; font-size: 11px;">Müdahale Et &rarr;</a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 8px 12px; font-size: 12px; color: #166534; display: flex; align-items: center; gap: 6px;">
                            <span>✅</span> <span>Periyodik bakımlar güncel, açık kritik arıza bulunmuyor.</span>
                        </div>
                    <?php endif; ?>

                    <div style="display: flex; justify-content: space-between; font-size: 11.5px; color: #6b7280; padding-top: 4px;">
                        <span>Açık İş Emirleri: <strong><?= $openWoCount ?></strong></span>
                        <span>MTBF: <strong><?= number_format((float)($maintenanceSummary['mtbf_hours'] ?? 0), 1) ?> sa</strong> | MTTR: <strong><?= number_format((float)($maintenanceSummary['mttr_minutes'] ?? 0), 1) ?> dk</strong></span>
                    </div>
                </div>
            </div>

            <!-- 2. CANLI HAT DURUŞLARI -->
            <div style="background: #fafafa; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px 18px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px solid #f3f4f6; padding-bottom: 8px;">
                    <div style="font-size: 12.5px; font-weight: 700; color: #374151; display: flex; align-items: center; gap: 6px;">
                        <span>🛑</span> Aktif Üretim Hat Duruşları
                    </div>
                    <a href="/stok-takip/public/oee" style="font-size: 11.5px; color: #2563eb; font-weight: 600; text-decoration: none;">OEE &amp; Duruş &rarr;</a>
                </div>

                <?php if (empty($openDowntimes)): ?>
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px 14px; font-size: 12px; color: #166534; display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 16px;">✅</span>
                        <span>Şu anda aktif üretim duruşu bulunmuyor. Tüm hatlar operasyonel.</span>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 6px; max-height: 120px; overflow-y: auto;">
                        <?php foreach ($openDowntimes as $odt): 
                            $sec = (int)($odt['current_duration_seconds'] ?? 0);
                            $mins = max(1, (int)round($sec / 60));
                            $durStr = ($mins >= 60) ? (floor($mins / 60) . ' sa ' . ($mins % 60) . ' dk') : ($mins . ' dk');
                        ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; background: #ffffff; border: 1px solid #fecaca; border-left: 4px solid <?= htmlspecialchars($odt['color_hex'] ?? '#ef4444') ?>; border-radius: 6px; padding: 6px 10px; font-size: 12px;">
                                <div>
                                    <strong style="color: #0f172a;"><?= htmlspecialchars($odt['line_name']) ?></strong>
                                    <span style="font-size: 11px; color: #64748b; margin-left: 4px;">(<?= htmlspecialchars($odt['reason_name']) ?>)</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 11px; font-weight: 700; color: #dc2626;">⏱ <?= $durStr ?></span>
                                    <span style="font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 4px; <?= !empty($odt['is_planned']) ? 'background: #eff6ff; color: #1d4ed8;' : 'background: #fee2e2; color: #991b1b;' ?>">
                                        <?= !empty($odt['is_planned']) ? 'PLANLI' : 'ARIZA' ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- 3. ALT TABLO: KRİTİK STOKLAR (STOK AÇIĞI) -->
        <div style="border-top: 1px solid #f1f5f9; padding-top: 14px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <div style="font-size: 13px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                    <span>🧱</span> Minimum Seviye Altındaki Kritik Hammaddeler
                </div>
                <a href="/stok-takip/public/materials" style="font-size: 11.5px; color: #2563eb; font-weight: 600; text-decoration: none;">Tüm Malzeme Listesi &rarr;</a>
            </div>

            <?php if (empty($criticalStocks)): ?>
                <div style="display: flex; align-items: center; gap: 10px; padding: 14px 18px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; color: #166534; font-size: 12.5px;">
                    <span>✅</span>
                    <span><strong>Harika!</strong> Minimum stok seviyesinin altına düşmüş kritik hammadde bulunmuyor.</span>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 12.5px; text-align: left;">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Malzeme Adı &amp; Kodu</th>
                                <th style="padding: 8px 12px; font-weight: 700; color: #475569;">Kategori</th>
                                <th style="padding: 8px 12px; font-weight: 700; color: #475569; text-align: right;">Mevcut Stok</th>
                                <th style="padding: 8px 12px; font-weight: 700; color: #475569; text-align: right;">Min. Stok</th>
                                <th style="padding: 8px 12px; font-weight: 700; color: #dc2626; text-align: right;">Eksik Miktar (Açık)</th>
                                <th style="padding: 8px 12px; font-weight: 700; color: #475569; text-align: center;">Durum</th>
                                <th style="padding: 8px 12px; font-weight: 700; color: #475569; text-align: right;">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($criticalStocks as $cs): 
                                $currStock = (float)($cs['current_stock'] ?? 0);
                                $minStock = (float)($cs['min_stock'] ?? 0);
                                $deficit = (float)($cs['deficit'] ?? 0);
                                $isOutOfStock = ($currStock <= 0);
                            ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 10px 12px;">
                                        <a href="/stok-takip/public/materials/show?id=<?= (int)$cs['material_id'] ?>" style="font-weight: 700; color: #0f172a; text-decoration: none;">
                                            <?= htmlspecialchars($cs['material_name']) ?>
                                        </a>
                                        <span style="font-size: 11px; color: #64748b; display: block; font-family: monospace;"><?= htmlspecialchars($cs['material_code']) ?></span>
                                    </td>
                                    <td style="padding: 10px 12px; color: #475569;">
                                        <?= htmlspecialchars($cs['category_name']) ?>
                                    </td>
                                    <td style="padding: 10px 12px; text-align: right; font-weight: 700; color: <?= $isOutOfStock ? '#dc2626' : '#d97706' ?>;">
                                        <?= number_format($currStock, 2, ',', '.') ?> <small style="color: #64748b;"><?= htmlspecialchars($cs['unit_symbol']) ?></small>
                                    </td>
                                    <td style="padding: 10px 12px; text-align: right; font-weight: 600; color: #475569;">
                                        <?= number_format($minStock, 2, ',', '.') ?> <small style="color: #64748b;"><?= htmlspecialchars($cs['unit_symbol']) ?></small>
                                    </td>
                                    <td style="padding: 10px 12px; text-align: right; font-weight: 800; color: #dc2626;">
                                        -<?= number_format($deficit, 2, ',', '.') ?> <small style="color: #64748b;"><?= htmlspecialchars($cs['unit_symbol']) ?></small>
                                    </td>
                                    <td style="padding: 10px 12px; text-align: center;">
                                        <?php if ($isOutOfStock): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 7px; border-radius: 4px; font-size: 10.5px; font-weight: 800; background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;">
                                                ⚫ TÜKENDİ
                                            </span>
                                        <?php else: ?>
                                            <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 7px; border-radius: 4px; font-size: 10.5px; font-weight: 800; background: #fef3c7; color: #92400e; border: 1px solid #fde68a;">
                                                🔴 KRİTİK
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 10px 12px; text-align: right;">
                                        <a href="/stok-takip/public/purchase-requests/create?material_id=<?= (int)$cs['material_id'] ?>" style="font-size: 11px; font-weight: 700; color: #2563eb; text-decoration: none; padding: 3px 8px; background: #eff6ff; border-radius: 5px; border: 1px solid #bfdbfe; display: inline-block;">
                                            Talep Aç &rarr;
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- 4. DETAY BÖLÜMÜ: ÜRETİM TRENDİ (SOL) & EN ÇOK TÜKETİLEN HAMMADDELER (SAĞ) -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">

        <!-- SOL: Üretim Trend Çubukları -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.02);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0;">
                    📈 Üretim Günlük Dağılım Trendi
                </h3>
                <a href="/stok-takip/public/finished-goods" style="font-size: 12px; color: #2563eb; font-weight: 600; text-decoration: none;">Tüm Paneller &rarr;</a>
            </div>

            <?php if (empty($trend)): ?>
                <p style="color: #64748b; font-size: 13px; text-align: center; padding: 30px 0;">Seçilen tarih aralığında üretim kaydı bulunmuyor.</p>
            <?php else: 
                $maxQty = max(array_column($trend, 'qty')) ?: 1;
                $maxPlan = max(array_column($trend, 'planned')) ?: 1;
                $overallMax = max($maxQty, $maxPlan);
            ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($trend as $t): 
                        $pctAct = round(($t['qty'] / $overallMax) * 100);
                        $pctPlan = round(($t['planned'] / $overallMax) * 100);
                    ?>
                        <div>
                            <div style="display: flex; justify-content: space-between; font-size: 12px; color: #475569; margin-bottom: 4px;">
                                <span style="font-weight: 600;"><?= date('d.m.Y', strtotime($t['p_date'])) ?></span>
                                <span style="font-weight: 700; color: #0f172a;">Üretilen: <?= number_format((int)$t['qty']) ?> / Planlanan: <?= number_format((int)$t['planned']) ?></span>
                            </div>
                            <div style="position: relative; height: 12px; background: #f1f5f9; border-radius: 4px; overflow: hidden; display: flex; flex-direction: column; gap: 1px;">
                                <div style="width: <?= $pctPlan ?>%; height: 5px; background: #cbd5e1; border-radius: 2px;" title="Planlanan"></div>
                                <div style="width: <?= $pctAct ?>%; height: 6px; background: linear-gradient(90deg, #2563eb 0%, #3b82f6 100%); border-radius: 2px;" title="Gerçekleşen"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: 14px; display: flex; gap: 16px; font-size: 11.5px; color: #64748b; justify-content: center;">
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <div style="width: 12px; height: 6px; background: #cbd5e1; border-radius: 2px;"></div> Planlanan
                    </div>
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <div style="width: 12px; height: 6px; background: #3b82f6; border-radius: 2px;"></div> Gerçekleşen
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- SAĞ: En Çok Tüketilen Hammaddeler -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.02);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0;">
                    🧱 En Çok Tüketilen 5 Hammadde
                </h3>
                <a href="/stok-takip/public/stock-movements" style="font-size: 12px; color: #2563eb; font-weight: 600; text-decoration: none;">Stok Hareketleri &rarr;</a>
            </div>

            <?php if (empty($topMaterials)): ?>
                <p style="color: #64748b; font-size: 13px; text-align: center; padding: 30px 0;">Seçilen dönemde hammadde çıkış hareketi bulunmuyor.</p>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                            <th style="padding: 10px 12px; font-weight: 700; color: #475569;">Malzeme Adı</th>
                            <th style="padding: 10px 12px; font-weight: 700; color: #475569; text-align: right;">Miktar</th>
                            <th style="padding: 10px 12px; font-weight: 700; color: #475569; text-align: right;">Toplam Tutar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topMaterials as $mat): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 10px 12px;">
                                    <a href="/stok-takip/public/materials/edit?id=<?= (int)$mat['material_id'] ?>" style="font-weight: 700; color: #0f172a; text-decoration: none;">
                                        <?= htmlspecialchars($mat['material_name']) ?>
                                    </a>
                                    <span style="font-size: 11px; color: #64748b; display: block; font-family: monospace;"><?= htmlspecialchars($mat['material_code']) ?></span>
                                </td>
                                <td style="padding: 10px 12px; text-align: right; font-weight: 700; color: #0f172a;">
                                    <?= number_format((float)$mat['total_qty'], 2, ',', '.') ?> <small style="color: #64748b;"><?= htmlspecialchars($mat['unit_symbol']) ?></small>
                                </td>
                                <td style="padding: 10px 12px; text-align: right; font-weight: 800; color: #059669;">
                                    ₺<?= number_format((float)$mat['total_cost_tl'], 2, ',', '.') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>

    <!-- 4. ÜRETİM HATLARI PERFORMANS VE KARŞILAŞTIRMA MATRİSİ -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px 24px; box-shadow: 0 2px 6px rgba(0,0,0,0.02);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px;">
            <div>
                <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0;">🏭 Üretim Hatları Performans &amp; Karşılaştırma Matrisi</h3>
                <p style="font-size: 12px; color: #64748b; margin: 2px 0 0 0;">Üretim hatlarının çıktı adetleri, kalite başarı oranları ve Spesifik Enerji Tüketimleri (SEC)</p>
            </div>
            <a href="/stok-takip/public/energy-cost" class="button button-small" style="font-size: 12px; font-weight: 600; text-decoration: none;">Detaylı Enerji Analizi &rarr;</a>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13.5px; text-align: left;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569;">Hat Kodu &amp; Adı</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-align: right;">Üretilen Panel</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-align: right;">Hedef Gerçekleşme</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-align: right;">Kalite Onay Oranı</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-align: right;">Toplam Tüketim (kWh)</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #0f172a; text-align: right;">Ortalama Enerji / Panel (SEC)</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #0f172a; text-align: right;">Birim Enerji Maliyeti</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lineMatrix)): ?>
                        <tr>
                            <td colspan="7" style="padding: 20px; text-align: center; color: #64748b;">Kayıtlı üretim hattı bulunamadı.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($lineMatrix as $lm): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 14px 16px; font-weight: 700; color: #0f172a;">
                                    <?= htmlspecialchars($lm['line_name']) ?>
                                    <span style="font-size: 11px; font-weight: 400; color: #64748b; display: block; font-family: monospace;"><?= htmlspecialchars($lm['line_code']) ?></span>
                                </td>
                                <td style="padding: 14px 16px; text-align: right; font-weight: 800; color: #0f172a;">
                                    <?= number_format((int)$lm['produced_panels'], 0, ',', '.') ?> <small style="font-size: 11px; font-weight: 500;">Panel</small>
                                </td>
                                <td style="padding: 14px 16px; text-align: right; font-weight: 700; color: <?= $lm['realization_rate_pct'] >= 90 ? '#16a34a' : '#d97706' ?>;">
                                    %<?= number_format((float)$lm['realization_rate_pct'], 1, ',', '.') ?>
                                </td>
                                <td style="padding: 14px 16px; text-align: right;">
                                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 800; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;">
                                        %<?= number_format((float)$lm['quality_pass_rate_pct'], 1, ',', '.') ?>
                                    </span>
                                </td>
                                <td style="padding: 14px 16px; text-align: right; font-weight: 600; color: #475569;">
                                    <?= number_format((float)$lm['total_kwh'], 1, ',', '.') ?> kWh
                                </td>
                                <td style="padding: 14px 16px; text-align: right; font-weight: 800; color: #2563eb;">
                                    <?= number_format((float)$lm['sec_kwh_per_panel'], 2, ',', '.') ?> <small>kWh/pnl</small>
                                </td>
                                <td style="padding: 14px 16px; text-align: right; font-weight: 800; color: #16a34a;">
                                    ₺<?= number_format((float)$lm['unit_energy_cost_tl_panel'], 2, ',', '.') ?> <small>/panel</small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
