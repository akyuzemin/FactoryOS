<?php
declare(strict_types=1);

$pageTitle = 'Talebi Düzenle: ' . ($request['request_no'] ?? '');
$activePage = 'purchase_requests';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';

$priorityLabels = [
    'LOW'    => 'Düşük',
    'MEDIUM' => 'Normal',
    'HIGH'   => 'Yüksek',
    'URGENT' => 'Acil',
];
?>

<main class="main-content">

    <!-- FLASH MESAJLARI -->
    <?php if (!empty($_SESSION['error'])): ?>
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 13.5px; font-weight: 600; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span>⚠️</span> <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <button type="button" onclick="this.parentElement.remove();" style="background: none; border: none; font-size: 16px; cursor: pointer; color: #991b1b;">&times;</button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- 1. ÜST BAŞLIK -->
    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="margin-bottom: 6px;">
                <a href="/stok-takip/public/purchase-requests/show?id=<?= (int)$request['id'] ?>" style="font-size: 12.5px; font-weight: 700; color: #3b82f6; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                    &larr; Talep Detayına Dön
                </a>
            </div>
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
                    Satın Alma Talebini Düzenle
                </h1>
                <span style="font-family: monospace; font-size: 16px; font-weight: 800; color: #1e40af; background: #eff6ff; border: 1px solid #bfdbfe; padding: 3px 10px; border-radius: 8px;">
                    <?= htmlspecialchars($request['request_no']) ?>
                </span>
                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;">
                    <span>📝</span> Taslak
                </span>
            </div>
            <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">
                Taslak durumundaki talebin başlık bilgilerini ve malzeme kalemlerini güncelleyin.
            </p>
        </div>
    </div>

    <!-- 2. FORM KARTI -->
    <form id="editPurchaseRequestForm" method="POST" action="/stok-takip/public/purchase-requests/edit" style="display: flex; flex-direction: column; gap: 20px;">
        <?= CsrfService::tokenField() ?>
        <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">

        <!-- BAŞLIK BİLGİLERİ KARTI -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <h2 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                <span>📋</span> Genel Talep Bilgileri
            </h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 16px;">
                
                <!-- Departman -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Talep Eden Departman <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="department_id" id="department_id" required style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; background: #fff;">
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= (int)$dept['id'] ?>" <?= ((int)$request['department_id'] === (int)$dept['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['name']) ?> (<?= htmlspecialchars($dept['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Talep Eden (Salt Okunur) -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #64748b; margin-bottom: 6px;">
                        Talep Eden Personel
                    </label>
                    <input type="text" readonly disabled value="<?= htmlspecialchars($request['requester_name'] ?: ($request['requester_username'] ?? '')) ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13.5px; background: #f8fafc; color: #475569; box-sizing: border-box;">
                </div>

                <!-- Talep Tarihi -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Talep Tarihi <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="date" name="request_date" id="request_date" required value="<?= htmlspecialchars($request['request_date']) ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; box-sizing: border-box;">
                </div>

                <!-- İhtiyaç Tarihi -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        İhtiyaç Duyulan Tarih <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="date" name="required_date" id="required_date" required min="<?= htmlspecialchars($request['request_date']) ?>" value="<?= htmlspecialchars($request['required_date']) ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; box-sizing: border-box;">
                </div>

                <!-- Öncelik -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Öncelik Derecesi <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="priority" id="priority" required style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; background: #fff;">
                        <?php foreach ($priorities as $p): ?>
                            <option value="<?= htmlspecialchars($p) ?>" <?= ($request['priority'] === $p) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($priorityLabels[$p] ?? $p) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <!-- Genel Açıklama -->
            <div>
                <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    Talep Gerekçesi / Genel Açıklama <small style="color: #64748b; font-weight: 400;">(Opsiyonel)</small>
                </label>
                <textarea name="description" id="description" rows="2" placeholder="Talebin genel amacını veya kullanım yerini belirtebilirsiniz..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-family: inherit; resize: vertical; box-sizing: border-box;"><?= htmlspecialchars($request['description'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- MALZEME KALEMLERİ KARTI -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                        <span>📦</span> Malzeme Kalemleri <span style="color: #ef4444;">*</span>
                    </h2>
                    <p style="font-size: 12px; color: #64748b; margin: 2px 0 0 0;">
                        Talebe ait malzeme kalemlerini düzenleyin, ekleyin veya çıkarın.
                    </p>
                </div>
                <button type="button" id="btnAddItem" class="button button-secondary" style="padding: 7px 14px; font-size: 12.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; background: #f8fafc; border: 1px solid #cbd5e1;">
                    <span>➕</span> Kalem Ekle
                </button>
            </div>

            <!-- TABLO -->
            <div style="overflow-x: auto;">
                <table id="itemsTable" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; text-align: left;">
                            <th style="padding: 10px 12px; font-weight: 700; color: #475569; width: 30%; min-width: 220px;">Malzeme <span style="color: #ef4444;">*</span></th>
                            <th style="padding: 10px 12px; font-weight: 700; color: #475569; width: 12%; min-width: 100px;">Miktar <span style="color: #ef4444;">*</span></th>
                            <th style="padding: 10px 12px; font-weight: 700; color: #475569; width: 9%; min-width: 70px;">Birim</th>
                            <th style="padding: 10px 12px; font-weight: 700; color: #475569; width: 14%; min-width: 110px;">Tahmini Fiyat</th>
                            <th style="padding: 10px 12px; font-weight: 700; color: #475569; width: 9%; min-width: 75px;">Kur</th>
                            <th style="padding: 10px 12px; font-weight: 700; color: #475569; width: 18%; min-width: 150px;">Önerilen Tedarikçi</th>
                            <th style="padding: 10px 12px; font-weight: 700; color: #475569; width: 15%; min-width: 130px;">Not</th>
                            <th style="padding: 10px 12px; font-weight: 700; color: #475569; width: 5%; text-align: center;">Sil</th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <!-- Mevcut kayıtlar JS tarafından render edilir -->
                    </tbody>
                </table>
            </div>

            <!-- ALT ÖZET ÇUBUĞU -->
            <div style="margin-top: 14px; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; font-size: 13px;">
                <div style="color: #64748b;">
                    Toplam Kalem: <strong id="itemCountLabel" style="color: #0f172a;">0</strong>
                </div>
                <div style="color: #334155; font-weight: 700;">
                    Tahmini Toplam: <span id="totalAmountLabel" style="color: #2563eb; font-size: 15px; font-weight: 800;">0.00 TL</span>
                </div>
            </div>
        </div>

        <!-- 3. AKSİYON BUTONLARI -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 10px 0;">
            <a href="/stok-takip/public/purchase-requests/show?id=<?= (int)$request['id'] ?>" class="button button-secondary" style="padding: 10px 18px; font-size: 13.5px; font-weight: 600; text-decoration: none; background: #fff; border: 1px solid #cbd5e1; color: #475569;">
                &larr; Talep Detayına Dön
            </a>

            <div style="display: flex; gap: 10px; align-items: center;">
                <button type="submit" name="action" value="draft" class="button button-secondary" style="padding: 10px 20px; font-size: 13.5px; font-weight: 700; cursor: pointer; background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; display: inline-flex; align-items: center; gap: 6px;">
                    <span>💾</span> Değişiklikleri Kaydet
                </button>
                <button type="submit" name="action" value="submit" class="button button-primary" style="padding: 10px 22px; font-size: 13.5px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                    <span>🚀</span> Değişiklikleri Kaydet ve Onaya Gönder
                </button>
            </div>
        </div>

    </form>

</main>

<!-- MALZEME, TEDARİKÇİ VE MEVCUT KALEM VERİLERİNİN JS İLE GÜVENLİ PAYLAŞIMI -->
<script>
window.PR_MATERIALS = <?= json_encode($materials, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
window.PR_SUPPLIERS = <?= json_encode($suppliers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
window.PR_CURRENCIES = <?= json_encode($currencies, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
window.PR_EXISTING_ITEMS = <?= json_encode($items, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;

document.addEventListener('DOMContentLoaded', function() {
    var tbody = document.getElementById('itemsBody');
    var btnAdd = document.getElementById('btnAddItem');
    var form = document.getElementById('editPurchaseRequestForm');
    var reqDate = document.getElementById('request_date');
    var reqiredDate = document.getElementById('required_date');

    var rowIndex = 0;

    // Tarih bağlayıcı
    if (reqDate && reqiredDate) {
        reqDate.addEventListener('change', function() {
            reqiredDate.min = this.value;
            if (reqiredDate.value && reqiredDate.value < this.value) {
                reqiredDate.value = this.value;
            }
        });
    }

    // Malzeme Haritası
    var materialMap = {};
    window.PR_MATERIALS.forEach(function(m) {
        materialMap[m.id] = m;
    });

    // Satır Ekleme Fonksiyonu
    function addRow(data) {
        data = data || {};
        var idx = rowIndex++;
        var tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid #f1f5f9';
        tr.className = 'pr-item-row';
        tr.id = 'row_' + idx;

        // 1. Malzeme Select
        var matOptions = '<option value="">-- Malzeme Seçin --</option>';
        window.PR_MATERIALS.forEach(function(m) {
            var selected = (data.material_id && parseInt(data.material_id, 10) === parseInt(m.id, 10)) ? 'selected' : '';
            var label = (m.code ? m.code + ' - ' : '') + m.name;
            matOptions += '<option value="' + m.id + '" ' + selected + '>' + escapeHtml(label) + '</option>';
        });

        // 2. Tedarikçi Select
        var suppOptions = '<option value="">(Belirtilmedi)</option>';
        window.PR_SUPPLIERS.forEach(function(s) {
            var selected = (data.suggested_supplier_id && parseInt(data.suggested_supplier_id, 10) === parseInt(s.id, 10)) ? 'selected' : '';
            suppOptions += '<option value="' + s.id + '" ' + selected + '>' + escapeHtml(s.name) + '</option>';
        });

        // 3. Kur Select
        var currOptions = '';
        window.PR_CURRENCIES.forEach(function(c) {
            var selected = (data.currency === c || (!data.currency && c === 'TL')) ? 'selected' : '';
            currOptions += '<option value="' + c + '" ' + selected + '>' + c + '</option>';
        });

        var selectedMat = data.material_id ? materialMap[data.material_id] : null;
        var unitLabel = selectedMat ? (selectedMat.unit_symbol || selectedMat.unit_name || '-') : (data.unit_symbol || data.unit_name || '-');

        tr.innerHTML = 
            '<td style="padding: 8px 10px;">' +
                '<select name="items[' + idx + '][material_id]" class="mat-select" required style="width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; background: #fff;">' +
                    matOptions +
                '</select>' +
            '</td>' +
            '<td style="padding: 8px 10px;">' +
                '<input type="number" name="items[' + idx + '][requested_quantity]" class="qty-input" required min="0.001" step="0.001" value="' + (data.requested_quantity !== undefined ? data.requested_quantity : '') + '" placeholder="0.00" style="width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; box-sizing: border-box;">' +
            '</td>' +
            '<td style="padding: 8px 10px; text-align: center;">' +
                '<span class="unit-badge" style="display: inline-block; padding: 4px 8px; background: #f1f5f9; color: #475569; border-radius: 6px; font-weight: 700; font-size: 11.5px;">' +
                    escapeHtml(unitLabel) +
                '</span>' +
            '</td>' +
            '<td style="padding: 8px 10px;">' +
                '<input type="number" name="items[' + idx + '][estimated_unit_price]" class="price-input" min="0" step="0.0001" value="' + (data.estimated_unit_price !== null && data.estimated_unit_price !== undefined ? data.estimated_unit_price : '') + '" placeholder="0.00" style="width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; box-sizing: border-box;">' +
            '</td>' +
            '<td style="padding: 8px 10px;">' +
                '<select name="items[' + idx + '][currency]" class="curr-select" style="width: 100%; padding: 7px 6px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12.5px; background: #fff;">' +
                    currOptions +
                '</select>' +
            '</td>' +
            '<td style="padding: 8px 10px;">' +
                '<select name="items[' + idx + '][suggested_supplier_id]" style="width: 100%; padding: 7px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12.5px; background: #fff;">' +
                    suppOptions +
                '</select>' +
            '</td>' +
            '<td style="padding: 8px 10px;">' +
                '<input type="text" name="items[' + idx + '][notes]" maxlength="255" value="' + escapeHtml(data.notes || '') + '" placeholder="Not..." style="width: 100%; padding: 7px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12.5px; box-sizing: border-box;">' +
            '</td>' +
            '<td style="padding: 8px 10px; text-align: center;">' +
                '<button type="button" class="btn-remove-row" style="background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; border-radius: 6px; width: 28px; height: 28px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;" title="Satırı Sil">&times;</button>' +
            '</td>';

        tbody.appendChild(tr);

        // Olay dinleyicileri
        var selectMatElem = tr.querySelector('.mat-select');
        var unitBadgeElem = tr.querySelector('.unit-badge');
        var qtyInputElem = tr.querySelector('.qty-input');
        var priceInputElem = tr.querySelector('.price-input');
        var btnRemoveElem = tr.querySelector('.btn-remove-row');

        selectMatElem.addEventListener('change', function() {
            var mId = this.value;
            if (mId && materialMap[mId]) {
                var mat = materialMap[mId];
                unitBadgeElem.textContent = mat.unit_symbol || mat.unit_name || '-';
                if (!priceInputElem.value && mat.unit_price) {
                    priceInputElem.value = parseFloat(mat.unit_price).toFixed(2);
                }
            } else {
                unitBadgeElem.textContent = '-';
            }
            updateSummary();
        });

        qtyInputElem.addEventListener('input', updateSummary);
        priceInputElem.addEventListener('input', updateSummary);

        btnRemoveElem.addEventListener('click', function() {
            var allRows = tbody.querySelectorAll('tr.pr-item-row');
            if (allRows.length <= 1) {
                // Son satır silinemez, temizlenir
                selectMatElem.value = '';
                unitBadgeElem.textContent = '-';
                qtyInputElem.value = '';
                priceInputElem.value = '';
                tr.querySelector('input[name*="[notes]"]').value = '';
                tr.querySelector('select[name*="[suggested_supplier_id]"]').value = '';
            } else {
                tr.remove();
            }
            updateSummary();
        });

        updateSummary();
    }

    // Özet Güncelleme
    function updateSummary() {
        var rows = tbody.querySelectorAll('tr.pr-item-row');
        var count = rows.length;
        var totalAmount = 0;

        rows.forEach(function(r) {
            var q = parseFloat(r.querySelector('.qty-input').value) || 0;
            var p = parseFloat(r.querySelector('.price-input').value) || 0;
            totalAmount += (q * p);
        });

        document.getElementById('itemCountLabel').textContent = count + ' kalem';
        document.getElementById('totalAmountLabel').textContent = totalAmount.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' TL (Tahmini)';
    }

    // Buton Dinleyicisi
    btnAdd.addEventListener('click', function() {
        addRow();
    });

    // Form Validasyonu
    form.addEventListener('submit', function(e) {
        var rows = tbody.querySelectorAll('tr.pr-item-row');
        if (rows.length === 0) {
            alert('Lütfen en az bir malzeme kalemi ekleyin.');
            e.preventDefault();
            return false;
        }

        var hasValidItem = false;
        var hasError = false;

        rows.forEach(function(r, i) {
            var matVal = r.querySelector('.mat-select').value;
            var qtyVal = parseFloat(r.querySelector('.qty-input').value) || 0;

            if (matVal) {
                if (qtyVal <= 0) {
                    alert('Kalem #' + (i + 1) + ' için miktar 0\'dan büyük olmalıdır.');
                    hasError = true;
                } else {
                    hasValidItem = true;
                }
            }
        });

        if (hasError) {
            e.preventDefault();
            return false;
        }

        if (!hasValidItem) {
            alert('Lütfen en az bir geçerli malzeme ve miktar seçin.');
            e.preventDefault();
            return false;
        }
    });

    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Mevcut kalemleri doldur (Varsa PR_EXISTING_ITEMS, yoksa 1 boş satır)
    if (window.PR_EXISTING_ITEMS && window.PR_EXISTING_ITEMS.length > 0) {
        window.PR_EXISTING_ITEMS.forEach(function(item) {
            addRow(item);
        });
    } else {
        addRow();
    }
});
</script>
</body>
</html>

