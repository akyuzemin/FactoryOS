<?php
$pageTitle = 'Yeni İş Emri Oluştur';
$activePage = 'mes';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <header class="page-header" style="margin-bottom: 24px;">
        <div>
            <p class="page-kicker" style="font-size: 12px; font-weight: 700; color: #4338ca; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 4px 0;">MES Üretim Yönetimi</p>
            <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">➕ Yeni İş Emri Oluştur</h1>
            <p class="page-description" style="font-size: 13.5px; color: #64748b; margin: 4px 0 0 0;">Üretilecek mamul, üretim hattı, hedef adet ve panel başına üretim süresini belirleyin.</p>
        </div>
        <div class="header-badges">
            <a href="/stok-takip/public/mes" class="button button-secondary" style="font-size: 13px; font-weight: 600; text-decoration: none;">
                &larr; İş Emirlerine Dön
            </a>
        </div>
    </header>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger" style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13.5px; font-weight: 600;">
            ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="card" style="max-width: 720px; padding: 28px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <form method="POST" action="/stok-takip/public/mes/work-orders/store">
            <?= CsrfService::tokenField() ?>
            
            <!-- 1. İş Emri No -->
            <div style="margin-bottom: 20px;">
                <label class="form-label" style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">İş Emri No *</label>
                <input type="text" name="work_order_no" class="form-input" style="width: 100%; padding: 10px 14px; font-size: 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 700; color: #0f172a; font-family: monospace;" value="<?= htmlspecialchars($suggestedNo) ?>" required>
                <span style="font-size: 11.5px; color: #64748b; margin-top: 4px; display: block;">Benzersiz üretim takip numarası.</span>
            </div>

            <!-- 2. Üretilecek Mamul & Üretim Hattı (2 Kolon) -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 20px;">
                <div>
                    <label class="form-label" style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">Üretilecek Mamul *</label>
                    <select name="product_material_id" id="product_material_id" class="form-input" style="width: 100%; padding: 10px 12px; font-size: 13.5px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 600; color: #0f172a;" required>
                        <option value="">-- Mamul Seçin --</option>
                        <?php foreach ($materials as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= ($m['code'] === 'SOL-MOD-550W' || $m['code'] === 'PNL-550W-MONO') ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label" style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">Üretim Hattı *</label>
                    <select name="production_line_id" class="form-input" style="width: 100%; padding: 10px 12px; font-size: 13.5px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 600; color: #0f172a;" required>
                        <option value="">-- Üretim Hattı Seçin --</option>
                        <?php foreach ($lines as $l): ?>
                            <option value="<?= $l['id'] ?>" <?= ($l['code'] === 'LINE-LAM-1' || $l['code'] === 'LAM-LINE-1' || $l['id'] == 1) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($l['name']) ?> (<?= htmlspecialchars($l['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- 3. Üretilecek Adet & Panel Başına Üretim Süresi (2 Kolon) -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 24px;">
                <div>
                    <label class="form-label" style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">Üretilecek Adet (Hedef) *</label>
                    <input type="number" step="1" min="1" name="planned_quantity" class="form-input" style="width: 100%; padding: 10px 14px; font-size: 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 700; color: #0f172a;" value="10" required>
                    <span style="font-size: 11.5px; color: #64748b; margin-top: 4px; display: block;">Toplam hedeflenen panel adedi.</span>
                </div>

                <div>
                    <label class="form-label" style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">Panel Başına Üretim Süresi *</label>
                    <select name="interval_seconds" class="form-input" style="width: 100%; padding: 10px 12px; font-size: 13.5px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 600; color: #0f172a;" required>
                        <option value="5" selected>⚡ 5 Saniye / Panel (Hızlı Simülasyon)</option>
                        <option value="10">⚡ 10 Saniye / Panel</option>
                        <option value="30">⏱️ 30 Saniye / Panel</option>
                        <option value="60">⏱️ 60 Saniye (1 Dakika / Panel)</option>
                        <option value="180">🏭 180 Saniye (3 Dakika / Panel - Standart)</option>
                        <option value="300">⏳ 300 Saniye (5 Dakika / Panel)</option>
                    </select>
                    <span style="font-size: 11.5px; color: #64748b; margin-top: 4px; display: block;">Hatta her bir panelin üretim süresi.</span>
                </div>
            </div>

            <!-- Bilgilendirme Notu -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin-bottom: 24px; font-size: 12.5px; color: #475569; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 18px;">ℹ️</span>
                <div>
                    İş emri başlangıçta <strong>Planlandı (PLANNED)</strong> durumunda oluşturulur. İlgili BOM reçetesi otomatik olarak eşleştirilir. Üretim, iş emri detayından veya simülatörden başlatılabilir.
                </div>
            </div>

            <!-- Butonlar -->
            <div style="display: flex; justify-content: flex-end; gap: 12px; padding-top: 16px; border-top: 1px solid #f1f5f9;">
                <a href="/stok-takip/public/mes" class="button button-secondary" style="padding: 10px 18px; font-size: 13px; font-weight: 600; text-decoration: none;">İptal</a>
                <button type="submit" class="button button-primary" style="padding: 10px 24px; font-size: 13.5px; font-weight: 700; background: #2563eb; color: #ffffff; border: none; border-radius: 8px; cursor: pointer;">
                    ✓ İş Emrini Oluştur
                </button>
            </div>

        </form>
    </div>

</main>
