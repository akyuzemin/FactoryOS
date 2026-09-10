<?php

$pageTitle = 'Malzeme Detayı: ' . $material['name'];

$stock = (float)($material['total_stock'] ?? 0);
$minStock = (float)($material['min_stock'] ?? 0);
$maxStock = !empty($material['max_stock']) ? (float)$material['max_stock'] : null;
$unitSymbol = $material['symbol'] ?: ($material['unit'] ?: 'AD');

$status = MaterialController::classifyStockStatus($stock, $minStock);
$statusLabel = $status['label'];
$statusBg = $status['bg'];
$statusColor = $status['color'];
$statusDot = $status['dot'];
$statusBorder = $status['border'];

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <!-- Breadcrumb & Header -->
    <div style="margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: #64748b; margin-bottom: 8px;">
            <a href="/stok-takip/public/materials" style="color: #4338ca; text-decoration: none; font-weight: 600;">Malzemeler</a>
            <span>&rsaquo;</span>
            <span style="color: #0f172a; font-weight: 600;"><?= htmlspecialchars($material['name']) ?></span>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                    <span class="code-badge" style="font-family: monospace; font-size: 13px; font-weight: 800; padding: 4px 10px; background: #e0e7ff; color: #3730a3; border-radius: 6px; border: 1px solid #c7d2fe;">
                        <?= htmlspecialchars($material['code']) ?>
                    </span>
                    <span style="font-size: 12.5px; font-weight: 700; color: #475569; background: #f1f5f9; padding: 3px 10px; border-radius: 20px;">
                        <?= htmlspecialchars($material['category']) ?>
                    </span>
                </div>
                <h1 style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 0 0 6px 0; letter-spacing: -0.02em;">
                    <?= htmlspecialchars($material['name']) ?>
                </h1>
                <?php if (!empty($material['description'])): ?>
                    <p style="font-size: 13.5px; color: #64748b; margin: 0; max-width: 600px;">
                        <?= htmlspecialchars($material['description']) ?>
                    </p>
                <?php endif; ?>
            </div>

            <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                <a class="button" href="/stok-takip/public/materials" style="padding: 8px 14px; font-size: 13px; font-weight: 600; text-decoration: none;">
                    &larr; Listeye Dön
                </a>
                <?php if ($can('material.update')): ?>
                    <a class="button" href="/stok-takip/public/materials/edit?id=<?= (int)$material['id'] ?>" style="padding: 8px 14px; font-size: 13px; font-weight: 600; text-decoration: none;">
                        ✏️ Düzenle
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MAIN STOCK TELEMETRY & ACTIONS BANNER -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 24px; margin-bottom: 24px; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; align-items: center;">
            
            <!-- Left: Big Stock Metric Card -->
            <div>
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">Toplam Mevcut Stok</span>
                    <span id="detail-stock-badge" style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; background: <?= $statusBg ?>; color: <?= $statusColor ?>; border: 1px solid <?= $statusBorder ?>; transition: all 0.3s ease;">
                        <span class="status-dot" style="width: 8px; height: 8px; border-radius: 50%; background: <?= $statusDot ?>;"></span>
                        <span class="status-text"><?= $statusLabel ?></span>
                    </span>
                </div>
                
                <div id="detail-stock-container" style="font-size: 40px; font-weight: 900; color: #0f172a; line-height: 1.1; letter-spacing: -0.02em; padding: 4px 8px; border-radius: 8px; transition: background 0.8s ease;">
                    <span id="detail-stock-val"><?= number_format($stock, 2, ',', '.') ?></span>
                    <span style="font-size: 18px; font-weight: 700; color: #64748b; margin-left: 4px;"><?= htmlspecialchars($unitSymbol) ?></span>
                </div>

                <!-- Limits & Price indicators -->
                <div style="margin-top: 14px; display: flex; flex-wrap: wrap; gap: 16px; font-size: 12.5px; color: #64748b; border-top: 1px solid #f1f5f9; padding-top: 10px;">
                    <div>Birim Fiyat: <strong style="color: #15803d; font-family: monospace;"><?= number_format((float)($material['unit_price'] ?? 0), 2, ',', '.') ?> <?= htmlspecialchars($material['currency'] ?: 'TL') ?></strong></div>
                    <div>Min. Stok: <strong style="color: #0f172a;"><?= number_format($minStock, 2, ',', '.') ?> <?= htmlspecialchars($unitSymbol) ?></strong></div>
                    <?php if ($maxStock !== null): ?>
                        <div>Maks. Stok: <strong style="color: #0f172a;"><?= number_format($maxStock, 2, ',', '.') ?> <?= htmlspecialchars($unitSymbol) ?></strong></div>
                    <?php endif; ?>
                    <div>Aktif Lokasyon Sayısı: <strong style="color: #0f172a;"><?= count($locationBalances) ?> Depo/Raf</strong></div>
                </div>
            </div>

            <!-- Right: Fast Stock Action Buttons -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px;">
                <div style="font-size: 11.5px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 12px;">
                    STOK İŞLEMLERİ
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <a href="/stok-takip/public/stock/in?material_id=<?= (int)$material['id'] ?>" style="display: flex; align-items: center; justify-content: center; gap: 6px; padding: 11px 14px; background: #16a34a; color: #ffffff; border-radius: 8px; font-weight: 700; font-size: 12.5px; text-decoration: none; box-shadow: 0 1px 2px rgba(22, 163, 74, 0.25); letter-spacing: 0.02em;">
                        <span>+</span> STOK GİRİŞİ
                    </a>
                    <a href="/stok-takip/public/stock/out?material_id=<?= (int)$material['id'] ?>" style="display: flex; align-items: center; justify-content: center; gap: 6px; padding: 11px 14px; background: #dc2626; color: #ffffff; border-radius: 8px; font-weight: 700; font-size: 12.5px; text-decoration: none; box-shadow: 0 1px 2px rgba(220, 38, 38, 0.25); letter-spacing: 0.02em;">
                        <span>-</span> STOK ÇIKIŞI
                    </a>
                    <a href="/stok-takip/public/stock/transfer?material_id=<?= (int)$material['id'] ?>" style="display: flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 12px; background: #ffffff; border: 1px solid #cbd5e1; color: #1e293b; border-radius: 8px; font-weight: 700; font-size: 12px; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
                        <span>⇄</span> TRANSFER
                    </a>
                    <a href="/stok-takip/public/stock/adjustment?material_id=<?= (int)$material['id'] ?>" style="display: flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 12px; background: #ffffff; border: 1px solid #cbd5e1; color: #1e293b; border-radius: 8px; font-weight: 700; font-size: 12px; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
                        <span>⚙️</span> SAYIM / DÜZELTME
                    </a>
                </div>
            </div>

        </div>
    </div>

    <!-- DEPO BAZLI STOK DAĞILIMI (WAREHOUSE BREAKDOWN) -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.03); margin-bottom: 24px; overflow: hidden;">
        <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; background: #fafafa; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0;">🏢 Depo ve Lokasyon Bazlı Stok Dağılımı</h3>
                <span style="font-size: 12px; color: #64748b;">Bu malzemenin hangi depolarda ve raflarda bulunduğu anlık olarak listelenmektedir.</span>
            </div>
            <div style="font-size: 12.5px; font-weight: 700; color: #334155;">
                Toplam: <?= number_format($stock, 2, ',', '.') ?> <?= htmlspecialchars($unitSymbol) ?>
            </div>
        </div>

        <?php if (empty($locationBalances)): ?>
            <div style="padding: 24px; text-align: center; color: #94a3b8; font-size: 13.5px;">
                Bu malzeme için herhangi bir depoda aktif stok bakiyesi bulunmuyor.
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; padding: 20px;">
                <?php foreach ($locationBalances as $loc): ?>
                    <?php
                    $locQty = (float)$loc['quantity'];
                    $locPct = $stock > 0 ? round(($locQty / $stock) * 100, 1) : 0;
                    ?>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                            <div>
                                <span style="font-size: 11px; font-weight: 700; color: #4338ca; text-transform: uppercase; letter-spacing: 0.04em;">
                                    <?= htmlspecialchars($loc['warehouse_name']) ?> (<?= htmlspecialchars($loc['warehouse_code']) ?>)
                                </span>
                                <div style="font-size: 14px; font-weight: 700; color: #0f172a; margin-top: 2px;">
                                    <?= htmlspecialchars($loc['location_name']) ?> <span style="font-size: 11.5px; color: #64748b; font-weight: normal;">(<?= htmlspecialchars($loc['location_code']) ?>)</span>
                                </div>
                            </div>
                            <span style="font-size: 11px; font-weight: 700; background: #e2e8f0; color: #334155; padding: 2px 6px; border-radius: 4px;">
                                %<?= $locPct ?>
                            </span>
                        </div>

                        <div style="font-size: 20px; font-weight: 800; color: #0f172a; margin: 8px 0 4px 0;">
                            <?= number_format($locQty, 2, ',', '.') ?>
                            <span style="font-size: 12px; font-weight: 600; color: #64748b;"><?= htmlspecialchars($unitSymbol) ?></span>
                        </div>

                        <div style="width: 100%; height: 5px; background: #e2e8f0; border-radius: 3px; overflow: hidden; margin-top: 6px;">
                            <div style="width: <?= $locPct ?>%; height: 100%; background: #3b82f6; border-radius: 3px;"></div>
                        </div>

                        <div style="font-size: 10.5px; color: #94a3b8; margin-top: 6px; text-align: right;">
                            Son güncelleme: <?= date('d.m.Y H:i', strtotime($loc['updated_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- SON STOK HAREKETLERİ & MES ENTEGRASYON BİLGİSİ -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.03); overflow: hidden;">
        <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; background: #fafafa; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0;">📜 Son Stok Hareketleri & MES Üretim Kayıtları</h3>
                <span style="font-size: 12px; color: #64748b;">Bu malzemenin stok giriş, çıkış, transfer ve MES otomatik üretim tüketim geçmişi.</span>
            </div>
            <a href="/stok-takip/public/stock-movements" style="font-size: 12.5px; font-weight: 600; color: #2563eb; text-decoration: none;">
                Tüm Hareketleri Gör &rarr;
            </a>
        </div>

        <?php if (empty($recentMovements)): ?>
            <div style="padding: 30px; text-align: center; color: #94a3b8; font-size: 13.5px;">
                Henüz bu malzeme için bir stok hareketi kaydedilmemiş.
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">
                        <th style="padding: 12px 16px;">Tarih</th>
                        <th style="padding: 12px 16px;">İşlem</th>
                        <th style="padding: 12px 16px; text-align: right;">Miktar</th>
                        <th style="padding: 12px 16px;">Depo / Lokasyon</th>
                        <th style="padding: 12px 16px;">Referans No</th>
                        <th style="padding: 12px 16px;">Kaynak / MES</th>
                        <th style="padding: 12px 16px;">Açıklama / Not</th>
                        <th style="padding: 12px 16px;">Kullanıcı</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentMovements as $mv): ?>
                    <?php
                    $isMes = (str_starts_with((string)$mv['reference_no'], 'PRD-') || stripos((string)$mv['description'], 'MES') !== false);
                    $mType = strtoupper($mv['movement_type'] ?? 'ENTRY');
                    
                    $isPositive = in_array($mType, ['IN', 'ENTRY', 'PRODUCTION_IN', 'RETURN']);
                    $isNegative = in_array($mType, ['OUT', 'EXIT', 'PRODUCTION_OUT', 'SCRAP']);

                    $typeLabel = match($mType) {
                        'IN', 'ENTRY'          => 'Giriş',
                        'OUT', 'EXIT'          => 'Çıkış',
                        'TRANSFER_IN'          => 'Transfer Giriş',
                        'TRANSFER_OUT'         => 'Transfer Çıkış',
                        'TRANSFER'             => 'Transfer',
                        'ADJUSTMENT'           => 'Düzeltme',
                        'SCRAP'                => 'Fire / Hurda',
                        'RETURN'               => 'İade',
                        'PRODUCTION_OUT'       => 'Üretim Tüketimi',
                        'PRODUCTION_IN'        => 'Mamul Üretimi',
                        default                => $mType
                    };

                    $typeBg = $isPositive ? '#f0fdf4' : ($isNegative ? '#fef2f2' : '#f8fafc');
                    $typeColor = $isPositive ? '#166534' : ($isNegative ? '#991b1b' : '#334155');
                    $typeBorder = $isPositive ? '#bbf7d0' : ($isNegative ? '#fecaca' : '#e2e8f0');

                    $qtyNum = (float)$mv['quantity'];
                    ?>
                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.12s ease;">
                        <td style="padding: 12px 16px; color: #475569; white-space: nowrap;">
                            <?= date('d.m.Y H:i', strtotime($mv['created_at'])) ?>
                        </td>

                        <td style="padding: 12px 16px;">
                            <span style="display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 11.5px; font-weight: 700; background: <?= $typeBg ?>; color: <?= $typeColor ?>; border: 1px solid <?= $typeBorder ?>;">
                                <?= htmlspecialchars($typeLabel) ?>
                            </span>
                        </td>

                        <td style="padding: 12px 16px; text-align: right; font-weight: 800; font-size: 13.5px; color: <?= $isPositive ? '#16a34a' : ($isNegative ? '#dc2626' : '#0f172a') ?>;">
                            <?= ($isPositive ? '+' : ($isNegative ? '-' : '')) . number_format(abs($qtyNum), 2, ',', '.') ?>
                            <span style="font-size: 11px; font-weight: 600; color: #64748b;"><?= htmlspecialchars($unitSymbol) ?></span>
                        </td>

                        <td style="padding: 12px 16px; color: #334155;">
                            <strong><?= htmlspecialchars($mv['warehouse_name']) ?></strong> / <?= htmlspecialchars($mv['location_name']) ?>
                        </td>

                        <td style="padding: 12px 16px; font-family: monospace; font-size: 11.5px; color: #475569;">
                            <?= htmlspecialchars($mv['reference_no'] ?: '-') ?>
                        </td>

                        <td style="padding: 12px 16px;">
                            <?php if ($isMes): ?>
                                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; font-size: 11px; font-weight: 800; background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; border-radius: 6px;">
                                    🏭 MES / Üretim
                                </span>
                            <?php else: ?>
                                <span style="display: inline-block; padding: 2px 6px; font-size: 11px; color: #64748b; background: #f1f5f9; border-radius: 4px;">
                                    📦 Manuel Depo
                                </span>
                            <?php endif; ?>
                        </td>

                        <td style="padding: 12px 16px; color: #64748b; font-size: 12px; max-width: 250px;">
                            <?= htmlspecialchars($mv['description'] ?: '-') ?>
                        </td>

                        <td style="padding: 12px 16px; color: #475569; font-size: 12px;">
                            <?= htmlspecialchars($mv['username'] ?: 'Sistem') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</main>

<script>
const currentMaterialId = <?= (int)$material['id'] ?>;
let previousDetailStock = <?= (float)$stock ?>;
let isFetchingDetailLive = false;

function pollDetailLiveStock() {
    if (isFetchingDetailLive) return;
    isFetchingDetailLive = true;

    fetch('/stok-takip/public/api/materials/live-stock')
        .then(response => {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(data => {
            isFetchingDetailLive = false;
            if (!data.success || !Array.isArray(data.materials)) return;

            const mat = data.materials.find(m => m.id === currentMaterialId);
            if (!mat) return;

            const newStock = parseFloat(mat.total_stock);
            const valEl = document.getElementById('detail-stock-val');
            const containerEl = document.getElementById('detail-stock-container');
            const badgeEl = document.getElementById('detail-stock-badge');

            if (valEl) {
                valEl.innerText = mat.formatted_stock;
            }

            if (badgeEl) {
                badgeEl.style.background = mat.status_bg;
                badgeEl.style.color = mat.status_color;
                badgeEl.style.borderColor = mat.status_border;
                const dot = badgeEl.querySelector('.status-dot');
                const txt = badgeEl.querySelector('.status-text');
                if (dot) dot.style.background = mat.status_dot;
                if (txt) txt.innerText = mat.status_label;
            }

            if (containerEl && Math.abs(newStock - previousDetailStock) > 0.001) {
                const isIncrease = newStock > previousDetailStock;
                containerEl.style.background = isIncrease ? '#dcfce7' : '#fee2e2';
                setTimeout(() => {
                    containerEl.style.background = 'transparent';
                }, 1200);
            }

            previousDetailStock = newStock;
        })
        .catch(err => {
            isFetchingDetailLive = false;
            console.warn('Detail stock live polling error:', err);
        });
}

document.addEventListener('DOMContentLoaded', function() {
    setInterval(pollDetailLiveStock, 1500);
});
</script>


