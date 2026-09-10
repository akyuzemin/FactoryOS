<?php

require_once __DIR__ . '/../../app/Services/CsrfService.php';

$pageTitle = 'Çalışan Kartı: ' . ($employee['first_name'] . ' ' . $employee['last_name']);
$activePage = 'employees';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';

$formatDate = static function (?string $date): string {
    if (empty($date)) {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date('d.m.Y', $ts) : $date;
};

$formatDateTime = static function (?string $datetime): string {
    if (empty($datetime)) {
        return '-';
    }
    $ts = strtotime($datetime);
    return $ts ? date('d.m.Y H:i:s', $ts) : $datetime;
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

    <!-- FLASH BİLDİRİMLERİ -->
    <?php if (!empty($_SESSION['success'])): ?>
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13.5px; font-weight: 600; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span>✅</span>
                <span><?= htmlspecialchars($_SESSION['success']) ?></span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" style="background: transparent; border: none; font-size: 16px; cursor: pointer; color: #166534;">&times;</button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13.5px; font-weight: 600; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span>⚠️</span>
                <span><?= htmlspecialchars($_SESSION['error']) ?></span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" style="background: transparent; border: none; font-size: 16px; cursor: pointer; color: #991b1b;">&times;</button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- 1. ÜST BAŞLIK VE GERİ DÖN BUTONU -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                <span class="page-kicker" style="font-size: 11.5px; font-weight: 800; color: #3b82f6; text-transform: uppercase; letter-spacing: 0.05em;">
                    👥 İnsan Kaynakları &bull; Personel Detayı
                </span>
                <span class="username-badge" style="font-family: monospace; font-size: 12px; font-weight: 700; background: #f1f5f9; color: #0f172a; padding: 2px 8px; border-radius: 6px; border: 1px solid #cbd5e1;">
                    <?= htmlspecialchars($employee['registration_no']) ?>
                </span>
            </div>
            <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em; display: flex; align-items: center; gap: 10px;">
                <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
                <span class="status-badge <?= $statusBadges[$employee['status']] ?? 'status-badge-info' ?>" style="font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 6px; vertical-align: middle;">
                    <?= htmlspecialchars($statusLabels[$employee['status']] ?? $employee['status']) ?>
                </span>
            </h1>
            <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">
                <?= htmlspecialchars($employee['department_name']) ?> &bull; <?= htmlspecialchars($employee['position_title']) ?>
            </p>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="/stok-takip/public/employees" class="button button-secondary" style="height: 38px; padding: 0 16px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;" onclick="if (document.referrer && document.referrer.indexOf('/employees') !== -1 && document.referrer.indexOf('/employees/show') === -1 && document.referrer.indexOf('/employees/create') === -1 && document.referrer.indexOf('/employees/edit') === -1) { event.preventDefault(); window.history.back(); }">
                <span>←</span> Çalışan Listesine Dön
            </a>
            <?php if ($can('employee.manage')): ?>
                <a href="/stok-takip/public/employees/edit?id=<?= (int)$employee['id'] ?>" class="button button-primary" style="height: 38px; padding: 0 16px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                    <span>✏️</span> Düzenle
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. KARTLAR GRID DÜZENİ -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 20px; margin-bottom: 24px;">

        <!-- KART 1: GENEL VE İLETİŞİM BİLGİLERİ -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 13px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 8px;">
                <span>👤</span> Genel &amp; İletişim Bilgileri
            </div>

            <div style="display: flex; flex-direction: column; gap: 12px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #64748b; font-weight: 600;">Sicil Numarası:</span>
                    <span style="font-family: monospace; font-weight: 700; color: #0f172a; background: #f8fafc; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0;">
                        <?= htmlspecialchars($employee['registration_no']) ?>
                    </span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #64748b; font-weight: 600;">Ad Soyad:</span>
                    <span style="font-weight: 700; color: #0f172a;">
                        <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
                    </span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #64748b; font-weight: 600;">E-posta Adresi:</span>
                    <a href="mailto:<?= htmlspecialchars($employee['email']) ?>" style="color: #2563eb; font-weight: 600; text-decoration: none;">
                        <?= htmlspecialchars($employee['email'] ?? '-') ?>
                    </a>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #64748b; font-weight: 600;">Telefon:</span>
                    <span style="color: #0f172a; font-weight: 600;">
                        <?= htmlspecialchars($employee['phone'] ?? '-') ?>
                    </span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #64748b; font-weight: 600;">İşe Giriş Tarihi:</span>
                    <span style="font-weight: 700; color: #0f172a;">
                        <?= $formatDate($employee['hire_date']) ?>
                    </span>
                </div>

                <?php if (!empty($employee['termination_date'])): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; background: #fef2f2; padding: 6px 10px; border-radius: 6px; border: 1px solid #fecaca;">
                        <span style="color: #b91c1c; font-weight: 700;">İşten Ayrılış Tarihi:</span>
                        <span style="font-weight: 800; color: #dc2626;">
                            <?= $formatDate($employee['termination_date']) ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- KART 2: ORGANİZASYON VE POZİSYON BİLGİLERİ -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 13px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 8px;">
                <span>🏢</span> Organizasyon &amp; Görev Bilgileri
            </div>

            <div style="display: flex; flex-direction: column; gap: 12px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #64748b; font-weight: 600;">Departman:</span>
                    <span style="font-weight: 700; color: #0f172a;">
                        <?= htmlspecialchars($employee['department_name']) ?>
                    </span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #64748b; font-weight: 600;">Departman Kodu:</span>
                    <span style="font-family: monospace; font-size: 11.5px; font-weight: 700; color: #475569; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">
                        <?= htmlspecialchars($employee['department_code']) ?>
                    </span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #64748b; font-weight: 600;">Pozisyon / Ünvan:</span>
                    <span style="font-weight: 700; color: #0f172a;">
                        <?= htmlspecialchars($employee['position_title']) ?>
                    </span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #64748b; font-weight: 600;">Pozisyon Seviyesi:</span>
                    <span style="display: inline-block; font-size: 11.5px; font-weight: 600; color: #334155; background: #f8fafc; padding: 2px 8px; border-radius: 4px; border: 1px solid #e2e8f0;">
                        <?= htmlspecialchars($employee['position_level'] ?? 'Standart') ?>
                    </span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #64748b; font-weight: 600;">İstihdam Türü:</span>
                    <span style="font-size: 11.5px; font-weight: 700; padding: 3px 8px; border-radius: 6px; <?= $employmentTypeBadges[$employee['employment_type']] ?? 'background:#f1f5f9;color:#475569;' ?>">
                        <?= htmlspecialchars($employmentTypeLabels[$employee['employment_type']] ?? $employee['employment_type']) ?>
                    </span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #64748b; font-weight: 600;">Çalışma Durumu:</span>
                    <span class="status-badge <?= $statusBadges[$employee['status']] ?? 'status-badge-info' ?>" style="font-size: 11.5px; font-weight: 700; padding: 3px 8px; border-radius: 6px;">
                        <?= htmlspecialchars($statusLabels[$employee['status']] ?? $employee['status']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- KART 3: VARDIYA VE ÇALIŞMA DÜZENİ -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 13px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 8px;">
                <span>⏰</span> Vardiya &amp; Çalışma Düzeni
            </div>

            <div style="display: flex; flex-direction: column; gap: 12px; font-size: 13px;">
                <?php if (!empty($employee['shift_name'])): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: #64748b; font-weight: 600;">Varsayılan Vardiya:</span>
                        <span style="font-weight: 700; color: #0284c7; background: #f0f9ff; border: 1px solid #bae6fd; padding: 3px 8px; border-radius: 6px;">
                            <?= htmlspecialchars($employee['shift_name']) ?>
                        </span>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: #64748b; font-weight: 600;">Vardiya Kodu:</span>
                        <span style="font-family: monospace; font-size: 11.5px; font-weight: 700; color: #0369a1; background: #e0f2fe; padding: 2px 6px; border-radius: 4px;">
                            <?= htmlspecialchars($employee['shift_code'] ?? '-') ?>
                        </span>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: #64748b; font-weight: 600;">Çalışma Saatleri:</span>
                        <span style="font-weight: 800; color: #0f172a;">
                            <?= htmlspecialchars(substr($employee['shift_start_time'] ?? '', 0, 5)) ?> - <?= htmlspecialchars(substr($employee['shift_end_time'] ?? '', 0, 5)) ?>
                        </span>
                    </div>
                <?php else: ?>
                    <div style="padding: 16px; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1; text-align: center; color: #64748b;">
                        <div style="font-size: 20px; margin-bottom: 4px;">🏢</div>
                        <div style="font-weight: 700; color: #334155;">Vardiyasız (Ofis / Genel Çalışma)</div>
                        <div style="font-size: 11.5px; margin-top: 2px;">Personel sabit mesai saatlerinde çalışmaktadır.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- KART 4: SİSTEM & KULLANICI HESABI BAĞLANTISI -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 13px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 8px;">
                <span>🔐</span> Kullanıcı Hesabı &amp; Sistem Bağlantısı
            </div>

            <div style="display: flex; flex-direction: column; gap: 12px; font-size: 13px;">
                <?php if (!empty($employee['user_id']) && !empty($employee['linked_username'])): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: #64748b; font-weight: 600;">Bağlı Hesap:</span>
                        <span style="font-weight: 700; color: #2563eb; background: #eff6ff; border: 1px solid #bfdbfe; padding: 3px 8px; border-radius: 6px;">
                            @<?= htmlspecialchars($employee['linked_username']) ?>
                        </span>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: #64748b; font-weight: 600;">Kullanıcı Rolü:</span>
                        <span style="font-weight: 700; color: #1e293b; background: #f1f5f9; padding: 3px 8px; border-radius: 6px;">
                            <?= htmlspecialchars($employee['user_role_name'] ?? 'Rol Tanımlanmamış') ?>
                        </span>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: #64748b; font-weight: 600;">Giriş E-postası:</span>
                        <span style="color: #334155; font-weight: 600;">
                            <?= htmlspecialchars($employee['user_email'] ?? '-') ?>
                        </span>
                    </div>
                <?php else: ?>
                    <div style="padding: 16px; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1; text-align: center; color: #64748b;">
                        <div style="font-size: 20px; margin-bottom: 4px;">👤</div>
                        <div style="font-weight: 700; color: #334155;">Kullanıcı Hesabı Yok</div>
                        <div style="font-size: 11.5px; margin-top: 2px;">Bu çalışan yalnızca İK kaydı olarak tutulmaktadır; web portalı giriş hesabı bulunmamaktadır.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- 3. VARDIYA ÇİZELGESİ VE ATAMA GEÇMİŞİ -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; gap: 10px;">
            <div>
                <h2 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <span>📅</span> Vardiya Çizelgesi &amp; Atama Geçmişi
                </h2>
                <p style="font-size: 12.5px; color: #64748b; margin: 3px 0 0 0;">
                    Çalışanın günlük atanmış vardiya planı ve son vardiya hareketleri (Son <?= count($employeeShifts ?? []) ?> kayıt)
                </p>
            </div>
            <?php if (!empty($employee['shift_name'])): ?>
                <div style="font-size: 12px; background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; padding: 4px 10px; border-radius: 6px; font-weight: 600;">
                    Varsayılan: <b><?= htmlspecialchars($employee['shift_name']) ?></b> (<?= htmlspecialchars(substr($employee['shift_start_time'] ?? '', 0, 5)) ?> - <?= htmlspecialchars(substr($employee['shift_end_time'] ?? '', 0, 5)) ?>)
                </div>
            <?php endif; ?>
        </div>

        <div style="display: grid; grid-template-columns: <?= ($can('employee.manage') && $employee['status'] !== 'TERMINATED') ? '340px 1fr' : '1fr' ?>; gap: 24px; align-items: start;">
            
            <?php if ($can('employee.manage') && $employee['status'] !== 'TERMINATED'): ?>
                <!-- SOL KOLON: YENİ VARDIYA ATAMA FORMU -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px;">
                    <div style="font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 14px; display: flex; align-items: center; gap: 6px;">
                        <span>➕</span> Yeni Vardiya Ata / Güncelle
                    </div>

                    <form action="/stok-takip/public/employees/shifts/assign" method="POST" style="display: flex; flex-direction: column; gap: 14px;">
                        <?= CsrfService::tokenField() ?>
                        <input type="hidden" name="employee_id" value="<?= (int)$employee['id'] ?>">

                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">
                                Vardiya Tarihi <span style="color: #ef4444;">*</span>
                            </label>
                            <input 
                                type="date" 
                                name="assigned_date" 
                                required 
                                value="<?= date('Y-m-d') ?>" 
                                min="<?= htmlspecialchars($employee['hire_date'] ?? '') ?>"
                                <?= !empty($employee['termination_date']) ? 'max="' . htmlspecialchars($employee['termination_date']) . '"' : '' ?>
                                style="width: 100%; height: 38px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; background: #ffffff;"
                            >
                        </div>

                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">
                                Atanacak Vardiya <span style="color: #ef4444;">*</span>
                            </label>
                            <select 
                                name="shift_id" 
                                required 
                                style="width: 100%; height: 38px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; background: #ffffff;"
                            >
                                <option value="">-- Vardiya Seçiniz --</option>
                                <?php foreach ($availableShifts as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>" <?= ((int)$s['id'] === (int)($employee['default_shift_id'] ?? 0)) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['code']) ?> - <?= htmlspecialchars($s['name']) ?> (<?= substr($s['start_time'], 0, 5) ?> - <?= substr($s['end_time'], 0, 5) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">
                                Not / Açıklama <span style="font-size: 11px; color: #94a3b8; font-weight: normal;">(Opsiyonel)</span>
                            </label>
                            <input 
                                type="text" 
                                name="notes" 
                                maxlength="255" 
                                placeholder="Örn: Hafta sonu nöbet değişimi" 
                                style="width: 100%; height: 38px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; background: #ffffff;"
                            >
                        </div>

                        <button 
                            type="submit" 
                            class="button button-primary" 
                            style="width: 100%; height: 38px; justify-content: center; font-size: 13px; font-weight: 700; margin-top: 4px; display: inline-flex; align-items: center; gap: 6px;"
                        >
                            <span>💾</span> Vardiya Kaydet
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- SAĞ KOLON: VARDIYA GEÇMİŞİ TABLOSU -->
            <div>
                <?php if (empty($employeeShifts)): ?>
                    <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 36px 20px; text-align: center; color: #64748b;">
                        <div style="font-size: 28px; margin-bottom: 8px;">📋</div>
                        <div style="font-weight: 700; font-size: 14px; color: #334155; margin-bottom: 4px;">Henüz atanmış vardiya bulunmuyor.</div>
                        <div style="font-size: 12px; color: #94a3b8;">
                            <?= ($can('employee.manage') && $employee['status'] !== 'TERMINATED') ? 'Soldaki formu kullanarak bu çalışan için gün bazlı vardiya ataması yapabilirsiniz.' : 'Bu çalışan için henüz özel bir vardiya çizelgesi kaydı oluşturulmamıştır.' ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 12.5px; text-align: left;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-weight: 700; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.03em;">
                                    <th style="padding: 10px 14px;">Tarih</th>
                                    <th style="padding: 10px 14px;">Vardiya Kodu</th>
                                    <th style="padding: 10px 14px;">Vardiya Adı &amp; Saatleri</th>
                                    <th style="padding: 10px 14px;">Açıklama / Not</th>
                                    <th style="padding: 10px 14px;">Kayıt Tarihi</th>
                                    <?php if ($can('employee.manage')): ?>
                                        <th style="padding: 10px 14px; text-align: right;">İşlem</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $todayDate = date('Y-m-d');
                                foreach ($employeeShifts as $shift): 
                                    $isToday = ($shift['assigned_date'] === $todayDate);
                                ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9; <?= $isToday ? 'background: #eff6ff;' : '' ?>">
                                        <td style="padding: 10px 14px; white-space: nowrap;">
                                            <span style="font-weight: 700; color: #0f172a;">
                                                <?= $formatDate($shift['assigned_date']) ?>
                                            </span>
                                            <?php if ($isToday): ?>
                                                <span style="display: inline-block; margin-left: 6px; font-size: 10px; font-weight: 800; background: #3b82f6; color: #ffffff; padding: 1px 6px; border-radius: 4px; text-transform: uppercase;">
                                                    Bugün
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 10px 14px; white-space: nowrap;">
                                            <span style="font-family: monospace; font-size: 11px; font-weight: 700; background: #f1f5f9; color: #0284c7; padding: 2px 6px; border-radius: 4px; border: 1px solid #e0f2fe;">
                                                <?= htmlspecialchars($shift['shift_code']) ?>
                                            </span>
                                        </td>
                                        <td style="padding: 10px 14px;">
                                            <div style="font-weight: 600; color: #1e293b;">
                                                <?= htmlspecialchars($shift['shift_name']) ?>
                                            </div>
                                            <div style="font-size: 11px; color: #64748b;">
                                                <?= htmlspecialchars(substr($shift['shift_start_time'], 0, 5)) ?> - <?= htmlspecialchars(substr($shift['shift_end_time'], 0, 5)) ?>
                                            </div>
                                        </td>
                                        <td style="padding: 10px 14px; color: #475569; max-width: 200px;">
                                            <?= !empty($shift['notes']) ? htmlspecialchars($shift['notes']) : '<span style="color: #cbd5e1;">-</span>' ?>
                                        </td>
                                        <td style="padding: 10px 14px; font-size: 11.5px; color: #64748b; white-space: nowrap;">
                                            <?= $formatDateTime($shift['created_at']) ?>
                                        </td>
                                        <?php if ($can('employee.manage')): ?>
                                            <td style="padding: 10px 14px; text-align: right; white-space: nowrap;">
                                                <form action="/stok-takip/public/employees/shifts/delete" method="POST" style="display: inline;" onsubmit="return confirm('<?= htmlspecialchars($formatDate($shift['assigned_date'])) ?> tarihli vardiya atamasını silmek istediğinize emin misiniz?');">
                                                    <?= CsrfService::tokenField() ?>
                                                    <input type="hidden" name="employee_id" value="<?= (int)$employee['id'] ?>">
                                                    <input type="hidden" name="id" value="<?= (int)$shift['id'] ?>">
                                                    <button 
                                                        type="submit" 
                                                        title="Vardiyayı Sil"
                                                        style="background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; border-radius: 6px; padding: 4px 8px; font-size: 11.5px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;"
                                                    >
                                                        <span>🗑️</span> Sil
                                                    </button>
                                                </form>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- 4. ALT BİLGİ: SİSTEM ZAMAN DAMGALARI -->
    <!-- 4. SORUMLU OLDUĞU ENVANTER VARLIKLARI (ZİMMET) -->
    <?php if ($can('inventory.view')): ?>
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                        <span>📦</span> Sorumlu Olduğu Varlıklar
                    </h2>
                    <p style="font-size: 12.5px; color: #64748b; margin: 3px 0 0 0;">
                        Bu personele zimmetlenmiş veya sorumluluğunda bulunan aktif envanter varlıkları (Toplam <?= count($assignedAssets ?? []) ?> varlık)
                    </p>
                </div>
            </div>

            <?php if (empty($employee['user_id'])): ?>
                <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 28px 20px; text-align: center; color: #64748b;">
                    <div style="font-size: 24px; margin-bottom: 6px;">👤</div>
                    <div style="font-weight: 700; font-size: 13.5px; color: #334155; margin-bottom: 3px;">Bu çalışana bağlı bir sistem kullanıcı hesabı bulunmamaktadır.</div>
                    <div style="font-size: 12px; color: #94a3b8;">Envanter modülünde zimmet atamaları sistem kullanıcı hesapları üzerinden ilişkilendirilmektedir.</div>
                </div>
            <?php elseif (empty($assignedAssets)): ?>
                <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 28px 20px; text-align: center; color: #64748b;">
                    <div style="font-size: 24px; margin-bottom: 6px;">📦</div>
                    <div style="font-weight: 700; font-size: 13.5px; color: #334155; margin-bottom: 3px;">Bu çalışanın üzerinde kayıtlı aktif varlık bulunmamaktadır.</div>
                    <div style="font-size: 12px; color: #94a3b8;">Personele zimmetlenmiş herhangi bir ekipman, donanım veya demirbaş kaydı yoktur.</div>
                </div>
            <?php else: ?>
                <?php
                // TERMINATED çalışan için zimmetli/kullanımda varlık uyarısı
                $hasUnreturnedAssets = ($employee['status'] === 'TERMINATED') && array_reduce($assignedAssets, function($carry, $item) {
                    return $carry || in_array($item['status'], ['ASSIGNED', 'IN_USE'], true);
                }, false);
                ?>

                <?php if ($hasUnreturnedAssets): ?>
                    <div style="background: #fef2f2; border: 1px solid #f87171; border-left: 4px solid #dc2626; color: #991b1b; padding: 14px 18px; border-radius: 8px; margin-bottom: 18px; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 12px;">
                        <span style="font-size: 20px;">⚠️</span>
                        <div>
                            <div style="font-weight: 800; font-size: 13.5px; color: #b91c1c;">Teslim Alınmamış Varlık Uyarısı</div>
                            <div style="font-weight: 500; font-size: 12.5px; color: #7f1d1d; margin-top: 2px;">
                                Bu çalışan işten ayrılmış (TERMINATED) durumdadır; ancak üzerinde hâlâ teslim alınmamış / iade edilmemiş aktif zimmetli varlıklar bulunmaktadır! Lütfen ilgili varlıkların iade sürecini tamamlayınız.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 12.5px; text-align: left;">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-weight: 700; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.03em;">
                                <th style="padding: 10px 14px;">Varlık Kodu</th>
                                <th style="padding: 10px 14px;">Varlık Adı</th>
                                <th style="padding: 10px 14px;">Kategori</th>
                                <th style="padding: 10px 14px;">Seri No / Model</th>
                                <th style="padding: 10px 14px;">Durum</th>
                                <th style="padding: 10px 14px;">Konum</th>
                                <th style="padding: 10px 14px;">Satın Alma Tarihi</th>
                                <th style="padding: 10px 14px; text-align: right;">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $assetStatusLabels = [
                                'IN_STOCK'   => 'Stokta',
                                'ASSIGNED'   => 'Zimmetli',
                                'IN_USE'     => 'Kullanımda',
                                'IN_REPAIR'  => 'Bakımda',
                                'LOST'       => 'Kayıp',
                                'RETIRED'    => 'Hizmet Dışı',
                                'DISPOSED'   => 'Hurda',
                            ];
                            $assetStatusStyles = [
                                'IN_STOCK'   => 'background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;',
                                'ASSIGNED'   => 'background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;',
                                'IN_USE'     => 'background: #f0fdfa; color: #0f766e; border: 1px solid #99f6e4;',
                                'IN_REPAIR'  => 'background: #fffbeb; color: #b45309; border: 1px solid #fde68a;',
                                'LOST'       => 'background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca;',
                                'RETIRED'    => 'background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;',
                                'DISPOSED'   => 'background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5;',
                            ];

                            foreach ($assignedAssets as $asset): 
                                $locationText = '-';
                                if (!empty($asset['warehouse_name']) || !empty($asset['location_name'])) {
                                    $locationText = trim(($asset['warehouse_name'] ?? '') . ' / ' . ($asset['location_name'] ?? ''), ' /');
                                } elseif (!empty($asset['production_line_name'])) {
                                    $locationText = 'Hat: ' . $asset['production_line_name'];
                                }
                            ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 10px 14px; white-space: nowrap;">
                                        <a href="/stok-takip/public/inventory/show?id=<?= (int)$asset['id'] ?>" style="font-family: monospace; font-size: 11.5px; font-weight: 700; color: #2563eb; text-decoration: none; background: #eff6ff; padding: 2px 6px; border-radius: 4px; border: 1px solid #dbeafe;">
                                            <?= htmlspecialchars($asset['asset_code']) ?>
                                        </a>
                                    </td>
                                    <td style="padding: 10px 14px;">
                                        <div style="font-weight: 700; color: #0f172a;">
                                            <?= htmlspecialchars($asset['asset_name']) ?>
                                        </div>
                                    </td>
                                    <td style="padding: 10px 14px; white-space: nowrap; color: #475569;">
                                        <?= htmlspecialchars($asset['category_name'] ?? '-') ?>
                                    </td>
                                    <td style="padding: 10px 14px; font-size: 11.5px; color: #334155;">
                                        <div><b>Seri:</b> <?= htmlspecialchars($asset['serial_no'] ?? '-') ?></div>
                                        <?php if (!empty($asset['model_no'])): ?>
                                            <div style="color: #64748b;"><b>Model:</b> <?= htmlspecialchars($asset['model_no']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 10px 14px; white-space: nowrap;">
                                        <span style="font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 6px; <?= $assetStatusStyles[$asset['status']] ?? 'background: #f1f5f9; color: #475569;' ?>">
                                            <?= htmlspecialchars($assetStatusLabels[$asset['status']] ?? $asset['status']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 10px 14px; color: #475569; font-size: 12px;">
                                        <?= htmlspecialchars($locationText) ?>
                                    </td>
                                    <td style="padding: 10px 14px; white-space: nowrap; font-size: 12px; color: #64748b;">
                                        <?= $formatDate($asset['purchase_date'] ?? null) ?>
                                    </td>
                                    <td style="padding: 10px 14px; text-align: right; white-space: nowrap;">
                                        <a href="/stok-takip/public/inventory/show?id=<?= (int)$asset['id'] ?>" class="button button-secondary" style="height: 28px; padding: 0 10px; font-size: 11.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; text-decoration: none;">
                                            <span>🔍</span> Görüntüle
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- 5. ALT BİLGİ: SİSTEM ZAMAN DAMGALARI -->
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; font-size: 12px; color: #64748b;">
        <div>
            Kayıt Oluşturulma: <b style="color: #334155;"><?= $formatDateTime($employee['created_at']) ?></b>
        </div>
        <div>
            Son Güncelleme: <b style="color: #334155;"><?= $formatDateTime($employee['updated_at']) ?></b>
        </div>
    </div>

</main>

