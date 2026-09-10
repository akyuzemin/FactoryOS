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
            <p class="page-description">Raf ve lokasyon bilgilerini eksiksiz doldurun.</p>
        </div>
        <a class="button" href="/stok-takip/public/locations">Listeye Dön</a>
    </header>

    <div class="form-panel warehouse-form-panel">
        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($formAction) ?>
            <?= CsrfService::tokenField() ?>" class="material-form">
            <div class="form-grid">
                <div class="form-field">
                    <label for="warehouse_id">Bağlı Olduğu Depo *</label>
                    <select id="warehouse_id" name="warehouse_id" required>
                        <option value="">Depo Seçin</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <option value="<?= (int) $warehouse['id'] ?>" <?= ((int) ($formData['warehouse_id'] ?? 0) === (int) $warehouse['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($warehouse['code']) ?> - <?= htmlspecialchars($warehouse['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label for="code">Raf / Lokasyon Kodu *</label>
                    <input id="code" name="code" type="text" maxlength="30" placeholder="Örn: A-01, RAF-101" required value="<?= htmlspecialchars($formData['code'] ?? '') ?>">
                </div>

                <div class="form-field form-field-wide">
                    <label for="name">Raf / Lokasyon Adı *</label>
                    <input id="name" name="name" type="text" maxlength="100" placeholder="Örn: A Rafı - 01, Hızlı Toplama Alanı" required value="<?= htmlspecialchars($formData['name'] ?? '') ?>">
                </div>

                <div class="form-field form-field-wide">
                    <label for="description">Açıklama</label>
                    <textarea id="description" name="description" rows="4" maxlength="65535" placeholder="Raf veya saklama alanına dair detaylı açıklama..."><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <a class="button" href="/stok-takip/public/locations">İptal</a>
                <button class="button button-primary" type="submit">Kaydet</button>
            </div>
        </form>
    </div>

</main>

