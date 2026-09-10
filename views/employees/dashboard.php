<?php

$pageTitle = 'Çalışan Dashboardu';
$activePage = 'employee-dashboard';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';

$formatDate = static function (?string $date): string {
    if (empty($date)) {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date('d.m.Y', $ts) : $date;
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

$totalEmployees = max(1, (int)($kpis['total'] ?? 0));
?>

<main class="main-content" style="max-width: 1400px; padding: 24px 32px;">

    <!-- 1. ÜST BAŞLIK VE AKSİYONLAR -->
    <header class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; background: #ffffff; padding: 20px 24px; border-radius: 14px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 24px;">📊</span>
                <h1 style="font-size: 22px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
                    Çalışan Dashboardu
                </h1>
            </div>
            <p class="page-description" style="font-size: 13px; color: #64748b; margin: 4px 0 0 32px;">
                Personel, vardiya ve zimmet durumunu tek ekranda takip edin. &bull; <strong style="color: #2563eb;"><?= date('d.m.Y') ?></strong>
            </p>
        </div>

        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <div style="background: #f1f5f9; border: 1px solid #cbd5e1; padding: 7px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 700; color: #334155; display: inline-flex; align-items: center; gap: 6px;">
                <span>📅</span> Bugün: <?= date('d.m.Y') ?>
            </div>
            <a href="/stok-takip/public/employees" class="button button-primary" style="height: 38px; padding: 0 16px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                <span>👥</span> Çalışan Listesi
            </a>
        </div>
    </header>

    <!-- 2. 1. SATIR: 5 KPI KARTI -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        
        <!-- TOPLAM ÇALIŞAN -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; border-left: 4px solid #2563eb; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #2563eb; text-transform: uppercase; letter-spacing: 0.04em;">
                👥 TOPLAM ÇALIŞAN
            </div>
            <div style="font-size: 26px; font-weight: 900; color: #1e3a8a; margin: 6px 0 2px 0;">
                <?= number_format((int)($kpis['total'] ?? 0), 0, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #64748b;">
                Kayıtlı Tüm Personel
            </div>
        </div>

        <!-- AKTİF -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; border-left: 4px solid #16a34a; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #16a34a; text-transform: uppercase; letter-spacing: 0.04em;">
                🟢 AKTİF PERSONEL
            </div>
            <div style="font-size: 26px; font-weight: 900; color: #15803d; margin: 6px 0 2px 0;">
                <?= number_format((int)($kpis['active'] ?? 0), 0, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #16a34a; font-weight: 600;">
                %<?= round(((int)($kpis['active'] ?? 0) / $totalEmployees) * 100, 1) ?> Görevde
            </div>
        </div>

        <!-- İZİNLİ -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; border-left: 4px solid #d97706; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #d97706; text-transform: uppercase; letter-spacing: 0.04em;">
                🏖️ İZİNLİ PERSONEL
            </div>
            <div style="font-size: 26px; font-weight: 900; color: #b45309; margin: 6px 0 2px 0;">
                <?= number_format((int)($kpis['on_leave'] ?? 0), 0, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #64748b;">
                Yıllık / Raporlu İzinli
            </div>
        </div>

        <!-- ASKIDA -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; border-left: 4px solid #ea580c; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #ea580c; text-transform: uppercase; letter-spacing: 0.04em;">
                ⏸️ ASKIDA PERSONEL
            </div>
            <div style="font-size: 26px; font-weight: 900; color: #c2410c; margin: 6px 0 2px 0;">
                <?= number_format((int)($kpis['suspended'] ?? 0), 0, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #64748b;">
                Geçici Pasif / Askıda
            </div>
        </div>

        <!-- İŞTEN AYRILMIŞ -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; border-left: 4px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em;">
                🛑 İŞTEN AYRILAN
            </div>
            <div style="font-size: 26px; font-weight: 900; color: #475569; margin: 6px 0 2px 0;">
                <?= number_format((int)($kpis['terminated'] ?? 0), 0, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #64748b;">
                Eski / Ayrılmış Personel
            </div>
        </div>

    </div>

    <!-- 3. 2. SATIR: BUGÜNKÜ VARDİYA & DEPARTMAN DAĞILIMI -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(480px, 1fr)); gap: 24px; margin-bottom: 24px;">
        
        <!-- SOL: BUGÜNKÜ VARDİYA DAĞILIMI -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9;">
                <div>
                    <h2 style="font-size: 15.5px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                        <span>⏰</span> Bugünkü Vardiya Dağılımı
                    </h2>
                    <p style="font-size: 12px; color: #64748b; margin: 2px 0 0 0;">
                        Günlük atama ve varsayılan vardiya harmanlanmış iş gücü dağılımı
                    </p>
                </div>
                <span style="font-size: 11.5px; font-weight: 700; background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; padding: 3px 8px; border-radius: 6px;">
                    Bugün
                </span>
            </div>

            <div style="display: flex; flex-direction: column; gap: 14px;">
                <?php foreach ($todayShifts as $shift): 
                    $count = (int)$shift['total_count'];
                    $actCount = (int)$shift['active_count'];
                    $dailyCount = (int)$shift['daily_assigned_count'];
                    $fallbackCount = (int)$shift['fallback_count'];
                    $percent = round(($count / $totalEmployees) * 100, 1);
                    
                    $isNoShift = ($shift['shift_code'] === 'NO_SHIFT');
                    $badgeBg = $isNoShift ? '#f1f5f9' : '#e0f2fe';
                    $badgeColor = $isNoShift ? '#475569' : '#0369a1';
                    $barColor = $isNoShift ? '#94a3b8' : '#0284c7';
                ?>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-family: monospace; font-size: 11.5px; font-weight: 700; background: <?= $badgeBg ?>; color: <?= $badgeColor ?>; padding: 2px 6px; border-radius: 4px;">
                                    <?= htmlspecialchars($shift['shift_code']) ?>
                                </span>
                                <span style="font-weight: 800; font-size: 13.5px; color: #0f172a;">
                                    <?= htmlspecialchars($shift['shift_name']) ?>
                                </span>
                                <?php if (!empty($shift['start_time'])): ?>
                                    <span style="font-size: 11.5px; color: #64748b;">
                                        (<?= substr($shift['start_time'], 0, 5) ?> - <?= substr($shift['end_time'], 0, 5) ?>)
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div style="text-align: right;">
                                <span style="font-size: 15px; font-weight: 900; color: #0f172a;"><?= $count ?></span>
                                <span style="font-size: 12px; color: #64748b;">kişi</span>
                                <span style="font-size: 11.5px; font-weight: 700; color: #16a34a; margin-left: 6px;">(<?= $actCount ?> aktif)</span>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div style="height: 7px; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin-bottom: 6px;">
                            <div style="width: <?= $percent ?>%; height: 100%; background: <?= $barColor ?>; border-radius: 4px;"></div>
                        </div>

                        <div style="display: flex; justify-content: space-between; font-size: 11px; color: #64748b;">
                            <span>Fabrika Payı: <b>%<?= $percent ?></b></span>
                            <span>
                                <?php if ($dailyCount > 0): ?>
                                    <b style="color: #2563eb;"><?= $dailyCount ?> Günlük Atama</b> &bull;
                                <?php endif; ?>
                                <?= $fallbackCount ?> Varsayılan
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- SAĞ: DEPARTMAN DAĞILIMI -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9;">
                <div>
                    <h2 style="font-size: 15.5px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                        <span>🏢</span> Departman Dağılımı
                    </h2>
                    <p style="font-size: 12px; color: #64748b; margin: 2px 0 0 0;">
                        Departman bazlı toplam personel ve aktif çalışan kapasitesi
                    </p>
                </div>
                <span style="font-size: 11.5px; font-weight: 700; background: #f1f5f9; color: #475569; padding: 3px 8px; border-radius: 6px;">
                    Toplam <?= count($departments) ?> Departman
                </span>
            </div>

            <div style="display: flex; flex-direction: column; gap: 10px; max-height: 380px; overflow-y: auto; padding-right: 4px;">
                <?php foreach ($departments as $dept): 
                    $deptTotal = (int)$dept['total_employees'];
                    $deptActive = (int)$dept['active_employees'];
                    $deptPercent = round(($deptTotal / $totalEmployees) * 100, 1);
                ?>
                    <div style="padding: 8px 12px; background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 8px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12.5px; margin-bottom: 5px;">
                            <div>
                                <span style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($dept['name']) ?></span>
                                <span style="font-family: monospace; font-size: 10.5px; color: #64748b; margin-left: 4px;">(<?= htmlspecialchars($dept['code']) ?>)</span>
                            </div>
                            <div>
                                <span style="font-weight: 800; color: #0f172a;"><?= $deptTotal ?></span>
                                <span style="color: #64748b; font-size: 11.5px;">kişi</span>
                                <span style="color: #16a34a; font-size: 11.5px; font-weight: 700; margin-left: 4px;">(<?= $deptActive ?> aktif)</span>
                            </div>
                        </div>
                        <div style="height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden;">
                            <div style="width: <?= $deptPercent ?>%; height: 100%; background: #3b82f6; border-radius: 3px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- 4. 3. SATIR: DEPARTMAN x VARDİYA MATRİSİ & ZİMMETLİ VARLIKLAR -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(480px, 1fr)); gap: 24px; margin-bottom: 24px;">
        
        <!-- SOL: DEPARTMAN x VARDİYA MATRİSİ -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9;">
                <div>
                    <h2 style="font-size: 15.5px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                        <span>📋</span> Departman &times; Vardiya Matrisi
                    </h2>
                    <p style="font-size: 12px; color: #64748b; margin: 2px 0 0 0;">
                        Departmanların bugünkü vardiya doluluk dağılımı
                    </p>
                </div>
            </div>

            <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 12px; text-align: left;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-weight: 800; font-size: 11px; text-transform: uppercase;">
                            <th style="padding: 9px 12px;">Departman</th>
                            <th style="padding: 9px 10px; text-align: center; color: #0284c7;">1. Vardiya</th>
                            <th style="padding: 9px 10px; text-align: center; color: #d97706;">2. Vardiya</th>
                            <th style="padding: 9px 10px; text-align: center; color: #7c3aed;">3. Vardiya</th>
                            <th style="padding: 9px 10px; text-align: center; color: #64748b;">Vardiyasız</th>
                            <th style="padding: 9px 12px; text-align: right;">Toplam</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departmentMatrix as $row): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 8px 12px; font-weight: 700; color: #0f172a;">
                                    <?= htmlspecialchars($row['department_name']) ?>
                                </td>
                                <td style="padding: 8px 10px; text-align: center; font-weight: 700; color: #0284c7;">
                                    <?= (int)$row['shift_1_count'] > 0 ? (int)$row['shift_1_count'] : '<span style="color:#cbd5e1;">-</span>' ?>
                                </td>
                                <td style="padding: 8px 10px; text-align: center; font-weight: 700; color: #d97706;">
                                    <?= (int)$row['shift_2_count'] > 0 ? (int)$row['shift_2_count'] : '<span style="color:#cbd5e1;">-</span>' ?>
                                </td>
                                <td style="padding: 8px 10px; text-align: center; font-weight: 700; color: #7c3aed;">
                                    <?= (int)$row['shift_3_count'] > 0 ? (int)$row['shift_3_count'] : '<span style="color:#cbd5e1;">-</span>' ?>
                                </td>
                                <td style="padding: 8px 10px; text-align: center; font-weight: 600; color: #64748b;">
                                    <?= (int)$row['no_shift_count'] > 0 ? (int)$row['no_shift_count'] : '<span style="color:#cbd5e1;">-</span>' ?>
                                </td>
                                <td style="padding: 8px 12px; text-align: right; font-weight: 800; color: #0f172a;">
                                    <?= (int)$row['total_employees'] ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SAĞ: ZİMMETLİ VARLIKLAR & ENVANTER ENTEGRASYONU -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9;">
                <div>
                    <h2 style="font-size: 15.5px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                        <span>📦</span> Zimmetli Varlıklar &amp; Ekipman
                    </h2>
                    <p style="font-size: 12px; color: #64748b; margin: 2px 0 0 0;">
                        Personel üzerine kayıtlı aktif envanter demirbaş ve cihazları
                    </p>
                </div>
                <a href="/stok-takip/public/inventory" style="font-size: 12px; font-weight: 700; color: #2563eb; text-decoration: none;">
                    Envanter &rarr;
                </a>
            </div>

            <!-- Zimmet KPI Grid -->
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 18px;">
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 12px 14px;">
                    <div style="font-size: 11px; font-weight: 800; color: #15803d; text-transform: uppercase;">TOPLAM ZİMMETLİ</div>
                    <div style="font-size: 22px; font-weight: 900; color: #166534; margin: 4px 0 0 0;">
                        <?= (int)($inventorySummary['total_assigned_assets'] ?? 0) ?> <small style="font-size: 12px; font-weight: 600;">Varlık</small>
                    </div>
                </div>

                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 12px 14px;">
                    <div style="font-size: 11px; font-weight: 800; color: #1d4ed8; text-transform: uppercase;">ZİMMETLİ PERSONEL</div>
                    <div style="font-size: 22px; font-weight: 900; color: #1e40af; margin: 4px 0 0 0;">
                        <?= (int)($inventorySummary['employees_with_assets'] ?? 0) ?> <small style="font-size: 12px; font-weight: 600;">Çalışan</small>
                    </div>
                </div>
            </div>

            <!-- Durum Dağılım Listesi -->
            <div style="display: flex; flex-direction: column; gap: 10px; font-size: 12.5px;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <span style="color: #475569; font-weight: 600;">📋 Tahsisli (ASSIGNED):</span>
                    <span style="font-weight: 800; color: #d97706;"><?= (int)($inventorySummary['status_assigned_count'] ?? 0) ?> adet</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <span style="color: #475569; font-weight: 600;">⚡ Kullanımda (IN_USE):</span>
                    <span style="font-weight: 800; color: #16a34a;"><?= (int)($inventorySummary['status_in_use_count'] ?? 0) ?> adet</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <span style="color: #475569; font-weight: 600;">🛠️ Bakımda (IN_REPAIR):</span>
                    <span style="font-weight: 800; color: #dc2626;"><?= (int)($inventorySummary['status_in_repair_count'] ?? 0) ?> adet</span>
                </div>
            </div>
        </div>

    </div>

    <!-- 5. 4. SATIR: VARDİYASIZ / TAKİP GEREKTİREN PERSONEL -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; gap: 8px;">
            <div>
                <h2 style="font-size: 15.5px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <span>⚠️</span> Vardiyasız / Takip Gerektiren Personel
                </h2>
                <p style="font-size: 12px; color: #64748b; margin: 2px 0 0 0;">
                    Bugün için ne günlük ataması ne de varsayılan vardiyası bulunan personeller
                </p>
            </div>
            <span style="font-size: 12px; font-weight: 700; background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; padding: 3px 10px; border-radius: 6px;">
                Toplam <?= count($unassignedEmployees) ?> Kayıt
            </span>
        </div>

        <?php 
        $activeUnassigned = array_filter($unassignedEmployees, fn($e) => $e['status'] === 'ACTIVE');
        if (!empty($activeUnassigned)): ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #ef4444; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                <span>🚨</span>
                <span>Dikkat: Fiilen <b>AKTİF</b> görevde olup vardiyası atanmamış <b><?= count($activeUnassigned) ?></b> çalışan bulunmaktadır.</span>
            </div>
        <?php endif; ?>

        <?php if (empty($unassignedEmployees)): ?>
            <div style="background: #f0fdf4; border: 1px dashed #86efac; border-radius: 10px; padding: 24px; text-align: center; color: #166534;">
                <div style="font-size: 24px; margin-bottom: 4px;">✅</div>
                <div style="font-weight: 700; font-size: 13.5px;">Takip gerektiren vardiyasız aktif çalışan bulunmuyor.</div>
                <div style="font-size: 12px; color: #15803d; margin-top: 2px;">Tüm personellerin günlük veya varsayılan vardiya tanımları eksiksizdir.</div>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 12.5px; text-align: left;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-weight: 800; font-size: 11px; text-transform: uppercase;">
                            <th style="padding: 10px 14px;">Sicil No</th>
                            <th style="padding: 10px 14px;">Ad Soyad</th>
                            <th style="padding: 10px 14px;">Departman</th>
                            <th style="padding: 10px 14px;">Pozisyon</th>
                            <th style="padding: 10px 14px;">Çalışma Durumu</th>
                            <th style="padding: 10px 14px; text-align: right;">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unassignedEmployees as $emp): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 10px 14px; white-space: nowrap;">
                                    <span style="font-family: monospace; font-size: 11.5px; font-weight: 700; background: #f1f5f9; color: #0f172a; padding: 2px 6px; border-radius: 4px; border: 1px solid #cbd5e1;">
                                        <?= htmlspecialchars($emp['registration_no']) ?>
                                    </span>
                                </td>
                                <td style="padding: 10px 14px; font-weight: 700; color: #0f172a;">
                                    <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                </td>
                                <td style="padding: 10px 14px; color: #334155;">
                                    <?= htmlspecialchars($emp['department_name']) ?>
                                </td>
                                <td style="padding: 10px 14px; color: #64748b;">
                                    <?= htmlspecialchars($emp['position_title']) ?>
                                </td>
                                <td style="padding: 10px 14px;">
                                    <span class="status-badge <?= $statusBadges[$emp['status']] ?? 'status-badge-info' ?>" style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 6px;">
                                        <?= htmlspecialchars($statusLabels[$emp['status']] ?? $emp['status']) ?>
                                    </span>
                                </td>
                                <td style="padding: 10px 14px; text-align: right; white-space: nowrap;">
                                    <a href="/stok-takip/public/employees/show?id=<?= (int)$emp['id'] ?>" class="button button-secondary" style="padding: 4px 10px; font-size: 11.5px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                        <span>🔍</span> Detay
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- 6. 5. SATIR: YAKLAŞAN 7 GÜNLÜK VARDİYA PLANI -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; gap: 8px;">
            <div>
                <h2 style="font-size: 15.5px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <span>📅</span> Yaklaşan 7 Günlük Vardiya Planı
                </h2>
                <p style="font-size: 12px; color: #64748b; margin: 2px 0 0 0;">
                    Önümüzdeki 1 hafta için sisteme girilmiş özel günlük vardiya atamaları (Bugünü dahil etmez)
                </p>
            </div>
            <span style="font-size: 11.5px; font-weight: 700; background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; padding: 3px 8px; border-radius: 6px;">
                Gelecek 7 Gün
            </span>
        </div>

        <?php if (empty($upcomingPlan)): ?>
            <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 28px 20px; text-align: center; color: #64748b;">
                <div style="font-size: 24px; margin-bottom: 4px;">🗓️</div>
                <div style="font-weight: 700; font-size: 13.5px; color: #334155;">Önümüzdeki 7 gün için özel günlük vardiya ataması bulunmuyor.</div>
                <div style="font-size: 12px; color: #94a3b8; margin-top: 2px;">Tüm çalışanlar kendi varsayılan vardiya çizelgelerine göre görev yapacaktır.</div>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 12.5px; text-align: left;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-weight: 800; font-size: 11px; text-transform: uppercase;">
                            <th style="padding: 10px 14px;">Planlanan Tarih</th>
                            <th style="padding: 10px 14px;">Vardiya Kodu</th>
                            <th style="padding: 10px 14px;">Vardiya Adı &amp; Saatleri</th>
                            <th style="padding: 10px 14px; text-align: right;">Atanan Personel Sayısı</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingPlan as $plan): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 10px 14px; font-weight: 700; color: #0f172a; white-space: nowrap;">
                                    <?= $formatDate($plan['assigned_date']) ?>
                                </td>
                                <td style="padding: 10px 14px; white-space: nowrap;">
                                    <span style="font-family: monospace; font-size: 11px; font-weight: 700; background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 4px; border: 1px solid #bae6fd;">
                                        <?= htmlspecialchars($plan['shift_code']) ?>
                                    </span>
                                </td>
                                <td style="padding: 10px 14px;">
                                    <span style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($plan['shift_name']) ?></span>
                                    <span style="font-size: 11.5px; color: #64748b; margin-left: 6px;">
                                        (<?= substr($plan['start_time'], 0, 5) ?> - <?= substr($plan['end_time'], 0, 5) ?>)
                                    </span>
                                </td>
                                <td style="padding: 10px 14px; text-align: right; font-weight: 800; color: #2563eb;">
                                    <?= (int)$plan['employee_count'] ?> çalışan
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</main>
