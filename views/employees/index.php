<?php

$pageTitle = 'Çalışan Yönetimi';
$activePage = 'employees';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';

$formatDate = static function (?string $date): string {
    return $date ? date('d.m.Y', strtotime($date)) : '-';
};

$statusLabels = [
    'ACTIVE'     => 'Aktif',
    'ON_LEAVE'   => 'İzinli',
    'SUSPENDED'  => 'Askıda',
    'TERMINATED' => 'İşten Ayrıldı',
];

$statusBadges = [
    'ACTIVE'     => 'status-badge-success',
    'ON_LEAVE'   => 'status-badge-warning',
    'SUSPENDED'  => 'status-badge-warning',
    'TERMINATED' => 'status-badge-inactive',
];

$employmentTypeLabels = [
    'FULL_TIME'  => 'Tam Zamanlı',
    'PART_TIME'  => 'Yarı Zamanlı',
    'CONTRACTOR' => 'Taşeron / Yüklenici',
    'INTERN'     => 'Stajyer',
];

$employmentTypeBadges = [
    'FULL_TIME'  => 'background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;',
    'PART_TIME'  => 'background: #fdf4ff; color: #86198f; border: 1px solid #f5d0fe;',
    'CONTRACTOR' => 'background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa;',
    'INTERN'     => 'background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0;',
];
?>

