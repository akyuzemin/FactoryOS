<?php

$pageTitle = 'Envanter Detayı';
$activePage = 'inventory';

$statusLabels = [
    'IN_STOCK' => 'Stokta',
    'ASSIGNED' => 'Tahsisli',
    'IN_USE' => 'Kullanımda',
    'IN_REPAIR' => 'Bakımda',
    'LOST' => 'Kayıp',
    'RETIRED' => 'Emekli',
    'DISPOSED' => 'Zayi',
];
$statusClasses = [
    'IN_STOCK' => 'status-badge-info',
    'ASSIGNED' => 'status-badge-warning',
    'IN_USE' => 'status-badge-success',
    'IN_REPAIR' => 'status-badge-warning',
    'LOST' => 'status-badge-danger',
    'RETIRED' => 'status-badge-inactive',
    'DISPOSED' => 'status-badge-danger',
];
$movementLabels = [
    'ASSIGN' => 'Tahsis',
    'TRANSFER' => 'Devir',
    'RETURN' => 'İade',
    'LOCATION_CHANGE' => 'Lokasyon Değişikliği',
    'STATUS_CHANGE' => 'Durum Değişikliği',
    'MAINTENANCE_SEND' => 'Bakıma Gönderildi',
    'MAINTENANCE_RETURN' => 'Bakımdan Döndü',
    'DISPOSAL' => 'Zayi',
];
$formatDate = static fn (?string $date): string => $date ? date('d.m.Y', strtotime($date)) : '-';
$formatDateTime = static fn (?string $date): string => $date ? date('d.m.Y H:i', strtotime($date)) : '-';
$display = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? '') ?: '-');
?>

<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/../layouts/sidebar.php'; ?>

<?php
$isAssignable = empty($asset['responsible_user_id']) && $asset['status'] === 'IN_STOCK' && (int) ($asset['is_active'] ?? 1) === 1;
$isTransferable = !empty($asset['responsible_user_id']) && $asset['status'] === 'ASSIGNED' && (int) ($asset['is_active'] ?? 1) === 1;
?>

