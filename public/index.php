<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Services/CsrfService.php';
require_once __DIR__ . '/../app/Router.php';
require_once __DIR__ . '/../routes/web.php';
require_once __DIR__ . '/../app/Services/MesSimulationService.php';
require_once __DIR__ . '/../app/Services/MesWorkerService.php';

$database = new Database();
$pdo = $database->connect();

$GLOBALS['pdo'] = $pdo;

// Background Production Safeguard & Worker Lifecycle Check
try {
    // Fast check: If any active simulation exists, ensure background worker is alive
    $hasActiveSim = (int)$pdo->query("SELECT COUNT(*) FROM mes_simulations WHERE is_active = 1")->fetchColumn() > 0;
    if ($hasActiveSim) {
        MesWorkerService::ensureWorkerRunning();
        
        // Fast non-blocking check for any due items
        $simService = new MesSimulationService($pdo);
        $simService->runDueSimulations(5);
    }
} catch (Throwable $e) {
    // Non-blocking error handling for background safeguard
}

$router = new Router($routes);

$router->dispatch($_SERVER['REQUEST_URI']);