<main class="main-content">

    <!-- 1. ÜST BAŞLIK VE ÖZET -->
    <header class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
        <div>
            <p class="page-kicker" style="font-size: 11.5px; font-weight: 800; color: #3b82f6; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 4px 0;">
                👥 İnsan Kaynakları &amp; Ekip Yönetimi
            </p>
            <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
                Çalışan Yönetimi
            </h1>
            <p class="page-description" style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">
                Fabrika personeli, departman dağılımı, pozisyonlar, istihdam türleri ve vardiya atamaları
            </p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <?php if ($can('employee.view')): ?>
                <a href="/stok-takip/public/employees/dashboard" class="button button-secondary" style="height: 38px; padding: 0 16px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a;">
                    <span>📊</span> Dashboard
                </a>
            <?php endif; ?>
            <?php if ($can('employee.manage')): ?>
                <a href="/stok-takip/public/employees/create" class="button button-primary" style="height: 38px; padding: 0 16px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                    <span>➕</span> Yeni Çalışan Ekle
                </a>
            <?php endif; ?>
            <div class="page-count" style="background: #ffffff; border: 1px solid #e2e8f0; padding: 7px 14px; border-radius: 8px; font-size: 13px; font-weight: 700; color: #334155; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
                Toplam: <b style="color: #2563eb;"><?= htmlspecialchars((string) count($employees)) ?></b> çalışan listeleniyor
            </div>
        </div>
    </header>

    <!-- 2. KPI GÖSTERGE KARTLARI (5 METRİK) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 24px;">
        
        <!-- TOPLAM ÇALIŞAN -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; border-left: 4px solid #2563eb; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #2563eb; text-transform: uppercase; display: flex; align-items: center; justify-content: space-between;">
                <span>👥 TOPLAM ÇALIŞAN</span>
            </div>
            <div style="font-size: 24px; font-weight: 900; color: #1e3a8a; margin: 4px 0 2px 0;">
                <?= (int)($kpi['total'] ?? 0) ?>
            </div>
            <div style="font-size: 11.5px; color: #64748b;">
                Kayıtlı Tüm Personel
            </div>
        </div>

        <!-- AKTİF ÇALIŞAN -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; border-left: 4px solid #16a34a; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #16a34a; text-transform: uppercase; display: flex; align-items: center; justify-content: space-between;">
                <span>🟢 AKTİF</span>
            </div>
            <div style="font-size: 24px; font-weight: 900; color: #15803d; margin: 4px 0 2px 0;">
                <?= (int)($kpi['active'] ?? 0) ?>
            </div>
            <div style="font-size: 11.5px; color: #64748b;">
                Fiilen Görevde Olanlar
            </div>
        </div>

        <!-- İZİNLİ ÇALIŞAN -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; border-left: 4px solid #d97706; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #d97706; text-transform: uppercase; display: flex; align-items: center; justify-content: space-between;">
                <span>🏖️ İZİNLİ</span>
            </div>
            <div style="font-size: 24px; font-weight: 900; color: #b45309; margin: 4px 0 2px 0;">
                <?= (int)($kpi['on_leave'] ?? 0) ?>
            </div>
            <div style="font-size: 11.5px; color: #64748b;">
                Yıllık / Raporlu / Ücretsiz İzin
            </div>
        </div>

        <!-- ASKIDA -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; border-left: 4px solid #ea580c; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #ea580c; text-transform: uppercase; display: flex; align-items: center; justify-content: space-between;">
                <span>⏸️ ASKIDA</span>
            </div>
            <div style="font-size: 24px; font-weight: 900; color: #c2410c; margin: 4px 0 2px 0;">
                <?= (int)($kpi['suspended'] ?? 0) ?>
            </div>
            <div style="font-size: 11.5px; color: #64748b;">
                Sözleşmesi Askıda Olanlar
            </div>
        </div>

        <!-- İŞTEN AYRILAN -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; border-left: 4px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; display: flex; align-items: center; justify-content: space-between;">
                <span>🛑 İŞTEN AYRILAN</span>
            </div>
            <div style="font-size: 24px; font-weight: 900; color: #475569; margin: 4px 0 2px 0;">
                <?= (int)($kpi['terminated'] ?? 0) ?>
            </div>
            <div style="font-size: 11.5px; color: #64748b;">
                Ayrılmış / Eski Personel
            </div>
        </div>

    </div>

    <!-- 3. FİLTRELEME PANELİ -->
    <form method="GET" action="/stok-takip/public/employees" class="filter-panel" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; align-items: flex-end;">
            
            <div class="filter-field">
                <label for="search" style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 6px; text-transform: uppercase;">Arama</label>
                <input type="search" id="search" name="search" placeholder="Sicil no, ad, soyad, e-posta, unvan..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>" style="width: 100%; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 13px;">
            </div>

            <div class="filter-field">
                <label for="department_id" style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 6px; text-transform: uppercase;">Departman</label>
                <select id="department_id" name="department_id" style="width: 100%; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 13px; background: #fff;">
                    <option value="">Tüm Departmanlar</option>
                    <?php foreach ($filterOptions['departments'] as $dept): ?>
                        <option value="<?= (int)$dept['id'] ?>" <?= ((int)($filters['department_id'] ?? 0) === (int)$dept['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['name']) ?> (<?= htmlspecialchars($dept['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-field">
                <label for="status" style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 6px; text-transform: uppercase;">Çalışma Durumu</label>
                <select id="status" name="status" style="width: 100%; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 13px; background: #fff;">
                    <option value="">Tüm Durumlar</option>
                    <?php foreach ($filterOptions['statuses'] as $sKey => $sLabel): ?>
                        <option value="<?= htmlspecialchars($sKey) ?>" <?= (($filters['status'] ?? '') === $sKey) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-field">
                <label for="employment_type" style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 6px; text-transform: uppercase;">İstihdam Türü</label>
                <select id="employment_type" name="employment_type" style="width: 100%; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 13px; background: #fff;">
                    <option value="">Tüm Türler</option>
                    <?php foreach ($filterOptions['employment_types'] as $etKey => $etLabel): ?>
                        <option value="<?= htmlspecialchars($etKey) ?>" <?= (($filters['employment_type'] ?? '') === $etKey) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($etLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-field">
                <label for="shift_id" style="display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 6px; text-transform: uppercase;">Vardiya</label>
                <select id="shift_id" name="shift_id" style="width: 100%; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 13px; background: #fff;">
                    <option value="">Tüm Vardiyalar</option>
                    <?php foreach ($filterOptions['shifts'] as $shift): ?>
                        <option value="<?= (int)$shift['id'] ?>" <?= (($filters['shift_id'] ?? '') === (string)$shift['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($shift['name']) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="none" <?= (($filters['shift_id'] ?? '') === 'none') ? 'selected' : '' ?>>Vardiyasız (Ofis)</option>
                </select>
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="button button-primary" style="height: 38px; padding: 0 16px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                    <span>🔍</span> Filtrele
                </button>
                <a href="/stok-takip/public/employees" class="button button-secondary" style="height: 38px; padding: 0 12px; font-size: 13px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                    Temizle
                </a>
            </div>

        </div>
    </form>

    <!-- 4. ÇALIŞANLAR TABLOSU -->
    <div class="table-shell" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); overflow-x: auto;">
        <table class="materials-table" style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-size: 11.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.03em;">
                    <th style="padding: 12px 16px;">Sicil No</th>
                    <th style="padding: 12px 16px;">Ad Soyad</th>
                    <th style="padding: 12px 16px;">Departman</th>
                    <th style="padding: 12px 16px;">Pozisyon</th>
                    <th style="padding: 12px 16px;">İstihdam Türü</th>
                    <th style="padding: 12px 16px;">Durum</th>
                    <th style="padding: 12px 16px;">Vardiya</th>
                    <th style="padding: 12px 16px;">E-posta &amp; Telefon</th>
                    <th style="padding: 12px 16px; text-align: center;">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="9" style="padding: 32px 16px; text-align: center; color: #64748b; font-size: 14px;">
                            <div style="font-size: 32px; margin-bottom: 8px;">🔍</div>
                            Aranan kriterlere uygun çalışan kaydı bulunamadı.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($employees as $emp): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background-color 0.15s ease;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
                            
                            <!-- Sicil No -->
                            <td style="padding: 12px 16px; white-space: nowrap;">
                                <span class="username-badge" style="font-family: monospace; font-size: 12px; font-weight: 700; background: #f1f5f9; color: #0f172a; padding: 4px 8px; border-radius: 6px; border: 1px solid #e2e8f0;">
                                    <?= htmlspecialchars($emp['registration_no']) ?>
                                </span>
                            </td>

                            <!-- Ad Soyad & User Badge -->
                            <td style="padding: 12px 16px;">
                                <div style="font-weight: 700; color: #0f172a; font-size: 13.5px;">
                                    <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                </div>
                                <?php if (!empty($emp['linked_username'])): ?>
                                    <div style="display: inline-flex; align-items: center; gap: 4px; font-size: 10.5px; color: #2563eb; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 4px; padding: 1px 6px; margin-top: 3px;">
                                        <span>👤</span> @<?= htmlspecialchars($emp['linked_username']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Departman -->
                            <td style="padding: 12px 16px;">
                                <div style="font-weight: 600; color: #1e293b;">
                                    <?= htmlspecialchars($emp['department_name']) ?>
                                </div>
                                <div style="font-size: 11px; color: #64748b;">
                                    <?= htmlspecialchars($emp['department_code']) ?>
                                </div>
                            </td>

                            <!-- Pozisyon -->
                            <td style="padding: 12px 16px;">
                                <div style="font-weight: 600; color: #1e293b;">
                                    <?= htmlspecialchars($emp['position_title']) ?>
                                </div>
                                <?php if (!empty($emp['position_level'])): ?>
                                    <span style="display: inline-block; font-size: 10.5px; font-weight: 600; color: #475569; background: #f1f5f9; padding: 1px 6px; border-radius: 4px; margin-top: 2px;">
                                        <?= htmlspecialchars($emp['position_level']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- İstihdam Türü -->
                            <td style="padding: 12px 16px; white-space: nowrap;">
                                <span style="display: inline-block; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 6px; <?= $employmentTypeBadges[$emp['employment_type']] ?? 'background:#f1f5f9;color:#475569;' ?>">
                                    <?= htmlspecialchars($employmentTypeLabels[$emp['employment_type']] ?? $emp['employment_type']) ?>
                                </span>
                            </td>

                            <!-- Durum -->
                            <td style="padding: 12px 16px; white-space: nowrap;">
                                <span class="status-badge <?= $statusBadges[$emp['status']] ?? 'status-badge-info' ?>" style="font-size: 11.5px; font-weight: 700; padding: 4px 9px; border-radius: 6px;">
                                    <?= htmlspecialchars($statusLabels[$emp['status']] ?? $emp['status']) ?>
                                </span>
                                <?php if ($emp['status'] === 'TERMINATED' && !empty($emp['termination_date'])): ?>
                                    <div style="font-size: 10.5px; color: #ef4444; margin-top: 2px;">
                                        <?= $formatDate($emp['termination_date']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Vardiya -->
                            <td style="padding: 12px 16px; white-space: nowrap;">
                                <?php if (!empty($emp['shift_name'])): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; font-weight: 600; color: #0284c7; background: #f0f9ff; border: 1px solid #bae6fd; padding: 3px 8px; border-radius: 6px;">
                                        <span>⏰</span> <?= htmlspecialchars($emp['shift_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="font-size: 11.5px; color: #94a3b8; font-style: italic;">
                                        Vardiyasız (Ofis)
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- E-posta & Telefon -->
                            <td style="padding: 12px 16px;">
                                <div style="font-size: 12px; color: #2563eb;">
                                    <a href="mailto:<?= htmlspecialchars($emp['email']) ?>" style="color: #2563eb; text-decoration: none;">
                                        <?= htmlspecialchars($emp['email']) ?>
                                    </a>
                                </div>
                                <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">
                                    <?= htmlspecialchars($emp['phone'] ?? '-') ?>
                                </div>
                            </td>

                            <!-- İşlemler -->
                            <td style="padding: 12px 16px; text-align: center; white-space: nowrap;">
                                <a href="/stok-takip/public/employees/show?id=<?= (int)$emp['id'] ?>" class="button button-small" style="padding: 4px 10px; font-size: 11.5px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                    <span>🔍</span> Görüntüle
                                </a>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Scroll Konumu ve Geri Dönüş Yönetimi -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Görüntüle linklerine tıklandığında mevcut scroll konumunu ve URL'yi kaydet
        document.querySelectorAll('a[href*="/employees/show"]').forEach(function(link) {
            link.addEventListener('click', function() {
                sessionStorage.setItem('employees_scroll_pos', window.scrollY.toString());
                sessionStorage.setItem('employees_filter_url', window.location.href);
            });
        });

        // 2. Sayfaya geri dönüldüğünde scroll konumunu geri yükle
        var savedPos = sessionStorage.getItem('employees_scroll_pos');
        var savedUrl = sessionStorage.getItem('employees_filter_url');

        if (savedPos !== null) {
            if (!savedUrl || savedUrl === window.location.href) {
                window.scrollTo({
                    top: parseInt(savedPos, 10),
                    behavior: 'instant'
                });
            }
            sessionStorage.removeItem('employees_scroll_pos');
            sessionStorage.removeItem('employees_filter_url');
        }

        // 3. Filtreleme form submit edildiğinde veya Temizle tıklandığında scroll kaydını temizle
        var filterForm = document.querySelector('form.filter-panel');
        if (filterForm) {
            filterForm.addEventListener('submit', function() {
                sessionStorage.removeItem('employees_scroll_pos');
                sessionStorage.removeItem('employees_filter_url');
            });
        }
        var clearBtn = document.querySelector('form.filter-panel a[href*="/employees"]');
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                sessionStorage.removeItem('employees_scroll_pos');
                sessionStorage.removeItem('employees_filter_url');
            });
        }
    });
    </script>

</main>

