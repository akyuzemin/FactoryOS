<?php
$pageTitle = 'Üretim Fişi & Stok Entegrasyon Detayı';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="page-kicker">Üretim Fişi &amp; Çift Yönlü Stok Hareketi</p>
            <h1>📦 Üretim Kaydı: <?= htmlspecialchars($production['stock_movement_ref'] ?: ('ID #' . $production['id'])) ?></h1>
            <p class="page-description">Üretim kaydıyla eşzamanlı olarak stoğa giren mamul (IN) ve düşülen hammadde (OUT) dökümü.</p>
        </div>
        <div class="header-badges">
            <a href="/stok-takip/public/production/create" class="button button-primary">
                ➕ Yeni Üretim Yap
            </a>
            <a href="/stok-takip/public/production" class="button button-secondary">
                &larr; Üretim Listesi
            </a>
        </div>
    </header>

    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success">
            <span>✓ <?= htmlspecialchars($flashSuccess) ?></span>
            <button type="button" onclick="this.parentElement.remove();" style="background:none; border:none; color:#15803d; cursor:pointer; font-weight:bold; font-size:16px;">&times;</button>
        </div>
    <?php endif; ?>

    <!-- 1. ÜST KPI / ÖZET KARTLARI -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="card" style="padding: 18px 20px;">
            <span style="font-size: 11px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase;">Üretilen Sağlam Panel</span>
            <p style="margin: 6px 0 0 0; font-size: 24px; font-weight: 800; color: var(--green);">
                +<?= number_format((int)$production['panels_produced_qty']) ?> AD
            </p>
            <span style="font-size: 11px; color: var(--text-dim);">Fire: <?= (int)$production['scrap_panels_qty'] ?> Panel</span>
        </div>

        <div class="card" style="padding: 18px 20px;">
            <span style="font-size: 11px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase;">Üretilen Toplam Güç</span>
            <p style="margin: 6px 0 0 0; font-size: 24px; font-weight: 800; color: var(--ink);">
                <?= number_format((float)$production['total_wp_produced'] / 1000, 2) ?> kWp
            </p>
            <span style="font-size: 11px; color: var(--text-dim);"><?= number_format((float)$production['total_wp_produced']) ?> Wp</span>
        </div>

        <div class="card" style="padding: 18px 20px;">
            <span style="font-size: 11px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase;">Üretim Hattı &amp; Vardiya</span>
            <p style="margin: 6px 0 0 0; font-size: 15px; font-weight: 700; color: var(--ink);">
                <?= htmlspecialchars($production['line_name']) ?>
            </p>
            <span style="font-size: 11px; color: var(--cyan);"><?= htmlspecialchars($production['shift_name']) ?> (<?= htmlspecialchars($production['log_date']) ?>)</span>
        </div>

        <div class="card" style="padding: 18px 20px;">
            <span style="font-size: 11px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase;">Tüketilen Solar Hücre</span>
            <p style="margin: 6px 0 0 0; font-size: 22px; font-weight: 800; color: #2563eb;">
                <?= number_format((float)$production['cells_used_qty']) ?> AD
            </p>
            <span style="font-size: 11px; color: var(--text-dim);">Reçete Sarfiyatı (OUT)</span>
        </div>
    </div>

    <!-- 2. MAMUL STOK GİRİŞİ (IN) KARTI -->
    <div class="card" style="padding: 24px; margin-bottom: 24px; border-left: 4px solid var(--green);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--line-subtle); padding-bottom: 10px;">
            <div>
                <h3 style="font-size: 15px; font-weight: 700; color: var(--ink); margin: 0;">
                    📥 Stoğa Eklenen Nihai Mamul (Giriş / IN)
                </h3>
                <p style="font-size: 12px; color: var(--text-secondary); margin: 2px 0 0 0;">
                    Üretim tamamlandığında otomatik olarak depoya giriş yapan nihai mamul.
                </p>
            </div>
            <div>
                <span class="status-badge status-badge-success" style="font-size: 12px; padding: 4px 12px;">
                    ✓ Giriş Yapıldı (IN)
                </span>
            </div>
        </div>

        <?php if (empty($inMovements)): ?>
            <div style="padding: 16px; color: var(--text-dim); font-size: 13px;">
                Mamul giriş kaydı bulunamadı (Eski veya manuel üretim kaydı).
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="saas-table" style="font-size: 13px;">
                    <thead>
                        <tr>
                            <th>Mamul Ürün</th>
                            <th>Kategori</th>
                            <th>Giriş Yapılan Depo / Lokasyon</th>
                            <th style="text-align: right; width: 140px;">Giriş Miktarı</th>
                            <th>Hareket Tipi</th>
                            <th>Referans No</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inMovements as $mv): ?>
                            <tr style="background: #f0fdf4;">
                                <td>
                                    <strong style="color: var(--ink);"><?= htmlspecialchars($mv['material_name']) ?></strong>
                                    <span class="code-badge" style="font-size: 10px; margin-left: 4px;"><?= htmlspecialchars($mv['material_code']) ?></span>
                                </td>
                                <td>
                                    <span style="font-size: 12px; color: var(--text-secondary);"><?= htmlspecialchars($mv['category_name'] ?? 'Mamul') ?></span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($mv['warehouse_name']) ?></strong>
                                    <span style="color: var(--text-dim); font-size: 11px;">/ <?= htmlspecialchars($mv['location_name']) ?></span>
                                </td>
                                <td style="text-align: right; font-weight: 800; color: #16a34a; font-size: 14px;">
                                    +<?= number_format((float)$mv['quantity'], 2, ',', '.') ?> <?= htmlspecialchars($mv['unit_symbol']) ?>
                                </td>
                                <td>
                                    <span class="status-badge status-badge-success" style="font-size: 11px;">
                                        IN (Giriş)
                                    </span>
                                </td>
                                <td>
                                    <span class="code-badge" style="font-size: 11px;"><?= htmlspecialchars($mv['reference_no']) ?></span>
                                </td>
                                <td style="color: var(--text-secondary); font-size: 12px;">
                                    <?= htmlspecialchars($mv['created_at']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- 3. HAMMADDE TÜKETİMİ (OUT) KARTI -->
    <div class="card" style="padding: 24px; margin-bottom: 24px; border-left: 4px solid var(--amber);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--line-subtle); padding-bottom: 10px;">
            <div>
                <h3 style="font-size: 15px; font-weight: 700; color: var(--ink); margin: 0;">
                    🧱 Otomatik Düşülen Hammadde Kalemleri (Çıkış / OUT)
                </h3>
                <p style="font-size: 12px; color: var(--text-secondary); margin: 2px 0 0 0;">
                    BOM reçetesine göre kaynak depodan düşülen sarfiyat ve hammadde listesi.
                </p>
            </div>
            <?php if (!empty($production['stock_movement_ref'])): ?>
                <a href="/stok-takip/public/stock-movements?search=<?= urlencode($production['stock_movement_ref']) ?>" class="button button-sm button-secondary">
                    📦 Tüm Stok Hareketlerinde Filtrele &rarr;
                </a>
            <?php endif; ?>
        </div>

        <div style="overflow-x: auto;">
            <table class="saas-table" style="font-size: 13px;">
                <thead>
                    <tr>
                        <th>Hammadde / Bileşen</th>
                        <th>Kategori</th>
                        <th>Çıkış Yapılan Depo / Raf</th>
                        <th style="text-align: right; width: 140px;">Düşülen Miktar</th>
                        <th>İşlem Tipi</th>
                        <th>Kullanıcı</th>
                        <th>İşlem Tarihi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($outMovements)): ?>
                    <tr>
                        <td colspan="7" class="empty-state">
                            Bu üretim kaydına bağlı hammadde çıkış hareketi bulunamadı.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($outMovements as $mv): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--ink);"><?= htmlspecialchars($mv['material_name']) ?></strong>
                                <span class="code-badge" style="font-size: 10px; margin-left: 4px;"><?= htmlspecialchars($mv['material_code']) ?></span>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--text-secondary);"><?= htmlspecialchars($mv['category_name'] ?? '-') ?></span>
                            </td>
                            <td>
                                <span><?= htmlspecialchars($mv['warehouse_name']) ?></span>
                                <span style="color: var(--text-dim); font-size: 11px;">/ <?= htmlspecialchars($mv['location_name']) ?></span>
                            </td>
                            <td style="text-align: right; font-weight: 700; color: #dc2626;">
                                -<?= number_format((float)$mv['quantity'], 2, ',', '.') ?> <?= htmlspecialchars($mv['unit_symbol']) ?>
                            </td>
                            <td>
                                <span class="status-badge status-badge-warning" style="font-size: 10.5px;">
                                    OUT (Çıkış)
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--text-secondary);"><?= htmlspecialchars($mv['user_name'] ?? 'Sistem') ?></span>
                            </td>
                            <td style="color: var(--text-secondary); font-size: 12px;">
                                <?= htmlspecialchars($mv['created_at']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($production['notes'])): ?>
            <div style="margin-top: 18px; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                <strong style="font-size: 12px; color: var(--ink);">Operasyonel Notlar:</strong>
                <p style="margin: 4px 0 0 0; font-size: 12.5px; color: var(--text-secondary);"><?= nl2br(htmlspecialchars($production['notes'])) ?></p>
            </div>
        <?php endif; ?>
    </div>

</main>
