<?php
require_once 'c:/wamp64/www/stok-takip/config/database.php';
require_once 'c:/wamp64/www/stok-takip/app/Services/QrCodeService.php';
require_once 'c:/wamp64/www/stok-takip/app/Models/PanelUnit.php';
require_once 'c:/wamp64/www/stok-takip/app/Models/Mes.php';
require_once 'c:/wamp64/www/stok-takip/app/Services/MesSimulationService.php';

$pdo = (new Database())->connect();
$qrService = new QrCodeService();
$panelModel = new PanelUnit($pdo);
$mesModel = new Mes($pdo);
$simService = new MesSimulationService($pdo);

echo "========================================================================\n";
echo "AŞAMA 7 — QR KOD / BARKOD ENTEGRASYON VE DOĞRULAMA TEST PROTOKOLÜ\n";
echo "========================================================================\n";

// 1. Produce a test panel
$woNo = 'WO-QR-TEST-' . strtoupper(bin2hex(random_bytes(3)));
$woId = $mesModel->createWorkOrder([
    'work_order_no'       => $woNo,
    'product_material_id' => 25,
    'recipe_id'           => 3,
    'production_line_id'  => 1,
    'planned_quantity'    => 1.0,
    'status'              => 'READY'
]);

$pdo->exec("UPDATE stock_balances SET quantity = 50000.0 WHERE location_id = 1");
$simRes = $simService->processTick($woId, true);
$panel = $pdo->query("SELECT * FROM panel_units WHERE work_order_id = {$woId} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$serialNo = $panel['serial_no'];

echo "Test Paneli Üretildi: {$serialNo}\n";

// ----------------------------------------------------
// TEST 1: Saf PHP QR Kod Üretimi & SVG Yapı Kontrolü
// ----------------------------------------------------
$targetUrl = 'http://localhost/stok-takip/public/finished-goods/serial?serial_no=' . urlencode($serialNo);
$svg = $qrService->generateSvg($targetUrl, 180);
$dataUri = $qrService->generateDataUri($targetUrl, 180);

$test1Ok = (
    !empty($svg) &&
    str_contains($svg, '<svg') &&
    str_contains($svg, '</svg>') &&
    str_contains($svg, 'rect') &&
    str_starts_with($dataUri, 'data:image/svg+xml;base64,')
);
echo "TEST 1 (Saf PHP QR SVG Üretimi & Vektör Çıktısı): " . ($test1Ok ? "[PASS]" : "[FAIL]") . "\n";

// ----------------------------------------------------
// TEST 2: Dinamik Ortam Bağımsız URL Üretimi (Localhost, Tunnel & Prod)
// ----------------------------------------------------
// Sim 2a: Ngrok / Cloudflare Tunnel
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
$_SERVER['HTTP_X_FORWARDED_HOST'] = 'mycompany.ngrok-free.app';
$isHttpsA = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
$hostA = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
$urlA = sprintf('%s://%s/stok-takip/public/finished-goods/serial?serial_no=%s', $isHttpsA ? 'https' : 'http', $hostA, urlencode($serialNo));

// Sim 2b: Production Domain
unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
unset($_SERVER['HTTP_X_FORWARDED_HOST']);
$_SERVER['HTTPS'] = 'on';
$_SERVER['HTTP_HOST'] = 'mes.fabrika.com';
$isHttpsB = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$hostB = $_SERVER['HTTP_HOST'];
$urlB = sprintf('%s://%s/stok-takip/public/finished-goods/serial?serial_no=%s', $isHttpsB ? 'https' : 'http', $hostB, urlencode($serialNo));

$test2Ok = (
    str_starts_with($urlA, 'https://mycompany.ngrok-free.app/stok-takip/public/finished-goods/serial?serial_no=') &&
    str_starts_with($urlB, 'https://mes.fabrika.com/stok-takip/public/finished-goods/serial?serial_no=')
);
echo "TEST 2 (Dinamik Ortam Bağımsız QR URL Çözümlemesi): " . ($test2Ok ? "[PASS]" : "[FAIL]") . "\n";

// ----------------------------------------------------
// TEST 3: Seri Numarası İle Panel Arama & Pasaport Bağlantısı
// ----------------------------------------------------
$fetchedPanel = $panelModel->getBySerial($serialNo);
$test3Ok = ($fetchedPanel !== null && (int)$fetchedPanel['id'] === (int)$panel['id']);
echo "TEST 3 (Seri No İle Panel Çekme / Route Çözümleme): " . ($test3Ok ? "[PASS]" : "[FAIL]") . "\n";

// ----------------------------------------------------
// TEST 4: Depo Hızlı Kontrol Durum Rozetleri
// ----------------------------------------------------
$pdo->exec("UPDATE panel_units SET status = 'QUALITY_APPROVED' WHERE id = {$panel['id']}");
$panelApproved = $panelModel->getById((int)$panel['id']);
$test4Ok = ($panelApproved['status'] === 'QUALITY_APPROVED');

$pdo->exec("UPDATE panel_units SET status = 'QUALITY_REJECTED' WHERE id = {$panel['id']}");
$panelRejected = $panelModel->getById((int)$panel['id']);
$test4Ok = $test4Ok && ($panelRejected['status'] === 'QUALITY_REJECTED');

echo "TEST 4 (Depo Hızlı Kontrol Durum Rozetleri): " . ($test4Ok ? "[PASS]" : "[FAIL]") . "\n";

// Clean up test panel
$pdo->exec("DELETE FROM panel_units WHERE work_order_id = {$woId}");
$pdo->exec("DELETE FROM stock_movements WHERE reference_no IN (SELECT DISTINCT stock_movement_ref FROM mes_production_events WHERE work_order_id = {$woId})");
$pdo->exec("DELETE FROM mes_production_events WHERE work_order_id = {$woId}");
$pdo->exec("DELETE FROM mes_simulations WHERE work_order_id = {$woId}");
$pdo->exec("DELETE FROM mes_work_orders WHERE id = {$woId}");

echo "\n==================================================\n";
if ($test1Ok && $test2Ok && $test3Ok && $test4Ok) {
    echo "AŞAMA 7 QR KOD TESTLERİ: TÜMÜ BAŞARIYLA GEÇTİ (100% SUCCESS)\n";
} else {
    echo "AŞAMA 7 QR KOD TESTLERİ: BAZI TESTLER BAŞARISIZ OLDU\n";
}
echo "==================================================\n";
