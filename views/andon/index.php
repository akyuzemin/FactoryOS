<?php

$pageTitle = 'Canlı Andon & Fabrika Vitrini';
$isKiosk = isset($_GET['kiosk']) && $_GET['kiosk'] == '1';

if (!$isKiosk) {
    require __DIR__ . '/../layouts/header.php';
    require __DIR__ . '/../layouts/sidebar.php';
} else {
    // Pure standalone kiosk header
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Canlı Andon &amp; Fabrika Vitrini (Kiosk TV Modu)</title>
        <link rel="stylesheet" href="/stok-takip/public/css/app.css">
    </head>
    <body class="andon-kiosk-body" style="margin: 0; padding: 0; background: #0b0f19; color: #f8fafc; font-family: system-ui, -apple-system, sans-serif; overflow-x: hidden;">
    <?php
}
?>

<style>
@keyframes andonFaultPulse {
    0%, 100% {
        box-shadow: 0 0 25px rgba(239, 68, 68, 0.4), inset 0 0 15px rgba(239, 68, 68, 0.2);
        border-color: #ef4444;
    }
    50% {
        box-shadow: 0 0 45px rgba(239, 68, 68, 0.85), inset 0 0 30px rgba(239, 68, 68, 0.4);
        border-color: #f87171;
    }
}

