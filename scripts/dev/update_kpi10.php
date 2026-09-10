<?php
$file = dirname(__DIR__, 2) . '/views/admin/dashboard.php';
$html = file_get_contents($file);

$oldKPI10 = <<<'HTML'
        <!-- KPI 10: Aktif Enerji AlarmlarÄ± -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase;">ğŸš¨ AKTÄ°F ALARMLAR</div>
            <div style="font-size: 22px; font-weight: 900; color: <?= ($kpis['active_alerts_count'] > 0) ? '#dc2626' : '#16a34a' ?>; margin: 4px 0 2px 0;">
                <?= (int)$kpis['active_alerts_count'] ?> <small style="font-size: 13px; font-weight: 600;">Olay</small>
            </div>
            <div style="font-size: 11.5px; color: #64748b;">Aksiyon bekleyen uyarÄ±</div>
        </div>
HTML;

$newKPI10 = <<<'HTML'
        <!-- KPI 10: Aktif Enerji Alarmları -->
        <a href="/stok-takip/public/energy-alerts" style="text-decoration: none; display: block; background: #ffffff; border: 1px solid <?= ($kpis['active_alerts_count'] > 0) ? '#fca5a5' : '#e2e8f0' ?>; border-radius: 12px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: all 0.2s; cursor: pointer;">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase;">🚨 AKTİF ALARMLAR</div>
            <div style="font-size: 22px; font-weight: 900; color: <?= ($kpis['active_alerts_count'] > 0) ? '#dc2626' : '#16a34a' ?>; margin: 4px 0 2px 0;">
                <?= (int)$kpis['active_alerts_count'] ?> <small style="font-size: 13px; font-weight: 600;">Olay</small>
            </div>
            <div style="font-size: 11.5px; color: <?= ($kpis['active_alerts_count'] > 0) ? '#dc2626' : '#64748b' ?>; font-weight: <?= ($kpis['active_alerts_count'] > 0) ? '700' : '400' ?>; display: flex; justify-content: space-between; align-items: center;">
                <span><?= ($kpis['active_alerts_count'] > 0) ? 'İncelemek için tıkla' : 'Yeni bildirim yok' ?></span>
                <span style="font-size: 14px;">➔</span>
            </div>
        </a>
HTML;

// Need to match exactly despite possible encoding issues.
$html = preg_replace('/<!-- KPI 10: Aktif Enerji Alarmlar.*?<\/div>\s*<\/div>/s', $newKPI10, $html);
file_put_contents($file, $html);
echo "Updated KPI 10.\n";
?>
