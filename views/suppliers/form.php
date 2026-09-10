<?php

$pageTitle = $formTitle;

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="page-kicker">Satın Alma Yönetimi</p>
            <h1><?= htmlspecialchars($formTitle) ?></h1>
            <p class="page-description">Tedarikçi iletişim ve firma bilgilerini eksiksiz doldurun.</p>
        </div>
        <a class="button" href="/stok-takip/public/suppliers">Listeye Dön</a>
    </header>

    <div class="form-panel supplier-form-panel">
        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($formAction) ?>
            <?= CsrfService::tokenField() ?>" class="material-form">
            <div class="form-grid">
                <div class="form-field">
                    <label for="code">Tedarikçi Kodu *</label>
                    <input id="code" name="code" type="text" maxlength="30" required value="<?= htmlspecialchars($formData['code'] ?? '') ?>">
                </div>

                <div class="form-field">
                    <label for="name">Firma Adı *</label>
                    <input id="name" name="name" type="text" maxlength="150" required value="<?= htmlspecialchars($formData['name'] ?? '') ?>">
                </div>

                <div class="form-field">
                    <label for="contact_name">Yetkili Kişi</label>
                    <input id="contact_name" name="contact_name" type="text" maxlength="100" value="<?= htmlspecialchars($formData['contact_name'] ?? '') ?>">
                </div>

                <div class="form-field">
                    <label for="phone">Telefon</label>
                    <input id="phone" name="phone" type="tel" maxlength="30" value="<?= htmlspecialchars($formData['phone'] ?? '') ?>">
                </div>

                <div class="form-field">
                    <label for="email">E-posta</label>
                    <input id="email" name="email" type="email" maxlength="150" value="<?= htmlspecialchars($formData['email'] ?? '') ?>">
                </div>

                <div class="form-field">
                    <label for="address">Adres</label>
                    <input id="address" name="address" type="text" value="<?= htmlspecialchars($formData['address'] ?? '') ?>">
                </div>

                <div class="form-field form-field-wide">
                    <label for="description">Not / Açıklama</label>
                    <textarea id="description" name="description" rows="4"><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <a class="button" href="/stok-takip/public/suppliers">İptal</a>
                <button class="button button-primary" type="submit">Kaydet</button>
            </div>
        </form>
    </div>

</main>
