<?php

$pageTitle = 'Tedarikçiler';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="page-kicker">Satın Alma Yönetimi</p>
            <h1>Tedarikçiler</h1>
            <p class="page-description">Aktif tedarikçilerin iletişim ve firma bilgilerini görüntüleyin.</p>
        </div>
        <?php if ($can('supplier.manage')): ?>
            <a class="button button-primary" href="/stok-takip/public/suppliers/create">+ Yeni Tedarikçi</a>
        <?php endif; ?>
        <div class="page-count">
            <?= htmlspecialchars((string) count($suppliers)) ?> aktif tedarikçi
        </div>
    </header>

    <div class="table-shell">
        <table class="materials-table suppliers-table">
            <thead>
                <tr>
                    <th>Kod</th>
                    <th>Firma</th>
                    <th>İletişim</th>
                    <th>Telefon</th>
                    <th>E-posta</th>
                    <th>Adres</th>
                    <th>Durum</th>
                    <?php if ($can('supplier.manage')): ?>
                        <th>İşlemler</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($suppliers as $supplier): ?>
                <tr>
                    <td><span class="code-badge"><?= htmlspecialchars($supplier['code']) ?></span></td>
                    <td>
                        <strong class="supplier-name"><?= htmlspecialchars($supplier['name']) ?></strong>
                    </td>
                    <td><?= htmlspecialchars($supplier['contact_name'] ?? '-') ?></td>
                    <td>
                        <?php if (!empty($supplier['phone'])): ?>
                            <a class="supplier-link" href="tel:<?= htmlspecialchars($supplier['phone']) ?>">
                                <?= htmlspecialchars($supplier['phone']) ?>
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($supplier['email'])): ?>
                            <a class="supplier-link" href="mailto:<?= htmlspecialchars($supplier['email']) ?>">
                                <?= htmlspecialchars($supplier['email']) ?>
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($supplier['address'] ?? '-') ?></td>
                    <td><span class="status-badge">Aktif</span></td>
                    <?php if ($can('supplier.manage')): ?>
                        <td class="material-actions">
                            <a class="button button-small" href="/stok-takip/public/suppliers/edit?id=<?= (int) $supplier['id'] ?>">Düzenle</a>
                            <form method="POST" action="/stok-takip/public/suppliers/delete" onsubmit="return confirm('Bu tedarikçi pasif hale getirilsin mi?');">
            <?= CsrfService::tokenField() ?>
                                <input type="hidden" name="id" value="<?= (int) $supplier['id'] ?>">
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
