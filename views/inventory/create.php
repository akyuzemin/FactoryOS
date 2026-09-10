<?php

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">
    <header class="page-header">
        <div>
            <p class="page-kicker">Varlık Yönetimi</p>
            <h1><?= htmlspecialchars($formTitle) ?></h1>
            <p class="page-description">Yeni varlık kaydını ve ilişkili satın alma bilgilerini oluşturun.</p>
        </div>
        <a class="button" href="/stok-takip/public/inventory">Listeye Dön</a>
    </header>

    <?php require __DIR__ . '/_form.php'; ?>
</main>
