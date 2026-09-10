<?php
$pageTitle = 'Yeni Üretim & Stok Tüketimi';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="page-kicker">Üretim &amp; Stok Entegrasyonu</p>
            <h1>⚡ Yeni Üretim Girişi &amp; Otomatik Stok Entegrasyonu</h1>
            <p class="page-description">Üretilen panel miktarına göre BOM hammaddeleri stoktan düşülür (OUT) ve üretilen nihai mamul stoğa otomatik giriş yapar (IN).</p>
        </div>
        <div class="header-badges">
            <a href="/stok-takip/public/production" class="button button-secondary">
                &larr; Üretim Günlükleri
            </a>
        </div>
    </header>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <span>⚠️ <?= htmlspecialchars($error) ?></span>
            <button type="button" onclick="this.parentElement.remove();" style="background:none; border:none; color:#b91c1c; cursor:pointer; font-weight:bold; font-size:16px;">&times;</button>
        </div>
    <?php endif; ?>

    <form method="POST" action="/stok-takip/public/production/store" id="production-form">
            <?= CsrfService::tokenField() ?>

        <!-- 1. ÜRETİM PARAMETRELERİ KARTI -->
        <div class="card" style="padding: 24px; margin-bottom: 24px;">
            <h3 style="font-size: 15px; font-weight: 700; color: var(--ink); margin: 0 0 18px 0; border-bottom: 1px solid var(--line-subtle); padding-bottom: 10px;">
                🏭 1. Üretim &amp; Tesis Parametreleri
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px;">
                
                <!-- Üretim Hattı -->
                <div class="form-group">
                    <label for="line_id">Üretim Hattı <span style="color:#ef4444;">*</span></label>
                    <select id="line_id" name="line_id" class="form-select" required>
                        <?php foreach ($lines as $l): ?>
                            <option value="<?= $l['id'] ?>" <?= ($formData['line_id'] == $l['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($l['name']) ?> (<?= htmlspecialchars($l['code']) ?> - <?= (float)$l['nominal_power_kw'] ?> kW)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Vardiya -->
                <div class="form-group">
                    <label for="shift_id">Vardiya <span style="color:#ef4444;">*</span></label>
                    <select id="shift_id" name="shift_id" class="form-select" required>
                        <?php foreach ($shifts as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($formData['shift_id'] == $s['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars(substr($s['start_time'],0,5)) ?> - <?= htmlspecialchars(substr($s['end_time'],0,5)) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Üretim Tarihi -->
                <div class="form-group">
                    <label for="log_date">Üretim Tarihi <span style="color:#ef4444;">*</span></label>
                    <input type="date" id="log_date" name="log_date" class="form-input" value="<?= htmlspecialchars($formData['log_date']) ?>" required>
                </div>

                <!-- BOM Reçetesi -->
                <div class="form-group">
                    <label for="recipe_id">BOM Üretim Reçetesi <span style="color:#ef4444;">*</span></label>
                    <select id="recipe_id" name="recipe_id" class="form-select" required onchange="calculateNeeds();">
                        <?php foreach ($recipes as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= ($formData['recipe_id'] == $r['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['name']) ?> (<?= htmlspecialchars($r['code']) ?> - <?= (int)$r['item_count'] ?> Kalem)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Üretim Miktarı (Panel Adedi) -->
                <div class="form-group">
                    <label for="panels_produced_qty">Sağlam Üretilen Panel Adedi <span style="color:#ef4444;">*</span></label>
                    <input type="number" min="1" step="1" id="panels_produced_qty" name="panels_produced_qty" class="form-input" value="<?= htmlspecialchars($formData['panels_produced_qty']) ?>" required oninput="calculateNeeds();">
                    <small style="color: var(--text-secondary); font-size: 11px;">Stok tüketim ve mamul giriş hesabı bu miktara göre yapılacaktır</small>
                </div>

                <!-- Fire Panel Adedi -->
                <div class="form-group">
                    <label for="scrap_panels_qty">Fire / Kusurlu Panel Adedi</label>
                    <input type="number" min="0" step="1" id="scrap_panels_qty" name="scrap_panels_qty" class="form-input" value="<?= htmlspecialchars($formData['scrap_panels_qty'] ?? '0') ?>">
                </div>

                <!-- Kaynak Depo (Hammadde Çıkışı) -->
                <div class="form-group">
                    <label for="source_location_id">Kaynak Depo (Hammadde Çıkışı) <span style="color:#ef4444;">*</span></label>
                    <select id="source_location_id" name="source_location_id" class="form-select" required onchange="calculateNeeds();">
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= $loc['id'] ?>" <?= ($formData['source_location_id'] == $loc['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($loc['warehouse_name']) ?> &rarr; <?= htmlspecialchars($loc['loc_name']) ?> (<?= htmlspecialchars($loc['loc_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: var(--text-secondary); font-size: 11px;">Hammaddeler bu lokasyondan kontrol edilip düşülecektir</small>
                </div>

                <!-- Hedef Depo (Mamul Girişi) -->
                <div class="form-group">
                    <label for="target_location_id">Hedef Depo (Mamul Girişi) <span style="color:#ef4444;">*</span></label>
                    <select id="target_location_id" name="target_location_id" class="form-select" required onchange="calculateNeeds();">
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= $loc['id'] ?>" <?= ($formData['target_location_id'] == $loc['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($loc['warehouse_name']) ?> &rarr; <?= htmlspecialchars($loc['loc_name']) ?> (<?= htmlspecialchars($loc['loc_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: var(--text-secondary); font-size: 11px;">Üretilen paneller bu lokasyona stok girişi yapacaktır</small>
                </div>

                <!-- Notlar -->
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="notes">Operasyonel Notlar</label>
                    <input type="text" id="notes" name="notes" class="form-input" placeholder="Örn: Hat 1 sabah vardiyası seri üretimi" value="<?= htmlspecialchars($formData['notes']) ?>">
                </div>

            </div>
        </div>

        <!-- 2. MAMUL GİRİŞİ BİLGİ KARTI -->
        <div class="card" style="padding: 20px 24px; margin-bottom: 24px; background: #f0fdf4; border: 1px solid #bbf7d0;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <div style="width: 42px; height: 42px; border-radius: 10px; background: #16a34a; color: #fff; display: grid; place-items: center; font-size: 20px;">
                        📦
                    </div>
                    <div>
                        <span style="font-size: 11.5px; font-weight: 700; color: #166534; text-transform: uppercase;">Stoğa Giriş Yapacak Nihai Mamul</span>
                        <div style="font-size: 15px; font-weight: 800; color: #0f172a;" id="output-material-name">
                            550Wp Schmid Pekintaş Monokristal Solar Modül
                        </div>
                        <div style="font-size: 12px; color: #15803d;">
                            Kod: <strong id="output-material-code">PNL-550W-MONO</strong> &bull; Giriş Miktarı: <strong id="output-material-qty">100 AD</strong>
                        </div>
                    </div>
                </div>
                <div>
                    <span class="status-badge status-badge-success" style="font-size: 12px; padding: 6px 14px;">
                        📥 Hareket: IN (Giriş)
                    </span>
                </div>
            </div>
        </div>

        <!-- 3. CANLI STOK KONTROLÜ VE TÜKETİM ANALİZİ KARTI -->
        <div class="card" style="padding: 24px; margin-bottom: 24px;" id="stock-analysis-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; border-bottom: 1px solid var(--line-subtle); padding-bottom: 10px;">
                <div>
                    <h3 style="font-size: 15px; font-weight: 700; color: var(--ink); margin: 0;">
                        🔍 2. Hammadde Tüketim Hesabı &amp; Stok Uygunluk Durumu
                    </h3>
                    <p style="font-size: 12px; color: var(--text-secondary); margin: 2px 0 0 0;">
                        Formül: <code>Tüketim = Üretim Miktarı × Reçete Miktarı × (1 + Fire% / 100)</code>
                    </p>
                </div>
                <div id="availability-summary-badge">
                    <span class="status-badge status-badge-info">Hesaplanıyor...</span>
                </div>
            </div>

            <!-- Tablo -->
            <div style="overflow-x: auto;">
                <table class="saas-table" id="stock-needs-table" style="font-size: 13px;">
                    <thead>
                        <tr>
                            <th>Hammadde / Malzeme</th>
                            <th style="text-align: right; width: 120px;">Reçete Oranı</th>
                            <th style="text-align: right; width: 110px;">Fire Oranı</th>
                            <th style="text-align: right; width: 140px;">Gerekli Tüketim</th>
                            <th style="text-align: right; width: 140px;">Depodaki Stok</th>
                            <th style="text-align: right; width: 120px;">Eksik Miktar</th>
                            <th style="text-align: center; width: 130px;">Stok Durumu</th>
                        </tr>
                    </thead>
                    <tbody id="stock-needs-tbody">
                        <tr>
                            <td colspan="7" class="empty-state">Stok verileri hesaplanıyor...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Uyarı Paneli (Yetersiz Stok Varsa Görünür) -->
            <div id="stock-warning-box" style="display: none; margin-top: 16px; padding: 14px 18px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 8px; color: #991b1b; font-size: 13px;">
                <strong>⚠️ Dikkat:</strong> Seçilen hammadde deposunda bazı kritik malzemeler yetersizdir. Yetersiz hammadde varken üretim onaylanamaz. Lütfen üretim miktarını azaltın veya depoya hammadde girişi/transferi yapın.
            </div>
        </div>

        <!-- 4. ÖZET & ONAY ÇUBUĞU -->
        <div class="card" style="padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div>
                <div style="font-size: 14px; font-weight: 700; color: var(--ink);" id="confirm-summary-title">
                    Üretim Özeti Hazırlanıyor...
                </div>
                <div style="font-size: 12px; color: var(--text-secondary);" id="confirm-summary-desc">
                    Onaylandığında hammadde çıkışları (OUT) ve mamul girişi (IN) tek transaction içinde atomik olarak gerçekleştirilecektir.
                </div>
            </div>

            <div style="display: flex; gap: 12px; align-items: center;">
                <a href="/stok-takip/public/production" class="button button-secondary">
                    İptal
                </a>
                <button type="submit" id="submit-btn" class="button button-primary" style="padding: 11px 26px; font-size: 14px; font-weight: 700;">
                    <span>🚀</span> Üretimi Onayla, Hammaddeleri Düş ve Mamulü Stoğa Ekle
                </button>
            </div>
        </div>

    </form>

</main>

<script>
async function calculateNeeds() {
    const recipeId = document.getElementById('recipe_id').value;
    const sourceLocationId = document.getElementById('source_location_id').value;
    const targetLocationId = document.getElementById('target_location_id').value;
    const qty = document.getElementById('panels_produced_qty').value;

    if (!recipeId || !sourceLocationId || qty <= 0) {
        return;
    }

    const tbody = document.getElementById('stock-needs-tbody');
    const badgeContainer = document.getElementById('availability-summary-badge');
    const warningBox = document.getElementById('stock-warning-box');
    const submitBtn = document.getElementById('submit-btn');
    const summaryTitle = document.getElementById('confirm-summary-title');
    const summaryDesc = document.getElementById('confirm-summary-desc');
    const outMatName = document.getElementById('output-material-name');
    const outMatCode = document.getElementById('output-material-code');
    const outMatQty = document.getElementById('output-material-qty');

    badgeContainer.innerHTML = '<span class="status-badge status-badge-info">Hesaplanıyor...</span>';

    try {
        const formData = new FormData();
        formData.append('recipe_id', recipeId);
        formData.append('source_location_id', sourceLocationId);
        formData.append('target_location_id', targetLocationId);
        formData.append('panels_produced_qty', qty);

        const response = await fetch('/stok-takip/public/production/preview-stock', {
            method: 'POST',
            body: formData
        });

        const res = await response.json();

        if (!res.success) {
            tbody.innerHTML = `<tr><td colspan="7" class="empty-state" style="color:#ef4444;">${res.message}</td></tr>`;
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.5';
            return;
        }

        const data = res.data;

        // Mamul Kartı Bilgilerini Güncelle
        if (data.output_material && data.output_material.name) {
            outMatName.innerText = data.output_material.name;
            outMatCode.innerText = data.output_material.code || '-';
            outMatQty.innerText = `${parseInt(qty).toLocaleString('tr-TR')} ${data.output_material.unit_symbol || 'AD'}`;
        }

        let html = '';

        data.items.forEach(it => {
            const statusClass = it.is_sufficient ? 'status-badge-success' : 'status-badge-danger';
            const statusText = it.is_sufficient ? '✓ Yeterli' : '✗ Yetersiz';
            const missingText = it.missing_qty > 0 ? `<span style="color:#ef4444; font-weight:700;">-${it.missing_qty.toLocaleString('tr-TR', {minimumFractionDigits: 2})} ${it.unit_symbol}</span>` : '<span style="color:#10b981;">0</span>';

            html += `
                <tr style="${!it.is_sufficient ? 'background: rgba(239,68,68,0.04);' : ''}">
                    <td>
                        <strong style="color: var(--ink);">${it.material_name}</strong>
                        <span class="code-badge" style="font-size: 10px; margin-left: 4px;">${it.material_code}</span>
                        ${it.is_critical ? '<span style="font-size: 10px; color:#64748b; margin-left: 4px;">(Zorunlu)</span>' : '<span style="font-size: 10px; color:#94a3b8; margin-left: 4px;">(Opsiyonel)</span>'}
                    </td>
                    <td style="text-align: right;">${parseFloat(it.unit_quantity).toLocaleString('tr-TR')} ${it.unit_symbol}</td>
                    <td style="text-align: right; color:#d97706;">%${parseFloat(it.scrap_rate_pct).toFixed(2)}</td>
                    <td style="text-align: right; font-weight: 700; color: var(--ink);">
                        ${parseFloat(it.required_qty).toLocaleString('tr-TR', {minimumFractionDigits: 2})} ${it.unit_symbol}
                    </td>
                    <td style="text-align: right; font-weight: 600; color: ${it.is_sufficient ? '#10b981' : '#ef4444'};">
                        ${parseFloat(it.available_qty).toLocaleString('tr-TR', {minimumFractionDigits: 2})} ${it.unit_symbol}
                    </td>
                    <td style="text-align: right;">${missingText}</td>
                    <td style="text-align: center;">
                        <span class="status-badge ${statusClass}">${statusText}</span>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;

        if (data.can_produce) {
            badgeContainer.innerHTML = `<span class="status-badge status-badge-success">✓ Tüm Stoklar Yeterli (${data.sufficient_items_count}/${data.total_items_count})</span>`;
            warningBox.style.display = 'none';
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
            submitBtn.style.cursor = 'pointer';

            summaryTitle.innerText = `Özet: +${parseInt(qty).toLocaleString('tr-TR')} ${data.output_material.unit_symbol || 'Panel'} Girişi & ${data.total_items_count} Hammadde Kalemi Tüketimi`;
            summaryDesc.innerText = `Kaynak: ${data.source_location.wh_name} | Hedef: ${data.target_location ? data.target_location.wh_name : 'Sevkiyat Deposu'}`;
        } else {
            badgeContainer.innerHTML = `<span class="status-badge status-badge-danger">✗ Yetersiz Stok (${data.sufficient_items_count}/${data.total_items_count})</span>`;
            warningBox.style.display = 'block';
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.5';
            submitBtn.style.cursor = 'not-allowed';

            summaryTitle.innerText = `⚠️ Yetersiz Stok Sebebiyle Onaylanamaz (${parseInt(qty).toLocaleString('tr-TR')} Panel)`;
            summaryDesc.innerText = `Lütfen eksik hammaddeleri temin edin veya üretim adedini düşürün.`;
        }

    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="7" class="empty-state" style="color:#ef4444;">Hesaplama sunucu hatası: ${err.message}</td></tr>`;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    calculateNeeds();
});
</script>
