<?php

$pageTitle = 'Bakım İş Emri: ' . ($workOrder['work_order_no'] ?? '');
$activePage = 'maintenance';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';

$workOrder = $workOrder ?? [];
$spareParts = $spareParts ?? [];
$availableMaterials = $availableMaterials ?? [];
$technicians = $technicians ?? [];
?>

<main class="main-content">

    <?php if (isset($_SESSION['success'])): ?>
        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; font-size: 13.5px; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
            <span>✅</span> <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; font-size: 13.5px; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
            <span>⚠️</span> <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- BREADCRUMB & TOP -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: #64748b; margin-bottom: 6px;">
                <a href="/stok-takip/public/maintenance" style="color: #4338ca; text-decoration: none; font-weight: 600;">Bakım Yönetimi</a>
                <span>&rsaquo;</span>
                <span style="color: #0f172a; font-weight: 600;"><?= htmlspecialchars($workOrder['work_order_no']) ?></span>
            </div>
            <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0;">
                BAKIM İŞ EMRİ: <span style="font-family: monospace; color: #4338ca;"><?= htmlspecialchars($workOrder['work_order_no']) ?></span>
            </h1>
        </div>

        <div style="display: flex; gap: 10px;">
            <a href="/stok-takip/public/maintenance" class="button" style="padding: 8px 14px; text-decoration: none;">
                &larr; Bakım Listesine Dön
            </a>
            <a href="/stok-takip/public/maintenance/asset?id=<?= $workOrder['asset_id'] ?>" class="button button-primary" style="padding: 8px 14px; text-decoration: none;">
                Ekipman Pasaportu &rarr;
            </a>
        </div>
    </div>

    <!-- LIFECYCLE PROGRESS STEPPER -->
    <?php
        $statuses = ['OPEN', 'ASSIGNED', 'IN_PROGRESS', 'COMPLETED', 'VERIFIED'];
        $currentIdx = array_search($workOrder['status'], $statuses);
        if ($currentIdx === false) $currentIdx = 0;
    ?>
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="display: flex; justify-content: space-between; align-items: center; position: relative;">
            <?php foreach ($statuses as $idx => $st): ?>
                <?php
                    $isDone = ($idx <= $currentIdx);
                    $isCurrent = ($st === $workOrder['status']);
                    $stName = match($st) {
                        'OPEN' => '1. Arıza Açıldı',
                        'ASSIGNED' => '2. Atandı',
                        'IN_PROGRESS' => '3. Onarımda',
                        'COMPLETED' => '4. Tamamlandı',
                        'VERIFIED' => '5. Doğrulandı & Kapandı',
                        default => $st
                    };
                ?>
                <div style="display: flex; flex-direction: column; align-items: center; z-index: 2; text-align: center;">
                    <div style="width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; background: <?= $isDone ? '#10b981' : '#e2e8f0' ?>; color: <?= $isDone ? '#ffffff' : '#64748b' ?>; margin-bottom: 6px; box-shadow: <?= $isCurrent ? '0 0 0 4px rgba(16, 185, 129, 0.25)' : 'none' ?>;">
                        <?= $isDone ? '✓' : ($idx + 1) ?>
                    </div>
                    <div style="font-size: 12px; font-weight: <?= $isCurrent ? '800' : '600' ?>; color: <?= $isCurrent ? '#0f172a' : ($isDone ? '#166534' : '#94a3b8') ?>;">
                        <?= $stName ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- MAIN GRID (DETAILS + ACTIONS) -->
    <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 20px; align-items: flex-start; margin-bottom: 24px;">
        
        <!-- SOL: İŞ EMRİ BİLGİ KARTI -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0 0 14px 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                ⚙️ İŞ EMRİ &amp; EKİPMAN BİLGİLERİ
            </h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; font-size: 13px; margin-bottom: 16px;">
                <div>
                    <span style="color: #64748b; display: block; font-size: 11px; text-transform: uppercase;">Ekipman Adı:</span>
                    <strong style="color: #0f172a; font-size: 14px;"><?= htmlspecialchars($workOrder['asset_name']) ?></strong>
                    <div style="font-family: monospace; color: #4338ca; font-size: 11.5px;"><?= htmlspecialchars($workOrder['asset_code']) ?></div>
                </div>
                <div>
                    <span style="color: #64748b; display: block; font-size: 11px; text-transform: uppercase;">Bağlı Hat:</span>
                    <strong style="color: #0f172a;"><?= htmlspecialchars($workOrder['line_name']) ?> (<?= htmlspecialchars($workOrder['line_code']) ?>)</strong>
                </div>
                <div>
                    <span style="color: #64748b; display: block; font-size: 11px; text-transform: uppercase;">Bakım Türü:</span>
                    <strong style="color: <?= $workOrder['maintenance_type'] === 'CORRECTIVE' ? '#dc2626' : '#2563eb' ?>;">
                        <?= htmlspecialchars($workOrder['maintenance_type']) ?>
                    </strong>
                </div>
                <div>
                    <span style="color: #64748b; display: block; font-size: 11px; text-transform: uppercase;">Öncelik:</span>
                    <strong style="color: #991b1b;"><?= htmlspecialchars($workOrder['priority']) ?></strong>
                </div>
                <div>
                    <span style="color: #64748b; display: block; font-size: 11px; text-transform: uppercase;">Bildiren Operatör:</span>
                    <strong><?= htmlspecialchars($workOrder['reporter_name']) ?></strong>
                </div>
                <div>
                    <span style="color: #64748b; display: block; font-size: 11px; text-transform: uppercase;">Atanan Teknisyen:</span>
                    <strong style="color: #3730a3;"><?= htmlspecialchars($workOrder['technician_name'] ?: 'Atanmadı') ?></strong>
                </div>
            </div>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px;">
                <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">Arıza Açıklaması:</div>
                <div style="font-size: 13px; color: #0f172a;">
                    <?= nl2br(htmlspecialchars($workOrder['failure_description'] ?: 'Belirtilmedi.')) ?>
                </div>
            </div>

            <?php if (!empty($workOrder['action_taken'])): ?>
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px 14px;">
                    <div style="font-size: 11px; font-weight: 700; color: #166534; text-transform: uppercase; margin-bottom: 4px;">Yapılan Onarım &amp; Kök Neden:</div>
                    <div style="font-size: 13px; color: #14532d;">
                        <strong>Kök Neden:</strong> <?= htmlspecialchars($workOrder['root_cause_text']) ?><br>
                        <strong>Müdahale:</strong> <?= nl2br(htmlspecialchars($workOrder['action_taken'])) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- SAĞ: AKSİYON & DURUM YÖNETİMİ -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0 0 14px 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                ⚡ BAKIM YÖNETİM AKSİYONLARI
            </h3>

            <!-- 1. TEKNİSYEN ATAMA (Eğer OPEN ise) -->
            <?php if ($workOrder['status'] === 'OPEN'): ?>
                <form action="/stok-takip/public/maintenance/assign" method="POST" style="margin-bottom: 16px;">
                    <?= CsrfService::renderInput() ?>
                    <input type="hidden" name="work_order_id" value="<?= $workOrder['id'] ?>">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">Teknisyen Ata *</label>
                    <div style="display: flex; gap: 8px;">
                        <select name="technician_user_id" required style="flex: 1; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
                            <option value="">Teknisyen Seçiniz...</option>
                            <?php foreach ($technicians as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name'] . ' (' . $t['username'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="button button-primary" style="padding: 8px 14px;">Ata</button>
                    </div>
                </form>
            <?php endif; ?>

            <!-- 2. İŞE BAŞLAMA (Eğer ASSIGNED ise) -->
            <?php if ($workOrder['status'] === 'ASSIGNED'): ?>
                <form action="/stok-takip/public/maintenance/start" method="POST" style="margin-bottom: 16px;">
                    <?= CsrfService::renderInput() ?>
                    <input type="hidden" name="work_order_id" value="<?= $workOrder['id'] ?>">
                    <button type="submit" class="button button-primary" style="width: 100%; padding: 12px; font-size: 14px; font-weight: 800; background: #0284c7; border-color: #0369a1;">
                        ▶️ Onarım &amp; Müdahaleyi Başlat (IN_PROGRESS)
                    </button>
                </form>
            <?php endif; ?>

            <!-- 3. ONARIM TAMAMLAMA (Eğer IN_PROGRESS ise) -->
            <?php if ($workOrder['status'] === 'IN_PROGRESS' || $workOrder['status'] === 'WAITING_PART'): ?>
                <button type="button" onclick="document.getElementById('modal-complete').style.display='flex'" class="button button-primary" style="width: 100%; padding: 12px; font-size: 14px; font-weight: 800; background: #16a34a; border-color: #15803d; margin-bottom: 14px;">
                    ✓ Onarımı Tamamla (COMPLETED)
                </button>
            <?php endif; ?>

            <!-- 4. DOĞRULAMA & DEVREYE ALMA (Eğer COMPLETED ise) -->
            <?php if ($workOrder['status'] === 'COMPLETED'): ?>
                <form action="/stok-takip/public/maintenance/verify" method="POST">
                    <?= CsrfService::renderInput() ?>
                    <input type="hidden" name="work_order_id" value="<?= $workOrder['id'] ?>">
                    <button type="submit" class="button button-primary" style="width: 100%; padding: 12px; font-size: 14px; font-weight: 800; background: #059669; border-color: #047857;">
                        🛡️ Kalite / Amir Onayı: Doğrula &amp; Hattı Devreye Al (VERIFIED)
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($workOrder['status'] === 'VERIFIED'): ?>
                <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 14px; text-align: center; color: #065f46; font-weight: 700; font-size: 13.5px;">
                    🎉 Bu bakım iş emri başarıyla tamamlanmış ve doğrulanmıştır.
                </div>
            <?php endif; ?>

            <!-- MALİYET ÖZETİ -->
            <div style="margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 14px;">
                <div style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 8px;">Maliyet Kırılımı:</div>
                <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px;">
                    <span style="color: #64748b;">İşçilik Maliyeti:</span>
                    <strong><?= number_format($workOrder['labor_cost'], 2, ',', '.') ?> TL</strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px;">
                    <span style="color: #64748b;">Yedek Parça Maliyeti:</span>
                    <strong><?= number_format($workOrder['spare_parts_cost'], 2, ',', '.') ?> TL</strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 15px; border-top: 1px dashed #cbd5e1; padding-top: 6px; margin-top: 6px;">
                    <span style="color: #0f172a; font-weight: 800;">Toplam Bakım Maliyeti:</span>
                    <strong style="color: #4338ca; font-weight: 900;"><?= number_format($workOrder['total_maintenance_cost'], 2, ',', '.') ?> TL</strong>
                </div>
            </div>

        </div>

    </div>

    <!-- 4. HARCANAN YEDEK PARÇALAR & STOK ENTEGRASYONU -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
            <div>
                <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0;">📦 KULLANILAN YEDEK PARÇALAR (MAINTENANCE_OUT)</h3>
                <p style="font-size: 12px; color: #64748b; margin: 2px 0 0 0;">Stoktan düşülen ve iş emrine maliyet snapshot'ı mühürlenen yedek parçalar</p>
            </div>

            <?php if (in_array($workOrder['status'], ['ASSIGNED', 'IN_PROGRESS', 'WAITING_PART'])): ?>
                <button type="button" onclick="document.getElementById('modal-spare-part').style.display='flex'" class="button button-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                    + Yedek Parça Ekle &amp; Tüket
                </button>
            <?php endif; ?>
        </div>

        <?php if (empty($spareParts)): ?>
            <div style="padding: 24px; text-align: center; color: #94a3b8; font-size: 13px;">
                Bu bakımda henüz yedek parça kullanılmadı.
            </div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 11px; text-transform: uppercase;">
                        <th style="padding: 8px 10px;">Parça Kodu</th>
                        <th style="padding: 8px 10px;">Parça Adı</th>
                        <th style="padding: 8px 10px; text-align: right;">Miktar</th>
                        <th style="padding: 8px 10px; text-align: right;">Birim Fiyat (Snapshot)</th>
                        <th style="padding: 8px 10px; text-align: right;">Toplam Tutar</th>
                        <th style="padding: 8px 10px;">Stok Ref</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($spareParts as $sp): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 10px; font-family: monospace; font-weight: 700; color: #4338ca;">
                                <?= htmlspecialchars($sp['material_code']) ?>
                            </td>
                            <td style="padding: 10px 10px; font-weight: 700; color: #0f172a;">
                                <?= htmlspecialchars($sp['material_name']) ?>
                            </td>
                            <td style="padding: 10px 10px; text-align: right; font-weight: 800;">
                                <?= number_format($sp['quantity_used'], 2, ',', '.') ?>
                            </td>
                            <td style="padding: 10px 10px; text-align: right; color: #64748b;">
                                <?= number_format($sp['unit_cost_snapshot'], 2, ',', '.') ?> TL
                            </td>
                            <td style="padding: 10px 10px; text-align: right; font-weight: 800; color: #0f172a;">
                                <?= number_format($sp['total_cost_snapshot'], 2, ',', '.') ?> TL
                            </td>
                            <td style="padding: 10px 10px; font-family: monospace; font-size: 11px; color: #64748b;">
                                <?= htmlspecialchars($sp['movement_ref'] ?: 'STK-OUT') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</main>

<!-- MODAL: YEDEK PARÇA TÜKETİMİ -->
<div id="modal-spare-part" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(3px);">
    <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 480px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); margin: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #0f172a;">📦 Bakım Yedek Parçası Kullan</h3>
            <button type="button" onclick="document.getElementById('modal-spare-part').style.display='none'" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">&times;</button>
        </div>

        <form action="/stok-takip/public/maintenance/spare-part/consume" method="POST">
            <?= CsrfService::renderInput() ?>
            <input type="hidden" name="work_order_id" value="<?= $workOrder['id'] ?>">

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">Yedek Parça Malzeme Kartı *</label>
                <select name="material_id" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
                    <option value="">Parça Seçiniz...</option>
                    <?php foreach ($availableMaterials as $am): ?>
                        <option value="<?= $am['id'] ?>">
                            <?= htmlspecialchars($am['name']) ?> (<?= htmlspecialchars($am['code']) ?>) - <?= number_format($am['unit_price'], 2) ?> TL
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">Kullanılan Adet / Miktar *</label>
                <input type="number" step="0.01" min="0.01" name="quantity" value="1.0" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="document.getElementById('modal-spare-part').style.display='none'" class="button" style="padding: 8px 16px;">İptal</button>
                <button type="submit" class="button button-primary" style="padding: 8px 18px;">Stoktan Düş &amp; Ekle</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: ONARIM TAMAMLAMA -->
<div id="modal-complete" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(3px);">
    <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 520px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); margin: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #0f172a;">✓ Bakım Onarımı Tamamla</h3>
            <button type="button" onclick="document.getElementById('modal-complete').style.display='none'" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">&times;</button>
        </div>

        <form action="/stok-takip/public/maintenance/complete" method="POST">
            <?= CsrfService::renderInput() ?>
            <input type="hidden" name="work_order_id" value="<?= $workOrder['id'] ?>">

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">Tespit Edilen Kök Neden *</label>
                <input type="text" name="root_cause_text" required placeholder="Örn: Sensör kablosunda temassızlık ve soket gevşemesi" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">Yapılan İşlem &amp; Onarım Özeti *</label>
                <textarea name="action_taken" required rows="3" placeholder="Uygulanan lehimleme, parça değişimi veya temizlik adımları..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;"></textarea>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">Harcanan İşçilik Süresi (Saat) *</label>
                <input type="number" step="0.5" min="0.1" name="labor_hours" value="1.0" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="document.getElementById('modal-complete').style.display='none'" class="button" style="padding: 8px 16px;">İptal</button>
                <button type="submit" class="button button-primary" style="padding: 8px 18px; background: #16a34a; border-color: #15803d;">Onarımı Kaydet</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>

