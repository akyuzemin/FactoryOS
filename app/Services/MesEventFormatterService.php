<?php

class MesEventFormatterService
{
    /**
     * Format a single MES event into human-friendly representation.
     */
    public static function formatEvent(array $event, int $sequenceNumber = 0): array
    {
        $status = strtoupper((string)($event['status'] ?? 'PENDING'));
        $qty = (float)($event['quantity'] ?? 1.0);
        $timeRaw = $event['event_time'] ?? ($event['created_at'] ?? date('Y-m-d H:i:s'));
        $timeFormatted = date('H:i:s', strtotime($timeRaw));
        $dateFormatted = date('d.m.Y', strtotime($timeRaw));
        $eventId = $event['event_id'] ?? '';
        $source = $event['source'] ?? 'MES';

        $seqDisplay = $sequenceNumber > 0 ? "#{$sequenceNumber}" : ($qty > 1 ? sprintf('%.0f Adet', $qty) : 'Panel');

        if ($status === 'PROCESSED' || $status === 'SUCCESS') {
            $serialNo = $event['serial_no'] ?? ($event['serial_numbers'][0] ?? null);
            $stockRef = $event['stock_movement_ref'] ?? null;
            $consumedItems = $event['consumed_items'] ?? [];

            return [
                'event_id'           => $eventId,
                'level'              => 'SUCCESS',
                'category'           => 'success',
                'title'              => sprintf('Panel %s üretildi', $seqDisplay),
                'quantity_badge'     => sprintf('+%.0f Panel', $qty),
                'status_label'       => '✓ Başarılı',
                'status_badge'       => [
                    'bg'     => '#f0fdf4',
                    'color'  => '#166534',
                    'border' => '#bbf7d0'
                ],
                'time_human'         => $timeFormatted,
                'date_human'         => $dateFormatted,
                'details'            => '✓ Hammadde stoğu düşüldü • ✓ Mamul stoğa eklendi',
                'source'             => $source,
                'raw_status'         => $status,
                'serial_no'          => $serialNo,
                'stock_movement_ref' => $stockRef,
                'consumed_items'     => $consumedItems
            ];
        }

        if ($status === 'FAILED' || $status === 'ERROR') {
            $errorRaw = (string)($event['error_message'] ?? ($event['message'] ?? ($event['last_error'] ?? '')));
            
            // Translate error into human friendly text
            $errorInfo = self::translateError($errorRaw, $seqDisplay);

            return [
                'event_id'         => $eventId,
                'level'            => $errorInfo['level'],
                'category'         => $errorInfo['category'],
                'title'            => $errorInfo['title'],
                'quantity_badge'   => sprintf('%.0f Panel', $qty),
                'status_label'     => $errorInfo['status_label'],
                'status_badge'     => $errorInfo['badge'],
                'time_human'       => $timeFormatted,
                'date_human'       => $dateFormatted,
                'details'          => $errorInfo['details'],
                'source'           => $source,
                'raw_status'       => $status
            ];
        }

        // Default / Warning / Already processed / Pending
        return [
            'event_id'         => $eventId,
            'level'            => 'INFO',
            'category'         => 'info',
            'title'            => sprintf('Panel %s işleniyor', $seqDisplay),
            'quantity_badge'   => sprintf('%.0f Panel', $qty),
            'status_label'     => 'İşleniyor',
            'status_badge'     => [
                'bg'     => '#eff6ff',
                'color'  => '#1e40af',
                'border' => '#bfdbfe'
            ],
            'time_human'       => $timeFormatted,
            'date_human'       => $dateFormatted,
            'details'          => 'MES üretim olayı sıraya alındı.',
            'source'           => $source,
            'raw_status'       => $status
        ];
    }

