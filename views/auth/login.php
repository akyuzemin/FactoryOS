<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Giriş - Stok Takip</title>
    <link rel="stylesheet" href="/stok-takip/public/css/app.css">
</head>

<body class="login-page">

<div class="login-box">

    <div class="login-mark"></div>
    <h1>Stok Takip</h1>
    <p class="login-subtitle">Yönetim panelinize giriş yapın.</p>

    <?php if (!empty($error)): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <div class="form-group">
            <label for="username">Kullanıcı Adı</label>

            <input
                type="text"
                id="username"
                name="username"
                required
            >
        </div>

        <div class="form-group">
            <label for="password">Şifre</label>

            <input
                type="password"
                id="password"
                name="password"
                required
            >
        </div>

        <button type="submit">
            Giriş Yap
        </button>

    </form>

</div>

</body>
</html>