<?php
declare(strict_types=1);

$pageTitle = 'Satın Alma Talepleri';
$activePage = 'purchase_requests';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';

$formatDate = static function (?string $date): string {
    if (empty($date)) {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date('d.m.Y', $ts) : $date;
};

// Durum Rozet Stilleri
$statusStyleMap = [
    'DRAFT'     => ['label' => 'Taslak',         'bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#cbd5e1', 'icon' => '📝'],
    'SUBMITTED' => ['label' => 'Onay Bekliyor',  'bg' => '#fef3c7', 'color' => '#92400e', 'border' => '#fde68a', 'icon' => '⏳'],
    'APPROVED'  => ['label' => 'Onaylandı',      'bg' => '#dcfce7', 'color' => '#166534', 'border' => '#bbf7d0', 'icon' => '✅'],
    'REJECTED'  => ['label' => 'Reddedildi',     'bg' => '#fee2e2', 'color' => '#991b1b', 'border' => '#fecaca', 'icon' => '❌'],
    'ORDERED'   => ['label' => 'Siparişleşti',   'bg' => '#e0e7ff', 'color' => '#3730a3', 'border' => '#c7d2fe', 'icon' => '🛒'],
    'CANCELLED' => ['label' => 'İptal',          'bg' => '#f3f4f6', 'color' => '#6b7280', 'border' => '#e5e7eb', 'icon' => '🚫'],
];

// Öncelik Rozet Stilleri
$priorityStyleMap = [
    'LOW'    => ['label' => 'Düşük',  'bg' => '#f0fdf4', 'color' => '#166534', 'border' => '#bbf7d0'],
    'MEDIUM' => ['label' => 'Normal', 'bg' => '#eff6ff', 'color' => '#1e40af', 'border' => '#bfdbfe'],
    'HIGH'   => ['label' => 'Yüksek', 'bg' => '#fff7ed', 'color' => '#c2410c', 'border' => '#fed7aa'],
    'URGENT' => ['label' => 'Acil',   'bg' => '#fef2f2', 'color' => '#991b1b', 'border' => '#fecaca'],
];

// Pagination link query helper
$buildQuery = static function (array $overrides = []) use ($filters): string {
    $params = array_merge($filters, $overrides);
    $params = array_filter($params, static fn($v) => $v !== '' && $v !== 0 && $v !== '0');
    return http_build_query($params);
};

$hasActiveFilters = !empty($filters['search']) || !empty($filters['status']) || !empty($filters['priority']) || !empty($filters['department_id']) || !empty($filters['requested_by']) || !empty($filters['date_from']) || !empty($filters['date_to']);
?>

<main class="main-content">

    <!-- FLASH MESAJLARI -->
    <?php if (!empty($_SESSION['success'])): ?>
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 12px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 13.5px; font-weight: 600; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span>✅</span> <?= htmlspecialchars($_SESSION['success']) ?>
            </div>
            <button type="button" onclick="this.parentElement.remove();" style="background: none; border: none; font-size: 16px; cursor: pointer; color: #166534;">&times;</button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 13.5px; font-weight: 600; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span>⚠️</span> <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <button type="button" onclick="this.parentElement.remove();" style="background: none; border: none; font-size: 16px; cursor: pointer; color: #991b1b;">&times;</button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- 1. ÜST BAŞLIK VE AKSİYON -->
    <header class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">
        <div>
            <p class="page-kicker" style="font-size: 11.5px; font-weight: 800; color: #3b82f6; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 4px 0;">
                📦 Satın Alma &amp; Tedarik Yönetimi
            </p>
            <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
                Satın Alma Talepleri
            </h1>
            <p class="page-description" style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">
                Malzeme ve hizmet ihtiyaçlarının talep, onay ve satın alma sürecini yönetin.
            </p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <?php if ($can('purchase.request')): ?>
                <a href="/stok-takip/public/purchase-requests/create" class="button button-primary" style="height: 38px; padding: 0 16px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                    <span>➕</span> Yeni Satın Alma Talebi
                </a>
            <?php endif; ?>
            <div class="page-count" style="background: #ffffff; border: 1px solid #e2e8f0; padding: 7px 14px; border-radius: 8px; font-size: 13px; font-weight: 700; color: #334155; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
                Toplam: <b style="color: #2563eb;"><?= (int)($kpis['total'] ?? 0) ?></b> talep
            </div>
        </div>
    </header>

    <!-- 2. KPI GÖSTERGE KARTLARI (5 METRİK) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; margin-bottom: 20px;">
        
        <!-- TOPLAM TALEP -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; border-left: 4px solid #2563eb; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #2563eb; text-transform: uppercase; display: flex; align-items: center; justify-content: space-between;">
                <span>📦 TOPLAM TALEP</span>
            </div>
            <div style="font-size: 24px; font-weight: 900; color: #1e3a8a; margin: 4px 0 2px 0;">
                <?= (int)($kpis['total'] ?? 0) ?>
            </div>
            <div style="font-size: 11.5px; color: #64748b;">
                Kayıtlı Tüm Talepler
            </div>
        </div>

        <!-- TASLAK -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; border-left: 4px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; display: flex; align-items: center; justify-content: space-between;">
                <span>📝 TASLAK</span>
            </div>
            <div style="font-size: 24px; font-weight: 900; color: #334155; margin: 4px 0 2px 0;">
                <?= (int)($kpis['draft'] ?? 0) ?>
            </div>
            <div style="font-size: 11.5px; color: #64748b;">
                Hazırlık Aşamasında
            </div>
        </div>

        <!-- ONAY BEKLEYEN -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; border-left: 4px solid #f59e0b; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #d97706; text-transform: uppercase; display: flex; align-items: center; justify-content: space-between;">
                <span>⏳ ONAY BEKLEYEN</span>
            </div>
            <div style="font-size: 24px; font-weight: 900; color: #b45309; margin: 4px 0 2px 0;">
                <?= (int)($kpis['submitted'] ?? 0) ?>
            </div>
            <div style="font-size: 11.5px; color: #64748b;">
                Yönetici Onayında
            </div>
        </div>

        <!-- ONAYLANAN -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; border-left: 4px solid #16a34a; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #16a34a; text-transform: uppercase; display: flex; align-items: center; justify-content: space-between;">
                <span>✅ ONAYLANAN</span>
            </div>
            <div style="font-size: 24px; font-weight: 900; color: #15803d; margin: 4px 0 2px 0;">
                <?= (int)($kpis['approved'] ?? 0) ?>
            </div>
            <div style="font-size: 11.5px; color: #64748b;">
                Satın Almaya Hazır
            </div>
        </div>

        <!-- SİPARİŞLEŞEN -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; border-left: 4px solid #6366f1; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #6366f1; text-transform: uppercase; display: flex; align-items: center; justify-content: space-between;">
                <span>🛒 SİPARİŞLEŞEN</span>
            </div>
            <div style="font-size: 24px; font-weight: 900; color: #4338ca; margin: 4px 0 2px 0;">
                <?= (int)($kpis['ordered'] ?? 0) ?>
            </div>
            <div style="font-size: 11.5px; color: #64748b;">
                Siparişi Verilenler
            </div>
        </div>

    </div>

    <!-- 3. FİLTRE ALANI -->
    <form method="GET" action="/stok-takip/public/purchase-requests" class="filter-panel" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; align-items: flex-end;">
            
            <!-- Arama -->
            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 4px;">Arama</label>
                <input type="search" name="search" placeholder="Talep no veya açıklama..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; box-sizing: border-box;">
            </div>

            <!-- Durum -->
            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 4px;">Durum</label>
                <select name="status" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; box-sizing: border-box; background: #fff;">
                    <option value="">Tüm Durumlar</option>
                    <?php foreach ($statuses as $st): ?>
                        <option value="<?= htmlspecialchars($st) ?>" <?= (($filters['status'] ?? '') === $st) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($statusStyleMap[$st]['label'] ?? $st) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Öncelik -->
            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 4px;">Öncelik</label>
                <select name="priority" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; box-sizing: border-box; background: #fff;">
                    <option value="">Tüm Öncelikler</option>
                    <?php foreach ($priorities as $pr): ?>
                        <option value="<?= htmlspecialchars($pr) ?>" <?= (($filters['priority'] ?? '') === $pr) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($priorityStyleMap[$pr]['label'] ?? $pr) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Departman -->
            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 4px;">Departman</label>
                <select name="department_id" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; box-sizing: border-box; background: #fff;">
                    <option value="">Tüm Departmanlar</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= (int)$dept['id'] ?>" <?= ((int)($filters['department_id'] ?? 0) === (int)$dept['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Talep Eden -->
            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 4px;">Talep Eden</label>
                <select name="requested_by" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; box-sizing: border-box; background: #fff;">
                    <option value="">Tüm Kullanıcılar</option>
                    <?php foreach ($requesters as $req): ?>
                        <option value="<?= (int)$req['id'] ?>" <?= ((int)($filters['requested_by'] ?? 0) === (int)$req['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($req['full_name'] ?: $req['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Başlangıç Tarihi -->
            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 4px;">Talep Başlangıç</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; box-sizing: border-box;">
            </div>

            <!-- Bitiş Tarihi -->
            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 4px;">Talep Bitiş</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; box-sizing: border-box;">
            </div>

            <!-- Butonlar -->
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="button button-primary" style="padding: 8px 16px; font-size: 13px; font-weight: 700; height: 38px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                    <span>🔍</span> Filtrele
                </button>
                <a href="/stok-takip/public/purchase-requests" class="button button-secondary" style="padding: 8px 14px; font-size: 13px; font-weight: 600; height: 38px; text-decoration: none; display: inline-flex; align-items: center; background: #ffffff; border: 1px solid #cbd5e1; color: #475569;">
                    Temizle
                </a>
            </div>

        </div>
    </form>

    <!-- 4. TALEP TABLOSU -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 20px;">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; white-space: nowrap;">Talep No</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; white-space: nowrap;">Talep Tarihi</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; white-space: nowrap;">Departman</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; white-space: nowrap;">Talep Eden</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; white-space: nowrap;">İhtiyaç Tarihi</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-align: center; white-space: nowrap;">Öncelik</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-align: center; white-space: nowrap;">Durum</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-align: center; white-space: nowrap;">Kalem Sayısı</th>
                        <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-align: right; white-space: nowrap;">Aksiyon</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="9" style="padding: 48px 20px; text-align: center;">
                                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px;">
                                    <span style="font-size: 36px;">📋</span>
                                    <div style="font-size: 15px; font-weight: 700; color: #334155;">
                                        <?= $hasActiveFilters ? 'Filtrelere uygun satın alma talebi bulunamadı.' : 'Henüz satın alma talebi bulunmuyor.' ?>
                                    </div>
                                    <p style="font-size: 13px; color: #64748b; margin: 0; max-width: 400px;">
                                        <?= $hasActiveFilters ? 'Filtre kriterlerini değiştirerek veya temizleyerek tekrar deneyebilirsiniz.' : 'Departmanınızın malzeme ve hizmet ihtiyaçları için hemen yeni bir talep oluşturabilirsiniz.' ?>
                                    </p>
                                    <?php if ($can('purchase.request') && !$hasActiveFilters): ?>
                                        <a href="/stok-takip/public/purchase-requests/create" class="button button-primary" style="margin-top: 8px; padding: 9px 18px; font-size: 13px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                            <span>➕</span> İlk Talebi Oluştur
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $r): ?>
                            <?php
                            $stInfo = $statusStyleMap[$r['status']] ?? ['label' => $r['status'], 'bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#cbd5e1', 'icon' => ''];
                            $prInfo = $priorityStyleMap[$r['priority']] ?? ['label' => $r['priority'], 'bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#cbd5e1'];
                            ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background='#f8fafc';" onmouseout="this.style.background='#ffffff';">
                                
                                <!-- Talep No -->
                                <td style="padding: 12px 16px; font-family: monospace; font-weight: 700; color: #1e40af;">
                                    <a href="/stok-takip/public/purchase-requests/show?id=<?= (int)$r['id'] ?>" style="color: #2563eb; text-decoration: none; font-weight: 700;">
                                        <?= htmlspecialchars($r['request_no']) ?>
                                    </a>
                                </td>

                                <!-- Talep Tarihi -->
                                <td style="padding: 12px 16px; color: #334155; white-space: nowrap;">
                                    <?= $formatDate($r['request_date']) ?>
                                </td>

                                <!-- Departman -->
                                <td style="padding: 12px 16px; font-weight: 600; color: #0f172a; white-space: nowrap;">
                                    <span style="display: inline-block; padding: 2px 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 12px;">
                                        <?= htmlspecialchars($r['department_name'] ?? '-') ?>
                                    </span>
                                </td>

                                <!-- Talep Eden -->
                                <td style="padding: 12px 16px; color: #334155; white-space: nowrap;">
                                    <?= htmlspecialchars($r['requester_name'] ?: $r['requester_username']) ?>
                                </td>

                                <!-- İhtiyaç Tarihi -->
                                <td style="padding: 12px 16px; color: #475569; white-space: nowrap;">
                                    <?= $formatDate($r['required_date']) ?>
                                </td>

                                <!-- Öncelik -->
                                <td style="padding: 12px 16px; text-align: center; white-space: nowrap;">
                                    <span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: <?= $prInfo['bg'] ?>; color: <?= $prInfo['color'] ?>; border: 1px solid <?= $prInfo['border'] ?>;">
                                        <?= htmlspecialchars($prInfo['label']) ?>
                                    </span>
                                </td>

                                <!-- Durum -->
                                <td style="padding: 12px 16px; text-align: center; white-space: nowrap;">
                                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: <?= $stInfo['bg'] ?>; color: <?= $stInfo['color'] ?>; border: 1px solid <?= $stInfo['border'] ?>;">
                                        <span><?= $stInfo['icon'] ?></span> <?= htmlspecialchars($stInfo['label']) ?>
                                    </span>
                                </td>

                                <!-- Kalem Sayısı -->
                                <td style="padding: 12px 16px; text-align: center; white-space: nowrap;">
                                    <span style="display: inline-block; padding: 2px 8px; background: #f1f5f9; color: #334155; border-radius: 6px; font-weight: 700; font-size: 12px;">
                                        <?= (int)($r['item_count'] ?? 0) ?> kalem
                                    </span>
                                </td>

                                <!-- Aksiyon -->
                                <td style="padding: 12px 16px; text-align: right; white-space: nowrap;">
                                    <div style="display: inline-flex; gap: 6px; justify-content: flex-end;">
                                        <a href="/stok-takip/public/purchase-requests/show?id=<?= (int)$r['id'] ?>" class="button button-small" style="padding: 4px 10px; font-size: 12px; text-decoration: none; background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; display: inline-flex; align-items: center; gap: 4px;">
                                            <span>👁️</span> Görüntüle
                                        </a>
                                        <?php if ($r['status'] === 'DRAFT' && $can('purchase.request')): ?>
                                            <a href="/stok-takip/public/purchase-requests/edit?id=<?= (int)$r['id'] ?>" class="button button-small" style="padding: 4px 10px; font-size: 12px; text-decoration: none; background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; display: inline-flex; align-items: center; gap: 4px;">
                                                <span>✏️</span> Düzenle
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 5. SAYFALAMA (PAGINATION) -->
    <?php if ($totalPages > 1): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 12px 0;">
            <div style="font-size: 12.5px; color: #64748b;">
                Toplam <b><?= $totalCount ?></b> kayıttan <b><?= min($totalCount, $offset + 1) ?> - <?= min($totalCount, $offset + $limit) ?></b> arası gösteriliyor
            </div>
            <div style="display: flex; gap: 6px; align-items: center;">
                <?php if ($page > 1): ?>
                    <a href="/stok-takip/public/purchase-requests?<?= $buildQuery(['page' => $page - 1]) ?>" class="button button-small" style="padding: 6px 12px; font-size: 12px; text-decoration: none; background: #fff; border: 1px solid #cbd5e1;">
                        &laquo; Önceki
                    </a>
                <?php endif; ?>

                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                    <a href="/stok-takip/public/purchase-requests?<?= $buildQuery(['page' => $p]) ?>" class="button button-small" style="padding: 6px 12px; font-size: 12px; text-decoration: none; <?= $p === $page ? 'background: #2563eb; color: #fff; border-color: #2563eb;' : 'background: #fff; border: 1px solid #cbd5e1; color: #334155;' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="/stok-takip/public/purchase-requests?<?= $buildQuery(['page' => $page + 1]) ?>" class="button button-small" style="padding: 6px 12px; font-size: 12px; text-decoration: none; background: #fff; border: 1px solid #cbd5e1;">
                        Sonraki &raquo;
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</main>
</body>
</html>