<main class="main-content">
    <?php if (!empty($_SESSION['success'])): ?>
        <div style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; border-radius: 8px; background: #ecfdf5; border: 1px solid #10b981; color: #065f46; display: flex; align-items: center; gap: 0.5rem; font-size: 13.5px; font-weight: 600;">
            <span style="font-size: 16px;">✅</span>
            <span><?= htmlspecialchars($_SESSION['success']) ?></span>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
        <div style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; border-radius: 8px; background: #fef2f2; border: 1px solid #ef4444; color: #991b1b; display: flex; align-items: center; gap: 0.5rem; font-size: 13.5px; font-weight: 600;">
            <span style="font-size: 16px;">⚠️</span>
            <span><?= htmlspecialchars($_SESSION['error']) ?></span>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <header class="page-header">
        <div>
            <p class="page-kicker">Varlık Yönetimi</p>
            <h1><?= $display($asset['asset_name']) ?></h1>
            <p class="page-description"><span class="code-badge"><?= $display($asset['asset_code']) ?></span> envanter detay ve geçmiş görünümü</p>
        </div>
        <div class="header-badges inventory-header-actions" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <a class="button" href="/stok-takip/public/inventory">Listeye Dön</a>
            <?php if ($can('inventory.update') && $isAssignable): ?>
                <button type="button" class="button button-success" style="background: #10b981; border-color: #059669; color: #ffffff; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;" onclick="openAssignModal()">
                    <span>📋</span> Varlık Tahsis Et
                </button>
            <?php endif; ?>
            <?php if ($can('inventory.update') && $isTransferable): ?>
                <button type="button" class="button" style="background: #0284c7; border-color: #0369a1; color: #ffffff; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;" onclick="openTransferModal()">
                    <span>🔄</span> Varlığı Devret
                </button>
                <button type="button" class="button" style="background: #d97706; border-color: #b45309; color: #ffffff; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;" onclick="openReturnModal()">
                    <span>↩️</span> Varlığı İade Al
                </button>
            <?php endif; ?>
            <?php if ($can('inventory.update')): ?>
                <a class="button button-primary" href="/stok-takip/public/inventory/edit?id=<?= (int) $asset['id'] ?>">Düzenle</a>
            <?php endif; ?>
        </div>
    </header>

    <section class="inventory-detail-grid">
        <div class="inventory-detail-card">
            <h2>Genel Bilgiler</h2>
            <dl class="inventory-detail-list">
                <div><dt>Varlık Kodu</dt><dd><?= $display($asset['asset_code']) ?></dd></div>
                <div><dt>Varlık Adı</dt><dd><?= $display($asset['asset_name']) ?></dd></div>
                <div><dt>Kategori</dt><dd><?= $display($asset['category_name']) ?></dd></div>
                <div><dt>Seri No</dt><dd><?= $display($asset['serial_no']) ?></dd></div>
                <div><dt>Marka / Model</dt><dd><?= $display(trim(($asset['manufacturer'] ?? '') . ' ' . ($asset['model_no'] ?? ''))) ?></dd></div>
                <div><dt>Durum</dt><dd><span class="status-badge <?= htmlspecialchars($statusClasses[$asset['status']] ?? 'status-badge-info') ?>"><?= $display($statusLabels[$asset['status']] ?? $asset['status']) ?></span></dd></div>
            </dl>
        </div>

        <div class="inventory-detail-card">
            <h2>Satın Alma ve Garanti</h2>
            <dl class="inventory-detail-list">
                <div><dt>Tedarikçi</dt><dd><?= $display($asset['supplier_name']) ?></dd></div>
                <div><dt>Satın Alma Tarihi</dt><dd><?= $formatDate($asset['purchase_date']) ?></dd></div>
                <div><dt>Maliyet</dt><dd><?= number_format((float) $asset['purchase_cost'], 2, ',', '.') ?> <?= $display($asset['currency']) ?></dd></div>
                <div><dt>Fatura No</dt><dd><?= $display($asset['invoice_no']) ?></dd></div>
                <div><dt>Garanti Başlangıcı</dt><dd><?= $formatDate($asset['warranty_start_date']) ?></dd></div>
                <div><dt>Garanti Bitişi</dt><dd><?= $formatDate($asset['warranty_end_date']) ?></dd></div>
            </dl>
        </div>

        <div class="inventory-detail-card">
            <h2>Konum ve Sorumlu</h2>
            <dl class="inventory-detail-list">
                <div><dt>Depo</dt><dd><?= $display($asset['warehouse_name']) ?></dd></div>
                <div><dt>Lokasyon</dt><dd><?= $display($asset['location_name']) ?></dd></div>
                <div><dt>Üretim Hattı</dt><dd><?= $display($asset['production_line_name']) ?></dd></div>
                <div><dt>Sorumlu Kullanıcı</dt><dd><?= !empty($asset['responsible_name']) ? ('<strong style="color:#0f172a;">' . $display($asset['responsible_name']) . '</strong>') : '<span style="color:#94a3b8; font-style:italic;">Zimmetli Değil (Boşta)</span>' ?></dd></div>
            </dl>
        </div>

        <div class="inventory-detail-card">
            <h2>Bakım Bağlantısı</h2>
            <?php if (!empty($asset['maintenance_asset_id'])): ?>
                <dl class="inventory-detail-list">
                    <div><dt>Bakım Varlığı</dt><dd><?= $display($asset['maintenance_asset_code']) ?></dd></div>
                    <div><dt>Varlık Adı</dt><dd><?= $display($asset['maintenance_asset_name']) ?></dd></div>
                </dl>
                <?php if ($can('maintenance.view')): ?><a class="button button-small" href="/stok-takip/public/maintenance/asset?id=<?= (int) $asset['maintenance_asset_id'] ?>">Bakım Geçmişini Gör</a><?php endif; ?>
            <?php else: ?>
                <p class="inventory-muted">Bu envanter varlığı bir bakım varlığına bağlanmamış.</p>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!empty($asset['description'])): ?>
        <section class="inventory-detail-card inventory-description-card">
            <h2>Açıklama</h2>
            <p><?= nl2br($display($asset['description'])) ?></p>
        </section>
    <?php endif; ?>

    <section class="inventory-detail-card inventory-history-card">
        <div class="section-heading">
            <div><p class="page-kicker">İzlenebilirlik</p><h2>Hareket Geçmişi</h2></div>
            <span class="section-meta"><?= count($movements) ?> kayıt</span>
        </div>
        <div class="table-shell">
            <table class="materials-table inventory-history-table">
                <thead><tr><th>Tarih</th><th>İşlem</th><th>Durum</th><th>Sorumlu</th><th>Konum</th><th>İşlemi Yapan</th><th>Açıklama</th></tr></thead>
                <tbody>
                <?php if (empty($movements)): ?>
                    <tr><td colspan="7" class="empty-state">Bu varlık için henüz hareket kaydı bulunmuyor.</td></tr>
                <?php else: foreach ($movements as $movement): ?>
                    <?php
                    $mType = $movement['movement_type'] ?? '';
                    $mBadgeStyle = match($mType) {
                        'ASSIGN' => 'background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;',
                        'TRANSFER' => 'background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;',
                        'RETURN' => 'background: #fef3c7; color: #b45309; border: 1px solid #fde68a;',
                        default => 'background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;',
                    };

                    $responsibleDisplay = '-';
                    if ($mType === 'TRANSFER' && !empty($movement['from_user_name']) && !empty($movement['to_user_name'])) {
                        $responsibleDisplay = htmlspecialchars($movement['from_user_name']) . ' → <strong style="color: #0369a1;">' . htmlspecialchars($movement['to_user_name']) . '</strong>';
                    } elseif ($mType === 'RETURN' && !empty($movement['from_user_name'])) {
                        $responsibleDisplay = htmlspecialchars($movement['from_user_name']) . ' → <span style="color:#94a3b8; font-style:italic;">-</span>';
                    } elseif (!empty($movement['to_user_name'])) {
                        $responsibleDisplay = htmlspecialchars($movement['to_user_name']);
                    } elseif (!empty($movement['from_user_name'])) {
                        $responsibleDisplay = htmlspecialchars($movement['from_user_name']);
                    }

                    $fromLocText = trim(($movement['from_warehouse_name'] ?? '') . ' / ' . ($movement['from_location_name'] ?? ''), ' /');
                    $toLocText = trim(($movement['to_warehouse_name'] ?? '') . ' / ' . ($movement['to_location_name'] ?? ''), ' /');
                    if ($mType === 'RETURN' && $fromLocText && $toLocText && $fromLocText !== $toLocText) {
                        $locationDisplay = htmlspecialchars($fromLocText) . ' → <strong>' . htmlspecialchars($toLocText) . '</strong>';
                    } else {
                        $locationDisplay = htmlspecialchars($toLocText ?: $fromLocText ?: '-');
                    }
                    ?>
                    <tr>
                        <td class="movement-date"><?= $formatDateTime($movement['movement_date']) ?></td>
                        <td><span class="movement-type" style="<?= $mBadgeStyle ?> padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 11.5px;"><?= $display($movementLabels[$mType] ?? $mType) ?></span></td>
                        <td><?= $display($statusLabels[$movement['from_status']] ?? ($movement['from_status'] ?: '-')) ?> → <strong><?= $display($statusLabels[$movement['to_status']] ?? ($movement['to_status'] ?: '-')) ?></strong></td>
                        <td><?= $responsibleDisplay ?></td>
                        <td><?= $locationDisplay ?></td>
                        <td><?= $display($movement['performed_by_name']) ?></td>
                        <td>
                            <?php if (!empty($movement['reference_no'])): ?>
                                <span style="font-family: monospace; font-size: 11px; background: #f1f5f9; padding: 2px 5px; border-radius: 4px; font-weight: 600; color: #475569;"><?= $display($movement['reference_no']) ?></span>
                            <?php endif; ?>
                            <?= $display($movement['reason'] ?: $movement['notes']) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($can('inventory.update') && $isAssignable): ?>
        <!-- VARLIK TAHSİS ET MODAL -->
        <div id="assignModalBackdrop" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px); z-index: 9999; align-items: center; justify-content: center; padding: 16px;">
            <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1); width: 100%; max-width: 580px; overflow: hidden; animation: modalFadeIn 0.15s ease-out;">
                
                <!-- Modal Başlık -->
                <div style="padding: 18px 22px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                            <span>📋</span> Varlık Tahsis Et (Zimmetle)
                        </h3>
                        <p style="margin: 3px 0 0 0; font-size: 12.5px; color: #64748b;">
                            <strong style="color: #0f172a;"><?= $display($asset['asset_name']) ?></strong> (<?= $display($asset['asset_code']) ?>)
                        </p>
                    </div>
                    <button type="button" onclick="closeAssignModal()" style="background: transparent; border: none; font-size: 20px; line-height: 1; color: #94a3b8; cursor: pointer; padding: 4px 8px; border-radius: 6px;" title="Kapat">✕</button>
                </div>

                <!-- Modal Form -->
                <form action="/stok-takip/public/inventory/assign" method="POST" style="padding: 22px;">
                    <?= CsrfService::tokenField() ?>
                    <input type="hidden" name="asset_id" value="<?= (int) $asset['id'] ?>">

                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                                Tahsis Edilecek Çalışan / Kullanıcı <span style="color: #ef4444;">*</span>
                            </label>
                            <select name="to_user_id" required style="width: 100%; height: 42px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #ffffff; color: #0f172a;">
                                <option value="">-- Çalışan Seçiniz --</option>
                                <?php foreach ($activeUsers ?? [] as $user): ?>
                                    <option value="<?= (int) $user['user_id'] ?>">
                                        <?= htmlspecialchars($user['full_name']) ?> — <?= htmlspecialchars($user['registration_no'] ?: ('@' . $user['username'])) ?> — <?= htmlspecialchars($user['department_name'] ?: 'Departmansız') ?> (<?= htmlspecialchars($user['position_name'] ?: 'Pozisyonsuz') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                                    Tahsis Tarihi <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="date" name="movement_date" value="<?= date('Y-m-d') ?>" required style="width: 100%; height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #ffffff; color: #0f172a;">
                            </div>

                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                                    Zimmet / Tutanak No <span style="font-size: 11px; color: #94a3b8; font-weight: normal;">(Opsiyonel)</span>
                                </label>
                                <input type="text" name="reference_no" placeholder="Örn: ZMT-<?= date('Y') ?>-001" maxlength="50" style="width: 100%; height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #ffffff; color: #0f172a;">
                            </div>
                        </div>

                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                                Tahsis Gerekçesi <span style="font-size: 11px; color: #94a3b8; font-weight: normal;">(Opsiyonel)</span>
                            </label>
                            <input type="text" name="reason" placeholder="Örn: Göreve başlama / Yeni ekipman tahsisi" maxlength="255" style="width: 100%; height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #ffffff; color: #0f172a;">
                        </div>

                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                                Ek Notlar &amp; Teslim Şartları <span style="font-size: 11px; color: #94a3b8; font-weight: normal;">(Opsiyonel)</span>
                            </label>
                            <textarea name="notes" rows="3" placeholder="Teslim edilen aksesuarlar, cihazın fiziksel durumu veya ek açıklamalar..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #ffffff; color: #0f172a; resize: vertical;"></textarea>
                        </div>
                    </div>

                    <!-- Modal Butonları -->
                    <div style="margin-top: 22px; padding-top: 16px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="button" onclick="closeAssignModal()" style="padding: 0 16px; height: 38px; cursor: pointer;">
                            İptal
                        </button>
                        <button type="submit" class="button button-success" style="background: #10b981; border-color: #059669; color: #ffffff; font-weight: 700; padding: 0 18px; height: 38px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                            <span>💾</span> Varlığı Tahsis Et
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function openAssignModal() {
                var modal = document.getElementById('assignModalBackdrop');
                if (modal) {
                    modal.style.display = 'flex';
                }
            }

            function closeAssignModal() {
                var modal = document.getElementById('assignModalBackdrop');
                if (modal) {
                    modal.style.display = 'none';
                }
            }
        </script>
    <?php endif; ?>

    <?php if ($can('inventory.update') && $isTransferable): ?>
        <!-- VARLIK DEVRET (TRANSFER) MODAL -->
        <div id="transferModalBackdrop" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px); z-index: 9999; align-items: center; justify-content: center; padding: 16px;">
            <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1); width: 100%; max-width: 580px; overflow: hidden; animation: modalFadeIn 0.15s ease-out;">
                
                <!-- Modal Başlık -->
                <div style="padding: 18px 22px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                            <span>🔄</span> Varlık Devret (Sorumluluk Transferi)
                        </h3>
                        <p style="margin: 3px 0 0 0; font-size: 12.5px; color: #64748b;">
                            <strong style="color: #0f172a;"><?= $display($asset['asset_name']) ?></strong> (<?= $display($asset['asset_code']) ?>)
                        </p>
                    </div>
                    <button type="button" onclick="closeTransferModal()" style="background: transparent; border: none; font-size: 20px; line-height: 1; color: #94a3b8; cursor: pointer; padding: 4px 8px; border-radius: 6px;" title="Kapat">✕</button>
                </div>

                <!-- Mevcut Sorumlu Bilgilendirme Kartı -->
                <div style="background: #f0f9ff; border-bottom: 1px solid #e0f2fe; padding: 14px 22px; display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 22px;">👤</span>
                    <div style="font-size: 12.5px;">
                        <div style="color: #0369a1; font-weight: 600;">Mevcut Sorumlu:</div>
                        <div style="color: #0c4a6e; font-weight: 800; font-size: 13.5px;">
                            <?= $display($asset['responsible_name']) ?>
                        </div>
                    </div>
                </div>

                <!-- Modal Form -->
                <form action="/stok-takip/public/inventory/transfer" method="POST" style="padding: 22px;">
                    <?= CsrfService::tokenField() ?>
                    <input type="hidden" name="asset_id" value="<?= (int) $asset['id'] ?>">

                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                                Yeni Sorumlu Çalışan / Kullanıcı <span style="color: #ef4444;">*</span>
                            </label>
                            <select name="to_user_id" required style="width: 100%; height: 42px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #ffffff; color: #0f172a;">
                                <option value="">-- Yeni Çalışan Seçiniz --</option>
                                <?php foreach ($activeUsers ?? [] as $user): ?>
                                    <?php $isCurrent = ((int)$user['user_id'] === (int)($asset['responsible_user_id'] ?? 0)); ?>
                                    <option value="<?= (int) $user['user_id'] ?>" <?= $isCurrent ? 'disabled style="color:#94a3b8;"' : '' ?>>
                                        <?= htmlspecialchars($user['full_name']) ?> — <?= htmlspecialchars($user['registration_no'] ?: ('@' . $user['username'])) ?> — <?= htmlspecialchars($user['department_name'] ?: 'Departmansız') ?> (<?= htmlspecialchars($user['position_name'] ?: 'Pozisyonsuz') ?>)
                                        <?= $isCurrent ? ' (Mevcut Sorumlu)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                                    Devir Tarihi <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="date" name="movement_date" value="<?= date('Y-m-d') ?>" required style="width: 100%; height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #ffffff; color: #0f172a;">
                            </div>

                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                                    Devir / Tutanak No <span style="font-size: 11px; color: #94a3b8; font-weight: normal;">(Opsiyonel)</span>
                                </label>
                                <input type="text" name="reference_no" placeholder="Örn: DVR-<?= date('Y') ?>-001" maxlength="50" style="width: 100%; height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #ffffff; color: #0f172a;">
                            </div>
                        </div>

                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                                Devir Nedeni / Gerekçesi <span style="font-size: 11px; color: #94a3b8; font-weight: normal;">(Opsiyonel)</span>
                            </label>
                            <input type="text" name="reason" placeholder="Örn: Görev değişimi / Vardiya nöbet devri" maxlength="255" style="width: 100%; height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #ffffff; color: #0f172a;">
                        </div>

                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                                Ek Notlar &amp; Teslim Durumu <span style="font-size: 11px; color: #94a3b8; font-weight: normal;">(Opsiyonel)</span>
                            </label>
                            <textarea name="notes" rows="3" placeholder="Cihazın teslim anındaki durumu, aksesuarları veya ek açıklamalar..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #ffffff; color: #0f172a; resize: vertical;"></textarea>
                        </div>
                    </div>

                    <!-- Modal Butonları -->
                    <div style="margin-top: 22px; padding-top: 16px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="button" onclick="closeTransferModal()" style="padding: 0 16px; height: 38px; cursor: pointer;">
                            İptal
                        </button>
                        <button type="submit" class="button" style="background: #0284c7; border-color: #0369a1; color: #ffffff; font-weight: 700; padding: 0 18px; height: 38px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                            <span>🔄</span> Varlığı Devret
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function openTransferModal() {
                var modal = document.getElementById('transferModalBackdrop');
                if (modal) {
                    modal.style.display = 'flex';
                }
            }

            function closeTransferModal() {
                var modal = document.getElementById('transferModalBackdrop');
                if (modal) {
                    modal.style.display = 'none';
                }
            }
        </script>

        <!-- VARLIK İADE AL (RETURN) MODAL -->
        <div id="returnModalBackdrop" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px); z-index: 9999; align-items: center; justify-content: center; padding: 16px;">
            <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1); width: 100%; max-width: 580px; overflow: hidden; animation: modalFadeIn 0.15s ease-out;">
                
                <!-- Modal Başlık -->
                <div style="padding: 18px 22px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                            <span>↩️</span> Varlığı İade Al (Depo Teslimi)
                        </h3>
                        <p style="margin: 3px 0 0 0; font-size: 12.5px; color: #64748b;">
                            <strong style="color: #0f172a;"><?= $display($asset['asset_name']) ?></strong> (<?= $display($asset['asset_code']) ?>)
                        </p>
                    </div>
                    <button type="button" onclick="closeReturnModal()" style="background: transparent; border: none; font-size: 20px; line-height: 1; color: #94a3b8; cursor: pointer; padding: 4px 8px; border-radius: 6px;" title="Kapat">✕</button>
                </div>

                <!-- Mevcut Sorumlu Bilgilendirme Kartı -->
                <div style="background: #fffbeb; border-bottom: 1px solid #fef3c7; padding: 14px 22px; display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 22px;">👤</span>
                    <div style="font-size: 12.5px;">
                        <div style="color: #b45309; font-weight: 600;">Mevcut Sorumlu (İade Eden):</div>
                        <div style="color: #78350f; font-weight: 800; font-size: 13.5px;">
                            <?= $display($asset['responsible_name']) ?>
                        </div>
                    </div>
                </div>

                <!-- Modal Form -->
                <form action="/stok-takip/public/inventory/return" method="POST" style="padding: 22px;">
                    <?= CsrfService::tokenField() ?>
                    <input type="hidden" name="asset_id" value="<?= (int) $asset['id'] ?>">

                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                                    Teslim Alınan Depo <span style="color: #ef4444;">*</span>
                                </label>
                                <select name="warehouse_id" id="return_warehouse_id" required style="width: 100%; height: 42px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #ffffff; color: #0f172a;">
                                    <option value="">-- Depo Seçiniz --</option>
                                    <?php foreach ($filterOptions['warehouses'] ?? [] as $w): ?>
                                        <option value="<?= (int) $w['id'] ?>" <?= ((int)($asset['warehouse_id'] ?? 0) === (int)$w['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($w['name']) ?> (<?= htmlspecialchars($w['code']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                                    Teslim Alınan Lokasyon <span style="color: #ef4444;">*</span>
                                </label>
                                <select name="location_id" id="return_location_id" required style="width: 100%; height: 42px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #ffffff; color: #0f172a;">
                                    <option value="">-- Önce Depo Seçiniz --</option>
                                </select>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                                    İade Tarihi <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="date" name="movement_date" value="<?= date('Y-m-d') ?>" required style="width: 100%; height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #ffffff; color: #0f172a;">
                            </div>

                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                                    İade / Tutanak No <span style="font-size: 11px; color: #94a3b8; font-weight: normal;">(Opsiyonel)</span>
                                </label>
                                <input type="text" name="reference_no" placeholder="Örn: İAD-<?= date('Y') ?>-001" maxlength="50" style="width: 100%; height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #ffffff; color: #0f172a;">
                            </div>
                        </div>

                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                                İade Nedeni / Açıklama <span style="font-size: 11px; color: #94a3b8; font-weight: normal;">(Opsiyonel)</span>
                            </label>
                            <input type="text" name="reason" placeholder="Örn: Görev devri / İhtiyaç fazlası / Proje tamamlandı" maxlength="255" style="width: 100%; height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #ffffff; color: #0f172a;">
                        </div>

                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                                Ek Notlar &amp; Teslim Durumu <span style="font-size: 11px; color: #94a3b8; font-weight: normal;">(Opsiyonel)</span>
                            </label>
                            <textarea name="notes" rows="3" placeholder="Cihazın teslim anındaki kondisyonu, eksik aparat veya aksesuar olup olmadığı..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #ffffff; color: #0f172a; resize: vertical;"></textarea>
                        </div>
                    </div>

                    <!-- Modal Butonları -->
                    <div style="margin-top: 22px; padding-top: 16px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="button" onclick="closeReturnModal()" style="padding: 0 16px; height: 38px; cursor: pointer;">
                            İptal
                        </button>
                        <button type="submit" class="button" style="background: #d97706; border-color: #b45309; color: #ffffff; font-weight: 700; padding: 0 18px; height: 38px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                            <span>↩️</span> Varlığı İade Al
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            const returnLocationsData = <?= json_encode(array_map(static fn(array $l): array => [
                'id' => (int) $l['id'],
                'warehouse_id' => (int) $l['warehouse_id'],
                'name' => $l['name'] . ' (' . $l['code'] . ')',
            ], $filterOptions['locations'] ?? []), JSON_UNESCAPED_UNICODE) ?>;
            const initialLocationId = <?= (int)($asset['location_id'] ?? 0) ?>;

            function updateReturnLocations(clearSelection = true) {
                const whSelect = document.getElementById('return_warehouse_id');
                const locSelect = document.getElementById('return_location_id');
                if (!whSelect || !locSelect) return;

                const whId = parseInt(whSelect.value, 10);
                locSelect.innerHTML = '';

                if (!whId) {
                    locSelect.disabled = true;
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = '-- Önce Depo Seçiniz --';
                    locSelect.appendChild(opt);
                    return;
                }

                locSelect.disabled = false;
                const defaultOpt = document.createElement('option');
                defaultOpt.value = '';
                defaultOpt.textContent = '-- Lokasyon Seçiniz --';
                locSelect.appendChild(defaultOpt);

                const filtered = returnLocationsData.filter(l => l.warehouse_id === whId);
                filtered.forEach(l => {
                    const opt = document.createElement('option');
                    opt.value = l.id;
                    opt.textContent = l.name;
                    locSelect.appendChild(opt);
                });

                if (!clearSelection && initialLocationId && filtered.some(l => l.id === initialLocationId)) {
                    locSelect.value = initialLocationId;
                }
            }

            const whSelectEl = document.getElementById('return_warehouse_id');
            if (whSelectEl) {
                whSelectEl.addEventListener('change', () => updateReturnLocations(true));
                updateReturnLocations(false);
            }

            function openReturnModal() {
                var modal = document.getElementById('returnModalBackdrop');
                if (modal) {
                    modal.style.display = 'flex';
                    updateReturnLocations(false);
                }
            }

            function closeReturnModal() {
                var modal = document.getElementById('returnModalBackdrop');
                if (modal) {
                    modal.style.display = 'none';
                }
            }
        </script>
    <?php endif; ?>

    <script>
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (typeof closeAssignModal === 'function') closeAssignModal();
                if (typeof closeTransferModal === 'function') closeTransferModal();
                if (typeof closeReturnModal === 'function') closeReturnModal();
            }
        });

        var assignBackdrop = document.getElementById('assignModalBackdrop');
        if (assignBackdrop) {
            assignBackdrop.addEventListener('click', function(e) {
                if (e.target === assignBackdrop) {
                    closeAssignModal();
                }
            });
        }

        var transferBackdrop = document.getElementById('transferModalBackdrop');
        if (transferBackdrop) {
            transferBackdrop.addEventListener('click', function(e) {
                if (e.target === transferBackdrop) {
                    closeTransferModal();
                }
            });
        }

        var returnBackdrop = document.getElementById('returnModalBackdrop');
        if (returnBackdrop) {
            returnBackdrop.addEventListener('click', function(e) {
                if (e.target === returnBackdrop) {
                    closeReturnModal();
                }
            });
        }
    </script>
</main>


