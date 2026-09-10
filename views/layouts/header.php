<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">

    <title><?= htmlspecialchars($pageTitle ?? 'Stok Takip Sistemi') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/stok-takip/public/css/app.css?v=<?= file_exists(__DIR__ . '/../../public/css/app.css') ? filemtime(__DIR__ . '/../../public/css/app.css') : time() ?>">
</head>

<body>