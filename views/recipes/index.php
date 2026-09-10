<?php
$pageTitle = 'Üretim Reçeteleri (BOM)';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <!-- BAŞLIK & AKSİYON BUTONU -->
    <header class="page-header">
        <div>
            <p class="page-kicker">Üretim &amp; Stok Entegrasyonu</p>
            <h1>📜 Üretim Reçeteleri (BOM)</h1>
            <p class="page-description">Ürün bazında hammadde ve sarf malzeme tüketim oranları, fire katsayıları ve reçete tanımları.</p>
        </div>
        <div class="header-badges">
            <?php if ($can('recipe.manage')): ?>
                <a href="/stok-takip/public/recipes/create" class="button button-primary">
                    <span>➕</span> Yeni Reçete Oluştur
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- FLASH BİLDİRİM MESAJLARI -->
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

    <!-- FİLTRE VE ARAMA PANELİ -->
    <div class="card" style="padding: 16px 20px; margin-bottom: 24px;">
        <form method="GET" action="/stok-takip/public/recipes" style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 240px;">
                <input type="text" name="search" class="form-input" placeholder="Reçete kodu, reçete adı veya açıklama ile ara..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>
            <div style="width: 180px;">
                <select name="is_active" class="form-select">
                    <option value="">Tüm Durumlar</option>
                    <option value="1" <?= ($_GET['is_active'] ?? '') === '1' ? 'selected' : '' ?>>Sadece Aktifler</option>
                    <option value="0" <?= ($_GET['is_active'] ?? '') === '0' ? 'selected' : '' ?>>Pasifler</option>
                </select>
            </div>
            <button type="submit" class="button button-secondary">
                <span>🔍</span> Filtrele
            </button>
            <?php if (!empty($_GET['search']) || isset($_GET['is_active']) && $_GET['is_active'] !== ''): ?>
                <a href="/stok-takip/public/recipes" class="button button-secondary" style="color: #64748b;">
                    Temizle
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- REÇETELER TABLOSU -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-shell" style="border: none; border-radius: 0;">
            <table class="materials-table">
                <thead>
                    <tr>
                        <th style="width: 140px;">Reçete Kodu</th>
                        <th>Reçete Adı</th>
                        <th>Nihai Ürün</th>
                        <th style="text-align: center; width: 120px;">Baz Miktar</th>
                        <th style="text-align: center; width: 120px;">Kalem Sayısı</th>
                        <th style="width: 110px;">Durum</th>
                        <th style="text-align: right; width: 160px;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recipes)): ?>
                    <tr>
                        <td colspan="7" class="empty-state">
                            Tanımlı üretim reçetesi bulunamadı. "Yeni Reçete Oluştur" butonuyla ilk reçetenizi ekleyebilirsiniz.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recipes as $r): ?>
                        <tr>
                            <td>
                                <span class="code-badge"><?= htmlspecialchars($r['code']) ?></span>
                            </td>
                            <td>
                                <strong style="color: var(--ink); display: block;"><?= htmlspecialchars($r['name']) ?></strong>
                                <?php if (!empty($r['description'])): ?>
                                    <span style="font-size: 11.5px; color: var(--text-secondary);"><?= htmlspecialchars($r['description']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($r['output_material_name'])): ?>
                                    <span style="color: var(--ink); font-weight: 600;"><?= htmlspecialchars($r['output_material_name']) ?></span>
                                    <span class="code-badge" style="font-size: 10px; margin-left: 4px;"><?= htmlspecialchars($r['output_material_code']) ?></span>
                                <?php else: ?>
                                    <span style="color: var(--text-dim); font-style: italic;">Tanımlanmamış</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center; font-weight: 600;">
                                <?= number_format((float)$r['base_quantity'], 0, ',', '.') ?> <?= htmlspecialchars($r['output_unit_symbol'] ?? 'AD') ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="status-badge status-badge-info" style="font-size: 11px;">
                                    <?= (int)$r['item_count'] ?> Malzeme
                                </span>
                            </td>
                            <td>
                                <?php if ($can('recipe.manage')): ?>
                                    <form method="POST" action="/stok-takip/public/recipes/toggle" style="display: inline;">
            <?= CsrfService::tokenField() ?>
                                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                        <button type="submit" class="status-badge <?= $r['is_active'] ? 'status-badge-success' : 'status-badge-inactive' ?>" style="cursor: pointer; border: none;" title="Durumu değiştirmek için tıklayın">
                                            <?= $r['is_active'] ? '✓ Aktif' : 'Pasif' ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="status-badge <?= $r['is_active'] ? 'status-badge-success' : 'status-badge-inactive' ?>">
                                        <?= $r['is_active'] ? '✓ Aktif' : 'Pasif' ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if ($can('recipe.manage')): ?>
                                    <div style="display: inline-flex; gap: 6px; align-items: center;">
                                        <a href="/stok-takip/public/recipes/edit?id=<?= (int)$r['id'] ?>" class="button button-sm button-secondary" title="Reçeteyi Düzenle">
                                            ✏️ Düzenle
                                        </a>
                                        <form method="POST" action="/stok-takip/public/recipes/delete" onsubmit="return confirm('Bu reçeteyi ve tüm hammadde kalemlerini silmek istediğinize emin misiniz?');" style="margin: 0; display: inline;">
                                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                            <button type="submit" class="button button-sm button-danger" title="Sil">
                                                🗑️
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--text-dim); font-size: 11px;">Görüntüleme Modu</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
