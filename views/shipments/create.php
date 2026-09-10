<?php
$pageTitle = 'Yeni Sevkiyat Emri';
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">
    <div style="margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: #64748b; margin-bottom: 8px;">
            <a href="/stok-takip/public/shipments" style="color: #4338ca; text-decoration: none; font-weight: 600;">Sevkiyat Yönetimi</a>
            <span>&rsaquo;</span>
            <span style="color: #0f172a; font-weight: 600;">Yeni Sevkiyat Emri</span>
        </div>
        <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0;">➕ Yeni Sevkiyat Emri</h1>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; font-size: 13.5px; font-weight: 600; margin-bottom: 20px;">
            ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; max-width: 600px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <form method="POST" action="/stok-takip/public/shipments/create">
            <?= CsrfService::tokenField() ?>
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 700; font-size: 13px; color: #374151; margin-bottom: 6px;">🏢 Alıcı Müşteri / Firma Adı:</label>
                <input type="text" name="customer_name" required placeholder="Örn: Güneş Enerjisi A.Ş." style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13.5px;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 700; font-size: 13px; color: #374151; margin-bottom: 6px;">📍 Sevkiyat / Teslimat Adresi:</label>
                <textarea name="shipping_address" required rows="4" placeholder="Sevkiyatın yapılacağı açık adres..." style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; resize: vertical;"></textarea>
            </div>

            <div style="display: flex; gap: 12px; align-items: center; border-top: 1px solid #e2e8f0; padding-top: 20px; margin-top: 20px;">
                <button type="submit" class="button button-primary" style="padding: 10px 20px; font-weight: 700; font-size: 13.5px; cursor: pointer;">
                    Sevkiyat Emrini Oluştur
                </button>
                <a href="/stok-takip/public/shipments" class="button" style="padding: 10px 16px; font-size: 13.5px; text-decoration: none;">
                    Vazgeç
                </a>
            </div>
        </form>
    </div>
</main>


