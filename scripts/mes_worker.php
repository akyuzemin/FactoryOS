<?php

/**
 * MES Backend Production Worker
 * 
 * Bu worker, tarayıcıdan ve arayüzden tamamen bağımsız olarak arka planda çalışır.
 * Zamanı gelen (next_run_at <= NOW()) aktif MES simülasyonlarını tespit eder,
 * atomik olarak processTick() çağırır, BOM hammadde çıkışlarını ve mamul girişlerini gerçekleştirir.
 * 
 * Kullanım Şekilleri:
 * 1. Tek Seferlik Çalıştırma:
 *    php scripts/mes_worker.php
 * 
 * 2. Sürekli Arka Plan Daemon Modu (Saniyede bir kontrol döngüsü):
 *    php scripts/mes_worker.php --daemon
 *    php scripts/mes_worker.php --daemon --interval=1
 * 
 * 3. Belirli Bir İş Emrini Hedefleme:
 *    php scripts/mes_worker.php --work_order=1
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Bu script sadece CLI (komut satırı) veya Task Scheduler üzerinden çalıştırılabilir.\n");
}

// Ignore user abort and set no time limit for continuous daemon
@ignore_user_abort(true);
@set_time_limit(0);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Services/MesSimulationService.php';
require_once __DIR__ . '/../app/Services/MesWorkerService.php';

// CLI Argümanlarını Oku
$options = getopt('', ['daemon', 'once', 'interval:', 'work_order:', 'work-order:', 'help']);

if (isset($options['help'])) {
    echo "MES Backend Worker Kullanım Kılavuzu:\n";
    echo "  --daemon         : Sürekli döngüde çalışır (Ctrl+C ile durdurulur).\n";
    echo "  --once           : Tek bir kontrol döngüsü yapar ve çıkar (varsayılan).\n";
    echo "  --interval=N     : Daemon modundaki kontrol aralığı (varsayılan: 1 saniye).\n";
    echo "  --work_order=ID  : Sadece belirtilen iş emrini çalıştırır.\n";
    exit(0);
}

$isDaemon = isset($options['daemon']);
$sleepSeconds = !empty($options['interval']) ? max(1, (int)$options['interval']) : 1;
$targetWorkOrderId = !empty($options['work_order']) ? (int)$options['work_order'] : (!empty($options['work-order']) ? (int)$options['work-order'] : null);

// PID Kaydı ve Kapanış Temizliği
$pid = getmypid();
$pidFile = MesWorkerService::getPidFilePath();
@file_put_contents($pidFile, (string)$pid);

register_shutdown_function(function() use ($pidFile, $pid) {
    if (file_exists($pidFile) && (int)@file_get_contents($pidFile) === $pid) {
        @unlink($pidFile);
    }
});

// Veritabanı ve Servis Başlatma
try {
    $pdo = (new Database())->connect();
    $simService = new MesSimulationService($pdo);
} catch (Throwable $e) {
    fwrite(STDERR, sprintf("[%s] [FATAL] Veritabanı bağlantı hatası: %s\n", date('Y-m-d H:i:s'), $e->getMessage()));
    exit(1);
}

function logWorker(string $message, string $level = 'INFO'): void {
    $line = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $level, $message);
    echo $line;

    // Optional persistent log file
    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    if (is_dir($logDir) && is_writable($logDir)) {
        @file_put_contents($logDir . '/mes_worker.log', $line, FILE_APPEND);
    }
}

logWorker(sprintf("MES Worker başlatıldı. PID: %d | Mod: %s | Hedef İş Emri: %s", $pid, $isDaemon ? "DAEMON (her {$sleepSeconds}s)" : "SINGLE PASS", $targetWorkOrderId ?: "TÜMÜ"));

$shouldRun = true;

// Graceful signal handling if available
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function() use (&$shouldRun) { $shouldRun = false; });
    pcntl_signal(SIGINT, function() use (&$shouldRun) { $shouldRun = false; });
}

$loopCount = 0;

do {
    $loopCount++;
    try {
        $batch = $simService->runDueSimulations(10, $targetWorkOrderId);
        
        if ($batch['due_count'] > 0) {
            foreach ($batch['results'] as $item) {
                $woNo = $item['work_order_no'];
                $res = $item['result'];

                if (!empty($res['success']) && ($res['action'] ?? '') === 'PANEL_PRODUCED') {
                    logWorker(sprintf(
                        "[%s] Panel #%d üretildi -> Ref: %s | Üretilen: %s/%s | Kalan: %s (%%%s)",
                        $woNo,
                        $res['panel_number'] ?? 0,
                        $res['stock_reference'] ?? 'N/A',
                        $res['produced_quantity'] ?? 0,
                        $res['planned_quantity'] ?? 0,
                        $res['remaining_quantity'] ?? 0,
                        $res['progress_pct'] ?? 0
                    ), 'INFO');
                } elseif (!empty($res['status']) && $res['status'] === 'COMPLETED') {
                    logWorker(sprintf("[%s] 🏆 HEDEF TAMAMLANDI: İş emri %s başarıyla tamamlandı.", $woNo, $woNo), 'INFO');
                } elseif (!empty($res['status']) && $res['status'] === 'RETRY_SCHEDULED') {
                    logWorker(sprintf(
                        "[%s] ⚠ Geçici hata | Retry %d/3 | Sonraki deneme: %s | Detay: %s",
                        $woNo,
                        $res['retry_count'] ?? 1,
                        !empty($res['next_retry_at']) ? date('H:i:s', strtotime($res['next_retry_at'])) : '-',
                        $res['message'] ?? 'Geçici sistem hatası'
                    ), 'WARN');
                } elseif (!empty($res['status']) && $res['status'] === 'FAILED') {
                    $errCode = $res['error_code'] ?? 'FAILED';
                    if ($errCode === 'RETRY_EXHAUSTED') {
                        logWorker(sprintf("[%s] 🛑 Retry limiti aşıldı (3/3) | Üretim durduruldu", $woNo), 'ERROR');
                    } elseif ($errCode === 'INSUFFICIENT_STOCK' || str_contains(mb_strtolower($res['message'] ?? ''), 'yetersiz')) {
                        logWorker(sprintf("[%s] 🛑 Hammadde stoğu yetersiz | Retry uygulanmadı | İş emri PAUSED | Detay: %s", $woNo, $res['message'] ?? ''), 'ERROR');
                    } else {
                        logWorker(sprintf("[%s] 🛑 İşlemsel Hata (%s) | Retry uygulanmadı | Detay: %s", $woNo, $errCode, $res['message'] ?? ''), 'ERROR');
                    }
                }
            }
        } elseif ($loopCount % 60 === 0 && $isDaemon) {
            // Heartbeat log every 60 seconds if idle
            logWorker("Heartbeat: Beklemede olan zamanı gelmiş aktif iş emri yok.", "DEBUG");
        }

    } catch (Throwable $e) {
        logWorker(sprintf("Worker döngü hatası (Devam ediliyor): %s", $e->getMessage()), "ERROR");
    }

    if ($isDaemon && $shouldRun) {
        sleep($sleepSeconds);
    } else {
        break;
    }

} while ($shouldRun);

logWorker("MES Worker çalışmasını tamamladı ve sonlandı.");
exit(0);
