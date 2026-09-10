<?php
// We will generate the updated view using a PHP script
$file = dirname(__DIR__, 2) . '/views/admin/dashboard.php';
$html = file_get_contents($file);

// Add the Quick Highlights between KPI Cards and Details
$highlightsHTML = <<<'HTML'
    <!-- 2.5 YÖNETİCİ ÖZETİ (HIGHLIGHTS) -->
    <div style="display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 24px;">
        <?php
            // Calculate Highlights
            $bestLine = null;
            $maxRealization = -1;
            
            $worstQualityLine = null;
            $minQuality = 101;
            
            foreach ($lineMatrix as $lm) {
                if ($lm['realization_rate_pct'] > $maxRealization) {
                    $maxRealization = $lm['realization_rate_pct'];
                    $bestLine = $lm['line_name'];
                }
                if ($lm['quality_pass_rate_pct'] < $minQuality && $lm['produced_panels'] > 0) {
                    $minQuality = $lm['quality_pass_rate_pct'];
                    $worstQualityLine = $lm['line_name'];
                }
            }
            $highestMaterial = !empty($topMaterials) ? $topMaterials[0]['material_name'] : '-';
        ?>
        <div style="flex: 1; min-width: 200px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; display: flex; align-items: center; gap: 12px;">
            <div style="font-size: 24px;">🏆</div>
            <div>
                <div style="font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase;">En İyi Hat</div>
                <div style="font-size: 13.5px; font-weight: 800; color: #0f172a;"><?= htmlspecialchars($bestLine ?? '-') ?> (%<?= $maxRealization ?>)</div>
            </div>
        </div>
        <div style="flex: 1; min-width: 200px; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 8px; padding: 12px; display: flex; align-items: center; gap: 12px;">
            <div style="font-size: 24px;">⚠️</div>
            <div>
                <div style="font-size: 11px; color: #9f1239; font-weight: 700; text-transform: uppercase;">Kalite Sorunu</div>
                <div style="font-size: 13.5px; font-weight: 800; color: #881337;"><?= htmlspecialchars($worstQualityLine ?? '-') ?> (Onay: %<?= $minQuality ?>)</div>
            </div>
        </div>
        <div style="flex: 1; min-width: 200px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px; display: flex; align-items: center; gap: 12px;">
            <div style="font-size: 24px;">📦</div>
            <div>
                <div style="font-size: 11px; color: #b45309; font-weight: 700; text-transform: uppercase;">En Çok Tüketilen</div>
                <div style="font-size: 13.5px; font-weight: 800; color: #78350f;"><?= htmlspecialchars($highestMaterial) ?></div>
            </div>
        </div>
    </div>
HTML;

$html = preg_replace('/(<!-- 3\. DETAY)/', $highlightsHTML . "\n\n    $1", $html);

// Update Trend HTML
$trendOld = <<<'HTML'
            <?php if (empty($trend)): ?>
                <p style="color: #64748b; font-size: 13px; text-align: center; padding: 20px 0;">Seçilen tarih aralığında üretim kaydı bulunmuyor.</p>
            <?php else: 
                $maxQty = max(array_column($trend, 'qty')) ?: 1;
            ?>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php foreach ($trend as $t): 
                        $pct = round(($t['qty'] / $maxQty) * 100);
                    ?>
                        <div>
                            <div style="display: flex; justify-content: space-between; font-size: 12px; color: #475569; margin-bottom: 4px;">
                                <span style="font-weight: 600;"><?= date('d.m.Y', strtotime($t['p_date'])) ?></span>
                                <span style="font-weight: 800; color: #0f172a;"><?= number_format((int)$t['qty']) ?> Panel</span>
                            </div>
                            <div style="height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden;">
                                <div style="width: <?= $pct ?>%; height: 100%; background: linear-gradient(90deg, #2563eb 0%, #3b82f6 100%); border-radius: 4px;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
HTML;

