<?php

/**
 * TEST: Admin Dashboard Encoding & Link Doğrulama Testi
 */

ob_start();
session_start();

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/routes/web.php';

$pdo = (new Database())->connect();
$GLOBALS['pdo'] = $pdo;

echo "========================================================================\n";
echo "YÖNETİCİ DASHBOARD ENCODING VE LİNK DOĞRULAMA TEST PROTOKOLÜ\n";
echo "========================================================================\n\n";

$passCount = 0;
$failCount = 0;

function reportTest(string $title, bool $passed, string $details = ''): void {
    global $passCount, $failCount;
    if ($passed) {
        $passCount++;
        echo "  [PASS] {$title}" . ($details ? " -> {$details}" : "") . "\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$title}" . ($details ? " -> {$details}" : "") . "\n";
    }
}

function fetchUrl(string $url, int $roleId = 3, int $userId = 3): array {
    $ch = curl_init();

    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = 'yonetici';
    $_SESSION['role_id'] = $roleId;
    $sessionId = session_id();
    session_write_close();

    $fullUrl = "http://localhost/stok-takip/public" . $url;
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . $sessionId);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    @session_start();

    return [
        'code' => $httpCode,
        'body' => $response
    ];
}

// ------------------------------------------------------------------------
// 1. /admin/dashboard Render & HTTP 200
// ------------------------------------------------------------------------
$res = fetchUrl('/admin/dashboard', 3);
reportTest('1. /admin/dashboard HTTP 200 Yanıtı', $res['code'] === 200, "HTTP Code: {$res['code']}");

// ------------------------------------------------------------------------
// 2. Türkçe Karakter / Encoding Kontrolü
// ------------------------------------------------------------------------
$mojibakePatterns = ['Ã¶', 'Ã¼', 'Ã§', 'Ã°', 'Å', 'Ä°', 'ğŸ', 'â‚º'];
$hasMojibake = false;
$foundMojibake = [];
foreach ($mojibakePatterns as $pattern) {
    if (strpos($res['body'], $pattern) !== false) {
        $hasMojibake = true;
        $foundMojibake[] = $pattern;
    }
}
reportTest('2. Bozuk / Mojibake Karakter Bulunmuyor', !$hasMojibake, $hasMojibake ? "Bozuk karakterler: " . implode(', ', $foundMojibake) : "Temiz UTF-8");

$turkishWords = ['Yönetici', 'Üretim', 'Gerçekleşme', 'Hammadde', 'Tüketim', 'Kalite', 'Sevkiyat', 'Enerji', 'Ortalama'];
$allWordsPresent = true;
$missingWords = [];
foreach ($turkishWords as $word) {
    if (mb_strpos($res['body'], $word) === false) {
        $allWordsPresent = false;
        $missingWords[] = $word;
    }
}
reportTest('3. Türkçe Sözcükler Eksiksiz ve Doğru', $allWordsPresent, $allWordsPresent ? "Tüm anahtar sözcükler mevcut" : "Eksik: " . implode(', ', $missingWords));

// ------------------------------------------------------------------------
// 3. Yanlış /dashboard/finished-goods Link Kontrolü
// ------------------------------------------------------------------------
$hasWrongUrl = (strpos($res['body'], '/dashboard/finished-goods') !== false || strpos($res['body'], 'dashboard/finished-goods') !== false);
reportTest('4. Yanlış /dashboard/finished-goods URL Bulunmuyor', !$hasWrongUrl, $hasWrongUrl ? "Yanlış link tespit edildi!" : "Hatalı URL yok");

// ------------------------------------------------------------------------
// 4. Panel Detay / Pasaport Linki Doğrulaması (HTTP 200)
// ------------------------------------------------------------------------
$samplePanel = $pdo->query("SELECT id FROM panel_units ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($samplePanel) {
    $panelId = (int)$samplePanel['id'];
    $panelRes = fetchUrl("/finished-goods/show?id={$panelId}", 3);
    reportTest("5. Panel Pasaport Linki Doğrulama (/finished-goods/show?id={$panelId})", $panelRes['code'] === 200, "HTTP Code: {$panelRes['code']}");
} else {
    reportTest("5. Panel Pasaport Linki Doğrulama", true, "Panel kaydı bulunmadığı için atlandı");
}

// ------------------------------------------------------------------------
// 5. Dashboard Linklerinin Geçerli Rotalara Gitmesi
// ------------------------------------------------------------------------
preg_match_all('/<a\s+[^>]*href="([^"]+)"/i', $res['body'], $matches);
$links = array_unique($matches[1]);
$base = '/stok-takip/public';
$invalidRoutes = [];

foreach ($links as $link) {
    if (str_starts_with($link, '?') || str_starts_with($link, '#') || str_starts_with($link, 'http')) {
        continue;
    }
    $path = parse_url($link, PHP_URL_PATH);
    if (str_starts_with($path, $base)) {
        $routePath = substr($path, strlen($base));
        if ($routePath === '') $routePath = '/';
        if (!isset($routes[$routePath])) {
            $invalidRoutes[] = $link;
        }
    }
}
reportTest('6. Dashboard Üzerindeki Tüm Linkler Geçerli Rotalara Bağlı', empty($invalidRoutes), empty($invalidRoutes) ? "Tüm bağlantılar routes/web.php ile uyumlu" : "Geçersiz rotalar: " . implode(', ', $invalidRoutes));

echo "\n========================================================================\n";
echo "TEST SONUCU: {$passCount} PASS / {$failCount} FAIL\n";
echo "========================================================================\n";
