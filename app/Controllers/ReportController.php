<?php

require_once __DIR__ . '/../Models/Report.php';

class ReportController
{
    private Report $report;

    public function __construct(PDO $pdo)
    {
        $this->report = new Report($pdo);
    }

    public function index(): void
    {
        $allowedTabs = ['summary', 'stock', 'critical', 'warehouses', 'movements', 'history'];
        $tab = trim($_GET['tab'] ?? 'summary');
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'summary';
        }

        $filters = $this->readFilters();
        $filterOptions = $this->report->getFilterOptions();

        $summary = null;
        $recentMovements = null;
        $reportData = null;
        $movementsTotals = null;
        $totalCount = 0;
        $totalPages = 1;
        $currentPage = 1;
        $perPage = 15;

        switch ($tab) {
            case 'summary':
                $summary = $this->report->getSummary();
                $recentMovements = $this->report->getRecentMovements(8);
                break;

            case 'stock':
                $reportData = $this->report->getStockStatusReport($filters);
                break;

            case 'critical':
                $reportData = $this->report->getCriticalStockReport($filters);
                break;

            case 'warehouses':
                $reportData = $this->report->getWarehouseStockReport($filters);
                break;

            case 'movements':
                $currentPage = max(1, (int) ($_GET['page'] ?? 1));
                $totalCount = $this->report->getMovementsReportCount($filters);
                $totalPages = max(1, (int) ceil($totalCount / $perPage));

                if ($currentPage > $totalPages && $totalCount > 0) {
                    $currentPage = $totalPages;
                }

                $reportData = $this->report->getMovementsReport($filters, $currentPage, $perPage);
                $movementsTotals = $this->report->getMovementsSummaryTotals($filters);
                break;

            case 'history':
                $reportData = $this->report->getHistoricalSummary($filters);
                break;
        }

