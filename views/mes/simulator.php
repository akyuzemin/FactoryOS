<?php
$pageTitle = 'MES Simülatörü';
$activePage = 'mes-simulator';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';

$selectedWoId = (int)($_GET['wo_id'] ?? ($workOrders[0]['id'] ?? 0));
$wo = $telemetry['work_order'] ?? [];
$sim = $telemetry['simulation'] ?? [];
$eventsHuman = $telemetry['recent_events_human'] ?? [];
$latestAction = $telemetry['latest_action_summary'] ?? [
    'icon'     => '⚡',
    'title'    => 'MES Simülatörü Hazır',
    'subtitle' => 'İş emri seçip simülasyonu başlatabilirsiniz.',
    'time'     => date('H:i:s'),
    'bg'       => '#f8fafc',
    'color'    => '#475569',
    'border'   => '#e2e8f0'
];

$planned = (float)($wo['planned_quantity'] ?? 0);
$produced = (float)($wo['produced_quantity'] ?? 0);
$remaining = max(0, $planned - $produced);
$pct = (float)($wo['progress_pct'] ?? 0);
$intervalSec = (int)($sim['interval_seconds'] ?? 180);
$speedHuman = $sim['interval_human'] ?? ($intervalSec >= 60 ? round($intervalSec / 60) . ' dk/panel' : $intervalSec . ' sn/panel');
$isSimActive = !empty($sim['is_active']);
$isCompleted = ($produced >= $planned && $planned > 0) || ($wo['status'] ?? '') === 'COMPLETED';
$remainingSec = (int)($sim['remaining_seconds'] ?? $intervalSec);
?>

