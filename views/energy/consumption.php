<?php
$pageTitle = 'Enerji Tüketimi';
$activePage = 'energy-consumption';

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
$hourly = is_array($hourly ?? null) ? $hourly : [];
$shifts = is_array($shifts ?? null) ? $shifts : [];

$breakdownData = is_array($breakdown ?? null) ? $breakdown : [];
$submeters = is_array($breakdownData['submeters'] ?? null) 
    ? $breakdownData['submeters'] 
    : (is_array($breakdownData['items'] ?? null) ? $breakdownData['items'] : []);

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="page-kicker">Yük Dağılımı &amp; Sayaç Telemetrisi</p>
            <h1>⚙️ Enerji Tüketimi</h1>
            <p class="page-description"><em>"Elektriği nerede ve ne kadar tüketiyoruz?"</em> — Ana trafo girişi, 3 vardiya tüketim dağılımı ve bölüm alt sayaçlarının saatlik analizi.</p>
        </div>
        <div class="header-badges">
            <form method="GET" action="/stok-takip/public/energy/consumption" class="energy-date-picker-form">
                <label for="date-select" class="sr-only">Tarih:</label>
                <input type="date" id="date-select" name="date" value="<?= $h($targetDate) ?>" class="energy-date-input" max="<?= date('Y-m-d') ?>">
                <button type="submit" class="button button-secondary button-sm">Filtrele</button>
            </form>
        </div>
    </header>

    <!-- 4 KPI KARTI -->
    <div class="report-metric-strip" style="margin-bottom: 24px;">
        <div class="report-metric-card">
            <span class="report-metric-title">Toplam Şebeke İthalatı</span>
            <span class="report-metric-value text-purple"><?= $fNum($kpis['today_grid_import_kwh'] ?? 0, 1) ?> <small style="font-size: 12px; font-weight: normal; color: var(--text-muted);">kWh</small></span>
        </div>
        <div class="report-metric-card">
            <span class="report-metric-title">Tesis Toplam Tüketimi</span>
            <span class="report-metric-value"><?= $fNum($kpis['today_total_plant_kwh'] ?? 0, 1) ?> <small style="font-size: 12px; font-weight: normal; color: var(--text-muted);">kWh</small></span>
        </div>
        <div class="report-metric-card">
            <span class="report-metric-title">Günlük Enerji Gideri</span>
            <span class="report-metric-value text-cyan">₺<?= $fNum($kpis['today_cost_tl'] ?? 0, 2) ?></span>
        </div>
        <div class="report-metric-card">
            <span class="report-metric-title">Spesifik Tüketim (SEC)</span>
            <span class="report-metric-value text-pink"><?= $fNum($kpis['kwh_per_panel'] ?? 0, 2) ?> <small style="font-size: 12px; font-weight: normal; color: var(--text-muted);">kWh/Panel</small></span>
        </div>
    </div>

    <!-- 1. DETAYLI SAATLİK YÜK GRAFİĞİ -->
    <div class="card chart-panel-card" style="margin-bottom: 24px;">
        <div class="card-header-clean">
            <div>
                <h3 class="card-title">Saatlik Aktif Güç &amp; Tüketim Eğrisi (24 Saat)</h3>
                <p class="card-subtitle">Her saatin şebekeden çekilen ortalama aktif gücü (kW) ve tüketilen enerji (kWh)</p>
            </div>
            <div class="chart-legend-wrap">
                <span class="legend-item"><span class="legend-color" style="background: #8b5cf6;"></span> Tüketim (kWh)</span>
                <span class="legend-item"><span class="legend-color" style="background: #06b6d4;"></span> Ortalama Güç (kW)</span>
            </div>
        </div>

        <?php
        $maxVal = 10.0;
        foreach ($hourly as $hItem) {
            $maxVal = max($maxVal, (float)($hItem['grid_kwh'] ?? 0), (float)($hItem['grid_kw'] ?? 0));
        }
        $chartHeight = 190;
        $chartWidth = 700;
        $barW = 16;
        $hourlyCount = max(1, count($hourly));
        ?>

        <div class="svg-chart-container">
            <svg viewBox="0 0 <?= $chartWidth ?> <?= $chartHeight + 35 ?>" class="energy-svg-chart">
                <!-- Grid Çizgileri -->
                <?php for ($g = 0; $g <= 4; $g++): 
                    $y = $chartHeight - ($g * ($chartHeight / 4));
                    $v = ($maxVal / 4) * $g;
                ?>
                    <line x1="0" y1="<?= $y ?>" x2="<?= $chartWidth ?>" y2="<?= $y ?>" stroke="rgba(255,255,255,0.06)" stroke-width="1" stroke-dasharray="<?= $g === 0 ? '0' : '4' ?>" />
                    <text x="5" y="<?= $y - 3 ?>" fill="#64748b" font-size="9" font-family="Inter, sans-serif"><?= number_format($v, 0) ?></text>
                <?php endfor; ?>

                <?php 
                $kwPoints = [];
                foreach ($hourly as $i => $hItem):
                    $x = ($i * ($chartWidth / $hourlyCount)) + ((($chartWidth / $hourlyCount) - $barW) / 2);
                    $gridKwh = (float)($hItem['grid_kwh'] ?? 0);
                    $gridKw  = (float)($hItem['grid_kw'] ?? 0);
                    $hourNum = (int)($hItem['hour'] ?? $i);
                    $tariffPeriod = (string)($hItem['tariff_period'] ?? 'T1');

                    $kwhH = $maxVal > 0 ? ($gridKwh / $maxVal) * $chartHeight : 0;
                    $kwY = $maxVal > 0 ? $chartHeight - (($gridKw / $maxVal) * $chartHeight) : $chartHeight;
                    $kwhY = $chartHeight - $kwhH;

                    $kwPoints[] = sprintf('%.1f,%.1f', $x + ($barW / 2), $kwY);
                ?>
                    <rect x="<?= $x ?>" y="<?= $kwhY ?>" width="<?= $barW ?>" height="<?= $kwhH ?>" rx="3" fill="#8b5cf6" fill-opacity="0.8">
                        <title><?= sprintf('%02d:00 | %.1f kWh (Tarife: %s)', $hourNum, $gridKwh, $tariffPeriod) ?></title>
                    </rect>

                    <?php if ($i % 2 === 0): ?>
                        <text x="<?= $x + ($barW / 2) ?>" y="<?= $chartHeight + 18 ?>" fill="#94a3b8" font-size="10" text-anchor="middle" font-family="Inter, sans-serif"><?= sprintf('%02d:00', $hourNum) ?></text>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if (!empty($kwPoints)): ?>
                    <polyline points="<?= implode(' ', $kwPoints) ?>" fill="none" stroke="#06b6d4" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                <?php endif; ?>
            </svg>
        </div>
    </div>

    <!-- 2. VARDİYA & ALT SAYAÇ KIRILIMI TABLOLARI -->
    <div class="dashboard-two-cols">
        <!-- Sol: Vardiya Dağılımı -->
        <div class="card chart-panel-card">
            <div class="card-header-clean">
                <div>
                    <h3 class="card-title">3 Vardiya Tüketim Dağılımı</h3>
                    <p class="card-subtitle">Gündüz, Akşam ve Gece vardiyaları enerji performansı</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Vardiya</th>
                            <th>Saat Aralığı</th>
                            <th>Tüketim (kWh)</th>
                            <th>Pay (%)</th>
                            <th>Maliyet (TL)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($shifts)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 16px;">
                                    Bu tarih için vardiya tüketim kaydı bulunamadı.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($shifts as $s): ?>
                                <?php 
                                $sName = $s['shift_name'] ?? $s['name'] ?? 'Vardiya';
                                $sStart = $s['start_time'] ?? '00:00';
                                $sEnd = $s['end_time'] ?? '00:00';
                                $sKwh = $s['total_kwh'] ?? $s['grid_kwh'] ?? 0;
                                $sPct = $s['percentage'] ?? 0;
                                $sCost = $s['cost_tl'] ?? 0;
                                ?>
                                <tr>
                                    <td><strong><?= $h($sName) ?></strong></td>
                                    <td><span style="font-size: 11.5px; color: var(--text-secondary);"><?= $h($sStart) ?> - <?= $h($sEnd) ?></span></td>
                                    <td><strong><?= $fNum($sKwh, 1) ?></strong></td>
                                    <td>
                                        <span class="kpi-tag kpi-tag-purple">%<?= $fNum($sPct, 1) ?></span>
                                    </td>
                                    <td class="text-cyan">₺<?= $fNum($sCost, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sağ: Alt Sayaç Dağılımı -->
        <div class="card chart-panel-card">
            <div class="card-header-clean">
                <div>
                    <h3 class="card-title">Bölüm &amp; Makine Tüketim Payları</h3>
                    <p class="card-subtitle">Alt sayaçların toplam yük içindeki ağırlığı</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sayaç / Hat</th>
                            <th>Tüketim (kWh)</th>
                            <th>Tesis Payı</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($submeters)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 16px;">
                                    Bu tarih için alt sayaç tüketim kaydı bulunamadı.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($submeters as $sub): ?>
                                <?php 
                                $subName = $sub['name'] ?? $sub['code'] ?? 'Sayaç';
                                $subKwh = $sub['kwh'] ?? $sub['total_kwh'] ?? 0;
                                $subPct = (float)($sub['percentage'] ?? 0);
                                $color = $sub['color'] ?? '#8b5cf6';
                                ?>
                                <tr>
                                    <td><strong><?= $h($subName) ?></strong></td>
                                    <td><strong><?= $fNum($subKwh, 1) ?></strong></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div style="flex: 1; height: 6px; background: rgba(255,255,255,0.06); border-radius: 3px; overflow: hidden;">
                                                <div style="width: <?= min(100, max(0, $subPct)) ?>%; height: 100%; background: <?= $h($color) ?>;"></div>
                                            </div>
                                            <span style="font-size: 11px; color: var(--text-secondary); min-width: 38px;">%<?= $fNum($subPct, 1) ?></span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>