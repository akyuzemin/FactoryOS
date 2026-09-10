<?php
$pageTitle = 'Maliyet &amp; Tasarruf';
$activePage = 'energy-cost';

// Defensive HTML Escaping Helper
$h = static function (mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
};

// Defensive Number Formatting Helper
$fNum = static function (mixed $value, int $decimals = 1): string {
    return number_format((float)($value ?? 0.0), $decimals, ',', '.');
};

// Defensive Extraction
$summary = is_array($summary ?? null) ? $summary : [];
$tariffs = is_array($tariffs ?? null) ? $tariffs : [];
$scenarios = is_array($scenarios ?? null) ? $scenarios : [];
$savingsEngineData = is_array($savingsEngine ?? null) ? $savingsEngine : ['rules' => array_values($scenarios)];
$rules = is_array($savingsEngineData['rules'] ?? null) ? $savingsEngineData['rules'] : array_values($scenarios);

$targetDate = (string)($summary['target_date'] ?? date('Y-m-d'));
$range = (string)($summary['range'] ?? 'today');

$monthlyProjected = (float)($summary['monthly_projected_cost_tl'] ?? $summary['total_cost_tl'] ?? 0);
$puantUnitPrice = (float)($tariffs['puant']['unit_price_tl'] ?? $tariffs['puant']['unit_price'] ?? 6.80);
$gunduzUnitPrice = (float)($tariffs['gunduz']['unit_price_tl'] ?? $tariffs['gunduz']['unit_price'] ?? 4.60);
$geceUnitPrice = (float)($tariffs['gece']['unit_price_tl'] ?? $tariffs['gece']['unit_price'] ?? 3.15);

