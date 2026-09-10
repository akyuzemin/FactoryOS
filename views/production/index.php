<?php
$pageTitle = 'Üretim Kayıtları & Stok Tüketimi';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="page-kicker">Üretim &amp; Stok Entegrasyonu</p>
            <h1>🏭 Üretim Kayıtları &amp; Otomatik Tüketim</h1>
            <p class="page-description">Gerçekleşen üretimlerin BOM reçeteleri üzerinden otomatik düşen hammadde tüketim geçmişi.</p>
        </div>
        <div class="header-badges">
            <a href="/stok-takip/public/production/create" class="button button-primary">
                <span>➕</span> Yeni Üretim Kaydı &amp; Stok Düşüşü
            </a>
        </div>
    </header>

    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success">
            <span>✓ <?= htmlspecialchars($flashSuccess) ?></span>
            <button type="button" onclick="this.parentElement.remove();" style="background:none; border:none; color:#15803d; cursor:pointer; font-weight:bold; font-size:16px;">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger">
            <span>⚠️ <?= htmlspecialchars($flashError) ?></span>
            <button type="button" onclick="this.parentElement.remove();" style="background:none; border:none; color:#b91c1c; cursor:pointer; font-weight:bold; font-size:16px;">&times;</button>
        </div>
    <?php endif; ?>

    <!-- FİLTRE PANELİ -->
    <div class="card" style="padding: 16px 20px; margin-bottom: 24px;">
        <form method="GET" action="/stok-takip/public/production" style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 220px;">
                <input type="text" name="search" class="form-input" placeholder="Referans no (PRD-...) veya not ile ara..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>
            <div style="width: 220px;">
                <select name="line_id" class="form-select">
                    <option value="">Tüm Üretim Hatları</option>
                    <?php foreach ($lines as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= ($_GET['line_id'] ?? '') == $l['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($l['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="button button-secondary">
                <span>🔍</span> Filtrele
            </button>
            <?php if (!empty($_GET['search']) || !empty($_GET['line_id'])): ?>
                <a href="/stok-takip/public/production" class="button button-secondary" style="color: #64748b;">
                    Temizle
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ÜRETİM GÜNLÜĞÜ TABLOSU -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-shell" style="border: none; border-radius: 0;">
            <table class="materials-table">
                <thead>
                    <tr>
                        <th style="width: 110px;">Tarih</th>
                        <th>Üretim Hattı</th>
                        <th>Vardiya</th>
                        <th style="text-align: right; width: 130px;">Üretilen Panel</th>
                        <th style="text-align: right; width: 130px;">Toplam Güç</th>
                        <th>Stok Referansı</th>
                        <th style="text-align: center; width: 130px;">Tüketim Durumu</th>
                        <th style="text-align: right; width: 120px;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="8" class="empty-state">
                            Kayıtlı üretim günlüğü bulunamadı. "Yeni Üretim Kaydı" butonu ile ilk üretim &amp; stok düşüş işlemini gerçekleştirebilirsiniz.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="white-space: nowrap; font-weight: 600; color: var(--ink);">
                                <?= htmlspecialchars($log['log_date']) ?>
                            </td>
                            <td>
                                <strong style="color: var(--ink); display: block;"><?= htmlspecialchars($log['line_name']) ?></strong>
                                <span class="code-badge" style="font-size: 10.5px;"><?= htmlspecialchars($log['line_code']) ?></span>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--text-secondary);"><?= htmlspecialchars($log['shift_name']) ?></span>
                            </td>
                            <td style="text-align: right; font-weight: 700; color: var(--green); font-size: 14px;">
                                <?= number_format((int)$log['panels_produced_qty']) ?> AD
                            </td>
                            <td style="text-align: right; font-weight: 600; color: var(--ink);">
                                <?= number_format((float)$log['total_wp_produced'] / 1000, 1) ?> kWp
                            </td>
                            <td>
                                <?php if (!empty($log['stock_movement_ref'])): ?>
                                    <a href="/stok-takip/public/stock-movements?search=<?= urlencode($log['stock_movement_ref']) ?>" class="code-badge" style="text-decoration: underline;" title="Stok hareketlerini filtrele">
                                        <?= htmlspecialchars($log['stock_movement_ref']) ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: var(--text-dim);">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ((int)$log['movement_count'] > 0): ?>
                                    <span class="status-badge status-badge-success" style="font-size: 11px;">
                                        ✓ <?= (int)$log['movement_count'] ?> Kalem Düştü
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-badge-inactive" style="font-size: 11px;">
                                        Manuel Kayıt
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <a href="/stok-takip/public/production/show?id=<?= (int)$log['id'] ?>" class="button button-sm button-secondary">
                                    👁 Detay
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
