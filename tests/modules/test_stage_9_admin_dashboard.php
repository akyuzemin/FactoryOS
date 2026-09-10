<?php
ob_start();
session_start();

function testEndpoint($url, $roleId, $userId = 1) {
    $ch = curl_init();
    
    $_SESSION['user_id'] = $userId;
    $_SESSION['role_id'] = $roleId;
    $sessionId = session_id();
    session_write_close();
    
    curl_setopt($ch, CURLOPT_URL, "http://localhost/stok-takip/public" . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . $sessionId);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    session_start(); // Reopen for next assignment
    
    return [
        'code' => $httpCode,
        'body' => $response
    ];
}

echo "========================================================================\n";
echo "AŞAMA 9 — YÖNETİCİ DASHBOARD TEST PROTOKOLÜ\n";
echo "========================================================================\n\n";

echo "TEST 1: Yetkili Kullanıcı (Yönetici) ile /admin/dashboard erişimi\n";
$res1 = testEndpoint('/admin/dashboard', 3);
if ($res1['code'] == 200 && strpos($res1['body'], 'Dashboard') !== false) {
    echo "[PASS] HTTP 200 alındı ve sayfa yüklendi.\n";
} else {
    echo "[FAIL] Beklenen HTTP 200, alınan: " . $res1['code'] . "\n";
}
echo "------------------------------------------------------------------------\n";

echo "TEST 2: Yetkisiz Kullanıcı (Operatör) ile /admin/dashboard erişimi\n";
$res2 = testEndpoint('/admin/dashboard', 4);
if ($res2['code'] == 403) {
    echo "[PASS] HTTP 403 Forbidden alındı.\n";
} else {
    echo "[FAIL] Beklenen HTTP 403, alınan: " . $res2['code'] . "\n";
}
echo "------------------------------------------------------------------------\n";

echo "TEST 3: Mevcut / (dashboard) ekranına erişim\n";
$res3 = testEndpoint('/', 1); // Admin

if ($res3['code'] == 200) {
    echo "[PASS] Mevcut / (dashboard) HTTP 200 döndürdü ve bozulmadı.\n";
} else {
    echo "[FAIL] Mevcut / (dashboard) HTTP 200 döndürmedi! Kod: " . $res3['code'] . "\n";
}

echo "========================================================================\n";
ob_end_flush();