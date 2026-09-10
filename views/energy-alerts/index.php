<?php
$pageTitle = 'Alarmlar &amp; Olay Kayıtları';
$activePage = 'energy-alerts';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <!-- BAŞLIK VE BİLGİLENDİRME BİLGİSİ -->
    <header class="page-header">
        <div>
            <p class="page-kicker">Kural Tabanlı Anomali Tespit Motoru &amp; Eşik Takibi</p>
            <h1>🚨 Enerji Alarmları &amp; Olay Kayıtları</h1>
            <p class="page-description">Gerilim dalgalanmaları, puant yük sıçramaları, düşük güç faktörü ve gece baz yük anomalilerini otomatik tespit eden Akıllı Alarm Sistemi.</p>
        </div>
        <div class="header-badges">
            <span class="status-badge status-badge-info" style="font-size: 11.5px; padding: 6px 12px;">
                💡 Kural Tabanlı Analiz (DEMO Veri Seti)
            </span>
        </div>
    </header>

    <?php if (!empty($flashMessage)): ?>
        <div class="alert alert-success" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #34d399; padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
            <span>✓ <?= htmlspecialchars($flashMessage['text']) ?></span>
            <button type="button" onclick="this.parentElement.remove();" style="background:none; border:none; color:#34d399; cursor:pointer; font-weight:bold;">&times;</button>
        </div>
    <?php endif; ?>

    <!-- 1. ALARM ÖZETİ (6 KPI KARTI) -->
    <div class="energy-kpi-grid" style="margin-bottom: 24px;">
        <!-- 1. Açık Kritik Alarm -->
        <div class="card energy-kpi-card" style="border-top: 3px solid #ef4444; background: <?= $stats['open_critical'] > 0 ? 'linear-gradient(180deg, rgba(239, 68, 68, 0.1) 0%, var(--card-bg) 100%)' : 'var(--card-bg)' ?>;">
            <div class="kpi-icon-wrap" style="background: rgba(239, 68, 68, 0.2); color: #ef4444;">🔴</div>
            <div class="kpi-content">
                <span class="kpi-label">Açık Kritik Alarm</span>
                <div class="kpi-value-row">
                    <span class="kpi-val text-danger"><?= $stats['open_critical'] ?></span>
                    <span class="kpi-unit">Adet</span>
                </div>
                <div class="kpi-sub">
                    <span class="kpi-tag" style="background: rgba(239, 68, 68, 0.2); color: #fca5a5;">Acil Müdahale</span>
                </div>
            </div>
        </div>

        <!-- 2. Açık Uyarı -->
        <div class="card energy-kpi-card" style="border-top: 3px solid var(--amber);">
            <div class="kpi-icon-wrap" style="background: rgba(245, 158, 11, 0.2); color: var(--amber);">🟡</div>
            <div class="kpi-content">
                <span class="kpi-label">Açık Uyarılar</span>
                <div class="kpi-value-row">
                    <span class="kpi-val text-amber"><?= $stats['open_warning'] ?></span>
                    <span class="kpi-unit">Adet</span>
                </div>
                <div class="kpi-sub">
                    <span class="kpi-tag kpi-tag-amber">İnceleme Bekliyor</span>
                </div>
            </div>
        </div>

        <!-- 3. Bugünkü Alarmlar -->
        <div class="card energy-kpi-card" style="border-top: 3px solid var(--purple);">
            <div class="kpi-icon-wrap" style="background: rgba(139, 92, 246, 0.2); color: var(--purple);">📅</div>
            <div class="kpi-content">
                <span class="kpi-label">Bugünkü Alarmlar</span>
                <div class="kpi-value-row">
                    <span class="kpi-val text-purple"><?= $stats['today_alerts'] ?></span>
                    <span class="kpi-unit">Olay</span>
                </div>
                <div class="kpi-sub">
                    <span class="kpi-hint"><?= date('d.m.Y', strtotime($stats['latest_date'])) ?></span>
                </div>
            </div>
        </div>

        <!-- 4. Son 7 Gün Alarm -->
        <div class="card energy-kpi-card" style="border-top: 3px solid var(--cyan);">
            <div class="kpi-icon-wrap" style="background: rgba(6, 182, 212, 0.2); color: var(--cyan);">📊</div>
            <div class="kpi-content">
                <span class="kpi-label">Son 7 Gün Toplam</span>
                <div class="kpi-value-row">
                    <span class="kpi-val text-cyan"><?= $stats['seven_days_alerts'] ?></span>
                    <span class="kpi-unit">Kayıt</span>
                </div>
                <div class="kpi-sub">
                    <span class="kpi-hint">Haftalık Trend</span>
                </div>
            </div>
        </div>

        <!-- 5. Çözülen Alarmlar -->
        <div class="card energy-kpi-card" style="border-top: 3px solid var(--green);">
            <div class="kpi-icon-wrap" style="background: rgba(16, 185, 129, 0.2); color: var(--green);">🟢</div>
            <div class="kpi-content">
                <span class="kpi-label">Çözülen Alarmlar</span>
                <div class="kpi-value-row">
                    <span class="kpi-val text-green"><?= $stats['resolved_alerts'] ?></span>
                    <span class="kpi-unit">Çözüldü</span>
                </div>
                <div class="kpi-sub">
                    <span class="kpi-tag" style="background: rgba(16, 185, 129, 0.2); color: #86efac;">+<?= $stats['acknowledged_alerts'] ?> Onaylı</span>
                </div>
            </div>
        </div>

        <!-- 6. En Çok Alarm Üreten Hat -->
        <div class="card energy-kpi-card" style="border-top: 3px solid #6366f1;">
            <div class="kpi-icon-wrap" style="background: rgba(99, 102, 241, 0.2); color: #818cf8;">🏭</div>
            <div class="kpi-content">
                <span class="kpi-label">En Çok Alarm Üreten</span>
                <div class="kpi-value-row" style="font-size: 14px; font-weight: 700; color: #a5b4fc; margin-top: 2px;">
                    <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px;" title="<?= htmlspecialchars($stats['top_meter_name']) ?>">
                        <?= htmlspecialchars($stats['top_meter_name']) ?>
                    </span>
                </div>
                <div class="kpi-sub">
                    <span class="kpi-hint"><?= $stats['top_meter_count'] ?> Olay Kaydı</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. ALARM FİLTRELEME FORMU -->
    <div class="card chart-panel-card" style="margin-bottom: 24px; padding: 16px 20px;">
        <form method="GET" action="/stok-takip/public/energy/alerts" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)) 100px; gap: 12px; align-items: flex-end;">
            <!-- Öncelik -->
            <div>
                <label style="font-size: 11px; color: var(--text-secondary); display: block; margin-bottom: 4px;">Öncelik:</label>
                <select name="severity" class="energy-date-input" style="width: 100%; height: 36px;">
                    <option value="">Tümü</option>
                    <option value="CRITICAL" <?= ($_GET['severity'] ?? '') === 'CRITICAL' ? 'selected' : '' ?>>🔴 CRITICAL</option>
                    <option value="WARNING" <?= ($_GET['severity'] ?? '') === 'WARNING' ? 'selected' : '' ?>>🟡 WARNING</option>
                    <option value="INFO" <?= ($_GET['severity'] ?? '') === 'INFO' ? 'selected' : '' ?>>🔵 INFO</option>
                </select>
            </div>

            <!-- Alarm Tipi -->
            <div>
                <label style="font-size: 11px; color: var(--text-secondary); display: block; margin-bottom: 4px;">Alarm Tipi:</label>
                <select name="alert_type" class="energy-date-input" style="width: 100%; height: 36px;">
                    <option value="">Tüm Tipler</option>
                    <option value="PEAK_POWER_SURGE" <?= ($_GET['alert_type'] ?? '') === 'PEAK_POWER_SURGE' ? 'selected' : '' ?>>Puant Güç Sıçraması</option>
                    <option value="IDLE_CONSUMPTION" <?= ($_GET['alert_type'] ?? '') === 'IDLE_CONSUMPTION' ? 'selected' : '' ?>>Gece Baz Yükü</option>
                    <option value="REACTIVE_INDUCTIVE_LIMIT" <?= ($_GET['alert_type'] ?? '') === 'REACTIVE_INDUCTIVE_LIMIT' ? 'selected' : '' ?>>Güç Faktörü / Reaktif</option>
                    <option value="VOLTAGE_ANOMALY" <?= ($_GET['alert_type'] ?? '') === 'VOLTAGE_ANOMALY' ? 'selected' : '' ?>>Gerilim Tolerans Dışı</option>
                    <option value="SOLAR_UNDERPERFORMANCE" <?= ($_GET['alert_type'] ?? '') === 'SOLAR_UNDERPERFORMANCE' ? 'selected' : '' ?>>GES Üretim Kaybı</option>
                    <option value="THRESHOLD_EXCEEDED" <?= ($_GET['alert_type'] ?? '') === 'THRESHOLD_EXCEEDED' ? 'selected' : '' ?>>Kapasite Eşik Aşımı</option>
                </select>
            </div>

            <!-- Sayaç -->
            <div>
                <label style="font-size: 11px; color: var(--text-secondary); display: block; margin-bottom: 4px;">Sayaç / Hat:</label>
                <select name="meter_id" class="energy-date-input" style="width: 100%; height: 36px;">
                    <option value="">Tüm Sayaçlar</option>
                    <?php foreach ($meters as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= ((string)($_GET['meter_id'] ?? '')) === (string)$m['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['code'] . ' - ' . $m['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Durum -->
            <div>
                <label style="font-size: 11px; color: var(--text-secondary); display: block; margin-bottom: 4px;">Durum:</label>
                <select name="status" class="energy-date-input" style="width: 100%; height: 36px;">
                    <option value="">Tüm Durumlar</option>
                    <option value="0" <?= ((string)($_GET['status'] ?? '')) === '0' ? 'selected' : '' ?>>Açık (Pending)</option>
                    <option value="1" <?= ((string)($_GET['status'] ?? '')) === '1' ? 'selected' : '' ?>>Onaylandı (Ack)</option>
                    <option value="2" <?= ((string)($_GET['status'] ?? '')) === '2' ? 'selected' : '' ?>>Çözüldü (Resolved)</option>
                </select>
            </div>

            <!-- Tarih -->
            <div>
                <label style="font-size: 11px; color: var(--text-secondary); display: block; margin-bottom: 4px;">Tarih:</label>
                <input type="date" name="date" value="<?= htmlspecialchars($_GET['date'] ?? '') ?>" class="energy-date-input" style="width: 100%; height: 36px;">
            </div>

            <!-- Butonlar -->
            <div style="display: flex; gap: 6px;">
                <button type="submit" class="button button-primary button-sm" style="height: 36px; padding: 0 16px;">Filtrele</button>
                <a href="/stok-takip/public/energy/alerts" class="button button-secondary button-sm" style="height: 36px; padding: 0 10px; display: inline-flex; align-items: center;" title="Filtreleri Temizle">↺</a>
            </div>
        </form>
    </div>

    <!-- 3. AKTİF KRİTİK ALARMLAR (Varsa Kırmızı Çerçeveli Acil Bölüm) -->
    <?php if (!empty($criticalActive)): ?>
        <div class="card chart-panel-card" style="margin-bottom: 24px; border: 2px solid #ef4444; background: linear-gradient(180deg, rgba(239, 68, 68, 0.08) 0%, var(--card-bg) 100%);">
            <div class="card-header-clean">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 20px;">🚨</span>
                    <div>
                        <h3 class="card-title text-danger" style="margin: 0;">Aktif Kritik Alarmlar (Müdahale Gerekli)</h3>
                        <p class="card-subtitle" style="margin: 0;"><?= count($criticalActive) ?> adet çözülmemiş veya onay bekleyen kritik eşik aşımı tespit edildi.</p>
                    </div>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($criticalActive as $crit): ?>
                    <div class="energy-alert-card" style="border-left: 4px solid #ef4444; background: rgba(239, 68, 68, 0.05);">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                    <span class="status-badge status-badge-danger">CRITICAL</span>
                                    <span class="status-badge status-badge-warning" style="font-size: 10.5px;">Açık (Beklemede)</span>
                                    <span style="font-size: 12px; color: var(--text-secondary);"><?= date('d.m.Y H:i', strtotime($crit['created_at'])) ?></span>
                                </div>
                                <strong style="font-size: 14px; color: var(--ink);">
                                    <?= htmlspecialchars($crit['meter_name']) ?> (<?= htmlspecialchars($crit['meter_code']) ?>)
                                </strong>
                                <?php if (!empty($crit['line_name'])): ?>
                                    <span style="font-size: 12px; color: var(--cyan); margin-left: 6px;">[<?= htmlspecialchars($crit['line_name']) ?>]</span>
                                <?php endif; ?>
                            </div>

                            <!-- Aksiyon Butonları -->
                            <div style="display: flex; gap: 6px;">
                                <a href="/stok-takip/public/energy/alerts?action=ack&id=<?= $crit['id'] ?>" class="button button-sm button-secondary" style="font-size: 11px;">
                                    ✓ Onayla
                                </a>
                                <a href="/stok-takip/public/energy/alerts?action=resolve&id=<?= $crit['id'] ?>" class="button button-sm button-primary" style="font-size: 11px; background: var(--green); border-color: var(--green);">
                                    ✓ Çözüldü
                                </a>
                            </div>
                        </div>

                        <p style="margin: 6px 0; font-size: 13px; color: #e2e8f0; line-height: 1.4;">
                            <?= htmlspecialchars($crit['message']) ?>
                        </p>

                        <!-- Önerilen Aksiyon Paneli -->
                        <div style="background: rgba(0, 0, 0, 0.25); border-left: 3px solid var(--cyan); padding: 8px 12px; border-radius: 4px; font-size: 12px;">
                            <div style="display: flex; gap: 16px; margin-bottom: 4px;">
                                <span>Ölçülen: <strong class="text-danger"><?= number_format($crit['measured_value'], 2) ?></strong></span>
                                <span>Limit: <strong><?= number_format($crit['threshold_value'], 2) ?></strong></span>
                                <span class="text-amber">Sapma: <strong><?= htmlspecialchars($crit['advice']['deviation']) ?></strong></span>
                            </div>
                            <div style="color: #cbd5e1;">
                                <strong style="color: var(--cyan);">🛠️ Önerilen Aksiyon:</strong> <?= htmlspecialchars($crit['advice']['action']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- 4. TÜM ALARMLAR LİSTESİ -->
    <div class="card chart-panel-card">
        <div class="card-header-clean">
            <div>
                <h3 class="card-title">📋 Olay &amp; Alarm Günlüğü</h3>
                <p class="card-subtitle">Filtrelere uyan toplam <?= count($allAlerts) ?> kayıt listeleniyor.</p>
            </div>
        </div>

        <?php if (empty($allAlerts)): ?>
            <div style="text-align: center; padding: 40px 20px; color: var(--text-secondary);">
                <span style="font-size: 32px; display: block; margin-bottom: 8px;">✓</span>
                Seçili kriterlere uygun herhangi bir alarm kaydı bulunamadı.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table" style="font-size: 12.5px;">
                    <thead>
                        <tr>
                            <th style="width: 130px;">Tarih / Saat</th>
                            <th style="width: 90px;">Öncelik</th>
                            <th>Alarm Tipi</th>
                            <th>Sayaç &amp; Hat</th>
                            <th style="width: 100px;">Ölçülen</th>
                            <th style="width: 90px;">Limit</th>
                            <th style="width: 100px;">Durum</th>
                            <th style="width: 140px; text-align: right;">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allAlerts as $a): 
                            $sevBadge = match($a['severity']) {
                                'CRITICAL' => 'status-badge-danger',
                                'WARNING' => 'status-badge-warning',
                                default => 'status-badge-info'
                            };

                            $statusBadge = match((int)$a['is_acknowledged']) {
                                1 => '<span class="status-badge" style="background: rgba(99,102,241,0.2); color: #a5b4fc;">Onaylandı</span>',
                                2 => '<span class="status-badge status-badge-success">Çözüldü</span>',
                                default => '<span class="status-badge status-badge-warning">Açık</span>'
                            };
                        ?>
                            <tr>
                                <td>
                                    <strong><?= date('d.m.Y', strtotime($a['created_at'])) ?></strong><br>
                                    <small style="color: var(--text-muted);"><?= date('H:i:s', strtotime($a['created_at'])) ?></small>
                                </td>
                                <td>
                                    <span class="status-badge <?= $sevBadge ?>"><?= htmlspecialchars($a['severity']) ?></span>
                                </td>
                                <td>
                                    <strong style="color: var(--ink);"><?= htmlspecialchars($a['alert_type']) ?></strong>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($a['meter_name']) ?></strong><br>
                                    <small style="color: var(--text-secondary);"><?= htmlspecialchars($a['meter_code']) ?> <?= !empty($a['line_name']) ? '· ' . htmlspecialchars($a['line_name']) : '' ?></small>
                                </td>
                                <td>
                                    <strong style="color: <?= $a['severity'] === 'CRITICAL' ? '#f87171' : 'var(--ink)' ?>;">
                                        <?= number_format($a['measured_value'], 2, ',', '.') ?>
                                    </strong>
                                </td>
                                <td>
                                    <span style="color: var(--text-secondary);"><?= number_format($a['threshold_value'], 2, ',', '.') ?></span>
                                </td>
                                <td>
                                    <?= $statusBadge ?>
                                    <?php if (!empty($a['ack_username'])): ?>
                                        <br><small style="font-size: 10px; color: var(--text-muted);">@<?= htmlspecialchars($a['ack_username']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <button type="button" class="button button-sm button-secondary" style="font-size: 11px; padding: 3px 7px;" onclick="toggleAdvice(<?= $a['id'] ?>)">
                                        Detay
                                    </button>
                                    <?php if ($a['is_acknowledged'] == 0): ?>
                                        <a href="/stok-takip/public/energy/alerts?action=ack&id=<?= $a['id'] ?>" class="button button-sm button-secondary" style="font-size: 11px; padding: 3px 7px;" title="Onayla">
                                            ✓ Onayla
                                        </a>
                                        <a href="/stok-takip/public/energy/alerts?action=resolve&id=<?= $a['id'] ?>" class="button button-sm button-primary" style="font-size: 11px; padding: 3px 7px; background: var(--green); border-color: var(--green);" title="Çözüldü Olarak İşaretle">
                                            ✓ Çöz
                                        </a>
                                    <?php elseif ($a['is_acknowledged'] == 1): ?>
                                        <a href="/stok-takip/public/energy/alerts?action=resolve&id=<?= $a['id'] ?>" class="button button-sm button-primary" style="font-size: 11px; padding: 3px 7px; background: var(--green); border-color: var(--green);" title="Çözüldü Olarak İşaretle">
                                            ✓ Çöz
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <!-- Genişletilebilir Detay Satırı -->
                            <tr id="advice-row-<?= $a['id'] ?>" style="display: none; background: rgba(0, 0, 0, 0.2);">
                                <td colspan="8" style="padding: 12px 18px;">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; font-size: 12px;">
                                        <div>
                                            <span style="color: var(--text-secondary); display: block; margin-bottom: 2px;">Olay Mesajı:</span>
                                            <p style="margin: 0 0 8px; color: #e2e8f0; font-weight: 500;"><?= htmlspecialchars($a['message']) ?></p>
                                            
                                            <div style="display: flex; gap: 16px; color: var(--text-secondary);">
                                                <span>Bölge: <strong><?= htmlspecialchars($a['zone_name'] ?? '-') ?></strong></span>
                                                <span>Sapma: <strong class="text-amber"><?= htmlspecialchars($a['advice']['deviation']) ?></strong></span>
                                                <span>Beklenen: <strong><?= htmlspecialchars($a['advice']['expected']) ?></strong></span>
                                            </div>
                                        </div>
                                        <div style="background: rgba(139, 92, 246, 0.08); border-left: 3px solid var(--purple); padding: 8px 12px; border-radius: 4px;">
                                            <strong style="color: #c4b5fd; display: block; margin-bottom: 3px;">🛠️ Önerilen Mühendislik Aksiyonu:</strong>
                                            <span style="color: #cbd5e1;"><?= htmlspecialchars($a['advice']['action']) ?></span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</main>

<script>
function toggleAdvice(id) {
    var row = document.getElementById('advice-row-' + id);
    if (row) {
        row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
    }
}
</script>