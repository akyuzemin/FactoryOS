<?php

$pageTitle = $formTitle;

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="page-kicker">Ekip Yönetimi</p>
            <h1><?= htmlspecialchars($formTitle) ?></h1>
            <p class="page-description">Kullanıcı bilgilerini ve erişim rolünü yönetin.</p>
        </div>
        <a class="button" href="/stok-takip/public/users">Listeye Dön</a>
    </header>

    <div class="form-panel user-form-panel">
        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($formAction) ?>
            <?= CsrfService::tokenField() ?>" class="material-form">
            <div class="form-grid">
                <div class="form-field">
                    <label for="username">Kullanıcı Adı *</label>
                    <input id="username" name="username" type="text" maxlength="50" required value="<?= htmlspecialchars($formData['username'] ?? '') ?>">
                </div>

                <div class="form-field">
                    <label for="email">E-posta *</label>
                    <input id="email" name="email" type="email" maxlength="150" required value="<?= htmlspecialchars($formData['email'] ?? '') ?>">
                </div>

                <div class="form-field">
                    <label for="first_name">Ad *</label>
                    <input id="first_name" name="first_name" type="text" maxlength="100" required value="<?= htmlspecialchars($formData['first_name'] ?? '') ?>">
                </div>

                <div class="form-field">
                    <label for="last_name">Soyad *</label>
                    <input id="last_name" name="last_name" type="text" maxlength="100" required value="<?= htmlspecialchars($formData['last_name'] ?? '') ?>">
                </div>

                <div class="form-field">
                    <label for="role_id">Rol *</label>
                    <select id="role_id" name="role_id" required>
                        <option value="">Rol seçin</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= (int) $role['id'] ?>" <?= (string) ($formData['role_id'] ?? '') === (string) $role['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label for="is_active">Durum *</label>
                    <select id="is_active" name="is_active" required>
                        <option value="1" <?= (string) ($formData['is_active'] ?? '1') === '1' ? 'selected' : '' ?>>Aktif</option>
                        <option value="0" <?= (string) ($formData['is_active'] ?? '1') === '0' ? 'selected' : '' ?>>Pasif</option>
                    </select>
                </div>

                <div class="form-field form-field-wide">
                    <label for="password">Şifre <?= $formTitle === 'Yeni Kullanıcı' ? '*' : '(değiştirmek istemiyorsanız boş bırakın)' ?></label>
                    <input id="password" name="password" type="password" minlength="8" <?= $formTitle === 'Yeni Kullanıcı' ? 'required' : '' ?> autocomplete="new-password">
                </div>
            </div>

            <div class="form-actions">
                <a class="button" href="/stok-takip/public/users">İptal</a>
                <button class="button button-primary" type="submit">Kaydet</button>
            </div>
        </form>
    </div>

</main>
