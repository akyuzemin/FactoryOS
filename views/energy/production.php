<?php
$pageTitle = 'Üretim Performansı';
$activePage = 'energy-production';

// Defensive HTML Escaping Helper
$h = static function (mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
};

// Defensive Number Formatting Helper
$fNum = static function (mixed $value, int $decimals = 1): string {
    return number_format((float)($value ?? 0.0), $decimals, ',', '.');
};

// Defensive variable extraction
$kpis = is_array($kpis ?? null) ? $kpis : [];
$targetDate = (string)($kpis['target_date'] ?? date('Y-m-d'));
$linesList = is_array($lines ?? null) 
    ? $lines 
    : (is_array($productionLines ?? null) ? $productionLines : []);

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="page-kicker">Spesifik Enerji Tüketimi (SEC) &amp; Hat Verimliliği</p>
            <h1>🏭 Üretim Performansı</h1>
            <p class="page-description"><em>"Hangi üretim hattı ne kadar verimli?"</em> — Panel başına harcanan elektrik enerjisi (SEC), nominal kapasite kullanım oranları ve hat bazlı optimizasyon.</p>
        </div>
        <div class="header-badges">
            <form method="GET" action="/stok-takip/public/energy/production" class="energy-date-picker-form">
                <label for="date-select" class="sr-only">Tarih:</label>
                <input type="date" id="date-select" name="date" value="<?= $h($targetDate) ?>" class="energy-date-input" max="<?= date('Y-m-d') ?>">
                <button type="submit" class="button button-secondary button-sm">Filtrele</button>
            </form>
        </div>
    </header>

    <!-- 4 KPI KARTI -->
    <div class="report-metric-strip" style="margin-bottom: 24px;">
        <div class="report-metric-card">
            <span class="report-metric-title">Toplam Üretilen Panel</span>
            <span class="report-metric-value"><?= $fNum($kpis['today_panels_qty'] ?? 0, 0) ?> <small style="font-size: 12px; color: var(--text-muted);">AD</small></span>
        </div>
        <div class="report-metric-card">
            <span class="report-metric-title">Toplam Üretilen Güç</span>
            <span class="report-metric-value text-amber"><?= $fNum(($kpis['today_total_wp'] ?? 0) / 1000, 1) ?> <small style="font-size: 12px; color: var(--text-muted);">kWp</small></span>
        </div>
        <div class="report-metric-card">
            <span class="report-metric-title">Ortalama Tesis SEC</span>
            <span class="report-metric-value text-pink"><?= $fNum($kpis['kwh_per_panel'] ?? 0, 2) ?> <small style="font-size: 12px; color: var(--text-muted);">kWh/Panel</small></span>
        </div>
        <div class="report-metric-card">
            <span class="report-metric-title">En Verimli Hat</span>
            <span class="report-metric-value text-green" style="font-size: 16px;">Test &amp; Flaş (1.19)</span>
        </div>
    </div>

    <!-- 1. ÜRETİM HATLARI DETAYLI TABLOSU -->
    <div class="card chart-panel-card" style="margin-bottom: 24px;">
        <div class="card-header-clean">
            <div>
                <h3 class="card-title">Üretim Hatları Detaylı Enerji &amp; SEC Tablosu</h3>
                <p class="card-subtitle">Nominal güç, günlük tüketim, üretilen panel adedi ve spesifik enerji tüketimi</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Hat Kodu &amp; Adı</th>
                        <th>Tesis Bölgesi</th>
                        <th>Nominal Güç</th>
                        <th>Günlük Tüketim</th>
                        <th>Üretilen Panel</th>
                        <th>SEC (kWh/Panel)</th>
                        <th>Ortalama Güç</th>
                        <th>Kapasite Yükü</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($linesList)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 16px;">
                                Bu tarih için hat üretim ve enerji verisi bulunamadı.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($linesList as $line): ?>
                            <?php 
                            $lName = $line['name'] ?? 'Hat';
                            $lCode = $line['code'] ?? '';
                            $lZone = $line['zone_name'] ?? 'Üretim';
                            $lNom = $line['nominal_power_kw'] ?? 0;
                            $lKwh = $line['today_kwh'] ?? 0;
                            $lPanels = $line['today_panels_qty'] ?? $line['panels_qty'] ?? 0;
                            $lSec = $line['sec_kwh_per_panel'] ?? 0;
                            $lAvgPower = $line['avg_power_kw'] ?? 0;
                            $lLoadPct = (float)($line['load_factor_percentage'] ?? $line['load_rate_percent'] ?? 0);
                            ?>
                            <tr>
                                <td>
                                    <strong><?= $h($lName) ?></strong><br>
                                    <small style="color: var(--text-muted);"><?= $h($lCode) ?></small>
                                </td>
                                <td><?= $h($lZone) ?></td>
                                <td><?= $fNum($lNom, 0) ?> kW</td>
                                <td><strong><?= $fNum($lKwh, 1) ?> kWh</strong></td>
                                <td><?= $fNum($lPanels, 0) ?> AD</td>
                                <td>
                                    <strong class="text-pink"><?= $fNum($lSec, 2) ?></strong>
                                </td>
                                <td><?= $fNum($lAvgPower, 1) ?> kW</td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <div style="flex: 1; height: 6px; background: rgba(255,255,255,0.06); border-radius: 3px; overflow: hidden; min-width: 50px;">
                                            <div style="width: <?= min(100, max(0, $lLoadPct)) ?>%; height: 100%; background: #06b6d4;"></div>
                                        </div>
                                        <span style="font-size: 11px;">%<?= $fNum($lLoadPct, 0) ?></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>