        require __DIR__ . '/../../views/reports/index.php';
    }

    public function export(): void
    {
        $allowedTabs = ['summary', 'stock', 'critical', 'warehouses', 'movements', 'history'];
        $tab = trim($_GET['tab'] ?? 'stock');
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'stock';
        }

        $filters = $this->readFilters();

        $filenameMap = [
            'summary'    => 'genel-ozet.csv',
            'stock'      => 'stok-durumu.csv',
            'critical'   => 'kritik-stoklar.csv',
            'warehouses' => 'depo-dagilimi.csv',
            'movements'  => 'hareket-raporu.csv',
            'history'    => 'tarihsel-ozet.csv',
        ];
        $filename = $filenameMap[$tab] ?? 'rapor.csv';

        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Turkish character compatibility in Excel
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        $formatNumber = static function (float|int|string|null $val): string {
            if ($val === null || $val === '') {
                return '0';
            }
            return number_format((float) $val, 3, ',', '');
        };

        $movementLabels = [
            'IN'           => 'Stok Girişi',
            'OUT'          => 'Stok Çıkışı',
            'TRANSFER_IN'  => 'Transfer Girişi',
            'TRANSFER_OUT' => 'Transfer Çıkışı',
            'RETURN'       => 'Stok İadesi',
            'ADJUSTMENT'   => 'Stok Düzeltme',
        ];

        switch ($tab) {
            case 'stock':
                $data = $this->report->getStockStatusReport($filters);
                fputcsv($out, ['Malzeme Kodu', 'Malzeme Adı', 'Kategori', 'Birim', 'Min Stok', 'Max Stok', 'Mevcut Stok', 'Durum'], ';');
                foreach ($data as $row) {
                    fputcsv($out, [
                        $row['material_code'],
                        $row['material_name'],
                        $row['category_name'],
                        $row['unit_symbol'],
                        $formatNumber($row['min_stock']),
                        $row['max_stock'] !== null ? $formatNumber($row['max_stock']) : '-',
                        $formatNumber($row['current_stock']),
                        $row['status'],
                    ], ';');
                }
                break;

            case 'critical':
                $data = $this->report->getCriticalStockReport($filters);
                fputcsv($out, ['Malzeme Kodu', 'Malzeme Adı', 'Kategori', 'Birim', 'Mevcut Stok', 'Min Stok', 'Eksik Miktar', 'Max Stok', 'Durum'], ';');
                foreach ($data as $row) {
                    fputcsv($out, [
                        $row['material_code'],
                        $row['material_name'],
                        $row['category_name'],
                        $row['unit_symbol'],
                        $formatNumber($row['current_stock']),
                        $formatNumber($row['min_stock']),
                        $formatNumber($row['deficit_qty']),
                        $row['max_stock'] !== null ? $formatNumber($row['max_stock']) : '-',
                        $row['status'],
                    ], ';');
                }
                break;

            case 'warehouses':
                $data = $this->report->getWarehouseStockReport($filters);
                fputcsv($out, ['Depo Adı', 'Depo Kodu', 'Raf / Lokasyon Adı', 'Raf Kodu', 'Malzeme Kodu', 'Malzeme Adı', 'Kategori', 'Miktar', 'Birim', 'Son Güncelleme'], ';');
                foreach ($data as $row) {
                    fputcsv($out, [
                        $row['warehouse_name'],
                        $row['warehouse_code'],
                        $row['location_name'],
                        $row['location_code'],
                        $row['material_code'],
                        $row['material_name'],
                        $row['category_name'],
                        $formatNumber($row['quantity']),
                        $row['unit_symbol'],
                        $row['updated_at'],
                    ], ';');
                }
                break;

            case 'movements':
                $data = $this->report->getMovementsReport($filters, 1, 0); // 0 perPage = no limit
                fputcsv($out, ['Tarih', 'Malzeme Kodu', 'Malzeme Adı', 'Depo', 'Raf', 'Hareket Tipi', 'Miktar', 'Birim', 'İşlem Niteliği', 'Kullanıcı', 'Referans No', 'Açıklama'], ';');
                foreach ($data as $row) {
                    $isScrap = (int) ($row['is_scrap'] ?? 0);
                    $typeLabel = $movementLabels[$row['movement_type']] ?? $row['movement_type'];
                    $nitelik = $isScrap ? 'Fire / Hurda Çıkışı' : $typeLabel;

                    fputcsv($out, [
                        $row['created_at'],
                        $row['material_code'],
                        $row['material_name'],
                        $row['warehouse_name'],
                        $row['location_name'] ?? '-',
                        $typeLabel,
                        $formatNumber($row['quantity']),
                        $row['unit_symbol'] ?? '',
                        $nitelik,
                        $row['user_name'],
                        $row['reference_no'] ?? '-',
                        $row['description'] ?? '-',
                    ], ';');
                }
                break;

            case 'history':
                $data = $this->report->getHistoricalSummary($filters);
                fputcsv($out, ['Tarih', 'Toplam İşlem Adedi', 'Giriş İşlem Adedi', 'Çıkış İşlem Adedi', 'Düzeltme İşlem Adedi', 'Toplam Giriş Miktarı', 'Toplam Çıkış Miktarı', 'Net Değişim'], ';');
                foreach ($data as $row) {
                    fputcsv($out, [
                        $row['movement_date'],
                        (int) $row['total_transactions'],
                        (int) $row['in_count'],
                        (int) $row['out_count'],
                        (int) $row['adj_count'],
                        $formatNumber($row['total_in_qty']),
                        $formatNumber($row['total_out_qty']),
                        $formatNumber($row['net_change_qty']),
                    ], ';');
                }
                break;

            case 'summary':
                $sum = $this->report->getSummary();
                fputcsv($out, ['Metrik', 'Değer'], ';');
                fputcsv($out, ['Toplam Aktif Malzeme', $sum['total_materials']], ';');
                fputcsv($out, ['Toplam Aktif Depo', $sum['total_warehouses']], ';');
                fputcsv($out, ['Toplam Stok Miktarı', $formatNumber($sum['total_stock'])], ';');
                fputcsv($out, ['Kritik Stoktaki Malzeme Sayısı', $sum['critical_stock_count']], ';');
                fputcsv($out, ['Toplam Stok Girişi', $formatNumber($sum['movement_totals']['total_in'] ?? 0)], ';');
                fputcsv($out, ['Toplam Stok Çıkışı', $formatNumber($sum['movement_totals']['total_out'] ?? 0)], ';');
                break;
        }

        fclose($out);
        exit;
    }

    private function readFilters(): array
    {
        $startDate = trim($_GET['start_date'] ?? '');
        $endDate = trim($_GET['end_date'] ?? '');

        if ($startDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = '';
        }
        if ($endDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $endDate = '';
        }

        $categoryId = !empty($_GET['category_id']) ? (int) $_GET['category_id'] : null;
        if ($categoryId !== null && $categoryId <= 0) {
            $categoryId = null;
        }

        $warehouseId = !empty($_GET['warehouse_id']) ? (int) $_GET['warehouse_id'] : null;
        if ($warehouseId !== null && $warehouseId <= 0) {
            $warehouseId = null;
        }

        $locationId = !empty($_GET['location_id']) ? (int) $_GET['location_id'] : null;
        if ($locationId !== null && $locationId <= 0) {
            $locationId = null;
        }

        $materialId = !empty($_GET['material_id']) ? (int) $_GET['material_id'] : null;
        if ($materialId !== null && $materialId <= 0) {
            $materialId = null;
        }

        $userId = !empty($_GET['user_id']) ? (int) $_GET['user_id'] : null;
        if ($userId !== null && $userId <= 0) {
            $userId = null;
        }

        $allowedMovementTypes = ['IN', 'OUT', 'TRANSFER_IN', 'TRANSFER_OUT', 'RETURN', 'ADJUSTMENT'];
        $movementType = trim($_GET['movement_type'] ?? '');
        if (!in_array($movementType, $allowedMovementTypes, true)) {
            $movementType = '';
        }

        $status = trim($_GET['status'] ?? '');
        $allowedStatuses = ['out_of_stock', 'critical', 'overstock', 'normal', 'Tükendi', 'Kritik', 'Fazla Stok', 'Normal'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $isScrap = trim((string) ($_GET['is_scrap'] ?? ''));
        if (!in_array($isScrap, ['1', '0', 'yes', 'no'], true)) {
            $isScrap = '';
        }

        $search = trim($_GET['search'] ?? '');
        if (mb_strlen($search) > 100) {
            $search = mb_substr($search, 0, 100);
        }

        return [
            'start_date'    => $startDate !== '' ? $startDate : null,
            'end_date'      => $endDate !== '' ? $endDate : null,
            'category_id'   => $categoryId,
            'warehouse_id'  => $warehouseId,
            'location_id'   => $locationId,
            'material_id'   => $materialId,
            'movement_type' => $movementType !== '' ? $movementType : null,
            'user_id'       => $userId,
            'status'        => $status !== '' ? $status : null,
            'is_scrap'      => $isScrap !== '' ? $isScrap : null,
            'search'        => $search !== '' ? $search : null,
        ];
    }
}

