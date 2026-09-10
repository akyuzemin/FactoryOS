<?php

$pageTitle = $formTitle;

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="page-kicker">Operasyon Yönetimi</p>
            <h1><?= htmlspecialchars($formTitle) ?></h1>
            <p class="page-description">Depo bilgilerini eksiksiz doldurun.</p>
        </div>
        <a class="button" href="/stok-takip/public/warehouses">Listeye Dön</a>
    </header>

    <div class="form-panel warehouse-form-panel">
        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($formAction) ?>
            <?= CsrfService::tokenField() ?>" class="material-form">
            <div class="form-grid">
                <div class="form-field">
                    <label for="code">Depo Kodu *</label>
                    <input id="code" name="code" type="text" maxlength="30" required value="<?= htmlspecialchars($formData['code'] ?? '') ?>">
                </div>

                <div class="form-field">
                    <label for="name">Depo Adı *</label>
                    <input id="name" name="name" type="text" maxlength="100" required value="<?= htmlspecialchars($formData['name'] ?? '') ?>">
                </div>

                <div class="form-field form-field-wide">
                    <label for="description">Açıklama</label>
                    <textarea id="description" name="description" rows="5" maxlength="65535"><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <a class="button" href="/stok-takip/public/warehouses">İptal</a>
                <button class="button button-primary" type="submit">Kaydet</button>
            </div>
        </form>
    </div>

</main>
