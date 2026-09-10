<?php
$pageTitle = 'Yeni Reçete Oluştur';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="page-kicker">Üretim Reçeteleri (BOM)</p>
            <h1>➕ Yeni Reçete Tanımla</h1>
            <p class="page-description">Üretilecek nihai mamul için gereken hammadde, bileşen ve fire oranlarını tanımlayın.</p>
        </div>
        <div class="header-badges">
            <a href="/stok-takip/public/recipes" class="button button-secondary">
                &larr; Reçete Listesi
            </a>
        </div>
    </header>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <span>⚠️ <?= htmlspecialchars($error) ?></span>
            <button type="button" onclick="this.parentElement.remove();" style="background:none; border:none; color:#b91c1c; cursor:pointer; font-weight:bold; font-size:16px;">&times;</button>
        </div>
    <?php endif; ?>

    <form method="POST" action="/stok-takip/public/recipes/create" id="recipe-form">
            <?= CsrfService::tokenField() ?>
        
        <!-- 1. GENEL REÇETE BİLGİLERİ KARTI -->
        <div class="card" style="padding: 24px; margin-bottom: 24px;">
            <h3 style="font-size: 15px; font-weight: 700; color: var(--ink); margin: 0 0 18px 0; border-bottom: 1px solid var(--line-subtle); padding-bottom: 10px;">
                📋 Genel Reçete Bilgileri
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px;">
                
                <!-- Reçete Kodu -->
                <div class="form-group">
                    <label for="code">Reçete Kodu <span style="color:#ef4444;">*</span></label>
                    <input type="text" id="code" name="code" class="form-input" placeholder="Örn: BOM-SP-550W-MONO" value="<?= htmlspecialchars($formData['code'] ?? '') ?>" required>
                    <small style="color: var(--text-secondary); font-size: 11px;">Benzersiz ürün reçete kodu</small>
                </div>

                <!-- Reçete Adı -->
                <div class="form-group">
                    <label for="name">Reçete Adı <span style="color:#ef4444;">*</span></label>
                    <input type="text" id="name" name="name" class="form-input" placeholder="Örn: 550Wp Schmid Pekintaş Monokristal Modül" value="<?= htmlspecialchars($formData['name'] ?? '') ?>" required>
                </div>

                <!-- Baz Üretim Miktarı -->
                <div class="form-group">
                    <label for="base_quantity">Baz Üretim Miktarı <span style="color:#ef4444;">*</span></label>
                    <input type="number" step="0.001" min="0.001" id="base_quantity" name="base_quantity" class="form-input" value="<?= htmlspecialchars($formData['base_quantity'] ?? '1.000') ?>" required>
                    <small style="color: var(--text-secondary); font-size: 11px;">Aşağıdaki malzeme oranlarının baz alındığı bitmiş ürün adedi (Varsayılan: 1 adet)</small>
                </div>

                <!-- Nihai Ürün (Opsiyonel) -->
                <div class="form-group">
                    <label for="output_material_id">Nihai Ürün (Malzeme Kartı)</label>
                    <select id="output_material_id" name="output_material_id" class="form-select">
                        <option value="">-- Malzeme Kartı Bağlama (Opsiyonel) --</option>
                        <?php foreach ($availableMaterials as $mat): ?>
                            <option value="<?= $mat['id'] ?>" <?= ($formData['output_material_id'] ?? '') == $mat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($mat['code']) ?> - <?= htmlspecialchars($mat['name']) ?> (<?= htmlspecialchars($mat['category_name'] ?? '') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: var(--text-secondary); font-size: 11px;">Üretildiğinde stoku artacak nihai mamul kartı</small>
                </div>

                <!-- Açıklama -->
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="description">Reçete Açıklaması &amp; Teknik Notlar</label>
                    <textarea id="description" name="description" class="form-textarea" rows="2" placeholder="Reçeteye ait teknik standartlar, hücre dizgi ve tolerans detayları..."><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
                </div>

            </div>
        </div>

        <!-- 2. REÇETE HAMMADDE & BİLEŞEN LİSTESİ KARTI -->
        <div class="card" style="padding: 24px; margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; border-bottom: 1px solid var(--line-subtle); padding-bottom: 10px;">
                <div>
                    <h3 style="font-size: 15px; font-weight: 700; color: var(--ink); margin: 0;">
                        🧱 Reçete Bileşenleri &amp; Hammaddeler (BOM Kalemleri)
                    </h3>
                    <p style="font-size: 12px; color: var(--text-secondary); margin: 2px 0 0 0;">
                        1 birim nihai ürünün üretimi için gereken hammadde ve sarf miktarlarını belirleyin.
                    </p>
                </div>
                <button type="button" class="button button-sm button-secondary" onclick="addRow();">
                    <span>➕</span> Satır Ekle
                </button>
            </div>

            <div style="overflow-x: auto;">
                <table class="saas-table" id="items-table" style="font-size: 13px;">
                    <thead>
                        <tr>
                            <th style="min-width: 280px;">Malzeme / Bileşen <span style="color:#ef4444;">*</span></th>
                            <th style="width: 140px;">Birim Miktar <span style="color:#ef4444;">*</span></th>
                            <th style="width: 130px;">Fire Oranı (%)</th>
                            <th style="width: 120px; text-align: center;">Zorunlu mu?</th>
                            <th style="width: 60px; text-align: center;">Sil</th>
                        </tr>
                    </thead>
                    <tbody id="items-tbody">
                        <!-- Dynamic JS Rows will be appended here -->
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 14px;">
                <button type="button" class="button button-sm button-secondary" onclick="addRow();">
                    <span>➕</span> Yeni Malzeme Satırı Ekle
                </button>
            </div>
        </div>

        <!-- 3. KAYDET ÇUBUĞU -->
        <div class="card" style="padding: 16px 24px; display: flex; justify-content: space-between; align-items: center;">
            <a href="/stok-takip/public/recipes" class="button button-secondary">
                İptal
            </a>
            <button type="submit" class="button button-primary" style="padding: 10px 24px; font-size: 14px;">
                <span>💾</span> Reçeteyi Kaydet
            </button>
        </div>

    </form>

</main>

<script>
const materialsList = <?= json_encode($availableMaterials, JSON_UNESCAPED_UNICODE) ?>;
let rowCount = 0;

function addRow(matId = '', qty = '', scrap = '0.00', isCritical = 1) {
    const tbody = document.getElementById('items-tbody');
    const rowId = rowCount++;

    let matOptions = '<option value="">-- Malzeme Seçin --</option>';
    materialsList.forEach(m => {
        const selected = (m.id == matId) ? 'selected' : '';
        matOptions += `<option value="${m.id}" ${selected}>${m.code} - ${m.name} (${m.unit_symbol || ''}) [Mevcut: ${m.current_stock}]</option>`;
    });

    const tr = document.createElement('tr');
    tr.id = `row-${rowId}`;
    tr.innerHTML = `
        <td>
            <select name="items[${rowId}][material_id]" class="form-select" required>
                ${matOptions}
            </select>
        </td>
        <td>
            <input type="number" step="0.0001" min="0.0001" name="items[${rowId}][quantity]" class="form-input" placeholder="0.0000" value="${qty}" required>
        </td>
        <td>
            <input type="number" step="0.01" min="0" max="100" name="items[${rowId}][scrap_rate_pct]" class="form-input" placeholder="0.00" value="${scrap}">
        </td>
        <td style="text-align: center;">
            <input type="checkbox" name="items[${rowId}][is_critical]" value="1" ${isCritical ? 'checked' : ''} style="width: 18px; height: 18px; accent-color: var(--purple); cursor: pointer;">
        </td>
        <td style="text-align: center;">
            <button type="button" onclick="removeRow(${rowId});" class="button button-sm button-danger" style="padding: 4px 8px;" title="Satırı Sil">
                🗑️
            </button>
        </td>
    `;
    tbody.appendChild(tr);
}

function removeRow(rowId) {
    const row = document.getElementById(`row-${rowId}`);
    if (row) {
        row.remove();
    }
}

// Default initial rows if empty
<?php if (!empty($formItems)): ?>
    <?php foreach ($formItems as $it): ?>
        addRow('<?= $it['material_id'] ?>', '<?= $it['quantity'] ?>', '<?= $it['scrap_rate_pct'] ?>', <?= $it['is_critical'] ?>);
    <?php endforeach; ?>
<?php else: ?>
    // Populate with common solar components as clean starting suggestions
    addRow(1, '144', '0.50', 1); // Solar Hücre
    addRow(2, '1', '0.00', 1);   // Solar Cam
    addRow(3, '1.2', '1.00', 1); // EVA Film
    addRow(4, '2.1', '0.50', 1); // Backsheet
    addRow(5, '1', '0.00', 1);   // Alüminyum Çerçeve
    addRow(6, '1', '0.00', 1);   // Junction Box
<?php endif; ?>
</script>
