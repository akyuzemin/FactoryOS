<?php

$pageTitle = 'Stok Transferi';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="page-kicker">Envanter İşlemleri</p>
            <h1>Stok Transferi</h1>
            <p class="page-description">Malzemeyi bir depo veya raftan diğerine aktarın.</p>
        </div>
        <a class="button" href="/stok-takip/public/stock-movements">Hareketlere Dön</a>
    </header>

    <div class="form-panel stock-transfer-panel">
        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/stok-takip/public/stock/transfer" class="material-form">
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
                    <label for="source_location_id">Kaynak Depo / Raf *</label>
                    <select id="source_location_id" name="source_location_id" required>
                        <option value="">Kaynak depo ve raf seçin</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?= (int) $location['id'] ?>" <?= (string) ($formData['source_location_id'] ?? '') === (string) $location['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($location['warehouse_name']) ?> / <?= htmlspecialchars($location['name']) ?> (<?= htmlspecialchars($location['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label for="target_location_id">Hedef Depo / Raf *</label>
                    <select id="target_location_id" name="target_location_id" required>
                        <option value="">Hedef depo ve raf seçin</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?= (int) $location['id'] ?>" <?= (string) ($formData['target_location_id'] ?? '') === (string) $location['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($location['warehouse_name']) ?> / <?= htmlspecialchars($location['name']) ?> (<?= htmlspecialchars($location['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label for="quantity">Miktar *</label>
                    <input id="quantity" name="quantity" type="number" min="0.001" step="0.001" required value="<?= htmlspecialchars($formData['quantity'] ?? '') ?>">
                </div>

                <div class="form-field form-field-wide">
                    <label for="description">Açıklama</label>
                    <textarea id="description" name="description" rows="4" maxlength="65535"><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <a class="button" href="/stok-takip/public/stock-movements">İptal</a>
                <button class="button button-primary button-transfer" type="submit">Transferi Kaydet</button>
            </div>
        </form>
    </div>

</main>
