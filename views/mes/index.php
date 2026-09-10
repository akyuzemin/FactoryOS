<?php
$pageTitle = 'İş Emirleri (MES)';
$activePage = 'mes';

$currentStatus = $_GET['status'] ?? '';
$searchQuery = trim($_GET['search'] ?? '');

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content" style="max-width: 1320px; padding: 24px 32px;">

    <!-- 1. BAŞLIK VE AKSİYON BUTONLARI -->
    <header style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0; letter-spacing: -0.02em;">İŞ EMİRLERİ</h1>
            <p style="font-size: 13px; color: #64748b; margin: 0;">Güneş paneli üretim hatları, aktif iş emirleri ve anlık ilerleme durumu.</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="/stok-takip/public/mes/simulator" style="display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; font-size: 13px; font-weight: 600; background: #ffffff; color: #2563eb; border: 1px solid #bfdbfe; border-radius: 8px; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
                <span>⚡</span> MES Simülatörü
            </a>
            <a href="/stok-takip/public/mes/work-orders/create" style="display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; font-size: 13px; font-weight: 600; background: #2563eb; color: #ffffff; border: 1px solid #1d4ed8; border-radius: 8px; text-decoration: none; box-shadow: 0 1px 2px rgba(37,99,235,0.2);">
                <span>➕</span> Yeni İş Emri
            </a>
        </div>
    </header>

    <?php if (!empty($_SESSION['success'])): ?>
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; font-weight: 600;">
            ✓ <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; font-weight: 600;">
            ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- 2. ÜRETİM HATLARI DURUM ALANI (KOMPAKT KARTLAR) -->
    <div style="margin-bottom: 22px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h2 style="font-size: 13px; font-weight: 800; color: #0f172a; margin: 0; text-transform: uppercase; letter-spacing: 0.04em; display: flex; align-items: center; gap: 6px;">
                <span>🏭</span> ÜRETİM HATLARI
            </h2>
            <span style="font-size: 11.5px; color: #64748b; font-weight: 600;">
                Toplam <?= count($productionLines ?? []) ?> Hat
            </span>
        </div>

        <div id="production-lines-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(270px, 1fr)); gap: 12px;">
            <?php foreach ($productionLines ?? [] as $pl): ?>
                <div class="line-card" id="line-card-<?= $pl['id'] ?>" style="background: #ffffff; border: 1px solid <?= $pl['badge_border'] ?>; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between; min-height: 125px;">
                    <div>
                        <!-- Line Header -->
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <span style="font-weight: 800; font-size: 13.5px; color: #0f172a;">
                                <?= htmlspecialchars($pl['name']) ?>
                            </span>
                            <span class="line-badge" style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 12px; background: <?= $pl['badge_bg'] ?>; color: <?= $pl['badge_color'] ?>; border: 1px solid <?= $pl['badge_border'] ?>;">
                                <?= $pl['status_badge'] ?>
                            </span>
                        </div>

                        <?php if (!empty($pl['active_work_order'])): 
                            $awo = $pl['active_work_order'];
                        ?>
                            <!-- Active Work Order Info -->
                            <div style="font-size: 12.5px; font-weight: 700; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?= htmlspecialchars($awo['product_name']) ?>
                            </div>
                            <div style="font-size: 11.5px; color: #64748b; font-family: monospace; margin-top: 1px;">
                                <a href="/stok-takip/public/mes/work-orders/show?id=<?= $awo['id'] ?>" style="color: #2563eb; text-decoration: none; font-weight: 600;">
                                    <?= htmlspecialchars($awo['work_order_no']) ?>
                                </a>
                            </div>

                            <!-- Progress & Counts -->
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; font-weight: 700; color: #0f172a; margin-top: 8px; margin-bottom: 4px;">
                                <span><?= number_format($awo['produced_quantity'], 0) ?> / <?= number_format($awo['planned_quantity'], 0) ?> PANEL</span>
                                <span style="color: #16a34a;">%<?= number_format($awo['progress_pct'], 1) ?></span>
                            </div>
                            <div style="width: 100%; height: 5px; background: #e2e8f0; border-radius: 999px; overflow: hidden; margin-bottom: 4px;">
                                <div style="width: <?= min(100, $awo['progress_pct']) ?>%; height: 100%; background: #10b981; border-radius: 999px;"></div>
                            </div>
                        <?php else: ?>
                            <!-- Idle / Maintenance / Fault Description -->
                            <div style="padding-top: 8px; font-size: 12.5px; color: #64748b;">
                                <?= htmlspecialchars($pl['status_desc']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($pl['active_work_order'])): ?>
                        <div style="font-size: 10.5px; color: #64748b; text-align: right; margin-top: 4px;">
                            <?= htmlspecialchars($pl['active_work_order']['estimated_human']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 3. ÜST 6 SADE KPI KARTI (İŞ EMRİ YAŞAM DÖNGÜSÜ) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(165px, 1fr)); gap: 12px; margin-bottom: 20px;">
        
        <!-- 📋 TOPLAM İŞ EMRİ -->
        <a href="/stok-takip/public/mes" style="text-decoration: none; background: #ffffff; border: 1px solid <?= empty($currentStatus) ? '#2563eb' : '#e2e8f0' ?>; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.02); transition: all 0.15s ease;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; letter-spacing: 0.04em;">📋 TOPLAM İŞ EMRİ</div>
            <div style="font-size: 24px; font-weight: 800; color: #0f172a; margin-top: 4px;">
                <?= (int)($stats['total_count'] ?? 0) ?>
            </div>
            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">Tüm iş emirleri</div>
        </a>

        <!-- 🟢 ÜRETİMDE -->
        <a href="/stok-takip/public/mes?status=RUNNING" style="text-decoration: none; background: #ffffff; border: 1px solid <?= $currentStatus === 'RUNNING' ? '#16a34a' : '#bbf7d0' ?>; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.02); transition: all 0.15s ease;">
            <div style="font-size: 11px; font-weight: 700; color: #166534; letter-spacing: 0.04em;">🟢 ÜRETİMDE</div>
            <div style="font-size: 24px; font-weight: 800; color: #15803d; margin-top: 4px;">
                <?= (int)($stats['running_count'] ?? 0) ?>
            </div>
            <div style="font-size: 11px; color: #16a34a; margin-top: 2px;">Aktif çalışan hat</div>
        </a>

        <!-- 🟣 HAZIR / KUYRUKTA -->
        <a href="/stok-takip/public/mes?status=READY" style="text-decoration: none; background: #ffffff; border: 1px solid <?= $currentStatus === 'READY' ? '#9333ea' : '#e9d5ff' ?>; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.02); transition: all 0.15s ease;">
            <div style="font-size: 11px; font-weight: 700; color: #6b21a8; letter-spacing: 0.04em;">🟣 HAZIR</div>
            <div style="font-size: 24px; font-weight: 800; color: #7e22ce; margin-top: 4px;">
                <?= (int)($stats['ready_count'] ?? 0) ?>
            </div>
            <div style="font-size: 11px; color: #9333ea; margin-top: 2px;">Kuyrukta hazır</div>
        </a>

        <!-- 🔵 PLANLANDI -->
        <a href="/stok-takip/public/mes?status=PLANNED" style="text-decoration: none; background: #ffffff; border: 1px solid <?= $currentStatus === 'PLANNED' ? '#2563eb' : '#bfdbfe' ?>; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.02); transition: all 0.15s ease;">
            <div style="font-size: 11px; font-weight: 700; color: #1e40af; letter-spacing: 0.04em;">🔵 PLANLANDI</div>
            <div style="font-size: 24px; font-weight: 800; color: #1d4ed8; margin-top: 4px;">
                <?= (int)($stats['planned_count'] ?? 0) ?>
            </div>
            <div style="font-size: 11px; color: #3b82f6; margin-top: 2px;">Taslak / planlı</div>
        </a>

        <!-- 🟡 DURAKLATILDI -->
        <a href="/stok-takip/public/mes?status=PAUSED" style="text-decoration: none; background: #ffffff; border: 1px solid <?= $currentStatus === 'PAUSED' ? '#d97706' : '#fde68a' ?>; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.02); transition: all 0.15s ease;">
            <div style="font-size: 11px; font-weight: 700; color: #92400e; letter-spacing: 0.04em;">🟡 DURAKLATILDI</div>
            <div style="font-size: 24px; font-weight: 800; color: #b45309; margin-top: 4px;">
                <?= (int)($stats['paused_count'] ?? 0) ?>
            </div>
            <div style="font-size: 11px; color: #d97706; margin-top: 2px;">Beklemede</div>
        </a>

        <!-- ⚫ TAMAMLANDI -->
        <a href="/stok-takip/public/mes?status=COMPLETED" style="text-decoration: none; background: #ffffff; border: 1px solid <?= $currentStatus === 'COMPLETED' ? '#475569' : '#cbd5e1' ?>; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.02); transition: all 0.15s ease;">
            <div style="font-size: 11px; font-weight: 700; color: #334155; letter-spacing: 0.04em;">⚫ TAMAMLANDI</div>
            <div style="font-size: 24px; font-weight: 800; color: #0f172a; margin-top: 4px;">
                <?= (int)($stats['completed_count'] ?? 0) ?>
            </div>
            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">Hedefe ulaşan</div>
        </a>

    </div>

    <!-- 3. FİLTRE VE ARAMA ÇUBUĞU -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 18px; margin-bottom: 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        
        <!-- Sekmeler -->
        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
            <a href="/stok-takip/public/mes" style="padding: 6px 12px; font-size: 12.5px; font-weight: 600; border-radius: 6px; text-decoration: none; <?= empty($currentStatus) ? 'background: #2563eb; color: #ffffff;' : 'background: #f1f5f9; color: #475569;' ?>">
                Tümü (<?= $stats['total_count'] ?? 0 ?>)
            </a>
            <a href="/stok-takip/public/mes?status=RUNNING" style="padding: 6px 12px; font-size: 12.5px; font-weight: 600; border-radius: 6px; text-decoration: none; <?= $currentStatus === 'RUNNING' ? 'background: #16a34a; color: #ffffff;' : 'background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;' ?>">
                🟢 Üretimde (<?= $stats['running_count'] ?? 0 ?>)
            </a>
            <a href="/stok-takip/public/mes?status=READY" style="padding: 6px 12px; font-size: 12.5px; font-weight: 600; border-radius: 6px; text-decoration: none; <?= $currentStatus === 'READY' ? 'background: #9333ea; color: #ffffff;' : 'background: #faf5ff; color: #6b21a8; border: 1px solid #e9d5ff;' ?>">
                🟣 Hazır (<?= $stats['ready_count'] ?? 0 ?>)
            </a>
            <a href="/stok-takip/public/mes?status=PLANNED" style="padding: 6px 12px; font-size: 12.5px; font-weight: 600; border-radius: 6px; text-decoration: none; <?= $currentStatus === 'PLANNED' ? 'background: #2563eb; color: #ffffff;' : 'background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe;' ?>">
                🔵 Planlandı (<?= $stats['planned_count'] ?? 0 ?>)
            </a>
            <a href="/stok-takip/public/mes?status=PAUSED" style="padding: 6px 12px; font-size: 12.5px; font-weight: 600; border-radius: 6px; text-decoration: none; <?= $currentStatus === 'PAUSED' ? 'background: #d97706; color: #ffffff;' : 'background: #fffbeb; color: #92400e; border: 1px solid #fde68a;' ?>">
                🟡 Duraklatıldı (<?= $stats['paused_count'] ?? 0 ?>)
            </a>
            <a href="/stok-takip/public/mes?status=COMPLETED" style="padding: 6px 12px; font-size: 12.5px; font-weight: 600; border-radius: 6px; text-decoration: none; <?= $currentStatus === 'COMPLETED' ? 'background: #334155; color: #ffffff;' : 'background: #f8fafc; color: #334155; border: 1px solid #cbd5e1;' ?>">
                ⚫ Tamamlandı (<?= $stats['completed_count'] ?? 0 ?>)
            </a>
            <?php if (!empty($stats['failed_count'])): ?>
                <a href="/stok-takip/public/mes?status=FAILED" style="padding: 6px 12px; font-size: 12.5px; font-weight: 600; border-radius: 6px; text-decoration: none; <?= $currentStatus === 'FAILED' ? 'background: #dc2626; color: #ffffff;' : 'background: #fef2f2; color: #991b1b; border: 1px solid #fecaca;' ?>">
                    🔴 Hata (<?= $stats['failed_count'] ?>)
                </a>
            <?php endif; ?>
        </div>

        <!-- Arama Kutusu -->
        <form method="GET" action="/stok-takip/public/mes" style="display: flex; gap: 6px; min-width: 260px; flex: 1; max-width: 380px;">
            <?php if (!empty($currentStatus)): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($currentStatus) ?>">
            <?php endif; ?>
            <input 
                type="text" 
                name="search" 
                placeholder="İş emri, ürün, hat ara..." 
                value="<?= htmlspecialchars($searchQuery) ?>"
                style="flex: 1; padding: 7px 12px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none;"
            >
            <button type="submit" style="padding: 7px 12px; font-size: 13px; font-weight: 600; background: #2563eb; color: #ffffff; border: none; border-radius: 6px; cursor: pointer;">
                Ara
            </button>
            <?php if (!empty($searchQuery)): ?>
                <a href="/stok-takip/public/mes<?= !empty($currentStatus) ? '?status=' . urlencode($currentStatus) : '' ?>" style="padding: 7px 10px; font-size: 12.5px; font-weight: 600; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center;">
                    ✕
                </a>
            <?php endif; ?>
        </form>

    </div>

    <!-- 4. İŞ EMİRLERİ TABLOSU -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13.5px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">
                    <th style="padding: 12px 18px;">İŞ EMRİ</th>
                    <th style="padding: 12px 18px;">ÜRÜN</th>
                    <th style="padding: 12px 18px;">HAT</th>
                    <th style="padding: 12px 18px; text-align: right;">ÜRETİM</th>
                    <th style="padding: 12px 18px; min-width: 150px;">İLERLEME</th>
                    <th style="padding: 12px 18px; text-align: center;">DURUM</th>
                    <th style="padding: 12px 18px; text-align: right;">İŞLEM</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($workOrders)): ?>
                <tr>
                    <td colspan="7" style="padding: 36px; text-align: center; color: #64748b; font-size: 14px;">
                        Kriterlere uygun iş emri bulunamadı. <a href="/stok-takip/public/mes/work-orders/create" style="color: #2563eb; font-weight: 600;">Yeni bir iş emri oluşturun.</a>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($workOrders as $wo): 
                    $st = $wo['status_info'] ?? Mes::classifyStatus($wo);
                    $planned = (float)($wo['planned_quantity'] ?? 0);
                    $produced = (float)($wo['produced_quantity'] ?? 0);
                    $pct = (float)($wo['progress_pct'] ?? 0);
                ?>
                    <tr style="border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: background 0.15s ease;" 
                        onclick="if(!event.target.closest('a') && !event.target.closest('button')) { window.location='/stok-takip/public/mes/work-orders/show?id=<?= $wo['id'] ?>'; }"
                        onmouseover="this.style.background='#f8fafc';" 
                        onmouseout="this.style.background='transparent';"
                    >
                        
                        <!-- İŞ EMRİ -->
                        <td style="padding: 14px 18px; white-space: nowrap;">
                            <a href="/stok-takip/public/mes/work-orders/show?id=<?= $wo['id'] ?>" style="font-weight: 700; color: #2563eb; text-decoration: none; font-size: 13.5px;" onmouseover="this.style.textDecoration='underline';" onmouseout="this.style.textDecoration='none';">
                                <?= htmlspecialchars($wo['work_order_no']) ?>
                            </a>
                            <div style="font-size: 11px; color: #64748b; font-family: monospace; margin-top: 2px;">
                                BOM: <?= htmlspecialchars($wo['recipe_code']) ?>
                            </div>
                        </td>

                        <!-- ÜRÜN -->
                        <td style="padding: 14px 18px;">
                            <div style="font-weight: 700; color: #0f172a; font-size: 13.5px; line-height: 1.3;">
                                <?= htmlspecialchars($wo['product_name']) ?>
                            </div>
                            <span style="font-size: 11px; color: #64748b; font-family: monospace; background: #f1f5f9; padding: 1px 5px; border-radius: 4px; border: 1px solid #e2e8f0; display: inline-block; margin-top: 3px;">
                                <?= htmlspecialchars($wo['product_code']) ?>
                            </span>
                        </td>

                        <!-- HAT -->
                        <td style="padding: 14px 18px; white-space: nowrap;">
                            <div style="font-weight: 600; color: #334155; font-size: 13px;">
                                <?= htmlspecialchars($wo['line_name']) ?>
                            </div>
                            <div style="font-size: 11px; color: #64748b; font-family: monospace;">
                                <?= htmlspecialchars($wo['line_code']) ?>
                            </div>
                        </td>

                        <!-- ÜRETİM (37 / 1000 PANEL) -->
                        <td style="padding: 14px 18px; text-align: right; white-space: nowrap;">
                            <div style="font-size: 14px; font-weight: 800; color: #0f172a;">
                                <span style="color: #16a34a;"><?= number_format($produced, 0, ',', '.') ?></span>
                                <span style="color: #94a3b8; font-weight: 400; font-size: 12.5px;">/</span>
                                <span><?= number_format($planned, 0, ',', '.') ?></span>
                                <span style="font-size: 11px; font-weight: 700; color: #64748b; margin-left: 2px;">PANEL</span>
                            </div>
                        </td>

                        <!-- İLERLEME (%) -->
                        <td style="padding: 14px 18px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="flex: 1; height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden; border: 1px solid #e2e8f0;">
                                    <div style="width: <?= min(100, $pct) ?>%; height: 100%; background: <?= $pct >= 100 ? '#10b981' : ($st['key'] === 'RUNNING' ? '#16a34a' : '#2563eb') ?>; border-radius: 4px; transition: width 0.3s ease;"></div>
                                </div>
                                <span style="font-size: 12px; font-weight: 700; color: #334155; font-variant-numeric: tabular-nums; min-width: 44px; text-align: right;">
                                    %<?= number_format($pct, 1, ',', '.') ?>
                                </span>
                            </div>
                        </td>

                        <!-- DURUM (ROZET) -->
                        <td style="padding: 14px 18px; text-align: center; white-space: nowrap;">
                            <span style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 700; background: <?= $st['bg'] ?>; color: <?= $st['color'] ?>; border: 1px solid <?= $st['border'] ?>;">
                                <span style="width: 6px; height: 6px; border-radius: 50%; background: <?= $st['dot'] ?>;"></span>
                                <?= $st['label'] ?>
                            </span>
                        </td>

                        <!-- İŞLEM -->
                        <td style="padding: 14px 18px; text-align: right; white-space: nowrap;">
                            <div style="display: inline-flex; gap: 6px; align-items: center;">
                                <?php if ($st['key'] === 'PLANNED'): ?>
                                    <button type="button" onclick="setWoStatus(<?= $wo['id'] ?>, 'READY', event)" style="padding: 5px 10px; font-size: 11.5px; font-weight: 600; background: #faf5ff; color: #6b21a8; border: 1px solid #e9d5ff; border-radius: 6px; cursor: pointer;">
                                        🟣 Sıraya Al
                                    </button>
                                    <a href="/stok-takip/public/mes/work-orders/show?id=<?= $wo['id'] ?>" style="padding: 5px 10px; font-size: 11.5px; font-weight: 600; background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; border-radius: 6px; text-decoration: none;">
                                        ▶ Başlat
                                    </a>
                                <?php elseif ($st['key'] === 'READY'): ?>
                                    <a href="/stok-takip/public/mes/work-orders/show?id=<?= $wo['id'] ?>" style="padding: 5px 10px; font-size: 11.5px; font-weight: 600; background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; border-radius: 6px; text-decoration: none;">
                                        ▶ Başlat
                                    </a>
                                <?php elseif ($st['key'] === 'RUNNING'): ?>
                                    <a href="/stok-takip/public/mes/work-orders/show?id=<?= $wo['id'] ?>" style="padding: 5px 10px; font-size: 11.5px; font-weight: 600; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; border-radius: 6px; text-decoration: none;">
                                        ⚡ Canlı
                                    </a>
                                <?php elseif ($st['key'] === 'PAUSED'): ?>
                                    <a href="/stok-takip/public/mes/work-orders/show?id=<?= $wo['id'] ?>" style="padding: 5px 10px; font-size: 11.5px; font-weight: 600; background: #fffbeb; color: #92400e; border: 1px solid #fde68a; border-radius: 6px; text-decoration: none;">
                                        ▶ Devam Et
                                    </a>
                                <?php elseif ($st['key'] === 'FAILED'): ?>
                                    <a href="/stok-takip/public/mes/work-orders/show?id=<?= $wo['id'] ?>" style="padding: 5px 10px; font-size: 11.5px; font-weight: 600; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; border-radius: 6px; text-decoration: none;">
                                        ↻ Yeniden Dene
                                    </a>
                                <?php endif; ?>
                                <a href="/stok-takip/public/mes/work-orders/show?id=<?= $wo['id'] ?>" style="padding: 5px 10px; font-size: 11.5px; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px; text-decoration: none;">
                                    Detay &rarr;
                                </a>
                            </div>
                        </td>

                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>

