<?php
$pageTitle = 'Raporlar';
$activePage = 'energy-reports';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="page-kicker">Geçmiş Trendler &amp; Dönemsel Karşılaştırma</p>
            <h1>📈 Enerji Raporları</h1>
            <p class="page-description"><em>"Geçmiş veriler bize ne söylüyor?"</em> — Günlük ve haftalık enerji tüketim trendleri, güneş üretim katkısı, maliyet değişimleri ve SEC performans dökümü.</p>
        </div>
        <div class="header-badges">
            <div class="filter-preset-wrap" style="display: flex; gap: 6px; align-items: center;">
                <a href="/stok-takip/public/energy/reports?range=7days" 
                   class="button button-sm <?= ($report['days_count'] <= 7) ? 'button-primary' : 'button-secondary' ?>">Son 7 Gün</a>
                <a href="/stok-takip/public/energy/reports?range=30days" 
                   class="button button-sm <?= ($report['days_count'] > 7) ? 'button-primary' : 'button-secondary' ?>">Son 30 Gün</a>
            </div>

            <form method="GET" action="/stok-takip/public/energy/reports" class="energy-date-picker-form">
                <input type="hidden" name="range" value="custom">
                <label for="start-date" class="sr-only">Başlangıç:</label>
                <input type="date" id="start-date" name="start_date" value="<?= htmlspecialchars($report['start_date']) ?>" class="energy-date-input" style="width: 130px;">
                <span>-</span>
                <label for="end-date" class="sr-only">Bitiş:</label>
                <input type="date" id="end-date" name="end_date" value="<?= htmlspecialchars($report['end_date']) ?>" class="energy-date-input" style="width: 130px;">
                <button type="submit" class="button button-secondary button-sm">Raporla</button>
            </form>
        </div>
    </header>

    <!-- 4 KPI KARTI -->
    <div class="report-metric-strip" style="margin-bottom: 24px;">
        <div class="report-metric-card">
            <span class="report-metric-title">Dönemlik Toplam Tüketim</span>
            <span class="report-metric-value text-purple"><?= number_format($report['total_grid_kwh'], 1, ',', '.') ?> <small style="font-size: 12px; color: var(--text-muted);">kWh</small></span>
        </div>
        <div class="report-metric-card">
            <span class="report-metric-title">Dönemlik Toplam Maliyet</span>
            <span class="report-metric-value text-cyan">₺<?= number_format($report['total_cost_tl'], 2, ',', '.') ?></span>
        </div>
        <div class="report-metric-card">
            <span class="report-metric-title">Toplam Çatı GES Üretimi</span>
            <span class="report-metric-value text-green"><?= number_format($report['total_solar_kwh'], 1, ',', '.') ?> <small style="font-size: 12px; color: var(--text-muted);">kWh (%<?= number_format($report['average_solar_share_percentage'], 1) ?>)</small></span>
        </div>
        <div class="report-metric-card">
            <span class="report-metric-title">Dönemsel Ortalama SEC</span>
            <span class="report-metric-value text-pink"><?= number_format($report['average_sec_kwh_per_panel'], 2, ',', '.') ?> <small style="font-size: 12px; color: var(--text-muted);">kWh/Panel</small></span>
        </div>
    </div>

    <!-- 1. GÜNLÜK TREND GRAFİĞİ -->
    <div class="card chart-panel-card" style="margin-bottom: 24px;">
        <div class="card-header-clean">
            <div>
                <h3 class="card-title">Dönemsel Şebeke Tüketimi &amp; Solar Üretim Trendi</h3>
                <p class="card-subtitle">Günlük Şebeke İthalatı (Mor) ile Çatı GES Üretimi (Yeşil) değişimi</p>
            </div>
            <div class="chart-legend-wrap">
                <span class="legend-item"><span class="legend-color" style="background: #8b5cf6;"></span> Şebeke (kWh)</span>
                <span class="legend-item"><span class="legend-color" style="background: #10b981;"></span> Çatı GES (kWh)</span>
            </div>
        </div>

        <?php
        $trendRows = array_reverse($report['daily_rows']);
        $maxTrendKwh = 1000.0;
        foreach ($trendRows as $tr) {
            $maxTrendKwh = max($maxTrendKwh, (float)$tr['grid_kwh'], (float)$tr['solar_kwh']);
        }
        $cHeight = 170;
        $cWidth = 650;
        $numDays = max(1, count($trendRows));
        $bWidth = min(24, ($cWidth / $numDays) / 3);
        ?>

        <div class="svg-chart-container">
            <svg viewBox="0 0 <?= $cWidth ?> <?= $cHeight + 35 ?>" class="energy-svg-chart">
                <!-- Kılavuz Çizgileri -->
                <?php for ($g = 0; $g <= 4; $g++): 
                    $y = $cHeight - ($g * ($cHeight / 4));
                    $v = ($maxTrendKwh / 4) * $g;
                ?>
                    <line x1="0" y1="<?= $y ?>" x2="<?= $cWidth ?>" y2="<?= $y ?>" stroke="rgba(255,255,255,0.06)" stroke-width="1" stroke-dasharray="<?= $g === 0 ? '0' : '4' ?>" />
                    <text x="5" y="<?= $y - 3 ?>" fill="#64748b" font-size="9" font-family="Inter, sans-serif"><?= number_format($v, 0) ?></text>
                <?php endfor; ?>

                <?php foreach ($trendRows as $idx => $day): 
                    $cx = ($idx * ($cWidth / $numDays)) + (($cWidth / $numDays) / 2);
                    $gH = $maxTrendKwh > 0 ? ((float)$day['grid_kwh'] / $maxTrendKwh) * $cHeight : 0;
                    $sH = $maxTrendKwh > 0 ? ((float)$day['solar_kwh'] / $maxTrendKwh) * $cHeight : 0;
                    $gY = $cHeight - $gH;
                    $sY = $cHeight - $sH;
                ?>
                    <!-- Grid Bar -->
                    <rect x="<?= $cx - $bWidth - 1 ?>" y="<?= $gY ?>" width="<?= $bWidth ?>" height="<?= $gH ?>" rx="2" fill="#8b5cf6">
                        <title><?= $day['date'] ?> | Şebeke: <?= number_format($day['grid_kwh'], 1) ?> kWh</title>
                    </rect>

                    <!-- Solar Bar -->
                    <rect x="<?= $cx + 1 ?>" y="<?= $sY ?>" width="<?= $bWidth ?>" height="<?= $sH ?>" rx="2" fill="#10b981">
                        <title><?= $day['date'] ?> | GES: <?= number_format($day['solar_kwh'], 1) ?> kWh</title>
                    </rect>

                    <!-- Tarih Etiketi -->
                    <text x="<?= $cx ?>" y="<?= $cHeight + 18 ?>" fill="#94a3b8" font-size="10" text-anchor="middle" font-family="Inter, sans-serif">
                        <?= date('d.m', strtotime($day['date'])) ?>
                    </text>
                <?php endforeach; ?>
            </svg>
        </div>
    </div>

    <!-- 2. GÜNLÜK RAPOR TABLOSU -->
    <div class="card chart-panel-card">
        <div class="card-header-clean">
            <div>
                <h3 class="card-title">📋 Günlük Karşılaştırmalı Rapor Tablosu</h3>
                <p class="card-subtitle"><?= htmlspecialchars($report['start_date']) ?> ile <?= htmlspecialchars($report['end_date']) ?> arasındaki döküm</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Şebeke (kWh)</th>
                        <th>GES Üretimi (kWh)</th>
                        <th>Toplam Enerji (kWh)</th>
                        <th>GES Payı (%)</th>
                        <th>Fatura Maliyeti (TL)</th>
                        <th>Üretilen Panel</th>
                        <th>SEC (kWh/p)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['daily_rows'] as $r): ?>
                        <tr>
                            <td><strong><?= date('d.m.Y', strtotime($r['date'])) ?></strong></td>
                            <td><strong><?= number_format($r['grid_kwh'], 1, ',', '.') ?></strong></td>
                            <td class="text-green"><?= number_format($r['solar_kwh'], 1, ',', '.') ?></td>
                            <td><?= number_format($r['total_kwh'], 1, ',', '.') ?></td>
                            <td>
                                <span class="kpi-tag kpi-tag-amber">%<?= number_format($r['solar_share_percentage'], 1) ?></span>
                            </td>
                            <td class="text-cyan">₺<?= number_format($r['cost_tl'], 2, ',', '.') ?></td>
                            <td><?= number_format($r['panels_qty'], 0) ?> AD</td>
                            <td>
                                <strong class="text-pink"><?= number_format($r['sec_kwh_per_panel'], 2) ?></strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>