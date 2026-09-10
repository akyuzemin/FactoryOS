<?php

$pageTitle = 'Panel Seri Takip & Mamul Deposu';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';

$maxInitialId = 0;
$renderedInitialIds = [];
if (!empty($panelUnits)) {
    foreach ($panelUnits as $p) {
        $pId = (int)$p['id'];
        $renderedInitialIds[] = $pId;
        if ($pId > $maxInitialId) {
            $maxInitialId = $pId;
        }
    }
}
?>

<style>
@keyframes rowHighlight {
    0% { background-color: #ecfdf5; }
    100% { background-color: transparent; }
}
.new-panel-row {
    animation: rowHighlight 3s ease-out;
}
</style>

<main class="main-content">

    <header class="page-header" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                <p class="page-kicker" style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #4338ca; margin: 0;">Mamul Deposu &amp; İzlenebilirlik</p>
                <span style="display: inline-flex; align-items: center; gap: 5px; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe;">
                    550Wp Solar Modül
                </span>
                <span id="live-poll-badge" style="display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;">
                    <span id="live-poll-dot" style="width: 7px; height: 7px; background: #22c55e; border-radius: 50%; display: inline-block;"></span>
                    <span id="live-poll-text">Canlı İzleme Aktif (5s)</span>
                </span>
            </div>
            <h1 style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 0 0 6px 0; letter-spacing: -0.02em;">Panel Seri Takip &amp; Mamul Deposu</h1>
            <p class="page-description" style="font-size: 13.5px; color: #64748b; margin: 0;">MES tarafından üretilen tüm solar panellerin tekil seri numaraları, lokasyonları ve kalite durumları.</p>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <a class="button button-primary" href="/stok-takip/public/mes" style="display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; font-size: 13px; font-weight: 600; text-decoration: none;">
                <span>🏭</span> MES İş Emirleri
            </a>
        </div>
    </header>

    <!-- KPI SUMMARY CARDS -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">Toplam Üretilen Panel</div>
            <div id="kpi-total" style="font-size: 26px; font-weight: 800; color: #0f172a;"><?= number_format($summaryStats['total'], 0, ',', '.') ?></div>
            <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">Sistemde kayıtlı tekil seri</div>
        </div>

        <div style="background: #ffffff; border: 1px solid #bbf7d0; border-radius: 12px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11.5px; font-weight: 700; color: #166534; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">🟢 Depoda / Sevkiyata Hazır</div>
            <div id="kpi-in-stock" style="font-size: 26px; font-weight: 800; color: #15803d;"><?= number_format($summaryStats['in_stock'], 0, ',', '.') ?></div>
            <div style="font-size: 12px; color: #16a34a; margin-top: 4px;">IN_STOCK statüsünde</div>
        </div>

        <div style="background: #ffffff; border: 1px solid #fed7aa; border-radius: 12px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11.5px; font-weight: 700; color: #9a3412; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">📅 Bugün Üretilen</div>
            <div id="kpi-today-produced" style="font-size: 26px; font-weight: 800; color: #c2410c;"><?= number_format($summaryStats['today_produced'], 0, ',', '.') ?></div>
            <div style="font-size: 12px; color: #ea580c; margin-top: 4px;">Bugünkü MES üretimi</div>
        </div>

        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">🚚 Sevk Edilen</div>
            <div id="kpi-shipped" style="font-size: 26px; font-weight: 800; color: #475569;"><?= number_format($summaryStats['shipped'], 0, ',', '.') ?></div>
            <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">Müşteriye çıkışı yapılan</div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="card" style="padding: 16px 20px; margin-bottom: 20px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px;">
        <form method="GET" action="/stok-takip/public/finished-goods" id="finished-goods-filter-form" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <div style="flex: 2; min-width: 200px;">
                <input type="text" name="serial_no" value="<?= htmlspecialchars($filters['serial_no']) ?>" placeholder="Seri No Ara (SP550W-...)" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
            </div>

            <div style="flex: 1.5; min-width: 160px;">
                <input type="text" name="work_order_no" value="<?= htmlspecialchars($filters['work_order_no']) ?>" placeholder="İş Emri No Ara..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
            </div>

            <div style="flex: 1.2; min-width: 140px;">
                <select name="warehouse_id" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
                    <option value="">Tüm Depolar</option>
                    <?php foreach ($warehouses as $wh): ?>
                        <option value="<?= (int)$wh['id'] ?>" <?= $filters['warehouse_id'] === (int)$wh['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($wh['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex: 1.2; min-width: 130px;">
                <select name="status" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
                    <option value="">Tüm Durumlar</option>
                    <option value="IN_STOCK" <?= $filters['status'] === 'IN_STOCK' ? 'selected' : '' ?>>IN_STOCK (Stokta)</option>
                    <option value="QUALITY_PENDING" <?= $filters['status'] === 'QUALITY_PENDING' ? 'selected' : '' ?>>QUALITY_PENDING</option>
                    <option value="QUALITY_APPROVED" <?= $filters['status'] === 'QUALITY_APPROVED' ? 'selected' : '' ?>>QUALITY_APPROVED (Onaylı)</option>
                    <option value="QUALITY_REJECTED" <?= $filters['status'] === 'QUALITY_REJECTED' ? 'selected' : '' ?>>QUALITY_REJECTED (Reddedildi)</option>
                    <option value="QUARANTINE" <?= $filters['status'] === 'QUARANTINE' ? 'selected' : '' ?>>QUARANTINE (Karantina)</option>
                    <option value="SHIPPED" <?= $filters['status'] === 'SHIPPED' ? 'selected' : '' ?>>SHIPPED (Sevk Edildi)</option>
                </select>
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="button button-primary" style="padding: 8px 16px; font-size: 13px; font-weight: 600;">Filtrele</button>
                <a href="/stok-takip/public/finished-goods" class="button" style="padding: 8px 12px; font-size: 13px; text-decoration: none;">Temizle</a>
            </div>
        </form>
    </div>

    <!-- DATA TABLE -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.03); overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13.5px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">
                    <th style="padding: 12px 18px;">Seri No</th>
                    <th style="padding: 12px 18px;">Ürün</th>
                    <th style="padding: 12px 18px;">İş Emri</th>
                    <th style="padding: 12px 18px;">Üretim Hattı</th>
                    <th style="padding: 12px 18px;">Depo / Lokasyon</th>
                    <th style="padding: 12px 18px;">Üretim Tarihi</th>
                    <th style="padding: 12px 18px; text-align: center;">Durum</th>
                    <th style="padding: 12px 18px; text-align: right;">İşlem</th>
                </tr>
            </thead>
            <tbody id="panel-table-body">
                <tr id="empty-row" style="<?= empty($panelUnits) ? '' : 'display: none;' ?>">
                    <td colspan="8" style="padding: 36px; text-align: center; color: #94a3b8; font-size: 14px;">
                        Arama kriterlerine uygun mamul panel kaydı bulunamadı.
                    </td>
                </tr>
                <?php if (!empty($panelUnits)): ?>
                    <?php foreach ($panelUnits as $pu): ?>
                        <tr id="panel-row-<?= (int)$pu['id'] ?>" data-panel-id="<?= (int)$pu['id'] ?>" style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background='#f8fafc';" onmouseout="this.style.background='transparent';">
                            <td style="padding: 14px 18px;">
                                <a href="/stok-takip/public/finished-goods/show?id=<?= (int)$pu['id'] ?>" style="font-family: monospace; font-size: 13.5px; font-weight: 800; color: #4338ca; text-decoration: none;">
                                    <?= htmlspecialchars($pu['serial_no']) ?>
                                </a>
                            </td>
                            <td style="padding: 14px 18px;">
                                <div style="font-weight: 700; color: #0f172a; font-size: 13.5px;"><?= htmlspecialchars($pu['material_name']) ?></div>
                                <span style="font-size: 11px; color: #64748b; font-family: monospace;"><?= htmlspecialchars($pu['material_code']) ?></span>
                            </td>
                            <td style="padding: 14px 18px;">
                                <?php if (!empty($pu['work_order_no'])): ?>
                                    <a href="/stok-takip/public/mes/work-orders/show?id=<?= (int)$pu['work_order_id'] ?>" style="font-weight: 700; color: #2563eb; text-decoration: none; font-size: 13px;">
                                        <?= htmlspecialchars($pu['work_order_no']) ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 14px 18px; color: #475569; font-size: 13px;">
                                <?= htmlspecialchars($pu['line_name'] ?? '-') ?>
                            </td>
                            <td style="padding: 14px 18px;">
                                <div style="font-weight: 600; color: #0f172a; font-size: 13px;"><?= htmlspecialchars($pu['warehouse_name'] ?? '-') ?></div>
                                <div style="font-size: 11.5px; color: #64748b;"><?= htmlspecialchars($pu['location_name'] ?? '-') ?></div>
                            </td>
                            <td style="padding: 14px 18px; font-size: 12.5px; color: #475569;">
                                <?= date('d.m.Y H:i:s', strtotime($pu['produced_at'])) ?>
                            </td>
                            <td style="padding: 14px 18px; text-align: center;">
                                <?php if ($pu['status'] === 'IN_STOCK'): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;">
                                        🟢 STOKTA
                                    </span>
                                <?php elseif ($pu['status'] === 'QUALITY_PENDING'): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: #fef9c3; color: #854d0e; border: 1px solid #fef08a;">
                                        🟡 KALİTE BEKLİYOR
                                    </span>
                                <?php elseif ($pu['status'] === 'QUALITY_APPROVED'): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">
                                        ✅ ONAYLANDI
                                    </span>
                                <?php elseif ($pu['status'] === 'QUALITY_REJECTED'): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;">
                                        🔴 REDDEDİLDİ
                                    </span>
                                <?php elseif ($pu['status'] === 'QUARANTINE'): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa;">
                                        ⚠️ KARANTİNA
                                    </span>
                                <?php elseif ($pu['status'] === 'SHIPPED'): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: #f8fafc; color: #475569; border: 1px solid #cbd5e1;">
                                        🚚 SEVK EDİLDİ
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa;">
                                        <?= htmlspecialchars($pu['status']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 14px 18px; text-align: right; white-space: nowrap;">
                                <a href="/stok-takip/public/finished-goods/show?id=<?= (int)$pu['id'] ?>" class="button button-small" style="padding: 5px 10px; font-size: 12px; text-decoration: none;">
                                    Pasaport &rarr;
                                </a>
                                <a href="/stok-takip/public/finished-goods/serial?serial_no=<?= urlencode($pu['serial_no']) ?>" class="button button-small" style="padding: 5px 8px; font-size: 12px; text-decoration: none; background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe;">
                                    📱 QR
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- PAGINATION -->
        <?php if ($totalPages > 1): ?>
            <div style="padding: 14px 20px; background: #fafafa; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 12.5px; color: #64748b;">
                    Toplam <?= $totalCount ?> kayıttan Sayfa <?= $page ?> / <?= $totalPages ?>
                </span>
                <div style="display: flex; gap: 6px;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="button button-small">&larr; Önceki</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="button button-small">Sonraki &rarr;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

</main>

<script>
(function() {
    let lastPanelId = <?= (int)$maxInitialId ?>;
    const renderedIds = new Set(<?= json_encode($renderedInitialIds) ?>);
    const tableBody = document.getElementById('panel-table-body');
    const emptyRow = document.getElementById('empty-row');
    const badgeText = document.getElementById('live-poll-text');
    const badgeDot = document.getElementById('live-poll-dot');

    const kpiTotal = document.getElementById('kpi-total');
    const kpiInStock = document.getElementById('kpi-in-stock');
    const kpiToday = document.getElementById('kpi-today-produced');
    const kpiShipped = document.getElementById('kpi-shipped');

    function formatNumber(num) {
        return new Intl.NumberFormat('tr-TR').format(num);
    }

    function getStatusBadgeHtml(status) {
        switch(status) {
            case 'IN_STOCK':
                return '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;">🟢 STOKTA</span>';
            case 'QUALITY_PENDING':
                return '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: #fef9c3; color: #854d0e; border: 1px solid #fef08a;">🟡 KALİTE BEKLİYOR</span>';
            case 'QUALITY_APPROVED':
                return '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">✅ ONAYLANDI</span>';
            case 'QUALITY_REJECTED':
                return '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;">🔴 REDDEDİLDİ</span>';
            case 'QUARANTINE':
                return '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa;">⚠️ KARANTİNA</span>';
            case 'SHIPPED':
                return '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: #f8fafc; color: #475569; border: 1px solid #cbd5e1;">🚚 SEVK EDİLDİ</span>';
            default:
                return '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa;">' + (status || '-') + '</span>';
        }
    }

    function createRowElement(item) {
        const tr = document.createElement('tr');
        tr.id = 'panel-row-' + item.id;
        tr.dataset.panelId = item.id;
        tr.className = 'new-panel-row';
        tr.style.borderBottom = '1px solid #f1f5f9';
        tr.style.transition = 'background 0.15s ease';
        tr.onmouseover = function() { this.style.background = '#f8fafc'; };
        tr.onmouseout = function() { this.style.background = 'transparent'; };

        const woHtml = item.work_order_id > 0 
            ? '<a href="/stok-takip/public/mes/work-orders/show?id=' + item.work_order_id + '" style="font-weight: 700; color: #2563eb; text-decoration: none; font-size: 13px;">' + (item.work_order_no || '-') + '</a>'
            : '<span style="color: #94a3b8;">-</span>';

        tr.innerHTML = `
            <td style="padding: 14px 18px;">
                <a href="${item.show_url}" style="font-family: monospace; font-size: 13.5px; font-weight: 800; color: #4338ca; text-decoration: none;">
                    ${item.serial_no}
                </a>
            </td>
            <td style="padding: 14px 18px;">
                <div style="font-weight: 700; color: #0f172a; font-size: 13.5px;">${item.material_name}</div>
                <span style="font-size: 11px; color: #64748b; font-family: monospace;">${item.material_code}</span>
            </td>
            <td style="padding: 14px 18px;">
                ${woHtml}
            </td>
            <td style="padding: 14px 18px; color: #475569; font-size: 13px;">
                ${item.line_name}
            </td>
            <td style="padding: 14px 18px;">
                <div style="font-weight: 600; color: #0f172a; font-size: 13px;">${item.warehouse_name}</div>
                <div style="font-size: 11.5px; color: #64748b;">${item.location_name}</div>
            </td>
            <td style="padding: 14px 18px; font-size: 12.5px; color: #475569;">
                ${item.produced_at}
            </td>
            <td style="padding: 14px 18px; text-align: center;">
                ${getStatusBadgeHtml(item.status)}
            </td>
            <td style="padding: 14px 18px; text-align: right; white-space: nowrap;">
                <a href="${item.show_url}" class="button button-small" style="padding: 5px 10px; font-size: 12px; text-decoration: none;">
                    Pasaport &rarr;
                </a>
                <a href="${item.qr_url}" class="button button-small" style="padding: 5px 8px; font-size: 12px; text-decoration: none; background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe;">
                    📱 QR
                </a>
            </td>
        `;

        return tr;
    }

    async function pollLivePanels() {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('last_id', lastPanelId);

        try {
            const response = await fetch('/stok-takip/public/finished-goods/live?' + urlParams.toString(), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                }
            });

            if (!response.ok) {
                if (badgeDot) badgeDot.style.background = '#eab308';
                if (badgeText) badgeText.textContent = 'Yeniden bağlanıyor...';
                return;
            }

            const data = await response.json();

            if (data && data.success) {
                if (badgeDot) badgeDot.style.background = '#22c55e';
                if (badgeText) badgeText.textContent = 'Canlı İzleme Aktif (5s)';

                // Update KPI Counters
                if (data.counters) {
                    if (kpiTotal && data.counters.total !== undefined) kpiTotal.textContent = formatNumber(data.counters.total);
                    if (kpiInStock && data.counters.in_stock !== undefined) kpiInStock.textContent = formatNumber(data.counters.in_stock);
                    if (kpiToday && data.counters.today_produced !== undefined) kpiToday.textContent = formatNumber(data.counters.today_produced);
                    if (kpiShipped && data.counters.shipped !== undefined) kpiShipped.textContent = formatNumber(data.counters.shipped);
                }

                // Process new items (prepend in correct order)
                if (Array.isArray(data.new_items) && data.new_items.length > 0) {
                    // Hide empty row if visible
                    if (emptyRow) emptyRow.style.display = 'none';

                    // new_items comes in DESC order (highest ID first)
                    for (let i = data.new_items.length - 1; i >= 0; i--) {
                        const item = data.new_items[i];
                        if (renderedIds.has(item.id)) {
                            continue; // Skip duplicate
                        }

                        const rowEl = createRowElement(item);
                        // Prepend at the top of the table body
                        if (emptyRow && emptyRow.nextSibling) {
                            tableBody.insertBefore(rowEl, emptyRow.nextSibling);
                        } else {
                            tableBody.insertBefore(rowEl, tableBody.firstChild);
                        }

                        renderedIds.add(item.id);
                        if (item.id > lastPanelId) {
                            lastPanelId = item.id;
                        }
                    }
                }

                if (data.last_id && data.last_id > lastPanelId) {
                    lastPanelId = data.last_id;
                }
            }
        } catch (err) {
            if (badgeDot) badgeDot.style.background = '#eab308';
            if (badgeText) badgeText.textContent = 'Bağlantı bekleniyor...';
        }
    }

    // Start 5-second polling
    const pollInterval = setInterval(pollLivePanels, 5000);

    // Clean up timer on page leave
    window.addEventListener('beforeunload', function() {
        clearInterval(pollInterval);
    });
})();
</script>