$shiftMonthlySaving = (float)($shiftScenario['monthly_saving_tl'] ?? $scenarios['peak_shift']['monthly_saving_tl'] ?? 0);
$totalMonthlySavingTl = (float)($totalMonthlySavingTl ?? $summary['monthly_savings_potential_tl'] ?? 0);

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <!-- BAŞLIK VE TARİH FİLTRESİ -->
    <header class="page-header">
        <div>
            <p class="page-kicker">Karar Destek Sistemi &amp; Maliyet Yönetimi</p>
            <h1>💰 Maliyet &amp; Tasarruf</h1>
            <p class="page-description">Tesis elektrik harcamaları ve uygulanabilir tasarruf fırsatları.</p>
        </div>
        <div class="header-badges">
            <div class="filter-preset-wrap" style="display: flex; gap: 6px; align-items: center;">
                <a href="/stok-takip/public/energy/cost?range=today&date=<?= $h($targetDate) ?>" 
                   class="button button-sm <?= $range === 'today' ? 'button-primary' : 'button-secondary' ?>">Bugün</a>
                <a href="/stok-takip/public/energy/cost?range=7days&date=<?= $h($targetDate) ?>" 
                   class="button button-sm <?= $range === '7days' ? 'button-primary' : 'button-secondary' ?>">7 Gün</a>
                <a href="/stok-takip/public/energy/cost?range=30days&date=<?= $h($targetDate) ?>" 
                   class="button button-sm <?= $range === '30days' ? 'button-primary' : 'button-secondary' ?>">30 Gün</a>
            </div>

            <form method="GET" action="/stok-takip/public/energy/cost" class="energy-date-picker-form">
                <input type="hidden" name="range" value="<?= $h($range) ?>">
                <input type="date" id="date-select" name="date" value="<?= $h($targetDate) ?>" class="energy-date-input" max="<?= date('Y-m-d') ?>">
                <button type="submit" class="button button-secondary button-sm">Filtrele</button>
            </form>
        </div>
    </header>

    <!-- 1. YÖNETİCİ KARAR DESTEK VİTRİNİ (THE EXECUTIVE STORY) -->
    <div class="card" style="padding: 24px; margin-bottom: 24px; background: linear-gradient(135deg, rgba(139, 92, 246, 0.12) 0%, rgba(6, 182, 212, 0.08) 100%); border-left: 4px solid var(--purple);">
        
        <div style="font-size: 13px; font-weight: 700; color: #c4b5fd; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
            YÖNETİCİ ÖZETİ &amp; KARAR DESTEK
        </div>

        <div style="font-size: 24px; font-weight: 800; color: var(--ink); line-height: 1.3; margin-bottom: 16px;">
            Bu ay enerji için yaklaşık <span style="color: #38bdf8;">₺<?= $fNum($monthlyProjected, 0) ?></span> harcanıyor.
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; margin-bottom: 16px;">
            
            <div style="padding: 12px 14px; border-radius: var(--radius-sm); background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25);">
                <div style="font-size: 11px; color: #fca5a5; font-weight: 700; text-transform: uppercase;">⚠️ EN BÜYÜK GİDER</div>
                <div style="font-size: 14px; font-weight: 700; color: var(--ink); margin-top: 2px;">
                    17:00 - 22:00 Puant Dönemi
                </div>
                <div style="font-size: 11.5px; color: var(--text-secondary); margin-top: 2px;">
                    Birim fiyat: <strong>₺<?= $fNum($puantUnitPrice, 2) ?>/kWh</strong> (%60 daha pahalı)
                </div>
            </div>

            <div style="padding: 12px 14px; border-radius: var(--radius-sm); background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.25);">
                <div style="font-size: 11px; color: #fcd34d; font-weight: 700; text-transform: uppercase;">💡 ÖNERİLEN AKSİYON</div>
                <div style="font-size: 14px; font-weight: 700; color: var(--ink); margin-top: 2px;">
                    Yükün %25'ini geceye kaydır
                </div>
                <div style="font-size: 11.5px; color: var(--text-secondary); margin-top: 2px;">
                    Test ve laminasyon hatlarını 22:00-06:00 arasına planlayın
                </div>
            </div>

            <div style="padding: 12px 14px; border-radius: var(--radius-sm); background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3);">
                <div style="font-size: 11px; color: #86efac; font-weight: 700; text-transform: uppercase;">🌱 POTANSİYEL KAZANÇ</div>
                <div style="font-size: 18px; font-weight: 800; color: #34d399; margin-top: 2px;">
                    +₺<?= $fNum($shiftMonthlySaving, 0) ?> <small style="font-size: 12px; font-weight: normal;">/ ay</small>
                </div>
                <div style="font-size: 11.5px; color: var(--text-secondary); margin-top: 2px;">
                    Toplam potansiyel: <strong>+₺<?= $fNum($totalMonthlySavingTl, 0) ?> / ay</strong>
                </div>
            </div>

        </div>

    </div>

    <!-- 2. 4 SOMUT TASARRUF FIRSATI KARTLARI -->
    <div style="margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
            <h3 style="font-size: 16px; font-weight: 700; color: var(--ink); margin: 0; display: flex; align-items: center; gap: 8px;">
                <span>🎯</span> Doğrulanmış Tasarruf Fırsatları (4 Senaryo)
            </h3>
            <span class="status-badge status-badge-success" style="font-size: 11px;">
                Toplam Potansiyel: +₺<?= $fNum($totalMonthlySavingTl, 0) ?> / Ay
            </span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
            <?php if (empty($rules)): ?>
                <div class="card" style="padding: 18px; color: var(--text-muted);">
                    Bu döneme ait tasarruf senaryosu bulunamadı.
                </div>
            <?php else: ?>
                <?php foreach ($rules as $rule): ?>
                    <?php 
                    $rBadgeClass = $rule['badge_class'] ?? $rule['priority_class'] ?? 'status-badge-info';
                    $rBadge = $rule['badge'] ?? $rule['priority'] ?? 'Fırsat';
                    $rSaving = $rule['estimated_monthly_saving_tl'] ?? $rule['monthly_saving_tl'] ?? 0;
                    $rTitle = $rule['title'] ?? 'Tasarruf Senaryosu';
                    $rProblem = $rule['problem'] ?? '';
                    $rWhy = $rule['why_important'] ?? $rule['recommendation'] ?? '';
                    ?>
                    <div class="card" style="padding: 18px; display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <span class="status-badge <?= $h($rBadgeClass) ?>" style="font-size: 10px;"><?= $h($rBadge) ?></span>
                                <strong style="color: #34d399; font-size: 14px;">+₺<?= $fNum($rSaving, 0) ?>/ay</strong>
                            </div>
                            <h4 style="font-size: 13.5px; font-weight: 700; color: var(--ink); margin: 0 0 6px 0;"><?= $h($rTitle) ?></h4>
                            <p style="font-size: 12px; color: var(--text-secondary); margin: 0 0 8px 0; line-height: 1.4;"><?= $h($rProblem) ?></p>
                        </div>

                        <div style="border-top: 1px solid rgba(255, 255, 255, 0.06); padding-top: 10px; margin-top: 8px; font-size: 11.5px; color: #a5b4fc;">
                            ✓ <?= $h($rWhy) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 3. DETAY HESAPLAMA METODOLOJİSİ (COLLAPSIBLE / EXPANDABLE ALAN) -->
    <details class="card" style="padding: 16px 20px; margin-bottom: 24px; cursor: pointer;">
        <summary style="font-size: 13.5px; font-weight: 700; color: var(--ink); display: flex; align-items: center; justify-content: space-between;">
            <span>📐 Nasıl hesaplandı? Tarife &amp; Matematiksel Formül Detayları</span>
            <span style="font-size: 11.5px; color: var(--text-muted);">Genişlet / Daralt ▾</span>
        </summary>

        <div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid rgba(255, 255, 255, 0.06); cursor: default;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 16px;">
                
                <div style="padding: 12px; background: rgba(255, 255, 255, 0.02); border-radius: var(--radius-sm);">
                    <strong style="color: var(--ink); font-size: 12.5px; display: block; margin-bottom: 6px;">EPDK 3 Zamanlı Tarife Katsayıları:</strong>
                    <ul style="font-size: 11.5px; color: var(--text-secondary); margin: 0; padding-left: 18px; line-height: 1.6;">
                        <li><strong>Gündüz (T1 - 06:00-17:00):</strong> ₺<?= $fNum($gunduzUnitPrice, 2) ?> / kWh</li>
                        <li><strong>Puant (T2 - 17:00-22:00):</strong> ₺<?= $fNum($puantUnitPrice, 2) ?> / kWh</li>
                        <li><strong>Gece (T3 - 22:00-06:00):</strong> ₺<?= $fNum($geceUnitPrice, 2) ?> / kWh</li>
                    </ul>
                </div>

                <div style="padding: 12px; background: rgba(255, 255, 255, 0.02); border-radius: var(--radius-sm);">
                    <strong style="color: var(--ink); font-size: 12.5px; display: block; margin-bottom: 6px;">Puant Kaydırma Formülü:</strong>
                    <p style="font-size: 11.5px; color: var(--text-secondary); margin: 0; line-height: 1.5;">
                        <code>Aylık Tasarruf = (Puant kWh × 0.25) × (₺6.80 - ₺2.75) × 30 gün</code><br>
                        Puant ve gece tarifesi arasındaki <strong>₺4.05/kWh</strong> fiyat farkı üzerinden net arbitraj hesaplanmaktadır.
                    </p>
                </div>

            </div>
        </div>
    </details>

</main>
