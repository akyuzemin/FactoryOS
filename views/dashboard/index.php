<?php

$pageTitle = 'Fabrika Genel Durumu';

$formatNum = static function (float|int|string|null $value, int $decimals = 0): string {
    if ($value === null || $value === '') {
        return '0';
    }
    return number_format((float)$value, $decimals, ',', '.');
};

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';

// Safe extraction of overview data
$overviewData = $overviewData ?? [];
$kpi = $overviewData['kpi'] ?? [];
$lines = $overviewData['lines'] ?? [];
$hourly = $overviewData['hourly_performance'] ?? ['labels' => [], 'actual' => [], 'planned' => []];
$oee = $overviewData['oee'] ?? [];
$maint = $overviewData['maintenance'] ?? [];
$maintAssets = $overviewData['maintenance_assets'] ?? [];
$stock = $overviewData['stock'] ?? [];
$energy = $overviewData['energy'] ?? [];
$alerts = $overviewData['alerts'] ?? [];
$events = $overviewData['events'] ?? [];
$currentPeriod = $overviewData['period'] ?? 'today';
?>

<style>
/* SAAS MODERN ERP/MES THEME (MATCHING USER REFERENCE DESIGN) */
body {
    background-color: #f8fafc !important;
    color: #0f172a !important;
    font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
}

.main-content {
    background-color: #f8fafc;
    padding: 24px 32px;
    min-height: 100vh;
}

/* TOP HEADER & BREADCRUMB */
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
    letter-spacing: -0.02em;
}

.topbar-breadcrumb {
    font-size: 13px;
    color: #64748b;
    margin-top: 4px;
    font-weight: 500;
}

.topbar-breadcrumb a {
    color: #64748b;
    text-decoration: none;
}

.period-filter-group {
    display: inline-flex;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 4px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}

.period-btn {
    padding: 6px 14px;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    border: none;
    background: transparent;
    border-radius: 7px;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s ease;
}

.period-btn.active {
    background: #2563eb;
    color: #ffffff;
    box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
}

/* 6 KPI CARDS GRID */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

@media (max-width: 1400px) {
    .kpi-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
@media (max-width: 768px) {
    .kpi-grid {
        grid-template-columns: repeat(1, 1fr);
    }
}

.kpi-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 18px 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}

.kpi-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.kpi-title {
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
}

