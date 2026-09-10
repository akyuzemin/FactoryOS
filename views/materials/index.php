<?php

$pageTitle = 'Malzemeler & Stok Kontrol Merkezi';

$totalMaterials = count($materials);
$sufficientCount = 0;
$lowCount = 0;
$criticalCount = 0;
$outOfStockCount = 0;

$processedMaterials = [];
foreach ($materials as $m) {
    $stock = (float)($m['total_stock'] ?? 0);
    $minStock = (float)($m['min_stock'] ?? 0);
    $unitSymbol = $m['symbol'] ?: ($m['unit'] ?: 'AD');
    
    $status = MaterialController::classifyStockStatus($stock, $minStock);
    
    if ($status['key'] === 'out_of_stock') {
        $outOfStockCount++;
    } elseif ($status['key'] === 'critical') {
        $criticalCount++;
    } elseif ($status['key'] === 'low') {
        $lowCount++;
    } else {
        $sufficientCount++;
    }

    $formattedStock = (floor($stock) == $stock)
        ? number_format($stock, 0, ',', '.')
        : rtrim(rtrim(number_format($stock, 2, ',', '.'), '0'), ',');

    $m['status_info'] = $status;
    $m['formatted_stock'] = $formattedStock;
    $m['display_stock'] = $formattedStock . ' ' . $unitSymbol;
    $m['unit_symbol'] = $unitSymbol;
    $processedMaterials[] = $m;
}

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content" style="max-width: 1320px; padding: 24px 32px;">

    <!-- KOMPAKT CANLI MES ÜRETİM BANNERI (SADE & HAREKETLİ) -->
    <div id="live-mes-banner" style="display: none; margin-bottom: 20px; padding: 12px 18px; border-radius: 10px; background: #0f172a; color: #f8fafc; font-size: 13px; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12); transition: all 0.3s ease;">
        <!-- Filled dynamically by liveStockPoller JS -->
    </div>

    <!-- BAŞLIK & ÜST AKSİYONLAR -->
    <header style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">MALZEMELER</h1>
            <span id="live-sync-indicator" style="display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;">
                <span style="width: 6px; height: 6px; border-radius: 50%; background: #22c55e; animation: pulseDot 1.5s infinite;"></span>
                Canlı Stok
            </span>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if ($can('material.create')): ?>
                <a class="button button-primary" href="/stok-takip/public/materials/create" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; font-size: 13px; font-weight: 600; background: #2563eb; color: #ffffff; border-radius: 8px; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                    <span>+</span> Yeni Malzeme
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- 4 SADE KPI KARTI (STOK DURUMU) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 20px;">
        
        <!-- 🟢 YETERLİ -->
        <div onclick="setFilter('sufficient')" class="kpi-card" id="kpi-card-sufficient" style="background: #ffffff; border: 1px solid #bbf7d0; border-radius: 10px; padding: 14px 18px; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                <span style="font-size: 11.5px; font-weight: 700; color: #166534; letter-spacing: 0.04em;">🟢 YETERLİ</span>
                <span style="font-size: 11px; color: #16a34a; font-weight: 600;">Min. Üzeri</span>
            </div>
            <div id="kpi-sufficient" style="font-size: 26px; font-weight: 800; color: #15803d; line-height: 1.1;"><?= $sufficientCount ?></div>
            <div style="font-size: 11.5px; color: #64748b; margin-top: 4px;">Sorunsuz malzeme</div>
        </div>

        <!-- 🟡 DÜŞÜK -->
        <div onclick="setFilter('low')" class="kpi-card" id="kpi-card-low" style="background: #ffffff; border: 1px solid #fde68a; border-radius: 10px; padding: 14px 18px; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                <span style="font-size: 11.5px; font-weight: 700; color: #92400e; letter-spacing: 0.04em;">🟡 DÜŞÜK</span>
                <span style="font-size: 11px; color: #d97706; font-weight: 600;">Sınıra Yakın</span>
            </div>
            <div id="kpi-low" style="font-size: 26px; font-weight: 800; color: #b45309; line-height: 1.1;"><?= $lowCount ?></div>
            <div style="font-size: 11.5px; color: #64748b; margin-top: 4px;">İkmal gerekebilir</div>
        </div>

        <!-- 🔴 KRİTİK -->
        <div onclick="setFilter('critical')" class="kpi-card" id="kpi-card-critical" style="background: #ffffff; border: 1px solid #fecaca; border-radius: 10px; padding: 14px 18px; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                <span style="font-size: 11.5px; font-weight: 700; color: #991b1b; letter-spacing: 0.04em;">🔴 KRİTİK</span>
                <span style="font-size: 11px; color: #dc2626; font-weight: 600;">Min. Altında</span>
            </div>
            <div id="kpi-critical" style="font-size: 26px; font-weight: 800; color: #b91c1c; line-height: 1.1;"><?= $criticalCount ?></div>
            <div style="font-size: 11.5px; color: #64748b; margin-top: 4px;">Acil müdahale</div>
        </div>

        <!-- ⚫ STOK YOK -->
        <div onclick="setFilter('out_of_stock')" class="kpi-card" id="kpi-card-out_of_stock" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 10px; padding: 14px 18px; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                <span style="font-size: 11.5px; font-weight: 700; color: #334155; letter-spacing: 0.04em;">⚫ STOK YOK</span>
                <span style="font-size: 11px; color: #64748b; font-weight: 600;">0 Bakiye</span>
            </div>
            <div id="kpi-out_of_stock" style="font-size: 26px; font-weight: 800; color: #1e293b; line-height: 1.1;"><?= $outOfStockCount ?></div>
            <div style="font-size: 11.5px; color: #64748b; margin-top: 4px;">Tükenmiş malzeme</div>
        </div>

    </div>

    <!-- ARAMA VE BASİT FİLTRE ÇUBUĞU -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 16px;">
        
        <!-- TEK ARAMA KUTUSU: "Malzeme ara..." -->
        <div style="flex: 1; min-width: 260px; max-width: 460px; position: relative;">
            <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 14px; color: #94a3b8; pointer-events: none;">🔍</span>
            <input 
                type="text" 
                id="search-input" 
                placeholder="Malzeme ara..." 
                oninput="applyFilterAndSearch()" 
                style="width: 100%; padding: 10px 36px 10px 38px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; background: #ffffff; color: #0f172a; outline: none; transition: border-color 0.2s ease, box-shadow 0.2s ease;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.15)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';"
            >
            <button id="search-clear-btn" type="button" onclick="clearSearch()" style="display: none; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; font-size: 16px; color: #94a3b8; cursor: pointer; padding: 0 4px;">&times;</button>
        </div>

        <!-- BASİT FİLTRE SEKMELERİ (Tümü | Yeterli | Düşük | Kritik | Stok Yok) -->
        <div style="display: inline-flex; background: #f1f5f9; padding: 3px; border-radius: 8px; gap: 2px;">
            <button type="button" class="filter-tab active" data-filter="all" onclick="setFilter('all')">
                Tümü (<span id="pill-count-all"><?= $totalMaterials ?></span>)
            </button>
            <button type="button" class="filter-tab" data-filter="sufficient" onclick="setFilter('sufficient')">
                🟢 Yeterli (<span id="pill-count-sufficient"><?= $sufficientCount ?></span>)
            </button>
            <button type="button" class="filter-tab" data-filter="low" onclick="setFilter('low')">
                🟡 Düşük (<span id="pill-count-low"><?= $lowCount ?></span>)
            </button>
            <button type="button" class="filter-tab" data-filter="critical" onclick="setFilter('critical')">
                🔴 Kritik (<span id="pill-count-critical"><?= $criticalCount ?></span>)
            </button>
            <button type="button" class="filter-tab" data-filter="out_of_stock" onclick="setFilter('out_of_stock')">
                ⚫ Stok Yok (<span id="pill-count-out_of_stock"><?= $outOfStockCount ?></span>)
            </button>
        </div>

    </div>

    <!-- SADE STOK TABLOSU: MALZEME | MEVCUT STOK | DURUM | İŞLEM -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); overflow: hidden;">
        <table id="materials-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">
                    <th style="padding: 12px 20px;">MALZEME</th>
                    <th style="padding: 12px 20px; text-align: right;">MEVCUT STOK</th>
                    <th style="padding: 12px 20px; text-align: center;">DURUM</th>
                    <th style="padding: 12px 20px; text-align: right; width: 60px;">İŞLEM</th>
                </tr>
            </thead>

            <tbody id="materials-table-body">
            <?php foreach ($processedMaterials as $m): ?>
                <?php
                $matId = (int)$m['id'];
                $st = $m['status_info'];
                ?>
                <tr 
                    class="material-row" 
                    id="mat-row-<?= $matId ?>" 
                    data-id="<?= $matId ?>" 
                    data-name="<?= htmlspecialchars(mb_strtolower($m['name'], 'UTF-8')) ?>" 
                    data-code="<?= htmlspecialchars(mb_strtolower($m['code'], 'UTF-8')) ?>" 
                    data-category="<?= htmlspecialchars(mb_strtolower($m['category'], 'UTF-8')) ?>" 
                    data-status="<?= $st['key'] ?>" 
                    data-stock="<?= (float)$m['total_stock'] ?>" 
                    onclick="window.location.href='/stok-takip/public/materials/show?id=<?= $matId ?>';" 
                    style="border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: background 0.2s ease;"
                >
                    <!-- MALZEME -->
                    <td style="padding: 14px 20px;">
                        <div style="font-weight: 700; color: #0f172a; font-size: 14px; line-height: 1.3;">
                            <?= htmlspecialchars($m['name']) ?>
                        </div>
                        <div style="margin-top: 3px; font-size: 11.5px; color: #64748b; font-family: monospace; display: flex; align-items: center; gap: 6px;">
                            <span style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0; font-weight: 600; color: #475569;"><?= htmlspecialchars($m['code']) ?></span>
                            <span>&bull;</span>
                            <span style="font-family: inherit; color: #64748b;"><?= htmlspecialchars($m['category']) ?></span>
                        </div>
                    </td>

                    <!-- MEVCUT STOK -->
                    <td style="padding: 14px 20px; text-align: right;">
                        <div id="stock-val-<?= $matId ?>" style="font-size: 15px; font-weight: 800; color: <?= (float)$m['total_stock'] > 0 ? '#0f172a' : '#94a3b8' ?>; font-variant-numeric: tabular-nums;">
                            <span class="stock-number"><?= $m['formatted_stock'] ?></span>
                            <span style="font-size: 12px; font-weight: 600; color: #64748b; margin-left: 3px;"><?= htmlspecialchars($m['unit_symbol']) ?></span>
                        </div>
                    </td>

                    <!-- DURUM -->
                    <td style="padding: 14px 20px; text-align: center;">
                        <span id="badge-<?= $matId ?>" style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; background: <?= $st['bg'] ?>; color: <?= $st['color'] ?>; border: 1px solid <?= $st['border'] ?>; transition: all 0.25s ease;">
                            <span class="status-dot" style="width: 7px; height: 7px; border-radius: 50%; background: <?= $st['dot'] ?>;"></span>
                            <span class="status-text"><?= $st['label'] ?></span>
                        </span>
                    </td>

                    <!-- İŞLEM -->
                    <td style="padding: 14px 20px; text-align: right; color: #94a3b8; font-size: 18px; font-weight: bold;">
                        <span class="action-arrow" style="transition: transform 0.2s ease; display: inline-block;">&rarr;</span>
                    </td>
                </tr>
            <?php endforeach; ?>

            <tr id="no-materials-row" style="display: none;">
                <td colspan="4" style="padding: 36px 20px; text-align: center; color: #64748b; font-size: 14px;">
                    Arama kriterlerine uygun malzeme bulunamadı.
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    <!-- SADE ALT BİLGİ -->
    <div style="margin-top: 14px; display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #64748b; padding: 0 4px;">
        <div>Toplam <strong id="footer-visible-count"><?= $totalMaterials ?></strong> malzeme listeleniyor</div>
        <div id="last-sync-time">Son Senkron: Şimdi</div>
    </div>