<main class="main-content">

    <!-- 1. ÜST BAŞLIK & MES BAĞLANTI DURUMU -->
    <header class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
        <div>
            <p class="page-kicker">Üretim Yürütme &amp; Canlı Telemetri Simülatörü</p>
            <h1>⚡ Fabrika MES Canlı Üretim Simülatörü</h1>
            <p class="page-description">Gerçek fabrika MES/PLC sistemini taklit eden, BOM tüketimi ve stok entegrasyonuyla canlı üretim yapan test motoru.</p>
        </div>
        <div class="header-badges" style="display: flex; gap: 10px; align-items: center;">
            <div id="mes-conn-status" style="display: flex; align-items: center; gap: 8px; padding: 7px 16px; background: <?= $isSimActive ? '#f0fdf4' : '#f8fafc' ?>; border: 1px solid <?= $isSimActive ? '#bbf7d0' : '#e2e8f0' ?>; border-radius: 20px; font-size: 13px; font-weight: 700; color: <?= $isSimActive ? '#166534' : '#475569' ?>;">
                <span id="conn-dot" style="display: inline-block; width: 9px; height: 9px; border-radius: 50%; background: <?= $isSimActive ? '#16a34a' : '#94a3b8' ?>; box-shadow: 0 0 8px <?= $isSimActive ? '#16a34a' : 'transparent' ?>;"></span>
                FABRİKA MES: <span id="conn-text"><?= $isCompleted ? '🏆 HEDEF TAMAMLANDI' : ($isSimActive ? '🟢 ÜRETİM AKTİF' : '⏸️ HAZIR / BEKLEMEDE') ?></span>
            </div>
            <a href="/stok-takip/public/mes" class="button button-secondary">
                &larr; İş Emirleri
            </a>
        </div>
    </header>

    <!-- 2. ANA SİMÜLATÖR DÜZENİ -->
    <div style="display: grid; grid-template-columns: 1.15fr 1fr; gap: 24px; margin-bottom: 24px;">

        <!-- SOL: HEDEF İŞ EMRİ, GERİ SAYIM, METRİKLER VE KONTROLLER -->
        <div style="display: flex; flex-direction: column; gap: 18px;">

            <!-- İş Emri Seçim & 3 Temel Metrik Kartı -->
            <div class="card" style="padding: 22px; background: #ffffff; border: 1px solid #edf2f7; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0;">
                        📋 Hedef İş Emri &amp; Üretim Durumu
                    </h3>
                    <span id="badge-sim-status" class="status-badge <?= $isCompleted ? 'status-badge-success' : ($isSimActive ? 'status-badge-info' : 'status-badge-secondary') ?>" style="font-size: 11.5px; padding: 4px 10px;">
                        <?= $isCompleted ? '🏆 TAMAMLANDI' : ($isSimActive ? '⚙️ ÇALIŞIYOR' : '⏸️ DURAKLATILDI') ?>
                    </span>
                </div>

                <!-- İş Emri Seçici -->
                <div style="margin-bottom: 16px;">
                    <select id="sim-wo-select" class="form-input" style="width: 100%; padding: 10px 12px; font-size: 13.5px; font-weight: 600; border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fafc;" onchange="switchWorkOrder(this.value)">
                        <?php foreach ($workOrders as $item): ?>
                            <option value="<?= $item['id'] ?>" <?= (int)$item['id'] === $selectedWoId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($item['work_order_no']) ?> &bull; <?= htmlspecialchars($item['product_name']) ?> (<?= number_format((float)$item['produced_quantity'], 0) ?>/<?= number_format((float)$item['planned_quantity'], 0) ?> Panel)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 3 Temel Metrik: Üretilen | Hedef | Kalan -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px;">
                    <div style="padding: 12px 14px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; text-align: center;">
                        <span style="font-size: 11px; color: #166534; font-weight: 700; text-transform: uppercase;">Üretilen</span>
                        <div id="stat-produced" style="font-size: 26px; font-weight: 800; color: #15803d; margin-top: 2px;">
                            <?= number_format($produced, 0, ',', '.') ?>
                        </div>
                    </div>
                    <div style="padding: 12px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; text-align: center;">
                        <span style="font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase;">Hedef</span>
                        <div id="stat-planned" style="font-size: 26px; font-weight: 800; color: #0f172a; margin-top: 2px;">
                            <?= number_format($planned, 0, ',', '.') ?>
                        </div>
                    </div>
                    <div style="padding: 12px 14px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; text-align: center;">
                        <span style="font-size: 11px; color: #92400e; font-weight: 700; text-transform: uppercase;">Kalan</span>
                        <div id="stat-remaining" style="font-size: 26px; font-weight: 800; color: #b45309; margin-top: 2px;">
                            <?= number_format($remaining, 0, ',', '.') ?>
                        </div>
                    </div>
                </div>

                <!-- İlerleme Çubuğu ve % -->
                <div style="margin-bottom: 14px;">
                    <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 700; margin-bottom: 6px;">
                        <span style="color: #64748b;">İlerleme</span>
                        <span id="stat-pct" style="color: #2563eb;">%<?= number_format($pct, 1, ',', '.') ?></span>
                    </div>
                    <div style="height: 10px; background: #e2e8f0; border-radius: 5px; overflow: hidden;">
                        <div id="stat-prog-bar" style="width: <?= min(100, $pct) ?>%; height: 100%; background: #2563eb; border-radius: 5px; transition: width 0.3s ease;"></div>
                    </div>
                </div>

                <!-- Bilgi Satırları -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12px; color: #475569; padding-top: 10px; border-top: 1px solid #f1f5f9;">
                    <div><strong>Ürün:</strong> <span id="info-product"><?= htmlspecialchars($wo['product_name'] ?? '-') ?></span></div>
                    <div><strong>Hat:</strong> <span id="info-line"><?= htmlspecialchars($wo['line_name'] ?? '-') ?></span></div>
                    <div><strong>Toplam Süre:</strong> <span id="info-total-duration"><?= htmlspecialchars($sim['total_estimated_human'] ?? '-') ?></span></div>
                    <div><strong>Tahmini Bitiş:</strong> <span id="info-est-end"><?= htmlspecialchars($sim['estimated_completion_human'] ?? '-') ?></span></div>
                    <div><strong>Son Panel:</strong> <span id="info-last-run"><?= !empty($sim['last_run_at']) ? date('H:i:s', strtotime($sim['last_run_at'])) : '-' ?></span></div>
                    <div><strong>Son Olay:</strong> <span id="info-last-event" style="font-family: monospace;"><?= htmlspecialchars($sim['last_event_id'] ?? '-') ?></span></div>
                </div>
            </div>

            <!-- Canlı Zamanlayıcı & Üretim Parametreleri -->
            <div class="card" style="padding: 22px; background: #ffffff; border: 1px solid #edf2f7; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                    <!-- Sonraki Panel Geri Sayımı -->
                    <div style="padding: 14px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; text-align: center;">
                        <span style="font-size: 11px; font-weight: 700; color: #1e40af; text-transform: uppercase; display: block;">
                            SONRAKİ PANEL
                        </span>
                        <div id="timer-display" style="font-family: monospace; font-size: 28px; font-weight: 800; color: #1d4ed8; margin: 2px 0;">
                            <?= $isCompleted ? 'TAMAMLANDI' : sprintf('%02d:%02d', floor($remainingSec / 60), $remainingSec % 60) ?>
                        </div>
                    </div>

                    <!-- Üretim Hızı Göstergesi -->
                    <div style="padding: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; text-align: center;">
                        <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; display: block;">
                            ÜRETİM HIZI
                        </span>
                        <div id="speed-label-display" style="font-size: 18px; font-weight: 800; color: #0f172a; margin-top: 6px;">
                            <?= htmlspecialchars($speedHuman) ?>
                        </div>
                    </div>
                </div>

                <!-- 2 Temel Parametre: Üretilecek Adet & Üretim Süresi -->
                <div style="display: grid; grid-template-columns: 1fr 1.3fr; gap: 12px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 5px;">1. Üretilecek Adet (Hedef):</label>
                        <input type="number" id="sim-target-qty" min="1" step="1" class="form-input" style="width: 100%; padding: 8px 12px; font-size: 13.5px; font-weight: 700; border: 1px solid #cbd5e1; border-radius: 6px;" value="<?= (int)$planned ?>" <?= ($isSimActive || $isCompleted) ? 'disabled' : '' ?>>
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 5px;">2. Panel Başına Süre:</label>
                        <select id="sim-speed-select" class="form-input" style="width: 100%; padding: 8px 12px; font-size: 13px; font-weight: 600; border: 1px solid #cbd5e1; border-radius: 6px;" onchange="onUserChangeSpeed(this.value)" <?= ($isSimActive || $isCompleted) ? 'disabled' : '' ?>>
                            <option value="180" <?= $intervalSec === 180 ? 'selected' : '' ?>>🏭 180 Saniye (3 Dk / Panel)</option>
                            <option value="60" <?= $intervalSec === 60 ? 'selected' : '' ?>>⏱️ 60 Saniye (1 Dk / Panel)</option>
                            <option value="30" <?= $intervalSec === 30 ? 'selected' : '' ?>>⚡ 30 Saniye (Seri Üretim)</option>
                            <option value="5" <?= $intervalSec === 5 ? 'selected' : '' ?>>⚡ 5 Saniye (Süper Hızlı Test)</option>
                            <option value="300" <?= $intervalSec === 300 ? 'selected' : '' ?>>⏳ 300 Saniye (5 Dk / Panel)</option>
                            <option value="600" <?= $intervalSec === 600 ? 'selected' : '' ?>>⏳ 600 Saniye (10 Dk / Panel)</option>
                        </select>
                    </div>
                </div>

                <!-- Ana Kontrol Butonları: [ ÜRETİMİ BAŞLAT ] [ DURAKLAT ] [ DEVAM ET ] [ +1 PANEL ÜRET ] -->
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 12px;">
                    <button type="button" id="btn-start-sim" onclick="handleStartSim()" class="button button-primary" style="padding: 11px 8px; font-size: 12.5px; font-weight: 700; text-align: center;" <?= ($isSimActive || $isCompleted) ? 'disabled' : '' ?>>
                        ▶️ BAŞLAT
                    </button>
                    <button type="button" id="btn-pause-sim" onclick="handlePauseSim()" class="button button-secondary" style="padding: 11px 8px; font-size: 12.5px; font-weight: 700; text-align: center;" <?= (!$isSimActive || $isCompleted) ? 'disabled' : '' ?>>
                        ⏸️ DURAKLAT
                    </button>
                    <button type="button" id="btn-resume-sim" onclick="handleResumeSim()" class="button button-secondary" style="padding: 11px 8px; font-size: 12.5px; font-weight: 700; text-align: center;" <?= ($isSimActive || $isCompleted) ? 'disabled' : '' ?>>
                        ⏯️ DEVAM ET
                    </button>
                    <button type="button" id="btn-step-sim" onclick="handleStepSim()" class="button button-success" style="padding: 11px 8px; font-size: 12.5px; font-weight: 700; text-align: center;" <?= $isCompleted ? 'disabled' : '' ?>>
                        ☀️ +1 PANEL
                    </button>
                </div>

                <!-- Bildirim Kutusu -->
                <div id="sim-alert-box" style="display: none; margin-top: 14px; padding: 14px; border-radius: 8px; font-size: 13px; line-height: 1.5;"></div>
            </div>

        </div>

        <!-- SAĞ: CANLI MES ÜRETİM OLAY GÜNLÜĞÜ (LIVE EVENT STREAM) -->
        <div class="card" style="padding: 22px; background: #ffffff; border: 1px solid #edf2f7; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); display: flex; flex-direction: column;">
            
            <!-- Son İşlem Hero Özeti -->
            <div id="hero-last-action-box" style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: <?= $latestAction['bg'] ?>; border: 1px solid <?= $latestAction['border'] ?>; border-radius: 8px; margin-bottom: 14px; flex-wrap: wrap; gap: 8px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span id="hero-last-action-icon" style="font-size: 18px;"><?= $latestAction['icon'] ?></span>
                    <div>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <strong id="hero-last-action-title" style="font-size: 13px; color: <?= $latestAction['color'] ?>; font-weight: 700;">
                                <?= htmlspecialchars($latestAction['title']) ?>
                            </strong>
                            <span id="hero-last-action-time" style="font-size: 11px; font-family: monospace; color: <?= $latestAction['color'] ?>; opacity: 0.85;">
                                <?= htmlspecialchars($latestAction['time']) ?>
                            </span>
                        </div>
                        <div id="hero-last-action-details" style="font-size: 11.5px; color: <?= $latestAction['color'] ?>; opacity: 0.9; margin-top: 1px;">
                            <?= htmlspecialchars($latestAction['subtitle']) ?>
                        </div>
                    </div>
                </div>
                <span style="font-size: 10px; font-weight: 700; color: <?= $latestAction['color'] ?>; text-transform: uppercase; letter-spacing: 0.04em; background: rgba(255,255,255,0.6); padding: 2px 6px; border-radius: 4px;">
                    SON İŞLEM
                </span>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                <h3 style="font-size: 14.5px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <span>📡</span> Canlı MES Üretim Olay Günlüğü
                </h3>
                <span id="stream-pulse" style="font-size: 11px; padding: 3px 10px; background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; border-radius: 12px; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                    <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #16a34a;"></span>
                    Canlı Telemetri
                </span>
            </div>

            <!-- Kullanıcı Dostu Event Listesi -->
            <div id="events-container" style="flex: 1; overflow-y: auto; max-height: 480px; border: 1px solid #f1f5f9; border-radius: 8px;">
                <table class="materials-table" style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                    <thead>
                        <tr style="background: #f8fafc; text-align: left; position: sticky; top: 0; z-index: 2;">
                            <th style="padding: 8px 10px; width: 75px;">Zaman</th>
                            <th style="padding: 8px 10px;">Olay &amp; Üretim Detayı</th>
                            <th style="padding: 8px 10px; text-align: right; width: 60px;">Miktar</th>
                            <th style="padding: 8px 10px; text-align: center; width: 85px;">Durum</th>
                        </tr>
                    </thead>
                    <tbody id="events-tbody">
                        <?php if (empty($eventsHuman)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 24px; color: #64748b;">
                                    Henüz üretim olayı gerçekleşmedi. Simülasyonu başlatın.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($eventsHuman as $ev): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 8px 10px; color: #64748b; font-size: 11px; font-family: monospace; vertical-align: top;">
                                        <?= htmlspecialchars($ev['time'] ?? '-') ?>
                                    </td>
                                    <td style="padding: 8px 10px; vertical-align: top;">
                                        <div style="font-weight: 700; color: #0f172a; font-size: 12.5px;">
                                            <?= htmlspecialchars($ev['title'] ?? 'PANEL TAMAMLANDI') ?>
                                        </div>
                                        <?php if (!empty($ev['details'])): ?>
                                            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                                <?= $ev['details'] ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($ev['stock_summary'])): ?>
                                            <div style="font-size: 11px; color: #16a34a; font-weight: 600; margin-top: 1px;">
                                                <?= htmlspecialchars($ev['stock_summary']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div style="font-size: 10px; font-family: monospace; color: #94a3b8; margin-top: 2px;">
                                            ID: <?= htmlspecialchars($ev['event_id']) ?>
                                        </div>
                                    </td>
                                    <td style="padding: 8px 10px; text-align: right; font-weight: 700; color: #16a34a; vertical-align: top;">
                                        +<?= number_format((float)($ev['quantity'] ?? 1), 0) ?>
                                    </td>
                                    <td style="padding: 8px 10px; text-align: center; vertical-align: top;">
                                        <span style="font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; background: <?= $ev['badge']['bg'] ?? '#f0fdf4' ?>; color: <?= $ev['badge']['color'] ?? '#166534' ?>; border: 1px solid <?= $ev['badge']['border'] ?? '#bbf7d0' ?>;">
                                            <?= htmlspecialchars($ev['status_label'] ?? 'PROCESSED') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- API Bilgilendirme Notu -->
            <div style="margin-top: 12px; padding: 10px 12px; background: #f8fafc; border-radius: 6px; font-size: 11px; color: #64748b; display: flex; justify-content: space-between; align-items: center;">
                <span>🔌 <strong>Gerçek MES API:</strong> <code>POST /api/mes/events</code></span>
                <span style="color: #15803d; font-weight: 600;">✓ BOM &amp; Stok Entegre</span>
            </div>
        </div>

    </div>

</main>

<script>
let currentWoId = <?= $selectedWoId ?>;
let currentRemainingSeconds = <?= $remainingSec ?>;
let isSimulationActive = <?= $isSimActive ? 'true' : 'false' ?>;
let currentIntervalSeconds = <?= $intervalSec ?>;
let isLineBlocked = false;
let lastNextRunAt = <?= json_encode($sim['next_run_at'] ?? null) ?>;
let lastProducedQty = <?= (float)($wo['produced_quantity'] ?? 0) ?>;
let isFirstLoad = true;

function formatCountdown(sec) {
    if (sec <= 0) return '00:00';
    const m = Math.floor(sec / 60);
    const s = sec % 60;
    return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
}

function formatSpeedHuman(sec) {
    sec = parseInt(sec) || 180;
    if (sec >= 60 && sec % 60 === 0) {
        return (sec / 60) + ' dakika / panel';
    } else if (sec >= 60) {
        return Math.floor(sec / 60) + ' dk ' + (sec % 60) + ' sn / panel';
    } else {
        return sec + ' saniye / panel';
    }
}

function onUserChangeSpeed(val) {
    const sec = parseInt(val) || 180;
    currentIntervalSeconds = sec;
    const label = document.getElementById('speed-label-display');
    if (label) label.innerText = formatSpeedHuman(sec);
    
    // If not running, also update timer display to new speed
    if (!isSimulationActive) {
        currentRemainingSeconds = sec;
        const timerEl = document.getElementById('timer-display');
        if (timerEl) timerEl.innerText = formatCountdown(sec);
    }
}

function updateTelemetryUI(data) {
    if (!data) return;
    const wo = data.work_order || (data.telemetry ? data.telemetry.work_order : null);
    if (!wo) return;

    const sim = data.simulation || (data.telemetry ? data.telemetry.simulation : null) || {};
    const eventsHuman = data.recent_events_human || (data.telemetry ? data.telemetry.recent_events_human : null) || [];
    const latestAction = data.latest_action_summary || (data.telemetry ? data.telemetry.latest_action_summary : null);
    const lines = data.production_lines || [];

    // 1. Work Order Metrics
    const planned = Number(wo.planned_quantity);
    const produced = Number(wo.produced_quantity);
    const remaining = Number(wo.remaining_quantity);
    const pct = Number(wo.progress_pct);
    const isCompleted = (wo.status === 'COMPLETED' || produced >= planned);

    document.getElementById('stat-planned').innerText = planned.toLocaleString('tr-TR');
    document.getElementById('stat-produced').innerText = produced.toLocaleString('tr-TR');
    document.getElementById('stat-remaining').innerText = remaining.toLocaleString('tr-TR');
    document.getElementById('stat-pct').innerText = '%' + pct.toLocaleString('tr-TR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
    document.getElementById('stat-prog-bar').style.width = Math.min(100, pct) + '%';

    const targetInput = document.getElementById('sim-target-qty');
    if (targetInput && !document.activeElement.isSameNode(targetInput)) {
        targetInput.value = planned;
    }
    
    document.getElementById('info-product').innerText = wo.product_name + ' (' + wo.product_code + ')';
    document.getElementById('info-line').innerText = wo.line_name;
    if (document.getElementById('info-total-duration')) {
        document.getElementById('info-total-duration').innerText = sim.total_estimated_human || '-';
    }
    if (document.getElementById('info-est-end')) {
        document.getElementById('info-est-end').innerText = sim.estimated_completion_human || '-';
    }
    document.getElementById('info-last-run').innerText = sim.last_run_at ? new Date(sim.last_run_at).toLocaleTimeString('tr-TR') : '-';
    document.getElementById('info-last-event').innerText = sim.last_event_id || '-';

    // 2. Simulation State & Synchronization
    const newIsActive = Boolean(sim.is_active);
    const newNextRunAt = sim.next_run_at || null;
    const serverRemaining = Number.isInteger(parseInt(sim.remaining_seconds)) ? parseInt(sim.remaining_seconds) : null;
    const newInterval = parseInt(sim.interval_seconds) || currentIntervalSeconds;

    const isNewCycle = (newNextRunAt !== null && newNextRunAt !== lastNextRunAt);
    const isNewPanelProduced = (produced !== lastProducedQty);
    const isStateChanged = (newIsActive !== isSimulationActive);
    const isIntervalChanged = (newInterval !== currentIntervalSeconds);

    isSimulationActive = newIsActive;
    currentIntervalSeconds = newInterval;

    // Synchronize speed display with backend truth
    const speedLabel = document.getElementById('speed-label-display');
    if (speedLabel) {
        speedLabel.innerText = sim.interval_human || formatSpeedHuman(currentIntervalSeconds);
    }

    const speedSelect = document.getElementById('sim-speed-select');
    if (speedSelect) {
        // Ensure option exists
        let found = false;
        for (let opt of speedSelect.options) {
            if (parseInt(opt.value) === currentIntervalSeconds) {
                found = true;
                break;
            }
        }
        if (!found && currentIntervalSeconds > 0) {
            const newOpt = document.createElement('option');
            newOpt.value = String(currentIntervalSeconds);
            newOpt.text = '⏱️ ' + currentIntervalSeconds + ' Saniye (' + formatSpeedHuman(currentIntervalSeconds) + ')';
            speedSelect.appendChild(newOpt);
        }
        speedSelect.value = String(currentIntervalSeconds);
        speedSelect.disabled = isSimulationActive || isCompleted;
    }

    if (targetInput) {
        targetInput.disabled = isSimulationActive || isCompleted;
    }

    // Line Status Verification
    let lineObj = lines.find(l => parseInt(l.id) === parseInt(wo.production_line_id));
    isLineBlocked = false;
    if (lineObj && (lineObj.effective_status === 'MAINTENANCE' || lineObj.effective_status === 'FAULT')) {
        isLineBlocked = true;
    }

    // Countdown / Timer synchronization (Strict & Continuous)
    if (isCompleted) {
        currentRemainingSeconds = 0;
        lastNextRunAt = null;
        lastProducedQty = produced;
    } else if (!isSimulationActive) {
        currentRemainingSeconds = (serverRemaining !== null) ? Math.max(0, serverRemaining) : currentIntervalSeconds;
        lastNextRunAt = newNextRunAt;
        lastProducedQty = produced;
    } else {
        // ACTIVE SIMULATION:
        // Reset countdown ONLY when a new cycle starts (new next_run_at), a new panel is produced, state became active, or interval changed.
        if (isFirstLoad || isNewCycle || isNewPanelProduced || isStateChanged || isIntervalChanged) {
            currentRemainingSeconds = (serverRemaining !== null) ? Math.max(0, serverRemaining) : currentIntervalSeconds;
            lastNextRunAt = newNextRunAt;
            lastProducedQty = produced;
        }
    }
    isFirstLoad = false;

    const badge = document.getElementById('badge-sim-status');
    const btnStart = document.getElementById('btn-start-sim');
    const btnPause = document.getElementById('btn-pause-sim');
    const btnResume = document.getElementById('btn-resume-sim');
    const btnStep = document.getElementById('btn-step-sim');
    const connText = document.getElementById('conn-text');
    const connDot = document.getElementById('conn-dot');
    const connBox = document.getElementById('mes-conn-status');

    if (isCompleted) {
        badge.className = 'status-badge status-badge-success';
        badge.innerText = '🏆 TAMAMLANDI';
        connText.innerText = '🏆 HEDEF TAMAMLANDI (' + planned + '/' + planned + ')';
        connDot.style.background = '#16a34a';
        connDot.style.boxShadow = '0 0 8px #16a34a';
        connBox.style.background = '#f0fdf4';
        connBox.style.borderColor = '#bbf7d0';
        btnStart.disabled = true;
        btnPause.disabled = true;
        btnResume.disabled = true;
        btnStep.disabled = true;
        document.getElementById('timer-display').innerText = 'TAMAMLANDI';
    } else if (isLineBlocked) {
        badge.className = 'status-badge status-badge-danger';
        badge.innerText = lineObj.status_badge || '🔴 HAT KAPALI';
        connText.innerText = '⚠️ HAT UYGUN DEĞİL (' + (lineObj.status_label || 'KAPALI') + ')';
        connDot.style.background = '#ef4444';
        connDot.style.boxShadow = '0 0 8px #ef4444';
        connBox.style.background = '#fef2f2';
        connBox.style.borderColor = '#fecaca';
        btnStart.disabled = true;
        btnPause.disabled = true;
        btnResume.disabled = true;
        btnStep.disabled = true;
        document.getElementById('timer-display').innerText = lineObj.status_label || 'KAPALI';
    } else if (isSimulationActive) {
        badge.className = 'status-badge status-badge-info';
        badge.innerText = '⚙️ ÇALIŞIYOR';
        connText.innerText = '🟢 ÜRETİM AKTİF (' + currentIntervalSeconds + ' sn)';
        connDot.style.background = '#16a34a';
        connDot.style.boxShadow = '0 0 8px #16a34a';
        connBox.style.background = '#f0fdf4';
        connBox.style.borderColor = '#bbf7d0';
        btnStart.disabled = true;
        btnPause.disabled = false;
        btnResume.disabled = true;
        btnStep.disabled = false;
        document.getElementById('timer-display').innerText = formatCountdown(currentRemainingSeconds);
    } else if (sim.last_status === 'FAILED') {
        badge.className = 'status-badge status-badge-danger';
        badge.innerText = '❌ HATA / DURDURULDU';
        connText.innerText = '🔴 ÜRETİM DURDURULDU';
        connDot.style.background = '#ef4444';
        connDot.style.boxShadow = '0 0 8px #ef4444';
        connBox.style.background = '#fef2f2';
        connBox.style.borderColor = '#fecaca';
        btnStart.disabled = false;
        btnPause.disabled = true;
        btnResume.disabled = false;
        btnStep.disabled = false;
        document.getElementById('timer-display').innerText = formatCountdown(currentRemainingSeconds);
    } else {
        badge.className = 'status-badge status-badge-secondary';
        badge.innerText = '⏸️ DURAKLATILDI';
        connText.innerText = '⏸️ DURAKLATILDI';
        connDot.style.background = '#94a3b8';
        connDot.style.boxShadow = 'none';
        connBox.style.background = '#f8fafc';
        connBox.style.borderColor = '#e2e8f0';
        btnStart.disabled = false;
        btnPause.disabled = true;
        btnResume.disabled = false;
        btnStep.disabled = false;
        document.getElementById('timer-display').innerText = formatCountdown(currentRemainingSeconds);
    }

    // 3. Render Hero Last Action
    if (latestAction) {
        const heroBox = document.getElementById('hero-last-action-box');
        if (heroBox) {
            heroBox.style.background = latestAction.bg;
            heroBox.style.borderColor = latestAction.border;
            document.getElementById('hero-last-action-icon').innerText = latestAction.icon;
            const t = document.getElementById('hero-last-action-title');
            t.innerText = latestAction.title;
            t.style.color = latestAction.color;
            const tm = document.getElementById('hero-last-action-time');
            tm.innerText = latestAction.time;
            tm.style.color = latestAction.color;
            const d = document.getElementById('hero-last-action-details');
            d.innerText = latestAction.subtitle;
            d.style.color = latestAction.color;
        }
    }

    // 4. Render Human-Friendly Events Table
    renderHumanEventsTable(eventsHuman);
}

function renderHumanEventsTable(events) {
    const tbody = document.getElementById('events-tbody');
    if (!tbody) return;

    if (!events || events.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:24px; color:#64748b;">Henüz üretim olayı gerçekleşmedi. Simülasyonu başlatın.</td></tr>';
        return;
    }

    let html = '';
    events.forEach(ev => {
        const badge = ev.badge || { bg: '#f0fdf4', color: '#166534', border: '#bbf7d0' };
        const detailsHtml = ev.details ? `<div style="font-size: 11px; color: #64748b; margin-top: 2px;">${ev.details}</div>` : '';
        const stockHtml = ev.stock_summary ? `<div style="font-size: 11px; color: #16a34a; font-weight: 600; margin-top: 1px;">${ev.stock_summary}</div>` : '';
        const qty = Number(ev.quantity || 1);

        html += `
            <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 8px 10px; color: #64748b; font-size: 11px; font-family: monospace; vertical-align: top;">
                    ${ev.time || '-'}
                </td>
                <td style="padding: 8px 10px; vertical-align: top;">
                    <div style="font-weight: 700; color: #0f172a; font-size: 12.5px;">
                        ${ev.title || 'PANEL TAMAMLANDI'}
                    </div>
                    ${detailsHtml}
                    ${stockHtml}
                    <div style="font-size: 10px; font-family: monospace; color: #94a3b8; margin-top: 2px;">
                        ID: ${ev.event_id}
                    </div>
                </td>
                <td style="padding: 8px 10px; text-align: right; font-weight: 700; color: #16a34a; vertical-align: top;">
                    +${qty.toLocaleString('tr-TR')}
                </td>
                <td style="padding: 8px 10px; text-align: center; vertical-align: top;">
                    <span style="font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; background: ${badge.bg}; color: ${badge.color}; border: 1px solid ${badge.border};">
                        ${ev.status_label || 'PROCESSED'}
                    </span>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function fetchTelemetry() {
    if (!currentWoId) return;
    fetch('/stok-takip/public/mes/simulator/status?work_order_id=' + currentWoId)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                updateTelemetryUI(data);
            }
        })
        .catch(err => console.error('Status fetch error:', err));
}

const simCsrfToken = '<?= CsrfService::generateToken() ?>';

function handleStartSim() {
    const speed = parseInt(document.getElementById('sim-speed-select').value) || 180;
    const targetQty = parseFloat(document.getElementById('sim-target-qty').value) || 100;
    const btnStart = document.getElementById('btn-start-sim');
    if (btnStart) {
        btnStart.disabled = true;
    }

    const formData = new FormData();
    formData.append('work_order_id', currentWoId);
    formData.append('interval_seconds', speed);
    formData.append('planned_quantity', targetQty);
    formData.append('csrf_token', simCsrfToken);

    fetch('/stok-takip/public/mes/simulator/start', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.work_order || data.telemetry) {
            updateTelemetryUI(data.telemetry || data);
            if (data.success !== false) {
                showAlert({ success: true, message: '✓ Canlı üretim simülasyonu başlatıldı. Hız: ' + formatSpeedHuman(speed) + '.' });
            } else {
                showAlert(data);
            }
        } else if (data.success === false) {
            showAlert(data);
        }
    })
    .catch(err => {
        console.error('Start error:', err);
        showAlert({ success: false, message: 'Simülasyon başlatılamadı: Sunucu hatası.' });
    });
}

function handlePauseSim() {
    const btnPause = document.getElementById('btn-pause-sim');
    if (btnPause) {
        btnPause.disabled = true;
    }

    const formData = new FormData();
    formData.append('work_order_id', currentWoId);
    formData.append('reason', 'Kullanıcı duraklattı');
    formData.append('csrf_token', simCsrfToken);

    fetch('/stok-takip/public/mes/simulator/pause', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.work_order || data.telemetry) {
            updateTelemetryUI(data.telemetry || data);
            showAlert({ success: false, status: 'PAUSED', message: '⏸️ Simülasyon duraklatıldı. Kalan süre (' + currentRemainingSeconds + ' sn) korundu.' });
        } else if (data.success === false) {
            showAlert(data);
        }
    })
    .catch(err => {
        console.error('Pause error:', err);
        showAlert({ success: false, message: 'Simülasyon duraklatılamadı: Sunucu hatası.' });
    });
}

function handleResumeSim() {
    handleStartSim();
}

function handleStepSim() {
    const btnStep = document.getElementById('btn-step-sim');
    if (btnStep) {
        btnStep.disabled = true;
    }

    const formData = new FormData();
    formData.append('work_order_id', currentWoId);
    formData.append('force_step', '1');
    formData.append('csrf_token', simCsrfToken);

    fetch('/stok-takip/public/mes/simulator/tick', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (btnStep) btnStep.disabled = false;
        if (data.telemetry || data.work_order) {
            updateTelemetryUI(data.telemetry || data);
        }
        showAlert(data);
    })
    .catch(err => {
        if (btnStep) btnStep.disabled = false;
        console.error('Step error:', err);
        showAlert({ success: false, message: 'Manuel adım gerçekleştirilemedi: Sunucu hatası.' });
    });
}

function runClientVisualCountdown() {
    if (!isSimulationActive) return;

    if (currentRemainingSeconds > 0) {
        currentRemainingSeconds--;
        const timerEl = document.getElementById('timer-display');
        if (timerEl) timerEl.innerText = formatCountdown(currentRemainingSeconds);
    } else {
        const timerEl = document.getElementById('timer-display');
        if (timerEl) timerEl.innerText = 'ÜRETİLİYOR...';
    }
}

function switchWorkOrder(woId) {
    currentWoId = parseInt(woId);
    window.location.href = '/stok-takip/public/mes/simulator?wo_id=' + currentWoId;
}

function showAlert(data) {
    const box = document.getElementById('sim-alert-box');
    if (!box) return;
    box.style.display = 'block';

    if (data.success && (data.status === 'PROCESSED' || data.action === 'PANEL_PRODUCED')) {
        box.style.background = '#f0fdf4';
        box.style.border = '1px solid #bbf7d0';
        box.style.color = '#166534';
        let stockRefHtml = data.stock_reference ? `<div style="font-size: 11.5px; margin-top: 4px; font-family: monospace; color: #15803d;">Stok Referansı: <strong>${data.stock_reference}</strong></div>` : '';
        box.innerHTML = `
            <div style="font-weight: 800; font-size: 13.5px; margin-bottom: 4px;">✓ ${data.message || '1 panel üretildi ve stoğa eklendi.'}</div>
            <div style="font-size: 12px; color: #15803d;">
                &bull; BOM hammadde çıkışları (OUT) ve mamul panel girişi (IN) yapıldı.
            </div>
            ${stockRefHtml}
        `;
    } else if (data.status === 'COMPLETED') {
        box.style.background = '#eff6ff';
        box.style.border = '1px solid #bfdbfe';
        box.style.color = '#1e40af';
        box.innerHTML = `<div style="font-weight:800; font-size:13.5px;">🏆 İş Emri Tamamlandı! Hedeflenen panel miktarına ulaşıldı.</div>`;
    } else if (data.status === 'PAUSED') {
        box.style.background = '#f8fafc';
        box.style.border = '1px solid #e2e8f0';
        box.style.color = '#475569';
        box.innerHTML = `<div>${data.message || 'Simülasyon duraklatıldı.'}</div>`;
    } else {
        box.style.background = '#fef2f2';
        box.style.border = '1px solid #fecaca';
        box.style.color = '#991b1b';
        let detailsHtml = '';
        if (data.details && Array.isArray(data.details) && data.details.length > 0) {
            detailsHtml = '<div style="margin-top:8px; padding:8px 10px; background:#fff; border:1px solid #fecaca; border-radius:6px; font-size:12px; color:#334155;">' +
                data.details.map(it => {
                    if (typeof it === 'object' && it.material_name) {
                        return `<div style="margin-bottom:3px;"><strong>${it.material_name} (${it.material_code}):</strong> Gerekli: ${it.required_qty} ${it.unit_symbol || 'AD'}, Mevcut: ${it.available_qty} ${it.unit_symbol || 'AD'}, <span style="color:#dc2626; font-weight:700;">Eksik: ${it.missing_qty} ${it.unit_symbol || 'AD'}</span></div>`;
                    }
                    return `<div style="margin-bottom:3px;">&bull; ${it}</div>`;
                }).join('') + '</div>';
        }
        box.innerHTML = `
            <div style="font-weight: 800; font-size: 13.5px; margin-bottom: 2px;">⚠ ÜRETİM DURDURULDU</div>
            <div style="font-size: 12px; margin-top: 4px;"><strong>Durum Nedeni:</strong> ${data.message || data.error || 'Hammadde yetersizliği veya sistem durumu'}</div>
            ${detailsHtml}
            <div style="font-size: 11px; margin-top: 4px; color: #7f1d1d;">(İşlem güvenle kontrol altında tutuldu, bakiye bozulmadı)</div>
        `;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    fetchTelemetry();
    // 1. Client-side visual-only second decrement
    setInterval(runClientVisualCountdown, 1000);
    // 2. Periodic backend telemetry status polling (reads worker results without triggering duplicate ticks)
    setInterval(fetchTelemetry, 2000);
});
</script>
