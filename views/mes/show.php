<?php
$pageTitle = 'İş Emri Detayı: ' . ($workOrder['work_order_no'] ?? '');
$activePage = 'mes';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';

$st = Mes::classifyStatus($workOrder ?? []);

$planned = (float)($workOrder['planned_quantity'] ?? 0);
$produced = (float)($workOrder['produced_quantity'] ?? 0);
$remaining = max(0, $planned - $produced);
$progressPct = $planned > 0 ? round(($produced / $planned) * 100, 1) : 0;

$sim = $simStatus['simulation'] ?? ($telemetry['simulation'] ?? []);
$isSimActive = !empty($sim['is_active']);
$intervalSec = (int)($sim['interval_seconds'] ?? 180);
$remainingSec = (int)($sim['remaining_seconds'] ?? $intervalSec);
$speedHuman = $sim['interval_human'] ?? ($intervalSec >= 60 && $intervalSec % 60 === 0 ? ($intervalSec / 60) . ' dk / panel' : $intervalSec . ' sn / panel');
$totalEstimatedHuman = $sim['total_estimated_human'] ?? '-';
$lastRunAt = !empty($sim['last_run_at']) ? date('H:i:s', strtotime($sim['last_run_at'])) : '-';
$nextRunAt = !empty($sim['next_run_at']) ? date('H:i:s', strtotime($sim['next_run_at'])) : '-';
$estimatedCompletion = $sim['estimated_completion_human'] ?? '-';

$latestAction = $latestAction ?? ($simStatus['latest_action_summary'] ?? ($telemetry['latest_action_summary'] ?? [
    'level'    => 'INFO',
    'icon'     => 'ℹ',
    'title'    => 'Beklemede',
    'subtitle' => 'Henüz işlem gerçekleşmedi.',
    'time'     => '-',
    'bg'       => '#f8fafc',
    'color'    => '#475569',
    'border'   => '#e2e8f0'
]));
$formattedEvents = $formattedEvents ?? ($simStatus['recent_events_human'] ?? ($telemetry['recent_events_human'] ?? []));
$bomConsumption = $bomConsumption ?? ($simStatus['bom_consumption'] ?? ($telemetry['bom_consumption'] ?? []));
$bomItems = $bomConsumption['bom_items'] ?? [];
$bomSummary = $bomConsumption['summary'] ?? [];
$costSummary = $costSummary ?? ($bomConsumption['cost_summary'] ?? ($telemetry['cost_summary'] ?? []));
$unitCost = (float)($costSummary['unit_cost'] ?? 0.0);
$realizedCost = (float)($costSummary['realized_cost'] ?? 0.0);
$estimatedTotalCost = (float)($costSummary['estimated_total_cost'] ?? 0.0);
$remainingCost = (float)($costSummary['remaining_estimated_cost'] ?? 0.0);
$isCompleted = ($st['key'] === 'COMPLETED' || ($produced >= $planned && $planned > 0));
$isFailed = ($st['key'] === 'FAILED');
?>

