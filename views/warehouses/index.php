<?php

$pageTitle = 'Depolar';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="page-kicker">Operasyon Yönetimi</p>
            <h1>Depolar</h1>
            <p class="page-description">Aktif depoları ve kullanım amaçlarını görüntüleyin.</p>
        </div>
        <?php if ($can('warehouse.manage')): ?>
            <a class="button button-primary" href="/stok-takip/public/warehouses/create">+ Yeni Depo</a>
        <?php endif; ?>
        <div class="page-count">
            <?= htmlspecialchars((string) count($warehouses)) ?> aktif depo
        </div>
    </header>

    <div class="table-shell">
        <table class="materials-table warehouses-table">
            <thead>
                <tr>
                    <th>Kod</th>
                    <th>Depo</th>
                    <th>Açıklama</th>
                    <th>Durum</th>
                    <?php if ($can('warehouse.manage')): ?>
                        <th>İşlemler</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($warehouses as $warehouse): ?>
                <tr>
                    <td><span class="code-badge"><?= htmlspecialchars($warehouse['code']) ?></span></td>
                    <td class="warehouse-name"><?= htmlspecialchars($warehouse['name']) ?></td>
                    <td><?= htmlspecialchars($warehouse['description'] ?? '-') ?></td>
                    <td><span class="status-badge">Aktif</span></td>
                    <?php if ($can('warehouse.manage')): ?>
                        <td class="warehouse-actions">
                            <a class="button button-small" href="/stok-takip/public/warehouses/edit?id=<?= (int) $warehouse['id'] ?>">Düzenle</a>
                            <form method="POST" action="/stok-takip/public/warehouses/delete" onsubmit="return confirm('Bu depo pasif hale getirilsin mi?');">
            <?= CsrfService::tokenField() ?>
                                <input type="hidden" name="id" value="<?= (int) $warehouse['id'] ?>">
                                <button class="button button-small button-danger" type="submit">Pasifleştir</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</main>
