<?php
$file = dirname(__DIR__, 2) . '/views/admin/dashboard.php';
$html = file_get_contents($file);

preg_match('/<!-- KPI 5: Toplam .*? Maliyeti -->.*?<\/div>\s*<\/div>/s', $html, $match);
if ($match) {
    $kpi5 = $match[0];
    $html = str_replace($kpi5, '', $html);
    $html = preg_replace('/<\/div>\s*<!-- 2\.5 YÖNETİCİ ÖZETİ/', "\n" . trim($kpi5) . "\n    </div>\n\n    <!-- 2.5 YÖNETİCİ ÖZETİ", $html);
    file_put_contents($file, $html);
    echo "Reordered KPI 5 to the end.\n";
} else {
    echo "Could not find KPI 5.\n";
}