<main class="main-content" style="max-width: 1320px; padding: 24px 32px;">

    <header style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 4px;">
                <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
                    <?= htmlspecialchars($workOrder['work_order_no'] ?? '') ?>
                </h1>
                <span id="badge-wo-status" style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; background: <?= $st['bg'] ?>; color: <?= $st['color'] ?>; border: 1px solid <?= $st['border'] ?>;">
                    <span id="badge-dot" style="width: 6px; height: 6px; border-radius: 50%; background: <?= $st['dot'] ?>;"></span>
                    <span id="badge-label"><?= $st['label'] ?></span>
                </span>
            </div>
            <p style="font-size: 13.5px; color: #64748b; margin: 0;">
                <strong style="color: #334155;"><?= htmlspecialchars($workOrder['product_name'] ?? '') ?></strong> 
                <span style="color: #94a3b8;">(<?= htmlspecialchars($workOrder['product_code'] ?? '') ?>)</span> &bull; 
                <span><?= htmlspecialchars($workOrder['line_name'] ?? '') ?></span> &bull; 
                <span>BOM: <?= htmlspecialchars($workOrder['recipe_code'] ?? '') ?></span>
            </p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="/stok-takip/public/mes/simulator?wo_id=<?= (int)($workOrder['id'] ?? 0) ?>" style="display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; font-size: 13px; font-weight: 600; background: #ffffff; color: #2563eb; border: 1px solid #bfdbfe; border-radius: 8px; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
                <span>⚡</span> MES Simülatörü
            </a>
            <a href="/stok-takip/public/mes" style="display: inline-flex; align-items: center; gap: 6px; padding: 9px 14px; font-size: 13px; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px; text-decoration: none;">
                &larr; İş Emirleri
            </a>
        </div>
    </header>

    <!-- Bildirim / Hata Kutusu -->
    <div id="live-alert-box" style="display: none; margin-bottom: 20px; padding: 12px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; line-height: 1.4;"></div>

    <!-- 2. CANLI ÜRETİM DURUMU VE METRİKLER (2 KOLON) -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 18px; margin-bottom: 20px;">
        
        <!-- Sol: Büyük Panel Sayacı, İlerleme Çubuğu, 3 KPI ve Zamanlama -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            
            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 6px;">
                <span style="font-size: 11.5px; font-weight: 700; color: #64748b; letter-spacing: 0.04em; text-transform: uppercase;">
                    ÜRETİM İLERLEMESİ
                </span>
                <span id="stat-pct" style="font-size: 22px; font-weight: 800; color: <?= $progressPct >= 100 ? '#15803d' : '#2563eb' ?>; font-variant-numeric: tabular-nums;">
                    %<?= number_format($progressPct, 1, ',', '.') ?>
                </span>
            </div>

            <!-- Büyük Panel Sayacı: 37 / 1.000 PANEL -->
            <div style="font-size: 32px; font-weight: 800; color: #0f172a; margin-bottom: 12px; letter-spacing: -0.02em;">
                <span id="stat-produced-hero" style="color: #16a34a;"><?= number_format($produced, 0, ',', '.') ?></span> 
                <span style="color: #cbd5e1; font-weight: 400; font-size: 26px;">/</span> 
                <span id="stat-planned-hero"><?= number_format($planned, 0, ',', '.') ?></span> 
                <span style="font-size: 16px; font-weight: 700; color: #64748b; margin-left: 4px;">PANEL</span>
            </div>

            <!-- İlerleme Çubuğu -->
            <div style="margin-bottom: 20px;">
                <div style="height: 12px; background: #f1f5f9; border-radius: 6px; overflow: hidden; border: 1px solid #e2e8f0;">
                    <div id="stat-prog-bar" style="width: <?= min(100, $progressPct) ?>%; height: 100%; background: <?= $progressPct >= 100 ? '#10b981' : '#2563eb' ?>; border-radius: 6px; transition: width 0.4s ease;"></div>
                </div>
            </div>

            <!-- 3 Temel Kart: ÜRETİLEN | HEDEF | KALAN -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; padding-top: 16px; border-top: 1px solid #f1f5f9;">
                
                <!-- 1. Üretilen -->
                <div style="padding: 12px 14px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;">
                    <span style="font-size: 11px; color: #166534; font-weight: 700; letter-spacing: 0.04em;">🟢 ÜRETİLEN</span>
                    <div id="stat-produced" style="font-size: 22px; font-weight: 800; color: #15803d; margin-top: 2px;">
                        <?= number_format($produced, 0, ',', '.') ?> <small style="font-size: 11px; font-weight: 700;">PANEL</small>
                    </div>
                    <span style="font-size: 10.5px; color: #166534;">Başarıyla tamamlanan</span>
                </div>

                <!-- 2. Hedef -->
                <div style="padding: 12px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                    <span style="font-size: 11px; color: #475569; font-weight: 700; letter-spacing: 0.04em;">🎯 HEDEF</span>
                    <div id="stat-planned" style="font-size: 22px; font-weight: 800; color: #0f172a; margin-top: 2px;">
                        <?= number_format($planned, 0, ',', '.') ?> <small style="font-size: 11px; font-weight: 700;">PANEL</small>
                    </div>
                    <span style="font-size: 10.5px; color: #64748b;">Planlanan toplam</span>
                </div>

                <!-- 3. Kalan -->
                <div style="padding: 12px 14px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px;">
                    <span style="font-size: 11px; color: #92400e; font-weight: 700; letter-spacing: 0.04em;">🟡 KALAN</span>
                    <div id="stat-remaining" style="font-size: 22px; font-weight: 800; color: #b45309; margin-top: 2px;">
                        <?= number_format($remaining, 0, ',', '.') ?> <small style="font-size: 11px; font-weight: 700;">PANEL</small>
                    </div>
                    <span style="font-size: 10.5px; color: #92400e;">Üretilecek miktar</span>
                </div>

            </div>

            <!-- Zaman Bilgisi & Telemetri Çubuğu -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px; padding: 12px 14px; background: #f8fafc; border-radius: 8px; border: 1px solid #edf2f7; font-size: 12px; margin-top: 14px;">
                <div>
                    <span style="color: #64748b; font-size: 10.5px; display: block; font-weight: 600;">ÜRETİM HIZI:</span>
                    <strong id="info-speed"><?= htmlspecialchars($speedHuman) ?></strong>
                </div>
                <div>
                    <span style="color: #64748b; font-size: 10.5px; display: block; font-weight: 600;">TOPLAM SÜRE:</span>
                    <strong id="info-total-duration"><?= htmlspecialchars($totalEstimatedHuman) ?></strong>
                </div>
                <div>
                    <span style="color: #64748b; font-size: 10.5px; display: block; font-weight: 600;">SON ÜRETİM:</span>
                    <strong id="info-last-run"><?= $lastRunAt ?></strong>
                </div>
                <div>
                    <span style="color: #64748b; font-size: 10.5px; display: block; font-weight: 600;">TAHMİNİ BİTİŞ:</span>
                    <strong id="info-est-end"><?= htmlspecialchars($estimatedCompletion) ?></strong>
                </div>
            </div>

        </div>

        <!-- Sağ: Canlı Durum & Kontrol Paneli -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <h3 style="font-size: 14px; font-weight: 700; color: #0f172a; margin: 0 0 12px 0;">
                    ⏱️ Üretim Kontrolü &amp; Geri Sayım
                </h3>

                <!-- Geri Sayım Kutusu -->
                <div style="padding: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; text-align: center; margin-bottom: 14px;">
                    <span id="live-state-title" style="font-size: 11px; font-weight: 700; color: #2563eb; text-transform: uppercase; letter-spacing: 0.04em; display: block;">
                        SONRAKİ PANEL GERİ SAYIMI
                    </span>
                    <div id="live-countdown" style="font-family: monospace; font-size: 32px; font-weight: 800; color: #0f172a; margin: 4px 0;">
                        <?= $isCompleted ? 'TAMAMLANDI' : '--:--' ?>
                    </div>
                    <span id="countdown-status" style="font-size: 11px; color: #64748b; font-weight: 600;">
                        <?= $isCompleted ? 'Hedefe ulaşıldı (' . (int)$planned . '/' . (int)$planned . ')' : ($isSimActive ? "Otomatik üretim aktif ({$intervalSec} sn)" : "Simülasyon duraklatıldı") ?>
                    </span>
                </div>

                <!-- Kontrol Butonları -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px;">
                    <button type="button" id="btn-start" onclick="handleStartSim()" style="padding: 9px 8px; font-size: 12px; font-weight: 700; background: #2563eb; color: #ffffff; border: 1px solid #1d4ed8; border-radius: 6px; cursor: pointer;" <?= ($isSimActive || $isCompleted) ? 'disabled' : '' ?>>
                        ▶️ BAŞLAT
                    </button>
                    <button type="button" id="btn-pause" onclick="handlePauseSim()" style="padding: 9px 8px; font-size: 12px; font-weight: 700; background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer;" <?= (!$isSimActive || $isCompleted) ? 'disabled' : '' ?>>
                        ⏸️ DURAKLAT
                    </button>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 10px;">
                    <button type="button" id="btn-resume" onclick="handleResumeSim()" style="padding: 9px 8px; font-size: 12px; font-weight: 700; background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer;" <?= ($isSimActive || $isCompleted) ? 'disabled' : '' ?>>
                        <?= $isFailed ? '↻ YENİDEN DENE' : '⏯️ DEVAM ET' ?>
                    </button>
                    <button type="button" id="btn-step" onclick="handleStepSim()" style="padding: 9px 8px; font-size: 12px; font-weight: 700; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; border-radius: 6px; cursor: pointer;" <?= $isCompleted ? 'disabled' : '' ?>>
                        ☀️ +1 PANEL
                    </button>
                </div>
            </div>

            <!-- Tesis & Hat Bilgisi -->
            <div style="padding-top: 12px; border-top: 1px solid #f1f5f9; font-size: 11.5px; color: #475569;">
                <div><strong>Hat:</strong> <?= htmlspecialchars($workOrder['line_name'] ?? '') ?> (<?= htmlspecialchars($workOrder['line_code'] ?? '') ?>)</div>
                <div style="margin-top: 2px;"><strong>Reçete:</strong> <?= htmlspecialchars($workOrder['recipe_name'] ?? '') ?></div>
            </div>
        </div>
    </div>

    <!-- 3. ÜRETİM MALİYETİ & FİNANSAL ANALİZ KARTI (AŞAMA 3.2) -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 22px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
            <div>
                <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <span>💰</span> Üretim Maliyeti &amp; Finansal Özet
                </h3>
                <p style="font-size: 12px; color: #64748b; margin: 3px 0 0 0;">
                    BOM Hammadde Maliyeti &bull; Anlık Maliyet Snapshot Güvencesi &bull; Birim &amp; Kümülatif Bütçe
                </p>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <span style="font-size: 11px; font-weight: 700; color: #4338ca; background: #e0e7ff; border: 1px solid #c7d2fe; padding: 4px 10px; border-radius: 6px;">
                    🔒 Maliyet Snapshot Aktif
                </span>
            </div>
        </div>

        <!-- 4 Maliyet Kartı: BİRİM PANEL | GERÇEKLEŞEN | TAHMİNİ TOPLAM | KALAN -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px;">
            <!-- 1. Birim Panel Maliyeti -->
            <div style="padding: 14px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                <span style="font-size: 11px; color: #475569; font-weight: 700; letter-spacing: 0.04em;">🏷️ PANEL MALİYETİ (BİRİM)</span>
                <div id="stat-unit-cost" style="font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 4px; font-family: monospace;">
                    <?= number_format($unitCost, 2, ',', '.') ?> <small style="font-size: 12px; font-weight: 700;">TL</small>
                </div>
                <span style="font-size: 10.5px; color: #64748b;">1 panel teorik BOM maliyeti</span>
            </div>

            <!-- 2. Gerçekleşen Maliyet -->
            <div style="padding: 14px 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;">
                <span style="font-size: 11px; color: #166534; font-weight: 700; letter-spacing: 0.04em;">🟢 GERÇEKLEŞEN MALİYET</span>
                <div id="stat-realized-cost" style="font-size: 20px; font-weight: 800; color: #15803d; margin-top: 4px; font-family: monospace;">
                    <?= number_format($realizedCost, 2, ',', '.') ?> <small style="font-size: 12px; font-weight: 700;">TL</small>
                </div>
                <span style="font-size: 10.5px; color: #166534;">Üretilen <?= number_format($produced, 0, ',', '.') ?> panel harcaması</span>
            </div>

            <!-- 3. Tahmini Toplam Maliyet -->
            <div style="padding: 14px 16px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px;">
                <span style="font-size: 11px; color: #1e40af; font-weight: 700; letter-spacing: 0.04em;">🎯 TAHMİNİ TOPLAM</span>
                <div id="stat-estimated-total-cost" style="font-size: 20px; font-weight: 800; color: #1d4ed8; margin-top: 4px; font-family: monospace;">
                    <?= number_format($estimatedTotalCost, 2, ',', '.') ?> <small style="font-size: 12px; font-weight: 700;">TL</small>
                </div>
                <span style="font-size: 10.5px; color: #1e40af;">Hedef <?= number_format($planned, 0, ',', '.') ?> panel bütçesi</span>
            </div>

            <!-- 4. Kalan Tahmini Maliyet -->
            <div style="padding: 14px 16px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px;">
                <span style="font-size: 11px; color: #92400e; font-weight: 700; letter-spacing: 0.04em;">🟡 KALAN TAHMİNİ MALİYET</span>
                <div id="stat-remaining-cost" style="font-size: 20px; font-weight: 800; color: #b45309; margin-top: 4px; font-family: monospace;">
                    <?= number_format($remainingCost, 2, ',', '.') ?> <small style="font-size: 12px; font-weight: 700;">TL</small>
                </div>
                <span style="font-size: 10.5px; color: #92400e;">Kalan <?= number_format($remaining, 0, ',', '.') ?> panel için gereken</span>
            </div>
        </div>
    </div>

    <!-- 4. BOM & MALZEME TÜKETİMİ BÖLÜMÜ -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 22px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
            <div>
                <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <span>📦</span> BOM Tüketim &amp; Maliyet Analizi
                </h3>
                <p style="font-size: 12px; color: #64748b; margin: 3px 0 0 0;">
                    Reçete: <strong style="color: #334155;"><?= htmlspecialchars($workOrder['recipe_name'] ?? '') ?></strong> (<?= htmlspecialchars($workOrder['recipe_code'] ?? '') ?>) &bull; Kalem Bazlı Birim Fiyat, Tüketim ve Maliyet Payı
                </p>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <span id="bom-sufficiency-badge" style="font-size: 11.5px; font-weight: 700; color: <?= !empty($bomSummary['all_materials_sufficient']) ? '#166534' : '#991b1b' ?>; background: <?= !empty($bomSummary['all_materials_sufficient']) ? '#f0fdf4' : '#fef2f2' ?>; border: 1px solid <?= !empty($bomSummary['all_materials_sufficient']) ? '#bbf7d0' : '#fecaca' ?>; padding: 4px 10px; border-radius: 6px;">
                    <?= !empty($bomSummary['all_materials_sufficient']) ? '✓ Tüm Hammaddeler Yeterli' : '⚠ Hammadde Eksikliği Var' ?>
                </span>
            </div>
        </div>

        <!-- BOM Tablosu -->
        <div style="overflow-x: auto; border: 1px solid #f1f5f9; border-radius: 8px;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">
                        <th style="padding: 10px 14px;">MALZEME</th>
                        <th style="padding: 10px 14px; text-align: right;">PANEL BAŞINA</th>
                        <th style="padding: 10px 14px; text-align: right;">BİRİM FİYAT</th>
                        <th style="padding: 10px 14px; text-align: right;">PANEL MALİYETİ</th>
                        <th style="padding: 10px 14px; text-align: right;">TÜKETİLEN</th>
                        <th style="padding: 10px 14px; text-align: right;">TOPLAM HARCAMA</th>
                        <th style="padding: 10px 14px; text-align: right;">MEVCUT STOK</th>
                        <th style="padding: 10px 14px; text-align: right;">PAY (%)</th>
                        <th style="padding: 10px 14px; text-align: center;">DURUM</th>
                    </tr>
                </thead>
                <tbody id="bom-consumption-tbody">
                    <?php if (empty($bomItems)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 24px; color: #64748b; font-size: 13px;">
                                Reçeteye ait hammadde kalemi bulunamadı.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bomItems as $bItem): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 12px 14px;">
                                    <div style="font-weight: 700; color: #0f172a; font-size: 13px;">
                                        <?= htmlspecialchars($bItem['material_name']) ?>
                                    </div>
                                    <span style="font-size: 11px; color: #64748b; font-family: monospace;">
                                        <?= htmlspecialchars($bItem['material_code']) ?>
                                    </span>
                                </td>
                                <td style="padding: 12px 14px; text-align: right; font-weight: 600; color: #334155; font-family: monospace;">
                                    <?= number_format($bItem['effective_unit_qty'], 2, ',', '.') ?> <?= htmlspecialchars($bItem['unit_symbol']) ?>
                                </td>
                                <td style="padding: 12px 14px; text-align: right; font-weight: 600; color: #64748b; font-family: monospace;">
                                    <?= number_format($bItem['unit_price'] ?? 0, 2, ',', '.') ?> TL
                                </td>
                                <td style="padding: 12px 14px; text-align: right; font-weight: 700; color: #0f172a; font-family: monospace;">
                                    <?= number_format($bItem['unit_cost_share'] ?? 0, 2, ',', '.') ?> TL
                                </td>
                                <td style="padding: 12px 14px; text-align: right; font-weight: 700; color: #dc2626; font-family: monospace;">
                                    -<?= number_format($bItem['actual_consumed'], 2, ',', '.') ?> <?= htmlspecialchars($bItem['unit_symbol']) ?>
                                </td>
                                <td style="padding: 12px 14px; text-align: right; font-weight: 700; color: #166534; font-family: monospace;">
                                    <?= number_format($bItem['actual_consumed_cost'] ?? 0, 2, ',', '.') ?> TL
                                </td>
                                <td style="padding: 12px 14px; text-align: right; font-weight: 700; color: <?= $bItem['is_sufficient'] ? '#16a34a' : '#dc2626' ?>; font-family: monospace;">
                                    <?= number_format($bItem['current_stock'], 2, ',', '.') ?> <?= htmlspecialchars($bItem['unit_symbol']) ?>
                                </td>
                                <td style="padding: 12px 14px; text-align: right; font-weight: 600; color: #4338ca; font-family: monospace;">
                                    %<?= number_format($bItem['cost_share_pct'] ?? 0, 1, ',', '.') ?>
                                </td>
                                <td style="padding: 12px 14px; text-align: center;">
                                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; background: <?= $bItem['status_badge']['bg'] ?>; color: <?= $bItem['status_badge']['color'] ?>; border: 1px solid <?= $bItem['status_badge']['border'] ?>;">
                                        <?= $bItem['status_badge']['icon'] ?> <?= htmlspecialchars($bItem['status_label']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 4. KULLANICI DOSTU EVENT GEÇMİŞİ & SON İŞLEM ÖZETİ -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        
        <!-- Son İşlem Özeti Kartı -->
        <div id="hero-last-action-box" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 18px; background: <?= $latestAction['bg'] ?>; border: 1px solid <?= $latestAction['border'] ?>; border-radius: 8px; margin-bottom: 18px; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <span id="hero-last-action-icon" style="font-size: 20px;"><?= $latestAction['icon'] ?></span>
                <div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <strong id="hero-last-action-title" style="font-size: 14px; color: <?= $latestAction['color'] ?>; font-weight: 700;">
                            <?= htmlspecialchars($latestAction['title']) ?>
                        </strong>
                        <span id="hero-last-action-time" style="font-size: 11.5px; font-family: monospace; color: <?= $latestAction['color'] ?>; opacity: 0.85;">
                            <?= htmlspecialchars($latestAction['time']) ?>
                        </span>
                    </div>
                    <div id="hero-last-action-details" style="font-size: 12px; color: <?= $latestAction['color'] ?>; opacity: 0.9; margin-top: 2px;">
                        <?= htmlspecialchars($latestAction['subtitle']) ?>
                    </div>
                </div>
            </div>
            <span style="font-size: 11px; font-weight: 700; color: <?= $latestAction['color'] ?>; text-transform: uppercase; letter-spacing: 0.04em; background: rgba(255,255,255,0.6); padding: 3px 8px; border-radius: 6px;">
                SON İŞLEM
            </span>
        </div>

        <!-- Başlık & Event Filtreleri -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px;">
            <h3 style="font-size: 14px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 6px;">
                <span>📡</span> Canlı Üretim Geçmişi
            </h3>

            <!-- Filtre Butonları -->
            <div style="display: flex; gap: 6px; align-items: center;" id="event-filters">
                <button type="button" onclick="setEventFilter('all')" id="filter-btn-all" class="event-filter-btn" style="padding: 4px 10px; font-size: 11.5px; font-weight: 700; border-radius: 6px; border: 1px solid #cbd5e1; background: #0f172a; color: #ffffff; cursor: pointer;">
                    Tümü
                </button>
                <button type="button" onclick="setEventFilter('success')" id="filter-btn-success" class="event-filter-btn" style="padding: 4px 10px; font-size: 11.5px; font-weight: 700; border-radius: 6px; border: 1px solid #e2e8f0; background: #ffffff; color: #166534; cursor: pointer;">
                    🟢 Başarılı
                </button>
                <button type="button" onclick="setEventFilter('warning')" id="filter-btn-warning" class="event-filter-btn" style="padding: 4px 10px; font-size: 11.5px; font-weight: 700; border-radius: 6px; border: 1px solid #e2e8f0; background: #ffffff; color: #92400e; cursor: pointer;">
                    🟡 Uyarı
                </button>
                <button type="button" onclick="setEventFilter('error')" id="filter-btn-error" class="event-filter-btn" style="padding: 4px 10px; font-size: 11.5px; font-weight: 700; border-radius: 6px; border: 1px solid #e2e8f0; background: #ffffff; color: #991b1b; cursor: pointer;">
                    🔴 Hata
                </button>
            </div>
        </div>

        <!-- Sadeleştirilmiş Olay Listesi -->
        <div style="border: 1px solid #edf2f7; border-radius: 8px; max-height: 420px; overflow-y: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; position: sticky; top: 0; z-index: 2;">
                        <th style="padding: 10px 14px; width: 90px;">ZAMAN</th>
                        <th style="padding: 10px 14px;">İŞLEM &amp; AÇIKLAMA</th>
                        <th style="padding: 10px 14px; text-align: right; width: 110px;">MİKTAR</th>
                        <th style="padding: 10px 14px; text-align: center; width: 120px;">DURUM</th>
                    </tr>
                </thead>
                <tbody id="live-events-tbody">
                    <?php if (empty($formattedEvents)): ?>
                        <tr id="empty-events-row">
                            <td colspan="4" style="text-align: center; padding: 28px; color: #64748b; font-size: 13px;">
                                Bu iş emri için henüz üretim olayı kaydedilmemiş. Simülasyonu başlatın.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($formattedEvents as $ev): ?>
                            <tr class="event-row" data-category="<?= $ev['category'] ?>" style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 12px 14px; color: #64748b; font-size: 12px; font-family: monospace;">
                                    <?= $ev['time_human'] ?>
                                </td>
                                <td style="padding: 12px 14px;">
                                    <div style="font-weight: 700; color: #0f172a; font-size: 13px;">
                                        <?= htmlspecialchars($ev['title']) ?>
                                    </div>
                                    <div style="font-size: 11.5px; color: <?= $ev['level'] === 'SUCCESS' ? '#166534' : '#64748b' ?>; margin-top: 2px;">
                                        <?= htmlspecialchars($ev['details']) ?>
                                    </div>
                                </td>
                                <td style="padding: 12px 14px; text-align: right; font-weight: 700; color: <?= $ev['level'] === 'SUCCESS' ? '#16a34a' : '#64748b' ?>;">
                                    <?= htmlspecialchars($ev['quantity_badge']) ?>
                                </td>
                                <td style="padding: 12px 14px; text-align: center;">
                                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; background: <?= $ev['status_badge']['bg'] ?>; color: <?= $ev['status_badge']['color'] ?>; border: 1px solid <?= $ev['status_badge']['border'] ?>;">
                                        <?= htmlspecialchars($ev['status_label']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
    </div>

    <!-- Panel & Event Tüketim Detay Modalı -->
    <div id="event-detail-modal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
        <div style="background: #ffffff; border-radius: 12px; max-width: 560px; width: 92%; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); border: 1px solid #e2e8f0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                <div>
                    <h3 id="modal-title" style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0;">Panel Üretim &amp; Tüketim Detayı</h3>
                    <div id="modal-subtitle" style="font-size: 12px; color: #64748b; margin-top: 2px;">Event &amp; Stok Hareketi İzlenebilirliği</div>
                </div>
                <button type="button" onclick="closeEventDetailModal()" style="background: none; border: none; font-size: 24px; line-height: 1; color: #94a3b8; cursor: pointer; padding: 4px;">&times;</button>
            </div>
            <div id="modal-body" style="font-size: 13px; color: #334155;">
                <div style="text-align: center; padding: 20px; color: #64748b;">Yükleniyor...</div>
            </div>
            <div style="margin-top: 18px; text-align: right; border-top: 1px solid #f1f5f9; padding-top: 14px;">
                <button type="button" onclick="closeEventDetailModal()" style="padding: 8px 18px; font-size: 12.5px; font-weight: 700; background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer;">Kapat</button>
            </div>
        </div>
    </div>

</main>

<script>
const currentWoId = <?= (int)$workOrder['id'] ?>;
let isSimulationActive = <?= $isSimActive ? 'true' : 'false' ?>;
let currentRemainingSeconds = <?= $remainingSec ?>;
let currentIntervalSeconds = <?= $intervalSec ?>;
let clientTimer = null;
let currentEventFilter = 'all';
let currentEventsData = <?= json_encode($formattedEvents ?? [], JSON_UNESCAPED_UNICODE) ?>;
let lastNextRunAt = <?= json_encode($sim['next_run_at'] ?? null) ?>;
let lastProducedQty = <?= (float)($workOrder['produced_quantity'] ?? 0) ?>;
let isFirstLoad = true;

function setEventFilter(category) {
    currentEventFilter = category;
    
    const btnIds = ['all', 'success', 'warning', 'error'];
    btnIds.forEach(id => {
        const btn = document.getElementById('filter-btn-' + id);
        if (!btn) return;
        if (id === category) {
            btn.style.background = '#0f172a';
            btn.style.color = '#ffffff';
            btn.style.borderColor = '#0f172a';
        } else {
            btn.style.background = '#ffffff';
            btn.style.color = id === 'success' ? '#166534' : (id === 'warning' ? '#92400e' : (id === 'error' ? '#991b1b' : '#334155'));
            btn.style.borderColor = '#e2e8f0';
        }
    });

    renderLiveEvents(currentEventsData);
}

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

function updateUI(data) {
    if (!data || !data.work_order) return;
    const wo = data.work_order;
    const sim = data.simulation || {};
    const events = data.recent_events || [];

    const planned = Number(wo.planned_quantity);
    const produced = Number(wo.produced_quantity);
    const remaining = Number(wo.remaining_quantity);
    const pct = wo.progress_pct;

    document.getElementById('stat-produced-hero').innerText = produced.toLocaleString('tr-TR');
    document.getElementById('stat-planned-hero').innerText = planned.toLocaleString('tr-TR');
    document.getElementById('stat-produced').innerHTML = produced.toLocaleString('tr-TR') + ' <small style="font-size:11px; font-weight:700;">PANEL</small>';
    document.getElementById('stat-planned').innerHTML = planned.toLocaleString('tr-TR') + ' <small style="font-size:11px; font-weight:700;">PANEL</small>';
    document.getElementById('stat-remaining').innerHTML = remaining.toLocaleString('tr-TR') + ' <small style="font-size:11px; font-weight:700;">PANEL</small>';
    document.getElementById('stat-pct').innerText = '%' + pct.toLocaleString('tr-TR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

    // Update Cost Counters
    const cost = data.cost_summary || (data.bom_consumption ? data.bom_consumption.cost_summary : null);
    if (cost) {
        const uCostEl = document.getElementById('stat-unit-cost');
        const rCostEl = document.getElementById('stat-realized-cost');
        const tCostEl = document.getElementById('stat-estimated-total-cost');
        const remCostEl = document.getElementById('stat-remaining-cost');
        if (uCostEl) uCostEl.innerHTML = Number(cost.unit_cost || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' <small style="font-size:12px; font-weight:700;">TL</small>';
        if (rCostEl) rCostEl.innerHTML = Number(cost.realized_cost || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' <small style="font-size:12px; font-weight:700;">TL</small>';
        if (tCostEl) tCostEl.innerHTML = Number(cost.estimated_total_cost || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' <small style="font-size:12px; font-weight:700;">TL</small>';
        if (remCostEl) remCostEl.innerHTML = Number(cost.remaining_estimated_cost || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' <small style="font-size:12px; font-weight:700;">TL</small>';
    }

    const progBar = document.getElementById('stat-prog-bar');
    progBar.style.width = Math.min(100, pct) + '%';
    progBar.style.background = pct >= 100 ? '#10b981' : '#2563eb';

    document.getElementById('info-speed').innerText = sim.interval_human || (sim.interval_seconds + ' sn / panel');
    if (document.getElementById('info-total-duration')) {
        document.getElementById('info-total-duration').innerText = sim.total_estimated_human || '-';
    }
    document.getElementById('info-last-run').innerText = sim.last_run_at ? new Date(sim.last_run_at).toLocaleTimeString('tr-TR') : '-';
    document.getElementById('info-est-end').innerText = sim.estimated_completion_human || '-';

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

    if (wo.status === 'COMPLETED' || produced >= planned) {
        currentRemainingSeconds = 0;
        lastNextRunAt = null;
        lastProducedQty = produced;
    } else if (!isSimulationActive) {
        currentRemainingSeconds = (serverRemaining !== null) ? Math.max(0, serverRemaining) : currentIntervalSeconds;
        lastNextRunAt = newNextRunAt;
        lastProducedQty = produced;
    } else {
        // ACTIVE SIMULATION: Reset ONLY when a new cycle starts, new panel produced, state changed, or interval changed.
        if (isFirstLoad || isNewCycle || isNewPanelProduced || isStateChanged || isIntervalChanged) {
            currentRemainingSeconds = (serverRemaining !== null) ? Math.max(0, serverRemaining) : currentIntervalSeconds;
            lastNextRunAt = newNextRunAt;
            lastProducedQty = produced;
        }
    }
    isFirstLoad = false;

    const badgeLabel = document.getElementById('badge-label');
    const badgeDot = document.getElementById('badge-dot');
    const badgeBox = document.getElementById('badge-wo-status');
    const btnStart = document.getElementById('btn-start');
    const btnPause = document.getElementById('btn-pause');
    const btnResume = document.getElementById('btn-resume');
    const btnStep = document.getElementById('btn-step');
    const cdDisplay = document.getElementById('live-countdown');
    const cdStatus = document.getElementById('countdown-status');
    
    if (wo.status === 'COMPLETED' || produced >= planned) {
        badgeBox.style.background = '#f8fafc';
        badgeBox.style.color = '#334155';
        badgeBox.style.borderColor = '#cbd5e1';
        badgeDot.style.background = '#64748b';
        badgeLabel.innerText = 'Tamamlandı';
        btnStart.disabled = true;
        btnPause.disabled = true;
        if (btnResume) { btnResume.disabled = true; btnResume.innerText = '⏯️ DEVAM ET'; btnResume.onclick = handleResumeSim; }
        btnStep.disabled = true;
        cdDisplay.innerText = 'TAMAMLANDI';
        cdStatus.innerText = 'Hedefe ulaşıldı (' + planned + '/' + planned + ')';
    } else if (wo.status === 'FAILED') {
        badgeBox.style.background = '#fef2f2';
        badgeBox.style.color = '#991b1b';
        badgeBox.style.borderColor = '#fecaca';
        badgeDot.style.background = '#ef4444';
        badgeLabel.innerText = 'Hata / Durduruldu';
        btnStart.disabled = false;
        btnPause.disabled = true;
        if (btnResume) { btnResume.disabled = false; btnResume.innerText = '↻ YENİDEN DENE'; btnResume.onclick = handleRetrySim; }
        btnStep.disabled = false;
        cdDisplay.innerText = 'HATA';
        cdStatus.innerText = sim.last_error || 'Üretim durduruldu';
    } else if (isSimulationActive) {
        if (btnResume) { btnResume.innerText = '⏯️ DEVAM ET'; btnResume.onclick = handleResumeSim; }
        if (sim.retry_count > 0) {
            badgeBox.style.background = '#fffbeb';
            badgeBox.style.color = '#92400e';
            badgeBox.style.borderColor = '#fde68a';
            badgeDot.style.background = '#f59e0b';
            badgeLabel.innerText = 'Tekrar Deneniyor (' + sim.retry_count + '/3)';
            btnStart.disabled = true; btnPause.disabled = false; btnResume.disabled = true; btnStep.disabled = false;
            cdDisplay.innerText = formatCountdown(currentRemainingSeconds);
            cdStatus.innerText = 'Geçici sistem hatası, tekrar deneniyor (' + sim.retry_count + '/3)';
        } else {
            badgeBox.style.background = '#f0fdf4';
            badgeBox.style.color = '#166534';
            badgeBox.style.borderColor = '#bbf7d0';
            badgeDot.style.background = '#22c55e';
            badgeLabel.innerText = 'Üretimde';
            btnStart.disabled = true; btnPause.disabled = false; btnResume.disabled = true; btnStep.disabled = false;
            cdDisplay.innerText = formatCountdown(currentRemainingSeconds);
            if (sim.last_error) {
                cdStatus.innerText = '⚠️ Son panelde hata: ' + sim.last_error + ' (Üretim kesintisiz devam ediyor)';
            } else {
                cdStatus.innerText = 'Otomatik üretim aktif (' + currentIntervalSeconds + ' sn)';
            }
        }
    } else if (wo.status === 'READY') {
        if (btnResume) { btnResume.innerText = '⏯️ DEVAM ET'; btnResume.onclick = handleResumeSim; }
        badgeBox.style.background = '#faf5ff';
        badgeBox.style.color = '#6b21a8';
        badgeBox.style.borderColor = '#e9d5ff';
        badgeDot.style.background = '#a855f7';
        badgeLabel.innerText = 'Hazır / Kuyrukta';
        btnStart.disabled = false; btnPause.disabled = true; btnResume.disabled = false; btnStep.disabled = false;
        cdDisplay.innerText = formatCountdown(currentRemainingSeconds);
        cdStatus.innerText = 'Kuyrukta hazır bekliyor (' + currentIntervalSeconds + ' sn)';
    } else if (wo.status === 'PAUSED') {
        if (btnResume) { btnResume.innerText = '⏯️ DEVAM ET'; btnResume.onclick = handleResumeSim; }
        badgeBox.style.background = '#fffbeb';
        badgeBox.style.color = '#92400e';
        badgeBox.style.borderColor = '#fde68a';
        badgeDot.style.background = '#f59e0b';
        badgeLabel.innerText = 'Duraklatıldı';
        btnStart.disabled = false; btnPause.disabled = true; btnResume.disabled = false; btnStep.disabled = false;
        cdDisplay.innerText = formatCountdown(currentRemainingSeconds);
        cdStatus.innerText = 'Simülasyon duraklatıldı (Kalan: ' + currentRemainingSeconds + ' sn)';
    } else {
        if (btnResume) { btnResume.innerText = '⏯️ DEVAM ET'; btnResume.onclick = handleResumeSim; }
        badgeBox.style.background = '#eff6ff';
        badgeBox.style.color = '#1e40af';
        badgeBox.style.borderColor = '#bfdbfe';
        badgeDot.style.background = '#3b82f6';
        badgeLabel.innerText = 'Planlandı';
        btnStart.disabled = false; btnPause.disabled = true; btnResume.disabled = false; btnStep.disabled = false;
        cdDisplay.innerText = formatCountdown(currentRemainingSeconds);
        cdStatus.innerText = 'İş emri planlandı (Başlatmaya hazır)';
    }

    // Update Latest Action Box
    if (data.latest_action_summary) {
        const la = data.latest_action_summary;
        const box = document.getElementById('hero-last-action-box');
        if (box) {
            box.style.background = la.bg;
            box.style.borderColor = la.border;
            const iconEl = document.getElementById('hero-last-action-icon');
            if (iconEl) iconEl.innerText = la.icon;
            const titleEl = document.getElementById('hero-last-action-title');
            if (titleEl) { titleEl.innerText = la.title; titleEl.style.color = la.color; }
            const timeEl = document.getElementById('hero-last-action-time');
            if (timeEl) { timeEl.innerText = la.time; timeEl.style.color = la.color; }
            const detEl = document.getElementById('hero-last-action-details');
            if (detEl) { detEl.innerText = la.subtitle; detEl.style.color = la.color; }
        }
    }

    if (data.recent_events_human) {
        currentEventsData = data.recent_events_human;
        renderLiveEvents(currentEventsData);
    }

    if (data.bom_consumption) {
        renderBomTable(data.bom_consumption);
    }
}

function renderBomTable(bomData) {
    if (!bomData) return;
    const items = bomData.bom_items || [];
    const summary = bomData.summary || {};
    const tbody = document.getElementById('bom-consumption-tbody');
    const badge = document.getElementById('bom-sufficiency-badge');

    if (badge) {
        if (summary.all_materials_sufficient) {
            badge.style.color = '#166534';
            badge.style.background = '#f0fdf4';
            badge.style.borderColor = '#bbf7d0';
            badge.innerText = '✓ Tüm Hammaddeler Yeterli';
        } else {
            badge.style.color = '#991b1b';
            badge.style.background = '#fef2f2';
            badge.style.borderColor = '#fecaca';
            badge.innerText = '⚠ Hammadde Eksikliği Var';
        }
    }

    if (!tbody) return;
    if (items.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" style="text-align: center; padding: 24px; color: #64748b; font-size: 13px;">Reçeteye ait hammadde kalemi bulunamadı.</td></tr>`;
        return;
    }

    let html = '';
    items.forEach(it => {
        const badge = it.status_badge || { bg: '#f0fdf4', color: '#166534', border: '#bbf7d0', icon: '✓' };
        const unit = it.unit_symbol || 'AD';
        const stockColor = it.is_sufficient ? '#16a34a' : '#dc2626';
        const unitPrice = Number(it.unit_price || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const unitCostShare = Number(it.unit_cost_share || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const actualCost = Number(it.actual_consumed_cost || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const costSharePct = Number(it.cost_share_pct || 0).toLocaleString('tr-TR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

        html += `
            <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 12px 14px;">
                    <div style="font-weight: 700; color: #0f172a; font-size: 13px;">${it.material_name}</div>
                    <span style="font-size: 11px; color: #64748b; font-family: monospace;">${it.material_code}</span>
                </td>
                <td style="padding: 12px 14px; text-align: right; font-weight: 600; color: #334155; font-family: monospace;">
                    ${Number(it.effective_unit_qty).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${unit}
                </td>
                <td style="padding: 12px 14px; text-align: right; font-weight: 600; color: #64748b; font-family: monospace;">
                    ${unitPrice} TL
                </td>
                <td style="padding: 12px 14px; text-align: right; font-weight: 700; color: #0f172a; font-family: monospace;">
                    ${unitCostShare} TL
                </td>
                <td style="padding: 12px 14px; text-align: right; font-weight: 700; color: #dc2626; font-family: monospace;">
                    -${Number(it.actual_consumed).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${unit}
                </td>
                <td style="padding: 12px 14px; text-align: right; font-weight: 700; color: #166534; font-family: monospace;">
                    ${actualCost} TL
                </td>
                <td style="padding: 12px 14px; text-align: right; font-weight: 700; color: ${stockColor}; font-family: monospace;">
                    ${Number(it.current_stock).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${unit}
                </td>
                <td style="padding: 12px 14px; text-align: right; font-weight: 600; color: #4338ca; font-family: monospace;">
                    %${costSharePct}
                </td>
                <td style="padding: 12px 14px; text-align: center;">
                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; background: ${badge.bg}; color: ${badge.color}; border: 1px solid ${badge.border};">
                        ${badge.icon} ${it.status_label}
                    </span>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function renderLiveEvents(events) {
    currentEventsData = events || [];
    const tbody = document.getElementById('live-events-tbody');
    if (!tbody) return;

    if (!events || events.length === 0) {
        tbody.innerHTML = `
            <tr id="empty-events-row">
                <td colspan="4" style="text-align: center; padding: 28px; color: #64748b; font-size: 13px;">
                    Bu iş emri için henüz üretim olayı kaydedilmemiş. Simülasyonu başlatın.
                </td>
            </tr>
        `;
        return;
    }

    const filtered = currentEventFilter === 'all' 
        ? events 
        : events.filter(e => e.category === currentEventFilter || (currentEventFilter === 'warning' && e.level === 'WARNING') || (currentEventFilter === 'error' && e.level === 'ERROR') || (currentEventFilter === 'success' && e.level === 'SUCCESS'));

    if (filtered.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" style="text-align: center; padding: 24px; color: #64748b; font-size: 12.5px;">
                    Bu filtreye uygun üretim kaydı bulunamadı.
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    filtered.forEach(ev => {
        const badge = ev.status_badge || { bg: '#f0fdf4', color: '#166534', border: '#bbf7d0' };
        const labelColor = ev.level === 'SUCCESS' ? '#166534' : (ev.level === 'ERROR' ? '#991b1b' : '#64748b');
        const qtyColor = ev.level === 'SUCCESS' ? '#16a34a' : '#64748b';

        let actionBtn = '';
        if (ev.level === 'SUCCESS' && ev.event_id) {
            actionBtn = `
                <div style="margin-top: 4px;">
                    <button type="button" onclick="showEventConsumptionDetails('${ev.event_id}')" style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; font-size: 11px; font-weight: 600; background: #f8fafc; color: #2563eb; border: 1px solid #bfdbfe; border-radius: 4px; cursor: pointer;">
                        🔍 Tüketim, Seri No &amp; Maliyet
                    </button>
                </div>
            `;
        }

        html += `
            <tr class="event-row" data-category="${ev.category || 'info'}" style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 12px 14px; color: #64748b; font-size: 12px; font-family: monospace;">
                    ${ev.time_human || '-'}
                </td>
                <td style="padding: 12px 14px;">
                    <div style="font-weight: 700; color: #0f172a; font-size: 13px;">
                        ${ev.title || 'Panel Üretimi'}
                    </div>
                    <div style="font-size: 11.5px; color: ${labelColor}; margin-top: 2px;">
                        ${ev.details || ''}
                    </div>
                    ${actionBtn}
                </td>
                <td style="padding: 12px 14px; text-align: right; font-weight: 700; color: ${qtyColor};">
                    ${ev.quantity_badge || '+1 Panel'}
                </td>
                <td style="padding: 12px 14px; text-align: center;">
                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; background: ${badge.bg}; color: ${badge.color}; border: 1px solid ${badge.border};">
                        ${ev.status_label || '✓ Başarılı'}
                    </span>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function showEventConsumptionDetails(eventId) {
    const modal = document.getElementById('event-detail-modal');
    const body = document.getElementById('modal-body');
    if (!modal || !body) return;

    modal.style.display = 'flex';
    body.innerHTML = '<div style="text-align:center; padding: 24px; color:#64748b;">⏳ Tüketim, izlenebilirlik ve snapshot maliyet verileri yükleniyor...</div>';

    fetch('/stok-takip/public/api/mes/event/consumption?event_id=' + encodeURIComponent(eventId))
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                body.innerHTML = `<div style="padding: 16px; background: #fef2f2; color: #991b1b; border-radius: 8px;">${res.message || 'Detaylar yüklenemedi.'}</div>`;
                return;
            }

            let serialsHtml = '';
            if (res.serial_numbers && res.serial_numbers.length > 0) {
                serialsHtml = res.serial_numbers.map(s => `<span style="font-family: monospace; font-weight: 700; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; padding: 3px 8px; border-radius: 6px; font-size: 12px;">🏷️ ${s}</span>`).join(' ');
            } else {
                serialsHtml = '<span style="color:#64748b; font-style: italic;">Seri No atanmamış / Beklemede</span>';
            }

            let itemsHtml = '';
            (res.consumed_items || []).forEach(it => {
                const itemPriceStr = it.formatted_unit_price ? ` &bull; Birim: ${it.formatted_unit_price}` : '';
                const itemTotalStr = it.formatted_total_price ? ` = ${it.formatted_total_price}` : '';
                itemsHtml += `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; border-bottom: 1px solid #f1f5f9;">
                        <div>
                            <strong style="color: #0f172a; font-size: 13px;">${it.material_name}</strong>
                            <span style="color: #64748b; font-size: 11px; font-family: monospace; margin-left: 4px;">(${it.material_code}${itemPriceStr})</span>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-family: monospace; font-weight: 700; color: #dc2626; font-size: 13px;">${it.formatted}</span>
                            ${it.formatted_total_price ? `<span style="font-family: monospace; font-weight: 600; color: #475569; font-size: 12px; margin-left: 6px;">(${it.formatted_total_price})</span>` : ''}
                        </div>
                    </div>
                `;
            });

            const unitCostFormatted = res.formatted_unit_cost || (Number(res.unit_cost || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' TL');
            const totalCostFormatted = res.formatted_total_cost || (Number(res.total_cost || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' TL');

            body.innerHTML = `
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Üretim Olayı (Event)</span>
                        <span style="font-family: monospace; font-size: 11.5px; color: #0f172a; font-weight: 700;">${res.event_id}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">İş Emri / Hat</span>
                        <span style="font-size: 12.5px; color: #334155;">${res.work_order_no} &bull; ${res.line_name}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Stok &amp; Maliyet Referansı</span>
                        <span style="font-family: monospace; font-size: 11.5px; color: #2563eb; font-weight: 700;">${res.stock_movement_ref || '-'}</span>
                    </div>
                </div>

                <!-- Snapshot Maliyet Kartı -->
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span style="font-size: 11px; font-weight: 700; color: #1e40af; text-transform: uppercase;">🔒 Üretim Maliyeti Snapshot</span>
                        <div style="font-size: 11.5px; color: #3b82f6;">Olay anındaki birim fiyatlarla hesaplanıp dondurulmuştur.</div>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-family: monospace; font-size: 16px; font-weight: 800; color: #1d4ed8;">${totalCostFormatted}</span>
                    </div>
                </div>

                <div style="margin-bottom: 14px;">
                    <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Üretilen Panel Seri Numarası</div>
                    <div>${serialsHtml}</div>
                </div>

                <div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 12px;">
                    <div style="background: #f8fafc; padding: 8px 12px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase;">
                        Tüketilen BOM Hammaddeleri &amp; Snapshot Maliyetleri
                    </div>
                    <div>${itemsHtml}</div>
                </div>

                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 12px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong style="color: #166534; font-size: 13px;">Mamul Girişi (Stoğa Eklendi)</strong>
                        <div style="font-size: 11.5px; color: #15803d;">${res.output_item ? res.output_item.product_name : ''}</div>
                    </div>
                    <span style="font-family: monospace; font-weight: 800; color: #16a34a; font-size: 14px;">${res.output_item ? res.output_item.formatted : '+1 AD'}</span>
                </div>
            `;
        })
        .catch(err => {
            console.error('Event detail error:', err);
            body.innerHTML = `<div style="padding: 16px; background: #fef2f2; color: #991b1b; border-radius: 8px;">Detaylar yüklenirken sunucu hatası oluştu.</div>`;
        });
}

function closeEventDetailModal() {
    const modal = document.getElementById('event-detail-modal');
    if (modal) modal.style.display = 'none';
}

const mesCsrfToken = '<?= CsrfService::generateToken() ?>';

function handleStartSim() {
    const btnStart = document.getElementById('btn-start');
    if (btnStart) {
        btnStart.disabled = true;
        btnStart.innerText = '▶️ BAŞLATILIYOR...';
    }

    const formData = new FormData();
    formData.append('work_order_id', currentWoId);
    formData.append('status', 'RUNNING');
    formData.append('csrf_token', mesCsrfToken);

    fetch('/stok-takip/public/mes/work-orders/status', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (data.telemetry) updateUI(data.telemetry);
            showLiveAlert('success', '✓ ' + (data.message || 'Üretim başlatıldı.'));
        } else {
            showLiveAlert('danger', '⚠ ' + (data.message || 'Üretim başlatılamadı.'));
            fetchShowTelemetry();
        }
    })
    .catch(err => {
        console.error('Start error:', err);
        showLiveAlert('danger', 'Sunucu bağlantı hatası.');
        fetchShowTelemetry();
    });
}

function handlePauseSim() {
    const btnPause = document.getElementById('btn-pause');
    if (btnPause) {
        btnPause.disabled = true;
        btnPause.innerText = '⏸️ DURAKLATILIYOR...';
    }

    const formData = new FormData();
    formData.append('work_order_id', currentWoId);
    formData.append('status', 'PAUSED');
    formData.append('csrf_token', mesCsrfToken);

    fetch('/stok-takip/public/mes/work-orders/status', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (data.telemetry) updateUI(data.telemetry);
            showLiveAlert('warning', '⏸️ ' + (data.message || 'Üretim duraklatıldı.'));
        } else {
            showLiveAlert('danger', '⚠ ' + (data.message || 'Duraklatma başarısız.'));
            fetchShowTelemetry();
        }
    })
    .catch(err => {
        console.error('Pause error:', err);
        showLiveAlert('danger', 'Sunucu bağlantı hatası.');
        fetchShowTelemetry();
    });
}

function handleResumeSim() {
    handleStartSim();
}

function handleRetrySim() {
    const formData = new FormData();
    formData.append('work_order_id', currentWoId);
    formData.append('status', 'READY');
    formData.append('csrf_token', mesCsrfToken);

    fetch('/stok-takip/public/mes/work-orders/status', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showLiveAlert('success', '✓ ' + (data.message || 'İş emri kuyruğa alındı.'));
            if (data.telemetry) updateUI(data.telemetry);
        } else {
            showLiveAlert('danger', '⚠ ' + (data.message || 'Yeniden deneme başarısız.'));
        }
    })
    .catch(err => {
        console.error('Retry error:', err);
        showLiveAlert('danger', 'Sunucu hatası.');
    });
}

function handleStepSim() {
    const btnStep = document.getElementById('btn-step');
    if (btnStep) {
        btnStep.disabled = true;
    }

    const formData = new FormData();
    formData.append('work_order_id', currentWoId);
    formData.append('force_step', '1');
    formData.append('csrf_token', mesCsrfToken);

    fetch('/stok-takip/public/mes/simulator/tick', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (btnStep) btnStep.disabled = false;
        if (data.telemetry) updateUI(data.telemetry);
        if (data.success && (data.status === 'PROCESSED' || data.status === 'RUNNING')) {
            showLiveAlert('success', '✓ ' + (data.message || '1 panel üretildi ve stoğa eklendi.'));
        } else if (data.action === 'PANEL_FAILED_CONTINUING') {
            showLiveAlert('warning', '⚠️ ' + (data.message || 'Panel üretiminde hata oluştu, ancak üretim devam ediyor.'));
        } else {
            showLiveAlert('danger', '⚠ ÜRETİM DURDURULDU. ' + (data.message || 'Üretim yapılamadı.'));
        }
    })
    .catch(err => {
        if (btnStep) btnStep.disabled = false;
        showLiveAlert('danger', 'Manuel adım hatası.');
    });
}

function runCountdownLoop() {
    if (!isSimulationActive) return;

    if (currentRemainingSeconds > 0) {
        currentRemainingSeconds--;
        const cdEl = document.getElementById('live-countdown');
        if (cdEl) cdEl.innerText = formatCountdown(currentRemainingSeconds);
    } else {
        const cdEl = document.getElementById('live-countdown');
        if (cdEl) cdEl.innerText = '00:00';
    }
}

function showLiveAlert(type, msg) {
    const box = document.getElementById('live-alert-box');
    if (!box) return;
    box.style.display = 'block';

    if (type === 'success') {
        box.style.background = '#f0fdf4';
        box.style.border = '1px solid #bbf7d0';
        box.style.color = '#166534';
    } else if (type === 'warning') {
        box.style.background = '#fffbeb';
        box.style.border = '1px solid #fef3c7';
        box.style.color = '#92400e';
    } else {
        box.style.background = '#fef2f2';
        box.style.border = '1px solid #fecaca';
        box.style.color = '#991b1b';
    }
    box.innerHTML = msg;
}

function fetchShowTelemetry() {
    fetch('/stok-takip/public/mes/simulator/status?work_order_id=' + currentWoId)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.telemetry) updateUI(data.telemetry);
        })
        .catch(err => console.warn('Telemetry poll error:', err));
}

document.addEventListener('DOMContentLoaded', function() {
    fetchShowTelemetry();
    // 1. Visual-only client countdown
    clientTimer = setInterval(runCountdownLoop, 1000);
    // 2. Periodic backend status sync (reads worker updates)
    setInterval(fetchShowTelemetry, 2000);
});
</script>