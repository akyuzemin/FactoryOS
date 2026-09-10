<?php
$pageTitle = 'Sayaçlar &amp; Tesis';
$activePage = 'energy-meters';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="page-kicker">Altyapı &amp; Telemetri Ağı</p>
            <h1>⚙️ Enerji Sayaçları &amp; Tesis Bölgeleri</h1>
            <p class="page-description">Tesis bünyesindeki 8 adet dijital enerji analizörü, alt sayaçlar ve bölgesel izleme noktaları.</p>
        </div>
        <div class="header-badges">
            <span class="live-badge"><span class="pulse-dot"></span> 8 Sayaç Çevrimiçi</span>
        </div>
    </header>

    <!-- KPI STRIP -->
    <div class="report-metric-strip" style="margin-bottom: 24px;">
        <div class="report-metric-card">
            <span class="report-metric-title">Toplam Sayaç</span>
            <span class="report-metric-value text-purple">8 <small style="font-size: 12px; color: var(--text-muted);">Adet</small></span>
        </div>
        <div class="report-metric-card">
            <span class="report-metric-title">Tesis Bölgesi</span>
            <span class="report-metric-value text-cyan">6 <small style="font-size: 12px; color: var(--text-muted);">Bölge</small></span>
        </div>
        <div class="report-metric-card">
            <span class="report-metric-title">İzlenen Üretim Hattı</span>
            <span class="report-metric-value text-amber">4 <small style="font-size: 12px; color: var(--text-muted);">Hat</small></span>
        </div>
        <div class="report-metric-card">
            <span class="report-metric-title">İletişim Protokolü</span>
            <span class="report-metric-value text-green">Modbus TCP/IP</span>
        </div>
    </div>

    <!-- 1. ENERJİ SAYAÇLARI LİSTESİ TABLOSU -->
    <div class="card chart-panel-card" style="margin-bottom: 24px;">
        <div class="card-header-clean">
            <div>
                <h3 class="card-title">Tesis Enerji Sayaçları &amp; Canlı Telemetri Tablosu</h3>
                <p class="card-subtitle">Sayaç tipleri, bağlı hatlar, son okunan anlık güç ve kümülatif sayaç endeksleri</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sayaç Kodu &amp; Adı</th>
                        <th>Sayaç Tipi</th>
                        <th>Tesis Bölgesi</th>
                        <th>Bağlı Hat</th>
                        <th>IP / Modbus</th>
                        <th>Anlık Güç</th>
                        <th>Bugünkü Tüketim</th>
                        <th>Kümülatif Endeks</th>
                        <th>Durum</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($meters as $m): ?>
                        <tr>
                            <td>
                                <div class="table-cell-title">
                                    <strong><?= htmlspecialchars($m['name']) ?></strong>
                                    <span class="table-cell-code"><?= htmlspecialchars($m['code']) ?></span>
                                </div>
                            </td>
                            <td><span class="badge-subtle"><?= htmlspecialchars($m['meter_type']) ?></span></td>
                            <td><?= htmlspecialchars($m['zone_name']) ?></td>
                            <td><?= htmlspecialchars($m['line_name'] ?: 'Genel Tesis') ?></td>
                            <td><code><?= htmlspecialchars($m['bus_address']) ?></code></td>
                            <td>
                                <strong style="color: #22d3ee;"><?= number_format((float)$m['latest_active_power_kw'], 1, ',', '.') ?> kW</strong>
                            </td>
                            <td>
                                <strong class="text-purple"><?= number_format((float)$m['today_kwh'], 1, ',', '.') ?> kWh</strong>
                            </td>
                            <td>
                                <small style="font-family: monospace; color: #cbd5e1;">
                                    <?= $m['meter_type'] === 'SOLAR_INVERTER' ? number_format((float)$m['latest_cum_export'], 1, ',', '.') : number_format((float)$m['latest_cum_import'], 1, ',', '.') ?>
                                </small>
                            </td>
                            <td>
                                <span class="status-badge status-badge-success">Çevrimiçi</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 2. TESİS BÖLGELERİ ÖZETİ -->
    <div class="card chart-panel-card">
        <div class="card-header-clean">
            <div>
                <h3 class="card-title">Tesis Bölgeleri (Facility Zones)</h3>
                <p class="card-subtitle">Schmid Pekintaş fabrikasının tanımlı enerji izleme lokasyonları</p>
            </div>
        </div>

        <div class="dashboard-cards" style="grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px;">
            <?php foreach ($zones as $z): ?>
                <div class="card" style="padding: 18px; border: 1px solid var(--line);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <h4 style="margin: 0; font-size: 15px; color: var(--ink);"><?= htmlspecialchars($z['name']) ?></h4>
                        <span class="badge-subtle"><?= htmlspecialchars($z['code']) ?></span>
                    </div>
                    <p style="font-size: 12px; color: var(--text-secondary); margin: 0 0 12px; line-height: 1.4;"><?= htmlspecialchars($z['description']) ?></p>
                    <div style="display: flex; justify-content: space-between; font-size: 11.5px; border-top: 1px solid var(--line-subtle); padding-top: 10px;">
                        <span>Hat Sayısı: <strong><?= $z['line_count'] ?></strong></span>
                        <span>Sayaç Sayısı: <strong><?= $z['meter_count'] ?></strong></span>
                        <span>Tüketim: <strong class="text-purple"><?= number_format($z['today_kwh'], 1, ',', '.') ?> kWh</strong></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</main>