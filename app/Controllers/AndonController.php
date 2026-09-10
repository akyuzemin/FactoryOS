<?php

require_once __DIR__ . '/../Services/AndonService.php';

class AndonController
{
    private PDO $pdo;
    private AndonService $andonService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->andonService = new AndonService($pdo);
    }

    /**
     * Canlı Andon Ekranı (GET /andon)
     */
    public function index(): void
    {
        $isKiosk = isset($_GET['kiosk']) && $_GET['kiosk'] == '1';
        $data = $this->andonService->getAndonData();

        $pageTitle = 'Canlı Andon & Fabrika Vitrini';
        $activePage = 'andon';

        require __DIR__ . '/../../views/andon/index.php';
    }

    /**
     * Canlı Andon JSON Telemetri API Endpoint (GET /andon/api/live)
     */
    public function live(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        $data = $this->andonService->getAndonData();

        echo json_encode([
            'success' => true,
            'data'    => $data
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
}

