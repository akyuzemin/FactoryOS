<?php
$pageTitle = 'GES Performansı';
$activePage = 'energy-ges';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="page-kicker">1.2 MWp Çatı Güneş Enerjisi Santrali</p>
            <h1>☀️ Çatı GES Performans Analizi</h1>
            <p class="page-description">Fotovoltaik çatı santrali üretim verileri, öz tüketim oranları ve temiz enerji telemetrisi.</p>
        </div>
        <div class="header-badges">
            <form method="GET" action="/stok-takip/public/energy/ges" class="energy-date-picker-form">
                <label for="date-select" class="sr-only">Tarih:</label>
                <input type="date" id="date-select" name="date" value="<?= htmlspecialchars($kpis['target_date']) ?>" class="energy-date-input" max="<?= date('Y-m-d') ?>">
                <button type="submit" class="button button-secondary button-sm">Filtrele</button>
            </form>
        </div>
    </header>

    <!-- GES KPI METRİKLERİ -->
    <div class="report-metric-strip" style="margin-bottom: 24px;">
        <div class="report-metric-card">
            <span class="report-metric-title">Bugünkü Solar Üretim</span>
            <span class="report-metric-value text-green"><?= number_format($kpis['today_solar_gen_kwh'], 1, ',', '.') ?> <small style="font-size: 12px; color: var(--text-muted);">kWh</small></span>
        </div>
        <div class="report-metric-card">
            <span class="report-metric-title">Öz Tüketim Oranı</span>
            <span class="report-metric-value text-cyan">%<?= number_format($kpis['solar_self_sufficiency_rate'], 1) ?></span>
        </div>
        <div class="report-metric-card">
            <span class="report-metric-title">Sağlanan Ekonomik Katkı</span>
            <span class="report-metric-value text-green">₺<?= number_format($kpis['today_solar_value_tl'], 2, ',', '.') ?></span>
        </div>
        <div class="report-metric-card">
            <span class="report-metric-title">Önlenen CO₂ Salımı</span>
            <span class="report-metric-value text-amber"><?= number_format($kpis['co2_saved_kg'], 0, ',', '.') ?> <small style="font-size: 12px; color: var(--text-muted);">kg CO₂</small></span>
        </div>
    </div>

    <!-- 1. GÜNEŞ IŞIMA VE ÜRETİM GRAFİĞİ -->
    <div class="card chart-panel-card" style="margin-bottom: 24px;">
        <div class="card-header-clean">
            <div>
                <h3 class="card-title">Gündüz Güneş Üretim Profili (06:00 - 20:00)</h3>
                <p class="card-subtitle">Saatlik bazda üretilen temiz enerji ve solar güç seviyeleri</p>
            </div>
            <div class="chart-legend-wrap">
                <span class="legend-item"><span class="legend-color" style="background: #10b981;"></span> Solar Üretim (kWh)</span>
                <span class="legend-item"><span class="legend-color" style="background: #f59e0b;"></span> Anlık Güç (kW)</span>
            </div>
        </div>

        <?php
        $maxSolar = 10.0;
        foreach ($hourly as $h) {
            $maxSolar = max($maxSolar, (float)$h['solar_kwh'], (float)$h['solar_kw']);
        }
        $chartHeight = 190;
        $chartWidth = 700;
        ?>

        <div class="svg-chart-container">
            <svg viewBox="0 0 <?= $chartWidth ?> <?= $chartHeight + 35 ?>" class="energy-svg-chart">
                <?php for ($g = 0; $g <= 4; $g++): 
                    $y = $chartHeight - ($g * ($chartHeight / 4));
                    $v = ($maxSolar / 4) * $g;
                ?>
                    <line x1="0" y1="<?= $y ?>" x2="<?= $chartWidth ?>" y2="<?= $y ?>" stroke="rgba(255,255,255,0.06)" stroke-width="1" stroke-dasharray="<?= $g === 0 ? '0' : '4' ?>" />
                    <text x="5" y="<?= $y - 3 ?>" fill="#64748b" font-size="9" font-family="Inter, sans-serif"><?= number_format($v, 0) ?></text>
                <?php endfor; ?>

                <?php 
                $linePoints = [];
                foreach ($hourly as $i => $h):
                    $x = ($i * ($chartWidth / 24)) + 14;
                    $sKwhH = $maxSolar > 0 ? ((float)$h['solar_kwh'] / $maxSolar) * $chartHeight : 0;
                    $sKwY = $maxSolar > 0 ? $chartHeight - (((float)$h['solar_kw'] / $maxSolar) * $chartHeight) : $chartHeight;
                    $sKwhY = $chartHeight - $sKwhH;

                    $linePoints[] = sprintf('%.1f,%.1f', $x + 6, $sKwY);
                ?>
                    <!-- Solar Bar -->
                    <?php if ((float)$h['solar_kwh'] > 0): ?>
                        <rect x="<?= $x ?>" y="<?= $sKwhY ?>" width="12" height="<?= $sKwhH ?>" rx="3" fill="#10b981" fill-opacity="0.85">
                            <title><?= sprintf('%02d:00 | Solar: %.1f kWh | Güç: %.1f kW', $h['hour'], $h['solar_kwh'], $h['solar_kw']) ?></title>
                        </rect>
                    <?php endif; ?>

                    <?php if ($i % 2 === 0): ?>
                        <text x="<?= $x + 6 ?>" y="<?= $chartHeight + 18 ?>" fill="#94a3b8" font-size="10" text-anchor="middle" font-family="Inter, sans-serif"><?= sprintf('%02d:00', $h['hour']) ?></text>
                    <?php endif; ?>
                <?php endforeach; ?>

                <polyline points="<?= implode(' ', $linePoints) ?>" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </div>
    </div>

    <!-- 2. 7 GÜNLÜK GES TRENDİ & İNVERTÖR ANOMALİ BİLGİSİ -->
    <div class="dashboard-two-cols">
        <!-- Sol: 7 Günlük Solar Üretim Tablosu -->
        <div class="card chart-panel-card">
            <div class="card-header-clean">
                <div>
                    <h3 class="card-title">Haftalık GES Üretim Tablosu</h3>
                    <p class="card-subtitle">Son 7 günün solar üretim ve tasarruf performansı</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Solar Üretim</th>
                            <th>Ekonomik Değer</th>
                            <th>CO₂ Engelleme</th>
                            <th>Öz Yeterlilik</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daily as $d): 
                            $ratio = $d['total_kwh'] > 0 ? min(100, ($d['solar_kwh'] / $d['total_kwh']) * 100) : 0;
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($d['date_label']) ?></strong></td>
                                <td class="text-green"><strong><?= number_format($d['solar_kwh'], 1, ',', '.') ?> kWh</strong></td>
                                <td class="text-cyan">₺<?= number_format($d['solar_kwh'] * 3.50, 0, ',', '.') ?></td>
                                <td><?= number_format($d['solar_kwh'] * 0.432, 0, ',', '.') ?> kg</td>
                                <td>
                                    <div class="load-bar-wrap">
                                        <div class="load-bar-track" style="width: 50px;">
                                            <div class="load-bar-fill" style="width: <?= $ratio ?>%; background: #10b981;"></div>
                                        </div>
                                        <span class="load-bar-text">%<?= number_format($ratio, 0) ?></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sağ: Tesis Solar Özellikleri ve İnvertör Sağlığı -->
        <div class="card chart-panel-card">
            <div class="card-header-clean">
                <div>
                    <h3 class="card-title">Santral Özellikleri &amp; Telemetri</h3>
                    <p class="card-subtitle">1.2 MWp Fotovoltaik santral teknik parametreleri</p>
                </div>
            </div>

            <div class="solar-stats-list">
                <div class="solar-stat-row">
                    <span class="stat-bullet" style="background: #06b6d4;"></span>
                    <div class="stat-text">
                        <span class="stat-name">Kurulu DC Güç</span>
                        <span class="stat-sub">Çatı fotovoltaik modül kapasitesi</span>
                    </div>
                    <span class="stat-val text-cyan">1.200 kWp (1.2 MWp)</span>
                </div>

                <div class="solar-stat-row">
                    <span class="stat-bullet" style="background: #10b981;"></span>
                    <div class="stat-text">
                        <span class="stat-name">İnvertör Sayacı</span>
                        <span class="stat-sub">MTR-SOLAR-MAIN Modbus IP</span>
                    </div>
                    <span class="stat-val text-green">Online (192.168.1.102)</span>
                </div>

                <div class="solar-stat-row">
                    <span class="stat-bullet" style="background: #f59e0b;"></span>
                    <div class="stat-text">
                        <span class="stat-name">Tesis Bölgesi</span>
                        <span class="stat-sub">Çatı GES Sahası (ZONE-GES)</span>
                    </div>
                    <span class="stat-val">3.600 m² Panel Alanı</span>
                </div>

                <div class="solar-stat-row">
                    <span class="stat-bullet" style="background: #ec4899;"></span>
                    <div class="stat-text">
                        <span class="stat-name">Performans Oranı (PR)</span>
                        <span class="stat-sub">Standart ışımaya göre verim</span>
                    </div>
                    <span class="stat-val text-pink">%81.4 (Optimum)</span>
                </div>
            </div>

            <div class="green-energy-callout" style="margin-top: 24px;">
                <span class="callout-icon">💡</span>
                <p><strong>Verimlilik Notu:</strong> 11:00-14:00 saatleri arasındaki solar üretim tepe noktası, laminatör kürleme fırınlarının enerji talebini %100 karşılamaktadır.</p>
            </div>
        </div>
    </div>

</main>