</main>

<style>
@keyframes pulseDot {
    0% { transform: scale(0.95); opacity: 0.8; }
    50% { transform: scale(1.25); opacity: 1; }
    100% { transform: scale(0.95); opacity: 0.8; }
}

.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.04) !important;
}

.filter-tab {
    background: transparent;
    border: none;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 12.5px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s ease;
}

.filter-tab:hover {
    color: #0f172a;
    background: rgba(255, 255, 255, 0.6);
}

.filter-tab.active {
    background: #ffffff;
    color: #0f172a;
    font-weight: 700;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

.material-row:hover {
    background-color: #f8fafc !important;
}

.material-row:hover .action-arrow {
    transform: translateX(4px);
    color: #2563eb;
}

@keyframes stockDecreaseRow {
    0% {
        background-color: #fee2e2;
    }
    25% {
        background-color: #fef2f2;
    }
    100% {
        background-color: transparent;
    }
}

@keyframes stockDecreaseNumber {
    0% {
        color: #dc2626;
        transform: scale(1.04);
    }
    30% {
        color: #dc2626;
        transform: scale(1.04);
    }
    100% {
        color: inherit;
        transform: scale(1);
    }
}

.material-row.stock-decreased {
    animation: stockDecreaseRow 1.3s cubic-bezier(0.25, 1, 0.5, 1) forwards;
}

.material-row.stock-decreased .stock-number {
    display: inline-block;
    animation: stockDecreaseNumber 1.3s cubic-bezier(0.25, 1, 0.5, 1) forwards;
}
</style>

<script>
let currentFilter = 'all';
let previousStockMap = {};
let isInitialPollDone = false;
let isFetchingLive = false;

// Initialize previous stock map from DOM
document.querySelectorAll('.material-row').forEach(row => {
    const id = parseInt(row.getAttribute('data-id'));
    const stock = parseFloat(row.getAttribute('data-stock'));
    if (!isNaN(id) && !isNaN(stock)) {
        previousStockMap[id] = stock;
    }
});

// Arama & Filtreleme Mantığı
function setFilter(filterKey) {
    currentFilter = filterKey;
    
    // Tab pill styling
    document.querySelectorAll('.filter-tab').forEach(tab => {
        if (tab.getAttribute('data-filter') === filterKey) {
            tab.classList.add('active');
        } else {
            tab.classList.remove('active');
        }
    });

    applyFilterAndSearch();
}

function clearSearch() {
    const input = document.getElementById('search-input');
    if (input) {
        input.value = '';
        document.getElementById('search-clear-btn').style.display = 'none';
        applyFilterAndSearch();
    }
}

function applyFilterAndSearch() {
    const searchInput = document.getElementById('search-input');
    const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
    const clearBtn = document.getElementById('search-clear-btn');
    if (clearBtn) {
        clearBtn.style.display = query.length > 0 ? 'block' : 'none';
    }

    const rows = document.querySelectorAll('.material-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const rowStatus = row.getAttribute('data-status');
        const rowName = row.getAttribute('data-name') || '';
        const rowCode = row.getAttribute('data-code') || '';
        const rowCat = row.getAttribute('data-category') || '';

        const matchesFilter = (currentFilter === 'all' || rowStatus === currentFilter);
        const matchesSearch = (query === '' || rowName.includes(query) || rowCode.includes(query) || rowCat.includes(query));

        if (matchesFilter && matchesSearch) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    const noRow = document.getElementById('no-materials-row');
    if (noRow) {
        noRow.style.display = visibleCount === 0 ? '' : 'none';
    }

    const footerCount = document.getElementById('footer-visible-count');
    if (footerCount) {
        footerCount.innerText = visibleCount;
    }
}

// Canlı MES Üretim ve Stok AJAX Poller
function pollLiveStock() {
    if (isFetchingLive) return;
    isFetchingLive = true;

    fetch('/stok-takip/public/api/materials/live-stock')
        .then(response => {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(data => {
            isFetchingLive = false;
            if (!data.success) return;

            // 1. Update 4 KPI Summary Cards & Filter Badges
            if (data.summary) {
                const suffEl = document.getElementById('kpi-sufficient');
                const lowEl = document.getElementById('kpi-low');
                const critEl = document.getElementById('kpi-critical');
                const oosEl = document.getElementById('kpi-out-of-stock');

                if (suffEl) suffEl.innerText = data.summary.sufficient_count;
                if (lowEl) lowEl.innerText = data.summary.low_count;
                if (critEl) critEl.innerText = data.summary.critical_count;
                if (oosEl) oosEl.innerText = data.summary.out_of_stock_count;

                const pillAll = document.getElementById('pill-count-all');
                const pillSuff = document.getElementById('pill-count-sufficient');
                const pillLow = document.getElementById('pill-count-low');
                const pillCrit = document.getElementById('pill-count-critical');
                const pillOos = document.getElementById('pill-count-out_of_stock');

                if (pillAll) pillAll.innerText = data.summary.total_materials;
                if (pillSuff) pillSuff.innerText = data.summary.sufficient_count;
                if (pillLow) pillLow.innerText = data.summary.low_count;
                if (pillCrit) pillCrit.innerText = data.summary.critical_count;
                if (pillOos) pillOos.innerText = data.summary.out_of_stock_count;
            }

            // 2. Update Compact Live MES Banner
            updateMesBanner(data.active_production);

            // 3. Update Sync Timestamp
            const syncEl = document.getElementById('last-sync-time');
            if (syncEl) {
                const now = new Date();
                const timeStr = now.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                syncEl.innerText = 'Son Senkron: ' + timeStr;
            }

            // 4. Update Each Material Row
            if (Array.isArray(data.materials)) {
                data.materials.forEach(mat => {
                    const row = document.getElementById('mat-row-' + mat.id);
                    if (!row) return;

                    const prevStock = previousStockMap[mat.id];
                    const currentStock = parseFloat(mat.total_stock);

                    // Update data attributes
                    row.setAttribute('data-status', mat.status_key);
                    row.setAttribute('data-stock', currentStock);

                    // Update stock value container
                    const stockValContainer = document.getElementById('stock-val-' + mat.id);
                    if (stockValContainer) {
                        const numEl = stockValContainer.querySelector('.stock-number');
                        if (numEl) numEl.innerText = mat.formatted_stock;
                        stockValContainer.style.color = currentStock > 0 ? '#0f172a' : '#94a3b8';
                    }

                    // Update status badge
                    const badge = document.getElementById('badge-' + mat.id);
                    if (badge) {
                        badge.style.background = mat.status_bg;
                        badge.style.color = mat.status_color;
                        badge.style.border = '1px solid ' + mat.status_border;

                        const dot = badge.querySelector('.status-dot');
                        if (dot) dot.style.background = mat.status_dot;

                        const text = badge.querySelector('.status-text');
                        if (text) text.innerText = mat.status_label;
                    }

                    // Subtle, professional decrease animation (Strictly: new_stock < old_stock)
                    // Does NOT trigger on initial page load / first poll, and does NOT trigger on stock increase or equality
                    if (isInitialPollDone && prevStock !== undefined && currentStock < prevStock) {
                        row.classList.remove('stock-decreased');
                        void row.offsetWidth; // force DOM reflow to restart animation smoothly
                        row.classList.add('stock-decreased');

                        setTimeout(() => {
                            row.classList.remove('stock-decreased');
                        }, 1300);
                    }

                    // Update memory cache with latest backend stock
                    previousStockMap[mat.id] = currentStock;
                });

                isInitialPollDone = true;
                applyFilterAndSearch();
            }
        })
        .catch(err => {
            isFetchingLive = false;
        });
}

function updateMesBanner(prod) {
    const banner = document.getElementById('live-mes-banner');
    if (!banner) return;

    if (!prod || !prod.is_active) {
        banner.style.display = 'none';
        return;
    }

    const planned = prod.planned_quantity || 0;
    const produced = prod.produced_quantity || 0;
    const pct = prod.progress_pct || 0;
    const speed = prod.interval_seconds || 5;

    banner.style.display = 'flex';
    banner.style.alignItems = 'center';
    banner.style.justifyContent = 'space-between';
    banner.style.flexWrap = 'wrap';
    banner.style.gap = '12px';

    banner.innerHTML = `
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 16px;">🏭</span>
            <div>
                <strong style="color: #ffffff;">MES ÜRETİM AKTİF:</strong>
                <span style="color: #cbd5e1; margin-left: 4px;">${escapeHtml(prod.work_order_no)} &bull; ${escapeHtml(prod.product_name)}</span>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-weight: 800; color: #38bdf8; font-size: 14px;">${produced} / ${planned} Panel</span>
                <span style="font-size: 12px; color: #94a3b8;">(%${pct})</span>
                <div style="width: 80px; height: 6px; background: rgba(255,255,255,0.2); border-radius: 3px; overflow: hidden;">
                    <div style="width: ${pct}%; height: 100%; background: #38bdf8; border-radius: 3px; transition: width 0.4s ease;"></div>
                </div>
            </div>
            <span style="font-size: 11.5px; color: #94a3b8; background: rgba(255,255,255,0.1); padding: 2px 8px; border-radius: 4px;">${speed} sn/panel</span>
            <a href="/stok-takip/public/mes/work-orders/show?id=${prod.id}" style="color: #38bdf8; text-decoration: none; font-weight: 700; font-size: 12.5px;">
                İş Emrine Git &rarr;
            </a>
        </div>
    `;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.innerText = text;
    return div.innerHTML;
}

// Start live polling every 2.5s
document.addEventListener('DOMContentLoaded', function() {
    pollLiveStock();
    setInterval(pollLiveStock, 2500);
});
</script>