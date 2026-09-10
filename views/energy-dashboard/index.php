<?php
$pageTitle = 'Enerji Dashboard';
$activePage = 'energy-dashboard';

$kpis = $kpis ?? [
    'today_grid_import_kwh'       => 0.0,
    'today_solar_gen_kwh'        => 0.0,
    'today_cost_tl'              => 0.0,
    'today_solar_value_tl'       => 0.0,
    'solar_self_sufficiency_rate'=> 0.0,
    'co2_saved_kg'               => 0.0,
    'kwh_per_panel'              => 0.0,
    'active_critical_alerts'     => 0,
    'target_date'                => date('Y-m-d'),
];
$hourly = $hourly ?? [];
$latestAlerts = $latestAlerts ?? [];

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<style>
/* LIGHT SAAS ENERGY DASHBOARD THEME */
body {
    background-color: #f1f5f9 !important;
    color: #1e293b !important;
}

.main-content {
    background-color: #f1f5f9;
    padding: 24px 32px;
    min-height: 100vh;
}

/* TOPBAR */
.dashboard-topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.topbar-title h1 {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
    line-height: 1.2;
}

.topbar-breadcrumb {
    font-size: 12.5px;
    color: #64748b;
    margin-top: 4px;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* 5 KPI CARDS GRID */
.kpi-cards-grid-5 {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

@media (max-width: 1200px) {
    .kpi-cards-grid-5 {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .kpi-cards-grid-5 {
        grid-template-columns: 1fr;
    }
}

.kpi-white-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
    border: 1px solid #edf2f7;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.kpi-circle-icon {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.kpi-circle-blue {
    background: #e0f2fe;
    color: #0284c7;
}

.kpi-circle-green {
    background: #dcfce7;
    color: #16a34a;
}

.kpi-circle-cyan {
    background: #e0f2fe;
    color: #0891b2;
}

.kpi-circle-purple {
    background: #f3e8ff;
    color: #9333ea;
}

.kpi-circle-orange {
    background: #fef3c7;
    color: #d97706;
}

.kpi-info-block {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.kpi-title-label {
    font-size: 11.5px;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 2px;
}

.kpi-big-value {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.1;
    display: flex;
    align-items: baseline;
    gap: 3px;
}

.kpi-big-value small {
    font-size: 11.5px;
    font-weight: 600;
    color: #64748b;
}

.kpi-sub-label {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* 2 COLUMN GRID */
.dashboard-grid-2col {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

@media (max-width: 992px) {
    .dashboard-grid-2col {
        grid-template-columns: 1fr;
    }
}

.dashboard-grid-half {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
}

@media (max-width: 992px) {
    .dashboard-grid-half {
        grid-template-columns: 1fr;
    }
}

.saas-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 20px 22px;
    border: 1px solid #edf2f7;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.saas-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f1f5f9;
}

.saas-card-title {
    font-size: 14.5px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.saas-card-subtitle {
    font-size: 11.5px;
    color: #64748b;
    margin: 2px 0 0 0;
}
</style>

<main class="main-content">

    <!-- 1. ÜST BAŞLIK VE TARİH FİLTRESİ -->
    <div class="dashboard-topbar">
        <div class="topbar-title">
            <h1>⚡ Enerji Dashboard</h1>
            <div class="topbar-breadcrumb">
                <span>Enerji &amp; Tesis Yönetimi</span> &bull; <span>Genel Durum Özeti</span>
            </div>
        </div>

        <div class="topbar-right">
            <span style="background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; font-size: 12px; padding: 6px 12px; border-radius: 20px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                <span style="width: 7px; height: 7px; border-radius: 50%; background: #16a34a; display: inline-block;"></span> Canlı Sayaç
            </span>

            <form method="GET" action="/stok-takip/public/energy-dashboard" style="display: flex; gap: 8px; align-items: center;">
                <input type="date" id="date-select" name="date" value="<?= htmlspecialchars($kpis['target_date'] ?? date('Y-m-d')) ?>" class="form-input" style="padding: 7px 12px; font-size: 12.5px; max-width: 150px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px;" max="<?= date('Y-m-d') ?>">
                <button type="submit" class="button button-secondary button-sm" style="padding: 7px 14px; font-size: 12.5px;">Filtrele</button>
            </form>
        </div>
    </div>

    <!-- 2. ÜST BÖLÜM: 5 SADE KPI KARTI -->
    <div class="kpi-cards-grid-5">

        <!-- 1. Şebeke Tüketimi -->
        <div class="kpi-white-card">
            <div class="kpi-circle-icon kpi-circle-purple">⚡</div>
            <div class="kpi-info-block">
                <span class="kpi-title-label">Şebeke Tüketimi</span>
                <div class="kpi-big-value">
                    <?= number_format((float)($kpis['today_grid_import_kwh'] ?? 0), 1, ',', '.') ?>
                    <small>kWh</small>
                </div>
                <span class="kpi-sub-label">Şebekeden çekilen</span>
            </div>
        </div>

        <!-- 2. GES Üretimi -->
        <div class="kpi-white-card">
            <div class="kpi-circle-icon kpi-circle-green">☀️</div>
            <div class="kpi-info-block">
                <span class="kpi-title-label">GES Üretimi</span>
                <div class="kpi-big-value" style="color: #16a34a;">
                    <?= number_format((float)($kpis['today_solar_gen_kwh'] ?? 0), 1, ',', '.') ?>
                    <small>kWh</small>
                </div>
                <span class="kpi-sub-label">1.2 MWp Çatı Santrali</span>
            </div>
        </div>

        <!-- 3. GES Karşılama Oranı -->
        <div class="kpi-white-card">
            <div class="kpi-circle-icon kpi-circle-cyan">🔄</div>
            <div class="kpi-info-block">
                <span class="kpi-title-label">GES Karşılama</span>
                <div class="kpi-big-value" style="color: #0284c7;">
                    %<?= number_format((float)($kpis['solar_self_sufficiency_rate'] ?? 0), 0) ?>
                </div>
                <span class="kpi-sub-label">Öz yeterlilik oranı</span>
            </div>
        </div>

        <!-- 4. Güncel Maliyet -->
        <div class="kpi-white-card">
            <div class="kpi-circle-icon kpi-circle-blue">₺</div>
            <div class="kpi-info-block">
                <span class="kpi-title-label">Güncel Maliyet</span>
                <div class="kpi-big-value">
                    ₺<?= number_format((float)($kpis['today_cost_tl'] ?? 0), 2, ',', '.') ?>
                </div>
                <span class="kpi-sub-label">EPDK 3 zamanlı tarife</span>
            </div>
        </div>

        <!-- 5. Tasarruf Potansiyeli -->
        <div class="kpi-white-card">
            <div class="kpi-circle-icon kpi-circle-orange">💡</div>
            <div class="kpi-info-block">
                <span class="kpi-title-label">Tasarruf Potansiyeli</span>
                <div class="kpi-big-value" style="color: #d97706;">
                    ₺64.564
                    <small>/ay</small>
                </div>
                <span class="kpi-sub-label">Puant yük kaydırma</span>
            </div>
        </div>

    </div>

    <!-- 3. ANA BÖLÜM: SAATLİK YÜK GRAFİĞİ (SOL) & ENERJİ DENGESİ (SAĞ) -->
    <div class="dashboard-grid-2col">

        <!-- SOL: Saatlik Enerji Yük Eğrisi -->
        <div class="saas-card">
            <div class="saas-card-header">
                <div>
                    <h3 class="saas-card-title">📊 Bugünkü Enerji Yük Eğrisi (24 Saat)</h3>
                    <p class="saas-card-subtitle">Şebeke tüketimi (Mor Bar), Çatı GES üretimi (Yeşil Çizgi) ve Puant saatleri (17:00-22:00)</p>
                </div>
                <div style="display: flex; gap: 12px; font-size: 11.5px;">
                    <span style="display: flex; align-items: center; gap: 5px; color: #64748b;">
                        <span style="width: 10px; height: 10px; background: #8b5cf6; border-radius: 2px;"></span> Şebeke
                    </span>
                    <span style="display: flex; align-items: center; gap: 5px; color: #64748b;">
                        <span style="width: 10px; height: 10px; background: #10b981; border-radius: 50%;"></span> Çatı GES
                    </span>
                </div>
            </div>

            <?php
            $maxHourlyKwh = 10.0;
            foreach ($hourly as $h) {
                $maxHourlyKwh = max($maxHourlyKwh, (float)($h['grid_kwh'] ?? 0), (float)($h['solar_kwh'] ?? 0));
            }
            $chartHeight = 155;
            $chartWidth = 640;
            $barWidth = 14;
            ?>

            <div style="width: 100%; overflow-x: auto;">
                <svg viewBox="0 0 <?= $chartWidth ?> <?= $chartHeight + 28 ?>" style="width: 100%; height: auto; display: block;">
                    <!-- Tarife Arka Planları -->
                    <rect x="0" y="0" width="<?= ($chartWidth / 24) * 6 ?>" height="<?= $chartHeight ?>" fill="#f8fafc" />
                    <rect x="<?= ($chartWidth / 24) * 6 ?>" y="0" width="<?= ($chartWidth / 24) * 11 ?>" height="<?= $chartHeight ?>" fill="#ffffff" />
                    <!-- Puant Saatleri (17:00 - 22:00) -->
                    <rect x="<?= ($chartWidth / 24) * 17 ?>" y="0" width="<?= ($chartWidth / 24) * 5 ?>" height="<?= $chartHeight ?>" fill="#fef2f2" opacity="0.8" />
                    <rect x="<?= ($chartWidth / 24) * 22 ?>" y="0" width="<?= ($chartWidth / 24) * 2 ?>" height="<?= $chartHeight ?>" fill="#f8fafc" />

                    <!-- Kılavuz Çizgileri -->
                    <?php for ($g = 0; $g <= 3; $g++): 
                        $y = $chartHeight - ($g * ($chartHeight / 3));
                    ?>
                        <line x1="0" y1="<?= $y ?>" x2="<?= $chartWidth ?>" y2="<?= $y ?>" stroke="#e2e8f0" stroke-width="1" stroke-dasharray="<?= $g === 0 ? '0' : '3 3' ?>" />
                    <?php endfor; ?>

                    <!-- Saatlik Barlar -->
                    <?php 
                    $solarPoints = [];
                    foreach ($hourly as $i => $h):
                        $x = ($i * ($chartWidth / 24)) + (($chartWidth / 24 - $barWidth) / 2);
                        $gridH = $maxHourlyKwh > 0 ? (((float)($h['grid_kwh'] ?? 0)) / $maxHourlyKwh) * $chartHeight : 0;
                        $solarH = $maxHourlyKwh > 0 ? (((float)($h['solar_kwh'] ?? 0)) / $maxHourlyKwh) * $chartHeight : 0;
                        $gridY = $chartHeight - $gridH;
                        $solarY = $chartHeight - $solarH;

                        $solarPoints[] = sprintf('%.1f,%.1f', $x + ($barWidth / 2), $solarY);
                        $isPuant = (($h['hour'] ?? 0) >= 17 && ($h['hour'] ?? 0) < 22);
                    ?>
                        <rect x="<?= $x ?>" y="<?= $gridY ?>" width="<?= $barWidth ?>" height="<?= $gridH ?>" rx="3" fill="<?= $isPuant ? '#ef4444' : '#8b5cf6' ?>" fill-opacity="0.85">
                            <title><?= sprintf('%02d:00 | Şebeke: %.1f kWh %s', $h['hour'] ?? $i, $h['grid_kwh'] ?? 0, $isPuant ? '(PUANT DÖNEMİ)' : '') ?></title>
                        </rect>

                        <?php if ($i % 3 === 0): ?>
                            <text x="<?= $x + ($barWidth / 2) ?>" y="<?= $chartHeight + 16 ?>" fill="#64748b" font-size="9.5" text-anchor="middle" font-family="'Plus Jakarta Sans', sans-serif"><?= sprintf('%02d:00', $h['hour'] ?? $i) ?></text>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <!-- Solar Çizgisi (Yeşil) -->
                    <?php if (!empty($solarPoints)): ?>
                        <polyline points="<?= implode(' ', $solarPoints) ?>" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                        <?php foreach ($solarPoints as $sp): list($px, $py) = explode(',', $sp); ?>
                            <circle cx="<?= $px ?>" cy="<?= $py ?>" r="2.5" fill="#10b981" />
                        <?php endforeach; ?>
                    <?php endif; ?>
                </svg>
            </div>
        </div>

        <!-- SAĞ: Enerji Dengesi & Öz Yeterlilik -->
        <div class="saas-card" style="display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div class="saas-card-header">
                    <h3 class="saas-card-title">☀️ Enerji Dengesi</h3>
                    <span style="font-size: 11px; color: #16a34a; font-weight: 600;">Temiz Enerji</span>
                </div>

                <div style="text-align: center; margin: 12px 0 16px 0;">
                    <div style="font-size: 36px; font-weight: 800; color: #16a34a; line-height: 1;">
                        %<?= number_format((float)($kpis['solar_self_sufficiency_rate'] ?? 0), 0) ?>
                    </div>
                    <span style="font-size: 11.5px; color: #64748b; font-weight: 600; margin-top: 3px; display: block;">
                        GES Karşılama Oranı
                    </span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 8px; font-size: 12.5px;">
                    <div style="display: flex; justify-content: space-between; padding-bottom: 5px; border-bottom: 1px solid #f1f5f9;">
                        <span style="color: #16a34a; font-weight: 600;">☀️ Çatı GES:</span>
                        <strong><?= number_format((float)($kpis['today_solar_gen_kwh'] ?? 0), 0, ',', '.') ?> kWh</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding-bottom: 5px; border-bottom: 1px solid #f1f5f9;">
                        <span style="color: #6366f1; font-weight: 600;">⚡ Şebeke:</span>
                        <strong><?= number_format((float)($kpis['today_grid_import_kwh'] ?? 0), 0, ',', '.') ?> kWh</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding-bottom: 5px; border-bottom: 1px solid #f1f5f9;">
                        <span style="color: #64748b;">🌱 Karbon Tasarrufu:</span>
                        <strong style="color: #16a34a;">-<?= number_format((float)($kpis['co2_saved_kg'] ?? 0), 0) ?> kg CO₂</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #64748b;">Birim Tüketim (SEC):</span>
                        <strong style="color: #0f172a;"><?= number_format((float)($kpis['kwh_per_panel'] ?? 0), 2) ?> kWh/Panel</strong>
                    </div>
                </div>
            </div>

            <div style="margin-top: 14px; padding-top: 10px; border-top: 1px solid #f1f5f9;">
                <a href="/stok-takip/public/energy/consumption" class="button button-sm button-secondary" style="width: 100%; text-align: center; justify-content: center; font-size: 12px;">
                    Detaylı Tüketim Analizi &rarr;
                </a>
            </div>
        </div>

    </div>

    <!-- 4. ALT BÖLÜM: 2 SADE ÖZET KARTI (UYARILAR & TASARRUF) -->
    <div class="dashboard-grid-half">

        <!-- SOL: Önemli Uyarılar (Top 3) -->
        <div class="saas-card">
            <div class="saas-card-header">
                <h3 class="saas-card-title">🚨 Önemli Uyarılar</h3>
                <span class="status-badge <?= ((int)($kpis['active_critical_alerts'] ?? 0) > 0) ? 'status-badge-danger' : 'status-badge-success' ?>" style="font-size: 10.5px;">
                    <?= ((int)($kpis['active_critical_alerts'] ?? 0) > 0) ? ($kpis['active_critical_alerts'] . ' Kritik Alarm') : 'Normal' ?>
                </span>
            </div>

            <div style="display: flex; flex-direction: column; gap: 8px;">
                <?php if (empty($latestAlerts)): ?>
                    <div style="padding: 14px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; color: #166534; font-size: 12.5px; text-align: center;">
                        ✓ Tüm üretim hatları ve sayaçlar normal çalışma toleransında.
                    </div>
                <?php else: ?>
                    <?php 
                    $shownAlerts = array_slice($latestAlerts, 0, 3);
                    foreach ($shownAlerts as $alt): 
                        $isCrit = (($alt['severity'] ?? '') === 'CRITICAL');
                    ?>
                        <div style="padding: 9px 12px; border-radius: 6px; background: <?= $isCrit ? '#fef2f2' : '#fffbeb' ?>; border-left: 3px solid <?= $isCrit ? '#ef4444' : '#f59e0b' ?>; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="font-size: 12px; color: #0f172a; display: block;">
                                    <?= htmlspecialchars($alt['title'] ?? $alt['rule_name'] ?? 'Yük Sınırı Aşımı') ?>
                                </strong>
                                <span style="font-size: 10.5px; color: #64748b;">
                                    <?= htmlspecialchars($alt['line_name'] ?? $alt['meter_name'] ?? 'Tesis') ?> &bull; <?= htmlspecialchars(substr($alt['created_at'] ?? 'Bugün', 11, 5)) ?>
                                </span>
                            </div>
                            <span class="status-badge <?= $isCrit ? 'status-badge-danger' : 'status-badge-warning' ?>" style="font-size: 9.5px;">
                                <?= $isCrit ? 'Kritik' : 'Uyarı' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div style="margin-top: 12px; text-align: right;">
                <a href="/stok-takip/public/energy/alerts" style="font-size: 11.5px; color: #2563eb; font-weight: 600; text-decoration: none;">
                    Tüm Alarmları Gör (<?= count($latestAlerts) ?> aktif olay) &rarr;
                </a>
            </div>
        </div>

        <!-- SAĞ: Tasarruf Fırsatları (Top 2) -->
        <div class="saas-card">
            <div class="saas-card-header">
                <h3 class="saas-card-title">💡 Öncelikli Tasarruf Fırsatları</h3>
                <span class="status-badge status-badge-info" style="font-size: 10.5px;">
                    +₺81.574 / Ay
                </span>
            </div>

            <div style="display: flex; flex-direction: column; gap: 8px;">
                
                <!-- Fırsat 1 -->
                <div style="padding: 10px 12px; border-radius: 6px; background: #f0fdf4; border-left: 3px solid #16a34a; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong style="font-size: 12px; color: #0f172a; display: block;">
                            Puant Saat Yük Kaydırma (17:00 - 22:00)
                        </strong>
                        <span style="font-size: 11px; color: #64748b;">
                            Laminatör &amp; test yükünün %25'ini geceye aktarın.
                        </span>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 14px; font-weight: 800; color: #16a34a;">+₺64.564</span>
                        <span style="font-size: 10px; color: #64748b; display: block;">/ ay</span>
                    </div>
                </div>

                <!-- Fırsat 2 -->
                <div style="padding: 10px 12px; border-radius: 6px; background: #eff6ff; border-left: 3px solid #2563eb; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong style="font-size: 12px; color: #0f172a; display: block;">
                            Gece Baz Yük &amp; Standby Optimizasyonu
                        </strong>
                        <span style="font-size: 11px; color: #64748b;">
                            Boşta çalışan kompresör ve aydınlatma otomasyonu.
                        </span>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 14px; font-weight: 800; color: #2563eb;">+₺17.010</span>
                        <span style="font-size: 10px; color: #64748b; display: block;">/ ay</span>
                    </div>
                </div>

            </div>

            <div style="margin-top: 12px; text-align: right;">
                <a href="/stok-takip/public/energy/cost" style="font-size: 11.5px; color: #2563eb; font-weight: 600; text-decoration: none;">
                    Detaylı Maliyet Analizi &rarr;
                </a>
            </div>
        </div>

    </div>

</main>
