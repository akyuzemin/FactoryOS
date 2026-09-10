<?php

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">
    <header class="page-header">
        <div>
            <p class="page-kicker">Varlık Yönetimi</p>
            <h1><?= htmlspecialchars($formTitle) ?></h1>
            <p class="page-description">Envanter kaydının güncel bilgilerini düzenleyin.</p>
        </div>
        <a class="button" href="/stok-takip/public/inventory/show?id=<?= (int) $formData['id'] ?>">Detaya Dön</a>
    </header>

    <?php require __DIR__ . '/_form.php'; ?>
</main>