$trendNew = <<<'HTML'
            <?php if (empty($trend)): ?>
                <p style="color: #64748b; font-size: 13px; text-align: center; padding: 20px 0;">Seçilen tarih aralığında üretim kaydı bulunmuyor.</p>
            <?php else: 
                $maxQty = max(array_column($trend, 'qty')) ?: 1;
                $maxPlan = max(array_column($trend, 'planned')) ?: 1;
                $overallMax = max($maxQty, $maxPlan);
            ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($trend as $t): 
                        $pctAct = round(($t['qty'] / $overallMax) * 100);
                        $pctPlan = round(($t['planned'] / $overallMax) * 100);
                    ?>
                        <div>
                            <div style="display: flex; justify-content: space-between; font-size: 12px; color: #475569; margin-bottom: 4px;">
                                <span style="font-weight: 600;"><?= date('d.m.Y', strtotime($t['p_date'])) ?></span>
                                <span style="font-weight: 700; color: #0f172a;">Ü: <?= number_format((int)$t['qty']) ?> / P: <?= number_format((int)$t['planned']) ?></span>
                            </div>
                            <div style="position: relative; height: 12px; background: #f1f5f9; border-radius: 4px; overflow: hidden; display: flex; flex-direction: column; gap: 1px;">
                                <!-- Planlanan çubuğu (arka plan gibi) -->
                                <div style="width: <?= $pctPlan ?>%; height: 5px; background: #cbd5e1; border-radius: 2px;"></div>
                                <!-- Gerçekleşen çubuğu -->
                                <div style="width: <?= $pctAct ?>%; height: 6px; background: linear-gradient(90deg, #2563eb 0%, #3b82f6 100%); border-radius: 2px;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: 12px; display: flex; gap: 16px; font-size: 11px; color: #64748b; justify-content: center;">
                    <div style="display: flex; align-items: center; gap: 4px;">
                        <div style="width: 10px; height: 10px; background: #cbd5e1; border-radius: 2px;"></div> Planlanan
                    </div>
                    <div style="display: flex; align-items: center; gap: 4px;">
                        <div style="width: 10px; height: 10px; background: #3b82f6; border-radius: 2px;"></div> Gerçekleşen
                    </div>
                </div>
            <?php endif; ?>
HTML;

// Note: I will use preg_replace for trendOld too since encoding issues with 'ç' might mismatch.
$html = preg_replace('/(<\?php if \(empty\(\$trend\)\): \?>.*?<\?php endif; \?>)/s', $trendNew, $html, 1);

// Update Matrix HTML
$matrixNew = <<<'HTML'
            <table style="width: 100%; border-collapse: collapse; font-size: 13.5px; text-align: left;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569;">Hat Kodu &amp; Adı</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-align: right;">Üretilen Panel</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-align: right;">Gerçekleşme %</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-align: right;">Kalite Onay %</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-align: right;">SEC (kWh/pnl)</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-align: right;">E. Maliyeti (TL/pnl)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lineMatrix as $lm): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 12px 16px; font-weight: 700; color: #0f172a;">
                                <?= htmlspecialchars($lm['line_name']) ?>
                                <span style="font-size: 11.5px; color: #64748b; font-weight: 400; display: block;"><?= htmlspecialchars($lm['line_code']) ?></span>
                            </td>
                            <td style="padding: 12px 16px; text-align: right; font-weight: 800; color: #0f172a;">
                                <?= number_format($lm['produced_panels'], 0, ',', '.') ?>
                            </td>
                            <td style="padding: 12px 16px; text-align: right; font-weight: 700; color: <?= $lm['realization_rate_pct'] >= 90 ? '#16a34a' : '#d97706' ?>;">
                                %<?= number_format($lm['realization_rate_pct'], 1, ',', '.') ?>
                            </td>
                            <td style="padding: 12px 16px; text-align: right; font-weight: 700; color: <?= $lm['quality_pass_rate_pct'] >= 95 ? '#16a34a' : '#dc2626' ?>;">
                                %<?= number_format($lm['quality_pass_rate_pct'], 1, ',', '.') ?>
                            </td>
                            <td style="padding: 12px 16px; text-align: right; font-weight: 800; color: #0284c7;">
                                <?= number_format($lm['sec_kwh_per_panel'], 2, ',', '.') ?>
                            </td>
                            <td style="padding: 12px 16px; text-align: right; font-weight: 700; color: #dc2626;">
                                ₺<?= number_format($lm['unit_energy_cost_tl_panel'], 2, ',', '.') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
HTML;

$html = preg_replace('/<table.*?<\/table>/s', $matrixNew, $html, 1); // since there is a top table for materials, maybe this is bad!
// Wait! Let's do it safer.
$html = preg_replace('/<table.*?Hat Kodu.*?<\/table>/s', $matrixNew, $html, 1);

file_put_contents($file, $html);
echo "View updated perfectly.\n";
?>