@keyframes andonBannerPulse {
    0%, 100% { background-color: #991b1b; }
    50% { background-color: #dc2626; }
}

.andon-wrap {
    background: #0b0f19;
    color: #f8fafc;
    min-height: 100vh;
    padding: <?= $isKiosk ? '16px 20px' : '20px' ?>;
    box-sizing: border-box;
}

.andon-card {
    background: #131b2e;
    border: 1px solid #1e293b;
    border-radius: 14px;
    padding: 18px 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
    transition: all 0.25s ease;
    position: relative;
    overflow: hidden;
}

.status-running {
    border-top: 5px solid #10b981 !important;
}
.status-fault {
    border-top: 5px solid #ef4444 !important;
    animation: andonFaultPulse 2s infinite ease-in-out;
    background: #1e1522 !important;
}
.status-maintenance {
    border-top: 5px solid #f59e0b !important;
    background: #1f1b18 !important;
}
.status-idle {
    border-top: 5px solid #64748b !important;
}

.andon-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.andon-badge-running { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid #059669; }
.andon-badge-fault { background: rgba(239, 68, 68, 0.25); color: #f87171; border: 1px solid #dc2626; }
.andon-badge-maintenance { background: rgba(245, 158, 11, 0.25); color: #fbbf24; border: 1px solid #d97706; }
.andon-badge-idle { background: rgba(100, 116, 139, 0.2); color: #94a3b8; border: 1px solid #475569; }

.andon-metric-box {
    background: #0d1322;
    border: 1px solid #1e293b;
    border-radius: 10px;
    padding: 10px 14px;
    text-align: center;
}

.pulse-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}
.pulse-dot-green { background: #10b981; box-shadow: 0 0 10px #10b981; }
.pulse-dot-red { background: #ef4444; box-shadow: 0 0 12px #ef4444; animation: andonFaultPulse 1.5s infinite; }
</style>

<div class="<?= $isKiosk ? 'andon-kiosk-wrap' : 'main-content' ?> andon-wrap" id="andon-root">

    <!-- ANDON TOP HEADER & TELEMETRY BAR -->
    <header style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 18px; border-bottom: 1px solid #1e293b; padding-bottom: 14px;">
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="width: 44px; height: 44px; background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4);">
                📺
            </div>
            <div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <h1 style="margin: 0; font-size: 24px; font-weight: 900; letter-spacing: -0.02em; color: #ffffff;">CANLI ANDON &amp; FABRİKA VİTRİNİ</h1>
                    <span id="live-indicator-badge" class="andon-badge andon-badge-running" style="font-size: 11px; padding: 2px 8px;">
                        <span class="pulse-dot pulse-dot-green"></span> CANLI TELEMETRİ (5s)
                    </span>
                </div>
                <div style="font-size: 12.5px; color: #94a3b8; margin-top: 2px;">
                    Fotovoltaik Güneş Paneli Üretim Hattı &bull; ISO 22400 / SEMI E10
                </div>
            </div>
        </div>

        <!-- RIGHT CONTROLS & DIGITAL CLOCK -->
        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
            <!-- DIGITAL CLOCK -->
            <div style="background: #131b2e; border: 1px solid #1e293b; border-radius: 10px; padding: 6px 16px; text-align: right;">
                <div id="andon-clock" style="font-size: 20px; font-weight: 900; font-family: monospace; color: #38bdf8; letter-spacing: 0.05em;">
                    <?= $data['current_time_fmt'] ?>
                </div>
                <div id="andon-date" style="font-size: 11px; color: #64748b; font-weight: 600;">
                    <?= $data['current_date_fmt'] ?>
                </div>
            </div>

            <!-- AUDIO TOGGLE BUTTON -->
            <button id="btn-audio-toggle" onclick="toggleAudioAlarm()" class="button" style="background: #1e293b; color: #cbd5e1; border: 1px solid #334155; padding: 8px 14px; font-size: 12.5px; font-weight: 700; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                <span id="audio-icon">🔇</span> <span id="audio-label">Ses Kapalı</span>
            </button>

            <!-- KIOSK / FULLSCREEN TOGGLE -->
            <?php if (!$isKiosk): ?>
                <a href="/stok-takip/public/andon?kiosk=1" target="_blank" class="button button-primary" style="padding: 8px 16px; font-size: 12.5px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                    <span>🖥️</span> TV Kiosk Modu
                </a>
            <?php else: ?>
                <button onclick="toggleFullscreen()" class="button button-primary" style="padding: 8px 16px; font-size: 12.5px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                    <span>⛶</span> Tam Ekran
                </button>
                <a href="/stok-takip/public/andon" class="button" style="background: #1e293b; color: #94a3b8; border: 1px solid #334155; padding: 8px 12px; font-size: 12.5px; text-decoration: none;">
                    Çıkış
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- ACTIVE DOWNTIME BANNER (DYNAMICALLY SHOWN IF ANY LINE IS DOWN) -->
    <div id="andon-alert-banner" style="display: <?= !empty($data['active_downtimes']) ? 'block' : 'none' ?>; margin-bottom: 18px;">
        <?php foreach ($data['active_downtimes'] as $ad): ?>
            <div style="background: #dc2626; animation: andonBannerPulse 2s infinite; color: #ffffff; border-radius: 12px; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; box-shadow: 0 4px 20px rgba(220, 38, 38, 0.5);">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <div style="font-size: 28px;">🚨</div>
                    <div>
                        <div style="font-size: 16px; font-weight: 900; letter-spacing: 0.02em;">
                            AKTİF DURUŞ: <?= htmlspecialchars($ad['line_name']) ?> (<?= htmlspecialchars($ad['line_code']) ?>)
                        </div>
                        <div style="font-size: 13px; color: #fecaca; margin-top: 2px;">
                            Neden: <strong><?= htmlspecialchars($ad['reason_name']) ?></strong> &bull; Başlangıç: <?= $ad['started_at_time'] ?>
                            <?php if (!empty($ad['operator_note'])): ?>
                                &bull; <em>"<?= htmlspecialchars($ad['operator_note']) ?>"</em>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #fee2e2;">DURUŞ SÜRESİ</div>
                    <div class="dt-timer-display" data-started="<?= strtotime($ad['started_at']) ?>" style="font-size: 26px; font-weight: 900; font-family: monospace; color: #ffffff;">
                        <?= $ad['formatted_duration'] ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- FACTORY SUMMARY KPI STRIP (TOP) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 20px;">
        
        <!-- 1. VARDİYA & KALAN SÜRE -->
        <div class="andon-metric-box" style="border-left: 4px solid #38bdf8;">
            <div style="font-size: 11px; font-weight: 800; color: #38bdf8; text-transform: uppercase; letter-spacing: 0.04em;">
                <span id="kpi-shift-name"><?= htmlspecialchars($data['shift']['name']) ?></span>
            </div>
            <div style="font-size: 18px; font-weight: 900; color: #f8fafc; margin: 3px 0;" id="kpi-shift-time-remain">
                <?= $data['shift']['remaining_minutes'] ?> dk kaldı
            </div>
            <div style="font-size: 11px; color: #94a3b8;">
                İlerleme: <b id="kpi-shift-prog">%<?= $data['shift']['progress_pct'] ?></b> (<?= $data['shift']['elapsed_minutes'] ?> dk)
            </div>
        </div>

        <!-- 2. TOPLAM ÜRETİM & HEDEF -->
        <div class="andon-metric-box" style="border-left: 4px solid #10b981;">
            <div style="font-size: 11px; font-weight: 800; color: #10b981; text-transform: uppercase; letter-spacing: 0.04em;">FABRİKA ÜRETİMİ</div>
            <div style="font-size: 20px; font-weight: 900; color: #34d399; margin: 3px 0;">
                <span id="kpi-total-prod"><?= number_format($data['factory_summary']['total_produced_qty'], 0, ',', '.') ?></span> 
                <small style="font-size: 12px; color: #94a3b8;">/ <span id="kpi-total-target"><?= number_format($data['factory_summary']['total_target_qty'], 0, ',', '.') ?></span></small>
            </div>
            <div style="font-size: 11px; color: #94a3b8;">
                Gerçekleşme: <b id="kpi-realization-pct" style="color: #34d399;">%<?= $data['factory_summary']['realization_pct'] ?></b>
            </div>
        </div>

        <!-- 3. FABRİKA OEE -->
        <div class="andon-metric-box" style="border-left: 4px solid #818cf8;">
            <div style="font-size: 11px; font-weight: 800; color: #818cf8; text-transform: uppercase; letter-spacing: 0.04em;">FABRİKA OEE</div>
            <div style="font-size: 22px; font-weight: 900; color: #a5b4fc; margin: 3px 0;" id="kpi-overall-oee">
                %<?= number_format($data['factory_summary']['overall_oee'], 1, ',', '.') ?>
            </div>
            <div style="font-size: 10.5px; color: #94a3b8;">
                A: <b id="kpi-oee-a">%<?= round($data['factory_summary']['overall_availability']) ?></b> | 
                P: <b id="kpi-oee-p">%<?= round($data['factory_summary']['overall_performance']) ?></b> | 
                Q: <b id="kpi-oee-q">%<?= round($data['factory_summary']['overall_quality']) ?></b>
            </div>
        </div>

        <!-- 4. AKTİF DURUŞ SAYISI -->
        <div class="andon-metric-box" style="border-left: 4px solid <?= $data['factory_summary']['active_downtimes_count'] > 0 ? '#ef4444' : '#10b981' ?>;">
            <div style="font-size: 11px; font-weight: 800; color: <?= $data['factory_summary']['active_downtimes_count'] > 0 ? '#f87171' : '#34d399' ?>; text-transform: uppercase;">AKTİF DURUŞ</div>
            <div style="font-size: 22px; font-weight: 900; color: <?= $data['factory_summary']['active_downtimes_count'] > 0 ? '#ef4444' : '#10b981' ?>; margin: 3px 0;" id="kpi-active-dt-cnt">
                <?= $data['factory_summary']['active_downtimes_count'] ?> Hat
            </div>
            <div style="font-size: 11px; color: #94a3b8;">
                Plansız Süre: <b id="kpi-unplan-dt-min"><?= $data['factory_summary']['total_unplanned_dt_min'] ?> dk</b>
            </div>
        </div>

        <!-- 5. AKTİF ALARMLAR -->
        <div class="andon-metric-box" style="border-left: 4px solid <?= $data['factory_summary']['active_alerts_count'] > 0 ? '#f59e0b' : '#64748b' ?>;">
            <div style="font-size: 11px; font-weight: 800; color: #fbbf24; text-transform: uppercase;">TESİS ALARMLARI</div>
            <div style="font-size: 22px; font-weight: 900; color: <?= $data['factory_summary']['active_alerts_count'] > 0 ? '#f59e0b' : '#94a3b8' ?>; margin: 3px 0;" id="kpi-active-alerts-cnt">
                <?= $data['factory_summary']['active_alerts_count'] ?> Olay
            </div>
            <div style="font-size: 11px; color: #94a3b8;">
                Enerji &amp; Güç İzleme
            </div>
        </div>

    </div>

    <!-- 4 PRODUCTION LINES GRID (2x2 / 4x1) -->
    <div id="andon-lines-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 16px;">
        <?php foreach ($data['lines'] as $l): ?>
            <?php
                $statusClass = 'status-idle';
                $badgeClass = 'andon-badge-idle';
                $statusLabel = '⚪ BOŞTA (IDLE)';
                if ($l['status'] === 'RUNNING') {
                    $statusClass = 'status-running';
                    $badgeClass = 'andon-badge-running';
                    $statusLabel = '🟢 ÇALIŞIYOR';
                } elseif ($l['status'] === 'FAULT') {
                    $statusClass = 'status-fault';
                    $badgeClass = 'andon-badge-fault';
                    $statusLabel = '🔴 ARIZA / DURUŞ';
                } elseif ($l['status'] === 'MAINTENANCE') {
                    $statusClass = 'status-maintenance';
                    $badgeClass = 'andon-badge-maintenance';
                    $statusLabel = '🟡 BAKIM / MOLA';
                }
            ?>
            <div class="andon-card <?= $statusClass ?>" id="line-card-<?= $l['id'] ?>">
                
                <!-- LINE CARD HEADER -->
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px;">
                    <div>
                        <div style="font-size: 18px; font-weight: 900; color: #ffffff; letter-spacing: -0.01em;">
                            <?= htmlspecialchars($l['name']) ?>
                        </div>
                        <div style="font-size: 12px; color: #94a3b8; font-family: monospace; margin-top: 2px;">
                            <?= htmlspecialchars($l['code']) ?> &bull; <?= $l['nominal_power_kw'] ?> kW
                            <?php if (!empty($l['active_work_order'])): ?>
                                &bull; <span style="color: #38bdf8;">WO: <?= htmlspecialchars($l['active_work_order']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <span class="andon-badge <?= $badgeClass ?>" id="line-badge-<?= $l['id'] ?>">
                            <?= $statusLabel ?>
                        </span>
                    </div>
                </div>

                <!-- ACTIVE DOWNTIME STRIP IF LINE IS FAULT/MAINTENANCE -->
                <?php if (!empty($l['active_downtime'])): ?>
                    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-size: 11px; font-weight: 800; color: #f87171; text-transform: uppercase;">DURUŞ NEDENİ:</div>
                            <div style="font-size: 14px; font-weight: 800; color: #ffffff;">
                                <?= htmlspecialchars($l['active_downtime']['reason_name']) ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 10px; color: #fca5a5;">SÜRE</div>
                            <div class="dt-timer-display" data-started="<?= strtotime($l['active_downtime']['started_at']) ?>" style="font-size: 18px; font-weight: 900; font-family: monospace; color: #ef4444;">
                                <?= $l['active_downtime']['formatted_duration'] ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- PRODUCTION PROGRESS DISPLAY (BIG NUMBERS) -->
                <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 6px;">
                    <div>
                        <span style="font-size: 34px; font-weight: 900; color: #ffffff; font-family: monospace;">
                            <?= number_format($l['produced_quantity'], 0, ',', '.') ?>
                        </span>
                        <span style="font-size: 16px; color: #64748b; font-weight: 700;">
                            / <?= number_format($l['shift_target'], 0, ',', '.') ?> ADET
                        </span>
                    </div>
                    <div>
                        <span style="font-size: 26px; font-weight: 900; color: <?= $l['realization_pct'] >= 80 ? '#34d399' : ($l['realization_pct'] >= 50 ? '#fbbf24' : '#f87171') ?>;">
                            %<?= number_format($l['realization_pct'], 1, ',', '.') ?>
                        </span>
                    </div>
                </div>

                <!-- PROGRESS BAR -->
                <div style="width: 100%; height: 10px; background: #0d1322; border-radius: 5px; overflow: hidden; margin-bottom: 14px; border: 1px solid #1e293b;">
                    <div style="width: <?= min(100, $l['realization_pct']) ?>%; height: 100%; background: <?= $l['realization_pct'] >= 80 ? '#10b981' : ($l['realization_pct'] >= 50 ? '#f59e0b' : '#ef4444') ?>; border-radius: 5px; transition: width 0.4s ease;"></div>
                </div>

                <!-- 4 MINI OEE TILES (A / P / Q / OEE) -->
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin-bottom: 12px;">
                    <div class="andon-metric-box">
                        <div style="font-size: 9.5px; font-weight: 700; color: #0284c7; text-transform: uppercase;">AVAIL</div>
                        <div style="font-size: 14px; font-weight: 900; color: #38bdf8;">%<?= round($l['availability_pct']) ?></div>
                    </div>
                    <div class="andon-metric-box">
                        <div style="font-size: 9.5px; font-weight: 700; color: #d97706; text-transform: uppercase;">PERF</div>
                        <div style="font-size: 14px; font-weight: 900; color: #fbbf24;">%<?= round($l['performance_pct']) ?></div>
                    </div>
                    <div class="andon-metric-box">
                        <div style="font-size: 9.5px; font-weight: 700; color: #16a34a; text-transform: uppercase;">QUAL</div>
                        <div style="font-size: 14px; font-weight: 900; color: #4ade80;">%<?= round($l['quality_pct']) ?></div>
                    </div>
                    <div class="andon-metric-box" style="background: rgba(99, 102, 241, 0.15); border-color: #4f46e5;">
                        <div style="font-size: 9.5px; font-weight: 800; color: #818cf8; text-transform: uppercase;">OEE</div>
                        <div style="font-size: 15px; font-weight: 900; color: #c7d2fe;">%<?= number_format($l['oee_pct'], 1, ',', '.') ?></div>
                    </div>
                </div>

                <!-- LINE FOOTER: PACE, CYCLE & LAST PRODUCED -->
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11.5px; color: #94a3b8; border-top: 1px solid #1e293b; padding-top: 10px;">
                    <div>
                        <span>İdeal: <b style="color: #cbd5e1;"><?= $l['ideal_cycle_seconds'] ?> sn</b></span> &bull; 
                        <span>Son 1 Saat: <b style="color: #38bdf8;"><?= $l['last_hour_pace'] ?> pnl/s</b></span>
                    </div>
                    <div>
                        <?php if ($l['last_produced_at']): ?>
                            <span>Son Üretim: <b style="color: #34d399;"><?= date('H:i:s', strtotime($l['last_produced_at'])) ?></b></span>
                        <?php else: ?>
                            <span style="color: #64748b;">Bu vardiya üretim yok</span>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- ANDON TELEMETRY JAVASCRIPT & WEB AUDIO API -->
<script>
let audioAlarmEnabled = false;
let audioCtx = null;
let lastActiveFaultCount = <?= (int)$data['factory_summary']['active_downtimes_count'] ?>;

// 1. Web Audio Alarm Synthesizer (No external mp3 required)
function playBeep(freq = 880, duration = 0.3) {
    if (!audioAlarmEnabled) return;
    try {
        if (!audioCtx) {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(freq, audioCtx.currentTime);
        gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + duration);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + duration);
    } catch (e) {
        console.warn("Audio Context error:", e);
    }
}

function toggleAudioAlarm() {
    audioAlarmEnabled = !audioAlarmEnabled;
    const btn = document.getElementById('btn-audio-toggle');
    const icon = document.getElementById('audio-icon');
    const label = document.getElementById('audio-label');

    if (audioAlarmEnabled) {
        icon.textContent = '🔊';
        label.textContent = 'Ses Açık';
        btn.style.background = '#065f46';
        btn.style.borderColor = '#10b981';
        btn.style.color = '#ffffff';
        // Test beep on activate
        playBeep(660, 0.15);
    } else {
        icon.textContent = '🔇';
        label.textContent = 'Ses Kapalı';
        btn.style.background = '#1e293b';
        btn.style.borderColor = '#334155';
        btn.style.color = '#cbd5e1';
    }
}

// 2. Fullscreen Toggle
function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(err => {
            console.warn(`Fullscreen error: ${err.message}`);
        });
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        }
    }
}

// 3. Digital Clock Live Tick (Every 1 second)
setInterval(() => {
    const now = new Date();
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    const s = String(now.getSeconds()).padStart(2, '0');
    const clockEl = document.getElementById('andon-clock');
    if (clockEl) clockEl.textContent = `${h}:${m}:${s}`;

    // Update active downtime client-side stopwatch timers
    document.querySelectorAll('.dt-timer-display').forEach(el => {
        const startTs = parseInt(el.getAttribute('data-started') || '0', 10);
        if (startTs > 0) {
            const currentTs = Math.floor(Date.now() / 1000);
            const diffSec = Math.max(0, currentTs - startTs);
            const mm = String(Math.floor(diffSec / 60)).padStart(2, '0');
            const ss = String(diffSec % 60).padStart(2, '0');
            el.textContent = `${mm}:${ss}`;
        }
    });
}, 1000);

// 4. Live Telemetry Polling (Every 5 seconds)
async function fetchAndonTelemetry() {
    try {
        const res = await fetch('/stok-takip/public/andon/api/live', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        });
        if (!res.ok) return;
        const json = await res.json();
        if (!json.success || !json.data) return;

        const d = json.data;

        // Update Top KPIs
        const fs = d.factory_summary;
        if (document.getElementById('kpi-total-prod')) document.getElementById('kpi-total-prod').textContent = Number(fs.total_produced_qty).toLocaleString('tr-TR');
        if (document.getElementById('kpi-total-target')) document.getElementById('kpi-total-target').textContent = Number(fs.total_target_qty).toLocaleString('tr-TR');
        if (document.getElementById('kpi-realization-pct')) document.getElementById('kpi-realization-pct').textContent = `%${fs.realization_pct}`;
        if (document.getElementById('kpi-overall-oee')) document.getElementById('kpi-overall-oee').textContent = `%${Number(fs.overall_oee).toFixed(1).replace('.', ',')}`;
        if (document.getElementById('kpi-oee-a')) document.getElementById('kpi-oee-a').textContent = `%${Math.round(fs.overall_availability)}`;
        if (document.getElementById('kpi-oee-p')) document.getElementById('kpi-oee-p').textContent = `%${Math.round(fs.overall_performance)}`;
        if (document.getElementById('kpi-oee-q')) document.getElementById('kpi-oee-q').textContent = `%${Math.round(fs.overall_quality)}`;
        if (document.getElementById('kpi-active-dt-cnt')) document.getElementById('kpi-active-dt-cnt').textContent = `${fs.active_downtimes_count} Hat`;
        if (document.getElementById('kpi-unplan-dt-min')) document.getElementById('kpi-unplan-dt-min').textContent = `${fs.total_unplanned_dt_min} dk`;
        if (document.getElementById('kpi-active-alerts-cnt')) document.getElementById('kpi-active-alerts-cnt').textContent = `${fs.active_alerts_count} Olay`;

        // Shift info
        if (document.getElementById('kpi-shift-name')) document.getElementById('kpi-shift-name').textContent = d.shift.name;
        if (document.getElementById('kpi-shift-time-remain')) document.getElementById('kpi-shift-time-remain').textContent = `${d.shift.remaining_minutes} dk kaldı`;
        if (document.getElementById('kpi-shift-prog')) document.getElementById('kpi-shift-prog').textContent = `%${d.shift.progress_pct}`;

        // Check if new fault occurred -> trigger audio alarm
        if (fs.active_downtimes_count > lastActiveFaultCount) {
            playBeep(980, 0.4);
            setTimeout(() => playBeep(780, 0.4), 450);
        }
        lastActiveFaultCount = fs.active_downtimes_count;

        // Render Active Downtime Banner
        const bannerContainer = document.getElementById('andon-alert-banner');
        if (bannerContainer) {
            if (d.active_downtimes && d.active_downtimes.length > 0) {
                let bannerHtml = '';
                d.active_downtimes.forEach(ad => {
                    bannerHtml += `
                        <div style="background: #dc2626; animation: andonBannerPulse 2s infinite; color: #ffffff; border-radius: 12px; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; box-shadow: 0 4px 20px rgba(220, 38, 38, 0.5); margin-bottom: 10px;">
                            <div style="display: flex; align-items: center; gap: 14px;">
                                <div style="font-size: 28px;">🚨</div>
                                <div>
                                    <div style="font-size: 16px; font-weight: 900; letter-spacing: 0.02em;">
                                        AKTİF DURUŞ: ${ad.line_name} (${ad.line_code})
                                    </div>
                                    <div style="font-size: 13px; color: #fecaca; margin-top: 2px;">
                                        Neden: <strong>${ad.reason_name}</strong> &bull; Başlangıç: ${ad.started_at_time}
                                        ${ad.operator_note ? `&bull; <em>"${ad.operator_note}"</em>` : ''}
                                    </div>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #fee2e2;">DURUŞ SÜRESİ</div>
                                <div class="dt-timer-display" data-started="${Math.floor(new Date(ad.started_at).getTime() / 1000)}" style="font-size: 26px; font-weight: 900; font-family: monospace; color: #ffffff;">
                                    ${ad.formatted_duration}
                                </div>
                            </div>
                        </div>
                    `;
                });
                bannerContainer.innerHTML = bannerHtml;
                bannerContainer.style.display = 'block';
            } else {
                bannerContainer.innerHTML = '';
                bannerContainer.style.display = 'none';
            }
        }

        // Render Lines
        const grid = document.getElementById('andon-lines-grid');
        if (grid && d.lines) {
            let gridHtml = '';
            d.lines.forEach(l => {
                let statusClass = 'status-idle';
                let badgeClass = 'andon-badge-idle';
                let statusLabel = '⚪ BOŞTA (IDLE)';

                if (l.status === 'RUNNING') {
                    statusClass = 'status-running';
                    badgeClass = 'andon-badge-running';
                    statusLabel = '🟢 ÇALIŞIYOR';
                } else if (l.status === 'FAULT') {
                    statusClass = 'status-fault';
                    badgeClass = 'andon-badge-fault';
                    statusLabel = '🔴 ARIZA / DURUŞ';
                } else if (l.status === 'MAINTENANCE') {
                    statusClass = 'status-maintenance';
                    badgeClass = 'andon-badge-maintenance';
                    statusLabel = '🟡 BAKIM / MOLA';
                }

                let dtHtml = '';
                if (l.active_downtime) {
                    const startSec = Math.floor(new Date(l.active_downtime.started_at).getTime() / 1000);
                    dtHtml = `
                        <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-size: 11px; font-weight: 800; color: #f87171; text-transform: uppercase;">DURUŞ NEDENİ:</div>
                                <div style="font-size: 14px; font-weight: 800; color: #ffffff;">
                                    ${l.active_downtime.reason_name}
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 10px; color: #fca5a5;">SÜRE</div>
                                <div class="dt-timer-display" data-started="${startSec}" style="font-size: 18px; font-weight: 900; font-family: monospace; color: #ef4444;">
                                    ${l.active_downtime.formatted_duration}
                                </div>
                            </div>
                        </div>
                    `;
                }

                const lastProdStr = l.last_produced_at ? `Son Üretim: <b style="color: #34d399;">${l.last_produced_at.split(' ')[1] || l.last_produced_at}</b>` : `<span style="color: #64748b;">Bu vardiya üretim yok</span>`;
                const realColor = l.realization_pct >= 80 ? '#34d399' : (l.realization_pct >= 50 ? '#fbbf24' : '#f87171');
                const progBarColor = l.realization_pct >= 80 ? '#10b981' : (l.realization_pct >= 50 ? '#f59e0b' : '#ef4444');

                gridHtml += `
                    <div class="andon-card ${statusClass}" id="line-card-${l.id}">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px;">
                            <div>
                                <div style="font-size: 18px; font-weight: 900; color: #ffffff; letter-spacing: -0.01em;">
                                    ${l.name}
                                </div>
                                <div style="font-size: 12px; color: #94a3b8; font-family: monospace; margin-top: 2px;">
                                    ${l.code} &bull; ${l.nominal_power_kw} kW
                                    ${l.active_work_order ? `&bull; <span style="color: #38bdf8;">WO: ${l.active_work_order}</span>` : ''}
                                </div>
                            </div>
                            <div>
                                <span class="andon-badge ${badgeClass}" id="line-badge-${l.id}">
                                    ${statusLabel}
                                </span>
                            </div>
                        </div>

                        ${dtHtml}

                        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 6px;">
                            <div>
                                <span style="font-size: 34px; font-weight: 900; color: #ffffff; font-family: monospace;">
                                    ${Number(l.produced_quantity).toLocaleString('tr-TR')}
                                </span>
                                <span style="font-size: 16px; color: #64748b; font-weight: 700;">
                                    / ${Number(l.shift_target).toLocaleString('tr-TR')} ADET
                                </span>
                            </div>
                            <div>
                                <span style="font-size: 26px; font-weight: 900; color: ${realColor};">
                                    %${Number(l.realization_pct).toFixed(1).replace('.', ',')}
                                </span>
                            </div>
                        </div>

                        <div style="width: 100%; height: 10px; background: #0d1322; border-radius: 5px; overflow: hidden; margin-bottom: 14px; border: 1px solid #1e293b;">
                            <div style="width: ${Math.min(100, l.realization_pct)}%; height: 100%; background: ${progBarColor}; border-radius: 5px; transition: width 0.4s ease;"></div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin-bottom: 12px;">
                            <div class="andon-metric-box">
                                <div style="font-size: 9.5px; font-weight: 700; color: #0284c7; text-transform: uppercase;">AVAIL</div>
                                <div style="font-size: 14px; font-weight: 900; color: #38bdf8;">%${Math.round(l.availability_pct)}</div>
                            </div>
                            <div class="andon-metric-box">
                                <div style="font-size: 9.5px; font-weight: 700; color: #d97706; text-transform: uppercase;">PERF</div>
                                <div style="font-size: 14px; font-weight: 900; color: #fbbf24;">%${Math.round(l.performance_pct)}</div>
                            </div>
                            <div class="andon-metric-box">
                                <div style="font-size: 9.5px; font-weight: 700; color: #16a34a; text-transform: uppercase;">QUAL</div>
                                <div style="font-size: 14px; font-weight: 900; color: #4ade80;">%${Math.round(l.quality_pct)}</div>
                            </div>
                            <div class="andon-metric-box" style="background: rgba(99, 102, 241, 0.15); border-color: #4f46e5;">
                                <div style="font-size: 9.5px; font-weight: 800; color: #818cf8; text-transform: uppercase;">OEE</div>
                                <div style="font-size: 15px; font-weight: 900; color: #c7d2fe;">%${Number(l.oee_pct).toFixed(1).replace('.', ',')}</div>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11.5px; color: #94a3b8; border-top: 1px solid #1e293b; padding-top: 10px;">
                            <div>
                                <span>İdeal: <b style="color: #cbd5e1;">${l.ideal_cycle_seconds} sn</b></span> &bull; 
                                <span>Son 1 Saat: <b style="color: #38bdf8;">${l.last_hour_pace} pnl/s</b></span>
                            </div>
                            <div>
                                ${lastProdStr}
                            </div>
                        </div>
                    </div>
                `;
            });
            grid.innerHTML = gridHtml;
        }
    } catch (err) {
        console.warn("Andon telemetri polling hatası:", err);
    }
}

// Start 5-second polling
setInterval(fetchAndonTelemetry, 5000);
</script>

<?php
if ($isKiosk) {
    echo '</body></html>';
}
?>

