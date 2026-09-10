<?php

$pageTitle = 'Stok Düzeltme';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="page-kicker">Envanter İşlemleri</p>
            <h1>Stok Düzeltme</h1>
            <p class="page-description">Sayım sonucundaki gerçek toplam stok miktarını kaydedin.</p>
        </div>
        <a class="button" href="/stok-takip/public/stock-movements">Hareketlere Dön</a>
    </header>

    <div class="form-panel stock-adjustment-panel">
        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/stok-takip/public/stock/adjustment" class="material-form">
            <?= CsrfService::tokenField() ?>
            <div class="form-grid">
                <div class="form-field form-field-wide">
                    <label for="material_id">Malzeme *</label>
                    <select id="material_id" name="material_id" required>
                        <option value="">Malzeme seçin</option>
                        <?php foreach ($materials as $material): ?>
                            <option value="<?= (int) $material['id'] ?>" <?= (string) ($formData['material_id'] ?? '') === (string) $material['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($material['name']) ?> (<?= htmlspecialchars($material['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label for="location_id">Depo / Raf *</label>
                    <select id="location_id" name="location_id" required>
                        <option value="">Depo ve raf seçin</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?= (int) $location['id'] ?>" <?= (string) ($formData['location_id'] ?? '') === (string) $location['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($location['warehouse_name']) ?> / <?= htmlspecialchars($location['name']) ?> (<?= htmlspecialchars($location['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label for="new_quantity">Yeni Gerçek Stok Miktarı *</label>
                    <input id="new_quantity" name="new_quantity" type="number" min="0" step="0.001" required value="<?= htmlspecialchars($formData['new_quantity'] ?? '') ?>">
                </div>

                <div class="form-field form-field-wide">
                    <label for="description">Açıklama</label>
                    <textarea id="description" name="description" rows="4" maxlength="65535"><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <a class="button" href="/stok-takip/public/stock-movements">İptal</a>
                <button class="button button-primary button-adjustment" type="submit">Düzeltmeyi Kaydet</button>
            </div>
        </form>
    </div>

</main>