<script>
function setWoStatus(woId, newStatus, evt) {
    if (evt) {
        evt.stopPropagation();
        evt.preventDefault();
    }
    const formData = new FormData();
    formData.append('work_order_id', woId);
    formData.append('status', newStatus);

    fetch('/stok-takip/public/mes/work-orders/status', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Durum güncellenemedi.');
        }
    })
    .catch(err => {
        alert('İşlem başarısız: ' + err.message);
    });
}

function renderProductionLines(lines) {
    const grid = document.getElementById('production-lines-grid');
    if (!grid || !lines) return;

    let html = '';
    lines.forEach(pl => {
        let content = '';
        if (pl.active_work_order) {
            const awo = pl.active_work_order;
            content = `
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-weight: 800; font-size: 13.5px; color: #0f172a;">${pl.name}</span>
                        <span style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 12px; background: ${pl.badge_bg}; color: ${pl.badge_color}; border: 1px solid ${pl.badge_border};">${pl.status_badge}</span>
                    </div>
                    <div style="font-size: 12.5px; font-weight: 700; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${awo.product_name}</div>
                    <div style="font-size: 11.5px; color: #64748b; font-family: monospace; margin-top: 1px;">
                        <a href="/stok-takip/public/mes/work-orders/show?id=${awo.id}" style="color: #2563eb; text-decoration: none; font-weight: 600;">${awo.work_order_no}</a>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; font-weight: 700; color: #0f172a; margin-top: 8px; margin-bottom: 4px;">
                        <span>${Number(awo.produced_quantity).toLocaleString('tr-TR')} / ${Number(awo.planned_quantity).toLocaleString('tr-TR')} PANEL</span>
                        <span style="color: #16a34a;">%${Number(awo.progress_pct).toLocaleString('tr-TR', {minimumFractionDigits: 1, maximumFractionDigits: 1})}</span>
                    </div>
                    <div style="width: 100%; height: 5px; background: #e2e8f0; border-radius: 999px; overflow: hidden; margin-bottom: 4px;">
                        <div style="width: ${Math.min(100, awo.progress_pct)}%; height: 100%; background: #10b981; border-radius: 999px;"></div>
                    </div>
                </div>
                <div style="font-size: 10.5px; color: #64748b; text-align: right; margin-top: 4px;">${awo.estimated_human || ''}</div>
            `;
        } else {
            content = `
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-weight: 800; font-size: 13.5px; color: #0f172a;">${pl.name}</span>
                        <span style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 12px; background: ${pl.badge_bg}; color: ${pl.badge_color}; border: 1px solid ${pl.badge_border};">${pl.status_badge}</span>
                    </div>
                    <div style="padding-top: 8px; font-size: 12.5px; color: #64748b;">${pl.status_desc || 'Üretim bekliyor'}</div>
                </div>
            `;
        }

        html += `
            <div class="line-card" id="line-card-${pl.id}" style="background: #ffffff; border: 1px solid ${pl.badge_border}; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between; min-height: 125px;">
                ${content}
            </div>
        `;
    });

    grid.innerHTML = html;
}

// Live polling for line statuses every 3 seconds
setInterval(() => {
    fetch('/stok-takip/public/api/mes/lines')
    .then(r => r.json())
    .then(data => {
        if (data && data.production_lines) {
            renderProductionLines(data.production_lines);
        }
    })
    .catch(() => {});
}, 3000);
</script>