<?php

$pageTitle = 'Yeni Çalışan Ekle';
$activePage = 'employees';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';

$selected = static function (mixed $current, mixed $expected): string {
    return (string) $current === (string) $expected ? 'selected' : '';
};

$value = static function (string $key) use ($formData): string {
    return htmlspecialchars((string) ($formData[$key] ?? ''), ENT_QUOTES, 'UTF-8');
};
?>

<main class="main-content">

    <!-- 1. ÜST BAŞLIK -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
        <div>
            <p class="page-kicker" style="font-size: 11.5px; font-weight: 800; color: #3b82f6; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 4px 0;">
                👥 İnsan Kaynakları &bull; Personel Tanımlama
            </p>
            <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
                Yeni Çalışan Ekle
            </h1>
            <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">
                Sisteme yeni bir çalışan kaydı ekleyin ve pozisyon/vardiya atamasını yapın.
            </p>
        </div>

        <div>
            <a href="/stok-takip/public/employees" class="button button-secondary" style="height: 38px; padding: 0 16px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                <span>←</span> Çalışan Listesine Dön
            </a>
        </div>
    </div>

    <!-- 2. HATA MESAJI BİLDİRİMİ -->
    <?php if (!empty($error)): ?>
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 14px 18px; border-radius: 10px; font-size: 13.5px; font-weight: 600; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
            <span style="font-size: 18px;">⚠️</span>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <!-- 3. ÇALIŞAN FORMU -->
    <div class="form-panel" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        
        <form method="POST" action="<?= htmlspecialchars($formAction) ?>" id="employee-form" class="material-form">
            <?= CsrfService::tokenField() ?>

            <!-- BÖLÜM 1: TEMEL & KİMLİK BİLGİLERİ -->
            <section style="margin-bottom: 28px;">
                <div style="font-size: 13.5px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 8px;">
                    <span style="background: #eff6ff; color: #2563eb; width: 24px; height: 24px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800;">1</span>
                    Temel &amp; İletişim Bilgileri
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px;">
                    
                    <!-- Sicil No (Otomatik) -->
                    <div class="form-field">
                        <label for="registration_no" style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                            Sicil Numarası
                        </label>
                        <input id="registration_no" type="text" readonly value="<?= htmlspecialchars($predictedRegNo) ?>" style="width: 100%; height: 38px; border: 1px solid #e2e8f0; background: #f8fafc; border-radius: 8px; padding: 0 12px; font-family: monospace; font-weight: 700; color: #334155;">
                        <small style="font-size: 11px; color: #64748b; margin-top: 4px; display: block;">Sistem tarafından otomatik üretilir.</small>
                    </div>

                    <!-- Ad -->
                    <div class="form-field">
                        <label for="first_name" style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                            Ad <span style="color: #dc2626;">*</span>
                        </label>
                        <input id="first_name" name="first_name" type="text" maxlength="100" required value="<?= $value('first_name') ?>" placeholder="Örn: Ahmet" style="width: 100%; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 13px;">
                    </div>

                    <!-- Soyad -->
                    <div class="form-field">
                        <label for="last_name" style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                            Soyad <span style="color: #dc2626;">*</span>
                        </label>
                        <input id="last_name" name="last_name" type="text" maxlength="100" required value="<?= $value('last_name') ?>" placeholder="Örn: Yılmaz" style="width: 100%; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 13px;">
                    </div>

                    <!-- E-posta -->
                    <div class="form-field">
                        <label for="email" style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                            E-posta Adresi
                        </label>
                        <input id="email" name="email" type="email" maxlength="150" value="<?= $value('email') ?>" placeholder="Örn: ahmet.yilmaz@fabrika.local" style="width: 100%; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 13px;">
                    </div>

                    <!-- Telefon -->
                    <div class="form-field">
                        <label for="phone" style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                            Telefon Numarası
                        </label>
                        <input id="phone" name="phone" type="text" maxlength="30" value="<?= $value('phone') ?>" placeholder="Örn: +90 532 100 0000" style="width: 100%; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 13px;">
                    </div>

                </div>
            </section>

            <!-- BÖLÜM 2: ORGANİZASYON & POZİSYON BİLGİLERİ -->
            <section style="margin-bottom: 28px;">
                <div style="font-size: 13.5px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 8px;">
                    <span style="background: #eff6ff; color: #2563eb; width: 24px; height: 24px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800;">2</span>
                    Organizasyon &amp; Görev Bilgileri
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px;">

                    <!-- Departman -->
                    <div class="form-field">
                        <label for="department_id" style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                            Departman <span style="color: #dc2626;">*</span>
                        </label>
                        <select id="department_id" name="department_id" required style="width: 100%; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 13px; background: #fff;" onchange="handleDepartmentChange()">
                            <option value="">Departman seçiniz</option>
                            <?php foreach ($options['departments'] as $dept): ?>
                                <option value="<?= (int) $dept['id'] ?>" <?= $selected($formData['department_id'] ?? '', $dept['id']) ?>>
                                    <?= htmlspecialchars($dept['name']) ?> (<?= htmlspecialchars($dept['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Pozisyon -->
                    <div class="form-field">
                        <label for="position_id" style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                            Pozisyon / Ünvan <span style="color: #dc2626;">*</span>
                        </label>
                        <select id="position_id" name="position_id" required style="width: 100%; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 13px; background: #fff;">
                            <option value="">Önce departman seçiniz</option>
                        </select>
                    </div>

                    <!-- İstihdam Türü -->
                    <div class="form-field">
                        <label for="employment_type" style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                            İstihdam Türü <span style="color: #dc2626;">*</span>
                        </label>
                        <select id="employment_type" name="employment_type" required style="width: 100%; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 13px; background: #fff;">
                            <?php foreach ($options['employment_types'] as $etKey => $etLabel): ?>
                                <option value="<?= htmlspecialchars($etKey) ?>" <?= $selected($formData['employment_type'] ?? 'FULL_TIME', $etKey) ?>>
                                    <?= htmlspecialchars($etLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Çalışma Durumu -->
                    <div class="form-field">
                        <label for="status" style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                            Çalışma Durumu <span style="color: #dc2626;">*</span>
                        </label>
                        <select id="status" name="status" required style="width: 100%; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 13px; background: #fff;" onchange="handleStatusChange()">
                            <?php foreach ($options['statuses'] as $sKey => $sLabel): ?>
                                <option value="<?= htmlspecialchars($sKey) ?>" <?= $selected($formData['status'] ?? 'ACTIVE', $sKey) ?>>
                                    <?= htmlspecialchars($sLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- İşe Giriş Tarihi -->
                    <div class="form-field">
                        <label for="hire_date" style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                            İşe Giriş Tarihi <span style="color: #dc2626;">*</span>
                        </label>
                        <input id="hire_date" name="hire_date" type="date" required value="<?= $value('hire_date') ?>" style="width: 100%; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 13px;">
                    </div>

                    <!-- İşten Ayrılış Tarihi -->
                    <div class="form-field" id="termination_date_container">
                        <label for="termination_date" style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                            İşten Ayrılış Tarihi <span id="term_req_indicator" style="color: #dc2626; display: none;">*</span>
                        </label>
                        <input id="termination_date" name="termination_date" type="date" value="<?= $value('termination_date') ?>" style="width: 100%; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 13px;">
                        <small id="term_help_text" style="font-size: 11px; color: #64748b; margin-top: 4px; display: block;">Yalnızca "İşten Ayrılan" durumunda zorunludur.</small>
                    </div>

                </div>
            </section>

            <!-- BÖLÜM 3: VARDIYA & SİSTEM BAĞLANTISI -->
            <section style="margin-bottom: 28px;">
                <div style="font-size: 13.5px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 8px;">
                    <span style="background: #eff6ff; color: #2563eb; width: 24px; height: 24px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800;">3</span>
                    Vardiya &amp; Sistem Bağlantısı (Opsiyonel)
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px;">

                    <!-- Varsayılan Vardiya -->
                    <div class="form-field">
                        <label for="default_shift_id" style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                            Varsayılan Vardiya
                        </label>
                        <select id="default_shift_id" name="default_shift_id" style="width: 100%; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 13px; background: #fff;">
                            <option value="">Vardiyasız (Sabit / Ofis Mesaisi)</option>
                            <?php foreach ($options['shifts'] as $shift): ?>
                                <option value="<?= (int) $shift['id'] ?>" <?= $selected($formData['default_shift_id'] ?? '', $shift['id']) ?>>
                                    <?= htmlspecialchars($shift['name']) ?> (<?= htmlspecialchars(substr($shift['start_time'], 0, 5)) ?> - <?= htmlspecialchars(substr($shift['end_time'], 0, 5)) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Kullanıcı Hesabı Eşleştirme -->
                    <div class="form-field">
                        <label for="user_id" style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                            Bağlı Portal Kullanıcı Hesabı
                        </label>
                        <select id="user_id" name="user_id" style="width: 100%; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 13px; background: #fff;">
                            <option value="">Kullanıcı Hesabı Bağlama (Yalnızca İK Kaydı)</option>
                            <?php foreach ($options['available_users'] as $u): ?>
                                <option value="<?= (int) $u['id'] ?>" <?= $selected($formData['user_id'] ?? '', $u['id']) ?>>
                                    @<?= htmlspecialchars($u['username']) ?> &mdash; <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?> (<?= htmlspecialchars($u['role_name'] ?? 'Kullanıcı') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="font-size: 11px; color: #64748b; margin-top: 4px; display: block;">Yalnızca henüz bir personele atanmamış aktif kullanıcılar listelenir.</small>
                    </div>

                </div>
            </section>

            <!-- BUTONLAR -->
            <div style="display: flex; justify-content: flex-end; gap: 12px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                <a href="/stok-takip/public/employees" class="button button-secondary" style="height: 40px; padding: 0 18px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                    İptal
                </a>
                <button type="submit" class="button button-primary" style="height: 40px; padding: 0 20px; font-size: 13.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
                    <span>💾</span> Çalışanı Kaydet
                </button>
            </div>

        </form>

    </div>

</main>

<script>
// Tüm pozisyon verilerini JavaScript nesnesine aktar
const allPositions = <?= json_encode($options['positions'], JSON_UNESCAPED_UNICODE) ?>;
const initialPositionId = <?= json_encode((string)($formData['position_id'] ?? '')) ?>;

function handleDepartmentChange() {
    const deptSelect = document.getElementById('department_id');
    const posSelect = document.getElementById('position_id');
    const selectedDeptId = deptSelect.value;

    posSelect.innerHTML = '';

    if (!selectedDeptId) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'Önce departman seçiniz';
        posSelect.appendChild(opt);
        posSelect.disabled = true;
        return;
    }

    posSelect.disabled = false;
    const filtered = allPositions.filter(p => String(p.department_id) === String(selectedDeptId));

    if (filtered.length === 0) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'Bu departmana ait pozisyon bulunamadı';
        posSelect.appendChild(opt);
        return;
    }

    const defaultOpt = document.createElement('option');
    defaultOpt.value = '';
    defaultOpt.textContent = 'Pozisyon seçiniz';
    posSelect.appendChild(defaultOpt);

    filtered.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.title + (p.level ? ' (' + p.level + ')' : '');
        if (String(p.id) === String(initialPositionId)) {
            opt.selected = true;
        }
        posSelect.appendChild(opt);
    });
}

function handleStatusChange() {
    const statusSelect = document.getElementById('status');
    const termInput = document.getElementById('termination_date');
    const termReqIndicator = document.getElementById('term_req_indicator');
    const isTerminated = statusSelect.value === 'TERMINATED';

    if (isTerminated) {
        termInput.required = true;
        termInput.disabled = false;
        termReqIndicator.style.display = 'inline';
    } else {
        termInput.required = false;
        termReqIndicator.style.display = 'none';
    }
}

// Sayfa yüklendiğinde pozisyonları ve durum durumunu başlat
document.addEventListener('DOMContentLoaded', function() {
    handleDepartmentChange();
    handleStatusChange();
});
</script>