    /**
     * Translate technical errors into clear, operator-friendly Turkish text.
     */
    public static function translateError(string $rawError, string $seqDisplay = ''): array
    {
        $err = mb_strtolower($rawError, 'UTF-8');

        if (str_contains($err, 'yetersiz') || str_contains($err, 'insufficient') || str_contains($err, 'stok')) {
            // Clean up missing item details if present
            $details = 'BOM hammadde stoğu yetersiz olduğu için üretim güvenli şekilde durduruldu.';
            if (preg_match('/Yetersiz hammadde stoğu:\s*(.*)/i', $rawError, $m)) {
                $details = 'Yetersiz Stok: ' . htmlspecialchars($m[1]);
            }
            return [
                'level'        => 'ERROR',
                'category'     => 'error',
                'title'        => sprintf('⚠ Üretim durduruldu (Panel %s üretilemedi)', $seqDisplay),
                'status_label' => 'Yetersiz Stok',
                'badge'        => ['bg' => '#fef2f2', 'color' => '#991b1b', 'border' => '#fecaca'],
                'details'      => $details
            ];
        }

        if (str_contains($err, 'duplicate_panel') || str_contains($err, 'mükerrer panel')) {
            return [
                'level'        => 'ERROR',
                'category'     => 'error',
                'title'        => 'Mükerrer Panel Seri No',
                'status_label' => 'Mükerrer Panel',
                'badge'        => ['bg' => '#fef2f2', 'color' => '#991b1b', 'border' => '#fecaca'],
                'details'      => 'Bu panel seri numarası ile daha önce mamul kaydı yapılmış.'
            ];
        }

        if (str_contains($err, 'duplicate') || str_contains($err, 'mükerrer') || str_contains($err, 'already_processed')) {
            return [
                'level'        => 'WARNING',
                'category'     => 'warning',
                'title'        => 'Mükerrer Üretim Sinyali Atlandı',
                'status_label' => 'Mükerrer',
                'badge'        => ['bg' => '#fffbeb', 'color' => '#92400e', 'border' => '#fde68a'],
                'details'      => 'Aynı üretim sinyali daha önce işlenmişti. Mükerrer stok hareketi engellendi.'
            ];
        }

        if (str_contains($err, 'target_reached') || str_contains($err, 'hedef') || str_contains($err, 'completed')) {
            return [
                'level'        => 'INFO',
                'category'     => 'info',
                'title'        => 'Hedef Adede Ulaşıldı',
                'status_label' => 'Hedef Tamam',
                'badge'        => ['bg' => '#f8fafc', 'color' => '#334155', 'border' => '#cbd5e1'],
                'details'      => 'İş emri hedefine ulaştı. Fazladan üretim engellendi.'
            ];
        }

        if (str_contains($err, 'product') || str_contains($err, 'ürün')) {
            return [
                'level'        => 'ERROR',
                'category'     => 'error',
                'title'        => 'Ürün Uyuşmazlığı',
                'status_label' => 'Ürün Hatası',
                'badge'        => ['bg' => '#fef2f2', 'color' => '#991b1b', 'border' => '#fecaca'],
                'details'      => 'Eventteki ürün bilgisi iş emriyle uyuşmuyor.'
            ];
        }

        if (str_contains($err, 'line_not_available') || str_contains($err, 'bakımda') || str_contains($err, 'maintenance') || str_contains($err, 'arızada') || str_contains($err, 'fault')) {
            return [
                'level'        => 'ERROR',
                'category'     => 'error',
                'title'        => '🔴 Hat Üretime Uygun Değil',
                'status_label' => 'Hat Kapalı',
                'badge'        => ['bg' => '#fef2f2', 'color' => '#991b1b', 'border' => '#fecaca'],
                'details'      => $rawError ?: 'Üretim hattı bakımda veya arızada olduğu için üretim yapılamaz.'
            ];
        }

        if (str_contains($err, 'line_busy') || str_contains($err, 'zaten aktif bir üretim')) {
            return [
                'level'        => 'WARNING',
                'category'     => 'warning',
                'title'        => '⚠ Hat Meşgul',
                'status_label' => 'Hat Meşgul',
                'badge'        => ['bg' => '#fffbeb', 'color' => '#92400e', 'border' => '#fde68a'],
                'details'      => $rawError ?: 'Bu üretim hattında zaten aktif çalışan bir iş emri bulunuyor.'
            ];
        }

        if (str_contains($err, 'line') || str_contains($err, 'hat')) {
            return [
                'level'        => 'ERROR',
                'category'     => 'error',
                'title'        => 'Üretim Hattı Uyuşmazlığı',
                'status_label' => 'Hat Hatası',
                'badge'        => ['bg' => '#fef2f2', 'color' => '#991b1b', 'border' => '#fecaca'],
                'details'      => 'Eventin gönderildiği üretim hattı iş emrine ait değil.'
            ];
        }

        if (str_contains($err, 'retry_exhausted') || str_contains($err, '3 denemede') || str_contains($err, 'limiti aşıldı')) {
            return [
                'level'        => 'ERROR',
                'category'     => 'error',
                'title'        => '🔴 Üretim Durduruldu',
                'status_label' => 'Retry Aşıldı',
                'badge'        => ['bg' => '#fef2f2', 'color' => '#991b1b', 'border' => '#fecaca'],
                'details'      => 'Geçici sistem hatası 3 denemede çözülemediği için üretim durduruldu.'
            ];
        }

        if (str_contains($err, 'retry_scheduled') || str_contains($err, 'yeniden deneniyor') || str_contains($err, 'temporary') || str_contains($err, 'transient')) {
            return [
                'level'        => 'WARNING',
                'category'     => 'warning',
                'title'        => '⚠ Geçici Sistem Problemi',
                'status_label' => 'Tekrar Deneniyor',
                'badge'        => ['bg' => '#fffbeb', 'color' => '#92400e', 'border' => '#fde68a'],
                'details'      => $rawError ?: 'Geçici sistem problemi nedeniyle üretim yeniden deneniyor.'
            ];
        }

        if (str_contains($err, 'recipe') || str_contains($err, 'reçete') || str_contains($err, 'bom')) {
            return [
                'level'        => 'ERROR',
                'category'     => 'error',
                'title'        => 'BOM Reçete Hatası',
                'status_label' => 'Reçete Hatası',
                'badge'        => ['bg' => '#fef2f2', 'color' => '#991b1b', 'border' => '#fecaca'],
                'details'      => 'İş emrine ait BOM reçetesi veya hammadde kalemleri eksik.'
            ];
        }

        return [
            'level'        => 'ERROR',
            'category'     => 'error',
            'title'        => sprintf('Üretim Hatası (Panel %s)', $seqDisplay),
            'status_label' => 'Hata',
            'badge'        => ['bg' => '#fef2f2', 'color' => '#991b1b', 'border' => '#fecaca'],
            'details'      => $rawError ?: 'Bilinmeyen üretim entegrasyon hatası.'
        ];
    }

