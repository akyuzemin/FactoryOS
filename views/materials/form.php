<?php

$pageTitle = $formTitle;

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="page-kicker">Envanter Yönetimi</p>
            <h1><?= htmlspecialchars($formTitle) ?></h1>
            <p class="page-description">Malzeme bilgilerini eksiksiz doldurun.</p>
        </div>
        <a class="button" href="/stok-takip/public/materials">Listeye Dön</a>
    </header>

    <div class="form-panel">
        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($formAction) ?>
            <?= CsrfService::tokenField() ?>" class="material-form">
            <div class="form-grid">
                <div class="form-field">
                    <label for="code">Malzeme Kodu *</label>
                    <input id="code" name="code" type="text" maxlength="50" required value="<?= htmlspecialchars($formData['code'] ?? '') ?>">
                </div>

                <div class="form-field">
                    <label for="name">Malzeme Adı *</label>
                    <input id="name" name="name" type="text" maxlength="150" required value="<?= htmlspecialchars($formData['name'] ?? '') ?>">
                </div>

                <div class="form-field">
                    <label for="category_id">Kategori *</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Kategori seçin</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>" <?= (string) ($formData['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label for="unit_id">Birim *</label>
                    <select id="unit_id" name="unit_id" required>
                        <option value="">Birim seçin</option>
                        <?php foreach ($units as $unit): ?>
                            <option value="<?= (int) $unit['id'] ?>" <?= (string) ($formData['unit_id'] ?? '') === (string) $unit['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($unit['name']) ?> (<?= htmlspecialchars($unit['symbol']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label for="min_stock">Minimum Stok *</label>
                    <input id="min_stock" name="min_stock" type="number" min="0" step="0.001" required value="<?= htmlspecialchars((string) ($formData['min_stock'] ?? '0')) ?>">
                </div>

                <div class="form-field">
                    <label for="max_stock">Maksimum Stok</label>
                    <input id="max_stock" name="max_stock" type="number" min="0" step="0.001" value="<?= htmlspecialchars((string) ($formData['max_stock'] ?? '')) ?>">
                </div>

                <div class="form-field form-field-wide">
                    <label for="description">Açıklama</label>
                    <textarea id="description" name="description" rows="4"><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <a class="button" href="/stok-takip/public/materials">İptal</a>
                <button class="button button-primary" type="submit">Kaydet</button>
            </div>
        </form>
    </div>

</main>
