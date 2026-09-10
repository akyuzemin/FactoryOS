<?php

$pageTitle = 'Kullanıcılar';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="page-kicker">Ekip Yönetimi</p>
            <h1>Kullanıcılar</h1>
            <p class="page-description">Sistemde tanımlı kullanıcıları ve rollerini görüntüleyin.</p>
        </div>
        <?php if ($can('user.manage')): ?>
            <a class="button button-primary" href="/stok-takip/public/users/create">+ Yeni Kullanıcı</a>
        <?php endif; ?>
        <div class="page-count">
            <?= htmlspecialchars((string) count($users)) ?> kullanıcı
        </div>
    </header>

    <div class="table-shell">
        <table class="materials-table users-table">
            <thead>
                <tr>
                    <th>Kullanıcı Adı</th>
                    <th>Ad</th>
                    <th>Soyad</th>
                    <th>Rol</th>
                    <th>Durum</th>
                    <th>Oluşturulma Tarihi</th>
                    <?php if ($can('user.manage')): ?>
                        <th>İşlemler</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><span class="username-badge"><?= htmlspecialchars($user['username']) ?></span></td>
                    <td class="user-name"><?= htmlspecialchars($user['first_name']) ?></td>
                    <td class="user-name"><?= htmlspecialchars($user['last_name']) ?></td>
                    <td><span class="role-badge"><?= htmlspecialchars($user['role_name']) ?></span></td>
                    <td>
                        <?php if ((int) $user['is_active'] === 1): ?>
                            <span class="status-badge status-badge-success">Aktif</span>
                        <?php else: ?>
                            <span class="status-badge status-badge-inactive">Pasif</span>
                        <?php endif; ?>
                    </td>
                    <td class="user-date">
                        <?= htmlspecialchars(date('d.m.Y H:i', strtotime($user['created_at']))) ?>
                    </td>
                    <?php if ($can('user.manage')): ?>
                        <td class="user-actions">
                            <a class="button button-small" href="/stok-takip/public/users/edit?id=<?= (int) $user['id'] ?>">Düzenle</a>
                            <?php if ((int) $user['is_active'] === 1): ?>
                                <form method="POST" action="/stok-takip/public/users/delete" onsubmit="return confirm('Bu kullanıcı pasif hale getirilsin mi?');">
            <?= CsrfService::tokenField() ?>
                                    <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                    <button class="button button-small button-danger" type="submit">Pasifleştir</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</main>