.kpi-icon-box {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.kpi-value {
    font-size: 24px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.03em;
    line-height: 1.1;
    margin-bottom: 6px;
}

.kpi-subtitle {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 4px;
}

.kpi-subtitle.positive { color: #16a34a; }
.kpi-subtitle.negative { color: #dc2626; }
.kpi-subtitle.warning  { color: #d97706; }

/* MAIN CONTENT GRID (2 COLUMNS) */
.dashboard-main-grid {
    display: grid;
    grid-template-columns: 7fr 5fr;
    gap: 24px;
}

@media (max-width: 1200px) {
    .dashboard-main-grid {
        grid-template-columns: 1fr;
    }
}

.dash-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 22px 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.dash-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f1f5f9;
}

.dash-card-title {
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 10px;
}

.dash-card-title .icon {
    font-size: 18px;
}

/* SECTION 1: PRODUCTION LINES GRID */
.lines-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 14px;
}

@media (max-width: 640px) {
    .lines-grid { grid-template-columns: 1fr; }
}

.line-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    transition: border-color 0.15s ease;
}

.line-box:hover {
    border-color: #cbd5e1;
}

.line-box-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.line-name {
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
}

.line-code {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    background: #e2e8f0;
    padding: 2px 6px;
    border-radius: 4px;
}

.badge-status {
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.badge-running { background: #dcfce7; color: #15803d; }
.badge-fault   { background: #fee2e2; color: #b91c1c; }
.badge-maintenance { background: #fef3c7; color: #b45309; }
.badge-idle    { background: #f1f5f9; color: #475569; }

.status-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
}
.badge-running .status-dot { background: #22c55e; }
.badge-fault .status-dot   { background: #ef4444; }
.badge-maintenance .status-dot { background: #f59e0b; }
.badge-idle .status-dot    { background: #94a3b8; }

.line-oee-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12.5px;
    margin-top: 10px;
    margin-bottom: 6px;
    font-weight: 600;
}

.progress-track {
    background: #e2e8f0;
    height: 7px;
    border-radius: 10px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: 10px;
    transition: width 0.3s ease;
}

.line-wo-info {
    font-size: 12px;
    color: #475569;
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px dashed #e2e8f0;
    display: flex;
    justify-content: space-between;
}

/* SECTION 3: OEE RINGS / METRICS */
.oee-metrics-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    text-align: center;
}

.oee-metric-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 10px;
}

.oee-metric-val {
    font-size: 20px;
    font-weight: 800;
    color: #0f172a;
    margin-top: 4px;
}

.oee-metric-lbl {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
}

/* SECTION 4: TPM & MAINTENANCE */
.maint-kpi-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 14px;
}

.maint-kpi-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px;
    text-align: center;
}

.maint-kpi-num {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
}

.maint-kpi-lbl {
    font-size: 10.5px;
    font-weight: 700;
    color: #64748b;
}

/* SECTION 7: RECENT ALERTS */
.alert-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 10px;
    margin-bottom: 10px;
    border: 1px solid transparent;
}

.alert-item.DANGER  { background: #fef2f2; border-color: #fecaca; }
.alert-item.WARNING { background: #fffbeb; border-color: #fde68a; }
.alert-item.SUCCESS { background: #f0fdf4; border-color: #bbf7d0; }

.alert-badge {
    font-size: 10.5px;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 4px;
    text-transform: uppercase;
}

.alert-item.DANGER .alert-badge  { background: #ef4444; color: #ffffff; }
.alert-item.WARNING .alert-badge { background: #f59e0b; color: #ffffff; }
.alert-item.SUCCESS .alert-badge { background: #22c55e; color: #ffffff; }

.alert-content { flex: 1; }
.alert-title { font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 2px; }
.alert-meta { font-size: 11.5px; color: #64748b; display: flex; gap: 12px; }

/* SECTION 8: RECENT EVENTS TABLE */
.events-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12.5px;
}

.events-table th {
    text-align: left;
    padding: 10px 12px;
    background: #f8fafc;
    color: #64748b;
    font-weight: 700;
    font-size: 11px;
    text-transform: uppercase;
    border-bottom: 1px solid #e2e8f0;
}

.events-table td {
    padding: 12px;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
    font-weight: 500;
}

.events-table tr:last-child td { border-bottom: none; }

/* SECTION 9: QUICK ACTIONS */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    color: #1e293b;
    font-size: 12.5px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.15s ease;
}

.action-btn:hover {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(37, 99, 235, 0.15);
}

.action-btn .icon { font-size: 16px; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<main class="main-content">

    <!-- DASHBOARD HEADER -->
    <div class="dashboard-topbar">
        <div class="topbar-left">
            <div class="topbar-title">
                <h1>Fabrika Genel Durumu</h1>
                <div class="topbar-breadcrumb">
                    <a href="/stok-takip/public/">Ana Sayfa</a> &gt; <span>Fabrika Genel Durumu</span>
                </div>
            </div>
        </div>

        <div class="topbar-right">
            <div class="period-filter-group">
                <a href="?period=today" class="period-btn <?= $currentPeriod === 'today' ? 'active' : '' ?>">Bugün</a>
                <a href="?period=week" class="period-btn <?= $currentPeriod === 'week' ? 'active' : '' ?>">Bu Hafta</a>
                <a href="?period=month" class="period-btn <?= $currentPeriod === 'month' ? 'active' : '' ?>">Bu Ay</a>
            </div>
        </div>
    </div>

    <!-- TOP 6 KPI CARDS -->
    <div class="kpi-grid">
        
        <!-- KPI 1: AKTİF HATLARI -->
        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-title">Aktif Üretim Hatları</span>
                <div class="kpi-icon-box" style="background: #eff6ff; color: #2563eb;">🏭</div>
            </div>
            <div class="kpi-value"><?= (int)($kpi['lines_active'] ?? 0) ?> / <?= (int)($kpi['lines_total'] ?? 0) ?></div>
            <div class="kpi-subtitle <?= ($kpi['lines_active'] ?? 0) == ($kpi['lines_total'] ?? 0) ? 'positive' : 'warning' ?>">
                <?= ($kpi['lines_active'] ?? 0) == ($kpi['lines_total'] ?? 0) ? '✓ Tüm Hatlar Aktif' : '⚠️ Hat Durumu Değişti' ?>
            </div>
        </div>

        <!-- KPI 2: BUGÜNKÜ ÜRETİM -->
        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-title">Bugünkü Üretim</span>
                <div class="kpi-icon-box" style="background: #f0fdf4; color: #16a34a;">⚡</div>
            </div>
            <div class="kpi-value"><?= $formatNum($kpi['today_production'] ?? 0) ?> <span style="font-size: 14px; font-weight: 600; color: #64748b;">Panel</span></div>
            <div class="kpi-subtitle <?= ($kpi['daily_change_pct'] ?? 0) >= 0 ? 'positive' : 'negative' ?>">
                <?= ($kpi['daily_change_pct'] ?? 0) >= 0 ? '▲ %' : '▼ %' ?><?= abs($kpi['daily_change_pct'] ?? 0) ?> dün ile kıyasla
            </div>
        </div>

        <!-- KPI 3: OEE -->
        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-title">Genel OEE</span>
                <div class="kpi-icon-box" style="background: #faf5ff; color: #9333ea;">📊</div>
            </div>
            <div class="kpi-value">%<?= $formatNum($kpi['overall_oee'] ?? 0, 1) ?></div>
            <div class="kpi-subtitle">
                Avail: %<?= $formatNum($kpi['overall_availability'] ?? 0, 0) ?> | Perf: %<?= $formatNum($kpi['overall_performance'] ?? 0, 0) ?>
            </div>
        </div>

        <!-- KPI 4: KALİTE ORANI -->
        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-title">Kalite Oranı</span>
                <div class="kpi-icon-box" style="background: #f0fdf4; color: #15803d;">🛡️</div>
            </div>
            <div class="kpi-value">%<?= $formatNum($kpi['quality_rate'] ?? 0, 1) ?></div>
            <div class="kpi-subtitle positive">
                Hedef: %<?= $formatNum($kpi['quality_target'] ?? 98.0, 1) ?>
            </div>
        </div>

        <!-- KPI 5: AKTİF İŞ EMİRLERİ -->
        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-title">Aktif İş Emirleri</span>
                <div class="kpi-icon-box" style="background: #fff7ed; color: #ea580c;">📋</div>
            </div>
            <div class="kpi-value"><?= (int)($kpi['active_work_orders'] ?? 0) ?></div>
            <div class="kpi-subtitle">
                <?= (int)($kpi['running_work_orders'] ?? 0) ?> Üretimde • <?= (int)($kpi['waiting_work_orders'] ?? 0) ?> Bekliyor
            </div>
        </div>

        <!-- KPI 6: KRİTİK UYARILAR -->
        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-title">Kritik Uyarılar</span>
                <div class="kpi-icon-box" style="background: #fef2f2; color: #dc2626;">⚠️</div>
            </div>
            <div class="kpi-value" style="color: <?= ($kpi['critical_alerts_count'] ?? 0) > 0 ? '#dc2626' : '#16a34a' ?>;">
                <?= (int)($kpi['critical_alerts_count'] ?? 0) ?>
            </div>
            <div class="kpi-subtitle <?= ($kpi['critical_alerts_count'] ?? 0) > 0 ? 'negative' : 'positive' ?>">
                <?= (int)($kpi['critical_stock_alerts'] ?? 0) ?> Stok • <?= (int)($kpi['critical_line_alerts'] ?? 0) ?> Duruş/Bakım
            </div>
        </div>

    </div>

    <!-- MAIN DASHBOARD CONTENT GRID (2 COLUMNS) -->
    <div class="dashboard-main-grid">
        
        <!-- LEFT COLUMN: OPERATIONS & PERFORMANCE -->
        <div class="grid-col-left">

            <!-- BÖLÜM 1 — ÜRETİM HATLARI ANLIK DURUMU -->
            <div class="dash-card">
                <div class="dash-card-header">
                    <div class="dash-card-title">
                        <span class="icon">🏭</span> Üretim Hatları Anlık Durumu
                    </div>
                    <span style="font-size: 12px; font-weight: 600; color: #64748b;">Toplam <?= count($lines) ?> Hat Tanımlı</span>
                </div>

                <div class="lines-grid">
                    <?php foreach ($lines as $line): ?>
                        <div class="line-box">
                            <div class="line-box-header">
                                <div>
                                    <span class="line-name"><?= htmlspecialchars($line['name']) ?></span>
                                    <span class="line-code"><?= htmlspecialchars($line['code']) ?></span>
                                </div>
                                <span class="badge-status <?= $line['status_badge_class'] ?>">
                                    <span class="status-dot"></span> <?= htmlspecialchars($line['status_label']) ?>
                                </span>
                            </div>

                            <div class="line-oee-row">
                                <span style="color: #64748b;">Hat OEE Değeri</span>
                                <span style="color: #0f172a;">%<?= $formatNum($line['oee_pct'], 1) ?></span>
                            </div>
                            <div class="progress-track">
                                <div class="progress-fill" style="width: <?= min(100, max(5, (float)$line['oee_pct'])) ?>%; background: <?= $line['oee_pct'] >= 80 ? '#22c55e' : ($line['oee_pct'] >= 60 ? '#f59e0b' : '#ef4444') ?>;"></div>
                            </div>

                            <div class="line-wo-info">
                                <div>
                                    <strong>İş Emri:</strong> <?= $line['active_work_order'] ? htmlspecialchars($line['active_work_order']['work_order_no']) : '<em>Boşta</em>' ?>
                                </div>
                                <div>
                                    <strong>Üretim:</strong> <?= $formatNum($line['produced_quantity']) ?> Panel
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- BÖLÜM 2 — SON 24 SAAT ÜRETİM PERFORMANSI (CHART) -->
            <div class="dash-card">
                <div class="dash-card-header">
                    <div class="dash-card-title">
                        <span class="icon">📈</span> Son 24 Saat Üretim Performansı
                    </div>
                    <div style="font-size: 12px; font-weight: 600; color: #64748b; display: flex; gap: 12px;">
                        <span style="display: flex; align-items: center; gap: 4px;"><span style="width: 10px; height: 10px; background: #2563eb; border-radius: 2px;"></span> Gerçekleşen</span>
                        <span style="display: flex; align-items: center; gap: 4px;"><span style="width: 10px; height: 10px; background: #94a3b8; border-radius: 2px;"></span> Planlanan Target</span>
                    </div>
                </div>

                <div style="height: 260px; position: relative;">
                    <canvas id="productionPerformanceChart"></canvas>
                </div>
            </div>

            <!-- BÖLÜM 8 — SON ÜRETİM AKTİVİTELERİ -->
            <div class="dash-card">
                <div class="dash-card-header">
                    <div class="dash-card-title">
                        <span class="icon">⚡</span> Son Üretim Aktiviteleri (MES Events)
                    </div>
                    <a href="/stok-takip/public/mes" style="font-size: 12.5px; font-weight: 700; color: #2563eb; text-decoration: none;">Tümünü Gör &rarr;</a>
                </div>

                <table class="events-table">
                    <thead>
                        <tr>
                            <th>Saat</th>
                            <th>Hat</th>
                            <th>İş Emri</th>
                            <th>Ürün</th>
                            <th>Miktar</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($events)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #64748b; padding: 16px;">Henüz kaydedilmiş üretim event verisi bulunmuyor.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($events as $ev): ?>
                                <tr>
                                    <td style="font-weight: 700; color: #475569;"><?= date('H:i:s', strtotime($ev['event_time'])) ?></td>
                                    <td><?= htmlspecialchars($ev['line_name'] ?? 'Hat-1') ?></td>
                                    <td><span style="font-family: monospace; font-weight: 600; color: #2563eb;"><?= htmlspecialchars($ev['work_order_no'] ?? '-') ?></span></td>
                                    <td><?= htmlspecialchars($ev['product_name'] ?? 'Solar Panel') ?></td>
                                    <td style="font-weight: 800; color: #16a34a;">+<?= $formatNum($ev['produced_quantity'] ?? 1) ?> Adet</td>
                                    <td>
                                        <span class="badge-status <?= ($ev['status'] ?? '') === 'PROCESSED' ? 'badge-running' : 'badge-fault' ?>">
                                            <?= ($ev['status'] ?? '') === 'PROCESSED' ? '✓ Başarılı' : '⚠️ Hata/İptal' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- RIGHT COLUMN: OEE, TPM, STOCK, ENERGY, ALERTS & ACTIONS -->
        <div class="grid-col-right">

            <!-- BÖLÜM 3 — OEE BİLEŞENLERİ -->
            <div class="dash-card">
                <div class="dash-card-header">
                    <div class="dash-card-title">
                        <span class="icon">📊</span> OEE Bileşenleri (Genel Tesis)
                    </div>
                </div>

                <div class="oee-metrics-grid">
                    <div class="oee-metric-card">
                        <div class="oee-metric-lbl">Avail</div>
                        <div class="oee-metric-val" style="color: #2563eb;">%<?= $formatNum($oee['overall_availability'] ?? 0, 1) ?></div>
                    </div>
                    <div class="oee-metric-card">
                        <div class="oee-metric-lbl">Perf</div>
                        <div class="oee-metric-val" style="color: #0284c7;">%<?= $formatNum($oee['overall_performance'] ?? 0, 1) ?></div>
                    </div>
                    <div class="oee-metric-card">
                        <div class="oee-metric-lbl">Qual</div>
                        <div class="oee-metric-val" style="color: #16a34a;">%<?= $formatNum($oee['overall_quality'] ?? 0, 1) ?></div>
                    </div>
                    <div class="oee-metric-card" style="background: #faf5ff; border-color: #e9d5ff;">
                        <div class="oee-metric-lbl" style="color: #9333ea;">OEE</div>
                        <div class="oee-metric-val" style="color: #7e22ce;">%<?= $formatNum($oee['overall_oee'] ?? 0, 1) ?></div>
                    </div>
                </div>
            </div>

            <!-- BÖLÜM 4 — BAKIM & TPM DURUMU -->
            <div class="dash-card">
                <div class="dash-card-header">
                    <div class="dash-card-title">
                        <span class="icon">🔧</span> Bakım & TPM Durumu
                    </div>
                </div>

                <div class="maint-kpi-row">
                    <div class="maint-kpi-box">
                        <div class="maint-kpi-num"><?= (int)($maint['open_work_orders_count'] ?? 0) ?></div>
                        <div class="maint-kpi-lbl">Açık İş Emri</div>
                    </div>
                    <div class="maint-kpi-box">
                        <div class="maint-kpi-num"><?= (int)($maint['assets_total'] ?? 8) ?></div>
                        <div class="maint-kpi-lbl">Kritik Ekipman</div>
                    </div>
                    <div class="maint-kpi-box">
                        <div class="maint-kpi-num"><?= $formatNum($maint['mtbf_hours'] ?? 78.1, 1) ?> <span style="font-size: 11px;">sa</span></div>
                        <div class="maint-kpi-lbl">MTBF (Ort. Arıza)</div>
                    </div>
                    <div class="maint-kpi-box">
                        <div class="maint-kpi-num"><?= $formatNum($maint['mttr_minutes'] ?? 0.3, 1) ?> <span style="font-size: 11px;">dk</span></div>
                        <div class="maint-kpi-lbl">MTTR (Ort. Tamir)</div>
                    </div>
                </div>
            </div>

            <!-- BÖLÜM 5 — STOK DURUMU -->
            <div class="dash-card">
                <div class="dash-card-header">
                    <div class="dash-card-title">
                        <span class="icon">📦</span> Stok Durumu Özeti
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; font-size: 13px;">
                    <div>
                        <span style="color: #64748b;">Toplam Malzeme:</span>
                        <strong style="color: #0f172a; font-size: 15px; margin-left: 4px;"><?= (int)($stock['total_materials'] ?? 0) ?> Çeşit</strong>
                    </div>
                    <div>
                        <span style="color: #64748b;">Toplam Stok Değeri:</span>
                        <strong style="color: #16a34a; font-size: 15px; margin-left: 4px;">₺<?= $formatNum(($stock['total_stock_value'] ?? 0) / 1000000, 2) ?>M TL</strong>
                    </div>
                </div>

                <?php if (!empty($stock['critical_items'])): ?>
                    <div style="font-size: 11.5px; font-weight: 700; color: #dc2626; margin-bottom: 8px;">⚠️ Kritik Stok Altındaki Malzemeler:</div>
                    <?php foreach ($stock['critical_items'] as $ci): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; background: #fffbeb; border: 1px solid #fde68a; padding: 6px 10px; border-radius: 6px; font-size: 12px; margin-bottom: 4px;">
                            <span style="font-weight: 600; color: #92400e;"><?= htmlspecialchars($ci['code']) ?> - <?= htmlspecialchars($ci['name']) ?></span>
                            <span style="font-weight: 800; color: #b45309;"><?= $formatNum($ci['current_stock']) ?> / Min: <?= $formatNum($ci['min_stock']) ?> <?= htmlspecialchars($ci['unit_symbol']) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; padding: 8px 12px; border-radius: 8px; font-size: 12px; font-weight: 600;">
                        ✓ Tüm malzeme stokları güvenli seviyenin üzerindedir.
                    </div>
                <?php endif; ?>
            </div>

            <!-- BÖLÜM 6 — ENERJİ TÜKETİMİ -->
            <div class="dash-card">
                <div class="dash-card-header">
                    <div class="dash-card-title">
                        <span class="icon">⚡</span> Enerji Tüketimi
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; text-align: center;">
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px;">
                        <div style="font-size: 11px; font-weight: 700; color: #64748b;">Tüketim</div>
                        <div style="font-size: 16px; font-weight: 800; color: #0f172a; margin-top: 2px;"><?= $formatNum($energy['today_kwh'] ?? 0) ?> kWh</div>
                    </div>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px;">
                        <div style="font-size: 11px; font-weight: 700; color: #64748b;">Panel Başı</div>
                        <div style="font-size: 16px; font-weight: 800; color: #2563eb; margin-top: 2px;"><?= $formatNum($energy['kwh_per_panel'] ?? 0, 2) ?> kWh</div>
                    </div>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px;">
                        <div style="font-size: 11px; font-weight: 700; color: #64748b;">Tahmini Maliyet</div>
                        <div style="font-size: 16px; font-weight: 800; color: #16a34a; margin-top: 2px;">₺<?= $formatNum($energy['today_cost_tl'] ?? 0, 0) ?> TL</div>
                    </div>
                </div>
            </div>

            <!-- BÖLÜM 7 — SON UYARILAR -->
            <div class="dash-card">
                <div class="dash-card-header">
                    <div class="dash-card-title">
                        <span class="icon">🔔</span> Son Uyarılar & Sistem Olayları
                    </div>
                </div>

                <div>
                    <?php foreach ($alerts as $alt): ?>
                        <div class="alert-item <?= $alt['severity'] ?>">
                            <span class="alert-badge"><?= htmlspecialchars($alt['badge']) ?></span>
                            <div class="alert-content">
                                <div class="alert-title"><?= htmlspecialchars($alt['title']) ?></div>
                                <div class="alert-meta">
                                    <span>📍 <?= htmlspecialchars($alt['source']) ?></span>
                                    <span>🕒 <?= htmlspecialchars($alt['timestamp']) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- BÖLÜM 9 — HIZLI İŞLEMLER (RBAC $can CONTROLLED) -->
            <div class="dash-card">
                <div class="dash-card-header">
                    <div class="dash-card-title">
                        <span class="icon">🚀</span> Hızlı İşlemler
                    </div>
                </div>

                <div class="quick-actions-grid">
                    <?php if ($can('mes.manage')): ?>
                        <a href="/stok-takip/public/mes/work-orders/create" class="action-btn">
                            <span class="icon">➕</span> Yeni İş Emri
                        </a>
                    <?php endif; ?>

                    <?php if ($can('mes.view')): ?>
                        <a href="/stok-takip/public/mes" class="action-btn">
                            <span class="icon">📋</span> MES İş Emirleri
                        </a>
                    <?php endif; ?>

                    <?php if ($can('stock.manage') || $can('stock.view')): ?>
                        <a href="/stok-takip/public/stock" class="action-btn">
                            <span class="icon">📥</span> Stok İşlemleri
                        </a>
                    <?php endif; ?>

                    <?php if ($can('maintenance.view') || $can('maintenance.request')): ?>
                        <a href="/stok-takip/public/maintenance" class="action-btn">
                            <span class="icon">🔧</span> Bakım Yönetimi
                        </a>
                    <?php endif; ?>

                    <?php if ($can('energy.view')): ?>
                        <a href="/stok-takip/public/energy" class="action-btn">
                            <span class="icon">⚡</span> Enerji Raporu
                        </a>
                    <?php endif; ?>

                    <?php if ($can('oee.view')): ?>
                        <a href="/stok-takip/public/oee" class="action-btn">
                            <span class="icon">📈</span> OEE Analizi
                        </a>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>

</main>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('productionPerformanceChart');
    if (!ctx) return;

    const labels = <?= json_encode($hourly['labels'] ?? []) ?>;
    const actualData = <?= json_encode($hourly['actual'] ?? []) ?>;
    const plannedData = <?= json_encode($hourly['planned'] ?? []) ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Gerçekleşen Üretim (Panel)',
                    data: actualData,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointHoverRadius: 6
                },
                {
                    label: 'Planlanan Hedef (Panel)',
                    data: plannedData,
                    borderColor: '#94a3b8',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    fill: false,
                    tension: 0.1,
                    pointRadius: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    padding: 10,
                    cornerRadius: 8
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 }, color: '#64748b' }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: { font: { size: 11 }, color: '#64748b' }
                }
            }
        }
    });
});
</script>
