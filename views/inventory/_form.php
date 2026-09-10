<?php

$statusLabels = [
    'IN_STOCK' => 'Stokta',
    'ASSIGNED' => 'Tahsisli',
    'IN_USE' => 'Kullanımda',
    'IN_REPAIR' => 'Bakımda',
    'LOST' => 'Kayıp',
    'RETIRED' => 'Emekli',
    'DISPOSED' => 'Zayi',
];

$selected = static function (mixed $current, mixed $expected): string {
    return (string) $current === (string) $expected ? 'selected' : '';
};

$value = static function (string $key) use ($formData): string {
    return htmlspecialchars((string) ($formData[$key] ?? ''), ENT_QUOTES, 'UTF-8');
};
?>

<div class="form-panel inventory-form-panel">
    <?php if (!empty($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" action="<?= htmlspecialchars($formAction) ?>" class="material-form" id="inventory-form">
        <?= CsrfService::tokenField() ?>
        <section class="inventory-form-section">
            <div class="inventory-form-section-header">
                <span class="inventory-form-section-number">01</span>
                <div><h2>Temel Bilgiler</h2><p>Varlığın kimliğini ve tanımını girin.</p></div>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label for="asset_code">Varlık Kodu</label>
                    <input id="asset_code" type="text" readonly value="<?= $value('asset_code') ?>" aria-describedby="asset-code-help">
                    <small id="asset-code-help" class="form-help">Sistem tarafından otomatik oluşturulur.</small>
                </div>
                <div class="form-field">
                    <label for="asset_name">Varlık Adı *</label>
                    <div class="inventory-autocomplete">
                        <input id="asset_name" name="asset_name" type="text" maxlength="150" required autocomplete="off" value="<?= $value('asset_name') ?>">
                        <ul class="inventory-suggestion-list" data-suggestion-list="asset_name" role="listbox" hidden></ul>
                    </div>
                </div>
                <div class="form-field">
                    <label for="inventory_category_id">Kategori *</label>
                    <select id="inventory_category_id" name="inventory_category_id" required>
                        <option value="">Kategori seçin</option>
                        <?php foreach ($filterOptions['categories'] as $category): ?>
                            <option value="<?= (int) $category['id'] ?>" <?= $selected($formData['inventory_category_id'] ?? '', $category['id']) ?>><?= htmlspecialchars($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label for="serial_no">Seri No</label>
                    <input id="serial_no" name="serial_no" type="text" maxlength="100" value="<?= $value('serial_no') ?>">
                </div>
                <div class="form-field">
                    <label for="manufacturer">Üretici / Marka</label>
                    <div class="inventory-autocomplete">
                        <input id="manufacturer" name="manufacturer" type="text" maxlength="100" autocomplete="off" value="<?= $value('manufacturer') ?>">
                        <ul class="inventory-suggestion-list" data-suggestion-list="manufacturer" role="listbox" hidden></ul>
                    </div>
                </div>
                <div class="form-field">
                    <label for="model_no">Model No</label>
                    <div class="inventory-autocomplete">
                        <input id="model_no" name="model_no" type="text" maxlength="100" autocomplete="off" value="<?= $value('model_no') ?>">
                        <ul class="inventory-suggestion-list" data-suggestion-list="model_no" role="listbox" hidden></ul>
                    </div>
                </div>
                <div class="form-field form-field-wide">
                    <label for="description">Açıklama</label>
                    <textarea id="description" name="description" rows="4" maxlength="65535"><?= $value('description') ?></textarea>
                </div>
            </div>
        </section>

        <section class="inventory-form-section">
            <div class="inventory-form-section-header">
                <span class="inventory-form-section-number">02</span>
                <div><h2>Konum &amp; Sorumluluk</h2><p>Varlığın mevcut durumunu ve bağlı olduğu alanı belirleyin.</p></div>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label for="status">Durum *</label>
                    <select id="status" name="status" required>
                        <?php foreach ($filterOptions['statuses'] as $status): ?>
                            <option value="<?= htmlspecialchars($status) ?>" <?= $selected($formData['status'] ?? '', $status) ?>><?= htmlspecialchars($statusLabels[$status] ?? $status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label for="responsible_user_id">Sorumlu Kullanıcı</label>
                    <select id="responsible_user_id" name="responsible_user_id">
                        <option value="">Sorumlu seçin</option>
                        <?php foreach ($filterOptions['users'] as $user): ?>
                            <option value="<?= (int) $user['id'] ?>" <?= $selected($formData['responsible_user_id'] ?? '', $user['id']) ?>><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label for="warehouse_id">Depo</label>
                    <select id="warehouse_id" name="warehouse_id">
                        <option value="">Depo seçin</option>
                        <?php foreach ($filterOptions['warehouses'] as $warehouse): ?>
                            <option value="<?= (int) $warehouse['id'] ?>" <?= $selected($formData['warehouse_id'] ?? '', $warehouse['id']) ?>><?= htmlspecialchars($warehouse['name']) ?> (<?= htmlspecialchars($warehouse['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label for="location_id">Lokasyon</label>
                    <select id="location_id" name="location_id" <?= empty($formData['warehouse_id']) ? 'disabled' : '' ?> data-selected-location="<?= (int) ($formData['location_id'] ?? 0) ?>">
                        <option value=""><?= empty($formData['warehouse_id']) ? 'Önce depo seçin' : 'Lokasyon seçin' ?></option>
                        <?php foreach ($filterOptions['locations'] as $location): ?>
                            <option value="<?= (int) $location['id'] ?>" data-warehouse-id="<?= (int) $location['warehouse_id'] ?>" <?= $selected($formData['location_id'] ?? '', $location['id']) ?>><?= htmlspecialchars($location['warehouse_name'] . ' / ' . $location['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field form-field-wide">
                    <label for="production_line_id">Üretim Hattı</label>
                    <select id="production_line_id" name="production_line_id">
                        <option value="">Üretim hattı seçin</option>
                        <?php foreach ($filterOptions['production_lines'] as $line): ?>
                            <option value="<?= (int) $line['id'] ?>" <?= $selected($formData['production_line_id'] ?? '', $line['id']) ?>><?= htmlspecialchars($line['name']) ?> (<?= htmlspecialchars($line['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </section>

        <section class="inventory-form-section">
            <div class="inventory-form-section-header">
                <span class="inventory-form-section-number">03</span>
                <div><h2>Satın Alma Bilgileri</h2><p>Satın alma ve mali kayıt bilgilerini tamamlayın.</p></div>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label for="supplier_id">Tedarikçi</label>
                    <select id="supplier_id" name="supplier_id">
                        <option value="">Tedarikçi seçin</option>
                        <?php foreach ($filterOptions['suppliers'] as $supplier): ?>
                            <option value="<?= (int) $supplier['id'] ?>" <?= $selected($formData['supplier_id'] ?? '', $supplier['id']) ?>><?= htmlspecialchars($supplier['name']) ?> (<?= htmlspecialchars($supplier['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label for="purchase_date">Satın Alma Tarihi</label>
                    <input id="purchase_date" name="purchase_date" type="date" value="<?= $value('purchase_date') ?>">
                </div>
                <div class="form-field">
                    <label for="purchase_cost">Satın Alma Bedeli</label>
                    <input id="purchase_cost" name="purchase_cost" type="number" min="0" step="0.0001" value="<?= $value('purchase_cost') ?>">
                </div>
                <div class="form-field">
                    <label for="currency">Para Birimi</label>
                    <input id="currency" name="currency" type="text" maxlength="10" value="<?= $value('currency') ?>">
                </div>
                <div class="form-field form-field-wide">
                    <label for="invoice_no">Fatura No</label>
                    <input id="invoice_no" name="invoice_no" type="text" maxlength="100" value="<?= $value('invoice_no') ?>">
                </div>
            </div>
        </section>

        <section class="inventory-form-section">
            <div class="inventory-form-section-header">
                <span class="inventory-form-section-number">04</span>
                <div><h2>Garanti</h2><p>Garanti kapsamındaki tarih aralığını belirtin.</p></div>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label for="warranty_start_date">Garanti Başlangıç</label>
                    <input id="warranty_start_date" name="warranty_start_date" type="date" value="<?= $value('warranty_start_date') ?>">
                </div>
                <div class="form-field">
                    <label for="warranty_end_date">Garanti Bitiş</label>
                    <input id="warranty_end_date" name="warranty_end_date" type="date" value="<?= $value('warranty_end_date') ?>">
                </div>
            </div>
        </section>

        <section class="inventory-form-section">
            <div class="inventory-form-section-header">
                <span class="inventory-form-section-number">05</span>
                <div><h2>Bakım</h2><p>Varlığın bakım kaydıyla bağlantısını yönetin.</p></div>
            </div>
            <div class="form-grid">
                <div class="form-field form-field-wide">
                    <label for="maintenance_asset_id">Bakım Varlığı</label>
                    <select id="maintenance_asset_id" name="maintenance_asset_id">
                        <option value="">Bakım varlığı bağlama</option>
                        <?php foreach ($filterOptions['maintenance_assets'] as $maintenanceAsset): ?>
                            <option value="<?= (int) $maintenanceAsset['id'] ?>" <?= $selected($formData['maintenance_asset_id'] ?? '', $maintenanceAsset['id']) ?>><?= htmlspecialchars($maintenanceAsset['asset_code'] . ' - ' . $maintenanceAsset['asset_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-help">Bu envanter bir bakım varlığı ile ilişkiliyse seçiniz.</small>
                </div>
            </div>
        </section>

        <div class="form-actions">
            <a class="button" href="/stok-takip/public/inventory">Vazgeç</a>
            <button class="button button-primary" type="submit"><?= str_contains($formTitle, 'Düzenle') ? 'Değişiklikleri Kaydet' : 'Envanteri Kaydet' ?></button>
        </div>
    </form>
</div>

<script>
(() => {
    const form = document.getElementById('inventory-form');
    const status = document.getElementById('status');
    const responsibleUser = document.getElementById('responsible_user_id');
    const warehouse = document.getElementById('warehouse_id');
    const location = document.getElementById('location_id');
    const suggestionFields = ['asset_name', 'manufacturer', 'model_no'];

    if (!form || !status || !responsibleUser || !warehouse || !location) {
        return;
    }

    const syncConditionalRequirements = () => {
        const requiresResponsibleUser = ['ASSIGNED', 'IN_USE'].includes(status.value);
        const requiresLocationPair = status.value === 'IN_STOCK';

        responsibleUser.required = requiresResponsibleUser;
        warehouse.required = requiresLocationPair;
        location.required = requiresLocationPair;
        responsibleUser.setAttribute('aria-required', String(requiresResponsibleUser));
        warehouse.setAttribute('aria-required', String(requiresLocationPair));
        location.setAttribute('aria-required', String(requiresLocationPair));
    };

    const validateLocationPair = () => {
        warehouse.setCustomValidity('');
        location.setCustomValidity('');

        if (Boolean(warehouse.value) !== Boolean(location.value)) {
            const message = 'Depo ve lokasyon birlikte seçilmelidir.';
            (warehouse.value ? location : warehouse).setCustomValidity(message);
        }
    };

    status.addEventListener('change', syncConditionalRequirements);
    warehouse.addEventListener('change', validateLocationPair);
    location.addEventListener('change', validateLocationPair);
    form.addEventListener('submit', validateLocationPair);

    const closeSuggestionLists = () => {
        document.querySelectorAll('[data-suggestion-list]').forEach((list) => {
            list.hidden = true;
            list.replaceChildren();
        });
    };

    const locationOptions = Array.from(location.options).slice(1).map((option) => ({
        value: option.value,
        warehouseId: option.dataset.warehouseId,
        text: option.textContent,
    }));
    const initialLocation = location.dataset.selectedLocation || location.value;

    const updateLocations = (clearSelection = false) => {
        const warehouseId = warehouse.value;
        location.replaceChildren();
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = warehouseId ? 'Lokasyon seçin' : 'Önce depo seçin';
        location.appendChild(placeholder);
        location.disabled = !warehouseId;

        if (!warehouseId) {
            location.value = '';
            return;
        }

        locationOptions
            .filter((option) => option.warehouseId === warehouseId)
            .forEach((optionData) => {
                const option = document.createElement('option');
                option.value = optionData.value;
                option.textContent = optionData.text;
                location.appendChild(option);
            });

        if (!clearSelection && initialLocation && location.querySelector(`option[value="${initialLocation}"]`)) {
            location.value = initialLocation;
        } else {
            location.value = '';
        }
    };

    warehouse.addEventListener('change', () => {
        updateLocations(true);
        validateLocationPair();
    });
    updateLocations(false);

    suggestionFields.forEach((field) => {
        const input = document.getElementById(field);
        const list = document.querySelector(`[data-suggestion-list="${field}"]`);
        let request = null;
        let debounceTimer = null;

        if (!input || !list) {
            return;
        }

        input.addEventListener('input', () => {
            window.clearTimeout(debounceTimer);
            list.hidden = true;
            list.replaceChildren();
            const query = input.value.trim();
            if (!query) {
                return;
            }

            debounceTimer = window.setTimeout(async () => {
                if (request) {
                    request.abort();
                }
                request = new AbortController();

                try {
                    const params = new URLSearchParams({field, q: query});
                    const response = await fetch(`/stok-takip/public/inventory/suggestions?${params.toString()}`, {
                        headers: {Accept: 'application/json'},
                        signal: request.signal,
                    });
                    if (!response.ok) {
                        return;
                    }
                    const payload = await response.json();
                    if (!Array.isArray(payload.suggestions) || document.activeElement !== input) {
                        return;
                    }

                    payload.suggestions.forEach((suggestion) => {
                        const item = document.createElement('li');
                        const option = document.createElement('button');
                        option.type = 'button';
                        option.textContent = suggestion;
                        option.setAttribute('role', 'option');
                        option.addEventListener('click', () => {
                            input.value = suggestion;
                            list.hidden = true;
                            list.replaceChildren();
                        });
                        item.appendChild(option);
                        list.appendChild(item);
                    });
                    list.hidden = list.childElementCount === 0;
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        list.hidden = true;
                    }
                }
            }, 180);
        });

        input.addEventListener('focus', () => {
            if (list.childElementCount > 0) {
                list.hidden = false;
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.inventory-autocomplete')) {
            closeSuggestionLists();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSuggestionLists();
        }
    });

    syncConditionalRequirements();
    validateLocationPair();
})();
</script>