    /**
     * Format a collection of events with sequence numbers and summary.
     */
    public static function formatEventList(array $events, float $totalProduced = 0): array
    {
        $total = count($events);
        $formatted = [];

        foreach ($events as $idx => $ev) {
            // Sequence number: newest event has highest seq number up to totalProduced
            $seq = max(1, (int)round($totalProduced) - $idx);
            $formattedItem = self::formatEvent($ev, $seq);
            $formatted[] = $formattedItem;
        }

        return $formatted;
    }

    /**
     * Create a concise "Son İşlem" summary object.
     */
    public static function getLatestActionSummary(array $formattedEvents, ?string $lastError = null): array
    {
        if (!empty($lastError)) {
            $errTrans = self::translateError($lastError);
            return [
                'level'    => 'ERROR',
                'icon'     => '⚠',
                'title'    => 'Üretim duraklatıldı',
                'time'     => date('H:i:s'),
                'subtitle' => $errTrans['details'],
                'bg'       => '#fef2f2',
                'color'    => '#991b1b',
                'border'   => '#fecaca'
            ];
        }

        if (empty($formattedEvents)) {
            return [
                'level'    => 'INFO',
                'icon'     => 'ℹ',
                'title'    => 'Beklemede',
                'time'     => '-',
                'subtitle' => 'Henüz üretim işlemi başlamadı.',
                'bg'       => '#f8fafc',
                'color'    => '#475569',
                'border'   => '#e2e8f0'
            ];
        }

        $latest = $formattedEvents[0];
        if ($latest['level'] === 'SUCCESS') {
            return [
                'level'    => 'SUCCESS',
                'icon'     => '✓',
                'title'    => $latest['title'],
                'time'     => $latest['time_human'],
                'subtitle' => 'Hammadde tüketildi, mamul stoğa eklendi.',
                'bg'       => '#f0fdf4',
                'color'    => '#166534',
                'border'   => '#bbf7d0'
            ];
        }

        return [
            'level'    => $latest['level'],
            'icon'     => $latest['level'] === 'WARNING' ? '⚠' : '❌',
            'title'    => $latest['title'],
            'time'     => $latest['time_human'],
            'subtitle' => $latest['details'],
            'bg'       => $latest['status_badge']['bg'] ?? '#fef2f2',
            'color'    => $latest['status_badge']['color'] ?? '#991b1b',
            'border'   => $latest['status_badge']['border'] ?? '#fecaca'
        ];
    }
}
