<?php
$pageTitle = 'Denetim İzi (Audit Logs)';
$activePage = 'audit-logs';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content" style="max-width: 1360px; padding: 24px 32px;">

    <!-- HEADER -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; background: #ffffff; padding: 20px 24px; border-radius: 14px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 22px;">🛡️</span>
                <h1 style="font-size: 22px; font-weight: 800; color: #0f172a; margin: 0;">Kurumsal Denetim İzi (Audit Log)</h1>
            </div>
            <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 30px;">
                Sistem genelinde gerçekleşen tüm kullanıcı girişleri, kritik veri değişiklikleri ve güvenlik olayları.
            </p>
        </div>

        <div style="display: flex; gap: 8px;">
            <a href="/stok-takip/public/audit-logs" class="button" style="padding: 7px 14px; font-size: 12.5px; font-weight: 700; background: #f1f5f9; color: #475569; text-decoration: none;">
                ↻ Yenile
            </a>
        </div>
    </div>

    <!-- FILTERS -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <form method="GET" action="/stok-takip/public/audit-logs" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            
            <div style="flex: 1; min-width: 150px;">
                <label style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 4px;">Modül</label>
                <select name="module" style="width: 100%; padding: 7px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff;">
                    <option value="">Tüm Modüller</option>
                    <option value="AUTH" <?= ($filters['module'] ?? '') === 'AUTH' ? 'selected' : '' ?>>Oturum / AUTH</option>
                    <option value="SECURITY" <?= ($filters['module'] ?? '') === 'SECURITY' ? 'selected' : '' ?>>Güvenlik / SECURITY</option>
                    <option value="STOCK" <?= ($filters['module'] ?? '') === 'STOCK' ? 'selected' : '' ?>>Stok / Hareketler</option>
                    <option value="MATERIAL" <?= ($filters['module'] ?? '') === 'MATERIAL' ? 'selected' : '' ?>>Malzeme Yönetimi</option>
                    <option value="MES" <?= ($filters['module'] ?? '') === 'MES' ? 'selected' : '' ?>>MES / Üretim</option>
                    <option value="QUALITY" <?= ($filters['module'] ?? '') === 'QUALITY' ? 'selected' : '' ?>>Kalite Kontrol</option>
                    <option value="SHIPMENT" <?= ($filters['module'] ?? '') === 'SHIPMENT' ? 'selected' : '' ?>>Sevkiyat</option>
                    <option value="ROLE_PERMISSION" <?= ($filters['module'] ?? '') === 'ROLE_PERMISSION' ? 'selected' : '' ?>>Rol &amp; İzinler</option>
                    <option value="API_TOKEN" <?= ($filters['module'] ?? '') === 'API_TOKEN' ? 'selected' : '' ?>>API Anahtarları</option>
                </select>
            </div>

            <div style="flex: 1.5; min-width: 180px;">
                <label style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 4px;">Olay / Aksiyon</label>
                <input type="text" name="action" value="<?= htmlspecialchars($filters['action'] ?? '') ?>" placeholder="Örn: LOGIN_SUCCESS, STOCK_..." style="width: 100%; padding: 7px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box;">
            </div>

            <div style="flex: 1; min-width: 140px;">
                <label style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 4px;">Başlangıç</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($filters['start_date'] ?? '') ?>" style="width: 100%; padding: 6px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box;">
            </div>

            <div style="flex: 1; min-width: 140px;">
                <label style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 4px;">Bitiş</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($filters['end_date'] ?? '') ?>" style="width: 100%; padding: 6px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box;">
            </div>

            <div style="align-self: flex-end; display: flex; gap: 8px;">
                <button type="submit" class="button button-primary" style="padding: 8px 16px; font-size: 13px; font-weight: 700;">Filtrele</button>
                <a href="/stok-takip/public/audit-logs" class="button" style="padding: 8px 12px; font-size: 13px; background: #e2e8f0; color: #475569; text-decoration: none;">Temizle</a>
            </div>

        </form>
    </div>

    <!-- LOGS TABLE -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); overflow: hidden;">
        
        <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size: 14px; font-weight: 700; color: #0f172a;">
                Kayıtlar <span style="font-size: 12px; color: #64748b; font-weight: normal;">(Toplam <?= number_format($totalLogs) ?> işlem bulundu)</span>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; width: 60px;">ID</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; width: 145px;">Zaman</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; width: 130px;">Kullanıcı</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; width: 110px;">Modül</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; width: 180px;">Aksiyon</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569;">Açıklama / Detay</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; width: 110px;">IP Adresi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" style="padding: 30px; text-align: center; color: #64748b;">
                                Seçilen filtrelere uygun denetim kaydı bulunamadı.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): 
                            $isDenied = str_contains($log['action'], 'DENIED') || str_contains($log['action'], 'FAILED');
                            $isWarn = str_contains($log['action'], 'DELETE') || str_contains($log['action'], 'REVOKED');
                        ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; <?= $isDenied ? 'background: #fff1f2;' : '' ?>">
                                <td style="padding: 12px 16px; font-family: monospace; color: #64748b; font-size: 12px;">
                                    #<?= (int)$log['id'] ?>
                                </td>
                                <td style="padding: 12px 16px; color: #334155; font-size: 12px; white-space: nowrap;">
                                    <?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <?php if (!empty($log['username'])): ?>
                                        <div style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($log['username']) ?></div>
                                        <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($log['role_name'] ?? 'Kullanıcı') ?></div>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-style: italic;">Anonim / Sistem</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; background: #e2e8f0; color: #334155;">
                                        <?= htmlspecialchars($log['module'] ?? 'SYSTEM') ?>
                                    </span>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <span style="font-weight: 700; font-family: monospace; font-size: 12px; color: <?= $isDenied ? '#dc2626' : ($isWarn ? '#d97706' : '#2563eb') ?>;">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </span>
                                </td>
                                <td style="padding: 12px 16px; color: #334155; line-height: 1.4;">
                                    <?= htmlspecialchars($log['description'] ?? '-') ?>
                                    <?php if (!empty($log['new_values'])): ?>
                                        <details style="margin-top: 4px; font-size: 11.5px; color: #64748b;">
                                            <summary style="cursor: pointer; color: #2563eb;">Veri Detayı</summary>
                                            <pre style="background: #f8fafc; padding: 6px 8px; border-radius: 4px; font-size: 11px; margin: 4px 0 0 0; overflow-x: auto;"><?= htmlspecialchars($log['new_values']) ?></pre>
                                        </details>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px 16px; font-family: monospace; font-size: 12px; color: #64748b; white-space: nowrap;">
                                    <?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <?php if ($totalPages > 1): ?>
            <div style="padding: 14px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 12.5px; color: #64748b;">
                    Sayfa <?= $page ?> / <?= $totalPages ?> (Toplam <?= number_format($totalLogs) ?> kayıt)
                </span>
                <div style="display: flex; gap: 6px;">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="button button-small">&larr; Önceki</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="button button-small">Sonraki &rarr;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>

</main>

